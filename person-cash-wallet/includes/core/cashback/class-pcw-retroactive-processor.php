<?php
/**
 * Processador de Cashback Retroativo
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Classe para processar cashback retroativo em lotes
 */
class PCW_Retroactive_Processor {

	/**
	 * Buscar pedidos com base nos filtros
	 *
	 * @param array $filters Filtros.
	 * @return array Array de IDs de pedidos.
	 */
	public static function find_orders( $filters ) {
		$args = array(
			'limit'  => -1,
			'return' => 'ids',
		);

		// Período.
		if ( ! empty( $filters['date_from'] ) ) {
			$args['date_created'] = '>=' . strtotime( $filters['date_from'] );
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$date_to = strtotime( $filters['date_to'] . ' 23:59:59' );
			if ( isset( $args['date_created'] ) ) {
				$args['date_created'] = $args['date_created'] . '...' . $date_to;
			} else {
				$args['date_created'] = '<=' . $date_to;
			}
		}

		// Status.
		if ( ! empty( $filters['status'] ) && is_array( $filters['status'] ) ) {
			$args['status'] = $filters['status'];
		}

		// Valor mínimo.
		if ( ! empty( $filters['min_amount'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_order_total',
				'value'   => floatval( $filters['min_amount'] ),
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}

		// Valor máximo.
		if ( ! empty( $filters['max_amount'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_order_total',
				'value'   => floatval( $filters['max_amount'] ),
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}

		// Clientes específicos.
		if ( ! empty( $filters['customers'] ) && is_array( $filters['customers'] ) ) {
			$args['customer_id'] = array_map( 'absint', $filters['customers'] );
		}

		$order_ids = wc_get_orders( $args );

		// Filtros adicionais que não podem ser feitos via wc_get_orders.
		if ( ! empty( $filters['products'] ) && is_array( $filters['products'] ) ) {
			$order_ids = self::filter_by_products( $order_ids, $filters['products'] );
		}

		// Ignorar pedidos que já têm cashback.
		if ( ! empty( $filters['ignore_existing'] ) ) {
			$order_ids = self::filter_existing_cashback( $order_ids );
		}

		return $order_ids;
	}

	/**
	 * Filtrar pedidos que contêm produtos específicos
	 *
	 * @param array $order_ids IDs dos pedidos.
	 * @param array $product_ids IDs dos produtos.
	 * @return array
	 */
	private static function filter_by_products( $order_ids, $product_ids ) {
		$filtered = array();

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();
				if ( in_array( $product_id, $product_ids, true ) ) {
					$filtered[] = $order_id;
					break;
				}
			}
		}

		return $filtered;
	}

	/**
	 * Filtrar pedidos que já têm cashback
	 *
	 * @param array $order_ids IDs dos pedidos.
	 * @return array
	 */
	private static function filter_existing_cashback( $order_ids ) {
		global $wpdb;

		if ( empty( $order_ids ) ) {
			return array();
		}

		$table       = $wpdb->prefix . 'pcw_cashback';
		$order_ids_str = implode( ',', array_map( 'absint', $order_ids ) );

		$existing = $wpdb->get_col(
			"SELECT DISTINCT order_id FROM {$table} WHERE order_id IN ({$order_ids_str})"
		);

		return array_diff( $order_ids, $existing );
	}

	/**
	 * Calcular cashback para um pedido
	 *
	 * @param int   $order_id ID do pedido.
	 * @param array $filters Filtros com regra de cashback.
	 * @return float
	 */
	public static function calculate_cashback( $order_id, $filters ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return 0;
		}

		$order_total = floatval( $order->get_total() );
		$cashback    = 0;

		// Aplicar regra atual.
		if ( 'current' === $filters['cashback_rule'] ) {
			$rules = PCW_Cashback_Rules::get_active_rules();

			foreach ( $rules as $rule ) {
				// Verificar valor mínimo da regra.
				if ( ! empty( $rule->min_order_amount ) && $order_total < floatval( $rule->min_order_amount ) ) {
					continue;
				}

				if ( 'percentage' === $rule->type ) {
					$cashback = ( $order_total * floatval( $rule->value ) ) / 100;
				} elseif ( 'fixed' === $rule->type ) {
					$cashback = floatval( $rule->value );
				}

				// Aplicar limite máximo se configurado.
				if ( ! empty( $rule->max_cashback_amount ) && floatval( $rule->max_cashback_amount ) > 0 ) {
					$cashback = min( $cashback, floatval( $rule->max_cashback_amount ) );
				}

				break; // Usar apenas a primeira regra ativa.
			}
		}

		// Regra específica.
		if ( 'specific' === $filters['cashback_rule'] && ! empty( $filters['rule_id'] ) ) {
			$rule = PCW_Cashback_Rules::get_rule( absint( $filters['rule_id'] ) );

			if ( $rule ) {
				if ( 'percentage' === $rule->type ) {
					$cashback = ( $order_total * floatval( $rule->value ) ) / 100;
				} elseif ( 'fixed' === $rule->type ) {
					$cashback = floatval( $rule->value );
				}

				if ( ! empty( $rule->max_cashback_amount ) && floatval( $rule->max_cashback_amount ) > 0 ) {
					$cashback = min( $cashback, floatval( $rule->max_cashback_amount ) );
				}
			}
		}

		// Valor fixo.
		if ( 'fixed' === $filters['cashback_rule'] && ! empty( $filters['fixed_amount'] ) ) {
			$cashback = floatval( $filters['fixed_amount'] );
		}

		// Percentual fixo.
		if ( 'percentage' === $filters['cashback_rule'] && ! empty( $filters['percentage_value'] ) ) {
			$cashback = ( $order_total * floatval( $filters['percentage_value'] ) ) / 100;
		}

		return round( $cashback, 2 );
	}

	/**
	 * Processar lote de pedidos
	 *
	 * @param array  $order_ids IDs dos pedidos.
	 * @param array  $filters Filtros.
	 * @param string $batch_id Batch ID.
	 * @return array Resultado do processamento.
	 */
	public static function process_batch( $order_ids, $filters, $batch_id ) {
		$results = array(
			'processed' => 0,
			'success'   => 0,
			'errors'    => 0,
			'amount'    => 0,
			'logs'      => array(),
		);

		foreach ( $order_ids as $order_id ) {
			$results['processed']++;

			try {
				$cashback = self::calculate_cashback( $order_id, $filters );

				if ( $cashback <= 0 ) {
					$results['logs'][] = sprintf(
						/* translators: %d: Order ID */
						__( 'Pedido #%d: Cashback calculado é R$ 0,00 - ignorado', 'person-cash-wallet' ),
						$order_id
					);
					continue;
				}

				$order       = wc_get_order( $order_id );
				$customer_id = $order->get_customer_id();

				if ( ! $customer_id ) {
					$results['errors']++;
					$results['logs'][] = sprintf(
						/* translators: %d: Order ID */
						__( 'Pedido #%d: Cliente não encontrado', 'person-cash-wallet' ),
						$order_id
					);
					continue;
				}

				// Calcular data de expiração.
				$expires_date = null;
				$expiration_days = absint( get_option( 'pcw_cashback_expiration_days', 0 ) );

				if ( $expiration_days > 0 ) {
					$expires_date = gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiration_days} days" ) );
				}

				// Inserir cashback.
				$cashback_id = PCW_Cashback::add(
					$customer_id,
					$cashback,
					'retroactive',
					$order_id,
					sprintf(
						/* translators: %s: Batch ID, %d: Order ID */
						__( 'Cashback retroativo [%s] - Pedido #%d', 'person-cash-wallet' ),
						$batch_id,
						$order_id
					),
					$expires_date
				);

				if ( $cashback_id ) {
					$results['success']++;
					$results['amount'] += $cashback;

					// Enviar email se habilitado.
					if ( ! empty( $filters['send_email'] ) ) {
						self::send_notification( $customer_id, $order_id, $cashback );
						$results['logs'][] = sprintf(
							/* translators: %1$d: User ID, %2$s: Email */
							__( '📧 Email enviado para: %2$s (ID: %1$d)', 'person-cash-wallet' ),
							$customer_id,
							get_userdata( $customer_id )->user_email
						);
					}

					$results['logs'][] = sprintf(
						/* translators: %1$d: Order ID, %2$s: Amount */
						__( 'Pedido #%1$d: Cashback de %2$s gerado com sucesso', 'person-cash-wallet' ),
						$order_id,
						wc_price( $cashback )
					);
				} else {
					$results['errors']++;
					$results['logs'][] = sprintf(
						/* translators: %d: Order ID */
						__( 'Pedido #%d: Erro ao inserir cashback no banco', 'person-cash-wallet' ),
						$order_id
					);
				}
			} catch ( Exception $e ) {
				$results['errors']++;
				$results['logs'][] = sprintf(
					/* translators: %1$d: Order ID, %2$s: Error message */
					__( 'Pedido #%1$d: Erro - %2$s', 'person-cash-wallet' ),
					$order_id,
					$e->getMessage()
				);
			}
		}

		do_action( 'pcw_retroactive_batch_processed', $batch_id, $results );

		return $results;
	}

