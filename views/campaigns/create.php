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

                                        <!-- Toggle: lista vs. segmento dinâmico -->
                                        <ul class="nav nav-tabs nav-line-tabs nav-stretch fs-6 border-0 mb-7" id="target_tabs">
                                            <li class="nav-item">
                                                <a class="nav-link active" data-bs-toggle="tab" href="#target_list_tab" id="tab_list_btn">
                                                    <i class="ki-duotone ki-people fs-4 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                                                    Lista de Contatos
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link" data-bs-toggle="tab" href="#target_filter_tab" id="tab_filter_btn">
                                                    <i class="ki-duotone ki-filter-search fs-4 me-2"><span class="path1"></span><span class="path2"></span></i>
                                                    Segmento Dinâmico
                                                    <span class="badge badge-light-primary fs-9 ms-2">Funis &amp; Etapas</span>
                                                </a>
                                            </li>
                                        </ul>

                                        <input type="hidden" name="target_type" id="target_type_hidden" value="list" />

                                        <div class="tab-content">

                                            <!-- TAB 1: Lista estática (comportamento atual) -->
                                            <div class="tab-pane fade show active" id="target_list_tab" role="tabpanel">
                                                <div class="mb-10">
                                                    <label class="form-label required">Selecione a Lista de Contatos</label>
                                                    <select class="form-select form-select-lg" name="contact_list_id" id="contact_list_id_select">
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

                                                <div class="separator separator-dashed my-5"></div>

                                                <div class="d-flex align-items-start gap-4 p-5 bg-light-primary rounded border border-dashed border-primary">
                                                    <div class="form-check form-switch mt-1">
                                                        <input class="form-check-input" type="checkbox" id="continuous_mode" name="continuous_mode" value="1">
                                                    </div>
                                                    <div>
                                                        <label class="fw-bold text-gray-800 cursor-pointer" for="continuous_mode">
                                                            <i class="ki-duotone ki-arrows-circle fs-4 text-primary me-1">
                                                                <span class="path1"></span><span class="path2"></span>
                                                            </i>
                                                            Modo Contínuo
                                                        </label>
                                                        <div class="text-gray-600 fs-7 mt-1">
                                                            A campanha <strong>não encerra</strong> quando todos os contatos forem enviados.
                                                            A cada ciclo do scheduler, ela verifica se há <strong>novos contatos</strong> na lista (adicionados via sincronização diária) e os inclui automaticamente.
                                                            Ideal para listas alimentadas por fontes externas (Google Maps, etc.).
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- TAB 2: Segmento dinâmico por funis/etapas -->
                                            <div class="tab-pane fade" id="target_filter_tab" role="tabpanel">

                                                <div class="alert alert-info d-flex align-items-start mb-7">
                                                    <i class="ki-duotone ki-information-5 fs-2x me-3 mt-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                                    <div>
                                                        <div class="fw-bold mb-1">Segmento dinâmico por histórico de funis</div>
                                                        <div class="fs-7">
                                                            Construa o público-alvo combinando regras: leads que <strong>passaram</strong> por etapas X
                                                            e <strong>não passaram</strong> por etapas Y, com filtros de data, tags e canais.
                                                            O sistema resolve as regras no momento de iniciar a campanha.
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Lógica entre regras -->
                                                <div class="mb-5">
                                                    <label class="form-label">Combinar regras com</label>
                                                    <div class="btn-group" role="group">
                                                        <input type="radio" class="btn-check" name="seg_logic" id="seg_logic_and" value="AND" checked>
                                                        <label class="btn btn-sm btn-outline btn-outline-primary" for="seg_logic_and">E (todas as regras)</label>
                                                        <input type="radio" class="btn-check" name="seg_logic" id="seg_logic_or" value="OR">
                                                        <label class="btn btn-sm btn-outline btn-outline-primary" for="seg_logic_or">OU (qualquer regra)</label>
                                                    </div>
                                                </div>

                                                <!-- Botões para adicionar regras -->
                                                <div class="d-flex flex-wrap gap-2 mb-5">
                                                    <button type="button" class="btn btn-sm btn-light-success" data-seg-add="passed_through">
                                                        <i class="ki-duotone ki-check-circle fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                                                        Passou pela etapa
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-light-danger" data-seg-add="not_passed_through">
                                                        <i class="ki-duotone ki-cross-circle fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                                                        NÃO passou pela etapa
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-light-primary" data-seg-add="currently_in_stage">
                                                        <i class="ki-duotone ki-pin fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                                                        Está atualmente na etapa
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-light-info" data-seg-add="created_between">
                                                        <i class="ki-duotone ki-calendar fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                                                        Criado em período
                                                    </button>
                                                </div>

                                                <!-- Lista de regras -->
                                                <div id="seg_rules_list" class="mb-5"></div>

                                                <div id="seg_rules_empty" class="text-center text-muted p-7 border border-dashed rounded">
                                                    <i class="ki-duotone ki-filter fs-3x text-muted mb-3"><span class="path1"></span><span class="path2"></span></i>
                                                    <div class="fs-6">Nenhuma regra adicionada. Use os botões acima.</div>
                                                </div>

                                                <!-- Preview de alcance -->
                                                <div class="separator separator-dashed my-5"></div>

                                                <div class="d-flex align-items-center justify-content-between mb-3">
                                                    <div>
                                                        <div class="fw-bold">Alcance estimado</div>
                                                        <div class="text-muted fs-7">Calcule quantos contatos casam com as regras antes de criar a campanha</div>
                                                    </div>
                                                    <button type="button" class="btn btn-primary btn-sm" id="seg_preview_btn">
                                                        <i class="ki-duotone ki-magnifier fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                                        Calcular alcance
                                                    </button>
                                                </div>

                                                <div id="seg_preview_result" class="d-none p-5 bg-light-success rounded">
                                                    <div class="fs-2 fw-bold text-success mb-2"><span id="seg_preview_total">0</span> contato(s)</div>
                                                    <div class="text-muted fs-7 mb-3">Amostra dos primeiros encontrados:</div>
                                                    <div id="seg_preview_sample" class="d-flex flex-wrap gap-2"></div>
                                                </div>

                                                <div id="seg_preview_error" class="d-none alert alert-warning mt-3"></div>
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
                                            
                                            <?php foreach ($whatsappAccounts as $account): 
                                                $providerLabel = match($account['provider'] ?? 'quepasa') {
                                                    'notificame' => 'Notificame',
                                                    'whatsapp_official' => 'API Oficial',
                                                    'meta_cloud', 'meta_coex' => 'Cloud API',
                                                    'evolution' => 'Evolution',
                                                    default => 'QuePasa'
                                                };
                                                $providerColor = match($account['provider'] ?? 'quepasa') {
                                                    'notificame' => 'badge-light-primary',
                                                    'whatsapp_official', 'meta_cloud', 'meta_coex' => 'badge-light-success',
                                                    default => 'badge-light-info'
                                                };
                                            ?>
                                            <div class="form-check form-check-custom form-check-solid mb-3">
                                                <input class="form-check-input" type="checkbox" name="integration_account_ids[]" 
                                                       value="<?php echo $account['id']; ?>" id="account_<?php echo $account['id']; ?>">
                                                <label class="form-check-label" for="account_<?php echo $account['id']; ?>">
                                                    <div class="fw-bold">
                                                        <?php echo htmlspecialchars($account['name']); ?>
                                                        <span class="badge <?php echo $providerColor; ?> ms-2 fs-8"><?php echo $providerLabel; ?></span>
                                                    </div>
                                                    <div class="text-muted fs-7"><?php echo htmlspecialchars($account['phone_number'] ?? $account['account_id'] ?? ''); ?></div>
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
                                                <div class="col-md-3">
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
                                                <div class="col-md-3">
                                                    <label class="d-flex flex-stack cursor-pointer mb-5">
                                                        <span class="d-flex align-items-center me-2">
                                                            <span class="symbol symbol-50px me-6">
                                                                <span class="symbol-label bg-light-info">
                                                                    <i class="ki-duotone ki-document fs-1 text-info"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                                                </span>
                                                            </span>
                                                            <span class="d-flex flex-column">
                                                                <span class="fw-bold fs-6">Template Aprovado</span>
                                                                <span class="fs-7 text-muted">Template META/Notificame</span>
                                                            </span>
                                                        </span>
                                                        <span class="form-check form-check-custom form-check-solid">
                                                            <input class="form-check-input" type="radio" name="message_type" value="template" onchange="toggleMessageType('template')" />
                                                        </span>
                                                    </label>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="d-flex flex-stack cursor-pointer mb-5">
                                                        <span class="d-flex align-items-center me-2">
                                                            <span class="symbol symbol-50px me-6">
                                                                <span class="symbol-label bg-light-success">
                                                                    <i class="ki-duotone ki-technology-4 fs-1 text-success"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                                                </span>
                                                            </span>
                                                            <span class="d-flex flex-column">
                                                                <span class="fw-bold fs-6">Gerar com IA</span>
                                                                <span class="fs-7 text-muted">Mensagem única p/ contato</span>
                                                            </span>
                                                        </span>
                                                        <span class="form-check form-check-custom form-check-solid">
                                                            <input class="form-check-input" type="radio" name="message_type" value="ai" onchange="toggleMessageType('ai')" />
                                                        </span>
                                                    </label>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="d-flex flex-stack cursor-pointer mb-5">
                                                        <span class="d-flex align-items-center me-2">
                                                            <span class="symbol symbol-50px me-6">
                                                                <span class="symbol-label bg-light-warning">
                                                                    <i class="ki-duotone ki-arrows-circle fs-1 text-warning"><span class="path1"></span><span class="path2"></span></i>
                                                                </span>
                                                            </span>
                                                            <span class="d-flex flex-column">
                                                                <span class="fw-bold fs-6">Round-Robin</span>
                                                                <span class="fs-7 text-muted">Rotação entre mensagens</span>
                                                            </span>
                                                        </span>
                                                        <span class="form-check form-check-custom form-check-solid">
                                                            <input class="form-check-input" type="radio" name="message_type" value="roundrobin" onchange="toggleMessageType('roundrobin')" />
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
                                        
                                        <!-- Template Aprovado (Notificame/META) -->
                                        <div id="template_message_config" style="display: none;">
                                            <input type="hidden" name="use_template" value="0" />
                                            <input type="hidden" name="template_name" id="template_name_input" value="" />

                                            <div class="alert alert-info d-flex align-items-center mb-10">
                                                <i class="ki-duotone ki-information-5 fs-2 me-3 text-info"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                                <div>
                                                    <strong>Template aprovado pela META:</strong> Templates são obrigatórios para iniciar conversas via WhatsApp API Oficial. Selecione um template aprovado da sua conta Notificame.
                                                </div>
                                            </div>

                                            <div class="mb-10">
                                                <label class="form-label required">Conta Notificame</label>
                                                <select class="form-select" id="template_account_select" onchange="loadCampaignTemplates(this.value)">
                                                    <option value="">Selecione a conta...</option>
                                                    <?php
                                                    $notificameAccounts = array_filter($whatsappAccounts ?? [], function($a) {
                                                        return ($a['provider'] ?? '') === 'notificame';
                                                    });
                                                    foreach ($notificameAccounts as $acc): ?>
                                                        <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?> (<?= htmlspecialchars($acc['phone_number'] ?? $acc['account_id'] ?? '') ?>)</option>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($notificameAccounts)): ?>
                                                        <option value="" disabled>Nenhuma conta Notificame encontrada</option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>

                                            <div id="template_loading" class="text-center py-5" style="display:none;">
                                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                                <span class="ms-2 text-muted">Carregando templates...</span>
                                            </div>

                                            <div id="template_select_container" class="mb-10" style="display:none;">
                                                <label class="form-label required">Template</label>
                                                <select class="form-select" id="template_select" onchange="selectCampaignTemplate(this.value)">
                                                    <option value="">Selecione o template...</option>
                                                </select>
                                            </div>

                                            <div id="template_preview_container" style="display:none;">
                                                <div class="mb-5">
                                                    <div class="d-flex align-items-center gap-2 mb-3">
                                                        <span class="badge badge-light-success" id="template_status_badge">Aprovado</span>
                                                        <span class="badge badge-light" id="template_category_badge">-</span>
                                                        <span class="badge badge-light" id="template_language_badge">-</span>
                                                    </div>
                                                </div>
                                                <div class="mb-10">
                                                    <label class="form-label">Preview do Template</label>
                                                    <div class="p-4 bg-light rounded border border-dashed border-info" id="template_preview" style="white-space: pre-wrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto;">
                                                    </div>
                                                </div>
                                                <div id="template_params_container" class="mb-10" style="display:none;">
                                                    <label class="form-label">Parâmetros do Template</label>
                                                    <div class="alert alert-light-warning border-warning border-dashed mb-3">
                                                        <small>Preencha os parâmetros do template. Use <code>{{nome}}</code>, <code>{{telefone}}</code>, <code>{{email}}</code> para personalizar por contato.</small>
                                                    </div>
                                                    <div id="template_params_fields"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Round-Robin de Mensagens -->
                                        <div id="roundrobin_message_config" style="display: none;">
                                            <input type="hidden" name="round_robin_enabled" id="round_robin_enabled_input" value="0" />

                                            <div class="alert alert-warning d-flex align-items-center mb-8">
                                                <i class="ki-duotone ki-arrows-circle fs-2 me-3 text-warning"><span class="path1"></span><span class="path2"></span></i>
                                                <div>
                                                    <strong>Round-Robin de Mensagens:</strong> Cada contato recebe uma mensagem diferente em rotação. Ótimo para testar variações de texto ou templates e ver qual gera mais resposta.
                                                </div>
                                            </div>

                                            <div id="rr_messages_list">
                                                <!-- Itens adicionados dinamicamente pelo JS -->
                                            </div>

                                            <button type="button" class="btn btn-light-warning btn-sm mt-3" onclick="addRoundRobinMessage()">
                                                <i class="ki-duotone ki-plus fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                                Adicionar Mensagem
                                            </button>
                                            <div class="form-text mt-2 text-muted">Mínimo 2 mensagens para ativar o round-robin.</div>
                                        </div>

                                        <!-- Template de item round-robin (oculto, clonado pelo JS) -->
                                        <template id="rr_item_template">
                                            <div class="rr-message-item card card-bordered border-warning mb-5" data-rr-index="__IDX__">
                                                <div class="card-header min-h-50px">
                                                    <h4 class="card-title text-warning fs-6 mb-0">
                                                        <i class="ki-duotone ki-arrows-circle fs-5 text-warning me-2"><span class="path1"></span><span class="path2"></span></i>
                                                        Mensagem <span class="rr-item-number">__NUM__</span>
                                                    </h4>
                                                    <div class="card-toolbar">
                                                        <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removeRoundRobinMessage(this)" title="Remover mensagem">
                                                            <i class="ki-duotone ki-trash fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="card-body py-4">
                                                    <!-- Tipo da mensagem do item -->
                                                    <div class="mb-5">
                                                        <label class="form-label fw-bold">Tipo</label>
                                                        <div class="d-flex gap-5">
                                                            <label class="d-flex align-items-center cursor-pointer">
                                                                <input type="radio" class="form-check-input me-2 rr-type-radio" name="rr_type___IDX__" value="text" checked onchange="toggleRRItemType(this, '__IDX__')" />
                                                                <span>Texto livre</span>
                                                            </label>
                                                            <label class="d-flex align-items-center cursor-pointer">
                                                                <input type="radio" class="form-check-input me-2 rr-type-radio" name="rr_type___IDX__" value="template" onchange="toggleRRItemType(this, '__IDX__')" />
                                                                <span>Template META aprovado</span>
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <!-- Conteúdo texto -->
                                                    <div class="rr-text-config">
                                                        <label class="form-label required">Conteúdo da Mensagem</label>
                                                        <textarea class="form-control rr-msg-content" rows="5"
                                                                  placeholder="Olá {{nome}}! Esta é a mensagem __NUM__..."></textarea>
                                                        <div class="form-text">Variáveis: {{nome}}, {{primeiro_nome}}, {{telefone}}, {{email}}, {{empresa}}, {{cidade}}</div>
                                                    </div>

                                                    <!-- Conteúdo template -->
                                                    <div class="rr-template-config" style="display:none;">
                                                        <div class="mb-4">
                                                            <label class="form-label required">Conta Notificame</label>
                                                            <select class="form-select rr-template-account" onchange="loadRRTemplates(this, '__IDX__')">
                                                                <option value="">Selecione a conta...</option>
                                                                <?php
                                                                $notificameAccounts = array_filter($whatsappAccounts ?? [], function($a) {
                                                                    return ($a['provider'] ?? '') === 'notificame';
                                                                });
                                                                foreach ($notificameAccounts as $acc): ?>
                                                                    <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?> (<?= htmlspecialchars($acc['phone_number'] ?? $acc['account_id'] ?? '') ?>)</option>
                                                                <?php endforeach; ?>
                                                                <?php if (empty($notificameAccounts)): ?>
                                                                    <option value="" disabled>Nenhuma conta Notificame</option>
                                                                <?php endif; ?>
                                                            </select>
                                                        </div>
                                                        <div class="rr-template-select-wrap mb-4" style="display:none;">
                                                            <label class="form-label required">Template</label>
                                                            <select class="form-select rr-template-select" onchange="selectRRTemplate(this, '__IDX__')">
                                                                <option value="">Selecione o template...</option>
                                                            </select>
                                                        </div>
                                                        <div class="rr-template-preview-wrap" style="display:none;">
                                                            <label class="form-label">Preview</label>
                                                            <div class="p-3 bg-light rounded border border-dashed border-warning rr-template-preview-text" style="white-space:pre-wrap; min-height:60px;"></div>
                                                            <input type="hidden" class="rr-template-name" value="" />
                                                            <div class="rr-template-params-wrap mt-3" style="display:none;">
                                                                <label class="form-label">Parâmetros do Template</label>
                                                                <div class="alert alert-light-warning border-warning border-dashed mb-2">
                                                                    <small>Use <code>{{nome}}</code>, <code>{{telefone}}</code>, <code>{{email}}</code> para personalizar.</small>
                                                                </div>
                                                                <div class="rr-template-params-fields"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

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
    const templateConfig = document.getElementById('template_message_config');
    const rrConfig = document.getElementById('roundrobin_message_config');
    const aiEnabledInput = document.querySelector('input[name="ai_message_enabled"]');
    const useTemplateInput = document.querySelector('input[name="use_template"]');
    const rrEnabledInput = document.getElementById('round_robin_enabled_input');
    const messageContentInput = document.querySelector('textarea[name="message_content"]');

    fixedConfig.style.display = 'none';
    aiConfig.style.display = 'none';
    templateConfig.style.display = 'none';
    rrConfig.style.display = 'none';
    if (aiEnabledInput) aiEnabledInput.value = '0';
    if (useTemplateInput) useTemplateInput.value = '0';
    if (rrEnabledInput) rrEnabledInput.value = '0';

    if (type === 'ai') {
        aiConfig.style.display = 'block';
        if (aiEnabledInput) aiEnabledInput.value = '1';
        if (messageContentInput) messageContentInput.removeAttribute('required');
    } else if (type === 'template') {
        templateConfig.style.display = 'block';
        if (useTemplateInput) useTemplateInput.value = '1';
        if (messageContentInput) messageContentInput.removeAttribute('required');
    } else if (type === 'roundrobin') {
        rrConfig.style.display = 'block';
        if (rrEnabledInput) rrEnabledInput.value = '1';
        if (messageContentInput) messageContentInput.removeAttribute('required');
        // Inicializar com 2 itens se vazio
        const list = document.getElementById('rr_messages_list');
        if (list && list.children.length === 0) {
            addRoundRobinMessage();
            addRoundRobinMessage();
        }
    } else {
        fixedConfig.style.display = 'block';
        if (messageContentInput) messageContentInput.setAttribute('required', 'required');
    }
}

