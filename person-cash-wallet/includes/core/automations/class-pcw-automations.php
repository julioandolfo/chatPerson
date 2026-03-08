<?php
/**
 * Gerenciador de Automações
 *
 * @package GrowlyDigital
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de gerenciamento de automações
 */
class PCW_Automations {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Automations
	 */
	private static $instance = null;

	/**
	 * Obter instância
	 *
	 * @return PCW_Automations
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
	 * Obter tipos de automação disponíveis
	 *
	 * @return array
	 */
	public static function get_automation_types() {
		return array(
			'product_viewed' => array(
				'name'        => __( 'Produtos Visualizados', 'person-cash-wallet' ),
				'description' => __( 'Envie um lembrete sobre os produtos que os clientes visualizaram, mas não compraram.', 'person-cash-wallet' ),
				'icon'        => 'dashicons-visibility',
				'color'       => '#667eea',
				'channels'    => array( 'email', 'whatsapp' ),
				'trigger'     => 'product_view',
			),
			'customer_recovery' => array(
				'name'        => __( 'Recuperação de Clientes', 'person-cash-wallet' ),
				'description' => __( 'Traga seus clientes de volta com campanhas de remarketing personalizadas.', 'person-cash-wallet' ),
				'icon'        => 'dashicons-groups',
				'color'       => '#f59e0b',
				'channels'    => array( 'email', 'whatsapp' ),
				'trigger'     => 'inactive_customer',
			),
			'recommended_products' => array(
				'name'        => __( 'Produtos Recomendados', 'person-cash-wallet' ),
				'description' => __( 'Sugira produtos aos clientes com base em compras anteriores e itens visualizados.', 'person-cash-wallet' ),
				'icon'        => 'dashicons-thumbs-up',
				'color'       => '#10b981',
				'channels'    => array( 'email' ),
				'trigger'     => 'order_completed',
			),
			'abandoned_cart' => array(
				'name'        => __( 'Carrinho Abandonado', 'person-cash-wallet' ),
				'description' => __( 'Não perca a oportunidade de recuperar carrinhos abandonados com lembretes automáticos.', 'person-cash-wallet' ),
				'icon'        => 'dashicons-cart',
				'color'       => '#ef4444',
				'channels'    => array( 'email', 'sms', 'whatsapp' ),
				'trigger'     => 'cart_abandoned',
			),
			'welcome' => array(
				'name'        => __( 'Boas-vindas aos Novos Inscritos', 'person-cash-wallet' ),
				'description' => __( 'Incentive os visitantes a se inscreverem na sua campanha para receber novidades.', 'person-cash-wallet' ),
				'icon'        => 'dashicons-admin-users',
				'color'       => '#8b5cf6',
				'channels'    => array( 'email' ),
				'trigger'     => 'user_registered',
			),
			'post_purchase' => array(
				'name'        => __( 'Pós-venda', 'person-cash-wallet' ),
				'description' => __( 'Desenvolva a lealdade com mensagens automáticas de pós-compra que agradecem e sugerem produtos.', 'person-cash-wallet' ),
				'icon'        => 'dashicons-heart',
				'color'       => '#ec4899',
				'channels'    => array( 'email', 'whatsapp' ),
				'trigger'     => 'order_completed',
			),
			'new_products' => array(
				'name'        => __( 'Lançamentos', 'person-cash-wallet' ),
				'description' => __( 'Avise seus clientes sobre lançamentos em categorias que eles acessam com frequência.', 'person-cash-wallet' ),
				'icon'        => 'dashicons-star-filled',
				'color'       => '#06b6d4',
				'channels'    => array( 'email' ),
				'trigger'     => 'new_product',
			),
			'cashback_earned' => array(
				'name'        => __( 'Cashback Ganho', 'person-cash-wallet' ),
				'description' => __( 'Notifique os clientes quando eles ganham cashback para incentivar novas compras.', 'person-cash-wallet' ),
				'icon'        => 'dashicons-money-alt',
				'color'       => '#22c55e',
				'channels'    => array( 'email', 'whatsapp' ),
				'trigger'     => 'cashback_earned',
			),
			'cashback_expiring' => array(
				'name'        => __( 'Cashback Expirando', 'person-cash-wallet' ),
				'description' => __( 'Lembre os clientes que seu cashback está expirando para criar urgência.', 'person-cash-wallet' ),
				'icon'        => 'dashicons-clock',
				'color'       => '#f97316',
				'channels'    => array( 'email', 'whatsapp' ),
				'trigger'     => 'cashback_expiring',
			),
			'level_achieved' => array(
				'name'        => __( 'Novo Nível Alcançado', 'person-cash-wallet' ),
				'description' => __( 'Parabenize os clientes quando alcançam um novo nível no programa de fidelidade.', 'person-cash-wallet' ),
				'icon'        => 'dashicons-awards',
				'color'       => '#eab308',
				'channels'    => array( 'email', 'whatsapp' ),
				'trigger'     => 'level_achieved',
			),
		);
	}

