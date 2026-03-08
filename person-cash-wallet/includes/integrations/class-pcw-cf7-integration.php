<?php
/**
 * Integração com Contact Form 7
 *
 * @package PersonCashWallet
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de integração com Contact Form 7
 */
class PCW_CF7_Integration extends PCW_Forms_Integration {

	/**
	 * Nome do provedor
	 *
	 * @var string
	 */
	protected $provider = 'contact_form_7';

	/**
	 * Inicializar integração
	 */
	public function init() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Hook para submissões do CF7
		add_action( 'wpcf7_before_send_mail', array( $this, 'handle_submission' ), 10, 1 );
	}

	/**
	 * Processar submissão do CF7
	 *
	 * @param WPCF7_ContactForm $contact_form Objeto do formulário.
	 */
	public function handle_submission( $contact_form ) {
		$submission = WPCF7_Submission::get_instance();

		if ( ! $submission ) {
			return;
		}

		$posted_data = $submission->get_posted_data();

		// Mapear campos
		$data = array(
			'form_id'   => $contact_form->id(),
			'form_name' => $contact_form->title(),
		);

		// Buscar campos comuns
		foreach ( $posted_data as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}

			if ( stripos( $key, 'email' ) !== false || stripos( $key, 'e-mail' ) !== false ) {
				$data['email'] = $value;
			} elseif ( stripos( $key, 'nome' ) !== false || stripos( $key, 'name' ) !== false ) {
				$data['name'] = $value;
			} elseif ( stripos( $key, 'telefone' ) !== false || stripos( $key, 'phone' ) !== false || stripos( $key, 'tel' ) !== false ) {
				$data['phone'] = $value;
			}
		}

		// Processar submissão
		$this->process_submission( $data );
	}
}
