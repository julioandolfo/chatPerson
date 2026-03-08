<?php
/**
 * Classe base para integração com formulários
 *
 * @package PersonCashWallet
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe base de integração com formulários
 */
abstract class PCW_Forms_Integration {

	/**
	 * Nome do provedor do formulário
	 *
	 * @var string
	 */
	protected $provider = '';

	/**
	 * Configurações da integração
	 *
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Construtor
	 */
	public function __construct() {
		$this->settings = $this->get_settings();
	}

	/**
	 * Obter configurações da integração
	 *
	 * @return array
	 */
	protected function get_settings() {
		$option_name = 'pcw_forms_integration_' . $this->provider;
		return get_option( $option_name, array() );
	}

	/**
	 * Verificar se integração está habilitada
	 *
	 * @return bool
	 */
	protected function is_enabled() {
		return isset( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Processar submissão de formulário
	 *
	 * @param array $data Dados do formulário.
	 * @return bool|WP_Error
	 */
	protected function process_submission( $data ) {
		// Validar dados obrigatórios
		if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
			return new WP_Error( 'invalid_email', __( 'Email inválido', 'person-cash-wallet' ) );
		}

		// Sanitizar dados
		$clean_data = array(
			'email' => sanitize_email( $data['email'] ),
			'name'  => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
			'phone' => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
		);

		// Buscar ou criar usuário
		$user = get_user_by( 'email', $clean_data['email'] );
		$user_id = $user ? $user->ID : null;

		// Se deve criar usuário e ele não existe
		if ( ! $user && ! empty( $this->settings['create_user'] ) && 'yes' === $this->settings['create_user'] ) {
			$user_id = $this->create_user( $clean_data );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
		}

		// Salvar submissão na tabela
		$submission_id = $this->save_submission( $clean_data, $user_id, $data );

		// Adicionar à lista se configurada
		if ( ! empty( $this->settings['list_id'] ) ) {
			$this->add_to_list( $clean_data, $user_id );
		}

		// Disparar automação se configurada
		if ( ! empty( $this->settings['automation_id'] ) && $user_id ) {
			$this->trigger_automation( $user_id );
		}

		// Hook customizado para desenvolvedores
		do_action( 'pcw_new_subscriber', $clean_data['email'], $clean_data['name'], array(
			'phone'         => $clean_data['phone'],
			'user_id'       => $user_id,
			'provider'      => $this->provider,
			'submission_id' => $submission_id,
		) );

		return true;
	}

	/**
	 * Criar usuário WordPress
	 *
	 * @param array $data Dados do usuário.
	 * @return int|WP_Error ID do usuário ou erro.
	 */
	protected function create_user( $data ) {
		// Gerar username
		$username = sanitize_user( $data['email'] );
		if ( username_exists( $username ) ) {
			$username = sanitize_user( $data['email'] . '_' . wp_generate_password( 4, false ) );
		}

		// Gerar senha
		$password = wp_generate_password( 12, true );

		// Criar usuário
		$user_id = wp_create_user( $username, $password, $data['email'] );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Atualizar nome
		if ( ! empty( $data['name'] ) ) {
			wp_update_user( array(
				'ID'           => $user_id,
				'display_name' => $data['name'],
				'first_name'   => $data['name'],
			) );
		}

		// Definir função
		$user_role = ! empty( $this->settings['user_role'] ) ? $this->settings['user_role'] : 'subscriber';
		$user = new WP_User( $user_id );
		$user->set_role( $user_role );

		// Enviar email de boas-vindas (opcional)
		if ( ! empty( $this->settings['send_welcome_email'] ) && 'yes' === $this->settings['send_welcome_email'] ) {
			wp_new_user_notification( $user_id, null, 'user' );
		}

		// Disparar hook de novo usuário
		do_action( 'pcw_user_registered', $user_id );

		return $user_id;
	}

	/**
	 * Salvar submissão no banco de dados
	 *
	 * @param array $data Dados limpos.
	 * @param int   $user_id ID do usuário.
	 * @param array $raw_data Dados brutos do formulário.
	 * @return int|false ID da submissão ou false.
	 */
	protected function save_submission( $data, $user_id, $raw_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_form_submissions';

		$inserted = $wpdb->insert(
			$table,
			array(
				'form_provider' => $this->provider,
				'form_id'       => isset( $raw_data['form_id'] ) ? sanitize_text_field( $raw_data['form_id'] ) : null,
				'form_name'     => isset( $raw_data['form_name'] ) ? sanitize_text_field( $raw_data['form_name'] ) : null,
				'email'         => $data['email'],
				'name'          => $data['name'],
				'phone'         => $data['phone'],
				'user_id'       => $user_id,
				'list_id'       => ! empty( $this->settings['list_id'] ) ? absint( $this->settings['list_id'] ) : null,
				'automation_id' => ! empty( $this->settings['automation_id'] ) ? absint( $this->settings['automation_id'] ) : null,
				'status'        => 'processed',
				'form_data'     => wp_json_encode( $raw_data ),
				'ip_address'    => $this->get_client_ip(),
				'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : null,
				'page_url'      => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : null,
				'referrer'      => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : null,
				'created_at'    => current_time( 'mysql' ),
				'processed_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Adicionar à lista
	 *
	 * @param array $data Dados do contato.
	 * @param int   $user_id ID do usuário.
	 */
	protected function add_to_list( $data, $user_id ) {
		$list_id = absint( $this->settings['list_id'] );

		$members = array(
			array(
				'email' => $data['email'],
				'name'  => $data['name'],
				'phone' => $data['phone'],
				'metadata' => array(
					'source'   => $this->provider,
					'added_at' => current_time( 'mysql' ),
				),
			),
		);

		PCW_Custom_Lists::add_members( $list_id, $members );
	}

	/**
	 * Disparar automação
	 *
	 * @param int $user_id ID do usuário.
	 */
	protected function trigger_automation( $user_id ) {
		// Se é um novo usuário, o hook user_register já foi disparado
		// Caso contrário, disparar um hook customizado
		do_action( 'pcw_form_submitted', $user_id, $this->provider );
	}

	/**
	 * Obter IP do cliente
	 *
	 * @return string
	 */
	protected function get_client_ip() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( isset( $_SERVER[ $key ] ) && filter_var( wp_unslash( $_SERVER[ $key ] ), FILTER_VALIDATE_IP ) ) {
				return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			}
		}

		return '';
	}

	/**
	 * Inicializar integração (deve ser implementado pelas classes filhas)
	 *
	 * @return void
	 */
	abstract public function init();
}
