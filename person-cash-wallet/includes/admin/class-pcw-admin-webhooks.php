<?php
/**
 * Página de administração de Webhooks
 *
 * @package PersonCashWallet
 * @since 1.2.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCW_Admin_Webhooks {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_pcw_save_webhook', array( $this, 'ajax_save_webhook' ) );
		add_action( 'wp_ajax_pcw_delete_webhook', array( $this, 'ajax_delete_webhook' ) );
		add_action( 'wp_ajax_pcw_test_webhook_send', array( $this, 'ajax_test_webhook_send' ) );
		add_action( 'wp_ajax_pcw_generate_webhook_message', array( $this, 'ajax_generate_webhook_message' ) );
	}

	/**
	 * Log de debug para arquivo
	 */
	private function log_debug( $message ) {
		$log_dir = PCW_PLUGIN_DIR . 'logs';
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}
		
		$log_file = $log_dir . '/webhooks-debug.log';
		$timestamp = current_time( 'Y-m-d H:i:s' );
		$log_entry = "[{$timestamp}] {$message}\n";
		
		// Usar error_log como fallback se file_put_contents falhar
		if ( false === @file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX ) ) {
			error_log( '[PCW Webhooks] ' . $message );
		}
	}

	/**
	 * Adicionar menu
	 */
	public function add_menu() {
		add_submenu_page(
			'pcw-dashboard',
			__( 'Webhooks', 'person-cash-wallet' ),
			__( 'Webhooks', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-webhooks',
			array( $this, 'render_page' ),
			30
		);
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts( $hook ) {
		// Verificar se estamos na página de webhooks (o hook pode variar)
		if ( strpos( $hook, 'pcw-webhooks' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'pcw-webhooks',
			PCW_PLUGIN_URL . 'assets/js/admin-webhooks.js',
			array( 'jquery' ),
			PCW_VERSION,
			true
		);

		wp_localize_script(
			'pcw-webhooks',
			'pcwWebhooks',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pcw_webhooks' ),
				'i18n'    => array(
					'confirmDelete' => __( 'Tem certeza que deseja excluir este webhook?', 'person-cash-wallet' ),
					'testSuccess'   => __( 'Teste realizado com sucesso!', 'person-cash-wallet' ),
					'testError'     => __( 'Erro ao testar webhook', 'person-cash-wallet' ),
				),
			)
		);

		wp_enqueue_style(
			'pcw-webhooks',
			PCW_PLUGIN_URL . 'assets/css/admin-webhooks.css',
			array(),
			PCW_VERSION
		);
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_webhooks';

		// Verificar se precisa forçar recriação da tabela
		if ( isset( $_GET['fix_table'] ) && $_GET['fix_table'] === '1' ) {
			$this->log_debug( '🔧 Forçando recriação da tabela via parâmetro URL' );
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
			$this->create_webhooks_table();
			echo '<div class="notice notice-success"><p>Tabela de webhooks recriada com sucesso! <a href="' . admin_url( 'admin.php?page=pcw-webhooks' ) . '">Clique aqui para continuar</a></p></div>';
		}

		// Verificar se tabela existe e tem estrutura correta
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;

		if ( ! $table_exists ) {
			$this->create_webhooks_table();
		} else {
			// Verificar se a estrutura está correta
			$has_event_field = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'event'" );
			if ( ! $has_event_field ) {
				// Mostrar botão para corrigir
				echo '<div class="notice notice-error" style="padding: 20px;">';
				echo '<h3>⚠️ Tabela de webhooks com estrutura desatualizada</h3>';
				echo '<p>A tabela precisa ser atualizada para funcionar corretamente.</p>';
				echo '<a href="' . admin_url( 'admin.php?page=pcw-webhooks&fix_table=1' ) . '" class="button button-primary">Atualizar Tabela Agora</a>';
				echo '</div>';
				return;
			}

			// Migration automática: adicionar body_template_no_tracking se não existir
			$has_no_tracking_col = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'body_template_no_tracking'" );
			if ( ! $has_no_tracking_col ) {
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN body_template_no_tracking text AFTER body_template" );
			}
		}

		$webhook_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$action     = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

		if ( $webhook_id > 0 || $action === 'add' ) {
			$this->render_edit_page( $webhook_id );
		} else {
			$this->render_list_page();
		}
	}

	/**
	 * Criar tabela de webhooks
	 */
	private function create_webhooks_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_webhooks';
		$charset_collate = $wpdb->get_charset_collate();

		$this->log_debug( '🔧 Iniciando criação/verificação da tabela: ' . $table );

		// Verificar se já existe uma tabela
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
		
		if ( $table_exists ) {
			// Verificar se tem a estrutura correta (campo 'event' deve existir)
			$has_event_field = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'event'" );
			$has_old_event_type = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'event_type'" );
			
			$this->log_debug( 'Tabela existe. Campo event: ' . var_export( $has_event_field, true ) . ', Campo event_type (antigo): ' . var_export( $has_old_event_type, true ) );
			
			if ( ! $has_event_field || $has_old_event_type ) {
				$this->log_debug( '⚠️ Estrutura desatualizada, dropando tabela antiga...' );
				$wpdb->query( "DROP TABLE {$table}" );
				$this->log_debug( 'Tabela dropada com sucesso' );
			} else {
				$this->log_debug( '✅ Estrutura da tabela está correta' );
				return; // Tabela já existe com estrutura correta
			}
		}

		$sql = "CREATE TABLE {$table} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			type varchar(50) NOT NULL DEFAULT 'personizi_whatsapp',
			event varchar(100) NOT NULL,
			url text NOT NULL,
			method varchar(10) NOT NULL DEFAULT 'POST',
			auth_type varchar(20) NOT NULL DEFAULT 'bearer',
			auth_token text,
			headers text,
			body_template text,
			body_template_no_tracking text,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY type (type),
			KEY event (event),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );
		
		$this->log_debug( 'dbDelta result: ' . wp_json_encode( $result ) );

		// Migration: adicionar coluna body_template_no_tracking se não existir
		$has_no_tracking_col = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'body_template_no_tracking'" );
		if ( ! $has_no_tracking_col ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN body_template_no_tracking text AFTER body_template" );
			$this->log_debug( '✅ Coluna body_template_no_tracking adicionada via migration' );
		}

		$this->log_debug( '✅ Tabela de webhooks criada com sucesso' );
	}

	/**
	 * Renderizar lista de webhooks
	 */
	private function render_list_page() {
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_webhooks';
		$webhooks = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );

		// Verificar se Personizi está configurado
		$personizi = PCW_Personizi_Integration::instance();
		$personizi_configured = ! empty( $personizi->get_api_token() );
		?>
		<div class="wrap">
			<div class="pcw-page-header">
				<div>
					<h1>
						<span class="dashicons dashicons-admin-plugins"></span>
						<?php esc_html_e( 'Webhooks', 'person-cash-wallet' ); ?>
					</h1>
					<p class="description">
						<?php esc_html_e( 'Configure webhooks para enviar notificações automáticas para serviços externos quando eventos ocorrem na loja.', 'person-cash-wallet' ); ?>
					</p>
				</div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-webhooks&action=add' ) ); ?>" class="button button-primary">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Criar Webhook', 'person-cash-wallet' ); ?>
				</a>
			</div>

			<!-- Aviso sobre Sistema de Filas -->
			<div class="notice notice-info" style="display: flex; align-items: center; gap: 16px; padding: 16px; background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); border-left: 4px solid #667eea;">
				<span class="dashicons dashicons-list-view" style="font-size: 36px; width: 36px; height: 36px; color: #667eea;"></span>
				<div style="flex: 1;">
					<p style="margin: 0 0 8px;">
						<strong style="font-size: 15px;"><?php esc_html_e( '✨ Novo: Sistema de Múltiplos Números & Rate Limiting', 'person-cash-wallet' ); ?></strong>
					</p>
					<p style="margin: 0 0 8px; font-size: 13px;">
						<?php esc_html_e( 'Configure múltiplos números WhatsApp, defina limites de envio por hora e escolha estratégias de distribuição (Round Robin, Aleatório ou Por Peso). Todos os webhooks usarão automaticamente o sistema de filas!', 'person-cash-wallet' ); ?>
					</p>
					<div style="display: flex; gap: 10px;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-queue&tab=numbers' ) ); ?>" class="button button-primary">
							<span class="dashicons dashicons-admin-settings"></span>
							<?php esc_html_e( 'Configurar Números', 'person-cash-wallet' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-queue&tab=queue' ) ); ?>" class="button">
							<span class="dashicons dashicons-chart-bar"></span>
							<?php esc_html_e( 'Ver Filas', 'person-cash-wallet' ); ?>
						</a>
					</div>
				</div>
			</div>

			<?php if ( ! $personizi_configured ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Personizi WhatsApp não configurado.', 'person-cash-wallet' ); ?></strong>
						<?php esc_html_e( 'Configure o Personizi para enviar mensagens WhatsApp automaticamente.', 'person-cash-wallet' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-settings&tab=personizi' ) ); ?>" class="button button-small">
							<?php esc_html_e( 'Configurar Agora', 'person-cash-wallet' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( empty( $webhooks ) ) : ?>
				<div class="pcw-empty-state">
					<div class="pcw-empty-icon">
						<span class="dashicons dashicons-admin-plugins"></span>
					</div>
					<h2><?php esc_html_e( 'Nenhum webhook configurado', 'person-cash-wallet' ); ?></h2>
					<p><?php esc_html_e( 'Crie seu primeiro webhook para começar a enviar notificações automáticas.', 'person-cash-wallet' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-webhooks&action=add' ) ); ?>" class="button button-primary button-hero">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Criar Primeiro Webhook', 'person-cash-wallet' ); ?>
					</a>
					
					<div class="pcw-quick-templates">
						<h3><?php esc_html_e( 'Templates Populares:', 'person-cash-wallet' ); ?></h3>
						<div class="pcw-template-grid">
							<div class="pcw-template-card">
								<div class="pcw-template-icon">💬</div>
								<h4><?php esc_html_e( 'Personizi WhatsApp', 'person-cash-wallet' ); ?></h4>
								<p><?php esc_html_e( 'Envie mensagens WhatsApp automaticamente', 'person-cash-wallet' ); ?></p>
							</div>
							<div class="pcw-template-card">
								<div class="pcw-template-icon">📧</div>
								<h4><?php esc_html_e( 'Email Notification', 'person-cash-wallet' ); ?></h4>
								<p><?php esc_html_e( 'Envie notificações por email', 'person-cash-wallet' ); ?></p>
							</div>
							<div class="pcw-template-card">
								<div class="pcw-template-icon">🔔</div>
								<h4><?php esc_html_e( 'Slack/Discord', 'person-cash-wallet' ); ?></h4>
								<p><?php esc_html_e( 'Notificações em tempo real', 'person-cash-wallet' ); ?></p>
							</div>
						</div>
					</div>
				</div>
			<?php else : ?>
				<div class="pcw-webhooks-grid">
					<?php foreach ( $webhooks as $webhook ) : ?>
						<div class="pcw-webhook-card">
							<div class="pcw-webhook-header">
								<div class="pcw-webhook-type-badge pcw-webhook-type-<?php echo esc_attr( $webhook->type ); ?>">
									<?php
									if ( $webhook->type === 'personizi_whatsapp' ) {
										echo '💬 WhatsApp';
									} else {
										echo '🔗 ' . esc_html( ucfirst( $webhook->type ) );
									}
									?>
								</div>
								<div class="pcw-webhook-status">
									<?php if ( $webhook->status === 'active' ) : ?>
										<span class="pcw-status-badge pcw-status-active">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php esc_html_e( 'Ativo', 'person-cash-wallet' ); ?>
										</span>
									<?php else : ?>
										<span class="pcw-status-badge pcw-status-inactive">
											<span class="dashicons dashicons-dismiss"></span>
											<?php esc_html_e( 'Inativo', 'person-cash-wallet' ); ?>
										</span>
									<?php endif; ?>
								</div>
							</div>

							<div class="pcw-webhook-body">
								<h3><?php echo esc_html( $webhook->name ); ?></h3>
								<p class="pcw-webhook-event">
									<strong><?php esc_html_e( 'Evento:', 'person-cash-wallet' ); ?></strong>
									<?php echo esc_html( $this->get_event_label( $webhook->event ) ); ?>
								</p>
								<p class="pcw-webhook-url">
									<strong><?php esc_html_e( 'URL:', 'person-cash-wallet' ); ?></strong>
									<code><?php echo esc_html( substr( $webhook->url, 0, 50 ) . ( strlen( $webhook->url ) > 50 ? '...' : '' ) ); ?></code>
								</p>
							</div>

							<div class="pcw-webhook-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-webhooks&edit=' . $webhook->id ) ); ?>" class="button">
									<span class="dashicons dashicons-edit"></span>
									<?php esc_html_e( 'Editar', 'person-cash-wallet' ); ?>
								</a>
								<button type="button" class="button pcw-delete-webhook" data-id="<?php echo esc_attr( $webhook->id ); ?>">
									<span class="dashicons dashicons-trash"></span>
									<?php esc_html_e( 'Excluir', 'person-cash-wallet' ); ?>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renderizar página de edição/criação
	 */
	private function render_edit_page( $webhook_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_webhooks';
		
		$webhook = null;
		if ( $webhook_id > 0 ) {
			$webhook = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $webhook_id ) );
		}

		$is_edit = $webhook !== null;
		$personizi = PCW_Personizi_Integration::instance();
		$personizi_accounts = $personizi->get_whatsapp_accounts();
		$personizi_configured = ! is_wp_error( $personizi_accounts ) && ! empty( $personizi_accounts );

		// Valores padrão
		$defaults = array(
			'name'                       => '',
			'type'                       => 'personizi_whatsapp',
			'event'                      => 'order_completed',
			'url'                        => 'https://chat.personizi.com.br/api/v1/messages/send',
			'method'                     => 'POST',
			'auth_type'                  => 'bearer',
			'auth_token'                 => $personizi->get_api_token(),
			'headers'                    => '',
			'body_template'              => '',
			'body_template_no_tracking'  => '',
			'status'                     => 'active',
		);

		if ( $webhook ) {
			foreach ( $defaults as $key => $value ) {
				if ( isset( $webhook->$key ) ) {
					$defaults[ $key ] = $webhook->$key;
				}
			}
		}
		
		// Extrair 'from' do campo headers se for Personizi
		$personizi_from = '';
		if ( $webhook && $webhook->type === 'personizi_whatsapp' && ! empty( $webhook->headers ) ) {
			$headers_data = json_decode( $webhook->headers, true );
			if ( isset( $headers_data['from'] ) ) {
				$personizi_from = $headers_data['from'];
			}
		}
		?>
		<div class="wrap">
			<div class="pcw-page-header">
				<div>
					<h1>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-webhooks' ) ); ?>" class="pcw-back-button">
							<span class="dashicons dashicons-arrow-left-alt2"></span>
						</a>
						<?php echo $is_edit ? esc_html__( 'Editar Webhook', 'person-cash-wallet' ) : esc_html__( 'Criar Webhook', 'person-cash-wallet' ); ?>
					</h1>
				</div>
			</div>

			<form id="pcw-webhook-form" class="pcw-webhook-form">
				<input type="hidden" name="webhook_id" value="<?php echo esc_attr( $webhook_id ); ?>">
				
				<div class="pcw-form-row">
					<div class="pcw-form-col-8">
						<!-- Card Principal -->
						<div class="pcw-card">
							<div class="pcw-card-header">
								<h2><?php esc_html_e( 'Configuração do Webhook', 'person-cash-wallet' ); ?></h2>
							</div>
							<div class="pcw-card-body">
								<!-- Nome -->
								<div class="pcw-form-group">
									<label for="webhook_name">
										<?php esc_html_e( 'Nome do Webhook', 'person-cash-wallet' ); ?>
										<span class="required">*</span>
									</label>
									<input type="text" id="webhook_name" name="name" class="widefat" 
										value="<?php echo esc_attr( $defaults['name'] ); ?>" 
										placeholder="<?php esc_attr_e( 'Ex: Notificação de Pedido via WhatsApp', 'person-cash-wallet' ); ?>" required>
									<p class="description"><?php esc_html_e( 'Nome descritivo para identificar este webhook', 'person-cash-wallet' ); ?></p>
								</div>

								<!-- Tipo de Webhook -->
								<div class="pcw-form-group">
									<label for="webhook_type">
										<?php esc_html_e( 'Tipo de Webhook', 'person-cash-wallet' ); ?>
										<span class="required">*</span>
									</label>
									<div class="pcw-webhook-types" style="display: flex; gap: 15px; flex-wrap: wrap;">
										<label class="pcw-webhook-type-option <?php echo $defaults['type'] === 'personizi_whatsapp' ? 'active' : ''; ?>" 
											style="flex: 1; min-width: 200px; cursor: pointer; border: 2px solid <?php echo $defaults['type'] === 'personizi_whatsapp' ? '#10b981' : '#e2e8f0'; ?>; border-radius: 8px; padding: 15px; background: <?php echo $defaults['type'] === 'personizi_whatsapp' ? '#ecfdf5' : '#fff'; ?>; transition: all 0.2s;">
											<input type="radio" name="type" value="personizi_whatsapp" 
												<?php checked( $defaults['type'], 'personizi_whatsapp' ); ?> required style="display: none;">
											<div style="display: flex; align-items: center; gap: 12px;">
												<div style="font-size: 32px;">💬</div>
												<div>
													<strong style="display: block; color: #1e293b;"><?php esc_html_e( 'Personizi WhatsApp', 'person-cash-wallet' ); ?></strong>
													<span style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Enviar mensagens WhatsApp', 'person-cash-wallet' ); ?></span>
													<?php if ( $personizi_configured ) : ?>
														<span style="display: block; margin-top: 4px; font-size: 11px; color: #10b981; font-weight: 600;">✓ Configurado</span>
													<?php else : ?>
														<span style="display: block; margin-top: 4px; font-size: 11px; color: #f59e0b; font-weight: 600;">⚠ Não configurado</span>
													<?php endif; ?>
												</div>
											</div>
										</label>

										<label class="pcw-webhook-type-option <?php echo $defaults['type'] === 'custom' ? 'active' : ''; ?>" 
											style="flex: 1; min-width: 200px; cursor: pointer; border: 2px solid <?php echo $defaults['type'] === 'custom' ? '#3b82f6' : '#e2e8f0'; ?>; border-radius: 8px; padding: 15px; background: <?php echo $defaults['type'] === 'custom' ? '#eff6ff' : '#fff'; ?>; transition: all 0.2s;">
											<input type="radio" name="type" value="custom" 
												<?php checked( $defaults['type'], 'custom' ); ?> style="display: none;">
											<div style="display: flex; align-items: center; gap: 12px;">
												<div style="font-size: 32px;">🔗</div>
												<div>
													<strong style="display: block; color: #1e293b;"><?php esc_html_e( 'Webhook Personalizado', 'person-cash-wallet' ); ?></strong>
													<span style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Qualquer API externa', 'person-cash-wallet' ); ?></span>
												</div>
											</div>
										</label>
									</div>
								</div>

								<!-- Evento -->
								<div class="pcw-form-group">
									<label for="webhook_event">
										<?php esc_html_e( 'Quando Disparar', 'person-cash-wallet' ); ?>
										<span class="required">*</span>
									</label>
									<select id="webhook_event" name="event" class="widefat" required>
										<?php $this->render_event_options( $defaults['event'] ); ?>
									</select>
									<p class="description">
										<?php esc_html_e( 'Selecione o evento que irá disparar este webhook. Todos os status de pedidos do WooCommerce estão disponíveis, incluindo os customizados.', 'person-cash-wallet' ); ?>
									</p>
								</div>

								<!-- Configuração Personizi -->
								<div id="personizi-config" style="<?php echo $defaults['type'] === 'personizi_whatsapp' ? '' : 'display: none;'; ?>">
									<?php if ( $personizi_configured ) : ?>
										<div class="pcw-form-group">
											<label><?php esc_html_e( 'Conta WhatsApp', 'person-cash-wallet' ); ?></label>
											<select name="personizi_from" class="widefat">
												<option value="" <?php selected( $personizi_from, '' ); ?>><?php esc_html_e( 'Usar conta padrão', 'person-cash-wallet' ); ?></option>
												<?php foreach ( $personizi_accounts as $account ) : ?>
													<option value="<?php echo esc_attr( $account['phone_number'] ); ?>" <?php selected( $personizi_from, $account['phone_number'] ); ?>>
														<?php echo esc_html( $account['name'] . ' (' . $account['phone_number'] . ')' ); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</div>

										<div class="pcw-form-group">
											<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
												<label style="margin: 0;"><?php esc_html_e( 'Mensagem', 'person-cash-wallet' ); ?></label>
												<?php if ( PCW_OpenAI::instance()->is_configured() ) : ?>
													<button type="button" id="pcw-generate-ai-message" class="button button-small" style="height: 28px;">
														<span class="dashicons dashicons-superhero" style="font-size: 16px; margin-top: 3px;"></span>
														<?php esc_html_e( 'Gerar com IA', 'person-cash-wallet' ); ?>
													</button>
												<?php else : ?>
													<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-settings&tab=ai' ) ); ?>" 
														class="button button-small" style="height: 28px; color: #64748b;" target="_blank">
														<span class="dashicons dashicons-admin-generic" style="font-size: 16px; margin-top: 3px;"></span>
														<?php esc_html_e( 'Configurar IA', 'person-cash-wallet' ); ?>
													</a>
												<?php endif; ?>
											</div>
										<textarea id="personizi_message" name="personizi_message" class="widefat" rows="6" 
											placeholder="<?php esc_attr_e( 'Olá {{customer_first_name}}! Seu pedido #{{order_number}} foi confirmado! 🎉', 'person-cash-wallet' ); ?>"><?php echo esc_textarea( $defaults['body_template'] ); ?></textarea>

										<div class="pcw-form-group" style="margin-top: 16px; padding: 14px; background: #fff8ed; border: 1px solid #f59e0b; border-radius: 6px;">
											<label style="font-weight: 600; color: #92400e; display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
												<span style="font-size: 16px;">📦</span>
												<?php esc_html_e( 'Mensagem quando NÃO há código de rastreio', 'person-cash-wallet' ); ?>
												<span style="font-size: 11px; font-weight: 400; color: #78350f; background: #fef3c7; padding: 2px 6px; border-radius: 10px;"><?php esc_html_e( 'Opcional', 'person-cash-wallet' ); ?></span>
											</label>
											<p class="description" style="margin: 0 0 8px; color: #78350f; font-size: 12px;">
												<?php esc_html_e( 'Usada quando o pedido foi retirado na loja, entrega própria ou enviado sem código de rastreamento. Se vazio, a mensagem principal é usada mesmo sem rastreio.', 'person-cash-wallet' ); ?>
											</p>
											<textarea id="personizi_message_no_tracking" name="personizi_message_no_tracking" class="widefat" rows="4" 
												placeholder="<?php esc_attr_e( 'Olá {{customer_first_name}}! Seu pedido #{{order_number}} foi enviado. Entraremos em contato sobre a entrega. 🚚', 'person-cash-wallet' ); ?>"><?php echo esc_textarea( $defaults['body_template_no_tracking'] ); ?></textarea>
										</div>

										<p class="description" style="margin-top: 10px;">
											<strong><?php esc_html_e( 'Variáveis disponíveis:', 'person-cash-wallet' ); ?></strong>
										</p>
											<div class="pcw-variables-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 8px; font-size: 12px;">
												<div>
													<strong><?php esc_html_e( 'Cliente:', 'person-cash-wallet' ); ?></strong><br>
													<code>{{customer_first_name}}</code><br>
													<code>{{customer_name}}</code><br>
													<code>{{customer_email}}</code><br>
													<code>{{customer_phone}}</code>
												</div>
												<div>
													<strong><?php esc_html_e( 'Pedido:', 'person-cash-wallet' ); ?></strong><br>
													<code>{{order_number}}</code><br>
													<code>{{order_total}}</code><br>
													<code>{{order_status_label}}</code><br>
													<code>{{payment_method}}</code>
												</div>
												<div>
													<strong><?php esc_html_e( 'Links:', 'person-cash-wallet' ); ?></strong><br>
													<code>{{payment_link}}</code><br>
													<code>{{tracking_code}}</code><br>
													<code>{{tracking_url}}</code><br>
													<code>{{site_name}}</code>
												</div>
											</div>
											<p class="description" style="margin-top: 8px;">
												<a href="#pcw-all-variables" class="pcw-show-all-variables" style="color: #0073aa; cursor: pointer;">
													<?php esc_html_e( '📋 Ver todas as variáveis disponíveis', 'person-cash-wallet' ); ?>
												</a>
											</p>
										</div>
									<?php else : ?>
										<div class="notice notice-warning inline">
											<p>
												<strong><?php esc_html_e( 'Personizi não configurado.', 'person-cash-wallet' ); ?></strong>
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-settings&tab=personizi' ) ); ?>" target="_blank">
													<?php esc_html_e( 'Configurar agora', 'person-cash-wallet' ); ?>
												</a>
											</p>
										</div>
									<?php endif; ?>
								</div>

								<!-- Configuração Custom -->
								<div id="custom-config" style="<?php echo $defaults['type'] === 'custom' ? '' : 'display: none;'; ?>">
									<div class="pcw-form-group">
										<label><?php esc_html_e( 'URL', 'person-cash-wallet' ); ?></label>
										<input type="url" name="url" class="widefat" 
											value="<?php echo esc_attr( $defaults['url'] ); ?>" 
											placeholder="https://api.exemplo.com/webhook">
									</div>

									<div class="pcw-form-group">
										<label><?php esc_html_e( 'Método', 'person-cash-wallet' ); ?></label>
										<select name="method" class="widefat">
											<option value="POST" <?php selected( $defaults['method'], 'POST' ); ?>>POST</option>
											<option value="GET" <?php selected( $defaults['method'], 'GET' ); ?>>GET</option>
											<option value="PUT" <?php selected( $defaults['method'], 'PUT' ); ?>>PUT</option>
										</select>
									</div>

									<div class="pcw-form-group">
										<label><?php esc_html_e( 'Body/Payload (JSON)', 'person-cash-wallet' ); ?></label>
										<textarea name="body_template" class="widefat pcw-code" rows="10" 
											placeholder='{"event": "{{event}}", "customer": "{{customer_name}}"}'><?php echo esc_textarea( $defaults['body_template'] ); ?></textarea>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="pcw-form-col-4">
						<!-- Sidebar -->
						<div class="pcw-card">
							<div class="pcw-card-header">
								<h2><?php esc_html_e( 'Publicar', 'person-cash-wallet' ); ?></h2>
							</div>
							<div class="pcw-card-body">
								<div class="pcw-form-group">
									<label><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></label>
									<select name="status" class="widefat">
										<option value="active" <?php selected( $defaults['status'], 'active' ); ?>>
											<?php esc_html_e( 'Ativo', 'person-cash-wallet' ); ?>
										</option>
										<option value="inactive" <?php selected( $defaults['status'], 'inactive' ); ?>>
											<?php esc_html_e( 'Inativo', 'person-cash-wallet' ); ?>
										</option>
									</select>
								</div>

								<button type="submit" class="button button-primary button-large" style="width: 100%; margin-top: 10px;">
									<span class="dashicons dashicons-saved"></span>
									<?php echo $is_edit ? esc_html__( 'Atualizar Webhook', 'person-cash-wallet' ) : esc_html__( 'Criar Webhook', 'person-cash-wallet' ); ?>
								</button>
							</div>
						</div>

						<!-- Card de Teste -->
						<div class="pcw-card" style="margin-top: 20px;">
							<div class="pcw-card-header">
								<h2>
									<span class="dashicons dashicons-admin-tools" style="margin-right: 6px;"></span>
									<?php esc_html_e( 'Testar Webhook', 'person-cash-wallet' ); ?>
								</h2>
							</div>
							<div class="pcw-card-body">
								<!-- Teste para Personizi WhatsApp -->
								<div id="test-personizi" style="<?php echo $defaults['type'] === 'personizi_whatsapp' ? '' : 'display: none;'; ?>">
									<p class="description" style="margin-bottom: 15px;">
										<?php esc_html_e( 'Envie uma mensagem de teste para verificar se a integração está funcionando.', 'person-cash-wallet' ); ?>
									</p>
									<div class="pcw-form-group">
										<label><?php esc_html_e( 'Número WhatsApp', 'person-cash-wallet' ); ?></label>
										<input type="tel" id="test_phone" class="widefat" placeholder="5511999999999" style="font-family: monospace;">
										<p class="description"><?php esc_html_e( 'Com código do país (55)', 'person-cash-wallet' ); ?></p>
									</div>
									<div class="pcw-form-group">
										<label><?php esc_html_e( 'Mensagem de Teste', 'person-cash-wallet' ); ?></label>
										<textarea id="test_message" class="widefat" rows="3" 
											placeholder="Olá! Esta é uma mensagem de teste 🚀">Olá! Esta é uma mensagem de teste do sistema 🚀</textarea>
									</div>
								</div>

								<!-- Teste para Webhook Customizado -->
								<div id="test-custom" style="<?php echo $defaults['type'] === 'custom' ? '' : 'display: none;'; ?>">
									<p class="description" style="margin-bottom: 15px;">
										<?php esc_html_e( 'Envia uma requisição de teste com dados simulados para verificar se a URL está respondendo.', 'person-cash-wallet' ); ?>
									</p>
									<div style="background: #f8fafc; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
										<p style="margin: 0; font-size: 12px; color: #64748b;">
											<strong><?php esc_html_e( 'O que será enviado:', 'person-cash-wallet' ); ?></strong><br>
											<?php esc_html_e( 'Payload JSON com dados de teste baseados no evento selecionado.', 'person-cash-wallet' ); ?>
										</p>
									</div>
								</div>

								<button type="button" id="pcw-test-webhook" class="button button-primary" style="width: 100%; height: 40px; font-size: 14px;">
									<span class="dashicons dashicons-controls-play" style="margin-top: 3px;"></span>
									<?php esc_html_e( 'Enviar Teste', 'person-cash-wallet' ); ?>
								</button>

								<div id="test-result" style="margin-top: 15px;"></div>
							</div>
						</div>

						<!-- Card de Variáveis -->
						<div class="pcw-card" style="margin-top: 20px;" id="pcw-all-variables">
							<div class="pcw-card-header" style="cursor: pointer;" onclick="jQuery('#pcw-variables-content').slideToggle();">
								<h2>
									<span class="dashicons dashicons-info" style="margin-right: 6px;"></span>
									<?php esc_html_e( 'Todas as Variáveis', 'person-cash-wallet' ); ?>
									<span class="dashicons dashicons-arrow-down-alt2" style="float: right;"></span>
								</h2>
							</div>
							<div class="pcw-card-body" id="pcw-variables-content" style="display: none; font-size: 12px;">
								<h4 style="margin-top: 0;"><?php esc_html_e( '👤 Cliente', 'person-cash-wallet' ); ?></h4>
								<code>{{customer_first_name}}</code> - <?php esc_html_e( 'Primeiro nome', 'person-cash-wallet' ); ?><br>
								<code>{{customer_name}}</code> - <?php esc_html_e( 'Nome completo', 'person-cash-wallet' ); ?><br>
								<code>{{customer_email}}</code> - <?php esc_html_e( 'E-mail', 'person-cash-wallet' ); ?><br>
								<code>{{customer_phone}}</code> - <?php esc_html_e( 'Telefone', 'person-cash-wallet' ); ?>

								<h4 style="margin-top: 15px;"><?php esc_html_e( '📦 Pedido', 'person-cash-wallet' ); ?></h4>
								<code>{{order_id}}</code> - <?php esc_html_e( 'ID do pedido', 'person-cash-wallet' ); ?><br>
								<code>{{order_number}}</code> - <?php esc_html_e( 'Número do pedido', 'person-cash-wallet' ); ?><br>
								<code>{{order_total}}</code> - <?php esc_html_e( 'Total formatado', 'person-cash-wallet' ); ?><br>
								<code>{{order_subtotal}}</code> - <?php esc_html_e( 'Subtotal', 'person-cash-wallet' ); ?><br>
								<code>{{order_discount}}</code> - <?php esc_html_e( 'Desconto', 'person-cash-wallet' ); ?><br>
								<code>{{order_shipping}}</code> - <?php esc_html_e( 'Frete', 'person-cash-wallet' ); ?><br>
								<code>{{order_status_label}}</code> - <?php esc_html_e( 'Status', 'person-cash-wallet' ); ?><br>
								<code>{{products_list}}</code> - <?php esc_html_e( 'Lista de produtos', 'person-cash-wallet' ); ?><br>
								<code>{{coupons}}</code> - <?php esc_html_e( 'Cupons usados', 'person-cash-wallet' ); ?>

								<h4 style="margin-top: 15px;"><?php esc_html_e( '💳 Pagamento', 'person-cash-wallet' ); ?></h4>
								<code>{{payment_method}}</code> - <?php esc_html_e( 'Método de pagamento', 'person-cash-wallet' ); ?><br>
								<code>{{payment_link}}</code> - <?php esc_html_e( 'Link de pagamento', 'person-cash-wallet' ); ?>

								<h4 style="margin-top: 15px;"><?php esc_html_e( '🚚 Envio/Rastreio', 'person-cash-wallet' ); ?></h4>
								<code>{{shipping_method}}</code> - <?php esc_html_e( 'Método de envio', 'person-cash-wallet' ); ?><br>
								<code>{{tracking_code}}</code> - <?php esc_html_e( 'Código de rastreio', 'person-cash-wallet' ); ?><br>
								<code>{{tracking_codes}}</code> - <?php esc_html_e( 'Todos os códigos', 'person-cash-wallet' ); ?><br>
								<code>{{tracking_url}}</code> - <?php esc_html_e( 'URL de rastreio', 'person-cash-wallet' ); ?><br>
								<code>{{shipping_address}}</code> - <?php esc_html_e( 'Endereço de entrega', 'person-cash-wallet' ); ?>

								<h4 style="margin-top: 15px;"><?php esc_html_e( '📅 Datas', 'person-cash-wallet' ); ?></h4>
								<code>{{order_date}}</code> - <?php esc_html_e( 'Data do pedido', 'person-cash-wallet' ); ?><br>
								<code>{{departure_date}}</code> - <?php esc_html_e( 'Data de saída', 'person-cash-wallet' ); ?><br>
								<code>{{delivery_date}}</code> - <?php esc_html_e( 'Data de entrega', 'person-cash-wallet' ); ?>

								<h4 style="margin-top: 15px;"><?php esc_html_e( '🏪 Loja', 'person-cash-wallet' ); ?></h4>
								<code>{{site_name}}</code> - <?php esc_html_e( 'Nome da loja', 'person-cash-wallet' ); ?><br>
								<code>{{site_url}}</code> - <?php esc_html_e( 'URL da loja', 'person-cash-wallet' ); ?>

								<h4 style="margin-top: 15px;"><?php esc_html_e( '📝 Observações', 'person-cash-wallet' ); ?></h4>
								<code>{{order_notes}}</code> - <?php esc_html_e( 'Observações do cliente', 'person-cash-wallet' ); ?><br>
								<code>{{budget_notes}}</code> - <?php esc_html_e( 'Observações do orçamento', 'person-cash-wallet' ); ?>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			console.log('[PCW Inline] Ready');
			
			// Link "Ver todas as variáveis"
			$('.pcw-show-all-variables').on('click', function(e) {
				e.preventDefault();
				$('#pcw-variables-content').slideDown(200);
				$('html, body').animate({
					scrollTop: $('#pcw-all-variables').offset().top - 50
				}, 300);
			});
			
			// Highlight tipo selecionado
			$('.pcw-webhook-type-option').on('click', function() {
				$('.pcw-webhook-type-option').css({
					'border-color': '#e2e8f0',
					'background': '#fff'
				});
				$(this).css({
					'border-color': $(this).find('input').val() === 'personizi_whatsapp' ? '#10b981' : '#3b82f6',
					'background': $(this).find('input').val() === 'personizi_whatsapp' ? '#ecfdf5' : '#eff6ff'
				});
				$(this).find('input').prop('checked', true).trigger('change');
			});

			// Alternar configurações baseado no tipo
			$('input[name="type"]').on('change', function() {
				var type = $(this).val();
				if (type === 'personizi_whatsapp') {
					$('#personizi-config').slideDown(200);
					$('#custom-config').slideUp(200);
					$('#test-personizi').slideDown(200);
					$('#test-custom').slideUp(200);
				} else {
					$('#personizi-config').slideUp(200);
					$('#custom-config').slideDown(200);
					$('#test-personizi').slideUp(200);
					$('#test-custom').slideDown(200);
				}
			});
			
			// Gerar mensagem com IA
			$('#pcw-generate-ai-message').on('click', function(e) {
				e.preventDefault();
				console.log('[PCW] Generate AI message clicked');
				
				var $btn = $(this);
				var $textarea = $('#personizi_message');
				var event = $('select[name="event"]').val();
				var webhookName = $('input[name="name"]').val();
				
				if (!event) {
					alert('<?php esc_html_e( 'Selecione um evento primeiro', 'person-cash-wallet' ); ?>');
					return;
				}
				
				// Mostrar loading
				var originalText = $btn.html();
				$btn.html('<span class="dashicons dashicons-update spin"></span> Gerando...').prop('disabled', true);
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'pcw_generate_webhook_message',
						nonce: '<?php echo wp_create_nonce( 'pcw_webhooks' ); ?>',
						event: event,
						webhook_name: webhookName
					},
					success: function(response) {
						console.log('[PCW] AI Response:', response);
						if (response.success) {
							$textarea.val(response.data.message);
							// Fazer o textarea "pulsar" para mostrar que foi atualizado
							$textarea.css('background-color', '#ecfdf5');
							setTimeout(function() {
								$textarea.css('background-color', '');
							}, 1000);
						} else {
							alert('<?php esc_html_e( 'Erro:', 'person-cash-wallet' ); ?> ' + (response.data && response.data.message ? response.data.message : 'Erro desconhecido'));
						}
					},
					error: function(xhr, status, error) {
						console.error('[PCW] AI Error:', error);
						alert('<?php esc_html_e( 'Erro ao gerar mensagem', 'person-cash-wallet' ); ?>');
					},
					complete: function() {
						$btn.html(originalText).prop('disabled', false);
					}
				});
			});
			
			// FALLBACK: Botão de teste inline
			$('#pcw-test-webhook').on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				console.log('[PCW Inline] Test button clicked');
				
				var $btn = $(this);
				var $result = $('#test-result');
				var type = $('input[name="type"]:checked').val();
				var event = $('select[name="event"]').val();
				
				if (!type) {
					$result.html('<div class="notice notice-error inline"><p>Selecione um tipo de webhook primeiro</p></div>');
					return;
				}
				
				// Mostrar loading
				var originalText = $btn.html();
				$btn.html('<span class="dashicons dashicons-update spin"></span> Enviando...').prop('disabled', true);
				
				var data = {
					action: 'pcw_test_webhook_send',
					nonce: '<?php echo wp_create_nonce( 'pcw_webhooks' ); ?>',
					type: type,
					event: event
				};
				
				if (type === 'personizi_whatsapp') {
					var phone = $('#test_phone').val();
					var message = $('#test_message').val();
					var from = $('select[name="personizi_from"]').val();
					
					if (!phone) {
						$result.html('<div class="notice notice-error inline"><p>Digite o número de telefone para teste</p></div>');
						$btn.html(originalText).prop('disabled', false);
						return;
					}
					
					if (!message) {
						$result.html('<div class="notice notice-error inline"><p>Digite uma mensagem de teste</p></div>');
						$btn.html(originalText).prop('disabled', false);
						return;
					}
					
					data.test_phone = phone;
					data.test_message = message;
					if (from) data.from = from;
				} else {
					// Webhook customizado
					var url = $('input[name="url"]').val();
					var method = $('select[name="method"]').val();
					
					if (!url) {
						$result.html('<div class="notice notice-error inline"><p>Digite a URL do webhook</p></div>');
						$btn.html(originalText).prop('disabled', false);
						return;
					}
					
					data.url = url;
					data.method = method;
				}
				
				console.log('[PCW Inline] Sending data:', data);
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: data,
					success: function(response) {
						console.log('[PCW Inline] Response:', response);
						if (response.success) {
							$result.html('<div class="notice notice-success inline" style="border-left-color: #10b981;"><p>✅ ' + response.data.message + '</p></div>');
						} else {
							$result.html('<div class="notice notice-error inline"><p>❌ ' + (response.data && response.data.message ? response.data.message : 'Erro desconhecido') + '</p></div>');
						}
					},
					error: function(xhr, status, error) {
						console.error('[PCW Inline] Error:', xhr.responseText);
						$result.html('<div class="notice notice-error inline"><p>❌ Erro na requisição: ' + error + '</p></div>');
					},
					complete: function() {
						$btn.html(originalText).prop('disabled', false);
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Renderizar opções de eventos
	 */
	private function render_event_options( $selected_event ) {
		// Eventos de pedidos - buscar todos os status do WooCommerce
		if ( function_exists( 'wc_get_order_statuses' ) ) {
			$order_statuses = wc_get_order_statuses();
			
			echo '<optgroup label="' . esc_attr__( 'Status de Pedidos', 'person-cash-wallet' ) . '">';
			
			foreach ( $order_statuses as $status_key => $status_label ) {
				// Remover prefixo wc- se tiver
				$clean_key = str_replace( 'wc-', '', $status_key );
				$event_value = 'order_' . $clean_key;
				
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $event_value ),
					selected( $selected_event, $event_value, false ),
					esc_html( $status_label )
				);
			}
			
			echo '</optgroup>';
		}
		
		// Eventos de cashback
		?>
		<optgroup label="<?php esc_attr_e( 'Cashback', 'person-cash-wallet' ); ?>">
			<option value="cashback_earned" <?php selected( $selected_event, 'cashback_earned' ); ?>>
				<?php esc_html_e( 'Cashback Ganho', 'person-cash-wallet' ); ?>
			</option>
			<option value="cashback_redeemed" <?php selected( $selected_event, 'cashback_redeemed' ); ?>>
				<?php esc_html_e( 'Cashback Resgatado', 'person-cash-wallet' ); ?>
			</option>
		</optgroup>
		
		<!-- Eventos de rastreio -->
		<optgroup label="<?php esc_attr_e( 'Rastreio / Tracking', 'person-cash-wallet' ); ?>">
			<option value="order_tracking_added" <?php selected( $selected_event, 'order_tracking_added' ); ?>>
				<?php esc_html_e( 'Código de Rastreio Adicionado', 'person-cash-wallet' ); ?>
			</option>
		</optgroup>

		<!-- Eventos de usuários -->
		<optgroup label="<?php esc_attr_e( 'Usuários', 'person-cash-wallet' ); ?>">
			<option value="user_registered" <?php selected( $selected_event, 'user_registered' ); ?>>
				<?php esc_html_e( 'Novo Usuário Registrado', 'person-cash-wallet' ); ?>
			</option>
			<option value="level_achieved" <?php selected( $selected_event, 'level_achieved' ); ?>>
				<?php esc_html_e( 'Nível VIP Alcançado', 'person-cash-wallet' ); ?>
			</option>
		</optgroup>
		<?php
	}

	/**
	 * Get event label
	 */
	private function get_event_label( $event ) {
		// Verificar se é um evento de pedido
		if ( strpos( $event, 'order_' ) === 0 && function_exists( 'wc_get_order_statuses' ) ) {
			$status_key = str_replace( 'order_', '', $event );
			$order_statuses = wc_get_order_statuses();
			
			// Tentar com e sem prefixo wc-
			if ( isset( $order_statuses[ 'wc-' . $status_key ] ) ) {
				return $order_statuses[ 'wc-' . $status_key ];
			}
			if ( isset( $order_statuses[ $status_key ] ) ) {
				return $order_statuses[ $status_key ];
			}
		}
		
		// Labels padrão para outros eventos
		$labels = array(
			'cashback_earned'       => __( 'Cashback Ganho', 'person-cash-wallet' ),
			'cashback_redeemed'     => __( 'Cashback Resgatado', 'person-cash-wallet' ),
			'user_registered'       => __( 'Novo Usuário Registrado', 'person-cash-wallet' ),
			'level_achieved'        => __( 'Nível VIP Alcançado', 'person-cash-wallet' ),
			'order_tracking_added'  => __( 'Código de Rastreio Adicionado', 'person-cash-wallet' ),
		);

		return isset( $labels[ $event ] ) ? $labels[ $event ] : ucfirst( str_replace( '_', ' ', $event ) );
	}

	/**
	 * AJAX: Salvar webhook
	 */
	public function ajax_save_webhook() {
		$this->log_debug( '━━━━━ AJAX: ajax_save_webhook ━━━━━' );
		$this->log_debug( 'POST data: ' . wp_json_encode( $_POST, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		
		check_ajax_referer( 'pcw_webhooks', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			$this->log_debug( 'Erro: Sem permissão' );
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_webhooks';

		// Verificar se tabela existe
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
		if ( ! $table_exists ) {
			$this->log_debug( 'Tabela não existe, criando...' );
			$this->create_webhooks_table();
		}

		$webhook_id = isset( $_POST['webhook_id'] ) ? absint( $_POST['webhook_id'] ) : 0;
		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'personizi_whatsapp';

		$this->log_debug( 'Webhook ID: ' . $webhook_id );
		$this->log_debug( 'Type: ' . $type );

		// Preparar dados baseado no tipo
		if ( $type === 'personizi_whatsapp' ) {
			$personizi = PCW_Personizi_Integration::instance();
			
			$data = array(
				'name'                      => isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '',
				'type'                      => $type,
				'event'                     => isset( $_POST['event'] ) ? sanitize_text_field( $_POST['event'] ) : '',
				'url'                       => 'https://chat.personizi.com.br/api/v1/messages/send',
				'method'                    => 'POST',
				'auth_type'                 => 'bearer',
				'auth_token'                => $personizi->get_api_token(),
				'body_template'             => isset( $_POST['personizi_message'] ) ? sanitize_textarea_field( $_POST['personizi_message'] ) : '',
				'body_template_no_tracking' => isset( $_POST['personizi_message_no_tracking'] ) ? sanitize_textarea_field( $_POST['personizi_message_no_tracking'] ) : '',
				'status'                    => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'active',
			);

			$from = isset( $_POST['personizi_from'] ) ? sanitize_text_field( $_POST['personizi_from'] ) : '';
			if ( $from ) {
				$data['headers'] = wp_json_encode( array( 'from' => $from ) );
			}
		} else {
			$data = array(
				'name'          => isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '',
				'type'          => $type,
				'event'         => isset( $_POST['event'] ) ? sanitize_text_field( $_POST['event'] ) : '',
				'url'           => isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '',
				'method'        => isset( $_POST['method'] ) ? sanitize_text_field( $_POST['method'] ) : 'POST',
				'auth_type'     => isset( $_POST['auth_type'] ) ? sanitize_text_field( $_POST['auth_type'] ) : 'none',
				'auth_token'    => isset( $_POST['auth_token'] ) ? sanitize_text_field( $_POST['auth_token'] ) : '',
				'headers'       => isset( $_POST['headers'] ) ? sanitize_textarea_field( $_POST['headers'] ) : '',
				'body_template' => isset( $_POST['body_template'] ) ? sanitize_textarea_field( $_POST['body_template'] ) : '',
				'status'        => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'active',
			);
		}

		// Validação
		if ( empty( $data['name'] ) ) {
			$this->log_debug( 'Erro: Nome vazio' );
			wp_send_json_error( array( 'message' => __( 'Nome do webhook é obrigatório', 'person-cash-wallet' ) ) );
		}

		if ( empty( $data['event'] ) ) {
			$this->log_debug( 'Erro: Evento vazio' );
			wp_send_json_error( array( 'message' => __( 'Selecione um evento', 'person-cash-wallet' ) ) );
		}

		$this->log_debug( 'Dados a serem salvos: ' . wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

		if ( $webhook_id > 0 ) {
			$this->log_debug( 'Atualizando webhook existente: ' . $webhook_id );
			$result = $wpdb->update( $table, $data, array( 'id' => $webhook_id ) );
			$this->log_debug( 'Resultado do UPDATE: ' . var_export( $result, true ) );
			$this->log_debug( 'Type of result: ' . gettype( $result ) );
		} else {
			$this->log_debug( 'Inserindo novo webhook' );
			$result = $wpdb->insert( $table, $data );
			$this->log_debug( 'Resultado do INSERT: ' . var_export( $result, true ) );
			$this->log_debug( 'Type of result: ' . gettype( $result ) );
			
			if ( $result !== false ) {
				$webhook_id = $wpdb->insert_id;
				$this->log_debug( 'Novo webhook ID: ' . $webhook_id );
			}
		}

		// Para INSERT: false = erro, número > 0 = sucesso
		// Para UPDATE: false = erro, 0 = nenhuma linha afetada (mas não é erro), número > 0 = linhas atualizadas
		if ( $result === false ) {
			$error = $wpdb->last_error;
			$this->log_debug( '❌ ERRO NO BANCO DE DADOS' );
			$this->log_debug( 'wpdb->last_error: ' . $error );
			$this->log_debug( 'wpdb->last_query: ' . $wpdb->last_query );
			$this->log_debug( 'wpdb->insert_id: ' . $wpdb->insert_id );
			$this->log_debug( 'Table: ' . $table );
			
			// Verificar se a tabela existe
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
			$this->log_debug( 'Table exists check: ' . var_export( $table_exists, true ) );
			
			// Descrever estrutura da tabela
			$table_structure = $wpdb->get_results( "DESCRIBE {$table}" );
			$this->log_debug( 'Table structure: ' . wp_json_encode( $table_structure, JSON_PRETTY_PRINT ) );
			
			// Verificar se o erro é de campo desconhecido (estrutura antiga)
			$error_message = __( 'Erro ao salvar webhook no banco de dados', 'person-cash-wallet' );
			$details = $error ? $error : __( 'Nenhum erro reportado pelo MySQL', 'person-cash-wallet' );
			
			if ( $error && ( strpos( $error, 'Unknown column' ) !== false || strpos( $error, 'Coluna desconhecida' ) !== false ) ) {
				$error_message = __( 'A estrutura da tabela de webhooks está desatualizada', 'person-cash-wallet' );
				$details = __( 'A tabela será recriada automaticamente. Por favor, tente salvar novamente.', 'person-cash-wallet' );
				
				// Forçar recriação da tabela
				$this->log_debug( 'Forçando recriação da tabela devido a erro de estrutura' );
				$this->create_webhooks_table();
			}
			
			wp_send_json_error( array( 
				'message' => $error_message,
				'details' => $details,
				'query'   => $wpdb->last_query,
				'note'    => __( 'Verifique os logs em Person Cash Wallet > Logs > [Plugin] Webhooks Debug para mais detalhes', 'person-cash-wallet' )
			) );
		}

		// Se for INSERT e não tiver ID, erro
		if ( $webhook_id == 0 && $result !== false ) {
			$this->log_debug( '⚠️ INSERT bem-sucedido mas sem ID retornado' );
			wp_send_json_error( array(
				'message' => __( 'Webhook inserido mas ID não foi retornado', 'person-cash-wallet' ),
				'details' => 'insert_id = ' . $wpdb->insert_id
			) );
		}

		$this->log_debug( '✅ Webhook salvo com sucesso! ID: ' . $webhook_id );

		wp_send_json_success( array(
			'message'    => __( 'Webhook salvo com sucesso!', 'person-cash-wallet' ),
			'webhook_id' => $webhook_id,
			'redirect'   => admin_url( 'admin.php?page=pcw-webhooks' ),
		) );
	}

	/**
	 * AJAX: Deletar webhook
	 */
	public function ajax_delete_webhook() {
		check_ajax_referer( 'pcw_webhooks', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$webhook_id = isset( $_POST['webhook_id'] ) ? absint( $_POST['webhook_id'] ) : 0;

		if ( ! $webhook_id ) {
			wp_send_json_error( array( 'message' => __( 'ID inválido', 'person-cash-wallet' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_webhooks';
		$result = $wpdb->delete( $table, array( 'id' => $webhook_id ) );

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => __( 'Erro ao deletar webhook', 'person-cash-wallet' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Webhook deletado com sucesso!', 'person-cash-wallet' ) ) );
	}

	/**
	 * AJAX: Testar webhook
	 */
	public function ajax_test_webhook_send() {
		// Log de início
		$this->log_debug( '━━━━━ AJAX: ajax_test_webhook_send ━━━━━' );
		$this->log_debug( 'POST data: ' . wp_json_encode( $_POST, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		
		check_ajax_referer( 'pcw_webhooks', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			$this->log_debug( 'Erro: Sem permissão' );
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		$this->log_debug( 'Type: ' . $type );

		if ( $type === 'personizi_whatsapp' ) {
			$test_phone = isset( $_POST['test_phone'] ) ? sanitize_text_field( $_POST['test_phone'] ) : '';
			$test_message = isset( $_POST['test_message'] ) ? sanitize_textarea_field( $_POST['test_message'] ) : '';
			$from = isset( $_POST['from'] ) ? sanitize_text_field( $_POST['from'] ) : '';

			$this->log_debug( 'Phone: ' . $test_phone );
			$this->log_debug( 'Message: ' . $test_message );
			$this->log_debug( 'From: ' . $from );

			if ( empty( $test_phone ) || empty( $test_message ) ) {
				$this->log_debug( 'Erro: Campos vazios' );
				wp_send_json_error( array( 'message' => __( 'Preencha o número e a mensagem', 'person-cash-wallet' ) ) );
			}

			$personizi = PCW_Personizi_Integration::instance();
			$this->log_debug( 'Personizi instance criado' );
			
			$result = $personizi->send_whatsapp_message( $test_phone, $test_message, 'Teste Webhook', $from );
			
			$this->log_debug( 'Resultado: ' . wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

			if ( is_wp_error( $result ) ) {
				$this->log_debug( 'WP_Error: ' . $result->get_error_message() );
				$this->log_debug( 'Error Code: ' . $result->get_error_code() );
				$this->log_debug( 'Error Data: ' . wp_json_encode( $result->get_error_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			// Verificar se o resultado tem a estrutura esperada
			$message_id = isset( $result['message_id'] ) ? $result['message_id'] : ( isset( $result['data']['message_id'] ) ? $result['data']['message_id'] : 'N/A' );
			$status = isset( $result['status'] ) ? $result['status'] : ( isset( $result['data']['status'] ) ? $result['data']['status'] : 'N/A' );
			
			$this->log_debug( 'Sucesso! Message ID: ' . $message_id . ', Status: ' . $status );

			wp_send_json_success( array(
				'message' => sprintf(
					__( 'Mensagem enviada! ID: %s | Status: %s', 'person-cash-wallet' ),
					$message_id,
					$status
				),
				'data' => $result,
			) );
		} elseif ( $type === 'custom' ) {
			// Teste de webhook customizado
			$url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';
			$event = isset( $_POST['event'] ) ? sanitize_text_field( $_POST['event'] ) : '';
			$method = isset( $_POST['method'] ) ? strtoupper( sanitize_text_field( $_POST['method'] ) ) : 'POST';
			$headers_raw = isset( $_POST['headers'] ) ? sanitize_textarea_field( $_POST['headers'] ) : '';

			if ( empty( $url ) ) {
				wp_send_json_error( array( 'message' => __( 'URL do webhook não pode estar vazia', 'person-cash-wallet' ) ) );
			}

			// Parse headers
			$headers = array();
			if ( ! empty( $headers_raw ) ) {
				$lines = explode( "\n", $headers_raw );
				foreach ( $lines as $line ) {
					$line = trim( $line );
					if ( empty( $line ) || strpos( $line, ':' ) === false ) {
						continue;
					}
					list( $key, $value ) = explode( ':', $line, 2 );
					$headers[ trim( $key ) ] = trim( $value );
				}
			}

			// Adicionar Content-Type se não tiver
			if ( ! isset( $headers['Content-Type'] ) ) {
				$headers['Content-Type'] = 'application/json';
			}

			// Preparar payload de teste baseado no evento
			$test_payload = $this->get_test_payload_for_event( $event );

			// Fazer requisição
			$args = array(
				'method'  => $method,
				'headers' => $headers,
				'timeout' => 30,
			);

			if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
				$args['body'] = wp_json_encode( $test_payload );
			}

			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				wp_send_json_error( array( 
					'message' => __( 'Erro ao enviar requisição: ', 'person-cash-wallet' ) . $response->get_error_message(),
				) );
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			$success = $response_code >= 200 && $response_code < 300;

			if ( $success ) {
				wp_send_json_success( array(
					'message'       => sprintf( __( 'Webhook testado com sucesso! Código HTTP: %d', 'person-cash-wallet' ), $response_code ),
					'response_code' => $response_code,
					'response_body' => substr( $response_body, 0, 500 ),
				) );
			} else {
				wp_send_json_error( array(
					'message'       => sprintf( __( 'Webhook retornou erro. Código HTTP: %d', 'person-cash-wallet' ), $response_code ),
					'response_code' => $response_code,
					'response_body' => substr( $response_body, 0, 500 ),
				) );
			}
		}

		wp_send_json_error( array( 'message' => __( 'Tipo de webhook não suportado para teste', 'person-cash-wallet' ) ) );
	}

	/**
	 * Obter payload de teste para um evento
	 *
	 * @param string $event Evento.
	 * @return array
	 */
	private function get_test_payload_for_event( $event ) {
		$timestamp = current_time( 'mysql' );
		
		// Verificar se é evento de pedido
		if ( strpos( $event, 'order_' ) === 0 ) {
			$status = str_replace( 'order_', '', $event );
			return array(
				'event'      => $event,
				'order_id'   => 12345,
				'order_key'  => 'wc_test_12345',
				'order_number' => '#12345',
				'total'      => '149.90',
				'subtotal'   => '125.90',
				'tax'        => '18.00',
				'shipping'   => '6.00',
				'status'     => $status,
				'payment_method' => 'bacs',
				'payment_method_title' => 'Transferência bancária',
				'currency'   => 'BRL',
				'customer'   => array(
					'id'         => 1,
					'first_name' => 'João',
					'last_name'  => 'Silva',
					'name'       => 'João Silva',
					'email'      => 'joao.silva@exemplo.com',
					'phone'      => '11999999999',
				),
				'items'      => array(
					array(
						'id'       => 1,
						'name'     => 'Produto Teste',
						'quantity' => 2,
						'price'    => '62.95',
						'total'    => '125.90',
					),
				),
				'shipping_address' => array(
					'address_1' => 'Rua Teste, 123',
					'address_2' => 'Apto 45',
					'city'      => 'São Paulo',
					'state'     => 'SP',
					'postcode'  => '01234-567',
					'country'   => 'BR',
				),
				'site_name'  => get_bloginfo( 'name' ),
				'site_url'   => home_url(),
				'timestamp'  => $timestamp,
			);
		}
		
		// Payloads específicos para outros eventos
		$payloads = array(
			'cashback_earned' => array(
				'event'           => 'cashback_earned',
				'cashback_id'     => 123,
				'user_id'         => 1,
				'order_id'        => 12345,
				'amount'          => '14.99',
				'expires_date'    => date( 'Y-m-d', strtotime( '+30 days' ) ),
				'customer'        => array(
					'id'    => 1,
					'name'  => 'João Silva',
					'email' => 'joao.silva@exemplo.com',
				),
				'timestamp'       => $timestamp,
			),
			'cashback_redeemed' => array(
				'event'           => 'cashback_redeemed',
				'redemption_id'   => 456,
				'user_id'         => 1,
				'amount'          => '50.00',
				'customer'        => array(
					'id'    => 1,
					'name'  => 'João Silva',
					'email' => 'joao.silva@exemplo.com',
				),
				'timestamp'       => $timestamp,
			),
			'user_registered' => array(
				'event'     => 'user_registered',
				'user_id'   => 123,
				'user_login' => 'joaosilva',
				'user_email' => 'joao.silva@exemplo.com',
				'first_name' => 'João',
				'last_name'  => 'Silva',
				'timestamp' => $timestamp,
			),
			'level_achieved' => array(
				'event'     => 'level_achieved',
				'user_id'   => 1,
				'level_id'  => 2,
				'level_name' => 'VIP Gold',
				'customer'  => array(
					'id'    => 1,
					'name'  => 'João Silva',
					'email' => 'joao.silva@exemplo.com',
				),
				'timestamp' => $timestamp,
			),
		);

		// Retornar payload específico ou genérico
		return isset( $payloads[ $event ] ) ? $payloads[ $event ] : array(
			'event'     => $event,
			'test'      => true,
			'message'   => 'Payload de teste',
			'timestamp' => $timestamp,
		);
	}

	/**
	 * AJAX: Gerar mensagem de webhook com IA
	 */
	public function ajax_generate_webhook_message() {
		check_ajax_referer( 'pcw_webhooks', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$event = isset( $_POST['event'] ) ? sanitize_text_field( $_POST['event'] ) : '';
		$webhook_name = isset( $_POST['webhook_name'] ) ? sanitize_text_field( $_POST['webhook_name'] ) : '';

		if ( empty( $event ) ) {
			wp_send_json_error( array( 'message' => __( 'Evento não especificado', 'person-cash-wallet' ) ) );
		}

		$ai = PCW_OpenAI::instance();

		if ( ! $ai->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'IA não configurada. Configure em Configurações > IA', 'person-cash-wallet' ) ) );
		}

		// Obter label do evento
		$event_label = $this->get_event_label( $event );
		
		// Obter nome da loja
		$site_name = get_bloginfo( 'name' );

		// Montar contexto baseado no evento
		$context = $this->build_ai_context_for_event( $event, $event_label );

		// Prompt para a IA
		$system_prompt = "Você é um especialista em criar mensagens profissionais e amigáveis para WhatsApp Business. " .
			"Sua tarefa é criar mensagens curtas (máximo 150 caracteres), claras e que mantenham um tom amigável mas profissional. " .
			"A mensagem deve ser em português do Brasil. " .
			"Use emojis quando apropriado, mas com moderação (máximo 2 emojis). " .
			"A mensagem deve ser para a loja '{$site_name}'.";

		// Montar variáveis relevantes para o tipo de evento
		$variables_for_event = $this->get_variables_for_event( $event );

		$user_prompt = "Crie uma mensagem de WhatsApp para o seguinte cenário:\n\n" .
			"Evento: {$event_label}\n" .
			"Contexto: {$context}\n\n" .
			"VARIÁVEIS DISPONÍVEIS (use as mais relevantes para este evento):\n" .
			$variables_for_event . "\n\n" .
			"DICAS:\n" .
			"- Para pedido pendente: use {{payment_link}} para enviar o link de pagamento\n" .
			"- Para pedido enviado: use {{tracking_code}} e {{tracking_url}} para rastreio\n" .
			"- Para confirmação: use {{order_total}} e {{payment_method}}\n\n" .
			"Exemplo: \"Olá {{customer_first_name}}! Seu pedido #{{order_number}} foi enviado! 🚚 Rastreie: {{tracking_url}}\"\n\n" .
			"Retorne APENAS a mensagem, sem explicações adicionais.";

		$this->log_debug( 'Gerando mensagem com IA para evento: ' . $event );
		$this->log_debug( 'System Prompt: ' . $system_prompt );
		$this->log_debug( 'User Prompt: ' . $user_prompt );

		$message = $ai->generate_text( $user_prompt, $system_prompt, array( 'max_tokens' => 200, 'temperature' => 0.8 ) );

		if ( is_wp_error( $message ) ) {
			$this->log_debug( 'Erro na IA: ' . $message->get_error_message() );
			wp_send_json_error( array( 'message' => $message->get_error_message() ) );
		}

		$this->log_debug( 'Mensagem gerada: ' . $message );

		wp_send_json_success( array(
			'message' => trim( $message ),
		) );
	}

	/**
	 * Construir contexto para IA baseado no evento
	 */
	private function build_ai_context_for_event( $event, $event_label ) {
		// Contextos específicos baseado no tipo de evento
		if ( strpos( $event, 'order_' ) === 0 ) {
			$status = str_replace( 'order_', '', $event );
			
			$contexts = array(
				'pending'          => 'O pedido foi criado mas ainda está aguardando pagamento. Informe o cliente que estamos aguardando a confirmação.',
				'processing'       => 'O pedido foi pago e está sendo preparado para envio. Tranquilize o cliente que está tudo certo.',
				'on-hold'          => 'O pedido está em espera, possivelmente aguardando confirmação de pagamento ou estoque.',
				'completed'        => 'O pedido foi concluído com sucesso! Agradeça ao cliente e pergunte se está tudo certo.',
				'cancelled'        => 'O pedido foi cancelado. Seja empático e ofereça ajuda se necessário.',
				'refunded'         => 'O pedido foi reembolsado. Confirme ao cliente que o valor será devolvido.',
				'failed'           => 'Houve um problema com o pedido. Seja empático e ofereça suporte.',
				'shipped'          => 'O pedido foi enviado! Informe o código de rastreio e link para o cliente acompanhar.',
				'enviado'          => 'O pedido foi enviado! Informe o código de rastreio e link para o cliente acompanhar.',
				'despachado'       => 'O pedido foi despachado! Informe o código de rastreio e link para o cliente acompanhar.',
			);
			
			return isset( $contexts[ $status ] ) ? $contexts[ $status ] : 'O status do pedido foi alterado para: ' . $event_label;
		}

		$contexts = array(
			'cashback_earned'      => 'O cliente ganhou cashback! Parabenize e explique que o valor pode ser usado na próxima compra.',
			'cashback_redeemed'    => 'O cliente resgatou seu cashback. Confirme o resgate e agradeça.',
			'user_registered'      => 'Um novo cliente se cadastrou. Dê as boas-vindas de forma calorosa.',
			'level_achieved'       => 'O cliente alcançou um novo nível VIP. Parabenize e explique os benefícios.',
			'order_tracking_added' => 'Um código de rastreio foi adicionado ao pedido. Informe o link de rastreamento para o cliente acompanhar a entrega.',
		);

		return isset( $contexts[ $event ] ) ? $contexts[ $event ] : 'Notifique o cliente sobre: ' . $event_label;
	}

	/**
	 * Obter variáveis relevantes para cada tipo de evento
	 *
	 * @param string $event Evento.
	 * @return string Lista formatada de variáveis.
	 */
	private function get_variables_for_event( $event ) {
		// Variáveis comuns a todos os eventos
		$common = array(
			'{{customer_first_name}}' => 'primeiro nome do cliente',
			'{{customer_name}}'       => 'nome completo',
			'{{site_name}}'           => 'nome da loja',
		);

		// Variáveis específicas por tipo de evento
		if ( strpos( $event, 'order_' ) === 0 ) {
			$status = str_replace( 'order_', '', $event );
			
			// Variáveis base de pedido
			$order_vars = array(
				'{{order_number}}'       => 'número do pedido',
				'{{order_total}}'        => 'valor total (R$ 149,90)',
				'{{order_status_label}}' => 'status do pedido',
				'{{payment_method}}'     => 'método de pagamento',
				'{{order_date}}'         => 'data do pedido',
			);

			// Variáveis específicas por status
			$status_specific = array();

			switch ( $status ) {
				case 'pending':
				case 'on-hold':
					$status_specific = array(
						'{{payment_link}}' => '⭐ LINK DE PAGAMENTO (importante!)',
						'{{order_subtotal}}' => 'subtotal',
					);
					break;

				case 'processing':
					$status_specific = array(
						'{{departure_date}}' => 'data prevista de saída',
						'{{delivery_date}}'  => 'data prevista de entrega',
						'{{products_list}}'  => 'lista de produtos',
					);
					break;

				case 'completed':
				case 'shipped':
				case 'enviado':
				case 'despachado':
					$status_specific = array(
						'{{tracking_code}}'  => '⭐ CÓDIGO DE RASTREIO',
						'{{tracking_url}}'   => '⭐ LINK DE RASTREIO',
						'{{tracking_codes}}' => 'todos os códigos de rastreio',
						'{{tracking_urls}}'  => 'todos os links de rastreio',
						'{{shipping_method}}' => 'método de envio',
						'{{delivery_date}}'  => 'data prevista de entrega',
					);
					break;

				case 'cancelled':
				case 'refunded':
				case 'failed':
					$status_specific = array(
						'{{order_notes}}' => 'observações do cliente',
					);
					break;
			}

			$all_vars = array_merge( $common, $order_vars, $status_specific );
		} elseif ( $event === 'order_tracking_added' ) {
			$all_vars = array_merge( $common, array(
				'{{order_number}}'    => 'número do pedido',
				'{{order_total}}'     => 'valor total (R$ 149,90)',
				'{{tracking_code}}'   => '⭐ CÓDIGO DE RASTREIO',
				'{{tracking_url}}'    => '⭐ LINK DE RASTREIO',
				'{{tracking_codes}}'  => 'todos os códigos de rastreio',
				'{{tracking_urls}}'   => 'todos os links de rastreio',
				'{{shipping_method}}' => 'método de envio',
				'{{delivery_date}}'   => 'data prevista de entrega',
			) );
		} elseif ( $event === 'cashback_earned' || $event === 'cashback_redeemed' ) {
			$all_vars = array_merge( $common, array(
				'{{cashback_amount}}' => 'valor do cashback',
				'{{order_number}}'    => 'número do pedido',
			) );
		} elseif ( $event === 'level_achieved' ) {
			$all_vars = array_merge( $common, array(
				'{{level_name}}' => 'nome do nível VIP',
			) );
		} elseif ( $event === 'user_registered' ) {
			$all_vars = $common;
		} else {
			// Evento genérico - mostrar todas as principais
			$all_vars = array_merge( $common, array(
				'{{order_number}}'   => 'número do pedido',
				'{{order_total}}'    => 'valor total',
				'{{payment_link}}'   => 'link de pagamento',
				'{{tracking_code}}'  => 'código de rastreio',
				'{{tracking_url}}'   => 'link de rastreio',
			) );
		}

		// Formatar como lista para o prompt
		$lines = array();
		foreach ( $all_vars as $var => $desc ) {
			$lines[] = "- {$var} → {$desc}";
		}

		return implode( "\n", $lines );
	}
}
