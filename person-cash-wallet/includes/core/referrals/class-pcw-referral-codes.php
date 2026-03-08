<?php
/**
 * Classe de gerenciamento de códigos de indicação
 *
 * @package PersonCashWallet
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de códigos de indicação
 */
class PCW_Referral_Codes {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Referral_Codes
	 */
	private static $instance = null;

	/**
	 * Obter instância singleton
	 *
	 * @return PCW_Referral_Codes
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
		// Singleton
	}

	/**
	 * Obter ou criar código para um usuário
	 *
	 * @param int $user_id ID do usuário.
	 * @return object|false Dados do código ou false em caso de erro.
	 */
	public function get_or_create_code( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return false;
		}

		// Verificar se já existe código para o usuário
		$existing = $this->get_code_by_user( $user_id );

		if ( $existing ) {
			return $existing;
		}

		// Criar novo código
		return $this->create_code( $user_id );
	}

	/**
	 * Criar código de indicação para um usuário
	 *
	 * @param int $user_id ID do usuário.
	 * @return object|false Dados do código ou false em caso de erro.
	 */
	public function create_code( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return false;
		}

		// Verificar se já existe
		$existing = $this->get_code_by_user( $user_id );
		if ( $existing ) {
			return $existing;
		}

		// Gerar código único
		$code = $this->generate_unique_code( $user_id );

		$table = $wpdb->prefix . 'pcw_referral_codes';

		$data = array(
			'user_id'          => $user_id,
			'code'             => $code,
			'total_referrals'  => 0,
			'total_conversions' => 0,
			'total_earned'     => 0,
			'status'           => 'active',
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
		);

		$result = $wpdb->insert(
			$table,
			$data,
			array( '%d', '%s', '%d', '%d', '%f', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		$code_id = $wpdb->insert_id;

		// Disparar ação
		do_action( 'pcw_referral_code_created', $code_id, $user_id, $code );

		return $this->get_code( $code_id );
	}

	/**
	 * Gerar código único
	 *
	 * @param int $user_id ID do usuário.
	 * @return string Código gerado.
	 */
	private function generate_unique_code( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_codes';
		$user  = get_userdata( $user_id );
		$attempts = 0;
		$max_attempts = 10;

		do {
			// Gerar código baseado no nome do usuário + números aleatórios
			if ( $user && ! empty( $user->first_name ) ) {
				// Usar primeira parte do primeiro nome (até 4 caracteres)
				$name_part = strtoupper( substr( sanitize_title( $user->first_name ), 0, 4 ) );
			} elseif ( $user && ! empty( $user->display_name ) ) {
				$name_part = strtoupper( substr( sanitize_title( $user->display_name ), 0, 4 ) );
			} else {
				$name_part = 'REF';
			}

			// Adicionar números aleatórios
			$random_part = wp_rand( 1000, 9999 );
			$code = $name_part . $random_part;

			// Verificar se já existe
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE code = %s",
					$code
				)
			);

			$attempts++;

		} while ( $exists && $attempts < $max_attempts );

		// Se após várias tentativas ainda houver colisão, usar hash
		if ( $exists ) {
			$code = strtoupper( substr( md5( $user_id . time() . wp_rand() ), 0, 8 ) );
		}

		return $code;
	}

	/**
	 * Obter código por ID
	 *
	 * @param int $code_id ID do código.
	 * @return object|null Dados do código ou null.
	 */
	public function get_code( $code_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_codes';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				absint( $code_id )
			)
		);
	}

	/**
	 * Obter código por usuário
	 *
	 * @param int $user_id ID do usuário.
	 * @return object|null Dados do código ou null.
	 */
	public function get_code_by_user( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_codes';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d",
				absint( $user_id )
			)
		);
	}

	/**
	 * Obter código por string do código
	 *
	 * @param string $code Código de indicação.
	 * @return object|null Dados do código ou null.
	 */
	public function get_code_by_code( $code ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_codes';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE code = %s AND status = 'active'",
				strtoupper( sanitize_text_field( $code ) )
			)
		);
	}

	/**
	 * Validar código de indicação
	 *
	 * @param string $code Código de indicação.
	 * @param int    $user_id ID do usuário que está usando (opcional, para evitar auto-indicação).
	 * @return array Array com 'valid' (bool) e 'message' (string) e 'data' (object|null).
	 */
	public function validate_code( $code, $user_id = 0 ) {
		$code = strtoupper( sanitize_text_field( $code ) );

		if ( empty( $code ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Código de indicação não informado.', 'person-cash-wallet' ),
				'data'    => null,
			);
		}

		$code_data = $this->get_code_by_code( $code );

		if ( ! $code_data ) {
			return array(
				'valid'   => false,
				'message' => __( 'Código de indicação inválido.', 'person-cash-wallet' ),
				'data'    => null,
			);
		}

		if ( 'active' !== $code_data->status ) {
			return array(
				'valid'   => false,
				'message' => __( 'Código de indicação inativo.', 'person-cash-wallet' ),
				'data'    => null,
			);
		}

		// Verificar auto-indicação
		if ( $user_id && absint( $user_id ) === absint( $code_data->user_id ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Você não pode usar seu próprio código de indicação.', 'person-cash-wallet' ),
				'data'    => null,
			);
		}

		// Obter nome do indicador
		$referrer = get_userdata( $code_data->user_id );
		$referrer_name = '';

		if ( $referrer ) {
			if ( ! empty( $referrer->first_name ) ) {
				// Exibir apenas primeiro nome + inicial do sobrenome
				$first_name = $referrer->first_name;
				$last_initial = ! empty( $referrer->last_name ) ? strtoupper( substr( $referrer->last_name, 0, 1 ) ) . '.' : '';
				$referrer_name = $first_name . ( $last_initial ? ' ' . $last_initial : '' );
			} else {
				$referrer_name = $referrer->display_name;
			}
		}

		return array(
			'valid'        => true,
			'message'      => sprintf(
				/* translators: %s: Nome do indicador */
				__( 'Indicado por: %s', 'person-cash-wallet' ),
				esc_html( $referrer_name )
			),
			'data'         => $code_data,
			'referrer_name' => $referrer_name,
		);
	}

	/**
	 * Obter link de indicação do usuário
	 *
	 * @param int $user_id ID do usuário.
	 * @return string|false URL do link ou false.
	 */
	public function get_referral_link( $user_id ) {
		$code_data = $this->get_or_create_code( $user_id );

		if ( ! $code_data ) {
			return false;
		}

		$base_url = home_url();

		return add_query_arg( 'ref', $code_data->code, $base_url );
	}

	/**
	 * Incrementar contadores do código
	 *
	 * @param int    $code_id ID do código.
	 * @param string $field Campo a incrementar: 'referrals', 'conversions'.
	 * @param float  $earned_amount Valor ganho (opcional, para 'conversions').
	 * @return bool
	 */
	public function increment_counter( $code_id, $field, $earned_amount = 0 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_codes';
		$code_id = absint( $code_id );

		$update_data = array(
			'updated_at' => current_time( 'mysql' ),
		);

		$update_format = array( '%s' );

		switch ( $field ) {
			case 'referrals':
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table} SET total_referrals = total_referrals + 1, updated_at = %s WHERE id = %d",
						current_time( 'mysql' ),
						$code_id
					)
				);
				return true;

			case 'conversions':
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table} SET total_conversions = total_conversions + 1, total_earned = total_earned + %f, updated_at = %s WHERE id = %d",
						floatval( $earned_amount ),
						current_time( 'mysql' ),
						$code_id
					)
				);
				return true;
		}

		return false;
	}

	/**
	 * Obter estatísticas do código de um usuário
	 *
	 * @param int $user_id ID do usuário.
	 * @return array Estatísticas.
	 */
	public function get_user_stats( $user_id ) {
		$code_data = $this->get_code_by_user( $user_id );

		if ( ! $code_data ) {
			return array(
				'code'             => '',
				'link'             => '',
				'total_referrals'  => 0,
				'total_conversions' => 0,
				'total_earned'     => 0,
				'conversion_rate'  => 0,
			);
		}

		$conversion_rate = $code_data->total_referrals > 0
			? round( ( $code_data->total_conversions / $code_data->total_referrals ) * 100, 1 )
			: 0;

		return array(
			'code'             => $code_data->code,
			'link'             => $this->get_referral_link( $user_id ),
			'total_referrals'  => absint( $code_data->total_referrals ),
			'total_conversions' => absint( $code_data->total_conversions ),
			'total_earned'     => floatval( $code_data->total_earned ),
			'conversion_rate'  => $conversion_rate,
		);
	}

	/**
	 * Obter nível de indicador (gamificação)
	 *
	 * @param int $user_id ID do usuário.
	 * @return array Dados do nível.
	 */
	public function get_referrer_level( $user_id ) {
		$stats = $this->get_user_stats( $user_id );
		$conversions = $stats['total_conversions'];

		// Definir níveis
		$levels = apply_filters(
			'pcw_referral_levels',
			array(
				array(
					'name'       => __( 'Iniciante', 'person-cash-wallet' ),
					'slug'       => 'beginner',
					'min'        => 0,
					'max'        => 2,
					'color'      => '#9CA3AF',
					'icon'       => '🌱',
				),
				array(
					'name'       => __( 'Bronze', 'person-cash-wallet' ),
					'slug'       => 'bronze',
					'min'        => 3,
					'max'        => 5,
					'color'      => '#CD7F32',
					'icon'       => '🥉',
				),
				array(
					'name'       => __( 'Prata', 'person-cash-wallet' ),
					'slug'       => 'silver',
					'min'        => 6,
					'max'        => 15,
					'color'      => '#C0C0C0',
					'icon'       => '🥈',
				),
				array(
					'name'       => __( 'Ouro', 'person-cash-wallet' ),
					'slug'       => 'gold',
					'min'        => 16,
					'max'        => 30,
					'color'      => '#FFD700',
					'icon'       => '🥇',
				),
				array(
					'name'       => __( 'Diamante', 'person-cash-wallet' ),
					'slug'       => 'diamond',
					'min'        => 31,
					'max'        => PHP_INT_MAX,
					'color'      => '#B9F2FF',
					'icon'       => '💎',
				),
			)
		);

		// Encontrar nível atual
		$current_level = $levels[0];
		$next_level = null;

		foreach ( $levels as $index => $level ) {
			if ( $conversions >= $level['min'] && $conversions <= $level['max'] ) {
				$current_level = $level;
				$next_level = isset( $levels[ $index + 1 ] ) ? $levels[ $index + 1 ] : null;
				break;
			}
		}

		// Calcular progresso para próximo nível
		$progress = 0;
		$remaining = 0;

		if ( $next_level ) {
			$range = $next_level['min'] - $current_level['min'];
			$done = $conversions - $current_level['min'];
			$progress = $range > 0 ? round( ( $done / $range ) * 100 ) : 0;
			$remaining = $next_level['min'] - $conversions;
		} else {
			$progress = 100;
		}

		return array(
			'current'    => $current_level,
			'next'       => $next_level,
			'progress'   => min( 100, max( 0, $progress ) ),
			'remaining'  => max( 0, $remaining ),
			'conversions' => $conversions,
		);
	}

	/**
	 * Obter todos os códigos (admin)
	 *
	 * @param array $args Argumentos de busca.
	 * @return array
	 */
	public function get_all_codes( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_codes';

		$defaults = array(
			'status'   => '',
			'orderby'  => 'total_conversions',
			'order'    => 'DESC',
			'limit'    => 50,
			'offset'   => 0,
			'search'   => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'c.status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[] = '(c.code LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)';
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		$where_clause = implode( ' AND ', $where );
		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );

		$query = "
			SELECT c.*, u.display_name, u.user_email
			FROM {$table} c
			LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
			WHERE {$where_clause}
			ORDER BY {$orderby}
			LIMIT %d OFFSET %d
		";

		$values[] = absint( $args['limit'] );
		$values[] = absint( $args['offset'] );

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		return $wpdb->get_results( $query );
	}

	/**
	 * Contar total de códigos (admin)
	 *
	 * @param array $args Argumentos de busca.
	 * @return int
	 */
	public function count_codes( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_codes';

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'c.status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[] = '(c.code LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)';
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		$where_clause = implode( ' AND ', $where );

		$query = "
			SELECT COUNT(*)
			FROM {$table} c
			LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
			WHERE {$where_clause}
		";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Obter top indicadores
	 *
	 * @param int $limit Limite de resultados.
	 * @return array
	 */
	public function get_top_referrers( $limit = 10 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_codes';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, u.display_name, u.user_email
				FROM {$table} c
				LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
				WHERE c.status = 'active' AND c.total_conversions > 0
				ORDER BY c.total_conversions DESC, c.total_earned DESC
				LIMIT %d",
				absint( $limit )
			)
		);
	}
}
