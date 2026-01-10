<?php
$settings = $data['settings'] ?? [];
$coachingSettings = $settings['realtime_coaching'] ?? [];
?>

<div class="card mb-5">
    <div class="card-header">
        <h3 class="card-title">⚡ Coaching em Tempo Real (IA)</h3>
        <div class="card-toolbar">
            <span class="badge badge-light-primary">NOVO</span>
        </div>
    </div>
    <div class="card-body">
        <!-- Habilitar/Desabilitar -->
        <div class="form-check form-switch form-check-custom form-check-solid mb-5">
            <input class="form-check-input" type="checkbox" 
                   id="realtime_coaching_enabled" 
                   name="realtime_coaching[enabled]" 
                   value="1"
                   <?= ($coachingSettings['enabled'] ?? false) ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold" for="realtime_coaching_enabled">
                Habilitar Coaching em Tempo Real
            </label>
            <div class="form-text">
                Fornece dicas instantâneas aos vendedores durante conversas ativas (3-8s de latência)
            </div>
        </div>

        <div id="realtime-coaching-settings" style="display: <?= ($coachingSettings['enabled'] ?? false) ? 'block' : 'none' ?>;">
            
            <!-- Modelo e Temperatura -->
            <div class="row mb-5">
                <div class="col-md-6">
                    <label class="form-label required">Modelo de IA</label>
                    <select class="form-select" name="realtime_coaching[model]">
                        <option value="gpt-3.5-turbo" <?= ($coachingSettings['model'] ?? 'gpt-3.5-turbo') === 'gpt-3.5-turbo' ? 'selected' : '' ?>>
                            GPT-3.5 Turbo (Recomendado - Rápido e barato)
                        </option>
                        <option value="gpt-4o" <?= ($coachingSettings['model'] ?? '') === 'gpt-4o' ? 'selected' : '' ?>>
                            GPT-4o (Mais preciso, mais caro)
                        </option>
                        <option value="gpt-4-turbo" <?= ($coachingSettings['model'] ?? '') === 'gpt-4-turbo' ? 'selected' : '' ?>>
                            GPT-4 Turbo (Muito preciso, mais caro)
                        </option>
                    </select>
                    <div class="form-text">GPT-3.5 é ideal para tempo real (mais rápido)</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Temperatura</label>
                    <input type="number" class="form-control" 
                           name="realtime_coaching[temperature]" 
                           value="<?= $coachingSettings['temperature'] ?? 0.5 ?>" 
                           step="0.1" min="0" max="1">
                    <div class="form-text">0 = Conservador, 1 = Criativo (recomendado: 0.5)</div>
                </div>
            </div>

            <!-- Rate Limiting -->
            <div class="separator my-5"></div>
            <h4 class="mb-4">⚡ Controle de Performance</h4>
            
            <div class="row mb-5">
                <div class="col-md-6">
                    <label class="form-label">Máximo de análises por minuto</label>
                    <input type="number" class="form-control" 
                           name="realtime_coaching[max_analyses_per_minute]" 
                           value="<?= $coachingSettings['max_analyses_per_minute'] ?? 10 ?>" 
                           min="1" max="60">
                    <div class="form-text">Limite global (recomendado: 10-20)</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Intervalo mínimo entre análises (segundos)</label>
                    <input type="number" class="form-control" 
                           name="realtime_coaching[min_interval_between_analyses]" 
                           value="<?= $coachingSettings['min_interval_between_analyses'] ?? 10 ?>" 
                           min="1" max="300">
                    <div class="form-text">Por agente (recomendado: 10-15s)</div>
                </div>
            </div>

            <!-- Fila e Processamento -->
            <div class="row mb-5">
                <div class="col-md-4">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" 
                               id="use_queue" 
                               name="realtime_coaching[use_queue]" 
                               value="1"
                               <?= ($coachingSettings['use_queue'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="use_queue">
                            Usar Fila (Recomendado)
                        </label>
                    </div>
                    <div class="form-text">Processa em background (não bloqueia)</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Delay de processamento (seg)</label>
                    <input type="number" class="form-control" 
                           name="realtime_coaching[queue_processing_delay]" 
                           value="<?= $coachingSettings['queue_processing_delay'] ?? 3 ?>" 
                           min="1" max="30">
                    <div class="form-text">Debouncing (recomendado: 2-5s)</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tamanho máximo da fila</label>
                    <input type="number" class="form-control" 
                           name="realtime_coaching[max_queue_size]" 
                           value="<?= $coachingSettings['max_queue_size'] ?? 100 ?>" 
                           min="10" max="1000">
                    <div class="form-text">Recomendado: 50-200</div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="separator my-5"></div>
            <h4 class="mb-4">🎯 Filtros (Quando Analisar)</h4>
            
            <div class="row mb-5">
                <div class="col-md-4">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" 
                               id="analyze_only_client_messages" 
                               name="realtime_coaching[analyze_only_client_messages]" 
                               value="1"
                               <?= ($coachingSettings['analyze_only_client_messages'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="analyze_only_client_messages">
                            Apenas mensagens do cliente
                        </label>
                    </div>
                    <div class="form-text">Economiza ~50% (recomendado)</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tamanho mínimo da mensagem</label>
                    <input type="number" class="form-control" 
                           name="realtime_coaching[min_message_length]" 
                           value="<?= $coachingSettings['min_message_length'] ?? 10 ?>" 
                           min="1" max="100">
                    <div class="form-text">Ignora "ok", "sim" (recomendado: 10-20)</div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" 
                               id="skip_if_agent_typing" 
                               name="realtime_coaching[skip_if_agent_typing]" 
                               value="1"
                               <?= ($coachingSettings['skip_if_agent_typing'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="skip_if_agent_typing">
                            Pular se agente digitando
                        </label>
                    </div>
                    <div class="form-text">Evita interrupção</div>
                </div>
            </div>

            <!-- Cache -->
            <div class="separator my-5"></div>
            <h4 class="mb-4">💾 Cache (Otimização)</h4>
            
            <div class="row mb-5">
                <div class="col-md-4">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" 
                               id="use_cache" 
                               name="realtime_coaching[use_cache]" 
                               value="1"
                               <?= ($coachingSettings['use_cache'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="use_cache">
                            Usar Cache
                        </label>
                    </div>
                    <div class="form-text">Reutiliza análises similares (economiza 30-40%)</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Validade do cache (minutos)</label>
                    <input type="number" class="form-control" 
                           name="realtime_coaching[cache_ttl_minutes]" 
                           value="<?= $coachingSettings['cache_ttl_minutes'] ?? 60 ?>" 
                           min="1" max="1440">
                    <div class="form-text">Recomendado: 30-120 minutos</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Threshold de similaridade</label>
                    <input type="number" class="form-control" 
                           name="realtime_coaching[cache_similarity_threshold]" 
                           value="<?= $coachingSettings['cache_similarity_threshold'] ?? 0.85 ?>" 
                           step="0.05" min="0.5" max="1">
                    <div class="form-text">0.85 = 85% similar (recomendado: 0.80-0.90)</div>
                </div>
            </div>

            <!-- Limites de Custo -->
            <div class="separator my-5"></div>
            <h4 class="mb-4">💰 Limites de Custo</h4>
            
            <div class="row mb-5">
                <div class="col-md-6">
                    <label class="form-label">Limite por hora (USD)</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" 
                               name="realtime_coaching[cost_limit_per_hour]" 
                               value="<?= $coachingSettings['cost_limit_per_hour'] ?? 1.00 ?>" 
                               step="0.10" min="0.10" max="100">
                    </div>
                    <div class="form-text">Para análises se ultrapassar (recomendado: $0.50-2.00)</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Limite por dia (USD)</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" 
                               name="realtime_coaching[cost_limit_per_day]" 
                               value="<?= $coachingSettings['cost_limit_per_day'] ?? 10.00 ?>" 
                               step="1" min="1" max="1000">
                    </div>
                    <div class="form-text">Limite diário total (recomendado: $5-20)</div>
                </div>
            </div>

            <!-- Tipos de Dica -->
            <div class="separator my-5"></div>
            <h4 class="mb-4">🎯 Tipos de Situação a Detectar</h4>
            
            <div class="row mb-5">
                <?php
                $hintTypes = [
                    'objection' => ['label' => 'Objeções', 'icon' => 'shield-cross', 'desc' => 'Cliente levanta objeção (preço, prazo, dúvida)'],
                    'opportunity' => ['label' => 'Oportunidades', 'icon' => 'rocket', 'desc' => 'Cliente demonstra interesse ou faz pergunta positiva'],
                    'question' => ['label' => 'Perguntas Importantes', 'icon' => 'question-2', 'desc' => 'Pergunta técnica ou importante que precisa resposta'],
                    'negative_sentiment' => ['label' => 'Sentimento Negativo', 'icon' => 'emoji-sad', 'desc' => 'Cliente insatisfeito ou frustrado'],
                    'buying_signal' => ['label' => 'Sinais de Compra', 'icon' => 'dollar', 'desc' => 'Cliente demonstra sinais de estar pronto para comprar'],
                    'closing_opportunity' => ['label' => 'Momento de Fechar', 'icon' => 'check-circle', 'desc' => 'Momento ideal para tentar fechar a venda'],
                    'escalation_needed' => ['label' => 'Escalar Conversa', 'icon' => 'arrow-up', 'desc' => 'Situação que precisa ser escalada para supervisor'],
                ];
                
                foreach ($hintTypes as $key => $type):
                    $checked = ($coachingSettings['hint_types'][$key] ?? true) ? 'checked' : '';
                ?>
                <div class="col-md-6 mb-4">
                    <div class="form-check form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" 
                               id="hint_type_<?= $key ?>" 
                               name="realtime_coaching[hint_types][<?= $key ?>]" 
                               value="1"
                               <?= $checked ?>>
                        <label class="form-check-label" for="hint_type_<?= $key ?>">
                            <i class="ki-duotone ki-<?= $type['icon'] ?> fs-2 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <strong><?= $type['label'] ?></strong>
                            <div class="text-muted fs-7"><?= $type['desc'] ?></div>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Apresentação -->
            <div class="separator my-5"></div>
            <h4 class="mb-4">🎨 Apresentação</h4>
            
            <div class="row mb-5">
                <div class="col-md-4">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" 
                               id="auto_show_hint" 
                               name="realtime_coaching[auto_show_hint]" 
                               value="1"
                               <?= ($coachingSettings['auto_show_hint'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="auto_show_hint">
                            Mostrar automaticamente
                        </label>
                    </div>
                    <div class="form-text">Se desabilitado, apenas notifica</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Duração da exibição (segundos)</label>
                    <input type="number" class="form-control" 
                           name="realtime_coaching[hint_display_duration]" 
                           value="<?= $coachingSettings['hint_display_duration'] ?? 30 ?>" 
                           min="5" max="300">
                    <div class="form-text">Tempo antes de fechar automaticamente</div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" 
                               id="play_sound" 
                               name="realtime_coaching[play_sound]" 
                               value="1"
                               <?= ($coachingSettings['play_sound'] ?? false) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="play_sound">
                            Tocar som ao receber dica
                        </label>
                    </div>
                    <div class="form-text">Notificação sonora</div>
                </div>
            </div>

            <!-- Estimativa de Custo -->
            <div class="alert alert-info d-flex align-items-center">
                <i class="ki-duotone ki-information-5 fs-2x me-4">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div>
                    <h5 class="mb-1">💡 Estimativa de Custo</h5>
                    <p class="mb-0">
                        Com as configurações atuais e volume de <strong>50 msgs/segundo</strong>:
                        <br>• <strong>~3-5 análises/segundo</strong> (após filtros e rate limiting)
                        <br>• <strong>~$3-7/dia</strong> com GPT-3.5-turbo
                        <br>• <strong>Latência: 3-8 segundos</strong> (aceitável para coaching)
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>
