<?php
/**
 * Executor de Workflows
 *
 * @package GrowlyDigital
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe executora de workflows
 */
class PCW_Workflow_Executor {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Workflow_Executor
	 */
	private static $instance = null;

	/**
	 * Obter instância
	 *
	 * @return PCW_Workflow_Executor
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
		// Privado para singleton
	}

	/**
	 * Inicializar hooks
	 */
	public function init() {
		// Gatilhos de Pedido
		add_action( 'woocommerce_order_status_changed', array( $this, 'trigger_order_status_changed' ), 10, 4 );
		add_action( 'woocommerce_new_order', array( $this, 'trigger_order_created' ), 10, 2 );

		// Gatilhos de Cashback
		add_action( 'pcw_cashback_earned', array( $this, 'trigger_cashback_earned' ), 10, 3 );
		add_action( 'pcw_cashback_expired', array( $this, 'trigger_cashback_expired' ), 10, 2 );
		add_action( 'pcw_cashback_used', array( $this, 'trigger_cashback_used' ), 10, 3 );

		// Gatilhos de Nível
		add_action( 'pcw_level_achieved', array( $this, 'trigger_level_achieved' ), 10, 3 );
		add_action( 'pcw_level_expiring', array( $this, 'trigger_level_expiring' ), 10, 3 );

		// Gatilhos de Wallet
		add_action( 'pcw_wallet_credit', array( $this, 'trigger_wallet_credit' ), 10, 4 );
		add_action( 'pcw_wallet_debit', array( $this, 'trigger_wallet_debit' ), 10, 4 );

		// Gatilhos de Cliente
		add_action( 'user_register', array( $this, 'trigger_customer_registered' ), 10, 1 );
	}

	/**
	 * Processar gatilho
	 *
	 * @param string $trigger_type Tipo de gatilho.
	 * @param array  $context Contexto de dados.
	 * @param array  $trigger_config Configuração extra do gatilho (para filtrar).
	 */
	public function process_trigger( $trigger_type, $context, $trigger_config = array() ) {
		$start_time = microtime( true );

		// Buscar workflows ativos para este gatilho
		$manager = PCW_Workflow_Manager::instance();
		$workflows = $manager->get_by_trigger( $trigger_type );

		if ( empty( $workflows ) ) {
			return;
		}

		foreach ( $workflows as $workflow ) {
			// Verificar configuração do gatilho (ex: status específico)
			if ( ! $this->matches_trigger_config( $workflow->trigger_config, $trigger_config ) ) {
				continue;
			}

			// Avaliar condições
			$conditions_result = PCW_Workflow_Conditions::evaluate( $workflow->conditions, $context );

			if ( ! $conditions_result ) {
				// Log: condições não atendidas
				$manager->log_execution( array(
					'workflow_id'       => $workflow->id,
					'trigger_type'      => $trigger_type,
					'trigger_data'      => $context,
					'conditions_result' => false,
					'actions_executed'  => array(),
					'status'            => 'skipped',
					'execution_time'    => microtime( true ) - $start_time,
				) );
				continue;
			}

			// Executar ações
			$actions_results = array();
			$overall_success = true;
			$error_message = '';

			if ( ! empty( $workflow->actions ) ) {
				foreach ( $workflow->actions as $action ) {
					if ( ! isset( $action['type'] ) ) {
						continue;
					}

					$action_config = isset( $action['config'] ) ? $action['config'] : array();
					$result = PCW_Workflow_Actions::execute( $action['type'], $action_config, $context );

					$actions_results[] = array(
						'type'   => $action['type'],
						'result' => $result,
					);

					if ( ! $result['success'] ) {
						$overall_success = false;
						$error_message = isset( $result['error'] ) ? $result['error'] : __( 'Erro desconhecido', 'person-cash-wallet' );
					}
				}
			}

			// Incrementar contador de execução
			$manager->increment_execution( $workflow->id );

			// Log de execução
			$manager->log_execution( array(
				'workflow_id'       => $workflow->id,
				'trigger_type'      => $trigger_type,
				'trigger_data'      => $context,
				'conditions_result' => true,
				'actions_executed'  => $actions_results,
				'status'            => $overall_success ? 'success' : 'failed',
				'error_message'     => $error_message,
				'execution_time'    => microtime( true ) - $start_time,
			) );

			// Hook para extensões
			do_action( 'pcw_workflow_executed', $workflow, $context, $actions_results );
		}
	}

