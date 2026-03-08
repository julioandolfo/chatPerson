<?php
/**
 * Rastreador de Origem de Usuários
 *
 * Captura e armazena dados de origem dos visitantes/usuários:
 * - UTM parameters (source, medium, campaign, term, content)
 * - Click IDs (gclid, fbclid, msclkid, etc)
 * - Referrer e landing page
 * - Device info (tipo, OS, browser)
 * - Código de referral
 *
 * @package PersonCashWallet
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de rastreamento de origem de usuários
 */
class PCW_User_Origin_Tracker {

	/**
	 * Nome do cookie de origem
	 */
	const COOKIE_FIRST_TOUCH = 'pcw_first_touch';
	const COOKIE_LAST_TOUCH  = 'pcw_last_touch';
	const COOKIE_SESSION     = 'pcw_origin_session';

	/**
	 * Parâmetros UTM suportados
	 */
	const UTM_PARAMS = array(
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
		'utm_id',
	);

	/**
	 * Click IDs de plataformas de anúncios
	 */
	const CLICK_IDS = array(
		'gclid'    => 'google_ads',
		'gbraid'   => 'google_ads',
		'wbraid'   => 'google_ads',
		'fbclid'   => 'facebook',
		'msclkid'  => 'microsoft_ads',
		'twclid'   => 'twitter',
		'ttclid'   => 'tiktok',
		'li_fat_id' => 'linkedin',
		'mc_cid'   => 'mailchimp',
		'mc_eid'   => 'mailchimp',
		'_kx'      => 'klaviyo',
	);

	/**
	 * Instância singleton
	 *
	 * @var PCW_User_Origin_Tracker
	 */
	private static $instance = null;

	/**
	 * Dados de origem da sessão atual
	 *
	 * @var array
	 */
	private $current_origin = array();

	/**
	 * Obter instância singleton
	 *
	 * @return PCW_User_Origin_Tracker
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor privado (singleton)
	 */
	private function __construct() {
		// Singleton
	}

	/**
	 * Inicializar hooks
	 */
	public function init() {
		// Capturar origem no init (antes de headers serem enviados)
		add_action( 'init', array( $this, 'capture_origin' ), 5 );

		// Enqueue scripts de tracking
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// AJAX endpoints
		add_action( 'wp_ajax_pcw_track_origin', array( $this, 'ajax_track_origin' ) );
		add_action( 'wp_ajax_nopriv_pcw_track_origin', array( $this, 'ajax_track_origin' ) );
		add_action( 'wp_ajax_pcw_get_origin_stats', array( $this, 'ajax_get_origin_stats' ) );

		// Hook no checkout para salvar atribuição
		add_action( 'woocommerce_checkout_order_created', array( $this, 'save_order_attribution' ), 10, 2 );
		
		// Hook alternativo para versões anteriores do WooCommerce
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_order_attribution_legacy' ), 10, 3 );

		// Hook para pay-order (link de pagamento) - quando clica em pagar
		add_action( 'woocommerce_before_pay_action', array( $this, 'save_pay_order_attribution' ), 10, 1 );

		// Hook quando a página pay-order é visualizada (antes de clicar em pagar)
		add_action( 'before_woocommerce_pay', array( $this, 'capture_pay_order_origin' ), 10 );

		// Hook quando usuário faz login - vincular sessão anônima
		add_action( 'wp_login', array( $this, 'link_anonymous_session' ), 10, 2 );

		// Hook quando usuário se registra
		add_action( 'user_register', array( $this, 'save_registration_origin' ), 10, 1 );

		// Cron para limpeza de sessões antigas
		add_action( 'pcw_daily_maintenance', array( $this, 'cleanup_old_sessions' ) );
	}

	/**
	 * Enqueue scripts de tracking
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			'pcw-origin-tracker',
			PCW_PLUGIN_URL . 'assets/js/origin-tracker.js',
			array( 'jquery' ),
			PCW_VERSION,
			true
		);

		// Dados para o JavaScript
		$first_touch = $this->get_first_touch_cookie();
		$last_touch  = $this->get_last_touch_cookie();

		wp_localize_script( 'pcw-origin-tracker', 'pcwOrigin', array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'pcw_origin_tracker' ),
			'sessionId'   => $this->get_or_create_session_id(),
			'userId'      => get_current_user_id(),
			'hasFirstTouch' => ! empty( $first_touch ),
			'utmParams'   => self::UTM_PARAMS,
			'clickIds'    => array_keys( self::CLICK_IDS ),
			'cookieDays'  => $this->get_cookie_days(),
		) );
	}

	/**
	 * Capturar origem do visitante
	 */
	public function capture_origin() {
		// Não capturar em admin ou AJAX
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		// Não capturar bots
		if ( $this->is_bot() ) {
			return;
		}

		// Coletar dados de origem
		$origin_data = $this->collect_origin_data();

		if ( empty( $origin_data ) ) {
			return;
		}

		$this->current_origin = $origin_data;

		// Verificar se tem dados significativos de origem
		$has_origin = ! empty( $origin_data['utm_source'] ) || 
					  ! empty( $origin_data['click_id'] ) || 
					  ! empty( $origin_data['referrer'] ) ||
					  ! empty( $origin_data['referral_code'] );

		// Salvar first touch (apenas se não existir)
		if ( ! $this->has_first_touch() && $has_origin ) {
			$this->save_first_touch( $origin_data );
		}

		// Atualizar last touch (sempre que tiver origem significativa)
		if ( $has_origin ) {
			$this->save_last_touch( $origin_data );
		}

		// Salvar sessão no banco
		$this->save_session( $origin_data );
	}

