<?php
/**
 * Classe principal de cashback
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de cashback
 */
class PCW_Cashback {

	/**
	 * ID do usuário
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Construtor
	 *
	 * @param int $user_id ID do usuário.
	 */
	public function __construct( $user_id = 0 ) {
		$this->user_id = $user_id ? absint( $user_id ) : get_current_user_id();
	}

	/**
	 * Criar cashback
	 *
	 * @param int    $order_id ID do pedido.
	 * @param float  $amount Valor do cashback.
	 * @param int    $rule_id ID da regra aplicada.
	 * @param string $expires_date Data de expiração.
	 * @return int|false ID do cashback ou false
	 */
	public function create( $order_id, $amount, $rule_id = 0, $expires_date = null ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_cashback';

		if ( ! $expires_date ) {
			$expires_date = $this->calculate_expiration_date( $rule_id );
		}

		$data = array(
			'user_id'      => $this->user_id,
			'order_id'     => absint( $order_id ),
			'amount'       => floatval( $amount ),
			'status'       => 'pending',
			'earned_date'  => current_time( 'mysql' ),
			'expires_date' => $expires_date,
			'rule_id'      => $rule_id ? absint( $rule_id ) : null,
			'created_at'   => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $data );

		if ( $result ) {
			$cashback_id = $wpdb->insert_id;

			// Criar histórico
			$this->add_history( $cashback_id, $order_id, 'earned', $amount );

			// Disparar ação
			do_action( 'pcw_cashback_created', $cashback_id, $this->user_id, $order_id, $amount );

			return $cashback_id;
		}

		return false;
	}

	/**
	 * Adicionar cashback (método estático)
	 *
	 * @param int    $user_id ID do usuário.
	 * @param float  $amount Valor do cashback.
	 * @param string $source Fonte (order, retroactive, manual, adjustment).
	 * @param int    $order_id ID do pedido.
	 * @param string $description Descrição.
	 * @param string $expires_date Data de expiração.
	 * @return int|false ID do cashback ou false
	 */
	public static function add( $user_id, $amount, $source = 'manual', $order_id = 0, $description = '', $expires_date = null ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_cashback';

		if ( ! $expires_date ) {
			$expiration_days = absint( get_option( 'pcw_cashback_expiration_days', 0 ) );
			if ( $expiration_days > 0 ) {
				$expires_date = gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiration_days} days" ) );
			}
		}

		// Determinar status inicial baseado na fonte
		$status = 'available'; // Por padrão, retroativo e manual já ficam disponíveis
		if ( 'order' === $source ) {
			$status = 'pending'; // Cashback de pedidos começa pending
		}

		$data = array(
			'user_id'      => absint( $user_id ),
			'order_id'     => absint( $order_id ),
			'amount'       => floatval( $amount ),
			'status'       => $status,
			'earned_date'  => current_time( 'mysql' ),
			'expires_date' => $expires_date,
			'rule_id'      => null,
			'created_at'   => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
		);

