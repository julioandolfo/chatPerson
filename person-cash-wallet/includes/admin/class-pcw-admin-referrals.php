<?php
/**
 * Classe de administração de indicações
 *
 * @package PersonCashWallet
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe admin de indicações
 */
class PCW_Admin_Referrals {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Admin_Referrals
	 */
	private static $instance = null;

	/**
	 * Obter instância singleton
	 *
	 * @return PCW_Admin_Referrals
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor
	 */
	private function __construct() {
		// Singleton
	}

	/**
	 * Inicializar hooks
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_pcw_admin_referral_action', array( $this, 'handle_ajax_action' ) );
	}

	/**
	 * Adicionar menu
	 */
	public function add_menu() {
		add_submenu_page(
			'pcw-dashboard',
			__( 'Indicações', 'person-cash-wallet' ),
			__( 'Indicações', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-referrals',
			array( $this, 'render_page' ),
			20
		);
	}

	/**
	 * Enfileirar scripts
	 *
	 * @param string $hook Hook da página.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'pcw-dashboard_page_pcw-referrals' !== $hook && 'growly-digital_page_pcw-referrals' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'pcw-admin-referrals',
			PCW_PLUGIN_URL . 'assets/css/admin-referrals.css',
			array(),
			PCW_VERSION
		);

		wp_enqueue_script(
			'pcw-admin-referrals',
			PCW_PLUGIN_URL . 'assets/js/admin-referrals.js',
			array( 'jquery', 'wp-util' ),
			PCW_VERSION,
			true
		);

		wp_localize_script( 'pcw-admin-referrals', 'pcwAdminReferrals', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pcw_admin_referrals' ),
			'i18n'    => array(
				'confirmDelete' => __( 'Tem certeza que deseja excluir esta indicação?', 'person-cash-wallet' ),
				'saved'         => __( 'Configurações salvas!', 'person-cash-wallet' ),
				'error'         => __( 'Erro ao salvar.', 'person-cash-wallet' ),
			),
		) );
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';

		$tabs = array(
			'dashboard'   => __( 'Dashboard', 'person-cash-wallet' ),
			'referrals'   => __( 'Todas Indicações', 'person-cash-wallet' ),
			'referrers'   => __( 'Indicadores', 'person-cash-wallet' ),
		);

		?>
		<div class="wrap pcw-admin-wrap">
			<h1><?php esc_html_e( 'Indicações', 'person-cash-wallet' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-referrals&tab=' . $tab_id ) ); ?>" 
					   class="nav-tab <?php echo $tab === $tab_id ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_name ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="pcw-admin-content">
				<?php
				switch ( $tab ) {
					case 'dashboard':
						$this->render_dashboard();
						break;
					case 'referrals':
						$this->render_referrals_list();
						break;
					case 'referrers':
						$this->render_referrers_list();
						break;
					default:
						$this->render_dashboard();
						break;
				}
				?>
			</div>
		</div>

		<style>
			.pcw-admin-wrap {
				max-width: 1200px;
			}
			.pcw-admin-content {
				margin-top: 20px;
			}
			.pcw-stats-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 20px;
				margin-bottom: 30px;
			}
			.pcw-stat-card {
				background: #fff;
				padding: 25px;
				border-radius: 8px;
				box-shadow: 0 1px 3px rgba(0,0,0,0.1);
				text-align: center;
			}
			.pcw-stat-value {
				font-size: 36px;
				font-weight: 700;
				color: #667eea;
			}
			.pcw-stat-label {
				font-size: 13px;
				color: #666;
				margin-top: 5px;
			}
			.pcw-stat-card.highlight {
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				color: white;
			}
			.pcw-stat-card.highlight .pcw-stat-value,
			.pcw-stat-card.highlight .pcw-stat-label {
				color: white;
			}
			.pcw-section {
				background: #fff;
				padding: 25px;
				border-radius: 8px;
				box-shadow: 0 1px 3px rgba(0,0,0,0.1);
				margin-bottom: 20px;
			}
			.pcw-section-title {
				font-size: 16px;
				font-weight: 600;
				margin: 0 0 20px 0;
				padding-bottom: 10px;
				border-bottom: 1px solid #eee;
			}
			.pcw-table {
				width: 100%;
				border-collapse: collapse;
			}
			.pcw-table th,
			.pcw-table td {
				padding: 12px 15px;
				text-align: left;
				border-bottom: 1px solid #eee;
			}
			.pcw-table th {
				font-weight: 600;
				background: #f8f9fa;
			}
			.pcw-table tr:hover {
				background: #f8f9fa;
			}
			.pcw-badge {
				display: inline-block;
				padding: 4px 10px;
				border-radius: 20px;
				font-size: 11px;
				font-weight: 500;
			}
			.pcw-badge-pending {
				background: #fef3c7;
				color: #92400e;
			}
			.pcw-badge-converted,
			.pcw-badge-rewarded {
				background: #d1fae5;
				color: #065f46;
			}
			.pcw-badge-expired,
			.pcw-badge-cancelled {
				background: #fee2e2;
				color: #991b1b;
			}
			.pcw-form-row {
				margin-bottom: 20px;
			}
			.pcw-form-row label {
				display: block;
				font-weight: 500;
				margin-bottom: 5px;
			}
			.pcw-form-row input[type="text"],
			.pcw-form-row input[type="number"],
			.pcw-form-row select,
			.pcw-form-row textarea {
				width: 100%;
				max-width: 400px;
				padding: 8px 12px;
			}
			.pcw-form-row .description {
				margin-top: 5px;
				color: #666;
				font-size: 13px;
			}
			.pcw-form-row-inline {
				display: flex;
				gap: 15px;
				align-items: center;
			}
			.pcw-actions {
				display: flex;
				gap: 5px;
			}
			.pcw-action-btn {
				padding: 5px 10px;
				font-size: 12px;
				cursor: pointer;
			}
			.pcw-top-referrers {
				list-style: none;
				padding: 0;
				margin: 0;
			}
			.pcw-top-referrer {
				display: flex;
				align-items: center;
				padding: 12px 0;
				border-bottom: 1px solid #eee;
			}
			.pcw-top-referrer:last-child {
				border-bottom: none;
			}
			.pcw-rank {
				width: 30px;
				height: 30px;
				background: #667eea;
				color: white;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				font-weight: 700;
				font-size: 14px;
				margin-right: 15px;
			}
			.pcw-rank.gold {
				background: #f59e0b;
			}
			.pcw-rank.silver {
				background: #9ca3af;
			}
			.pcw-rank.bronze {
				background: #b45309;
			}
			.pcw-referrer-info {
				flex: 1;
			}
			.pcw-referrer-name {
				font-weight: 600;
			}
			.pcw-referrer-code {
				font-size: 12px;
				color: #666;
			}
			.pcw-referrer-stats {
				text-align: right;
			}
			.pcw-referrer-conversions {
				font-weight: 700;
				color: #059669;
			}
			.pcw-referrer-earned {
				font-size: 12px;
				color: #666;
			}
			.pcw-empty {
				text-align: center;
				padding: 40px;
				color: #666;
			}
			.pcw-filters {
				display: flex;
				gap: 10px;
				margin-bottom: 20px;
				flex-wrap: wrap;
			}
			.pcw-pagination {
				display: flex;
				gap: 5px;
				margin-top: 20px;
				justify-content: center;
			}
		</style>
		<?php
	}

	/**
	 * Renderizar dashboard
	 */
	private function render_dashboard() {
		$referrals = PCW_Referrals::instance();
		$codes = PCW_Referral_Codes::instance();
		$emails = PCW_Referral_Emails::instance();

		$stats = $referrals->get_stats( 'month' );
		$chart_data = $referrals->get_chart_data( 30 );
		$top_referrers = $codes->get_top_referrers( 10 );
		$email_stats = $emails->get_email_stats();

		?>
		<div class="pcw-stats-grid">
			<div class="pcw-stat-card">
				<div class="pcw-stat-value"><?php echo esc_html( $stats['total'] ); ?></div>
				<div class="pcw-stat-label"><?php esc_html_e( 'Indicações (30 dias)', 'person-cash-wallet' ); ?></div>
			</div>
			<div class="pcw-stat-card">
				<div class="pcw-stat-value"><?php echo esc_html( $stats['converted'] ); ?></div>
				<div class="pcw-stat-label"><?php esc_html_e( 'Convertidas', 'person-cash-wallet' ); ?></div>
			</div>
			<div class="pcw-stat-card">
				<div class="pcw-stat-value"><?php echo esc_html( $stats['conversion_rate'] ); ?>%</div>
				<div class="pcw-stat-label"><?php esc_html_e( 'Taxa de Conversão', 'person-cash-wallet' ); ?></div>
			</div>
			<div class="pcw-stat-card highlight">
				<div class="pcw-stat-value"><?php echo wp_kses_post( PCW_Formatters::format_money( $stats['total_earned'] ) ); ?></div>
				<div class="pcw-stat-label"><?php esc_html_e( 'Recompensas Pagas', 'person-cash-wallet' ); ?></div>
			</div>
		</div>

		<div class="pcw-stats-grid">
			<div class="pcw-stat-card">
				<div class="pcw-stat-value"><?php echo esc_html( $email_stats['total'] ); ?></div>
				<div class="pcw-stat-label"><?php esc_html_e( 'Emails Enviados', 'person-cash-wallet' ); ?></div>
			</div>
			<div class="pcw-stat-card">
				<div class="pcw-stat-value"><?php echo esc_html( $email_stats['open_rate'] ); ?>%</div>
				<div class="pcw-stat-label"><?php esc_html_e( 'Taxa de Abertura', 'person-cash-wallet' ); ?></div>
			</div>
			<div class="pcw-stat-card">
				<div class="pcw-stat-value"><?php echo esc_html( $email_stats['click_rate'] ); ?>%</div>
				<div class="pcw-stat-label"><?php esc_html_e( 'Taxa de Clique', 'person-cash-wallet' ); ?></div>
			</div>
		</div>

		<div class="pcw-section">
			<h2 class="pcw-section-title"><?php esc_html_e( 'Top Indicadores', 'person-cash-wallet' ); ?></h2>

			<?php if ( empty( $top_referrers ) ) : ?>
				<p class="pcw-empty"><?php esc_html_e( 'Nenhum indicador com conversões ainda.', 'person-cash-wallet' ); ?></p>
			<?php else : ?>
				<ul class="pcw-top-referrers">
					<?php foreach ( $top_referrers as $index => $referrer ) : ?>
						<?php
						$rank_class = '';
						if ( 0 === $index ) $rank_class = 'gold';
						elseif ( 1 === $index ) $rank_class = 'silver';
						elseif ( 2 === $index ) $rank_class = 'bronze';
						?>
						<li class="pcw-top-referrer">
							<span class="pcw-rank <?php echo esc_attr( $rank_class ); ?>"><?php echo esc_html( $index + 1 ); ?></span>
							<div class="pcw-referrer-info">
								<div class="pcw-referrer-name"><?php echo esc_html( $referrer->display_name ); ?></div>
								<div class="pcw-referrer-code"><?php echo esc_html( $referrer->code ); ?> • <?php echo esc_html( $referrer->user_email ); ?></div>
							</div>
							<div class="pcw-referrer-stats">
								<div class="pcw-referrer-conversions"><?php echo esc_html( $referrer->total_conversions ); ?> <?php esc_html_e( 'conversões', 'person-cash-wallet' ); ?></div>
								<div class="pcw-referrer-earned"><?php echo wp_kses_post( PCW_Formatters::format_money( $referrer->total_earned ) ); ?> <?php esc_html_e( 'ganho', 'person-cash-wallet' ); ?></div>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renderizar lista de indicações
	 */
	private function render_referrals_list() {
		$referrals_obj = PCW_Referrals::instance();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

		$per_page = 30;
		$offset = ( $paged - 1 ) * $per_page;

		$args = array(
			'status' => $status,
			'search' => $search,
			'limit'  => $per_page,
			'offset' => $offset,
		);

		$referrals = $referrals_obj->get_all_referrals( $args );
		$total = $referrals_obj->count_all_referrals( $args );
		$total_pages = ceil( $total / $per_page );

		?>
		<div class="pcw-section">
			<div class="pcw-filters">
				<form method="get" action="">
					<input type="hidden" name="page" value="pcw-referrals" />
					<input type="hidden" name="tab" value="referrals" />

					<select name="status">
						<option value=""><?php esc_html_e( 'Todos os status', 'person-cash-wallet' ); ?></option>
						<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pendente', 'person-cash-wallet' ); ?></option>
						<option value="converted" <?php selected( $status, 'converted' ); ?>><?php esc_html_e( 'Convertido', 'person-cash-wallet' ); ?></option>
						<option value="rewarded" <?php selected( $status, 'rewarded' ); ?>><?php esc_html_e( 'Recompensado', 'person-cash-wallet' ); ?></option>
						<option value="expired" <?php selected( $status, 'expired' ); ?>><?php esc_html_e( 'Expirado', 'person-cash-wallet' ); ?></option>
					</select>

					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar...', 'person-cash-wallet' ); ?>" />

					<button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'person-cash-wallet' ); ?></button>
				</form>
			</div>

			<?php if ( empty( $referrals ) ) : ?>
				<p class="pcw-empty"><?php esc_html_e( 'Nenhuma indicação encontrada.', 'person-cash-wallet' ); ?></p>
			<?php else : ?>
				<table class="pcw-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Indicador', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Indicado', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Contato', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Recompensa', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Data', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Ações', 'person-cash-wallet' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $referrals as $referral ) : ?>
							<tr>
								<td>#<?php echo esc_html( $referral->id ); ?></td>
								<td>
									<strong><?php echo esc_html( $referral->referrer_name ); ?></strong><br>
									<small><?php echo esc_html( $referral->referrer_code ); ?></small>
								</td>
								<td><?php echo esc_html( $referral->referred_name ); ?></td>
								<td>
									<?php echo esc_html( $referral->referred_email ); ?><br>
									<small><?php echo esc_html( $referral->referred_phone ); ?></small>
								</td>
								<td>
									<span class="pcw-badge pcw-badge-<?php echo esc_attr( $referral->status ); ?>">
										<?php echo esc_html( $this->get_status_label( $referral->status ) ); ?>
									</span>
								</td>
								<td>
									<?php if ( $referral->reward_amount > 0 ) : ?>
										<?php echo wp_kses_post( PCW_Formatters::format_money( $referral->reward_amount ) ); ?>
									<?php else : ?>
										-
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $referral->created_at ) ) ); ?></td>
								<td class="pcw-actions">
									<button type="button" class="button pcw-action-btn pcw-delete-referral" data-id="<?php echo esc_attr( $referral->id ); ?>">
										<?php esc_html_e( 'Excluir', 'person-cash-wallet' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="pcw-pagination">
						<?php
						echo paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						) );
						?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renderizar lista de indicadores
	 */
	private function render_referrers_list() {
		$codes = PCW_Referral_Codes::instance();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

		$per_page = 30;
		$offset = ( $paged - 1 ) * $per_page;

		$args = array(
			'search' => $search,
			'limit'  => $per_page,
			'offset' => $offset,
		);

		$referrers = $codes->get_all_codes( $args );
		$total = $codes->count_codes( $args );
		$total_pages = ceil( $total / $per_page );

		?>
		<div class="pcw-section">
			<div class="pcw-filters">
				<form method="get" action="">
					<input type="hidden" name="page" value="pcw-referrals" />
					<input type="hidden" name="tab" value="referrers" />

					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar por nome, email ou código...', 'person-cash-wallet' ); ?>" />

					<button type="submit" class="button"><?php esc_html_e( 'Buscar', 'person-cash-wallet' ); ?></button>
				</form>
			</div>

			<?php if ( empty( $referrers ) ) : ?>
				<p class="pcw-empty"><?php esc_html_e( 'Nenhum indicador encontrado.', 'person-cash-wallet' ); ?></p>
			<?php else : ?>
				<table class="pcw-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Código', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Cliente', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Indicações', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Conversões', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Total Ganho', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Taxa', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $referrers as $referrer ) : ?>
							<?php
							$rate = $referrer->total_referrals > 0
								? round( ( $referrer->total_conversions / $referrer->total_referrals ) * 100, 1 )
								: 0;
							?>
							<tr>
								<td><strong><?php echo esc_html( $referrer->code ); ?></strong></td>
								<td>
									<?php echo esc_html( $referrer->display_name ); ?><br>
									<small><?php echo esc_html( $referrer->user_email ); ?></small>
								</td>
								<td><?php echo esc_html( $referrer->total_referrals ); ?></td>
								<td><?php echo esc_html( $referrer->total_conversions ); ?></td>
								<td><?php echo wp_kses_post( PCW_Formatters::format_money( $referrer->total_earned ) ); ?></td>
								<td><?php echo esc_html( $rate ); ?>%</td>
								<td>
									<span class="pcw-badge pcw-badge-<?php echo 'active' === $referrer->status ? 'converted' : 'expired'; ?>">
										<?php echo 'active' === $referrer->status ? esc_html__( 'Ativo', 'person-cash-wallet' ) : esc_html__( 'Inativo', 'person-cash-wallet' ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="pcw-pagination">
						<?php
						echo paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						) );
						?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renderizar lista de emails
	 */
	private function render_emails_list() {
		$emails_obj = PCW_Referral_Emails::instance();
		$emails = $emails_obj->get_sent_emails( array( 'limit' => 50 ) );

		?>
		<div class="pcw-section">
			<h2 class="pcw-section-title"><?php esc_html_e( 'Emails de Solicitação Enviados', 'person-cash-wallet' ); ?></h2>

			<?php if ( empty( $emails ) ) : ?>
				<p class="pcw-empty"><?php esc_html_e( 'Nenhum email enviado ainda.', 'person-cash-wallet' ); ?></p>
			<?php else : ?>
				<table class="pcw-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Cliente', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Pedido', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Enviado em', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Aberto', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Clicado', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Indicações', 'person-cash-wallet' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $emails as $email ) : ?>
							<tr>
								<td>
									<?php echo esc_html( $email->display_name ); ?><br>
									<small><?php echo esc_html( $email->user_email ); ?></small>
								</td>
								<td>#<?php echo esc_html( $email->order_id ); ?></td>
								<td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $email->sent_at ) ) ); ?></td>
								<td>
									<?php if ( $email->opened_at ) : ?>
										<span style="color: #059669;">✓</span>
										<?php echo esc_html( date_i18n( 'd/m H:i', strtotime( $email->opened_at ) ) ); ?>
									<?php else : ?>
										<span style="color: #9ca3af;">-</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $email->clicked_at ) : ?>
										<span style="color: #059669;">✓</span>
										<?php echo esc_html( date_i18n( 'd/m H:i', strtotime( $email->clicked_at ) ) ); ?>
									<?php else : ?>
										<span style="color: #9ca3af;">-</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $email->referrals_from_email ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renderizar configurações
	 */
	private function render_settings() {
		// Salvar configurações
		if ( isset( $_POST['pcw_save_referral_settings'] ) ) {
			check_admin_referer( 'pcw_referral_settings' );

			$settings = array(
				'enabled'                    => isset( $_POST['enabled'] ) ? 'yes' : 'no',
				'reward_type'                => sanitize_text_field( $_POST['reward_type'] ),
				'reward_amount'              => floatval( $_POST['reward_amount'] ),
				'max_reward_amount'          => floatval( $_POST['max_reward_amount'] ),
				'min_order_amount'           => floatval( $_POST['min_order_amount'] ),
				'reward_order_statuses'      => isset( $_POST['reward_order_statuses'] ) ? array_map( 'sanitize_text_field', $_POST['reward_order_statuses'] ) : array( 'completed' ),
				'reward_limit_type'          => sanitize_text_field( $_POST['reward_limit_type'] ),
				'reward_limit_count'         => absint( $_POST['reward_limit_count'] ),
				'referred_reward_enabled'    => isset( $_POST['referred_reward_enabled'] ) ? 'yes' : 'no',
				'referred_reward_type'       => sanitize_text_field( $_POST['referred_reward_type'] ),
				'referred_reward_amount'     => floatval( $_POST['referred_reward_amount'] ),
				'referred_reward_first_only' => isset( $_POST['referred_reward_first_only'] ) ? 'yes' : 'no',
				'cookie_days'                => absint( $_POST['cookie_days'] ),
				'email_days_after_order'     => absint( $_POST['email_days_after_order'] ),
			);

			PCW_Referral_Rewards::instance()->save_settings( $settings );

			echo '<div class="notice notice-success"><p>' . esc_html__( 'Configurações salvas!', 'person-cash-wallet' ) . '</p></div>';
		}

		$settings = PCW_Referral_Rewards::instance()->get_settings();

		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'pcw_referral_settings' ); ?>

			<div class="pcw-section">
				<h2 class="pcw-section-title"><?php esc_html_e( 'Configurações Gerais', 'person-cash-wallet' ); ?></h2>

				<div class="pcw-form-row">
					<label>
						<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'], 'yes' ); ?> />
						<?php esc_html_e( 'Habilitar Sistema de Indicações', 'person-cash-wallet' ); ?>
					</label>
				</div>

				<div class="pcw-form-row">
					<label><?php esc_html_e( 'Dias para salvar cookie de indicação', 'person-cash-wallet' ); ?></label>
					<input type="number" name="cookie_days" value="<?php echo esc_attr( $settings['cookie_days'] ); ?>" min="1" max="365" />
					<p class="description"><?php esc_html_e( 'Quantos dias o código de indicação fica salvo no navegador do visitante.', 'person-cash-wallet' ); ?></p>
				</div>
			</div>

			<div class="pcw-section">
				<h2 class="pcw-section-title"><?php esc_html_e( 'Recompensa do Indicador', 'person-cash-wallet' ); ?></h2>

				<div class="pcw-form-row">
					<label><?php esc_html_e( 'Tipo de Recompensa', 'person-cash-wallet' ); ?></label>
					<select name="reward_type">
						<option value="fixed" <?php selected( $settings['reward_type'], 'fixed' ); ?>><?php esc_html_e( 'Valor Fixo (R$)', 'person-cash-wallet' ); ?></option>
						<option value="percentage" <?php selected( $settings['reward_type'], 'percentage' ); ?>><?php esc_html_e( 'Porcentagem (%)', 'person-cash-wallet' ); ?></option>
					</select>
				</div>

				<div class="pcw-form-row">
					<label><?php esc_html_e( 'Valor/Porcentagem da Recompensa', 'person-cash-wallet' ); ?></label>
					<input type="number" name="reward_amount" value="<?php echo esc_attr( $settings['reward_amount'] ); ?>" min="0" step="0.01" />
				</div>

				<div class="pcw-form-row">
					<label><?php esc_html_e( 'Valor Máximo de Recompensa (se %)', 'person-cash-wallet' ); ?></label>
					<input type="number" name="max_reward_amount" value="<?php echo esc_attr( $settings['max_reward_amount'] ); ?>" min="0" step="0.01" />
					<p class="description"><?php esc_html_e( 'Deixe 0 para não limitar.', 'person-cash-wallet' ); ?></p>
				</div>

				<div class="pcw-form-row">
					<label><?php esc_html_e( 'Valor Mínimo do Pedido', 'person-cash-wallet' ); ?></label>
					<input type="number" name="min_order_amount" value="<?php echo esc_attr( $settings['min_order_amount'] ); ?>" min="0" step="0.01" />
					<p class="description"><?php esc_html_e( 'Valor mínimo do pedido para gerar recompensa. Deixe 0 para qualquer valor.', 'person-cash-wallet' ); ?></p>
				</div>

				<div class="pcw-form-row">
					<label><?php esc_html_e( 'Status do Pedido que Gera Recompensa', 'person-cash-wallet' ); ?></label>
					<?php
					$order_statuses = wc_get_order_statuses();
					$selected_statuses = $settings['reward_order_statuses'];
					?>
					<select name="reward_order_statuses[]" multiple style="height: 100px;">
						<?php foreach ( $order_statuses as $status_key => $status_label ) : ?>
							<?php $status_clean = str_replace( 'wc-', '', $status_key ); ?>
							<option value="<?php echo esc_attr( $status_clean ); ?>" <?php echo in_array( $status_clean, $selected_statuses, true ) ? 'selected' : ''; ?>>
								<?php echo esc_html( $status_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="pcw-form-row">
					<label><?php esc_html_e( 'Limite de Recompensas por Indicado', 'person-cash-wallet' ); ?></label>
					<select name="reward_limit_type">
						<option value="first" <?php selected( $settings['reward_limit_type'], 'first' ); ?>><?php esc_html_e( 'Apenas primeira compra', 'person-cash-wallet' ); ?></option>
						<option value="limited" <?php selected( $settings['reward_limit_type'], 'limited' ); ?>><?php esc_html_e( 'Primeiras X compras', 'person-cash-wallet' ); ?></option>
						<option value="unlimited" <?php selected( $settings['reward_limit_type'], 'unlimited' ); ?>><?php esc_html_e( 'Todas as compras', 'person-cash-wallet' ); ?></option>
					</select>
				</div>

				<div class="pcw-form-row">
					<label><?php esc_html_e( 'Quantidade de Compras (se limitado)', 'person-cash-wallet' ); ?></label>
					<input type="number" name="reward_limit_count" value="<?php echo esc_attr( $settings['reward_limit_count'] ); ?>" min="1" max="100" />
				</div>
			</div>

			<div class="pcw-section">
				<h2 class="pcw-section-title"><?php esc_html_e( 'Recompensa do Indicado (Opcional)', 'person-cash-wallet' ); ?></h2>

				<div class="pcw-form-row">
					<label>
						<input type="checkbox" name="referred_reward_enabled" value="1" <?php checked( $settings['referred_reward_enabled'], 'yes' ); ?> />
						<?php esc_html_e( 'Dar recompensa também para quem foi indicado', 'person-cash-wallet' ); ?>
					</label>
				</div>

				<div class="pcw-form-row">
					<label><?php esc_html_e( 'Tipo de Recompensa do Indicado', 'person-cash-wallet' ); ?></label>
					<select name="referred_reward_type">
						<option value="fixed" <?php selected( $settings['referred_reward_type'], 'fixed' ); ?>><?php esc_html_e( 'Valor Fixo (R$)', 'person-cash-wallet' ); ?></option>
						<option value="percentage" <?php selected( $settings['referred_reward_type'], 'percentage' ); ?>><?php esc_html_e( 'Porcentagem (%)', 'person-cash-wallet' ); ?></option>
					</select>
				</div>

				<div class="pcw-form-row">
					<label><?php esc_html_e( 'Valor/Porcentagem', 'person-cash-wallet' ); ?></label>
					<input type="number" name="referred_reward_amount" value="<?php echo esc_attr( $settings['referred_reward_amount'] ); ?>" min="0" step="0.01" />
				</div>

				<div class="pcw-form-row">
					<label>
						<input type="checkbox" name="referred_reward_first_only" value="1" <?php checked( $settings['referred_reward_first_only'], 'yes' ); ?> />
						<?php esc_html_e( 'Apenas na primeira compra do indicado', 'person-cash-wallet' ); ?>
					</label>
				</div>
			</div>

			<div class="pcw-section">
				<h2 class="pcw-section-title"><?php esc_html_e( 'Email Automático', 'person-cash-wallet' ); ?></h2>

				<div class="pcw-form-row">
					<label><?php esc_html_e( 'Enviar email de solicitação após X dias do pedido', 'person-cash-wallet' ); ?></label>
					<input type="number" name="email_days_after_order" value="<?php echo esc_attr( $settings['email_days_after_order'] ); ?>" min="1" max="90" />
					<p class="description"><?php esc_html_e( 'Dias após o pedido ser concluído para enviar o email pedindo indicação.', 'person-cash-wallet' ); ?></p>
				</div>
			</div>

			<p class="submit">
				<button type="submit" name="pcw_save_referral_settings" class="button button-primary">
					<?php esc_html_e( 'Salvar Configurações', 'person-cash-wallet' ); ?>
				</button>
			</p>
		</form>
		<?php
	}

	/**
	 * Obter label do status
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'pending'   => __( 'Pendente', 'person-cash-wallet' ),
			'converted' => __( 'Convertido', 'person-cash-wallet' ),
			'rewarded'  => __( 'Recompensado', 'person-cash-wallet' ),
			'expired'   => __( 'Expirado', 'person-cash-wallet' ),
			'cancelled' => __( 'Cancelado', 'person-cash-wallet' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Handle AJAX actions
	 */
	public function handle_ajax_action() {
		check_ajax_referer( 'pcw_admin_referrals', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		$action = isset( $_POST['referral_action'] ) ? sanitize_text_field( $_POST['referral_action'] ) : '';

		switch ( $action ) {
			case 'delete':
				$referral_id = isset( $_POST['referral_id'] ) ? absint( $_POST['referral_id'] ) : 0;
				$result = PCW_Referrals::instance()->delete_referral( $referral_id );

				if ( $result ) {
					wp_send_json_success( array( 'message' => __( 'Indicação excluída.', 'person-cash-wallet' ) ) );
				} else {
					wp_send_json_error( array( 'message' => __( 'Erro ao excluir.', 'person-cash-wallet' ) ) );
				}
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Ação inválida.', 'person-cash-wallet' ) ) );
		}
	}
}
