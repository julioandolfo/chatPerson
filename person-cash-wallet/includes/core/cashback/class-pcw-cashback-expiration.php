<?php
/**
 * Classe de expiração de cashback
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de expiração de cashback
 */
class PCW_Cashback_Expiration {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'pcw_daily_expiration_check', array( $this, 'check_expirations' ) );
	}

	/**
	 * Verificar expirações
	 */
	public function check_expirations() {
		$this->notify_expiring_cashback();
		$this->expire_cashback();
	}

	/**
	 * Notificar cashback próximo de expirar
	 */
	private function notify_expiring_cashback() {
		// Obter configurações de lembretes
		$settings = get_option( 'pcw_notification_settings', array() );
		
		if ( ! isset( $settings['cashback_expiring'] ) || 'yes' !== $settings['cashback_expiring']['enabled'] ) {
			return;
		}

		$reminders = isset( $settings['cashback_expiring']['reminders'] ) ? $settings['cashback_expiring']['reminders'] : array();
		
		if ( empty( $reminders ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_cashback';

		// Processar cada lembrete configurado
		foreach ( $reminders as $reminder_index => $reminder ) {
			// Verificar se o lembrete está ativo
			if ( ! isset( $reminder['enabled'] ) || 'yes' !== $reminder['enabled'] ) {
				continue;
			}

			$days_before = absint( $reminder['days_before'] );
			
			if ( $days_before <= 0 ) {
				continue;
			}

			$date_threshold = date( 'Y-m-d', strtotime( "+{$days_before} days" ) );

			// Buscar cashback que expira exatamente em X dias e ainda não foi notificado neste lembrete
			$expiring_cashback = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT c.*, u.user_email, u.display_name
					FROM {$table} c
					INNER JOIN {$wpdb->users} u ON c.user_id = u.ID
					WHERE c.status = 'available'
					AND DATE(c.expires_date) = %s",
					$date_threshold
				)
			);

			foreach ( $expiring_cashback as $cashback ) {
				// Verificar se já foi notificado para este lembrete específico
				$meta_key = '_pcw_expiring_notified_' . $days_before . '_days';
				$already_notified = get_user_meta( $cashback->user_id, $meta_key, true );
				
				// Verificar se já foi notificado para este cashback específico
				if ( $already_notified && in_array( $cashback->id, (array) $already_notified ) ) {
					continue; // Já foi notificado neste lembrete
				}

				// Enviar notificação com as configurações deste lembrete específico
				$this->send_expiring_notification( $cashback, $reminder, $days_before );

				// Marcar como notificado para este lembrete
				$notified_ids = $already_notified ? (array) $already_notified : array();
				$notified_ids[] = $cashback->id;
				update_user_meta( $cashback->user_id, $meta_key, $notified_ids );

				// Disparar webhook
				do_action( 'pcw_cashback_expiring', $cashback, $days_before );
			}
		}
	}

	/**
	 * Expirar cashback
	 */
	private function expire_cashback() {
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_cashback';

		// Buscar cashback expirado
		$expired_cashback = $wpdb->get_results(
			"SELECT * FROM {$table} 
			WHERE status = 'available' 
			AND expires_date IS NOT NULL 
			AND expires_date <= NOW()
			LIMIT 100"
		);

		foreach ( $expired_cashback as $cashback ) {
			// Atualizar status
			$wpdb->update(
				$table,
				array(
					'status'     => 'expired',
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $cashback->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			// Adicionar ao histórico
			global $wpdb;
			$history_table = $wpdb->prefix . 'pcw_cashback_history';
			
			$available_balance = 0; // Já foi expirado, então balance é 0
			
			$wpdb->insert(
				$history_table,
				array(
					'cashback_id'   => $cashback->id,
					'user_id'       => $cashback->user_id,
					'order_id'      => $cashback->order_id,
					'type'          => 'expired',
					'amount'        => floatval( $cashback->amount ),
					'balance_before' => floatval( $cashback->amount ),
					'balance_after' => 0,
					'description'   => __( 'Cashback expirado', 'person-cash-wallet' ),
					'created_at'    => current_time( 'mysql' ),
				)
			);

			// Enviar notificação
			$this->send_expired_notification( $cashback );

			// Disparar webhook
			do_action( 'pcw_cashback_expired', $cashback );
		}
	}

	/**
	 * Enviar notificação de cashback expirando
	 *
	 * @param object $cashback Dados do cashback.
	 * @param array  $reminder Configurações do lembrete.
	 * @param int    $days_before Dias antes de expirar.
	 */
	private function send_expiring_notification( $cashback, $reminder, $days_before ) {
		$user = get_userdata( $cashback->user_id );
		if ( ! $user ) {
			return;
		}

		PCW_Email_Handler::send_cashback_expiring( $cashback, $user, $reminder, $days_before );
	}

	/**
	 * Enviar notificação de cashback expirado
	 *
	 * @param object $cashback Dados do cashback.
	 */
	private function send_expired_notification( $cashback ) {
		$user = get_userdata( $cashback->user_id );
		if ( ! $user ) {
			return;
		}

		PCW_Email_Handler::send_cashback_expired( $cashback, $user );
	}
}
