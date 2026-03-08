<?php
/**
 * Tracking de Automações
 *
 * @package GrowlyDigital
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de tracking de automações
 */
class PCW_Automation_Tracking {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Automation_Tracking
	 */
	private static $instance = null;

	/**
	 * Janela de atribuição (em dias)
	 *
	 * @var int
	 */
	private $attribution_window = 7;

	/**
	 * Obter instância
	 *
	 * @return PCW_Automation_Tracking
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
		$this->attribution_window = (int) get_option( 'pcw_automation_attribution_window', 7 );
		$this->init_hooks();
	}

	/**
	 * Inicializar hooks
	 */
	private function init_hooks() {
		// Tracking de cliques e aberturas
		add_action( 'init', array( $this, 'handle_tracking_request' ) );

		// Atribuição de conversões
		add_action( 'woocommerce_thankyou', array( $this, 'attribute_conversion' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'confirm_conversion' ), 10, 1 );
	}

	/**
	 * Criar tracking para um email enviado
	 *
	 * @param int    $automation_id ID da automação.
	 * @param int    $execution_id ID da execução.
	 * @param int    $user_id ID do usuário.
	 * @param string $email Email do destinatário.
	 * @param string $subject Assunto do email.
	 * @param int    $email_log_id ID do log de email (opcional).
	 * @return string|false Tracking code ou false em caso de erro.
	 */
	public function create_email_tracking( $automation_id, $execution_id, $user_id, $email, $subject, $email_log_id = null ) {
		global $wpdb;

		$tracking_code = $this->generate_tracking_code();
		$automation = PCW_Automations::instance()->get( $automation_id );
		$utm_campaign = sanitize_title( $automation->name );

		$data = array(
			'automation_id'   => $automation_id,
			'execution_id'    => $execution_id,
			'email_log_id'    => $email_log_id,
			'tracking_code'   => $tracking_code,
			'user_id'         => $user_id,
			'recipient_email' => $email,
			'subject'         => $subject,
			'utm_campaign'    => $utm_campaign,
			'utm_source'      => 'automation',
			'utm_medium'      => 'email',
			'sent_at'         => current_time( 'mysql' ),
			'created_at'      => current_time( 'mysql' ),
		);

		$result = $wpdb->insert(
			$wpdb->prefix . 'pcw_email_tracking',
			$data
		);

		if ( $result ) {
			// Registrar evento
			$this->log_event( $automation_id, $execution_id, 'email_sent', 0, $user_id, $email );

			return $tracking_code;
		}

		return false;
	}

	/**
	 * Processar HTML do email para adicionar tracking
	 *
	 * @param string $html HTML do email.
	 * @param string $tracking_code Código de tracking.
	 * @param int    $automation_id ID da automação.
	 * @return string HTML processado.
	 */
	public function process_email_html( $html, $tracking_code, $automation_id ) {
		global $wpdb;

		// Adicionar pixel de rastreamento de abertura
		$pixel_url = home_url( '/pcw-track-open/' . $tracking_code );
		$tracking_pixel = '<img src="' . esc_url( $pixel_url ) . '" width="1" height="1" style="display:none;" alt="" />';

		// Processar links
		$html = $this->process_links( $html, $tracking_code, $automation_id );

		// Adicionar pixel antes da tag </body> ou no final
		if ( stripos( $html, '</body>' ) !== false ) {
			$html = str_ireplace( '</body>', $tracking_pixel . '</body>', $html );
		} else {
			$html .= $tracking_pixel;
		}

		return $html;
	}

