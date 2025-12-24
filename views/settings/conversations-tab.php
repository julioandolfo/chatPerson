<?php
/**
 * Aba de Configurações Avançadas de Conversas
 */
$cs = $conversationSettings ?? [];
$gl = $cs['global_limits'] ?? [];
$sla = $cs['sla'] ?? [];
$dist = $cs['distribution'] ?? [];
$pctDist = $cs['percentage_distribution'] ?? [];
$reassign = $cs['reassignment'] ?? [];
$sentiment = $cs['sentiment_analysis'] ?? [];
$transcription = $cs['audio_transcription'] ?? [];
$tts = $cs['text_to_speech'] ?? [];
?>
<form id="kt_settings_conversations_form" class="form">
    <!--begin::Configurações de Chat-->
    <div class="mb-10">
        <h4 class="fw-bold mb-4">Configurações de Chat</h4>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="chat_agent_name_enabled" class="form-check-input me-2" 
                       <?= \App\Services\SettingService::get('chat_agent_name_enabled', false) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Habilitar envio do nome do agente por padrão</span>
            </label>
            <div class="form-text">Quando habilitado, o nome do agente será enviado em negrito (*Nome*) antes da mensagem por padrão. O agente pode ativar/desativar individualmente no chat.</div>
        </div>
    </div>
    <!--end::Configurações de Chat-->
    
    <div class="separator separator-dashed my-10"></div>
    
    <!--begin::Limites Globais-->
    <div class="mb-10">
        <h4 class="fw-bold mb-4">Limites Globais</h4>
        <div class="row">
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Máx. Conversas por Agente</label>
                    <input type="number" name="max_conversations_per_agent" class="form-control form-control-solid" 
                           value="<?= $gl['max_conversations_per_agent'] ?? '' ?>" min="1" 
                           placeholder="Deixe vazio para ilimitado" />
                    <div class="form-text">Limite global de conversas simultâneas por agente (pode ser sobrescrito por limite individual)</div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Máx. Conversas por Setor</label>
                    <input type="number" name="max_conversations_per_department" class="form-control form-control-solid" 
                           value="<?= $gl['max_conversations_per_department'] ?? '' ?>" min="1" 
                           placeholder="Deixe vazio para ilimitado" />
                    <div class="form-text">Limite total de conversas simultâneas por setor</div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Máx. Conversas por Funil</label>
                    <input type="number" name="max_conversations_per_funnel" class="form-control form-control-solid" 
                           value="<?= $gl['max_conversations_per_funnel'] ?? '' ?>" min="1" 
                           placeholder="Deixe vazio para ilimitado" />
                    <div class="form-text">Limite total de conversas simultâneas por funil</div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Máx. Conversas por Estágio</label>
                    <input type="number" name="max_conversations_per_stage" class="form-control form-control-solid" 
                           value="<?= $gl['max_conversations_per_stage'] ?? '' ?>" min="1" 
                           placeholder="Deixe vazio para ilimitado" />
                    <div class="form-text">Limite total de conversas simultâneas por estágio</div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Limites Globais-->
    
    <div class="separator separator-dashed my-10"></div>
    
    <!--begin::SLA-->
    <div class="mb-10">
        <h4 class="fw-bold mb-4">SLA (Service Level Agreement)</h4>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="enable_sla_monitoring" class="form-check-input me-2" 
                       <?= ($sla['enable_sla_monitoring'] ?? true) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Habilitar monitoramento de SLA</span>
            </label>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Tempo de Primeira Resposta (minutos)</label>
                    <input type="number" name="sla_first_response_time" class="form-control form-control-solid" 
                           value="<?= $sla['first_response_time'] ?? 15 ?>" min="1" required />
                    <div class="form-text">Tempo máximo para primeira resposta do agente</div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Tempo de Resolução (minutos)</label>
                    <input type="number" name="sla_resolution_time" class="form-control form-control-solid" 
                           value="<?= $sla['resolution_time'] ?? 60 ?>" min="1" required />
                    <div class="form-text">Tempo máximo para resolução da conversa</div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Tempo de Resposta em Conversa (minutos)</label>
                    <input type="number" name="sla_ongoing_response_time" class="form-control form-control-solid" 
                           value="<?= $sla['ongoing_response_time'] ?? 15 ?>" min="1" />
                    <div class="form-text">Tempo para responder novas mensagens durante a conversa</div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" name="enable_resolution_sla" class="form-check-input me-2" 
                               <?= ($sla['enable_resolution_sla'] ?? true) ? 'checked' : '' ?> />
                        <span class="fw-semibold fs-6">Monitorar SLA de Resolução</span>
                    </label>
                    <div class="form-text">Desmarque para usar apenas SLA de respostas</div>
                </div>
            </div>
        </div>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="sla_working_hours_enabled" class="form-check-input me-2" 
                       <?= ($sla['working_hours_enabled'] ?? false) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Considerar apenas horário de atendimento</span>
            </label>
        </div>
        <div class="row">
            <div class="col-lg-3">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Início</label>
                    <input type="time" name="sla_working_hours_start" class="form-control form-control-solid" 
                           value="<?= $sla['working_hours_start'] ?? '08:00' ?>" />
                </div>
            </div>
            <div class="col-lg-3">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Fim</label>
                    <input type="time" name="sla_working_hours_end" class="form-control form-control-solid" 
                           value="<?= $sla['working_hours_end'] ?? '18:00' ?>" />
                </div>
            </div>
        </div>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="auto_reassign_on_sla_breach" class="form-check-input me-2" 
                       <?= ($sla['auto_reassign_on_sla_breach'] ?? true) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Reatribuir automaticamente quando SLA for excedido</span>
            </label>
        </div>
        <div class="fv-row mb-7">
            <label class="fw-semibold fs-6 mb-2">Reatribuir após (minutos)</label>
            <input type="number" name="reassign_after_minutes" class="form-control form-control-solid" 
                   value="<?= $sla['reassign_after_minutes'] ?? 30 ?>" min="1" />
            <div class="form-text">Tempo após exceder SLA para reatribuir conversa</div>
        </div>
    </div>
    <!--end::SLA-->
    
    <div class="separator separator-dashed my-10"></div>
    
    <!--begin::Distribuição-->
    <div class="mb-10">
        <h4 class="fw-bold mb-4">Distribuição e Atribuição</h4>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="enable_auto_assignment" class="form-check-input me-2" 
                       <?= ($dist['enable_auto_assignment'] ?? true) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Habilitar atribuição automática</span>
            </label>
        </div>
        <div class="fv-row mb-7">
            <label class="fw-semibold fs-6 mb-2">Método de Distribuição</label>
            <select name="distribution_method" class="form-select form-select-solid">
                <option value="round_robin" <?= ($dist['method'] ?? 'round_robin') === 'round_robin' ? 'selected' : '' ?>>Round-Robin (Distribuição igual)</option>
                <option value="by_load" <?= ($dist['method'] ?? '') === 'by_load' ? 'selected' : '' ?>>Por Carga (Menor carga primeiro)</option>
                <option value="by_performance" <?= ($dist['method'] ?? '') === 'by_performance' ? 'selected' : '' ?>>Por Performance (Melhor performance primeiro)</option>
                <option value="by_specialty" <?= ($dist['method'] ?? '') === 'by_specialty' ? 'selected' : '' ?>>Por Especialidade</option>
                <option value="percentage" <?= ($dist['method'] ?? '') === 'percentage' ? 'selected' : '' ?>>Por Porcentagem</option>
            </select>
            <div class="form-text">Método usado para distribuir conversas automaticamente</div>
        </div>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="consider_availability" class="form-check-input me-2" 
                       <?= ($dist['consider_availability'] ?? true) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Considerar status de disponibilidade (online/offline)</span>
            </label>
        </div>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="consider_max_conversations" class="form-check-input me-2" 
                       <?= ($dist['consider_max_conversations'] ?? true) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Considerar limite máximo de conversas</span>
            </label>
        </div>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="assign_to_ai_agent" class="form-check-input me-2" 
                       <?= ($dist['assign_to_ai_agent'] ?? false) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Permitir atribuição a agentes de IA</span>
            </label>
            <div class="form-text">Se habilitado, conversas podem ser atribuídas a agentes de IA quando não houver agentes humanos disponíveis</div>
        </div>
    </div>
    <!--end::Distribuição-->
    
    <div class="separator separator-dashed my-10"></div>
    
    <!--begin::Distribuição por Porcentagem-->
    <div class="mb-10">
        <h4 class="fw-bold mb-4">Distribuição por Porcentagem</h4>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="percentage_distribution_enabled" class="form-check-input me-2" 
                       id="percentage_distribution_enabled"
                       <?= ($pctDist['enabled'] ?? false) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Habilitar distribuição por porcentagem</span>
            </label>
            <div class="form-text">Permite definir porcentagens específicas de distribuição por agente ou setor</div>
        </div>
        <div id="percentage_distribution_rules_container" style="display: <?= ($pctDist['enabled'] ?? false) ? 'block' : 'none' ?>;">
            <div class="fv-row mb-7">
                <label class="fw-semibold fs-6 mb-2">Regras de Distribuição</label>
                <div id="percentage_rules_list" class="mb-3">
                    <?php 
                    $rules = $pctDist['rules'] ?? [];
                    foreach ($rules as $index => $rule): 
                    ?>
                    <div class="d-flex gap-2 mb-2 percentage-rule-item" data-index="<?= $index ?>">
                        <select name="percentage_rule_type[]" class="form-select form-select-solid" style="width: 150px;">
                            <option value="agent" <?= ($rule['agent_id'] ?? null) ? 'selected' : '' ?>>Agente</option>
                            <option value="department" <?= ($rule['department_id'] ?? null) ? 'selected' : '' ?>>Setor</option>
                        </select>
                        <select name="percentage_rule_id[]" class="form-select form-select-solid percentage-rule-select" style="flex: 1;">
                            <?php if (isset($rule['agent_id'])): ?>
                                <?php foreach ($users ?? [] as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= $rule['agent_id'] == $user['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php elseif (isset($rule['department_id'])): ?>
                                <?php foreach ($departments ?? [] as $dept): ?>
                                    <option value="<?= $dept['id'] ?>" <?= $rule['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <input type="number" name="percentage_rule_value[]" class="form-control form-control-solid" 
                               value="<?= $rule['percentage'] ?? 0 ?>" min="0" max="100" 
                               placeholder="%" style="width: 100px;" />
                        <button type="button" class="btn btn-sm btn-light-danger remove-percentage-rule">
                            <i class="ki-duotone ki-trash fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-sm btn-light-primary" id="add_percentage_rule">
                    <i class="ki-duotone ki-plus fs-5 me-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Adicionar Regra
                </button>
                <input type="hidden" name="percentage_distribution_rules" id="percentage_distribution_rules" value="" />
            </div>
        </div>
    </div>
    <!--end::Distribuição por Porcentagem-->
    
    <div class="separator separator-dashed my-10"></div>
    
    <!--begin::Reatribuição-->
    <div class="mb-10">
        <h4 class="fw-bold mb-4">Reatribuição Automática</h4>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="enable_auto_reassignment" class="form-check-input me-2" 
                       <?= ($reassign['enable_auto_reassignment'] ?? true) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Habilitar reatribuição automática</span>
            </label>
        </div>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="reassign_on_sla_breach" class="form-check-input me-2" 
                       <?= ($reassign['reassign_on_sla_breach'] ?? true) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Reatribuir quando SLA for excedido</span>
            </label>
        </div>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="reassign_on_agent_offline" class="form-check-input me-2" 
                       <?= ($reassign['reassign_on_agent_offline'] ?? true) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Reatribuir quando agente ficar offline</span>
            </label>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Reatribuir por Inatividade (minutos)</label>
                    <input type="number" name="reassign_on_inactivity_minutes" class="form-control form-control-solid" 
                           value="<?= $reassign['reassign_on_inactivity_minutes'] ?? 60 ?>" min="1" />
                    <div class="form-text">Tempo sem resposta do agente para reatribuir</div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Máximo de Reatribuições</label>
                    <input type="number" name="max_reassignments" class="form-control form-control-solid" 
                           value="<?= $reassign['max_reassignments'] ?? 3 ?>" min="1" />
                    <div class="form-text">Número máximo de reatribuições por conversa</div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Reatribuição-->
    
    <div class="separator separator-dashed my-10"></div>
    
    <!--begin::Análise de Sentimento-->
    <div class="mb-10">
        <h4 class="fw-bold mb-4">Análise de Sentimento (OpenAI)</h4>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="sentiment_analysis_enabled" class="form-check-input me-2" 
                       id="sentiment_analysis_enabled"
                       <?= ($sentiment['enabled'] ?? false) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Habilitar análise de sentimento</span>
            </label>
            <div class="form-text">Analisa automaticamente o sentimento das conversas usando OpenAI GPT</div>
        </div>
        
        <div id="sentiment_analysis_settings" style="display: <?= ($sentiment['enabled'] ?? false) ? 'block' : 'none' ?>;">
            <div class="row">
                <div class="col-lg-6">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Modelo OpenAI</label>
                        <select name="sentiment_analysis_model" class="form-select form-select-solid">
                            <option value="gpt-3.5-turbo" <?= ($sentiment['model'] ?? 'gpt-3.5-turbo') === 'gpt-3.5-turbo' ? 'selected' : '' ?>>GPT-3.5 Turbo (Recomendado - Mais barato)</option>
                            <option value="gpt-4" <?= ($sentiment['model'] ?? '') === 'gpt-4' ? 'selected' : '' ?>>GPT-4 (Mais preciso - Mais caro)</option>
                            <option value="gpt-4-turbo" <?= ($sentiment['model'] ?? '') === 'gpt-4-turbo' ? 'selected' : '' ?>>GPT-4 Turbo</option>
                        </select>
                        <div class="form-text">Modelo da OpenAI a usar para análise</div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Temperature</label>
                        <input type="number" name="sentiment_analysis_temperature" class="form-control form-control-solid" 
                               value="<?= $sentiment['temperature'] ?? 0.3 ?>" min="0" max="2" step="0.1" />
                        <div class="form-text">Quanto menor, mais determinístico (padrão: 0.3)</div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-6">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Intervalo de Verificação (horas)</label>
                        <input type="number" name="sentiment_check_interval_hours" class="form-control form-control-solid" 
                               value="<?= $sentiment['check_interval_hours'] ?? 5 ?>" min="1" />
                        <div class="form-text">A cada quantas horas verificar conversas abertas</div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Idade Máxima da Conversa (dias)</label>
                        <input type="number" name="sentiment_max_conversation_age_days" class="form-control form-control-solid" 
                               value="<?= $sentiment['max_conversation_age_days'] ?? 30 ?>" min="1" />
                        <div class="form-text">Não analisar conversas abertas há mais de X dias</div>
                    </div>
                </div>
            </div>
            
            <div class="fv-row mb-7">
                <label class="d-flex align-items-center">
                    <input type="checkbox" name="sentiment_analyze_on_new_message" class="form-check-input me-2" 
                           <?= ($sentiment['analyze_on_new_message'] ?? true) ? 'checked' : '' ?> />
                    <span class="fw-semibold fs-6">Analisar automaticamente ao receber nova mensagem</span>
                </label>
            </div>
            
            <div class="row">
                <div class="col-lg-4">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Mín. Mensagens para Analisar</label>
                        <input type="number" name="sentiment_min_messages_to_analyze" class="form-control form-control-solid" 
                               value="<?= $sentiment['min_messages_to_analyze'] ?? 3 ?>" min="1" />
                        <div class="form-text">Mínimo de mensagens do contato para fazer análise</div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Analisar a cada X Mensagens</label>
                        <input type="number" name="sentiment_analyze_on_message_count" class="form-control form-control-solid" 
                               value="<?= $sentiment['analyze_on_message_count'] ?? 5 ?>" min="1" />
                        <div class="form-text">Analisar quando contador de mensagens for múltiplo deste número</div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Analisar Últimas X Mensagens</label>
                        <input type="number" name="sentiment_analyze_last_messages" class="form-control form-control-solid" 
                               value="<?= $sentiment['analyze_last_messages'] ?? '' ?>" min="1" 
                               placeholder="Deixe vazio para toda conversa" />
                        <div class="form-text">Analisar apenas últimas X mensagens (vazio = toda conversa)</div>
                    </div>
                </div>
            </div>
            
            <div class="fv-row mb-7">
                <label class="d-flex align-items-center">
                    <input type="checkbox" name="sentiment_include_emotions" class="form-check-input me-2" 
                           <?= ($sentiment['include_emotions'] ?? true) ? 'checked' : '' ?> />
                    <span class="fw-semibold fs-6">Incluir análise de emoções específicas</span>
                </label>
            </div>
            
            <div class="fv-row mb-7">
                <label class="d-flex align-items-center">
                    <input type="checkbox" name="sentiment_include_urgency" class="form-check-input me-2" 
                           <?= ($sentiment['include_urgency'] ?? true) ? 'checked' : '' ?> />
                    <span class="fw-semibold fs-6">Incluir nível de urgência</span>
                </label>
            </div>
            
            <div class="fv-row mb-7">
                <label class="d-flex align-items-center">
                    <input type="checkbox" name="sentiment_auto_tag_negative" class="form-check-input me-2" 
                           id="sentiment_auto_tag_negative"
                           <?= ($sentiment['auto_tag_negative'] ?? false) ? 'checked' : '' ?> />
                    <span class="fw-semibold fs-6">Adicionar tag automaticamente quando sentimento negativo</span>
                </label>
            </div>
            
            <div id="sentiment_tag_select_container" style="display: <?= ($sentiment['auto_tag_negative'] ?? false) ? 'block' : 'none' ?>;">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Tag para Sentimento Negativo</label>
                    <select name="sentiment_negative_tag_id" class="form-select form-select-solid">
                        <option value="">Selecione uma tag...</option>
                        <?php foreach ($tags ?? [] as $tag): ?>
                            <option value="<?= $tag['id'] ?>" <?= ($sentiment['negative_tag_id'] ?? null) == $tag['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tag['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Tag que será adicionada automaticamente quando sentimento negativo for detectado</div>
                </div>
            </div>
            
            <div class="fv-row mb-7">
                <label class="fw-semibold fs-6 mb-2">Limite de Custo Diário (USD)</label>
                <input type="number" name="sentiment_cost_limit_per_day" class="form-control form-control-solid" 
                       value="<?= $sentiment['cost_limit_per_day'] ?? 5.00 ?>" min="0" step="0.01" />
                <div class="form-text">Limite máximo de custo por dia (0 = ilimitado)</div>
            </div>
        </div>
    </div>
    <!--end::Análise de Sentimento-->
    
    <div class="separator separator-dashed my-10"></div>
    
    <!--begin::Transcrição de Áudio-->
    <div class="mb-10">
        <h4 class="fw-bold mb-4">Transcrição de Áudio (OpenAI Whisper)</h4>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="audio_transcription_enabled" class="form-check-input me-2" 
                       id="audio_transcription_enabled"
                       <?= ($transcription['enabled'] ?? false) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Habilitar transcrição automática de áudio</span>
            </label>
            <div class="form-text">Transcreve automaticamente mensagens de áudio recebidas usando OpenAI Whisper</div>
        </div>
        
        <div id="audio_transcription_settings" style="display: <?= ($transcription['enabled'] ?? false) ? 'block' : 'none' ?>;">
            <div class="fv-row mb-7">
                <label class="d-flex align-items-center">
                    <input type="checkbox" name="audio_transcription_auto_transcribe" class="form-check-input me-2" 
                           <?= ($transcription['auto_transcribe'] ?? true) ? 'checked' : '' ?> />
                    <span class="fw-semibold fs-6">Transcrever automaticamente quando áudio chegar</span>
                </label>
            </div>
            <div class="fv-row mb-7">
                <label class="d-flex align-items-center">
                    <input type="checkbox" name="audio_transcription_only_for_ai_agents" class="form-check-input me-2" 
                           <?= ($transcription['only_for_ai_agents'] ?? true) ? 'checked' : '' ?> />
                    <span class="fw-semibold fs-6">Só transcrever se conversa tem agente de IA atribuído</span>
                </label>
            </div>
            <div class="row">
                <div class="col-lg-6">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Idioma</label>
                        <select name="audio_transcription_language" class="form-select form-select-solid">
                            <option value="pt" <?= ($transcription['language'] ?? 'pt') === 'pt' ? 'selected' : '' ?>>Português</option>
                            <option value="en" <?= ($transcription['language'] ?? '') === 'en' ? 'selected' : '' ?>>Inglês</option>
                            <option value="es" <?= ($transcription['language'] ?? '') === 'es' ? 'selected' : '' ?>>Espanhol</option>
                        </select>
                        <div class="form-text">Idioma do áudio para melhor precisão</div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Limite de Tamanho (MB)</label>
                        <input type="number" name="audio_transcription_max_file_size_mb" class="form-control form-control-solid" 
                               value="<?= $transcription['max_file_size_mb'] ?? 25 ?>" min="1" max="25" />
                        <div class="form-text">Tamanho máximo do arquivo de áudio (limite da OpenAI: 25MB)</div>
                    </div>
                </div>
            </div>
            <div class="fv-row mb-7">
                <label class="d-flex align-items-center">
                    <input type="checkbox" name="audio_transcription_update_message_content" class="form-check-input me-2" 
                           <?= ($transcription['update_message_content'] ?? true) ? 'checked' : '' ?> />
                    <span class="fw-semibold fs-6">Atualizar conteúdo da mensagem com texto transcrito</span>
                </label>
                <div class="form-text">Se desmarcado, texto transcrito será salvo apenas em metadata</div>
            </div>
            
            <!-- ✅ NOVO: Exibir transcrição no chat -->
            <div class="fv-row mb-7">
                <label class="d-flex align-items-center">
                    <input type="checkbox" name="audio_transcription_show_transcription_in_chat" class="form-check-input me-2" 
                           <?= ($transcription['show_transcription_in_chat'] ?? true) ? 'checked' : '' ?> />
                    <span class="fw-semibold fs-6">📝 Exibir transcrição abaixo dos áudios no chat</span>
                </label>
                <div class="form-text">Recomendado! Melhora UX mostrando o texto abaixo de todos os áudios (recebidos e enviados)</div>
            </div>
            
            <div class="fv-row mb-7">
                <label class="fw-semibold fs-6 mb-2">Limite de Custo Diário (USD)</label>
                <input type="number" name="audio_transcription_cost_limit_per_day" class="form-control form-control-solid" 
                       value="<?= $transcription['cost_limit_per_day'] ?? 10.00 ?>" min="0" step="0.01" />
                <div class="form-text">Limite máximo de custo por dia ($0.006 por minuto de áudio)</div>
            </div>
        </div>
    </div>
    <!--end::Transcrição de Áudio-->
    
    <div class="separator separator-dashed my-10"></div>
    
    <!--begin::Text-to-Speech (Geração de Áudio)-->
    <div class="mb-10">
        <h4 class="fw-bold mb-4">Text-to-Speech (Geração de Áudio com IA)</h4>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="text_to_speech_enabled" class="form-check-input me-2" 
                       id="text_to_speech_enabled"
                       <?= ($tts['enabled'] ?? false) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Habilitar geração automática de áudio</span>
            </label>
            <div class="form-text">Gera áudio automaticamente para respostas da IA usando TTS</div>
        </div>
        
        <div id="text_to_speech_settings" style="display: <?= ($tts['enabled'] ?? false) ? 'block' : 'none' ?>;">
            <div class="fv-row mb-7">
                <label class="fw-semibold fs-6 mb-2">Provider</label>
                <select name="text_to_speech_provider" class="form-select form-select-solid" id="tts_provider_select">
                    <option value="openai" <?= ($tts['provider'] ?? 'openai') === 'openai' ? 'selected' : '' ?>>OpenAI TTS (Recomendado - Mais barato)</option>
                    <option value="elevenlabs" <?= ($tts['provider'] ?? '') === 'elevenlabs' ? 'selected' : '' ?>>ElevenLabs (Mais vozes - Mais caro)</option>
                </select>
                <div class="form-text">Escolha o provedor de TTS. OpenAI usa a mesma API key, ElevenLabs requer configuração separada.</div>
            </div>
            
            <div class="fv-row mb-7">
                <label class="d-flex align-items-center">
                    <input type="checkbox" name="text_to_speech_auto_generate_audio" class="form-check-input me-2" 
                           <?= ($tts['auto_generate_audio'] ?? false) ? 'checked' : '' ?> />
                    <span class="fw-semibold fs-6">Gerar áudio automaticamente para respostas da IA</span>
                </label>
            </div>
            <div class="fv-row mb-7">
                <label class="fw-semibold fs-6 mb-2">Modo de Envio</label>
                <select name="text_to_speech_send_mode" class="form-select form-select-solid" id="tts_send_mode_select">
                    <option value="intelligent" <?= ($tts['send_mode'] ?? 'intelligent') === 'intelligent' ? 'selected' : '' ?>>🤖 Inteligente (Recomendado)</option>
                    <option value="audio_only" <?= ($tts['send_mode'] ?? '') === 'audio_only' ? 'selected' : '' ?>>🎤 Somente Áudio</option>
                    <option value="text_only" <?= ($tts['send_mode'] ?? '') === 'text_only' ? 'selected' : '' ?>>📝 Somente Texto</option>
                    <option value="both" <?= ($tts['send_mode'] ?? '') === 'both' ? 'selected' : '' ?>>🎤📝 Áudio + Texto como Legenda</option>
                </select>
                <div class="form-text">
                    <strong>⚠️ IMPORTANTE:</strong> O sistema sempre envia UMA ÚNICA mensagem. Não envia duas mensagens separadas.<br><br>
                    <strong>🤖 Inteligente:</strong> Decide automaticamente baseado em regras configuráveis (tamanho, URLs, código, etc).<br>
                    <strong>🎤 Somente Áudio:</strong> Envia apenas o áudio (sem texto). Cliente ouve a mensagem.<br>
                    <strong>📝 Somente Texto:</strong> Envia apenas o texto (sem áudio). Cliente lê a mensagem.<br>
                    <strong>🎤📝 Áudio + Texto:</strong> Envia uma mensagem com áudio e texto aparece como legenda/caption no WhatsApp.
                </div>
            </div>
            
            <!-- Configurações do Modo Inteligente -->
            <div id="tts_intelligent_settings" style="display: <?= ($tts['send_mode'] ?? 'intelligent') === 'intelligent' ? 'block' : 'none' ?>;">
                <div class="separator separator-dashed my-5"></div>
                <h5 class="fw-bold mb-4">⚙️ Configurações do Modo Inteligente</h5>
                
                <!-- ✅ NOVO: Primeira mensagem sempre texto -->
                <div class="fv-row mb-7">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" name="tts_intelligent_first_message_always_text" class="form-check-input me-2" 
                               <?= ($tts['intelligent_rules']['first_message_always_text'] ?? true) ? 'checked' : '' ?> />
                        <span class="fw-semibold fs-6">📝 Primeira mensagem sempre em texto</span>
                    </label>
                    <div class="form-text ms-6">Evita enviar áudio logo no primeiro contato. Recomendado!</div>
                </div>
                
                <div class="separator separator-dashed my-5"></div>
                
                <div class="fv-row mb-7">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" name="tts_intelligent_use_text_length" class="form-check-input me-2" 
                               <?= ($tts['intelligent_rules']['use_text_length'] ?? true) ? 'checked' : '' ?> />
                        <span class="fw-semibold fs-6">Considerar tamanho do texto</span>
                    </label>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Máx. caracteres para áudio</label>
                            <input type="number" name="tts_intelligent_max_chars_for_audio" class="form-control form-control-solid" 
                                   value="<?= $tts['intelligent_rules']['max_chars_for_audio'] ?? 500 ?>" min="50" max="2000" />
                            <div class="form-text">Textos acima disso preferem texto</div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Mín. caracteres para forçar texto</label>
                            <input type="number" name="tts_intelligent_min_chars_for_text" class="form-control form-control-solid" 
                                   value="<?= $tts['intelligent_rules']['min_chars_for_text'] ?? 1000 ?>" min="200" max="5000" />
                            <div class="form-text">Textos acima disso sempre usam texto</div>
                        </div>
                    </div>
                </div>
                
                <div class="fv-row mb-7">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" name="tts_intelligent_force_text_if_urls" class="form-check-input me-2" 
                               <?= ($tts['intelligent_rules']['force_text_if_urls'] ?? true) ? 'checked' : '' ?> />
                        <span class="fw-semibold fs-6">Forçar texto se contém URLs</span>
                    </label>
                </div>
                
                <div class="fv-row mb-7">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" name="tts_intelligent_force_text_if_code" class="form-check-input me-2" 
                               <?= ($tts['intelligent_rules']['force_text_if_code'] ?? true) ? 'checked' : '' ?> />
                        <span class="fw-semibold fs-6">Forçar texto se contém código/formatação</span>
                    </label>
                </div>
                
                <div class="fv-row mb-7">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" name="tts_intelligent_use_emojis" class="form-check-input me-2" 
                               <?= ($tts['intelligent_rules']['use_emojis'] ?? true) ? 'checked' : '' ?> />
                        <span class="fw-semibold fs-6">Considerar quantidade de emojis</span>
                    </label>
                </div>
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Máx. emojis para áudio</label>
                    <input type="number" name="tts_intelligent_max_emojis_for_audio" class="form-control form-control-solid" 
                           value="<?= $tts['intelligent_rules']['max_emojis_for_audio'] ?? 3 ?>" min="0" max="20" />
                    <div class="form-text">Mensagens com mais emojis preferem texto</div>
                </div>
                
                <div class="fv-row mb-7">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" name="tts_intelligent_use_complexity" class="form-check-input me-2" 
                               <?= ($tts['intelligent_rules']['use_complexity'] ?? true) ? 'checked' : '' ?> />
                        <span class="fw-semibold fs-6">Forçar texto se contém palavras-chave técnicas</span>
                    </label>
                </div>
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Palavras-chave técnicas (separadas por vírgula)</label>
                    <input type="text" name="tts_intelligent_complexity_keywords" class="form-control form-control-solid" 
                           value="<?= htmlspecialchars(implode(', ', $tts['intelligent_rules']['complexity_keywords'] ?? ['instrução', 'passo a passo', 'tutorial', 'configuração', 'instalar', 'configurar', 'ajustar'])) ?>" 
                           placeholder="instrução, passo a passo, tutorial" />
                    <div class="form-text">Mensagens com essas palavras sempre usam texto</div>
                </div>
                
                <div class="fv-row mb-7">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" name="tts_intelligent_prefer_audio_if_client_sent_audio" class="form-check-input me-2" 
                               <?= ($tts['intelligent_rules']['prefer_audio_if_client_sent_audio'] ?? true) ? 'checked' : '' ?> />
                        <span class="fw-semibold fs-6">Preferir áudio se cliente enviou áudio recentemente</span>
                    </label>
                </div>
                
                <!-- ✅ NOVO: Prompt customizável -->
                <div class="separator separator-dashed my-5"></div>
                
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2 d-flex align-items-center">
                        💬 Comportamento Customizado (Opcional)
                        <span class="badge badge-light-warning ms-2">Em Desenvolvimento</span>
                    </label>
                    <textarea name="tts_intelligent_custom_behavior_prompt" class="form-control form-control-solid" rows="3" placeholder="Ex: Enviar áudio apenas quando a resposta for casual e amigável. Respostas técnicas ou com instruções devem ser sempre em texto."><?= htmlspecialchars($tts['intelligent_rules']['custom_behavior_prompt'] ?? '') ?></textarea>
                    <div class="form-text">
                        <strong>Futuro:</strong> Descreva regras adicionais em linguagem natural para auxiliar o sistema inteligente.
                        Por ora, use as opções específicas acima para melhor controle.
                    </div>
                </div>
                
                <div class="separator separator-dashed my-5"></div>
                
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Modo padrão (quando não há regras aplicáveis)</label>
                    <select name="tts_intelligent_default_mode" class="form-select form-select-solid">
                        <option value="audio_only" <?= ($tts['intelligent_rules']['default_mode'] ?? 'audio_only') === 'audio_only' ? 'selected' : '' ?>>Somente Áudio</option>
                        <option value="text_only" <?= ($tts['intelligent_rules']['default_mode'] ?? '') === 'text_only' ? 'selected' : '' ?>>Somente Texto</option>
                        <option value="both" <?= ($tts['intelligent_rules']['default_mode'] ?? '') === 'both' ? 'selected' : '' ?>>Áudio + Texto</option>
                    </select>
                </div>
            </div>
            <div class="fv-row mb-7">
                <label class="d-flex align-items-center">
                    <input type="checkbox" name="text_to_speech_only_for_ai_agents" class="form-check-input me-2" 
                           <?= ($tts['only_for_ai_agents'] ?? true) ? 'checked' : '' ?> />
                    <span class="fw-semibold fs-6">Só gerar áudio se for resposta de agente de IA</span>
                </label>
            </div>
            
            <!-- Configurações OpenAI TTS -->
            <div id="tts_openai_settings" style="display: <?= ($tts['provider'] ?? 'openai') === 'openai' ? 'block' : 'none' ?>;">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Modelo</label>
                            <select name="text_to_speech_model_openai" class="form-select form-select-solid">
                                <option value="tts-1" <?= ($tts['model'] ?? 'tts-1') === 'tts-1' ? 'selected' : '' ?>>TTS-1 (Rápido)</option>
                                <option value="tts-1-hd" <?= ($tts['model'] ?? '') === 'tts-1-hd' ? 'selected' : '' ?>>TTS-1-HD (Alta Qualidade)</option>
                            </select>
                            <div class="form-text">TTS-1 é mais rápido, TTS-1-HD tem melhor qualidade</div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Voz</label>
                            <select name="text_to_speech_voice_id_openai" class="form-select form-select-solid">
                                <option value="alloy" <?= ($tts['voice_id'] ?? 'alloy') === 'alloy' ? 'selected' : '' ?>>Alloy (Neutra)</option>
                                <option value="echo" <?= ($tts['voice_id'] ?? '') === 'echo' ? 'selected' : '' ?>>Echo (Masculina)</option>
                                <option value="fable" <?= ($tts['voice_id'] ?? '') === 'fable' ? 'selected' : '' ?>>Fable (Neutra Expressiva)</option>
                                <option value="onyx" <?= ($tts['voice_id'] ?? '') === 'onyx' ? 'selected' : '' ?>>Onyx (Masculina Profunda)</option>
                                <option value="nova" <?= ($tts['voice_id'] ?? '') === 'nova' ? 'selected' : '' ?>>Nova (Feminina Suave)</option>
                                <option value="shimmer" <?= ($tts['voice_id'] ?? '') === 'shimmer' ? 'selected' : '' ?>>Shimmer (Feminina Brilhante)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Configurações ElevenLabs -->
            <div id="tts_elevenlabs_settings" style="display: <?= ($tts['provider'] ?? '') === 'elevenlabs' ? 'block' : 'none' ?>;">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Modelo</label>
                            <select name="text_to_speech_model_elevenlabs" class="form-select form-select-solid" data-provider="elevenlabs">
                                <option value="eleven_multilingual_v2" <?= ($tts['model'] ?? 'eleven_multilingual_v2') === 'eleven_multilingual_v2' ? 'selected' : '' ?>>Multilingual v2 (Recomendado)</option>
                                <option value="eleven_turbo_v2" <?= ($tts['model'] ?? '') === 'eleven_turbo_v2' ? 'selected' : '' ?>>Turbo v2 (Mais Rápido)</option>
                                <option value="eleven_turbo_v2_5" <?= ($tts['model'] ?? '') === 'eleven_turbo_v2_5' ? 'selected' : '' ?>>Turbo v2.5 (Mais Rápido + Qualidade)</option>
                                <option value="eleven_monolingual_v1" <?= ($tts['model'] ?? '') === 'eleven_monolingual_v1' ? 'selected' : '' ?>>Monolingual v1 (Inglês apenas)</option>
                            </select>
                            <div class="form-text">
                                <strong>Multilingual v2:</strong> Melhor para português e outros idiomas<br>
                                <strong>Turbo v2/v2.5:</strong> Mais rápido e econômico, boa qualidade
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Voice ID</label>
                            <div class="input-group">
                                <input type="text" name="text_to_speech_voice_id_elevenlabs" class="form-control form-control-solid" 
                                       id="elevenlabs_voice_id_input"
                                       value="<?= htmlspecialchars($tts['voice_id'] ?? '21m00Tcm4TlvDq8ikWAM') ?>" 
                                       placeholder="21m00Tcm4TlvDq8ikWAM" 
                                       data-provider="elevenlabs" />
                                <button type="button" class="btn btn-light-primary" id="load_elevenlabs_voices">
                                    <i class="ki-duotone ki-arrows-circle fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Buscar Vozes
                                </button>
                            </div>
                            <div class="form-text">ID da voz do ElevenLabs. Clique em "Buscar Vozes" para ver as disponíveis.</div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal/Container para exibir vozes disponíveis -->
                <div id="elevenlabs_voices_container" class="mb-7" style="display: none;">
                    <div class="card bg-light">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4">🎤 Vozes Disponíveis:</h5>
                            <div id="elevenlabs_voices_list" class="row g-3">
                                <!-- Será preenchido dinamicamente via JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-4">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Estabilidade</label>
                            <input type="number" name="text_to_speech_stability" class="form-control form-control-solid" 
                                   value="<?= $tts['stability'] ?? 0.5 ?>" min="0" max="1" step="0.01" 
                                   lang="en"
                                   data-provider="elevenlabs" 
                                   placeholder="0.50" />
                            <div class="form-text">⚠️ Use ponto (.), não vírgula. Ex: <strong>0.50</strong> (0.0 = variável, 1.0 = estável)</div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Similaridade</label>
                            <input type="number" name="text_to_speech_similarity_boost" class="form-control form-control-solid" 
                                   value="<?= $tts['similarity_boost'] ?? 0.75 ?>" min="0" max="1" step="0.01" 
                                   lang="en"
                                   data-provider="elevenlabs" 
                                   placeholder="0.75" />
                            <div class="form-text">⚠️ Use ponto (.), não vírgula. Ex: <strong>0.75</strong> (quão similar à voz original)</div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Velocidade</label>
                            <input type="number" name="text_to_speech_speed" class="form-control form-control-solid" 
                                   value="<?= $tts['speed'] ?? 1.0 ?>" min="0.25" max="4.0" step="0.01" 
                                   lang="en"
                                   data-provider="elevenlabs" 
                                   placeholder="1.00" />
                            <div class="form-text">⚠️ Use ponto (.), não vírgula. Ex: <strong>1.00</strong> (0.25 a 4.0)</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Configurações Comuns -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Idioma</label>
                        <select name="text_to_speech_language" class="form-select form-select-solid">
                            <option value="pt" <?= ($tts['language'] ?? 'pt') === 'pt' ? 'selected' : '' ?>>Português</option>
                            <option value="en" <?= ($tts['language'] ?? '') === 'en' ? 'selected' : '' ?>>Inglês</option>
                            <option value="es" <?= ($tts['language'] ?? '') === 'es' ? 'selected' : '' ?>>Espanhol</option>
                        </select>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Formato de Saída</label>
                        <select name="text_to_speech_output_format" class="form-select form-select-solid">
                            <option value="mp3" <?= ($tts['output_format'] ?? 'mp3') === 'mp3' ? 'selected' : '' ?>>MP3</option>
                            <option value="opus" <?= ($tts['output_format'] ?? '') === 'opus' ? 'selected' : '' ?>>Opus (Recomendado para WhatsApp)</option>
                            <option value="ogg" <?= ($tts['output_format'] ?? '') === 'ogg' ? 'selected' : '' ?>>OGG</option>
                        </select>
                        <div class="form-text">Formato do arquivo de áudio gerado</div>
                    </div>
                </div>
            </div>
            <div class="fv-row mb-7">
                <label class="d-flex align-items-center">
                    <input type="checkbox" name="text_to_speech_convert_to_whatsapp_format" class="form-check-input me-2" 
                           <?= ($tts['convert_to_whatsapp_format'] ?? true) ? 'checked' : '' ?> />
                    <span class="fw-semibold fs-6">Converter automaticamente para formato compatível com WhatsApp</span>
                </label>
                <div class="form-text">Converte para OGG/Opus se necessário (requer FFmpeg instalado)</div>
            </div>
            <div class="fv-row mb-7">
                <label class="fw-semibold fs-6 mb-2">Limite de Custo Diário (USD)</label>
                <input type="number" name="text_to_speech_cost_limit_per_day" class="form-control form-control-solid" 
                       value="<?= $tts['cost_limit_per_day'] ?? 5.00 ?>" min="0" step="0.01" />
                <div class="form-text">OpenAI: $0.015/1k chars | ElevenLabs: $0.18/1k chars</div>
            </div>
        </div>
    </div>
    <!--end::Text-to-Speech-->
    
    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
            <span class="indicator-label">Salvar Configurações</span>
            <span class="indicator-progress">Aguarde...
            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
        </button>
    </div>
</form>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("kt_settings_conversations_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            submitConversationsForm(this);
        });
    }
    
    // Toggle distribuição por porcentagem
    const percentageEnabled = document.getElementById("percentage_distribution_enabled");
    const percentageContainer = document.getElementById("percentage_distribution_rules_container");
    if (percentageEnabled && percentageContainer) {
        percentageEnabled.addEventListener("change", function() {
            percentageContainer.style.display = this.checked ? "block" : "none";
        });
    }
    
    // Toggle análise de sentimento
    const sentimentEnabled = document.getElementById("sentiment_analysis_enabled");
    const sentimentContainer = document.getElementById("sentiment_analysis_settings");
    if (sentimentEnabled && sentimentContainer) {
        sentimentEnabled.addEventListener("change", function() {
            sentimentContainer.style.display = this.checked ? "block" : "none";
        });
    }
    
    // Toggle transcrição de áudio
    const transcriptionEnabled = document.getElementById("audio_transcription_enabled");
    const transcriptionContainer = document.getElementById("audio_transcription_settings");
    if (transcriptionEnabled && transcriptionContainer) {
        transcriptionEnabled.addEventListener("change", function() {
            transcriptionContainer.style.display = this.checked ? "block" : "none";
        });
    }
    
    // Toggle text-to-speech
    const ttsEnabled = document.getElementById("text_to_speech_enabled");
    const ttsContainer = document.getElementById("text_to_speech_settings");
    if (ttsEnabled && ttsContainer) {
        ttsEnabled.addEventListener("change", function() {
            ttsContainer.style.display = this.checked ? "block" : "none";
        });
    }
    
    // Toggle provider TTS (OpenAI/ElevenLabs)
    const ttsProviderSelect = document.getElementById("tts_provider_select");
    const openaiSettings = document.getElementById("tts_openai_settings");
    const elevenlabsSettings = document.getElementById("tts_elevenlabs_settings");
    if (ttsProviderSelect && openaiSettings && elevenlabsSettings) {
        function toggleProviderSettings(provider) {
            if (provider === "openai") {
                openaiSettings.style.display = "block";
                elevenlabsSettings.style.display = "none";
                // Desabilitar campos do ElevenLabs para evitar validação quando escondidos
                setTimeout(function() {
                    document.querySelectorAll('[data-provider="elevenlabs"]').forEach(function(input) {
                        input.disabled = true;
                        input.removeAttribute('required');
                    });
                }, 100);
            } else {
                openaiSettings.style.display = "none";
                elevenlabsSettings.style.display = "block";
                // Habilitar campos do ElevenLabs
                setTimeout(function() {
                    document.querySelectorAll('[data-provider="elevenlabs"]').forEach(function(input) {
                        input.disabled = false;
                    });
                }, 100);
            }
        }
        
        ttsProviderSelect.addEventListener("change", function() {
            toggleProviderSettings(this.value);
        });
        
        // Inicializar estado correto ao carregar página
        setTimeout(function() {
            toggleProviderSettings(ttsProviderSelect.value);
        }, 200);
    }
    
    // Toggle modo de envio (mostrar/esconder configurações inteligentes)
    const ttsSendModeSelect = document.getElementById("tts_send_mode_select");
    const intelligentSettings = document.getElementById("tts_intelligent_settings");
    if (ttsSendModeSelect && intelligentSettings) {
        ttsSendModeSelect.addEventListener("change", function() {
            intelligentSettings.style.display = (this.value === "intelligent") ? "block" : "none";
        });
    }
    
    // Toggle tag automática para sentimento negativo
    const autoTagNegative = document.getElementById("sentiment_auto_tag_negative");
    const tagSelectContainer = document.getElementById("sentiment_tag_select_container");
    if (autoTagNegative && tagSelectContainer) {
        autoTagNegative.addEventListener("change", function() {
            tagSelectContainer.style.display = this.checked ? "block" : "none";
        });
    }
    
    // Adicionar regra de porcentagem
    const addRuleBtn = document.getElementById("add_percentage_rule");
    const rulesList = document.getElementById("percentage_rules_list");
    if (addRuleBtn && rulesList) {
        addRuleBtn.addEventListener("click", function() {
            const index = rulesList.children.length;
            const ruleHtml = `
                <div class="d-flex gap-2 mb-2 percentage-rule-item" data-index="${index}">
                    <select name="percentage_rule_type[]" class="form-select form-select-solid percentage-rule-type" style="width: 150px;">
                        <option value="agent">Agente</option>
                        <option value="department">Setor</option>
                    </select>
                    <select name="percentage_rule_id[]" class="form-select form-select-solid percentage-rule-select" style="flex: 1;">
                        <option value="">Selecione...</option>
                    </select>
                    <input type="number" name="percentage_rule_value[]" class="form-control form-control-solid" 
                           value="0" min="0" max="100" placeholder="%" style="width: 100px;" />
                    <button type="button" class="btn btn-sm btn-light-danger remove-percentage-rule">
                        <i class="ki-duotone ki-trash fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                    </button>
                </div>
            `;
            rulesList.insertAdjacentHTML("beforeend", ruleHtml);
            updatePercentageRuleSelect(rulesList.lastElementChild);
        });
    }
    
    // Remover regra de porcentagem
    document.addEventListener("click", function(e) {
        if (e.target.closest(".remove-percentage-rule")) {
            e.target.closest(".percentage-rule-item").remove();
            updatePercentageRulesHidden();
        }
    });
    
    // Atualizar select quando tipo mudar
    document.addEventListener("change", function(e) {
        if (e.target.classList.contains("percentage-rule-type")) {
            updatePercentageRuleSelect(e.target.closest(".percentage-rule-item"));
        }
    });
    
    function updatePercentageRuleSelect(ruleItem) {
        const typeSelect = ruleItem.querySelector(".percentage-rule-type");
        const idSelect = ruleItem.querySelector(".percentage-rule-select");
        const type = typeSelect.value;
        
        idSelect.innerHTML = '<option value="">Selecione...</option>';
        
        if (type === "agent") {
            <?php foreach ($users ?? [] as $user): ?>
            idSelect.innerHTML += `<option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name'], ENT_QUOTES) ?></option>`;
            <?php endforeach; ?>
        } else if (type === "department") {
            <?php foreach ($departments ?? [] as $dept): ?>
            idSelect.innerHTML += `<option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name'], ENT_QUOTES) ?></option>`;
            <?php endforeach; ?>
        }
    }
    
    function updatePercentageRulesHidden() {
        const rules = [];
        document.querySelectorAll(".percentage-rule-item").forEach(function(item) {
            const type = item.querySelector(".percentage-rule-type").value;
            const id = item.querySelector(".percentage-rule-select").value;
            const percentage = item.querySelector("input[type=\"number\"]").value;
            
            if (id && percentage) {
                const rule = { percentage: parseInt(percentage) };
                if (type === "agent") {
                    rule.agent_id = parseInt(id);
                } else {
                    rule.department_id = parseInt(id);
                }
                rules.push(rule);
            }
        });
        
        document.getElementById("percentage_distribution_rules").value = JSON.stringify(rules);
    }
    
    // Atualizar hidden input quando valores mudarem
    document.addEventListener("input", function(e) {
        if (e.target.closest(".percentage-rule-item")) {
            updatePercentageRulesHidden();
        }
    });
    
    // Atualizar na inicialização
    updatePercentageRulesHidden();
    
    // ✅ NOVO: Carregar vozes disponíveis do ElevenLabs
    const loadVoicesBtn = document.getElementById("load_elevenlabs_voices");
    if (loadVoicesBtn) {
        loadVoicesBtn.addEventListener("click", function() {
            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Carregando...';
            
            fetch("<?= \App\Helpers\Url::to('/api/elevenlabs/voices') ?>", {
                method: "GET",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                
                if (data.success && data.voices && data.voices.length > 0) {
                    const container = document.getElementById("elevenlabs_voices_container");
                    const list = document.getElementById("elevenlabs_voices_list");
                    const input = document.getElementById("elevenlabs_voice_id_input");
                    
                    // Limpar lista
                    list.innerHTML = '';
                    
                    // Renderizar vozes
                    data.voices.forEach(function(voice) {
                        const genderIcon = voice.gender === 'male' ? '👨' : voice.gender === 'female' ? '👩' : '🎤';
                        const voiceHtml = `
                            <div class="col-lg-6">
                                <div class="card card-bordered card-flush h-100 voice-card" style="cursor: pointer;" data-voice-id="${voice.id}">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <h6 class="fw-bold mb-0">${genderIcon} ${voice.name}</h6>
                                            <span class="badge badge-light-primary">${voice.id.substring(0, 8)}...</span>
                                        </div>
                                        <p class="text-muted fs-7 mb-2">${voice.description || 'Sem descrição'}</p>
                                        ${voice.preview_url ? `<audio controls style="width: 100%; height: 32px;" class="mt-2" onclick="event.stopPropagation();"><source src="${voice.preview_url}" type="audio/mpeg">Seu navegador não suporta áudio.</audio>` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                        list.insertAdjacentHTML('beforeend', voiceHtml);
                    });
                    
                    // Mostrar container
                    container.style.display = 'block';
                    
                    // Adicionar evento de clique para selecionar voz
                    list.querySelectorAll('.voice-card').forEach(function(card) {
                        card.addEventListener('click', function() {
                            const voiceId = this.dataset.voiceId;
                            input.value = voiceId;
                            
                            // Destacar card selecionado
                            list.querySelectorAll('.voice-card').forEach(c => c.classList.remove('border-primary'));
                            this.classList.add('border-primary');
                            
                            // Scroll suave para o input
                            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            input.focus();
                        });
                    });
                    
                    // Destacar voz atual se já estiver selecionada
                    const currentVoiceId = input.value;
                    if (currentVoiceId) {
                        const currentCard = list.querySelector(`[data-voice-id="${currentVoiceId}"]`);
                        if (currentCard) {
                            currentCard.classList.add('border-primary');
                        }
                    }
                } else {
                    alert(data.message || 'Nenhuma voz encontrada. Verifique se a API Key do ElevenLabs está configurada.');
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                alert('Erro ao carregar vozes: ' + error.message);
            });
        });
    }
    
    function submitConversationsForm(form) {
        // Atualizar regras de porcentagem antes de enviar
        updatePercentageRulesHidden();
        
        // Desabilitar campos escondidos antes de validar/enviar para evitar erro de validação
        const provider = document.getElementById("tts_provider_select")?.value || "openai";
        if (provider === "openai") {
            document.querySelectorAll('[data-provider="elevenlabs"]').forEach(function(input) {
                input.disabled = true;
                input.removeAttribute('required');
            });
        } else {
            document.querySelectorAll('[data-provider="openai"]').forEach(function(input) {
                input.disabled = true;
                input.removeAttribute('required');
            });
        }
        
        const submitBtn = form.querySelector("button[type=\"submit\"]");
        submitBtn.setAttribute("data-kt-indicator", "on");
        submitBtn.disabled = true;
        
        const formData = new FormData(form);
        
        fetch("<?= \App\Helpers\Url::to('/settings/conversations') ?>", {
            method: "POST",
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.removeAttribute("data-kt-indicator");
            submitBtn.disabled = false;
            
            // Reabilitar campos após envio
            document.querySelectorAll('[data-provider]').forEach(function(input) {
                input.disabled = false;
            });
            
            if (data.success) {
                alert(data.message || "Configurações salvas com sucesso!");
            } else {
                alert("Erro: " + (data.message || "Erro ao salvar configurações"));
            }
        })
        .catch(error => {
            submitBtn.removeAttribute("data-kt-indicator");
            submitBtn.disabled = false;
            
            // Reabilitar campos após erro
            document.querySelectorAll('[data-provider]').forEach(function(input) {
                input.disabled = false;
            });
            
            alert("Erro ao salvar configurações");
        });
    }
});
</script>
