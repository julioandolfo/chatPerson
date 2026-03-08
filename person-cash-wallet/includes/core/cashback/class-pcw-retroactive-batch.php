<?php
/**
 * Gerenciamento de Batches de Cashback Retroativo
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Classe para gerenciar batches de processamento retroativo
 */
class PCW_Retroactive_Batch {

	/**
	 * Nome da tabela
	 *
	 * @var string
	 */
	private static $table_name = 'pcw_retroactive_batches';

	/**
	 * Criar tabela de batches
	 */
	public static function create_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . self::$table_name;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			batch_id varchar(100) NOT NULL,
			created_at datetime NOT NULL,
			filters longtext NOT NULL,
			total_orders int(11) NOT NULL DEFAULT 0,
			total_cashback int(11) NOT NULL DEFAULT 0,
			total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			status varchar(20) NOT NULL DEFAULT 'pending',
			processed_by bigint(20) NOT NULL,
			completed_at datetime DEFAULT NULL,
			error_log longtext DEFAULT NULL,
			PRIMARY KEY (id),
			KEY batch_id (batch_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar novo batch
	 *
	 * @param array $filters Filtros usados.
	 * @return string Batch ID.
	 */
	public static function create( $filters ) {
		global $wpdb;

		$batch_id = wp_generate_uuid4();
		$table    = $wpdb->prefix . self::$table_name;

		$wpdb->insert(
			$table,
			array(
				'batch_id'     => $batch_id,
				'created_at'   => current_time( 'mysql' ),
				'filters'      => wp_json_encode( $filters ),
				'status'       => 'pending',
				'processed_by' => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		return $batch_id;
	}

	/**
	 * Atualizar status do batch
	 *
	 * @param string $batch_id Batch ID.
	 * @param string $status Status.
	 * @param array  $data Dados adicionais.
	 */
	public static function update_status( $batch_id, $status, $data = array() ) {
		global $wpdb;

		$table       = $wpdb->prefix . self::$table_name;
		$update_data = array( 'status' => $status );

		if ( 'completed' === $status ) {
			$update_data['completed_at'] = current_time( 'mysql' );
		}

		if ( ! empty( $data['total_orders'] ) ) {
			$update_data['total_orders'] = absint( $data['total_orders'] );
		}

		if ( ! empty( $data['total_cashback'] ) ) {
			$update_data['total_cashback'] = absint( $data['total_cashback'] );
		}

		if ( isset( $data['total_amount'] ) ) {
			$update_data['total_amount'] = floatval( $data['total_amount'] );
		}

		if ( ! empty( $data['error_log'] ) ) {
			$update_data['error_log'] = $data['error_log'];
		}

		$wpdb->update(
			$table,
			$update_data,
			array( 'batch_id' => $batch_id ),
			array_fill( 0, count( $update_data ), '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Buscar batch por ID
	 *
	 * @param string $batch_id Batch ID.
	 * @return object|null
	 */
	public static function get( $batch_id ) {
		global $wpdb;

		$table = $wpdb->prefix . self::$table_name;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE batch_id = %s",
				$batch_id
			)
		);
	}

	/**
	 * Listar todos os batches
	 *
	 * @param int $limit Limite de resultados.
	 * @param int $offset Offset.
	 * @return array
	 */
	public static function get_all( $limit = 50, $offset = 0 ) {
		global $wpdb;

		$table = $wpdb->prefix . self::$table_name;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
	}

	/**
	 * Contar total de batches
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;

		$table = $wpdb->prefix . self::$table_name;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Cancelar batch em processamento (travado)
	 *
	 * @param string $batch_id Batch ID.
	 * @return array Resultado.
	 */
	public static function cancel( $batch_id ) {
		global $wpdb;

		$batch = self::get( $batch_id );

		if ( ! $batch ) {
			return array(
				'success' => false,
				'message' => __( 'Batch não encontrado.', 'person-cash-wallet' ),
			);
		}

		if ( ! in_array( $batch->status, array( 'pending', 'processing' ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Apenas batches pendentes ou em processamento podem ser cancelados.', 'person-cash-wallet' ),
			);
		}

		// Cancelar cashbacks já gerados por este batch.
		$cashback_table = $wpdb->prefix . 'pcw_cashback';
		$cancelled      = 0;

		$cashbacks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$cashback_table} WHERE source = 'retroactive' AND description LIKE %s",
				'%' . $wpdb->esc_like( $batch_id ) . '%'
			)
		);

		foreach ( $cashbacks as $cashback ) {
			if ( in_array( $cashback->status, array( 'pending', 'available', 'expired' ), true ) ) {
				$wpdb->update(
					$cashback_table,
					array( 'status' => 'cancelled' ),
					array( 'id' => $cashback->id ),
					array( '%s' ),
					array( '%d' )
				);
				$cancelled++;
			}
		}

		// Atualizar status do batch.
		self::update_status(
			$batch_id,
			'cancelled',
			array(
				'error_log' => sprintf(
					/* translators: %d: Number of cancelled cashbacks */
					__( 'Batch cancelado manualmente. %d cashbacks cancelados.', 'person-cash-wallet' ),
					$cancelled
				),
			)
		);

		do_action( 'pcw_retroactive_batch_cancelled', $batch_id, $cancelled );

		return array(
			'success'   => true,
			'cancelled' => $cancelled,
			'message'   => sprintf(
				/* translators: %d: Number of cancelled cashbacks */
				__( 'Batch cancelado com sucesso. %d cashbacks foram revertidos.', 'person-cash-wallet' ),
				$cancelled
			),
		);
	}

	/**
	 * Reverter batch
	 *
	 * @param string $batch_id Batch ID.
	 * @return array Resultado com sucessos e falhas.
	 */
	public static function revert( $batch_id ) {
		global $wpdb;

		$batch = self::get( $batch_id );

		if ( ! $batch || ! in_array( $batch->status, array( 'completed', 'processing' ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Batch não encontrado ou não pode ser revertido.', 'person-cash-wallet' ),
			);
		}

		$table     = $wpdb->prefix . 'pcw_cashback';
		$cashbacks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE source = 'retroactive' AND description LIKE %s",
				'%' . $wpdb->esc_like( $batch_id ) . '%'
			)
		);

		$reverted = 0;
		$failed   = 0;
		$errors   = array();

		foreach ( $cashbacks as $cashback ) {
			// Só pode reverter se estiver disponível ou expirado.
			if ( in_array( $cashback->status, array( 'available', 'expired' ), true ) ) {
				$wpdb->update(
					$table,
					array( 'status' => 'cancelled' ),
					array( 'id' => $cashback->id ),
					array( '%s' ),
					array( '%d' )
				);
				$reverted++;
			} else {
				$failed++;
				$errors[] = sprintf(
					/* translators: %d: Cashback ID, %s: Status */
					__( 'Cashback #%d não pôde ser revertido (status: %s)', 'person-cash-wallet' ),
					$cashback->id,
					$cashback->status
				);
			}
		}

		// Atualizar status do batch.
		self::update_status(
			$batch_id,
			'reverted',
			array(
				'error_log' => implode( "\n", $errors ),
			)
		);

		do_action( 'pcw_retroactive_batch_reverted', $batch_id, $reverted, $failed );

		return array(
			'success'  => true,
			'reverted' => $reverted,
			'failed'   => $failed,
			'errors'   => $errors,
		);
	}

	/**
	 * Deletar batch antigo
	 *
	 * @param string $batch_id Batch ID.
	 * @return bool
	 */
	public static function delete( $batch_id ) {
		global $wpdb;

		$table = $wpdb->prefix . self::$table_name;

		return (bool) $wpdb->delete(
			$table,
			array( 'batch_id' => $batch_id ),
			array( '%s' )
		);
	}
}
