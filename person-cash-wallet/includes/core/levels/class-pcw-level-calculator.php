<?php
/**
 * Classe calculadora de níveis
 *
 * @package PersonCashWallet
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe calculadora de níveis
 */
class PCW_Level_Calculator {

	/**
	 * Calcular nível do usuário
	 *
	 * @param int $user_id ID do usuário.
	 * @return int|false ID do nível ou false
	 */
	public static function calculate_user_level( $user_id ) {
		$all_levels = PCW_Levels::get_all_levels( array( 'status' => 'active' ) );

		if ( empty( $all_levels ) ) {
			return false;
		}

		$user_metrics = self::get_user_metrics( $user_id );
		$highest_qualified = null;

		// Verificar cada nível do menor para o maior
		foreach ( $all_levels as $level ) {
			if ( self::user_meets_requirements( $user_id, $level, $user_metrics ) ) {
				$highest_qualified = $level;
			}
		}

		if ( $highest_qualified ) {
			$current_level = PCW_Levels::get_user_level( $user_id );

			// Se não tem nível ou é diferente, atribuir novo nível
			if ( ! $current_level || $current_level->id !== $highest_qualified->id ) {
				PCW_Levels::assign_level( $user_id, $highest_qualified->id );

				// Se tinha nível anterior e é menor, remover
				if ( $current_level && $current_level->level_number < $highest_qualified->level_number ) {
					// Não remover, apenas atualizar (upgrade)
				} elseif ( $current_level && $current_level->level_number > $highest_qualified->level_number ) {
					// Downgrade - remover nível anterior
					PCW_Levels::remove_user_level( $user_id, $current_level->id );
				}

				return $highest_qualified->id;
			}

			return $current_level->id;
		}

		return false;
	}

	/**
	 * Obter métricas do usuário
	 *
	 * @param int $user_id ID do usuário.
	 * @return array
	 */
	public static function get_user_metrics( $user_id ) {
		return array(
			'total_spent'    => self::get_total_spent( $user_id ),
			'order_count'    => self::get_order_count( $user_id ),
			'item_count'     => self::get_item_count( $user_id ),
			'last_order_date' => self::get_last_order_date( $user_id ),
		);
	}

	/**
	 * Verificar se usuário atende requisitos do nível
	 *
	 * @param int    $user_id ID do usuário.
	 * @param object $level Nível.
	 * @param array  $metrics Métricas do usuário.
	 * @return bool
	 */
	private static function user_meets_requirements( $user_id, $level, $metrics ) {
		$requirements = self::get_level_requirements( $level->id );

		if ( empty( $requirements ) ) {
			return false; // Nível sem requisitos não pode ser atribuído
		}

		$meets_all = true;

		foreach ( $requirements as $requirement ) {
			$meets = false;

			switch ( $requirement->requirement_type ) {
				case 'total_spent':
					$period_value = self::get_total_spent( $user_id, $requirement->period_type, $requirement->period_start, $requirement->period_end );
					$meets = $period_value >= floatval( $requirement->requirement_value );
					break;

				case 'order_count':
					$period_value = self::get_order_count( $user_id, $requirement->period_type, $requirement->period_start, $requirement->period_end );
					$meets = $period_value >= floatval( $requirement->requirement_value );
					break;

				case 'item_count':
					$period_value = self::get_item_count( $user_id, $requirement->period_type, $requirement->period_start, $requirement->period_end );
					$meets = $period_value >= floatval( $requirement->requirement_value );
					break;
			}

			if ( ! $meets ) {
				$meets_all = false;
				break;
			}
		}

		return $meets_all;
	}

	/**
	 * Obter requisitos do nível
	 *
	 * @param int $level_id ID do nível.
	 * @return array
	 */
	private static function get_level_requirements( $level_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pcw_level_requirements';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE level_id = %d",
				absint( $level_id )
			)
		);
	}

	/**
	 * Obter total gasto do usuário
	 *
	 * @param int    $user_id ID do usuário.
	 * @param string $period Período.
	 * @param string $start Data início.
	 * @param string $end Data fim.
	 * @return float
	 */
	private static function get_total_spent( $user_id, $period = 'lifetime', $start = null, $end = null ) {
		global $wpdb;

		$date_condition = '';

		switch ( $period ) {
			case 'last_30_days':
				$date_condition = "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
				break;
			case 'last_90_days':
				$date_condition = "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
				break;
			case 'last_year':
				$date_condition = "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
				break;
			case 'custom':
				if ( $start && $end ) {
					$date_condition = $wpdb->prepare( "AND p.post_date BETWEEN %s AND %s", $start, $end );
				}
				break;
		}

		$sql = $wpdb->prepare(
			"SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2)))
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE p.post_author = %d
			AND p.post_type = 'shop_order'
			AND p.post_status IN ('wc-completed', 'wc-processing')
			AND pm.meta_key = '_order_total'
			{$date_condition}",
			$user_id
		);

		return floatval( $wpdb->get_var( $sql ) );
	}

	/**
	 * Obter quantidade de pedidos
	 *
	 * @param int    $user_id ID do usuário.
	 * @param string $period Período.
	 * @param string $start Data início.
	 * @param string $end Data fim.
	 * @return int
	 */
	private static function get_order_count( $user_id, $period = 'lifetime', $start = null, $end = null ) {
		global $wpdb;

		$date_condition = '';

		switch ( $period ) {
			case 'last_30_days':
				$date_condition = "AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
				break;
			case 'last_90_days':
				$date_condition = "AND post_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
				break;
			case 'last_year':
				$date_condition = "AND post_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
				break;
			case 'custom':
				if ( $start && $end ) {
					$date_condition = $wpdb->prepare( "AND post_date BETWEEN %s AND %s", $start, $end );
				}
				break;
		}

		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_author = %d
			AND post_type = 'shop_order'
			AND post_status IN ('wc-completed', 'wc-processing')
			{$date_condition}",
			$user_id
		);

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Obter quantidade de itens comprados
	 *
	 * @param int    $user_id ID do usuário.
	 * @param string $period Período.
	 * @param string $start Data início.
	 * @param string $end Data fim.
	 * @return int
	 */
	private static function get_item_count( $user_id, $period = 'lifetime', $start = null, $end = null ) {
		global $wpdb;

		$date_condition = '';

		switch ( $period ) {
			case 'last_30_days':
				$date_condition = "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
				break;
			case 'last_90_days':
				$date_condition = "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
				break;
			case 'last_year':
				$date_condition = "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
				break;
			case 'custom':
				if ( $start && $end ) {
					$date_condition = $wpdb->prepare( "AND p.post_date BETWEEN %s AND %s", $start, $end );
				}
				break;
		}

		$sql = $wpdb->prepare(
			"SELECT SUM(CAST(om.meta_value AS UNSIGNED))
			FROM {$wpdb->postmeta} om
			INNER JOIN {$wpdb->posts} p ON p.ID = om.post_id
			WHERE p.post_author = %d
			AND p.post_type = 'shop_order'
			AND p.post_status IN ('wc-completed', 'wc-processing')
			AND om.meta_key = '_qty'
			{$date_condition}",
			$user_id
		);

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Obter data do último pedido
	 *
	 * @param int $user_id ID do usuário.
	 * @return string|null
	 */
	private static function get_last_order_date( $user_id ) {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_date FROM {$wpdb->posts}
				WHERE post_author = %d
				AND post_type = 'shop_order'
				AND post_status IN ('wc-completed', 'wc-processing')
				ORDER BY post_date DESC
				LIMIT 1",
				$user_id
			)
		);
	}
}
