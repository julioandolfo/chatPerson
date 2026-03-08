<?php
/**
 * Admin de Campanhas de Newsletter
 *
 * @package GrowlyDigital
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe admin de campanhas
 */
class PCW_Admin_Campaigns {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_pcw_save_campaign', array( $this, 'ajax_save_campaign' ) );
		add_action( 'wp_ajax_pcw_send_campaign', array( $this, 'ajax_send_campaign' ) );
		add_action( 'wp_ajax_pcw_delete_campaign', array( $this, 'ajax_delete_campaign' ) );
		add_action( 'wp_ajax_pcw_preview_recipients', array( $this, 'ajax_preview_recipients' ) );
		add_action( 'wp_ajax_pcw_preview_list_members', array( $this, 'ajax_preview_list_members' ) );
		add_action( 'wp_ajax_pcw_search_products', array( $this, 'ajax_search_products' ) );
	}

	/**
	 * Adicionar menu
	 */
	public function add_menu() {
		add_submenu_page(
			'pcw-dashboard',
			__( 'Campanhas', 'person-cash-wallet' ),
			__( 'Campanhas', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-campaigns',
			array( $this, 'render_page' ),
			25
		);
	}

	/**
	 * Enqueue scripts
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'pcw-campaigns' ) === false ) {
			return;
		}

		// Estilos do plugin
		wp_enqueue_style(
			'pcw-admin-campaigns',
			PCW_PLUGIN_URL . 'assets/css/admin-campaigns.css',
			array(),
			PCW_VERSION
		);

		wp_enqueue_style(
			'pcw-email-editor',
			PCW_PLUGIN_URL . 'assets/css/email-editor.css',
			array(),
			PCW_VERSION
		);

		// Scripts do plugin
		wp_enqueue_script(
			'pcw-admin-campaigns',
			PCW_PLUGIN_URL . 'assets/js/admin-campaigns.js',
			array( 'jquery' ),
			PCW_VERSION,
			true
		);

		wp_enqueue_script(
			'pcw-email-editor',
			PCW_PLUGIN_URL . 'assets/js/email-editor.js',
			array( 'jquery' ),
			PCW_VERSION,
			true
		);

		// Media uploader
		wp_enqueue_media();

		$smtp_accounts = PCW_SMTP_Accounts::instance();

		wp_localize_script( 'pcw-admin-campaigns', 'pcwCampaigns', array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'pcw_campaigns' ),
			'smtpAccounts' => $smtp_accounts->get_for_select(),
			'i18n'         => array(
				'confirmDelete' => __( 'Tem certeza que deseja excluir esta campanha?', 'person-cash-wallet' ),
				'confirmSend'   => __( 'Iniciar o envio da campanha? Esta ação não pode ser desfeita.', 'person-cash-wallet' ),
				'sending'       => __( 'Iniciando envio...', 'person-cash-wallet' ),
			),
		) );

		wp_localize_script( 'pcw-email-editor', 'pcwEmailEditor', array(
			'pluginUrl' => PCW_PLUGIN_URL,
			'siteName'  => get_bloginfo( 'name' ),
		) );
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		?>
		<div class="wrap pcw-campaigns-page">
			<?php
			if ( 'edit' === $action || 'new' === $action ) {
				$this->render_edit_form( $id );
			} elseif ( 'stats' === $action && $id > 0 ) {
				$this->render_stats( $id );
			} else {
				$this->render_list();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Renderizar lista de campanhas
	 */
	private function render_list() {
		$campaigns = PCW_Campaigns::instance()->get_all();

		?>
		<!-- Page Header -->
		<div class="pcw-page-header">
			<div>
				<h1>
					<span class="dashicons dashicons-megaphone"></span>
					<?php esc_html_e( 'Campanhas de Newsletter', 'person-cash-wallet' ); ?>
				</h1>
				<p class="description"><?php esc_html_e( 'Crie e gerencie campanhas de email marketing', 'person-cash-wallet' ); ?></p>
			</div>
			<div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-campaigns&action=new' ) ); ?>" class="button pcw-button-primary pcw-button-icon">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Nova Campanha', 'person-cash-wallet' ); ?>
				</a>
			</div>
		</div>

		<!-- Lista de Campanhas -->
		<div class="pcw-card">
			<div class="pcw-card-body" style="padding: 0;">
				<?php if ( empty( $campaigns ) ) : ?>
					<div class="pcw-empty-state">
						<span class="dashicons dashicons-megaphone"></span>
						<h3><?php esc_html_e( 'Nenhuma campanha criada', 'person-cash-wallet' ); ?></h3>
						<p><?php esc_html_e( 'Crie sua primeira campanha de newsletter para engajar seus clientes', 'person-cash-wallet' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-campaigns&action=new' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Criar Campanha', 'person-cash-wallet' ); ?>
						</a>
					</div>
				<?php else : ?>
					<table class="pcw-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Campanha', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Enviados', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Abertos', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Cliques', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Criado em', 'person-cash-wallet' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $campaigns as $campaign ) : 
								$open_rate = $campaign->sent_count > 0 ? round( ( $campaign->opened_count / $campaign->sent_count ) * 100, 1 ) : 0;
								$click_rate = $campaign->opened_count > 0 ? round( ( $campaign->clicked_count / $campaign->opened_count ) * 100, 1 ) : 0;
							?>
							<tr data-id="<?php echo esc_attr( $campaign->id ); ?>">
								<td>
									<strong><?php echo esc_html( $campaign->name ); ?></strong>
									<p class="description"><?php echo esc_html( $campaign->subject ); ?></p>
								</td>
								<td>
									<?php
									$status_classes = array(
										'draft'     => 'pcw-badge',
										'scheduled' => 'pcw-badge pcw-badge-info',
										'sending'   => 'pcw-badge pcw-badge-warning',
										'completed' => 'pcw-badge pcw-badge-success',
									);
									$status_labels = array(
										'draft'     => __( 'Rascunho', 'person-cash-wallet' ),
										'scheduled' => __( 'Agendada', 'person-cash-wallet' ),
										'sending'   => __( 'Enviando', 'person-cash-wallet' ),
										'completed' => __( 'Concluída', 'person-cash-wallet' ),
									);
									$class = isset( $status_classes[ $campaign->status ] ) ? $status_classes[ $campaign->status ] : 'pcw-badge';
									$label = isset( $status_labels[ $campaign->status ] ) ? $status_labels[ $campaign->status ] : $campaign->status;
									?>
									<span class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></span>
								</td>
								<td>
									<?php echo esc_html( number_format_i18n( $campaign->sent_count ) ); ?> / <?php echo esc_html( number_format_i18n( $campaign->total_recipients ) ); ?>
								</td>
								<td>
									<span class="pcw-rate"><?php echo esc_html( $campaign->opened_count ); ?></span>
									<span class="pcw-rate-percent">(<?php echo esc_html( $open_rate ); ?>%)</span>
								</td>
								<td>
									<span class="pcw-rate"><?php echo esc_html( $campaign->clicked_count ); ?></span>
									<span class="pcw-rate-percent">(<?php echo esc_html( $click_rate ); ?>%)</span>
								</td>
								<td>
									<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $campaign->created_at ) ) ); ?>
								</td>
								<td class="pcw-actions-cell">
									<?php if ( $campaign->status === 'draft' ) : ?>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-campaigns&action=edit&id=' . $campaign->id ) ); ?>" 
											class="button button-small" title="<?php esc_attr_e( 'Editar', 'person-cash-wallet' ); ?>">
											<span class="dashicons dashicons-edit"></span>
										</a>
									<?php endif; ?>
									<?php if ( in_array( $campaign->status, array( 'sending', 'completed' ), true ) ) : ?>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-campaigns&action=stats&id=' . $campaign->id ) ); ?>" 
											class="button button-small" title="<?php esc_attr_e( 'Estatísticas', 'person-cash-wallet' ); ?>">
											<span class="dashicons dashicons-chart-bar"></span>
										</a>
									<?php endif; ?>
									<?php if ( $campaign->status === 'draft' ) : ?>
										<button type="button" class="button button-small pcw-delete-campaign" 
											data-id="<?php echo esc_attr( $campaign->id ); ?>"
											title="<?php esc_attr_e( 'Excluir', 'person-cash-wallet' ); ?>">
											<span class="dashicons dashicons-trash"></span>
										</button>
									<?php endif; ?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderizar formulário de edição
	 *
	 * @param int $id ID da campanha.
	 */
	private function render_edit_form( $id ) {
		$campaign = null;
		$is_new = $id === 0;

		if ( ! $is_new ) {
			$campaign = PCW_Campaigns::instance()->get( $id );
		}

		$smtp_accounts = PCW_SMTP_Accounts::instance()->get_for_select();

		?>
		<!-- Page Header -->
		<div class="pcw-page-header">
			<div>
				<h1>
					<span class="dashicons dashicons-megaphone"></span>
					<?php echo $is_new ? esc_html__( 'Nova Campanha', 'person-cash-wallet' ) : esc_html__( 'Editar Campanha', 'person-cash-wallet' ); ?>
				</h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-campaigns' ) ); ?>" class="pcw-back-link">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Voltar para campanhas', 'person-cash-wallet' ); ?>
				</a>
			</div>
		</div>

		<form id="pcw-campaign-form" class="pcw-campaign-form">
			<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $id ); ?>">

			<div class="pcw-form-columns">
				<!-- Coluna Principal -->
				<div class="pcw-form-main">
					<!-- Conteúdo -->
					<div class="pcw-card">
						<div class="pcw-card-header">
							<h2>
								<span class="dashicons dashicons-edit"></span>
								<?php esc_html_e( 'Conteúdo da Campanha', 'person-cash-wallet' ); ?>
							</h2>
						</div>
						<div class="pcw-card-body">
							<div class="pcw-form-group">
								<label for="campaign_name"><?php esc_html_e( 'Nome da Campanha', 'person-cash-wallet' ); ?> *</label>
								<input type="text" id="campaign_name" name="name" 
									value="<?php echo esc_attr( $campaign ? $campaign->name : '' ); ?>" 
									required placeholder="<?php esc_attr_e( 'Nome interno para identificação', 'person-cash-wallet' ); ?>">
							</div>

							<div class="pcw-form-group">
								<label for="campaign_subject"><?php esc_html_e( 'Assunto do Email', 'person-cash-wallet' ); ?> *</label>
								<input type="text" id="campaign_subject" name="subject" 
									value="<?php echo esc_attr( $campaign ? $campaign->subject : '' ); ?>" 
									required placeholder="<?php esc_attr_e( 'Ex: Novidades especiais para você!', 'person-cash-wallet' ); ?>">
							</div>

							<div class="pcw-form-group">
								<label for="campaign_preview_text"><?php esc_html_e( 'Texto de Preview', 'person-cash-wallet' ); ?></label>
								<input type="text" id="campaign_preview_text" name="preview_text" 
									value="<?php echo esc_attr( $campaign ? $campaign->preview_text : '' ); ?>" 
									placeholder="<?php esc_attr_e( 'Texto exibido após o assunto na caixa de entrada', 'person-cash-wallet' ); ?>">
							</div>

							<div class="pcw-form-group">
								<label for="campaign_content"><?php esc_html_e( 'Conteúdo do Email', 'person-cash-wallet' ); ?></label>
								
								<div class="pcw-email-editor-actions">
									<button type="button" class="button button-primary pcw-open-email-editor" data-target="campaign_content_visual">
										<span class="dashicons dashicons-edit-page"></span>
										<?php esc_html_e( 'Editor Visual (Drag & Drop)', 'person-cash-wallet' ); ?>
									</button>
								</div>
								
								<!-- Campo oculto para armazenar o HTML do editor visual -->
								<textarea id="campaign_content_visual" name="content" style="display: none;"><?php echo esc_textarea( $campaign ? $campaign->content : '' ); ?></textarea>
								
								<!-- Preview do email atual -->
								<?php if ( $campaign && ! empty( $campaign->content ) ) : ?>
								<div class="pcw-email-preview">
									<p><strong><?php esc_html_e( 'Preview atual:', 'person-cash-wallet' ); ?></strong></p>
									<iframe class="pcw-email-preview-frame" srcdoc="<?php echo esc_attr( $campaign->content ); ?>"></iframe>
								</div>
								<?php endif; ?>

								<p class="description" style="margin-top: 15px;">
									<strong><?php esc_html_e( 'Variáveis disponíveis:', 'person-cash-wallet' ); ?></strong><br>
									<code>{{customer_name}}</code> - Nome completo<br>
									<code>{{customer_first_name}}</code> - Primeiro nome<br>
									<code>{{customer_email}}</code> - Email<br>
									<code>{{cashback_balance}}</code> - Saldo de cashback<br>
									<code>{{user_level}}</code> - Nível VIP<br>
									<code>{{site_name}}</code> - Nome da loja<br>
									<code>{{site_url}}</code> - URL da loja<br>
									<code>{{unsubscribe_url}}</code> - Link para descadastro
								</p>
							</div>
						</div>
					</div>
				</div>

				<!-- Coluna Lateral -->
				<div class="pcw-form-sidebar">
					<!-- Publicar -->
					<div class="pcw-card">
						<div class="pcw-card-header">
							<h2><?php esc_html_e( 'Enviar Campanha', 'person-cash-wallet' ); ?></h2>
						</div>
						<div class="pcw-card-body">
							<button type="submit" class="button button-secondary" style="width: 100%; margin-bottom: 10px;">
								<span class="dashicons dashicons-saved"></span>
								<?php esc_html_e( 'Salvar Rascunho', 'person-cash-wallet' ); ?>
							</button>

							<button type="button" class="button button-primary" id="send-campaign" style="width: 100%;">
								<span class="dashicons dashicons-email"></span>
								<?php esc_html_e( 'Enviar Campanha', 'person-cash-wallet' ); ?>
							</button>
						</div>
					</div>

					<!-- Conta SMTP -->
					<div class="pcw-card">
						<div class="pcw-card-header">
							<h2><?php esc_html_e( 'Conta de Envio', 'person-cash-wallet' ); ?></h2>
						</div>
						<div class="pcw-card-body">
							<div class="pcw-form-group">
								<select name="smtp_account_id">
									<option value=""><?php esc_html_e( 'Padrão do WordPress', 'person-cash-wallet' ); ?></option>
									<?php foreach ( $smtp_accounts as $acc_id => $acc_name ) : ?>
										<option value="<?php echo esc_attr( $acc_id ); ?>" <?php selected( $campaign && $campaign->smtp_account_id == $acc_id ); ?>>
											<?php echo esc_html( $acc_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-settings&tab=smtp' ) ); ?>">
										<?php esc_html_e( 'Gerenciar contas SMTP', 'person-cash-wallet' ); ?>
									</a>
								</p>
							</div>
						</div>
					</div>

					<!-- Destinatários -->
					<div class="pcw-card">
						<div class="pcw-card-header">
							<h2><?php esc_html_e( 'Destinatários', 'person-cash-wallet' ); ?></h2>
						</div>
						<div class="pcw-card-body">
							<div class="pcw-form-group">
								<label><?php esc_html_e( 'Tipo de Audiência', 'person-cash-wallet' ); ?></label>
								<select name="audience_type" id="audience-type">
									<option value="filtered"><?php esc_html_e( 'Por Filtros (todos/clientes/etc)', 'person-cash-wallet' ); ?></option>
									<option value="custom_list"><?php esc_html_e( 'Lista Personalizada', 'person-cash-wallet' ); ?></option>
								</select>
							</div>

							<!-- Filtros (padrão) -->
							<div id="filtered-audience" class="audience-section active" style="display: block;">
								<div class="pcw-form-group">
									<label><?php esc_html_e( 'Tipo de Cliente', 'person-cash-wallet' ); ?></label>
									<select name="recipient_conditions[customer_type]">
										<option value="all"><?php esc_html_e( 'Todos os usuários', 'person-cash-wallet' ); ?></option>
										<option value="customers"><?php esc_html_e( 'Apenas clientes', 'person-cash-wallet' ); ?></option>
										<option value="subscribers"><?php esc_html_e( 'Apenas assinantes', 'person-cash-wallet' ); ?></option>
									</select>
								</div>

								<div class="pcw-form-group">
									<label><?php esc_html_e( 'Mínimo de Pedidos', 'person-cash-wallet' ); ?></label>
									<input type="number" name="recipient_conditions[min_orders]" min="0" value="0">
								</div>

								<div class="pcw-form-group">
									<label><?php esc_html_e( 'Mínimo Gasto (R$)', 'person-cash-wallet' ); ?></label>
									<input type="number" name="recipient_conditions[min_spent]" min="0" step="0.01" value="0">
								</div>

								<button type="button" class="button" id="preview-recipients" style="width: 100%;">
									<span class="dashicons dashicons-groups"></span>
									<?php esc_html_e( 'Ver Destinatários', 'person-cash-wallet' ); ?>
								</button>
								<div id="recipients-preview" style="margin-top: 10px;"></div>
							</div>

							<!-- Lista Personalizada -->
							<div id="custom-list-audience" class="audience-section">
								<div class="pcw-form-group">
									<label><?php esc_html_e( 'Selecionar Lista', 'person-cash-wallet' ); ?></label>
									<select name="custom_list_id" id="custom-list-select">
										<option value=""><?php esc_html_e( 'Selecione uma lista', 'person-cash-wallet' ); ?></option>
										<?php
										$lists = PCW_Custom_Lists::get_all();
										foreach ( $lists as $list ) :
											?>
											<option value="<?php echo esc_attr( $list->id ); ?>">
												<?php echo esc_html( $list->name ); ?> (<?php echo esc_html( number_format_i18n( $list->total_members ) ); ?> membros)
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="pcw-notice pcw-notice-info">
									<p>
										<?php esc_html_e( 'Não tem listas? ', 'person-cash-wallet' ); ?>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-custom-lists' ) ); ?>" target="_blank">
											<?php esc_html_e( 'Criar Nova Lista', 'person-cash-wallet' ); ?>
										</a>
									</p>
								</div>

								<div id="list-members-preview" style="margin-top: 15px;"></div>
							</div>
						</div>
					</div>

					<!-- Configurações de Envio -->
					<div class="pcw-card">
						<div class="pcw-card-header">
							<h2><?php esc_html_e( 'Configurações de Envio', 'person-cash-wallet' ); ?></h2>
						</div>
						<div class="pcw-card-body">
							<div class="pcw-form-group">
								<label><?php esc_html_e( 'Emails por Lote', 'person-cash-wallet' ); ?></label>
								<input type="number" name="batch_size" min="1" max="500" 
									value="<?php echo esc_attr( $campaign ? $campaign->batch_size : 50 ); ?>">
								<p class="description"><?php esc_html_e( 'Quantidade de emails enviados por vez', 'person-cash-wallet' ); ?></p>
							</div>

							<div class="pcw-form-group">
								<label><?php esc_html_e( 'Intervalo entre Lotes (segundos)', 'person-cash-wallet' ); ?></label>
								<input type="number" name="batch_delay" min="10" 
									value="<?php echo esc_attr( $campaign ? $campaign->batch_delay : 60 ); ?>">
								<p class="description"><?php esc_html_e( 'Tempo de espera entre cada lote', 'person-cash-wallet' ); ?></p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</form>
		<?php
	}

	/**
	 * Renderizar estatísticas
	 *
	 * @param int $id ID da campanha.
	 */
	private function render_stats( $id ) {
		$campaign = PCW_Campaigns::instance()->get( $id );

		if ( ! $campaign ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Campanha não encontrada', 'person-cash-wallet' ) . '</p></div>';
			return;
		}

		$stats = PCW_Campaigns::instance()->get_stats( $id );

		?>
		<!-- Page Header -->
		<div class="pcw-page-header">
			<div>
				<h1>
					<span class="dashicons dashicons-chart-bar"></span>
					<?php echo esc_html( $campaign->name ); ?>
				</h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-campaigns' ) ); ?>" class="pcw-back-link">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Voltar para campanhas', 'person-cash-wallet' ); ?>
				</a>
			</div>
		</div>

		<!-- Stats Cards -->
		<div class="pcw-stats-grid">
			<div class="pcw-stat-card">
				<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['sent'] ) ); ?></div>
				<div class="pcw-stat-label"><?php esc_html_e( 'Emails Enviados', 'person-cash-wallet' ); ?></div>
			</div>
			<div class="pcw-stat-card">
				<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['opened'] ) ); ?></div>
				<div class="pcw-stat-label"><?php esc_html_e( 'Aberturas', 'person-cash-wallet' ); ?></div>
				<div class="pcw-stat-percent"><?php echo esc_html( $stats['open_rate'] ); ?>%</div>
			</div>
			<div class="pcw-stat-card">
				<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['clicked'] ) ); ?></div>
				<div class="pcw-stat-label"><?php esc_html_e( 'Cliques', 'person-cash-wallet' ); ?></div>
				<div class="pcw-stat-percent"><?php echo esc_html( $stats['click_rate'] ); ?>%</div>
			</div>
			<div class="pcw-stat-card">
				<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['failed'] ) ); ?></div>
				<div class="pcw-stat-label"><?php esc_html_e( 'Falhas', 'person-cash-wallet' ); ?></div>
			</div>
		</div>

		<!-- Progresso -->
		<?php if ( $campaign->status === 'sending' ) : ?>
		<div class="pcw-card">
			<div class="pcw-card-header">
				<h2><?php esc_html_e( 'Progresso do Envio', 'person-cash-wallet' ); ?></h2>
			</div>
			<div class="pcw-card-body">
				<?php 
				$progress = $campaign->total_recipients > 0 ? round( ( $stats['sent'] / $campaign->total_recipients ) * 100 ) : 0;
				?>
				<div class="pcw-progress-bar">
					<div class="pcw-progress-fill" style="width: <?php echo esc_attr( $progress ); ?>%;"></div>
				</div>
				<p style="text-align: center; margin-top: 10px;">
					<?php echo esc_html( $stats['sent'] ); ?> / <?php echo esc_html( $campaign->total_recipients ); ?> 
					(<?php echo esc_html( $progress ); ?>%)
				</p>
			</div>
		</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * AJAX: Salvar campanha
	 */
	public function ajax_save_campaign() {
		check_ajax_referer( 'pcw_campaigns', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;

		$data = array(
			'name'                 => isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '',
			'subject'              => isset( $_POST['subject'] ) ? sanitize_text_field( $_POST['subject'] ) : '',
			'preview_text'         => isset( $_POST['preview_text'] ) ? sanitize_text_field( $_POST['preview_text'] ) : '',
			'content'              => isset( $_POST['content'] ) ? wp_kses_post( $_POST['content'] ) : '',
			'smtp_account_id'      => isset( $_POST['smtp_account_id'] ) ? absint( $_POST['smtp_account_id'] ) : null,
			'recipient_conditions' => isset( $_POST['recipient_conditions'] ) ? $_POST['recipient_conditions'] : array(),
			'batch_size'           => isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 50,
			'batch_delay'          => isset( $_POST['batch_delay'] ) ? absint( $_POST['batch_delay'] ) : 60,
		);

		$manager = PCW_Campaigns::instance();

		if ( $campaign_id > 0 ) {
			$result = $manager->update( $campaign_id, $data );
		} else {
			$campaign_id = $manager->create( $data );
			$result = $campaign_id > 0;
		}

		if ( $result ) {
			wp_send_json_success( array(
				'message'     => __( 'Campanha salva!', 'person-cash-wallet' ),
				'campaign_id' => $campaign_id,
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Erro ao salvar', 'person-cash-wallet' ) ) );
		}
	}

	/**
	 * AJAX: Preview membros da lista
	 */
	public function ajax_preview_list_members() {
		check_ajax_referer( 'pcw_campaigns', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$list_id = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0;

		if ( ! $list_id ) {
			wp_send_json_error( array( 'message' => __( 'Lista não encontrada', 'person-cash-wallet' ) ) );
		}

		$members = PCW_Custom_Lists::get_members( $list_id, array( 'limit' => 100 ) );
		$list = PCW_Custom_Lists::get( $list_id );

		wp_send_json_success( array(
			'count'   => $list ? $list->total_members : 0,
			'preview' => array_slice( $members, 0, 5 ),
		) );
	}

	/**
	 * AJAX: Enviar campanha
	 */
	public function ajax_send_campaign() {
		check_ajax_referer( 'pcw_campaigns', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;

		if ( ! $campaign_id ) {
			wp_send_json_error( array( 'message' => __( 'Campanha não encontrada', 'person-cash-wallet' ) ) );
		}

		$result = PCW_Campaigns::instance()->start( $campaign_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'  => __( 'Campanha iniciada!', 'person-cash-wallet' ),
			'redirect' => admin_url( 'admin.php?page=pcw-campaigns&action=stats&id=' . $campaign_id ),
		) );
	}

	/**
	 * AJAX: Deletar campanha
	 */
	public function ajax_delete_campaign() {
		check_ajax_referer( 'pcw_campaigns', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		PCW_Campaigns::instance()->delete( $id );

		wp_send_json_success();
	}

	/**
	 * AJAX: Preview destinatários
	 */
	public function ajax_preview_recipients() {
		check_ajax_referer( 'pcw_campaigns', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$conditions = isset( $_POST['conditions'] ) ? $_POST['conditions'] : array();
		$recipients = PCW_Campaigns::instance()->get_recipients( $conditions );

		wp_send_json_success( array(
			'count' => count( $recipients ),
			'preview' => array_slice( $recipients, 0, 5 ),
		) );
	}

	/**
	 * AJAX: Buscar produtos
	 */
	public function ajax_search_products() {
		check_ajax_referer( 'pcw_campaigns', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = 20;

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$query = new WP_Query( $args );
		$products = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$product = wc_get_product( get_the_ID() );

				if ( ! $product ) {
					continue;
				}

				$image = wp_get_attachment_image_url( $product->get_image_id(), 'medium' );
				$regular_price = $product->get_regular_price();
				$sale_price = $product->get_sale_price();

				$products[] = array(
					'id'            => $product->get_id(),
					'name'          => $product->get_name(),
					'permalink'     => $product->get_permalink(),
					'image'         => $image ?: wc_placeholder_img_src(),
					'price'         => $product->get_price_html(),
					'regular_price' => $regular_price ? wc_price( $regular_price ) : '',
					'sale_price'    => $sale_price ? wc_price( $sale_price ) : '',
					'on_sale'       => $product->is_on_sale(),
					'sku'           => $product->get_sku(),
					'description'   => wp_trim_words( $product->get_short_description(), 20 ),
				);
			}
			wp_reset_postdata();
		}

		wp_send_json_success( array(
			'products'   => $products,
			'total'      => $query->found_posts,
			'pages'      => $query->max_num_pages,
			'page'       => $page,
		) );
	}
}
