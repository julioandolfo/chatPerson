<?php
/**
 * Classe para página pública de indicação
 *
 * @package PersonCashWallet
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de página pública de indicação
 */
class PCW_Referral_Public {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Referral_Public
	 */
	private static $instance = null;

	/**
	 * Obter instância singleton
	 *
	 * @return PCW_Referral_Public
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
		$settings = PCW_Referral_Rewards::instance()->get_settings();

		if ( 'yes' !== $settings['enabled'] ) {
			return;
		}

		// Registrar rewrite rules
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );

		// Template redirect
		add_action( 'template_redirect', array( $this, 'handle_page' ) );

		// AJAX para adicionar indicação (sem login)
		add_action( 'wp_ajax_nopriv_pcw_public_add_referral', array( $this, 'ajax_add_referral' ) );
		add_action( 'wp_ajax_pcw_public_add_referral', array( $this, 'ajax_add_referral' ) );
	}

	/**
	 * Registrar rewrite rules
	 */
	public function register_rewrite_rules() {
		add_rewrite_rule(
			'^indicar/([^/]+)/?$',
			'index.php?pcw_referral_page=1&pcw_referral_token=$matches[1]',
			'top'
		);

		add_rewrite_tag( '%pcw_referral_page%', '([^&]+)' );
		add_rewrite_tag( '%pcw_referral_token%', '([^&]+)' );
	}

	/**
	 * Processar página
	 */
	public function handle_page() {
		if ( ! get_query_var( 'pcw_referral_page' ) ) {
			return;
		}

		$token = get_query_var( 'pcw_referral_token' );

		if ( empty( $token ) ) {
			wp_safe_redirect( home_url() );
			exit;
		}

		// Decodificar token
		$token_data = PCW_Referral_Emails::instance()->decode_email_token( $token );

		if ( ! $token_data ) {
			// Token inválido - pode ser um código direto
			$this->render_invalid_page();
			exit;
		}

		$user_id = $token_data['user_id'];
		$order_id = $token_data['order_id'];

		// Verificar se usuário existe
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			$this->render_invalid_page();
			exit;
		}

		// Obter ou criar código
		$code_data = PCW_Referral_Codes::instance()->get_or_create_code( $user_id );

		if ( ! $code_data ) {
			$this->render_invalid_page();
			exit;
		}

		// Marcar email como clicado
		$this->mark_email_clicked( $token );

