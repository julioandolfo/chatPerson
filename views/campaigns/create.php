<?php
$layout = 'layouts.metronic.app';
$title = 'Nova Campanha';
$pageTitle = 'Nova Campanha';
?>

<?php ob_start(); ?>
<div class="app-toolbar py-3 py-lg-6">
    <div class="app-container container-fluid d-flex flex-stack">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                Nova Campanha WhatsApp
            </h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="/dashboard" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                <li class="breadcrumb-item text-muted">
                    <a href="/campaigns" class="text-muted text-hover-primary">Campanhas</a>
                </li>
                <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                <li class="breadcrumb-item text-muted">Nova</li>
            </ul>
        </div>
    </div>
</div>
<div class="app-container container-fluid">
            
            <!-- Wizard -->
            <div class="card">
                <div class="card-body">
                    
                    <!-- Stepper -->
                    <div class="stepper stepper-pills stepper-column d-flex flex-column flex-xl-row flex-row-fluid gap-10" id="campaign_wizard">
                        
                        <!-- Aside -->
                        <div class="card d-flex justify-content-center justify-content-xl-start flex-row-auto w-100 w-xl-300px w-xxl-400px">
                            <div class="card-body px-6 px-lg-10 px-xxl-15 py-20">
                                <div class="stepper-nav">
                                    
                                    <div class="stepper-item current" data-kt-stepper-element="nav">
                                        <div class="stepper-wrapper">
                                            <div class="stepper-icon w-40px h-40px">
                                                <i class="stepper-check fas fa-check"></i>
                                                <span class="stepper-number">1</span>
                                            </div>
                                            <div class="stepper-label">
                                                <h3 class="stepper-title">Informações Básicas</h3>
                                                <div class="stepper-desc fw-semibold">Nome e configurações gerais</div>
                                            </div>
                                        </div>
                                        <div class="stepper-line h-40px"></div>
                                    </div>
                                    
                                    <div class="stepper-item" data-kt-stepper-element="nav">
                                        <div class="stepper-wrapper">
                                            <div class="stepper-icon w-40px h-40px">
                                                <i class="stepper-check fas fa-check"></i>
                                                <span class="stepper-number">2</span>
                                            </div>
                                            <div class="stepper-label">
                                                <h3 class="stepper-title">Público-Alvo</h3>
                                                <div class="stepper-desc fw-semibold">Selecione os contatos</div>
                                            </div>
                                        </div>
                                        <div class="stepper-line h-40px"></div>
                                    </div>
                                    
                                    <div class="stepper-item" data-kt-stepper-element="nav">
                                        <div class="stepper-wrapper">
                                            <div class="stepper-icon w-40px h-40px">
                                                <i class="stepper-check fas fa-check"></i>
                                                <span class="stepper-number">3</span>
                                            </div>
                                            <div class="stepper-label">
                                                <h3 class="stepper-title">Contas WhatsApp</h3>
                                                <div class="stepper-desc fw-semibold">Rotação entre contas</div>
                                            </div>
                                        </div>
                                        <div class="stepper-line h-40px"></div>
                                    </div>
                                    
                                    <div class="stepper-item" data-kt-stepper-element="nav">
                                        <div class="stepper-wrapper">
                                            <div class="stepper-icon w-40px h-40px">
                                                <i class="stepper-check fas fa-check"></i>
                                                <span class="stepper-number">4</span>
                                            </div>
                                            <div class="stepper-label">
                                                <h3 class="stepper-title">Mensagem</h3>
                                                <div class="stepper-desc fw-semibold">Conteúdo e variáveis</div>
                                            </div>
                                        </div>
                                        <div class="stepper-line h-40px"></div>
                                    </div>
                                    
                                    <div class="stepper-item" data-kt-stepper-element="nav">
                                        <div class="stepper-wrapper">
                                            <div class="stepper-icon w-40px h-40px">
                                                <i class="stepper-check fas fa-check"></i>
                                                <span class="stepper-number">5</span>
                                            </div>
                                            <div class="stepper-label">
                                                <h3 class="stepper-title">Agendamento</h3>
                                                <div class="stepper-desc fw-semibold">Horários e cadência</div>
                                            </div>
                                        </div>
                                        <div class="stepper-line h-40px"></div>
                                    </div>
                                    
                                    <div class="stepper-item" data-kt-stepper-element="nav">
                                        <div class="stepper-wrapper">
                                            <div class="stepper-icon w-40px h-40px">
                                                <i class="stepper-check fas fa-check"></i>
                                                <span class="stepper-number">6</span>
                                            </div>
                                            <div class="stepper-label">
                                                <h3 class="stepper-title">Revisão</h3>
                                                <div class="stepper-desc fw-semibold">Confirmar e criar</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="card d-flex flex-row-fluid flex-center">
                            <form class="card-body py-20 w-100 mw-xl-700px px-9" novalidate="novalidate" id="campaign_form">
                                
                                <!-- Step 1: Informações Básicas -->
                                <div class="current" data-kt-stepper-element="content">
                                    <div class="w-100">
                                        <div class="mb-10">
                                            <label class="d-flex align-items-center form-label required">
                                                Nome da Campanha
                                            </label>
                                            <input type="text" class="form-control form-control-lg" name="name" placeholder="Ex: Black Friday 2026" required />
                                        </div>
                                        
                                        <div class="mb-10">
                                            <label class="form-label">Descrição (opcional)</label>
                                            <textarea class="form-control form-control-lg" name="description" rows="3" placeholder="Descreva o objetivo desta campanha..."></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Step 2: Público-Alvo -->
                                <div data-kt-stepper-element="content">
                                    <div class="w-100">
                                        <div class="mb-10">
                                            <label class="form-label required">Selecione a Lista de Contatos</label>
                                            <select class="form-select form-select-lg" name="contact_list_id" required>
                                                <option value="">Selecione...</option>
                                                <?php foreach ($lists as $list): ?>
                                                <option value="<?php echo $list['id']; ?>">
                                                    <?php echo htmlspecialchars($list['name']); ?> (<?php echo $list['total_contacts']; ?> contatos)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">
                                                <a href="/contact-lists/create" target="_blank">Criar nova lista</a> se necessário
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Step 3: Contas WhatsApp -->
                                <div data-kt-stepper-element="content">
                                    <div class="w-100">
                                        <div class="mb-10">
                                            <label class="form-label required d-block">Selecione as Contas WhatsApp (múltiplas)</label>
                                            <div class="alert alert-info d-flex align-items-center mb-5">
                                                <i class="ki-duotone ki-information-5 fs-2 me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                                <div>Selecione 2 ou mais contas para ativar a <strong>rotação automática</strong> entre elas!</div>
                                            </div>
                                            
                                            <?php foreach ($whatsappAccounts as $account): ?>
                                            <div class="form-check form-check-custom form-check-solid mb-3">
                                                <input class="form-check-input" type="checkbox" name="integration_account_ids[]" 
                                                       value="<?php echo $account['id']; ?>" id="account_<?php echo $account['id']; ?>">
                                                <label class="form-check-label" for="account_<?php echo $account['id']; ?>">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($account['name']); ?></div>
                                                    <div class="text-muted fs-7"><?php echo htmlspecialchars($account['phone_number']); ?></div>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php if (empty($whatsappAccounts)): ?>
                                            <div class="alert alert-warning">
                                                Nenhuma conta WhatsApp encontrada. Verifique em <a href="/integrations/whatsapp">Integrações</a>.
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mb-10">
                                            <label class="form-label">Estratégia de Rotação</label>
                                            <select class="form-select" name="rotation_strategy">
                                                <option value="round_robin">Round Robin (Revezamento Justo)</option>
                                                <option value="random">Aleatório</option>
                                                <option value="by_load">Por Carga (Menos Usada)</option>
                                            </select>
                                            <div class="form-text">Como as contas serão alternadas durante o envio</div>
                                        </div>

                                        <div class="mb-10">
                                            <label class="form-label d-block">Criar conversa no funil?</label>
                                            <input type="hidden" name="create_conversation" value="0" />
                                            <label class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="create_conversation" value="1" checked onchange="toggleConversationConfig(this.checked)" />
                                                <span class="form-check-label">Sim, criar conversa e posicionar no funil</span>
                                            </label>
                                        </div>

                                        <div class="mb-10" id="conversation_config">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Funil</label>
                                                    <select class="form-select" name="funnel_id" id="funnel_id" onchange="updateStages()">
                                                        <option value="">Selecione...</option>
                                                        <?php foreach ($funnels ?? [] as $funnel): ?>
                                                            <option value="<?php echo $funnel['id']; ?>" <?php echo (!empty($defaultFunnelId) && (int)$defaultFunnelId === (int)$funnel['id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($funnel['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Etapa Inicial</label>
                                                    <select class="form-select" name="initial_stage_id" id="initial_stage_id">
                                                        <option value="">Selecione...</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="form-text">A conversa será criada neste funil/etapa</div>
                                        </div>
                                        
                                        <div class="mb-10">
                                            <label class="form-label d-block">Executar automações da etapa?</label>
                                            <input type="hidden" name="execute_automations" value="0" />
                                            <label class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="execute_automations" value="1" />
                                                <span class="form-check-label">Sim, executar automações configuradas na etapa</span>
                                            </label>
                                            <div class="form-text mt-2">
                                                Se marcado, as automações configuradas para a etapa inicial serão executadas automaticamente quando a conversa for criada.
                                                <br><span class="text-warning"><strong>Atenção:</strong> Isso pode incluir atribuição de agentes, envio de mensagens adicionais, etc.</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Filtros de Contatos -->
                                        <div class="separator separator-dashed my-8"></div>
                                        <h4 class="fw-bold mb-5">Filtros de Contatos</h4>
                                        
                                        <div class="mb-5">
                                            <input type="hidden" name="skip_duplicates" value="0" />
                                            <label class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="skip_duplicates" value="1" checked />
                                                <span class="form-check-label">Pular duplicatas (contatos que já receberam nesta campanha)</span>
                                            </label>
                                        </div>
                                        
                                        <div class="mb-5">
                                            <input type="hidden" name="skip_recent_conversations" value="0" />
                                            <label class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="skip_recent_conversations" value="1" id="skip_recent_check" onchange="toggleSkipRecentHours(this.checked)" />
                                                <span class="form-check-label">Pular contatos com conversa ativa recente</span>
                                            </label>
                                        </div>
                                        
                                        <div class="mb-5 ms-10" id="skip_recent_hours_config" style="display: none;">
                                            <label class="form-label">Pular se teve conversa nas últimas:</label>
                                            <div class="input-group" style="max-width: 200px;">
                                                <input type="number" class="form-control" name="skip_recent_hours" value="24" min="1" max="720" />
                                                <span class="input-group-text">horas</span>
                                            </div>
                                            <div class="form-text">Contatos com conversa aberta nesse período serão pulados</div>
                                        </div>
                                        
                                        <div class="mb-5">
                                            <input type="hidden" name="respect_blacklist" value="0" />
                                            <label class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="respect_blacklist" value="1" checked />
                                                <span class="form-check-label">Respeitar blacklist (não enviar para contatos bloqueados)</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Step 4: Mensagem -->
                                <div data-kt-stepper-element="content">
                                    <div class="w-100">
                                        <!-- Tipo de Mensagem -->
                                        <div class="mb-10">
                                            <label class="form-label fw-bold fs-5">Tipo de Mensagem</label>
                                            <div class="row g-5">
                                                <div class="col-md-6">
                                                    <label class="d-flex flex-stack cursor-pointer mb-5">
                                                        <span class="d-flex align-items-center me-2">
                                                            <span class="symbol symbol-50px me-6">
                                                                <span class="symbol-label bg-light-primary">
                                                                    <i class="ki-duotone ki-message-text-2 fs-1 text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                                                </span>
                                                            </span>
                                                            <span class="d-flex flex-column">
                                                                <span class="fw-bold fs-6">Mensagem Fixa</span>
                                                                <span class="fs-7 text-muted">Mesmo texto para todos</span>
                                                            </span>
                                                        </span>
                                                        <span class="form-check form-check-custom form-check-solid">
                                                            <input class="form-check-input" type="radio" name="message_type" value="fixed" checked onchange="toggleMessageType('fixed')" />
                                                        </span>
                                                    </label>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="d-flex flex-stack cursor-pointer mb-5">
                                                        <span class="d-flex align-items-center me-2">
                                                            <span class="symbol symbol-50px me-6">
                                                                <span class="symbol-label bg-light-success">
                                                                    <i class="ki-duotone ki-technology-4 fs-1 text-success"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                                                </span>
                                                            </span>
                                                            <span class="d-flex flex-column">
                                                                <span class="fw-bold fs-6">Gerar com IA</span>
                                                                <span class="fs-7 text-muted">Mensagem única para cada contato</span>
                                                            </span>
                                                        </span>
                                                        <span class="form-check form-check-custom form-check-solid">
                                                            <input class="form-check-input" type="radio" name="message_type" value="ai" onchange="toggleMessageType('ai')" />
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Mensagem Fixa -->
                                        <div id="fixed_message_config">
                                            <div class="mb-10">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <label class="form-label required mb-0">Conteúdo da Mensagem</label>
                                                    <a href="/campaigns/templates" target="_blank" class="btn btn-sm btn-light-primary">
                                                        <i class="ki-duotone ki-document fs-6"></i>
                                                        Ver Templates
                                                    </a>
                                                </div>
                                                <textarea class="form-control" name="message_content" id="message_content" rows="8"
                                                          placeholder="Olá {{nome}}! Temos uma oferta especial para você..."></textarea>
                                                <div class="form-text">
                                                    <strong>Variáveis disponíveis:</strong> {{nome}}, {{primeiro_nome}}, {{telefone}}, {{email}}
                                                </div>
                                            </div>
                                            
                                            <div class="mb-10">
                                                <label class="form-label">Preview da Mensagem</label>
                                                <div class="p-4 bg-light rounded" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto; white-space: pre-wrap; min-height: 100px;" id="message_preview">
                                                    Digite a mensagem para ver o preview...
                                                </div>
                                            </div>
                                            
                                            <div class="mb-10">
                                                <label class="form-label">Contador de Caracteres</label>
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-muted" id="char_count">0 caracteres</span>
                                                    <span class="text-muted" id="msg_count">~1 mensagem WhatsApp</span>
                                                </div>
                                                <div class="progress h-4px mt-2">
                                                    <div class="progress-bar bg-success" id="char_progress" style="width: 0%"></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Mensagem com IA -->
                                        <div id="ai_message_config" style="display: none;">
                                            <input type="hidden" name="ai_message_enabled" value="0" />
                                            
                                            <div class="alert alert-success d-flex align-items-center mb-10">
                                                <i class="ki-duotone ki-information-5 fs-2 me-3 text-success"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                                <div>
                                                    <strong>Mensagem gerada por IA:</strong> Cada contato receberá uma mensagem única e personalizada, gerada automaticamente pela IA baseada no seu prompt. Isso evita que as mensagens pareçam spam.
                                                </div>
                                            </div>
                                            
                                            <div class="mb-10">
                                                <label class="form-label required">Prompt para a IA</label>
                                                <textarea class="form-control" name="ai_message_prompt" id="ai_message_prompt" rows="6"
                                                          placeholder="Exemplo: Crie uma mensagem amigável oferecendo nossos serviços de marketing digital. Mencione que temos uma promoção especial para novos clientes. Use o nome do contato se disponível."></textarea>
                                                <div class="form-text">
                                                    Descreva o que a mensagem deve conter. A IA usará os dados do contato ({{nome}}, {{empresa}}, etc.) automaticamente.
                                                </div>
                                            </div>
                                            
                                            <div class="mb-10">
                                                <label class="form-label">Mensagem de Referência (opcional)</label>
                                                <textarea class="form-control" name="ai_reference_message" id="ai_reference_message" rows="4"
                                                          placeholder="Cole aqui um exemplo de mensagem que você gostaria que a IA use como referência de estilo..."></textarea>
                                                <div class="form-text">
                                                    A IA usará esta mensagem como inspiração, mas variará o conteúdo para cada contato.
                                                </div>
                                            </div>
                                            
                                            <div class="mb-10">
                                                <label class="form-label">Criatividade da IA</label>
                                                <div class="row align-items-center">
                                                    <div class="col-8">
                                                        <input type="range" class="form-range" name="ai_temperature" id="ai_temperature" min="0" max="1" step="0.1" value="0.7" oninput="updateTemperatureLabel(this.value)" />
                                                    </div>
                                                    <div class="col-4">
                                                        <span id="temperature_label" class="badge badge-lg badge-light-primary">0.7 - Balanceado</span>
                                                    </div>
                                                </div>
                                                <div class="form-text">
                                                    0.0 = Mais conservador e previsível | 1.0 = Mais criativo e variado
                                                </div>
                                            </div>
                                            
                                            <div class="mb-10">
                                                <label class="form-label">Preview (exemplo gerado)</label>
                                                <div class="p-4 bg-light rounded border-dashed border-success" id="ai_preview">
                                                    <div class="text-muted text-center py-5">
                                                        <i class="ki-duotone ki-technology-4 fs-3x text-success mb-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                                        <p class="mb-0">Clique em "Gerar Exemplo" para ver como ficará a mensagem</p>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-light-success mt-3" onclick="generateAIPreview()">
                                                    <i class="ki-duotone ki-arrows-circle fs-4"><span class="path1"></span><span class="path2"></span></i>
                                                    Gerar Exemplo
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Step 5: Agendamento -->
                                <div data-kt-stepper-element="content">
                                    <div class="w-100">
                                        <div class="mb-10">
                                            <label class="form-label">Quando enviar?</label>
                                            <select class="form-select" name="send_strategy" onchange="toggleSchedule(this.value)">
                                                <option value="immediate">Imediatamente (após preparar)</option>
                                                <option value="scheduled">Agendar para data/hora específica</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-10" id="scheduled_at_container" style="display:none;">
                                            <label class="form-label">Data e Hora</label>
                                            <input type="datetime-local" class="form-control" name="scheduled_at" />
                                        </div>
                                        
                                        <!-- Limites de Quantidade -->
                                        <div class="mb-10">
                                            <label class="form-label fw-bold fs-5">Limites de Envio</label>
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label fs-7">Limite Diário Total</label>
                                                    <input type="number" class="form-control" name="daily_limit" placeholder="Sem limite" min="1" />
                                                    <div class="form-text">Máx. msgs por dia</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fs-7">Limite por Hora</label>
                                                    <input type="number" class="form-control" name="hourly_limit" placeholder="Sem limite" min="1" />
                                                    <div class="form-text">Máx. msgs por hora</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fs-7">Limite por Conta/Dia</label>
                                                    <input type="number" class="form-control" name="daily_limit_per_account" placeholder="Sem limite" min="1" />
                                                    <div class="form-text">Por conta WhatsApp</div>
                                                </div>
                                            </div>
                                            <div class="alert alert-light-info d-flex align-items-center p-3 mt-3">
                                                <i class="ki-duotone ki-information-5 fs-2 me-3 text-info"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                                <div class="fs-7">Exemplo: Para enviar apenas 10 mensagens por dia, defina "Limite Diário Total" como 10.</div>
                                            </div>
                                        </div>

                                        <!-- Cadência de Envio -->
                                        <div class="mb-10">
                                            <label class="form-label fw-bold fs-5">Cadência de Envio</label>
                                            
                                            <!-- Intervalo fixo ou aleatório -->
                                            <div class="mb-5">
                                                <div class="form-check form-check-custom form-check-solid mb-3">
                                                    <input class="form-check-input" type="radio" name="interval_type" value="fixed" id="interval_fixed" checked onchange="toggleIntervalType('fixed')" />
                                                    <label class="form-check-label" for="interval_fixed">Intervalo Fixo</label>
                                                </div>
                                                <div class="form-check form-check-custom form-check-solid">
                                                    <input class="form-check-input" type="radio" name="interval_type" value="random" id="interval_random" onchange="toggleIntervalType('random')" />
                                                    <label class="form-check-label" for="interval_random">Intervalo Aleatório <span class="badge badge-light-success ms-2">Mais natural</span></label>
                                                </div>
                                            </div>
                                            
                                            <!-- Intervalo Fixo -->
                                            <div id="fixed_interval_config" class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fs-7">Mensagens por Minuto</label>
                                                    <input type="number" class="form-control" name="send_rate_per_minute" value="10" min="1" max="100" />
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fs-7">Intervalo entre Mensagens (segundos)</label>
                                                    <input type="number" class="form-control" name="send_interval_seconds" value="6" min="1" max="300" />
                                                </div>
                                            </div>
                                            
                                            <!-- Intervalo Aleatório -->
                                            <div id="random_interval_config" class="row g-3" style="display: none;">
                                                <input type="hidden" name="random_interval_enabled" value="0" />
                                                <div class="col-md-6">
                                                    <label class="form-label fs-7">Intervalo Mínimo (segundos)</label>
                                                    <input type="number" class="form-control" name="random_interval_min" value="30" min="5" max="600" />
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fs-7">Intervalo Máximo (segundos)</label>
                                                    <input type="number" class="form-control" name="random_interval_max" value="120" min="10" max="600" />
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-text">O sistema escolherá um intervalo aleatório entre esses valores para cada mensagem, simulando comportamento humano.</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Lotes -->
                                        <div class="mb-10">
                                            <label class="form-label fw-bold fs-5">Envio em Lotes (opcional)</label>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fs-7">Tamanho do Lote</label>
                                                    <input type="number" class="form-control" name="batch_size" placeholder="Sem lotes" min="1" />
                                                    <div class="form-text">Msgs por lote</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fs-7">Pausa entre Lotes (minutos)</label>
                                                    <input type="number" class="form-control" name="batch_pause_minutes" value="5" min="1" max="120" />
                                                    <div class="form-text">Tempo de espera</div>
                                                </div>
                                            </div>
                                            <div class="form-text mt-2">Exemplo: Lote de 5 msgs com pausa de 30 min = 5 msgs, espera 30 min, mais 5 msgs...</div>
                                        </div>
                                        
                                        <!-- Janela de Horário -->
                                        <div class="mb-10">
                                            <label class="form-label fw-bold fs-5">Janela de Envio</label>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fs-7">Horário Início</label>
                                                    <input type="time" class="form-control" name="send_window_start" value="09:00" />
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fs-7">Horário Fim</label>
                                                    <input type="time" class="form-control" name="send_window_end" value="18:00" />
                                                </div>
                                            </div>
                                            <div class="form-text">Mensagens só serão enviadas dentro deste horário</div>
                                        </div>

                                        <!-- Dias da Semana -->
                                        <div class="mb-10">
                                            <label class="form-label fw-bold fs-5">Dias da Semana</label>
                                            <div class="d-flex flex-wrap gap-4">
                                                <label class="form-check form-check-custom form-check-solid">
                                                    <input class="form-check-input" type="checkbox" name="send_days[]" value="1" checked />
                                                    <span class="form-check-label">Seg</span>
                                                </label>
                                                <label class="form-check form-check-custom form-check-solid">
                                                    <input class="form-check-input" type="checkbox" name="send_days[]" value="2" checked />
                                                    <span class="form-check-label">Ter</span>
                                                </label>
                                                <label class="form-check form-check-custom form-check-solid">
                                                    <input class="form-check-input" type="checkbox" name="send_days[]" value="3" checked />
                                                    <span class="form-check-label">Qua</span>
                                                </label>
                                                <label class="form-check form-check-custom form-check-solid">
                                                    <input class="form-check-input" type="checkbox" name="send_days[]" value="4" checked />
                                                    <span class="form-check-label">Qui</span>
                                                </label>
                                                <label class="form-check form-check-custom form-check-solid">
                                                    <input class="form-check-input" type="checkbox" name="send_days[]" value="5" checked />
                                                    <span class="form-check-label">Sex</span>
                                                </label>
                                                <label class="form-check form-check-custom form-check-solid">
                                                    <input class="form-check-input" type="checkbox" name="send_days[]" value="6" />
                                                    <span class="form-check-label">Sáb</span>
                                                </label>
                                                <label class="form-check form-check-custom form-check-solid">
                                                    <input class="form-check-input" type="checkbox" name="send_days[]" value="7" />
                                                    <span class="form-check-label">Dom</span>
                                                </label>
                                            </div>
                                            <div class="form-text">Selecione os dias permitidos para envio</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Step 6: Revisão -->
                                <div data-kt-stepper-element="content">
                                    <div class="w-100">
                                        <div class="mb-10">
                                            <h3 class="mb-5">Revisão Final</h3>
                                            <div id="review-summary"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Botões -->
                                <div class="d-flex flex-stack pt-10">
                                    <div class="me-2">
                                        <button type="button" class="btn btn-lg btn-light-primary me-3" data-kt-stepper-action="previous">
                                            <i class="ki-duotone ki-arrow-left fs-4 me-1"></i>
                                            Voltar
                                        </button>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-lg btn-primary" data-kt-stepper-action="submit">
                                            <span class="indicator-label">Criar Campanha</span>
                                            <span class="indicator-progress">Criando...
                                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                                        </button>
                                        <button type="button" class="btn btn-lg btn-primary" data-kt-stepper-action="next">
                                            Próximo
                                            <i class="ki-duotone ki-arrow-right fs-4 ms-1"></i>
                                        </button>
                                    </div>
                                </div>
                                
                            </form>
                        </div>
                        
                    </div>
                    
                </div>
            </div>
            
    </div>
</div>

<script>
let stepper;

function toggleSchedule(strategy) {
    document.getElementById('scheduled_at_container').style.display = 
        strategy === 'scheduled' ? 'block' : 'none';
}

function toggleIntervalType(type) {
    const fixedConfig = document.getElementById('fixed_interval_config');
    const randomConfig = document.getElementById('random_interval_config');
    const randomEnabledInput = document.querySelector('input[name="random_interval_enabled"]');
    
    if (type === 'random') {
        fixedConfig.style.display = 'none';
        randomConfig.style.display = 'flex';
        if (randomEnabledInput) randomEnabledInput.value = '1';
    } else {
        fixedConfig.style.display = 'flex';
        randomConfig.style.display = 'none';
        if (randomEnabledInput) randomEnabledInput.value = '0';
    }
}

function toggleMessageType(type) {
    const fixedConfig = document.getElementById('fixed_message_config');
    const aiConfig = document.getElementById('ai_message_config');
    const aiEnabledInput = document.querySelector('input[name="ai_message_enabled"]');
    const messageContentInput = document.querySelector('textarea[name="message_content"]');
    
    if (type === 'ai') {
        fixedConfig.style.display = 'none';
        aiConfig.style.display = 'block';
        if (aiEnabledInput) aiEnabledInput.value = '1';
        if (messageContentInput) messageContentInput.removeAttribute('required');
    } else {
        fixedConfig.style.display = 'block';
        aiConfig.style.display = 'none';
        if (aiEnabledInput) aiEnabledInput.value = '0';
        if (messageContentInput) messageContentInput.setAttribute('required', 'required');
    }
}

function updateTemperatureLabel(value) {
    const label = document.getElementById('temperature_label');
    if (!label) return;
    
    value = parseFloat(value);
    let text = '';
    let colorClass = 'badge-light-primary';
    
    if (value <= 0.3) {
        text = value.toFixed(1) + ' - Conservador';
        colorClass = 'badge-light-info';
    } else if (value <= 0.6) {
        text = value.toFixed(1) + ' - Balanceado';
        colorClass = 'badge-light-primary';
    } else if (value <= 0.8) {
        text = value.toFixed(1) + ' - Criativo';
        colorClass = 'badge-light-success';
    } else {
        text = value.toFixed(1) + ' - Muito Criativo';
        colorClass = 'badge-light-warning';
    }
    
    label.textContent = text;
    label.className = 'badge badge-lg ' + colorClass;
}

function generateAIPreview() {
    const prompt = document.getElementById('ai_message_prompt')?.value;
    const reference = document.getElementById('ai_reference_message')?.value;
    const temperature = document.getElementById('ai_temperature')?.value || 0.7;
    const previewDiv = document.getElementById('ai_preview');
    
    if (!prompt) {
        toastr.warning('Preencha o prompt para a IA');
        return;
    }
    
    previewDiv.innerHTML = '<div class="text-center py-5"><span class="spinner-border text-success"></span><p class="mt-3 mb-0">Gerando exemplo...</p></div>';
    
    fetch('/api/campaigns/preview-ai-message', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
            prompt: prompt,
            reference_message: reference,
            temperature: parseFloat(temperature),
            // Dados fictícios para preview
            contact: {
                name: 'João Silva',
                email: 'joao@email.com',
                phone: '11999991234',
                company: 'Empresa Exemplo Ltda'
            }
        })
    })
    .then(r => r.json())
    .then(result => {
        if (result.success && result.message) {
            previewDiv.innerHTML = '<div style="white-space: pre-wrap; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto;">' + result.message + '</div>';
        } else {
            previewDiv.innerHTML = '<div class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + (result.error || 'Erro ao gerar preview') + '</div>';
        }
    })
    .catch(err => {
        previewDiv.innerHTML = '<div class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Erro de conexão</div>';
    });
}

