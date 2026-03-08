<?php
/**
 * Classe de ativação do plugin
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de ativação
 */
class PCW_Activator {

	/**
	 * Ativar plugin
	 */
	public static function activate() {
		// Verificar se WooCommerce está ativo
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( PCW_PLUGIN_FILE ) );
			wp_die( esc_html__( 'Growly Digital requer WooCommerce para funcionar. Por favor, instale e ative o WooCommerce primeiro.', 'person-cash-wallet' ) );
		}

		// Criar tabelas do banco de dados
		self::create_tables();

		// Criar opções padrão
		self::create_default_options();

		// Criar automações padrão
		self::create_default_automations();

		// Criar lista padrão
		self::create_default_list();

		// Agendar cron jobs
		self::schedule_cron_jobs();

		// Salvar versão do plugin
		update_option( 'pcw_version', PCW_VERSION );

		// Flag de ativação
		set_transient( 'pcw_activation_redirect', true, 30 );

		// Flush rewrite rules para registrar endpoints
		flush_rewrite_rules();
	}

	/**
	 * Criar tabelas do banco de dados
	 */
	private static function create_tables() {
		require_once PCW_PLUGIN_DIR . 'includes/core/database/class-pcw-database.php';
		$database = new PCW_Database();
		$database->create_tables();
	}

	/**
	 * Criar opções padrão
	 */
	private static function create_default_options() {
		$default_options = array(
			'pcw_cashback_enabled'           => 'yes',
			'pcw_levels_enabled'             => 'yes',
			'pcw_wallet_enabled'             => 'yes',
			'pcw_notifications_enabled'      => 'yes',
			'pcw_currency'                   => 'BRL',
			'pcw_cashback_expiring_days'     => 7,
			'pcw_auto_transfer_to_wallet'    => 'yes',
			'pcw_auto_transfer_days'         => 0,
			'pcw_wallet_min_amount'          => 0,
			'pcw_allow_partial_payment'     => 'yes',
			'pcw_allow_full_payment'        => 'yes',
			// Email Cashback Retroativo.
			'pcw_email_retroactive_enabled'  => 'yes',
			'pcw_email_retroactive_subject'  => '🎁 Você ganhou cashback retroativo!',
			'pcw_email_retroactive_body'     => '',
		);

		foreach ( $default_options as $option => $value ) {
			if ( false === get_option( $option ) ) {
				add_option( $option, $value );
			}
		}

		// Configurações padrão de Indicações
		$referral_settings = get_option( 'pcw_referral_settings' );
		if ( false === $referral_settings ) {
			$referral_defaults = array(
				'enabled'                    => 'yes',
				'reward_type'                => 'fixed',
				'reward_amount'              => 10.00,
				'max_reward_amount'          => 0,
				'min_order_amount'           => 0,
				'reward_order_statuses'      => array( 'completed' ),
				'reward_limit_type'          => 'first',
				'reward_limit_count'         => 1,
				'referred_reward_enabled'    => 'no',
				'referred_reward_type'       => 'fixed',
				'referred_reward_amount'     => 5.00,
				'referred_reward_first_only' => 'yes',
				'cookie_days'                => 30,
				'email_days_after_order'     => 20,
			);
			add_option( 'pcw_referral_settings', $referral_defaults );
		}
	}

	/**
	 * Criar lista padrão
	 */
	private static function create_default_list() {
		global $wpdb;

		// Verificar se já existe uma lista padrão
		$table = $wpdb->prefix . 'pcw_custom_lists';
		$exists = $wpdb->get_var( "SELECT id FROM {$table} WHERE name = 'Newsletter Geral' LIMIT 1" );

		if ( $exists ) {
			return absint( $exists );
		}

		// Criar lista padrão
		require_once PCW_PLUGIN_DIR . 'includes/core/campaigns/class-pcw-custom-lists.php';

		$list_id = PCW_Custom_Lists::create( array(
			'name'        => 'Newsletter Geral',
			'description' => 'Lista geral de inscritos na newsletter',
			'type'        => 'manual',
		) );

		if ( ! is_wp_error( $list_id ) ) {
			update_option( 'pcw_default_list_id', $list_id );
			return $list_id;
		}

		return 0;
	}

	/**
	 * Criar automações padrão
	 */
	private static function create_default_automations() {
		global $wpdb;

		// Verificar se já existe a automação de boas-vindas
		$table = $wpdb->prefix . 'pcw_automations';
		$exists = $wpdb->get_var( "SELECT id FROM {$table} WHERE type = 'welcome' AND name = 'Boas-vindas aos Novos Inscritos' LIMIT 1" );

		if ( $exists ) {
			return; // Já existe
		}

		require_once PCW_PLUGIN_DIR . 'includes/core/automations/class-pcw-automations.php';

		// Obter lista padrão
		$default_list_id = get_option( 'pcw_default_list_id', 0 );

		// Template de email de boas-vindas
		$email_template = self::get_welcome_email_template();

		// Workflow steps
		$workflow_steps = array(
			array(
				'action' => 'send_email',
				'delay'  => 0,
			),
		);

		// Se tem lista padrão, adicionar step para adicionar à lista
		if ( $default_list_id > 0 ) {
			$workflow_steps[] = array(
				'action'  => 'add_to_list',
				'list_id' => $default_list_id,
				'delay'   => 0,
			);
		}

		// Criar automação
		$automation_id = PCW_Automations::instance()->create( array(
			'name'            => 'Boas-vindas aos Novos Inscritos',
			'description'     => 'Envia email de boas-vindas automaticamente para novos usuários e inscritos',
			'type'            => 'welcome',
			'status'          => 'active',
			'trigger_type'    => 'user_registered',
			'trigger_config'  => array(),
			'workflow_steps'  => $workflow_steps,
			'email_template'  => $email_template,
			'email_subject'   => '🎉 Bem-vindo(a) à {site_name}!',
			'use_ai_subject'  => 0,
			'channels'        => 'email',
		) );

		if ( $automation_id ) {
			update_option( 'pcw_welcome_automation_id', $automation_id );
		}
	}

	/**
	 * Obter template de email de boas-vindas
	 *
	 * @return string
	 */
	private static function get_welcome_email_template() {
		ob_start();
		?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Bem-vindo(a)!</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
	<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 0;">
		<tr>
			<td align="center">
				<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;">
					<!-- Header -->
					<tr>
						<td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;">
							<h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: 700;">
								🎉 Bem-vindo(a)!
							</h1>
						</td>
					</tr>
					
					<!-- Corpo -->
					<tr>
						<td style="padding: 40px 30px;">
							<p style="margin: 0 0 20px; font-size: 16px; line-height: 1.6; color: #374151;">
								Olá <strong>{customer_name}</strong>,
							</p>
							
							<p style="margin: 0 0 20px; font-size: 16px; line-height: 1.6; color: #374151;">
								É com grande alegria que damos as boas-vindas à <strong>{site_name}</strong>! 
								Estamos muito felizes em tê-lo(a) conosco.
							</p>
							
							<p style="margin: 0 0 20px; font-size: 16px; line-height: 1.6; color: #374151;">
								A partir de agora, você terá acesso a:
							</p>
							
							<ul style="margin: 0 0 20px 20px; font-size: 16px; line-height: 1.8; color: #374151;">
								<li>✨ Ofertas e descontos exclusivos</li>
								<li>🎁 Programa de cashback em todas as compras</li>
								<li>📧 Novidades e lançamentos em primeira mão</li>
								<li>🏆 Sistema de níveis e recompensas</li>
								<li>💰 Wallet digital para usar seus créditos</li>
							</ul>
							
							<p style="margin: 0 0 30px; font-size: 16px; line-height: 1.6; color: #374151;">
								Aproveite e comece a explorar nossa loja agora mesmo!
							</p>
							
							<!-- Botão CTA -->
							<table width="100%" cellpadding="0" cellspacing="0">
								<tr>
									<td align="center" style="padding: 20px 0;">
										<a href="{site_url}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
											Começar a Comprar
										</a>
									</td>
								</tr>
							</table>
							
							<p style="margin: 30px 0 0; font-size: 16px; line-height: 1.6; color: #374151;">
								Se tiver qualquer dúvida, nossa equipe está sempre pronta para ajudar!
							</p>
							
							<p style="margin: 20px 0 0; font-size: 16px; line-height: 1.6; color: #374151;">
								Um grande abraço,<br>
								<strong>Equipe {site_name}</strong>
							</p>
						</td>
					</tr>
					
					<!-- Footer -->
					<tr>
						<td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
							<p style="margin: 0 0 10px; font-size: 14px; color: #6b7280;">
								{site_name}
							</p>
							<p style="margin: 0 0 10px; font-size: 14px; color: #6b7280;">
								📧 {admin_email}
							</p>
							<p style="margin: 0; font-size: 12px; color: #9ca3af;">
								Você está recebendo este email porque se cadastrou em nosso site.
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Agendar cron jobs
	 */
	private static function schedule_cron_jobs() {
		if ( ! wp_next_scheduled( 'pcw_daily_expiration_check' ) ) {
			wp_schedule_event( time(), 'daily', 'pcw_daily_expiration_check' );
		}

		// Cron para recalcular RFM semanalmente
		if ( ! wp_next_scheduled( 'pcw_weekly_rfm_calculation' ) ) {
			wp_schedule_event( time(), 'weekly', 'pcw_weekly_rfm_calculation' );
		}
	}
}
