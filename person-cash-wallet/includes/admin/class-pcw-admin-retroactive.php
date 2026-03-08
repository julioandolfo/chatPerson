<?php
/**
 * Página Admin - Cashback Retroativo
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Classe para gerenciar a página de cashback retroativo
 */
class PCW_Admin_Retroactive {

	/**
	 * Inicializar
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// AJAX endpoints.
		add_action( 'wp_ajax_pcw_retroactive_preview', array( __CLASS__, 'ajax_preview' ) );
		add_action( 'wp_ajax_pcw_retroactive_process', array( __CLASS__, 'ajax_process' ) );
		add_action( 'wp_ajax_pcw_retroactive_revert', array( __CLASS__, 'ajax_revert' ) );
		add_action( 'wp_ajax_pcw_retroactive_cancel', array( __CLASS__, 'ajax_cancel' ) );
		add_action( 'wp_ajax_pcw_search_customers', array( __CLASS__, 'ajax_search_customers' ) );
		add_action( 'wp_ajax_pcw_search_products', array( __CLASS__, 'ajax_search_products' ) );
	}

	/**
	 * Adicionar menu
	 */
	public static function add_menu() {
		// Menu oculto - acessível via Cashback > Retroativo
		add_submenu_page(
			null, // Oculto do menu
			__( 'Cashback Retroativo', 'person-cash-wallet' ),
			__( 'Cashback Retroativo', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-retroactive',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enfileirar assets
	 *
	 * @param string $hook Hook.
	 */
	public static function enqueue_assets( $hook ) {
		// Verificar se estamos na página correta (suporta vários formatos de hook)
		if ( strpos( $hook, 'pcw-retroactive' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'pcw-admin-retroactive',
			PCW_PLUGIN_URL . 'assets/css/admin-retroactive.css',
			array( 'pcw-admin-global' ),
			PCW_VERSION
		);

		wp_enqueue_script(
			'pcw-admin-retroactive',
			PCW_PLUGIN_URL . 'assets/js/admin-retroactive.js',
			array( 'jquery', 'wp-util' ),
			PCW_VERSION,
			true
		);

		wp_localize_script(
			'pcw-admin-retroactive',
			'pcwRetroactive',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pcw_retroactive' ),
				'i18n'    => array(
					'confirm_process' => __( 'Tem certeza que deseja processar este lote? Esta ação não pode ser desfeita automaticamente.', 'person-cash-wallet' ),
					'confirm_revert'  => __( 'Tem certeza que deseja reverter este lote? Apenas cashbacks não utilizados serão revertidos.', 'person-cash-wallet' ),
					'confirm_cancel'  => __( 'Tem certeza que deseja cancelar este lote? Todos os cashbacks já gerados serão revertidos.', 'person-cash-wallet' ),
					'processing'      => __( 'Processando...', 'person-cash-wallet' ),
					'error'           => __( 'Erro ao processar. Tente novamente.', 'person-cash-wallet' ),
				),
			)
		);

		// Select2.
		wp_enqueue_style( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0' );
		wp_enqueue_script( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0', true );
	}

	/**
	 * Renderizar página
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'person-cash-wallet' ) );
		}

		// Buscar regras de cashback para o select.
		$rules = PCW_Cashback_Rules::get_all_rules();

		// Buscar status disponíveis (incluindo custom do Order Status Manager).
		$order_statuses = wc_get_order_statuses();

		?>
		<div class="wrap pcw-wrap">
			<h1 class="wp-heading-inline">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Cashback Retroativo', 'person-cash-wallet' ); ?>
			</h1>

			<p class="pcw-subtitle">
				<?php esc_html_e( 'Gere cashback para pedidos já finalizados. Configure os filtros, visualize o preview e processe em lotes.', 'person-cash-wallet' ); ?>
			</p>

			<div class="pcw-admin-retroactive">
				<!-- Card: Filtros -->
				<div class="pcw-card pcw-filters-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-filter"></span>
							<?php esc_html_e( 'Filtros', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<form id="pcw-retroactive-filters">
							<div class="pcw-form-row">
								<div class="pcw-form-group">
									<label for="date_from">
										<?php esc_html_e( 'Data Inicial', 'person-cash-wallet' ); ?>
									</label>
									<input type="date" id="date_from" name="date_from" class="pcw-input">
								</div>

								<div class="pcw-form-group">
									<label for="date_to">
										<?php esc_html_e( 'Data Final', 'person-cash-wallet' ); ?>
									</label>
									<input type="date" id="date_to" name="date_to" class="pcw-input">
								</div>
							</div>

							<div class="pcw-form-group">
								<label for="status">
									<?php esc_html_e( 'Status dos Pedidos', 'person-cash-wallet' ); ?>
								</label>
								<select id="status" name="status[]" class="pcw-select" multiple>
									<?php foreach ( $order_statuses as $status => $label ) : ?>
										<option value="<?php echo esc_attr( $status ); ?>" <?php selected( 'wc-completed', $status ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Selecione quais status de pedido considerar. Padrão: Concluído.', 'person-cash-wallet' ); ?>
								</p>
							</div>

							<div class="pcw-form-row">
								<div class="pcw-form-group">
									<label for="min_amount">
										<?php esc_html_e( 'Valor Mínimo do Pedido (R$)', 'person-cash-wallet' ); ?>
									</label>
									<input type="number" id="min_amount" name="min_amount" class="pcw-input" step="0.01" min="0" placeholder="0,00">
								</div>

								<div class="pcw-form-group">
									<label for="max_amount">
										<?php esc_html_e( 'Valor Máximo do Pedido (R$)', 'person-cash-wallet' ); ?>
									</label>
									<input type="number" id="max_amount" name="max_amount" class="pcw-input" step="0.01" min="0" placeholder="Ilimitado">
								</div>
							</div>

							<div class="pcw-form-group">
								<label for="customers">
									<?php esc_html_e( 'Clientes Específicos (Opcional)', 'person-cash-wallet' ); ?>
								</label>
								<select id="customers" name="customers[]" class="pcw-select-ajax" multiple>
								</select>
								<p class="description">
									<?php esc_html_e( 'Deixe em branco para todos os clientes. Comece a digitar para buscar.', 'person-cash-wallet' ); ?>
								</p>
							</div>

							<div class="pcw-form-group">
								<label for="products">
									<?php esc_html_e( 'Produtos Específicos (Opcional)', 'person-cash-wallet' ); ?>
								</label>
								<select id="products" name="products[]" class="pcw-select-ajax" multiple>
								</select>
								<p class="description">
									<?php esc_html_e( 'Deixe em branco para todos os produtos. Apenas pedidos que contenham estes produtos serão processados.', 'person-cash-wallet' ); ?>
								</p>
							</div>

							<div class="pcw-form-group">
								<label for="cashback_rule">
									<?php esc_html_e( 'Regra de Cashback', 'person-cash-wallet' ); ?>
								</label>
								<select id="cashback_rule" name="cashback_rule" class="pcw-select">
									<option value="current"><?php esc_html_e( 'Aplicar regra atual', 'person-cash-wallet' ); ?></option>
									<option value="specific"><?php esc_html_e( 'Regra específica', 'person-cash-wallet' ); ?></option>
									<option value="fixed"><?php esc_html_e( 'Valor fixo', 'person-cash-wallet' ); ?></option>
									<option value="percentage"><?php esc_html_e( 'Percentual fixo', 'person-cash-wallet' ); ?></option>
								</select>
							</div>

							<!-- Opções condicionais -->
							<div class="pcw-form-group pcw-conditional" data-show-when="cashback_rule" data-show-value="specific" style="display:none;">
								<label for="rule_id">
									<?php esc_html_e( 'Selecione a Regra', 'person-cash-wallet' ); ?>
								</label>
								<select id="rule_id" name="rule_id" class="pcw-select">
									<option value=""><?php esc_html_e( 'Selecione...', 'person-cash-wallet' ); ?></option>
									<?php foreach ( $rules as $rule ) : ?>
										<option value="<?php echo esc_attr( $rule->id ); ?>">
											<?php echo esc_html( $rule->name ); ?> 
											(<?php echo 'percentage' === $rule->cashback_type ? esc_html( $rule->cashback_value ) . '%' : wc_price( $rule->cashback_value ); ?>)
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="pcw-form-group pcw-conditional" data-show-when="cashback_rule" data-show-value="fixed" style="display:none;">
								<label for="fixed_amount">
									<?php esc_html_e( 'Valor Fixo (R$)', 'person-cash-wallet' ); ?>
								</label>
								<input type="number" id="fixed_amount" name="fixed_amount" class="pcw-input" step="0.01" min="0" placeholder="10,00">
							</div>

							<div class="pcw-form-group pcw-conditional" data-show-when="cashback_rule" data-show-value="percentage" style="display:none;">
								<label for="percentage_value">
									<?php esc_html_e( 'Percentual (%)', 'person-cash-wallet' ); ?>
								</label>
								<input type="number" id="percentage_value" name="percentage_value" class="pcw-input" step="0.01" min="0" max="100" placeholder="5">
							</div>

							<div class="pcw-form-group">
								<label class="pcw-checkbox-label">
									<input type="checkbox" id="ignore_existing" name="ignore_existing" value="1" checked>
									<?php esc_html_e( 'Ignorar pedidos que já têm cashback', 'person-cash-wallet' ); ?>
								</label>
							</div>

							<div class="pcw-form-group">
								<label class="pcw-checkbox-label">
									<input type="checkbox" id="send_email" name="send_email" value="1">
									<?php esc_html_e( 'Enviar email de notificação aos clientes', 'person-cash-wallet' ); ?>
								</label>
							</div>

							<div class="pcw-form-actions">
								<button type="button" id="pcw-search-orders" class="button button-primary">
									<span class="dashicons dashicons-search"></span>
									<?php esc_html_e( 'Buscar Pedidos', 'person-cash-wallet' ); ?>
								</button>
							</div>
						</form>
					</div>
				</div>

				<!-- Card: Preview -->
				<div class="pcw-card pcw-preview-card" id="pcw-preview-container" style="display:none;">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-visibility"></span>
							<?php esc_html_e( 'Preview', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<div id="pcw-preview-summary" class="pcw-summary-grid"></div>
						<div id="pcw-preview-table"></div>
						<div class="pcw-form-actions">
							<button type="button" id="pcw-process-cashback" class="button button-primary button-hero">
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Processar Cashback Retroativo', 'person-cash-wallet' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Card: Processando -->
				<div class="pcw-card pcw-processing-card" id="pcw-processing-container" style="display:none;">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-update spin"></span>
							<?php esc_html_e( 'Processando...', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<div class="pcw-progress-bar">
							<div class="pcw-progress-fill" id="pcw-progress-fill" style="width: 0%;"></div>
						</div>
						<div id="pcw-processing-info" class="pcw-processing-info"></div>
						<div id="pcw-processing-logs" class="pcw-logs-container"></div>
					</div>
				</div>

				<!-- Card: Resultado -->
				<div class="pcw-card pcw-result-card" id="pcw-result-container" style="display:none;">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Processamento Concluído', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<div id="pcw-result-summary"></div>
						<div class="pcw-form-actions">
							<button type="button" id="pcw-download-csv" class="button">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Baixar Relatório CSV', 'person-cash-wallet' ); ?>
							</button>
							<button type="button" id="pcw-new-process" class="button button-primary">
								<span class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Novo Processamento', 'person-cash-wallet' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Card: Histórico -->
				<div class="pcw-card pcw-history-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-backup"></span>
							<?php esc_html_e( 'Histórico de Processamentos', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<?php self::render_history(); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderizar histórico
	 */
	private static function render_history() {
		$batches = PCW_Retroactive_Batch::get_all( 20 );

		if ( empty( $batches ) ) {
			?>
			<div class="pcw-empty-state">
				<span class="dashicons dashicons-backup"></span>
				<h3><?php esc_html_e( 'Nenhum processamento encontrado', 'person-cash-wallet' ); ?></h3>
				<p><?php esc_html_e( 'O histórico de processamentos retroativos aparecerá aqui.', 'person-cash-wallet' ); ?></p>
			</div>
			<?php
			return;
		}

		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Data/Hora', 'person-cash-wallet' ); ?></th>
					<th><?php esc_html_e( 'Pedidos', 'person-cash-wallet' ); ?></th>
					<th><?php esc_html_e( 'Cashbacks', 'person-cash-wallet' ); ?></th>
					<th><?php esc_html_e( 'Valor Total', 'person-cash-wallet' ); ?></th>
					<th><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></th>
					<th><?php esc_html_e( 'Processado por', 'person-cash-wallet' ); ?></th>
					<th><?php esc_html_e( 'Ações', 'person-cash-wallet' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $batches as $batch ) : ?>
					<?php
					$user      = get_userdata( $batch->processed_by );
					$user_name = $user ? $user->display_name : __( 'Desconhecido', 'person-cash-wallet' );
					?>
					<tr>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $batch->created_at ) ); ?></td>
						<td><?php echo esc_html( $batch->total_orders ); ?></td>
						<td><?php echo esc_html( $batch->total_cashback ); ?></td>
						<td><?php echo wc_price( $batch->total_amount ); ?></td>
						<td>
							<span class="pcw-badge pcw-badge-<?php echo esc_attr( $batch->status ); ?>">
								<?php echo esc_html( ucfirst( $batch->status ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $user_name ); ?></td>
						<td>
							<?php if ( 'completed' === $batch->status ) : ?>
								<button type="button" class="button button-small pcw-revert-batch" data-batch-id="<?php echo esc_attr( $batch->batch_id ); ?>">
									<span class="dashicons dashicons-undo"></span>
									<?php esc_html_e( 'Reverter', 'person-cash-wallet' ); ?>
								</button>
							<?php elseif ( in_array( $batch->status, array( 'processing', 'pending' ), true ) ) : ?>
								<button type="button" class="button button-small pcw-cancel-batch" data-batch-id="<?php echo esc_attr( $batch->batch_id ); ?>" onclick="console.log('Button clicked', this.dataset.batchId)">
									<span class="dashicons dashicons-no-alt"></span>
									<?php esc_html_e( 'Cancelar', 'person-cash-wallet' ); ?>
								</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * AJAX: Preview
	 */
	public static function ajax_preview() {
		// Limpar qualquer output anterior
		if ( ob_get_level() ) {
			ob_clean();
		}

		check_ajax_referer( 'pcw_retroactive', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		$filters = self::sanitize_filters( $_POST );

		$order_ids = PCW_Retroactive_Processor::find_orders( $filters );
		$preview   = PCW_Retroactive_Processor::generate_preview( $order_ids, $filters, 50 );

		wp_send_json_success( $preview );
	}

	/**
	 * AJAX: Processar
	 */
	public static function ajax_process() {
		// Limpar qualquer output anterior
		if ( ob_get_level() ) {
			ob_clean();
		}

		check_ajax_referer( 'pcw_retroactive', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		$filters = self::sanitize_filters( $_POST );
		$offset  = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$limit   = 50; // Processar 50 por vez.

		// Na primeira chamada, criar o batch.
		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';

		if ( empty( $batch_id ) ) {
			$batch_id = PCW_Retroactive_Batch::create( $filters );
			PCW_Retroactive_Batch::update_status( $batch_id, 'processing' );
		}

		$order_ids       = PCW_Retroactive_Processor::find_orders( $filters );
		$total_orders    = count( $order_ids );
		$orders_to_process = array_slice( $order_ids, $offset, $limit );

		$results = PCW_Retroactive_Processor::process_batch( $orders_to_process, $filters, $batch_id );

		$new_offset = $offset + $limit;
		$completed  = $new_offset >= $total_orders;

		// Se concluído, atualizar batch.
		if ( $completed ) {
			// Calcular totais finais.
			$batch = PCW_Retroactive_Batch::get( $batch_id );

			$final_total_cashback = absint( $batch->total_cashback ) + $results['success'];
			$final_total_amount   = floatval( $batch->total_amount ) + $results['amount'];

			PCW_Retroactive_Batch::update_status(
				$batch_id,
				'completed',
				array(
					'total_orders'   => $total_orders,
					'total_cashback' => $final_total_cashback,
					'total_amount'   => $final_total_amount,
					'error_log'      => implode( "\n", $results['logs'] ),
				)
			);
		} else {
			// Atualizar progresso parcial.
			$batch = PCW_Retroactive_Batch::get( $batch_id );

			PCW_Retroactive_Batch::update_status(
				$batch_id,
				'processing',
				array(
					'total_cashback' => absint( $batch->total_cashback ) + $results['success'],
					'total_amount'   => floatval( $batch->total_amount ) + $results['amount'],
				)
			);
		}

		wp_send_json_success(
			array(
				'batch_id'      => $batch_id,
				'offset'        => $new_offset,
				'total'         => $total_orders,
				'processed'     => min( $new_offset, $total_orders ),
				'success'       => $results['success'],
				'errors'        => $results['errors'],
				'amount'        => $results['amount'],
				'completed'     => $completed,
				'logs'          => $results['logs'],
			)
		);
	}

	/**
	 * AJAX: Reverter
	 */
	public static function ajax_revert() {
		// Limpar qualquer output anterior
		if ( ob_get_level() ) {
			ob_clean();
		}

		check_ajax_referer( 'pcw_retroactive', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';

		if ( empty( $batch_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Batch ID inválido.', 'person-cash-wallet' ) ) );
		}

		$result = PCW_Retroactive_Batch::revert( $batch_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Cancelar batch
	 */
	public static function ajax_cancel() {
		// Limpar qualquer output anterior.
		if ( ob_get_level() ) {
			ob_clean();
		}

		check_ajax_referer( 'pcw_retroactive', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'person-cash-wallet' ) ) );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';

		if ( empty( $batch_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Batch ID inválido.', 'person-cash-wallet' ) ) );
		}

		$result = PCW_Retroactive_Batch::cancel( $batch_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Buscar clientes
	 */
	public static function ajax_search_customers() {
		// Limpar qualquer output anterior
		if ( ob_get_level() ) {
			ob_clean();
		}

		check_ajax_referer( 'pcw_retroactive', 'nonce' );

		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		$users = get_users(
			array(
				'search'  => '*' . $search . '*',
				'number'  => 20,
				'orderby' => 'display_name',
			)
		);

		$results = array();

		foreach ( $users as $user ) {
			$results[] = array(
				'id'   => $user->ID,
				'text' => $user->display_name . ' (' . $user->user_email . ')',
			);
		}

		wp_send_json( array( 'results' => $results ) );
	}

	/**
	 * AJAX: Buscar produtos
	 */
	public static function ajax_search_products() {
		// Limpar qualquer output anterior
		if ( ob_get_level() ) {
			ob_clean();
		}

		check_ajax_referer( 'pcw_retroactive', 'nonce' );

		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		$products = wc_get_products(
			array(
				's'      => $search,
				'limit'  => 20,
				'return' => 'ids',
			)
		);

		$results = array();

		foreach ( $products as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$results[] = array(
				'id'   => $product_id,
				'text' => $product->get_name() . ' (#' . $product_id . ')',
			);
		}

		wp_send_json( array( 'results' => $results ) );
	}

	/**
	 * Sanitizar filtros
	 *
	 * @param array $post_data POST data.
	 * @return array
	 */
	private static function sanitize_filters( $post_data ) {
		$filters = array();

		$filters['date_from']      = isset( $post_data['date_from'] ) ? sanitize_text_field( wp_unslash( $post_data['date_from'] ) ) : '';
		$filters['date_to']        = isset( $post_data['date_to'] ) ? sanitize_text_field( wp_unslash( $post_data['date_to'] ) ) : '';
		$filters['status']         = isset( $post_data['status'] ) ? array_map( 'sanitize_text_field', wp_unslash( $post_data['status'] ) ) : array( 'wc-completed' );
		$filters['min_amount']     = isset( $post_data['min_amount'] ) ? floatval( $post_data['min_amount'] ) : 0;
		$filters['max_amount']     = isset( $post_data['max_amount'] ) ? floatval( $post_data['max_amount'] ) : 0;
		$filters['customers']      = isset( $post_data['customers'] ) ? array_map( 'absint', wp_unslash( $post_data['customers'] ) ) : array();
		$filters['products']       = isset( $post_data['products'] ) ? array_map( 'absint', wp_unslash( $post_data['products'] ) ) : array();
		$filters['cashback_rule']  = isset( $post_data['cashback_rule'] ) ? sanitize_text_field( wp_unslash( $post_data['cashback_rule'] ) ) : 'current';
		$filters['rule_id']        = isset( $post_data['rule_id'] ) ? absint( $post_data['rule_id'] ) : 0;
		$filters['fixed_amount']   = isset( $post_data['fixed_amount'] ) ? floatval( $post_data['fixed_amount'] ) : 0;
		$filters['percentage_value'] = isset( $post_data['percentage_value'] ) ? floatval( $post_data['percentage_value'] ) : 0;
		$filters['ignore_existing'] = isset( $post_data['ignore_existing'] ) ? (bool) $post_data['ignore_existing'] : false;
		$filters['send_email']     = isset( $post_data['send_email'] ) ? (bool) $post_data['send_email'] : false;

		return $filters;
	}
}
