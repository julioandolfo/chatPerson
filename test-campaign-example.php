<?php
/**
 * Exemplo de criação de campanha via código
 * 
 * ANTES DE EXECUTAR:
 * 1. Execute as migrations: php database/migrate.php
 * 2. Ajuste os IDs abaixo conforme seu banco de dados
 */

require_once __DIR__ . '/config/bootstrap.php';

use App\Services\CampaignService;
use App\Services\ContactListService;
use App\Helpers\Auth;

echo "=== TESTE DE CAMPANHA WHATSAPP ===\n\n";

try {
    // ===================================
    // 1. CRIAR LISTA DE CONTATOS
    // ===================================
    echo "1. Criando lista de contatos...\n";
    
    $listId = ContactListService::create([
        'name' => 'Lista Teste - ' . date('Y-m-d H:i'),
        'description' => 'Lista de teste para campanha WhatsApp',
        'created_by' => 1  // ⚠️ Ajuste para ID de um usuário válido
    ]);
    
    echo "   ✅ Lista criada: ID={$listId}\n\n";

    // ===================================
    // 2. ADICIONAR CONTATOS À LISTA
    // ===================================
    echo "2. Adicionando contatos à lista...\n";
    
    // ⚠️ AJUSTE: Substitua pelos IDs de contatos reais do seu banco
    $contactIds = [1, 2, 3]; // Exemplo: IDs 1, 2, 3
    
    $added = 0;
    foreach ($contactIds as $contactId) {
        try {
            if (ContactListService::addContact($listId, $contactId)) {
                $added++;
                echo "   ✅ Contato {$contactId} adicionado\n";
            }
        } catch (\Exception $e) {
            echo "   ⚠️ Contato {$contactId}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "   Total: {$added} contatos adicionados\n\n";

    // ===================================
    // 3. CRIAR CAMPANHA
    // ===================================
    echo "3. Criando campanha...\n";
    
    // ⚠️ AJUSTE: IDs das suas contas WhatsApp (integration_accounts)
    // Você pode ter 2, 3, 5 ou mais contas
    $whatsappAccountIds = [1, 2]; // Exemplo: contas 1 e 2
    
    // BUSCAR CONTAS REAIS
    $sql = "SELECT id, name, phone_number, status FROM integration_accounts WHERE channel = 'whatsapp' AND status = 'active' LIMIT 5";
    $accounts = \App\Helpers\Database::fetchAll($sql, []);
    
    if (empty($accounts)) {
        echo "   ❌ ERRO: Nenhuma conta WhatsApp ativa encontrada!\n";
        echo "   Você precisa ter pelo menos 1 conta WhatsApp configurada.\n";
        exit(1);
    }
    
    echo "   Contas WhatsApp disponíveis:\n";
    foreach ($accounts as $account) {
        echo "      - ID {$account['id']}: {$account['name']} ({$account['phone_number']})\n";
    }
    
    $whatsappAccountIds = array_column($accounts, 'id');
    echo "   Usando " . count($whatsappAccountIds) . " contas para rotação\n\n";
    
    $campaignId = CampaignService::create([
        'name' => 'Campanha Teste - ' . date('Y-m-d H:i'),
        'description' => 'Teste de envio em massa com rotação de contas',
        
        // Canal e Lista
        'channel' => 'whatsapp',
        'target_type' => 'list',
        'contact_list_id' => $listId,
        
        // Mensagem
        'message_content' => "Olá {{nome}}! 👋\n\nEsta é uma mensagem de TESTE do sistema de campanhas.\n\nSeu telefone: {{telefone}}\n\nData: " . date('d/m/Y H:i'),
        
        // Contas WhatsApp (ROTAÇÃO)
        'integration_account_ids' => $whatsappAccountIds,
        'rotation_strategy' => 'round_robin', // ou 'random', 'by_load'
        
        // Cadência
        'send_rate_per_minute' => 10,
        'send_interval_seconds' => 6,
        
        // Janela de envio (OPCIONAL - comente se quiser enviar agora)
        // 'send_window_start' => '09:00:00',
        // 'send_window_end' => '18:00:00',
        // 'send_days' => [1,2,3,4,5], // Seg-Sex
        
        // Configurações
        'create_conversation' => true,
        'skip_duplicates' => true,
        'skip_recent_conversations' => true,
        'skip_recent_hours' => 24,
        'respect_blacklist' => true,
        
        // Audit
        'created_by' => 1  // ⚠️ Ajuste para ID do usuário
    ]);
    
    echo "   ✅ Campanha criada: ID={$campaignId}\n\n";

    // ===================================
    // 4. PREPARAR CAMPANHA
    // ===================================
    echo "4. Preparando campanha (processando variáveis e criando registros)...\n";
    
    $result = CampaignService::prepare($campaignId);
    
    echo "   ✅ Preparação concluída:\n";
    echo "      - Mensagens criadas: {$result['created']}\n";
    echo "      - Contatos pulados: {$result['skipped']}\n";
    echo "      - Total: {$result['total']}\n\n";

    // ===================================
    // 5. INICIAR CAMPANHA
    // ===================================
    echo "5. Iniciando campanha...\n";
    
    CampaignService::start($campaignId);
    
    echo "   ✅ Campanha iniciada!\n";
    echo "   Status: RUNNING\n\n";

    // ===================================
    // 6. VER ESTATÍSTICAS
    // ===================================
    echo "6. Estatísticas atuais:\n";
    
    $stats = CampaignService::getStats($campaignId);
    echo "   - Total de contatos: {$stats['total_contacts']}\n";
    echo "   - Enviadas: {$stats['total_sent']}\n";
    echo "   - Entregues: {$stats['total_delivered']}\n";
    echo "   - Lidas: {$stats['total_read']}\n";
    echo "   - Respondidas: {$stats['total_replied']}\n";
    echo "   - Falhas: {$stats['total_failed']}\n";
    echo "   - Puladas: {$stats['total_skipped']}\n";
    echo "   - Progresso: {$stats['progress']}%\n\n";

    // ===================================
    // 7. INSTRUÇÕES
    // ===================================
    echo "=== PRÓXIMOS PASSOS ===\n\n";
    echo "A campanha está ATIVA e aguardando processamento.\n\n";
    echo "Para processar as mensagens, você tem 2 opções:\n\n";
    echo "OPÇÃO 1: Processar manualmente AGORA\n";
    echo "   php public\\scripts\\process-campaigns.php\n\n";
    echo "OPÇÃO 2: Configurar cron job (Windows Task Scheduler)\n";
    echo "   - Programa: C:\\laragon\\bin\\php\\php-8.1.10-Win32-vs16-x64\\php.exe\n";
    echo "   - Argumentos: C:\\laragon\\www\\chat\\public\\scripts\\process-campaigns.php\n";
    echo "   - Repetir: A cada 1 minuto\n\n";
    echo "=== FIM DO TESTE ===\n";

} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