// ========== ROUND-ROBIN JS ==========
let _rrIndex = 0;
const _rrTemplatesCache = {};

function addRoundRobinMessage() {
    const list = document.getElementById('rr_messages_list');
    const template = document.getElementById('rr_item_template');
    if (!list || !template) return;

    const idx = _rrIndex++;
    const num = list.children.length + 1;
    let html = template.innerHTML
        .replace(/__IDX__/g, idx)
        .replace(/__NUM__/g, num);

    const wrapper = document.createElement('div');
    wrapper.innerHTML = html;
    list.appendChild(wrapper.firstElementChild);
    _renumberRRItems();
}

function removeRoundRobinMessage(btn) {
    const item = btn.closest('.rr-message-item');
    if (!item) return;
    const list = document.getElementById('rr_messages_list');
    if (list && list.children.length <= 2) {
        toastr.warning('Round-robin requer no mínimo 2 mensagens');
        return;
    }
    item.remove();
    _renumberRRItems();
}

function _renumberRRItems() {
    const items = document.querySelectorAll('#rr_messages_list .rr-message-item');
    items.forEach((item, i) => {
        const numEl = item.querySelector('.rr-item-number');
        if (numEl) numEl.textContent = (i + 1);
    });
}

function toggleRRItemType(radio, idx) {
    const item = radio.closest('.rr-message-item');
    if (!item) return;
    const textConfig = item.querySelector('.rr-text-config');
    const tplConfig = item.querySelector('.rr-template-config');
    if (radio.value === 'template') {
        if (textConfig) textConfig.style.display = 'none';
        if (tplConfig) tplConfig.style.display = 'block';
    } else {
        if (textConfig) textConfig.style.display = 'block';
        if (tplConfig) tplConfig.style.display = 'none';
    }
}

