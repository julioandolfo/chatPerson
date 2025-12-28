<?php
$layout = 'layouts.metronic.app';
$title = 'Criar Agente Kanban';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Criar Novo Agente Kanban</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        <form id="kt_form_kanban_agent">
            <div class="row mb-5">
                <div class="col-md-12">
                    <label class="form-label required">Nome</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
            </div>
            
            <div class="row mb-5">
                <div class="col-md-12">
                    <label class="form-label">Descrição</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            
            <div class="row mb-5">
                <div class="col-md-6">
                    <label class="form-label required">Tipo</label>
                    <select name="agent_type" class="form-select" required>
                        <option value="kanban_followup">Followup</option>
                        <option value="kanban_analyzer">Analisador</option>
                        <option value="kanban_manager">Gerenciador</option>
                        <option value="kanban_custom">Personalizado</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Modelo</label>
                    <select name="model" class="form-select" required>
                        <option value="gpt-4">GPT-4</option>
                        <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-5">
                <div class="col-md-12">
                    <label class="form-label required">Prompt</label>
                    <textarea name="prompt" class="form-control" rows="5" required placeholder="Digite o prompt do agente..."></textarea>
                </div>
            </div>
            
            <div class="row mb-5">
                <div class="col-md-6">
                    <label class="form-label required">Tipo de Execução</label>
                    <select name="execution_type" class="form-select" required id="execution_type">
                        <option value="interval">Por Intervalo</option>
                        <option value="schedule">Agendado</option>
                        <option value="manual">Manual</option>
                    </select>
                </div>
                <div class="col-md-6" id="interval_hours_container">
                    <label class="form-label">Intervalo (horas)</label>
                    <input type="number" name="execution_interval_hours" class="form-control" min="1" placeholder="Ex: 48 (a cada 2 dias)">
                </div>
            </div>
            
            <div class="row mb-5">
                <div class="col-md-6">
                    <label class="form-label">Máximo de Conversas por Execução</label>
                    <input type="number" name="max_conversations_per_execution" class="form-control" value="50" min="1" max="1000">
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch mt-8">
                        <input class="form-check-input" type="checkbox" name="enabled" id="enabled" checked>
                        <label class="form-check-label" for="enabled">Ativo</label>
                    </div>
                </div>
            </div>
            
            <div class="separator separator-dashed my-10"></div>
            
            <h4 class="fw-bold mb-5">Funis e Etapas Alvo</h4>
            <div class="row mb-5">
                <div class="col-md-6">
                    <label class="form-label">Funis (deixe vazio para todos)</label>
                    <select name="target_funnel_ids[]" class="form-select" id="target_funnels" multiple size="5">
                        <?php foreach ($funnels as $funnel): ?>
                            <option value="<?= $funnel['id'] ?>"><?= htmlspecialchars($funnel['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Segure Ctrl/Cmd para selecionar múltiplos funis</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Etapas (deixe vazio para todas)</label>
                    <select name="target_stage_ids[]" class="form-select" id="target_stages" multiple size="5">
                        <option value="">Carregando etapas...</option>
                    </select>
                    <div class="form-text">Segure Ctrl/Cmd para selecionar múltiplas etapas</div>
                </div>
            </div>
            
            <div class="separator separator-dashed my-10"></div>
            
            <div class="d-flex justify-content-end">
                <a href="<?= \App\Helpers\Url::to('/kanban-agents') ?>" class="btn btn-light me-3">Cancelar</a>
                <button type="submit" class="btn btn-primary">Criar Agente</button>
            </div>
        </form>
    </div>
</div>
<!--end::Card-->

<script>
const allStages = <?= json_encode($allStages, JSON_UNESCAPED_UNICODE) ?>;

document.getElementById('execution_type').addEventListener('change', function() {
    const intervalContainer = document.getElementById('interval_hours_container');
    if (this.value === 'interval') {
        intervalContainer.style.display = 'block';
    } else {
        intervalContainer.style.display = 'none';
    }
});

// Atualizar etapas quando funis são selecionados
document.getElementById('target_funnels').addEventListener('change', function() {
    const selectedFunnels = Array.from(this.selectedOptions).map(opt => parseInt(opt.value));
    const stagesSelect = document.getElementById('target_stages');
    
    stagesSelect.innerHTML = '';
    
    if (selectedFunnels.length === 0) {
        // Se nenhum funil selecionado, mostrar todas as etapas
        Object.keys(allStages).forEach(funnelId => {
            allStages[funnelId].forEach(stage => {
                const option = document.createElement('option');
                option.value = stage.id;
                option.textContent = stage.name + ' (Funil: ' + stage.funnel_id + ')';
                stagesSelect.appendChild(option);
            });
        });
    } else {
        // Mostrar apenas etapas dos funis selecionados
        selectedFunnels.forEach(funnelId => {
            if (allStages[funnelId]) {
                allStages[funnelId].forEach(stage => {
                    const option = document.createElement('option');
                    option.value = stage.id;
                    option.textContent = stage.name;
                    stagesSelect.appendChild(option);
                });
            }
        });
    }
});

// Carregar todas as etapas inicialmente
document.getElementById('target_funnels').dispatchEvent(new Event('change'));

document.getElementById('kt_form_kanban_agent').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {};
    
    // Processar campos normais
    for (let [key, value] of formData.entries()) {
        if (key.endsWith('[]')) {
            const realKey = key.replace('[]', '');
            if (!data[realKey]) {
                data[realKey] = [];
            }
            data[realKey].push(value);
        } else {
            data[key] = value;
        }
    }
    
    // Converter arrays vazios para null
    if (data.target_funnel_ids && data.target_funnel_ids.length === 0) {
        data.target_funnel_ids = null;
    }
    if (data.target_stage_ids && data.target_stage_ids.length === 0) {
        data.target_stage_ids = null;
    }
    
    data.enabled = document.getElementById('enabled').checked;
    data.conditions = JSON.stringify({operator: 'AND', conditions: []});
    data.actions = JSON.stringify([]);
    
    fetch('/kanban-agents', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Sucesso!', data.message, 'success').then(() => {
                window.location.href = '/kanban-agents/' + data.agent_id;
            });
        } else {
            Swal.fire('Erro!', data.message || 'Erro ao criar agente', 'error');
        }
    })
    .catch(error => {
        Swal.fire('Erro!', 'Erro ao criar agente', 'error');
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>

