<?php
/**
 * Agendador de Workflows
 * 
 * Processa workflows agendados (scheduled) via cron
 *
 * @package PersonCashWallet
 * @since 1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de agendamento de workflows
 */
class PCW_Workflow_Scheduler {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Workflow_Scheduler
	 */
	private static $instance = null;

	/**
	 * Logger do WooCommerce
	 *
	 * @var WC_Logger
	 */
	private $logger = null;

	/**
	 * Contexto do log
	 *
	 * @var array
	 */
	private $log_context = array( 'source' => 'pcw-workflow-scheduler' );

	/**
	 * Obter instância
	 *
	 * @return PCW_Workflow_Scheduler
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
		if ( function_exists( 'wc_get_logger' ) ) {
			$this->logger = wc_get_logger();
		}
	}

	/**
	 * Inicializar
	 */
	public function init() {
		// Registrar cron schedule personalizado
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

		// Agendar evento se não existir
		if ( ! wp_next_scheduled( 'pcw_workflow_scheduled_check' ) ) {
			wp_schedule_event( time(), 'hourly', 'pcw_workflow_scheduled_check' );
		}

		// Hook do cron
		add_action( 'pcw_workflow_scheduled_check', array( $this, 'process_scheduled_workflows' ) );

		// Salvar data da mudança de status para cálculo de dias
		add_action( 'woocommerce_order_status_changed', array( $this, 'save_status_change_date' ), 5, 3 );
	}

