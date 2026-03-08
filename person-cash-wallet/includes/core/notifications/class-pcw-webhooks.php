<?php
/**
 * Classe de gerenciamento de webhooks
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de webhooks
 */
class PCW_Webhooks {

	/**
	 * Obter todos os webhooks
	 *
	 * @param array $args Argumentos.
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_webhooks';

		$defaults = array(
			'status' => '',
			'order'  => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array();
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$order_by     = sanitize_sql_orderby( "created_at {$args['order']}" );

		$query = "SELECT * FROM {$table} {$where_clause} ORDER BY {$order_by}";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		return $wpdb->get_results( $query );
	}

	/**
	 * Obter webhook por ID
	 *
	 * @param int $webhook_id ID do webhook.
	 * @return object|null
	 */
	public static function get( $webhook_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_webhooks';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				absint( $webhook_id )
			)
		);
	}

	/**
	 * Criar webhook
	 *
	 * @param array $data Dados do webhook.
	 * @return int|false ID do webhook ou false
	 */
	public static function create( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_webhooks';

		$defaults = array(
			'name'           => '',
			'url'            => '',
			'event_type'     => '*',
			'secret_key'     => wp_generate_password( 32, false ),
			'status'         => 'active',
			'retry_count'    => 3,
			'timeout'        => 30,
			'headers'        => '',
			'custom_payload' => '',
		);

		$data = wp_parse_args( $data, $defaults );

		$insert_data = array(
			'name'           => sanitize_text_field( $data['name'] ),
			'url'            => esc_url_raw( $data['url'] ),
			'event_type'     => sanitize_text_field( $data['event_type'] ),
			'secret_key'     => sanitize_text_field( $data['secret_key'] ),
			'status'         => sanitize_text_field( $data['status'] ),
			'retry_count'    => absint( $data['retry_count'] ),
			'timeout'        => absint( $data['timeout'] ),
			'headers'        => ! empty( $data['headers'] ) ? wp_json_encode( $data['headers'] ) : null,
			'custom_payload' => ! empty( $data['custom_payload'] ) ? $data['custom_payload'] : null,
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $insert_data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Atualizar webhook
	 *
	 * @param int   $webhook_id ID do webhook.
	 * @param array $data Dados para atualizar.
	 * @return bool
	 */
	public static function update( $webhook_id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_webhooks';

		$update_data = array(
			'updated_at' => current_time( 'mysql' ),
		);

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
		}

		if ( isset( $data['url'] ) ) {
			$update_data['url'] = esc_url_raw( $data['url'] );
		}

		if ( isset( $data['event_type'] ) ) {
			$update_data['event_type'] = sanitize_text_field( $data['event_type'] );
		}

		if ( isset( $data['secret_key'] ) ) {
			$update_data['secret_key'] = sanitize_text_field( $data['secret_key'] );
		}

		if ( isset( $data['status'] ) ) {
			$update_data['status'] = sanitize_text_field( $data['status'] );
		}

		if ( isset( $data['retry_count'] ) ) {
			$update_data['retry_count'] = absint( $data['retry_count'] );
		}

		if ( isset( $data['timeout'] ) ) {
			$update_data['timeout'] = absint( $data['timeout'] );
		}

		if ( isset( $data['headers'] ) ) {
			$update_data['headers'] = ! empty( $data['headers'] ) ? wp_json_encode( $data['headers'] ) : null;
		}

		if ( isset( $data['custom_payload'] ) ) {
			$update_data['custom_payload'] = ! empty( $data['custom_payload'] ) ? $data['custom_payload'] : null;
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => absint( $webhook_id ) ),
			null,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Excluir webhook
	 *
	 * @param int $webhook_id ID do webhook.
	 * @return bool
	 */
	public static function delete( $webhook_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_webhooks';

		return $wpdb->delete(
			$table,
			array( 'id' => absint( $webhook_id ) ),
			array( '%d' )
		);
	}

	/**
	 * Testar webhook
	 *
	 * @param int $webhook_id ID do webhook.
	 * @return array Resultado do teste
	 */
	public static function test( $webhook_id ) {
		$webhook = self::get( $webhook_id );

		if ( ! $webhook ) {
			return array(
				'success' => false,
				'message' => __( 'Webhook não encontrado.', 'person-cash-wallet' ),
			);
		}

		$test_data = array(
			'test' => true,
			'message' => __( 'Este é um teste de webhook', 'person-cash-wallet' ),
		);

		PCW_Webhook_Handler::trigger( 'test', $test_data );

		return array(
			'success' => true,
			'message' => __( 'Webhook de teste enviado. Verifique os logs.', 'person-cash-wallet' ),
		);
	}

	/**
	 * Obter logs do webhook
	 *
	 * @param int   $webhook_id ID do webhook.
	 * @param array $args Argumentos.
	 * @return array
	 */
	public static function get_logs( $webhook_id, $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_webhook_logs';

		$defaults = array(
			'limit' => 50,
			'offset' => 0,
			'status' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( 'webhook_id = %d' );
		$values = array( absint( $webhook_id ) );

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );

		$query = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d",
			array_merge( $values, array( absint( $args['limit'] ), absint( $args['offset'] ) ) )
		);

		return $wpdb->get_results( $query );
	}
}