		// Renderizar página
		$this->render_page( $user, $code_data, $token );
		exit;
	}

	/**
	 * Marcar email como clicado
	 *
	 * @param string $token Token do email.
	 */
	private function mark_email_clicked( $token ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_referral_emails';

		$wpdb->update(
			$table,
			array(
				'clicked_at' => current_time( 'mysql' ),
				'status'     => 'clicked',
			),
			array( 'token' => $token ),
			array( '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Renderizar página de indicação
	 *
	 * @param WP_User $user Usuário.
	 * @param object  $code_data Dados do código.
	 * @param string  $token Token.
	 */
	private function render_page( $user, $code_data, $token ) {
		// Obter nome parcial para privacidade
		$first_name = $user->first_name ?: $user->display_name;
		$display_name = $first_name;
		if ( strlen( $first_name ) > 2 ) {
			$display_name = substr( $first_name, 0, 2 ) . str_repeat( '*', strlen( $first_name ) - 2 );
		}

		// Links de compartilhamento
		$tracking = PCW_Referral_Tracking::instance();
		$share_links = $tracking->get_share_links( $code_data->code );
		$referral_link = PCW_Referral_Codes::instance()->get_referral_link( $user->ID );
		$qr_code_url = $tracking->get_qr_code_url( $code_data->code, 200 );

		// Configurações de recompensa
		$reward_settings = PCW_Referral_Rewards::instance()->get_settings();
		$reward_text = 'percentage' === $reward_settings['reward_type']
			? $reward_settings['reward_amount'] . '%'
			: PCW_Formatters::format_money( $reward_settings['reward_amount'] );

		$site_name = get_bloginfo( 'name' );

		// Output
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php printf( esc_html__( 'Indique Amigos - %s', 'person-cash-wallet' ), esc_html( $site_name ) ); ?></title>
			<?php wp_head(); ?>
			<style>
				* {
					margin: 0;
					padding: 0;
					box-sizing: border-box;
				}

				body {
					font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
					background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
					min-height: 100vh;
					padding: 20px;
				}

				.pcw-public-container {
					max-width: 600px;
					margin: 0 auto;
				}

				.pcw-public-header {
					text-align: center;
					color: white;
					margin-bottom: 30px;
				}

				.pcw-public-logo {
					font-size: 48px;
					margin-bottom: 15px;
				}

				.pcw-public-title {
					font-size: 28px;
					font-weight: 700;
					margin-bottom: 10px;
				}

				.pcw-public-subtitle {
					font-size: 16px;
					opacity: 0.9;
				}

				.pcw-public-card {
					background: white;
					border-radius: 16px;
					padding: 30px;
					margin-bottom: 20px;
					box-shadow: 0 10px 40px rgba(0,0,0,0.1);
				}

				.pcw-public-section-title {
					font-size: 18px;
					font-weight: 600;
					margin-bottom: 15px;
					color: #333;
				}

				/* Código e Link */
				.pcw-code-box {
					background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
					color: white;
					padding: 25px;
					border-radius: 12px;
					text-align: center;
					margin-bottom: 25px;
				}

				.pcw-code-label {
					font-size: 12px;
					text-transform: uppercase;
					letter-spacing: 1px;
					opacity: 0.8;
					margin-bottom: 10px;
				}

				.pcw-code-value {
					font-size: 32px;
					font-weight: 700;
					letter-spacing: 4px;
					margin-bottom: 20px;
				}

				.pcw-link-box {
					background: rgba(255,255,255,0.2);
					padding: 12px 15px;
					border-radius: 8px;
					display: flex;
					gap: 10px;
					align-items: center;
				}

				.pcw-link-input {
					flex: 1;
					background: transparent;
					border: none;
					color: white;
					font-size: 13px;
					outline: none;
				}

				.pcw-copy-btn {
					background: white;
					color: #667eea;
					border: none;
					padding: 8px 16px;
					border-radius: 6px;
					font-weight: 600;
					cursor: pointer;
					transition: transform 0.2s;
				}

				.pcw-copy-btn:hover {
					transform: scale(1.05);
				}

				.pcw-reward-banner {
					background: #d1fae5;
					color: #065f46;
					padding: 15px;
					border-radius: 8px;
					text-align: center;
					font-weight: 500;
					margin-top: 20px;
				}

				/* Compartilhamento */
				.pcw-share-buttons {
					display: flex;
					flex-wrap: wrap;
					gap: 10px;
					justify-content: center;
					margin-bottom: 20px;
				}

				.pcw-share-btn {
					display: inline-flex;
					align-items: center;
					gap: 8px;
					padding: 12px 24px;
					border-radius: 8px;
					color: white;
					text-decoration: none;
					font-size: 14px;
					font-weight: 500;
					transition: transform 0.2s, opacity 0.2s;
				}

				.pcw-share-btn:hover {
					transform: translateY(-2px);
					opacity: 0.9;
					color: white;
				}

				.pcw-qr-section {
					text-align: center;
					padding-top: 20px;
					border-top: 1px solid #e5e7eb;
				}

				.pcw-qr-code img {
					max-width: 150px;
					border-radius: 10px;
					margin: 15px 0;
				}

				.pcw-qr-text {
					font-size: 13px;
					color: #666;
				}

				/* Formulário */
				.pcw-form-intro {
					margin-bottom: 20px;
					color: #666;
					font-size: 14px;
				}

				.pcw-form-row {
					margin-bottom: 15px;
				}

				.pcw-form-row-2 {
					display: grid;
					grid-template-columns: 1fr 1fr;
					gap: 15px;
				}

				@media (max-width: 500px) {
					.pcw-form-row-2 {
						grid-template-columns: 1fr;
					}
				}

				.pcw-form-field label {
					display: block;
					font-size: 14px;
					font-weight: 500;
					margin-bottom: 6px;
					color: #333;
				}

				.pcw-form-field input,
				.pcw-form-field textarea {
					width: 100%;
					padding: 12px 15px;
					border: 2px solid #e5e7eb;
					border-radius: 8px;
					font-size: 15px;
					transition: border-color 0.2s;
				}

				.pcw-form-field input:focus,
				.pcw-form-field textarea:focus {
					border-color: #667eea;
					outline: none;
				}

				.pcw-form-field .required {
					color: #dc2626;
				}

				.pcw-submit-btn {
					width: 100%;
					background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
					color: white;
					border: none;
					padding: 15px;
					border-radius: 8px;
					font-size: 16px;
					font-weight: 600;
					cursor: pointer;
					transition: transform 0.2s, box-shadow 0.2s;
				}

				.pcw-submit-btn:hover {
					transform: translateY(-2px);
					box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
				}

				.pcw-submit-btn:disabled {
					opacity: 0.7;
					cursor: not-allowed;
					transform: none;
				}

				.pcw-form-message {
					margin-top: 15px;
					padding: 15px;
					border-radius: 8px;
					text-align: center;
					display: none;
				}

				.pcw-form-message.success {
					background: #d1fae5;
					color: #065f46;
					display: block;
				}

				.pcw-form-message.error {
					background: #fee2e2;
					color: #991b1b;
					display: block;
				}

				/* Referências já adicionadas */
				.pcw-added-referrals {
					margin-top: 25px;
					padding-top: 25px;
					border-top: 1px solid #e5e7eb;
				}

				.pcw-added-title {
					font-size: 14px;
					font-weight: 600;
					color: #666;
					margin-bottom: 10px;
				}

				.pcw-added-list {
					list-style: none;
				}

				.pcw-added-item {
					display: flex;
					align-items: center;
					gap: 10px;
					padding: 10px 0;
					border-bottom: 1px solid #f3f4f6;
				}

				.pcw-added-item:last-child {
					border-bottom: none;
				}

				.pcw-added-icon {
					color: #10b981;
					font-size: 18px;
				}

				.pcw-added-name {
					flex: 1;
					font-weight: 500;
				}

				.pcw-added-status {
					font-size: 12px;
					color: #9ca3af;
				}

				/* Footer */
				.pcw-public-footer {
					text-align: center;
					color: rgba(255,255,255,0.7);
					font-size: 13px;
					margin-top: 20px;
				}

				.pcw-public-footer a {
					color: white;
					text-decoration: none;
				}
			</style>
		</head>
		<body>
			<div class="pcw-public-container">
				<div class="pcw-public-header">
					<div class="pcw-public-logo">🎁</div>
					<h1 class="pcw-public-title"><?php esc_html_e( 'Indique Amigos', 'person-cash-wallet' ); ?></h1>
					<p class="pcw-public-subtitle"><?php echo esc_html( $site_name ); ?></p>
				</div>

				<!-- Código e Link -->
				<div class="pcw-public-card">
					<div class="pcw-code-box">
						<div class="pcw-code-label"><?php esc_html_e( 'Seu Código de Indicação', 'person-cash-wallet' ); ?></div>
						<div class="pcw-code-value" id="pcw-code"><?php echo esc_html( $code_data->code ); ?></div>
						
						<div class="pcw-link-box">
							<input type="text" readonly value="<?php echo esc_url( $referral_link ); ?>" id="pcw-link" class="pcw-link-input" />
							<button type="button" class="pcw-copy-btn" onclick="pcwCopyLink()">
								<?php esc_html_e( 'Copiar', 'person-cash-wallet' ); ?>
							</button>
						</div>

						<div class="pcw-reward-banner">
							<?php printf(
								esc_html__( '🎉 Você ganha %s para cada amigo que comprar!', 'person-cash-wallet' ),
								'<strong>' . esc_html( $reward_text ) . '</strong>'
							); ?>
						</div>
					</div>
				</div>

				<!-- Compartilhar -->
				<div class="pcw-public-card">
					<h2 class="pcw-public-section-title"><?php esc_html_e( 'Compartilhe com seus amigos', 'person-cash-wallet' ); ?></h2>
					
					<div class="pcw-share-buttons">
						<?php foreach ( $share_links as $key => $link ) : ?>
							<?php if ( 'copy' === $key ) continue; ?>
							<a href="<?php echo esc_url( $link['url'] ); ?>" 
							   class="pcw-share-btn" 
							   target="_blank" 
							   rel="noopener noreferrer"
							   style="background-color: <?php echo esc_attr( $link['color'] ); ?>">
								<?php echo esc_html( $link['name'] ); ?>
							</a>
						<?php endforeach; ?>
					</div>

					<div class="pcw-qr-section">
						<p class="pcw-qr-text"><?php esc_html_e( 'Ou escaneie o QR Code:', 'person-cash-wallet' ); ?></p>
						<div class="pcw-qr-code">
							<img src="<?php echo esc_url( $qr_code_url ); ?>" alt="QR Code" />
						</div>
					</div>
				</div>

				<!-- Formulário de Indicação -->
				<div class="pcw-public-card">
					<h2 class="pcw-public-section-title"><?php esc_html_e( 'Cadastrar Indicação', 'person-cash-wallet' ); ?></h2>
					<p class="pcw-form-intro"><?php esc_html_e( 'Preencha os dados de quem você quer indicar:', 'person-cash-wallet' ); ?></p>

					<form id="pcw-public-referral-form">
						<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>" />
						<input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>" />
						<?php wp_nonce_field( 'pcw_public_referral', 'pcw_public_nonce' ); ?>

						<div class="pcw-form-row">
							<div class="pcw-form-field">
								<label for="referred_name"><?php esc_html_e( 'Nome Completo', 'person-cash-wallet' ); ?> <span class="required">*</span></label>
								<input type="text" name="referred_name" id="referred_name" required />
							</div>
						</div>

						<div class="pcw-form-row pcw-form-row-2">
							<div class="pcw-form-field">
								<label for="referred_email"><?php esc_html_e( 'Email', 'person-cash-wallet' ); ?> <span class="required">*</span></label>
								<input type="email" name="referred_email" id="referred_email" required />
							</div>
							<div class="pcw-form-field">
								<label for="referred_phone"><?php esc_html_e( 'Telefone', 'person-cash-wallet' ); ?> <span class="required">*</span></label>
								<input type="tel" name="referred_phone" id="referred_phone" required placeholder="(00) 00000-0000" />
							</div>
						</div>

						<button type="submit" class="pcw-submit-btn" id="pcw-submit-btn">
							<?php esc_html_e( 'Adicionar Indicação', 'person-cash-wallet' ); ?>
						</button>

						<div class="pcw-form-message" id="pcw-form-message"></div>
					</form>

					<!-- Lista de indicações já adicionadas nesta sessão -->
					<div class="pcw-added-referrals" id="pcw-added-referrals" style="display: none;">
						<div class="pcw-added-title"><?php esc_html_e( 'Indicações adicionadas:', 'person-cash-wallet' ); ?></div>
						<ul class="pcw-added-list" id="pcw-added-list"></ul>
					</div>
				</div>

				<div class="pcw-public-footer">
					<a href="<?php echo esc_url( home_url() ); ?>"><?php echo esc_html( $site_name ); ?></a>
				</div>
			</div>

			<script>
				function pcwCopyLink() {
					var link = document.getElementById('pcw-link');
					link.select();
					document.execCommand('copy');
					
					var btn = document.querySelector('.pcw-copy-btn');
					var originalText = btn.textContent;
					btn.textContent = '<?php echo esc_js( __( 'Copiado!', 'person-cash-wallet' ) ); ?>';
					setTimeout(function() {
						btn.textContent = originalText;
					}, 2000);
				}

				document.getElementById('pcw-public-referral-form').addEventListener('submit', function(e) {
					e.preventDefault();
					
					var btn = document.getElementById('pcw-submit-btn');
					var messageEl = document.getElementById('pcw-form-message');
					var form = this;
					
					btn.disabled = true;
					btn.textContent = '<?php echo esc_js( __( 'Enviando...', 'person-cash-wallet' ) ); ?>';
					messageEl.style.display = 'none';
					messageEl.className = 'pcw-form-message';
					
					var formData = new FormData(form);
					formData.append('action', 'pcw_public_add_referral');
					
					fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
						method: 'POST',
						body: formData
					})
					.then(function(response) { return response.json(); })
					.then(function(data) {
						btn.disabled = false;
						btn.textContent = '<?php echo esc_js( __( 'Adicionar Indicação', 'person-cash-wallet' ) ); ?>';
						
						if (data.success) {
							messageEl.textContent = data.data.message;
							messageEl.className = 'pcw-form-message success';
							messageEl.style.display = 'block';
							
							// Limpar formulário
							form.reset();
							
							// Adicionar à lista
							var addedList = document.getElementById('pcw-added-list');
							var addedSection = document.getElementById('pcw-added-referrals');
							
							var li = document.createElement('li');
							li.className = 'pcw-added-item';
							li.innerHTML = '<span class="pcw-added-icon">✓</span>' +
								'<span class="pcw-added-name">' + data.data.name + '</span>' +
								'<span class="pcw-added-status"><?php echo esc_js( __( 'Adicionado agora', 'person-cash-wallet' ) ); ?></span>';
							addedList.appendChild(li);
							addedSection.style.display = 'block';
							
						} else {
							messageEl.textContent = data.data.message;
							messageEl.className = 'pcw-form-message error';
							messageEl.style.display = 'block';
						}
					})
					.catch(function(error) {
						btn.disabled = false;
						btn.textContent = '<?php echo esc_js( __( 'Adicionar Indicação', 'person-cash-wallet' ) ); ?>';
						messageEl.textContent = '<?php echo esc_js( __( 'Erro ao enviar. Tente novamente.', 'person-cash-wallet' ) ); ?>';
						messageEl.className = 'pcw-form-message error';
						messageEl.style.display = 'block';
					});
				});
			</script>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
	}

	/**
	 * Renderizar página inválida
	 */
	private function render_invalid_page() {
		$site_name = get_bloginfo( 'name' );
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php esc_html_e( 'Link Inválido', 'person-cash-wallet' ); ?></title>
			<style>
				body {
					font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
					background: #f3f4f6;
					display: flex;
					align-items: center;
					justify-content: center;
					min-height: 100vh;
					margin: 0;
					padding: 20px;
				}
				.error-box {
					background: white;
					padding: 40px;
					border-radius: 16px;
					text-align: center;
					max-width: 400px;
					box-shadow: 0 10px 40px rgba(0,0,0,0.1);
				}
				.error-icon {
					font-size: 64px;
					margin-bottom: 20px;
				}
				.error-title {
					font-size: 24px;
					font-weight: 700;
					margin-bottom: 10px;
					color: #333;
				}
				.error-text {
					color: #666;
					margin-bottom: 25px;
				}
				.error-btn {
					display: inline-block;
					background: #667eea;
					color: white;
					padding: 12px 30px;
					border-radius: 8px;
					text-decoration: none;
					font-weight: 600;
				}
			</style>
		</head>
		<body>
			<div class="error-box">
				<div class="error-icon">😕</div>
				<h1 class="error-title"><?php esc_html_e( 'Link Inválido', 'person-cash-wallet' ); ?></h1>
				<p class="error-text"><?php esc_html_e( 'Este link de indicação é inválido ou expirou.', 'person-cash-wallet' ); ?></p>
				<a href="<?php echo esc_url( home_url() ); ?>" class="error-btn"><?php esc_html_e( 'Ir para a loja', 'person-cash-wallet' ); ?></a>
			</div>
		</body>
		</html>
		<?php
	}

	/**
	 * AJAX para adicionar indicação
	 */
	public function ajax_add_referral() {
		check_ajax_referer( 'pcw_public_referral', 'pcw_public_nonce' );

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! $user_id ) {
			wp_send_json_error( array(
				'message' => __( 'Dados inválidos.', 'person-cash-wallet' ),
			) );
		}

		// Verificar se usuário existe
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			wp_send_json_error( array(
				'message' => __( 'Usuário inválido.', 'person-cash-wallet' ),
			) );
		}

		$data = array(
			'referrer_user_id' => $user_id,
			'referred_name'    => isset( $_POST['referred_name'] ) ? sanitize_text_field( wp_unslash( $_POST['referred_name'] ) ) : '',
			'referred_email'   => isset( $_POST['referred_email'] ) ? sanitize_email( wp_unslash( $_POST['referred_email'] ) ) : '',
			'referred_phone'   => isset( $_POST['referred_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['referred_phone'] ) ) : '',
			'source'           => 'email_link',
			'ip_address'       => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
		);

		$result = PCW_Referrals::instance()->create_referral( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
			) );
		}

		// Atualizar contador de referrals do email
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		if ( $token ) {
			global $wpdb;
			$table = $wpdb->prefix . 'pcw_referral_emails';

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET referrals_from_email = referrals_from_email + 1 WHERE token = %s",
					$token
				)
			);
		}

		wp_send_json_success( array(
			'message'     => __( 'Indicação adicionada com sucesso!', 'person-cash-wallet' ),
			'referral_id' => $result,
			'name'        => sanitize_text_field( $data['referred_name'] ),
		) );
	}
}