	/**
	 * Salvar data da mudança de status no pedido
	 *
	 * @param int    $order_id ID do pedido.
	 * @param string $old_status Status antigo.
	 * @param string $new_status Novo status.
	 */
	public function save_status_change_date( $order_id, $old_status, $new_status ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order->update_meta_data( '_pcw_status_changed_date', current_time( 'mysql' ) );
			$order->update_meta_data( '_pcw_status_changed_to', $new_status );
			$order->save();
		}
	}

	/**
	 * Adicionar schedules personalizados
	 *
	 * @param array $schedules Schedules existentes.
	 * @return array
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['every_six_hours'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'A cada 6 horas', 'person-cash-wallet' ),
		);
		return $schedules;
	}

	/**
	 * Log
	 *
	 * @param string $level Nível.
	 * @param string $message Mensagem.
	 */
	private function log( $level, $message ) {
		if ( $this->logger ) {
			$this->logger->log( $level, $message, $this->log_context );
		}
	}

	/**
	 * Processar workflows agendados
	 */
	public function process_scheduled_workflows() {
		$manager = PCW_Workflow_Manager::instance();
		
		// Buscar workflows com gatilho scheduled_order_check
		$workflows = $manager->get_by_trigger( 'scheduled_order_check' );

		if ( empty( $workflows ) ) {
			return;
		}

		$this->log( 'info', sprintf( '=== Processando %d workflows agendados ===', count( $workflows ) ) );

		foreach ( $workflows as $workflow ) {
			$this->process_single_workflow( $workflow );
		}
	}

	/**
	 * Processar um workflow específico
	 *
	 * @param object $workflow Workflow.
	 */
	private function process_single_workflow( $workflow ) {
		$config = $workflow->trigger_config;

		// Configurações do gatilho
		$target_status      = isset( $config['target_status'] ) ? $config['target_status'] : '';
		$min_days           = isset( $config['min_days_in_status'] ) ? absint( $config['min_days_in_status'] ) : 1;
		$max_days           = isset( $config['max_days_in_status'] ) && $config['max_days_in_status'] !== '' ? absint( $config['max_days_in_status'] ) : null;
		$run_once_per_order = isset( $config['run_once_per_order'] ) ? (bool) $config['run_once_per_order'] : true;

		if ( empty( $target_status ) ) {
			$this->log( 'error', "Workflow #{$workflow->id}: Status alvo não configurado" );
			return;
		}

		// Buscar pedidos no status alvo
		$orders = $this->get_orders_in_status( $target_status, $min_days, $max_days );

		if ( empty( $orders ) ) {
			return;
		}

		$processed = 0;
		$skipped   = 0;

		foreach ( $orders as $order ) {
			// Verificar se já foi executado para este pedido
			if ( $run_once_per_order ) {
				$executed_key = '_pcw_workflow_' . $workflow->id . '_executed';
				$already_executed = $order->get_meta( $executed_key );

				if ( $already_executed ) {
					$skipped++;
					continue;
				}
			}

			// Construir contexto
			$context = $this->build_order_context( $order, $min_days );

			// Avaliar condições adicionais
			$conditions_result = PCW_Workflow_Conditions::evaluate( $workflow->conditions, $context );

			if ( ! $conditions_result ) {
				$skipped++;
				continue;
			}

			// Executar ações
			$this->execute_workflow_actions( $workflow, $context, $order );

			// Marcar como executado
			if ( $run_once_per_order ) {
				$order->update_meta_data( $executed_key, current_time( 'mysql' ) );
				$order->save();
			}

			$processed++;
		}
		
		$this->log( 'info', sprintf( 'Workflow #%d finalizado: %d processados, %d ignorados', $workflow->id, $processed, $skipped ) );
	}

	/**
	 * Buscar pedidos em um status específico
	 *
	 * @param string   $status Status.
	 * @param int      $min_days Mínimo de dias.
	 * @param int|null $max_days Máximo de dias.
	 * @return array
	 */
	private function get_orders_in_status( $status, $min_days, $max_days = null ) {
		// Se max_days não foi definido, limitar a 30 dias para evitar processar pedidos muito antigos
		$effective_max_days = $max_days !== null ? $max_days : 30;
		
		// Calcular datas para filtro na query
		$date_after  = date( 'Y-m-d H:i:s', strtotime( "-{$effective_max_days} days" ) );
		$date_before = date( 'Y-m-d H:i:s', strtotime( "-{$min_days} days" ) );
		
		$args = array(
			'status'       => 'wc-' . $status,
			'limit'        => 200,
			'orderby'      => 'date',
			'order'        => 'DESC',
			'date_created' => $date_after . '...' . $date_before,
			'return'       => 'objects',
		);

		$orders = wc_get_orders( $args );
		
		$this->log( 'info', sprintf( 
			'Busca: wc-%s, criados entre %s e %s, encontrados: %d', 
			$status, $date_after, $date_before, count( $orders ) 
		) );

		// Filtrar por data de mudança de status (quando disponível)
		$filtered_orders = array();

		foreach ( $orders as $order ) {
			$days_in_status = $this->get_days_in_current_status( $order );

			if ( $days_in_status >= $min_days && $days_in_status <= $effective_max_days ) {
				$filtered_orders[] = $order;
			}
		}
		
		$this->log( 'info', sprintf( 'Após filtro de dias: %d pedidos elegíveis', count( $filtered_orders ) ) );

		return $filtered_orders;
	}

	/**
	 * Calcular dias que o pedido está no status atual
	 *
	 * @param WC_Order $order Pedido.
	 * @return int
	 */
	private function get_days_in_current_status( $order ) {
		// Tentar obter data da última mudança de status (salva pelo plugin)
		$status_date = $order->get_meta( '_pcw_status_changed_date' );

		if ( ! empty( $status_date ) ) {
			// Verificar se a data do meta corresponde ao status atual
			$saved_status = $order->get_meta( '_pcw_status_changed_to' );
			$current_status = $order->get_status();
			
			if ( $saved_status && $saved_status !== $current_status ) {
				// O meta é de uma mudança de status anterior, não usar
				$status_date = '';
			}
		}

		if ( empty( $status_date ) ) {
			// Fallback: usar data de criação do pedido
			// Para pedidos que nunca tiveram o status alterado com o plugin ativo,
			// a data de criação é a referência mais confiável
			$created = $order->get_date_created();
			$status_date = $created ? $created->format( 'Y-m-d H:i:s' ) : current_time( 'mysql' );
		}

		$now = new DateTime( 'now', wp_timezone() );
		$status_datetime = new DateTime( $status_date, wp_timezone() );
		$diff = $now->diff( $status_datetime );

		return $diff->days;
	}

	/**
	 * Construir contexto do pedido
	 *
	 * @param WC_Order $order Pedido.
	 * @param int      $days_in_status Dias no status.
	 * @return array
	 */
	private function build_order_context( $order, $days_in_status ) {
		$customer_id = $order->get_customer_id();
		$phone = preg_replace( '/[^0-9]/', '', $order->get_billing_phone() );

		// Normalizar telefone
		$phone = ltrim( $phone, '0' );
		if ( strlen( $phone ) >= 10 && substr( $phone, 0, 2 ) !== '55' ) {
			$phone = '55' . $phone;
		}

		// Lista de produtos
		$products_list = array();
		foreach ( $order->get_items() as $item ) {
			$products_list[] = $item->get_name() . ' x' . $item->get_quantity();
		}

		// Link de pagamento
		$payment_link = $order->get_meta( 'manual_order_auto_login_url' );
		if ( empty( $payment_link ) ) {
			$payment_link = $order->get_checkout_payment_url();
		}

		// Calcular dias desde criação
		$created = $order->get_date_created();
		$order_age_days = 0;
		if ( $created ) {
			$now = new DateTime( 'now', wp_timezone() );
			$diff = $now->diff( new DateTime( $created->format( 'Y-m-d H:i:s' ), wp_timezone() ) );
			$order_age_days = $diff->days;
		}

		return array(
			'customer_id'        => $customer_id,
			'customer_name'      => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'customer_email'     => $order->get_billing_email(),
			'customer_phone'     => $phone,
			'order_id'           => $order->get_id(),
			'order_number'       => $order->get_order_number(),
			'order_total'        => 'R$ ' . number_format( (float) $order->get_total(), 2, ',', '.' ),
			'order_status'       => wc_get_order_status_name( $order->get_status() ), // Nome traduzido (ex: "Aguardando Pagamento")
			'order_status_slug'  => $order->get_status(), // Slug técnico (ex: "pending")
			'order_date'         => $created ? $created->date_i18n( get_option( 'date_format' ) ) : '',
			'order_age_days'     => $order_age_days,
			'days_in_status'     => $days_in_status,
			'payment_method'     => $order->get_payment_method_title(),
			'payment_link'       => $payment_link,
			'products_list'      => implode( ', ', $products_list ),
			'billing_address'    => wp_strip_all_tags( $order->get_formatted_billing_address() ),
			'shipping_address'   => wp_strip_all_tags( $order->get_formatted_shipping_address() ),
		);
	}

	/**
	 * Executar ações do workflow
	 *
	 * @param object   $workflow Workflow.
	 * @param array    $context Contexto.
	 * @param WC_Order $order Pedido.
	 */
	private function execute_workflow_actions( $workflow, $context, $order ) {
		$start_time = microtime( true );
		$manager = PCW_Workflow_Manager::instance();

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
					$this->log( 'error', "Workflow #{$workflow->id} - Pedido #{$order->get_id()} - Erro na ação {$action['type']}: {$error_message}" );
				}
			}
		}

		// Incrementar contador
		$manager->increment_execution( $workflow->id );

		// Log de execução
		$manager->log_execution( array(
			'workflow_id'       => $workflow->id,
			'trigger_type'      => 'scheduled_order_check',
			'trigger_data'      => $context,
			'conditions_result' => true,
			'actions_executed'  => $actions_results,
			'status'            => $overall_success ? 'success' : 'failed',
			'error_message'     => $error_message,
			'execution_time'    => microtime( true ) - $start_time,
		) );
	}

	/**
	 * Executar manualmente (para testes/debug)
	 */
	public function run_now() {
		$this->process_scheduled_workflows();
	}
}
