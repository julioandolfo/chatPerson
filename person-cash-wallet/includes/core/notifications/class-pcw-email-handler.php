<?php
/**
 * Classe handler de emails
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe handler de emails
 */
class PCW_Email_Handler {

	/**
	 * Inicializar
	 */
	public function init() {
		// Configurar PHPMailer se SMTP estiver habilitado
		if ( $this->is_smtp_enabled() ) {
			add_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
		}
	}

	/**
	 * Verificar se SMTP está habilitado
	 *
	 * @return bool
	 */
	private function is_smtp_enabled() {
		return 'yes' === get_option( 'pcw_email_smtp_enabled', 'no' );
	}

	/**
	 * Configurar PHPMailer com SMTP
	 *
	 * @param PHPMailer $phpmailer Instância do PHPMailer.
	 */
	public function configure_smtp( $phpmailer ) {
		// Verificar se é email do nosso plugin
		global $pcw_sending_email;
		if ( ! isset( $pcw_sending_email ) || ! $pcw_sending_email ) {
			return;
		}

		$smtp_host = get_option( 'pcw_email_smtp_host', '' );
		if ( empty( $smtp_host ) ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = $smtp_host;
		$phpmailer->SMTPAuth   = 'yes' === get_option( 'pcw_email_smtp_auth', 'yes' );
		$phpmailer->Port       = absint( get_option( 'pcw_email_smtp_port', 587 ) );
		$phpmailer->Username   = get_option( 'pcw_email_smtp_user', '' );
		$phpmailer->Password   = get_option( 'pcw_email_smtp_pass', '' );
		
		$secure = get_option( 'pcw_email_smtp_secure', 'tls' );
		if ( 'none' !== $secure ) {
			$phpmailer->SMTPSecure = $secure;
		}
		
		$phpmailer->From       = get_option( 'pcw_email_from_email', get_option( 'admin_email' ) );
		$phpmailer->FromName   = get_option( 'pcw_email_from_name', get_bloginfo( 'name' ) );

		// Configurações adicionais
		$phpmailer->SMTPDebug = 0; // 0 = off, 1 = client, 2 = client and server
		$phpmailer->CharSet   = 'UTF-8';
		$phpmailer->isHTML( true );
	}

	/**
	 * Enviar email
	 *
	 * @param string|array $to Email(s) destinatário(s).
	 * @param string       $subject Assunto.
	 * @param string       $message Mensagem.
	 * @param array        $headers Headers adicionais.
	 * @param array        $attachments Anexos.
	 * @param bool         $wrap_html Se deve envolver o conteúdo no template HTML.
	 * @param array        $log_data Dados adicionais para log (email_type, user_id, order_id, related_id, metadata).
	 * @param bool         $use_queue Se deve usar fila (null = auto-detectar).
	 * @return bool|int True/false para envio direto, ou ID da fila se enfileirado.
	 */
	public static function send( $to, $subject, $message, $headers = array(), $attachments = array(), $wrap_html = true, $log_data = array(), $use_queue = null ) {
		// Envolver conteúdo no template HTML bonito se solicitado
		if ( $wrap_html ) {
			$message = self::wrap_html_template( $message, $subject );
		}

		// Auto-detectar se deve usar fila
		if ( null === $use_queue ) {
			$use_queue = self::should_use_queue();
		}

		// Se deve usar fila e não está em processamento de fila
		global $pcw_processing_queue;
		if ( $use_queue && empty( $pcw_processing_queue ) && class_exists( 'PCW_Message_Queue_Manager' ) ) {
			$recipients = is_array( $to ) ? $to : array( $to );
			$queue_manager = PCW_Message_Queue_Manager::instance();
			$queue_ids = array();

			foreach ( $recipients as $recipient ) {
				$queue_id = $queue_manager->add_email_to_queue( array(
					'to_email'    => $recipient,
					'subject'     => $subject,
					'message'     => $message,
					'headers'     => $headers,
					'attachments' => $attachments,
					'metadata'    => $log_data,
				) );
				if ( $queue_id ) {
					$queue_ids[] = $queue_id;
				}
			}

			// Retornar IDs da fila ou primeiro ID
			return count( $queue_ids ) === 1 ? $queue_ids[0] : $queue_ids;
		}

		// Envio direto
		return self::send_direct( $to, $subject, $message, $headers, $attachments, $log_data );
	}

	/**
	 * Enviar email diretamente (sem fila)
	 *
	 * @param string|array $to Email(s) destinatário(s).
	 * @param string       $subject Assunto.
	 * @param string       $message Mensagem.
	 * @param array        $headers Headers adicionais.
	 * @param array        $attachments Anexos.
	 * @param array        $log_data Dados adicionais para log.
	 * @return bool
	 */
	public static function send_direct( $to, $subject, $message, $headers = array(), $attachments = array(), $log_data = array() ) {
		// Marcar como email do plugin
		global $pcw_sending_email;
		$pcw_sending_email = true;

		// Preparar headers padrão
		$default_headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_option( 'pcw_email_from_name', get_bloginfo( 'name' ) ) . ' <' . get_option( 'pcw_email_from_email', get_option( 'admin_email' ) ) . '>',
		);

		$all_headers = array_merge( $default_headers, $headers );

		// Enviar email
		$result = wp_mail( $to, $subject, $message, $all_headers, $attachments );

		// Limpar flag
		$pcw_sending_email = false;

		// Registrar log do envio (após limpar flag para evitar loops)
		self::log_email( $to, $subject, $message, $result, $log_data );

		return $result;
	}

