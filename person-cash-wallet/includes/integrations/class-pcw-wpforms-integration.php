<?php
/**
 * Integração com WPForms
 *
 * @package PersonCashWallet
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de integração com WPForms
 */
class PCW_WPForms_Integration extends PCW_Forms_Integration {

	/**
	 * Nome do provedor
	 *
	 * @var string
	 */
	protected $provider = 'wpforms';

	/**
	 * Inicializar integração
	 */
	public function init() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Hook para submissões do WPForms
		add_action( 'wpforms_process_complete', array( $this, 'handle_submission' ), 10, 4 );
	}

	/**
	 * Processar submissão do WPForms
	 *
	 * @param array $fields Campos do formulário.
	 * @param array $entry Entrada do formulário.
	 * @param array $form_data Dados do formulário.
	 * @param int   $entry_id ID da entrada.
	 */
	public function handle_submission( $fields, $entry, $form_data, $entry_id ) {
		$data = array(
			'form_id'   => $form_data['id'],
			'form_name' => $form_data['settings']['form_title'],
		);

		// Mapear campos
		foreach ( $fields as $field_id => $field ) {
			$field_type = $field['type'];
			$field_value = $field['value'];

			if ( 'email' === $field_type ) {
				$data['email'] = $field_value;
			} elseif ( 'name' === $field_type ) {
				if ( is_array( $field_value ) ) {
					$data['name'] = trim( ( $field_value['first'] ?? '' ) . ' ' . ( $field_value['last'] ?? '' ) );
				} else {
					$data['name'] = $field_value;
				}
			} elseif ( 'phone' === $field_type ) {
				$data['phone'] = $field_value;
			} elseif ( 'text' === $field_type ) {
				if ( stripos( $field['name'], 'nome' ) !== false || stripos( $field['name'], 'name' ) !== false ) {
					$data['name'] = $field_value;
				}
			}
		}

		// Processar submissão
		$this->process_submission( $data );
	}
}
