<?php
/**
 * Classe de transações da wallet
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de transações da wallet
 */
class PCW_Wallet_Transactions {

	/**
	 * Obter transação por ID
	 *
	 * @param int $transaction_id ID da transação.
	 * @return object|null
	 */
	public static function get_transaction( $transaction_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_wallet_transactions';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				absint( $transaction_id )
			)
		);
	}

	/**
	 * Obter transações por usuário
	 *
	 * @param int   $user_id ID do usuário.
	 * @param array $args Argumentos.
	 * @return array
	 */
	public static function get_user_transactions( $user_id, $args = array() ) {
		$wallet = new PCW_Wallet( $user_id );
		return $wallet->get_transactions( $args );
	}

	/**
	 * Obter transações por pedido
	 *
	 * @param int $order_id ID do pedido.
	 * @return array
	 */
	public static function get_order_transactions( $order_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_wallet_transactions';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at DESC",
				absint( $order_id )
			)
		);
	}

	/**
	 * Obter estatísticas de transações
	 *
	 * @param int $user_id ID do usuário.
	 * @return array
	 */
	public static function get_statistics( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_wallet_transactions';

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(*) as total_transactions,
					SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) as total_credits,
					SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) as total_debits
				FROM {$table}
				WHERE user_id = %d",
				absint( $user_id )
			)
		);

		return array(
			'total_transactions' => (int) $stats->total_transactions,
			'total_credits'       => floatval( $stats->total_credits ),
			'total_debits'        => floatval( $stats->total_debits ),
		);
	}
}
