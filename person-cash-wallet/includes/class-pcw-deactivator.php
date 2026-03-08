<?php
/**
 * Classe de desativação do plugin
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de desativação
 */
class PCW_Deactivator {

	/**
	 * Desativar plugin
	 */
	public static function deactivate() {
		// Limpar cron jobs
		self::clear_cron_jobs();

		// Limpar transients
		self::clear_transients();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Limpar cron jobs
	 */
	private static function clear_cron_jobs() {
		$timestamp = wp_next_scheduled( 'pcw_daily_expiration_check' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'pcw_daily_expiration_check' );
		}
	}

	/**
	 * Limpar transients
	 */
	private static function clear_transients() {
		global $wpdb;
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
	}
}
