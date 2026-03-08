<?php
/**
 * Classe para área Minha Conta - Indicações
 *
 * @package PersonCashWallet
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de Minha Conta - Indicações
 */
class PCW_Referral_My_Account {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Referral_My_Account
	 */
	private static $instance = null;

	/**
	 * Obter instância singleton
	 *
	 * @return PCW_Referral_My_Account
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

		// Adicionar endpoint
		add_action( 'init', array( $this, 'add_endpoint' ) );

		// Adicionar item no menu
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );

		// Conteúdo do endpoint
		add_action( 'woocommerce_account_indicacoes_endpoint', array( $this, 'render_content' ) );

		// AJAX para adicionar indicação
		add_action( 'wp_ajax_pcw_add_referral', array( $this, 'ajax_add_referral' ) );

		// Scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Adicionar endpoint
	 */
	public function add_endpoint() {
		add_rewrite_endpoint( 'indicacoes', EP_ROOT | EP_PAGES );
	}

	/**
	 * Adicionar item no menu
	 *
	 * @param array $items Itens do menu.
	 * @return array
	 */
	public function add_menu_item( $items ) {
		$new_items = array();

		foreach ( $items as $key => $value ) {
			$new_items[ $key ] = $value;

			// Adicionar após "downloads" ou antes de "customer-logout"
			if ( 'downloads' === $key || 'edit-account' === $key ) {
				$new_items['indicacoes'] = __( 'Minhas Indicações', 'person-cash-wallet' );
			}
		}

		// Se não encontrou posição, adicionar antes do logout
		if ( ! isset( $new_items['indicacoes'] ) ) {
			$logout = isset( $new_items['customer-logout'] ) ? $new_items['customer-logout'] : null;
			unset( $new_items['customer-logout'] );
			$new_items['indicacoes'] = __( 'Minhas Indicações', 'person-cash-wallet' );
			if ( $logout ) {
				$new_items['customer-logout'] = $logout;
			}
		}

		return $new_items;
	}

	/**
	 * Renderizar conteúdo
	 */
	public function render_content() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		// Obter dados do código
		$codes = PCW_Referral_Codes::instance();
		$code_data = $codes->get_or_create_code( $user_id );
		$stats = $codes->get_user_stats( $user_id );
		$level = $codes->get_referrer_level( $user_id );

		// Obter indicações
		$referrals = PCW_Referrals::instance();
		$user_referrals = $referrals->get_user_referrals( $user_id, array( 'limit' => 50 ) );

		// Obter links de compartilhamento
		$tracking = PCW_Referral_Tracking::instance();
		$share_links = $tracking->get_share_links( $code_data->code );
		$qr_code_url = $tracking->get_qr_code_url( $code_data->code, 200 );

		// Configurações de recompensa
		$reward_settings = PCW_Referral_Rewards::instance()->get_settings();
		$reward_text = 'percentage' === $reward_settings['reward_type']
			? $reward_settings['reward_amount'] . '%'
			: PCW_Formatters::format_money( $reward_settings['reward_amount'] );

		?>
		<div class="pcw-my-referrals">
			<!-- Cabeçalho com código e estatísticas -->
			<div class="pcw-referral-header">
				<div class="pcw-referral-code-card">
					<div class="pcw-code-section">
						<span class="pcw-label"><?php esc_html_e( 'Seu Código de Indicação', 'person-cash-wallet' ); ?></span>
						<div class="pcw-code-display">
							<span class="pcw-code" id="pcw-my-code"><?php echo esc_html( $code_data->code ); ?></span>
							<button type="button" class="pcw-copy-btn" data-copy="<?php echo esc_attr( $code_data->code ); ?>" title="<?php esc_attr_e( 'Copiar código', 'person-cash-wallet' ); ?>">
								<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
							</button>
						</div>
					</div>

					<div class="pcw-link-section">
						<span class="pcw-label"><?php esc_html_e( 'Seu Link de Indicação', 'person-cash-wallet' ); ?></span>
						<div class="pcw-link-display">
							<input type="text" readonly value="<?php echo esc_url( $stats['link'] ); ?>" id="pcw-my-link" class="pcw-link-input" />
							<button type="button" class="pcw-copy-btn" data-copy="<?php echo esc_url( $stats['link'] ); ?>" title="<?php esc_attr_e( 'Copiar link', 'person-cash-wallet' ); ?>">
								<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
							</button>
						</div>
					</div>

					<div class="pcw-reward-info">
						<span class="pcw-reward-icon">🎁</span>
						<span><?php printf( esc_html__( 'Você ganha %s para cada amigo que comprar!', 'person-cash-wallet' ), '<strong>' . esc_html( $reward_text ) . '</strong>' ); ?></span>
					</div>
				</div>