	/**
	 * Verificar se a configuração do gatilho corresponde
	 *
	 * @param array $workflow_config Configuração do workflow.
	 * @param array $trigger_config Configuração atual.
	 * @return bool
	 */
	private function matches_trigger_config( $workflow_config, $trigger_config ) {
		if ( empty( $workflow_config ) ) {
			return true; // Sem configuração específica = aceita tudo
		}

		foreach ( $workflow_config as $key => $value ) {
			// Ignorar valores vazios ou "any"
			if ( empty( $value ) || 'any' === $value ) {
				continue;
			}

			if ( ! isset( $trigger_config[ $key ] ) ) {
				return false;
			}

			if ( $trigger_config[ $key ] !== $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Construir contexto de pedido
	 *
	 * @param WC_Order $order Pedido.
	 * @return array
	 */
	private function build_order_context( $order ) {
		if ( ! $order ) {
			return array();
		}

		$customer_id = $order->get_customer_id();
		$customer = $customer_id ? get_userdata( $customer_id ) : null;

		$billing_phone = $order->get_billing_phone();
		// Formatar telefone (remover caracteres não numéricos)
		$phone_clean = preg_replace( '/[^0-9]/', '', $billing_phone );

		// Lista de produtos
		$products_list = array();
		$product_ids = array();
		$category_ids = array();

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product ) {
				$products_list[] = $product->get_name() . ' x' . $item->get_quantity();
				$product_ids[] = $product->get_id();

				$terms = get_the_terms( $product->get_id(), 'product_cat' );
				if ( $terms && ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$category_ids[] = $term->term_id;
					}
				}
			}
		}

		$context = array(
			'customer_id'      => $customer_id,
			'customer_name'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'customer_email'   => $order->get_billing_email(),
			'customer_phone'   => $phone_clean,
			'order_id'         => $order->get_id(),
			'order_number'     => $order->get_order_number(),
			'order_total'      => $order->get_total(),
			'order_status'     => $order->get_status(),
			'order_items_count' => $order->get_item_count(),
			'payment_method'   => $order->get_payment_method(),
			'billing_address'  => $order->get_formatted_billing_address(),
			'shipping_address' => $order->get_formatted_shipping_address(),
			'products_list'    => implode( ', ', $products_list ),
			'product_ids'      => array_unique( $product_ids ),
			'category_ids'     => array_unique( $category_ids ),
			'order_date'       => $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '',
		);

		// Nível do cliente
		if ( $customer_id && class_exists( 'PCW_Levels' ) ) {
			$user_level = PCW_Levels::get_user_level( $customer_id );
			$context['customer_level'] = $user_level ? $user_level->level_id : 0;
		}

		return $context;
	}

	/**
	 * Construir contexto de usuário
	 *
	 * @param int $user_id ID do usuário.
	 * @return array
	 */
	private function build_user_context( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return array();
		}

		$phone = get_user_meta( $user_id, 'billing_phone', true );
		$phone_clean = preg_replace( '/[^0-9]/', '', $phone );

