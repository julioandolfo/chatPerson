<?php
/**
 * Classe handler de webhooks
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe handler de webhooks
 */
class PCW_Webhook_Handler {

	/**
	 * Disparar webhook
	 *
	 * @param string $event_type Tipo do evento.
	 * @param array  $data Dados do evento.
	 */
	public static function trigger( $event_type, $data ) {
		// Buscar webhooks ativos para este evento
		$webhooks = self::get_active_webhooks( $event_type );

		foreach ( $webhooks as $webhook ) {
			self::send_webhook( $webhook, $event_type, $data );
		}
	}

	/**
	 * Obter webhooks ativos para um evento
	 *
	 * @param string $event_type Tipo do evento.
	 * @return array
	 */
	private static function get_active_webhooks( $event_type ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_webhooks';

		// Buscar webhooks que correspondem ao evento ou são wildcard
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
				WHERE status = 'active' 
				AND (event_type = %s OR event_type = '*' OR %s LIKE CONCAT(event_type, '.%%'))
				ORDER BY id ASC",
				$event_type,
				$event_type
			)
		);
	}

	/**
	 * Enviar webhook
	 *
	 * @param object $webhook Dados do webhook.
	 * @param string $event_type Tipo do evento.
	 * @param array  $data Dados do evento.
	 */
	private static function send_webhook( $webhook, $event_type, $data ) {
		// Verificar se tem payload customizado
		if ( ! empty( $webhook->custom_payload ) ) {
			// Usar payload customizado com substituição de variáveis
			$payload_string = PCW_Webhook_Variables::replace_variables( $webhook->custom_payload, $event_type, $data );
			
			// Validar JSON
			$validation = PCW_Webhook_Variables::validate_json( $payload_string );
			if ( ! $validation['valid'] ) {
				// Se o JSON customizado for inválido, usar padrão
				error_log( 'PCW Webhook: Payload customizado inválido para webhook #' . $webhook->id . ': ' . $validation['error'] );
				$payload = array(
					'event'     => $event_type,
					'timestamp' => current_time( 'mysql' ),
					'data'      => $data,
				);
				$payload_string = wp_json_encode( $payload );
			}
		} else {
			// Payload padrão
			$payload = array(
				'event'     => $event_type,
				'timestamp' => current_time( 'mysql' ),
				'data'      => $data,
			);
			$payload_string = wp_json_encode( $payload );
		}

		// Gerar assinatura HMAC
		$signature = self::generate_signature( $payload_string, $webhook->secret_key );

		// Preparar headers
		$headers = array(
			'Content-Type'    => 'application/json',
			'X-PCW-Event'     => $event_type,
			'X-PCW-Signature' => $signature,
			'X-PCW-Timestamp' => time(),
		);

		// Adicionar headers customizados
		if ( ! empty( $webhook->headers ) ) {
			$custom_headers = json_decode( $webhook->headers, true );
			if ( is_array( $custom_headers ) ) {
				$headers = array_merge( $headers, $custom_headers );
			}
		}

		// Enviar requisição
		$response = wp_remote_post(
			$webhook->url,
			array(
				'method'  => 'POST',
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
				'timeout' => absint( $webhook->timeout ),
				'sslverify' => true,
			)
		);

		// Log da tentativa
		$log_data = array(
			'webhook_id'    => $webhook->id,
			'event_type'    => $event_type,
			'payload'       => wp_json_encode( $payload ),
			'response_code' => wp_remote_retrieve_response_code( $response ),
			'response_body' => wp_remote_retrieve_body( $response ),
			'status'        => ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) < 400 ? 'success' : 'failed',
			'retry_count'   => 0,
			'error_message' => is_wp_error( $response ) ? $response->get_error_message() : null,
		);

		self::log_webhook( $log_data );

		// Se falhou e tem retry configurado, agendar retry
		if ( 'failed' === $log_data['status'] && $webhook->retry_count > 0 ) {
			self::schedule_retry( $webhook, $payload );
		}
	}

	/**
	 * Gerar assinatura HMAC SHA256
	 *
	 * @param string $payload_string Payload string.
	 * @param string $secret_key Chave secreta.
	 * @return string
	 */
	private static function generate_signature( $payload_string, $secret_key ) {
		return hash_hmac( 'sha256', $payload_string, $secret_key );
	}

	/**
	 * Log de webhook
	 *
	 * @param array $log_data Dados do log.
	 * @return int|false ID do log ou false
	 */
	private static function log_webhook( $log_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_webhook_logs';

		$insert_data = array(
			'webhook_id'    => absint( $log_data['webhook_id'] ),
			'event_type'    => sanitize_text_field( $log_data['event_type'] ),
			'payload'       => $log_data['payload'],
			'response_code' => isset( $log_data['response_code'] ) ? absint( $log_data['response_code'] ) : null,
			'response_body' => isset( $log_data['response_body'] ) ? sanitize_textarea_field( $log_data['response_body'] ) : null,
			'status'        => sanitize_text_field( $log_data['status'] ),
			'retry_count'   => absint( $log_data['retry_count'] ),
			'error_message' => isset( $log_data['error_message'] ) ? sanitize_textarea_field( $log_data['error_message'] ) : null,
			'created_at'    => current_time( 'mysql' ),
		);

		return $wpdb->insert( $table, $insert_data );
	}

	/**
	 * Agendar retry
	 *
	 * @param object $webhook Dados do webhook.
	 * @param array  $payload Payload original.
	 */
	private static function schedule_retry( $webhook, $payload ) {
		// Buscar último log para obter retry_count
		global $wpdb;
		$logs_table = $wpdb->prefix . 'pcw_webhook_logs';

		$last_log = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$logs_table} 
				WHERE webhook_id = %d 
				AND event_type = %s 
				ORDER BY created_at DESC 
				LIMIT 1",
				$webhook->id,
				$payload['event']
			)
		);

		$retry_count = $last_log ? (int) $last_log->retry_count + 1 : 1;

		if ( $retry_count <= absint( $webhook->retry_count ) ) {
			// Agendar retry após 5 minutos
			wp_schedule_single_event( time() + ( 5 * 60 ), 'pcw_webhook_retry', array( $webhook->id, $payload ) );
		}
	}

	/**
	 * Processar retry agendado
	 *
	 * @param int   $webhook_id ID do webhook.
	 * @param array $payload Payload original.
	 */
	public static function process_retry( $webhook_id, $payload ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_webhooks';

		$webhook = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				absint( $webhook_id )
			)
		);

		if ( ! $webhook || 'active' !== $webhook->status ) {
			return;
		}

		self::send_webhook( $webhook, $payload['event'], $payload['data'] );
	}
}

// Hook para processar retries
add_action( 'pcw_webhook_retry', array( 'PCW_Webhook_Handler', 'process_retry' ), 10, 2 );
