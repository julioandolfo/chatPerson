<?php
/**
 * Gerenciador de Contas SMTP
 *
 * @package GrowlyDigital
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de gerenciamento de contas SMTP
 */
class PCW_SMTP_Accounts {

	/**
	 * Instância singleton
	 *
	 * @var PCW_SMTP_Accounts
	 */
	private static $instance = null;

	/**
	 * Obter instância
	 *
	 * @return PCW_SMTP_Accounts
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
	private function __construct() {
		// Privado para singleton
	}

	/**
	 * Criar conta SMTP
	 *
	 * @param array $data Dados da conta.
	 * @return int|false
	 */
	public function create( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_smtp_accounts';

		$insert_data = array(
			'name'                  => sanitize_text_field( $data['name'] ),
			'from_email'            => sanitize_email( $data['from_email'] ),
			'from_name'             => sanitize_text_field( $data['from_name'] ),
			'provider'              => isset( $data['provider'] ) ? sanitize_text_field( $data['provider'] ) : 'custom',
			'host'                  => isset( $data['host'] ) ? sanitize_text_field( $data['host'] ) : null,
			'port'                  => isset( $data['port'] ) ? absint( $data['port'] ) : 587,
			'encryption'            => isset( $data['encryption'] ) ? sanitize_text_field( $data['encryption'] ) : 'tls',
			'username'              => isset( $data['username'] ) ? sanitize_text_field( $data['username'] ) : null,
			'password'              => isset( $data['password'] ) ? $this->encrypt_password( $data['password'] ) : null,
			'fluent_connection_id'  => isset( $data['fluent_connection_id'] ) ? absint( $data['fluent_connection_id'] ) : null,
			'daily_limit'           => isset( $data['daily_limit'] ) ? absint( $data['daily_limit'] ) : 0,
			'status'                => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'active',
			'created_at'            => current_time( 'mysql' ),
			'updated_at'            => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $insert_data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Atualizar conta SMTP
	 *
	 * @param int   $id ID da conta.
	 * @param array $data Dados para atualizar.
	 * @return bool
	 */
	public function update( $id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_smtp_accounts';

		$update_data = array(
			'updated_at' => current_time( 'mysql' ),
		);

		$fields = array( 'name', 'from_email', 'from_name', 'provider', 'host', 'port', 'encryption', 'username', 'daily_limit', 'status', 'fluent_connection_id' );

		foreach ( $fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$update_data[ $field ] = $data[ $field ];
			}
		}

		// Senha só atualiza se fornecida
		if ( isset( $data['password'] ) && ! empty( $data['password'] ) ) {
			$update_data['password'] = $this->encrypt_password( $data['password'] );
		}

		$result = $wpdb->update( $table, $update_data, array( 'id' => $id ) );

		return false !== $result;
	}

	/**
	 * Deletar conta SMTP
	 *
	 * @param int $id ID da conta.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_smtp_accounts';

		$result = $wpdb->delete( $table, array( 'id' => $id ) );

		return false !== $result;
	}

	/**
	 * Obter conta por ID
	 *
	 * @param int $id ID da conta.
	 * @return object|null
	 */
	public function get( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_smtp_accounts';

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Obter todas as contas
	 *
	 * @param array $args Argumentos.
	 * @return array
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_smtp_accounts';

		$defaults = array(
			'status' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where .= ' AND status = %s';
			$params[] = $args['status'];
		}

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY name ASC";

		if ( ! empty( $params ) ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Obter contas ativas para seleção
	 *
	 * @return array
	 */
	public function get_for_select() {
		$accounts = $this->get_all( array( 'status' => 'active' ) );
		$options = array();

		// Adicionar contas próprias
		foreach ( $accounts as $account ) {
			$options[ $account->id ] = sprintf( '%s (%s)', $account->name, $account->from_email );
		}

		// Adicionar contas do FluentSMTP se disponível
		$fluent_accounts = $this->get_fluent_smtp_connections();
		foreach ( $fluent_accounts as $fa ) {
			$options[ 'fluent_' . $fa['id'] ] = sprintf( '[FluentSMTP] %s (%s)', $fa['name'], $fa['from_email'] );
		}

		return $options;
	}

	/**
	 * Obter conexões do FluentSMTP
	 *
	 * @return array
	 */
	public function get_fluent_smtp_connections() {
		$connections = array();
		$log_dir = WP_CONTENT_DIR . '/pcw-logs';
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}
		$log_file = $log_dir . '/pcw-smtp-sync.log';

		// Verificar se FluentSMTP está ativo
		if ( ! function_exists( 'fluentMail' ) && ! class_exists( 'FluentMail\App\Models\Settings' ) ) {
			file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . '] FluentSMTP not active' . PHP_EOL, FILE_APPEND );
			return $connections;
		}

		// Tentar obter configurações do FluentSMTP
		$fluent_settings = get_option( 'fluentmail-settings', array() );

		// Log das configurações brutas
		file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . '] Raw FluentSMTP settings: ' . json_encode( $fluent_settings, JSON_UNESCAPED_UNICODE ) . PHP_EOL, FILE_APPEND );

		if ( ! empty( $fluent_settings['connections'] ) ) {
			$id = 0;
			foreach ( $fluent_settings['connections'] as $key => $connection ) {
				// Log do conteúdo bruto da conexão
				file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . '] Raw connection [' . $key . ']: ' . json_encode( $connection, JSON_UNESCAPED_UNICODE ) . PHP_EOL, FILE_APPEND );

				// FluentSMTP armazena dados dentro de provider_settings
				$settings = isset( $connection['provider_settings'] ) ? $connection['provider_settings'] : $connection;
				
				file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . '] Settings extracted: ' . json_encode( $settings, JSON_UNESCAPED_UNICODE ) . PHP_EOL, FILE_APPEND );

				// FluentSMTP armazena email em diferentes campos dependendo da versão/provider
				$from_email = '';
				$from_name  = '';

				// Campos possíveis para email
				$email_fields = array( 'sender_email', 'from_email', 'email', 'from', 'username', 'auth_user', 'auth_username' );
				foreach ( $email_fields as $field ) {
					file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . '] Checking field: ' . $field . ' = ' . ( isset( $settings[ $field ] ) ? $settings[ $field ] : 'NOT SET' ) . PHP_EOL, FILE_APPEND );
					
					if ( ! empty( $settings[ $field ] ) ) {
						$from_email = $settings[ $field ];
						file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . '] Found email in field: ' . $field . ' = ' . $from_email . PHP_EOL, FILE_APPEND );
						break;
					}
				}

				// Se ainda não encontrou, buscar nos mappings
				if ( empty( $from_email ) && ! empty( $fluent_settings['mappings'] ) ) {
					foreach ( $fluent_settings['mappings'] as $email => $conn_key ) {
						if ( $conn_key === $key && is_email( $email ) ) {
							$from_email = $email;
							file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . '] Found email in mappings: ' . $email . PHP_EOL, FILE_APPEND );
							break;
						}
					}
				}

				// Se ainda não encontrou, usar a chave (geralmente é o email)
				if ( empty( $from_email ) && is_email( $key ) ) {
					$from_email = $key;
					file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . '] Using key as email: ' . $key . PHP_EOL, FILE_APPEND );
				}

				// Campos possíveis para nome
				$name_fields = array( 'sender_name', 'from_name', 'name', 'title' );
				foreach ( $name_fields as $field ) {
					if ( ! empty( $settings[ $field ] ) ) {
						$from_name = $settings[ $field ];
						break;
					}
				}

				// Se não encontrou nome, usar o title da conexão
				if ( empty( $from_name ) && ! empty( $connection['title'] ) ) {
					$from_name = $connection['title'];
				}

				// Título da conexão
				$title = isset( $connection['title'] ) ? $connection['title'] : ( isset( $settings['provider'] ) ? ucfirst( $settings['provider'] ) : 'Servidor SMTP' );

				$connections[] = array(
					'id'         => $id++,
					'key'        => $key,
					'name'       => $title,
					'from_email' => $from_email,
					'from_name'  => $from_name,
					'provider'   => isset( $settings['provider'] ) ? $settings['provider'] : 'smtp',
				);
			}
		}

		return $connections;
	}

	/**
	 * Enviar email usando conta SMTP específica
	 *
	 * @param int    $account_id ID da conta (ou 'fluent_X' para FluentSMTP).
	 * @param string $to Destinatário.
	 * @param string $subject Assunto.
	 * @param string $message Mensagem.
	 * @param array  $headers Headers adicionais.
	 * @return bool|WP_Error
	 */
	public function send_email( $account_id, $to, $subject, $message, $headers = array() ) {
		// Se for conta FluentSMTP
		if ( is_string( $account_id ) && strpos( $account_id, 'fluent_' ) === 0 ) {
			return $this->send_via_fluent( $account_id, $to, $subject, $message, $headers );
		}

		$account = $this->get( $account_id );

		if ( ! $account ) {
			return new WP_Error( 'invalid_account', __( 'Conta SMTP não encontrada', 'person-cash-wallet' ) );
		}

		// Verificar limite diário
		if ( $account->daily_limit > 0 ) {
			$this->check_daily_limit( $account );
			if ( $account->sent_today >= $account->daily_limit ) {
				return new WP_Error( 'daily_limit', __( 'Limite diário de envios atingido', 'person-cash-wallet' ) );
			}
		}

		// Configurar PHPMailer
		add_action( 'phpmailer_init', function( $phpmailer ) use ( $account ) {
			$phpmailer->isSMTP();
			$phpmailer->Host = $account->host;
			$phpmailer->Port = $account->port;
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $account->username;
			$phpmailer->Password = $this->decrypt_password( $account->password );
			$phpmailer->SMTPSecure = $account->encryption;
			$phpmailer->From = $account->from_email;
			$phpmailer->FromName = $account->from_name;
		} );

		// Headers
		$email_headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $account->from_name . ' <' . $account->from_email . '>',
		);
		$email_headers = array_merge( $email_headers, $headers );

		$result = wp_mail( $to, $subject, $message, $email_headers );

		// Incrementar contador
		if ( $result && $account->daily_limit > 0 ) {
			$this->increment_sent_count( $account->id );
		}

		return $result;
	}

	/**
	 * Enviar via FluentSMTP
	 *
	 * @param string $account_id ID no formato 'fluent_X'.
	 * @param string $to Destinatário.
	 * @param string $subject Assunto.
	 * @param string $message Mensagem.
	 * @param array  $headers Headers.
	 * @return bool
	 */
	private function send_via_fluent( $account_id, $to, $subject, $message, $headers = array() ) {
		// FluentSMTP usa o wp_mail padrão com sua configuração
		$email_headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);
		$email_headers = array_merge( $email_headers, $headers );

		return wp_mail( $to, $subject, $message, $email_headers );
	}

	/**
	 * Verificar e resetar limite diário
	 *
	 * @param object $account Conta.
	 */
	private function check_daily_limit( $account ) {
		global $wpdb;

		$today = date( 'Y-m-d' );

		if ( $account->last_sent_reset !== $today ) {
			$wpdb->update(
				$wpdb->prefix . 'pcw_smtp_accounts',
				array(
					'sent_today'      => 0,
					'last_sent_reset' => $today,
				),
				array( 'id' => $account->id )
			);
			$account->sent_today = 0;
		}
	}

	/**
	 * Incrementar contador de envios
	 *
	 * @param int $account_id ID da conta.
	 */
	private function increment_sent_count( $account_id ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}pcw_smtp_accounts SET sent_today = sent_today + 1 WHERE id = %d",
			$account_id
		) );
	}

	/**
	 * Criptografar senha
	 *
	 * @param string $password Senha.
	 * @return string
	 */
	private function encrypt_password( $password ) {
		if ( empty( $password ) ) {
			return '';
		}
		return base64_encode( $password );
	}

	/**
	 * Descriptografar senha
	 *
	 * @param string $encrypted Senha criptografada.
	 * @return string
	 */
	private function decrypt_password( $encrypted ) {
		if ( empty( $encrypted ) ) {
			return '';
		}
		return base64_decode( $encrypted );
	}

	/**
	 * Testar conexão SMTP
	 *
	 * @param int $account_id ID da conta.
	 * @return array
	 */
	public function test_connection( $account_id ) {
		$account = $this->get( $account_id );

		if ( ! $account ) {
			return array(
				'success' => false,
				'message' => __( 'Conta não encontrada', 'person-cash-wallet' ),
			);
		}

		$to = get_option( 'admin_email' );
		$subject = __( 'Teste de Conexão SMTP - Growly Digital', 'person-cash-wallet' );
		$message = '<p>' . __( 'Este é um email de teste para verificar a configuração SMTP.', 'person-cash-wallet' ) . '</p>';

		$result = $this->send_email( $account_id, $to, $subject, $message );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		return array(
			'success' => $result,
			'message' => $result ? __( 'Email de teste enviado com sucesso!', 'person-cash-wallet' ) : __( 'Falha ao enviar email', 'person-cash-wallet' ),
		);
	}
}
