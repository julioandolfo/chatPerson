<?php
/**
 * Classe para disparar webhooks quando eventos ocorrem
 *
 * @package PersonCashWallet
 * @since 1.7.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de disparo de webhooks
 */
class PCW_Webhook_Dispatcher {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Webhook_Dispatcher
	 */
	private static $instance = null;

	/**
	 * Logger do WooCommerce
	 *
	 * @var WC_Logger
	 */
	private $logger = null;

	/**
	 * Contexto do log
	 *
	 * @var array
	 */
	private $log_context = array( 'source' => 'pcw-webhook-dispatcher' );

	/**
	 * Obter instância
	 *
	 * @return PCW_Webhook_Dispatcher
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
		if ( function_exists( 'wc_get_logger' ) ) {
			$this->logger = wc_get_logger();
		}
	}

	/**
	 * Inicializar hooks
	 */
	public function init() {
		$this->log( 'info', '━━━━━ PCW_Webhook_Dispatcher::init() chamado ━━━━━' );
		
		// Registrar hooks para todos os status de pedidos do WooCommerce
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 4 );
		
		$this->log( 'info', 'Hook woocommerce_order_status_changed registrado' );
		
		// Eventos de cashback
		add_action( 'pcw_cashback_created', array( $this, 'on_cashback_earned' ), 10, 4 );
		add_action( 'pcw_cashback_used', array( $this, 'on_cashback_redeemed' ), 10, 3 );
		
		// Eventos de usuário
		add_action( 'user_register', array( $this, 'on_user_registered' ), 10, 1 );
		
		// Eventos de nível
		add_action( 'pcw_level_assigned', array( $this, 'on_level_achieved' ), 10, 3 );

		// Eventos de tracking/rastreio - Melhor Envio (principal)
		add_action( 'wc_melhor_envio_new_tracking_code', array( $this, 'on_tracking_code_added' ), 10, 2 );
		
		// Hooks genéricos para quando tracking metas são adicionadas/atualizadas
		// CPT storage (post meta)
		add_action( 'added_post_meta', array( $this, 'on_post_meta_tracking_changed' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'on_post_meta_tracking_changed' ), 10, 4 );
		// HPOS storage (order meta)
		add_action( 'added_order_meta', array( $this, 'on_post_meta_tracking_changed' ), 10, 4 );
		add_action( 'updated_order_meta', array( $this, 'on_post_meta_tracking_changed' ), 10, 4 );

		// Handler para despacho diferido (Action Scheduler ou WP Cron)
		add_action( 'pcw_dispatch_tracking_webhook', array( $this, 'execute_tracking_webhook_dispatch' ), 10, 1 );
		
