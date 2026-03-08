<?php
/**
 * Análise RFM (Recency, Frequency, Monetary)
 *
 * Sistema de segmentação automática de clientes baseado em:
 * - Recência: Quando foi a última compra
 * - Frequência: Quantas vezes comprou
 * - Monetário: Quanto gastou no total
 *
 * @package PersonCashWallet
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de análise RFM
 */
class PCW_RFM_Analysis {

	/**
	 * Instância singleton
	 *
	 * @var PCW_RFM_Analysis
	 */
	private static $instance = null;

	/**
	 * Obter instância
	 *
	 * @return PCW_RFM_Analysis
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
		// Privado para singleton
	}

	/**
	 * Calcular RFM para todos os clientes
	 *
	 * @return array Resultado do cálculo.
	 */
	public function calculate_all_customers() {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'posts';
		$postmeta_table = $wpdb->prefix . 'postmeta';

		// Buscar todos os clientes com pedidos
		$sql = "
			SELECT 
				pm.meta_value as user_id,
				COUNT(DISTINCT p.ID) as total_orders,
				MAX(p.post_date) as last_order_date,
				DATEDIFF(NOW(), MAX(p.post_date)) as days_since_last_order,
				SUM(CAST(pm2.meta_value AS DECIMAL(10,2))) as total_spent
			FROM {$orders_table} p
			INNER JOIN {$postmeta_table} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_user'
			INNER JOIN {$postmeta_table} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_order_total'
			WHERE p.post_type = 'shop_order'
			AND p.post_status IN ('wc-completed', 'wc-processing')
			AND pm.meta_value > 0
			GROUP BY pm.meta_value
			HAVING total_orders > 0
		";

		$customers = $wpdb->get_results( $sql, ARRAY_A );

		if ( empty( $customers ) ) {
			return array(
				'success'   => false,
				'message'   => __( 'Nenhum cliente encontrado', 'person-cash-wallet' ),
				'processed' => 0,
			);
		}

		// Calcular quartis
		$recency_values = array_column( $customers, 'days_since_last_order' );
		$frequency_values = array_column( $customers, 'total_orders' );
		$monetary_values = array_column( $customers, 'total_spent' );

		$recency_quartiles = $this->calculate_quartiles( $recency_values, true ); // Inverso: menor é melhor
		$frequency_quartiles = $this->calculate_quartiles( $frequency_values, false );
		$monetary_quartiles = $this->calculate_quartiles( $monetary_values, false );

		// Processar cada cliente
		$processed = 0;
		foreach ( $customers as $customer ) {
			$user_id = absint( $customer['user_id'] );

			// Calcular scores (1-4, sendo 4 o melhor)
			$recency_score = $this->calculate_score( $customer['days_since_last_order'], $recency_quartiles, true );
			$frequency_score = $this->calculate_score( $customer['total_orders'], $frequency_quartiles, false );
			$monetary_score = $this->calculate_score( $customer['total_spent'], $monetary_quartiles, false );

			// Determinar segmento
			$segment = $this->determine_segment( $recency_score, $frequency_score, $monetary_score );

			// Salvar no banco
			$this->save_rfm_data( array(
				'user_id'              => $user_id,
				'recency_score'        => $recency_score,
				'frequency_score'      => $frequency_score,
				'monetary_score'       => $monetary_score,
				'segment'              => $segment,
				'last_order_date'      => $customer['last_order_date'],
				'days_since_last_order' => $customer['days_since_last_order'],
				'total_orders'         => $customer['total_orders'],
				'total_spent'          => $customer['total_spent'],
				'average_order_value'  => $customer['total_spent'] / $customer['total_orders'],
			) );

			$processed++;
		}

		return array(
			'success'   => true,
			'message'   => sprintf( __( '%d clientes processados', 'person-cash-wallet' ), $processed ),
			'processed' => $processed,
		);
	}

	/**
	 * Calcular quartis
	 *
	 * @param array $values Valores.
	 * @param bool  $reverse Se deve inverter (para recência).
	 * @return array Quartis.
	 */
	private function calculate_quartiles( $values, $reverse = false ) {
		sort( $values );

		if ( $reverse ) {
			$values = array_reverse( $values );
		}

		$count = count( $values );

		return array(
			'q1' => $values[ (int) floor( $count * 0.25 ) ],
			'q2' => $values[ (int) floor( $count * 0.50 ) ],
			'q3' => $values[ (int) floor( $count * 0.75 ) ],
		);
	}

