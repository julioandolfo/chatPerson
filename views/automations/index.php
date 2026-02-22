<?php
$title = 'Automações';

ob_start();
?>

<style>
/* ============================================
   AUTOMATIONS LIST - MODERN DESIGN
   ============================================ */

.automations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    padding: 20px 0;
}

.automation-card {
    background: var(--bs-card-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
    padding: 20px;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.automation-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    border-color: var(--bs-primary);
}

.automation-card.active::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(to bottom, #10b981, #34d399);
}

.automation-card.inactive::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(to bottom, #6b7280, #9ca3af);
}

.automation-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 12px;
}

.automation-card-title {
    font-weight: 600;
    font-size: 16px;
    color: var(--bs-heading-color);
    margin: 0;
    line-height: 1.4;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.automation-card-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.automation-card-badge.active {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.automation-card-badge.inactive {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
}

.automation-card-description {
    font-size: 13px;
    color: var(--bs-text-muted);
    margin-bottom: 16px;
    line-height: 1.5;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    min-height: 40px;
}

.automation-card-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--bs-border-color);
}

.automation-card-meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--bs-text-muted);
}

.automation-card-meta-item i {
    font-size: 14px;
    color: var(--bs-primary);
}

.automation-card-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 16px;
}

.automation-stat {
    display: flex;
    flex-direction: column;
}

.automation-stat-value {
    font-size: 20px;
    font-weight: 700;
    color: var(--bs-heading-color);
    line-height: 1;
}

.automation-stat-label {
    font-size: 11px;
    color: var(--bs-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
}

.automation-card-actions {
    display: flex;
    gap: 8px;
}

.automation-card-actions .btn {
    flex: 1;
    justify-content: center;
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 500;
}

.automation-card-actions .btn-icon {
    flex: 0 0 auto;
    padding: 8px;
}

/* Empty State Moderno */
.automations-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80px 20px;
    text-align: center;
}

.automations-empty-icon {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 24px;
}

.automations-empty-icon i {
    font-size: 48px;
    color: var(--bs-primary);
}

.automations-empty h3 {
    font-size: 20px;
    font-weight: 600;
    color: var(--bs-heading-color);
    margin-bottom: 8px;
}

.automations-empty p {
    font-size: 14px;
    color: var(--bs-text-muted);
    max-width: 400px;
    margin-bottom: 24px;
}

/* Filtros e Toolbar */
.automations-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 0;
    border-bottom: 1px solid var(--bs-border-color);
    margin-bottom: 20px;
}

.automations-search {
    position: relative;
    flex: 1;
    max-width: 400px;
}

.automations-search input {
    padding-left: 40px;
    border-radius: 8px;
}

.automations-search i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--bs-text-muted);
}

.automations-filters {
    display: flex;
    gap: 8px;
}

/* Nova Automação Card */
.automation-card-new {
    border: 2px dashed var(--bs-border-color);
    background: transparent;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 280px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.automation-card-new:hover {
    border-color: var(--bs-primary);
    background: rgba(59, 130, 246, 0.02);
}

.automation-card-new i {
    font-size: 40px;
    color: var(--bs-primary);
    margin-bottom: 12px;
}

.automation-card-new span {
    font-weight: 500;
    color: var(--bs-primary);
}

/* Mini Preview do Fluxo */
.automation-flow-preview {
    height: 60px;
    background: linear-gradient(90deg, 
        rgba(59, 130, 246, 0.1) 0%, 
        rgba(139, 92, 246, 0.1) 50%,
        rgba(16, 185, 129, 0.1) 100%);
    border-radius: 8px;
    margin-bottom: 16px;
    position: relative;
    overflow: hidden;
}

.automation-flow-preview::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 10%;
    right: 10%;
    height: 2px;
    background: linear-gradient(90deg, var(--bs-primary), var(--bs-success));
    transform: translateY(-50%);
}

.automation-flow-nodes {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    gap: 20px;
    padding: 0 20px;
}

