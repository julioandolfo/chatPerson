<?php
/**
 * Classe de processamento de recompensas de indicação
 *
 * @package PersonCashWallet
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de recompensas de indicação
 */
class PCW_Referral_Rewards {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Referral_Rewards
	 */
	private static $instance = null;

	/**
	 * Obter instância singleton
	 *
	 * @return PCW_Referral_Rewards
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
		// Registrar hooks dinâmicos baseado nas configurações
		$this->register_order_hooks();
	}

	/**
	 * Registrar hooks de pedidos
	 */
	private function register_order_hooks() {
		$settings = $this->get_settings();

		// Status que geram recompensa
		$reward_statuses = ! empty( $settings['reward_order_statuses'] ) 
			? $settings['reward_order_statuses'] 
			: array( 'completed' );

		foreach ( $reward_statuses as $status ) {
			$hook = 'woocommerce_order_status_' . $status;
			add_action( $hook, array( $this, 'process_referral_reward' ), 30, 2 );
		}
	}

	/**
	 * Obter configurações de recompensa
	 *
	 * @return array
	 */
	public function get_settings() {
		$defaults = array(
			'enabled'                    => 'yes',
			'reward_type'                => 'fixed', // fixed, percentage
			'reward_amount'              => 10.00,
			'max_reward_amount'          => 0, // 0 = sem limite
			'min_order_amount'           => 0,
			'reward_order_statuses'      => array( 'completed' ),
			'reward_limit_type'          => 'first', // first, unlimited, limited
			'reward_limit_count'         => 1, // Se limited, quantas compras
			'referred_reward_enabled'    => 'no',
			'referred_reward_type'       => 'fixed',
			'referred_reward_amount'     => 5.00,
			'referred_reward_first_only' => 'yes',
			'cookie_days'                => 30,
			'email_days_after_order'     => 20,
			'email_subject'              => __( '💰 Ganhe R$ indicando amigos!', 'person-cash-wallet' ),
			'email_body'                 => '',
		);

		$settings = get_option( 'pcw_referral_settings', array() );

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Salvar configurações
	 *
	 * @param array $settings Configurações.
	 * @return bool
	 */
	public function save_settings( $settings ) {
		return update_option( 'pcw_referral_settings', $settings );
	}

	/**
	 * Processar recompensa de indicação quando pedido atinge status
	 *
	 * @param int      $order_id ID do pedido.
	 * @param WC_Order $order Objeto do pedido (opcional).
	 */
	public function process_referral_reward( $order_id, $order = null ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$settings = $this->get_settings();

		// Verificar se sistema está habilitado
		if ( 'yes' !== $settings['enabled'] ) {
			return;
		}

		// Verificar se já foi processada recompensa para este pedido
		$reward_processed = $order->get_meta( '_pcw_referral_reward_processed' );
		if ( $reward_processed ) {
			return;
		}

		// Verificar se tem código de indicação
		$referral_code = $order->get_meta( '_pcw_referral_code' );
		if ( empty( $referral_code ) ) {
			return;
		}

		// Validar código
		$codes = PCW_Referral_Codes::instance();
		$code_data = $codes->get_code_by_code( $referral_code );

		if ( ! $code_data ) {
			return;
		}

		// Verificar valor mínimo do pedido
		$order_total = floatval( $order->get_total() );
		$min_amount = floatval( $settings['min_order_amount'] );

		if ( $min_amount > 0 && $order_total < $min_amount ) {
			return;
		}

		// Obter email do comprador
		$buyer_email = $order->get_billing_email();
		$buyer_user_id = $order->get_user_id();

		// Buscar ou criar indicação
		$referrals = PCW_Referrals::instance();
		$referral = $referrals->get_referral_by_email( $buyer_email );

		if ( ! $referral ) {
			// Criar indicação retroativamente (veio pelo link/cookie)
			$referral_id = $referrals->create_referral( array(
				'referrer_user_id' => $code_data->user_id,
				'referrer_code'    => $code_data->code,
				'referred_name'    => $order->get_formatted_billing_full_name(),
				'referred_email'   => $buyer_email,
				'referred_phone'   => $order->get_billing_phone(),
				'source'           => 'checkout',
			) );

			if ( is_wp_error( $referral_id ) ) {
				// Log do erro mas não bloqueia o pedido
				error_log( '[PCW Referrals] Erro ao criar indicação: ' . $referral_id->get_error_message() );
				return;
			}

			$referral = $referrals->get_referral( $referral_id );
		}

		if ( ! $referral ) {
			return;
		}

		// Verificar se pode gerar recompensa baseado no limite
		if ( ! $this->can_reward( $referral, $buyer_email ) ) {
			return;
		}

		// Calcular recompensa para o indicador
		$reward_amount = $this->calculate_reward( $order, $settings );

		if ( $reward_amount <= 0 ) {
			return;
		}

		// Creditar na wallet do indicador
		$wallet = new PCW_Wallet( $code_data->user_id );
		$transaction_id = $wallet->credit(
			$reward_amount,
			'referral',
			sprintf(
				/* translators: %1$s: Nome do indicado, %2$d: ID do pedido */
				__( 'Recompensa por indicação de %1$s (Pedido #%2$d)', 'person-cash-wallet' ),
				$referral->referred_name,
				$order_id
			),
			$order_id
		);

		if ( ! $transaction_id ) {
			error_log( '[PCW Referrals] Erro ao creditar recompensa na wallet' );
			return;
		}

		// Atualizar indicação
		$referrals->update_status( $referral->id, 'rewarded', array(
			'referred_user_id'       => $buyer_user_id ?: null,
			'referred_order_id'      => $order_id,
			'reward_amount'          => $reward_amount,
			'reward_transaction_id'  => $transaction_id,
			'conversion_count'       => $referral->conversion_count + 1,
			'converted_at'           => $referral->converted_at ?: current_time( 'mysql' ),
		) );

		// Atualizar contadores do código
		$codes->increment_counter( $code_data->id, 'conversions', $reward_amount );

		// Marcar pedido como processado
		$order->update_meta_data( '_pcw_referral_reward_processed', true );
		$order->update_meta_data( '_pcw_referral_reward_amount', $reward_amount );
		$order->update_meta_data( '_pcw_referral_reward_transaction_id', $transaction_id );
		$order->save();

		// Disparar ação
		do_action( 'pcw_referral_rewarded', $referral->id, $reward_amount, $transaction_id, $order_id );

		// Processar recompensa do indicado (se habilitado)
		$this->process_referred_reward( $order, $referral, $settings );

		// Enviar email de notificação para quem indicou
		$this->send_reward_notification( $code_data->user_id, $referral, $reward_amount );

		// Registrar click como convertido
		$this->mark_click_converted( $referral_code, $order_id );
	}

	/**
	 * Verificar se pode gerar recompensa
	 *
	 * @param object $referral Dados da indicação.
	 * @param string $buyer_email Email do comprador.
	 * @return bool
	 */
	private function can_reward( $referral, $buyer_email ) {
		$settings = $this->get_settings();
		$limit_type = $settings['reward_limit_type'];

		switch ( $limit_type ) {
			case 'first':
				// Apenas primeira compra
				return 0 === (int) $referral->conversion_count;

			case 'limited':
				// Limite de X compras
				$limit = absint( $settings['reward_limit_count'] );
				return (int) $referral->conversion_count < $limit;

			case 'unlimited':
			default:
				return true;
		}
	}

	/**
	 * Calcular valor da recompensa
	 *
	 * @param WC_Order $order Pedido.
	 * @param array    $settings Configurações.
	 * @return float
	 */
	private function calculate_reward( $order, $settings ) {
		$order_total = floatval( $order->get_total() );

		if ( 'percentage' === $settings['reward_type'] ) {
			$reward = ( $order_total * floatval( $settings['reward_amount'] ) ) / 100;

			// Aplicar limite máximo se configurado
			if ( $settings['max_reward_amount'] > 0 ) {
				$reward = min( $reward, floatval( $settings['max_reward_amount'] ) );
			}
		} else {
			$reward = floatval( $settings['reward_amount'] );
		}

		return round( $reward, 2 );
	}

	/**
	 * Processar recompensa para o indicado
	 *
	 * @param WC_Order $order Pedido.
	 * @param object   $referral Indicação.
	 * @param array    $settings Configurações.
	 */
	private function process_referred_reward( $order, $referral, $settings ) {
		// Verificar se está habilitado
		if ( 'yes' !== $settings['referred_reward_enabled'] ) {
			return;
		}

		$buyer_user_id = $order->get_user_id();

		if ( ! $buyer_user_id ) {
			return; // Precisa estar logado para receber recompensa
		}

		// Verificar se é apenas primeira compra
		if ( 'yes' === $settings['referred_reward_first_only'] ) {
			if ( (int) $referral->conversion_count > 0 ) {
				return; // Já recebeu na primeira compra
			}
		}

		// Calcular valor
		$order_total = floatval( $order->get_total() );

		if ( 'percentage' === $settings['referred_reward_type'] ) {
			$reward = ( $order_total * floatval( $settings['referred_reward_amount'] ) ) / 100;
		} else {
			$reward = floatval( $settings['referred_reward_amount'] );
		}

		$reward = round( $reward, 2 );

		if ( $reward <= 0 ) {
			return;
		}

		// Creditar na wallet do indicado
		$wallet = new PCW_Wallet( $buyer_user_id );
		$transaction_id = $wallet->credit(
			$reward,
			'referral_bonus',
			__( 'Bônus por ter sido indicado', 'person-cash-wallet' ),
			$order->get_id()
		);

		if ( $transaction_id ) {
			// Atualizar indicação com recompensa do indicado
			global $wpdb;
			$table = $wpdb->prefix . 'pcw_referrals';

			$wpdb->update(
				$table,
				array(
					'referred_reward_amount'         => $reward,
					'referred_reward_transaction_id' => $transaction_id,
				),
				array( 'id' => $referral->id ),
				array( '%f', '%d' ),
				array( '%d' )
			);

			// Marcar no pedido
			$order->update_meta_data( '_pcw_referred_reward_amount', $reward );
			$order->update_meta_data( '_pcw_referred_reward_transaction_id', $transaction_id );
			$order->save();

			do_action( 'pcw_referred_rewarded', $referral->id, $buyer_user_id, $reward, $transaction_id );
		}
	}

	/**
	 * Enviar notificação de recompensa
	 *
	 * @param int    $user_id ID do usuário que indicou.
	 * @param object $referral Dados da indicação.
	 * @param float  $amount Valor da recompensa.
	 */
	private function send_reward_notification( $user_id, $referral, $amount ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		// Verificar se notificações estão habilitadas
		if ( 'yes' !== get_option( 'pcw_notifications_enabled', 'yes' ) ) {
			return;
		}

		$settings = get_option( 'pcw_notification_settings', array() );

		if ( isset( $settings['referral_reward']['enabled'] ) && 'no' === $settings['referral_reward']['enabled'] ) {
			return;
		}

		// Carregar template de email
		$subject = ! empty( $settings['referral_reward']['subject'] )
			? $settings['referral_reward']['subject']
			: __( '🎉 Você ganhou uma recompensa por indicação!', 'person-cash-wallet' );

		$body = ! empty( $settings['referral_reward']['body'] )
			? $settings['referral_reward']['body']
			: $this->get_default_reward_email_body();

		// Substituir placeholders
		$wallet = new PCW_Wallet( $user_id );

		$placeholders = array(
			'{customer_name}'    => $user->first_name ?: $user->display_name,
			'{referred_name}'    => $referral->referred_name,
			'{reward_amount}'    => PCW_Formatters::format_money( $amount ),
			'{current_balance}'  => PCW_Formatters::format_money( $wallet->get_balance() ),
			'{site_name}'        => get_bloginfo( 'name' ),
			'{site_url}'         => home_url(),
			'{my_account_url}'   => wc_get_account_endpoint_url( 'indicacoes' ),
		);

		$subject = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $subject );
		$body = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $body );

