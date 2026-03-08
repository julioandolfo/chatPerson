<?php
/**
 * Classe para campo de indicação no checkout e pay for order
 *
 * @package PersonCashWallet
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de checkout de indicação
 */
class PCW_Referral_Checkout {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Referral_Checkout
	 */
	private static $instance = null;

	/**
	 * Obter instância singleton
	 *
	 * @return PCW_Referral_Checkout
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
		$settings = PCW_Referral_Rewards::instance()->get_settings();

		if ( 'yes' !== $settings['enabled'] ) {
			return;
		}

		// Campo no checkout normal - tentar vários hooks para compatibilidade
		add_action( 'woocommerce_review_order_before_payment', array( $this, 'render_checkout_field' ), 5 );

		// Campo na página pay_for_order - usar hook que existe no template
		add_action( 'wc_smart_checkout_before_order_pay', array( $this, 'render_pay_order_field' ), 99 );
		add_action( 'woocommerce_pay_order_before_payment', array( $this, 'render_pay_order_field' ) );

		// Validação
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_checkout_field' ) );

		// Salvar no pedido
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_referral_code' ) );

		// Salvar no pay_for_order
		add_action( 'woocommerce_before_pay_action', array( $this, 'save_pay_order_referral_code' ) );

		// AJAX para validar código
		add_action( 'wp_ajax_pcw_validate_referral_code', array( $this, 'ajax_validate_code' ) );
		add_action( 'wp_ajax_nopriv_pcw_validate_referral_code', array( $this, 'ajax_validate_code' ) );

		// Scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// WooCommerce Blocks API
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'save_referral_from_blocks' ), 10, 2 );
	}

	/**
	 * Renderizar campo no checkout normal
	 *
	 * @param WC_Checkout $checkout Objeto checkout.
	 */
	public function render_checkout_field( $checkout = null ) {
		// Não renderizar na página pay_for_order
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}

		// Calcular cashback estimado do carrinho
		$cashback_amount = $this->calculate_cart_cashback();

		// Renderizar box de cashback se houver
		if ( $cashback_amount > 0 ) {
			$this->render_cashback_box( $cashback_amount );
		}

		// Não exibir para o próprio indicador
		$current_user_id = get_current_user_id();

		// Obter código do cookie
		$cookie_code = PCW_Referral_Tracking::instance()->get_cookie();

		// Validar código do cookie
		$cookie_valid = false;
		$cookie_message = '';

		if ( $cookie_code ) {
			$validation = PCW_Referral_Codes::instance()->validate_code( $cookie_code, $current_user_id );
			$cookie_valid = $validation['valid'];
			$cookie_message = $validation['message'];
		}