	/**
	 * Calcular score baseado em quartis
	 *
	 * @param float $value Valor.
	 * @param array $quartiles Quartis.
	 * @param bool  $reverse Se deve inverter.
	 * @return int Score de 1 a 4.
	 */
	private function calculate_score( $value, $quartiles, $reverse = false ) {
		if ( $reverse ) {
			// Para recência: menor valor = score maior
			if ( $value <= $quartiles['q1'] ) {
				return 4;
			} elseif ( $value <= $quartiles['q2'] ) {
				return 3;
			} elseif ( $value <= $quartiles['q3'] ) {
				return 2;
			} else {
				return 1;
			}
		} else {
			// Para frequência e monetário: maior valor = score maior
			if ( $value >= $quartiles['q3'] ) {
				return 4;
			} elseif ( $value >= $quartiles['q2'] ) {
				return 3;
			} elseif ( $value >= $quartiles['q1'] ) {
				return 2;
			} else {
				return 1;
			}
		}
	}

	/**
	 * Determinar segmento baseado nos scores RFM
	 *
	 * @param int $r Recency score.
	 * @param int $f Frequency score.
	 * @param int $m Monetary score.
	 * @return string Segmento.
	 */
	private function determine_segment( $r, $f, $m ) {
		$rfm_string = "{$r}{$f}{$m}";

		// Champions: Melhores clientes
		if ( $r >= 4 && $f >= 4 && $m >= 4 ) {
			return 'champions';
		}

		// Loyal Customers: Compram frequentemente
		if ( $r >= 3 && $f >= 4 ) {
			return 'loyal_customers';
		}

		// Potential Loyalists: Clientes recentes com potencial
		if ( $r >= 4 && $f >= 2 && $f <= 3 ) {
			return 'potential_loyalists';
		}

		// New Customers: Clientes novos
		if ( $r >= 4 && $f <= 2 && $m <= 2 ) {
			return 'new_customers';
		}

		// Promising: Compradores recentes com bom valor
		if ( $r >= 3 && $f <= 2 && $m >= 3 ) {
			return 'promising';
		}

		// Need Attention: Clientes acima da média que não compram recentemente
		if ( $r >= 2 && $r <= 3 && $f >= 2 && $f <= 3 ) {
			return 'need_attention';
		}

		// About to Sleep: Precisam de atenção urgente
		if ( $r <= 3 && $f <= 2 ) {
			return 'about_to_sleep';
		}

		// At Risk: Alto valor mas não compram recentemente
		if ( $r <= 2 && $f >= 2 && $m >= 3 ) {
			return 'at_risk';
		}

		// Can't Lose Them: Melhores clientes que estão se perdendo
		if ( $r <= 2 && $f >= 4 && $m >= 4 ) {
			return 'cant_lose';
		}

		// Hibernating: Não compram há muito tempo
		if ( $r <= 2 && $f <= 2 && $m <= 2 ) {
			return 'hibernating';
		}

		// Lost: Clientes perdidos
		if ( $r === 1 ) {
			return 'lost';
		}

		return 'others';
	}

