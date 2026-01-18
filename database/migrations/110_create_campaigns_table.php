<?php
/**
 * Migration: Criar tabela campaigns (Campanhas de Disparo em Massa)
 * 
 * Sistema de campanhas para envio em massa no WhatsApp
 * com rotação de contas, cadência, validações e tracking completo
 */

function up_create_campaigns_table() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Nome da campanha',
        description TEXT COMMENT 'Descrição detalhada',
        
        -- SEGMENTAÇÃO
        target_type VARCHAR(50) DEFAULT 'list' COMMENT 'list, filter, custom',
        contact_list_id INT COMMENT 'ID da lista se target_type = list',
        filter_config JSON COMMENT 'Configuração de filtros se target_type = filter',
        
        -- MENSAGEM
        message_template_id INT COMMENT 'ID do template a usar',
        message_content TEXT COMMENT 'Conteúdo da mensagem',
        message_variables JSON COMMENT 'Variáveis globais da campanha',
        attachments JSON COMMENT 'Anexos (imagens, documentos, etc)',
        
        -- CANAL (apenas WhatsApp por enquanto)
        channel VARCHAR(50) DEFAULT 'whatsapp' COMMENT 'Tipo de canal',
        integration_account_ids JSON NOT NULL COMMENT 'Array de IDs de contas WhatsApp para rotação',
        rotation_strategy VARCHAR(50) DEFAULT 'round_robin' COMMENT 'round_robin, random, by_load',
        
        -- AGENDAMENTO E CADÊNCIA
        scheduled_at TIMESTAMP NULL COMMENT 'Quando iniciar o envio',
        send_strategy VARCHAR(50) DEFAULT 'immediate' COMMENT 'immediate, scheduled, drip',
        send_rate_per_minute INT DEFAULT 10 COMMENT 'Mensagens por minuto',
        send_interval_seconds INT DEFAULT 6 COMMENT 'Intervalo entre mensagens',
        send_window_start TIME COMMENT 'Horário inicial (ex: 09:00:00)',
        send_window_end TIME COMMENT 'Horário final (ex: 18:00:00)',
        send_days JSON COMMENT 'Dias da semana permitidos [1,2,3,4,5]',
        timezone VARCHAR(50) DEFAULT 'America/Sao_Paulo',
        
        -- FUNIL/ETAPAS
        funnel_id INT COMMENT 'Funil de destino',
        initial_stage_id INT COMMENT 'Etapa inicial ao criar conversa',
        auto_move_on_reply BOOLEAN DEFAULT FALSE COMMENT 'Mover para outra etapa ao responder',
        reply_stage_id INT COMMENT 'Etapa ao responder',
        
        -- STATUS E CONTROLE
        status VARCHAR(50) DEFAULT 'draft' COMMENT 'draft, scheduled, running, paused, completed, cancelled',
        priority INT DEFAULT 0 COMMENT 'Prioridade (maior = mais importante)',
        
        -- CONFIGURAÇÕES AVANÇADAS
        skip_duplicates BOOLEAN DEFAULT TRUE COMMENT 'Não enviar se já enviou nesta campanha',
        skip_recent_conversations BOOLEAN DEFAULT TRUE COMMENT 'Não enviar se tem conversa ativa',
        skip_recent_hours INT DEFAULT 24 COMMENT 'Horas para considerar conversa recente',
        respect_blacklist BOOLEAN DEFAULT TRUE COMMENT 'Respeitar blacklist',
        create_conversation BOOLEAN DEFAULT TRUE COMMENT 'Criar conversa ao enviar',
        tag_on_send VARCHAR(100) COMMENT 'Nome da tag a adicionar ao enviar',
        
        -- ESTATÍSTICAS
        total_contacts INT DEFAULT 0 COMMENT 'Total de contatos na campanha',
        total_sent INT DEFAULT 0 COMMENT 'Total de mensagens enviadas',
        total_delivered INT DEFAULT 0 COMMENT 'Total de mensagens entregues',
        total_read INT DEFAULT 0 COMMENT 'Total de mensagens lidas',
        total_replied INT DEFAULT 0 COMMENT 'Total de respostas recebidas',
        total_failed INT DEFAULT 0 COMMENT 'Total de falhas',
        total_skipped INT DEFAULT 0 COMMENT 'Total de contatos pulados',
        
        -- TRACKING DE EXECUÇÃO
        started_at TIMESTAMP NULL COMMENT 'Quando iniciou o envio',
        completed_at TIMESTAMP NULL COMMENT 'Quando completou',
        paused_at TIMESTAMP NULL COMMENT 'Quando foi pausada',
        cancelled_at TIMESTAMP NULL COMMENT 'Quando foi cancelada',
        last_processed_at TIMESTAMP NULL COMMENT 'Última vez que foi processada',
        
        -- AUDIT
        created_by INT COMMENT 'Usuário que criou',
        updated_by INT COMMENT 'Usuário que atualizou',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_status (status),
        INDEX idx_scheduled_at (scheduled_at),
        INDEX idx_channel (channel),
        INDEX idx_contact_list_id (contact_list_id),
        INDEX idx_created_by (created_by),
        
        FOREIGN KEY (contact_list_id) REFERENCES contact_lists(id) ON DELETE SET NULL,
        FOREIGN KEY (message_template_id) REFERENCES message_templates(id) ON DELETE SET NULL,
        FOREIGN KEY (funnel_id) REFERENCES funnels(id) ON DELETE SET NULL,
        FOREIGN KEY (initial_stage_id) REFERENCES funnel_stages(id) ON DELETE SET NULL,
        FOREIGN KEY (reply_stage_id) REFERENCES funnel_stages(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'campaigns' criada com sucesso!\n";
}

function down_create_campaigns_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "DROP TABLE IF EXISTS campaigns";
    $db->exec($sql);
    echo "✅ Tabela 'campaigns' removida!\n";
}
