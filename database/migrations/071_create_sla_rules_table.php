<?php
/**
 * Migration: Criar tabela de regras de SLA personalizadas
 * Permite SLA diferente por prioridade, canal, setor, funil
 */

function up_create_sla_rules() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    echo "🔧 Criando tabela 'sla_rules'...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS sla_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Nome da regra',
        priority TINYINT DEFAULT 0 COMMENT 'Prioridade da regra (maior = mais importante)',
        
        -- Condições (NULL = aplica para todos)
        conversation_priority VARCHAR(50) NULL COMMENT 'urgent, high, normal, low',
        channel VARCHAR(50) NULL COMMENT 'whatsapp, instagram, email, etc',
        department_id INT NULL COMMENT 'ID do setor',
        funnel_id INT NULL COMMENT 'ID do funil',
        funnel_stage_id INT NULL COMMENT 'ID do estágio',
        
        -- SLA específico (em minutos)
        first_response_time INT DEFAULT 15 COMMENT 'Tempo de primeira resposta',
        resolution_time INT DEFAULT 60 COMMENT 'Tempo de resolução',
        ongoing_response_time INT DEFAULT 15 COMMENT 'Tempo de resposta contínua',
        
        enabled TINYINT(1) DEFAULT 1 COMMENT 'Se a regra está ativa',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
        FOREIGN KEY (funnel_id) REFERENCES funnels(id) ON DELETE CASCADE,
        FOREIGN KEY (funnel_stage_id) REFERENCES funnel_stages(id) ON DELETE CASCADE,
        
        INDEX idx_priority (priority),
        INDEX idx_conversation_priority (conversation_priority),
        INDEX idx_channel (channel),
        INDEX idx_department (department_id),
        INDEX idx_funnel (funnel_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "   ✅ Tabela 'sla_rules' criada\n";
    
    // Inserir regras padrão
    echo "\n📝 Inserindo regras padrão de SLA...\n";
    
    $defaultRules = [
        [
            'name' => 'SLA Urgente',
            'priority' => 100,
            'conversation_priority' => 'urgent',
            'first_response' => 5,
            'resolution' => 30,
            'ongoing' => 5
        ],
        [
            'name' => 'SLA Alta Prioridade',
            'priority' => 80,
            'conversation_priority' => 'high',
            'first_response' => 10,
            'resolution' => 45,
            'ongoing' => 10
        ],
        [
            'name' => 'SLA Normal',
            'priority' => 50,
            'conversation_priority' => 'normal',
            'first_response' => 15,
            'resolution' => 60,
            'ongoing' => 15
        ],
        [
            'name' => 'SLA Baixa Prioridade',
            'priority' => 20,
            'conversation_priority' => 'low',
            'first_response' => 30,
            'resolution' => 120,
            'ongoing' => 30
        ]
    ];
    
    foreach ($defaultRules as $rule) {
        $checkSql = "SELECT COUNT(*) as count FROM sla_rules WHERE name = '{$rule['name']}'";
        $result = $db->query($checkSql)->fetch(\PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            $insertSql = "INSERT INTO sla_rules 
                         (name, priority, conversation_priority, first_response_time, resolution_time, ongoing_response_time, enabled) 
                         VALUES (
                             '{$rule['name']}', 
                             {$rule['priority']}, 
                             '{$rule['conversation_priority']}', 
                             {$rule['first_response']}, 
                             {$rule['resolution']}, 
                             {$rule['ongoing']},
                             1
                         )";
            $db->exec($insertSql);
            echo "   ✅ {$rule['name']}: {$rule['first_response']}min / {$rule['resolution']}min\n";
        }
    }
    
    echo "\n✅ Migration concluída com sucesso!\n";
}

function down_create_sla_rules() {
    $db = \App\Helpers\Database::getInstance();
    
    echo "🔧 Removendo tabela 'sla_rules'...\n";
    
    $db->exec("DROP TABLE IF EXISTS sla_rules");
    echo "   ✅ Tabela removida\n";
    
    echo "\n✅ Rollback concluído!\n";
}