	/**
	 * Processar links do email para adicionar tracking
	 *
	 * @param string $html HTML do email.
	 * @param string $tracking_code Código de tracking do email.
	 * @param int    $automation_id ID da automação.
	 * @return string HTML processado.
	 */
	private function process_links( $html, $tracking_code, $automation_id ) {
		global $wpdb;

		// Buscar ID do email tracking
		$email_tracking_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}pcw_email_tracking WHERE tracking_code = %s",
			$tracking_code
		) );

		if ( ! $email_tracking_id ) {
			return $html;
		}

		// Regex para encontrar links
		preg_match_all( '/<a\s+([^>]*href=["\']([^"\']+)["\'][^>]*)>([^<]*)<\/a>/i', $html, $matches, PREG_SET_ORDER );

		$position = 0;
		foreach ( $matches as $match ) {
			$full_tag = $match[0];
			$attributes = $match[1];
			$url = $match[2];
			$text = $match[3];

			// Ignorar links especiais
			if ( $this->should_skip_link( $url ) ) {
				continue;
			}

			$position++;

			// Criar tracking para este link
			$tracking_hash = $this->generate_tracking_code();

			// Adicionar UTMs à URL original
			$redirect_url = $this->add_utm_params( $url, $automation_id, $tracking_code );

			$wpdb->insert(
				$wpdb->prefix . 'pcw_link_tracking',
				array(
					'automation_id'     => $automation_id,
					'email_tracking_id' => $email_tracking_id,
					'link_url'          => $url,
					'link_text'         => strip_tags( $text ),
					'link_position'     => $position,
					'tracking_hash'     => $tracking_hash,
					'redirect_url'      => $redirect_url,
					'created_at'        => current_time( 'mysql' ),
				)
			);

			// Criar URL de tracking
			$tracked_url = home_url( '/pcw-track/' . $tracking_hash );

			// Substituir no HTML
			$new_tag = str_replace( 'href="' . $url . '"', 'href="' . $tracked_url . '"', $full_tag );
			$html = str_replace( $full_tag, $new_tag, $html );
		}

		return $html;
	}

	/**
	 * Verificar se deve pular o tracking deste link
	 *
	 * @param string $url URL do link.
	 * @return bool
	 */
	private function should_skip_link( $url ) {
		// Pular links especiais
		$skip_patterns = array(
			'mailto:',
			'tel:',
			'#',
			'javascript:',
			'/pcw-track',
		);

		foreach ( $skip_patterns as $pattern ) {
			if ( strpos( $url, $pattern ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Adicionar parâmetros UTM à URL
	 *
	 * @param string $url URL original.
	 * @param int    $automation_id ID da automação.
	 * @param string $tracking_code Código de tracking.
	 * @return string URL com UTMs.
	 */
	private function add_utm_params( $url, $automation_id, $tracking_code ) {
		$automation = PCW_Automations::instance()->get( $automation_id );
		$utm_campaign = sanitize_title( $automation->name );

		$params = array(
			'utm_source'   => 'automation',
			'utm_medium'   => 'email',
			'utm_campaign' => $utm_campaign,
			'pcw_track'    => $tracking_code,
		);

		return add_query_arg( $params, $url );
	}

	/**
	 * Manipular requisições de tracking
	 */
	public function handle_tracking_request() {
		$request_uri = $_SERVER['REQUEST_URI'];

		// Tracking de clique
		if ( preg_match( '#/pcw-track/([a-zA-Z0-9]+)#', $request_uri, $matches ) ) {
			$this->handle_click_tracking( $matches[1] );
			exit;
		}

		// Tracking de abertura
		if ( preg_match( '#/pcw-track-open/([a-zA-Z0-9]+)#', $request_uri, $matches ) ) {
			$this->handle_open_tracking( $matches[1] );
			exit;
		}
	}

	/**
	 * Manipular tracking de clique
	 *
	 * @param string $tracking_hash Hash do link.
	 */
	private function handle_click_tracking( $tracking_hash ) {
		global $wpdb;

		// Buscar link
		$link = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}pcw_link_tracking WHERE tracking_hash = %s",
			$tracking_hash
		) );

		if ( ! $link ) {
			wp_redirect( home_url() );
			return;
		}

		// Buscar email tracking
		$email_tracking = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}pcw_email_tracking WHERE id = %d",
			$link->email_tracking_id
		) );

		if ( $email_tracking ) {
			// Atualizar contadores
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->prefix}pcw_link_tracking 
				SET clicked_count = clicked_count + 1 
				WHERE id = %d",
				$link->id
			) );

			$now = current_time( 'mysql' );

			// Atualizar email tracking
			$update = array(
				'click_count' => $email_tracking->click_count + 1,
				'last_clicked_at' => $now,
			);

			if ( ! $email_tracking->first_clicked_at ) {
				$update['first_clicked_at'] = $now;
			}

			// Adicionar aos detalhes de cliques
			$clicks_detail = json_decode( $email_tracking->clicks_detail, true ) ?: array();
			$clicks_detail[] = array(
				'link_url'   => $link->link_url,
				'link_text'  => $link->link_text,
				'clicked_at' => $now,
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
				'ip_address' => $this->get_ip_address(),
			);

			$update['clicks_detail'] = wp_json_encode( $clicks_detail );

			$wpdb->update(
				$wpdb->prefix . 'pcw_email_tracking',
				$update,
				array( 'id' => $email_tracking->id )
			);

			// Registrar evento
			$this->log_event(
				$email_tracking->automation_id,
				$email_tracking->execution_id,
				'email_clicked',
				null,
				$email_tracking->user_id,
				$email_tracking->recipient_email,
				array(
					'link_url'  => $link->link_url,
					'link_text' => $link->link_text,
				)
			);

			// Atualizar stats da automação
			PCW_Automations::instance()->increment_stat( $email_tracking->automation_id, 'clicked' );

			// Salvar cookie para atribuição
			$this->set_tracking_cookie( $email_tracking->tracking_code );
		}

		// Redirecionar
		wp_redirect( $link->redirect_url );
		exit;
	}

	/**
	 * Manipular tracking de abertura
	 *
	 * @param string $tracking_code Código de tracking.
	 */
	private function handle_open_tracking( $tracking_code ) {
		global $wpdb;

		// Buscar email tracking
		$email_tracking = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}pcw_email_tracking WHERE tracking_code = %s",
			$tracking_code
		) );

		if ( $email_tracking ) {
			$now = current_time( 'mysql' );

			// Atualizar contadores
			$update = array(
				'open_count' => $email_tracking->open_count + 1,
				'last_opened_at' => $now,
			);

			if ( ! $email_tracking->first_opened_at ) {
				$update['first_opened_at'] = $now;
			}

			// Detectar device e client
			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
			$update['device_type'] = $this->detect_device_type( $user_agent );
			$update['email_client'] = $this->detect_email_client( $user_agent );

			$wpdb->update(
				$wpdb->prefix . 'pcw_email_tracking',
				$update,
				array( 'id' => $email_tracking->id )
			);

			// Registrar evento (somente na primeira abertura)
			if ( ! $email_tracking->first_opened_at ) {
				$this->log_event(
					$email_tracking->automation_id,
					$email_tracking->execution_id,
					'email_opened',
					null,
					$email_tracking->user_id,
					$email_tracking->recipient_email,
					array(
						'device_type'  => $update['device_type'],
						'email_client' => $update['email_client'],
					)
				);

				// Atualizar stats da automação
				PCW_Automations::instance()->increment_stat( $email_tracking->automation_id, 'opened' );
			}
		}

		// Retornar pixel transparente
		header( 'Content-Type: image/gif' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );
		echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
		exit;
	}

	/**
	 * Detectar tipo de dispositivo
	 *
	 * @param string $user_agent User agent.
	 * @return string
	 */
	private function detect_device_type( $user_agent ) {
		if ( preg_match( '/mobile|android|iphone|ipad|ipod/i', $user_agent ) ) {
			if ( preg_match( '/ipad|tablet/i', $user_agent ) ) {
				return 'tablet';
			}
			return 'mobile';
		}
		return 'desktop';
	}

	/**
	 * Detectar cliente de email
	 *
	 * @param string $user_agent User agent.
	 * @return string
	 */
	private function detect_email_client( $user_agent ) {
		$clients = array(
			'Gmail'           => '/gmail/i',
			'Outlook'         => '/outlook|microsoft/i',
			'Apple Mail'      => '/applewebkit.*mail/i',
			'Yahoo Mail'      => '/yahoo/i',
			'Thunderbird'     => '/thunderbird/i',
			'Windows Mail'    => '/windows.*mail/i',
		);

		foreach ( $clients as $name => $pattern ) {
			if ( preg_match( $pattern, $user_agent ) ) {
				return $name;
			}
		}

		return 'Unknown';
	}

	/**
	 * Registrar evento
	 *
	 * @param int    $automation_id ID da automação.
	 * @param int    $execution_id ID da execução.
	 * @param string $event_type Tipo do evento.
	 * @param int    $step_index Índice da etapa (opcional).
	 * @param int    $user_id ID do usuário (opcional).
	 * @param string $email Email (opcional).
	 * @param array  $metadata Metadados extras (opcional).
	 */
	public function log_event( $automation_id, $execution_id, $event_type, $step_index = null, $user_id = null, $email = null, $metadata = array() ) {
		global $wpdb;

		$data = array(
			'automation_id' => $automation_id,
			'execution_id'  => $execution_id,
			'event_type'    => $event_type,
			'step_index'    => $step_index,
			'user_id'       => $user_id,
			'email'         => $email,
			'metadata'      => ! empty( $metadata ) ? wp_json_encode( $metadata ) : null,
			'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 500 ) : null,
			'ip_address'    => $this->get_ip_address(),
			'created_at'    => current_time( 'mysql' ),
		);

		$wpdb->insert(
			$wpdb->prefix . 'pcw_automation_events',
			$data
		);
	}

	/**
	 * Atribuir conversão
	 *
	 * @param int $order_id ID do pedido.
	 */
	public function attribute_conversion( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		// Verificar se já tem atribuição
		$existing = get_post_meta( $order_id, '_pcw_automation_tracking', true );
		if ( $existing ) {
			return;
		}

		// Buscar tracking code do cookie ou URL
		$tracking_code = $this->get_tracking_code();

		if ( ! $tracking_code ) {
			return;
		}

		global $wpdb;

		// Buscar email tracking dentro da janela de atribuição
		$days_ago = date( 'Y-m-d H:i:s', strtotime( '-' . $this->attribution_window . ' days' ) );

		$email_tracking = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}pcw_email_tracking 
			WHERE tracking_code = %s 
			AND created_at >= %s 
			ORDER BY last_clicked_at DESC 
			LIMIT 1",
			$tracking_code,
			$days_ago
		) );

		if ( ! $email_tracking ) {
			return;
		}

		// Obter valor do pedido
		$order = wc_get_order( $order_id );
		$order_total = $order ? $order->get_total() : 0;

		// Atualizar email tracking com conversão
		$wpdb->update(
			$wpdb->prefix . 'pcw_email_tracking',
			array(
				'conversion_at'       => current_time( 'mysql' ),
				'conversion_order_id' => $order_id,
				'conversion_value'    => $order_total,
			),
			array( 'id' => $email_tracking->id )
		);

		// Registrar evento de conversão
		$this->log_event(
			$email_tracking->automation_id,
			$email_tracking->execution_id,
			'conversion',
			null,
			$email_tracking->user_id,
			$email_tracking->recipient_email,
			array(
				'order_id'        => $order_id,
				'order_total'     => $order_total,
				'tracking_code'   => $tracking_code,
			)
		);

		// Atualizar stats da automação
		PCW_Automations::instance()->increment_stat( $email_tracking->automation_id, 'converted' );

		// Salvar metadado no pedido
		update_post_meta( $order_id, '_pcw_automation_tracking', array(
			'automation_id' => $email_tracking->automation_id,
			'execution_id'  => $email_tracking->execution_id,
			'tracking_code' => $tracking_code,
			'attributed_at' => current_time( 'mysql' ),
		) );
	}

	/**
	 * Confirmar conversão quando pedido for completado
	 *
	 * @param int $order_id ID do pedido.
	 */
	public function confirm_conversion( $order_id ) {
		// Apenas registrar evento de pedido completado
		$tracking = get_post_meta( $order_id, '_pcw_automation_tracking', true );
		
		if ( $tracking && isset( $tracking['automation_id'] ) ) {
			$order = wc_get_order( $order_id );
			
			$this->log_event(
				$tracking['automation_id'],
				$tracking['execution_id'],
				'order_completed',
				null,
				$order->get_customer_id(),
				$order->get_billing_email(),
				array(
					'order_id'    => $order_id,
					'order_total' => $order->get_total(),
				)
			);
		}
	}

	/**
	 * Salvar cookie de tracking
	 *
	 * @param string $tracking_code Código de tracking.
	 */
	private function set_tracking_cookie( $tracking_code ) {
		$expire = time() + ( $this->attribution_window * DAY_IN_SECONDS );
		setcookie( 'pcw_track', $tracking_code, $expire, COOKIEPATH, COOKIE_DOMAIN );
	}

	/**
	 * Obter tracking code do cookie ou URL
	 *
	 * @return string|false
	 */
	private function get_tracking_code() {
		// Verificar URL primeiro
		if ( isset( $_GET['pcw_track'] ) ) {
			return sanitize_text_field( $_GET['pcw_track'] );
		}

		// Verificar cookie
		if ( isset( $_COOKIE['pcw_track'] ) ) {
			return sanitize_text_field( $_COOKIE['pcw_track'] );
		}

		return false;
	}

	/**
	 * Gerar código de tracking único
	 *
	 * @return string
	 */
	private function generate_tracking_code() {
		return substr( md5( uniqid( rand(), true ) ), 0, 32 );
	}

	/**
	 * Obter endereço IP do visitante
	 *
	 * @return string
	 */
	private function get_ip_address() {
		$ip = '';

		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return substr( $ip, 0, 45 );
	}

	/**
	 * Definir janela de atribuição
	 *
	 * @param int $days Número de dias.
	 */
	public function set_attribution_window( $days ) {
		$this->attribution_window = absint( $days );
		update_option( 'pcw_automation_attribution_window', $this->attribution_window );
	}

	/**
	 * Obter janela de atribuição
	 *
	 * @return int
	 */
	public function get_attribution_window() {
		return $this->attribution_window;
	}
}
