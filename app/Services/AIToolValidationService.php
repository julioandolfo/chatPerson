<?php
/**
 * Service AIToolValidationService
 * Validação e verificação de integridade das tools de IA
 */

namespace App\Services;

use App\Models\AITool;
use App\Models\AIAgent;
use App\Helpers\Database;

class AIToolValidationService
{
    /**
     * Validar todas as tools do sistema
     */
    public static function validateAllTools(): array
    {
        $results = [
            'total' => 0,
            'valid' => 0,
            'invalid' => 0,
            'errors' => [],
            'warnings' => [],
            'tools' => []
        ];

        $tools = AITool::getAllActive();
        $results['total'] = count($tools);

        foreach ($tools as $tool) {
            $validation = self::validateTool($tool);
            $results['tools'][] = [
                'id' => $tool['id'],
                'name' => $tool['name'],
                'slug' => $tool['slug'],
                'validation' => $validation
            ];

            if ($validation['valid']) {
                $results['valid']++;
            } else {
                $results['invalid']++;
                $results['errors'] = array_merge($results['errors'], $validation['errors']);
            }

            if (!empty($validation['warnings'])) {
                $results['warnings'] = array_merge($results['warnings'], $validation['warnings']);
            }
        }

        return $results;
    }

    /**
     * Validar uma tool específica
     */
    public static function validateTool(array $tool): array
    {
        $errors = [];
        $warnings = [];
        $valid = true;

        // 1. Validar campos obrigatórios
        $requiredFields = ['name', 'slug', 'tool_type', 'function_schema'];
        foreach ($requiredFields as $field) {
            if (empty($tool[$field])) {
                $errors[] = "Campo obrigatório ausente: {$field}";
                $valid = false;
            }
        }

        // 2. Validar formato do function_schema
        $schemaValidation = self::validateFunctionSchema($tool);
        if (!$schemaValidation['valid']) {
            $errors = array_merge($errors, $schemaValidation['errors']);
            $valid = false;
        }
        if (!empty($schemaValidation['warnings'])) {
            $warnings = array_merge($warnings, $schemaValidation['warnings']);
        }

        // 3. Validar correspondência entre slug e function name
        $slugValidation = self::validateSlugMatch($tool);
        if (!$slugValidation['valid']) {
            $errors = array_merge($errors, $slugValidation['errors']);
            $valid = false;
        }

        // 4. Validar tool_type
        $validTypes = ['system', 'woocommerce', 'database', 'n8n', 'document', 'api', 'followup'];
        if (!in_array($tool['tool_type'], $validTypes)) {
            $errors[] = "Tipo de tool inválido: {$tool['tool_type']}. Tipos válidos: " . implode(', ', $validTypes);
            $valid = false;
        }

        // 5. Validar se a tool pode ser executada (verificar método correspondente)
        $executionValidation = self::validateToolExecution($tool);
        if (!$executionValidation['valid']) {
            $warnings = array_merge($warnings, $executionValidation['warnings']);
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Validar formato do function_schema
     */
    private static function validateFunctionSchema(array $tool): array
    {
        $errors = [];
        $warnings = [];
        $valid = true;

        $schema = $tool['function_schema'];
        
        // Decodificar se for string
        if (is_string($schema)) {
            $schema = json_decode($schema, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = "function_schema não é um JSON válido: " . json_last_error_msg();
                return ['valid' => false, 'errors' => $errors, 'warnings' => []];
            }
        }

        if (!is_array($schema)) {
            $errors[] = "function_schema deve ser um array/objeto";
            return ['valid' => false, 'errors' => $errors, 'warnings' => []];
        }

        // Verificar estrutura esperada pela OpenAI
        // Formato esperado: { "type": "function", "function": { "name": "...", "description": "...", "parameters": {...} } }
        if (isset($schema['type']) && $schema['type'] === 'function') {
            // Formato completo (com type e function)
            if (!isset($schema['function'])) {
                $errors[] = "function_schema com type='function' deve conter campo 'function'";
                $valid = false;
            } else {
                $function = $schema['function'];
                if (!isset($function['name'])) {
                    $errors[] = "function_schema.function deve conter campo 'name'";
                    $valid = false;
                }
                if (!isset($function['description'])) {
                    $warnings[] = "function_schema.function deveria conter campo 'description'";
                }
                if (!isset($function['parameters'])) {
                    $errors[] = "function_schema.function deve conter campo 'parameters'";
                    $valid = false;
                } else {
                    $paramsValidation = self::validateParameters($function['parameters']);
                    if (!$paramsValidation['valid']) {
                        $errors = array_merge($errors, $paramsValidation['errors']);
                        $valid = false;
                    }
                }
            }
        } elseif (isset($schema['name'])) {
            // Formato direto (apenas function, sem wrapper)
            // Isso está OK, será envolvido pelo OpenAIService
            if (!isset($schema['description'])) {
                $warnings[] = "function_schema deveria conter campo 'description'";
            }
            if (!isset($schema['parameters'])) {
                $errors[] = "function_schema deve conter campo 'parameters'";
                $valid = false;
            } else {
                $paramsValidation = self::validateParameters($schema['parameters']);
                if (!$paramsValidation['valid']) {
                    $errors = array_merge($errors, $paramsValidation['errors']);
                    $valid = false;
                }
            }
        } else {
            $errors[] = "function_schema deve conter 'name' ou estrutura com 'type'='function' e 'function'";
            $valid = false;
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Validar estrutura de parameters
     */
    private static function validateParameters(array $parameters): array
    {
        $errors = [];
        $valid = true;

        if (!isset($parameters['type']) || $parameters['type'] !== 'object') {
            $errors[] = "parameters.type deve ser 'object'";
            $valid = false;
        }

        if (!isset($parameters['properties'])) {
            $warnings[] = "parameters deveria conter 'properties'";
        } elseif (!is_array($parameters['properties'])) {
            $errors[] = "parameters.properties deve ser um objeto/array";
            $valid = false;
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
            'warnings' => []
        ];
    }

    /**
     * Validar correspondência entre slug e function name
     */
    private static function validateSlugMatch(array $tool): array
    {
        $errors = [];
        $valid = true;

        $slug = $tool['slug'];
        $schema = $tool['function_schema'];

        // Decodificar se for string
        if (is_string($schema)) {
            $schema = json_decode($schema, true);
        }

        // Extrair function name do schema
        $functionName = null;
        if (isset($schema['type']) && $schema['type'] === 'function' && isset($schema['function']['name'])) {
            $functionName = $schema['function']['name'];
        } elseif (isset($schema['name'])) {
            $functionName = $schema['name'];
        }

        if (!$functionName) {
            $errors[] = "Não foi possível extrair 'name' do function_schema";
            $valid = false;
        } elseif ($functionName !== $slug) {
            $errors[] = "Slug '{$slug}' não corresponde ao function name '{$functionName}' no schema";
            $valid = false;
        }

        return [
            'valid' => $valid,
            'errors' => $errors
        ];
    }

    /**
     * Validar se a tool pode ser executada (verificar método correspondente)
     */
    private static function validateToolExecution(array $tool): array
    {
        $warnings = [];
        $valid = true;

        $toolType = $tool['tool_type'];
        $functionName = null;

        // Extrair function name
        $schema = $tool['function_schema'];
        if (is_string($schema)) {
            $schema = json_decode($schema, true);
        }
        if (isset($schema['type']) && $schema['type'] === 'function' && isset($schema['function']['name'])) {
            $functionName = $schema['function']['name'];
        } elseif (isset($schema['name'])) {
            $functionName = $schema['name'];
        }

        // Verificar se existe método de execução correspondente no OpenAIService
        $executionMethods = [
            'system' => ['buscar_conversas_anteriores', 'buscar_informacoes_contato', 'adicionar_tag', 'adicionar_tag_conversa', 'mover_para_estagio', 'escalar_para_humano'],
            'followup' => ['verificar_status_conversa', 'verificar_ultima_interacao'],
            'woocommerce' => ['buscar_pedido_woocommerce', 'buscar_produto_woocommerce', 'criar_pedido_woocommerce', 'atualizar_status_pedido'],
            'database' => ['consultar_banco_dados'],
            'n8n' => ['executar_workflow_n8n', 'buscar_dados_n8n', 'chamar_webhook_n8n', 'consultar_api_n8n'],
            'api' => ['chamar_api_externa'],
            'document' => ['buscar_documento', 'extrair_texto_documento']
        ];

        if ($functionName && isset($executionMethods[$toolType])) {
            if (!in_array($functionName, $executionMethods[$toolType])) {
                $warnings[] = "Function name '{$functionName}' não está na lista de funções conhecidas para tipo '{$toolType}'";
            }
        }

        return [
            'valid' => $valid,
            'warnings' => $warnings
        ];
    }

    /**
     * Validar integração com OpenAI (formato do payload)
     */
    public static function validateOpenAIIntegration(int $agentId): array
    {
        $errors = [];
        $warnings = [];
        $valid = true;

        $agent = AIAgent::find($agentId);
        if (!$agent) {
            return [
                'valid' => false,
                'errors' => ['Agente não encontrado'],
                'warnings' => []
            ];
        }

        // Obter tools do agente
        $tools = AIAgent::getTools($agentId);
        
        if (empty($tools)) {
            $warnings[] = "Agente não possui tools atribuídas";
            return [
                'valid' => true,
                'errors' => [],
                'warnings' => $warnings
            ];
        }

        // Validar formato que será enviado para OpenAI
        $functions = [];
        foreach ($tools as $tool) {
            $functionSchema = is_string($tool['function_schema']) 
                ? json_decode($tool['function_schema'], true) 
                : ($tool['function_schema'] ?? []);
            
            if (empty($functionSchema)) {
                $errors[] = "Tool '{$tool['name']}' possui function_schema vazio ou inválido";
                $valid = false;
                continue;
            }

            // Verificar formato esperado pela OpenAI
            // OpenAI espera: { "type": "function", "function": { "name": "...", ... } }
            if (isset($functionSchema['type']) && $functionSchema['type'] === 'function') {
                // Formato completo - OK
                $functions[] = $functionSchema;
            } elseif (isset($functionSchema['name'])) {
                // Formato direto - será envolvido pelo OpenAIService
                // Verificar se o wrapper está correto
                $wrapped = ['type' => 'function', 'function' => $functionSchema];
                $functions[] = $wrapped;
            } else {
                $errors[] = "Tool '{$tool['name']}' possui function_schema em formato inválido";
                $valid = false;
            }
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
            'warnings' => $warnings,
            'functions_count' => count($functions),
            'functions' => $functions
        ];
    }

    /**
     * Validar execução de tool calls
     */
    public static function validateToolCallExecution(string $functionName, int $agentId): array
    {
        $errors = [];
        $warnings = [];
        $valid = true;

        // Buscar tool pelo slug (que deve corresponder ao functionName)
        $tool = AITool::findBySlug($functionName);
        
        if (!$tool) {
            $errors[] = "Tool com slug '{$functionName}' não encontrada";
            $valid = false;
            return [
                'valid' => $valid,
                'errors' => $errors,
                'warnings' => $warnings
            ];
        }

        if (!$tool['enabled']) {
            $errors[] = "Tool '{$tool['name']}' está desabilitada";
            $valid = false;
        }

        // Verificar se tool está atribuída ao agente
        $agentTools = AIAgent::getTools($agentId);
        $toolAssigned = false;
        foreach ($agentTools as $agentTool) {
            if ($agentTool['id'] == $tool['id']) {
                $toolAssigned = true;
                break;
            }
        }

        if (!$toolAssigned) {
            $errors[] = "Tool '{$tool['name']}' não está atribuída ao agente ID {$agentId}";
            $valid = false;
        }

        // Verificar se existe método de execução
        $executionValidation = self::validateToolExecution($tool);
        if (!empty($executionValidation['warnings'])) {
            $warnings = array_merge($warnings, $executionValidation['warnings']);
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
            'warnings' => $warnings,
            'tool' => $tool
        ];
    }

    /**
     * Gerar relatório completo de validação
     */
    public static function generateReport(): array
    {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tools_validation' => self::validateAllTools(),
            'agents_with_tools' => []
        ];

        // Validar agentes que possuem tools
        try {
            $agents = AIAgent::getAll();
            foreach ($agents as $agent) {
                if (!$agent['enabled']) {
                    continue;
                }

                $tools = AIAgent::getTools($agent['id']);
                if (!empty($tools)) {
                    $integration = self::validateOpenAIIntegration($agent['id']);
                    $report['agents_with_tools'][] = [
                        'agent_id' => $agent['id'],
                        'agent_name' => $agent['name'],
                        'tools_count' => count($tools),
                        'integration' => $integration
                    ];
                }
            }
        } catch (\Exception $e) {
            $report['agents_with_tools_error'] = $e->getMessage();
        }

        return $report;
    }
}

