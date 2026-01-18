<?php
$layout = 'layouts.metronic.app';
$title = 'Nova Sequência Drip';
$pageTitle = 'Nova Sequência';
?>

<?php ob_start(); ?>
<div class="app-toolbar py-3 py-lg-6">
    <div class="app-container container-fluid d-flex flex-stack">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                Nova Sequência Drip
            </h1>
        </div>
    </div>
</div>
<div class="app-container container-fluid">
            
            <div class="row">
                <div class="col-xl-8">
                    <div class="card mb-5">
                        <div class="card-header">
                            <h3 class="card-title">Informações da Sequência</h3>
                        </div>
                        <div class="card-body">
                            <form id="sequence_form">
                                <div class="mb-10">
                                    <label class="form-label required">Nome da Sequência</label>
                                    <input type="text" class="form-control" name="name" placeholder="Ex: Nutrição de Leads - Black Friday" required />
                                </div>
                                
                                <div class="mb-10">
                                    <label class="form-label">Descrição</label>
                                    <textarea class="form-control" name="description" rows="3" placeholder="Descreva o objetivo desta sequência..."></textarea>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Etapas -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Etapas da Sequência</h3>
                            <div class="card-toolbar">
                                <button type="button" class="btn btn-sm btn-primary" onclick="addStep()">
                                    <i class="ki-duotone ki-plus fs-3"></i>
                                    Adicionar Etapa
                                </button>
                            </div>
                        </div>
                        <div class="card-body" id="steps_container">
                            <div class="alert alert-info">
                                <strong>Dica:</strong> Adicione pelo menos 2 etapas. Cada etapa será enviada após o delay configurado.
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end mt-5">
                        <button type="button" class="btn btn-light me-3" onclick="window.history.back()">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btn_save" onclick="saveSequence()">
                            <span class="indicator-label">Criar Sequência</span>
                            <span class="indicator-progress">Criando...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </div>
                
                <div class="col-xl-4">
                    <div class="card card-flush mb-5" style="position: sticky; top: 100px;">
                        <div class="card-body">
                            <h4 class="mb-5">Visual da Sequência</h4>
                            <div id="sequence_preview">
                                <div class="text-muted text-center py-10">
                                    Adicione etapas para ver o preview
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
    </div>
</div>

<script>
let stepCounter = 0;
let steps = [];

function addStep() {
    stepCounter++;
    const stepHtml = `
        <div class="card mb-5" id="step_${stepCounter}" data-step-id="${stepCounter}">
            <div class="card-header">
                <h3 class="card-title">Etapa ${stepCounter}</h3>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-sm btn-light-danger" onclick="removeStep(${stepCounter})">
                        <i class="ki-duotone ki-trash fs-6"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-5">
                    <label class="form-label required">Nome da Etapa</label>
                    <input type="text" class="form-control step-name" placeholder="Ex: Mensagem Inicial" required />
                </div>
                
                <div class="mb-5">
                    <label class="form-label required">Mensagem</label>
                    <textarea class="form-control step-message" rows="4" placeholder="Olá {{nome}}! Primeira mensagem..." required></textarea>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Delay (dias)</label>
                        <input type="number" class="form-control step-delay-days" value="${stepCounter === 1 ? 0 : 1}" min="0" />
                        <div class="form-text">Aguardar X dias após etapa anterior</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Delay (horas)</label>
                        <input type="number" class="form-control step-delay-hours" value="0" min="0" max="23" />
                        <div class="form-text">Horas adicionais</div>
                    </div>
                </div>
                
                <div class="mt-5">
                    <label class="form-label">Condição (opcional)</label>
                    <select class="form-select step-condition">
                        <option value="">Nenhuma - sempre enviar</option>
                        <option value="no_reply">Somente se NÃO respondeu etapa anterior</option>
                        <option value="replied">Somente se RESPONDEU etapa anterior</option>
                    </select>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('steps_container').insertAdjacentHTML('beforeend', stepHtml);
    updatePreview();
}

function removeStep(stepId) {
    if (!confirm('Deseja remover esta etapa?')) return;
    document.getElementById(`step_${stepId}`).remove();
    renumberSteps();
    updatePreview();
}

function renumberSteps() {
    const stepCards = document.querySelectorAll('[data-step-id]');
    stepCards.forEach((card, index) => {
        card.querySelector('.card-title').textContent = `Etapa ${index + 1}`;
    });
}

function updatePreview() {
    const stepCards = document.querySelectorAll('[data-step-id]');
    if (stepCards.length === 0) {
        document.getElementById('sequence_preview').innerHTML = `
            <div class="text-muted text-center py-10">
                Adicione etapas para ver o preview
            </div>
        `;
        return;
    }
    
    let html = '<div class="timeline">';
    stepCards.forEach((card, index) => {
        const name = card.querySelector('.step-name').value || `Etapa ${index + 1}`;
        const days = card.querySelector('.step-delay-days').value || 0;
        const hours = card.querySelector('.step-delay-hours').value || 0;
        
        html += `
            <div class="timeline-item mb-5">
                <div class="timeline-line w-40px"></div>
                <div class="timeline-icon symbol symbol-circle symbol-40px">
                    <div class="symbol-label bg-light-primary">
                        <span class="text-primary fw-bold">${index + 1}</span>
                    </div>
                </div>
                <div class="timeline-content mb-5 mt-n1">
                    <div class="fw-bold text-gray-800">${name}</div>
                    ${index > 0 ? `<div class="text-muted fs-7">Aguarda ${days}d ${hours}h</div>` : '<div class="text-success fs-7">Imediato</div>'}
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    document.getElementById('sequence_preview').innerHTML = html;
}

function saveSequence() {
    const btn = document.getElementById('btn_save');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    const formData = new FormData(document.getElementById('sequence_form'));
    const data = Object.fromEntries(formData);
    
    // Coletar etapas
    const stepCards = document.querySelectorAll('[data-step-id]');
    const stepsData = [];
    
    stepCards.forEach((card, index) => {
        stepsData.push({
            step_order: index + 1,
            name: card.querySelector('.step-name').value,
            message_content: card.querySelector('.step-message').value,
            delay_days: parseInt(card.querySelector('.step-delay-days').value) || 0,
            delay_hours: parseInt(card.querySelector('.step-delay-hours').value) || 0,
            condition_type: card.querySelector('.step-condition').value || null
        });
    });
    
    if (stepsData.length < 2) {
        toastr.error('Adicione pelo menos 2 etapas');
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        return;
    }
    
    data.steps = stepsData;
    
    fetch('/drip-sequences', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        if (result.success) {
            toastr.success('Sequência criada com sucesso!');
            setTimeout(() => {
                window.location.href = `/drip-sequences/${result.sequence_id}`;
            }, 1000);
        } else {
            toastr.error(result.message || 'Erro ao criar sequência');
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        toastr.error('Erro de rede');
    });
}

// Adicionar primeira etapa automaticamente
document.addEventListener('DOMContentLoaded', () => {
    addStep();
});

// Update preview quando campos mudarem
document.addEventListener('input', (e) => {
    if (e.target.matches('.step-name, .step-delay-days, .step-delay-hours')) {
        updatePreview();
    }
});
</script>

<?php 
$content = ob_get_clean(); 
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
