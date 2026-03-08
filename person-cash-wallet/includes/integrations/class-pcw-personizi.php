<?php
/**
 * Integração com Personizi Chat/WhatsApp
 *
 * @package PersonCashWallet
 * @since 1.2.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCW_Personizi_Integration {

	/**
	 * API Base URL
	 */
	const API_BASE_URL = 'https://chat.personizi.com.br/api.php';

	/**
	 * API Token
	 *
	 * @var string
	 */
	private $api_token;

	/**
	 * Número WhatsApp Padrão (from)
	 *
	 * @var string
	 */
	private $default_from;

	/**
	 * Cache de contas WhatsApp
	 *
	 * @var array|null
	 */
	private $accounts_cache = null;

	/**
	 * Instância única
	 */
	private static $instance = null;

	/**
	 * Obter instância única
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor
	 */
	public function __construct() {
		$this->api_token    = get_option( 'pcw_personizi_token', 'b481e4bb3d224638a498be99ae3e411c2f414e71a69f081039edd0c4dff99912' );
		$this->default_from = get_option( 'pcw_personizi_default_from', '5511916127354' );
		
		// Log de inicialização
		$this->log( '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'INFO' );
		$this->log( '🔧 PERSONIZI INTEGRATION INICIALIZADA', 'INFO' );
		$this->log( 'Token carregado (length): ' . strlen( $this->api_token ), 'DEBUG' );
		$this->log( 'Token value: ' . $this->api_token, 'DEBUG' );
		$this->log( 'Default From: ' . $this->default_from, 'DEBUG' );
		$this->log( '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'INFO' );
	}

	/**
	 * Registrar log em arquivo específico
	 *
	 * @param string $message Mensagem a ser logada
	 * @param string $level Nível do log (INFO, ERROR, DEBUG)
	 */
	private function log( $message, $level = 'INFO' ) {
		$log_dir = WP_CONTENT_DIR . '/pcw-logs';
		
		// Criar diretório se não existir
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}
		
		$log_file = $log_dir . '/webhook-whats.log';
		$timestamp = current_time( 'Y-m-d H:i:s' );
		$log_entry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
		
		// Escrever no arquivo
		file_put_contents( $log_file, $log_entry, FILE_APPEND );
		
		// Também logar no debug.log padrão se WP_DEBUG estiver ativo
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[Personizi {$level}] {$message}" );
		}
	}

	/**
	 * Fazer requisição à API
	 *
	 * @param string $endpoint Endpoint da API (ex: /messages/send)
	 * @param string $method Método HTTP (GET, POST, PUT, DELETE)
	 * @param array  $data Dados para enviar
	 * @return array|WP_Error
	 */
	private function request( $endpoint, $method = 'GET', $data = array() ) {
		$url = self::API_BASE_URL . $endpoint;

		$args = array(
			'method'    => $method,
			'headers'   => array(
				'Authorization' => 'Bearer ' . $this->api_token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'timeout'   => 30,
			'sslverify' => true,
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		// Log da requisição
		$this->log( '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'INFO' );
		$this->log( 'NOVA REQUISIÇÃO À API PERSONIZI', 'INFO' );
		$this->log( 'URL: ' . $url, 'INFO' );
		$this->log( 'Method: ' . $method, 'INFO' );
		$this->log( 'Token Length: ' . strlen( $this->api_token ), 'DEBUG' );
		$this->log( 'Token Preview: ' . substr( $this->api_token, 0, 20 ) . '...' . substr( $this->api_token, -10 ), 'DEBUG' );
		$this->log( 'Full Token (for debug): ' . $this->api_token, 'DEBUG' );
		$this->log( 'Headers Sent: ' . wp_json_encode( $args['headers'], JSON_PRETTY_PRINT ), 'DEBUG' );
		if ( ! empty( $data ) ) {
			$this->log( 'Payload: ' . wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), 'INFO' );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( '❌ WP_ERROR na requisição', 'ERROR' );
			$this->log( 'Error Code: ' . $response->get_error_code(), 'ERROR' );
			$this->log( 'Error Message: ' . $response->get_error_message(), 'ERROR' );
			$this->log( 'Error Data: ' . wp_json_encode( $response->get_error_data() ), 'ERROR' );
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		// Log da resposta
		$this->log( '📥 RESPOSTA DA API RECEBIDA', 'INFO' );
		$this->log( 'Status HTTP: ' . $status_code, 'INFO' );
		$this->log( 'Response Body: ' . $body, 'DEBUG' );
		$this->log( 'Parsed JSON: ' . wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), 'DEBUG' );

		if ( $status_code >= 400 ) {
			$this->log( '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'ERROR' );
			$this->log( '❌ ERRO NA API - Status ' . $status_code, 'ERROR' );
			$this->log( 'Response: ' . wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), 'ERROR' );
			$this->log( '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'ERROR' );
			
			$error_message = isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : __( 'Erro na API do Personizi', 'person-cash-wallet' );
			
			// Incluir status HTTP e detalhes para facilitar debug
			$detailed_error = sprintf( '[HTTP %d] %s', $status_code, $error_message );
			
			// Adicionar detalhes extras se disponíveis
			if ( isset( $decoded['error']['details'] ) ) {
				$detailed_error .= ' | ' . ( is_string( $decoded['error']['details'] ) ? $decoded['error']['details'] : wp_json_encode( $decoded['error']['details'] ) );
			}
			
			return new WP_Error(
				'personizi_api_error',
				$detailed_error,
				array( 
					'status'   => $status_code, 
					'response' => $decoded,
					'url'      => $url,
					'method'   => $method,
				)
			);
		}

		// Sucesso
		$this->log( '✅ REQUISIÇÃO BEM-SUCEDIDA - Status ' . $status_code, 'INFO' );
		$this->log( 'Success Response: ' . wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), 'INFO' );
		$this->log( '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'INFO' );

		return $decoded;
	}

	/**
	 * Normalizar telefone para formato internacional
	 *
	 * @param string $phone Telefone em qualquer formato.
	 * @return string Telefone normalizado (apenas números com código do país).
	 */
	private function normalize_phone( $phone ) {
		// Remover tudo que não for número
		$phone = preg_replace( '/[^0-9]/', '', $phone );
		
		if ( empty( $phone ) ) {
			return '';
		}
		
		// Se começa com 0, remover
		$phone = ltrim( $phone, '0' );
		
		// Se tem menos de 10 dígitos, inválido
		if ( strlen( $phone ) < 10 ) {
			return '';
		}
		
		// Se não começa com 55 (Brasil), adicionar
		if ( substr( $phone, 0, 2 ) !== '55' ) {
			$phone = '55' . $phone;
		}
		
		return $phone;
	}

	/**
	 * Enviar mensagem WhatsApp
	 *
	 * @param string $to Número do destinatário (ex: 5511999998888)
	 * @param string $message Texto da mensagem
	 * @param string $contact_name Nome do contato (opcional)
	 * @param string $from Número de origem (opcional, usa padrão se não informado)
	 * @return array|WP_Error
	 */
	public function send_whatsapp_message( $to, $message, $contact_name = '', $from = '' ) {
		// Normalizar telefone de destino
		$to = $this->normalize_phone( $to );
		
		if ( empty( $to ) ) {
			$this->log( 'Telefone de destino inválido após normalização', 'ERROR' );
			return new WP_Error( 'invalid_phone', __( 'Telefone de destino inválido', 'person-cash-wallet' ) );
		}

		// Se não informou from, usa o padrão
		if ( empty( $from ) ) {
			$from = $this->default_from;
		}
		
		// Normalizar from também
		$from = $this->normalize_phone( $from );
		
		if ( empty( $from ) ) {
			$this->log( 'Número de origem (from) inválido após normalização', 'ERROR' );
			return new WP_Error( 'invalid_from', __( 'Número de origem inválido', 'person-cash-wallet' ) );
		}

		$this->log( "Enviando para: {$to} | De: {$from}", 'INFO' );

		$data = array(
			'to'      => $to,
			'from'    => $from,
			'message' => $message,
		);

		if ( ! empty( $contact_name ) ) {
			$data['contact_name'] = $contact_name;
		}

		$result = $this->request( '/messages/send', 'POST', $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// ✅ Verificar estrutura de resposta: data->data->message_id
		if ( isset( $result['success'] ) && $result['success'] === true ) {
			// Retornar dados formatados
			return array(
				'success'         => true,
				'message_id'      => isset( $result['data']['message_id'] ) ? $result['data']['message_id'] : null,
				'conversation_id' => isset( $result['data']['conversation_id'] ) ? $result['data']['conversation_id'] : null,
				'contact_id'      => isset( $result['data']['contact_id'] ) ? $result['data']['contact_id'] : null,
				'status'          => isset( $result['data']['status'] ) ? $result['data']['status'] : 'sent',
			);
		}

		// Se não tiver success=true, retornar erro com detalhes
		$send_error_msg = isset( $result['error']['message'] ) ? $result['error']['message'] : '';
		if ( empty( $send_error_msg ) && isset( $result['message'] ) ) {
			$send_error_msg = $result['message'];
		}
		if ( empty( $send_error_msg ) ) {
			$send_error_msg = __( 'Erro ao enviar mensagem', 'person-cash-wallet' );
		}
		
		// Incluir detalhes adicionais na mensagem de erro
		if ( isset( $result['error']['details'] ) ) {
			$details = is_string( $result['error']['details'] ) ? $result['error']['details'] : wp_json_encode( $result['error']['details'] );
			$send_error_msg .= ' | ' . $details;
		}
		
		$this->log( '❌ Resposta sem success=true: ' . wp_json_encode( $result, JSON_UNESCAPED_UNICODE ), 'ERROR' );
		
		return new WP_Error(
			'personizi_send_error',
			$send_error_msg,
			$result
		);
	}

	/**
	 * Listar contas WhatsApp disponíveis
	 *
	 * @param bool $force_refresh Forçar atualização do cache
	 * @return array|WP_Error
	 */
	public function get_whatsapp_accounts( $force_refresh = false ) {
		// Usar cache se disponível
		if ( ! $force_refresh && null !== $this->accounts_cache ) {
			return $this->accounts_cache;
		}

		// ✅ URL CORRETA: /whatsapp-accounts?status=active (com hífen, não barra)
		$result = $this->request( '/whatsapp-accounts?status=active', 'GET' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// ✅ Estrutura correta da API: data->data->accounts (dois níveis de 'data')
		$accounts = array();
		if ( isset( $result['data']['accounts'] ) ) {
			// Resposta direta: { "data": { "accounts": [...] } }
			$accounts = $result['data']['accounts'];
		} elseif ( isset( $result['data']['data']['accounts'] ) ) {
			// Resposta aninhada: { "data": { "data": { "accounts": [...] } } }
			$accounts = $result['data']['data']['accounts'];
		}

		// Cachear resultado
		$this->accounts_cache = $accounts;

		return $accounts;
	}


	/**
	 * Listar templates aprovados de uma conta WhatsApp
	 *
	 * @param string $from_number Número da conta WhatsApp
	 * @param bool   $force_refresh Forçar atualização do cache
	 * @return array|WP_Error
	 */
	public function get_templates( $from_number, $force_refresh = false ) {
		$from_number = $this->normalize_phone( $from_number );
		if ( empty( $from_number ) ) {
			return new WP_Error( 'invalid_from', __( 'Número de origem inválido', 'person-cash-wallet' ) );
		}

		$cache_key = 'pcw_templates_' . $from_number;
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$this->log( "Buscando templates para: {$from_number}", 'INFO' );

		$result = $this->request( '/templates?from=' . $from_number, 'GET' );

		if ( is_wp_error( $result ) ) {
			$this->log( 'Erro ao buscar templates: ' . $result->get_error_message(), 'ERROR' );
			return $result;
		}

		$this->log( 'Resposta completa templates: ' . wp_json_encode( array_keys( $result ?? [] ) ), 'DEBUG' );
		if ( isset( $result['data'] ) ) {
			$this->log( 'result[data] keys: ' . wp_json_encode( array_keys( $result['data'] ) ), 'DEBUG' );
			if ( isset( $result['data']['data'] ) && is_array( $result['data']['data'] ) ) {
				$this->log( 'result[data][data] keys: ' . wp_json_encode( array_keys( $result['data']['data'] ) ), 'DEBUG' );
			}
		}

		$templates = array();
		if ( isset( $result['data']['templates'] ) && is_array( $result['data']['templates'] ) ) {
			$templates = $result['data']['templates'];
			$this->log( 'Templates encontrados em data.templates', 'DEBUG' );
		} elseif ( isset( $result['data']['data']['templates'] ) && is_array( $result['data']['data']['templates'] ) ) {
			$templates = $result['data']['data']['templates'];
			$this->log( 'Templates encontrados em data.data.templates', 'DEBUG' );
		} elseif ( isset( $result['templates'] ) && is_array( $result['templates'] ) ) {
			$templates = $result['templates'];
			$this->log( 'Templates encontrados em templates', 'DEBUG' );
		}

		$this->log( 'Templates encontrados: ' . count( $templates ), 'INFO' );

		$debug = $result['data']['debug'] ?? $result['data']['data']['debug'] ?? '';
		if ( count( $templates ) === 0 ) {
			$provider = $result['data']['provider'] ?? $result['data']['data']['provider'] ?? 'desconhecido';
			$accId = $result['data']['account_id'] ?? $result['data']['data']['account_id'] ?? 'N/A';
			$this->log( "AVISO: 0 templates. Provider: {$provider}, Account ID: {$accId}, Debug: {$debug}", 'WARNING' );

			if ( ! empty( $debug ) ) {
				return new WP_Error( 'no_templates', $debug );
			}
		}

		if ( ! empty( $templates ) ) {
			set_transient( $cache_key, $templates, 5 * MINUTE_IN_SECONDS );
		}

		return $templates;
	}

	/**
	 * Enviar template WhatsApp via API
	 *
	 * @param string $to             Número de destino
	 * @param string $from           Número de origem
	 * @param string $template_name  Nome do template
	 * @param array  $params         Valores das variáveis
	 * @param string $language       Código do idioma
	 * @param string $contact_name   Nome do contato (opcional)
	 * @param string $body_text      Texto do body para preview (opcional)
	 * @return array|WP_Error
	 */
	public function send_template_message( $to, $from, $template_name, $params = array(), $language = 'pt_BR', $contact_name = '', $body_text = '' ) {
		$to   = $this->normalize_phone( $to );
		$from = $this->normalize_phone( $from );

		if ( empty( $to ) ) {
			return new WP_Error( 'invalid_phone', __( 'Telefone de destino inválido', 'person-cash-wallet' ) );
		}
		if ( empty( $from ) ) {
			$from = $this->default_from;
			$from = $this->normalize_phone( $from );
		}
		if ( empty( $from ) ) {
			return new WP_Error( 'invalid_from', __( 'Número de origem inválido', 'person-cash-wallet' ) );
		}
		if ( empty( $template_name ) ) {
			return new WP_Error( 'no_template', __( 'Nome do template é obrigatório', 'person-cash-wallet' ) );
		}

		$this->log( "Enviando template '{$template_name}' para: {$to} | De: {$from}", 'INFO' );

		$data = array(
			'to'                => $to,
			'from'              => $from,
			'template_name'     => $template_name,
			'template_language' => $language,
			'template_params'   => is_array( $params ) ? $params : array(),
		);

		if ( ! empty( $contact_name ) ) {
			$data['contact_name'] = $contact_name;
		}
		if ( ! empty( $body_text ) ) {
			$data['template_body_text'] = $body_text;
		}

		$result = $this->request( '/messages/send-template', 'POST', $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( isset( $result['success'] ) && $result['success'] === true ) {
			return array(
				'success'         => true,
				'message_id'      => isset( $result['data']['message_id'] ) ? $result['data']['message_id'] : null,
				'conversation_id' => isset( $result['data']['conversation_id'] ) ? $result['data']['conversation_id'] : null,
				'contact_id'      => isset( $result['data']['contact_id'] ) ? $result['data']['contact_id'] : null,
				'template_name'   => $template_name,
				'status'          => isset( $result['data']['status'] ) ? $result['data']['status'] : 'sent',
			);
		}

		$error_msg = isset( $result['message'] ) ? $result['message'] : __( 'Erro ao enviar template', 'person-cash-wallet' );
		$this->log( 'Falha ao enviar template: ' . wp_json_encode( $result, JSON_UNESCAPED_UNICODE ), 'ERROR' );

		return new WP_Error( 'personizi_template_error', $error_msg, $result );
	}

	/**
	 * Verificar se uma conta é de API Oficial (Notificame/Meta Cloud)
	 *
	 * @param string $provider Provider da conta
	 * @return bool
	 */
	public static function is_official_api( $provider ) {
		return in_array( $provider, array( 'notificame', 'meta_cloud', 'meta_coex' ), true );
	}

	/**
	 * Testar conexão com a API
	 *
	 * @return bool|WP_Error
	 */
	public function test_connection() {
		// ✅ URL CORRETA: /whatsapp-accounts?per_page=1 (teste rápido)
		$result = $this->request( '/whatsapp-accounts?per_page=1', 'GET' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Obter API Token configurado
	 *
	 * @return string
	 */
	public function get_api_token() {
		return $this->api_token;
	}

	/**
	 * Obter número WhatsApp padrão
	 *
	 * @return string
	 */
	public function get_default_from() {
		return $this->default_from;
	}

	/**
	 * Atualizar API Token
	 *
	 * @param string $token
	 * @return bool
	 */
	public function update_api_token( $token ) {
		$this->api_token      = $token;
		$this->accounts_cache = null; // Limpar cache
		return update_option( 'pcw_personizi_token', $token );
	}

	/**
	 * Atualizar número WhatsApp padrão
	 *
	 * @param string $phone
	 * @return bool
	 */
	public function update_default_from( $phone ) {
		$this->default_from = preg_replace( '/[^0-9]/', '', $phone );
		return update_option( 'pcw_personizi_default_from', $this->default_from );
	}

	/**
	 * Processar variáveis na mensagem
	 *
	 * @param string $message Mensagem com variáveis
	 * @param int    $user_id ID do usuário
	 * @param int    $order_id ID do pedido (opcional)
	 * @return string
	 */
	public function process_message_variables( $message, $user_id = 0, $order_id = 0 ) {
		// Variáveis do usuário
		if ( $user_id > 0 ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$message = str_replace( '{{customer_first_name}}', $user->first_name, $message );
				$message = str_replace( '{{customer_name}}', $user->display_name, $message );
				$message = str_replace( '{{customer_email}}', $user->user_email, $message );
			}
		}

		// Variáveis do pedido
		if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$message = str_replace( '{{order_number}}', $order->get_order_number(), $message );
				$message = str_replace( '{{order_total}}', wc_price( $order->get_total() ), $message );
				$message = str_replace( '{{order_date}}', $order->get_date_created()->format( 'd/m/Y' ), $message );
			}
		}

		// Variáveis gerais
		$message = str_replace( '{{site_name}}', get_bloginfo( 'name' ), $message );
		$message = str_replace( '{{site_url}}', home_url(), $message );

		return $message;
	}

	/**
	 * Enviar mensagem via fila (com rate limiting)
	 *
	 * @param string $to_number Número destino
	 * @param string $message Mensagem
	 * @param string $from_number Número remetente (opcional)
	 * @param string $contact_name Nome do contato (opcional)
	 * @param array  $options Opções adicionais (webhook_id, automation_id, priority)
	 * @return int|false ID da fila ou false
	 */
	public function queue_message( $to_number, $message, $from_number = null, $contact_name = null, $options = array() ) {
		$queue_manager = PCW_Message_Queue_Manager::instance();

		$args = array(
			'type'          => 'whatsapp',
			'to_number'     => $to_number,
			'from_number'   => $from_number,
			'message'       => $message,
			'contact_name'  => $contact_name,
			'webhook_id'    => $options['webhook_id'] ?? null,
			'automation_id' => $options['automation_id'] ?? null,
			'priority'      => $options['priority'] ?? 5,
		);

		return $queue_manager->add_to_queue( $args );
	}
}
