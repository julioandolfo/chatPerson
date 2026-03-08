<?php
/**
 * Classe de integração de webhooks com eventos
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de integração de webhooks
 */
class PCW_Webhook_Integration {

	/**
	 * Inicializar listeners
	 */
	public function init() {
		// Cashback events
		add_action( 'pcw_cashback_created', array( $this, 'handle_cashback_created' ), 10, 4 );
		add_action( 'pcw_cashback_used', array( $this, 'handle_cashback_used' ), 10, 3 );
		add_action( 'pcw_cashback_expiring', array( $this, 'handle_cashback_expiring' ), 10, 2 );
		add_action( 'pcw_cashback_expired', array( $this, 'handle_cashback_expired' ), 10, 1 );

		// Level events
		add_action( 'pcw_level_assigned', array( $this, 'handle_level_assigned' ), 10, 3 );
		add_action( 'pcw_level_removed', array( $this, 'handle_level_removed' ), 10, 2 );
		add_action( 'pcw_level_expiring', array( $this, 'handle_level_expiring' ), 10, 1 );
		add_action( 'pcw_level_expired', array( $this, 'handle_level_expired' ), 10, 1 );

		// Wallet events
		add_action( 'pcw_wallet_credited', array( $this, 'handle_wallet_credited' ), 10, 4 );
		add_action( 'pcw_wallet_debited', array( $this, 'handle_wallet_debited' ), 10, 4 );
		add_action( 'pcw_wallet_payment_complete', array( $this, 'handle_wallet_payment_complete' ), 10, 3 );

		// WooCommerce Order events
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_changed' ), 10, 4 );
		add_action( 'woocommerce_new_order', array( $this, 'handle_order_created' ), 10, 2 );
	}

	/**
	 * Handler: Cashback criado
	 *
	 * @param int    $cashback_id ID do cashback.
	 * @param int    $user_id ID do usuário.
	 * @param int    $order_id ID do pedido.
	 * @param float  $amount Valor.
	 */
	public function handle_cashback_created( $cashback_id, $user_id, $order_id, $amount ) {
		$user = get_userdata( $user_id );
		$order = wc_get_order( $order_id );

		PCW_Webhook_Handler::trigger(
			'cashback.earned',
			array(
				'cashback_id'  => $cashback_id,
				'user_id'      => $user_id,
				'user_email'   => $user ? $user->user_email : '',
				'user_name'    => $user ? $user->display_name : '',
				'order_id'     => $order_id,
				'order_total'  => $order ? $order->get_total() : 0,
				'amount'       => $amount,
			)
		);
	}

	/**
	 * Handler: Cashback usado
	 *
	 * @param int   $user_id ID do usuário.
	 * @param float $amount Valor usado.
	 * @param int   $order_id ID do pedido.
	 */
	public function handle_cashback_used( $user_id, $amount, $order_id ) {
		$user = get_userdata( $user_id );

		PCW_Webhook_Handler::trigger(
			'cashback.used',
			array(
				'user_id'     => $user_id,
				'user_email'  => $user ? $user->user_email : '',
				'user_name'   => $user ? $user->display_name : '',
				'order_id'    => $order_id,
				'amount'      => $amount,
			)
		);
	}

	/**
	 * Handler: Cashback expirando
	 *
	 * @param object $cashback Dados do cashback.
	 * @param int    $days_before Dias antes da expiração.
	 */
	public function handle_cashback_expiring( $cashback, $days_before = 0 ) {
		$user = get_userdata( $cashback->user_id );

		PCW_Webhook_Handler::trigger(
			'cashback.expiring',
			array(
				'cashback_id'  => $cashback->id,
				'user_id'      => $cashback->user_id,
				'user_email'   => $user ? $user->user_email : '',
				'user_name'    => $user ? $user->display_name : '',
				'amount'       => $cashback->amount,
				'expires_date' => $cashback->expires_date,
				'days_before'  => absint( $days_before ),
			)
		);
	}

	/**
	 * Handler: Cashback expirado
	 *
	 * @param object $cashback Dados do cashback.
	 */
	public function handle_cashback_expired( $cashback ) {
		$user = get_userdata( $cashback->user_id );

		PCW_Webhook_Handler::trigger(
			'cashback.expired',
			array(
				'cashback_id'  => $cashback->id,
				'user_id'      => $cashback->user_id,
				'user_email'   => $user ? $user->user_email : '',
				'user_name'    => $user ? $user->display_name : '',
				'amount'       => $cashback->amount,
				'expires_date' => $cashback->expires_date,
			)
		);
	}

