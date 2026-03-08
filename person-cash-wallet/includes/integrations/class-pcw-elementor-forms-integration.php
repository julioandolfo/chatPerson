<?php
/**
 * Integração com Elementor Pro Forms
 *
 * @package PersonCashWallet
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de integração com Elementor Forms
 */
class PCW_Elementor_Forms_Integration extends PCW_Forms_Integration {

	/**
	 * Nome do provedor
	 *
	 * @var string
	 */
	protected $provider = 'elementor';

	/**
	 * Inicializar integração
	 */
	public function init() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Hook para submissões de formulários Elementor
		add_action( 'elementor_pro/forms/new_record', array( $this, 'handle_submission' ), 10, 2 );
	}

	/**
	 * Processar submissão do Elementor
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record Registro do formulário.
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler Handler AJAX.
	 */
	public function handle_submission( $record, $ajax_handler ) {
		$raw_fields = $record->get( 'fields' );
		$form_name = $record->get_form_settings( 'form_name' );

		// Mapear campos
		$data = array(
			'form_id'   => $record->get_form_settings( 'id' ),
			'form_name' => $form_name,
		);

		// Buscar campos de email e nome
		foreach ( $raw_fields as $field_id => $field ) {
			$field_type = $field['type'];
			$field_value = $field['value'];

			if ( 'email' === $field_type ) {
				$data['email'] = $field_value;
			} elseif ( 'text' === $field_type ) {
				if ( stripos( $field['title'], 'nome' ) !== false || stripos( $field['title'], 'name' ) !== false ) {
					$data['name'] = $field_value;
				}
			} elseif ( 'tel' === $field_type ) {
				$data['phone'] = $field_value;
			}
		}

		// Processar submissão
		$result = $this->process_submission( $data );

		if ( is_wp_error( $result ) ) {
			$ajax_handler->add_error_message( $result->get_error_message() );
		}
	}
}
