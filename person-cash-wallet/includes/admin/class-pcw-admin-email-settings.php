<?php
/**
 * Classe admin para configurações de email
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe admin email settings
 */
class PCW_Admin_Email_Settings {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_pcw_save_email_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'wp_ajax_pcw_send_test_email', array( $this, 'ajax_send_test_email' ) );
	}

	/**
	 * Adicionar menu
	 */
	public function add_menu() {
		// Menu oculto - acessível via Configurações > Emails
		add_submenu_page(
			null, // Oculto do menu
			__( 'Configurações de Email', 'person-cash-wallet' ),
			__( 'Email Settings', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-email-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		?>
		<div class="wrap">
			<!-- Page Header -->
			<div class="pcw-page-header">
				<div>
					<h1>
						<span class="dashicons dashicons-email"></span>
						<?php esc_html_e( 'Configurações de Email', 'person-cash-wallet' ); ?>
					</h1>
					<p class="description"><?php esc_html_e( 'Configure emails e SMTP específico do plugin', 'person-cash-wallet' ); ?></p>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'pcw_save_email_settings', 'pcw_nonce' ); ?>
				<input type="hidden" name="action" value="pcw_save_email_settings">

				<!-- General Settings -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-admin-generic"></span>
							<?php esc_html_e( 'Configurações Gerais', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<table class="form-table">
					<tr>
						<th><label for="pcw_email_from_name"><?php esc_html_e( 'Nome do Remetente', 'person-cash-wallet' ); ?></label></th>
						<td>
							<input type="text" id="pcw_email_from_name" name="pcw_email_from_name" value="<?php echo esc_attr( get_option( 'pcw_email_from_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Nome que aparecerá como remetente dos emails.', 'person-cash-wallet' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="pcw_email_from_email"><?php esc_html_e( 'Email do Remetente', 'person-cash-wallet' ); ?></label></th>
						<td>
							<input type="email" id="pcw_email_from_email" name="pcw_email_from_email" value="<?php echo esc_attr( get_option( 'pcw_email_from_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Email que aparecerá como remetente dos emails.', 'person-cash-wallet' ); ?></p>
						</td>
					</tr>
						</table>
					</div>
				</div>

				<!-- SMTP Settings -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-cloud"></span>
							<?php esc_html_e( 'Configurações SMTP', 'person-cash-wallet' ); ?>
						</h2>
						<span class="pcw-badge pcw-badge-info">
							<span class="dashicons dashicons-info"></span>
							<?php esc_html_e( 'Opcional', 'person-cash-wallet' ); ?>
						</span>
					</div>
					<div class="pcw-card-body">
						<div style="padding: 16px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px; margin-bottom: 20px;">
							<strong><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'Informação:', 'person-cash-wallet' ); ?></strong>
							<?php esc_html_e( 'Configure SMTP específico para os emails do plugin. Se não configurar, usará as configurações padrão do WordPress.', 'person-cash-wallet' ); ?>
						</div>
						<table class="form-table">
					<tr>
						<th><label for="pcw_email_smtp_enabled"><?php esc_html_e( 'Usar SMTP', 'person-cash-wallet' ); ?></label></th>
						<td>
							<label>
								<input type="checkbox" id="pcw_email_smtp_enabled" name="pcw_email_smtp_enabled" value="yes" <?php checked( get_option( 'pcw_email_smtp_enabled', 'no' ), 'yes' ); ?>>
								<?php esc_html_e( 'Ativar SMTP específico para emails do plugin', 'person-cash-wallet' ); ?>
							</label>
						</td>
					</tr>
					<tr class="pcw-smtp-field">
						<th><label for="pcw_email_smtp_host"><?php esc_html_e( 'Servidor SMTP', 'person-cash-wallet' ); ?></label></th>
						<td>
							<input type="text" id="pcw_email_smtp_host" name="pcw_email_smtp_host" value="<?php echo esc_attr( get_option( 'pcw_email_smtp_host', '' ) ); ?>" class="regular-text" placeholder="smtp.exemplo.com">
							<p class="description"><?php esc_html_e( 'Endereço do servidor SMTP.', 'person-cash-wallet' ); ?></p>
						</td>
					</tr>
					<tr class="pcw-smtp-field">
						<th><label for="pcw_email_smtp_port"><?php esc_html_e( 'Porta SMTP', 'person-cash-wallet' ); ?></label></th>
						<td>
							<input type="number" id="pcw_email_smtp_port" name="pcw_email_smtp_port" value="<?php echo esc_attr( get_option( 'pcw_email_smtp_port', 587 ) ); ?>" class="small-text" min="1" max="65535">
							<p class="description"><?php esc_html_e( 'Porta do servidor SMTP (geralmente 587 para TLS ou 465 para SSL).', 'person-cash-wallet' ); ?></p>
						</td>
					</tr>
					<tr class="pcw-smtp-field">
						<th><label for="pcw_email_smtp_secure"><?php esc_html_e( 'Criptografia', 'person-cash-wallet' ); ?></label></th>
						<td>
							<select id="pcw_email_smtp_secure" name="pcw_email_smtp_secure">
								<option value="none" <?php selected( get_option( 'pcw_email_smtp_secure', 'tls' ), 'none' ); ?>><?php esc_html_e( 'Nenhuma', 'person-cash-wallet' ); ?></option>
								<option value="tls" <?php selected( get_option( 'pcw_email_smtp_secure', 'tls' ), 'tls' ); ?>>TLS</option>
								<option value="ssl" <?php selected( get_option( 'pcw_email_smtp_secure', 'tls' ), 'ssl' ); ?>>SSL</option>
							</select>
							<p class="description"><?php esc_html_e( 'Tipo de criptografia (TLS recomendado para porta 587, SSL para porta 465).', 'person-cash-wallet' ); ?></p>
						</td>
					</tr>
					<tr class="pcw-smtp-field">
						<th><label for="pcw_email_smtp_auth"><?php esc_html_e( 'Autenticação', 'person-cash-wallet' ); ?></label></th>
						<td>
							<label>
								<input type="checkbox" id="pcw_email_smtp_auth" name="pcw_email_smtp_auth" value="yes" <?php checked( get_option( 'pcw_email_smtp_auth', 'yes' ), 'yes' ); ?>>
								<?php esc_html_e( 'Requer autenticação', 'person-cash-wallet' ); ?>
							</label>
						</td>
					</tr>
					<tr class="pcw-smtp-field">
						<th><label for="pcw_email_smtp_user"><?php esc_html_e( 'Usuário SMTP', 'person-cash-wallet' ); ?></label></th>
						<td>
							<input type="text" id="pcw_email_smtp_user" name="pcw_email_smtp_user" value="<?php echo esc_attr( get_option( 'pcw_email_smtp_user', '' ) ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Usuário para autenticação SMTP (geralmente o email completo).', 'person-cash-wallet' ); ?></p>
						</td>
					</tr>
					<tr class="pcw-smtp-field">
						<th><label for="pcw_email_smtp_pass"><?php esc_html_e( 'Senha SMTP', 'person-cash-wallet' ); ?></label></th>
						<td>
							<input type="password" id="pcw_email_smtp_pass" name="pcw_email_smtp_pass" value="<?php echo esc_attr( get_option( 'pcw_email_smtp_pass', '' ) ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Senha para autenticação SMTP.', 'person-cash-wallet' ); ?></p>
						</td>
					</tr>
						</table>
					</div>
				</div>

				<!-- Actions -->
				<div style="display: flex; gap: 12px; align-items: center;">
					<button type="submit" class="button pcw-button-primary pcw-button-icon">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e( 'Salvar Configurações', 'person-cash-wallet' ); ?>
					</button>
					<button type="button" class="button pcw-button-icon" id="pcw-test-email">
						<span class="dashicons dashicons-email-alt"></span>
						<?php esc_html_e( 'Testar Email', 'person-cash-wallet' ); ?>
					</button>
				</div>
			</form>
		</div>

		<!-- Logs de Email -->
		<div class="pcw-card" style="margin-top: 20px;">
			<div class="pcw-card-header">
				<h2>
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'Logs de Email', 'person-cash-wallet' ); ?>
				</h2>
				<button type="button" class="button" onclick="location.reload();">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Atualizar', 'person-cash-wallet' ); ?>
				</button>
			</div>
			<div class="pcw-card-body">
				<p class="description">
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'Mostrando os últimos envios do plugin (fonte: person-cash-wallet).', 'person-cash-wallet' ); ?>
				</p>

				<?php
				// Debug info
				$log_source = '';
				if ( class_exists( 'WC_Log_Handler_File' ) ) {
					$handler = new WC_Log_Handler_File();
					if ( method_exists( $handler, 'get_log_file_path' ) ) {
						$log_path = $handler->get_log_file_path( 'person-cash-wallet' );
						if ( ! empty( $log_path ) && file_exists( $log_path ) ) {
							$log_source = 'WooCommerce Logger: ' . basename( $log_path );
						}
					}
				}

				if ( empty( $log_source ) && defined( 'WP_CONTENT_DIR' ) ) {
					$fallback_log = WP_CONTENT_DIR . '/person-cash-wallet-emails.log';
					if ( file_exists( $fallback_log ) ) {
						$log_source = 'Arquivo de fallback: person-cash-wallet-emails.log';
					}
				}

				if ( ! empty( $log_source ) ) {
					echo '<p style="font-size: 11px; color: #6b7280; margin-bottom: 8px;"><em>Fonte: ' . esc_html( $log_source ) . '</em></p>';
				}
				?>

				<div style="background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 6px; padding: 12px; max-height: 320px; overflow: auto; font-family: monospace; font-size: 12px;">
					<?php
					$lines = $this->get_email_log_lines( 60 );
					if ( empty( $lines ) ) :
						?>
						<div style="color: #646970; text-align: center; padding: 20px;">
							<span class="dashicons dashicons-email" style="font-size: 48px; color: #d1d5db; margin-bottom: 10px;"></span>
							<br>
							<strong><?php esc_html_e( 'Nenhum log encontrado ainda', 'person-cash-wallet' ); ?></strong>
							<br><br>
							<?php esc_html_e( 'Os logs aparecerão aqui automaticamente quando emails forem enviados pelo plugin.', 'person-cash-wallet' ); ?>
							<br><br>
							<em style="font-size: 12px; color: #9ca3af;">
								<?php esc_html_e( 'Clique no botão "Testar Email" acima para enviar um email de teste.', 'person-cash-wallet' ); ?>
							</em>
						</div>
						<?php
					else :
						foreach ( $lines as $line ) :
							// Colorir SUCCESS e FALHA
							$colored_line = str_replace( 'ENVIADO', '<span style="color:#10b981; font-weight:bold;">ENVIADO</span>', $line );
							$colored_line = str_replace( 'FALHA', '<span style="color:#ef4444; font-weight:bold;">FALHA</span>', $colored_line );
							echo '<div>' . wp_kses_post( $colored_line ) . '</div>';
						endforeach;
					endif;
					?>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			function toggleSmtpFields() {
				if ($('#pcw_email_smtp_enabled').is(':checked')) {
					$('.pcw-smtp-field').show();
				} else {
					$('.pcw-smtp-field').hide();
				}
			}

			$('#pcw_email_smtp_enabled').on('change', toggleSmtpFields);
			toggleSmtpFields();

			// Mostrar campos se já estiver habilitado
			if ($('#pcw_email_smtp_enabled').is(':checked')) {
				$('.pcw-smtp-field').show();
			}

			$('#pcw-test-email').on('click', function(e) {
				e.preventDefault();
				var adminEmail = '<?php echo esc_js( get_option( 'admin_email' ) ); ?>';
				if (confirm('<?php echo esc_js( __( 'Enviar email de teste para:', 'person-cash-wallet' ) ); ?> ' + adminEmail + '?')) {
					var button = $(this);
					button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Enviando...');
					
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'pcw_send_test_email',
							nonce: '<?php echo esc_js( wp_create_nonce( 'pcw_send_test_email' ) ); ?>'
						},
						success: function(response) {
							if (response.success) {
								alert('✅ ' + response.data.message);
								location.reload();
							} else {
								alert('❌ ' + response.data.message);
								button.prop('disabled', false).html('<span class="dashicons dashicons-email-alt"></span> <?php echo esc_js( __( 'Testar Email', 'person-cash-wallet' ) ); ?>');
							}
						},
						error: function() {
							alert('❌ Erro ao enviar email de teste');
							button.prop('disabled', false).html('<span class="dashicons dashicons-email-alt"></span> <?php echo esc_js( __( 'Testar Email', 'person-cash-wallet' ) ); ?>');
						}
					});
				}
			});
		});
		</script>

		<style>
		.pcw-smtp-field {
			display: none;
		}
		#pcw_email_smtp_enabled:checked ~ table .pcw-smtp-field,
		body:has(#pcw_email_smtp_enabled:checked) .pcw-smtp-field {
			display: table-row;
		}
		@keyframes spin {
			from { transform: rotate(0deg); }
			to { transform: rotate(360deg); }
		}
		.dashicons.spin {
			animation: spin 1s linear infinite;
		}
		</style>
		<?php
	}

	/**
	 * Ler últimas linhas do log de email
	 *
	 * @param int $limit Limite de linhas.
	 * @return array
	 */
	private function get_email_log_lines( $limit = 50 ) {
		$lines = array();

		// Tentar ler do WooCommerce Logger primeiro
		if ( class_exists( 'WC_Log_Handler_File' ) ) {
			$handler = new WC_Log_Handler_File();
			if ( method_exists( $handler, 'get_log_file_path' ) ) {
				$log_path = $handler->get_log_file_path( 'person-cash-wallet' );
				if ( ! empty( $log_path ) && file_exists( $log_path ) ) {
					$lines = $this->read_log_file( $log_path, $limit );
				}
			}
		}

		// Se não encontrou logs no WooCommerce, tentar arquivo de fallback
		if ( empty( $lines ) && defined( 'WP_CONTENT_DIR' ) ) {
			$fallback_log = WP_CONTENT_DIR . '/person-cash-wallet-emails.log';
			if ( file_exists( $fallback_log ) ) {
				$lines = $this->read_log_file( $fallback_log, $limit );
			}
		}

		return $lines;
	}

	/**
	 * Ler arquivo de log
	 *
	 * @param string $log_path Caminho do arquivo.
	 * @param int    $limit Limite de linhas.
	 * @return array
	 */
	private function read_log_file( $log_path, $limit = 50 ) {
		$lines = array();

		try {
			$file = new SplFileObject( $log_path, 'r' );
			$file->seek( PHP_INT_MAX );
			$last = $file->key();
			$start = max( 0, $last - absint( $limit ) );

			for ( $i = $start; $i <= $last; $i++ ) {
				$file->seek( $i );
				$line = trim( $file->current() );
				if ( '' !== $line ) {
					$lines[] = $line;
				}
			}
		} catch ( Exception $e ) {
			return array();
		}

		return $lines;
	}

	/**
	 * Processar salvamento de configurações
	 */
	public function handle_save_settings() {
		if ( ! isset( $_POST['pcw_nonce'] ) || ! wp_verify_nonce( $_POST['pcw_nonce'], 'pcw_save_email_settings' ) ) {
			wp_die( esc_html__( 'Ação não autorizada.', 'person-cash-wallet' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'person-cash-wallet' ) );
		}

		// Salvar configurações gerais
		update_option( 'pcw_email_from_name', sanitize_text_field( $_POST['pcw_email_from_name'] ) );
		update_option( 'pcw_email_from_email', sanitize_email( $_POST['pcw_email_from_email'] ) );

		// Salvar configurações SMTP
		update_option( 'pcw_email_smtp_enabled', isset( $_POST['pcw_email_smtp_enabled'] ) ? 'yes' : 'no' );
		update_option( 'pcw_email_smtp_host', sanitize_text_field( $_POST['pcw_email_smtp_host'] ) );
		update_option( 'pcw_email_smtp_port', absint( $_POST['pcw_email_smtp_port'] ) );
		update_option( 'pcw_email_smtp_secure', sanitize_text_field( $_POST['pcw_email_smtp_secure'] ) );
		update_option( 'pcw_email_smtp_auth', isset( $_POST['pcw_email_smtp_auth'] ) ? 'yes' : 'no' );
		update_option( 'pcw_email_smtp_user', sanitize_text_field( $_POST['pcw_email_smtp_user'] ) );
		
		// Senha só atualiza se fornecida
		if ( ! empty( $_POST['pcw_email_smtp_pass'] ) ) {
			update_option( 'pcw_email_smtp_pass', sanitize_text_field( $_POST['pcw_email_smtp_pass'] ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=pcw-email-settings&message=saved' ) );
		exit;
	}

	/**
	 * Processar teste de email
	 */
	/**
	 * AJAX - Enviar email de teste
	 */
	public function ajax_send_test_email() {
		check_ajax_referer( 'pcw_send_test_email', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'person-cash-wallet' ) ) );
		}

		$to = get_option( 'admin_email' );
		$subject = __( '✉️ Teste de Email - Growly Digital', 'person-cash-wallet' );
		$message = '
		<h2 style="color: #059669; margin: 0 0 20px 0; font-size: 22px;">
			✅ Email de Teste
		</h2>
		
		<p style="font-size: 16px; margin-bottom: 24px;">
			Este é um email de teste do sistema <strong>Person Cash Wallet</strong>.
		</p>

		<div style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-left: 4px solid #10b981; padding: 20px; border-radius: 8px; margin: 24px 0;">
			<p style="margin: 0 0 12px 0; color: #065f46; font-size: 15px;">
				<strong>🎉 Configuração Funcionando Corretamente!</strong>
			</p>
			<p style="margin: 12px 0 0 0; color: #047857; font-size: 13px; line-height: 1.6;">
				<strong>Data/Hora:</strong> ' . current_time( 'd/m/Y \à\s H:i:s' ) . '<br>
				<strong>Remetente:</strong> ' . esc_html( get_option( 'pcw_email_from_name', get_bloginfo( 'name' ) ) ) . '<br>
				<strong>Email:</strong> ' . esc_html( get_option( 'pcw_email_from_email', get_option( 'admin_email' ) ) ) . '
			</p>
		</div>

		<p style="margin: 24px 0; line-height: 1.7;">
			Se você recebeu este email, significa que todas as configurações de envio estão corretas! Agora seus clientes receberão emails bonitos e profissionais. ✨
		</p>

		<p style="color: #9ca3af; font-size: 13px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
			<em>Email automático gerado pelo Growly Digital</em>
		</p>';

		// Enviar com wrap_html = true para usar o novo template bonito
		$result = PCW_Email_Handler::send( $to, $subject, $message, array(), array(), true );

		if ( $result ) {
			wp_send_json_success( array(
				'message' => sprintf(
					__( 'Email de teste enviado com sucesso para %s! Verifique sua caixa de entrada e os logs abaixo.', 'person-cash-wallet' ),
					$to
				),
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Falha ao enviar email de teste. Verifique as configurações de SMTP e os logs.', 'person-cash-wallet' ),
			) );
		}
	}
}
