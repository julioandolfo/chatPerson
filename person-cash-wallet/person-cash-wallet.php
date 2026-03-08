<?php
/**
 * Plugin Name: Growly Digital
 * Plugin URI: https://growly.digital
 * Description: Sistema completo de cashback, níveis e wallet para WooCommerce
 * Version: 2.1.0
 * Author: Growly Digital
 * Author URI: https://growly.digital
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: person-cash-wallet
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevenir acesso direto
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Definir constantes
define( 'PCW_VERSION', '2.8.3' );
define( 'PCW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PCW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PCW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PCW_PLUGIN_FILE', __FILE__ );

// Verificar se WooCommerce está ativo
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	add_action( 'admin_notices', 'pcw_woocommerce_missing_notice' );
	return;
}

/**
 * Aviso se WooCommerce não estiver ativo
 */
function pcw_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Growly Digital requer WooCommerce para funcionar. Por favor, instale e ative o WooCommerce.', 'person-cash-wallet' ); ?></p>
	</div>
	<?php
}

// Carregar classes de ativação/desativação primeiro
require_once PCW_PLUGIN_DIR . 'includes/class-pcw-activator.php';
require_once PCW_PLUGIN_DIR . 'includes/class-pcw-deactivator.php';

// Registrar hooks de ativação/desativação
register_activation_hook( __FILE__, array( 'PCW_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PCW_Deactivator', 'deactivate' ) );

// CRÍTICO: Registrar hooks de cron IMEDIATAMENTE (não esperar por init/plugins_loaded)
// O WP-Cron executa muito cedo, antes de qualquer hook do WordPress
add_action( 'pcw_workflow_scheduled_check', 'pcw_process_scheduled_workflows_early' );
add_action( 'pcw_process_message_queue', 'pcw_process_message_queue_early' );

/**
 * Callback antecipado para workflows agendados
 */
function pcw_process_scheduled_workflows_early() {
	// Log para debug
	if ( function_exists( 'wc_get_logger' ) ) {
		wc_get_logger()->info( '━━━━━ CRON: pcw_workflow_scheduled_check executado ━━━━━', array( 'source' => 'pcw-workflow-cron' ) );
	}
	
	// Verificar se WooCommerce está ativo
	if ( ! class_exists( 'WooCommerce' ) ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning( 'WooCommerce não está ativo, abortando workflow cron', array( 'source' => 'pcw-workflow-cron' ) );
		}
		return;
	}
	
	// Carregar dependências do scheduler
	$workflow_files = array(
		'includes/core/workflows/class-pcw-workflow-triggers.php',
		'includes/core/workflows/class-pcw-workflow-conditions.php',
		'includes/core/workflows/class-pcw-workflow-actions.php',
		'includes/core/workflows/class-pcw-workflow-manager.php',
		'includes/core/workflows/class-pcw-workflow-scheduler.php',
	);
	
	foreach ( $workflow_files as $file ) {
		$filepath = PCW_PLUGIN_DIR . $file;
		if ( file_exists( $filepath ) ) {
			require_once $filepath;
		}
	}
	
	// Carregar classes auxiliares
	$aux_files = array(
		'includes/class-pcw-wallet.php',
		'includes/class-pcw-message-queue.php',
		'includes/integrations/class-pcw-personizi-integration.php',
	);
	
	foreach ( $aux_files as $file ) {
		$filepath = PCW_PLUGIN_DIR . $file;
		if ( file_exists( $filepath ) ) {
			require_once $filepath;
		}
	}
	
	// Executar scheduler
	if ( class_exists( 'PCW_Workflow_Scheduler' ) ) {
		$scheduler = PCW_Workflow_Scheduler::instance();
		$scheduler->run_now();
		
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info( 'Workflow scheduler executado com sucesso', array( 'source' => 'pcw-workflow-cron' ) );
		}
	} else {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( 'PCW_Workflow_Scheduler não encontrado!', array( 'source' => 'pcw-workflow-cron' ) );
		}
	}
}

/**
 * Callback antecipado para fila de mensagens
 */
function pcw_process_message_queue_early() {
	// Carregar o queue manager se ainda não foi carregado
	if ( ! class_exists( 'PCW_Message_Queue_Manager' ) ) {
		require_once PCW_PLUGIN_DIR . 'includes/class-pcw-message-queue.php';
	}
	
	$queue_manager = PCW_Message_Queue_Manager::instance();
	$queue_manager->process_queue();
}

// Inicializar plugin após WooCommerce estar carregado
add_action( 'plugins_loaded', 'pcw_init', 20 );

/**
 * Inicializar plugin
 */
function pcw_init() {
	// Verificar se WooCommerce está ativo
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Carregar autoloader
	require_once PCW_PLUGIN_DIR . 'includes/class-pcw-loader.php';

	$loader = new PCW_Loader();
	$loader->run();
}
