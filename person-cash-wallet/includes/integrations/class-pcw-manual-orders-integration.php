<?php
/**
 * Integração com WC Advanced Manual Orders
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de integração com WC Advanced Manual Orders
 */
class PCW_Manual_Orders_Integration {

	/**
	 * Inicializar
	 */
	public static function init() {
		// Verificar se o plugin está ativo
		if ( ! self::is_plugin_active() ) {
			return;
		}

		// Enfileirar scripts na página de criação de pedidos
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

		// AJAX para buscar saldo do cliente
		add_action( 'wp_ajax_pcw_get_customer_cashback', array( __CLASS__, 'ajax_get_customer_cashback' ) );
	}

	/**
	 * Verificar se o plugin está ativo
	 *
	 * @return bool
	 */
	private static function is_plugin_active() {
		// Verificar se é o plugin WC Advanced Manual Orders
		return class_exists( 'WC_Advanced_Manual_Orders' ) || 
		       is_plugin_active( 'wc-advanced-manual-orders/wc-advanced-manual-orders.php' ) ||
		       // Também suportar outros plugins de pedidos manuais
		       ( function_exists( 'is_plugin_active' ) && 
		         ( is_plugin_active( 'woocommerce-manual-order/woocommerce-manual-order.php' ) ||
		           is_plugin_active( 'manual-order/manual-order.php' ) ) );
	}

