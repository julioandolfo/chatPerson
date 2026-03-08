<?php
/**
 * Gerenciador de Workflows
 *
 * @package GrowlyDigital
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe gerenciadora de workflows
 */
class PCW_Workflow_Manager {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Workflow_Manager
	 */
	private static $instance = null;

	/**
	 * Obter instância
	 *
	 * @return PCW_Workflow_Manager
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
	 * Criar workflow
	 *
	 * @param array $data Dados do workflow.
	 * @return int|false ID do workflow ou false em caso de erro.
	 */
	public function create( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_workflows';

		$insert_data = array(
			'name'           => sanitize_text_field( $data['name'] ),
			'description'    => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'status'         => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'active',
			'trigger_type'   => sanitize_text_field( $data['trigger_type'] ),
			'trigger_config' => isset( $data['trigger_config'] ) ? wp_json_encode( $data['trigger_config'] ) : '{}',
			'conditions'     => isset( $data['conditions'] ) ? wp_json_encode( $data['conditions'] ) : '[]',
			'actions'        => isset( $data['actions'] ) ? wp_json_encode( $data['actions'] ) : '[]',
			'priority'       => isset( $data['priority'] ) ? absint( $data['priority'] ) : 10,
			'created_by'     => get_current_user_id(),
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $insert_data );

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Atualizar workflow
	 *
	 * @param int   $id ID do workflow.
	 * @param array $data Dados para atualizar.
	 * @return bool
	 */
	public function update( $id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_workflows';

		$update_data = array(
			'updated_at' => current_time( 'mysql' ),
		);

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['description'] ) ) {
			$update_data['description'] = sanitize_textarea_field( $data['description'] );
		}
		if ( isset( $data['status'] ) ) {
			$update_data['status'] = sanitize_text_field( $data['status'] );
		}
		if ( isset( $data['trigger_type'] ) ) {
			$update_data['trigger_type'] = sanitize_text_field( $data['trigger_type'] );
		}
		if ( isset( $data['trigger_config'] ) ) {
			$update_data['trigger_config'] = wp_json_encode( $data['trigger_config'] );
		}
		if ( isset( $data['conditions'] ) ) {
			$update_data['conditions'] = wp_json_encode( $data['conditions'] );
		}
		if ( isset( $data['actions'] ) ) {
			$update_data['actions'] = wp_json_encode( $data['actions'] );
		}
		if ( isset( $data['priority'] ) ) {
			$update_data['priority'] = absint( $data['priority'] );
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Deletar workflow
	 *
	 * @param int $id ID do workflow.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_workflows';

		$result = $wpdb->delete(
			$table,
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Obter workflow por ID
	 *
	 * @param int $id ID do workflow.
	 * @return object|null
	 */
	public function get( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_workflows';

		$workflow = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id )
		);

		if ( $workflow ) {
			$workflow->trigger_config = json_decode( $workflow->trigger_config, true );
			$workflow->conditions = json_decode( $workflow->conditions, true );
			$workflow->actions = json_decode( $workflow->actions, true );
		}

		return $workflow;
	}