	/**
	 * Coletar dados de origem da requisição atual
	 *
	 * @return array
	 */
	public function collect_origin_data() {
		$data = array(
			'timestamp'      => current_time( 'mysql' ),
			'session_id'     => $this->get_or_create_session_id(),
			'user_id'        => get_current_user_id() ?: null,
			'ip_address'     => $this->get_client_ip(),
			'user_agent'     => $this->get_user_agent(),
			'referrer'       => $this->get_referrer(),
			'referrer_domain'=> $this->get_referrer_domain(),
			'landing_page'   => $this->get_current_url(),
			'page_path'      => $this->get_current_path(),
		);

		// Coletar UTM parameters
		foreach ( self::UTM_PARAMS as $param ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$data[ $param ] = isset( $_GET[ $param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) : '';
		}

		// Coletar Click IDs
		$data['click_id']       = '';
		$data['click_id_type']  = '';
		$data['click_platform'] = '';

		foreach ( self::CLICK_IDS as $param => $platform ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET[ $param ] ) && ! empty( $_GET[ $param ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$data['click_id']       = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
				$data['click_id_type']  = $param;
				$data['click_platform'] = $platform;
				break; // Pegar apenas o primeiro
			}
		}

		// Código de referral
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data['referral_code'] = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';

		// Tracking de automação/campanha de email
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data['email_tracking_id'] = isset( $_GET['pcw_track'] ) ? sanitize_text_field( wp_unslash( $_GET['pcw_track'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data['automation_id'] = isset( $_GET['pcw_auto'] ) ? absint( $_GET['pcw_auto'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data['campaign_id'] = isset( $_GET['pcw_camp'] ) ? absint( $_GET['pcw_camp'] ) : 0;

		// Dados do dispositivo
		$device_info = $this->parse_user_agent();
		$data['device_type']    = $device_info['device_type'];
		$data['device_os']      = $device_info['os'];
		$data['device_browser'] = $device_info['browser'];

		// Determinar source/medium se não foram passados
		if ( empty( $data['utm_source'] ) ) {
			$inferred = $this->infer_source_medium( $data );
			$data['utm_source'] = $inferred['source'];
			$data['utm_medium'] = $inferred['medium'];
		}

		// Canal de aquisição
		$data['channel'] = $this->determine_channel( $data );

		return $data;
	}

	/**
	 * Inferir source/medium baseado em outros dados
	 *
	 * @param array $data Dados coletados.
	 * @return array
	 */
	private function infer_source_medium( $data ) {
		$source = '';
		$medium = '';

		// Se tem click ID, inferir de lá
		if ( ! empty( $data['click_platform'] ) ) {
			$platform_map = array(
				'google_ads'    => array( 'google', 'cpc' ),
				'facebook'      => array( 'facebook', 'cpc' ),
				'microsoft_ads' => array( 'bing', 'cpc' ),
				'twitter'       => array( 'twitter', 'cpc' ),
				'tiktok'        => array( 'tiktok', 'cpc' ),
				'linkedin'      => array( 'linkedin', 'cpc' ),
				'mailchimp'     => array( 'mailchimp', 'email' ),
				'klaviyo'       => array( 'klaviyo', 'email' ),
			);

			if ( isset( $platform_map[ $data['click_platform'] ] ) ) {
				return array(
					'source' => $platform_map[ $data['click_platform'] ][0],
					'medium' => $platform_map[ $data['click_platform'] ][1],
				);
			}
		}

		// Se tem tracking de email
		if ( ! empty( $data['email_tracking_id'] ) || ! empty( $data['automation_id'] ) || ! empty( $data['campaign_id'] ) ) {
			return array(
				'source' => 'pcw_email',
				'medium' => 'email',
			);
		}

		// Se tem código de referral
		if ( ! empty( $data['referral_code'] ) ) {
			return array(
				'source' => 'referral',
				'medium' => 'referral',
			);
		}

		// Inferir do referrer
		if ( ! empty( $data['referrer_domain'] ) ) {
			$domain = strtolower( $data['referrer_domain'] );

			// Redes sociais
			$social_domains = array(
				'facebook.com' => 'facebook',
				'fb.com'       => 'facebook',
				'm.facebook.com' => 'facebook',
				'l.facebook.com' => 'facebook',
				'instagram.com' => 'instagram',
				'l.instagram.com' => 'instagram',
				'twitter.com'  => 'twitter',
				't.co'         => 'twitter',
				'linkedin.com' => 'linkedin',
				'lnkd.in'      => 'linkedin',
				'youtube.com'  => 'youtube',
				'youtu.be'     => 'youtube',
				'pinterest.com' => 'pinterest',
				'pin.it'       => 'pinterest',
				'tiktok.com'   => 'tiktok',
				'reddit.com'   => 'reddit',
				'tumblr.com'   => 'tumblr',
			);

			foreach ( $social_domains as $social_domain => $social_name ) {
				if ( strpos( $domain, $social_domain ) !== false ) {
					return array(
						'source' => $social_name,
						'medium' => 'social',
					);
				}
			}

			// Buscadores
			$search_domains = array(
				'google.'      => 'google',
				'bing.com'     => 'bing',
				'yahoo.'       => 'yahoo',
				'duckduckgo.com' => 'duckduckgo',
				'baidu.com'    => 'baidu',
				'yandex.'      => 'yandex',
			);

			foreach ( $search_domains as $search_domain => $search_name ) {
				if ( strpos( $domain, $search_domain ) !== false ) {
					return array(
						'source' => $search_name,
						'medium' => 'organic',
					);
				}
			}

			// Email providers
			$email_domains = array(
				'mail.google.com' => 'gmail',
				'outlook.'     => 'outlook',
				'mail.yahoo.'  => 'yahoo_mail',
			);

			foreach ( $email_domains as $email_domain => $email_name ) {
				if ( strpos( $domain, $email_domain ) !== false ) {
					return array(
						'source' => $email_name,
						'medium' => 'email',
					);
				}
			}

			// Outro site (referral)
			return array(
				'source' => $data['referrer_domain'],
				'medium' => 'referral',
			);
		}

		// Acesso direto
		return array(
			'source' => '(direct)',
			'medium' => '(none)',
		);
	}

	/**
	 * Determinar canal de aquisição
	 *
	 * @param array $data Dados coletados.
	 * @return string
	 */
	private function determine_channel( $data ) {
		$source = strtolower( $data['utm_source'] ?? '' );
		$medium = strtolower( $data['utm_medium'] ?? '' );

		// Direct
		if ( $source === '(direct)' || ( empty( $source ) && empty( $data['referrer'] ) ) ) {
			return 'direct';
		}

		// Paid Search
		if ( in_array( $medium, array( 'cpc', 'ppc', 'paidsearch', 'paid_search' ), true ) ) {
			return 'paid_search';
		}

		// Paid Social
		if ( $medium === 'paid_social' || ( in_array( $medium, array( 'cpc', 'ppc' ), true ) && in_array( $source, array( 'facebook', 'instagram', 'twitter', 'linkedin', 'tiktok', 'pinterest' ), true ) ) ) {
			return 'paid_social';
		}

		// Display
		if ( in_array( $medium, array( 'display', 'cpm', 'banner' ), true ) ) {
			return 'display';
		}

		// Email
		if ( $medium === 'email' || strpos( $source, 'mail' ) !== false ) {
			return 'email';
		}

		// Organic Social
		if ( $medium === 'social' || in_array( $source, array( 'facebook', 'instagram', 'twitter', 'linkedin', 'youtube', 'pinterest', 'tiktok', 'reddit' ), true ) ) {
			return 'organic_social';
		}

		// Referral (incluindo programa de indicações)
		if ( $medium === 'referral' || ! empty( $data['referral_code'] ) ) {
			return 'referral';
		}

		// Organic Search
		if ( $medium === 'organic' || in_array( $source, array( 'google', 'bing', 'yahoo', 'duckduckgo', 'baidu', 'yandex' ), true ) ) {
			return 'organic_search';
		}

		// Affiliates
		if ( in_array( $medium, array( 'affiliate', 'affiliates' ), true ) ) {
			return 'affiliates';
		}

		// Other
		return 'other';
	}

	/**
	 * Verificar se já tem first touch
	 *
	 * @return bool
	 */
	public function has_first_touch() {
		$cookie = $this->get_first_touch_cookie();
		return ! empty( $cookie );
	}

	/**
	 * Obter dados do first touch cookie
	 *
	 * @return array|null
	 */
	public function get_first_touch_cookie() {
		if ( ! isset( $_COOKIE[ self::COOKIE_FIRST_TOUCH ] ) ) {
			return null;
		}

		$data = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_FIRST_TOUCH ] ) );
		$decoded = json_decode( base64_decode( $data ), true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Obter dados do last touch cookie
	 *
	 * @return array|null
	 */
	public function get_last_touch_cookie() {
		if ( ! isset( $_COOKIE[ self::COOKIE_LAST_TOUCH ] ) ) {
			return null;
		}

		$data = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_LAST_TOUCH ] ) );
		$decoded = json_decode( base64_decode( $data ), true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Salvar first touch cookie
	 *
	 * @param array $data Dados de origem.
	 */
	private function save_first_touch( $data ) {
		$cookie_data = $this->prepare_cookie_data( $data );
		$encoded = base64_encode( wp_json_encode( $cookie_data ) );
		$expiration = time() + ( $this->get_cookie_days() * DAY_IN_SECONDS );

		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_FIRST_TOUCH,
				$encoded,
				$expiration,
				COOKIEPATH,
				COOKIE_DOMAIN,
				is_ssl(),
				false // Não httpOnly para permitir leitura via JS
			);
		}

		$_COOKIE[ self::COOKIE_FIRST_TOUCH ] = $encoded;
	}

	/**
	 * Salvar last touch cookie
	 *
	 * @param array $data Dados de origem.
	 */
	private function save_last_touch( $data ) {
		$cookie_data = $this->prepare_cookie_data( $data );
		$encoded = base64_encode( wp_json_encode( $cookie_data ) );
		$expiration = time() + ( 30 * DAY_IN_SECONDS ); // 30 dias para last touch

		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_LAST_TOUCH,
				$encoded,
				$expiration,
				COOKIEPATH,
				COOKIE_DOMAIN,
				is_ssl(),
				false
			);
		}

		$_COOKIE[ self::COOKIE_LAST_TOUCH ] = $encoded;
	}

