<?php
/**
 * Classe admin para configurações de notificações
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe admin notificações
 */
class PCW_Admin_Notifications {

	/**
	 * Inicializar
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_pcw_save_notification_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'wp_ajax_pcw_preview_email', array( $this, 'ajax_preview_email' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts para a página de notificações
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'growly-digital_page_pcw-notifications' !== $hook && 'person-cash-wallet_page_pcw-notifications' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'pcw-admin-notifications',
			PCW_PLUGIN_URL . 'assets/js/admin-notifications.js',
			array( 'jquery' ),
			PCW_VERSION,
			true
		);

		wp_localize_script( 'pcw-admin-notifications', 'pcwNotifications', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pcw_preview_email' ),
		) );
	}

	/**
	 * AJAX: Preview de email
	 */
	public function ajax_preview_email() {
		check_ajax_referer( 'pcw_preview_email', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'person-cash-wallet' ) ) );
		}

		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( $_POST['subject'] ) : '';
		$body = isset( $_POST['body'] ) ? wp_kses_post( $_POST['body'] ) : '';

		// Dados de exemplo para substituição
		$sample_data = array(
			'{customer_name}'   => 'João Silva',
			'{amount}'          => 'R$ 25,00',
			'{cashback_amount}' => 'R$ 25,00',
			'{order_id}'        => '12345',
			'{order_date}'      => date_i18n( get_option( 'date_format' ) ),
			'{expiration_date}' => date_i18n( get_option( 'date_format' ), strtotime( '+30 days' ) ),
			'{days}'            => '7',
			'{site_name}'       => get_bloginfo( 'name' ),
			'{shop_url}'        => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url(),
			'{level_name}'      => 'Gold',
			'{level_number}'    => '3',
			'{balance}'         => 'R$ 150,00',
			'{current_balance}' => 'R$ 150,00',
			'{benefits}'        => 'Descontos exclusivos, Frete grátis, Cashback dobrado',
		);

		// Substituir variáveis
		$subject = str_replace( array_keys( $sample_data ), array_values( $sample_data ), $subject );
		$body = str_replace( array_keys( $sample_data ), array_values( $sample_data ), $body );

		// Gerar email completo com wrapper HTML
		$full_email = PCW_Email_Handler::generate_preview( $body, $subject );

		wp_send_json_success( array(
			'subject' => $subject,
			'body'    => $full_email,
		) );
	}

	/**
	 * Adicionar menu
	 */
	public function add_menu() {
		add_submenu_page(
			'pcw-dashboard',
			__( 'Notificações', 'person-cash-wallet' ),
			__( 'Notificações', 'person-cash-wallet' ),
			'manage_woocommerce',
			'pcw-notifications',
			array( $this, 'render_page' ),
			50
		);
	}

	/**
	 * Renderizar página
	 */
	public function render_page() {
		$defaults = $this->get_default_settings();
		$saved    = get_option( 'pcw_notification_settings', array() );
		$settings = $this->merge_settings( $saved, $defaults );

		?>
		<div class="wrap">
			<!-- Page Header -->
			<div class="pcw-page-header">
				<div>
					<h1>
						<span class="dashicons dashicons-email-alt"></span>
						<?php esc_html_e( 'Configurações de Notificações', 'person-cash-wallet' ); ?>
					</h1>
					<p class="description"><?php esc_html_e( 'Configure quando e como os emails automáticos serão enviados aos clientes', 'person-cash-wallet' ); ?></p>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'pcw_save_notification_settings', 'pcw_nonce' ); ?>
				<input type="hidden" name="action" value="pcw_save_notification_settings">

				<!-- Notificações de Cashback -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-chart-line"></span>
							<?php esc_html_e( 'Notificações de Cashback', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<div style="padding: 16px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px; margin-bottom: 20px;">
							<strong><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'Dica:', 'person-cash-wallet' ); ?></strong>
							<?php esc_html_e( 'Configure emails automáticos para manter seus clientes informados sobre cashback ganho, uso e expiração.', 'person-cash-wallet' ); ?>
						</div>

						<!-- Cashback Ganho -->
						<div style="border: 1px solid #dcdcde; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
							<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
								<label class="switch">
									<input type="checkbox" name="cashback_earned_enabled" value="yes" <?php checked( $settings['cashback_earned']['enabled'], 'yes' ); ?>>
									<span class="slider"></span>
								</label>
								<h3 style="margin: 0;">💰 <?php esc_html_e( 'Cashback Ganho', 'person-cash-wallet' ); ?></h3>
							</div>
							
							<table class="form-table">
								<tr>
									<th style="width: 200px;"><label for="cashback_earned_when"><?php esc_html_e( 'Quando Enviar', 'person-cash-wallet' ); ?></label></th>
									<td>
										<select id="cashback_earned_when" name="cashback_earned_when" class="pcw-form-input" style="width: 100%; max-width: 400px;">
											<option value="immediately" <?php selected( $settings['cashback_earned']['when'], 'immediately' ); ?>>⚡ <?php esc_html_e( 'Imediatamente após ganhar', 'person-cash-wallet' ); ?></option>
											<option value="1_hour" <?php selected( $settings['cashback_earned']['when'], '1_hour' ); ?>>🕐 <?php esc_html_e( '1 hora após ganhar', 'person-cash-wallet' ); ?></option>
											<option value="1_day" <?php selected( $settings['cashback_earned']['when'], '1_day' ); ?>>📅 <?php esc_html_e( '1 dia após ganhar', 'person-cash-wallet' ); ?></option>
											<option value="3_days" <?php selected( $settings['cashback_earned']['when'], '3_days' ); ?>>📅 <?php esc_html_e( '3 dias após ganhar', 'person-cash-wallet' ); ?></option>
											<option value="7_days" <?php selected( $settings['cashback_earned']['when'], '7_days' ); ?>>📅 <?php esc_html_e( '7 dias após ganhar', 'person-cash-wallet' ); ?></option>
										</select>
										<p class="description">
											<span class="dashicons dashicons-clock"></span>
											<?php esc_html_e( 'Quando o cliente receberá o email informando sobre o cashback ganho', 'person-cash-wallet' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th><label for="cashback_earned_subject"><?php esc_html_e( 'Assunto do Email', 'person-cash-wallet' ); ?></label></th>
									<td>
										<input type="text" id="cashback_earned_subject" name="cashback_earned_subject" value="<?php echo esc_attr( $settings['cashback_earned']['subject'] ); ?>" class="pcw-form-input" style="width: 100%;" placeholder="<?php esc_attr_e( 'Você ganhou {amount} de cashback!', 'person-cash-wallet' ); ?>">
										<p class="description">
											<span class="dashicons dashicons-info"></span>
											<?php esc_html_e( 'Variáveis disponíveis:', 'person-cash-wallet' ); ?> 
											<code>{amount}</code>, <code>{customer_name}</code>, <code>{order_id}</code>, <code>{site_name}</code>, <code>{shop_url}</code>
										</p>
									</td>
								</tr>
								<tr>
									<th><label for="cashback_earned_body"><?php esc_html_e( 'Corpo do Email', 'person-cash-wallet' ); ?></label></th>
									<td>
										<?php
										$body_content = isset( $settings['cashback_earned']['body'] ) ? $settings['cashback_earned']['body'] : $this->get_default_settings()['cashback_earned']['body'];
										wp_editor(
											$body_content,
											'cashback_earned_body',
											array(
												'textarea_name' => 'cashback_earned_body',
												'textarea_rows' => 10,
												'media_buttons' => false,
												'teeny'         => true,
												'quicktags'     => true,
											)
										);
										?>
										<p class="description">
											<span class="dashicons dashicons-info"></span>
											<?php esc_html_e( 'Use HTML para formatar o email. Variáveis:', 'person-cash-wallet' ); ?> 
											<code>{amount}</code>, <code>{customer_name}</code>, <code>{order_id}</code>, <code>{site_name}</code>, <code>{shop_url}</code>
										</p>
										<button type="button" class="button pcw-preview-email" data-type="cashback_earned" data-subject-field="cashback_earned_subject" data-body-editor="cashback_earned_body" style="margin-top: 10px;">
											<span class="dashicons dashicons-visibility"></span>
											<?php esc_html_e( 'Visualizar Email', 'person-cash-wallet' ); ?>
										</button>
									</td>
								</tr>
							</table>
						</div>

						<!-- Cashback Expirando -->
						<div style="border: 1px solid #dcdcde; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
							<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
								<label class="switch">
									<input type="checkbox" name="cashback_expiring_enabled" value="yes" <?php checked( $settings['cashback_expiring']['enabled'], 'yes' ); ?>>
									<span class="slider"></span>
								</label>
								<h3 style="margin: 0;">⚠️ <?php esc_html_e( 'Cashback Expirando - Múltiplos Lembretes', 'person-cash-wallet' ); ?></h3>
							</div>

							<div style="padding: 12px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px; margin-bottom: 20px;">
								<strong><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'Configure até 3 lembretes:', 'person-cash-wallet' ); ?></strong>
								<?php esc_html_e( 'Envie múltiplos avisos antes do cashback expirar. Ex: 30 dias, 15 dias e 7 dias antes.', 'person-cash-wallet' ); ?>
							</div>

							<?php
							$reminders = isset( $settings['cashback_expiring']['reminders'] ) ? $settings['cashback_expiring']['reminders'] : $this->get_default_settings()['cashback_expiring']['reminders'];
							$reminder_labels = array( '1º Lembrete', '2º Lembrete', '3º Lembrete' );
							$reminder_icons = array( '📅', '⚠️', '🚨' );
							
							foreach ( $reminders as $index => $reminder ) :
								$reminder_key = 'reminder_' . $index;
							?>
							<div style="border: 2px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px; background: #fafafa;">
								<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
									<label class="switch">
										<input type="checkbox" name="cashback_expiring_reminder_<?php echo $index; ?>_enabled" value="yes" <?php checked( $reminder['enabled'], 'yes' ); ?>>
										<span class="slider"></span>
									</label>
									<h4 style="margin: 0; font-size: 15px;">
										<?php echo esc_html( $reminder_icons[$index] . ' ' . $reminder_labels[$index] ); ?>
									</h4>
								</div>

								<table class="form-table">
									<tr>
										<th style="width: 200px;">
											<label for="cashback_expiring_reminder_<?php echo $index; ?>_days">
												<?php esc_html_e( 'Dias Antes de Expirar', 'person-cash-wallet' ); ?>
											</label>
										</th>
										<td>
											<input 
												type="number" 
												id="cashback_expiring_reminder_<?php echo $index; ?>_days" 
												name="cashback_expiring_reminder_<?php echo $index; ?>_days" 
												value="<?php echo esc_attr( $reminder['days_before'] ); ?>" 
												min="1" 
												max="90" 
												class="pcw-form-input" 
												style="width: 150px;"
											>
											<p class="description">
												<span class="dashicons dashicons-calendar-alt"></span>
												<?php esc_html_e( 'Enviar lembrete X dias antes de expirar', 'person-cash-wallet' ); ?>
											</p>
										</td>
									</tr>
									<tr>
										<th>
											<label for="cashback_expiring_reminder_<?php echo $index; ?>_subject">
												<?php esc_html_e( 'Assunto', 'person-cash-wallet' ); ?>
											</label>
										</th>
										<td>
											<input 
												type="text" 
												id="cashback_expiring_reminder_<?php echo $index; ?>_subject" 
												name="cashback_expiring_reminder_<?php echo $index; ?>_subject" 
												value="<?php echo esc_attr( $reminder['subject'] ); ?>" 
												class="pcw-form-input" 
												style="width: 100%;"
												placeholder="<?php esc_attr_e( 'Seu cashback de {amount} expira em {days} dias!', 'person-cash-wallet' ); ?>"
											>
											<p class="description">
												<span class="dashicons dashicons-info"></span>
												<?php esc_html_e( 'Variáveis:', 'person-cash-wallet' ); ?> 
												<code>{amount}</code>, <code>{customer_name}</code>, <code>{days}</code>, <code>{expiration_date}</code>, <code>{site_name}</code>, <code>{shop_url}</code>
											</p>
										</td>
									</tr>
									<tr>
										<th>
											<label for="cashback_expiring_reminder_<?php echo $index; ?>_body">
												<?php esc_html_e( 'Corpo do Email', 'person-cash-wallet' ); ?>
											</label>
										</th>
										<td>
											<?php
											wp_editor(
												$reminder['body'],
												'cashback_expiring_reminder_' . $index . '_body',
												array(
													'textarea_name' => 'cashback_expiring_reminder_' . $index . '_body',
													'textarea_rows' => 8,
													'media_buttons' => false,
													'teeny'         => true,
													'quicktags'     => true,
												)
											);
											?>
											<p class="description">
												<span class="dashicons dashicons-info"></span>
												<?php esc_html_e( 'Personalize a mensagem para este lembrete específico', 'person-cash-wallet' ); ?>
											</p>
										</td>
									</tr>
								</table>
							</div>
							<?php endforeach; ?>
						</div>

						<!-- Cashback Expirado -->
						<div style="border: 1px solid #dcdcde; border-radius: 8px; padding: 20px;">
							<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
								<label class="switch">
									<input type="checkbox" name="cashback_expired_enabled" value="yes" <?php checked( $settings['cashback_expired']['enabled'], 'yes' ); ?>>
									<span class="slider"></span>
								</label>
								<h3 style="margin: 0;">❌ <?php esc_html_e( 'Cashback Expirado', 'person-cash-wallet' ); ?></h3>
							</div>
							
							<table class="form-table">
								<tr>
									<th style="width: 200px;"><label for="cashback_expired_subject"><?php esc_html_e( 'Assunto do Email', 'person-cash-wallet' ); ?></label></th>
									<td>
										<input type="text" id="cashback_expired_subject" name="cashback_expired_subject" value="<?php echo esc_attr( $settings['cashback_expired']['subject'] ); ?>" class="pcw-form-input" style="width: 100%;" placeholder="<?php esc_attr_e( 'Seu cashback de {amount} expirou', 'person-cash-wallet' ); ?>">
										<p class="description">
											<span class="dashicons dashicons-info"></span>
											<?php esc_html_e( 'Variáveis:', 'person-cash-wallet' ); ?> 
											<code>{amount}</code>, <code>{customer_name}</code>, <code>{expiration_date}</code>
										</p>
									</td>
								</tr>
							</table>
						</div>
					</div>
				</div>

				<!-- Notificações de Cashback Retroativo -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Cashback Retroativo', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<div style="padding: 16px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px; margin-bottom: 20px;">
							<strong><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'Dica:', 'person-cash-wallet' ); ?></strong>
							<?php esc_html_e( 'Este email é enviado quando você processa cashback retroativo para pedidos antigos.', 'person-cash-wallet' ); ?>
						</div>

						<div style="border: 1px solid #dcdcde; border-radius: 8px; padding: 20px;">
							<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
								<label class="switch">
									<input type="checkbox" name="cashback_retroactive_enabled" value="yes" <?php checked( $settings['cashback_retroactive']['enabled'], 'yes' ); ?>>
									<span class="slider"></span>
								</label>
								<h3 style="margin: 0;">🎁 <?php esc_html_e( 'Cashback Retroativo Creditado', 'person-cash-wallet' ); ?></h3>
							</div>
							
							<table class="form-table">
								<tr>
									<th style="width: 200px;"><label for="cashback_retroactive_subject"><?php esc_html_e( 'Assunto do Email', 'person-cash-wallet' ); ?></label></th>
									<td>
										<input type="text" id="cashback_retroactive_subject" name="cashback_retroactive_subject" value="<?php echo esc_attr( $settings['cashback_retroactive']['subject'] ); ?>" class="pcw-form-input" style="width: 100%;" placeholder="<?php esc_attr_e( 'Você ganhou cashback retroativo!', 'person-cash-wallet' ); ?>">
										<p class="description">
											<span class="dashicons dashicons-info"></span>
											<?php esc_html_e( 'Variáveis:', 'person-cash-wallet' ); ?> 
											<code>{customer_name}</code>, <code>{cashback_amount}</code>, <code>{order_id}</code>, <code>{order_date}</code>, <code>{current_balance}</code>
										</p>
									</td>
								</tr>
								<tr>
									<th><label for="cashback_retroactive_body"><?php esc_html_e( 'Corpo do Email', 'person-cash-wallet' ); ?></label></th>
									<td>
										<?php
										wp_editor(
											$settings['cashback_retroactive']['body'],
											'cashback_retroactive_body',
											array(
												'textarea_name' => 'cashback_retroactive_body',
												'textarea_rows' => 10,
												'media_buttons' => false,
												'teeny'         => true,
											)
										);
										?>
										<p class="description">
											<span class="dashicons dashicons-info"></span>
											<?php esc_html_e( 'Personalize o conteúdo do email de cashback retroativo', 'person-cash-wallet' ); ?>
										</p>
									</td>
								</tr>
							</table>
						</div>
					</div>
				</div>

				<!-- Notificações de Níveis -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-star-filled"></span>
							<?php esc_html_e( 'Notificações de Níveis', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<!-- Nível Atualizado -->
						<div style="border: 1px solid #dcdcde; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
							<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
								<label class="switch">
									<input type="checkbox" name="level_updated_enabled" value="yes" <?php checked( $settings['level_updated']['enabled'], 'yes' ); ?>>
									<span class="slider"></span>
								</label>
								<h3 style="margin: 0;">⬆️ <?php esc_html_e( 'Nível Atualizado (Promoção)', 'person-cash-wallet' ); ?></h3>
							</div>
							
							<table class="form-table">
								<tr>
									<th style="width: 200px;"><label for="level_updated_subject"><?php esc_html_e( 'Assunto do Email', 'person-cash-wallet' ); ?></label></th>
									<td>
										<input type="text" id="level_updated_subject" name="level_updated_subject" value="<?php echo esc_attr( $settings['level_updated']['subject'] ); ?>" class="pcw-form-input" style="width: 100%;" placeholder="<?php esc_attr_e( '🎉 Parabéns! Você alcançou o nível {level_name}!', 'person-cash-wallet' ); ?>">
										<p class="description">
											<span class="dashicons dashicons-info"></span>
											<?php esc_html_e( 'Variáveis:', 'person-cash-wallet' ); ?> 
											<code>{customer_name}</code>, <code>{level_name}</code>, <code>{level_number}</code>, <code>{benefits}</code>
										</p>
									</td>
								</tr>
							</table>
						</div>

						<!-- Nível Expirando -->
						<div style="border: 1px solid #dcdcde; border-radius: 8px; padding: 20px;">
							<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
								<label class="switch">
									<input type="checkbox" name="level_expiring_enabled" value="yes" <?php checked( $settings['level_expiring']['enabled'], 'yes' ); ?>>
									<span class="slider"></span>
								</label>
								<h3 style="margin: 0;">⚠️ <?php esc_html_e( 'Nível Expirando', 'person-cash-wallet' ); ?></h3>
							</div>
							
							<table class="form-table">
								<tr>
									<th style="width: 200px;"><label for="level_expiring_days"><?php esc_html_e( 'Avisar Quantos Dias Antes', 'person-cash-wallet' ); ?></label></th>
									<td>
										<input type="number" id="level_expiring_days" name="level_expiring_days" value="<?php echo esc_attr( $settings['level_expiring']['days_before'] ); ?>" min="1" max="90" class="pcw-form-input" style="width: 150px;">
										<p class="description">
											<span class="dashicons dashicons-calendar-alt"></span>
											<?php esc_html_e( 'Dias antes da expiração do nível. Ex: 15 dias', 'person-cash-wallet' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th><label for="level_expiring_subject"><?php esc_html_e( 'Assunto do Email', 'person-cash-wallet' ); ?></label></th>
									<td>
										<input type="text" id="level_expiring_subject" name="level_expiring_subject" value="<?php echo esc_attr( $settings['level_expiring']['subject'] ); ?>" class="pcw-form-input" style="width: 100%;" placeholder="<?php esc_attr_e( '⚠️ Seu nível {level_name} expira em {days} dias', 'person-cash-wallet' ); ?>">
										<p class="description">
											<span class="dashicons dashicons-info"></span>
											<?php esc_html_e( 'Variáveis:', 'person-cash-wallet' ); ?> 
											<code>{customer_name}</code>, <code>{level_name}</code>, <code>{days}</code>, <code>{expiration_date}</code>
										</p>
									</td>
								</tr>
							</table>
						</div>
					</div>
				</div>

				<!-- Notificações de Wallet -->
				<div class="pcw-card">
					<div class="pcw-card-header">
						<h2>
							<span class="dashicons dashicons-money-alt"></span>
							<?php esc_html_e( 'Notificações de Wallet', 'person-cash-wallet' ); ?>
						</h2>
					</div>
					<div class="pcw-card-body">
						<!-- Crédito Adicionado -->
						<div style="border: 1px solid #dcdcde; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
							<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
								<label class="switch">
									<input type="checkbox" name="wallet_credit_enabled" value="yes" <?php checked( $settings['wallet_credit']['enabled'], 'yes' ); ?>>
									<span class="slider"></span>
								</label>
								<h3 style="margin: 0;">➕ <?php esc_html_e( 'Crédito Adicionado', 'person-cash-wallet' ); ?></h3>
							</div>
							
							<table class="form-table">
								<tr>
									<th style="width: 200px;"><label for="wallet_credit_subject"><?php esc_html_e( 'Assunto do Email', 'person-cash-wallet' ); ?></label></th>
									<td>
										<input type="text" id="wallet_credit_subject" name="wallet_credit_subject" value="<?php echo esc_attr( $settings['wallet_credit']['subject'] ); ?>" class="pcw-form-input" style="width: 100%;" placeholder="<?php esc_attr_e( 'Você recebeu {amount} na sua wallet!', 'person-cash-wallet' ); ?>">
										<p class="description">
											<span class="dashicons dashicons-info"></span>
											<?php esc_html_e( 'Variáveis:', 'person-cash-wallet' ); ?> 
											<code>{amount}</code>, <code>{customer_name}</code>, <code>{new_balance}</code>, <code>{source}</code>
										</p>
									</td>
								</tr>
							</table>
						</div>

						<!-- Saldo Baixo -->
						<div style="border: 1px solid #dcdcde; border-radius: 8px; padding: 20px;">
							<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
								<label class="switch">
									<input type="checkbox" name="wallet_low_balance_enabled" value="yes" <?php checked( $settings['wallet_low_balance']['enabled'], 'yes' ); ?>>
									<span class="slider"></span>
								</label>
								<h3 style="margin: 0;">⚡ <?php esc_html_e( 'Saldo Baixo', 'person-cash-wallet' ); ?></h3>
							</div>
							
							<table class="form-table">
								<tr>
									<th style="width: 200px;"><label for="wallet_low_balance_threshold"><?php esc_html_e( 'Avisar Quando Saldo Menor Que', 'person-cash-wallet' ); ?></label></th>
									<td>
										<input type="number" id="wallet_low_balance_threshold" name="wallet_low_balance_threshold" value="<?php echo esc_attr( $settings['wallet_low_balance']['threshold'] ); ?>" step="0.01" min="0" class="pcw-form-input" style="width: 150px;" placeholder="10.00">
										<p class="description">
											<span class="dashicons dashicons-warning"></span>
											<?php esc_html_e( 'Avisar cliente quando saldo ficar abaixo deste valor. Ex: R$ 10,00', 'person-cash-wallet' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th><label for="wallet_low_balance_subject"><?php esc_html_e( 'Assunto do Email', 'person-cash-wallet' ); ?></label></th>
									<td>
										<input type="text" id="wallet_low_balance_subject" name="wallet_low_balance_subject" value="<?php echo esc_attr( $settings['wallet_low_balance']['subject'] ); ?>" class="pcw-form-input" style="width: 100%;" placeholder="<?php esc_attr_e( 'Seu saldo na wallet está baixo: {balance}', 'person-cash-wallet' ); ?>">
										<p class="description">
											<span class="dashicons dashicons-info"></span>
											<?php esc_html_e( 'Variáveis:', 'person-cash-wallet' ); ?> 
											<code>{balance}</code>, <code>{customer_name}</code>
										</p>
									</td>
								</tr>
							</table>
						</div>
					</div>
				</div>

				<!-- Botões de Ação -->
				<div style="display: flex; gap: 12px; margin-bottom: 20px;">
					<button type="submit" class="button pcw-button-primary pcw-button-icon">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e( 'Salvar Configurações', 'person-cash-wallet' ); ?>
					</button>
				</div>
			</form>
		</div>

		<!-- CSS para Switch Toggle -->
		<style>
		.switch {
			position: relative;
			display: inline-block;
			width: 50px;
			height: 26px;
			flex-shrink: 0;
		}
		.switch input {
			opacity: 0;
			width: 0;
			height: 0;
		}
		.slider {
			position: absolute;
			cursor: pointer;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background-color: #dcdcde;
			transition: .3s;
			border-radius: 26px;
		}
		.slider:before {
			position: absolute;
			content: "";
			height: 20px;
			width: 20px;
			left: 3px;
			bottom: 3px;
			background-color: white;
			transition: .3s;
			border-radius: 50%;
		}
		input:checked + .slider {
			background-color: #00a32a;
		}
		input:checked + .slider:before {
			transform: translateX(24px);
		}

		/* Modal de Preview */
		.pcw-email-preview-modal {
			display: none;
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.7);
			z-index: 100000;
			align-items: center;
			justify-content: center;
		}
		.pcw-email-preview-modal.active {
			display: flex;
		}
		.pcw-email-preview-content {
			background: #fff;
			width: 90%;
			max-width: 700px;
			max-height: 90vh;
			border-radius: 12px;
			overflow: hidden;
			box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
		}
		.pcw-email-preview-header {
			padding: 20px;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: #fff;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.pcw-email-preview-header h3 {
			margin: 0;
			font-size: 18px;
		}
		.pcw-email-preview-close {
			background: rgba(255,255,255,0.2);
			border: none;
			color: #fff;
			width: 32px;
			height: 32px;
			border-radius: 50%;
			cursor: pointer;
			font-size: 20px;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.pcw-email-preview-close:hover {
			background: rgba(255,255,255,0.3);
		}
		.pcw-email-preview-subject {
			padding: 15px 20px;
			background: #f9fafb;
			border-bottom: 1px solid #e5e7eb;
		}
		.pcw-email-preview-subject strong {
			color: #374151;
		}
		.pcw-email-preview-body {
			padding: 0;
			max-height: calc(90vh - 150px);
			overflow-y: auto;
		}
		.pcw-email-preview-body iframe {
			width: 100%;
			height: 500px;
			border: none;
		}
		.pcw-preview-email {
			display: inline-flex;
			align-items: center;
			gap: 5px;
		}
		.pcw-preview-email .dashicons {
			font-size: 16px;
			width: 16px;
			height: 16px;
		}
		</style>

		<!-- Modal de Preview de Email -->
		<div id="pcw-email-preview-modal" class="pcw-email-preview-modal">
			<div class="pcw-email-preview-content">
				<div class="pcw-email-preview-header">
					<h3><?php esc_html_e( 'Preview do Email', 'person-cash-wallet' ); ?></h3>
					<button type="button" class="pcw-email-preview-close">&times;</button>
				</div>
				<div class="pcw-email-preview-subject">
					<strong><?php esc_html_e( 'Assunto:', 'person-cash-wallet' ); ?></strong>
					<span id="pcw-preview-subject"></span>
				</div>
				<div class="pcw-email-preview-body">
					<iframe id="pcw-preview-iframe"></iframe>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Fazer merge das configurações salvas com os defaults
	 *
	 * @param array $saved Configurações salvas.
	 * @param array $defaults Configurações padrão.
	 * @return array
	 */
	private function merge_settings( $saved, $defaults ) {
		$merged = $defaults;

		foreach ( $defaults as $key => $value ) {
			if ( isset( $saved[ $key ] ) ) {
				if ( is_array( $value ) && is_array( $saved[ $key ] ) ) {
					// Merge recursivo para arrays.
					$merged[ $key ] = $this->merge_settings_recursive( $saved[ $key ], $value );
				} else {
					$merged[ $key ] = $saved[ $key ];
				}
			}
		}

		return $merged;
	}

	/**
	 * Merge recursivo de arrays
	 *
	 * @param array $saved Configurações salvas.
	 * @param array $defaults Configurações padrão.
	 * @return array
	 */
	private function merge_settings_recursive( $saved, $defaults ) {
		$merged = $defaults;

		foreach ( $defaults as $key => $value ) {
			if ( isset( $saved[ $key ] ) ) {
				if ( is_array( $value ) && is_array( $saved[ $key ] ) && ! isset( $value[0] ) ) {
					// Array associativo - merge recursivo.
					$merged[ $key ] = $this->merge_settings_recursive( $saved[ $key ], $value );
				} else {
					// Valor simples ou array indexado - usar valor salvo.
					$merged[ $key ] = $saved[ $key ];
				}
			}
		}

		return $merged;
	}

	/**
	 * Obter configurações padrão
	 *
	 * @return array
	 */
	private function get_default_settings() {
		return array(
			'cashback_earned' => array(
				'enabled' => 'yes',
				'when'    => 'immediately',
				'subject' => __( 'Você ganhou {amount} de cashback!', 'person-cash-wallet' ),
				'body'    => __( '<p>Olá, {customer_name}!</p><p>Temos uma ótima notícia! Você ganhou <strong>{amount}</strong> de cashback no pedido <strong>#{order_id}</strong>!</p><p>Seu cashback estará disponível para uso em breve.</p><p>Aproveite e continue comprando para ganhar ainda mais cashback!</p><p>Atenciosamente,<br>Equipe {site_name}</p>', 'person-cash-wallet' ),
			),
			'cashback_expiring' => array(
				'enabled' => 'yes',
				'reminders' => array(
					array(
						'enabled'     => 'yes',
						'days_before' => 30,
						'subject'     => __( '⚠️ Seu cashback de {amount} expira em 30 dias!', 'person-cash-wallet' ),
						'body'        => __( '<p>Olá, {customer_name}!</p><p>Você tem <strong>{amount}</strong> de cashback que expira em <strong>30 dias</strong>.</p><p>Não se esqueça de usar seu cashback!</p><p><a href="{shop_url}" style="background: #00a32a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Fazer uma compra</a></p><p>Atenciosamente,<br>Equipe {site_name}</p>', 'person-cash-wallet' ),
					),
					array(
						'enabled'     => 'yes',
						'days_before' => 15,
						'subject'     => __( '⚠️ Seu cashback de {amount} expira em 15 dias!', 'person-cash-wallet' ),
						'body'        => __( '<p>Olá, {customer_name}!</p><p>⚠️ Atenção! Você tem <strong>{amount}</strong> de cashback que expira em <strong>15 dias</strong>.</p><p>Não perca seu cashback! Faça uma compra em breve.</p><p><a href="{shop_url}" style="background: #f59e0b; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Comprar agora</a></p><p>Atenciosamente,<br>Equipe {site_name}</p>', 'person-cash-wallet' ),
					),
					array(
						'enabled'     => 'yes',
						'days_before' => 7,
						'subject'     => __( '🚨 URGENTE: Seu cashback de {amount} expira em 7 dias!', 'person-cash-wallet' ),
						'body'        => __( '<p>Olá, {customer_name}!</p><p>🚨 <strong>URGENTE!</strong> Você tem <strong>{amount}</strong> de cashback que expira em apenas <strong>7 dias</strong>!</p><p>Não deixe seu dinheiro expirar! Use agora mesmo.</p><p><a href="{shop_url}" style="background: #dc2626; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">USAR AGORA</a></p><p>Atenciosamente,<br>Equipe {site_name}</p>', 'person-cash-wallet' ),
					),
				),
			),
			'cashback_expired' => array(
				'enabled' => 'no',
				'subject' => __( 'Seu cashback de {amount} expirou', 'person-cash-wallet' ),
				'body'    => __( '<p>Olá, {customer_name}!</p><p>Informamos que seu cashback de <strong>{amount}</strong> expirou em {expiration_date}.</p><p>Continue comprando para ganhar novos cashbacks!</p><p>Atenciosamente,<br>Equipe {site_name}</p>', 'person-cash-wallet' ),
			),
			'cashback_retroactive' => array(
				'enabled' => 'yes',
				'subject' => __( 'Você ganhou cashback retroativo!', 'person-cash-wallet' ),
				'body'    => __( '<p>Olá, {customer_name}!</p><p>Boa notícia! Você acaba de receber <strong>{cashback_amount}</strong> em cashback referente ao pedido <strong>#{order_id}</strong> realizado em {order_date}.</p><p>Seu saldo atual: <strong>{current_balance}</strong></p><p>Use seu cashback na próxima compra!</p><p>Atenciosamente,<br>Equipe {site_name}</p>', 'person-cash-wallet' ),
			),
			'level_updated' => array(
				'enabled' => 'yes',
				'subject' => __( '🎉 Parabéns! Você alcançou o nível {level_name}!', 'person-cash-wallet' ),
				'body'    => __( '<p>Olá, {customer_name}!</p><p>🎉 <strong>Parabéns!</strong> Você alcançou o nível <strong>{level_name}</strong>!</p><p>Agora você tem acesso a benefícios exclusivos:</p><ul><li>Descontos especiais</li><li>Cashback diferenciado</li><li>Ofertas exclusivas</li></ul><p>Continue comprando para manter seu nível e desbloquear novos benefícios!</p><p>Atenciosamente,<br>Equipe {site_name}</p>', 'person-cash-wallet' ),
			),
			'level_expiring' => array(
				'enabled'     => 'yes',
				'days_before' => 15,
				'subject'     => __( '⚠️ Seu nível {level_name} expira em {days} dias', 'person-cash-wallet' ),
				'body'        => __( '<p>Olá, {customer_name}!</p><p>⚠️ Seu nível <strong>{level_name}</strong> expira em <strong>{days} dias</strong> ({expiration_date}).</p><p>Não perca seus benefícios exclusivos! Faça uma compra para renovar seu nível.</p><p><a href="{shop_url}" style="background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Renovar nível</a></p><p>Atenciosamente,<br>Equipe {site_name}</p>', 'person-cash-wallet' ),
			),
			'wallet_credit' => array(
				'enabled' => 'yes',
				'subject' => __( 'Você recebeu {amount} na sua wallet!', 'person-cash-wallet' ),
				'body'    => __( '<p>Olá, {customer_name}!</p><p>💰 Você recebeu um crédito de <strong>{amount}</strong> na sua wallet!</p><p>Seu saldo atual é: <strong>{balance}</strong></p><p>Use seu saldo na próxima compra!</p><p><a href="{shop_url}" style="background: #00a32a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Comprar agora</a></p><p>Atenciosamente,<br>Equipe {site_name}</p>', 'person-cash-wallet' ),
			),
			'wallet_low_balance' => array(
				'enabled'   => 'no',
				'threshold' => 10,
				'subject'   => __( 'Seu saldo na wallet está baixo: {balance}', 'person-cash-wallet' ),
				'body'      => __( '<p>Olá, {customer_name}!</p><p>Seu saldo na wallet está baixo: <strong>{balance}</strong></p><p>Faça novas compras para ganhar cashback e aumentar seu saldo!</p><p><a href="{shop_url}" style="background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Ver produtos</a></p><p>Atenciosamente,<br>Equipe {site_name}</p>', 'person-cash-wallet' ),
			),
		);
	}

	/**
	 * Salvar configurações
	 */
	public function handle_save_settings() {
		// Verificar nonce
		if ( ! isset( $_POST['pcw_nonce'] ) || ! wp_verify_nonce( $_POST['pcw_nonce'], 'pcw_save_notification_settings' ) ) {
			wp_die( esc_html__( 'Ação não autorizada.', 'person-cash-wallet' ) );
		}

		// Verificar capability
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'person-cash-wallet' ) );
		}

		$settings = array(
			'cashback_earned' => array(
				'enabled' => isset( $_POST['cashback_earned_enabled'] ) ? 'yes' : 'no',
				'when'    => isset( $_POST['cashback_earned_when'] ) ? sanitize_text_field( $_POST['cashback_earned_when'] ) : 'immediately',
				'subject' => isset( $_POST['cashback_earned_subject'] ) ? sanitize_text_field( $_POST['cashback_earned_subject'] ) : '',
				'body'    => isset( $_POST['cashback_earned_body'] ) ? wp_kses_post( $_POST['cashback_earned_body'] ) : '',
			),
			'cashback_expiring' => array(
				'enabled' => isset( $_POST['cashback_expiring_enabled'] ) ? 'yes' : 'no',
				'reminders' => array(
					array(
						'enabled'     => isset( $_POST['cashback_expiring_reminder_0_enabled'] ) ? 'yes' : 'no',
						'days_before' => isset( $_POST['cashback_expiring_reminder_0_days'] ) ? absint( $_POST['cashback_expiring_reminder_0_days'] ) : 30,
						'subject'     => isset( $_POST['cashback_expiring_reminder_0_subject'] ) ? sanitize_text_field( $_POST['cashback_expiring_reminder_0_subject'] ) : '',
						'body'        => isset( $_POST['cashback_expiring_reminder_0_body'] ) ? wp_kses_post( $_POST['cashback_expiring_reminder_0_body'] ) : '',
					),
					array(
						'enabled'     => isset( $_POST['cashback_expiring_reminder_1_enabled'] ) ? 'yes' : 'no',
						'days_before' => isset( $_POST['cashback_expiring_reminder_1_days'] ) ? absint( $_POST['cashback_expiring_reminder_1_days'] ) : 15,
						'subject'     => isset( $_POST['cashback_expiring_reminder_1_subject'] ) ? sanitize_text_field( $_POST['cashback_expiring_reminder_1_subject'] ) : '',
						'body'        => isset( $_POST['cashback_expiring_reminder_1_body'] ) ? wp_kses_post( $_POST['cashback_expiring_reminder_1_body'] ) : '',
					),
					array(
						'enabled'     => isset( $_POST['cashback_expiring_reminder_2_enabled'] ) ? 'yes' : 'no',
						'days_before' => isset( $_POST['cashback_expiring_reminder_2_days'] ) ? absint( $_POST['cashback_expiring_reminder_2_days'] ) : 7,
						'subject'     => isset( $_POST['cashback_expiring_reminder_2_subject'] ) ? sanitize_text_field( $_POST['cashback_expiring_reminder_2_subject'] ) : '',
						'body'        => isset( $_POST['cashback_expiring_reminder_2_body'] ) ? wp_kses_post( $_POST['cashback_expiring_reminder_2_body'] ) : '',
					),
				),
			),
			'cashback_expired' => array(
				'enabled' => isset( $_POST['cashback_expired_enabled'] ) ? 'yes' : 'no',
				'subject' => isset( $_POST['cashback_expired_subject'] ) ? sanitize_text_field( $_POST['cashback_expired_subject'] ) : '',
				'body'    => isset( $_POST['cashback_expired_body'] ) ? wp_kses_post( $_POST['cashback_expired_body'] ) : '',
			),
			'cashback_retroactive' => array(
				'enabled' => isset( $_POST['cashback_retroactive_enabled'] ) ? 'yes' : 'no',
				'subject' => isset( $_POST['cashback_retroactive_subject'] ) ? sanitize_text_field( $_POST['cashback_retroactive_subject'] ) : '',
				'body'    => isset( $_POST['cashback_retroactive_body'] ) ? wp_kses_post( $_POST['cashback_retroactive_body'] ) : '',
			),
			'level_updated' => array(
				'enabled' => isset( $_POST['level_updated_enabled'] ) ? 'yes' : 'no',
				'subject' => isset( $_POST['level_updated_subject'] ) ? sanitize_text_field( $_POST['level_updated_subject'] ) : '',
				'body'    => isset( $_POST['level_updated_body'] ) ? wp_kses_post( $_POST['level_updated_body'] ) : '',
			),
			'level_expiring' => array(
				'enabled'     => isset( $_POST['level_expiring_enabled'] ) ? 'yes' : 'no',
				'days_before' => isset( $_POST['level_expiring_days'] ) ? absint( $_POST['level_expiring_days'] ) : 15,
				'subject'     => isset( $_POST['level_expiring_subject'] ) ? sanitize_text_field( $_POST['level_expiring_subject'] ) : '',
				'body'        => isset( $_POST['level_expiring_body'] ) ? wp_kses_post( $_POST['level_expiring_body'] ) : '',
			),
			'wallet_credit' => array(
				'enabled' => isset( $_POST['wallet_credit_enabled'] ) ? 'yes' : 'no',
				'subject' => isset( $_POST['wallet_credit_subject'] ) ? sanitize_text_field( $_POST['wallet_credit_subject'] ) : '',
				'body'    => isset( $_POST['wallet_credit_body'] ) ? wp_kses_post( $_POST['wallet_credit_body'] ) : '',
			),
			'wallet_low_balance' => array(
				'enabled'   => isset( $_POST['wallet_low_balance_enabled'] ) ? 'yes' : 'no',
				'threshold' => isset( $_POST['wallet_low_balance_threshold'] ) ? floatval( $_POST['wallet_low_balance_threshold'] ) : 10,
				'subject'   => isset( $_POST['wallet_low_balance_subject'] ) ? sanitize_text_field( $_POST['wallet_low_balance_subject'] ) : '',
				'body'      => isset( $_POST['wallet_low_balance_body'] ) ? wp_kses_post( $_POST['wallet_low_balance_body'] ) : '',
			),
		);

		update_option( 'pcw_notification_settings', $settings );

		wp_safe_redirect( add_query_arg( array( 'page' => 'pcw-notifications', 'message' => 'settings_saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
