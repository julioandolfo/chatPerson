<?php
/**
 * Classe admin para dashboard
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe admin dashboard
 */
class PCW_Admin_Dashboard {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Adicionar menu
	 */
	public function add_menu() {
		// Menu principal
		add_menu_page(
			__( 'Growly Digital', 'person-cash-wallet' ),
			__( 'Growly Digital', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-money-alt',
			56
		);

		// Dashboard (submenu) - posição 0
		add_submenu_page(
			'pcw-dashboard',
			__( 'Dashboard', 'person-cash-wallet' ),
			__( 'Dashboard', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-dashboard',
			array( $this, 'render_dashboard' ),
			0
		);
	}

	/**
	 * Enfileirar scripts
	 *
	 * @param string $hook Hook atual.
	 */
	public function enqueue_scripts( $hook ) {
		// Enfileirar estilos e scripts globais em todas as páginas do plugin
		if ( strpos( $hook, 'pcw-' ) !== false || strpos( $hook, 'pcw_' ) !== false || strpos( $hook, 'woocommerce_page_pcw-' ) !== false ) {
			wp_enqueue_style( 'pcw-admin-global', PCW_PLUGIN_URL . 'assets/css/admin-global.css', array(), PCW_VERSION . '-' . time() );
			wp_enqueue_script( 'pcw-admin-global', PCW_PLUGIN_URL . 'assets/js/admin-global.js', array( 'jquery' ), PCW_VERSION, true );
		}

		if ( 'toplevel_page_pcw-dashboard' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'pcw-admin-dashboard', PCW_PLUGIN_URL . 'assets/css/admin-dashboard.css', array( 'pcw-admin-global' ), PCW_VERSION );
		
		// Enqueue Chart.js
		wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true );
		
		// Enqueue custom dashboard JS
		wp_enqueue_script( 'pcw-admin-dashboard', PCW_PLUGIN_URL . 'assets/js/admin-dashboard.js', array( 'chartjs', 'jquery', 'pcw-admin-global' ), PCW_VERSION, true );
		
		// Pass data to JS
		wp_localize_script( 'pcw-admin-dashboard', 'pcwDashboardData', array(
			'cashbackChart' => $this->get_cashback_chart_data(),
			'levelsChart'   => $this->get_levels_chart_data(),
			'walletChart'   => $this->get_wallet_chart_data(),
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'pcw_live_dashboard' ),
		) );
	}