	/**
	 * Preparar dados para cookie (versão reduzida)
	 *
	 * @param array $data Dados completos.
	 * @return array
	 */
	private function prepare_cookie_data( $data ) {
		return array(
			'ts'   => $data['timestamp'],
			'src'  => $data['utm_source'] ?? '',
			'med'  => $data['utm_medium'] ?? '',
			'cmp'  => $data['utm_campaign'] ?? '',
			'trm'  => $data['utm_term'] ?? '',
			'cnt'  => $data['utm_content'] ?? '',
			'cid'  => $data['click_id'] ?? '',
			'cit'  => $data['click_id_type'] ?? '',
			'ref'  => $data['referrer_domain'] ?? '',
			'rfc'  => $data['referral_code'] ?? '',
			'lp'   => $data['page_path'] ?? '',
			'ch'   => $data['channel'] ?? '',
			'aid'  => $data['automation_id'] ?? 0,
			'cpid' => $data['campaign_id'] ?? 0,
		);
	}

	/**
	 * Salvar sessão no banco de dados
	 *
	 * @param array $data Dados de origem.
	 */
	private function save_session( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_user_sessions';

		// Verificar se tabela existe
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
		if ( ! $table_exists ) {
			return;
		}

		// Verificar se já existe sessão recente com mesmos dados (evitar duplicatas)
		$recent = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} 
				WHERE session_id = %s AND created_at > %s",
				$data['session_id'],
				gmdate( 'Y-m-d H:i:s', strtotime( '-5 minutes' ) )
			)
		);

		if ( $recent ) {
			// Atualizar sessão existente
			$wpdb->update(
				$table,
				array(
					'pages_viewed'  => $wpdb->get_var( $wpdb->prepare( "SELECT pages_viewed FROM {$table} WHERE id = %d", $recent ) ) + 1,
					'last_activity' => current_time( 'mysql' ),
					'last_page'     => $data['landing_page'],
				),
				array( 'id' => $recent ),
				array( '%d', '%s', '%s' ),
				array( '%d' )
			);
			return;
		}

		// Inserir nova sessão
		$insert_data = array(
			'session_id'        => $data['session_id'],
			'user_id'           => $data['user_id'],
			'ip_address'        => $data['ip_address'],
			'user_agent'        => substr( $data['user_agent'], 0, 500 ),
			'utm_source'        => $data['utm_source'] ?? '',
			'utm_medium'        => $data['utm_medium'] ?? '',
			'utm_campaign'      => $data['utm_campaign'] ?? '',
			'utm_term'          => $data['utm_term'] ?? '',
			'utm_content'       => $data['utm_content'] ?? '',
			'utm_id'            => $data['utm_id'] ?? '',
			'click_id'          => $data['click_id'] ?? '',
			'click_id_type'     => $data['click_id_type'] ?? '',
			'click_platform'    => $data['click_platform'] ?? '',
			'referrer'          => substr( $data['referrer'] ?? '', 0, 500 ),
			'referrer_domain'   => $data['referrer_domain'] ?? '',
			'landing_page'      => substr( $data['landing_page'] ?? '', 0, 500 ),
			'referral_code'     => $data['referral_code'] ?? '',
			'email_tracking_id' => $data['email_tracking_id'] ?? '',
			'automation_id'     => $data['automation_id'] ?? 0,
			'campaign_id'       => $data['campaign_id'] ?? 0,
			'channel'           => $data['channel'] ?? '',
			'device_type'       => $data['device_type'] ?? '',
			'device_os'         => $data['device_os'] ?? '',
			'device_browser'    => $data['device_browser'] ?? '',
			'pages_viewed'      => 1,
			'is_first_visit'    => $this->is_first_visit() ? 1 : 0,
			'created_at'        => current_time( 'mysql' ),
			'last_activity'     => current_time( 'mysql' ),
			'last_page'         => $data['landing_page'],
		);

		$wpdb->insert( $table, $insert_data );
	}

	/**
	 * Verificar se é primeira visita do usuário
	 *
	 * @return bool
	 */
	private function is_first_visit() {
		return ! isset( $_COOKIE[ self::COOKIE_FIRST_TOUCH ] );
	}

	/**
	 * Salvar atribuição de origem no pedido
	 *
	 * @param WC_Order $order Pedido.
	 * @param array    $data Dados do checkout (opcional).
	 */
	public function save_order_attribution( $order, $data = array() ) {
		if ( ! $order ) {
			return;
		}

		$order_id = $order->get_id();
		$user_id  = $order->get_user_id();

		// Obter dados de first e last touch
		$first_touch = $this->get_first_touch_cookie();
		$last_touch  = $this->get_last_touch_cookie();

		// Obter dados da sessão atual
		$current = $this->current_origin;
		if ( empty( $current ) ) {
			$current = $this->collect_origin_data();
		}

		// Preparar dados de atribuição
		$attribution = array(
			// First Touch Attribution
			'first_touch_source'    => $first_touch['src'] ?? '',
			'first_touch_medium'    => $first_touch['med'] ?? '',
			'first_touch_campaign'  => $first_touch['cmp'] ?? '',
			'first_touch_term'      => $first_touch['trm'] ?? '',
			'first_touch_content'   => $first_touch['cnt'] ?? '',
			'first_touch_channel'   => $first_touch['ch'] ?? '',
			'first_touch_referrer'  => $first_touch['ref'] ?? '',
			'first_touch_landing'   => $first_touch['lp'] ?? '',
			'first_touch_timestamp' => $first_touch['ts'] ?? '',

			// Last Touch Attribution
			'last_touch_source'     => $last_touch['src'] ?? $current['utm_source'] ?? '',
			'last_touch_medium'     => $last_touch['med'] ?? $current['utm_medium'] ?? '',
			'last_touch_campaign'   => $last_touch['cmp'] ?? $current['utm_campaign'] ?? '',
			'last_touch_term'       => $last_touch['trm'] ?? $current['utm_term'] ?? '',
			'last_touch_content'    => $last_touch['cnt'] ?? $current['utm_content'] ?? '',
			'last_touch_channel'    => $last_touch['ch'] ?? $current['channel'] ?? '',
			'last_touch_referrer'   => $last_touch['ref'] ?? $current['referrer_domain'] ?? '',
			'last_touch_timestamp'  => $last_touch['ts'] ?? current_time( 'mysql' ),

			// Click IDs
			'gclid'                 => '',
			'fbclid'                => '',

			// Referral
			'referral_code'         => $last_touch['rfc'] ?? $current['referral_code'] ?? '',

			// Email/Automação
			'automation_id'         => $last_touch['aid'] ?? $current['automation_id'] ?? 0,
			'campaign_id'           => $last_touch['cpid'] ?? $current['campaign_id'] ?? 0,

			// Device
			'device_type'           => $current['device_type'] ?? '',
			'device_os'             => $current['device_os'] ?? '',
			'device_browser'        => $current['device_browser'] ?? '',

			// Session
			'session_id'            => $current['session_id'] ?? '',
			'ip_address'            => $current['ip_address'] ?? '',
		);

		// Extrair click IDs
		if ( ! empty( $last_touch['cit'] ) && ! empty( $last_touch['cid'] ) ) {
			$attribution[ $last_touch['cit'] ] = $last_touch['cid'];
		}
		if ( ! empty( $current['click_id_type'] ) && ! empty( $current['click_id'] ) ) {
			$attribution[ $current['click_id_type'] ] = $current['click_id'];
		}

		// Salvar como meta do pedido
		foreach ( $attribution as $key => $value ) {
			if ( ! empty( $value ) ) {
				$order->update_meta_data( '_pcw_attr_' . $key, $value );
			}
		}

		// Salvar JSON completo também
		$order->update_meta_data( '_pcw_attribution', wp_json_encode( $attribution ) );
		$order->update_meta_data( '_pcw_attribution_version', '1.0' );
		$order->save();

		// Salvar na tabela de atribuições
		$this->save_attribution_to_db( $order_id, $user_id, $attribution );

		// Disparar ação
		do_action( 'pcw_order_attribution_saved', $order_id, $attribution );
	}

	/**
	 * Salvar atribuição (versão legada para WooCommerce antigo)
	 *
	 * @param int   $order_id ID do pedido.
	 * @param array $posted_data Dados postados.
	 * @param WC_Order $order Pedido.
	 */
	public function save_order_attribution_legacy( $order_id, $posted_data, $order ) {
		// Verificar se já foi salvo
		if ( $order->get_meta( '_pcw_attribution' ) ) {
			return;
		}

		$this->save_order_attribution( $order );
	}

	/**
	 * Capturar origem quando cliente visualiza página pay-order
	 * 
	 * Isso acontece antes do cliente clicar em pagar,
	 * garantindo que capturamos os cookies de origem.
	 */
	public function capture_pay_order_origin() {
		global $wp;

		// Obter order_id da URL
		if ( ! isset( $wp->query_vars['order-pay'] ) ) {
			return;
		}

		$order_id = absint( $wp->query_vars['order-pay'] );
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Verificar se devemos atualizar a atribuição
		$this->maybe_update_pay_order_attribution( $order );
	}

	/**
	 * Verificar e atualizar atribuição de pay-order se necessário
	 *
	 * @param WC_Order $order Pedido.
	 */
	private function maybe_update_pay_order_attribution( $order ) {
		// Obter dados de first e last touch do cookie do cliente
		$first_touch = $this->get_first_touch_cookie();
		$last_touch  = $this->get_last_touch_cookie();

		// Se o cliente não tem cookies de origem, não há nada a fazer
		if ( empty( $first_touch ) && empty( $last_touch ) ) {
			return;
		}

		// Verificar atribuição atual
		$current_attribution = $order->get_meta( '_pcw_attribution' );
		$current_data = $current_attribution ? json_decode( $current_attribution, true ) : null;

		// Determinar se devemos atualizar
		$should_update = false;

		if ( ! $current_attribution ) {
			// Não tem atribuição, salvar nova
			$should_update = true;
		} elseif ( $current_data ) {
			// Verificar se a atribuição atual é genérica
			$current_source = strtolower( $current_data['last_touch_source'] ?? '' );
			$generic_sources = array( '', '(direct)', 'admin', 'wc-admin', 'wp-admin', 'typein' );

			if ( in_array( $current_source, $generic_sources, true ) ) {
				$should_update = true;
			}
		}

		if ( $should_update ) {
			$this->update_order_attribution( $order, $first_touch, $last_touch );
		}
	}

	/**
	 * Salvar atribuição para pedidos pay-order
	 * 
	 * Importante: Em pedidos criados manualmente pelo admin,
	 * a atribuição fica como "admin". Quando o cliente acessa
	 * o link de pagamento, podemos capturar a origem real dele
	 * através dos cookies que foram salvos quando ele visitou o site.
	 *
	 * @param WC_Order $order Pedido.
	 */
	public function save_pay_order_attribution( $order ) {
		// Usar o mesmo método que já verifica e atualiza
		$this->maybe_update_pay_order_attribution( $order );
	}

	/**
	 * Atualizar atribuição do pedido com dados do cliente
	 *
	 * @param WC_Order $order Pedido.
	 * @param array    $first_touch Dados de first touch.
	 * @param array    $last_touch Dados de last touch.
	 */
	private function update_order_attribution( $order, $first_touch, $last_touch ) {
		$order_id = $order->get_id();
		$user_id  = $order->get_user_id();

		// Obter dados da sessão atual
		$current = $this->current_origin;
		if ( empty( $current ) ) {
			$current = $this->collect_origin_data();
		}

		// Preparar dados de atribuição
		$attribution = array(
			// First Touch Attribution
			'first_touch_source'    => $first_touch['src'] ?? '',
			'first_touch_medium'    => $first_touch['med'] ?? '',
			'first_touch_campaign'  => $first_touch['cmp'] ?? '',
			'first_touch_term'      => $first_touch['trm'] ?? '',
			'first_touch_content'   => $first_touch['cnt'] ?? '',
			'first_touch_channel'   => $first_touch['ch'] ?? '',
			'first_touch_referrer'  => $first_touch['ref'] ?? '',
			'first_touch_landing'   => $first_touch['lp'] ?? '',
			'first_touch_timestamp' => $first_touch['ts'] ?? '',

			// Last Touch Attribution
			'last_touch_source'     => $last_touch['src'] ?? $first_touch['src'] ?? '',
			'last_touch_medium'     => $last_touch['med'] ?? $first_touch['med'] ?? '',
			'last_touch_campaign'   => $last_touch['cmp'] ?? $first_touch['cmp'] ?? '',
			'last_touch_term'       => $last_touch['trm'] ?? $first_touch['trm'] ?? '',
			'last_touch_content'    => $last_touch['cnt'] ?? $first_touch['cnt'] ?? '',
			'last_touch_channel'    => $last_touch['ch'] ?? $first_touch['ch'] ?? '',
			'last_touch_referrer'   => $last_touch['ref'] ?? $first_touch['ref'] ?? '',
			'last_touch_timestamp'  => $last_touch['ts'] ?? current_time( 'mysql' ),

			// Click IDs
			'gclid'                 => '',
			'fbclid'                => '',

			// Referral
			'referral_code'         => $last_touch['rfc'] ?? $first_touch['rfc'] ?? '',

			// Device
			'device_type'           => $current['device_type'] ?? '',
			'device_os'             => $current['device_os'] ?? '',
			'device_browser'        => $current['device_browser'] ?? '',

			// Session
			'session_id'            => $current['session_id'] ?? '',
			'ip_address'            => $current['ip_address'] ?? '',

			// Marcador especial
			'updated_from_pay_order' => true,
		);

		// Extrair click IDs
		if ( ! empty( $last_touch['cit'] ) && ! empty( $last_touch['cid'] ) ) {
			$attribution[ $last_touch['cit'] ] = $last_touch['cid'];
		}
		if ( ! empty( $first_touch['cit'] ) && ! empty( $first_touch['cid'] ) ) {
			$attribution[ $first_touch['cit'] ] = $first_touch['cid'];
		}

		// Atualizar meta do pedido
		foreach ( $attribution as $key => $value ) {
			if ( ! empty( $value ) ) {
				$order->update_meta_data( '_pcw_attr_' . $key, $value );
			}
		}

		// Atualizar JSON completo
		$order->update_meta_data( '_pcw_attribution', wp_json_encode( $attribution ) );
		$order->update_meta_data( '_pcw_attribution_updated', current_time( 'mysql' ) );
		$order->save();

		// Atualizar na tabela de atribuições
		$this->update_attribution_in_db( $order_id, $user_id, $attribution );

		// Disparar ação
		do_action( 'pcw_order_attribution_updated', $order_id, $attribution );
	}

	/**
	 * Atualizar atribuição na tabela do banco
	 *
	 * @param int   $order_id ID do pedido.
	 * @param int   $user_id ID do usuário.
	 * @param array $attribution Dados de atribuição.
	 */
	private function update_attribution_in_db( $order_id, $user_id, $attribution ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_order_attributions';

		// Verificar se tabela existe
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
		if ( ! $table_exists ) {
			return;
		}

		// Verificar se já existe registro
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE order_id = %d",
				$order_id
			)
		);

		$data = array(
			'user_id'               => $user_id ?: null,
			'first_touch_source'    => $attribution['first_touch_source'] ?? '',
			'first_touch_medium'    => $attribution['first_touch_medium'] ?? '',
			'first_touch_campaign'  => $attribution['first_touch_campaign'] ?? '',
			'first_touch_channel'   => $attribution['first_touch_channel'] ?? '',
			'first_touch_referrer'  => $attribution['first_touch_referrer'] ?? '',
			'last_touch_source'     => $attribution['last_touch_source'] ?? '',
			'last_touch_medium'     => $attribution['last_touch_medium'] ?? '',
			'last_touch_campaign'   => $attribution['last_touch_campaign'] ?? '',
			'last_touch_channel'    => $attribution['last_touch_channel'] ?? '',
			'last_touch_referrer'   => $attribution['last_touch_referrer'] ?? '',
			'gclid'                 => $attribution['gclid'] ?? '',
			'fbclid'                => $attribution['fbclid'] ?? '',
			'referral_code'         => $attribution['referral_code'] ?? '',
		);

		if ( $existing ) {
			// Atualizar registro existente
			$wpdb->update(
				$table,
				$data,
				array( 'order_id' => $order_id )
			);
		} else {
			// Inserir novo registro
			$data['order_id'] = $order_id;
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
		}
	}

	/**
	 * Salvar atribuição na tabela do banco
	 *
	 * @param int   $order_id ID do pedido.
	 * @param int   $user_id ID do usuário.
	 * @param array $attribution Dados de atribuição.
	 */
	private function save_attribution_to_db( $order_id, $user_id, $attribution ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_order_attributions';

		// Verificar se tabela existe
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
		if ( ! $table_exists ) {
			return;
		}

		$wpdb->insert(
			$table,
			array(
				'order_id'              => $order_id,
				'user_id'               => $user_id ?: null,
				'session_id'            => $attribution['session_id'] ?? '',
				'first_touch_source'    => $attribution['first_touch_source'] ?? '',
				'first_touch_medium'    => $attribution['first_touch_medium'] ?? '',
				'first_touch_campaign'  => $attribution['first_touch_campaign'] ?? '',
				'first_touch_channel'   => $attribution['first_touch_channel'] ?? '',
				'first_touch_referrer'  => $attribution['first_touch_referrer'] ?? '',
				'first_touch_landing'   => $attribution['first_touch_landing'] ?? '',
				'first_touch_timestamp' => $attribution['first_touch_timestamp'] ?: null,
				'last_touch_source'     => $attribution['last_touch_source'] ?? '',
				'last_touch_medium'     => $attribution['last_touch_medium'] ?? '',
				'last_touch_campaign'   => $attribution['last_touch_campaign'] ?? '',
				'last_touch_channel'    => $attribution['last_touch_channel'] ?? '',
				'last_touch_referrer'   => $attribution['last_touch_referrer'] ?? '',
				'last_touch_timestamp'  => $attribution['last_touch_timestamp'] ?: null,
				'gclid'                 => $attribution['gclid'] ?? '',
				'fbclid'                => $attribution['fbclid'] ?? '',
				'referral_code'         => $attribution['referral_code'] ?? '',
				'automation_id'         => $attribution['automation_id'] ?? 0,
				'campaign_id'           => $attribution['campaign_id'] ?? 0,
				'device_type'           => $attribution['device_type'] ?? '',
				'device_os'             => $attribution['device_os'] ?? '',
				'device_browser'        => $attribution['device_browser'] ?? '',
				'ip_address'            => $attribution['ip_address'] ?? '',
				'attribution_data'      => wp_json_encode( $attribution ),
				'created_at'            => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Vincular sessão anônima quando usuário faz login
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user Usuário.
	 */
	public function link_anonymous_session( $user_login, $user ) {
		global $wpdb;

		$session_id = $this->get_or_create_session_id();
		$table = $wpdb->prefix . 'pcw_user_sessions';

		// Atualizar sessões anônimas com o user_id
		$wpdb->update(
			$table,
			array( 'user_id' => $user->ID ),
			array(
				'session_id' => $session_id,
				'user_id'    => null,
			),
			array( '%d' ),
			array( '%s', '%d' )
		);

		// Salvar first touch como user meta se não existir
		$first_touch = $this->get_first_touch_cookie();
		if ( $first_touch && ! get_user_meta( $user->ID, '_pcw_first_touch', true ) ) {
			update_user_meta( $user->ID, '_pcw_first_touch', $first_touch );
			update_user_meta( $user->ID, '_pcw_first_touch_date', current_time( 'mysql' ) );
		}
	}

	/**
	 * Salvar origem no registro do usuário
	 *
	 * @param int $user_id ID do usuário.
	 */
	public function save_registration_origin( $user_id ) {
		$first_touch = $this->get_first_touch_cookie();
		$last_touch  = $this->get_last_touch_cookie();
		$current     = $this->current_origin;

		if ( empty( $current ) ) {
			$current = $this->collect_origin_data();
		}

		// Salvar como user meta
		if ( $first_touch ) {
			update_user_meta( $user_id, '_pcw_first_touch', $first_touch );
		}

		if ( $last_touch ) {
			update_user_meta( $user_id, '_pcw_registration_touch', $last_touch );
		}

		update_user_meta( $user_id, '_pcw_registration_channel', $current['channel'] ?? '' );
		update_user_meta( $user_id, '_pcw_registration_source', $current['utm_source'] ?? '' );
		update_user_meta( $user_id, '_pcw_registration_medium', $current['utm_medium'] ?? '' );
		update_user_meta( $user_id, '_pcw_registration_campaign', $current['utm_campaign'] ?? '' );
		update_user_meta( $user_id, '_pcw_registration_referral', $current['referral_code'] ?? '' );
		update_user_meta( $user_id, '_pcw_registration_date', current_time( 'mysql' ) );
	}

	/**
	 * AJAX: Track origin via JavaScript
	 */
	public function ajax_track_origin() {
		check_ajax_referer( 'pcw_origin_tracker', 'nonce' );

		// Coletar dados enviados pelo JS
		$client_data = array();
		
		if ( isset( $_POST['screen_width'] ) ) {
			$client_data['screen_width'] = absint( $_POST['screen_width'] );
		}
		if ( isset( $_POST['screen_height'] ) ) {
			$client_data['screen_height'] = absint( $_POST['screen_height'] );
		}
		if ( isset( $_POST['timezone'] ) ) {
			$client_data['timezone'] = sanitize_text_field( wp_unslash( $_POST['timezone'] ) );
		}
		if ( isset( $_POST['language'] ) ) {
			$client_data['language'] = sanitize_text_field( wp_unslash( $_POST['language'] ) );
		}

		// Atualizar sessão com dados do cliente
		if ( ! empty( $client_data ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'pcw_user_sessions';
			$session_id = $this->get_or_create_session_id();

			$wpdb->update(
				$table,
				array(
					'screen_resolution' => ( $client_data['screen_width'] ?? 0 ) . 'x' . ( $client_data['screen_height'] ?? 0 ),
					'timezone'          => $client_data['timezone'] ?? '',
					'language'          => $client_data['language'] ?? '',
				),
				array( 'session_id' => $session_id ),
				array( '%s', '%s', '%s' ),
				array( '%s' )
			);
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Obter estatísticas de origem (admin)
	 */
	public function ajax_get_origin_stats() {
		check_ajax_referer( 'pcw_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		$period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : '30days';
		$stats = $this->get_stats( $period );

		wp_send_json_success( $stats );
	}

	/**
	 * Obter estatísticas de origem
	 *
	 * @param string $period Período.
	 * @return array
	 */
	public function get_stats( $period = '30days' ) {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'pcw_user_sessions';
		$orders_table = $wpdb->prefix . 'pcw_order_attributions';

		// Determinar data inicial
		switch ( $period ) {
			case 'today':
				$start_date = date( 'Y-m-d 00:00:00' );
				break;
			case '7days':
				$start_date = date( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );
				break;
			case '30days':
				$start_date = date( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );
				break;
			case '90days':
				$start_date = date( 'Y-m-d 00:00:00', strtotime( '-90 days' ) );
				break;
			default:
				$start_date = date( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );
		}

		$stats = array(
			'period'     => $period,
			'start_date' => $start_date,
		);

		// Verificar se tabelas existem
		$sessions_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$sessions_table}'" ) === $sessions_table;
		$orders_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$orders_table}'" ) === $orders_table;

		if ( $sessions_exists ) {
			// Sessões por canal
			$stats['sessions_by_channel'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT channel, COUNT(*) as sessions, COUNT(DISTINCT session_id) as unique_sessions
					 FROM {$sessions_table}
					 WHERE created_at >= %s AND channel != ''
					 GROUP BY channel
					 ORDER BY sessions DESC",
					$start_date
				),
				ARRAY_A
			);

			// Sessões por source
			$stats['sessions_by_source'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT utm_source as source, utm_medium as medium, 
					        COUNT(*) as sessions, COUNT(DISTINCT session_id) as unique_sessions
					 FROM {$sessions_table}
					 WHERE created_at >= %s AND utm_source != ''
					 GROUP BY utm_source, utm_medium
					 ORDER BY sessions DESC
					 LIMIT 20",
					$start_date
				),
				ARRAY_A
			);

			// Sessões por campanha
			$stats['sessions_by_campaign'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT utm_campaign as campaign, utm_source as source,
					        COUNT(*) as sessions, COUNT(DISTINCT session_id) as unique_sessions
					 FROM {$sessions_table}
					 WHERE created_at >= %s AND utm_campaign != ''
					 GROUP BY utm_campaign, utm_source
					 ORDER BY sessions DESC
					 LIMIT 20",
					$start_date
				),
				ARRAY_A
			);

			// Sessões por dispositivo
			$stats['sessions_by_device'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT device_type, COUNT(*) as sessions
					 FROM {$sessions_table}
					 WHERE created_at >= %s AND device_type != ''
					 GROUP BY device_type
					 ORDER BY sessions DESC",
					$start_date
				),
				ARRAY_A
			);

			// Total de sessões
			$stats['total_sessions'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$sessions_table} WHERE created_at >= %s",
					$start_date
				)
			);

			// Sessões únicas
			$stats['unique_sessions'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT session_id) FROM {$sessions_table} WHERE created_at >= %s",
					$start_date
				)
			);

			// Primeiras visitas vs retorno
			$stats['first_visits'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$sessions_table} WHERE created_at >= %s AND is_first_visit = 1",
					$start_date
				)
			);
		}

		if ( $orders_exists ) {
			// Pedidos por canal (first touch)
			$stats['orders_by_channel_first'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT first_touch_channel as channel, COUNT(*) as orders
					 FROM {$orders_table}
					 WHERE created_at >= %s AND first_touch_channel != ''
					 GROUP BY first_touch_channel
					 ORDER BY orders DESC",
					$start_date
				),
				ARRAY_A
			);

			// Pedidos por canal (last touch)
			$stats['orders_by_channel_last'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT last_touch_channel as channel, COUNT(*) as orders
					 FROM {$orders_table}
					 WHERE created_at >= %s AND last_touch_channel != ''
					 GROUP BY last_touch_channel
					 ORDER BY orders DESC",
					$start_date
				),
				ARRAY_A
			);

			// Pedidos por source (last touch)
			$stats['orders_by_source'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT last_touch_source as source, last_touch_medium as medium, COUNT(*) as orders
					 FROM {$orders_table}
					 WHERE created_at >= %s AND last_touch_source != ''
					 GROUP BY last_touch_source, last_touch_medium
					 ORDER BY orders DESC
					 LIMIT 20",
					$start_date
				),
				ARRAY_A
			);

			// Pedidos por campanha
			$stats['orders_by_campaign'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT last_touch_campaign as campaign, COUNT(*) as orders
					 FROM {$orders_table}
					 WHERE created_at >= %s AND last_touch_campaign != ''
					 GROUP BY last_touch_campaign
					 ORDER BY orders DESC
					 LIMIT 20",
					$start_date
				),
				ARRAY_A
			);

			// Pedidos por referral
			$stats['orders_by_referral'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT referral_code, COUNT(*) as orders
					 FROM {$orders_table}
					 WHERE created_at >= %s AND referral_code != ''
					 GROUP BY referral_code
					 ORDER BY orders DESC
					 LIMIT 20",
					$start_date
				),
				ARRAY_A
			);

			// Total de pedidos com atribuição
			$stats['total_orders_attributed'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$orders_table} WHERE created_at >= %s",
					$start_date
				)
			);
		}

		return $stats;
	}

	/**
	 * Limpar sessões antigas
	 *
	 * @param int $days Dias para manter.
	 */
	public function cleanup_old_sessions( $days = 90 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_user_sessions';
		$date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Manter sessões que resultaram em conversão
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} 
				 WHERE created_at < %s 
				 AND session_id NOT IN (
				     SELECT DISTINCT session_id FROM {$wpdb->prefix}pcw_order_attributions
				 )",
				$date
			)
		);
	}

	// ========================================
	// MÉTODOS AUXILIARES
	// ========================================

	/**
	 * Obter ou criar session ID
	 *
	 * @return string
	 */
	private function get_or_create_session_id() {
		if ( isset( $_COOKIE[ self::COOKIE_SESSION ] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_SESSION ] ) );
		}

		// Tentar usar session do Activity Tracker
		if ( isset( $_COOKIE['pcw_session'] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE['pcw_session'] ) );
		}

		// Criar novo
		$session_id = wp_generate_uuid4();

		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_SESSION,
				$session_id,
				time() + ( 30 * DAY_IN_SECONDS ),
				COOKIEPATH,
				COOKIE_DOMAIN,
				is_ssl(),
				true
			);
		}

		$_COOKIE[ self::COOKIE_SESSION ] = $session_id;

		return $session_id;
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
				$ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
				return trim( $ip[0] );
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Obter User Agent
	 *
	 * @return string
	 */
	private function get_user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) 
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) 
			: '';
	}

	/**
	 * Obter Referrer
	 *
	 * @return string
	 */
	private function get_referrer() {
		if ( ! isset( $_SERVER['HTTP_REFERER'] ) ) {
			return '';
		}

		$referrer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );

		// Ignorar referrer do próprio site
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$ref_host = wp_parse_url( $referrer, PHP_URL_HOST );

		if ( $site_host === $ref_host ) {
			return '';
		}

		return $referrer;
	}

	/**
	 * Obter domínio do referrer
	 *
	 * @return string
	 */
	private function get_referrer_domain() {
		$referrer = $this->get_referrer();

		if ( empty( $referrer ) ) {
			return '';
		}

		$host = wp_parse_url( $referrer, PHP_URL_HOST );

		// Remover www.
		if ( strpos( $host, 'www.' ) === 0 ) {
			$host = substr( $host, 4 );
		}

		return $host;
	}

	/**
	 * Obter URL atual
	 *
	 * @return string
	 */
	private function get_current_url() {
		$protocol = is_ssl() ? 'https://' : 'http://';
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		return $protocol . $host . $uri;
	}

	/**
	 * Obter path atual
	 *
	 * @return string
	 */
	private function get_current_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = wp_parse_url( $uri, PHP_URL_PATH );

		return $path ?: '/';
	}

	/**
	 * Parse User Agent para obter informações do dispositivo
	 *
	 * @return array
	 */
	private function parse_user_agent() {
		$ua = $this->get_user_agent();
		$ua_lower = strtolower( $ua );

		// Tipo de dispositivo
		$device_type = 'desktop';
		if ( preg_match( '/mobile|android|iphone|ipod|blackberry|opera mini|opera mobi|windows phone/i', $ua ) ) {
			$device_type = 'mobile';
		} elseif ( preg_match( '/tablet|ipad|playbook|silk/i', $ua ) ) {
			$device_type = 'tablet';
		}

		// Sistema operacional
		$os = 'unknown';
		if ( strpos( $ua_lower, 'windows' ) !== false ) {
			$os = 'windows';
		} elseif ( strpos( $ua_lower, 'macintosh' ) !== false || strpos( $ua_lower, 'mac os' ) !== false ) {
			$os = 'macos';
		} elseif ( strpos( $ua_lower, 'iphone' ) !== false || strpos( $ua_lower, 'ipad' ) !== false ) {
			$os = 'ios';
		} elseif ( strpos( $ua_lower, 'android' ) !== false ) {
			$os = 'android';
		} elseif ( strpos( $ua_lower, 'linux' ) !== false ) {
			$os = 'linux';
		}

		// Navegador
		$browser = 'unknown';
		if ( strpos( $ua_lower, 'edg/' ) !== false ) {
			$browser = 'edge';
		} elseif ( strpos( $ua_lower, 'chrome' ) !== false && strpos( $ua_lower, 'safari' ) !== false ) {
			$browser = 'chrome';
		} elseif ( strpos( $ua_lower, 'firefox' ) !== false ) {
			$browser = 'firefox';
		} elseif ( strpos( $ua_lower, 'safari' ) !== false && strpos( $ua_lower, 'chrome' ) === false ) {
			$browser = 'safari';
		} elseif ( strpos( $ua_lower, 'opera' ) !== false || strpos( $ua_lower, 'opr/' ) !== false ) {
			$browser = 'opera';
		} elseif ( strpos( $ua_lower, 'msie' ) !== false || strpos( $ua_lower, 'trident' ) !== false ) {
			$browser = 'ie';
		}

		return array(
			'device_type' => $device_type,
			'os'          => $os,
			'browser'     => $browser,
		);
	}

	/**
	 * Verificar se é bot
	 *
	 * @return bool
	 */
	private function is_bot() {
		$ua = strtolower( $this->get_user_agent() );

		if ( empty( $ua ) ) {
			return true;
		}

		$bots = array(
			'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider', 'yandexbot',
			'sogou', 'exabot', 'facebot', 'ia_archiver', 'mj12bot', 'ahrefsbot',
			'semrushbot', 'dotbot', 'rogerbot', 'blexbot', 'linkdexbot',
			'bot', 'spider', 'crawl', 'scraper', 'fetch', 'curl', 'wget',
			'python', 'php/', 'java/', 'ruby', 'perl', 'libwww', 'httpclient',
			'facebookexternalhit', 'twitterbot', 'linkedinbot', 'pinterest',
			'whatsapp', 'telegrambot', 'discordbot', 'slackbot',
			'lighthouse', 'pagespeed', 'gtmetrix', 'pingdom', 'uptimerobot',
			'headlesschrome', 'phantomjs', 'selenium', 'puppeteer',
		);

		foreach ( $bots as $bot ) {
			if ( strpos( $ua, $bot ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Obter dias de cookie
	 *
	 * @return int
	 */
	private function get_cookie_days() {
		return absint( get_option( 'pcw_origin_cookie_days', 90 ) );
	}

	/**
	 * Obter atribuição de um pedido
	 *
	 * @param int $order_id ID do pedido.
	 * @return array|null
	 */
	public function get_order_attribution( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return null;
		}

		$attribution_json = $order->get_meta( '_pcw_attribution' );

		if ( $attribution_json ) {
			return json_decode( $attribution_json, true );
		}

		// Tentar reconstruir dos metas individuais
		$attribution = array();
		$meta_keys = array(
			'first_touch_source', 'first_touch_medium', 'first_touch_campaign',
			'first_touch_channel', 'first_touch_referrer',
			'last_touch_source', 'last_touch_medium', 'last_touch_campaign',
			'last_touch_channel', 'last_touch_referrer',
			'referral_code', 'gclid', 'fbclid',
			'device_type', 'device_os', 'device_browser',
		);

		foreach ( $meta_keys as $key ) {
			$value = $order->get_meta( '_pcw_attr_' . $key );
			if ( $value ) {
				$attribution[ $key ] = $value;
			}
		}

		return ! empty( $attribution ) ? $attribution : null;
	}

	/**
	 * Obter dados de origem atual
	 *
	 * @return array
	 */
	public function get_current_origin() {
		if ( empty( $this->current_origin ) ) {
			$this->current_origin = $this->collect_origin_data();
		}

		return $this->current_origin;
	}
}
