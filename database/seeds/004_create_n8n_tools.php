<?php
/**
 * Seed: Criar tools N8N melhoradas para disparo e coleta
 */

function seed_n8n_tools() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    echo "🚀 Criando tools N8N melhoradas...\n";
    
    $n8nTools = [
        [
            'name' => 'Chamar Webhook N8N',
            'slug' => 'chamar_webhook_n8n',
            'description' => 'Chama um webhook do N8N com suporte a diferentes métodos HTTP (GET, POST, PUT, DELETE, PATCH) para disparo de workflows',
            'tool_type' => 'n8n',
            'function_schema' => json_encode([
                'type' => 'function',
                'function' => [
                    'name' => 'chamar_webhook_n8n',
                    'description' => 'Chama um webhook do N8N para disparar um workflow. Suporta GET, POST, PUT, DELETE e PATCH. Use GET para coletar dados e POST/PUT para disparar ações.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'webhook_id' => [
                                'type' => 'string',
                                'description' => 'ID do webhook do N8N (ex: abc123) ou URL completa do webhook'
                            ],
                            'method' => [
                                'type' => 'string',
                                'enum' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
                                'description' => 'Método HTTP a ser usado. GET para coletar dados, POST/PUT para disparar ações. Padrão: POST'
                            ],
                            'data' => [
                                'type' => 'object',
                                'description' => 'Dados a serem enviados no body da requisição (para POST, PUT, PATCH)'
                            ],
                            'query_params' => [
                                'type' => 'object',
                                'description' => 'Parâmetros de query string (para GET ou adicionar à URL)'
                            ],
                            'headers' => [
                                'type' => 'object',
                                'description' => 'Headers HTTP customizados a serem enviados'
                            ]
                        ],
                        'required' => ['webhook_id']
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE),
            'config' => json_encode([
                'n8n_url' => '',
                'webhook_id' => '',
                'api_key' => '',
                'default_method' => 'POST',
                'webhook_path' => '/webhook',
                'timeout' => 60,
                'custom_headers' => []
            ], JSON_UNESCAPED_UNICODE),
            'enabled' => true
        ],
        [
            'name' => 'Consultar API N8N',
            'slug' => 'consultar_api_n8n',
            'description' => 'Consulta a API REST do N8N para buscar dados ou executar operações',
            'tool_type' => 'n8n',
            'function_schema' => json_encode([
                'type' => 'function',
                'function' => [
                    'name' => 'consultar_api_n8n',
                    'description' => 'Consulta a API REST do N8N para buscar dados, listar workflows, ou executar outras operações administrativas',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'endpoint' => [
                                'type' => 'string',
                                'description' => 'Endpoint da API (ex: workflows, executions, nodes) ou URL completa'
                            ],
                            'method' => [
                                'type' => 'string',
                                'enum' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
                                'description' => 'Método HTTP. Padrão: GET'
                            ],
                            'query_params' => [
                                'type' => 'object',
                                'description' => 'Parâmetros de query string'
                            ],
                            'data' => [
                                'type' => 'object',
                                'description' => 'Dados a serem enviados no body (para POST, PUT, PATCH)'
                            ],
                            'headers' => [
                                'type' => 'object',
                                'description' => 'Headers HTTP customizados'
                            ]
                        ],
                        'required' => ['endpoint']
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE),
            'config' => json_encode([
                'n8n_url' => '',
                'api_key' => '',
                'default_method' => 'GET',
                'timeout' => 30,
                'custom_headers' => []
            ], JSON_UNESCAPED_UNICODE),
            'enabled' => true
        ],
        [
            'name' => 'Executar Workflow N8N',
            'slug' => 'executar_workflow_n8n',
            'description' => 'Executa um workflow N8N via webhook (compatibilidade com versão anterior)',
            'tool_type' => 'n8n',
            'function_schema' => json_encode([
                'type' => 'function',
                'function' => [
                    'name' => 'executar_workflow_n8n',
                    'description' => 'Executa um workflow N8N via webhook. Método padrão: POST. Use esta função para compatibilidade com workflows existentes.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'workflow_id' => [
                                'type' => 'string',
                                'description' => 'ID do workflow/webhook do N8N'
                            ],
                            'data' => [
                                'type' => 'object',
                                'description' => 'Dados a serem enviados ao workflow'
                            ],
                            'method' => [
                                'type' => 'string',
                                'enum' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
                                'description' => 'Método HTTP. Padrão: POST'
                            ]
                        ],
                        'required' => ['workflow_id']
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE),
            'config' => json_encode([
                'n8n_url' => '',
                'webhook_id' => '',
                'api_key' => '',
                'default_method' => 'POST',
                'webhook_path' => '/webhook',
                'timeout' => 60
            ], JSON_UNESCAPED_UNICODE),
            'enabled' => true
        ]
    ];
    
    foreach ($n8nTools as $tool) {
        $sql = "INSERT INTO ai_tools (name, slug, description, tool_type, function_schema, config, enabled, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name), 
                    description = VALUES(description), 
                    tool_type = VALUES(tool_type),
                    function_schema = VALUES(function_schema),
                    config = VALUES(config),
                    enabled = VALUES(enabled),
                    updated_at = NOW()";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $tool['name'],
            $tool['slug'],
            $tool['description'],
            $tool['tool_type'],
            $tool['function_schema'],
            $tool['config'],
            $tool['enabled'] ? 1 : 0
        ]);
        echo "✅ Tool N8N '{$tool['name']}' criada/atualizada\n";
    }
    
    echo "✅ Tools N8N criadas com sucesso!\n";
}

