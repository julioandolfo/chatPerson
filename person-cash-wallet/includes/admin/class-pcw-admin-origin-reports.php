<?php
/**
 * Relatórios de Origem de Usuários (Admin)
 *
 * Exibe estatísticas e relatórios sobre a origem dos visitantes e pedidos.
 *
 * @package PersonCashWallet
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de relatórios de origem
 */
class PCW_Admin_Origin_Reports {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Admin_Origin_Reports
	 */
	private static $instance = null;

	/**
	 * Obter instância singleton
	 *
	 * @return PCW_Admin_Origin_Reports
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor privado
	 */
	private function __construct() {
		// Singleton
	}

	/**
	 * Inicializar hooks
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_submenu_page' ), 25 );
		add_action( 'wp_ajax_pcw_get_origin_report', array( $this, 'ajax_get_report' ) );
		add_action( 'wp_ajax_pcw_get_order_attribution', array( $this, 'ajax_get_order_attribution' ) );
		add_action( 'wp_ajax_pcw_sync_wc_attributions', array( $this, 'ajax_sync_wc_attributions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Adicionar meta box nos pedidos
		add_action( 'add_meta_boxes', array( $this, 'add_order_meta_box' ) );
	}

	/**
	 * Adicionar submenu
	 */
	public function add_submenu_page() {
		add_submenu_page(
			'pcw-dashboard',
			__( 'Origens de Tráfego', 'person-cash-wallet' ),
			__( 'Origens', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-origin-reports',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue scripts
	 *
	 * @param string $hook Hook da página.
	 */
	public function enqueue_scripts( $hook ) {
		// Verificar se é a página de origin reports (o hook pode variar)
		if ( strpos( $hook, 'pcw-origin-reports' ) === false ) {
			return;
		}

		wp_enqueue_style( 'pcw-admin-global' );

		wp_enqueue_script(
			'pcw-admin-origin-reports',
			PCW_PLUGIN_URL . 'assets/js/admin-origin-reports.js',
			array( 'jquery', 'wp-util' ),
			PCW_VERSION,
			true
		);

		wp_localize_script( 'pcw-admin-origin-reports', 'pcwOriginReports', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pcw_admin' ),
		) );
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		$period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : '30days';
		$stats = PCW_User_Origin_Tracker::instance()->get_stats( $period );
		?>
		<div class="wrap pcw-admin-wrap">
			<h1 class="wp-heading-inline">
				<span class="dashicons dashicons-chart-area" style="margin-right: 10px;"></span>
				<?php esc_html_e( 'Relatórios de Origem', 'person-cash-wallet' ); ?>
			</h1>

			<!-- Filtro de período -->
			<div class="pcw-period-filter" style="margin: 20px 0; display: flex; align-items: center; gap: 15px;">
				<form method="get" style="margin: 0;">
					<input type="hidden" name="page" value="pcw-origin-reports">
					<select name="period" onchange="this.form.submit()">
						<option value="today" <?php selected( $period, 'today' ); ?>><?php esc_html_e( 'Hoje', 'person-cash-wallet' ); ?></option>
						<option value="7days" <?php selected( $period, '7days' ); ?>><?php esc_html_e( 'Últimos 7 dias', 'person-cash-wallet' ); ?></option>
						<option value="30days" <?php selected( $period, '30days' ); ?>><?php esc_html_e( 'Últimos 30 dias', 'person-cash-wallet' ); ?></option>
						<option value="90days" <?php selected( $period, '90days' ); ?>><?php esc_html_e( 'Últimos 90 dias', 'person-cash-wallet' ); ?></option>
					</select>
				</form>

				<button type="button" id="pcw-sync-wc-attributions" class="button">
					<span class="dashicons dashicons-update" style="margin-top: 4px;"></span>
					<?php esc_html_e( 'Sincronizar Atribuições WooCommerce', 'person-cash-wallet' ); ?>
				</button>
				<span id="pcw-sync-result" style="font-size: 13px;"></span>
			</div>

			<!-- Cards de resumo -->
			<div class="pcw-stats-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
				<div class="pcw-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
					<h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Total de Sessões', 'person-cash-wallet' ); ?></h3>
					<p style="margin: 0; font-size: 32px; font-weight: bold; color: #667eea;"><?php echo esc_html( number_format_i18n( $stats['total_sessions'] ?? 0 ) ); ?></p>
				</div>

				<div class="pcw-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
					<h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Sessões Únicas', 'person-cash-wallet' ); ?></h3>
					<p style="margin: 0; font-size: 32px; font-weight: bold; color: #22c55e;"><?php echo esc_html( number_format_i18n( $stats['unique_sessions'] ?? 0 ) ); ?></p>
				</div>

				<div class="pcw-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
					<h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Primeiras Visitas', 'person-cash-wallet' ); ?></h3>
					<p style="margin: 0; font-size: 32px; font-weight: bold; color: #f59e0b;"><?php echo esc_html( number_format_i18n( $stats['first_visits'] ?? 0 ) ); ?></p>
				</div>

				<div class="pcw-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
					<h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Pedidos Atribuídos', 'person-cash-wallet' ); ?></h3>
					<p style="margin: 0; font-size: 32px; font-weight: bold; color: #ec4899;"><?php echo esc_html( number_format_i18n( $stats['total_orders_attributed'] ?? 0 ) ); ?></p>
				</div>
			</div>

			<!-- Abas de relatórios -->
			<div class="pcw-origin-tabs" style="margin-bottom: 20px;">
				<nav class="nav-tab-wrapper">
					<a href="#channels" class="nav-tab nav-tab-active" data-tab="channels"><?php esc_html_e( 'Por Canal', 'person-cash-wallet' ); ?></a>
					<a href="#sources" class="nav-tab" data-tab="sources"><?php esc_html_e( 'Por Fonte', 'person-cash-wallet' ); ?></a>
					<a href="#campaigns" class="nav-tab" data-tab="campaigns"><?php esc_html_e( 'Por Campanha', 'person-cash-wallet' ); ?></a>
					<a href="#devices" class="nav-tab" data-tab="devices"><?php esc_html_e( 'Por Dispositivo', 'person-cash-wallet' ); ?></a>
					<a href="#referrals" class="nav-tab" data-tab="referrals"><?php esc_html_e( 'Por Indicação', 'person-cash-wallet' ); ?></a>
				</nav>
			</div>

			<!-- Conteúdo das abas -->
			<div class="pcw-tab-content">
				<!-- Por Canal -->
				<div id="tab-channels" class="pcw-tab-panel active">
					<?php $this->render_channel_table( $stats ); ?>
				</div>

				<!-- Por Fonte -->
				<div id="tab-sources" class="pcw-tab-panel" style="display: none;">
					<?php $this->render_source_table( $stats ); ?>
				</div>

				<!-- Por Campanha -->
				<div id="tab-campaigns" class="pcw-tab-panel" style="display: none;">
					<?php $this->render_campaign_table( $stats ); ?>
				</div>

				<!-- Por Dispositivo -->
				<div id="tab-devices" class="pcw-tab-panel" style="display: none;">
					<?php $this->render_device_table( $stats ); ?>
				</div>

				<!-- Por Indicação -->
				<div id="tab-referrals" class="pcw-tab-panel" style="display: none;">
					<?php $this->render_referral_table( $stats ); ?>
				</div>
			</div>

			<!-- Info sobre tracking -->
			<div class="pcw-info-box" style="margin-top: 30px; padding: 20px; background: #f0f9ff; border-left: 4px solid #667eea; border-radius: 4px;">
				<h4 style="margin: 0 0 10px 0; color: #667eea;">
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'Como funciona o rastreamento de origem?', 'person-cash-wallet' ); ?>
				</h4>
				<p style="margin: 0; color: #666;">
					<?php esc_html_e( 'O sistema captura automaticamente dados de origem dos visitantes:', 'person-cash-wallet' ); ?>
				</p>
				<ul style="margin: 10px 0 0 20px; color: #666;">
					<li><strong>First Touch:</strong> <?php esc_html_e( 'A primeira origem que trouxe o visitante ao site', 'person-cash-wallet' ); ?></li>
					<li><strong>Last Touch:</strong> <?php esc_html_e( 'A última origem antes da conversão', 'person-cash-wallet' ); ?></li>
					<li><strong>UTMs:</strong> <?php esc_html_e( 'Parâmetros utm_source, utm_medium, utm_campaign, etc', 'person-cash-wallet' ); ?></li>
					<li><strong>Click IDs:</strong> <?php esc_html_e( 'gclid (Google), fbclid (Facebook), msclkid (Bing), etc', 'person-cash-wallet' ); ?></li>
					<li><strong>Referrer:</strong> <?php esc_html_e( 'O site de onde o visitante veio', 'person-cash-wallet' ); ?></li>
					<li><strong>Indicações:</strong> <?php esc_html_e( 'Código de referral (?ref=CODIGO)', 'person-cash-wallet' ); ?></li>
				</ul>
			</div>
		</div>

		<style>
			.pcw-origin-tabs .nav-tab { cursor: pointer; }
			.pcw-origin-tabs .nav-tab-active { background: #fff; border-bottom: 1px solid #fff; }
			.pcw-tab-panel { background: #fff; padding: 20px; border: 1px solid #ccc; border-top: none; }
			.pcw-origin-table { width: 100%; border-collapse: collapse; }
			.pcw-origin-table th, .pcw-origin-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
			.pcw-origin-table th { background: #f9f9f9; font-weight: 600; }
			.pcw-origin-table tr:hover { background: #f5f5f5; }
			.pcw-channel-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
			.pcw-channel-direct { background: #e5e7eb; color: #374151; }
			.pcw-channel-organic_search { background: #dcfce7; color: #166534; }
			.pcw-channel-paid_search { background: #fef3c7; color: #92400e; }
			.pcw-channel-organic_social { background: #dbeafe; color: #1e40af; }
			.pcw-channel-paid_social { background: #fce7f3; color: #9d174d; }
			.pcw-channel-email { background: #f3e8ff; color: #6b21a8; }
			.pcw-channel-referral { background: #ccfbf1; color: #0f766e; }
			.pcw-channel-display { background: #fed7aa; color: #c2410c; }
			.pcw-channel-other { background: #f3f4f6; color: #6b7280; }
			.pcw-progress-bar { height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; }
			.pcw-progress-fill { height: 100%; background: #667eea; }
		</style>

		<script>
		jQuery(document).ready(function($) {
			$('.pcw-origin-tabs .nav-tab').on('click', function(e) {
				e.preventDefault();
				var tab = $(this).data('tab');
				
				$('.nav-tab').removeClass('nav-tab-active');
				$(this).addClass('nav-tab-active');
				
				$('.pcw-tab-panel').hide();
				$('#tab-' + tab).show();
			});
		});
		</script>
		<?php
	}

	/**
	 * Renderizar tabela de canais
	 *
	 * @param array $stats Estatísticas.
	 */
	private function render_channel_table( $stats ) {
		$sessions_by_channel = $stats['sessions_by_channel'] ?? array();
		$orders_by_channel = $stats['orders_by_channel_last'] ?? array();

		// Criar mapa de pedidos por canal
		$orders_map = array();
		foreach ( $orders_by_channel as $row ) {
			$orders_map[ $row['channel'] ] = $row['orders'];
		}

		// Calcular total para porcentagens
		$total_sessions = array_sum( array_column( $sessions_by_channel, 'sessions' ) );
		$total_orders = array_sum( array_column( $orders_by_channel, 'orders' ) );

		$channel_labels = array(
			'direct'         => __( 'Direto', 'person-cash-wallet' ),
			'organic_search' => __( 'Busca Orgânica', 'person-cash-wallet' ),
			'paid_search'    => __( 'Busca Paga', 'person-cash-wallet' ),
			'organic_social' => __( 'Social Orgânico', 'person-cash-wallet' ),
			'paid_social'    => __( 'Social Pago', 'person-cash-wallet' ),
			'email'          => __( 'Email', 'person-cash-wallet' ),
			'referral'       => __( 'Indicação/Referral', 'person-cash-wallet' ),
			'display'        => __( 'Display', 'person-cash-wallet' ),
			'affiliates'     => __( 'Afiliados', 'person-cash-wallet' ),
			'other'          => __( 'Outros', 'person-cash-wallet' ),
		);
		?>
		<h3><?php esc_html_e( 'Sessões e Pedidos por Canal de Aquisição', 'person-cash-wallet' ); ?></h3>

		<?php if ( empty( $sessions_by_channel ) ) : ?>
			<p style="color: #666;"><?php esc_html_e( 'Nenhum dado de canal disponível ainda. Os dados começarão a aparecer após os primeiros visitantes.', 'person-cash-wallet' ); ?></p>
		<?php else : ?>
			<table class="pcw-origin-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Canal', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Sessões', 'person-cash-wallet' ); ?></th>
						<th style="width: 200px;"><?php esc_html_e( '% Sessões', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Pedidos', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Taxa Conversão', 'person-cash-wallet' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sessions_by_channel as $row ) : 
						$channel = $row['channel'];
						$sessions = (int) $row['sessions'];
						$orders = isset( $orders_map[ $channel ] ) ? (int) $orders_map[ $channel ] : 0;
						$pct = $total_sessions > 0 ? round( ( $sessions / $total_sessions ) * 100, 1 ) : 0;
						$conv_rate = $sessions > 0 ? round( ( $orders / $sessions ) * 100, 2 ) : 0;
						$label = isset( $channel_labels[ $channel ] ) ? $channel_labels[ $channel ] : ucfirst( $channel );
					?>
					<tr>
						<td>
							<span class="pcw-channel-badge pcw-channel-<?php echo esc_attr( $channel ); ?>">
								<?php echo esc_html( $label ); ?>
							</span>
						</td>
						<td><strong><?php echo esc_html( number_format_i18n( $sessions ) ); ?></strong></td>
						<td>
							<div class="pcw-progress-bar">
								<div class="pcw-progress-fill" style="width: <?php echo esc_attr( $pct ); ?>%;"></div>
							</div>
							<small><?php echo esc_html( $pct ); ?>%</small>
						</td>
						<td><?php echo esc_html( number_format_i18n( $orders ) ); ?></td>
						<td>
							<span style="color: <?php echo $conv_rate > 0 ? '#22c55e' : '#999'; ?>;">
								<?php echo esc_html( $conv_rate ); ?>%
							</span>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr style="background: #f9f9f9; font-weight: bold;">
						<td><?php esc_html_e( 'Total', 'person-cash-wallet' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $total_sessions ) ); ?></td>
						<td>100%</td>
						<td><?php echo esc_html( number_format_i18n( $total_orders ) ); ?></td>
						<td>
							<?php 
							$total_conv = $total_sessions > 0 ? round( ( $total_orders / $total_sessions ) * 100, 2 ) : 0;
							echo esc_html( $total_conv . '%' ); 
							?>
						</td>
					</tr>
				</tfoot>
			</table>
		<?php endif;
	}

	/**
	 * Renderizar tabela de fontes
	 *
	 * @param array $stats Estatísticas.
	 */
	private function render_source_table( $stats ) {
		$sessions_by_source = $stats['sessions_by_source'] ?? array();
		$orders_by_source = $stats['orders_by_source'] ?? array();

		// Criar mapa de pedidos por source/medium
		$orders_map = array();
		foreach ( $orders_by_source as $row ) {
			$key = $row['source'] . '/' . $row['medium'];
			$orders_map[ $key ] = $row['orders'];
		}
		?>
		<h3><?php esc_html_e( 'Sessões por Fonte/Mídia (Source/Medium)', 'person-cash-wallet' ); ?></h3>

		<?php if ( empty( $sessions_by_source ) ) : ?>
			<p style="color: #666;"><?php esc_html_e( 'Nenhum dado de fonte disponível ainda.', 'person-cash-wallet' ); ?></p>
		<?php else : ?>
			<table class="pcw-origin-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Fonte', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Mídia', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Sessões', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Sessões Únicas', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Pedidos', 'person-cash-wallet' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sessions_by_source as $row ) : 
						$key = $row['source'] . '/' . $row['medium'];
						$orders = isset( $orders_map[ $key ] ) ? (int) $orders_map[ $key ] : 0;
					?>
					<tr>
						<td><strong><?php echo esc_html( $row['source'] ?: '(não definido)' ); ?></strong></td>
						<td><?php echo esc_html( $row['medium'] ?: '(não definido)' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $row['sessions'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $row['unique_sessions'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $orders ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif;
	}

	/**
	 * Renderizar tabela de campanhas
	 *
	 * @param array $stats Estatísticas.
	 */
	private function render_campaign_table( $stats ) {
		$sessions_by_campaign = $stats['sessions_by_campaign'] ?? array();
		$orders_by_campaign = $stats['orders_by_campaign'] ?? array();

		$orders_map = array();
		foreach ( $orders_by_campaign as $row ) {
			$orders_map[ $row['campaign'] ] = $row['orders'];
		}
		?>
		<h3><?php esc_html_e( 'Sessões e Pedidos por Campanha (UTM Campaign)', 'person-cash-wallet' ); ?></h3>

		<?php if ( empty( $sessions_by_campaign ) ) : ?>
			<p style="color: #666;"><?php esc_html_e( 'Nenhum dado de campanha disponível. Certifique-se de usar parâmetros utm_campaign em seus links.', 'person-cash-wallet' ); ?></p>
		<?php else : ?>
			<table class="pcw-origin-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Campanha', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Fonte', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Sessões', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Pedidos', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Taxa Conversão', 'person-cash-wallet' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sessions_by_campaign as $row ) : 
						$orders = isset( $orders_map[ $row['campaign'] ] ) ? (int) $orders_map[ $row['campaign'] ] : 0;
						$sessions = (int) $row['sessions'];
						$conv_rate = $sessions > 0 ? round( ( $orders / $sessions ) * 100, 2 ) : 0;
					?>
					<tr>
						<td><strong><?php echo esc_html( $row['campaign'] ); ?></strong></td>
						<td><?php echo esc_html( $row['source'] ?: '-' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $sessions ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $orders ) ); ?></td>
						<td>
							<span style="color: <?php echo $conv_rate > 0 ? '#22c55e' : '#999'; ?>;">
								<?php echo esc_html( $conv_rate ); ?>%
							</span>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif;
	}

	/**
	 * Renderizar tabela de dispositivos
	 *
	 * @param array $stats Estatísticas.
	 */
	private function render_device_table( $stats ) {
		$sessions_by_device = $stats['sessions_by_device'] ?? array();
		$total = array_sum( array_column( $sessions_by_device, 'sessions' ) );

		$device_icons = array(
			'desktop' => 'dashicons-desktop',
			'mobile'  => 'dashicons-smartphone',
			'tablet'  => 'dashicons-tablet',
		);

		$device_labels = array(
			'desktop' => __( 'Desktop', 'person-cash-wallet' ),
			'mobile'  => __( 'Mobile', 'person-cash-wallet' ),
			'tablet'  => __( 'Tablet', 'person-cash-wallet' ),
		);
		?>
		<h3><?php esc_html_e( 'Sessões por Tipo de Dispositivo', 'person-cash-wallet' ); ?></h3>

		<?php if ( empty( $sessions_by_device ) ) : ?>
			<p style="color: #666;"><?php esc_html_e( 'Nenhum dado de dispositivo disponível ainda.', 'person-cash-wallet' ); ?></p>
		<?php else : ?>
			<div style="display: flex; gap: 20px; flex-wrap: wrap;">
				<?php foreach ( $sessions_by_device as $row ) : 
					$device = $row['device_type'];
					$sessions = (int) $row['sessions'];
					$pct = $total > 0 ? round( ( $sessions / $total ) * 100, 1 ) : 0;
					$icon = isset( $device_icons[ $device ] ) ? $device_icons[ $device ] : 'dashicons-admin-generic';
					$label = isset( $device_labels[ $device ] ) ? $device_labels[ $device ] : ucfirst( $device );
				?>
				<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-width: 150px; text-align: center;">
					<span class="dashicons <?php echo esc_attr( $icon ); ?>" style="font-size: 48px; color: #667eea;"></span>
					<h4 style="margin: 10px 0 5px 0;"><?php echo esc_html( $label ); ?></h4>
					<p style="margin: 0; font-size: 24px; font-weight: bold;"><?php echo esc_html( number_format_i18n( $sessions ) ); ?></p>
					<p style="margin: 5px 0 0 0; color: #666;"><?php echo esc_html( $pct ); ?>%</p>
				</div>
				<?php endforeach; ?>
			</div>
		<?php endif;
	}

	/**
	 * Renderizar tabela de indicações
	 *
	 * @param array $stats Estatísticas.
	 */
	private function render_referral_table( $stats ) {
		$orders_by_referral = $stats['orders_by_referral'] ?? array();
		?>
		<h3><?php esc_html_e( 'Pedidos por Código de Indicação', 'person-cash-wallet' ); ?></h3>

		<?php if ( empty( $orders_by_referral ) ) : ?>
			<p style="color: #666;"><?php esc_html_e( 'Nenhum pedido com código de indicação no período selecionado.', 'person-cash-wallet' ); ?></p>
		<?php else : ?>
			<table class="pcw-origin-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Código', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Pedidos', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Indicador', 'person-cash-wallet' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $orders_by_referral as $row ) : 
						// Buscar informações do código
						$referral_code = PCW_Referral_Codes::instance()->get_code_by_code( $row['referral_code'] );
						$referrer_name = '-';
						if ( $referral_code ) {
							$user = get_userdata( $referral_code->user_id );
							$referrer_name = $user ? $user->display_name : 'ID: ' . $referral_code->user_id;
						}
					?>
					<tr>
						<td><code style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px;"><?php echo esc_html( $row['referral_code'] ); ?></code></td>
						<td><strong><?php echo esc_html( number_format_i18n( $row['orders'] ) ); ?></strong></td>
						<td><?php echo esc_html( $referrer_name ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif;
	}

	/**
	 * Adicionar meta box de atribuição nos pedidos
	 */
	public function add_order_meta_box() {
		add_meta_box(
			'pcw-order-attribution',
			__( 'Origem/Atribuição', 'person-cash-wallet' ),
			array( $this, 'render_order_attribution_meta_box' ),
			'shop_order',
			'side',
			'default'
		);

		// HPOS support
		add_meta_box(
			'pcw-order-attribution',
			__( 'Origem/Atribuição', 'person-cash-wallet' ),
			array( $this, 'render_order_attribution_meta_box' ),
			'woocommerce_page_wc-orders',
			'side',
			'default'
		);
	}

	/**
	 * Renderizar meta box de atribuição no pedido
	 *
	 * @param WP_Post|WC_Order $post Post ou Order.
	 */
	public function render_order_attribution_meta_box( $post ) {
		// HPOS support
		if ( $post instanceof WC_Order ) {
			$order = $post;
		} else {
			$order = wc_get_order( $post->ID );
		}

		if ( ! $order ) {
			return;
		}

		$attribution = PCW_User_Origin_Tracker::instance()->get_order_attribution( $order->get_id() );

		if ( empty( $attribution ) ) {
			?>
			<p style="color: #999; font-size: 12px;">
				<?php esc_html_e( 'Dados de origem não disponíveis para este pedido.', 'person-cash-wallet' ); ?>
			</p>
			<?php
			return;
		}

		$channel_labels = array(
			'direct'         => __( 'Direto', 'person-cash-wallet' ),
			'organic_search' => __( 'Busca Orgânica', 'person-cash-wallet' ),
			'paid_search'    => __( 'Busca Paga', 'person-cash-wallet' ),
			'organic_social' => __( 'Social Orgânico', 'person-cash-wallet' ),
			'paid_social'    => __( 'Social Pago', 'person-cash-wallet' ),
			'email'          => __( 'Email', 'person-cash-wallet' ),
			'referral'       => __( 'Indicação', 'person-cash-wallet' ),
			'display'        => __( 'Display', 'person-cash-wallet' ),
			'other'          => __( 'Outros', 'person-cash-wallet' ),
		);
		?>
		<div style="font-size: 12px;">
			<?php if ( ! empty( $attribution['last_touch_channel'] ) ) : 
				$channel = $attribution['last_touch_channel'];
				$label = isset( $channel_labels[ $channel ] ) ? $channel_labels[ $channel ] : ucfirst( $channel );
			?>
			<p style="margin: 0 0 10px 0;">
				<span class="pcw-channel-badge pcw-channel-<?php echo esc_attr( $channel ); ?>" style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500;">
					<?php echo esc_html( $label ); ?>
				</span>
			</p>
			<?php endif; ?>

			<table style="width: 100%; font-size: 11px;">
				<?php if ( ! empty( $attribution['last_touch_source'] ) ) : ?>
				<tr>
					<td style="color: #666; padding: 3px 0;"><?php esc_html_e( 'Fonte:', 'person-cash-wallet' ); ?></td>
					<td style="padding: 3px 0;"><strong><?php echo esc_html( $attribution['last_touch_source'] ); ?></strong></td>
				</tr>
				<?php endif; ?>

				<?php if ( ! empty( $attribution['last_touch_medium'] ) ) : ?>
				<tr>
					<td style="color: #666; padding: 3px 0;"><?php esc_html_e( 'Mídia:', 'person-cash-wallet' ); ?></td>
					<td style="padding: 3px 0;"><?php echo esc_html( $attribution['last_touch_medium'] ); ?></td>
				</tr>
				<?php endif; ?>

				<?php if ( ! empty( $attribution['last_touch_campaign'] ) ) : ?>
				<tr>
					<td style="color: #666; padding: 3px 0;"><?php esc_html_e( 'Campanha:', 'person-cash-wallet' ); ?></td>
					<td style="padding: 3px 0;"><?php echo esc_html( $attribution['last_touch_campaign'] ); ?></td>
				</tr>
				<?php endif; ?>

				<?php if ( ! empty( $attribution['referral_code'] ) ) : ?>
				<tr>
					<td style="color: #666; padding: 3px 0;"><?php esc_html_e( 'Indicação:', 'person-cash-wallet' ); ?></td>
					<td style="padding: 3px 0;"><code><?php echo esc_html( $attribution['referral_code'] ); ?></code></td>
				</tr>
				<?php endif; ?>

				<?php if ( ! empty( $attribution['gclid'] ) ) : ?>
				<tr>
					<td style="color: #666; padding: 3px 0;"><?php esc_html_e( 'Google Ads:', 'person-cash-wallet' ); ?></td>
					<td style="padding: 3px 0;">✅ GCLID</td>
				</tr>
				<?php endif; ?>

				<?php if ( ! empty( $attribution['fbclid'] ) ) : ?>
				<tr>
					<td style="color: #666; padding: 3px 0;"><?php esc_html_e( 'Facebook:', 'person-cash-wallet' ); ?></td>
					<td style="padding: 3px 0;">✅ FBCLID</td>
				</tr>
				<?php endif; ?>

				<?php if ( ! empty( $attribution['device_type'] ) ) : ?>
				<tr>
					<td style="color: #666; padding: 3px 0;"><?php esc_html_e( 'Dispositivo:', 'person-cash-wallet' ); ?></td>
					<td style="padding: 3px 0;"><?php echo esc_html( ucfirst( $attribution['device_type'] ) ); ?></td>
				</tr>
				<?php endif; ?>
			</table>

			<?php if ( ! empty( $attribution['first_touch_source'] ) && $attribution['first_touch_source'] !== $attribution['last_touch_source'] ) : ?>
			<hr style="margin: 10px 0; border: none; border-top: 1px solid #eee;">
			<p style="margin: 0; color: #999; font-size: 10px;">
				<strong><?php esc_html_e( 'First Touch:', 'person-cash-wallet' ); ?></strong>
				<?php echo esc_html( $attribution['first_touch_source'] . ' / ' . ( $attribution['first_touch_medium'] ?? '' ) ); ?>
			</p>
			<?php endif; ?>
		</div>

		<style>
			.pcw-channel-direct { background: #e5e7eb; color: #374151; }
			.pcw-channel-organic_search { background: #dcfce7; color: #166534; }
			.pcw-channel-paid_search { background: #fef3c7; color: #92400e; }
			.pcw-channel-organic_social { background: #dbeafe; color: #1e40af; }
			.pcw-channel-paid_social { background: #fce7f3; color: #9d174d; }
			.pcw-channel-email { background: #f3e8ff; color: #6b21a8; }
			.pcw-channel-referral { background: #ccfbf1; color: #0f766e; }
			.pcw-channel-display { background: #fed7aa; color: #c2410c; }
			.pcw-channel-other { background: #f3f4f6; color: #6b7280; }
		</style>
		<?php
	}

	/**
	 * AJAX: Obter relatório de origem
	 */
	public function ajax_get_report() {
		check_ajax_referer( 'pcw_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		$period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : '30days';
		$stats = PCW_User_Origin_Tracker::instance()->get_stats( $period );

		wp_send_json_success( $stats );
	}

	/**
	 * AJAX: Obter atribuição de pedido
	 */
	public function ajax_get_order_attribution() {
		check_ajax_referer( 'pcw_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => 'ID do pedido inválido' ) );
		}

		$attribution = PCW_User_Origin_Tracker::instance()->get_order_attribution( $order_id );

		if ( ! $attribution ) {
			wp_send_json_error( array( 'message' => 'Atribuição não encontrada' ) );
		}

		wp_send_json_success( $attribution );
	}

	/**
	 * AJAX: Sincronizar atribuições do WooCommerce nativo
	 */
	public function ajax_sync_wc_attributions() {
		check_ajax_referer( 'pcw_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		global $wpdb;

		$table = $wpdb->prefix . 'pcw_order_attributions';

		// Verificar se tabela existe
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
		if ( ! $table_exists ) {
			wp_send_json_error( array( 'message' => 'Tabela de atribuições não existe' ) );
		}

		// Buscar pedidos recentes (últimos 90 dias) que não têm atribuição no PCW
		$start_date = date( 'Y-m-d', strtotime( '-90 days' ) );

		// Obter pedidos do WooCommerce
		$orders = wc_get_orders( array(
			'limit'      => 500,
			'date_after' => $start_date,
			'status'     => array( 'completed', 'processing', 'on-hold', 'pending' ),
			'orderby'    => 'date',
			'order'      => 'DESC',
		) );

		$synced = 0;
		$skipped = 0;

		foreach ( $orders as $order ) {
			$order_id = $order->get_id();

			// Verificar se já tem atribuição PCW
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT order_id FROM {$table} WHERE order_id = %d",
					$order_id
				)
			);

			if ( $existing ) {
				$skipped++;
				continue;
			}

			// Tentar obter dados do WooCommerce nativo
			$wc_source = $order->get_meta( '_wc_order_attribution_utm_source' );
			$wc_medium = $order->get_meta( '_wc_order_attribution_utm_medium' );
			$wc_campaign = $order->get_meta( '_wc_order_attribution_utm_campaign' );
			$wc_source_type = $order->get_meta( '_wc_order_attribution_source_type' );
			$wc_referrer = $order->get_meta( '_wc_order_attribution_referrer' );

			// Se não tem dados do WooCommerce nativo, tentar meta do PCW
			if ( empty( $wc_source ) ) {
				$pcw_json = $order->get_meta( '_pcw_attribution' );
				if ( $pcw_json ) {
					$pcw_data = json_decode( $pcw_json, true );
					if ( $pcw_data ) {
						$wc_source = $pcw_data['last_touch_source'] ?? '';
						$wc_medium = $pcw_data['last_touch_medium'] ?? '';
						$wc_campaign = $pcw_data['last_touch_campaign'] ?? '';
					}
				}
			}

			// Se ainda não tem source, definir como direct
			if ( empty( $wc_source ) ) {
				$wc_source = '(direct)';
				$wc_medium = '(none)';
			}

			// Determinar canal
			$channel = $this->determine_channel( $wc_source, $wc_medium );

			// Inserir na tabela
			$wpdb->insert(
				$table,
				array(
					'order_id'             => $order_id,
					'user_id'              => $order->get_user_id() ?: null,
					'first_touch_source'   => $wc_source,
					'first_touch_medium'   => $wc_medium,
					'first_touch_campaign' => $wc_campaign,
					'first_touch_channel'  => $channel,
					'first_touch_referrer' => $wc_referrer,
					'last_touch_source'    => $wc_source,
					'last_touch_medium'    => $wc_medium,
					'last_touch_campaign'  => $wc_campaign,
					'last_touch_channel'   => $channel,
					'last_touch_referrer'  => $wc_referrer,
					'created_at'           => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
				)
			);

			if ( $wpdb->insert_id ) {
				$synced++;
			}
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %1$d: synced count, %2$d: skipped count */
					__( 'Sincronização concluída! %1$d pedidos sincronizados, %2$d já existiam.', 'person-cash-wallet' ),
					$synced,
					$skipped
				),
				'synced'  => $synced,
				'skipped' => $skipped,
			)
		);
	}

	/**
	 * Determinar canal baseado em source/medium
	 *
	 * @param string $source Fonte.
	 * @param string $medium Mídia.
	 * @return string
	 */
	private function determine_channel( $source, $medium ) {
		$source = strtolower( $source );
		$medium = strtolower( $medium );

		if ( '(direct)' === $source || empty( $source ) ) {
			return 'direct';
		}

		if ( in_array( $medium, array( 'cpc', 'ppc', 'paidsearch' ), true ) ) {
			return 'paid_search';
		}

		if ( 'email' === $medium ) {
			return 'email';
		}

		if ( 'organic' === $medium || in_array( $source, array( 'google', 'bing', 'yahoo', 'duckduckgo' ), true ) ) {
			return 'organic_search';
		}

		if ( 'social' === $medium || in_array( $source, array( 'facebook', 'instagram', 'twitter', 'linkedin', 'youtube', 'tiktok' ), true ) ) {
			return 'organic_social';
		}

		if ( 'referral' === $medium ) {
			return 'referral';
		}

		return 'other';
	}
}
