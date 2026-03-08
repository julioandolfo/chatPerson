<?php
/**
 * Condições de Workflow
 *
 * @package GrowlyDigital
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de condições de workflow
 */
class PCW_Workflow_Conditions {

	/**
	 * Tipos de condições disponíveis
	 *
	 * @var array
	 */
	private static $condition_types = array();

	/**
	 * Inicializar
	 */
	public static function init() {
		self::register_default_conditions();
	}

	/**
	 * Registrar condições padrão
	 */
	private static function register_default_conditions() {
		// Condições de Pedido
		self::register( 'order_status', array(
			'name'        => __( 'Status do Pedido', 'person-cash-wallet' ),
			'group'       => 'order',
			'operators'   => array( '=', '!=' ),
			'value_type'  => 'select',
			'options'     => 'order_statuses',
			'description' => __( 'Status atual do pedido', 'person-cash-wallet' ),
		) );

		self::register( 'order_age_days', array(
			'name'        => __( 'Dias desde Criação do Pedido', 'person-cash-wallet' ),
			'group'       => 'order',
			'operators'   => array( '>', '<', '>=', '<=', '=' ),
			'value_type'  => 'number',
			'description' => __( 'Quantidade de dias desde a criação do pedido', 'person-cash-wallet' ),
		) );

		self::register( 'days_in_current_status', array(
			'name'        => __( 'Dias no Status Atual', 'person-cash-wallet' ),
			'group'       => 'order',
			'operators'   => array( '>', '<', '>=', '<=', '=' ),
			'value_type'  => 'number',
			'description' => __( 'Quantidade de dias que o pedido está no status atual', 'person-cash-wallet' ),
		) );

		self::register( 'order_total', array(
			'name'      => __( 'Total do Pedido', 'person-cash-wallet' ),
			'group'     => 'order',
			'operators' => array( '>', '<', '>=', '<=', '=', '!=' ),
			'value_type' => 'number',
		) );

		self::register( 'order_items_count', array(
			'name'      => __( 'Quantidade de Itens', 'person-cash-wallet' ),
			'group'     => 'order',
			'operators' => array( '>', '<', '>=', '<=', '=', '!=' ),
			'value_type' => 'number',
		) );

		self::register( 'payment_method', array(
			'name'      => __( 'Método de Pagamento', 'person-cash-wallet' ),
			'group'     => 'order',
			'operators' => array( '=', '!=' ),
			'value_type' => 'select',
			'options'   => 'payment_methods',
		) );

		// Condições de Cliente
		self::register( 'customer_level', array(
			'name'      => __( 'Nível do Cliente', 'person-cash-wallet' ),
			'group'     => 'customer',
			'operators' => array( '=', '!=', '>=', '<=' ),
			'value_type' => 'select',
			'options'   => 'levels',
		) );

		self::register( 'customer_orders_count', array(
			'name'      => __( 'Total de Pedidos do Cliente', 'person-cash-wallet' ),
			'group'     => 'customer',
			'operators' => array( '>', '<', '>=', '<=', '=', '!=' ),
			'value_type' => 'number',
		) );

		self::register( 'customer_total_spent', array(
			'name'      => __( 'Total Gasto pelo Cliente', 'person-cash-wallet' ),
			'group'     => 'customer',
			'operators' => array( '>', '<', '>=', '<=', '=', '!=' ),
			'value_type' => 'number',
		) );

		self::register( 'customer_has_phone', array(
			'name'      => __( 'Cliente tem Telefone', 'person-cash-wallet' ),
			'group'     => 'customer',
			'operators' => array( '=' ),
			'value_type' => 'boolean',
		) );

		self::register( 'days_since_last_order', array(
			'name'        => __( 'Dias desde Último Pedido', 'person-cash-wallet' ),
			'group'       => 'customer',
			'operators'   => array( '>', '<', '>=', '<=', '=' ),
			'value_type'  => 'number',
			'description' => __( 'Quantidade de dias desde o último pedido do cliente', 'person-cash-wallet' ),
		) );

		self::register( 'months_since_last_order', array(
			'name'        => __( 'Meses desde Último Pedido', 'person-cash-wallet' ),
			'group'       => 'customer',
			'operators'   => array( '>', '<', '>=', '<=', '=' ),
			'value_type'  => 'number',
			'description' => __( 'Quantidade de meses desde o último pedido do cliente', 'person-cash-wallet' ),
		) );

		// Condições de Cashback
		self::register( 'cashback_amount', array(
			'name'      => __( 'Valor do Cashback', 'person-cash-wallet' ),
			'group'     => 'cashback',
			'operators' => array( '>', '<', '>=', '<=', '=', '!=' ),
			'value_type' => 'number',
		) );

		self::register( 'wallet_balance', array(
			'name'      => __( 'Saldo da Wallet', 'person-cash-wallet' ),
			'group'     => 'cashback',
			'operators' => array( '>', '<', '>=', '<=', '=', '!=' ),
			'value_type' => 'number',
		) );

		// Condições de Produto
		self::register( 'product_in_order', array(
			'name'      => __( 'Produto no Pedido', 'person-cash-wallet' ),
			'group'     => 'product',
			'operators' => array( 'contains', 'not_contains' ),
			'value_type' => 'product_search',
		) );

		self::register( 'category_in_order', array(
			'name'      => __( 'Categoria no Pedido', 'person-cash-wallet' ),
			'group'     => 'product',
			'operators' => array( 'contains', 'not_contains' ),
			'value_type' => 'category_search',
		) );
	}

