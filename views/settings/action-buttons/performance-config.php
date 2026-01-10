<?php
/**
 * Configurações de Análise de Performance de Vendedores
 * Para incluir em views/settings/action-buttons/index.php
 */
$perfSettings = $conversationSettings['agent_performance_analysis'] ?? [];
?>

<div class="card mb-5">
    <div class="card-header">
        <h3 class="card-title">📊 Análise de Performance de Vendedores (OpenAI)</h3>
    </div>
    <div class="card-body">
        
        <!-- Habilitar -->
        <div class="mb-5">
            <label class="form-check form-switch form-check-custom form-check-solid">
                <input class="form-check-input" type="checkbox" name="agent_performance_analysis[enabled]" 
                       value="1" <?= !empty($perfSettings['enabled']) ? 'checked' : '' ?>>
                <span class="form-check-label fw-bold">Habilitar análise de performance</span>
            </label>
            <div class="form-text">Analisa automaticamente a performance dos vendedores em conversas fechadas usando OpenAI</div>
        </div>
        
        <div id="agent_performance_analysis_settings" style="display: <?= !empty($perfSettings['enabled']) ? 'block' : 'none' ?>;">
        <div class="row">
            <!-- Modelo -->
            <div class="col-md-6 mb-5">
                <label class="form-label required">Modelo OpenAI</label>
                <select name="agent_performance_analysis[model]" class="form-select">
                    <option value="gpt-3.5-turbo" <?= ($perfSettings['model'] ?? '') === 'gpt-3.5-turbo' ? 'selected' : '' ?>>
                        GPT-3.5 Turbo (Econômico)
                    </option>
                    <option value="gpt-4o" <?= ($perfSettings['model'] ?? '') === 'gpt-4o' ? 'selected' : '' ?>>
                        GPT-4o (Recomendado)
                    </option>
                    <option value="gpt-4-turbo" <?= ($perfSettings['model'] ?? 'gpt-4-turbo') === 'gpt-4-turbo' ? 'selected' : '' ?>>
                        GPT-4 Turbo (Preciso)
                    </option>
                    <option value="gpt-4" <?= ($perfSettings['model'] ?? '') === 'gpt-4' ? 'selected' : '' ?>>
                        GPT-4 (Mais preciso, mais caro)
                    </option>
                </select>
                <div class="form-text">GPT-4-turbo oferece melhor custo-benefício</div>
            </div>
            
            <!-- Temperature -->
            <div class="col-md-6 mb-5">
                <label class="form-label">Temperature</label>
                <input type="number" name="agent_performance_analysis[temperature]" class="form-control" 
                       value="<?= $perfSettings['temperature'] ?? 0.3 ?>" min="0" max="1" step="0.1">
                <div class="form-text">Quanto menor, mais determinístico (padrão: 0.3)</div>
            </div>
        </div>
        
        <div class="row">
            <!-- Intervalo de verificação -->
            <div class="col-md-4 mb-5">
                <label class="form-label">Intervalo de Verificação (horas)</label>
                <input type="number" name="agent_performance_analysis[check_interval_hours]" class="form-control" 
                       value="<?= $perfSettings['check_interval_hours'] ?? 24 ?>" min="1">
                <div class="form-text">A cada quantas horas verificar conversas</div>
            </div>
            
            <!-- Idade máxima -->
            <div class="col-md-4 mb-5">
                <label class="form-label">Idade Máxima da Conversa (dias)</label>
                <input type="number" name="agent_performance_analysis[max_conversation_age_days]" class="form-control" 
                       value="<?= $perfSettings['max_conversation_age_days'] ?? 7 ?>" min="1">
                <div class="form-text">Não analisar conversas mais antigas que X dias</div>
            </div>
            
            <!-- Limite de custo -->
            <div class="col-md-4 mb-5">
                <label class="form-label">Limite de Custo Diário (USD)</label>
                <input type="number" name="agent_performance_analysis[cost_limit_per_day]" class="form-control" 
                       value="<?= $perfSettings['cost_limit_per_day'] ?? 10 ?>" min="0" step="0.01">
                <div class="form-text">Limite máximo de gasto por dia (0 = ilimitado)</div>
            </div>
        </div>
        
        <div class="row">
            <!-- Mín mensagens totais -->
            <div class="col-md-4 mb-5">
                <label class="form-label">Mín. Mensagens Totais</label>
                <input type="number" name="agent_performance_analysis[min_messages_to_analyze]" class="form-control" 
                       value="<?= $perfSettings['min_messages_to_analyze'] ?? 5 ?>" min="1">
                <div class="form-text">Mínimo de mensagens na conversa</div>
            </div>
            
            <!-- Mín mensagens do agente -->
            <div class="col-md-4 mb-5">
                <label class="form-label">Mín. Mensagens do Agente</label>
                <input type="number" name="agent_performance_analysis[min_agent_messages]" class="form-control" 
                       value="<?= $perfSettings['min_agent_messages'] ?? 3 ?>" min="1">
                <div class="form-text">Mínimo de mensagens do vendedor</div>
            </div>
            
            <!-- Apenas fechadas -->
            <div class="col-md-4 mb-5">
                <label class="form-label">Analisar Apenas Conversas Fechadas</label>
                <select name="agent_performance_analysis[analyze_closed_only]" class="form-select">
                    <option value="1" <?= !empty($perfSettings['analyze_closed_only']) ? 'selected' : '' ?>>Sim (Recomendado)</option>
                    <option value="0" <?= empty($perfSettings['analyze_closed_only']) ? 'selected' : '' ?>>Não</option>
                </select>
            </div>
        </div>
        
        <!-- Separador -->
        <div class="separator my-5"></div>
        <h4 class="fw-bold">⚙️ Funcionalidades</h4>
        
        <div class="row">
            <!-- Gamificação -->
            <div class="col-md-4 mb-5">
                <label class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" 
                           name="agent_performance_analysis[gamification][enabled]" value="1"
                           <?= !empty($perfSettings['gamification']['enabled']) ? 'checked' : '' ?>>
                    <span class="form-check-label fw-bold">🎮 Gamificação</span>
                </label>
                <div class="form-text">Premiar badges e conquistas</div>
            </div>
            
            <!-- Coaching -->
            <div class="col-md-4 mb-5">
                <label class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" 
                           name="agent_performance_analysis[coaching][enabled]" value="1"
                           <?= !empty($perfSettings['coaching']['enabled']) ? 'checked' : '' ?>>
                    <span class="form-check-label fw-bold">🎯 Coaching Automático</span>
                </label>
                <div class="form-text">Criar metas e feedback automático</div>
            </div>
            
            <!-- Melhores práticas -->
            <div class="col-md-4 mb-5">
                <label class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" 
                           name="agent_performance_analysis[coaching][save_best_practices]" value="1"
                           <?= !empty($perfSettings['coaching']['save_best_practices']) ? 'checked' : '' ?>>
                    <span class="form-check-label fw-bold">📚 Melhores Práticas</span>
                </label>
                <div class="form-text">Salvar conversas excelentes (>= 4.5)</div>
            </div>
        </div>
        
        <!-- Nota mínima para melhor prática -->
        <div class="row">
            <div class="col-md-6 mb-5">
                <label class="form-label">Nota Mínima para Melhor Prática</label>
                <input type="number" name="agent_performance_analysis[coaching][min_score_for_best_practice]" 
                       class="form-control" value="<?= $perfSettings['coaching']['min_score_for_best_practice'] ?? 4.5 ?>" 
                       min="0" max="5" step="0.1">
                <div class="form-text">Conversas com nota acima disso viram exemplos</div>
            </div>
        </div>
        
        <!-- Separador -->
        <div class="separator my-5"></div>
        <h4 class="fw-bold">📊 Dimensões Avaliadas</h4>
        <p class="text-muted mb-5">Cada dimensão pode ser habilitada/desabilitada e tem um peso na nota final</p>
        
        <?php
        $dimensions = [
            'proactivity' => ['name' => 'Proatividade', 'icon' => '🚀', 'desc' => 'Toma iniciativa, faz perguntas'],
            'objection_handling' => ['name' => 'Quebra de Objeções', 'icon' => '💪', 'desc' => 'Responde objeções estruturadamente'],
            'rapport' => ['name' => 'Rapport', 'icon' => '🤝', 'desc' => 'Cria conexão com o cliente'],
            'closing_techniques' => ['name' => 'Fechamento', 'icon' => '🎯', 'desc' => 'Tenta fechar, usa técnicas'],
            'qualification' => ['name' => 'Qualificação', 'icon' => '🎓', 'desc' => 'Faz perguntas BANT'],
            'clarity' => ['name' => 'Clareza', 'icon' => '💬', 'desc' => 'Explica de forma clara'],
            'value_proposition' => ['name' => 'Valor', 'icon' => '💎', 'desc' => 'Apresenta valor vs features'],
            'response_time' => ['name' => 'Tempo de Resposta', 'icon' => '⚡', 'desc' => 'Responde rapidamente'],
            'follow_up' => ['name' => 'Follow-up', 'icon' => '📅', 'desc' => 'Define próximos passos'],
            'professionalism' => ['name' => 'Profissionalismo', 'icon' => '🎩', 'desc' => 'Gramática, tom, postura'],
        ];
        
        foreach ($dimensions as $key => $dim):
            $enabled = $perfSettings['dimensions'][$key]['enabled'] ?? true;
            $weight = $perfSettings['dimensions'][$key]['weight'] ?? 1.0;
        ?>
        <div class="row align-items-center mb-3 py-3 border-bottom">
            <div class="col-md-5">
                <label class="form-check form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" 
                           name="agent_performance_analysis[dimensions][<?= $key ?>][enabled]" value="1"
                           <?= $enabled ? 'checked' : '' ?>>
                    <span class="form-check-label fw-bold">
                        <?= $dim['icon'] ?> <?= $dim['name'] ?>
                    </span>
                </label>
                <div class="text-muted fs-7"><?= $dim['desc'] ?></div>
            </div>
            <div class="col-md-3">
                <label class="form-label fs-7 mb-1">Peso:</label>
                <input type="number" name="agent_performance_analysis[dimensions][<?= $key ?>][weight]" 
                       class="form-control form-control-sm" value="<?= $weight ?>" 
                       min="0" max="3" step="0.1">
            </div>
            <div class="col-md-4">
                <span class="badge badge-light-primary"><?= $weight ?>x na média final</span>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Info -->
        <div class="alert alert-info mt-5">
            <div class="d-flex">
                <i class="ki-duotone ki-information fs-2x text-info me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                <div>
                    <h5 class="mb-1">ℹ️ Como funciona</h5>
                    <p class="mb-2">O sistema analisa automaticamente conversas fechadas e avalia o vendedor em 10 dimensões diferentes, usando OpenAI GPT.</p>
                    <ul class="mb-0">
                        <li><strong>Nota geral:</strong> Média ponderada das 10 dimensões</li>
                        <li><strong>Badges:</strong> Premiações automáticas por conquistas</li>
                        <li><strong>Metas:</strong> Criadas automaticamente para dimensões com nota < 3.5</li>
                        <li><strong>Práticas:</strong> Conversas com nota >= 4.5 viram exemplos</li>
                    </ul>
                    <p class="mb-0 mt-2">
                        <strong>Custo estimado:</strong> ~$0.02 por análise com GPT-4-turbo | 
                        <a href="<?= \App\Helpers\Url::to('/agent-performance') ?>" target="_blank">Ver Dashboard →</a>
                    </p>
                </div>
            </div>
        </div>
        </div><!-- Fecha #agent_performance_analysis_settings -->
        
    </div>
</div>
