<?php
/**
 * Classe principal de indicações
 *
 * @package PersonCashWallet
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe principal de indicações
 */
class PCW_Referrals {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Referrals
	 */
	private static $instance = null;

	/**
	 * Obter instância singleton
	 *
	 * @return PCW_Referrals
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
	 * Inicializar
	 */
	public function init() {
		// Hook para criar código automaticamente quando usuário faz primeira compra
		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_create_referral_code' ), 5 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_create_referral_code' ), 5 );
	}

	/**
	 * Criar código de indicação automaticamente se necessário
	 *
	 * @param int $order_id ID do pedido.
	 */
	public function maybe_create_referral_code( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			return;
		}

		// Criar código se não existir
		PCW_Referral_Codes::instance()->get_or_create_code( $user_id );
	}

	/**
	 * Criar uma indicação
	 *
	 * @param array $data Dados da indicação.
	 * @return int|WP_Error ID da indicação ou erro.
	 */
	public function create_referral( $data ) {
		global $wpdb;

		$defaults = array(
			'referrer_user_id' => 0,
			'referrer_code'    => '',
			'referred_name'    => '',
			'referred_email'   => '',
			'referred_phone'   => '',
			'source'           => 'manual',
			'notes'            => '',
			'ip_address'       => '',
		);

		$data = wp_parse_args( $data, $defaults );

		// Validações
		if ( empty( $data['referrer_user_id'] ) && empty( $data['referrer_code'] ) ) {
			return new WP_Error( 'missing_referrer', __( 'Indicador não informado.', 'person-cash-wallet' ) );
		}

		if ( empty( $data['referred_name'] ) ) {
			return new WP_Error( 'missing_name', __( 'Nome do indicado é obrigatório.', 'person-cash-wallet' ) );
		}

		if ( empty( $data['referred_email'] ) || ! is_email( $data['referred_email'] ) ) {
			return new WP_Error( 'invalid_email', __( 'Email do indicado é inválido.', 'person-cash-wallet' ) );
		}

		if ( empty( $data['referred_phone'] ) ) {
			return new WP_Error( 'missing_phone', __( 'Telefone do indicado é obrigatório.', 'person-cash-wallet' ) );
		}

		// Obter dados do código
		$codes = PCW_Referral_Codes::instance();

		if ( ! empty( $data['referrer_code'] ) ) {
			$code_data = $codes->get_code_by_code( $data['referrer_code'] );
			if ( ! $code_data ) {
				return new WP_Error( 'invalid_code', __( 'Código de indicação inválido.', 'person-cash-wallet' ) );
			}
			$data['referrer_user_id'] = $code_data->user_id;
			$data['referrer_code'] = $code_data->code;
		} else {
			$code_data = $codes->get_code_by_user( $data['referrer_user_id'] );
			if ( ! $code_data ) {
				$code_data = $codes->get_or_create_code( $data['referrer_user_id'] );
			}
			$data['referrer_code'] = $code_data->code;
		}

		// Verificar auto-indicação por email
		$referrer = get_userdata( $data['referrer_user_id'] );
		if ( $referrer && strtolower( $referrer->user_email ) === strtolower( $data['referred_email'] ) ) {
			return new WP_Error( 'self_referral', __( 'Você não pode indicar a si mesmo.', 'person-cash-wallet' ) );
		}

		// Verificar se já existe indicação para este email
		$existing = $this->get_referral_by_email( $data['referred_email'] );
		if ( $existing ) {
			return new WP_Error( 'duplicate_referral', __( 'Este email já foi indicado anteriormente.', 'person-cash-wallet' ) );
		}

		// Verificar se o email já é de um cliente existente
		$existing_user = get_user_by( 'email', $data['referred_email'] );
		if ( $existing_user ) {
			// Verificar se já fez alguma compra
			$customer_orders = wc_get_orders( array(
				'customer' => $existing_user->ID,
				'limit'    => 1,
				'status'   => array( 'wc-completed', 'wc-processing' ),
			) );

			if ( ! empty( $customer_orders ) ) {
				return new WP_Error( 'existing_customer', __( 'Este email já pertence a um cliente existente.', 'person-cash-wallet' ) );
			}
		}

		$table = $wpdb->prefix . 'pcw_referrals';

		$insert_data = array(
			'referrer_user_id' => absint( $data['referrer_user_id'] ),
			'referrer_code'    => sanitize_text_field( $data['referrer_code'] ),
			'referred_name'    => sanitize_text_field( $data['referred_name'] ),
			'referred_email'   => sanitize_email( $data['referred_email'] ),
			'referred_phone'   => sanitize_text_field( $data['referred_phone'] ),
			'referred_user_id' => $existing_user ? $existing_user->ID : null,
			'status'           => 'pending',
			'source'           => sanitize_text_field( $data['source'] ),
			'ip_address'       => sanitize_text_field( $data['ip_address'] ),
			'notes'            => sanitize_textarea_field( $data['notes'] ),
			'created_at'       => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $insert_data );

		if ( ! $result ) {
			return new WP_Error( 'db_error', __( 'Erro ao salvar indicação.', 'person-cash-wallet' ) );
		}

		$referral_id = $wpdb->insert_id;

		// Incrementar contador de indicações
		$codes->increment_counter( $code_data->id, 'referrals' );

		// Disparar ação
		do_action( 'pcw_referral_created', $referral_id, $data['referrer_user_id'], $insert_data );

		return $referral_id;
	}

	/**
	 * Obter indicação por ID
	 *
	 * @param int $referral_id ID da indicação.
	 * @return object|null
	 */
	public function get_referral( $referral_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referrals';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				absint( $referral_id )
			)
		);
	}

	/**
	 * Obter indicação por email
	 *
	 * @param string $email Email do indicado.
	 * @return object|null
	 */
	public function get_referral_by_email( $email ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referrals';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE referred_email = %s",
				sanitize_email( $email )
			)
		);
	}

	/**
	 * Obter indicações de um usuário (que ele fez)
	 *
	 * @param int   $user_id ID do usuário.
	 * @param array $args Argumentos.
	 * @return array
	 */
	public function get_user_referrals( $user_id, $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referrals';

		$defaults = array(
			'status' => '',
			'limit'  => 50,
			'offset' => 0,
			'order'  => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( 'referrer_user_id = %d' );
		$values = array( absint( $user_id ) );

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );
		$order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		$query = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at {$order} LIMIT %d OFFSET %d",
			array_merge( $values, array( absint( $args['limit'] ), absint( $args['offset'] ) ) )
		);

		return $wpdb->get_results( $query );
	}

	/**
	 * Contar indicações de um usuário
	 *
	 * @param int   $user_id ID do usuário.
	 * @param array $args Argumentos.
	 * @return int
	 */
	public function count_user_referrals( $user_id, $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referrals';

		$where = array( 'referrer_user_id = %d' );
		$values = array( absint( $user_id ) );

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE {$where_clause}",
				$values
			)
		);
	}

	/**
	 * Obter todas as indicações (admin)
	 *
	 * @param array $args Argumentos.
	 * @return array
	 */
	public function get_all_referrals( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referrals';

		$defaults = array(
			'status'           => '',
			'referrer_user_id' => 0,
			'search'           => '',
			'orderby'          => 'created_at',
			'order'            => 'DESC',
			'limit'            => 50,
			'offset'           => 0,
			'date_from'        => '',
			'date_to'          => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'r.status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		if ( ! empty( $args['referrer_user_id'] ) ) {
			$where[] = 'r.referrer_user_id = %d';
			$values[] = absint( $args['referrer_user_id'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[] = '(r.referred_name LIKE %s OR r.referred_email LIKE %s OR r.referred_phone LIKE %s OR r.referrer_code LIKE %s)';
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[] = 'r.created_at >= %s';
			$values[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[] = 'r.created_at <= %s';
			$values[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );

		$allowed_orderby = array( 'created_at', 'converted_at', 'rewarded_at', 'referred_name', 'status', 'reward_amount' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		$query = "
			SELECT r.*, u.display_name as referrer_name, u.user_email as referrer_email
			FROM {$table} r
			LEFT JOIN {$wpdb->users} u ON r.referrer_user_id = u.ID
			WHERE {$where_clause}
			ORDER BY r.{$orderby} {$order}
			LIMIT %d OFFSET %d
		";

		$values[] = absint( $args['limit'] );
		$values[] = absint( $args['offset'] );

		return $wpdb->get_results( $wpdb->prepare( $query, $values ) );
	}

	/**
	 * Contar todas as indicações (admin)
	 *
	 * @param array $args Argumentos.
	 * @return int
	 */
	public function count_all_referrals( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referrals';

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		if ( ! empty( $args['referrer_user_id'] ) ) {
			$where[] = 'referrer_user_id = %d';
			$values[] = absint( $args['referrer_user_id'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[] = '(referred_name LIKE %s OR referred_email LIKE %s OR referred_phone LIKE %s)';
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Atualizar status de uma indicação
	 *
	 * @param int    $referral_id ID da indicação.
	 * @param string $status Novo status.
	 * @param array  $extra_data Dados extras para atualizar.
	 * @return bool
	 */
	public function update_status( $referral_id, $status, $extra_data = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referrals';

		$allowed_status = array( 'pending', 'converted', 'rewarded', 'expired', 'cancelled' );

		if ( ! in_array( $status, $allowed_status, true ) ) {
			return false;
		}

		$update_data = array(
			'status' => $status,
		);

		$update_format = array( '%s' );

		// Dados extras
		if ( 'converted' === $status && empty( $extra_data['converted_at'] ) ) {
			$update_data['converted_at'] = current_time( 'mysql' );
			$update_format[] = '%s';
		}

		if ( 'rewarded' === $status && empty( $extra_data['rewarded_at'] ) ) {
			$update_data['rewarded_at'] = current_time( 'mysql' );
			$update_format[] = '%s';
		}

		// Merge extra data
		foreach ( $extra_data as $key => $value ) {
			$update_data[ $key ] = $value;
			$update_format[] = is_numeric( $value ) ? '%f' : '%s';
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => absint( $referral_id ) ),
			$update_format,
			array( '%d' )
		);

		if ( false !== $result ) {
			do_action( 'pcw_referral_status_changed', $referral_id, $status, $extra_data );
		}

		return false !== $result;
	}

	/**
	 * Excluir indicação
	 *
	 * @param int $referral_id ID da indicação.
	 * @return bool
	 */
	public function delete_referral( $referral_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referrals';

		$referral = $this->get_referral( $referral_id );

		if ( ! $referral ) {
			return false;
		}

		$result = $wpdb->delete(
			$table,
			array( 'id' => absint( $referral_id ) ),
			array( '%d' )
		);

		if ( $result ) {
			do_action( 'pcw_referral_deleted', $referral_id, $referral );
		}

		return (bool) $result;
	}

	/**
	 * Obter estatísticas gerais (admin dashboard)
	 *
	 * @param string $period Período: 'today', 'week', 'month', 'year', 'all'.
	 * @return array
	 */
	public function get_stats( $period = 'all' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referrals';

		$date_condition = '';
		$date_value = '';

		switch ( $period ) {
			case 'today':
				$date_condition = 'AND created_at >= %s';
				$date_value = gmdate( 'Y-m-d 00:00:00' );
				break;
			case 'week':
				$date_condition = 'AND created_at >= %s';
				$date_value = gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );
				break;
			case 'month':
				$date_condition = 'AND created_at >= %s';
				$date_value = gmdate( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );
				break;
			case 'year':
				$date_condition = 'AND created_at >= %s';
				$date_value = gmdate( 'Y-m-d 00:00:00', strtotime( '-365 days' ) );
				break;
		}

		$query_base = "SELECT COUNT(*) FROM {$table} WHERE 1=1 {$date_condition}";

		if ( $date_value ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $query_base, $date_value ) );
			$pending = (int) $wpdb->get_var( $wpdb->prepare( $query_base . " AND status = 'pending'", $date_value ) );
			$converted = (int) $wpdb->get_var( $wpdb->prepare( $query_base . " AND status IN ('converted', 'rewarded')", $date_value ) );
			$rewarded = (int) $wpdb->get_var( $wpdb->prepare( $query_base . " AND status = 'rewarded'", $date_value ) );
			$total_earned = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(reward_amount) FROM {$table} WHERE status = 'rewarded' {$date_condition}", $date_value ) );
		} else {
			$total = (int) $wpdb->get_var( $query_base );
			$pending = (int) $wpdb->get_var( $query_base . " AND status = 'pending'" );
			$converted = (int) $wpdb->get_var( $query_base . " AND status IN ('converted', 'rewarded')" );
			$rewarded = (int) $wpdb->get_var( $query_base . " AND status = 'rewarded'" );
			$total_earned = (float) $wpdb->get_var( "SELECT SUM(reward_amount) FROM {$table} WHERE status = 'rewarded'" );
		}

		$conversion_rate = $total > 0 ? round( ( $converted / $total ) * 100, 1 ) : 0;

		return array(
			'total'           => $total,
			'pending'         => $pending,
			'converted'       => $converted,
			'rewarded'        => $rewarded,
			'total_earned'    => $total_earned ?: 0,
			'conversion_rate' => $conversion_rate,
		);
	}

	/**
	 * Obter dados para gráfico (admin dashboard)
	 *
	 * @param int $days Número de dias.
	 * @return array
	 */
	public function get_chart_data( $days = 30 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referrals';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					DATE(created_at) as date,
					COUNT(*) as total,
					SUM(CASE WHEN status IN ('converted', 'rewarded') THEN 1 ELSE 0 END) as converted
				FROM {$table}
				WHERE created_at >= %s
				GROUP BY DATE(created_at)
				ORDER BY date ASC",
				gmdate( 'Y-m-d 00:00:00', strtotime( "-{$days} days" ) )
			)
		);

		$data = array(
			'labels'    => array(),
			'referrals' => array(),
			'converted' => array(),
		);

		// Preencher todos os dias
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
			$data['labels'][] = gmdate( 'd/m', strtotime( $date ) );
			$data['referrals'][] = 0;
			$data['converted'][] = 0;
		}

		// Preencher com dados reais
		foreach ( $results as $row ) {
			$index = array_search( gmdate( 'd/m', strtotime( $row->date ) ), $data['labels'], true );
			if ( false !== $index ) {
				$data['referrals'][ $index ] = (int) $row->total;
				$data['converted'][ $index ] = (int) $row->converted;
			}
		}

		return $data;
	}
}
