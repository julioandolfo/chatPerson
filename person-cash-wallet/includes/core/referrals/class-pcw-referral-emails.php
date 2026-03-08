<?php
/**
 * Classe de emails automáticos de indicação
 *
 * @package PersonCashWallet
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de emails de indicação
 */
class PCW_Referral_Emails {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Referral_Emails
	 */
	private static $instance = null;

	/**
	 * Obter instância singleton
	 *
	 * @return PCW_Referral_Emails
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
		// Singleton
	}

	/**
	 * Inicializar hooks
	 */
	public function init() {
		// Agendar cron para enviar emails de solicitação
		add_action( 'pcw_daily_expiration_check', array( $this, 'send_scheduled_referral_requests' ) );

		// Registrar endpoint para tracking de abertura
		add_action( 'init', array( $this, 'register_tracking_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'handle_tracking_endpoint' ) );
	}

	/**
	 * Enviar emails agendados de solicitação de indicação
	 */
	public function send_scheduled_referral_requests() {
		$settings = PCW_Referral_Rewards::instance()->get_settings();

		// Verificar se está habilitado
		if ( 'yes' !== $settings['enabled'] ) {
			return;
		}

		$days_after = absint( $settings['email_days_after_order'] );
		if ( $days_after <= 0 ) {
			return;
		}

		// Buscar pedidos que atingiram X dias
		$date_target = gmdate( 'Y-m-d', strtotime( "-{$days_after} days" ) );

		$orders = wc_get_orders( array(
			'status'       => array( 'wc-completed' ),
			'date_created' => $date_target . '...' . $date_target . ' 23:59:59',
			'limit'        => 50,
			'meta_query'   => array(
				array(
					'key'     => '_pcw_referral_email_sent',
					'compare' => 'NOT EXISTS',
				),
			),
		) );

		foreach ( $orders as $order ) {
			$this->send_referral_request_email( $order );
		}
	}

	/**
	 * Enviar email de solicitação de indicação
	 *
	 * @param WC_Order $order Pedido.
	 * @return bool
	 */
	public function send_referral_request_email( $order ) {
		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			return false; // Apenas para usuários logados
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		// Verificar se já enviou email para este pedido
		$email_sent = $order->get_meta( '_pcw_referral_email_sent' );
		if ( $email_sent ) {
			return false;
		}

		// Verificar limite de emails por usuário
		if ( ! $this->can_send_to_user( $user_id ) ) {
			return false;
		}

		// Gerar token único para a página pública
		$token = $this->generate_email_token( $user_id, $order->get_id() );

		// Obter ou criar código de indicação
		$code_data = PCW_Referral_Codes::instance()->get_or_create_code( $user_id );

		if ( ! $code_data ) {
			return false;
		}

		// Montar email - usar configurações unificadas
		$reward_settings = PCW_Referral_Rewards::instance()->get_settings();

		$subject = ! empty( $reward_settings['email_subject'] )
			? $reward_settings['email_subject']
			: __( '🎁 Indique amigos e ganhe recompensas!', 'person-cash-wallet' );

		$body = ! empty( $reward_settings['email_body'] )
			? $reward_settings['email_body']
			: $this->get_default_request_email_body();

		// URL da página pública
		$referral_page_url = home_url( '/indicar/' . $token . '/' );
		$referral_link = PCW_Referral_Codes::instance()->get_referral_link( $user_id );

		// Reward amount (usando $reward_settings já declarado acima)
		$reward_text = 'percentage' === $reward_settings['reward_type']
			? $reward_settings['reward_amount'] . '%'
			: PCW_Formatters::format_money( $reward_settings['reward_amount'] );

		// Substituir placeholders
		$placeholders = array(
			'{customer_name}'     => $user->first_name ?: $user->display_name,
			'{referral_code}'     => $code_data->code,
			'{referral_link}'     => $referral_link,
			'{referral_page_url}' => $referral_page_url,
			'{reward_amount}'     => $reward_text,
			'{order_id}'          => $order->get_id(),
			'{order_total}'       => $order->get_formatted_order_total(),
			'{site_name}'         => get_bloginfo( 'name' ),
			'{site_url}'          => home_url(),
		);

		$subject = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $subject );
		$body = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $body );

		// Adicionar pixel de tracking
		$tracking_url = $this->get_tracking_url( $token, 'open' );
		$body .= '<img src="' . esc_url( $tracking_url ) . '" width="1" height="1" style="display:none;" alt="" />';

		// Enviar email
		$sent = PCW_Email_Handler::send( $user->user_email, $subject, $body, array(), array(), true, array(
			'email_type' => 'referral_request',
			'user_id'    => $user_id,
			'order_id'   => $order->get_id(),
		) );

		if ( $sent ) {
			// Registrar envio
			$this->log_email_sent( $user_id, $order->get_id(), $token );

			// Marcar pedido
			$order->update_meta_data( '_pcw_referral_email_sent', true );
			$order->update_meta_data( '_pcw_referral_email_sent_at', current_time( 'mysql' ) );
			$order->update_meta_data( '_pcw_referral_email_token', $token );
			$order->save();

			do_action( 'pcw_referral_email_sent', $user_id, $order->get_id(), $token );
		}

		return $sent;
	}

