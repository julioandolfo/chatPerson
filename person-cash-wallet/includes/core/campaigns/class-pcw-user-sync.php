<?php
/**
 * Sincronização de usuários WordPress com listas
 *
 * @package PersonCashWallet
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de sincronização de usuários
 */
class PCW_User_Sync {

	/**
	 * Instância singleton
	 *
	 * @var PCW_User_Sync
	 */
	private static $instance = null;

	/**
	 * Obter instância
	 *
	 * @return PCW_User_Sync
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
	 * Inicializar
	 */
	public function init() {
		// Sincronizar automaticamente novos usuários
		add_action( 'user_register', array( $this, 'sync_new_user' ), 10, 1 );

		// Sincronizar quando usuário atualiza perfil
		add_action( 'profile_update', array( $this, 'sync_updated_user' ), 10, 2 );
	}

	/**
	 * Sincronizar novo usuário
	 *
	 * @param int $user_id ID do usuário.
	 */
	public function sync_new_user( $user_id ) {
		$settings = $this->get_sync_settings();

		if ( empty( $settings['auto_sync_new_users'] ) || 'yes' !== $settings['auto_sync_new_users'] ) {
			return;
		}

		$list_id = ! empty( $settings['default_list_id'] ) ? absint( $settings['default_list_id'] ) : 0;

		if ( $list_id <= 0 ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return;
		}

		// Verificar se a função do usuário está nas permitidas
		$allowed_roles = ! empty( $settings['allowed_roles'] ) ? $settings['allowed_roles'] : array();

		if ( ! empty( $allowed_roles ) && ! array_intersect( $user->roles, $allowed_roles ) ) {
			return;
		}

		// Adicionar à lista
		$this->add_user_to_list( $user, $list_id );
	}

	/**
	 * Sincronizar usuário atualizado
	 *
	 * @param int      $user_id ID do usuário.
	 * @param WP_User  $old_user_data Dados antigos do usuário.
	 */
	public function sync_updated_user( $user_id, $old_user_data ) {
		$settings = $this->get_sync_settings();

		if ( empty( $settings['auto_sync_updates'] ) || 'yes' !== $settings['auto_sync_updates'] ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return;
		}

		// Atualizar informações nas listas
		$this->update_user_in_lists( $user );
	}

	/**
	 * Sincronizar todos os usuários
	 *
	 * @param int   $list_id ID da lista.
	 * @param array $args Argumentos.
	 * @return array Resultado da sincronização.
	 */
	public function sync_all_users( $list_id, $args = array() ) {
		$defaults = array(
			'roles'  => array(),
			'limit'  => 0,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// Buscar usuários
		$user_args = array(
			'fields' => 'all',
		);

		if ( ! empty( $args['roles'] ) ) {
			$user_args['role__in'] = $args['roles'];
		}

		if ( $args['limit'] > 0 ) {
			$user_args['number'] = $args['limit'];
			$user_args['offset'] = $args['offset'];
		}

		$users = get_users( $user_args );

		$added = 0;
		$skipped = 0;
		$errors = array();

		foreach ( $users as $user ) {
			$result = $this->add_user_to_list( $user, $list_id );

			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
				$skipped++;
			} elseif ( $result ) {
				$added++;
			} else {
				$skipped++;
			}
		}

		return array(
			'success' => true,
			'added'   => $added,
			'skipped' => $skipped,
			'total'   => count( $users ),
			'errors'  => $errors,
		);
	}

	/**
	 * Adicionar usuário a uma lista
	 *
	 * @param WP_User $user Usuário.
	 * @param int     $list_id ID da lista.
	 * @return bool|WP_Error
	 */
	private function add_user_to_list( $user, $list_id ) {
		$members = array(
			array(
				'email' => $user->user_email,
				'name'  => $user->display_name,
				'metadata' => array(
					'user_id'    => $user->ID,
					'user_login' => $user->user_login,
					'user_roles' => implode( ', ', $user->roles ),
					'synced_at'  => current_time( 'mysql' ),
					'source'     => 'user_sync',
				),
			),
		);

		$result = PCW_Custom_Lists::add_members( $list_id, $members );

		if ( isset( $result['errors'] ) && ! empty( $result['errors'] ) ) {
			return new WP_Error( 'sync_error', implode( ', ', $result['errors'] ) );
		}

		return $result['added'] > 0;
	}

