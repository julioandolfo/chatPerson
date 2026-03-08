<?php
/**
 * Integração com Google Analytics 4
 *
 * @package PersonCashWallet
 * @since 1.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de integração com Google Analytics 4
 */
class PCW_Google_Analytics {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Google_Analytics
	 */
	private static $instance = null;

	/**
	 * Property ID do GA4
	 *
	 * @var string
	 */
	private $property_id = '';

	/**
	 * Credenciais do Service Account
	 *
	 * @var array
	 */
	private $credentials = array();

	/**
	 * Access token atual
	 *
	 * @var string
	 */
	private $access_token = '';

	/**
	 * Cache de dados
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Obter instância singleton
	 *
	 * @return PCW_Google_Analytics
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor
	 */
	private function __construct() {
		$this->reload_credentials();

		add_action( 'wp_ajax_pcw_get_ga4_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_pcw_ga4_simple_test', array( $this, 'ajax_simple_test' ) );
		add_action( 'wp_ajax_pcw_test_ga4_connection', array( $this, 'ajax_test_connection' ) );
	}

	/**
	 * Recarregar credenciais do banco de dados
	 */
	public function reload_credentials() {
		$this->property_id = get_option( 'pcw_ga4_property_id', '' );
		$credentials_json  = get_option( 'pcw_ga4_credentials', '' );

		if ( ! empty( $credentials_json ) ) {
			$this->credentials = json_decode( $credentials_json, true );
		} else {
			$this->credentials = array();
		}

		// Limpar token se credenciais mudaram
		$this->access_token = '';
	}

	/**
	 * Verificar se está configurado
	 *
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( $this->property_id ) && ! empty( $this->credentials );
	}

	/**
	 * AJAX: Teste simples de conexão (para debug)
	 */
	public function ajax_simple_test() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		// Recarregar credenciais
		$this->reload_credentials();
		$this->clear_cache();

		if ( ! $this->is_configured() ) {
			wp_send_json_error( array( 
				'message'     => 'GA4 não configurado',
				'property_id' => $this->property_id,
				'has_creds'   => ! empty( $this->credentials ),
			) );
		}

