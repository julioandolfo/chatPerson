<?php
/**
 * Funções de formatação
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de formatação
 */
class PCW_Formatters {

	/**
	 * Formatar valor monetário (retorna HTML)
	 *
	 * @param float $value Valor.
	 * @return string
	 */
	public static function format_money( $value ) {
		if ( function_exists( 'wc_price' ) ) {
			return wc_price( $value );
		}
		return 'R$ ' . number_format( floatval( $value ), 2, ',', '.' );
	}

	/**
	 * Formatar valor monetário (retorna texto simples)
	 *
	 * @param float $value Valor.
	 * @return string
	 */
	public static function format_money_plain( $value ) {
		return 'R$ ' . number_format( floatval( $value ), 2, ',', '.' );
	}
}