	/**
	 * Handler: Nível atribuído
	 *
	 * @param int $user_id ID do usuário.
	 * @param int $level_id ID do nível.
	 * @param int $user_level_id ID do registro.
	 */
	public function handle_level_assigned( $user_id, $level_id, $user_level_id ) {
		$user = get_userdata( $user_id );
		$level = PCW_Levels::get_level( $level_id );

		PCW_Webhook_Handler::trigger(
			'level.updated',
			array(
				'user_id'      => $user_id,
				'user_email'   => $user ? $user->user_email : '',
				'user_name'    => $user ? $user->display_name : '',
				'level_id'     => $level_id,
				'level_name'   => $level ? $level->name : '',
				'level_number' => $level ? $level->level_number : 0,
				'action'       => 'assigned',
			)
		);
	}

	/**
	 * Handler: Nível removido
	 *
	 * @param int $user_id ID do usuário.
	 * @param int $level_id ID do nível.
	 */
	public function handle_level_removed( $user_id, $level_id ) {
		$user = get_userdata( $user_id );
		$level = PCW_Levels::get_level( $level_id );

		PCW_Webhook_Handler::trigger(
			'level.updated',
			array(
				'user_id'      => $user_id,
				'user_email'   => $user ? $user->user_email : '',
				'user_name'    => $user ? $user->display_name : '',
				'level_id'     => $level_id,
				'level_name'   => $level ? $level->name : '',
				'level_number' => $level ? $level->level_number : 0,
				'action'       => 'removed',
			)
		);
	}

	/**
	 * Handler: Nível expirando
	 *
	 * @param object $user_level Dados do nível do usuário.
	 */
	public function handle_level_expiring( $user_level ) {
		$user = get_userdata( $user_level->user_id );

		PCW_Webhook_Handler::trigger(
			'level.expiring',
			array(
				'user_id'      => $user_level->user_id,
				'user_email'   => $user ? $user->user_email : '',
				'user_name'    => $user ? $user->display_name : '',
				'level_id'     => $user_level->level_id,
				'level_name'   => $user_level->level_name,
				'expires_date' => $user_level->expires_date,
			)
		);
	}

	/**
	 * Handler: Nível expirado
	 *
	 * @param object $user_level Dados do nível do usuário.
	 */
	public function handle_level_expired( $user_level ) {
		$user = get_userdata( $user_level->user_id );
		$level = PCW_Levels::get_level( $user_level->level_id );

		PCW_Webhook_Handler::trigger(
			'level.expired',
			array(
				'user_id'      => $user_level->user_id,
				'user_email'   => $user ? $user->user_email : '',
				'user_name'    => $user ? $user->display_name : '',
				'level_id'     => $user_level->level_id,
				'level_name'   => $level ? $level->name : '',
			)
		);
	}

	/**
	 * Handler: Wallet creditada
	 *
	 * @param int    $user_id ID do usuário.
	 * @param float  $amount Valor.
	 * @param string $source Fonte.
	 * @param int    $transaction_id ID da transação.
	 */
	public function handle_wallet_credited( $user_id, $amount, $source, $transaction_id ) {
		$user = get_userdata( $user_id );
		$wallet = new PCW_Wallet( $user_id );

		PCW_Webhook_Handler::trigger(
			'wallet.credit',
			array(
				'user_id'        => $user_id,
				'user_email'     => $user ? $user->user_email : '',
				'user_name'      => $user ? $user->display_name : '',
				'transaction_id' => $transaction_id,
				'amount'         => $amount,
				'source'         => $source,
				'balance'        => $wallet->get_balance(),
			)
		);

		PCW_Webhook_Handler::trigger(
			'wallet.transaction',
			array(
				'user_id'        => $user_id,
				'user_email'     => $user ? $user->user_email : '',
				'transaction_id' => $transaction_id,
				'type'           => 'credit',
				'amount'         => $amount,
				'source'         => $source,
				'balance'        => $wallet->get_balance(),
			)
		);
	}

	/**
	 * Handler: Wallet debitada
	 *
	 * @param int    $user_id ID do usuário.
	 * @param float  $amount Valor.
	 * @param string $source Fonte.
	 * @param int    $transaction_id ID da transação.
	 */
	public function handle_wallet_debited( $user_id, $amount, $source, $transaction_id ) {
		$user = get_userdata( $user_id );
		$wallet = new PCW_Wallet( $user_id );

		PCW_Webhook_Handler::trigger(
			'wallet.debit',
			array(
				'user_id'        => $user_id,
				'user_email'     => $user ? $user->user_email : '',
				'user_name'      => $user ? $user->display_name : '',
				'transaction_id' => $transaction_id,
				'amount'         => $amount,
				'source'         => $source,
				'balance'        => $wallet->get_balance(),
			)
		);

		PCW_Webhook_Handler::trigger(
			'wallet.transaction',
			array(
				'user_id'        => $user_id,
				'user_email'     => $user ? $user->user_email : '',
				'transaction_id' => $transaction_id,
				'type'           => 'debit',
				'amount'         => $amount,
				'source'         => $source,
				'balance'        => $wallet->get_balance(),
			)
		);
	}