	/**
	 * Criar automação
	 *
	 * @param array $data Dados da automação.
	 * @return int|false
	 */
	public function create( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_automations';

		$insert_data = array(
			'name'            => sanitize_text_field( $data['name'] ),
			'description'     => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'type'            => sanitize_text_field( $data['type'] ),
			'status'          => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'active',
			'trigger_type'    => sanitize_text_field( $data['trigger_type'] ),
			'trigger_config'  => isset( $data['trigger_config'] ) ? wp_json_encode( $data['trigger_config'] ) : '{}',
			'workflow_steps'  => isset( $data['workflow_steps'] ) ? wp_json_encode( $data['workflow_steps'] ) : '[]',
			'email_template'  => isset( $data['email_template'] ) ? wp_kses_post( $data['email_template'] ) : '',
			'email_subject'   => isset( $data['email_subject'] ) ? sanitize_text_field( $data['email_subject'] ) : '',
			'use_ai_subject'  => isset( $data['use_ai_subject'] ) ? (int) $data['use_ai_subject'] : 0,
			'channels'        => isset( $data['channels'] ) ? sanitize_text_field( $data['channels'] ) : 'email',
			'created_by'      => get_current_user_id(),
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $insert_data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Atualizar automação
	 *
	 * @param int   $id ID da automação.
	 * @param array $data Dados para atualizar.
	 * @return bool
	 */
	public function update( $id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_automations';

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
		if ( isset( $data['trigger_config'] ) ) {
			$update_data['trigger_config'] = wp_json_encode( $data['trigger_config'] );
		}
		if ( isset( $data['workflow_steps'] ) ) {
			$update_data['workflow_steps'] = wp_json_encode( $data['workflow_steps'] );
		}
		if ( isset( $data['email_template'] ) ) {
			$update_data['email_template'] = wp_kses_post( $data['email_template'] );
		}
		if ( isset( $data['email_subject'] ) ) {
			$update_data['email_subject'] = sanitize_text_field( $data['email_subject'] );
		}
		if ( isset( $data['use_ai_subject'] ) ) {
			$update_data['use_ai_subject'] = (int) $data['use_ai_subject'];
		}
		if ( isset( $data['channels'] ) ) {
			$update_data['channels'] = sanitize_text_field( $data['channels'] );
		}

		$result = $wpdb->update( $table, $update_data, array( 'id' => $id ) );

		return false !== $result;
	}

	/**
	 * Deletar automação
	 *
	 * @param int $id ID da automação.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_automations';

		$result = $wpdb->delete( $table, array( 'id' => $id ) );

		return false !== $result;
	}

	/**
	 * Obter automação por ID
	 *
	 * @param int $id ID da automação.
	 * @return object|null
	 */
	public function get( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_automations';

		$automation = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		if ( $automation ) {
			$automation->trigger_config = json_decode( $automation->trigger_config, true );
			$automation->workflow_steps = json_decode( $automation->workflow_steps, true );
		}

		return $automation;
	}

	/**
	 * Obter todas as automações
	 *
	 * @param array $args Argumentos.
	 * @return array
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_automations';

		$defaults = array(
			'status' => '',
			'type'   => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where .= ' AND status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['type'] ) ) {
			$where .= ' AND type = %s';
			$params[] = $args['type'];
		}

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY name ASC";

		if ( ! empty( $params ) ) {
			$automations = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		} else {
			$automations = $wpdb->get_results( $sql );
		}

		foreach ( $automations as $automation ) {
			$automation->trigger_config = json_decode( $automation->trigger_config, true );
			$automation->workflow_steps = json_decode( $automation->workflow_steps, true );
		}

		return $automations;
	}

	/**
	 * Obter automações ativas por tipo de gatilho
	 *
	 * @param string $trigger_type Tipo de gatilho.
	 * @return array
	 */
	public function get_active_by_trigger( $trigger_type ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_automations';

		$automations = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = 'active' AND trigger_type = %s ORDER BY name ASC",
			$trigger_type
		) );

		foreach ( $automations as $automation ) {
			$automation->trigger_config = json_decode( $automation->trigger_config, true );
			$automation->workflow_steps = json_decode( $automation->workflow_steps, true );
		}

		return $automations;
	}

	/**
	 * Incrementar estatísticas
	 *
	 * @param int    $id ID da automação.
	 * @param string $stat Nome da estatística (sent, opened, clicked, converted).
	 */
	public function increment_stat( $id, $stat ) {
		global $wpdb;

		$column = 'stats_' . sanitize_key( $stat );
		$allowed = array( 'stats_sent', 'stats_opened', 'stats_clicked', 'stats_converted' );

		if ( ! in_array( $column, $allowed, true ) ) {
			return;
		}

		$table = $wpdb->prefix . 'pcw_automations';

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET {$column} = {$column} + 1 WHERE id = %d",
			$id
		) );
	}

	/**
	 * Contar automações
	 *
	 * @param string $status Status.
	 * @return int
	 */
	public function count( $status = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_automations';

		if ( ! empty( $status ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s",
				$status
			) );
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}
}
