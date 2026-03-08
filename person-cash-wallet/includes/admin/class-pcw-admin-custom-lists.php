<?php
/**
 * Admin - Listas Personalizadas
 *
 * @package PersonCashWallet
 * @since 1.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de administração de listas
 */
class PCW_Admin_Custom_Lists {

	/**
	 * Inicializar
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 65 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// AJAX endpoints
		add_action( 'wp_ajax_pcw_save_custom_list', array( __CLASS__, 'ajax_save_list' ) );
		add_action( 'wp_ajax_pcw_upload_list_file', array( __CLASS__, 'ajax_upload_file' ) );
		add_action( 'wp_ajax_pcw_add_list_member', array( __CLASS__, 'ajax_add_member' ) );
		add_action( 'wp_ajax_pcw_remove_list_member', array( __CLASS__, 'ajax_remove_member' ) );
		add_action( 'wp_ajax_pcw_delete_custom_list', array( __CLASS__, 'ajax_delete_list' ) );
		add_action( 'wp_ajax_pcw_get_list_members', array( __CLASS__, 'ajax_get_members' ) );
	}

	/**
	 * Adicionar menu
	 */
	public static function add_menu() {
		add_submenu_page(
			'pcw-dashboard',
			__( 'Listas de Contatos', 'person-cash-wallet' ),
			__( 'Listas de Contatos', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-custom-lists',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enfileirar assets
	 *
	 * @param string $hook Hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'pcw-custom-lists' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'pcw-admin-custom-lists',
			PCW_PLUGIN_URL . 'assets/css/admin-campaigns.css',
			array( 'pcw-admin-global' ),
			PCW_VERSION
		);

		wp_enqueue_script(
			'pcw-admin-custom-lists',
			PCW_PLUGIN_URL . 'assets/js/admin-custom-lists.js',
			array( 'jquery', 'wp-util' ),
			PCW_VERSION,
			true
		);

		wp_localize_script(
			'pcw-admin-custom-lists',
			'pcwCustomLists',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pcw_custom_lists' ),
				'i18n'    => array(
					'confirm_delete' => __( 'Tem certeza que deseja deletar esta lista?', 'person-cash-wallet' ),
					'confirm_remove' => __( 'Tem certeza que deseja remover este membro?', 'person-cash-wallet' ),
				),
			)
		);
	}