				<!-- Estatísticas -->
				<div class="pcw-referral-stats">
					<div class="pcw-stat-card">
						<span class="pcw-stat-value"><?php echo esc_html( $stats['total_referrals'] ); ?></span>
						<span class="pcw-stat-label"><?php esc_html_e( 'Indicados', 'person-cash-wallet' ); ?></span>
					</div>
					<div class="pcw-stat-card">
						<span class="pcw-stat-value"><?php echo esc_html( $stats['total_conversions'] ); ?></span>
						<span class="pcw-stat-label"><?php esc_html_e( 'Convertidos', 'person-cash-wallet' ); ?></span>
					</div>
					<div class="pcw-stat-card pcw-stat-highlight">
						<span class="pcw-stat-value"><?php echo wp_kses_post( PCW_Formatters::format_money( $stats['total_earned'] ) ); ?></span>
						<span class="pcw-stat-label"><?php esc_html_e( 'Total Ganho', 'person-cash-wallet' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Nível de Indicador -->
			<div class="pcw-referrer-level">
				<div class="pcw-level-header">
					<span class="pcw-level-icon"><?php echo esc_html( $level['current']['icon'] ); ?></span>
					<span class="pcw-level-name" style="color: <?php echo esc_attr( $level['current']['color'] ); ?>">
						<?php echo esc_html( $level['current']['name'] ); ?>
					</span>
				</div>
				<?php if ( $level['next'] ) : ?>
					<div class="pcw-level-progress">
						<div class="pcw-progress-bar">
							<div class="pcw-progress-fill" style="width: <?php echo esc_attr( $level['progress'] ); ?>%; background: <?php echo esc_attr( $level['next']['color'] ); ?>;"></div>
						</div>
						<span class="pcw-progress-text">
							<?php printf(
								esc_html__( 'Faltam %d conversões para %s %s', 'person-cash-wallet' ),
								$level['remaining'],
								$level['next']['icon'],
								$level['next']['name']
							); ?>
						</span>
					</div>
				<?php else : ?>
					<span class="pcw-level-max"><?php esc_html_e( 'Você atingiu o nível máximo! 🎉', 'person-cash-wallet' ); ?></span>
				<?php endif; ?>
			</div>

			<!-- Compartilhamento Social -->
			<div class="pcw-share-section">
				<h3><?php esc_html_e( 'Compartilhar', 'person-cash-wallet' ); ?></h3>
				<div class="pcw-share-buttons">
					<?php foreach ( $share_links as $key => $link ) : ?>
						<?php if ( 'copy' === $key ) continue; ?>
						<a href="<?php echo esc_url( $link['url'] ); ?>" 
						   class="pcw-share-btn pcw-share-<?php echo esc_attr( $key ); ?>" 
						   target="_blank" 
						   rel="noopener noreferrer"
						   style="background-color: <?php echo esc_attr( $link['color'] ); ?>">
							<?php echo esc_html( $link['name'] ); ?>
						</a>
					<?php endforeach; ?>
				</div>

				<!-- QR Code -->
				<div class="pcw-qr-section">
					<button type="button" class="pcw-qr-toggle" id="pcw-qr-toggle">
						<?php esc_html_e( 'Mostrar QR Code', 'person-cash-wallet' ); ?>
					</button>
					<div class="pcw-qr-code" id="pcw-qr-code" style="display: none;">
						<img src="<?php echo esc_url( $qr_code_url ); ?>" alt="QR Code" />
						<p class="pcw-qr-desc"><?php esc_html_e( 'Escaneie para acessar seu link de indicação', 'person-cash-wallet' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Formulário de Nova Indicação -->
			<div class="pcw-add-referral-section">
				<h3><?php esc_html_e( 'Adicionar Indicação', 'person-cash-wallet' ); ?></h3>
				<p class="pcw-section-desc"><?php esc_html_e( 'Cadastre manualmente os dados de quem você indicou:', 'person-cash-wallet' ); ?></p>

				<form id="pcw-add-referral-form" class="pcw-referral-form">
					<?php wp_nonce_field( 'pcw_add_referral', 'pcw_referral_nonce' ); ?>

					<div class="pcw-form-row">
						<div class="pcw-form-field">
							<label for="pcw_referred_name"><?php esc_html_e( 'Nome Completo', 'person-cash-wallet' ); ?> <span class="required">*</span></label>
							<input type="text" name="referred_name" id="pcw_referred_name" required />
						</div>
					</div>

					<div class="pcw-form-row pcw-form-row-2">
						<div class="pcw-form-field">
							<label for="pcw_referred_email"><?php esc_html_e( 'Email', 'person-cash-wallet' ); ?> <span class="required">*</span></label>
							<input type="email" name="referred_email" id="pcw_referred_email" required />
						</div>
						<div class="pcw-form-field">
							<label for="pcw_referred_phone"><?php esc_html_e( 'Telefone', 'person-cash-wallet' ); ?> <span class="required">*</span></label>
							<input type="tel" name="referred_phone" id="pcw_referred_phone" required placeholder="(00) 00000-0000" />
						</div>
					</div>

					<div class="pcw-form-row">
						<div class="pcw-form-field">
							<label for="pcw_referred_notes"><?php esc_html_e( 'Observações', 'person-cash-wallet' ); ?></label>
							<textarea name="notes" id="pcw_referred_notes" rows="2" placeholder="<?php esc_attr_e( 'Opcional: Como conhece essa pessoa?', 'person-cash-wallet' ); ?>"></textarea>
						</div>
					</div>

					<div class="pcw-form-actions">
						<button type="submit" class="button pcw-submit-btn">
							<?php esc_html_e( 'Adicionar Indicação', 'person-cash-wallet' ); ?>
						</button>
						<span class="pcw-form-message" id="pcw-form-message"></span>
					</div>
				</form>
			</div>

			<!-- Lista de Indicações -->
			<div class="pcw-referrals-list-section">
				<h3><?php esc_html_e( 'Suas Indicações', 'person-cash-wallet' ); ?></h3>

				<?php if ( empty( $user_referrals ) ) : ?>
					<div class="pcw-empty-state">
						<span class="pcw-empty-icon">👥</span>
						<p><?php esc_html_e( 'Você ainda não fez nenhuma indicação.', 'person-cash-wallet' ); ?></p>
						<p class="pcw-empty-hint"><?php esc_html_e( 'Compartilhe seu link ou adicione indicações acima!', 'person-cash-wallet' ); ?></p>
					</div>
				<?php else : ?>
					<div class="pcw-referrals-table-wrapper">
						<table class="pcw-referrals-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Nome', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Email', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Status', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Recompensa', 'person-cash-wallet' ); ?></th>
									<th><?php esc_html_e( 'Data', 'person-cash-wallet' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $user_referrals as $referral ) : ?>
									<tr>
										<td class="pcw-col-name"><?php echo esc_html( $referral->referred_name ); ?></td>
										<td class="pcw-col-email"><?php echo esc_html( $referral->referred_email ); ?></td>
										<td class="pcw-col-status">
											<?php echo wp_kses_post( $this->get_status_badge( $referral->status ) ); ?>
										</td>
										<td class="pcw-col-reward">
											<?php if ( $referral->reward_amount > 0 ) : ?>
												<span class="pcw-reward-earned"><?php echo wp_kses_post( PCW_Formatters::format_money( $referral->reward_amount ) ); ?></span>
											<?php else : ?>
												<span class="pcw-reward-pending">-</span>
											<?php endif; ?>
										</td>
										<td class="pcw-col-date">
											<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $referral->created_at ) ) ); ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<style>
			.pcw-my-referrals {
				max-width: 900px;
			}

			/* Cabeçalho */
			.pcw-referral-header {
				display: grid;
				grid-template-columns: 1fr;
				gap: 20px;
				margin-bottom: 30px;
			}

			@media (min-width: 768px) {
				.pcw-referral-header {
					grid-template-columns: 2fr 1fr;
				}
			}

			.pcw-referral-code-card {
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				color: white;
				padding: 25px;
				border-radius: 12px;
			}

			.pcw-label {
				display: block;
				font-size: 12px;
				text-transform: uppercase;
				letter-spacing: 1px;
				opacity: 0.8;
				margin-bottom: 8px;
			}

			.pcw-code-display {
				display: flex;
				align-items: center;
				gap: 10px;
				margin-bottom: 20px;
			}

			.pcw-code {
				font-size: 28px;
				font-weight: 700;
				letter-spacing: 3px;
				background: rgba(255,255,255,0.2);
				padding: 8px 16px;
				border-radius: 8px;
			}

			.pcw-copy-btn {
				background: rgba(255,255,255,0.2);
				border: none;
				padding: 10px;
				border-radius: 6px;
				cursor: pointer;
				color: white;
				transition: background 0.2s;
			}

			.pcw-copy-btn:hover {
				background: rgba(255,255,255,0.3);
			}

			.pcw-link-section {
				margin-bottom: 20px;
			}

			.pcw-link-display {
				display: flex;
				gap: 10px;
			}

			.pcw-link-input {
				flex: 1;
				background: rgba(255,255,255,0.2);
				border: none;
				padding: 10px 15px;
				border-radius: 6px;
				color: white;
				font-size: 13px;
			}

			.pcw-reward-info {
				display: flex;
				align-items: center;
				gap: 10px;
				background: rgba(255,255,255,0.15);
				padding: 12px 15px;
				border-radius: 8px;
				font-size: 14px;
			}

			.pcw-reward-icon {
				font-size: 20px;
			}

			/* Estatísticas */
			.pcw-referral-stats {
				display: flex;
				flex-direction: column;
				gap: 10px;
			}

			.pcw-stat-card {
				background: #f8f9fa;
				padding: 20px;
				border-radius: 10px;
				text-align: center;
			}

			.pcw-stat-value {
				display: block;
				font-size: 24px;
				font-weight: 700;
				color: #333;
			}

			.pcw-stat-label {
				font-size: 12px;
				color: #666;
				text-transform: uppercase;
				letter-spacing: 0.5px;
			}

			.pcw-stat-highlight {
				background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
			}

			.pcw-stat-highlight .pcw-stat-value {
				color: #059669;
			}

			/* Nível */
			.pcw-referrer-level {
				background: #f8f9fa;
				padding: 20px;
				border-radius: 10px;
				margin-bottom: 30px;
			}

			.pcw-level-header {
				display: flex;
				align-items: center;
				gap: 10px;
				margin-bottom: 15px;
			}

			.pcw-level-icon {
				font-size: 28px;
			}

			.pcw-level-name {
				font-size: 20px;
				font-weight: 700;
			}

			.pcw-progress-bar {
				height: 8px;
				background: #e5e7eb;
				border-radius: 4px;
				overflow: hidden;
				margin-bottom: 10px;
			}

			.pcw-progress-fill {
				height: 100%;
				border-radius: 4px;
				transition: width 0.3s ease;
			}

			.pcw-progress-text {
				font-size: 13px;
				color: #666;
			}

			.pcw-level-max {
				color: #059669;
				font-weight: 600;
			}

			/* Compartilhamento */
			.pcw-share-section {
				background: #f8f9fa;
				padding: 20px;
				border-radius: 10px;
				margin-bottom: 30px;
			}

			.pcw-share-section h3 {
				margin: 0 0 15px 0;
				font-size: 16px;
			}

			.pcw-share-buttons {
				display: flex;
				flex-wrap: wrap;
				gap: 10px;
				margin-bottom: 15px;
			}

			.pcw-share-btn {
				display: inline-flex;
				align-items: center;
				gap: 8px;
				padding: 10px 20px;
				border-radius: 6px;
				color: white;
				text-decoration: none;
				font-size: 14px;
				font-weight: 500;
				transition: opacity 0.2s;
			}

			.pcw-share-btn:hover {
				opacity: 0.9;
				color: white;
			}

			.pcw-qr-toggle {
				background: #e5e7eb;
				border: none;
				padding: 10px 20px;
				border-radius: 6px;
				cursor: pointer;
				font-size: 14px;
			}

			.pcw-qr-code {
				margin-top: 15px;
				text-align: center;
			}

			.pcw-qr-code img {
				max-width: 200px;
				border-radius: 10px;
			}

			.pcw-qr-desc {
				margin: 10px 0 0;
				font-size: 13px;
				color: #666;
			}

			/* Formulário */
			.pcw-add-referral-section {
				background: #f8f9fa;
				padding: 25px;
				border-radius: 10px;
				margin-bottom: 30px;
			}

			.pcw-add-referral-section h3 {
				margin: 0 0 5px 0;
				font-size: 18px;
			}

			.pcw-section-desc {
				margin: 0 0 20px 0;
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

			@media (max-width: 600px) {
				.pcw-form-row-2 {
					grid-template-columns: 1fr;
				}
			}

			.pcw-form-field label {
				display: block;
				font-size: 14px;
				font-weight: 500;
				margin-bottom: 5px;
			}

			.pcw-form-field input,
			.pcw-form-field textarea {
				width: 100%;
				padding: 10px 12px;
				border: 1px solid #d1d5db;
				border-radius: 6px;
				font-size: 14px;
			}

			.pcw-form-field input:focus,
			.pcw-form-field textarea:focus {
				border-color: #667eea;
				outline: none;
				box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
			}

			.pcw-form-actions {
				display: flex;
				align-items: center;
				gap: 15px;
			}

			.pcw-submit-btn {
				background: #667eea !important;
				color: white !important;
				border: none !important;
				padding: 12px 24px !important;
				border-radius: 6px !important;
				font-weight: 500 !important;
				cursor: pointer !important;
			}

			.pcw-submit-btn:hover {
				background: #5a6fd6 !important;
			}

			.pcw-form-message {
				font-size: 14px;
			}

			.pcw-form-message.success {
				color: #059669;
			}

			.pcw-form-message.error {
				color: #dc2626;
			}

			/* Tabela */
			.pcw-referrals-list-section h3 {
				margin: 0 0 15px 0;
				font-size: 18px;
			}

			.pcw-referrals-table-wrapper {
				overflow-x: auto;
			}

			.pcw-referrals-table {
				width: 100%;
				border-collapse: collapse;
			}

			.pcw-referrals-table th,
			.pcw-referrals-table td {
				padding: 12px 15px;
				text-align: left;
				border-bottom: 1px solid #e5e7eb;
			}

			.pcw-referrals-table th {
				background: #f8f9fa;
				font-weight: 600;
				font-size: 12px;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				color: #666;
			}

			.pcw-status-badge {
				display: inline-block;
				padding: 4px 10px;
				border-radius: 20px;
				font-size: 12px;
				font-weight: 500;
			}

			.pcw-status-pending {
				background: #fef3c7;
				color: #92400e;
			}

			.pcw-status-converted,
			.pcw-status-rewarded {
				background: #d1fae5;
				color: #065f46;
			}

			.pcw-status-expired {
				background: #fee2e2;
				color: #991b1b;
			}

			.pcw-reward-earned {
				color: #059669;
				font-weight: 600;
			}

			.pcw-reward-pending {
				color: #9ca3af;
			}

			/* Empty State */
			.pcw-empty-state {
				text-align: center;
				padding: 40px 20px;
				background: #f8f9fa;
				border-radius: 10px;
			}

			.pcw-empty-icon {
				font-size: 48px;
				display: block;
				margin-bottom: 15px;
			}

			.pcw-empty-state p {
				margin: 0 0 5px;
				color: #666;
			}

			.pcw-empty-hint {
				font-size: 14px;
				color: #9ca3af !important;
			}
		</style>
		<?php
	}

	/**
	 * Obter badge de status
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private function get_status_badge( $status ) {
		$labels = array(
			'pending'   => __( 'Pendente', 'person-cash-wallet' ),
			'converted' => __( 'Convertido', 'person-cash-wallet' ),
			'rewarded'  => __( 'Recompensado', 'person-cash-wallet' ),
			'expired'   => __( 'Expirado', 'person-cash-wallet' ),
			'cancelled' => __( 'Cancelado', 'person-cash-wallet' ),
		);

		$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;

		return sprintf(
			'<span class="pcw-status-badge pcw-status-%s">%s</span>',
			esc_attr( $status ),
			esc_html( $label )
		);
	}

	/**
	 * AJAX para adicionar indicação
	 */
	public function ajax_add_referral() {
		check_ajax_referer( 'pcw_add_referral', 'pcw_referral_nonce' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array(
				'message' => __( 'Você precisa estar logado.', 'person-cash-wallet' ),
			) );
		}

		$data = array(
			'referrer_user_id' => $user_id,
			'referred_name'    => isset( $_POST['referred_name'] ) ? sanitize_text_field( wp_unslash( $_POST['referred_name'] ) ) : '',
			'referred_email'   => isset( $_POST['referred_email'] ) ? sanitize_email( wp_unslash( $_POST['referred_email'] ) ) : '',
			'referred_phone'   => isset( $_POST['referred_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['referred_phone'] ) ) : '',
			'notes'            => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			'source'           => 'my_account',
			'ip_address'       => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
		);

		$result = PCW_Referrals::instance()->create_referral( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
			) );
		}

		wp_send_json_success( array(
			'message'     => __( 'Indicação adicionada com sucesso!', 'person-cash-wallet' ),
			'referral_id' => $result,
		) );
	}

	/**
	 * Enfileirar scripts
	 */
	public function enqueue_scripts() {
		if ( ! is_account_page() ) {
			return;
		}

		wp_enqueue_script(
			'pcw-my-account-referrals',
			PCW_PLUGIN_URL . 'assets/js/my-account-referrals.js',
			array( 'jquery' ),
			PCW_VERSION,
			true
		);

		wp_localize_script( 'pcw-my-account-referrals', 'pcwMyReferrals', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'copied'  => __( 'Copiado!', 'person-cash-wallet' ),
		) );
	}
}
