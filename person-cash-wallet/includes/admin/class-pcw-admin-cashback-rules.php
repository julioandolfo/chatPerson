<?php
/**
 * Classe admin para gerenciar regras de cashback
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe admin cashback rules
 */
class PCW_Admin_Cashback_Rules {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_pcw_save_cashback_rule', array( $this, 'handle_save_rule' ) );
		add_action( 'admin_post_pcw_delete_cashback_rule', array( $this, 'handle_delete_rule' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Adicionar menu
	 */
	public function add_menu() {
		add_submenu_page(
			'pcw-dashboard',
			__( 'Regras de Cashback', 'person-cash-wallet' ),
			__( 'Cashback', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-cashback-rules',
			array( $this, 'render_page' ),
			5
		);
	}

	/**
	 * Enfileirar scripts
	 *
	 * @param string $hook Hook atual.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_pcw-cashback-rules' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$rule_id = isset( $_GET['rule_id'] ) ? absint( $_GET['rule_id'] ) : 0;

		if ( 'edit' === $action && $rule_id ) {
			$this->render_edit_form( $rule_id );
		} elseif ( 'new' === $action ) {
			$this->render_edit_form( 0 );
		} else {
			$this->render_list();
		}
	}

	/**
	 * Renderizar lista de regras
	 */
	private function render_list() {
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_cashback_rules';

		$rules = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY priority DESC, created_at DESC" );

		?>
		<div class="wrap pcw-admin-page">
			<?php
			// Page Header usando helper
			PCW_Admin_UI::render_page_header(
				__( 'Regras de Cashback', 'person-cash-wallet' ),
				__( 'Configure regras para gerar cashback automaticamente nos pedidos dos clientes', 'person-cash-wallet' ),
				array(
					array(
						'label' => __( 'Nova Regra', 'person-cash-wallet' ),
						'url'   => admin_url( 'admin.php?page=pcw-cashback-rules&action=new' ),
						'class' => '',
						'icon'  => 'plus-alt',
					),
				),
				'chart-line'
			);
			?>

			<?php if ( empty( $rules ) ) : ?>
				<div class="pcw-card">
					<div class="pcw-empty-state">
						<span class="dashicons dashicons-chart-line"></span>
						<h3><?php esc_html_e( 'Nenhuma regra criada ainda', 'person-cash-wallet' ); ?></h3>
						<p><?php esc_html_e( 'Crie sua primeira regra de cashback para começar a recompensar seus clientes.', 'person-cash-wallet' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-cashback-rules&action=new' ) ); ?>" class="button pcw-button-primary">
							<?php esc_html_e( 'Criar Primeira Regra', 'person-cash-wallet' ); ?>
						</a>
					</div>
				</div>
			<?php else : ?>
				<!-- Rules Card -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-list-view"></span>
							<?php esc_html_e( 'Todas as Regras', 'person-cash-wallet' ); ?>
						</h2>
						<span class="pcw-badge pcw-badge-default"><?php echo esc_html( count( $rules ) ); ?> <?php esc_html_e( 'regras', 'person-cash-wallet' ); ?></span>
					</div>
					<div class="pcw-table-wrapper">
						<table class="pcw-table">
							<thead>
								<tr>
									<th style="width: 50px;">#</th>
									<th><?php esc_html_e( 'Nome da Regra', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Tipo', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Valor', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Pedido Mín.', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Expiração', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Prioridade', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Ações', 'person-cash-wallet' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rules as $rule ) : ?>
									<tr>
										<td><strong>#<?php echo esc_html( $rule->id ); ?></strong></td>
										<td>
											<strong><?php echo esc_html( $rule->name ); ?></strong>
											<?php if ( $rule->max_cashback_amount > 0 ) : ?>
												<br><small style="color: #646970;"><?php echo esc_html( sprintf( __( 'Máx: %s', 'person-cash-wallet' ), PCW_Formatters::format_money_plain( $rule->max_cashback_amount ) ) ); ?></small>
											<?php endif; ?>
										</td>
										<td>
											<?php if ( 'percentage' === $rule->type ) : ?>
												<span class="pcw-badge pcw-badge-info">
													<span class="dashicons dashicons-chart-pie"></span>
													<?php esc_html_e( 'Percentual', 'person-cash-wallet' ); ?>
												</span>
											<?php else : ?>
												<span class="pcw-badge pcw-badge-default">
													<span class="dashicons dashicons-money-alt"></span>
													<?php esc_html_e( 'Fixo', 'person-cash-wallet' ); ?>
												</span>
											<?php endif; ?>
										</td>
										<td>
											<strong>
												<?php
												if ( 'percentage' === $rule->type ) {
													echo esc_html( number_format( $rule->value, 2, ',', '.' ) . '%' );
												} else {
													echo wp_kses_post( PCW_Formatters::format_money( $rule->value ) );
												}
												?>
											</strong>
										</td>
										<td><?php echo wp_kses_post( PCW_Formatters::format_money( $rule->min_order_amount ) ); ?></td>
										<td>
											<?php
											if ( $rule->expiration_days > 0 ) {
												echo '<span class="pcw-badge pcw-badge-warning">' . esc_html( sprintf( __( '%d dias', 'person-cash-wallet' ), $rule->expiration_days ) ) . '</span>';
											} else {
												echo '<span class="pcw-badge pcw-badge-success">' . esc_html__( 'Sem expiração', 'person-cash-wallet' ) . '</span>';
											}
											?>
										</td>
										<td>
											<span class="pcw-badge pcw-badge-default"><?php echo esc_html( $rule->priority ); ?></span>
										</td>
										<td>
											<?php if ( 'active' === $rule->status ) : ?>
												<span class="pcw-badge pcw-badge-success">
													<span class="dashicons dashicons-yes"></span>
													<?php esc_html_e( 'Ativa', 'person-cash-wallet' ); ?>
												</span>
											<?php else : ?>
												<span class="pcw-badge pcw-badge-danger">
													<span class="dashicons dashicons-no-alt"></span>
													<?php esc_html_e( 'Inativa', 'person-cash-wallet' ); ?>
												</span>
											<?php endif; ?>
										</td>
										<td>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-cashback-rules&action=edit&rule_id=' . $rule->id ) ); ?>" class="button button-small pcw-button-icon">
												<span class="dashicons dashicons-edit"></span>
												<?php esc_html_e( 'Editar', 'person-cash-wallet' ); ?>
											</a>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
												<?php wp_nonce_field( 'pcw_delete_rule', 'pcw_nonce' ); ?>
												<input type="hidden" name="action" value="pcw_delete_cashback_rule">
												<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule->id ); ?>">
												<button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Tem certeza que deseja excluir esta regra?', 'person-cash-wallet' ) ); ?>');">
													<span class="dashicons dashicons-trash"></span>
													<?php esc_html_e( 'Excluir', 'person-cash-wallet' ); ?>
												</button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renderizar formulário de edição
	 *
	 * @param int $rule_id ID da regra (0 para nova).
	 */
	private function render_edit_form( $rule_id ) {
		$rule = null;
		if ( $rule_id ) {
			$rule = PCW_Cashback_Rules::get_rule( $rule_id );
			if ( ! $rule ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Regra não encontrada.', 'person-cash-wallet' ) . '</p></div>';
				$this->render_list();
				return;
			}
		}

		$defaults = array(
			'name'                => '',
			'description'         => '',
			'status'              => 'active',
			'type'                => 'percentage',
			'value'               => 0,
			'min_order_amount'    => 0,
			'max_cashback_amount' => 0,
			'expiration_days'     => 90,
			'expiration_type'     => 'days',
			'priority'            => 10,
			'product_categories'  => array(),
			'excluded_products'   => array(),
			'user_roles'          => array(),
		);

		if ( $rule ) {
			$defaults['name']                = $rule->name;
			$defaults['description']         = $rule->description;
			$defaults['status']              = $rule->status;
			$defaults['type']                = $rule->type;
			$defaults['value']               = $rule->value;
			$defaults['min_order_amount']    = $rule->min_order_amount;
			$defaults['max_cashback_amount'] = $rule->max_cashback_amount;
			$defaults['expiration_days']    = $rule->expiration_days;
			$defaults['expiration_type']     = $rule->expiration_type;
			$defaults['priority']            = $rule->priority;

			if ( ! empty( $rule->product_categories ) ) {
				$defaults['product_categories'] = json_decode( $rule->product_categories, true );
			}
			if ( ! empty( $rule->excluded_products ) ) {
				$defaults['excluded_products'] = json_decode( $rule->excluded_products, true );
			}
			if ( ! empty( $rule->user_roles ) ) {
				$defaults['user_roles'] = json_decode( $rule->user_roles, true );
			}
		}

		?>
		<div class="wrap">
			<!-- Page Header -->
			<div class="pcw-page-header">
				<div>
					<h1>
						<span class="dashicons dashicons-chart-line"></span>
						<?php echo $rule_id ? esc_html__( 'Editar Regra de Cashback', 'person-cash-wallet' ) : esc_html__( 'Nova Regra de Cashback', 'person-cash-wallet' ); ?>
					</h1>
					<p class="description">
						<?php echo $rule_id ? esc_html__( 'Modifique as configurações da regra de cashback', 'person-cash-wallet' ) : esc_html__( 'Configure uma nova regra para gerar cashback automaticamente', 'person-cash-wallet' ); ?>
					</p>
				</div>
				<div class="pcw-header-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-cashback-rules' ) ); ?>" class="button">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php esc_html_e( 'Voltar', 'person-cash-wallet' ); ?>
					</a>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'pcw_save_rule', 'pcw_nonce' ); ?>
				<input type="hidden" name="action" value="pcw_save_cashback_rule">
				<?php if ( $rule_id ) : ?>
					<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule_id ); ?>">
				<?php endif; ?>

				<!-- Informações Básicas -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-admin-generic"></span>
							<?php esc_html_e( 'Informações Básicas', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<table class="form-table">
					<tr>
						<th><label for="name"><?php esc_html_e( 'Nome da Regra', 'person-cash-wallet' ); ?></label></th>
						<td>
							<input type="text" id="name" name="name" value="<?php echo esc_attr( $defaults['name'] ); ?>" class="regular-text" required>
							<p class="description"><?php esc_html_e( 'Nome interno para identificar esta regra.', 'person-cash-wallet' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="description"><?php esc_html_e( 'Descrição', 'person-cash-wallet' ); ?></label></th>
						<td>
							<textarea id="description" name="description" rows="3" class="large-text"><?php echo esc_textarea( $defaults['description'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th><label for="status"><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></label></th>
						<td>
							<select id="status" name="status" class="pcw-form-input">
								<option value="active" <?php selected( $defaults['status'], 'active' ); ?>>✓ <?php esc_html_e( 'Ativa', 'person-cash-wallet' ); ?></option>
								<option value="inactive" <?php selected( $defaults['status'], 'inactive' ); ?>>✗ <?php esc_html_e( 'Inativa', 'person-cash-wallet' ); ?></option>
							</select>
							<p class="description">
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'Apenas regras ativas geram cashback automaticamente', 'person-cash-wallet' ); ?>
							</p>
						</td>
					</tr>
						</table>
					</div>
				</div>

				<!-- Configurações de Valor -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-money-alt"></span>
							<?php esc_html_e( 'Configurações de Valor', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<div style="padding: 16px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px; margin-bottom: 20px;">
							<strong><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'Dica:', 'person-cash-wallet' ); ?></strong>
							<?php esc_html_e( 'Configure quanto de cashback será gerado para cada compra. Pode ser um percentual do valor total ou um valor fixo em reais.', 'person-cash-wallet' ); ?>
						</div>
						<table class="form-table">
					<tr>
						<th><label for="type"><?php esc_html_e( 'Tipo de Cashback', 'person-cash-wallet' ); ?></label></th>
						<td>
							<select id="type" name="type" class="pcw-form-input">
								<option value="percentage" <?php selected( $defaults['type'], 'percentage' ); ?>>📊 <?php esc_html_e( 'Percentual (% do valor)', 'person-cash-wallet' ); ?></option>
								<option value="fixed" <?php selected( $defaults['type'], 'fixed' ); ?>>💰 <?php esc_html_e( 'Valor Fixo (R$)', 'person-cash-wallet' ); ?></option>
							</select>
							<p class="description">
								<strong><?php esc_html_e( 'Percentual:', 'person-cash-wallet' ); ?></strong> <?php esc_html_e( 'Ex: 5% de um pedido de R$ 100,00 = R$ 5,00 de cashback', 'person-cash-wallet' ); ?><br>
								<strong><?php esc_html_e( 'Fixo:', 'person-cash-wallet' ); ?></strong> <?php esc_html_e( 'Ex: R$ 10,00 de cashback independente do valor do pedido', 'person-cash-wallet' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="value"><?php esc_html_e( 'Valor', 'person-cash-wallet' ); ?> <span class="required">*</span></label></th>
						<td>
							<div style="display: flex; align-items: center; gap: 10px;">
								<input type="number" id="value" name="value" value="<?php echo esc_attr( $defaults['value'] ); ?>" step="0.01" min="0" class="pcw-form-input" style="width: 150px;" required>
								<strong id="value-suffix" style="font-size: 16px;"><?php echo 'percentage' === $defaults['type'] ? '%' : 'R$'; ?></strong>
							</div>
							<p class="description">
								<?php esc_html_e( 'Quanto de cashback será gerado', 'person-cash-wallet' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="min_order_amount"><?php esc_html_e( 'Valor Mínimo do Pedido', 'person-cash-wallet' ); ?></label></th>
						<td>
							<input type="number" id="min_order_amount" name="min_order_amount" value="<?php echo esc_attr( $defaults['min_order_amount'] ); ?>" step="0.01" min="0" class="pcw-form-input" style="width: 150px;" placeholder="0,00">
							<p class="description">
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'Pedidos abaixo deste valor não geram cashback. Deixe em 0 para não aplicar limite.', 'person-cash-wallet' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="max_cashback_amount"><?php esc_html_e( 'Cashback Máximo por Pedido', 'person-cash-wallet' ); ?></label></th>
						<td>
							<input type="number" id="max_cashback_amount" name="max_cashback_amount" value="<?php echo esc_attr( $defaults['max_cashback_amount'] ); ?>" step="0.01" min="0" class="pcw-form-input" style="width: 150px;" placeholder="0,00">
							<p class="description">
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'Limite máximo de cashback por pedido. Deixe em 0 para não aplicar limite. Ex: 5% de R$ 10.000 = R$ 500, mas se limite for R$ 100, gerará apenas R$ 100', 'person-cash-wallet' ); ?>
							</p>
						</td>
					</tr>
						</table>
					</div>
				</div>

				<!-- Expiração e Prioridade -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-calendar-alt"></span>
							<?php esc_html_e( 'Expiração e Prioridade', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<table class="form-table">
					<tr>
						<th><label for="expiration_days"><?php esc_html_e( 'Dias para Expirar', 'person-cash-wallet' ); ?></label></th>
						<td>
							<input type="number" id="expiration_days" name="expiration_days" value="<?php echo esc_attr( $defaults['expiration_days'] ); ?>" min="0" class="pcw-form-input" style="width: 150px;" placeholder="90">
							<p class="description">
								<span class="dashicons dashicons-clock"></span>
								<?php esc_html_e( 'Número de dias até o cashback expirar. Deixe em 0 para nunca expirar. Recomendado: 90 dias', 'person-cash-wallet' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="priority"><?php esc_html_e( 'Prioridade', 'person-cash-wallet' ); ?></label></th>
						<td>
							<input type="number" id="priority" name="priority" value="<?php echo esc_attr( $defaults['priority'] ); ?>" min="0" class="pcw-form-input" style="width: 150px;" placeholder="10">
							<p class="description">
								<span class="dashicons dashicons-sort"></span>
								<?php esc_html_e( 'Regras com maior prioridade são avaliadas primeiro. Se um pedido atender múltiplas regras, será usada a de maior prioridade. Padrão: 10', 'person-cash-wallet' ); ?>
							</p>
						</td>
					</tr>
						</table>
					</div>
				</div>

				<!-- Botões de Ação -->
				<div style="display: flex; gap: 12px; margin-bottom: 20px;">
					<button type="submit" class="button pcw-button-primary pcw-button-icon">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e( 'Salvar Regra', 'person-cash-wallet' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-cashback-rules' ) ); ?>" class="button pcw-button-icon">
						<span class="dashicons dashicons-no-alt"></span>
						<?php esc_html_e( 'Cancelar', 'person-cash-wallet' ); ?>
					</a>
				</div>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#type').on('change', function() {
				var type = $(this).val();
				if (type === 'percentage') {
					$('#value-suffix').text('%');
				} else {
					$('#value-suffix').text('R$');
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Processar salvamento de regra
	 */
	public function handle_save_rule() {
		// Verificar nonce
		if ( ! isset( $_POST['pcw_nonce'] ) || ! wp_verify_nonce( $_POST['pcw_nonce'], 'pcw_save_rule' ) ) {
			wp_die( esc_html__( 'Ação não autorizada.', 'person-cash-wallet' ) );
		}

		// Verificar capability
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'person-cash-wallet' ) );
		}

		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;

		$data = array(
			'name'                => sanitize_text_field( $_POST['name'] ),
			'description'         => sanitize_textarea_field( $_POST['description'] ),
			'status'              => sanitize_text_field( $_POST['status'] ),
			'type'                => sanitize_text_field( $_POST['type'] ),
			'value'               => floatval( $_POST['value'] ),
			'min_order_amount'    => floatval( $_POST['min_order_amount'] ),
			'max_cashback_amount' => floatval( $_POST['max_cashback_amount'] ),
			'expiration_days'     => absint( $_POST['expiration_days'] ),
			'expiration_type'     => 'days',
			'priority'            => absint( $_POST['priority'] ),
		);

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_cashback_rules';

		if ( $rule_id ) {
			// Atualizar
			$data['updated_at'] = current_time( 'mysql' );
			$wpdb->update(
				$table,
				$data,
				array( 'id' => $rule_id ),
				array( '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%d', '%s', '%d', '%s' ),
				array( '%d' )
			);
		} else {
			// Criar
			$data['created_at'] = current_time( 'mysql' );
			$data['updated_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=pcw-cashback-rules&message=saved' ) );
		exit;
	}

	/**
	 * Processar exclusão de regra
	 */
	public function handle_delete_rule() {
		// Verificar nonce
		if ( ! isset( $_POST['pcw_nonce'] ) || ! wp_verify_nonce( $_POST['pcw_nonce'], 'pcw_delete_rule' ) ) {
			wp_die( esc_html__( 'Ação não autorizada.', 'person-cash-wallet' ) );
		}

		// Verificar capability
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'person-cash-wallet' ) );
		}

		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;

		if ( ! $rule_id ) {
			wp_die( esc_html__( 'ID da regra inválido.', 'person-cash-wallet' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_cashback_rules';

		$wpdb->delete( $table, array( 'id' => $rule_id ), array( '%d' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=pcw-cashback-rules&message=deleted' ) );
		exit;
	}
}
