<?php
/**
 * Sistema de descadastramento (Unsubscribe)
 *
 * @package PersonCashWallet
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de unsubscribe
 */
class PCW_Unsubscribe {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Unsubscribe
	 */
	private static $instance = null;

	/**
	 * Obter instância
	 *
	 * @return PCW_Unsubscribe
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Inicializar
	 */
	public function init() {
		// Adicionar rewrite rules
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_unsubscribe_page' ) );

		// Adicionar link de unsubscribe nos emails
		add_filter( 'pcw_email_content', array( $this, 'add_unsubscribe_link' ), 10, 2 );

		// Handler AJAX
		add_action( 'wp_ajax_pcw_unsubscribe', array( $this, 'handle_ajax_unsubscribe' ) );
		add_action( 'wp_ajax_nopriv_pcw_unsubscribe', array( $this, 'handle_ajax_unsubscribe' ) );
	}

	/**
	 * Adicionar rewrite rules
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule( '^unsubscribe/?$', 'index.php?pcw_unsubscribe=1', 'top' );
		add_rewrite_rule( '^email-preferences/?$', 'index.php?pcw_email_preferences=1', 'top' );
	}

	/**
	 * Adicionar query vars
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'pcw_unsubscribe';
		$vars[] = 'pcw_email_preferences';
		$vars[] = 'email';
		$vars[] = 'token';
		return $vars;
	}

	/**
	 * Processar página de unsubscribe
	 */
	public function handle_unsubscribe_page() {
		$is_unsubscribe = get_query_var( 'pcw_unsubscribe' );
		$is_preferences = get_query_var( 'pcw_email_preferences' );

		if ( $is_unsubscribe ) {
			$this->render_unsubscribe_page();
			exit;
		}

		if ( $is_preferences ) {
			$this->render_preferences_page();
			exit;
		}
	}

	/**
	 * Renderizar página de unsubscribe
	 */
	private function render_unsubscribe_page() {
		$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		// Verificar token
		if ( ! $this->verify_token( $email, $token ) ) {
			wp_die( esc_html__( 'Link inválido ou expirado', 'person-cash-wallet' ) );
		}

		// Processar descadastramento se foi enviado
		$message = '';
		if ( isset( $_POST['pcw_unsubscribe_submit'] ) ) {
			if ( ! isset( $_POST['pcw_unsubscribe_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pcw_unsubscribe_nonce'] ) ), 'pcw_unsubscribe' ) ) {
				wp_die( esc_html__( 'Ação não autorizada', 'person-cash-wallet' ) );
			}

			$unsubscribe_all = isset( $_POST['unsubscribe_all'] ) && 'yes' === $_POST['unsubscribe_all'];
			$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
			$feedback = isset( $_POST['feedback'] ) ? sanitize_textarea_field( wp_unslash( $_POST['feedback'] ) ) : '';

			$this->process_unsubscribe( $email, null, $unsubscribe_all, $reason, $feedback );