// Restaurar template se houver
const savedTemplate = sessionStorage.getItem('campaign_template');
if (savedTemplate) {
    const template = JSON.parse(savedTemplate);
    document.querySelector('[name="message_content"]').value = template.message;
    sessionStorage.removeItem('campaign_template');
}

// Preview e contador de mensagem
document.getElementById('message_content')?.addEventListener('input', function() {
    const content = this.value;
    const preview = document.getElementById('message_preview');
    const charCount = document.getElementById('char_count');
    const msgCount = document.getElementById('msg_count');
    const charProgress = document.getElementById('char_progress');
    
    // Preview com variáveis simuladas
    let previewText = content
        .replace(/\{\{nome\}\}/g, 'João Silva')
        .replace(/\{\{primeiro_nome\}\}/g, 'João')
        .replace(/\{\{telefone\}\}/g, '(11) 99999-1111')
        .replace(/\{\{email\}\}/g, 'joao@email.com');
    
    preview.innerHTML = previewText || 'Digite a mensagem para ver o preview...';
    
    // Contador
    const length = content.length;
    charCount.textContent = `${length} caracteres`;
    
    const whatsappLimit = 4096;
    const messagesNeeded = Math.ceil(length / 160) || 1;
    msgCount.textContent = `~${messagesNeeded} mensagem${messagesNeeded > 1 ? 'ns' : ''} WhatsApp`;
    
    const percent = Math.min((length / whatsappLimit) * 100, 100);
    charProgress.style.width = percent + '%';
    
    if (length > whatsappLimit) {
        charProgress.classList.remove('bg-success');
        charProgress.classList.add('bg-danger');
        msgCount.classList.add('text-danger');
    } else {
        charProgress.classList.remove('bg-danger');
        charProgress.classList.add('bg-success');
        msgCount.classList.remove('text-danger');
    }
});

