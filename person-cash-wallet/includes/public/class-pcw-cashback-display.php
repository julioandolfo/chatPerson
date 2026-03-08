<?php
/**
 * Exibição de cashback no frontend
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de exibição de cashback
 */
class PCW_Cashback_Display {

	/**
	 * Configurações
	 *
	 * @var array
	 */
	private $settings;
	/**
	 * Flag para evitar render duplicado na página do produto
	 *
	 * @var bool
	 */
	private $product_display_rendered = false;

	/**
	 * Inicializar
	 */
	public function init() {
		$this->settings = wp_parse_args(
			get_option( 'pcw_display_settings', array() ),
			$this->get_default_settings()
		);

		// Hooks para página do produto
		if ( ! empty( $this->settings['product']['enabled'] ) && 'yes' === $this->settings['product']['enabled'] ) {
			$position = ! empty( $this->settings['product']['position'] ) ? $this->settings['product']['position'] : 'woocommerce_before_add_to_cart_form';
			$priority = ! empty( $this->settings['product']['priority'] ) ? $this->settings['product']['priority'] : 10;
			add_action( $position, array( $this, 'display_product_cashback' ), $priority );
			// Fallback para temas que não disparam o hook escolhido
			add_action( 'woocommerce_after_single_product', array( $this, 'display_product_cashback_fallback' ), 5 );
			// Fallback JS para temas que ignoram hooks WooCommerce
			add_action( 'wp_footer', array( $this, 'inject_product_cashback_js' ), 5 );
		}

		// Hooks para carrinho
		if ( ! empty( $this->settings['cart']['enabled'] ) && 'yes' === $this->settings['cart']['enabled'] ) {
			$position = ! empty( $this->settings['cart']['position'] ) ? $this->settings['cart']['position'] : 'woocommerce_cart_totals_after_order_total';
			$priority = ! empty( $this->settings['cart']['priority'] ) ? $this->settings['cart']['priority'] : 10;
			add_action( $position, array( $this, 'display_cart_cashback' ), $priority );
		}

		// Hooks para checkout
		if ( ! empty( $this->settings['checkout']['enabled'] ) && 'yes' === $this->settings['checkout']['enabled'] ) {
			$position = ! empty( $this->settings['checkout']['position'] ) ? $this->settings['checkout']['position'] : 'woocommerce_review_order_after_order_total';
			$priority = ! empty( $this->settings['checkout']['priority'] ) ? $this->settings['checkout']['priority'] : 10;
			add_action( $position, array( $this, 'display_checkout_cashback' ), $priority );
		}

		// Hook para página order-pay (pagamento de pedido pendente)
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_order_pay_cashback' ), 10 );

		// Enqueue CSS no frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Log de debug (PHP)
	 *
	 * @param string $message Mensagem.
	 * @param array  $context Contexto extra.
	 */
	private function log_debug( $message, $context = array() ) {
		$payload = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		$log_message = '[PCW Cashback Display] ' . $message . $payload;

		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->info( $log_message, array( 'source' => 'person-cash-wallet' ) );
		} else {
			error_log( $log_message );
		}
	}

	/**
	 * Enqueue styles
	 */
	public function enqueue_styles() {
		if ( ! is_product() && ! is_cart() && ! is_checkout() && ! is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}

		wp_enqueue_style( 
			'pcw-cashback-display',
			PCW_PLUGIN_URL . 'assets/css/public-cashback.css',
			array(),
			'1.0.0'
		);
	}

