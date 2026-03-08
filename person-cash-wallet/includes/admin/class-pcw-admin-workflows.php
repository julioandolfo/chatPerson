<?php
/**
 * Admin de Workflows
 *
 * @package GrowlyDigital
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe admin de workflows
 */
class PCW_Admin_Workflows {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_init', array( $this, 'handle_mass_expiration' ) );
		add_action( 'wp_ajax_pcw_run_scheduled_workflow', array( $this, 'ajax_run_scheduled_workflow' ) );
		add_action( 'wp_ajax_pcw_save_workflow', array( $this, 'ajax_save_workflow' ) );
		add_action( 'wp_ajax_pcw_delete_workflow', array( $this, 'ajax_delete_workflow' ) );
		add_action( 'wp_ajax_pcw_toggle_workflow', array( $this, 'ajax_toggle_workflow' ) );
		add_action( 'wp_ajax_pcw_get_trigger_variables', array( $this, 'ajax_get_trigger_variables' ) );
		add_action( 'wp_ajax_pcw_test_webhook', array( $this, 'ajax_test_webhook' ) );
		add_action( 'wp_ajax_pcw_generate_workflow_message', array( $this, 'ajax_generate_workflow_message' ) );
		add_action( 'wp_ajax_pcw_test_run_workflow', array( $this, 'ajax_test_run_workflow' ) );
		add_action( 'wp_ajax_pcw_run_all_scheduled_workflows', array( $this, 'ajax_run_all_scheduled_workflows' ) );
		add_action( 'wp_ajax_pcw_fix_workflow_cron', array( $this, 'ajax_fix_workflow_cron' ) );
	}

	/**
	 * Processar atualização em massa de expiração
	 */
	public function handle_mass_expiration() {
		if ( ! isset( $_POST['action'] ) || 'pcw_set_mass_expiration' !== $_POST['action'] ) {
			return;
		}

		if ( ! isset( $_POST['pcw_mass_exp_nonce'] ) || ! wp_verify_nonce( $_POST['pcw_mass_exp_nonce'], 'pcw_set_mass_expiration' ) ) {
			wp_die( __( 'Ação não autorizada.', 'person-cash-wallet' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Sem permissão.', 'person-cash-wallet' ) );
		}

		$days = isset( $_POST['expiration_days'] ) ? absint( $_POST['expiration_days'] ) : 90;
		$expires_date = gmdate( 'Y-m-d H:i:s', strtotime( "+{$days} days" ) );

		global $wpdb;
		$table = $wpdb->prefix . 'pcw_cashback';

		// Atualizar todos os cashbacks disponíveis sem expiração
		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} 
			SET expires_date = %s, updated_at = %s 
			WHERE status = 'available' 
			AND expires_date IS NULL",
			$expires_date,
			current_time( 'mysql' )
		) );

		// Redirecionar de volta com mensagem
		$redirect_url = add_query_arg(
			array(
				'page'      => 'pcw-workflows',
				'action'    => 'analytics',
				'id'        => isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0,
				'updated'   => $updated,
				'exp_days'  => $days,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * AJAX: Executar workflow agendado manualmente
	 */
	public function ajax_run_scheduled_workflow() {
		check_ajax_referer( 'pcw_workflows', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'person-cash-wallet' ) ) );
		}

		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( $_POST['workflow_id'] ) : 0;

		if ( ! $workflow_id ) {
			wp_send_json_error( array( 'message' => __( 'ID do workflow inválido.', 'person-cash-wallet' ) ) );
		}

		// Verificar se o scheduler existe
		if ( ! class_exists( 'PCW_Workflow_Scheduler' ) ) {
			wp_send_json_error( array( 'message' => __( 'Scheduler não disponível.', 'person-cash-wallet' ) ) );
		}

		$scheduler = PCW_Workflow_Scheduler::instance();
		$manager = PCW_Workflow_Manager::instance();
		$workflow = $manager->get( $workflow_id );

		if ( ! $workflow ) {
			wp_send_json_error( array( 'message' => __( 'Workflow não encontrado.', 'person-cash-wallet' ) ) );
		}

		if ( 'scheduled_order_check' !== $workflow->trigger_type ) {
			wp_send_json_error( array( 'message' => __( 'Este workflow não é do tipo agendado.', 'person-cash-wallet' ) ) );
		}

		// Executar o scheduler completo (vai processar apenas workflows ativos)
		$scheduler->run_now();

		// Buscar logs recentes para mostrar resultado
		global $wpdb;
		$logs_table = $wpdb->prefix . 'pcw_workflow_logs';
		
		$five_minutes_ago = date( 'Y-m-d H:i:s', strtotime( '-5 minutes', current_time( 'timestamp' ) ) );
		$recent_logs = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$logs_table} 
			WHERE workflow_id = %d 
			AND executed_at >= %s
			ORDER BY executed_at DESC",
			$workflow_id,
			$five_minutes_ago
		) );

		$success_count = 0;
		$error_count = 0;

		foreach ( $recent_logs as $log ) {
			if ( 'success' === $log->status ) {
				$success_count++;
			} else {
				$error_count++;
			}
		}

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: 1: success count 2: error count */
				__( 'Execução concluída! %1$d sucesso(s), %2$d erro(s).', 'person-cash-wallet' ),
				$success_count,
				$error_count
			),
			'processed' => count( $recent_logs ),
			'success'   => $success_count,
			'errors'    => $error_count,
		) );
	}

	/**
	 * AJAX: Executar todos os workflows agendados
	 */
	public function ajax_run_all_scheduled_workflows() {
		check_ajax_referer( 'pcw_workflows', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'person-cash-wallet' ) ) );
		}

		if ( ! class_exists( 'PCW_Workflow_Scheduler' ) ) {
			wp_send_json_error( array( 'message' => __( 'Scheduler não disponível.', 'person-cash-wallet' ) ) );
		}

		$scheduler = PCW_Workflow_Scheduler::instance();
		$scheduler->run_now();

		// Contar execuções recentes
		global $wpdb;
		$logs_table = $wpdb->prefix . 'pcw_workflow_logs';
		
		$two_minutes_ago = date( 'Y-m-d H:i:s', strtotime( '-2 minutes', current_time( 'timestamp' ) ) );
		$recent_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$logs_table} 
			WHERE executed_at >= %s",
			$two_minutes_ago
		) );

		wp_send_json_success( array(
			'message' => sprintf(
				__( 'Cron executado! %d ação(ões) processada(s).', 'person-cash-wallet' ),
				absint( $recent_count )
			),
		) );
	}

	/**
	 * AJAX: Corrigir/reagendar cron de workflows
	 */
	public function ajax_fix_workflow_cron() {
		check_ajax_referer( 'pcw_workflows', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'person-cash-wallet' ) ) );
		}

		// Remover agendamento existente
		$timestamp = wp_next_scheduled( 'pcw_workflow_scheduled_check' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'pcw_workflow_scheduled_check' );
		}

		// Reagendar
		$result = wp_schedule_event( time(), 'hourly', 'pcw_workflow_scheduled_check' );

		if ( $result ) {
			wp_send_json_success( array(
				'message' => __( 'Cron reagendado com sucesso!', 'person-cash-wallet' ),
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Erro ao reagendar cron.', 'person-cash-wallet' ),
			) );
		}
	}

	/**
	 * Adicionar menu
	 */
	public function add_menu() {
		add_submenu_page(
			'pcw-dashboard',
			__( 'Workflows', 'person-cash-wallet' ),
			__( 'Workflows', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-workflows',
			array( $this, 'render_page' ),
			35
		);
	}

	/**
	 * Enqueue scripts
	 *
	 * @param string $hook Hook da página.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'growly-digital_page_pcw-workflows' !== $hook && 'person-cash-wallet_page_pcw-workflows' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'pcw-admin-workflows',
			PCW_PLUGIN_URL . 'assets/css/admin-workflows.css',
			array(),
			PCW_VERSION
		);

		// Email Editor CSS (para ação de email).
		wp_enqueue_style(
			'pcw-email-editor',
			PCW_PLUGIN_URL . 'assets/css/email-editor.css',
			array(),
			PCW_VERSION
		);

		wp_enqueue_script(
			'pcw-admin-workflows',
			PCW_PLUGIN_URL . 'assets/js/admin-workflows.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			PCW_VERSION,
			true
		);

		// Email Editor JS (para ação de email).
		wp_enqueue_script(
			'pcw-email-editor',
			PCW_PLUGIN_URL . 'assets/js/email-editor.js',
			array( 'jquery' ),
			PCW_VERSION,
			true
		);

		// Media uploader para imagens no email.
		wp_enqueue_media();

		// Localização do email editor.
		wp_localize_script( 'pcw-email-editor', 'pcwEmailEditor', array(
			'pluginUrl' => PCW_PLUGIN_URL,
			'siteName'  => get_bloginfo( 'name' ),
		) );

		// Inicializar triggers e actions
		PCW_Workflow_Triggers::init();
		PCW_Workflow_Actions::init();

		// Verificar se IA está configurada
		$ai_configured = class_exists( 'PCW_OpenAI' ) && PCW_OpenAI::instance()->is_configured();

		wp_localize_script( 'pcw-admin-workflows', 'pcwWorkflows', array(
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'pcw_workflows' ),
			'triggers'          => PCW_Workflow_Triggers::get_grouped(),
			'triggersList'      => PCW_Workflow_Triggers::get_all(),
			'actions'           => PCW_Workflow_Actions::get_all(),
			'conditions'        => PCW_Workflow_Conditions::get_all(),
			'operators'         => PCW_Workflow_Conditions::get_operators(),
			'orderStatuses'     => $this->get_order_statuses(),
			'levels'            => $this->get_levels(),
			'personiziAccounts' => $this->get_personizi_accounts(),
			'aiConfigured'      => $ai_configured,
			'aiSettingsUrl'     => admin_url( 'admin.php?page=pcw-settings&tab=ai' ),
			'i18n'              => array(
				'confirmDelete'      => __( 'Tem certeza que deseja excluir este workflow?', 'person-cash-wallet' ),
				'saving'             => __( 'Salvando...', 'person-cash-wallet' ),
				'saved'              => __( 'Salvo!', 'person-cash-wallet' ),
				'error'              => __( 'Erro ao salvar', 'person-cash-wallet' ),
				'addCondition'       => __( 'Adicionar Condição', 'person-cash-wallet' ),
				'addAction'          => __( 'Adicionar Ação', 'person-cash-wallet' ),
				'addField'           => __( 'Adicionar Campo', 'person-cash-wallet' ),
				'selectTrigger'      => __( 'Selecione um gatilho', 'person-cash-wallet' ),
				'selectAction'       => __( 'Selecione uma ação', 'person-cash-wallet' ),
				'selectCondition'    => __( 'Selecione uma condição', 'person-cash-wallet' ),
				'testing'            => __( 'Testando...', 'person-cash-wallet' ),
				'testSuccess'        => __( 'Webhook enviado com sucesso!', 'person-cash-wallet' ),
				'testError'          => __( 'Erro no teste', 'person-cash-wallet' ),
				'useDefault'         => __( 'Usar padrão configurado', 'person-cash-wallet' ),
				'generateWithAI'     => __( 'Gerar com IA', 'person-cash-wallet' ),
				'configureAI'        => __( 'Configurar IA', 'person-cash-wallet' ),
				'generating'         => __( 'Gerando...', 'person-cash-wallet' ),
				'availableVariables' => __( 'Variáveis disponíveis:', 'person-cash-wallet' ),
				'client'             => __( 'Cliente', 'person-cash-wallet' ),
				'orderCashback'      => __( 'Pedido/Cashback', 'person-cash-wallet' ),
				'datesLinks'         => __( 'Datas/Links', 'person-cash-wallet' ),
				'allVariables'       => __( 'Todas as variáveis para:', 'person-cash-wallet' ),
			),
		) );
	}

	/**
	 * Obter status de pedidos do WooCommerce
	 *
	 * @return array
	 */
	private function get_order_statuses() {
		$statuses = array( 'any' => __( 'Qualquer status', 'person-cash-wallet' ) );

		if ( function_exists( 'wc_get_order_statuses' ) ) {
			foreach ( wc_get_order_statuses() as $status => $label ) {
				$status_key = str_replace( 'wc-', '', $status );
				$statuses[ $status_key ] = $label;
			}
		}

		return $statuses;
	}

	/**
	 * Obter níveis disponíveis
	 *
	 * @return array
	 */
	private function get_levels() {
		$levels = array();

		if ( class_exists( 'PCW_Levels' ) ) {
			$all_levels = PCW_Levels::get_all_levels();
			foreach ( $all_levels as $level ) {
				$levels[ $level->id ] = $level->name;
			}
		}

		return $levels;
	}

	/**
	 * Obter contas WhatsApp do Personizi
	 *
	 * @return array
	 */
	private function get_personizi_accounts() {
		$accounts = array();

		if ( ! class_exists( 'PCW_Personizi_Integration' ) ) {
			return $accounts;
		}

		$personizi = PCW_Personizi_Integration::instance();
		$whatsapp_accounts = $personizi->get_whatsapp_accounts();

		if ( is_wp_error( $whatsapp_accounts ) || empty( $whatsapp_accounts ) ) {
			return $accounts;
		}

		foreach ( $whatsapp_accounts as $account ) {
			$phone = isset( $account['phone_number'] ) ? $account['phone_number'] : '';
			$name = isset( $account['name'] ) ? $account['name'] : $phone;
			if ( $phone ) {
				$accounts[ $phone ] = $name . ' (' . $phone . ')';
			}
		}

		return $accounts;
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		?>
		<div class="wrap pcw-workflows-page">
			<?php
			if ( 'edit' === $action || 'new' === $action ) {
				$this->render_edit_form( $id );
			} elseif ( 'analytics' === $action ) {
				$this->render_analytics( $id );
			} else {
				$this->render_list();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Garantir que a tabela de logs tem todas as colunas necessárias
	 *
	 * @param string $table Nome da tabela.
	 */
	private function ensure_workflow_logs_columns( $table ) {
		global $wpdb;
		
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
		
		if ( empty( $columns ) ) {
			return;
		}
		
		if ( ! in_array( 'executed_at', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN executed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER execution_time" );
			$wpdb->query( "ALTER TABLE {$table} ADD KEY executed_at (executed_at)" );
			$wpdb->query( "UPDATE {$table} SET executed_at = created_at WHERE executed_at = '0000-00-00 00:00:00'" );
		}
		
		if ( ! in_array( 'context', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN context longtext AFTER trigger_data" );
		}
		
		if ( ! in_array( 'result', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN result longtext AFTER actions_executed" );
		}
	}

	/**
	 * Renderizar status do cron
	 */
	private function render_cron_status() {
		$next_scheduled = wp_next_scheduled( 'pcw_workflow_scheduled_check' );
		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		
		// Calcular tempo desde a última execução esperada
		$is_overdue = false;
		$hours_overdue = 0;
		
		if ( $next_scheduled ) {
			// Se o próximo agendamento já passou, está atrasado
			if ( $next_scheduled < time() ) {
				$is_overdue = true;
				$hours_overdue = round( ( time() - $next_scheduled ) / 3600, 1 );
			}
		}
		
		// Verificar se há workflows agendados ativos
		$manager = PCW_Workflow_Manager::instance();
		$scheduled_workflows = $manager->get_by_trigger( 'scheduled_order_check' );
		$has_scheduled = ! empty( $scheduled_workflows );
		
		// Status geral
		$status_class = 'pcw-cron-ok';
		$status_icon = 'yes-alt';
		$status_text = __( 'Cron funcionando', 'person-cash-wallet' );
		
		if ( ! $next_scheduled ) {
			$status_class = 'pcw-cron-error';
			$status_icon = 'warning';
			$status_text = __( 'Cron NÃO agendado', 'person-cash-wallet' );
		} elseif ( $is_overdue && $hours_overdue > 2 ) {
			$status_class = 'pcw-cron-error';
			$status_icon = 'warning';
			$status_text = sprintf( __( 'Atrasado %.1f horas', 'person-cash-wallet' ), $hours_overdue );
		} elseif ( $is_overdue ) {
			$status_class = 'pcw-cron-warning';
			$status_icon = 'clock';
			$status_text = __( 'Aguardando execução', 'person-cash-wallet' );
		}
		
		if ( ! $has_scheduled ) {
			return; // Não mostrar se não há workflows agendados
		}
		?>
		<div class="pcw-cron-status-card <?php echo esc_attr( $status_class ); ?>" style="margin-bottom: 20px; padding: 15px 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
			<div style="display: flex; align-items: center; gap: 10px;">
				<span class="dashicons dashicons-<?php echo esc_attr( $status_icon ); ?>" style="font-size: 24px; <?php echo $status_class === 'pcw-cron-ok' ? 'color: #10b981;' : ( $status_class === 'pcw-cron-warning' ? 'color: #f59e0b;' : 'color: #ef4444;' ); ?>"></span>
				<div>
					<strong><?php esc_html_e( 'Cron de Workflows Agendados', 'person-cash-wallet' ); ?></strong>
					<div style="font-size: 12px; color: #64748b;">
						<?php echo esc_html( $status_text ); ?>
					</div>
				</div>
			</div>
			
			<div style="display: flex; gap: 20px; flex-wrap: wrap; font-size: 13px;">
				<div>
					<span style="color: #64748b;"><?php esc_html_e( 'Próxima execução:', 'person-cash-wallet' ); ?></span>
					<strong>
						<?php 
						if ( $next_scheduled ) {
							echo esc_html( date_i18n( 'd/m/Y H:i:s', $next_scheduled ) );
							if ( $is_overdue ) {
								echo ' <span style="color: #ef4444;">(' . esc_html__( 'atrasado', 'person-cash-wallet' ) . ')</span>';
							}
						} else {
							echo '<span style="color: #ef4444;">' . esc_html__( 'Não agendado', 'person-cash-wallet' ) . '</span>';
						}
						?>
					</strong>
				</div>
				
				<?php if ( $cron_disabled ) : ?>
				<div style="color: #f59e0b;">
					<span class="dashicons dashicons-info" style="font-size: 16px; vertical-align: middle;"></span>
					<?php esc_html_e( 'DISABLE_WP_CRON ativo (requer cron real)', 'person-cash-wallet' ); ?>
				</div>
				<?php endif; ?>
			</div>
			
			<div style="margin-left: auto; display: flex; gap: 10px;">
				<button type="button" class="button" id="pcw-fix-cron">
					<span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
					<?php esc_html_e( 'Reagendar Cron', 'person-cash-wallet' ); ?>
				</button>
				
				<button type="button" class="button button-primary" id="pcw-run-cron-now">
					<span class="dashicons dashicons-controls-play" style="vertical-align: middle;"></span>
					<?php esc_html_e( 'Executar Agora', 'person-cash-wallet' ); ?>
				</button>
			</div>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('#pcw-run-cron-now').on('click', function() {
				var btn = $(this);
				btn.prop('disabled', true).find('.dashicons').removeClass('dashicons-controls-play').addClass('dashicons-update spin');
				
				$.post(ajaxurl, {
					action: 'pcw_run_all_scheduled_workflows',
					nonce: '<?php echo wp_create_nonce( 'pcw_workflows' ); ?>'
				}, function(response) {
					btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-controls-play');
					if (response.success) {
						alert(response.data.message || 'Cron executado com sucesso!');
						location.reload();
					} else {
						alert(response.data.message || 'Erro ao executar cron');
					}
				}).fail(function() {
					btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-controls-play');
					alert('Erro de conexão');
				});
			});
			
			$('#pcw-fix-cron').on('click', function() {
				var btn = $(this);
				btn.prop('disabled', true);
				
				$.post(ajaxurl, {
					action: 'pcw_fix_workflow_cron',
					nonce: '<?php echo wp_create_nonce( 'pcw_workflows' ); ?>'
				}, function(response) {
					if (response.success) {
						alert(response.data.message || 'Cron reagendado!');
						location.reload();
					} else {
						alert(response.data.message || 'Erro ao reagendar');
						btn.prop('disabled', false);
					}
				}).fail(function() {
					alert('Erro de conexão');
					btn.prop('disabled', false);
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Renderizar lista de workflows
	 */
	private function render_list() {
		$manager = PCW_Workflow_Manager::instance();
		$workflows = $manager->get_all();
		$count_active = $manager->count( 'active' );
		$count_inactive = $manager->count( 'inactive' );

		?>
		<!-- Page Header -->
		<div class="pcw-page-header">
			<div>
				<h1>
					<span class="dashicons dashicons-randomize"></span>
					<?php esc_html_e( 'Workflows', 'person-cash-wallet' ); ?>
				</h1>
				<p class="description"><?php esc_html_e( 'Automatize ações baseadas em eventos do sistema', 'person-cash-wallet' ); ?></p>
			</div>
			<div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-workflows&action=new' ) ); ?>" class="button pcw-button-primary pcw-button-icon">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Novo Workflow', 'person-cash-wallet' ); ?>
				</a>
			</div>
		</div>

		<!-- Stats Cards -->
		<div class="pcw-stats-mini">
			<div class="pcw-stat-mini">
				<span class="pcw-stat-mini-value"><?php echo esc_html( count( $workflows ) ); ?></span>
				<span class="pcw-stat-mini-label"><?php esc_html_e( 'Total', 'person-cash-wallet' ); ?></span>
			</div>
			<div class="pcw-stat-mini pcw-stat-success">
				<span class="pcw-stat-mini-value"><?php echo esc_html( $count_active ); ?></span>
				<span class="pcw-stat-mini-label"><?php esc_html_e( 'Ativos', 'person-cash-wallet' ); ?></span>
			</div>
			<div class="pcw-stat-mini pcw-stat-warning">
				<span class="pcw-stat-mini-value"><?php echo esc_html( $count_inactive ); ?></span>
				<span class="pcw-stat-mini-label"><?php esc_html_e( 'Inativos', 'person-cash-wallet' ); ?></span>
			</div>
		</div>

		<?php $this->render_cron_status(); ?>

		<!-- Lista de Workflows -->
		<div class="pcw-card">
			<div class="pcw-card-body" style="padding: 0;">
				<?php if ( empty( $workflows ) ) : ?>
					<div class="pcw-empty-state">
						<span class="dashicons dashicons-randomize"></span>
						<h3><?php esc_html_e( 'Nenhum workflow criado', 'person-cash-wallet' ); ?></h3>
						<p><?php esc_html_e( 'Crie seu primeiro workflow para automatizar ações', 'person-cash-wallet' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-workflows&action=new' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Criar Workflow', 'person-cash-wallet' ); ?>
						</a>
					</div>
				<?php else : ?>
					<table class="pcw-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Nome', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Gatilho', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Ações', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Execuções', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Última Execução', 'person-cash-wallet' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $workflows as $workflow ) : 
								$trigger = PCW_Workflow_Triggers::get( $workflow->trigger_type );
								$trigger_name = $trigger ? $trigger['name'] : $workflow->trigger_type;
								$actions_count = is_array( $workflow->actions ) ? count( $workflow->actions ) : 0;
							?>
							<tr data-id="<?php echo esc_attr( $workflow->id ); ?>">
								<td>
									<label class="pcw-toggle-switch">
										<input type="checkbox" class="pcw-workflow-toggle" 
											data-id="<?php echo esc_attr( $workflow->id ); ?>"
											<?php checked( $workflow->status, 'active' ); ?>>
										<span class="pcw-toggle-slider"></span>
									</label>
								</td>
								<td>
									<strong><?php echo esc_html( $workflow->name ); ?></strong>
									<?php if ( ! empty( $workflow->description ) ) : ?>
										<p class="description"><?php echo esc_html( $workflow->description ); ?></p>
									<?php endif; ?>
								</td>
								<td>
									<span class="pcw-badge pcw-badge-info">
										<?php echo esc_html( $trigger_name ); ?>
									</span>
								</td>
								<td>
									<span class="pcw-badge">
										<?php echo esc_html( $actions_count ); ?> <?php esc_html_e( 'ação(ões)', 'person-cash-wallet' ); ?>
									</span>
								</td>
								<td><?php echo esc_html( number_format_i18n( $workflow->execution_count ) ); ?></td>
								<td>
									<?php 
									if ( $workflow->last_execution ) {
										echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $workflow->last_execution ) ) );
									} else {
										esc_html_e( 'Nunca', 'person-cash-wallet' );
									}
									?>
								</td>
								<td class="pcw-actions-cell">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-workflows&action=analytics&id=' . $workflow->id ) ); ?>" 
										class="button button-small" title="<?php esc_attr_e( 'Analytics', 'person-cash-wallet' ); ?>">
										<span class="dashicons dashicons-chart-bar"></span>
									</a>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-workflows&action=edit&id=' . $workflow->id ) ); ?>" 
										class="button button-small" title="<?php esc_attr_e( 'Editar', 'person-cash-wallet' ); ?>">
										<span class="dashicons dashicons-edit"></span>
									</a>
									<button type="button" class="button button-small pcw-delete-workflow" 
										data-id="<?php echo esc_attr( $workflow->id ); ?>"
										title="<?php esc_attr_e( 'Excluir', 'person-cash-wallet' ); ?>">
										<span class="dashicons dashicons-trash"></span>
									</button>
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
	 * @param int $id ID do workflow (0 para novo).
	 */
	private function render_edit_form( $id ) {
		$workflow = null;
		$is_new = true;

		if ( $id > 0 ) {
			$manager = PCW_Workflow_Manager::instance();
			$workflow = $manager->get( $id );
			$is_new = false;
		}

		?>
		<!-- Page Header -->
		<div class="pcw-page-header">
			<div>
				<h1>
					<span class="dashicons dashicons-randomize"></span>
					<?php echo $is_new ? esc_html__( 'Novo Workflow', 'person-cash-wallet' ) : esc_html__( 'Editar Workflow', 'person-cash-wallet' ); ?>
				</h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-workflows' ) ); ?>" class="pcw-back-link">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Voltar para lista', 'person-cash-wallet' ); ?>
				</a>
			</div>
		</div>

		<form id="pcw-workflow-form" class="pcw-workflow-form">
			<input type="hidden" name="workflow_id" value="<?php echo esc_attr( $id ); ?>">

			<!-- Informações Básicas -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'Informações Básicas', 'person-cash-wallet' ); ?>
					</h2>
				</div>
				<div class="pcw-card-body">
					<div class="pcw-form-row">
						<div class="pcw-form-group pcw-form-group-wide">
							<label for="workflow_name"><?php esc_html_e( 'Nome do Workflow', 'person-cash-wallet' ); ?> *</label>
							<input type="text" id="workflow_name" name="name" value="<?php echo esc_attr( $workflow ? $workflow->name : '' ); ?>" required placeholder="<?php esc_attr_e( 'Ex: Notificar WhatsApp em novo pedido', 'person-cash-wallet' ); ?>">
						</div>
					</div>
					<div class="pcw-form-row">
						<div class="pcw-form-group pcw-form-group-wide">
							<label for="workflow_description"><?php esc_html_e( 'Descrição', 'person-cash-wallet' ); ?></label>
							<textarea id="workflow_description" name="description" rows="2" placeholder="<?php esc_attr_e( 'Descrição opcional do workflow', 'person-cash-wallet' ); ?>"><?php echo esc_textarea( $workflow ? $workflow->description : '' ); ?></textarea>
						</div>
					</div>
				</div>
			</div>

			<!-- Gatilho -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-flag"></span>
						<?php esc_html_e( 'Gatilho - Quando Executar', 'person-cash-wallet' ); ?>
					</h2>
				</div>
				<div class="pcw-card-body">
					<div class="pcw-form-row">
						<div class="pcw-form-group pcw-form-group-wide">
							<label for="trigger_type"><?php esc_html_e( 'Selecione o Gatilho', 'person-cash-wallet' ); ?> *</label>
							<select id="trigger_type" name="trigger_type" required>
								<option value=""><?php esc_html_e( '-- Selecione --', 'person-cash-wallet' ); ?></option>
								<?php
								$grouped_triggers = PCW_Workflow_Triggers::get_grouped();
								foreach ( $grouped_triggers as $group_id => $group ) :
								?>
									<optgroup label="<?php echo esc_attr( $group['label'] ); ?>">
										<?php foreach ( $group['triggers'] as $trigger_id => $trigger ) : ?>
											<option value="<?php echo esc_attr( $trigger_id ); ?>" <?php selected( $workflow ? $workflow->trigger_type : '', $trigger_id ); ?>>
												<?php echo esc_html( $trigger['name'] ); ?>
											</option>
										<?php endforeach; ?>
									</optgroup>
								<?php endforeach; ?>
							</select>
							<p class="description" id="trigger_description"></p>
						</div>
					</div>

					<!-- Configuração do Gatilho (dinâmico) -->
					<div id="trigger_config_container" style="display: none;">
						<hr>
						<div id="trigger_config_fields"></div>
					</div>

					<?php if ( $workflow && ! empty( $workflow->trigger_config ) ) : ?>
						<script>var pcwWorkflowData = { trigger_config: <?php echo wp_json_encode( $workflow->trigger_config ); ?> };</script>
					<?php endif; ?>
				</div>
			</div>

			<!-- Condições -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-filter"></span>
						<?php esc_html_e( 'Condições - Filtrar Execução', 'person-cash-wallet' ); ?>
					</h2>
					<span class="pcw-badge pcw-badge-light"><?php esc_html_e( 'Opcional', 'person-cash-wallet' ); ?></span>
				</div>
				<div class="pcw-card-body">
					<div class="pcw-info-box">
						<span class="dashicons dashicons-info"></span>
						<?php esc_html_e( 'Adicione condições para filtrar quando o workflow deve ser executado. Se nenhuma condição for adicionada, o workflow sempre executará.', 'person-cash-wallet' ); ?>
					</div>

					<div class="pcw-conditions-logic">
						<label><?php esc_html_e( 'Lógica:', 'person-cash-wallet' ); ?></label>
						<select id="conditions_logic" name="conditions_logic">
							<option value="AND" <?php selected( isset( $workflow->conditions['logic'] ) ? $workflow->conditions['logic'] : 'AND', 'AND' ); ?>><?php esc_html_e( 'Todas as condições (E)', 'person-cash-wallet' ); ?></option>
							<option value="OR" <?php selected( isset( $workflow->conditions['logic'] ) ? $workflow->conditions['logic'] : 'AND', 'OR' ); ?>><?php esc_html_e( 'Qualquer condição (OU)', 'person-cash-wallet' ); ?></option>
						</select>
					</div>

					<div id="conditions_container">
						<?php
						$conditions = $workflow && isset( $workflow->conditions['rules'] ) ? $workflow->conditions['rules'] : array();
						if ( ! empty( $conditions ) ) {
							echo '<script>var pcwInitialConditions = ' . wp_json_encode( $conditions ) . ';</script>';
						}
						?>
					</div>

					<button type="button" id="add_condition" class="button">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Adicionar Condição', 'person-cash-wallet' ); ?>
					</button>
				</div>
			</div>

			<!-- Ações -->
			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2>
						<span class="dashicons dashicons-controls-play"></span>
						<?php esc_html_e( 'Ações - O Que Fazer', 'person-cash-wallet' ); ?>
					</h2>
				</div>
				<div class="pcw-card-body">
					<div id="actions_container" class="pcw-actions-list">
						<?php
						$actions = $workflow && is_array( $workflow->actions ) ? $workflow->actions : array();
						if ( ! empty( $actions ) ) {
							echo '<script>var pcwInitialActions = ' . wp_json_encode( $actions ) . ';</script>';
						}
						?>
					</div>

					<button type="button" id="add_action" class="button button-primary">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Adicionar Ação', 'person-cash-wallet' ); ?>
					</button>
				</div>
			</div>

			<!-- Botões de Ação -->
			<div class="pcw-form-actions" style="display: flex; gap: 10px; align-items: center;">
				<button type="submit" class="button pcw-button-primary pcw-button-icon pcw-button-large">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Salvar Workflow', 'person-cash-wallet' ); ?>
				</button>

				<?php if ( ! $is_new ) : ?>
				<button type="button" id="pcw-test-workflow" class="button button-large" style="background: #2271b1; color: #fff; border-color: #2271b1;">
					<span class="dashicons dashicons-controls-play" style="margin-top: 4px;"></span>
					<?php esc_html_e( 'Executar Teste', 'person-cash-wallet' ); ?>
				</button>
				<?php endif; ?>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-workflows' ) ); ?>" class="button button-large">
					<?php esc_html_e( 'Cancelar', 'person-cash-wallet' ); ?>
				</a>
			</div>

			<?php if ( ! $is_new ) : ?>
			<!-- Modal de Teste -->
			<div id="pcw-test-workflow-modal" style="display: none;">
				<div class="pcw-modal-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100000;"></div>
				<div class="pcw-modal-content" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 30px; border-radius: 8px; z-index: 100001; width: 500px; max-width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
					<h3 style="margin: 0 0 20px; display: flex; align-items: center; gap: 10px;">
						<span class="dashicons dashicons-controls-play" style="color: #2271b1;"></span>
						<?php esc_html_e( 'Executar Teste do Workflow', 'person-cash-wallet' ); ?>
					</h3>

					<div style="background: #fff8e5; border-left: 4px solid #ffb900; padding: 12px; margin-bottom: 20px; border-radius: 4px;">
						<strong>⚠️ <?php esc_html_e( 'Atenção:', 'person-cash-wallet' ); ?></strong>
						<?php esc_html_e( 'Este teste executará as ações REAIS do workflow (emails, WhatsApp, webhooks). Use com cuidado.', 'person-cash-wallet' ); ?>
					</div>

					<div class="pcw-form-group" style="margin-bottom: 20px;">
						<label style="font-weight: 600; display: block; margin-bottom: 8px;">
							<?php esc_html_e( 'Modo de Teste:', 'person-cash-wallet' ); ?>
						</label>
						<label style="display: block; padding: 10px; background: #f0f6fc; border-radius: 4px; margin-bottom: 8px; cursor: pointer;">
							<input type="radio" name="test_mode" value="sample" checked style="margin-right: 8px;">
							<strong><?php esc_html_e( 'Dados de Exemplo', 'person-cash-wallet' ); ?></strong>
							<p style="margin: 5px 0 0 24px; color: #666; font-size: 12px;">
								<?php esc_html_e( 'Usa dados fictícios para teste (Cliente Teste, email@exemplo.com)', 'person-cash-wallet' ); ?>
							</p>
						</label>
						<label style="display: block; padding: 10px; background: #f0f6fc; border-radius: 4px; cursor: pointer;">
							<input type="radio" name="test_mode" value="real" style="margin-right: 8px;">
							<strong><?php esc_html_e( 'Dados Reais', 'person-cash-wallet' ); ?></strong>
							<p style="margin: 5px 0 0 24px; color: #666; font-size: 12px;">
								<?php esc_html_e( 'Busca o primeiro registro real que corresponde ao gatilho', 'person-cash-wallet' ); ?>
							</p>
						</label>
					</div>

					<div id="pcw-test-override" style="margin-bottom: 20px;">
						<label style="font-weight: 600; display: block; margin-bottom: 8px;">
							<?php esc_html_e( 'Sobrescrever destinatário (opcional):', 'person-cash-wallet' ); ?>
						</label>
						<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
							<input type="email" id="test_override_email" placeholder="<?php esc_attr_e( 'Email de teste', 'person-cash-wallet' ); ?>" class="regular-text" style="width: 100%;">
							<input type="text" id="test_override_phone" placeholder="<?php esc_attr_e( 'WhatsApp (5511999...)', 'person-cash-wallet' ); ?>" class="regular-text" style="width: 100%;">
						</div>
						<p class="description" style="margin-top: 5px;">
							<?php esc_html_e( 'Se preenchido, as mensagens serão enviadas para estes contatos em vez dos originais.', 'person-cash-wallet' ); ?>
						</p>
					</div>

					<div id="pcw-test-result" style="display: none; margin-bottom: 20px;"></div>

					<div style="display: flex; gap: 10px; justify-content: flex-end;">
						<button type="button" class="button pcw-close-test-modal">
							<?php esc_html_e( 'Cancelar', 'person-cash-wallet' ); ?>
						</button>
						<button type="button" id="pcw-run-test" class="button button-primary">
							<span class="dashicons dashicons-controls-play" style="margin-top: 4px;"></span>
							<?php esc_html_e( 'Executar Agora', 'person-cash-wallet' ); ?>
						</button>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * AJAX: Salvar workflow
	 */
	public function ajax_save_workflow() {
		check_ajax_referer( 'pcw_workflows', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'person-cash-wallet' ) ) );
		}

		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( $_POST['workflow_id'] ) : 0;
		$name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';
		$trigger_type = isset( $_POST['trigger_type'] ) ? sanitize_text_field( $_POST['trigger_type'] ) : '';
		$trigger_config = isset( $_POST['trigger_config'] ) ? $_POST['trigger_config'] : array();
		$conditions = isset( $_POST['conditions'] ) ? $_POST['conditions'] : array();
		$actions = isset( $_POST['actions'] ) ? $_POST['actions'] : array();

		if ( empty( $name ) || empty( $trigger_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Nome e gatilho são obrigatórios.', 'person-cash-wallet' ) ) );
		}

		$manager = PCW_Workflow_Manager::instance();

		$data = array(
			'name'           => $name,
			'description'    => $description,
			'trigger_type'   => $trigger_type,
			'trigger_config' => $trigger_config,
			'conditions'     => $conditions,
			'actions'        => $actions,
		);

		if ( $workflow_id > 0 ) {
			$result = $manager->update( $workflow_id, $data );
		} else {
			$workflow_id = $manager->create( $data );
			$result = $workflow_id > 0;
		}

		if ( $result ) {
			wp_send_json_success( array(
				'message'     => __( 'Workflow salvo com sucesso!', 'person-cash-wallet' ),
				'workflow_id' => $workflow_id,
				'redirect'    => admin_url( 'admin.php?page=pcw-workflows' ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Erro ao salvar workflow.', 'person-cash-wallet' ) ) );
		}
	}

	/**
	 * AJAX: Deletar workflow
	 */
	public function ajax_delete_workflow() {
		check_ajax_referer( 'pcw_workflows', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'person-cash-wallet' ) ) );
		}

		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( $_POST['workflow_id'] ) : 0;

		if ( ! $workflow_id ) {
			wp_send_json_error( array( 'message' => __( 'ID inválido.', 'person-cash-wallet' ) ) );
		}

		$manager = PCW_Workflow_Manager::instance();
		$result = $manager->delete( $workflow_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Workflow excluído!', 'person-cash-wallet' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Erro ao excluir.', 'person-cash-wallet' ) ) );
		}
	}

	/**
	 * AJAX: Toggle workflow status
	 */
	public function ajax_toggle_workflow() {
		check_ajax_referer( 'pcw_workflows', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'person-cash-wallet' ) ) );
		}

		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( $_POST['workflow_id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'inactive';

		if ( ! $workflow_id ) {
			wp_send_json_error( array( 'message' => __( 'ID inválido.', 'person-cash-wallet' ) ) );
		}

		$manager = PCW_Workflow_Manager::instance();
		$result = $manager->update( $workflow_id, array( 'status' => $status ) );

		if ( $result ) {
			wp_send_json_success( array( 'status' => $status ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Erro ao atualizar.', 'person-cash-wallet' ) ) );
		}
	}

	/**
	 * AJAX: Obter variáveis do gatilho
	 */
	public function ajax_get_trigger_variables() {
		check_ajax_referer( 'pcw_workflows', 'nonce' );

		$trigger_type = isset( $_POST['trigger_type'] ) ? sanitize_text_field( $_POST['trigger_type'] ) : '';

		if ( empty( $trigger_type ) ) {
			wp_send_json_error();
		}

		$variables = PCW_Workflow_Triggers::get_variables( $trigger_type );

		wp_send_json_success( array( 'variables' => $variables ) );
	}

	/**
	 * AJAX: Testar webhook
	 */
	public function ajax_test_webhook() {
		check_ajax_referer( 'pcw_workflows', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'person-cash-wallet' ) ) );
		}

		$config = isset( $_POST['config'] ) ? $_POST['config'] : array();

		// Contexto de teste com dados fictícios
		$test_context = array(
			'customer_name'   => 'Cliente Teste',
			'customer_email'  => 'teste@exemplo.com',
			'customer_phone'  => '5511999998888',
			'order_id'        => '12345',
			'order_total'     => 'R$ 150,00',
			'cashback_amount' => 'R$ 15,00',
			'wallet_balance'  => 'R$ 50,00',
		);

		$result = PCW_Workflow_Actions::execute( 'webhook', $config, $test_context );

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message'       => __( 'Webhook enviado com sucesso!', 'person-cash-wallet' ),
				'response_code' => isset( $result['response_code'] ) ? $result['response_code'] : '',
				'response_body' => isset( $result['response_body'] ) ? $result['response_body'] : '',
			) );
		} else {
			wp_send_json_error( array(
				'message' => isset( $result['error'] ) ? $result['error'] : __( 'Erro desconhecido', 'person-cash-wallet' ),
			) );
		}
	}

	/**
	 * AJAX: Gerar mensagem com IA
	 */
	public function ajax_generate_workflow_message() {
		check_ajax_referer( 'pcw_workflows', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'person-cash-wallet' ) ) );
		}

		if ( ! class_exists( 'PCW_OpenAI' ) ) {
			wp_send_json_error( array( 'message' => __( 'Módulo de IA não disponível.', 'person-cash-wallet' ) ) );
		}

		$ai = PCW_OpenAI::instance();
		if ( ! $ai->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'IA não está configurada. Vá em Configurações > IA.', 'person-cash-wallet' ) ) );
		}

		$trigger_type = isset( $_POST['trigger_type'] ) ? sanitize_text_field( $_POST['trigger_type'] ) : '';
		$workflow_name = isset( $_POST['workflow_name'] ) ? sanitize_text_field( $_POST['workflow_name'] ) : '';

		if ( empty( $trigger_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Selecione um gatilho primeiro.', 'person-cash-wallet' ) ) );
		}

		// Obter informações do gatilho
		$trigger = PCW_Workflow_Triggers::get( $trigger_type );
		$trigger_name = $trigger ? $trigger['name'] : $trigger_type;
		$trigger_variables = $trigger ? $trigger['variables'] : array();

		// Nome da loja
		$site_name = get_bloginfo( 'name' );

		// Montar contexto baseado no gatilho
		$context = $this->build_ai_context_for_trigger( $trigger_type );

		// Montar lista de variáveis
		$variables_list = '';
		foreach ( $trigger_variables as $var => $description ) {
			$variables_list .= "- {" . $var . "}: " . $description . "\n";
		}

		// Prompt para a IA
		$system_prompt = "Você é um especialista em criar mensagens profissionais e amigáveis para WhatsApp Business. " .
			"Sua tarefa é criar mensagens curtas (máximo 160 caracteres), claras e que mantenham um tom amigável mas profissional. " .
			"A mensagem deve ser em português do Brasil. " .
			"Use emojis quando apropriado, mas com moderação (máximo 2 emojis). " .
			"A mensagem será enviada pela loja '{$site_name}'.";

		$user_prompt = "Crie uma mensagem de WhatsApp para o seguinte cenário:\n\n" .
			"Gatilho: {$trigger_name}\n" .
			"Contexto: {$context}\n\n" .
			"VARIÁVEIS DISPONÍVEIS (use as mais relevantes):\n" .
			$variables_list . "\n" .
			"IMPORTANTE:\n" .
			"- Use as variáveis no formato {nome_variavel}\n" .
			"- Seja conciso e direto\n" .
			"- Mantenha tom amigável e profissional\n\n" .
			"Retorne APENAS a mensagem, sem explicações adicionais.";

		$message = $ai->generate_text( $user_prompt, $system_prompt, array( 'max_tokens' => 200, 'temperature' => 0.8 ) );

		if ( is_wp_error( $message ) ) {
			wp_send_json_error( array( 'message' => $message->get_error_message() ) );
		}

		// Limpar possíveis aspas ou formatação extra
		$message = trim( $message, '"\'`' );

		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * Construir contexto para IA baseado no gatilho
	 *
	 * @param string $trigger_type Tipo do gatilho.
	 * @return string
	 */
	private function build_ai_context_for_trigger( $trigger_type ) {
		$contexts = array(
			// Pedidos
			'order_status_changed' => 'O status do pedido foi alterado. Informe o cliente sobre a mudança.',
			'order_created'        => 'Um novo pedido foi criado. Confirme o recebimento ao cliente.',

			// Cashback
			'cashback_earned'   => 'O cliente ganhou cashback! Parabenize e explique que o valor pode ser usado na próxima compra.',
			'cashback_expiring' => 'O cashback do cliente está prestes a expirar! Alerte sobre a data e incentive a usar.',
			'cashback_expired'  => 'O cashback do cliente expirou. Seja empático e sugira novas compras.',
			'cashback_used'     => 'O cliente usou seu cashback. Confirme o uso e agradeça.',

			// Níveis
			'level_achieved' => 'O cliente alcançou um novo nível VIP! Parabenize e explique os benefícios.',
			'level_expiring' => 'O nível do cliente está prestes a expirar. Incentive a manter o status.',

			// Wallet
			'wallet_credit' => 'Crédito foi adicionado à wallet do cliente. Informe o valor e incentive a usar.',
			'wallet_debit'  => 'Débito foi realizado na wallet. Confirme a transação.',

			// Cliente
			'customer_registered' => 'Um novo cliente se cadastrou! Dê as boas-vindas de forma calorosa.',

			// Agendado
			'scheduled_order_check' => 'Verificação periódica de pedidos. Use para cobranças ou lembretes.',
		);

		return isset( $contexts[ $trigger_type ] ) ? $contexts[ $trigger_type ] : 'Notifique o cliente sobre um evento importante.';
	}

	/**
	 * AJAX: Executar teste do workflow
	 */
	public function ajax_test_run_workflow() {
		check_ajax_referer( 'pcw_workflows', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'person-cash-wallet' ) ) );
		}

		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( $_POST['workflow_id'] ) : 0;
		$test_mode = isset( $_POST['test_mode'] ) ? sanitize_text_field( $_POST['test_mode'] ) : 'sample';
		$override_email = isset( $_POST['override_email'] ) ? sanitize_email( $_POST['override_email'] ) : '';
		$override_phone = isset( $_POST['override_phone'] ) ? sanitize_text_field( $_POST['override_phone'] ) : '';

		if ( ! $workflow_id ) {
			wp_send_json_error( array( 'message' => __( 'ID do workflow inválido.', 'person-cash-wallet' ) ) );
		}

		// Carregar workflow
		global $wpdb;
		$table = $wpdb->prefix . 'pcw_workflows';
		$workflow = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $workflow_id ) );

		if ( ! $workflow ) {
			wp_send_json_error( array( 'message' => __( 'Workflow não encontrado.', 'person-cash-wallet' ) ) );
		}

		$trigger_type = $workflow->trigger_type;
		$actions = json_decode( $workflow->actions, true ) ?: array();

		if ( empty( $actions ) ) {
			wp_send_json_error( array( 'message' => __( 'Workflow não tem ações configuradas.', 'person-cash-wallet' ) ) );
		}

		// Obter contexto baseado no modo de teste
		if ( 'real' === $test_mode ) {
			$context = $this->get_real_context_for_trigger( $trigger_type );
		} else {
			$context = $this->get_sample_context_for_trigger( $trigger_type );
		}

		if ( ! $context ) {
			wp_send_json_error( array( 'message' => __( 'Não foi possível obter dados para o teste. Verifique se há registros que correspondem ao gatilho.', 'person-cash-wallet' ) ) );
		}

		// Sobrescrever destinatários se fornecido
		if ( ! empty( $override_email ) ) {
			$context['customer_email'] = $override_email;
		}
		if ( ! empty( $override_phone ) ) {
			$context['customer_phone'] = $override_phone;
		}

		// Adicionar info do workflow ao contexto
		$context['workflow_id'] = $workflow_id;
		$context['workflow_name'] = $workflow->name;
		$context['is_test'] = true;

		// Executar cada ação
		$results = array();
		$success_count = 0;
		$error_count = 0;

		foreach ( $actions as $index => $action ) {
			$action_type = isset( $action['type'] ) ? $action['type'] : '';
			$action_config = isset( $action['config'] ) ? $action['config'] : array();

			if ( empty( $action_type ) ) {
				continue;
			}

			// Executar ação
			$result = PCW_Workflow_Actions::execute( $action_type, $action_config, $context );

			$action_info = PCW_Workflow_Actions::get( $action_type );
			$action_name = $action_info ? $action_info['name'] : $action_type;

			$results[] = array(
				'action'  => $action_name,
				'success' => $result['success'],
				'message' => $result['success'] 
					? ( isset( $result['message'] ) ? $result['message'] : __( 'Executado com sucesso', 'person-cash-wallet' ) )
					: ( isset( $result['error'] ) ? $result['error'] : __( 'Erro desconhecido', 'person-cash-wallet' ) ),
			);

			if ( $result['success'] ) {
				$success_count++;
			} else {
				$error_count++;
			}
		}

		// Atualizar contador de execuções
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET execution_count = execution_count + 1, last_execution = %s WHERE id = %d",
				current_time( 'mysql' ),
				$workflow_id
			)
		);

		wp_send_json_success( array(
			'message'       => sprintf(
				/* translators: %1$d: success count, %2$d: error count */
				__( 'Teste concluído! %1$d ação(ões) executada(s) com sucesso, %2$d erro(s).', 'person-cash-wallet' ),
				$success_count,
				$error_count
			),
			'results'       => $results,
			'context_used'  => array(
				'customer_name'  => $context['customer_name'] ?? '',
				'customer_email' => $context['customer_email'] ?? '',
				'customer_phone' => $context['customer_phone'] ?? '',
			),
		) );
	}

	/**
	 * Obter contexto de exemplo para teste
	 *
	 * @param string $trigger_type Tipo do gatilho.
	 * @return array
	 */
	private function get_sample_context_for_trigger( $trigger_type ) {
		$base_context = array(
			'customer_name'       => 'Cliente Teste',
			'customer_first_name' => 'Cliente',
			'customer_email'      => 'teste@exemplo.com',
			'customer_phone'      => '5511999998888',
			'site_name'           => get_bloginfo( 'name' ),
			'date'                => date_i18n( get_option( 'date_format' ) ),
		);

		// Adicionar dados específicos por tipo de gatilho
		switch ( $trigger_type ) {
			case 'order_status_changed':
			case 'order_created':
			case 'scheduled_order_check':
				$base_context['order_id']         = '12345';
				$base_context['order_number']     = '12345';
				$base_context['order_total']      = 'R$ 150,00';
				$base_context['order_status']     = 'processing';
				$base_context['order_status_old'] = 'pending';
				$base_context['payment_method']   = 'PIX';
				$base_context['payment_link']     = home_url( '/checkout/order-pay/12345/' );
				$base_context['products_list']    = 'Produto Exemplo x1';
				$base_context['order_date']       = date_i18n( get_option( 'date_format' ) );
				break;

			case 'cashback_earned':
			case 'cashback_expiring':
			case 'cashback_expired':
			case 'cashback_used':
				$base_context['cashback_amount']  = 'R$ 15,00';
				$base_context['wallet_balance']   = 'R$ 50,00';
				$base_context['expiration_date']  = date_i18n( get_option( 'date_format' ), strtotime( '+30 days' ) );
				$base_context['days_remaining']   = '7';
				$base_context['order_id']         = '12345';
				break;

			case 'level_achieved':
			case 'level_expiring':
				$base_context['level_name']       = 'VIP Gold';
				$base_context['level_number']     = '3';
				$base_context['old_level_name']   = 'VIP Silver';
				$base_context['expiration_date']  = date_i18n( get_option( 'date_format' ), strtotime( '+30 days' ) );
				$base_context['days_remaining']   = '15';
				break;

			case 'wallet_credit':
			case 'wallet_debit':
				$base_context['credit_amount']    = 'R$ 25,00';
				$base_context['debit_amount']     = 'R$ 25,00';
				$base_context['credit_source']    = 'cashback';
				$base_context['wallet_balance']   = 'R$ 75,00';
				break;

			case 'customer_registered':
				$base_context['register_date']    = date_i18n( get_option( 'date_format' ) );
				break;
		}

		return $base_context;
	}

	/**
	 * Obter contexto real para teste
	 *
	 * @param string $trigger_type Tipo do gatilho.
	 * @return array|null
	 */
	private function get_real_context_for_trigger( $trigger_type ) {
		global $wpdb;

		// Base do contexto
		$context = array(
			'site_name' => get_bloginfo( 'name' ),
			'date'      => date_i18n( get_option( 'date_format' ) ),
		);

		switch ( $trigger_type ) {
			case 'order_status_changed':
			case 'order_created':
			case 'scheduled_order_check':
				// Buscar pedido mais recente
				$orders = wc_get_orders( array(
					'limit'   => 1,
					'orderby' => 'date',
					'order'   => 'DESC',
				) );

				if ( empty( $orders ) ) {
					return null;
				}

				$order = $orders[0];
				$context = array_merge( $context, $this->build_order_context( $order ) );
				break;

			case 'cashback_earned':
			case 'cashback_expiring':
			case 'cashback_expired':
			case 'cashback_used':
				// Buscar cashback mais recente
				$cashback_table = $wpdb->prefix . 'pcw_cashback';
				$cashback = $wpdb->get_row( "SELECT * FROM {$cashback_table} ORDER BY created_at DESC LIMIT 1" );

				if ( ! $cashback ) {
					// Fallback para dados de exemplo
					return $this->get_sample_context_for_trigger( $trigger_type );
				}

				$user = get_user_by( 'id', $cashback->user_id );
				if ( $user ) {
					$context['customer_name']       = $user->display_name;
					$context['customer_first_name'] = $user->first_name ?: $user->display_name;
					$context['customer_email']      = $user->user_email;
					$context['customer_phone']      = $this->get_user_phone( $user->ID );
				}

				$context['cashback_amount']  = $this->format_price_plain( $cashback->amount );
				$context['expiration_date']  = $cashback->expires_at ? date_i18n( get_option( 'date_format' ), strtotime( $cashback->expires_at ) ) : __( 'não expira', 'person-cash-wallet' );
				$context['days_remaining']   = $cashback->expires_at ? max( 0, floor( ( strtotime( $cashback->expires_at ) - time() ) / DAY_IN_SECONDS ) ) : __( 'ilimitado', 'person-cash-wallet' );
				$context['order_id']         = $cashback->order_id;

				// Saldo da wallet
				if ( class_exists( 'PCW_Wallet' ) && isset( $cashback->user_id ) ) {
					$wallet = new PCW_Wallet( $cashback->user_id );
					$context['wallet_balance'] = $this->format_price_plain( $wallet->get_balance() );
				}
				break;

			case 'customer_registered':
				// Buscar usuário mais recente
				$users = get_users( array(
					'number'  => 1,
					'orderby' => 'registered',
					'order'   => 'DESC',
					'role'    => 'customer',
				) );

				if ( empty( $users ) ) {
					return $this->get_sample_context_for_trigger( $trigger_type );
				}

				$user = $users[0];
				$context['customer_name']       = $user->display_name;
				$context['customer_first_name'] = $user->first_name ?: $user->display_name;
				$context['customer_email']      = $user->user_email;
				$context['customer_phone']      = $this->get_user_phone( $user->ID );
				$context['register_date']       = date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) );
				break;

			default:
				// Para gatilhos não mapeados, usar dados de exemplo
				return $this->get_sample_context_for_trigger( $trigger_type );
		}

		return $context;
	}

	/**
	 * Formatar preço como texto simples (sem HTML)
	 *
	 * @param float $amount Valor.
	 * @return string
	 */
	private function format_price_plain( $amount ) {
		// Formatar como moeda brasileira sem HTML
		return 'R$ ' . number_format( (float) $amount, 2, ',', '.' );
	}

	/**
	 * Obter telefone do usuário de vários campos possíveis
	 *
	 * @param int $user_id ID do usuário.
	 * @return string
	 */
	private function get_user_phone( $user_id ) {
		// Campos possíveis de telefone
		$phone_fields = array(
			'billing_phone',
			'billing_cellphone',
			'billing_phone_2',
			'billing_cel',
			'billing_mobile',
			'phone',
			'cellphone',
			'mobile',
		);

		$phone = '';
		foreach ( $phone_fields as $field ) {
			$phone = get_user_meta( $user_id, $field, true );
			if ( ! empty( $phone ) ) {
				break;
			}
		}

		// Limpar formatação do telefone
		if ( ! empty( $phone ) ) {
			$phone = preg_replace( '/[^0-9]/', '', $phone );
			// Adicionar código do país se não tiver
			if ( strlen( $phone ) <= 11 && substr( $phone, 0, 2 ) !== '55' ) {
				$phone = '55' . $phone;
			}
		}

		return $phone;
	}

	/**
	 * Construir contexto a partir de um pedido
	 *
	 * @param WC_Order $order Pedido.
	 * @return array
	 */
	private function build_order_context( $order ) {
		// Tentar pegar telefone de vários campos possíveis
		$phone = $order->get_billing_phone();
		
		// Se não encontrou no campo padrão, tentar campos customizados
		if ( empty( $phone ) ) {
			// Campos comuns de telefone/celular
			$phone_fields = array(
				'_billing_cellphone',
				'billing_cellphone',
				'_billing_phone_2',
				'billing_phone_2',
				'_billing_cel',
				'billing_cel',
				'_billing_mobile',
				'billing_mobile',
			);
			
			foreach ( $phone_fields as $field ) {
				$phone = $order->get_meta( $field );
				if ( ! empty( $phone ) ) {
					break;
				}
			}
		}

		// Limpar formatação do telefone (remover parênteses, traços, espaços)
		if ( ! empty( $phone ) ) {
			$phone = preg_replace( '/[^0-9]/', '', $phone );
			// Adicionar código do país se não tiver
			if ( strlen( $phone ) <= 11 && substr( $phone, 0, 2 ) !== '55' ) {
				$phone = '55' . $phone;
			}
		}

		$context = array(
			'customer_name'       => $order->get_formatted_billing_full_name(),
			'customer_first_name' => $order->get_billing_first_name(),
			'customer_email'      => $order->get_billing_email(),
			'customer_phone'      => $phone,
			'order_id'            => $order->get_id(),
			'order_number'        => $order->get_order_number(),
			'order_total'         => $this->format_price_plain( $order->get_total() ),
			'order_status'        => $order->get_status(),
			'order_status_old'    => 'pending',
			'payment_method'      => $order->get_payment_method_title(),
			'payment_link'        => $order->get_checkout_payment_url(),
			'order_date'          => date_i18n( get_option( 'date_format' ), $order->get_date_created()->getTimestamp() ),
		);

		// Lista de produtos
		$products = array();
		foreach ( $order->get_items() as $item ) {
			$products[] = $item->get_name() . ' x' . $item->get_quantity();
		}
		$context['products_list'] = implode( ', ', $products );

		return $context;
	}

	/**
	 * Renderizar página de Analytics
	 *
	 * @param int $workflow_id ID do workflow.
	 */
	private function render_analytics( $workflow_id ) {
		if ( ! $workflow_id ) {
			wp_die( __( 'Workflow inválido.', 'person-cash-wallet' ) );
		}

		global $wpdb;
		$manager = PCW_Workflow_Manager::instance();
		$workflow = $manager->get( $workflow_id );

		if ( ! $workflow ) {
			wp_die( __( 'Workflow não encontrado.', 'person-cash-wallet' ) );
		}

		$logs_table = $wpdb->prefix . 'pcw_workflow_logs';

		// Garantir que a tabela tem todas as colunas necessárias
		$this->ensure_workflow_logs_columns( $logs_table );

		// Período selecionado
		$period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : '30';
		$date_from = date( 'Y-m-d 00:00:00', strtotime( "-{$period} days", current_time( 'timestamp' ) ) );

		// Total de execuções no período
		$total_executions = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$logs_table} WHERE workflow_id = %d AND executed_at >= %s",
			$workflow_id,
			$date_from
		) );

		// Execuções bem-sucedidas
		$successful = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$logs_table} WHERE workflow_id = %d AND status = 'success' AND executed_at >= %s",
			$workflow_id,
			$date_from
		) );

		// Execuções com erro (status pode ser 'failed' ou 'error')
		$failed = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$logs_table} WHERE workflow_id = %d AND status IN ('error', 'failed') AND executed_at >= %s",
			$workflow_id,
			$date_from
		) );

		// Taxa de sucesso
		$success_rate = $total_executions > 0 ? round( ( $successful / $total_executions ) * 100, 1 ) : 0;

		// Tempo médio de execução
		$avg_execution_time = $wpdb->get_var( $wpdb->prepare(
			"SELECT AVG(execution_time) FROM {$logs_table} WHERE workflow_id = %d AND executed_at >= %s",
			$workflow_id,
			$date_from
		) );
		$avg_execution_time = $avg_execution_time ? round( $avg_execution_time, 2 ) : 0;

		// Execuções por dia (últimos 30 dias)
		$daily_stats = $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				DATE(executed_at) as date,
				COUNT(*) as total,
				SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
				SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error
			FROM {$logs_table}
			WHERE workflow_id = %d AND executed_at >= %s
			GROUP BY DATE(executed_at)
			ORDER BY date DESC
			LIMIT 30",
			$workflow_id,
			$date_from
		) );

		// Últimos logs
		$recent_logs = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$logs_table}
			WHERE workflow_id = %d
			ORDER BY executed_at DESC
			LIMIT 50",
			$workflow_id
		) );

		// Calcular projeção de disparos futuros
		$projected_triggers = $this->calculate_projected_triggers( $workflow );

		// Preparar dados para gráfico
		$chart_labels = array();
		$chart_success = array();
		$chart_error = array();
		
		foreach ( array_reverse( $daily_stats ) as $stat ) {
			$chart_labels[] = date_i18n( 'd/m', strtotime( $stat->date ) );
			$chart_success[] = (int) $stat->success;
			$chart_error[] = (int) $stat->error;
		}

		?>
		<?php
		// Mensagem de sucesso após atualização em massa
		if ( isset( $_GET['updated'] ) && isset( $_GET['exp_days'] ) ) {
			$updated_count = absint( $_GET['updated'] );
			$exp_days = absint( $_GET['exp_days'] );
			?>
			<div class="notice notice-success is-dismissible" style="margin-bottom: 20px;">
				<p>
					<strong>✅ <?php esc_html_e( 'Atualização concluída!', 'person-cash-wallet' ); ?></strong><br>
					<?php 
					printf(
						/* translators: 1: count of updated cashbacks 2: days until expiration */
						esc_html__( '%1$d cashbacks foram atualizados para expirar em %2$d dias.', 'person-cash-wallet' ),
						$updated_count,
						$exp_days
					);
					?>
				</p>
			</div>
			<?php
		}
		?>

		<!-- Page Header -->
		<div class="pcw-page-header">
			<div>
				<h1>
					<span class="dashicons dashicons-chart-bar"></span>
					<?php esc_html_e( 'Analytics do Workflow', 'person-cash-wallet' ); ?>
				</h1>
				<p class="description"><?php echo esc_html( $workflow->name ); ?></p>
			</div>
			<div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-workflows' ) ); ?>" class="button">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php esc_html_e( 'Voltar', 'person-cash-wallet' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-workflows&action=edit&id=' . $workflow_id ) ); ?>" class="button button-primary">
					<span class="dashicons dashicons-edit"></span>
					<?php esc_html_e( 'Editar Workflow', 'person-cash-wallet' ); ?>
				</a>
				<?php if ( 'scheduled_order_check' === $workflow->trigger_type ) : ?>
				<button type="button" id="pcw-run-scheduled-now" class="button" style="background: #f59e0b; border-color: #f59e0b; color: #fff;">
					<span class="dashicons dashicons-controls-play"></span>
					<?php esc_html_e( 'Executar Agora', 'person-cash-wallet' ); ?>
				</button>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( 'scheduled_order_check' === $workflow->trigger_type ) : ?>
		<!-- Info sobre Cron -->
		<div class="notice notice-info" style="margin-bottom: 20px;">
			<p>
				<strong><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Execução Automática:', 'person-cash-wallet' ); ?></strong>
				<?php 
				$next_run = wp_next_scheduled( 'pcw_workflow_scheduled_check' );
				if ( $next_run ) {
					$time_diff = $next_run - time();
					if ( $time_diff > 0 ) {
						$hours = floor( $time_diff / 3600 );
						$minutes = floor( ( $time_diff % 3600 ) / 60 );
						printf(
							/* translators: 1: hours 2: minutes */
							esc_html__( 'Próxima execução em %1$dh %2$dm (executa de hora em hora)', 'person-cash-wallet' ),
							$hours,
							$minutes
						);
					} else {
						esc_html_e( 'Execução em andamento...', 'person-cash-wallet' );
					}
				} else {
					esc_html_e( 'Cron não agendado. Verifique as configurações.', 'person-cash-wallet' );
				}
				?>
			</p>
		</div>
		<?php endif; ?>

		<!-- Filtro de Período -->
		<div class="pcw-card" style="margin-bottom: 20px;">
			<div class="pcw-card-body">
				<label style="margin-right: 10px;"><strong><?php esc_html_e( 'Período:', 'person-cash-wallet' ); ?></strong></label>
				<select id="pcw-analytics-period" onchange="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=pcw-workflows&action=analytics&id=' . $workflow_id . '&period=' ) ); ?>' + this.value">
					<option value="7" <?php selected( $period, '7' ); ?>><?php esc_html_e( 'Últimos 7 dias', 'person-cash-wallet' ); ?></option>
					<option value="15" <?php selected( $period, '15' ); ?>><?php esc_html_e( 'Últimos 15 dias', 'person-cash-wallet' ); ?></option>
					<option value="30" <?php selected( $period, '30' ); ?>><?php esc_html_e( 'Últimos 30 dias', 'person-cash-wallet' ); ?></option>
					<option value="60" <?php selected( $period, '60' ); ?>><?php esc_html_e( 'Últimos 60 dias', 'person-cash-wallet' ); ?></option>
					<option value="90" <?php selected( $period, '90' ); ?>><?php esc_html_e( 'Últimos 90 dias', 'person-cash-wallet' ); ?></option>
				</select>
			</div>
		</div>

		<!-- Stats Cards -->
		<div class="pcw-stats-row" style="margin-bottom: 30px;">
			<div class="pcw-stat-card">
				<div class="pcw-stat-icon pcw-stat-primary">
					<span class="dashicons dashicons-backup"></span>
				</div>
				<div class="pcw-stat-content">
					<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $total_executions ) ); ?></div>
					<div class="pcw-stat-label"><?php esc_html_e( 'Total de Execuções', 'person-cash-wallet' ); ?></div>
				</div>
			</div>

			<div class="pcw-stat-card">
				<div class="pcw-stat-icon pcw-stat-success">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<div class="pcw-stat-content">
					<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $successful ) ); ?></div>
					<div class="pcw-stat-label"><?php esc_html_e( 'Bem-sucedidas', 'person-cash-wallet' ); ?></div>
				</div>
			</div>

			<div class="pcw-stat-card">
				<div class="pcw-stat-icon pcw-stat-error">
					<span class="dashicons dashicons-dismiss"></span>
				</div>
				<div class="pcw-stat-content">
					<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $failed ) ); ?></div>
					<div class="pcw-stat-label"><?php esc_html_e( 'Com Erro', 'person-cash-wallet' ); ?></div>
				</div>
			</div>

			<div class="pcw-stat-card">
				<div class="pcw-stat-icon pcw-stat-info">
					<span class="dashicons dashicons-chart-line"></span>
				</div>
				<div class="pcw-stat-content">
					<div class="pcw-stat-value"><?php echo esc_html( $success_rate ); ?>%</div>
					<div class="pcw-stat-label"><?php esc_html_e( 'Taxa de Sucesso', 'person-cash-wallet' ); ?></div>
				</div>
			</div>

			<div class="pcw-stat-card">
				<div class="pcw-stat-icon pcw-stat-warning">
					<span class="dashicons dashicons-clock"></span>
				</div>
				<div class="pcw-stat-content">
					<div class="pcw-stat-value"><?php echo esc_html( $avg_execution_time ); ?>s</div>
					<div class="pcw-stat-label"><?php esc_html_e( 'Tempo Médio', 'person-cash-wallet' ); ?></div>
				</div>
			</div>
		</div>

		<!-- Projeção de Disparos -->
		<div class="pcw-card" style="margin-bottom: 30px;">
			<div class="pcw-card-header">
				<h2>
					<span class="dashicons dashicons-calendar-alt" style="color: #667eea;"></span>
					<?php esc_html_e( 'Projeção de Disparos Futuros', 'person-cash-wallet' ); ?>
				</h2>
				<p class="description" style="margin: 8px 0 0 0; font-size: 13px;">
					<?php 
					esc_html_e( 'Baseado no gatilho e condições configuradas', 'person-cash-wallet' );
					echo ' - <em>' . esc_html( $workflow->trigger_type ) . '</em>';
					?>
				</p>
			</div>
			<div class="pcw-card-body">
				<?php 
				// Mostrar alerta quando não há cashbacks com data de expiração configurada
				if ( 'cashback_expiring' === $workflow->trigger_type && empty( $projected_triggers ) ) {
					global $wpdb;
					$cashback_table = $wpdb->prefix . 'pcw_cashback';
					
					// Cashbacks disponíveis
					$available_cashbacks = $wpdb->get_var( "SELECT COUNT(*) FROM {$cashback_table} WHERE status = 'available'" );
					
					// Cashbacks com data de expiração
					$with_expiry = $wpdb->get_var( "SELECT COUNT(*) FROM {$cashback_table} WHERE status = 'available' AND expires_date IS NOT NULL" );
					
					// Valor total disponível
					$total_amount = $wpdb->get_var( "SELECT SUM(amount) FROM {$cashback_table} WHERE status = 'available'" );
					
					if ( $available_cashbacks > 0 && $with_expiry == 0 ) {
						echo '<div class="pcw-projection-item" style="border-left: 4px solid #f59e0b; background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);">';
						echo '<div class="pcw-projection-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">';
						echo '<span class="dashicons dashicons-warning"></span>';
						echo '</div>';
						echo '<div class="pcw-projection-content">';
						echo '<div class="pcw-projection-title">' . esc_html__( 'Cashbacks Sem Expiração', 'person-cash-wallet' ) . '</div>';
						echo '<div class="pcw-projection-subtitle">';
						printf( 
							/* translators: 1: count of cashbacks 2: total amount */
							esc_html__( 'Existem %1$d cashbacks disponíveis (R$ %2$s) sem data de expiração configurada', 'person-cash-wallet' ),
							$available_cashbacks,
							number_format( (float) $total_amount, 2, ',', '.' )
						);
						echo '</div>';
						echo '</div>';
						echo '<div class="pcw-projection-badge" style="border-color: #f59e0b;">';
						echo '<strong style="color: #f59e0b;">' . esc_html( $available_cashbacks ) . '</strong>';
						echo '<span>' . esc_html__( 'sem expiração', 'person-cash-wallet' ) . '</span>';
						echo '</div>';
						echo '</div>';
						
						// Verificar configuração atual
						$current_expiration_days = absint( get_option( 'pcw_cashback_expiration_days', 0 ) );
						
						// Mostrar como configurar
						echo '<div style="margin-top: 20px; padding: 16px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;">';
						echo '<strong>⚙️ ' . esc_html__( 'Configuração Atual:', 'person-cash-wallet' ) . '</strong>';
						echo '<p style="margin: 8px 0 0; font-size: 13px;">';
						echo esc_html__( 'Dias para expiração:', 'person-cash-wallet' ) . ' ';
						if ( $current_expiration_days > 0 ) {
							echo '<strong style="color: #059669;">' . $current_expiration_days . ' ' . esc_html__( 'dias', 'person-cash-wallet' ) . '</strong>';
						} else {
							echo '<strong style="color: #dc2626;">' . esc_html__( 'Não configurado (nunca expira)', 'person-cash-wallet' ) . '</strong>';
						}
						echo '</p>';
						
						echo '<div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">';
						echo '<strong>💡 ' . esc_html__( 'O que fazer:', 'person-cash-wallet' ) . '</strong>';
						echo '<ol style="margin: 12px 0 0 20px; font-size: 13px; color: #4b5563;">';
						echo '<li>';
						echo '<a href="' . esc_url( admin_url( 'admin.php?page=pcw-settings&tab=cashback' ) ) . '" style="color: #667eea; font-weight: 600;">';
						echo esc_html__( 'Configurar dias de expiração', 'person-cash-wallet' );
						echo '</a>';
						echo ' - ' . esc_html__( 'Para novos cashbacks terem data de expiração', 'person-cash-wallet' );
						echo '</li>';
						echo '<li>' . esc_html__( 'Depois de configurar, use a ferramenta abaixo para atualizar os existentes', 'person-cash-wallet' ) . '</li>';
						echo '</ol>';
						echo '</div>';
						
						// Ferramenta para definir expiração em massa
						echo '<div style="margin-top: 16px; padding: 16px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">';
						echo '<strong>🔧 ' . esc_html__( 'Definir Expiração em Massa', 'person-cash-wallet' ) . '</strong>';
						echo '<p style="margin: 8px 0 12px; font-size: 13px; color: #6b7280;">';
						echo esc_html__( 'Adicione uma data de expiração aos cashbacks "available" que não têm expiração:', 'person-cash-wallet' );
						echo '</p>';
						echo '<form method="post" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">';
						wp_nonce_field( 'pcw_set_mass_expiration', 'pcw_mass_exp_nonce' );
						echo '<input type="hidden" name="action" value="pcw_set_mass_expiration">';
						echo '<label style="font-size: 13px;">' . esc_html__( 'Expirar em:', 'person-cash-wallet' ) . '</label>';
						echo '<select name="expiration_days" style="padding: 6px 12px;">';
						echo '<option value="30">30 ' . esc_html__( 'dias', 'person-cash-wallet' ) . '</option>';
						echo '<option value="60">60 ' . esc_html__( 'dias', 'person-cash-wallet' ) . '</option>';
						echo '<option value="90" selected>90 ' . esc_html__( 'dias', 'person-cash-wallet' ) . '</option>';
						echo '<option value="120">120 ' . esc_html__( 'dias', 'person-cash-wallet' ) . '</option>';
						echo '<option value="180">180 ' . esc_html__( 'dias', 'person-cash-wallet' ) . '</option>';
						echo '<option value="365">365 ' . esc_html__( 'dias', 'person-cash-wallet' ) . '</option>';
						echo '</select>';
						echo '<button type="submit" class="button button-primary" onclick="return confirm(\'' . esc_js( __( 'Isso vai definir data de expiração para todos os cashbacks disponíveis sem expiração. Continuar?', 'person-cash-wallet' ) ) . '\');">';
						echo '<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> ';
						printf( 
							/* translators: %d: count of cashbacks */
							esc_html__( 'Atualizar %d cashbacks', 'person-cash-wallet' ),
							$available_cashbacks
						);
						echo '</button>';
						echo '</form>';
						echo '</div>';
						
						echo '</div>';
					}
				}
				?>
				
				<?php if ( ! empty( $projected_triggers ) ) : ?>
					<div class="pcw-projected-triggers">
						<?php foreach ( $projected_triggers as $projection ) : ?>
							<div class="pcw-projection-item">
								<div class="pcw-projection-icon">
									<span class="dashicons <?php echo esc_attr( $projection['icon'] ); ?>"></span>
								</div>
								<div class="pcw-projection-content">
									<div class="pcw-projection-title"><?php echo esc_html( $projection['title'] ); ?></div>
									<div class="pcw-projection-subtitle"><?php echo esc_html( $projection['subtitle'] ); ?></div>
								</div>
								<div class="pcw-projection-badge">
									<strong><?php echo esc_html( $projection['count'] ); ?></strong>
									<span><?php echo esc_html( $projection['label'] ); ?></span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					
					<?php if ( isset( $projected_triggers[0]['details'] ) && ! empty( $projected_triggers[0]['details'] ) ) : ?>
					<div class="pcw-projection-details">
						<strong><?php esc_html_e( 'Próximos eventos:', 'person-cash-wallet' ); ?></strong>
						<ul>
							<?php foreach ( array_slice( $projected_triggers[0]['details'], 0, 5 ) as $detail ) : ?>
								<li>
									<?php echo esc_html( $detail['text'] ); ?>
									<span class="pcw-projection-date"><?php echo esc_html( $detail['date'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
						<?php if ( count( $projected_triggers[0]['details'] ) > 5 ) : ?>
							<p style="margin: 10px 0 0; font-size: 12px; color: #6b7280;">
								<?php
								/* translators: %d: number of additional events */
								printf( esc_html__( '+ %d eventos adicionais', 'person-cash-wallet' ), count( $projected_triggers[0]['details'] ) - 5 );
								?>
							</p>
						<?php endif; ?>
					</div>
					<?php endif; ?>
					
					<?php 
					// Lista detalhada para todos os gatilhos
					$upcoming_items = $this->get_upcoming_trigger_details( $workflow );
					
					// Definir colunas baseado no tipo de gatilho
					$column_labels = $this->get_trigger_column_labels( $workflow->trigger_type );
					?>
					<div class="pcw-projection-customers" style="margin-top: 20px;">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
							<strong style="font-size: 14px;">
								<span class="dashicons dashicons-groups" style="margin-right: 5px;"></span>
								<?php echo esc_html( $column_labels['list_title'] ); ?>
								<span style="font-weight: normal; color: #6b7280;">(<?php echo count( $upcoming_items ); ?>)</span>
							</strong>
							<button type="button" class="button button-small" id="pcw-toggle-customer-list" style="display: flex; align-items: center; gap: 4px;">
								<span class="dashicons dashicons-arrow-down-alt2" style="font-size: 16px; width: 16px; height: 16px;"></span>
								<span class="pcw-toggle-text"><?php esc_html_e( 'Expandir Lista', 'person-cash-wallet' ); ?></span>
							</button>
						</div>
						
						<div id="pcw-customer-list-container" style="display: none;">
							<?php if ( ! empty( $upcoming_items ) ) : ?>
								<div style="max-height: 400px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px;">
									<table class="wp-list-table widefat fixed striped" style="margin: 0;">
										<thead style="position: sticky; top: 0; background: #f8fafc; z-index: 1;">
											<tr>
												<th style="width: 22%;"><?php esc_html_e( 'Cliente', 'person-cash-wallet' ); ?></th>
												<th style="width: 18%;"><?php esc_html_e( 'Telefone', 'person-cash-wallet' ); ?></th>
												<th style="width: 12%;"><?php esc_html_e( 'Pedido', 'person-cash-wallet' ); ?></th>
												<th style="width: 12%;"><?php echo esc_html( $column_labels['amount'] ); ?></th>
												<th style="width: 18%;"><?php echo esc_html( $column_labels['date'] ); ?></th>
												<th style="width: 10%;"><?php echo esc_html( $column_labels['extra'] ); ?></th>
												<?php if ( $column_labels['show_trigger'] ) : ?>
												<th style="width: 8%;"><?php esc_html_e( 'Disparo', 'person-cash-wallet' ); ?></th>
												<?php endif; ?>
											</tr>
										</thead>
										<tbody>
											<?php foreach ( $upcoming_items as $item ) : ?>
												<tr>
													<td>
														<strong><?php echo esc_html( $item['customer_name'] ); ?></strong>
														<br>
														<small style="color: #6b7280;"><?php echo esc_html( $item['customer_email'] ); ?></small>
													</td>
													<td>
														<?php if ( ! empty( $item['customer_phone'] ) && '-' !== $item['customer_phone'] ) : ?>
															<a href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $item['customer_phone'] ) ); ?>" target="_blank" style="text-decoration: none;">
																<?php echo esc_html( $item['customer_phone'] ); ?>
																<span class="dashicons dashicons-whatsapp" style="font-size: 14px; width: 14px; height: 14px; color: #25D366;"></span>
															</a>
														<?php else : ?>
															<span style="color: #9ca3af;">-</span>
														<?php endif; ?>
													</td>
													<td>
														<?php if ( $item['order_id'] > 0 ) : ?>
															<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $item['order_id'] . '&action=edit' ) ); ?>" target="_blank">
																#<?php echo esc_html( $item['order_id'] ); ?>
															</a>
														<?php else : ?>
															<span style="color: #9ca3af;">-</span>
														<?php endif; ?>
													</td>
													<td>
														<?php if ( $item['amount'] > 0 ) : ?>
															<strong style="color: #059669;">R$ <?php echo esc_html( number_format( $item['amount'], 2, ',', '.' ) ); ?></strong>
														<?php else : ?>
															<span style="color: #9ca3af;">-</span>
														<?php endif; ?>
													</td>
													<td>
														<?php echo esc_html( $item['date_info'] ); ?>
														<?php if ( $item['days_info'] > 0 ) : ?>
															<br>
															<small style="color: #6b7280;">
																<?php 
																/* translators: %d: days */
																printf( esc_html__( '(%d dias)', 'person-cash-wallet' ), $item['days_info'] ); 
																?>
															</small>
														<?php endif; ?>
													</td>
													<td>
														<?php if ( ! empty( $item['extra_info'] ) ) : ?>
															<span class="pcw-badge pcw-badge-info" style="font-size: 11px;"><?php echo esc_html( $item['extra_info'] ); ?></span>
														<?php else : ?>
															<span style="color: #9ca3af;">-</span>
														<?php endif; ?>
													</td>
													<?php if ( $column_labels['show_trigger'] ) : ?>
													<td>
														<?php if ( $item['trigger_days'] < 0 ) : ?>
															<?php // Pedidos agendados - já passaram do threshold ?>
															<span class="pcw-badge pcw-badge-success" title="<?php esc_attr_e( 'Pronto para disparar', 'person-cash-wallet' ); ?>">
																<?php esc_html_e( 'Pronto', 'person-cash-wallet' ); ?>
															</span>
															<?php if ( isset( $item['days_over'] ) && $item['days_over'] > 0 ) : ?>
																<br><small style="color: #dc2626;">+<?php echo esc_html( $item['days_over'] ); ?>d</small>
															<?php endif; ?>
														<?php elseif ( $item['trigger_days'] <= 0 ) : ?>
															<span class="pcw-badge pcw-badge-warning"><?php esc_html_e( 'Hoje', 'person-cash-wallet' ); ?></span>
														<?php elseif ( $item['trigger_days'] <= 7 ) : ?>
															<span class="pcw-badge pcw-badge-info">
																<?php 
																/* translators: %d: days until trigger */
																printf( esc_html__( '%d d', 'person-cash-wallet' ), $item['trigger_days'] ); 
																?>
															</span>
														<?php else : ?>
															<span style="color: #6b7280;">
																<?php 
																/* translators: %d: days until trigger */
																printf( esc_html__( '%d d', 'person-cash-wallet' ), $item['trigger_days'] ); 
																?>
															</span>
														<?php endif; ?>
													</td>
													<?php endif; ?>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
								<p style="margin: 10px 0 0; font-size: 12px; color: #6b7280; text-align: right;">
									<?php 
									/* translators: %d: total items count */
									printf( esc_html__( 'Mostrando %d registros', 'person-cash-wallet' ), count( $upcoming_items ) ); 
									?>
								</p>
							<?php else : ?>
								<p style="padding: 20px; text-align: center; color: #6b7280; background: #f9fafb; border-radius: 8px;">
									<?php esc_html_e( 'Nenhum registro encontrado para este gatilho.', 'person-cash-wallet' ); ?>
								</p>
							<?php endif; ?>
						</div>
					</div>
					
					<script>
					jQuery(document).ready(function($) {
						$('#pcw-toggle-customer-list').on('click', function() {
							var $container = $('#pcw-customer-list-container');
							var $button = $(this);
							var $icon = $button.find('.dashicons');
							var $text = $button.find('.pcw-toggle-text');
							
							$container.slideToggle(300, function() {
								if ($container.is(':visible')) {
									$icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
									$text.text('<?php echo esc_js( __( 'Recolher Lista', 'person-cash-wallet' ) ); ?>');
								} else {
									$icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
									$text.text('<?php echo esc_js( __( 'Expandir Lista', 'person-cash-wallet' ) ); ?>');
								}
							});
						});
					});
					</script>
					
				<?php else : ?>
					<div style="padding: 20px; text-align: center; color: #6b7280;">
						<span class="dashicons dashicons-info" style="font-size: 48px; width: 48px; height: 48px; opacity: 0.3;"></span>
						<p style="margin: 12px 0 0;">
							<?php esc_html_e( 'Nenhuma projeção disponível para este tipo de gatilho no momento.', 'person-cash-wallet' ); ?>
						</p>
						<p style="margin: 8px 0 0; font-size: 13px;">
							<?php 
							/* translators: %s: trigger type name */
							printf( esc_html__( 'Tipo de gatilho: %s', 'person-cash-wallet' ), '<strong>' . esc_html( $workflow->trigger_type ) . '</strong>' ); 
							?>
						</p>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Gráfico de Execuções -->
		<div class="pcw-card" style="margin-bottom: 30px;">
			<div class="pcw-card-header">
				<h2><?php esc_html_e( 'Execuções ao Longo do Tempo', 'person-cash-wallet' ); ?></h2>
			</div>
			<div class="pcw-card-body">
				<canvas id="pcw-workflow-chart" height="80"></canvas>
			</div>
		</div>

		<!-- Últimas Execuções -->
		<div class="pcw-card">
			<div class="pcw-card-header">
				<h2><?php esc_html_e( 'Últimas Execuções', 'person-cash-wallet' ); ?></h2>
			</div>
			<div class="pcw-card-body">
				<?php if ( empty( $recent_logs ) ) : ?>
					<p><?php esc_html_e( 'Nenhuma execução registrada ainda.', 'person-cash-wallet' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th width="20%"><?php esc_html_e( 'Data/Hora', 'person-cash-wallet' ); ?></th>
								<th width="10%"><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></th>
								<th width="10%"><?php esc_html_e( 'Tempo', 'person-cash-wallet' ); ?></th>
								<th width="20%"><?php esc_html_e( 'Gatilho', 'person-cash-wallet' ); ?></th>
								<th width="20%"><?php esc_html_e( 'Contexto', 'person-cash-wallet' ); ?></th>
								<th width="20%"><?php esc_html_e( 'Resultado', 'person-cash-wallet' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent_logs as $log ) : ?>
								<tr>
									<td><?php echo esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( $log->executed_at ) ) ); ?></td>
									<td>
										<?php if ( 'success' === $log->status ) : ?>
											<span class="pcw-badge pcw-badge-success"><?php esc_html_e( 'Sucesso', 'person-cash-wallet' ); ?></span>
										<?php else : ?>
											<span class="pcw-badge pcw-badge-error"><?php esc_html_e( 'Erro', 'person-cash-wallet' ); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( round( $log->execution_time, 2 ) ); ?>s</td>
									<td>
										<small><?php echo esc_html( $log->trigger_type ?: 'N/A' ); ?></small>
									</td>
									<td>
										<?php 
										$context = json_decode( $log->context, true );
										if ( $context && is_array( $context ) ) {
											$display = array();
											if ( isset( $context['customer_name'] ) ) {
												$display[] = $context['customer_name'];
											}
											if ( isset( $context['order_id'] ) ) {
												$display[] = 'Pedido #' . $context['order_id'];
											}
											echo esc_html( implode( ' - ', $display ) );
										}
										?>
									</td>
									<td>
										<?php if ( $log->error_message ) : ?>
											<small style="color: #d63638;"><?php echo esc_html( $log->error_message ); ?></small>
										<?php else : ?>
											<?php 
											$result = json_decode( $log->result, true );
											if ( $result && is_array( $result ) ) {
												$actions_count = count( $result );
												$actions_success = 0;
												foreach ( $result as $action_result ) {
													if ( isset( $action_result['success'] ) && $action_result['success'] ) {
														$actions_success++;
													}
												}
												/* translators: 1: number of successful actions 2: total number of actions */
												printf( esc_html__( '%1$d de %2$d ações OK', 'person-cash-wallet' ), $actions_success, $actions_count );
											}
											?>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
		<script>
		jQuery(document).ready(function($) {
			const ctx = document.getElementById('pcw-workflow-chart');
			if (ctx) {
				new Chart(ctx, {
					type: 'line',
					data: {
						labels: <?php echo wp_json_encode( $chart_labels ); ?>,
						datasets: [
							{
								label: '<?php esc_html_e( 'Sucesso', 'person-cash-wallet' ); ?>',
								data: <?php echo wp_json_encode( $chart_success ); ?>,
								borderColor: '#46b450',
								backgroundColor: 'rgba(70, 180, 80, 0.1)',
								tension: 0.3,
								fill: true
							},
							{
								label: '<?php esc_html_e( 'Erro', 'person-cash-wallet' ); ?>',
								data: <?php echo wp_json_encode( $chart_error ); ?>,
								borderColor: '#d63638',
								backgroundColor: 'rgba(214, 54, 56, 0.1)',
								tension: 0.3,
								fill: true
							}
						]
					},
					options: {
						responsive: true,
						maintainAspectRatio: true,
						plugins: {
							legend: {
								position: 'top',
							}
						},
						scales: {
							y: {
								beginAtZero: true,
								ticks: {
									stepSize: 1
								}
							}
						}
					}
				});
			}
			// Botão Executar Agora
			$('#pcw-run-scheduled-now').on('click', function() {
				var $btn = $(this);
				var originalText = $btn.html();
				
				if (!confirm('<?php echo esc_js( __( 'Isso vai executar o workflow agora para todos os pedidos que se enquadram nas condições. Continuar?', 'person-cash-wallet' ) ); ?>')) {
					return;
				}
				
				$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php echo esc_js( __( 'Executando...', 'person-cash-wallet' ) ); ?>');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'pcw_run_scheduled_workflow',
						nonce: '<?php echo esc_js( wp_create_nonce( 'pcw_workflows' ) ); ?>',
						workflow_id: <?php echo (int) $workflow_id; ?>
					},
					success: function(response) {
						$btn.prop('disabled', false).html(originalText);
						
						if (response.success) {
							alert('✅ ' + response.data.message);
							location.reload();
						} else {
							alert('❌ ' + (response.data.message || '<?php echo esc_js( __( 'Erro desconhecido', 'person-cash-wallet' ) ); ?>'));
						}
					},
					error: function() {
						$btn.prop('disabled', false).html(originalText);
						alert('❌ <?php echo esc_js( __( 'Erro de conexão', 'person-cash-wallet' ) ); ?>');
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
			100% { transform: rotate(360deg); }
		}
		</style>
		<?php
	}

	/**
	 * Obter labels das colunas baseado no tipo de gatilho
	 *
	 * @param string $trigger_type Tipo do gatilho.
	 * @return array
	 */
	private function get_trigger_column_labels( $trigger_type ) {
		$labels = array(
			'list_title'   => __( 'Lista de Registros:', 'person-cash-wallet' ),
			'amount'       => __( 'Valor', 'person-cash-wallet' ),
			'date'         => __( 'Data', 'person-cash-wallet' ),
			'extra'        => __( 'Info', 'person-cash-wallet' ),
			'show_trigger' => false,
		);

		switch ( $trigger_type ) {
			case 'cashback_expiring':
				$labels['list_title']   = __( 'Clientes com Cashback Expirando:', 'person-cash-wallet' );
				$labels['amount']       = __( 'Cashback', 'person-cash-wallet' );
				$labels['date']         = __( 'Expira em', 'person-cash-wallet' );
				$labels['extra']        = __( 'Info', 'person-cash-wallet' );
				$labels['show_trigger'] = true;
				break;

			case 'scheduled_order_check':
				$labels['list_title']   = __( 'Pedidos Aguardando:', 'person-cash-wallet' );
				$labels['amount']       = __( 'Total', 'person-cash-wallet' );
				$labels['date']         = __( 'Criado em', 'person-cash-wallet' );
				$labels['extra']        = __( 'Status', 'person-cash-wallet' );
				$labels['show_trigger'] = true; // Mostrar coluna de disparo
				break;

			case 'level_expiring':
				$labels['list_title']   = __( 'Clientes com Nível Expirando:', 'person-cash-wallet' );
				$labels['amount']       = __( 'Valor', 'person-cash-wallet' );
				$labels['date']         = __( 'Expira em', 'person-cash-wallet' );
				$labels['extra']        = __( 'Nível', 'person-cash-wallet' );
				$labels['show_trigger'] = true;
				break;

			case 'order_status_changed':
			case 'order_created':
				$labels['list_title']   = __( 'Pedidos Recentes:', 'person-cash-wallet' );
				$labels['amount']       = __( 'Total', 'person-cash-wallet' );
				$labels['date']         = __( 'Data', 'person-cash-wallet' );
				$labels['extra']        = __( 'Status', 'person-cash-wallet' );
				break;

			case 'cashback_earned':
			case 'cashback_used':
				$labels['list_title']   = __( 'Cashbacks Recentes:', 'person-cash-wallet' );
				$labels['amount']       = __( 'Valor', 'person-cash-wallet' );
				$labels['date']         = __( 'Data', 'person-cash-wallet' );
				$labels['extra']        = __( 'Status', 'person-cash-wallet' );
				break;

			case 'level_achieved':
				$labels['list_title']   = __( 'Níveis Recentes:', 'person-cash-wallet' );
				$labels['amount']       = __( 'Valor', 'person-cash-wallet' );
				$labels['date']         = __( 'Alcançado em', 'person-cash-wallet' );
				$labels['extra']        = __( 'Nível', 'person-cash-wallet' );
				break;

			case 'wallet_credit':
			case 'wallet_debit':
				$labels['list_title']   = __( 'Transações Recentes:', 'person-cash-wallet' );
				$labels['amount']       = __( 'Valor', 'person-cash-wallet' );
				$labels['date']         = __( 'Data', 'person-cash-wallet' );
				$labels['extra']        = __( 'Fonte', 'person-cash-wallet' );
				break;

			case 'customer_registered':
				$labels['list_title']   = __( 'Clientes Recentes:', 'person-cash-wallet' );
				$labels['amount']       = __( 'Valor', 'person-cash-wallet' );
				$labels['date']         = __( 'Cadastro', 'person-cash-wallet' );
				$labels['extra']        = __( 'Info', 'person-cash-wallet' );
				break;
		}

		return $labels;
	}

	/**
	 * Obter detalhes dos itens que vão disparar alertas (genérico para todos os gatilhos)
	 *
	 * @param object $workflow Workflow.
	 * @return array
	 */
	private function get_upcoming_trigger_details( $workflow ) {
		global $wpdb;

		$result = array();
		$config = array();
		
		if ( ! empty( $workflow->trigger_config ) ) {
			$config = is_string( $workflow->trigger_config ) ? json_decode( $workflow->trigger_config, true ) : $workflow->trigger_config;
		}

		switch ( $workflow->trigger_type ) {
			case 'cashback_expiring':
				$result = $this->get_cashback_expiring_details( $config );
				break;

			case 'scheduled_order_check':
				$result = $this->get_scheduled_order_details( $config, $workflow->id );
				break;

			case 'level_expiring':
				$result = $this->get_level_expiring_details( $config );
				break;

			case 'order_status_changed':
			case 'order_created':
				$result = $this->get_recent_orders_details( $config );
				break;

			case 'cashback_earned':
			case 'cashback_used':
				$result = $this->get_recent_cashback_details( $config );
				break;

			case 'level_achieved':
				$result = $this->get_recent_level_details( $config );
				break;

			case 'wallet_credit':
			case 'wallet_debit':
				$result = $this->get_recent_wallet_details( $config, $workflow->trigger_type );
				break;

			case 'customer_registered':
				$result = $this->get_recent_customers_details( $config );
				break;
		}

		return $result;
	}

	/**
	 * Obter detalhes de cashbacks expirando
	 */
	private function get_cashback_expiring_details( $config ) {
		global $wpdb;

		// Suportar ambas as chaves para compatibilidade
		$days_config = isset( $config['days_before_expiration'] ) ? absint( $config['days_before_expiration'] ) : ( isset( $config['days_before'] ) ? absint( $config['days_before'] ) : 7 );
		$cashback_table = $wpdb->prefix . 'pcw_cashback';
		$today = date( 'Y-m-d' );

		$cashbacks = $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				c.id,
				c.user_id,
				c.order_id,
				c.amount,
				c.expires_date,
				DATEDIFF(DATE(c.expires_date), %s) as days_until_expiry
			FROM {$cashback_table} c
			WHERE c.status = 'available'
			AND c.expires_date IS NOT NULL
			AND DATE(c.expires_date) >= %s
			ORDER BY c.expires_date ASC
			LIMIT 100",
			$today,
			$today
		) );

		$result = array();
		foreach ( $cashbacks as $cashback ) {
			$days_until_expiry = (int) $cashback->days_until_expiry;
			$days_until_trigger = $days_until_expiry - $days_config;

			if ( $days_until_trigger < -1 ) {
				continue;
			}

			$user = get_user_by( 'id', $cashback->user_id );
			if ( ! $user ) {
				continue;
			}

			$result[] = array(
				'type'            => 'cashback_expiring',
				'id'              => $cashback->id,
				'user_id'         => $cashback->user_id,
				'customer_name'   => $user->display_name ?: $user->user_login,
				'customer_email'  => $user->user_email,
				'customer_phone'  => $this->get_user_phone( $cashback->user_id ) ?: '-',
				'order_id'        => $cashback->order_id,
				'amount'          => floatval( $cashback->amount ),
				'date_info'       => date_i18n( get_option( 'date_format' ), strtotime( $cashback->expires_date ) ),
				'days_info'       => $days_until_expiry,
				'trigger_days'    => max( 0, $days_until_trigger ),
				'extra_info'      => '',
			);
		}

		usort( $result, function( $a, $b ) {
			return $a['trigger_days'] - $b['trigger_days'];
		} );

		return $result;
	}

	/**
	 * Obter detalhes de pedidos agendados (scheduled_order_check)
	 */
	private function get_scheduled_order_details( $config, $workflow_id = 0 ) {
		// Suportar ambas as chaves para compatibilidade
		$target_status = isset( $config['target_status'] ) ? $config['target_status'] : ( isset( $config['order_status'] ) ? $config['order_status'] : 'pending' );
		$days_config = isset( $config['min_days_in_status'] ) ? absint( $config['min_days_in_status'] ) : ( isset( $config['days_old'] ) ? absint( $config['days_old'] ) : 1 );
		$run_once = isset( $config['run_once_per_order'] ) ? (bool) $config['run_once_per_order'] : true;

		$date_threshold = date( 'Y-m-d H:i:s', strtotime( "-{$days_config} days" ) );

		$orders = wc_get_orders( array(
			'status'       => $target_status,
			'date_created' => '<' . strtotime( $date_threshold ),
			'limit'        => 100,
			'orderby'      => 'date',
			'order'        => 'ASC',
		) );

		$result = array();
		foreach ( $orders as $order ) {
			// Verificar se já foi executado para este pedido (se run_once está ativo)
			if ( $run_once && $workflow_id > 0 ) {
				$executed_key = '_pcw_workflow_' . $workflow_id . '_executed';
				$already_executed = $order->get_meta( $executed_key );
				if ( $already_executed ) {
					continue; // Pular pedidos já processados
				}
			}

			$user_id = $order->get_user_id();
			$customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
			$customer_email = $order->get_billing_email();
			$customer_phone = $order->get_billing_phone();

			if ( ! $customer_phone && $user_id ) {
				$customer_phone = $this->get_user_phone( $user_id );
			}

			$days_old = floor( ( time() - $order->get_date_created()->getTimestamp() ) / DAY_IN_SECONDS );
			$days_over_threshold = $days_old - $days_config;

			$result[] = array(
				'type'            => 'scheduled_order_check',
				'id'              => $order->get_id(),
				'user_id'         => $user_id,
				'customer_name'   => trim( $customer_name ) ?: __( 'Visitante', 'person-cash-wallet' ),
				'customer_email'  => $customer_email ?: '-',
				'customer_phone'  => $customer_phone ?: '-',
				'order_id'        => $order->get_id(),
				'amount'          => floatval( $order->get_total() ),
				'date_info'       => $order->get_date_created()->date_i18n( get_option( 'date_format' ) ),
				'days_info'       => $days_old,
				'trigger_days'    => -1, // -1 indica que já passou do threshold (pronto para disparar)
				'days_over'       => $days_over_threshold, // Dias além do mínimo
				'extra_info'      => wc_get_order_status_name( $order->get_status() ),
			);
		}

		return $result;
	}

	/**
	 * Obter detalhes de níveis expirando
	 */
	private function get_level_expiring_details( $config ) {
		global $wpdb;

		// Suportar ambas as chaves para compatibilidade
		$days_config = isset( $config['days_before_expiration'] ) ? absint( $config['days_before_expiration'] ) : ( isset( $config['days_before'] ) ? absint( $config['days_before'] ) : 7 );
		$user_levels_table = $wpdb->prefix . 'pcw_user_levels';
		$levels_table = $wpdb->prefix . 'pcw_levels';
		$today = date( 'Y-m-d' );

		$levels = $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				ul.id,
				ul.user_id,
				ul.level_id,
				ul.expires_date,
				l.name as level_name,
				DATEDIFF(DATE(ul.expires_date), %s) as days_until_expiry
			FROM {$user_levels_table} ul
			INNER JOIN {$levels_table} l ON l.id = ul.level_id
			WHERE ul.status = 'active'
			AND ul.expires_date IS NOT NULL
			AND DATE(ul.expires_date) >= %s
			ORDER BY ul.expires_date ASC
			LIMIT 100",
			$today,
			$today
		) );

		$result = array();
		foreach ( $levels as $level ) {
			$days_until_expiry = (int) $level->days_until_expiry;
			$days_until_trigger = $days_until_expiry - $days_config;

			if ( $days_until_trigger < -1 ) {
				continue;
			}

			$user = get_user_by( 'id', $level->user_id );
			if ( ! $user ) {
				continue;
			}

			$result[] = array(
				'type'            => 'level_expiring',
				'id'              => $level->id,
				'user_id'         => $level->user_id,
				'customer_name'   => $user->display_name ?: $user->user_login,
				'customer_email'  => $user->user_email,
				'customer_phone'  => $this->get_user_phone( $level->user_id ) ?: '-',
				'order_id'        => 0,
				'amount'          => 0,
				'date_info'       => date_i18n( get_option( 'date_format' ), strtotime( $level->expires_date ) ),
				'days_info'       => $days_until_expiry,
				'trigger_days'    => max( 0, $days_until_trigger ),
				'extra_info'      => $level->level_name,
			);
		}

		usort( $result, function( $a, $b ) {
			return $a['trigger_days'] - $b['trigger_days'];
		} );

		return $result;
	}

	/**
	 * Obter detalhes de pedidos recentes
	 */
	private function get_recent_orders_details( $config ) {
		$orders = wc_get_orders( array(
			'limit'   => 50,
			'orderby' => 'date',
			'order'   => 'DESC',
		) );

		$result = array();
		foreach ( $orders as $order ) {
			$user_id = $order->get_user_id();
			$customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
			$customer_email = $order->get_billing_email();
			$customer_phone = $order->get_billing_phone();

			if ( ! $customer_phone && $user_id ) {
				$customer_phone = $this->get_user_phone( $user_id );
			}

			$result[] = array(
				'type'            => 'order',
				'id'              => $order->get_id(),
				'user_id'         => $user_id,
				'customer_name'   => trim( $customer_name ) ?: __( 'Visitante', 'person-cash-wallet' ),
				'customer_email'  => $customer_email ?: '-',
				'customer_phone'  => $customer_phone ?: '-',
				'order_id'        => $order->get_id(),
				'amount'          => floatval( $order->get_total() ),
				'date_info'       => $order->get_date_created()->date_i18n( get_option( 'date_format' ) . ' H:i' ),
				'days_info'       => 0,
				'trigger_days'    => 0,
				'extra_info'      => wc_get_order_status_name( $order->get_status() ),
			);
		}

		return $result;
	}

	/**
	 * Obter detalhes de cashbacks recentes
	 */
	private function get_recent_cashback_details( $config ) {
		global $wpdb;

		$cashback_table = $wpdb->prefix . 'pcw_cashback';

		$cashbacks = $wpdb->get_results(
			"SELECT * FROM {$cashback_table} ORDER BY created_at DESC LIMIT 50"
		);

		$result = array();
		foreach ( $cashbacks as $cashback ) {
			$user = get_user_by( 'id', $cashback->user_id );
			if ( ! $user ) {
				continue;
			}

			$result[] = array(
				'type'            => 'cashback',
				'id'              => $cashback->id,
				'user_id'         => $cashback->user_id,
				'customer_name'   => $user->display_name ?: $user->user_login,
				'customer_email'  => $user->user_email,
				'customer_phone'  => $this->get_user_phone( $cashback->user_id ) ?: '-',
				'order_id'        => $cashback->order_id,
				'amount'          => floatval( $cashback->amount ),
				'date_info'       => date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $cashback->created_at ) ),
				'days_info'       => 0,
				'trigger_days'    => 0,
				'extra_info'      => ucfirst( $cashback->status ),
			);
		}

		return $result;
	}

	/**
	 * Obter detalhes de níveis recentes
	 */
	private function get_recent_level_details( $config ) {
		global $wpdb;

		$user_levels_table = $wpdb->prefix . 'pcw_user_levels';
		$levels_table = $wpdb->prefix . 'pcw_levels';

		$levels = $wpdb->get_results(
			"SELECT ul.*, l.name as level_name 
			FROM {$user_levels_table} ul
			INNER JOIN {$levels_table} l ON l.id = ul.level_id
			ORDER BY ul.achieved_date DESC 
			LIMIT 50"
		);

		$result = array();
		foreach ( $levels as $level ) {
			$user = get_user_by( 'id', $level->user_id );
			if ( ! $user ) {
				continue;
			}

			$result[] = array(
				'type'            => 'level',
				'id'              => $level->id,
				'user_id'         => $level->user_id,
				'customer_name'   => $user->display_name ?: $user->user_login,
				'customer_email'  => $user->user_email,
				'customer_phone'  => $this->get_user_phone( $level->user_id ) ?: '-',
				'order_id'        => 0,
				'amount'          => 0,
				'date_info'       => date_i18n( get_option( 'date_format' ), strtotime( $level->achieved_date ) ),
				'days_info'       => 0,
				'trigger_days'    => 0,
				'extra_info'      => $level->level_name,
			);
		}

		return $result;
	}

	/**
	 * Obter detalhes de transações wallet recentes
	 */
	private function get_recent_wallet_details( $config, $type ) {
		global $wpdb;

		$wallet_table = $wpdb->prefix . 'pcw_wallet_transactions';
		$transaction_type = 'wallet_credit' === $type ? 'credit' : 'debit';

		$transactions = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wallet_table} WHERE type = %s ORDER BY created_at DESC LIMIT 50",
			$transaction_type
		) );

		$result = array();
		foreach ( $transactions as $tx ) {
			$user = get_user_by( 'id', $tx->user_id );
			if ( ! $user ) {
				continue;
			}

			$result[] = array(
				'type'            => 'wallet',
				'id'              => $tx->id,
				'user_id'         => $tx->user_id,
				'customer_name'   => $user->display_name ?: $user->user_login,
				'customer_email'  => $user->user_email,
				'customer_phone'  => $this->get_user_phone( $tx->user_id ) ?: '-',
				'order_id'        => isset( $tx->order_id ) ? $tx->order_id : 0,
				'amount'          => floatval( $tx->amount ),
				'date_info'       => date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $tx->created_at ) ),
				'days_info'       => 0,
				'trigger_days'    => 0,
				'extra_info'      => isset( $tx->source ) ? ucfirst( $tx->source ) : '-',
			);
		}

		return $result;
	}

	/**
	 * Obter detalhes de clientes recentes
	 */
	private function get_recent_customers_details( $config ) {
		$users = get_users( array(
			'number'  => 50,
			'orderby' => 'registered',
			'order'   => 'DESC',
			'role'    => 'customer',
		) );

		$result = array();
		foreach ( $users as $user ) {
			$result[] = array(
				'type'            => 'customer',
				'id'              => $user->ID,
				'user_id'         => $user->ID,
				'customer_name'   => $user->display_name ?: $user->user_login,
				'customer_email'  => $user->user_email,
				'customer_phone'  => $this->get_user_phone( $user->ID ) ?: '-',
				'order_id'        => 0,
				'amount'          => 0,
				'date_info'       => date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $user->user_registered ) ),
				'days_info'       => 0,
				'trigger_days'    => 0,
				'extra_info'      => '',
			);
		}

		return $result;
	}

	/**
	 * Calcular projeção de disparos futuros baseado no gatilho do workflow
	 *
	 * @param object $workflow Workflow.
	 * @return array
	 */
	private function calculate_projected_triggers( $workflow ) {
		global $wpdb;
		$projections = array();
		$logs_table = $wpdb->prefix . 'pcw_workflow_logs';

		switch ( $workflow->trigger_type ) {
			case 'cashback_expiring':
				// Buscar cashbacks que vão expirar nos próximos dias
				$days_config = 7; // Default
				if ( ! empty( $workflow->trigger_config ) ) {
					$config = is_string( $workflow->trigger_config ) ? json_decode( $workflow->trigger_config, true ) : $workflow->trigger_config;
					// Suportar ambas as chaves para compatibilidade
					if ( isset( $config['days_before_expiration'] ) ) {
						$days_config = absint( $config['days_before_expiration'] );
					} elseif ( isset( $config['days_before'] ) ) {
						$days_config = absint( $config['days_before'] );
					}
				}

				$cashback_table = $wpdb->prefix . 'pcw_cashback';
				$today = date( 'Y-m-d' );
				
				// Buscar TODOS os cashbacks disponíveis que ainda não expiraram
				// NOTA: O campo correto é expires_date (não expires_at)
				$cashbacks = $wpdb->get_results( $wpdb->prepare(
					"SELECT 
						DATE(expires_date) as expiry_date,
						COUNT(*) as count,
						SUM(amount) as total_amount,
						DATEDIFF(DATE(expires_date), %s) as days_until_expiry
					FROM {$cashback_table}
					WHERE status = 'available'
					AND expires_date IS NOT NULL
					AND DATE(expires_date) >= %s
					GROUP BY DATE(expires_date)
					ORDER BY expires_date ASC
					LIMIT 50",
					$today,
					$today
				) );

				if ( ! empty( $cashbacks ) ) {
					$total_count = 0;
					$total_count_all = 0;
					$total_amount_all = 0;
					$details = array();
					$upcoming_by_trigger = array();
					$first_trigger_days = null;
					
					// Calcular quando cada cashback vai disparar o alerta
					foreach ( $cashbacks as $item ) {
						$days_until_expiry = (int) $item->days_until_expiry;
						$days_until_trigger = $days_until_expiry - $days_config;
						
						// Contar todos os cashbacks (para mostrar resumo geral)
						if ( $days_until_trigger >= 0 ) {
							$total_count_all += $item->count;
							$total_amount_all += $item->total_amount;
							
							// Guardar primeiro disparo
							if ( null === $first_trigger_days || $days_until_trigger < $first_trigger_days ) {
								$first_trigger_days = $days_until_trigger;
							}
						}
						
						// Se o disparo é nos próximos 120 dias (aumentado de 30)
						if ( $days_until_trigger >= 0 && $days_until_trigger <= 120 ) {
							$total_count += $item->count;
							
							$trigger_key = $days_until_trigger;
							if ( ! isset( $upcoming_by_trigger[ $trigger_key ] ) ) {
								$upcoming_by_trigger[ $trigger_key ] = array(
									'count' => 0,
									'amount' => 0,
									'days' => $days_until_trigger,
								);
							}
							
							$upcoming_by_trigger[ $trigger_key ]['count'] += $item->count;
							$upcoming_by_trigger[ $trigger_key ]['amount'] += $item->total_amount;
						}
					}
					
					// Ordenar por data de disparo
					ksort( $upcoming_by_trigger );
					
					// Criar lista de detalhes
					foreach ( array_slice( $upcoming_by_trigger, 0, 10, true ) as $item ) {
						$days = (int) $item['days'];
						
						if ( $days === 0 ) {
							$date_text = __( 'hoje', 'person-cash-wallet' );
						} elseif ( $days === 1 ) {
							$date_text = __( 'amanhã', 'person-cash-wallet' );
						} else {
							$date_text = sprintf(
								/* translators: %d: days until trigger */
								_n( 'em %d dia', 'em %d dias', $days, 'person-cash-wallet' ),
								$days
							);
						}
						
						$details[] = array(
							'text' => sprintf(
								/* translators: 1: count of cashbacks 2: total amount */
								__( '%1$d alerta(s) - Total: R$ %2$s', 'person-cash-wallet' ),
								$item['count'],
								number_format( (float) $item['amount'], 2, ',', '.' )
							),
							'date' => $date_text,
						);
					}

					// Se há cashbacks programados
					if ( $total_count_all > 0 ) {
						// Se o primeiro disparo está muito longe
						if ( $first_trigger_days > 30 && empty( $details ) ) {
							$details[] = array(
								'text' => sprintf(
									/* translators: 1: count of cashbacks 2: total amount */
									__( '%1$d cashback(s) programados - Total: R$ %2$s', 'person-cash-wallet' ),
									$total_count_all,
									number_format( (float) $total_amount_all, 2, ',', '.' )
								),
								'date' => sprintf(
									/* translators: %d: days until first trigger */
									__( 'primeiro disparo em %d dias', 'person-cash-wallet' ),
									$first_trigger_days
								),
							);
						}
						
						$projections[] = array(
							'icon'     => 'dashicons-clock',
							'title'    => __( 'Cashback Expirando', 'person-cash-wallet' ),
							'subtitle' => sprintf(
								/* translators: %d: days before expiration */
								__( 'Alertas %d dias antes da expiração', 'person-cash-wallet' ),
								$days_config
							),
							'count'    => $total_count_all,
							'label'    => _n( 'disparo programado', 'disparos programados', $total_count_all, 'person-cash-wallet' ),
							'details'  => $details,
						);
					}
				}
				break;

			case 'level_expiring':
				// Buscar níveis que vão expirar
				$days_config = 7;
				if ( ! empty( $workflow->trigger_config ) ) {
					$config = is_string( $workflow->trigger_config ) ? json_decode( $workflow->trigger_config, true ) : $workflow->trigger_config;
					// Suportar ambas as chaves para compatibilidade
					if ( isset( $config['days_before_expiration'] ) ) {
						$days_config = absint( $config['days_before_expiration'] );
					} elseif ( isset( $config['days_before'] ) ) {
						$days_config = absint( $config['days_before'] );
					}
				}

				$user_levels_table = $wpdb->prefix . 'pcw_user_levels';
				$levels_table = $wpdb->prefix . 'pcw_levels';
				$today = date( 'Y-m-d' );

				// Buscar TODOS os níveis ativos que vão expirar
				// NOTA: O campo correto é expires_date (não expires_at)
				$levels = $wpdb->get_results( $wpdb->prepare(
					"SELECT 
						DATE(ul.expires_date) as expiry_date,
						COUNT(*) as count,
						l.name as level_name,
						DATEDIFF(DATE(ul.expires_date), %s) as days_until_expiry
					FROM {$user_levels_table} ul
					INNER JOIN {$levels_table} l ON l.id = ul.level_id
					WHERE ul.status = 'active'
					AND ul.expires_date IS NOT NULL
					AND DATE(ul.expires_date) >= %s
					GROUP BY DATE(ul.expires_date), l.name
					ORDER BY ul.expires_date ASC
					LIMIT 50",
					$today,
					$today
				) );

				if ( ! empty( $levels ) ) {
					$total_count = 0;
					$details = array();
					$upcoming_by_trigger = array();
					
					// Calcular quando cada nível vai disparar o alerta
					foreach ( $levels as $item ) {
						$days_until_expiry = (int) $item->days_until_expiry;
						$days_until_trigger = $days_until_expiry - $days_config;
						
						// Se o disparo é nos próximos 30 dias
						if ( $days_until_trigger >= 0 && $days_until_trigger <= 30 ) {
							$total_count += $item->count;
							
							$trigger_key = $days_until_trigger;
							if ( ! isset( $upcoming_by_trigger[ $trigger_key ] ) ) {
								$upcoming_by_trigger[ $trigger_key ] = array(
									'count' => 0,
									'levels' => array(),
									'days' => $days_until_trigger,
								);
							}
							
							$upcoming_by_trigger[ $trigger_key ]['count'] += $item->count;
							$upcoming_by_trigger[ $trigger_key ]['levels'][] = $item->level_name . ' (' . $item->count . ')';
						}
					}
					
					// Ordenar por data de disparo
					ksort( $upcoming_by_trigger );
					
					// Criar lista de detalhes
					foreach ( array_slice( $upcoming_by_trigger, 0, 10, true ) as $item ) {
						$days = (int) $item['days'];
						
						if ( $days === 0 ) {
							$date_text = __( 'hoje', 'person-cash-wallet' );
						} elseif ( $days === 1 ) {
							$date_text = __( 'amanhã', 'person-cash-wallet' );
						} else {
							$date_text = sprintf(
								/* translators: %d: days until trigger */
								_n( 'em %d dia', 'em %d dias', $days, 'person-cash-wallet' ),
								$days
							);
						}
						
						$details[] = array(
							'text' => sprintf(
								/* translators: 1: count 2: level names */
								__( '%1$d alerta(s) - %2$s', 'person-cash-wallet' ),
								$item['count'],
								implode( ', ', array_slice( $item['levels'], 0, 3 ) )
							),
							'date' => $date_text,
						);
					}

					if ( $total_count > 0 ) {
						$projections[] = array(
							'icon'     => 'dashicons-awards',
							'title'    => __( 'Níveis Expirando', 'person-cash-wallet' ),
							'subtitle' => sprintf(
								/* translators: %d: days before expiration */
								__( 'Alertas %d dias antes da expiração', 'person-cash-wallet' ),
								$days_config
							),
							'count'    => $total_count,
							'label'    => _n( 'disparo', 'disparos', $total_count, 'person-cash-wallet' ),
							'details'  => $details,
						);
					}
				}
				break;

			case 'scheduled_order_check':
				// Usar a mesma lógica da lista detalhada para contar pedidos
				$config_proj = ! empty( $workflow->trigger_config ) ? 
					( is_string( $workflow->trigger_config ) ? json_decode( $workflow->trigger_config, true ) : $workflow->trigger_config ) : 
					array();
				
				$status = isset( $config_proj['target_status'] ) ? $config_proj['target_status'] : ( isset( $config_proj['order_status'] ) ? $config_proj['order_status'] : 'pending' );
				$min_days = isset( $config_proj['min_days_in_status'] ) ? absint( $config_proj['min_days_in_status'] ) : ( isset( $config_proj['days_old'] ) ? absint( $config_proj['days_old'] ) : 1 );

				// Reutilizar a lista detalhada para garantir mesmo count
				$order_details = $this->get_scheduled_order_details( $config_proj, $workflow->id );

				if ( ! empty( $order_details ) ) {
					$projections[] = array(
						'icon'     => 'dashicons-cart',
						'title'    => __( 'Verificação de Pedidos Agendada', 'person-cash-wallet' ),
						'subtitle' => sprintf(
							/* translators: 1: order status 2: days old */
							__( 'Pedidos "%1$s" com mais de %2$d dias', 'person-cash-wallet' ),
							wc_get_order_status_name( $status ),
							$min_days
						),
						'count'    => count( $order_details ),
						'label'    => __( 'pedidos aguardando', 'person-cash-wallet' ),
						'details'  => array(),
					);
				}
				break;

			case 'order_status_changed':
			case 'order_created':
				// Estes são gatilhos imediatos, mostrar média de disparos
				$logs_table = $wpdb->prefix . 'pcw_workflow_logs';
				$avg_per_day = $wpdb->get_var( $wpdb->prepare(
					"SELECT AVG(daily_count) 
					FROM (
						SELECT DATE(executed_at) as date, COUNT(*) as daily_count
						FROM {$logs_table}
						WHERE workflow_id = %d
						AND executed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
						GROUP BY DATE(executed_at)
					) as daily_stats",
					$workflow->id
				) );

				if ( $avg_per_day > 0 ) {
					$avg_per_day = round( $avg_per_day, 1 );
					$trigger_name = 'order_status_changed' === $workflow->trigger_type ? 
						__( 'Mudança de Status', 'person-cash-wallet' ) : 
						__( 'Novo Pedido', 'person-cash-wallet' );

					$projections[] = array(
						'icon'     => 'dashicons-update',
						'title'    => $trigger_name,
						'subtitle' => __( 'Baseado na média dos últimos 30 dias', 'person-cash-wallet' ),
						'count'    => $avg_per_day,
						'label'    => __( 'disparos/dia', 'person-cash-wallet' ),
						'details'  => array(),
					);
				}
				break;

			case 'customer_registered':
				// Média de novos clientes
				$avg_new_users = $wpdb->get_var(
					"SELECT AVG(daily_count) 
					FROM (
						SELECT DATE(user_registered) as date, COUNT(*) as daily_count
						FROM {$wpdb->users}
						WHERE user_registered >= DATE_SUB(NOW(), INTERVAL 30 DAY)
						GROUP BY DATE(user_registered)
					) as daily_stats"
				);

				if ( $avg_new_users > 0 ) {
					$avg_new_users = round( $avg_new_users, 1 );
					$projections[] = array(
						'icon'     => 'dashicons-admin-users',
						'title'    => __( 'Novos Clientes', 'person-cash-wallet' ),
						'subtitle' => __( 'Baseado na média dos últimos 30 dias', 'person-cash-wallet' ),
						'count'    => $avg_new_users,
						'label'    => __( 'disparos/dia', 'person-cash-wallet' ),
						'details'  => array(),
					);
				}
				break;
		}

		// Fallback: Se não há projeção específica, mostrar média de execuções gerais
		if ( empty( $projections ) ) {
			$avg_per_day = $wpdb->get_var( $wpdb->prepare(
				"SELECT AVG(daily_count) 
				FROM (
					SELECT DATE(executed_at) as date, COUNT(*) as daily_count
					FROM {$logs_table}
					WHERE workflow_id = %d
					AND executed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
					GROUP BY DATE(executed_at)
				) as daily_stats",
				$workflow->id
			) );

			if ( $avg_per_day > 0 ) {
				$avg_per_day = round( $avg_per_day, 1 );
				
				// Obter nome do gatilho
				$triggers = PCW_Workflow_Triggers::get_all();
				$trigger_name = isset( $triggers[ $workflow->trigger_type ] ) ? 
					$triggers[ $workflow->trigger_type ]['label'] : 
					ucfirst( str_replace( '_', ' ', $workflow->trigger_type ) );

				$projections[] = array(
					'icon'     => 'dashicons-chart-line',
					'title'    => $trigger_name,
					'subtitle' => __( 'Baseado na média dos últimos 30 dias', 'person-cash-wallet' ),
					'count'    => $avg_per_day,
					'label'    => __( 'disparos/dia', 'person-cash-wallet' ),
					'details'  => array(),
				);
			}
		}

		return $projections;
	}
}
