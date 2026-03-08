<?php
/**
 * Classe de variáveis para webhooks
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de variáveis de webhooks
 */
class PCW_Webhook_Variables {

	/**
	 * Obter todas as variáveis disponíveis organizadas por categoria
	 *
	 * @return array
	 */
	public static function get_available_variables() {
		return array(
			'user' => array(
				'label' => __( 'Dados do Usuário', 'person-cash-wallet' ),
				'icon'  => 'admin-users',
				'variables' => array(
					'user_id'           => __( 'ID do usuário', 'person-cash-wallet' ),
					'user_name'         => __( 'Nome completo do usuário', 'person-cash-wallet' ),
					'user_first_name'   => __( 'Primeiro nome', 'person-cash-wallet' ),
					'user_last_name'    => __( 'Sobrenome', 'person-cash-wallet' ),
					'user_email'        => __( 'E-mail', 'person-cash-wallet' ),
					'user_phone'        => __( 'Telefone', 'person-cash-wallet' ),
					'user_cpf'          => __( 'CPF', 'person-cash-wallet' ),
					'user_role'         => __( 'Função do usuário', 'person-cash-wallet' ),
					'user_display_name' => __( 'Nome de exibição', 'person-cash-wallet' ),
				),
			),
			'wallet' => array(
				'label' => __( 'Wallet/Carteira', 'person-cash-wallet' ),
				'icon'  => 'money-alt',
				'variables' => array(
					'wallet_balance'      => __( 'Saldo atual da wallet', 'person-cash-wallet' ),
					'wallet_total_earned' => __( 'Total ganho', 'person-cash-wallet' ),
					'wallet_total_spent'  => __( 'Total gasto', 'person-cash-wallet' ),
					'transaction_id'      => __( 'ID da transação', 'person-cash-wallet' ),
					'transaction_type'    => __( 'Tipo da transação (credit/debit)', 'person-cash-wallet' ),
					'transaction_amount'  => __( 'Valor da transação', 'person-cash-wallet' ),
					'transaction_source'  => __( 'Origem da transação', 'person-cash-wallet' ),
					'transaction_description' => __( 'Descrição da transação', 'person-cash-wallet' ),
				),
			),
			'cashback' => array(
				'label' => __( 'Cashback', 'person-cash-wallet' ),
				'icon'  => 'chart-line',
				'variables' => array(
					'cashback_id'           => __( 'ID do cashback', 'person-cash-wallet' ),
					'cashback_amount'       => __( 'Valor do cashback', 'person-cash-wallet' ),
					'cashback_status'       => __( 'Status do cashback', 'person-cash-wallet' ),
					'cashback_earned_date'  => __( 'Data que ganhou', 'person-cash-wallet' ),
					'cashback_expires_date' => __( 'Data de expiração', 'person-cash-wallet' ),
					'cashback_used_date'    => __( 'Data de uso', 'person-cash-wallet' ),
					'cashback_rule_name'    => __( 'Nome da regra aplicada', 'person-cash-wallet' ),
				),
			),
			'level' => array(
				'label' => __( 'Níveis', 'person-cash-wallet' ),
				'icon'  => 'star-filled',
				'variables' => array(
					'level_id'          => __( 'ID do nível', 'person-cash-wallet' ),
					'level_name'        => __( 'Nome do nível', 'person-cash-wallet' ),
					'level_number'      => __( 'Número do nível', 'person-cash-wallet' ),
					'level_description' => __( 'Descrição do nível', 'person-cash-wallet' ),
					'level_achieved_date' => __( 'Data de conquista', 'person-cash-wallet' ),
					'level_expires_date' => __( 'Data de expiração', 'person-cash-wallet' ),
					'previous_level_name' => __( 'Nome do nível anterior', 'person-cash-wallet' ),
				),
			),
			'order' => array(
				'label' => __( 'Pedido', 'person-cash-wallet' ),
				'icon'  => 'cart',
				'variables' => array(
					'order_id'           => __( 'ID do pedido', 'person-cash-wallet' ),
					'order_number'       => __( 'Número do pedido', 'person-cash-wallet' ),
					'order_total'        => __( 'Valor total', 'person-cash-wallet' ),
					'order_status'       => __( 'Status do pedido', 'person-cash-wallet' ),
					'order_date'         => __( 'Data do pedido', 'person-cash-wallet' ),
					'order_payment_method' => __( 'Método de pagamento', 'person-cash-wallet' ),
					'order_items_count'  => __( 'Quantidade de itens', 'person-cash-wallet' ),
				),
			),
			'event' => array(
				'label' => __( 'Evento', 'person-cash-wallet' ),
				'icon'  => 'admin-plugins',
				'variables' => array(
					'event_type'      => __( 'Tipo do evento', 'person-cash-wallet' ),
					'event_timestamp' => __( 'Timestamp do evento', 'person-cash-wallet' ),
					'event_date'      => __( 'Data do evento', 'person-cash-wallet' ),
					'event_time'      => __( 'Hora do evento', 'person-cash-wallet' ),
				),
			),
			'site' => array(
				'label' => __( 'Site', 'person-cash-wallet' ),
				'icon'  => 'admin-site',
				'variables' => array(
					'site_name'  => __( 'Nome do site', 'person-cash-wallet' ),
					'site_url'   => __( 'URL do site', 'person-cash-wallet' ),
					'admin_email' => __( 'E-mail do admin', 'person-cash-wallet' ),
				),
			),
		);
	}

