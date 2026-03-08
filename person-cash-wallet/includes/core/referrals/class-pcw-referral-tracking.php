<?php
/**
 * Classe de tracking de indicações (cookies e cliques)
 *
 * @package PersonCashWallet
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de tracking de indicações
 */
class PCW_Referral_Tracking {

	/**
	 * Nome do cookie de indicação
	 */
	const COOKIE_NAME = 'pcw_referral_code';

	/**
	 * Instância singleton
	 *
	 * @var PCW_Referral_Tracking
	 */
	private static $instance = null;

	/**
	 * Obter instância singleton
	 *
	 * @return PCW_Referral_Tracking
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
	 * Inicializar hooks
	 */
	public function init() {
		// Detectar parâmetro ref na URL e salvar cookie
		add_action( 'init', array( $this, 'detect_referral_code' ), 1 );

		// Registrar endpoint para QR Code
		add_action( 'init', array( $this, 'register_qr_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'handle_qr_endpoint' ) );
	}

	/**
	 * Detectar código de indicação na URL
	 */
	public function detect_referral_code() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['ref'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = sanitize_text_field( wp_unslash( $_GET['ref'] ) );

		if ( empty( $code ) ) {
			return;
		}

		// Validar código
		$codes = PCW_Referral_Codes::instance();
		$validation = $codes->validate_code( $code );

		if ( ! $validation['valid'] ) {
			return;
		}

		// Não sobrescrever cookie existente (para evitar fraude)
		if ( $this->get_cookie() && $this->get_cookie() !== $code ) {
			// Cookie já existe com outro código - manter o original
			// Isso evita que alguém troque o código no último momento
			return;
		}

		// Salvar cookie
		$this->set_cookie( $code );

		// Registrar click
		$this->log_click( $code );

		// Disparar ação
		do_action( 'pcw_referral_link_clicked', $code, $validation['data'] );
	}

	/**
	 * Definir cookie de indicação
	 *
	 * @param string $code Código de indicação.
	 */
	public function set_cookie( $code ) {
		$settings = PCW_Referral_Rewards::instance()->get_settings();
		$days = absint( $settings['cookie_days'] );

		if ( $days <= 0 ) {
			$days = 30;
		}

		$expiration = time() + ( $days * DAY_IN_SECONDS );

		// Definir cookie
		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_NAME,
				sanitize_text_field( $code ),
				$expiration,
				COOKIEPATH,
				COOKIE_DOMAIN,
				is_ssl(),
				true // HttpOnly
			);
		}

		// Também definir em $_COOKIE para uso imediato
		$_COOKIE[ self::COOKIE_NAME ] = $code;
	}

