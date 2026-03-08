<?php
/**
 * Integração com Gravity Forms
 *
 * @package PersonCashWallet
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de integração com Gravity Forms
 */
class PCW_Gravity_Forms_Integration extends PCW_Forms_Integration {

	/**
	 * Nome do provedor
	 *
	 * @var string
	 */
	protected $provider = 'gravity_forms';

	/**
	 * Inicializar integração
	 */
	public function init() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Hook para submissões do Gravity Forms
		add_action( 'gform_after_submission', array( $this, 'handle_submission' ), 10, 2 );
	}

	/**
	 * Processar submissão do Gravity Forms
	 *
	 * @param array $entry Entrada do formulário.
	 * @param array $form Formulário.
	 */
	public function handle_submission( $entry, $form ) {
		$data = array(
			'form_id'   => $form['id'],
			'form_name' => $form['title'],
		);

		// Mapear campos
		foreach ( $form['fields'] as $field ) {
			$field_id = $field->id;
			$field_type = $field->type;
			$field_value = isset( $entry[ $field_id ] ) ? $entry[ $field_id ] : '';

			if ( 'email' === $field_type ) {
				$data['email'] = $field_value;
			} elseif ( 'name' === $field_type ) {
				$data['name'] = $field_value;
			} elseif ( 'phone' === $field_type ) {
				$data['phone'] = $field_value;
			} elseif ( 'text' === $field_type ) {
				if ( stripos( $field->label, 'nome' ) !== false || stripos( $field->label, 'name' ) !== false ) {
					$data['name'] = $field_value;
				}
			}
		}

		// Processar submissão
		$this->process_submission( $data );
	}
}