		// Tentar obter token
		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			wp_send_json_error( array( 
				'message' => 'Erro ao obter token: ' . $token->get_error_message(),
				'step'    => 'get_access_token',
			) );
		}

		// Tentar obter usuários em tempo real
		$realtime = $this->get_realtime_users();
		if ( is_wp_error( $realtime ) ) {
			wp_send_json_error( array( 
				'message' => 'Erro realtime: ' . $realtime->get_error_message(),
				'step'    => 'get_realtime_users',
			) );
		}

		wp_send_json_success( array( 
			'message'        => 'Conexão OK!',
			'realtime_users' => $realtime,
			'property_id'    => $this->property_id,
		) );
	}

	/**
	 * Obter access token via JWT
	 *
	 * @return string|WP_Error
	 */
	private function get_access_token() {
		if ( ! empty( $this->access_token ) ) {
			return $this->access_token;
		}

		$cached_token = get_transient( 'pcw_ga4_access_token' );
		if ( $cached_token ) {
			$this->access_token = $cached_token;
			return $cached_token;
		}

		if ( empty( $this->credentials['private_key'] ) || empty( $this->credentials['client_email'] ) ) {
			return new WP_Error( 'missing_credentials', __( 'Credenciais do GA4 não configuradas', 'person-cash-wallet' ) );
		}

		// Criar JWT
		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		);

		$now    = time();
		$claims = array(
			'iss'   => $this->credentials['client_email'],
			'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'iat'   => $now,
			'exp'   => $now + 3600,
		);

		$header_encoded  = $this->base64url_encode( wp_json_encode( $header ) );
		$claims_encoded  = $this->base64url_encode( wp_json_encode( $claims ) );
		$signature_input = $header_encoded . '.' . $claims_encoded;

		// Assinar com chave privada
		$private_key = openssl_pkey_get_private( $this->credentials['private_key'] );
		if ( ! $private_key ) {
			return new WP_Error( 'invalid_key', __( 'Chave privada inválida', 'person-cash-wallet' ) );
		}

		$signature = '';
		if ( ! openssl_sign( $signature_input, $signature, $private_key, OPENSSL_ALGO_SHA256 ) ) {
			return new WP_Error( 'sign_error', __( 'Erro ao assinar JWT', 'person-cash-wallet' ) );
		}

		$jwt = $signature_input . '.' . $this->base64url_encode( $signature );

		// Trocar JWT por access token
		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'body' => array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $jwt,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'token_error', $body['error_description'] ?? $body['error'] );
		}

		if ( ! isset( $body['access_token'] ) ) {
			return new WP_Error( 'no_token', __( 'Token não recebido', 'person-cash-wallet' ) );
		}

		$this->access_token = $body['access_token'];
		$expires_in         = isset( $body['expires_in'] ) ? intval( $body['expires_in'] ) - 60 : 3540;
		set_transient( 'pcw_ga4_access_token', $this->access_token, $expires_in );

		return $this->access_token;
	}

	/**
	 * Base64 URL encode
	 *
	 * @param string $data Dados.
	 * @return string
	 */
	private function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Fazer requisição à API do GA4
	 *
	 * @param array $request_body Corpo da requisição.
	 * @return array|WP_Error
	 */
	private function api_request( $request_body ) {
		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url = sprintf(
			'https://analyticsdata.googleapis.com/v1beta/properties/%s:runReport',
			$this->property_id
		);

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $request_body ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error(
				'api_error',
				$body['error']['message'] ?? __( 'Erro na API do GA4', 'person-cash-wallet' )
			);
		}

		return $body;
	}

	/**
	 * Fazer requisição de realtime à API do GA4
	 *
	 * @param array $request_body Corpo da requisição.
	 * @return array|WP_Error
	 */
	private function realtime_request( $request_body ) {
		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url = sprintf(
			'https://analyticsdata.googleapis.com/v1beta/properties/%s:runRealtimeReport',
			$this->property_id
		);

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $request_body ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error(
				'api_error',
				$body['error']['message'] ?? __( 'Erro na API do GA4', 'person-cash-wallet' )
			);
		}

		return $body;
	}

	/**
	 * Obter usuários em tempo real
	 *
	 * @return int|WP_Error
	 */
	public function get_realtime_users() {
		$cache_key = 'pcw_ga4_realtime';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->realtime_request( array(
			'metrics' => array(
				array( 'name' => 'activeUsers' ),
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$users = 0;
		if ( isset( $response['rows'][0]['metricValues'][0]['value'] ) ) {
			$users = intval( $response['rows'][0]['metricValues'][0]['value'] );
		}

		set_transient( $cache_key, $users, 60 ); // Cache 1 minuto

		return $users;
	}

	/**
	 * Obter estatísticas do período
	 *
	 * @param string $period Período (today, 7days, 30days).
	 * @return array|WP_Error
	 */
	public function get_stats( $period = '7days' ) {
		$cache_key = 'pcw_ga4_stats_' . $period;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		// Determinar range de datas
		switch ( $period ) {
			case 'today':
				$start_date = 'today';
				$end_date   = 'today';
				break;
			case '7days':
				$start_date = '7daysAgo';
				$end_date   = 'today';
				break;
			case '30days':
				$start_date = '30daysAgo';
				$end_date   = 'today';
				break;
			default:
				$start_date = '7daysAgo';
				$end_date   = 'today';
		}

		// Métricas principais (max 10 por request)
		$response = $this->api_request( array(
			'dateRanges' => array(
				array(
					'startDate' => $start_date,
					'endDate'   => $end_date,
				),
			),
			'metrics'    => array(
				array( 'name' => 'activeUsers' ),
				array( 'name' => 'newUsers' ),
				array( 'name' => 'sessions' ),
				array( 'name' => 'screenPageViews' ),
				array( 'name' => 'bounceRate' ),
				array( 'name' => 'averageSessionDuration' ),
				array( 'name' => 'screenPageViewsPerSession' ),
				array( 'name' => 'engagementRate' ),
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$stats = $this->parse_metrics_response( $response );

		// Segunda request para métricas de e-commerce
		$ecommerce_response = $this->api_request( array(
			'dateRanges' => array(
				array(
					'startDate' => $start_date,
					'endDate'   => $end_date,
				),
			),
			'metrics'    => array(
				array( 'name' => 'ecommercePurchases' ),
				array( 'name' => 'addToCarts' ),
			),
		) );

		if ( ! is_wp_error( $ecommerce_response ) && isset( $ecommerce_response['rows'][0]['metricValues'] ) ) {
			$ecom_values = $ecommerce_response['rows'][0]['metricValues'];
			$stats['purchases']    = isset( $ecom_values[0]['value'] ) ? intval( $ecom_values[0]['value'] ) : 0;
			$stats['add_to_carts'] = isset( $ecom_values[1]['value'] ) ? intval( $ecom_values[1]['value'] ) : 0;
		}

		// Buscar fontes de tráfego
		$traffic_response = $this->api_request( array(
			'dateRanges' => array(
				array(
					'startDate' => $start_date,
					'endDate'   => $end_date,
				),
			),
			'dimensions' => array(
				array( 'name' => 'sessionDefaultChannelGroup' ),
			),
			'metrics'    => array(
				array( 'name' => 'sessions' ),
				array( 'name' => 'activeUsers' ),
			),
			'orderBys'   => array(
				array(
					'metric' => array( 'metricName' => 'sessions' ),
					'desc'   => true,
				),
			),
			'limit'      => 10,
		) );

		if ( ! is_wp_error( $traffic_response ) ) {
			$stats['traffic_sources'] = $this->parse_dimension_response( $traffic_response, 'channel' );
		}

		// Buscar dispositivos
		$device_response = $this->api_request( array(
			'dateRanges' => array(
				array(
					'startDate' => $start_date,
					'endDate'   => $end_date,
				),
			),
			'dimensions' => array(
				array( 'name' => 'deviceCategory' ),
			),
			'metrics'    => array(
				array( 'name' => 'sessions' ),
				array( 'name' => 'activeUsers' ),
			),
		) );

		if ( ! is_wp_error( $device_response ) ) {
			$stats['devices'] = $this->parse_dimension_response( $device_response, 'device' );
		}

		// Buscar países
		$country_response = $this->api_request( array(
			'dateRanges' => array(
				array(
					'startDate' => $start_date,
					'endDate'   => $end_date,
				),
			),
			'dimensions' => array(
				array( 'name' => 'country' ),
			),
			'metrics'    => array(
				array( 'name' => 'sessions' ),
				array( 'name' => 'activeUsers' ),
			),
			'orderBys'   => array(
				array(
					'metric' => array( 'metricName' => 'sessions' ),
					'desc'   => true,
				),
			),
			'limit'      => 10,
		) );

		if ( ! is_wp_error( $country_response ) ) {
			$stats['countries'] = $this->parse_dimension_response( $country_response, 'country' );
		}

		// Buscar páginas mais visitadas
		$pages_response = $this->api_request( array(
			'dateRanges' => array(
				array(
					'startDate' => $start_date,
					'endDate'   => $end_date,
				),
			),
			'dimensions' => array(
				array( 'name' => 'pagePath' ),
			),
			'metrics'    => array(
				array( 'name' => 'screenPageViews' ),
				array( 'name' => 'activeUsers' ),
			),
			'orderBys'   => array(
				array(
					'metric' => array( 'metricName' => 'screenPageViews' ),
					'desc'   => true,
				),
			),
			'limit'      => 10,
		) );

		if ( ! is_wp_error( $pages_response ) ) {
			$stats['top_pages'] = $this->parse_dimension_response( $pages_response, 'page' );
		}

		// Cache por 5 minutos
		$cache_time = ( 'today' === $period ) ? 120 : 300;
		set_transient( $cache_key, $stats, $cache_time );

		return $stats;
	}

	/**
	 * Parsear resposta de métricas
	 *
	 * @param array $response Resposta da API.
	 * @return array
	 */
	private function parse_metrics_response( $response ) {
		$stats = array(
			'users'                    => 0,
			'new_users'                => 0,
			'sessions'                 => 0,
			'pageviews'                => 0,
			'bounce_rate'              => 0,
			'avg_session_duration'     => 0,
			'pages_per_session'        => 0,
			'engagement_rate'          => 0,
			'purchases'                => 0,
			'add_to_carts'             => 0,
		);

		if ( ! isset( $response['rows'][0]['metricValues'] ) ) {
			return $stats;
		}

		$values = $response['rows'][0]['metricValues'];
		// Ordem das métricas na primeira request (8 métricas)
		$metrics = array(
			'users',
			'new_users',
			'sessions',
			'pageviews',
			'bounce_rate',
			'avg_session_duration',
			'pages_per_session',
			'engagement_rate',
		);

		foreach ( $values as $index => $value ) {
			if ( isset( $metrics[ $index ] ) ) {
				$stats[ $metrics[ $index ] ] = floatval( $value['value'] );
			}
		}

		// Formatar valores
		$stats['bounce_rate']          = round( $stats['bounce_rate'] * 100, 1 );
		$stats['engagement_rate']      = round( $stats['engagement_rate'] * 100, 1 );
		$stats['avg_session_duration'] = round( $stats['avg_session_duration'] );
		$stats['pages_per_session']    = round( $stats['pages_per_session'], 1 );

		return $stats;
	}

	/**
	 * Parsear resposta de dimensões
	 *
	 * @param array  $response Resposta da API.
	 * @param string $type Tipo de dimensão.
	 * @return array
	 */
	private function parse_dimension_response( $response, $type ) {
		$result = array();

		if ( ! isset( $response['rows'] ) ) {
			return $result;
		}

		foreach ( $response['rows'] as $row ) {
			$dimension = $row['dimensionValues'][0]['value'] ?? '';
			$sessions  = intval( $row['metricValues'][0]['value'] ?? 0 );
			$users     = intval( $row['metricValues'][1]['value'] ?? 0 );

			$result[] = array(
				'name'     => $dimension,
				'sessions' => $sessions,
				'users'    => $users,
			);
		}

		return $result;
	}

	/**
	 * Comparar com tracking interno
	 *
	 * @param string $period Período.
	 * @return array
	 */
	public function compare_with_internal( $period = '7days' ) {
		$ga_stats = $this->get_stats( $period );

		if ( is_wp_error( $ga_stats ) ) {
			return array(
				'error'   => true,
				'message' => $ga_stats->get_error_message(),
			);
		}

		// Obter stats internos
		$tracker        = PCW_Activity_Tracker::instance();
		$internal_stats = $tracker->get_stats( $period );

		// Garantir que internal_stats tenha todos os campos necessários
		$internal_stats = wp_parse_args( $internal_stats, array(
			'visitors'     => 0,
			'pageviews'    => 0,
			'add_to_cart'  => 0,
			'orders'       => 0,
		) );

		// Calcular diferenças
		$comparison = array(
			'visitors' => array(
				'internal'   => intval( $internal_stats['visitors'] ),
				'ga'         => intval( $ga_stats['users'] ?? 0 ),
				'diff'       => intval( $internal_stats['visitors'] ) - intval( $ga_stats['users'] ?? 0 ),
				'diff_pct'   => $this->calculate_diff_percent( $internal_stats['visitors'], $ga_stats['users'] ?? 0 ),
			),
			'pageviews' => array(
				'internal'   => intval( $internal_stats['pageviews'] ),
				'ga'         => intval( $ga_stats['pageviews'] ?? 0 ),
				'diff'       => intval( $internal_stats['pageviews'] ) - intval( $ga_stats['pageviews'] ?? 0 ),
				'diff_pct'   => $this->calculate_diff_percent( $internal_stats['pageviews'], $ga_stats['pageviews'] ?? 0 ),
			),
			'add_to_cart' => array(
				'internal'   => intval( $internal_stats['add_to_cart'] ),
				'ga'         => intval( $ga_stats['add_to_carts'] ?? 0 ),
				'diff'       => intval( $internal_stats['add_to_cart'] ) - intval( $ga_stats['add_to_carts'] ?? 0 ),
				'diff_pct'   => $this->calculate_diff_percent( $internal_stats['add_to_cart'], $ga_stats['add_to_carts'] ?? 0 ),
			),
			'orders' => array(
				'internal'   => intval( $internal_stats['orders'] ),
				'ga'         => intval( $ga_stats['purchases'] ?? 0 ),
				'diff'       => intval( $internal_stats['orders'] ) - intval( $ga_stats['purchases'] ?? 0 ),
				'diff_pct'   => $this->calculate_diff_percent( $internal_stats['orders'], $ga_stats['purchases'] ?? 0 ),
			),
		);

		return array(
			'ga_stats'       => $ga_stats,
			'internal_stats' => $internal_stats,
			'comparison'     => $comparison,
			'period'         => $period,
		);
	}

	/**
	 * Calcular diferença percentual
	 *
	 * @param int|float $a Valor A.
	 * @param int|float $b Valor B.
	 * @return float
	 */
	private function calculate_diff_percent( $a, $b ) {
		if ( 0 == $b ) {
			return 0 == $a ? 0 : 100;
		}
		return round( ( ( $a - $b ) / $b ) * 100, 1 );
	}

	/**
	 * Limpar todos os caches do GA4
	 */
	public function clear_cache() {
		delete_transient( 'pcw_ga4_access_token' );
		delete_transient( 'pcw_ga4_realtime' );
		delete_transient( 'pcw_ga4_stats_today' );
		delete_transient( 'pcw_ga4_stats_7days' );
		delete_transient( 'pcw_ga4_stats_30days' );
		$this->access_token = '';
	}

	/**
	 * AJAX: Obter estatísticas do GA4
	 */
	public function ajax_get_stats() {
		// Verificar nonce - tentar ambos os nonces possíveis
		$nonce_valid = wp_verify_nonce( $_POST['nonce'] ?? '', 'pcw_live_dashboard' );
		if ( ! $nonce_valid ) {
			$nonce_valid = wp_verify_nonce( $_POST['nonce'] ?? '', 'pcw_admin' );
		}

		if ( ! $nonce_valid ) {
			wp_send_json_error( array( 'message' => 'Nonce inválido. Recarregue a página.' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		// Recarregar credenciais do banco
		$this->reload_credentials();

		if ( ! $this->is_configured() ) {
			wp_send_json_error( array( 'message' => 'GA4 não configurado', 'not_configured' => true ) );
		}

		// Limpar cache se solicitado
		if ( ! empty( $_POST['clear_cache'] ) ) {
			$this->clear_cache();
		}

		$period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : '7days';

		try {
			$data = $this->compare_with_internal( $period );

			if ( isset( $data['error'] ) ) {
				wp_send_json_error( array( 
					'message' => $data['message'],
					'debug'   => 'compare_with_internal returned error',
				) );
			}

			// Adicionar usuários em tempo real
			$realtime = $this->get_realtime_users();
			if ( ! is_wp_error( $realtime ) ) {
				$data['realtime_users'] = $realtime;
			} else {
				$data['realtime_error'] = $realtime->get_error_message();
			}

			wp_send_json_success( $data );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 
				'message' => 'Exceção: ' . $e->getMessage(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
			) );
		}
	}

	/**
	 * AJAX: Testar conexão com GA4
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'pcw_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		if ( ! $this->is_configured() ) {
			wp_send_json_error( array( 'message' => 'Configure o Property ID e as credenciais primeiro' ) );
		}

		// Limpar todos os caches
		$this->clear_cache();

		// Tentar obter usuários em tempo real como teste
		$result = $this->get_realtime_users();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'        => 'Conexão estabelecida com sucesso!',
			'realtime_users' => $result,
		) );
	}

	/**
	 * Formatar duração em segundos para texto
	 *
	 * @param int $seconds Segundos.
	 * @return string
	 */
	public static function format_duration( $seconds ) {
		if ( $seconds < 60 ) {
			return $seconds . 's';
		}

		$minutes = floor( $seconds / 60 );
		$secs    = $seconds % 60;

		if ( $minutes < 60 ) {
			return sprintf( '%dm %ds', $minutes, $secs );
		}

		$hours = floor( $minutes / 60 );
		$mins  = $minutes % 60;

		return sprintf( '%dh %dm', $hours, $mins );
	}
}
