<?php
/**
 * Sistema de Filas e Rate Limiting para Mensagens
 *
 * @package PersonCashWallet
 * @since 1.4.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCW_Message_Queue_Manager {

	/**
	 * Instância única
	 */
	private static $instance = null;

	/**
	 * Nome da tabela
	 */
	private $table_name;

	/**
	 * Nome da tabela de configurações de números
	 */
	private $numbers_table;

	/**
	 * Nome da tabela de contas SMTP
	 */
	private $smtp_table;

	/**
	 * Construtor
	 */
	private function __construct() {
		global $wpdb;
		$this->table_name     = $wpdb->prefix . 'pcw_message_queue';
		$this->numbers_table  = $wpdb->prefix . 'pcw_whatsapp_numbers';
		$this->smtp_table     = $wpdb->prefix . 'pcw_smtp_accounts';
	}

	/**
	 * Obter instância única
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Inicializar
	 */
	public function init() {
		// Criar tabelas se não existirem
		$this->create_tables();

		// Adicionar intervalo customizado de 1 minuto (ANTES de agendar)
		add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );

		// Registrar cron job
		add_action( 'pcw_process_message_queue', array( $this, 'process_queue' ) );
		
		// Agendar cron se não estiver agendado
		$next_scheduled = wp_next_scheduled( 'pcw_process_message_queue' );
		if ( ! $next_scheduled ) {
			$result = wp_schedule_event( time(), 'every_minute', 'pcw_process_message_queue' );
			if ( function_exists( 'wc_get_logger' ) ) {
				$logger = wc_get_logger();
				$logger->info( 'Cron pcw_process_message_queue agendado: ' . ( $result ? 'sucesso' : 'falha' ), array( 'source' => 'pcw-queue-cron' ) );
			}
		}
	}

	/**
	 * Adicionar intervalo de cron customizado
	 */
	public function add_cron_interval( $schedules ) {
		$schedules['every_minute'] = array(
			'interval' => 60,
			'display'  => __( 'A cada minuto', 'person-cash-wallet' ),
		);
		return $schedules;
	}

	/**
	 * Criar tabelas
	 */
	public function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Tabela de filas
		$sql_queue = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			type varchar(50) NOT NULL DEFAULT 'whatsapp',
			to_number varchar(20) NOT NULL,
			from_number varchar(20) DEFAULT NULL,
			message text NOT NULL,
			contact_name varchar(255) DEFAULT NULL,
			webhook_id bigint(20) DEFAULT NULL,
			automation_id bigint(20) DEFAULT NULL,
			template_name varchar(255) DEFAULT NULL,
			template_language varchar(10) DEFAULT NULL,
			template_params text DEFAULT NULL,
			template_body_text text DEFAULT NULL,
			priority int(11) NOT NULL DEFAULT 5,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts int(11) NOT NULL DEFAULT 0,
			max_attempts int(11) NOT NULL DEFAULT 3,
			scheduled_at datetime DEFAULT NULL,
			processed_at datetime DEFAULT NULL,
			error_message text,
			response_data text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY type (type),
			KEY status (status),
			KEY scheduled_at (scheduled_at),
			KEY from_number (from_number)
		) {$charset_collate};";

		// Tabela de configuração de números WhatsApp
		$sql_numbers = "CREATE TABLE IF NOT EXISTS {$this->numbers_table} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			phone_number varchar(20) NOT NULL,
			name varchar(255) NOT NULL,
			provider varchar(50) NOT NULL DEFAULT 'evolution',
			account_id int(11) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			rate_limit_hour int(11) NOT NULL DEFAULT 60,
			min_interval_seconds int(11) NOT NULL DEFAULT 30,
			distribution_weight int(11) NOT NULL DEFAULT 100,
			distribution_enabled tinyint(1) NOT NULL DEFAULT 1,
			sent_last_hour int(11) NOT NULL DEFAULT 0,
			sent_last_reset datetime DEFAULT NULL,
			total_sent int(11) NOT NULL DEFAULT 0,
			total_failed int(11) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY phone_number (phone_number),
			KEY status (status),
			KEY distribution_enabled (distribution_enabled),
			KEY provider (provider)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_queue );
		dbDelta( $sql_numbers );

		// Migrar tabela existente: adicionar coluna min_interval_seconds se não existir
		$this->migrate_whatsapp_numbers_table();
	}

	/**
	 * Migrar tabela de números WhatsApp: adicionar colunas novas se não existirem
	 */
	private function migrate_whatsapp_numbers_table() {
		global $wpdb;

		$table_exists = $wpdb->get_var( $wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$this->numbers_table
		) );

		if ( ! $table_exists ) {
			return;
		}

		$columns = $wpdb->get_col( "DESCRIBE {$this->numbers_table}", 0 );

		if ( ! in_array( 'min_interval_seconds', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$this->numbers_table} ADD COLUMN min_interval_seconds int(11) NOT NULL DEFAULT 30 AFTER rate_limit_hour" );
		}

		if ( ! in_array( 'provider', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$this->numbers_table} ADD COLUMN provider varchar(50) NOT NULL DEFAULT 'evolution' AFTER name" );
		}

		if ( ! in_array( 'account_id', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$this->numbers_table} ADD COLUMN account_id int(11) DEFAULT NULL AFTER provider" );
		}

		// Migrar tabela de fila
		$queue_columns = $wpdb->get_col( "DESCRIBE {$this->table_name}", 0 );

		if ( ! in_array( 'template_name', $queue_columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN template_name varchar(255) DEFAULT NULL AFTER automation_id" );
		}
		if ( ! in_array( 'template_language', $queue_columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN template_language varchar(10) DEFAULT NULL AFTER template_name" );
		}
		if ( ! in_array( 'template_params', $queue_columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN template_params text DEFAULT NULL AFTER template_language" );
		}
		if ( ! in_array( 'template_body_text', $queue_columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN template_body_text text DEFAULT NULL AFTER template_params" );
		}
	}

	/**
	 * Verificar e garantir que a coluna min_interval_seconds existe
	 * 
	 * @return bool
	 */
	public function ensure_min_interval_column() {
		global $wpdb;

		$columns = $wpdb->get_col( "DESCRIBE {$this->numbers_table}", 0 );

		if ( ! in_array( 'min_interval_seconds', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$this->numbers_table} ADD COLUMN min_interval_seconds int(11) NOT NULL DEFAULT 30 AFTER rate_limit_hour" );
		}

		return in_array( 'min_interval_seconds', $wpdb->get_col( "DESCRIBE {$this->numbers_table}", 0 ), true );
	}

	/**
	 * Adicionar mensagem à fila
	 *
	 * @param array $args Argumentos da mensagem
	 * @return int|false ID da fila ou false em caso de erro
	 */
	public function add_to_queue( $args ) {
		global $wpdb;

		$defaults = array(
			'type'               => 'whatsapp',
			'to_number'          => '',
			'from_number'        => null,
			'message'            => '',
			'contact_name'       => null,
			'webhook_id'         => null,
			'automation_id'      => null,
			'template_name'      => null,
			'template_language'  => null,
			'template_params'    => null,
			'template_body_text' => null,
			'priority'           => 5,
			'scheduled_at'       => null,
		);

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['to_number'] ) || empty( $args['message'] ) ) {
			return false;
		}

		// ═══════ LOCK DISTRIBUÍDO ═══════
		// Usar lock por número para evitar race conditions na adição simultânea
		$lock_key = 'pcw_queue_add_' . md5( $args['to_number'] . ( $args['from_number'] ?? 'auto' ) );
		$lock_attempts = 0;
		$max_lock_attempts = 10;
		
		while ( get_transient( $lock_key ) && $lock_attempts < $max_lock_attempts ) {
			usleep( 50000 ); // 50ms
			$lock_attempts++;
		}
		
		if ( $lock_attempts >= $max_lock_attempts ) {
			$this->log_cron( 'warning', 'Timeout ao aguardar lock para adicionar mensagem à fila' );
			return false;
		}
		
		// Criar lock por 5 segundos
		set_transient( $lock_key, time(), 5 );

		// Se não especificou from_number, selecionar automaticamente
		// Passa o to_number para manter consistência (sticky number)
		if ( empty( $args['from_number'] ) ) {
			$args['from_number'] = $this->select_number_for_sending( $args['to_number'] );
		}

		// Verificar se há número disponível
		if ( empty( $args['from_number'] ) ) {
			delete_transient( $lock_key );
			$this->log_cron( 'error', 'Nenhum número disponível para envio à fila. to=' . $args['to_number'] . ' type=' . $args['type'] );
			error_log( '[PCW Queue] ERRO: Nenhum número WhatsApp disponível para envio. Verifique se há números ativos com distribution_enabled=1 em Fila > Números WhatsApp.' );
			return false;
		}

		// Se não definiu scheduled_at, calcular com base no rate limit
		if ( empty( $args['scheduled_at'] ) ) {
			$args['scheduled_at'] = $this->calculate_next_available_slot( $args['from_number'] );
		}

		$insert_data = array(
			'type'          => $args['type'],
			'to_number'     => $args['to_number'],
			'from_number'   => $args['from_number'],
			'message'       => $args['message'],
			'contact_name'  => $args['contact_name'],
			'webhook_id'    => $args['webhook_id'],
			'automation_id' => $args['automation_id'],
			'priority'      => $args['priority'],
			'scheduled_at'  => $args['scheduled_at'],
		);
		$insert_format = array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' );

		if ( ! empty( $args['template_name'] ) ) {
			$insert_data['template_name']      = $args['template_name'];
			$insert_data['template_language']   = $args['template_language'];
			$insert_data['template_params']     = $args['template_params'];
			$insert_data['template_body_text']  = $args['template_body_text'];
			$insert_format[] = '%s';
			$insert_format[] = '%s';
			$insert_format[] = '%s';
			$insert_format[] = '%s';
		}

		$result = $wpdb->insert( $this->table_name, $insert_data, $insert_format );

		// Liberar lock
		delete_transient( $lock_key );

		if ( $result === false ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Adicionar mensagem WhatsApp à fila
	 *
	 * Wrapper específico para adicionar WhatsApp na fila com rate limiting
	 *
	 * @param array $args Argumentos da mensagem.
	 * @return int|false ID da fila ou false em caso de erro.
	 */
	public function add_whatsapp_to_queue( $args ) {
		$defaults = array(
			'to'           => '',
			'message'      => '',
			'from'         => '',
			'contact_name' => '',
			'metadata'     => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		// Mapear para o formato do add_to_queue
		return $this->add_to_queue( array(
			'type'         => 'whatsapp',
			'to_number'    => $args['to'],
			'from_number'  => $args['from'],
			'message'      => $args['message'],
			'contact_name' => $args['contact_name'],
			'metadata'     => $args['metadata'],
		) );
	}

	/**
	 * Adicionar template WhatsApp à fila
	 *
	 * @param array $args Argumentos do template
	 * @return int|false ID da fila ou false em caso de erro
	 */
	public function add_template_to_queue( $args ) {
		$defaults = array(
			'to'                => '',
			'from'              => '',
			'template_name'     => '',
			'template_params'   => array(),
			'template_language' => 'pt_BR',
			'template_body_text'=> '',
			'contact_name'      => '',
			'automation_id'     => null,
			'metadata'          => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['to'] ) || empty( $args['template_name'] ) ) {
			return false;
		}

		$body_preview = $args['template_body_text'];
		if ( ! empty( $body_preview ) && ! empty( $args['template_params'] ) ) {
			$params = is_array( $args['template_params'] ) ? $args['template_params'] : json_decode( $args['template_params'], true );
			if ( is_array( $params ) ) {
				foreach ( $params as $i => $val ) {
					$body_preview = str_replace( '{{' . ( $i + 1 ) . '}}', $val, $body_preview );
				}
			}
		}

		return $this->add_to_queue( array(
			'type'               => 'whatsapp_template',
			'to_number'          => $args['to'],
			'from_number'        => $args['from'],
			'message'            => ! empty( $body_preview ) ? $body_preview : '[Template: ' . $args['template_name'] . ']',
			'contact_name'       => $args['contact_name'],
			'automation_id'      => $args['automation_id'],
			'template_name'      => $args['template_name'],
			'template_language'  => $args['template_language'],
			'template_params'    => is_array( $args['template_params'] ) ? wp_json_encode( $args['template_params'] ) : $args['template_params'],
			'template_body_text' => $args['template_body_text'],
			'metadata'           => $args['metadata'],
		) );
	}

	/**
	 * Adicionar email à fila
	 *
	 * @param array $args Argumentos do email
	 * @return int|false ID da fila ou false em caso de erro
	 */
	public function add_email_to_queue( $args ) {
		global $wpdb;

		$defaults = array(
			'to_email'      => '',
			'subject'       => '',
			'message'       => '',
			'headers'       => array(),
			'attachments'   => array(),
			'smtp_account'  => null,
			'webhook_id'    => null,
			'automation_id' => null,
			'priority'      => 5,
			'scheduled_at'  => null,
			'metadata'      => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		// Validar campos obrigatórios
		if ( empty( $args['to_email'] ) || empty( $args['subject'] ) || empty( $args['message'] ) ) {
			return false;
		}

		// Se não especificou conta SMTP, selecionar automaticamente
		$smtp_account_id = $args['smtp_account'];
		if ( empty( $smtp_account_id ) ) {
			$smtp_account_id = $this->select_smtp_account();
		}

		// Se não definiu scheduled_at, calcular com base no rate limit
		if ( empty( $args['scheduled_at'] ) ) {
			$args['scheduled_at'] = $this->calculate_smtp_next_slot( $smtp_account_id );
		}

		// Preparar dados para salvar
		$message_data = array(
			'subject'     => $args['subject'],
			'body'        => $args['message'],
			'headers'     => $args['headers'],
			'attachments' => $args['attachments'],
			'metadata'    => $args['metadata'],
		);

		$result = $wpdb->insert(
			$this->table_name,
			array(
				'type'          => 'email',
				'to_number'     => $args['to_email'],
				'from_number'   => strval( $smtp_account_id ),
				'message'       => wp_json_encode( $message_data ),
				'contact_name'  => $args['subject'],
				'webhook_id'    => $args['webhook_id'],
				'automation_id' => $args['automation_id'],
				'priority'      => $args['priority'],
				'scheduled_at'  => $args['scheduled_at'],
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		if ( $result === false ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Calcular próximo slot disponível para envio de email
	 *
	 * @param int|string $account_id ID da conta SMTP
	 * @return string Data/hora do próximo slot
	 */
	private function calculate_smtp_next_slot( $account_id ) {
		global $wpdb;

		if ( empty( $account_id ) ) {
			return current_time( 'mysql' );
		}

		// Buscar configuração da conta
		$account_config = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->smtp_table} WHERE id = %d",
			absint( $account_id )
		) );

		if ( ! $account_config || empty( $account_config->rate_limit_hour ) ) {
			return current_time( 'mysql' );
		}

		// Buscar última mensagem agendada para esta conta
		$last_scheduled = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(scheduled_at) FROM {$this->table_name} 
			WHERE type = 'email' AND from_number = %s AND status = 'pending'",
			strval( $account_id )
		) );

		if ( ! $last_scheduled ) {
			return current_time( 'mysql' );
		}

		// Calcular intervalo entre mensagens (em segundos)
		$messages_per_hour = $account_config->rate_limit_hour;
		$interval_seconds = 3600 / $messages_per_hour;

		// Adicionar intervalo à última mensagem agendada
		$next_slot = strtotime( $last_scheduled ) + $interval_seconds;

		// Se o slot está no passado, usar agora
		if ( $next_slot < time() ) {
			return current_time( 'mysql' );
		}

		return date( 'Y-m-d H:i:s', $next_slot );
	}

	/**
	 * Selecionar número para envio (estratégia de distribuição)
	 * 
	 * A seleção segue a seguinte prioridade:
	 * 1. Se o destinatário já recebeu mensagem, usar o MESMO número (sticky number)
	 * 2. Se o número anterior não estiver disponível, usar outro como fallback
	 * 3. Se é a primeira mensagem para o destinatário, aplicar estratégia de distribuição
	 *
	 * @param string $to_number Número do destinatário (opcional, para sticky number)
	 * @param bool   $check_pending Se deve considerar mensagens pendentes no cálculo (default: true)
	 * @return string|null Número selecionado ou null
	 */
	public function select_number_for_sending( $to_number = '', $check_pending = true ) {
		global $wpdb;

		$now_mysql = current_time( 'mysql' );

		// 1. Verificar se o destinatário já recebeu mensagens anteriores (sticky number)
		if ( ! empty( $to_number ) ) {
			$sticky_number = $this->get_sticky_number_for_recipient( $to_number );
			
			if ( $sticky_number ) {
				// Verificar se o número sticky ainda está ativo e disponível
				$number_data = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM {$this->numbers_table} 
					WHERE phone_number = %s 
					AND status = 'active' 
					AND distribution_enabled = 1",
					$sticky_number
				) );

				if ( $number_data ) {
					// Verificar rate limit considerando mensagens pendentes também
					if ( ! $this->has_reached_rate_limit( $number_data, $check_pending ) ) {
						$this->log_cron( 'debug', "Sticky number encontrado para {$to_number}: {$sticky_number}" );
						return $sticky_number;
					} else {
						$this->log_cron( 'debug', "Sticky number {$sticky_number} atingiu rate limit, usando fallback" );
					}
				} else {
					$this->log_cron( 'debug', "Sticky number {$sticky_number} não está mais ativo, usando fallback" );
				}
			}
		}

		// 2. Se não tem sticky ou não está disponível, aplicar estratégia de distribuição
		$strategy = get_option( 'pcw_whatsapp_distribution_strategy', 'round_robin' );

		// Buscar números ativos e habilitados para distribuição
		$numbers = $wpdb->get_results(
			"SELECT * FROM {$this->numbers_table} 
			WHERE status = 'active' 
			AND distribution_enabled = 1 
			ORDER BY id ASC"
		);

		if ( empty( $numbers ) ) {
			// Fallback para número padrão
			$default_from = get_option( 'pcw_personizi_default_from', '' );
			$this->log_cron( 'debug', "Nenhum número na tabela, usando padrão: {$default_from}" );
			return $default_from;
		}

		// Filtrar números que não atingiram o rate limit
		// Incluir mensagens pendentes no cálculo para previsão mais precisa
		$available_numbers = array();
		foreach ( $numbers as $number ) {
			if ( ! $this->has_reached_rate_limit( $number, $check_pending ) ) {
				$available_numbers[] = $number;
			}
		}

		// Se todos atingiram o rate limit, retornar null (não forçar envio)
		if ( empty( $available_numbers ) ) {
			$this->log_cron( 'warning', 'Todos os números atingiram o rate limit horário' );
			return null;
		}

		// Aplicar estratégia de distribuição
		switch ( $strategy ) {
			case 'random':
				$selected = $available_numbers[ array_rand( $available_numbers ) ];
				break;

			case 'weighted':
				$selected = $this->select_by_weight( $available_numbers );
				break;

			case 'round_robin':
			default:
				// Buscar o último usado
				$last_used = get_option( 'pcw_whatsapp_last_used_index', 0 );
				$next_index = ( $last_used + 1 ) % count( $available_numbers );
				update_option( 'pcw_whatsapp_last_used_index', $next_index );
				$selected = $available_numbers[ $next_index ];
				break;
		}

		return $selected->phone_number;
	}

	/**
	 * Buscar número "sticky" para um destinatário
	 * 
	 * Verifica o histórico de mensagens enviadas com sucesso para o destinatário
	 * e retorna o número remetente mais recente.
	 *
	 * @param string $to_number Número do destinatário
	 * @return string|null Número remetente ou null se não encontrado
	 */
	private function get_sticky_number_for_recipient( $to_number ) {
		global $wpdb;

		// Buscar a última mensagem ENVIADA COM SUCESSO para este destinatário
		$last_from = $wpdb->get_var( $wpdb->prepare(
			"SELECT from_number FROM {$this->table_name} 
			WHERE to_number = %s 
			AND type = 'whatsapp' 
			AND status = 'sent' 
			AND from_number IS NOT NULL 
			AND from_number != ''
			ORDER BY processed_at DESC 
			LIMIT 1",
			$to_number
		) );

		if ( $last_from ) {
			return $last_from;
		}

		// Se não encontrou enviada, buscar pendente mais recente (pode ser do mesmo fluxo)
		$pending_from = $wpdb->get_var( $wpdb->prepare(
			"SELECT from_number FROM {$this->table_name} 
			WHERE to_number = %s 
			AND type = 'whatsapp' 
			AND status = 'pending' 
			AND from_number IS NOT NULL 
			AND from_number != ''
			ORDER BY created_at DESC 
			LIMIT 1",
			$to_number
		) );

		return $pending_from;
	}

	/**
	 * Selecionar número por peso (%)
	 *
	 * @param array $numbers Lista de números
	 * @return object Número selecionado
	 */
	private function select_by_weight( $numbers ) {
		$total_weight = array_sum( array_column( $numbers, 'distribution_weight' ) );
		$random = mt_rand( 1, $total_weight );
		
		$current_weight = 0;
		foreach ( $numbers as $number ) {
			$current_weight += $number->distribution_weight;
			if ( $random <= $current_weight ) {
				return $number;
			}
		}

		return $numbers[0];
	}

	/**
	 * Verificar se número atingiu rate limit
	 *
	 * @param object $number Dados do número
	 * @param bool   $include_pending Se deve incluir mensagens pendentes no cálculo
	 * @return bool
	 */
	private function has_reached_rate_limit( $number, $include_pending = true ) {
		global $wpdb;

		$limit = absint( $number->rate_limit_hour );
		if ( $limit <= 0 ) {
			return false;
		}

		$phone = $number->phone_number;
		$now_mysql = current_time( 'mysql' );

		// Consultar envios reais na última hora direto da tabela da fila (fonte única da verdade)
		$real_sent = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} 
			WHERE from_number = %s 
			AND type = 'whatsapp'
			AND status = 'sent' 
			AND processed_at >= DATE_SUB(%s, INTERVAL 1 HOUR)",
			$phone,
			$now_mysql
		) );

		// Se deve considerar pendentes, incluir mensagens agendadas para esta hora
		if ( $include_pending ) {
			$pending_this_hour = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} 
				WHERE from_number = %s 
				AND type = 'whatsapp'
				AND status = 'pending'
				AND scheduled_at >= DATE_SUB(%s, INTERVAL 1 HOUR)
				AND scheduled_at <= DATE_ADD(%s, INTERVAL 1 HOUR)",
				$phone,
				$now_mysql,
				$now_mysql
			) );
			
			$total = $real_sent + $pending_this_hour;
		} else {
			$total = $real_sent;
		}

		return $total >= $limit;
	}

	/**
	 * Resetar contador horário
	 *
	 * @param string $phone_number Número de telefone
	 */
	private function reset_hourly_counter( $phone_number ) {
		global $wpdb;
		$wpdb->update(
			$this->numbers_table,
			array(
				'sent_last_hour'  => 0,
				'sent_last_reset' => current_time( 'mysql' ),
			),
			array( 'phone_number' => $phone_number ),
			array( '%d', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Calcular próximo slot disponível para envio
	 *
	 * Algoritmo de distribuição temporal que garante:
	 * 1. Respeito rigoroso ao rate_limit_hour (máximo de mensagens por hora)
	 * 2. Espaçamento mínimo entre mensagens (min_interval_seconds)
	 * 3. Distribuição uniforme ao longo da hora quando há muitas mensagens
	 *
	 * @param string $from_number Número remetente
	 * @return string Data/hora do próximo slot
	 */
	private function calculate_next_available_slot( $from_number ) {
		global $wpdb;

		if ( empty( $from_number ) ) {
			return current_time( 'mysql' );
		}

		// Buscar configuração do número
		$number_config = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->numbers_table} WHERE phone_number = %s",
			$from_number
		) );

		if ( ! $number_config ) {
			return current_time( 'mysql' );
		}

		$now = current_time( 'timestamp' );
		$now_mysql = current_time( 'mysql' );
		$min_interval = isset( $number_config->min_interval_seconds ) ? absint( $number_config->min_interval_seconds ) : 30;
		$rate_limit = absint( $number_config->rate_limit_hour );

		// Se não tem rate limit, respeitar apenas min_interval
		if ( $rate_limit <= 0 ) {
			$last_scheduled = $wpdb->get_var( $wpdb->prepare(
				"SELECT MAX(scheduled_at) FROM {$this->table_name} 
				WHERE from_number = %s AND status = 'pending'",
				$from_number
			) );
			
			if ( $last_scheduled ) {
				$next_slot = max( $now, strtotime( $last_scheduled ) + $min_interval );
				return date( 'Y-m-d H:i:s', $next_slot );
			}
			
			return $now_mysql;
		}

		// ═══════ CÁLCULO PRECISO DO PRÓXIMO SLOT ═══════
		
		// 1. Contar envios reais na última hora
		$real_sent_hour = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} 
			WHERE from_number = %s 
			AND type = 'whatsapp'
			AND status = 'sent' 
			AND processed_at >= DATE_SUB(%s, INTERVAL 1 HOUR)",
			$from_number,
			$now_mysql
		) );

		// 2. Contar TODAS as mensagens pendentes (independente de quando estão agendadas)
		// Isso garante que não vamos enfileirar infinitamente
		$pending_total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} 
			WHERE from_number = %s 
			AND status = 'pending'
			AND type = 'whatsapp'",
			$from_number
		) );

		// Total de mensagens já alocadas para esta hora (enviadas + pendentes)
		$total_allocated = $real_sent_hour + $pending_total;

		// 3. Buscar a última mensagem agendada para calcular o próximo slot
		$last_scheduled = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(scheduled_at) FROM {$this->table_name} 
			WHERE from_number = %s 
			AND status = 'pending'
			AND type = 'whatsapp'",
			$from_number
		) );

		// Se não tem mensagens pendentes e ainda tem espaço no rate limit
		if ( $pending_total === 0 && $real_sent_hour < $rate_limit ) {
			return $now_mysql;
		}

		// Se já atingimos o limite da hora atual, calcular quando começa a próxima hora
		if ( $total_allocated >= $rate_limit ) {
			// Calcular quantas mensagens podemos enviar na próxima hora
			$overflow = $total_allocated - $rate_limit;
			$hour_offset = ceil( ( $overflow + 1 ) / $rate_limit ); // Em qual hora futura cabe esta mensagem
			
			// Calcular início da próxima hora disponível
			$next_hour_start = strtotime( date( 'Y-m-d H:00:00', $now ) ) + ( $hour_offset * 3600 );
			
			// Distribuir uniformemente ao longo dessa hora
			$position_in_hour = ( $overflow % $rate_limit ) + 1;
			$interval_in_hour = max( $min_interval, 3600 / $rate_limit );
			$slot_in_hour = ( $position_in_hour - 1 ) * $interval_in_hour;
			
			$next_slot = $next_hour_start + $slot_in_hour;
			
			$this->log_cron( 'info', "Slot calculado (overflow): {$from_number} -> " . date( 'Y-m-d H:i:s', $next_slot ) . " (pos {$position_in_hour}/{$rate_limit}, offset +{$hour_offset}h)" );
			
			return date( 'Y-m-d H:i:s', $next_slot );
		}

		// Ainda tem espaço na hora atual, mas precisa respeitar min_interval
		if ( $last_scheduled ) {
			$next_by_interval = strtotime( $last_scheduled ) + $min_interval;
			$next_slot = max( $now, $next_by_interval );
			
			// Verificar se ainda está dentro do rate limit da hora atual
			$hour_start = strtotime( date( 'Y-m-d H:00:00', $now ) );
			if ( $next_slot >= $hour_start + 3600 ) {
				// Slot cai na próxima hora, distribuir corretamente
				$next_hour_start = strtotime( date( 'Y-m-d H:00:00', $now ) ) + 3600;
				$overflow_position = $total_allocated - $rate_limit + 1;
				$interval_in_hour = max( $min_interval, 3600 / $rate_limit );
				$slot_in_hour = max( 0, $overflow_position - 1 ) * $interval_in_hour;
				$next_slot = $next_hour_start + $slot_in_hour;
			}
			
			return date( 'Y-m-d H:i:s', $next_slot );
		}

		// Fallback: agendar para agora se nenhuma condição acima foi atingida
		return $now_mysql;
	}

	/**
	 * Verificar se o horário atual está dentro do período permitido para disparos.
	 *
	 * @return bool True se pode disparar, false caso contrário.
	 */
	public function is_within_schedule() {
		$schedule = get_option( 'pcw_queue_schedule', array() );

		// Se não está habilitado, permite tudo
		if ( empty( $schedule['enabled'] ) ) {
			return true;
		}

		$start_hour = isset( $schedule['start_hour'] ) ? absint( $schedule['start_hour'] ) : 8;
		$end_hour   = isset( $schedule['end_hour'] ) ? absint( $schedule['end_hour'] ) : 18;
		$days       = isset( $schedule['days'] ) ? (array) $schedule['days'] : array( 1, 2, 3, 4, 5 );

		$current_hour = (int) current_time( 'G' ); // 0-23 sem zero à esquerda
		$current_day  = (int) current_time( 'w' ); // 0=dom, 1=seg, ..., 6=sáb

		// Verificar dia da semana
		if ( ! in_array( $current_day, $days, true ) ) {
			return false;
		}

		// Verificar horário
		if ( $current_hour < $start_hour || $current_hour >= $end_hour ) {
			return false;
		}

		return true;
	}

	/**
	 * Sincronizar contadores cached dos números com dados reais da fila.
	 *
	 * Atualiza sent_last_hour, sent_today, total_sent e total_failed na tabela
	 * pcw_whatsapp_numbers com base nos dados reais da tabela pcw_message_queue.
	 * Isso garante que o rate limiting e displays sempre usem dados precisos.
	 */
	public function sync_number_counters() {
		global $wpdb;

		$now = current_time( 'mysql' );

		// Buscar todos os números ativos
		$numbers = $wpdb->get_results(
			"SELECT id, phone_number FROM {$this->numbers_table} WHERE status = 'active'"
		);

		if ( empty( $numbers ) ) {
			return;
		}

		foreach ( $numbers as $number ) {
			$phone = $number->phone_number;

			// Contagem real de envios na última hora
			$real_sent_hour = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} 
				WHERE from_number = %s 
				AND type = 'whatsapp'
				AND status = 'sent' 
				AND processed_at >= DATE_SUB(%s, INTERVAL 1 HOUR)",
				$phone,
				$now
			) );

			// Contagem real de envios hoje
			$real_sent_today = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} 
				WHERE from_number = %s 
				AND type = 'whatsapp'
				AND status = 'sent' 
				AND DATE(processed_at) = CURDATE()",
				$phone
			) );

			// Total enviados (histórico completo)
			$real_total_sent = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} 
				WHERE from_number = %s 
				AND type = 'whatsapp'
				AND status = 'sent'",
				$phone
			) );

			// Total falhados (histórico completo)
			$real_total_failed = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} 
				WHERE from_number = %s 
				AND type = 'whatsapp'
				AND status = 'failed'",
				$phone
			) );

			// Atualizar a tabela de números com os dados reais
			$wpdb->update(
				$this->numbers_table,
				array(
					'sent_last_hour'  => $real_sent_hour,
					'sent_last_reset' => $now,
					'sent_today'      => $real_sent_today,
					'total_sent'      => $real_total_sent,
					'total_failed'    => $real_total_failed,
				),
				array( 'id' => $number->id ),
				array( '%d', '%s', '%d', '%d', '%d' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Normalizar mensagens pendentes agendadas muito no futuro.
	 *
	 * Corrige mensagens que ficaram com datas distantes devido ao cascateamento antigo.
	 * Redistribui espaçando por min_interval_seconds entre cada uma, começando de agora.
	 * O rate limit real é controlado pelo process_queue na hora de enviar.
	 */
	private function normalize_pending_schedules() {
		global $wpdb;

		$now = current_time( 'mysql' );
		$now_ts = current_time( 'timestamp' );

		// Limite: mensagens agendadas mais de 10 minutos no futuro são consideradas "atrasadas demais"
		$max_future = date( 'Y-m-d H:i:s', $now_ts + 600 );

		// Buscar mensagens pendentes agendadas para mais de 10 min no futuro
		$stuck_messages = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, from_number FROM {$this->table_name} 
			WHERE status = 'pending' 
			AND scheduled_at > %s 
			AND attempts < max_attempts 
			ORDER BY created_at ASC 
			LIMIT 200",
			$max_future
		) );

		if ( empty( $stuck_messages ) ) {
			return;
		}

		$count = count( $stuck_messages );
		$this->log_cron( 'info', "Normalizando {$count} mensagem(ns) pendente(s) agendadas muito no futuro" );

		// Agrupar por from_number para respeitar min_interval entre mensagens do mesmo número
		$grouped = array();
		foreach ( $stuck_messages as $msg ) {
			$from = $msg->from_number ?: '_default';
			if ( ! isset( $grouped[ $from ] ) ) {
				$grouped[ $from ] = array();
			}
			$grouped[ $from ][] = $msg->id;
		}

		foreach ( $grouped as $from_number => $msg_ids ) {
			// Buscar min_interval do número
			$min_interval = 30; // padrão
			if ( '_default' !== $from_number ) {
				$number_config = $wpdb->get_row( $wpdb->prepare(
					"SELECT min_interval_seconds FROM {$this->numbers_table} WHERE phone_number = %s",
					$from_number
				) );
				if ( $number_config && isset( $number_config->min_interval_seconds ) ) {
					$min_interval = absint( $number_config->min_interval_seconds );
				}
			}

			// Reagendar cada mensagem com espaçamento de min_interval a partir de agora
			$slot_ts = $now_ts;
			foreach ( $msg_ids as $msg_id ) {
				$wpdb->update(
					$this->table_name,
					array( 'scheduled_at' => date( 'Y-m-d H:i:s', $slot_ts ) ),
					array( 'id' => $msg_id ),
					array( '%s' ),
					array( '%d' )
				);
				$slot_ts += $min_interval;
			}

			$this->log_cron( 'info', "  Número {$from_number}: " . count( $msg_ids ) . " msg(s) reagendadas (intervalo: {$min_interval}s)" );
		}
	}

	/**
	 * Processar fila de mensagens.
	 *
	 * @param bool $force_schedule Se true, ignora a restrição de horários (usado para processamento manual via admin).
	 */
	public function process_queue( $force_schedule = false ) {
		global $wpdb;

		// Verificar se a fila está pausada
		if ( get_option( 'pcw_queue_paused', false ) ) {
			$this->log_cron( 'info', 'Fila PAUSADA - nenhuma mensagem será processada' );
			return;
		}

		// Verificar restrição de horários (pode ser ignorada se forçado manualmente)
		if ( ! $force_schedule && ! $this->is_within_schedule() ) {
			$this->log_cron( 'info', 'Fora do horário de disparo - mensagens aguardam na fila' );
			return;
		}

		$this->log_cron( 'info', '━━━━━ CRON: process_queue() iniciado ━━━━━' );

		// ═══════ LOCK DE CONCORRÊNCIA ═══════
		// Impedir múltiplas execuções simultâneas do process_queue
		$lock_key = 'pcw_process_queue_lock';
		$existing_lock = get_transient( $lock_key );
		if ( $existing_lock ) {
			$lock_age = time() - (int) $existing_lock;
			// Se o lock tem menos de 5 minutos, outra instância está rodando
			if ( $lock_age < 300 ) {
				$this->log_cron( 'info', "Outra instância do process_queue já está rodando há {$lock_age}s - abortando" );
				return;
			}
			// Se o lock expirou (> 5 min), é um lock órfão - continuar
			$this->log_cron( 'warning', "Lock órfão detectado ({$lock_age}s) - substituindo" );
		}
		set_transient( $lock_key, time(), 300 );

		// Sincronizar contadores dos números com dados reais da fila
		$this->sync_number_counters();

		// Normalizar mensagens pendentes que foram agendadas muito no futuro
		$this->normalize_pending_schedules();

		// ═══════ CONSULTA DE RATE LIMIT REAL (FONTE ÚNICA DA VERDADE) ═══════
		$now_mysql = current_time( 'mysql' );
		$now = current_time( 'timestamp' );
		
		// Contar envios reais na última hora POR NÚMERO direto da tabela da fila
		$real_hourly_counts = array();
		$hourly_data = $wpdb->get_results( $wpdb->prepare(
			"SELECT from_number, COUNT(*) as sent_count 
			FROM {$this->table_name} 
			WHERE status = 'sent' 
			AND type = 'whatsapp' 
			AND processed_at >= DATE_SUB(%s, INTERVAL 1 HOUR) 
			AND from_number IS NOT NULL 
			AND from_number != '' 
			GROUP BY from_number",
			$now_mysql
		) );
		foreach ( $hourly_data as $row ) {
			$real_hourly_counts[ $row->from_number ] = (int) $row->sent_count;
		}

		// Buscar configurações de todos os números ativos
		$active_numbers = $wpdb->get_results(
			"SELECT * FROM {$this->numbers_table} WHERE status = 'active'"
		);
		
		$numbers_map = array();
		$number_limits = array();
		foreach ( $active_numbers as $num ) {
			$numbers_map[ $num->phone_number ] = $num;
			$number_limits[ $num->phone_number ] = array(
				'limit' => absint( $num->rate_limit_hour ),
				'min_interval' => isset( $num->min_interval_seconds ) ? absint( $num->min_interval_seconds ) : 30,
			);
		}

		// ═══════ CÁLCULO DE CAPACIDADE POR NÚMERO ═══════
		// Determinar quantas mensagens podemos enviar por número nesta execução
		$available_capacity = array();
		foreach ( $number_limits as $phone => $config ) {
			$real_sent = isset( $real_hourly_counts[ $phone ] ) ? $real_hourly_counts[ $phone ] : 0;
			$limit = $config['limit'];
			
			if ( $limit <= 0 ) {
				// Sem limite definido, usar limite conservador de 10/hora
				$available_capacity[ $phone ] = max( 0, 10 - $real_sent );
			} else {
				$available_capacity[ $phone ] = max( 0, $limit - $real_sent );
			}
			
			$this->log_cron( 'info', "Capacidade {$phone}: {$available_capacity[$phone]}/{$limit} (já enviados: {$real_sent})" );
		}

		// Buscar mensagens prontas para envio
		// ═══════ QUERY OTIMIZADA QUE RESPEITA RATE LIMITS ═══════
		$messages = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$this->table_name} 
			WHERE status = 'pending' 
			AND scheduled_at <= %s 
			AND attempts < max_attempts 
			ORDER BY priority DESC, scheduled_at ASC 
			LIMIT 50",
			$now_mysql
		) );

		$count = $messages ? count( $messages ) : 0;
		$this->log_cron( 'info', "Mensagens prontas para envio: {$count}" );

		if ( empty( $messages ) ) {
			delete_transient( $lock_key );
			return;
		}

		$processed_count = 0;
		$skipped_count = 0;
		// Controle local: quantas enviamos nesta execução por número
		$sent_this_run = array();
		// Controle de intervalo mínimo: timestamp do último envio por número nesta execução
		$last_send_time = array();

		foreach ( $messages as $message ) {
			// Para WhatsApp, aplicar rate limiting rigoroso
			if ( 'whatsapp' === $message->type ) {
				$from = $message->from_number;
				
				// Se from está vazio, selecionar número
				if ( empty( $from ) ) {
					$from = $this->select_number_for_sending( $message->to_number );
					if ( $from ) {
						$wpdb->update(
							$this->table_name,
							array( 'from_number' => $from ),
							array( 'id' => $message->id ),
							array( '%s' ),
							array( '%d' )
						);
						$message->from_number = $from;
					}
				}
				
				// ═══════ RATE LIMITING RIGOROSO ═══════
				if ( ! empty( $from ) ) {
					// Buscar limite e intervalo do número
					$limit = 10; // padrão conservador
					$min_interval = 30;
					
					if ( isset( $number_limits[ $from ] ) ) {
						$limit = $number_limits[ $from ]['limit'];
						$min_interval = $number_limits[ $from ]['min_interval'];
					}

					// Calcular total real: envios na última hora (do DB) + envios nesta execução
					$real_sent = isset( $real_hourly_counts[ $from ] ) ? $real_hourly_counts[ $from ] : 0;
					$run_count = isset( $sent_this_run[ $from ] ) ? $sent_this_run[ $from ] : 0;
					$already_sent = $real_sent + $run_count;
					
					// ═══════ VERIFICAÇÃO DE LIMITE HORÁRIO ═══════
					if ( $limit > 0 && $already_sent >= $limit ) {
						// Rate limit atingido - reagendar para próxima hora com distribuição
						$overflow_position = $already_sent - $limit + 1;
						$interval_in_hour = max( $min_interval, 3600 / max( 1, $limit ) );
						$slot_in_hour = ( $overflow_position - 1 ) * $interval_in_hour;
						$next_slot_ts = strtotime( date( 'Y-m-d H:00:00', $now ) ) + 3600 + $slot_in_hour;
						$next_slot = date( 'Y-m-d H:i:s', $next_slot_ts );
						
						$wpdb->update(
							$this->table_name,
							array( 'scheduled_at' => $next_slot ),
							array( 'id' => $message->id ),
							array( '%s' ),
							array( '%d' )
						);
						
						$this->log_cron( 'warning', "RATE LIMIT: Msg #{$message->id}: {$from} atingiu limite ({$already_sent}/{$limit}), reagendada para {$next_slot}" );
						$skipped_count++;
						continue;
					}

					// ═══════ VERIFICAÇÃO DE INTERVALO MÍNIMO ═══════
					if ( $min_interval > 0 && isset( $last_send_time[ $from ] ) ) {
						$elapsed = time() - $last_send_time[ $from ];
						if ( $elapsed < $min_interval ) {
							// Muito cedo para enviar com este número, reagendar
							$wait_seconds = $min_interval - $elapsed;
							$next_slot = date( 'Y-m-d H:i:s', time() + $wait_seconds );
							$wpdb->update(
								$this->table_name,
								array( 'scheduled_at' => $next_slot ),
								array( 'id' => $message->id ),
								array( '%s' ),
								array( '%d' )
							);
							$this->log_cron( 'debug', "Msg #{$message->id}: min_interval de {$min_interval}s para {$from}, reagendada para {$next_slot}" );
							$skipped_count++;
							continue;
						}
					}

					$this->log_cron( 'info', "Msg #{$message->id}: {$from} ok para envio ({$already_sent}/{$limit})" );
				} else {
					// Nenhum número disponível, reagendar para 5 minutos
					$next_slot = date( 'Y-m-d H:i:s', strtotime( '+5 minutes', $now ) );
					$wpdb->update(
						$this->table_name,
						array( 'scheduled_at' => $next_slot ),
						array( 'id' => $message->id ),
						array( '%s' ),
						array( '%d' )
					);
					
					$this->log_cron( 'warning', "Msg #{$message->id}: Nenhum número disponível, reagendada para {$next_slot}" );
					$skipped_count++;
					continue;
				}
			}
			
			// Enviar mensagem
			if ( 'email' === $message->type ) {
				$this->process_email_message( $message );
			} else {
				$this->process_message( $message );
				
				// Atualizar controle de envios após processamento bem-sucedido
				$actual_from = $message->from_number;
				if ( ! empty( $actual_from ) ) {
					if ( ! isset( $sent_this_run[ $actual_from ] ) ) {
						$sent_this_run[ $actual_from ] = 0;
					}
					$sent_this_run[ $actual_from ]++;
					$last_send_time[ $actual_from ] = time();
					
					// Atualizar contador real também
					if ( ! isset( $real_hourly_counts[ $actual_from ] ) ) {
						$real_hourly_counts[ $actual_from ] = 0;
					}
					$real_hourly_counts[ $actual_from ]++;
				}
			}
			
			$processed_count++;
		}

		// Sincronizar contadores ao final para manter tudo atualizado
		$this->sync_number_counters();

		// Liberar lock de concorrência
		delete_transient( $lock_key );

		$this->log_cron( 'info', "━━━━━ process_queue() finalizado: {$processed_count} enviadas, {$skipped_count} reagendadas ━━━━━" );
	}

	/**
	 * Verificar capacidade disponível de envio para todos os números
	 *
	 * Retorna um array com a capacidade restante de cada número ativo,
	 * considerando envios já realizados e mensagens pendentes.
	 *
	 * @return array [phone_number => capacity]
	 */
	public function get_available_capacity() {
		global $wpdb;
		
		$now_mysql = current_time( 'mysql' );
		$capacities = array();
		
		// Buscar todos os números ativos
		$numbers = $wpdb->get_results(
			"SELECT * FROM {$this->numbers_table} WHERE status = 'active' AND distribution_enabled = 1"
		);
		
		foreach ( $numbers as $num ) {
			$phone = $num->phone_number;
			$limit = absint( $num->rate_limit_hour );
			
			if ( $limit <= 0 ) {
				$limit = 60; // Padrão conservador
			}
			
			// Contar envios na última hora
			$sent = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} 
				WHERE from_number = %s 
				AND type = 'whatsapp'
				AND status = 'sent' 
				AND processed_at >= DATE_SUB(%s, INTERVAL 1 HOUR)",
				$phone,
				$now_mysql
			) );
			
			// Contar mensagens pendentes agendadas para esta hora
			$pending = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} 
				WHERE from_number = %s 
				AND type = 'whatsapp'
				AND status = 'pending'
				AND scheduled_at >= DATE_SUB(%s, INTERVAL 1 HOUR)
				AND scheduled_at <= DATE_ADD(%s, INTERVAL 1 HOUR)",
				$phone,
				$now_mysql,
				$now_mysql
			) );
			
			$capacities[ $phone ] = max( 0, $limit - $sent - $pending );
		}
		
		return $capacities;
	}

	/**
	 * Obter rate limits de todos os números
	 *
	 * @return array
	 */
	private function get_all_number_rate_limits() {
		global $wpdb;
		
		$numbers = $wpdb->get_results(
			"SELECT phone_number, rate_limit_hour FROM {$this->numbers_table} WHERE status = 'active'"
		);
		
		$limits = array();
		foreach ( $numbers as $num ) {
			$limits[ $num->phone_number ] = absint( $num->rate_limit_hour );
		}
		
		return $limits;
	}

	/**
	 * Log do processamento do cron
	 *
	 * @param string $level Nível (debug, info, warning, error).
	 * @param string $message Mensagem.
	 */
	private function log_cron( $level, $message ) {
		// Log no WooCommerce
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->log( $level, $message, array( 'source' => 'pcw-queue-cron' ) );
		}
	}

	/**
	 * Processar uma mensagem individual
	 *
	 * @param object $message Dados da mensagem
	 */
	private function process_message( $message ) {
		global $wpdb;

		$this->log_cron( 'debug', "Mensagem #{$message->id}: Tentativa " . ( $message->attempts + 1 ) . " de {$message->max_attempts}" );

		// Incrementar tentativas
		$wpdb->update(
			$this->table_name,
			array( 'attempts' => $message->attempts + 1 ),
			array( 'id' => $message->id ),
			array( '%d' ),
			array( '%d' )
		);

		// Verificar se Personizi está disponível
		if ( ! class_exists( 'PCW_Personizi_Integration' ) ) {
			$this->log_cron( 'error', "Mensagem #{$message->id}: PCW_Personizi_Integration não disponível" );
			return;
		}

		// Enviar mensagem
		$personizi = PCW_Personizi_Integration::instance();
		
		// Preparar parâmetros
		$to = $message->to_number;
		$msg_text = $message->message;
		$contact_name = $message->contact_name ? $message->contact_name : '';
		$from = $message->from_number ? $message->from_number : '';
		
		// Se from estiver vazio, selecionar via distribuição da fila
		if ( empty( $from ) ) {
			$from = $this->select_number_for_sending( $to );
			
			// Se ainda vazio, usar padrão como último fallback
			if ( empty( $from ) ) {
				$from = get_option( 'pcw_personizi_default_from', '' );
			}
			
			// Atualizar a mensagem com o número selecionado
			if ( ! empty( $from ) ) {
				$wpdb->update(
					$this->table_name,
					array( 'from_number' => $from ),
					array( 'id' => $message->id ),
					array( '%s' ),
					array( '%d' )
				);
				$message->from_number = $from;
			}
		}
		
		$is_template = ( $message->type === 'whatsapp_template' || ! empty( $message->template_name ) );

		// Detectar provider do número para logging
		$number_provider = 'evolution';
		if ( ! empty( $from ) ) {
			$number_data = $wpdb->get_row( $wpdb->prepare(
				"SELECT provider FROM {$this->numbers_table} WHERE phone_number = %s LIMIT 1",
				$from
			) );
			if ( $number_data && ! empty( $number_data->provider ) ) {
				$number_provider = $number_data->provider;
			}
		}

		$this->log_cron( 'info', "Mensagem #{$message->id}: provider={$number_provider}, is_template=" . ( $is_template ? 'sim' : 'não' ) . ", from={$from}, to={$to}" );

		if ( $is_template ) {
			$template_params = array();
			if ( ! empty( $message->template_params ) ) {
				$decoded = json_decode( $message->template_params, true );
				if ( is_array( $decoded ) ) {
					$template_params = $decoded;
				}
			}

			$this->log_cron( 'info', "Mensagem #{$message->id}: Enviando template '{$message->template_name}' via {$number_provider}" );

			$result = $personizi->send_template_message(
				$to,
				$from,
				$message->template_name,
				$template_params,
				! empty( $message->template_language ) ? $message->template_language : 'pt_BR',
				$contact_name,
				! empty( $message->template_body_text ) ? $message->template_body_text : ''
			);
		} else {
			$this->log_cron( 'info', "Mensagem #{$message->id}: Enviando mensagem normal via {$number_provider}" );

			$result = $personizi->send_whatsapp_message(
				$to,
				$msg_text,
				$contact_name,
				$from
			);
		}

		if ( is_wp_error( $result ) ) {
			// Falha no envio
			$status = ( $message->attempts + 1 >= $message->max_attempts ) ? 'failed' : 'pending';
			$error_msg = $result->get_error_message();
			$error_code = $result->get_error_code();
			$error_data = $result->get_error_data();
			
			// Montar response_data com detalhes completos do erro para debug
			$error_response = array(
				'error_code'    => $error_code,
				'error_message' => $error_msg,
				'error_data'    => $error_data,
				'from_number'   => $message->from_number,
				'to_number'     => $message->to_number,
				'attempt'       => $message->attempts + 1,
				'timestamp'     => current_time( 'mysql' ),
			);
			
			$this->log_cron( 'error', "Mensagem #{$message->id}: ✗ FALHA [{$error_code}] - {$error_msg} (status: {$status})" );
			if ( $error_data ) {
				$this->log_cron( 'debug', "Mensagem #{$message->id}: Error data: " . wp_json_encode( $error_data ) );
			}
			
			// Reagendar se não atingiu max attempts
			if ( $status === 'pending' ) {
				// Tentar usar outro número como fallback
				$fallback_number = $this->get_fallback_number( $message->from_number, $message->to_number );
				$next_slot = $this->calculate_next_available_slot( $fallback_number );
				
				$this->log_cron( 'debug', "Mensagem #{$message->id}: Fallback de {$message->from_number} para {$fallback_number}" );
				$this->log_cron( 'debug', "Mensagem #{$message->id}: Reagendada para {$next_slot}" );
				
				$wpdb->update(
					$this->table_name,
					array(
						'status'        => $status,
						'error_message' => $error_msg,
						'from_number'   => $fallback_number,
						'scheduled_at'  => $next_slot,
						'response_data' => wp_json_encode( $error_response ),
					),
					array( 'id' => $message->id ),
					array( '%s', '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);
			} else {
				// Falha definitiva
				$wpdb->update(
					$this->table_name,
					array(
						'status'        => $status,
						'error_message' => $error_msg,
						'processed_at'  => current_time( 'mysql' ),
						'response_data' => wp_json_encode( $error_response ),
					),
					array( 'id' => $message->id ),
					array( '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);
				
				// Incrementar contador de falhas do número
				$this->increment_number_failed_counter( $message->from_number );
			}
		} else {
			// Sucesso
			$this->log_cron( 'info', "Mensagem #{$message->id}: ✓ ENVIADA com sucesso para {$message->to_number}" );
			
			$wpdb->update(
				$this->table_name,
				array(
					'status'        => 'sent',
					'processed_at'  => current_time( 'mysql' ),
					'response_data' => wp_json_encode( $result ),
				),
				array( 'id' => $message->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			// Incrementar contador do número
			$this->increment_number_counter( $message->from_number );
		}
	}

	/**
	 * Incrementar contador de envios do número
	 *
	 * @param string $phone_number Número de telefone
	 */
	private function increment_number_counter( $phone_number ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$this->numbers_table} 
			SET sent_last_hour = sent_last_hour + 1, 
			    sent_today = sent_today + 1,
			    total_sent = total_sent + 1 
			WHERE phone_number = %s",
			$phone_number
		) );
	}

	/**
	 * Incrementar contador de falhas do número
	 *
	 * @param string $phone_number Número de telefone
	 */
	private function increment_number_failed_counter( $phone_number ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$this->numbers_table} 
			SET total_failed = total_failed + 1 
			WHERE phone_number = %s",
			$phone_number
		) );
	}

	/**
	 * Obter número de fallback quando o número principal falha
	 * 
	 * Busca outro número ativo diferente do atual para tentar o envio
	 *
	 * @param string $current_number Número atual que falhou
	 * @param string $to_number Número do destinatário
	 * @return string Número de fallback ou o mesmo se não houver alternativa
	 */
	private function get_fallback_number( $current_number, $to_number ) {
		global $wpdb;

		// Buscar números ativos e habilitados, excluindo o atual
		// e que ainda não atingiram o rate limit
		$fallback_numbers = $wpdb->get_results( $wpdb->prepare(
			"SELECT phone_number, rate_limit_hour, sent_last_hour, sent_last_reset 
			FROM {$this->numbers_table} 
			WHERE status = 'active' 
			AND distribution_enabled = 1 
			AND phone_number != %s
			ORDER BY sent_last_hour ASC
			LIMIT 5",
			$current_number
		) );

		if ( ! empty( $fallback_numbers ) ) {
			foreach ( $fallback_numbers as $num ) {
				// Verificar se o contador precisa ser resetado
				$last_reset = $num->sent_last_reset ? strtotime( $num->sent_last_reset ) : 0;
				$sent = absint( $num->sent_last_hour );
				if ( $last_reset < ( time() - 3600 ) ) {
					$sent = 0; // Contador expirado, considerar como zero
				}

				$limit = absint( $num->rate_limit_hour );
				if ( $limit <= 0 || $sent < $limit ) {
					$this->log_cron( 'info', "Fallback encontrado: de {$current_number} para {$num->phone_number} ({$sent}/{$limit})" );
					return $num->phone_number;
				}
			}
		}

		// Se não encontrou fallback dentro do limite, usar o mesmo número
		$this->log_cron( 'warning', "Nenhum fallback disponível dentro do rate limit, mantendo {$current_number}" );
		return $current_number;
	}

	/**
	 * Processar uma mensagem de email individual
	 *
	 * @param object $message Dados da mensagem
	 */
	private function process_email_message( $message ) {
		global $wpdb, $pcw_processing_queue;

		// Marcar que está processando a fila (evita loops)
		$pcw_processing_queue = true;

		// Incrementar tentativas
		$wpdb->update(
			$this->table_name,
			array( 'attempts' => $message->attempts + 1 ),
			array( 'id' => $message->id ),
			array( '%d' ),
			array( '%d' )
		);

		// Decodificar dados do email
		$email_data = json_decode( $message->message, true );
		if ( ! $email_data ) {
			$wpdb->update(
				$this->table_name,
				array(
					'status'        => 'failed',
					'error_message' => 'Dados do email inválidos',
				),
				array( 'id' => $message->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			$pcw_processing_queue = false;
			return;
		}

		$to_email    = $message->to_number;
		$subject     = isset( $email_data['subject'] ) ? $email_data['subject'] : '';
		$body        = isset( $email_data['body'] ) ? $email_data['body'] : '';
		$headers     = isset( $email_data['headers'] ) ? $email_data['headers'] : array();
		$attachments = isset( $email_data['attachments'] ) ? $email_data['attachments'] : array();
		$account_id  = $message->from_number;

		// Tentar enviar o email
		$result = $this->send_queued_email( $to_email, $subject, $body, $headers, $attachments, $account_id );

		if ( is_wp_error( $result ) ) {
			// Falha no envio
			$status = ( $message->attempts + 1 >= $message->max_attempts ) ? 'failed' : 'pending';
			
			$wpdb->update(
				$this->table_name,
				array(
					'status'        => $status,
					'error_message' => $result->get_error_message(),
				),
				array( 'id' => $message->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			// Reagendar se não atingiu max attempts
			if ( $status === 'pending' ) {
				$next_slot = $this->calculate_smtp_next_slot( $account_id );
				$wpdb->update(
					$this->table_name,
					array( 'scheduled_at' => $next_slot ),
					array( 'id' => $message->id ),
					array( '%s' ),
					array( '%d' )
				);
			}

			// Incrementar contador de falhas
			if ( $status === 'failed' && is_numeric( $account_id ) ) {
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$this->smtp_table} SET total_failed = total_failed + 1 WHERE id = %d",
					absint( $account_id )
				) );
			}

			// Limpar flag se terminou com falha definitiva
			if ( $status === 'failed' ) {
				$pcw_processing_queue = false;
				return;
			}
		} else {
			// Sucesso
			$wpdb->update(
				$this->table_name,
				array(
					'status'        => 'sent',
					'processed_at'  => current_time( 'mysql' ),
					'response_data' => 'Email enviado com sucesso',
				),
				array( 'id' => $message->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			// Incrementar contador da conta SMTP
			if ( is_numeric( $account_id ) ) {
				$this->increment_smtp_counter( absint( $account_id ) );
			}
		}

		// Limpar flag de processamento
		$pcw_processing_queue = false;
	}

	/**
	 * Enviar email da fila usando conta SMTP específica
	 *
	 * @param string     $to Email destinatário.
	 * @param string     $subject Assunto.
	 * @param string     $body Corpo do email.
	 * @param array      $headers Headers.
	 * @param array      $attachments Anexos.
	 * @param int|string $account_id ID da conta SMTP.
	 * @return bool|WP_Error
	 */
	private function send_queued_email( $to, $subject, $body, $headers = array(), $attachments = array(), $account_id = null ) {
		// Se tem conta SMTP específica, usar ela
		if ( ! empty( $account_id ) && is_numeric( $account_id ) && class_exists( 'PCW_SMTP_Accounts' ) ) {
			$smtp_accounts = PCW_SMTP_Accounts::instance();
			return $smtp_accounts->send_email( absint( $account_id ), $to, $subject, $body, $headers );
		}

		// Fallback: usar wp_mail padrão
		$default_headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$all_headers = array_merge( $default_headers, (array) $headers );

		$result = wp_mail( $to, $subject, $body, $all_headers, (array) $attachments );

		if ( ! $result ) {
			return new WP_Error( 'email_failed', __( 'Falha ao enviar email via wp_mail', 'person-cash-wallet' ) );
		}

		return true;
	}

	/**
	 * Obter estatísticas da fila
	 *
	 * @param string $type Tipo de mensagem (vazio = todos, 'whatsapp', 'email')
	 * @return array Estatísticas
	 */
	public function get_queue_stats( $type = '' ) {
		global $wpdb;

		$where = '';
		if ( ! empty( $type ) ) {
			$where = $wpdb->prepare( " AND type = %s", $type );
		}

		return array(
			'pending' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'pending'" . $where ),
			'sent'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'sent'" . $where ),
			'failed'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'failed'" . $where ),
			'total'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE 1=1" . $where ),
		);
	}

	/**
	 * Obter estatísticas separadas por tipo
	 *
	 * @return array Estatísticas por tipo
	 */
	public function get_queue_stats_by_type() {
		return array(
			'all'      => $this->get_queue_stats(),
			'whatsapp' => $this->get_queue_stats( 'whatsapp' ),
			'email'    => $this->get_queue_stats( 'email' ),
		);
	}

	/**
	 * Obter estatísticas dos números
	 *
	 * @return array Estatísticas por número
	 */
	public function get_numbers_stats() {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM {$this->numbers_table} ORDER BY id ASC" );
	}

	/**
	 * Adicionar ou atualizar número WhatsApp
	 *
	 * @param array $args Dados do número
	 * @return int|false ID do número ou false
	 */
	public function save_whatsapp_number( $args ) {
		global $wpdb;

		$defaults = array(
			'id'                    => 0,
			'phone_number'          => '',
			'name'                  => '',
			'provider'              => 'evolution',
			'account_id'            => null,
			'status'                => 'active',
			'rate_limit_hour'       => 60,
			'min_interval_seconds'  => 30,
			'distribution_weight'   => 100,
			'distribution_enabled'  => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['phone_number'] ) ) {
			return false;
		}

		$data = array(
			'phone_number'          => $args['phone_number'],
			'name'                  => $args['name'],
			'provider'              => $args['provider'],
			'account_id'            => $args['account_id'],
			'status'                => $args['status'],
			'rate_limit_hour'       => absint( $args['rate_limit_hour'] ),
			'min_interval_seconds'  => absint( $args['min_interval_seconds'] ),
			'distribution_weight'   => absint( $args['distribution_weight'] ),
			'distribution_enabled'  => absint( $args['distribution_enabled'] ),
		);

		$format = array( '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%d' );

		if ( $args['id'] > 0 ) {
			$result = $wpdb->update(
				$this->numbers_table,
				$data,
				array( 'id' => $args['id'] ),
				$format,
				array( '%d' )
			);
			
			if ( function_exists( 'wc_get_logger' ) && $result === false ) {
				$logger = wc_get_logger();
				$logger->error( 'Erro ao atualizar número WhatsApp: ' . $wpdb->last_error . ' | Query: ' . $wpdb->last_query, array( 'source' => 'pcw-queue' ) );
			}
			
			return $result !== false ? $args['id'] : false;
		} else {
			$result = $wpdb->insert( $this->numbers_table, $data, $format );
			
			if ( function_exists( 'wc_get_logger' ) && $result === false ) {
				$logger = wc_get_logger();
				$logger->error( 'Erro ao inserir número WhatsApp: ' . $wpdb->last_error . ' | Query: ' . $wpdb->last_query, array( 'source' => 'pcw-queue' ) );
			}
			
			return $result !== false ? $wpdb->insert_id : false;
		}
	}

	/**
	 * Deletar número WhatsApp
	 *
	 * @param int $id ID do número
	 * @return bool
	 */
	public function delete_whatsapp_number( $id ) {
		global $wpdb;
		return $wpdb->delete( $this->numbers_table, array( 'id' => $id ), array( '%d' ) ) !== false;
	}

	/**
	 * Limpar fila (remover mensagens antigas)
	 *
	 * @param int $days Dias para manter
	 */
	public function cleanup_queue( $days = 30 ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$this->table_name} 
			WHERE status IN ('sent', 'failed') 
			AND processed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days
		) );
	}

	/**
	 * Selecionar conta SMTP para envio (estratégia de distribuição)
	 *
	 * @return int|null ID da conta selecionada ou null
	 */
	public function select_smtp_account() {
		global $wpdb;

		$strategy = get_option( 'pcw_smtp_distribution_strategy', 'round_robin' );

		// Buscar contas ativas e habilitadas para distribuição
		$accounts = $wpdb->get_results(
			"SELECT * FROM {$this->smtp_table} 
			WHERE status = 'active' 
			AND distribution_enabled = 1 
			ORDER BY id ASC"
		);

		if ( empty( $accounts ) ) {
			return null;
		}

		// Filtrar contas que não atingiram o rate limit
		$available_accounts = array();
		foreach ( $accounts as $account ) {
			if ( ! $this->smtp_reached_rate_limit( $account ) ) {
				$available_accounts[] = $account;
			}
		}

		// Se todas atingiram o rate limit, usar a que tem menor uso
		if ( empty( $available_accounts ) ) {
			usort( $accounts, function( $a, $b ) {
				return $a->sent_last_hour - $b->sent_last_hour;
			});
			$available_accounts = array( $accounts[0] );
		}

		// Aplicar estratégia de distribuição
		switch ( $strategy ) {
			case 'random':
				$selected = $available_accounts[ array_rand( $available_accounts ) ];
				break;

			case 'weighted':
				$selected = $this->select_smtp_by_weight( $available_accounts );
				break;

			case 'round_robin':
			default:
				$last_used = get_option( 'pcw_smtp_last_used_index', 0 );
				$next_index = ( $last_used + 1 ) % count( $available_accounts );
				update_option( 'pcw_smtp_last_used_index', $next_index );
				$selected = $available_accounts[ $next_index ];
				break;
		}

		return $selected->id;
	}

	/**
	 * Selecionar conta SMTP por peso (%)
	 *
	 * @param array $accounts Lista de contas
	 * @return object Conta selecionada
	 */
	private function select_smtp_by_weight( $accounts ) {
		$total_weight = array_sum( array_column( $accounts, 'distribution_weight' ) );
		$random = mt_rand( 1, $total_weight );
		
		$current_weight = 0;
		foreach ( $accounts as $account ) {
			$current_weight += $account->distribution_weight;
			if ( $random <= $current_weight ) {
				return $account;
			}
		}

		return $accounts[0];
	}

	/**
	 * Verificar se conta SMTP atingiu rate limit
	 *
	 * @param object $account Dados da conta
	 * @return bool
	 */
	private function smtp_reached_rate_limit( $account ) {
		// Resetar contador se passou 1 hora
		$last_reset = $account->sent_last_reset ? strtotime( $account->sent_last_reset ) : 0;
		$one_hour_ago = time() - 3600;

		if ( $last_reset < $one_hour_ago ) {
			$this->reset_smtp_hourly_counter( $account->id );
			return false;
		}

		return $account->sent_last_hour >= $account->rate_limit_hour;
	}

	/**
	 * Resetar contador horário da conta SMTP
	 *
	 * @param int $account_id ID da conta
	 */
	private function reset_smtp_hourly_counter( $account_id ) {
		global $wpdb;
		$wpdb->update(
			$this->smtp_table,
			array(
				'sent_last_hour'  => 0,
				'sent_last_reset' => current_time( 'mysql' ),
			),
			array( 'id' => $account_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Incrementar contador de envios da conta SMTP
	 *
	 * @param int $account_id ID da conta
	 */
	public function increment_smtp_counter( $account_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$this->smtp_table} 
			SET sent_last_hour = sent_last_hour + 1, 
			    sent_today = sent_today + 1,
			    total_sent = total_sent + 1 
			WHERE id = %d",
			$account_id
		) );
	}

	/**
	 * Obter estatísticas das contas SMTP
	 *
	 * @return array Estatísticas por conta
	 */
	public function get_smtp_stats() {
		global $wpdb;
		$accounts = $wpdb->get_results( "SELECT * FROM {$this->smtp_table} ORDER BY id ASC" );
		
		// Adicionar contas do FluentSMTP se estiver instalado
		$fluent_accounts = $this->get_fluentsmtp_connections();
		
		if ( ! empty( $fluent_accounts ) ) {
			foreach ( $fluent_accounts as $fluent_account ) {
				// Verificar se já existe no sistema (evitar duplicatas)
				$exists = false;
				foreach ( $accounts as $account ) {
					// Verificar por fluent_connection_id ou por email
					if ( ( isset( $account->fluent_connection_id ) && $account->fluent_connection_id == $fluent_account->id ) ||
					     ( isset( $account->from_email ) && $account->from_email === $fluent_account->sender_email ) ) {
						$exists = true;
						break;
					}
				}
				
				if ( ! $exists && ! empty( $fluent_account->sender_email ) ) {
					// Criar objeto simulado para exibição
					$fluent_obj = (object) array(
						'id'                   => 'fluent_' . $fluent_account->id,
						'name'                 => $fluent_account->title . ' (FluentSMTP)',
						'from_email'           => $fluent_account->sender_email,
						'from_name'            => $fluent_account->sender_name ?? $fluent_account->title,
						'provider'             => 'fluentsmtp',
						'status'               => 'active',
						'rate_limit_hour'      => 60,
						'distribution_weight'  => 0,
						'distribution_enabled' => 0,
						'sent_last_hour'       => 0,
						'sent_today'           => 0,
						'total_sent'           => 0,
						'total_failed'         => 0,
						'fluent_connection_id' => $fluent_account->id,
						'is_fluent'            => true,
					);
					$accounts[] = $fluent_obj;
				}
			}
		}
		
		// Adicionar contas SendPulse
		$sendpulse_table = $wpdb->prefix . 'pcw_sendpulse_accounts';
		$sendpulse_accounts = $wpdb->get_results(
			"SELECT * FROM {$sendpulse_table} WHERE status = 'active' ORDER BY id ASC"
		);
		
		if ( ! empty( $sendpulse_accounts ) ) {
			foreach ( $sendpulse_accounts as $sp_account ) {
				// Verificar se já existe no sistema
				$sp_exists = false;
				foreach ( $accounts as $account ) {
					if ( isset( $account->sendpulse_id ) && $account->sendpulse_id == $sp_account->id ) {
						$sp_exists = true;
						break;
					}
				}
				
				if ( ! $sp_exists ) {
					$sp_obj = (object) array(
						'id'                   => 'sendpulse_' . $sp_account->id,
						'name'                 => $sp_account->name . ' (SendPulse)',
						'from_email'           => $sp_account->from_email,
						'from_name'            => $sp_account->from_name,
						'provider'             => 'sendpulse',
						'status'               => $sp_account->status,
						'rate_limit_hour'      => $sp_account->rate_limit_hour,
						'distribution_weight'  => $sp_account->distribution_weight,
						'distribution_enabled' => $sp_account->distribution_enabled,
						'sent_last_hour'       => $sp_account->sent_last_hour,
						'sent_today'           => $sp_account->sent_today,
						'total_sent'           => $sp_account->total_sent,
						'total_failed'         => $sp_account->total_failed,
						'sendpulse_id'         => $sp_account->id,
						'is_sendpulse'         => true,
					);
					$accounts[] = $sp_obj;
				}
			}
		}
		
		return $accounts;
	}

	/**
	 * Obter conexões do FluentSMTP
	 *
	 * @return array
	 */
	private function get_fluentsmtp_connections() {
		global $wpdb;
		
		$connections = array();

		// Método 1: Buscar direto na tabela pcw_smtp_accounts onde provider é fluentsmtp
		$smtp_table = $wpdb->prefix . 'pcw_smtp_accounts';
		$fluent_accounts = $wpdb->get_results(
			"SELECT * FROM {$smtp_table} WHERE provider = 'fluentsmtp' ORDER BY id ASC"
		);
		
		if ( ! empty( $fluent_accounts ) ) {
			foreach ( $fluent_accounts as $account ) {
				$connections[] = (object) array(
					'id'           => $account->fluent_connection_id ?? $account->id,
					'title'        => $account->name,
					'sender_email' => $account->from_email,
					'sender_name'  => $account->from_name,
					'provider'     => 'fluentsmtp',
				);
			}
			return $connections;
		}

		// Método 2: Tentar detectar FluentSMTP ativo
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		$fluent_active = is_plugin_active( 'fluent-smtp/fluent-smtp.php' ) || 
		                 is_plugin_active( 'fluentcrm/fluentcrm.php' ) ||
		                 defined( 'FLUENTMAIL' ) ||
		                 defined( 'FLUENTCRM' );
		
		if ( ! $fluent_active ) {
			return array();
		}

		// Método 3: Buscar na opção fluentmail-settings
		$fluentmail_settings = get_option( 'fluentmail-settings', array() );
		if ( ! empty( $fluentmail_settings ) && isset( $fluentmail_settings['connections'] ) ) {
			foreach ( $fluentmail_settings['connections'] as $id => $conn ) {
				if ( isset( $conn['sender_email'] ) && ! empty( $conn['sender_email'] ) ) {
					$connections[] = (object) array(
						'id'           => $id,
						'title'        => $conn['sender_name'] ?? 'FluentSMTP Connection',
						'sender_email' => $conn['sender_email'],
						'sender_name'  => $conn['sender_name'] ?? '',
						'provider'     => 'fluentsmtp',
					);
				}
			}
		}

		// Método 4: Buscar na tabela do banco
		$table = $wpdb->prefix . 'fluentmail_settings';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
			$db_connections = $wpdb->get_results( "SELECT * FROM {$table}" );
			
			foreach ( $db_connections as $conn ) {
				$value = maybe_unserialize( $conn->value );
				if ( is_array( $value ) && isset( $value['sender_email'] ) && ! empty( $value['sender_email'] ) ) {
					$connections[] = (object) array(
						'id'           => $conn->id,
						'title'        => $value['sender_name'] ?? 'FluentSMTP',
						'sender_email' => $value['sender_email'],
						'sender_name'  => $value['sender_name'] ?? '',
						'provider'     => 'fluentsmtp',
					);
				}
			}
		}

		// Método 5: Opção global fluentmail_settings (sem hífen)
		if ( empty( $connections ) ) {
			$global_settings = get_option( 'fluentmail_settings', array() );
			if ( ! empty( $global_settings ) && isset( $global_settings['sender_email'] ) && ! empty( $global_settings['sender_email'] ) ) {
				$connections[] = (object) array(
					'id'           => 0,
					'title'        => 'FluentMail Global',
					'sender_email' => $global_settings['sender_email'],
					'sender_name'  => $global_settings['sender_name'] ?? '',
					'provider'     => 'fluentmail',
				);
			}
		}

		return $connections;
	}
}
