<?php
/**
 * Gatilhos de Workflow
 *
 * @package GrowlyDigital
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de gatilhos de workflow
 */
class PCW_Workflow_Triggers {

	/**
	 * Gatilhos registrados
	 *
	 * @var array
	 */
	private static $triggers = array();

	/**
	 * Inicializar gatilhos
	 */
	public static function init() {
		self::register_default_triggers();
	}

	/**
	 * Registrar gatilhos padrão
	 */
	private static function register_default_triggers() {
		// Gatilhos de Pedido
		self::register( 'order_status_changed', array(
			'name'        => __( 'Pedido: Status Alterado', 'person-cash-wallet' ),
			'description' => __( 'Disparado quando o status de um pedido é alterado', 'person-cash-wallet' ),
			'group'       => 'order',
			'icon'        => 'cart',
			'variables'   => array(
				'customer_name'    => __( 'Nome do Cliente', 'person-cash-wallet' ),
				'customer_email'   => __( 'Email do Cliente', 'person-cash-wallet' ),
				'customer_phone'   => __( 'Telefone do Cliente', 'person-cash-wallet' ),
				'order_id'         => __( 'ID do Pedido', 'person-cash-wallet' ),
				'order_number'     => __( 'Número do Pedido', 'person-cash-wallet' ),
				'order_total'      => __( 'Total do Pedido', 'person-cash-wallet' ),
				'order_status'     => __( 'Status Atual', 'person-cash-wallet' ),
				'order_status_old' => __( 'Status Anterior', 'person-cash-wallet' ),
				'payment_method'   => __( 'Método de Pagamento', 'person-cash-wallet' ),
				'billing_address'  => __( 'Endereço de Cobrança', 'person-cash-wallet' ),
				'shipping_address' => __( 'Endereço de Entrega', 'person-cash-wallet' ),
				'products_list'    => __( 'Lista de Produtos', 'person-cash-wallet' ),
				'order_date'       => __( 'Data do Pedido', 'person-cash-wallet' ),
			),
			'config_fields' => array(
				'status_from' => array(
					'type'    => 'select',
					'label'   => __( 'De Status', 'person-cash-wallet' ),
					'options' => 'order_statuses',
				),
				'status_to' => array(
					'type'    => 'select',
					'label'   => __( 'Para Status', 'person-cash-wallet' ),
					'options' => 'order_statuses',
				),
			),
		) );

		self::register( 'order_created', array(
			'name'        => __( 'Pedido: Novo Pedido', 'person-cash-wallet' ),
			'description' => __( 'Disparado quando um novo pedido é criado', 'person-cash-wallet' ),
			'group'       => 'order',
			'icon'        => 'cart',
			'variables'   => array(
				'customer_name'   => __( 'Nome do Cliente', 'person-cash-wallet' ),
				'customer_email'  => __( 'Email do Cliente', 'person-cash-wallet' ),
				'customer_phone'  => __( 'Telefone do Cliente', 'person-cash-wallet' ),
				'order_id'        => __( 'ID do Pedido', 'person-cash-wallet' ),
				'order_number'    => __( 'Número do Pedido', 'person-cash-wallet' ),
				'order_total'     => __( 'Total do Pedido', 'person-cash-wallet' ),
				'order_status'    => __( 'Status do Pedido', 'person-cash-wallet' ),
				'payment_method'  => __( 'Método de Pagamento', 'person-cash-wallet' ),
				'products_list'   => __( 'Lista de Produtos', 'person-cash-wallet' ),
				'order_date'      => __( 'Data do Pedido', 'person-cash-wallet' ),
			),
		) );

		// Gatilhos de Cashback
		self::register( 'cashback_earned', array(
			'name'        => __( 'Cashback: Ganho', 'person-cash-wallet' ),
			'description' => __( 'Disparado quando o cliente ganha cashback', 'person-cash-wallet' ),
			'group'       => 'cashback',
			'icon'        => 'money',
			'variables'   => array(
				'customer_name'    => __( 'Nome do Cliente', 'person-cash-wallet' ),
				'customer_email'   => __( 'Email do Cliente', 'person-cash-wallet' ),
				'customer_phone'   => __( 'Telefone do Cliente', 'person-cash-wallet' ),
				'cashback_amount'  => __( 'Valor do Cashback', 'person-cash-wallet' ),
				'order_id'         => __( 'ID do Pedido', 'person-cash-wallet' ),
				'order_total'      => __( 'Total do Pedido', 'person-cash-wallet' ),
				'expiration_date'  => __( 'Data de Expiração', 'person-cash-wallet' ),
				'wallet_balance'   => __( 'Saldo da Wallet', 'person-cash-wallet' ),
			),
		) );

		self::register( 'cashback_expiring', array(
			'name'        => __( 'Cashback: Expirando', 'person-cash-wallet' ),
			'description' => __( 'Disparado quando o cashback está próximo de expirar', 'person-cash-wallet' ),
			'group'       => 'cashback',
			'icon'        => 'warning',
			'variables'   => array(
				'customer_name'    => __( 'Nome do Cliente', 'person-cash-wallet' ),
				'customer_email'   => __( 'Email do Cliente', 'person-cash-wallet' ),
				'customer_phone'   => __( 'Telefone do Cliente', 'person-cash-wallet' ),
				'cashback_amount'  => __( 'Valor do Cashback', 'person-cash-wallet' ),
				'days_remaining'   => __( 'Dias Restantes', 'person-cash-wallet' ),
				'expiration_date'  => __( 'Data de Expiração', 'person-cash-wallet' ),
			),
			'config_fields' => array(
				'days_before_expiration' => array(
					'type'        => 'number',
					'label'       => __( 'Dias Antes da Expiração', 'person-cash-wallet' ),
					'default'     => 7,
					'min'         => 1,
					'max'         => 90,
					'description' => __( 'Quantos dias antes da expiração o gatilho deve ser acionado', 'person-cash-wallet' ),
				),
			),
		) );

		self::register( 'cashback_expired', array(
			'name'        => __( 'Cashback: Expirado', 'person-cash-wallet' ),
			'description' => __( 'Disparado quando o cashback expira', 'person-cash-wallet' ),
			'group'       => 'cashback',
			'icon'        => 'dismiss',
			'variables'   => array(
				'customer_name'    => __( 'Nome do Cliente', 'person-cash-wallet' ),
				'customer_email'   => __( 'Email do Cliente', 'person-cash-wallet' ),
				'customer_phone'   => __( 'Telefone do Cliente', 'person-cash-wallet' ),
				'cashback_amount'  => __( 'Valor do Cashback', 'person-cash-wallet' ),
			),
		) );

		self::register( 'cashback_used', array(
			'name'        => __( 'Cashback: Utilizado', 'person-cash-wallet' ),
			'description' => __( 'Disparado quando o cliente usa o cashback', 'person-cash-wallet' ),
			'group'       => 'cashback',
			'icon'        => 'yes',
			'variables'   => array(
				'customer_name'    => __( 'Nome do Cliente', 'person-cash-wallet' ),
				'customer_email'   => __( 'Email do Cliente', 'person-cash-wallet' ),
				'customer_phone'   => __( 'Telefone do Cliente', 'person-cash-wallet' ),
				'cashback_amount'  => __( 'Valor Utilizado', 'person-cash-wallet' ),
				'order_id'         => __( 'ID do Pedido', 'person-cash-wallet' ),
				'wallet_balance'   => __( 'Saldo Restante', 'person-cash-wallet' ),
			),
		) );

		// Gatilhos de Nível
		self::register( 'level_achieved', array(
			'name'        => __( 'Nível: Alcançado', 'person-cash-wallet' ),
			'description' => __( 'Disparado quando o cliente alcança um novo nível', 'person-cash-wallet' ),
			'group'       => 'level',
			'icon'        => 'star-filled',
			'variables'   => array(
				'customer_name'   => __( 'Nome do Cliente', 'person-cash-wallet' ),
				'customer_email'  => __( 'Email do Cliente', 'person-cash-wallet' ),
				'customer_phone'  => __( 'Telefone do Cliente', 'person-cash-wallet' ),
				'level_name'      => __( 'Nome do Nível', 'person-cash-wallet' ),
				'level_number'    => __( 'Número do Nível', 'person-cash-wallet' ),
				'old_level_name'  => __( 'Nível Anterior', 'person-cash-wallet' ),
			),
		) );

		self::register( 'level_expiring', array(
			'name'        => __( 'Nível: Expirando', 'person-cash-wallet' ),
			'description' => __( 'Disparado quando o nível está próximo de expirar', 'person-cash-wallet' ),
			'group'       => 'level',
			'icon'        => 'warning',
			'variables'   => array(
				'customer_name'   => __( 'Nome do Cliente', 'person-cash-wallet' ),
				'customer_email'  => __( 'Email do Cliente', 'person-cash-wallet' ),
				'customer_phone'  => __( 'Telefone do Cliente', 'person-cash-wallet' ),
				'level_name'      => __( 'Nome do Nível', 'person-cash-wallet' ),
				'days_remaining'  => __( 'Dias Restantes', 'person-cash-wallet' ),
				'expiration_date' => __( 'Data de Expiração', 'person-cash-wallet' ),
			),
		) );

		// Gatilhos de Wallet
		self::register( 'wallet_credit', array(
			'name'        => __( 'Wallet: Crédito Adicionado', 'person-cash-wallet' ),
			'description' => __( 'Disparado quando crédito é adicionado à wallet', 'person-cash-wallet' ),
			'group'       => 'wallet',
			'icon'        => 'plus',
			'variables'   => array(
				'customer_name'   => __( 'Nome do Cliente', 'person-cash-wallet' ),
				'customer_email'  => __( 'Email do Cliente', 'person-cash-wallet' ),
				'customer_phone'  => __( 'Telefone do Cliente', 'person-cash-wallet' ),
				'credit_amount'   => __( 'Valor do Crédito', 'person-cash-wallet' ),
				'credit_source'   => __( 'Origem do Crédito', 'person-cash-wallet' ),
				'wallet_balance'  => __( 'Saldo Atual', 'person-cash-wallet' ),
			),
		) );

		self::register( 'wallet_debit', array(
			'name'        => __( 'Wallet: Débito Realizado', 'person-cash-wallet' ),
			'description' => __( 'Disparado quando débito é realizado na wallet', 'person-cash-wallet' ),
			'group'       => 'wallet',
			'icon'        => 'minus',
			'variables'   => array(
				'customer_name'   => __( 'Nome do Cliente', 'person-cash-wallet' ),
				'customer_email'  => __( 'Email do Cliente', 'person-cash-wallet' ),
				'customer_phone'  => __( 'Telefone do Cliente', 'person-cash-wallet' ),
				'debit_amount'    => __( 'Valor do Débito', 'person-cash-wallet' ),
				'order_id'        => __( 'ID do Pedido', 'person-cash-wallet' ),
				'wallet_balance'  => __( 'Saldo Restante', 'person-cash-wallet' ),
			),
		) );

		// Gatilhos de Cliente
		self::register( 'customer_registered', array(
			'name'        => __( 'Cliente: Novo Cadastro', 'person-cash-wallet' ),
			'description' => __( 'Disparado quando um novo cliente se cadastra', 'person-cash-wallet' ),
			'group'       => 'customer',
			'icon'        => 'admin-users',
			'variables'   => array(
				'customer_name'   => __( 'Nome do Cliente', 'person-cash-wallet' ),
				'customer_email'  => __( 'Email do Cliente', 'person-cash-wallet' ),
				'customer_phone'  => __( 'Telefone do Cliente', 'person-cash-wallet' ),
				'register_date'   => __( 'Data de Cadastro', 'person-cash-wallet' ),
			),
		) );

		// Gatilho Agendado (Cron)
		self::register( 'scheduled_order_check', array(
			'name'        => __( 'Agendado: Verificar Pedidos', 'person-cash-wallet' ),
			'description' => __( 'Verifica pedidos periodicamente baseado em condições de tempo (ex: pedidos há X dias em um status)', 'person-cash-wallet' ),
			'group'       => 'scheduled',
			'icon'        => 'clock',
			'variables'   => array(
				'customer_name'    => __( 'Nome do Cliente', 'person-cash-wallet' ),
				'customer_email'   => __( 'Email do Cliente', 'person-cash-wallet' ),
				'customer_phone'   => __( 'Telefone do Cliente', 'person-cash-wallet' ),
				'order_id'         => __( 'ID do Pedido', 'person-cash-wallet' ),
				'order_number'     => __( 'Número do Pedido', 'person-cash-wallet' ),
				'order_total'      => __( 'Total do Pedido (formatado)', 'person-cash-wallet' ),
				'order_status'     => __( 'Status Atual (ex: Aguardando Pagamento)', 'person-cash-wallet' ),
				'order_status_slug' => __( 'Status Slug (ex: pending)', 'person-cash-wallet' ),
				'order_date'       => __( 'Data do Pedido', 'person-cash-wallet' ),
				'order_age_days'   => __( 'Dias desde a Criação', 'person-cash-wallet' ),
				'days_in_status'   => __( 'Dias no Status Atual', 'person-cash-wallet' ),
				'payment_method'   => __( 'Método de Pagamento', 'person-cash-wallet' ),
				'payment_link'     => __( 'Link de Pagamento', 'person-cash-wallet' ),
				'products_list'    => __( 'Lista de Produtos', 'person-cash-wallet' ),
			),
			'config_fields' => array(
				'target_status' => array(
					'type'        => 'select',
					'label'       => __( 'Status do Pedido', 'person-cash-wallet' ),
					'options'     => 'order_statuses',
					'description' => __( 'Verificar pedidos neste status', 'person-cash-wallet' ),
				),
				'min_days_in_status' => array(
					'type'        => 'number',
					'label'       => __( 'Mínimo de Dias no Status', 'person-cash-wallet' ),
					'default'     => 1,
					'description' => __( 'Executar apenas se o pedido estiver há pelo menos X dias neste status', 'person-cash-wallet' ),
				),
				'max_days_in_status' => array(
					'type'        => 'number',
					'label'       => __( 'Máximo de Dias no Status (opcional)', 'person-cash-wallet' ),
					'default'     => '',
					'description' => __( 'Não executar se ultrapassar X dias (deixe vazio para sem limite)', 'person-cash-wallet' ),
				),
				'run_once_per_order' => array(
					'type'        => 'checkbox',
					'label'       => __( 'Executar apenas uma vez por pedido', 'person-cash-wallet' ),
					'default'     => true,
					'description' => __( 'Evita enviar múltiplas notificações para o mesmo pedido', 'person-cash-wallet' ),
				),
			),
		) );

		// Permitir plugins externos registrar gatilhos
		do_action( 'pcw_register_workflow_triggers' );
	}

