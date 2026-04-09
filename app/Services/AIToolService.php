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

        // Normalizar function_schema antes de salvar
        $data['function_schema'] = self::normalizeFunctionSchema($data['function_schema']);

        // Sincronizar function.name com o slug da tool
        $data['function_schema'] = self::syncFunctionName($data['function_schema'], $data['slug']);

        // Validar schema contra a spec do OpenAI Function Calling
        $schemaErrors = self::validateFunctionSchema($data['function_schema']);
        if (!empty($schemaErrors)) {
            throw new \InvalidArgumentException('Schema inválido para OpenAI Function Calling: ' . implode('; ', $schemaErrors));
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

        // Normalizar function_schema antes de salvar
        if (isset($data['function_schema']) && is_array($data['function_schema'])) {
            $data['function_schema'] = self::normalizeFunctionSchema($data['function_schema']);

            // Sincronizar function.name com o slug da tool
            $slug = $data['slug'] ?? $tool['slug'] ?? null;
            if ($slug) {
                $data['function_schema'] = self::syncFunctionName($data['function_schema'], $slug);
            }

            // Validar schema contra a spec do OpenAI Function Calling
            $schemaErrors = self::validateFunctionSchema($data['function_schema']);
            if (!empty($schemaErrors)) {
                throw new \InvalidArgumentException('Schema inválido para OpenAI Function Calling: ' . implode('; ', $schemaErrors));
            }

            $data['function_schema'] = json_encode($data['function_schema'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['config']) && \is_array($data['config'])) {
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
     * Normalizar function schema para formato correto da OpenAI
     * Corrige problemas como properties: [] ao invés de properties: {}
     */
    /**
     * Sincroniza o function.name do schema com o slug da tool
     */
    private static function syncFunctionName(array $schema, string $slug): array
    {
        // Multi-função: cada item já tem seu próprio nome — não sobrescrever.
        if (!empty($schema) && array_keys($schema) === range(0, count($schema) - 1)) {
            return $schema;
        }
        if (isset($schema['function']['name'])) {
            $schema['function']['name'] = $slug;
        } elseif (isset($schema['name'])) {
            $schema['name'] = $slug;
        }
        return $schema;
    }

    private static function normalizeFunctionSchema(array $schema): array
    {
        // Multi-função: lista de schemas — normaliza cada item recursivamente
        if (!empty($schema) && array_keys($schema) === range(0, count($schema) - 1)) {
            foreach ($schema as $i => $sub) {
                if (\is_array($sub)) {
                    $schema[$i] = self::normalizeFunctionSchema($sub);
                }
            }
            return $schema;
        }

        // Se é o formato wrapper {type: function, function: {...}}
        if (isset($schema['function'])) {
            $func = &$schema['function'];
        } else {
            $func = &$schema;
        }
        
        // Corrigir parameters
        if (isset($func['parameters'])) {
            $params = &$func['parameters'];
            
            // Garantir que type é 'object'
            if (!isset($params['type'])) {
                $params['type'] = 'object';
            }
            
            // Corrigir properties: [] para properties: {} (objeto vazio)
            if (!isset($params['properties']) || (is_array($params['properties']) && empty($params['properties']))) {
                $params['properties'] = new \stdClass(); // Será {} no JSON
            }
            
            // Garantir required existe
            if (!isset($params['required'])) {
                $params['required'] = [];
            }
        } else {
            // Adicionar parameters padrão se não existir
            $func['parameters'] = [
                'type' => 'object',
                'properties' => new \stdClass(),
                'required' => []
            ];
        }
        
        return $schema;
    }

    /**
     * Valida o function_schema contra a spec do OpenAI Function Calling.
     * Retorna array de mensagens de erro (vazio = ok).
     *
     * Regras cobertas:
     *  - function.name presente, ^[a-zA-Z0-9_-]{1,64}$
     *  - function.description presente e não vazia
     *  - parameters.type === 'object'
     *  - properties é objeto/dicionário (não array indexado)
     *  - required[] é array e só referencia keys existentes em properties
     *  - cada propriedade tem 'type' válido e idealmente 'description'
     */
    public static function validateFunctionSchema(array $schema): array
    {
        // Suporte a multi-função: se vier uma lista, valida cada item.
        if (!empty($schema) && array_keys($schema) === range(0, count($schema) - 1)) {
            $allErrors = [];
            foreach ($schema as $i => $sub) {
                if (!\is_array($sub)) {
                    $allErrors[] = "schema #{$i}: deve ser um objeto";
                    continue;
                }
                $subErrors = self::validateFunctionSchema($sub);
                foreach ($subErrors as $e) {
                    $allErrors[] = "schema #{$i}: {$e}";
                }
            }
            return $allErrors;
        }

        $errors = [];

        // Aceita formato wrapper {type:'function', function:{...}} ou direto
        $func = $schema['function'] ?? $schema;

        // 1) name
        $name = $func['name'] ?? null;
        if (!\is_string($name) || $name === '') {
            $errors[] = 'function.name é obrigatório';
        } elseif (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $name)) {
            $errors[] = "function.name '{$name}' inválido (use apenas a-z, A-Z, 0-9, _ ou -, máx 64 chars)";
        }

        // 2) description
        $desc = $func['description'] ?? null;
        if (!\is_string($desc) || trim($desc) === '') {
            $errors[] = 'function.description é obrigatória e não pode estar vazia (o LLM usa para escolher a tool)';
        }

        // 3) parameters
        if (isset($func['parameters'])) {
            $params = $func['parameters'];

            // parameters pode vir como stdClass (JSON {}) — converter
            if ($params instanceof \stdClass) {
                $params = (array) $params;
            }

            if (!\is_array($params)) {
                $errors[] = 'function.parameters deve ser um objeto';
            } else {
                $type = $params['type'] ?? null;
                if ($type !== 'object') {
                    $errors[] = "function.parameters.type deve ser 'object' (recebido: " . var_export($type, true) . ')';
                }

                $properties = $params['properties'] ?? null;
                if ($properties instanceof \stdClass) {
                    $properties = (array) $properties;
                }

                // properties pode estar vazio (tool sem parâmetros) — ok
                if ($properties !== null && !\is_array($properties)) {
                    $errors[] = 'function.parameters.properties deve ser um objeto';
                }

                $validTypes = ['string','number','integer','boolean','array','object','null'];
                if (\is_array($properties)) {
                    // Detectar array indexado (lista) onde deveria ser objeto
                    if (!empty($properties) && array_keys($properties) === range(0, count($properties) - 1)) {
                        $errors[] = 'function.parameters.properties deve ser um objeto (chave→def), não uma lista indexada';
                    } else {
                        foreach ($properties as $propName => $propDef) {
                            if (!\is_string($propName) || $propName === '') {
                                $errors[] = 'cada propriedade em parameters.properties precisa de uma chave string não-vazia';
                                continue;
                            }
                            if ($propDef instanceof \stdClass) {
                                $propDef = (array) $propDef;
                            }
                            if (!\is_array($propDef)) {
                                $errors[] = "propriedade '{$propName}' deve ser um objeto";
                                continue;
                            }
                            $pType = $propDef['type'] ?? null;
                            if ($pType === null) {
                                $errors[] = "propriedade '{$propName}' não tem 'type'";
                            } elseif (\is_string($pType) && !\in_array($pType, $validTypes, true)) {
                                $errors[] = "propriedade '{$propName}' tem type inválido '{$pType}' (use: " . implode(', ', $validTypes) . ')';
                            }
                            if (!isset($propDef['description']) || !\is_string($propDef['description']) || trim($propDef['description']) === '') {
                                $errors[] = "propriedade '{$propName}' deveria ter 'description' (ajuda o LLM a preencher corretamente)";
                            }
                        }
                    }
                }

                // required[]
                if (isset($params['required'])) {
                    if (!\is_array($params['required'])) {
                        $errors[] = 'function.parameters.required deve ser um array';
                    } else {
                        $propKeys = \is_array($properties) ? array_keys($properties) : [];
                        foreach ($params['required'] as $req) {
                            if (!\is_string($req)) {
                                $errors[] = 'function.parameters.required deve conter apenas strings';
                                continue;
                            }
                            if (!\in_array($req, $propKeys, true)) {
                                $errors[] = "required referencia '{$req}' que não existe em properties";
                            }
                        }
                    }
                }
            }
        }

        return $errors;
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
                            'properties' => new \stdClass(),
                            'required' => []
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
                            'properties' => new \stdClass(),
                            'required' => []
                        ]
                    ]
                ]
            ]
        ];
    }
}

