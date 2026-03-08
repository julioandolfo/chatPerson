<?php
/**
 * Ações de Workflow
 *
 * @package GrowlyDigital
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de ações de workflow
 */
class PCW_Workflow_Actions {

	/**
	 * Tipos de ações disponíveis
	 *
	 * @var array
	 */
	private static $action_types = array();

	/**
	 * Inicializar
	 */
	public static function init() {
		self::register_default_actions();
	}

	/**
	 * Registrar ações padrão
	 */
	private static function register_default_actions() {
		// Ação de Webhook
		self::register( 'webhook', array(
			'name'        => __( 'Enviar Webhook', 'person-cash-wallet' ),
			'description' => __( 'Envia uma requisição HTTP para uma URL externa', 'person-cash-wallet' ),
			'icon'        => 'admin-links',
			'fields'      => array(
				'url' => array(
					'type'        => 'text',
					'label'       => __( 'URL', 'person-cash-wallet' ),
					'placeholder' => 'https://api.exemplo.com/webhook',
					'required'    => true,
				),
				'method' => array(
					'type'    => 'select',
					'label'   => __( 'Método HTTP', 'person-cash-wallet' ),
					'options' => array(
						'POST'   => 'POST',
						'GET'    => 'GET',
						'PUT'    => 'PUT',
						'PATCH'  => 'PATCH',
						'DELETE' => 'DELETE',
					),
					'default' => 'POST',
				),
				'headers' => array(
					'type'        => 'key_value',
					'label'       => __( 'Headers', 'person-cash-wallet' ),
					'description' => __( 'Headers HTTP adicionais', 'person-cash-wallet' ),
				),
				'body_type' => array(
					'type'    => 'select',
					'label'   => __( 'Tipo do Body', 'person-cash-wallet' ),
					'options' => array(
						'json'      => 'JSON',
						'form_data' => 'Form Data',
					),
					'default' => 'json',
				),
				'body' => array(
					'type'        => 'payload_builder',
					'label'       => __( 'Payload', 'person-cash-wallet' ),
					'description' => __( 'Configure os campos do payload', 'person-cash-wallet' ),
				),
			),
			'handler' => array( __CLASS__, 'execute_webhook' ),
		) );

		// Ação de Email
		self::register( 'send_email', array(
			'name'        => __( 'Enviar Email', 'person-cash-wallet' ),
			'description' => __( 'Envia um email para o cliente ou endereço específico', 'person-cash-wallet' ),
			'icon'        => 'email',
			'fields'      => array(
				'to' => array(
					'type'        => 'text',
					'label'       => __( 'Destinatário', 'person-cash-wallet' ),
					'placeholder' => '{customer_email}',
					'description' => __( 'Use {customer_email} para email do cliente', 'person-cash-wallet' ),
					'required'    => true,
					'default'     => '{customer_email}',
				),
				'subject' => array(
					'type'        => 'text',
					'label'       => __( 'Assunto', 'person-cash-wallet' ),
					'placeholder' => __( 'Novo pedido #{order_id}', 'person-cash-wallet' ),
					'required'    => true,
				),
				'preview_text' => array(
					'type'        => 'text',
					'label'       => __( 'Texto de Preview', 'person-cash-wallet' ),
					'placeholder' => __( 'Texto exibido após o assunto na caixa de entrada', 'person-cash-wallet' ),
					'required'    => false,
				),
				'body' => array(
					'type'        => 'email_editor',
					'label'       => __( 'Conteúdo do Email', 'person-cash-wallet' ),
					'description' => __( 'Use o editor visual para criar emails personalizados', 'person-cash-wallet' ),
					'required'    => true,
				),
			),
			'handler' => array( __CLASS__, 'execute_send_email' ),
		) );

		// Ação de Adicionar Nota no Pedido
		self::register( 'add_order_note', array(
			'name'        => __( 'Adicionar Nota no Pedido', 'person-cash-wallet' ),
			'description' => __( 'Adiciona uma nota interna ou para o cliente no pedido', 'person-cash-wallet' ),
			'icon'        => 'edit',
			'fields'      => array(
				'note' => array(
					'type'        => 'textarea',
					'label'       => __( 'Nota', 'person-cash-wallet' ),
					'placeholder' => __( 'Workflow executado em {date}', 'person-cash-wallet' ),
					'required'    => true,
				),
				'is_customer_note' => array(
					'type'    => 'checkbox',
					'label'   => __( 'Visível para o cliente', 'person-cash-wallet' ),
					'default' => false,
				),
			),
			'handler' => array( __CLASS__, 'execute_add_order_note' ),
		) );

		// Ação de Delay (atraso)
		self::register( 'delay', array(
			'name'        => __( 'Aguardar', 'person-cash-wallet' ),
			'description' => __( 'Aguarda um período antes de executar as próximas ações', 'person-cash-wallet' ),
			'icon'        => 'clock',
			'fields'      => array(
				'delay_value' => array(
					'type'     => 'number',
					'label'    => __( 'Tempo', 'person-cash-wallet' ),
					'default'  => 1,
					'required' => true,
				),
				'delay_unit' => array(
					'type'    => 'select',
					'label'   => __( 'Unidade', 'person-cash-wallet' ),
					'options' => array(
						'minutes' => __( 'Minutos', 'person-cash-wallet' ),
						'hours'   => __( 'Horas', 'person-cash-wallet' ),
						'days'    => __( 'Dias', 'person-cash-wallet' ),
					),
					'default' => 'hours',
				),
			),
			'handler' => array( __CLASS__, 'execute_delay' ),
		) );

		// Ação de WhatsApp Personizi
		self::register( 'send_whatsapp', array(
			'name'        => __( 'Enviar WhatsApp (Personizi)', 'person-cash-wallet' ),
			'description' => __( 'Envia uma mensagem WhatsApp via integração Personizi', 'person-cash-wallet' ),
			'icon'        => 'admin-comments',
			'fields'      => array(
				'to' => array(
					'type'        => 'text',
					'label'       => __( 'Destinatário', 'person-cash-wallet' ),
					'placeholder' => '{customer_phone}',
					'description' => __( 'Use {customer_phone} para telefone do cliente', 'person-cash-wallet' ),
					'required'    => true,
					'default'     => '{customer_phone}',
				),
				'from' => array(
					'type'        => 'personizi_accounts',
					'label'       => __( 'Número de Origem', 'person-cash-wallet' ),
					'description' => __( 'Selecione a conta WhatsApp configurada no Personizi', 'person-cash-wallet' ),
					'required'    => false,
				),
				'message' => array(
					'type'        => 'textarea',
					'label'       => __( 'Mensagem', 'person-cash-wallet' ),
					'placeholder' => __( 'Olá {customer_name}, seu cashback de {cashback_amount} expira em {days_remaining} dias!', 'person-cash-wallet' ),
					'description' => __( 'Use variáveis disponíveis no gatilho selecionado', 'person-cash-wallet' ),
					'required'    => false,
					'rows'        => 5,
				),
				'use_template' => array(
					'type'        => 'checkbox',
					'label'       => __( 'Usar Template Aprovado', 'person-cash-wallet' ),
					'description' => __( 'Enviar um template pré-aprovado (necessário para API Oficial)', 'person-cash-wallet' ),
					'required'    => false,
				),
				'template_name' => array(
					'type'        => 'text',
					'label'       => __( 'Nome do Template', 'person-cash-wallet' ),
					'required'    => false,
				),
				'template_language' => array(
					'type'        => 'text',
					'label'       => __( 'Idioma do Template', 'person-cash-wallet' ),
					'default'     => 'pt_BR',
					'required'    => false,
				),
				'template_params' => array(
					'type'        => 'text',
					'label'       => __( 'Parâmetros do Template (JSON)', 'person-cash-wallet' ),
					'description' => __( 'Array JSON de parâmetros. Ex: ["{customer_first_name}", "123"]', 'person-cash-wallet' ),
					'required'    => false,
				),
				'template_body_text' => array(
					'type'        => 'text',
					'label'       => __( 'Corpo do Template', 'person-cash-wallet' ),
					'required'    => false,
				),
			),
			'handler' => array( __CLASS__, 'execute_send_whatsapp' ),
		) );

		// Permitir plugins externos registrar ações
		do_action( 'pcw_register_workflow_actions' );
	}