function loadRRTemplates(select, idx) {
    const accountId = select.value;
    const item = select.closest('.rr-message-item');
    if (!item || !accountId) return;

    const selectWrap = item.querySelector('.rr-template-select-wrap');
    const previewWrap = item.querySelector('.rr-template-preview-wrap');
    const tplSelect = item.querySelector('.rr-template-select');
    selectWrap.style.display = 'none';
    previewWrap.style.display = 'none';

    fetch(`/integrations/notificame/accounts/${accountId}/templates`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success || !data.templates) return;
        const templates = data.templates.filter(t => (t.status || '').toLowerCase() === 'approved');
        _rrTemplatesCache[idx + '_' + accountId] = {};
        templates.forEach(t => {
            const name = t.name || t.templateName || t.id;
            _rrTemplatesCache[idx + '_' + accountId][name] = t;
        });
        let opts = '<option value="">Selecione o template...</option>';
        templates.forEach(t => {
            const name = t.name || t.templateName || t.id;
            const cat = t.category || t.type || '';
            opts += `<option value="${escapeAttr(name)}">${escapeAttr(name)}${cat ? ' (' + cat + ')' : ''}</option>`;
        });
        tplSelect.innerHTML = opts;
        selectWrap.style.display = 'block';
        if (templates.length === 0) tplSelect.innerHTML = '<option value="">Nenhum template aprovado</option>';
    })
    .catch(() => toastr.error('Erro ao carregar templates'));
}