.automation-flow-node {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}

/* Tags */
.automation-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 12px;
}

.automation-tag {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 500;
    background: var(--bs-light);
    color: var(--bs-text-muted);
}

/* Responsivo */
@media (max-width: 768px) {
    .automations-grid {
        grid-template-columns: 1fr;
    }
    
    .automations-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .automations-search {
        max-width: none;
    }
}
</style>

<!--begin::Header-->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4 mb-5">
    <div>
        <h1 class="fw-bold fs-2 mb-2">Automações</h1>
        <p class="text-muted fs-6 mb-0">Gerencie seus fluxos de automação e nós de trabalho</p>
    </div>
    <div>
        <?php if (\App\Helpers\Permission::can('automations.create')): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_automation">
            <i class="ki-duotone ki-plus fs-2 me-2"></i>
            Nova Automação
        </button>
        <?php endif; ?>
    </div>
</div>
<!--end::Header-->

<!--begin::Toolbar-->
<div class="automations-toolbar">
    <div class="automations-search">
        <i class="ki-duotone ki-search fs-3"></i>
        <input type="text" class="form-control" id="automation_search" placeholder="Buscar automações..." oninput="filterAutomations(this.value)">
    </div>
    <div class="automations-filters">
        <select class="form-select form-select-sm w-150px" id="filter_status" onchange="filterAutomations()">
            <option value="">Todos os status</option>
            <option value="active">Ativas</option>
            <option value="inactive">Inativas</option>
        </select>
        <select class="form-select form-select-sm w-150px" id="filter_trigger" onchange="filterAutomations()">
            <option value="">Todos os gatilhos</option>
            <option value="new_conversation">Nova Conversa</option>
            <option value="message_received">Mensagem Recebida</option>
            <option value="time_based">Baseado em Tempo</option>
            <option value="webhook">Webhook</option>
        </select>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Grid-->
