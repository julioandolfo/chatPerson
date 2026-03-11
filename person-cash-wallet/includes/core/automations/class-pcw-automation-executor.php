<?php
/**
 * Executor de Automações
 *
 * Escuta gatilhos e dispara automações automaticamente
 *
 * @package GrowlyDigital
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe executor de automações
 */
class PCW_Automation_Executor {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Automation_Executor
	 */
	private static $instance = null;

	/**
	 * Obter instância
	 *
	 * @return PCW_Automation_Executor
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
		$this->init_hooks();
	}

	/**
	 * Inicializar hooks
	 */
	private function init_hooks() {
		// Gatilhos de pedidos
		add_action( 'woocommerce_order_status_completed', array( $this, 'trigger_order_completed' ), 10, 1 );
		add_action( 'woocommerce_thankyou', array( $this, 'trigger_order_completed' ), 10, 1 );

		// Gatilho de cashback ganho
		add_action( 'pcw_cashback_earned', array( $this, 'trigger_cashback_earned' ), 10, 3 );

		// Gatilho de novo nível alcançado
		add_action( 'pcw_level_achieved', array( $this, 'trigger_level_achieved' ), 10, 3 );

		// Gatilho de novo usuário
		add_action( 'user_register', array( $this, 'trigger_user_registered' ), 10, 1 );

		// Gatilhos via cron
		add_action( 'pcw_check_inactive_customers', array( $this, 'check_inactive_customers' ) );
		add_action( 'pcw_check_abandoned_carts', array( $this, 'check_abandoned_carts' ) );
		add_action( 'pcw_check_cashback_expiring', array( $this, 'check_cashback_expiring' ) );

		// AJAX para disparo manual
		add_action( 'wp_ajax_pcw_run_automation_cron', array( $this, 'ajax_run_automation_cron' ) );

		// Agendar crons se ainda não existem
		if ( ! wp_next_scheduled( 'pcw_check_inactive_customers' ) ) {
			wp_schedule_event( time(), 'daily', 'pcw_check_inactive_customers' );
		}
		if ( ! wp_next_scheduled( 'pcw_check_abandoned_carts' ) ) {
			wp_schedule_event( time(), 'hourly', 'pcw_check_abandoned_carts' );
		}
		if ( ! wp_next_scheduled( 'pcw_check_cashback_expiring' ) ) {
			wp_schedule_event( time(), 'daily', 'pcw_check_cashback_expiring' );
		}
	}

	/**
	 * AJAX: Disparar manualmente o cron de uma automação
	 */
	public function ajax_run_automation_cron() {
		check_ajax_referer( 'pcw_reports', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$automation_id = isset( $_POST['automation_id'] ) ? absint( $_POST['automation_id'] ) : 0;
		$automation    = PCW_Automations::instance()->get( $automation_id );

		if ( ! $automation ) {
			wp_send_json_error( array( 'message' => __( 'Automação não encontrada', 'person-cash-wallet' ) ) );
		}

		$trigger = $automation->trigger_type;
		$started = current_time( 'mysql' );

		@set_time_limit( 300 );

		error_log( "[PCW EXECUTAR AGORA] Iniciando automação #{$automation_id}, trigger={$trigger}" );

		try {
			if ( in_array( $trigger, array( 'inactive_customer', 'customer_recovery' ), true ) ) {
				$this->check_inactive_customers();
			} elseif ( $trigger === 'cart_abandoned' ) {
				$this->check_abandoned_carts();
			} elseif ( $trigger === 'cashback_expiring' ) {
				$this->check_cashback_expiring();
			} else {
				wp_send_json_error( array( 'message' => sprintf( __( 'Trigger "%s" não suporta disparo manual', 'person-cash-wallet' ), esc_html( $trigger ) ) ) );
			}
		} catch ( \Throwable $e ) {
			error_log( '[PCW Automations] Erro fatal ao executar automação #' . $automation_id . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( array(
				'message' => sprintf( __( 'Erro ao executar: %s', 'person-cash-wallet' ), $e->getMessage() ),
			) );
		}

		// Contar execuções criadas a partir do disparo
		global $wpdb;
		$executions_table = $wpdb->prefix . 'pcw_automation_executions';
		$new_execs = absint( $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$executions_table} WHERE automation_id = %d AND created_at >= %s",
			$automation_id,
			$started
		) ) );