function selectRRTemplate(select, idx) {
    const name = select.value;
    const item = select.closest('.rr-message-item');
    if (!item) return;
    const previewWrap = item.querySelector('.rr-template-preview-wrap');
    const previewText = item.querySelector('.rr-template-preview-text');
    const nameInput = item.querySelector('.rr-template-name');
    const paramsWrap = item.querySelector('.rr-template-params-wrap');
    const paramsFields = item.querySelector('.rr-template-params-fields');

    if (!name) { previewWrap.style.display = 'none'; nameInput.value = ''; return; }

    const accountSelect = item.querySelector('.rr-template-account');
    const accountId = accountSelect ? accountSelect.value : '';
    const cacheKey = idx + '_' + accountId;
    const t = (_rrTemplatesCache[cacheKey] || {})[name];
    if (!t) return;

    nameInput.value = name;
    const body = t.body || t.text || t.content || (t.components || []).find(c => c.type === 'BODY')?.text || '';
    previewText.textContent = body;

    const paramRegex = /\{\{(\d+)\}\}/g;
    const params = [];
    let match;
    while ((match = paramRegex.exec(body)) !== null) {
        if (!params.includes(match[1])) params.push(match[1]);
    }

    if (params.length > 0) {
        let fieldsHtml = '';
        params.sort((a, b) => parseInt(a) - parseInt(b)).forEach(p => {
            fieldsHtml += `<div class="input-group mb-2">
                <span class="input-group-text fw-bold">{{${p}}}</span>
                <input type="text" class="form-control rr-tpl-param" data-param="${p}" placeholder="ex: {{nome}}" />
            </div>`;
        });
        paramsFields.innerHTML = fieldsHtml;
        paramsWrap.style.display = 'block';
    } else {
        paramsWrap.style.display = 'none';
        paramsFields.innerHTML = '';
    }

    previewWrap.style.display = 'block';
}