		$context = array(
			'customer_id'     => $user_id,
			'customer_name'   => $user->display_name,
			'customer_email'  => $user->user_email,
			'customer_phone'  => $phone_clean,
			'register_date'   => date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ),
		);

		// Saldo da wallet
		if ( class_exists( 'PCW_Wallet' ) ) {
			$wallet = new PCW_Wallet( $user_id );
			$context['wallet_balance'] = $wallet->get_balance();
		}

		// Nível do cliente
		if ( class_exists( 'PCW_Levels' ) ) {
			$user_level = PCW_Levels::get_user_level( $user_id );
			$context['customer_level'] = $user_level ? $user_level->level_id : 0;
			$context['level_name'] = $user_level ? $user_level->name : '';
		}

		return $context;
	}

	// ========== GATILHOS ==========

	/**
	 * Gatilho: Status do pedido alterado
	 *
	 * @param int      $order_id ID do pedido.
	 * @param string   $old_status Status antigo.
	 * @param string   $new_status Novo status.
	 * @param WC_Order $order Pedido.
	 */
	public function trigger_order_status_changed( $order_id, $old_status, $new_status, $order ) {
		$context = $this->build_order_context( $order );
		$context['order_status_old'] = $old_status;

		$trigger_config = array(
			'status_from' => $old_status,
			'status_to'   => $new_status,
		);

		$this->process_trigger( 'order_status_changed', $context, $trigger_config );
	}

	/**
	 * Gatilho: Novo pedido criado
	 *
	 * @param int      $order_id ID do pedido.
	 * @param WC_Order $order Pedido.
	 */
	public function trigger_order_created( $order_id, $order = null ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		$context = $this->build_order_context( $order );
		$this->process_trigger( 'order_created', $context );
	}

	/**
	 * Gatilho: Cashback ganho
	 *
	 * @param object $cashback Dados do cashback.
	 * @param object $user Usuário.
	 * @param object $order Pedido (opcional).
	 */
	public function trigger_cashback_earned( $cashback, $user, $order = null ) {
		$context = array(
			'customer_id'     => $user->ID,
			'customer_name'   => $user->display_name,
			'customer_email'  => $user->user_email,
			'customer_phone'  => preg_replace( '/[^0-9]/', '', get_user_meta( $user->ID, 'billing_phone', true ) ),
			'cashback_amount' => isset( $cashback->amount ) ? PCW_Formatters::format_money_plain( $cashback->amount ) : '',
			'order_id'        => isset( $cashback->order_id ) ? $cashback->order_id : '',
			'order_total'     => $order ? $order->get_total() : '',
			'expiration_date' => isset( $cashback->expires_date ) ? date_i18n( get_option( 'date_format' ), strtotime( $cashback->expires_date ) ) : '',
		);

		if ( class_exists( 'PCW_Wallet' ) ) {
			$context['wallet_balance'] = PCW_Formatters::format_money_plain( PCW_Wallet::get_balance( $user->ID ) );
		}

		$this->process_trigger( 'cashback_earned', $context );
	}

	/**
	 * Gatilho: Cashback expirado
	 *
	 * @param object $cashback Dados do cashback.
	 * @param object $user Usuário.
	 */
	public function trigger_cashback_expired( $cashback, $user ) {
		$context = array(
			'customer_id'     => $user->ID,
			'customer_name'   => $user->display_name,
			'customer_email'  => $user->user_email,
			'customer_phone'  => preg_replace( '/[^0-9]/', '', get_user_meta( $user->ID, 'billing_phone', true ) ),
			'cashback_amount' => isset( $cashback->amount ) ? PCW_Formatters::format_money_plain( $cashback->amount ) : '',
		);

		$this->process_trigger( 'cashback_expired', $context );
	}

	/**
	 * Gatilho: Cashback usado
	 *
	 * @param object $cashback Dados do cashback.
	 * @param object $user Usuário.
	 * @param int    $order_id ID do pedido.
	 */
	public function trigger_cashback_used( $cashback, $user, $order_id ) {
		$context = array(
			'customer_id'     => $user->ID,
			'customer_name'   => $user->display_name,
			'customer_email'  => $user->user_email,
			'customer_phone'  => preg_replace( '/[^0-9]/', '', get_user_meta( $user->ID, 'billing_phone', true ) ),
			'cashback_amount' => isset( $cashback->amount ) ? PCW_Formatters::format_money_plain( $cashback->amount ) : '',
			'order_id'        => $order_id,
		);

		if ( class_exists( 'PCW_Wallet' ) ) {
			$context['wallet_balance'] = PCW_Formatters::format_money_plain( PCW_Wallet::get_balance( $user->ID ) );
		}

		$this->process_trigger( 'cashback_used', $context );
	}

	/**
	 * Gatilho: Nível alcançado
	 *
	 * @param int    $user_id ID do usuário.
	 * @param object $new_level Novo nível.
	 * @param object $old_level Nível anterior (opcional).
	 */
	public function trigger_level_achieved( $user_id, $new_level, $old_level = null ) {
		$context = $this->build_user_context( $user_id );
		$context['level_name'] = isset( $new_level->name ) ? $new_level->name : '';
		$context['level_number'] = isset( $new_level->level_number ) ? $new_level->level_number : '';
		$context['old_level_name'] = $old_level && isset( $old_level->name ) ? $old_level->name : '';

		$this->process_trigger( 'level_achieved', $context );
	}

	/**
	 * Gatilho: Nível expirando
	 *
	 * @param int    $user_id ID do usuário.
	 * @param object $level Nível.
	 * @param int    $days_remaining Dias restantes.
	 */
	public function trigger_level_expiring( $user_id, $level, $days_remaining ) {
		$context = $this->build_user_context( $user_id );
		$context['level_name'] = isset( $level->name ) ? $level->name : '';
		$context['days_remaining'] = $days_remaining;
		$context['expiration_date'] = date_i18n( get_option( 'date_format' ), strtotime( "+{$days_remaining} days" ) );

		$this->process_trigger( 'level_expiring', $context );
	}

	/**
	 * Gatilho: Crédito na wallet
	 *
	 * @param int    $user_id ID do usuário.
	 * @param float  $amount Valor.
	 * @param string $source Origem.
	 * @param float  $new_balance Novo saldo.
	 */
	public function trigger_wallet_credit( $user_id, $amount, $source, $new_balance ) {
		$context = $this->build_user_context( $user_id );
		$context['credit_amount'] = PCW_Formatters::format_money_plain( $amount );
		$context['credit_source'] = $source;
		$context['wallet_balance'] = PCW_Formatters::format_money_plain( $new_balance );

		$this->process_trigger( 'wallet_credit', $context );
	}

	/**
	 * Gatilho: Débito na wallet
	 *
	 * @param int    $user_id ID do usuário.
	 * @param float  $amount Valor.
	 * @param int    $order_id ID do pedido.
	 * @param float  $new_balance Novo saldo.
	 */
	public function trigger_wallet_debit( $user_id, $amount, $order_id, $new_balance ) {
		$context = $this->build_user_context( $user_id );
		$context['debit_amount'] = PCW_Formatters::format_money_plain( $amount );
		$context['order_id'] = $order_id;
		$context['wallet_balance'] = PCW_Formatters::format_money_plain( $new_balance );

		$this->process_trigger( 'wallet_debit', $context );
	}

	/**
	 * Gatilho: Cliente registrado
	 *
	 * @param int $user_id ID do usuário.
	 */
	public function trigger_customer_registered( $user_id ) {
		$context = $this->build_user_context( $user_id );
		$this->process_trigger( 'customer_registered', $context );
	}
}
