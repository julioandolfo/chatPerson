<?php
/**
 * Classe admin para gerenciamento de wallet
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe admin wallet
 */
class PCW_Admin_Wallet {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_post_pcw_add_wallet_balance', array( $this, 'handle_add_balance' ) );
		add_action( 'admin_post_pcw_remove_wallet_balance', array( $this, 'handle_remove_balance' ) );
	}

	/**
	 * Adicionar menu
	 */
	public function add_menu() {
		add_submenu_page(
			'pcw-dashboard',
			__( 'Gerenciar Wallet', 'person-cash-wallet' ),
			__( 'Wallet', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-wallet',
			array( $this, 'render_page' ),
			15
		);
	}

	/**
	 * Enfileirar scripts
	 *
	 * @param string $hook Hook atual.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'growly-digital_page_pcw-wallet' !== $hook && 'person-cash-wallet_page_pcw-wallet' !== $hook ) {
			return;
		}

		// Enqueue WooCommerce Select2 para busca de clientes
		if ( function_exists( 'wc_enqueue_js' ) ) {
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_style( 'select2' );
			
			// Inicializar Select2 para busca de clientes
			wc_enqueue_js( "
				jQuery(document).ready(function($) {
					$('.wc-customer-search').selectWoo({
						ajax: {
							url: wc_enhanced_select_params.ajax_url,
							dataType: 'json',
							delay: 250,
							data: function(params) {
								return {
									term: params.term,
									action: 'woocommerce_json_search_customers',
									security: '" . wp_create_nonce( 'search-customers' ) . "',
									exclude: ''
								};
							},
							processResults: function(data) {
								var terms = [];
								if (data) {
									$.each(data, function(id, text) {
										terms.push({
											id: id,
											text: text
										});
									});
								}
								return {
									results: terms
								};
							},
							cache: true
						},
						minimumInputLength: 2,
						placeholder: $(this).data('placeholder'),
						allowClear: Boolean($(this).data('allow_clear'))
					});
				});
			" );
		}
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;

		if ( ! $user_id ) {
			$this->render_user_search();
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Usuário não encontrado.', 'person-cash-wallet' ) . '</p></div>';
			$this->render_user_search();
			return;
		}

		$wallet = new PCW_Wallet( $user_id );
		$wallet_data = $wallet->get_wallet_data();
		$transactions = $wallet->get_transactions( array( 'limit' => 10 ) );

		?>
		<div class="wrap">
			<!-- Page Header -->
			<div class="pcw-page-header">
				<div>
					<h1>
						<span class="dashicons dashicons-money-alt"></span>
						<?php esc_html_e( 'Gerenciar Wallet', 'person-cash-wallet' ); ?>
					</h1>
					<p class="description"><?php esc_html_e( 'Adicione ou remova saldo manualmente da wallet dos usuários', 'person-cash-wallet' ); ?></p>
				</div>
				<div class="pcw-header-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-wallet' ) ); ?>" class="button">
						<span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Buscar Outro Usuário', 'person-cash-wallet' ); ?>
					</a>
				</div>
			</div>

			<!-- User Info Card -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-admin-users"></span>
						<?php echo esc_html( $user->display_name ); ?>
					</h2>
					<span class="pcw-badge pcw-badge-info"><?php echo esc_html( $user->user_email ); ?></span>
				</div>
				<div class="pcw-card-body">
					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
						<div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; color: #fff;">
							<div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;"><?php esc_html_e( 'Saldo Atual', 'person-cash-wallet' ); ?></div>
							<div style="font-size: 32px; font-weight: 700;"><?php echo wp_kses_post( PCW_Formatters::format_money( $wallet_data->balance ) ); ?></div>
						</div>
						<div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 8px; color: #fff;">
							<div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;"><?php esc_html_e( 'Total Ganho', 'person-cash-wallet' ); ?></div>
							<div style="font-size: 28px; font-weight: 700;"><?php echo wp_kses_post( PCW_Formatters::format_money( $wallet_data->total_earned ) ); ?></div>
						</div>
						<div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 8px; color: #fff;">
							<div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;"><?php esc_html_e( 'Total Gasto', 'person-cash-wallet' ); ?></div>
							<div style="font-size: 28px; font-weight: 700;"><?php echo wp_kses_post( PCW_Formatters::format_money( $wallet_data->total_spent ) ); ?></div>
						</div>
					</div>
				</div>
			</div>

			<!-- Actions -->
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 20px;">
				<!-- Add Balance -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Adicionar Saldo', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'pcw_add_balance', 'pcw_nonce' ); ?>
							<input type="hidden" name="action" value="pcw_add_wallet_balance">
							<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
							
							<div class="pcw-form-group">
								<label class="pcw-form-label">
									<?php esc_html_e( 'Valor', 'person-cash-wallet' ); ?>
									<span class="required">*</span>
								</label>
								<input type="number" name="amount" class="pcw-form-input" step="0.01" min="0.01" placeholder="0,00" required>
								<span class="pcw-form-help"><?php esc_html_e( 'Valor a ser adicionado na wallet', 'person-cash-wallet' ); ?></span>
							</div>
							
							<div class="pcw-form-group">
								<label class="pcw-form-label"><?php esc_html_e( 'Descrição', 'person-cash-wallet' ); ?></label>
								<input type="text" name="description" class="pcw-form-input" placeholder="<?php esc_attr_e( 'Motivo da adição (opcional)', 'person-cash-wallet' ); ?>">
							</div>
							
							<button type="submit" class="button pcw-button-success pcw-button-icon" style="width: 100%;">
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Adicionar Saldo', 'person-cash-wallet' ); ?>
							</button>
						</form>
					</div>
				</div>

				<!-- Remove Balance -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-minus"></span>
							<?php esc_html_e( 'Remover Saldo', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'pcw_remove_balance', 'pcw_nonce' ); ?>
							<input type="hidden" name="action" value="pcw_remove_wallet_balance">
							<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
							
							<div class="pcw-form-group">
								<label class="pcw-form-label">
									<?php esc_html_e( 'Valor', 'person-cash-wallet' ); ?>
									<span class="required">*</span>
								</label>
								<input type="number" name="amount" class="pcw-form-input" step="0.01" min="0.01" max="<?php echo esc_attr( $wallet_data->balance ); ?>" placeholder="0,00" required>
								<span class="pcw-form-help">
									<?php echo esc_html( sprintf( __( 'Máximo: %s', 'person-cash-wallet' ), PCW_Formatters::format_money_plain( $wallet_data->balance ) ) ); ?>
								</span>
							</div>
							
							<div class="pcw-form-group">
								<label class="pcw-form-label"><?php esc_html_e( 'Descrição', 'person-cash-wallet' ); ?></label>
								<input type="text" name="description" class="pcw-form-input" placeholder="<?php esc_attr_e( 'Motivo da remoção (opcional)', 'person-cash-wallet' ); ?>">
							</div>
							
							<button type="submit" class="button pcw-button-danger pcw-button-icon" style="width: 100%;" onclick="return confirm('<?php echo esc_js( __( 'Tem certeza que deseja remover este saldo?', 'person-cash-wallet' ) ); ?>');">
								<span class="dashicons dashicons-no-alt"></span>
								<?php esc_html_e( 'Remover Saldo', 'person-cash-wallet' ); ?>
							</button>
						</form>
					</div>
				</div>
			</div>

			<!-- Transactions -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-list-view"></span>
						<?php esc_html_e( 'Últimas Transações', 'person-cash-wallet' ); ?>
					</h2>
					<span class="pcw-badge pcw-badge-default"><?php echo esc_html( count( $transactions ) ); ?> <?php esc_html_e( 'registros', 'person-cash-wallet' ); ?></span>
				</div>
				
				<?php if ( empty( $transactions ) ) : ?>
					<div class="pcw-empty-state">
						<span class="dashicons dashicons-format-status"></span>
						<h3><?php esc_html_e( 'Nenhuma transação ainda', 'person-cash-wallet' ); ?></h3>
						<p><?php esc_html_e( 'Este usuário ainda não possui transações na wallet.', 'person-cash-wallet' ); ?></p>
					</div>
				<?php else : ?>
					<div class="pcw-table-wrapper">
						<table class="pcw-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Data', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Tipo', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Fonte', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Valor', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Saldo Após', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Descrição', 'person-cash-wallet' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $transactions as $transaction ) : ?>
									<tr>
										<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $transaction->created_at ) ) ); ?></td>
										<td>
											<?php if ( 'credit' === $transaction->type ) : ?>
												<span class="pcw-badge pcw-badge-success">
													<span class="dashicons dashicons-arrow-up-alt"></span>
													<?php esc_html_e( 'Crédito', 'person-cash-wallet' ); ?>
												</span>
											<?php else : ?>
												<span class="pcw-badge pcw-badge-danger">
													<span class="dashicons dashicons-arrow-down-alt"></span>
													<?php esc_html_e( 'Débito', 'person-cash-wallet' ); ?>
												</span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $this->get_source_label( $transaction->source ) ); ?></td>
										<td>
											<?php if ( 'credit' === $transaction->type ) : ?>
												<span style="color: #00a32a; font-weight: 600;">+<?php echo wp_kses_post( PCW_Formatters::format_money( $transaction->amount ) ); ?></span>
											<?php else : ?>
												<span style="color: #d63638; font-weight: 600;">-<?php echo wp_kses_post( PCW_Formatters::format_money( $transaction->amount ) ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo wp_kses_post( PCW_Formatters::format_money( $transaction->balance_after ) ); ?></td>
										<td>
											<?php
											if ( ! empty( $transaction->description ) ) {
												echo esc_html( $transaction->description );
											} else {
												echo '<em style="color: #646970;">' . esc_html__( 'Sem descrição', 'person-cash-wallet' ) . '</em>';
											}
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderizar busca de usuário
	 */
	private function render_user_search() {
		// Buscar últimos usuários com wallet
		global $wpdb;
		$wallet_table = $wpdb->prefix . 'pcw_wallet';
		
		$recent_users = $wpdb->get_results(
			"SELECT w.user_id, w.balance, u.display_name, u.user_email
			FROM {$wallet_table} w
			LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID
			ORDER BY w.updated_at DESC
			LIMIT 10"
		);
		
		?>
		<div class="wrap">
			<!-- Page Header -->
			<div class="pcw-page-header">
				<div>
					<h1>
						<span class="dashicons dashicons-money-alt"></span>
						<?php esc_html_e( 'Gerenciar Wallet', 'person-cash-wallet' ); ?>
					</h1>
					<p class="description"><?php esc_html_e( 'Selecione um cliente para gerenciar sua wallet', 'person-cash-wallet' ); ?></p>
				</div>
			</div>
			
			<!-- Search Card -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-search"></span>
						<?php esc_html_e( 'Buscar Cliente', 'person-cash-wallet' ); ?>
					</h2>
				</div>
				<div class="pcw-card-body">
					<form method="get" action="">
						<input type="hidden" name="page" value="pcw-wallet">
						
						<div class="pcw-form-group">
							<label class="pcw-form-label">
								<?php esc_html_e( 'Selecione o cliente', 'person-cash-wallet' ); ?>
								<span class="required">*</span>
							</label>
							<select name="user_id" class="wc-customer-search" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Digite para buscar clientes...', 'person-cash-wallet' ); ?>" data-allow_clear="true" required>
								<option value=""><?php esc_html_e( 'Digite para buscar...', 'person-cash-wallet' ); ?></option>
							</select>
							<span class="pcw-form-help">
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'Digite o nome ou email do cliente para buscar', 'person-cash-wallet' ); ?>
							</span>
						</div>
						
						<button type="submit" class="button pcw-button-primary pcw-button-icon" style="width: 100%; max-width: 300px;">
							<span class="dashicons dashicons-search"></span>
							<?php esc_html_e( 'Buscar Cliente', 'person-cash-wallet' ); ?>
						</button>
					</form>
				</div>
			</div>

			<?php if ( ! empty( $recent_users ) ) : ?>
				<!-- Recent Users -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-clock"></span>
							<?php esc_html_e( 'Clientes Recentes', 'person-cash-wallet' ); ?>
						</h2>
						<span class="pcw-badge pcw-badge-info"><?php echo esc_html( count( $recent_users ) ); ?></span>
					</div>
					<div class="pcw-card-body" style="padding: 0;">
						<div style="display: grid; gap: 0;">
							<?php foreach ( $recent_users as $user ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-wallet&user_id=' . $user->user_id ) ); ?>" 
								   style="display: flex; align-items: center; gap: 16px; padding: 16px 20px; text-decoration: none; color: inherit; border-bottom: 1px solid #dcdcde; transition: background 0.2s ease;"
								   onmouseover="this.style.background='#f6f7f7'"
								   onmouseout="this.style.background='transparent'">
									<div style="width: 48px; height: 48px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 18px; flex-shrink: 0;">
										<?php echo esc_html( strtoupper( substr( $user->display_name, 0, 1 ) ) ); ?>
									</div>
									<div style="flex: 1; min-width: 0;">
										<div style="font-weight: 600; font-size: 15px; color: #1d2327; margin-bottom: 4px;">
											<?php echo esc_html( $user->display_name ); ?>
										</div>
										<div style="font-size: 13px; color: #646970;">
											<?php echo esc_html( $user->user_email ); ?>
										</div>
									</div>
									<div style="text-align: right; flex-shrink: 0;">
										<div style="font-size: 12px; color: #646970; margin-bottom: 4px;">
											<?php esc_html_e( 'Saldo', 'person-cash-wallet' ); ?>
										</div>
										<div style="font-weight: 700; font-size: 16px; color: #00a32a;">
											<?php echo wp_kses_post( PCW_Formatters::format_money( $user->balance ) ); ?>
										</div>
									</div>
									<span class="dashicons dashicons-arrow-right-alt2" style="color: #646970;"></span>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Processar adicionar saldo
	 */
	public function handle_add_balance() {
		// Verificar nonce
		if ( ! isset( $_POST['pcw_nonce'] ) || ! wp_verify_nonce( $_POST['pcw_nonce'], 'pcw_add_balance' ) ) {
			wp_die( esc_html__( 'Ação não autorizada.', 'person-cash-wallet' ) );
		}

		// Verificar capability
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'person-cash-wallet' ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$amount = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;
		$description = isset( $_POST['description'] ) ? sanitize_text_field( $_POST['description'] ) : '';

		if ( ! $user_id || $amount <= 0 ) {
			wp_die( esc_html__( 'Dados inválidos.', 'person-cash-wallet' ) );
		}

		$wallet = new PCW_Wallet( $user_id );
		$result = $wallet->add_balance_manual( $amount, $description );

		if ( $result ) {
			wp_safe_redirect( add_query_arg( array( 'user_id' => $user_id, 'message' => 'balance_added' ), admin_url( 'admin.php?page=pcw-wallet' ) ) );
			exit;
		} else {
			wp_die( esc_html__( 'Erro ao adicionar saldo.', 'person-cash-wallet' ) );
		}
	}

	/**
	 * Processar remover saldo
	 */
	public function handle_remove_balance() {
		// Verificar nonce
		if ( ! isset( $_POST['pcw_nonce'] ) || ! wp_verify_nonce( $_POST['pcw_nonce'], 'pcw_remove_balance' ) ) {
			wp_die( esc_html__( 'Ação não autorizada.', 'person-cash-wallet' ) );
		}

		// Verificar capability
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'person-cash-wallet' ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$amount = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;
		$description = isset( $_POST['description'] ) ? sanitize_text_field( $_POST['description'] ) : '';

		if ( ! $user_id || $amount <= 0 ) {
			wp_die( esc_html__( 'Dados inválidos.', 'person-cash-wallet' ) );
		}

		$wallet = new PCW_Wallet( $user_id );
		$result = $wallet->remove_balance_manual( $amount, $description );

		if ( $result ) {
			wp_safe_redirect( add_query_arg( array( 'user_id' => $user_id, 'message' => 'balance_removed' ), admin_url( 'admin.php?page=pcw-wallet' ) ) );
			exit;
		} else {
			wp_die( esc_html__( 'Erro ao remover saldo. Verifique se o usuário tem saldo suficiente.', 'person-cash-wallet' ) );
		}
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
}