	/**
	 * Registrar ação
	 *
	 * @param string $id ID da ação.
	 * @param array  $args Argumentos.
	 */
	public static function register( $id, $args ) {
		$defaults = array(
			'name'        => '',
			'description' => '',
			'icon'        => 'admin-generic',
			'fields'      => array(),
			'handler'     => null,
		);

		self::$action_types[ $id ] = wp_parse_args( $args, $defaults );
	}

	/**
	 * Obter todos os tipos de ação
	 *
	 * @return array
	 */
	public static function get_all() {
		if ( empty( self::$action_types ) ) {
			self::init();
		}
		return self::$action_types;
	}

	/**
	 * Obter ação por ID
	 *
	 * @param string $id ID da ação.
	 * @return array|null
	 */
	public static function get( $id ) {
		if ( empty( self::$action_types ) ) {
			self::init();
		}
		return isset( self::$action_types[ $id ] ) ? self::$action_types[ $id ] : null;
	}

	/**
	 * Executar ação
	 *
	 * @param string $action_id ID da ação.
	 * @param array  $config Configuração da ação.
	 * @param array  $context Contexto de dados.
	 * @return array Resultado da execução.
	 */
	public static function execute( $action_id, $config, $context ) {
		$action = self::get( $action_id );

		if ( ! $action ) {
			return array(
				'success' => false,
				'error'   => sprintf( __( 'Ação não encontrada: %s', 'person-cash-wallet' ), $action_id ),
			);
		}

		if ( ! is_callable( $action['handler'] ) ) {
			return array(
				'success' => false,
				'error'   => sprintf( __( 'Handler não configurado para ação: %s', 'person-cash-wallet' ), $action_id ),
			);
		}

		// Processar variáveis no config
		$processed_config = self::process_variables( $config, $context );

		try {
			$result = call_user_func( $action['handler'], $processed_config, $context );
			return $result;
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Processar variáveis nos valores de configuração
	 *
	 * @param array $config Configuração.
	 * @param array $context Contexto.
	 * @return array
	 */
	private static function process_variables( $config, $context ) {
		$processed = array();

		foreach ( $config as $key => $value ) {
			if ( is_array( $value ) ) {
				$processed[ $key ] = self::process_variables( $value, $context );
			} elseif ( is_string( $value ) ) {
				$processed[ $key ] = self::replace_variables( $value, $context );
			} else {
				$processed[ $key ] = $value;
			}
		}

		return $processed;
	}

	/**
	 * Substituir variáveis em uma string
	 *
	 * @param string $string String com variáveis.
	 * @param array  $context Contexto.
	 * @return string
	 */
	public static function replace_variables( $string, $context ) {
		// Padrão: {variable_name}
		$result = preg_replace_callback(
			'/\{([a-zA-Z_]+)\}/',
			function( $matches ) use ( $context ) {
				$var = $matches[1];
				if ( isset( $context[ $var ] ) && $context[ $var ] !== '' ) {
					// Strip HTML tags para garantir texto limpo
					$value = $context[ $var ];
					if ( is_string( $value ) ) {
						$value = wp_strip_all_tags( html_entity_decode( $value ) );
					}
					return $value;
				}
				// Se a variável não existe ou está vazia, remover a referência
				return '';
			},
			$string
		);

		// Limpar espaços duplos e pontuação órfã
		$result = preg_replace( '/\s+/', ' ', $result );
		$result = preg_replace( '/\s+([.,!?])/', '$1', $result );
		$result = trim( $result );

		return $result;
	}

	/**
	 * Handler: Executar Webhook
	 *
	 * @param array $config Configuração.
	 * @param array $context Contexto.
	 * @return array
	 */
	public static function execute_webhook( $config, $context ) {
		$url = isset( $config['url'] ) ? $config['url'] : '';
		$method = isset( $config['method'] ) ? strtoupper( $config['method'] ) : 'POST';
		$headers = isset( $config['headers'] ) ? $config['headers'] : array();
		$body_type = isset( $config['body_type'] ) ? $config['body_type'] : 'json';
		$body = isset( $config['body'] ) ? $config['body'] : array();

		if ( empty( $url ) ) {
			return array(
				'success' => false,
				'error'   => __( 'URL não configurada', 'person-cash-wallet' ),
			);
		}

		// Preparar headers
		$request_headers = array();
		if ( is_array( $headers ) ) {
			foreach ( $headers as $header ) {
				if ( isset( $header['key'] ) && isset( $header['value'] ) && ! empty( $header['key'] ) ) {
					$request_headers[ $header['key'] ] = $header['value'];
				}
			}
		}

		// Content-Type baseado no body_type
		if ( 'json' === $body_type ) {
			$request_headers['Content-Type'] = 'application/json';
		}

		// Preparar body
		$request_body = '';
		if ( 'json' === $body_type ) {
			// Construir objeto JSON a partir do payload builder
			$json_body = array();
			if ( is_array( $body ) ) {
				foreach ( $body as $field ) {
					if ( isset( $field['key'] ) && ! empty( $field['key'] ) ) {
						$value = isset( $field['value'] ) ? $field['value'] : '';
						// Se o valor for uma variável, substituir
						$value = self::replace_variables( $value, $context );
						$json_body[ $field['key'] ] = $value;
					}
				}
			}
			$request_body = wp_json_encode( $json_body );
		} else {
			// Form data
			$form_data = array();
			if ( is_array( $body ) ) {
				foreach ( $body as $field ) {
					if ( isset( $field['key'] ) && ! empty( $field['key'] ) ) {
						$value = isset( $field['value'] ) ? $field['value'] : '';
						$form_data[ $field['key'] ] = self::replace_variables( $value, $context );
					}
				}
			}
			$request_body = $form_data;
		}

		// Fazer requisição
		$args = array(
			'method'  => $method,
			'headers' => $request_headers,
			'timeout' => 30,
		);

		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = $request_body;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		$success = $response_code >= 200 && $response_code < 300;

		return array(
			'success'       => $success,
			'response_code' => $response_code,
			'response_body' => $response_body,
			'error'         => $success ? '' : sprintf( __( 'HTTP %d: %s', 'person-cash-wallet' ), $response_code, $response_body ),
		);
	}

	/**
	 * Handler: Enviar Email
	 *
	 * @param array $config Configuração.
	 * @param array $context Contexto.
	 * @return array
	 */
	public static function execute_send_email( $config, $context ) {
		$to = isset( $config['to'] ) ? $config['to'] : '';
		$subject = isset( $config['subject'] ) ? $config['subject'] : '';
		$body = isset( $config['body'] ) ? $config['body'] : '';

		if ( empty( $to ) || empty( $subject ) || empty( $body ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Campos obrigatórios não preenchidos', 'person-cash-wallet' ),
			);
		}

		// Verificar se deve usar fila (se há contas SMTP com distribuição habilitada)
		$use_queue = self::should_use_email_queue();

		if ( $use_queue ) {
			// Adicionar à fila para processamento com rate limiting
			$queue_manager = PCW_Message_Queue_Manager::instance();
			$queue_id = $queue_manager->add_email_to_queue( array(
				'to_email'      => $to,
				'subject'       => $subject,
				'message'       => $body,
				'automation_id' => isset( $context['workflow_id'] ) ? absint( $context['workflow_id'] ) : null,
				'metadata'      => array(
					'source'  => 'workflow',
					'context' => $context,
				),
			) );

			if ( $queue_id ) {
				return array(
					'success'  => true,
					'queued'   => true,
					'queue_id' => $queue_id,
					'message'  => __( 'Email adicionado à fila para envio', 'person-cash-wallet' ),
				);
			} else {
				return array(
					'success' => false,
					'error'   => __( 'Falha ao adicionar email à fila', 'person-cash-wallet' ),
				);
			}
		}

		// Envio direto (sem fila)
		$result = PCW_Email_Handler::send( $to, $subject, $body );

		return array(
			'success' => $result,
			'error'   => $result ? '' : __( 'Falha ao enviar email', 'person-cash-wallet' ),
		);
	}

	/**
	 * Verificar se deve usar fila para emails
	 *
	 * @return bool
	 */
	private static function should_use_email_queue() {
		global $wpdb;
		$smtp_table = $wpdb->prefix . 'pcw_smtp_accounts';

		// Verificar se a tabela existe
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$smtp_table}'" );
		if ( ! $table_exists ) {
			return false;
		}

		// Verificar se há contas SMTP com distribuição habilitada
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$smtp_table} 
			WHERE status = 'active' AND distribution_enabled = 1"
		);