	/**
	 * Verificar se deve usar fila para emails
	 *
	 * @return bool
	 */
	public static function should_use_queue() {
		global $wpdb;
		$smtp_table = $wpdb->prefix . 'pcw_smtp_accounts';

		// Verificar se há contas SMTP com distribuição habilitada
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$smtp_table} 
			WHERE status = 'active' AND distribution_enabled = 1"
		);

		return $count > 0;
	}

	/**
	 * Envolver conteúdo no template HTML bonito
	 *
	 * @param string $content Conteúdo do email.
	 * @param string $title Título para o cabeçalho.
	 * @return string
	 */
	private static function wrap_html_template( $content, $title = '' ) {
		$site_name = get_bloginfo( 'name' );
		$current_year = date( 'Y' );
		$site_url = home_url();
		
		// Converter quebras de linha em parágrafos HTML se o conteúdo for texto puro
		if ( strip_tags( $content ) === $content ) {
			$content = wpautop( $content );
		}

		$html = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>' . esc_html( $title ) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f9fafb; line-height: 1.6;">
	<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f9fafb; padding: 40px 20px;">
		<tr>
			<td align="center">
				<!-- Container Principal -->
				<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); overflow: hidden;">
					
					<!-- Cabeçalho com Marca -->
					<tr>
						<td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px 40px; text-align: center;">
							<h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600; letter-spacing: -0.5px;">
								💳 ' . esc_html( $site_name ) . '
							</h1>
						</td>
					</tr>

					<!-- Conteúdo Principal -->
					<tr>
						<td style="padding: 40px;">
							<div style="color: #374151; font-size: 15px; line-height: 1.7;">
								' . $content . '
							</div>
						</td>
					</tr>

					<!-- Botão de Ação (se houver links) -->
					<tr>
						<td style="padding: 0 40px 40px 40px; text-align: center;">
							<!-- O botão será adicionado via shortcode {action_button} no conteúdo -->
						</td>
					</tr>

					<!-- Rodapé -->
					<tr>
						<td style="background-color: #f9fafb; padding: 30px 40px; border-top: 1px solid #e5e7eb;">
							<table width="100%" cellpadding="0" cellspacing="0" border="0">
								<tr>
									<td style="text-align: center;">
										<p style="margin: 0 0 12px 0; color: #6b7280; font-size: 13px;">
											<strong>' . esc_html( $site_name ) . '</strong>
										</p>
										<p style="margin: 0 0 12px 0; color: #9ca3af; font-size: 12px;">
											Este é um email automático.<br>
											Por favor, não responda este email.
										</p>
										<p style="margin: 0; color: #9ca3af; font-size: 11px;">
											© ' . esc_html( $current_year ) . ' ' . esc_html( $site_name ) . '. Todos os direitos reservados.
										</p>
										<p style="margin: 12px 0 0 0;">
											<a href="' . esc_url( $site_url ) . '" style="color: #667eea; text-decoration: none; font-size: 12px;">
												Visitar o site
											</a>
										</p>
									</td>
								</tr>
							</table>
						</td>
					</tr>

				</table>
			</td>
		</tr>
	</table>
