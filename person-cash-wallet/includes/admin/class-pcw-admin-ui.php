<?php
/**
 * Helper UI para componentes padronizados do admin
 *
 * @package GrowlyDigital
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe PCW_Admin_UI
 */
class PCW_Admin_UI {

	/**
	 * Renderizar cabeçalho de página
	 *
	 * @param string $title       Título da página.
	 * @param string $description Descrição opcional.
	 * @param array  $actions     Botões de ação (array de arrays com 'label', 'url', 'class').
	 * @param string $icon        Classe do dashicon (sem 'dashicons-').
	 */
	public static function render_page_header( $title, $description = '', $actions = array(), $icon = 'admin-generic' ) {
		?>
		<div class="pcw-page-header">
			<div class="pcw-page-header-content">
				<div>
					<h1>
						<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
						<?php echo esc_html( $title ); ?>
					</h1>
					<?php if ( $description ) : ?>
						<p class="description"><?php echo esc_html( $description ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $actions ) ) : ?>
					<div class="pcw-header-actions">
						<?php foreach ( $actions as $action ) : ?>
							<a href="<?php echo esc_url( $action['url'] ); ?>" 
							   class="button <?php echo esc_attr( $action['class'] ?? '' ); ?>">
								<?php if ( isset( $action['icon'] ) ) : ?>
									<span class="dashicons dashicons-<?php echo esc_attr( $action['icon'] ); ?>"></span>
								<?php endif; ?>
								<?php echo esc_html( $action['label'] ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Iniciar card
	 *
	 * @param string $title Título do card.
	 * @param string $icon  Classe do dashicon (sem 'dashicons-').
	 */
	public static function card_start( $title = '', $icon = '' ) {
		?>
		<div class="pcw-card">
			<?php if ( $title ) : ?>
				<div class="pcw-card-header">
					<h2>
						<?php if ( $icon ) : ?>
							<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
						<?php endif; ?>
						<?php echo esc_html( $title ); ?>
					</h2>
				</div>
			<?php endif; ?>
			<div class="pcw-card-body">
		<?php
	}

	/**
	 * Finalizar card
	 *
	 * @param string $footer_content Conteúdo HTML do footer (opcional).
	 */
	public static function card_end( $footer_content = '' ) {
		?>
			</div>
			<?php if ( $footer_content ) : ?>
				<div class="pcw-card-footer">
					<?php echo $footer_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renderizar tabs
	 *
	 * @param array  $tabs        Array de tabs ['id' => ['label' => '', 'icon' => '']].
	 * @param string $active_tab  ID da tab ativa.
	 */
	public static function render_tabs( $tabs, $active_tab = '' ) {
		if ( empty( $active_tab ) ) {
			$active_tab = array_key_first( $tabs );
		}
		?>
		<div class="pcw-tabs">
			<div class="pcw-tabs-nav">
				<?php foreach ( $tabs as $tab_id => $tab ) : ?>
					<a href="#<?php echo esc_attr( $tab_id ); ?>" 
					   class="pcw-tab-link <?php echo $tab_id === $active_tab ? 'active' : ''; ?>"
					   data-tab="<?php echo esc_attr( $tab_id ); ?>">
						<?php if ( isset( $tab['icon'] ) ) : ?>
							<span class="dashicons dashicons-<?php echo esc_attr( $tab['icon'] ); ?>"></span>
						<?php endif; ?>
						<?php echo esc_html( $tab['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Abrir formulário com classes padronizadas
	 *
	 * @param string $action     URL de ação.
	 * @param string $method     Método (POST ou GET).
	 * @param array  $attributes Atributos adicionais.
	 */
	public static function form_start( $action, $method = 'POST', $attributes = array() ) {
		$default_attrs = array(
			'method' => $method,
			'action' => $action,
			'class'  => 'pcw-form',
		);

		$attrs = array_merge( $default_attrs, $attributes );

		echo '<form';
		foreach ( $attrs as $key => $value ) {
			echo ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
		echo '>';

		if ( 'POST' === strtoupper( $method ) ) {
			wp_nonce_field( 'pcw_form_action', 'pcw_form_nonce' );
		}
	}

	/**
	 * Fechar formulário
	 */
	public static function form_end() {
		echo '</form>';
	}

	/**
	 * Renderizar input de formulário
	 *
	 * @param array $args Argumentos do campo.
	 */
	public static function render_input( $args ) {
		$defaults = array(
			'type'        => 'text',
			'name'        => '',
			'id'          => '',
			'value'       => '',
			'label'       => '',
			'help'        => '',
			'required'    => false,
			'placeholder' => '',
			'class'       => 'pcw-form-input',
			'icon'        => '',
		);

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['id'] ) ) {
			$args['id'] = $args['name'];
		}
		?>
		<div class="pcw-form-group">
			<?php if ( $args['label'] ) : ?>
				<label for="<?php echo esc_attr( $args['id'] ); ?>" class="pcw-form-label">
					<?php if ( $args['icon'] ) : ?>
						<span class="dashicons dashicons-<?php echo esc_attr( $args['icon'] ); ?>"></span>
					<?php endif; ?>
					<?php echo esc_html( $args['label'] ); ?>
					<?php if ( $args['required'] ) : ?>
						<span class="required">*</span>
					<?php endif; ?>
				</label>
			<?php endif; ?>
			
			<?php if ( $args['icon'] ) : ?>
				<div class="pcw-input-group">
					<span class="dashicons dashicons-<?php echo esc_attr( $args['icon'] ); ?>"></span>
			<?php endif; ?>
			
			<?php if ( 'textarea' === $args['type'] ) : ?>
				<textarea 
					name="<?php echo esc_attr( $args['name'] ); ?>"
					id="<?php echo esc_attr( $args['id'] ); ?>"
					class="<?php echo esc_attr( $args['class'] ); ?>"
					placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
					<?php echo $args['required'] ? 'required' : ''; ?>
				><?php echo esc_textarea( $args['value'] ); ?></textarea>
			<?php elseif ( 'select' === $args['type'] ) : ?>
				<select 
					name="<?php echo esc_attr( $args['name'] ); ?>"
					id="<?php echo esc_attr( $args['id'] ); ?>"
					class="pcw-form-select <?php echo esc_attr( $args['class'] ); ?>"
					<?php echo $args['required'] ? 'required' : ''; ?>
				>
					<?php if ( isset( $args['options'] ) ) : ?>
						<?php foreach ( $args['options'] as $option_value => $option_label ) : ?>
							<option value="<?php echo esc_attr( $option_value ); ?>" 
								<?php selected( $args['value'], $option_value ); ?>>
								<?php echo esc_html( $option_label ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			<?php else : ?>
				<input 
					type="<?php echo esc_attr( $args['type'] ); ?>"
					name="<?php echo esc_attr( $args['name'] ); ?>"
					id="<?php echo esc_attr( $args['id'] ); ?>"
					value="<?php echo esc_attr( $args['value'] ); ?>"
					class="<?php echo esc_attr( $args['class'] ); ?>"
					placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
					<?php echo $args['required'] ? 'required' : ''; ?>
				/>
			<?php endif; ?>
			
			<?php if ( $args['icon'] ) : ?>
				</div>
			<?php endif; ?>
			
			<?php if ( $args['help'] ) : ?>
				<span class="pcw-form-help"><?php echo esc_html( $args['help'] ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renderizar tabela
	 *
	 * @param array $columns Colunas ['key' => 'Label'].
	 * @param array $data    Dados da tabela.
	 * @param array $actions Ações por linha (callback).
	 */
	public static function render_table( $columns, $data, $actions = null ) {
		?>
		<div class="pcw-table-wrapper">
			<table class="pcw-table">
				<thead>
					<tr>
						<?php foreach ( $columns as $key => $label ) : ?>
							<th><?php echo esc_html( $label ); ?></th>
						<?php endforeach; ?>
						<?php if ( $actions ) : ?>
							<th><?php esc_html_e( 'Ações', 'person-cash-wallet' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $data ) ) : ?>
						<tr>
							<td colspan="<?php echo count( $columns ) + ( $actions ? 1 : 0 ); ?>">
								<div class="pcw-empty-state">
									<span class="dashicons dashicons-inbox"></span>
									<h3><?php esc_html_e( 'Nenhum registro encontrado', 'person-cash-wallet' ); ?></h3>
									<p><?php esc_html_e( 'Ainda não há dados para exibir aqui.', 'person-cash-wallet' ); ?></p>
								</div>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $data as $row ) : ?>
							<tr>
								<?php foreach ( $columns as $key => $label ) : ?>
									<td><?php echo isset( $row[ $key ] ) ? wp_kses_post( $row[ $key ] ) : '—'; ?></td>
								<?php endforeach; ?>
								<?php if ( $actions ) : ?>
									<td class="pcw-table-actions">
										<?php echo call_user_func( $actions, $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Renderizar badge
	 *
	 * @param string $label Texto do badge.
	 * @param string $type  Tipo (success, warning, danger, info, default).
	 */
	public static function badge( $label, $type = 'default' ) {
		return sprintf(
			'<span class="pcw-badge pcw-badge-%s">%s</span>',
			esc_attr( $type ),
			esc_html( $label )
		);
	}

	/**
	 * Renderizar info box
	 *
	 * @param string $message Mensagem.
	 * @param string $type    Tipo (success, warning, danger, info).
	 * @param string $icon    Classe do dashicon (sem 'dashicons-').
	 */
	public static function info_box( $message, $type = 'info', $icon = 'info' ) {
		?>
		<div class="pcw-info-box pcw-info-box-<?php echo esc_attr( $type ); ?>">
			<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
			<div><?php echo wp_kses_post( $message ); ?></div>
		</div>
		<?php
	}

	/**
	 * Renderizar toggle switch
	 *
	 * @param string  $name    Nome do campo.
	 * @param boolean $checked Estado atual.
	 * @param string  $label   Label ao lado.
	 */
	public static function toggle_switch( $name, $checked = false, $label = '' ) {
		?>
		<label class="pcw-toggle">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?>>
			<span class="pcw-toggle-slider"></span>
		</label>
		<?php if ( $label ) : ?>
			<span style="margin-left: 12px;"><?php echo esc_html( $label ); ?></span>
		<?php endif; ?>
		<?php
	}
}
