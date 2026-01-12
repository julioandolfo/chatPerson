<?php
/**
 * Migration 021 - Criar Índices de Performance
 * 
 * Objetivo: Otimizar queries pesadas identificadas no slow.log
 * - Query #1: Histórico do Contato (ContactController::getHistoryMetrics)
 * - Query #2: Ranking de Agentes (AgentPerformanceService::getAgentsRanking)
 * 
 * Impacto esperado:
 * - Query #1: de 3s para ~0.5s (sem cache)
 * - Query #2: de 1s para ~0.3s (sem cache)
 * - CPU: redução de 60-70% para 20-30%
 * 
 * Data: 2026-01-12
 */

function up_create_performance_indexes() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    echo "📊 Criando índices de performance...\n";
    
    try {
        // ═══════════════════════════════════════════════════════════════════
        // 1. ÍNDICES PARA QUERY #1 - Histórico do Contato
        // ═══════════════════════════════════════════════════════════════════
        
        echo "  → Criando índice messages (conversation_id, sender_type, created_at)...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_conv_sender_date 
                   ON messages(conversation_id, sender_type, created_at)");
        
        echo "  → Criando índice conversations (contact_id)...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_conversations_contact 
                   ON conversations(contact_id)");
        
        echo "  → Criando índice messages (conversation_id, created_at)...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_conversation_id 
                   ON messages(conversation_id, created_at)");
        
        // ═══════════════════════════════════════════════════════════════════
        // 2. ÍNDICES PARA QUERY #2 - Ranking de Agentes
        // ═══════════════════════════════════════════════════════════════════
        
        echo "  → Criando índice conversations (agent_id, created_at, status, resolved_at)...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_conversations_agent_date_status 
                   ON conversations(agent_id, created_at, status, resolved_at)");
        
        echo "  → Criando índice messages (sender_id, sender_type, created_at, ai_agent_id)...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_sender_type_date 
                   ON messages(sender_id, sender_type, created_at, ai_agent_id)");
        
        echo "  → Criando índice users (role, status)...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_users_role_status 
                   ON users(role, status)");
        
        // ═══════════════════════════════════════════════════════════════════
        // 3. ÍNDICES ADICIONAIS (OTIMIZAÇÕES GERAIS)
        // ═══════════════════════════════════════════════════════════════════
        
        echo "  → Criando índice conversations (status)...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_conversations_status 
                   ON conversations(status)");
        
        echo "  → Criando índice conversations (department_id, status)...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_conversations_dept_status 
                   ON conversations(department_id, status)");
        
        echo "  → Criando índice messages (ai_agent_id)...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_ai_agent 
                   ON messages(ai_agent_id)");
        
        echo "  → Criando índice contacts (email)...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_contacts_email 
                   ON contacts(email)");
        
        echo "  → Criando índice contacts (phone)...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_contacts_phone 
                   ON contacts(phone)");
        
        echo "  → Criando índice conversations (created_at)...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_conversations_created 
                   ON conversations(created_at)");
        
        echo "  → Criando índice messages (created_at)...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_created 
                   ON messages(created_at)");
        
        // ═══════════════════════════════════════════════════════════════════
        // 4. ÍNDICES PARA DASHBOARD E ANALYTICS
        // ═══════════════════════════════════════════════════════════════════
        
        echo "  → Criando índice conversations (funnel_id, created_at, status)...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_conversations_funnel_date 
                   ON conversations(funnel_id, created_at, status)");
        
        echo "  → Criando índice conversations (agent_id, status) - não atribuídas...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_conversations_unassigned 
                   ON conversations(agent_id, status)");
        
        // ═══════════════════════════════════════════════════════════════════
        // 5. ANALISAR TABELAS (atualizar estatísticas)
        // ═══════════════════════════════════════════════════════════════════
        
        echo "  → Analisando tabelas para atualizar estatísticas...\n";
        $db->exec("ANALYZE TABLE conversations");
        $db->exec("ANALYZE TABLE messages");
        $db->exec("ANALYZE TABLE users");
        $db->exec("ANALYZE TABLE contacts");
        
        echo "✅ Índices de performance criados com sucesso!\n";
        echo "\n";
        echo "📊 IMPACTO ESPERADO:\n";
        echo "  - Query #1 (Histórico): de 3s para ~0.5s\n";
        echo "  - Query #2 (Ranking): de 1s para ~0.3s\n";
        echo "  - CPU: redução de 40-50%\n";
        echo "  - Slow log: redução de 90%+\n";
        echo "\n";
        echo "💡 PRÓXIMOS PASSOS:\n";
        echo "  1. Testar queries no sistema\n";
        echo "  2. Monitorar slow.log\n";
        echo "  3. Verificar uso de CPU\n";
        echo "\n";
        
    } catch (\Exception $e) {
        echo "❌ Erro ao criar índices: " . $e->getMessage() . "\n";
        throw $e;
    }
}

function down_create_performance_indexes() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    echo "🗑️ Removendo índices de performance...\n";
    
    try {
        // Remover índices na ordem reversa
        $indexes = [
            'messages' => [
                'idx_messages_conv_sender_date',
                'idx_messages_conversation_id',
                'idx_messages_sender_type_date',
                'idx_messages_ai_agent',
                'idx_messages_created'
            ],
            'conversations' => [
                'idx_conversations_contact',
                'idx_conversations_agent_date_status',
                'idx_conversations_status',
                'idx_conversations_dept_status',
                'idx_conversations_created',
                'idx_conversations_funnel_date',
                'idx_conversations_unassigned'
            ],
            'users' => [
                'idx_users_role_status'
            ],
            'contacts' => [
                'idx_contacts_email',
                'idx_contacts_phone'
            ]
        ];
        
        foreach ($indexes as $table => $tableIndexes) {
            foreach ($tableIndexes as $index) {
                echo "  → Removendo índice {$index} da tabela {$table}...\n";
                try {
                    $db->exec("DROP INDEX IF EXISTS {$index} ON {$table}");
                } catch (\Exception $e) {
                    echo "  ⚠️ Aviso: Não foi possível remover {$index}: " . $e->getMessage() . "\n";
                }
            }
        }
        
        echo "✅ Índices removidos com sucesso!\n";
        
    } catch (\Exception $e) {
        echo "❌ Erro ao remover índices: " . $e->getMessage() . "\n";
        throw $e;
    }
}