	/**
	 * Exibir cashback na página do produto
	 */
	public function display_product_cashback() {
		global $product;
		$this->log_debug( 'display_product_cashback: start', array( 'is_product' => is_product() ) );

		// Verificar se produto existe
		if ( ! $product ) {
			$product = wc_get_product( get_the_ID() );
		}

		// Verificar se é um produto válido
		if ( ! $product || ! is_object( $product ) ) {
			$this->log_debug( 'display_product_cashback: produto inválido' );
			return;
		}

		// Evitar duplicidade em produtos EPC
		if ( $this->is_epc_product( $product ) ) {
			$this->log_debug( 'display_product_cashback: produto EPC, ignorando para evitar duplicidade' );
			return;
		}

		// Verificar se produto pode ser comprado
		if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			$this->log_debug( 'display_product_cashback: produto não comprável ou sem estoque', array( 'purchasable' => $product->is_purchasable(), 'in_stock' => $product->is_in_stock() ) );
			return;
		}

		// Calcular cashback
		$cashback_amount = $this->calculate_product_cashback( $product );
		$this->log_debug( 'display_product_cashback: cashback calculado', array( 'product_id' => $product->get_id(), 'amount' => $cashback_amount ) );

		if ( $cashback_amount <= 0 ) {
			return;
		}

