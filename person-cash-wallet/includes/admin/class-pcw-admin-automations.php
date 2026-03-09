<?php
/**
 * Admin de Automações
 *
 * @package GrowlyDigital
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe admin de automações
 */
class PCW_Admin_Automations {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_pcw_save_automation', array( $this, 'ajax_save_automation' ) );
		add_action( 'wp_ajax_pcw_toggle_automation', array( $this, 'ajax_toggle_automation' ) );
		add_action( 'wp_ajax_pcw_delete_automation', array( $this, 'ajax_delete_automation' ) );
		add_action( 'wp_ajax_pcw_generate_ai_subject', array( $this, 'ajax_generate_ai_subject' ) );
		add_action( 'wp_ajax_pcw_generate_ai_content', array( $this, 'ajax_generate_ai_content' ) );
		add_action( 'wp_ajax_pcw_test_webhook', array( $this, 'ajax_test_webhook' ) );
		add_action( 'wp_ajax_pcw_get_personizi_token', array( $this, 'ajax_get_personizi_token' ) );
		add_action( 'wp_ajax_pcw_get_personizi_accounts', array( $this, 'ajax_get_personizi_accounts' ) );
		add_action( 'wp_ajax_pcw_generate_whatsapp_ai', array( $this, 'ajax_generate_whatsapp_ai' ) );
	}

	/**
	 * Adicionar menu
	 */
	public function add_menu() {
		add_submenu_page(
			'pcw-dashboard',
			__( 'Automações', 'person-cash-wallet' ),
			__( 'Automações', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-automations',
			array( $this, 'render_page' ),
			30
		);
	}

	/**
	 * Enqueue scripts
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'pcw-automations' ) === false ) {
			return;
		}

		// Estilos do plugin
		wp_enqueue_style(
			'pcw-admin-automations',
			PCW_PLUGIN_URL . 'assets/css/admin-automations.css',
			array(),
			PCW_VERSION . '-' . time()
		);

		wp_enqueue_style(
			'pcw-email-editor',
			PCW_PLUGIN_URL . 'assets/css/email-editor.css',
			array(),
			PCW_VERSION
		);

		// Scripts do plugin
		wp_enqueue_script(
			'pcw-admin-automations',
			PCW_PLUGIN_URL . 'assets/js/admin-automations.js',
			array( 'jquery' ),
			PCW_VERSION,
			true
		);

		// jQuery UI Dialog (novo sistema de modais)
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_style( 'wp-jquery-ui-dialog' );

		wp_enqueue_script(
			'pcw-email-editor',
			PCW_PLUGIN_URL . 'assets/js/email-editor.js',
			array( 'jquery' ),
			PCW_VERSION,
			true
		);

		// Media uploader
		wp_enqueue_media();

		wp_localize_script( 'pcw-admin-automations', 'pcwAutomations', array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'pcw_automations' ),
			'queueNonce'  => wp_create_nonce( 'pcw_queue' ),
			'settingsUrl' => admin_url( 'admin.php?page=pcw-settings&tab=personizi' ),
			'types'       => PCW_Automations::get_automation_types(),
			'i18n'        => array(
				'confirmDelete' => __( 'Tem certeza que deseja excluir esta automação?', 'person-cash-wallet' ),
				'generating'    => __( 'Gerando com IA...', 'person-cash-wallet' ),
				'aiError'       => __( 'Erro ao gerar com IA', 'person-cash-wallet' ),
			),
		) );

		wp_localize_script( 'pcw-email-editor', 'pcwEmailEditor', array(
			'pluginUrl' => PCW_PLUGIN_URL,
			'siteName'  => get_bloginfo( 'name' ),
		) );
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		?>
		<div class="wrap pcw-automations-page">
			<?php
			if ( 'edit' === $action || 'new' === $action ) {
				$this->render_edit_form( $id );
			} else {
				$this->render_list();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Renderizar lista de automações por tipo
	 */
	private function render_list() {
		global $wpdb;

		$automations = PCW_Automations::instance();
		$all_automations = $automations->get_all();
		$types = PCW_Automations::get_automation_types();

		// Enriquecer stats com dados reais das tabelas de execuções e fila
		$executions_table = $wpdb->prefix . 'pcw_automation_executions';
		$queue_table      = $wpdb->prefix . 'pcw_message_queue';
		$events_table     = $wpdb->prefix . 'pcw_automation_events';

		foreach ( $all_automations as &$auto ) {
			// Disparos = execuções completadas
			$real_sent = absint( $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$executions_table} WHERE automation_id = %d AND status = 'completed'",
				$auto->id
			) ) );

			// WhatsApp enviados via fila
			$queue_sent = absint( $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table} WHERE automation_id = %d AND type IN ('whatsapp', 'whatsapp_template') AND status = 'sent'",
				$auto->id
			) ) );

			// Usar o maior valor entre o contador estático e os dados reais
			$auto->stats_sent = max( $auto->stats_sent, $real_sent, $queue_sent );

			// Abertos e cliques: buscar da tabela de eventos se existir
			if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $events_table ) ) ) {
				$real_opened = absint( $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(DISTINCT user_id) FROM {$events_table} WHERE automation_id = %d AND event_type = 'open'",
					$auto->id
				) ) );
				$real_clicked = absint( $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(DISTINCT user_id) FROM {$events_table} WHERE automation_id = %d AND event_type = 'click'",
					$auto->id
				) ) );
				$real_converted = absint( $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(DISTINCT user_id) FROM {$events_table} WHERE automation_id = %d AND event_type = 'conversion'",
					$auto->id
				) ) );

				$auto->stats_opened    = max( $auto->stats_opened, $real_opened );
				$auto->stats_clicked   = max( $auto->stats_clicked, $real_clicked );
				$auto->stats_converted = max( $auto->stats_converted, $real_converted );
			}
		}
		unset( $auto );

		// Agrupar por tipo
		$by_type = array();
		foreach ( $all_automations as $auto ) {
			$by_type[ $auto->type ][] = $auto;
		}

		?>
		<!-- Page Header -->
		<div class="pcw-page-header">
			<div>
				<h1>
					<span class="dashicons dashicons-controls-repeat"></span>
					<?php esc_html_e( 'Automações', 'person-cash-wallet' ); ?>
				</h1>
				<p class="description"><?php esc_html_e( 'Configure automações para engajar seus clientes automaticamente', 'person-cash-wallet' ); ?></p>
			</div>
		</div>

		<!-- Grid de Tipos de Automação -->
		<div class="pcw-automations-grid">
			<?php foreach ( $types as $type_id => $type ) : 
				$type_automations = isset( $by_type[ $type_id ] ) ? $by_type[ $type_id ] : array();
				$has_automation = ! empty( $type_automations );
				$is_active = $has_automation && $type_automations[0]->status === 'active';
			?>
			<div class="pcw-automation-card" data-type="<?php echo esc_attr( $type_id ); ?>">
				<div class="pcw-automation-card-preview" style="border-bottom-color: <?php echo esc_attr( $type['color'] ); ?>;">
					<span class="dashicons <?php echo esc_attr( $type['icon'] ); ?>" style="color: <?php echo esc_attr( $type['color'] ); ?>;"></span>
				</div>
				
				<?php if ( $has_automation ) : ?>
					<div class="pcw-automation-status <?php echo $is_active ? 'active' : 'inactive'; ?>">
						<span class="dashicons dashicons-controls-<?php echo $is_active ? 'play' : 'pause'; ?>"></span>
						<?php echo $is_active ? esc_html__( 'Ativa', 'person-cash-wallet' ) : esc_html__( 'Inativa', 'person-cash-wallet' ); ?>
					</div>
				<?php endif; ?>

				<h3><?php echo esc_html( $type['name'] ); ?></h3>
				<p><?php echo esc_html( $type['description'] ); ?></p>

				<?php if ( $has_automation ) : ?>
					<div class="pcw-automation-quick-stats">
						<div class="pcw-quick-stat">
							<span class="pcw-stat-icon">📧</span>
							<div class="pcw-stat-value"><?php echo number_format_i18n( $type_automations[0]->stats_sent ); ?></div>
							<div class="pcw-stat-label"><?php esc_html_e( 'Enviados', 'person-cash-wallet' ); ?></div>
						</div>
						<div class="pcw-quick-stat">
							<span class="pcw-stat-icon">👁️</span>
							<div class="pcw-stat-value"><?php echo number_format_i18n( $type_automations[0]->stats_opened ); ?></div>
							<div class="pcw-stat-label"><?php esc_html_e( 'Abertos', 'person-cash-wallet' ); ?></div>
						</div>
						<div class="pcw-quick-stat">
							<span class="pcw-stat-icon">🖱️</span>
							<div class="pcw-stat-value"><?php echo number_format_i18n( $type_automations[0]->stats_clicked ); ?></div>
							<div class="pcw-stat-label"><?php esc_html_e( 'Cliques', 'person-cash-wallet' ); ?></div>
						</div>
						<div class="pcw-quick-stat">
							<span class="pcw-stat-icon">💰</span>
							<div class="pcw-stat-value"><?php echo number_format_i18n( $type_automations[0]->stats_converted ); ?></div>
							<div class="pcw-stat-label"><?php esc_html_e( 'Conversões', 'person-cash-wallet' ); ?></div>
						</div>
					</div>
				<?php endif; ?>

				<div class="pcw-automation-actions">
					<?php if ( $has_automation ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-automation-report&automation_id=' . $type_automations[0]->id ) ); ?>" class="button button-primary pcw-analytics-btn">
							<span class="dashicons dashicons-chart-area"></span>
							<?php esc_html_e( '📊 Analytics', 'person-cash-wallet' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-automations&action=edit&id=' . $type_automations[0]->id ) ); ?>" class="button">
							<span class="dashicons dashicons-edit"></span>
							<?php esc_html_e( 'Configurar', 'person-cash-wallet' ); ?>
						</a>
						<button type="button" class="button pcw-toggle-automation" 
							data-id="<?php echo esc_attr( $type_automations[0]->id ); ?>"
							data-status="<?php echo esc_attr( $type_automations[0]->status ); ?>">
							<?php echo $is_active ? esc_html__( 'Pausar', 'person-cash-wallet' ) : esc_html__( 'Ativar', 'person-cash-wallet' ); ?>
						</button>
					<?php else : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-automations&action=new&type=' . $type_id ) ); ?>" class="button button-primary">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Criar Automação', 'person-cash-wallet' ); ?>
						</a>
					<?php endif; ?>
				</div>

				<?php if ( $has_automation ) : ?>
					<div class="pcw-automation-stats">
						<div class="pcw-stat">
							<span class="pcw-stat-value"><?php echo number_format_i18n( $type_automations[0]->stats_sent ); ?></span>
							<span class="pcw-stat-label"><?php esc_html_e( 'Enviados', 'person-cash-wallet' ); ?></span>
						</div>
						<div class="pcw-stat">
							<span class="pcw-stat-value"><?php echo number_format_i18n( $type_automations[0]->stats_opened ); ?></span>
							<span class="pcw-stat-label"><?php esc_html_e( 'Abertos', 'person-cash-wallet' ); ?></span>
						</div>
						<div class="pcw-stat">
							<span class="pcw-stat-value"><?php echo number_format_i18n( $type_automations[0]->stats_clicked ); ?></span>
							<span class="pcw-stat-label"><?php esc_html_e( 'Cliques', 'person-cash-wallet' ); ?></span>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Renderizar formulário de edição
	 *
	 * @param int $id ID da automação.
	 */
	private function render_edit_form( $id ) {
		$automation = null;
		$type_id = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';
		$is_new = $id === 0;

		if ( ! $is_new ) {
			$automation = PCW_Automations::instance()->get( $id );
			$type_id = $automation->type;
		}

		$types = PCW_Automations::get_automation_types();
		$type = isset( $types[ $type_id ] ) ? $types[ $type_id ] : null;

		if ( ! $type ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Tipo de automação inválido', 'person-cash-wallet' ) . '</p></div>';
			return;
		}

		?>
		<!-- Page Header -->
		<div class="pcw-page-header">
			<div>
				<h1>
					<span class="dashicons dashicons-controls-repeat"></span>
					<?php echo $is_new ? esc_html__( 'Nova Automação', 'person-cash-wallet' ) : esc_html__( 'Editar Automação', 'person-cash-wallet' ); ?>
				</h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-automations' ) ); ?>" class="pcw-back-link">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Voltar para automações', 'person-cash-wallet' ); ?>
				</a>
			</div>
		</div>

		<form id="pcw-automation-form" class="pcw-automation-form">
			<input type="hidden" name="automation_id" value="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="type" value="<?php echo esc_attr( $type_id ); ?>">

			<div class="pcw-form-columns">
				<!-- Coluna Principal -->
				<div class="pcw-form-main">
					<!-- Informações Básicas -->
					<div class="pcw-card" style="border-left: 4px solid <?php echo esc_attr( $type['color'] ); ?>;">
						<div class="pcw-card-header" style="background: #f8fafc; border-bottom: 2px solid <?php echo esc_attr( $type['color'] ); ?>;">
							<h2>
								<span class="dashicons <?php echo esc_attr( $type['icon'] ); ?>" style="color: <?php echo esc_attr( $type['color'] ); ?>; background: <?php echo esc_attr( $type['color'] ); ?>15; padding: 8px; border-radius: 8px;"></span>
								<?php echo esc_html( $type['name'] ); ?>
							</h2>
						</div>
						<div class="pcw-card-body">
							<div class="pcw-form-group">
								<label for="automation_name"><?php esc_html_e( 'Nome da Automação', 'person-cash-wallet' ); ?></label>
								<input type="text" id="automation_name" name="name" 
									value="<?php echo esc_attr( $automation ? $automation->name : $type['name'] ); ?>" required>
							</div>

							<div class="pcw-form-group">
								<label for="automation_description"><?php esc_html_e( 'Descrição', 'person-cash-wallet' ); ?></label>
								<textarea id="automation_description" name="description" rows="2"><?php echo esc_textarea( $automation ? $automation->description : $type['description'] ); ?></textarea>
							</div>
						</div>
					</div>

					<!-- Workflow Steps -->
					<div class="pcw-card">
						<div class="pcw-card-header">
							<h2>
								<span class="dashicons dashicons-randomize"></span>
								<?php esc_html_e( 'Fluxo de Trabalho', 'person-cash-wallet' ); ?>
							</h2>
						</div>
						<div class="pcw-card-body">
							<div class="pcw-workflow-builder" id="workflow-builder">
								<!-- Trigger -->
								<div class="pcw-workflow-step pcw-step-trigger">
									<div class="pcw-step-icon">
										<span class="dashicons dashicons-flag"></span>
									</div>
									<div class="pcw-step-content">
										<h4><?php esc_html_e( 'Gatilho', 'person-cash-wallet' ); ?></h4>
										<p><?php echo esc_html( $this->get_trigger_description( $type['trigger'] ) ); ?></p>
										
										<!-- Configuração do Gatilho -->
										<?php $this->render_trigger_config( $type['trigger'], $automation ); ?>
									</div>
								</div>

								<div class="pcw-workflow-connector"></div>

								<!-- Steps Container -->
								<div id="workflow-steps-container">
									<?php
									$steps = $automation && ! empty( $automation->workflow_steps ) ? $automation->workflow_steps : array(
										array( 'type' => 'delay', 'config' => array( 'value' => 3, 'unit' => 'days' ) ),
										array( 'type' => 'send_email', 'config' => array() ),
									);
									foreach ( $steps as $index => $step ) {
										$this->render_workflow_step( $step, $index );
									}
									?>
								</div>

								<button type="button" class="button" id="add-workflow-step">
									<span class="dashicons dashicons-plus-alt2"></span>
									<?php esc_html_e( 'Adicionar Etapa', 'person-cash-wallet' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Coluna Lateral -->
				<div class="pcw-form-sidebar">
					<!-- Publicar -->
					<div class="pcw-card">
						<div class="pcw-card-header">
							<h2><?php esc_html_e( 'Publicar', 'person-cash-wallet' ); ?></h2>
						</div>
						<div class="pcw-card-body">
							<div class="pcw-form-group">
								<label><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></label>
								<select name="status">
									<option value="active" <?php selected( $automation && $automation->status, 'active' ); ?>><?php esc_html_e( 'Ativa', 'person-cash-wallet' ); ?></option>
									<option value="inactive" <?php selected( ! $automation || $automation->status === 'inactive' ); ?>><?php esc_html_e( 'Inativa', 'person-cash-wallet' ); ?></option>
								</select>
							</div>

							<button type="submit" class="button button-primary button-large" style="width: 100%;">
								<span class="dashicons dashicons-saved"></span>
								<?php esc_html_e( 'Salvar Automação', 'person-cash-wallet' ); ?>
							</button>
						</div>
					</div>

				</div>
			</div>
		</form>
		<?php
	}

	/**
	 * Renderizar etapa do workflow
	 *
	 * @param array $step Dados da etapa.
	 * @param int   $index Índice.
	 */
	private function render_workflow_step( $step, $index ) {
		$type = isset( $step['type'] ) ? $step['type'] : 'delay';
		$config = isset( $step['config'] ) ? $step['config'] : array();

		?>
		<div class="pcw-workflow-step pcw-step-<?php echo esc_attr( $type ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
			<div class="pcw-step-header">
				<div class="pcw-step-icon">
					<?php if ( $type === 'delay' ) : ?>
						<span class="dashicons dashicons-clock"></span>
					<?php elseif ( $type === 'send_email' ) : ?>
						<span class="dashicons dashicons-email"></span>
					<?php elseif ( $type === 'send_sms' ) : ?>
						<span class="dashicons dashicons-smartphone"></span>
					<?php elseif ( $type === 'send_whatsapp' ) : ?>
						<span class="dashicons dashicons-admin-comments"></span>
					<?php elseif ( $type === 'condition' ) : ?>
						<span class="dashicons dashicons-randomize"></span>
					<?php endif; ?>
				</div>
				<div class="pcw-step-summary">
					<?php if ( $type === 'delay' ) : ?>
						<h4><?php esc_html_e( 'Aguardar', 'person-cash-wallet' ); ?></h4>
					<?php elseif ( $type === 'send_email' ) : ?>
						<h4><?php esc_html_e( 'Enviar Email', 'person-cash-wallet' ); ?></h4>
					<?php elseif ( $type === 'send_sms' ) : ?>
						<h4><?php esc_html_e( 'Enviar SMS', 'person-cash-wallet' ); ?></h4>
					<?php elseif ( $type === 'send_whatsapp' ) : ?>
						<h4><?php esc_html_e( 'Enviar WhatsApp', 'person-cash-wallet' ); ?></h4>
					<?php elseif ( $type === 'condition' ) : ?>
						<h4><?php esc_html_e( 'Condição', 'person-cash-wallet' ); ?></h4>
					<?php endif; ?>
					<p class="description">
						<?php
						if ( $type === 'send_email' && ! empty( $config['subject'] ) ) {
							echo esc_html( $config['subject'] );
						} elseif ( $type === 'delay' ) {
							$value = isset( $config['value'] ) ? $config['value'] : 3;
							$unit = isset( $config['unit'] ) ? $config['unit'] : 'days';
							echo esc_html( $value . ' ' . $unit );
						} else {
							echo esc_html__( 'Clique para configurar', 'person-cash-wallet' );
						}
						?>
					</p>
				</div>
				<div class="pcw-step-actions">
					<button type="button" class="button button-small pcw-toggle-step" title="<?php esc_attr_e( 'Expandir/Recolher', 'person-cash-wallet' ); ?>">
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</button>
					<button type="button" class="button button-small pcw-remove-step" title="<?php esc_attr_e( 'Remover', 'person-cash-wallet' ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>
			</div>

			<div class="pcw-step-config" style="display: none;">
				<input type="hidden" name="steps[<?php echo esc_attr( $index ); ?>][type]" value="<?php echo esc_attr( $type ); ?>">
				
				<?php if ( $type === 'delay' ) : ?>
					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Tempo de Espera', 'person-cash-wallet' ); ?></label>
						<div class="pcw-inline-form">
							<input type="number" name="steps[<?php echo esc_attr( $index ); ?>][config][value]" 
								value="<?php echo esc_attr( isset( $config['value'] ) ? $config['value'] : 3 ); ?>" min="1" style="width: 80px;">
							<select name="steps[<?php echo esc_attr( $index ); ?>][config][unit]" style="width: 120px;">
								<option value="minutes" <?php selected( isset( $config['unit'] ) ? $config['unit'] : '', 'minutes' ); ?>><?php esc_html_e( 'minutos', 'person-cash-wallet' ); ?></option>
								<option value="hours" <?php selected( isset( $config['unit'] ) ? $config['unit'] : '', 'hours' ); ?>><?php esc_html_e( 'horas', 'person-cash-wallet' ); ?></option>
								<option value="days" <?php selected( isset( $config['unit'] ) ? $config['unit'] : 'days', 'days' ); ?>><?php esc_html_e( 'dias', 'person-cash-wallet' ); ?></option>
							</select>
						</div>
						<p class="description"><?php esc_html_e( 'Para não sobrecarregar o cliente com muitas mensagens', 'person-cash-wallet' ); ?></p>
					</div>

				<?php elseif ( $type === 'send_email' ) : ?>
					<div class="pcw-form-group">
						<label>
							<?php esc_html_e( 'Assunto do Email', 'person-cash-wallet' ); ?>
							<button type="button" class="button button-small pcw-ai-btn pcw-generate-step-subject" 
								data-step="<?php echo esc_attr( $index ); ?>" 
								title="<?php esc_attr_e( 'Gerar sugestões de assunto com IA', 'person-cash-wallet' ); ?>">
								<span class="dashicons dashicons-admin-site-alt3"></span> IA
							</button>
						</label>
						<input type="text" 
							name="steps[<?php echo esc_attr( $index ); ?>][config][subject]" 
							id="step-email-subject-<?php echo esc_attr( $index ); ?>"
							value="<?php echo esc_attr( isset( $config['subject'] ) ? $config['subject'] : '' ); ?>"
							placeholder="<?php esc_attr_e( 'Ex: Olá {{customer_first_name}}, temos novidades!', 'person-cash-wallet' ); ?>"
							class="widefat">
					</div>

					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Conteúdo do Email', 'person-cash-wallet' ); ?></label>
						<div class="pcw-email-editor-actions">
							<button type="button" class="button button-primary pcw-open-step-email-editor" 
								data-step="<?php echo esc_attr( $index ); ?>">
								<span class="dashicons dashicons-edit-page"></span>
								<?php esc_html_e( 'Editor Visual (Drag & Drop)', 'person-cash-wallet' ); ?>
							</button>
							<button type="button" class="button button-hero pcw-ai-btn-hero pcw-generate-step-complete" 
								data-step="<?php echo esc_attr( $index ); ?>"
								title="<?php esc_attr_e( 'Gerar assunto e conteúdo com IA', 'person-cash-wallet' ); ?>">
								<span class="dashicons dashicons-admin-site-alt3"></span> <?php esc_html_e( 'Gerar Email Completo com IA', 'person-cash-wallet' ); ?>
							</button>
							<button type="button" class="button pcw-ai-btn pcw-generate-step-content" 
								data-step="<?php echo esc_attr( $index ); ?>"
								title="<?php esc_attr_e( 'Gerar apenas conteúdo com IA', 'person-cash-wallet' ); ?>">
								<span class="dashicons dashicons-admin-site-alt3"></span> <?php esc_html_e( 'Gerar Conteúdo', 'person-cash-wallet' ); ?>
							</button>
						</div>
						
						<textarea name="steps[<?php echo esc_attr( $index ); ?>][config][content]" 
							id="step-email-content-<?php echo esc_attr( $index ); ?>"
							class="widefat" 
							rows="6" 
							style="display: none;"><?php echo esc_textarea( isset( $config['content'] ) ? $config['content'] : '' ); ?></textarea>
						
						<?php if ( ! empty( $config['content'] ) ) : ?>
							<div class="pcw-email-preview-mini" id="step-email-preview-<?php echo esc_attr( $index ); ?>">
								<p><strong><?php esc_html_e( 'Preview:', 'person-cash-wallet' ); ?></strong></p>
								<iframe class="pcw-email-preview-frame" srcdoc="<?php echo esc_attr( $config['content'] ); ?>" style="width: 100%; height: 200px; border: 1px solid #ddd; border-radius: 4px;"></iframe>
							</div>
						<?php endif; ?>

						<p class="description" style="margin-top: 15px;">
							<strong><?php esc_html_e( 'Variáveis disponíveis:', 'person-cash-wallet' ); ?></strong><br>
							<code>{{customer_name}}</code>, <code>{{customer_first_name}}</code>, <code>{{customer_email}}</code>,
							<code>{{product_name}}</code>, <code>{{product_image}}</code>, <code>{{product_price}}</code>,
							<code>{{cashback_balance}}</code>, <code>{{user_level}}</code>, <code>{{site_name}}</code>, <code>{{site_url}}</code>
						</p>
					</div>

					<div class="pcw-form-group">
						<label class="pcw-checkbox-inline">
							<input type="checkbox" name="steps[<?php echo esc_attr( $index ); ?>][config][use_ai]" value="1"
								<?php checked( ! empty( $config['use_ai'] ) ); ?>>
							<?php esc_html_e( 'Usar IA para personalizar conteúdo automaticamente em cada envio', 'person-cash-wallet' ); ?>
						</label>
						<p class="description" style="margin-left: 24px;">
							<?php esc_html_e( 'A IA vai adaptar o conteúdo para cada cliente baseado no contexto (nome, produtos, etc)', 'person-cash-wallet' ); ?>
						</p>
					</div>

				<?php elseif ( $type === 'send_sms' ) : ?>
					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Mensagem SMS', 'person-cash-wallet' ); ?></label>
						<textarea name="steps[<?php echo esc_attr( $index ); ?>][config][message]" 
							class="widefat" 
							rows="4" 
							maxlength="160" 
							placeholder="<?php esc_attr_e( 'Ex: Olá {{customer_first_name}}, confira nossas ofertas!', 'person-cash-wallet' ); ?>"><?php echo esc_textarea( isset( $config['message'] ) ? $config['message'] : '' ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Máximo 160 caracteres', 'person-cash-wallet' ); ?> 
							<span class="pcw-char-count">0/160</span>
						</p>
					</div>

					<div class="pcw-form-group">
						<label class="pcw-checkbox-inline">
							<input type="checkbox" name="steps[<?php echo esc_attr( $index ); ?>][config][use_short_url]" value="1"
								<?php checked( ! empty( $config['use_short_url'] ) ); ?>>
							<?php esc_html_e( 'Encurtar URLs automaticamente', 'person-cash-wallet' ); ?>
						</label>
					</div>

				<?php elseif ( $type === 'send_whatsapp' ) : ?>
					<?php
					// Verificar se Personizi está configurado
					$personizi = PCW_Personizi_Integration::instance();
					$personizi_accounts = $personizi->get_whatsapp_accounts();
					$personizi_configured = ! is_wp_error( $personizi_accounts ) && ! empty( $personizi_accounts );
					$use_personizi = isset( $config['use_personizi'] ) ? $config['use_personizi'] : ( $personizi_configured ? '1' : '0' );
					?>

					<?php if ( $personizi_configured ) : ?>
						<div class="pcw-form-group">
							<label class="pcw-checkbox-inline">
								<input type="checkbox" name="steps[<?php echo esc_attr( $index ); ?>][config][use_personizi]" value="1"
									class="pcw-use-personizi" data-step="<?php echo esc_attr( $index ); ?>"
									<?php checked( $use_personizi, '1' ); ?>>
								<?php esc_html_e( 'Enviar via Personizi WhatsApp', 'person-cash-wallet' ); ?>
								<span style="color: #22c55e; font-weight: 600;">✓ Configurado</span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Use sua integração Personizi para enviar mensagens WhatsApp automaticamente.', 'person-cash-wallet' ); ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-settings&tab=personizi' ) ); ?>" target="_blank">
									<?php esc_html_e( 'Ver configurações', 'person-cash-wallet' ); ?>
								</a>
							</p>
						</div>

						<div class="pcw-personizi-config pcw-personizi-config-<?php echo esc_attr( $index ); ?>" style="<?php echo $use_personizi == '1' ? '' : 'display: none;'; ?>">
							<div class="notice notice-info inline" style="margin: 0 0 15px;">
								<p>
									<strong>🔄 Sistema de Fila Automático</strong><br>
									<?php
									$active_count = count( array_filter( $personizi_accounts, function( $acc ) {
										return $acc['status'] === 'active';
									} ) );
									printf(
										esc_html__( 'Os envios serão distribuídos automaticamente entre %d conta(s) WhatsApp ativa(s) usando Round-Robin.', 'person-cash-wallet' ),
										$active_count
									);
									?>
								</p>
								<p style="margin: 8px 0 0; font-size: 13px;">
									<strong><?php esc_html_e( 'Contas configuradas:', 'person-cash-wallet' ); ?></strong>
									<?php
									$active_accounts = array_filter( $personizi_accounts, function( $acc ) {
										return $acc['status'] === 'active';
									} );
									foreach ( $active_accounts as $account ) :
										echo '<br>• ' . esc_html( $account['name'] . ' (' . $account['phone_number'] . ')' );
									endforeach;
									?>
								</p>
								<p style="margin: 8px 0 0; font-size: 12px; color: #666;">
									<span class="dashicons dashicons-admin-settings" style="font-size: 14px; vertical-align: middle;"></span>
									<?php esc_html_e( 'Para configurar números, rate limiting e estratégia:', 'person-cash-wallet' ); ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-queue&tab=numbers' ) ); ?>" target="_blank">
										<?php esc_html_e( 'Gerenciar Filas', 'person-cash-wallet' ); ?>
									</a>
								</p>
							</div>

							<div class="pcw-form-group">
								<label>
									<input type="checkbox" name="steps[<?php echo esc_attr( $index ); ?>][config][use_specific_number]" 
										value="1" 
										class="pcw-use-specific-number" 
										data-step="<?php echo esc_attr( $index ); ?>"
										<?php checked( ! empty( $config['personizi_from'] ) ); ?>>
									<?php esc_html_e( 'Forçar número específico (ignorar fila)', 'person-cash-wallet' ); ?>
								</label>
								<p class="description" style="margin-left: 24px;">
									<?php esc_html_e( 'Use apenas se precisar que ESTA automação sempre use um número fixo.', 'person-cash-wallet' ); ?>
								</p>
							</div>

							<div class="pcw-personizi-specific-number pcw-personizi-specific-<?php echo esc_attr( $index ); ?>" 
								style="<?php echo ! empty( $config['personizi_from'] ) ? '' : 'display: none;'; ?>">
								<div class="pcw-form-group">
									<label><?php esc_html_e( 'Número Fixo', 'person-cash-wallet' ); ?></label>
									<select name="steps[<?php echo esc_attr( $index ); ?>][config][personizi_from]" class="widefat">
										<option value=""><?php esc_html_e( 'Selecione um número', 'person-cash-wallet' ); ?></option>
										<?php
										foreach ( $personizi_accounts as $account ) :
											$selected = isset( $config['personizi_from'] ) && $config['personizi_from'] === $account['phone_number'];
											?>
											<option value="<?php echo esc_attr( $account['phone_number'] ); ?>" <?php selected( $selected ); ?>>
												<?php echo esc_html( $account['name'] . ' (' . $account['phone_number'] . ')' ); ?>
												<?php if ( $account['status'] !== 'active' ) : ?>
													[<?php echo esc_html( strtoupper( $account['status'] ) ); ?>]
												<?php endif; ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description">
										<?php esc_html_e( 'Este número será usado sempre, ignorando o sistema de fila.', 'person-cash-wallet' ); ?>
									</p>
								</div>
							</div>
						</div>
					<?php else : ?>
						<div class="notice notice-warning inline" style="margin: 0 0 15px;">
							<p>
								<span class="dashicons dashicons-warning"></span>
								<strong><?php esc_html_e( 'Personizi não configurado.', 'person-cash-wallet' ); ?></strong>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-settings&tab=personizi' ) ); ?>" target="_blank">
									<?php esc_html_e( 'Configurar agora', 'person-cash-wallet' ); ?>
								</a>
							</p>
						</div>
					<?php endif; ?>

			<?php
			// Template option (only shown when Personizi is configured and there are official API accounts)
			$has_official_accounts = false;
			if ( $personizi_configured ) {
				foreach ( $personizi_accounts as $pa ) {
					if ( PCW_Personizi_Integration::is_official_api( $pa['provider'] ?? '' ) ) {
						$has_official_accounts = true;
						break;
					}
				}
			}
			$use_template_val = isset( $config['use_template'] ) ? $config['use_template'] : '0';
			?>

			<?php if ( $has_official_accounts ) : ?>
			<div class="pcw-form-group" style="margin-bottom: 15px;">
				<div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 14px;">
					<label class="pcw-checkbox-inline" style="align-items: flex-start; gap: 8px;">
						<input type="checkbox"
							name="steps[<?php echo esc_attr( $index ); ?>][config][use_template]"
							value="1"
							class="pcw-use-template-toggle"
							data-step="<?php echo esc_attr( $index ); ?>"
							style="margin-top: 2px;"
							<?php checked( $use_template_val, '1' ); ?>>
						<div>
							<strong><?php esc_html_e( 'Usar Template Aprovado (API Oficial)', 'person-cash-wallet' ); ?></strong><br>
							<span style="font-size: 12px; color: #1e40af;">
								<?php esc_html_e( 'Enviar um template pré-aprovado pelo Meta. Necessário para iniciar conversas em contas de API Oficial.', 'person-cash-wallet' ); ?>
							</span>
						</div>
					</label>
				</div>
			</div>

			<div class="pcw-template-config pcw-template-config-<?php echo esc_attr( $index ); ?>" style="<?php echo $use_template_val == '1' ? '' : 'display: none;'; ?>">
				<div class="pcw-form-group">
					<label><?php esc_html_e( 'Conta (API Oficial)', 'person-cash-wallet' ); ?></label>
					<select name="steps[<?php echo esc_attr( $index ); ?>][config][template_from]" 
						class="widefat pcw-template-account" data-step="<?php echo esc_attr( $index ); ?>">
						<option value=""><?php esc_html_e( 'Selecione a conta', 'person-cash-wallet' ); ?></option>
						<?php foreach ( $personizi_accounts as $pa ) :
							if ( ! PCW_Personizi_Integration::is_official_api( $pa['provider'] ?? '' ) ) continue;
							$sel = isset( $config['template_from'] ) && $config['template_from'] === $pa['phone_number'];
						?>
						<option value="<?php echo esc_attr( $pa['phone_number'] ); ?>" <?php selected( $sel ); ?>>
							<?php echo esc_html( $pa['name'] . ' (' . $pa['phone_number'] . ')' ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="pcw-form-group">
					<label><?php esc_html_e( 'Template', 'person-cash-wallet' ); ?></label>
					<select name="steps[<?php echo esc_attr( $index ); ?>][config][template_name]" 
						class="widefat pcw-template-select" data-step="<?php echo esc_attr( $index ); ?>">
						<option value=""><?php esc_html_e( 'Selecione o template', 'person-cash-wallet' ); ?></option>
						<?php if ( ! empty( $config['template_name'] ) ) : ?>
						<option value="<?php echo esc_attr( $config['template_name'] ); ?>" selected>
							<?php echo esc_html( $config['template_name'] ); ?>
						</option>
						<?php endif; ?>
					</select>
				</div>

				<input type="hidden" name="steps[<?php echo esc_attr( $index ); ?>][config][template_language]" 
					class="pcw-template-language" data-step="<?php echo esc_attr( $index ); ?>"
					value="<?php echo esc_attr( isset( $config['template_language'] ) ? $config['template_language'] : 'pt_BR' ); ?>">
				<input type="hidden" name="steps[<?php echo esc_attr( $index ); ?>][config][template_body_text]" 
					class="pcw-template-body-text" data-step="<?php echo esc_attr( $index ); ?>"
					value="<?php echo esc_attr( isset( $config['template_body_text'] ) ? $config['template_body_text'] : '' ); ?>">

				<div class="pcw-template-params-container pcw-template-params-<?php echo esc_attr( $index ); ?>" style="display: none;">
					<label><?php esc_html_e( 'Variáveis do Template', 'person-cash-wallet' ); ?></label>
					<div class="pcw-template-params-fields"></div>
					<p class="description" style="font-size: 11px;">
						<?php esc_html_e( 'Variáveis de automação disponíveis: {{customer_first_name}}, {{customer_name}}, {{order_number}}, {{order_total}}', 'person-cash-wallet' ); ?>
					</p>
				</div>

				<div class="pcw-template-preview pcw-template-preview-<?php echo esc_attr( $index ); ?>" style="display: none; margin-top: 10px;">
					<label><?php esc_html_e( 'Preview', 'person-cash-wallet' ); ?></label>
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; font-size: 13px; color: #475569; white-space: pre-wrap;"></div>
				</div>
			</div>
			<?php endif; ?>

			<div class="pcw-form-group pcw-message-group pcw-message-group-<?php echo esc_attr( $index ); ?>" style="<?php echo $use_template_val == '1' ? 'display: none;' : ''; ?>">
				<label>
					<?php esc_html_e( 'Mensagem WhatsApp', 'person-cash-wallet' ); ?>
					<button type="button" class="button button-small pcw-ai-btn pcw-generate-whatsapp-message" 
						data-step="<?php echo esc_attr( $index ); ?>" 
						title="<?php esc_attr_e( 'Gerar mensagem base com IA', 'person-cash-wallet' ); ?>">
						<span class="dashicons dashicons-admin-site-alt3"></span> <?php esc_html_e( 'Gerar com IA', 'person-cash-wallet' ); ?>
					</button>
				</label>

				<?php $ai_unique = isset( $config['ai_unique_message'] ) ? $config['ai_unique_message'] : '0'; ?>
				<div style="background: #fefce8; border: 1px solid #fde68a; border-radius: 6px; padding: 10px 12px; margin-bottom: 10px;">
					<label class="pcw-checkbox-inline" style="align-items: flex-start; gap: 8px;">
						<input type="checkbox"
							name="steps[<?php echo esc_attr( $index ); ?>][config][ai_unique_message]"
							value="1"
							class="pcw-ai-unique-toggle"
							data-step="<?php echo esc_attr( $index ); ?>"
							style="margin-top: 2px;"
							<?php checked( $ai_unique, '1' ); ?>>
						<div>
							<strong>✨ <?php esc_html_e( 'Mensagem única por IA para cada envio', 'person-cash-wallet' ); ?></strong><br>
							<span style="font-size: 12px; color: #92400e;">
								<?php esc_html_e( 'A IA gera uma nova variação da mensagem para cada destinatário — reduz risco de bloqueio por conteúdo repetitivo. A mensagem abaixo é usada como contexto/base.', 'person-cash-wallet' ); ?>
							</span>
						</div>
					</label>
				</div>

				<textarea name="steps[<?php echo esc_attr( $index ); ?>][config][message]" 
					id="step-whatsapp-message-<?php echo esc_attr( $index ); ?>"
					class="widefat" 
					rows="6" 
					placeholder="<?php esc_attr_e( 'Ex: Olá {{customer_first_name}}! 🎉', 'person-cash-wallet' ); ?>"><?php echo esc_textarea( isset( $config['message'] ) ? $config['message'] : '' ); ?></textarea>
				<p class="description">
					<?php esc_html_e( 'Emojis são bem-vindos! 😊 Variáveis disponíveis: {{customer_first_name}}, {{customer_name}}, {{order_number}}, {{order_total}}', 'person-cash-wallet' ); ?>
					<?php if ( $ai_unique === '1' ) : ?>
						<br><span style="color: #92400e;">⚡ <?php esc_html_e( 'A IA gerará uma variação única antes de cada envio real.', 'person-cash-wallet' ); ?></span>
					<?php endif; ?>
				</p>
			</div>

				<div class="pcw-form-group pcw-whatsapp-media" style="<?php echo $use_personizi == '1' ? 'display: none;' : ''; ?>">
					<label><?php esc_html_e( 'Imagem/Arquivo (opcional)', 'person-cash-wallet' ); ?></label>
					<div class="pcw-media-uploader">
						<input type="text" name="steps[<?php echo esc_attr( $index ); ?>][config][media_url]" 
							class="widefat pcw-media-input" 
							value="<?php echo esc_attr( isset( $config['media_url'] ) ? $config['media_url'] : '' ); ?>" 
							placeholder="URL da imagem ou arquivo">
						<button type="button" class="button pcw-select-media">
							<span class="dashicons dashicons-admin-media"></span>
							<?php esc_html_e( 'Selecionar', 'person-cash-wallet' ); ?>
						</button>
					</div>
					<p class="description"><?php esc_html_e( 'Disponível apenas para outras integrações WhatsApp', 'person-cash-wallet' ); ?></p>
				</div>

				<div class="pcw-form-group">
					<label><?php esc_html_e( 'Botões de Ação (opcional)', 'person-cash-wallet' ); ?></label>
					<input type="text" name="steps[<?php echo esc_attr( $index ); ?>][config][button_text]" 
						class="widefat" 
						value="<?php echo esc_attr( isset( $config['button_text'] ) ? $config['button_text'] : '' ); ?>" 
						placeholder="<?php esc_attr_e( 'Ex: Ver Produtos', 'person-cash-wallet' ); ?>"
						style="margin-bottom: 8px;">
					<input type="url" name="steps[<?php echo esc_attr( $index ); ?>][config][button_url]" 
						class="widefat" 
						value="<?php echo esc_attr( isset( $config['button_url'] ) ? $config['button_url'] : '' ); ?>" 
						placeholder="<?php esc_attr_e( 'URL do botão', 'person-cash-wallet' ); ?>">
					<p class="description"><?php esc_html_e( 'Adicione um botão de ação à mensagem (ex: link para produtos, pedidos, cashback)', 'person-cash-wallet' ); ?></p>
				</div>

				<?php elseif ( $type === 'condition' ) : ?>
					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Tipo de Condição', 'person-cash-wallet' ); ?></label>
						<select name="steps[<?php echo esc_attr( $index ); ?>][config][condition_type]" class="widefat pcw-condition-type" data-step="<?php echo esc_attr( $index ); ?>">
							<option value="opened_email" <?php selected( isset( $config['condition_type'] ) ? $config['condition_type'] : '', 'opened_email' ); ?>><?php esc_html_e( 'Abriu o email anterior', 'person-cash-wallet' ); ?></option>
							<option value="clicked_email" <?php selected( isset( $config['condition_type'] ) ? $config['condition_type'] : '', 'clicked_email' ); ?>><?php esc_html_e( 'Clicou no email anterior', 'person-cash-wallet' ); ?></option>
							<option value="made_purchase" <?php selected( isset( $config['condition_type'] ) ? $config['condition_type'] : '', 'made_purchase' ); ?>><?php esc_html_e( 'Cliente fez uma compra', 'person-cash-wallet' ); ?></option>
							<option value="not_purchased" <?php selected( isset( $config['condition_type'] ) ? $config['condition_type'] : '', 'not_purchased' ); ?>><?php esc_html_e( 'Cliente NÃO fez compra', 'person-cash-wallet' ); ?></option>
							<option value="cart_value" <?php selected( isset( $config['condition_type'] ) ? $config['condition_type'] : '', 'cart_value' ); ?>><?php esc_html_e( 'Valor do carrinho', 'person-cash-wallet' ); ?></option>
							<option value="order_count" <?php selected( isset( $config['condition_type'] ) ? $config['condition_type'] : '', 'order_count' ); ?>><?php esc_html_e( 'Quantidade de pedidos', 'person-cash-wallet' ); ?></option>
							<option value="cashback_balance" <?php selected( isset( $config['condition_type'] ) ? $config['condition_type'] : '', 'cashback_balance' ); ?>><?php esc_html_e( 'Saldo de cashback', 'person-cash-wallet' ); ?></option>
							<option value="user_level" <?php selected( isset( $config['condition_type'] ) ? $config['condition_type'] : '', 'user_level' ); ?>><?php esc_html_e( 'Nível VIP do cliente', 'person-cash-wallet' ); ?></option>
							<option value="product_category" <?php selected( isset( $config['condition_type'] ) ? $config['condition_type'] : '', 'product_category' ); ?>><?php esc_html_e( 'Comprou de categoria específica', 'person-cash-wallet' ); ?></option>
						</select>
					</div>

					<?php
					$condition_type = isset( $config['condition_type'] ) ? $config['condition_type'] : 'opened_email';
					?>

					<!-- Opened Email -->
					<div class="pcw-condition-config pcw-condition-opened_email" style="<?php echo $condition_type === 'opened_email' ? '' : 'display: none;'; ?>">
						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Período de Verificação', 'person-cash-wallet' ); ?></label>
							<div class="pcw-inline-form">
								<span><?php esc_html_e( 'Nos últimos', 'person-cash-wallet' ); ?></span>
								<input type="number" name="steps[<?php echo esc_attr( $index ); ?>][config][email_period_value]" 
									value="<?php echo esc_attr( isset( $config['email_period_value'] ) ? $config['email_period_value'] : 24 ); ?>" 
									min="1" style="width: 80px;">
								<select name="steps[<?php echo esc_attr( $index ); ?>][config][email_period_unit]" style="width: 100px;">
									<option value="hours" <?php selected( isset( $config['email_period_unit'] ) ? $config['email_period_unit'] : 'hours', 'hours' ); ?>><?php esc_html_e( 'horas', 'person-cash-wallet' ); ?></option>
									<option value="days" <?php selected( isset( $config['email_period_unit'] ) ? $config['email_period_unit'] : '', 'days' ); ?>><?php esc_html_e( 'dias', 'person-cash-wallet' ); ?></option>
								</select>
							</div>
							<p class="description"><?php esc_html_e( 'Verifica se o cliente abriu o email enviado na etapa anterior dentro deste período', 'person-cash-wallet' ); ?></p>
						</div>
					</div>

					<!-- Clicked Email -->
					<div class="pcw-condition-config pcw-condition-clicked_email" style="<?php echo $condition_type === 'clicked_email' ? '' : 'display: none;'; ?>">
						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Período de Verificação', 'person-cash-wallet' ); ?></label>
							<div class="pcw-inline-form">
								<span><?php esc_html_e( 'Nos últimos', 'person-cash-wallet' ); ?></span>
								<input type="number" name="steps[<?php echo esc_attr( $index ); ?>][config][click_period_value]" 
									value="<?php echo esc_attr( isset( $config['click_period_value'] ) ? $config['click_period_value'] : 24 ); ?>" 
									min="1" style="width: 80px;">
								<select name="steps[<?php echo esc_attr( $index ); ?>][config][click_period_unit]" style="width: 100px;">
									<option value="hours" <?php selected( isset( $config['click_period_unit'] ) ? $config['click_period_unit'] : 'hours', 'hours' ); ?>><?php esc_html_e( 'horas', 'person-cash-wallet' ); ?></option>
									<option value="days" <?php selected( isset( $config['click_period_unit'] ) ? $config['click_period_unit'] : '', 'days' ); ?>><?php esc_html_e( 'dias', 'person-cash-wallet' ); ?></option>
								</select>
							</div>
							<p class="description"><?php esc_html_e( 'Verifica se o cliente clicou em algum link do email anterior', 'person-cash-wallet' ); ?></p>
						</div>
					</div>

					<!-- Made Purchase -->
					<div class="pcw-condition-config pcw-condition-made_purchase" style="<?php echo $condition_type === 'made_purchase' ? '' : 'display: none;'; ?>">
						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Período de Verificação', 'person-cash-wallet' ); ?></label>
							<div class="pcw-inline-form">
								<span><?php esc_html_e( 'Nos últimos', 'person-cash-wallet' ); ?></span>
								<input type="number" name="steps[<?php echo esc_attr( $index ); ?>][config][purchase_period_value]" 
									value="<?php echo esc_attr( isset( $config['purchase_period_value'] ) ? $config['purchase_period_value'] : 7 ); ?>" 
									min="1" style="width: 80px;">
								<select name="steps[<?php echo esc_attr( $index ); ?>][config][purchase_period_unit]" style="width: 100px;">
									<option value="hours" <?php selected( isset( $config['purchase_period_unit'] ) ? $config['purchase_period_unit'] : '', 'hours' ); ?>><?php esc_html_e( 'horas', 'person-cash-wallet' ); ?></option>
									<option value="days" <?php selected( isset( $config['purchase_period_unit'] ) ? $config['purchase_period_unit'] : 'days', 'days' ); ?>><?php esc_html_e( 'dias', 'person-cash-wallet' ); ?></option>
								</select>
							</div>
						</div>

						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Status de Pedido a Considerar', 'person-cash-wallet' ); ?></label>
							<select name="steps[<?php echo esc_attr( $index ); ?>][config][order_status][]" class="widefat" multiple size="5">
								<?php
								$wc_statuses = wc_get_order_statuses();
								$selected_statuses = isset( $config['order_status'] ) ? (array) $config['order_status'] : array( 'wc-completed', 'wc-processing' );
								foreach ( $wc_statuses as $status_key => $status_label ) :
								?>
									<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( in_array( $status_key, $selected_statuses, true ) ); ?>>
										<?php echo esc_html( $status_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Segure Ctrl (Cmd no Mac) para selecionar múltiplos status', 'person-cash-wallet' ); ?></p>
						</div>

						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Valor Mínimo (opcional)', 'person-cash-wallet' ); ?></label>
							<input type="number" name="steps[<?php echo esc_attr( $index ); ?>][config][min_order_value]" 
								value="<?php echo esc_attr( isset( $config['min_order_value'] ) ? $config['min_order_value'] : '' ); ?>" 
								min="0" step="0.01" placeholder="0.00" style="width: 150px;">
							<p class="description"><?php esc_html_e( 'Deixe vazio para considerar qualquer valor', 'person-cash-wallet' ); ?></p>
						</div>
					</div>

					<!-- Not Purchased -->
					<div class="pcw-condition-config pcw-condition-not_purchased" style="<?php echo $condition_type === 'not_purchased' ? '' : 'display: none;'; ?>">
						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Período de Verificação', 'person-cash-wallet' ); ?></label>
							<div class="pcw-inline-form">
								<span><?php esc_html_e( 'Nos últimos', 'person-cash-wallet' ); ?></span>
								<input type="number" name="steps[<?php echo esc_attr( $index ); ?>][config][no_purchase_period_value]" 
									value="<?php echo esc_attr( isset( $config['no_purchase_period_value'] ) ? $config['no_purchase_period_value'] : 30 ); ?>" 
									min="1" style="width: 80px;">
								<select name="steps[<?php echo esc_attr( $index ); ?>][config][no_purchase_period_unit]" style="width: 100px;">
									<option value="days" <?php selected( isset( $config['no_purchase_period_unit'] ) ? $config['no_purchase_period_unit'] : 'days', 'days' ); ?>><?php esc_html_e( 'dias', 'person-cash-wallet' ); ?></option>
									<option value="months" <?php selected( isset( $config['no_purchase_period_unit'] ) ? $config['no_purchase_period_unit'] : '', 'months' ); ?>><?php esc_html_e( 'meses', 'person-cash-wallet' ); ?></option>
								</select>
							</div>
							<p class="description"><?php esc_html_e( 'Verifica se o cliente NÃO fez nenhuma compra neste período', 'person-cash-wallet' ); ?></p>
						</div>
					</div>

					<!-- Cart Value -->
					<div class="pcw-condition-config pcw-condition-cart_value" style="<?php echo $condition_type === 'cart_value' ? '' : 'display: none;'; ?>">
						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Operador', 'person-cash-wallet' ); ?></label>
							<select name="steps[<?php echo esc_attr( $index ); ?>][config][cart_operator]" style="width: 150px;">
								<option value="greater_than" <?php selected( isset( $config['cart_operator'] ) ? $config['cart_operator'] : 'greater_than', 'greater_than' ); ?>><?php esc_html_e( 'Maior que', 'person-cash-wallet' ); ?></option>
								<option value="less_than" <?php selected( isset( $config['cart_operator'] ) ? $config['cart_operator'] : '', 'less_than' ); ?>><?php esc_html_e( 'Menor que', 'person-cash-wallet' ); ?></option>
								<option value="equals" <?php selected( isset( $config['cart_operator'] ) ? $config['cart_operator'] : '', 'equals' ); ?>><?php esc_html_e( 'Igual a', 'person-cash-wallet' ); ?></option>
								<option value="between" <?php selected( isset( $config['cart_operator'] ) ? $config['cart_operator'] : '', 'between' ); ?>><?php esc_html_e( 'Entre', 'person-cash-wallet' ); ?></option>
							</select>
						</div>

						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Valor (R$)', 'person-cash-wallet' ); ?></label>
							<input type="number" name="steps[<?php echo esc_attr( $index ); ?>][config][cart_value]" 
								value="<?php echo esc_attr( isset( $config['cart_value'] ) ? $config['cart_value'] : '' ); ?>" 
								min="0" step="0.01" placeholder="100.00" style="width: 150px;">
						</div>

						<div class="pcw-form-group pcw-cart-value-max" style="display: none;">
							<label><?php esc_html_e( 'Valor Máximo (R$)', 'person-cash-wallet' ); ?></label>
							<input type="number" name="steps[<?php echo esc_attr( $index ); ?>][config][cart_value_max]" 
								value="<?php echo esc_attr( isset( $config['cart_value_max'] ) ? $config['cart_value_max'] : '' ); ?>" 
								min="0" step="0.01" placeholder="500.00" style="width: 150px;">
						</div>
					</div>

					<!-- Order Count -->
					<div class="pcw-condition-config pcw-condition-order_count" style="<?php echo $condition_type === 'order_count' ? '' : 'display: none;'; ?>">
						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Operador', 'person-cash-wallet' ); ?></label>
							<select name="steps[<?php echo esc_attr( $index ); ?>][config][order_count_operator]" style="width: 150px;">
								<option value="greater_than" <?php selected( isset( $config['order_count_operator'] ) ? $config['order_count_operator'] : 'greater_than', 'greater_than' ); ?>><?php esc_html_e( 'Maior que', 'person-cash-wallet' ); ?></option>
								<option value="less_than" <?php selected( isset( $config['order_count_operator'] ) ? $config['order_count_operator'] : '', 'less_than' ); ?>><?php esc_html_e( 'Menor que', 'person-cash-wallet' ); ?></option>
								<option value="equals" <?php selected( isset( $config['order_count_operator'] ) ? $config['order_count_operator'] : '', 'equals' ); ?>><?php esc_html_e( 'Igual a', 'person-cash-wallet' ); ?></option>
							</select>
						</div>

						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Quantidade de Pedidos', 'person-cash-wallet' ); ?></label>
							<input type="number" name="steps[<?php echo esc_attr( $index ); ?>][config][order_count_value]" 
								value="<?php echo esc_attr( isset( $config['order_count_value'] ) ? $config['order_count_value'] : 1 ); ?>" 
								min="0" style="width: 100px;">
						</div>

						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Período de Verificação (opcional)', 'person-cash-wallet' ); ?></label>
							<div class="pcw-inline-form">
								<span><?php esc_html_e( 'Nos últimos', 'person-cash-wallet' ); ?></span>
								<input type="number" name="steps[<?php echo esc_attr( $index ); ?>][config][order_count_period_value]" 
									value="<?php echo esc_attr( isset( $config['order_count_period_value'] ) ? $config['order_count_period_value'] : '' ); ?>" 
									min="1" placeholder="30" style="width: 80px;">
								<select name="steps[<?php echo esc_attr( $index ); ?>][config][order_count_period_unit]" style="width: 100px;">
									<option value="days" <?php selected( isset( $config['order_count_period_unit'] ) ? $config['order_count_period_unit'] : 'days', 'days' ); ?>><?php esc_html_e( 'dias', 'person-cash-wallet' ); ?></option>
									<option value="months" <?php selected( isset( $config['order_count_period_unit'] ) ? $config['order_count_period_unit'] : '', 'months' ); ?>><?php esc_html_e( 'meses', 'person-cash-wallet' ); ?></option>
								</select>
							</div>
							<p class="description"><?php esc_html_e( 'Deixe vazio para contar todos os pedidos (desde sempre)', 'person-cash-wallet' ); ?></p>
						</div>
					</div>

					<!-- Cashback Balance -->
					<div class="pcw-condition-config pcw-condition-cashback_balance" style="<?php echo $condition_type === 'cashback_balance' ? '' : 'display: none;'; ?>">
						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Operador', 'person-cash-wallet' ); ?></label>
							<select name="steps[<?php echo esc_attr( $index ); ?>][config][cashback_operator]" style="width: 150px;">
								<option value="greater_than" <?php selected( isset( $config['cashback_operator'] ) ? $config['cashback_operator'] : 'greater_than', 'greater_than' ); ?>><?php esc_html_e( 'Maior que', 'person-cash-wallet' ); ?></option>
								<option value="less_than" <?php selected( isset( $config['cashback_operator'] ) ? $config['cashback_operator'] : '', 'less_than' ); ?>><?php esc_html_e( 'Menor que', 'person-cash-wallet' ); ?></option>
								<option value="equals" <?php selected( isset( $config['cashback_operator'] ) ? $config['cashback_operator'] : '', 'equals' ); ?>><?php esc_html_e( 'Igual a', 'person-cash-wallet' ); ?></option>
							</select>
						</div>

						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Valor (R$)', 'person-cash-wallet' ); ?></label>
							<input type="number" name="steps[<?php echo esc_attr( $index ); ?>][config][cashback_value]" 
								value="<?php echo esc_attr( isset( $config['cashback_value'] ) ? $config['cashback_value'] : 0 ); ?>" 
								min="0" step="0.01" placeholder="50.00" style="width: 150px;">
						</div>
					</div>

					<!-- User Level -->
					<div class="pcw-condition-config pcw-condition-user_level" style="<?php echo $condition_type === 'user_level' ? '' : 'display: none;'; ?>">
						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Nível VIP', 'person-cash-wallet' ); ?></label>
							<select name="steps[<?php echo esc_attr( $index ); ?>][config][user_level_id]" class="widefat">
								<option value=""><?php esc_html_e( 'Selecione um nível', 'person-cash-wallet' ); ?></option>
								<?php
								// Buscar níveis VIP
								global $wpdb;
								$levels = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}pcw_levels ORDER BY min_points ASC" );
								$selected_level = isset( $config['user_level_id'] ) ? $config['user_level_id'] : '';
								foreach ( $levels as $level ) :
								?>
									<option value="<?php echo esc_attr( $level->id ); ?>" <?php selected( $selected_level, $level->id ); ?>>
										<?php echo esc_html( $level->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Operador', 'person-cash-wallet' ); ?></label>
							<select name="steps[<?php echo esc_attr( $index ); ?>][config][level_operator]" style="width: 200px;">
								<option value="equals" <?php selected( isset( $config['level_operator'] ) ? $config['level_operator'] : 'equals', 'equals' ); ?>><?php esc_html_e( 'É exatamente', 'person-cash-wallet' ); ?></option>
								<option value="greater_than" <?php selected( isset( $config['level_operator'] ) ? $config['level_operator'] : '', 'greater_than' ); ?>><?php esc_html_e( 'É maior ou igual', 'person-cash-wallet' ); ?></option>
								<option value="less_than" <?php selected( isset( $config['level_operator'] ) ? $config['level_operator'] : '', 'less_than' ); ?>><?php esc_html_e( 'É menor ou igual', 'person-cash-wallet' ); ?></option>
							</select>
						</div>
					</div>

					<!-- Product Category -->
					<div class="pcw-condition-config pcw-condition-product_category" style="<?php echo $condition_type === 'product_category' ? '' : 'display: none;'; ?>">
						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Categorias de Produto', 'person-cash-wallet' ); ?></label>
							<select name="steps[<?php echo esc_attr( $index ); ?>][config][product_categories][]" class="widefat" multiple size="8">
								<?php
								$categories = get_terms( array(
									'taxonomy'   => 'product_cat',
									'hide_empty' => false,
								) );
								$selected_cats = isset( $config['product_categories'] ) ? (array) $config['product_categories'] : array();
								foreach ( $categories as $category ) :
								?>
									<option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( in_array( $category->term_id, $selected_cats, true ) ); ?>>
										<?php echo esc_html( $category->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Segure Ctrl (Cmd no Mac) para selecionar múltiplas categorias', 'person-cash-wallet' ); ?></p>
						</div>

						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Período de Verificação', 'person-cash-wallet' ); ?></label>
							<div class="pcw-inline-form">
								<span><?php esc_html_e( 'Nos últimos', 'person-cash-wallet' ); ?></span>
								<input type="number" name="steps[<?php echo esc_attr( $index ); ?>][config][category_period_value]" 
									value="<?php echo esc_attr( isset( $config['category_period_value'] ) ? $config['category_period_value'] : 30 ); ?>" 
									min="1" style="width: 80px;">
								<select name="steps[<?php echo esc_attr( $index ); ?>][config][category_period_unit]" style="width: 100px;">
									<option value="days" <?php selected( isset( $config['category_period_unit'] ) ? $config['category_period_unit'] : 'days', 'days' ); ?>><?php esc_html_e( 'dias', 'person-cash-wallet' ); ?></option>
									<option value="months" <?php selected( isset( $config['category_period_unit'] ) ? $config['category_period_unit'] : '', 'months' ); ?>><?php esc_html_e( 'meses', 'person-cash-wallet' ); ?></option>
								</select>
							</div>
						</div>
					</div>

					<div class="pcw-notice pcw-notice-info" style="margin-top: 16px;">
						<p><strong><?php esc_html_e( 'Como funciona:', 'person-cash-wallet' ); ?></strong></p>
						<p><?php esc_html_e( 'Se a condição for VERDADEIRA → continua para próxima etapa', 'person-cash-wallet' ); ?></p>
						<p><?php esc_html_e( 'Se a condição for FALSA → pula as próximas etapas e encerra', 'person-cash-wallet' ); ?></p>
					</div>

				<?php elseif ( $type === 'webhook' ) : ?>
					<?php
					// Verificar se Personizi está configurado
					$personizi = PCW_Personizi_Integration::instance();
					$personizi_token = $personizi->get_api_token();
					$personizi_from = $personizi->get_default_from();
					$webhook_preset = isset( $config['webhook_preset'] ) ? $config['webhook_preset'] : '';
					?>

					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Template Rápido', 'person-cash-wallet' ); ?></label>
						<select name="steps[<?php echo esc_attr( $index ); ?>][config][webhook_preset]" 
							class="widefat pcw-webhook-preset" 
							data-step="<?php echo esc_attr( $index ); ?>">
							<option value=""><?php esc_html_e( 'Nenhum - Configurar manualmente', 'person-cash-wallet' ); ?></option>
							<option value="personizi_whatsapp" <?php selected( $webhook_preset, 'personizi_whatsapp' ); ?>>
								💬 Personizi WhatsApp
								<?php if ( ! empty( $personizi_token ) ) : ?>
									✓ <?php esc_html_e( 'Configurado', 'person-cash-wallet' ); ?>
								<?php else : ?>
									⚠️ <?php esc_html_e( 'Não configurado', 'person-cash-wallet' ); ?>
								<?php endif; ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Selecione um template para preencher automaticamente os campos abaixo', 'person-cash-wallet' ); ?>
						</p>
					</div>

					<div class="pcw-form-group">
						<label><?php esc_html_e( 'URL do Webhook', 'person-cash-wallet' ); ?></label>
						<input type="url" name="steps[<?php echo esc_attr( $index ); ?>][config][url]" 
							class="widefat pcw-webhook-url" 
							data-step="<?php echo esc_attr( $index ); ?>"
							value="<?php echo esc_attr( isset( $config['url'] ) ? $config['url'] : '' ); ?>" 
							placeholder="https://api.exemplo.com/endpoint" required>
					</div>

					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Método HTTP', 'person-cash-wallet' ); ?></label>
						<select name="steps[<?php echo esc_attr( $index ); ?>][config][method]" class="widefat pcw-webhook-method" data-step="<?php echo esc_attr( $index ); ?>">
							<option value="POST" <?php selected( isset( $config['method'] ) ? $config['method'] : 'POST', 'POST' ); ?>>POST</option>
							<option value="GET" <?php selected( isset( $config['method'] ) ? $config['method'] : '', 'GET' ); ?>>GET</option>
							<option value="PUT" <?php selected( isset( $config['method'] ) ? $config['method'] : '', 'PUT' ); ?>>PUT</option>
							<option value="PATCH" <?php selected( isset( $config['method'] ) ? $config['method'] : '', 'PATCH' ); ?>>PATCH</option>
							<option value="DELETE" <?php selected( isset( $config['method'] ) ? $config['method'] : '', 'DELETE' ); ?>>DELETE</option>
						</select>
					</div>

					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Autenticação', 'person-cash-wallet' ); ?></label>
						<select name="steps[<?php echo esc_attr( $index ); ?>][config][auth_type]" class="widefat pcw-webhook-auth" data-step="<?php echo esc_attr( $index ); ?>">
							<option value="none" <?php selected( isset( $config['auth_type'] ) ? $config['auth_type'] : 'none', 'none' ); ?>><?php esc_html_e( 'Nenhuma', 'person-cash-wallet' ); ?></option>
							<option value="bearer" <?php selected( isset( $config['auth_type'] ) ? $config['auth_type'] : '', 'bearer' ); ?>><?php esc_html_e( 'Bearer Token', 'person-cash-wallet' ); ?></option>
							<option value="basic" <?php selected( isset( $config['auth_type'] ) ? $config['auth_type'] : '', 'basic' ); ?>><?php esc_html_e( 'Basic Auth', 'person-cash-wallet' ); ?></option>
							<option value="api_key" <?php selected( isset( $config['auth_type'] ) ? $config['auth_type'] : '', 'api_key' ); ?>><?php esc_html_e( 'API Key (Header)', 'person-cash-wallet' ); ?></option>
						</select>
					</div>

					<?php
					$auth_type = isset( $config['auth_type'] ) ? $config['auth_type'] : 'none';
					$show_auth_fields = $auth_type !== 'none';
					?>

					<div class="pcw-webhook-auth-fields" id="auth-fields-<?php echo esc_attr( $index ); ?>" style="<?php echo $show_auth_fields ? '' : 'display: none;'; ?>">
						<div class="pcw-auth-bearer" style="<?php echo $auth_type === 'bearer' ? '' : 'display: none;'; ?>">
							<div class="pcw-form-group">
								<label><?php esc_html_e( 'Bearer Token', 'person-cash-wallet' ); ?></label>
								<input type="text" name="steps[<?php echo esc_attr( $index ); ?>][config][bearer_token]" 
									class="widefat" 
									value="<?php echo esc_attr( isset( $config['bearer_token'] ) ? $config['bearer_token'] : '' ); ?>" 
									placeholder="seu-token-aqui">
							</div>
						</div>

						<div class="pcw-auth-basic" style="<?php echo $auth_type === 'basic' ? '' : 'display: none;'; ?>">
							<div class="pcw-form-group">
								<label><?php esc_html_e( 'Usuário', 'person-cash-wallet' ); ?></label>
								<input type="text" name="steps[<?php echo esc_attr( $index ); ?>][config][basic_username]" 
									class="widefat" 
									value="<?php echo esc_attr( isset( $config['basic_username'] ) ? $config['basic_username'] : '' ); ?>" 
									placeholder="username">
							</div>
							<div class="pcw-form-group">
								<label><?php esc_html_e( 'Senha', 'person-cash-wallet' ); ?></label>
								<input type="password" name="steps[<?php echo esc_attr( $index ); ?>][config][basic_password]" 
									class="widefat" 
									value="<?php echo esc_attr( isset( $config['basic_password'] ) ? $config['basic_password'] : '' ); ?>" 
									placeholder="password">
							</div>
						</div>

						<div class="pcw-auth-api_key" style="<?php echo $auth_type === 'api_key' ? '' : 'display: none;'; ?>">
							<div class="pcw-form-group">
								<label><?php esc_html_e( 'Nome do Header', 'person-cash-wallet' ); ?></label>
								<input type="text" name="steps[<?php echo esc_attr( $index ); ?>][config][api_key_header]" 
									class="widefat" 
									value="<?php echo esc_attr( isset( $config['api_key_header'] ) ? $config['api_key_header'] : '' ); ?>" 
									placeholder="X-API-Key">
							</div>
							<div class="pcw-form-group">
								<label><?php esc_html_e( 'Valor da API Key', 'person-cash-wallet' ); ?></label>
								<input type="text" name="steps[<?php echo esc_attr( $index ); ?>][config][api_key_value]" 
									class="widefat" 
									value="<?php echo esc_attr( isset( $config['api_key_value'] ) ? $config['api_key_value'] : '' ); ?>" 
									placeholder="sua-api-key-aqui">
							</div>
						</div>
					</div>

					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Headers Customizados', 'person-cash-wallet' ); ?> <small>(<?php esc_html_e( 'opcional', 'person-cash-wallet' ); ?>)</small></label>
						<div class="pcw-webhook-headers" id="webhook-headers-<?php echo esc_attr( $index ); ?>">
							<?php
							$headers = isset( $config['headers'] ) && is_array( $config['headers'] ) ? $config['headers'] : array( array( 'key' => '', 'value' => '' ) );
							foreach ( $headers as $h_index => $header ) :
							?>
								<div class="pcw-webhook-header-row">
									<input type="text" name="steps[<?php echo esc_attr( $index ); ?>][config][headers][<?php echo esc_attr( $h_index ); ?>][key]" 
										value="<?php echo esc_attr( isset( $header['key'] ) ? $header['key'] : '' ); ?>" 
										placeholder="Content-Type" style="width: 45%;">
									<input type="text" name="steps[<?php echo esc_attr( $index ); ?>][config][headers][<?php echo esc_attr( $h_index ); ?>][value]" 
										value="<?php echo esc_attr( isset( $header['value'] ) ? $header['value'] : '' ); ?>" 
										placeholder="application/json" style="width: 45%;">
									<button type="button" class="button button-small pcw-remove-header" style="<?php echo count( $headers ) > 1 ? '' : 'display: none;'; ?>">
										<span class="dashicons dashicons-trash"></span>
									</button>
								</div>
							<?php endforeach; ?>
						</div>
						<button type="button" class="button button-small pcw-add-header" data-step="<?php echo esc_attr( $index ); ?>" style="margin-top: 8px;">
							<span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e( 'Adicionar Header', 'person-cash-wallet' ); ?>
						</button>
					</div>

					<?php
					$method = isset( $config['method'] ) ? $config['method'] : 'POST';
					$show_body = in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true );
					?>

					<div class="pcw-webhook-body-section" id="webhook-body-<?php echo esc_attr( $index ); ?>" style="<?php echo $show_body ? '' : 'display: none;'; ?>">
						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Formato do Body', 'person-cash-wallet' ); ?></label>
							<select name="steps[<?php echo esc_attr( $index ); ?>][config][body_format]" class="widefat">
								<option value="json" <?php selected( isset( $config['body_format'] ) ? $config['body_format'] : 'json', 'json' ); ?>>JSON</option>
								<option value="form" <?php selected( isset( $config['body_format'] ) ? $config['body_format'] : '', 'form' ); ?>>Form Data</option>
								<option value="raw" <?php selected( isset( $config['body_format'] ) ? $config['body_format'] : '', 'raw' ); ?>>Raw (texto)</option>
							</select>
						</div>

						<div class="pcw-form-group">
							<label><?php esc_html_e( 'Parâmetros / Body', 'person-cash-wallet' ); ?></label>
							<textarea name="steps[<?php echo esc_attr( $index ); ?>][config][body]" 
								class="widefat pcw-webhook-body" 
								rows="8" 
								placeholder='{"customer_name": "{{customer_name}}", "customer_email": "{{customer_email}}", "event": "automation_triggered"}'><?php echo esc_textarea( isset( $config['body'] ) ? $config['body'] : '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Use variáveis como', 'person-cash-wallet' ); ?> <code>{{customer_name}}</code>, <code>{{customer_email}}</code>, <code>{{product_name}}</code>, etc.</p>
						</div>
					</div>

					<!-- Seção de Teste do Webhook -->
					<div class="pcw-webhook-test-section" style="margin-top: 20px; padding: 15px; background: #f8fafc; border-left: 4px solid #667eea; border-radius: 4px;">
						<h4 style="margin-top: 0; color: #667eea;">
							<span class="dashicons dashicons-admin-tools"></span>
							<?php esc_html_e( 'Testar Webhook', 'person-cash-wallet' ); ?>
						</h4>
						
						<div class="pcw-webhook-test-personizi" id="webhook-test-personizi-<?php echo esc_attr( $index ); ?>" style="<?php echo $webhook_preset === 'personizi_whatsapp' ? '' : 'display: none;'; ?>">
							<div class="pcw-form-group">
								<label><?php esc_html_e( 'Número de Teste (WhatsApp)', 'person-cash-wallet' ); ?></label>
								<input type="tel" class="widefat pcw-test-phone" 
									placeholder="5535991970289" 
									pattern="[0-9]{11,15}"
									style="max-width: 300px;">
								<p class="description"><?php esc_html_e( 'Número com código do país, ex: 5535991970289', 'person-cash-wallet' ); ?></p>
							</div>
							
							<div class="pcw-form-group">
								<label><?php esc_html_e( 'Mensagem de Teste', 'person-cash-wallet' ); ?></label>
								<textarea class="widefat pcw-test-message" 
									rows="4" 
									placeholder="<?php esc_attr_e( 'Olá! Esta é uma mensagem de teste 🚀', 'person-cash-wallet' ); ?>"></textarea>
								<p class="description"><?php esc_html_e( 'Envie uma mensagem real de teste via Personizi', 'person-cash-wallet' ); ?></p>
							</div>
						</div>

						<div class="pcw-webhook-test-generic" id="webhook-test-generic-<?php echo esc_attr( $index ); ?>" style="<?php echo $webhook_preset === 'personizi_whatsapp' ? 'display: none;' : ''; ?>">
							<p class="description">
								<?php esc_html_e( 'Este teste enviará uma requisição real usando as configurações acima.', 'person-cash-wallet' ); ?>
							</p>
						</div>

						<button type="button" class="button button-primary pcw-test-webhook" data-step="<?php echo esc_attr( $index ); ?>">
							<span class="dashicons dashicons-cloud"></span> 
							<?php esc_html_e( 'Testar Agora', 'person-cash-wallet' ); ?>
						</button>
						
						<div class="pcw-webhook-test-result" id="webhook-test-<?php echo esc_attr( $index ); ?>" style="margin-top: 15px;"></div>
					</div>

				<?php endif; ?>

				<div class="pcw-step-variables">
					<p class="description">
						<strong><?php esc_html_e( 'Variáveis disponíveis:', 'person-cash-wallet' ); ?></strong><br>
						<code>{{customer_name}}</code>, <code>{{customer_first_name}}</code>, <code>{{customer_email}}</code>,
						<code>{{product_name}}</code>, <code>{{cashback_balance}}</code>, <code>{{site_name}}</code>
					</p>
				</div>
			</div>
		</div>
		<div class="pcw-workflow-connector"></div>
		<?php
	}

	/**
	 * Renderizar campos de configuração do gatilho
	 *
	 * @param string $trigger ID do gatilho.
	 * @param object|null $automation Dados da automação.
	 */
	private function render_trigger_config( $trigger, $automation ) {
		$trigger_config = $automation && ! empty( $automation->trigger_config ) ? $automation->trigger_config : array();

		?>
		<div class="pcw-trigger-config" style="margin-top: 15px;">
			<?php
			switch ( $trigger ) {
			case 'inactive_customer':
				$days         = isset( $trigger_config['inactive_days'] ) ? $trigger_config['inactive_days'] : 30;
				$include_hist = isset( $trigger_config['include_historical'] ) ? $trigger_config['include_historical'] : '1';
				$batch_per_day = isset( $trigger_config['batch_per_day'] ) ? $trigger_config['batch_per_day'] : 50;
				$batch_days    = isset( $trigger_config['batch_days'] ) ? $trigger_config['batch_days'] : 30;
				?>
				<div class="pcw-form-group">
					<label><?php esc_html_e( 'Dias de Inatividade', 'person-cash-wallet' ); ?></label>
					<div class="pcw-inline-form" style="align-items: center; gap: 8px;">
						<span><?php esc_html_e( 'Cliente inativo por', 'person-cash-wallet' ); ?></span>
						<input type="number" name="trigger_config[inactive_days]"
							value="<?php echo esc_attr( $days ); ?>"
							min="1"
							style="width: 80px;">
						<span><?php esc_html_e( 'dias', 'person-cash-wallet' ); ?></span>
					</div>
					<p class="description"><?php esc_html_e( 'Tempo sem realizar compras para ser considerado inativo', 'person-cash-wallet' ); ?></p>
				</div>

				<div class="pcw-form-group" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-top: 10px;">
					<label style="font-weight: 600; display: block; margin-bottom: 10px;">
						<span class="dashicons dashicons-groups" style="color: #f59e0b;"></span>
						<?php esc_html_e( 'Clientes da Base Existente', 'person-cash-wallet' ); ?>
					</label>

					<div style="margin-bottom: 12px;">
						<label class="pcw-checkbox-inline">
							<input type="checkbox"
								name="trigger_config[include_historical]"
								value="1"
							class="pcw-include-historical"
								<?php checked( $include_hist, '1' ); ?>>
							<?php esc_html_e( 'Incluir clientes já inativos na base', 'person-cash-wallet' ); ?>
						</label>
						<p class="description" style="margin-left: 24px; margin-top: 4px;">
							<?php esc_html_e( 'Ativa envio gradual para clientes que já estão inativos antes desta automação ser criada.', 'person-cash-wallet' ); ?>
						</p>
					</div>

					<div class="pcw-historical-pacing" style="<?php echo $include_hist == '1' ? '' : 'display:none;'; ?> border-top: 1px dashed #cbd5e1; padding-top: 12px;">
						<p style="margin: 0 0 10px; font-size: 12px; color: #64748b;">
							⚡ <?php esc_html_e( 'Envio gradual para evitar bloqueios e spam. O cron diário disparará lotes até zerar a fila histórica.', 'person-cash-wallet' ); ?>
						</p>
						<div class="pcw-inline-form" style="align-items: center; gap: 8px; margin-bottom: 8px;">
							<span><?php esc_html_e( 'Enviar', 'person-cash-wallet' ); ?></span>
							<input type="number"
								name="trigger_config[batch_per_day]"
								value="<?php echo esc_attr( $batch_per_day ); ?>"
								min="1"
								max="500"
								style="width: 70px;">
							<span><?php esc_html_e( 'clientes por dia', 'person-cash-wallet' ); ?></span>
						</div>
						<div class="pcw-inline-form" style="align-items: center; gap: 8px;">
							<span><?php esc_html_e( 'por até', 'person-cash-wallet' ); ?></span>
							<input type="number"
								name="trigger_config[batch_days]"
								value="<?php echo esc_attr( $batch_days ); ?>"
								min="1"
								max="365"
								style="width: 70px;">
							<span><?php esc_html_e( 'dias (depois só novos inativos)', 'person-cash-wallet' ); ?></span>
						</div>
						<p class="description" style="margin-top: 8px; color: #dc2626;">
							<?php
							printf(
								/* translators: %1$s: batch size, %2$s: days */
								esc_html__( 'Com %1$s clientes/dia por %2$s dias = máx. %3$s contatos totais da base histórica.', 'person-cash-wallet' ),
								'<strong id="batch_total_clients">' . esc_html( $batch_per_day ) . '</strong>',
								'<strong id="batch_total_days">' . esc_html( $batch_days ) . '</strong>',
								'<strong id="batch_total_calc">' . esc_html( $batch_per_day * $batch_days ) . '</strong>'
							);
							?>
						</p>
					</div>
				</div>
				<script>
				(function($){
					$(document).on('change', '.pcw-include-historical', function(){
						$(this).closest('.pcw-form-group').find('.pcw-historical-pacing').toggle(this.checked);
					});
					$(document).on('input', 'input[name="trigger_config[batch_per_day]"], input[name="trigger_config[batch_days]"]', function(){
						var perDay = parseInt($('input[name="trigger_config[batch_per_day]"]').val()) || 0;
						var days   = parseInt($('input[name="trigger_config[batch_days]"]').val()) || 0;
						$('#batch_total_clients').text(perDay);
						$('#batch_total_days').text(days);
						$('#batch_total_calc').text(perDay * days);
					});
				})(jQuery);
				</script>
				<?php
				break;

				case 'cart_abandoned':
					$hours = isset( $trigger_config['abandoned_hours'] ) ? $trigger_config['abandoned_hours'] : 24;
					?>
					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Tempo de Abandono', 'person-cash-wallet' ); ?></label>
						<div class="pcw-inline-form" style="align-items: center; gap: 8px;">
							<span><?php esc_html_e( 'Carrinho abandonado por', 'person-cash-wallet' ); ?></span>
							<input type="number" name="trigger_config[abandoned_hours]" 
								value="<?php echo esc_attr( $hours ); ?>" 
								min="1" 
								style="width: 80px;">
							<span><?php esc_html_e( 'horas', 'person-cash-wallet' ); ?></span>
						</div>
						<p class="description"><?php esc_html_e( 'Tempo que o carrinho ficou abandonado antes de disparar a automação', 'person-cash-wallet' ); ?></p>
					</div>
					<?php
					break;

				case 'product_view':
					$days = isset( $trigger_config['view_days'] ) ? $trigger_config['view_days'] : 7;
					$min_views = isset( $trigger_config['min_views'] ) ? $trigger_config['min_views'] : 2;
					?>
					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Período de Visualização', 'person-cash-wallet' ); ?></label>
						<div class="pcw-inline-form" style="align-items: center; gap: 8px;">
							<span><?php esc_html_e( 'Produto visualizado', 'person-cash-wallet' ); ?></span>
							<input type="number" name="trigger_config[min_views]" 
								value="<?php echo esc_attr( $min_views ); ?>" 
								min="1" 
								style="width: 60px;">
							<span><?php esc_html_e( 'vezes nos últimos', 'person-cash-wallet' ); ?></span>
							<input type="number" name="trigger_config[view_days]" 
								value="<?php echo esc_attr( $days ); ?>" 
								min="1" 
								style="width: 60px;">
							<span><?php esc_html_e( 'dias', 'person-cash-wallet' ); ?></span>
						</div>
						<p class="description"><?php esc_html_e( 'Dispara quando o cliente visualiza produtos mas não compra', 'person-cash-wallet' ); ?></p>
					</div>
					<?php
					break;

				case 'cashback_expiring':
					$days = isset( $trigger_config['expiring_days'] ) ? $trigger_config['expiring_days'] : 7;
					?>
					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Dias Antes da Expiração', 'person-cash-wallet' ); ?></label>
						<div class="pcw-inline-form" style="align-items: center; gap: 8px;">
							<span><?php esc_html_e( 'Notificar quando faltar', 'person-cash-wallet' ); ?></span>
							<input type="number" name="trigger_config[expiring_days]" 
								value="<?php echo esc_attr( $days ); ?>" 
								min="1" 
								style="width: 80px;">
							<span><?php esc_html_e( 'dias para expirar', 'person-cash-wallet' ); ?></span>
						</div>
						<p class="description"><?php esc_html_e( 'Alertar cliente antes do cashback expirar', 'person-cash-wallet' ); ?></p>
					</div>
					<?php
					break;

				case 'order_completed':
					$min_value = isset( $trigger_config['min_order_value'] ) ? $trigger_config['min_order_value'] : '';
					?>
					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Valor Mínimo do Pedido (opcional)', 'person-cash-wallet' ); ?></label>
						<div class="pcw-inline-form" style="align-items: center; gap: 8px;">
							<span>R$</span>
							<input type="number" name="trigger_config[min_order_value]" 
								value="<?php echo esc_attr( $min_value ); ?>" 
								min="0" 
								step="0.01"
								placeholder="0.00"
								style="width: 120px;">
						</div>
						<p class="description"><?php esc_html_e( 'Deixe vazio para disparar em qualquer valor de pedido', 'person-cash-wallet' ); ?></p>
					</div>
					<?php
					break;

				case 'cashback_earned':
					$min_value = isset( $trigger_config['min_cashback_value'] ) ? $trigger_config['min_cashback_value'] : '';
					?>
					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Valor Mínimo de Cashback (opcional)', 'person-cash-wallet' ); ?></label>
						<div class="pcw-inline-form" style="align-items: center; gap: 8px;">
							<span>R$</span>
							<input type="number" name="trigger_config[min_cashback_value]" 
								value="<?php echo esc_attr( $min_value ); ?>" 
								min="0" 
								step="0.01"
								placeholder="0.00"
								style="width: 120px;">
						</div>
						<p class="description"><?php esc_html_e( 'Deixe vazio para notificar em qualquer valor de cashback', 'person-cash-wallet' ); ?></p>
					</div>
					<?php
					break;

				case 'level_achieved':
					$level_id = isset( $trigger_config['level_id'] ) ? $trigger_config['level_id'] : '';
					?>
					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Nível Específico (opcional)', 'person-cash-wallet' ); ?></label>
						<select name="trigger_config[level_id]" class="widefat" style="max-width: 300px;">
							<option value=""><?php esc_html_e( 'Qualquer nível', 'person-cash-wallet' ); ?></option>
							<?php
							global $wpdb;
							$levels = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}pcw_levels ORDER BY min_points ASC" );
							foreach ( $levels as $level ) :
							?>
								<option value="<?php echo esc_attr( $level->id ); ?>" <?php selected( $level_id, $level->id ); ?>>
									<?php echo esc_html( $level->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Deixe vazio para disparar em qualquer nível alcançado', 'person-cash-wallet' ); ?></p>
					</div>
					<?php
					break;

				case 'new_product':
					$categories = isset( $trigger_config['product_categories'] ) ? (array) $trigger_config['product_categories'] : array();
					?>
					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Categorias Específicas (opcional)', 'person-cash-wallet' ); ?></label>
						<select name="trigger_config[product_categories][]" class="widefat" multiple size="5" style="max-width: 300px;">
							<?php
							$terms = get_terms( array(
								'taxonomy'   => 'product_cat',
								'hide_empty' => false,
							) );
							foreach ( $terms as $term ) :
							?>
								<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( $term->term_id, $categories, true ) ); ?>>
									<?php echo esc_html( $term->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Deixe vazio para notificar sobre qualquer novo produto. Segure Ctrl (Cmd no Mac) para selecionar múltiplas categorias.', 'person-cash-wallet' ); ?></p>
					</div>
					<?php
					break;
			}
			?>
		</div>
		<?php
	}

	/**
	 * Obter descrição do gatilho
	 *
	 * @param string $trigger ID do gatilho.
	 * @return string
	 */
	private function get_trigger_description( $trigger ) {
		$descriptions = array(
			'product_view'      => __( 'Quando o cliente visualiza produtos mas não compra', 'person-cash-wallet' ),
			'inactive_customer' => __( 'Quando o cliente fica inativo por um período sem compras', 'person-cash-wallet' ),
			'order_completed'   => __( 'Quando um pedido é finalizado', 'person-cash-wallet' ),
			'cart_abandoned'    => __( 'Quando o cliente abandona o carrinho por um período', 'person-cash-wallet' ),
			'user_registered'   => __( 'Quando um novo usuário se cadastra', 'person-cash-wallet' ),
			'new_product'       => __( 'Quando um novo produto é publicado', 'person-cash-wallet' ),
			'cashback_earned'   => __( 'Quando o cliente ganha cashback', 'person-cash-wallet' ),
			'cashback_expiring' => __( 'Quando o cashback está prestes a expirar', 'person-cash-wallet' ),
			'level_achieved'    => __( 'Quando o cliente alcança um novo nível', 'person-cash-wallet' ),
		);

		return isset( $descriptions[ $trigger ] ) ? $descriptions[ $trigger ] : $trigger;
	}

	/**
	 * Escurecer cor
	 *
	 * @param string $hex Cor hex.
	 * @return string
	 */
	private function darken_color( $hex ) {
		$hex = ltrim( $hex, '#' );
		$r = max( 0, hexdec( substr( $hex, 0, 2 ) ) - 30 );
		$g = max( 0, hexdec( substr( $hex, 2, 2 ) ) - 30 );
		$b = max( 0, hexdec( substr( $hex, 4, 2 ) ) - 30 );
		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Sanitizar configuração do gatilho
	 *
	 * @param array $config Configuração não sanitizada.
	 * @return array
	 */
	private function sanitize_trigger_config( $config ) {
		if ( ! is_array( $config ) ) {
			return array();
		}

		$sanitized = array();

		// Dias de inatividade
		if ( isset( $config['inactive_days'] ) ) {
			$sanitized['inactive_days'] = absint( $config['inactive_days'] );
		}

		// Clientes históricos - pacing
		$sanitized['include_historical'] = ! empty( $config['include_historical'] ) ? '1' : '0';
		if ( isset( $config['batch_per_day'] ) ) {
			$sanitized['batch_per_day'] = max( 1, min( 500, absint( $config['batch_per_day'] ) ) );
		}
		if ( isset( $config['batch_days'] ) ) {
			$sanitized['batch_days'] = max( 1, min( 365, absint( $config['batch_days'] ) ) );
		}

		// Horas de carrinho abandonado
		if ( isset( $config['abandoned_hours'] ) ) {
			$sanitized['abandoned_hours'] = absint( $config['abandoned_hours'] );
		}

		// Dias de visualização de produto
		if ( isset( $config['view_days'] ) ) {
			$sanitized['view_days'] = absint( $config['view_days'] );
		}

		// Visualizações mínimas
		if ( isset( $config['min_views'] ) ) {
			$sanitized['min_views'] = absint( $config['min_views'] );
		}

		// Dias antes de expirar cashback
		if ( isset( $config['expiring_days'] ) ) {
			$sanitized['expiring_days'] = absint( $config['expiring_days'] );
		}

		// Valor mínimo do pedido
		if ( isset( $config['min_order_value'] ) && $config['min_order_value'] !== '' ) {
			$sanitized['min_order_value'] = floatval( $config['min_order_value'] );
		}

		// Valor mínimo de cashback
		if ( isset( $config['min_cashback_value'] ) && $config['min_cashback_value'] !== '' ) {
			$sanitized['min_cashback_value'] = floatval( $config['min_cashback_value'] );
		}

		// ID do nível
		if ( isset( $config['level_id'] ) && $config['level_id'] !== '' ) {
			$sanitized['level_id'] = absint( $config['level_id'] );
		}

		// Categorias de produto
		if ( isset( $config['product_categories'] ) && is_array( $config['product_categories'] ) ) {
			$sanitized['product_categories'] = array_map( 'absint', $config['product_categories'] );
		}

		return $sanitized;
	}

	/**
	 * AJAX: Salvar automação
	 */
	public function ajax_save_automation() {
		check_ajax_referer( 'pcw_automations', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$automation_id = isset( $_POST['automation_id'] ) ? absint( $_POST['automation_id'] ) : 0;

		$data = array(
			'name'           => isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '',
			'description'    => isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '',
			'type'           => isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '',
			'status'         => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'inactive',
			'trigger_type'   => isset( $_POST['type'] ) && sanitize_text_field( $_POST['type'] ) === 'customer_recovery' ? 'customer_recovery' : ( isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '' ),
			'trigger_config' => isset( $_POST['trigger_config'] ) ? $this->sanitize_trigger_config( $_POST['trigger_config'] ) : array(),
			'workflow_steps' => isset( $_POST['steps'] ) ? $_POST['steps'] : array(),
			'email_template' => isset( $_POST['email_template'] ) ? wp_kses_post( $_POST['email_template'] ) : '',
			'email_subject'  => isset( $_POST['email_subject'] ) ? sanitize_text_field( $_POST['email_subject'] ) : '',
			'use_ai_subject' => isset( $_POST['use_ai_subject'] ) ? 1 : 0,
		);

		$manager = PCW_Automations::instance();

		if ( $automation_id > 0 ) {
			$result = $manager->update( $automation_id, $data );
		} else {
			$automation_id = $manager->create( $data );
			$result = $automation_id > 0;

			// Para automações baseadas em cron, quando o usuário NÃO quer incluir
			// clientes históricos, registrá-los como "skipped" para que nunca sejam disparados.
			// Quando include_historical = '1', o pacing cuida da distribuição gradual —
			// não há seed para não bloquear o processamento em lotes.
			$include_hist = isset( $data['trigger_config']['include_historical'] ) ? $data['trigger_config']['include_historical'] : '0';
			if ( $result && $include_hist !== '1' && in_array( $data['type'], array( 'customer_recovery', 'cashback_expiring', 'abandoned_cart' ), true ) ) {
				$executor = PCW_Automation_Executor::instance();
				$executor->seed_existing_eligible_users( $automation_id, $data['type'], $data['trigger_config'] );
			}
		}

		if ( $result ) {
			wp_send_json_success( array(
				'message' => __( 'Automação salva com sucesso!', 'person-cash-wallet' ),
				'redirect' => admin_url( 'admin.php?page=pcw-automations' ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Erro ao salvar automação', 'person-cash-wallet' ) ) );
		}
	}

	/**
	 * AJAX: Toggle automação
	 */
	public function ajax_toggle_automation() {
		check_ajax_referer( 'pcw_automations', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$status = isset( $_POST['status'] ) && $_POST['status'] === 'active' ? 'inactive' : 'active';

		$result = PCW_Automations::instance()->update( $id, array( 'status' => $status ) );

		if ( $result ) {
			wp_send_json_success( array( 'status' => $status ) );
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * AJAX: Deletar automação
	 */
	public function ajax_delete_automation() {
		check_ajax_referer( 'pcw_automations', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$result = PCW_Automations::instance()->delete( $id );

		wp_send_json_success();
	}

	/**
	 * AJAX: Gerar assunto com IA
	 */
	public function ajax_generate_ai_subject() {
		check_ajax_referer( 'pcw_automations', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		$context = isset( $_POST['context'] ) ? sanitize_textarea_field( $_POST['context'] ) : '';

		$openai = PCW_OpenAI::instance();

		if ( ! $openai->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Configure a API da OpenAI nas configurações', 'person-cash-wallet' ) ) );
		}

		$types = PCW_Automations::get_automation_types();
		$type_name = isset( $types[ $type ] ) ? $types[ $type ]['name'] : $type;
		$type_description = isset( $types[ $type ] ) ? $types[ $type ]['description'] : '';

		// Buscar contexto automaticamente
		$auto_context = $this->get_automation_context( $type );
		
		// Combinar contexto manual com automático
		$full_context = $type_description;
		if ( ! empty( $auto_context ) ) {
			$full_context .= "\n\nDados da loja: " . $auto_context;
		}
		if ( ! empty( $context ) ) {
			$full_context .= "\n\nInstruções: " . $context;
		}

		$result = $openai->generate_email_subject( $full_context, 'automation' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'subject' => $result ) );
	}

	/**
	 * AJAX: Gerar conteúdo com IA
	 */
	public function ajax_generate_ai_content() {
		check_ajax_referer( 'pcw_automations', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		$context = isset( $_POST['context'] ) ? sanitize_textarea_field( $_POST['context'] ) : '';

		$openai = PCW_OpenAI::instance();

		if ( ! $openai->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Configure a API da OpenAI nas configurações', 'person-cash-wallet' ) ) );
		}

		$types = PCW_Automations::get_automation_types();
		$type_name = isset( $types[ $type ] ) ? $types[ $type ]['name'] : $type;
		$type_description = isset( $types[ $type ] ) ? $types[ $type ]['description'] : '';

		// Buscar contexto automaticamente
		$auto_context = $this->get_automation_context( $type );
		
		// Combinar contexto manual com automático
		$full_context = $type_description;
		if ( ! empty( $auto_context ) ) {
			$full_context .= "\n\nDados da loja: " . $auto_context;
		}
		if ( ! empty( $context ) ) {
			$full_context .= "\n\nInstruções: " . $context;
		}

		$result = $openai->generate_email_content( $full_context, 'automation' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'content' => $result ) );
	}

	/**
	 * Buscar contexto automático para IA baseado no tipo de automação
	 *
	 * @param string $type Tipo de automação.
	 * @return string
	 */
	private function get_automation_context( $type ) {
		$context_parts = array();

		// Informações da loja
		$context_parts[] = "Nome da loja: " . get_bloginfo( 'name' );

		// Contexto específico por tipo de automação
		switch ( $type ) {
			case 'product_viewed':
				$top_products = $this->get_top_viewed_products( 5 );
				if ( ! empty( $top_products ) ) {
					$context_parts[] = "Produtos mais visualizados: " . implode( ', ', $top_products );
				}
				break;

			case 'customer_recovery':
				$customer_stats = $this->get_customer_stats();
				if ( ! empty( $customer_stats ) ) {
					$context_parts[] = "Estatísticas: " . $customer_stats;
				}
				break;

			case 'recommended_products':
			case 'post_purchase':
				$top_sellers = $this->get_top_selling_products( 5 );
				if ( ! empty( $top_sellers ) ) {
					$context_parts[] = "Produtos mais vendidos: " . implode( ', ', $top_sellers );
				}
				break;

			case 'abandoned_cart':
				$avg_cart_value = $this->get_average_cart_value();
				if ( $avg_cart_value ) {
					$context_parts[] = "Valor médio do carrinho: " . wc_price( $avg_cart_value );
				}
				break;

			case 'new_products':
				$recent_products = $this->get_recent_products( 3 );
				if ( ! empty( $recent_products ) ) {
					$context_parts[] = "Lançamentos recentes: " . implode( ', ', $recent_products );
				}
				break;

			case 'cashback_earned':
			case 'cashback_expiring':
				$cashback_stats = $this->get_cashback_stats();
				if ( ! empty( $cashback_stats ) ) {
					$context_parts[] = $cashback_stats;
				}
				break;

			case 'level_achieved':
				$levels = PCW_Levels::instance()->get_all_levels();
				if ( ! empty( $levels ) ) {
					$level_names = array_map( function( $level ) {
						return $level->name;
					}, $levels );
					$context_parts[] = "Níveis VIP: " . implode( ', ', $level_names );
				}
				break;
		}

		// Informações gerais do WooCommerce
		$categories = $this->get_top_categories( 3 );
		if ( ! empty( $categories ) ) {
			$context_parts[] = "Principais categorias: " . implode( ', ', $categories );
		}

		return implode( "\n", $context_parts );
	}

	/**
	 * Obter produtos mais visualizados
	 *
	 * @param int $limit Limite.
	 * @return array
	 */
	private function get_top_viewed_products( $limit = 5 ) {
		$products = wc_get_products( array(
			'limit'   => $limit,
			'orderby' => 'popularity',
			'order'   => 'DESC',
			'status'  => 'publish',
		) );

		return array_map( function( $product ) {
			return $product->get_name();
		}, $products );
	}

	/**
	 * Obter produtos mais vendidos
	 *
	 * @param int $limit Limite.
	 * @return array
	 */
	private function get_top_selling_products( $limit = 5 ) {
		$products = wc_get_products( array(
			'limit'   => $limit,
			'orderby' => 'total_sales',
			'order'   => 'DESC',
			'status'  => 'publish',
		) );

		return array_map( function( $product ) {
			return $product->get_name() . ' (' . wc_price( $product->get_price() ) . ')';
		}, $products );
	}

	/**
	 * Obter produtos recentes
	 *
	 * @param int $limit Limite.
	 * @return array
	 */
	private function get_recent_products( $limit = 3 ) {
		$products = wc_get_products( array(
			'limit'   => $limit,
			'orderby' => 'date',
			'order'   => 'DESC',
			'status'  => 'publish',
		) );

		return array_map( function( $product ) {
			return $product->get_name() . ' (' . wc_price( $product->get_price() ) . ')';
		}, $products );
	}

	/**
	 * Obter principais categorias
	 *
	 * @param int $limit Limite.
	 * @return array
	 */
	private function get_top_categories( $limit = 3 ) {
		$categories = get_terms( array(
			'taxonomy'   => 'product_cat',
			'number'     => $limit,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'hide_empty' => true,
		) );

		if ( is_wp_error( $categories ) || empty( $categories ) ) {
			return array();
		}

		return array_map( function( $cat ) {
			return $cat->name;
		}, $categories );
	}

	/**
	 * Obter estatísticas de clientes
	 *
	 * @return string
	 */
	private function get_customer_stats() {
		global $wpdb;

		$total_customers = $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_author) 
			FROM {$wpdb->posts} 
			WHERE post_type = 'shop_order' 
			AND post_status IN ('wc-completed', 'wc-processing')"
		);

		$avg_order_value = $wpdb->get_var(
			"SELECT AVG(meta_value) 
			FROM {$wpdb->postmeta} 
			WHERE meta_key = '_order_total'"
		);

		if ( $total_customers && $avg_order_value ) {
			return sprintf(
				'Total de clientes: %s | Ticket médio: %s',
				number_format_i18n( $total_customers ),
				wc_price( $avg_order_value )
			);
		}

		return '';
	}

	/**
	 * Obter valor médio do carrinho
	 *
	 * @return float
	 */
	private function get_average_cart_value() {
		global $wpdb;

		return (float) $wpdb->get_var(
			"SELECT AVG(meta_value) 
			FROM {$wpdb->postmeta} 
			WHERE meta_key = '_order_total'"
		);
	}

	/**
	 * Obter estatísticas de cashback
	 *
	 * @return string
	 */
	private function get_cashback_stats() {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_cashback';

		$avg_cashback = $wpdb->get_var(
			"SELECT AVG(amount) FROM {$table} WHERE status = 'available'"
		);

		$total_active = $wpdb->get_var(
			"SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE status = 'available'"
		);

		if ( $avg_cashback && $total_active ) {
			return sprintf(
				'Cashback médio disponível: %s | Clientes com cashback ativo: %s',
				wc_price( $avg_cashback ),
				number_format_i18n( $total_active )
			);
		}

		return '';
	}

	/**
	 * AJAX: Testar webhook
	 */
	public function ajax_test_webhook() {
		check_ajax_referer( 'pcw_automations', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$preset = isset( $_POST['preset'] ) ? sanitize_text_field( $_POST['preset'] ) : '';

		// Teste especial para Personizi
		if ( $preset === 'personizi_whatsapp' ) {
			$test_phone = isset( $_POST['test_phone'] ) ? sanitize_text_field( $_POST['test_phone'] ) : '';
			$test_message = isset( $_POST['test_message'] ) ? sanitize_textarea_field( $_POST['test_message'] ) : '';

			if ( empty( $test_phone ) ) {
				wp_send_json_error( array( 'message' => __( 'Número de teste é obrigatório', 'person-cash-wallet' ) ) );
			}

			if ( empty( $test_message ) ) {
				wp_send_json_error( array( 'message' => __( 'Mensagem de teste é obrigatória', 'person-cash-wallet' ) ) );
			}

			// Enviar via Personizi
			$personizi = PCW_Personizi_Integration::instance();
			$result = $personizi->send_whatsapp_message( $test_phone, $test_message, 'Teste Automação' );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			$message_id = isset( $result['data']['message_id'] ) ? $result['data']['message_id'] : '';
			$status = isset( $result['data']['status'] ) ? $result['data']['status'] : '';

			wp_send_json_success( array(
				'message' => sprintf(
					__( 'Mensagem enviada com sucesso via Personizi! ID: %s | Status: %s', 'person-cash-wallet' ),
					$message_id,
					$status
				),
				'data' => $result['data'],
			) );
		}

		// Teste genérico de webhook
		$url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';
		$method = isset( $_POST['method'] ) ? sanitize_text_field( $_POST['method'] ) : 'POST';
		$auth_type = isset( $_POST['auth_type'] ) ? sanitize_text_field( $_POST['auth_type'] ) : 'none';
		$auth_data = isset( $_POST['auth_data'] ) ? $_POST['auth_data'] : array();
		$headers_array = isset( $_POST['headers'] ) ? $_POST['headers'] : array();
		$body = isset( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : '';
		$body_format = isset( $_POST['body_format'] ) ? sanitize_text_field( $_POST['body_format'] ) : 'json';

		if ( empty( $url ) ) {
			wp_send_json_error( array( 'message' => __( 'URL é obrigatória', 'person-cash-wallet' ) ) );
		}

		// Preparar headers
		$headers = array();
		
		// Headers customizados
		if ( ! empty( $headers_array ) ) {
			foreach ( $headers_array as $header ) {
				if ( ! empty( $header['key'] ) && ! empty( $header['value'] ) ) {
					$headers[ sanitize_text_field( $header['key'] ) ] = sanitize_text_field( $header['value'] );
				}
			}
		}

		// Autenticação
		if ( $auth_type === 'bearer' && ! empty( $auth_data['bearer_token'] ) ) {
			$headers['Authorization'] = 'Bearer ' . sanitize_text_field( $auth_data['bearer_token'] );
		} elseif ( $auth_type === 'basic' && ! empty( $auth_data['basic_username'] ) && ! empty( $auth_data['basic_password'] ) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( sanitize_text_field( $auth_data['basic_username'] ) . ':' . sanitize_text_field( $auth_data['basic_password'] ) );
		} elseif ( $auth_type === 'api_key' && ! empty( $auth_data['api_key_header'] ) && ! empty( $auth_data['api_key_value'] ) ) {
			$headers[ sanitize_text_field( $auth_data['api_key_header'] ) ] = sanitize_text_field( $auth_data['api_key_value'] );
		}

		// Preparar body
		$request_body = '';
		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && ! empty( $body ) ) {
			if ( $body_format === 'json' ) {
				$headers['Content-Type'] = 'application/json';
				$request_body = $body;
			} elseif ( $body_format === 'form' ) {
				$headers['Content-Type'] = 'application/x-www-form-urlencoded';
				parse_str( $body, $body_array );
				$request_body = http_build_query( $body_array );
			} else {
				$request_body = $body;
			}
		}

		// Fazer requisição
		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'body'    => $request_body,
			'timeout' => 15,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body_response = wp_remote_retrieve_body( $response );

		wp_send_json_success( array(
			'status' => $status,
			'body'   => $body_response,
		) );
	}

	/**
	 * AJAX: Buscar token do Personizi
	 */
	public function ajax_get_personizi_token() {
		check_ajax_referer( 'pcw_automations', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$personizi = PCW_Personizi_Integration::instance();
		$token = $personizi->get_api_token();
		$from = $personizi->get_default_from();

		wp_send_json_success( array(
			'token' => $token,
			'from'  => $from,
		) );
	}

	/**
	 * AJAX: Buscar contas WhatsApp do Personizi
	 */
	public function ajax_get_personizi_accounts() {
		check_ajax_referer( 'pcw_automations', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$personizi = PCW_Personizi_Integration::instance();
		$accounts = $personizi->get_whatsapp_accounts();

		if ( is_wp_error( $accounts ) ) {
			wp_send_json_error( array( 'message' => $accounts->get_error_message() ) );
		}

		wp_send_json_success( array( 'accounts' => $accounts ) );
	}

	/**
	 * AJAX: Gerar mensagem WhatsApp com IA
	 */
	public function ajax_generate_whatsapp_ai() {
		check_ajax_referer( 'pcw_automations', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$trigger = isset( $_POST['trigger'] ) ? sanitize_text_field( $_POST['trigger'] ) : '';
		$automation_name = isset( $_POST['automation_name'] ) ? sanitize_text_field( $_POST['automation_name'] ) : '';

		if ( empty( $trigger ) ) {
			wp_send_json_error( array( 'message' => __( 'Trigger não fornecido', 'person-cash-wallet' ) ) );
		}

		// Verificar se tem OpenAI configurado
		if ( ! class_exists( 'PCW_OpenAI' ) ) {
			wp_send_json_error( array( 'message' => __( 'Classe OpenAI não encontrada', 'person-cash-wallet' ) ) );
		}

		$openai = PCW_OpenAI::instance();

		if ( ! $openai->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Configure a API Key da OpenAI em Configurações > IA antes de gerar mensagens.', 'person-cash-wallet' ) ) );
		}

		// Preparar contexto baseado no trigger
		$context = $this->prepare_whatsapp_context( $trigger, $automation_name );

		// Gerar mensagem
		$message = $openai->generate_whatsapp_message( $context, $trigger );

		if ( is_wp_error( $message ) ) {
			wp_send_json_error( array( 'message' => $message->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => $message,
			'context' => $context,
		) );
	}

	/**
	 * Preparar contexto para geração de mensagem WhatsApp
	 *
	 * @param string $trigger Tipo de trigger
	 * @param string $automation_name Nome da automação
	 * @return array Contexto
	 */
	private function prepare_whatsapp_context( $trigger, $automation_name ) {
		$context = array(
			'trigger'          => $trigger,
			'automation_name'  => $automation_name,
			'platform'         => 'whatsapp',
			'business_type'    => 'ecommerce',
			'site_name'        => get_bloginfo( 'name' ),
		);

		// Adicionar informações específicas por trigger
		switch ( $trigger ) {
			case 'user_registered':
				$context['type'] = 'boas-vindas';
				$context['purpose'] = 'dar boas-vindas ao novo usuário e apresentar benefícios';
				$context['variables'] = array( 'customer_first_name', 'customer_name', 'site_name', 'cashback_balance' );
				break;

			case 'order_completed':
				$context['type'] = 'confirmacao-pedido';
				$context['purpose'] = 'confirmar pedido e informar próximos passos';
				$context['variables'] = array( 'customer_first_name', 'order_number', 'order_total', 'cashback_earned' );
				break;

			case 'cashback_expiring':
				$context['type'] = 'alerta-expiracao';
				$context['purpose'] = 'alertar sobre cashback prestes a expirar';
				$context['variables'] = array( 'customer_first_name', 'cashback_balance', 'expiry_date' );
				break;

			case 'birthday':
				$context['type'] = 'aniversario';
				$context['purpose'] = 'parabenizar e oferecer presente de aniversário';
				$context['variables'] = array( 'customer_first_name', 'birthday_gift' );
				break;

			case 'abandoned_cart':
				$context['type'] = 'carrinho-abandonado';
				$context['purpose'] = 'lembrar sobre produtos no carrinho';
				$context['variables'] = array( 'customer_first_name', 'cart_items', 'cart_total' );
				break;

			case 'customer_recovery':
			case 'inactive_customer':
				$context['type'] = 'recuperacao-clientes';
				$context['purpose'] = 'trazer de volta um cliente que não compra há bastante tempo, com tom amigável e saudoso, oferecendo motivos para voltar';
				$context['variables'] = array( 'customer_first_name', 'customer_name', 'site_name', 'cashback_balance' );
				break;

			default:
				$context['type'] = 'notificacao';
				$context['purpose'] = 'enviar notificação relevante';
				$context['variables'] = array( 'customer_first_name', 'customer_name' );
				break;
		}

		return $context;
	}
}
