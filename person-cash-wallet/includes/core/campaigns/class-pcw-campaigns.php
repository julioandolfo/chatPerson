<?php
/**
 * Gerenciador de Campanhas de Newsletter
 *
 * @package GrowlyDigital
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de gerenciamento de campanhas
 */
class PCW_Campaigns {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Campaigns
	 */
	private static $instance = null;

	/**
	 * Obter instância
	 *
	 * @return PCW_Campaigns
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
	 * Criar campanha
	 *
	 * @param array $data Dados da campanha.
	 * @return int|false
	 */
	public function create( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_campaigns';

		$insert_data = array(
			'name'                 => sanitize_text_field( $data['name'] ),
			'subject'              => sanitize_text_field( $data['subject'] ),
			'preview_text'         => isset( $data['preview_text'] ) ? sanitize_text_field( $data['preview_text'] ) : '',
			'content'              => wp_kses_post( $data['content'] ),
			'smtp_account_id'      => isset( $data['smtp_account_id'] ) ? absint( $data['smtp_account_id'] ) : null,
			'recipient_conditions' => isset( $data['recipient_conditions'] ) ? wp_json_encode( $data['recipient_conditions'] ) : '{}',
			'status'               => 'draft',
			'batch_size'           => isset( $data['batch_size'] ) ? absint( $data['batch_size'] ) : 50,
			'batch_delay'          => isset( $data['batch_delay'] ) ? absint( $data['batch_delay'] ) : 60,
			'created_by'           => get_current_user_id(),
			'created_at'           => current_time( 'mysql' ),
			'updated_at'           => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $insert_data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Atualizar campanha
	 *
	 * @param int   $id ID da campanha.
	 * @param array $data Dados para atualizar.
	 * @return bool
	 */
	public function update( $id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_campaigns';

		$update_data = array(
			'updated_at' => current_time( 'mysql' ),
		);

		$fields = array( 'name', 'subject', 'preview_text', 'content', 'smtp_account_id', 'status', 'batch_size', 'batch_delay', 'scheduled_at' );

		foreach ( $fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				if ( $field === 'content' ) {
					$update_data[ $field ] = wp_kses_post( $data[ $field ] );
				} elseif ( $field === 'smtp_account_id' || $field === 'batch_size' || $field === 'batch_delay' ) {
					$update_data[ $field ] = absint( $data[ $field ] );
				} else {
					$update_data[ $field ] = sanitize_text_field( $data[ $field ] );
				}
			}
		}

		if ( isset( $data['recipient_conditions'] ) ) {
			$update_data['recipient_conditions'] = wp_json_encode( $data['recipient_conditions'] );
		}

		$result = $wpdb->update( $table, $update_data, array( 'id' => $id ) );

		return false !== $result;
	}

	/**
	 * Deletar campanha
	 *
	 * @param int $id ID da campanha.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		// Deletar envios relacionados
		$wpdb->delete( $wpdb->prefix . 'pcw_campaign_sends', array( 'campaign_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'pcw_campaign_tracking', array( 'campaign_id' => $id ) );

		// Deletar campanha
		$result = $wpdb->delete( $wpdb->prefix . 'pcw_campaigns', array( 'id' => $id ) );

		return false !== $result;
	}

	/**
	 * Obter campanha por ID
	 *
	 * @param int $id ID da campanha.
	 * @return object|null
	 */
	public function get( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_campaigns';

		$campaign = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		if ( $campaign ) {
			$campaign->recipient_conditions = json_decode( $campaign->recipient_conditions, true );
		}

		return $campaign;
	}

	/**
	 * Obter todas as campanhas
	 *
	 * @param array $args Argumentos.
	 * @return array
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_campaigns';

		$defaults = array(
			'status'  => '',
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 50,
			'offset'  => 0,
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
			$orderby = 'created_at DESC';
		}

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} LIMIT %d OFFSET %d";
		$params[] = $args['limit'];
		$params[] = $args['offset'];

		$campaigns = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		foreach ( $campaigns as $campaign ) {
			$campaign->recipient_conditions = json_decode( $campaign->recipient_conditions, true );
		}

		return $campaigns;
	}

	/**
	 * Obter destinatários baseado nas condições
	 *
	 * @param array $conditions Condições.
	 * @return array
	 */
	public function get_recipients( $conditions ) {
		global $wpdb;

		$where = "u.ID > 0 AND u.user_email != ''";
		$join = '';
		$params = array();

		if ( ! empty( $conditions['customer_type'] ) ) {
			switch ( $conditions['customer_type'] ) {
				case 'all':
					// Todos os usuários
					break;
				case 'customers':
					$join .= " INNER JOIN {$wpdb->usermeta} um_role ON u.ID = um_role.user_id AND um_role.meta_key = '{$wpdb->prefix}capabilities'";
					$where .= " AND um_role.meta_value LIKE '%customer%'";
					break;
				case 'subscribers':
					$join .= " INNER JOIN {$wpdb->usermeta} um_role ON u.ID = um_role.user_id AND um_role.meta_key = '{$wpdb->prefix}capabilities'";
					$where .= " AND um_role.meta_value LIKE '%subscriber%'";
					break;
			}
		}

		// Filtro por nível
		if ( ! empty( $conditions['level_id'] ) ) {
			$join .= " INNER JOIN {$wpdb->prefix}pcw_user_levels ul ON u.ID = ul.user_id AND ul.level_id = %d AND ul.status = 'active'";
			$params[] = absint( $conditions['level_id'] );
		}

		// Filtro por compras
		if ( ! empty( $conditions['min_orders'] ) ) {
			$join .= " LEFT JOIN (SELECT customer_id, COUNT(*) as order_count FROM {$wpdb->prefix}wc_orders WHERE status IN ('wc-completed','wc-processing') GROUP BY customer_id) oc ON u.ID = oc.customer_id";
			$where .= " AND COALESCE(oc.order_count, 0) >= %d";
			$params[] = absint( $conditions['min_orders'] );
		}

		// Filtro por valor gasto
		if ( ! empty( $conditions['min_spent'] ) ) {
			$join .= " LEFT JOIN {$wpdb->usermeta} um_spent ON u.ID = um_spent.user_id AND um_spent.meta_key = '_money_spent'";
			$where .= " AND COALESCE(um_spent.meta_value, 0) >= %f";
			$params[] = floatval( $conditions['min_spent'] );
		}

		// Filtro por data de cadastro
		if ( ! empty( $conditions['registered_after'] ) ) {
			$where .= " AND u.user_registered >= %s";
			$params[] = $conditions['registered_after'];
		}

		if ( ! empty( $conditions['registered_before'] ) ) {
			$where .= " AND u.user_registered <= %s";
			$params[] = $conditions['registered_before'];
		}

		$sql = "SELECT u.ID, u.user_email, u.display_name FROM {$wpdb->users} u {$join} WHERE {$where}";

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Iniciar campanha
	 *
	 * @param int $campaign_id ID da campanha.
	 * @return bool|WP_Error
	 */
	public function start( $campaign_id ) {
		global $wpdb;

		$campaign = $this->get( $campaign_id );

		if ( ! $campaign ) {
			return new WP_Error( 'not_found', __( 'Campanha não encontrada', 'person-cash-wallet' ) );
		}

		if ( $campaign->status === 'sending' || $campaign->status === 'completed' ) {
			return new WP_Error( 'invalid_status', __( 'Campanha já iniciada ou concluída', 'person-cash-wallet' ) );
		}

		// Verificar tipo de audiência
		$is_custom_list = false;
		$custom_list_id = 0;

		if ( ! empty( $campaign->audience_type ) && 'custom_list' === $campaign->audience_type ) {
			$is_custom_list = true;
			$custom_list_id = absint( $campaign->custom_list_id ?? 0 );
		}

		// Obter destinatários
		if ( $is_custom_list && $custom_list_id > 0 ) {
			$recipients = $this->get_recipients_from_list( $custom_list_id );
		} else {
			$recipients = $this->get_recipients( $campaign->recipient_conditions );
		}

		if ( empty( $recipients ) ) {
			return new WP_Error( 'no_recipients', __( 'Nenhum destinatário encontrado', 'person-cash-wallet' ) );
		}

		// Criar registros de envio
		$sends_table = $wpdb->prefix . 'pcw_campaign_sends';

		foreach ( $recipients as $recipient ) {
			$wpdb->insert( $sends_table, array(
				'campaign_id' => $campaign_id,
				'user_id'     => $recipient->user_id ?? null,
				'email'       => $recipient->email ?? $recipient->user_email ?? '',
				'status'      => 'pending',
				'created_at'  => current_time( 'mysql' ),
			) );
		}

		// Atualizar campanha
		$this->update( $campaign_id, array(
			'status'           => 'sending',
			'started_at'       => current_time( 'mysql' ),
			'total_recipients' => count( $recipients ),
		) );

		// Agendar processamento em lotes
		wp_schedule_single_event( time(), 'pcw_process_campaign_batch', array( $campaign_id ) );

		return true;
	}

	/**
	 * Obter destinatários de lista personalizada
	 *
	 * @param int $list_id ID da lista.
	 * @return array
	 */
	private function get_recipients_from_list( $list_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_list_members';

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE list_id = %d",
			$list_id
		) );
	}

