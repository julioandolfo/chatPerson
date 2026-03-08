<?php
/**
 * Classe de gerenciamento do banco de dados
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de banco de dados
 */
class PCW_Database {

	/**
	 * Versão do schema do banco de dados
	 *
	 * @var string
	 */
	private $db_version = '1.6.0';

	/**
	 * Construtor
	 */
	public function __construct() {
		// Vazio
	}

	/**
	 * Criar todas as tabelas
	 */
	public function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Tabela de cashback
		$this->create_cashback_table( $charset_collate );

		// Tabela de regras de cashback
		$this->create_cashback_rules_table( $charset_collate );

		// Tabela de histórico de cashback
		$this->create_cashback_history_table( $charset_collate );

		// Tabela de níveis
		$this->create_levels_table( $charset_collate );

		// Tabela de requisitos de níveis
		$this->create_level_requirements_table( $charset_collate );

		// Tabela de descontos de níveis
		$this->create_level_discounts_table( $charset_collate );

		// Tabela de níveis dos usuários
		$this->create_user_levels_table( $charset_collate );

		// Tabela de wallet
		$this->create_wallet_table( $charset_collate );

		// Tabela de transações da wallet
		$this->create_wallet_transactions_table( $charset_collate );

		// Tabela de webhooks
		$this->create_webhooks_table( $charset_collate );

		// Tabela de logs de webhooks
		$this->create_webhook_logs_table( $charset_collate );

		// Tabela de batches retroativos
		$this->create_retroactive_batches_table( $charset_collate );

		// Tabela de workflows
		$this->create_workflows_table( $charset_collate );

		// Tabela de logs de workflows
		$this->create_workflow_logs_table( $charset_collate );

		// Tabela de atividades (live dashboard)
		$this->create_activities_table( $charset_collate );

		// Tabela de automações
		$this->create_automations_table( $charset_collate );

		// Tabela de execuções de automações
		$this->create_automation_executions_table( $charset_collate );

		// Tabela de eventos de automações
		$this->create_automation_events_table( $charset_collate );

		// Tabela de tracking de emails
		$this->create_email_tracking_table( $charset_collate );

		// Tabela de tracking de links
		$this->create_link_tracking_table( $charset_collate );

		// Tabela de listas personalizadas
		$this->create_custom_lists_table( $charset_collate );

		// Tabela de membros das listas
		$this->create_list_members_table( $charset_collate );

		// Tabela de contas SMTP
		$this->create_smtp_accounts_table( $charset_collate );

		// Tabela de contas SendPulse
		$this->create_sendpulse_accounts_table( $charset_collate );

		// Tabela de campanhas
		$this->create_campaigns_table( $charset_collate );

		// Tabela de envios de campanhas
		$this->create_campaign_sends_table( $charset_collate );

		// Tabela de tracking de campanhas
		$this->create_campaign_tracking_table( $charset_collate );

		// Tabelas de indicações (referrals)
		$this->create_referral_codes_table( $charset_collate );
		$this->create_referrals_table( $charset_collate );
		$this->create_referral_emails_table( $charset_collate );
		$this->create_referral_clicks_table( $charset_collate );
		$this->create_email_logs_table( $charset_collate );

		// Novas tabelas - Sistema de Tags
		$this->create_contact_tags_table( $charset_collate );
		$this->create_contact_tag_relations_table( $charset_collate );

		// Tabela de submissões de formulários
		$this->create_form_submissions_table( $charset_collate );

		// Tabela de segmentos RFM
		$this->create_rfm_segments_table( $charset_collate );

		// Tabela de analytics de listas
		$this->create_list_analytics_table( $charset_collate );

		// Tabela de unsubscribes
		$this->create_unsubscribes_table( $charset_collate );

		// Tabelas de tracking de origem (v1.5.0)
		$this->create_user_sessions_table( $charset_collate );
		$this->create_order_attributions_table( $charset_collate );

