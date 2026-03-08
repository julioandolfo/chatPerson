<?php
/**
 * Desconto de Wallet no Checkout
 *
 * Permite usar o saldo da wallet como desconto parcial no checkout,
 * combinando com outras formas de pagamento.
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de desconto de wallet
 */
class PCW_Wallet_Discount {

	/**
	 * Instância única
	 *
	 * @var PCW_Wallet_Discount
	 */
	private static $instance = null;

	/**
	 * Obter instância
	 *
	 * @return PCW_Wallet_Discount
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor
	 */
	private function __construct() {
		// Adicionar seção no checkout
		add_action( 'woocommerce_review_order_before_payment', array( $this, 'render_wallet_discount_section' ), 5 );

		// Adicionar seção no pay-order - usar vários hooks para compatibilidade
		add_action( 'woocommerce_pay_order_before_payment', array( $this, 'render_wallet_discount_section_pay_order' ), 5 );
		add_action( 'wc_smart_checkout_before_order_pay', array( $this, 'render_wallet_discount_section_pay_order' ), 99 );

		// Aplicar desconto via AJAX
		add_action( 'wp_ajax_pcw_apply_wallet_discount', array( $this, 'ajax_apply_wallet_discount' ) );
		add_action( 'wp_ajax_pcw_remove_wallet_discount', array( $this, 'ajax_remove_wallet_discount' ) );

		// Aplicar fee no carrinho
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_wallet_fee' ) );

		// Processar débito da wallet quando pedido é criado
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_wallet_debit_on_order' ), 10, 3 );

		// Processar débito no pay-order
		add_action( 'woocommerce_before_pay_action', array( $this, 'process_wallet_debit_on_pay_order' ), 10, 1 );

		// Enfileirar scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Limpar sessão quando carrinho é esvaziado
		add_action( 'woocommerce_cart_emptied', array( $this, 'clear_wallet_session' ) );

		// Restaurar wallet se pedido falhar
		add_action( 'woocommerce_order_status_failed', array( $this, 'restore_wallet_on_failure' ), 10, 2 );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'restore_wallet_on_failure' ), 10, 2 );
	}

	/**
	 * Renderizar seção de desconto wallet no checkout
	 */
	public function render_wallet_discount_section() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Verificar se pagamento parcial está habilitado
		$settings = get_option( 'pcw_general_settings', array() );
		if ( isset( $settings['wallet_payment_enabled'] ) && 'no' === $settings['wallet_payment_enabled'] ) {
			return;
		}

		$wallet = new PCW_Wallet();
		$balance = $wallet->get_balance();

		// Se não tem saldo, verificar configuração para mostrar ou não
		if ( $balance <= 0 ) {
			$show_without_balance = get_option( 'pcw_show_wallet_without_balance', 'no' );
			if ( 'yes' !== $show_without_balance ) {
				return;
			}

			?>
			<div class="pcw-wallet-discount-section pcw-wallet-no-balance" id="pcw-wallet-discount-section" style="background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);">
				<div class="pcw-wallet-header">
					<span class="pcw-wallet-icon">💰</span>
					<strong><?php esc_html_e( 'Saldo da Wallet', 'person-cash-wallet' ); ?></strong>
				</div>
				<div class="pcw-wallet-content">
					<p style="margin: 0;">
						<?php esc_html_e( 'Você não possui saldo disponível na wallet.', 'person-cash-wallet' ); ?>
					</p>
				</div>
			</div>
			<?php
			return;
		}

		$cart_total = floatval( WC()->cart->get_total( 'edit' ) );
		$applied_amount = floatval( WC()->session->get( 'pcw_wallet_discount_amount', 0 ) );

		// Se já aplicou desconto, ajustar o total real
		if ( $applied_amount > 0 ) {
			$cart_total = $cart_total + $applied_amount; // Voltar ao total original
		}

		$max_usable = min( $balance, $cart_total );

		$this->render_wallet_section( $balance, $cart_total, $applied_amount, $max_usable );
	}

	/**
	 * Renderizar seção de desconto wallet no pay-order
	 *
	 * @param WC_Order $order Objeto do pedido.
	 */
	public function render_wallet_discount_section_pay_order( $order = null ) {
		// Evitar renderizar duas vezes
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		$rendered = true;

		if ( ! is_user_logged_in() ) {
			return;
		}

		// Verificar se pagamento parcial está habilitado
		$settings = get_option( 'pcw_general_settings', array() );
		if ( isset( $settings['wallet_payment_enabled'] ) && 'no' === $settings['wallet_payment_enabled'] ) {
			return;
		}

		$wallet = new PCW_Wallet();
		$balance = $wallet->get_balance();

		// Obter order da URL se não foi passada
		if ( ! $order ) {
			global $wp;
			$order_id = 0;
			if ( isset( $wp->query_vars['order-pay'] ) ) {
				$order_id = absint( $wp->query_vars['order-pay'] );
			}
			if ( $order_id ) {
				$order = wc_get_order( $order_id );
			}
		}

		if ( ! $order ) {
			$rendered = false;
			return;
		}

		$order_total = floatval( $order->get_total() );
		$already_used = floatval( $order->get_meta( '_pcw_wallet_used' ) );

		// Se não tem saldo, verificar configuração para mostrar ou não
		if ( $balance <= 0 ) {
			$show_without_balance = get_option( 'pcw_show_wallet_without_balance', 'no' );
			if ( 'yes' !== $show_without_balance ) {
				return;
			}

			?>
			<div class="pcw-wallet-discount-section pcw-wallet-no-balance" id="pcw-wallet-discount-section" style="background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);">
				<div class="pcw-wallet-header">
					<span class="pcw-wallet-icon">💰</span>
					<strong><?php esc_html_e( 'Saldo da Wallet', 'person-cash-wallet' ); ?></strong>
				</div>
				<div class="pcw-wallet-content">
					<p style="margin: 0;">
						<?php esc_html_e( 'Você não possui saldo disponível na wallet.', 'person-cash-wallet' ); ?>
					</p>
				</div>
			</div>
			<?php
			$this->render_pay_order_positioning_script();
			return;
		}

		// Se já usou wallet neste pedido, mostrar informação
		if ( $already_used > 0 ) {
			?>
			<div class="pcw-wallet-discount-section pcw-wallet-applied" id="pcw-wallet-discount-section">
				<div class="pcw-wallet-header">
					<span class="pcw-wallet-icon">💰</span>
					<strong><?php esc_html_e( 'Desconto Wallet Aplicado', 'person-cash-wallet' ); ?></strong>
				</div>
				<div class="pcw-wallet-content">
					<p>
						<?php
						printf(
							/* translators: %s: amount already used */
							esc_html__( 'Você já usou %s da sua wallet neste pedido.', 'person-cash-wallet' ),
							wp_kses_post( wc_price( $already_used ) )
						);
						?>
					</p>
				</div>
			</div>
			<?php
			$this->render_pay_order_positioning_script();
			return;
		}

		$max_usable = min( $balance, $order_total );
		$applied_amount = floatval( WC()->session->get( 'pcw_wallet_discount_amount_order_' . $order->get_id(), 0 ) );

		$this->render_wallet_section( $balance, $order_total, $applied_amount, $max_usable, $order->get_id() );
		$this->render_pay_order_positioning_script();
	}

	/**
	 * Renderizar script de posicionamento para pay-order
	 */
	private function render_pay_order_positioning_script() {
		// Adicionar estilos inline para garantir que funcionem no pay-order
		?>
		<style>
			.pcw-wallet-discount-section {
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				border-radius: 12px;
				padding: 20px;
				margin-top: 25px;
				margin-bottom: 20px;
				color: #fff;
			}
			.pcw-wallet-header {
				display: flex;
				align-items: center;
				gap: 10px;
				margin-bottom: 15px;
				font-size: 1.1em;
			}
			.pcw-wallet-icon {
				font-size: 1.5em;
			}
			.pcw-wallet-content {
				background: rgba(255,255,255,0.15);
				border-radius: 8px;
				padding: 15px;
			}
			.pcw-wallet-balance-info {
				margin: 0 0 15px 0;
				font-size: 1em;
			}
			.pcw-wallet-input-group label {
				display: block;
				margin-bottom: 8px;
				font-weight: 500;
			}
			.pcw-wallet-input-wrapper {
				display: flex;
				align-items: center;
				background: #fff;
				border-radius: 6px;
				overflow: hidden;
				max-width: 200px;
			}
			.pcw-currency-symbol {
				padding: 10px 12px;
				background: #f0f0f0;
				color: #333;
				font-weight: bold;
			}
			.pcw-wallet-input {
				border: none !important;
				padding: 10px !important;
				width: 100% !important;
				color: #333 !important;
				font-size: 1.1em !important;
			}
			.pcw-wallet-input:focus {
				outline: none !important;
				box-shadow: none !important;
			}
			.pcw-wallet-max-info {
				margin: 8px 0 15px 0;
				font-size: 0.9em;
				opacity: 0.9;
			}
			.pcw-wallet-quick-buttons {
				display: flex;
				gap: 8px;
				flex-wrap: wrap;
				margin-bottom: 15px;
			}
			.pcw-quick-amount {
				background: rgba(255,255,255,0.2);
				border: 1px solid rgba(255,255,255,0.3);
				color: #fff;
				padding: 6px 12px;
				border-radius: 20px;
				cursor: pointer;
				font-size: 0.85em;
				transition: all 0.2s;
			}
			.pcw-quick-amount:hover {
				background: rgba(255,255,255,0.3);
			}
			.pcw-apply-wallet-discount {
				background: #fff !important;
				color: #667eea !important;
				border: none !important;
				padding: 12px 24px !important;
				border-radius: 6px !important;
				font-weight: bold !important;
				cursor: pointer;
				transition: all 0.2s;
			}
			.pcw-apply-wallet-discount:hover {
				transform: translateY(-2px);
				box-shadow: 0 4px 12px rgba(0,0,0,0.2);
			}
			.pcw-wallet-applied-info {
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 15px;
				flex-wrap: wrap;
			}
			.pcw-wallet-applied-info p {
				margin: 0;
				font-size: 1.1em;
			}
			.pcw-discount-value {
				font-size: 1.3em;
			}
			.pcw-remove-wallet-discount {
				background: rgba(255,255,255,0.2) !important;
				color: #fff !important;
				border: 1px solid rgba(255,255,255,0.3) !important;
				padding: 8px 16px !important;
				border-radius: 6px !important;
			}
			.pcw-remove-wallet-discount:hover {
				background: rgba(255,255,255,0.3) !important;
			}
			.pcw-wallet-remaining-info {
				margin: 15px 0 0 0;
				padding-top: 15px;
				border-top: 1px solid rgba(255,255,255,0.2);
				font-size: 0.95em;
				opacity: 0.9;
			}
			.pcw-wallet-discount-section.pcw-loading {
				opacity: 0.7;
				pointer-events: none;
			}
		</style>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			console.log('[PCW Wallet] Iniciando posicionamento do campo (pay_order)...');
			
			var field = document.getElementById('pcw-wallet-discount-section');
			if (!field) {
				console.log('[PCW Wallet] ERRO: Campo não encontrado!');
				return;
			}
			console.log('[PCW Wallet] Campo encontrado');

			// WC Smart Checkout: inserir após .order-totals
			var orderTotals = document.querySelector('.order-totals');
			console.log('[PCW Wallet] Buscando .order-totals:', orderTotals ? 'encontrado' : 'não encontrado');
			if (orderTotals) {
				console.log('[PCW Wallet] ✓ WC Smart Checkout detectado! Inserindo após .order-totals');
				orderTotals.parentNode.insertBefore(field, orderTotals.nextSibling);
				field.style.display = 'block';
				return;
			}

			// WC Smart Checkout: inserir após .smart-checkout-review-order
			var smartReview = document.querySelector('.smart-checkout-review-order');
			console.log('[PCW Wallet] Buscando .smart-checkout-review-order:', smartReview ? 'encontrado' : 'não encontrado');
			if (smartReview) {
				console.log('[PCW Wallet] ✓ WC Smart Checkout detectado! Inserindo após .smart-checkout-review-order');
				smartReview.parentNode.insertBefore(field, smartReview.nextSibling);
				field.style.display = 'block';
				return;
			}

			// Inserir ANTES do campo de indicação (se existir)
			var referralField = document.getElementById('pcw-referral-field-wrapper');
			if (referralField) {
				console.log('[PCW Wallet] ✓ Campo de indicação encontrado! Inserindo antes dele');
				referralField.parentNode.insertBefore(field, referralField);
				field.style.display = 'block';
				return;
			}

			// Buscar por texto "Selecione" em headings e inserir ANTES
			var headings = document.querySelectorAll('h2, h3, h4, .order-pay-title');
			console.log('[PCW Wallet] Headings encontrados:', headings.length);
			
			for (var i = 0; i < headings.length; i++) {
				var text = headings[i].textContent || '';
				if (text.indexOf('Selecione') !== -1 || text.indexOf('forma de pagamento') !== -1) {
					console.log('[PCW Wallet] ✓ Match "Selecione"! Inserindo antes');
					headings[i].parentNode.insertBefore(field, headings[i]);
					field.style.display = 'block';
					return;
				}
			}

			// Fallback: inserir antes do #payment
			var payment = document.querySelector('#payment');
			if (payment) {
				console.log('[PCW Wallet] ✓ Usando fallback #payment');
				payment.parentNode.insertBefore(field, payment);
				field.style.display = 'block';
				return;
			}

			// Fallback: inserir após shop_table
			var shopTable = document.querySelector('.shop_table, .woocommerce-checkout-review-order-table');
			if (shopTable) {
				console.log('[PCW Wallet] ✓ Usando fallback shop_table');
				shopTable.parentNode.insertBefore(field, shopTable.nextSibling);
				field.style.display = 'block';
				return;
			}

			console.log('[PCW Wallet] ⚠ Nenhum local encontrado!');
		});
		</script>
		<?php
	}

	/**
	 * Renderizar seção de wallet
	 *
	 * @param float $balance Saldo disponível.
	 * @param float $total Total do pedido/carrinho.
	 * @param float $applied_amount Valor já aplicado.
	 * @param float $max_usable Máximo utilizável.
	 * @param int   $order_id ID do pedido (0 para checkout).
	 */
	private function render_wallet_section( $balance, $total, $applied_amount, $max_usable, $order_id = 0 ) {
		?>
		<div class="pcw-wallet-discount-section" id="pcw-wallet-discount-section">
			<div class="pcw-wallet-header">
				<span class="pcw-wallet-icon">💰</span>
				<strong><?php esc_html_e( 'Usar Saldo da Wallet', 'person-cash-wallet' ); ?></strong>
			</div>

			<div class="pcw-wallet-content">
				<p class="pcw-wallet-balance-info">
					<?php esc_html_e( 'Seu saldo:', 'person-cash-wallet' ); ?>
					<strong><?php echo wp_kses_post( wc_price( $balance ) ); ?></strong>
				</p>

				<?php if ( $applied_amount > 0 ) : ?>
					<div class="pcw-wallet-applied-info">
						<p>
							<?php esc_html_e( 'Desconto aplicado:', 'person-cash-wallet' ); ?>
							<strong class="pcw-discount-value">-<?php echo wp_kses_post( wc_price( $applied_amount ) ); ?></strong>
						</p>
						<button type="button" class="button pcw-remove-wallet-discount" data-order-id="<?php echo esc_attr( $order_id ); ?>">
							<?php esc_html_e( 'Remover', 'person-cash-wallet' ); ?>
						</button>
					</div>
				<?php else : ?>
					<div class="pcw-wallet-apply-form">
						<div class="pcw-wallet-input-group">
							<label for="pcw_wallet_discount_amount">
								<?php esc_html_e( 'Quanto deseja usar?', 'person-cash-wallet' ); ?>
							</label>
							<div class="pcw-wallet-input-wrapper">
								<span class="pcw-currency-symbol"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
								<input 
									type="number" 
									id="pcw_wallet_discount_amount" 
									name="pcw_wallet_discount_amount" 
									step="0.01" 
									min="0.01" 
									max="<?php echo esc_attr( $max_usable ); ?>" 
									value="<?php echo esc_attr( $max_usable ); ?>"
									class="pcw-wallet-input"
								>
							</div>
							<p class="pcw-wallet-max-info">
								<?php
								printf(
									/* translators: %s: maximum amount */
									esc_html__( 'Máximo: %s', 'person-cash-wallet' ),
									wp_kses_post( wc_price( $max_usable ) )
								);
								?>
							</p>
						</div>

						<div class="pcw-wallet-quick-buttons">
							<button type="button" class="pcw-quick-amount" data-amount="<?php echo esc_attr( $max_usable ); ?>">
								<?php esc_html_e( 'Usar tudo', 'person-cash-wallet' ); ?>
							</button>
							<?php if ( $max_usable > 50 ) : ?>
								<button type="button" class="pcw-quick-amount" data-amount="50">R$ 50</button>
							<?php endif; ?>
							<?php if ( $max_usable > 100 ) : ?>
								<button type="button" class="pcw-quick-amount" data-amount="100">R$ 100</button>
							<?php endif; ?>
						</div>

						<button type="button" class="button alt pcw-apply-wallet-discount" data-order-id="<?php echo esc_attr( $order_id ); ?>">
							<?php esc_html_e( 'Aplicar Desconto', 'person-cash-wallet' ); ?>
						</button>
					</div>
				<?php endif; ?>

				<p class="pcw-wallet-remaining-info" style="display: <?php echo $applied_amount > 0 ? 'block' : 'none'; ?>;">
					<?php esc_html_e( 'Pague o restante com outra forma de pagamento.', 'person-cash-wallet' ); ?>
				</p>
			</div>

			<input type="hidden" id="pcw_wallet_discount_applied" name="pcw_wallet_discount_applied" value="<?php echo esc_attr( $applied_amount ); ?>">
			<input type="hidden" id="pcw_wallet_order_id" value="<?php echo esc_attr( $order_id ); ?>">
		</div>
		<?php
	}

	/**
	 * Aplicar desconto de wallet via AJAX
	 */
	public function ajax_apply_wallet_discount() {
		check_ajax_referer( 'pcw_wallet_discount_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Você precisa estar logado.', 'person-cash-wallet' ) ) );
		}

		$amount = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( $amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Valor inválido.', 'person-cash-wallet' ) ) );
		}

		$wallet = new PCW_Wallet();
		$balance = $wallet->get_balance();

		if ( $amount > $balance ) {
			wp_send_json_error( array( 'message' => __( 'Saldo insuficiente.', 'person-cash-wallet' ) ) );
		}

		// Verificar total
		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				wp_send_json_error( array( 'message' => __( 'Pedido não encontrado.', 'person-cash-wallet' ) ) );
			}
			$total = floatval( $order->get_total() );
			$session_key = 'pcw_wallet_discount_amount_order_' . $order_id;
		} else {
			$total = floatval( WC()->cart->get_total( 'edit' ) );
			$session_key = 'pcw_wallet_discount_amount';
		}

		if ( $amount > $total ) {
			$amount = $total;
		}

		// Salvar na sessão
		WC()->session->set( $session_key, $amount );

		wp_send_json_success(
			array(
				'message'    => sprintf(
					/* translators: %s: discount amount */
					__( 'Desconto de %s aplicado!', 'person-cash-wallet' ),
					wc_price( $amount )
				),
				'amount'     => $amount,
				'amount_formatted' => wc_price( $amount ),
			)
		);
	}

	/**
	 * Remover desconto de wallet via AJAX
	 */
	public function ajax_remove_wallet_discount() {
		check_ajax_referer( 'pcw_wallet_discount_nonce', 'nonce' );

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( $order_id > 0 ) {
			$session_key = 'pcw_wallet_discount_amount_order_' . $order_id;
		} else {
			$session_key = 'pcw_wallet_discount_amount';
		}

		WC()->session->set( $session_key, 0 );

		wp_send_json_success(
			array(
				'message' => __( 'Desconto removido.', 'person-cash-wallet' ),
			)
		);
	}

	/**
	 * Aplicar fee de wallet no carrinho
	 *
	 * @param WC_Cart $cart Objeto do carrinho.
	 */
	public function apply_wallet_fee( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$discount_amount = floatval( WC()->session->get( 'pcw_wallet_discount_amount', 0 ) );

		if ( $discount_amount <= 0 ) {
			return;
		}

		// Verificar se usuário ainda tem saldo
		$wallet = new PCW_Wallet();
		$balance = $wallet->get_balance();

		if ( $discount_amount > $balance ) {
			$discount_amount = $balance;
			WC()->session->set( 'pcw_wallet_discount_amount', $discount_amount );
		}

		// Verificar se desconto não é maior que o total
		$cart_total = $cart->get_subtotal() + $cart->get_shipping_total() + $cart->get_subtotal_tax() + $cart->get_shipping_tax();

		// Subtrair outros fees para calcular total real
		foreach ( $cart->get_fees() as $fee ) {
			if ( 'pcw-wallet-discount' !== $fee->id ) {
				$cart_total += $fee->total;
			}
		}

		if ( $discount_amount > $cart_total ) {
			$discount_amount = $cart_total;
			WC()->session->set( 'pcw_wallet_discount_amount', $discount_amount );
		}

		if ( $discount_amount > 0 ) {
			$cart->add_fee( __( 'Desconto Wallet', 'person-cash-wallet' ), -$discount_amount, false, '' );
		}
	}

	/**
	 * Processar débito da wallet quando pedido é criado
	 *
	 * @param int      $order_id ID do pedido.
	 * @param array    $posted_data Dados postados.
	 * @param WC_Order $order Objeto do pedido.
	 */
	public function process_wallet_debit_on_order( $order_id, $posted_data, $order ) {
		$discount_amount = floatval( WC()->session->get( 'pcw_wallet_discount_amount', 0 ) );

		if ( $discount_amount <= 0 ) {
			return;
		}

		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			return;
		}

		$wallet = new PCW_Wallet( $user_id );
		$balance_before = $wallet->get_balance();

		// Verificar saldo novamente
		if ( $discount_amount > $balance_before ) {
			$discount_amount = $balance_before;
		}

		if ( $discount_amount <= 0 ) {
			return;
		}

		// Debitar da wallet
		$transaction_id = $wallet->debit(
			$discount_amount,
			'purchase',
			sprintf( __( 'Desconto no pedido #%d', 'person-cash-wallet' ), $order_id ),
			$order_id
		);

		if ( $transaction_id ) {
			$balance_after = $wallet->get_balance();

			// Salvar informações no pedido
			$order->update_meta_data( '_pcw_wallet_used', $discount_amount );
			$order->update_meta_data( '_pcw_wallet_balance_before', $balance_before );
			$order->update_meta_data( '_pcw_wallet_balance_after', $balance_after );
			$order->update_meta_data( '_pcw_wallet_transaction_id', $transaction_id );
			$order->save();

			// Adicionar nota no pedido
			$order->add_order_note(
				sprintf(
					/* translators: %s: discount amount */
					__( 'Desconto de %s aplicado usando saldo da Wallet.', 'person-cash-wallet' ),
					wc_price( $discount_amount )
				)
			);
		}

		// Limpar sessão
		WC()->session->set( 'pcw_wallet_discount_amount', 0 );
	}

	/**
	 * Processar débito da wallet no pay-order
	 *
	 * @param WC_Order $order Objeto do pedido.
	 */
	public function process_wallet_debit_on_pay_order( $order ) {
		$order_id = $order->get_id();
		$discount_amount = floatval( WC()->session->get( 'pcw_wallet_discount_amount_order_' . $order_id, 0 ) );

		if ( $discount_amount <= 0 ) {
			return;
		}

		// Verificar se já usou wallet neste pedido
		$already_used = floatval( $order->get_meta( '_pcw_wallet_used' ) );
		if ( $already_used > 0 ) {
			return;
		}

		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			return;
		}

		$wallet = new PCW_Wallet( $user_id );
		$balance_before = $wallet->get_balance();

		// Verificar saldo novamente
		if ( $discount_amount > $balance_before ) {
			$discount_amount = $balance_before;
		}

		if ( $discount_amount <= 0 ) {
			return;
		}

		// Debitar da wallet
		$transaction_id = $wallet->debit(
			$discount_amount,
			'purchase',
			sprintf( __( 'Desconto no pedido #%d', 'person-cash-wallet' ), $order_id ),
			$order_id
		);

		if ( $transaction_id ) {
			$balance_after = $wallet->get_balance();

			// Adicionar fee negativo ao pedido
			$item = new WC_Order_Item_Fee();
			$item->set_name( __( 'Desconto Wallet', 'person-cash-wallet' ) );
			$item->set_amount( -$discount_amount );
			$item->set_total( -$discount_amount );
			$order->add_item( $item );

			// Salvar informações no pedido
			$order->update_meta_data( '_pcw_wallet_used', $discount_amount );
			$order->update_meta_data( '_pcw_wallet_balance_before', $balance_before );
			$order->update_meta_data( '_pcw_wallet_balance_after', $balance_after );
			$order->update_meta_data( '_pcw_wallet_transaction_id', $transaction_id );

			// Recalcular totais
			$order->calculate_totals();
			$order->save();

			// Adicionar nota no pedido
			$order->add_order_note(
				sprintf(
					/* translators: %s: discount amount */
					__( 'Desconto de %s aplicado usando saldo da Wallet.', 'person-cash-wallet' ),
					wc_price( $discount_amount )
				)
			);
		}

		// Limpar sessão
		WC()->session->set( 'pcw_wallet_discount_amount_order_' . $order_id, 0 );
	}

	/**
	 * Limpar sessão de wallet
	 */
	public function clear_wallet_session() {
		if ( WC()->session ) {
			WC()->session->set( 'pcw_wallet_discount_amount', 0 );
		}
	}

	/**
	 * Restaurar wallet se pedido falhar ou for cancelado
	 *
	 * @param int      $order_id ID do pedido.
	 * @param WC_Order $order Objeto do pedido.
	 */
	public function restore_wallet_on_failure( $order_id, $order = null ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		// Verificar se já foi restaurado
		$restored = $order->get_meta( '_pcw_wallet_restored' );
		if ( $restored ) {
			return;
		}

		$wallet_used = floatval( $order->get_meta( '_pcw_wallet_used' ) );

		if ( $wallet_used <= 0 ) {
			return;
		}

		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			return;
		}

		$wallet = new PCW_Wallet( $user_id );

		// Creditar de volta
		$transaction_id = $wallet->credit(
			$wallet_used,
			'refund',
			sprintf( __( 'Estorno do pedido #%d (cancelado/falhou)', 'person-cash-wallet' ), $order_id ),
			$order_id
		);

		if ( $transaction_id ) {
			$order->update_meta_data( '_pcw_wallet_restored', true );
			$order->update_meta_data( '_pcw_wallet_restored_amount', $wallet_used );
			$order->save();

			$order->add_order_note(
				sprintf(
					/* translators: %s: refund amount */
					__( 'Wallet: %s estornado devido ao cancelamento/falha do pedido.', 'person-cash-wallet' ),
					wc_price( $wallet_used )
				)
			);
		}
	}

	/**
	 * Enfileirar scripts
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() && ! is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		// Verificar se usuário tem saldo
		$wallet = new PCW_Wallet();
		$balance = $wallet->get_balance();

		if ( $balance <= 0 ) {
			return;
		}

		// Na página pay-order, wc-checkout pode não estar disponível
		$deps = array( 'jquery' );
		if ( is_checkout() && ! is_wc_endpoint_url( 'order-pay' ) ) {
			$deps[] = 'wc-checkout';
		}

		wp_enqueue_script(
			'pcw-wallet-discount',
			PCW_PLUGIN_URL . 'assets/js/wallet-discount.js',
			$deps,
			PCW_VERSION,
			true
		);

		wp_localize_script(
			'pcw-wallet-discount',
			'pcw_wallet_discount',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'pcw_wallet_discount_nonce' ),
				'i18n'     => array(
					'applying'  => __( 'Aplicando...', 'person-cash-wallet' ),
					'removing'  => __( 'Removendo...', 'person-cash-wallet' ),
					'error'     => __( 'Erro ao processar. Tente novamente.', 'person-cash-wallet' ),
				),
			)
		);

		// CSS inline
		wp_add_inline_style( 'woocommerce-general', $this->get_inline_styles() );
	}

	/**
	 * Obter estilos inline
	 *
	 * @return string
	 */
	private function get_inline_styles() {
		return '
			.pcw-wallet-discount-section {
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				border-radius: 12px;
				padding: 20px;
				margin-top: 25px;
				margin-bottom: 20px;
				color: #fff;
			}
			.pcw-wallet-header {
				display: flex;
				align-items: center;
				gap: 10px;
				margin-bottom: 15px;
				font-size: 1.1em;
			}
			.pcw-wallet-icon {
				font-size: 1.5em;
			}
			.pcw-wallet-content {
				background: rgba(255,255,255,0.15);
				border-radius: 8px;
				padding: 15px;
			}
			.pcw-wallet-balance-info {
				margin: 0 0 15px 0;
				font-size: 1em;
			}
			.pcw-wallet-input-group label {
				display: block;
				margin-bottom: 8px;
				font-weight: 500;
			}
			.pcw-wallet-input-wrapper {
				display: flex;
				align-items: center;
				background: #fff;
				border-radius: 6px;
				overflow: hidden;
				max-width: 200px;
			}
			.pcw-currency-symbol {
				padding: 10px 12px;
				background: #f0f0f0;
				color: #333;
				font-weight: bold;
			}
			.pcw-wallet-input {
				border: none !important;
				padding: 10px !important;
				width: 100% !important;
				color: #333 !important;
				font-size: 1.1em !important;
			}
			.pcw-wallet-input:focus {
				outline: none !important;
				box-shadow: none !important;
			}
			.pcw-wallet-max-info {
				margin: 8px 0 15px 0;
				font-size: 0.9em;
				opacity: 0.9;
			}
			.pcw-wallet-quick-buttons {
				display: flex;
				gap: 8px;
				flex-wrap: wrap;
				margin-bottom: 15px;
			}
			.pcw-quick-amount {
				background: rgba(255,255,255,0.2);
				border: 1px solid rgba(255,255,255,0.3);
				color: #fff;
				padding: 6px 12px;
				border-radius: 20px;
				cursor: pointer;
				font-size: 0.85em;
				transition: all 0.2s;
			}
			.pcw-quick-amount:hover {
				background: rgba(255,255,255,0.3);
			}
			.pcw-apply-wallet-discount {
				background: #fff !important;
				color: #667eea !important;
				border: none !important;
				padding: 12px 24px !important;
				border-radius: 6px !important;
				font-weight: bold !important;
				cursor: pointer;
				transition: all 0.2s;
			}
			.pcw-apply-wallet-discount:hover {
				transform: translateY(-2px);
				box-shadow: 0 4px 12px rgba(0,0,0,0.2);
			}
			.pcw-wallet-applied-info {
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 15px;
				flex-wrap: wrap;
			}
			.pcw-wallet-applied-info p {
				margin: 0;
				font-size: 1.1em;
			}
			.pcw-discount-value {
				font-size: 1.3em;
			}
			.pcw-remove-wallet-discount {
				background: rgba(255,255,255,0.2) !important;
				color: #fff !important;
				border: 1px solid rgba(255,255,255,0.3) !important;
				padding: 8px 16px !important;
				border-radius: 6px !important;
			}
			.pcw-remove-wallet-discount:hover {
				background: rgba(255,255,255,0.3) !important;
			}
			.pcw-wallet-remaining-info {
				margin: 15px 0 0 0;
				padding-top: 15px;
				border-top: 1px solid rgba(255,255,255,0.2);
				font-size: 0.95em;
				opacity: 0.9;
			}
			.pcw-wallet-discount-section.pcw-loading {
				opacity: 0.7;
				pointer-events: none;
			}
		';
	}
}
