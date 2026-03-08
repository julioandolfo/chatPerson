<?php
/**
 * Visualizador de Logs
 *
 * @package PersonCashWallet
 * @since 1.3.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCW_Admin_Logs {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 100 );
		add_action( 'wp_ajax_pcw_clear_log', array( $this, 'ajax_clear_log' ) );
		add_action( 'wp_ajax_pcw_download_log', array( $this, 'ajax_download_log' ) );
	}

	/**
	 * Adicionar menu
	 */
	public function add_menu() {
		add_submenu_page(
			'pcw-dashboard',
			__( 'Logs', 'person-cash-wallet' ),
			__( 'Logs', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-logs',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		// Diretórios de logs
		$log_dirs = array(
			'content'  => WP_CONTENT_DIR . '/pcw-logs',
			'plugin'   => PCW_PLUGIN_DIR . 'logs',
		);
		
		$available_logs = $this->get_all_available_logs( $log_dirs );
		
		// Pegar log da URL (pode vir encoded)
		$current_log = '';
		if ( isset( $_GET['log'] ) ) {
			$current_log = sanitize_text_field( urldecode( $_GET['log'] ) );
		}
		
		// Se não foi especificado e tem logs disponíveis, usar o primeiro
		if ( empty( $current_log ) && ! empty( $available_logs ) ) {
			$first_log = reset( $available_logs );
			$current_log = $first_log['key'];
		}
		
		// Encontrar o log selecionado
		$log_file = '';
		$log_info = null;
		foreach ( $available_logs as $log ) {
			if ( $log['key'] === $current_log ) {
				$log_file = $log['path'];
				$log_info = $log;
				break;
			}
		}
		
		// Se não encontrou, usar o primeiro
		if ( empty( $log_file ) && ! empty( $available_logs ) ) {
			$first_log = reset( $available_logs );
			$log_file = $first_log['path'];
			$log_info = $first_log;
			$current_log = $first_log['key'];
		}
		
		$log_content = '';
		$log_size = 0;
		$log_lines = 0;
		
		if ( ! empty( $log_file ) && file_exists( $log_file ) ) {
			$log_size = filesize( $log_file );
			$log_content = file_get_contents( $log_file );
			$log_lines = substr_count( $log_content, "\n" );
		}
		
		?>
		<div class="wrap">
			<div class="pcw-page-header">
				<div>
					<h1>
						<span class="dashicons dashicons-media-text"></span>
						<?php esc_html_e( 'Logs do Sistema', 'person-cash-wallet' ); ?>
					</h1>
					<p class="description">
						<?php esc_html_e( 'Visualize logs detalhados de integrações e processos', 'person-cash-wallet' ); ?>
					</p>
				</div>
			</div>

			<div class="pcw-card" style="max-width: 100%; margin-top: 20px;">
				<div class="pcw-card-header" style="display: flex; justify-content: space-between; align-items: center;">
					<div>
						<label for="log-selector" style="margin-right: 10px; font-weight: 600;">
							<?php esc_html_e( 'Arquivo de Log:', 'person-cash-wallet' ); ?>
						</label>
						<select id="log-selector" style="min-width: 300px;">
							<?php foreach ( $available_logs as $log ) : ?>
								<option value="<?php echo esc_attr( $log['key'] ); ?>" <?php selected( $current_log, $log['key'] ); ?>>
									<?php echo esc_html( $log['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div>
						<?php if ( file_exists( $log_file ) ) : ?>
							<span class="dashicons dashicons-media-document" style="color: #059669;"></span>
							<strong><?php echo esc_html( size_format( $log_size ) ); ?></strong>
							<span style="color: #64748b; margin: 0 10px;">•</span>
							<strong><?php echo number_format( $log_lines ); ?></strong> linhas
							<span style="color: #64748b; margin: 0 10px;">•</span>
							<?php
							$mod_time = filemtime( $log_file );
							$time_diff = human_time_diff( $mod_time, current_time( 'timestamp' ) );
							?>
							<span style="color: #64748b;">
								<?php printf( __( 'Última modificação: %s atrás', 'person-cash-wallet' ), $time_diff ); ?>
							</span>
						<?php endif; ?>
					</div>
				</div>

				<div class="pcw-card-body" style="padding: 0;">
					<?php if ( empty( $available_logs ) ) : ?>
						<div style="padding: 40px; text-align: center; color: #64748b;">
							<span class="dashicons dashicons-info" style="font-size: 48px; opacity: 0.3;"></span>
							<p style="font-size: 16px; margin-top: 10px;">
								<?php esc_html_e( 'Nenhum arquivo de log encontrado', 'person-cash-wallet' ); ?>
							</p>
							<p class="description">
								<?php esc_html_e( 'Os logs serão criados automaticamente quando houver atividade', 'person-cash-wallet' ); ?>
							</p>
						</div>
					<?php elseif ( ! file_exists( $log_file ) ) : ?>
						<div style="padding: 40px; text-align: center; color: #64748b;">
							<span class="dashicons dashicons-warning" style="font-size: 48px; opacity: 0.3;"></span>
							<p style="font-size: 16px; margin-top: 10px;">
								<?php esc_html_e( 'Arquivo de log não encontrado', 'person-cash-wallet' ); ?>
							</p>
						</div>
					<?php elseif ( empty( $log_content ) ) : ?>
						<div style="padding: 40px; text-align: center; color: #64748b;">
							<span class="dashicons dashicons-yes-alt" style="font-size: 48px; opacity: 0.3; color: #22c55e;"></span>
							<p style="font-size: 16px; margin-top: 10px;">
								<?php esc_html_e( 'Log vazio', 'person-cash-wallet' ); ?>
							</p>
							<p class="description">
								<?php esc_html_e( 'Nenhuma atividade registrada ainda', 'person-cash-wallet' ); ?>
							</p>
						</div>
					<?php else : ?>
						<div style="background: #1e293b; padding: 20px; overflow: auto; max-height: 600px; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.6;">
							<pre style="margin: 0; color: #e2e8f0; white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html( $log_content ); ?></pre>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( file_exists( $log_file ) && ! empty( $log_content ) ) : ?>
					<div class="pcw-card-footer" style="padding: 15px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc; display: flex; justify-content: space-between; align-items: center;">
						<div>
							<button type="button" id="refresh-log" class="button">
								<span class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Atualizar', 'person-cash-wallet' ); ?>
							</button>
							<button type="button" id="clear-log" class="button" data-log="<?php echo esc_attr( $current_log ); ?>" data-filename="<?php echo esc_attr( $log_info ? $log_info['file'] : '' ); ?>">
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Limpar Log', 'person-cash-wallet' ); ?>
							</button>
						</div>
						<div>
							<?php if ( $log_info ) : ?>
								<a href="<?php echo esc_url( $log_info['url'] ); ?>" class="button" download>
									<span class="dashicons dashicons-download"></span>
									<?php esc_html_e( 'Baixar Log', 'person-cash-wallet' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<div class="pcw-card" style="max-width: 100%; margin-top: 20px;">
				<div class="pcw-card-header">
					<h3 style="margin: 0;">
						<span class="dashicons dashicons-info"></span>
						<?php esc_html_e( 'Sobre os Logs', 'person-cash-wallet' ); ?>
					</h3>
				</div>
				<div class="pcw-card-body">
					<h4><?php esc_html_e( 'Níveis de Log', 'person-cash-wallet' ); ?></h4>
					<ul style="list-style: none; padding: 0;">
						<li style="padding: 5px 0;">
							<span style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;">INFO</span>
							<span style="margin-left: 10px;"><?php esc_html_e( 'Informações gerais de operações', 'person-cash-wallet' ); ?></span>
						</li>
						<li style="padding: 5px 0;">
							<span style="background: #f59e0b; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;">DEBUG</span>
							<span style="margin-left: 10px;"><?php esc_html_e( 'Detalhes técnicos para diagnóstico', 'person-cash-wallet' ); ?></span>
						</li>
						<li style="padding: 5px 0;">
							<span style="background: #dc2626; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;">ERROR</span>
							<span style="margin-left: 10px;"><?php esc_html_e( 'Erros que precisam de atenção', 'person-cash-wallet' ); ?></span>
						</li>
					</ul>

					<h4 style="margin-top: 20px;"><?php esc_html_e( 'Arquivos de Log', 'person-cash-wallet' ); ?></h4>
					<ul>
						<li><strong>webhook-whats.log:</strong> <?php esc_html_e( 'Integração com Personizi WhatsApp (requisições à API)', 'person-cash-wallet' ); ?></li>
						<li><strong>webhooks-debug.log:</strong> <?php esc_html_e( 'Debug de webhooks (testes e operações AJAX)', 'person-cash-wallet' ); ?></li>
						<li><strong>automations.log:</strong> <?php esc_html_e( 'Automações e workflows', 'person-cash-wallet' ); ?></li>
						<li><strong>email.log:</strong> <?php esc_html_e( 'Envio de emails', 'person-cash-wallet' ); ?></li>
					</ul>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			console.log('[PCW Logs] Current log:', '<?php echo esc_js( $current_log ); ?>');
			console.log('[PCW Logs] Selected value:', $('#log-selector').val());
			
			// Mudar log
			$('#log-selector').on('change', function() {
				var log = $(this).val();
				console.log('[PCW Logs] Changing to:', log);
				window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=pcw-logs&log=' ) ); ?>' + encodeURIComponent(log);
			});

			// Atualizar
			$('#refresh-log').on('click', function() {
				location.reload();
			});

			// Limpar log
			$('#clear-log').on('click', function() {
				if (!confirm('<?php esc_html_e( 'Tem certeza que deseja limpar este log? Esta ação não pode ser desfeita.', 'person-cash-wallet' ); ?>')) {
					return;
				}

				var $btn = $(this);
				var log = $btn.data('log');
				var originalText = $btn.html();

				$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Limpando...');

				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'pcw_clear_log',
						nonce: '<?php echo esc_js( wp_create_nonce( 'pcw_logs' ) ); ?>',
						log: log
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert('Erro: ' + response.data.message);
							$btn.prop('disabled', false).html(originalText);
						}
					},
					error: function() {
						alert('<?php esc_html_e( 'Erro ao limpar log', 'person-cash-wallet' ); ?>');
						$btn.prop('disabled', false).html(originalText);
					}
				});
			});
		});
		</script>

		<style>
		.dashicons.spin {
			animation: spin 1s linear infinite;
		}
		@keyframes spin {
			from { transform: rotate(0deg); }
			to { transform: rotate(360deg); }
		}
		</style>
		<?php
	}

	/**
	 * Obter todos os logs disponíveis de múltiplos diretórios
	 *
	 * @param array $log_dirs Diretórios de logs
	 * @return array
	 */
	private function get_all_available_logs( $log_dirs ) {
		$logs = array();
		
		// Labels amigáveis para cada arquivo de log
		$log_labels = array(
			'webhook-whats.log'      => 'Personizi WhatsApp (API)',
			'webhooks-debug.log'     => 'Webhooks Debug',
			'webhook-dispatcher.log' => 'Webhook Dispatcher (Eventos)',
			'automations.log'        => 'Automações',
			'email.log'              => 'Emails',
			'queue.log'              => 'Fila de Mensagens',
			'sendpulse.log'          => 'SendPulse',
			'ga4.log'                => 'Google Analytics 4',
		);
		
		foreach ( $log_dirs as $dir_key => $log_dir ) {
			if ( ! is_dir( $log_dir ) ) {
				continue;
			}
			
			$files = scandir( $log_dir );
			
			foreach ( $files as $file ) {
				if ( pathinfo( $file, PATHINFO_EXTENSION ) !== 'log' ) {
					continue;
				}
				
				$key = $dir_key . ':' . $file;
				$label = isset( $log_labels[ $file ] ) ? $log_labels[ $file ] : $file;
				
				// Adicionar indicador de origem
				if ( $dir_key === 'plugin' ) {
					$label = '[Plugin] ' . $label;
				}
				
				// URL para download
				if ( $dir_key === 'content' ) {
					$url = content_url( 'pcw-logs/' . $file );
				} else {
					$url = plugins_url( 'logs/' . $file, PCW_PLUGIN_FILE );
				}
				
				$logs[] = array(
					'key'      => $key,
					'file'     => $file,
					'label'    => $label,
					'path'     => $log_dir . '/' . $file,
					'url'      => $url,
					'dir_key'  => $dir_key,
				);
			}
		}
		
		// Ordenar por label
		usort( $logs, function( $a, $b ) {
			return strcmp( $a['label'], $b['label'] );
		});
		
		return $logs;
	}
	
	/**
	 * Obter logs disponíveis (legacy)
	 *
	 * @param string $log_dir Diretório de logs
	 * @return array
	 */
	private function get_available_logs( $log_dir ) {
		if ( ! is_dir( $log_dir ) ) {
			return array();
		}

		$logs = array();
		$files = scandir( $log_dir );

		foreach ( $files as $file ) {
			if ( pathinfo( $file, PATHINFO_EXTENSION ) === 'log' ) {
				$logs[] = $file;
			}
		}

		return $logs;
	}

	/**
	 * AJAX: Limpar log
	 */
	public function ajax_clear_log() {
		check_ajax_referer( 'pcw_logs', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$log_key = isset( $_POST['log'] ) ? sanitize_text_field( $_POST['log'] ) : '';
		
		if ( empty( $log_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Log não especificado', 'person-cash-wallet' ) ) );
		}

		// Parse log key (format: dir_key:filename)
		$parts = explode( ':', $log_key, 2 );
		if ( count( $parts ) !== 2 ) {
			wp_send_json_error( array( 'message' => __( 'Formato de log inválido', 'person-cash-wallet' ) ) );
		}
		
		$dir_key = $parts[0];
		$filename = sanitize_file_name( $parts[1] );
		
		// Determinar diretório
		if ( $dir_key === 'content' ) {
			$log_file = WP_CONTENT_DIR . '/pcw-logs/' . $filename;
		} elseif ( $dir_key === 'plugin' ) {
			$log_file = PCW_PLUGIN_DIR . 'logs/' . $filename;
		} else {
			wp_send_json_error( array( 'message' => __( 'Diretório de log inválido', 'person-cash-wallet' ) ) );
		}

		if ( ! file_exists( $log_file ) ) {
			wp_send_json_error( array( 'message' => __( 'Arquivo não encontrado', 'person-cash-wallet' ) ) );
		}

		// Limpar arquivo
		file_put_contents( $log_file, '' );

		wp_send_json_success( array( 'message' => __( 'Log limpo com sucesso', 'person-cash-wallet' ) ) );
	}
}