		// Salvar versão do schema
		update_option( 'pcw_db_version', $this->db_version );
	}

	/**
	 * Criar tabela de cashback
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_cashback_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_cashback';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			order_id bigint(20) UNSIGNED NOT NULL,
			amount decimal(10,2) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			earned_date datetime NOT NULL,
			expires_date datetime DEFAULT NULL,
			used_date datetime DEFAULT NULL,
			order_item_id bigint(20) UNSIGNED DEFAULT NULL,
			rule_id bigint(20) UNSIGNED DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY order_id (order_id),
			KEY status (status),
			KEY expires_date (expires_date),
			KEY rule_id (rule_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de regras de cashback
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_cashback_rules_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_cashback_rules';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			status varchar(20) NOT NULL DEFAULT 'active',
			type varchar(20) NOT NULL DEFAULT 'percentage',
			value decimal(10,2) NOT NULL,
			min_order_amount decimal(10,2) DEFAULT 0,
			max_cashback_amount decimal(10,2) DEFAULT 0,
			expiration_days int(11) DEFAULT NULL,
			expiration_type varchar(20) DEFAULT 'days',
			product_categories text,
			excluded_products text,
			user_roles text,
			conditions text,
			priority int(11) DEFAULT 10,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY priority (priority)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de histórico de cashback
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_cashback_history_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_cashback_history';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			cashback_id bigint(20) UNSIGNED DEFAULT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			order_id bigint(20) UNSIGNED DEFAULT NULL,
			type varchar(20) NOT NULL,
			amount decimal(10,2) NOT NULL,
			balance_before decimal(10,2) DEFAULT 0,
			balance_after decimal(10,2) DEFAULT 0,
			description text,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY cashback_id (cashback_id),
			KEY user_id (user_id),
			KEY order_id (order_id),
			KEY type (type),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de níveis
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_levels_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_levels';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			slug varchar(100) NOT NULL,
			level_number int(11) NOT NULL,
			badge_image varchar(255) DEFAULT NULL,
			color varchar(7) DEFAULT NULL,
			description text,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY level_number (level_number),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de requisitos de níveis
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_level_requirements_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_level_requirements';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			level_id bigint(20) UNSIGNED NOT NULL,
			requirement_type varchar(50) NOT NULL,
			requirement_value decimal(10,2) NOT NULL,
			period_type varchar(50) DEFAULT 'lifetime',
			period_start date DEFAULT NULL,
			period_end date DEFAULT NULL,
			conditions text,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY level_id (level_id),
			KEY requirement_type (requirement_type)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de descontos de níveis
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_level_discounts_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_level_discounts';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			level_id bigint(20) UNSIGNED NOT NULL,
			discount_type varchar(50) NOT NULL,
			discount_value decimal(10,2) NOT NULL,
			min_order_amount decimal(10,2) DEFAULT 0,
			max_discount_amount decimal(10,2) DEFAULT 0,
			applicable_to varchar(50) DEFAULT 'all',
			product_ids text,
			category_ids text,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY level_id (level_id),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de níveis dos usuários
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_user_levels_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_user_levels';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			level_id bigint(20) UNSIGNED NOT NULL,
			achieved_date datetime NOT NULL,
			expires_date datetime DEFAULT NULL,
			expiration_type varchar(20) DEFAULT 'never',
			expiration_days int(11) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			last_renewal_date datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY level_id (level_id),
			KEY status (status),
			KEY expires_date (expires_date)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de wallet
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_wallet_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_wallet';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			balance decimal(10,2) NOT NULL DEFAULT 0,
			total_earned decimal(10,2) NOT NULL DEFAULT 0,
			total_spent decimal(10,2) NOT NULL DEFAULT 0,
			currency varchar(3) NOT NULL DEFAULT 'BRL',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de transações da wallet
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_wallet_transactions_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_wallet_transactions';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			wallet_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			order_id bigint(20) UNSIGNED DEFAULT NULL,
			type varchar(20) NOT NULL,
			source varchar(50) NOT NULL,
			amount decimal(10,2) NOT NULL,
			balance_before decimal(10,2) NOT NULL DEFAULT 0,
			balance_after decimal(10,2) NOT NULL DEFAULT 0,
			description text,
			reference_id bigint(20) UNSIGNED DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'completed',
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY wallet_id (wallet_id),
			KEY user_id (user_id),
			KEY order_id (order_id),
			KEY type (type),
			KEY source (source),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de webhooks
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_webhooks_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_webhooks';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			type varchar(50) NOT NULL DEFAULT 'personizi_whatsapp',
			event varchar(100) NOT NULL,
			url text NOT NULL,
			method varchar(10) NOT NULL DEFAULT 'POST',
			auth_type varchar(20) NOT NULL DEFAULT 'bearer',
			auth_token text,
			headers text,
			body_template text,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY type (type),
			KEY event (event),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de logs de webhooks
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_webhook_logs_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_webhook_logs';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			webhook_id bigint(20) UNSIGNED NOT NULL,
			event_type varchar(100) NOT NULL,
			payload longtext,
			response_code int(11) DEFAULT NULL,
			response_body text,
			status varchar(20) NOT NULL,
			retry_count int(11) NOT NULL DEFAULT 0,
			error_message text,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY webhook_id (webhook_id),
			KEY event_type (event_type),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de batches retroativos
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_retroactive_batches_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_retroactive_batches';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			batch_id varchar(100) NOT NULL,
			created_at datetime NOT NULL,
			filters longtext NOT NULL,
			total_orders int(11) NOT NULL DEFAULT 0,
			total_cashback int(11) NOT NULL DEFAULT 0,
			total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			status varchar(20) NOT NULL DEFAULT 'pending',
			processed_by bigint(20) NOT NULL,
			completed_at datetime DEFAULT NULL,
			error_log longtext DEFAULT NULL,
			PRIMARY KEY (id),
			KEY batch_id (batch_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de workflows
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_workflows_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_workflows';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			status varchar(20) NOT NULL DEFAULT 'active',
			trigger_type varchar(100) NOT NULL,
			trigger_config longtext,
			conditions longtext,
			actions longtext,
			execution_count bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			last_execution datetime DEFAULT NULL,
			priority int(11) NOT NULL DEFAULT 10,
			created_by bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY trigger_type (trigger_type),
			KEY priority (priority),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de logs de workflows
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_workflow_logs_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_workflow_logs';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			workflow_id bigint(20) UNSIGNED NOT NULL,
			trigger_type varchar(100) NOT NULL,
			trigger_data longtext,
			context longtext,
			conditions_result tinyint(1) DEFAULT 1,
			actions_executed longtext,
			result longtext,
			status varchar(20) NOT NULL DEFAULT 'success',
			error_message text,
			execution_time float DEFAULT 0,
			executed_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY workflow_id (workflow_id),
			KEY trigger_type (trigger_type),
			KEY status (status),
			KEY executed_at (executed_at),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		
		// Migrar tabela existente: adicionar colunas faltantes
		$this->migrate_workflow_logs_table( $table_name );
	}

	/**
	 * Migrar tabela de logs de workflow para adicionar colunas faltantes
	 *
	 * @param string $table_name Nome da tabela.
	 */
	private function migrate_workflow_logs_table( $table_name ) {
		global $wpdb;
		
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table_name}", 0 );
		
		if ( empty( $columns ) ) {
			return;
		}
		
		if ( ! in_array( 'executed_at', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN executed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER execution_time" );
			$wpdb->query( "ALTER TABLE {$table_name} ADD KEY executed_at (executed_at)" );
			// Preencher executed_at com created_at para registros existentes
			$wpdb->query( "UPDATE {$table_name} SET executed_at = created_at WHERE executed_at = '0000-00-00 00:00:00'" );
		}
		
		if ( ! in_array( 'context', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN context longtext AFTER trigger_data" );
		}
		
		if ( ! in_array( 'result', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN result longtext AFTER actions_executed" );
		}
	}

	/**
	 * Criar tabela de atividades (live dashboard)
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_activities_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_activities';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id varchar(100) NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			fingerprint varchar(32) DEFAULT NULL,
			activity_type varchar(50) NOT NULL,
			object_type varchar(50) DEFAULT NULL,
			object_id bigint(20) UNSIGNED DEFAULT NULL,
			object_name varchar(255) DEFAULT NULL,
			object_price decimal(10,2) DEFAULT NULL,
			object_image varchar(500) DEFAULT NULL,
			page_url varchar(500) DEFAULT NULL,
			referrer varchar(500) DEFAULT NULL,
			user_agent varchar(500) DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			extra_data longtext,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY user_id (user_id),
			KEY fingerprint (fingerprint),
			KEY activity_type (activity_type),
			KEY object_type (object_type),
			KEY ip_address (ip_address),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de automações
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_automations_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_automations';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			type varchar(50) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			trigger_type varchar(100) NOT NULL,
			trigger_config longtext,
			workflow_steps longtext,
			email_template longtext,
			email_subject varchar(255) DEFAULT NULL,
			use_ai_subject tinyint(1) DEFAULT 0,
			channels varchar(100) DEFAULT 'email',
			stats_sent bigint(20) UNSIGNED DEFAULT 0,
			stats_opened bigint(20) UNSIGNED DEFAULT 0,
			stats_clicked bigint(20) UNSIGNED DEFAULT 0,
			stats_converted bigint(20) UNSIGNED DEFAULT 0,
			created_by bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY type (type),
			KEY status (status),
			KEY trigger_type (trigger_type)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de execuções de automações
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_automation_executions_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_automation_executions';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			automation_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			object_id bigint(20) UNSIGNED DEFAULT NULL,
			current_step int(11) DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			scheduled_at datetime DEFAULT NULL,
			executed_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			step_results longtext,
			error_message text,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY automation_id (automation_id),
			KEY user_id (user_id),
			KEY status (status),
			KEY scheduled_at (scheduled_at),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de eventos de automações
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_automation_events_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_automation_events';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			automation_id bigint(20) UNSIGNED NOT NULL,
			execution_id bigint(20) UNSIGNED DEFAULT NULL,
			event_type varchar(50) NOT NULL,
			step_index int(11) DEFAULT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			email varchar(255) DEFAULT NULL,
			link_clicked varchar(500) DEFAULT NULL,
			conversion_value decimal(10,2) DEFAULT NULL,
			order_id bigint(20) UNSIGNED DEFAULT NULL,
			metadata longtext,
			user_agent varchar(500) DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY automation_id (automation_id),
			KEY execution_id (execution_id),
			KEY event_type (event_type),
			KEY user_id (user_id),
			KEY order_id (order_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de tracking de emails
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_email_tracking_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_email_tracking';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			automation_id bigint(20) UNSIGNED NOT NULL,
			execution_id bigint(20) UNSIGNED NOT NULL,
			email_log_id bigint(20) UNSIGNED DEFAULT NULL,
			tracking_code varchar(32) NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			recipient_email varchar(255) NOT NULL,
			subject varchar(255) NOT NULL,
			utm_campaign varchar(255) DEFAULT NULL,
			utm_source varchar(100) DEFAULT 'automation',
			utm_medium varchar(100) DEFAULT 'email',
			sent_at datetime DEFAULT NULL,
			delivered_at datetime DEFAULT NULL,
			first_opened_at datetime DEFAULT NULL,
			last_opened_at datetime DEFAULT NULL,
			open_count int(11) DEFAULT 0,
			first_clicked_at datetime DEFAULT NULL,
			last_clicked_at datetime DEFAULT NULL,
			click_count int(11) DEFAULT 0,
			clicks_detail longtext,
			conversion_at datetime DEFAULT NULL,
			conversion_order_id bigint(20) UNSIGNED DEFAULT NULL,
			conversion_value decimal(10,2) DEFAULT NULL,
			device_type varchar(20) DEFAULT NULL,
			email_client varchar(100) DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY tracking_code (tracking_code),
			KEY automation_id (automation_id),
			KEY execution_id (execution_id),
			KEY user_id (user_id),
			KEY recipient_email (recipient_email),
			KEY conversion_order_id (conversion_order_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de tracking de links
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_link_tracking_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_link_tracking';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			automation_id bigint(20) UNSIGNED NOT NULL,
			email_tracking_id bigint(20) UNSIGNED NOT NULL,
			link_url varchar(500) NOT NULL,
			link_text varchar(255) DEFAULT NULL,
			link_position int(11) DEFAULT NULL,
			tracking_hash varchar(32) NOT NULL,
			redirect_url varchar(1000) NOT NULL,
			clicked_count int(11) DEFAULT 0,
			unique_clicks int(11) DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY tracking_hash (tracking_hash),
			KEY automation_id (automation_id),
			KEY email_tracking_id (email_tracking_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de contas SMTP
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_smtp_accounts_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_smtp_accounts';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			from_email varchar(255) NOT NULL,
			from_name varchar(255) NOT NULL,
			provider varchar(50) NOT NULL DEFAULT 'custom',
			host varchar(255) DEFAULT NULL,
			port int(11) DEFAULT 587,
			encryption varchar(10) DEFAULT 'tls',
			username varchar(255) DEFAULT NULL,
			password varchar(255) DEFAULT NULL,
			fluent_connection_id bigint(20) UNSIGNED DEFAULT NULL,
			daily_limit int(11) DEFAULT 0,
			rate_limit_hour int(11) DEFAULT 60,
			distribution_weight int(11) DEFAULT 100,
			distribution_enabled tinyint(1) DEFAULT 1,
			sent_today int(11) DEFAULT 0,
			sent_last_hour int(11) DEFAULT 0,
			sent_last_reset datetime DEFAULT NULL,
			last_sent_reset date DEFAULT NULL,
			total_sent int(11) DEFAULT 0,
			total_failed int(11) DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY provider (provider),
			KEY status (status),
			KEY distribution_enabled (distribution_enabled)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de contas SendPulse
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_sendpulse_accounts_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_sendpulse_accounts';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			client_id varchar(255) NOT NULL,
			client_secret varchar(255) NOT NULL,
			from_email varchar(255) NOT NULL,
			from_name varchar(255) NOT NULL,
			rate_limit_hour int(11) DEFAULT 1000,
			distribution_weight int(11) DEFAULT 100,
			distribution_enabled tinyint(1) DEFAULT 1,
			sent_last_hour int(11) DEFAULT 0,
			sent_today int(11) DEFAULT 0,
			sent_last_reset datetime DEFAULT NULL,
			last_sent_reset date DEFAULT NULL,
			total_sent int(11) DEFAULT 0,
			total_failed int(11) DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY distribution_enabled (distribution_enabled)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de campanhas
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_campaigns_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_campaigns';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			subject varchar(255) NOT NULL,
			preview_text varchar(255) DEFAULT NULL,
			content longtext NOT NULL,
			smtp_account_id bigint(20) UNSIGNED DEFAULT NULL,
			recipient_conditions longtext,
			status varchar(20) NOT NULL DEFAULT 'draft',
			scheduled_at datetime DEFAULT NULL,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			batch_size int(11) DEFAULT 50,
			batch_delay int(11) DEFAULT 60,
			total_recipients int(11) DEFAULT 0,
			sent_count int(11) DEFAULT 0,
			opened_count int(11) DEFAULT 0,
			clicked_count int(11) DEFAULT 0,
			bounced_count int(11) DEFAULT 0,
			unsubscribed_count int(11) DEFAULT 0,
			created_by bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY smtp_account_id (smtp_account_id),
			KEY scheduled_at (scheduled_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de envios de campanhas
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_campaign_sends_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_campaign_sends';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			email varchar(255) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			sent_at datetime DEFAULT NULL,
			opened_at datetime DEFAULT NULL,
			clicked_at datetime DEFAULT NULL,
			bounced_at datetime DEFAULT NULL,
			unsubscribed_at datetime DEFAULT NULL,
			open_count int(11) DEFAULT 0,
			click_count int(11) DEFAULT 0,
			error_message text,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY campaign_id (campaign_id),
			KEY user_id (user_id),
			KEY email (email),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de tracking de campanhas
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_campaign_tracking_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_campaign_tracking';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) UNSIGNED NOT NULL,
			send_id bigint(20) UNSIGNED NOT NULL,
			event_type varchar(20) NOT NULL,
			link_url varchar(500) DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			user_agent varchar(500) DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY campaign_id (campaign_id),
			KEY send_id (send_id),
			KEY event_type (event_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de códigos de indicação
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_referral_codes_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_referral_codes';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			code varchar(20) NOT NULL,
			total_referrals int(11) NOT NULL DEFAULT 0,
			total_conversions int(11) NOT NULL DEFAULT 0,
			total_earned decimal(10,2) NOT NULL DEFAULT 0.00,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id),
			UNIQUE KEY code (code),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de indicações
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_referrals_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_referrals';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			referrer_user_id bigint(20) UNSIGNED NOT NULL,
			referrer_code varchar(20) NOT NULL,
			referred_name varchar(255) NOT NULL,
			referred_email varchar(255) NOT NULL,
			referred_phone varchar(20) NOT NULL,
			referred_user_id bigint(20) UNSIGNED DEFAULT NULL,
			referred_order_id bigint(20) UNSIGNED DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			reward_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			reward_transaction_id bigint(20) UNSIGNED DEFAULT NULL,
			referred_reward_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			referred_reward_transaction_id bigint(20) UNSIGNED DEFAULT NULL,
			conversion_count int(11) NOT NULL DEFAULT 0,
			source varchar(50) NOT NULL DEFAULT 'manual',
			ip_address varchar(45) DEFAULT NULL,
			notes text,
			created_at datetime NOT NULL,
			converted_at datetime DEFAULT NULL,
			rewarded_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY referrer_user_id (referrer_user_id),
			KEY referrer_code (referrer_code),
			KEY referred_email (referred_email),
			KEY referred_user_id (referred_user_id),
			KEY referred_order_id (referred_order_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de emails de indicação enviados
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_referral_emails_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_referral_emails';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			order_id bigint(20) UNSIGNED NOT NULL,
			email_type varchar(50) NOT NULL DEFAULT 'request_referral',
			token varchar(64) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'sent',
			sent_at datetime NOT NULL,
			opened_at datetime DEFAULT NULL,
			clicked_at datetime DEFAULT NULL,
			referrals_from_email int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY order_id (order_id),
			KEY token (token),
			KEY status (status),
			KEY sent_at (sent_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de clicks em links de indicação
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_referral_clicks_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_referral_clicks';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			referral_code varchar(20) NOT NULL,
			ip_address varchar(45) DEFAULT NULL,
			user_agent varchar(500) DEFAULT NULL,
			referrer_url varchar(500) DEFAULT NULL,
			landing_page varchar(500) DEFAULT NULL,
			converted tinyint(1) NOT NULL DEFAULT 0,
			order_id bigint(20) UNSIGNED DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY referral_code (referral_code),
			KEY ip_address (ip_address),
			KEY converted (converted),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de logs de emails
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_email_logs_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_email_logs';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			recipient varchar(255) NOT NULL,
			subject varchar(255) NOT NULL,
			content longtext NOT NULL,
			email_type varchar(50) NOT NULL DEFAULT 'general',
			status varchar(20) NOT NULL DEFAULT 'sent',
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			order_id bigint(20) UNSIGNED DEFAULT NULL,
			related_id bigint(20) UNSIGNED DEFAULT NULL,
			metadata text DEFAULT NULL,
			opened_at datetime DEFAULT NULL,
			clicked_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY recipient (recipient),
			KEY email_type (email_type),
			KEY status (status),
			KEY user_id (user_id),
			KEY order_id (order_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de listas personalizadas
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_custom_lists_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_custom_lists';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text DEFAULT NULL,
			type varchar(50) NOT NULL DEFAULT 'manual',
			total_members int(11) NOT NULL DEFAULT 0,
			created_by bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY type (type),
			KEY created_by (created_by),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de membros das listas
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_list_members_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_list_members';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			list_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			email varchar(255) NOT NULL,
			name varchar(255) DEFAULT NULL,
			phone varchar(50) DEFAULT NULL,
			metadata text DEFAULT NULL,
			added_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY list_email (list_id, email),
			KEY list_id (list_id),
			KEY user_id (user_id),
			KEY email (email)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de tags de contatos
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_contact_tags_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_contact_tags';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			slug varchar(100) NOT NULL,
			description text DEFAULT NULL,
			color varchar(7) DEFAULT '#3b82f6',
			total_contacts int(11) NOT NULL DEFAULT 0,
			created_by bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY created_by (created_by)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de relações de tags
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_contact_tag_relations_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_contact_tag_relations';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tag_id bigint(20) UNSIGNED NOT NULL,
			member_id bigint(20) UNSIGNED NOT NULL,
			list_id bigint(20) UNSIGNED NOT NULL,
			added_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY tag_member (tag_id, member_id),
			KEY tag_id (tag_id),
			KEY member_id (member_id),
			KEY list_id (list_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de submissões de formulários
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_form_submissions_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_form_submissions';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_provider varchar(50) NOT NULL,
			form_id varchar(100) DEFAULT NULL,
			form_name varchar(255) DEFAULT NULL,
			email varchar(255) NOT NULL,
			name varchar(255) DEFAULT NULL,
			phone varchar(50) DEFAULT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			list_id bigint(20) UNSIGNED DEFAULT NULL,
			automation_id bigint(20) UNSIGNED DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			form_data longtext,
			ip_address varchar(45) DEFAULT NULL,
			user_agent varchar(500) DEFAULT NULL,
			page_url varchar(500) DEFAULT NULL,
			referrer varchar(500) DEFAULT NULL,
			created_at datetime NOT NULL,
			processed_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY form_provider (form_provider),
			KEY email (email),
			KEY user_id (user_id),
			KEY list_id (list_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de segmentos RFM
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_rfm_segments_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_rfm_segments';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			recency_score int(11) NOT NULL DEFAULT 0,
			frequency_score int(11) NOT NULL DEFAULT 0,
			monetary_score int(11) NOT NULL DEFAULT 0,
			rfm_score varchar(10) NOT NULL,
			segment varchar(50) NOT NULL,
			last_order_date datetime DEFAULT NULL,
			days_since_last_order int(11) DEFAULT NULL,
			total_orders int(11) NOT NULL DEFAULT 0,
			total_spent decimal(10,2) NOT NULL DEFAULT 0.00,
			average_order_value decimal(10,2) NOT NULL DEFAULT 0.00,
			calculated_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id),
			KEY segment (segment),
			KEY rfm_score (rfm_score),
			KEY recency_score (recency_score),
			KEY frequency_score (frequency_score),
			KEY monetary_score (monetary_score),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de analytics de listas
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_list_analytics_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_list_analytics';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			list_id bigint(20) UNSIGNED NOT NULL,
			date date NOT NULL,
			new_subscribers int(11) NOT NULL DEFAULT 0,
			unsubscribers int(11) NOT NULL DEFAULT 0,
			total_subscribers int(11) NOT NULL DEFAULT 0,
			emails_sent int(11) NOT NULL DEFAULT 0,
			emails_opened int(11) NOT NULL DEFAULT 0,
			emails_clicked int(11) NOT NULL DEFAULT 0,
			conversions int(11) NOT NULL DEFAULT 0,
			conversion_value decimal(10,2) NOT NULL DEFAULT 0.00,
			bounce_count int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY list_date (list_id, date),
			KEY list_id (list_id),
			KEY date (date)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de unsubscribes
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_unsubscribes_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_unsubscribes';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			email varchar(255) NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			list_id bigint(20) UNSIGNED DEFAULT NULL,
			reason varchar(255) DEFAULT NULL,
			feedback text DEFAULT NULL,
			unsubscribe_all tinyint(1) NOT NULL DEFAULT 0,
			ip_address varchar(45) DEFAULT NULL,
			user_agent varchar(500) DEFAULT NULL,
			source varchar(50) DEFAULT 'manual',
			campaign_id bigint(20) UNSIGNED DEFAULT NULL,
			automation_id bigint(20) UNSIGNED DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY email (email),
			KEY user_id (user_id),
			KEY list_id (list_id),
			KEY unsubscribe_all (unsubscribe_all),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de sessões de usuários (tracking de origem)
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_user_sessions_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_user_sessions';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id varchar(100) NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			user_agent varchar(500) DEFAULT NULL,
			utm_source varchar(255) DEFAULT NULL,
			utm_medium varchar(255) DEFAULT NULL,
			utm_campaign varchar(255) DEFAULT NULL,
			utm_term varchar(255) DEFAULT NULL,
			utm_content varchar(255) DEFAULT NULL,
			utm_id varchar(255) DEFAULT NULL,
			click_id varchar(255) DEFAULT NULL,
			click_id_type varchar(50) DEFAULT NULL,
			click_platform varchar(50) DEFAULT NULL,
			referrer varchar(500) DEFAULT NULL,
			referrer_domain varchar(255) DEFAULT NULL,
			landing_page varchar(500) DEFAULT NULL,
			referral_code varchar(50) DEFAULT NULL,
			email_tracking_id varchar(100) DEFAULT NULL,
			automation_id bigint(20) UNSIGNED DEFAULT 0,
			campaign_id bigint(20) UNSIGNED DEFAULT 0,
			channel varchar(50) DEFAULT NULL,
			device_type varchar(20) DEFAULT NULL,
			device_os varchar(50) DEFAULT NULL,
			device_browser varchar(50) DEFAULT NULL,
			screen_resolution varchar(20) DEFAULT NULL,
			timezone varchar(100) DEFAULT NULL,
			language varchar(20) DEFAULT NULL,
			pages_viewed int(11) DEFAULT 1,
			is_first_visit tinyint(1) DEFAULT 0,
			last_page varchar(500) DEFAULT NULL,
			last_activity datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY user_id (user_id),
			KEY ip_address (ip_address),
			KEY utm_source (utm_source),
			KEY utm_campaign (utm_campaign),
			KEY channel (channel),
			KEY referral_code (referral_code),
			KEY click_id_type (click_id_type),
			KEY automation_id (automation_id),
			KEY campaign_id (campaign_id),
			KEY device_type (device_type),
			KEY is_first_visit (is_first_visit),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Criar tabela de atribuições de pedidos
	 *
	 * @param string $charset_collate Charset collate.
	 */
	private function create_order_attributions_table( $charset_collate ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_order_attributions';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			session_id varchar(100) DEFAULT NULL,
			first_touch_source varchar(255) DEFAULT NULL,
			first_touch_medium varchar(255) DEFAULT NULL,
			first_touch_campaign varchar(255) DEFAULT NULL,
			first_touch_channel varchar(50) DEFAULT NULL,
			first_touch_referrer varchar(255) DEFAULT NULL,
			first_touch_landing varchar(500) DEFAULT NULL,
			first_touch_timestamp datetime DEFAULT NULL,
			last_touch_source varchar(255) DEFAULT NULL,
			last_touch_medium varchar(255) DEFAULT NULL,
			last_touch_campaign varchar(255) DEFAULT NULL,
			last_touch_channel varchar(50) DEFAULT NULL,
			last_touch_referrer varchar(255) DEFAULT NULL,
			last_touch_timestamp datetime DEFAULT NULL,
			gclid varchar(255) DEFAULT NULL,
			fbclid varchar(255) DEFAULT NULL,
			msclkid varchar(255) DEFAULT NULL,
			referral_code varchar(50) DEFAULT NULL,
			automation_id bigint(20) UNSIGNED DEFAULT 0,
			campaign_id bigint(20) UNSIGNED DEFAULT 0,
			device_type varchar(20) DEFAULT NULL,
			device_os varchar(50) DEFAULT NULL,
			device_browser varchar(50) DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			attribution_data longtext,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY order_id (order_id),
			KEY user_id (user_id),
			KEY session_id (session_id),
			KEY first_touch_source (first_touch_source),
			KEY first_touch_channel (first_touch_channel),
			KEY last_touch_source (last_touch_source),
			KEY last_touch_channel (last_touch_channel),
			KEY referral_code (referral_code),
			KEY gclid (gclid),
			KEY fbclid (fbclid),
			KEY automation_id (automation_id),
			KEY campaign_id (campaign_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