	/**
	 * Salvar dados RFM no banco
	 *
	 * @param array $data Dados RFM.
	 * @return bool
	 */
	private function save_rfm_data( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_rfm_segments';

		$rfm_score = $data['recency_score'] . $data['frequency_score'] . $data['monetary_score'];

		// Verificar se já existe
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE user_id = %d",
			$data['user_id']
		) );

		$save_data = array(
			'user_id'              => $data['user_id'],
			'recency_score'        => $data['recency_score'],
			'frequency_score'      => $data['frequency_score'],
			'monetary_score'       => $data['monetary_score'],
			'rfm_score'            => $rfm_score,
			'segment'              => $data['segment'],
			'last_order_date'      => $data['last_order_date'],
			'days_since_last_order' => $data['days_since_last_order'],
			'total_orders'         => $data['total_orders'],
			'total_spent'          => $data['total_spent'],
			'average_order_value'  => $data['average_order_value'],
			'calculated_at'        => current_time( 'mysql' ),
			'expires_at'           => date( 'Y-m-d H:i:s', strtotime( '+7 days' ) ), // Recalcular a cada 7 dias
		);

		if ( $exists ) {
			$wpdb->update(
				$table,
				$save_data,
				array( 'user_id' => $data['user_id'] ),
				array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$table,
				$save_data,
				array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%s', '%s' )
			);
		}

		return true;
	}

	/**
	 * Obter segmento de um usuário
	 *
	 * @param int $user_id ID do usuário.
	 * @return object|null
	 */
	public function get_user_segment( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_rfm_segments';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d",
			$user_id
		) );
	}

	/**
	 * Obter usuários de um segmento
	 *
	 * @param string $segment Nome do segmento.
	 * @return array
	 */
	public function get_segment_users( $segment ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_rfm_segments';

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE segment = %s ORDER BY total_spent DESC",
			$segment
		) );
	}

	/**
	 * Obter estatísticas dos segmentos
	 *
	 * @return array
	 */
	public function get_segments_stats() {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_rfm_segments';

		return $wpdb->get_results(
			"SELECT 
				segment,
				COUNT(*) as total_customers,
				SUM(total_spent) as total_revenue,
				AVG(total_spent) as avg_revenue,
				AVG(total_orders) as avg_orders,
				AVG(days_since_last_order) as avg_days_since_purchase
			FROM {$table}
			GROUP BY segment
			ORDER BY total_revenue DESC"
		);
	}

	/**
	 * Obter nome legível do segmento
	 *
	 * @param string $segment Segmento.
	 * @return string
	 */
	public static function get_segment_label( $segment ) {
		$labels = array(
			'champions'           => __( 'Campeões', 'person-cash-wallet' ),
			'loyal_customers'     => __( 'Clientes Fiéis', 'person-cash-wallet' ),
			'potential_loyalists' => __( 'Potenciais Fiéis', 'person-cash-wallet' ),
			'new_customers'       => __( 'Novos Clientes', 'person-cash-wallet' ),
			'promising'           => __( 'Promissores', 'person-cash-wallet' ),
			'need_attention'      => __( 'Precisam Atenção', 'person-cash-wallet' ),
			'about_to_sleep'      => __( 'Prestes a Dormir', 'person-cash-wallet' ),
			'at_risk'             => __( 'Em Risco', 'person-cash-wallet' ),
			'cant_lose'           => __( 'Não Pode Perder', 'person-cash-wallet' ),
			'hibernating'         => __( 'Hibernando', 'person-cash-wallet' ),
			'lost'                => __( 'Perdidos', 'person-cash-wallet' ),
			'others'              => __( 'Outros', 'person-cash-wallet' ),
		);

		return isset( $labels[ $segment ] ) ? $labels[ $segment ] : $segment;
	}

	/**
	 * Obter descrição do segmento
	 *
	 * @param string $segment Segmento.
	 * @return string
	 */
	public static function get_segment_description( $segment ) {
		$descriptions = array(
			'champions'           => __( 'Seus melhores clientes: compram frequentemente e gastam muito.', 'person-cash-wallet' ),
			'loyal_customers'     => __( 'Compram regularmente e respondem bem a promoções.', 'person-cash-wallet' ),
			'potential_loyalists' => __( 'Clientes recentes com potencial de se tornarem fiéis.', 'person-cash-wallet' ),
			'new_customers'       => __( 'Compraram recentemente pela primeira vez.', 'person-cash-wallet' ),
			'promising'           => __( 'Compradores recentes com alto ticket médio.', 'person-cash-wallet' ),
			'need_attention'      => __( 'Compradores acima da média que não compram recentemente.', 'person-cash-wallet' ),
			'about_to_sleep'     => __( 'Clientes inativos que precisam de campanhas de reativação.', 'person-cash-wallet' ),
			'at_risk'             => __( 'Gastaram muito mas não compram há algum tempo. Não perca!', 'person-cash-wallet' ),
			'cant_lose'           => __( 'Eram seus melhores clientes mas pararam de comprar.', 'person-cash-wallet' ),
			'hibernating'         => __( 'Não compram há muito tempo. Difícil recuperar.', 'person-cash-wallet' ),
			'lost'                => __( 'Provavelmente perdidos. Baixa chance de recuperação.', 'person-cash-wallet' ),
			'others'              => __( 'Segmento indefinido.', 'person-cash-wallet' ),
		);

		return isset( $descriptions[ $segment ] ) ? $descriptions[ $segment ] : '';
	}

	/**
	 * Obter cor do segmento
	 *
	 * @param string $segment Segmento.
	 * @return string
	 */
	public static function get_segment_color( $segment ) {
		$colors = array(
			'champions'           => '#10b981', // Verde
			'loyal_customers'     => '#22c55e', // Verde claro
			'potential_loyalists' => '#3b82f6', // Azul
			'new_customers'       => '#06b6d4', // Ciano
			'promising'           => '#8b5cf6', // Roxo
			'need_attention'      => '#f59e0b', // Laranja
			'about_to_sleep'      => '#f97316', // Laranja escuro
			'at_risk'             => '#ef4444', // Vermelho
			'cant_lose'           => '#dc2626', // Vermelho escuro
			'hibernating'         => '#991b1b', // Vermelho mais escuro
			'lost'                => '#7f1d1d', // Vermelho muito escuro
			'others'              => '#6b7280', // Cinza
		);

		return isset( $colors[ $segment ] ) ? $colors[ $segment ] : '#6b7280';
	}
}