	/**
	 * Renderizar página
	 */
	public static function render_page() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$list_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		?>
		<div class="wrap pcw-admin">
			<div class="pcw-page-header">
				<div>
					<h1>
						<span class="dashicons dashicons-groups"></span>
						<?php esc_html_e( 'Listas de Contatos', 'person-cash-wallet' ); ?>
					</h1>
				</div>
				<?php if ( 'list' === $action ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-custom-lists&action=new' ) ); ?>" class="button button-primary">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Nova Lista', 'person-cash-wallet' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<?php
			switch ( $action ) {
				case 'new':
					self::render_form();
					break;
				case 'edit':
					self::render_form( $list_id );
					break;
				case 'view':
					self::render_view( $list_id );
					break;
				default:
					self::render_list();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Renderizar lista de listas
	 */
	private static function render_list() {
		$lists = PCW_Custom_Lists::get_all();

		?>
		<div class="pcw-card">
			<div class="pcw-card-header">
				<h2><?php esc_html_e( 'Minhas Listas', 'person-cash-wallet' ); ?></h2>
			</div>
			<div class="pcw-card-body">
				<?php if ( empty( $lists ) ) : ?>
					<div class="pcw-empty-state">
						<span class="dashicons dashicons-groups"></span>
						<h3><?php esc_html_e( 'Nenhuma lista encontrada', 'person-cash-wallet' ); ?></h3>
						<p><?php esc_html_e( 'Crie sua primeira lista para organizar seus contatos.', 'person-cash-wallet' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-custom-lists&action=new' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Criar Lista', 'person-cash-wallet' ); ?>
						</a>
					</div>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Nome', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Descrição', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Membros', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Criado em', 'person-cash-wallet' ); ?></th>
								<th><?php esc_html_e( 'Ações', 'person-cash-wallet' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $lists as $list ) : ?>
								<tr>
									<td>
										<strong>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-custom-lists&action=view&id=' . $list->id ) ); ?>">
												<?php echo esc_html( $list->name ); ?>
											</a>
										</strong>
									</td>
									<td><?php echo esc_html( $list->description ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $list->total_members ) ); ?></td>
									<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $list->created_at ) ); ?></td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-custom-lists&action=view&id=' . $list->id ) ); ?>" class="button button-small">
											<span class="dashicons dashicons-visibility"></span>
											<?php esc_html_e( 'Ver', 'person-cash-wallet' ); ?>
										</a>
										<button type="button" class="button button-small pcw-delete-list" data-list-id="<?php echo esc_attr( $list->id ); ?>">
											<span class="dashicons dashicons-trash"></span>
											<?php esc_html_e( 'Deletar', 'person-cash-wallet' ); ?>
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
	 * Renderizar formulário de criação/edição
	 *
	 * @param int $list_id ID da lista (0 para nova).
	 */
	private static function render_form( $list_id = 0 ) {
		$list = $list_id ? PCW_Custom_Lists::get( $list_id ) : null;

		?>
		<form id="pcw-list-form" class="pcw-form">
			<input type="hidden" name="list_id" value="<?php echo esc_attr( $list_id ); ?>">

			<div class="pcw-card">
				<div class="pcw-card-header">
					<h2><?php echo $list_id ? esc_html__( 'Editar Lista', 'person-cash-wallet' ) : esc_html__( 'Nova Lista', 'person-cash-wallet' ); ?></h2>
				</div>
				<div class="pcw-card-body">
					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Nome da Lista', 'person-cash-wallet' ); ?></label>
						<input type="text" name="name" value="<?php echo $list ? esc_attr( $list->name ) : ''; ?>" required>
					</div>

					<div class="pcw-form-group">
						<label><?php esc_html_e( 'Descrição', 'person-cash-wallet' ); ?></label>
						<textarea name="description" rows="3"><?php echo $list ? esc_textarea( $list->description ) : ''; ?></textarea>
					</div>

					<button type="submit" class="button button-primary">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e( 'Salvar Lista', 'person-cash-wallet' ); ?>
					</button>

					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcw-custom-lists' ) ); ?>" class="button">
						<?php esc_html_e( 'Cancelar', 'person-cash-wallet' ); ?>
					</a>
				</div>
			</div>
		</form>
		<?php
	}

	/**
	 * Renderizar visualização da lista
	 *
	 * @param int $list_id ID da lista.
	 */
	private static function render_view( $list_id ) {
		$list = PCW_Custom_Lists::get( $list_id );

		if ( ! $list ) {
			?>
			<div class="pcw-notice pcw-notice-error">
				<?php esc_html_e( 'Lista não encontrada', 'person-cash-wallet' ); ?>
			</div>
			<?php
			return;
		}

		$members = PCW_Custom_Lists::get_members( $list_id, array( 'limit' => 1000 ) );

		?>
		<div class="pcw-card">
			<div class="pcw-card-header">
				<h2><?php echo esc_html( $list->name ); ?></h2>
			</div>
			<div class="pcw-card-body">
				<?php if ( $list->description ) : ?>
					<p><?php echo esc_html( $list->description ); ?></p>
				<?php endif; ?>

				<p>
					<strong><?php esc_html_e( 'Total de membros:', 'person-cash-wallet' ); ?></strong>
					<?php echo esc_html( number_format_i18n( $list->total_members ) ); ?>
				</p>

				<!-- Upload de arquivo -->
				<div class="pcw-form-group">
					<h3><?php esc_html_e( 'Importar de Arquivo', 'person-cash-wallet' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Faça upload de um arquivo CSV com as colunas: email, nome (opcional), telefone (opcional)', 'person-cash-wallet' ); ?>
					</p>
					<form id="pcw-upload-form" enctype="multipart/form-data">
						<input type="hidden" name="list_id" value="<?php echo esc_attr( $list_id ); ?>">
						<input type="file" name="file" accept=".csv,.xls,.xlsx" required>
						<button type="submit" class="button button-secondary">
							<span class="dashicons dashicons-upload"></span>
							<?php esc_html_e( 'Upload', 'person-cash-wallet' ); ?>
						</button>
					</form>
					<div id="upload-result" style="margin-top: 10px;"></div>
				</div>

				<!-- Adicionar membro manualmente -->
				<div class="pcw-form-group">
					<h3><?php esc_html_e( 'Adicionar Membro Manualmente', 'person-cash-wallet' ); ?></h3>
					<form id="pcw-add-member-form" style="display: flex; gap: 10px;">
						<input type="hidden" name="list_id" value="<?php echo esc_attr( $list_id ); ?>">
						<input type="email" name="email" placeholder="Email" required style="flex: 1;">
						<input type="text" name="name" placeholder="Nome (opcional)" style="flex: 1;">
						<button type="submit" class="button button-secondary">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Adicionar', 'person-cash-wallet' ); ?>
						</button>
					</form>
				</div>

				<!-- Lista de membros -->
				<h3><?php esc_html_e( 'Membros', 'person-cash-wallet' ); ?></h3>
				<div id="members-list">
					<?php if ( empty( $members ) ) : ?>
						<p><?php esc_html_e( 'Nenhum membro nesta lista ainda.', 'person-cash-wallet' ); ?></p>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Email', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Nome', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Telefone', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Adicionado em', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Ações', 'person-cash-wallet' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $members as $member ) : ?>
									<tr>
										<td><?php echo esc_html( $member->email ); ?></td>
										<td><?php echo esc_html( $member->name ); ?></td>
										<td><?php echo esc_html( $member->phone ); ?></td>
										<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $member->added_at ) ); ?></td>
										<td>
											<button type="button" class="button button-small pcw-remove-member" data-member-id="<?php echo esc_attr( $member->id ); ?>">
												<span class="dashicons dashicons-trash"></span>
												<?php esc_html_e( 'Remover', 'person-cash-wallet' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Salvar lista
	 */
	public static function ajax_save_list() {
		check_ajax_referer( 'pcw_custom_lists', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$list_id = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0;
		$name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Nome é obrigatório', 'person-cash-wallet' ) ) );
		}

		$result = PCW_Custom_Lists::create( array(
			'name'        => $name,
			'description' => $description,
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'  => __( 'Lista salva com sucesso!', 'person-cash-wallet' ),
			'redirect' => admin_url( 'admin.php?page=pcw-custom-lists&action=view&id=' . $result ),
		) );
	}

	/**
	 * AJAX: Upload de arquivo
	 */
	public static function ajax_upload_file() {
		check_ajax_referer( 'pcw_custom_lists', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$list_id = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0;

		if ( ! $list_id ) {
			wp_send_json_error( array( 'message' => __( 'Lista não encontrada', 'person-cash-wallet' ) ) );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Nenhum arquivo enviado', 'person-cash-wallet' ) ) );
		}

		$file = $_FILES['file'];
		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( isset( $upload['error'] ) ) {
			wp_send_json_error( array( 'message' => $upload['error'] ) );
		}

		$result = PCW_Custom_Lists::import_from_excel( $list_id, $upload['file'] );

		// Deletar arquivo temporário
		@unlink( $upload['file'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => sprintf(
				__( '%d membros adicionados, %d ignorados', 'person-cash-wallet' ),
				$result['added'],
				$result['skipped']
			),
			'result' => $result,
		) );
	}

	/**
	 * AJAX: Adicionar membro
	 */
	public static function ajax_add_member() {
		check_ajax_referer( 'pcw_custom_lists', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$list_id = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0;
		$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';

		if ( ! $list_id || ! $email ) {
			wp_send_json_error( array( 'message' => __( 'Dados inválidos', 'person-cash-wallet' ) ) );
		}

		$result = PCW_Custom_Lists::add_members( $list_id, array(
			array(
				'email' => $email,
				'name'  => $name,
			),
		) );

		if ( $result['added'] > 0 ) {
			wp_send_json_success( array(
				'message' => __( 'Membro adicionado com sucesso!', 'person-cash-wallet' ),
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Não foi possível adicionar o membro. Ele já pode estar na lista.', 'person-cash-wallet' ),
			) );
		}
	}

	/**
	 * AJAX: Remover membro
	 */
	public static function ajax_remove_member() {
		check_ajax_referer( 'pcw_custom_lists', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$list_id = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0;
		$member_id = isset( $_POST['member_id'] ) ? absint( $_POST['member_id'] ) : 0;

		if ( ! $list_id || ! $member_id ) {
			wp_send_json_error( array( 'message' => __( 'Dados inválidos', 'person-cash-wallet' ) ) );
		}

		$result = PCW_Custom_Lists::remove_member( $list_id, $member_id );

		if ( $result ) {
			wp_send_json_success( array(
				'message' => __( 'Membro removido com sucesso!', 'person-cash-wallet' ),
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Não foi possível remover o membro', 'person-cash-wallet' ),
			) );
		}
	}

	/**
	 * AJAX: Deletar lista
	 */
	public static function ajax_delete_list() {
		check_ajax_referer( 'pcw_custom_lists', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'person-cash-wallet' ) ) );
		}

		$list_id = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0;

		if ( ! $list_id ) {
			wp_send_json_error( array( 'message' => __( 'Lista não encontrada', 'person-cash-wallet' ) ) );
		}

		$result = PCW_Custom_Lists::delete( $list_id );

		if ( $result ) {
			wp_send_json_success( array(
				'message' => __( 'Lista deletada com sucesso!', 'person-cash-wallet' ),
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Não foi possível deletar a lista', 'person-cash-wallet' ),
			) );
		}
	}

	/**
	 * AJAX: Obter membros
	 */
	public static function ajax_get_members() {
		check_ajax_referer( 'pcw_custom_lists', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$list_id = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0;

		if ( ! $list_id ) {
			wp_send_json_error();
		}

		$members = PCW_Custom_Lists::get_members( $list_id );

		wp_send_json_success( array( 'members' => $members ) );
	}
}