	/**
	 * Handler: Pagamento com wallet completo
	 *
	 * @param int   $order_id ID do pedido.
	 * @param int   $user_id ID do usuário.
	 * @param float $amount Valor.
	 */
	public function handle_wallet_payment_complete( $order_id, $user_id, $amount ) {
		$user = get_userdata( $user_id );
		$order = wc_get_order( $order_id );

		PCW_Webhook_Handler::trigger(
			'wallet.payment_complete',
			array(
				'user_id'     => $user_id,
				'user_email'  => $user ? $user->user_email : '',
				'order_id'    => $order_id,
				'order_total' => $order ? $order->get_total() : 0,
				'amount'      => $amount,
			)
		);
	}

	/**
	 * Handler: Status do pedido alterado
	 *
	 * @param int    $order_id ID do pedido.
	 * @param string $old_status Status antigo.
	 * @param string $new_status Novo status.
	 * @param object $order Objeto do pedido.
	 */
	public function handle_order_status_changed( $order_id, $old_status, $new_status, $order ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();
		$user = $user_id ? get_userdata( $user_id ) : null;

		// Preparar dados do pedido
		$order_data = array(
			'order_id'       => $order_id,
			'order_number'   => $order->get_order_number(),
			'order_key'      => $order->get_order_key(),
			'old_status'     => $old_status,
			'new_status'     => $new_status,
			'status_label'   => wc_get_order_status_name( $new_status ),
			'total'          => floatval( $order->get_total() ),
			'subtotal'       => floatval( $order->get_subtotal() ),
			'currency'       => $order->get_currency(),
			'payment_method' => $order->get_payment_method(),
			'payment_method_title' => $order->get_payment_method_title(),
			'created_date'   => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
			'user_id'        => $user_id,
			'user_email'     => $user ? $user->user_email : $order->get_billing_email(),
			'user_name'      => $user ? $user->display_name : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'billing'        => array(
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'email'      => $order->get_billing_email(),
				'phone'      => $order->get_billing_phone(),
				'address_1'  => $order->get_billing_address_1(),
				'address_2'  => $order->get_billing_address_2(),
				'city'       => $order->get_billing_city(),
				'state'      => $order->get_billing_state(),
				'postcode'   => $order->get_billing_postcode(),
				'country'    => $order->get_billing_country(),
			),
			'items'          => array(),
		);

		// Adicionar itens do pedido
		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			$order_data['items'][] = array(
				'item_id'      => $item_id,
				'product_id'   => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'product_name' => $item->get_name(),
				'quantity'     => $item->get_quantity(),
				'total'        => floatval( $item->get_total() ),
				'subtotal'     => floatval( $item->get_subtotal() ),
				'sku'          => $product ? $product->get_sku() : '',
			);
		}

		// Disparar evento genérico de mudança de status
		PCW_Webhook_Handler::trigger(
			'order.status_changed',
			$order_data
		);

		// Disparar evento específico do status (ex: order.processing, order.completed)
		$status_event = 'order.' . $new_status;
		PCW_Webhook_Handler::trigger(
			$status_event,
			$order_data
		);
	}

	/**
	 * Handler: Novo pedido criado
	 *
	 * @param int    $order_id ID do pedido.
	 * @param object $order Objeto do pedido.
	 */
	public function handle_order_created( $order_id, $order ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();
		$user = $user_id ? get_userdata( $user_id ) : null;

		// Preparar dados do pedido
		$order_data = array(
			'order_id'       => $order_id,
			'order_number'   => $order->get_order_number(),
			'order_key'      => $order->get_order_key(),
			'status'         => $order->get_status(),
			'status_label'   => wc_get_order_status_name( $order->get_status() ),
			'total'          => floatval( $order->get_total() ),
			'subtotal'       => floatval( $order->get_subtotal() ),
			'currency'       => $order->get_currency(),
			'payment_method' => $order->get_payment_method(),
			'payment_method_title' => $order->get_payment_method_title(),
			'created_date'   => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
			'user_id'        => $user_id,
			'user_email'     => $user ? $user->user_email : $order->get_billing_email(),
			'user_name'      => $user ? $user->display_name : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'billing'        => array(
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'email'      => $order->get_billing_email(),
				'phone'      => $order->get_billing_phone(),
				'address_1'  => $order->get_billing_address_1(),
				'address_2'  => $order->get_billing_address_2(),
				'city'       => $order->get_billing_city(),
				'state'      => $order->get_billing_state(),
				'postcode'   => $order->get_billing_postcode(),
				'country'    => $order->get_billing_country(),
			),
			'items'          => array(),
		);

		// Adicionar itens do pedido
		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			$order_data['items'][] = array(
				'item_id'      => $item_id,
				'product_id'   => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'product_name' => $item->get_name(),
				'quantity'     => $item->get_quantity(),
				'total'        => floatval( $item->get_total() ),
				'subtotal'     => floatval( $item->get_subtotal() ),
				'sku'          => $product ? $product->get_sku() : '',
			);
		}

		PCW_Webhook_Handler::trigger(
			'order.created',
			$order_data
		);
	}
}
