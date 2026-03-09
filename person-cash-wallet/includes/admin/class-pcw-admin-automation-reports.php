<?php
/**
 * Admin de Relatórios de Automações
 *
 * @package GrowlyDigital
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe admin de relatórios de automações
 */
class PCW_Admin_Automation_Reports {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_pcw_get_automation_metrics', array( $this, 'ajax_get_metrics' ) );
		add_action( 'wp_ajax_pcw_get_automation_events', array( $this, 'ajax_get_events' ) );
		add_action( 'wp_ajax_pcw_get_automation_queue', array( $this, 'ajax_get_queue' ) );
		add_action( 'wp_ajax_pcw_export_automation_report', array( $this, 'ajax_export_report' ) );
	}

	/**
	 * Adicionar submenu (oculto)
	 */
	public function add_menu() {
		// Submenu oculto - será acessado via URL direta
		add_submenu_page(
			null, // parent_slug = null torna o menu oculto
			__( 'Relatório de Automação', 'person-cash-wallet' ),
			__( 'Relatório', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-automation-report',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue scripts
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'pcw-automation-report' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'pcw-automation-reports',
			PCW_PLUGIN_URL . 'assets/css/automation-reports.css',
			array(),
			PCW_VERSION
		);

		wp_enqueue_script(
			'chart-js',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);

		wp_enqueue_script(
			'pcw-automation-reports',
			PCW_PLUGIN_URL . 'assets/js/automation-reports.js',
			array( 'jquery', 'chart-js' ),
			PCW_VERSION,
			true
		);

		wp_localize_script( 'pcw-automation-reports', 'pcwReports', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pcw_reports' ),
			'i18n'    => array(
				'loading'       => __( 'Carregando...', 'person-cash-wallet' ),
				'error'         => __( 'Erro ao carregar dados', 'person-cash-wallet' ),
				'noData'        => __( 'Nenhum dado disponível', 'person-cash-wallet' ),
				'exportSuccess' => __( 'Relatório exportado com sucesso!', 'person-cash-wallet' ),
			),
		) );
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		$automation_id = isset( $_GET['automation_id'] ) ? absint( $_GET['automation_id'] ) : 0;

		if ( ! $automation_id ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'ID da automação inválido', 'person-cash-wallet' ) . '</p></div>';
			return;
		}

		$automation = PCW_Automations::instance()->get( $automation_id );

		if ( ! $automation ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Automação não encontrada', 'person-cash-wallet' ) . '</p></div>';
			return;
		}

		$end_date   = date( 'Y-m-d' );
		$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );

		$analytics = PCW_Automation_Analytics::instance();
		$metrics   = $analytics->get_metrics( $automation_id, $start_date, $end_date );

		$automation_types = PCW_Automations::get_automation_types();
		$type_info        = isset( $automation_types[ $automation->type ] ) ? $automation_types[ $automation->type ] : null;

		$queue_stats    = $this->get_queue_stats( $automation_id );
		$customer_stats = $this->get_customer_stats( $automation );

		$has_whatsapp_step = $this->automation_has_channel( $automation, 'send_whatsapp' );
		$has_email_step    = $this->automation_has_channel( $automation, 'send_email' );

		?>
		<div class="wrap pcw-automation-report-page" data-automation-id="<?php echo esc_attr( $automation_id ); ?>">
			<!-- Header -->
			<div class="pcw-report-header">
				<div class="pcw-report-header-left">
					<h1>
						<?php if ( $type_info ) : ?>
							<span class="dashicons <?php echo esc_attr( $type_info['icon'] ); ?>"></span>
						<?php endif; ?>
						<?php echo esc_html( $automation->name ); ?>
					</h1>
					<p class="pcw-report-subtitle">
						<?php esc_html_e( 'Relatório de Performance', 'person-cash-wallet' ); ?>
						<span class="pcw-automation-status pcw-status-<?php echo esc_attr( $automation->status ); ?>">
							<?php echo esc_html( $automation->status === 'active' ? __( 'Ativa', 'person-cash-wallet' ) : __( 'Inativa', 'person-cash-wallet' ) ); ?>
						</span>
					</p>
				</div>
				<div class="pcw-report-header-right">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-automations&action=edit&id=' . $automation_id ) ); ?>" class="button">
						<span class="dashicons dashicons-edit"></span>
						<?php esc_html_e( 'Editar Automação', 'person-cash-wallet' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-automations' ) ); ?>" class="button">
						<span class="dashicons dashicons-arrow-left-alt"></span>
						<?php esc_html_e( 'Voltar', 'person-cash-wallet' ); ?>
					</a>
				</div>
			</div>

			<!-- Resumo da Automação -->
			<?php $this->render_automation_summary( $automation, $customer_stats ); ?>

			<!-- Métricas de Envio (WhatsApp + Email) -->
			<div class="pcw-metrics-grid" id="pcw-metrics-container">
				<?php $this->render_dispatch_metrics( $metrics, $queue_stats, $customer_stats, $has_whatsapp_step, $has_email_step ); ?>
			</div>

			<!-- Filtros -->
			<div class="pcw-report-filters">
				<div class="pcw-filters-row">
					<div class="pcw-filter-group">
						<label><?php esc_html_e( 'Período:', 'person-cash-wallet' ); ?></label>
						<select id="pcw-period-preset" class="pcw-filter-select">
							<option value="today"><?php esc_html_e( 'Hoje', 'person-cash-wallet' ); ?></option>
							<option value="yesterday"><?php esc_html_e( 'Ontem', 'person-cash-wallet' ); ?></option>
							<option value="last_7_days"><?php esc_html_e( 'Últimos 7 dias', 'person-cash-wallet' ); ?></option>
							<option value="last_30_days" selected><?php esc_html_e( 'Últimos 30 dias', 'person-cash-wallet' ); ?></option>
							<option value="last_90_days"><?php esc_html_e( 'Últimos 90 dias', 'person-cash-wallet' ); ?></option>
							<option value="this_month"><?php esc_html_e( 'Este mês', 'person-cash-wallet' ); ?></option>
							<option value="last_month"><?php esc_html_e( 'Mês passado', 'person-cash-wallet' ); ?></option>
							<option value="custom"><?php esc_html_e( 'Personalizado', 'person-cash-wallet' ); ?></option>
						</select>
					</div>

					<div class="pcw-filter-group pcw-custom-dates" style="display: none;">
						<label><?php esc_html_e( 'De:', 'person-cash-wallet' ); ?></label>
						<input type="date" id="pcw-start-date" class="pcw-filter-input" value="<?php echo esc_attr( $start_date ); ?>">
					</div>

					<div class="pcw-filter-group pcw-custom-dates" style="display: none;">
						<label><?php esc_html_e( 'Até:', 'person-cash-wallet' ); ?></label>
						<input type="date" id="pcw-end-date" class="pcw-filter-input" value="<?php echo esc_attr( $end_date ); ?>">
					</div>

					<div class="pcw-filter-group">
						<label><?php esc_html_e( 'Evento:', 'person-cash-wallet' ); ?></label>
						<select id="pcw-event-type" class="pcw-filter-select">
							<option value=""><?php esc_html_e( 'Todos', 'person-cash-wallet' ); ?></option>
							<?php if ( $has_whatsapp_step ) : ?>
								<option value="whatsapp_queued"><?php esc_html_e( 'WhatsApp na Fila', 'person-cash-wallet' ); ?></option>
								<option value="whatsapp_sent"><?php esc_html_e( 'WhatsApp Enviado', 'person-cash-wallet' ); ?></option>
								<option value="whatsapp_failed"><?php esc_html_e( 'WhatsApp Falhou', 'person-cash-wallet' ); ?></option>
							<?php endif; ?>
							<?php if ( $has_email_step ) : ?>
								<option value="email_sent"><?php esc_html_e( 'Email Enviado', 'person-cash-wallet' ); ?></option>
								<option value="email_opened"><?php esc_html_e( 'Email Aberto', 'person-cash-wallet' ); ?></option>
								<option value="email_clicked"><?php esc_html_e( 'Email Clicado', 'person-cash-wallet' ); ?></option>
							<?php endif; ?>
							<option value="conversion"><?php esc_html_e( 'Conversão', 'person-cash-wallet' ); ?></option>
						</select>
					</div>

					<div class="pcw-filter-group">
						<label><?php esc_html_e( 'Buscar:', 'person-cash-wallet' ); ?></label>
						<input type="text" id="pcw-filter-email" class="pcw-filter-input" placeholder="<?php esc_attr_e( 'Email ou telefone...', 'person-cash-wallet' ); ?>">
					</div>

					<div class="pcw-filter-actions">
						<button type="button" id="pcw-apply-filters" class="button button-primary">
							<span class="dashicons dashicons-search"></span>
							<?php esc_html_e( 'Filtrar', 'person-cash-wallet' ); ?>
						</button>
						<button type="button" id="pcw-reset-filters" class="button">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Limpar', 'person-cash-wallet' ); ?>
						</button>
						<button type="button" id="pcw-export-csv" class="button">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Exportar CSV', 'person-cash-wallet' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Tabs -->
			<div class="pcw-report-tabs">
				<button class="pcw-tab-button active" data-tab="timeline">
					<span class="dashicons dashicons-chart-line"></span>
					<?php esc_html_e( 'Linha do Tempo', 'person-cash-wallet' ); ?>
				</button>
				<?php if ( $has_whatsapp_step ) : ?>
					<button class="pcw-tab-button" data-tab="queue">
						<span class="dashicons dashicons-admin-comments"></span>
						<?php esc_html_e( 'Fila WhatsApp', 'person-cash-wallet' ); ?>
						<?php if ( $queue_stats['pending'] > 0 ) : ?>
							<span class="pcw-tab-badge"><?php echo number_format_i18n( $queue_stats['pending'] ); ?></span>
						<?php endif; ?>
					</button>
				<?php endif; ?>
				<button class="pcw-tab-button" data-tab="events">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'Eventos Detalhados', 'person-cash-wallet' ); ?>
				</button>
				<?php if ( $has_email_step ) : ?>
					<button class="pcw-tab-button" data-tab="links">
						<span class="dashicons dashicons-admin-links"></span>
						<?php esc_html_e( 'Links Mais Clicados', 'person-cash-wallet' ); ?>
					</button>
					<button class="pcw-tab-button" data-tab="devices">
						<span class="dashicons dashicons-smartphone"></span>
						<?php esc_html_e( 'Dispositivos', 'person-cash-wallet' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<!-- Tab: Linha do Tempo -->
			<div class="pcw-tab-content active" id="tab-timeline">
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2><?php esc_html_e( 'Performance ao Longo do Tempo', 'person-cash-wallet' ); ?></h2>
					</div>
					<div class="pcw-card-body">
						<canvas id="pcw-timeline-chart" height="80"></canvas>
					</div>
				</div>
			</div>

			<!-- Tab: Fila WhatsApp -->
			<?php if ( $has_whatsapp_step ) : ?>
				<div class="pcw-tab-content" id="tab-queue" style="display: none;">
					<div class="pcw-card">
						<div class="pcw-card-header" style="display: flex; justify-content: space-between; align-items: center;">
							<h2>
								<span class="dashicons dashicons-admin-comments" style="color: #22c55e;"></span>
								<?php esc_html_e( 'Mensagens na Fila WhatsApp', 'person-cash-wallet' ); ?>
							</h2>
							<button type="button" id="pcw-refresh-queue" class="button button-small">
								<span class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Atualizar', 'person-cash-wallet' ); ?>
							</button>
						</div>
						<div class="pcw-card-body">
							<!-- Mini stats da fila -->
							<div class="pcw-queue-mini-stats">
								<div class="pcw-queue-stat pcw-queue-stat-pending">
									<span class="pcw-queue-stat-icon">⏳</span>
									<span class="pcw-queue-stat-value"><?php echo number_format_i18n( $queue_stats['pending'] ); ?></span>
									<span class="pcw-queue-stat-label"><?php esc_html_e( 'Pendentes', 'person-cash-wallet' ); ?></span>
								</div>
								<div class="pcw-queue-stat pcw-queue-stat-scheduled">
									<span class="pcw-queue-stat-icon">📅</span>
									<span class="pcw-queue-stat-value"><?php echo number_format_i18n( $queue_stats['scheduled'] ); ?></span>
									<span class="pcw-queue-stat-label"><?php esc_html_e( 'Agendadas', 'person-cash-wallet' ); ?></span>
								</div>
								<div class="pcw-queue-stat pcw-queue-stat-sent">
									<span class="pcw-queue-stat-icon">✅</span>
									<span class="pcw-queue-stat-value"><?php echo number_format_i18n( $queue_stats['sent'] ); ?></span>
									<span class="pcw-queue-stat-label"><?php esc_html_e( 'Enviadas', 'person-cash-wallet' ); ?></span>
								</div>
								<div class="pcw-queue-stat pcw-queue-stat-failed">
									<span class="pcw-queue-stat-icon">❌</span>
									<span class="pcw-queue-stat-value"><?php echo number_format_i18n( $queue_stats['failed'] ); ?></span>
									<span class="pcw-queue-stat-label"><?php esc_html_e( 'Falharam', 'person-cash-wallet' ); ?></span>
								</div>
								<?php if ( $queue_stats['next_scheduled'] ) : ?>
									<div class="pcw-queue-stat pcw-queue-stat-next">
										<span class="pcw-queue-stat-icon">🕐</span>
										<span class="pcw-queue-stat-value" style="font-size: 14px;">
											<?php echo esc_html( date_i18n( 'd/m H:i', strtotime( $queue_stats['next_scheduled'] ) ) ); ?>
										</span>
										<span class="pcw-queue-stat-label"><?php esc_html_e( 'Próximo envio', 'person-cash-wallet' ); ?></span>
									</div>
								<?php endif; ?>
							</div>

							<div id="pcw-queue-table-container">
								<p class="pcw-loading"><?php esc_html_e( 'Carregando fila...', 'person-cash-wallet' ); ?></p>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<!-- Tab: Eventos -->
			<div class="pcw-tab-content" id="tab-events" style="display: none;">
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2><?php esc_html_e( 'Eventos Detalhados', 'person-cash-wallet' ); ?></h2>
					</div>
					<div class="pcw-card-body">
						<div id="pcw-events-table-container">
							<p class="pcw-loading"><?php esc_html_e( 'Carregando eventos...', 'person-cash-wallet' ); ?></p>
						</div>
					</div>
				</div>
			</div>

			<?php if ( $has_email_step ) : ?>
				<!-- Tab: Links -->
				<div class="pcw-tab-content" id="tab-links" style="display: none;">
					<div class="pcw-card">
						<div class="pcw-card-header">
							<h2><?php esc_html_e( 'Top 10 Links Mais Clicados', 'person-cash-wallet' ); ?></h2>
						</div>
						<div class="pcw-card-body">
							<div id="pcw-top-links-container">
								<p class="pcw-loading"><?php esc_html_e( 'Carregando links...', 'person-cash-wallet' ); ?></p>
							</div>
						</div>
					</div>
				</div>

				<!-- Tab: Dispositivos -->
				<div class="pcw-tab-content" id="tab-devices" style="display: none;">
					<div class="pcw-metrics-grid pcw-grid-2">
						<div class="pcw-card">
							<div class="pcw-card-header">
								<h2><?php esc_html_e( 'Dispositivos', 'person-cash-wallet' ); ?></h2>
							</div>
							<div class="pcw-card-body">
								<canvas id="pcw-devices-chart"></canvas>
							</div>
						</div>
						<div class="pcw-card">
							<div class="pcw-card-header">
								<h2><?php esc_html_e( 'Clientes de Email', 'person-cash-wallet' ); ?></h2>
							</div>
							<div class="pcw-card-body">
								<canvas id="pcw-email-clients-chart"></canvas>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Verificar se a automação tem um canal específico nas etapas
	 *
	 * @param object $automation Automação.
	 * @param string $action_type Tipo de ação (send_whatsapp, send_email).
	 * @return bool
	 */
	private function automation_has_channel( $automation, $action_type ) {
		if ( empty( $automation->workflow_steps ) || ! is_array( $automation->workflow_steps ) ) {
			return false;
		}

		foreach ( $automation->workflow_steps as $step ) {
			$step_type = isset( $step['action'] ) ? $step['action'] : ( isset( $step['type'] ) ? $step['type'] : '' );
			if ( $step_type === $action_type ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Renderizar resumo da automação
	 *
	 * @param object $automation Automação.
	 * @param array  $customer_stats Estatísticas de clientes.
	 */
	private function render_automation_summary( $automation, $customer_stats ) {
		$config = is_array( $automation->trigger_config ) ? $automation->trigger_config : array();
		$types  = PCW_Automations::get_automation_types();
		$type_info = isset( $types[ $automation->type ] ) ? $types[ $automation->type ] : null;
		?>
		<div class="pcw-summary-grid">
			<!-- Info da Automação -->
			<div class="pcw-summary-card">
				<h3>
					<span class="dashicons dashicons-info-outline"></span>
					<?php esc_html_e( 'Informações', 'person-cash-wallet' ); ?>
				</h3>
				<div class="pcw-summary-items">
					<div class="pcw-summary-item">
						<span class="pcw-summary-label"><?php esc_html_e( 'Tipo', 'person-cash-wallet' ); ?></span>
						<span class="pcw-summary-value"><?php echo $type_info ? esc_html( $type_info['name'] ) : esc_html( $automation->type ); ?></span>
					</div>
					<div class="pcw-summary-item">
						<span class="pcw-summary-label"><?php esc_html_e( 'Gatilho', 'person-cash-wallet' ); ?></span>
						<span class="pcw-summary-value"><?php echo esc_html( $automation->trigger_type ); ?></span>
					</div>
					<?php if ( ! empty( $config['inactive_days'] ) ) : ?>
						<div class="pcw-summary-item">
							<span class="pcw-summary-label"><?php esc_html_e( 'Inatividade', 'person-cash-wallet' ); ?></span>
							<span class="pcw-summary-value">
								<?php printf( esc_html__( '%d dias sem comprar', 'person-cash-wallet' ), absint( $config['inactive_days'] ) ); ?>
							</span>
						</div>
					<?php endif; ?>
					<div class="pcw-summary-item">
						<span class="pcw-summary-label"><?php esc_html_e( 'Criada em', 'person-cash-wallet' ); ?></span>
						<span class="pcw-summary-value"><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $automation->created_at ) ) ); ?></span>
					</div>
					<?php if ( ! empty( $automation->last_run ) ) : ?>
						<div class="pcw-summary-item">
							<span class="pcw-summary-label"><?php esc_html_e( 'Última execução', 'person-cash-wallet' ); ?></span>
							<span class="pcw-summary-value"><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $automation->last_run ) ) ); ?></span>
						</div>
					<?php endif; ?>
					<div class="pcw-summary-item">
						<span class="pcw-summary-label"><?php esc_html_e( 'Etapas', 'person-cash-wallet' ); ?></span>
						<span class="pcw-summary-value">
							<?php
							$step_icons = array();
							if ( ! empty( $automation->workflow_steps ) ) {
								foreach ( $automation->workflow_steps as $step ) {
									$st = isset( $step['action'] ) ? $step['action'] : ( isset( $step['type'] ) ? $step['type'] : '' );
									switch ( $st ) {
										case 'send_whatsapp':
											$step_icons[] = '💬 WhatsApp';
											break;
										case 'send_email':
											$step_icons[] = '📧 Email';
											break;
										case 'delay':
											$step_icons[] = '⏱️ Espera';
											break;
										case 'condition':
											$step_icons[] = '🔀 Condição';
											break;
										default:
											$step_icons[] = $st;
									}
								}
							}
							echo esc_html( implode( ' → ', $step_icons ) );
							?>
						</span>
					</div>
				</div>
			</div>

			<!-- Configuração de Pacing (se for recuperação de clientes) -->
			<?php if ( in_array( $automation->type, array( 'customer_recovery', 'inactive_customer' ), true ) || $automation->trigger_type === 'inactive_customer' ) : ?>
				<div class="pcw-summary-card">
					<h3>
						<span class="dashicons dashicons-groups"></span>
						<?php esc_html_e( 'Clientes Alvo', 'person-cash-wallet' ); ?>
					</h3>
					<div class="pcw-summary-items">
						<div class="pcw-summary-item">
							<span class="pcw-summary-label"><?php esc_html_e( 'Total elegíveis', 'person-cash-wallet' ); ?></span>
							<span class="pcw-summary-value pcw-value-lg"><?php echo number_format_i18n( $customer_stats['total_eligible'] ); ?></span>
						</div>
						<div class="pcw-summary-item">
							<span class="pcw-summary-label"><?php esc_html_e( 'Já notificados', 'person-cash-wallet' ); ?></span>
							<span class="pcw-summary-value"><?php echo number_format_i18n( $customer_stats['already_notified'] ); ?></span>
						</div>
						<div class="pcw-summary-item">
							<span class="pcw-summary-label"><?php esc_html_e( 'Restantes', 'person-cash-wallet' ); ?></span>
							<span class="pcw-summary-value pcw-value-highlight"><?php echo number_format_i18n( $customer_stats['remaining'] ); ?></span>
						</div>
						<?php if ( ! empty( $config['include_historical'] ) && $config['include_historical'] === '1' ) : ?>
							<div class="pcw-summary-item">
								<span class="pcw-summary-label"><?php esc_html_e( 'Históricos incluídos', 'person-cash-wallet' ); ?></span>
								<span class="pcw-summary-value" style="color: #22c55e;">✓ <?php esc_html_e( 'Sim', 'person-cash-wallet' ); ?></span>
							</div>
							<?php if ( ! empty( $config['batch_per_day'] ) ) : ?>
								<div class="pcw-summary-item">
									<span class="pcw-summary-label"><?php esc_html_e( 'Ritmo de envio', 'person-cash-wallet' ); ?></span>
									<span class="pcw-summary-value">
										<?php printf( esc_html__( '%d/dia', 'person-cash-wallet' ), absint( $config['batch_per_day'] ) ); ?>
										<?php if ( ! empty( $config['batch_days'] ) ) : ?>
											<?php printf( esc_html__( ' por %d dias', 'person-cash-wallet' ), absint( $config['batch_days'] ) ); ?>
										<?php endif; ?>
									</span>
								</div>
								<?php
								if ( $customer_stats['remaining'] > 0 && ! empty( $config['batch_per_day'] ) ) {
									$days_needed = ceil( $customer_stats['remaining'] / absint( $config['batch_per_day'] ) );
									$estimated_end = date_i18n( 'd/m/Y', strtotime( "+{$days_needed} days" ) );
								}
								?>
								<?php if ( ! empty( $days_needed ) ) : ?>
									<div class="pcw-summary-item">
										<span class="pcw-summary-label"><?php esc_html_e( 'Previsão conclusão', 'person-cash-wallet' ); ?></span>
										<span class="pcw-summary-value">
											~<?php echo esc_html( $days_needed ); ?> <?php esc_html_e( 'dias', 'person-cash-wallet' ); ?>
											<span style="font-size: 12px; color: #64748b;">(<?php echo esc_html( $estimated_end ); ?>)</span>
										</span>
									</div>
								<?php endif; ?>
							<?php endif; ?>
						<?php else : ?>
							<div class="pcw-summary-item">
								<span class="pcw-summary-label"><?php esc_html_e( 'Históricos incluídos', 'person-cash-wallet' ); ?></span>
								<span class="pcw-summary-value" style="color: #94a3b8;">✗ <?php esc_html_e( 'Não', 'person-cash-wallet' ); ?></span>
							</div>
						<?php endif; ?>

						<?php if ( $customer_stats['total_eligible'] > 0 ) : ?>
							<div class="pcw-summary-progress">
								<?php
								$progress = $customer_stats['already_notified'] > 0
									? min( 100, round( ( $customer_stats['already_notified'] / $customer_stats['total_eligible'] ) * 100 ) )
									: 0;
								?>
								<div class="pcw-progress-bar">
									<div class="pcw-progress-fill" style="width: <?php echo esc_attr( $progress ); ?>%;"></div>
								</div>
								<span class="pcw-progress-text"><?php echo esc_html( $progress ); ?>% <?php esc_html_e( 'concluído', 'person-cash-wallet' ); ?></span>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Execuções -->
			<div class="pcw-summary-card">
				<h3>
					<span class="dashicons dashicons-chart-bar"></span>
					<?php esc_html_e( 'Execuções', 'person-cash-wallet' ); ?>
				</h3>
				<div class="pcw-summary-items">
					<?php
					$exec_stats = $this->get_execution_stats( $automation->id );
					?>
					<div class="pcw-summary-item">
						<span class="pcw-summary-label"><?php esc_html_e( 'Total', 'person-cash-wallet' ); ?></span>
						<span class="pcw-summary-value pcw-value-lg"><?php echo number_format_i18n( $exec_stats['total'] ); ?></span>
					</div>
					<div class="pcw-summary-item">
						<span class="pcw-summary-label"><?php esc_html_e( 'Completas', 'person-cash-wallet' ); ?></span>
						<span class="pcw-summary-value" style="color: #22c55e;"><?php echo number_format_i18n( $exec_stats['completed'] ); ?></span>
					</div>
					<div class="pcw-summary-item">
						<span class="pcw-summary-label"><?php esc_html_e( 'Pendentes', 'person-cash-wallet' ); ?></span>
						<span class="pcw-summary-value" style="color: #f59e0b;"><?php echo number_format_i18n( $exec_stats['pending'] ); ?></span>
					</div>
					<div class="pcw-summary-item">
						<span class="pcw-summary-label"><?php esc_html_e( 'Falharam', 'person-cash-wallet' ); ?></span>
						<span class="pcw-summary-value" style="color: #ef4444;"><?php echo number_format_i18n( $exec_stats['failed'] ); ?></span>
					</div>
					<div class="pcw-summary-item">
						<span class="pcw-summary-label"><?php esc_html_e( 'Hoje', 'person-cash-wallet' ); ?></span>
						<span class="pcw-summary-value"><?php echo number_format_i18n( $exec_stats['today'] ); ?></span>
					</div>

					<?php
					// Mostrar próxima execução do cron
					$next_cron = wp_next_scheduled( 'pcw_check_inactive_customers' );
					?>
					<div class="pcw-summary-item" style="margin-top: 6px; padding-top: 10px; border-top: 1px solid #f1f5f9;">
						<span class="pcw-summary-label"><?php esc_html_e( 'Próximo cron', 'person-cash-wallet' ); ?></span>
						<span class="pcw-summary-value" style="font-size: 12px;">
							<?php
							if ( $next_cron ) {
								echo esc_html( date_i18n( 'd/m H:i', $next_cron ) );
							} else {
								esc_html_e( 'Não agendado', 'person-cash-wallet' );
							}
							?>
						</span>
					</div>
				</div>

			<div style="margin-top: 16px; padding-top: 12px; border-top: 1px solid #f1f5f9;">
				<button type="button"
					id="pcw-run-automation-now"
					class="button button-primary"
					data-automation-id="<?php echo esc_attr( $automation->id ); ?>"
					style="width: 100%; justify-content: center;">
						<span class="dashicons dashicons-controls-play"></span>
						<?php esc_html_e( 'Executar agora', 'person-cash-wallet' ); ?>
					</button>
					<p style="font-size: 11px; color: #94a3b8; margin: 6px 0 0; text-align: center;">
						<?php esc_html_e( 'Processa o lote do dia imediatamente', 'person-cash-wallet' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderizar métricas de envio (WhatsApp + Email)
	 *
	 * @param array $metrics Métricas de analytics.
	 * @param array $queue_stats Estatísticas da fila.
	 * @param array $customer_stats Estatísticas de clientes.
	 * @param bool  $has_whatsapp Se tem etapa WhatsApp.
	 * @param bool  $has_email Se tem etapa Email.
	 */
	private function render_dispatch_metrics( $metrics, $queue_stats, $customer_stats, $has_whatsapp, $has_email ) {
		?>
		<!-- Disparos Totais -->
		<div class="pcw-metric-card" data-metric="executions">
			<div class="pcw-metric-icon" style="background: #667eea;">
				<span class="dashicons dashicons-controls-repeat"></span>
			</div>
			<div class="pcw-metric-content">
				<div class="pcw-metric-label"><?php esc_html_e( 'Disparos', 'person-cash-wallet' ); ?></div>
				<div class="pcw-metric-value"><?php echo number_format_i18n( $metrics['executions'] ); ?></div>
			</div>
		</div>

		<?php if ( $has_whatsapp ) : ?>
			<!-- WhatsApp na Fila -->
			<div class="pcw-metric-card" data-metric="whatsapp_pending">
				<div class="pcw-metric-icon" style="background: #f59e0b;">
					<span class="dashicons dashicons-clock"></span>
				</div>
				<div class="pcw-metric-content">
					<div class="pcw-metric-label"><?php esc_html_e( 'Na Fila', 'person-cash-wallet' ); ?></div>
					<div class="pcw-metric-value"><?php echo number_format_i18n( $queue_stats['pending'] + $queue_stats['scheduled'] ); ?></div>
					<div class="pcw-metric-subtext">
						<?php echo number_format_i18n( $queue_stats['pending'] ); ?> <?php esc_html_e( 'pendentes', 'person-cash-wallet' ); ?> +
						<?php echo number_format_i18n( $queue_stats['scheduled'] ); ?> <?php esc_html_e( 'agendadas', 'person-cash-wallet' ); ?>
					</div>
				</div>
			</div>

			<!-- WhatsApp Enviados -->
			<div class="pcw-metric-card" data-metric="whatsapp_sent">
				<div class="pcw-metric-icon" style="background: #22c55e;">
					<span class="dashicons dashicons-admin-comments"></span>
				</div>
				<div class="pcw-metric-content">
					<div class="pcw-metric-label"><?php esc_html_e( 'WhatsApp Enviados', 'person-cash-wallet' ); ?></div>
					<div class="pcw-metric-value"><?php echo number_format_i18n( $queue_stats['sent'] ); ?></div>
					<?php if ( $queue_stats['failed'] > 0 ) : ?>
						<div class="pcw-metric-subtext" style="color: #ef4444;">
							<?php echo number_format_i18n( $queue_stats['failed'] ); ?> <?php esc_html_e( 'falharam', 'person-cash-wallet' ); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $has_email ) : ?>
			<!-- Emails Enviados -->
			<div class="pcw-metric-card" data-metric="emails_sent">
				<div class="pcw-metric-icon" style="background: #3b82f6;">
					<span class="dashicons dashicons-email"></span>
				</div>
				<div class="pcw-metric-content">
					<div class="pcw-metric-label"><?php esc_html_e( 'Emails Enviados', 'person-cash-wallet' ); ?></div>
					<div class="pcw-metric-value"><?php echo number_format_i18n( $metrics['emails_sent'] ); ?></div>
					<div class="pcw-metric-subtext"><?php echo esc_html( $metrics['delivery_rate'] ); ?>%</div>
				</div>
			</div>

			<!-- Abertos -->
			<div class="pcw-metric-card" data-metric="emails_opened">
				<div class="pcw-metric-icon" style="background: #10b981;">
					<span class="dashicons dashicons-visibility"></span>
				</div>
				<div class="pcw-metric-content">
					<div class="pcw-metric-label"><?php esc_html_e( 'Abertos', 'person-cash-wallet' ); ?></div>
					<div class="pcw-metric-value"><?php echo number_format_i18n( $metrics['emails_opened'] ); ?></div>
					<div class="pcw-metric-subtext"><?php echo esc_html( $metrics['open_rate'] ); ?>%</div>
				</div>
			</div>
		<?php endif; ?>

		<!-- Conversões -->
		<div class="pcw-metric-card" data-metric="conversions">
			<div class="pcw-metric-icon" style="background: #8b5cf6;">
				<span class="dashicons dashicons-cart"></span>
			</div>
			<div class="pcw-metric-content">
				<div class="pcw-metric-label"><?php esc_html_e( 'Conversões', 'person-cash-wallet' ); ?></div>
				<div class="pcw-metric-value"><?php echo number_format_i18n( $metrics['conversions'] ); ?></div>
				<div class="pcw-metric-subtext"><?php echo esc_html( $metrics['conversion_rate'] ); ?>%</div>
			</div>
		</div>

		<!-- Receita -->
		<div class="pcw-metric-card pcw-metric-highlight" data-metric="revenue">
			<div class="pcw-metric-icon" style="background: rgba(255,255,255,0.2);">
				<span class="dashicons dashicons-money-alt"></span>
			</div>
			<div class="pcw-metric-content">
				<div class="pcw-metric-label"><?php esc_html_e( 'Receita Atribuída', 'person-cash-wallet' ); ?></div>
				<div class="pcw-metric-value"><?php echo wc_price( $metrics['revenue'] ); ?></div>
				<div class="pcw-metric-subtext">
					<?php
					printf(
						esc_html__( 'Média: %s', 'person-cash-wallet' ),
						wc_price( $metrics['avg_order_value'] )
					);
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Obter estatísticas da fila de mensagens para esta automação
	 *
	 * @param int $automation_id ID da automação.
	 * @return array
	 */
	private function get_queue_stats( $automation_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_message_queue';

		$stats = array(
			'pending'        => 0,
			'scheduled'      => 0,
			'sent'           => 0,
			'failed'         => 0,
			'total'          => 0,
			'next_scheduled' => null,
			'sent_today'     => 0,
		);

		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
		if ( ! $table_exists ) {
			return $stats;
		}

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT status, COUNT(*) as cnt FROM {$table} WHERE automation_id = %d AND type IN ('whatsapp', 'whatsapp_template') GROUP BY status",
			$automation_id
		) );

		foreach ( $results as $row ) {
			switch ( $row->status ) {
				case 'pending':
					$stats['pending'] = absint( $row->cnt );
					break;
				case 'scheduled':
					$stats['scheduled'] = absint( $row->cnt );
					break;
				case 'sent':
					$stats['sent'] = absint( $row->cnt );
					break;
				case 'failed':
					$stats['failed'] = absint( $row->cnt );
					break;
			}
			$stats['total'] += absint( $row->cnt );
		}

		$stats['next_scheduled'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT MIN(scheduled_at) FROM {$table} WHERE automation_id = %d AND type IN ('whatsapp', 'whatsapp_template') AND status IN ('pending', 'scheduled') AND scheduled_at > NOW()",
			$automation_id
		) );

		$stats['sent_today'] = absint( $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE automation_id = %d AND type IN ('whatsapp', 'whatsapp_template') AND status = 'sent' AND processed_at >= %s",
			$automation_id,
			date( 'Y-m-d 00:00:00' )
		) ) );

		return $stats;
	}

	/**
	 * Obter estatísticas de clientes alvo para automação de recuperação
	 *
	 * @param object $automation Automação.
	 * @return array
	 */
	private function get_customer_stats( $automation ) {
		global $wpdb;

		$stats = array(
			'total_eligible'    => 0,
			'already_notified'  => 0,
			'remaining'         => 0,
		);

		if ( ! in_array( $automation->trigger_type, array( 'inactive_customer', 'customer_recovery' ), true ) ) {
			return $stats;
		}

		$config        = is_array( $automation->trigger_config ) ? $automation->trigger_config : array();
		$inactive_days = isset( $config['inactive_days'] ) ? absint( $config['inactive_days'] ) : 90;
		$since         = date( 'Y-m-d', strtotime( "-{$inactive_days} days" ) );

		$orders_table = $wpdb->prefix . 'wc_orders';
		$use_hpos     = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		if ( $use_hpos && $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $orders_table ) ) ) {
			$stats['total_eligible'] = absint( $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT customer_id) FROM {$orders_table}
				WHERE status IN ('wc-completed', 'wc-processing')
				AND customer_id > 0
				AND customer_id NOT IN (
					SELECT DISTINCT customer_id FROM {$orders_table}
					WHERE status IN ('wc-completed', 'wc-processing')
					AND date_created_gmt >= %s
				)
				HAVING COUNT(*) > 0",
				$since
			) ) );
		} else {
			$stats['total_eligible'] = absint( $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT pm.meta_value) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_user'
				WHERE p.post_type = 'shop_order'
				AND p.post_status IN ('wc-completed', 'wc-processing')
				AND pm.meta_value > 0
				AND pm.meta_value NOT IN (
					SELECT DISTINCT pm2.meta_value FROM {$wpdb->posts} p2
					INNER JOIN {$wpdb->postmeta} pm2 ON p2.ID = pm2.post_id AND pm2.meta_key = '_customer_user'
					WHERE p2.post_type = 'shop_order'
					AND p2.post_status IN ('wc-completed', 'wc-processing')
					AND p2.post_date >= %s
				)",
				$since
			) ) );
		}

		$executions_table = $wpdb->prefix . 'pcw_automation_executions';
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $executions_table ) ) ) {
			$stats['already_notified'] = absint( $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$executions_table} WHERE automation_id = %d AND status IN ('completed', 'processing', 'pending', 'running')",
				$automation->id
			) ) );
		}

		$stats['remaining'] = max( 0, $stats['total_eligible'] - $stats['already_notified'] );

		return $stats;
	}

	/**
	 * Obter estatísticas de execuções
	 *
	 * @param int $automation_id ID da automação.
	 * @return array
	 */
	private function get_execution_stats( $automation_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_automation_executions';

		$stats = array(
			'total'     => 0,
			'completed' => 0,
			'pending'   => 0,
			'failed'    => 0,
			'skipped'   => 0,
			'today'     => 0,
		);

		if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) ) {
			return $stats;
		}

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT status, COUNT(*) as cnt FROM {$table} WHERE automation_id = %d GROUP BY status",
			$automation_id
		) );

		foreach ( $results as $row ) {
			$stats['total'] += absint( $row->cnt );
			$key = sanitize_key( $row->status );
			if ( isset( $stats[ $key ] ) ) {
				$stats[ $key ] = absint( $row->cnt );
			}
		}

		$stats['today'] = absint( $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE automation_id = %d AND executed_at >= %s",
			$automation_id,
			date( 'Y-m-d 00:00:00' )
		) ) );

		return $stats;
	}

	/**
	 * AJAX: Obter métricas
	 */
	public function ajax_get_metrics() {
		check_ajax_referer( 'pcw_reports', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$automation_id = isset( $_POST['automation_id'] ) ? absint( $_POST['automation_id'] ) : 0;
		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : null;
		$end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : null;

		$analytics = PCW_Automation_Analytics::instance();
		$metrics = $analytics->get_metrics( $automation_id, $start_date, $end_date );

		// Obter dados para gráficos
		$timeline = $analytics->get_timeline_data( $automation_id, $start_date, $end_date, 'day' );
		$top_links = $analytics->get_top_links( $automation_id, $start_date, $end_date, 10 );
		$devices = $analytics->get_device_distribution( $automation_id, $start_date, $end_date );
		$email_clients = $analytics->get_email_client_distribution( $automation_id, $start_date, $end_date );

		wp_send_json_success( array(
			'metrics'       => $metrics,
			'timeline'      => $timeline,
			'top_links'     => $top_links,
			'devices'       => $devices,
			'email_clients' => $email_clients,
		) );
	}

	/**
	 * AJAX: Obter eventos
	 */
	public function ajax_get_events() {
		check_ajax_referer( 'pcw_reports', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$automation_id = isset( $_POST['automation_id'] ) ? absint( $_POST['automation_id'] ) : 0;
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;

		$filters = array(
			'start_date' => isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : null,
			'end_date'   => isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : null,
			'event_type' => isset( $_POST['event_type'] ) ? sanitize_text_field( $_POST['event_type'] ) : null,
			'email'      => isset( $_POST['email'] ) ? sanitize_text_field( $_POST['email'] ) : null,
		);

		$analytics = PCW_Automation_Analytics::instance();
		$data = $analytics->get_events( $automation_id, $filters, $page, $per_page );

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Exportar relatório
	 */
	public function ajax_export_report() {
		check_ajax_referer( 'pcw_reports', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$automation_id = isset( $_POST['automation_id'] ) ? absint( $_POST['automation_id'] ) : 0;
		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : null;
		$end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : null;

		$filters = array(
			'start_date' => $start_date,
			'end_date'   => $end_date,
			'event_type' => isset( $_POST['event_type'] ) ? sanitize_text_field( $_POST['event_type'] ) : null,
			'email'      => isset( $_POST['email'] ) ? sanitize_text_field( $_POST['email'] ) : null,
		);

		$analytics = PCW_Automation_Analytics::instance();
		$automation = PCW_Automations::instance()->get( $automation_id );

		// Buscar todos os eventos (sem paginação)
		$data = $analytics->get_events( $automation_id, $filters, 1, 10000 );

		// Gerar CSV
		$filename = 'automacao-' . sanitize_title( $automation->name ) . '-' . date( 'Y-m-d' ) . '.csv';
		
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// BOM para UTF-8
		fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );

		// Cabeçalhos
		fputcsv( $output, array(
			'Data/Hora',
			'Tipo de Evento',
			'Cliente',
			'Email',
			'Detalhes',
		), ';' );

		// Dados
		foreach ( $data['events'] as $event ) {
			$event_type_labels = array(
				'email_sent'    => 'Email Enviado',
				'email_opened'  => 'Email Aberto',
				'email_clicked' => 'Email Clicado',
				'conversion'    => 'Conversão',
			);

			$details = '';
			if ( ! empty( $event->metadata ) ) {
				if ( isset( $event->metadata['order_total'] ) ) {
					$details = 'R$ ' . number_format( $event->metadata['order_total'], 2, ',', '.' );
				} elseif ( isset( $event->metadata['link_text'] ) ) {
					$details = $event->metadata['link_text'];
				}
			}

			fputcsv( $output, array(
				date_i18n( 'd/m/Y H:i:s', strtotime( $event->created_at ) ),
				isset( $event_type_labels[ $event->event_type ] ) ? $event_type_labels[ $event->event_type ] : $event->event_type,
				isset( $event->user_name ) ? $event->user_name : '',
				$event->email,
				$details,
			), ';' );
		}

		fclose( $output );
		exit;
	}

	/**
	 * AJAX: Obter mensagens da fila para esta automação
	 */
	public function ajax_get_queue() {
		check_ajax_referer( 'pcw_reports', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		global $wpdb;

		$automation_id = isset( $_POST['automation_id'] ) ? absint( $_POST['automation_id'] ) : 0;
		$page          = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page      = 20;
		$status_filter = isset( $_POST['queue_status'] ) ? sanitize_text_field( $_POST['queue_status'] ) : '';
		$offset        = ( $page - 1 ) * $per_page;

		$table = $wpdb->prefix . 'pcw_message_queue';

		$where = $wpdb->prepare( "automation_id = %d AND type IN ('whatsapp', 'whatsapp_template')", $automation_id );
		if ( ! empty( $status_filter ) ) {
			$where .= $wpdb->prepare( " AND status = %s", $status_filter );
		}

		$total = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ) );

		$messages = $wpdb->get_results(
			"SELECT id, to_number, contact_name, from_number, status, scheduled_at, processed_at, error_message, created_at
			FROM {$table}
			WHERE {$where}
			ORDER BY
				CASE status
					WHEN 'pending' THEN 1
					WHEN 'scheduled' THEN 2
					WHEN 'processing' THEN 3
					WHEN 'sent' THEN 4
					WHEN 'failed' THEN 5
				END,
				COALESCE(scheduled_at, created_at) DESC
			LIMIT {$per_page} OFFSET {$offset}"
		);

		$formatted = array();
		foreach ( $messages as $msg ) {
			$user_name = '';
			if ( $msg->contact_name ) {
				$user_name = $msg->contact_name;
			} elseif ( $msg->to_number ) {
				$user = get_users( array(
					'meta_key'   => 'billing_phone',
					'meta_value' => $msg->to_number,
					'number'     => 1,
					'fields'     => array( 'display_name' ),
				) );
				if ( ! empty( $user ) ) {
					$user_name = $user[0]->display_name;
				}
			}

			$formatted[] = array(
				'id'           => $msg->id,
				'phone'        => $msg->to_number,
				'name'         => $user_name,
				'from_number'  => $msg->from_number,
				'status'       => $msg->status,
				'scheduled_at' => $msg->scheduled_at ? date_i18n( 'd/m/Y H:i', strtotime( $msg->scheduled_at ) ) : '-',
				'sent_at'      => $msg->processed_at ? date_i18n( 'd/m/Y H:i', strtotime( $msg->processed_at ) ) : '-',
				'error'        => $msg->error_message,
				'created_at'   => date_i18n( 'd/m/Y H:i', strtotime( $msg->created_at ) ),
			);
		}

		$stats = $this->get_queue_stats( $automation_id );

		wp_send_json_success( array(
			'messages'    => $formatted,
			'total'       => $total,
			'page'        => $page,
			'total_pages' => ceil( $total / $per_page ),
			'stats'       => $stats,
		) );
	}
}
