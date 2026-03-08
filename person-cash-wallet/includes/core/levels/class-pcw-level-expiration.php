<?php
/**
 * Classe de expiração de níveis
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de expiração de níveis
 */
class PCW_Level_Expiration {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'pcw_daily_expiration_check', array( $this, 'check_level_expirations' ) );
	}

	/**
	 * Verificar expirações de níveis
	 */
	public function check_level_expirations() {
		$this->notify_expiring_levels();
		$this->expire_levels();
	}

	/**
	 * Notificar níveis próximos de expirar
	 */
	private function notify_expiring_levels() {
		global $wpdb;

		$user_levels_table = $wpdb->prefix . 'pcw_user_levels';
		$levels_table = $wpdb->prefix . 'pcw_levels';

		// Buscar níveis que expiram em 30 dias
		$date_threshold = date( 'Y-m-d', strtotime( '+30 days' ) );

		$expiring_levels = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ul.*, l.name as level_name, u.user_email
				FROM {$user_levels_table} ul
				INNER JOIN {$levels_table} l ON ul.level_id = l.id
				INNER JOIN {$wpdb->users} u ON ul.user_id = u.ID
				WHERE ul.status = 'active'
				AND ul.expires_date IS NOT NULL
				AND DATE(ul.expires_date) = %s
				AND ul.id NOT IN (
					SELECT meta_value FROM {$wpdb->usermeta}
					WHERE meta_key = '_pcw_level_expiring_notified'
					AND meta_value = ul.id
				)",
				$date_threshold
			)
		);

		foreach ( $expiring_levels as $user_level ) {
			$this->send_expiring_notification( $user_level );
			add_user_meta( $user_level->user_id, '_pcw_level_expiring_notified', $user_level->id );
			do_action( 'pcw_level_expiring', $user_level );
		}
	}

	/**
	 * Expirar níveis
	 */
	private function expire_levels() {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_user_levels';

		$expired_levels = $wpdb->get_results(
			"SELECT * FROM {$table}
			WHERE status = 'active'
			AND expires_date IS NOT NULL
			AND expires_date <= NOW()"
		);

		foreach ( $expired_levels as $user_level ) {
			PCW_Levels::remove_user_level( $user_level->user_id, $user_level->level_id );
			$this->send_expired_notification( $user_level );
			do_action( 'pcw_level_expired', $user_level );
		}
	}

	/**
	 * Enviar notificação de nível expirando
	 *
	 * @param object $user_level Dados do nível do usuário.
	 */
	private function send_expiring_notification( $user_level ) {
		$user = get_userdata( $user_level->user_id );
		if ( ! $user ) {
			return;
		}

		$subject = sprintf(
			__( 'Seu nível %s expira em breve!', 'person-cash-wallet' ),
			$user_level->level_name
		);

		$message = sprintf(
			__( 'Olá %s,

Queremos avisar que seu nível %s vai expirar em breve.

Expira em: %s

Mantenha suas compras para renovar seu nível!

Atenciosamente,
Equipe %s', 'person-cash-wallet' ),
			$user->display_name,
			$user_level->level_name,
			date_i18n( get_option( 'date_format' ), strtotime( $user_level->expires_date ) ),
			get_bloginfo( 'name' )
		);

		PCW_Email_Handler::send( $user->user_email, $subject, $message );
	}

	/**
	 * Enviar notificação de nível expirado
	 *
	 * @param object $user_level Dados do nível do usuário.
	 */
	private function send_expired_notification( $user_level ) {
		$user = get_userdata( $user_level->user_id );
		if ( ! $user ) {
			return;
		}

		$level = PCW_Levels::get_level( $user_level->level_id );

		$subject = __( 'Seu nível expirou', 'person-cash-wallet' );

		$message = sprintf(
			__( 'Olá %s,

Infelizmente seu nível %s expirou.

Para recuperar seu nível, continue fazendo compras!

Atenciosamente,
Equipe %s', 'person-cash-wallet' ),
			$user->display_name,
			$level ? $level->name : '',
			get_bloginfo( 'name' )
		);

		PCW_Email_Handler::send( $user->user_email, $subject, $message );
	}
}