	/**
	 * Renderizar dashboard
	 */
	public function render_dashboard() {
		global $wpdb;

		// Estatísticas gerais
		$stats = $this->get_stats();

		?>
		<div class="wrap pcw-dashboard">
			<h1><?php esc_html_e( 'Dashboard - Growly Digital', 'person-cash-wallet' ); ?></h1>

			<!-- Atividade em Tempo Real -->
			<div class="pcw-live-section">
				<div class="pcw-live-header">
					<h2>
						<span class="pcw-live-indicator"></span>
						<?php esc_html_e( 'Atividade em Tempo Real', 'person-cash-wallet' ); ?>
					</h2>
					<select id="pcw-live-period">
						<option value="today"><?php esc_html_e( 'Hoje', 'person-cash-wallet' ); ?></option>
						<option value="7days" selected><?php esc_html_e( 'Últimos 7 dias', 'person-cash-wallet' ); ?></option>
						<option value="30days"><?php esc_html_e( 'Últimos 30 dias', 'person-cash-wallet' ); ?></option>
					</select>
				</div>
				<div class="pcw-live-stats-row">
					<div class="pcw-live-stat pcw-live-stat-main">
						<span class="pcw-live-stat-icon">👥</span>
						<span class="pcw-live-stat-value" id="stat-visitors">-</span>
						<span class="pcw-live-stat-label"><?php esc_html_e( 'Visitantes Unicos', 'person-cash-wallet' ); ?></span>
						<span class="pcw-live-stat-sub" id="stat-visitors-detail"></span>
					</div>
					<div class="pcw-live-stat">
						<span class="pcw-live-stat-icon">🆕</span>
						<span class="pcw-live-stat-value" id="stat-new-visitors">-</span>
						<span class="pcw-live-stat-label"><?php esc_html_e( 'Novos', 'person-cash-wallet' ); ?></span>
					</div>
					<div class="pcw-live-stat">
						<span class="pcw-live-stat-icon">🔄</span>
						<span class="pcw-live-stat-value" id="stat-returning-visitors">-</span>
						<span class="pcw-live-stat-label"><?php esc_html_e( 'Recorrentes', 'person-cash-wallet' ); ?></span>
					</div>
					<div class="pcw-live-stat">
						<span class="pcw-live-stat-icon">👁️</span>
						<span class="pcw-live-stat-value" id="stat-views">-</span>
						<span class="pcw-live-stat-label"><?php esc_html_e( 'Produtos Vistos', 'person-cash-wallet' ); ?></span>
					</div>
					<div class="pcw-live-stat">
						<span class="pcw-live-stat-icon">🛒</span>
						<span class="pcw-live-stat-value" id="stat-cart">-</span>
						<span class="pcw-live-stat-label"><?php esc_html_e( 'Add ao Carrinho', 'person-cash-wallet' ); ?></span>
					</div>
					<div class="pcw-live-stat">
						<span class="pcw-live-stat-icon">📦</span>
						<span class="pcw-live-stat-value" id="stat-orders">-</span>
						<span class="pcw-live-stat-label"><?php esc_html_e( 'Pedidos', 'person-cash-wallet' ); ?></span>
					</div>
					<div class="pcw-live-stat">
						<span class="pcw-live-stat-icon">📊</span>
						<span class="pcw-live-stat-value" id="stat-conversion">-</span>
						<span class="pcw-live-stat-label"><?php esc_html_e( 'Conversao', 'person-cash-wallet' ); ?></span>
					</div>
				</div>
				<!-- Detalhes de visitantes -->
				<div class="pcw-visitors-detail-row" id="pcw-visitors-details">
					<div class="pcw-visitor-detail-item">
						<span class="pcw-visitor-detail-label"><?php esc_html_e( 'Clientes logados:', 'person-cash-wallet' ); ?></span>
						<span class="pcw-visitor-detail-value" id="stat-logged">-</span>
					</div>
					<div class="pcw-visitor-detail-item">
						<span class="pcw-visitor-detail-label"><?php esc_html_e( 'Visitantes anonimos:', 'person-cash-wallet' ); ?></span>
						<span class="pcw-visitor-detail-value" id="stat-anonymous">-</span>
					</div>
					<div class="pcw-visitor-detail-item">
						<span class="pcw-visitor-detail-label"><?php esc_html_e( 'Pageviews:', 'person-cash-wallet' ); ?></span>
						<span class="pcw-visitor-detail-value" id="stat-pageviews">-</span>
					</div>
					<div class="pcw-visitor-detail-item">
						<span class="pcw-visitor-detail-label"><?php esc_html_e( 'Pag/Visita:', 'person-cash-wallet' ); ?></span>
						<span class="pcw-visitor-detail-value" id="stat-pages-per-visit">-</span>
					</div>
				</div>
				<div class="pcw-live-feed">
					<h3><?php esc_html_e( 'Atividades Recentes', 'person-cash-wallet' ); ?></h3>
					<div id="pcw-live-activities">
						<p class="pcw-loading"><?php esc_html_e( 'Carregando atividades...', 'person-cash-wallet' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Google Analytics 4 Integration -->
			<?php $ga = PCW_Google_Analytics::get_instance(); ?>
			<?php if ( $ga->is_configured() ) : ?>
			<div class="pcw-ga4-section">
				<div class="pcw-ga4-header">
					<h2>
						<span class="dashicons dashicons-chart-area"></span>
						<?php esc_html_e( 'Google Analytics 4', 'person-cash-wallet' ); ?>
						<span class="pcw-ga4-realtime" id="ga4-realtime-badge" title="Usuários em tempo real">
							<span class="pcw-pulse"></span>
							<span id="ga4-realtime-count">-</span>
							<span class="pcw-realtime-label"><?php esc_html_e( 'Nos últimos 30 minutos', 'person-cash-wallet' ); ?></span>
						</span>
					</h2>
				</div>

				<!-- Comparação Tracking Interno vs GA4 -->
				<div class="pcw-ga4-comparison" id="pcw-ga4-comparison">
					<h3><?php esc_html_e( 'Comparação: Tracking Interno vs Google Analytics', 'person-cash-wallet' ); ?></h3>
					<div class="pcw-comparison-grid">
						<div class="pcw-comparison-item">
							<span class="pcw-comparison-label"><?php esc_html_e( 'Visitantes', 'person-cash-wallet' ); ?></span>
							<div class="pcw-comparison-values">
								<span class="pcw-comparison-internal" title="Tracking Interno">
									<small>Interno</small>
									<strong id="cmp-visitors-internal">-</strong>
								</span>
								<span class="pcw-comparison-vs">vs</span>
								<span class="pcw-comparison-ga" title="Google Analytics">
									<small>GA4</small>
									<strong id="cmp-visitors-ga">-</strong>
								</span>
								<span class="pcw-comparison-diff" id="cmp-visitors-diff"></span>
							</div>
						</div>
						<div class="pcw-comparison-item">
							<span class="pcw-comparison-label"><?php esc_html_e( 'Pageviews', 'person-cash-wallet' ); ?></span>
							<div class="pcw-comparison-values">
								<span class="pcw-comparison-internal">
									<small>Interno</small>
									<strong id="cmp-pageviews-internal">-</strong>
								</span>
								<span class="pcw-comparison-vs">vs</span>
								<span class="pcw-comparison-ga">
									<small>GA4</small>
									<strong id="cmp-pageviews-ga">-</strong>
								</span>
								<span class="pcw-comparison-diff" id="cmp-pageviews-diff"></span>
							</div>
						</div>
						<div class="pcw-comparison-item">
							<span class="pcw-comparison-label"><?php esc_html_e( 'Add to Cart', 'person-cash-wallet' ); ?></span>
							<div class="pcw-comparison-values">
								<span class="pcw-comparison-internal">
									<small>Interno</small>
									<strong id="cmp-cart-internal">-</strong>
								</span>
								<span class="pcw-comparison-vs">vs</span>
								<span class="pcw-comparison-ga">
									<small>GA4</small>
									<strong id="cmp-cart-ga">-</strong>
								</span>
								<span class="pcw-comparison-diff" id="cmp-cart-diff"></span>
							</div>
						</div>
						<div class="pcw-comparison-item">
							<span class="pcw-comparison-label"><?php esc_html_e( 'Pedidos', 'person-cash-wallet' ); ?></span>
							<div class="pcw-comparison-values">
								<span class="pcw-comparison-internal">
									<small>Interno</small>
									<strong id="cmp-orders-internal">-</strong>
								</span>
								<span class="pcw-comparison-vs">vs</span>
								<span class="pcw-comparison-ga">
									<small>GA4</small>
									<strong id="cmp-orders-ga">-</strong>
								</span>
								<span class="pcw-comparison-diff" id="cmp-orders-diff"></span>
							</div>
						</div>
					</div>
				</div>

				<!-- Métricas detalhadas do GA4 -->
				<div class="pcw-ga4-metrics-grid">
					<div class="pcw-ga4-metric-card">
						<h4><?php esc_html_e( 'Engajamento', 'person-cash-wallet' ); ?></h4>
						<div class="pcw-ga4-metric-content">
							<div class="pcw-ga4-metric-item">
								<span class="label"><?php esc_html_e( 'Taxa de Rejeição', 'person-cash-wallet' ); ?></span>
								<span class="value" id="ga4-bounce-rate">-</span>
							</div>
							<div class="pcw-ga4-metric-item">
								<span class="label"><?php esc_html_e( 'Taxa de Engajamento', 'person-cash-wallet' ); ?></span>
								<span class="value" id="ga4-engagement-rate">-</span>
							</div>
							<div class="pcw-ga4-metric-item">
								<span class="label"><?php esc_html_e( 'Duração Média', 'person-cash-wallet' ); ?></span>
								<span class="value" id="ga4-avg-duration">-</span>
							</div>
							<div class="pcw-ga4-metric-item">
								<span class="label"><?php esc_html_e( 'Páginas/Sessão', 'person-cash-wallet' ); ?></span>
								<span class="value" id="ga4-pages-session">-</span>
							</div>
						</div>
					</div>

					<div class="pcw-ga4-metric-card">
						<h4><?php esc_html_e( 'Fontes de Tráfego', 'person-cash-wallet' ); ?></h4>
						<div class="pcw-ga4-metric-content" id="ga4-traffic-sources">
							<p class="pcw-loading-small"><?php esc_html_e( 'Carregando...', 'person-cash-wallet' ); ?></p>
						</div>
					</div>

					<div class="pcw-ga4-metric-card">
						<h4><?php esc_html_e( 'Dispositivos', 'person-cash-wallet' ); ?></h4>
						<div class="pcw-ga4-metric-content" id="ga4-devices">
							<p class="pcw-loading-small"><?php esc_html_e( 'Carregando...', 'person-cash-wallet' ); ?></p>
						</div>
					</div>

					<div class="pcw-ga4-metric-card">
						<h4><?php esc_html_e( 'Top Países', 'person-cash-wallet' ); ?></h4>
						<div class="pcw-ga4-metric-content" id="ga4-countries">
							<p class="pcw-loading-small"><?php esc_html_e( 'Carregando...', 'person-cash-wallet' ); ?></p>
						</div>
					</div>
				</div>

				<!-- Páginas mais visitadas -->
				<div class="pcw-ga4-top-pages">
					<h4><?php esc_html_e( 'Páginas Mais Visitadas', 'person-cash-wallet' ); ?></h4>
					<div id="ga4-top-pages">
						<p class="pcw-loading-small"><?php esc_html_e( 'Carregando...', 'person-cash-wallet' ); ?></p>
					</div>
				</div>
			</div>
			<?php else : ?>
			<div class="pcw-ga4-not-configured">
				<div class="pcw-notice pcw-notice-info">
					<span class="dashicons dashicons-chart-area"></span>
					<div>
						<strong><?php esc_html_e( 'Google Analytics 4 não configurado', 'person-cash-wallet' ); ?></strong>
						<p><?php esc_html_e( 'Configure a integração para ver métricas detalhadas do GA4 aqui.', 'person-cash-wallet' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-settings&tab=analytics' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Configurar Agora', 'person-cash-wallet' ); ?>
						</a>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<div class="pcw-stats-grid">
				<!-- Wallet Stats -->
				<div class="pcw-stat-card">
					<div class="pcw-stat-header">
						<h3><?php esc_html_e( 'Wallet', 'person-cash-wallet' ); ?></h3>
						<span class="dashicons dashicons-money-alt"></span>
					</div>
					<div class="pcw-stat-content">
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Total em Wallet', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo PCW_Formatters::format_money( $stats['wallet']['total_balance'] ); ?></span>
						</div>
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Usuários com Wallet', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['wallet']['users_count'] ) ); ?></span>
						</div>
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Transações (30 dias)', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['wallet']['transactions_30d'] ) ); ?></span>
						</div>
					</div>
					<div class="pcw-stat-footer">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-wallet' ) ); ?>" class="button">
							<?php esc_html_e( 'Gerenciar Wallet', 'person-cash-wallet' ); ?>
						</a>
					</div>
				</div>

				<!-- Cashback Stats -->
				<div class="pcw-stat-card">
					<div class="pcw-stat-header">
						<h3><?php esc_html_e( 'Cashback', 'person-cash-wallet' ); ?></h3>
						<span class="dashicons dashicons-chart-line"></span>
					</div>
					<div class="pcw-stat-content">
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Cashback Disponível', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo PCW_Formatters::format_money( $stats['cashback']['available'] ); ?></span>
						</div>
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Cashback Utilizado', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo PCW_Formatters::format_money( $stats['cashback']['used'] ); ?></span>
						</div>
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Cashback Expirado', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo PCW_Formatters::format_money( $stats['cashback']['expired'] ); ?></span>
						</div>
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Regras Ativas', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['cashback']['active_rules'] ) ); ?></span>
						</div>
					</div>
					<div class="pcw-stat-footer">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-cashback-rules' ) ); ?>" class="button">
							<?php esc_html_e( 'Gerenciar Cashback', 'person-cash-wallet' ); ?>
						</a>
					</div>
				</div>

				<!-- Levels Stats -->
				<div class="pcw-stat-card">
					<div class="pcw-stat-header">
						<h3><?php esc_html_e( 'Níveis', 'person-cash-wallet' ); ?></h3>
						<span class="dashicons dashicons-awards"></span>
					</div>
					<div class="pcw-stat-content">
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Total de Níveis', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['levels']['total_levels'] ) ); ?></span>
						</div>
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Usuários com Nível', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['levels']['users_with_level'] ) ); ?></span>
						</div>
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Níveis Ativos', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['levels']['active_levels'] ) ); ?></span>
						</div>
					</div>
					<div class="pcw-stat-footer">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-levels' ) ); ?>" class="button">
							<?php esc_html_e( 'Gerenciar Níveis', 'person-cash-wallet' ); ?>
						</a>
					</div>
				</div>

				<!-- Webhooks Stats -->
				<div class="pcw-stat-card">
					<div class="pcw-stat-header">
						<h3><?php esc_html_e( 'Webhooks', 'person-cash-wallet' ); ?></h3>
						<span class="dashicons dashicons-networking"></span>
					</div>
					<div class="pcw-stat-content">
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Webhooks Ativos', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['webhooks']['active'] ) ); ?></span>
						</div>
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Enviados (30 dias)', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['webhooks']['sent_30d'] ) ); ?></span>
						</div>
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Taxa de Sucesso', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo esc_html( $stats['webhooks']['success_rate'] ); ?>%</span>
						</div>
					</div>
					<div class="pcw-stat-footer">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-webhooks' ) ); ?>" class="button">
							<?php esc_html_e( 'Gerenciar Webhooks', 'person-cash-wallet' ); ?>
						</a>
					</div>
				</div>

				<!-- Indicações Stats -->
				<div class="pcw-stat-card">
					<div class="pcw-stat-header">
						<h3><?php esc_html_e( 'Indicações', 'person-cash-wallet' ); ?></h3>
						<span class="dashicons dashicons-share"></span>
					</div>
					<div class="pcw-stat-content">
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Indicações (30 dias)', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['referrals']['total'] ) ); ?></span>
						</div>
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Convertidas', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['referrals']['converted'] ) ); ?></span>
						</div>
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Recompensas Pagas', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo PCW_Formatters::format_money( $stats['referrals']['total_earned'] ); ?></span>
						</div>
						<div class="pcw-stat-item">
							<span class="pcw-stat-label"><?php esc_html_e( 'Indicadores Ativos', 'person-cash-wallet' ); ?></span>
							<span class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['referrals']['active_referrers'] ) ); ?></span>
						</div>
					</div>
					<div class="pcw-stat-footer">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-referrals' ) ); ?>" class="button">
							<?php esc_html_e( 'Gerenciar Indicações', 'person-cash-wallet' ); ?>
						</a>
					</div>
				</div>
			</div>