			$message = '<div class="pcw-message pcw-message--success">' . esc_html__( 'Você foi descadastrado com sucesso.', 'person-cash-wallet' ) . '</div>';
		}

		get_header();
		?>
		<div class="pcw-unsubscribe-page">
			<div class="pcw-unsubscribe-container">
				<h1><?php esc_html_e( 'Descadastrar', 'person-cash-wallet' ); ?></h1>

				<?php echo wp_kses_post( $message ); ?>

				<?php if ( empty( $message ) ) : ?>
					<p><?php esc_html_e( 'Sentimos muito em vê-lo partir. Por favor, nos diga o motivo:', 'person-cash-wallet' ); ?></p>

					<form method="post" class="pcw-unsubscribe-form">
						<?php wp_nonce_field( 'pcw_unsubscribe', 'pcw_unsubscribe_nonce' ); ?>

						<div class="pcw-form-field">
							<label>
								<input type="radio" name="reason" value="too_many_emails" />
								<?php esc_html_e( 'Recebo muitos emails', 'person-cash-wallet' ); ?>
							</label>
						</div>

						<div class="pcw-form-field">
							<label>
								<input type="radio" name="reason" value="not_relevant" />
								<?php esc_html_e( 'O conteúdo não é relevante para mim', 'person-cash-wallet' ); ?>
							</label>
						</div>

						<div class="pcw-form-field">
							<label>
								<input type="radio" name="reason" value="never_signed_up" />
								<?php esc_html_e( 'Nunca me inscrevi', 'person-cash-wallet' ); ?>
							</label>
						</div>

						<div class="pcw-form-field">
							<label>
								<input type="radio" name="reason" value="other" />
								<?php esc_html_e( 'Outro motivo', 'person-cash-wallet' ); ?>
							</label>
						</div>

						<div class="pcw-form-field">
							<label for="feedback"><?php esc_html_e( 'Feedback adicional (opcional):', 'person-cash-wallet' ); ?></label>
							<textarea id="feedback" name="feedback" rows="4"></textarea>
						</div>

						<div class="pcw-form-field">
							<label>
								<input type="checkbox" name="unsubscribe_all" value="yes" />
								<?php esc_html_e( 'Descadastrar de TODAS as listas e comunicações', 'person-cash-wallet' ); ?>
							</label>
						</div>

						<button type="submit" name="pcw_unsubscribe_submit" class="pcw-button">
							<?php esc_html_e( 'Descadastrar', 'person-cash-wallet' ); ?>
						</button>

						<p class="pcw-alternative-link">
							<?php
							printf(
								/* translators: %s: link para página de preferências */
								esc_html__( 'Ou você pode %s para escolher quais emails receber.', 'person-cash-wallet' ),
								'<a href="' . esc_url( home_url( '/email-preferences/?email=' . urlencode( $email ) . '&token=' . urlencode( $token ) ) ) . '">' . esc_html__( 'gerenciar suas preferências', 'person-cash-wallet' ) . '</a>'
							);
							?>
						</p>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
		get_footer();
	}

	/**
	 * Renderizar página de preferências
	 */
	private function render_preferences_page() {
		$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		// Verificar token
		if ( ! $this->verify_token( $email, $token ) ) {
			wp_die( esc_html__( 'Link inválido ou expirado', 'person-cash-wallet' ) );
		}

		// Buscar listas do usuário
		$user_lists = $this->get_user_lists( $email );

		// Processar alterações se foi enviado
		$message = '';
		if ( isset( $_POST['pcw_preferences_submit'] ) ) {
			if ( ! isset( $_POST['pcw_preferences_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pcw_preferences_nonce'] ) ), 'pcw_preferences' ) ) {
				wp_die( esc_html__( 'Ação não autorizada', 'person-cash-wallet' ) );
			}

			$selected_lists = isset( $_POST['lists'] ) ? array_map( 'absint', wp_unslash( $_POST['lists'] ) ) : array();
			$this->update_preferences( $email, $selected_lists, $user_lists );

			$message = '<div class="pcw-message pcw-message--success">' . esc_html__( 'Preferências atualizadas com sucesso!', 'person-cash-wallet' ) . '</div>';
		}

		get_header();
		?>
		<div class="pcw-preferences-page">
			<div class="pcw-preferences-container">
				<h1><?php esc_html_e( 'Preferências de Email', 'person-cash-wallet' ); ?></h1>

				<?php echo wp_kses_post( $message ); ?>

				<p><?php esc_html_e( 'Escolha quais emails você deseja receber:', 'person-cash-wallet' ); ?></p>

				<form method="post" class="pcw-preferences-form">
					<?php wp_nonce_field( 'pcw_preferences', 'pcw_preferences_nonce' ); ?>

					<?php foreach ( $user_lists as $list ) : ?>
						<div class="pcw-form-field">
							<label>
								<input 
									type="checkbox" 
									name="lists[]" 
									value="<?php echo esc_attr( $list->id ); ?>"
									<?php checked( true, $list->is_subscribed ); ?>
								/>
								<strong><?php echo esc_html( $list->name ); ?></strong>
								<?php if ( ! empty( $list->description ) ) : ?>
									<br />
									<small><?php echo esc_html( $list->description ); ?></small>
								<?php endif; ?>
							</label>
						</div>
					<?php endforeach; ?>

					<button type="submit" name="pcw_preferences_submit" class="pcw-button">
						<?php esc_html_e( 'Salvar Preferências', 'person-cash-wallet' ); ?>
					</button>
				</form>
			</div>
		</div>
		<?php
		get_footer();
	}

	/**
	 * Processar descadastramento
	 *
	 * @param string $email Email.
	 * @param int    $list_id ID da lista (null para todas).
	 * @param bool   $unsubscribe_all Descadastrar de tudo.
	 * @param string $reason Motivo.
	 * @param string $feedback Feedback.
	 * @return bool
	 */
	public function process_unsubscribe( $email, $list_id = null, $unsubscribe_all = false, $reason = '', $feedback = '' ) {
		global $wpdb;

		$user = get_user_by( 'email', $email );
		$user_id = $user ? $user->ID : null;

		// Salvar registro de unsubscribe
		$unsubscribes_table = $wpdb->prefix . 'pcw_unsubscribes';
		$wpdb->insert(
			$unsubscribes_table,
			array(
				'email'           => $email,
				'user_id'         => $user_id,
				'list_id'         => $list_id,
				'reason'          => $reason,
				'feedback'        => $feedback,
				'unsubscribe_all' => $unsubscribe_all ? 1 : 0,
				'ip_address'      => $this->get_client_ip(),
				'user_agent'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : null,
				'source'          => 'manual',
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		// Remover das listas
		$members_table = $wpdb->prefix . 'pcw_list_members';

		if ( $unsubscribe_all ) {
			// Remover de todas as listas
			$wpdb->delete(
				$members_table,
				array( 'email' => $email ),
				array( '%s' )
			);
		} elseif ( $list_id ) {
			// Remover de uma lista específica
			$wpdb->delete(
				$members_table,
				array(
					'email'   => $email,
					'list_id' => $list_id,
				),
				array( '%s', '%d' )
			);
		}

		do_action( 'pcw_user_unsubscribed', $email, $list_id, $unsubscribe_all );

		return true;
	}

	/**
	 * Obter listas do usuário
	 *
	 * @param string $email Email.
	 * @return array
	 */
	private function get_user_lists( $email ) {
		global $wpdb;

		$lists_table = $wpdb->prefix . 'pcw_custom_lists';
		$members_table = $wpdb->prefix . 'pcw_list_members';

		$sql = "
			SELECT 
				l.*,
				CASE WHEN m.id IS NOT NULL THEN 1 ELSE 0 END as is_subscribed
			FROM {$lists_table} l
			LEFT JOIN {$members_table} m ON l.id = m.list_id AND m.email = %s
			ORDER BY l.name ASC
		";

		return $wpdb->get_results( $wpdb->prepare( $sql, $email ) );
	}

	/**
	 * Atualizar preferências
	 *
	 * @param string $email Email.
	 * @param array  $selected_lists Listas selecionadas.
	 * @param array  $all_lists Todas as listas.
	 */
	private function update_preferences( $email, $selected_lists, $all_lists ) {
		foreach ( $all_lists as $list ) {
			$should_be_subscribed = in_array( $list->id, $selected_lists, true );
			$is_subscribed = (bool) $list->is_subscribed;

			if ( $should_be_subscribed && ! $is_subscribed ) {
				// Adicionar à lista
				PCW_Custom_Lists::add_members( $list->id, array(
					array( 'email' => $email ),
				) );
			} elseif ( ! $should_be_subscribed && $is_subscribed ) {
				// Remover da lista
				$this->process_unsubscribe( $email, $list->id, false, 'user_preference', '' );
			}
		}
	}

	/**
	 * Verificar token
	 *
	 * @param string $email Email.
	 * @param string $token Token.
	 * @return bool
	 */
	private function verify_token( $email, $token ) {
		$expected_token = $this->generate_token( $email );
		return hash_equals( $expected_token, $token );
	}

	/**
	 * Gerar token
	 *
	 * @param string $email Email.
	 * @return string
	 */
	public function generate_token( $email ) {
		return hash( 'sha256', $email . wp_salt( 'nonce' ) );
	}

	/**
	 * Adicionar link de unsubscribe nos emails
	 *
	 * @param string $content Conteúdo do email.
	 * @param string $email Email do destinatário.
	 * @return string
	 */
	public function add_unsubscribe_link( $content, $email ) {
		$token = $this->generate_token( $email );
		$unsubscribe_url = home_url( '/unsubscribe/?email=' . urlencode( $email ) . '&token=' . urlencode( $token ) );
		$preferences_url = home_url( '/email-preferences/?email=' . urlencode( $email ) . '&token=' . urlencode( $token ) );

		$footer = '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280; text-align: center;">';
		$footer .= '<p>' . sprintf(
			/* translators: %1$s: link de preferências, %2$s: link de descadastrar */
			__( 'Você pode %1$s ou %2$s a qualquer momento.', 'person-cash-wallet' ),
			'<a href="' . esc_url( $preferences_url ) . '">' . __( 'gerenciar suas preferências', 'person-cash-wallet' ) . '</a>',
			'<a href="' . esc_url( $unsubscribe_url ) . '">' . __( 'descadastrar', 'person-cash-wallet' ) . '</a>'
		) . '</p>';
		$footer .= '</div>';

		return $content . $footer;
	}

	/**
	 * Handler AJAX para descadastramento
	 */
	public function handle_ajax_unsubscribe() {
		// Verificar nonce (se enviado)
		if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pcw_unsubscribe' ) ) {
			wp_send_json_error( array( 'message' => __( 'Ação não autorizada', 'person-cash-wallet' ) ) );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$list_id = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : null;

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Email inválido', 'person-cash-wallet' ) ) );
		}

		$this->process_unsubscribe( $email, $list_id );

		wp_send_json_success( array( 'message' => __( 'Descadastrado com sucesso', 'person-cash-wallet' ) ) );
	}

	/**
	 * Obter IP do cliente
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( isset( $_SERVER[ $key ] ) && filter_var( wp_unslash( $_SERVER[ $key ] ), FILTER_VALIDATE_IP ) ) {
				return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			}
		}

		return '';
	}
}