		// Se tiver descrição, adicionar como meta ou registrar
		$result = $wpdb->insert(
			$table,
			$data,
			array( '%d', '%d', '%f', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( $result ) {
			$cashback_id = $wpdb->insert_id;

			// Adicionar à wallet se for retroativo, manual ou adjustment
			if ( in_array( $source, array( 'retroactive', 'manual', 'adjustment' ), true ) ) {
				$wallet = new PCW_Wallet( $user_id );
				$wallet->credit( $amount, $source, $description, $order_id, $cashback_id );
			}

			// Disparar ação
			do_action( 'pcw_cashback_added', $cashback_id, $user_id, $amount, $source );

			return $cashback_id;
		}

		return false;
	}

	/**
	 * Calcular data de expiração
	 *
	 * @param int $rule_id ID da regra.
	 * @return string|null Data de expiração ou NULL se não expira
	 */
	private function calculate_expiration_date( $rule_id = 0 ) {
		$expiration_days = null;

		// Primeiro, verificar se tem uma regra específica com expiração definida
		if ( $rule_id ) {
			$rule = $this->get_rule( $rule_id );
			if ( $rule && $rule->expiration_days ) {
				$expiration_days = absint( $rule->expiration_days );
			}
		}

		// Se não tem regra ou a regra não define expiração, usar configuração global
		if ( null === $expiration_days ) {
			$expiration_days = absint( get_option( 'pcw_cashback_expiration_days', 0 ) );
		}

		// Se 0 dias, não expira (retorna NULL)
		if ( 0 === $expiration_days ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiration_days} days" ) );
	}

	/**
	 * Obter regra de cashback
	 *
	 * @param int $rule_id ID da regra.
	 * @return object|null
	 */
	private function get_rule( $rule_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_cashback_rules';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND status = 'active'",
				absint( $rule_id )
			)
		);
	}

	/**
	 * Adicionar ao histórico
	 *
	 * @param int    $cashback_id ID do cashback.
	 * @param int    $order_id ID do pedido.
	 * @param string $type Tipo (earned, used, expired).
	 * @param float  $amount Valor.
	 */
	public function add_history( $cashback_id, $order_id, $type, $amount ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_cashback_history';

		$available_balance = $this->get_available_balance();

		$data = array(
			'cashback_id'   => absint( $cashback_id ),
			'user_id'       => $this->user_id,
			'order_id'      => $order_id ? absint( $order_id ) : null,
			'type'          => sanitize_text_field( $type ),
			'amount'        => floatval( $amount ),
			'balance_before' => $available_balance,
			'balance_after' => 'earned' === $type ? $available_balance + $amount : $available_balance - $amount,
			'description'   => $this->get_type_description( $type ),
			'created_at'     => current_time( 'mysql' ),
		);

		$wpdb->insert( $table, $data );
	}

	/**
	 * Obter descrição do tipo
	 *
	 * @param string $type Tipo.
	 * @return string
	 */
	private function get_type_description( $type ) {
		$descriptions = array(
			'earned'  => __( 'Cashback ganho', 'person-cash-wallet' ),
			'used'    => __( 'Cashback utilizado', 'person-cash-wallet' ),
			'expired' => __( 'Cashback expirado', 'person-cash-wallet' ),
		);

		return isset( $descriptions[ $type ] ) ? $descriptions[ $type ] : '';
	}

	/**
	 * Obter saldo disponível de cashback
	 *
	 * @return float
	 */
	public function get_available_balance() {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_cashback';

		$balance = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(amount) FROM {$table} 
				WHERE user_id = %d 
				AND status = 'available' 
				AND (expires_date IS NULL OR expires_date > NOW())",
				$this->user_id
			)
		);

		return floatval( $balance );
	}

	/**
	 * Obter cashback disponível (lista)
	 *
	 * @return array
	 */
	public function get_available_cashback() {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_cashback';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
				WHERE user_id = %d 
				AND status = 'available' 
				AND (expires_date IS NULL OR expires_date > NOW())
				ORDER BY expires_date ASC",
				$this->user_id
			)
		);
	}

	/**
	 * Obter histórico de cashback
	 *
	 * @param array $args Argumentos.
	 * @return array
	 */
	public function get_history( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_cashback_history';

		$defaults = array(
			'limit' => 20,
			'offset' => 0,
			'type' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( 'user_id = %d' );
		$values = array( $this->user_id );

		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'type = %s';
			$values[] = sanitize_text_field( $args['type'] );
		}

		$where_clause = implode( ' AND ', $where );

		$query = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d",
			array_merge( $values, array( absint( $args['limit'] ), absint( $args['offset'] ) ) )
		);

		return $wpdb->get_results( $query );
	}

	/**
	 * Usar cashback
	 *
	 * @param float $amount Valor a usar.
	 * @param int   $order_id ID do pedido.
	 * @return bool
	 */
	public function use_cashback( $amount, $order_id = 0 ) {
		$available = $this->get_available_cashback();
		$remaining = floatval( $amount );

		if ( $this->get_available_balance() < $amount ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_cashback';

		foreach ( $available as $cashback ) {
			if ( $remaining <= 0 ) {
				break;
			}

			$cashback_amount = floatval( $cashback->amount );
			$used_amount      = min( $cashback_amount, $remaining );

			if ( $used_amount >= $cashback_amount ) {
				// Usar cashback completo
				$wpdb->update(
					$table,
					array(
						'status'     => 'used',
						'used_date'  => current_time( 'mysql' ),
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => $cashback->id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);

				$this->add_history( $cashback->id, $order_id, 'used', $cashback_amount );
			} else {
				// Criar novo cashback com o restante
				$remaining_amount = $cashback_amount - $used_amount;

				$wpdb->insert(
					$table,
					array(
						'user_id'      => $this->user_id,
						'order_id'     => $cashback->order_id,
						'amount'       => $remaining_amount,
						'status'       => 'available',
						'earned_date'  => $cashback->earned_date,
						'expires_date' => $cashback->expires_date,
						'rule_id'      => $cashback->rule_id,
						'created_at'   => current_time( 'mysql' ),
						'updated_at'   => current_time( 'mysql' ),
					)
				);

				// Marcar original como usado
				$wpdb->update(
					$table,
					array(
						'amount'      => $used_amount,
						'status'      => 'used',
						'used_date'   => current_time( 'mysql' ),
						'updated_at'  => current_time( 'mysql' ),
					),
					array( 'id' => $cashback->id ),
					array( '%f', '%s', '%s', '%s' ),
					array( '%d' )
				);

				$this->add_history( $cashback->id, $order_id, 'used', $used_amount );
			}

			$remaining -= $used_amount;
		}

		do_action( 'pcw_cashback_used', $this->user_id, $amount, $order_id );

		return true;
	}
}