			<!-- Charts Section -->
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 30px;">
				<!-- Cashback Chart -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-chart-line"></span>
							<?php esc_html_e( 'Cashback - Últimos 7 Dias', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<canvas id="pcwCashbackChart" style="max-height: 300px;"></canvas>
					</div>
				</div>

				<!-- Levels Chart -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-chart-pie"></span>
							<?php esc_html_e( 'Distribuição de Níveis', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<canvas id="pcwLevelsChart" style="max-height: 300px;"></canvas>
					</div>
				</div>

				<!-- Wallet Chart -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-chart-bar"></span>
							<?php esc_html_e( 'Wallet - Últimos 7 Dias', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<canvas id="pcwWalletChart" style="max-height: 300px;"></canvas>
					</div>
				</div>
			</div>

			<!-- Top Clientes -->
			<?php $this->render_top_customers(); ?>

			<!-- Últimos Cashbacks Utilizados -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-tickets-alt"></span>
						<?php esc_html_e( 'Últimos Cashbacks Utilizados', 'person-cash-wallet' ); ?>
					</h2>
				</div>
				<div class="pcw-card-body">
					<?php $this->render_recent_cashback_used(); ?>
				</div>
			</div>

			<!-- Últimas Transações Wallet -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-money-alt"></span>
						<?php esc_html_e( 'Últimas Transações de Wallet', 'person-cash-wallet' ); ?>
					</h2>
				</div>
				<div class="pcw-card-body">
					<?php $this->render_recent_wallet_transactions(); ?>
				</div>
			</div>

			<!-- Atividades Recentes -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-clock"></span>
						<?php esc_html_e( 'Atividades Recentes', 'person-cash-wallet' ); ?>
					</h2>
				</div>
				<div class="pcw-card-body">
					<?php $this->render_recent_activities(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Obter estatísticas
	 *
	 * @return array
	 */
	private function get_stats() {
		global $wpdb;

		$stats = array(
			'wallet'    => array(),
			'cashback'  => array(),
			'levels'    => array(),
			'webhooks'  => array(),
			'referrals' => array(),
		);

		// Wallet Stats
		$wallet_table = $wpdb->prefix . 'pcw_wallet';
		$wallet_transactions_table = $wpdb->prefix . 'pcw_wallet_transactions';

		$stats['wallet']['total_balance'] = (float) $wpdb->get_var(
			"SELECT SUM(balance) FROM {$wallet_table}"
		);

		$stats['wallet']['users_count'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wallet_table} WHERE balance > 0"
		);

		$stats['wallet']['transactions_30d'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wallet_transactions_table} WHERE DATE(created_at) >= %s",
				date( 'Y-m-d', strtotime( '-30 days' ) )
			)
		);

		// Cashback Stats
		$cashback_table = $wpdb->prefix . 'pcw_cashback';
		$cashback_rules_table = $wpdb->prefix . 'pcw_cashback_rules';

		$stats['cashback']['available'] = (float) $wpdb->get_var(
			"SELECT SUM(amount) FROM {$cashback_table} WHERE status = 'available'"
		);

		$stats['cashback']['used'] = (float) $wpdb->get_var(
			"SELECT SUM(amount) FROM {$cashback_table} WHERE status = 'used'"
		);

		$stats['cashback']['expired'] = (float) $wpdb->get_var(
			"SELECT SUM(amount) FROM {$cashback_table} WHERE status = 'expired'"
		);

		$stats['cashback']['active_rules'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$cashback_rules_table} WHERE status = 'active'"
		);

		// Levels Stats
		$levels_table = $wpdb->prefix . 'pcw_levels';
		$user_levels_table = $wpdb->prefix . 'pcw_user_levels';

		$stats['levels']['total_levels'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$levels_table}"
		);

