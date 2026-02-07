<?php
/**
 * Migration: Criar tabela mockup_generations (Gerações de Mockup)
 * 
 * Armazena histórico de todos os mockups gerados com IA ou canvas
 */

function up_create_mockup_generations_table() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS mockup_generations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL COMMENT 'Conversa onde foi gerado',
        
        -- PRODUTO E LOGO
        product_id INT COMMENT 'ID do produto salvo (se usado)',
        product_image_path VARCHAR(500) COMMENT 'Imagem do produto usada',
        logo_id INT COMMENT 'ID da logo usada',
        logo_image_path VARCHAR(500) COMMENT 'Caminho da logo usada',
        
        -- CONFIGURAÇÃO DA LOGO
        logo_config JSON COMMENT 'Configurações de aplicação da logo',
        /* Estrutura do logo_config:
        {
            position: 'center|top-center|bottom-right|...',
            size: 20,  // percentual
            style: 'original|white|black|grayscale',
            orientation: 'auto|horizontal|vertical|square',
            opacity: 100,  // 0-100
            effects: {
                shadow: true,
                border: false,
                reflection: false
            }
        }
        */
        
        -- MODO DE GERAÇÃO
        generation_mode ENUM('ai', 'manual', 'hybrid') NOT NULL COMMENT 'Modo de geração',
        
        -- PROMPTS (se modo AI ou híbrido)
        original_prompt TEXT COMMENT 'Prompt original antes de otimização',
        optimized_prompt TEXT COMMENT 'Prompt otimizado pelo GPT-4o Vision',
        gpt4_analysis TEXT COMMENT 'Análise do GPT-4o sobre produto e logo',
        dalle_model VARCHAR(50) DEFAULT 'dall-e-3' COMMENT 'Modelo DALL-E usado',
        dalle_size VARCHAR(20) DEFAULT '1024x1024' COMMENT 'Tamanho da imagem gerada',
        dalle_quality VARCHAR(20) DEFAULT 'standard' COMMENT 'standard ou hd',
        
        -- CANVAS (se modo manual ou híbrido)
        canvas_data JSON COMMENT 'Estado completo do canvas (Fabric.js JSON)',
        
        -- RESULTADO
        result_image_path VARCHAR(500) COMMENT 'Caminho da imagem final gerada',
        result_thumbnail_path VARCHAR(500) COMMENT 'Miniatura do resultado',
        result_size INT COMMENT 'Tamanho do arquivo em bytes',
        
        -- STATUS E CONTROLE
        status ENUM('generating', 'completed', 'failed') DEFAULT 'generating',
        error_message TEXT COMMENT 'Mensagem de erro se falhou',
        processing_time INT COMMENT 'Tempo de processamento em milissegundos',
        
        -- CUSTOS (para tracking)
        gpt4_cost DECIMAL(10, 6) DEFAULT 0 COMMENT 'Custo da análise GPT-4o',
        dalle_cost DECIMAL(10, 6) DEFAULT 0 COMMENT 'Custo da geração DALL-E',
        total_cost DECIMAL(10, 6) DEFAULT 0 COMMENT 'Custo total',
        
        -- INTERAÇÃO
        sent_as_message BOOLEAN DEFAULT false COMMENT 'Se foi enviado como mensagem',
        message_id INT COMMENT 'ID da mensagem se enviado',
        
        -- AUDIT
        generated_by INT COMMENT 'Usuário que gerou',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_conversation_id (conversation_id),
        INDEX idx_product_id (product_id),
        INDEX idx_logo_id (logo_id),
        INDEX idx_status (status),
        INDEX idx_generation_mode (generation_mode),
        INDEX idx_generated_by (generated_by),
        INDEX idx_created_at (created_at),
        
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES mockup_products(id) ON DELETE SET NULL,
        FOREIGN KEY (logo_id) REFERENCES conversation_logos(id) ON DELETE SET NULL,
        FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL,
        FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'mockup_generations' criada com sucesso!\n";
}

function down_create_mockup_generations_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "DROP TABLE IF EXISTS mockup_generations";
    $db->exec($sql);
    echo "✅ Tabela 'mockup_generations' removida!\n";
}
