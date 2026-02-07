<?php
/**
 * Controller AIAssistantController
 * Endpoints para o Assistente IA no chat
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\AIAssistantService;
use App\Services\AIAssistantFeatureService;
use App\Services\AIAgentSelectorService;

class AIAssistantController
{
    /**
     * Listar funcionalidades disponíveis para o usuário
     */
    public function getFeatures(): void
    {
        Permission::abortIfCannot('ai_assistant.use');
        
        try {
            $userId = \App\Helpers\Auth::id();
            $features = AIAssistantFeatureService::listForUser($userId);
            
            Response::json([
                'success' => true,
                'features' => $features
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gerar resposta
     */
    public function generateResponse(): void
    {
        Permission::abortIfCannot('ai_assistant.use');
        
        try {
            $userId = \App\Helpers\Auth::id();
            $conversationId = (int)Request::post('conversation_id');
            $count = (int)(Request::post('count') ?? 3);
            $tone = Request::post('tone') ?? 'professional';

            if (!$conversationId) {
                throw new \Exception('ID da conversa é obrigatório');
            }

            $result = AIAssistantService::generateResponse(
                $userId,
                $conversationId,
                'generate_response',
                [
                    'count' => $count,
                    'tone' => $tone
                ]
            );

            Response::json($result);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Executar funcionalidade específica
     */
    public function executeFeature(): void
    {
        Permission::abortIfCannot('ai_assistant.use');
        
        try {
            $userId = \App\Helpers\Auth::id();
            $conversationId = (int)Request::post('conversation_id');
            $featureKey = Request::post('feature_key');

            if (!$conversationId) {
                throw new \Exception('ID da conversa é obrigatório');
            }

            if (!$featureKey) {
                throw new \Exception('Chave da funcionalidade é obrigatória');
            }

            $options = Request::post('options') ?? [];

            $result = AIAssistantService::executeFeature(
                $userId,
                $conversationId,
                $featureKey,
                $options
            );

            Response::json($result);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter agente selecionado para contexto
     */
    public function getSelectedAgent(): void
    {
        Permission::abortIfCannot('ai_assistant.use');
        
        try {
            $userId = \App\Helpers\Auth::id();
            $conversationId = (int)Request::get('conversation_id');
            $featureKey = Request::get('feature_key') ?? 'generate_response';

            if (!$conversationId) {
                throw new \Exception('ID da conversa é obrigatório');
            }

            $context = AIAgentSelectorService::buildContext($conversationId);
            $agentId = AIAgentSelectorService::selectAgent($userId, $featureKey, $context);
            
            if (!$agentId) {
                Response::json([
                    'success' => false,
                    'message' => 'Nenhum agente disponível'
                ]);
                return;
            }

            $agentInfo = AIAgentSelectorService::getAgentInfo($agentId);

            Response::json([
                'success' => true,
                'agent' => $agentInfo,
                'context' => $context
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar configuração do usuário
     */
    public function updateUserSetting(): void
    {
        Permission::abortIfCannot('conversations.view');
        
        try {
            $userId = \App\Helpers\Auth::id();
            $featureKey = Request::post('feature_key');
            $data = Request::post();

            if (!$featureKey) {
                throw new \Exception('Chave da funcionalidade é obrigatória');
            }

            $result = AIAssistantFeatureService::updateUserSetting($userId, $featureKey, $data);

            Response::json([
                'success' => $result,
                'message' => $result ? 'Configuração atualizada com sucesso' : 'Erro ao atualizar configuração'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar funcionalidade (admin)
     */
    public function updateFeature(string $featureKey): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $feature = \App\Models\AIAssistantFeature::getByKey($featureKey);
            if (!$feature) {
                throw new \Exception('Funcionalidade não encontrada');
            }

            $data = Request::post();
            $result = AIAssistantFeatureService::updateFeature($feature['id'], $data);

            Response::json([
                'success' => $result,
                'message' => $result ? 'Funcionalidade atualizada com sucesso' : 'Erro ao atualizar funcionalidade'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar regras de seleção de agentes
     */
    public function getRules(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            // Passar string vazia para obter todas as regras
            $rules = \App\Models\AIAssistantFeatureAgent::getRulesForFeature('');
            
            // Enriquecer com nomes
            foreach ($rules as &$rule) {
                $agent = \App\Models\AIAgent::find($rule['ai_agent_id']);
                $feature = \App\Models\AIAssistantFeature::getByKey($rule['feature_key']);
                $rule['agent_name'] = $agent ? $agent['name'] : 'N/A';
                $rule['feature_name'] = $feature ? $feature['name'] : $rule['feature_key'];
            }

            Response::json([
                'success' => true,
                'rules' => $rules
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Criar regra de seleção de agente
     */
    public function createRule(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $data = Request::post();
            
            if (empty($data['feature_key']) || empty($data['ai_agent_id'])) {
                throw new \Exception('Funcionalidade e Agente são obrigatórios');
            }

            $priority = (int)($data['priority'] ?? 0);
            $conditions = $data['conditions'] ?? [];
            
            // Converter tags de string para array se necessário
            if (isset($conditions['tags']) && is_string($conditions['tags'])) {
                $conditions['tags'] = array_filter(array_map('trim', explode(',', $conditions['tags'])));
            }

            $result = \App\Models\AIAssistantFeatureAgent::addRule(
                $data['feature_key'],
                (int)$data['ai_agent_id'],
                $priority,
                $conditions
            );

            Response::json([
                'success' => $result,
                'message' => $result ? 'Regra criada com sucesso' : 'Erro ao criar regra'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Excluir regra de seleção de agente
     */
    public function deleteRule(int $ruleId): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $rule = \App\Models\AIAssistantFeatureAgent::find($ruleId);
            if (!$rule) {
                throw new \Exception('Regra não encontrada');
            }

            $result = \App\Models\AIAssistantFeatureAgent::delete($ruleId);

            Response::json([
                'success' => $result,
                'message' => $result ? 'Regra excluída com sucesso' : 'Erro ao excluir regra'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar configurações de uma funcionalidade
     */
    public function updateFeatureSettings(string $featureKey): void
    {
        Permission::abortIfCannot('ai_assistant.configure');
        
        try {
            $feature = \App\Models\AIAssistantFeature::getByKey($featureKey);
            if (!$feature) {
                throw new \Exception('Funcionalidade não encontrada');
            }

            $data = Request::post();
            $settings = $data['settings'] ?? [];

            // Obter configurações atuais
            $currentSettings = json_decode($feature['settings'] ?? '{}', true);
            
            // Mesclar com novas configurações
            $mergedSettings = array_merge($currentSettings, $settings);

            // Atualizar
            $result = \App\Models\AIAssistantFeature::updateSettings($featureKey, $mergedSettings);
            
            // Invalidar cache
            if ($result) {
                \App\Services\AIAssistantFeatureService::invalidateCache();
            }

            Response::json([
                'success' => $result,
                'message' => $result ? 'Configurações salvas com sucesso' : 'Erro ao salvar configurações'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter logs de uso do Assistente IA
     */
    public function getLogs(): void
    {
        Permission::abortIfCannot('ai_assistant.view_logs');
        
        try {
            $userId = Request::get('user_id') ? (int)Request::get('user_id') : null;
            $conversationId = Request::get('conversation_id') ? (int)Request::get('conversation_id') : null;
            $featureKey = Request::get('feature_key');
            $limit = (int)(Request::get('limit') ?? 50);
            
            $logs = \App\Models\AIAssistantLog::getRecent($limit, $userId, $conversationId);
            
            Response::json([
                'success' => true,
                'logs' => $logs
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter estatísticas do Assistente IA
     */
    public function getStats(): void
    {
        Permission::abortIfCannot('ai_assistant.view_logs');
        
        try {
            $days = (int)(Request::get('days') ?? 30);
            $featureKey = Request::get('feature_key');
            $agentId = Request::get('agent_id') ? (int)Request::get('agent_id') : null;
            $includeCharts = Request::get('include_charts') === 'true';
            
            $statsByFeature = \App\Models\AIAssistantLog::getStatsByFeature($featureKey, $days);
            $statsByAgent = \App\Models\AIAssistantLog::getStatsByAgent($agentId, $days);
            
            // Calcular totais
            $totalUses = array_sum(array_column($statsByFeature, 'total_uses'));
            $totalTokens = array_sum(array_column($statsByFeature, 'total_tokens'));
            $totalCost = array_sum(array_column($statsByFeature, 'total_cost'));
            $totalSuccessful = array_sum(array_column($statsByFeature, 'successful_uses'));
            $totalFailed = array_sum(array_column($statsByFeature, 'failed_uses'));
            
            $result = [
                'success' => true,
                'stats' => [
                    'by_feature' => $statsByFeature,
                    'by_agent' => $statsByAgent,
                    'totals' => [
                        'total_uses' => $totalUses,
                        'total_tokens' => $totalTokens,
                        'total_cost' => $totalCost,
                        'total_successful' => $totalSuccessful,
                        'total_failed' => $totalFailed,
                        'success_rate' => $totalUses > 0 ? ($totalSuccessful / $totalUses) * 100 : 0,
                        'avg_cost_per_use' => $totalUses > 0 ? $totalCost / $totalUses : 0,
                        'avg_tokens_per_use' => $totalUses > 0 ? $totalTokens / $totalUses : 0,
                        'days' => $days
                    ]
                ]
            ];
            
            // Adicionar dados para gráficos se solicitado
            if ($includeCharts) {
                $groupBy = $days <= 7 ? 'hour' : ($days <= 30 ? 'day' : 'day');
                $usageOverTime = \App\Models\AIAssistantLog::getUsageOverTime($days, $groupBy);
                $costByModel = \App\Models\AIAssistantLog::getCostByModel($days);
                
                $result['stats']['usage_over_time'] = $usageOverTime;
                $result['stats']['cost_by_model'] = $costByModel;
            }
            
            Response::json($result);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter histórico de respostas geradas
     */
    public function getResponseHistory(): void
    {
        Permission::abortIfCannot('ai_assistant.use');
        
        try {
            $userId = \App\Helpers\Auth::id();
            $conversationId = (int)Request::get('conversation_id');
            $limit = (int)(Request::get('limit') ?? 20);
            
            if (!$conversationId) {
                throw new \Exception('ID da conversa é obrigatório');
            }
            
            $history = \App\Models\AIAssistantResponse::getHistory($conversationId, $userId, $limit);
            
            Response::json([
                'success' => true,
                'history' => $history
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter respostas favoritas do usuário
     */
    public function getFavorites(): void
    {
        Permission::abortIfCannot('ai_assistant.use');
        
        try {
            $userId = \App\Helpers\Auth::id();
            $limit = (int)(Request::get('limit') ?? 50);
            
            $favorites = \App\Models\AIAssistantResponse::getFavorites($userId, $limit);
            
            Response::json([
                'success' => true,
                'favorites' => $favorites
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Alternar favorito de uma resposta
     */
    public function toggleFavorite(): void
    {
        Permission::abortIfCannot('ai_assistant.use');
        
        try {
            $responseId = (int)Request::post('response_id');
            
            if (!$responseId) {
                throw new \Exception('ID da resposta é obrigatório');
            }
            
            $success = \App\Models\AIAssistantResponse::toggleFavorite($responseId);
            
            Response::json([
                'success' => $success,
                'message' => $success ? 'Favorito atualizado' : 'Erro ao atualizar favorito'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar resposta como usada
     */
    public function markAsUsed(): void
    {
        Permission::abortIfCannot('ai_assistant.use');
        
        try {
            $responseId = (int)Request::post('response_id');
            
            if (!$responseId) {
                throw new \Exception('ID da resposta é obrigatório');
            }
            
            $success = \App\Models\AIAssistantResponse::markAsUsed($responseId);
            
            Response::json([
                'success' => $success,
                'message' => $success ? 'Resposta marcada como usada' : 'Erro ao marcar resposta'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar disponibilidade do Assistente IA antes de usar
     */
    public function checkAvailability(): void
    {
        Permission::abortIfCannot('ai_assistant.use');
        
        try {
            $userId = \App\Helpers\Auth::id();
            $conversationId = Request::get('conversation_id') ? (int)Request::get('conversation_id') : null;
            $featureKey = Request::get('feature_key') ?? 'generate_response';
            
            $issues = [];
            $warnings = [];
            
            // 1. Verificar API Key da OpenAI
            $apiKey = \App\Models\Setting::get('openai_api_key');
            if (empty($apiKey)) {
                $apiKey = getenv('OPENAI_API_KEY') ?: null;
            }
            if (empty($apiKey)) {
                $issues[] = [
                    'type' => 'api_key',
                    'severity' => 'error',
                    'title' => 'API Key não configurada',
                    'message' => 'A chave de API da OpenAI não está configurada. Configure em Configurações > Geral > OpenAI.',
                    'action' => 'configure_api_key',
                    'action_url' => \App\Helpers\Url::to('/settings?tab=general')
                ];
            }
            
            // 2. Verificar se há funcionalidades disponíveis
            $features = AIAssistantFeatureService::listForUser($userId);
            if (empty($features)) {
                $issues[] = [
                    'type' => 'no_features',
                    'severity' => 'error',
                    'title' => 'Nenhuma funcionalidade disponível',
                    'message' => 'Não há funcionalidades do Assistente IA habilitadas para você.',
                    'action' => 'contact_admin'
                ];
            } else {
                // Verificar se a funcionalidade específica está disponível
                $featureAvailable = false;
                foreach ($features as $feature) {
                    if ($feature['feature_key'] === $featureKey) {
                        $featureAvailable = true;
                        break;
                    }
                }
                
                if (!$featureAvailable) {
                    $issues[] = [
                        'type' => 'feature_disabled',
                        'severity' => 'error',
                        'title' => 'Funcionalidade não disponível',
                        'message' => "A funcionalidade '{$featureKey}' não está disponível para você.",
                        'action' => 'use_other_feature'
                    ];
                }
            }
            
            // 3. Verificar se há agentes disponíveis (com criação automática)
            $availableAgents = \App\Models\AIAgent::getAvailableAgents();
            if (empty($availableAgents)) {
                // Tentar criar agentes especializados automaticamente
                try {
                    \App\Helpers\Logger::info('[Assistente IA] Nenhum agente encontrado. Tentando criar agentes especializados automaticamente...');
                    
                    // Executar seed de agentes especializados
                    require_once __DIR__ . '/../../database/seeds/006_create_ai_assistant_specialized_agents.php';
                    seed_ai_assistant_specialized_agents();
                    
                    // Verificar novamente
                    $availableAgents = \App\Models\AIAgent::getAvailableAgents();
                    
                    if (empty($availableAgents)) {
                        $issues[] = [
                            'type' => 'no_agents',
                            'severity' => 'error',
                            'title' => 'Nenhum agente de IA disponível',
                            'message' => 'Não foi possível criar agentes automaticamente. Configure manualmente em Agentes de IA.',
                            'action' => 'configure_agents',
                            'action_url' => \App\Helpers\Url::to('/ai-agents')
                        ];
                    } else {
                        \App\Helpers\Logger::info('[Assistente IA] Agentes especializados criados automaticamente com sucesso!');
                    }
                } catch (\Exception $e) {
                    \App\Helpers\Logger::error('[Assistente IA] Erro ao criar agentes automaticamente: ' . $e->getMessage());
                    $issues[] = [
                        'type' => 'no_agents',
                        'severity' => 'error',
                        'title' => 'Nenhum agente de IA disponível',
                        'message' => 'Não há agentes de IA habilitados no sistema e não foi possível criá-los automaticamente.',
                        'action' => 'configure_agents',
                        'action_url' => \App\Helpers\Url::to('/ai-agents')
                    ];
                }
            }
            
            // Se há agentes disponíveis, verificar contexto da conversa
            if (!empty($availableAgents)) {
                // Se há conversa, verificar se há agente para ela
                if ($conversationId) {
                    $context = AIAgentSelectorService::buildContext($conversationId);
                    $agentId = AIAgentSelectorService::selectAgent($userId, $featureKey, $context);
                    
                    if (!$agentId) {
                        $warnings[] = [
                            'type' => 'no_agent_for_context',
                            'severity' => 'warning',
                            'title' => 'Nenhum agente específico encontrado',
                            'message' => 'Não foi possível selecionar um agente específico para este contexto. Um agente padrão será usado.',
                            'action' => 'use_default'
                        ];
                    }
                }
            }
            
            // 4. Verificar se há conversa selecionada (para funcionalidades que precisam)
            if ($conversationId) {
                $conversation = \App\Models\Conversation::find($conversationId);
                if (!$conversation) {
                    $issues[] = [
                        'type' => 'invalid_conversation',
                        'severity' => 'error',
                        'title' => 'Conversa não encontrada',
                        'message' => 'A conversa selecionada não existe ou você não tem permissão para acessá-la.',
                        'action' => 'select_conversation'
                    ];
                }
            } else {
                // Algumas funcionalidades precisam de conversa
                $featuresRequiringConversation = ['generate_response', 'summarize', 'suggest_tags', 'analyze_sentiment', 'suggest_next_steps', 'extract_info'];
                if (in_array($featureKey, $featuresRequiringConversation)) {
                    $issues[] = [
                        'type' => 'no_conversation',
                        'severity' => 'error',
                        'title' => 'Conversa não selecionada',
                        'message' => 'Esta funcionalidade requer que uma conversa esteja selecionada.',
                        'action' => 'select_conversation'
                    ];
                }
            }
            
            Response::json([
                'success' => empty($issues),
                'available' => empty($issues),
                'issues' => $issues,
                'warnings' => $warnings,
                'features_count' => count($features),
                'agents_count' => count($availableAgents),
                'has_api_key' => !empty($apiKey)
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'available' => false,
                'message' => $e->getMessage(),
                'issues' => [[
                    'type' => 'unknown_error',
                    'severity' => 'error',
                    'title' => 'Erro ao verificar disponibilidade',
                    'message' => $e->getMessage()
                ]]
            ], 500);
        }
    }
}

