<?php
/**
 * Gerenciamento de listas personalizadas
 *
 * @package PersonCashWallet
 * @since 1.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de listas personalizadas
 */
class PCW_Custom_Lists {

	/**
	 * Criar nova lista
	 *
	 * @param array $data Dados da lista.
	 * @return int|WP_Error ID da lista ou erro.
	 */
	public static function create( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_custom_lists';

		$defaults = array(
			'name'        => '',
			'description' => '',
			'type'        => 'manual',
			'created_by'  => get_current_user_id(),
		);

		$data = wp_parse_args( $data, $defaults );

		if ( empty( $data['name'] ) ) {
			return new WP_Error( 'empty_name', __( 'Nome da lista é obrigatório', 'person-cash-wallet' ) );
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'name'        => sanitize_text_field( $data['name'] ),
				'description' => sanitize_textarea_field( $data['description'] ),
				'type'        => sanitize_text_field( $data['type'] ),
				'created_by'  => absint( $data['created_by'] ),
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Erro ao criar lista', 'person-cash-wallet' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Adicionar membros à lista
	 *
	 * @param int   $list_id ID da lista.
	 * @param array $members Array de membros (email obrigatório).
	 * @return array Resultado com total adicionado e erros.
	 */
	public static function add_members( $list_id, $members ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_list_members';
		$added = 0;
		$skipped = 0;
		$errors = array();

		foreach ( $members as $member ) {
			if ( empty( $member['email'] ) ) {
				$errors[] = 'Email vazio ignorado';
				$skipped++;
				continue;
			}

			$email = sanitize_email( $member['email'] );
			if ( ! is_email( $email ) ) {
				$errors[] = sprintf( 'Email inválido: %s', $member['email'] );
				$skipped++;
				continue;
			}

			// Verificar se já existe
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE list_id = %d AND email = %s",
				$list_id,
				$email
			) );

			if ( $exists ) {
				$skipped++;
				continue;
			}

			// Buscar user_id se existir
			$user = get_user_by( 'email', $email );
			$user_id = $user ? $user->ID : null;

			$inserted = $wpdb->insert(
				$table,
				array(
					'list_id'  => $list_id,
					'user_id'  => $user_id,
					'email'    => $email,
					'name'     => ! empty( $member['name'] ) ? sanitize_text_field( $member['name'] ) : '',
					'phone'    => ! empty( $member['phone'] ) ? sanitize_text_field( $member['phone'] ) : '',
					'metadata' => ! empty( $member['metadata'] ) ? wp_json_encode( $member['metadata'] ) : null,
					'added_at' => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( $inserted ) {
				$added++;
			} else {
				$errors[] = sprintf( 'Erro ao adicionar: %s', $email );
				$skipped++;
			}
		}

		// Atualizar contador
		self::update_member_count( $list_id );

		return array(
			'added'   => $added,
			'skipped' => $skipped,
			'errors'  => $errors,
		);
	}

	/**
	 * Importar de arquivo XLS/XLSX
	 *
	 * @param int    $list_id ID da lista.
	 * @param string $file_path Caminho do arquivo.
	 * @return array|WP_Error Resultado ou erro.
	 */
	public static function import_from_excel( $list_id, $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'Arquivo não encontrado', 'person-cash-wallet' ) );
		}

		// Carregar PhpSpreadsheet se disponível
		if ( ! class_exists( '\\PhpOffice\\PhpSpreadsheet\\IOFactory' ) ) {
			// Tentar usar SimpleXLSX como alternativa
			return self::import_from_excel_simple( $list_id, $file_path );
		}

		try {
			$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $file_path );
			$worksheet = $spreadsheet->getActiveSheet();
			$rows = $worksheet->toArray();

			if ( empty( $rows ) ) {
				return new WP_Error( 'empty_file', __( 'Arquivo vazio', 'person-cash-wallet' ) );
			}

			// Primeira linha são os cabeçalhos
			$headers = array_shift( $rows );
			$headers = array_map( 'strtolower', $headers );

			// Encontrar índices das colunas
			$email_index = array_search( 'email', $headers );
			$name_index = array_search( 'nome', $headers ) !== false ? array_search( 'nome', $headers ) : array_search( 'name', $headers );
			$phone_index = array_search( 'telefone', $headers ) !== false ? array_search( 'telefone', $headers ) : array_search( 'phone', $headers );

			if ( false === $email_index ) {
				return new WP_Error( 'no_email_column', __( 'Coluna "email" não encontrada', 'person-cash-wallet' ) );
			}

			$members = array();
			foreach ( $rows as $row ) {
				if ( empty( $row[ $email_index ] ) ) {
					continue;
				}

				$member = array(
					'email' => $row[ $email_index ],
				);

				if ( false !== $name_index && ! empty( $row[ $name_index ] ) ) {
					$member['name'] = $row[ $name_index ];
				}

				if ( false !== $phone_index && ! empty( $row[ $phone_index ] ) ) {
					$member['phone'] = $row[ $phone_index ];
				}

				$members[] = $member;
			}