<div class="automations-grid" id="automations_grid">
    
    <?php if (\App\Helpers\Permission::can('automations.create')): ?>
    <!-- Card para Nova Automação -->
    <div class="automation-card automation-card-new" data-bs-toggle="modal" data-bs-target="#kt_modal_new_automation">
        <i class="ki-duotone ki-plus-circle"></i>
        <span>Criar Nova Automação</span>
    </div>
    <?php endif; ?>
    
    <?php if (empty($automations)): ?>
        <!-- Empty State -->
        <div class="automations-empty" style="grid-column: 1 / -1;">
            <div class="automations-empty-icon">
                <i class="ki-duotone ki-gear"></i>
            </div>
            <h3>Nenhuma automação encontrada</h3>
            <p>Comece criando sua primeira automação para automatizar tarefas repetitivas e melhorar seu fluxo de trabalho.</p>
            <?php if (\App\Helpers\Permission::can('automations.create')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_automation">
                <i class="ki-duotone ki-plus fs-2 me-2"></i>
                Criar Automação
            </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php 
        $triggerLabels = [
            'new_conversation' => 'Nova Conversa',
            'message_received' => 'Mensagem do Cliente',
            'agent_message_sent' => 'Mensagem do Agente',
            'conversation_updated' => 'Conversa Atualizada',
            'conversation_moved' => 'Conversa Movida',
            'conversation_resolved' => 'Conversa Resolvida',
            'no_customer_response' => 'Sem Resposta Cliente',
            'no_agent_response' => 'Sem Resposta Agente',
            'time_based' => 'Baseado em Tempo',
            'contact_created' => 'Contato Criado',
            'contact_updated' => 'Contato Atualizado',
            'agent_activity' => 'Atividade do Agente',
            'webhook' => 'Webhook Externo'
        ];
        
        $triggerIcons = [
            'new_conversation' => 'ki-message-text-2',
            'message_received' => 'ki-message-notif',
            'agent_message_sent' => 'ki-send',
            'conversation_updated' => 'ki-update-file',
            'conversation_moved' => 'ki-arrow-right-left',
            'conversation_resolved' => 'ki-check-circle',
            'no_customer_response' => 'ki-timer',
            'no_agent_response' => 'ki-timer',
            'time_based' => 'ki-calendar',
            'contact_created' => 'ki-user',
            'contact_updated' => 'ki-user-edit',
            'agent_activity' => 'ki-profile-user',
            'webhook' => 'ki-abstract-26'
        ];
        
        foreach ($automations as $automation): 
            $isActive = $automation['status'] === 'active' && $automation['is_active'];
            $statusClass = $isActive ? 'active' : 'inactive';
            $triggerLabel = $triggerLabels[$automation['trigger_type']] ?? $automation['trigger_type'];
            $triggerIcon = $triggerIcons[$automation['trigger_type']] ?? 'ki-gear';
            
            // Contar nós
            $nodeCount = 0;
            try {
                $nodes = \App\Models\AutomationNode::where('automation_id', '=', $automation['id']);
                $nodeCount = count($nodes);
            } catch (\Exception $e) {
                $nodeCount = 0;
            }
            
            // Estatísticas
            $stats = ['total' => 0, 'completed' => 0];
            try {
                $stats = \App\Models\AutomationExecution::getStats($automation['id']);
            } catch (\Exception $e) {
                // ignorar
            }
        ?>
        <div class="automation-card <?= $statusClass ?>" data-name="<?= strtolower(htmlspecialchars($automation['name'])) ?>" data-status="<?= $isActive ? 'active' : 'inactive' ?>" data-trigger="<?= $automation['trigger_type'] ?>">
            <div class="automation-card-header">
                <h3 class="automation-card-title"><?= htmlspecialchars($automation['name']) ?></h3>
                <span class="automation-card-badge <?= $statusClass ?>">
                    <span class="bullet bullet-dot bg-<?= $isActive ? 'success' : 'secondary' ?> w-5px h-5px me-1"></span>
                    <?= $isActive ? 'Ativa' : 'Inativa' ?>
                </span>
            </div>
            
            <p class="automation-card-description">
                <?= htmlspecialchars($automation['description'] ?: 'Sem descrição') ?>
            </p>
            
            <!-- Mini Preview Visual -->
            <div class="automation-flow-preview">
                <div class="automation-flow-nodes">
                    <div class="automation-flow-node" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b;">
                        <i class="ki-duotone ki-flash-circle fs-7"></i>
                    </div>
                    <?php for ($i = 0; $i < min(3, max(0, $nodeCount - 1)); $i++): ?>
                    <div class="automation-flow-node" style="background: rgba(59, 130, 246, 0.2); color: #3b82f6;">
                        <i class="ki-duotone ki-gear fs-7"></i>
                    </div>
                    <?php endfor; ?>
                    <?php if ($nodeCount > 4): ?>
                    <div class="automation-flow-node" style="background: rgba(107, 114, 128, 0.2); color: #6b7280;">
                        <span style="font-size: 10px;">+<?= $nodeCount - 4 ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="automation-card-meta">
                <div class="automation-card-meta-item">
                    <i class="ki-duotone <?= $triggerIcon ?>"></i>
                    <span><?= $triggerLabel ?></span>
                </div>
                <div class="automation-card-meta-item">
                    <i class="ki-duotone ki-geometric-abstract"></i>
                    <span><?= $nodeCount ?> nó<?= $nodeCount !== 1 ? 's' : '' ?></span>
                </div>
            </div>
            
            <div class="automation-card-stats">
                <div class="automation-stat">
                    <span class="automation-stat-value"><?= $stats['completed'] ?? 0 ?></span>
                    <span class="automation-stat-label">Execuções</span>
                </div>
                <div class="automation-stat">
                    <span class="automation-stat-value"><?= date('d/m', strtotime($automation['created_at'])) ?></span>
                    <span class="automation-stat-label">Criado</span>
                </div>
            </div>
            
            <div class="automation-card-actions">
                <a href="<?= \App\Helpers\Url::to('/automations/' . $automation['id']) ?>" class="btn btn-light btn-sm">
                    <i class="ki-duotone ki-pencil me-1 fs-7"></i>
                    Editar
                </a>
                <a href="<?= \App\Helpers\Url::to('/automations/' . $automation['id']) ?>#logs" class="btn btn-light btn-sm btn-active-light-info">
                    <i class="ki-duotone ki-row-horizontal me-1 fs-7"></i>
                    Logs
                </a>
                <?php if (\App\Helpers\Permission::can('automations.delete')): ?>
                <button type="button" class="btn btn-icon btn-light btn-sm btn-active-light-danger delete-automation-btn" data-id="<?= $automation['id'] ?>" data-name="<?= htmlspecialchars($automation['name']) ?>" title="Excluir">
                    <i class="ki-duotone ki-trash fs-6">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                        <span class="path5"></span>
                    </i>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<!--end::Grid-->

<!--begin::Modal - Nova Automação-->
<div class="modal fade" id="kt_modal_new_automation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Nova Automação</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"></i>
                </div>
            </div>
            <form class="form" action="<?= \App\Helpers\Url::to('/automations') ?>" method="POST">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" class="form-control form-control-solid" placeholder="Ex: Boas-vindas WhatsApp" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" class="form-control form-control-solid" rows="3" placeholder="Descreva o objetivo desta automação..."></textarea>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Gatilho</label>
                        <select name="trigger_type" class="form-select form-select-solid" required>
                            <option value="">Selecione um gatilho...</option>
                            <optgroup label="Conversas">
                                <option value="new_conversation">Nova Conversa</option>
                                <option value="message_received">Mensagem do Cliente</option>
                                <option value="agent_message_sent">Mensagem do Agente</option>
                                <option value="conversation_updated">Conversa Atualizada</option>
                                <option value="conversation_moved">Conversa Movida no Funil</option>
                                <option value="conversation_resolved">Conversa Resolvida</option>
                            </optgroup>
                            <optgroup label="Tempo">
                                <option value="no_customer_response">Tempo sem Resposta do Cliente</option>
                                <option value="no_agent_response">Tempo sem Resposta do Agente</option>
                                <option value="time_based">Baseado em Tempo (Agendado)</option>
                            </optgroup>
                            <optgroup label="Contatos">
                                <option value="contact_created">Contato Criado</option>
                                <option value="contact_updated">Contato Atualizado</option>
                            </optgroup>
                            <optgroup label="Outros">
                                <option value="agent_activity">Atividade do Agente</option>
                                <option value="webhook">Webhook Externo</option>
                            </optgroup>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Criar Automação</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Nova Automação-->

<script>
function filterAutomations(query = null) {
    if (query === null) {
        query = document.getElementById('automation_search')?.value || '';
    }
    
    const statusFilter = document.getElementById('filter_status')?.value || '';
    const triggerFilter = document.getElementById('filter_trigger')?.value || '';
    
    query = query.toLowerCase();
    
    const cards = document.querySelectorAll('.automation-card:not(.automation-card-new)');
    
    cards.forEach(card => {
        const name = card.dataset.name || '';
        const status = card.dataset.status || '';
        const trigger = card.dataset.trigger || '';
        
        const matchesSearch = name.includes(query);
        const matchesStatus = !statusFilter || status === statusFilter;
        const matchesTrigger = !triggerFilter || trigger === triggerFilter;
        
        card.style.display = matchesSearch && matchesStatus && matchesTrigger ? 'block' : 'none';
    });
}

// Delete confirmation
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.delete-automation-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            
            if (confirm(`Deseja realmente excluir a automação "${name}"?\n\nEsta ação não pode ser desfeita.`)) {
                window.location.href = `<?= \App\Helpers\Url::to('/automations/') ?>${id}/delete`;
            }
        });
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