		$this->render_field( $cookie_code, $cookie_valid, $cookie_message, 'checkout' );
	}

	/**
	 * Calcular cashback estimado do carrinho
	 *
	 * @return float
	 */
	private function calculate_cart_cashback() {
		// Verificar se cashback está habilitado
		if ( 'yes' !== get_option( 'pcw_cashback_enabled', 'yes' ) ) {
			return 0;
		}

		if ( ! WC()->cart ) {
			return 0;
		}

		$cart_total = WC()->cart->get_total( 'edit' );

		if ( $cart_total <= 0 ) {
			return 0;
		}

		// Obter regras ativas
		$rules = PCW_Cashback_Rules::get_active_rules();

		if ( empty( $rules ) ) {
			return 0;
		}

		// Usar a primeira regra ativa (maior prioridade)
		foreach ( $rules as $rule ) {
			// Verificar valor mínimo
			if ( $rule->min_order_amount > 0 && $cart_total < floatval( $rule->min_order_amount ) ) {
				continue;
			}

			// Calcular cashback
			if ( 'percentage' === $rule->type ) {
				$cashback = ( $cart_total * floatval( $rule->value ) ) / 100;
			} else {
				$cashback = floatval( $rule->value );
			}

			// Aplicar limite máximo
			if ( $rule->max_cashback_amount > 0 ) {
				$cashback = min( $cashback, floatval( $rule->max_cashback_amount ) );
			}

			if ( $cashback > 0 ) {
				return round( $cashback, 2 );
			}
		}

		return 0;
	}

	/**
	 * Renderizar campo na página pay_for_order
	 */
	public function render_pay_order_field() {
		global $wp;

		// Evitar renderizar duas vezes
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		$rendered = true;

		// Obter order ID da URL
		$order_id = 0;

		if ( isset( $wp->query_vars['order-pay'] ) ) {
			$order_id = absint( $wp->query_vars['order-pay'] );
		}

		if ( ! $order_id ) {
			$rendered = false; // Reset se não tinha order
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Verificar se pedido já tem código salvo
		$saved_code = $order->get_meta( '_pcw_referral_code' );

		if ( $saved_code ) {
			// Exibir código já salvo como readonly
			$validation = PCW_Referral_Codes::instance()->validate_code( $saved_code );
			?>
			<div class="pcw-referral-field pcw-referral-readonly">
				<h4><?php esc_html_e( 'Código de Indicação', 'person-cash-wallet' ); ?></h4>
				<div class="pcw-referral-saved">
					<span class="pcw-referral-code"><?php echo esc_html( $saved_code ); ?></span>
					<?php if ( $validation['valid'] ) : ?>
						<span class="pcw-referral-valid">✓ <?php echo esc_html( $validation['message'] ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<?php
			return;
		}

		// Calcular cashback estimado para este pedido
		$cashback_amount = $this->calculate_estimated_cashback( $order );

		// Renderizar box de cashback se houver
		if ( $cashback_amount > 0 ) {
			$this->render_cashback_box( $cashback_amount );
		}

		// Não exibir para o próprio indicador
		$current_user_id = $order->get_user_id();

		// Obter código do cookie
		$cookie_code = PCW_Referral_Tracking::instance()->get_cookie();

		// Validar código do cookie
		$cookie_valid = false;
		$cookie_message = '';

		if ( $cookie_code ) {
			$validation = PCW_Referral_Codes::instance()->validate_code( $cookie_code, $current_user_id );
			$cookie_valid = $validation['valid'];
			$cookie_message = $validation['message'];
		}

		$this->render_field( $cookie_code, $cookie_valid, $cookie_message, 'pay_order' );
	}

	/**
	 * Calcular cashback estimado para um pedido
	 *
	 * @param WC_Order $order Pedido.
	 * @return float
	 */
	private function calculate_estimated_cashback( $order ) {
		// Verificar se cashback está habilitado
		if ( 'yes' !== get_option( 'pcw_cashback_enabled', 'yes' ) ) {
			return 0;
		}

		// Obter regras ativas
		$rules = PCW_Cashback_Rules::get_active_rules();

		if ( empty( $rules ) ) {
			return 0;
		}

		$total_cashback = 0;

		foreach ( $rules as $rule ) {
			// Verificar se a regra se aplica
			if ( ! PCW_Cashback_Rules::rule_applies( $order, $rule ) ) {
				continue;
			}

			// Calcular cashback desta regra
			$cashback = PCW_Cashback_Rules::calculate_cashback( $order, $rule );

			if ( $cashback > 0 ) {
				$total_cashback += $cashback;
				break; // Usar apenas a primeira regra que se aplica (maior prioridade)
			}
		}

		return $total_cashback;
	}

	/**
	 * Renderizar box de cashback
	 *
	 * @param float $amount Valor do cashback.
	 */
	private function render_cashback_box( $amount ) {
		?>
		<div class="pcw-cashback-box" id="pcw-cashback-box">
			<div class="pcw-cashback-icon">💰</div>
			<div class="pcw-cashback-info">
				<span class="pcw-cashback-label"><?php esc_html_e( 'Cashback nesta compra', 'person-cash-wallet' ); ?></span>
				<span class="pcw-cashback-value"><?php echo wp_kses_post( wc_price( $amount ) ); ?></span>
			</div>
		</div>
		<style>
			.pcw-cashback-box {
				display: flex;
				align-items: center;
				gap: 10px;
				margin: 15px 0 10px 0;
				padding: 10px 14px;
				background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
				border: 1px solid #a7f3d0;
				border-radius: 6px;
			}
			.pcw-cashback-icon {
				font-size: 20px;
			}
			.pcw-cashback-info {
				display: flex;
				flex-direction: column;
				gap: 2px;
			}
			.pcw-cashback-label {
				font-size: 11px;
				color: #065f46;
				text-transform: uppercase;
				letter-spacing: 0.5px;
			}
			.pcw-cashback-value {
				font-size: 16px;
				font-weight: 700;
				color: #047857;
			}
			.pcw-cashback-value .woocommerce-Price-amount {
				color: #047857;
			}
		</style>
		<?php
	}

	/**
	 * Renderizar campo de indicação
	 *
	 * @param string $cookie_code Código do cookie.
	 * @param bool   $cookie_valid Se cookie é válido.
	 * @param string $cookie_message Mensagem de validação.
	 * @param string $context Contexto: 'checkout' ou 'pay_order'.
	 */
	private function render_field( $cookie_code, $cookie_valid, $cookie_message, $context = 'checkout' ) {
		$field_id = 'pay_order' === $context ? 'pcw_referral_code_pay' : 'pcw_referral_code';
		?>
		<div class="pcw-referral-field" id="pcw-referral-field-wrapper">
			<h4><?php esc_html_e( 'Código de Indicação', 'person-cash-wallet' ); ?></h4>
			<p class="pcw-referral-description"><?php esc_html_e( 'Foi indicado por alguém? Informe o código:', 'person-cash-wallet' ); ?></p>

			<div class="pcw-referral-input-wrapper">
				<input 
					type="text" 
					name="<?php echo esc_attr( $field_id ); ?>" 
					id="<?php echo esc_attr( $field_id ); ?>" 
					class="input-text pcw-referral-input" 
					placeholder="<?php esc_attr_e( 'Ex: JOAO1234', 'person-cash-wallet' ); ?>"
					value="<?php echo $cookie_valid ? esc_attr( $cookie_code ) : ''; ?>"
					maxlength="20"
					autocomplete="off"
				/>
				<button type="button" class="pcw-referral-validate-btn" id="pcw-validate-code-btn"><?php esc_html_e( 'Validar', 'person-cash-wallet' ); ?></button>
			</div>

			<div class="pcw-referral-feedback" id="pcw-referral-feedback" style="<?php echo $cookie_valid ? '' : 'display:none;'; ?>"><?php if ( $cookie_valid ) : ?><span class="pcw-valid">✓ <?php echo esc_html( $cookie_message ); ?></span><?php endif; ?></div>

			<input type="hidden" name="pcw_referral_code_validated" id="pcw_referral_code_validated" value="<?php echo $cookie_valid ? '1' : '0'; ?>" />
		</div>

		<style>
			.pcw-referral-field {
				margin: 15px 0;
				padding: 12px 15px;
				background: #fafafa;
				border-radius: 6px;
				border: 1px solid #e5e5e5;
			}
			.pcw-referral-field h4 {
				margin: 0 0 4px 0;
				font-size: 13px;
				font-weight: 600;
				color: #444;
			}
			.pcw-referral-description {
				margin: 0 0 10px 0;
				color: #777;
				font-size: 12px;
			}
			.pcw-referral-input-wrapper {
				display: flex;
				gap: 8px;
				max-width: 380px;
			}
			.pcw-referral-input {
				flex: 1;
				min-width: 180px;
				text-transform: uppercase;
				font-weight: 500;
				letter-spacing: 1px;
				font-size: 14px !important;
				padding: 10px 12px !important;
				height: auto !important;
				min-height: unset !important;
				border: 1px solid #ccc !important;
				border-radius: 4px !important;
			}
			.pcw-referral-input:focus {
				border-color: #666 !important;
				outline: none !important;
			}
			.pcw-referral-validate-btn {
				white-space: nowrap;
				font-size: 13px !important;
				padding: 10px 18px !important;
				height: auto !important;
				min-height: unset !important;
				background: #555 !important;
				color: #fff !important;
				border: none !important;
				border-radius: 4px !important;
				cursor: pointer;
			}
			.pcw-referral-validate-btn:hover {
				background: #333 !important;
			}
			.pcw-referral-feedback {
				margin-top: 8px;
				font-size: 12px;
			}
			.pcw-referral-feedback .pcw-valid {
				color: #059669;
				background: #ecfdf5;
				display: inline-block;
				padding: 6px 10px;
				border-radius: 4px;
			}
			.pcw-referral-feedback .pcw-invalid {
				color: #dc2626;
				background: #fef2f2;
				display: inline-block;
				padding: 6px 10px;
				border-radius: 4px;
			}
			.pcw-referral-feedback .pcw-loading {
				color: #6b7280;
			}
			.pcw-referral-readonly {
				background: #f0f7ff;
				border-color: #a5b4fc;
			}
			.pcw-referral-saved {
				display: flex;
				align-items: center;
				gap: 10px;
				flex-wrap: wrap;
			}
			.pcw-referral-saved .pcw-referral-code {
				font-weight: 600;
				font-size: 14px;
				color: #4f46e5;
				background: #fff;
				padding: 6px 12px;
				border-radius: 4px;
				letter-spacing: 1px;
				border: 1px solid #e0e7ff;
			}
			.pcw-referral-saved .pcw-referral-valid {
				color: #059669;
				font-size: 12px;
			}
		</style>

		<?php if ( 'pay_order' === $context ) : ?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			console.log('[PCW Referral] Iniciando posicionamento do campo (pay_order)...');
			
			var field = document.getElementById('pcw-referral-field-wrapper');
			if (!field) {
				console.log('[PCW Referral] ERRO: Campo não encontrado!');
				return;
			}
			console.log('[PCW Referral] Campo encontrado');

			// WC Smart Checkout: inserir após .order-totals
			var orderTotals = document.querySelector('.order-totals');
			console.log('[PCW Referral] Buscando .order-totals:', orderTotals ? 'encontrado' : 'não encontrado');
			if (orderTotals) {
				console.log('[PCW Referral] ✓ WC Smart Checkout detectado! Inserindo após .order-totals');
				orderTotals.parentNode.insertBefore(field, orderTotals.nextSibling);
				field.style.display = 'block';
				return;
			}

			// WC Smart Checkout: inserir após .smart-checkout-review-order
			var smartReview = document.querySelector('.smart-checkout-review-order');
			console.log('[PCW Referral] Buscando .smart-checkout-review-order:', smartReview ? 'encontrado' : 'não encontrado');
			if (smartReview) {
				console.log('[PCW Referral] ✓ WC Smart Checkout detectado! Inserindo após .smart-checkout-review-order');
				smartReview.parentNode.insertBefore(field, smartReview.nextSibling);
				field.style.display = 'block';
				return;
			}

			// Buscar por texto "Selecione" em headings e inserir ANTES
			var headings = document.querySelectorAll('h2, h3, h4, .order-pay-title');
			console.log('[PCW Referral] Headings encontrados:', headings.length);
			
			for (var i = 0; i < headings.length; i++) {
				var text = headings[i].textContent || '';
				if (text.indexOf('Selecione') !== -1 || text.indexOf('forma de pagamento') !== -1) {
					console.log('[PCW Referral] ✓ Match "Selecione"! Inserindo antes');
					headings[i].parentNode.insertBefore(field, headings[i]);
					field.style.display = 'block';
					return;
				}
			}

			// Fallback: inserir antes do #payment
			var payment = document.querySelector('#payment');
			if (payment) {
				console.log('[PCW Referral] ✓ Usando fallback #payment');
				payment.parentNode.insertBefore(field, payment);
				field.style.display = 'block';
				return;
			}

			// Fallback: inserir após shop_table
			var shopTable = document.querySelector('.shop_table, .woocommerce-checkout-review-order-table');
			if (shopTable) {
				console.log('[PCW Referral] ✓ Usando fallback shop_table');
				shopTable.parentNode.insertBefore(field, shopTable.nextSibling);
				field.style.display = 'block';
				return;
			}

			console.log('[PCW Referral] ⚠ Nenhum local encontrado!');
		});
		</script>
		<?php endif; ?>
		<?php
	}

	/**
	 * Validar campo no checkout
	 */
	public function validate_checkout_field() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$code = isset( $_POST['pcw_referral_code'] ) ? sanitize_text_field( wp_unslash( $_POST['pcw_referral_code'] ) ) : '';

		if ( empty( $code ) ) {
			return; // Campo opcional
		}

		$current_user_id = get_current_user_id();
		$validation = PCW_Referral_Codes::instance()->validate_code( $code, $current_user_id );

		if ( ! $validation['valid'] ) {
			wc_add_notice( $validation['message'], 'error' );
		}
	}

	/**
	 * Salvar código de indicação no pedido
	 *
	 * @param int $order_id ID do pedido.
	 */
	public function save_referral_code( $order_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$code = isset( $_POST['pcw_referral_code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['pcw_referral_code'] ) ) ) : '';

		if ( empty( $code ) ) {
			// Tentar do cookie
			$code = PCW_Referral_Tracking::instance()->get_cookie();
		}

		if ( empty( $code ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Validar código
		$validation = PCW_Referral_Codes::instance()->validate_code( $code, $order->get_user_id() );

		if ( ! $validation['valid'] ) {
			return;
		}

		// Salvar no pedido
		$order->update_meta_data( '_pcw_referral_code', $code );
		$order->update_meta_data( '_pcw_referral_code_owner', $validation['data']->user_id );
		$order->save();

		// Limpar cookie após uso
		PCW_Referral_Tracking::instance()->clear_cookie();

		do_action( 'pcw_referral_code_applied', $order_id, $code, $validation['data'] );
	}

	/**
	 * Salvar código na página pay_for_order
	 *
	 * @param WC_Order $order Pedido.
	 */
	public function save_pay_order_referral_code( $order ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$code = isset( $_POST['pcw_referral_code_pay'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['pcw_referral_code_pay'] ) ) ) : '';

		// Verificar se já tem código salvo
		$saved_code = $order->get_meta( '_pcw_referral_code' );
		if ( $saved_code ) {
			return; // Já tem código, não sobrescrever
		}

		if ( empty( $code ) ) {
			// Tentar do cookie
			$code = PCW_Referral_Tracking::instance()->get_cookie();
		}

		if ( empty( $code ) ) {
			return;
		}

		// Validar código
		$validation = PCW_Referral_Codes::instance()->validate_code( $code, $order->get_user_id() );

		if ( ! $validation['valid'] ) {
			return;
		}

		// Salvar no pedido
		$order->update_meta_data( '_pcw_referral_code', $code );
		$order->update_meta_data( '_pcw_referral_code_owner', $validation['data']->user_id );
		$order->save();

		// Limpar cookie após uso
		PCW_Referral_Tracking::instance()->clear_cookie();

		do_action( 'pcw_referral_code_applied', $order->get_id(), $code, $validation['data'] );
	}

	/**
	 * AJAX para validar código
	 */
	public function ajax_validate_code() {
		check_ajax_referer( 'pcw_referral_nonce', 'nonce' );

		$code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		if ( empty( $code ) ) {
			wp_send_json_error( array(
				'message' => __( 'Informe um código de indicação.', 'person-cash-wallet' ),
			) );
		}

		$current_user_id = get_current_user_id();
		$validation = PCW_Referral_Codes::instance()->validate_code( $code, $current_user_id );

		if ( $validation['valid'] ) {
			wp_send_json_success( array(
				'message'       => $validation['message'],
				'referrer_name' => $validation['referrer_name'],
			) );
		} else {
			wp_send_json_error( array(
				'message' => $validation['message'],
			) );
		}
	}

	/**
	 * Enfileirar scripts
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() && ! is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}

		wp_enqueue_script(
			'pcw-referral-checkout',
			PCW_PLUGIN_URL . 'assets/js/checkout-referral.js',
			array( 'jquery' ),
			PCW_VERSION,
			true
		);

		wp_localize_script( 'pcw-referral-checkout', 'pcwReferral', array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'pcw_referral_nonce' ),
			'validating'  => __( 'Validando...', 'person-cash-wallet' ),
			'validate'    => __( 'Validar', 'person-cash-wallet' ),
		) );
	}

	/**
	 * Salvar código de indicação do WooCommerce Blocks
	 *
	 * @param WC_Order        $order Pedido.
	 * @param WP_REST_Request $request Request.
	 */
	public function save_referral_from_blocks( $order, $request ) {
		$data = $request->get_param( 'extensions' );

		if ( ! isset( $data['pcw_referral'] ) ) {
			// Tentar do cookie
			$code = PCW_Referral_Tracking::instance()->get_cookie();

			if ( empty( $code ) ) {
				return;
			}
		} else {
			$code = sanitize_text_field( $data['pcw_referral']['code'] ?? '' );
		}

		if ( empty( $code ) ) {
			return;
		}

		$code = strtoupper( $code );

		// Validar código
		$validation = PCW_Referral_Codes::instance()->validate_code( $code, $order->get_user_id() );

		if ( ! $validation['valid'] ) {
			return;
		}

		// Salvar no pedido
		$order->update_meta_data( '_pcw_referral_code', $code );
		$order->update_meta_data( '_pcw_referral_code_owner', $validation['data']->user_id );

		// Limpar cookie após uso
		PCW_Referral_Tracking::instance()->clear_cookie();

		do_action( 'pcw_referral_code_applied', $order->get_id(), $code, $validation['data'] );
	}
}