	/**
	 * Processar lote de envios
	 *
	 * @param int $campaign_id ID da campanha.
	 */
	public function process_batch( $campaign_id ) {
		global $wpdb;

		$campaign = $this->get( $campaign_id );

		if ( ! $campaign || $campaign->status !== 'sending' ) {
			return;
		}

		// Obter próximo lote de envios pendentes
		$sends_table = $wpdb->prefix . 'pcw_campaign_sends';
		$pending = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$sends_table} WHERE campaign_id = %d AND status = 'pending' LIMIT %d",
			$campaign_id,
			$campaign->batch_size
		) );

		if ( empty( $pending ) ) {
			// Campanha concluída
			$this->update( $campaign_id, array(
				'status'       => 'completed',
				'completed_at' => current_time( 'mysql' ),
			) );
			return;
		}

		$smtp_accounts = PCW_SMTP_Accounts::instance();

		foreach ( $pending as $send ) {
			// Preparar conteúdo personalizado
			$content = $this->personalize_content( $campaign->content, $send );
			$subject = $this->personalize_content( $campaign->subject, $send );

			// Adicionar tracking pixel
			$tracking_pixel = $this->get_tracking_pixel( $campaign_id, $send->id );
			$content .= $tracking_pixel;

			// Adicionar link tracking
			$content = $this->add_link_tracking( $content, $campaign_id, $send->id );

			// Envolver em template
			$html_content = PCW_Email_Handler::generate_preview( $content, $subject );

			// Enviar email
			if ( $campaign->smtp_account_id ) {
				$result = $smtp_accounts->send_email( $campaign->smtp_account_id, $send->email, $subject, $html_content );
			} else {
				$result = wp_mail( $send->email, $subject, $html_content, array( 'Content-Type: text/html; charset=UTF-8' ) );
			}

			// Atualizar status do envio
			$status = ( $result && ! is_wp_error( $result ) ) ? 'sent' : 'failed';
			$error_message = is_wp_error( $result ) ? $result->get_error_message() : '';

			$wpdb->update(
				$sends_table,
				array(
					'status'        => $status,
					'sent_at'       => current_time( 'mysql' ),
					'error_message' => $error_message,
				),
				array( 'id' => $send->id )
			);

			// Atualizar contagem
			if ( $status === 'sent' ) {
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$wpdb->prefix}pcw_campaigns SET sent_count = sent_count + 1 WHERE id = %d",
					$campaign_id
				) );
			}
		}

		// Agendar próximo lote
		wp_schedule_single_event( time() + $campaign->batch_delay, 'pcw_process_campaign_batch', array( $campaign_id ) );
	}

	/**
	 * Personalizar conteúdo
	 *
	 * @param string $content Conteúdo.
	 * @param object $send Dados do envio.
	 * @return string
	 */
	private function personalize_content( $content, $send ) {
		$user = get_userdata( $send->user_id );

		$replacements = array(
			'{customer_name}'  => $user ? $user->display_name : '',
			'{first_name}'     => $user ? $user->first_name : '',
			'{last_name}'      => $user ? $user->last_name : '',
			'{email}'          => $send->email,
			'{site_name}'      => get_bloginfo( 'name' ),
			'{site_url}'       => home_url(),
			'{shop_url}'       => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url(),
			'{unsubscribe_url}' => add_query_arg( array( 'pcw_unsubscribe' => base64_encode( $send->email ) ), home_url() ),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
	}

	/**
	 * Obter pixel de tracking
	 *
	 * @param int $campaign_id ID da campanha.
	 * @param int $send_id ID do envio.
	 * @return string
	 */
	private function get_tracking_pixel( $campaign_id, $send_id ) {
		$tracking_url = add_query_arg(
			array(
				'pcw_track'   => 'open',
				'campaign_id' => $campaign_id,
				'send_id'     => $send_id,
			),
			home_url( '/' )
		);

		return '<img src="' . esc_url( $tracking_url ) . '" width="1" height="1" style="display:none;" alt="" />';
	}

	/**
	 * Adicionar tracking aos links
	 *
	 * @param string $content Conteúdo.
	 * @param int    $campaign_id ID da campanha.
	 * @param int    $send_id ID do envio.
	 * @return string
	 */
	private function add_link_tracking( $content, $campaign_id, $send_id ) {
		return preg_replace_callback(
			'/<a\s+href=["\']([^"\']+)["\']/i',
			function( $matches ) use ( $campaign_id, $send_id ) {
				$original_url = $matches[1];

				// Não rastrear links internos do tracking
				if ( strpos( $original_url, 'pcw_track' ) !== false ) {
					return $matches[0];
				}

				$tracking_url = add_query_arg(
					array(
						'pcw_track'   => 'click',
						'campaign_id' => $campaign_id,
						'send_id'     => $send_id,
						'url'         => urlencode( $original_url ),
					),
					home_url( '/' )
				);

				return '<a href="' . esc_url( $tracking_url ) . '"';
			},
			$content
		);
	}

	/**
	 * Registrar evento de tracking
	 *
	 * @param int    $campaign_id ID da campanha.
	 * @param int    $send_id ID do envio.
	 * @param string $event_type Tipo de evento.
	 * @param string $link_url URL do link (para clicks).
	 */
	public function track_event( $campaign_id, $send_id, $event_type, $link_url = '' ) {
		global $wpdb;

		// Registrar evento
		$wpdb->insert( $wpdb->prefix . 'pcw_campaign_tracking', array(
			'campaign_id' => $campaign_id,
			'send_id'     => $send_id,
			'event_type'  => $event_type,
			'link_url'    => $link_url,
			'ip_address'  => $this->get_client_ip(),
			'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '',
			'created_at'  => current_time( 'mysql' ),
		) );

		// Atualizar contagem na campanha
		$campaign_column = '';
		$send_column = '';

		if ( $event_type === 'open' ) {
			$campaign_column = 'opened_count';
			$send_column = 'opened_at';
		} elseif ( $event_type === 'click' ) {
			$campaign_column = 'clicked_count';
			$send_column = 'clicked_at';
		}

		if ( $campaign_column ) {
			// Incrementar na campanha (apenas primeira vez)
			$sends_table = $wpdb->prefix . 'pcw_campaign_sends';
			$send = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$sends_table} WHERE id = %d", $send_id ) );

			if ( $send && empty( $send->$send_column ) ) {
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$wpdb->prefix}pcw_campaigns SET {$campaign_column} = {$campaign_column} + 1 WHERE id = %d",
					$campaign_id
				) );

				// Atualizar envio
				$wpdb->update(
					$sends_table,
					array( $send_column => current_time( 'mysql' ) ),
					array( 'id' => $send_id )
				);
			}

			// Sempre incrementar contagem de cliques
			if ( $event_type === 'click' ) {
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$sends_table} SET click_count = click_count + 1 WHERE id = %d",
					$send_id
				) );
			}
		}
	}

	/**
	 * Obter estatísticas da campanha
	 *
	 * @param int $campaign_id ID da campanha.
	 * @return array
	 */
	public function get_stats( $campaign_id ) {
		global $wpdb;

		$campaign = $this->get( $campaign_id );

		if ( ! $campaign ) {
			return array();
		}

		$sends_table = $wpdb->prefix . 'pcw_campaign_sends';

		$stats = array(
			'total'        => $campaign->total_recipients,
			'sent'         => $campaign->sent_count,
			'opened'       => $campaign->opened_count,
			'clicked'      => $campaign->clicked_count,
			'bounced'      => $campaign->bounced_count,
			'unsubscribed' => $campaign->unsubscribed_count,
			'pending'      => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sends_table} WHERE campaign_id = %d AND status = 'pending'", $campaign_id ) ),
			'failed'       => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sends_table} WHERE campaign_id = %d AND status = 'failed'", $campaign_id ) ),
		);

		// Taxas
		$stats['open_rate'] = $stats['sent'] > 0 ? round( ( $stats['opened'] / $stats['sent'] ) * 100, 2 ) : 0;
		$stats['click_rate'] = $stats['opened'] > 0 ? round( ( $stats['clicked'] / $stats['opened'] ) * 100, 2 ) : 0;

		return $stats;
	}

	/**
	 * Obter IP do cliente
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = explode( ',', $_SERVER[ $key ] );
				return trim( $ip[0] );
			}
		}

		return '0.0.0.0';
	}
}
