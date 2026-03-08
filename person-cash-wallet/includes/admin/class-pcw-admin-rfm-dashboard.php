<?php
/**
 * Dashboard RFM e Analytics
 *
 * @package PersonCashWallet
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de dashboard RFM
 */
class PCW_Admin_RFM_Dashboard {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Admin_RFM_Dashboard
	 */
	private static $instance = null;

	/**
	 * Obter instância
	 *
	 * @return PCW_Admin_RFM_Dashboard
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_pcw_recalculate_rfm', array( $this, 'ajax_recalculate_rfm' ) );
	}

	/**
	 * Adicionar página no menu
	 */
	public function add_menu_page() {
		add_submenu_page(
			'pcw-dashboard',
			__( 'Análise RFM', 'person-cash-wallet' ),
			__( 'Análise RFM', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-rfm-analysis',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enfileirar assets
	 *
	 * @param string $hook Hook da página.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'growly-digital_page_pcw-rfm-analysis' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'pcw-rfm-dashboard', PCW_PLUGIN_URL . 'assets/css/admin-rfm-dashboard.css', array(), PCW_VERSION );
		wp_enqueue_script( 'pcw-rfm-dashboard', PCW_PLUGIN_URL . 'assets/js/admin-rfm-dashboard.js', array( 'jquery', 'chart-js' ), PCW_VERSION, true );

		wp_localize_script(
			'pcw-rfm-dashboard',
			'pcwRFM',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'pcw_rfm_actions' ),
			)
		);
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		$rfm = PCW_RFM_Analysis::instance();
		$stats = $rfm->get_segments_stats();

		// Calcular totais
		$total_customers = 0;
		$total_revenue = 0;

		foreach ( $stats as $segment_stat ) {
			$total_customers += $segment_stat->total_customers;
			$total_revenue += $segment_stat->total_revenue;
		}

		?>
		<div class="wrap pcw-rfm-dashboard">
			<h1><?php esc_html_e( 'Análise RFM - Segmentação de Clientes', 'person-cash-wallet' ); ?></h1>

			<div class="pcw-rfm-header">
				<p class="description">
					<?php esc_html_e( 'RFM (Recency, Frequency, Monetary) é uma análise que segmenta seus clientes baseado em quando compraram (Recência), quantas vezes compraram (Frequência) e quanto gastaram (Monetário).', 'person-cash-wallet' ); ?>
				</p>

				<button type="button" class="button button-primary" id="pcw-recalculate-rfm">
					<?php esc_html_e( 'Recalcular Segmentos', 'person-cash-wallet' ); ?>
				</button>
			</div>

