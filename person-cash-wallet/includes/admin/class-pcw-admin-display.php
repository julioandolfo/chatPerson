<?php
/**
 * Configurações de exibição de cashback
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe admin display
 */
class PCW_Admin_Display {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_pcw_save_display_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Adicionar menu
	 */
	public function add_menu() {
		// Menu oculto - acessível via Configurações > Exibição
		add_submenu_page(
			null, // Oculto do menu
			__( 'Exibição', 'person-cash-wallet' ),
			__( 'Exibição', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-display',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'growly-digital_page_pcw-display' !== $hook && 'person-cash-wallet_page_pcw-display' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		$settings = get_option( 'pcw_display_settings', $this->get_default_settings() );

		?>
		<div class="wrap">
			<!-- Page Header -->
			<div class="pcw-page-header">
				<div>
					<h1>
						<span class="dashicons dashicons-visibility"></span>
						<?php esc_html_e( 'Configurações de Exibição', 'person-cash-wallet' ); ?>
					</h1>
					<p class="description"><?php esc_html_e( 'Configure como e onde o cashback será exibido para os clientes', 'person-cash-wallet' ); ?></p>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'pcw_save_display_settings', 'pcw_nonce' ); ?>
				<input type="hidden" name="action" value="pcw_save_display_settings">

				<!-- Página do Produto -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-products"></span>
							<?php esc_html_e( 'Página do Produto', 'person-cash-wallet' ); ?>
						</h2>
						<label class="switch">
							<input type="checkbox" name="product_enabled" value="yes" <?php checked( $settings['product']['enabled'], 'yes' ); ?>>
							<span class="slider"></span>
						</label>
					</div>
					<div class="pcw-card-body">
						<div style="padding: 16px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px; margin-bottom: 20px;">
							<strong><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'Dica:', 'person-cash-wallet' ); ?></strong>
							<?php esc_html_e( 'Mostre ao cliente quanto de cashback ele vai ganhar ao comprar o produto. Isso incentiva a conversão!', 'person-cash-wallet' ); ?>
						</div>

						<table class="form-table">
							<tr>
								<th style="width: 200px;"><label for="product_position"><?php esc_html_e( 'Posição', 'person-cash-wallet' ); ?></label></th>
								<td>
									<select id="product_position" name="product_position" class="pcw-form-input" style="width: 100%; max-width: 400px;">
										<option value="woocommerce_before_add_to_cart_form" <?php selected( $settings['product']['position'], 'woocommerce_before_add_to_cart_form' ); ?>><?php esc_html_e( 'Antes do formulário de compra', 'person-cash-wallet' ); ?></option>
										<option value="woocommerce_after_add_to_cart_form" <?php selected( $settings['product']['position'], 'woocommerce_after_add_to_cart_form' ); ?>><?php esc_html_e( 'Depois do formulário de compra', 'person-cash-wallet' ); ?></option>
										<option value="woocommerce_single_product_summary" <?php selected( $settings['product']['position'], 'woocommerce_single_product_summary' ); ?>><?php esc_html_e( 'No resumo do produto', 'person-cash-wallet' ); ?></option>
										<option value="woocommerce_before_single_product_summary" <?php selected( $settings['product']['position'], 'woocommerce_before_single_product_summary' ); ?>><?php esc_html_e( 'Antes do resumo', 'person-cash-wallet' ); ?></option>
										<option value="woocommerce_after_single_product_summary" <?php selected( $settings['product']['position'], 'woocommerce_after_single_product_summary' ); ?>><?php esc_html_e( 'Depois do resumo', 'person-cash-wallet' ); ?></option>
									</select>
									<p class="description">
										<span class="dashicons dashicons-admin-site"></span>
										<?php esc_html_e( 'Onde a informação de cashback aparecerá na página do produto', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th style="width: 200px;"><label for="product_epc_position"><?php esc_html_e( 'Posição no EPC', 'person-cash-wallet' ); ?></label></th>
								<td>
									<select id="product_epc_position" name="product_epc_position" class="pcw-form-input" style="width: 100%; max-width: 400px;">
										<option value="auto" <?php selected( $settings['product']['epc_position'], 'auto' ); ?>><?php esc_html_e( 'Automático', 'person-cash-wallet' ); ?></option>
										<option value="summary_above" <?php selected( $settings['product']['epc_position'], 'summary_above' ); ?>><?php esc_html_e( 'Acima do resumo (mesma largura)', 'person-cash-wallet' ); ?></option>
										<option value="title_after" <?php selected( $settings['product']['epc_position'], 'title_after' ); ?>><?php esc_html_e( 'Abaixo do título', 'person-cash-wallet' ); ?></option>
									</select>
									<p class="description">
										<span class="dashicons dashicons-info"></span>
										<?php esc_html_e( 'Define a posição específica para produtos do Easy Product Creator', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th><label for="product_priority"><?php esc_html_e( 'Prioridade', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="number" id="product_priority" name="product_priority" value="<?php echo esc_attr( $settings['product']['priority'] ); ?>" min="1" max="999" class="pcw-form-input" style="width: 100px;">
									<p class="description">
										<span class="dashicons dashicons-sort"></span>
										<?php esc_html_e( 'Ordem de exibição (10 = padrão, menor = primeiro)', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th><label for="product_layout"><?php esc_html_e( 'Layout', 'person-cash-wallet' ); ?></label></th>
								<td>
									<select id="product_layout" name="product_layout" class="pcw-form-input" style="width: 100%; max-width: 400px;">
										<option value="badge" <?php selected( $settings['product']['layout'], 'badge' ); ?>>🏷️ <?php esc_html_e( 'Badge (Pequeno e discreto)', 'person-cash-wallet' ); ?></option>
										<option value="banner" <?php selected( $settings['product']['layout'], 'banner' ); ?>>📢 <?php esc_html_e( 'Banner (Destaque grande)', 'person-cash-wallet' ); ?></option>
										<option value="box" <?php selected( $settings['product']['layout'], 'box' ); ?>>📦 <?php esc_html_e( 'Box (Caixa com borda)', 'person-cash-wallet' ); ?></option>
										<option value="inline" <?php selected( $settings['product']['layout'], 'inline' ); ?>>➡️ <?php esc_html_e( 'Inline (Texto simples)', 'person-cash-wallet' ); ?></option>
									</select>
									<p class="description">
										<span class="dashicons dashicons-format-image"></span>
										<?php esc_html_e( 'Estilo visual da exibição do cashback', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th><label for="product_text"><?php esc_html_e( 'Texto Personalizado', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="text" id="product_text" name="product_text" value="<?php echo esc_attr( $settings['product']['text'] ); ?>" class="pcw-form-input" style="width: 100%;" placeholder="<?php esc_attr_e( 'Ganhe {amount} de cashback nesta compra!', 'person-cash-wallet' ); ?>">
									<p class="description">
										<span class="dashicons dashicons-info"></span>
										<?php esc_html_e( 'Variáveis disponíveis:', 'person-cash-wallet' ); ?> 
										<code>{amount}</code> (valor do cashback), <code>{percentage}</code> (% se aplicável)
									</p>
								</td>
							</tr>
							<tr>
								<th><label for="product_icon"><?php esc_html_e( 'Ícone/Emoji', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="text" id="product_icon" name="product_icon" value="<?php echo esc_attr( $settings['product']['icon'] ); ?>" class="pcw-form-input" style="width: 150px;" placeholder="💰">
									<p class="description">
										<span class="dashicons dashicons-smiley"></span>
										<?php esc_html_e( 'Emoji ou texto que aparece antes do valor. Ex: 💰, 🎁, ⭐', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th><label for="product_bg_color"><?php esc_html_e( 'Cor de Fundo', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="text" id="product_bg_color" name="product_bg_color" value="<?php echo esc_attr( $settings['product']['bg_color'] ); ?>" class="pcw-color-picker">
									<p class="description">
										<span class="dashicons dashicons-art"></span>
										<?php esc_html_e( 'Cor de fundo do elemento', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th><label for="product_text_color"><?php esc_html_e( 'Cor do Texto', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="text" id="product_text_color" name="product_text_color" value="<?php echo esc_attr( $settings['product']['text_color'] ); ?>" class="pcw-color-picker">
									<p class="description">
										<span class="dashicons dashicons-editor-textcolor"></span>
										<?php esc_html_e( 'Cor do texto', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
						</table>

						<!-- Preview -->
						<div style="margin-top: 20px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
							<h4 style="margin-top: 0;"><?php esc_html_e( 'Preview:', 'person-cash-wallet' ); ?></h4>
							<div id="product-preview" style="display: inline-block;"></div>
						</div>
					</div>
				</div>

				<!-- Carrinho -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-cart"></span>
							<?php esc_html_e( 'Carrinho', 'person-cash-wallet' ); ?>
						</h2>
						<label class="switch">
							<input type="checkbox" name="cart_enabled" value="yes" <?php checked( $settings['cart']['enabled'], 'yes' ); ?>>
							<span class="slider"></span>
						</label>
					</div>
					<div class="pcw-card-body">
						<table class="form-table">
							<tr>
								<th style="width: 200px;"><label for="cart_position"><?php esc_html_e( 'Posição', 'person-cash-wallet' ); ?></label></th>
								<td>
									<select id="cart_position" name="cart_position" class="pcw-form-input" style="width: 100%; max-width: 400px;">
										<option value="woocommerce_before_cart" <?php selected( $settings['cart']['position'], 'woocommerce_before_cart' ); ?>><?php esc_html_e( 'Antes do carrinho', 'person-cash-wallet' ); ?></option>
										<option value="woocommerce_before_cart_table" <?php selected( $settings['cart']['position'], 'woocommerce_before_cart_table' ); ?>><?php esc_html_e( 'Antes da tabela', 'person-cash-wallet' ); ?></option>
										<option value="woocommerce_cart_contents" <?php selected( $settings['cart']['position'], 'woocommerce_cart_contents' ); ?>><?php esc_html_e( 'Dentro da tabela', 'person-cash-wallet' ); ?></option>
										<option value="woocommerce_after_cart_table" <?php selected( $settings['cart']['position'], 'woocommerce_after_cart_table' ); ?>><?php esc_html_e( 'Depois da tabela', 'person-cash-wallet' ); ?></option>
										<option value="woocommerce_cart_totals_before_order_total" <?php selected( $settings['cart']['position'], 'woocommerce_cart_totals_before_order_total' ); ?>><?php esc_html_e( 'No total (antes)', 'person-cash-wallet' ); ?></option>
										<option value="woocommerce_cart_totals_after_order_total" <?php selected( $settings['cart']['position'], 'woocommerce_cart_totals_after_order_total' ); ?>><?php esc_html_e( 'No total (depois)', 'person-cash-wallet' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th><label for="cart_priority"><?php esc_html_e( 'Prioridade', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="number" id="cart_priority" name="cart_priority" value="<?php echo esc_attr( $settings['cart']['priority'] ); ?>" min="1" max="999" class="pcw-form-input" style="width: 100px;">
								</td>
							</tr>
							<tr>
								<th><label for="cart_layout"><?php esc_html_e( 'Layout', 'person-cash-wallet' ); ?></label></th>
								<td>
									<select id="cart_layout" name="cart_layout" class="pcw-form-input" style="width: 100%; max-width: 400px;">
										<option value="badge" <?php selected( $settings['cart']['layout'], 'badge' ); ?>>🏷️ <?php esc_html_e( 'Badge', 'person-cash-wallet' ); ?></option>
										<option value="banner" <?php selected( $settings['cart']['layout'], 'banner' ); ?>>📢 <?php esc_html_e( 'Banner', 'person-cash-wallet' ); ?></option>
										<option value="box" <?php selected( $settings['cart']['layout'], 'box' ); ?>>📦 <?php esc_html_e( 'Box', 'person-cash-wallet' ); ?></option>
										<option value="inline" <?php selected( $settings['cart']['layout'], 'inline' ); ?>>➡️ <?php esc_html_e( 'Inline', 'person-cash-wallet' ); ?></option>
										<option value="table_row" <?php selected( $settings['cart']['layout'], 'table_row' ); ?>>📋 <?php esc_html_e( 'Linha da Tabela', 'person-cash-wallet' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th><label for="cart_text"><?php esc_html_e( 'Texto Personalizado', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="text" id="cart_text" name="cart_text" value="<?php echo esc_attr( $settings['cart']['text'] ); ?>" class="pcw-form-input" style="width: 100%;" placeholder="<?php esc_attr_e( 'Você vai ganhar {amount} de cashback neste pedido!', 'person-cash-wallet' ); ?>">
								</td>
							</tr>
							<tr>
								<th><label for="cart_icon"><?php esc_html_e( 'Ícone/Emoji', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="text" id="cart_icon" name="cart_icon" value="<?php echo esc_attr( $settings['cart']['icon'] ); ?>" class="pcw-form-input" style="width: 150px;" placeholder="🎁">
								</td>
							</tr>
							<tr>
								<th><label for="cart_bg_color"><?php esc_html_e( 'Cor de Fundo', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="text" id="cart_bg_color" name="cart_bg_color" value="<?php echo esc_attr( $settings['cart']['bg_color'] ); ?>" class="pcw-color-picker">
								</td>
							</tr>
							<tr>
								<th><label for="cart_text_color"><?php esc_html_e( 'Cor do Texto', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="text" id="cart_text_color" name="cart_text_color" value="<?php echo esc_attr( $settings['cart']['text_color'] ); ?>" class="pcw-color-picker">
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Checkout -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-yes"></span>
							<?php esc_html_e( 'Checkout', 'person-cash-wallet' ); ?>
						</h2>
						<label class="switch">
							<input type="checkbox" name="checkout_enabled" value="yes" <?php checked( $settings['checkout']['enabled'], 'yes' ); ?>>
							<span class="slider"></span>
						</label>
					</div>
					<div class="pcw-card-body">
						<div style="padding: 16px; background: #fff3cd; border-left: 4px solid #dba617; border-radius: 4px; margin-bottom: 20px;">
							<strong><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'Integração com WC Smart Checkout:', 'person-cash-wallet' ); ?></strong>
							<?php esc_html_e( 'As configurações abaixo se integram automaticamente com o checkout customizado.', 'person-cash-wallet' ); ?>
						</div>

						<table class="form-table">
							<tr>
								<th style="width: 200px;"><label for="checkout_position"><?php esc_html_e( 'Posição', 'person-cash-wallet' ); ?></label></th>
								<td>
									<select id="checkout_position" name="checkout_position" class="pcw-form-input" style="width: 100%; max-width: 400px;">
										<option value="woocommerce_before_checkout_form" <?php selected( $settings['checkout']['position'], 'woocommerce_before_checkout_form' ); ?>><?php esc_html_e( 'Antes do formulário', 'person-cash-wallet' ); ?></option>
										<option value="woocommerce_checkout_before_customer_details" <?php selected( $settings['checkout']['position'], 'woocommerce_checkout_before_customer_details' ); ?>><?php esc_html_e( 'Antes dos dados do cliente', 'person-cash-wallet' ); ?></option>
										<option value="woocommerce_checkout_after_customer_details" <?php selected( $settings['checkout']['position'], 'woocommerce_checkout_after_customer_details' ); ?>><?php esc_html_e( 'Depois dos dados do cliente', 'person-cash-wallet' ); ?></option>
										<option value="woocommerce_review_order_before_payment" <?php selected( $settings['checkout']['position'], 'woocommerce_review_order_before_payment' ); ?>><?php esc_html_e( 'Antes do pagamento', 'person-cash-wallet' ); ?></option>
										<option value="woocommerce_review_order_after_payment" <?php selected( $settings['checkout']['position'], 'woocommerce_review_order_after_payment' ); ?>><?php esc_html_e( 'Depois do pagamento', 'person-cash-wallet' ); ?></option>
										<option value="woocommerce_review_order_before_order_total" <?php selected( $settings['checkout']['position'], 'woocommerce_review_order_before_order_total' ); ?>><?php esc_html_e( 'No resumo (antes do total)', 'person-cash-wallet' ); ?></option>
										<option value="woocommerce_review_order_after_order_total" <?php selected( $settings['checkout']['position'], 'woocommerce_review_order_after_order_total' ); ?>><?php esc_html_e( 'No resumo (depois do total)', 'person-cash-wallet' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th><label for="checkout_priority"><?php esc_html_e( 'Prioridade', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="number" id="checkout_priority" name="checkout_priority" value="<?php echo esc_attr( $settings['checkout']['priority'] ); ?>" min="1" max="999" class="pcw-form-input" style="width: 100px;">
								</td>
							</tr>
							<tr>
								<th><label for="checkout_layout"><?php esc_html_e( 'Layout', 'person-cash-wallet' ); ?></label></th>
								<td>
									<select id="checkout_layout" name="checkout_layout" class="pcw-form-input" style="width: 100%; max-width: 400px;">
										<option value="badge" <?php selected( $settings['checkout']['layout'], 'badge' ); ?>>🏷️ <?php esc_html_e( 'Badge', 'person-cash-wallet' ); ?></option>
										<option value="banner" <?php selected( $settings['checkout']['layout'], 'banner' ); ?>>📢 <?php esc_html_e( 'Banner', 'person-cash-wallet' ); ?></option>
										<option value="box" <?php selected( $settings['checkout']['layout'], 'box' ); ?>>📦 <?php esc_html_e( 'Box', 'person-cash-wallet' ); ?></option>
										<option value="inline" <?php selected( $settings['checkout']['layout'], 'inline' ); ?>>➡️ <?php esc_html_e( 'Inline', 'person-cash-wallet' ); ?></option>
										<option value="table_row" <?php selected( $settings['checkout']['layout'], 'table_row' ); ?>>📋 <?php esc_html_e( 'Linha da Tabela', 'person-cash-wallet' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th><label for="checkout_text"><?php esc_html_e( 'Texto Personalizado', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="text" id="checkout_text" name="checkout_text" value="<?php echo esc_attr( $settings['checkout']['text'] ); ?>" class="pcw-form-input" style="width: 100%;" placeholder="<?php esc_attr_e( 'Você vai ganhar {amount} de cashback nesta compra! 🎉', 'person-cash-wallet' ); ?>">
								</td>
							</tr>
							<tr>
								<th><label for="checkout_icon"><?php esc_html_e( 'Ícone/Emoji', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="text" id="checkout_icon" name="checkout_icon" value="<?php echo esc_attr( $settings['checkout']['icon'] ); ?>" class="pcw-form-input" style="width: 150px;" placeholder="🎉">
								</td>
							</tr>
							<tr>
								<th><label for="checkout_bg_color"><?php esc_html_e( 'Cor de Fundo', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="text" id="checkout_bg_color" name="checkout_bg_color" value="<?php echo esc_attr( $settings['checkout']['bg_color'] ); ?>" class="pcw-color-picker">
								</td>
							</tr>
							<tr>
								<th><label for="checkout_text_color"><?php esc_html_e( 'Cor do Texto', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="text" id="checkout_text_color" name="checkout_text_color" value="<?php echo esc_attr( $settings['checkout']['text_color'] ); ?>" class="pcw-color-picker">
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Botões de Ação -->
				<div style="display: flex; gap: 12px; margin-bottom: 20px;">
					<button type="submit" class="button pcw-button-primary pcw-button-icon">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e( 'Salvar Configurações', 'person-cash-wallet' ); ?>
					</button>
				</div>
			</form>
		</div>

		<!-- CSS -->
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

		<!-- JavaScript -->
		<script>
		jQuery(document).ready(function($) {
			// Inicializar color pickers
			$('.pcw-color-picker').wpColorPicker();

			// Preview em tempo real
			function updatePreview() {
				var layout = $('#product_layout').val();
				var text = $('#product_text').val() || 'Ganhe {amount} de cashback nesta compra!';
				var icon = $('#product_icon').val() || '💰';
				var bgColor = $('#product_bg_color').val() || '#f0f6fc';
				var textColor = $('#product_text_color').val() || '#0073aa';
				
				text = text.replace('{amount}', 'R$ 50,00').replace('{percentage}', '5%');

				var html = '';
				switch(layout) {
					case 'badge':
						html = '<span style="display: inline-block; padding: 8px 16px; background: ' + bgColor + '; color: ' + textColor + '; border-radius: 20px; font-size: 14px; font-weight: 600;">' + icon + ' ' + text + '</span>';
						break;
					case 'banner':
						html = '<div style="padding: 20px; background: ' + bgColor + '; color: ' + textColor + '; text-align: center; font-size: 18px; font-weight: 700; border-radius: 8px;">' + icon + ' ' + text + '</div>';
						break;
					case 'box':
						html = '<div style="padding: 16px; background: ' + bgColor + '; color: ' + textColor + '; border: 2px solid ' + textColor + '30; border-radius: 8px; font-weight: 600;">' + icon + ' ' + text + '</div>';
						break;
					case 'inline':
						html = '<p style="color: ' + textColor + '; font-weight: 600; margin: 10px 0;">' + icon + ' ' + text + '</p>';
						break;
				}

				$('#product-preview').html(html);
			}

			$('#product_layout, #product_text, #product_icon').on('change keyup', updatePreview);
			$('#product_bg_color, #product_text_color').on('change', function() {
				setTimeout(updatePreview, 100);
			});

			updatePreview();
		});
		</script>
		<?php
	}

	/**
	 * Obter configurações padrão
	 *
	 * @return array
	 */
	private function get_default_settings() {
		return array(
			'product' => array(
				'enabled'    => 'yes',
				'position'   => 'woocommerce_before_add_to_cart_form',
				'epc_position' => 'auto',
				'priority'   => 10,
				'layout'     => 'banner',
				'text'       => __( 'Ganhe {amount} de cashback nesta compra!', 'person-cash-wallet' ),
				'icon'       => '💰',
				'bg_color'   => '#f0f6fc',
				'text_color' => '#0073aa',
			),
			'cart' => array(
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

	/**
	 * Salvar configurações
	 */
	public function handle_save_settings() {
		// Verificar nonce
		if ( ! isset( $_POST['pcw_nonce'] ) || ! wp_verify_nonce( $_POST['pcw_nonce'], 'pcw_save_display_settings' ) ) {
			wp_die( esc_html__( 'Ação não autorizada.', 'person-cash-wallet' ) );
		}

		// Verificar capability
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'person-cash-wallet' ) );
		}

		$settings = array(
			'product' => array(
				'enabled'    => isset( $_POST['product_enabled'] ) ? 'yes' : 'no',
				'position'   => isset( $_POST['product_position'] ) ? sanitize_text_field( $_POST['product_position'] ) : 'woocommerce_before_add_to_cart_form',
				'epc_position' => isset( $_POST['product_epc_position'] ) ? sanitize_text_field( $_POST['product_epc_position'] ) : 'auto',
				'priority'   => isset( $_POST['product_priority'] ) ? absint( $_POST['product_priority'] ) : 10,
				'layout'     => isset( $_POST['product_layout'] ) ? sanitize_text_field( $_POST['product_layout'] ) : 'banner',
				'text'       => isset( $_POST['product_text'] ) ? sanitize_text_field( $_POST['product_text'] ) : '',
				'icon'       => isset( $_POST['product_icon'] ) ? sanitize_text_field( $_POST['product_icon'] ) : '',
				'bg_color'   => isset( $_POST['product_bg_color'] ) ? sanitize_hex_color( $_POST['product_bg_color'] ) : '#f0f6fc',
				'text_color' => isset( $_POST['product_text_color'] ) ? sanitize_hex_color( $_POST['product_text_color'] ) : '#0073aa',
			),
			'cart' => array(
				'enabled'    => isset( $_POST['cart_enabled'] ) ? 'yes' : 'no',
				'position'   => isset( $_POST['cart_position'] ) ? sanitize_text_field( $_POST['cart_position'] ) : 'woocommerce_cart_totals_after_order_total',
				'priority'   => isset( $_POST['cart_priority'] ) ? absint( $_POST['cart_priority'] ) : 10,
				'layout'     => isset( $_POST['cart_layout'] ) ? sanitize_text_field( $_POST['cart_layout'] ) : 'box',
				'text'       => isset( $_POST['cart_text'] ) ? sanitize_text_field( $_POST['cart_text'] ) : '',
				'icon'       => isset( $_POST['cart_icon'] ) ? sanitize_text_field( $_POST['cart_icon'] ) : '',
				'bg_color'   => isset( $_POST['cart_bg_color'] ) ? sanitize_hex_color( $_POST['cart_bg_color'] ) : '#f0fdf4',
				'text_color' => isset( $_POST['cart_text_color'] ) ? sanitize_hex_color( $_POST['cart_text_color'] ) : '#00a32a',
			),
			'checkout' => array(
				'enabled'    => isset( $_POST['checkout_enabled'] ) ? 'yes' : 'no',
				'position'   => isset( $_POST['checkout_position'] ) ? sanitize_text_field( $_POST['checkout_position'] ) : 'woocommerce_review_order_after_order_total',
				'priority'   => isset( $_POST['checkout_priority'] ) ? absint( $_POST['checkout_priority'] ) : 10,
				'layout'     => isset( $_POST['checkout_layout'] ) ? sanitize_text_field( $_POST['checkout_layout'] ) : 'banner',
				'text'       => isset( $_POST['checkout_text'] ) ? sanitize_text_field( $_POST['checkout_text'] ) : '',
				'icon'       => isset( $_POST['checkout_icon'] ) ? sanitize_text_field( $_POST['checkout_icon'] ) : '',
				'bg_color'   => isset( $_POST['checkout_bg_color'] ) ? sanitize_hex_color( $_POST['checkout_bg_color'] ) : '#fef3c7',
				'text_color' => isset( $_POST['checkout_text_color'] ) ? sanitize_hex_color( $_POST['checkout_text_color'] ) : '#92400e',
			),
		);

		update_option( 'pcw_display_settings', $settings );

		wp_safe_redirect( add_query_arg( array( 'page' => 'pcw-display', 'message' => 'settings_saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