let _campaignTemplatesCache = {};

function loadCampaignTemplates(accountId) {
    if (!accountId) return;
    const loading = document.getElementById('template_loading');
    const selectContainer = document.getElementById('template_select_container');
    const previewContainer = document.getElementById('template_preview_container');
    const select = document.getElementById('template_select');

    loading.style.display = 'block';
    selectContainer.style.display = 'none';
    previewContainer.style.display = 'none';

    fetch(`/integrations/notificame/accounts/${accountId}/templates`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        loading.style.display = 'none';
        if (!data.success || !data.templates) {
            Swal.fire({ text: data.message || 'Erro ao carregar templates', icon: 'error', buttonsStyling: false, confirmButtonText: 'OK', customClass: { confirmButton: 'btn btn-primary' } });
            return;
        }
        const templates = data.templates.filter(t => (t.status || '').toLowerCase() === 'approved');
        _campaignTemplatesCache = {};
        templates.forEach(t => {
            const name = t.name || t.templateName || t.id;
            _campaignTemplatesCache[name] = t;
        });

        let options = '<option value="">Selecione o template...</option>';
        templates.forEach(t => {
            const name = t.name || t.templateName || t.id;
            const category = t.category || t.type || '';
            options += `<option value="${escapeAttr(name)}">${escapeAttr(name)} ${category ? '(' + category + ')' : ''}</option>`;
        });
        select.innerHTML = options;
        selectContainer.style.display = 'block';

        if (templates.length === 0) {
            select.innerHTML = '<option value="">Nenhum template aprovado encontrado</option>';
        }
    })
    .catch(err => {
        loading.style.display = 'none';
        Swal.fire({ text: 'Erro: ' + err.message, icon: 'error', buttonsStyling: false, confirmButtonText: 'OK', customClass: { confirmButton: 'btn btn-primary' } });
    });
}

function selectCampaignTemplate(name) {
    const previewContainer = document.getElementById('template_preview_container');
    const templateNameInput = document.getElementById('template_name_input');
    if (!name) {
        previewContainer.style.display = 'none';
        templateNameInput.value = '';
        return;
    }
    const t = _campaignTemplatesCache[name];
    if (!t) return;

    templateNameInput.value = name;
    const body = t.body || t.text || t.content || t.components?.find(c => c.type === 'BODY')?.text || '';
    const category = t.category || t.type || '-';
    const language = t.language || t.lang || '-';
    const status = (t.status || '').toLowerCase();

    document.getElementById('template_status_badge').textContent = status === 'approved' ? 'Aprovado' : status;
    document.getElementById('template_category_badge').textContent = category;
    document.getElementById('template_language_badge').textContent = language;
    document.getElementById('template_preview').textContent = body;

    // Detectar parâmetros {{1}}, {{2}}, etc.
    const paramRegex = /\{\{(\d+)\}\}/g;
    const params = [];
    let match;
    while ((match = paramRegex.exec(body)) !== null) {
        if (!params.includes(match[1])) params.push(match[1]);
    }

    const paramsContainer = document.getElementById('template_params_container');
    const paramsFields = document.getElementById('template_params_fields');

    if (params.length > 0) {
        let fieldsHtml = '';
        params.sort((a, b) => parseInt(a) - parseInt(b)).forEach(p => {
            fieldsHtml += `
                <div class="input-group mb-3">
                    <span class="input-group-text fw-bold">{{${p}}}</span>
                    <input type="text" class="form-control" name="template_params[${p}]" placeholder="Valor ou variável (ex: {{nome}})" />
                </div>`;
        });
        paramsFields.innerHTML = fieldsHtml;
        paramsContainer.style.display = 'block';
    } else {
        paramsContainer.style.display = 'none';
        paramsFields.innerHTML = '';
    }

    previewContainer.style.display = 'block';
}