	/**
	 * Enviar notificação de cashback retroativo
	 *
	 * @param int   $user_id ID do usuário.
	 * @param int   $order_id ID do pedido.
	 * @param float $amount Valor do cashback.
	 */
	private static function send_notification( $user_id, $order_id, $amount ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		// Buscar configurações da página de notificações.
		$notification_settings = get_option( 'pcw_notification_settings', array() );
		$retroactive_settings  = isset( $notification_settings['cashback_retroactive'] ) ? $notification_settings['cashback_retroactive'] : array();

		// Defaults.
		$enabled = isset( $retroactive_settings['enabled'] ) ? $retroactive_settings['enabled'] : 'yes';

		if ( 'yes' !== $enabled ) {
			return;
		}

		$order        = wc_get_order( $order_id );
		$order_date   = $order ? $order->get_date_created()->format( 'd/m/Y' ) : '-';
		
		// Obter saldo atual da wallet.
		$wallet          = new PCW_Wallet( $user_id );
		$current_balance = $wallet->get_balance();

		// Formatação plain text para assunto (sem HTML).
		$amount_plain = 'R$ ' . number_format( $amount, 2, ',', '.' );

		// Assunto das configurações ou default.
		$subject = isset( $retroactive_settings['subject'] ) && ! empty( $retroactive_settings['subject'] )
			? $retroactive_settings['subject']
			: __( '🎁 Você ganhou cashback retroativo!', 'person-cash-wallet' );

		// Substituir variáveis no assunto.
		$subject = str_replace( '{cashback_amount}', $amount_plain, $subject );
		$subject = str_replace( '{order_id}', $order_id, $subject );

		// Corpo do email das configurações.
		$custom_body = isset( $retroactive_settings['body'] ) ? $retroactive_settings['body'] : '';

		if ( ! empty( $custom_body ) ) {
			// Usar template customizado.
			$body_replacements = array(
				'{customer_name}'   => $user->display_name,
				'{customer_email}'  => $user->user_email,
				'{cashback_amount}' => wc_price( $amount ),
				'{order_id}'        => $order_id,
				'{order_date}'      => $order_date,
				'{current_balance}' => wc_price( $current_balance ),
			);

			$body = str_replace( array_keys( $body_replacements ), array_values( $body_replacements ), $custom_body );
		} else {
			// Usar template padrão bonito.
			$wallet_url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'wallet' ) : wc_get_page_permalink( 'myaccount' );
			$shop_url   = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url();

			$body = '
			<h2 style="color: #059669; margin: 0 0 20px 0; font-size: 22px;">
				🎁 Boa notícia, ' . esc_html( $user->display_name ) . '!
			</h2>
			
			<p style="font-size: 16px; margin-bottom: 24px;">
				Você acaba de receber um <strong>cashback retroativo</strong> referente a uma compra anterior. Esse valor já está disponível na sua carteira!
			</p>

			<div style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-left: 4px solid #10b981; padding: 20px; border-radius: 8px; margin: 24px 0;">
				<table width="100%" cellpadding="0" cellspacing="0" border="0">
					<tr>
						<td style="padding: 8px 0;">
							<span style="color: #6b7280; font-size: 13px; display: block;">Valor do Cashback</span>
							<span style="color: #059669; font-size: 28px; font-weight: bold; display: block; margin-top: 4px;">
								' . wc_price( $amount ) . '
							</span>
						</td>
					</tr>
					<tr>
						<td style="padding: 8px 0; border-top: 1px solid rgba(16, 185, 129, 0.2);">
							<span style="color: #6b7280; font-size: 13px;">📦 Pedido: <strong>#' . absint( $order_id ) . '</strong></span>
						</td>
					</tr>
					<tr>
						<td style="padding: 8px 0;">
							<span style="color: #6b7280; font-size: 13px;">📅 Data do Pedido: <strong>' . esc_html( $order_date ) . '</strong></span>
						</td>
					</tr>
					<tr>
						<td style="padding: 12px 0 0 0; border-top: 1px solid rgba(16, 185, 129, 0.2);">
							<span style="color: #6b7280; font-size: 13px; display: block;">Saldo Atual da Carteira</span>
							<span style="color: #047857; font-size: 20px; font-weight: bold; display: block; margin-top: 4px;">
								' . wc_price( $current_balance ) . '
							</span>
						</td>
					</tr>
				</table>
			</div>

			<p style="margin: 24px 0;">
				Use seu cashback na sua próxima compra e economize ainda mais! 💰
			</p>

			<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0;">
				<tr>
					<td align="center">
						<a href="' . esc_url( $shop_url ) . '" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px; margin-right: 12px;">
							Comprar Agora
						</a>
						<a href="' . esc_url( $wallet_url ) . '" style="display: inline-block; background: #f3f4f6; color: #374151; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px;">
							Ver Minha Carteira
						</a>
					</td>
				</tr>
			</table>

			<p style="color: #9ca3af; font-size: 13px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
				<em>Aproveite seu cashback para economizar nas próximas compras!</em>
			</p>';
		}

