<?php
/**
 * Carregador principal do plugin
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe principal de carregamento
 */
class PCW_Loader {

	/**
	 * Array de ações registradas
	 *
	 * @var array
	 */
	protected $actions;

	/**
	 * Array de filtros registrados
	 *
	 * @var array
	 */
	protected $filters;

	/**
	 * Construtor
	 */
	public function __construct() {
		$this->actions = array();
		$this->filters = array();
	}

	/**
	 * Adicionar ação
	 *
	 * @param string $hook Hook name.
	 * @param object $component Component.
	 * @param string $callback Callback.
	 * @param int    $priority Priority.
	 * @param int    $accepted_args Accepted args.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Adicionar filtro
	 *
	 * @param string $hook Hook name.
	 * @param object $component Component.
	 * @param string $callback Callback.
	 * @param int    $priority Priority.
	 * @param int    $accepted_args Accepted args.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Adicionar hook ao array
	 *
	 * @param array  $hooks Hooks array.
	 * @param string $hook Hook name.
	 * @param object $component Component.
	 * @param string $callback Callback.
	 * @param int    $priority Priority.
	 * @param int    $accepted_args Accepted args.
	 * @return array
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}

	/**
	 * Carregar classes e registrar hooks
	 */
	public function run() {
		// Carregar classes principais
		$this->load_dependencies();

		// Registrar hooks
		$this->register_hooks();
	}