</body>
</html>';

		return $html;
	}

	/**
	 * Gerar preview de email (público para uso no admin)
	 *
	 * @param string $content Conteúdo do email.
	 * @param string $title Título para o cabeçalho.
	 * @return string
	 */
	public static function generate_preview( $content, $title = '' ) {
		return self::wrap_html_template( $content, $title );
	}

	/**
	 * Registrar log do envio de email
	 *
	 * @param string|array $to Destinatário(s).
	 * @param string       $subject Assunto.
	 * @param string       $message Corpo do email.
	 * @param bool         $result Resultado do envio.
	 */
	/**
	 * Registrar log de email no banco de dados e arquivos
	 *
	 * @param string|array $to Destinatário(s).
	 * @param string       $subject Assunto.
	 * @param string       $message Mensagem.
	 * @param bool         $result Resultado do envio.
	 * @param array        $log_data Dados adicionais (email_type, user_id, order_id, related_id, metadata).
	 */
	private static function log_email( $to, $subject, $message, $result, $log_data = array() ) {
		global $wpdb;

		$recipients = is_array( $to ) ? implode( ', ', $to ) : $to;
		$plain_message = wp_strip_all_tags( $message );
		$preview = substr( $plain_message, 0, 150 );
		if ( strlen( $plain_message ) > 150 ) {
			$preview .= '...';
		}

		$timestamp = current_time( 'Y-m-d H:i:s' );
		$log_message = sprintf(
			'[%s] Email %s | Para: %s | Assunto: %s | Preview: %s',
			$timestamp,
			$result ? 'ENVIADO' : 'FALHA',
			$recipients,
			$subject,
			$preview
		);

		// 1. Salvar no banco de dados
		$table_name = $wpdb->prefix . 'pcw_email_logs';
		$wpdb->insert(
			$table_name,
			array(
				'recipient'   => $recipients,
				'subject'     => $subject,
				'content'     => $message,
				'email_type'  => isset( $log_data['email_type'] ) ? sanitize_text_field( $log_data['email_type'] ) : 'general',
				'status'      => $result ? 'sent' : 'failed',
				'user_id'     => isset( $log_data['user_id'] ) ? absint( $log_data['user_id'] ) : null,
				'order_id'    => isset( $log_data['order_id'] ) ? absint( $log_data['order_id'] ) : null,
				'related_id'  => isset( $log_data['related_id'] ) ? absint( $log_data['related_id'] ) : null,
				'metadata'    => isset( $log_data['metadata'] ) ? wp_json_encode( $log_data['metadata'] ) : null,
				'created_at'  => $timestamp,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		// 2. Tentar usar WooCommerce Logger
		$logged = false;
		if ( function_exists( 'wc_get_logger' ) ) {
			try {
				$logger = wc_get_logger();
				$logger->info( $log_message, array( 'source' => 'person-cash-wallet' ) );
				$logged = true;
			} catch ( Exception $e ) {
				// Falhou, usar fallback
				$logged = false;
			}
		}

		// 3. Fallback: error_log do PHP
		error_log( '[Growly Digital] ' . $log_message );
	}

	/**
	 * Obter logs de emails do banco de dados
	 *
	 * @param array $args Argumentos de busca.
	 * @return array
	 */
	public static function get_email_logs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'email_type' => '',
			'status'     => '',
			'user_id'    => 0,
			'search'     => '',
			'limit'      => 30,
			'offset'     => 0,
			'orderby'    => 'created_at',
			'order'      => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );
		$table_name = $wpdb->prefix . 'pcw_email_logs';

		$where = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['email_type'] ) ) {
			$where[] = 'email_type = %s';
			$where_values[] = $args['email_type'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[] = 'user_id = %d';
			$where_values[] = absint( $args['user_id'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[] = '(recipient LIKE %s OR subject LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where_values[] = $search_term;
			$where_values[] = $search_term;
		}

		$where_sql = implode( ' AND ', $where );
		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		$limit = absint( $args['limit'] );
		$offset = absint( $args['offset'] );

		$sql = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY {$orderby} LIMIT %d OFFSET %d";
		$where_values[] = $limit;
		$where_values[] = $offset;

		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, $where_values );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Contar logs de emails
	 *
	 * @param array $args Argumentos de busca.
	 * @return int
	 */
	public static function count_email_logs( $args = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_email_logs';

		$where = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['email_type'] ) ) {
			$where[] = 'email_type = %s';
			$where_values[] = $args['email_type'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[] = 'user_id = %d';
			$where_values[] = absint( $args['user_id'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[] = '(recipient LIKE %s OR subject LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where_values[] = $search_term;
			$where_values[] = $search_term;
		}

		$where_sql = implode( ' AND ', $where );

		$sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";

		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, $where_values );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Obter estatísticas de emails
	 *
	 * @return array
	 */
	public static function get_email_stats() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pcw_email_logs';
		$stats = array();

		// Total de emails
		$stats['total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

		// Emails enviados
		$stats['sent'] = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE status = %s", 'sent' )
		);

		// Emails com falha
		$stats['failed'] = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE status = %s", 'failed' )
		);

		// Emails por tipo
		$stats['by_type'] = $wpdb->get_results(
			"SELECT email_type, COUNT(*) as count FROM {$table_name} GROUP BY email_type ORDER BY count DESC",
			ARRAY_A
		);

		// Últimos 30 dias
		$stats['last_30_days'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s",
				date( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
			)
		);

		// Taxa de sucesso
		$stats['success_rate'] = $stats['total'] > 0 
			? round( ( $stats['sent'] / $stats['total'] ) * 100, 1 ) 
			: 0;

		return $stats;
	}

	/**
	 * Obter tipos de email disponíveis
	 *
	 * @return array
	 */
	public static function get_email_types() {
		return array(
			'general'             => __( 'Geral', 'person-cash-wallet' ),
			'cashback_earned'     => __( 'Cashback Ganho', 'person-cash-wallet' ),
			'cashback_available'  => __( 'Cashback Disponível', 'person-cash-wallet' ),
			'cashback_expiring'   => __( 'Cashback Expirando', 'person-cash-wallet' ),
			'cashback_expired'    => __( 'Cashback Expirado', 'person-cash-wallet' ),
			'level_up'            => __( 'Subiu de Nível', 'person-cash-wallet' ),
			'level_expiring'      => __( 'Nível Expirando', 'person-cash-wallet' ),
			'level_expired'       => __( 'Nível Expirado', 'person-cash-wallet' ),
			'wallet_credit'       => __( 'Crédito na Wallet', 'person-cash-wallet' ),
			'wallet_debit'        => __( 'Débito na Wallet', 'person-cash-wallet' ),
			'referral_request'    => __( 'Pedido de Indicação', 'person-cash-wallet' ),
			'referral_reward'     => __( 'Recompensa de Indicação', 'person-cash-wallet' ),
			'referral_conversion' => __( 'Indicação Convertida', 'person-cash-wallet' ),
			'campaign'            => __( 'Campanha', 'person-cash-wallet' ),
			'automation'          => __( 'Automação', 'person-cash-wallet' ),
		);
	}

	/**
	 * Enviar email de cashback ganho
	 *
	 * @param object $cashback Dados do cashback.
	 * @param object $user Dados do usuário.
	 */
	public static function send_cashback_earned( $cashback, $user ) {
		if ( 'yes' !== get_option( 'pcw_notifications_enabled', 'yes' ) ) {
			return;
		}

		// Assunto usa formatação plain text (sem HTML)
		$subject = sprintf(
			__( '🎉 Você ganhou %s de cashback!', 'person-cash-wallet' ),
			PCW_Formatters::format_money_plain( $cashback->amount )
		);

		// Corpo HTML estruturado e bonito
		$message = '
		<h2 style="color: #059669; margin: 0 0 20px 0; font-size: 22px;">
			🎉 Parabéns, ' . esc_html( $user->display_name ) . '!
		</h2>
		
		<p style="font-size: 16px; margin-bottom: 24px;">
			Você acabou de ganhar cashback na sua compra!
		</p>

		<div style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-left: 4px solid #10b981; padding: 20px; border-radius: 8px; margin: 24px 0;">
			<table width="100%" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td style="padding: 8px 0;">
						<span style="color: #6b7280; font-size: 13px; display: block;">Valor do Cashback</span>
						<span style="color: #059669; font-size: 28px; font-weight: bold; display: block; margin-top: 4px;">
							' . PCW_Formatters::format_money( $cashback->amount ) . '
						</span>
					</td>
				</tr>
				<tr>
					<td style="padding: 8px 0; border-top: 1px solid rgba(16, 185, 129, 0.2);">
						<span style="color: #6b7280; font-size: 13px;">📦 Pedido: <strong>#' . absint( $cashback->order_id ) . '</strong></span>
					</td>
				</tr>
				<tr>
					<td style="padding: 8px 0;">
						<span style="color: #6b7280; font-size: 13px;">📅 Válido até: <strong>' . date_i18n( get_option( 'date_format' ), strtotime( $cashback->expires_date ) ) . '</strong></span>
					</td>
				</tr>
			</table>
		</div>

		<p style="margin: 24px 0;">
			Use seu cashback na sua próxima compra e economize ainda mais! 💰
		</p>

		<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0;">
			<tr>
				<td align="center">
					<a href="' . esc_url( wc_get_account_endpoint_url( 'wallet' ) ) . '" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px;">
						Ver Minha Carteira
					</a>
				</td>
			</tr>
		</table>

		<p style="color: #9ca3af; font-size: 13px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
			<em>Aproveite seu cashback antes do vencimento!</em>
		</p>';

		return self::send( $user->user_email, $subject, $message, array(), array(), true, array(
			'email_type' => 'cashback_earned',
			'user_id'    => $user->ID,
			'order_id'   => $cashback->order_id,
			'related_id' => $cashback->id,
		) );
	}

	/**
	 * Enviar email de cashback expirando
	 *
	 * @param object $cashback Dados do cashback.
	 * @param object $user Dados do usuário.
	 * @param array  $reminder Configurações do lembrete.
	 * @param int    $days_before Dias antes da expiração.
	 */
	public static function send_cashback_expiring( $cashback, $user, $reminder = null, $days_before = 7 ) {
		if ( 'yes' !== get_option( 'pcw_notifications_enabled', 'yes' ) ) {
			return;
		}

		// Usar configurações do lembrete se fornecidas
		if ( $reminder && isset( $reminder['subject'] ) && isset( $reminder['body'] ) ) {
			$subject = $reminder['subject'];
			$body = $reminder['body'];
		} else {
			// Fallback para configurações antigas (retrocompatibilidade) - usar template bonito
			$subject = sprintf(
				__( '⏰ Seu cashback de %s expira em breve!', 'person-cash-wallet' ),
				PCW_Formatters::format_money_plain( $cashback->amount )
			);
			
			$body = '
			<h2 style="color: #f59e0b; margin: 0 0 20px 0; font-size: 22px;">
				⏰ Atenção: Cashback Expirando!
			</h2>
			
			<p style="font-size: 16px; margin-bottom: 24px;">
				Olá ' . esc_html( $user->display_name ) . ',
			</p>

			<div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 20px; border-radius: 8px; margin: 24px 0;">
				<p style="margin: 0 0 12px 0; color: #92400e; font-size: 15px;">
					<strong>⚠️ Você tem cashback que vai expirar em ' . absint( $days_before ) . ' dias!</strong>
				</p>
				<div style="margin-top: 16px;">
					<span style="color: #6b7280; font-size: 13px; display: block;">Valor do Cashback</span>
					<span style="color: #f59e0b; font-size: 24px; font-weight: bold; display: block; margin-top: 4px;">
						' . PCW_Formatters::format_money( $cashback->amount ) . '
					</span>
				</div>
			</div>

			<p style="margin: 24px 0; line-height: 1.7;">
				Não deixe seu cashback expirar! Faça uma compra agora e use seu saldo para economizar.
			</p>

			<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0;">
				<tr>
					<td align="center">
						<a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px; margin-right: 12px;">
							Ver Produtos
						</a>
						<a href="' . esc_url( wc_get_account_endpoint_url( 'wallet' ) ) . '" style="display: inline-block; background: #f3f4f6; color: #374151; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px;">
							Ver Minha Carteira
						</a>
					</td>
				</tr>
			</table>';
		}

		// Substituir variáveis no assunto e corpo
		$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url();
		$expiration_date = date_i18n( get_option( 'date_format' ), strtotime( $cashback->expires_date ) );
		
		// Replacements para assunto (plain text)
		$subject_replacements = array(
			'{customer_name}'   => $user->display_name,
			'{amount}'          => PCW_Formatters::format_money_plain( $cashback->amount ),
			'{days}'            => $days_before,
			'{expiration_date}' => $expiration_date,
			'{site_name}'       => get_bloginfo( 'name' ),
			'{shop_url}'        => $shop_url,
		);

		// Replacements para corpo (HTML)
		$body_replacements = array(
			'{customer_name}'   => $user->display_name,
			'{amount}'          => PCW_Formatters::format_money( $cashback->amount ),
			'{days}'            => $days_before,
			'{expiration_date}' => $expiration_date,
			'{site_name}'       => get_bloginfo( 'name' ),
			'{shop_url}'        => $shop_url,
		);

		$subject = str_replace( array_keys( $subject_replacements ), array_values( $subject_replacements ), $subject );
		$body = str_replace( array_keys( $body_replacements ), array_values( $body_replacements ), $body );

		// Enviar email
		return self::send( $user->user_email, $subject, $body, array(), array(), true, array(
			'email_type' => 'cashback_expiring',
			'user_id'    => $user->ID,
			'order_id'   => $cashback->order_id,
			'related_id' => $cashback->id,
		) );
	}

	/**
	 * Enviar email de cashback expirado
	 *
	 * @param object $cashback Dados do cashback.
	 * @param object $user Dados do usuário.
	 */
	public static function send_cashback_expired( $cashback, $user ) {
		if ( 'yes' !== get_option( 'pcw_notifications_enabled', 'yes' ) ) {
			return;
		}

		$subject = sprintf(
			__( '⏰ Seu cashback de %s expirou', 'person-cash-wallet' ),
			PCW_Formatters::format_money_plain( $cashback->amount )
		);

		$message = '
		<h2 style="color: #dc2626; margin: 0 0 20px 0; font-size: 22px;">
			⏰ Cashback Expirado
		</h2>
		
		<p style="font-size: 16px; margin-bottom: 24px;">
			Olá ' . esc_html( $user->display_name ) . ',
		</p>

		<div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 20px; border-radius: 8px; margin: 24px 0;">
			<p style="margin: 0; color: #991b1b; font-size: 15px;">
				<strong>⚠️ Infelizmente, seu cashback de ' . PCW_Formatters::format_money( $cashback->amount ) . ' expirou.</strong>
			</p>
		</div>

		<p style="margin: 24px 0; line-height: 1.7;">
			Para evitar que isso aconteça novamente:
		</p>

		<ul style="margin: 16px 0; padding-left: 24px; color: #4b5563;">
			<li style="margin: 8px 0;">📱 Fique atento aos emails de aviso de expiração</li>
			<li style="margin: 8px 0;">🛍️ Use seus cashbacks assim que recebê-los</li>
			<li style="margin: 8px 0;">⏰ Verifique regularmente sua carteira</li>
		</ul>

		<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0;">
			<tr>
				<td align="center">
					<a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px;">
						Fazer Uma Nova Compra
					</a>
				</td>
			</tr>
		</table>';

		return self::send( $user->user_email, $subject, $message, array(), array(), true, array(
			'email_type' => 'cashback_expired',
			'user_id'    => $user->ID,
			'order_id'   => $cashback->order_id,
			'related_id' => $cashback->id,
		) );
	}

	/**
	 * Enviar email de nível atualizado
	 *
	 * @param int    $user_id ID do usuário.
	 * @param int    $level_id ID do nível.
	 * @param string $action Ação (assigned/removed).
	 */
	public static function send_level_updated( $user_id, $level_id, $action = 'assigned' ) {
		if ( 'yes' !== get_option( 'pcw_notifications_enabled', 'yes' ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		$level = PCW_Levels::get_level( $level_id );

		if ( ! $user || ! $level ) {
			return;
		}

		if ( 'assigned' === $action ) {
			$subject = sprintf(
				__( '🏆 Parabéns! Você alcançou o nível %s!', 'person-cash-wallet' ),
				$level->name
			);
			
			$message = '
			<h2 style="color: #7c3aed; margin: 0 0 20px 0; font-size: 22px;">
				🏆 Parabéns, ' . esc_html( $user->display_name ) . '!
			</h2>
			
			<p style="font-size: 16px; margin-bottom: 24px;">
				Você alcançou o nível <strong>' . esc_html( $level->name ) . '</strong>!
			</p>

			<div style="background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%); border-left: 4px solid #7c3aed; padding: 20px; border-radius: 8px; margin: 24px 0; text-align: center;">
				<span style="color: #7c3aed; font-size: 48px; display: block; margin-bottom: 12px;">🏆</span>
				<span style="color: #5b21b6; font-size: 24px; font-weight: bold; display: block;">
					Nível ' . esc_html( $level->name ) . '
				</span>
			</div>

			<p style="margin: 24px 0; line-height: 1.7;">
				Como membro deste nível, você tem acesso a benefícios exclusivos e descontos especiais nas suas compras!
			</p>

			<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0;">
				<tr>
					<td align="center">
						<a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px;">
							Aproveitar Benefícios
						</a>
					</td>
				</tr>
			</table>';
		} else {
			$subject = sprintf(
				__( '📊 Atualização de Nível - %s', 'person-cash-wallet' ),
				$level->name
			);
			
			$message = '
			<h2 style="color: #6b7280; margin: 0 0 20px 0; font-size: 22px;">
				📊 Atualização de Nível
			</h2>
			
			<p style="font-size: 16px; margin-bottom: 24px;">
				Olá ' . esc_html( $user->display_name ) . ',
			</p>

			<div style="background: #f9fafb; border-left: 4px solid #9ca3af; padding: 20px; border-radius: 8px; margin: 24px 0;">
				<p style="margin: 0; color: #4b5563; font-size: 15px;">
					Seu nível <strong>' . esc_html( $level->name ) . '</strong> foi removido.
				</p>
			</div>

			<p style="margin: 24px 0; line-height: 1.7;">
				Continue comprando para alcançar novos níveis e ter acesso a benefícios exclusivos!
			</p>

			<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0;">
				<tr>
					<td align="center">
						<a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px;">
							Ver Produtos
						</a>
					</td>
				</tr>
			</table>';
		}

		return self::send( $user->user_email, $subject, $message, array(), array(), true, array(
			'email_type' => 'assigned' === $action ? 'level_up' : 'level_expired',
			'user_id'    => $user_id,
			'related_id' => $level_id,
		) );
	}

	/**
	 * Obter template de email
	 *
	 * @param string $template_name Nome do template.
	 * @param array  $vars Variáveis para substituir.
	 * @return string
	 */
	private static function get_template( $template_name, $vars = array() ) {
		// Buscar template customizado
		$custom_template = get_option( "pcw_email_template_{$template_name}", '' );

		if ( ! empty( $custom_template ) ) {
			$template = $custom_template;
		} else {
			// Usar template padrão
			$template = self::get_default_template( $template_name );
		}

		// Substituir variáveis
		foreach ( $vars as $key => $value ) {
			$template = str_replace( '{' . $key . '}', $value, $template );
		}

		return $template;
	}

	/**
	 * Obter template padrão
	 *
	 * @param string $template_name Nome do template.
	 * @return string
	 */
	private static function get_default_template( $template_name ) {
		$templates = array(
			'cashback-earned' => __( 'Olá {user_name},

Parabéns! Você acabou de ganhar cashback na sua compra!

📦 Pedido: #{order_number}
💰 Valor do Cashback: {cashback_amount}
📅 Válido até: {expires_date}

Use seu cashback na sua próxima compra e economize ainda mais!

[Ver minha carteira] {wallet_link}

Atenciosamente,
Equipe {site_name}', 'person-cash-wallet' ),

			'cashback-expiring' => __( 'Olá {user_name},

Queremos avisar que você tem cashback que vai expirar em breve:

💰 Valor: {cashback_amount}
📅 Expira em: {expires_date}

Não deixe seu cashback expirar! Faça uma compra agora e use seu saldo.

[Ver produtos] {shop_link}
[Ver minha carteira] {wallet_link}

Atenciosamente,
Equipe {site_name}', 'person-cash-wallet' ),

			'cashback-expired' => __( 'Olá {user_name},

Infelizmente seu cashback de {cashback_amount} expirou.

Para evitar que isso aconteça novamente, aproveite seus cashbacks assim que recebê-los!

Atenciosamente,
Equipe {site_name}', 'person-cash-wallet' ),

			'level-updated' => __( 'Olá {user_name},

{message}

Atenciosamente,
Equipe {site_name}', 'person-cash-wallet' ),
		);

		return isset( $templates[ $template_name ] ) ? $templates[ $template_name ] : '';
	}
}