		// Enviar email
		PCW_Email_Handler::send_email( $user->user_email, $subject, $body );
	}

	/**
	 * Obter corpo padrão do email de recompensa
	 *
	 * @return string
	 */
	private function get_default_reward_email_body() {
		return '
<p>Olá, {customer_name}!</p>

<p>Ótimas notícias! 🎉</p>

<p>Sua indicação <strong>{referred_name}</strong> acabou de fazer uma compra e você ganhou <strong>{reward_amount}</strong> de recompensa!</p>

<p>Seu saldo atual na wallet: <strong>{current_balance}</strong></p>

<p>Continue indicando amigos e ganhe ainda mais!</p>

<p><a href="{my_account_url}" style="display: inline-block; background: #667eea; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 6px;">Ver Minhas Indicações</a></p>

<p>Atenciosamente,<br>Equipe {site_name}</p>
';
	}

	/**
	 * Marcar click como convertido
	 *
	 * @param string $code Código de indicação.
	 * @param int    $order_id ID do pedido.
	 */
	private function mark_click_converted( $code, $order_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_clicks';

		// Marcar último click deste código como convertido
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} 
				SET converted = 1, order_id = %d 
				WHERE referral_code = %s AND converted = 0 
				ORDER BY created_at DESC 
				LIMIT 1",
				absint( $order_id ),
				sanitize_text_field( $code )
			)
		);
	}

	/**
	 * Obter histórico de recompensas de um usuário
	 *
	 * @param int   $user_id ID do usuário.
	 * @param array $args Argumentos.
	 * @return array
	 */
	public function get_user_rewards_history( $user_id, $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referrals';

		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
				WHERE referrer_user_id = %d AND status = 'rewarded' AND reward_amount > 0
				ORDER BY rewarded_at DESC
				LIMIT %d OFFSET %d",
				absint( $user_id ),
				absint( $args['limit'] ),
				absint( $args['offset'] )
			)
		);
	}
}