		// Contar mensagens na fila criadas neste período
		$queue_table = $wpdb->prefix . 'pcw_message_queue';
		$new_queue = absint( $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$queue_table} WHERE created_at >= %s",
			$started
		) ) );

		$details = '';
		if ( $new_execs === 0 ) {
			$config = $automation->trigger_config ?? array();
			$inactive_days = isset( $config['inactive_days'] ) ? absint( $config['inactive_days'] ) : 30;
			$include_historical = ! empty( $config['include_historical'] ) && $config['include_historical'] === '1';
			$details = sprintf(
				' (trigger=%s, inactive_days=%d, include_historical=%s). Verifique se existem clientes elegíveis e se os steps estão configurados corretamente.',
				$trigger,
				$inactive_days,
				$include_historical ? 'sim' : 'não'
			);
		}

		error_log( "[PCW EXECUTAR AGORA] Finalizado automação #{$automation_id}: {$new_execs} execuções, {$new_queue} mensagens na fila" );

		wp_send_json_success( array(
			'message'       => sprintf(
				__( 'Cron executado com sucesso. %d execução(ões) criada(s), %d mensagem(ns) na fila.%s', 'person-cash-wallet' ),
				$new_execs,
				$new_queue,
				$details
			),
			'new_executions' => $new_execs,
			'new_queue'      => $new_queue,
		) );
	}

	/**
	 * Trigger: Pedido completado
	 *
	 * @param int $order_id ID do pedido.
	 */
	public function trigger_order_completed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		// Buscar automações ativas para este trigger
		$automations = PCW_Automations::instance()->get_active_by_trigger( 'order_completed' );

		foreach ( $automations as $automation ) {
			// Verificar condições específicas
			if ( ! $this->check_trigger_conditions( $automation, $user_id, array( 'order' => $order ) ) ) {
				continue;
			}

			// Executar automação
			$this->execute_automation( $automation, $user_id, array(
				'order_id'    => $order_id,
				'order_total' => $order->get_total(),
			) );
		}
	}

	/**
	 * Trigger: Cashback ganho
	 *
	 * @param int   $user_id ID do usuário.
	 * @param float $amount Valor do cashback.
	 * @param int   $order_id ID do pedido.
	 */
	public function trigger_cashback_earned( $user_id, $amount, $order_id ) {
		$automations = PCW_Automations::instance()->get_active_by_trigger( 'cashback_earned' );

		foreach ( $automations as $automation ) {
			if ( ! $this->check_trigger_conditions( $automation, $user_id, array( 'cashback_amount' => $amount ) ) ) {
				continue;
			}

			$this->execute_automation( $automation, $user_id, array(
				'cashback_amount' => $amount,
				'order_id'        => $order_id,
			) );
		}
	}

	/**
	 * Trigger: Novo nível alcançado
	 *
	 * @param int $user_id ID do usuário.
	 * @param int $old_level_id ID do nível antigo.
	 * @param int $new_level_id ID do novo nível.
	 */
	public function trigger_level_achieved( $user_id, $old_level_id, $new_level_id ) {
		$automations = PCW_Automations::instance()->get_active_by_trigger( 'level_achieved' );

		foreach ( $automations as $automation ) {
			// Verificar se é o nível específico configurado
			$config = $automation->trigger_config;
			if ( ! empty( $config['level_id'] ) && $config['level_id'] != $new_level_id ) {
				continue;
			}

			$this->execute_automation( $automation, $user_id, array(
				'old_level_id' => $old_level_id,
				'new_level_id' => $new_level_id,
			) );
		}
	}

	/**
	 * Trigger: Novo usuário registrado
	 *
	 * @param int $user_id ID do usuário.
	 */
	public function trigger_user_registered( $user_id ) {
		$automations = PCW_Automations::instance()->get_active_by_trigger( 'user_registered' );

		foreach ( $automations as $automation ) {
			$this->execute_automation( $automation, $user_id, array(
				'is_new_user' => true,
			) );
		}
	}

	/**
	 * Verificar se HPOS (High Performance Order Storage) está ativo
	 *
	 * @return bool
	 */
	private function is_hpos_enabled() {
		return class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Verificar clientes inativos (via cron)
	 */
	public function check_inactive_customers() {
		global $wpdb;

		$use_hpos     = $this->is_hpos_enabled();
		$orders_table = $wpdb->prefix . 'wc_orders';

		// Buscar por ambos os nomes de trigger (legado 'inactive_customer' e atual 'customer_recovery')
		$automations_1 = PCW_Automations::instance()->get_active_by_trigger( 'inactive_customer' );
		$automations_2 = PCW_Automations::instance()->get_active_by_trigger( 'customer_recovery' );
		$automations   = array_merge( $automations_1, $automations_2 );

		foreach ( $automations as $automation ) {
			$config        = $automation->trigger_config;
			$inactive_days = isset( $config['inactive_days'] ) ? absint( $config['inactive_days'] ) : 30;

			$automation_created_at = isset( $automation->created_at ) ? $automation->created_at : current_time( 'mysql' );
			$since                 = date( 'Y-m-d', strtotime( "-{$inactive_days} days" ) );

			// ─── NOVOS INATIVOS ─────────────────────────────────────────────────────
			// Clientes que ficaram inativos APÓS a criação da automação
			// → buscar inativos há X dias cujo último pedido é APÓS created_at
			if ( $use_hpos ) {
				$new_users = $wpdb->get_col( $wpdb->prepare(
					"SELECT DISTINCT o.customer_id
					FROM {$orders_table} o
					WHERE o.type = 'shop_order'
					AND o.status IN ('wc-completed', 'wc-processing')
					AND o.customer_id > 0
					AND o.customer_id NOT IN (
						SELECT DISTINCT o2.customer_id FROM {$orders_table} o2
						WHERE o2.type = 'shop_order'
						AND o2.status IN ('wc-completed', 'wc-processing')
						AND o2.date_created_gmt > %s
					)
					AND o.customer_id IN (
						SELECT DISTINCT o3.customer_id FROM {$orders_table} o3
						WHERE o3.type = 'shop_order'
						AND o3.status IN ('wc-completed', 'wc-processing')
						AND o3.date_created_gmt > %s
					)
					ORDER BY o.customer_id ASC",
					$since,
					$automation_created_at
				) );
			} else {
				$new_users = $wpdb->get_col( $wpdb->prepare(
					"SELECT DISTINCT p.post_author as user_id
					FROM {$wpdb->posts} p
					WHERE p.post_type = 'shop_order'
					AND p.post_status IN ('wc-completed', 'wc-processing')
					AND p.post_author > 0
					AND p.post_author IN (
						SELECT DISTINCT p_last.post_author
						FROM {$wpdb->posts} p_last
						WHERE p_last.post_type = 'shop_order'
						AND p_last.post_status IN ('wc-completed', 'wc-processing')
						AND p_last.post_date BETWEEN %s AND %s
						GROUP BY p_last.post_author
						HAVING MAX(p_last.post_date) = p_last.post_date
					)
					AND p.post_author NOT IN (
						SELECT DISTINCT p2.post_author FROM {$wpdb->posts} p2
						WHERE p2.post_type = 'shop_order'
						AND p2.post_status IN ('wc-completed', 'wc-processing')
						AND p2.post_date > %s
					)
					ORDER BY p.post_author ASC",
					$automation_created_at,
					$since,
					$since
				) );
			}

			error_log( sprintf( '[PCW Automations] Automação #%d: HPOS=%s, novos_inativos=%d, since=%s, created_at=%s', $automation->id, $use_hpos ? 'sim' : 'não', count( $new_users ), $since, $automation_created_at ) );

			$skipped_already_notified = 0;
			$executed_new = 0;
			foreach ( $new_users as $user_id ) {
				if ( $this->was_notified_while_still_inactive( $automation->id, $user_id ) ) {
					$skipped_already_notified++;
					continue;
				}
				$this->execute_automation( $automation, $user_id, array(
					'inactive_days' => $inactive_days,
					'source'        => 'new',
				) );
				$executed_new++;
			}
			error_log( sprintf( '[PCW Automations] Automação #%d: novos_inativos executados=%d, ignorados_já_notificados=%d', $automation->id, $executed_new, $skipped_already_notified ) );

			// ─── CLIENTES HISTÓRICOS (PACING) ────────────────────────────────────
			$include_historical = ! empty( $config['include_historical'] ) && $config['include_historical'] === '1';
			if ( ! $include_historical ) {
				continue;
			}

			$batch_per_day       = isset( $config['batch_per_day'] ) ? max( 1, absint( $config['batch_per_day'] ) ) : 50;
			$batch_days          = isset( $config['batch_days'] )    ? max( 1, absint( $config['batch_days'] ) )    : 30;
			$days_since_creation = max( 0, floor( ( current_time( 'timestamp' ) - strtotime( $automation_created_at ) ) / DAY_IN_SECONDS ) );

			if ( $days_since_creation >= $batch_days ) {
				// Período de pacing encerrado — histórico completo
				error_log( "[PCW Automations] Automação #{$automation->id}: período de pacing histórico encerrado ({$days_since_creation}/{$batch_days} dias)" );
				continue;
			}

			// Buscar lote histórico: inativos há X dias, cujo último pedido é ANTES da criação da automação
			// O SQL já exclui quem já foi notificado (e ainda não comprou depois)
			$batch = $this->get_inactive_customers( $inactive_days, $automation->id, $batch_per_day );

			// Filtrar apenas históricos (último pedido antes da automação)
			$historical_batch = array();
			foreach ( $batch as $user_id ) {
				$last_order = $this->get_user_last_order_date( $user_id );
				if ( $last_order && $last_order < $automation_created_at ) {
					$historical_batch[] = $user_id;
				}
			}

			// Se o lote filtrado ficou abaixo do batch_per_day (porque alguns eram "novos"),
			// buscar mais candidatos históricos para completar
			if ( count( $historical_batch ) < $batch_per_day ) {
				$extra = $this->get_inactive_customers( $inactive_days, $automation->id, $batch_per_day * 3, $batch_per_day );
				foreach ( $extra as $user_id ) {
					if ( count( $historical_batch ) >= $batch_per_day ) {
						break;
					}
					$last_order = $this->get_user_last_order_date( $user_id );
					if ( $last_order && $last_order < $automation_created_at && ! in_array( $user_id, $historical_batch, true ) ) {
						$historical_batch[] = $user_id;
					}
				}
			}

			foreach ( $historical_batch as $user_id ) {
				$this->execute_automation( $automation, $user_id, array(
					'inactive_days' => $inactive_days,
					'source'        => 'historical_batch',
					'batch_day'     => $days_since_creation + 1,
				) );
			}

			if ( ! empty( $historical_batch ) ) {
				error_log( sprintf(
					'[PCW Automations] Automação #%d (pacing histórico): %d clientes no dia %d/%d',
					$automation->id,
					count( $historical_batch ),
					$days_since_creation + 1,
					$batch_days
				) );
			}
		}
	}

	/**
	 * Obter data do último pedido de um usuário
	 *
	 * @param int $user_id ID do usuário.
	 * @return string|null Data no formato MySQL ou null.
	 */
	private function get_user_last_order_date( $user_id ) {
		global $wpdb;

		if ( $this->is_hpos_enabled() ) {
			$orders_table = $wpdb->prefix . 'wc_orders';
			return $wpdb->get_var( $wpdb->prepare(
				"SELECT MAX(date_created_gmt) FROM {$orders_table}
				WHERE type = 'shop_order'
				AND status IN ('wc-completed', 'wc-processing')
				AND customer_id = %d",
				$user_id
			) );
		}

		return $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(post_date) FROM {$wpdb->posts}
			WHERE post_type = 'shop_order'
			AND post_status IN ('wc-completed', 'wc-processing')
			AND post_author = %d",
			$user_id
		) );
	}

	/**
	 * Verificar carrinhos abandonados (via cron)
	 */
	public function check_abandoned_carts() {
		$automations = PCW_Automations::instance()->get_active_by_trigger( 'cart_abandoned' );

		foreach ( $automations as $automation ) {
			$config = $automation->trigger_config;
			$abandoned_hours = isset( $config['abandoned_hours'] ) ? absint( $config['abandoned_hours'] ) : 24;

			// Buscar carrinhos abandonados
			$carts = $this->get_abandoned_carts( $abandoned_hours );

			foreach ( $carts as $cart ) {
				// Verificar se já foi enviado
				if ( $this->was_recently_sent( $automation->id, $cart['user_id'], 1 ) ) {
					continue;
				}

				$this->execute_automation( $automation, $cart['user_id'], array(
					'cart_total' => $cart['cart_total'],
					'cart_items' => $cart['cart_items'],
				) );
			}
		}
	}

	/**
	 * Verificar cashback expirando (via cron)
	 */
	public function check_cashback_expiring() {
		$automations = PCW_Automations::instance()->get_active_by_trigger( 'cashback_expiring' );

		foreach ( $automations as $automation ) {
			$config = $automation->trigger_config;
			$expiring_days = isset( $config['expiring_days'] ) ? absint( $config['expiring_days'] ) : 7;

			// Buscar cashback expirando
			$expiring = $this->get_expiring_cashback( $expiring_days );

			foreach ( $expiring as $item ) {
				// Verificar se já foi enviado
				if ( $this->was_recently_sent( $automation->id, $item['user_id'], 3 ) ) {
					continue;
				}

				$this->execute_automation( $automation, $item['user_id'], array(
					'cashback_amount'  => $item['amount'],
					'expiration_date'  => $item['expiration_date'],
					'days_to_expire'   => $item['days_to_expire'],
				) );
			}
		}
	}

	/**
	 * Executar automação
	 *
	 * @param object $automation Dados da automação.
	 * @param int    $user_id ID do usuário.
	 * @param array  $extra_data Dados extras.
	 */
	private function execute_automation( $automation, $user_id, $extra_data = array() ) {
		global $wpdb;

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}

		// Criar execução
		$executions_table = $wpdb->prefix . 'pcw_automation_executions';
		$wpdb->insert(
			$executions_table,
			array(
				'automation_id' => $automation->id,
				'user_id'       => $user_id,
				'step_results'  => wp_json_encode( $extra_data ),
				'status'        => 'processing',
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		$execution_id = $wpdb->insert_id;

		if ( ! $execution_id ) {
			return;
		}

		// Registrar evento de disparo
		$this->record_event( $automation->id, $execution_id, 'dispatch', $user_id, $user->user_email );

		// Processar workflow
		$workflow_steps = $automation->workflow_steps;
		$success = true;

		foreach ( $workflow_steps as $index => $step ) {
			// Passar o índice do step atual para que send_whatsapp/send_email saibam qual config usar
			$step_extra = array_merge( $extra_data, array( 'current_step_index' => $index ) );

			$step_result = $this->execute_step( $step, $automation, $execution_id, $user_id, $step_extra );

			if ( is_wp_error( $step_result ) ) {
				$success = false;
				error_log( sprintf(
					'[PCW Automations] Erro na automação %d, passo %d: %s',
					$automation->id,
					$index,
					$step_result->get_error_message()
				) );
				break;
			}

			// Delay não é feito com sleep() em produção — steps com delay são adicionados à fila
			// A gestão de tempo é feita pelo sistema de fila de mensagens
		}

		// Atualizar execução
		$wpdb->update(
			$executions_table,
			array(
				'status'       => $success ? 'completed' : 'failed',
				'completed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $execution_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Executar passo do workflow
	 *
	 * @param array  $step Dados do passo.
	 * @param object $automation Automação.
	 * @param int    $execution_id ID da execução.
	 * @param int    $user_id ID do usuário.
	 * @param array  $extra_data Dados extras.
	 * @return true|WP_Error
	 */
	private function execute_step( $step, $automation, $execution_id, $user_id, $extra_data ) {
		// Suporta tanto 'action' (legado) quanto 'type' (novo formato)
		$action = isset( $step['action'] ) ? $step['action'] : ( isset( $step['type'] ) ? $step['type'] : '' );

		if ( empty( $action ) || $action === 'delay' ) {
			// Steps do tipo 'delay' são ignorados na execução direta (o tempo é controlado pela fila)
			return true;
		}

		switch ( $action ) {
			case 'send_email':
				return $this->send_email( $automation, $execution_id, $user_id, $extra_data );

			case 'send_whatsapp':
				return $this->send_whatsapp( $automation, $execution_id, $user_id, $extra_data );

			case 'add_to_list':
				$list_id = isset( $step['list_id'] ) ? $step['list_id'] : ( isset( $step['config']['list_id'] ) ? $step['config']['list_id'] : 0 );
				return $this->add_to_list( $user_id, $list_id );

			case 'remove_from_list':
				$list_id = isset( $step['list_id'] ) ? $step['list_id'] : ( isset( $step['config']['list_id'] ) ? $step['config']['list_id'] : 0 );
				return $this->remove_from_list( $user_id, $list_id );

			case 'condition':
			case 'add_tag':
			case 'webhook':
				// Tipos ainda não implementados — ignorar sem erro
				return true;

			default:
				return new WP_Error( 'unknown_action', sprintf( __( 'Ação desconhecida: %s', 'person-cash-wallet' ), $action ) );
		}
	}

	/**
	 * Enviar email com tracking
	 *
	 * @param object $automation Automação.
	 * @param int    $execution_id ID da execução.
	 * @param int    $user_id ID do usuário.
	 * @param array  $extra_data Dados extras.
	 * @return true|WP_Error
	 */
	private function send_email( $automation, $execution_id, $user_id, $extra_data ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'Usuário não encontrado', 'person-cash-wallet' ) );
		}

		// Processar variáveis no template
		$html_content = PCW_Automation_Email_Helper::process_email_variables(
			$automation->email_template,
			$user_id,
			$extra_data
		);

		// Processar variáveis no assunto
		$subject = PCW_Automation_Email_Helper::process_email_variables(
			$automation->email_subject,
			$user_id,
			$extra_data
		);

		// Enviar com tracking
		$result = PCW_Automation_Email_Helper::send_tracked_email(
			$automation->id,
			$execution_id,
			$user_id,
			$user->user_email,
			$subject,
			$html_content
		);

		return $result;
	}

	/**
	 * Enviar WhatsApp
	 *
	 * @param object $automation Automação.
	 * @param int    $execution_id ID da execução.
	 * @param int    $user_id ID do usuário.
	 * @param array  $extra_data Dados extras.
	 * @return true|WP_Error
	 */
	private function send_whatsapp( $automation, $execution_id, $user_id, $extra_data ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			error_log( "[PCW send_whatsapp] user_id={$user_id} não encontrado" );
			return new WP_Error( 'user_not_found', __( 'Usuário não encontrado', 'person-cash-wallet' ) );
		}

		// Buscar config da etapa atual
		$current_step = null;
		if ( isset( $extra_data['current_step_index'] ) && isset( $automation->workflow_steps[ $extra_data['current_step_index'] ] ) ) {
			$current_step = $automation->workflow_steps[ $extra_data['current_step_index'] ];
		}

		$step_type = $current_step['type'] ?? $current_step['action'] ?? '';
		if ( ! $current_step || $step_type !== 'send_whatsapp' ) {
			error_log( "[PCW send_whatsapp] Step inválido. step_type={$step_type}, automation={$automation->id}, step_index=" . ( $extra_data['current_step_index'] ?? 'N/A' ) );
			error_log( "[PCW send_whatsapp] current_step=" . wp_json_encode( $current_step ) );
			return new WP_Error( 'invalid_step', __( 'Configuração de WhatsApp não encontrada', 'person-cash-wallet' ) );
		}

		$config = isset( $current_step['config'] ) ? $current_step['config'] : array();

		// Verificar se tem Personizi habilitado
		$use_personizi = isset( $config['use_personizi'] ) && $config['use_personizi'] == '1';

		if ( ! $use_personizi ) {
			return new WP_Error( 'whatsapp_not_configured', __( 'Integração WhatsApp não configurada', 'person-cash-wallet' ) );
		}

		// Mensagem base configurada na automação
		$message = isset( $config['message'] ) ? $config['message'] : '';

		// Geração de variação única por IA (se habilitado)
		$ai_unique = ! empty( $config['ai_unique_message'] ) && $config['ai_unique_message'] == '1';
		if ( $ai_unique ) {
			$openai = PCW_OpenAI::instance();
			if ( $openai->is_configured() ) {
				$recipient_data = array(
					'first_name' => $user->first_name ?: $user->display_name,
				);
				$trigger_type   = isset( $automation->type ) ? $automation->type : '';
				$ai_variation   = $openai->generate_unique_whatsapp_variation( $message, $recipient_data, $trigger_type );

				if ( ! is_wp_error( $ai_variation ) && ! empty( $ai_variation ) ) {
					$message = $ai_variation;
				}
				// Se IA falhar, usa a mensagem base — sem interromper o fluxo
			}
		}

		// Processar variáveis na mensagem ({{customer_first_name}}, etc.)
		$message = PCW_Automation_Email_Helper::process_email_variables( $message, $user_id, $extra_data );

		// Obter telefone do usuário
		$phone = get_user_meta( $user_id, 'billing_phone', true );
		if ( empty( $phone ) ) {
			$phone = get_user_meta( $user_id, 'phone', true );
		}

		if ( empty( $phone ) ) {
			return new WP_Error( 'no_phone', __( 'Usuário não possui telefone cadastrado', 'person-cash-wallet' ) );
		}

		// Normalizar telefone para formato internacional
		$phone = $this->normalize_phone( $phone );
		if ( empty( $phone ) ) {
			return new WP_Error( 'invalid_phone', __( 'Telefone do usuário é inválido', 'person-cash-wallet' ) );
		}

		// Verificar se a fila está pausada
		if ( get_option( 'pcw_queue_paused', false ) ) {
			return new WP_Error( 'queue_paused', __( 'Fila de disparos está pausada', 'person-cash-wallet' ) );
		}

		$queue_manager = PCW_Message_Queue_Manager::instance();

		$from_number = '';
		if ( ! empty( $config['personizi_from'] ) ) {
			$from_number = $config['personizi_from'];
			error_log( "[PCW send_whatsapp] Usando número específico configurado: {$from_number}" );
		} else {
			error_log( "[PCW send_whatsapp] AVISO: personizi_from vazio. Config keys: " . wp_json_encode( array_keys( $config ) ) );
			error_log( "[PCW send_whatsapp] Config completo (parcial): personizi_from=" . ( $config['personizi_from'] ?? 'NULL' ) . ", use_personizi=" . ( $config['use_personizi'] ?? 'NULL' ) . ", use_specific_number=" . ( $config['use_specific_number'] ?? 'NULL' ) );
		}

		$use_template = ! empty( $config['use_template'] ) && $config['use_template'] == '1';

		error_log( "[PCW send_whatsapp] automation={$automation->id}, user={$user_id}, phone={$phone}, from={$from_number}, use_template=" . ( $use_template ? 'sim' : 'não' ) . ", template_name=" . ( $config['template_name'] ?? 'N/A' ) . ", message_len=" . strlen( $message ) );

		if ( $use_template && ! empty( $config['template_name'] ) ) {
			$template_params = array();
			if ( ! empty( $config['template_params'] ) ) {
				$raw_params = is_string( $config['template_params'] )
					? json_decode( $config['template_params'], true )
					: $config['template_params'];
				if ( is_array( $raw_params ) ) {
					foreach ( $raw_params as $param ) {
						$template_params[] = PCW_Automation_Email_Helper::process_email_variables( $param, $user_id, $extra_data );
					}
				}
			}

			$template_body = isset( $config['template_body_text'] ) ? $config['template_body_text'] : '';
			$template_header = isset( $config['template_header_text'] ) ? $config['template_header_text'] : '';
			$template_footer = isset( $config['template_footer_text'] ) ? $config['template_footer_text'] : '';
			$template_buttons = isset( $config['template_buttons'] ) ? $config['template_buttons'] : '[]';

			error_log( "[PCW send_whatsapp] Adicionando template à fila: template={$config['template_name']}, params=" . wp_json_encode( $template_params ) );

			$queue_id = $queue_manager->add_template_to_queue( array(
				'to'                 => $phone,
				'from'               => $from_number,
				'template_name'      => $config['template_name'],
				'template_params'    => $template_params,
				'template_language'  => ! empty( $config['template_language'] ) ? $config['template_language'] : 'pt_BR',
				'template_body_text' => $template_body,
				'template_header_text' => $template_header,
				'template_footer_text' => $template_footer,
				'template_buttons'   => $template_buttons,
				'contact_name'       => $user->display_name,
				'automation_id'      => $automation->id,
			) );
		} else {
			if ( empty( $message ) && ! $use_template ) {
				error_log( "[PCW send_whatsapp] AVISO: Mensagem vazia e sem template! config=" . wp_json_encode( array_keys( $config ) ) );
			}

			$queue_args = array(
				'type'          => 'whatsapp',
				'to_number'     => $phone,
				'message'       => $message,
				'contact_name'  => $user->display_name,
				'automation_id' => $automation->id,
				'priority'      => 5,
			);

			if ( ! empty( $from_number ) ) {
				$queue_args['from_number'] = $from_number;
			}

			error_log( "[PCW send_whatsapp] Adicionando mensagem à fila: to={$phone}, from={$from_number}, msg_len=" . strlen( $message ) );

			$queue_id = $queue_manager->add_to_queue( $queue_args );
		}

		error_log( "[PCW send_whatsapp] Resultado queue_id=" . var_export( $queue_id, true ) );

		if ( false === $queue_id ) {
			error_log( "[PCW send_whatsapp] ERRO: Falha ao adicionar à fila. automation={$automation->id}, user={$user_id}" );
			return new WP_Error( 'queue_error', __( 'Erro ao adicionar mensagem à fila', 'person-cash-wallet' ) );
		}

		// Incrementar contador de enviados na automação
		PCW_Automations::instance()->increment_stat( $automation->id, 'sent' );

		// Registrar evento de envio WhatsApp
		$this->record_event( $automation->id, $execution_id, 'whatsapp_queued', $user_id, $phone, array(
			'queue_id' => $queue_id,
			'strategy' => $strategy,
		) );

		// Log do envio (mensagem foi adicionada à fila)
		$this->log_action(
			$execution_id,
			'send_whatsapp',
			array(
				'to'       => $phone,
				'message'  => substr( $message, 0, 100 ),
				'queue_id' => $queue_id,
				'strategy' => $strategy,
				'status'   => 'queued',
			)
		);

		return true;
	}

	/**
	 * Adicionar usuário a lista
	 *
	 * @param int $user_id ID do usuário.
	 * @param int $list_id ID da lista.
	 * @return true|WP_Error
	 */
	private function add_to_list( $user_id, $list_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'Usuário não encontrado', 'person-cash-wallet' ) );
		}

		$members = array(
			array(
				'email' => $user->user_email,
				'name'  => $user->display_name,
			),
		);

		$result = PCW_Custom_Lists::add_members( $list_id, $members );

		if ( isset( $result['errors'] ) && ! empty( $result['errors'] ) ) {
			return new WP_Error( 'add_to_list_error', implode( ', ', $result['errors'] ) );
		}

		return true;
	}

	/**
	 * Remover usuário de lista
	 *
	 * @param int $user_id ID do usuário.
	 * @param int $list_id ID da lista.
	 * @return true|WP_Error
	 */
	private function remove_from_list( $user_id, $list_id ) {
		global $wpdb;

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'Usuário não encontrado', 'person-cash-wallet' ) );
		}

		$members_table = $wpdb->prefix . 'pcw_list_members';

		// Buscar membro na lista
		$member_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$members_table} WHERE list_id = %d AND email = %s",
			$list_id,
			$user->user_email
		) );

		if ( ! $member_id ) {
			return new WP_Error( 'member_not_found', __( 'Contato não encontrado na lista', 'person-cash-wallet' ) );
		}

		$removed = PCW_Custom_Lists::remove_member( $list_id, $member_id );

		if ( ! $removed ) {
			return new WP_Error( 'remove_error', __( 'Erro ao remover contato da lista', 'person-cash-wallet' ) );
		}

		return true;
	}

	/**
	 * Verificar condições do trigger
	 *
	 * @param object $automation Automação.
	 * @param int    $user_id ID do usuário.
	 * @param array  $context Contexto adicional.
	 * @return bool
	 */
	private function check_trigger_conditions( $automation, $user_id, $context = array() ) {
		$config = $automation->trigger_config;

		// Verificar valor mínimo do pedido
		if ( isset( $context['order'] ) && ! empty( $config['min_order_value'] ) ) {
			$order = $context['order'];
			if ( $order->get_total() < floatval( $config['min_order_value'] ) ) {
				return false;
			}
		}

		// Verificar valor mínimo do cashback
		if ( isset( $context['cashback_amount'] ) && ! empty( $config['min_cashback_value'] ) ) {
			if ( $context['cashback_amount'] < floatval( $config['min_cashback_value'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Verificar se o cliente já foi notificado e ainda não comprou novamente.
	 *
	 * Retorna true (bloquear envio) se:
	 * - Já existe um registro desta automação para o usuário (seed ou disparo real), E
	 * - O usuário NÃO fez nenhuma compra APÓS esse último registro.
	 *
	 * Retorna false (permitir envio) se:
	 * - Nunca foi notificado (primeira vez), OU
	 * - Foi notificado, comprou depois, e ficou inativo de novo.
	 *
	 * @param int $automation_id ID da automação.
	 * @param int $user_id ID do usuário.
	 * @return bool
	 */
	private function was_notified_while_still_inactive( $automation_id, $user_id ) {
		global $wpdb;

		$executions_table = $wpdb->prefix . 'pcw_automation_executions';

		// Buscar data do último registro desta automação para o usuário
		$last_notified = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(created_at) FROM {$executions_table} WHERE automation_id = %d AND user_id = %d",
			$automation_id,
			$user_id
		) );

		// Nunca foi notificado → permitir
		if ( ! $last_notified ) {
			return false;
		}

		// Verificar se o cliente fez alguma compra APÓS a última notificação
		if ( $this->is_hpos_enabled() ) {
			$orders_table = $wpdb->prefix . 'wc_orders';
			$orders_after = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders_table}
				WHERE type = 'shop_order'
				AND status IN ('wc-completed', 'wc-processing')
				AND customer_id = %d
				AND date_created_gmt > %s",
				$user_id,
				$last_notified
			) );
		} else {
			$orders_after = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				WHERE post_type = 'shop_order'
				AND post_status IN ('wc-completed', 'wc-processing')
				AND post_author = %d
				AND post_date > %s",
				$user_id,
				$last_notified
			) );
		}

		// Se comprou após a notificação, o cliente "voltou" — pode ser notificado novamente no futuro
		// mas ainda verificamos se está inativo (isso é feito no get_inactive_customers)
		// Se não comprou: já foi notificado e continua inativo → não reenviar
		return intval( $orders_after ) === 0;
	}

	/**
	 * Verificar se foi enviado recentemente
	 *
	 * @param int $automation_id ID da automação.
	 * @param int $user_id ID do usuário.
	 * @param int $days Dias para considerar.
	 * @return bool
	 */
	private function was_recently_sent( $automation_id, $user_id, $days = 7 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_automation_executions';
		$since = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE automation_id = %d AND user_id = %d AND created_at > %s",
			$automation_id,
			$user_id,
			$since
		) );

		return $count > 0;
	}

	/**
	 * Registrar usuários ATUALMENTE elegíveis como "já processados" ao criar a automação.
	 *
	 * Isso garante que a automação só dispare para casos que se tornarem elegíveis
	 * DEPOIS da sua criação, evitando spam em massa nos clientes históricos.
	 *
	 * @param int    $automation_id ID da automação recém-criada.
	 * @param string $type Tipo da automação.
	 * @param array  $trigger_config Configuração do trigger.
	 */
	public function seed_existing_eligible_users( $automation_id, $type, $trigger_config ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_automation_executions';
		$now   = current_time( 'mysql' );
		$users = array();

		switch ( $type ) {
			case 'customer_recovery':
				$days  = isset( $trigger_config['inactive_days'] ) ? absint( $trigger_config['inactive_days'] ) : 30;
				$users = $this->get_inactive_customers( $days );
				break;

			case 'cashback_expiring':
				$days    = isset( $trigger_config['expiring_days'] ) ? absint( $trigger_config['expiring_days'] ) : 7;
				$expiring = $this->get_expiring_cashback( $days );
				$users   = array_column( $expiring, 'user_id' );
				break;

			case 'abandoned_cart':
				// Não há implementação de carrinhos ainda, mas deixamos o case para o futuro
				break;
		}

		if ( empty( $users ) ) {
			return;
		}

		// Inserir em lotes de 100 para não travar o banco
		$chunks = array_chunk( $users, 100 );
		foreach ( $chunks as $chunk ) {
			foreach ( $chunk as $user_id ) {
				$user_id = absint( $user_id );
				if ( ! $user_id ) {
					continue;
				}

				// Inserir registro com status 'skipped' — aparece nos logs como "já existia"
				// mas impede disparos futuros até o período de was_recently_sent expirar
				$wpdb->insert(
					$table,
					array(
						'automation_id' => $automation_id,
						'user_id'       => $user_id,
						'step_results'  => wp_json_encode( array( 'seed' => true, 'note' => 'Cliente já estava elegível na criação da automação' ) ),
						'status'        => 'skipped',
						'created_at'    => $now,
						'completed_at'  => $now,
					),
					array( '%d', '%d', '%s', '%s', '%s', '%s' )
				);
			}
		}
	}

	/**
	 * Buscar clientes inativos.
	 *
	 * @param int      $days       Dias de inatividade mínima.
	 * @param int      $automation_id ID da automação (para excluir já processados). 0 = sem exclusão.
	 * @param int      $limit      Limite de resultados. 0 = sem limite.
	 * @param int      $offset     Offset para paginação.
	 * @return array   Array de user_ids.
	 */
	private function get_inactive_customers( $days, $automation_id = 0, $limit = 0, $offset = 0 ) {
		global $wpdb;

		$since        = date( 'Y-m-d', strtotime( "-{$days} days" ) );
		$executions   = $wpdb->prefix . 'pcw_automation_executions';
		$use_hpos     = $this->is_hpos_enabled();
		$orders_table = $wpdb->prefix . 'wc_orders';

		$limit_clause  = $limit > 0 ? $wpdb->prepare( 'LIMIT %d', $limit ) : '';
		$offset_clause = $offset > 0 ? $wpdb->prepare( 'OFFSET %d', $offset ) : '';

		if ( $use_hpos ) {
			$exclude_notified = '';
			if ( $automation_id > 0 ) {
				$exclude_notified = $wpdb->prepare(
					"AND o.customer_id NOT IN (
						SELECT e.user_id FROM {$executions} e
						WHERE e.automation_id = %d
						AND e.user_id NOT IN (
							SELECT DISTINCT o3.customer_id FROM {$orders_table} o3
							WHERE o3.type = 'shop_order'
							AND o3.status IN ('wc-completed', 'wc-processing')
							AND o3.customer_id = e.user_id
							AND o3.date_created_gmt > e.created_at
						)
					)",
					$automation_id
				);
			}

			$sql = "
				SELECT DISTINCT o.customer_id
				FROM {$orders_table} o
				WHERE o.type = 'shop_order'
				AND o.status IN ('wc-completed', 'wc-processing')
				AND o.customer_id > 0
				AND o.customer_id NOT IN (
					SELECT DISTINCT o2.customer_id
					FROM {$orders_table} o2
					WHERE o2.type = 'shop_order'
					AND o2.status IN ('wc-completed', 'wc-processing')
					AND o2.date_created_gmt > '{$since}'
				)
				{$exclude_notified}
				ORDER BY o.customer_id ASC
				{$limit_clause} {$offset_clause}
			";
		} else {
			$exclude_notified = '';
			if ( $automation_id > 0 ) {
				$exclude_notified = $wpdb->prepare(
					"AND p.post_author NOT IN (
						SELECT e.user_id FROM {$executions} e
						WHERE e.automation_id = %d
						AND e.user_id NOT IN (
							SELECT DISTINCT p3.post_author FROM {$wpdb->posts} p3
							WHERE p3.post_type = 'shop_order'
							AND p3.post_status IN ('wc-completed', 'wc-processing')
							AND p3.post_author = e.user_id
							AND p3.post_date > e.created_at
						)
					)",
					$automation_id
				);
			}

			$sql = "
				SELECT DISTINCT p.post_author as user_id
				FROM {$wpdb->posts} p
				WHERE p.post_type = 'shop_order'
				AND p.post_status IN ('wc-completed', 'wc-processing')
				AND p.post_author > 0
				AND p.post_author NOT IN (
					SELECT DISTINCT p2.post_author
					FROM {$wpdb->posts} p2
					WHERE p2.post_type = 'shop_order'
					AND p2.post_status IN ('wc-completed', 'wc-processing')
					AND p2.post_date > '{$since}'
				)
				{$exclude_notified}
				ORDER BY p.post_author ASC
				{$limit_clause} {$offset_clause}
			";
		}

		return $wpdb->get_col( $sql );
	}

	/**
	 * Buscar carrinhos abandonados
	 *
	 * @param int $hours Horas de abandono.
	 * @return array
	 */
	private function get_abandoned_carts( $hours ) {
		// TODO: Implementar busca de carrinhos abandonados
		// Por ora retorna array vazio
		return array();
	}

	/**
	 * Buscar cashback expirando
	 *
	 * @param int $days Dias até expirar.
	 * @return array
	 */
	private function get_expiring_cashback( $days ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_cashback';
		$start_date = date( 'Y-m-d' );
		$end_date = date( 'Y-m-d', strtotime( "+{$days} days" ) );

		$sql = "
			SELECT 
				user_id,
				SUM(amount) as amount,
				MIN(expires_at) as expiration_date,
				DATEDIFF(MIN(expires_at), NOW()) as days_to_expire
			FROM {$table}
			WHERE status = 'available'
			AND expires_at BETWEEN %s AND %s
			GROUP BY user_id
			HAVING amount > 0
		";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $start_date, $end_date ), ARRAY_A );

		return $results ? $results : array();
	}

	/**
	 * Registrar evento na tabela de analytics
	 *
	 * @param int    $automation_id ID da automação.
	 * @param int    $execution_id ID da execução.
	 * @param string $event_type Tipo do evento.
	 * @param int    $user_id ID do usuário.
	 * @param string $email Email ou telefone.
	 * @param array  $metadata Dados extras.
	 */
	private function record_event( $automation_id, $execution_id, $event_type, $user_id, $email = '', $metadata = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_automation_events';

		$wpdb->insert(
			$table,
			array(
				'automation_id' => $automation_id,
				'execution_id'  => $execution_id,
				'event_type'    => $event_type,
				'user_id'       => $user_id,
				'email'         => $email,
				'metadata'      => ! empty( $metadata ) ? wp_json_encode( $metadata ) : null,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Registrar ação executada em um passo da automação
	 *
	 * @param int    $execution_id ID da execução.
	 * @param string $action Tipo de ação.
	 * @param array  $data Dados da ação.
	 */
	private function log_action( $execution_id, $action, $data = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_automation_executions';

		$current = $wpdb->get_var( $wpdb->prepare(
			"SELECT step_results FROM {$table} WHERE id = %d",
			$execution_id
		) );

		$results = $current ? json_decode( $current, true ) : array();
		if ( ! is_array( $results ) ) {
			$results = array();
		}

		$results[] = array(
			'action' => $action,
			'data'   => $data,
			'time'   => current_time( 'mysql' ),
		);

		$wpdb->update(
			$table,
			array( 'step_results' => wp_json_encode( $results ) ),
			array( 'id' => $execution_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Normalizar telefone para formato internacional
	 *
	 * @param string $phone Telefone em qualquer formato.
	 * @return string Telefone normalizado (apenas números com código do país).
	 */
	private function normalize_phone( $phone ) {
		// Remover tudo que não for número
		$phone = preg_replace( '/[^0-9]/', '', $phone );
		
		if ( empty( $phone ) ) {
			return '';
		}
		
		// Se começa com 0, remover
		$phone = ltrim( $phone, '0' );
		
		// Se tem menos de 10 dígitos, inválido
		if ( strlen( $phone ) < 10 ) {
			return '';
		}
		
		// Se não começa com 55 (Brasil), adicionar
		if ( substr( $phone, 0, 2 ) !== '55' ) {
			$phone = '55' . $phone;
		}
		
		return $phone;
	}
}