	/**
	 * Carregar dependências
	 */
	private function load_dependencies() {
		// Core
		require_once PCW_PLUGIN_DIR . 'includes/core/database/class-pcw-database.php';

		// Verificar se precisa atualizar banco de dados
		$this->maybe_upgrade_database();
		require_once PCW_PLUGIN_DIR . 'includes/core/cashback/class-pcw-cashback.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/cashback/class-pcw-cashback-rules.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/cashback/class-pcw-cashback-expiration.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/cashback/class-pcw-retroactive-batch.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/cashback/class-pcw-retroactive-processor.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/levels/class-pcw-levels.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/levels/class-pcw-level-calculator.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/levels/class-pcw-level-discounts.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/levels/class-pcw-level-expiration.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/wallet/class-pcw-wallet.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/wallet/class-pcw-wallet-transactions.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/notifications/class-pcw-notifications.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/notifications/class-pcw-email-handler.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/notifications/class-pcw-webhook-handler.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/notifications/class-pcw-webhooks.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/notifications/class-pcw-webhook-integration.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/notifications/class-pcw-webhook-variables.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/notifications/class-pcw-webhook-dispatcher.php';

		// Workflows
		require_once PCW_PLUGIN_DIR . 'includes/core/workflows/class-pcw-workflow-manager.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/workflows/class-pcw-workflow-triggers.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/workflows/class-pcw-workflow-conditions.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/workflows/class-pcw-workflow-actions.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/workflows/class-pcw-workflow-executor.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/workflows/class-pcw-workflow-scheduler.php';

		// Activity Tracking
		require_once PCW_PLUGIN_DIR . 'includes/core/tracking/class-pcw-activity-tracker.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/tracking/class-pcw-user-origin-tracker.php';

		// AI Integration
		require_once PCW_PLUGIN_DIR . 'includes/core/ai/class-pcw-openai.php';

		// Email/SMTP
		require_once PCW_PLUGIN_DIR . 'includes/core/email/class-pcw-smtp-accounts.php';

		// Automations
		require_once PCW_PLUGIN_DIR . 'includes/core/automations/class-pcw-automations.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/automations/class-pcw-automation-executor.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/automations/class-pcw-automation-tracking.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/automations/class-pcw-automation-analytics.php';

		// Campaigns
		require_once PCW_PLUGIN_DIR . 'includes/core/campaigns/class-pcw-campaigns.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/campaigns/class-pcw-custom-lists.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/campaigns/class-pcw-campaign-tracker.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/campaigns/class-pcw-contact-tags.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/campaigns/class-pcw-rfm-analysis.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/campaigns/class-pcw-user-sync.php';

		// Referrals (Indicações)
		require_once PCW_PLUGIN_DIR . 'includes/core/referrals/class-pcw-referral-codes.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/referrals/class-pcw-referrals.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/referrals/class-pcw-referral-rewards.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/referrals/class-pcw-referral-tracking.php';
		require_once PCW_PLUGIN_DIR . 'includes/core/referrals/class-pcw-referral-emails.php';

		// Admin
		if ( is_admin() ) {
			require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-ui.php';
			require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin.php';
			require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-referrals.php';
			require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-emails.php';
			require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-rfm-dashboard.php';
			require_once PCW_PLUGIN_DIR . 'includes/admin/class-pcw-admin-origin-reports.php';
		}

		// Public
		if ( ! is_admin() ) {
			require_once PCW_PLUGIN_DIR . 'includes/public/class-pcw-public.php';
			require_once PCW_PLUGIN_DIR . 'includes/public/class-pcw-cashback-display.php';
			require_once PCW_PLUGIN_DIR . 'includes/public/class-pcw-epc-integration.php';
			require_once PCW_PLUGIN_DIR . 'includes/public/class-pcw-subscribe-form.php';
			require_once PCW_PLUGIN_DIR . 'includes/public/class-pcw-unsubscribe.php';
		}

		// Referral Checkout - carregar sempre para AJAX funcionar
		require_once PCW_PLUGIN_DIR . 'includes/public/class-pcw-referral-checkout.php';
		require_once PCW_PLUGIN_DIR . 'includes/public/class-pcw-referral-my-account.php';
		require_once PCW_PLUGIN_DIR . 'includes/public/class-pcw-referral-public.php';

		// Message Queue & Rate Limiting
		require_once PCW_PLUGIN_DIR . 'includes/class-pcw-message-queue.php';

		// Integrations
		require_once PCW_PLUGIN_DIR . 'includes/integrations/class-pcw-woocommerce-integration.php';
		require_once PCW_PLUGIN_DIR . 'includes/integrations/class-pcw-manual-orders-integration.php';
		require_once PCW_PLUGIN_DIR . 'includes/integrations/class-pcw-google-analytics.php';
		require_once PCW_PLUGIN_DIR . 'includes/integrations/class-pcw-personizi.php';
		require_once PCW_PLUGIN_DIR . 'includes/integrations/class-pcw-sendpulse.php';
		
		// Forms Integrations
		require_once PCW_PLUGIN_DIR . 'includes/integrations/class-pcw-forms-integration.php';
		require_once PCW_PLUGIN_DIR . 'includes/integrations/class-pcw-elementor-forms-integration.php';
		require_once PCW_PLUGIN_DIR . 'includes/integrations/class-pcw-cf7-integration.php';
		require_once PCW_PLUGIN_DIR . 'includes/integrations/class-pcw-gravity-forms-integration.php';
		require_once PCW_PLUGIN_DIR . 'includes/integrations/class-pcw-wpforms-integration.php';
		
		// Gateway - carregar arquivos AGORA se WooCommerce já estiver disponível
		if ( class_exists( 'WooCommerce' ) && class_exists( 'WC_Payment_Gateway' ) ) {
			require_once PCW_PLUGIN_DIR . 'includes/integrations/class-pcw-payment-gateway.php';
			require_once PCW_PLUGIN_DIR . 'includes/integrations/class-pcw-gateway-loader.php';
			require_once PCW_PLUGIN_DIR . 'includes/integrations/class-pcw-wallet-discount.php';
			
			// Registrar gateway no filtro do WooCommerce
			add_filter( 'woocommerce_payment_gateways', array( 'PCW_Gateway_Loader', 'add_gateway' ), 10 );
		}
		
		// Inicializar integração com pedidos manuais
		PCW_Manual_Orders_Integration::init();

		// Inicializar executor de automações
		PCW_Automation_Executor::instance();

		// Inicializar tracking de automações
		PCW_Automation_Tracking::instance();

		// Helpers
		require_once PCW_PLUGIN_DIR . 'includes/helpers/class-pcw-helpers.php';
		require_once PCW_PLUGIN_DIR . 'includes/helpers/class-pcw-formatters.php';
		require_once PCW_PLUGIN_DIR . 'includes/helpers/class-pcw-automation-email-helper.php';
	}