function escapeAttr(str) {
    return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
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
                    <strong>Público-alvo:</strong> ${(() => {
                        const t = document.getElementById('target_type_hidden')?.value || 'list';
                        if (t === 'filter') {
                            const cfg = SegBuilder.toFilterConfig();
                            const n = cfg.rules.length;
                            return `<span class="badge badge-light-primary">Segmento dinâmico</span> ${n} regra(s) — lógica ${cfg.logic}`;
                        }
                        return document.querySelector('[name="contact_list_id"] option:checked')?.text || 'Não selecionada';
                    })()}
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
                ${messageType === 'roundrobin' ? (() => {
                    const rrItems = document.querySelectorAll('#rr_messages_list .rr-message-item');
                    let rrHtml = '<div class="alert alert-warning mb-3"><strong>Round-Robin</strong> - Rotação entre ' + rrItems.length + ' mensagens</div>';
                    rrItems.forEach((item, i) => {
                        const typeRadio = item.querySelector('.rr-type-radio:checked');
                        const msgType = typeRadio ? typeRadio.value : 'text';
                        if (msgType === 'template') {
                            const tplName = item.querySelector('.rr-template-name')?.value || 'não selecionado';
                            rrHtml += `<div class="mb-2"><strong>Msg ${i+1}:</strong> <span class="badge badge-light-info">Template</span> ${tplName}</div>`;
                        } else {
                            const content = item.querySelector('.rr-msg-content')?.value || '';
                            rrHtml += `<div class="mb-2 p-2 bg-white rounded"><strong>Msg ${i+1}:</strong> ${content.substring(0, 80)}${content.length > 80 ? '...' : ''}</div>`;
                        }
                    });
                    return rrHtml;
                })() : messageType === 'template' ? `
                <div class="alert alert-info mb-3">
                    <strong><i class="ki-duotone ki-document fs-4 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>Template Aprovado</strong> - Envio via template META/Notificame
                </div>
                <div class="mb-3"><strong>Template:</strong> ${formData.get('template_name') || 'Não selecionado'}</div>
                <div class="p-3 bg-white rounded mb-3" style="white-space: pre-wrap;">${document.getElementById('template_preview')?.textContent || ''}</div>
                ` : aiEnabled ? `
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

// =====================================================
// SEGMENT BUILDER (target_type=filter)
// =====================================================
const SegBuilder = (function() {
    let rules = [];
    let nextId = 1;

    function el(html) {
        const div = document.createElement('div');
        div.innerHTML = html.trim();
        return div.firstElementChild;
    }

    function ruleTitleFor(type) {
        return ({
            'passed_through': 'Passou pela etapa',
            'not_passed_through': 'NÃO passou pela etapa',
            'currently_in_stage': 'Está atualmente na etapa',
            'created_between': 'Contato criado em período',
        })[type] || type;
    }

    function ruleBadgeFor(type) {
        return ({
            'passed_through': 'badge-light-success',
            'not_passed_through': 'badge-light-danger',
            'currently_in_stage': 'badge-light-primary',
            'created_between': 'badge-light-info',
        })[type] || 'badge-light';
    }

    function funnelOptions() {
        let html = '<option value="">Qualquer funil</option>';
        funnelsData.forEach(f => {
            html += `<option value="${f.id}">${f.name}</option>`;
        });
        return html;
    }

    function stageOptionsForFunnel(funnelId) {
        if (!funnelId) {
            // Lista todas as etapas de todos os funis
            const opts = [];
            funnelsData.forEach(f => {
                (f.stages || []).forEach(s => {
                    opts.push(`<option value="${s.id}">${f.name} → ${s.name}</option>`);
                });
            });
            return opts.join('');
        }
        const funnel = funnelsData.find(f => String(f.id) === String(funnelId));
        if (!funnel || !funnel.stages) return '';
        return funnel.stages.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
    }

    function renderStageRule(rule) {
        const stagesHtml = stageOptionsForFunnel(rule.funnel_id);
        return `
            <div class="row g-3 mb-3">
                <div class="col-md-5">
                    <label class="form-label fs-8 text-muted">Funil (opcional)</label>
                    <select class="form-select form-select-sm seg-funnel-select" data-rule-id="${rule._id}">
                        ${funnelOptions()}
                    </select>
                </div>
                <div class="col-md-7">
                    <label class="form-label fs-8 text-muted">Etapas (uma ou mais)</label>
                    <select class="form-select form-select-sm seg-stages-select" data-rule-id="${rule._id}" multiple size="4">
                        ${stagesHtml}
                    </select>
                </div>
            </div>
            ${rule.type !== 'currently_in_stage' ? `
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fs-8 text-muted">A partir de</label>
                    <input type="date" class="form-control form-control-sm seg-since" data-rule-id="${rule._id}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fs-8 text-muted">Até</label>
                    <input type="date" class="form-control form-control-sm seg-until" data-rule-id="${rule._id}">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input class="form-check-input seg-anyof" data-rule-id="${rule._id}" type="checkbox" checked>
                        <label class="form-check-label fs-8">Qualquer das etapas (OU)</label>
                    </div>
                </div>
            </div>` : ''}
        `;
    }

    function renderDateRule(rule) {
        return `
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fs-8 text-muted">A partir de</label>
                    <input type="date" class="form-control form-control-sm seg-since" data-rule-id="${rule._id}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fs-8 text-muted">Até</label>
                    <input type="date" class="form-control form-control-sm seg-until" data-rule-id="${rule._id}">
                </div>
            </div>
        `;
    }

    function renderRule(rule) {
        const body = (rule.type === 'created_between') ? renderDateRule(rule) : renderStageRule(rule);
        return el(`
            <div class="card mb-3 seg-rule-card" data-rule-id="${rule._id}">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="badge ${ruleBadgeFor(rule.type)} fs-7 fw-bold">
                            <i class="ki-duotone ki-${rule.type === 'not_passed_through' ? 'cross-circle' : (rule.type === 'currently_in_stage' ? 'pin' : (rule.type === 'created_between' ? 'calendar' : 'check-circle'))} fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                            ${ruleTitleFor(rule.type)}
                        </span>
                        <button type="button" class="btn btn-sm btn-icon btn-light-danger" data-seg-remove="${rule._id}" title="Remover regra">
                            <i class="ki-duotone ki-trash fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                        </button>
                    </div>
                    ${body}
                </div>
            </div>
        `);
    }

    function refreshEmptyState() {
        document.getElementById('seg_rules_empty').style.display = rules.length === 0 ? '' : 'none';
    }

    function addRule(type) {
        const rule = { _id: nextId++, type, funnel_id: '', stage_ids: [], any_of: true, since: '', until: '' };
        rules.push(rule);
        const card = renderRule(rule);
        document.getElementById('seg_rules_list').appendChild(card);
        refreshEmptyState();
    }

    function removeRule(id) {
        rules = rules.filter(r => String(r._id) !== String(id));
        const card = document.querySelector(`.seg-rule-card[data-rule-id="${id}"]`);
        if (card) card.remove();
        refreshEmptyState();
    }

    function syncRuleFromDom(id) {
        const rule = rules.find(r => String(r._id) === String(id));
        if (!rule) return;
        const root = document.querySelector(`.seg-rule-card[data-rule-id="${id}"]`);
        if (!root) return;

        const funnelSel = root.querySelector('.seg-funnel-select');
        const stagesSel = root.querySelector('.seg-stages-select');
        const anyOf = root.querySelector('.seg-anyof');
        const since = root.querySelector('.seg-since');
        const until = root.querySelector('.seg-until');

        if (funnelSel) rule.funnel_id = funnelSel.value || '';
        if (stagesSel) {
            rule.stage_ids = Array.from(stagesSel.selectedOptions).map(o => parseInt(o.value, 10)).filter(Boolean);
        }
        if (anyOf) rule.any_of = anyOf.checked;
        if (since) rule.since = since.value || '';
        if (until) rule.until = until.value || '';
    }

    function reloadStagesForRule(id) {
        const rule = rules.find(r => String(r._id) === String(id));
        if (!rule) return;
        const root = document.querySelector(`.seg-rule-card[data-rule-id="${id}"]`);
        if (!root) return;
        const funnelSel = root.querySelector('.seg-funnel-select');
        const stagesSel = root.querySelector('.seg-stages-select');
        if (!funnelSel || !stagesSel) return;
        rule.funnel_id = funnelSel.value || '';
        stagesSel.innerHTML = stageOptionsForFunnel(rule.funnel_id);
        rule.stage_ids = [];
    }

    // Listeners delegados
    document.addEventListener('click', function(e) {
        const addBtn = e.target.closest('[data-seg-add]');
        if (addBtn) {
            addRule(addBtn.dataset.segAdd);
            return;
        }
        const rmBtn = e.target.closest('[data-seg-remove]');
        if (rmBtn) {
            removeRule(rmBtn.dataset.segRemove);
            return;
        }
    });

    document.addEventListener('change', function(e) {
        const id = e.target.dataset?.ruleId;
        if (!id) return;
        if (e.target.classList.contains('seg-funnel-select')) {
            reloadStagesForRule(id);
        } else {
            syncRuleFromDom(id);
        }
    });

    // Tab switch
    document.addEventListener('DOMContentLoaded', function() {
        const tabList = document.getElementById('tab_list_btn');
        const tabFilter = document.getElementById('tab_filter_btn');
        const hidden = document.getElementById('target_type_hidden');
        const contactSel = document.getElementById('contact_list_id_select');

        if (tabList) tabList.addEventListener('shown.bs.tab', () => {
            hidden.value = 'list';
            if (contactSel) contactSel.setAttribute('required', 'required');
        });
        if (tabFilter) tabFilter.addEventListener('shown.bs.tab', () => {
            hidden.value = 'filter';
            if (contactSel) contactSel.removeAttribute('required');
        });
    });

    // Botão preview
    document.addEventListener('DOMContentLoaded', function() {
        const btn = document.getElementById('seg_preview_btn');
        if (!btn) return;
        btn.addEventListener('click', function() {
            const config = SegBuilder.toFilterConfig();
            if (!config.rules.length) {
                toastr.warning('Adicione ao menos uma regra primeiro');
                return;
            }
            btn.setAttribute('data-kt-indicator', 'on');
            btn.disabled = true;
            const errBox = document.getElementById('seg_preview_error');
            const okBox = document.getElementById('seg_preview_result');
            errBox.classList.add('d-none');
            okBox.classList.add('d-none');

            fetch('/api/campaigns/preview-segment', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ filter_config: config, sample_size: 10 })
            })
            .then(r => r.json())
            .then(result => {
                btn.removeAttribute('data-kt-indicator');
                btn.disabled = false;
                if (!result.success) {
                    errBox.textContent = result.error || 'Erro ao calcular alcance';
                    errBox.classList.remove('d-none');
                    return;
                }
                document.getElementById('seg_preview_total').textContent = result.total.toLocaleString('pt-BR');
                const sampleEl = document.getElementById('seg_preview_sample');
                sampleEl.innerHTML = '';
                (result.sample || []).forEach(s => {
                    const chip = document.createElement('span');
                    chip.className = 'badge badge-light-primary';
                    chip.textContent = (s.name || 'Sem nome') + ' · ' + (s.phone || s.email || '—');
                    sampleEl.appendChild(chip);
                });
                if (!result.sample || result.sample.length === 0) {
                    sampleEl.innerHTML = '<span class="text-muted fs-7">Nenhum contato encontrado</span>';
                }
                okBox.classList.remove('d-none');
            })
            .catch(err => {
                btn.removeAttribute('data-kt-indicator');
                btn.disabled = false;
                errBox.textContent = 'Erro de conexão: ' + err.message;
                errBox.classList.remove('d-none');
            });
        });
    });

    return {
        toFilterConfig() {
            // Sincronizar todos os DOM antes de serializar
            rules.forEach(r => syncRuleFromDom(r._id));
            const logicEl = document.querySelector('input[name="seg_logic"]:checked');
            const logic = logicEl ? logicEl.value : 'AND';

            const cleanRules = rules.map(r => {
                const out = { type: r.type };
                if (['passed_through', 'not_passed_through', 'currently_in_stage'].includes(r.type)) {
                    if (r.funnel_id) out.funnel_id = parseInt(r.funnel_id, 10);
                    out.stage_ids = (r.stage_ids || []).map(x => parseInt(x, 10)).filter(Boolean);
                    if (r.type !== 'currently_in_stage') {
                        out.any_of = r.any_of !== false;
                        if (r.since) out.since = r.since;
                        if (r.until) out.until = r.until;
                    }
                } else if (r.type === 'created_between') {
                    if (r.since) out.since = r.since;
                    if (r.until) out.until = r.until;
                }
                return out;
            }).filter(r => {
                if (['passed_through', 'not_passed_through', 'currently_in_stage'].includes(r.type)) {
                    return r.stage_ids && r.stage_ids.length > 0;
                }
                if (r.type === 'created_between') return r.since || r.until;
                return false;
            });

            return { logic, rules: cleanRules };
        },
        getRulesCount() { return rules.length; },
        isActive() { return document.getElementById('target_type_hidden').value === 'filter'; }
    };
})();

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
    data.continuous_mode = document.getElementById('continuous_mode')?.checked ? '1' : '0';

    // Target: lista vs. segmento dinâmico
    const targetType = document.getElementById('target_type_hidden')?.value || 'list';
    data.target_type = targetType;

    if (targetType === 'filter') {
        const filterConfig = SegBuilder.toFilterConfig();
        if (!filterConfig.rules.length) {
            btn.removeAttribute('data-kt-indicator');
            btn.disabled = false;
            toastr.error('Segmento dinâmico requer ao menos uma regra preenchida (com etapas selecionadas)');
            return;
        }
        data.filter_config = filterConfig;
        data.contact_list_id = '';
    }

    // Template Notificame: coletar parâmetros
    if (data.use_template === '1' && data.template_name) {
        const paramInputs = document.querySelectorAll('#template_params_fields input[name^="template_params"]');
        const templateParams = {};
        paramInputs.forEach(input => {
            const match = input.name.match(/template_params\[(\d+)\]/);
            if (match) templateParams[match[1]] = input.value;
        });
        data.template_params = templateParams;
        data.template_account_id = document.getElementById('template_account_select')?.value || '';
    }

    // Round-robin: coletar mensagens
    if (data.round_robin_enabled === '1') {
        const rrItems = document.querySelectorAll('#rr_messages_list .rr-message-item');
        const rrMessages = [];
        rrItems.forEach(item => {
            const typeRadio = item.querySelector('.rr-type-radio:checked');
            const msgType = typeRadio ? typeRadio.value : 'text';

            if (msgType === 'template') {
                const templateName = item.querySelector('.rr-template-name')?.value || '';
                const accountId = item.querySelector('.rr-template-account')?.value || '';
                const paramInputs = item.querySelectorAll('.rr-tpl-param');
                const params = {};
                paramInputs.forEach(inp => {
                    if (inp.dataset.param) params[inp.dataset.param] = inp.value;
                });
                rrMessages.push({
                    message_type: 'template',
                    message_content: '[Template: ' + templateName + ']',
                    template_name: templateName,
                    template_account_id: accountId,
                    template_params: params
                });
            } else {
                const content = item.querySelector('.rr-msg-content')?.value || '';
                rrMessages.push({
                    message_type: 'text',
                    message_content: content
                });
            }
        });

        if (rrMessages.length < 2) {
            btn.removeAttribute('data-kt-indicator');
            btn.disabled = false;
            toastr.error('Round-robin requer no mínimo 2 mensagens preenchidas');
            return;
        }

        data.round_robin_messages = rrMessages;
        // Placeholder para message_content (obrigatório no backend)
        data.message_content = '[Round-Robin]';
    }

    fetch('/campaigns', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(data)
    })
    .then(r => {
        const contentType = r.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            return r.json();
        }
        return r.text().then(text => {
            console.error('Resposta não-JSON do servidor:', text);
            throw new Error(text.substring(0, 300) || `HTTP ${r.status}`);
        });
    })
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
        console.error('Erro ao criar campanha:', err);
        toastr.error(err.message || 'Erro de rede');
    });
}
</script>

<?php 
$content = ob_get_clean(); 
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
