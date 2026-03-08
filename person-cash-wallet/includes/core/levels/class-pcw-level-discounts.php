<?php
/**
 * Classe de descontos por nível
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de descontos por nível
 */
class PCW_Level_Discounts {

	/**
	 * Aplicar descontos do nível no carrinho
	 *
	 * @param WC_Cart $cart Carrinho.
	 */
	public static function apply_level_discounts( $cart ) {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_level = PCW_Levels::get_user_level( get_current_user_id() );

		if ( ! $user_level ) {
			return;
		}

		$discounts = self::get_level_discounts( $user_level->id );

		foreach ( $discounts as $discount ) {
			if ( 'active' !== $discount->status ) {
				continue;
			}

			$cart_total = floatval( $cart->get_subtotal() );

			// Verificar valor mínimo
			if ( $discount->min_order_amount > 0 && $cart_total < floatval( $discount->min_order_amount ) ) {
				continue;
			}

			// Aplicar desconto
			switch ( $discount->discount_type ) {
				case 'percentage':
					$discount_amount = ( $cart_total * floatval( $discount->discount_value ) ) / 100;

					// Aplicar limite máximo
					if ( $discount->max_discount_amount > 0 ) {
						$discount_amount = min( $discount_amount, floatval( $discount->max_discount_amount ) );
					}

					if ( $discount_amount > 0 ) {
						$cart->add_fee( __( 'Desconto Nível', 'person-cash-wallet' ) . ' ' . $user_level->name, -$discount_amount );
					}
					break;

				case 'fixed':
					$discount_amount = floatval( $discount->discount_value );

					// Não pode exceder o total
					if ( $discount_amount > $cart_total ) {
						$discount_amount = $cart_total;
					}

					if ( $discount_amount > 0 ) {
						$cart->add_fee( __( 'Desconto Nível', 'person-cash-wallet' ) . ' ' . $user_level->name, -$discount_amount );
					}
					break;

				case 'free_shipping':
					// Será aplicado via filtro de frete
					add_filter( 'woocommerce_package_rates', array( __CLASS__, 'apply_free_shipping' ), 10, 2 );
					break;
			}
		}
	}

	/**
	 * Aplicar frete grátis
	 *
	 * @param array $rates Taxas de frete.
	 * @param array $package Pacote.
	 * @return array
	 */
	public static function apply_free_shipping( $rates, $package ) {
		foreach ( $rates as $rate_id => $rate ) {
			if ( 'free_shipping' !== $rate->method_id ) {
				$rates[ $rate_id ]->cost = 0;
				$rates[ $rate_id ]->label .= ' ' . __( '(Grátis - Nível)', 'person-cash-wallet' );
			}
		}

		return $rates;
	}

	/**
	 * Obter descontos do nível
	 *
	 * @param int $level_id ID do nível.
	 * @return array
	 */
	public static function get_level_discounts( $level_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_level_discounts';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE level_id = %d AND status = 'active'",
				absint( $level_id )
			)
		);
	}

	/**
	 * Criar desconto
	 *
	 * @param array $data Dados do desconto.
	 * @return int|false ID do desconto ou false
	 */
	public static function create( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_level_discounts';

		$defaults = array(
			'level_id'          => 0,
			'discount_type'     => 'percentage',
			'discount_value'    => 0,
			'min_order_amount'  => 0,
			'max_discount_amount' => 0,
			'applicable_to'     => 'all',
			'product_ids'       => array(),
			'category_ids'      => array(),
			'status'            => 'active',
		);

		$data = wp_parse_args( $data, $defaults );

		$insert_data = array(
			'level_id'          => absint( $data['level_id'] ),
			'discount_type'     => sanitize_text_field( $data['discount_type'] ),
			'discount_value'    => floatval( $data['discount_value'] ),
			'min_order_amount'  => floatval( $data['min_order_amount'] ),
			'max_discount_amount' => floatval( $data['max_discount_amount'] ),
			'applicable_to'     => sanitize_text_field( $data['applicable_to'] ),
			'status'            => sanitize_text_field( $data['status'] ),
			'created_at'        => current_time( 'mysql' ),
			'updated_at'        => current_time( 'mysql' ),
		);

		if ( ! empty( $data['product_ids'] ) ) {
			$insert_data['product_ids'] = wp_json_encode( $data['product_ids'] );
		}

		if ( ! empty( $data['category_ids'] ) ) {
			$insert_data['category_ids'] = wp_json_encode( $data['category_ids'] );
		}

		$result = $wpdb->insert( $table, $insert_data );

		return $result ? $wpdb->insert_id : false;
	}
}

// Aplicar descontos no carrinho
add_action( 'woocommerce_cart_calculate_fees', array( 'PCW_Level_Discounts', 'apply_level_discounts' ) );
