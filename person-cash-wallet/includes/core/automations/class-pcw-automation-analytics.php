<?php
/**
 * Analytics de Automações
 *
 * @package GrowlyDigital
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de analytics de automações
 */
class PCW_Automation_Analytics {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Automation_Analytics
	 */
	private static $instance = null;

	/**
	 * Obter instância
	 *
	 * @return PCW_Automation_Analytics
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Obter métricas gerais da automação
	 *
	 * @param int    $automation_id ID da automação.
	 * @param string $start_date Data inicial (Y-m-d).
	 * @param string $end_date Data final (Y-m-d).
	 * @return array
	 */
	public function get_metrics( $automation_id, $start_date = null, $end_date = null ) {
		global $wpdb;

		$where = array( "e.automation_id = %d" );
		$params = array( $automation_id );

		if ( $start_date ) {
			$where[] = "e.created_at >= %s";
			$params[] = $start_date . ' 00:00:00';
		}

		if ( $end_date ) {
			$where[] = "e.created_at <= %s";
			$params[] = $end_date . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );

		// Total de disparos (execuções)
		$executions = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}pcw_automation_executions e WHERE {$where_clause}",
			...$params
		) );

		// Emails enviados
		$emails_sent = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}pcw_email_tracking e WHERE {$where_clause}",
			...$params
		) );

		// Emails abertos (únicos)
		$emails_opened = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}pcw_email_tracking e 
			WHERE {$where_clause} AND first_opened_at IS NOT NULL",
			...$params
		) );

		// Emails clicados (únicos)
		$emails_clicked = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}pcw_email_tracking e 
			WHERE {$where_clause} AND first_clicked_at IS NOT NULL",
			...$params
		) );

		// Conversões
		$conversions = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}pcw_email_tracking e 
			WHERE {$where_clause} AND conversion_order_id IS NOT NULL",
			...$params
		) );

		// Receita total
		$revenue = $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(conversion_value), 0) FROM {$wpdb->prefix}pcw_email_tracking e 
			WHERE {$where_clause} AND conversion_value IS NOT NULL",
			...$params
		) );

		// Total de aberturas (incluindo múltiplas)
		$total_opens = $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(open_count), 0) FROM {$wpdb->prefix}pcw_email_tracking e WHERE {$where_clause}",
			...$params
		) );

		// Total de cliques (incluindo múltiplos)
		$total_clicks = $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(click_count), 0) FROM {$wpdb->prefix}pcw_email_tracking e WHERE {$where_clause}",
			...$params
		) );

		// WhatsApp: enviados e na fila
		$queue_table = $wpdb->prefix . 'pcw_message_queue';
		$whatsapp_sent = 0;
		$whatsapp_pending = 0;
		$whatsapp_failed = 0;

		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $queue_table ) ) ) {
			$q_where = array( "q.automation_id = %d", "q.type IN ('whatsapp', 'whatsapp_template')" );
			$q_params = array( $automation_id );

			if ( $start_date ) {
				$q_where[] = "q.created_at >= %s";
				$q_params[] = $start_date . ' 00:00:00';
			}
			if ( $end_date ) {
				$q_where[] = "q.created_at <= %s";
				$q_params[] = $end_date . ' 23:59:59';
			}

			$q_where_clause = implode( ' AND ', $q_where );

			$whatsapp_sent = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table} q WHERE {$q_where_clause} AND q.status = 'sent'",
				...$q_params
			) );
			$whatsapp_pending = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table} q WHERE {$q_where_clause} AND q.status = 'pending'",
				...$q_params
			) );
			$whatsapp_failed = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table} q WHERE {$q_where_clause} AND q.status = 'failed'",
				...$q_params
			) );
		}

		// Calcular taxas
		$delivery_rate = $emails_sent > 0 ? ( $emails_sent / $executions ) * 100 : 0;
		$open_rate = $emails_sent > 0 ? ( $emails_opened / $emails_sent ) * 100 : 0;
		$click_rate = $emails_sent > 0 ? ( $emails_clicked / $emails_sent ) * 100 : 0;
		$ctor = $emails_opened > 0 ? ( $emails_clicked / $emails_opened ) * 100 : 0;
		$conversion_rate = $emails_sent > 0 ? ( $conversions / $emails_sent ) * 100 : 0;
		$avg_order_value = $conversions > 0 ? $revenue / $conversions : 0;

		return array(
			'executions'        => (int) $executions,
			'emails_sent'       => (int) $emails_sent,
			'emails_opened'     => (int) $emails_opened,
			'emails_clicked'    => (int) $emails_clicked,
			'conversions'       => (int) $conversions,
			'revenue'           => (float) $revenue,
			'total_opens'       => (int) $total_opens,
			'total_clicks'      => (int) $total_clicks,
			'whatsapp_sent'     => $whatsapp_sent,
			'whatsapp_pending'  => $whatsapp_pending,
			'whatsapp_failed'   => $whatsapp_failed,
			'delivery_rate'     => round( $delivery_rate, 2 ),
			'open_rate'         => round( $open_rate, 2 ),
			'click_rate'        => round( $click_rate, 2 ),
			'ctor'              => round( $ctor, 2 ),
			'conversion_rate'   => round( $conversion_rate, 2 ),
			'avg_order_value'   => round( $avg_order_value, 2 ),
		);
	}

	/**
	 * Obter eventos da automação
	 *
	 * @param int    $automation_id ID da automação.
	 * @param array  $filters Filtros (start_date, end_date, event_type, user_id, email).
	 * @param int    $page Página.
	 * @param int    $per_page Itens por página.
	 * @return array
	 */
	public function get_events( $automation_id, $filters = array(), $page = 1, $per_page = 20 ) {
		global $wpdb;

		$where = array( "automation_id = %d" );
		$params = array( $automation_id );

		if ( ! empty( $filters['start_date'] ) ) {
			$where[] = "created_at >= %s";
			$params[] = $filters['start_date'] . ' 00:00:00';
		}

		if ( ! empty( $filters['end_date'] ) ) {
			$where[] = "created_at <= %s";
			$params[] = $filters['end_date'] . ' 23:59:59';
		}

		if ( ! empty( $filters['event_type'] ) ) {
			$where[] = "event_type = %s";
			$params[] = $filters['event_type'];
		}

		if ( ! empty( $filters['user_id'] ) ) {
			$where[] = "user_id = %d";
			$params[] = $filters['user_id'];
		}

		if ( ! empty( $filters['email'] ) ) {
			$where[] = "email LIKE %s";
			$params[] = '%' . $wpdb->esc_like( $filters['email'] ) . '%';
		}

		$where_clause = implode( ' AND ', $where );

		// Total
		$total = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}pcw_automation_events WHERE {$where_clause}",
			...$params
		) );

		// Paginação
		$offset = ( $page - 1 ) * $per_page;
		$params[] = $offset;
		$params[] = $per_page;

		// Buscar eventos
		$events = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}pcw_automation_events 
			WHERE {$where_clause} 
			ORDER BY created_at DESC 
			LIMIT %d, %d",
			...$params
		) );

		// Processar eventos
		foreach ( $events as &$event ) {
			$event->metadata = json_decode( $event->metadata, true );
			
			// Buscar nome do usuário se disponível
			if ( $event->user_id ) {
				$user = get_user_by( 'id', $event->user_id );
				$event->user_name = $user ? $user->display_name : 'Usuário #' . $event->user_id;
			}
		}

		return array(
			'total'      => (int) $total,
			'events'     => $events,
			'page'       => $page,
			'per_page'   => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		);
	}

	/**
	 * Obter dados para gráfico de linha do tempo
	 *
	 * @param int    $automation_id ID da automação.
	 * @param string $start_date Data inicial.
	 * @param string $end_date Data final.
	 * @param string $group_by Agrupar por (day, week, month).
	 * @return array
	 */
	public function get_timeline_data( $automation_id, $start_date, $end_date, $group_by = 'day' ) {
		global $wpdb;

		$date_format = $this->get_date_format( $group_by );
		$events_table     = $wpdb->prefix . 'pcw_automation_events';
		$executions_table = $wpdb->prefix . 'pcw_automation_executions';

		// Tentar eventos primeiro
		$data = $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				DATE_FORMAT(created_at, %s) as date_label,
				COUNT(*) as total
			FROM {$events_table}
			WHERE automation_id = %d
			AND created_at >= %s
			AND created_at <= %s
			GROUP BY date_label
			ORDER BY date_label ASC",
			$date_format,
			$automation_id,
			$start_date . ' 00:00:00',
			$end_date . ' 23:59:59'
		) );

		// Se não há eventos, usar execuções como fonte de dados
		if ( empty( $data ) ) {
			$data = $wpdb->get_results( $wpdb->prepare(
				"SELECT 
					DATE_FORMAT(created_at, %s) as date_label,
					COUNT(*) as total
				FROM {$executions_table}
				WHERE automation_id = %d
				AND created_at >= %s
				AND created_at <= %s
				GROUP BY date_label
				ORDER BY date_label ASC",
				$date_format,
				$automation_id,
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			) );
		}

		return $data;
	}

	/**
	 * Obter top links mais clicados
	 *
	 * @param int    $automation_id ID da automação.
	 * @param string $start_date Data inicial.
	 * @param string $end_date Data final.
	 * @param int    $limit Limite.
	 * @return array
	 */
	public function get_top_links( $automation_id, $start_date = null, $end_date = null, $limit = 10 ) {
		global $wpdb;

		$where = array( "automation_id = %d" );
		$params = array( $automation_id );

		if ( $start_date ) {
			$where[] = "created_at >= %s";
			$params[] = $start_date . ' 00:00:00';
		}

		if ( $end_date ) {
			$where[] = "created_at <= %s";
			$params[] = $end_date . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );
		$params[] = $limit;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT link_url, link_text, clicked_count, unique_clicks
			FROM {$wpdb->prefix}pcw_link_tracking
			WHERE {$where_clause}
			ORDER BY clicked_count DESC
			LIMIT %d",
			...$params
		) );
	}

	/**
	 * Obter distribuição por dispositivo
	 *
	 * @param int    $automation_id ID da automação.
	 * @param string $start_date Data inicial.
	 * @param string $end_date Data final.
	 * @return array
	 */
	public function get_device_distribution( $automation_id, $start_date = null, $end_date = null ) {
		global $wpdb;

		$where = array( "automation_id = %d" );
		$params = array( $automation_id );

		if ( $start_date ) {
			$where[] = "created_at >= %s";
			$params[] = $start_date . ' 00:00:00';
		}

		if ( $end_date ) {
			$where[] = "created_at <= %s";
			$params[] = $end_date . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				device_type,
				COUNT(*) as count,
				COUNT(*) * 100.0 / SUM(COUNT(*)) OVER() as percentage
			FROM {$wpdb->prefix}pcw_email_tracking
			WHERE {$where_clause}
			AND device_type IS NOT NULL
			GROUP BY device_type
			ORDER BY count DESC",
			...$params
		) );
	}

	/**
	 * Obter distribuição por cliente de email
	 *
	 * @param int    $automation_id ID da automação.
	 * @param string $start_date Data inicial.
	 * @param string $end_date Data final.
	 * @return array
	 */
	public function get_email_client_distribution( $automation_id, $start_date = null, $end_date = null ) {
		global $wpdb;

		$where = array( "automation_id = %d" );
		$params = array( $automation_id );

		if ( $start_date ) {
			$where[] = "created_at >= %s";
			$params[] = $start_date . ' 00:00:00';
		}

		if ( $end_date ) {
			$where[] = "created_at <= %s";
			$params[] = $end_date . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				email_client,
				COUNT(*) as count,
				COUNT(*) * 100.0 / SUM(COUNT(*)) OVER() as percentage
			FROM {$wpdb->prefix}pcw_email_tracking
			WHERE {$where_clause}
			AND email_client IS NOT NULL
			GROUP BY email_client
			ORDER BY count DESC",
			...$params
		) );
	}

	/**
	 * Obter performance por etapa do workflow
	 *
	 * @param int $automation_id ID da automação.
	 * @return array
	 */
	public function get_workflow_performance( $automation_id ) {
		global $wpdb;

		$automation = PCW_Automations::instance()->get( $automation_id );
		$steps = $automation->workflow_steps;

		if ( empty( $steps ) ) {
			return array();
		}

		$performance = array();

		foreach ( $steps as $index => $step ) {
			$step_type = $step['type'];
			
			// Contar eventos dessa etapa
			$count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}pcw_automation_events
				WHERE automation_id = %d
				AND step_index = %d",
				$automation_id,
				$index
			) );

			$performance[] = array(
				'step_index' => $index,
				'step_type'  => $step_type,
				'count'      => (int) $count,
			);
		}

		return $performance;
	}

	/**
	 * Obter formato de data para agrupamento
	 *
	 * @param string $group_by Tipo de agrupamento.
	 * @return string
	 */
	private function get_date_format( $group_by ) {
		switch ( $group_by ) {
			case 'hour':
				return '%Y-%m-%d %H:00';
			case 'day':
				return '%Y-%m-%d';
			case 'week':
				return '%Y-%u';
			case 'month':
				return '%Y-%m';
			case 'year':
				return '%Y';
			default:
				return '%Y-%m-%d';
		}
	}

	/**
	 * Limpar dados antigos (manutenção)
	 *
	 * @param int $months Meses para manter.
	 */
	public function cleanup_old_data( $months = 12 ) {
		global $wpdb;

		$date_limit = date( 'Y-m-d H:i:s', strtotime( '-' . $months . ' months' ) );

		// Limpar eventos antigos
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}pcw_automation_events WHERE created_at < %s",
			$date_limit
		) );

		// Limpar tracking de emails antigos
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}pcw_email_tracking WHERE created_at < %s",
			$date_limit
		) );

		// Limpar links órfãos
		$wpdb->query(
			"DELETE l FROM {$wpdb->prefix}pcw_link_tracking l
			LEFT JOIN {$wpdb->prefix}pcw_email_tracking e ON l.email_tracking_id = e.id
			WHERE e.id IS NULL"
		);
	}
}
