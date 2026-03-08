<?php
/**
 * Rastreador de Atividades para Live Dashboard
 *
 * @package GrowlyDigital
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de rastreamento de atividades
 */
class PCW_Activity_Tracker {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Activity_Tracker
	 */
	private static $instance = null;

	/**
	 * Obter instância
	 *
	 * @return PCW_Activity_Tracker
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
	 * Inicializar hooks
	 */
	public function init() {
		// Hooks do frontend
		if ( ! is_admin() ) {
			add_action( 'wp', array( $this, 'track_page_view' ) );
			add_action( 'woocommerce_after_single_product', array( $this, 'track_product_view' ) );
		}

		// AJAX endpoints
		add_action( 'wp_ajax_pcw_track_activity', array( $this, 'ajax_track_activity' ) );
		add_action( 'wp_ajax_nopriv_pcw_track_activity', array( $this, 'ajax_track_activity' ) );
		add_action( 'wp_ajax_pcw_get_live_activities', array( $this, 'ajax_get_live_activities' ) );
		add_action( 'wp_ajax_pcw_get_dashboard_stats', array( $this, 'ajax_get_dashboard_stats' ) );

		// Enqueue frontend scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts do frontend
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			'pcw-activity-tracker',
			PCW_PLUGIN_URL . 'assets/js/activity-tracker.js',
			array( 'jquery' ),
			PCW_VERSION,
			true
		);