	/**
	 * Atualizar usuário nas listas
	 *
	 * @param WP_User $user Usuário.
	 */
	private function update_user_in_lists( $user ) {
		global $wpdb;

		$members_table = $wpdb->prefix . 'pcw_list_members';

		// Buscar membros do usuário
		$members = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$members_table} WHERE user_id = %d OR email = %s",
			$user->ID,
			$user->user_email
		) );

		foreach ( $members as $member ) {
			// Atualizar metadados
			$metadata = ! empty( $member->metadata ) ? json_decode( $member->metadata, true ) : array();
			$metadata['user_login'] = $user->user_login;
			$metadata['user_roles'] = implode( ', ', $user->roles );
			$metadata['updated_at'] = current_time( 'mysql' );

			$wpdb->update(
				$members_table,
				array(
					'name'     => $user->display_name,
					'metadata' => wp_json_encode( $metadata ),
				),
				array( 'id' => $member->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Sincronizar clientes WooCommerce
	 *
	 * @param int   $list_id ID da lista.
	 * @param array $args Argumentos.
	 * @return array Resultado da sincronização.
	 */
	public function sync_woocommerce_customers( $list_id, $args = array() ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'success' => false,
				'message' => __( 'WooCommerce não está ativo', 'person-cash-wallet' ),
			);
		}

		$defaults = array(
			'min_orders'       => 0,
			'min_spent'        => 0,
			'order_status'     => array( 'completed', 'processing' ),
			'date_from'        => '',
			'date_to'          => '',
			'limit'            => 0,
			'offset'           => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		global $wpdb;

		// Buscar clientes
		$sql = "
			SELECT DISTINCT pm.meta_value as user_id
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_user'
			WHERE p.post_type = 'shop_order'
		";

		$where = array( 'pm.meta_value > 0' );

		if ( ! empty( $args['order_status'] ) ) {
			$statuses = array_map( function( $status ) {
				return 'wc-' . $status;
			}, $args['order_status'] );
			$where[] = "p.post_status IN ('" . implode( "','", array_map( 'esc_sql', $statuses ) ) . "')";
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[] = $wpdb->prepare( 'p.post_date >= %s', $args['date_from'] );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[] = $wpdb->prepare( 'p.post_date <= %s', $args['date_to'] );
		}

		$sql .= ' AND ' . implode( ' AND ', $where );

		if ( $args['limit'] > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );
		}

		$user_ids = $wpdb->get_col( $sql );

		$added = 0;
		$skipped = 0;

		foreach ( $user_ids as $user_id ) {
			$user = get_user_by( 'id', $user_id );

			if ( ! $user ) {
				$skipped++;
				continue;
			}

			// Verificar filtros adicionais
			if ( $args['min_orders'] > 0 || $args['min_spent'] > 0 ) {
				$customer_orders = wc_get_customer_order_count( $user_id );
				$customer_spent = wc_get_customer_total_spent( $user_id );

				if ( $customer_orders < $args['min_orders'] || $customer_spent < $args['min_spent'] ) {
					$skipped++;
					continue;
				}
			}

			$result = $this->add_user_to_list( $user, $list_id );

			if ( $result ) {
				$added++;
			} else {
				$skipped++;
			}
		}

		return array(
			'success' => true,
			'added'   => $added,
			'skipped' => $skipped,
			'total'   => count( $user_ids ),
		);
	}

	/**
	 * Obter configurações de sincronização
	 *
	 * @return array
	 */
	private function get_sync_settings() {
		return get_option( 'pcw_user_sync_settings', array() );
	}

	/**
	 * Salvar configurações de sincronização
	 *
	 * @param array $settings Configurações.
	 * @return bool
	 */
	public function save_sync_settings( $settings ) {
		$defaults = array(
			'auto_sync_new_users' => 'no',
			'auto_sync_updates'   => 'no',
			'default_list_id'     => 0,
			'allowed_roles'       => array(),
		);

		$settings = wp_parse_args( $settings, $defaults );

		return update_option( 'pcw_user_sync_settings', $settings );
	}
}
