<?php
/**
 * Classe principal de níveis
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de níveis
 */
class PCW_Levels {

	/**
	 * Obter todos os níveis
	 *
	 * @param array $args Argumentos.
	 * @return array
	 */
	public static function get_all_levels( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_levels';

		$defaults = array(
			'status' => 'active',
			'order'  => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array();
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$order_by    = sanitize_sql_orderby( "level_number {$args['order']}" );

		$query = "SELECT * FROM {$table} {$where_clause} ORDER BY {$order_by}";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		return $wpdb->get_results( $query );
	}

	/**
	 * Obter nível por ID
	 *
	 * @param int $level_id ID do nível.
	 * @return object|null
	 */
	public static function get_level( $level_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_levels';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				absint( $level_id )
			)
		);
	}

	/**
	 * Obter nível por slug
	 *
	 * @param string $slug Slug do nível.
	 * @return object|null
	 */
	public static function get_level_by_slug( $slug ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_levels';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE slug = %s",
				sanitize_title( $slug )
			)
		);
	}

	/**
	 * Criar nível
	 *
	 * @param array $data Dados do nível.
	 * @return int|false ID do nível ou false
	 */
	public static function create( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_levels';

		$defaults = array(
			'name'         => '',
			'slug'         => '',
			'level_number' => 1,
			'badge_image'  => '',
			'color'        => '#000000',
			'description'  => '',
			'status'       => 'active',
		);

		$data = wp_parse_args( $data, $defaults );

		// Gerar slug se não fornecido
		if ( empty( $data['slug'] ) && ! empty( $data['name'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		}

		$insert_data = array(
			'name'         => sanitize_text_field( $data['name'] ),
			'slug'         => sanitize_title( $data['slug'] ),
			'level_number' => absint( $data['level_number'] ),
			'badge_image'  => esc_url_raw( $data['badge_image'] ),
			'color'        => sanitize_hex_color( $data['color'] ),
			'description'  => sanitize_textarea_field( $data['description'] ),
			'status'       => sanitize_text_field( $data['status'] ),
			'created_at'   => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $insert_data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Atualizar nível
	 *
	 * @param int   $level_id ID do nível.
	 * @param array $data Dados para atualizar.
	 * @return bool
	 */
	public static function update( $level_id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_levels';

		$update_data = array(
			'updated_at' => current_time( 'mysql' ),
		);

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
		}

		if ( isset( $data['slug'] ) ) {
			$update_data['slug'] = sanitize_title( $data['slug'] );
		}

		if ( isset( $data['level_number'] ) ) {
			$update_data['level_number'] = absint( $data['level_number'] );
		}

		if ( isset( $data['badge_image'] ) ) {
			$update_data['badge_image'] = esc_url_raw( $data['badge_image'] );
		}

		if ( isset( $data['color'] ) ) {
			$update_data['color'] = sanitize_hex_color( $data['color'] );
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = sanitize_textarea_field( $data['description'] );
		}

		if ( isset( $data['status'] ) ) {
			$update_data['status'] = sanitize_text_field( $data['status'] );
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => absint( $level_id ) ),
			null,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Excluir nível
	 *
	 * @param int $level_id ID do nível.
	 * @return bool
	 */
	public static function delete( $level_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_levels';

		// Não excluir fisicamente, apenas desativar
		return self::update( $level_id, array( 'status' => 'inactive' ) );
	}

	/**
	 * Obter nível atual do usuário
	 *
	 * @param int $user_id ID do usuário.
	 * @return object|null
	 */
	public static function get_user_level( $user_id ) {
		global $wpdb;

		$user_levels_table = $wpdb->prefix . 'pcw_user_levels';
		$levels_table = $wpdb->prefix . 'pcw_levels';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT l.*, ul.achieved_date, ul.expires_date, ul.status as user_level_status
				FROM {$user_levels_table} ul
				INNER JOIN {$levels_table} l ON ul.level_id = l.id
				WHERE ul.user_id = %d
				AND ul.status = 'active'
				ORDER BY l.level_number DESC
				LIMIT 1",
				absint( $user_id )
			)
		);
	}

	/**
	 * Atribuir nível ao usuário
	 *
	 * @param int $user_id ID do usuário.
	 * @param int $level_id ID do nível.
	 * @return int|false ID do registro ou false
	 */
	public static function assign_level( $user_id, $level_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_user_levels';

		// Verificar se já tem este nível
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND level_id = %d",
				absint( $user_id ),
				absint( $level_id )
			)
		);

		if ( $existing ) {
			// Atualizar se estava inativo
			if ( 'inactive' === $existing->status || 'expired' === $existing->status ) {
				$wpdb->update(
					$table,
					array(
						'status'            => 'active',
						'achieved_date'     => current_time( 'mysql' ),
						'last_renewal_date' => current_time( 'mysql' ),
						'updated_at'        => current_time( 'mysql' ),
					),
					array( 'id' => $existing->id ),
					array( '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);
				return $existing->id;
			}
			return $existing->id;
		}

		// Buscar informações do nível para expiração
		$level = self::get_level( $level_id );
		$expires_date = null;

		// Calcular data de expiração se necessário (será implementado na classe de expiração)
		// Por enquanto, nunca expira

		$data = array(
			'user_id'         => absint( $user_id ),
			'level_id'        => absint( $level_id ),
			'achieved_date'   => current_time( 'mysql' ),
			'expires_date'    => $expires_date,
			'expiration_type' => 'never',
			'status'          => 'active',
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $data );

		if ( $result ) {
			$user_level_id = $wpdb->insert_id;

			// Disparar ação
			do_action( 'pcw_level_assigned', $user_id, $level_id, $user_level_id );

			// Enviar email
			if ( 'yes' === get_option( 'pcw_notifications_enabled', 'yes' ) ) {
				require_once PCW_PLUGIN_DIR . 'includes/core/notifications/class-pcw-email-handler.php';
				PCW_Email_Handler::send_level_updated( $user_id, $level_id, 'assigned' );
			}

			return $user_level_id;
		}

		return false;
	}

	/**
	 * Remover nível do usuário
	 *
	 * @param int $user_id ID do usuário.
	 * @param int $level_id ID do nível.
	 * @return bool
	 */
	public static function remove_user_level( $user_id, $level_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_user_levels';

		$result = $wpdb->update(
			$table,
			array(
				'status'     => 'expired',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'user_id'  => absint( $user_id ),
				'level_id' => absint( $level_id ),
			),
			array( '%s', '%s' ),
			array( '%d', '%d' )
		);

		if ( false !== $result ) {
			do_action( 'pcw_level_removed', $user_id, $level_id );
		}

		return false !== $result;
	}
}