			<div class="pcw-rfm-summary">
				<div class="pcw-stat-card">
					<h3><?php esc_html_e( 'Total de Clientes', 'person-cash-wallet' ); ?></h3>
					<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $total_customers ) ); ?></div>
				</div>

				<div class="pcw-stat-card">
					<h3><?php esc_html_e( 'Receita Total', 'person-cash-wallet' ); ?></h3>
					<div class="pcw-stat-value"><?php echo wp_kses_post( wc_price( $total_revenue ) ); ?></div>
				</div>

				<div class="pcw-stat-card">
					<h3><?php esc_html_e( 'Ticket Médio', 'person-cash-wallet' ); ?></h3>
					<div class="pcw-stat-value">
						<?php echo $total_customers > 0 ? wp_kses_post( wc_price( $total_revenue / $total_customers ) ) : wc_price( 0 ); ?>
					</div>
				</div>
			</div>

			<div class="pcw-rfm-segments">
				<h2><?php esc_html_e( 'Segmentos de Clientes', 'person-cash-wallet' ); ?></h2>

				<div class="pcw-segments-grid">
					<?php foreach ( $stats as $segment_stat ) : ?>
						<?php
						$segment_label = PCW_RFM_Analysis::get_segment_label( $segment_stat->segment );
						$segment_description = PCW_RFM_Analysis::get_segment_description( $segment_stat->segment );
						$segment_color = PCW_RFM_Analysis::get_segment_color( $segment_stat->segment );
						$percentage = $total_customers > 0 ? ( $segment_stat->total_customers / $total_customers ) * 100 : 0;
						?>
						<div class="pcw-segment-card" style="border-left: 4px solid <?php echo esc_attr( $segment_color ); ?>;">
							<div class="pcw-segment-header">
								<h3><?php echo esc_html( $segment_label ); ?></h3>
								<span class="pcw-segment-badge" style="background-color: <?php echo esc_attr( $segment_color ); ?>;">
									<?php echo esc_html( number_format( $percentage, 1 ) ); ?>%
								</span>
							</div>

							<p class="pcw-segment-description"><?php echo esc_html( $segment_description ); ?></p>

							<div class="pcw-segment-stats">
								<div class="pcw-segment-stat">
									<span class="label"><?php esc_html_e( 'Clientes:', 'person-cash-wallet' ); ?></span>
									<span class="value"><?php echo esc_html( number_format_i18n( $segment_stat->total_customers ) ); ?></span>
								</div>

								<div class="pcw-segment-stat">
									<span class="label"><?php esc_html_e( 'Receita:', 'person-cash-wallet' ); ?></span>
									<span class="value"><?php echo wp_kses_post( wc_price( $segment_stat->total_revenue ) ); ?></span>
								</div>

								<div class="pcw-segment-stat">
									<span class="label"><?php esc_html_e( 'Ticket Médio:', 'person-cash-wallet' ); ?></span>
									<span class="value"><?php echo wp_kses_post( wc_price( $segment_stat->avg_revenue ) ); ?></span>
								</div>

								<div class="pcw-segment-stat">
									<span class="label"><?php esc_html_e( 'Pedidos Médios:', 'person-cash-wallet' ); ?></span>
									<span class="value"><?php echo esc_html( number_format( $segment_stat->avg_orders, 1 ) ); ?></span>
								</div>

								<div class="pcw-segment-stat">
									<span class="label"><?php esc_html_e( 'Dias desde última compra:', 'person-cash-wallet' ); ?></span>
									<span class="value"><?php echo esc_html( number_format( $segment_stat->avg_days_since_purchase, 0 ) ); ?></span>
								</div>
							</div>

							<div class="pcw-segment-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-campaigns&segment=' . $segment_stat->segment ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Criar Campanha', 'person-cash-wallet' ); ?>
								</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="pcw-rfm-recommendations">
				<h2><?php esc_html_e( 'Recomendações de Ações', 'person-cash-wallet' ); ?></h2>

				<div class="pcw-recommendations-grid">
					<div class="pcw-recommendation-card">
						<h3><?php esc_html_e( 'Campeões', 'person-cash-wallet' ); ?></h3>
						<p><?php esc_html_e( 'Recompense com programas VIP, produtos em primeira mão e atendimento prioritário.', 'person-cash-wallet' ); ?></p>
					</div>

					<div class="pcw-recommendation-card">
						<h3><?php esc_html_e( 'Clientes Fiéis', 'person-cash-wallet' ); ?></h3>
						<p><?php esc_html_e( 'Incentive com programas de fidelidade e cashback aumentado.', 'person-cash-wallet' ); ?></p>
					</div>

					<div class="pcw-recommendation-card">
						<h3><?php esc_html_e( 'Em Risco', 'person-cash-wallet' ); ?></h3>
						<p><?php esc_html_e( 'Envie ofertas personalizadas e pesquisas de satisfação urgentes.', 'person-cash-wallet' ); ?></p>
					</div>

					<div class="pcw-recommendation-card">
						<h3><?php esc_html_e( 'Não Pode Perder', 'person-cash-wallet' ); ?></h3>
						<p><?php esc_html_e( 'Contato direto, ofertas especiais e descontos exclusivos imediatamente.', 'person-cash-wallet' ); ?></p>
					</div>

					<div class="pcw-recommendation-card">
						<h3><?php esc_html_e( 'Hibernando', 'person-cash-wallet' ); ?></h3>
						<p><?php esc_html_e( 'Campanhas de reativação com ofertas agressivas e novos produtos.', 'person-cash-wallet' ); ?></p>
					</div>

					<div class="pcw-recommendation-card">
						<h3><?php esc_html_e( 'Novos Clientes', 'person-cash-wallet' ); ?></h3>
						<p><?php esc_html_e( 'Onboarding personalizado, educação sobre produtos e suporte ativo.', 'person-cash-wallet' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Recalcular RFM
	 */
	public function ajax_recalculate_rfm() {
		check_ajax_referer( 'pcw_rfm_actions', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$rfm = PCW_RFM_Analysis::instance();
		$result = $rfm->calculate_all_customers();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}
}
