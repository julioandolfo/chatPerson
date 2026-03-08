<?php
/**
 * Classe admin para gerenciar níveis
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe admin levels
 */
class PCW_Admin_Levels {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_pcw_save_level', array( $this, 'handle_save_level' ) );
		add_action( 'admin_post_pcw_delete_level', array( $this, 'handle_delete_level' ) );
		add_action( 'admin_post_pcw_save_level_requirement', array( $this, 'handle_save_requirement' ) );
		add_action( 'admin_post_pcw_save_level_discount', array( $this, 'handle_save_discount' ) );
	}

	/**
	 * Adicionar menu
	 */
	public function add_menu() {
		add_submenu_page(
			'pcw-dashboard',
			__( 'Níveis', 'person-cash-wallet' ),
			__( 'Níveis', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-levels',
			array( $this, 'render_page' ),
			10
		);
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$level_id = isset( $_GET['level_id'] ) ? absint( $_GET['level_id'] ) : 0;

		if ( 'edit' === $action && $level_id ) {
			$this->render_edit_form( $level_id );
		} elseif ( 'new' === $action ) {
			$this->render_edit_form( 0 );
		} elseif ( 'requirements' === $action && $level_id ) {
			$this->render_requirements_page( $level_id );
		} elseif ( 'discounts' === $action && $level_id ) {
			$this->render_discounts_page( $level_id );
		} else {
			$this->render_list();
		}
	}

	/**
	 * Renderizar lista de níveis
	 */
	private function render_list() {
		$levels = PCW_Levels::get_all_levels();

		?>
		<div class="wrap">
			<!-- Page Header -->
			<div class="pcw-page-header">
				<div>
					<h1>
						<span class="dashicons dashicons-star-filled"></span>
						<?php esc_html_e( 'Níveis', 'person-cash-wallet' ); ?>
					</h1>
					<p class="description"><?php esc_html_e( 'Configure níveis VIP com requisitos e descontos exclusivos', 'person-cash-wallet' ); ?></p>
				</div>
				<div class="pcw-header-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-levels&action=new' ) ); ?>" class="button pcw-button-primary pcw-button-icon">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Novo Nível', 'person-cash-wallet' ); ?>
					</a>
				</div>
			</div>

			<?php if ( empty( $levels ) ) : ?>
				<div class="pcw-card">
					<div class="pcw-empty-state">
						<span class="dashicons dashicons-star-filled"></span>
						<h3><?php esc_html_e( 'Nenhum nível criado ainda', 'person-cash-wallet' ); ?></h3>
						<p><?php esc_html_e( 'Crie níveis VIP para premiar seus melhores clientes com benefícios exclusivos.', 'person-cash-wallet' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-levels&action=new' ) ); ?>" class="button pcw-button-primary">
							<?php esc_html_e( 'Criar Primeiro Nível', 'person-cash-wallet' ); ?>
						</a>
					</div>
				</div>
			<?php else : ?>
				<!-- Levels Grid -->
				<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
					<?php foreach ( $levels as $level ) : ?>
						<div class="pcw-card" style="border-top: 4px solid <?php echo esc_attr( $level->color ); ?>;">
							<div class="pcw-card-header" style="border-bottom: 1px solid <?php echo esc_attr( $level->color ); ?>33;">
								<div style="display: flex; align-items: center; gap: 12px;">
									<div style="width: 40px; height: 40px; background: <?php echo esc_attr( $level->color ); ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 18px;">
										<?php echo esc_html( $level->level_number ); ?>
									</div>
									<div style="flex: 1;">
										<h2 style="margin: 0; font-size: 18px;"><?php echo esc_html( $level->name ); ?></h2>
										<?php if ( ! empty( $level->description ) ) : ?>
											<p style="margin: 4px 0 0; color: #646970; font-size: 13px;"><?php echo esc_html( wp_trim_words( $level->description, 10 ) ); ?></p>
										<?php endif; ?>
									</div>
								</div>
								<?php if ( 'active' === $level->status ) : ?>
									<span class="pcw-badge pcw-badge-success">
										<span class="dashicons dashicons-yes"></span>
										<?php esc_html_e( 'Ativo', 'person-cash-wallet' ); ?>
									</span>
								<?php else : ?>
									<span class="pcw-badge pcw-badge-danger">
										<span class="dashicons dashicons-no-alt"></span>
										<?php esc_html_e( 'Inativo', 'person-cash-wallet' ); ?>
									</span>
								<?php endif; ?>
							</div>
							<div class="pcw-card-body">
								<div style="display: flex; flex-direction: column; gap: 8px;">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-levels&action=edit&level_id=' . $level->id ) ); ?>" class="button button-secondary pcw-button-icon" style="width: 100%;">
										<span class="dashicons dashicons-edit"></span>
										<?php esc_html_e( 'Editar Nível', 'person-cash-wallet' ); ?>
									</a>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-levels&action=requirements&level_id=' . $level->id ) ); ?>" class="button pcw-button-icon" style="width: 100%;">
										<span class="dashicons dashicons-visibility"></span>
										<?php esc_html_e( 'Requisitos', 'person-cash-wallet' ); ?>
									</a>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-levels&action=discounts&level_id=' . $level->id ) ); ?>" class="button pcw-button-icon" style="width: 100%;">
										<span class="dashicons dashicons-tag"></span>
										<?php esc_html_e( 'Descontos', 'person-cash-wallet' ); ?>
									</a>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renderizar formulário de edição
	 *
	 * @param int $level_id ID do nível (0 para novo).
	 */
	private function render_edit_form( $level_id ) {
		$level = null;
		if ( $level_id ) {
			$level = PCW_Levels::get_level( $level_id );
			if ( ! $level ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Nível não encontrado.', 'person-cash-wallet' ) . '</p></div>';
				$this->render_list();
				return;
			}
		}

		$defaults = array(
			'name'         => '',
			'slug'         => '',
			'level_number' => 1,
			'badge_image'  => '',
			'color'        => '#000000',
			'description'  => '',
			'status'       => 'active',
		);

		if ( $level ) {
			$defaults = (array) $level;
		}

		?>
		<div class="wrap">
			<!-- Page Header -->
			<div class="pcw-page-header">
				<div>
					<h1>
						<span class="dashicons dashicons-star-filled"></span>
						<?php echo $level_id ? esc_html__( 'Editar Nível', 'person-cash-wallet' ) : esc_html__( 'Novo Nível', 'person-cash-wallet' ); ?>
					</h1>
					<p class="description">
						<?php echo $level_id ? esc_html__( 'Modifique as configurações deste nível VIP', 'person-cash-wallet' ) : esc_html__( 'Crie um novo nível VIP com benefícios exclusivos para seus clientes', 'person-cash-wallet' ); ?>
					</p>
				</div>
				<div class="pcw-header-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-levels' ) ); ?>" class="button">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php esc_html_e( 'Voltar', 'person-cash-wallet' ); ?>
					</a>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'pcw_save_level', 'pcw_nonce' ); ?>
				<input type="hidden" name="action" value="pcw_save_level">
				<?php if ( $level_id ) : ?>
					<input type="hidden" name="level_id" value="<?php echo esc_attr( $level_id ); ?>">
				<?php endif; ?>

				<!-- Informações do Nível -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-admin-generic"></span>
							<?php esc_html_e( 'Informações do Nível', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<div style="padding: 16px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px; margin-bottom: 20px;">
							<strong><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'Dica:', 'person-cash-wallet' ); ?></strong>
							<?php esc_html_e( 'Defina nome, cor e descrição para identificar este nível. Após salvar, configure os requisitos e descontos específicos.', 'person-cash-wallet' ); ?>
						</div>
						<table class="form-table">
							<tr>
								<th><label for="name"><?php esc_html_e( 'Nome do Nível', 'person-cash-wallet' ); ?> <span class="required">*</span></label></th>
								<td>
									<input type="text" id="name" name="name" value="<?php echo esc_attr( $defaults['name'] ); ?>" class="pcw-form-input" style="width: 100%; max-width: 400px;" placeholder="<?php esc_attr_e( 'Ex: Bronze, Prata, Ouro, Diamante', 'person-cash-wallet' ); ?>" required>
									<p class="description">
										<span class="dashicons dashicons-info"></span>
										<?php esc_html_e( 'Nome que será exibido ao cliente (Ex: Bronze, Prata, Ouro, Platina, Diamante)', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th><label for="level_number"><?php esc_html_e( 'Número do Nível', 'person-cash-wallet' ); ?> <span class="required">*</span></label></th>
								<td>
									<input type="number" id="level_number" name="level_number" value="<?php echo esc_attr( $defaults['level_number'] ); ?>" min="1" class="pcw-form-input" style="width: 150px;" required>
									<p class="description">
										<span class="dashicons dashicons-sort"></span>
										<?php esc_html_e( 'Ordem crescente (1 = básico, 2, 3, 4, 5 = premium). Níveis maiores têm mais benefícios.', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th><label for="color"><?php esc_html_e( 'Cor do Nível', 'person-cash-wallet' ); ?></label></th>
								<td>
									<div style="display: flex; align-items: center; gap: 12px;">
										<input type="color" id="color" name="color" value="<?php echo esc_attr( $defaults['color'] ); ?>" style="width: 80px; height: 40px; border-radius: 4px; border: 1px solid #dcdcde; cursor: pointer;">
										<div style="flex: 1;">
											<p class="description" style="margin: 0;">
												<span class="dashicons dashicons-art"></span>
												<?php esc_html_e( 'Cor usada para identificar visualmente este nível no sistema e para o cliente', 'person-cash-wallet' ); ?>
											</p>
										</div>
									</div>
								</td>
							</tr>
							<tr>
								<th><label for="description"><?php esc_html_e( 'Descrição', 'person-cash-wallet' ); ?></label></th>
								<td>
									<textarea id="description" name="description" rows="3" class="pcw-form-input" style="width: 100%; max-width: 600px;" placeholder="<?php esc_attr_e( 'Descreva os benefícios deste nível...', 'person-cash-wallet' ); ?>"><?php echo esc_textarea( $defaults['description'] ); ?></textarea>
									<p class="description">
										<span class="dashicons dashicons-editor-alignleft"></span>
										<?php esc_html_e( 'Descrição opcional que será exibida ao cliente (Ex: "Clientes Ouro recebem 10% de desconto em todos os produtos")', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th><label for="status"><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></label></th>
								<td>
									<select id="status" name="status" class="pcw-form-input">
										<option value="active" <?php selected( $defaults['status'], 'active' ); ?>>✓ <?php esc_html_e( 'Ativo', 'person-cash-wallet' ); ?></option>
										<option value="inactive" <?php selected( $defaults['status'], 'inactive' ); ?>>✗ <?php esc_html_e( 'Inativo', 'person-cash-wallet' ); ?></option>
									</select>
									<p class="description">
										<span class="dashicons dashicons-info"></span>
										<?php esc_html_e( 'Apenas níveis ativos podem ser atribuídos a clientes', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<?php if ( $level_id ) : ?>
					<!-- Próximos Passos -->
					<div class="pcw-card">
						<div class="pcw-card-header">
							<h2>
								<span class="dashicons dashicons-admin-tools"></span>
								<?php esc_html_e( 'Configurações Adicionais', 'person-cash-wallet' ); ?>
							</h2>
						</div>
						<div class="pcw-card-body">
							<p><?php esc_html_e( 'Após salvar as informações básicas, configure:', 'person-cash-wallet' ); ?></p>
							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-top: 16px;">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-levels&action=requirements&level_id=' . $level_id ) ); ?>" class="button button-secondary pcw-button-icon" style="height: auto; padding: 16px; text-align: left; display: flex; align-items: flex-start; gap: 12px;">
									<span class="dashicons dashicons-visibility" style="font-size: 24px; color: #667eea;"></span>
									<div>
										<strong><?php esc_html_e( 'Requisitos', 'person-cash-wallet' ); ?></strong>
										<br>
										<small style="color: #646970;"><?php esc_html_e( 'Condições para alcançar este nível', 'person-cash-wallet' ); ?></small>
									</div>
								</a>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-levels&action=discounts&level_id=' . $level_id ) ); ?>" class="button button-secondary pcw-button-icon" style="height: auto; padding: 16px; text-align: left; display: flex; align-items: flex-start; gap: 12px;">
									<span class="dashicons dashicons-tag" style="font-size: 24px; color: #f5576c;"></span>
									<div>
										<strong><?php esc_html_e( 'Descontos', 'person-cash-wallet' ); ?></strong>
										<br>
										<small style="color: #646970;"><?php esc_html_e( 'Benefícios exclusivos deste nível', 'person-cash-wallet' ); ?></small>
									</div>
								</a>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<!-- Botões de Ação -->
				<div style="display: flex; gap: 12px; margin-bottom: 20px;">
					<button type="submit" class="button pcw-button-primary pcw-button-icon">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e( 'Salvar Nível', 'person-cash-wallet' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-levels' ) ); ?>" class="button pcw-button-icon">
						<span class="dashicons dashicons-no-alt"></span>
						<?php esc_html_e( 'Cancelar', 'person-cash-wallet' ); ?>
					</a>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Renderizar página de requisitos
	 *
	 * @param int $level_id ID do nível.
	 */
	private function render_requirements_page( $level_id ) {
		$level = PCW_Levels::get_level( $level_id );
		if ( ! $level ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Nível não encontrado.', 'person-cash-wallet' ) . '</p></div>';
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_level_requirements';
		$requirements = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE level_id = %d",
				$level_id
			)
		);

		?>
		<div class="wrap">
			<!-- Page Header -->
			<div class="pcw-page-header">
				<div>
					<h1>
						<span class="dashicons dashicons-visibility"></span>
						<?php echo esc_html( sprintf( __( 'Requisitos: %s', 'person-cash-wallet' ), $level->name ) ); ?>
					</h1>
					<p class="description"><?php esc_html_e( 'Configure as condições que um cliente precisa atingir para alcançar este nível', 'person-cash-wallet' ); ?></p>
				</div>
				<div class="pcw-header-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-levels&action=edit&level_id=' . $level_id ) ); ?>" class="button">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php esc_html_e( 'Voltar ao Nível', 'person-cash-wallet' ); ?>
					</a>
				</div>
			</div>

			<!-- Nível Info Badge -->
			<div style="display: inline-flex; align-items: center; gap: 12px; padding: 12px 20px; background: <?php echo esc_attr( $level->color ); ?>22; border-left: 4px solid <?php echo esc_attr( $level->color ); ?>; border-radius: 4px; margin-bottom: 20px;">
				<div style="width: 40px; height: 40px; background: <?php echo esc_attr( $level->color ); ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 18px;">
					<?php echo esc_html( $level->level_number ); ?>
				</div>
				<div>
					<div style="font-weight: 600; font-size: 16px;"><?php echo esc_html( $level->name ); ?></div>
					<div style="font-size: 13px; color: #646970;"><?php esc_html_e( 'Nível', 'person-cash-wallet' ); ?> <?php echo esc_html( $level->level_number ); ?></div>
				</div>
			</div>

			<!-- Adicionar Requisito -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Adicionar Requisito', 'person-cash-wallet' ); ?>
					</h2>
				</div>
				<div class="pcw-card-body">
					<div style="padding: 16px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px; margin-bottom: 20px;">
						<strong><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'Como funciona:', 'person-cash-wallet' ); ?></strong>
						<?php esc_html_e( 'Defina uma ou mais condições. O cliente precisará atender TODAS as condições para alcançar este nível. Exemplos: "Gastar R$ 5.000" OU "Fazer 10 pedidos" OU ambos.', 'person-cash-wallet' ); ?>
					</div>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'pcw_save_requirement', 'pcw_nonce' ); ?>
						<input type="hidden" name="action" value="pcw_save_level_requirement">
						<input type="hidden" name="level_id" value="<?php echo esc_attr( $level_id ); ?>">

						<table class="form-table">
							<tr>
								<th><label for="requirement_type"><?php esc_html_e( 'Tipo de Requisito', 'person-cash-wallet' ); ?> <span class="required">*</span></label></th>
								<td>
									<select id="requirement_type" name="requirement_type" class="pcw-form-input" style="width: 100%; max-width: 400px;" required>
										<option value="total_spent">💰 <?php esc_html_e( 'Total Gasto (R$)', 'person-cash-wallet' ); ?></option>
										<option value="order_count">📦 <?php esc_html_e( 'Quantidade de Pedidos', 'person-cash-wallet' ); ?></option>
										<option value="item_count">🛒 <?php esc_html_e( 'Quantidade de Itens Comprados', 'person-cash-wallet' ); ?></option>
									</select>
									<p class="description">
										<span class="dashicons dashicons-info"></span>
										<strong><?php esc_html_e( 'Total Gasto:', 'person-cash-wallet' ); ?></strong> <?php esc_html_e( 'Ex: Cliente precisa ter gasto R$ 5.000', 'person-cash-wallet' ); ?><br>
										<strong><?php esc_html_e( 'Pedidos:', 'person-cash-wallet' ); ?></strong> <?php esc_html_e( 'Ex: Cliente precisa ter feito 10 pedidos', 'person-cash-wallet' ); ?><br>
										<strong><?php esc_html_e( 'Itens:', 'person-cash-wallet' ); ?></strong> <?php esc_html_e( 'Ex: Cliente precisa ter comprado 50 produtos', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th><label for="requirement_value"><?php esc_html_e( 'Valor do Requisito', 'person-cash-wallet' ); ?> <span class="required">*</span></label></th>
								<td>
									<input type="number" id="requirement_value" name="requirement_value" step="0.01" min="0" class="pcw-form-input" style="width: 200px;" placeholder="Ex: 5000" required>
									<p class="description">
										<span class="dashicons dashicons-calculator"></span>
										<?php esc_html_e( 'Quantidade ou valor necessário (números apenas). Ex: 5000 para R$ 5.000 ou 10 para 10 pedidos', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th><label for="period_type"><?php esc_html_e( 'Período de Avaliação', 'person-cash-wallet' ); ?></label></th>
								<td>
									<select id="period_type" name="period_type" class="pcw-form-input" style="width: 100%; max-width: 400px;">
										<option value="lifetime">♾️ <?php esc_html_e( 'Desde Sempre (Total histórico)', 'person-cash-wallet' ); ?></option>
										<option value="last_30_days">📅 <?php esc_html_e( 'Últimos 30 Dias', 'person-cash-wallet' ); ?></option>
										<option value="last_90_days">📅 <?php esc_html_e( 'Últimos 90 Dias', 'person-cash-wallet' ); ?></option>
										<option value="last_year">📅 <?php esc_html_e( 'Último Ano (365 dias)', 'person-cash-wallet' ); ?></option>
									</select>
									<p class="description">
										<span class="dashicons dashicons-clock"></span>
										<strong><?php esc_html_e( 'Desde Sempre:', 'person-cash-wallet' ); ?></strong> <?php esc_html_e( 'Conta todo o histórico do cliente', 'person-cash-wallet' ); ?><br>
										<strong><?php esc_html_e( 'Período:', 'person-cash-wallet' ); ?></strong> <?php esc_html_e( 'Renova automaticamente (precisa manter o requisito no período)', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
						</table>

						<button type="submit" class="button pcw-button-primary pcw-button-icon">
							<span class="dashicons dashicons-saved"></span>
							<?php esc_html_e( 'Adicionar Requisito', 'person-cash-wallet' ); ?>
						</button>
					</form>
				</div>
			</div>

			<!-- Requisitos Existentes -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-list-view"></span>
						<?php esc_html_e( 'Requisitos Configurados', 'person-cash-wallet' ); ?>
					</h2>
					<span class="pcw-badge pcw-badge-info"><?php echo esc_html( count( $requirements ) ); ?> <?php esc_html_e( 'requisitos', 'person-cash-wallet' ); ?></span>
				</div>
				
				<?php if ( empty( $requirements ) ) : ?>
					<div class="pcw-empty-state">
						<span class="dashicons dashicons-visibility"></span>
						<h3><?php esc_html_e( 'Nenhum requisito configurado', 'person-cash-wallet' ); ?></h3>
						<p><?php esc_html_e( 'Adicione pelo menos um requisito para que os clientes possam alcançar este nível.', 'person-cash-wallet' ); ?></p>
					</div>
				<?php else : ?>
					<div style="padding: 16px; background: #fff3cd; border-left: 4px solid #dba617; border-radius: 4px; margin: 20px;">
						<strong><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Importante:', 'person-cash-wallet' ); ?></strong>
						<?php esc_html_e( 'O cliente precisa atender TODOS os requisitos abaixo para alcançar este nível.', 'person-cash-wallet' ); ?>
					</div>
					<div class="pcw-table-wrapper">
						<table class="pcw-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Tipo de Requisito', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Valor Necessário', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Período', 'person-cash-wallet' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php 
								$type_labels = array(
									'total_spent' => __( 'Total Gasto', 'person-cash-wallet' ),
									'order_count' => __( 'Quantidade de Pedidos', 'person-cash-wallet' ),
									'item_count'  => __( 'Quantidade de Itens', 'person-cash-wallet' ),
								);
								$period_labels = array(
									'lifetime'      => __( 'Desde Sempre', 'person-cash-wallet' ),
									'last_30_days'  => __( 'Últimos 30 Dias', 'person-cash-wallet' ),
									'last_90_days'  => __( 'Últimos 90 Dias', 'person-cash-wallet' ),
									'last_year'     => __( 'Último Ano', 'person-cash-wallet' ),
								);
								?>
								<?php foreach ( $requirements as $req ) : ?>
									<tr>
										<td>
											<span class="pcw-badge pcw-badge-info">
												<?php 
												if ( 'total_spent' === $req->requirement_type ) echo '💰';
												elseif ( 'order_count' === $req->requirement_type ) echo '📦';
												else echo '🛒';
												?>
												<?php echo esc_html( isset( $type_labels[ $req->requirement_type ] ) ? $type_labels[ $req->requirement_type ] : ucfirst( str_replace( '_', ' ', $req->requirement_type ) ) ); ?>
											</span>
										</td>
										<td>
											<strong>
												<?php 
												if ( 'total_spent' === $req->requirement_type ) {
													echo wp_kses_post( PCW_Formatters::format_money( $req->requirement_value ) );
												} else {
													echo esc_html( number_format_i18n( $req->requirement_value ) );
													echo ' ' . esc_html( 'order_count' === $req->requirement_type ? __( 'pedidos', 'person-cash-wallet' ) : __( 'itens', 'person-cash-wallet' ) );
												}
												?>
											</strong>
										</td>
										<td>
											<span class="pcw-badge pcw-badge-default">
												<?php echo esc_html( isset( $period_labels[ $req->period_type ] ) ? $period_labels[ $req->period_type ] : ucfirst( str_replace( '_', ' ', $req->period_type ) ) ); ?>
											</span>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderizar página de descontos
	 *
	 * @param int $level_id ID do nível.
	 */
	private function render_discounts_page( $level_id ) {
		$level = PCW_Levels::get_level( $level_id );
		if ( ! $level ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Nível não encontrado.', 'person-cash-wallet' ) . '</p></div>';
			return;
		}

		$discounts = PCW_Level_Discounts::get_level_discounts( $level_id );

		?>
		<div class="wrap">
			<!-- Page Header -->
			<div class="pcw-page-header">
				<div>
					<h1>
						<span class="dashicons dashicons-tag"></span>
						<?php echo esc_html( sprintf( __( 'Descontos: %s', 'person-cash-wallet' ), $level->name ) ); ?>
					</h1>
					<p class="description"><?php esc_html_e( 'Configure os descontos e benefícios exclusivos que este nível oferece', 'person-cash-wallet' ); ?></p>
				</div>
				<div class="pcw-header-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-levels&action=edit&level_id=' . $level_id ) ); ?>" class="button">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php esc_html_e( 'Voltar ao Nível', 'person-cash-wallet' ); ?>
					</a>
				</div>
			</div>

			<!-- Nível Info Badge -->
			<div style="display: inline-flex; align-items: center; gap: 12px; padding: 12px 20px; background: <?php echo esc_attr( $level->color ); ?>22; border-left: 4px solid <?php echo esc_attr( $level->color ); ?>; border-radius: 4px; margin-bottom: 20px;">
				<div style="width: 40px; height: 40px; background: <?php echo esc_attr( $level->color ); ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 18px;">
					<?php echo esc_html( $level->level_number ); ?>
				</div>
				<div>
					<div style="font-weight: 600; font-size: 16px;"><?php echo esc_html( $level->name ); ?></div>
					<div style="font-size: 13px; color: #646970;"><?php esc_html_e( 'Nível', 'person-cash-wallet' ); ?> <?php echo esc_html( $level->level_number ); ?></div>
				</div>
			</div>

			<!-- Adicionar Desconto -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Adicionar Desconto', 'person-cash-wallet' ); ?>
					</h2>
				</div>
				<div class="pcw-card-body">
					<div style="padding: 16px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px; margin-bottom: 20px;">
						<strong><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'Benefícios Exclusivos:', 'person-cash-wallet' ); ?></strong>
						<?php esc_html_e( 'Configure descontos automáticos que serão aplicados em todas as compras de clientes deste nível. Você pode adicionar múltiplos descontos com diferentes condições.', 'person-cash-wallet' ); ?>
					</div>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'pcw_save_discount', 'pcw_nonce' ); ?>
						<input type="hidden" name="action" value="pcw_save_level_discount">
						<input type="hidden" name="level_id" value="<?php echo esc_attr( $level_id ); ?>">

						<table class="form-table">
							<tr>
								<th><label for="discount_type"><?php esc_html_e( 'Tipo de Desconto', 'person-cash-wallet' ); ?> <span class="required">*</span></label></th>
								<td>
									<select id="discount_type" name="discount_type" class="pcw-form-input" style="width: 100%; max-width: 400px;" required>
										<option value="percentage">📊 <?php esc_html_e( 'Percentual (% do pedido)', 'person-cash-wallet' ); ?></option>
										<option value="fixed">💰 <?php esc_html_e( 'Valor Fixo (R$)', 'person-cash-wallet' ); ?></option>
										<option value="free_shipping">🚚 <?php esc_html_e( 'Frete Grátis', 'person-cash-wallet' ); ?></option>
									</select>
									<p class="description">
										<span class="dashicons dashicons-info"></span>
										<strong><?php esc_html_e( 'Percentual:', 'person-cash-wallet' ); ?></strong> <?php esc_html_e( 'Ex: 10% de desconto em todo pedido', 'person-cash-wallet' ); ?><br>
										<strong><?php esc_html_e( 'Fixo:', 'person-cash-wallet' ); ?></strong> <?php esc_html_e( 'Ex: R$ 50,00 de desconto em todo pedido', 'person-cash-wallet' ); ?><br>
										<strong><?php esc_html_e( 'Frete Grátis:', 'person-cash-wallet' ); ?></strong> <?php esc_html_e( 'Cliente não paga frete em nenhum pedido', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr id="discount_value_row">
								<th><label for="discount_value"><?php esc_html_e( 'Valor do Desconto', 'person-cash-wallet' ); ?> <span class="required">*</span></label></th>
								<td>
									<div style="display: flex; align-items: center; gap: 10px;">
										<input type="number" id="discount_value" name="discount_value" step="0.01" min="0" class="pcw-form-input" style="width: 150px;" placeholder="Ex: 10" required>
										<strong id="value-suffix" style="font-size: 16px;">%</strong>
									</div>
									<p class="description">
										<span class="dashicons dashicons-calculator"></span>
										<?php esc_html_e( 'Valor do desconto (% ou R$ dependendo do tipo)', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th><label for="min_order_amount"><?php esc_html_e( 'Valor Mínimo do Pedido', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="number" id="min_order_amount" name="min_order_amount" step="0.01" min="0" value="0" class="pcw-form-input" style="width: 150px;" placeholder="0,00">
									<p class="description">
										<span class="dashicons dashicons-cart"></span>
										<?php esc_html_e( 'Desconto só aplica se pedido for maior que este valor. Deixe 0 para aplicar sempre.', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
							<tr id="max_discount_row">
								<th><label for="max_discount_amount"><?php esc_html_e( 'Desconto Máximo', 'person-cash-wallet' ); ?></label></th>
								<td>
									<input type="number" id="max_discount_amount" name="max_discount_amount" step="0.01" min="0" value="0" class="pcw-form-input" style="width: 150px;" placeholder="0,00">
									<p class="description">
										<span class="dashicons dashicons-warning"></span>
										<?php esc_html_e( 'Limite máximo do desconto em reais. Deixe 0 para sem limite. Ex: 10% de R$ 5.000 = R$ 500, mas se limite for R$ 200, desconto será R$ 200', 'person-cash-wallet' ); ?>
									</p>
								</td>
							</tr>
						</table>

						<button type="submit" class="button pcw-button-primary pcw-button-icon">
							<span class="dashicons dashicons-saved"></span>
							<?php esc_html_e( 'Adicionar Desconto', 'person-cash-wallet' ); ?>
						</button>
					</form>

					<script>
					jQuery(document).ready(function($) {
						$('#discount_type').on('change', function() {
							var type = $(this).val();
							if (type === 'percentage') {
								$('#value-suffix').text('%');
								$('#max_discount_row').show();
								$('#discount_value_row').show();
								$('#discount_value').prop('required', true);
							} else if (type === 'fixed') {
								$('#value-suffix').text('R$');
								$('#max_discount_row').hide();
								$('#discount_value_row').show();
								$('#discount_value').prop('required', true);
							} else {
								$('#discount_value_row').hide();
								$('#max_discount_row').hide();
								$('#discount_value').prop('required', false);
							}
						});
					});
					</script>
				</div>
			</div>

			<!-- Descontos Existentes -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-list-view"></span>
						<?php esc_html_e( 'Descontos Configurados', 'person-cash-wallet' ); ?>
					</h2>
					<span class="pcw-badge pcw-badge-info"><?php echo esc_html( count( $discounts ) ); ?> <?php esc_html_e( 'descontos', 'person-cash-wallet' ); ?></span>
				</div>
				
				<?php if ( empty( $discounts ) ) : ?>
					<div class="pcw-empty-state">
						<span class="dashicons dashicons-tag"></span>
						<h3><?php esc_html_e( 'Nenhum desconto configurado', 'person-cash-wallet' ); ?></h3>
						<p><?php esc_html_e( 'Adicione descontos exclusivos para recompensar os clientes deste nível.', 'person-cash-wallet' ); ?></p>
					</div>
				<?php else : ?>
					<div class="pcw-table-wrapper">
						<table class="pcw-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Tipo de Desconto', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Valor', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Pedido Mínimo', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Desconto Máximo', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $discounts as $discount ) : ?>
									<tr>
										<td>
											<?php if ( 'percentage' === $discount->discount_type ) : ?>
												<span class="pcw-badge pcw-badge-info">📊 <?php esc_html_e( 'Percentual', 'person-cash-wallet' ); ?></span>
											<?php elseif ( 'fixed' === $discount->discount_type ) : ?>
												<span class="pcw-badge pcw-badge-success">💰 <?php esc_html_e( 'Fixo', 'person-cash-wallet' ); ?></span>
											<?php else : ?>
												<span class="pcw-badge pcw-badge-warning">🚚 <?php esc_html_e( 'Frete Grátis', 'person-cash-wallet' ); ?></span>
											<?php endif; ?>
										</td>
										<td>
											<strong>
												<?php
												if ( 'percentage' === $discount->discount_type ) {
													echo esc_html( number_format( $discount->discount_value, 2, ',', '.' ) . '%' );
												} elseif ( 'fixed' === $discount->discount_type ) {
													echo wp_kses_post( PCW_Formatters::format_money( $discount->discount_value ) );
												} else {
													echo '<span style="color: #00a32a;">' . esc_html__( 'Frete Grátis', 'person-cash-wallet' ) . '</span>';
												}
												?>
											</strong>
										</td>
										<td>
											<?php 
											if ( $discount->min_order_amount > 0 ) {
												echo wp_kses_post( PCW_Formatters::format_money( $discount->min_order_amount ) );
											} else {
												echo '<em style="color: #646970;">' . esc_html__( 'Sempre', 'person-cash-wallet' ) . '</em>';
											}
											?>
										</td>
										<td>
											<?php 
											if ( 'percentage' === $discount->discount_type && $discount->max_discount_amount > 0 ) {
												echo wp_kses_post( PCW_Formatters::format_money( $discount->max_discount_amount ) );
											} else {
												echo '<em style="color: #646970;">-</em>';
											}
											?>
										</td>
										<td>
											<?php if ( 'active' === $discount->status ) : ?>
												<span class="pcw-badge pcw-badge-success">
													<span class="dashicons dashicons-yes"></span>
													<?php esc_html_e( 'Ativo', 'person-cash-wallet' ); ?>
												</span>
											<?php else : ?>
												<span class="pcw-badge pcw-badge-danger">
													<span class="dashicons dashicons-no-alt"></span>
													<?php esc_html_e( 'Inativo', 'person-cash-wallet' ); ?>
												</span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Processar salvamento de nível
	 */
	public function handle_save_level() {
		if ( ! isset( $_POST['pcw_nonce'] ) || ! wp_verify_nonce( $_POST['pcw_nonce'], 'pcw_save_level' ) ) {
			wp_die( esc_html__( 'Ação não autorizada.', 'person-cash-wallet' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'person-cash-wallet' ) );
		}

		$level_id = isset( $_POST['level_id'] ) ? absint( $_POST['level_id'] ) : 0;

		$data = array(
			'name'         => sanitize_text_field( $_POST['name'] ),
			'level_number' => absint( $_POST['level_number'] ),
			'color'        => sanitize_hex_color( $_POST['color'] ),
			'description'  => sanitize_textarea_field( $_POST['description'] ),
			'status'       => sanitize_text_field( $_POST['status'] ),
		);

		if ( $level_id ) {
			PCW_Levels::update( $level_id, $data );
		} else {
			PCW_Levels::create( $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=pcw-levels&message=saved' ) );
		exit;
	}

	/**
	 * Processar salvamento de requisito
	 */
	public function handle_save_requirement() {
		if ( ! isset( $_POST['pcw_nonce'] ) || ! wp_verify_nonce( $_POST['pcw_nonce'], 'pcw_save_requirement' ) ) {
			wp_die( esc_html__( 'Ação não autorizada.', 'person-cash-wallet' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'person-cash-wallet' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_level_requirements';

		$data = array(
			'level_id'         => absint( $_POST['level_id'] ),
			'requirement_type' => sanitize_text_field( $_POST['requirement_type'] ),
			'requirement_value' => floatval( $_POST['requirement_value'] ),
			'period_type'      => sanitize_text_field( $_POST['period_type'] ),
			'created_at'       => current_time( 'mysql' ),
		);

		$wpdb->insert( $table, $data );

		wp_safe_redirect( admin_url( 'admin.php?page=pcw-levels&action=requirements&level_id=' . absint( $_POST['level_id'] ) ) );
		exit;
	}

	/**
	 * Processar salvamento de desconto
	 */
	public function handle_save_discount() {
		if ( ! isset( $_POST['pcw_nonce'] ) || ! wp_verify_nonce( $_POST['pcw_nonce'], 'pcw_save_discount' ) ) {
			wp_die( esc_html__( 'Ação não autorizada.', 'person-cash-wallet' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'person-cash-wallet' ) );
		}

		$data = array(
			'level_id'          => absint( $_POST['level_id'] ),
			'discount_type'     => sanitize_text_field( $_POST['discount_type'] ),
			'discount_value'    => floatval( $_POST['discount_value'] ),
			'min_order_amount'  => floatval( $_POST['min_order_amount'] ),
			'max_discount_amount' => floatval( $_POST['max_discount_amount'] ),
		);

		PCW_Level_Discounts::create( $data );

		wp_safe_redirect( admin_url( 'admin.php?page=pcw-levels&action=discounts&level_id=' . absint( $_POST['level_id'] ) ) );
		exit;
	}

	/**
	 * Processar exclusão de nível
	 */
	public function handle_delete_level() {
		// Implementar se necessário
	}
}
