<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

// Se não foi chamado pelo WordPress, sair
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Opção para limpar dados na desinstalação
$delete_data = get_option( 'pcw_delete_data_on_uninstall', 'no' );

if ( 'yes' !== $delete_data ) {
	return;
}

global $wpdb;

// Remover todas as tabelas
$tables = array(
	$wpdb->prefix . 'pcw_cashback',
	$wpdb->prefix . 'pcw_cashback_rules',
	$wpdb->prefix . 'pcw_cashback_history',
	$wpdb->prefix . 'pcw_levels',
	$wpdb->prefix . 'pcw_level_requirements',
	$wpdb->prefix . 'pcw_level_discounts',
	$wpdb->prefix . 'pcw_user_levels',
	$wpdb->prefix . 'pcw_wallet',
	$wpdb->prefix . 'pcw_wallet_transactions',
	$wpdb->prefix . 'pcw_webhooks',
	$wpdb->prefix . 'pcw_webhook_logs',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Remover opções
$options = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'pcw_' ) . '%'
	)
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Remover transients
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_pcw_' ) . '%'
	)
);
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_timeout_pcw_' ) . '%'
	)
);

// Limpar cron jobs
$timestamp = wp_next_scheduled( 'pcw_daily_expiration_check' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'pcw_daily_expiration_check' );
}