		return $count > 0;
	}

	/**
	 * Handler: Adicionar Nota no Pedido
	 *
	 * @param array $config Configuração.
	 * @param array $context Contexto.
	 * @return array
	 */
	public static function execute_add_order_note( $config, $context ) {
		if ( ! isset( $context['order_id'] ) || empty( $context['order_id'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'ID do pedido não disponível', 'person-cash-wallet' ),
			);
		}

		$order = wc_get_order( $context['order_id'] );
		if ( ! $order ) {
			return array(
				'success' => false,
				'error'   => __( 'Pedido não encontrado', 'person-cash-wallet' ),
			);
		}

		$note = isset( $config['note'] ) ? $config['note'] : '';
		$is_customer_note = isset( $config['is_customer_note'] ) && $config['is_customer_note'];

		if ( empty( $note ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Nota não pode ser vazia', 'person-cash-wallet' ),
			);
		}

		$order->add_order_note( $note, $is_customer_note );

		return array(
			'success' => true,
		);
	}

	/**
	 * Handler: Delay (não implementado como ação síncrona)
	 *
	 * @param array $config Configuração.
	 * @param array $context Contexto.
	 * @return array
	 */
	public static function execute_delay( $config, $context ) {
		// Delay seria implementado com scheduled actions (Action Scheduler)
		// Por enquanto, apenas retorna sucesso
		return array(
			'success' => true,
			'message' => __( 'Delay agendado (implementação futura)', 'person-cash-wallet' ),
		);
	}

	/**
	 * Handler: Enviar WhatsApp via Personizi
	 *
	 * @param array $config Configuração.
	 * @param array $context Contexto.
	 * @return array
	 */
	public static function execute_send_whatsapp( $config, $context ) {
		$to = isset( $config['to'] ) ? $config['to'] : '';
		$from = isset( $config['from'] ) ? $config['from'] : '';
		$message = isset( $config['message'] ) ? $config['message'] : '';

		// Verificar se o destinatário está vazio ou contém apenas a variável não substituída
		if ( empty( $to ) || $to === '{customer_phone}' ) {
			// Tentar pegar do contexto diretamente
			$to = isset( $context['customer_phone'] ) ? $context['customer_phone'] : '';
		}

		$use_template = ! empty( $config['use_template'] ) && $config['use_template'] == '1';

		if ( empty( $message ) && ! $use_template ) {
			return array(
				'success' => false,
				'error'   => __( 'Mensagem não configurada na ação', 'person-cash-wallet' ),
			);
		}

		// Validar telefone
		if ( empty( $to ) || strlen( preg_replace( '/[^0-9]/', '', $to ) ) < 10 ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: customer name */
					__( 'Cliente "%s" não possui telefone válido cadastrado', 'person-cash-wallet' ),
					isset( $context['customer_name'] ) ? $context['customer_name'] : __( 'Desconhecido', 'person-cash-wallet' )
				),
			);
		}

		// Verificar se Personizi está disponível
		if ( ! class_exists( 'PCW_Personizi_Integration' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Integração Personizi não está disponível', 'person-cash-wallet' ),
			);
		}

		$personizi = PCW_Personizi_Integration::instance();

		// Verificar se está configurado
		if ( empty( $personizi->get_api_token() ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Personizi não está configurado. Configure em Configurações > Personizi WhatsApp', 'person-cash-wallet' ),
			);
		}

		// Preparar nome do contato
		$contact_name = isset( $context['customer_name'] ) ? $context['customer_name'] : 'Cliente';

		// Verificar se a fila está pausada
		if ( get_option( 'pcw_queue_paused', false ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Fila de disparos está pausada', 'person-cash-wallet' ),
			);
		}

		// Sempre usar fila quando disponível para garantir rate limiting
		if ( class_exists( 'PCW_Message_Queue_Manager' ) ) {
			$queue_manager = PCW_Message_Queue_Manager::instance();

			$use_template = ! empty( $config['use_template'] ) && $config['use_template'] == '1';

			if ( $use_template && ! empty( $config['template_name'] ) ) {
				$template_params = array();
				if ( ! empty( $config['template_params'] ) ) {
					$raw_params = is_string( $config['template_params'] )
						? json_decode( $config['template_params'], true )
						: $config['template_params'];
					if ( is_array( $raw_params ) ) {
						foreach ( $raw_params as $param ) {
							$resolved = $param;
							if ( is_array( $context ) ) {
								foreach ( $context as $key => $val ) {
									if ( is_string( $val ) ) {
										$resolved = str_replace( '{{' . $key . '}}', $val, $resolved );
										$resolved = str_replace( '{' . $key . '}', $val, $resolved );
									}
								}
							}
							$template_params[] = $resolved;
						}
					}
				}

				$queue_id = $queue_manager->add_template_to_queue( array(
					'to'                 => $to,
					'from'               => $from,
					'template_name'      => $config['template_name'],
					'template_params'    => $template_params,
					'template_language'  => ! empty( $config['template_language'] ) ? $config['template_language'] : 'pt_BR',
					'template_body_text' => isset( $config['template_body_text'] ) ? $config['template_body_text'] : '',
					'contact_name'       => $contact_name,
					'metadata'           => array(
						'source'      => 'workflow',
						'workflow_id' => isset( $context['workflow_id'] ) ? $context['workflow_id'] : null,
					),
				) );
			} else {
				$queue_id = $queue_manager->add_whatsapp_to_queue( array(
					'to'           => $to,
					'message'      => $message,
					'from'         => '',
					'contact_name' => $contact_name,
					'metadata'     => array(
						'source'      => 'workflow',
						'workflow_id' => isset( $context['workflow_id'] ) ? $context['workflow_id'] : null,
					),
				) );
			}

			if ( $queue_id ) {
				return array(
					'success'  => true,
					'queued'   => true,
					'queue_id' => $queue_id,
					'message'  => __( 'Mensagem WhatsApp adicionada à fila para envio', 'person-cash-wallet' ),
				);
			} else {
				return array(
					'success' => false,
					'error'   => __( 'Falha ao adicionar WhatsApp à fila', 'person-cash-wallet' ),
				);
			}
		}

		// Envio direto apenas se fila não está disponível
		if ( empty( $from ) ) {
			$from = $personizi->get_default_from();
		}

		$result = $personizi->send_whatsapp_message( $to, $message, $contact_name, $from );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'error'   => $result->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Mensagem WhatsApp enviada com sucesso', 'person-cash-wallet' ),
			'result'  => $result,
		);
	}

	/**
	 * Verificar se deve usar fila para WhatsApp
	 *
	 * @return bool
	 */
	private static function should_use_whatsapp_queue() {
		// Verificar se Message Queue Manager está disponível
		if ( ! class_exists( 'PCW_Message_Queue_Manager' ) ) {
			return false;
		}

		// Verificar se há números configurados na tabela
		// Se houver, usar a fila automaticamente para respeitar rate limiting
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_whatsapp_numbers';
		
		// Verificar se a tabela existe
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
		if ( ! $table_exists ) {
			// Fallback: verificar configuração antiga
			$settings = get_option( 'pcw_queue_settings', array() );
			return ! empty( $settings['whatsapp_rate_limiting'] );
		}
		
		// Se há números ativos configurados, usar a fila
		$active_numbers = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE status = 'active'"
		);
		
		if ( absint( $active_numbers ) > 0 ) {
			return true;
		}

		// Fallback: verificar configuração manual
		$settings = get_option( 'pcw_queue_settings', array() );
		return ! empty( $settings['whatsapp_rate_limiting'] );
	}

	/**
	 * Obter contas WhatsApp do Personizi para seleção
	 *
	 * @return array
	 */
	public static function get_personizi_accounts() {
		if ( ! class_exists( 'PCW_Personizi_Integration' ) ) {
			return array();
		}

		$personizi = PCW_Personizi_Integration::instance();
		$accounts = $personizi->get_whatsapp_accounts();

		if ( is_wp_error( $accounts ) || empty( $accounts ) ) {
			return array();
		}

		$options = array(
			'' => __( 'Usar padrão configurado', 'person-cash-wallet' ),
		);

		foreach ( $accounts as $account ) {
			$phone = isset( $account['phone_number'] ) ? $account['phone_number'] : '';
			$name = isset( $account['name'] ) ? $account['name'] : $phone;
			if ( $phone ) {
				$options[ $phone ] = $name . ' (' . $phone . ')';
			}
		}

		return $options;
	}
}