	/**
	 * Obter todos os workflows
	 *
	 * @param array $args Argumentos de consulta.
	 * @return array
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_workflows';

		$defaults = array(
			'status'   => '',
			'orderby'  => 'priority',
			'order'    => 'ASC',
			'limit'    => 100,
			'offset'   => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where .= ' AND status = %s';
			$params[] = $args['status'];
		}

		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		if ( ! $orderby ) {
			$orderby = 'priority ASC';
		}

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} LIMIT %d OFFSET %d";
		$params[] = $args['limit'];
		$params[] = $args['offset'];

		$workflows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		foreach ( $workflows as $workflow ) {
			$workflow->trigger_config = json_decode( $workflow->trigger_config, true );
			$workflow->conditions = json_decode( $workflow->conditions, true );
			$workflow->actions = json_decode( $workflow->actions, true );
		}

		return $workflows;
	}

	/**
	 * Obter workflows ativos por tipo de gatilho
	 *
	 * @param string $trigger_type Tipo de gatilho.
	 * @return array
	 */
	public function get_by_trigger( $trigger_type ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_workflows';

		$workflows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'active' AND trigger_type = %s ORDER BY priority ASC",
				$trigger_type
			)
		);

		foreach ( $workflows as $workflow ) {
			$workflow->trigger_config = json_decode( $workflow->trigger_config, true );
			$workflow->conditions = json_decode( $workflow->conditions, true );
			$workflow->actions = json_decode( $workflow->actions, true );
		}

		return $workflows;
	}

	/**
	 * Incrementar contador de execução
	 *
	 * @param int $id ID do workflow.
	 */
	public function increment_execution( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_workflows';

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET execution_count = execution_count + 1, last_execution = %s WHERE id = %d",
				current_time( 'mysql' ),
				$id
			)
		);
	}

	/**
	 * Registrar log de execução
	 *
	 * @param array $log_data Dados do log.
	 * @return int|false
	 */
	public function log_execution( $log_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_workflow_logs';

		// Limitar tamanho dos dados para evitar erros de insert
		$trigger_data_json = '{}';
		if ( isset( $log_data['trigger_data'] ) ) {
			$trigger_data_json = wp_json_encode( $log_data['trigger_data'] );
			// Limitar a 65000 chars (longtext suporta mais, mas evitar dados excessivos)
			if ( strlen( $trigger_data_json ) > 65000 ) {
				$trigger_data_json = wp_json_encode( array( 'truncated' => true, 'order_id' => isset( $log_data['trigger_data']['order_id'] ) ? $log_data['trigger_data']['order_id'] : 0 ) );
			}
		}

		$actions_json = '[]';
		if ( isset( $log_data['actions_executed'] ) ) {
			$actions_json = wp_json_encode( $log_data['actions_executed'] );
			if ( strlen( $actions_json ) > 65000 ) {
				$actions_json = wp_json_encode( array( 'truncated' => true ) );
			}
		}

		// Verificar colunas da tabela para inserir apenas as que existem
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
		
		if ( empty( $columns ) ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error( 
					sprintf( 'Tabela %s não encontrada ou sem colunas', $table ), 
					array( 'source' => 'pcw-workflow-scheduler' ) 
				);
			}
			return false;
		}

		$insert_data = array();
		
		// Mapear dados para colunas que existem
		$all_data = array(
			'workflow_id'       => absint( $log_data['workflow_id'] ),
			'trigger_type'      => sanitize_text_field( $log_data['trigger_type'] ),
			'trigger_data'      => $trigger_data_json,
			'context'           => $trigger_data_json,
			'conditions_result' => isset( $log_data['conditions_result'] ) ? (int) $log_data['conditions_result'] : 1,
			'actions_executed'  => $actions_json,
			'result'            => $actions_json,
			'status'            => isset( $log_data['status'] ) ? sanitize_text_field( $log_data['status'] ) : 'success',
			'error_message'     => isset( $log_data['error_message'] ) ? sanitize_textarea_field( $log_data['error_message'] ) : '',
			'execution_time'    => isset( $log_data['execution_time'] ) ? floatval( $log_data['execution_time'] ) : 0,
			'executed_at'       => current_time( 'mysql' ),
			'created_at'        => current_time( 'mysql' ),
		);

		// Inserir apenas colunas que existem na tabela (exceto 'id' que é auto_increment)
		foreach ( $all_data as $key => $value ) {
			if ( in_array( $key, $columns, true ) ) {
				$insert_data[ $key ] = $value;
			}
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info( 
				sprintf( 'Inserindo log workflow #%d - Colunas tabela: [%s] - Colunas insert: [%s]', 
					$log_data['workflow_id'],
					implode( ', ', $columns ),
					implode( ', ', array_keys( $insert_data ) )
				), 
				array( 'source' => 'pcw-workflow-scheduler' ) 
			);
		}

		$result = $wpdb->insert( $table, $insert_data );

		if ( ! $result ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error( 
					sprintf( 'ERRO ao salvar log do workflow #%d: %s | Last query: %s', 
						$insert_data['workflow_id'], 
						$wpdb->last_error,
						$wpdb->last_query
					), 
					array( 'source' => 'pcw-workflow-scheduler' ) 
				);
			}
			return false;
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info( 
				sprintf( 'Log salvo com sucesso! ID: %d, Workflow #%d', $wpdb->insert_id, $log_data['workflow_id'] ), 
				array( 'source' => 'pcw-workflow-scheduler' ) 
			);
		}

		return $wpdb->insert_id;
	}

	/**
	 * Obter logs de um workflow
	 *
	 * @param int   $workflow_id ID do workflow.
	 * @param array $args Argumentos.
	 * @return array
	 */
	public function get_logs( $workflow_id, $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_workflow_logs';

		$defaults = array(
			'limit'  => 50,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE workflow_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$workflow_id,
				$args['limit'],
				$args['offset']
			)
		);

		foreach ( $logs as $log ) {
			$log->trigger_data = json_decode( $log->trigger_data, true );
			$log->actions_executed = json_decode( $log->actions_executed, true );
		}

		return $logs;
	}

	/**
	 * Contar workflows
	 *
	 * @param string $status Status para filtrar.
	 * @return int
	 */
	public function count( $status = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_workflows';

		if ( ! empty( $status ) ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status )
			);
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}
}
