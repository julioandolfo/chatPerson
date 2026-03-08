<?php
/**
 * Helper para envio de emails com tracking de automações
 *
 * @package GrowlyDigital
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe helper para emails de automações
 */
class PCW_Automation_Email_Helper {

	/**
	 * Enviar email de automação com tracking completo
	 *
	 * @param int    $automation_id ID da automação.
	 * @param int    $execution_id ID da execução.
	 * @param int    $user_id ID do usuário.
	 * @param string $email Email do destinatário.
	 * @param string $subject Assunto do email.
	 * @param string $html_content Conteúdo HTML do email.
	 * @param int    $email_log_id ID do log de email (opcional).
	 * @return bool|WP_Error True se enviado, WP_Error em caso de erro.
	 */
	public static function send_tracked_email( $automation_id, $execution_id, $user_id, $email, $subject, $html_content, $email_log_id = null ) {
		$tracking = PCW_Automation_Tracking::instance();

		// Criar tracking
		$tracking_code = $tracking->create_email_tracking(
			$automation_id,
			$execution_id,
			$user_id,
			$email,
			$subject,
			$email_log_id
		);

		if ( ! $tracking_code ) {
			return new WP_Error( 'tracking_error', __( 'Erro ao criar tracking', 'person-cash-wallet' ) );
		}

		// Processar HTML com tracking
		$html_content = $tracking->process_email_html( $html_content, $tracking_code, $automation_id );

		// Enviar email usando o sistema de distribuição e filas
		$sent = PCW_Email_Handler::send(
			$email,
			$subject,
			$html_content,
			array(), // headers
			array(), // attachments
			false,   // wrap_html = false (já está processado)
			array(   // log_data
				'email_type'    => 'automation',
				'user_id'       => $user_id,
				'related_id'    => $automation_id,
				'metadata'      => array(
					'automation_id' => $automation_id,
					'execution_id'  => $execution_id,
					'tracking_code' => $tracking_code,
				),
			),
			null // use_queue = null (auto-detectar)
		);

		if ( $sent ) {
			// Incrementar contador na automação
			PCW_Automations::instance()->increment_stat( $automation_id, 'sent' );

			return true;
		}

		return new WP_Error( 'send_error', __( 'Erro ao enviar email', 'person-cash-wallet' ) );
	}

	/**
	 * Processar variáveis do email
	 *
	 * @param string $content Conteúdo do email.
	 * @param int    $user_id ID do usuário.
	 * @param array  $extra_vars Variáveis extras.
	 * @return string
	 */
	public static function process_email_variables( $content, $user_id, $extra_vars = array() ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return $content;
		}

		$customer = new WC_Customer( $user_id );

		// Variáveis padrão
		$variables = array(
			'{{customer_name}}'       => $user->display_name,
			'{{customer_first_name}}' => $user->first_name,
			'{{customer_email}}'      => $user->user_email,
			'{{site_name}}'           => get_bloginfo( 'name' ),
			'{{site_url}}'            => home_url(),
		);

		// Cashback
		$wallet  = new PCW_Wallet( $user_id );
		$balance = $wallet->get_balance();
		$variables['{{cashback_balance}}'] = wc_price( $balance );

		// Nível VIP
		$user_level = PCW_Levels::get_user_level( $user_id );
		if ( $user_level ) {
			$variables['{{user_level}}'] = $user_level->name;
		} else {
			$variables['{{user_level}}'] = __( 'Nenhum', 'person-cash-wallet' );
		}

		// Adicionar variáveis extras
		$variables = array_merge( $variables, $extra_vars );

		// Substituir
		return str_replace( array_keys( $variables ), array_values( $variables ), $content );
	}

	/**
	 * Exemplo de uso completo
	 *
	 * @example
	 * ```php
	 * // Recuperar dados da automação
	 * $automation = PCW_Automations::instance()->get( $automation_id );
	 * 
	 * // Processar variáveis
	 * $html_content = PCW_Automation_Email_Helper::process_email_variables(
	 *     $automation->email_template,
	 *     $user_id,
	 *     array(
	 *         '{{product_name}}' => 'Nome do Produto',
	 *         '{{product_price}}' => wc_price( 99.90 ),
	 *     )
	 * );
	 * 
	 * // Enviar com tracking
	 * $result = PCW_Automation_Email_Helper::send_tracked_email(
	 *     $automation_id,
	 *     $execution_id,
	 *     $user_id,
	 *     $email,
	 *     $automation->email_subject,
	 *     $html_content
	 * );
	 * 
	 * if ( is_wp_error( $result ) ) {
	 *     error_log( 'Erro ao enviar: ' . $result->get_error_message() );
	 * }
	 * ```
	 */
	public static function usage_example() {
		// Este método existe apenas para documentação
	}
}
