<?php
/**
 * Gateway de pagamento Wallet
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Verificar se WooCommerce está disponível
if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	return;
}

/**
 * Classe de gateway de pagamento Wallet
 */
class PCW_Payment_Gateway extends WC_Payment_Gateway {

	/**
	 * Construtor
	 */
	public function __construct() {
		$this->id                 = 'pcw_wallet';
		$this->icon               = '';
		$this->has_fields         = true;
		$this->method_title       = __( 'Wallet', 'person-cash-wallet' );
		$this->method_description = __( 'Permite que clientes paguem usando o saldo da wallet.', 'person-cash-wallet' );

		// Carregar configurações
		$this->init_form_fields();
		$this->init_settings();

		// Definir variáveis
		$this->title        = $this->get_option( 'title', __( 'Wallet', 'person-cash-wallet' ) );
		$this->description = $this->get_option( 'description', __( 'Use o saldo da sua wallet para pagar.', 'person-cash-wallet' ) );
		$this->enabled      = $this->get_option( 'enabled', 'yes' );

		// Ações
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_payment' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Inicializar campos do formulário
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Ativar/Desativar', 'person-cash-wallet' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ativar gateway Wallet', 'person-cash-wallet' ),
				'default' => 'yes',
			),
			'show_without_balance' => array(
				'title'   => __( 'Exibir sem saldo', 'person-cash-wallet' ),
				'type'    => 'checkbox',
				'label'   => __( 'Mostrar o método Wallet mesmo quando o cliente não tem saldo', 'person-cash-wallet' ),
				'default' => 'yes',
				'description' => __( 'Quando ativado, o método aparece no checkout, mas o cliente verá a mensagem de saldo insuficiente.', 'person-cash-wallet' ),
				'desc_tip' => true,
			),
			'title' => array(
				'title'       => __( 'Título', 'person-cash-wallet' ),
				'type'        => 'text',
				'description' => __( 'Título que o cliente verá durante o checkout.', 'person-cash-wallet' ),
				'default'     => __( 'Wallet', 'person-cash-wallet' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Descrição', 'person-cash-wallet' ),
				'type'        => 'textarea',
				'description' => __( 'Descrição que o cliente verá durante o checkout.', 'person-cash-wallet' ),
				'default'     => __( 'Use o saldo da sua wallet para pagar.', 'person-cash-wallet' ),
				'desc_tip'    => true,
			),
			'allow_partial' => array(
				'title'   => __( 'Permitir Pagamento Parcial', 'person-cash-wallet' ),
				'type'    => 'checkbox',
				'label'   => __( 'Permitir que clientes usem wallet parcialmente e paguem o restante com outro método', 'person-cash-wallet' ),
				'default' => 'yes',
			),
			'min_amount' => array(
				'title'       => __( 'Valor Mínimo', 'person-cash-wallet' ),
				'type'        => 'text',
				'description' => __( 'Valor mínimo do pedido para usar wallet. Deixe vazio para não aplicar limite.', 'person-cash-wallet' ),
				'default'     => '',
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Campos de pagamento no checkout
	 */
	public function payment_fields() {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Você precisa estar logado para usar a wallet.', 'person-cash-wallet' ) . '</p>';
			return;
		}

		// Verificar se já há desconto de wallet aplicado via seção de desconto
		$wallet_discount_applied = floatval( WC()->session->get( 'pcw_wallet_discount_amount', 0 ) );
		if ( $wallet_discount_applied > 0 ) {
			echo '<div class="pcw-wallet-payment-fields">';
			echo '<p class="pcw-wallet-info">';
			echo '<strong>' . esc_html__( 'Desconto Wallet já aplicado!', 'person-cash-wallet' ) . '</strong><br>';
			printf(
				/* translators: %s: discount amount */
				esc_html__( 'Você está usando %s do seu saldo como desconto. Pague o restante com este ou outro método de pagamento.', 'person-cash-wallet' ),
				wp_kses_post( wc_price( $wallet_discount_applied ) )
			);
			echo '</p>';
			echo '</div>';
			return;
		}

		$wallet = new PCW_Wallet();
		$balance = $wallet->get_balance();
		$cart_total_float = floatval( WC()->cart->get_total( 'edit' ) );

		$allow_partial = 'yes' === $this->get_option( 'allow_partial', 'yes' );
		$min_amount = $this->get_option( 'min_amount' );
		$min_amount_float = $min_amount ? floatval( $min_amount ) : 0;

		// Verificar valor mínimo
		if ( $min_amount_float > 0 && $cart_total_float < $min_amount_float ) {
			echo '<p class="pcw-wallet-error">' . sprintf(
				esc_html__( 'Valor mínimo do pedido para usar wallet: %s', 'person-cash-wallet' ),
				wp_kses_post( PCW_Formatters::format_money( $min_amount_float ) )
			) . '</p>';
			return;
		}

		if ( $balance <= 0 ) {
			echo '<p class="pcw-wallet-info">' . esc_html__( 'Você não possui saldo na wallet.', 'person-cash-wallet' ) . '</p>';
			return;
		}

		?>
		<div class="pcw-wallet-payment-fields">
			<p class="pcw-wallet-balance">
				<strong><?php esc_html_e( 'Saldo Disponível:', 'person-cash-wallet' ); ?></strong>
				<?php echo wp_kses_post( PCW_Formatters::format_money( $balance ) ); ?>
			</p>

			<?php if ( $balance >= $cart_total_float ) : ?>
				<p class="pcw-wallet-info">
					<?php esc_html_e( 'Você pode pagar o pedido integralmente com sua wallet.', 'person-cash-wallet' ); ?>
				</p>
				<input type="hidden" name="pcw_use_wallet_amount" id="pcw_use_wallet_amount" value="<?php echo esc_attr( $cart_total_float ); ?>">
			<?php elseif ( $allow_partial && $balance > 0 ) : ?>
				<p class="pcw-wallet-info">
					<?php esc_html_e( 'Você pode usar parte do saldo da wallet e pagar o restante com outro método.', 'person-cash-wallet' ); ?>
				</p>
				<p class="form-row form-row-wide">
					<label for="pcw_use_wallet_amount">
						<?php esc_html_e( 'Valor a usar da wallet:', 'person-cash-wallet' ); ?>
						<span class="pcw-remaining">
							(<?php esc_html_e( 'Restante:', 'person-cash-wallet' ); ?> 
							<span id="pcw_remaining_amount"><?php echo wp_kses_post( PCW_Formatters::format_money( $cart_total_float - $balance ) ); ?></span>)
						</span>
					</label>
					<input type="number" 
						name="pcw_use_wallet_amount" 
						id="pcw_use_wallet_amount" 
						step="0.01" 
						min="0.01" 
						max="<?php echo esc_attr( $balance ); ?>" 
						value="<?php echo esc_attr( $balance ); ?>"
						class="input-text">
				</p>
			<?php else : ?>
				<p class="pcw-wallet-error">
					<?php esc_html_e( 'Saldo insuficiente para usar wallet neste pedido.', 'person-cash-wallet' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var cartTotal = <?php echo esc_js( $cart_total_float ); ?>;
			var walletBalance = <?php echo esc_js( $balance ); ?>;

			$('#pcw_use_wallet_amount').on('input change', function() {
				var useAmount = parseFloat($(this).val()) || 0;
				var remaining = cartTotal - useAmount;
				
				if (remaining < 0) {
					remaining = 0;
					$(this).val(cartTotal);
					useAmount = cartTotal;
				}

				$('#pcw_remaining_amount').text('R$ ' + remaining.toFixed(2).replace('.', ','));
			});
		});
		</script>
		<?php
	}

	/**
	 * Validar pagamento
	 */
	public function validate_payment() {
		if ( 'pcw_wallet' !== $_POST['payment_method'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! is_user_logged_in() ) {
			wc_add_notice( __( 'Você precisa estar logado para usar a wallet.', 'person-cash-wallet' ), 'error' );
			return;
		}

		$wallet = new PCW_Wallet();
		$balance = $wallet->get_balance();

		if ( $balance <= 0 ) {
			wc_add_notice( __( 'Você não possui saldo na wallet.', 'person-cash-wallet' ), 'error' );
			return;
		}

		$use_amount = isset( $_POST['pcw_use_wallet_amount'] ) ? floatval( $_POST['pcw_use_wallet_amount'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $use_amount <= 0 ) {
			wc_add_notice( __( 'Valor inválido para usar da wallet.', 'person-cash-wallet' ), 'error' );
			return;
		}

		if ( $use_amount > $balance ) {
			wc_add_notice( __( 'Você não possui saldo suficiente na wallet.', 'person-cash-wallet' ), 'error' );
			return;
		}

		$cart_total = floatval( WC()->cart->get_total( 'edit' ) );

		if ( $use_amount > $cart_total ) {
			wc_add_notice( __( 'Valor a usar da wallet não pode ser maior que o total do pedido.', 'person-cash-wallet' ), 'error' );
			return;
		}
	}

	/**
	 * Processar pagamento
	 *
	 * @param int $order_id ID do pedido.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}

		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			wc_add_notice( __( 'Erro ao processar pagamento com wallet.', 'person-cash-wallet' ), 'error' );
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}

		// Verificar se já há desconto de wallet aplicado via seção de desconto
		$wallet_discount_applied = floatval( WC()->session->get( 'pcw_wallet_discount_amount', 0 ) );
		$order_total = (float) $order->get_total();

		// Se já tem desconto aplicado e o total é zero ou quase zero
		if ( $wallet_discount_applied > 0 && $order_total < 0.01 ) {
			// O desconto já foi processado pelo PCW_Wallet_Discount
			// Apenas completar o pedido
			$order->payment_complete();
			$order->set_payment_method( $this->id );
			$order->set_payment_method_title( __( 'Wallet (Desconto integral)', 'person-cash-wallet' ) );
			$order->save();

			// Limpar carrinho
			WC()->cart->empty_cart();

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}

		$wallet = new PCW_Wallet( $user_id );
		$balance = $wallet->get_balance();

		$use_amount = isset( $_POST['pcw_use_wallet_amount'] ) ? floatval( $_POST['pcw_use_wallet_amount'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Ajustar valor se necessário
		if ( $use_amount > $balance ) {
			$use_amount = $balance;
		}

		if ( $use_amount > $order_total ) {
			$use_amount = $order_total;
		}

		if ( $use_amount <= 0 ) {
			wc_add_notice( __( 'Valor inválido para usar da wallet.', 'person-cash-wallet' ), 'error' );
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}

		// Debitar da wallet
		$transaction_id = $wallet->debit(
			$use_amount,
			'purchase',
			sprintf( __( 'Pagamento do pedido #%d', 'person-cash-wallet' ), $order_id ),
			$order_id
		);

		if ( ! $transaction_id ) {
			wc_add_notice( __( 'Erro ao debitar da wallet. Verifique seu saldo.', 'person-cash-wallet' ), 'error' );
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}

		// Salvar informações no pedido
		$balance_after = $wallet->get_balance();
		$order->update_meta_data( '_pcw_wallet_balance_before', $balance );
		$order->update_meta_data( '_pcw_wallet_used', $use_amount );
		$order->update_meta_data( '_pcw_wallet_balance_after', $balance_after );
		$order->update_meta_data( '_pcw_wallet_transaction_id', $transaction_id );

		$remaining = $order_total - $use_amount;

		if ( $remaining > 0.01 ) {
			// Pagamento parcial
			$order->update_meta_data( '_pcw_wallet_used', $use_amount );
			$order->update_meta_data( '_pcw_wallet_remaining', $remaining );
			
			// Criar fee negativo para aplicar desconto
			$item = new WC_Order_Item_Fee();
			$item->set_name( __( 'Desconto Wallet', 'person-cash-wallet' ) );
			$item->set_amount( -$use_amount );
			$item->set_total( -$use_amount );
			$order->add_item( $item );
			
			$order->calculate_totals();
			$order->set_payment_method( $this->id );
			$order->set_payment_method_title( sprintf( __( 'Wallet (R$ %s) - Pagamento parcial', 'person-cash-wallet' ), number_format( $use_amount, 2, ',', '.' ) ) );
			$order->update_status( 'on-hold', __( 'Pagamento parcial com wallet. Aguardando pagamento do restante.', 'person-cash-wallet' ) );
			$order->save();

			// Limpar carrinho
			WC()->cart->empty_cart();

			// Disparar ação
			do_action( 'pcw_wallet_partial_payment', $order_id, $user_id, $use_amount, $remaining );

			// Nota: O cliente precisará pagar o restante manualmente ou via outro gateway
			// Por enquanto, marcamos como on-hold
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		} else {
			// Pagamento integral
			$order->set_total( 0 );
			$order->payment_complete();
			$order->set_payment_method( $this->id );
			$order->set_payment_method_title( __( 'Wallet', 'person-cash-wallet' ) );
			$order->save();

			// Limpar carrinho
			WC()->cart->empty_cart();

			// Disparar ação
			do_action( 'pcw_wallet_payment_complete', $order_id, $user_id, $use_amount );

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}
	}

	/**
	 * Enfileirar scripts
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		wp_add_inline_style( 'woocommerce-general', '
			.pcw-wallet-payment-fields {
				margin: 15px 0;
				padding: 15px;
				background: #f5f5f5;
				border-radius: 5px;
			}
			.pcw-wallet-balance {
				font-size: 1.2em;
				margin-bottom: 10px;
			}
			.pcw-wallet-info {
				color: #0073aa;
				margin: 10px 0;
			}
			.pcw-wallet-error {
				color: #dc3232;
				margin: 10px 0;
			}
			#pcw_use_wallet_amount {
				width: 100%;
				max-width: 200px;
			}
			.pcw-remaining {
				font-size: 0.9em;
				color: #666;
			}
		' );
	}

	/**
	 * Verificar se gateway está disponível
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Verificar se WC()->session e WC()->cart estão disponíveis
		// (não estão no contexto do admin/editor de blocos)
		if ( is_null( WC()->session ) || is_null( WC()->cart ) ) {
			return parent::is_available();
		}

		// Se já há desconto de wallet aplicado, ainda permitir selecionar o gateway
		// para que o cliente veja a mensagem informativa
		$wallet_discount_applied = floatval( WC()->session->get( 'pcw_wallet_discount_amount', 0 ) );
		if ( $wallet_discount_applied > 0 ) {
			// Verificar se o total restante é zero ou quase zero
			$cart_total = (float) WC()->cart->get_total( 'edit' );
			if ( $cart_total < 0.01 ) {
				// Total zerado, pode pagar só com wallet
				return true;
			}
			// Tem restante para pagar - ainda mostra o gateway para informar
			return true;
		}

		$wallet = new PCW_Wallet();
		$balance = $wallet->get_balance();

		$show_without_balance = 'yes' === $this->get_option( 'show_without_balance', 'yes' );
		if ( $balance <= 0 && ! $show_without_balance ) {
			return false;
		}

		$min_amount = $this->get_option( 'min_amount' );
		if ( $min_amount ) {
			$cart_total = (float) WC()->cart->get_total( 'edit' );
			if ( $cart_total < floatval( $min_amount ) ) {
				return false;
			}
		}

		return parent::is_available();
	}
}
