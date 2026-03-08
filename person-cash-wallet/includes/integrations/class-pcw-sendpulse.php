<?php
/**
 * Integração com SendPulse API
 *
 * @package PersonCashWallet
 * @since 1.6.0
 * @see https://sendpulse.com/api
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de integração com SendPulse
 */
class PCW_SendPulse_Integration {

	/**
	 * Instância singleton
	 *
	 * @var PCW_SendPulse_Integration
	 */
	private static $instance = null;

	/**
	 * URL base da API
	 *
	 * @var string
	 */
	private $api_url = 'https://api.sendpulse.com';

	/**
	 * Token de acesso
	 *
	 * @var string|null
	 */
	private $access_token = null;

	/**
	 * Timestamp de expiração do token
	 *
	 * @var int
	 */
	private $token_expires_at = 0;

	/**
	 * Obter instância singleton
	 *
	 * @return PCW_SendPulse_Integration
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor
	 */
	public function __construct() {
		// Carregar token armazenado
		$this->load_token();
	}

	/**
	 * Obter credenciais configuradas
	 *
	 * @return array
	 */
	private function get_credentials() {
		return array(
			'client_id'     => get_option( 'pcw_sendpulse_client_id', '' ),
			'client_secret' => get_option( 'pcw_sendpulse_client_secret', '' ),
		);
	}

	/**
	 * Carregar token armazenado
	 */
	private function load_token() {
		$token_data = get_transient( 'pcw_sendpulse_token' );
		if ( $token_data ) {
			$this->access_token     = $token_data['token'];
			$this->token_expires_at = $token_data['expires_at'];
		}
	}

	/**
	 * Salvar token
	 *
	 * @param string $token Token de acesso.
	 * @param int    $expires_in Tempo de expiração em segundos.
	 */
	private function save_token( $token, $expires_in ) {
		$this->access_token     = $token;
		$this->token_expires_at = time() + $expires_in - 60; // 60s de margem

		set_transient( 'pcw_sendpulse_token', array(
			'token'      => $token,
			'expires_at' => $this->token_expires_at,
		), $expires_in - 60 );
	}

	/**
	 * Verificar se o token é válido
	 *
	 * @return bool
	 */
	private function is_token_valid() {
		return ! empty( $this->access_token ) && time() < $this->token_expires_at;
	}

	/**
	 * Obter token de acesso
	 *
	 * @return string|false Token ou false em caso de erro
	 */
	private function get_access_token() {
		if ( $this->is_token_valid() ) {
			return $this->access_token;
		}

		$credentials = $this->get_credentials();

		if ( empty( $credentials['client_id'] ) || empty( $credentials['client_secret'] ) ) {
			return false;
		}

		$response = wp_remote_post( $this->api_url . '/oauth/access_token', array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body' => wp_json_encode( array(
				'grant_type'    => 'client_credentials',
				'client_id'     => $credentials['client_id'],
				'client_secret' => $credentials['client_secret'],
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Token request failed: ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			$this->log_error( 'Invalid token response: ' . wp_json_encode( $body ) );
			return false;
		}

		$this->save_token( $body['access_token'], $body['expires_in'] ?? 3600 );

		return $this->access_token;
	}

	/**
	 * Fazer requisição à API
	 *
	 * @param string $endpoint Endpoint da API.
	 * @param string $method Método HTTP (GET, POST, etc).
	 * @param array  $data Dados a enviar.
	 * @return array|false Resposta da API ou false
	 */
	private function api_request( $endpoint, $method = 'GET', $data = array() ) {
		$token = $this->get_access_token();

		if ( ! $token ) {
			return false;
		}

		$url = $this->api_url . $endpoint;

		$args = array(
			'timeout' => 30,
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'API request failed: ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$this->log_error( 'API error: ' . wp_json_encode( $body ) );
			return false;
		}

		return $body;
	}

	/**
	 * Enviar email
	 *
	 * @param array $args Argumentos do email.
	 * @return array|false Resultado do envio
	 */
	public function send_email( $args ) {
		$defaults = array(
			'from_email' => get_option( 'pcw_sendpulse_from_email', get_option( 'admin_email' ) ),
			'from_name'  => get_option( 'pcw_sendpulse_from_name', get_bloginfo( 'name' ) ),
			'to_email'   => '',
			'to_name'    => '',
			'subject'    => '',
			'html'       => '',
			'text'       => '',
			'reply_to'   => '',
		);

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['to_email'] ) || empty( $args['subject'] ) ) {
			return false;
		}

		$email_data = array(
			'email' => array(
				'subject' => $args['subject'],
				'from'    => array(
					'name'  => $args['from_name'],
					'email' => $args['from_email'],
				),
				'to' => array(
					array(
						'name'  => $args['to_name'] ?: $args['to_email'],
						'email' => $args['to_email'],
					),
				),
			),
		);

		// HTML ou texto
		if ( ! empty( $args['html'] ) ) {
			$email_data['email']['html'] = $args['html'];
		} elseif ( ! empty( $args['text'] ) ) {
			$email_data['email']['text'] = $args['text'];
		}

		// Reply-to
		if ( ! empty( $args['reply_to'] ) ) {
			$email_data['email']['reply_to'] = $args['reply_to'];
		}

		$result = $this->api_request( '/smtp/emails', 'POST', $email_data );

		if ( $result && isset( $result['is_error'] ) && ! $result['is_error'] ) {
			return array(
				'success'    => true,
				'message_id' => $result['id'] ?? null,
			);
		}

		return false;
	}

	/**
	 * Testar conexão
	 *
	 * @return array Status da conexão
	 */
	public function test_connection() {
		$token = $this->get_access_token();

		if ( ! $token ) {
			return array(
				'success' => false,
				'message' => __( 'Falha ao obter token de acesso. Verifique as credenciais.', 'person-cash-wallet' ),
			);
		}

		// Testar fazendo uma requisição simples
		$result = $this->api_request( '/smtp/emails/total' );

		if ( $result !== false ) {
			return array(
				'success' => true,
				'message' => __( 'Conexão com SendPulse estabelecida com sucesso!', 'person-cash-wallet' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Erro ao conectar com SendPulse.', 'person-cash-wallet' ),
		);
	}

	/**
	 * Obter estatísticas do SMTP
	 *
	 * @return array|false
	 */
	public function get_smtp_statistics() {
		return $this->api_request( '/smtp/emails/total' );
	}

	/**
	 * Registrar erro no log
	 *
	 * @param string $message Mensagem de erro.
	 */
	private function log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[PCW SendPulse] ' . $message );
		}
	}
}
