<?php
/**
 * Página de administração de Filas e Rate Limiting
 *
 * @package PersonCashWallet
 * @since 1.4.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCW_Admin_Queue {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		
		// AJAX actions
		add_action( 'wp_ajax_pcw_save_whatsapp_number', array( $this, 'ajax_save_number' ) );
		add_action( 'wp_ajax_pcw_delete_whatsapp_number', array( $this, 'ajax_delete_number' ) );
		add_action( 'wp_ajax_pcw_get_queue_stats', array( $this, 'ajax_get_queue_stats' ) );
		add_action( 'wp_ajax_pcw_clear_failed_queue', array( $this, 'ajax_clear_failed_queue' ) );
		add_action( 'wp_ajax_pcw_retry_failed_queue', array( $this, 'ajax_retry_failed_queue' ) );
		add_action( 'wp_ajax_pcw_save_distribution_strategy', array( $this, 'ajax_save_distribution_strategy' ) );
		add_action( 'wp_ajax_pcw_get_whatsapp_number', array( $this, 'ajax_get_number' ) );
		add_action( 'wp_ajax_pcw_update_smtp_rate_limit', array( $this, 'ajax_update_smtp_rate_limit' ) );
		add_action( 'wp_ajax_pcw_get_queue_messages', array( $this, 'ajax_get_queue_messages' ) );
		add_action( 'wp_ajax_pcw_get_message_details', array( $this, 'ajax_get_message_details' ) );
		add_action( 'wp_ajax_pcw_process_queue_now', array( $this, 'ajax_process_queue_now' ) );
		add_action( 'wp_ajax_pcw_toggle_queue_pause', array( $this, 'ajax_toggle_queue_pause' ) );
		add_action( 'wp_ajax_pcw_save_queue_schedule', array( $this, 'ajax_save_queue_schedule' ) );
		add_action( 'wp_ajax_pcw_retry_single_message', array( $this, 'ajax_retry_single_message' ) );
		add_action( 'wp_ajax_pcw_get_templates', array( $this, 'ajax_get_templates' ) );
	}

	/**
	 * AJAX: Pausar/retomar fila de disparos
	 */
	public function ajax_toggle_queue_pause() {
		check_ajax_referer( 'pcw_queue', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$is_paused = get_option( 'pcw_queue_paused', false );

		if ( $is_paused ) {
			delete_option( 'pcw_queue_paused' );
			delete_option( 'pcw_queue_paused_at' );
			delete_option( 'pcw_queue_paused_by' );
			wp_send_json_success( array(
				'message' => __( 'Fila de disparos retomada!', 'person-cash-wallet' ),
				'paused'  => false,
			) );
		} else {
			update_option( 'pcw_queue_paused', true );
			update_option( 'pcw_queue_paused_at', current_time( 'mysql' ) );
			update_option( 'pcw_queue_paused_by', wp_get_current_user()->display_name );
			wp_send_json_success( array(
				'message' => __( 'Fila de disparos pausada!', 'person-cash-wallet' ),
				'paused'  => true,
			) );
		}
	}

	/**
	 * AJAX: Salvar configuração de horários da fila
	 */
	public function ajax_save_queue_schedule() {
		check_ajax_referer( 'pcw_queue', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$schedule = array(
			'enabled'    => isset( $_POST['enabled'] ) ? (bool) $_POST['enabled'] : false,
			'start_hour' => isset( $_POST['start_hour'] ) ? absint( $_POST['start_hour'] ) : 8,
			'end_hour'   => isset( $_POST['end_hour'] ) ? absint( $_POST['end_hour'] ) : 18,
			'days'       => isset( $_POST['days'] ) && is_array( $_POST['days'] ) ? array_map( 'absint', $_POST['days'] ) : array( 1, 2, 3, 4, 5 ),
		);

		// Validar horários
		if ( $schedule['start_hour'] > 23 ) {
			$schedule['start_hour'] = 8;
		}
		if ( $schedule['end_hour'] > 23 ) {
			$schedule['end_hour'] = 18;
		}
		if ( $schedule['start_hour'] >= $schedule['end_hour'] ) {
			wp_send_json_error( array( 'message' => __( 'O horário inicial deve ser menor que o final.', 'person-cash-wallet' ) ) );
		}

		// Validar dias (0=dom, 1=seg, ..., 6=sab)
		$schedule['days'] = array_filter( $schedule['days'], function( $d ) {
			return $d >= 0 && $d <= 6;
		} );

		if ( empty( $schedule['days'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Selecione pelo menos um dia da semana.', 'person-cash-wallet' ) ) );
		}

		update_option( 'pcw_queue_schedule', $schedule );

		wp_send_json_success( array(
			'message' => __( 'Horários de disparo salvos com sucesso!', 'person-cash-wallet' ),
		) );
	}

	/**
	 * AJAX: Processar fila agora (manualmente)
	 */
	public function ajax_process_queue_now() {
		check_ajax_referer( 'pcw_queue', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		// Verificar se a fila está pausada
		if ( get_option( 'pcw_queue_paused', false ) ) {
			wp_send_json_error( array( 'message' => __( 'A fila está pausada. Retome antes de processar.', 'person-cash-wallet' ) ) );
		}

		$queue_manager = PCW_Message_Queue_Manager::instance();
		
		// Contar mensagens pendentes antes
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_message_queue';
		$pending_before = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
		
		// Processar fila (force_schedule = true para ignorar restrição de horário quando admin força)
		$queue_manager->process_queue( true );
		
		// Contar mensagens processadas
		$pending_after = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
		$processed = $pending_before - $pending_after;
		
		// Contar sucessos e falhas
		$sent = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'sent' AND processed_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)" );
		$failed = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'failed' AND processed_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)" );

		wp_send_json_success( array(
			'message'   => sprintf(
				__( 'Fila processada! %d mensagens enviadas, %d falhadas, %d pendentes.', 'person-cash-wallet' ),
				$sent,
				$failed,
				$pending_after
			),
			'processed' => $processed,
			'sent'      => $sent,
			'failed'    => $failed,
			'pending'   => $pending_after,
		) );
	}

	/**
	 * Adicionar menu
	 */
	public function add_menu() {
		add_submenu_page(
			'pcw-dashboard',
			__( 'Filas & Rate Limiting', 'person-cash-wallet' ),
			__( 'Filas', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-queue',
			array( $this, 'render_page' ),
			35
		);
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts( $hook ) {
		// O hook pode variar dependendo do nome do menu pai
		if ( strpos( $hook, 'pcw-queue' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'pcw-queue',
			PCW_PLUGIN_URL . 'assets/js/admin-queue.js',
			array( 'jquery' ),
			PCW_VERSION,
			true
		);

		wp_localize_script(
			'pcw-queue',
			'pcwQueue',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pcw_queue' ),
				'i18n'    => array(
					'confirmDelete'     => __( 'Tem certeza que deseja excluir este número?', 'person-cash-wallet' ),
					'confirmClear'      => __( 'Tem certeza que deseja limpar as mensagens falhadas?', 'person-cash-wallet' ),
					'confirmRetry'      => __( 'Tem certeza que deseja reprocessar todas as mensagens falhadas?', 'person-cash-wallet' ),
					'saveSuccess'       => __( 'Salvo com sucesso!', 'person-cash-wallet' ),
					'deleteSuccess'     => __( 'Número excluído com sucesso!', 'person-cash-wallet' ),
					'error'             => __( 'Erro ao processar solicitação.', 'person-cash-wallet' ),
				),
			)
		);

		wp_enqueue_style(
			'pcw-queue',
			PCW_PLUGIN_URL . 'assets/css/admin-queue.css',
			array(),
			PCW_VERSION
		);
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		// Verificar se é para renderizar o relatório de um número
		if ( isset( $_GET['action'] ) && 'report' === $_GET['action'] && isset( $_GET['number_id'] ) ) {
			$this->render_number_report_page( absint( $_GET['number_id'] ) );
			return;
		}
		
		$queue_manager = PCW_Message_Queue_Manager::instance();
		
		// Garantir que a estrutura da tabela esteja atualizada
		$queue_manager->create_tables();
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'numbers';
		?>
		<div class="wrap pcw-queue-page">
			<div class="pcw-page-header">
				<div>
					<h1>
						<span class="dashicons dashicons-list-view"></span>
						<?php esc_html_e( 'Filas & Rate Limiting', 'person-cash-wallet' ); ?>
					</h1>
					<p class="description">
						<?php esc_html_e( 'Gerencie múltiplos números WhatsApp, configure rate limiting e monitore filas de envio.', 'person-cash-wallet' ); ?>
					</p>
				</div>
			</div>

			<nav class="nav-tab-wrapper">
				<a href="?page=pcw-queue&tab=numbers" class="nav-tab <?php echo $tab === 'numbers' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-phone"></span>
					<?php esc_html_e( 'Números WhatsApp', 'person-cash-wallet' ); ?>
				</a>
				<a href="?page=pcw-queue&tab=smtp" class="nav-tab <?php echo $tab === 'smtp' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-email"></span>
					<?php esc_html_e( 'Contas SMTP', 'person-cash-wallet' ); ?>
				</a>
				<a href="?page=pcw-queue&tab=queue" class="nav-tab <?php echo $tab === 'queue' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'Monitor de Filas', 'person-cash-wallet' ); ?>
				</a>
				<a href="?page=pcw-queue&tab=distribution" class="nav-tab <?php echo $tab === 'distribution' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-randomize"></span>
					<?php esc_html_e( 'Estratégias', 'person-cash-wallet' ); ?>
				</a>
			</nav>

			<div class="pcw-tab-content">
				<?php
				switch ( $tab ) {
					case 'smtp':
						$this->render_smtp_tab();
						break;
					case 'queue':
						$this->render_queue_tab();
						break;
					case 'distribution':
						$this->render_distribution_tab();
						break;
					case 'numbers':
					default:
						$this->render_numbers_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderizar aba de números WhatsApp
	 */
	private function render_numbers_tab() {
		global $wpdb;
		$queue_manager = PCW_Message_Queue_Manager::instance();
		$numbers = $queue_manager->get_numbers_stats();
		$queue_table = $wpdb->prefix . 'pcw_message_queue';

		// Buscar contadores reais da fila para cada número (fonte confiável)
		$real_counts = array();
		$now = current_time( 'mysql' );
		if ( ! empty( $numbers ) ) {
			$sent_counts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT from_number, 
						SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as real_sent,
						SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as real_failed,
						SUM(CASE WHEN status = 'sent' AND processed_at >= DATE_SUB(%s, INTERVAL 1 HOUR) THEN 1 ELSE 0 END) as real_sent_last_hour,
						SUM(CASE WHEN status = 'sent' AND DATE(processed_at) = CURDATE() THEN 1 ELSE 0 END) as real_sent_today
					FROM {$queue_table} 
					WHERE type IN ('whatsapp', 'whatsapp_template') AND from_number IS NOT NULL AND from_number != ''
					GROUP BY from_number",
					$now
				)
			);
			if ( $sent_counts ) {
				foreach ( $sent_counts as $row ) {
					$real_counts[ $row->from_number ] = array(
						'sent'            => (int) $row->real_sent,
						'failed'          => (int) $row->real_failed,
						'sent_last_hour'  => (int) $row->real_sent_last_hour,
						'sent_today'      => (int) $row->real_sent_today,
					);
				}
			}
		}
		?>
		<div class="pcw-numbers-section">
			<div class="pcw-section-header">
				<h2><?php esc_html_e( 'Números WhatsApp Configurados', 'person-cash-wallet' ); ?></h2>
				<button type="button" class="button button-primary" id="add-new-number">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Adicionar Número', 'person-cash-wallet' ); ?>
				</button>
			</div>

			<?php if ( empty( $numbers ) ) : ?>
				<div class="pcw-empty-state">
					<div class="pcw-empty-icon">
						<span class="dashicons dashicons-phone"></span>
					</div>
					<h3><?php esc_html_e( 'Nenhum número configurado', 'person-cash-wallet' ); ?></h3>
					<p><?php esc_html_e( 'Adicione números WhatsApp para distribuir o envio de mensagens e evitar bloqueios.', 'person-cash-wallet' ); ?></p>
					<button type="button" class="button button-primary button-hero" id="add-first-number">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Adicionar Primeiro Número', 'person-cash-wallet' ); ?>
					</button>
				</div>
			<?php else : ?>
				<div class="pcw-numbers-grid">
					<?php foreach ( $numbers as $number ) : ?>
						<div class="pcw-number-card" data-id="<?php echo esc_attr( $number->id ); ?>">
							<div class="pcw-number-header">
								<div class="pcw-number-title">
									<span class="pcw-number-icon">📱</span>
									<div>
										<h3>
											<?php echo esc_html( $number->name ); ?>
											<?php
											$provider = isset( $number->provider ) ? $number->provider : 'evolution';
											if ( PCW_Personizi_Integration::is_official_api( $provider ) ) : ?>
												<span class="pcw-badge pcw-badge-info" style="font-size: 10px; padding: 2px 6px; margin-left: 6px; vertical-align: middle;">API Oficial</span>
											<?php else : ?>
												<span class="pcw-badge" style="font-size: 10px; padding: 2px 6px; margin-left: 6px; vertical-align: middle; background: #e2e8f0; color: #64748b;">WhatsApp Web</span>
											<?php endif; ?>
										</h3>
										<p class="pcw-number-phone"><?php echo esc_html( $number->phone_number ); ?></p>
									</div>
								</div>
								<div class="pcw-number-status">
									<?php if ( $number->status === 'active' ) : ?>
										<span class="pcw-badge pcw-badge-success">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php esc_html_e( 'Ativo', 'person-cash-wallet' ); ?>
										</span>
									<?php else : ?>
										<span class="pcw-badge pcw-badge-inactive">
											<span class="dashicons dashicons-dismiss"></span>
											<?php esc_html_e( 'Inativo', 'person-cash-wallet' ); ?>
										</span>
									<?php endif; ?>
								</div>
							</div>

						<?php
						// Usar dados reais da fila (fonte confiável) em vez do contador cached
						$phone_key_stats = $number->phone_number;
						$real_sent_1h = isset( $real_counts[ $phone_key_stats ] ) ? $real_counts[ $phone_key_stats ]['sent_last_hour'] : 0;
						?>
						<div class="pcw-number-stats">
							<div class="pcw-stat">
								<div class="pcw-stat-label"><?php esc_html_e( 'Limite/Hora', 'person-cash-wallet' ); ?></div>
								<div class="pcw-stat-value"><?php echo esc_html( $number->rate_limit_hour ); ?> msgs</div>
							</div>
							<div class="pcw-stat">
								<div class="pcw-stat-label"><?php esc_html_e( 'Enviadas (1h)', 'person-cash-wallet' ); ?></div>
								<div class="pcw-stat-value pcw-stat-highlight">
									<?php echo esc_html( $real_sent_1h ); ?> / <?php echo esc_html( $number->rate_limit_hour ); ?>
								</div>
							</div>
							<div class="pcw-stat">
								<div class="pcw-stat-label"><?php esc_html_e( 'Peso (%)', 'person-cash-wallet' ); ?></div>
								<div class="pcw-stat-value"><?php echo esc_html( $number->distribution_weight ); ?>%</div>
							</div>
						</div>

							<?php
							$phone_key = $number->phone_number;
							$num_real_sent = isset( $real_counts[ $phone_key ] ) ? $real_counts[ $phone_key ]['sent'] : 0;
							$num_real_failed = isset( $real_counts[ $phone_key ] ) ? $real_counts[ $phone_key ]['failed'] : 0;
							?>
							<div class="pcw-number-stats-secondary">
								<div class="pcw-stat-item">
									<span class="dashicons dashicons-yes-alt"></span>
									<span><?php echo esc_html( number_format_i18n( $num_real_sent ) ); ?> enviadas</span>
								</div>
								<div class="pcw-stat-item pcw-stat-error">
									<span class="dashicons dashicons-dismiss"></span>
									<span><?php echo esc_html( number_format_i18n( $num_real_failed ) ); ?> falhadas</span>
								</div>
							</div>

						<div class="pcw-number-progress">
							<?php
							$percentage = $number->rate_limit_hour > 0 ? min( 100, ( $real_sent_1h / $number->rate_limit_hour ) * 100 ) : 0;
							$progress_class = $percentage >= 90 ? 'pcw-progress-danger' : ( $percentage >= 70 ? 'pcw-progress-warning' : 'pcw-progress-success' );
							?>
							<div class="pcw-progress-bar">
								<div class="pcw-progress-fill <?php echo esc_attr( $progress_class ); ?>" style="width: <?php echo esc_attr( $percentage ); ?>%"></div>
							</div>
							<p class="pcw-progress-label">
								<?php
								/* translators: %d: percentual do limite */
								echo esc_html( sprintf( __( '%d%% do limite horário utilizado', 'person-cash-wallet' ), round( $percentage ) ) );
								?>
							</p>
						</div>

							<div class="pcw-number-actions">
								<?php if ( PCW_Personizi_Integration::is_official_api( $provider ) ) : ?>
								<button type="button" class="button button-small pcw-view-templates" data-phone="<?php echo esc_attr( $number->phone_number ); ?>" data-name="<?php echo esc_attr( $number->name ); ?>">
									<span class="dashicons dashicons-media-text"></span>
									<?php esc_html_e( 'Templates', 'person-cash-wallet' ); ?>
								</button>
								<?php endif; ?>
								<button type="button" class="button button-small pcw-edit-number" data-id="<?php echo esc_attr( $number->id ); ?>">
									<span class="dashicons dashicons-edit"></span>
									<?php esc_html_e( 'Editar', 'person-cash-wallet' ); ?>
								</button>
								<button type="button" class="button button-small button-link-delete pcw-delete-number" data-id="<?php echo esc_attr( $number->id ); ?>">
									<span class="dashicons dashicons-trash"></span>
									<?php esc_html_e( 'Excluir', 'person-cash-wallet' ); ?>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Modal de Edição -->
		<?php
		// Buscar números disponíveis do Personizi
		$personizi = PCW_Personizi_Integration::instance();
		$personizi_accounts = $personizi->get_whatsapp_accounts();
		$has_accounts = ! is_wp_error( $personizi_accounts ) && ! empty( $personizi_accounts );
		
		// Buscar números já configurados para excluir da lista
		$configured_numbers = array();
		foreach ( $numbers as $num ) {
			$configured_numbers[] = $num->phone_number;
		}
		?>
		<div id="number-modal" class="pcw-modal" style="display: none;">
			<div class="pcw-modal-content">
				<div class="pcw-modal-header">
					<h2 id="modal-title"><?php esc_html_e( 'Adicionar Número WhatsApp', 'person-cash-wallet' ); ?></h2>
					<button type="button" class="pcw-modal-close">&times;</button>
				</div>
				<form id="number-form" class="pcw-modal-body">
					<input type="hidden" id="number_id" name="id" value="0">
					<input type="hidden" id="number_name" name="name" value="">
					<input type="hidden" id="number_provider" name="provider" value="evolution">
					<input type="hidden" id="number_account_id" name="account_id" value="">
					
					<?php if ( $has_accounts ) : ?>
						<div class="pcw-form-group">
							<label for="number_phone">
								<?php esc_html_e( 'Selecionar Número WhatsApp', 'person-cash-wallet' ); ?>
								<span class="required">*</span>
							</label>
							<select id="number_phone" name="phone_number" class="widefat" required>
								<option value=""><?php esc_html_e( '-- Selecione um número --', 'person-cash-wallet' ); ?></option>
								<?php foreach ( $personizi_accounts as $account ) : 
									$already_configured = in_array( $account['phone_number'], $configured_numbers, true );
									$disabled = $already_configured ? 'disabled' : '';
									$suffix = $already_configured ? ' (' . __( 'Já configurado', 'person-cash-wallet' ) . ')' : '';
									$status_icon = $account['status'] === 'active' ? '✅' : '⚠️';
									$acct_provider = isset( $account['provider'] ) ? $account['provider'] : 'evolution';
									$acct_id = isset( $account['id'] ) ? $account['id'] : '';
									$provider_label = PCW_Personizi_Integration::is_official_api( $acct_provider ) ? ' [API Oficial]' : '';
								?>
									<option value="<?php echo esc_attr( $account['phone_number'] ); ?>" 
										data-name="<?php echo esc_attr( $account['name'] ); ?>"
										data-status="<?php echo esc_attr( $account['status'] ); ?>"
										data-provider="<?php echo esc_attr( $acct_provider ); ?>"
										data-account-id="<?php echo esc_attr( $acct_id ); ?>"
										<?php echo $disabled; ?>>
										<?php echo esc_html( $status_icon . ' ' . $account['name'] . ' (' . $account['phone_number'] . ')' . $provider_label . $suffix ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Números disponíveis na sua conta Personizi.', 'person-cash-wallet' ); ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-settings&tab=personizi' ) ); ?>">
									<?php esc_html_e( 'Configurar integração', 'person-cash-wallet' ); ?>
								</a>
							</p>
						</div>

						<!-- Preview do número selecionado -->
						<div id="number-preview" class="pcw-number-preview" style="display: none;">
							<div class="pcw-preview-card">
								<span class="pcw-preview-icon">📱</span>
								<div class="pcw-preview-info">
									<strong id="preview-name">-</strong>
									<span id="preview-phone">-</span>
								</div>
								<span id="preview-status" class="pcw-badge pcw-badge-success">Ativo</span>
							</div>
						</div>
					<?php else : ?>
						<div class="notice notice-warning inline" style="margin: 0 0 20px;">
							<p>
								<strong><?php esc_html_e( 'Nenhum número disponível!', 'person-cash-wallet' ); ?></strong><br>
								<?php esc_html_e( 'Configure a integração com o Personizi primeiro.', 'person-cash-wallet' ); ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-settings&tab=personizi' ) ); ?>" class="button button-small" style="margin-left: 10px;">
									<?php esc_html_e( 'Configurar Personizi', 'person-cash-wallet' ); ?>
								</a>
							</p>
						</div>
					<?php endif; ?>

					<hr style="margin: 20px 0; border: none; border-top: 1px solid #e2e8f0;">

					<h4 style="margin: 0 0 15px; color: #1e293b;">
						<span class="dashicons dashicons-admin-settings" style="margin-right: 6px;"></span>
						<?php esc_html_e( 'Configurações de Rate Limiting', 'person-cash-wallet' ); ?>
					</h4>

					<div class="pcw-form-row">
						<div class="pcw-form-group">
							<label for="number_rate_limit">
								<?php esc_html_e( 'Limite de Disparos/Hora', 'person-cash-wallet' ); ?>
								<span class="required">*</span>
							</label>
							<input type="number" id="number_rate_limit" name="rate_limit_hour" class="widefat" 
								value="60" min="1" max="1000" required>
							<p class="description"><?php esc_html_e( 'Máximo de mensagens por hora', 'person-cash-wallet' ); ?></p>
						</div>

						<div class="pcw-form-group">
							<label for="number_min_interval">
								<?php esc_html_e( 'Intervalo Mínimo (seg)', 'person-cash-wallet' ); ?>
								<span class="required">*</span>
							</label>
							<input type="number" id="number_min_interval" name="min_interval_seconds" class="widefat" 
								value="30" min="0" max="3600" required>
							<p class="description"><?php esc_html_e( 'Tempo mínimo entre disparos (segundos). Ex: 30 = 1 msg a cada 30s', 'person-cash-wallet' ); ?></p>
						</div>
					</div>

					<div class="pcw-form-row">
						<div class="pcw-form-group">
							<label for="number_weight">
								<?php esc_html_e( 'Peso (%)', 'person-cash-wallet' ); ?>
								<span class="required">*</span>
							</label>
							<input type="number" id="number_weight" name="distribution_weight" class="widefat" 
								value="100" min="0" max="100" required>
							<p class="description"><?php esc_html_e( 'Para estratégia "Por Peso"', 'person-cash-wallet' ); ?></p>
						</div>

					<div class="pcw-form-row">
						<div class="pcw-form-group">
							<label for="number_status">
								<?php esc_html_e( 'Status', 'person-cash-wallet' ); ?>
							</label>
							<select id="number_status" name="status" class="widefat">
								<option value="active"><?php esc_html_e( 'Ativo', 'person-cash-wallet' ); ?></option>
								<option value="inactive"><?php esc_html_e( 'Inativo', 'person-cash-wallet' ); ?></option>
							</select>
						</div>

						<div class="pcw-form-group" style="display: flex; align-items: center; padding-top: 28px;">
							<label style="margin: 0;">
								<input type="checkbox" id="number_distribution_enabled" name="distribution_enabled" value="1" checked>
								<?php esc_html_e( 'Usar na distribuição', 'person-cash-wallet' ); ?>
							</label>
						</div>
					</div>
				</form>
				<div class="pcw-modal-footer">
					<button type="button" class="button button-large" id="cancel-number"><?php esc_html_e( 'Cancelar', 'person-cash-wallet' ); ?></button>
					<button type="submit" form="number-form" class="button button-primary button-large" id="save-number" <?php echo ! $has_accounts ? 'disabled' : ''; ?>>
						<?php esc_html_e( 'Salvar Número', 'person-cash-wallet' ); ?>
					</button>
				</div>
			</div>
		</div>

		<script type="text/javascript">
		(function($) {
			$(document).ready(function() {
				// Abrir modal para adicionar
				$(document).on('click', '#add-new-number, #add-first-number', function(e) {
					e.preventDefault();
					$('#number-form')[0].reset();
					$('#number_id').val('0');
					$('#number_name').val('');
					$('#number_phone').prop('disabled', false);
					$('#number_distribution_enabled').prop('checked', true);
					$('#number-preview').hide();
					$('#modal-title').text('<?php echo esc_js( __( 'Adicionar Número WhatsApp', 'person-cash-wallet' ) ); ?>');
					$('#number-modal').fadeIn(200);
				});

				// Abrir modal para editar
				$(document).on('click', '.pcw-edit-number', function(e) {
					e.preventDefault();
					var id = $(this).data('id');
					var btn = $(this);
					
					btn.prop('disabled', true);
					
					$.post(pcwQueue.ajaxUrl, {
						action: 'pcw_get_whatsapp_number',
						nonce: pcwQueue.nonce,
						id: id
					}, function(response) {
						btn.prop('disabled', false);
						
						if (response.success && response.data.number) {
							var num = response.data.number;
							
							$('#number_id').val(num.id);
							$('#number_phone').val(num.phone_number).prop('disabled', true);
							$('#number_name').val(num.name);
							$('#number_provider').val(num.provider || 'evolution');
							$('#number_account_id').val(num.account_id || '');
							$('#number_rate_limit').val(num.rate_limit_hour);
							$('#number_min_interval').val(num.min_interval_seconds || 30);
							$('#number_weight').val(num.distribution_weight);
							$('#number_status').val(num.status);
							$('#number_distribution_enabled').prop('checked', num.distribution_enabled == 1);
							
							// Mostrar preview
							$('#preview-name').text(num.name);
							$('#preview-phone').text(num.phone_number);
							$('#preview-status')
								.text(num.status === 'active' ? 'Ativo' : 'Inativo')
								.removeClass('pcw-badge-success pcw-badge-inactive')
								.addClass(num.status === 'active' ? 'pcw-badge-success' : 'pcw-badge-inactive');
							$('#number-preview').show();
							
							$('#modal-title').text('<?php echo esc_js( __( 'Editar Número WhatsApp', 'person-cash-wallet' ) ); ?>');
							$('#number-modal').fadeIn(200);
						} else {
							alert(response.data.message || 'Erro ao carregar dados');
						}
					}).fail(function() {
						btn.prop('disabled', false);
						alert('Erro de conexão');
					});
				});

				// Quando selecionar um número, preencher nome e mostrar preview
				$(document).on('change', '#number_phone', function() {
					var selected = $(this).find('option:selected');
					var name = selected.data('name') || '';
					var phone = $(this).val();
					var status = selected.data('status') || 'active';
					var provider = selected.data('provider') || 'evolution';
					var accountId = selected.data('account-id') || '';

					$('#number_name').val(name);
					$('#number_provider').val(provider);
					$('#number_account_id').val(accountId);

					var isOfficialApi = (provider === 'notificame' || provider === 'meta_cloud' || provider === 'meta_coex');
					<?php
					$def_evolution  = get_option( 'pcw_default_limits_evolution', array( 'rate_limit_hour' => 60, 'min_interval_seconds' => 30 ) );
					$def_notificame = get_option( 'pcw_default_limits_notificame', array( 'rate_limit_hour' => 500, 'min_interval_seconds' => 2 ) );
					?>
					if (isOfficialApi) {
						$('#number_rate_limit').val(<?php echo (int) $def_notificame['rate_limit_hour']; ?>);
						$('#number_min_interval').val(<?php echo (int) $def_notificame['min_interval_seconds']; ?>);
					} else {
						$('#number_rate_limit').val(<?php echo (int) $def_evolution['rate_limit_hour']; ?>);
						$('#number_min_interval').val(<?php echo (int) $def_evolution['min_interval_seconds']; ?>);
					}

					if (phone) {
						$('#preview-name').text(name);
						$('#preview-phone').text(phone);
						$('#preview-status')
							.text(status === 'active' ? 'Ativo' : 'Inativo')
							.removeClass('pcw-badge-success pcw-badge-inactive')
							.addClass(status === 'active' ? 'pcw-badge-success' : 'pcw-badge-inactive');
						$('#number-preview').slideDown(200);
					} else {
						$('#number-preview').slideUp(200);
					}
				});

				// Fechar modal
				$(document).on('click', '.pcw-modal-close, #cancel-number', function() {
					$('#number-modal').fadeOut(200);
					// Reabilitar select do telefone
					$('#number_phone').prop('disabled', false);
				});

				// Fechar ao clicar fora
				$('#number-modal').on('click', function(e) {
					if ($(e.target).is('#number-modal')) {
						$(this).fadeOut(200);
						$('#number_phone').prop('disabled', false);
					}
				});

				// Deletar número
				$(document).on('click', '.pcw-delete-number', function(e) {
					e.preventDefault();
					if (!confirm('<?php echo esc_js( __( 'Tem certeza que deseja excluir este número?', 'person-cash-wallet' ) ); ?>')) {
						return;
					}

					var card = $(this).closest('.pcw-number-card');
					var id = $(this).data('id');
					var btn = $(this);

					btn.prop('disabled', true);

					$.post(pcwQueue.ajaxUrl, {
						action: 'pcw_delete_whatsapp_number',
						nonce: pcwQueue.nonce,
						id: id
					}, function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || 'Erro ao excluir');
							btn.prop('disabled', false);
						}
					}).fail(function() {
						alert('Erro de conexão');
						btn.prop('disabled', false);
					});
				});

				// Salvar número
				$('#number-form').on('submit', function(e) {
					e.preventDefault();

					var submitBtn = $('#save-number');
					var originalText = submitBtn.html();
					
					// Temporariamente habilitar o select para enviar o valor
					var phoneSelect = $('#number_phone');
					var wasDisabled = phoneSelect.prop('disabled');
					phoneSelect.prop('disabled', false);
					
					submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Salvando...');

					$.post(pcwQueue.ajaxUrl, {
						action: 'pcw_save_whatsapp_number',
						nonce: pcwQueue.nonce,
						id: $('#number_id').val(),
						phone_number: $('#number_phone').val(),
						name: $('#number_name').val(),
						provider: $('#number_provider').val(),
						account_id: $('#number_account_id').val(),
						rate_limit_hour: $('#number_rate_limit').val(),
						min_interval_seconds: $('#number_min_interval').val(),
						distribution_weight: $('#number_weight').val(),
						status: $('#number_status').val(),
						distribution_enabled: $('#number_distribution_enabled').is(':checked') ? 1 : 0
					}, function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || 'Erro ao salvar');
							submitBtn.prop('disabled', false).html(originalText);
							phoneSelect.prop('disabled', wasDisabled);
						}
					}).fail(function() {
						alert('Erro de conexão');
						submitBtn.prop('disabled', false).html(originalText);
						phoneSelect.prop('disabled', wasDisabled);
					});
				});
				// Ver Templates (para números API Oficial)
				$(document).on('click', '.pcw-view-templates', function(e) {
					e.preventDefault();
					var phone = $(this).data('phone');
					var name = $(this).data('name');
					var btn = $(this);

					btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>');

					$.post(pcwQueue.ajaxUrl, {
						action: 'pcw_get_templates',
						nonce: pcwQueue.nonce,
						phone: phone
					}, function(response) {
						btn.prop('disabled', false).html('<span class="dashicons dashicons-media-text"></span> Templates');

						if (response.success && response.data.templates) {
							var tpls = response.data.templates;
							var html = '<div class="pcw-templates-list">';

							if (tpls.length === 0) {
								html += '<div class="pcw-empty-state" style="padding: 20px; text-align: center;">';
								html += '<p>Nenhum template encontrado para este número.</p></div>';
							} else {
								html += '<table class="wp-list-table widefat striped" style="margin-top: 10px;">';
								html += '<thead><tr><th>Nome</th><th>Categoria</th><th>Idioma</th><th>Status</th><th>Preview</th></tr></thead><tbody>';
								tpls.forEach(function(t) {
									var statusBadge = t.status === 'APPROVED'
										? '<span class="pcw-badge pcw-badge-success">Aprovado</span>'
										: '<span class="pcw-badge pcw-badge-inactive">' + (t.status || 'N/A') + '</span>';
									var body = t.body_text || t.body || '-';
									if (body.length > 80) body = body.substring(0, 80) + '...';
									html += '<tr>';
									html += '<td><strong>' + $('<span>').text(t.name).html() + '</strong></td>';
									html += '<td>' + $('<span>').text(t.category || '-').html() + '</td>';
									html += '<td>' + $('<span>').text(t.language || 'pt_BR').html() + '</td>';
									html += '<td>' + statusBadge + '</td>';
									html += '<td style="max-width: 200px; word-break: break-word; font-size: 12px; color: #64748b;">' + $('<span>').text(body).html() + '</td>';
									html += '</tr>';
								});
								html += '</tbody></table>';
							}

							html += '</div>';

							$('#templates-modal-title').text('Templates: ' + name + ' (' + phone + ')');
							$('#templates-modal-body').html(html);
							$('#templates-modal').fadeIn(200);
						} else {
							alert(response.data && response.data.message ? response.data.message : 'Erro ao carregar templates');
						}
					}).fail(function() {
						btn.prop('disabled', false).html('<span class="dashicons dashicons-media-text"></span> Templates');
						alert('Erro de conexão');
					});
				});

				// Fechar modal de templates
				$(document).on('click', '#templates-modal .pcw-modal-close, #templates-modal-close', function() {
					$('#templates-modal').fadeOut(200);
				});
				$('#templates-modal').on('click', function(e) {
					if ($(e.target).is('#templates-modal')) {
						$(this).fadeOut(200);
					}
				});
			});
		})(jQuery);
		</script>

		<!-- Modal de Templates -->
		<div id="templates-modal" class="pcw-modal" style="display: none;">
			<div class="pcw-modal-content" style="max-width: 800px;">
				<div class="pcw-modal-header">
					<h2 id="templates-modal-title"><?php esc_html_e( 'Templates Disponíveis', 'person-cash-wallet' ); ?></h2>
					<button type="button" class="pcw-modal-close">&times;</button>
				</div>
				<div class="pcw-modal-body" id="templates-modal-body">
					<p><?php esc_html_e( 'Carregando...', 'person-cash-wallet' ); ?></p>
				</div>
				<div class="pcw-modal-footer">
					<button type="button" class="button button-large" id="templates-modal-close"><?php esc_html_e( 'Fechar', 'person-cash-wallet' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderizar aba de monitor de filas
	 */
	private function render_queue_tab() {
		$queue_manager = PCW_Message_Queue_Manager::instance();
		$stats_by_type = $queue_manager->get_queue_stats_by_type();
		$stats = $stats_by_type['all'];
		$whatsapp_stats = $stats_by_type['whatsapp'];
		$email_stats = $stats_by_type['email'];
		$is_paused = get_option( 'pcw_queue_paused', false );
		$paused_at = get_option( 'pcw_queue_paused_at', '' );
		$paused_by = get_option( 'pcw_queue_paused_by', '' );
		?>
		<div class="pcw-queue-section">
			<?php if ( $is_paused ) : ?>
			<!-- Banner de Fila Pausada -->
			<div id="pcw-queue-paused-banner" style="background: linear-gradient(135deg, #fef3c7, #fde68a); border: 1px solid #f59e0b; border-radius: 10px; padding: 16px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
				<div style="display: flex; align-items: center; gap: 12px;">
					<span style="font-size: 28px;">⏸️</span>
					<div>
						<strong style="font-size: 16px; color: #92400e;"><?php esc_html_e( 'Fila de Disparos PAUSADA', 'person-cash-wallet' ); ?></strong>
						<p style="margin: 4px 0 0; color: #a16207; font-size: 13px;">
							<?php 
							if ( $paused_at && $paused_by ) {
								printf(
									/* translators: 1: user name 2: date */
									esc_html__( 'Pausada por %1$s em %2$s. Nenhuma mensagem está sendo enviada.', 'person-cash-wallet' ),
									esc_html( $paused_by ),
									esc_html( wp_date( 'd/m/Y H:i', strtotime( $paused_at ) ) )
								);
							} else {
								esc_html_e( 'Nenhuma mensagem está sendo enviada enquanto a fila estiver pausada.', 'person-cash-wallet' );
							}
							?>
						</p>
					</div>
				</div>
				<button type="button" id="pcw-toggle-queue-pause" class="button" style="background: #059669; border-color: #059669; color: #fff; display: flex; align-items: center; gap: 6px; font-weight: 600;">
					<span class="dashicons dashicons-controls-play" style="font-size: 16px; width: 16px; height: 16px;"></span>
					<?php esc_html_e( 'Retomar Disparos', 'person-cash-wallet' ); ?>
				</button>
			</div>
			<?php endif; ?>

			<?php 
			// Banner de fora do horário
			$schedule = get_option( 'pcw_queue_schedule', array() );
			$schedule_enabled = ! empty( $schedule['enabled'] );
			if ( $schedule_enabled ) :
				$is_within = $queue_manager->is_within_schedule();
				$sched_start = isset( $schedule['start_hour'] ) ? absint( $schedule['start_hour'] ) : 8;
				$sched_end = isset( $schedule['end_hour'] ) ? absint( $schedule['end_hour'] ) : 18;
				$sched_days = isset( $schedule['days'] ) ? (array) $schedule['days'] : array( 1, 2, 3, 4, 5 );
				$day_labels = array( 
					0 => __( 'Dom', 'person-cash-wallet' ), 
					1 => __( 'Seg', 'person-cash-wallet' ), 
					2 => __( 'Ter', 'person-cash-wallet' ), 
					3 => __( 'Qua', 'person-cash-wallet' ), 
					4 => __( 'Qui', 'person-cash-wallet' ), 
					5 => __( 'Sex', 'person-cash-wallet' ), 
					6 => __( 'Sáb', 'person-cash-wallet' ),
				);
				$active_day_labels = array();
				foreach ( $sched_days as $d ) {
					if ( isset( $day_labels[ $d ] ) ) {
						$active_day_labels[] = $day_labels[ $d ];
					}
				}
			?>
			<div id="pcw-schedule-banner" style="background: linear-gradient(135deg, <?php echo $is_within ? '#dcfce7, #bbf7d0' : '#ede9fe, #ddd6fe'; ?>); border: 1px solid <?php echo $is_within ? '#22c55e' : '#8b5cf6'; ?>; border-radius: 10px; padding: 14px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
				<span style="font-size: 24px;"><?php echo $is_within ? '🟢' : '🟣'; ?></span>
				<div>
					<?php if ( $is_within ) : ?>
						<strong style="color: #166534; font-size: 14px;">
							<?php esc_html_e( 'Dentro do horário de disparo', 'person-cash-wallet' ); ?>
						</strong>
						<p style="margin: 2px 0 0; color: #15803d; font-size: 12px;">
							<?php
							printf(
								/* translators: 1: start hour 2: end hour 3: days */
								esc_html__( 'Horário ativo: %1$s:00 às %2$s:00 | Dias: %3$s', 'person-cash-wallet' ),
								str_pad( $sched_start, 2, '0', STR_PAD_LEFT ),
								str_pad( $sched_end, 2, '0', STR_PAD_LEFT ),
								implode( ', ', $active_day_labels )
							);
							?>
						</p>
					<?php else : ?>
						<strong style="color: #5b21b6; font-size: 14px;">
							<?php esc_html_e( 'Fora do horário de disparo', 'person-cash-wallet' ); ?>
						</strong>
						<p style="margin: 2px 0 0; color: #6d28d9; font-size: 12px;">
							<?php
							printf(
								/* translators: 1: start hour 2: end hour 3: days */
								esc_html__( 'Mensagens aguardam na fila. Próximo período: %1$s:00 às %2$s:00 | Dias: %3$s', 'person-cash-wallet' ),
								str_pad( $sched_start, 2, '0', STR_PAD_LEFT ),
								str_pad( $sched_end, 2, '0', STR_PAD_LEFT ),
								implode( ', ', $active_day_labels )
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Estatísticas Gerais -->
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
				<h3 style="margin: 0; color: #1e293b;">
					<span class="dashicons dashicons-chart-bar" style="margin-right: 6px;"></span>
					<?php esc_html_e( 'Visão Geral', 'person-cash-wallet' ); ?>
				</h3>
				<div style="display: flex; gap: 8px;">
					<?php if ( ! $is_paused ) : ?>
					<button type="button" id="pcw-toggle-queue-pause" class="button" style="background: #dc2626; border-color: #dc2626; color: #fff; display: flex; align-items: center; gap: 6px;">
						<span class="dashicons dashicons-controls-pause" style="font-size: 16px; width: 16px; height: 16px;"></span>
						<?php esc_html_e( 'Pausar Disparos', 'person-cash-wallet' ); ?>
					</button>
					<?php endif; ?>
					<button type="button" id="pcw-process-queue-now" class="button button-primary" style="display: flex; align-items: center; gap: 6px;" <?php echo $is_paused ? 'disabled' : ''; ?>>
						<span class="dashicons dashicons-controls-play" style="font-size: 16px; width: 16px; height: 16px;"></span>
						<?php esc_html_e( 'Processar Fila Agora', 'person-cash-wallet' ); ?>
					</button>
				</div>
			</div>
			<div class="pcw-stats-grid">
				<div class="pcw-stat-card">
					<div class="pcw-stat-icon pcw-stat-pending">
						<span class="dashicons dashicons-clock"></span>
					</div>
					<div class="pcw-stat-content">
						<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['pending'] ) ); ?></div>
						<div class="pcw-stat-label"><?php esc_html_e( 'Pendentes', 'person-cash-wallet' ); ?></div>
					</div>
				</div>

				<div class="pcw-stat-card">
					<div class="pcw-stat-icon pcw-stat-sent">
						<span class="dashicons dashicons-yes-alt"></span>
					</div>
					<div class="pcw-stat-content">
						<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['sent'] ) ); ?></div>
						<div class="pcw-stat-label"><?php esc_html_e( 'Enviadas', 'person-cash-wallet' ); ?></div>
					</div>
				</div>

				<div class="pcw-stat-card">
					<div class="pcw-stat-icon pcw-stat-failed">
						<span class="dashicons dashicons-dismiss"></span>
					</div>
					<div class="pcw-stat-content">
						<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['failed'] ) ); ?></div>
						<div class="pcw-stat-label"><?php esc_html_e( 'Falhadas', 'person-cash-wallet' ); ?></div>
					</div>
				</div>

				<div class="pcw-stat-card">
					<div class="pcw-stat-icon pcw-stat-total">
						<span class="dashicons dashicons-list-view"></span>
					</div>
					<div class="pcw-stat-content">
						<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></div>
						<div class="pcw-stat-label"><?php esc_html_e( 'Total', 'person-cash-wallet' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Estatísticas por Número WhatsApp -->
			<?php
			$numbers_stats = $this->get_numbers_hourly_stats();
			if ( ! empty( $numbers_stats ) ) :
			?>
			<div style="margin-top: 30px;">
				<h3 style="margin: 0 0 15px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
					<span class="dashicons dashicons-chart-line"></span>
					<?php esc_html_e( 'Desempenho por Número WhatsApp', 'person-cash-wallet' ); ?>
				</h3>
				<div class="pcw-numbers-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 15px;">
					<?php foreach ( $numbers_stats as $num ) : ?>
						<div class="pcw-number-stats-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; border-left: 4px solid #25d366;">
							<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
								<div>
									<h4 style="margin: 0 0 4px; font-size: 15px; color: #1e293b;">
										📱 <?php echo esc_html( $num['name'] ); ?>
									</h4>
									<p style="margin: 0; font-size: 13px; color: #64748b;">
										<?php echo esc_html( $num['phone'] ); ?>
									</p>
								</div>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-queue&action=report&number_id=' . $num['id'] ) ); ?>" 
									class="button button-small">
									<span class="dashicons dashicons-chart-bar" style="margin-top: 3px;"></span>
									<?php esc_html_e( 'Report', 'person-cash-wallet' ); ?>
								</a>
							</div>
							
							<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; text-align: center;">
								<div style="background: #f0fdf4; padding: 10px 8px; border-radius: 6px;">
									<div style="font-size: 18px; font-weight: bold; color: #16a34a;">
										<?php echo esc_html( number_format( $num['avg_per_hour'], 1 ) ); ?>
									</div>
									<div style="font-size: 11px; color: #64748b;"><?php esc_html_e( 'Média/Hora', 'person-cash-wallet' ); ?></div>
								</div>
								<div style="background: #f0f9ff; padding: 10px 8px; border-radius: 6px;">
									<div style="font-size: 18px; font-weight: bold; color: #0284c7;">
										<?php echo esc_html( number_format_i18n( $num['sent_today'] ) ); ?>
									</div>
									<div style="font-size: 11px; color: #64748b;"><?php esc_html_e( 'Hoje', 'person-cash-wallet' ); ?></div>
								</div>
								<div style="background: #fef3c7; padding: 10px 8px; border-radius: 6px;">
									<div style="font-size: 18px; font-weight: bold; color: #d97706;">
										<?php echo esc_html( number_format_i18n( $num['sent_7d'] ) ); ?>
									</div>
									<div style="font-size: 11px; color: #64748b;"><?php esc_html_e( '7 dias', 'person-cash-wallet' ); ?></div>
								</div>
								<div style="background: #fef2f2; padding: 10px 8px; border-radius: 6px;">
									<div style="font-size: 18px; font-weight: bold; color: #dc2626;">
										<?php echo esc_html( number_format_i18n( $num['failed'] ) ); ?>
									</div>
									<div style="font-size: 11px; color: #64748b;"><?php esc_html_e( 'Falhas', 'person-cash-wallet' ); ?></div>
								</div>
							</div>
							
							<?php 
							$usage_pct = $num['rate_limit'] > 0 ? min( 100, ( $num['sent_last_hour'] / $num['rate_limit'] ) * 100 ) : 0;
							$bar_color = $usage_pct >= 90 ? '#dc2626' : ( $usage_pct >= 70 ? '#f59e0b' : '#22c55e' );
							?>
							<div style="margin-top: 12px;">
								<div style="display: flex; justify-content: space-between; font-size: 11px; color: #64748b; margin-bottom: 4px;">
									<span><?php esc_html_e( 'Uso da última hora', 'person-cash-wallet' ); ?></span>
									<span><?php echo esc_html( $num['sent_last_hour'] ); ?> / <?php echo esc_html( $num['rate_limit'] ); ?></span>
								</div>
								<div style="height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
									<div style="height: 100%; width: <?php echo esc_attr( $usage_pct ); ?>%; background: <?php echo esc_attr( $bar_color ); ?>; transition: width 0.3s;"></div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Estatísticas por Tipo -->
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
				<!-- WhatsApp -->
				<div class="pcw-card" style="border-left: 4px solid #25d366;">
					<div class="pcw-card-header" style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0;">
						<h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
							<span style="font-size: 20px;">📱</span>
							<?php esc_html_e( 'WhatsApp', 'person-cash-wallet' ); ?>
						</h3>
					</div>
					<div class="pcw-card-body" style="padding: 20px;">
						<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
							<div style="text-align: center; padding: 10px; background: #f8fafc; border-radius: 8px;">
								<div style="font-size: 24px; font-weight: bold; color: #f59e0b;"><?php echo esc_html( number_format_i18n( $whatsapp_stats['pending'] ) ); ?></div>
								<div style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Pendentes', 'person-cash-wallet' ); ?></div>
							</div>
							<div style="text-align: center; padding: 10px; background: #f8fafc; border-radius: 8px;">
								<div style="font-size: 24px; font-weight: bold; color: #10b981;"><?php echo esc_html( number_format_i18n( $whatsapp_stats['sent'] ) ); ?></div>
								<div style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Enviadas', 'person-cash-wallet' ); ?></div>
							</div>
							<div style="text-align: center; padding: 10px; background: #f8fafc; border-radius: 8px;">
								<div style="font-size: 24px; font-weight: bold; color: #ef4444;"><?php echo esc_html( number_format_i18n( $whatsapp_stats['failed'] ) ); ?></div>
								<div style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Falhadas', 'person-cash-wallet' ); ?></div>
							</div>
							<div style="text-align: center; padding: 10px; background: #f8fafc; border-radius: 8px;">
								<div style="font-size: 24px; font-weight: bold; color: #6366f1;"><?php echo esc_html( number_format_i18n( $whatsapp_stats['total'] ) ); ?></div>
								<div style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Total', 'person-cash-wallet' ); ?></div>
							</div>
						</div>
					</div>
				</div>

				<!-- Email -->
				<div class="pcw-card" style="border-left: 4px solid #3b82f6;">
					<div class="pcw-card-header" style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0;">
						<h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
							<span style="font-size: 20px;">📧</span>
							<?php esc_html_e( 'Email (SMTP)', 'person-cash-wallet' ); ?>
						</h3>
					</div>
					<div class="pcw-card-body" style="padding: 20px;">
						<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
							<div style="text-align: center; padding: 10px; background: #f8fafc; border-radius: 8px;">
								<div style="font-size: 24px; font-weight: bold; color: #f59e0b;"><?php echo esc_html( number_format_i18n( $email_stats['pending'] ) ); ?></div>
								<div style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Pendentes', 'person-cash-wallet' ); ?></div>
							</div>
							<div style="text-align: center; padding: 10px; background: #f8fafc; border-radius: 8px;">
								<div style="font-size: 24px; font-weight: bold; color: #10b981;"><?php echo esc_html( number_format_i18n( $email_stats['sent'] ) ); ?></div>
								<div style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Enviadas', 'person-cash-wallet' ); ?></div>
							</div>
							<div style="text-align: center; padding: 10px; background: #f8fafc; border-radius: 8px;">
								<div style="font-size: 24px; font-weight: bold; color: #ef4444;"><?php echo esc_html( number_format_i18n( $email_stats['failed'] ) ); ?></div>
								<div style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Falhadas', 'person-cash-wallet' ); ?></div>
							</div>
							<div style="text-align: center; padding: 10px; background: #f8fafc; border-radius: 8px;">
								<div style="font-size: 24px; font-weight: bold; color: #6366f1;"><?php echo esc_html( number_format_i18n( $email_stats['total'] ) ); ?></div>
								<div style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Total', 'person-cash-wallet' ); ?></div>
							</div>
						</div>
						<?php if ( $email_stats['total'] === 0 ) : ?>
							<p style="margin: 15px 0 0; padding: 12px; background: #fef3c7; border-radius: 6px; color: #92400e; font-size: 13px;">
								<span class="dashicons dashicons-info" style="margin-right: 4px;"></span>
								<?php esc_html_e( 'Para emails aparecerem aqui, habilite a distribuição automática em Contas SMTP e use ações de email em workflows/automações.', 'person-cash-wallet' ); ?>
							</p>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="pcw-queue-actions" style="margin-top: 20px;">
				<button type="button" class="button" id="refresh-stats">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Atualizar Estatísticas', 'person-cash-wallet' ); ?>
				</button>
				<?php if ( $stats['failed'] > 0 ) : ?>
					<button type="button" class="button" id="retry-failed">
						<span class="dashicons dashicons-controls-repeat"></span>
						<?php esc_html_e( 'Reprocessar Falhadas', 'person-cash-wallet' ); ?>
					</button>
					<button type="button" class="button button-link-delete" id="clear-failed">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Limpar Falhadas', 'person-cash-wallet' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<!-- Listagem de Mensagens -->
			<div style="margin-top: 40px;">
				<h3 style="margin: 0 0 15px; color: #1e293b;">
					<span class="dashicons dashicons-email" style="margin-right: 6px;"></span>
					<?php esc_html_e( 'Mensagens na Fila', 'person-cash-wallet' ); ?>
				</h3>

				<!-- Filtros -->
				<div style="display: flex; gap: 10px; margin-bottom: 15px; align-items: center;">
					<select id="queue-filter-type" style="max-width: 150px;">
						<option value=""><?php esc_html_e( 'Todos os tipos', 'person-cash-wallet' ); ?></option>
						<option value="email"><?php esc_html_e( 'Email', 'person-cash-wallet' ); ?></option>
						<option value="whatsapp"><?php esc_html_e( 'WhatsApp', 'person-cash-wallet' ); ?></option>
					</select>

					<select id="queue-filter-status" style="max-width: 150px;">
						<option value=""><?php esc_html_e( 'Todos os status', 'person-cash-wallet' ); ?></option>
						<option value="pending"><?php esc_html_e( 'Pendentes', 'person-cash-wallet' ); ?></option>
						<option value="sent"><?php esc_html_e( 'Enviadas', 'person-cash-wallet' ); ?></option>
						<option value="failed"><?php esc_html_e( 'Falhadas', 'person-cash-wallet' ); ?></option>
					</select>

					<button type="button" class="button" id="queue-filter-apply">
						<span class="dashicons dashicons-filter"></span>
						<?php esc_html_e( 'Filtrar', 'person-cash-wallet' ); ?>
					</button>

					<button type="button" class="button" id="queue-filter-reset">
						<?php esc_html_e( 'Limpar', 'person-cash-wallet' ); ?>
					</button>
				</div>

				<!-- Tabela -->
				<div class="pcw-queue-table-wrapper" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow-x: auto; width: 100%;">
					<table class="wp-list-table widefat striped" id="queue-messages-table" style="width: 100%; table-layout: auto;">
						<thead>
							<tr>
								<th style="width: 50px; text-align: center;"><?php esc_html_e( 'Tipo', 'person-cash-wallet' ); ?></th>
								<th style="white-space: nowrap;"><?php esc_html_e( 'Para', 'person-cash-wallet' ); ?></th>
								<th style="white-space: nowrap;"><?php esc_html_e( 'Enviado Por', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Assunto/Mensagem', 'person-cash-wallet' ); ?></th>
								<th style="width: 90px; text-align: center;"><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></th>
								<th style="white-space: nowrap;"><?php esc_html_e( 'Agendado', 'person-cash-wallet' ); ?></th>
								<th style="white-space: nowrap;"><?php esc_html_e( 'Ações', 'person-cash-wallet' ); ?></th>
							</tr>
						</thead>
						<tbody id="queue-messages-tbody">
							<tr>
								<td colspan="7" style="text-align: center; padding: 40px;">
									<span class="dashicons dashicons-update spin" style="font-size: 32px; opacity: 0.5;"></span>
									<p><?php esc_html_e( 'Carregando mensagens...', 'person-cash-wallet' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Paginação -->
				<div id="queue-pagination" style="margin-top: 15px; display: none;"></div>
			</div>

			<div class="pcw-info-box" style="margin-top: 20px;">
				<h3>
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'Como funciona o sistema de filas?', 'person-cash-wallet' ); ?>
				</h3>
				<ul>
					<li><?php esc_html_e( 'Mensagens são adicionadas à fila quando disparadas por webhooks, automações ou workflows', 'person-cash-wallet' ); ?></li>
					<li><?php esc_html_e( 'O sistema processa a fila a cada minuto, respeitando o rate limit de cada conta/número', 'person-cash-wallet' ); ?></li>
					<li><?php esc_html_e( 'Para WhatsApp: distribui entre os números configurados na aba "Números WhatsApp"', 'person-cash-wallet' ); ?></li>
					<li><?php esc_html_e( 'Para Email: distribui entre as contas SMTP com "Distribuição Automática" habilitada', 'person-cash-wallet' ); ?></li>
					<li><?php esc_html_e( 'Mensagens falhadas são reprocessadas automaticamente até 3 tentativas', 'person-cash-wallet' ); ?></li>
				</ul>
			</div>
		</div>

		<!-- Modal de Visualização de Mensagem -->
		<div id="message-preview-modal" class="pcw-modal" style="display: none;">
			<div class="pcw-modal-content" style="max-width: 800px;">
				<div class="pcw-modal-header">
					<h2><?php esc_html_e( 'Visualizar Mensagem', 'person-cash-wallet' ); ?></h2>
					<button type="button" class="pcw-modal-close">&times;</button>
				</div>
				<div class="pcw-modal-body" id="message-preview-content" style="max-height: 70vh; overflow-y: auto;">
					<div style="text-align: center; padding: 40px;">
						<span class="dashicons dashicons-update spin" style="font-size: 32px;"></span>
						<p><?php esc_html_e( 'Carregando...', 'person-cash-wallet' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<style>
		.pcw-modal {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.7);
			z-index: 999999;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 20px;
		}
		.pcw-modal-content {
			background: #fff;
			border-radius: 8px;
			width: 100%;
			max-width: 600px;
			box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
		}
		.pcw-modal-header {
			padding: 20px;
			border-bottom: 1px solid #e2e8f0;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.pcw-modal-header h2 {
			margin: 0;
			font-size: 20px;
		}
		.pcw-modal-close {
			background: none;
			border: none;
			font-size: 28px;
			cursor: pointer;
			color: #64748b;
			line-height: 1;
			padding: 0;
			width: 32px;
			height: 32px;
		}
		.pcw-modal-close:hover {
			color: #1e293b;
		}
		.pcw-modal-body {
			padding: 20px;
		}
		.message-detail-row {
			margin-bottom: 15px;
			padding-bottom: 15px;
			border-bottom: 1px solid #f1f5f9;
		}
		.message-detail-row:last-child {
			border-bottom: none;
		}
		.message-detail-label {
			font-weight: 600;
			color: #475569;
			font-size: 12px;
			text-transform: uppercase;
			margin-bottom: 4px;
		}
		.message-detail-value {
			color: #1e293b;
			font-size: 14px;
		}
		.message-preview-iframe {
			width: 100%;
			min-height: 400px;
			border: 1px solid #e2e8f0;
			border-radius: 4px;
		}
		</style>

		<script type="text/javascript">
		(function($) {
			var currentPage = 1;
			var perPage = 20;

			function loadMessages() {
				var type = $('#queue-filter-type').val();
				var status = $('#queue-filter-status').val();

				$('#queue-messages-tbody').html('<tr><td colspan="7" style="text-align: center; padding: 40px;"><span class="dashicons dashicons-update spin" style="font-size: 32px; opacity: 0.5;"></span></td></tr>');

				$.post(ajaxurl, {
					action: 'pcw_get_queue_messages',
					nonce: pcwQueue.nonce,
					type: type,
					status: status,
					page: currentPage,
					per_page: perPage
				}, function(response) {
					if (response.success) {
						renderMessages(response.data.messages);
						renderPagination(response.data.total, response.data.page, response.data.per_page);
					} else {
						$('#queue-messages-tbody').html('<tr><td colspan="7" style="text-align: center; padding: 20px; color: #dc2626;">Erro ao carregar mensagens</td></tr>');
					}
				});
			}

			function renderMessages(messages) {
				if (!messages || messages.length === 0) {
					$('#queue-messages-tbody').html('<tr><td colspan="7" style="text-align: center; padding: 40px; color: #64748b;">Nenhuma mensagem encontrada</td></tr>');
					return;
				}

				var html = '';
				messages.forEach(function(msg) {
					var typeIcon = msg.type === 'email' ? '📧' : '📱';
					var statusClass = msg.status === 'sent' ? 'success' : (msg.status === 'failed' ? 'error' : 'warning');
					var statusText = msg.status === 'sent' ? 'Enviada' : (msg.status === 'failed' ? 'Falhada' : 'Pendente');
					var preview = msg.preview.substring(0, 60) + (msg.preview.length > 60 ? '...' : '');
					var fromNumber = msg.from_number ? escapeHtml(msg.from_number) : '<span style="color: #94a3b8;">-</span>';

					html += '<tr>';
					html += '<td style="text-align: center; font-size: 20px;">' + typeIcon + '</td>';
					html += '<td>' + escapeHtml(msg.to_number) + '</td>';
					html += '<td style="font-family: monospace; font-size: 12px;">' + fromNumber + '</td>';
					html += '<td>' + escapeHtml(preview) + '</td>';
					html += '<td><span class="pcw-badge pcw-badge-' + statusClass + '">' + statusText + '</span></td>';
					html += '<td>' + msg.scheduled_at + '</td>';
					html += '<td style="white-space: nowrap;">';
					html += '<button class="button button-small view-message-btn" data-id="' + msg.id + '" style="margin-right: 4px;"><span class="dashicons dashicons-visibility"></span> Ver</button>';
					if (msg.status === 'failed') {
						html += '<button class="button button-small retry-single-btn" data-id="' + msg.id + '" style="color: #d97706; border-color: #d97706;"><span class="dashicons dashicons-controls-repeat"></span> Reenviar</button>';
					}
					html += '</td>';
					html += '</tr>';
				});

				$('#queue-messages-tbody').html(html);
			}

			function renderPagination(total, page, per_page) {
				var totalPages = Math.ceil(total / per_page);
				if (totalPages <= 1) {
					$('#queue-pagination').hide();
					return;
				}

				var html = '<div style="display: flex; gap: 5px; align-items: center; justify-content: center;">';
				html += '<span style="margin-right: 10px;">Página ' + page + ' de ' + totalPages + ' (' + total + ' mensagens)</span>';
				
				if (page > 1) {
					html += '<button class="button page-btn" data-page="1">«</button>';
					html += '<button class="button page-btn" data-page="' + (page - 1) + '">‹</button>';
				}

				for (var i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) {
					html += '<button class="button ' + (i === page ? 'button-primary' : '') + ' page-btn" data-page="' + i + '">' + i + '</button>';
				}

				if (page < totalPages) {
					html += '<button class="button page-btn" data-page="' + (page + 1) + '">›</button>';
					html += '<button class="button page-btn" data-page="' + totalPages + '">»</button>';
				}

				html += '</div>';
				$('#queue-pagination').html(html).show();
			}

			function escapeHtml(text) {
				var map = {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#039;'
				};
				return text.replace(/[&<>"']/g, function(m) { return map[m]; });
			}

			// Event handlers
			$(document).on('click', '#queue-filter-apply', function() {
				currentPage = 1;
				loadMessages();
			});

			$(document).on('click', '#queue-filter-reset', function() {
				$('#queue-filter-type').val('');
				$('#queue-filter-status').val('');
				currentPage = 1;
				loadMessages();
			});

			$(document).on('click', '.page-btn', function() {
				currentPage = parseInt($(this).data('page'));
				loadMessages();
			});

			$(document).on('click', '.view-message-btn', function() {
				var messageId = $(this).data('id');
				viewMessage(messageId);
			});

			function viewMessage(messageId) {
				$('#message-preview-modal').fadeIn(200);
				$('#message-preview-content').html('<div style="text-align: center; padding: 40px;"><span class="dashicons dashicons-update spin" style="font-size: 32px;"></span></div>');

				$.post(ajaxurl, {
					action: 'pcw_get_message_details',
					nonce: pcwQueue.nonce,
					message_id: messageId
				}, function(response) {
					if (response.success) {
						renderMessageDetails(response.data, messageId);
					} else {
						$('#message-preview-content').html('<p style="color: #dc2626; text-align: center;">Erro ao carregar mensagem</p>');
					}
				});
			}

			function renderMessageDetails(data, messageId) {
				var html = '';
				
				html += '<div class="message-detail-row">';
				html += '<div class="message-detail-label">Tipo</div>';
				html += '<div class="message-detail-value">' + (data.type === 'email' ? '📧 Email' : '📱 WhatsApp') + '</div>';
				html += '</div>';

				html += '<div class="message-detail-row">';
				html += '<div class="message-detail-label">Para</div>';
				html += '<div class="message-detail-value">' + escapeHtml(data.to_number) + '</div>';
				html += '</div>';

				if (data.type === 'email' && data.subject) {
					html += '<div class="message-detail-row">';
					html += '<div class="message-detail-label">Assunto</div>';
					html += '<div class="message-detail-value">' + escapeHtml(data.subject) + '</div>';
					html += '</div>';
				}

				html += '<div class="message-detail-row">';
				html += '<div class="message-detail-label">Status</div>';
				html += '<div class="message-detail-value">';
				var statusText = data.status === 'sent' ? '✅ Enviada' : (data.status === 'failed' ? '❌ Falhada' : '⏳ Pendente');
				html += statusText;
				if (data.status === 'sent' && data.processed_at) {
					html += ' em ' + data.processed_at;
				}
				html += '</div>';
				html += '</div>';

				if (data.error_message) {
					html += '<div class="message-detail-row">';
					html += '<div class="message-detail-label"><?php echo esc_js( __( 'Erro', 'person-cash-wallet' ) ); ?></div>';
					html += '<div class="message-detail-value" style="color: #dc2626;">' + escapeHtml(data.error_message) + '</div>';
					html += '</div>';
				}

				if (data.response_data) {
					html += '<div class="message-detail-row">';
					html += '<div class="message-detail-label"><?php echo esc_js( __( 'Detalhes do Erro', 'person-cash-wallet' ) ); ?></div>';
					html += '<div class="message-detail-value">';
					html += '<div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 12px; font-size: 12px;">';
					
					if (data.response_data.error_code) {
						html += '<div style="margin-bottom: 4px;"><strong><?php echo esc_js( __( 'Código:', 'person-cash-wallet' ) ); ?></strong> ' + escapeHtml(data.response_data.error_code) + '</div>';
					}
					if (data.response_data.error_data && data.response_data.error_data.status) {
						html += '<div style="margin-bottom: 4px;"><strong>HTTP Status:</strong> ' + escapeHtml(String(data.response_data.error_data.status)) + '</div>';
					}
					if (data.response_data.error_data && data.response_data.error_data.response) {
						html += '<div style="margin-bottom: 4px;"><strong><?php echo esc_js( __( 'Resposta API:', 'person-cash-wallet' ) ); ?></strong></div>';
						html += '<pre style="background: #fff; padding: 8px; border-radius: 4px; overflow-x: auto; font-size: 11px; max-height: 200px; overflow-y: auto;">' + escapeHtml(JSON.stringify(data.response_data.error_data.response, null, 2)) + '</pre>';
					}
					if (data.response_data.from_number) {
						html += '<div style="margin-bottom: 4px;"><strong><?php echo esc_js( __( 'Número remetente:', 'person-cash-wallet' ) ); ?></strong> ' + escapeHtml(data.response_data.from_number) + '</div>';
					}
					if (data.response_data.timestamp) {
						html += '<div><strong><?php echo esc_js( __( 'Data/Hora:', 'person-cash-wallet' ) ); ?></strong> ' + escapeHtml(data.response_data.timestamp) + '</div>';
					}
					
					html += '</div>';
					html += '</div>';
					html += '</div>';
				}

				if (data.created_at) {
					html += '<div class="message-detail-row">';
					html += '<div class="message-detail-label"><?php echo esc_js( __( 'Criada em', 'person-cash-wallet' ) ); ?></div>';
					html += '<div class="message-detail-value">' + escapeHtml(data.created_at) + '</div>';
					html += '</div>';
				}

				html += '<div class="message-detail-row">';
				html += '<div class="message-detail-label">Agendado para</div>';
				html += '<div class="message-detail-value">' + data.scheduled_at + '</div>';
				html += '</div>';

				html += '<div class="message-detail-row">';
				html += '<div class="message-detail-label">Tentativas</div>';
				html += '<div class="message-detail-value">' + data.attempts + ' / ' + data.max_attempts + '</div>';
				html += '</div>';

				html += '<div class="message-detail-row">';
				html += '<div class="message-detail-label">' + (data.type === 'email' ? 'Corpo do Email' : 'Mensagem') + '</div>';
				html += '<div class="message-detail-value">';
				if (data.type === 'email' && data.body_html) {
					html += '<iframe class="message-preview-iframe" srcdoc="' + escapeHtml(data.body_html).replace(/"/g, '&quot;') + '"></iframe>';
				} else {
					html += '<div style="white-space: pre-wrap; padding: 15px; background: #f8fafc; border-radius: 4px; border: 1px solid #e2e8f0;">' + escapeHtml(data.message) + '</div>';
				}
				html += '</div>';
				html += '</div>';

				// Botão de reprocessar se falhou
				if (data.status === 'failed') {
					html += '<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center;">';
					html += '<button type="button" class="button button-primary retry-single-btn" data-id="' + messageId + '" style="background: #d97706; border-color: #d97706; display: inline-flex; align-items: center; gap: 6px; font-size: 14px; padding: 6px 20px;">';
					html += '<span class="dashicons dashicons-controls-repeat" style="font-size: 18px; width: 18px; height: 18px;"></span>';
					html += '<?php echo esc_js( __( 'Reenviar para Fila', 'person-cash-wallet' ) ); ?>';
					html += '</button>';
					html += '</div>';
				}

				$('#message-preview-content').html(html);
			}

			// Close modal
			$(document).on('click', '.pcw-modal-close', function() {
				$('#message-preview-modal').fadeOut(200);
			});

			$(document).on('click', '#message-preview-modal', function(e) {
				if ($(e.target).is('#message-preview-modal')) {
					$(this).fadeOut(200);
				}
			});

			// Reprocessar mensagem individual
			$(document).on('click', '.retry-single-btn', function(e) {
				e.preventDefault();
				e.stopPropagation();
				var $btn = $(this);
				var msgId = $btn.data('id');

				if (!confirm('<?php echo esc_js( __( 'Reenviar esta mensagem para a fila?', 'person-cash-wallet' ) ); ?>')) {
					return;
				}

				var originalHtml = $btn.html();
				$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>');

				$.post(pcwQueue.ajaxUrl, {
					action: 'pcw_retry_single_message',
					nonce: pcwQueue.nonce,
					message_id: msgId
				}, function(response) {
					if (response.success) {
						// Fechar modal se estiver aberto
						$('#message-preview-modal').fadeOut(200);
						// Recarregar listagem
						loadMessages();
						// Notificação
						var notice = $('<div class="notice notice-success is-dismissible" style="position: fixed; top: 40px; right: 20px; z-index: 100001; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"><p>' + response.data.message + '</p></div>');
						$('body').append(notice);
						setTimeout(function() { notice.fadeOut(function() { notice.remove(); }); }, 3000);
					} else {
						alert(response.data.message || '<?php echo esc_js( __( 'Erro ao reprocessar.', 'person-cash-wallet' ) ); ?>');
						$btn.prop('disabled', false).html(originalHtml);
					}
				}).fail(function() {
					alert('<?php echo esc_js( __( 'Erro de conexão.', 'person-cash-wallet' ) ); ?>');
					$btn.prop('disabled', false).html(originalHtml);
				});
			});

			// Load messages on page load
			$(document).ready(function() {
				loadMessages();
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Renderizar aba de estratégia de distribuição
	 */
	private function render_distribution_tab() {
		$whatsapp_strategy = get_option( 'pcw_whatsapp_distribution_strategy', 'round_robin' );
		$smtp_strategy = get_option( 'pcw_smtp_distribution_strategy', 'round_robin' );
		?>
		<div class="pcw-distribution-section">
			<!-- WhatsApp Strategy -->
			<form id="whatsapp-distribution-form">
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-phone" style="color: #10b981;"></span>
							<?php esc_html_e( 'Distribuição WhatsApp', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<p class="description">
							<?php esc_html_e( 'Estratégia de distribuição entre números WhatsApp:', 'person-cash-wallet' ); ?>
						</p>

						<div class="pcw-strategy-options">
							<label class="pcw-strategy-option <?php echo $whatsapp_strategy === 'round_robin' ? 'active' : ''; ?>">
								<input type="radio" name="whatsapp_strategy" value="round_robin" 
									<?php checked( $whatsapp_strategy, 'round_robin' ); ?>>
								<div class="pcw-strategy-card">
									<div class="pcw-strategy-icon">🔄</div>
									<div class="pcw-strategy-info">
										<h3><?php esc_html_e( 'Round Robin (Rotação)', 'person-cash-wallet' ); ?></h3>
										<p><?php esc_html_e( 'Alterna entre os números em ordem sequencial. Cada número recebe uma mensagem por vez.', 'person-cash-wallet' ); ?></p>
										<div class="pcw-strategy-example">
											<strong><?php esc_html_e( 'Exemplo:', 'person-cash-wallet' ); ?></strong>
											<code>Nº1 → Nº2 → Nº3 → Nº1 → Nº2...</code>
										</div>
									</div>
								</div>
							</label>

							<label class="pcw-strategy-option <?php echo $current_strategy === 'random' ? 'active' : ''; ?>">
								<input type="radio" name="distribution_strategy" value="random" 
									<?php checked( $current_strategy, 'random' ); ?>>
								<div class="pcw-strategy-card">
									<div class="pcw-strategy-icon">🎲</div>
									<div class="pcw-strategy-info">
										<h3><?php esc_html_e( 'Aleatório', 'person-cash-wallet' ); ?></h3>
										<p><?php esc_html_e( 'Seleciona um número aleatório a cada envio. Distribuição imprevisível e natural.', 'person-cash-wallet' ); ?></p>
										<div class="pcw-strategy-example">
											<strong><?php esc_html_e( 'Exemplo:', 'person-cash-wallet' ); ?></strong>
											<code>Nº3 → Nº1 → Nº3 → Nº2 → Nº1...</code>
										</div>
									</div>
								</div>
							</label>

							<label class="pcw-strategy-option <?php echo $whatsapp_strategy === 'weighted' ? 'active' : ''; ?>">
								<input type="radio" name="whatsapp_strategy" value="weighted" 
									<?php checked( $whatsapp_strategy, 'weighted' ); ?>>
								<div class="pcw-strategy-card">
									<div class="pcw-strategy-icon">⚖️</div>
									<div class="pcw-strategy-info">
										<h3><?php esc_html_e( 'Por Peso (%)', 'person-cash-wallet' ); ?></h3>
										<p><?php esc_html_e( 'Distribui baseado no peso configurado em cada número. Números com maior peso recebem mais mensagens.', 'person-cash-wallet' ); ?></p>
										<div class="pcw-strategy-example">
											<strong><?php esc_html_e( 'Exemplo:', 'person-cash-wallet' ); ?></strong>
											<code>Nº1(50%) → Nº2(30%) → Nº3(20%)</code>
										</div>
									</div>
								</div>
							</label>
						</div>

						<div class="pcw-form-actions">
							<button type="submit" class="button button-primary button-large">
								<span class="dashicons dashicons-saved"></span>
								<?php esc_html_e( 'Salvar Estratégia WhatsApp', 'person-cash-wallet' ); ?>
							</button>
						</div>
					</div>
				</div>
			</form>

			<!-- SMTP Strategy -->
			<form id="smtp-distribution-form" style="margin-top: 30px;">
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-email" style="color: #3b82f6;"></span>
							<?php esc_html_e( 'Distribuição SMTP (Email)', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<p class="description">
							<?php esc_html_e( 'Estratégia de distribuição entre contas SMTP:', 'person-cash-wallet' ); ?>
						</p>

						<div class="pcw-strategy-options">
							<label class="pcw-strategy-option <?php echo $smtp_strategy === 'round_robin' ? 'active' : ''; ?>">
								<input type="radio" name="smtp_strategy" value="round_robin" 
									<?php checked( $smtp_strategy, 'round_robin' ); ?>>
								<div class="pcw-strategy-card">
									<div class="pcw-strategy-icon">🔄</div>
									<div class="pcw-strategy-info">
										<h3><?php esc_html_e( 'Round Robin (Rotação)', 'person-cash-wallet' ); ?></h3>
										<p><?php esc_html_e( 'Alterna entre as contas em ordem sequencial.', 'person-cash-wallet' ); ?></p>
									</div>
								</div>
							</label>

							<label class="pcw-strategy-option <?php echo $smtp_strategy === 'random' ? 'active' : ''; ?>">
								<input type="radio" name="smtp_strategy" value="random" 
									<?php checked( $smtp_strategy, 'random' ); ?>>
								<div class="pcw-strategy-card">
									<div class="pcw-strategy-icon">🎲</div>
									<div class="pcw-strategy-info">
										<h3><?php esc_html_e( 'Aleatório', 'person-cash-wallet' ); ?></h3>
										<p><?php esc_html_e( 'Seleciona uma conta aleatória a cada envio.', 'person-cash-wallet' ); ?></p>
									</div>
								</div>
							</label>

							<label class="pcw-strategy-option <?php echo $smtp_strategy === 'weighted' ? 'active' : ''; ?>">
								<input type="radio" name="smtp_strategy" value="weighted" 
									<?php checked( $smtp_strategy, 'weighted' ); ?>>
								<div class="pcw-strategy-card">
									<div class="pcw-strategy-icon">⚖️</div>
									<div class="pcw-strategy-info">
										<h3><?php esc_html_e( 'Por Peso (%)', 'person-cash-wallet' ); ?></h3>
										<p><?php esc_html_e( 'Distribui baseado no peso configurado em cada conta.', 'person-cash-wallet' ); ?></p>
									</div>
								</div>
							</label>
						</div>

						<div class="pcw-form-actions">
							<button type="submit" class="button button-primary button-large">
								<span class="dashicons dashicons-saved"></span>
								<?php esc_html_e( 'Salvar Estratégia SMTP', 'person-cash-wallet' ); ?>
							</button>
						</div>
					</div>
				</div>
			</form>

			<!-- Horários de Disparo -->
			<?php
			$schedule = get_option( 'pcw_queue_schedule', array(
				'enabled'    => false,
				'start_hour' => 8,
				'end_hour'   => 18,
				'days'       => array( 1, 2, 3, 4, 5 ),
			) );
			$schedule_enabled = isset( $schedule['enabled'] ) ? (bool) $schedule['enabled'] : false;
			$start_hour = isset( $schedule['start_hour'] ) ? absint( $schedule['start_hour'] ) : 8;
			$end_hour = isset( $schedule['end_hour'] ) ? absint( $schedule['end_hour'] ) : 18;
			$active_days = isset( $schedule['days'] ) ? (array) $schedule['days'] : array( 1, 2, 3, 4, 5 );
			
			$day_names = array(
				0 => __( 'Dom', 'person-cash-wallet' ),
				1 => __( 'Seg', 'person-cash-wallet' ),
				2 => __( 'Ter', 'person-cash-wallet' ),
				3 => __( 'Qua', 'person-cash-wallet' ),
				4 => __( 'Qui', 'person-cash-wallet' ),
				5 => __( 'Sex', 'person-cash-wallet' ),
				6 => __( 'Sáb', 'person-cash-wallet' ),
			);
			
			// Verificar se está dentro do horário agora
			$current_hour = (int) current_time( 'G' );
			$current_day = (int) current_time( 'w' );
			$is_within_schedule = true;
			if ( $schedule_enabled ) {
				$is_within_schedule = ( $current_hour >= $start_hour && $current_hour < $end_hour && in_array( $current_day, $active_days, true ) );
			}
			?>
			<form id="queue-schedule-form" style="margin-top: 30px;">
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-clock" style="color: #8b5cf6;"></span>
							<?php esc_html_e( 'Horários de Disparo', 'person-cash-wallet' ); ?>
							<?php if ( $schedule_enabled ) : ?>
								<?php if ( $is_within_schedule ) : ?>
									<span style="background: #dcfce7; color: #16a34a; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-left: 10px;">
										● <?php esc_html_e( 'Dentro do horário', 'person-cash-wallet' ); ?>
									</span>
								<?php else : ?>
									<span style="background: #fef3c7; color: #d97706; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-left: 10px;">
										● <?php esc_html_e( 'Fora do horário', 'person-cash-wallet' ); ?>
									</span>
								<?php endif; ?>
							<?php endif; ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<p class="description" style="margin-bottom: 20px;">
							<?php esc_html_e( 'Configure os horários e dias em que os disparos da fila podem ser enviados. Fora desses horários, as mensagens ficam na fila aguardando.', 'person-cash-wallet' ); ?>
						</p>

						<!-- Toggle Ativar -->
						<div style="margin-bottom: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
							<label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 15px; font-weight: 600;">
								<input type="checkbox" name="schedule_enabled" id="pcw-schedule-enabled" value="1" <?php checked( $schedule_enabled ); ?> style="width: 18px; height: 18px;">
								<?php esc_html_e( 'Ativar restrição de horários', 'person-cash-wallet' ); ?>
							</label>
							<p style="margin: 8px 0 0 28px; color: #64748b; font-size: 13px;">
								<?php esc_html_e( 'Se desativado, os disparos acontecem 24 horas por dia, 7 dias por semana.', 'person-cash-wallet' ); ?>
							</p>
						</div>

						<div id="pcw-schedule-fields" style="<?php echo $schedule_enabled ? '' : 'opacity: 0.5; pointer-events: none;'; ?>">
							<!-- Horários -->
							<div style="margin-bottom: 25px;">
								<h4 style="margin: 0 0 12px; color: #1e293b; font-size: 14px;">
									<span class="dashicons dashicons-clock" style="font-size: 16px; margin-right: 4px;"></span>
									<?php esc_html_e( 'Horário de Funcionamento', 'person-cash-wallet' ); ?>
								</h4>
								<div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
									<div style="display: flex; align-items: center; gap: 8px;">
										<label style="font-weight: 500; color: #475569;"><?php esc_html_e( 'Das', 'person-cash-wallet' ); ?></label>
										<select name="start_hour" id="pcw-start-hour" style="width: 80px; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 15px; font-weight: 600;">
											<?php for ( $h = 0; $h <= 23; $h++ ) : ?>
												<option value="<?php echo $h; ?>" <?php selected( $start_hour, $h ); ?>>
													<?php echo str_pad( $h, 2, '0', STR_PAD_LEFT ); ?>:00
												</option>
											<?php endfor; ?>
										</select>
									</div>
									<div style="display: flex; align-items: center; gap: 8px;">
										<label style="font-weight: 500; color: #475569;"><?php esc_html_e( 'às', 'person-cash-wallet' ); ?></label>
										<select name="end_hour" id="pcw-end-hour" style="width: 80px; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 15px; font-weight: 600;">
											<?php for ( $h = 1; $h <= 23; $h++ ) : ?>
												<option value="<?php echo $h; ?>" <?php selected( $end_hour, $h ); ?>>
													<?php echo str_pad( $h, 2, '0', STR_PAD_LEFT ); ?>:00
												</option>
											<?php endfor; ?>
										</select>
									</div>
									<span style="color: #94a3b8; font-size: 13px;">
										(<?php echo ( $end_hour - $start_hour ); ?>h <?php esc_html_e( 'de funcionamento', 'person-cash-wallet' ); ?>)
									</span>
								</div>
							</div>

							<!-- Dias da semana -->
							<div>
								<h4 style="margin: 0 0 12px; color: #1e293b; font-size: 14px;">
									<span class="dashicons dashicons-calendar-alt" style="font-size: 16px; margin-right: 4px;"></span>
									<?php esc_html_e( 'Dias da Semana', 'person-cash-wallet' ); ?>
								</h4>
								<div style="display: flex; gap: 8px; flex-wrap: wrap;">
									<?php foreach ( $day_names as $day_num => $day_label ) : ?>
										<label style="display: flex; flex-direction: column; align-items: center; gap: 4px; cursor: pointer; padding: 10px 14px; border-radius: 8px; border: 2px solid <?php echo in_array( $day_num, $active_days, true ) ? '#6366f1' : '#e2e8f0'; ?>; background: <?php echo in_array( $day_num, $active_days, true ) ? '#eef2ff' : '#fff'; ?>; transition: all 0.2s; min-width: 55px;">
											<input type="checkbox" name="schedule_days[]" value="<?php echo $day_num; ?>" <?php checked( in_array( $day_num, $active_days, true ) ); ?> style="display: none;" class="pcw-day-checkbox">
											<span style="font-weight: 600; font-size: 14px; color: <?php echo in_array( $day_num, $active_days, true ) ? '#4f46e5' : '#94a3b8'; ?>;"><?php echo esc_html( $day_label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
								<div style="margin-top: 10px; display: flex; gap: 10px;">
									<button type="button" class="button button-small" id="pcw-select-weekdays">
										<?php esc_html_e( 'Seg a Sex', 'person-cash-wallet' ); ?>
									</button>
									<button type="button" class="button button-small" id="pcw-select-alldays">
										<?php esc_html_e( 'Todos os dias', 'person-cash-wallet' ); ?>
									</button>
								</div>
							</div>
						</div>

						<div class="pcw-form-actions" style="margin-top: 25px;">
							<button type="submit" class="button button-primary button-large">
								<span class="dashicons dashicons-saved"></span>
								<?php esc_html_e( 'Salvar Horários', 'person-cash-wallet' ); ?>
							</button>
						</div>
					</div>
				</div>
			</form>

			<div class="pcw-info-box pcw-info-success" style="margin-top: 30px;">
				<h3>
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Dicas de Uso', 'person-cash-wallet' ); ?>
				</h3>
				<ul>
					<li><strong>Round Robin:</strong> <?php esc_html_e( 'Melhor para distribuição uniforme e previsível entre todas as contas', 'person-cash-wallet' ); ?></li>
					<li><strong>Aleatório:</strong> <?php esc_html_e( 'Simula comportamento humano mais natural', 'person-cash-wallet' ); ?></li>
					<li><strong>Por Peso:</strong> <?php esc_html_e( 'Use quando algumas contas têm limites maiores', 'person-cash-wallet' ); ?></li>
					<li><strong><?php esc_html_e( 'Horários:', 'person-cash-wallet' ); ?></strong> <?php esc_html_e( 'Mensagens fora do horário ficam na fila e são enviadas no próximo horário disponível', 'person-cash-wallet' ); ?></li>
				</ul>
			</div>

			<script type="text/javascript">
			(function($) {
				$(document).ready(function() {
					// WhatsApp Strategy
					$('#whatsapp-distribution-form').on('submit', function(e) {
						e.preventDefault();
						var strategy = $('input[name="whatsapp_strategy"]:checked').val();
						saveStrategy('whatsapp', strategy, $(this));
					});

					// SMTP Strategy
					$('#smtp-distribution-form').on('submit', function(e) {
						e.preventDefault();
						var strategy = $('input[name="smtp_strategy"]:checked').val();
						saveStrategy('smtp', strategy, $(this));
					});

					// Toggle ativo
					$('.pcw-strategy-option input[type="radio"]').on('change', function() {
						$(this).closest('.pcw-strategy-options').find('.pcw-strategy-option').removeClass('active');
						$(this).closest('.pcw-strategy-option').addClass('active');
					});

					function saveStrategy(type, strategy, form) {
						var submitBtn = form.find('button[type="submit"]');
						var originalText = submitBtn.html();

						submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Salvando...');

						$.post(pcwQueue.ajaxUrl, {
							action: 'pcw_save_distribution_strategy',
							nonce: pcwQueue.nonce,
							type: type,
							strategy: strategy
						}, function(response) {
							if (response.success) {
								var notice = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
								form.find('.pcw-card-body').prepend(notice);
								setTimeout(function() { notice.fadeOut(); }, 3000);
							} else {
								alert(response.data.message || 'Erro ao salvar');
							}
							submitBtn.prop('disabled', false).html(originalText);
						}).fail(function() {
							alert('Erro de conexão');
							submitBtn.prop('disabled', false).html(originalText);
						});
					}

					// ============================================
					// Horários de Disparo
					// ============================================

					// Toggle habilitar/desabilitar campos
					$('#pcw-schedule-enabled').on('change', function() {
						var $fields = $('#pcw-schedule-fields');
						if ($(this).is(':checked')) {
							$fields.css({ 'opacity': '1', 'pointer-events': 'auto' });
						} else {
							$fields.css({ 'opacity': '0.5', 'pointer-events': 'none' });
						}
					});

					// Toggle visual dos dias
					$('.pcw-day-checkbox').on('change', function() {
						var $label = $(this).closest('label');
						if ($(this).is(':checked')) {
							$label.css({ 'border-color': '#6366f1', 'background': '#eef2ff' });
							$label.find('span').css('color', '#4f46e5');
						} else {
							$label.css({ 'border-color': '#e2e8f0', 'background': '#fff' });
							$label.find('span').css('color', '#94a3b8');
						}
					});

					// Atalho: Seg a Sex
					$('#pcw-select-weekdays').on('click', function() {
						$('.pcw-day-checkbox').each(function() {
							var val = parseInt($(this).val());
							$(this).prop('checked', val >= 1 && val <= 5).trigger('change');
						});
					});

					// Atalho: Todos os dias
					$('#pcw-select-alldays').on('click', function() {
						$('.pcw-day-checkbox').prop('checked', true).trigger('change');
					});

					// Salvar horários
					$('#queue-schedule-form').on('submit', function(e) {
						e.preventDefault();
						var $form = $(this);
						var submitBtn = $form.find('button[type="submit"]');
						var originalText = submitBtn.html();

						var startHour = parseInt($('#pcw-start-hour').val());
						var endHour = parseInt($('#pcw-end-hour').val());

						if ($('#pcw-schedule-enabled').is(':checked') && startHour >= endHour) {
							alert('<?php echo esc_js( __( 'O horário inicial deve ser menor que o final.', 'person-cash-wallet' ) ); ?>');
							return;
						}

						var days = [];
						$('.pcw-day-checkbox:checked').each(function() {
							days.push(parseInt($(this).val()));
						});

						if ($('#pcw-schedule-enabled').is(':checked') && days.length === 0) {
							alert('<?php echo esc_js( __( 'Selecione pelo menos um dia da semana.', 'person-cash-wallet' ) ); ?>');
							return;
						}

						submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Salvando...');

						$.post(pcwQueue.ajaxUrl, {
							action: 'pcw_save_queue_schedule',
							nonce: pcwQueue.nonce,
							enabled: $('#pcw-schedule-enabled').is(':checked') ? 1 : 0,
							start_hour: startHour,
							end_hour: endHour,
							days: days
						}, function(response) {
							if (response.success) {
								var notice = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
								$form.find('.pcw-card-body').prepend(notice);
								setTimeout(function() { notice.fadeOut(); location.reload(); }, 2000);
							} else {
								alert(response.data.message || 'Erro ao salvar');
							}
							submitBtn.prop('disabled', false).html(originalText);
						}).fail(function() {
							alert('Erro de conexão');
							submitBtn.prop('disabled', false).html(originalText);
						});
					});
				});
			})(jQuery);
			</script>
		</div>
		<?php
	}

	/**
	 * AJAX: Salvar número WhatsApp
	 */
	public function ajax_save_number() {
		check_ajax_referer( 'pcw_queue', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		$queue_manager = PCW_Message_Queue_Manager::instance();
		
		// Garantir que a coluna min_interval_seconds existe
		$queue_manager->ensure_min_interval_column();

		$id = absint( $_POST['id'] ?? 0 );
		$phone_number = sanitize_text_field( $_POST['phone_number'] ?? '' );

		// Se for edição e phone_number estiver vazio, buscar do banco
		if ( $id > 0 && empty( $phone_number ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'pcw_whatsapp_numbers';
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT phone_number, name FROM {$table} WHERE id = %d", $id ) );
			if ( $existing ) {
				$phone_number = $existing->phone_number;
			}
		}

		$args = array(
			'id'                    => $id,
			'phone_number'          => $phone_number,
			'name'                  => sanitize_text_field( $_POST['name'] ?? '' ),
			'provider'              => sanitize_text_field( $_POST['provider'] ?? 'evolution' ),
			'account_id'            => absint( $_POST['account_id'] ?? 0 ) ?: null,
			'status'                => sanitize_text_field( $_POST['status'] ?? 'active' ),
			'rate_limit_hour'       => absint( $_POST['rate_limit_hour'] ?? 60 ),
			'min_interval_seconds'  => absint( $_POST['min_interval_seconds'] ?? 30 ),
			'distribution_weight'   => absint( $_POST['distribution_weight'] ?? 100 ),
			'distribution_enabled'  => isset( $_POST['distribution_enabled'] ) ? 1 : 0,
		);

		$result = $queue_manager->save_whatsapp_number( $args );

		if ( $result !== false ) {
			// Recalcular pesos para que a soma seja 100%
			$this->rebalance_weights( $result );

			wp_send_json_success( array( 
				'message' => __( 'Número salvo com sucesso!', 'person-cash-wallet' ),
				'id'      => $result,
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Erro ao salvar número.', 'person-cash-wallet' ) ) );
		}
	}

	/**
	 * Rebalancear pesos para que a soma seja 100%
	 *
	 * @param int $current_id ID do número que foi salvo/editado
	 */
	private function rebalance_weights( $current_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_whatsapp_numbers';

		// Buscar todos os números ativos com distribuição habilitada
		$numbers = $wpdb->get_results(
			"SELECT id, distribution_weight FROM {$table} 
			WHERE status = 'active' AND distribution_enabled = 1 
			ORDER BY id ASC"
		);

		if ( count( $numbers ) <= 1 ) {
			// Se só tem 1 número, ele fica com 100%
			if ( count( $numbers ) === 1 ) {
				$wpdb->update( $table, array( 'distribution_weight' => 100 ), array( 'id' => $numbers[0]->id ) );
			}
			return;
		}

		// Calcular soma atual dos pesos
		$total_weight = array_sum( array_column( $numbers, 'distribution_weight' ) );

		if ( $total_weight === 0 ) {
			// Se todos têm peso 0, distribuir igualmente
			$equal_weight = floor( 100 / count( $numbers ) );
			$remainder = 100 - ( $equal_weight * count( $numbers ) );

			foreach ( $numbers as $index => $number ) {
				$weight = $equal_weight + ( $index === 0 ? $remainder : 0 );
				$wpdb->update( $table, array( 'distribution_weight' => $weight ), array( 'id' => $number->id ) );
			}
			return;
		}

		// Normalizar pesos para somar 100%
		$new_weights = array();
		$sum = 0;

		foreach ( $numbers as $number ) {
			$new_weight = round( ( $number->distribution_weight / $total_weight ) * 100 );
			$new_weights[ $number->id ] = $new_weight;
			$sum += $new_weight;
		}

		// Ajustar diferença de arredondamento
		if ( $sum !== 100 ) {
			$diff = 100 - $sum;
			// Adicionar/subtrair diferença do primeiro número
			$first_id = array_key_first( $new_weights );
			$new_weights[ $first_id ] += $diff;
		}

		// Atualizar no banco
		foreach ( $new_weights as $id => $weight ) {
			$wpdb->update( $table, array( 'distribution_weight' => max( 0, $weight ) ), array( 'id' => $id ) );
		}
	}

	/**
	 * AJAX: Deletar número WhatsApp
	 */
	public function ajax_delete_number() {
		check_ajax_referer( 'pcw_queue', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'ID inválido.', 'person-cash-wallet' ) ) );
		}

		$queue_manager = PCW_Message_Queue_Manager::instance();
		$result = $queue_manager->delete_whatsapp_number( $id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Número excluído com sucesso!', 'person-cash-wallet' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Erro ao excluir número.', 'person-cash-wallet' ) ) );
		}
	}

	/**
	 * AJAX: Obter estatísticas da fila
	 */
	public function ajax_get_queue_stats() {
		check_ajax_referer( 'pcw_queue', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		$queue_manager = PCW_Message_Queue_Manager::instance();
		$stats = $queue_manager->get_queue_stats();

		wp_send_json_success( array( 'stats' => $stats ) );
	}

	/**
	 * AJAX: Limpar mensagens falhadas
	 */
	public function ajax_clear_failed_queue() {
		check_ajax_referer( 'pcw_queue', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_message_queue';
		$wpdb->query( "DELETE FROM {$table} WHERE status = 'failed'" );

		wp_send_json_success( array( 'message' => __( 'Mensagens falhadas limpas com sucesso!', 'person-cash-wallet' ) ) );
	}

	/**
	 * AJAX: Reprocessar mensagens falhadas
	 */
	public function ajax_retry_failed_queue() {
		check_ajax_referer( 'pcw_queue', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_message_queue';
		$wpdb->update(
			$table,
			array(
				'status'   => 'pending',
				'attempts' => 0,
			),
			array( 'status' => 'failed' ),
			array( '%s', '%d' ),
			array( '%s' )
		);

		wp_send_json_success( array( 'message' => __( 'Mensagens marcadas para reprocessamento!', 'person-cash-wallet' ) ) );
	}

	/**
	 * AJAX: Reprocessar uma mensagem individual (falha -> pendente)
	 */
	public function ajax_retry_single_message() {
		check_ajax_referer( 'pcw_queue', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		$message_id = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
		if ( ! $message_id ) {
			wp_send_json_error( array( 'message' => __( 'ID da mensagem inválido.', 'person-cash-wallet' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_message_queue';

		// Verificar se a mensagem existe e está falhada
		$message = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $message_id ) );
		if ( ! $message ) {
			wp_send_json_error( array( 'message' => __( 'Mensagem não encontrada.', 'person-cash-wallet' ) ) );
		}

		if ( $message->status !== 'failed' ) {
			wp_send_json_error( array( 'message' => __( 'Apenas mensagens falhadas podem ser reprocessadas.', 'person-cash-wallet' ) ) );
		}

		// Resetar para pendente com tentativas zeradas e novo agendamento
		$result = $wpdb->update(
			$table,
			array(
				'status'        => 'pending',
				'attempts'      => 0,
				'error_message' => null,
				'response_data' => null,
				'processed_at'  => null,
				'scheduled_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $message_id ),
			array( '%s', '%d', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => __( 'Erro ao reprocessar mensagem.', 'person-cash-wallet' ) ) );
		}

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: message ID */
				__( 'Mensagem #%d enviada para a fila novamente!', 'person-cash-wallet' ),
				$message_id
			),
		) );
	}

	/**
	 * AJAX: Salvar estratégia de distribuição
	 */
	public function ajax_save_distribution_strategy() {
		check_ajax_referer( 'pcw_queue', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		$type = sanitize_text_field( $_POST['type'] ?? 'whatsapp' );
		$strategy = sanitize_text_field( $_POST['strategy'] ?? 'round_robin' );
		
		if ( ! in_array( $strategy, array( 'round_robin', 'random', 'weighted' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Estratégia inválida.', 'person-cash-wallet' ) ) );
		}

		$option_name = $type === 'smtp' ? 'pcw_smtp_distribution_strategy' : 'pcw_whatsapp_distribution_strategy';
		update_option( $option_name, $strategy );

		$message = $type === 'smtp' 
			? __( 'Estratégia SMTP salva com sucesso!', 'person-cash-wallet' )
			: __( 'Estratégia WhatsApp salva com sucesso!', 'person-cash-wallet' );

		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * AJAX: Obter dados de um número específico
	 */
	public function ajax_get_number() {
		check_ajax_referer( 'pcw_queue', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'ID inválido.', 'person-cash-wallet' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_whatsapp_numbers';
		$number = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		if ( ! $number ) {
			wp_send_json_error( array( 'message' => __( 'Número não encontrado.', 'person-cash-wallet' ) ) );
		}

		wp_send_json_success( array(
			'number' => array(
				'id'                    => $number->id,
				'phone_number'          => $number->phone_number,
				'name'                  => $number->name,
				'provider'              => isset( $number->provider ) ? $number->provider : 'evolution',
				'account_id'            => isset( $number->account_id ) ? $number->account_id : null,
				'status'                => $number->status,
				'rate_limit_hour'       => $number->rate_limit_hour,
				'min_interval_seconds'  => isset( $number->min_interval_seconds ) ? $number->min_interval_seconds : 30,
				'distribution_weight'   => $number->distribution_weight,
				'distribution_enabled'  => $number->distribution_enabled,
			),
		) );
	}

	/**
	 * AJAX: Buscar templates de uma conta WhatsApp
	 */
	public function ajax_get_templates() {
		check_ajax_referer( 'pcw_queue', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		$phone = sanitize_text_field( $_POST['phone'] ?? '' );
		if ( empty( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'Número não informado.', 'person-cash-wallet' ) ) );
		}

		$personizi = PCW_Personizi_Integration::instance();
		$templates = $personizi->get_templates( $phone );

		if ( is_wp_error( $templates ) ) {
			wp_send_json_error( array( 'message' => $templates->get_error_message() ) );
		}

		wp_send_json_success( array( 'templates' => $templates ) );
	}

	/**
	 * Renderizar aba de contas SMTP
	 */
	private function render_smtp_tab() {
		// Atualizar automaticamente contas FluentSMTP que não têm campos de rate limiting
		$this->auto_sync_fluentsmtp();
		
		$queue_manager = PCW_Message_Queue_Manager::instance();
		$smtp_accounts = $queue_manager->get_smtp_stats();
		?>
		<div class="pcw-smtp-section">
			<div class="pcw-section-header">
				<div>
					<h2><?php esc_html_e( 'Contas SMTP Configuradas', 'person-cash-wallet' ); ?></h2>
					<p class="description" style="margin: 8px 0 0;">
						<?php esc_html_e( 'Configure rate limiting e distribuição para suas contas SMTP', 'person-cash-wallet' ); ?>
					</p>
				</div>
				<div style="display: flex; gap: 10px;">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-settings&tab=smtp' ) ); ?>" class="button button-primary">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Gerenciar Contas SMTP', 'person-cash-wallet' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-settings&tab=sendpulse' ) ); ?>" class="button">
						<span class="dashicons dashicons-email"></span>
						<?php esc_html_e( 'Configurar SendPulse', 'person-cash-wallet' ); ?>
					</a>
				</div>
			</div>

			<?php if ( empty( $smtp_accounts ) ) : ?>
				<div class="pcw-empty-state">
					<div class="pcw-empty-icon">
						<span class="dashicons dashicons-email"></span>
					</div>
					<h3><?php esc_html_e( 'Nenhuma conta SMTP configurada', 'person-cash-wallet' ); ?></h3>
					<p><?php esc_html_e( 'Configure contas SMTP para enviar emails com rate limiting e distribuição automática.', 'person-cash-wallet' ); ?></p>
					<div style="display: flex; gap: 12px; justify-content: center;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-settings&tab=smtp' ) ); ?>" class="button button-primary button-hero">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Configurar SMTP', 'person-cash-wallet' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-settings&tab=sendpulse' ) ); ?>" class="button button-hero">
							<span class="dashicons dashicons-email"></span>
							<?php esc_html_e( 'Usar SendPulse', 'person-cash-wallet' ); ?>
						</a>
					</div>
				</div>
			<?php else : ?>
				<form id="smtp-rate-form">
					<div class="pcw-smtp-grid">
					<?php foreach ( $smtp_accounts as $account ) : ?>
						<?php
						// Identificar tipo de conta
						$is_sendpulse = ( $account->provider === 'sendpulse' || strpos( $account->id, 'sendpulse_' ) === 0 );
						$is_fluent = ( $account->provider === 'fluentsmtp' || ! empty( $account->is_fluent ) );
						$is_inactive = ( $account->status !== 'active' );
						$card_style = $is_inactive ? 'opacity: 0.6; border: 2px dashed #cbd5e1;' : '';
						?>
						<div class="pcw-smtp-card" data-id="<?php echo esc_attr( $account->id ); ?>" style="<?php echo esc_attr( $card_style ); ?>">
								<div class="pcw-smtp-header">
									<div class="pcw-smtp-title">
										<?php if ( $is_sendpulse ) : ?>
											<span class="pcw-smtp-icon">📬</span>
										<?php elseif ( $is_fluent ) : ?>
											<span class="pcw-smtp-icon">💌</span>
										<?php else : ?>
											<span class="pcw-smtp-icon">📧</span>
										<?php endif; ?>
										<div>
											<h3><?php echo esc_html( $account->name ); ?></h3>
											<p class="pcw-smtp-email"><?php echo esc_html( $account->from_email ); ?></p>
										</div>
									</div>
								<div class="pcw-smtp-status">
									<?php if ( $account->status === 'active' ) : ?>
										<span class="pcw-badge pcw-badge-success">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php esc_html_e( 'Ativa', 'person-cash-wallet' ); ?>
										</span>
									<?php else : ?>
										<span class="pcw-badge pcw-badge-inactive">
											<span class="dashicons dashicons-dismiss"></span>
											<?php esc_html_e( 'Inativa', 'person-cash-wallet' ); ?>
										</span>
									<?php endif; ?>
								</div>
							</div>

							<?php if ( $is_inactive ) : ?>
								<div style="margin: 12px 0; padding: 10px; background: #fef3c7; border-left: 3px solid #f59e0b; border-radius: 4px;">
									<p style="margin: 0; color: #92400e; font-size: 13px;">
										<span class="dashicons dashicons-info" style="font-size: 16px; vertical-align: middle;"></span>
										<strong><?php esc_html_e( 'Esta conta está desativada', 'person-cash-wallet' ); ?></strong><br>
										<span style="font-size: 12px;"><?php esc_html_e( 'Não será usada para envio de emails. Marque "Conta Ativa" e salve para reativar.', 'person-cash-wallet' ); ?></span>
									</p>
								</div>
							<?php endif; ?>

							<div class="pcw-smtp-config">
								<div class="pcw-form-row">
									<div class="pcw-form-group">
										<label><?php esc_html_e( 'Limite/Hora', 'person-cash-wallet' ); ?></label>
										<input type="number" 
											class="smtp-rate-input" 
											data-account-id="<?php echo esc_attr( $account->id ); ?>"
											value="<?php echo esc_attr( $account->rate_limit_hour ); ?>" 
											min="1" max="1000">
										<span class="pcw-input-suffix">msgs</span>
									</div>
									<div class="pcw-form-group">
										<label><?php esc_html_e( 'Peso (%)', 'person-cash-wallet' ); ?></label>
										<input type="number" 
											class="smtp-weight-input" 
											data-account-id="<?php echo esc_attr( $account->id ); ?>"
											value="<?php echo esc_attr( $account->distribution_weight ); ?>" 
											min="0" max="100">
										<span class="pcw-input-suffix">%</span>
									</div>
								</div>

								<div class="pcw-form-row" style="gap: 20px;">
									<div class="pcw-form-group">
										<label>
											<input type="checkbox" 
												class="smtp-distribution-toggle" 
												data-account-id="<?php echo esc_attr( $account->id ); ?>"
												<?php checked( $account->distribution_enabled, 1 ); ?>>
											<?php esc_html_e( 'Habilitar para distribuição automática', 'person-cash-wallet' ); ?>
										</label>
									</div>
									<div class="pcw-form-group">
										<label>
											<input type="checkbox" 
												class="smtp-status-toggle" 
												data-account-id="<?php echo esc_attr( $account->id ); ?>"
												<?php checked( $account->status, 'active' ); ?>>
											<strong><?php esc_html_e( 'Conta Ativa', 'person-cash-wallet' ); ?></strong>
										</label>
										<p class="description" style="margin: 4px 0 0 0; font-size: 11px;">
											<?php esc_html_e( 'Desmarque para desativar a conta completamente', 'person-cash-wallet' ); ?>
										</p>
									</div>
								</div>
							</div>

								<div class="pcw-smtp-stats">
									<div class="pcw-stat">
										<div class="pcw-stat-label"><?php esc_html_e( 'Enviados (1h)', 'person-cash-wallet' ); ?></div>
										<div class="pcw-stat-value pcw-stat-highlight">
											<?php echo esc_html( $account->sent_last_hour ); ?> / <?php echo esc_html( $account->rate_limit_hour ); ?>
										</div>
									</div>
									<div class="pcw-stat">
										<div class="pcw-stat-label"><?php esc_html_e( 'Hoje', 'person-cash-wallet' ); ?></div>
										<div class="pcw-stat-value">
											<?php echo esc_html( number_format_i18n( $account->sent_today ) ); ?>
										</div>
									</div>
									<div class="pcw-stat">
										<div class="pcw-stat-label"><?php esc_html_e( 'Total', 'person-cash-wallet' ); ?></div>
										<div class="pcw-stat-value">
											<?php echo esc_html( number_format_i18n( $account->total_sent ) ); ?>
										</div>
									</div>
								</div>

								<div class="pcw-smtp-progress">
									<?php
									$percentage = $account->rate_limit_hour > 0 ? min( 100, ( $account->sent_last_hour / $account->rate_limit_hour ) * 100 ) : 0;
									$progress_class = $percentage >= 90 ? 'pcw-progress-danger' : ( $percentage >= 70 ? 'pcw-progress-warning' : 'pcw-progress-success' );
									?>
									<div class="pcw-progress-bar">
										<div class="pcw-progress-fill <?php echo esc_attr( $progress_class ); ?>" style="width: <?php echo esc_attr( $percentage ); ?>%"></div>
									</div>
									<p class="pcw-progress-label">
										<?php echo esc_html( sprintf( __( '%d%% do limite horário utilizado', 'person-cash-wallet' ), round( $percentage ) ) ); ?>
									</p>
								</div>

								<button type="button" class="button button-small pcw-save-smtp-config" data-id="<?php echo esc_attr( $account->id ); ?>">
									<span class="dashicons dashicons-saved"></span>
									<?php esc_html_e( 'Salvar Configurações', 'person-cash-wallet' ); ?>
								</button>
								<?php if ( $is_fluent ) : ?>
									<p class="description" style="margin: 8px 0 0; font-size: 11px; color: #666;">
										<?php esc_html_e( 'Credenciais gerenciadas pelo FluentSMTP', 'person-cash-wallet' ); ?>
									</p>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</form>
			<?php endif; ?>

			<div class="pcw-info-box" style="margin-top: 30px;">
				<h3>
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'Sobre Rate Limiting para SMTP', 'person-cash-wallet' ); ?>
				</h3>
				<ul>
					<li><?php esc_html_e( 'Cada provedor de email tem limites diferentes (Gmail: 100-500/dia, SendGrid: milhares/hora)', 'person-cash-wallet' ); ?></li>
					<li><?php esc_html_e( 'Configure o rate limit de acordo com seu plano e provedor', 'person-cash-wallet' ); ?></li>
					<li><?php esc_html_e( 'O sistema distribui emails entre as contas conforme a estratégia escolhida', 'person-cash-wallet' ); ?></li>
					<li><?php esc_html_e( 'Contas desabilitadas não recebem emails na distribuição automática', 'person-cash-wallet' ); ?></li>
				</ul>
			</div>
		</div>

		<script type="text/javascript">
		(function($) {
			$(document).ready(function() {
				// Salvar configurações SMTP
				$(document).on('click', '.pcw-save-smtp-config', function(e) {
					e.preventDefault();
					
					var btn = $(this);
					var card = btn.closest('.pcw-smtp-card');
					var accountId = card.data('id');
					var originalText = btn.html();
					
					btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Salvando...');

					$.post(ajaxurl, {
						action: 'pcw_update_smtp_rate_limit',
						nonce: '<?php echo wp_create_nonce( 'pcw_queue' ); ?>',
						account_id: accountId,
						rate_limit_hour: card.find('.smtp-rate-input').val(),
						distribution_weight: card.find('.smtp-weight-input').val(),
						distribution_enabled: card.find('.smtp-distribution-toggle').is(':checked') ? 1 : 0,
						status: card.find('.smtp-status-toggle').is(':checked') ? 'active' : 'inactive'
					}, function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || 'Erro ao salvar');
							btn.prop('disabled', false).html(originalText);
						}
					}).fail(function() {
						alert('Erro de conexão');
						btn.prop('disabled', false).html(originalText);
					});
				});
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * AJAX: Atualizar rate limit e distribuição da conta SMTP
	 */
	public function ajax_update_smtp_rate_limit() {
		check_ajax_referer( 'pcw_queue', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		global $wpdb;

		$account_id = sanitize_text_field( $_POST['account_id'] ?? '' );
		if ( empty( $account_id ) ) {
			wp_send_json_error( array( 'message' => __( 'ID inválido.', 'person-cash-wallet' ) ) );
		}

		$rate_limit_hour      = absint( $_POST['rate_limit_hour'] ?? 60 );
		$distribution_weight  = absint( $_POST['distribution_weight'] ?? 100 );
		$distribution_enabled = absint( $_POST['distribution_enabled'] ?? 0 );
		$status               = isset( $_POST['status'] ) && in_array( $_POST['status'], array( 'active', 'inactive' ), true ) 
			? sanitize_text_field( $_POST['status'] ) 
			: 'active';

		// Verificar se é SendPulse
		if ( strpos( $account_id, 'sendpulse_' ) === 0 ) {
			$sendpulse_id = absint( str_replace( 'sendpulse_', '', $account_id ) );
			$sendpulse_table = $wpdb->prefix . 'pcw_sendpulse_accounts';

			$result = $wpdb->update(
				$sendpulse_table,
				array(
					'rate_limit_hour'      => $rate_limit_hour,
					'distribution_weight'  => $distribution_weight,
					'distribution_enabled' => $distribution_enabled,
					'status'               => $status,
				),
				array( 'id' => $sendpulse_id ),
				array( '%d', '%d', '%d', '%s' ),
				array( '%d' )
			);

			$this->rebalance_smtp_weights();

			wp_send_json_success( array( 
				'message' => __( 'Configurações SendPulse salvas com sucesso!', 'person-cash-wallet' ),
			) );
			return;
		}

		// Verificar se é FluentSMTP não importado (ID com prefixo fluent_)
		// Contas FluentSMTP importadas para a tabela têm IDs numéricos e podem ser editadas
		if ( strpos( $account_id, 'fluent_' ) === 0 ) {
			wp_send_json_error( array( 'message' => __( 'Esta conta FluentSMTP ainda não foi importada. Recarregue a página.', 'person-cash-wallet' ) ) );
			return;
		}

		// Conta SMTP normal
		$table = $wpdb->prefix . 'pcw_smtp_accounts';

		$result = $wpdb->update(
			$table,
			array(
				'rate_limit_hour'      => $rate_limit_hour,
				'distribution_weight'  => $distribution_weight,
				'distribution_enabled' => $distribution_enabled,
				'status'               => $status,
				'updated_at'           => current_time( 'mysql' ),
			),
			array( 'id' => absint( $account_id ) ),
			array( '%d', '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			// Rebalancear pesos das contas SMTP
			$this->rebalance_smtp_weights();

			wp_send_json_success( array( 
				'message' => __( 'Configurações salvas com sucesso!', 'person-cash-wallet' ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Erro ao salvar configurações.', 'person-cash-wallet' ) ) );
		}
	}

	/**
	 * Rebalancear pesos SMTP para que a soma seja 100%
	 */
	private function rebalance_smtp_weights() {
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_smtp_accounts';

		// Buscar contas SMTP normais
		$accounts = $wpdb->get_results(
			"SELECT id, distribution_weight FROM {$table} 
			WHERE status = 'active' AND distribution_enabled = 1 
			ORDER BY id ASC"
		);

		// Adicionar contas SendPulse
		$sendpulse_table = $wpdb->prefix . 'pcw_sendpulse_accounts';
		$sendpulse_accounts = $wpdb->get_results(
			"SELECT id, distribution_weight FROM {$sendpulse_table} 
			WHERE status = 'active' AND distribution_enabled = 1 
			ORDER BY id ASC"
		);

		$all_accounts = $accounts;
		
		foreach ( $sendpulse_accounts as $sp ) {
			$all_accounts[] = (object) array(
				'id'                   => 'sendpulse_' . $sp->id,
				'distribution_weight'  => $sp->distribution_weight,
			);
		}

		if ( count( $all_accounts ) <= 1 ) {
			if ( count( $all_accounts ) === 1 ) {
				if ( $all_accounts[0]->id === 'sendpulse' ) {
					update_option( 'pcw_sendpulse_distribution_weight', 100 );
				} else {
					$wpdb->update( $table, array( 'distribution_weight' => 100 ), array( 'id' => $all_accounts[0]->id ) );
				}
			}
			return;
		}

		$total_weight = array_sum( array_column( $all_accounts, 'distribution_weight' ) );

		if ( $total_weight === 0 ) {
			$equal_weight = floor( 100 / count( $all_accounts ) );
			$remainder = 100 - ( $equal_weight * count( $all_accounts ) );

			foreach ( $all_accounts as $index => $account ) {
				$weight = $equal_weight + ( $index === 0 ? $remainder : 0 );
				if ( strpos( $account->id, 'sendpulse_' ) === 0 ) {
					$sp_id = str_replace( 'sendpulse_', '', $account->id );
					$wpdb->update( $sendpulse_table, array( 'distribution_weight' => $weight ), array( 'id' => absint( $sp_id ) ) );
				} else {
					$wpdb->update( $table, array( 'distribution_weight' => $weight ), array( 'id' => $account->id ) );
				}
			}
			return;
		}

		$new_weights = array();
		$sum = 0;

		foreach ( $all_accounts as $account ) {
			$new_weight = round( ( $account->distribution_weight / $total_weight ) * 100 );
			$new_weights[ $account->id ] = $new_weight;
			$sum += $new_weight;
		}

		if ( $sum !== 100 ) {
			$diff = 100 - $sum;
			$first_id = array_key_first( $new_weights );
			$new_weights[ $first_id ] += $diff;
		}

		foreach ( $new_weights as $id => $weight ) {
			if ( strpos( $id, 'sendpulse_' ) === 0 ) {
				$sp_id = str_replace( 'sendpulse_', '', $id );
				$wpdb->update( $sendpulse_table, array( 'distribution_weight' => max( 0, $weight ) ), array( 'id' => absint( $sp_id ) ) );
			} else {
				$wpdb->update( $table, array( 'distribution_weight' => max( 0, $weight ) ), array( 'id' => $id ) );
			}
		}
	}

	/**
	 * Sincronizar automaticamente contas FluentSMTP
	 */
	private function auto_sync_fluentsmtp() {
		global $wpdb;
		$smtp_table = $wpdb->prefix . 'pcw_smtp_accounts';

		$this->pcw_log_smtp_sync( 'Auto sync start' );

		// Migrar tabela: adicionar colunas faltantes de rate limiting
		$this->migrate_smtp_table();

		// Limpar registros FluentSMTP potencialmente corrompidos (sem from_email ou status)
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$smtp_table} WHERE provider = %s AND (from_email IS NULL OR from_email = '' OR status IS NULL OR status = '')",
				'fluentsmtp'
			)
		);
		if ( $deleted > 0 ) {
			$this->pcw_log_smtp_sync( 'Cleaned up corrupted records: ' . $deleted );
		}

		// Atualizar contas FluentSMTP existentes que não têm campos de rate limiting
		$updated_rows = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$smtp_table} 
				SET rate_limit_hour = COALESCE(rate_limit_hour, %d), 
				    distribution_weight = COALESCE(distribution_weight, %d), 
				    distribution_enabled = COALESCE(distribution_enabled, %d),
				    sent_last_hour = COALESCE(sent_last_hour, 0),
				    sent_today = COALESCE(sent_today, 0),
				    total_sent = COALESCE(total_sent, 0),
				    total_failed = COALESCE(total_failed, 0)
				WHERE provider = %s",
				60,
				100,
				0,
				'fluentsmtp'
			)
		);
		$this->pcw_log_smtp_sync( 'Updated rows: ' . absint( $updated_rows ) );

		// Importar contas FluentSMTP que aparecem em pcw-settings&tab=smtp
		if ( ! class_exists( 'PCW_SMTP_Accounts' ) ) {
			$this->pcw_log_smtp_sync( 'PCW_SMTP_Accounts class not found' );
			return;
		}

		$fluent_accounts = PCW_SMTP_Accounts::instance()->get_fluent_smtp_connections();
		if ( empty( $fluent_accounts ) ) {
			$this->pcw_log_smtp_sync( 'No FluentSMTP accounts found from settings' );
			return;
		}

		$this->pcw_log_smtp_sync( 'FluentSMTP accounts found: ' . count( $fluent_accounts ) );

		$existing = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT from_email FROM {$smtp_table} WHERE provider = %s",
				'fluentsmtp'
			)
		);
		$this->pcw_log_smtp_sync( 'Existing FluentSMTP emails: ' . count( $existing ) );

		foreach ( $fluent_accounts as $fa ) {
			// Log completo do array para debug
			$this->pcw_log_smtp_sync( 'Account data: ' . json_encode( $fa ) );
			
			$from_email = isset( $fa['from_email'] ) ? sanitize_email( $fa['from_email'] ) : '';
			$from_name  = isset( $fa['from_name'] ) ? sanitize_text_field( $fa['from_name'] ) : '';
			$name       = isset( $fa['name'] ) ? sanitize_text_field( $fa['name'] ) : 'FluentSMTP';
			$fa_id      = isset( $fa['id'] ) ? absint( $fa['id'] ) : 0;

			$this->pcw_log_smtp_sync( 'Extracted - Email: [' . $from_email . '] Name: [' . $name . ']' );

			if ( empty( $from_email ) || in_array( $from_email, $existing, true ) ) {
				$this->pcw_log_smtp_sync( 'Skip account - Empty email or already exists' );
				continue;
			}

			$insert_result = $wpdb->insert(
				$smtp_table,
				array(
					'name'                  => $name,
					'from_email'            => $from_email,
					'from_name'             => $from_name,
					'provider'              => 'fluentsmtp',
					'fluent_connection_id'  => $fa_id,
					'rate_limit_hour'       => 60,
					'distribution_weight'   => 100,
					'distribution_enabled'  => 0,
					'sent_last_hour'        => 0,
					'sent_today'            => 0,
					'total_sent'            => 0,
					'total_failed'          => 0,
					'status'                => 'active',
					'created_at'            => current_time( 'mysql' ),
					'updated_at'            => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
			);
			
			if ( $insert_result === false ) {
				$this->pcw_log_smtp_sync( 'Insert FAILED for: ' . $from_email . ' - Error: ' . $wpdb->last_error );
			}

			$this->pcw_log_smtp_sync( 'Imported account: ' . $from_email );
			$existing[] = $from_email;
		}

		$this->pcw_log_smtp_sync( 'Auto sync end' );
	}

	/**
	 * Log de sincronizacao FluentSMTP
	 *
	 * @param string $message Mensagem.
	 */
	private function pcw_log_smtp_sync( $message ) {
		$log_dir = WP_CONTENT_DIR . '/pcw-logs';
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		$log_file = $log_dir . '/pcw-smtp-sync.log';
		$time = date( 'Y-m-d H:i:s' );
		$line = '[' . $time . '] ' . $message . PHP_EOL;

		file_put_contents( $log_file, $line, FILE_APPEND );
	}

	/**
	 * AJAX: Obter mensagens da fila
	 */
	public function ajax_get_queue_messages() {
		check_ajax_referer( 'pcw_queue', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_message_queue';

		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;

		$offset = ( $page - 1 ) * $per_page;

		// Build WHERE clause
		$where = array( '1=1' );
		$where_values = array();

		if ( ! empty( $type ) ) {
			$where[] = 'type = %s';
			$where_values[] = $type;
		}

		if ( ! empty( $status ) ) {
			$where[] = 'status = %s';
			$where_values[] = $status;
		}

		$where_sql = implode( ' AND ', $where );

		// Count total
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( ! empty( $where_values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $where_values );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		// Get messages
		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY scheduled_at DESC, id DESC LIMIT %d OFFSET %d";
		$query_values = array_merge( $where_values, array( $per_page, $offset ) );
		$messages = $wpdb->get_results( $wpdb->prepare( $sql, $query_values ) );

		// Format messages
		$formatted = array();
		foreach ( $messages as $msg ) {
			$preview = '';
			if ( 'email' === $msg->type ) {
				$email_data = json_decode( $msg->message, true );
				$preview = isset( $email_data['subject'] ) ? $email_data['subject'] : '';
			} else {
				$preview = substr( wp_strip_all_tags( $msg->message ), 0, 100 );
			}

			$formatted[] = array(
				'id'           => $msg->id,
				'type'         => $msg->type,
				'to_number'    => $msg->to_number,
				'from_number'  => $msg->from_number,
				'preview'      => $preview,
				'status'       => $msg->status,
				'scheduled_at' => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $msg->scheduled_at ),
			);
		}

		wp_send_json_success( array(
			'messages' => $formatted,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		) );
	}

	/**
	 * AJAX: Obter detalhes de uma mensagem
	 */
	public function ajax_get_message_details() {
		check_ajax_referer( 'pcw_queue', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_message_queue';

		$message_id = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
		if ( ! $message_id ) {
			wp_send_json_error( array( 'message' => __( 'ID inválido.', 'person-cash-wallet' ) ) );
		}

		$message = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $message_id ) );
		if ( ! $message ) {
			wp_send_json_error( array( 'message' => __( 'Mensagem não encontrada.', 'person-cash-wallet' ) ) );
		}

		// Decodificar response_data para obter detalhes do erro
		$response_data = null;
		if ( ! empty( $message->response_data ) ) {
			$response_data = json_decode( $message->response_data, true );
		}

		$data = array(
			'type'          => $message->type,
			'to_number'     => $message->to_number,
			'from_number'   => $message->from_number,
			'status'        => $message->status,
			'attempts'      => $message->attempts,
			'max_attempts'  => $message->max_attempts,
			'scheduled_at'  => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $message->scheduled_at ),
			'processed_at'  => $message->processed_at ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $message->processed_at ) : null,
			'error_message' => $message->error_message,
			'message'       => $message->message,
			'response_data' => $response_data,
			'created_at'    => $message->created_at ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $message->created_at ) : null,
		);

		// For emails, decode and format
		if ( 'email' === $message->type ) {
			$email_data = json_decode( $message->message, true );
			if ( $email_data ) {
				$data['subject'] = isset( $email_data['subject'] ) ? $email_data['subject'] : '';
				$data['body_html'] = isset( $email_data['body'] ) ? $email_data['body'] : '';
				$data['message'] = wp_strip_all_tags( $data['body_html'] );
			}
		}

		wp_send_json_success( $data );
	}

	/**
	 * Obter estatísticas horárias por número WhatsApp
	 *
	 * @return array
	 */
	private function get_numbers_hourly_stats() {
		global $wpdb;
		
		$numbers_table = $wpdb->prefix . 'pcw_whatsapp_numbers';
		$queue_table   = $wpdb->prefix . 'pcw_message_queue';
		
		// Verificar se a tabela existe
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$numbers_table}'" );
		if ( ! $table_exists ) {
			return array();
		}
		
		$numbers = $wpdb->get_results(
			"SELECT id, phone_number, name, rate_limit_hour, status FROM {$numbers_table} ORDER BY name ASC"
		);
		
		if ( empty( $numbers ) ) {
			return array();
		}
		
		$stats = array();
		$now = current_time( 'mysql' );
		
		foreach ( $numbers as $number ) {
			$phone = $number->phone_number;
			
			// Enviados na última hora
			$sent_last_hour = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$queue_table} 
					WHERE from_number = %s 
					AND type IN ('whatsapp', 'whatsapp_template')
					AND status = 'sent'
					AND processed_at >= DATE_SUB(%s, INTERVAL 1 HOUR)",
					$phone,
					$now
				)
			);
			
			// Enviados hoje
			$sent_today = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$queue_table} 
					WHERE from_number = %s 
					AND type IN ('whatsapp', 'whatsapp_template')
					AND status = 'sent'
					AND DATE(processed_at) = CURDATE()",
					$phone
				)
			);
			
			// Enviados nos últimos 7 dias
			$sent_7d = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$queue_table} 
					WHERE from_number = %s 
					AND type IN ('whatsapp', 'whatsapp_template')
					AND status = 'sent'
					AND processed_at >= DATE_SUB(%s, INTERVAL 7 DAY)",
					$phone,
					$now
				)
			);
			
			// Falhas totais
			$failed = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$queue_table} 
					WHERE from_number = %s 
					AND type IN ('whatsapp', 'whatsapp_template')
					AND status = 'failed'",
					$phone
				)
			);
			
			// Total enviados (para calcular média)
			$total_sent = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$queue_table} 
					WHERE from_number = %s 
					AND type IN ('whatsapp', 'whatsapp_template')
					AND status = 'sent'",
					$phone
				)
			);
			
			// Calcular média por hora (baseado nos últimos 7 dias)
			// 7 dias = 168 horas
			$hours_active = 168; // Considera horário comercial? Podemos ajustar depois
			$avg_per_hour = $sent_7d > 0 ? $sent_7d / $hours_active : 0;
			
			// Alternativa: calcular baseado nas horas reais que teve envio
			$first_sent = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MIN(processed_at) FROM {$queue_table} 
					WHERE from_number = %s 
					AND type IN ('whatsapp', 'whatsapp_template')
					AND status = 'sent'",
					$phone
				)
			);
			
			if ( $first_sent && $total_sent > 0 ) {
				$first_timestamp = strtotime( $first_sent );
				$now_timestamp = current_time( 'timestamp' );
				$hours_since_first = max( 1, ( $now_timestamp - $first_timestamp ) / 3600 );
				$avg_per_hour = $total_sent / $hours_since_first;
			}
			
			$stats[] = array(
				'id'             => $number->id,
				'phone'          => $phone,
				'name'           => $number->name ?: $phone,
				'rate_limit'     => absint( $number->rate_limit_hour ),
				'status'         => $number->status,
				'sent_last_hour' => absint( $sent_last_hour ),
				'sent_today'     => absint( $sent_today ),
				'sent_7d'        => absint( $sent_7d ),
				'failed'         => absint( $failed ),
				'total_sent'     => absint( $total_sent ),
				'avg_per_hour'   => $avg_per_hour,
			);
		}
		
		return $stats;
	}

	/**
	 * Renderizar página de relatório do número
	 */
	private function render_number_report_page( $number_id ) {
		if ( ! $number_id ) {
			wp_die( __( 'Número inválido.', 'person-cash-wallet' ) );
		}

		global $wpdb;
		
		$numbers_table = $wpdb->prefix . 'pcw_whatsapp_numbers';
		$queue_table   = $wpdb->prefix . 'pcw_message_queue';
		
		$number = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$numbers_table} WHERE id = %d", $number_id )
		);
		
		if ( ! $number ) {
			wp_die( __( 'Número não encontrado.', 'person-cash-wallet' ) );
		}
		
		$phone = $number->phone_number;
		$now = current_time( 'mysql' );
		
		// Estatísticas gerais
		$total_sent = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table} 
				WHERE from_number = %s AND type IN ('whatsapp', 'whatsapp_template') AND status = 'sent'",
				$phone
			)
		);
		
		$total_failed = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table} 
				WHERE from_number = %s AND type IN ('whatsapp', 'whatsapp_template') AND status = 'failed'",
				$phone
			)
		);
		
		$total_pending = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table} 
				WHERE from_number = %s AND type IN ('whatsapp', 'whatsapp_template') AND status = 'pending'",
				$phone
			)
		);
		
		// Enviados por hora (últimas 24 horas)
		$hourly_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					HOUR(processed_at) as hour,
					COUNT(*) as count
				FROM {$queue_table}
				WHERE from_number = %s 
				AND type IN ('whatsapp', 'whatsapp_template')
				AND status = 'sent'
				AND processed_at >= DATE_SUB(%s, INTERVAL 24 HOUR)
				GROUP BY HOUR(processed_at)
				ORDER BY hour ASC",
				$phone,
				$now
			)
		);
		
		// Converter para array completo de 24 horas
		$hourly = array_fill( 0, 24, 0 );
		foreach ( $hourly_data as $row ) {
			$hourly[ (int) $row->hour ] = (int) $row->count;
		}
		
		// Enviados por dia (últimos 30 dias)
		$daily_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					DATE(processed_at) as date,
					COUNT(*) as count,
					SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as success_count,
					SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
				FROM {$queue_table}
				WHERE from_number = %s 
				AND type IN ('whatsapp', 'whatsapp_template')
				AND processed_at >= DATE_SUB(%s, INTERVAL 30 DAY)
				GROUP BY DATE(processed_at)
				ORDER BY date ASC",
				$phone,
				$now
			)
		);
		
		$daily = array();
		foreach ( $daily_data as $row ) {
			$daily[ $row->date ] = array(
				'total'   => (int) $row->count,
				'success' => (int) $row->success_count,
				'failed'  => (int) $row->failed_count,
			);
		}
		
		// Preencher dias faltantes
		$daily_filled = array();
		for ( $i = 29; $i >= 0; $i-- ) {
			$date = date( 'Y-m-d', strtotime( "-{$i} days" ) );
			$daily_filled[ $date ] = isset( $daily[ $date ] ) ? $daily[ $date ] : array( 'total' => 0, 'success' => 0, 'failed' => 0 );
		}
		
		// Últimas 20 mensagens
		$recent_messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, to_number, message, status, processed_at, scheduled_at, error_message
				FROM {$queue_table}
				WHERE from_number = %s AND type IN ('whatsapp', 'whatsapp_template')
				ORDER BY COALESCE(processed_at, scheduled_at) DESC
				LIMIT 20",
				$phone
			)
		);
		
		// Taxa de sucesso
		$total = $total_sent + $total_failed;
		$success_rate = $total > 0 ? round( ( $total_sent / $total ) * 100, 1 ) : 100;
		
		// Média por hora (últimos 7 dias)
		$sent_7d = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table}
				WHERE from_number = %s AND type IN ('whatsapp', 'whatsapp_template') AND status = 'sent'
				AND processed_at >= DATE_SUB(%s, INTERVAL 7 DAY)",
				$phone,
				$now
			)
		);
		$avg_per_hour = $sent_7d > 0 ? round( $sent_7d / 168, 1 ) : 0;
		
		// Pico de envios (hora com mais envios)
		$peak_hour = max( $hourly ) > 0 ? array_search( max( $hourly ), $hourly ) : 0;
		$peak_count = max( $hourly );
		
		// Preparar dados para o gráfico
		$chart_labels = array();
		$chart_success = array();
		$chart_failed = array();
		foreach ( $daily_filled as $date => $data ) {
			$chart_labels[] = date_i18n( 'd/m', strtotime( $date ) );
			$chart_success[] = $data['success'];
			$chart_failed[] = $data['failed'];
		}
		
		?>
		<div class="wrap pcw-workflow-analytics">
			<div class="pcw-page-header" style="margin-bottom: 20px;">
				<div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-queue&tab=queue' ) ); ?>" class="pcw-back-link">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php esc_html_e( 'Voltar para Filas', 'person-cash-wallet' ); ?>
					</a>
					<h1 style="margin: 10px 0 5px;">
						<span class="dashicons dashicons-phone" style="color: #25d366;"></span>
						<?php echo esc_html( $number->name ?: $phone ); ?>
					</h1>
					<p class="description" style="margin: 0;">
						<?php echo esc_html( $phone ); ?>
						<?php if ( $number->status === 'active' ) : ?>
							<span class="pcw-badge pcw-badge-success" style="margin-left: 10px;">
								<?php esc_html_e( 'Ativo', 'person-cash-wallet' ); ?>
							</span>
						<?php else : ?>
							<span class="pcw-badge pcw-badge-inactive" style="margin-left: 10px;">
								<?php esc_html_e( 'Inativo', 'person-cash-wallet' ); ?>
							</span>
						<?php endif; ?>
					</p>
				</div>
			</div>

			<!-- Stats Cards -->
			<div class="pcw-stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 30px;">
				<div class="pcw-stat-card">
					<div class="pcw-stat-icon pcw-stat-sent">
						<span class="dashicons dashicons-yes-alt"></span>
					</div>
					<div class="pcw-stat-content">
						<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $total_sent ) ); ?></div>
						<div class="pcw-stat-label"><?php esc_html_e( 'Enviadas', 'person-cash-wallet' ); ?></div>
					</div>
				</div>

				<div class="pcw-stat-card">
					<div class="pcw-stat-icon pcw-stat-failed">
						<span class="dashicons dashicons-dismiss"></span>
					</div>
					<div class="pcw-stat-content">
						<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $total_failed ) ); ?></div>
						<div class="pcw-stat-label"><?php esc_html_e( 'Falhadas', 'person-cash-wallet' ); ?></div>
					</div>
				</div>

				<div class="pcw-stat-card">
					<div class="pcw-stat-icon pcw-stat-pending">
						<span class="dashicons dashicons-clock"></span>
					</div>
					<div class="pcw-stat-content">
						<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $total_pending ) ); ?></div>
						<div class="pcw-stat-label"><?php esc_html_e( 'Pendentes', 'person-cash-wallet' ); ?></div>
					</div>
				</div>

				<div class="pcw-stat-card">
					<div class="pcw-stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
						<span class="dashicons dashicons-chart-line"></span>
					</div>
					<div class="pcw-stat-content">
						<div class="pcw-stat-value"><?php echo esc_html( $success_rate ); ?>%</div>
						<div class="pcw-stat-label"><?php esc_html_e( 'Taxa de Sucesso', 'person-cash-wallet' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Second Row Stats -->
			<div class="pcw-stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 30px;">
				<div class="pcw-stat-card">
					<div class="pcw-stat-icon" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
						<span class="dashicons dashicons-clock"></span>
					</div>
					<div class="pcw-stat-content">
						<div class="pcw-stat-value"><?php echo esc_html( number_format( $avg_per_hour, 1, ',', '.' ) ); ?></div>
						<div class="pcw-stat-label"><?php esc_html_e( 'Média por Hora (7d)', 'person-cash-wallet' ); ?></div>
					</div>
				</div>

				<div class="pcw-stat-card">
					<div class="pcw-stat-icon" style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);">
						<span class="dashicons dashicons-warning"></span>
					</div>
					<div class="pcw-stat-content">
						<div class="pcw-stat-value"><?php echo esc_html( sprintf( '%02d:00', $peak_hour ) ); ?></div>
						<div class="pcw-stat-label"><?php esc_html_e( 'Hora Pico', 'person-cash-wallet' ); ?></div>
					</div>
				</div>

				<div class="pcw-stat-card">
					<div class="pcw-stat-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
						<span class="dashicons dashicons-arrow-up-alt"></span>
					</div>
					<div class="pcw-stat-content">
						<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $peak_count ) ); ?></div>
						<div class="pcw-stat-label"><?php esc_html_e( 'Envios no Pico', 'person-cash-wallet' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Charts -->
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
				<!-- Hourly Chart -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h3>
							<span class="dashicons dashicons-chart-bar"></span>
							<?php esc_html_e( 'Envios por Hora (últimas 24h)', 'person-cash-wallet' ); ?>
						</h3>
					</div>
					<div class="pcw-card-body">
						<canvas id="hourly-chart" style="max-height: 250px;"></canvas>
					</div>
				</div>

				<!-- Rate Limit Info -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h3>
							<span class="dashicons dashicons-info"></span>
							<?php esc_html_e( 'Informações de Rate Limit', 'person-cash-wallet' ); ?>
						</h3>
					</div>
					<div class="pcw-card-body">
						<div style="padding: 20px;">
							<div style="margin-bottom: 20px;">
								<label style="display: block; font-weight: 600; margin-bottom: 8px; color: #475569;">
									<?php esc_html_e( 'Limite Configurado', 'person-cash-wallet' ); ?>
								</label>
								<div style="font-size: 32px; font-weight: bold; color: #25d366;">
									<?php echo esc_html( number_format_i18n( $number->rate_limit_hour ) ); ?>
									<span style="font-size: 18px; color: #64748b; font-weight: normal;">msgs/hora</span>
								</div>
							</div>
							
							<div style="margin-bottom: 20px;">
								<label style="display: block; font-weight: 600; margin-bottom: 8px; color: #475569;">
									<?php esc_html_e( 'Peso na Distribuição', 'person-cash-wallet' ); ?>
								</label>
								<div style="font-size: 32px; font-weight: bold; color: #6366f1;">
									<?php echo esc_html( $number->distribution_weight ); ?>%
								</div>
							</div>
							
							<div>
								<label style="display: block; font-weight: 600; margin-bottom: 8px; color: #475569;">
									<?php esc_html_e( 'Distribuição Automática', 'person-cash-wallet' ); ?>
								</label>
								<?php if ( $number->distribution_enabled ) : ?>
									<span class="pcw-badge pcw-badge-success">
										<span class="dashicons dashicons-yes-alt"></span>
										<?php esc_html_e( 'Habilitada', 'person-cash-wallet' ); ?>
									</span>
								<?php else : ?>
									<span class="pcw-badge pcw-badge-inactive">
										<span class="dashicons dashicons-dismiss"></span>
										<?php esc_html_e( 'Desabilitada', 'person-cash-wallet' ); ?>
									</span>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Daily Chart -->
			<div class="pcw-card" style="margin-bottom: 30px;">
				<div class="pcw-card-header">
					<h3>
						<span class="dashicons dashicons-chart-area"></span>
						<?php esc_html_e( 'Histórico de Envios (últimos 30 dias)', 'person-cash-wallet' ); ?>
					</h3>
				</div>
				<div class="pcw-card-body">
					<canvas id="daily-chart" style="max-height: 300px;"></canvas>
				</div>
			</div>

			<!-- Recent Messages -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h3>
						<span class="dashicons dashicons-email"></span>
						<?php esc_html_e( 'Mensagens Recentes', 'person-cash-wallet' ); ?>
					</h3>
				</div>
				<div class="pcw-card-body">
					<?php if ( empty( $recent_messages ) ) : ?>
						<div class="pcw-empty-state">
							<p><?php esc_html_e( 'Nenhuma mensagem encontrada.', 'person-cash-wallet' ); ?></p>
						</div>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th style="width: 15%;"><?php esc_html_e( 'Destinatário', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Mensagem', 'person-cash-wallet' ); ?></th>
									<th style="width: 12%;"><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></th>
									<th style="width: 15%;"><?php esc_html_e( 'Data', 'person-cash-wallet' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $recent_messages as $msg ) : ?>
									<tr>
										<td style="font-size: 12px;">
											<?php echo esc_html( $msg->to_number ); ?>
										</td>
										<td style="font-size: 12px;">
											<?php echo esc_html( mb_substr( wp_strip_all_tags( $msg->message ), 0, 80 ) . '...' ); ?>
										</td>
										<td>
											<?php if ( 'sent' === $msg->status ) : ?>
												<span class="pcw-badge pcw-badge-success">
													<?php esc_html_e( 'Enviada', 'person-cash-wallet' ); ?>
												</span>
											<?php elseif ( 'failed' === $msg->status ) : ?>
												<span class="pcw-badge pcw-badge-error" title="<?php echo esc_attr( $msg->error_message ?: '' ); ?>">
													<?php esc_html_e( 'Falhou', 'person-cash-wallet' ); ?>
												</span>
											<?php else : ?>
												<span class="pcw-badge pcw-badge-warning">
													<?php esc_html_e( 'Pendente', 'person-cash-wallet' ); ?>
												</span>
											<?php endif; ?>
										</td>
										<td style="font-size: 12px;">
											<?php
											$date = $msg->processed_at ?: $msg->scheduled_at;
											echo $date ? esc_html( mysql2date( 'd/m/Y H:i', $date ) ) : '-';
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
		<script type="text/javascript">
		(function() {
			// Hourly Chart
			var hourlyCtx = document.getElementById('hourly-chart');
			if (hourlyCtx) {
				var hourlyData = <?php echo wp_json_encode( array_values( $hourly ) ); ?>;
				var hourlyLabels = [];
				for (var h = 0; h < 24; h++) {
					hourlyLabels.push(h + 'h');
				}

				new Chart(hourlyCtx, {
					type: 'bar',
					data: {
						labels: hourlyLabels,
						datasets: [{
							label: 'Mensagens Enviadas',
							data: hourlyData,
							backgroundColor: '#22c55e',
							borderColor: '#16a34a',
							borderWidth: 1
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: true,
						plugins: {
							legend: {
								display: false
							}
						},
						scales: {
							y: {
								beginAtZero: true,
								ticks: {
									precision: 0
								}
							}
						}
					}
				});
			}

			// Daily Chart
			var dailyCtx = document.getElementById('daily-chart');
			if (dailyCtx) {
				new Chart(dailyCtx, {
					type: 'line',
					data: {
						labels: <?php echo wp_json_encode( $chart_labels ); ?>,
						datasets: [
							{
								label: 'Enviadas',
								data: <?php echo wp_json_encode( $chart_success ); ?>,
								borderColor: '#22c55e',
								backgroundColor: 'rgba(34, 197, 94, 0.1)',
								tension: 0.3,
								fill: true
							},
							{
								label: 'Falhadas',
								data: <?php echo wp_json_encode( $chart_failed ); ?>,
								borderColor: '#ef4444',
								backgroundColor: 'rgba(239, 68, 68, 0.1)',
								tension: 0.3,
								fill: true
							}
						]
					},
					options: {
						responsive: true,
						maintainAspectRatio: true,
						plugins: {
							legend: {
								position: 'top'
							}
						},
						scales: {
							y: {
								beginAtZero: true,
								ticks: {
									precision: 0
								}
							}
						}
					}
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * Migrar tabela SMTP: adicionar colunas de rate limiting se não existirem
	 */
	private function migrate_smtp_table() {
		global $wpdb;
		$smtp_table = $wpdb->prefix . 'pcw_smtp_accounts';

		// Verificar se as colunas existem
		$columns = $wpdb->get_col( "DESCRIBE {$smtp_table}", 0 );

		$columns_to_add = array(
			'rate_limit_hour'       => "ADD COLUMN rate_limit_hour int(11) DEFAULT 60 AFTER daily_limit",
			'distribution_weight'   => "ADD COLUMN distribution_weight int(11) DEFAULT 100 AFTER rate_limit_hour",
			'distribution_enabled'  => "ADD COLUMN distribution_enabled tinyint(1) DEFAULT 1 AFTER distribution_weight",
			'sent_last_hour'        => "ADD COLUMN sent_last_hour int(11) DEFAULT 0 AFTER distribution_enabled",
			'sent_last_reset'       => "ADD COLUMN sent_last_reset datetime DEFAULT NULL AFTER sent_last_hour",
			'total_sent'            => "ADD COLUMN total_sent int(11) DEFAULT 0 AFTER sent_last_reset",
			'total_failed'          => "ADD COLUMN total_failed int(11) DEFAULT 0 AFTER total_sent",
		);

		foreach ( $columns_to_add as $column_name => $alter_sql ) {
			if ( ! in_array( $column_name, $columns, true ) ) {
				$result = $wpdb->query( "ALTER TABLE {$smtp_table} {$alter_sql}" );
				if ( $result !== false ) {
					$this->pcw_log_smtp_sync( "Added column: {$column_name}" );
				} else {
					$this->pcw_log_smtp_sync( "Failed to add column: {$column_name} - Error: " . $wpdb->last_error );
				}
			}
		}

		// Adicionar índice se não existir
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$smtp_table}" );
		$has_distribution_index = false;
		foreach ( $indexes as $index ) {
			if ( $index->Key_name === 'distribution_enabled' ) {
				$has_distribution_index = true;
				break;
			}
		}

		if ( ! $has_distribution_index ) {
			$wpdb->query( "ALTER TABLE {$smtp_table} ADD KEY distribution_enabled (distribution_enabled)" );
			$this->pcw_log_smtp_sync( 'Added index: distribution_enabled' );
		}
	}
}