	/**
	 * Enfileirar scripts
	 *
	 * @param string $hook Hook da página.
	 */
	public static function enqueue_scripts( $hook ) {
		// Detectar páginas de pedidos (diferentes plugins usam diferentes hooks)
		$is_order_page = false;
		
		// WooCommerce nativo
		if ( 'post-new.php' === $hook || 'post.php' === $hook ) {
			$screen = get_current_screen();
			if ( $screen && 'shop_order' === $screen->id ) {
				$is_order_page = true;
			}
		}
		
		// Advanced Manual Orders
		if ( strpos( $hook, 'advanced-manual-orders' ) !== false ) {
			$is_order_page = true;
		}
		
		// Outros plugins de pedidos manuais
		if ( strpos( $hook, 'manual-order' ) !== false || 
		     strpos( $hook, 'wc-order' ) !== false ||
		     strpos( $hook, 'create-order' ) !== false ) {
			$is_order_page = true;
		}
		
		if ( ! $is_order_page ) {
			return;
		}

		// Enfileirar jQuery
		wp_enqueue_script( 'jquery' );
		
		// Enfileirar nosso JavaScript
		wp_enqueue_script(
			'pcw-manual-orders-integration',
			PCW_PLUGIN_URL . 'assets/js/admin-manual-orders-integration.js',
			array( 'jquery' ),
			PCW_VERSION,
			true
		);
		
		// Passar dados para o JavaScript
		wp_localize_script(
			'pcw-manual-orders-integration',
			'pcwManualOrdersData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pcw_manual_orders' ),
			)
		);
		
		// CSS inline (pequeno, não vale arquivo separado)
		wp_add_inline_style( 'wp-admin', '
			/* Growly Digital - Card de Saldo */
			#pcw-customer-cashback-card {
				display: none;
				margin-top: 20px;
				animation: pcwSlideDown 0.3s ease-out;
			}
			
			#pcw-customer-cashback-card.pcw-show {
				display: block;
			}
			
			/* Usar estilo do plugin quando for .item-box */
			#pcw-customer-cashback-card.item-box .pcw-cashback-card-inner {
				padding: 0;
				background: transparent;
			}
			
			#pcw-customer-cashback-card.item-box .title {
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				color: white;
				padding: 12px 16px;
				border-radius: 4px 4px 0 0;
				margin: -1px -1px 0 -1px;
			}
			
			#pcw-customer-cashback-card.item-box .title h3 {
				color: white;
				margin: 0;
				font-size: 14px;
				font-weight: 600;
			}
			
			#pcw-customer-cashback-card.item-box .inner {
				padding: 16px;
			}
			
			.pcw-cashback-card-inner {
				background: white;
				padding: 20px;
				border-radius: 8px;
			}
			
			.pcw-cashback-header {
				display: flex;
				align-items: center;
				gap: 12px;
				margin-bottom: 16px;
				padding-bottom: 16px;
				border-bottom: 2px solid #f3f4f6;
			}
			
			.pcw-cashback-icon {
				width: 48px;
				height: 48px;
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				border-radius: 12px;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 24px;
				flex-shrink: 0;
			}
			
			.pcw-cashback-title {
				flex: 1;
			}
			
			.pcw-cashback-title h3 {
				margin: 0 0 4px 0;
				font-size: 16px;
				font-weight: 600;
				color: #1f2937;
			}
			
			.pcw-cashback-title p {
				margin: 0;
				font-size: 13px;
				color: #6b7280;
			}
			
			.pcw-cashback-body {
				display: grid;
				grid-template-columns: repeat(3, 1fr);
				gap: 12px;
			}
			
			.pcw-cashback-stat {
				background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
				padding: 14px 12px;
				border-radius: 8px;
				text-align: center;
				border: 1px solid #e2e8f0;
			}
			
			.pcw-cashback-stat-label {
				display: block;
				font-size: 11px;
				color: #64748b;
				margin-bottom: 6px;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				font-weight: 500;
			}
			
			.pcw-cashback-stat-value {
				display: block;
				font-size: 18px;
				font-weight: 700;
				color: #667eea;
			}
			
			.pcw-cashback-stat-value .woocommerce-Price-amount {
				font-size: inherit;
				font-weight: inherit;
				color: inherit;
			}
			
			.pcw-cashback-loading {
				padding: 20px;
				text-align: center;
				color: #6b7280;
			}
			
			.pcw-cashback-error {
				padding: 16px;
				background: #fef2f2;
				border: 1px solid #fecaca;
				border-radius: 8px;
				color: #991b1b;
				text-align: center;
			}
			
			@keyframes pcwSlideDown {
				from {
					opacity: 0;
					transform: translateY(-10px);
				}
				to {
					opacity: 1;
					transform: translateY(0);
				}
			}
			
			/* Loading spinner */
			.pcw-spinner {
				display: inline-block;
				width: 20px;
				height: 20px;
				border: 3px solid rgba(102, 126, 234, 0.2);
				border-top-color: #667eea;
				border-radius: 50%;
				animation: pcwSpin 0.8s linear infinite;
			}
			
			@keyframes pcwSpin {
				to { transform: rotate(360deg); }
			}
		' );
	}

	/**
	 * AJAX: Buscar saldo de cashback do cliente
	 */
	public static function ajax_get_customer_cashback() {
		// Verificar nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'pcw_manual_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'Ação não autorizada', 'person-cash-wallet' ) ) );
		}

		// Verificar permissão
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$email   = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$user    = null;

		// Buscar usuário por ID se disponível
		if ( $user_id > 0 ) {
			$user = get_userdata( $user_id );
		}

		// Fallback: buscar usuário por email
		if ( ! $user && ! empty( $email ) && is_email( $email ) ) {
			$user = get_user_by( 'email', $email );
		}

		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Cliente não encontrado', 'person-cash-wallet' ) ) );
		}

		// Buscar dados de cashback
		$wallet = new PCW_Wallet( $user->ID );
		$balance = $wallet->get_balance();

		// Buscar cashback pendente
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_cashback';

		$pending = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE user_id = %d AND status = 'pending'",
				$user->ID
			)
		);

		$total_earned = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE user_id = %d AND status IN ('pending', 'available', 'used')",
				$user->ID
			)
		);

		// Retornar dados
		wp_send_json_success( array(
			'customer_id'   => $user->ID,
			'customer_name' => $user->display_name,
			'customer_email' => $user->user_email,
			'balance'       => wc_price( $balance ),
			'balance_raw'   => floatval( $balance ),
			'pending'       => wc_price( $pending ),
			'pending_raw'   => floatval( $pending ),
			'total_earned'  => wc_price( $total_earned ),
			'total_earned_raw' => floatval( $total_earned ),
		) );
	}
}