	/**
	 * Obter payload padrão
	 *
	 * @return string
	 */
	public static function get_default_payload() {
		return json_encode(
			array(
				'event'     => '{{event_type}}',
				'timestamp' => '{{event_timestamp}}',
				'user'      => array(
					'id'    => '{{user_id}}',
					'name'  => '{{user_name}}',
					'email' => '{{user_email}}',
					'phone' => '{{user_phone}}',
				),
				'data'      => array(
					// Os dados específicos do evento serão incluídos aqui
				),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * Substituir variáveis no payload
	 *
	 * @param string $payload Payload com variáveis.
	 * @param string $event_type Tipo do evento.
	 * @param array  $data Dados do evento.
	 * @return string
	 */
	public static function replace_variables( $payload, $event_type, $data ) {
		// Obter valores das variáveis
		$variables = self::get_variable_values( $event_type, $data );

		// Substituir variáveis no formato {{variable}}
		foreach ( $variables as $key => $value ) {
			// Converter valores para string
			if ( is_array( $value ) || is_object( $value ) ) {
				$value = wp_json_encode( $value );
			} elseif ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			} elseif ( is_null( $value ) ) {
				$value = 'null';
			} else {
				$value = (string) $value;
			}

			$payload = str_replace( '{{' . $key . '}}', $value, $payload );
		}

		return $payload;
	}

	/**
	 * Obter valores das variáveis
	 *
	 * @param string $event_type Tipo do evento.
	 * @param array  $data Dados do evento.
	 * @return array
	 */
	private static function get_variable_values( $event_type, $data ) {
		$values = array();

		// Dados do evento
		$values['event_type']      = $event_type;
		$values['event_timestamp'] = current_time( 'timestamp' );
		$values['event_date']      = current_time( 'Y-m-d' );
		$values['event_time']      = current_time( 'H:i:s' );

		// Dados do site
		$values['site_name']  = get_bloginfo( 'name' );
		$values['site_url']   = get_site_url();
		$values['admin_email'] = get_option( 'admin_email' );

		// Dados do usuário
		$user_id = isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0;
		if ( $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$values['user_id']           = $user->ID;
				$values['user_name']         = $user->first_name . ' ' . $user->last_name;
				$values['user_first_name']   = $user->first_name;
				$values['user_last_name']    = $user->last_name;
				$values['user_email']        = $user->user_email;
				$values['user_display_name'] = $user->display_name;
				$values['user_role']         = implode( ', ', $user->roles );

				// Telefone e CPF (meta fields do WooCommerce)
				$values['user_phone'] = get_user_meta( $user_id, 'billing_phone', true );
				$values['user_cpf']   = get_user_meta( $user_id, 'billing_cpf', true );
			}
		}

		// Dados da wallet
		if ( isset( $data['wallet_id'] ) || $user_id ) {
			$wallet = PCW_Wallet::get_by_user( $user_id );
			if ( $wallet ) {
				$values['wallet_balance']      = wc_format_decimal( $wallet->balance, 2 );
				$values['wallet_total_earned'] = wc_format_decimal( $wallet->total_earned, 2 );
				$values['wallet_total_spent']  = wc_format_decimal( $wallet->total_spent, 2 );
			}
		}

		// Dados da transação
		if ( isset( $data['transaction_id'] ) ) {
			$transaction = PCW_Wallet::get_transaction( $data['transaction_id'] );
			if ( $transaction ) {
				$values['transaction_id']          = $transaction->id;
				$values['transaction_type']        = $transaction->type;
				$values['transaction_amount']      = wc_format_decimal( $transaction->amount, 2 );
				$values['transaction_source']      = $transaction->source;
				$values['transaction_description'] = $transaction->description;
			}
		}

		// Dados do cashback
		if ( isset( $data['cashback_id'] ) ) {
			global $wpdb;
			$cashback = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}pcw_cashback WHERE id = %d",
					absint( $data['cashback_id'] )
				)
			);
			if ( $cashback ) {
				$values['cashback_id']           = $cashback->id;
				$values['cashback_amount']       = wc_format_decimal( $cashback->amount, 2 );
				$values['cashback_status']       = $cashback->status;
				$values['cashback_earned_date']  = $cashback->earned_date;
				$values['cashback_expires_date'] = $cashback->expires_date;
				$values['cashback_used_date']    = $cashback->used_date;

				// Nome da regra
				if ( $cashback->rule_id ) {
					$rule = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT name FROM {$wpdb->prefix}pcw_cashback_rules WHERE id = %d",
							absint( $cashback->rule_id )
						)
					);
					$values['cashback_rule_name'] = $rule ? $rule->name : '';
				}
			}
		}

		// Dados do nível
		if ( isset( $data['level_id'] ) ) {
			global $wpdb;
			$level = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}pcw_levels WHERE id = %d",
					absint( $data['level_id'] )
				)
			);
			if ( $level ) {
				$values['level_id']          = $level->id;
				$values['level_name']        = $level->name;
				$values['level_number']      = $level->level_number;
				$values['level_description'] = $level->description;
			}

			// Dados do nível do usuário
			if ( $user_id ) {
				$user_level = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}pcw_user_levels WHERE user_id = %d AND level_id = %d ORDER BY id DESC LIMIT 1",
						$user_id,
						absint( $data['level_id'] )
					)
				);
				if ( $user_level ) {
					$values['level_achieved_date'] = $user_level->achieved_date;
					$values['level_expires_date']  = $user_level->expires_date;
				}
			}

			// Nível anterior
			if ( isset( $data['previous_level_id'] ) ) {
				$previous_level = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT name FROM {$wpdb->prefix}pcw_levels WHERE id = %d",
						absint( $data['previous_level_id'] )
					)
				);
				$values['previous_level_name'] = $previous_level ? $previous_level->name : '';
			}
		}

		// Dados do pedido
		if ( isset( $data['order_id'] ) ) {
			$order = wc_get_order( $data['order_id'] );
			if ( $order ) {
				$values['order_id']             = $order->get_id();
				$values['order_number']         = $order->get_order_number();
				$values['order_total']          = $order->get_total();
				$values['order_status']         = $order->get_status();
				$values['order_date']           = $order->get_date_created()->format( 'Y-m-d H:i:s' );
				$values['order_payment_method'] = $order->get_payment_method_title();
				$values['order_items_count']    = $order->get_item_count();
			}
		}

		// Incluir todos os dados originais (prefixados com data_)
		foreach ( $data as $key => $value ) {
			if ( ! isset( $values[ $key ] ) ) {
				$values[ 'data_' . $key ] = $value;
			}
		}

		return $values;
	}

	/**
	 * Validar JSON do payload
	 *
	 * @param string $payload Payload JSON.
	 * @return array Array com 'valid' (bool) e 'error' (string|null)
	 */
	public static function validate_json( $payload ) {
		if ( empty( $payload ) ) {
			return array(
				'valid' => false,
				'error' => __( 'Payload não pode estar vazio', 'person-cash-wallet' ),
			);
		}

		json_decode( $payload );
		$error = json_last_error();

		if ( JSON_ERROR_NONE !== $error ) {
			$error_messages = array(
				JSON_ERROR_DEPTH            => __( 'Profundidade máxima excedida', 'person-cash-wallet' ),
				JSON_ERROR_STATE_MISMATCH   => __( 'JSON inválido ou malformado', 'person-cash-wallet' ),
				JSON_ERROR_CTRL_CHAR        => __( 'Erro de caractere de controle', 'person-cash-wallet' ),
				JSON_ERROR_SYNTAX           => __( 'Erro de sintaxe', 'person-cash-wallet' ),
				JSON_ERROR_UTF8             => __( 'Caracteres UTF-8 malformados', 'person-cash-wallet' ),
			);

			return array(
				'valid' => false,
				'error' => isset( $error_messages[ $error ] ) ? $error_messages[ $error ] : __( 'Erro desconhecido no JSON', 'person-cash-wallet' ),
			);
		}

		return array(
			'valid' => true,
			'error' => null,
		);
	}

	/**
	 * Formatar JSON para exibição
	 *
	 * @param string $json JSON string.
	 * @return string
	 */
	public static function format_json( $json ) {
		$decoded = json_decode( $json );
		if ( null === $decoded ) {
			return $json;
		}

		return wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
}
