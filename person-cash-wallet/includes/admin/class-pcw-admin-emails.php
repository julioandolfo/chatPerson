<?php
/**
 * Classe de administração de emails enviados
 *
 * @package PersonCashWallet
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe admin de emails
 */
class PCW_Admin_Emails {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Admin_Emails
	 */
	private static $instance = null;

	/**
	 * Obter instância singleton
	 *
	 * @return PCW_Admin_Emails
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor
	 */
	private function __construct() {
		// Singleton
	}

	/**
	 * Inicializar hooks
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_pcw_view_email', array( $this, 'ajax_view_email' ) );
		add_action( 'wp_ajax_pcw_delete_email_log', array( $this, 'ajax_delete_email_log' ) );
		add_action( 'wp_ajax_pcw_resend_email', array( $this, 'ajax_resend_email' ) );
	}

	/**
	 * Adicionar menu
	 */
	public function add_menu() {
		add_submenu_page(
			'pcw-dashboard',
			__( 'Emails Enviados', 'person-cash-wallet' ),
			__( 'Emails Enviados', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-emails',
			array( $this, 'render_page' ),
			40
		);
	}

	/**
	 * Enfileirar scripts
	 *
	 * @param string $hook Hook da página.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'growly-digital_page_pcw-emails' !== $hook && 'pcw-dashboard_page_pcw-emails' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'pcw-admin-emails',
			PCW_PLUGIN_URL . 'assets/css/admin-emails.css',
			array(),
			PCW_VERSION
		);

		wp_enqueue_script(
			'pcw-admin-emails',
			PCW_PLUGIN_URL . 'assets/js/admin-emails.js',
			array( 'jquery', 'wp-util' ),
			PCW_VERSION,
			true
		);

		wp_localize_script( 'pcw-admin-emails', 'pcwAdminEmails', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pcw_admin_emails' ),
			'i18n'    => array(
				'confirmDelete' => __( 'Tem certeza que deseja excluir este log de email?', 'person-cash-wallet' ),
				'confirmResend' => __( 'Tem certeza que deseja reenviar este email?', 'person-cash-wallet' ),
				'deleted'       => __( 'Log excluído!', 'person-cash-wallet' ),
				'resent'        => __( 'Email reenviado!', 'person-cash-wallet' ),
				'error'         => __( 'Erro ao processar.', 'person-cash-wallet' ),
			),
		) );
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'all';

		$tabs = array(
			'all'      => __( 'Todos', 'person-cash-wallet' ),
			'stats'    => __( 'Estatísticas', 'person-cash-wallet' ),
		);

		$email_types = PCW_Email_Handler::get_email_types();
		$stats = PCW_Email_Handler::get_email_stats();

		?>
		<div class="wrap pcw-admin-wrap">
			<h1>
				<span class="dashicons dashicons-email-alt"></span>
				<?php esc_html_e( 'Emails Enviados', 'person-cash-wallet' ); ?>
			</h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-emails&tab=' . $tab_id ) ); ?>" 
					   class="nav-tab <?php echo $tab === $tab_id ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_name ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="pcw-admin-content">
				<?php
				switch ( $tab ) {
					case 'stats':
						$this->render_stats( $stats, $email_types );
						break;
					default:
						$this->render_emails_list( $email_types );
						break;
				}
				?>
			</div>
		</div>

		<!-- Modal para visualizar email -->
		<div id="pcw-email-modal" class="pcw-modal" style="display: none;">
			<div class="pcw-modal-overlay"></div>
			<div class="pcw-modal-content">
				<div class="pcw-modal-header">
					<h2><?php esc_html_e( 'Visualizar Email', 'person-cash-wallet' ); ?></h2>
					<button type="button" class="pcw-modal-close">&times;</button>
				</div>
				<div class="pcw-modal-body">
					<div id="pcw-email-preview"></div>
				</div>
			</div>
		</div>

		<style>
			.pcw-admin-wrap {
				max-width: 1400px;
			}
			.pcw-admin-wrap > h1 {
				display: flex;
				align-items: center;
				gap: 10px;
			}
			.pcw-admin-wrap > h1 .dashicons {
				font-size: 28px;
				width: 28px;
				height: 28px;
			}
			.pcw-admin-content {
				margin-top: 20px;
			}
			.pcw-stats-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 20px;
				margin-bottom: 30px;
			}
			.pcw-stat-card {
				background: #fff;
				padding: 25px;
				border-radius: 8px;
				box-shadow: 0 1px 3px rgba(0,0,0,0.1);
				text-align: center;
			}
			.pcw-stat-value {
				font-size: 36px;
				font-weight: 700;
				color: #667eea;
			}
			.pcw-stat-label {
				font-size: 13px;
				color: #666;
				margin-top: 5px;
			}
			.pcw-stat-card.success .pcw-stat-value {
				color: #059669;
			}
			.pcw-stat-card.danger .pcw-stat-value {
				color: #dc2626;
			}
			.pcw-section {
				background: #fff;
				padding: 25px;
				border-radius: 8px;
				box-shadow: 0 1px 3px rgba(0,0,0,0.1);
				margin-bottom: 20px;
			}
			.pcw-section-title {
				font-size: 16px;
				font-weight: 600;
				margin: 0 0 20px 0;
				padding-bottom: 10px;
				border-bottom: 1px solid #eee;
			}
			.pcw-table {
				width: 100%;
				border-collapse: collapse;
			}
			.pcw-table th,
			.pcw-table td {
				padding: 12px 15px;
				text-align: left;
				border-bottom: 1px solid #eee;
			}
			.pcw-table th {
				font-weight: 600;
				background: #f8f9fa;
			}
			.pcw-table tr:hover {
				background: #f8f9fa;
			}
			.pcw-badge {
				display: inline-block;
				padding: 4px 10px;
				border-radius: 20px;
				font-size: 11px;
				font-weight: 500;
			}
			.pcw-badge-sent {
				background: #d1fae5;
				color: #065f46;
			}
			.pcw-badge-failed {
				background: #fee2e2;
				color: #991b1b;
			}
			.pcw-filters {
				display: flex;
				gap: 10px;
				margin-bottom: 20px;
				flex-wrap: wrap;
				align-items: center;
			}
			.pcw-filters select,
			.pcw-filters input[type="search"] {
				padding: 8px 12px;
			}
			.pcw-pagination {
				display: flex;
				gap: 5px;
				margin-top: 20px;
				justify-content: center;
			}
			.pcw-actions {
				display: flex;
				gap: 5px;
			}
			.pcw-action-btn {
				padding: 5px 10px;
				font-size: 12px;
				cursor: pointer;
				background: none;
				border: 1px solid #ccc;
				border-radius: 3px;
			}
			.pcw-action-btn:hover {
				background: #f0f0f0;
			}
			.pcw-action-btn.danger {
				color: #dc2626;
				border-color: #dc2626;
			}
			.pcw-action-btn.danger:hover {
				background: #fee2e2;
			}
			.pcw-empty {
				text-align: center;
				padding: 40px;
				color: #666;
			}
			.pcw-email-subject {
				max-width: 300px;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}
			.pcw-email-recipient {
				max-width: 200px;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}
			.pcw-types-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
				gap: 15px;
			}
			.pcw-type-card {
				background: #f8f9fa;
				padding: 15px;
				border-radius: 6px;
				text-align: center;
			}
			.pcw-type-count {
				font-size: 24px;
				font-weight: 700;
				color: #667eea;
			}
			.pcw-type-name {
				font-size: 12px;
				color: #666;
				margin-top: 5px;
			}
			/* Modal */
			.pcw-modal {
				position: fixed;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				z-index: 100000;
			}
			.pcw-modal-overlay {
				position: absolute;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				background: rgba(0,0,0,0.5);
			}
			.pcw-modal-content {
				position: absolute;
				top: 50%;
				left: 50%;
				transform: translate(-50%, -50%);
				background: #fff;
				border-radius: 8px;
				width: 90%;
				max-width: 800px;
				max-height: 80vh;
				overflow: hidden;
				display: flex;
				flex-direction: column;
			}
			.pcw-modal-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 15px 20px;
				border-bottom: 1px solid #eee;
			}
			.pcw-modal-header h2 {
				margin: 0;
				font-size: 18px;
			}
			.pcw-modal-close {
				background: none;
				border: none;
				font-size: 24px;
				cursor: pointer;
				color: #999;
			}
			.pcw-modal-close:hover {
				color: #333;
			}
			.pcw-modal-body {
				padding: 20px;
				overflow-y: auto;
				flex: 1;
			}
			#pcw-email-preview {
				border: 1px solid #eee;
				border-radius: 4px;
				min-height: 300px;
			}
			#pcw-email-preview iframe {
				width: 100%;
				height: 400px;
				border: none;
			}
			.pcw-email-meta {
				background: #f8f9fa;
				padding: 15px;
				border-radius: 4px;
				margin-bottom: 15px;
			}
			.pcw-email-meta-item {
				display: flex;
				margin-bottom: 8px;
			}
			.pcw-email-meta-item:last-child {
				margin-bottom: 0;
			}
			.pcw-email-meta-label {
				font-weight: 600;
				width: 120px;
				flex-shrink: 0;
			}
			.pcw-email-meta-value {
				color: #666;
			}
		</style>
		<?php
	}

	/**
	 * Renderizar estatísticas
	 *
	 * @param array $stats Estatísticas.
	 * @param array $email_types Tipos de email.
	 */
	private function render_stats( $stats, $email_types ) {
		?>
		<div class="pcw-stats-grid">
			<div class="pcw-stat-card">
				<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></div>
				<div class="pcw-stat-label"><?php esc_html_e( 'Total de Emails', 'person-cash-wallet' ); ?></div>
			</div>
			<div class="pcw-stat-card success">
				<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['sent'] ) ); ?></div>
				<div class="pcw-stat-label"><?php esc_html_e( 'Enviados', 'person-cash-wallet' ); ?></div>
			</div>
			<div class="pcw-stat-card danger">
				<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['failed'] ) ); ?></div>
				<div class="pcw-stat-label"><?php esc_html_e( 'Falhas', 'person-cash-wallet' ); ?></div>
			</div>
			<div class="pcw-stat-card">
				<div class="pcw-stat-value"><?php echo esc_html( $stats['success_rate'] ); ?>%</div>
				<div class="pcw-stat-label"><?php esc_html_e( 'Taxa de Sucesso', 'person-cash-wallet' ); ?></div>
			</div>
			<div class="pcw-stat-card">
				<div class="pcw-stat-value"><?php echo esc_html( number_format_i18n( $stats['last_30_days'] ) ); ?></div>
				<div class="pcw-stat-label"><?php esc_html_e( 'Últimos 30 dias', 'person-cash-wallet' ); ?></div>
			</div>
		</div>

		<div class="pcw-section">
			<h2 class="pcw-section-title"><?php esc_html_e( 'Emails por Tipo', 'person-cash-wallet' ); ?></h2>
			
			<?php if ( empty( $stats['by_type'] ) ) : ?>
				<p class="pcw-empty"><?php esc_html_e( 'Nenhum email enviado ainda.', 'person-cash-wallet' ); ?></p>
			<?php else : ?>
				<div class="pcw-types-grid">
					<?php foreach ( $stats['by_type'] as $type_stat ) : ?>
						<div class="pcw-type-card">
							<div class="pcw-type-count"><?php echo esc_html( number_format_i18n( $type_stat['count'] ) ); ?></div>
							<div class="pcw-type-name">
								<?php 
								echo esc_html( isset( $email_types[ $type_stat['email_type'] ] ) 
									? $email_types[ $type_stat['email_type'] ] 
									: $type_stat['email_type'] 
								); 
								?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renderizar lista de emails
	 *
	 * @param array $email_types Tipos de email.
	 */
	private function render_emails_list( $email_types ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$email_type = isset( $_GET['email_type'] ) ? sanitize_text_field( wp_unslash( $_GET['email_type'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

		$per_page = 30;
		$offset = ( $paged - 1 ) * $per_page;

		$args = array(
			'email_type' => $email_type,
			'status'     => $status,
			'search'     => $search,
			'limit'      => $per_page,
			'offset'     => $offset,
		);

		$emails = PCW_Email_Handler::get_email_logs( $args );
		$total = PCW_Email_Handler::count_email_logs( $args );
		$total_pages = ceil( $total / $per_page );

		?>
		<div class="pcw-section">
			<div class="pcw-filters">
				<form method="get" action="">
					<input type="hidden" name="page" value="pcw-emails" />
					<input type="hidden" name="tab" value="all" />

					<select name="email_type">
						<option value=""><?php esc_html_e( 'Todos os tipos', 'person-cash-wallet' ); ?></option>
						<?php foreach ( $email_types as $type_key => $type_name ) : ?>
							<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $email_type, $type_key ); ?>>
								<?php echo esc_html( $type_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<select name="status">
						<option value=""><?php esc_html_e( 'Todos os status', 'person-cash-wallet' ); ?></option>
						<option value="sent" <?php selected( $status, 'sent' ); ?>><?php esc_html_e( 'Enviado', 'person-cash-wallet' ); ?></option>
						<option value="failed" <?php selected( $status, 'failed' ); ?>><?php esc_html_e( 'Falha', 'person-cash-wallet' ); ?></option>
					</select>

					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar destinatário ou assunto...', 'person-cash-wallet' ); ?>" style="min-width: 250px;" />

					<button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'person-cash-wallet' ); ?></button>

					<?php if ( ! empty( $email_type ) || ! empty( $status ) || ! empty( $search ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-emails' ) ); ?>" class="button">
							<?php esc_html_e( 'Limpar', 'person-cash-wallet' ); ?>
						</a>
					<?php endif; ?>
				</form>
			</div>

			<?php if ( empty( $emails ) ) : ?>
				<p class="pcw-empty"><?php esc_html_e( 'Nenhum email encontrado.', 'person-cash-wallet' ); ?></p>
			<?php else : ?>
				<table class="pcw-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Destinatário', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Assunto', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Tipo', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Data', 'person-cash-wallet' ); ?></th>
							<th><?php esc_html_e( 'Ações', 'person-cash-wallet' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $emails as $email ) : ?>
							<tr data-id="<?php echo esc_attr( $email->id ); ?>">
								<td><?php echo esc_html( $email->id ); ?></td>
								<td class="pcw-email-recipient" title="<?php echo esc_attr( $email->recipient ); ?>">
									<?php echo esc_html( $email->recipient ); ?>
								</td>
								<td class="pcw-email-subject" title="<?php echo esc_attr( $email->subject ); ?>">
									<?php echo esc_html( $email->subject ); ?>
								</td>
								<td>
									<?php 
									echo esc_html( isset( $email_types[ $email->email_type ] ) 
										? $email_types[ $email->email_type ] 
										: $email->email_type 
									); 
									?>
								</td>
								<td>
									<span class="pcw-badge pcw-badge-<?php echo esc_attr( $email->status ); ?>">
										<?php echo 'sent' === $email->status ? esc_html__( 'Enviado', 'person-cash-wallet' ) : esc_html__( 'Falha', 'person-cash-wallet' ); ?>
									</span>
								</td>
								<td>
									<?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $email->created_at ) ) ); ?>
								</td>
								<td class="pcw-actions">
									<button type="button" class="pcw-action-btn pcw-view-email" data-id="<?php echo esc_attr( $email->id ); ?>" title="<?php esc_attr_e( 'Visualizar', 'person-cash-wallet' ); ?>">
										<span class="dashicons dashicons-visibility"></span>
									</button>
									<button type="button" class="pcw-action-btn pcw-resend-email" data-id="<?php echo esc_attr( $email->id ); ?>" title="<?php esc_attr_e( 'Reenviar', 'person-cash-wallet' ); ?>">
										<span class="dashicons dashicons-update"></span>
									</button>
									<button type="button" class="pcw-action-btn danger pcw-delete-email" data-id="<?php echo esc_attr( $email->id ); ?>" title="<?php esc_attr_e( 'Excluir', 'person-cash-wallet' ); ?>">
										<span class="dashicons dashicons-trash"></span>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="pcw-pagination">
						<?php
						$base_url = admin_url( 'admin.php?page=pcw-emails&tab=all' );
						if ( ! empty( $email_type ) ) {
							$base_url .= '&email_type=' . $email_type;
						}
						if ( ! empty( $status ) ) {
							$base_url .= '&status=' . $status;
						}
						if ( ! empty( $search ) ) {
							$base_url .= '&s=' . urlencode( $search );
						}

						for ( $i = 1; $i <= $total_pages; $i++ ) :
							$class = $i === $paged ? 'button button-primary' : 'button';
							?>
							<a href="<?php echo esc_url( $base_url . '&paged=' . $i ); ?>" class="<?php echo esc_attr( $class ); ?>">
								<?php echo esc_html( $i ); ?>
							</a>
						<?php endfor; ?>
					</div>
				<?php endif; ?>

				<p style="margin-top: 15px; color: #666; font-size: 13px;">
					<?php
					printf(
						/* translators: %1$d: current page, %2$d: total pages, %3$d: total items */
						esc_html__( 'Página %1$d de %2$d (%3$d emails)', 'person-cash-wallet' ),
						$paged,
						$total_pages,
						$total
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX: Visualizar email
	 */
	public function ajax_view_email() {
		check_ajax_referer( 'pcw_admin_emails', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'person-cash-wallet' ) ) );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'ID inválido.', 'person-cash-wallet' ) ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'pcw_email_logs';
		$email = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $id ) );

		if ( ! $email ) {
			wp_send_json_error( array( 'message' => __( 'Email não encontrado.', 'person-cash-wallet' ) ) );
		}

		$email_types = PCW_Email_Handler::get_email_types();
		$type_label = isset( $email_types[ $email->email_type ] ) ? $email_types[ $email->email_type ] : $email->email_type;

		wp_send_json_success( array(
			'id'         => $email->id,
			'recipient'  => $email->recipient,
			'subject'    => $email->subject,
			'content'    => $email->content,
			'email_type' => $type_label,
			'status'     => $email->status,
			'created_at' => date_i18n( 'd/m/Y H:i:s', strtotime( $email->created_at ) ),
		) );
	}

	/**
	 * AJAX: Excluir log de email
	 */
	public function ajax_delete_email_log() {
		check_ajax_referer( 'pcw_admin_emails', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'person-cash-wallet' ) ) );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'ID inválido.', 'person-cash-wallet' ) ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'pcw_email_logs';
		$result = $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Erro ao excluir.', 'person-cash-wallet' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Log excluído com sucesso!', 'person-cash-wallet' ) ) );
	}

	/**
	 * AJAX: Reenviar email
	 */
	public function ajax_resend_email() {
		check_ajax_referer( 'pcw_admin_emails', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'person-cash-wallet' ) ) );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'ID inválido.', 'person-cash-wallet' ) ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'pcw_email_logs';
		$email = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $id ) );

		if ( ! $email ) {
			wp_send_json_error( array( 'message' => __( 'Email não encontrado.', 'person-cash-wallet' ) ) );
		}

		// Reenviar o email sem envolver no template HTML novamente (já está formatado)
		$result = PCW_Email_Handler::send(
			$email->recipient,
			$email->subject,
			$email->content,
			array(),
			array(),
			false, // Não envolver no template
			array(
				'email_type' => 'resend_' . $email->email_type,
				'related_id' => $email->id,
			)
		);

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Email reenviado com sucesso!', 'person-cash-wallet' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Falha ao reenviar email.', 'person-cash-wallet' ) ) );
		}
	}
}