		PCW_Email_Handler::send( $user->user_email, $subject, $body );
	}

	/**
	 * Gerar preview dos pedidos
	 *
	 * @param array $order_ids IDs dos pedidos.
	 * @param array $filters Filtros.
	 * @param int   $limit Limite de resultados.
	 * @return array
	 */
	public static function generate_preview( $order_ids, $filters, $limit = 50 ) {
		$preview = array(
			'total_orders'    => count( $order_ids ),
			'total_cashback'  => 0,
			'total_amount'    => 0,
			'customers_count' => 0,
			'orders'          => array(),
		);

		$customers    = array();
		$orders_slice = array_slice( $order_ids, 0, $limit );

		foreach ( $orders_slice as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				continue;
			}

			$cashback    = self::calculate_cashback( $order_id, $filters );
			$customer_id = $order->get_customer_id();

			if ( $customer_id ) {
				$customers[ $customer_id ] = true;
			}

			$preview['total_cashback'] += $cashback;
			$preview['total_amount']   += floatval( $order->get_total() );

			$preview['orders'][] = array(
				'id'          => $order_id,
				'number'      => $order->get_order_number(),
				'customer'    => $order->get_formatted_billing_full_name(),
				'date'        => $order->get_date_created()->format( 'd/m/Y' ),
				'total'       => floatval( $order->get_total() ),
				'cashback'    => $cashback,
				'has_cashback' => self::order_has_cashback( $order_id ),
			);
		}

		// Contar cashback total para TODOS os pedidos, não apenas o preview.
		if ( count( $order_ids ) > $limit ) {
			$remaining_ids = array_slice( $order_ids, $limit );
			foreach ( $remaining_ids as $order_id ) {
				$cashback = self::calculate_cashback( $order_id, $filters );
				$preview['total_cashback'] += $cashback;

				$order = wc_get_order( $order_id );
				if ( $order ) {
					$preview['total_amount'] += floatval( $order->get_total() );
					$customer_id = $order->get_customer_id();
					if ( $customer_id ) {
						$customers[ $customer_id ] = true;
					}
				}
			}
		}

		$preview['customers_count'] = count( $customers );

		return $preview;
	}

	/**
	 * Verificar se pedido já tem cashback
	 *
	 * @param int $order_id ID do pedido.
	 * @return bool
	 */
	private static function order_has_cashback( $order_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_cashback';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE order_id = %d",
				$order_id
			)
		);

		return $count > 0;
	}
}