	/**
	 * Registrar gatilho
	 *
	 * @param string $id Identificador único.
	 * @param array  $args Argumentos do gatilho.
	 */
	public static function register( $id, $args ) {
		$defaults = array(
			'name'          => '',
			'description'   => '',
			'group'         => 'general',
			'icon'          => 'admin-generic',
			'variables'     => array(),
			'config_fields' => array(),
		);

		self::$triggers[ $id ] = wp_parse_args( $args, $defaults );
	}

	/**
	 * Obter todos os gatilhos
	 *
	 * @return array
	 */
	public static function get_all() {
		if ( empty( self::$triggers ) ) {
			self::init();
		}
		return self::$triggers;
	}

	/**
	 * Obter gatilho por ID
	 *
	 * @param string $id ID do gatilho.
	 * @return array|null
	 */
	public static function get( $id ) {
		if ( empty( self::$triggers ) ) {
			self::init();
		}
		return isset( self::$triggers[ $id ] ) ? self::$triggers[ $id ] : null;
	}

	/**
	 * Obter gatilhos agrupados
	 *
	 * @return array
	 */
	public static function get_grouped() {
		if ( empty( self::$triggers ) ) {
			self::init();
		}

		$groups = array(
			'scheduled' => array( 'label' => __( '⏰ Agendados', 'person-cash-wallet' ), 'triggers' => array() ),
			'order'     => array( 'label' => __( 'Pedidos', 'person-cash-wallet' ), 'triggers' => array() ),
			'cashback'  => array( 'label' => __( 'Cashback', 'person-cash-wallet' ), 'triggers' => array() ),
			'level'     => array( 'label' => __( 'Níveis', 'person-cash-wallet' ), 'triggers' => array() ),
			'wallet'    => array( 'label' => __( 'Wallet', 'person-cash-wallet' ), 'triggers' => array() ),
			'customer'  => array( 'label' => __( 'Clientes', 'person-cash-wallet' ), 'triggers' => array() ),
			'general'   => array( 'label' => __( 'Geral', 'person-cash-wallet' ), 'triggers' => array() ),
		);

		foreach ( self::$triggers as $id => $trigger ) {
			$group = isset( $trigger['group'] ) ? $trigger['group'] : 'general';
			if ( ! isset( $groups[ $group ] ) ) {
				$groups[ $group ] = array( 'label' => ucfirst( $group ), 'triggers' => array() );
			}
			$groups[ $group ]['triggers'][ $id ] = $trigger;
		}

		// Remover grupos vazios
		foreach ( $groups as $key => $group ) {
			if ( empty( $group['triggers'] ) ) {
				unset( $groups[ $key ] );
			}
		}

		return $groups;
	}

	/**
	 * Obter variáveis de um gatilho
	 *
	 * @param string $trigger_id ID do gatilho.
	 * @return array
	 */
	public static function get_variables( $trigger_id ) {
		$trigger = self::get( $trigger_id );
		return $trigger ? $trigger['variables'] : array();
	}
}
