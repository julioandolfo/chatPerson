<?php
/**
 * Diagnóstico de Gateway - Person Cash Wallet
 * Acesse via navegador para verificar se o gateway está registrado
 */

// Carregar WordPress
require_once 'wp-load.php';

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Sem permissão' );
}

echo '<h1>🔍 Diagnóstico de Gateway - Person Cash Wallet</h1>';
echo '<style>
	body { 
		font-family: sans-serif; 
		max-width: 1200px; 
		margin: 20px auto; 
		padding: 20px; 
	} 
	.success { color: green; font-weight: bold; } 
	.error { color: red; font-weight: bold; } 
	.warning { color: orange; font-weight: bold; } 
	.info { color: blue; } 
	pre { 
		background: #f5f5f5; 
		padding: 15px; 
		border-radius: 5px; 
		overflow: auto; 
		border-left: 4px solid #667eea;
	} 
	.section { 
		margin: 30px 0; 
		padding: 20px; 
		border: 1px solid #ddd; 
		border-radius: 8px; 
		background: white;
	}
	.badge {
		display: inline-block;
		padding: 4px 12px;
		border-radius: 4px;
		font-size: 12px;
		font-weight: bold;
		margin-left: 8px;
	}
	.badge-success { background: #d4edda; color: #155724; }
	.badge-error { background: #f8d7da; color: #721c24; }
	.badge-warning { background: #fff3cd; color: #856404; }
	h2 { border-bottom: 2px solid #667eea; padding-bottom: 10px; }
</style>';

// Teste 1: Verificar se WooCommerce está ativo
echo '<div class="section">';
echo '<h2>1️⃣ Verificação do WooCommerce</h2>';

if ( ! class_exists( 'WooCommerce' ) ) {
	echo '<p class="error">❌ WooCommerce NÃO está instalado ou ativo!</p>';
	echo '<p>O gateway de pagamento Wallet requer o WooCommerce para funcionar.</p>';
} else {
	echo '<p class="success">✅ WooCommerce está ativo</p>';
	echo '<p class="info">Versão: ' . esc_html( WC()->version ) . '</p>';
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	echo '<p class="error">❌ Classe WC_Payment_Gateway não encontrada!</p>';
} else {
	echo '<p class="success">✅ Classe WC_Payment_Gateway disponível</p>';
}

echo '</div>';

// Teste 2: Verificar se a classe do gateway existe
echo '<div class="section">';
echo '<h2>2️⃣ Verificação da Classe do Gateway</h2>';

$gateway_file = WP_PLUGIN_DIR . '/person-cash-wallet/includes/integrations/class-pcw-payment-gateway.php';

if ( ! file_exists( $gateway_file ) ) {
	echo '<p class="error">❌ Arquivo do gateway não encontrado!</p>';
	echo '<p><code>' . esc_html( $gateway_file ) . '</code></p>';
} else {
	echo '<p class="success">✅ Arquivo do gateway existe</p>';
	echo '<p><code>' . esc_html( $gateway_file ) . '</code></p>';
}

if ( ! class_exists( 'PCW_Payment_Gateway' ) ) {
	echo '<p class="error">❌ Classe PCW_Payment_Gateway NÃO está carregada!</p>';
	echo '<p class="warning">Isso significa que o plugin não conseguiu carregar o gateway.</p>';
} else {
	echo '<p class="success">✅ Classe PCW_Payment_Gateway está carregada</p>';
	
	// Informações da classe
	$reflection = new ReflectionClass( 'PCW_Payment_Gateway' );
	echo '<p class="info">Arquivo da classe: ' . esc_html( $reflection->getFileName() ) . '</p>';
}

echo '</div>';

// Teste 3: Verificar gateways registrados
echo '<div class="section">';
echo '<h2>3️⃣ Gateways Registrados no WooCommerce</h2>';

if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) ) {
	$payment_gateways = WC()->payment_gateways->payment_gateways();
	
	echo '<p><strong>Total de gateways:</strong> ' . count( $payment_gateways ) . '</p>';
	
	$found_wallet = false;
	
	echo '<table style="width: 100%; border-collapse: collapse; margin-top: 15px;">';
	echo '<thead>';
	echo '<tr style="background: #f5f5f5;">';
	echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">ID</th>';
	echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Título</th>';
	echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Classe</th>';
	echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Status</th>';
	echo '</tr>';
	echo '</thead>';
	echo '<tbody>';
	
	foreach ( $payment_gateways as $gateway ) {
		$is_wallet = ( $gateway->id === 'pcw_wallet' );
		if ( $is_wallet ) {
			$found_wallet = true;
		}
		
		$row_style = $is_wallet ? 'background: #d4edda;' : '';
		
		echo '<tr style="' . $row_style . '">';
		echo '<td style="padding: 10px; border: 1px solid #ddd;"><code>' . esc_html( $gateway->id ) . '</code></td>';
		echo '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html( $gateway->title ) . '</td>';
		echo '<td style="padding: 10px; border: 1px solid #ddd;"><code>' . esc_html( get_class( $gateway ) ) . '</code></td>';
		echo '<td style="padding: 10px; border: 1px solid #ddd;">';
		
		if ( $gateway->enabled === 'yes' ) {
			echo '<span class="badge badge-success">✅ Ativo</span>';
		} else {
			echo '<span class="badge badge-warning">⚠️ Desativado</span>';
		}
		
		if ( $is_wallet ) {
			echo '<span class="badge badge-success">🎯 WALLET</span>';
		}
		
		echo '</td>';
		echo '</tr>';
	}
	
	echo '</tbody>';
	echo '</table>';
	
	if ( $found_wallet ) {
		echo '<p class="success" style="margin-top: 20px; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px;">';
		echo '✅ <strong>Gateway Wallet encontrado e registrado!</strong>';
		echo '</p>';
	} else {
		echo '<p class="error" style="margin-top: 20px; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 6px;">';
		echo '❌ <strong>Gateway Wallet NÃO encontrado na lista de gateways!</strong><br>';
		echo 'O gateway não está sendo registrado corretamente.';
		echo '</p>';
	}
} else {
	echo '<p class="error">❌ Não foi possível acessar WC()->payment_gateways</p>';
}

echo '</div>';

// Teste 4: Verificar filtro
echo '<div class="section">';
echo '<h2>4️⃣ Verificação de Hooks e Filtros</h2>';

global $wp_filter;

if ( isset( $wp_filter['woocommerce_payment_gateways'] ) ) {
	echo '<p class="success">✅ Filtro <code>woocommerce_payment_gateways</code> existe</p>';
	
	$callbacks = $wp_filter['woocommerce_payment_gateways']->callbacks;
	
	echo '<p><strong>Callbacks registrados:</strong></p>';
	echo '<ul>';
	
	$found_pcw = false;
	foreach ( $callbacks as $priority => $hooks ) {
		foreach ( $hooks as $hook ) {
			$callback_name = '';
			
			if ( is_array( $hook['function'] ) ) {
				if ( is_string( $hook['function'][0] ) ) {
					$callback_name = $hook['function'][0] . '::' . $hook['function'][1];
				} else {
					$callback_name = get_class( $hook['function'][0] ) . '->' . $hook['function'][1];
				}
			} elseif ( is_string( $hook['function'] ) ) {
				$callback_name = $hook['function'];
			}
			
			if ( strpos( $callback_name, 'PCW' ) !== false || strpos( $callback_name, 'Gateway_Loader' ) !== false ) {
				echo '<li class="success">✅ <strong>' . esc_html( $callback_name ) . '</strong> (prioridade: ' . $priority . ')</li>';
				$found_pcw = true;
			} else {
				echo '<li>' . esc_html( $callback_name ) . ' (prioridade: ' . $priority . ')</li>';
			}
		}
	}
	
	echo '</ul>';
	
	if ( ! $found_pcw ) {
		echo '<p class="error">⚠️ Nenhum callback do Person Cash Wallet encontrado neste filtro!</p>';
	}
} else {
	echo '<p class="error">❌ Filtro <code>woocommerce_payment_gateways</code> não encontrado</p>';
}

echo '</div>';

// Teste 5: Testar instanciar o gateway manualmente
echo '<div class="section">';
echo '<h2>5️⃣ Teste de Instanciação Manual</h2>';

if ( class_exists( 'PCW_Payment_Gateway' ) ) {
	try {
		$test_gateway = new PCW_Payment_Gateway();
		
		echo '<p class="success">✅ Gateway instanciado com sucesso!</p>';
		echo '<p><strong>Propriedades:</strong></p>';
		echo '<ul>';
		echo '<li><strong>ID:</strong> <code>' . esc_html( $test_gateway->id ) . '</code></li>';
		echo '<li><strong>Título:</strong> ' . esc_html( $test_gateway->method_title ) . '</li>';
		echo '<li><strong>Descrição:</strong> ' . esc_html( $test_gateway->method_description ) . '</li>';
		echo '<li><strong>Habilitado:</strong> ' . ( $test_gateway->enabled === 'yes' ? '<span class="success">Sim</span>' : '<span class="error">Não</span>' ) . '</li>';
		echo '</ul>';
		
		if ( method_exists( $test_gateway, 'is_available' ) ) {
			$available = $test_gateway->is_available();
			echo '<p><strong>Disponível para uso:</strong> ';
			if ( $available ) {
				echo '<span class="success">✅ Sim</span>';
			} else {
				echo '<span class="warning">⚠️ Não (pode ser porque não há usuário logado ou sem saldo)</span>';
			}
			echo '</p>';
		}
		
	} catch ( Exception $e ) {
		echo '<p class="error">❌ Erro ao instanciar: ' . esc_html( $e->getMessage() ) . '</p>';
	}
} else {
	echo '<p class="error">❌ Não é possível testar - classe não existe</p>';
}

echo '</div>';

// Teste 6: Links úteis
echo '<div class="section">';
echo '<h2>6️⃣ Próximos Passos</h2>';

echo '<p><strong>Se o gateway está registrado mas não aparece nas configurações:</strong></p>';
echo '<ol>';
echo '<li>Vá para: <a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '" target="_blank"><strong>WooCommerce → Configurações → Pagamentos</strong></a></li>';
echo '<li>Procure por "Wallet" na lista</li>';
echo '<li>Clique em "Gerenciar" para configurar</li>';
echo '<li>Marque "Ativar gateway Wallet"</li>';
echo '<li>Salve as alterações</li>';
echo '</ol>';

echo '<p><strong>Se o gateway NÃO está registrado:</strong></p>';
echo '<ol>';
echo '<li>Desative e reative o plugin Person Cash Wallet</li>';
echo '<li>Limpe o cache se estiver usando algum plugin de cache</li>';
echo '<li>Verifique os logs de erro do WordPress em <code>wp-content/debug.log</code></li>';
echo '</ol>';

echo '</div>';

echo '<hr>';
echo '<p style="text-align: center; margin: 30px 0;">';
echo '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '" class="button button-primary" style="margin: 0 10px;">📊 Ir para Configurações de Pagamento</a>';
echo '<a href="' . admin_url( 'plugins.php' ) . '" class="button" style="margin: 0 10px;">🔌 Gerenciar Plugins</a>';
echo '<a href="javascript:location.reload();" class="button" style="margin: 0 10px;">🔄 Recarregar Diagnóstico</a>';
echo '</p>';
?>