		$this->log( 'info', 'Todos os hooks registrados com sucesso (incluindo tracking diferido)' );
	}

	/**
	 * Log usando WC_Logger
	 *
	 * @param string $level Nível (debug, info, notice, warning, error, critical, alert, emergency).
	 * @param string $message Mensagem.
	 */
	private function log( $level, $message ) {
		if ( $this->logger ) {
			$this->logger->log( $level, $message, $this->log_context );
		}
		
		// Também salvar em arquivo local para debug
		$log_dir = PCW_PLUGIN_DIR . 'logs';
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}
		
		$log_file = $log_dir . '/webhook-dispatcher.log';
		$timestamp = current_time( 'Y-m-d H:i:s' );
		$log_entry = "[{$timestamp}] [{$level}] {$message}\n";
		
		@file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Handler: Status do pedido alterado
	 *
	 * @param int    $order_id ID do pedido.
	 * @param string $old_status Status antigo.
	 * @param string $new_status Novo status.
	 * @param object $order Objeto do pedido.
	 */
	public function on_order_status_changed( $order_id, $old_status, $new_status, $order = null ) {
		$this->log( 'info', "━━━━━ EVENTO: woocommerce_order_status_changed ━━━━━" );
		$this->log( 'info', "Pedido #{$order_id}: {$old_status} → {$new_status}" );
		
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
			$this->log( 'debug', "Pedido carregado via wc_get_order()" );
		}

		if ( ! $order ) {
			$this->log( 'error', "Erro: Pedido #{$order_id} não encontrado" );
			return;
		}

		// Evento no formato usado pelo admin: order_processing, order_completed, etc.
		$event = 'order_' . $new_status;
		
		// Verificar se já foi disparado para este status (evitar duplicatas)
		$dispatched_key = '_pcw_webhook_dispatched_' . $new_status;
		$already_dispatched = $order->get_meta( $dispatched_key );
		
		if ( $already_dispatched ) {
			$this->log( 'info', "⏭️ Webhook para '{$event}' já foi disparado anteriormente para este pedido. Ignorando." );
			$this->log( 'info', "   Disparado em: {$already_dispatched}" );
			return;
		}
		
		$this->log( 'info', "Buscando webhooks para evento: {$event}" );

		// Buscar webhooks ativos para este evento
		$webhooks = $this->get_active_webhooks( $event );
		
		$this->log( 'info', "Webhooks encontrados: " . count( $webhooks ) );

		if ( empty( $webhooks ) ) {
			$this->log( 'info', "Nenhum webhook ativo para o evento '{$event}'" );
			return;
		}

		// Marcar como disparado ANTES de processar (evitar race conditions)
		$order->update_meta_data( $dispatched_key, current_time( 'mysql' ) );
		$order->save();
		$this->log( 'info', "✓ Marcado como disparado: {$dispatched_key}" );

		// Preparar dados do pedido
		$data = $this->prepare_order_data( $order );
		$this->log( 'debug', "Dados do pedido preparados: " . wp_json_encode( array_keys( $data ) ) );

		// Disparar cada webhook
		foreach ( $webhooks as $webhook ) {
			$this->log( 'info', "Disparando webhook #{$webhook->id}: {$webhook->name}" );
			$this->dispatch_webhook( $webhook, $data );
		}
	}

	/**
	 * Handler: Código de rastreio adicionado (Melhor Envio)
	 *
	 * Dispara quando o Melhor Envio adiciona um código de rastreio ao pedido.
	 * O hook wc_melhor_envio_new_tracking_code dispara ANTES do $order->save() no Queue,
	 * então agendamos o despacho para depois que o pedido for salvo no banco.
	 *
	 * @param string   $tracking_code Código de rastreio.
	 * @param WC_Order $order Objeto do pedido.
	 */
	public function on_tracking_code_added( $tracking_code, $order ) {
		$order_id = $order->get_id();
		$this->log( 'info', "━━━━━ EVENTO: order_tracking_added (Melhor Envio) ━━━━━" );
		$this->log( 'info', "Pedido #{$order_id} - Código: {$tracking_code}" );

		$this->schedule_tracking_webhook_dispatch( $order_id );
	}

	/**
	 * Handler: Meta de tracking adicionada/atualizada
	 *
	 * Detecta quando metas de tracking são adicionadas ou atualizadas em pedidos.
	 * Funciona tanto com CPT (added_post_meta/updated_post_meta) quanto HPOS (added_order_meta/updated_order_meta).
	 * Ambos os hooks têm a mesma assinatura: ($meta_id, $object_id, $meta_key, $meta_value).
	 *
	 * @param int    $meta_id ID da meta.
	 * @param int    $object_id ID do pedido/post.
	 * @param string $meta_key Chave da meta.
	 * @param mixed  $meta_value Valor da meta.
	 */
	public function on_post_meta_tracking_changed( $meta_id, $object_id, $meta_key, $meta_value ) {
		// Lista de meta keys de tracking que devem disparar o evento
		// NÃO inclui _melhor_envio_tracking_codes pois já é tratado pelo hook específico
		static $tracking_meta_keys = array(
			'_tracking_code',
			'_tracking_url',
			'_tracking_link',
			'_correios_tracking_code',
			'_correios_tracking',
			'_rastreio',
			'_wc_shipment_tracking_items',
			'_aftership_tracking_number',
		);

		// Early return para meta keys que não são de tracking (performance)
		if ( ! in_array( $meta_key, $tracking_meta_keys, true ) ) {
			return;
		}

		// Verificar se é um pedido WooCommerce
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $object_id );
		if ( ! $order ) {
			return;
		}

		$this->log( 'info', "━━━━━ EVENTO: order_tracking_added (meta: {$meta_key}) ━━━━━" );
		$this->log( 'info', "Pedido #{$object_id}" );

		$this->schedule_tracking_webhook_dispatch( $object_id );
	}

	/**
	 * Agendar despacho diferido do webhook de tracking
	 *
	 * Em vez de disparar inline (durante hooks do Melhor Envio que ainda não salvaram o pedido),
	 * agendamos o despacho para rodar logo após o pedido ser salvo no banco.
	 * Isso garante que:
	 * 1. O pedido está completamente salvo no DB
	 * 2. Todos os tracking codes estão persistidos
	 * 3. A mensagem vai para a fila de mensagens com rate limiting
	 * 4. Números pausados/no limite são visíveis na fila
	 *
	 * @param int $order_id ID do pedido.
	 */
	private function schedule_tracking_webhook_dispatch( $order_id ) {
		// Debounce via transient (120 segundos) para evitar disparos duplicados
		$transient_key = 'pcw_tracking_webhook_' . $order_id;
		if ( get_transient( $transient_key ) ) {
			$this->log( 'debug', "Tracking webhook para pedido #{$order_id} já agendado recentemente. Debounce ativo." );
			return;
		}

		// Verificar se existem webhooks ativos para o evento antes de agendar
		$webhooks = $this->get_active_webhooks( 'order_tracking_added' );
		if ( empty( $webhooks ) ) {
			$this->log( 'info', "Nenhum webhook ativo para 'order_tracking_added' - não precisa agendar" );
			return;
		}

		// Marcar como agendado (transient expira em 120 segundos)
		set_transient( $transient_key, current_time( 'mysql' ), 120 );

		$this->log( 'info', "Agendando despacho de tracking webhook para pedido #{$order_id} ({$this->count_webhooks( $webhooks )} webhook(s))..." );

		// Usar Action Scheduler (WooCommerce) se disponível, senão WP Cron
		if ( function_exists( 'as_schedule_single_action' ) ) {
			// Action Scheduler: executa em ~15 segundos (tempo para o Melhor Envio salvar o pedido)
			$action_id = as_schedule_single_action(
				time() + 15,
				'pcw_dispatch_tracking_webhook',
				array( 'order_id' => $order_id ),
				'pcw-webhooks'
			);
			$this->log( 'info', "✓ Agendado via Action Scheduler (action ID: {$action_id}) - executa em ~15s" );
		} else {
			// Fallback: WP Cron (executa na próxima visita após o tempo agendado)
			$scheduled = wp_schedule_single_event(
				time() + 15,
				'pcw_dispatch_tracking_webhook',
				array( $order_id )
			);
			$this->log( 'info', "✓ Agendado via WP Cron - executa em ~15s (scheduled: " . ( $scheduled !== false ? 'ok' : 'falha' ) . ")" );
		}
	}

	/**
	 * Contar webhooks de forma segura
	 *
	 * @param array $webhooks Lista de webhooks.
	 * @return int
	 */
	private function count_webhooks( $webhooks ) {
		return is_array( $webhooks ) ? count( $webhooks ) : 0;
	}

	/**
	 * Executar despacho do webhook de tracking (chamado pelo Action Scheduler ou WP Cron)
	 *
	 * Carrega o pedido do banco de dados (já salvo) e despacha os webhooks.
	 * As mensagens são adicionadas à fila com rate limiting e distribuição de números.
	 *
	 * @param int $order_id ID do pedido.
	 */
	public function execute_tracking_webhook_dispatch( $order_id ) {
		$this->log( 'info', "━━━━━ EXECUTANDO: tracking webhook diferido para pedido #{$order_id} ━━━━━" );

		// Carregar pedido FRESCO do banco de dados (agora está salvo)
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$this->log( 'error', "Pedido #{$order_id} não encontrado no banco" );
			return;
		}

		// Buscar webhooks ativos
		$event = 'order_tracking_added';
		$webhooks = $this->get_active_webhooks( $event );

		if ( empty( $webhooks ) ) {
			$this->log( 'info', "Nenhum webhook ativo para '{$event}'" );
			return;
		}

		// Preparar dados do pedido (agora o tracking code está salvo no DB)
		$data = $this->prepare_order_data( $order );

		$this->log( 'info', "tracking_code: " . ( ! empty( $data['tracking_code'] ) ? $data['tracking_code'] : '(vazio)' ) );
		$this->log( 'info', "tracking_url: " . ( ! empty( $data['tracking_url'] ) ? $data['tracking_url'] : '(vazio)' ) );

		if ( empty( $data['tracking_code'] ) && empty( $data['tracking_url'] ) ) {
			$this->log( 'warning', "⚠ Tracking ainda vazio após despacho diferido. O pedido pode não ter código de rastreio." );
		}

		// Disparar cada webhook (vai para a fila de mensagens com rate limiting)
		foreach ( $webhooks as $webhook ) {
			$this->log( 'info', "Disparando webhook #{$webhook->id}: {$webhook->name} → fila de mensagens" );
			$this->dispatch_webhook( $webhook, $data );
		}

		$this->log( 'info', "✓ Tracking webhooks despachados para fila - pedido #{$order_id}" );
	}

	/**
	 * Handler: Cashback ganho
	 *
	 * @param int   $cashback_id ID do cashback.
	 * @param int   $user_id ID do usuário.
	 * @param int   $order_id ID do pedido.
	 * @param float $amount Valor.
	 */
	public function on_cashback_earned( $cashback_id, $user_id, $order_id, $amount ) {
		$this->log( 'info', "━━━━━ EVENTO: cashback_earned ━━━━━" );
		$this->log( 'info', "Cashback #{$cashback_id} - User #{$user_id} - Order #{$order_id} - R$ {$amount}" );
		
		$webhooks = $this->get_active_webhooks( 'cashback_earned' );
		
		if ( empty( $webhooks ) ) {
			$this->log( 'info', "Nenhum webhook ativo para cashback_earned" );
			return;
		}

		$user = get_userdata( $user_id );
		$order = wc_get_order( $order_id );
		
		$data = array(
			'cashback_id'          => $cashback_id,
			'user_id'              => $user_id,
			'order_id'             => $order_id,
			'amount'               => $amount,
			'customer_first_name'  => $user ? $user->first_name : '',
			'customer_name'        => $user ? $user->display_name : '',
			'customer_email'       => $user ? $user->user_email : '',
			'customer_phone'       => $order ? $order->get_billing_phone() : '',
			'order_number'         => $order ? $order->get_order_number() : $order_id,
			'order_total'          => $order ? wc_price( $order->get_total() ) : '',
			'site_name'            => get_bloginfo( 'name' ),
		);

		foreach ( $webhooks as $webhook ) {
			$this->dispatch_webhook( $webhook, $data );
		}
	}

	/**
	 * Handler: Cashback resgatado
	 *
	 * @param int   $user_id ID do usuário.
	 * @param float $amount Valor.
	 * @param int   $order_id ID do pedido.
	 */
	public function on_cashback_redeemed( $user_id, $amount, $order_id ) {
		$this->log( 'info', "━━━━━ EVENTO: cashback_redeemed ━━━━━" );
		
		$webhooks = $this->get_active_webhooks( 'cashback_redeemed' );
		
		if ( empty( $webhooks ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		$order = wc_get_order( $order_id );
		
		$data = array(
			'user_id'              => $user_id,
			'order_id'             => $order_id,
			'amount'               => $amount,
			'customer_first_name'  => $user ? $user->first_name : '',
			'customer_name'        => $user ? $user->display_name : '',
			'customer_email'       => $user ? $user->user_email : '',
			'customer_phone'       => $order ? $order->get_billing_phone() : '',
			'order_number'         => $order ? $order->get_order_number() : $order_id,
			'site_name'            => get_bloginfo( 'name' ),
		);

		foreach ( $webhooks as $webhook ) {
			$this->dispatch_webhook( $webhook, $data );
		}
	}

	/**
	 * Handler: Usuário registrado
	 *
	 * @param int $user_id ID do usuário.
	 */
	public function on_user_registered( $user_id ) {
		$this->log( 'info', "━━━━━ EVENTO: user_registered ━━━━━" );
		
		$webhooks = $this->get_active_webhooks( 'user_registered' );
		
		if ( empty( $webhooks ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		
		$data = array(
			'user_id'              => $user_id,
			'customer_first_name'  => $user ? $user->first_name : '',
			'customer_name'        => $user ? $user->display_name : '',
			'customer_email'       => $user ? $user->user_email : '',
			'customer_phone'       => get_user_meta( $user_id, 'billing_phone', true ),
			'site_name'            => get_bloginfo( 'name' ),
		);

		foreach ( $webhooks as $webhook ) {
			$this->dispatch_webhook( $webhook, $data );
		}
	}

	/**
	 * Handler: Nível alcançado
	 *
	 * @param int $user_id ID do usuário.
	 * @param int $level_id ID do nível.
	 * @param int $user_level_id ID do registro.
	 */
	public function on_level_achieved( $user_id, $level_id, $user_level_id ) {
		$this->log( 'info', "━━━━━ EVENTO: level_achieved ━━━━━" );
		
		$webhooks = $this->get_active_webhooks( 'level_achieved' );
		
		if ( empty( $webhooks ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		$level = class_exists( 'PCW_Levels' ) ? PCW_Levels::get_level( $level_id ) : null;
		
		$data = array(
			'user_id'              => $user_id,
			'level_id'             => $level_id,
			'level_name'           => $level ? $level->name : '',
			'customer_first_name'  => $user ? $user->first_name : '',
			'customer_name'        => $user ? $user->display_name : '',
			'customer_email'       => $user ? $user->user_email : '',
			'customer_phone'       => get_user_meta( $user_id, 'billing_phone', true ),
			'site_name'            => get_bloginfo( 'name' ),
		);

		foreach ( $webhooks as $webhook ) {
			$this->dispatch_webhook( $webhook, $data );
		}
	}

	/**
	 * Buscar webhooks ativos para um evento
	 *
	 * @param string $event Evento.
	 * @return array
	 */
	private function get_active_webhooks( $event ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_webhooks';

		// Verificar se tabela existe
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
		if ( ! $table_exists ) {
			$this->log( 'warning', "Tabela {$table} não existe" );
			return array();
		}

		// Verificar estrutura da tabela
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
		$this->log( 'debug', "Colunas da tabela: " . implode( ', ', $columns ) );

		// Verificar se a coluna 'event' existe
		if ( ! in_array( 'event', $columns, true ) ) {
			$this->log( 'error', "Coluna 'event' não existe na tabela. Estrutura desatualizada?" );
			return array();
		}

		$query = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = 'active' AND event = %s",
			$event
		);

		$this->log( 'debug', "Query: {$query}" );

		$webhooks = $wpdb->get_results( $query );

		if ( $wpdb->last_error ) {
			$this->log( 'error', "Erro SQL: " . $wpdb->last_error );
		}

		$count = $webhooks ? count( $webhooks ) : 0;
		$this->log( 'info', "Webhooks encontrados para '{$event}': {$count}" );

		// Listar todos os webhooks para debug
		if ( $count === 0 ) {
			$all_webhooks = $wpdb->get_results( "SELECT id, name, event, status FROM {$table}" );
			if ( $all_webhooks ) {
				$this->log( 'debug', "Todos os webhooks cadastrados:" );
				foreach ( $all_webhooks as $wh ) {
					$this->log( 'debug', "  - #{$wh->id} '{$wh->name}' | evento: '{$wh->event}' | status: {$wh->status}" );
				}
			} else {
				$this->log( 'debug', "Nenhum webhook cadastrado na tabela" );
			}
		}

		return $webhooks ? $webhooks : array();
	}

	/**
	 * Preparar dados do pedido
	 *
	 * @param WC_Order $order Pedido.
	 * @return array
	 */
	private function prepare_order_data( $order ) {
		$user_id = $order->get_user_id();
		$user = $user_id ? get_userdata( $user_id ) : null;

		// Link de pagamento (WC Advanced Manual Orders)
		$payment_link = $order->get_meta( 'manual_order_auto_login_url' );
		if ( empty( $payment_link ) ) {
			// Fallback para URL padrão de pagamento do WooCommerce
			$payment_link = $order->get_checkout_payment_url();
		}

		// Códigos de rastreio (Melhor Envio e outros plugins)
		$tracking_codes = $this->get_order_tracking_codes( $order );
		$tracking_urls  = $this->get_order_tracking_urls( $order );

		$this->log( 'info', "Pedido #{$order->get_id()} - Tracking codes: " . ( ! empty( $tracking_codes ) ? implode( ', ', $tracking_codes ) : '(nenhum)' ) );
		$this->log( 'info', "Pedido #{$order->get_id()} - Tracking URLs: " . ( ! empty( $tracking_urls ) ? implode( ', ', $tracking_urls ) : '(nenhuma)' ) );

		// Produtos do pedido
		$products_list = $this->get_order_products_list( $order );

		// Datas de entrega (WC Advanced Manual Orders)
		$departure_date = $order->get_meta( '_departure_date' );
		$delivery_date  = $order->get_meta( '_delivery_date' );

		// Observações do orçamento
		$budget_notes = $order->get_meta( '_budget_notes' );

		// Método de envio
		$shipping_method = '';
		$shipping_methods = $order->get_shipping_methods();
		if ( ! empty( $shipping_methods ) ) {
			$shipping = current( $shipping_methods );
			$shipping_method = $shipping->get_method_title();
		}

		// Desconto
		$discount_total = $order->get_discount_total();

		// Cupons
		$coupons = $order->get_coupon_codes();
		$coupons_list = ! empty( $coupons ) ? implode( ', ', $coupons ) : '';

		// Dados básicos do pedido
		$data = array(
			// Identificação
			'order_id'             => $order->get_id(),
			'order_number'         => $order->get_order_number(),
			
			// Valores
			'order_total'          => $this->format_price( $order->get_total() ),
			'order_total_raw'      => $order->get_total(),
			'order_subtotal'       => $this->format_price( $order->get_subtotal() ),
			'order_subtotal_raw'   => $order->get_subtotal(),
			'order_discount'       => $this->format_price( $discount_total ),
			'order_discount_raw'   => $discount_total,
			'order_shipping'       => $this->format_price( $order->get_shipping_total() ),
			'order_shipping_raw'   => $order->get_shipping_total(),
			
			// Status
			'order_status'         => $order->get_status(),
			'order_status_label'   => wc_get_order_status_name( $order->get_status() ),
			
			// Pagamento
			'payment_method'       => $order->get_payment_method_title(),
			'payment_link'         => $payment_link,
			'payment_url'          => $payment_link, // Alias
			
			// Datas
			'order_date'           => $order->get_date_created() ? $order->get_date_created()->date( 'd/m/Y H:i' ) : '',
			'order_date_short'     => $order->get_date_created() ? $order->get_date_created()->date( 'd/m/Y' ) : '',
			'departure_date'       => $departure_date ? date( 'd/m/Y', strtotime( $departure_date ) ) : '',
			'delivery_date'        => $delivery_date ? date( 'd/m/Y', strtotime( $delivery_date ) ) : '',
			
			// Envio/Rastreio
			'shipping_method'      => $shipping_method,
			'tracking_code'        => ! empty( $tracking_codes ) ? $tracking_codes[0] : '',
			'tracking_codes'       => implode( ', ', $tracking_codes ),
			'tracking_url'         => ! empty( $tracking_urls ) ? $tracking_urls[0] : '',
			'tracking_urls'        => implode( "\n", $tracking_urls ),
			
			// Produtos
			'products_list'        => $products_list,
			
			// Cupons
			'coupons'              => $coupons_list,
			
			// Cliente
			'user_id'              => $user_id,
			'customer_first_name'  => $order->get_billing_first_name(),
			'customer_name'        => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'customer_email'       => $order->get_billing_email(),
			'customer_phone'       => $order->get_billing_phone(),
			
			// Endereços
			'billing_address'      => wp_strip_all_tags( $order->get_formatted_billing_address() ),
			'shipping_address'     => wp_strip_all_tags( $order->get_formatted_shipping_address() ),
			
			// Observações
			'order_notes'          => $order->get_customer_note(),
			'budget_notes'         => $budget_notes,
			
			// Site
			'site_name'            => get_bloginfo( 'name' ),
			'site_url'             => home_url(),
		);

		// Permitir extensão via filtro
		return apply_filters( 'pcw_webhook_order_data', $data, $order );
	}

	/**
	 * Obter códigos de rastreio do pedido
	 *
	 * @param WC_Order $order Pedido.
	 * @return array
	 */
	private function get_order_tracking_codes( $order ) {
		$tracking_codes = array();
		$order_id = $order->get_id();

		$this->log( 'debug', "━━ Buscando tracking codes para pedido #{$order_id} ━━" );

		// Melhor Envio
		if ( function_exists( 'wc_melhor_envio_get_tracking_codes' ) ) {
			$me_codes = wc_melhor_envio_get_tracking_codes( $order );
			$this->log( 'debug', "Melhor Envio (função): " . wp_json_encode( $me_codes ) );
			if ( is_array( $me_codes ) && ! empty( $me_codes ) ) {
				$tracking_codes = array_merge( $tracking_codes, $me_codes );
			}
		} else {
			// Fallback: tentar obter diretamente da meta
			$me_codes = $order->get_meta( '_melhor_envio_tracking_codes' );
			$this->log( 'debug', "Melhor Envio (meta fallback): " . wp_json_encode( $me_codes ) );
			if ( ! empty( $me_codes ) ) {
				if ( is_array( $me_codes ) ) {
					$tracking_codes = array_merge( $tracking_codes, $me_codes );
				} else {
					$tracking_codes[] = $me_codes;
				}
			}
		}

		// Correios (outros plugins podem usar essa meta)
		$correios_code = $order->get_meta( '_correios_tracking_code' );
		if ( ! empty( $correios_code ) ) {
			$this->log( 'debug', "Correios: " . wp_json_encode( $correios_code ) );
			if ( is_array( $correios_code ) ) {
				$tracking_codes = array_merge( $tracking_codes, $correios_code );
			} else {
				$tracking_codes[] = $correios_code;
			}
		}

		// WooCommerce Shipment Tracking
		$shipment_tracking = $order->get_meta( '_wc_shipment_tracking_items' );
		if ( ! empty( $shipment_tracking ) && is_array( $shipment_tracking ) ) {
			$this->log( 'debug', "WC Shipment Tracking: " . wp_json_encode( $shipment_tracking ) );
			foreach ( $shipment_tracking as $item ) {
				if ( isset( $item['tracking_number'] ) ) {
					$tracking_codes[] = $item['tracking_number'];
				}
			}
		}

		// Advanced Shipment Tracking (fallback via post_meta)
		if ( empty( $shipment_tracking ) ) {
			$ast_tracking = get_post_meta( $order->get_id(), '_wc_shipment_tracking_items', true );
			if ( ! empty( $ast_tracking ) && is_array( $ast_tracking ) ) {
				$this->log( 'debug', "AST (post_meta): " . wp_json_encode( $ast_tracking ) );
				foreach ( $ast_tracking as $item ) {
					if ( isset( $item['tracking_number'] ) ) {
						$tracking_codes[] = $item['tracking_number'];
					}
				}
			}
		}

		// Jadlog
		$jadlog_code = $order->get_meta( '_jadlog_tracking_code' );
		if ( ! empty( $jadlog_code ) ) {
			$this->log( 'debug', "Jadlog: {$jadlog_code}" );
			$tracking_codes[] = $jadlog_code;
		}

		// Frenet
		$frenet_code = $order->get_meta( '_frenet_tracking_number' );
		if ( ! empty( $frenet_code ) ) {
			$this->log( 'debug', "Frenet: {$frenet_code}" );
			$tracking_codes[] = $frenet_code;
		}

		// AfterShip
		$aftership_code = $order->get_meta( '_aftership_tracking_number' );
		if ( ! empty( $aftership_code ) ) {
			$this->log( 'debug', "AfterShip: {$aftership_code}" );
			$tracking_codes[] = $aftership_code;
		}

		// Rastreio Correios (plugin brasileiro)
		$rastreio_code = $order->get_meta( '_rastreio' );
		if ( ! empty( $rastreio_code ) ) {
			$this->log( 'debug', "Rastreio Correios: {$rastreio_code}" );
			$tracking_codes[] = $rastreio_code;
		}

		// WooCommerce Correios (Claudio Sanches)
		$cs_correios = $order->get_meta( '_correios_tracking' );
		if ( ! empty( $cs_correios ) ) {
			$this->log( 'debug', "WC Correios (CS): " . wp_json_encode( $cs_correios ) );
			if ( is_array( $cs_correios ) ) {
				$tracking_codes = array_merge( $tracking_codes, $cs_correios );
			} else {
				$tracking_codes[] = $cs_correios;
			}
		}

		// Genérico: meta comum _tracking_code
		$generic_code = $order->get_meta( '_tracking_code' );
		if ( ! empty( $generic_code ) ) {
			$this->log( 'debug', "Genérico (_tracking_code): " . wp_json_encode( $generic_code ) );
			if ( is_array( $generic_code ) ) {
				$tracking_codes = array_merge( $tracking_codes, $generic_code );
			} else {
				$tracking_codes[] = $generic_code;
			}
		}

		// Genérico: _order_tracking (alguns plugins)
		$order_tracking = $order->get_meta( '_order_tracking' );
		if ( ! empty( $order_tracking ) ) {
			$this->log( 'debug', "Genérico (_order_tracking): " . wp_json_encode( $order_tracking ) );
			if ( is_array( $order_tracking ) ) {
				$tracking_codes = array_merge( $tracking_codes, $order_tracking );
			} else {
				$tracking_codes[] = $order_tracking;
			}
		}

		// Normalizar: converter para string, remover espaços, filtrar valores inválidos
		$tracking_codes = array_map(
			function ( $code ) {
				// Garantir que é string escalar
				if ( is_array( $code ) || is_object( $code ) ) {
					return '';
				}
				return trim( (string) $code );
			},
			$tracking_codes
		);

		// Remover duplicados e valores vazios/inválidos (menos de 4 caracteres = provável lixo)
		$tracking_codes = array_values(
			array_unique(
				array_filter(
					$tracking_codes,
					function ( $code ) {
						return strlen( $code ) >= 4;
					}
				)
			)
		);

		$this->log( 'debug', "Total de tracking codes encontrados: " . count( $tracking_codes ) );
		if ( ! empty( $tracking_codes ) ) {
			$this->log( 'info', "Tracking codes: " . implode( ', ', $tracking_codes ) );
		} else {
			$this->log( 'warning', "⚠ Nenhum tracking code encontrado para pedido #{$order_id}. Verifique se o código de rastreio foi adicionado ao pedido antes da mudança de status." );
		}

		// Permitir extensão via filtro
		return apply_filters( 'pcw_webhook_tracking_codes', $tracking_codes, $order );
	}

	/**
	 * Obter URLs de rastreio do pedido
	 *
	 * @param WC_Order $order Pedido.
	 * @return array
	 */
	private function get_order_tracking_urls( $order ) {
		$tracking_urls = array();
		$order_id = $order->get_id();

		$this->log( 'debug', "━━ Buscando tracking URLs para pedido #{$order_id} ━━" );

		// 1. Verificar URLs diretas primeiro (metas com URL completa)
		$direct_url = $order->get_meta( '_tracking_url' );
		if ( ! empty( $direct_url ) ) {
			$this->log( 'debug', "URL direta (_tracking_url): {$direct_url}" );
			$tracking_urls[] = $direct_url;
		}

		$direct_link = $order->get_meta( '_tracking_link' );
		if ( ! empty( $direct_link ) ) {
			$this->log( 'debug', "URL direta (_tracking_link): {$direct_link}" );
			$tracking_urls[] = $direct_link;
		}

		// WooCommerce Shipment Tracking - pode ter URL
		$shipment_tracking = $order->get_meta( '_wc_shipment_tracking_items' );
		if ( ! empty( $shipment_tracking ) && is_array( $shipment_tracking ) ) {
			foreach ( $shipment_tracking as $item ) {
				if ( isset( $item['custom_tracking_link'] ) && ! empty( $item['custom_tracking_link'] ) ) {
					$this->log( 'debug', "WC Shipment Tracking URL: {$item['custom_tracking_link']}" );
					$tracking_urls[] = $item['custom_tracking_link'];
				}
				// Advanced Shipment Tracking - Construir URL baseada no provider
				if ( isset( $item['tracking_provider'] ) && isset( $item['tracking_number'] ) ) {
					$url = $this->get_tracking_url_by_provider( $item['tracking_provider'], $item['tracking_number'] );
					if ( ! empty( $url ) ) {
						$this->log( 'debug', "AST URL ({$item['tracking_provider']}): {$url}" );
						$tracking_urls[] = $url;
					}
				}
			}
		}

		// 2. Se não encontrou URLs diretas, gerar a partir dos códigos
		if ( empty( $tracking_urls ) ) {
			$this->log( 'debug', "Nenhuma URL direta encontrada, gerando a partir dos códigos de rastreio..." );
			$tracking_codes = $this->get_order_tracking_codes( $order );

			foreach ( $tracking_codes as $code ) {
				// Melhor Envio
				if ( function_exists( 'wc_melhor_envio_get_tracking_url' ) ) {
					$url = wc_melhor_envio_get_tracking_url( $code );
					$this->log( 'debug', "URL gerada (Melhor Envio): {$url}" );
					$tracking_urls[] = $url;
				} else {
					// Fallback: URL padrão do Melhor Rastreio
					$url = 'https://www.melhorrastreio.com.br/meu-rastreio/' . $code;
					$this->log( 'debug', "URL gerada (Melhor Rastreio fallback): {$url}" );
					$tracking_urls[] = $url;
				}
			}
		}

		// Remover duplicados e valores vazios
		$tracking_urls = array_unique( array_filter( $tracking_urls ) );

		$this->log( 'debug', "Total de tracking URLs: " . count( $tracking_urls ) );
		if ( ! empty( $tracking_urls ) ) {
			$this->log( 'info', "Tracking URLs: " . implode( ', ', $tracking_urls ) );
		} else {
			$this->log( 'warning', "⚠ Nenhuma tracking URL encontrada para pedido #{$order_id}. Use o evento 'order_tracking_added' para disparar quando o rastreio estiver disponível." );
		}

		// Permitir extensão via filtro
		return apply_filters( 'pcw_webhook_tracking_urls', $tracking_urls, $order );
	}

	/**
	 * Obter URL de rastreio baseada no provider
	 *
	 * @param string $provider Nome do provider.
	 * @param string $tracking_number Código de rastreio.
	 * @return string
	 */
	private function get_tracking_url_by_provider( $provider, $tracking_number ) {
		$provider_lower = strtolower( $provider );

		$providers = array(
			'correios'      => 'https://www.linkcorreios.com.br/?id=' . $tracking_number,
			'jadlog'        => 'https://www.jadlog.com.br/siteInstitucional/tracking.jad?cte=' . $tracking_number,
			'sedex'         => 'https://www.linkcorreios.com.br/?id=' . $tracking_number,
			'pac'           => 'https://www.linkcorreios.com.br/?id=' . $tracking_number,
			'melhor-envio'  => 'https://www.melhorrastreio.com.br/meu-rastreio/' . $tracking_number,
			'melhor_envio'  => 'https://www.melhorrastreio.com.br/meu-rastreio/' . $tracking_number,
			'loggi'         => 'https://www.loggi.com/rastrear/' . $tracking_number,
			'total-express' => 'https://tracking.totalexpress.com.br/poupup_track.php?reid=' . $tracking_number,
		);

		foreach ( $providers as $key => $url ) {
			if ( strpos( $provider_lower, $key ) !== false ) {
				return $url;
			}
		}

		// Fallback: Melhor Rastreio (aceita vários tipos)
		return 'https://www.melhorrastreio.com.br/meu-rastreio/' . $tracking_number;
	}

	/**
	 * Obter lista de produtos do pedido formatada
	 *
	 * @param WC_Order $order Pedido.
	 * @return string
	 */
	private function get_order_products_list( $order ) {
		$products = array();

		foreach ( $order->get_items() as $item ) {
			$product_name = $item->get_name();
			$quantity     = $item->get_quantity();
			$total        = $this->format_price( $item->get_total() );

			$products[] = "• {$product_name} (x{$quantity}) - {$total}";
		}

		return implode( "\n", $products );
	}

	/**
	 * Formatar preço sem HTML
	 *
	 * @param float $price Preço.
	 * @return string Preço formatado (ex: R$ 149,90).
	 */
	private function format_price( $price ) {
		return 'R$ ' . number_format( floatval( $price ), 2, ',', '.' );
	}

	/**
	 * Disparar webhook
	 *
	 * @param object $webhook Webhook.
	 * @param array  $data Dados.
	 */
	private function dispatch_webhook( $webhook, $data ) {
		$this->log( 'info', "Processando webhook #{$webhook->id} ({$webhook->type})" );
		
		try {
			if ( $webhook->type === 'personizi_whatsapp' ) {
				$this->dispatch_personizi_whatsapp( $webhook, $data );
			} else {
				$this->dispatch_custom_webhook( $webhook, $data );
			}
		} catch ( Exception $e ) {
			$this->log( 'error', "Exceção ao disparar webhook: " . $e->getMessage() );
		}
	}

	/**
	 * Disparar webhook via Personizi WhatsApp
	 *
	 * @param object $webhook Webhook.
	 * @param array  $data Dados.
	 */
	private function dispatch_personizi_whatsapp( $webhook, $data ) {
		$this->log( 'info', "Tipo: Personizi WhatsApp" );
		
		// Obter telefone do cliente
		$phone = isset( $data['customer_phone'] ) ? $data['customer_phone'] : '';
		
		if ( empty( $phone ) ) {
			$this->log( 'warning', "Telefone não disponível para o cliente" );
			return;
		}

		// Normalizar telefone para formato internacional (apenas números com código do país)
		$phone = $this->normalize_phone( $phone );
		
		if ( empty( $phone ) ) {
			$this->log( 'warning', "Telefone inválido após normalização" );
			return;
		}

		// Selecionar template: usar "sem rastreio" quando não há código e o template alternativo está definido
		$has_tracking          = ! empty( $data['tracking_code'] );
		$no_tracking_template  = isset( $webhook->body_template_no_tracking ) ? $webhook->body_template_no_tracking : '';
		$template              = $webhook->body_template;

		if ( ! $has_tracking && ! empty( $no_tracking_template ) ) {
			$template = $no_tracking_template;
			$this->log( 'info', "Usando template alternativo (sem código de rastreio)" );
		} elseif ( ! $has_tracking ) {
			$this->log( 'info', "Sem código de rastreio — usando template principal (sem alternativo configurado)" );
		}

		// Processar template da mensagem
		$message = $this->replace_variables( $template, $data );
		
		$this->log( 'info', "Telefone: {$phone}" );
		$this->log( 'info', "Mensagem: " . substr( $message, 0, 100 ) . '...' );

		// Obter número de origem dos headers
		$from = '';
		if ( ! empty( $webhook->headers ) ) {
			$headers_data = json_decode( $webhook->headers, true );
			if ( isset( $headers_data['from'] ) ) {
				$from = $headers_data['from'];
			}
		}

		// Verificar se Personizi está disponível
		if ( ! class_exists( 'PCW_Personizi_Integration' ) ) {
			$this->log( 'error', "PCW_Personizi_Integration não disponível" );
			return;
		}

		$personizi = PCW_Personizi_Integration::instance();

		// Sempre usar a fila quando disponível (a fila controla pausas e rate limits)
		if ( class_exists( 'PCW_Message_Queue_Manager' ) ) {
			$queue = PCW_Message_Queue_Manager::instance();
			
			// IMPORTANTE: Deixar from_number como null para que a fila use a estratégia de distribuição
			$queue_args = array(
				'type'         => 'whatsapp',
				'to_number'    => $phone,
				'from_number'  => null,
				'message'      => $message,
				'contact_name' => isset( $data['customer_name'] ) ? $data['customer_name'] : null,
				'webhook_id'   => $webhook->id,
				'priority'     => 5,
			);
			
			$result = $queue->add_to_queue( $queue_args );
			
			if ( $result ) {
				$this->log( 'info', "✓ Mensagem adicionada à fila (ID: {$result})" );
			} else {
				$this->log( 'error', "✗ Erro ao adicionar mensagem à fila para {$phone}" );
			}
		} else {
			// Enviar diretamente apenas se a fila não está disponível
			$this->send_direct( $personizi, $phone, $message, $webhook->name, $from );
		}
	}

	/**
	 * Enviar mensagem diretamente via Personizi
	 *
	 * @param object $personizi Instância do Personizi.
	 * @param string $phone Telefone.
	 * @param string $message Mensagem.
	 * @param string $webhook_name Nome do webhook.
	 * @param string $from Número de origem.
	 */
	private function send_direct( $personizi, $phone, $message, $webhook_name, $from = '' ) {
		$this->log( 'info', "Enviando diretamente via Personizi" );
		$result = $personizi->send_whatsapp_message( $phone, $message, 'Webhook: ' . $webhook_name, $from );
		
		if ( is_wp_error( $result ) ) {
			$this->log( 'error', "✗ Erro ao enviar: " . $result->get_error_message() );
		} else {
			$this->log( 'info', "✓ Mensagem enviada com sucesso!" );
		}
	}

	/**
	 * Normalizar telefone para formato internacional
	 *
	 * @param string $phone Telefone em qualquer formato.
	 * @return string Telefone normalizado (apenas números com código do país).
	 */
	private function normalize_phone( $phone ) {
		// Remover tudo que não for número
		$phone = preg_replace( '/[^0-9]/', '', $phone );
		
		if ( empty( $phone ) ) {
			return '';
		}
		
		// Se começa com 0, remover
		$phone = ltrim( $phone, '0' );
		
		// Se tem menos de 10 dígitos, inválido
		if ( strlen( $phone ) < 10 ) {
			return '';
		}
		
		// Se não começa com 55 (Brasil), adicionar
		if ( substr( $phone, 0, 2 ) !== '55' ) {
			$phone = '55' . $phone;
		}
		
		return $phone;
	}

	/**
	 * Disparar webhook customizado
	 *
	 * @param object $webhook Webhook.
	 * @param array  $data Dados.
	 */
	private function dispatch_custom_webhook( $webhook, $data ) {
		$this->log( 'info', "Tipo: Custom Webhook" );
		$this->log( 'info', "URL: {$webhook->url}" );
		$this->log( 'info', "Método: {$webhook->method}" );

		// Preparar headers
		$headers = array(
			'Content-Type' => 'application/json',
		);

		// Adicionar autenticação
		if ( $webhook->auth_type === 'bearer' && ! empty( $webhook->auth_token ) ) {
			$headers['Authorization'] = 'Bearer ' . $webhook->auth_token;
		}

		// Selecionar template: usar "sem rastreio" quando não há código e o template alternativo está definido
		$has_tracking         = ! empty( $data['tracking_code'] );
		$no_tracking_template = isset( $webhook->body_template_no_tracking ) ? $webhook->body_template_no_tracking : '';
		$active_template      = $webhook->body_template;

		if ( ! $has_tracking && ! empty( $no_tracking_template ) ) {
			$active_template = $no_tracking_template;
			$this->log( 'info', "Usando template alternativo (sem código de rastreio)" );
		}

		// Preparar body
		$body = array();
		if ( ! empty( $active_template ) ) {
			$body_string = $this->replace_variables( $active_template, $data );
			$body = json_decode( $body_string, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				// Se não for JSON válido, enviar como está
				$body = $data;
			}
		} else {
			$body = $data;
		}

		// Enviar requisição
		$args = array(
			'method'  => $webhook->method,
			'headers' => $headers,
			'timeout' => 30,
			'body'    => wp_json_encode( $body ),
		);

		$response = wp_remote_request( $webhook->url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( 'error', "✗ Erro: " . $response->get_error_message() );
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			$this->log( 'info', "✓ Resposta HTTP: {$code}" );
		}
	}

	/**
	 * Substituir variáveis no template
	 *
	 * @param string $template Template.
	 * @param array  $data Dados.
	 * @return string
	 */
	private function replace_variables( $template, $data ) {
		if ( empty( $template ) ) {
			return '';
		}

		// Mapear variáveis - todas as disponíveis
		$replacements = array(
			// Cliente
			'{{customer_first_name}}'  => isset( $data['customer_first_name'] ) ? $data['customer_first_name'] : '',
			'{{customer_name}}'        => isset( $data['customer_name'] ) ? $data['customer_name'] : '',
			'{{customer_email}}'       => isset( $data['customer_email'] ) ? $data['customer_email'] : '',
			'{{customer_phone}}'       => isset( $data['customer_phone'] ) ? $data['customer_phone'] : '',
			'{{user_id}}'              => isset( $data['user_id'] ) ? $data['user_id'] : '',
			
			// Pedido - Identificação
			'{{order_number}}'         => isset( $data['order_number'] ) ? $data['order_number'] : '',
			'{{order_id}}'             => isset( $data['order_id'] ) ? $data['order_id'] : '',
			
			// Pedido - Valores
			'{{order_total}}'          => isset( $data['order_total'] ) ? $data['order_total'] : '',
			'{{order_total_raw}}'      => isset( $data['order_total_raw'] ) ? $data['order_total_raw'] : '',
			'{{order_subtotal}}'       => isset( $data['order_subtotal'] ) ? $data['order_subtotal'] : '',
			'{{order_subtotal_raw}}'   => isset( $data['order_subtotal_raw'] ) ? $data['order_subtotal_raw'] : '',
			'{{order_discount}}'       => isset( $data['order_discount'] ) ? $data['order_discount'] : '',
			'{{order_discount_raw}}'   => isset( $data['order_discount_raw'] ) ? $data['order_discount_raw'] : '',
			'{{order_shipping}}'       => isset( $data['order_shipping'] ) ? $data['order_shipping'] : '',
			'{{order_shipping_raw}}'   => isset( $data['order_shipping_raw'] ) ? $data['order_shipping_raw'] : '',
			
			// Pedido - Status
			'{{order_status}}'         => isset( $data['order_status_label'] ) ? $data['order_status_label'] : '',
			'{{order_status_label}}'   => isset( $data['order_status_label'] ) ? $data['order_status_label'] : '',
			'{{order_status_raw}}'     => isset( $data['order_status'] ) ? $data['order_status'] : '',
			
			// Pagamento
			'{{payment_method}}'       => isset( $data['payment_method'] ) ? $data['payment_method'] : '',
			'{{payment_link}}'         => isset( $data['payment_link'] ) ? $data['payment_link'] : '',
			'{{payment_url}}'          => isset( $data['payment_url'] ) ? $data['payment_url'] : '',
			
			// Datas
			'{{order_date}}'           => isset( $data['order_date'] ) ? $data['order_date'] : '',
			'{{order_date_short}}'     => isset( $data['order_date_short'] ) ? $data['order_date_short'] : '',
			'{{departure_date}}'       => isset( $data['departure_date'] ) ? $data['departure_date'] : '',
			'{{delivery_date}}'        => isset( $data['delivery_date'] ) ? $data['delivery_date'] : '',
			
			// Envio/Rastreio
			'{{shipping_method}}'      => isset( $data['shipping_method'] ) ? $data['shipping_method'] : '',
			'{{tracking_code}}'        => isset( $data['tracking_code'] ) ? $data['tracking_code'] : '',
			'{{tracking_codes}}'       => isset( $data['tracking_codes'] ) ? $data['tracking_codes'] : '',
			'{{tracking_url}}'         => isset( $data['tracking_url'] ) ? $data['tracking_url'] : '',
			'{{tracking_urls}}'        => isset( $data['tracking_urls'] ) ? $data['tracking_urls'] : '',
			'{{shipping_address}}'     => isset( $data['shipping_address'] ) ? $data['shipping_address'] : '',
			'{{billing_address}}'      => isset( $data['billing_address'] ) ? $data['billing_address'] : '',
			
			// Produtos e Cupons
			'{{products_list}}'        => isset( $data['products_list'] ) ? $data['products_list'] : '',
			'{{coupons}}'              => isset( $data['coupons'] ) ? $data['coupons'] : '',
			
			// Observações
			'{{order_notes}}'          => isset( $data['order_notes'] ) ? $data['order_notes'] : '',
			'{{budget_notes}}'         => isset( $data['budget_notes'] ) ? $data['budget_notes'] : '',
			
			// Site
			'{{site_name}}'            => isset( $data['site_name'] ) ? $data['site_name'] : get_bloginfo( 'name' ),
			'{{site_url}}'             => isset( $data['site_url'] ) ? $data['site_url'] : home_url(),
			
			// Cashback/Níveis (eventos específicos)
			'{{level_name}}'           => isset( $data['level_name'] ) ? $data['level_name'] : '',
			'{{cashback_amount}}'      => isset( $data['amount'] ) ? $this->format_price( $data['amount'] ) : '',
		);

		// Permitir extensão via filtro
		$replacements = apply_filters( 'pcw_webhook_replacements', $replacements, $data );

		$result = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

		return $result;
	}
}
