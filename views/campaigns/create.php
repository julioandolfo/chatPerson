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
                                    </div>
                                </div>
                                
                                <!-- Step 4: Mensagem -->
                                <div data-kt-stepper-element="content">
                                    <div class="w-100">
                                        <div class="mb-10">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <label class="form-label required mb-0">Conteúdo da Mensagem</label>
                                                <a href="/campaigns/templates" target="_blank" class="btn btn-sm btn-light-primary">
                                                    <i class="ki-duotone ki-document fs-6"></i>
                                                    Ver Templates
                                                </a>
                                            </div>
                                            <textarea class="form-control" name="message_content" id="message_content" rows="8" required
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
                                        
                                        <div class="mb-10">
                                            <label class="form-label">Cadência de Envio</label>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fs-7">Mensagens por Minuto</label>
                                                    <input type="number" class="form-control" name="send_rate_per_minute" value="10" min="1" max="100" />
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fs-7">Intervalo entre Mensagens (segundos)</label>
                                                    <input type="number" class="form-control" name="send_interval_seconds" value="6" min="1" max="60" />
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-10">
                                            <label class="form-label">Janela de Envio (opcional)</label>
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

                                        <div class="mb-10">
                                            <label class="form-label">Dias da Semana</label>
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
});

function updateReviewSummary() {
    const formData = new FormData(document.getElementById('campaign_form'));
    const accounts = formData.getAll('integration_account_ids[]');
    const daysSelected = formData.getAll('send_days[]');
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
    
    const html = `
        <div class="card bg-light">
            <div class="card-body">
                <div class="mb-5">
                    <strong>Nome:</strong> ${formData.get('name')}
                </div>
                <div class="mb-5">
                    <strong>Lista:</strong> ${document.querySelector('[name="contact_list_id"] option:checked').text}
                </div>
                <div class="mb-5">
                    <strong>Contas WhatsApp:</strong> ${accounts.length} selecionada(s)
                </div>
                <div class="mb-5">
                    <strong>Estratégia:</strong> ${formData.get('rotation_strategy')}
                </div>
                <div class="mb-5">
                    <strong>Cadência:</strong> ${formData.get('send_rate_per_minute')} msgs/min
                </div>
                <div class="mb-5">
                    <strong>Dias de Envio:</strong> ${daysText}
                </div>
                <div>
                    <strong>Mensagem:</strong>
                    <div class="mt-2 p-3 bg-white rounded">${formData.get('message_content')}</div>
                </div>
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
