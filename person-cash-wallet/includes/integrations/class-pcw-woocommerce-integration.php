<?php
/**
 * Classe de integração com WooCommerce
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de integração WooCommerce
 */
class PCW_WooCommerce_Integration {

	/**
	 * Inicializar
	 */
	public function init() {
		// Registrar hooks dinâmicos baseado nas configurações
		$this->register_dynamic_hooks();

		// Transferir cashback pendente para wallet após X dias
		add_action( 'pcw_daily_expiration_check', array( $this, 'transfer_pending_cashback_to_wallet' ) );

		// Adicionar campo de cashback no checkout (futuro)
		add_action( 'woocommerce_review_order_before_payment', array( $this, 'display_cashback_available' ) );

		// Adicionar meta box no pedido
		add_action( 'add_meta_boxes', array( $this, 'add_order_meta_box' ) );
	}

	/**
	 * Registrar hooks dinâmicos baseado nas configurações
	 */
	private function register_dynamic_hooks() {
		$settings = get_option( 'pcw_general_settings', array() );

		// Status que geram cashback
		$generate_statuses = ! empty( $settings['generate_cashback_statuses'] ) ? $settings['generate_cashback_statuses'] : array( 'completed' );
		
		foreach ( $generate_statuses as $status ) {
			$hook = 'woocommerce_order_status_' . $status;
			add_action( $hook, array( $this, 'process_cashback_on_order_completion' ), 10, 2 );
		}

		// Status que cancelam/revertem cashback
		$cancel_statuses = ! empty( $settings['cancel_cashback_statuses'] ) ? $settings['cancel_cashback_statuses'] : array( 'cancelled', 'refunded', 'failed' );
		
		foreach ( $cancel_statuses as $status ) {
			$hook = 'woocommerce_order_status_' . $status;
			add_action( $hook, array( $this, 'reverse_cashback_on_order_cancel' ), 10, 2 );
		}

		// Hook para calcular nível (apenas nos status de geração)
		foreach ( $generate_statuses as $status ) {
			$hook = 'woocommerce_order_status_' . $status;
			add_action( $hook, array( $this, 'calculate_user_level_after_order' ), 20, 2 );
		}
	}

	/**
	 * Processar cashback quando pedido atinge status configurado
	 *
	 * @param int            $order_id ID do pedido.
	 * @param WC_Order|false $order Objeto do pedido (opcional).
	 */
	public function process_cashback_on_order_completion( $order_id, $order = false ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			return; // Apenas para usuários logados
		}

		// Verificar se cashback já foi processado
		$cashback_processed = $order->get_meta( '_pcw_cashback_processed' );
		if ( $cashback_processed ) {
			return;
		}

		// Verificar se cashback está habilitado
		if ( 'yes' !== get_option( 'pcw_cashback_enabled', 'yes' ) ) {
			return;
		}

		// Buscar regras aplicáveis
		$rules = PCW_Cashback_Rules::get_active_rules();
		$total_cashback = 0;
		$applied_rules = array();

		foreach ( $rules as $rule ) {
			// Verificar se regra se aplica
			if ( ! PCW_Cashback_Rules::rule_applies( $order, $rule ) ) {
				continue;
			}

			// Calcular cashback
			$cashback_amount = PCW_Cashback_Rules::calculate_cashback( $order, $rule );

			if ( $cashback_amount > 0 ) {
				$total_cashback += $cashback_amount;
				$applied_rules[] = array(
					'rule_id' => $rule->id,
					'amount'  => $cashback_amount,
				);
			}
		}

		// Se não há regras aplicáveis, usar regra padrão
		if ( empty( $applied_rules ) && $total_cashback === 0 ) {
			$default_percentage = floatval( get_option( 'pcw_cashback_default_percentage', 0 ) );
			if ( $default_percentage > 0 ) {
				$order_total = floatval( $order->get_total() );
				$total_cashback = ( $order_total * $default_percentage ) / 100;
			}
		}