		$stats['levels']['active_levels'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$levels_table} WHERE status = 'active'"
		);

		$stats['levels']['users_with_level'] = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT user_id) FROM {$user_levels_table} WHERE status = 'active'"
		);

		// Webhooks Stats
		$webhooks_table = $wpdb->prefix . 'pcw_webhooks';
		$webhook_logs_table = $wpdb->prefix . 'pcw_webhook_logs';

		$stats['webhooks']['active'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$webhooks_table} WHERE status = 'active'"
		);

		$stats['webhooks']['sent_30d'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$webhook_logs_table} WHERE DATE(created_at) >= %s",
				date( 'Y-m-d', strtotime( '-30 days' ) )
			)
		);

		$total_webhooks = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$webhook_logs_table} WHERE DATE(created_at) >= %s",
				date( 'Y-m-d', strtotime( '-30 days' ) )
			)
		);

		$success_webhooks = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$webhook_logs_table} WHERE status = 'success' AND DATE(created_at) >= %s",
				date( 'Y-m-d', strtotime( '-30 days' ) )
			)
		);

		$stats['webhooks']['success_rate'] = $total_webhooks > 0 ? round( ( $success_webhooks / $total_webhooks ) * 100, 1 ) : 0;

		// Referrals Stats
		$referrals_table = $wpdb->prefix . 'pcw_referrals';
		$referral_codes_table = $wpdb->prefix . 'pcw_referral_codes';

		$stats['referrals']['total'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$referrals_table} WHERE DATE(created_at) >= %s",
				date( 'Y-m-d', strtotime( '-30 days' ) )
			)
		);

		$stats['referrals']['converted'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$referrals_table} WHERE status IN ('converted', 'rewarded') AND DATE(created_at) >= %s",
				date( 'Y-m-d', strtotime( '-30 days' ) )
			)
		);

		$stats['referrals']['total_earned'] = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(reward_amount), 0) FROM {$referrals_table} WHERE status = 'rewarded' AND DATE(rewarded_at) >= %s",
				date( 'Y-m-d', strtotime( '-30 days' ) )
			)
		);

		$stats['referrals']['active_referrers'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$referral_codes_table} WHERE status = 'active' AND total_conversions > 0"
		);

		return $stats;
	}

	/**
	 * Renderizar atividades recentes
	 */
	private function render_recent_activities() {
		global $wpdb;

		$activities = array();

		// Cashback recente
		$cashback_table = $wpdb->prefix . 'pcw_cashback';
		$recent_cashback = $wpdb->get_results(
			"SELECT c.*, u.display_name 
			FROM {$cashback_table} c
			LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
			ORDER BY c.earned_date DESC
			LIMIT 5"
		);

		foreach ( $recent_cashback as $cashback ) {
			$date = ! empty( $cashback->earned_date ) ? $cashback->earned_date : $cashback->created_at;
			$activities[] = array(
				'type'      => 'cashback',
				'title'     => sprintf( __( 'Cashback de %s para %s', 'person-cash-wallet' ), PCW_Formatters::format_money_plain( $cashback->amount ), $cashback->display_name ),
				'time'      => strtotime( $date ),
				'time_human' => human_time_diff( strtotime( $date ), current_time( 'timestamp' ) ),
				'status'    => $cashback->status,
			);
		}

		// Transações wallet recentes
		$wallet_transactions_table = $wpdb->prefix . 'pcw_wallet_transactions';
		$recent_transactions = $wpdb->get_results(
			"SELECT t.*, u.display_name 
			FROM {$wallet_transactions_table} t
			LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
			ORDER BY t.created_at DESC
			LIMIT 5"
		);

		foreach ( $recent_transactions as $transaction ) {
			$type_label = 'credit' === $transaction->type ? __( 'Crédito', 'person-cash-wallet' ) : __( 'Débito', 'person-cash-wallet' );
			$activities[] = array(
				'type'      => 'wallet',
				'title'     => sprintf( __( '%s de %s na wallet de %s', 'person-cash-wallet' ), $type_label, PCW_Formatters::format_money_plain( $transaction->amount ), $transaction->display_name ),
				'time'      => strtotime( $transaction->created_at ),
				'time_human' => human_time_diff( strtotime( $transaction->created_at ), current_time( 'timestamp' ) ),
				'status'    => 'success',
			);
		}

		// Ordenar por data (mais recente primeiro)
		usort( $activities, function( $a, $b ) {
			return $b['time'] - $a['time'];
		} );

		$activities = array_slice( $activities, 0, 10 );

		if ( empty( $activities ) ) {
			?>
			<div class="pcw-empty-state">
				<span class="dashicons dashicons-clock"></span>
				<h3><?php esc_html_e( 'Nenhuma atividade ainda', 'person-cash-wallet' ); ?></h3>
				<p><?php esc_html_e( 'Quando houver atividades no sistema, elas aparecerão aqui.', 'person-cash-wallet' ); ?></p>
			</div>
			<?php
			return;
		}

		?>
		<ul style="list-style: none; margin: 0; padding: 0;">
			<?php foreach ( $activities as $activity ) : ?>
				<?php
				$icon = 'cashback' === $activity['type'] ? 'chart-line' : 'money-alt';
				$icon_color = 'cashback' === $activity['type'] ? '#667eea' : '#00a32a';
				?>
				<li style="display: flex; align-items: flex-start; gap: 12px; padding: 16px; border-bottom: 1px solid #dcdcde; transition: background 0.2s ease;">
					<div style="width: 32px; height: 32px; background: <?php echo esc_attr( $icon_color ); ?>33; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
						<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>" style="color: <?php echo esc_attr( $icon_color ); ?>; font-size: 16px;"></span>
					</div>
					<div style="flex: 1; min-width: 0;">
						<div style="font-size: 14px; color: #1d2327; line-height: 1.5;">
							<?php echo esc_html( $activity['title'] ); ?>
						</div>
						<div style="font-size: 12px; color: #646970; margin-top: 4px;">
							<span class="dashicons dashicons-clock" style="font-size: 12px; vertical-align: middle;"></span>
							<?php echo esc_html( $activity['time_human'] ); ?> <?php esc_html_e( 'atrás', 'person-cash-wallet' ); ?>
						</div>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Renderizar top clientes
	 */
	private function render_top_customers() {
		global $wpdb;

		$wallet_table = $wpdb->prefix . 'pcw_wallet';
		$cashback_table = $wpdb->prefix . 'pcw_cashback';

		// Top 5 clientes por saldo de wallet
		$top_wallet = $wpdb->get_results(
			"SELECT w.user_id, w.balance, u.display_name, u.user_email
			FROM {$wallet_table} w
			LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID
			WHERE w.balance > 0
			ORDER BY w.balance DESC
			LIMIT 5"
		);

		// Top 5 clientes por cashback ganho
		$top_cashback = $wpdb->get_results(
			"SELECT c.user_id, SUM(c.amount) as total_cashback, u.display_name, u.user_email
			FROM {$cashback_table} c
			LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
			WHERE c.status IN ('available', 'used')
			GROUP BY c.user_id
			ORDER BY total_cashback DESC
			LIMIT 5"
		);

		?>
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
			<!-- Maior Saldo em Wallet -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h3 style="margin: 0; font-size: 16px;">
						<span class="dashicons dashicons-money-alt" style="color: #667eea;"></span>
						<?php esc_html_e( 'Maior Saldo em Wallet', 'person-cash-wallet' ); ?>
					</h3>
					<span class="pcw-badge pcw-badge-info"><?php echo esc_html( count( $top_wallet ) ); ?></span>
				</div>
				<div class="pcw-card-body" style="padding: 0;">
					<?php if ( empty( $top_wallet ) ) : ?>
						<div class="pcw-empty-state" style="padding: 40px 20px;">
							<span class="dashicons dashicons-admin-users" style="font-size: 48px; opacity: 0.3;"></span>
							<p style="margin: 10px 0 0; color: #646970;"><?php esc_html_e( 'Nenhum cliente com saldo ainda.', 'person-cash-wallet' ); ?></p>
						</div>
					<?php else : ?>
						<ul style="list-style: none; margin: 0; padding: 0;">
							<?php foreach ( $top_wallet as $index => $customer ) : ?>
								<li style="display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid #dcdcde;">
									<div style="width: 32px; height: 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 14px;">
										<?php echo esc_html( $index + 1 ); ?>
									</div>
									<div style="flex: 1; min-width: 0;">
										<div style="font-weight: 600; font-size: 14px; color: #1d2327; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
											<?php echo esc_html( $customer->display_name ); ?>
										</div>
										<div style="font-size: 12px; color: #646970; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
											<?php echo esc_html( $customer->user_email ); ?>
										</div>
									</div>
									<div style="font-weight: 700; font-size: 15px; color: #00a32a; white-space: nowrap;">
										<?php echo wp_kses_post( PCW_Formatters::format_money( $customer->balance ) ); ?>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>

			<!-- Mais Cashback Ganho -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h3 style="margin: 0; font-size: 16px;">
						<span class="dashicons dashicons-chart-line" style="color: #f5576c;"></span>
						<?php esc_html_e( 'Mais Cashback Ganho', 'person-cash-wallet' ); ?>
					</h3>
					<span class="pcw-badge pcw-badge-info"><?php echo esc_html( count( $top_cashback ) ); ?></span>
				</div>
				<div class="pcw-card-body" style="padding: 0;">
					<?php if ( empty( $top_cashback ) ) : ?>
						<div class="pcw-empty-state" style="padding: 40px 20px;">
							<span class="dashicons dashicons-chart-line" style="font-size: 48px; opacity: 0.3;"></span>
							<p style="margin: 10px 0 0; color: #646970;"><?php esc_html_e( 'Nenhum cashback gerado ainda.', 'person-cash-wallet' ); ?></p>
						</div>
					<?php else : ?>
						<ul style="list-style: none; margin: 0; padding: 0;">
							<?php foreach ( $top_cashback as $index => $customer ) : ?>
								<li style="display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid #dcdcde;">
									<div style="width: 32px; height: 32px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 14px;">
										<?php echo esc_html( $index + 1 ); ?>
									</div>
									<div style="flex: 1; min-width: 0;">
										<div style="font-weight: 600; font-size: 14px; color: #1d2327; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
											<?php echo esc_html( $customer->display_name ); ?>
										</div>
										<div style="font-size: 12px; color: #646970; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
											<?php echo esc_html( $customer->user_email ); ?>
										</div>
									</div>
									<div style="font-weight: 700; font-size: 15px; color: #f5576c; white-space: nowrap;">
										<?php echo wp_kses_post( PCW_Formatters::format_money( $customer->total_cashback ) ); ?>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderizar últimos cashbacks utilizados
	 */
	private function render_recent_cashback_used() {
		global $wpdb;

		$cashback_table = $wpdb->prefix . 'pcw_cashback';

		$recent_used = $wpdb->get_results(
			"SELECT c.*, u.display_name, u.user_email, o.ID as order_id
			FROM {$cashback_table} c
			LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
			LEFT JOIN {$wpdb->posts} o ON c.order_id = o.ID
			WHERE c.status = 'used'
			ORDER BY COALESCE(c.used_date, c.updated_at) DESC
			LIMIT 10"
		);

		if ( empty( $recent_used ) ) {
			?>
			<div class="pcw-empty-state">
				<span class="dashicons dashicons-chart-line"></span>
				<h3><?php esc_html_e( 'Nenhum cashback utilizado', 'person-cash-wallet' ); ?></h3>
				<p><?php esc_html_e( 'Quando seus clientes utilizarem cashback, aparecerá aqui.', 'person-cash-wallet' ); ?></p>
			</div>
			<?php
			return;
		}

		?>
		<div class="pcw-table-wrapper">
			<table class="pcw-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Cliente', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Valor', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Pedido', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Data', 'person-cash-wallet' ); ?></th>
					</tr>
				</thead>
			<tbody>
				<?php foreach ( $recent_used as $cashback ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $cashback->display_name ); ?></strong>
							<br>
							<small><?php echo esc_html( $cashback->user_email ); ?></small>
						</td>
						<td>
							<span class="pcw-amount-positive"><?php echo PCW_Formatters::format_money( $cashback->amount ); ?></span>
						</td>
						<td>
							<?php if ( $cashback->order_id ) : ?>
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $cashback->order_id . '&action=edit' ) ); ?>" target="_blank">
									#<?php echo esc_html( $cashback->order_id ); ?>
								</a>
							<?php else : ?>
								-
							<?php endif; ?>
						</td>
						<td>
							<?php
							$date_to_show = ! empty( $cashback->used_date ) ? $cashback->used_date : $cashback->updated_at;
							if ( $date_to_show ) {
								echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $date_to_show ) ) );
							} else {
								echo '-';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Renderizar últimas transações de wallet
	 */
	private function render_recent_wallet_transactions() {
		global $wpdb;

		$wallet_transactions_table = $wpdb->prefix . 'pcw_wallet_transactions';

		$recent_transactions = $wpdb->get_results(
			"SELECT t.*, u.display_name, u.user_email
			FROM {$wallet_transactions_table} t
			LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
			ORDER BY t.created_at DESC
			LIMIT 15"
		);

		if ( empty( $recent_transactions ) ) {
			?>
			<div class="pcw-empty-state">
				<span class="dashicons dashicons-money-alt"></span>
				<h3><?php esc_html_e( 'Nenhuma transação ainda', 'person-cash-wallet' ); ?></h3>
				<p><?php esc_html_e( 'As transações de wallet dos seus clientes aparecerão aqui.', 'person-cash-wallet' ); ?></p>
			</div>
			<?php
			return;
		}

		?>
		<div class="pcw-table-wrapper">
			<table class="pcw-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Cliente', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Tipo', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Valor', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Fonte', 'person-cash-wallet' ); ?></th>
						<th><?php esc_html_e( 'Data', 'person-cash-wallet' ); ?></th>
					</tr>
				</thead>
			<tbody>
				<?php foreach ( $recent_transactions as $transaction ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $transaction->display_name ); ?></strong>
							<br>
							<small><?php echo esc_html( $transaction->user_email ); ?></small>
						</td>
						<td>
							<?php
							if ( 'credit' === $transaction->type ) {
								echo '<span class="pcw-badge pcw-badge-success">' . esc_html__( 'Crédito', 'person-cash-wallet' ) . '</span>';
							} else {
								echo '<span class="pcw-badge pcw-badge-danger">' . esc_html__( 'Débito', 'person-cash-wallet' ) . '</span>';
							}
							?>
						</td>
						<td>
							<?php if ( 'credit' === $transaction->type ) : ?>
								<span class="pcw-amount-positive">+<?php echo PCW_Formatters::format_money( $transaction->amount ); ?></span>
							<?php else : ?>
								<span class="pcw-amount-negative">-<?php echo PCW_Formatters::format_money( $transaction->amount ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							$source_labels = array(
								'cashback'   => __( 'Cashback', 'person-cash-wallet' ),
								'manual'     => __( 'Manual', 'person-cash-wallet' ),
								'refund'     => __( 'Reembolso', 'person-cash-wallet' ),
								'purchase'   => __( 'Compra', 'person-cash-wallet' ),
								'adjustment' => __( 'Ajuste', 'person-cash-wallet' ),
							);
							echo esc_html( isset( $source_labels[ $transaction->source ] ) ? $source_labels[ $transaction->source ] : $transaction->source );
							?>
						</td>
						<td>
							<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $transaction->created_at ) ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Obter dados para gráfico de cashback
	 *
	 * @return array
	 */
	private function get_cashback_chart_data() {
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_cashback';

		// Últimos 7 dias
		$data = array();
		$labels = array();

		for ( $i = 6; $i >= 0; $i-- ) {
			$date = date( 'Y-m-d', strtotime( "-$i days" ) );
			$labels[] = date_i18n( 'd/m', strtotime( $date ) );

			// Cashback ganho (earned_date)
			$earned = $wpdb->get_var( $wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE status IN ('pending', 'available', 'used', 'expired') AND DATE(earned_date) = %s",
				$date
			) );

			// Cashback utilizado (used_date)
			$used = $wpdb->get_var( $wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE status = 'used' AND DATE(COALESCE(used_date, updated_at)) = %s",
				$date
			) );

			$data['earned'][] = floatval( $earned );
			$data['used'][] = floatval( $used );
		}

		return array(
			'labels' => $labels,
			'earned' => $data['earned'],
			'used'   => $data['used'],
		);
	}

	/**
	 * Obter dados para gráfico de níveis
	 *
	 * @return array
	 */
	private function get_levels_chart_data() {
		global $wpdb;
		$levels_table = $wpdb->prefix . 'pcw_levels';
		$user_levels_table = $wpdb->prefix . 'pcw_user_levels';

		// Top 5 níveis
		$levels = $wpdb->get_results( "
			SELECT 
				l.name,
				l.color,
				COUNT(ul.user_id) as user_count
			FROM {$levels_table} l
			LEFT JOIN {$user_levels_table} ul ON l.id = ul.level_id AND ul.status = 'active'
			WHERE l.status = 'active'
			GROUP BY l.id
			ORDER BY user_count DESC
			LIMIT 5
		" );

		$labels = array();
		$data = array();
		$colors = array();

		foreach ( $levels as $level ) {
			$labels[] = $level->name;
			$data[] = intval( $level->user_count );
			$colors[] = $level->color;
		}

		return array(
			'labels' => $labels,
			'data'   => $data,
			'colors' => $colors,
		);
	}

	/**
	 * Obter dados para gráfico de wallet
	 *
	 * @return array
	 */
	private function get_wallet_chart_data() {
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_wallet_transactions';

		// Últimos 7 dias - Total de créditos vs débitos
		$data = array();
		$labels = array();

		for ( $i = 6; $i >= 0; $i-- ) {
			$date = date( 'Y-m-d', strtotime( "-$i days" ) );
			$labels[] = date_i18n( 'd/m', strtotime( $date ) );

			// Créditos
			$credits = $wpdb->get_var( $wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE type = 'credit' AND DATE(created_at) = %s",
				$date
			) );

			// Débitos
			$debits = $wpdb->get_var( $wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE type = 'debit' AND DATE(created_at) = %s",
				$date
			) );

			$data['credits'][] = floatval( $credits );
			$data['debits'][] = floatval( $debits );
		}

		return array(
			'labels'  => $labels,
			'credits' => $data['credits'],
			'debits'  => $data['debits'],
		);
	}
}