	/**
	 * Registrar hooks
	 */
	private function register_hooks() {
		// Actions
		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		// Filters
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		// Inicializar classes principais
		if ( is_admin() ) {
			$admin = new PCW_Admin();
			$admin->init();
		} else {
			$public = new PCW_Public();
			$public->init();

			// Display de cashback no frontend
			$cashback_display = new PCW_Cashback_Display();
			$cashback_display->init();

			// Integração com Easy Product Creator
			$epc_integration = new PCW_EPC_Integration();
			$epc_integration->init();
		}

		// Integração WooCommerce
		$woocommerce_integration = new PCW_WooCommerce_Integration();
		$woocommerce_integration->init();

		// Sistema de expiração
		$cashback_expiration = new PCW_Cashback_Expiration();
		$cashback_expiration->init();

		$level_expiration = new PCW_Level_Expiration();
		$level_expiration->init();

		// Integração de webhooks
		$webhook_integration = new PCW_Webhook_Integration();
		$webhook_integration->init();

		// Dispatcher de webhooks (dispara webhooks configurados no admin)
		$webhook_dispatcher = PCW_Webhook_Dispatcher::instance();
		$webhook_dispatcher->init();

		// Handler de emails
		$email_handler = new PCW_Email_Handler();
		$email_handler->init();

		// Executor de workflows
		$workflow_executor = PCW_Workflow_Executor::instance();
		$workflow_executor->init();

		// Agendador de workflows (cron)
		$workflow_scheduler = PCW_Workflow_Scheduler::instance();
		$workflow_scheduler->init();

		// Activity tracker (frontend)
		$activity_tracker = PCW_Activity_Tracker::instance();
		$activity_tracker->init();

		// User Origin Tracker (UTMs, referrer, etc)
		$origin_tracker = PCW_User_Origin_Tracker::instance();
		$origin_tracker->init();

		// Google Analytics integration
		PCW_Google_Analytics::get_instance();

		// Message Queue & Rate Limiting
		$message_queue = PCW_Message_Queue_Manager::instance();
		$message_queue->init();

		// Campaign batch processing
		add_action( 'pcw_process_campaign_batch', array( PCW_Campaigns::instance(), 'process_batch' ) );

		// Campaign tracking (opens, clicks)
		$campaign_tracker = PCW_Campaign_Tracker::instance();
		$campaign_tracker->init();

		// RFM calculation (weekly)
		add_action( 'pcw_weekly_rfm_calculation', array( $this, 'calculate_rfm_segments' ) );

		// Sistema de Indicações (Referrals)
		$referrals = PCW_Referrals::instance();
		$referrals->init();

		$referral_rewards = PCW_Referral_Rewards::instance();
		$referral_rewards->init();

		$referral_tracking = PCW_Referral_Tracking::instance();
		$referral_tracking->init();

		$referral_emails = PCW_Referral_Emails::instance();
		$referral_emails->init();

		// Referrals - Admin
		if ( is_admin() ) {
			$admin_referrals = PCW_Admin_Referrals::instance();
			$admin_referrals->init();

			// Admin Emails
			$admin_emails = PCW_Admin_Emails::instance();
			$admin_emails->init();

			// RFM Dashboard
			$rfm_dashboard = PCW_Admin_RFM_Dashboard::instance();
			$rfm_dashboard->init();

			// Origin Reports
			$origin_reports = PCW_Admin_Origin_Reports::instance();
			$origin_reports->init();
		}

		// Referrals - Checkout (inicializar sempre para AJAX funcionar)
		$referral_checkout = PCW_Referral_Checkout::instance();
		$referral_checkout->init();

		// Wallet Discount - Checkout e Pay-Order
		if ( class_exists( 'PCW_Wallet_Discount' ) ) {
			PCW_Wallet_Discount::get_instance();
		}

		// Referrals - My Account e Public (apenas frontend)
		if ( ! is_admin() ) {
			$referral_my_account = PCW_Referral_My_Account::instance();
			$referral_my_account->init();

			$referral_public = PCW_Referral_Public::instance();
			$referral_public->init();

			// Subscribe Form
			$subscribe_form = PCW_Subscribe_Form::instance();
			$subscribe_form->init();

			// Unsubscribe
			$unsubscribe = PCW_Unsubscribe::instance();
			$unsubscribe->init();
		}

		// User Sync
		$user_sync = PCW_User_Sync::instance();
		$user_sync->init();

		// Forms Integrations - verificar se os plugins estão ativos
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$elementor_forms = new PCW_Elementor_Forms_Integration();
			$elementor_forms->init();
		}

		if ( class_exists( 'WPCF7' ) ) {
			$cf7_integration = new PCW_CF7_Integration();
			$cf7_integration->init();
		}

		if ( class_exists( 'GFForms' ) ) {
			$gravity_forms = new PCW_Gravity_Forms_Integration();
			$gravity_forms->init();
		}