	/**
	 * Registrar condição
	 *
	 * @param string $id ID da condição.
	 * @param array  $args Argumentos.
	 */
	public static function register( $id, $args ) {
		$defaults = array(
			'name'       => '',
			'group'      => 'general',
			'operators'  => array( '=' ),
			'value_type' => 'text',
			'options'    => array(),
		);

		self::$condition_types[ $id ] = wp_parse_args( $args, $defaults );
	}

	/**
	 * Obter todos os tipos de condição
	 *
	 * @return array
	 */
	public static function get_all() {
		if ( empty( self::$condition_types ) ) {
			self::init();
		}
		return self::$condition_types;
	}

	/**
	 * Avaliar condições
	 *
	 * @param array $conditions Lista de condições.
	 * @param array $context Contexto de dados.
	 * @return bool
	 */
	public static function evaluate( $conditions, $context ) {
		if ( empty( $conditions ) ) {
			return true; // Sem condições = passa
		}

		$logic = isset( $conditions['logic'] ) ? $conditions['logic'] : 'AND';
		$rules = isset( $conditions['rules'] ) ? $conditions['rules'] : $conditions;

		if ( empty( $rules ) ) {
			return true;
		}

		$results = array();

		foreach ( $rules as $rule ) {
			if ( ! isset( $rule['type'] ) || ! isset( $rule['operator'] ) ) {
				continue;
			}

			$results[] = self::evaluate_single( $rule, $context );
		}

		if ( empty( $results ) ) {
			return true;
		}

		if ( 'OR' === strtoupper( $logic ) ) {
			return in_array( true, $results, true );
		}

		// AND (padrão)
		return ! in_array( false, $results, true );
	}

	/**
	 * Avaliar condição individual
	 *
	 * @param array $rule Regra de condição.
	 * @param array $context Contexto de dados.
	 * @return bool
	 */
	private static function evaluate_single( $rule, $context ) {
		$type = $rule['type'];
		$operator = $rule['operator'];
		$value = isset( $rule['value'] ) ? $rule['value'] : '';

		// Obter valor do contexto
		$context_value = self::get_context_value( $type, $context );

		// Comparar
		return self::compare( $context_value, $operator, $value );
	}

	/**
	 * Obter valor do contexto
	 *
	 * @param string $type Tipo de condição.
	 * @param array  $context Contexto.
	 * @return mixed
	 */
	private static function get_context_value( $type, $context ) {
		switch ( $type ) {
			case 'order_status':
				return isset( $context['order_status'] ) ? $context['order_status'] : '';

			case 'order_age_days':
				return isset( $context['order_age_days'] ) ? intval( $context['order_age_days'] ) : 0;

			case 'days_in_current_status':
				return isset( $context['days_in_status'] ) ? intval( $context['days_in_status'] ) : 0;

			case 'order_total':
				return isset( $context['order_total'] ) ? floatval( $context['order_total'] ) : 0;

			case 'order_items_count':
				return isset( $context['order_items_count'] ) ? intval( $context['order_items_count'] ) : 0;

			case 'payment_method':
				return isset( $context['payment_method'] ) ? $context['payment_method'] : '';

			case 'customer_level':
				return isset( $context['customer_level'] ) ? intval( $context['customer_level'] ) : 0;

			case 'customer_orders_count':
				if ( isset( $context['customer_id'] ) ) {
					return self::get_customer_orders_count( $context['customer_id'] );
				}
				return 0;

			case 'customer_total_spent':
				if ( isset( $context['customer_id'] ) ) {
					return self::get_customer_total_spent( $context['customer_id'] );
				}
				return 0;

			case 'customer_has_phone':
				return ! empty( $context['customer_phone'] );

			case 'cashback_amount':
				return isset( $context['cashback_amount'] ) ? floatval( $context['cashback_amount'] ) : 0;

			case 'wallet_balance':
				if ( isset( $context['customer_id'] ) ) {
					return PCW_Wallet::get_balance( $context['customer_id'] );
				}
				return 0;

			case 'product_in_order':
				return isset( $context['product_ids'] ) ? $context['product_ids'] : array();

			case 'category_in_order':
				return isset( $context['category_ids'] ) ? $context['category_ids'] : array();

			case 'days_since_last_order':
				if ( isset( $context['customer_id'] ) ) {
					return self::get_days_since_last_order( $context['customer_id'] );
				}
				return 0;

			case 'months_since_last_order':
				if ( isset( $context['customer_id'] ) ) {
					$days = self::get_days_since_last_order( $context['customer_id'] );
					return floor( $days / 30 );
				}
				return 0;

			default:
				return isset( $context[ $type ] ) ? $context[ $type ] : null;
		}
	}

