<?php
/**
 * Classe da área administrativa
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe admin
 */
class PCW_Admin {

	/**
	 * Instância de admin wallet
	 *
	 * @var PCW_Admin_Wallet
	 */
	private $wallet_admin;

	/**
	 * Inicializar
	 */
	public function init() {
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-dashboard.php';
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-wallet.php';
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-cashback-rules.php';
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-levels.php';
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-webhooks.php';
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-email-settings.php';
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-notifications.php';
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-display.php';
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-settings.php';
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-retroactive.php';
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-custom-lists.php';
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-workflows.php';
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-automations.php';
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-automation-reports.php';
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-campaigns.php';
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-queue.php';
		require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-logs.php';
		
		$dashboard_admin = new PCW_Admin_Dashboard();
		$dashboard_admin->init();

		$notifications_admin = new PCW_Admin_Notifications();
		$notifications_admin->init();

		$display_admin = new PCW_Admin_Display();
		$display_admin->init();

		$settings_admin = new PCW_Admin_Settings();
		$settings_admin->init();

		$this->wallet_admin = new PCW_Admin_Wallet();
		$this->wallet_admin->init();

		$cashback_rules_admin = new PCW_Admin_Cashback_Rules();
		$cashback_rules_admin->init();

		$levels_admin = new PCW_Admin_Levels();
		$levels_admin->init();

		$webhooks_admin = new PCW_Admin_Webhooks();
		$webhooks_admin->init();

		$email_settings_admin = new PCW_Admin_Email_Settings();
		$email_settings_admin->init();

		// Inicializar cashback retroativo
		PCW_Admin_Retroactive::init();

		// Inicializar listas personalizadas
		PCW_Admin_Custom_Lists::init();

		// Inicializar workflows
		$workflows_admin = new PCW_Admin_Workflows();
		$workflows_admin->init();

		// Inicializar automações
		$automations_admin = new PCW_Admin_Automations();
		$automations_admin->init();

		// Inicializar relatórios de automações
		$automation_reports_admin = new PCW_Admin_Automation_Reports();
		$automation_reports_admin->init();

		// Inicializar campanhas
		$campaigns_admin = new PCW_Admin_Campaigns();
		$campaigns_admin->init();

		// Inicializar filas & rate limiting
		$queue_admin = new PCW_Admin_Queue();
		$queue_admin->init();

		// Inicializar logs
		$logs_admin = new PCW_Admin_Logs();
		$logs_admin->init();

		// Adicionar scripts e estilos
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enfileirar scripts e estilos
	 *
	 * @param string $hook Hook atual.
	 */
	public function enqueue_scripts( $hook ) {
		// Enfileirar estilos globais em todas as páginas do plugin
		if ( strpos( $hook, 'pcw-' ) !== false || strpos( $hook, '_page_pcw-' ) !== false ) {
			wp_enqueue_style( 'pcw-admin-global', PCW_PLUGIN_URL . 'assets/css/admin-global.css', array(), PCW_VERSION . '-' . time() );
		}

		// Scripts específicos da página de wallet
		if ( 'toplevel_page_pcw-dashboard' === $hook || strpos( $hook, '_page_pcw-wallet' ) !== false ) {
			// Select2 para busca de usuários
			wp_enqueue_script( 'select2' );
			wp_enqueue_style( 'select2' );

			// AJAX para busca de usuários
			add_action( 'wp_ajax_pcw_search_users', array( $this, 'ajax_search_users' ) );
			add_action( 'admin_footer', array( $this, 'add_select2_script' ) );
		}
	}

	/**
	 * AJAX: Buscar usuários
	 */
	public function ajax_search_users() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		if ( strlen( $search ) < 2 ) {
			wp_send_json( array() );
		}

		$users = get_users( array(
			'search'         => '*' . $search . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'user_email', 'display_name' ),
			'number'         => 20,
		) );

		$results = array();
		foreach ( $users as $user ) {
			$results[] = array(
				'id'   => $user->ID,
				'text' => sprintf( '%s (%s)', $user->display_name, $user->user_email ),
			);
		}

		wp_send_json( $results );
	}

	/**
	 * Adicionar script Select2
	 */
	public function add_select2_script() {
		$screen = get_current_screen();
		if ( 'woocommerce_page_pcw-wallet' !== $screen->id ) {
			return;
		}
		?>
		<script>
		jQuery(document).ready(function($) {
			if (typeof $.fn.select2 !== 'undefined') {
				$('.pcw-user-select').select2({
					ajax: {
						url: ajaxurl,
						dataType: 'json',
						delay: 250,
						data: function (params) {
							return {
								q: params.term,
								action: 'pcw_search_users'
							};
						},
						processResults: function (data) {
							return {
								results: data
							};
						},
						cache: true
					},
					minimumInputLength: 2
				});
			}
		});
		</script>
		<?php
	}
}