const funnelsData = <?php echo json_encode($funnels ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function updateStages() {
    const funnelSelect = document.getElementById('funnel_id');
    const stageSelect = document.getElementById('initial_stage_id');
    if (!funnelSelect || !stageSelect) return;
    
    const defaultFunnelId = "<?php echo $defaultFunnelId ?? ''; ?>";
    if (!funnelSelect.value && defaultFunnelId) {
        funnelSelect.value = defaultFunnelId;
    }
    
    const funnelId = funnelSelect.value;
    const selectedFunnel = funnelsData.find(f => String(f.id) === String(funnelId));
    
    stageSelect.innerHTML = '<option value="">Selecione...</option>';
    if (!selectedFunnel || !selectedFunnel.stages) {
        return;
    }
    
    selectedFunnel.stages.forEach(stage => {
        const option = document.createElement('option');
        option.value = stage.id;
        option.textContent = stage.name;
        stageSelect.appendChild(option);
    });
    
    const defaultStageId = "<?php echo $defaultStageId ?? ''; ?>";
    if (defaultStageId) {
        stageSelect.value = defaultStageId;
    }
}

function toggleConversationConfig(enabled) {
    const container = document.getElementById('conversation_config');
    if (!container) return;
    container.style.display = enabled ? 'block' : 'none';
}

function toggleSkipRecentHours(enabled) {
    const container = document.getElementById('skip_recent_hours_config');
    if (!container) return;
    container.style.display = enabled ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    const stepperEl = document.querySelector('#campaign_wizard');
    stepper = new KTStepper(stepperEl);
    
    stepper.on('kt.stepper.next', function(stepper) {
        stepper.goNext();
        
        if (stepper.getCurrentStepIndex() === 6) {
            updateReviewSummary();
        }
    });
    
    stepper.on('kt.stepper.previous', function(stepper) {
        stepper.goPrevious();
    });
    
    document.querySelector('[data-kt-stepper-action="submit"]').addEventListener('click', function(e) {
        e.preventDefault();
        submitCampaign();
    });

    updateStages();
    const createConversationCheckbox = document.querySelector('input[name="create_conversation"][type="checkbox"]');
    toggleConversationConfig(createConversationCheckbox?.checked ?? true);
});