		// Criar cashback se houver valor
		if ( $total_cashback > 0 ) {
			$cashback = new PCW_Cashback( $user_id );

			// Se múltiplas regras, criar um cashback por regra
			if ( ! empty( $applied_rules ) ) {
				foreach ( $applied_rules as $applied_rule ) {
					$cashback->create( $order_id, $applied_rule['amount'], $applied_rule['rule_id'] );
				}
			} else {
				// Regra padrão
				$cashback->create( $order_id, $total_cashback, 0 );
			}

			// Marcar como processado
			$order->update_meta_data( '_pcw_cashback_processed', true );
			$order->update_meta_data( '_pcw_cashback_amount', $total_cashback );
			$order->save();

			// Transferência imediata para wallet se configurado
			$auto_transfer_enabled = 'yes' === get_option( 'pcw_auto_transfer_to_wallet', 'yes' );
			$auto_transfer_days    = absint( get_option( 'pcw_auto_transfer_days', 0 ) );

			if ( $auto_transfer_enabled && 0 === $auto_transfer_days ) {
				$this->transfer_cashback_to_wallet_immediately( $order_id, $user_id, $total_cashback );
			}

			// Disparar ação
			do_action( 'pcw_cashback_processed', $order_id, $user_id, $total_cashback );

			// Enviar email de cashback ganho
			if ( 'yes' === get_option( 'pcw_notifications_enabled', 'yes' ) ) {
				$user = get_userdata( $user_id );
				if ( $user ) {
					// Buscar cashback criado para enviar email
					global $wpdb;
					$cashback_table = $wpdb->prefix . 'pcw_cashback';
					$cashback_list = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM {$cashback_table} WHERE order_id = %d AND user_id = %d ORDER BY id DESC LIMIT 1",
							$order_id,
							$user_id
						)
					);

					if ( ! empty( $cashback_list ) ) {
						PCW_Email_Handler::send_cashback_earned( $cashback_list[0], $user );
					}
				}
			}
		}
	}

	/**
	 * Transferir cashback pendente para wallet
	 */
	public function transfer_pending_cashback_to_wallet() {
		// Verificar se transferência automática está habilitada
		if ( 'yes' !== get_option( 'pcw_auto_transfer_to_wallet', 'yes' ) ) {
			return;
		}

		$transfer_days = absint( get_option( 'pcw_auto_transfer_days', 0 ) );

		// Se dias = 0, a transferência é feita imediatamente no processo_cashback_on_order_completion
		if ( 0 === $transfer_days ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_cashback';

		// Buscar cashback pendente há X dias
		$date_threshold = date( 'Y-m-d H:i:s', strtotime( "-{$transfer_days} days" ) );

		$pending_cashback = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
				WHERE status = 'pending' 
				AND earned_date <= %s
				LIMIT 100",
				$date_threshold
			)
		);

		foreach ( $pending_cashback as $cashback ) {
			// Atualizar status para available
			$wpdb->update(
				$table,
				array(
					'status'     => 'available',
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $cashback->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			// Transferir para wallet
			$wallet = new PCW_Wallet( $cashback->user_id );
			$wallet->credit(
				$cashback->amount,
				'cashback',
				sprintf( __( 'Cashback do pedido #%d', 'person-cash-wallet' ), $cashback->order_id ),
				$cashback->order_id,
				$cashback->id
			);

			// Marcar cashback como transferido
			$wpdb->update(
				$table,
				array(
					'status'     => 'used',
					'used_date'  => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $cashback->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Transferir cashback para wallet imediatamente
	 *
	 * @param int   $order_id ID do pedido.
	 * @param int   $user_id ID do usuário.
	 * @param float $amount Valor do cashback.
	 */
	private function transfer_cashback_to_wallet_immediately( $order_id, $user_id, $amount ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_cashback';

		// Buscar cashback pendente do pedido
		$pending_cashback = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
				WHERE order_id = %d 
				AND user_id = %d 
				AND status = 'pending'",
				$order_id,
				$user_id
			)
		);

		foreach ( $pending_cashback as $cashback ) {
			// Atualizar status para available
			$wpdb->update(
				$table,
				array(
					'status'     => 'available',
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $cashback->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			// Transferir para wallet
			$wallet = new PCW_Wallet( $cashback->user_id );
			$wallet->credit(
				$cashback->amount,
				'cashback',
				sprintf( __( 'Cashback do pedido #%d', 'person-cash-wallet' ), $cashback->order_id ),
				$cashback->order_id,
				$cashback->id
			);

			// Marcar cashback como usado (já transferido para wallet)
			$wpdb->update(
				$table,
				array(
					'status'     => 'used',
					'used_date'  => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $cashback->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Exibir cashback disponível no checkout
	 */
	public function display_cashback_available() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$cashback = new PCW_Cashback();
		$available_balance = $cashback->get_available_balance();

		if ( $available_balance <= 0 ) {
			return;
		}

		?>
		<tr class="pcw-cashback-available">
			<th><?php esc_html_e( 'Cashback Disponível', 'person-cash-wallet' ); ?></th>
			<td>
				<strong><?php echo wp_kses_post( PCW_Formatters::format_money( $available_balance ) ); ?></strong>
				<p class="description">
					<?php esc_html_e( 'Você pode usar este cashback na sua próxima compra.', 'person-cash-wallet' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Adicionar meta box no pedido
	 */
	public function add_order_meta_box() {
		add_meta_box(
			'pcw-order-cashback',
			__( 'Cashback & Wallet', 'person-cash-wallet' ),
			array( $this, 'render_order_cashback_meta_box' ),
			'shop_order',
			'side',
			'default'
		);

		// Também adicionar suporte para HPOS (High-Performance Order Storage)
		add_meta_box(
			'pcw-order-cashback',
			__( 'Cashback & Wallet', 'person-cash-wallet' ),
			array( $this, 'render_order_cashback_meta_box' ),
			'woocommerce_page_wc-orders',
			'side',
			'default'
		);
	}

	/**
	 * Renderizar meta box de cashback no pedido
	 *
	 * @param WP_Post|WC_Order $post Post object ou Order (para HPOS).
	 */
	public function render_order_cashback_meta_box( $post ) {
		// Suporte a HPOS
		if ( $post instanceof WC_Order ) {
			$order = $post;
		} else {
			$order = wc_get_order( $post->ID );
		}

		if ( ! $order ) {
			return;
		}

		// ========================================
		// WALLET PAYMENT DETAILS
		// ========================================
		$wallet_used = $order->get_meta( '_pcw_wallet_used' );
		$wallet_balance_before = $order->get_meta( '_pcw_wallet_balance_before' );
		$wallet_balance_after = $order->get_meta( '_pcw_wallet_balance_after' );

		if ( $wallet_used && $wallet_used > 0 ) {
			?>
			<div style="background: #f0f7ff; padding: 12px; border-radius: 6px; margin-bottom: 15px; border-left: 3px solid #667eea;">
				<p style="margin: 0 0 8px 0;"><strong style="color: #667eea;">💳 <?php esc_html_e( 'Pagamento com Wallet', 'person-cash-wallet' ); ?></strong></p>
				<table style="width: 100%; font-size: 12px;">
					<?php if ( $wallet_balance_before ) : ?>
					<tr>
						<td style="padding: 3px 0;"><?php esc_html_e( 'Saldo antes:', 'person-cash-wallet' ); ?></td>
						<td style="text-align: right; padding: 3px 0;"><?php echo wp_kses_post( wc_price( $wallet_balance_before ) ); ?></td>
					</tr>
					<?php endif; ?>
					<tr style="color: #e74c3c;">
						<td style="padding: 3px 0;"><strong><?php esc_html_e( 'Valor usado:', 'person-cash-wallet' ); ?></strong></td>
						<td style="text-align: right; padding: 3px 0;"><strong>- <?php echo wp_kses_post( wc_price( $wallet_used ) ); ?></strong></td>
					</tr>
					<?php if ( $wallet_balance_after !== '' && $wallet_balance_after !== false ) : ?>
					<tr style="color: #27ae60;">
						<td style="padding: 3px 0;"><strong><?php esc_html_e( 'Saldo apos:', 'person-cash-wallet' ); ?></strong></td>
						<td style="text-align: right; padding: 3px 0;"><strong><?php echo wp_kses_post( wc_price( $wallet_balance_after ) ); ?></strong></td>
					</tr>
					<?php endif; ?>
				</table>
			</div>
			<?php
		}

		// ========================================
		// CASHBACK DETAILS
		// ========================================
		$cashback_processed = $order->get_meta( '_pcw_cashback_processed' );
		$cashback_amount = $order->get_meta( '_pcw_cashback_amount' );

		if ( $cashback_processed && $cashback_amount ) {
			?>
			<p>
				<strong><?php esc_html_e( 'Cashback Gerado:', 'person-cash-wallet' ); ?></strong><br>
				<?php echo wp_kses_post( PCW_Formatters::format_money( $cashback_amount ) ); ?>
			</p>
			<?php
		} else {
			?>
			<p style="color: #999; font-size: 12px;"><?php esc_html_e( 'Cashback ainda não foi processado para este pedido.', 'person-cash-wallet' ); ?></p>
			<?php
		}

		// Buscar cashback relacionado ao pedido
		$user_id = $order->get_user_id();
		if ( $user_id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'pcw_cashback';

			$cashback_list = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at DESC",
					$post->ID
				)
			);

			if ( ! empty( $cashback_list ) ) {
				?>
				<hr>
				<p><strong><?php esc_html_e( 'Registros de Cashback:', 'person-cash-wallet' ); ?></strong></p>
				<ul>
					<?php foreach ( $cashback_list as $cb ) : ?>
						<li>
							<?php echo wp_kses_post( PCW_Formatters::format_money( $cb->amount ) ); ?>
							- <?php echo esc_html( ucfirst( $cb->status ) ); ?>
							<?php if ( $cb->expires_date ) : ?>
								<br><small><?php esc_html_e( 'Expira:', 'person-cash-wallet' ); ?> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $cb->expires_date ) ) ); ?></small>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php
			}
		}
	}

	/**
	 * Calcular nível do usuário após pedido concluído
	 *
	 * @param int            $order_id ID do pedido.
	 * @param WC_Order|false $order Objeto do pedido (opcional).
	 */
	public function calculate_user_level_after_order( $order_id, $order = false ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			return;
		}

		// Verificar se níveis estão habilitados
		$settings = get_option( 'pcw_general_settings', array() );
		if ( isset( $settings['levels_enabled'] ) && 'no' === $settings['levels_enabled'] ) {
			return;
		}

		// Calcular nível do usuário
		PCW_Level_Calculator::calculate_user_level( $user_id );
	}

	/**
	 * Reverter cashback quando pedido é cancelado/reembolsado
	 *
	 * @param int            $order_id ID do pedido.
	 * @param WC_Order|false $order Objeto do pedido (opcional).
	 */
	public function reverse_cashback_on_order_cancel( $order_id, $order = false ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			return;
		}

		// Verificar se cashback foi processado
		$cashback_processed = $order->get_meta( '_pcw_cashback_processed' );
		if ( ! $cashback_processed ) {
			return; // Não há cashback para reverter
		}

		// Verificar se já foi revertido
		$cashback_reversed = $order->get_meta( '_pcw_cashback_reversed' );
		if ( $cashback_reversed ) {
			return; // Já foi revertido
		}

		// Buscar cashback relacionado ao pedido
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_cashback';

		$cashback_list = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d AND user_id = %d AND status != 'expired'",
				$order_id,
				$user_id
			)
		);

		if ( empty( $cashback_list ) ) {
			return; // Nenhum cashback para reverter
		}

		$total_reversed = 0;

		foreach ( $cashback_list as $cashback ) {
			// Se cashback está disponível ou pendente: remover
			if ( in_array( $cashback->status, array( 'available', 'pending' ) ) ) {
				$wpdb->update(
					$table,
					array(
						'status'     => 'cancelled',
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => $cashback->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);

				$total_reversed += floatval( $cashback->amount );

				// Adicionar histórico
				$cashback_obj = new PCW_Cashback( $user_id );
				$cashback_obj->add_history(
					$cashback->id,
					'cancelled',
					sprintf( __( 'Cashback revertido devido ao cancelamento do pedido #%d', 'person-cash-wallet' ), $order_id )
				);
			}
			// Se cashback já foi usado: debitar da wallet
			elseif ( 'used' === $cashback->status ) {
				$wallet = new PCW_Wallet( $user_id );
				$wallet->debit(
					floatval( $cashback->amount ),
					'cashback_reversal',
					sprintf( __( 'Reversão de cashback do pedido #%d cancelado', 'person-cash-wallet' ), $order_id ),
					$order_id
				);

				$wpdb->update(
					$table,
					array(
						'status'     => 'cancelled',
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => $cashback->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);

				$total_reversed += floatval( $cashback->amount );

				// Adicionar histórico
				$cashback_obj = new PCW_Cashback( $user_id );
				$cashback_obj->add_history(
					$cashback->id,
					'cancelled',
					sprintf( __( 'Cashback já usado foi debitado da wallet devido ao cancelamento do pedido #%d', 'person-cash-wallet' ), $order_id )
				);
			}
		}

		if ( $total_reversed > 0 ) {
			// Marcar como revertido
			$order->update_meta_data( '_pcw_cashback_reversed', true );
			$order->update_meta_data( '_pcw_cashback_reversed_amount', $total_reversed );
			$order->update_meta_data( '_pcw_cashback_reversed_date', current_time( 'mysql' ) );
			$order->save();

			// Disparar ação
			do_action( 'pcw_cashback_reversed', $order_id, $user_id, $total_reversed );

			// Adicionar nota no pedido
			$order->add_order_note(
				sprintf(
					__( 'Cashback de %s foi revertido/cancelado devido à mudança de status.', 'person-cash-wallet' ),
					PCW_Formatters::format_money_plain( $total_reversed )
				)
			);
		}
	}
}
