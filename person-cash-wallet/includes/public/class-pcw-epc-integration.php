<?php
/**
 * Integração com Easy Product Creator
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de integração EPC
 */
class PCW_EPC_Integration {

	/**
	 * Configurações
	 *
	 * @var array
	 */
	private $settings;
	/**
	 * Regra aplicada no cálculo
	 *
	 * @var object|null
	 */
	private $last_rule;

	/**
	 * Inicializar
	 */
	public function init() {
		// Verificar se EPC está ativo
		if ( ! $this->is_epc_active() ) {
			$this->log_debug( 'EPC não ativo, integração ignorada' );
			return;
		}

		$this->settings = wp_parse_args(
			get_option( 'pcw_display_settings', array() ),
			$this->get_default_settings()
		);

		// Adicionar cashback via JavaScript para produtos EPC
		add_action( 'wp_footer', array( $this, 'inject_cashback_display' ) );
		
		// Enqueue scripts necessários
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Log de debug (PHP)
	 *
	 * @param string $message Mensagem.
	 * @param array  $context Contexto extra.
	 */
	private function log_debug( $message, $context = array() ) {
		$payload = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		$log_message = '[PCW EPC Integration] ' . $message . $payload;

		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->info( $log_message, array( 'source' => 'person-cash-wallet' ) );
		} else {
			error_log( $log_message );
		}
	}

	/**
	 * Verificar se EPC está ativo
	 *
	 * @return bool
	 */
	private function is_epc_active() {
		return defined( 'EPC_VERSION' ) || class_exists( 'EPC_Frontend' );
	}