			return self::add_members( $list_id, $members );

		} catch ( Exception $e ) {
			return new WP_Error( 'import_error', $e->getMessage() );
		}
	}

	/**
	 * Importar de Excel usando método simples (CSV/TSV)
	 *
	 * @param int    $list_id ID da lista.
	 * @param string $file_path Caminho do arquivo.
	 * @return array|WP_Error Resultado ou erro.
	 */
	private static function import_from_excel_simple( $list_id, $file_path ) {
		$extension = pathinfo( $file_path, PATHINFO_EXTENSION );

		// Se for XLS/XLSX, tentar converter para CSV
		if ( in_array( strtolower( $extension ), array( 'xls', 'xlsx' ), true ) ) {
			return new WP_Error( 'no_library', __( 'Biblioteca PhpSpreadsheet não disponível. Use arquivo CSV.', 'person-cash-wallet' ) );
		}

		// Ler arquivo CSV
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return new WP_Error( 'cannot_open', __( 'Não foi possível abrir o arquivo', 'person-cash-wallet' ) );
		}

		$members = array();
		$headers = fgetcsv( $handle, 0, ',' );
		if ( ! $headers ) {
			fclose( $handle );
			return new WP_Error( 'invalid_format', __( 'Formato de arquivo inválido', 'person-cash-wallet' ) );
		}

		$headers = array_map( 'strtolower', $headers );
		$email_index = array_search( 'email', $headers );
		$name_index = array_search( 'nome', $headers ) !== false ? array_search( 'nome', $headers ) : array_search( 'name', $headers );
		$phone_index = array_search( 'telefone', $headers ) !== false ? array_search( 'telefone', $headers ) : array_search( 'phone', $headers );

		if ( false === $email_index ) {
			fclose( $handle );
			return new WP_Error( 'no_email_column', __( 'Coluna "email" não encontrada', 'person-cash-wallet' ) );
		}

		while ( ( $row = fgetcsv( $handle, 0, ',' ) ) !== false ) {
			if ( empty( $row[ $email_index ] ) ) {
				continue;
			}

			$member = array(
				'email' => $row[ $email_index ],
			);

			if ( false !== $name_index && ! empty( $row[ $name_index ] ) ) {
				$member['name'] = $row[ $name_index ];
			}

			if ( false !== $phone_index && ! empty( $row[ $phone_index ] ) ) {
				$member['phone'] = $row[ $phone_index ];
			}

			$members[] = $member;
		}

		fclose( $handle );

		return self::add_members( $list_id, $members );
	}

	/**
	 * Obter lista por ID
	 *
	 * @param int $list_id ID da lista.
	 * @return object|null
	 */
	public static function get( $list_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_custom_lists';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$list_id
		) );
	}

	/**
	 * Obter todas as listas
	 *
	 * @param array $args Argumentos.
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_custom_lists';

		$defaults = array(
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 50,
		);

		$args = wp_parse_args( $args, $defaults );

		$sql = "SELECT * FROM {$table} ORDER BY {$args['orderby']} {$args['order']} LIMIT {$args['limit']}";

		return $wpdb->get_results( $sql );
	}

	/**
	 * Obter membros de uma lista
	 *
	 * @param int   $list_id ID da lista.
	 * @param array $args Argumentos.
	 * @return array
	 */
	public static function get_members( $list_id, $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_list_members';

		$defaults = array(
			'offset' => 0,
			'limit'  => 100,
		);

		$args = wp_parse_args( $args, $defaults );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE list_id = %d ORDER BY added_at DESC LIMIT %d OFFSET %d",
			$list_id,
			$args['limit'],
			$args['offset']
		) );
	}

	/**
	 * Remover membro da lista
	 *
	 * @param int $list_id ID da lista.
	 * @param int $member_id ID do membro.
	 * @return bool
	 */
	public static function remove_member( $list_id, $member_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_list_members';

		$deleted = $wpdb->delete(
			$table,
			array(
				'id'      => $member_id,
				'list_id' => $list_id,
			),
			array( '%d', '%d' )
		);

		if ( $deleted ) {
			self::update_member_count( $list_id );
		}

		return (bool) $deleted;
	}

	/**
	 * Deletar lista
	 *
	 * @param int $list_id ID da lista.
	 * @return bool
	 */
	public static function delete( $list_id ) {
		global $wpdb;

		$list_table = $wpdb->prefix . 'pcw_custom_lists';
		$members_table = $wpdb->prefix . 'pcw_list_members';

		// Deletar membros primeiro
		$wpdb->delete( $members_table, array( 'list_id' => $list_id ), array( '%d' ) );

		// Deletar lista
		return (bool) $wpdb->delete( $list_table, array( 'id' => $list_id ), array( '%d' ) );
	}

	/**
	 * Atualizar contagem de membros
	 *
	 * @param int $list_id ID da lista.
	 */
	private static function update_member_count( $list_id ) {
		global $wpdb;

		$list_table = $wpdb->prefix . 'pcw_custom_lists';
		$members_table = $wpdb->prefix . 'pcw_list_members';

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$members_table} WHERE list_id = %d",
			$list_id
		) );

		$wpdb->update(
			$list_table,
			array(
				'total_members' => $count,
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => $list_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Obter emails da lista para envio
	 *
	 * @param int $list_id ID da lista.
	 * @return array Array de emails.
	 */
	public static function get_emails_for_send( $list_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_list_members';

		return $wpdb->get_col( $wpdb->prepare(
			"SELECT email FROM {$table} WHERE list_id = %d",
			$list_id
		) );
	}
}