	/**
	 * Comparar valores
	 *
	 * @param mixed  $value1 Valor 1.
	 * @param string $operator Operador.
	 * @param mixed  $value2 Valor 2.
	 * @return bool
	 */
	private static function compare( $value1, $operator, $value2 ) {
		switch ( $operator ) {
			case '=':
			case '==':
				return $value1 == $value2;

			case '!=':
			case '<>':
				return $value1 != $value2;

			case '>':
				return floatval( $value1 ) > floatval( $value2 );

			case '<':
				return floatval( $value1 ) < floatval( $value2 );

			case '>=':
				return floatval( $value1 ) >= floatval( $value2 );

			case '<=':
				return floatval( $value1 ) <= floatval( $value2 );

			case 'contains':
				if ( is_array( $value1 ) ) {
					return in_array( $value2, $value1 );
				}
				return false !== strpos( (string) $value1, (string) $value2 );

			case 'not_contains':
				if ( is_array( $value1 ) ) {
					return ! in_array( $value2, $value1 );
				}
				return false === strpos( (string) $value1, (string) $value2 );

			default:
				return false;
		}
	}

	/**
	 * Obter contagem de pedidos do cliente
	 *
	 * @param int $customer_id ID do cliente.
	 * @return int
	 */
	private static function get_customer_orders_count( $customer_id ) {
		if ( ! function_exists( 'wc_get_customer_order_count' ) ) {
			return 0;
		}
		return wc_get_customer_order_count( $customer_id );
	}

	/**
	 * Obter total gasto pelo cliente
	 *
	 * @param int $customer_id ID do cliente.
	 * @return float
	 */
	private static function get_customer_total_spent( $customer_id ) {
		if ( ! function_exists( 'wc_get_customer_total_spent' ) ) {
			return 0;
		}
		return floatval( wc_get_customer_total_spent( $customer_id ) );
	}

	/**
	 * Obter dias desde o último pedido do cliente
	 *
	 * @param int $customer_id ID do cliente.
	 * @return int Número de dias desde o último pedido (ou 9999 se nunca comprou).
	 */
	private static function get_days_since_last_order( $customer_id ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 9999;
		}

		$orders = wc_get_orders( array(
			'customer_id' => $customer_id,
			'status'      => array( 'wc-completed', 'wc-processing' ),
			'orderby'     => 'date',
			'order'       => 'DESC',
			'limit'       => 1,
			'return'      => 'objects',
		) );

		if ( empty( $orders ) ) {
			return 9999; // Cliente nunca fez pedido.
		}

		$last_order = $orders[0];
		$last_order_date = $last_order->get_date_created();

		if ( ! $last_order_date ) {
			return 9999;
		}

		$now = new DateTime( 'now', wp_timezone() );
		$order_date = new DateTime( $last_order_date->format( 'Y-m-d H:i:s' ), wp_timezone() );
		$diff = $now->diff( $order_date );

		return $diff->days;
	}

	/**
	 * Obter operadores formatados
	 *
	 * @return array
	 */
	public static function get_operators() {
		return array(
			'='            => __( 'Igual a', 'person-cash-wallet' ),
			'!='           => __( 'Diferente de', 'person-cash-wallet' ),
			'>'            => __( 'Maior que', 'person-cash-wallet' ),
			'<'            => __( 'Menor que', 'person-cash-wallet' ),
			'>='           => __( 'Maior ou igual a', 'person-cash-wallet' ),
			'<='           => __( 'Menor ou igual a', 'person-cash-wallet' ),
			'contains'     => __( 'Contém', 'person-cash-wallet' ),
			'not_contains' => __( 'Não contém', 'person-cash-wallet' ),
		);
	}
}