	/**
	 * Verificar se é produto EPC
	 *
	 * @return bool
	 */
	private function is_epc_product() {
		if ( ! is_product() ) {
			return false;
		}

		global $product;
		if ( ! $product ) {
			$product = wc_get_product( get_the_ID() );
		}

		if ( ! $product ) {
			$this->log_debug( 'is_epc_product: produto não encontrado' );
			return false;
		}

		// Verificar meta do EPC
		$is_epc = $product->get_meta( '_is_epc_product' ) === 'yes';
		
		// Verificar processos de fabricação
		if ( ! $is_epc ) {
			$processos = get_post_meta( $product->get_id(), '_processos_fabricacao', true );
			$is_epc = ! empty( $processos );
		}

		// Verificar tiers de preço (EPC)
		if ( ! $is_epc ) {
			$tiers = get_post_meta( $product->get_id(), '_price_tiers', true );
			$is_epc = ! empty( $tiers ) && is_array( $tiers );
			if ( $is_epc ) {
				$this->log_debug( 'is_epc_product: detectado via _price_tiers', array( 'tiers_count' => count( $tiers ) ) );
			}
		}

		$this->log_debug( 'is_epc_product: resultado', array( 'product_id' => $product->get_id(), 'is_epc' => $is_epc ) );
		return $is_epc;
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts() {
		if ( ! $this->is_epc_product() ) {
			$this->log_debug( 'enqueue_scripts: não é EPC, ignorado' );
			return;
		}

		// CSS já foi encarregado pelo PCW_Cashback_Display
		// Só precisamos do JS
		wp_enqueue_script(
			'pcw-epc-integration',
			PCW_PLUGIN_URL . 'assets/js/epc-integration.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);
	}

	/**
	 * Injetar exibição de cashback via JavaScript
	 */
	public function inject_cashback_display() {
		if ( ! $this->is_epc_product() ) {
			$this->log_debug( 'inject_cashback_display: não é EPC, ignorado' );
			return;
		}

		// Verificar se exibição está ativada
		if ( empty( $this->settings['product']['enabled'] ) || 'yes' !== $this->settings['product']['enabled'] ) {
			$this->log_debug( 'inject_cashback_display: exibição desativada' );
			return;
		}

		global $product;
		if ( ! $product ) {
			$product = wc_get_product( get_the_ID() );
		}

		if ( ! $product ) {
			$this->log_debug( 'inject_cashback_display: produto não encontrado' );
			return;
		}

		// Calcular cashback
		$cashback_amount = $this->calculate_cashback( $product );
		$this->log_debug( 'inject_cashback_display: cashback calculado', array( 'product_id' => $product->get_id(), 'amount' => $cashback_amount ) );

		if ( $cashback_amount <= 0 ) {
			$this->log_debug( 'inject_cashback_display: cashback <= 0', array( 'product_id' => $product->get_id() ) );
			return;
		}

		// Obter configurações
		$config = $this->settings['product'];
		$layout = ! empty( $config['layout'] ) ? $config['layout'] : 'banner';
		$text_template = ! empty( $config['text'] ) ? $config['text'] : __( 'Ganhe {amount} de cashback!', 'person-cash-wallet' );
		$epc_position = ! empty( $config['epc_position'] ) ? $config['epc_position'] : 'auto';
		$icon = ! empty( $config['icon'] ) ? $config['icon'] : '💰';
		$bg_color = ! empty( $config['bg_color'] ) ? $config['bg_color'] : '#f0f6fc';
		$text_color = ! empty( $config['text_color'] ) ? $config['text_color'] : '#0073aa';

		// Substituir variáveis
		$formatted_amount = PCW_Formatters::format_money_plain( $cashback_amount );
		$text = str_replace( '{amount}', $formatted_amount, $text_template );

		// Buscar percentual se necessário
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

		// Gerar HTML
		$rule_type = $this->last_rule ? $this->last_rule->type : '';
		$rule_value = $this->last_rule ? $this->last_rule->value : '';
		$rule_min = $this->last_rule ? $this->last_rule->min_order_amount : '';
		$rule_max = $this->last_rule ? $this->last_rule->max_cashback_amount : '';

		$html = $this->generate_html( $layout, $text, $icon, $bg_color, $text_color, $text_template, $rule_type, $rule_value, $rule_min, $rule_max );

		// Passar para JavaScript
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			console.log('[PCW EPC] Página EPC detectada');
			console.log('[PCW EPC] Cashback calculado:', <?php echo wp_json_encode( $cashback_amount ); ?>);
			var epcPosition = <?php echo wp_json_encode( $epc_position ); ?>;
			// Aguardar carregamento completo
			setTimeout(function() {
				if (window.__pcwCashbackInserted) {
					console.log('[PCW EPC] Já inserido, ignorando duplicidade');
					return;
				}

				var cashbackHtml = <?php echo wp_json_encode( $html ); ?>;
				var inserted = false;

				if (epcPosition === 'summary_above') {
					var $summary = $('.epc-summary-card').first();
					if ($summary.length) {
						var wrapper = '<div class="pcw-epc-summary-slot" style="width:100%;box-sizing:border-box;margin-bottom:16px;">' + cashbackHtml + '</div>';
						$summary.before(wrapper);
						inserted = true;
						console.log('[PCW EPC] Inserido acima do resumo');
					}
				}

				if (!inserted && epcPosition === 'title_after') {
					var $title = $('.epc-product-title-section').first();
					if ($title.length) {
						$title.after(cashbackHtml);
						inserted = true;
						console.log('[PCW EPC] Inserido abaixo do título');
					}
				}

				if (!inserted) {
				// Tentar múltiplos seletores EPC
				var selectors = [
					'.epc-product-title-section',
					'.epc-single-product',
					'.epc-product-layout',
					'.epc-configuration',
					'.product-configurator',
					'#product-configurator',
					'.woocommerce-product-details',
					'.summary.entry-summary',
					'.product',
					'.type-product',
					'article.product'
				];

					// Verificar qual seletor existe
					for (var i = 0; i < selectors.length; i++) {
						var $target = $(selectors[i]).first();
						if ($target.length > 0) {
							// Inserção específica para seção de título EPC
							if ($target.hasClass('epc-product-title-section')) {
								$target.append(cashbackHtml);
							} else {
								$target.prepend(cashbackHtml);
							}
							inserted = true;
							console.log('[PCW EPC] Cashback inserido via seletor: ' + selectors[i]);
							break;
						}
					}
				}

				// Fallback: inserir antes do título se nada funcionou
				if (!inserted) {
					var $title = $('h1.product_title, h1.entry-title, .product-title').first();
					if ($title.length > 0) {
						$title.before(cashbackHtml);
						inserted = true;
						console.log('[PCW EPC] Cashback inserido antes do título');
					}
				}

				// Último fallback: inserir no body
				if (!inserted) {
					$('body').prepend(cashbackHtml);
					console.log('[PCW EPC] Cashback inserido no body (fallback)');
				}

				window.__pcwCashbackInserted = true;
				// Animar entrada
				$('.pcw-cashback-epc-inject').hide().fadeIn(500);
				console.log('[PCW EPC] Finalizado. Inserido:', inserted);

				// Atualização dinâmica por mudança no total
				var $cashback = $('.pcw-cashback-epc-inject').first();
				if ($cashback.length) {
					var template = $cashback.data('pcwTemplate');
					var ruleType = $cashback.data('pcwRuleType');
					var ruleValue = parseFloat($cashback.data('pcwRuleValue')) || 0;
					var ruleMin = parseFloat($cashback.data('pcwRuleMin')) || 0;
					var ruleMax = parseFloat($cashback.data('pcwRuleMax')) || 0;

					var parseMoney = function(text) {
						if (!text) return 0;
						// Remove tudo que não for número, ponto ou vírgula
						text = text.replace(/[^\d.,-]/g, '');
						// Se tem vírgula, assume formato BRL
						if (text.indexOf(',') !== -1) {
							text = text.replace(/\./g, '').replace(',', '.');
						}
						var value = parseFloat(text);
						return isNaN(value) ? 0 : value;
					};

					var formatMoney = function(value) {
						try {
							return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
						} catch (e) {
							return 'R$ ' + value.toFixed(2).replace('.', ',');
						}
					};

					var updateCashback = function() {
						var totalText = $('#total-price').text();
						var total = parseMoney(totalText);
						if (total <= 0) {
							$cashback.hide();
							return;
						}

						if (ruleMin > 0 && total < ruleMin) {
							$cashback.hide();
							return;
						}

						var amount = 0;
						if (ruleType === 'percentage') {
							amount = (total * ruleValue) / 100;
						} else if (ruleType === 'fixed') {
							amount = ruleValue;
						}

						if (ruleMax > 0 && amount > ruleMax) {
							amount = ruleMax;
						}

						if (amount <= 0) {
							$cashback.hide();
							return;
						}

						$cashback.show();
						var text = template.replace('{amount}', formatMoney(amount));
						$cashback.find('.pcw-cashback-text').text(text);
					};

					updateCashback();

					var totalNode = document.getElementById('total-price');
					if (totalNode) {
						var observer = new MutationObserver(function() {
							updateCashback();
						});
						observer.observe(totalNode, { childList: true, characterData: true, subtree: true });
					}
				}
			}, 500);
		});
		</script>
		<?php
	}