		$config = ! empty( $this->settings['product'] ) ? $this->settings['product'] : array();
		$this->render_cashback_display( $cashback_amount, $config, 'product' );
		$this->product_display_rendered = true;
		$this->log_debug( 'display_product_cashback: renderizado', array( 'product_id' => $product->get_id() ) );
	}

	/**
	 * Fallback para exibir cashback se hook principal não for disparado
	 */
	public function display_product_cashback_fallback() {
		if ( $this->product_display_rendered || ! is_product() ) {
			return;
		}

		$this->display_product_cashback();
	}

	/**
	 * Fallback JS para inserir cashback na página do produto
	 */
	public function inject_product_cashback_js() {
		if ( $this->product_display_rendered || ! is_product() ) {
			return;
		}

		global $product;
		if ( ! $product ) {
			$product = wc_get_product( get_the_ID() );
		}

		if ( ! $product ) {
			$this->log_debug( 'inject_product_cashback_js: produto não encontrado' );
			return;
		}

		if ( $this->is_epc_product( $product ) ) {
			$this->log_debug( 'inject_product_cashback_js: produto EPC, ignorando para evitar duplicidade' );
			return;
		}

		$price = $this->get_product_base_price( $product );
		$cashback_amount = $this->calculate_product_cashback( $product );
		$rules_debug = $this->get_rules_debug();

		if ( $cashback_amount <= 0 ) {
			$this->log_debug( 'inject_product_cashback_js: cashback <= 0', array( 'product_id' => $product->get_id() ) );
			?>
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				console.log('[PCW] JS fallback ativo');
				console.log('[PCW] Produto:', <?php echo wp_json_encode( array( 'id' => $product->get_id(), 'price' => $price ) ); ?>);
				console.log('[PCW] Cashback calculado:', <?php echo wp_json_encode( $cashback_amount ); ?>);
				console.log('[PCW] Regras:', <?php echo wp_json_encode( $rules_debug ); ?>);
			});
			</script>
			<?php
			return;
		}

		$config = ! empty( $this->settings['product'] ) ? $this->settings['product'] : array();
		$html = $this->get_cashback_html( $cashback_amount, $config, 'product' );
		if ( empty( $html ) ) {
			$this->log_debug( 'inject_product_cashback_js: html vazio' );
			return;
		}

		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			console.log('[PCW] JS fallback ativo na página de produto');
			console.log('[PCW] Produto:', <?php echo wp_json_encode( array( 'id' => $product->get_id(), 'price' => $price ) ); ?>);
			console.log('[PCW] Cashback calculado:', <?php echo wp_json_encode( $cashback_amount ); ?>);
			console.log('[PCW] Regras:', <?php echo wp_json_encode( $rules_debug ); ?>);
			if ($('.pcw-cashback-display.pcw-cashback-product').length) {
				console.log('[PCW] Elemento já existe, ignorando JS fallback');
				return;
			}

			var cashbackHtml = <?php echo wp_json_encode( $html ); ?>;
			var inserted = false;

			var selectors = [
				'.summary.entry-summary',
				'.product .summary',
				'.woocommerce-product-details__short-description',
				'.epc-product-title-section',
				'h1.product_title',
				'h1.entry-title'
			];

			var isEpcDom = $('.epc-single-product, .epc-product-title-section, .epc-product-layout').length > 0;
			console.log('[PCW] EPC DOM detectado:', isEpcDom);
			if (!isEpcDom) {
				console.log('[PCW] EPC DOM não detectado, não inserindo via JS fallback');
				return;
			}

			for (var i = 0; i < selectors.length; i++) {
				var $target = $(selectors[i]).first();
				if ($target.length) {
					if ($target.hasClass('epc-product-title-section')) {
						$target.append(cashbackHtml);
					} else if ($target.is('h1')) {
						$target.after(cashbackHtml);
					} else {
						$target.prepend(cashbackHtml);
					}
					inserted = true;
					console.log('[PCW] Inserido via seletor:', selectors[i]);
					break;
				}
			}

			if (!inserted) {
				$('body').prepend(cashbackHtml);
				console.log('[PCW] Inserido no body (fallback)');
			}
		});
		</script>
		<?php
	}

	/**
	 * Exibir cashback no carrinho
	 */
	public function display_cart_cashback() {
		if ( ! WC()->cart ) {
			return;
		}

		$cashback_amount = $this->calculate_cart_cashback();

		if ( $cashback_amount <= 0 ) {
			return;
		}

		$config = ! empty( $this->settings['cart'] ) ? $this->settings['cart'] : array();
		
		// Layout especial para table_row
		if ( isset( $config['layout'] ) && 'table_row' === $config['layout'] ) {
			$this->render_table_row_display( $cashback_amount, $config, 'cart' );
		} else {
			$this->render_cashback_display( $cashback_amount, $config, 'cart' );
		}
	}

	/**
	 * Exibir cashback no checkout
	 */
	public function display_checkout_cashback() {
		if ( ! WC()->cart ) {
			return;
		}

		$cashback_amount = $this->calculate_cart_cashback();

		if ( $cashback_amount <= 0 ) {
			return;
		}

		$config = ! empty( $this->settings['checkout'] ) ? $this->settings['checkout'] : array();
		
		// Layout especial para table_row
		if ( isset( $config['layout'] ) && 'table_row' === $config['layout'] ) {
			$this->render_table_row_display( $cashback_amount, $config, 'checkout' );
		} else {
			$this->render_cashback_display( $cashback_amount, $config, 'checkout' );
		}
	}

	/**
	 * Exibir cashback na página order-pay
	 *
	 * @param WC_Order $order Pedido WooCommerce.
	 */
	public function display_order_pay_cashback( $order ) {
		// Verificar se estamos na página order-pay
		if ( ! is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}

		// Verificar se pedido existe
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		// Verificar se cashback já foi processado para este pedido
		$cashback_processed = $order->get_meta( '_pcw_cashback_processed' );
		if ( $cashback_processed ) {
			return; // Não mostrar se já foi processado
		}

		// Calcular cashback do pedido
		$cashback_amount = $this->calculate_order_cashback( $order );

		if ( $cashback_amount <= 0 ) {
			return;
		}

		// Renderizar exibição
		$this->render_order_pay_cashback_display( $cashback_amount, $order );
	}

	/**
	 * Calcular cashback de um pedido
	 *
	 * @param WC_Order $order Pedido WooCommerce.
	 * @return float
	 */
	private function calculate_order_cashback( $order ) {
		$order_total = floatval( $order->get_total() );

		if ( $order_total <= 0 ) {
			return 0;
		}

		// Buscar regras de cashback ativas
		$rules = PCW_Cashback_Rules::get_active_rules();

		if ( empty( $rules ) ) {
			return 0;
		}

		$cashback_amount = 0;

		// Usar primeira regra ativa que se aplica
		foreach ( $rules as $rule ) {
			// Verificar se há valor mínimo de pedido
			if ( ! empty( $rule->min_order_amount ) && $order_total < floatval( $rule->min_order_amount ) ) {
				continue;
			}

			// Verificar se regra se aplica ao pedido (categorias, produtos, roles)
			if ( ! PCW_Cashback_Rules::rule_applies( $order, $rule ) ) {
				continue;
			}

			// Calcular cashback baseado no tipo
			if ( 'percentage' === $rule->type ) {
				$cashback_amount = ( $order_total * floatval( $rule->value ) ) / 100;

				// Aplicar limite máximo apenas se for > 0
				$max_cashback = floatval( $rule->max_cashback_amount );
				if ( $max_cashback > 0 && $cashback_amount > $max_cashback ) {
					$cashback_amount = $max_cashback;
				}
			} else {
				$cashback_amount = floatval( $rule->value );
			}

			// Se encontrou uma regra válida, retornar
			if ( $cashback_amount > 0 ) {
				break;
			}
		}

		return round( $cashback_amount, 2 );
	}

	/**
	 * Renderizar exibição de cashback na página order-pay
	 *
	 * @param float    $amount Valor do cashback.
	 * @param WC_Order $order  Pedido WooCommerce.
	 */
	private function render_order_pay_cashback_display( $amount, $order ) {
		$formatted_amount = PCW_Formatters::format_money_plain( $amount );

		// Buscar configurações do checkout para manter consistência
		$config = ! empty( $this->settings['checkout'] ) ? $this->settings['checkout'] : array();
		$icon = ! empty( $config['icon'] ) ? $config['icon'] : '🎁';
		?>
		<div class="pcw-order-pay-cashback" style="
			background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
			border: 2px solid #22c55e;
			border-radius: 12px;
			padding: 20px;
			margin: 20px 0;
			text-align: center;
		">
			<div style="
				font-size: 40px;
				margin-bottom: 10px;
			"><?php echo esc_html( $icon ); ?></div>
			
			<h3 style="
				color: #15803d;
				margin: 0 0 8px 0;
				font-size: 18px;
				font-weight: 600;
			"><?php esc_html_e( 'Cashback que você vai ganhar', 'person-cash-wallet' ); ?></h3>
			
			<p style="
				font-size: 28px;
				font-weight: 700;
				color: #16a34a;
				margin: 0 0 10px 0;
			"><?php echo esc_html( $formatted_amount ); ?></p>
			
			<p style="
				color: #166534;
				font-size: 14px;
				margin: 0;
			">
				<?php
				printf(
					/* translators: %s: formatted cashback amount */
					esc_html__( 'Ao pagar este pedido, você receberá %s em cashback para usar em compras futuras!', 'person-cash-wallet' ),
					'<strong>' . esc_html( $formatted_amount ) . '</strong>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Calcular cashback do produto
	 *
	 * @param WC_Product $product Produto.
	 * @return float
	 */
	private function calculate_product_cashback( $product ) {
		// Verificar se produto é válido
		if ( ! $product || ! is_object( $product ) ) {
			return 0;
		}

		// Obter preço do produto (com fallback para EPC)
		$price = $this->get_product_base_price( $product );
		$this->log_debug( 'calculate_product_cashback: preço base', array( 'product_id' => $product->get_id(), 'price' => $price ) );

		// Verificar se produto tem preço válido
		if ( $price <= 0 ) {
			return 0;
		}

		// Buscar regras de cashback ativas
		$rules = PCW_Cashback_Rules::get_active_rules();
		$this->log_debug( 'calculate_product_cashback: regras ativas', array( 'count' => count( $rules ) ) );

		if ( empty( $rules ) ) {
			return 0;
		}

		$cashback_amount = 0;

		// Usar primeira regra ativa que se aplica
		foreach ( $rules as $rule ) {
			$this->log_debug( 'calculate_product_cashback: avaliando regra', array(
				'rule_id'            => $rule->id,
				'type'               => $rule->type,
				'value'              => $rule->value,
				'min_order_amount'   => $rule->min_order_amount,
				'max_cashback_amount' => $rule->max_cashback_amount,
			) );

			// Verificar se há valor mínimo de pedido
			if ( ! empty( $rule->min_order_amount ) && $price < $rule->min_order_amount ) {
				$this->log_debug( 'calculate_product_cashback: regra ignorada por min_order_amount', array( 'rule_id' => $rule->id, 'min_order_amount' => $rule->min_order_amount ) );
				continue;
			}

			// Calcular cashback baseado no tipo
			if ( 'percentage' === $rule->type ) {
				$cashback_amount = ( $price * floatval( $rule->value ) ) / 100;

				// Aplicar limite máximo apenas se for > 0
				$max_cashback = floatval( $rule->max_cashback_amount );
				if ( $max_cashback > 0 && $cashback_amount > $max_cashback ) {
					$cashback_amount = $max_cashback;
				}
			} else {
				$cashback_amount = floatval( $rule->value );
			}

			// Se encontrou uma regra válida, retornar
			if ( $cashback_amount > 0 ) {
				break;
			}
		}

		$this->log_debug( 'calculate_product_cashback: valor final', array( 'amount' => $cashback_amount ) );
		return $cashback_amount;
	}

	/**
	 * Obter preço base do produto com fallback para EPC
	 *
	 * @param WC_Product $product Produto.
	 * @return float
	 */
	private function get_product_base_price( $product ) {
		$price = floatval( $product->get_price() );

		if ( $price > 0 ) {
			$this->log_debug( 'get_product_base_price: usando get_price', array( 'price' => $price ) );
			return $price;
		}

		// Fallback para produtos EPC (usam tiers de preço)
		$tiers = get_post_meta( $product->get_id(), '_price_tiers', true );
		if ( ! empty( $tiers ) && is_array( $tiers ) ) {
			$best_index = get_post_meta( $product->get_id(), '_melhor_opcao_tier', true );

			if ( '' !== $best_index && isset( $tiers[ $best_index ]['preco'] ) ) {
				$price = floatval( $tiers[ $best_index ]['preco'] );
				$this->log_debug( 'get_product_base_price: usando melhor tier', array( 'best_index' => $best_index, 'price' => $price ) );
			} elseif ( isset( $tiers[0]['preco'] ) ) {
				$price = floatval( $tiers[0]['preco'] );
				$this->log_debug( 'get_product_base_price: usando primeiro tier', array( 'price' => $price ) );
			}
		}

		if ( $price <= 0 ) {
			$price = floatval( $product->get_regular_price() );
			$this->log_debug( 'get_product_base_price: usando regular_price', array( 'price' => $price ) );
		}

		return $price;
	}

	/**
	 * Verificar se produto é EPC
	 *
	 * @param WC_Product $product Produto.
	 * @return bool
	 */
	private function is_epc_product( $product ) {
		if ( ! $product || ! is_object( $product ) ) {
			return false;
		}

		$is_epc = $product->get_meta( '_is_epc_product' ) === 'yes';

		if ( ! $is_epc ) {
			$processos = get_post_meta( $product->get_id(), '_processos_fabricacao', true );
			$is_epc = ! empty( $processos );
		}

		if ( ! $is_epc ) {
			$tiers = get_post_meta( $product->get_id(), '_price_tiers', true );
			$is_epc = ! empty( $tiers ) && is_array( $tiers );
		}

		return $is_epc;
	}

	/**
	 * Gerar debug das regras ativas
	 *
	 * @return array
	 */
	private function get_rules_debug() {
		$rules = PCW_Cashback_Rules::get_active_rules();
		$debug = array();

		foreach ( $rules as $rule ) {
			$debug[] = array(
				'id'               => $rule->id,
				'type'             => $rule->type,
				'value'            => $rule->value,
				'min_order_amount' => $rule->min_order_amount,
				'max_cashback'     => $rule->max_cashback_amount,
				'status'           => $rule->status,
			);
		}

		return $debug;
	}

	/**
	 * Calcular cashback do carrinho
	 *
	 * @return float
	 */
	private function calculate_cart_cashback() {
		// Verificar se carrinho existe e não está vazio
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return 0;
		}

		// Obter subtotal do carrinho
		$total = floatval( WC()->cart->get_subtotal() );

		if ( $total <= 0 ) {
			return 0;
		}

		// Buscar regras de cashback ativas
		$rules = PCW_Cashback_Rules::get_active_rules();

		if ( empty( $rules ) ) {
			return 0;
		}

		$cashback_amount = 0;

		// Usar primeira regra ativa que se aplica
		foreach ( $rules as $rule ) {
			// Verificar se há valor mínimo de pedido
			if ( ! empty( $rule->min_order_amount ) && $total < $rule->min_order_amount ) {
				continue;
			}

			// Calcular cashback baseado no tipo
			if ( 'percentage' === $rule->type ) {
				$cashback_amount = ( $total * floatval( $rule->value ) ) / 100;

				// Aplicar limite máximo apenas se for > 0
				$max_cashback = floatval( $rule->max_cashback_amount );
				if ( $max_cashback > 0 && $cashback_amount > $max_cashback ) {
					$cashback_amount = $max_cashback;
				}
			} else {
				$cashback_amount = floatval( $rule->value );
			}

			// Se encontrou uma regra válida, retornar
			if ( $cashback_amount > 0 ) {
				break;
			}
		}

		return $cashback_amount;
	}

	/**
	 * Renderizar exibição de cashback
	 *
	 * @param float  $amount Valor do cashback.
	 * @param array  $config Configurações.
	 * @param string $context Contexto (product, cart, checkout).
	 */
	private function render_cashback_display( $amount, $config, $context ) {
		echo $this->get_cashback_html( $amount, $config, $context );
	}

	/**
	 * Renderizar como linha de tabela
	 *
	 * @param float  $amount Valor do cashback.
	 * @param array  $config Configurações.
	 * @param string $context Contexto.
	 */
	private function render_table_row_display( $amount, $config, $context ) {
		$text = ! empty( $config['text'] ) ? $config['text'] : __( 'Cashback', 'person-cash-wallet' );
		$icon = ! empty( $config['icon'] ) ? $config['icon'] : '💰';
		$text_color = ! empty( $config['text_color'] ) ? $config['text_color'] : '#00a32a';

		// Substituir variáveis
		$formatted_amount = PCW_Formatters::format_money_plain( $amount );
		$display_text = str_replace( '{amount}', $formatted_amount, $text );

		?>
		<tr class="pcw-cashback-display table_row pcw-cashback-<?php echo esc_attr( $context ); ?>">
			<td style="color: <?php echo esc_attr( $text_color ); ?>; font-weight: 600;">
				<?php if ( ! empty( $icon ) ) : ?>
					<span class="pcw-cashback-icon"><?php echo esc_html( $icon ); ?></span>
				<?php endif; ?>
				<?php esc_html_e( 'Cashback a ganhar', 'person-cash-wallet' ); ?>
			</td>
			<td style="color: <?php echo esc_attr( $text_color ); ?>; font-weight: 700; text-align: right;">
				<?php echo esc_html( $formatted_amount ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Gerar HTML da exibição de cashback
	 *
	 * @param float  $amount Valor do cashback.
	 * @param array  $config Configurações.
	 * @param string $context Contexto.
	 * @return string
	 */
	private function get_cashback_html( $amount, $config, $context ) {
		$layout = ! empty( $config['layout'] ) ? $config['layout'] : 'banner';
		$text = ! empty( $config['text'] ) ? $config['text'] : __( 'Ganhe {amount} de cashback!', 'person-cash-wallet' );
		$icon = ! empty( $config['icon'] ) ? $config['icon'] : '💰';
		$bg_color = ! empty( $config['bg_color'] ) ? $config['bg_color'] : '#f0f6fc';
		$text_color = ! empty( $config['text_color'] ) ? $config['text_color'] : '#0073aa';

		// Substituir variáveis
		$formatted_amount = PCW_Formatters::format_money_plain( $amount );
		$text = str_replace( '{amount}', $formatted_amount, $text );

		// Calcular percentual se aplicável
		if ( strpos( $text, '{percentage}' ) !== false ) {
			$rules = PCW_Cashback_Rules::get_active_rules();
			$percentage = '';
			foreach ( $rules as $rule ) {
				if ( 'percentage' === $rule->type ) {
					$percentage = number_format( $rule->value, 1 ) . '%';
					break;
				}
			}
			$text = str_replace( '{percentage}', $percentage, $text );
		}

		// Estilos inline
		$styles = array();

		switch ( $layout ) {
			case 'badge':
				$styles[] = 'padding: 8px 16px';
				$styles[] = 'border-radius: 20px';
				$styles[] = 'font-size: 14px';
				$styles[] = 'font-weight: 600';
				$styles[] = 'display: inline-block';
				break;

			case 'banner':
				$styles[] = 'padding: 20px';
				$styles[] = 'text-align: center';
				$styles[] = 'font-size: 18px';
				$styles[] = 'font-weight: 700';
				$styles[] = 'border-radius: 8px';
				break;

			case 'box':
				$styles[] = 'padding: 16px';
				$styles[] = 'border: 2px solid ' . $text_color . '30';
				$styles[] = 'border-radius: 8px';
				$styles[] = 'font-weight: 600';
				break;

			case 'inline':
				$styles[] = 'font-weight: 600';
				$styles[] = 'display: inline-block';
				$styles[] = 'margin: 10px 0';
				break;
		}

		$styles[] = 'background: ' . esc_attr( $bg_color );
		$styles[] = 'color: ' . esc_attr( $text_color );

		$style_attr = implode( '; ', $styles );

		ob_start();
		?>
		<div class="pcw-cashback-display <?php echo esc_attr( $layout ); ?> pcw-cashback-<?php echo esc_attr( $context ); ?>">
			<div class="pcw-cashback-content" style="<?php echo esc_attr( $style_attr ); ?>">
				<?php if ( ! empty( $icon ) ) : ?>
					<span class="pcw-cashback-icon"><?php echo esc_html( $icon ); ?></span>
				<?php endif; ?>
				<span class="pcw-cashback-text"><?php echo esc_html( $text ); ?></span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Configurações padrão
	 *
	 * @return array
	 */
	private function get_default_settings() {
		return array(
			'product'  => array(
				'enabled'    => 'yes',
				'position'   => 'woocommerce_before_add_to_cart_form',
				'priority'   => 10,
				'layout'     => 'banner',
				'text'       => __( 'Ganhe {amount} de cashback nesta compra!', 'person-cash-wallet' ),
				'icon'       => '💰',
				'bg_color'   => '#f0f6fc',
				'text_color' => '#0073aa',
			),
			'cart'     => array(
				'enabled'    => 'yes',
				'position'   => 'woocommerce_cart_totals_after_order_total',
				'priority'   => 10,
				'layout'     => 'box',
				'text'       => __( 'Você vai ganhar {amount} de cashback neste pedido!', 'person-cash-wallet' ),
				'icon'       => '🎁',
				'bg_color'   => '#f0fdf4',
				'text_color' => '#00a32a',
			),
			'checkout' => array(
				'enabled'    => 'yes',
				'position'   => 'woocommerce_review_order_after_order_total',
				'priority'   => 10,
				'layout'     => 'banner',
				'text'       => __( 'Você vai ganhar {amount} de cashback nesta compra! 🎉', 'person-cash-wallet' ),
				'icon'       => '🎉',
				'bg_color'   => '#fef3c7',
				'text_color' => '#92400e',
			),
		);
	}
}
