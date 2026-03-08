<?php
/**
 * Classe da área pública
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe public
 */
class PCW_Public {

	/**
	 * Inicializar
	 */
	public function init() {
		// Adicionar endpoints no Minha Conta
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_menu_items' ) );
		add_action( 'init', array( $this, 'add_account_endpoints' ) );

		// Páginas do Minha Conta
		add_action( 'woocommerce_account_wallet_endpoint', array( $this, 'render_wallet_page' ) );
		add_action( 'woocommerce_account_cashback_endpoint', array( $this, 'render_cashback_page' ) );
		add_action( 'woocommerce_account_levels_endpoint', array( $this, 'render_levels_page' ) );

		// Adicionar detalhes no pedido
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_order_cashback_details' ), 10, 1 );

		// Shortcodes
		add_shortcode( 'pcw_wallet_balance', array( $this, 'wallet_balance_shortcode' ) );
		add_shortcode( 'pcw_cashback_available', array( $this, 'cashback_available_shortcode' ) );
		add_shortcode( 'pcw_level_badge', array( $this, 'level_badge_shortcode' ) );
		add_shortcode( 'pcw_level_progress', array( $this, 'level_progress_shortcode' ) );

		// Enfileirar estilos
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Adicionar itens no menu Minha Conta
	 *
	 * @param array $items Itens do menu.
	 * @return array
	 */
	public function add_account_menu_items( $items ) {
		$new_items = array();

		// Inserir antes de "Sair"
		foreach ( $items as $key => $item ) {
			if ( 'customer-logout' === $key ) {
				if ( 'yes' === get_option( 'pcw_wallet_enabled', 'yes' ) ) {
					$new_items['wallet'] = __( 'Wallet', 'person-cash-wallet' );
				}
				if ( 'yes' === get_option( 'pcw_cashback_enabled', 'yes' ) ) {
					$new_items['cashback'] = __( 'Cashback', 'person-cash-wallet' );
				}
				if ( 'yes' === get_option( 'pcw_levels_enabled', 'yes' ) ) {
					$new_items['levels'] = __( 'Níveis', 'person-cash-wallet' );
				}
			}
			$new_items[ $key ] = $item;
		}

		return $new_items;
	}

	/**
	 * Adicionar endpoints
	 */
	public function add_account_endpoints() {
		add_rewrite_endpoint( 'wallet', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'cashback', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'levels', EP_ROOT | EP_PAGES );
	}

	/**
	 * Renderizar página de Wallet
	 */
	public function render_wallet_page() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		$wallet = new PCW_Wallet( $user_id );
		$wallet_data = $wallet->get_wallet_data();

		// Paginação
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page = 20;
		$offset = ( $paged - 1 ) * $per_page;

		$transactions = $wallet->get_transactions( array(
			'limit' => $per_page,
			'offset' => $offset,
		) );

		$total_transactions = $wallet->get_transactions_count();
		$total_pages = ceil( $total_transactions / $per_page );

		// Carregar template
		$template_path = PCW_PLUGIN_DIR . 'templates/my-account/wallet.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			$this->render_wallet_template( $wallet_data, $transactions, $paged, $total_pages );
		}
	}

	/**
	 * Renderizar template de wallet (fallback)
	 *
	 * @param object $wallet_data Dados da wallet.
	 * @param array  $transactions Transações.
	 * @param int    $paged Página atual.
	 * @param int    $total_pages Total de páginas.
	 */
	private function render_wallet_template( $wallet_data, $transactions, $paged, $total_pages ) {
		?>
		<div class="pcw-wallet-page">
			<h2><?php esc_html_e( 'Minha Wallet', 'person-cash-wallet' ); ?></h2>

			<div class="pcw-wallet-summary">
				<div class="pcw-balance-box">
					<h3><?php esc_html_e( 'Saldo Disponível', 'person-cash-wallet' ); ?></h3>
					<p class="pcw-balance-amount"><?php echo wp_kses_post( PCW_Formatters::format_money( $wallet_data->balance ) ); ?></p>
				</div>

				<div class="pcw-wallet-stats">
					<p>
						<strong><?php esc_html_e( 'Total Ganho:', 'person-cash-wallet' ); ?></strong>
						<?php echo wp_kses_post( PCW_Formatters::format_money( $wallet_data->total_earned ) ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Total Gasto:', 'person-cash-wallet' ); ?></strong>
						<?php echo wp_kses_post( PCW_Formatters::format_money( $wallet_data->total_spent ) ); ?>
					</p>
				</div>
			</div>

			<div class="pcw-transactions">
				<h3><?php esc_html_e( 'Histórico de Transações', 'person-cash-wallet' ); ?></h3>

				<?php if ( ! empty( $transactions ) ) : ?>
					<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Data', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Tipo', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Fonte', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Valor', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Saldo', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Descrição', 'person-cash-wallet' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $transactions as $transaction ) : ?>
								<tr>
									<td data-title="<?php esc_attr_e( 'Data', 'person-cash-wallet' ); ?>">
										<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $transaction->created_at ) ) ); ?>
									</td>
									<td data-title="<?php esc_attr_e( 'Tipo', 'person-cash-wallet' ); ?>">
										<?php
										if ( 'credit' === $transaction->type ) {
											echo '<span style="color: green;">' . esc_html__( 'Crédito', 'person-cash-wallet' ) . '</span>';
										} else {
											echo '<span style="color: red;">' . esc_html__( 'Débito', 'person-cash-wallet' ) . '</span>';
										}
										?>
									</td>
									<td data-title="<?php esc_attr_e( 'Fonte', 'person-cash-wallet' ); ?>">
										<?php echo esc_html( $this->get_source_label( $transaction->source ) ); ?>
									</td>
									<td data-title="<?php esc_attr_e( 'Valor', 'person-cash-wallet' ); ?>">
										<?php echo wp_kses_post( PCW_Formatters::format_money( $transaction->amount ) ); ?>
									</td>
									<td data-title="<?php esc_attr_e( 'Saldo', 'person-cash-wallet' ); ?>">
										<?php echo wp_kses_post( PCW_Formatters::format_money( $transaction->balance_after ) ); ?>
									</td>
									<td data-title="<?php esc_attr_e( 'Descrição', 'person-cash-wallet' ); ?>">
										<?php echo esc_html( $transaction->description ); ?>
										<?php if ( $transaction->order_id ) : ?>
											<br><a href="<?php echo esc_url( wc_get_endpoint_url( 'view-order', $transaction->order_id ) ); ?>">
												<?php esc_html_e( 'Ver pedido', 'person-cash-wallet' ); ?>
											</a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( $total_pages > 1 ) : ?>
						<div class="woocommerce-pagination">
							<?php
							echo paginate_links( array(
								'base'      => esc_url_raw( str_replace( 999999999, '%#%', remove_query_arg( 'add-to-cart', get_pagenum_link( 999999999, false ) ) ) ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $total_pages,
								'prev_text' => '&larr;',
								'next_text' => '&rarr;',
								'type'      => 'list',
								'end_size'  => 3,
								'mid_size'  => 3,
							) );
							?>
						</div>
					<?php endif; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'Nenhuma transação encontrada.', 'person-cash-wallet' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderizar página de Cashback
	 */
	public function render_cashback_page() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		$cashback = new PCW_Cashback( $user_id );

		$available_balance = $cashback->get_available_balance();
		$available_list = $cashback->get_available_cashback();

		// Buscar cashback pendente
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_cashback';
		$pending_cashback = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
				WHERE user_id = %d 
				AND status = 'pending'
				ORDER BY earned_date DESC",
				$user_id
			)
		);

		// Buscar histórico
		$history = $cashback->get_history( array( 'limit' => 20 ) );

		// Buscar cashback expirado
		$expired_cashback = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
				WHERE user_id = %d 
				AND status = 'expired'
				ORDER BY expires_date DESC
				LIMIT 10",
				$user_id
			)
		);

		?>
		<div class="pcw-cashback-page">
			<h2><?php esc_html_e( 'Meu Cashback', 'person-cash-wallet' ); ?></h2>

			<div class="pcw-cashback-summary">
				<div class="pcw-balance-box">
					<h3><?php esc_html_e( 'Cashback Disponível', 'person-cash-wallet' ); ?></h3>
					<p class="pcw-balance-amount"><?php echo wp_kses_post( PCW_Formatters::format_money( $available_balance ) ); ?></p>
				</div>
			</div>

			<?php if ( ! empty( $pending_cashback ) ) : ?>
				<div class="pcw-cashback-section">
					<h3><?php esc_html_e( 'Cashback Pendente', 'person-cash-wallet' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Cashback que será liberado em breve.', 'person-cash-wallet' ); ?></p>
					<ul>
						<?php foreach ( $pending_cashback as $cb ) : ?>
							<li>
								<?php echo wp_kses_post( PCW_Formatters::format_money( $cb->amount ) ); ?>
								- <?php esc_html_e( 'Pedido', 'person-cash-wallet' ); ?> #<?php echo esc_html( $cb->order_id ); ?>
								<?php if ( $cb->expires_date ) : ?>
									<br><small><?php esc_html_e( 'Expira em:', 'person-cash-wallet' ); ?> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $cb->expires_date ) ) ); ?></small>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $available_list ) ) : ?>
				<div class="pcw-cashback-section">
					<h3><?php esc_html_e( 'Cashback Disponível', 'person-cash-wallet' ); ?></h3>
					<ul>
						<?php foreach ( $available_list as $cb ) : ?>
							<li>
								<?php echo wp_kses_post( PCW_Formatters::format_money( $cb->amount ) ); ?>
								- <?php esc_html_e( 'Pedido', 'person-cash-wallet' ); ?> #<?php echo esc_html( $cb->order_id ); ?>
								<?php if ( $cb->expires_date ) : ?>
									<br><small><?php esc_html_e( 'Expira em:', 'person-cash-wallet' ); ?> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $cb->expires_date ) ) ); ?></small>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $expired_cashback ) ) : ?>
				<div class="pcw-cashback-section">
					<h3><?php esc_html_e( 'Cashback Expirado', 'person-cash-wallet' ); ?></h3>
					<ul>
						<?php foreach ( $expired_cashback as $cb ) : ?>
							<li>
								<?php echo wp_kses_post( PCW_Formatters::format_money( $cb->amount ) ); ?>
								- <?php esc_html_e( 'Pedido', 'person-cash-wallet' ); ?> #<?php echo esc_html( $cb->order_id ); ?>
								<br><small><?php esc_html_e( 'Expirado em:', 'person-cash-wallet' ); ?> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $cb->expires_date ) ) ); ?></small>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $history ) ) : ?>
				<div class="pcw-cashback-section">
					<h3><?php esc_html_e( 'Histórico', 'person-cash-wallet' ); ?></h3>
					<table class="woocommerce-orders-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Data', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Tipo', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Valor', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Saldo', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Descrição', 'person-cash-wallet' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $history as $item ) : ?>
								<tr>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->created_at ) ) ); ?></td>
									<td><?php echo esc_html( ucfirst( $item->type ) ); ?></td>
									<td><?php echo wp_kses_post( PCW_Formatters::format_money( $item->amount ) ); ?></td>
									<td><?php echo wp_kses_post( PCW_Formatters::format_money( $item->balance_after ) ); ?></td>
									<td><?php echo esc_html( $item->description ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renderizar página de Níveis
	 */
	public function render_levels_page() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		$current_level = PCW_Levels::get_user_level( $user_id );

		// Buscar próximo nível
		$all_levels = PCW_Levels::get_all_levels( array( 'status' => 'active' ) );
		$next_level = null;

		if ( $current_level ) {
			foreach ( $all_levels as $level ) {
				if ( $level->level_number > $current_level->level_number ) {
					$next_level = $level;
					break;
				}
			}
		} else {
			// Se não tem nível, o primeiro é o próximo
			if ( ! empty( $all_levels ) ) {
				$next_level = $all_levels[0];
			}
		}

		// Calcular progresso
		$progress = null;
		if ( $next_level ) {
			$metrics = PCW_Level_Calculator::get_user_metrics( $user_id );
			// TODO: Calcular progresso baseado nos requisitos
		}

		?>
		<div class="pcw-levels-page">
			<h2><?php esc_html_e( 'Meus Níveis', 'person-cash-wallet' ); ?></h2>

			<?php if ( $current_level ) : ?>
				<div class="pcw-current-level">
					<h3><?php esc_html_e( 'Nível Atual', 'person-cash-wallet' ); ?></h3>
					<div class="pcw-level-badge" style="background-color: <?php echo esc_attr( $current_level->color ); ?>;">
						<h4><?php echo esc_html( $current_level->name ); ?></h4>
						<?php if ( $current_level->description ) : ?>
							<p><?php echo esc_html( $current_level->description ); ?></p>
						<?php endif; ?>
					</div>
					<?php if ( $current_level->expires_date ) : ?>
						<p><strong><?php esc_html_e( 'Expira em:', 'person-cash-wallet' ); ?></strong> 
						<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $current_level->expires_date ) ) ); ?></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'Você ainda não possui um nível atribuído.', 'person-cash-wallet' ); ?></p>
			<?php endif; ?>

			<?php if ( $next_level ) : ?>
				<div class="pcw-next-level">
					<h3><?php esc_html_e( 'Próximo Nível', 'person-cash-wallet' ); ?></h3>
					<p><strong><?php echo esc_html( $next_level->name ); ?></strong></p>
					<?php if ( $next_level->description ) : ?>
						<p><?php echo esc_html( $next_level->description ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Exibir detalhes de cashback no pedido
	 *
	 * @param WC_Order $order Pedido.
	 */
	public function display_order_cashback_details( $order ) {
		$order_id = $order->get_id();
		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			return;
		}

		// Buscar cashback gerado neste pedido
		global $wpdb;
		$cashback_table = $wpdb->prefix . 'pcw_cashback';
		$cashback_list = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$cashback_table} WHERE order_id = %d ORDER BY created_at DESC",
				$order_id
			)
		);

		// Buscar transações da wallet neste pedido
		$wallet_table = $wpdb->prefix . 'pcw_wallet_transactions';
		$wallet_transactions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wallet_table} WHERE order_id = %d ORDER BY created_at DESC",
				$order_id
			)
		);

		// Verificar se houve pagamento com wallet
		$wallet_used = $order->get_meta( '_pcw_wallet_used' );
		$wallet_balance_before = $order->get_meta( '_pcw_wallet_balance_before' );
		$wallet_balance_after = $order->get_meta( '_pcw_wallet_balance_after' );
		$has_wallet_payment = ! empty( $wallet_used ) && $wallet_used > 0;

		if ( empty( $cashback_list ) && empty( $wallet_transactions ) && ! $has_wallet_payment ) {
			return;
		}

		?>
		<section class="pcw-order-details">
			<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Cashback e Wallet', 'person-cash-wallet' ); ?></h2>

			<?php if ( $has_wallet_payment ) : ?>
				<div class="pcw-order-wallet-summary">
					<h3><?php esc_html_e( 'Pagamento com Wallet', 'person-cash-wallet' ); ?></h3>
					<table class="woocommerce-table woocommerce-table--order-details shop_table order_details pcw-wallet-summary-table">
						<tbody>
							<?php if ( $wallet_balance_before ) : ?>
							<tr>
								<th><?php esc_html_e( 'Saldo antes da compra:', 'person-cash-wallet' ); ?></th>
								<td><?php echo wp_kses_post( wc_price( $wallet_balance_before ) ); ?></td>
							</tr>
							<?php endif; ?>
							<tr class="pcw-wallet-used-row">
								<th><?php esc_html_e( 'Valor utilizado neste pedido:', 'person-cash-wallet' ); ?></th>
								<td><strong style="color: #e74c3c;">- <?php echo wp_kses_post( wc_price( $wallet_used ) ); ?></strong></td>
							</tr>
							<?php if ( $wallet_balance_after !== '' && $wallet_balance_after !== false ) : ?>
							<tr class="pcw-wallet-after-row">
								<th><?php esc_html_e( 'Saldo apos a compra:', 'person-cash-wallet' ); ?></th>
								<td><strong style="color: #27ae60;"><?php echo wp_kses_post( wc_price( $wallet_balance_after ) ); ?></strong></td>
							</tr>
							<?php else : 
								// Calcular saldo atual se não tiver o saldo após salvo
								$wallet = new PCW_Wallet( $user_id );
								$current_balance = $wallet->get_balance();
							?>
							<tr class="pcw-wallet-current-row">
								<th><?php esc_html_e( 'Saldo atual:', 'person-cash-wallet' ); ?></th>
								<td><strong style="color: #27ae60;"><?php echo wp_kses_post( wc_price( $current_balance ) ); ?></strong></td>
							</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $cashback_list ) ) : ?>
				<div class="pcw-order-cashback">
					<h3><?php esc_html_e( 'Cashback Gerado', 'person-cash-wallet' ); ?></h3>
					<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Valor', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Ganho em', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Expira em', 'person-cash-wallet' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $cashback_list as $cb ) : ?>
								<tr>
									<td><?php echo wp_kses_post( PCW_Formatters::format_money( $cb->amount ) ); ?></td>
									<td>
										<?php
										$status_labels = array(
											'pending'   => __( 'Pendente', 'person-cash-wallet' ),
											'available' => __( 'Disponível', 'person-cash-wallet' ),
											'used'      => __( 'Utilizado', 'person-cash-wallet' ),
											'expired'   => __( 'Expirado', 'person-cash-wallet' ),
										);
										echo esc_html( isset( $status_labels[ $cb->status ] ) ? $status_labels[ $cb->status ] : $cb->status );
										?>
									</td>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $cb->earned_date ) ) ); ?></td>
									<td>
										<?php
										if ( $cb->expires_date ) {
											echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $cb->expires_date ) ) );
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
			<?php endif; ?>

			<?php if ( ! empty( $wallet_transactions ) ) : ?>
				<div class="pcw-order-wallet">
					<h3><?php esc_html_e( 'Wallet Utilizada', 'person-cash-wallet' ); ?></h3>
					<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Tipo', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Valor', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Data', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Descrição', 'person-cash-wallet' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $wallet_transactions as $transaction ) : ?>
								<tr>
									<td>
										<?php
										if ( 'credit' === $transaction->type ) {
											echo '<span style="color: green;">' . esc_html__( 'Crédito', 'person-cash-wallet' ) . '</span>';
										} else {
											echo '<span style="color: red;">' . esc_html__( 'Débito', 'person-cash-wallet' ) . '</span>';
										}
										?>
									</td>
									<td><?php echo wp_kses_post( PCW_Formatters::format_money( $transaction->amount ) ); ?></td>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $transaction->created_at ) ) ); ?></td>
									<td><?php echo esc_html( $transaction->description ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Shortcode: Saldo da wallet
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function wallet_balance_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$wallet = new PCW_Wallet();
		$balance = $wallet->get_balance();

		ob_start();
		?>
		<div class="pcw-wallet-balance">
			<span class="pcw-label"><?php esc_html_e( 'Saldo disponível:', 'person-cash-wallet' ); ?></span>
			<span class="pcw-amount"><?php echo wp_kses_post( PCW_Formatters::format_money( $balance ) ); ?></span>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode: Cashback disponível
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function cashback_available_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$cashback = new PCW_Cashback();
		$balance = $cashback->get_available_balance();

		ob_start();
		?>
		<div class="pcw-cashback-available">
			<span class="pcw-label"><?php esc_html_e( 'Cashback disponível:', 'person-cash-wallet' ); ?></span>
			<span class="pcw-amount"><?php echo wp_kses_post( PCW_Formatters::format_money( $balance ) ); ?></span>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode: Badge do nível
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function level_badge_shortcode( $atts ) {
		// TODO: Implementar quando sistema de níveis estiver completo
		return '<div class="pcw-level-badge">' . esc_html__( 'Nível em desenvolvimento', 'person-cash-wallet' ) . '</div>';
	}

	/**
	 * Shortcode: Progresso do nível
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function level_progress_shortcode( $atts ) {
		// TODO: Implementar quando sistema de níveis estiver completo
		return '<div class="pcw-level-progress">' . esc_html__( 'Progresso em desenvolvimento', 'person-cash-wallet' ) . '</div>';
	}

	/**
	 * Obter label da fonte
	 *
	 * @param string $source Fonte.
	 * @return string
	 */
	private function get_source_label( $source ) {
		$labels = array(
			'cashback'   => __( 'Cashback', 'person-cash-wallet' ),
			'manual'     => __( 'Manual', 'person-cash-wallet' ),
			'refund'     => __( 'Reembolso', 'person-cash-wallet' ),
			'purchase'   => __( 'Compra', 'person-cash-wallet' ),
			'adjustment' => __( 'Ajuste', 'person-cash-wallet' ),
		);

		return isset( $labels[ $source ] ) ? $labels[ $source ] : $source;
	}

	/**
	 * Enfileirar scripts e estilos
	 */
	public function enqueue_scripts() {
		if ( ! is_account_page() ) {
			return;
		}

		wp_add_inline_style( 'woocommerce-general', '
			.pcw-wallet-page, .pcw-cashback-page, .pcw-levels-page {
				margin-top: 20px;
			}
			.pcw-balance-box {
				background: #f5f5f5;
				padding: 20px;
				border-radius: 5px;
				margin-bottom: 20px;
			}
			.pcw-balance-amount {
				font-size: 2em;
				font-weight: bold;
				color: #2c3e50;
			}
			.pcw-cashback-section {
				margin: 20px 0;
			}
			.pcw-cashback-section ul {
				list-style: none;
				padding: 0;
			}
			.pcw-cashback-section li {
				padding: 10px;
				border-bottom: 1px solid #eee;
			}
		' );
	}
}
