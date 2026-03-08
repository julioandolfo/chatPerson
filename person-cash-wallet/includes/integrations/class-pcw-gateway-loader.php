<?php
/**
 * Carregador de gateways de pagamento
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de carregamento de gateways
 */
class PCW_Gateway_Loader {

	/**
	 * Adicionar gateway ao WooCommerce
	 *
	 * @param array $gateways Gateways existentes.
	 * @return array
	 */
	public static function add_gateway( $gateways ) {
		// Verificar se a classe existe
		if ( ! class_exists( 'PCW_Payment_Gateway' ) ) {
			return $gateways;
		}

		// Adicionar gateway
		$gateways[] = 'PCW_Payment_Gateway';
		
		return $gateways;
	}
}

// O filtro será adicionado pelo loader quando apropriado
