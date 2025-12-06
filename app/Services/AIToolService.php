<?php
/**
 * Service AIToolService
 * Lógica de negócio para tools de IA
 */

namespace App\Services;

use App\Models\AITool;
use App\Helpers\Validator;

class AIToolService
{
    /**
     * Criar tool
     */
    public static function create(array $data): int
    {
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100',
            'description' => 'nullable|string',
            'tool_type' => 'required|string|in:woocommerce,database,n8n,document,system,api,followup',
            'function_schema' => 'required|array',
            'config' => 'nullable|array',
            'enabled' => 'nullable|boolean',
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Serializar JSON fields
        $data['function_schema'] = json_encode($data['function_schema'], JSON_UNESCAPED_UNICODE);
        if (isset($data['config']) && is_array($data['config'])) {
            $data['config'] = json_encode($data['config'], JSON_UNESCAPED_UNICODE);
        }

        // Converter enabled para boolean
        if (isset($data['enabled'])) {
            $data['enabled'] = filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        } else {
            $data['enabled'] = true;
        }

        return AITool::create($data);
    }

    /**
     * Atualizar tool
     */
    public static function update(int $id, array $data): bool
    {
        $tool = AITool::find($id);
        if (!$tool) {
            throw new \Exception('Tool não encontrada');
        }

        $errors = Validator::validate($data, [
            'name' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'tool_type' => 'nullable|string|in:woocommerce,database,n8n,document,system,api,followup',
            'function_schema' => 'nullable|array',
            'config' => 'nullable|array',
            'enabled' => 'nullable|boolean',
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Serializar JSON fields
        if (isset($data['function_schema']) && is_array($data['function_schema'])) {
            $data['function_schema'] = json_encode($data['function_schema'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['config']) && is_array($data['config'])) {
            $data['config'] = json_encode($data['config'], JSON_UNESCAPED_UNICODE);
        }

        // Converter enabled para boolean
        if (isset($data['enabled'])) {
            $data['enabled'] = filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        return AITool::update($id, $data);
    }

    /**
     * Listar tools
     */
    public static function list(array $filters = []): array
    {
        $sql = "SELECT * FROM ai_tools WHERE 1=1";
        $params = [];

        if (!empty($filters['tool_type'])) {
            $sql .= " AND tool_type = ?";
            $params[] = $filters['tool_type'];
        }

        if (isset($filters['enabled'])) {
            $sql .= " AND enabled = ?";
            $params[] = $filters['enabled'] ? 1 : 0;
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= " ORDER BY tool_type ASC, name ASC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }

        return \App\Helpers\Database::fetchAll($sql, $params);
    }

    /**
     * Obter tool
     */
    public static function get(int $id): ?array
    {
        return AITool::find($id);
    }

    /**
     * Obter tools padrão do sistema
     */
    public static function getDefaultTools(): array
    {
        return [
            [
                'name' => 'Buscar Conversas Anteriores',
                'slug' => 'buscar_conversas_anteriores',
                'description' => 'Busca conversas anteriores do mesmo contato',
                'tool_type' => 'system',
                'function_schema' => [
                    'type' => 'function',
                    'function' => [
                        'name' => 'buscar_conversas_anteriores',
                        'description' => 'Busca conversas anteriores do mesmo contato para contexto',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => []
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Adicionar Tag',
                'slug' => 'adicionar_tag',
                'description' => 'Adiciona uma tag à conversa',
                'tool_type' => 'system',
                'function_schema' => [
                    'type' => 'function',
                    'function' => [
                        'name' => 'adicionar_tag',
                        'description' => 'Adiciona uma tag à conversa atual',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'tag' => [
                                    'type' => 'string',
                                    'description' => 'Nome da tag a ser adicionada'
                                ]
                            ],
                            'required' => ['tag']
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Escalar para Humano',
                'slug' => 'escalar_para_humano',
                'description' => 'Escala a conversa para um agente humano',
                'tool_type' => 'system',
                'function_schema' => [
                    'type' => 'function',
                    'function' => [
                        'name' => 'escalar_para_humano',
                        'description' => 'Escala a conversa para um agente humano quando necessário',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => []
                        ]
                    ]
                ]
            ]
        ];
    }
}