	/**
	 * Obter código do cookie
	 *
	 * @return string|null
	 */
	public function get_cookie() {
		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		}
		return null;
	}

	/**
	 * Remover cookie
	 */
	public function clear_cookie() {
		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_NAME,
				'',
				time() - 3600,
				COOKIEPATH,
				COOKIE_DOMAIN,
				is_ssl(),
				true
			);
		}

		unset( $_COOKIE[ self::COOKIE_NAME ] );
	}

	/**
	 * Registrar click no link de indicação
	 *
	 * @param string $code Código de indicação.
	 */
	public function log_click( $code ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_clicks';

		// Limitar clicks por IP para evitar spam
		$ip = $this->get_client_ip();
		$recent_click = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} 
				WHERE referral_code = %s AND ip_address = %s AND created_at > %s",
				$code,
				$ip,
				gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) )
			)
		);

		if ( $recent_click > 0 ) {
			return; // Já clicou recentemente
		}

		$data = array(
			'referral_code' => sanitize_text_field( $code ),
			'ip_address'    => $ip,
			'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'referrer_url'  => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			'landing_page'  => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '',
			'converted'     => 0,
			'created_at'    => current_time( 'mysql' ),
		);

		$wpdb->insert( $table, $data );
	}

	/**
	 * Obter IP do cliente
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			// Pegar apenas o primeiro IP se houver vários
			if ( strpos( $ip, ',' ) !== false ) {
				$ips = explode( ',', $ip );
				$ip = trim( $ips[0] );
			}
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Obter estatísticas de clicks de um código
	 *
	 * @param string $code Código de indicação.
	 * @param int    $days Dias para análise.
	 * @return array
	 */
	public function get_click_stats( $code, $days = 30 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_clicks';
		$date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE referral_code = %s AND created_at >= %s",
				$code,
				$date_from
			)
		);

		$converted = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE referral_code = %s AND converted = 1 AND created_at >= %s",
				$code,
				$date_from
			)
		);

		$unique_ips = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT ip_address) FROM {$table} WHERE referral_code = %s AND created_at >= %s",
				$code,
				$date_from
			)
		);

		return array(
			'total_clicks'    => $total,
			'unique_visitors' => $unique_ips,
			'converted'       => $converted,
			'conversion_rate' => $total > 0 ? round( ( $converted / $total ) * 100, 1 ) : 0,
		);
	}

	/**
	 * Registrar endpoint para QR Code
	 */
	public function register_qr_endpoint() {
		add_rewrite_rule(
			'^referral-qr/([^/]+)/?$',
			'index.php?pcw_referral_qr=$matches[1]',
			'top'
		);

		add_rewrite_tag( '%pcw_referral_qr%', '([^&]+)' );
	}

	/**
	 * Processar endpoint de QR Code
	 */
	public function handle_qr_endpoint() {
		$code = get_query_var( 'pcw_referral_qr' );

		if ( empty( $code ) ) {
			return;
		}

		$this->generate_qr_code( $code );
		exit;
	}

	/**
	 * Gerar QR Code para o código de indicação
	 *
	 * @param string $code Código de indicação.
	 */
	public function generate_qr_code( $code ) {
		// Validar código
		$codes = PCW_Referral_Codes::instance();
		$code_data = $codes->get_code_by_code( $code );

		if ( ! $code_data ) {
			wp_die( esc_html__( 'Código de indicação inválido.', 'person-cash-wallet' ) );
		}

		$url = $codes->get_referral_link( $code_data->user_id );

		// Usar Google Charts API para gerar QR Code
		$qr_url = 'https://chart.googleapis.com/chart?' . http_build_query( array(
			'chs'  => '300x300',
			'cht'  => 'qr',
			'chl'  => $url,
			'choe' => 'UTF-8',
		) );

		// Redirecionar para a imagem do QR
		wp_redirect( $qr_url );
		exit;
	}

	/**
	 * Obter URL do QR Code
	 *
	 * @param string $code Código de indicação.
	 * @param int    $size Tamanho em pixels.
	 * @return string
	 */
	public function get_qr_code_url( $code, $size = 200 ) {
		$codes = PCW_Referral_Codes::instance();
		$code_data = $codes->get_code_by_code( $code );

		if ( ! $code_data ) {
			return '';
		}

		$url = $codes->get_referral_link( $code_data->user_id );

		// Usar Google Charts API
		return 'https://chart.googleapis.com/chart?' . http_build_query( array(
			'chs'  => $size . 'x' . $size,
			'cht'  => 'qr',
			'chl'  => $url,
			'choe' => 'UTF-8',
		) );
	}

	/**
	 * Gerar links de compartilhamento social
	 *
	 * @param string $code Código de indicação.
	 * @param string $message Mensagem personalizada (opcional).
	 * @return array
	 */
	public function get_share_links( $code, $message = '' ) {
		$codes = PCW_Referral_Codes::instance();
		$code_data = $codes->get_code_by_code( $code );

		if ( ! $code_data ) {
			return array();
		}

		$url = $codes->get_referral_link( $code_data->user_id );
		$site_name = get_bloginfo( 'name' );

		if ( empty( $message ) ) {
			$message = sprintf(
				/* translators: %s: Nome do site */
				__( 'Use meu código de indicação e ganhe benefícios na %s!', 'person-cash-wallet' ),
				$site_name
			);
		}

		$encoded_url = rawurlencode( $url );
		$encoded_message = rawurlencode( $message . ' ' . $url );

		return array(
			'whatsapp' => array(
				'name' => 'WhatsApp',
				'icon' => 'whatsapp',
				'url'  => 'https://api.whatsapp.com/send?text=' . $encoded_message,
				'color' => '#25D366',
			),
			'facebook' => array(
				'name' => 'Facebook',
				'icon' => 'facebook',
				'url'  => 'https://www.facebook.com/sharer/sharer.php?u=' . $encoded_url . '&quote=' . rawurlencode( $message ),
				'color' => '#1877F2',
			),
			'twitter' => array(
				'name' => 'Twitter/X',
				'icon' => 'twitter',
				'url'  => 'https://twitter.com/intent/tweet?text=' . $encoded_message,
				'color' => '#1DA1F2',
			),
			'telegram' => array(
				'name' => 'Telegram',
				'icon' => 'telegram',
				'url'  => 'https://t.me/share/url?url=' . $encoded_url . '&text=' . rawurlencode( $message ),
				'color' => '#0088CC',
			),
			'email' => array(
				'name' => 'Email',
				'icon' => 'email',
				'url'  => 'mailto:?subject=' . rawurlencode( sprintf( __( 'Indicação - %s', 'person-cash-wallet' ), $site_name ) ) . '&body=' . $encoded_message,
				'color' => '#EA4335',
			),
			'copy' => array(
				'name' => __( 'Copiar Link', 'person-cash-wallet' ),
				'icon' => 'copy',
				'url'  => $url,
				'color' => '#6B7280',
			),
		);
	}

	/**
	 * Obter clicks recentes (admin)
	 *
	 * @param array $args Argumentos.
	 * @return array
	 */
	public function get_recent_clicks( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_clicks';

		$defaults = array(
			'limit'  => 50,
			'offset' => 0,
			'code'   => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = '1=1';
		$values = array();

		if ( ! empty( $args['code'] ) ) {
			$where .= ' AND referral_code = %s';
			$values[] = sanitize_text_field( $args['code'] );
		}

		$values[] = absint( $args['limit'] );
		$values[] = absint( $args['offset'] );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$values
			)
		);
	}

	/**
	 * Limpar clicks antigos (cron)
	 *
	 * @param int $days Dias para manter.
	 */
	public function cleanup_old_clicks( $days = 90 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_clicks';
		$date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s AND converted = 0",
				$date
			)
		);
	}
}