function updateReviewSummary() {
    const formData = new FormData(document.getElementById('campaign_form'));
    const accounts = formData.getAll('integration_account_ids[]');
    const daysSelected = formData.getAll('send_days[]');
    // Verificar checkbox diretamente (formData.get pega o hidden, não o checkbox)
    const createConversationCheckbox = document.querySelector('input[name="create_conversation"][type="checkbox"]');
    const createConversation = createConversationCheckbox?.checked ?? false;
    const funnelName = document.querySelector('#funnel_id option:checked')?.text || 'Não definido';
    const stageName = document.querySelector('#initial_stage_id option:checked')?.text || 'Não definido';
    const daysMap = {
        '1': 'Seg',
        '2': 'Ter',
        '3': 'Qua',
        '4': 'Qui',
        '5': 'Sex',
        '6': 'Sáb',
        '7': 'Dom'
    };
    const daysText = daysSelected.length
        ? daysSelected.map(d => daysMap[d] || d).join(', ')
        : 'Todos';
    
    // Limites
    const dailyLimit = formData.get('daily_limit') || 'Sem limite';
    const hourlyLimit = formData.get('hourly_limit') || 'Sem limite';
    const dailyLimitPerAccount = formData.get('daily_limit_per_account') || 'Sem limite';
    
    // Intervalo
    const intervalType = formData.get('interval_type');
    let intervalText = '';
    if (intervalType === 'random') {
        const minInt = formData.get('random_interval_min') || 30;
        const maxInt = formData.get('random_interval_max') || 120;
        intervalText = `Aleatório: ${minInt}s - ${maxInt}s`;
    } else {
        intervalText = `${formData.get('send_rate_per_minute')} msgs/min (intervalo: ${formData.get('send_interval_seconds')}s)`;
    }
    
    // Tipo de mensagem
    const messageType = formData.get('message_type');
    const aiEnabled = messageType === 'ai';
    // Verificar checkbox diretamente (formData.get pega o hidden, não o checkbox)
    const executeAutomationsCheckbox = document.querySelector('input[name="execute_automations"][type="checkbox"]');
    const executeAutomations = executeAutomationsCheckbox?.checked ?? false;
    
    // Lotes
    const batchSize = formData.get('batch_size');
    const batchPause = formData.get('batch_pause_minutes');
    const batchText = batchSize ? `${batchSize} msgs, pausa de ${batchPause} min` : 'Desativado';
    
    // Janela
    const windowStart = formData.get('send_window_start') || '09:00';
    const windowEnd = formData.get('send_window_end') || '18:00';
    
    const html = `
        <div class="card bg-light">
            <div class="card-body">
                <h5 class="mb-4">Configuração Básica</h5>
                <div class="mb-3">
                    <strong>Nome:</strong> ${formData.get('name')}
                </div>
                <div class="mb-3">
                    <strong>Lista:</strong> ${document.querySelector('[name="contact_list_id"] option:checked')?.text || 'Não selecionada'}
                </div>
                <div class="mb-3">
                    <strong>Contas WhatsApp:</strong> ${accounts.length} selecionada(s)
                </div>
                <div class="mb-3">
                    <strong>Conversa:</strong> ${createConversation ? `Sim (${funnelName} / ${stageName})` : 'Não criar'}
                </div>
                <div class="mb-3">
                    <strong>Executar Automações:</strong> ${executeAutomations ? '<span class="badge badge-success">Sim</span>' : '<span class="badge badge-secondary">Não</span>'}
                </div>
                
                <hr class="my-4">
                <h5 class="mb-4">Limites de Envio</h5>
                <div class="row mb-3">
                    <div class="col-4"><strong>Diário:</strong> ${dailyLimit}</div>
                    <div class="col-4"><strong>Por Hora:</strong> ${hourlyLimit}</div>
                    <div class="col-4"><strong>Por Conta:</strong> ${dailyLimitPerAccount}</div>
                </div>
                
                <hr class="my-4">
                <h5 class="mb-4">Cadência</h5>
                <div class="mb-3">
                    <strong>Intervalo:</strong> ${intervalText}
                </div>
                <div class="mb-3">
                    <strong>Lotes:</strong> ${batchText}
                </div>
                <div class="mb-3">
                    <strong>Horário:</strong> ${windowStart} às ${windowEnd}
                </div>
                <div class="mb-3">
                    <strong>Dias:</strong> ${daysText}
                </div>
                
                <hr class="my-4">
                <h5 class="mb-4">Mensagem</h5>
                ${aiEnabled ? `
                <div class="alert alert-success mb-3">
                    <strong><i class="fas fa-robot me-2"></i>Gerada por IA</strong> - Cada contato receberá uma mensagem única
                </div>
                <div class="mb-3"><strong>Prompt:</strong></div>
                <div class="p-3 bg-white rounded mb-3" style="white-space: pre-wrap;">${formData.get('ai_message_prompt') || 'Não definido'}</div>
                <div class="mb-2"><strong>Temperatura:</strong> ${formData.get('ai_temperature') || 0.7}</div>
                ` : `
                <div class="p-3 bg-white rounded" style="white-space: pre-wrap;">${formData.get('message_content') || 'Não definida'}</div>
                `}
            </div>
        </div>
    `;
    
    document.getElementById('review-summary').innerHTML = html;
}

function submitCampaign() {
    const btn = document.querySelector('[data-kt-stepper-action="submit"]');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    const formData = new FormData(document.getElementById('campaign_form'));
    const data = {};
    
    formData.forEach((value, key) => {
        if (key.includes('[]')) {
            const arrayKey = key.replace('[]', '');
            if (!data[arrayKey]) data[arrayKey] = [];
            data[arrayKey].push(value);
        } else {
            data[key] = value;
        }
    });
    
    data.channel = 'whatsapp';
    data.target_type = 'list';
    
    fetch('/campaigns', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        if (result.success) {
            toastr.success('Campanha criada com sucesso!');
            setTimeout(() => {
                window.location.href = `/campaigns/${result.campaign_id}`;
            }, 1000);
        } else {
            toastr.error(result.message || 'Erro ao criar campanha');
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        toastr.error('Erro de rede');
    });
}
</script>

<?php 
$content = ob_get_clean(); 
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
