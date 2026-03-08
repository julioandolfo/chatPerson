<?php
/**
 * Classe principal de wallet
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de wallet
 */
class PCW_Wallet {

	/**
	 * ID do usuário
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Dados da wallet
	 *
	 * @var object|null
	 */
	private $wallet_data;

	/**
	 * Construtor
	 *
	 * @param int $user_id ID do usuário.
	 */
	public function __construct( $user_id = 0 ) {
		$this->user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$this->load_wallet();
	}

	/**
	 * Carregar dados da wallet
	 */
	private function load_wallet() {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_wallet';

		$this->wallet_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d",
				$this->user_id
			)
		);

		// Criar wallet se não existir
		if ( ! $this->wallet_data ) {
			$this->create_wallet();
		}
	}

	/**
	 * Criar wallet para o usuário
	 *
	 * @return int|false ID da wallet ou false em caso de erro
	 */
	private function create_wallet() {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_wallet';

		$data = array(
			'user_id'     => $this->user_id,
			'balance'     => 0,
			'total_earned' => 0,
			'total_spent' => 0,
			'currency'    => get_option( 'pcw_currency', 'BRL' ),
			'created_at'  => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $data );

		if ( $result ) {
			$this->wallet_data = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE user_id = %d",
					$this->user_id
				)
			);
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Obter saldo da wallet
	 *
	 * @return float
	 */
	public function get_balance() {
		return $this->wallet_data ? floatval( $this->wallet_data->balance ) : 0;
	}

	/**
	 * Obter dados completos da wallet
	 *
	 * @return object|null
	 */
	public function get_wallet_data() {
		return $this->wallet_data;
	}

	/**
	 * Adicionar saldo à wallet
	 *
	 * @param float  $amount Valor a adicionar.
	 * @param string $source Fonte do crédito (cashback, manual, refund, etc).
	 * @param string $description Descrição da transação.
	 * @param int    $order_id ID do pedido (opcional).
	 * @param int    $reference_id ID de referência (opcional).
	 * @return int|false ID da transação ou false em caso de erro
	 */
	public function credit( $amount, $source = 'manual', $description = '', $order_id = 0, $reference_id = 0 ) {
		$amount = floatval( $amount );

		if ( $amount <= 0 ) {
			return false;
		}

		$balance_before = $this->get_balance();
		$balance_after  = $balance_before + $amount;

		// Criar transação
		$transaction_id = $this->create_transaction(
			'credit',
			$source,
			$amount,
			$balance_before,
			$balance_after,
			$description,
			$order_id,
			$reference_id
		);

		if ( ! $transaction_id ) {
			return false;
		}

		// Atualizar saldo
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_wallet';

		$wpdb->update(
			$table,
			array(
				'balance'      => $balance_after,
				'total_earned' => floatval( $this->wallet_data->total_earned ) + $amount,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'user_id' => $this->user_id ),
			array( '%f', '%f', '%s' ),
			array( '%d' )
		);

		// Recarregar dados
		$this->load_wallet();

		// Disparar ação
		do_action( 'pcw_wallet_credited', $this->user_id, $amount, $source, $transaction_id );

		return $transaction_id;
	}

	/**
	 * Remover saldo da wallet
	 *
	 * @param float  $amount Valor a remover.
	 * @param string $source Fonte do débito (purchase, manual, adjustment, etc).
	 * @param string $description Descrição da transação.
	 * @param int    $order_id ID do pedido (opcional).
	 * @param int    $reference_id ID de referência (opcional).
	 * @return int|false ID da transação ou false em caso de erro
	 */
	public function debit( $amount, $source = 'manual', $description = '', $order_id = 0, $reference_id = 0 ) {
		$amount = floatval( $amount );

		if ( $amount <= 0 ) {
			return false;
		}

		$balance_before = $this->get_balance();

		// Verificar se tem saldo suficiente
		if ( $balance_before < $amount ) {
			return false;
		}

		$balance_after = $balance_before - $amount;

		// Criar transação
		$transaction_id = $this->create_transaction(
			'debit',
			$source,
			$amount,
			$balance_before,
			$balance_after,
			$description,
			$order_id,
			$reference_id
		);

		if ( ! $transaction_id ) {
			return false;
		}

		// Atualizar saldo
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_wallet';

		$wpdb->update(
			$table,
			array(
				'balance'     => $balance_after,
				'total_spent' => floatval( $this->wallet_data->total_spent ) + $amount,
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'user_id' => $this->user_id ),
			array( '%f', '%f', '%s' ),
			array( '%d' )
		);

		// Recarregar dados
		$this->load_wallet();

		// Disparar ação
		do_action( 'pcw_wallet_debited', $this->user_id, $amount, $source, $transaction_id );

		return $transaction_id;
	}

	/**
	 * Criar transação
	 *
	 * @param string $type Tipo (credit/debit).
	 * @param string $source Fonte.
	 * @param float  $amount Valor.
	 * @param float  $balance_before Saldo antes.
	 * @param float  $balance_after Saldo depois.
	 * @param string $description Descrição.
	 * @param int    $order_id ID do pedido.
	 * @param int    $reference_id ID de referência.
	 * @return int|false ID da transação ou false
	 */
	private function create_transaction( $type, $source, $amount, $balance_before, $balance_after, $description = '', $order_id = 0, $reference_id = 0 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_wallet_transactions';

		$data = array(
			'wallet_id'      => $this->wallet_data->id,
			'user_id'        => $this->user_id,
			'order_id'       => $order_id ? absint( $order_id ) : null,
			'type'           => sanitize_text_field( $type ),
			'source'         => sanitize_text_field( $source ),
			'amount'         => floatval( $amount ),
			'balance_before' => floatval( $balance_before ),
			'balance_after'  => floatval( $balance_after ),
			'description'    => sanitize_textarea_field( $description ),
			'reference_id'   => $reference_id ? absint( $reference_id ) : null,
			'status'         => 'completed',
			'created_at'     => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Obter histórico de transações
	 *
	 * @param array $args Argumentos da query.
	 * @return array
	 */
	public function get_transactions( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_wallet_transactions';

		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
			'type'   => '',
			'source' => '',
			'order'  => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( 'user_id = %d' );
		$values = array( $this->user_id );

		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'type = %s';
			$values[] = sanitize_text_field( $args['type'] );
		}

		if ( ! empty( $args['source'] ) ) {
			$where[]  = 'source = %s';
			$values[] = sanitize_text_field( $args['source'] );
		}

		$where_clause = implode( ' AND ', $where );
		$order_by     = sanitize_sql_orderby( "created_at {$args['order']}" );

		$query = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$order_by} LIMIT %d OFFSET %d",
			array_merge( $values, array( absint( $args['limit'] ), absint( $args['offset'] ) ) )
		);

		return $wpdb->get_results( $query );
	}

	/**
	 * Obter total de transações
	 *
	 * @param array $args Argumentos da query.
	 * @return int
	 */
	public function get_transactions_count( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_wallet_transactions';

		$where = array( 'user_id = %d' );
		$values = array( $this->user_id );

		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'type = %s';
			$values[] = sanitize_text_field( $args['type'] );
		}

		if ( ! empty( $args['source'] ) ) {
			$where[]  = 'source = %s';
			$values[] = sanitize_text_field( $args['source'] );
		}

		$where_clause = implode( ' AND ', $where );

		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE {$where_clause}",
			$values
		);

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Adicionar saldo manualmente (admin)
	 *
	 * @param float  $amount Valor a adicionar.
	 * @param string $description Descrição.
	 * @return int|false ID da transação ou false
	 */
	public function add_balance_manual( $amount, $description = '' ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		if ( empty( $description ) ) {
			$description = __( 'Saldo adicionado manualmente', 'person-cash-wallet' );
		}

		return $this->credit( $amount, 'manual', $description );
	}

	/**
	 * Remover saldo manualmente (admin)
	 *
	 * @param float  $amount Valor a remover.
	 * @param string $description Descrição.
	 * @return int|false ID da transação ou false
	 */
	public function remove_balance_manual( $amount, $description = '' ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		if ( empty( $description ) ) {
			$description = __( 'Saldo removido manualmente', 'person-cash-wallet' );
		}

		return $this->debit( $amount, 'manual', $description );
	}

	/**
	 * Verificar se usuário tem saldo suficiente
	 *
	 * @param float $amount Valor necessário.
	 * @return bool
	 */
	public function has_balance( $amount ) {
		return $this->get_balance() >= floatval( $amount );
	}
}