	/**
	 * Calcular cashback
	 *
	 * @param WC_Product $product Produto.
	 * @return float
	 */
	private function calculate_cashback( $product ) {
		if ( ! $product || ! is_object( $product ) ) {
			return 0;
		}

		$price = $this->get_product_base_price( $product );
		$this->log_debug( 'calculate_cashback: preço base', array( 'product_id' => $product->get_id(), 'price' => $price ) );

		if ( $price <= 0 ) {
			return 0;
		}

		$rules = PCW_Cashback_Rules::get_active_rules();
		$this->log_debug( 'calculate_cashback: regras ativas', array( 'count' => count( $rules ) ) );

		if ( empty( $rules ) ) {
			return 0;
		}

		$cashback_amount = 0;

		foreach ( $rules as $rule ) {
			if ( ! empty( $rule->min_order_amount ) && $price < $rule->min_order_amount ) {
				$this->log_debug( 'calculate_cashback: regra ignorada por min_order_amount', array( 'rule_id' => $rule->id, 'min_order_amount' => $rule->min_order_amount ) );
				continue;
			}

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

			if ( $cashback_amount > 0 ) {
				$this->last_rule = $rule;
				break;
			}
		}

		$this->log_debug( 'calculate_cashback: valor final', array( 'amount' => $cashback_amount ) );
		return $cashback_amount;
	}

	/**
	 * Obter preço base do produto EPC
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
	 * Gerar HTML do cashback
	 *
	 * @param string $layout Layout.
	 * @param string $text Texto.
	 * @param string $icon Ícone.
	 * @param string $bg_color Cor de fundo.
	 * @param string $text_color Cor do texto.
	 * @return string
	 */
	private function generate_html( $layout, $text, $icon, $bg_color, $text_color, $template = '', $rule_type = '', $rule_value = '', $rule_min = '', $rule_max = '' ) {
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
				$styles[] = 'width: 100%';
				$styles[] = 'box-sizing: border-box';
				break;

			case 'box':
				$styles[] = 'padding: 16px';
				$styles[] = 'border: 2px solid ' . esc_attr( $text_color ) . '30';
				$styles[] = 'border-radius: 8px';
				$styles[] = 'font-weight: 600';
				$styles[] = 'width: 100%';
				$styles[] = 'box-sizing: border-box';
				break;

			case 'inline':
				$styles[] = 'font-weight: 600';
				$styles[] = 'display: inline-block';
				$styles[] = 'margin: 10px 0';
				break;
		}

		$styles[] = 'background: ' . esc_attr( $bg_color );
		$styles[] = 'color: ' . esc_attr( $text_color );
		$styles[] = 'margin: 15px 0';
		$styles[] = 'clear: both';

		$style_attr = implode( '; ', $styles );

		$html = '<div class="pcw-cashback-display pcw-cashback-epc-inject ' . esc_attr( $layout ) . '"' .
			' data-pcw-template="' . esc_attr( $template ) . '"' .
			' data-pcw-rule-type="' . esc_attr( $rule_type ) . '"' .
			' data-pcw-rule-value="' . esc_attr( $rule_value ) . '"' .
			' data-pcw-rule-min="' . esc_attr( $rule_min ) . '"' .
			' data-pcw-rule-max="' . esc_attr( $rule_max ) . '"' .
			'>';
		$html .= '<div class="pcw-cashback-content" style="' . esc_attr( $style_attr ) . '">';
		
		if ( ! empty( $icon ) ) {
			$html .= '<span class="pcw-cashback-icon" style="margin-right: 8px;">' . esc_html( $icon ) . '</span>';
		}
		
		$html .= '<span class="pcw-cashback-text">' . esc_html( $text ) . '</span>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Configurações padrão
	 *
	 * @return array
	 */
	private function get_default_settings() {
		return array(
			'product' => array(
				'enabled'    => 'yes',
				'position'   => 'woocommerce_before_add_to_cart_form',
				'priority'   => 10,
				'layout'     => 'banner',
				'text'       => __( 'Ganhe {amount} de cashback nesta compra!', 'person-cash-wallet' ),
				'icon'       => '💰',
				'bg_color'   => '#f0f6fc',
				'text_color' => '#0073aa',
			),
		);
	}
}
