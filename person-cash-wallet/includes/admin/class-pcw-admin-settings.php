<?php
/**
 * Configurações gerais do plugin
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de configurações
 */
class PCW_Admin_Settings {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_post_pcw_save_general_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_pcw_save_ai_settings', array( $this, 'handle_save_ai_settings' ) );
		add_action( 'admin_post_pcw_save_smtp_account', array( $this, 'handle_save_smtp_account' ) );
		add_action( 'admin_post_pcw_save_ga4_settings', array( $this, 'handle_save_ga4_settings' ) );
		add_action( 'admin_post_pcw_save_personizi_settings', array( $this, 'handle_save_personizi_settings' ) );
		add_action( 'wp_ajax_pcw_test_ai_connection', array( $this, 'ajax_test_ai_connection' ) );
		add_action( 'wp_ajax_pcw_test_smtp_connection', array( $this, 'ajax_test_smtp_connection' ) );
		add_action( 'wp_ajax_pcw_delete_smtp_account', array( $this, 'ajax_delete_smtp_account' ) );
		add_action( 'wp_ajax_pcw_test_personizi_connection', array( $this, 'ajax_test_personizi_connection' ) );
		add_action( 'wp_ajax_pcw_get_personizi_accounts', array( $this, 'ajax_get_personizi_accounts' ) );
		add_action( 'wp_ajax_pcw_debug_personizi', array( $this, 'ajax_debug_personizi' ) );
		add_action( 'wp_ajax_pcw_send_test_personizi_message', array( $this, 'ajax_send_test_personizi_message' ) );
		add_action( 'admin_post_pcw_save_sendpulse_settings', array( $this, 'handle_save_sendpulse_settings' ) );
		add_action( 'wp_ajax_pcw_test_sendpulse_connection', array( $this, 'ajax_test_sendpulse_connection' ) );
		add_action( 'wp_ajax_pcw_save_sendpulse_account', array( $this, 'ajax_save_sendpulse_account' ) );
		add_action( 'wp_ajax_pcw_delete_sendpulse_account', array( $this, 'ajax_delete_sendpulse_account' ) );
		add_action( 'wp_ajax_pcw_get_sendpulse_account', array( $this, 'ajax_get_sendpulse_account' ) );
		add_action( 'wp_ajax_pcw_process_pending_cashback', array( $this, 'ajax_process_pending_cashback' ) );
	}

	/**
	 * Adicionar menu
	 */
	public function add_menu() {
		add_submenu_page(
			'pcw-dashboard',
			__( 'Configurações', 'person-cash-wallet' ),
			__( 'Configurações', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-settings',
			array( $this, 'render_page' ),
			99
		);
	}

	/**
	 * Enfileirar scripts e estilos
	 */
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'pcw-settings' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'pcw-admin-queue-css',
			PCW_PLUGIN_URL . 'assets/css/admin-queue.css',
			array(),
			PCW_VERSION
		);
	}

	/**
	 * Obter todos os status do WooCommerce
	 *
	 * @return array
	 */
	private function get_all_order_statuses() {
		$statuses = array();

		// Status padrão do WooCommerce
		$wc_statuses = wc_get_order_statuses();

		foreach ( $wc_statuses as $slug => $label ) {
			// Remover prefixo 'wc-' se existir
			$clean_slug = str_replace( 'wc-', '', $slug );
			$statuses[ $clean_slug ] = $label;
		}

		// Integração com WooCommerce Order Status Manager
		if ( function_exists( 'wc_order_status_manager_get_order_status_posts' ) ) {
			$custom_statuses = wc_order_status_manager_get_order_status_posts();
			
			if ( ! empty( $custom_statuses ) ) {
				foreach ( $custom_statuses as $status ) {
					$slug = $status->post_name;
					$label = $status->post_title;
					
					// Adicionar apenas se não existir
					if ( ! isset( $statuses[ $slug ] ) ) {
						$statuses[ $slug ] = $label;
					}
				}
			}
		}

		// Alternativa: buscar posts do tipo 'wc_order_status'
		if ( post_type_exists( 'wc_order_status' ) ) {
			$custom_status_posts = get_posts( array(
				'post_type'      => 'wc_order_status',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			) );

			foreach ( $custom_status_posts as $status_post ) {
				$slug = $status_post->post_name;
				$label = $status_post->post_title;
				
				if ( ! isset( $statuses[ $slug ] ) ) {
					$statuses[ $slug ] = $label;
				}
			}
		}

		return $statuses;
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
		$tabs = array(
			'general'    => __( 'Geral', 'person-cash-wallet' ),
			'referrals'  => __( 'Indicações', 'person-cash-wallet' ),
			'ai'         => __( 'Inteligência Artificial', 'person-cash-wallet' ),
			'smtp'       => __( 'Contas SMTP', 'person-cash-wallet' ),
			'analytics'  => __( 'Google Analytics', 'person-cash-wallet' ),
			'personizi'  => __( 'Personizi WhatsApp', 'person-cash-wallet' ),
		);

		?>
		<div class="wrap">
			<div class="pcw-page-header">
				<div>
					<h1>
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'Configurações', 'person-cash-wallet' ); ?>
					</h1>
				</div>
			</div>

			<nav class="nav-tab-wrapper pcw-tabs">
				<?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-settings&tab=' . $tab_id ) ); ?>" 
						class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_name ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php
			switch ( $current_tab ) {
				case 'referrals':
					$this->render_referrals_tab();
					break;
				case 'ai':
					$this->render_ai_tab();
					break;
				case 'smtp':
					$this->render_smtp_tab();
					break;
				case 'sendpulse':
					$this->render_sendpulse_tab();
					break;
				case 'analytics':
					$this->render_analytics_tab();
					break;
				case 'personizi':
					$this->render_personizi_tab();
					break;
				default:
					$this->render_general_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Renderizar aba geral
	 */
	private function render_general_tab() {
		$settings = get_option( 'pcw_general_settings', $this->get_default_settings() );

		// Carregar valores de transferência automática das options individuais se não existirem no array
		if ( ! isset( $settings['auto_transfer_to_wallet'] ) ) {
			$settings['auto_transfer_to_wallet'] = get_option( 'pcw_auto_transfer_to_wallet', 'yes' );
		}
		if ( ! isset( $settings['auto_transfer_days'] ) ) {
			$settings['auto_transfer_days'] = get_option( 'pcw_auto_transfer_days', 0 );
		}
		if ( ! isset( $settings['cashback_expiration_days'] ) ) {
			$settings['cashback_expiration_days'] = get_option( 'pcw_cashback_expiration_days', 0 );
		}

		$all_statuses = $this->get_all_order_statuses();

		// Separar status em categorias para melhor UX
		$positive_statuses = array( 'completed', 'processing' );
		$negative_statuses = array( 'cancelled', 'refunded', 'failed' );
		$other_statuses = array_diff( array_keys( $all_statuses ), $positive_statuses, $negative_statuses );

		?>
		<div class="wrap">
			<!-- Page Header -->
			<div class="pcw-page-header">
				<div>
					<h1>
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'Configurações Gerais', 'person-cash-wallet' ); ?>
					</h1>
					<p class="description"><?php esc_html_e( 'Configure o comportamento geral do sistema de cashback, wallet e níveis', 'person-cash-wallet' ); ?></p>
				</div>
			</div>

			<?php if ( isset( $_GET['message'] ) && 'settings_saved' === $_GET['message'] ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Configurações salvas com sucesso!', 'person-cash-wallet' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'pcw_save_general_settings', 'pcw_nonce' ); ?>
				<input type="hidden" name="action" value="pcw_save_general_settings">

				<!-- Ativação de Módulos -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-admin-plugins"></span>
							<?php esc_html_e( 'Módulos Ativos', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<div style="padding: 16px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px; margin-bottom: 20px;">
							<strong><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'Dica:', 'person-cash-wallet' ); ?></strong>
							<?php esc_html_e( 'Desative módulos que não estiver usando para melhorar a performance.', 'person-cash-wallet' ); ?>
						</div>

						<table class="form-table">
							<tr>
								<th style="width: 200px;"><?php esc_html_e( 'Sistema de Cashback', 'person-cash-wallet' ); ?></th>
								<td>
									<label class="switch">
										<input type="checkbox" name="cashback_enabled" value="yes" <?php checked( $settings['cashback_enabled'], 'yes' ); ?>>
										<span class="slider"></span>
									</label>
									<p class="description">
										<span class="dashicons dashicons-info"></span>
										<?php esc_html_e( 'Ativar geração automática de cashback em compras', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Sistema de Níveis VIP', 'person-cash-wallet' ); ?></th>
								<td>
									<label class="switch">
										<input type="checkbox" name="levels_enabled" value="yes" <?php checked( $settings['levels_enabled'], 'yes' ); ?>>
										<span class="slider"></span>
									</label>
									<p class="description">
										<span class="dashicons dashicons-info"></span>
										<?php esc_html_e( 'Ativar sistema de níveis e descontos exclusivos', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Wallet como Pagamento', 'person-cash-wallet' ); ?></th>
								<td>
									<label class="switch">
										<input type="checkbox" name="wallet_payment_enabled" value="yes" <?php checked( $settings['wallet_payment_enabled'], 'yes' ); ?>>
										<span class="slider"></span>
									</label>
									<p class="description">
										<span class="dashicons dashicons-info"></span>
										<?php esc_html_e( 'Permitir usar saldo da wallet como forma de pagamento', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Configurações de Expiração de Cashback -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-clock"></span>
							<?php esc_html_e( 'Expiração de Cashback', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<div style="padding: 16px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; margin-bottom: 20px;">
							<strong><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Importante:', 'person-cash-wallet' ); ?></strong>
							<?php esc_html_e( 'Defina após quantos dias o cashback expira. Isso é necessário para que os workflows de "Cashback Expirando" funcionem corretamente.', 'person-cash-wallet' ); ?>
						</div>

						<table class="form-table">
							<tr>
								<th style="width: 200px;">
									<label for="cashback_expiration_days"><?php esc_html_e( 'Dias para Expirar', 'person-cash-wallet' ); ?></label>
								</th>
								<td>
									<input 
										type="number" 
										id="cashback_expiration_days" 
										name="cashback_expiration_days" 
										value="<?php echo esc_attr( isset( $settings['cashback_expiration_days'] ) ? $settings['cashback_expiration_days'] : 0 ); ?>" 
										min="0" 
										max="365" 
										class="small-text"
										style="width: 80px;"
									>
									<span style="margin-left: 8px;"><?php esc_html_e( 'dias após a criação', 'person-cash-wallet' ); ?></span>
									<p class="description">
										<span class="dashicons dashicons-info"></span>
										<?php esc_html_e( 'Deixe 0 para nunca expirar. Valores comuns: 30, 60, 90 ou 180 dias.', 'person-cash-wallet' ); ?>
									</p>
									<?php 
									$current_value = absint( get_option( 'pcw_cashback_expiration_days', 0 ) );
									if ( $current_value === 0 ) : 
									?>
									<p style="margin-top: 10px; padding: 10px; background: #fef3c7; border-radius: 4px; color: #92400e;">
										<span class="dashicons dashicons-warning"></span>
										<strong><?php esc_html_e( 'Atenção:', 'person-cash-wallet' ); ?></strong>
										<?php esc_html_e( 'Atualmente os cashbacks não têm data de expiração. Workflows de "Cashback Expirando" não serão disparados.', 'person-cash-wallet' ); ?>
									</p>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Status que Geram Cashback -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Status que Geram Cashback', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<div style="padding: 16px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px; margin-bottom: 20px;">
							<strong><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'Como funciona:', 'person-cash-wallet' ); ?></strong>
							<?php esc_html_e( 'Quando um pedido atingir QUALQUER um dos status selecionados abaixo, o cashback será gerado automaticamente. O sistema garante que não haverá duplicatas, mesmo que o pedido passe por múltiplos status selecionados.', 'person-cash-wallet' ); ?>
						</div>

						<p><strong><?php esc_html_e( 'Selecione os status:', 'person-cash-wallet' ); ?></strong></p>

						<!-- Status Positivos -->
						<div style="margin-bottom: 20px; padding: 15px; background: #d4edda; border-radius: 8px;">
							<h4 style="margin-top: 0; color: #155724;">
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Status Recomendados', 'person-cash-wallet' ); ?>
							</h4>
							<?php foreach ( $positive_statuses as $status_slug ) : ?>
								<?php if ( isset( $all_statuses[ $status_slug ] ) ) : ?>
									<label style="display: block; padding: 8px 0;">
										<input 
											type="checkbox" 
											name="generate_cashback_statuses[]" 
											value="<?php echo esc_attr( $status_slug ); ?>"
											<?php checked( in_array( $status_slug, $settings['generate_cashback_statuses'] ) ); ?>
										>
										<strong><?php echo esc_html( $all_statuses[ $status_slug ] ); ?></strong>
										<span style="color: #666;">
											(<?php echo esc_html( $status_slug ); ?>)
											<?php if ( 'completed' === $status_slug ) : ?>
												<em style="color: #155724;">← Recomendado (padrão)</em>
											<?php endif; ?>
										</span>
									</label>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>

						<!-- Outros Status -->
						<?php if ( ! empty( $other_statuses ) ) : ?>
							<div style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border-radius: 8px;">
								<h4 style="margin-top: 0; color: #856404;">
									<span class="dashicons dashicons-info"></span>
									<?php esc_html_e( 'Outros Status', 'person-cash-wallet' ); ?>
								</h4>
								<?php foreach ( $other_statuses as $status_slug ) : ?>
									<?php if ( isset( $all_statuses[ $status_slug ] ) ) : ?>
										<label style="display: block; padding: 8px 0;">
											<input 
												type="checkbox" 
												name="generate_cashback_statuses[]" 
												value="<?php echo esc_attr( $status_slug ); ?>"
												<?php checked( in_array( $status_slug, $settings['generate_cashback_statuses'] ) ); ?>
											>
											<?php echo esc_html( $all_statuses[ $status_slug ] ); ?>
											<span style="color: #666;">(<?php echo esc_html( $status_slug ); ?>)</span>
										</label>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<p class="description">
							<span class="dashicons dashicons-warning"></span>
							<strong><?php esc_html_e( 'Importante:', 'person-cash-wallet' ); ?></strong>
							<?php esc_html_e( 'Não se preocupe com duplicatas! O sistema só gera cashback UMA VEZ por pedido, mesmo que ele passe por vários status selecionados.', 'person-cash-wallet' ); ?>
						</p>
					</div>
				</div>

				<!-- Status que Cancelam/Revertem Cashback -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-dismiss"></span>
							<?php esc_html_e( 'Status que Cancelam/Revertem Cashback', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<div style="padding: 16px; background: #fff3cd; border-left: 4px solid #dba617; border-radius: 4px; margin-bottom: 20px;">
							<strong><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Como funciona:', 'person-cash-wallet' ); ?></strong>
							<?php esc_html_e( 'Se um pedido que já gerou cashback mudar para QUALQUER um dos status abaixo, o cashback será CANCELADO/REVERTIDO automaticamente. Isso é útil para cancelamentos, reembolsos e fraudes.', 'person-cash-wallet' ); ?>
						</div>

						<p><strong><?php esc_html_e( 'Selecione os status:', 'person-cash-wallet' ); ?></strong></p>

						<!-- Status Negativos -->
						<div style="margin-bottom: 20px; padding: 15px; background: #f8d7da; border-radius: 8px;">
							<h4 style="margin-top: 0; color: #721c24;">
								<span class="dashicons dashicons-no"></span>
								<?php esc_html_e( 'Status Recomendados para Reversão', 'person-cash-wallet' ); ?>
							</h4>
							<?php foreach ( $negative_statuses as $status_slug ) : ?>
								<?php if ( isset( $all_statuses[ $status_slug ] ) ) : ?>
									<label style="display: block; padding: 8px 0;">
										<input 
											type="checkbox" 
											name="cancel_cashback_statuses[]" 
											value="<?php echo esc_attr( $status_slug ); ?>"
											<?php checked( in_array( $status_slug, $settings['cancel_cashback_statuses'] ) ); ?>
										>
										<strong><?php echo esc_html( $all_statuses[ $status_slug ] ); ?></strong>
										<span style="color: #666;">
											(<?php echo esc_html( $status_slug ); ?>)
											<?php if ( 'refunded' === $status_slug || 'cancelled' === $status_slug ) : ?>
												<em style="color: #721c24;">← Recomendado</em>
											<?php endif; ?>
										</span>
									</label>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>

						<!-- Outros Status (para reversão) -->
						<?php if ( ! empty( $other_statuses ) ) : ?>
							<div style="margin-bottom: 20px; padding: 15px; background: #e7e7e7; border-radius: 8px;">
								<h4 style="margin-top: 0;">
									<span class="dashicons dashicons-info"></span>
									<?php esc_html_e( 'Outros Status', 'person-cash-wallet' ); ?>
								</h4>
								<?php foreach ( $other_statuses as $status_slug ) : ?>
									<?php if ( isset( $all_statuses[ $status_slug ] ) ) : ?>
										<label style="display: block; padding: 8px 0;">
											<input 
												type="checkbox" 
												name="cancel_cashback_statuses[]" 
												value="<?php echo esc_attr( $status_slug ); ?>"
												<?php checked( in_array( $status_slug, $settings['cancel_cashback_statuses'] ) ); ?>
											>
											<?php echo esc_html( $all_statuses[ $status_slug ] ); ?>
											<span style="color: #666;">(<?php echo esc_html( $status_slug ); ?>)</span>
										</label>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<div style="padding: 16px; background: #fff; border: 2px solid #f8d7da; border-radius: 8px;">
							<h4 style="margin-top: 0; color: #721c24;">
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'O que acontece ao reverter:', 'person-cash-wallet' ); ?>
							</h4>
							<ul style="margin: 0;">
								<li>✅ <?php esc_html_e( 'Se cashback ainda está DISPONÍVEL: Remove da conta do cliente', 'person-cash-wallet' ); ?></li>
								<li>✅ <?php esc_html_e( 'Se cashback já foi USADO: Debita o valor da Wallet do cliente', 'person-cash-wallet' ); ?></li>
								<li>✅ <?php esc_html_e( 'Se cashback já EXPIROU: Nada acontece (já foi perdido)', 'person-cash-wallet' ); ?></li>
								<li>✅ <?php esc_html_e( 'Registra no histórico do cliente', 'person-cash-wallet' ); ?></li>
							</ul>
						</div>
					</div>
				</div>

				<!-- Configurações da Wallet -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-money-alt"></span>
							<?php esc_html_e( 'Transferência para Wallet', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<div style="padding: 16px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px; margin-bottom: 20px;">
							<strong><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'Como funciona:', 'person-cash-wallet' ); ?></strong>
							<?php esc_html_e( 'Quando um pedido é completado, o cashback é gerado como "Pendente". Configure se deseja transferir automaticamente o cashback para a Wallet após um período de tempo.', 'person-cash-wallet' ); ?>
						</div>

						<table class="form-table">
							<tr>
								<th style="width: 200px;"><?php esc_html_e( 'Transferir Automaticamente', 'person-cash-wallet' ); ?></th>
								<td>
									<label class="switch">
										<input type="checkbox" name="auto_transfer_to_wallet" value="yes" <?php checked( isset( $settings['auto_transfer_to_wallet'] ) ? $settings['auto_transfer_to_wallet'] : 'no', 'yes' ); ?>>
										<span class="slider"></span>
									</label>
									<p class="description">
										<span class="dashicons dashicons-info"></span>
										<?php esc_html_e( 'Ativar transferência automática do cashback pendente para a Wallet', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th><label for="auto_transfer_days"><?php esc_html_e( 'Dias para Transferência', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="number" id="auto_transfer_days" name="auto_transfer_days" value="<?php echo esc_attr( isset( $settings['auto_transfer_days'] ) ? $settings['auto_transfer_days'] : 7 ); ?>" min="0" max="365" class="pcw-form-input" style="width: 100px;">
									<span class="description"> <?php esc_html_e( 'dias após o cashback ser gerado', 'person-cash-wallet' ); ?></span>
									<p class="description">
										<span class="dashicons dashicons-clock"></span>
										<?php esc_html_e( 'Número de dias que o cashback fica "Pendente" antes de ser transferido automaticamente para a Wallet. Use 0 para transferir imediatamente.', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th style="width: 200px;"><?php esc_html_e( 'Exibir sem Saldo', 'person-cash-wallet' ); ?></th>
								<td>
									<label class="switch">
										<input type="checkbox" name="show_wallet_without_balance" value="yes" <?php checked( isset( $settings['show_wallet_without_balance'] ) ? $settings['show_wallet_without_balance'] : 'no', 'yes' ); ?>>
										<span class="slider"></span>
									</label>
									<p class="description">
										<span class="dashicons dashicons-info"></span>
										<?php esc_html_e( 'Mostrar seção de Wallet no checkout/pay-order mesmo quando o cliente não tem saldo disponível', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
						</table>

						<div style="padding: 16px; background: #fff3cd; border-left: 4px solid #dba617; border-radius: 4px; margin-top: 15px;">
							<h4 style="margin-top: 0; color: #856404;">
								<span class="dashicons dashicons-warning"></span>
								<?php esc_html_e( 'Importante:', 'person-cash-wallet' ); ?>
							</h4>
							<ul style="margin: 0;">
								<li><?php esc_html_e( 'O cashback pendente só aparece na Wallet após ser transferido.', 'person-cash-wallet' ); ?></li>
								<li><?php esc_html_e( 'Se a transferência automática estiver desativada, o cashback permanece como "Pendente" indefinidamente.', 'person-cash-wallet' ); ?></li>
								<li><?php esc_html_e( 'A transferência é executada diariamente pelo cron do sistema.', 'person-cash-wallet' ); ?></li>
							</ul>
						</div>

						<?php
						// Buscar cashbacks pendentes
						global $wpdb;
						$pending_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}pcw_cashback WHERE status = 'pending'" );
						$pending_total = $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}pcw_cashback WHERE status = 'pending'" );

						if ( $pending_count > 0 ) :
						?>
						<div style="padding: 16px; background: #e7f3ff; border-left: 4px solid #2271b1; border-radius: 4px; margin-top: 15px;">
							<h4 style="margin-top: 0; color: #2271b1;">
								<span class="dashicons dashicons-clock"></span>
								<?php esc_html_e( 'Cashbacks Pendentes', 'person-cash-wallet' ); ?>
							</h4>
							<p style="margin: 10px 0;">
								<?php
								printf(
									/* translators: %1$d: number of pending cashbacks, %2$s: total amount */
									esc_html__( 'Existem %1$d cashback(s) pendente(s) totalizando %2$s aguardando transferência para a Wallet.', 'person-cash-wallet' ),
									absint( $pending_count ),
									wp_kses_post( wc_price( $pending_total ) )
								);
								?>
							</p>
							<button type="button" id="pcw-process-pending-cashback" class="button button-primary">
								<span class="dashicons dashicons-update" style="margin-top: 4px;"></span>
								<?php esc_html_e( 'Processar Pendentes Agora', 'person-cash-wallet' ); ?>
							</button>
							<span id="pcw-process-pending-result" style="margin-left: 10px;"></span>
						</div>

						<script>
						jQuery(document).ready(function($) {
							$('#pcw-process-pending-cashback').on('click', function() {
								var $btn = $(this);
								var $result = $('#pcw-process-pending-result');
								
								$btn.prop('disabled', true);
								$btn.find('.dashicons').addClass('spin');
								$result.html('<span style="color: #666;">Processando...</span>');
								
								$.ajax({
									url: ajaxurl,
									type: 'POST',
									data: {
										action: 'pcw_process_pending_cashback',
										nonce: '<?php echo esc_js( wp_create_nonce( 'pcw_process_pending_cashback' ) ); ?>'
									},
									success: function(response) {
										if (response.success) {
											$result.html('<span style="color: #00a32a;">✓ ' + response.data.message + '</span>');
											setTimeout(function() {
												location.reload();
											}, 2000);
										} else {
											$result.html('<span style="color: #d63638;">✗ ' + response.data.message + '</span>');
										}
									},
									error: function() {
										$result.html('<span style="color: #d63638;">✗ Erro ao processar</span>');
									},
									complete: function() {
										$btn.prop('disabled', false);
										$btn.find('.dashicons').removeClass('spin');
									}
								});
							});
						});
						</script>
						<style>
						.dashicons.spin {
							animation: pcw-spin 1s linear infinite;
						}
						@keyframes pcw-spin {
							100% { transform: rotate(360deg); }
						}
						</style>
						<?php endif; ?>
					</div>
				</div>

				<!-- Integração Detectada -->
				<?php if ( post_type_exists( 'wc_order_status' ) || function_exists( 'wc_order_status_manager_get_order_status_posts' ) ) : ?>
					<div class="pcw-card">
						<div class="pcw-card-header">
							<h2>
								<span class="dashicons dashicons-admin-plugins"></span>
								<?php esc_html_e( 'Integrações Detectadas', 'person-cash-wallet' ); ?>
							</h2>
						</div>
						<div class="pcw-card-body">
							<div style="padding: 16px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px;">
								<strong>✅ WooCommerce Order Status Manager</strong><br>
								<?php esc_html_e( 'Detectado! Todos os status personalizados foram carregados automaticamente nas opções acima.', 'person-cash-wallet' ); ?>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<!-- Botões de Ação -->
				<div style="display: flex; gap: 12px; margin-bottom: 20px;">
					<button type="submit" class="button pcw-button-primary pcw-button-icon">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e( 'Salvar Configurações', 'person-cash-wallet' ); ?>
					</button>
					<button type="button" class="button" onclick="location.reload();">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Cancelar', 'person-cash-wallet' ); ?>
					</button>
				</div>
			</form>
		</div>

		<!-- CSS para Switch Toggle (se ainda não existir) -->
		<style>
		.switch {
			position: relative;
			display: inline-block;
			width: 50px;
			height: 26px;
			flex-shrink: 0;
		}
		.switch input {
			opacity: 0;
			width: 0;
			height: 0;
		}
		.slider {
			position: absolute;
			cursor: pointer;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background-color: #dcdcde;
			transition: .3s;
			border-radius: 26px;
		}
		.slider:before {
			position: absolute;
			content: "";
			height: 20px;
			width: 20px;
			left: 3px;
			bottom: 3px;
			background-color: white;
			transition: .3s;
			border-radius: 50%;
		}
		input:checked + .slider {
			background-color: #00a32a;
		}
		input:checked + .slider:before {
			transform: translateX(24px);
		}
		</style>
		<?php
	}

	/**
	 * Obter configurações padrão
	 *
	 * @return array
	 */
	private function get_default_settings() {
		return array(
			'cashback_enabled'            => 'yes',
			'levels_enabled'              => 'yes',
			'wallet_payment_enabled'      => 'yes',
			'generate_cashback_statuses'  => array( 'completed' ),
			'cancel_cashback_statuses'    => array( 'cancelled', 'refunded', 'failed' ),
			'auto_transfer_to_wallet'     => 'yes',
			'auto_transfer_days'          => 0,
			'show_wallet_without_balance' => 'no',
			'cashback_expiration_days'    => 0,
		);
	}

	/**
	 * Salvar configurações
	 */
	public function handle_save_settings() {
		// Verificar nonce
		if ( ! isset( $_POST['pcw_nonce'] ) || ! wp_verify_nonce( $_POST['pcw_nonce'], 'pcw_save_general_settings' ) ) {
			wp_die( esc_html__( 'Ação não autorizada.', 'person-cash-wallet' ) );
		}

		// Verificar capability
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'person-cash-wallet' ) );
		}

		// Preparar dados
		$settings = array(
			'cashback_enabled'       => isset( $_POST['cashback_enabled'] ) ? 'yes' : 'no',
			'levels_enabled'         => isset( $_POST['levels_enabled'] ) ? 'yes' : 'no',
			'wallet_payment_enabled' => isset( $_POST['wallet_payment_enabled'] ) ? 'yes' : 'no',
		);

		// Status que geram cashback
		$generate_statuses = array();
		if ( isset( $_POST['generate_cashback_statuses'] ) && is_array( $_POST['generate_cashback_statuses'] ) ) {
			foreach ( $_POST['generate_cashback_statuses'] as $status ) {
				$generate_statuses[] = sanitize_text_field( $status );
			}
		}
		$settings['generate_cashback_statuses'] = $generate_statuses;

		// Status que cancelam cashback
		$cancel_statuses = array();
		if ( isset( $_POST['cancel_cashback_statuses'] ) && is_array( $_POST['cancel_cashback_statuses'] ) ) {
			foreach ( $_POST['cancel_cashback_statuses'] as $status ) {
				$cancel_statuses[] = sanitize_text_field( $status );
			}
		}
		$settings['cancel_cashback_statuses'] = $cancel_statuses;

		// Configurações de transferência para wallet
		$settings['auto_transfer_to_wallet']     = isset( $_POST['auto_transfer_to_wallet'] ) ? 'yes' : 'no';
		$settings['auto_transfer_days']          = isset( $_POST['auto_transfer_days'] ) ? absint( $_POST['auto_transfer_days'] ) : 0;
		$settings['show_wallet_without_balance'] = isset( $_POST['show_wallet_without_balance'] ) ? 'yes' : 'no';

		// Configuração de expiração de cashback
		$settings['cashback_expiration_days'] = isset( $_POST['cashback_expiration_days'] ) ? absint( $_POST['cashback_expiration_days'] ) : 0;

		// Sincronizar com options individuais usadas pelo WooCommerce Integration
		update_option( 'pcw_auto_transfer_to_wallet', $settings['auto_transfer_to_wallet'] );
		update_option( 'pcw_auto_transfer_days', $settings['auto_transfer_days'] );
		update_option( 'pcw_show_wallet_without_balance', $settings['show_wallet_without_balance'] );
		update_option( 'pcw_cashback_expiration_days', $settings['cashback_expiration_days'] );

		// Salvar
		update_option( 'pcw_general_settings', $settings );

		// Redirecionar
		wp_safe_redirect( add_query_arg( array( 'page' => 'pcw-settings', 'message' => 'settings_saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Processar cashbacks pendentes via AJAX
	 */
	public function ajax_process_pending_cashback() {
		// Verificar nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'pcw_process_pending_cashback' ) ) {
			wp_send_json_error( array( 'message' => __( 'Ação não autorizada.', 'person-cash-wallet' ) ) );
		}

		// Verificar capability
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'person-cash-wallet' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_cashback';

		// Buscar todos os cashbacks pendentes
		$pending_cashback = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE status = 'pending' LIMIT 500"
		);

		if ( empty( $pending_cashback ) ) {
			wp_send_json_success( array( 'message' => __( 'Nenhum cashback pendente encontrado.', 'person-cash-wallet' ) ) );
		}

		$processed = 0;
		$total_amount = 0;

		foreach ( $pending_cashback as $cashback ) {
			// Atualizar status para available
			$wpdb->update(
				$table,
				array(
					'status'     => 'available',
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $cashback->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			// Transferir para wallet
			$wallet = new PCW_Wallet( $cashback->user_id );
			$wallet->credit(
				$cashback->amount,
				'cashback',
				sprintf( __( 'Cashback do pedido #%d', 'person-cash-wallet' ), $cashback->order_id ),
				$cashback->order_id,
				$cashback->id
			);

			// Marcar cashback como usado (já transferido para wallet)
			$wpdb->update(
				$table,
				array(
					'status'     => 'used',
					'used_date'  => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $cashback->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			$processed++;
			$total_amount += floatval( $cashback->amount );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %1$d: number of cashbacks processed, %2$s: total amount */
					__( '%1$d cashback(s) processado(s). Total: %2$s creditado(s) nas wallets.', 'person-cash-wallet' ),
					$processed,
					wc_price( $total_amount )
				),
			)
		);
	}

	/**
	 * Renderizar aba de IA
	 */
	private function render_ai_tab() {
		$api_key = get_option( 'pcw_openai_api_key', '' );
		$model = get_option( 'pcw_openai_model', 'gpt-4o-mini' );
		$models = PCW_OpenAI::get_available_models();

		?>
		<?php if ( isset( $_GET['message'] ) && 'ai_saved' === $_GET['message'] ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Configurações de IA salvas com sucesso!', 'person-cash-wallet' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'pcw_save_ai_settings', 'pcw_nonce' ); ?>
			<input type="hidden" name="action" value="pcw_save_ai_settings">

			<div class="pcw-card" style="max-width: 800px;">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-admin-site-alt3"></span>
						<?php esc_html_e( 'Integração OpenAI', 'person-cash-wallet' ); ?>
					</h2>
				</div>
				<div class="pcw-card-body">
					<p class="description" style="margin-bottom: 20px;">
						<?php esc_html_e( 'Configure a integração com OpenAI para gerar assuntos e conteúdos de emails automaticamente.', 'person-cash-wallet' ); ?>
					</p>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="openai_api_key"><?php esc_html_e( 'API Key', 'person-cash-wallet' ); ?></label>
							</th>
							<td>
								<input type="password" id="openai_api_key" name="openai_api_key" 
									value="<?php echo esc_attr( $api_key ); ?>" 
									class="regular-text" 
									placeholder="sk-...">
								<p class="description">
									<?php printf(
										esc_html__( 'Obtenha sua API Key em %s', 'person-cash-wallet' ),
										'<a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>'
									); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="openai_model"><?php esc_html_e( 'Modelo', 'person-cash-wallet' ); ?></label>
							</th>
							<td>
								<select id="openai_model" name="openai_model" class="regular-text">
									<?php foreach ( $models as $model_id => $model_name ) : ?>
										<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $model, $model_id ); ?>>
											<?php echo esc_html( $model_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'GPT-4o Mini é recomendado para equilibrar custo e qualidade.', 'person-cash-wallet' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<div style="margin-top: 20px; padding: 15px; background: #f8f8f8; border-radius: 6px;">
						<button type="button" id="test-ai-connection" class="button">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Testar Conexão', 'person-cash-wallet' ); ?>
						</button>
						<span id="ai-test-result" style="margin-left: 10px;"></span>
					</div>
				</div>
				<div class="pcw-card-footer">
					<button type="submit" class="button button-primary">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e( 'Salvar Configurações', 'person-cash-wallet' ); ?>
					</button>
				</div>
			</div>
		</form>

		<script>
		jQuery(document).ready(function($) {
			$('#test-ai-connection').on('click', function() {
				var $btn = $(this);
				var $result = $('#ai-test-result');
				
				$btn.prop('disabled', true);
				$result.html('<span style="color:#666;">Testando...</span>');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'pcw_test_ai_connection',
						nonce: '<?php echo wp_create_nonce( 'pcw_test_ai' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$result.html('<span style="color:#00a32a;"><span class="dashicons dashicons-yes"></span> ' + response.data.message + '</span>');
						} else {
							$result.html('<span style="color:#dc2626;"><span class="dashicons dashicons-no"></span> ' + response.data.message + '</span>');
						}
					},
					error: function() {
						$result.html('<span style="color:#dc2626;">Erro de conexão</span>');
					},
					complete: function() {
						$btn.prop('disabled', false);
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Renderizar aba de SMTP
	 */
	private function render_smtp_tab() {
		$smtp_accounts = PCW_SMTP_Accounts::instance();
		$accounts = $smtp_accounts->get_all();
		$fluent_accounts = $smtp_accounts->get_fluent_smtp_connections();
		$editing_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$editing = $editing_id > 0 ? $smtp_accounts->get( $editing_id ) : null;

		?>
		<?php if ( isset( $_GET['message'] ) && 'smtp_saved' === $_GET['message'] ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Conta SMTP salva com sucesso!', 'person-cash-wallet' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="pcw-grid-2">
			<!-- Lista de Contas -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-email"></span>
						<?php esc_html_e( 'Contas SMTP', 'person-cash-wallet' ); ?>
					</h2>
				</div>
				<div class="pcw-card-body" style="padding: 0;">
					<?php if ( empty( $accounts ) && empty( $fluent_accounts ) ) : ?>
						<div style="padding: 40px; text-align: center; color: #666;">
							<span class="dashicons dashicons-email" style="font-size: 48px; width: 48px; height: 48px; margin-bottom: 10px;"></span>
							<p><?php esc_html_e( 'Nenhuma conta SMTP configurada', 'person-cash-wallet' ); ?></p>
						</div>
					<?php else : ?>
						<table class="pcw-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Nome', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Email', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Tipo', 'person-cash-wallet' ); ?></th>
									<th></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $accounts as $account ) : ?>
								<tr data-id="<?php echo esc_attr( $account->id ); ?>">
									<td><strong><?php echo esc_html( $account->name ); ?></strong></td>
									<td><?php echo esc_html( $account->from_email ); ?></td>
									<td>
										<span class="pcw-badge"><?php echo esc_html( ucfirst( $account->provider ) ); ?></span>
									</td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-settings&tab=smtp&edit=' . $account->id ) ); ?>" 
											class="button button-small">
											<span class="dashicons dashicons-edit"></span>
										</a>
										<button type="button" class="button button-small pcw-delete-smtp" data-id="<?php echo esc_attr( $account->id ); ?>">
											<span class="dashicons dashicons-trash"></span>
										</button>
									</td>
								</tr>
								<?php endforeach; ?>

								<?php if ( ! empty( $fluent_accounts ) ) : ?>
									<?php foreach ( $fluent_accounts as $fa ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $fa['name'] ); ?></strong></td>
										<td><?php echo esc_html( $fa['from_email'] ); ?></td>
										<td>
											<span class="pcw-badge pcw-badge-info">FluentSMTP</span>
										</td>
										<td>
											<span class="description"><?php esc_html_e( 'Gerenciado pelo FluentSMTP', 'person-cash-wallet' ); ?></span>
										</td>
									</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>

			<!-- Formulário -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-plus-alt"></span>
						<?php echo $editing ? esc_html__( 'Editar Conta', 'person-cash-wallet' ) : esc_html__( 'Nova Conta SMTP', 'person-cash-wallet' ); ?>
					</h2>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'pcw_save_smtp_account', 'pcw_nonce' ); ?>
					<input type="hidden" name="action" value="pcw_save_smtp_account">
					<input type="hidden" name="account_id" value="<?php echo esc_attr( $editing_id ); ?>">

					<div class="pcw-card-body">
						<table class="form-table">
							<tr>
								<th><label for="smtp_name"><?php esc_html_e( 'Nome', 'person-cash-wallet' ); ?> *</label></th>
								<td>
									<input type="text" id="smtp_name" name="name" class="regular-text" required
										value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>">
								</td>
							</tr>
							<tr>
								<th><label for="smtp_from_email"><?php esc_html_e( 'Email de Envio', 'person-cash-wallet' ); ?> *</label></th>
								<td>
									<input type="email" id="smtp_from_email" name="from_email" class="regular-text" required
										value="<?php echo esc_attr( $editing ? $editing->from_email : '' ); ?>">
								</td>
							</tr>
							<tr>
								<th><label for="smtp_from_name"><?php esc_html_e( 'Nome do Remetente', 'person-cash-wallet' ); ?> *</label></th>
								<td>
									<input type="text" id="smtp_from_name" name="from_name" class="regular-text" required
										value="<?php echo esc_attr( $editing ? $editing->from_name : get_bloginfo( 'name' ) ); ?>">
								</td>
							</tr>
							<tr>
								<th><label for="smtp_host"><?php esc_html_e( 'Servidor SMTP', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="text" id="smtp_host" name="host" class="regular-text"
										value="<?php echo esc_attr( $editing ? $editing->host : '' ); ?>"
										placeholder="smtp.exemplo.com">
								</td>
							</tr>
							<tr>
								<th><label for="smtp_port"><?php esc_html_e( 'Porta', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="number" id="smtp_port" name="port" style="width: 80px;"
										value="<?php echo esc_attr( $editing ? $editing->port : 587 ); ?>">
									<select name="encryption" style="margin-left: 10px;">
										<option value="tls" <?php selected( $editing ? $editing->encryption : 'tls', 'tls' ); ?>>TLS</option>
										<option value="ssl" <?php selected( $editing ? $editing->encryption : '', 'ssl' ); ?>>SSL</option>
										<option value="" <?php selected( $editing ? $editing->encryption : '', '' ); ?>><?php esc_html_e( 'Nenhum', 'person-cash-wallet' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th><label for="smtp_username"><?php esc_html_e( 'Usuário', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="text" id="smtp_username" name="username" class="regular-text"
										value="<?php echo esc_attr( $editing ? $editing->username : '' ); ?>">
								</td>
							</tr>
							<tr>
								<th><label for="smtp_password"><?php esc_html_e( 'Senha', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="password" id="smtp_password" name="password" class="regular-text"
										placeholder="<?php echo $editing ? esc_attr__( '(manter atual)', 'person-cash-wallet' ) : ''; ?>">
								</td>
							</tr>
							<tr>
								<th><label for="smtp_daily_limit"><?php esc_html_e( 'Limite Diário', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="number" id="smtp_daily_limit" name="daily_limit" style="width: 100px;"
										value="<?php echo esc_attr( $editing ? $editing->daily_limit : 0 ); ?>">
									<p class="description"><?php esc_html_e( '0 = sem limite', 'person-cash-wallet' ); ?></p>
								</td>
							</tr>
						</table>
					</div>
					<div class="pcw-card-footer">
						<?php if ( $editing ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-settings&tab=smtp' ) ); ?>" class="button">
								<?php esc_html_e( 'Cancelar', 'person-cash-wallet' ); ?>
							</a>
						<?php endif; ?>
						<button type="submit" class="button button-primary">
							<span class="dashicons dashicons-saved"></span>
							<?php echo $editing ? esc_html__( 'Atualizar', 'person-cash-wallet' ) : esc_html__( 'Adicionar Conta', 'person-cash-wallet' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>

		<style>
		.pcw-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 20px; }
		@media (max-width: 1200px) { .pcw-grid-2 { grid-template-columns: 1fr; } }
		</style>

		<script>
		jQuery(document).ready(function($) {
			$('.pcw-delete-smtp').on('click', function() {
				if (!confirm('<?php echo esc_js( __( 'Tem certeza que deseja excluir esta conta?', 'person-cash-wallet' ) ); ?>')) {
					return;
				}
				
				var $tr = $(this).closest('tr');
				var id = $(this).data('id');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'pcw_delete_smtp_account',
						nonce: '<?php echo wp_create_nonce( 'pcw_smtp_delete' ); ?>',
						id: id
					},
					success: function() {
						$tr.fadeOut(300, function() { $(this).remove(); });
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Salvar configurações de IA
	 */
	public function handle_save_ai_settings() {
		if ( ! isset( $_POST['pcw_nonce'] ) || ! wp_verify_nonce( $_POST['pcw_nonce'], 'pcw_save_ai_settings' ) ) {
			wp_die( esc_html__( 'Ação não autorizada.', 'person-cash-wallet' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'person-cash-wallet' ) );
		}

		$api_key = isset( $_POST['openai_api_key'] ) ? sanitize_text_field( $_POST['openai_api_key'] ) : '';
		$model = isset( $_POST['openai_model'] ) ? sanitize_text_field( $_POST['openai_model'] ) : 'gpt-4o-mini';

		update_option( 'pcw_openai_api_key', $api_key );
		update_option( 'pcw_openai_model', $model );

		wp_safe_redirect( add_query_arg( array( 'page' => 'pcw-settings', 'tab' => 'ai', 'message' => 'ai_saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Salvar conta SMTP
	 */
	public function handle_save_smtp_account() {
		if ( ! isset( $_POST['pcw_nonce'] ) || ! wp_verify_nonce( $_POST['pcw_nonce'], 'pcw_save_smtp_account' ) ) {
			wp_die( esc_html__( 'Ação não autorizada.', 'person-cash-wallet' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'person-cash-wallet' ) );
		}

		$account_id = isset( $_POST['account_id'] ) ? absint( $_POST['account_id'] ) : 0;

		$data = array(
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '',
			'from_email'  => isset( $_POST['from_email'] ) ? sanitize_email( $_POST['from_email'] ) : '',
			'from_name'   => isset( $_POST['from_name'] ) ? sanitize_text_field( $_POST['from_name'] ) : '',
			'host'        => isset( $_POST['host'] ) ? sanitize_text_field( $_POST['host'] ) : '',
			'port'        => isset( $_POST['port'] ) ? absint( $_POST['port'] ) : 587,
			'encryption'  => isset( $_POST['encryption'] ) ? sanitize_text_field( $_POST['encryption'] ) : 'tls',
			'username'    => isset( $_POST['username'] ) ? sanitize_text_field( $_POST['username'] ) : '',
			'password'    => isset( $_POST['password'] ) && ! empty( $_POST['password'] ) ? $_POST['password'] : '',
			'daily_limit' => isset( $_POST['daily_limit'] ) ? absint( $_POST['daily_limit'] ) : 0,
		);

		$smtp = PCW_SMTP_Accounts::instance();

		if ( $account_id > 0 ) {
			$smtp->update( $account_id, $data );
		} else {
			$smtp->create( $data );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'pcw-settings', 'tab' => 'smtp', 'message' => 'smtp_saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * AJAX: Testar conexão com IA
	 */
	public function ajax_test_ai_connection() {
		check_ajax_referer( 'pcw_test_ai', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$result = PCW_OpenAI::instance()->test_connection();

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * AJAX: Testar conexão SMTP
	 */
	public function ajax_test_smtp_connection() {
		check_ajax_referer( 'pcw_smtp_test', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$account_id = isset( $_POST['account_id'] ) ? absint( $_POST['account_id'] ) : 0;
		$result = PCW_SMTP_Accounts::instance()->test_connection( $account_id );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * AJAX: Deletar conta SMTP
	 */
	public function ajax_delete_smtp_account() {
		check_ajax_referer( 'pcw_smtp_delete', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		PCW_SMTP_Accounts::instance()->delete( $id );

		wp_send_json_success();
	}

	/**
	 * Renderizar aba Google Analytics
	 */
	private function render_analytics_tab() {
		$property_id     = get_option( 'pcw_ga4_property_id', '' );
		$credentials     = get_option( 'pcw_ga4_credentials', '' );
		$has_credentials = ! empty( $credentials );

		// Extrair email da service account para exibição
		$service_email = '';
		if ( $has_credentials ) {
			$cred_data = json_decode( $credentials, true );
			if ( isset( $cred_data['client_email'] ) ) {
				$service_email = $cred_data['client_email'];
			}
		}

		// Verificar conexão
		$ga = PCW_Google_Analytics::get_instance();
		$is_connected = $ga->is_configured();

		?>
		<div class="pcw-settings-content pcw-card">
			<h2>
				<span class="dashicons dashicons-chart-area"></span>
				<?php esc_html_e( 'Integração Google Analytics 4', 'person-cash-wallet' ); ?>
			</h2>

			<div class="pcw-notice pcw-notice-info">
				<p>
					<strong><?php esc_html_e( 'Como configurar:', 'person-cash-wallet' ); ?></strong>
				</p>
				<ol>
					<li><?php esc_html_e( 'Acesse o Google Cloud Console e crie um projeto', 'person-cash-wallet' ); ?></li>
					<li><?php esc_html_e( 'Ative a API "Google Analytics Data API"', 'person-cash-wallet' ); ?></li>
					<li><?php esc_html_e( 'Crie uma Service Account e baixe o arquivo JSON de credenciais', 'person-cash-wallet' ); ?></li>
					<li><?php esc_html_e( 'No Google Analytics, adicione o email da Service Account como usuário com permissão de leitura', 'person-cash-wallet' ); ?></li>
					<li><?php esc_html_e( 'Cole o Property ID (encontrado em Admin > Property Settings)', 'person-cash-wallet' ); ?></li>
				</ol>
				<p>
					<a href="https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com" target="_blank" class="button button-secondary">
						<?php esc_html_e( 'Abrir Google Cloud Console', 'person-cash-wallet' ); ?>
					</a>
				</p>
			</div>

			<?php
			// Mensagens de erro/sucesso
			$error = isset( $_GET['error'] ) ? sanitize_text_field( $_GET['error'] ) : '';
			$saved = isset( $_GET['saved'] ) ? true : false;

			if ( $saved ) : ?>
				<div class="pcw-notice pcw-notice-success">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Configurações salvas com sucesso!', 'person-cash-wallet' ); ?>
				</div>
			<?php endif;

			if ( 'invalid_json' === $error ) : ?>
				<div class="pcw-notice pcw-notice-error">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Erro: JSON inválido. Verifique se copiou o conteúdo completo do arquivo.', 'person-cash-wallet' ); ?>
				</div>
			<?php elseif ( 'missing_fields' === $error ) : ?>
				<div class="pcw-notice pcw-notice-error">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Erro: JSON incompleto. Campos obrigatórios: type, project_id, private_key, client_email', 'person-cash-wallet' ); ?>
				</div>
			<?php elseif ( 'not_service_account' === $error ) : ?>
				<div class="pcw-notice pcw-notice-error">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Erro: O JSON precisa ser de uma Service Account (type: service_account)', 'person-cash-wallet' ); ?>
				</div>
			<?php endif;

			if ( $is_connected ) : ?>
				<div class="pcw-notice pcw-notice-success">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Conectado ao Google Analytics 4', 'person-cash-wallet' ); ?>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pcw_save_ga4_settings">
				<?php wp_nonce_field( 'pcw_save_ga4_settings', 'pcw_ga4_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="ga4_property_id"><?php esc_html_e( 'Property ID', 'person-cash-wallet' ); ?></label>
						</th>
						<td>
							<input type="text" 
								id="ga4_property_id" 
								name="ga4_property_id" 
								value="<?php echo esc_attr( $property_id ); ?>" 
								class="regular-text"
								placeholder="123456789">
							<p class="description">
								<?php esc_html_e( 'O ID numérico da propriedade GA4 (sem o prefixo "properties/")', 'person-cash-wallet' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ga4_credentials"><?php esc_html_e( 'Credenciais JSON', 'person-cash-wallet' ); ?></label>
						</th>
						<td>
							<?php if ( $has_credentials && $service_email ) : ?>
								<div class="pcw-credentials-saved">
									<div class="pcw-credentials-info">
										<span class="dashicons dashicons-yes-alt" style="color: #10b981;"></span>
										<div>
											<strong><?php esc_html_e( 'Credenciais configuradas', 'person-cash-wallet' ); ?></strong>
											<p style="margin: 5px 0 0 0; color: #64748b;">
												<?php esc_html_e( 'Service Account:', 'person-cash-wallet' ); ?>
												<code><?php echo esc_html( $service_email ); ?></code>
											</p>
										</div>
									</div>
									<details style="margin-top: 15px;">
										<summary style="cursor: pointer; color: #3b82f6;">
											<?php esc_html_e( 'Substituir credenciais', 'person-cash-wallet' ); ?>
										</summary>
										<textarea 
											id="ga4_credentials" 
											name="ga4_credentials" 
											rows="8" 
											class="large-text code"
											style="margin-top: 10px;"
											placeholder='{"type": "service_account", "project_id": "...", ...}'></textarea>
										<p class="description">
											<?php esc_html_e( 'Cole um novo JSON para substituir as credenciais atuais', 'person-cash-wallet' ); ?>
										</p>
									</details>
								</div>
							<?php else : ?>
								<textarea 
									id="ga4_credentials" 
									name="ga4_credentials" 
									rows="10" 
									class="large-text code"
									placeholder='{"type": "service_account", "project_id": "...", ...}'></textarea>
								<p class="description">
									<?php esc_html_e( 'Cole o conteúdo completo do arquivo JSON de credenciais da Service Account', 'person-cash-wallet' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e( 'Salvar Configurações', 'person-cash-wallet' ); ?>
					</button>

					<?php if ( $is_connected ) : ?>
						<button type="button" class="button button-secondary" id="pcw-test-ga4">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Testar Conexão', 'person-cash-wallet' ); ?>
						</button>
					<?php endif; ?>
				</p>
			</form>

			<?php if ( $is_connected ) : ?>
				<hr>
				<h3><?php esc_html_e( 'Status da Integração', 'person-cash-wallet' ); ?></h3>
				<div id="pcw-ga4-status">
					<p class="description"><?php esc_html_e( 'Clique em "Testar Conexão" para verificar', 'person-cash-wallet' ); ?></p>
				</div>
			<?php endif; ?>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#pcw-test-ga4').on('click', function() {
				var $btn = $(this);
				var $status = $('#pcw-ga4-status');

				$btn.prop('disabled', true).find('.dashicons').addClass('spin');
				$status.html('<p>Testando conexão...</p>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'pcw_test_ga4_connection',
						nonce: '<?php echo wp_create_nonce( 'pcw_admin' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$status.html(
								'<div class="pcw-notice pcw-notice-success">' +
								'<span class="dashicons dashicons-yes-alt"></span> ' +
								response.data.message + '<br>' +
								'<strong>Usuários em tempo real:</strong> ' + response.data.realtime_users +
								'</div>'
							);
						} else {
							$status.html(
								'<div class="pcw-notice pcw-notice-error">' +
								'<span class="dashicons dashicons-warning"></span> ' +
								response.data.message +
								'</div>'
							);
						}
					},
					error: function() {
						$status.html(
							'<div class="pcw-notice pcw-notice-error">Erro de conexão</div>'
						);
					},
					complete: function() {
						$btn.prop('disabled', false).find('.dashicons').removeClass('spin');
					}
				});
			});
		});
		</script>
		<style>
			.dashicons.spin {
				animation: spin 1s linear infinite;
			}
			@keyframes spin {
				100% { transform: rotate(360deg); }
			}
		</style>
		<?php
	}

	/**
	 * Salvar configurações do GA4
	 */
	public function handle_save_ga4_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Sem permissão', 'person-cash-wallet' ) );
		}

		check_admin_referer( 'pcw_save_ga4_settings', 'pcw_ga4_nonce' );

		$property_id = isset( $_POST['ga4_property_id'] ) ? sanitize_text_field( $_POST['ga4_property_id'] ) : '';
		
		// Pegar credenciais sem sanitizar (JSON precisa preservar caracteres especiais)
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$credentials_raw = isset( $_POST['ga4_credentials'] ) ? wp_unslash( $_POST['ga4_credentials'] ) : '';

		// Limpar property ID (remover espaços e prefixo se houver)
		$property_id = preg_replace( '/[^0-9]/', '', $property_id );

		// Validar JSON das credenciais
		$credentials = '';
		if ( ! empty( $credentials_raw ) ) {
			$decoded = json_decode( $credentials_raw, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				wp_redirect( admin_url( 'admin.php?page=pcw-settings&tab=analytics&error=invalid_json' ) );
				exit;
			}

			// Verificar campos obrigatórios
			$required_fields = array( 'type', 'project_id', 'private_key', 'client_email' );
			foreach ( $required_fields as $field ) {
				if ( empty( $decoded[ $field ] ) ) {
					wp_redirect( admin_url( 'admin.php?page=pcw-settings&tab=analytics&error=missing_fields' ) );
					exit;
				}
			}

			// Verificar se é service account
			if ( 'service_account' !== $decoded['type'] ) {
				wp_redirect( admin_url( 'admin.php?page=pcw-settings&tab=analytics&error=not_service_account' ) );
				exit;
			}

			// Se passou na validação, re-encodar para garantir formato limpo
			$credentials = wp_json_encode( $decoded );
		}

		update_option( 'pcw_ga4_property_id', $property_id );

		// Só atualizar credenciais se foram fornecidas (permite atualizar só o property ID)
		if ( ! empty( $credentials ) ) {
			update_option( 'pcw_ga4_credentials', $credentials );
		}

		// Limpar cache de token
		delete_transient( 'pcw_ga4_access_token' );

		wp_redirect( admin_url( 'admin.php?page=pcw-settings&tab=analytics&saved=1' ) );
		exit;
	}

	/**
	 * Renderizar aba de indicações
	 */
	private function render_referrals_tab() {
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
				'email_subject'              => sanitize_text_field( $_POST['email_subject'] ),
				'email_body'                 => wp_kses_post( $_POST['email_body'] ),
			);

			PCW_Referral_Rewards::instance()->save_settings( $settings );

			echo '<div class="notice notice-success"><p>' . esc_html__( 'Configurações salvas!', 'person-cash-wallet' ) . '</p></div>';
		}

		$settings = PCW_Referral_Rewards::instance()->get_settings();

		// Valores padrão para email se não existirem
		$default_subject = __( '💰 Ganhe R$ indicando amigos!', 'person-cash-wallet' );
		$default_body = __( 'Olá {customer_name}!

Que tal ganhar dinheiro indicando amigos para nossa loja?

Para cada amigo que comprar usando seu código de indicação, você ganha R$ {reward_amount} de crédito!

Seu código de indicação: {referral_code}
Seu link de indicação: {referral_link}

É fácil: basta compartilhar seu link ou código com amigos e familiares!

Abraços,
{site_name}', 'person-cash-wallet' );

		$email_subject = isset( $settings['email_subject'] ) && ! empty( $settings['email_subject'] ) ? $settings['email_subject'] : $default_subject;
		$email_body = isset( $settings['email_body'] ) && ! empty( $settings['email_body'] ) ? $settings['email_body'] : $default_body;

		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'pcw_referral_settings' ); ?>

			<div class="pcw-settings-section">
				<h2><?php esc_html_e( 'Configurações Gerais', 'person-cash-wallet' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Habilitar Sistema', 'person-cash-wallet' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'], 'yes' ); ?> />
								<?php esc_html_e( 'Habilitar Sistema de Indicações', 'person-cash-wallet' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Dias do Cookie', 'person-cash-wallet' ); ?></th>
						<td>
							<input type="number" name="cookie_days" value="<?php echo esc_attr( $settings['cookie_days'] ); ?>" min="1" max="365" class="small-text" />
							<p class="description"><?php esc_html_e( 'Quantos dias o código de indicação fica salvo no navegador do visitante.', 'person-cash-wallet' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="pcw-settings-section">
				<h2><?php esc_html_e( 'Recompensa do Indicador', 'person-cash-wallet' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Tipo de Recompensa', 'person-cash-wallet' ); ?></th>
						<td>
							<select name="reward_type">
								<option value="fixed" <?php selected( $settings['reward_type'], 'fixed' ); ?>><?php esc_html_e( 'Valor Fixo (R$)', 'person-cash-wallet' ); ?></option>
								<option value="percentage" <?php selected( $settings['reward_type'], 'percentage' ); ?>><?php esc_html_e( 'Porcentagem (%)', 'person-cash-wallet' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Valor/Porcentagem', 'person-cash-wallet' ); ?></th>
						<td>
							<input type="number" name="reward_amount" value="<?php echo esc_attr( $settings['reward_amount'] ); ?>" min="0" step="0.01" class="small-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Valor Máximo (se %)', 'person-cash-wallet' ); ?></th>
						<td>
							<input type="number" name="max_reward_amount" value="<?php echo esc_attr( $settings['max_reward_amount'] ); ?>" min="0" step="0.01" class="small-text" />
							<p class="description"><?php esc_html_e( 'Deixe 0 para não limitar.', 'person-cash-wallet' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Valor Mínimo do Pedido', 'person-cash-wallet' ); ?></th>
						<td>
							<input type="number" name="min_order_amount" value="<?php echo esc_attr( $settings['min_order_amount'] ); ?>" min="0" step="0.01" class="small-text" />
							<p class="description"><?php esc_html_e( 'Valor mínimo do pedido para gerar recompensa. Deixe 0 para qualquer valor.', 'person-cash-wallet' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status que Gera Recompensa', 'person-cash-wallet' ); ?></th>
						<td>
							<?php
							$order_statuses = wc_get_order_statuses();
							$selected_statuses = $settings['reward_order_statuses'];
							?>
							<select name="reward_order_statuses[]" multiple style="height: 100px; min-width: 200px;">
								<?php foreach ( $order_statuses as $status_key => $status_label ) : ?>
									<?php $status_clean = str_replace( 'wc-', '', $status_key ); ?>
									<option value="<?php echo esc_attr( $status_clean ); ?>" <?php echo in_array( $status_clean, $selected_statuses, true ) ? 'selected' : ''; ?>>
										<?php echo esc_html( $status_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Limite de Recompensas', 'person-cash-wallet' ); ?></th>
						<td>
							<select name="reward_limit_type">
								<option value="first" <?php selected( $settings['reward_limit_type'], 'first' ); ?>><?php esc_html_e( 'Apenas primeira compra', 'person-cash-wallet' ); ?></option>
								<option value="limited" <?php selected( $settings['reward_limit_type'], 'limited' ); ?>><?php esc_html_e( 'Primeiras X compras', 'person-cash-wallet' ); ?></option>
								<option value="unlimited" <?php selected( $settings['reward_limit_type'], 'unlimited' ); ?>><?php esc_html_e( 'Todas as compras', 'person-cash-wallet' ); ?></option>
							</select>
							<input type="number" name="reward_limit_count" value="<?php echo esc_attr( $settings['reward_limit_count'] ); ?>" min="1" max="100" class="small-text" />
							<span class="description"><?php esc_html_e( 'compras (se limitado)', 'person-cash-wallet' ); ?></span>
						</td>
					</tr>
				</table>
			</div>

			<div class="pcw-settings-section">
				<h2><?php esc_html_e( 'Recompensa do Indicado (Opcional)', 'person-cash-wallet' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Habilitar', 'person-cash-wallet' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="referred_reward_enabled" value="1" <?php checked( $settings['referred_reward_enabled'], 'yes' ); ?> />
								<?php esc_html_e( 'Dar recompensa também para quem foi indicado', 'person-cash-wallet' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tipo de Recompensa', 'person-cash-wallet' ); ?></th>
						<td>
							<select name="referred_reward_type">
								<option value="fixed" <?php selected( $settings['referred_reward_type'], 'fixed' ); ?>><?php esc_html_e( 'Valor Fixo (R$)', 'person-cash-wallet' ); ?></option>
								<option value="percentage" <?php selected( $settings['referred_reward_type'], 'percentage' ); ?>><?php esc_html_e( 'Porcentagem (%)', 'person-cash-wallet' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Valor/Porcentagem', 'person-cash-wallet' ); ?></th>
						<td>
							<input type="number" name="referred_reward_amount" value="<?php echo esc_attr( $settings['referred_reward_amount'] ); ?>" min="0" step="0.01" class="small-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Apenas Primeira Compra', 'person-cash-wallet' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="referred_reward_first_only" value="1" <?php checked( $settings['referred_reward_first_only'], 'yes' ); ?> />
								<?php esc_html_e( 'Apenas na primeira compra do indicado', 'person-cash-wallet' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<div class="pcw-settings-section">
				<h2><?php esc_html_e( 'Email de Solicitação de Indicação', 'person-cash-wallet' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enviar Após', 'person-cash-wallet' ); ?></th>
						<td>
							<input type="number" name="email_days_after_order" value="<?php echo esc_attr( $settings['email_days_after_order'] ); ?>" min="1" max="90" class="small-text" />
							<?php esc_html_e( 'dias após o pedido ser concluído', 'person-cash-wallet' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Assunto do Email', 'person-cash-wallet' ); ?></th>
						<td>
							<input type="text" name="email_subject" value="<?php echo esc_attr( $email_subject ); ?>" class="large-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Corpo do Email', 'person-cash-wallet' ); ?></th>
						<td>
							<?php
							wp_editor(
								$email_body,
								'email_body',
								array(
									'textarea_name' => 'email_body',
									'textarea_rows' => 12,
									'media_buttons' => false,
									'teeny'         => true,
								)
							);
							?>
							<p class="description">
								<?php esc_html_e( 'Variáveis disponíveis:', 'person-cash-wallet' ); ?><br>
								<code>{customer_name}</code> - <?php esc_html_e( 'Nome do cliente', 'person-cash-wallet' ); ?><br>
								<code>{reward_amount}</code> - <?php esc_html_e( 'Valor da recompensa', 'person-cash-wallet' ); ?><br>
								<code>{referral_code}</code> - <?php esc_html_e( 'Código de indicação', 'person-cash-wallet' ); ?><br>
								<code>{referral_link}</code> - <?php esc_html_e( 'Link de indicação', 'person-cash-wallet' ); ?><br>
								<code>{site_name}</code> - <?php esc_html_e( 'Nome do site', 'person-cash-wallet' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<p class="submit">
				<button type="submit" name="pcw_save_referral_settings" class="button button-primary">
					<?php esc_html_e( 'Salvar Configurações', 'person-cash-wallet' ); ?>
				</button>
			</p>
		</form>

		<style>
			.pcw-settings-section {
				background: #fff;
				padding: 20px;
				margin: 20px 0;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
			}
			.pcw-settings-section h2 {
				margin-top: 0;
				padding-bottom: 10px;
				border-bottom: 1px solid #eee;
			}
		</style>
		<?php
	}

	/**
	 * Renderizar tab Personizi WhatsApp
	 */
	private function render_personizi_tab() {
		$personizi = PCW_Personizi_Integration::instance();
		$api_token = $personizi->get_api_token();
		$default_from = $personizi->get_default_from();
		
		// Testar conexão e buscar contas
		$accounts = array();
		$connection_status = 'unknown';
		$connection_message = '';
		
		$test = $personizi->test_connection();
		if ( is_wp_error( $test ) ) {
			$connection_status = 'error';
			$connection_message = $test->get_error_message();
		} else {
			$connection_status = 'success';
			$accounts = $personizi->get_whatsapp_accounts();
			if ( is_wp_error( $accounts ) ) {
				$accounts = array();
			}
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="pcw_save_personizi_settings">
			<?php wp_nonce_field( 'pcw_save_personizi_settings' ); ?>

			<div class="pcw-card" style="max-width: 800px; margin-top: 20px;">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-admin-comments"></span>
						<?php esc_html_e( 'Configuração do Personizi WhatsApp', 'person-cash-wallet' ); ?>
					</h2>
				</div>
				<div class="pcw-card-body">
					<p class="description" style="margin-bottom: 20px;">
						<?php esc_html_e( 'Configure a integração com o seu sistema Personizi para enviar mensagens WhatsApp automatizadas através das automações.', 'person-cash-wallet' ); ?>
					</p>

					<!-- Status da Conexão -->
					<?php if ( $connection_status === 'success' ) : ?>
						<div class="notice notice-success inline" style="margin: 0 0 20px;">
							<p>
								<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
								<strong><?php esc_html_e( 'Conectado com sucesso!', 'person-cash-wallet' ); ?></strong>
							</p>
						</div>
					<?php elseif ( $connection_status === 'error' ) : ?>
						<div class="notice notice-error inline" style="margin: 0 0 20px;">
							<p>
								<span class="dashicons dashicons-warning"></span>
								<strong><?php esc_html_e( 'Erro de conexão:', 'person-cash-wallet' ); ?></strong>
								<?php echo esc_html( $connection_message ); ?>
							</p>
						</div>
					<?php endif; ?>

					<!-- Novo Sistema de Múltiplos Números -->
					<div style="margin: 0 0 30px; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; color: #fff; box-shadow: 0 4px 14px rgba(102, 126, 234, 0.3);">
						<div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px;">
							<span class="dashicons dashicons-list-view" style="font-size: 48px; width: 48px; height: 48px;"></span>
							<div>
								<h3 style="margin: 0 0 6px; color: #fff; font-size: 20px;">
									✨ <?php esc_html_e( 'Sistema de Múltiplos Números & Rate Limiting', 'person-cash-wallet' ); ?>
								</h3>
								<p style="margin: 0; opacity: 0.95; font-size: 14px;">
									<?php esc_html_e( 'Configure múltiplos números WhatsApp, defina limites de envio por hora e escolha estratégias de distribuição!', 'person-cash-wallet' ); ?>
								</p>
							</div>
						</div>
						
						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px;">
							<div style="background: rgba(255, 255, 255, 0.15); padding: 12px; border-radius: 8px; backdrop-filter: blur(10px);">
								<div style="font-size: 24px; margin-bottom: 4px;">📱</div>
								<div style="font-size: 12px; opacity: 0.9; margin-bottom: 2px;"><?php esc_html_e( 'Múltiplos Números', 'person-cash-wallet' ); ?></div>
								<div style="font-size: 11px; opacity: 0.75;"><?php esc_html_e( 'Configure quantos quiser', 'person-cash-wallet' ); ?></div>
							</div>
							<div style="background: rgba(255, 255, 255, 0.15); padding: 12px; border-radius: 8px; backdrop-filter: blur(10px);">
								<div style="font-size: 24px; margin-bottom: 4px;">⏱️</div>
								<div style="font-size: 12px; opacity: 0.9; margin-bottom: 2px;"><?php esc_html_e( 'Rate Limiting', 'person-cash-wallet' ); ?></div>
								<div style="font-size: 11px; opacity: 0.75;"><?php esc_html_e( 'Limite de msgs/hora', 'person-cash-wallet' ); ?></div>
							</div>
							<div style="background: rgba(255, 255, 255, 0.15); padding: 12px; border-radius: 8px; backdrop-filter: blur(10px);">
								<div style="font-size: 24px; margin-bottom: 4px;">🔄</div>
								<div style="font-size: 12px; opacity: 0.9; margin-bottom: 2px;"><?php esc_html_e( 'Distribuição', 'person-cash-wallet' ); ?></div>
								<div style="font-size: 11px; opacity: 0.75;"><?php esc_html_e( 'Round Robin, Aleatório ou %', 'person-cash-wallet' ); ?></div>
							</div>
							<div style="background: rgba(255, 255, 255, 0.15); padding: 12px; border-radius: 8px; backdrop-filter: blur(10px);">
								<div style="font-size: 24px; margin-bottom: 4px;">📊</div>
								<div style="font-size: 12px; opacity: 0.9; margin-bottom: 2px;"><?php esc_html_e( 'Monitor', 'person-cash-wallet' ); ?></div>
								<div style="font-size: 11px; opacity: 0.75;"><?php esc_html_e( 'Fila em tempo real', 'person-cash-wallet' ); ?></div>
							</div>
						</div>

						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-queue' ) ); ?>" 
							class="button button-primary button-hero" 
							style="background: #fff; border-color: #fff; color: #667eea; font-weight: 600; box-shadow: 0 2px 8px rgba(0,0,0,0.15); text-shadow: none;">
							<span class="dashicons dashicons-admin-settings" style="margin-top: 4px;"></span>
							<?php esc_html_e( 'Configurar Múltiplos Números & Filas', 'person-cash-wallet' ); ?>
						</a>
						
						<p style="margin: 12px 0 0; font-size: 12px; opacity: 0.85;">
							<strong><?php esc_html_e( 'Importante:', 'person-cash-wallet' ); ?></strong>
							<?php esc_html_e( 'O número padrão acima é usado apenas quando nenhum número múltiplo está configurado. Configure múltiplos números para evitar bloqueios!', 'person-cash-wallet' ); ?>
						</p>
					</div>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="personizi_token">
									<?php esc_html_e( 'API Token', 'person-cash-wallet' ); ?>
									<span style="color: #dc3232;">*</span>
								</label>
							</th>
							<td>
								<input type="text" 
									id="personizi_token" 
									name="personizi_token" 
									value="<?php echo esc_attr( $api_token ); ?>" 
									class="regular-text" 
									required>
								<p class="description">
									<?php esc_html_e( 'Token de autenticação da API Personizi. Gere um token em Configurações → API & Tokens no painel do Personizi.', 'person-cash-wallet' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="personizi_default_from">
									<?php esc_html_e( 'Número WhatsApp Padrão', 'person-cash-wallet' ); ?>
									<span style="color: #dc3232;">*</span>
								</label>
							</th>
							<td>
								<?php if ( ! empty( $accounts ) ) : ?>
									<select id="personizi_default_from" name="personizi_default_from" class="regular-text" required>
										<option value=""><?php esc_html_e( 'Selecione uma conta', 'person-cash-wallet' ); ?></option>
										<?php foreach ( $accounts as $account ) : ?>
											<option value="<?php echo esc_attr( $account['phone_number'] ); ?>" 
												<?php selected( $default_from, $account['phone_number'] ); ?>>
												<?php echo esc_html( $account['name'] . ' (' . $account['phone_number'] . ')' ); ?>
												<?php if ( $account['status'] !== 'active' ) : ?>
													[<?php echo esc_html( strtoupper( $account['status'] ) ); ?>]
												<?php endif; ?>
											</option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									<input type="text" 
										id="personizi_default_from" 
										name="personizi_default_from" 
										value="<?php echo esc_attr( $default_from ); ?>" 
										class="regular-text" 
										placeholder="5535991970289"
										pattern="[0-9]{11,15}"
										required>
								<?php endif; ?>
								<p class="description">
									<?php esc_html_e( 'Número WhatsApp que será usado como remetente padrão nas automações. Apenas números.', 'person-cash-wallet' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<!-- Contas WhatsApp Disponíveis -->
					<?php if ( ! empty( $accounts ) ) : ?>
						<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
							<h3>
								<span class="dashicons dashicons-admin-comments"></span>
								<?php esc_html_e( 'Contas WhatsApp Disponíveis', 'person-cash-wallet' ); ?>
							</h3>
							<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Nome', 'person-cash-wallet' ); ?></th>
										<th><?php esc_html_e( 'Número', 'person-cash-wallet' ); ?></th>
										<th><?php esc_html_e( 'Tipo', 'person-cash-wallet' ); ?></th>
										<th><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $accounts as $account ) :
										$acct_provider = isset( $account['provider'] ) ? $account['provider'] : 'evolution';
										$is_official = PCW_Personizi_Integration::is_official_api( $acct_provider );
									?>
										<tr>
											<td><strong><?php echo esc_html( $account['name'] ); ?></strong></td>
											<td><?php echo esc_html( $account['phone_number'] ); ?></td>
											<td>
												<?php if ( $is_official ) : ?>
													<span style="display: inline-flex; align-items: center; gap: 4px; background: #dbeafe; color: #1d4ed8; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 500;">
														<span class="dashicons dashicons-shield" style="font-size: 14px; width: 14px; height: 14px;"></span>
														API Oficial
													</span>
												<?php else : ?>
													<span style="display: inline-flex; align-items: center; gap: 4px; background: #e2e8f0; color: #64748b; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 500;">
														WhatsApp Web
													</span>
												<?php endif; ?>
											</td>
											<td>
												<?php if ( $account['status'] === 'active' ) : ?>
													<span style="color: #46b450;">
														<span class="dashicons dashicons-yes-alt"></span>
														<?php esc_html_e( 'Ativo', 'person-cash-wallet' ); ?>
													</span>
												<?php else : ?>
													<span style="color: #dc3232;">
														<span class="dashicons dashicons-warning"></span>
														<?php echo esc_html( ucfirst( $account['status'] ) ); ?>
													</span>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>

					<!-- Limites por Tipo de Conta -->
					<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
						<h3>
							<span class="dashicons dashicons-performance"></span>
							<?php esc_html_e( 'Limites Padrão por Tipo de Conta', 'person-cash-wallet' ); ?>
						</h3>
						<p class="description" style="margin-bottom: 15px;">
							<?php esc_html_e( 'Estes valores são usados como padrão ao adicionar novos números. Contas API Oficial suportam volumes muito maiores.', 'person-cash-wallet' ); ?>
						</p>

						<?php
						$limits_evolution   = get_option( 'pcw_default_limits_evolution', array( 'rate_limit_hour' => 60, 'min_interval_seconds' => 30 ) );
						$limits_notificame  = get_option( 'pcw_default_limits_notificame', array( 'rate_limit_hour' => 500, 'min_interval_seconds' => 2 ) );
						?>
						<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
							<div style="padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc;">
								<h4 style="margin: 0 0 12px; display: flex; align-items: center; gap: 6px;">
									<span style="background: #e2e8f0; color: #64748b; padding: 2px 8px; border-radius: 10px; font-size: 11px;">WhatsApp Web</span>
									Evolution
								</h4>
								<div style="display: flex; gap: 12px;">
									<div style="flex: 1;">
										<label style="font-size: 12px; font-weight: 500;"><?php esc_html_e( 'Msgs/Hora', 'person-cash-wallet' ); ?></label>
										<input type="number" name="limits_evolution_rate" class="widefat"
											value="<?php echo esc_attr( $limits_evolution['rate_limit_hour'] ); ?>" min="1" max="1000">
									</div>
									<div style="flex: 1;">
										<label style="font-size: 12px; font-weight: 500;"><?php esc_html_e( 'Intervalo (seg)', 'person-cash-wallet' ); ?></label>
										<input type="number" name="limits_evolution_interval" class="widefat"
											value="<?php echo esc_attr( $limits_evolution['min_interval_seconds'] ); ?>" min="0" max="3600">
									</div>
								</div>
							</div>

							<div style="padding: 15px; border: 1px solid #bfdbfe; border-radius: 8px; background: #eff6ff;">
								<h4 style="margin: 0 0 12px; display: flex; align-items: center; gap: 6px;">
									<span style="background: #dbeafe; color: #1d4ed8; padding: 2px 8px; border-radius: 10px; font-size: 11px;">API Oficial</span>
									Notificame / Meta Cloud
								</h4>
								<div style="display: flex; gap: 12px;">
									<div style="flex: 1;">
										<label style="font-size: 12px; font-weight: 500;"><?php esc_html_e( 'Msgs/Hora', 'person-cash-wallet' ); ?></label>
										<input type="number" name="limits_notificame_rate" class="widefat"
											value="<?php echo esc_attr( $limits_notificame['rate_limit_hour'] ); ?>" min="1" max="10000">
									</div>
									<div style="flex: 1;">
										<label style="font-size: 12px; font-weight: 500;"><?php esc_html_e( 'Intervalo (seg)', 'person-cash-wallet' ); ?></label>
										<input type="number" name="limits_notificame_interval" class="widefat"
											value="<?php echo esc_attr( $limits_notificame['min_interval_seconds'] ); ?>" min="0" max="3600">
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Documentação -->
					<div style="margin-top: 30px; padding: 15px; background: #f8fafc; border-left: 4px solid #667eea; border-radius: 4px;">
						<h4 style="margin-top: 0;">
							<span class="dashicons dashicons-book"></span>
							<?php esc_html_e( 'Como usar nas Automações', 'person-cash-wallet' ); ?>
						</h4>
						<ol style="margin: 0;">
							<li><?php esc_html_e( 'Configure o Token e o Número Padrão acima', 'person-cash-wallet' ); ?></li>
							<li><?php esc_html_e( 'Ao criar/editar uma Automação, adicione a etapa "Enviar WhatsApp"', 'person-cash-wallet' ); ?></li>
							<li><?php esc_html_e( 'A mensagem será enviada automaticamente via Personizi quando a automação executar', 'person-cash-wallet' ); ?></li>
							<li><?php esc_html_e( 'Você pode usar variáveis como {{customer_first_name}} na mensagem', 'person-cash-wallet' ); ?></li>
						</ol>
					</div>
				</div>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Salvar Configurações', 'person-cash-wallet' ); ?>
				</button>
				<button type="button" id="test-personizi-connection" class="button">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Testar Conexão', 'person-cash-wallet' ); ?>
				</button>
				<button type="button" id="debug-personizi" class="button">
					<span class="dashicons dashicons-admin-tools"></span>
					<?php esc_html_e( 'Debug Completo', 'person-cash-wallet' ); ?>
				</button>
			</p>
		</form>

		<!-- Teste de Envio Real -->
		<div class="pcw-card" style="max-width: 800px; margin-top: 20px;">
			<div class="pcw-card-header" style="background: #f8fafc; padding: 15px 20px; border-bottom: 1px solid #e2e8f0;">
				<h2 style="margin: 0; font-size: 16px; color: #1e293b;">
					<span class="dashicons dashicons-admin-comments"></span>
					<?php esc_html_e( 'Testar Envio de Mensagem', 'person-cash-wallet' ); ?>
				</h2>
			</div>
			<div class="pcw-card-body" style="padding: 20px;">
				<p class="description" style="margin: 0 0 20px;">
					<?php esc_html_e( 'Envie uma mensagem de teste real para verificar se tudo está funcionando corretamente.', 'person-cash-wallet' ); ?>
				</p>

				<div style="margin-bottom: 15px;">
					<label for="test_from" style="display: block; margin-bottom: 5px; font-weight: 600;">
						<?php esc_html_e( 'Remetente (De):', 'person-cash-wallet' ); ?>
					</label>
					<input type="text" id="test_from" class="regular-text" 
						value="5511916127354" 
						readonly style="background: #f1f5f9;">
					<p class="description"><?php esc_html_e( 'Número padrão configurado', 'person-cash-wallet' ); ?></p>
				</div>

				<div style="margin-bottom: 15px;">
					<label for="test_to" style="display: block; margin-bottom: 5px; font-weight: 600;">
						<?php esc_html_e( 'Destinatário (Para):', 'person-cash-wallet' ); ?>
						<span style="color: #dc2626;">*</span>
					</label>
					<input type="tel" id="test_to" class="regular-text" 
						placeholder="5511999998888" 
						pattern="[0-9]{11,15}">
					<p class="description"><?php esc_html_e( 'Número com código do país e DDD (apenas números, 11-15 dígitos)', 'person-cash-wallet' ); ?></p>
				</div>

				<div style="margin-bottom: 15px;">
					<label for="test_message" style="display: block; margin-bottom: 5px; font-weight: 600;">
						<?php esc_html_e( 'Mensagem de Teste:', 'person-cash-wallet' ); ?>
						<span style="color: #dc2626;">*</span>
					</label>
					<textarea id="test_message" class="large-text" rows="4" 
						placeholder="<?php esc_attr_e( 'Olá! Esta é uma mensagem de teste do sistema Person Cash Wallet 🚀', 'person-cash-wallet' ); ?>"></textarea>
					<p class="description"><?php esc_html_e( 'Mensagem que será enviada para o destinatário', 'person-cash-wallet' ); ?></p>
				</div>

				<div>
					<button type="button" id="send-test-message" class="button button-primary button-large">
						<span class="dashicons dashicons-admin-comments"></span>
						<?php esc_html_e( 'Enviar Mensagem de Teste', 'person-cash-wallet' ); ?>
					</button>
				</div>

				<div id="test-message-result" style="margin-top: 20px;"></div>
			</div>
		</div>
		
		<div id="personizi-debug-results" style="margin-top: 20px; display: none;"></div>

		<script>
		jQuery(document).ready(function($) {
			// Teste de conexão
			$('#test-personizi-connection').on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var originalText = $btn.html();
				
				$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spinner is-active"></span> Testando...');
				
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'pcw_test_personizi_connection',
						nonce: '<?php echo esc_js( wp_create_nonce( 'pcw_personizi' ) ); ?>',
						token: $('#personizi_token').val(),
						from: $('#personizi_default_from').val()
					},
					success: function(response) {
						if (response.success) {
							alert('✅ Conexão bem-sucedida!\n\n' + response.data.message);
							if (response.data.accounts && response.data.accounts.length > 0) {
								alert('Contas WhatsApp encontradas: ' + response.data.accounts.length + '\n\nRecarregue a página para ver a lista atualizada.');
							}
						} else {
							alert('❌ Erro na conexão:\n\n' + response.data.message);
						}
					},
					error: function() {
						alert('❌ Erro ao testar conexão. Tente novamente.');
					},
					complete: function() {
						$btn.prop('disabled', false).html(originalText);
					}
				});
			});

			// Debug completo
			$('#debug-personizi').on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $results = $('#personizi-debug-results');
				var originalText = $btn.html();
				
				$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spinner is-active"></span> Analisando...');
				$results.html('<div class="notice notice-info inline"><p>Executando diagnóstico completo...</p></div>').show();
				
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'pcw_debug_personizi',
						nonce: '<?php echo esc_js( wp_create_nonce( 'pcw_personizi' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							var debug = response.data;
							var html = '<div class="pcw-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
							html += '<h2 style="margin-top: 0;">🔍 Diagnóstico do Personizi</h2>';
							
							// Token
							html += '<h3>1️⃣ Token de Autenticação</h3>';
							if (debug.token.exists) {
								html += '<p>✅ Token configurado (' + debug.token.length + ' caracteres)</p>';
								html += '<p><code>' + debug.token.preview + '</code></p>';
							} else {
								html += '<p>❌ Token não configurado!</p>';
							}
							
							// Número padrão
							html += '<h3>2️⃣ Número WhatsApp Padrão</h3>';
							html += '<p><strong>Valor:</strong> ' + debug.default_from.value + '</p>';
							if (debug.default_from.valid) {
								html += '<p>✅ Formato válido</p>';
							} else {
								html += '<p>❌ Formato inválido! Deve conter apenas números (11-15 dígitos)</p>';
							}
							
							// Conectividade
							html += '<h3>3️⃣ Conectividade API</h3>';
							if (debug.connectivity.success) {
								html += '<p>✅ Conexão estabelecida com sucesso!</p>';
								html += '<p><strong>Status HTTP:</strong> ' + debug.connectivity.status_code + '</p>';
							} else {
								html += '<p>❌ Falha na conexão!</p>';
								html += '<p><strong>Status HTTP:</strong> ' + debug.connectivity.status_code + '</p>';
								if (debug.connectivity.error) {
									html += '<p><strong>Erro:</strong> ' + debug.connectivity.error + '</p>';
									html += '<p><strong>Código:</strong> ' + debug.connectivity.error_code + '</p>';
								}
								html += '<div style="background: #f1f5f9; padding: 10px; border-radius: 4px; margin: 10px 0;">';
								html += '<p><strong>Resposta da API:</strong></p>';
								html += '<pre style="font-size: 11px; overflow: auto;">' + JSON.stringify(debug.connectivity.full_response, null, 2) + '</pre>';
								html += '</div>';
							}
							
							// Contas
							html += '<h3>4️⃣ Contas WhatsApp</h3>';
							if (debug.accounts.success) {
								html += '<p>✅ ' + debug.accounts.count + ' conta(s) encontrada(s):</p>';
								html += '<ul>';
								debug.accounts.accounts.forEach(function(acc) {
									html += '<li><strong>' + acc.name + '</strong> (' + acc.phone_number + ') - Status: ' + acc.status + '</li>';
								});
								html += '</ul>';
							} else {
								html += '<p>❌ Erro ao buscar contas</p>';
								html += '<p><strong>Mensagem:</strong> ' + debug.accounts.error + '</p>';
								if (debug.accounts.error_data) {
									html += '<div style="background: #f1f5f9; padding: 10px; border-radius: 4px; margin: 10px 0;">';
									html += '<pre style="font-size: 11px; overflow: auto;">' + JSON.stringify(debug.accounts.error_data, null, 2) + '</pre>';
									html += '</div>';
								}
							}
							
							// Info WordPress
							html += '<h3>5️⃣ Informações do Servidor</h3>';
							html += '<ul>';
							html += '<li><strong>WordPress:</strong> ' + debug.wordpress.wp_version + '</li>';
							html += '<li><strong>PHP:</strong> ' + debug.wordpress.php_version + '</li>';
							html += '<li><strong>WP_DEBUG:</strong> ' + (debug.wordpress.wp_debug ? 'Ativado ✅' : 'Desativado ❌') + '</li>';
							html += '<li><strong>cURL:</strong> ' + (debug.wordpress.curl_enabled ? 'Disponível ✅' : 'Não disponível ❌') + '</li>';
							html += '<li><strong>OpenSSL:</strong> ' + (debug.wordpress.openssl_enabled ? 'Disponível ✅' : 'Não disponível ❌') + '</li>';
							html += '</ul>';
							
							// Recomendações
							html += '<h3>💡 Recomendações</h3>';
							html += '<ul>';
							if (!debug.token.exists) {
								html += '<li>❗ Configure o Token na API do Personizi</li>';
							}
							if (!debug.default_from.valid) {
								html += '<li>❗ Corrija o formato do número WhatsApp padrão</li>';
							}
							if (!debug.connectivity.success) {
								html += '<li>❗ Verifique se o token está correto no Personizi</li>';
								html += '<li>❗ Verifique se o servidor tem acesso à URL: https://chat.personizi.com.br</li>';
							}
							if (!debug.wordpress.wp_debug) {
								html += '<li>💡 Ative WP_DEBUG para ver logs detalhados no arquivo wp-content/debug.log</li>';
							}
							html += '</ul>';
							
							html += '</div>';
							$results.html(html);
						} else {
							$results.html('<div class="notice notice-error inline"><p>❌ Erro ao executar debug: ' + response.data.message + '</p></div>');
						}
					},
					error: function() {
						$results.html('<div class="notice notice-error inline"><p>❌ Erro ao conectar com o servidor.</p></div>');
					},
					complete: function() {
						$btn.prop('disabled', false).html(originalText);
					}
				});
			});

			// Enviar mensagem de teste
			$('#send-test-message').on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $result = $('#test-message-result');
				var originalText = $btn.html();
				
				var testTo = $('#test_to').val().trim();
				var testMessage = $('#test_message').val().trim();
				
				// Validações
				if (!testTo) {
					$result.html('<div class="notice notice-error inline"><p>❌ Por favor, informe o número do destinatário.</p></div>');
					$('#test_to').focus();
					return;
				}
				
				if (!/^[0-9]{11,15}$/.test(testTo)) {
					$result.html('<div class="notice notice-error inline"><p>❌ Número inválido. Use apenas números (11-15 dígitos).<br>Exemplo: 5511999998888</p></div>');
					$('#test_to').focus();
					return;
				}
				
				if (!testMessage) {
					$result.html('<div class="notice notice-error inline"><p>❌ Por favor, escreva uma mensagem de teste.</p></div>');
					$('#test_message').focus();
					return;
				}
				
				$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Enviando mensagem...');
				$result.html('<div class="notice notice-info inline"><p>📤 Enviando mensagem WhatsApp para ' + testTo + '...</p></div>');
				
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'pcw_send_test_personizi_message',
						nonce: '<?php echo esc_js( wp_create_nonce( 'pcw_personizi' ) ); ?>',
						to: testTo,
						message: testMessage
					},
					success: function(response) {
						if (response.success) {
							var html = '<div class="notice notice-success inline">';
							html += '<p><strong>✅ Mensagem enviada com sucesso!</strong></p>';
							if (response.data.message_id) {
								html += '<p style="font-size: 13px; color: #666; margin-top: 8px;">';
								html += '<strong>ID da Mensagem:</strong> ' + response.data.message_id + '<br>';
								html += '<strong>ID da Conversa:</strong> ' + response.data.conversation_id + '<br>';
								html += '<strong>Status:</strong> ' + response.data.status + '<br>';
								html += '<strong>Destinatário:</strong> ' + testTo;
								html += '</p>';
							}
							html += '<p style="margin-top: 10px; padding: 10px; background: #f0fdf4; border-radius: 4px; border-left: 3px solid #22c55e;">';
							html += '<strong>💬 Mensagem enviada:</strong><br>"' + testMessage + '"';
							html += '</p>';
							html += '</div>';
							$result.html(html);
							
							// Limpar campos após sucesso
							$('#test_to').val('');
							$('#test_message').val('');
						} else {
							var html = '<div class="notice notice-error inline">';
							html += '<p><strong>❌ Erro ao enviar mensagem</strong></p>';
							html += '<p>' + (response.data.message || 'Erro desconhecido') + '</p>';
							if (response.data.details) {
								html += '<div style="background: #fef2f2; padding: 10px; border-radius: 4px; margin-top: 10px;">';
								html += '<pre style="font-size: 11px; margin: 0; white-space: pre-wrap;">' + JSON.stringify(response.data.details, null, 2) + '</pre>';
								html += '</div>';
							}
							html += '</div>';
							$result.html(html);
						}
					},
					error: function() {
						$result.html('<div class="notice notice-error inline"><p>❌ Erro ao conectar com o servidor. Tente novamente.</p></div>');
					},
					complete: function() {
						$btn.prop('disabled', false).html(originalText);
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Salvar configurações do Personizi
	 */
	public function handle_save_personizi_settings() {
		check_admin_referer( 'pcw_save_personizi_settings' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Sem permissão', 'person-cash-wallet' ) );
		}

		$token = isset( $_POST['personizi_token'] ) ? sanitize_text_field( $_POST['personizi_token'] ) : '';
		$from  = isset( $_POST['personizi_default_from'] ) ? sanitize_text_field( $_POST['personizi_default_from'] ) : '';

		// Validar
		if ( empty( $token ) ) {
			wp_safe_redirect( add_query_arg( array(
				'page'   => 'pcw-settings',
				'tab'    => 'personizi',
				'status' => 'error',
				'message' => urlencode( __( 'Token é obrigatório', 'person-cash-wallet' ) ),
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( empty( $from ) || ! preg_match( '/^[0-9]{11,15}$/', $from ) ) {
			wp_safe_redirect( add_query_arg( array(
				'page'   => 'pcw-settings',
				'tab'    => 'personizi',
				'status' => 'error',
				'message' => urlencode( __( 'Número WhatsApp inválido', 'person-cash-wallet' ) ),
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Salvar
		$personizi = PCW_Personizi_Integration::instance();
		$personizi->update_api_token( $token );
		$personizi->update_default_from( $from );

		// Salvar limites por tipo
		if ( isset( $_POST['limits_evolution_rate'] ) ) {
			update_option( 'pcw_default_limits_evolution', array(
				'rate_limit_hour'      => absint( $_POST['limits_evolution_rate'] ),
				'min_interval_seconds' => absint( $_POST['limits_evolution_interval'] ?? 30 ),
			) );
		}
		if ( isset( $_POST['limits_notificame_rate'] ) ) {
			update_option( 'pcw_default_limits_notificame', array(
				'rate_limit_hour'      => absint( $_POST['limits_notificame_rate'] ),
				'min_interval_seconds' => absint( $_POST['limits_notificame_interval'] ?? 2 ),
			) );
		}

		// Testar conexão
		$test = $personizi->test_connection();
		if ( is_wp_error( $test ) ) {
			wp_safe_redirect( add_query_arg( array(
				'page'   => 'pcw-settings',
				'tab'    => 'personizi',
				'status' => 'warning',
				'message' => urlencode( __( 'Configurações salvas, mas falha ao testar conexão: ', 'person-cash-wallet' ) . $test->get_error_message() ),
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( array(
			'page'   => 'pcw-settings',
			'tab'    => 'personizi',
			'status' => 'success',
			'message' => urlencode( __( 'Configurações salvas com sucesso!', 'person-cash-wallet' ) ),
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * AJAX: Testar conexão Personizi
	 */
	public function ajax_test_personizi_connection() {
		check_ajax_referer( 'pcw_personizi', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$token = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';

		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Token é obrigatório', 'person-cash-wallet' ) ) );
		}

		// Criar instância temporária
		$personizi = PCW_Personizi_Integration::instance();
		$personizi->update_api_token( $token );

		$test = $personizi->test_connection();

		if ( is_wp_error( $test ) ) {
			wp_send_json_error( array( 'message' => $test->get_error_message() ) );
		}

		$accounts = $personizi->get_whatsapp_accounts( true );

		wp_send_json_success( array(
			'message'  => __( 'Conexão estabelecida com sucesso!', 'person-cash-wallet' ),
			'accounts' => is_array( $accounts ) ? $accounts : array(),
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
		$accounts = $personizi->get_whatsapp_accounts( true );

		if ( is_wp_error( $accounts ) ) {
			wp_send_json_error( array( 'message' => $accounts->get_error_message() ) );
		}

		wp_send_json_success( array( 'accounts' => $accounts ) );
	}

	/**
	 * AJAX: Debug completo do Personizi
	 */
	public function ajax_debug_personizi() {
		check_ajax_referer( 'pcw_personizi', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$personizi = PCW_Personizi_Integration::instance();
		$debug_info = array();

		// 1. Verificar token
		$token = $personizi->get_api_token();
		$token_from_option = get_option( 'pcw_personizi_token', '' );
		$debug_info['token'] = array(
			'exists' => ! empty( $token ),
			'length' => strlen( $token ),
			'preview' => substr( $token, 0, 20 ) . '...' . substr( $token, -10 ),
			'full_token' => $token, // Para copiar/colar se necessário
			'from_option' => $token_from_option,
			'tokens_match' => ( $token === $token_from_option ),
		);

		// 2. Verificar número padrão
		$from = $personizi->get_default_from();
		$debug_info['default_from'] = array(
			'value' => $from,
			'valid' => preg_match( '/^[0-9]{11,15}$/', $from ) ? true : false,
		);

		// 3. Testar conectividade básica COM DETALHES
		// ✅ URL CORRETA: api.php/whatsapp-accounts?status=active
		$test_url = 'https://chat.personizi.com.br/api.php/whatsapp-accounts?status=active';
		
		// Armazenar detalhes da requisição
		$debug_info['request'] = array(
			'url' => $test_url,
			'method' => 'GET',
			'headers' => array(
				'Authorization' => 'Bearer ' . substr( $token, 0, 30 ) . '...',
				'Content-Type' => 'application/json',
			),
		);

		$response = wp_remote_get( $test_url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
			),
			'timeout' => 15,
			'sslverify' => true,
		) );

		if ( is_wp_error( $response ) ) {
			$debug_info['connectivity'] = array(
				'success' => false,
				'error_type' => 'wp_error',
				'error' => $response->get_error_message(),
				'error_code' => $response->get_error_code(),
			);
		} else {
			$status = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$headers = wp_remote_retrieve_headers( $response );
			
			$debug_info['connectivity'] = array(
				'success' => $status === 200,
				'status_code' => $status,
				'raw_body' => $body,
				'parsed_response' => json_decode( $body, true ),
				'response_headers' => is_object( $headers ) ? $headers->getAll() : $headers,
			);
		}

		// 4. Tentar buscar contas
		$accounts = $personizi->get_whatsapp_accounts( true );
		
		if ( is_wp_error( $accounts ) ) {
			$debug_info['accounts'] = array(
				'success' => false,
				'error' => $accounts->get_error_message(),
				'error_data' => $accounts->get_error_data(),
			);
		} else {
			$debug_info['accounts'] = array(
				'success' => true,
				'count' => count( $accounts ),
				'accounts' => $accounts,
			);
		}

		// 5. Informações do WordPress
		$log_file = WP_CONTENT_DIR . '/debug.log';
		$debug_info['wordpress'] = array(
			'wp_debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wp_debug_log' => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'wp_version' => get_bloginfo( 'version' ),
			'php_version' => PHP_VERSION,
			'curl_enabled' => function_exists( 'curl_version' ),
			'openssl_enabled' => extension_loaded( 'openssl' ),
			'log_file_path' => $log_file,
			'log_file_exists' => file_exists( $log_file ),
			'log_file_writable' => is_writable( dirname( $log_file ) ),
		);

		wp_send_json_success( $debug_info );
	}

	/**
	 * AJAX: Enviar mensagem de teste via Personizi
	 */
	public function ajax_send_test_personizi_message() {
		check_ajax_referer( 'pcw_personizi', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$to = isset( $_POST['to'] ) ? sanitize_text_field( $_POST['to'] ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';

		// Validações
		if ( empty( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Número de destino é obrigatório', 'person-cash-wallet' ) ) );
		}

		if ( ! preg_match( '/^[0-9]{11,15}$/', $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Número de destino inválido. Use apenas números (11-15 dígitos)', 'person-cash-wallet' ) ) );
		}

		if ( empty( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'Mensagem é obrigatória', 'person-cash-wallet' ) ) );
		}

		// Enviar mensagem
		$personizi = PCW_Personizi_Integration::instance();
		$result = $personizi->send_whatsapp_message( $to, $message, 'Teste do Sistema' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
				'details' => $result->get_error_data(),
			) );
		}

		// Extrair dados da resposta
		$message_id = isset( $result['data']['message_id'] ) ? $result['data']['message_id'] : null;
		$conversation_id = isset( $result['data']['conversation_id'] ) ? $result['data']['conversation_id'] : null;
		$status = isset( $result['data']['status'] ) ? $result['data']['status'] : 'unknown';

		wp_send_json_success( array(
			'message_id' => $message_id,
			'conversation_id' => $conversation_id,
			'status' => $status,
			'to' => $to,
			'full_response' => $result,
		) );
	}

	/**
	 * Renderizar aba SendPulse
	 */
	private function render_sendpulse_tab() {
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_sendpulse_accounts';
		$accounts = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" );
		?>
		<div class="pcw-settings-section">
			<div class="pcw-card">
				<div class="pcw-card-header" style="display: flex; justify-content: space-between; align-items: center;">
					<div>
						<h2>
							<span class="dashicons dashicons-email" style="color: #00b8d4;"></span>
							<?php esc_html_e( 'Contas SendPulse', 'person-cash-wallet' ); ?>
						</h2>
						<p class="description">
							<?php esc_html_e( 'Gerencie múltiplas contas SendPulse para distribuição de envios.', 'person-cash-wallet' ); ?>
						</p>
					</div>
					<button type="button" id="add-sendpulse-account-btn" class="button button-primary">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Adicionar Conta', 'person-cash-wallet' ); ?>
					</button>
				</div>

					<div class="pcw-card-body">
						<?php if ( empty( $accounts ) ) : ?>
							<div class="pcw-empty-state">
								<div class="pcw-empty-icon">
									<span class="dashicons dashicons-email"></span>
								</div>
								<h3><?php esc_html_e( 'Nenhuma conta SendPulse configurada', 'person-cash-wallet' ); ?></h3>
								<p><?php esc_html_e( 'Adicione uma ou mais contas SendPulse para enviar emails com rate limiting e distribuição automática.', 'person-cash-wallet' ); ?></p>
							</div>
						<?php else : ?>
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Nome', 'person-cash-wallet' ); ?></th>
										<th><?php esc_html_e( 'Email Remetente', 'person-cash-wallet' ); ?></th>
										<th><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></th>
										<th><?php esc_html_e( 'Enviados (Hoje)', 'person-cash-wallet' ); ?></th>
										<th><?php esc_html_e( 'Total', 'person-cash-wallet' ); ?></th>
										<th><?php esc_html_e( 'Ações', 'person-cash-wallet' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $accounts as $account ) : ?>
										<tr>
											<td><strong><?php echo esc_html( $account->name ); ?></strong></td>
											<td><?php echo esc_html( $account->from_email ); ?></td>
											<td>
												<?php if ( $account->status === 'active' ) : ?>
													<span class="pcw-badge pcw-badge-success"><?php esc_html_e( 'Ativa', 'person-cash-wallet' ); ?></span>
												<?php else : ?>
													<span class="pcw-badge pcw-badge-inactive"><?php esc_html_e( 'Inativa', 'person-cash-wallet' ); ?></span>
												<?php endif; ?>
											</td>
											<td><?php echo esc_html( number_format_i18n( $account->sent_today ) ); ?></td>
											<td><?php echo esc_html( number_format_i18n( $account->total_sent ) ); ?></td>
											<td>
												<button type="button" class="button button-small edit-sendpulse-account" data-id="<?php echo esc_attr( $account->id ); ?>">
													<span class="dashicons dashicons-edit"></span>
													<?php esc_html_e( 'Editar', 'person-cash-wallet' ); ?>
												</button>
												<button type="button" class="button button-small button-link-delete delete-sendpulse-account" data-id="<?php echo esc_attr( $account->id ); ?>">
													<span class="dashicons dashicons-trash"></span>
													<?php esc_html_e( 'Excluir', 'person-cash-wallet' ); ?>
												</button>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>

						<!-- Instruções -->
						<div class="pcw-info-box" style="margin-top: 24px;">
							<h3>
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'Como obter suas credenciais', 'person-cash-wallet' ); ?>
							</h3>
							<ol>
								<li><?php esc_html_e( 'Acesse', 'person-cash-wallet' ); ?> <a href="https://login.sendpulse.com/" target="_blank">SendPulse.com</a></li>
								<li><?php esc_html_e( 'Vá em Configurações → API', 'person-cash-wallet' ); ?></li>
								<li><?php esc_html_e( 'Copie o "ID" e "Secret"', 'person-cash-wallet' ); ?></li>
								<li><?php esc_html_e( 'Clique em "Adicionar Conta" acima', 'person-cash-wallet' ); ?></li>
							</ol>
						</div>
					</div>
				</div>
			</div>

			<!-- Modal para adicionar/editar conta -->
			<div id="sendpulse-account-modal" class="pcw-modal" style="display: none;">
				<div class="pcw-modal-content">
					<div class="pcw-modal-header">
						<h2 id="sendpulse-modal-title"><?php esc_html_e( 'Adicionar Conta SendPulse', 'person-cash-wallet' ); ?></h2>
						<button type="button" class="pcw-modal-close">&times;</button>
					</div>
					<form id="sendpulse-account-form">
						<input type="hidden" id="sendpulse_account_id" name="account_id" value="">
						
						<div class="pcw-form-group">
							<label for="sendpulse_account_name">
								<?php esc_html_e( 'Nome da Conta', 'person-cash-wallet' ); ?>
								<span class="required">*</span>
							</label>
							<input type="text" id="sendpulse_account_name" name="account_name" class="regular-text" required>
						</div>

						<div class="pcw-form-group">
							<label for="sendpulse_account_client_id">
								<?php esc_html_e( 'Client ID', 'person-cash-wallet' ); ?>
								<span class="required">*</span>
							</label>
							<input type="text" id="sendpulse_account_client_id" name="client_id" class="regular-text" required>
						</div>

						<div class="pcw-form-group">
							<label for="sendpulse_account_client_secret">
								<?php esc_html_e( 'Client Secret', 'person-cash-wallet' ); ?>
								<span class="required">*</span>
							</label>
							<input type="password" id="sendpulse_account_client_secret" name="client_secret" class="regular-text" required>
						</div>

						<div class="pcw-form-group">
							<label for="sendpulse_account_from_email">
								<?php esc_html_e( 'Email Remetente', 'person-cash-wallet' ); ?>
								<span class="required">*</span>
							</label>
							<input type="email" id="sendpulse_account_from_email" name="from_email" class="regular-text" required>
						</div>

						<div class="pcw-form-group">
							<label for="sendpulse_account_from_name">
								<?php esc_html_e( 'Nome Remetente', 'person-cash-wallet' ); ?>
								<span class="required">*</span>
							</label>
							<input type="text" id="sendpulse_account_from_name" name="from_name" class="regular-text" required>
						</div>

						<div class="pcw-modal-footer">
							<div style="flex: 1;">
								<button type="button" id="test-sendpulse-account-btn" class="button">
									<span class="dashicons dashicons-update"></span>
									<?php esc_html_e( 'Testar Conexão', 'person-cash-wallet' ); ?>
								</button>
								<span id="test-sendpulse-result" style="margin-left: 10px;"></span>
							</div>
							<div>
								<button type="button" class="button pcw-modal-close"><?php esc_html_e( 'Cancelar', 'person-cash-wallet' ); ?></button>
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Salvar', 'person-cash-wallet' ); ?></button>
							</div>
						</div>
					</form>
				</div>
			</div>

			<script type="text/javascript">
			jQuery(document).ready(function($) {
				console.log('SendPulse accounts script loaded');
				
				var modal = $('#sendpulse-account-modal');
				var form = $('#sendpulse-account-form');
				
				console.log('Modal element:', modal.length);
				console.log('Form element:', form.length);
				console.log('Button element:', $('#add-sendpulse-account-btn').length);

				// Abrir modal para adicionar
				$('#add-sendpulse-account-btn').on('click', function(e) {
					e.preventDefault();
					console.log('Add account button clicked');
					
					$('#sendpulse-modal-title').text('<?php echo esc_js( __( 'Adicionar Conta SendPulse', 'person-cash-wallet' ) ); ?>');
					form[0].reset();
					$('#sendpulse_account_id').val('');
					modal.css('display', 'block').fadeIn();
					
					console.log('Modal should be visible now');
				});

				// Fechar modal
				$('.pcw-modal-close, .pcw-modal').on('click', function(e) {
					if (e.target === this) {
						modal.fadeOut();
					}
				});
				
				// Prevenir fechar ao clicar dentro do conteúdo
				$('.pcw-modal-content').on('click', function(e) {
					e.stopPropagation();
				});

				// Testar conexão no modal
				$('#test-sendpulse-account-btn').on('click', function(e) {
					e.preventDefault();
					
					var btn = $(this);
					var resultSpan = $('#test-sendpulse-result');
					var originalText = btn.html();
					
					var clientId = $('#sendpulse_account_client_id').val();
					var clientSecret = $('#sendpulse_account_client_secret').val();
					
					if (!clientId || !clientSecret) {
						resultSpan.html('<span style="color: #dc3545;">❌ Preencha Client ID e Secret</span>');
						return;
					}
					
					btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Testando...');
					resultSpan.html('');
					
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'pcw_test_sendpulse_connection',
							nonce: '<?php echo esc_js( wp_create_nonce( 'pcw_test_sendpulse' ) ); ?>',
							client_id: clientId,
							client_secret: clientSecret
						},
						success: function(response) {
							console.log('Test response:', response);
							
							if (response.success) {
								resultSpan.html('<span style="color: #28a745; font-weight: 600;">✅ Conexão OK!</span>');
							} else {
								var message = response.data && response.data.message ? response.data.message : 'Erro ao testar';
								resultSpan.html('<span style="color: #dc3545;">❌ ' + message + '</span>');
							}
							
							btn.prop('disabled', false).html(originalText);
						},
						error: function(xhr, status, error) {
							console.error('AJAX Error:', xhr, status, error);
							resultSpan.html('<span style="color: #dc3545;">❌ Erro de conexão</span>');
							btn.prop('disabled', false).html(originalText);
						}
					});
				});

				// Editar conta
				$(document).on('click', '.edit-sendpulse-account', function(e) {
					e.preventDefault();
					var accountId = $(this).data('id');
					
					console.log('Edit account clicked:', accountId);
					
					$.post(ajaxurl, {
						action: 'pcw_get_sendpulse_account',
						nonce: '<?php echo esc_js( wp_create_nonce( 'pcw_sendpulse_account' ) ); ?>',
						account_id: accountId
					}, function(response) {
						console.log('Edit response:', response);
						
						if (response.success && response.data) {
							$('#sendpulse-modal-title').text('<?php echo esc_js( __( 'Editar Conta SendPulse', 'person-cash-wallet' ) ); ?>');
							$('#sendpulse_account_id').val(response.data.id);
							$('#sendpulse_account_name').val(response.data.name);
							$('#sendpulse_account_client_id').val(response.data.client_id);
							$('#sendpulse_account_client_secret').val(response.data.client_secret);
							$('#sendpulse_account_from_email').val(response.data.from_email);
							$('#sendpulse_account_from_name').val(response.data.from_name);
							modal.css('display', 'block').fadeIn();
						} else {
							alert('Erro ao carregar dados da conta');
						}
					}).fail(function(xhr, status, error) {
						console.error('AJAX error:', xhr, status, error);
						alert('Erro ao carregar dados da conta');
					});
				});

				// Salvar conta
				form.on('submit', function(e) {
					e.preventDefault();
					
					console.log('Form submitted');
					
					var submitBtn = form.find('button[type="submit"]');
					var originalText = submitBtn.html();
					
					submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Salvando...');

					$.post(ajaxurl, {
						action: 'pcw_save_sendpulse_account',
						nonce: '<?php echo esc_js( wp_create_nonce( 'pcw_sendpulse_account' ) ); ?>',
						account_id: $('#sendpulse_account_id').val(),
						name: $('#sendpulse_account_name').val(),
						client_id: $('#sendpulse_account_client_id').val(),
						client_secret: $('#sendpulse_account_client_secret').val(),
						from_email: $('#sendpulse_account_from_email').val(),
						from_name: $('#sendpulse_account_from_name').val()
					}, function(response) {
						console.log('Save response:', response);
						
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || 'Erro ao salvar conta');
							submitBtn.prop('disabled', false).html(originalText);
						}
					}).fail(function(xhr, status, error) {
						console.error('AJAX error:', xhr, status, error);
						alert('Erro ao salvar conta');
						submitBtn.prop('disabled', false).html(originalText);
					});
				});

				// Testar conta inline (na tabela)
				$(document).on('click', '.test-sendpulse-account-inline', function(e) {
					e.preventDefault();
					
					var btn = $(this);
					var originalText = btn.html();
					var clientId = btn.data('client-id');
					var clientSecret = btn.data('client-secret');
					
					btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Testando...');
					
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'pcw_test_sendpulse_connection',
							nonce: '<?php echo esc_js( wp_create_nonce( 'pcw_test_sendpulse' ) ); ?>',
							client_id: clientId,
							client_secret: clientSecret
						},
						success: function(response) {
							console.log('Test response:', response);
							
							if (response.success) {
								btn.html('<span class="dashicons dashicons-yes" style="color: #28a745;"></span> OK');
								setTimeout(function() {
									btn.prop('disabled', false).html(originalText);
								}, 2000);
							} else {
								var message = response.data && response.data.message ? response.data.message : 'Erro';
								btn.html('<span class="dashicons dashicons-no" style="color: #dc3545;"></span> ' + message);
								setTimeout(function() {
									btn.prop('disabled', false).html(originalText);
								}, 3000);
							}
						},
						error: function(xhr, status, error) {
							console.error('AJAX Error:', xhr, status, error);
							btn.html('<span class="dashicons dashicons-no" style="color: #dc3545;"></span> Erro');
							setTimeout(function() {
								btn.prop('disabled', false).html(originalText);
							}, 3000);
						}
					});
				});

				// Testar conta
				$(document).on('click', '.test-sendpulse-account', function(e) {
					e.preventDefault();
					
					var btn = $(this);
					var originalText = btn.html();
					var clientId = btn.data('client-id');
					var clientSecret = btn.data('client-secret');
					
					btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Testando...');
					
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'pcw_test_sendpulse_connection',
							nonce: '<?php echo esc_js( wp_create_nonce( 'pcw_test_sendpulse' ) ); ?>',
							client_id: clientId,
							client_secret: clientSecret
						},
						success: function(response) {
							console.log('Test response:', response);
							
							var message = 'Erro ao testar';
							if (response.success) {
								message = response.data && response.data.message ? response.data.message : 'Conexão OK!';
								alert('✅ ' + message);
							} else {
								message = response.data && response.data.message ? response.data.message : 'Erro na conexão';
								alert('❌ ' + message);
							}
							
							btn.prop('disabled', false).html(originalText);
						},
						error: function(xhr, status, error) {
							console.error('AJAX error:', xhr, status, error);
							alert('❌ Erro de conexão: ' + error);
							btn.prop('disabled', false).html(originalText);
						}
					});
				});

				// Excluir conta
				$(document).on('click', '.delete-sendpulse-account', function(e) {
					e.preventDefault();
					
					if (!confirm('<?php echo esc_js( __( 'Tem certeza que deseja excluir esta conta?', 'person-cash-wallet' ) ); ?>')) {
						return;
					}
					
					var accountId = $(this).data('id');
					
					console.log('Delete account:', accountId);
					
					$.post(ajaxurl, {
						action: 'pcw_delete_sendpulse_account',
						nonce: '<?php echo esc_js( wp_create_nonce( 'pcw_sendpulse_account' ) ); ?>',
						account_id: accountId
					}, function(response) {
						console.log('Delete response:', response);
						
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || 'Erro ao excluir conta');
						}
					}).fail(function(xhr, status, error) {
						console.error('AJAX error:', xhr, status, error);
						alert('Erro ao excluir conta');
					});
				});
				
				console.log('All event handlers registered');
			});
			</script>

			<!-- Vantagens -->
			<div class="pcw-card" style="margin-top: 30px;">
				<div class="pcw-card-header">
					<h3>
						<span class="dashicons dashicons-awards"></span>
						<?php esc_html_e( 'Por que usar SendPulse?', 'person-cash-wallet' ); ?>
					</h3>
				</div>
				<div class="pcw-card-body">
					<ul>
						<li>✅ <strong><?php esc_html_e( 'Envios em massa:', 'person-cash-wallet' ); ?></strong> <?php esc_html_e( 'Milhares de emails por hora', 'person-cash-wallet' ); ?></li>
						<li>✅ <strong><?php esc_html_e( 'Taxa de entrega:', 'person-cash-wallet' ); ?></strong> <?php esc_html_e( 'Alta taxa de inbox', 'person-cash-wallet' ); ?></li>
						<li>✅ <strong><?php esc_html_e( 'Plano gratuito:', 'person-cash-wallet' ); ?></strong> <?php esc_html_e( '15.000 emails/mês grátis', 'person-cash-wallet' ); ?></li>
						<li>✅ <strong><?php esc_html_e( 'Relatórios:', 'person-cash-wallet' ); ?></strong> <?php esc_html_e( 'Acompanhe aberturas, cliques e mais', 'person-cash-wallet' ); ?></li>
					</ul>
				</div>
			</div>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#test-sendpulse-btn').on('click', function(e) {
				e.preventDefault();
				
				var btn = $(this);
				var originalText = btn.html();
				var resultDiv = $('#sendpulse-test-result');
				
				btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Testando...');
				resultDiv.hide();

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'pcw_test_sendpulse_connection',
						nonce: '<?php echo esc_js( wp_create_nonce( 'pcw_test_sendpulse' ) ); ?>',
						client_id: $('#sendpulse_client_id').val(),
						client_secret: $('#sendpulse_client_secret').val()
					},
					success: function(response) {
						console.log('Response:', response);
						
						var cssClass = 'notice-error';
						var message = 'Erro desconhecido';
						
						if (response.success) {
							cssClass = 'notice-success';
							message = response.data && response.data.message ? response.data.message : 'Conexão testada com sucesso!';
						} else {
							message = response.data && response.data.message ? response.data.message : 'Erro ao testar conexão';
						}
						
						resultDiv.html('<div class="notice ' + cssClass + '"><p>' + message + '</p></div>').fadeIn();
						btn.prop('disabled', false).html(originalText);
					},
					error: function(xhr, status, error) {
						console.error('AJAX Error:', xhr, status, error);
						resultDiv.html('<div class="notice notice-error"><p>Erro de conexão: ' + error + '</p></div>').fadeIn();
						btn.prop('disabled', false).html(originalText);
					}
				});
			});
			});
			</script>
		</div>
		<?php
	}

	/**
	 * AJAX: Salvar conta SendPulse
	 */
	public function ajax_save_sendpulse_account() {
		check_ajax_referer( 'pcw_sendpulse_account', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_sendpulse_accounts';

		$account_id = absint( $_POST['account_id'] ?? 0 );
		$data = array(
			'name'          => sanitize_text_field( $_POST['name'] ?? '' ),
			'client_id'     => sanitize_text_field( $_POST['client_id'] ?? '' ),
			'client_secret' => sanitize_text_field( $_POST['client_secret'] ?? '' ),
			'from_email'    => sanitize_email( $_POST['from_email'] ?? '' ),
			'from_name'     => sanitize_text_field( $_POST['from_name'] ?? '' ),
			'updated_at'    => current_time( 'mysql' ),
		);

		if ( $account_id > 0 ) {
			// Atualizar
			$result = $wpdb->update( $table, $data, array( 'id' => $account_id ) );
		} else {
			// Inserir
			$data['status'] = 'active';
			$data['created_at'] = current_time( 'mysql' );
			$result = $wpdb->insert( $table, $data );
		}

		if ( $result !== false ) {
			wp_send_json_success( array( 'message' => __( 'Conta salva com sucesso!', 'person-cash-wallet' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Erro ao salvar conta.', 'person-cash-wallet' ) ) );
		}
	}

	/**
	 * AJAX: Excluir conta SendPulse
	 */
	public function ajax_delete_sendpulse_account() {
		check_ajax_referer( 'pcw_sendpulse_account', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		$account_id = absint( $_POST['account_id'] ?? 0 );
		if ( ! $account_id ) {
			wp_send_json_error( array( 'message' => __( 'ID inválido.', 'person-cash-wallet' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_sendpulse_accounts';

		$result = $wpdb->delete( $table, array( 'id' => $account_id ), array( '%d' ) );

		if ( $result !== false ) {
			wp_send_json_success( array( 'message' => __( 'Conta excluída com sucesso!', 'person-cash-wallet' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Erro ao excluir conta.', 'person-cash-wallet' ) ) );
		}
	}

	/**
	 * AJAX: Obter conta SendPulse
	 */
	public function ajax_get_sendpulse_account() {
		check_ajax_referer( 'pcw_sendpulse_account', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		$account_id = absint( $_POST['account_id'] ?? 0 );
		if ( ! $account_id ) {
			wp_send_json_error( array( 'message' => __( 'ID inválido.', 'person-cash-wallet' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_sendpulse_accounts';
		$account = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $account_id ) );

		if ( ! $account ) {
			wp_send_json_error( array( 'message' => __( 'Conta não encontrada.', 'person-cash-wallet' ) ) );
		}

		wp_send_json_success( $account );
	}

	/**
	 * Salvar configurações SendPulse (DEPRECATED - manter por compatibilidade)
	 */
	public function handle_save_sendpulse_settings() {
		check_admin_referer( 'pcw_save_sendpulse_settings' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'person-cash-wallet' ) );
		}

		update_option( 'pcw_sendpulse_client_id', sanitize_text_field( $_POST['client_id'] ?? '' ) );
		update_option( 'pcw_sendpulse_client_secret', sanitize_text_field( $_POST['client_secret'] ?? '' ) );
		update_option( 'pcw_sendpulse_from_email', sanitize_email( $_POST['from_email'] ?? '' ) );
		update_option( 'pcw_sendpulse_from_name', sanitize_text_field( $_POST['from_name'] ?? '' ) );

		// Limpar token armazenado para forçar nova autenticação
		delete_transient( 'pcw_sendpulse_token' );

		wp_safe_redirect( add_query_arg( 
			array( 
				'page'    => 'pcw-settings', 
				'tab'     => 'sendpulse', 
				'message' => 'sendpulse_saved' 
			), 
			admin_url( 'admin.php' ) 
		) );
		exit;
	}

	/**
	 * AJAX: Testar conexão SendPulse
	 */
	public function ajax_test_sendpulse_connection() {
		check_ajax_referer( 'pcw_test_sendpulse', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		// Salvar temporariamente as credenciais para teste
		$client_id     = sanitize_text_field( $_POST['client_id'] ?? '' );
		$client_secret = sanitize_text_field( $_POST['client_secret'] ?? '' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			wp_send_json_error( array( 'message' => __( 'Preencha Client ID e Client Secret.', 'person-cash-wallet' ) ) );
		}

		update_option( 'pcw_sendpulse_client_id', $client_id );
		update_option( 'pcw_sendpulse_client_secret', $client_secret );
		delete_transient( 'pcw_sendpulse_token' );

		$sendpulse = PCW_SendPulse_Integration::instance();
		$result    = $sendpulse->test_connection();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}
}
