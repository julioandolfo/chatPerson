<?php
/**
 * Rastreador de Campanhas (opens, clicks)
 *
 * @package GrowlyDigital
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de rastreamento de campanhas
 */
class PCW_Campaign_Tracker {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Campaign_Tracker
	 */
	private static $instance = null;

	/**
	 * Obter instância
	 *
	 * @return PCW_Campaign_Tracker
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
		add_action( 'init', array( $this, 'handle_tracking_request' ) );
	}

	/**
	 * Processar requisição de tracking
	 */
	public function handle_tracking_request() {
		if ( ! isset( $_GET['pcw_track'] ) ) {
			return;
		}

		$event_type = sanitize_text_field( $_GET['pcw_track'] );
		$campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
		$send_id = isset( $_GET['send_id'] ) ? absint( $_GET['send_id'] ) : 0;

		if ( ! $campaign_id || ! $send_id ) {
			return;
		}

		$campaigns = PCW_Campaigns::instance();

		switch ( $event_type ) {
			case 'open':
				$campaigns->track_event( $campaign_id, $send_id, 'open' );
				
				// Retornar pixel transparente 1x1
				header( 'Content-Type: image/gif' );
				header( 'Cache-Control: no-cache, no-store, must-revalidate' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );
				echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
				exit;

			case 'click':
				$url = isset( $_GET['url'] ) ? urldecode( $_GET['url'] ) : home_url();
				$campaigns->track_event( $campaign_id, $send_id, 'click', $url );
				
				// Redirecionar para URL original
				wp_redirect( $url );
				exit;
		}
	}
}
