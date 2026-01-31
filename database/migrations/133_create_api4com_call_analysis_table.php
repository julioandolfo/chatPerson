<?php
/**
 * Migration: Criar tabela api4com_call_analysis
 * Armazena análises de performance de chamadas telefônicas
 */

function up_api4com_call_analysis_table() {
    $sql = "CREATE TABLE IF NOT EXISTS api4com_call_analysis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        call_id INT NOT NULL COMMENT 'ID da chamada em api4com_calls',
        agent_id INT NULL COMMENT 'ID do agente',
        conversation_id INT NULL COMMENT 'ID da conversa relacionada',
        
        -- Transcrição
        transcription TEXT NULL COMMENT 'Transcrição completa do áudio',
        transcription_language VARCHAR(10) DEFAULT 'pt' COMMENT 'Idioma detectado',
        transcription_duration INT DEFAULT 0 COMMENT 'Duração em segundos',
        transcription_cost DECIMAL(10,6) DEFAULT 0 COMMENT 'Custo da transcrição (Whisper)',
        
        -- Resumo
        summary TEXT NULL COMMENT 'Resumo executivo da ligação',
        call_outcome ENUM('positive', 'negative', 'neutral', 'followup_needed') DEFAULT 'neutral' COMMENT 'Resultado da ligação',
        call_type ENUM('sales', 'support', 'followup', 'prospecting', 'other') DEFAULT 'sales' COMMENT 'Tipo de ligação',
        
        -- Scores (0.0 a 5.0)
        score_opening DECIMAL(3,1) DEFAULT 0 COMMENT 'Abertura/Apresentação',
        score_tone DECIMAL(3,1) DEFAULT 0 COMMENT 'Tom de voz/Cordialidade',
        score_listening DECIMAL(3,1) DEFAULT 0 COMMENT 'Escuta ativa',
        score_objection_handling DECIMAL(3,1) DEFAULT 0 COMMENT 'Quebra de objeções',
        score_value_proposition DECIMAL(3,1) DEFAULT 0 COMMENT 'Proposta de valor',
        score_closing DECIMAL(3,1) DEFAULT 0 COMMENT 'Técnicas de fechamento',
        score_qualification DECIMAL(3,1) DEFAULT 0 COMMENT 'Qualificação do cliente',
        score_control DECIMAL(3,1) DEFAULT 0 COMMENT 'Controle da conversa',
        score_professionalism DECIMAL(3,1) DEFAULT 0 COMMENT 'Profissionalismo',
        score_empathy DECIMAL(3,1) DEFAULT 0 COMMENT 'Empatia/Rapport',
        
        -- Score geral
        overall_score DECIMAL(3,1) DEFAULT 0 COMMENT 'Score geral (média ponderada)',
        
        -- Análise detalhada
        strengths JSON NULL COMMENT 'Pontos fortes identificados',
        weaknesses JSON NULL COMMENT 'Pontos fracos identificados',
        suggestions JSON NULL COMMENT 'Sugestões de melhoria',
        key_moments JSON NULL COMMENT 'Momentos-chave da conversa',
        detailed_analysis TEXT NULL COMMENT 'Análise detalhada em texto',
        
        -- Cliente
        client_sentiment ENUM('very_positive', 'positive', 'neutral', 'negative', 'very_negative') DEFAULT 'neutral' COMMENT 'Sentimento do cliente',
        client_objections JSON NULL COMMENT 'Objeções levantadas pelo cliente',
        client_interests JSON NULL COMMENT 'Interesses demonstrados',
        
        -- Metadados
        model_used VARCHAR(50) DEFAULT 'gpt-4-turbo' COMMENT 'Modelo de IA usado',
        analysis_cost DECIMAL(10,6) DEFAULT 0 COMMENT 'Custo da análise (GPT)',
        tokens_used INT DEFAULT 0 COMMENT 'Tokens utilizados',
        processing_time_ms INT DEFAULT 0 COMMENT 'Tempo de processamento em ms',
        
        -- Status
        status ENUM('pending', 'transcribing', 'analyzing', 'completed', 'failed') DEFAULT 'pending',
        error_message TEXT NULL COMMENT 'Mensagem de erro se falhou',
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (call_id) REFERENCES api4com_calls(id) ON DELETE CASCADE,
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
        
        INDEX idx_call_id (call_id),
        INDEX idx_agent_id (agent_id),
        INDEX idx_status (status),
        INDEX idx_overall_score (overall_score),
        INDEX idx_call_outcome (call_outcome),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'api4com_call_analysis' criada com sucesso!\n";
}

function down_api4com_call_analysis_table() {
    $sql = "DROP TABLE IF EXISTS api4com_call_analysis";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'api4com_call_analysis' removida!\n";
}
