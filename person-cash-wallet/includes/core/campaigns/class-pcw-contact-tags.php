<?php
/**
 * Gerenciamento de tags de contatos
 *
 * @package PersonCashWallet
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de tags de contatos
 */
class PCW_Contact_Tags {

	/**
	 * Criar nova tag
	 *
	 * @param array $data Dados da tag.
	 * @return int|WP_Error ID da tag ou erro.
	 */
	public static function create( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_contact_tags';

		$defaults = array(
			'name'        => '',
			'description' => '',
			'color'       => '#3b82f6',
			'created_by'  => get_current_user_id(),
		);

		$data = wp_parse_args( $data, $defaults );

		if ( empty( $data['name'] ) ) {
			return new WP_Error( 'empty_name', __( 'Nome da tag é obrigatório', 'person-cash-wallet' ) );
		}

		// Gerar slug
		$slug = sanitize_title( $data['name'] );

		// Verificar se slug já existe
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE slug = %s",
			$slug
		) );

		if ( $exists ) {
			return new WP_Error( 'slug_exists', __( 'Já existe uma tag com este nome', 'person-cash-wallet' ) );
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'name'        => sanitize_text_field( $data['name'] ),
				'slug'        => $slug,
				'description' => sanitize_textarea_field( $data['description'] ),
				'color'       => sanitize_hex_color( $data['color'] ),
				'created_by'  => absint( $data['created_by'] ),
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Erro ao criar tag', 'person-cash-wallet' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Atualizar tag
	 *
	 * @param int   $tag_id ID da tag.
	 * @param array $data Dados para atualizar.
	 * @return bool
	 */
	public static function update( $tag_id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_contact_tags';

		$update_data = array(
			'updated_at' => current_time( 'mysql' ),
		);

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
			$update_data['slug'] = sanitize_title( $data['name'] );
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = sanitize_textarea_field( $data['description'] );
		}

		if ( isset( $data['color'] ) ) {
			$update_data['color'] = sanitize_hex_color( $data['color'] );
		}

		$updated = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $tag_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Deletar tag
	 *
	 * @param int $tag_id ID da tag.
	 * @return bool
	 */
	public static function delete( $tag_id ) {
		global $wpdb;

		$tag_table = $wpdb->prefix . 'pcw_contact_tags';
		$relations_table = $wpdb->prefix . 'pcw_contact_tag_relations';

		// Deletar relações primeiro
		$wpdb->delete( $relations_table, array( 'tag_id' => $tag_id ), array( '%d' ) );

		// Deletar tag
		return (bool) $wpdb->delete( $tag_table, array( 'id' => $tag_id ), array( '%d' ) );
	}

	/**
	 * Obter tag por ID
	 *
	 * @param int $tag_id ID da tag.
	 * @return object|null
	 */
	public static function get( $tag_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_contact_tags';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$tag_id
		) );
	}

	/**
	 * Obter todas as tags
	 *
	 * @param array $args Argumentos.
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_contact_tags';

		$defaults = array(
			'orderby' => 'name',
			'order'   => 'ASC',
			'limit'   => 100,
		);

		$args = wp_parse_args( $args, $defaults );

		$sql = "SELECT * FROM {$table} ORDER BY {$args['orderby']} {$args['order']} LIMIT {$args['limit']}";

		return $wpdb->get_results( $sql );
	}

	/**
	 * Adicionar tag a um contato
	 *
	 * @param int $tag_id ID da tag.
	 * @param int $member_id ID do membro.
	 * @param int $list_id ID da lista.
	 * @return bool
	 */
	public static function add_to_contact( $tag_id, $member_id, $list_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_contact_tag_relations';

		// Verificar se já existe
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE tag_id = %d AND member_id = %d",
			$tag_id,
			$member_id
		) );

		if ( $exists ) {
			return true;
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'tag_id'    => $tag_id,
				'member_id' => $member_id,
				'list_id'   => $list_id,
				'added_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s' )
		);

		if ( $inserted ) {
			self::update_contact_count( $tag_id );
		}

		return (bool) $inserted;
	}

	/**
	 * Remover tag de um contato
	 *
	 * @param int $tag_id ID da tag.
	 * @param int $member_id ID do membro.
	 * @return bool
	 */
	public static function remove_from_contact( $tag_id, $member_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_contact_tag_relations';

		$deleted = $wpdb->delete(
			$table,
			array(
				'tag_id'    => $tag_id,
				'member_id' => $member_id,
			),
			array( '%d', '%d' )
		);

		if ( $deleted ) {
			self::update_contact_count( $tag_id );
		}

		return (bool) $deleted;
	}

	/**
	 * Obter tags de um contato
	 *
	 * @param int $member_id ID do membro.
	 * @return array
	 */
	public static function get_contact_tags( $member_id ) {
		global $wpdb;

		$tags_table = $wpdb->prefix . 'pcw_contact_tags';
		$relations_table = $wpdb->prefix . 'pcw_contact_tag_relations';

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT t.* FROM {$tags_table} t
			INNER JOIN {$relations_table} r ON t.id = r.tag_id
			WHERE r.member_id = %d
			ORDER BY t.name ASC",
			$member_id
		) );
	}

	/**
	 * Obter contatos de uma tag
	 *
	 * @param int   $tag_id ID da tag.
	 * @param array $args Argumentos.
	 * @return array
	 */
	public static function get_tag_contacts( $tag_id, $args = array() ) {
		global $wpdb;

		$members_table = $wpdb->prefix . 'pcw_list_members';
		$relations_table = $wpdb->prefix . 'pcw_contact_tag_relations';

		$defaults = array(
			'offset' => 0,
			'limit'  => 100,
		);

		$args = wp_parse_args( $args, $defaults );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT m.* FROM {$members_table} m
			INNER JOIN {$relations_table} r ON m.id = r.member_id
			WHERE r.tag_id = %d
			ORDER BY m.added_at DESC
			LIMIT %d OFFSET %d",
			$tag_id,
			$args['limit'],
			$args['offset']
		) );
	}

	/**
	 * Atualizar contagem de contatos
	 *
	 * @param int $tag_id ID da tag.
	 */
	private static function update_contact_count( $tag_id ) {
		global $wpdb;

		$tag_table = $wpdb->prefix . 'pcw_contact_tags';
		$relations_table = $wpdb->prefix . 'pcw_contact_tag_relations';

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$relations_table} WHERE tag_id = %d",
			$tag_id
		) );

		$wpdb->update(
			$tag_table,
			array(
				'total_contacts' => $count,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $tag_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Adicionar tags em massa a contatos
	 *
	 * @param array $tag_ids IDs das tags.
	 * @param array $member_ids IDs dos membros.
	 * @param int   $list_id ID da lista.
	 * @return int Número de tags adicionadas.
	 */
	public static function bulk_add_tags( $tag_ids, $member_ids, $list_id ) {
		$added = 0;

		foreach ( $tag_ids as $tag_id ) {
			foreach ( $member_ids as $member_id ) {
				if ( self::add_to_contact( $tag_id, $member_id, $list_id ) ) {
					$added++;
				}
			}
		}

		return $added;
	}

	/**
	 * Remover tags em massa de contatos
	 *
	 * @param array $tag_ids IDs das tags.
	 * @param array $member_ids IDs dos membros.
	 * @return int Número de tags removidas.
	 */
	public static function bulk_remove_tags( $tag_ids, $member_ids ) {
		$removed = 0;

		foreach ( $tag_ids as $tag_id ) {
			foreach ( $member_ids as $member_id ) {
				if ( self::remove_from_contact( $tag_id, $member_id ) ) {
					$removed++;
				}
			}
		}

		return $removed;
	}
}
