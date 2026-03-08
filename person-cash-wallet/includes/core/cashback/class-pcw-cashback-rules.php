<?php
/**
 * Classe de regras de cashback
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de regras de cashback
 */
class PCW_Cashback_Rules {

	/**
	 * Obter todas as regras ativas
	 *
	 * @return array
	 */
	public static function get_active_rules() {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_cashback_rules';

		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE status = 'active' ORDER BY priority DESC"
		);
	}

	/**
	 * Obter todas as regras (ativas e inativas)
	 *
	 * @return array
	 */
	public static function get_all_rules() {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_cashback_rules';

		return $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY priority DESC, created_at DESC"
		);
	}

	/**
	 * Obter regra por ID
	 *
	 * @param int $rule_id ID da regra.
	 * @return object|null
	 */
	public static function get_rule( $rule_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_cashback_rules';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				absint( $rule_id )
			)
		);
	}

	/**
	 * Criar regra
	 *
	 * @param array $data Dados da regra.
	 * @return int|false ID da regra ou false
	 */
	public static function create( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_cashback_rules';

		$defaults = array(
			'name'                => '',
			'description'         => '',
			'status'             => 'active',
			'type'               => 'percentage',
			'value'              => 0,
			'min_order_amount'   => 0,
			'max_cashback_amount' => 0,
			'expiration_days'    => 90,
			'expiration_type'    => 'days',
			'priority'           => 10,
		);

		$data = wp_parse_args( $data, $defaults );

		$insert_data = array(
			'name'                => sanitize_text_field( $data['name'] ),
			'description'         => sanitize_textarea_field( $data['description'] ),
			'status'             => sanitize_text_field( $data['status'] ),
			'type'               => sanitize_text_field( $data['type'] ),
			'value'              => floatval( $data['value'] ),
			'min_order_amount'   => floatval( $data['min_order_amount'] ),
			'max_cashback_amount' => floatval( $data['max_cashback_amount'] ),
			'expiration_days'    => absint( $data['expiration_days'] ),
			'expiration_type'    => sanitize_text_field( $data['expiration_type'] ),
			'priority'           => absint( $data['priority'] ),
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
		);

		// Campos opcionais JSON
		if ( isset( $data['product_categories'] ) ) {
			$insert_data['product_categories'] = wp_json_encode( $data['product_categories'] );
		}

		if ( isset( $data['excluded_products'] ) ) {
			$insert_data['excluded_products'] = wp_json_encode( $data['excluded_products'] );
		}

		if ( isset( $data['user_roles'] ) ) {
			$insert_data['user_roles'] = wp_json_encode( $data['user_roles'] );
		}

		if ( isset( $data['conditions'] ) ) {
			$insert_data['conditions'] = wp_json_encode( $data['conditions'] );
		}

		$result = $wpdb->insert( $table, $insert_data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Calcular cashback baseado na regra e pedido
	 *
	 * @param object $order Pedido WooCommerce.
	 * @param object $rule Regra de cashback.
	 * @return float
	 */
	public static function calculate_cashback( $order, $rule ) {
		$order_total = floatval( $order->get_total() );

		// Verificar valor mínimo
		if ( $rule->min_order_amount > 0 && $order_total < floatval( $rule->min_order_amount ) ) {
			return 0;
		}

		// Calcular cashback
		if ( 'percentage' === $rule->type ) {
			$cashback = ( $order_total * floatval( $rule->value ) ) / 100;
		} else {
			$cashback = floatval( $rule->value );
		}

		// Aplicar limite máximo
		if ( $rule->max_cashback_amount > 0 ) {
			$cashback = min( $cashback, floatval( $rule->max_cashback_amount ) );
		}

		return round( $cashback, 2 );
	}

	/**
	 * Verificar se regra se aplica ao pedido
	 *
	 * @param object $order Pedido WooCommerce.
	 * @param object $rule Regra de cashback.
	 * @return bool
	 */
	public static function rule_applies( $order, $rule ) {
		// Verificar categorias de produtos
		if ( ! empty( $rule->product_categories ) ) {
			$categories = json_decode( $rule->product_categories, true );
			if ( is_array( $categories ) && ! empty( $categories ) ) {
				$order_categories = array();
				foreach ( $order->get_items() as $item ) {
					$product_id = $item->get_product_id();
					$terms      = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
					$order_categories = array_merge( $order_categories, $terms );
				}

				if ( empty( array_intersect( $categories, $order_categories ) ) ) {
					return false;
				}
			}
		}

		// Verificar produtos excluídos
		if ( ! empty( $rule->excluded_products ) ) {
			$excluded = json_decode( $rule->excluded_products, true );
			if ( is_array( $excluded ) && ! empty( $excluded ) ) {
				foreach ( $order->get_items() as $item ) {
					if ( in_array( $item->get_product_id(), $excluded, true ) ) {
						return false;
					}
				}
			}
		}

		// Verificar roles do usuário
		if ( ! empty( $rule->user_roles ) ) {
			$roles = json_decode( $rule->user_roles, true );
			if ( is_array( $roles ) && ! empty( $roles ) ) {
				$user = $order->get_user();
				if ( $user ) {
					$user_roles = $user->roles;
					if ( empty( array_intersect( $roles, $user_roles ) ) ) {
						return false;
					}
				}
			}
		}

		return true;
	}
}