		wp_localize_script( 'pcw-activity-tracker', 'pcwTracker', array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'pcw_track_activity' ),
			'sessionId' => $this->get_session_id(),
			'userId'    => get_current_user_id(),
		) );
	}

	/**
	 * Obter ou criar session ID
	 *
	 * @return string
	 */
	private function get_session_id() {
		if ( ! isset( $_COOKIE['pcw_session'] ) ) {
			$session_id = wp_generate_uuid4();
			setcookie( 'pcw_session', $session_id, time() + ( 30 * 24 * 60 * 60 ), '/' );
			return $session_id;
		}
		return sanitize_text_field( $_COOKIE['pcw_session'] );
	}

	/**
	 * Rastrear visualização de página
	 */
	public function track_page_view() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		// Não rastrear bots
		if ( $this->is_bot() ) {
			return;
		}

		global $wp;
		$page_url = home_url( $wp->request );
		$page_type = 'page';
		$page_name = '';
		$object_id = 0;

		if ( is_front_page() ) {
			$page_type = 'home';
			$page_name = 'Pagina Inicial';
		} elseif ( is_shop() ) {
			$page_type = 'shop';
			$page_name = 'Loja';
		} elseif ( is_product_category() ) {
			$page_type = 'category';
			$term = get_queried_object();
			if ( $term ) {
				$page_name = $term->name;
				$object_id = $term->term_id;
			}
		} elseif ( is_category() ) {
			$page_type = 'category';
			$term = get_queried_object();
			if ( $term ) {
				$page_name = $term->name;
				$object_id = $term->term_id;
			}
		} elseif ( is_product() ) {
			$page_type = 'product';
			// Produto é rastreado separadamente em track_product_view
			return;
		} elseif ( is_cart() ) {
			$page_type = 'cart';
			$page_name = 'Carrinho';
		} elseif ( is_checkout() ) {
			$page_type = 'checkout';
			$page_name = 'Checkout';
		} elseif ( is_page() ) {
			$page_type = 'page';
			$page_name = get_the_title();
			$object_id = get_the_ID();
		} elseif ( is_single() ) {
			$page_type = 'post';
			$page_name = get_the_title();
			$object_id = get_the_ID();
		}

		$this->log_activity( 'page_view', $page_type, $object_id, $page_name, null, null, $page_url );
	}

	/**
	 * Rastrear visualização de produto
	 */
	public function track_product_view() {
		global $product;

		if ( ! $product ) {
			return;
		}

		$this->log_activity(
			'product_view',
			'product',
			$product->get_id(),
			$product->get_name(),
			$product->get_price(),
			wp_get_attachment_url( $product->get_image_id() )
		);
	}

	/**
	 * AJAX: Rastrear atividade
	 */
	public function ajax_track_activity() {
		check_ajax_referer( 'pcw_track_activity', 'nonce' );

		$activity_type = isset( $_POST['activity_type'] ) ? sanitize_text_field( $_POST['activity_type'] ) : '';
		$object_type = isset( $_POST['object_type'] ) ? sanitize_text_field( $_POST['object_type'] ) : '';
		$object_id = isset( $_POST['object_id'] ) ? absint( $_POST['object_id'] ) : 0;
		$object_name = isset( $_POST['object_name'] ) ? sanitize_text_field( $_POST['object_name'] ) : '';
		$object_price = isset( $_POST['object_price'] ) ? floatval( $_POST['object_price'] ) : null;
		$page_url = isset( $_POST['page_url'] ) ? esc_url_raw( $_POST['page_url'] ) : '';

		$this->log_activity( $activity_type, $object_type, $object_id, $object_name, $object_price, null, $page_url );

		wp_send_json_success();
	}

	/**
	 * Registrar atividade
	 *
	 * @param string      $activity_type Tipo de atividade.
	 * @param string      $object_type Tipo do objeto.
	 * @param int         $object_id ID do objeto.
	 * @param string|null $object_name Nome do objeto.
	 * @param float|null  $object_price Preço do objeto.
	 * @param string|null $object_image Imagem do objeto.
	 * @param string|null $page_url URL da página.
	 * @param array|null  $extra_data Dados extras.
	 */
	public function log_activity( $activity_type, $object_type = '', $object_id = 0, $object_name = null, $object_price = null, $object_image = null, $page_url = null, $extra_data = null ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_activities';

		$data = array(
			'session_id'    => $this->get_session_id(),
			'user_id'       => get_current_user_id() ?: null,
			'fingerprint'   => $this->get_visitor_fingerprint(),
			'activity_type' => $activity_type,
			'object_type'   => $object_type,
			'object_id'     => $object_id ?: null,
			'object_name'   => $object_name,
			'object_price'  => $object_price,
			'object_image'  => $object_image,
			'page_url'      => $page_url ?: ( isset( $_SERVER['REQUEST_URI'] ) ? home_url( $_SERVER['REQUEST_URI'] ) : null ),
			'referrer'      => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : null,
			'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : null,
			'ip_address'    => $this->get_client_ip(),
			'extra_data'    => $extra_data ? wp_json_encode( $extra_data ) : null,
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
		$ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = explode( ',', $_SERVER[ $key ] );
				return trim( $ip[0] );
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Verificar se é bot (lista rigorosa)
	 *
	 * @return bool
	 */
	private function is_bot() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( $_SERVER['HTTP_USER_AGENT'] ) : '';

		// Se não tem user agent, provavelmente é bot
		if ( empty( $user_agent ) ) {
			return true;
		}

		// Lista rigorosa de bots conhecidos
		$bots = array(
			// Search engines
			'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider', 'yandexbot',
			'sogou', 'exabot', 'facebot', 'ia_archiver', 'mj12bot', 'ahrefsbot',
			'semrushbot', 'dotbot', 'rogerbot', 'blexbot', 'linkdexbot',
			// Generic bot patterns
			'bot', 'spider', 'crawl', 'scraper', 'fetch', 'curl', 'wget', 'python',
			'php', 'java', 'ruby', 'perl', 'libwww', 'httpclient', 'apache-http',
			// Social media
			'facebookexternalhit', 'twitterbot', 'linkedinbot', 'pinterest', 'whatsapp',
			'telegrambot', 'discordbot', 'slackbot', 'skypeuripreview',
			// Tools
			'lighthouse', 'pagespeed', 'gtmetrix', 'pingdom', 'uptimerobot',
			'statuspage', 'headlesschrome', 'phantomjs', 'selenium', 'puppeteer',
			// Malicious/spam
			'masscan', 'nmap', 'nikto', 'sqlmap', 'zgrab', 'censys',
		);

		foreach ( $bots as $bot ) {
			if ( strpos( $user_agent, $bot ) !== false ) {
				return true;
			}
		}

		// Verificar padrões suspeitos
		if ( preg_match( '/^mozilla\/\d\.0$/', $user_agent ) ) {
			return true; // User agent muito genérico
		}

		// Verificar se tem características de navegador real
		$real_browsers = array( 'chrome', 'firefox', 'safari', 'edge', 'opera', 'msie', 'trident' );
		$has_browser = false;
		foreach ( $real_browsers as $browser ) {
			if ( strpos( $user_agent, $browser ) !== false ) {
				$has_browser = true;
				break;
			}
		}

		// Se não tem nenhum navegador conhecido e user agent é curto, provavelmente é bot
		if ( ! $has_browser && strlen( $user_agent ) < 50 ) {
			return true;
		}

		return false;
	}

	/**
	 * Gerar fingerprint do visitante (IP + User Agent hash)
	 *
	 * @return string
	 */
	private function get_visitor_fingerprint() {
		$ip = $this->get_client_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$accept_language = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';

		// Criar hash único baseado em múltiplos fatores
		$fingerprint_data = $ip . '|' . $user_agent . '|' . $accept_language;
		return md5( $fingerprint_data );
	}

	/**
	 * AJAX: Obter atividades em tempo real
	 */
	public function ajax_get_live_activities() {
		check_ajax_referer( 'pcw_live_dashboard', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$limit = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 20;
		$since = isset( $_POST['since'] ) ? sanitize_text_field( $_POST['since'] ) : '';

		$activities = $this->get_recent_activities( $limit, $since );

		wp_send_json_success( array( 'activities' => $activities ) );
	}

	/**
	 * AJAX: Obter estatísticas do dashboard
	 */
	public function ajax_get_dashboard_stats() {
		check_ajax_referer( 'pcw_live_dashboard', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : '7days';
		$stats = $this->get_stats( $period );

		wp_send_json_success( $stats );
	}

	/**
	 * Obter atividades recentes
	 *
	 * @param int    $limit Limite.
	 * @param string $since Desde quando.
	 * @return array
	 */
	public function get_recent_activities( $limit = 20, $since = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_activities';
		$users_table = $wpdb->users;

		// Verificar se tabela existe
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
		if ( ! $table_exists ) {
			return array();
		}

		$where = '1=1';
		$params = array();

		if ( $since ) {
			$where .= ' AND a.created_at > %s';
			$params[] = $since;
		}

		$params[] = $limit;

		$sql = "SELECT a.*, u.display_name as user_name 
				FROM {$table} a
				LEFT JOIN {$users_table} u ON a.user_id = u.ID
				WHERE {$where}
				ORDER BY a.created_at DESC
				LIMIT %d";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		if ( ! $results ) {
			return array();
		}

		$activities = array();
		foreach ( $results as $row ) {
			// Determinar se é cliente ou visitante
			$is_customer = ! empty( $row->user_id );
			$user_type = $is_customer ? 'cliente' : 'visitante';
			$user_email = '';
			$user_display = 'Visitante';

			if ( $is_customer ) {
				$user = get_userdata( $row->user_id );
				if ( $user ) {
					$user_display = $user->display_name ?: $user->user_login;
					$user_email = $user->user_email;
				}
			}

			// Determinar nome do objeto/página
			$object_name = $row->object_name;
			$object_image = $row->object_image;
			$page_name = '';

			// Se for produto, buscar nome do produto
			if ( $row->activity_type === 'product_view' && $row->object_id ) {
				if ( empty( $object_name ) ) {
					$product = wc_get_product( $row->object_id );
					if ( $product ) {
						$object_name = $product->get_name();
						$object_image = wp_get_attachment_url( $product->get_image_id() );
					}
				}
			} elseif ( $row->activity_type === 'page_view' ) {
				// Para visualização de página, tentar obter nome se não existir
				if ( empty( $object_name ) ) {
					$object_name = $this->get_page_title_from_context( $row->object_type, $row->object_id, $row->page_url );
				}
			}

			// Obter nome legível da URL se ainda não tiver nome
			$page_name = $object_name ?: $this->get_page_name_from_url( $row->page_url, $row->object_type );

			$activities[] = array(
				'id'            => $row->id,
				'type'          => $row->activity_type,
				'object_type'   => $row->object_type,
				'object_id'     => $row->object_id,
				'object_name'   => $object_name,
				'object_price'  => $row->object_price ? wc_price( $row->object_price ) : '',
				'object_image'  => $object_image,
				'user_type'     => $user_type,
				'user_name'     => $user_display,
				'user_email'    => $user_email,
				'user_id'       => $row->user_id,
				'page_url'      => $row->page_url,
				'page_name'     => $page_name,
				'created_at'    => $row->created_at,
				'time_ago'      => $this->time_ago( $row->created_at ),
			);
		}

		return $activities;
	}

	/**
	 * Obter título da página a partir do contexto
	 *
	 * @param string $object_type Tipo de objeto.
	 * @param int    $object_id ID do objeto.
	 * @param string $url URL.
	 * @return string
	 */
	private function get_page_title_from_context( $object_type, $object_id, $url ) {
		// Se tiver ID, tentar buscar o título
		if ( $object_id > 0 ) {
			if ( $object_type === 'category' ) {
				$term = get_term( $object_id );
				if ( $term && ! is_wp_error( $term ) ) {
					return $term->name;
				}

				// Tentar taxonomias comuns (categoria de produto/blog)
				$term = get_term( $object_id, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					return $term->name;
				}
				$term = get_term( $object_id, 'category' );
				if ( $term && ! is_wp_error( $term ) ) {
					return $term->name;
				}
			} elseif ( in_array( $object_type, array( 'page', 'post' ), true ) ) {
				$post = get_post( $object_id );
				if ( $post ) {
					return $post->post_title;
				}
			}
		}

		// Tentar resolver por URL (page/post)
		if ( ! empty( $url ) ) {
			$post_id = url_to_postid( $url );
			if ( $post_id ) {
				$title = get_the_title( $post_id );
				if ( ! empty( $title ) ) {
					return $title;
				}
			}

			// Tentar resolver por slug para categorias
			$path = wp_parse_url( $url, PHP_URL_PATH );
			$path = trim( $path, '/' );
			if ( ! empty( $path ) ) {
				$parts = explode( '/', $path );
				$slug = end( $parts );
				if ( $slug ) {
					$term = get_term_by( 'slug', $slug, 'product_cat' );
					if ( $term && ! is_wp_error( $term ) ) {
						return $term->name;
					}
					$term = get_term_by( 'slug', $slug, 'category' );
					if ( $term && ! is_wp_error( $term ) ) {
						return $term->name;
					}
				}
			}
		}

		// Tentar identificar por tipo
		$type_names = array(
			'home'     => 'Pagina Inicial',
			'shop'     => 'Loja',
			'cart'     => 'Carrinho',
			'checkout' => 'Checkout',
		);

		if ( isset( $type_names[ $object_type ] ) ) {
			return $type_names[ $object_type ];
		}

		return '';
	}

	/**
	 * Extrair nome legível da página a partir da URL
	 *
	 * @param string $url URL da página.
	 * @param string $object_type Tipo de objeto.
	 * @return string
	 */
	private function get_page_name_from_url( $url, $object_type = '' ) {
		if ( empty( $url ) ) {
			return 'Pagina desconhecida';
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		$path = trim( $path, '/' );

		if ( empty( $path ) ) {
			return 'Pagina inicial';
		}

		// Mapear tipos de objeto para nomes legíveis (apenas páginas fixas)
		$type_names = array(
			'home'     => 'Pagina inicial',
			'shop'     => 'Loja',
			'cart'     => 'Carrinho',
			'checkout' => 'Checkout',
		);

		if ( ! empty( $object_type ) && isset( $type_names[ $object_type ] ) ) {
			return $type_names[ $object_type ];
		}

		// Tentar identificar páginas comuns
		if ( strpos( $path, 'cart' ) !== false || strpos( $path, 'carrinho' ) !== false ) {
			return 'Carrinho';
		}
		if ( strpos( $path, 'checkout' ) !== false || strpos( $path, 'finalizar' ) !== false ) {
			return 'Checkout';
		}
		if ( strpos( $path, 'shop' ) !== false || strpos( $path, 'loja' ) !== false ) {
			return 'Loja';
		}
		if ( strpos( $path, 'product-category' ) !== false || strpos( $path, 'categoria' ) !== false ) {
			return 'Categoria';
		}
		if ( strpos( $path, 'product' ) !== false || strpos( $path, 'produto' ) !== false ) {
			return 'Produto';
		}
		if ( strpos( $path, 'my-account' ) !== false || strpos( $path, 'minha-conta' ) !== false ) {
			return 'Minha Conta';
		}

		// Retornar última parte do path formatada
		$parts = explode( '/', $path );
		$last_part = end( $parts );
		return ucwords( str_replace( array( '-', '_' ), ' ', $last_part ) );
	}

	/**
	 * Obter estatísticas
	 *
	 * @param string $period Período.
	 * @return array
	 */
	public function get_stats( $period = '7days' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_activities';

		// Verificar se tabela existe
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;

		// Verificar se coluna fingerprint existe
		$has_fingerprint = false;
		if ( $table_exists ) {
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
			$has_fingerprint = in_array( 'fingerprint', $columns, true );
		}

		// Determinar data inicial
		switch ( $period ) {
			case 'today':
				$start_date = date( 'Y-m-d 00:00:00' );
				$prev_start = date( 'Y-m-d 00:00:00', strtotime( '-1 day' ) );
				$prev_end   = date( 'Y-m-d 23:59:59', strtotime( '-1 day' ) );
				break;
			case '7days':
				$start_date = date( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );
				$prev_start = date( 'Y-m-d 00:00:00', strtotime( '-14 days' ) );
				$prev_end   = date( 'Y-m-d 23:59:59', strtotime( '-8 days' ) );
				break;
			case '30days':
				$start_date = date( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );
				$prev_start = date( 'Y-m-d 00:00:00', strtotime( '-60 days' ) );
				$prev_end   = date( 'Y-m-d 23:59:59', strtotime( '-31 days' ) );
				break;
			default:
				$start_date = date( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );
				$prev_start = date( 'Y-m-d 00:00:00', strtotime( '-14 days' ) );
				$prev_end   = date( 'Y-m-d 23:59:59', strtotime( '-8 days' ) );
		}

		// Inicializar valores
		$visitors = 0;
		$visitors_by_ip = 0;
		$visitors_logged = 0;
		$visitors_anonymous = 0;
		$new_visitors = 0;
		$returning_visitors = 0;
		$product_views = 0;
		$add_to_cart = 0;
		$visitors_by_day = array();

		if ( $table_exists ) {
			// ========================================
			// VISITANTES ÚNICOS
			// ========================================

			// Método 1: Por session_id (cookie-based)
			$visitors = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT session_id) FROM {$table} WHERE created_at >= %s",
				$start_date
			) );

			// Método 2: Por IP + User Agent (mais preciso para visitantes sem cookies)
			$visitors_by_ip = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT CONCAT(ip_address, '-', LEFT(user_agent, 100))) 
				 FROM {$table} 
				 WHERE created_at >= %s AND ip_address IS NOT NULL",
				$start_date
			) );

			// Método 3: Por fingerprint (se disponível)
			if ( $has_fingerprint ) {
				$visitors_by_fingerprint = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(DISTINCT fingerprint) 
					 FROM {$table} 
					 WHERE created_at >= %s AND fingerprint IS NOT NULL AND fingerprint != ''",
					$start_date
				) );
				// Usar o menor valor entre fingerprint e session (mais preciso)
				if ( $visitors_by_fingerprint > 0 && $visitors_by_fingerprint < $visitors ) {
					$visitors = $visitors_by_fingerprint;
				}
			}

			// ========================================
			// VISITANTES LOGADOS vs ANÔNIMOS
			// ========================================

			// Visitantes logados (usuários únicos)
			$visitors_logged = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) 
				 FROM {$table} 
				 WHERE created_at >= %s AND user_id IS NOT NULL AND user_id > 0",
				$start_date
			) );

			// Visitantes anônimos (sessões sem user_id)
			$visitors_anonymous = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT session_id) 
				 FROM {$table} 
				 WHERE created_at >= %s AND (user_id IS NULL OR user_id = 0)",
				$start_date
			) );

			// ========================================
			// NOVOS vs RECORRENTES
			// ========================================

			// Sessões que existiam antes do período atual (recorrentes)
			$returning_sessions = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT session_id 
				 FROM {$table} 
				 WHERE created_at >= %s 
				   AND session_id IN (
				       SELECT session_id FROM {$table} WHERE created_at < %s
				   )",
				$start_date,
				$start_date
			) );
			$returning_visitors = count( $returning_sessions );
			$new_visitors = max( 0, $visitors - $returning_visitors );

			// ========================================
			// VISITANTES POR DIA
			// ========================================
			$daily_stats = $wpdb->get_results( $wpdb->prepare(
				"SELECT DATE(created_at) as date, COUNT(DISTINCT session_id) as visitors
				 FROM {$table}
				 WHERE created_at >= %s
				 GROUP BY DATE(created_at)
				 ORDER BY date ASC",
				$start_date
			) );

			foreach ( $daily_stats as $day ) {
				$visitors_by_day[ $day->date ] = (int) $day->visitors;
			}

			// ========================================
			// OUTRAS MÉTRICAS
			// ========================================

			// Visualizações de produtos
			$product_views = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE activity_type = 'product_view' AND created_at >= %s",
				$start_date
			) );

			// Adicionados ao carrinho
			$add_to_cart = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE activity_type = 'add_to_cart' AND created_at >= %s",
				$start_date
			) );

			// Total de pageviews
			$pageviews = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
				$start_date
			) );
		}

		// Pedidos (do WooCommerce)
		$orders = 0;
		if ( function_exists( 'wc_get_orders' ) ) {
			$orders = count( wc_get_orders( array(
				'date_created' => '>=' . strtotime( $start_date ),
				'limit'        => -1,
				'return'       => 'ids',
			) ) );
		}

		// Novos clientes registrados
		$new_customers = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= %s",
			$start_date
		) );

		// Calcular taxa de conversão
		$conversion_rate = $visitors > 0 ? round( ( $orders / $visitors ) * 100, 2 ) : 0;

		// Calcular páginas por visita
		$pages_per_visit = $visitors > 0 ? round( $pageviews / $visitors, 1 ) : 0;

		return array(
			// Visitantes principais
			'visitors'            => (int) $visitors,
			'visitors_by_ip'      => (int) $visitors_by_ip,

			// Breakdown por tipo
			'visitors_logged'     => (int) $visitors_logged,
			'visitors_anonymous'  => (int) $visitors_anonymous,

			// Novos vs recorrentes
			'new_visitors'        => (int) $new_visitors,
			'returning_visitors'  => (int) $returning_visitors,

			// Por dia
			'visitors_by_day'     => $visitors_by_day,

			// Outras métricas
			'product_views'       => (int) $product_views,
			'add_to_cart'         => (int) $add_to_cart,
			'pageviews'           => (int) $pageviews,
			'pages_per_visit'     => $pages_per_visit,
			'orders'              => (int) $orders,
			'new_customers'       => (int) $new_customers,
			'conversion_rate'     => $conversion_rate,

			// Meta
			'period'              => $period,
			'start_date'          => $start_date,
		);
	}

	/**
	 * Formatar tempo atrás
	 *
	 * @param string $datetime Data/hora.
	 * @return string
	 */
	private function time_ago( $datetime ) {
		$time = strtotime( $datetime );
		$now = current_time( 'timestamp' );
		$diff = $now - $time;

		if ( $diff < 60 ) {
			return __( 'há alguns segundos', 'person-cash-wallet' );
		} elseif ( $diff < 3600 ) {
			$mins = floor( $diff / 60 );
			return sprintf( _n( 'há %d minuto', 'há %d minutos', $mins, 'person-cash-wallet' ), $mins );
		} elseif ( $diff < 86400 ) {
			$hours = floor( $diff / 3600 );
			return sprintf( _n( 'há %d hora', 'há %d horas', $hours, 'person-cash-wallet' ), $hours );
		} else {
			$days = floor( $diff / 86400 );
			return sprintf( _n( 'há %d dia', 'há %d dias', $days, 'person-cash-wallet' ), $days );
		}
	}
}