		if ( function_exists( 'wpforms' ) ) {
			$wpforms = new PCW_WPForms_Integration();
			$wpforms->init();
		}
	}

	/**
	 * Calcular segmentos RFM (cron job)
	 */
	public function calculate_rfm_segments() {
		$rfm = PCW_RFM_Analysis::instance();
		$rfm->calculate_all_customers();
	}

	/**
	 * Verificar e executar upgrades de banco de dados
	 */
	private function maybe_upgrade_database() {
		$current_version = get_option( 'pcw_db_version', '1.0.0' );
		
		if ( version_compare( $current_version, PCW_VERSION, '<' ) ) {
			$database = new PCW_Database();
			$database->create_tables();
			
			// Upgrade específico: adicionar coluna fingerprint na tabela activities.
			$this->upgrade_activities_table();

			// Upgrade: adicionar campos de lista personalizada em campanhas.
			$this->upgrade_campaigns_table();

			// Upgrade: adicionar opções padrão de email retroativo.
			$this->upgrade_email_retroactive_options();
			
			// Atualizar versão.
			update_option( 'pcw_db_version', PCW_VERSION );
		}
	}

	/**
	 * Upgrade: adicionar opções de email retroativo
	 */
	private function upgrade_email_retroactive_options() {
		// Adicionar opções de email retroativo se não existirem.
		if ( false === get_option( 'pcw_email_retroactive_enabled' ) ) {
			add_option( 'pcw_email_retroactive_enabled', 'yes' );
		}
		if ( false === get_option( 'pcw_email_retroactive_subject' ) ) {
			add_option( 'pcw_email_retroactive_subject', __( '🎁 Você ganhou cashback retroativo!', 'person-cash-wallet' ) );
		}
		if ( false === get_option( 'pcw_email_retroactive_body' ) ) {
			add_option( 'pcw_email_retroactive_body', '' );
		}

		// Atualizar configurações de notificação para incluir cashback_retroactive.
		$this->upgrade_notification_settings();
	}

	/**
	 * Upgrade: atualizar configurações de notificação
	 */
	private function upgrade_notification_settings() {
		$settings = get_option( 'pcw_notification_settings', array() );

		// Adicionar cashback_retroactive se não existir.
		if ( empty( $settings['cashback_retroactive'] ) ) {
			$settings['cashback_retroactive'] = array(
				'enabled' => 'yes',
				'subject' => __( 'Você ganhou cashback retroativo!', 'person-cash-wallet' ),
				'body'    => __( '<p>Olá, {customer_name}!</p><p>Boa notícia! Você acaba de receber <strong>{cashback_amount}</strong> em cashback referente ao pedido <strong>#{order_id}</strong> realizado em {order_date}.</p><p>Seu saldo atual: <strong>{current_balance}</strong></p><p>Use seu cashback na próxima compra!</p><p>Atenciosamente,<br>Equipe {site_name}</p>', 'person-cash-wallet' ),
			);
			update_option( 'pcw_notification_settings', $settings );
		}
	}

	/**
	 * Upgrade da tabela de atividades
	 */
	private function upgrade_activities_table() {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_activities';

		// Verificar se tabela existe
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
		if ( ! $table_exists ) {
			return;
		}

		// Verificar se coluna fingerprint existe
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
		
		if ( ! in_array( 'fingerprint', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN fingerprint varchar(32) DEFAULT NULL AFTER user_id" );
			$wpdb->query( "ALTER TABLE {$table} ADD INDEX fingerprint (fingerprint)" );
		}

		// Adicionar índice em ip_address se não existir
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'ip_address'" );
		if ( empty( $indexes ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD INDEX ip_address (ip_address)" );
		}
	}

	/**
	 * Upgrade da tabela de campanhas
	 */
	private function upgrade_campaigns_table() {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_campaigns';

		// Verificar se tabela existe
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
		if ( ! $table_exists ) {
			return;
		}

		// Verificar se colunas existem
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
		
		if ( ! in_array( 'audience_type', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN audience_type varchar(50) DEFAULT 'filtered' AFTER recipient_conditions" );
		}

		if ( ! in_array( 'custom_list_id', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN custom_list_id bigint(20) UNSIGNED DEFAULT NULL AFTER audience_type" );
			$wpdb->query( "ALTER TABLE {$table} ADD INDEX custom_list_id (custom_list_id)" );
		}
	}
}