	/**
	 * Verificar se pode enviar email para o usuário
	 *
	 * @param int $user_id ID do usuário.
	 * @return bool
	 */
	private function can_send_to_user( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_emails';

		// Limite: máximo de 3 emails nos últimos 30 dias
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} 
				WHERE user_id = %d AND sent_at > %s",
				$user_id,
				gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
			)
		);

		return $count < 3;
	}

	/**
	 * Gerar token para email
	 *
	 * @param int $user_id ID do usuário.
	 * @param int $order_id ID do pedido.
	 * @return string
	 */
	private function generate_email_token( $user_id, $order_id ) {
		$data = $user_id . '|' . $order_id . '|' . time();
		return wp_hash( $data ) . '-' . base64_encode( $user_id . ':' . $order_id );
	}

	/**
	 * Decodificar token de email
	 *
	 * @param string $token Token.
	 * @return array|false Array com user_id e order_id ou false.
	 */
	public function decode_email_token( $token ) {
		$parts = explode( '-', $token, 2 );

		if ( count( $parts ) !== 2 ) {
			return false;
		}

		// Decodificar parte base64
		$decoded = base64_decode( $parts[1] );

		if ( ! $decoded ) {
			return false;
		}

		$data_parts = explode( ':', $decoded );

		if ( count( $data_parts ) !== 2 ) {
			return false;
		}

		return array(
			'user_id'  => absint( $data_parts[0] ),
			'order_id' => absint( $data_parts[1] ),
		);
	}

	/**
	 * Registrar envio de email
	 *
	 * @param int    $user_id ID do usuário.
	 * @param int    $order_id ID do pedido.
	 * @param string $token Token do email.
	 */
	private function log_email_sent( $user_id, $order_id, $token ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_emails';

		$wpdb->insert(
			$table,
			array(
				'user_id'    => absint( $user_id ),
				'order_id'   => absint( $order_id ),
				'email_type' => 'request_referral',
				'token'      => sanitize_text_field( $token ),
				'status'     => 'sent',
				'sent_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Registrar endpoint de tracking
	 */
	public function register_tracking_endpoint() {
		add_rewrite_rule(
			'^referral-track/([^/]+)/([^/]+)/?$',
			'index.php?pcw_referral_track=$matches[1]&pcw_track_type=$matches[2]',
			'top'
		);

		add_rewrite_tag( '%pcw_referral_track%', '([^&]+)' );
		add_rewrite_tag( '%pcw_track_type%', '([^&]+)' );
	}

	/**
	 * Processar endpoint de tracking
	 */
	public function handle_tracking_endpoint() {
		$token = get_query_var( 'pcw_referral_track' );
		$type = get_query_var( 'pcw_track_type' );

		if ( empty( $token ) || empty( $type ) ) {
			return;
		}

		$this->track_email_action( $token, $type );

		// Retornar pixel transparente para opens
		if ( 'open' === $type ) {
			header( 'Content-Type: image/gif' );
			// 1x1 transparent gif
			echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
			exit;
		}

		// Para clicks, redirecionar para a página de indicação
		$token_data = $this->decode_email_token( $token );
		if ( $token_data ) {
			wp_safe_redirect( home_url( '/indicar/' . $token . '/' ) );
			exit;
		}
	}

	/**
	 * Rastrear ação do email
	 *
	 * @param string $token Token do email.
	 * @param string $type Tipo: 'open', 'click'.
	 */
	private function track_email_action( $token, $type ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_emails';

		$email = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE token = %s",
				$token
			)
		);

		if ( ! $email ) {
			return;
		}

		$update = array();

		if ( 'open' === $type && empty( $email->opened_at ) ) {
			$update['opened_at'] = current_time( 'mysql' );
			$update['status'] = 'opened';
		}

		if ( 'click' === $type && empty( $email->clicked_at ) ) {
			$update['clicked_at'] = current_time( 'mysql' );
			$update['status'] = 'clicked';
		}

		if ( ! empty( $update ) ) {
			$wpdb->update(
				$table,
				$update,
				array( 'id' => $email->id )
			);
		}
	}

	/**
	 * Obter URL de tracking
	 *
	 * @param string $token Token.
	 * @param string $type Tipo.
	 * @return string
	 */
	private function get_tracking_url( $token, $type ) {
		return home_url( '/referral-track/' . $token . '/' . $type . '/' );
	}

	/**
	 * Obter corpo padrão do email de solicitação
	 *
	 * @return string
	 */
	private function get_default_request_email_body() {
		return '
<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto;">
	<p style="font-size: 18px;">Olá, {customer_name}! 👋</p>

	<p>Esperamos que você esteja adorando sua compra! Temos uma proposta especial para você:</p>

	<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; text-align: center; margin: 30px 0;">
		<h2 style="margin: 0 0 10px 0; font-size: 24px;">🎁 Indique amigos e ganhe!</h2>
		<p style="margin: 0; font-size: 16px; opacity: 0.9;">Você ganha <strong>{reward_amount}</strong> para cada amigo que comprar usando sua indicação!</p>
	</div>

	<p><strong>Como funciona:</strong></p>
	<ol style="line-height: 1.8;">
		<li>Compartilhe seu código: <strong style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px;">{referral_code}</strong></li>
		<li>Seu amigo faz uma compra usando seu código</li>
		<li>Você recebe {reward_amount} de crédito na sua wallet!</li>
	</ol>

	<div style="text-align: center; margin: 30px 0;">
		<a href="{referral_page_url}" style="display: inline-block; background: #667eea; color: white; padding: 16px 32px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px;">Indicar Amigos Agora</a>
	</div>

	<div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
		<p style="margin: 0 0 10px 0;"><strong>Seu link de indicação:</strong></p>
		<p style="margin: 0; word-break: break-all; color: #667eea;">{referral_link}</p>
	</div>

	<p>Quanto mais amigos você indicar, mais você ganha! 🚀</p>

	<p>Atenciosamente,<br><strong>Equipe {site_name}</strong></p>
</div>
';
	}

	/**
	 * Enviar email de lembrete
	 *
	 * @param int $user_id ID do usuário.
	 * @return bool
	 */
	public function send_reminder_email( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		$code_data = PCW_Referral_Codes::instance()->get_or_create_code( $user_id );

		if ( ! $code_data ) {
			return false;
		}

		$settings = get_option( 'pcw_referral_email_settings', array() );

		$subject = ! empty( $settings['reminder_subject'] )
			? $settings['reminder_subject']
			: __( '⏰ Não esqueça de indicar seus amigos!', 'person-cash-wallet' );

		$body = ! empty( $settings['reminder_body'] )
			? $settings['reminder_body']
			: $this->get_default_reminder_email_body();

		$reward_settings = PCW_Referral_Rewards::instance()->get_settings();
		$reward_text = 'percentage' === $reward_settings['reward_type']
			? $reward_settings['reward_amount'] . '%'
			: PCW_Formatters::format_money( $reward_settings['reward_amount'] );

		$referral_link = PCW_Referral_Codes::instance()->get_referral_link( $user_id );

		$placeholders = array(
			'{customer_name}'  => $user->first_name ?: $user->display_name,
			'{referral_code}'  => $code_data->code,
			'{referral_link}'  => $referral_link,
			'{reward_amount}'  => $reward_text,
			'{site_name}'      => get_bloginfo( 'name' ),
			'{my_account_url}' => wc_get_account_endpoint_url( 'indicacoes' ),
		);

		$subject = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $subject );
		$body = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $body );

		return PCW_Email_Handler::send( $user->user_email, $subject, $body, array(), array(), true, array(
			'email_type' => 'referral_reminder',
			'user_id'    => $user_id,
		) );
	}

	/**
	 * Obter corpo padrão do email de lembrete
	 *
	 * @return string
	 */
	private function get_default_reminder_email_body() {
		return '
<p>Olá, {customer_name}!</p>

<p>Você sabia que pode ganhar <strong>{reward_amount}</strong> indicando amigos?</p>

<p>Ainda não fez nenhuma indicação. Que tal começar agora?</p>

<p>Seu código: <strong>{referral_code}</strong></p>

<p><a href="{my_account_url}" style="display: inline-block; background: #667eea; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 6px;">Indicar Amigos</a></p>

<p>Atenciosamente,<br>Equipe {site_name}</p>
';
	}

	/**
	 * Obter estatísticas de emails (admin)
	 *
	 * @return array
	 */
	public function get_email_stats() {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_emails';

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$opened = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE opened_at IS NOT NULL" );
		$clicked = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE clicked_at IS NOT NULL" );

		return array(
			'total'       => $total,
			'opened'      => $opened,
			'clicked'     => $clicked,
			'open_rate'   => $total > 0 ? round( ( $opened / $total ) * 100, 1 ) : 0,
			'click_rate'  => $total > 0 ? round( ( $clicked / $total ) * 100, 1 ) : 0,
		);
	}

	/**
	 * Obter emails enviados (admin)
	 *
	 * @param array $args Argumentos.
	 * @return array
	 */
	public function get_sent_emails( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_emails';

		$defaults = array(
			'limit'  => 50,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.*, u.display_name, u.user_email
				FROM {$table} e
				LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
				ORDER BY e.sent_at DESC
				LIMIT %d OFFSET %d",
				absint( $args['limit'] ),
				absint( $args['offset'] )
			)
		);
	}
}
