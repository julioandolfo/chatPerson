<!-- Sidebar Direita - Detalhes da Conversa -->
<script>
// Stubs imediatos para evitar ReferenceError em onclick inline
(function() {
    const stub = (name) => function(...args) {
        console.warn(`[stub] ${name} chamado antes da definição real`, args);
    };
    window.moveConversationStage = window.moveConversationStage || stub('moveConversationStage');
    window.markAsSpam = window.markAsSpam || stub('markAsSpam');
    window.editContact = window.editContact || stub('editContact');
    window.manageContactAgents = window.manageContactAgents || stub('manageContactAgents');
    window.showAIHistory = window.showAIHistory || stub('showAIHistory');
    window.removeAIAgent = window.removeAIAgent || stub('removeAIAgent');
    window.showAddAIAgentModal = window.showAddAIAgentModal || stub('showAddAIAgentModal');
    window.showAddParticipantModal = window.showAddParticipantModal || stub('showAddParticipantModal');
    window.manageTags = window.manageTags || stub('manageTags');
    window.escalateFromAI = window.escalateFromAI || stub('escalateFromAI');
    window.assignConversation = window.assignConversation || stub('assignConversation');
    window.changeDepartment = window.changeDepartment || stub('changeDepartment');
    window.closeConversation = window.closeConversation || stub('closeConversation');
    window.reopenConversation = window.reopenConversation || stub('reopenConversation');
    window.addNote = window.addNote || stub('addNote');
    window.loadConversationSLA = window.loadConversationSLA || stub('loadConversationSLA');
})();
</script>

<script>
// ========== DEFINIÇÃO IMEDIATA DA FUNÇÃO loadConversationSLA ==========
// Esta função precisa estar disponível imediatamente para updateConversationSidebar
window.loadConversationSLA = function(conversationId) {
    if (!conversationId) return;
    
    const loadingEl = document.getElementById('sla-loading');
    const contentEl = document.getElementById('sla-content');
    const statusBadge = document.getElementById('sla-status-badge');
    
    if (loadingEl) loadingEl.style.display = 'block';
    if (contentEl) contentEl.style.display = 'none';
    if (statusBadge) statusBadge.textContent = '...';
    
    console.log('🔍 Carregando SLA para conversa:', conversationId);
    
    fetch(`<?= \App\Helpers\Url::to('/conversations/sla-details') ?>?id=${conversationId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        console.log('📥 Resposta SLA recebida:', {
            status: response.status,
            statusText: response.statusText,
            ok: response.ok,
            url: response.url
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return response.text().then(text => {
            console.log('📄 Resposta em texto:', text.substring(0, 500));
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('❌ Erro ao fazer parse do JSON:', e);
                console.error('📄 Texto completo:', text);
                throw new Error('Resposta não é JSON válido');
            }
        });
    })
    .then(data => {
        console.log('📊 Dados SLA parseados:', data);
        
        if (data.success && data.sla) {
            const sla = data.sla;
            
            if (loadingEl) loadingEl.style.display = 'none';
            if (contentEl) contentEl.style.display = 'block';
            
            // Atualizar badge de status
            let badgeClass = 'badge-light-success';
            let badgeText = '✓ No prazo';
            
            if (sla.status_indicator === 'exceeded') {
                badgeClass = 'badge-light-danger';
                badgeText = '✗ Excedido';
            } else if (sla.status_indicator === 'warning') {
                badgeClass = 'badge-light-warning';
                badgeText = '⚠ Alerta';
            } else if (!sla.should_start) {
                badgeClass = 'badge-light-secondary';
                badgeText = '⏸ Aguardando';
            }
            
            if (statusBadge) {
                statusBadge.className = `badge badge-sm ${badgeClass}`;
                statusBadge.textContent = badgeText;
            }
            
            // Atualizar progresso
            const progressBar = document.getElementById('sla-progress-bar');
            const elapsedTimeEl = document.getElementById('sla-elapsed-time');
            const percentageEl = document.getElementById('sla-percentage');
            const targetEl = document.getElementById('sla-target');
            
            if (progressBar) {
                let barClass = 'bg-success';
                if (sla.percentage >= 100) barClass = 'bg-danger';
                else if (sla.percentage >= 80) barClass = 'bg-warning';
                
                progressBar.className = `progress-bar progress-bar-striped ${sla.percentage < 100 ? 'progress-bar-animated' : ''} ${barClass}`;
                progressBar.style.width = Math.min(100, sla.percentage) + '%';
            }
            
            if (elapsedTimeEl) {
                elapsedTimeEl.textContent = `${sla.elapsed_minutes} min`;
                elapsedTimeEl.className = `fs-6 fw-bold ${sla.percentage >= 100 ? 'text-danger' : sla.percentage >= 80 ? 'text-warning' : 'text-success'}`;
            }
            
            if (percentageEl) {
                percentageEl.textContent = `${sla.percentage}%`;
                percentageEl.className = `fs-8 fw-bold ${sla.percentage >= 100 ? 'text-danger' : sla.percentage >= 80 ? 'text-warning' : 'text-success'}`;
            }
            
            if (targetEl) {
                const targetMinutes = sla.current_sla_minutes ?? sla.first_response_sla;
                const label = sla.sla_label ? ` (${sla.sla_label})` : '';
                targetEl.textContent = `${targetMinutes} min${label}`;
            }
            
            // Detalhes
            const ruleNameEl = document.getElementById('sla-rule-name');
            const startTimeEl = document.getElementById('sla-start-time');
            
            if (ruleNameEl) ruleNameEl.textContent = sla.sla_rule || 'Global';
            if (startTimeEl) {
                if (!sla.should_start) {
                    startTimeEl.textContent = '—';
                } else if (sla.start_time) {
                    // Converter data do servidor (formato: 2026-01-20 14:20:00) para objeto Date
                    const startDate = new Date(sla.start_time.replace(' ', 'T'));
                    startTimeEl.textContent = startDate.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
                } else {
                    startTimeEl.textContent = '—';
                }
            }
            
            // Excedido
            const exceededContainer = document.getElementById('sla-exceeded-container');
            const exceededByEl = document.getElementById('sla-exceeded-by');
            
            if (exceededContainer && exceededByEl) {
                if (sla.percentage > 100) {
                    exceededContainer.style.display = 'flex';
                    const exceededMinutes = sla.elapsed_minutes - sla.first_response_sla;
                    exceededByEl.textContent = `+${exceededMinutes.toFixed(0)} min`;
                } else {
                    exceededContainer.style.display = 'none';
                }
            }
            
            // Timeline
            const timelineEl = document.getElementById('sla-timeline');
            if (timelineEl && sla.timeline) {
                let timelineHtml = '';
                
                sla.timeline.forEach((event, index) => {
                    const time = new Date(event.time);
                    const timeStr = time.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
                    
                    if (event.type === 'agent_response') {
                        const agentType = event.is_ai ? '🤖 IA' : '👤 Agente';
                        timelineHtml += `
                            <div class="timeline-sla-item agent">
                                <div class="fs-8 text-muted">${timeStr}</div>
                                <div class="fs-7 fw-semibold text-primary">${agentType} respondeu</div>
                                ${event.content_preview ? `<div class="fs-8 text-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;">${event.content_preview}</div>` : ''}
                            </div>
                        `;
                    } else if (event.type === 'contact_message') {
                        const slaClass = event.sla_active ? 'sla-active' : '';
                        const slaIcon = event.sla_active ? '🔴 ' : '';
                        const minSince = event.minutes_since_agent ? `(${event.minutes_since_agent.toFixed(1)} min depois)` : '';
                        
                        timelineHtml += `
                            <div class="timeline-sla-item contact ${slaClass}">
                                <div class="fs-8 text-muted">${timeStr}</div>
                                <div class="fs-7 fw-semibold text-success">${slaIcon}Cliente enviou</div>
                                ${minSince ? `<div class="fs-8 text-muted">${minSince}</div>` : ''}
                                ${event.content_preview ? `<div class="fs-8 text-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;">${event.content_preview}</div>` : ''}
                            </div>
                        `;
                    }
                });
                
                timelineEl.innerHTML = timelineHtml || '<div class="text-muted fs-8">Nenhum evento ainda</div>';
            }
            
            // Badges informativos
            const badgesContainer = document.getElementById('sla-badges-container');
            if (badgesContainer) {
                let badgesHtml = '';
                
                if (sla.is_paused) {
                    badgesHtml += '<span class="badge badge-light-warning fs-8">⏸ Pausado</span>';
                }
                
                if (sla.warning_sent) {
                    badgesHtml += '<span class="badge badge-light-info fs-8">🔔 Alerta enviado</span>';
                }
                
                if (sla.reassignment_count > 0) {
                    badgesHtml += `<span class="badge badge-light-danger fs-8">🔄 ${sla.reassignment_count}x reatribuída</span>`;
                }
                
                if (sla.paused_duration > 0) {
                    badgesHtml += `<span class="badge badge-light-secondary fs-8">⏱ ${sla.paused_duration}min pausado</span>`;
                }
                
                if (!sla.should_start) {
                    badgesHtml += `<span class="badge badge-light-info fs-8">⏳ Delay de ${sla.delay_minutes}min</span>`;
                }
                
                badgesContainer.innerHTML = badgesHtml || '<span class="text-muted fs-8">Sem eventos especiais</span>';
            }
            
            // Auto-atualizar a cada 30 segundos se SLA estiver ativo
            if (sla.should_start && !sla.is_within_sla && sla.status !== 'closed') {
                setTimeout(() => {
                    if (window.currentConversationId === conversationId) {
                        window.loadConversationSLA(conversationId);
                    }
                }, 30000);
            }
        }
    })
    .catch(error => {
        console.error('❌ Erro ao carregar SLA:', error);
        if (loadingEl) {
            loadingEl.style.display = 'none';
        }
        if (contentEl) {
            contentEl.style.display = 'block';
            contentEl.innerHTML = `
                <div class="alert alert-danger d-flex align-items-center p-3">
                    <i class="ki-duotone ki-information fs-2 text-danger me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="fs-7">
                        <strong>Erro ao carregar SLA</strong><br>
                        <span class="text-muted">${error.message || 'Erro desconhecido'}</span>
                    </div>
                </div>
            `;
        }
    });
};

console.log('✅ Função loadConversationSLA registrada (definição imediata)');
console.log('🔍 URL do endpoint SLA:', '<?= \App\Helpers\Url::to('/conversations/sla-details') ?>');
</script>

<script>
// Definições reais carregadas cedo (substituem os stubs)
document.addEventListener('DOMContentLoaded', function() {
    // Mover conversa (funil/etapa)
    window.moveConversationStage = function() {
        const conversationId = window.currentConversationId || 0;
        if (!conversationId) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Selecione uma conversa primeiro' });
            return;
        }
        fetch('<?= \App\Helpers\Url::to("/funnels") ?>', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.funnels || data.funnels.length === 0) throw new Error('Nenhum funil disponível');
            const funnels = data.funnels;
            const funnelOptions = funnels.map(f => `<option value="${f.id}">${f.name}</option>`).join('');
            Swal.fire({
                title: 'Mover Conversa',
                html: `
                    <div class="mb-4">
                        <label class="form-label">Selecione o Funil:</label>
                        <select id="swal-funnel-select" class="form-select">
                            <option value="">Selecione...</option>
                            ${funnelOptions}
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Selecione a Etapa:</label>
                        <select id="swal-stage-select" class="form-select" disabled>
                            <option value="">Selecione um funil primeiro</option>
                        </select>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Mover',
                cancelButtonText: 'Cancelar',
                didOpen: () => {
                    const funnelSelect = document.getElementById('swal-funnel-select');
                    const stageSelect = document.getElementById('swal-stage-select');
                    funnelSelect.addEventListener('change', (e) => {
                        const funnelId = e.target.value;
                        if (!funnelId) {
                            stageSelect.disabled = true;
                            stageSelect.innerHTML = '<option value="">Selecione um funil primeiro</option>';
                            return;
                        }
                        fetch(`<?= \App\Helpers\Url::to("/funnels") ?>/${funnelId}/stages/json`, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success && data.stages) {
                                const stageOptions = data.stages.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
                                stageSelect.innerHTML = `<option value="">Selecione...</option>${stageOptions}`;
                                stageSelect.disabled = false;
                            }
                        })
                        .catch(() => {
                            stageSelect.innerHTML = '<option value="">Erro ao carregar etapas</option>';
                        });
                    });
                },
                preConfirm: () => {
                    const funnelId = document.getElementById('swal-funnel-select').value;
                    const stageId = document.getElementById('swal-stage-select').value;
                    if (!funnelId || !stageId) {
                        Swal.showValidationMessage('Selecione um funil e uma etapa');
                        return false;
                    }
                    return { funnelId, stageId };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const { stageId } = result.value;
                    const formData = new FormData();
                    formData.append('stage_id', stageId);
                    fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/move-stage`, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.text().then(text => {
                                console.error('Erro HTTP:', response.status, text);
                                throw new Error(`HTTP ${response.status}: ${text}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Conversa movida com sucesso', timer: 2000, showConfirmButton: false });
                            if (typeof selectConversation === 'function') selectConversation(conversationId);
                        } else {
                            throw new Error(data.message || 'Erro ao mover conversa');
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao mover conversa:', error);
                        Swal.fire({ icon: 'error', title: 'Erro', text: error.message });
                    });
                }
            });
        })
        .catch(error => {
            Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao carregar funis: ' + error.message });
        });
    };

    // Marcar como spam
    window.markAsSpam = function(conversationId) {
        const convId = conversationId || window.currentConversationId || 0;
        if (!convId) { alert('Nenhuma conversa selecionada'); return; }
        if (!confirm('Deseja realmente marcar esta conversa como spam? Esta ação não pode ser desfeita e a conversa será fechada automaticamente.')) return;
        fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${convId}/spam`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Conversa marcada como spam', timer: 2000, showConfirmButton: false })
                    .then(() => window.location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao marcar como spam: ' + (data.message || 'Erro desconhecido') });
            }
        })
        .catch(() => {
            Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao marcar como spam' });
        });
    };

    // Editar contato
    window.editContact = function(contactId) {
        const contactIdValue = contactId || window.currentConversation?.contact_id || 0;
        if (!contactIdValue) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Contato não encontrado' });
            return;
        }
        fetch(`<?= \App\Helpers\Url::to('/contacts') ?>/${contactIdValue}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(r => {
            if (!r.ok) throw new Error('Erro ao carregar contato');
            return r.json();
        })
        .then(data => {
            if (!data.success || !data.contact) throw new Error('Erro ao carregar dados do contato');
            const contact = data.contact;
            document.getElementById('editContactId').value = contact.id;
            document.getElementById('editContactName').value = contact.name || '';
            document.getElementById('editContactLastName').value = contact.last_name || '';
            document.getElementById('editContactEmail').value = contact.email || '';
            document.getElementById('editContactPhone').value = contact.phone || '';
            document.getElementById('editContactCity').value = contact.city || '';
            document.getElementById('editContactCountry').value = contact.country || '';
            document.getElementById('editContactCompany').value = contact.company || '';
            document.getElementById('editContactBio').value = contact.bio || '';
            const modal = new bootstrap.Modal(document.getElementById('kt_modal_edit_contact'));
            modal.show();
        })
        .catch(error => {
            Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao carregar dados do contato: ' + error.message });
        });
    };

    // Trocar departamento
    window.changeDepartment = function(conversationId) {
        const modal = new bootstrap.Modal(document.getElementById('kt_modal_change_department'));
        const conversationIdValue = conversationId || window.currentConversationId || 0;
        document.getElementById('changeDepartmentConversationId').value = conversationIdValue;
        const departmentNameEl = document.querySelector('[data-field="department_name"]');
        const currentDepartmentId = departmentNameEl?.dataset.departmentId || '';
        const selectEl = document.getElementById('changeDepartmentSelect');
        if (selectEl) selectEl.value = currentDepartmentId || '';
        modal.show();
    };

    // Fechar conversa
    window.closeConversation = function(conversationId) {
        if (!confirm('Deseja realmente encerrar esta conversa?')) return;
        fetch(`/conversations/${conversationId}/close`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) window.location.reload();
            else alert('Erro ao encerrar conversa: ' + (data.message || 'Erro desconhecido'));
        })
        .catch(() => alert('Erro ao encerrar conversa'));
    };

    // Reabrir conversa
    window.reopenConversation = function(conversationId) {
        if (!confirm('Deseja realmente reabrir esta conversa?')) return;
        fetch(`/conversations/${conversationId}/reopen`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) window.location.reload();
            else alert('Erro ao reabrir conversa: ' + (data.message || 'Erro desconhecido'));
        })
        .catch(() => alert('Erro ao reabrir conversa'));
    };
});
</script>
<style>
.conversation-sidebar {
    width: 0;
    max-width: 400px;
    border-left: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
    overflow: hidden;
    transition: width 0.3s ease;
    display: flex;
    flex-direction: column;
    position: relative;
    z-index: 100; /* Reduzido para não sobrepor dropdowns do header */
    flex-shrink: 0;
}

.conversation-sidebar.open {
    width: 400px !important;
}

.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid var(--bs-border-color);
    flex-shrink: 0;
}

.sidebar-content {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
}

.sidebar-section {
    margin-bottom: 25px;
}

/* Estilos SLA Timeline */
.timeline-sla {
    position: relative;
    padding-left: 20px;
    max-height: 200px;
    overflow-y: auto;
}

.timeline-sla-item {
    position: relative;
    padding: 8px 0;
    border-left: 2px solid #e4e6ef;
    padding-left: 15px;
    margin-bottom: 8px;
}

.timeline-sla-item:last-child {
    margin-bottom: 0;
}

.timeline-sla-item::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 12px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #e4e6ef;
}

.timeline-sla-item.agent::before {
    background: #009ef7;
}

.timeline-sla-item.contact::before {
    background: #50cd89;
}

.timeline-sla-item.contact.sla-active::before {
    background: #f1416c;
    box-shadow: 0 0 0 3px rgba(241, 65, 108, 0.2);
    animation: pulse-sla 2s infinite;
}

@keyframes pulse-sla {
    0%, 100% {
        box-shadow: 0 0 0 3px rgba(241, 65, 108, 0.2);
    }
    50% {
        box-shadow: 0 0 0 6px rgba(241, 65, 108, 0.1);
    }
}

.sidebar-section-title {
    font-weight: 600;
    font-size: 13px;
    color: var(--bs-text-dark);
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.sidebar-info-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 13px;
}

.sidebar-info-label {
    color: var(--bs-text-gray-700);
}

.sidebar-info-value {
    color: var(--bs-text-dark);
    font-weight: 500;
}

.sidebar-content::-webkit-scrollbar {
    width: 6px;
}

.sidebar-content::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-content::-webkit-scrollbar-thumb {
    background: var(--bs-gray-300);
    border-radius: 3px;
}

.sidebar-content::-webkit-scrollbar-thumb:hover {
    background: var(--bs-gray-400);
}
</style>

<div class="conversation-sidebar" id="conversationSidebar">
    
    <!-- Header do Sidebar (sempre presente) -->
    <div class="sidebar-header">
        <!-- Botão Voltar (Mobile/Tablet) -->
        <button class="btn btn-sm btn-icon btn-light sidebar-back-btn d-none" onclick="closeConversationSidebar()" title="Voltar" id="sidebarBackBtn">
            <i class="ki-duotone ki-arrow-left fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
        </button>
        <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x border-transparent fs-7 fw-bold">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#kt_tab_details">Detalhes</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#kt_tab_timeline">Timeline</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#kt_tab_history">Histórico</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#kt_tab_orders">Pedidos</a>
            </li>
        </ul>
    </div>
    
    <!-- Conteúdo do Sidebar (sempre presente, preenchido via JS quando necessário) -->
    <div class="sidebar-content">
        <div class="tab-content">
            
            <!-- ABA: DETALHES -->
            <div class="tab-pane fade show active" id="kt_tab_details">
                
                <!-- Informações do Contato -->
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Contato</div>
                    
                    <div class="text-center mb-5">
                        <div class="symbol symbol-100px symbol-circle mb-3">
                            <div class="symbol-label bg-light-primary text-primary fs-1 fw-bold" id="sidebar-contact-initials">NN</div>
                        </div>
                        <div class="fs-5 fw-bold text-gray-800" data-field="contact_name">-</div>
                        <div class="fs-7 text-muted" data-field="contact_phone">-</div>
                    </div>
                    
                    <div class="sidebar-info-item">
                        <span class="sidebar-info-label">Email:</span>
                        <span class="sidebar-info-value" data-field="contact_email">-</span>
                    </div>
                    
                    <div class="sidebar-info-item">
                        <span class="sidebar-info-label">Telefone:</span>
                        <span class="sidebar-info-value" data-field="contact_phone">-</span>
                    </div>
                    
                    <button class="btn btn-sm btn-light-primary w-100 mt-3" id="sidebar-edit-contact-btn" style="display: none;" onclick="editContact(0)">
                        <i class="ki-duotone ki-pencil fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Editar Contato
                    </button>
                </div>
                
                <div class="separator my-5"></div>
                
                <!-- Funil e Etapa -->
                <div class="sidebar-section" id="sidebar-funnel-stage-section" style="display: none;">
                <div class="sidebar-section-title d-flex justify-content-between align-items-center">
                    <span>🎯 Funil e Etapa</span>
                    <button class="btn btn-sm btn-icon btn-light-primary p-0" 
                            id="sidebar-move-stage-btn" 
                            title="Mover conversa"
                            onclick="console.log('🖱️ Click inline detectado'); if (typeof window.moveConversationStage === 'function') { window.moveConversationStage(); } else { console.error('Função não existe:', typeof window.moveConversationStage); }">
                        <i class="ki-duotone ki-arrows-circle fs-6">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                </div>
                    
                    <!-- Card com cor da etapa -->
                    <div class="card border border-gray-300 mb-3" id="sidebar-funnel-card">
                        <div class="card-body p-4" style="border-left: 5px solid #009ef7;">
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-grow-1">
                                    <div class="fs-7 text-muted mb-1">Funil Atual</div>
                                    <div class="fs-6 fw-bold text-gray-800" data-field="funnel_name">-</div>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="fs-7 text-muted mb-1">Etapa Atual</div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge badge-primary" id="sidebar-stage-badge" data-field="stage_name">-</span>
                                        <span class="fs-8 text-muted" id="sidebar-stage-time">⏱️ -</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Histórico de movimentação (futuro) -->
                    <!-- 
                    <div class="alert alert-info d-flex align-items-start p-3 mb-0" style="font-size: 0.75rem;">
                        <i class="ki-duotone ki-information-5 fs-6 me-2 mt-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div>
                            <div class="fw-semibold mb-1">Última Movimentação</div>
                            <div>Nova Entrada → Em Atendimento (há 2h)</div>
                        </div>
                    </div>
                    -->
                </div>
                
                <div class="separator my-5" id="sidebar-funnel-separator" style="display: none;"></div>
                
                <!-- Agentes do Contato -->
                <div class="sidebar-section">
                    <div class="sidebar-section-title d-flex justify-content-between align-items-center">
                        <span>Agentes do Contato</span>
                        <button class="btn btn-sm btn-icon btn-light-primary p-0" id="sidebar-manage-contact-agents-btn" style="display: none;" onclick="manageContactAgents(0)" title="Gerenciar agentes">
                            <i class="ki-duotone ki-setting-3 fs-6">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </button>
                    </div>
                    
                    <div id="contact-agents-list" class="mb-3">
                        <div class="text-muted fs-7">Nenhum agente atribuído</div>
                    </div>
                    
                    <div class="alert alert-info d-flex align-items-start p-3 mb-0" style="font-size: 0.75rem;">
                        <i class="ki-duotone ki-information-5 fs-6 me-2 mt-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div>
                            <div class="fw-semibold mb-1">Atribuição Automática</div>
                            <div>Quando uma conversa fechada for reaberta ou o contato chamar novamente, será atribuído automaticamente ao agente principal.</div>
                        </div>
                    </div>
                </div>
                
                <div class="separator my-5"></div>
                
                <!-- Agente de IA -->
                <div class="sidebar-section" id="sidebar-ai-agent-section">
                    <div class="sidebar-section-title d-flex justify-content-between align-items-center">
                        <span>🤖 Agente de IA</span>
                    </div>
                    
                    <!-- Status da IA (será preenchido via JS) -->
                    <div id="sidebar-ai-status">
                        <div class="text-muted fs-7">Carregando...</div>
                    </div>
                    
                    <!-- Botões de ação (serão mostrados/ocultados via JS) -->
                    <div id="sidebar-ai-actions" class="mt-3" style="display: none;">
                        <button class="btn btn-sm btn-light-info w-100 mb-2" id="sidebar-ai-history-btn" onclick="showAIHistory()">
                            <i class="ki-duotone ki-history fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Ver Histórico
                        </button>
                        <button class="btn btn-sm btn-light-danger w-100" id="sidebar-ai-remove-btn" onclick="removeAIAgent()">
                            <i class="ki-duotone ki-cross fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Remover IA
                        </button>
                    </div>
                    
                    <!-- Botão para adicionar IA (quando não tem IA) -->
                    <div id="sidebar-ai-add-section" class="mt-3" style="display: none;">
                        <button class="btn btn-sm btn-light-primary w-100" id="sidebar-ai-add-btn" onclick="showAddAIAgentModal()">
                            <i class="ki-duotone ki-plus fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            Adicionar Agente de IA
                        </button>
                    </div>
                </div>
                
                <div class="separator my-5"></div>

                <!-- Automação -->
                <div class="sidebar-section" id="sidebar-automation-section">
                    <div class="sidebar-section-title d-flex justify-content-between align-items-center">
                        <span>⚙️ Automação</span>
                    </div>
                    <div id="sidebar-automation-status">
                        <div class="text-muted fs-7">Carregando...</div>
                    </div>
                </div>

                <div class="separator my-5"></div>
                
                <!-- SLA da Conversa -->
                <div class="sidebar-section" id="sidebar-sla-section">
                    <div class="sidebar-section-title d-flex justify-content-between align-items-center">
                        <span>
                            <i class="ki-duotone ki-timer text-primary fs-5 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            SLA
                        </span>
                        <span class="badge badge-sm" id="sla-status-badge">-</span>
                    </div>
                    
                    <div id="sla-loading" class="text-center py-5">
                        <span class="spinner-border spinner-border-sm text-primary"></span>
                    </div>
                    
                    <div id="sla-content" style="display: none;">
                        <!-- Barra de progresso principal -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fs-7 text-muted">Tempo Decorrido</span>
                                <span class="fs-6 fw-bold" id="sla-elapsed-time">-</span>
                            </div>
                            <div class="progress" style="height: 12px; border-radius: 6px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                     id="sla-progress-bar" 
                                     role="progressbar" 
                                     style="width: 0%;">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <span class="fs-8 text-muted">Meta: <span id="sla-target">-</span></span>
                                <span class="fs-8" id="sla-percentage">0%</span>
                            </div>
                        </div>
                        
                        <!-- Detalhes -->
                        <div class="card bg-light mb-3">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fs-7 text-muted">Regra aplicada:</span>
                                    <span class="fs-7 fw-semibold" id="sla-rule-name">-</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fs-7 text-muted">Início SLA:</span>
                                    <span class="fs-7" id="sla-start-time">-</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center" id="sla-exceeded-container" style="display: none;">
                                    <span class="fs-7 text-muted">Excedido em:</span>
                                    <span class="fs-7 fw-bold text-danger" id="sla-exceeded-by">-</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Timeline de eventos -->
                        <div class="mb-3">
                            <div class="fs-7 fw-semibold text-gray-700 mb-2">Timeline de Eventos:</div>
                            <div id="sla-timeline" class="timeline-sla">
                                <!-- Timeline será carregada aqui -->
                            </div>
                        </div>
                        
                        <!-- Badges informativos -->
                        <div class="d-flex flex-wrap gap-2" id="sla-badges-container">
                            <!-- Badges serão carregados aqui -->
                        </div>
                    </div>
                </div>

                <div class="separator my-5"></div>
                
                <!-- Informações da Conversa -->
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Conversa</div>
                    
                    <div class="sidebar-info-item">
                        <span class="sidebar-info-label">Status:</span>
                        <span class="sidebar-info-value" data-field="status">-</span>
                        <span class="badge badge-danger ms-2" id="sidebar-spam-badge" style="display: none;">🚫 SPAM</span>
                    </div>
                    
                    <!-- Sentimento -->
                    <div class="sidebar-info-item" id="sentiment-info" style="display: none;">
                        <span class="sidebar-info-label">Sentimento:</span>
                        <span class="sidebar-info-value" id="sentiment-label">-</span>
                        <div class="mt-2">
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar" id="sentiment-progress" role="progressbar" style="width: 50%;"></div>
                            </div>
                            <div class="fs-8 text-muted mt-1" id="sentiment-score">Score: -</div>
                        </div>
                    </div>
                    
                    <!-- Performance do Agente -->
                    <div class="sidebar-info-item" id="agent-performance-info" style="display: none;">
                        <span class="sidebar-info-label">
                            <i class="ki-duotone ki-chart-line-up fs-5 text-primary me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Performance:
                        </span>
                        <div class="mt-2">
                            <!-- Estado: Analisado -->
                            <div id="performance-analyzed-state" style="display: none;">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="fs-7 text-muted">Nota Geral:</span>
                                    <span class="badge badge-lg" id="performance-overall-badge">-</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar" id="performance-progress" role="progressbar" style="width: 0%;"></div>
                                </div>
                                <div class="fs-8 text-muted mt-2" id="performance-details"></div>
                                <a href="#" id="performance-view-link" class="btn btn-sm btn-light-primary w-100 mt-2">
                                    <i class="ki-duotone ki-eye fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Ver Análise Completa
                                </a>
                            </div>
                            
                            <!-- Estado: Aguardando Análise -->
                            <div id="performance-pending-state" style="display: none;">
                                <div class="d-flex align-items-center justify-content-center py-3">
                                    <div class="text-center">
                                        <i class="ki-duotone ki-timer fs-3x text-warning mb-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <div class="fs-7 text-muted">Aguardando análise</div>
                                        <div class="fs-8 text-muted mt-1" id="performance-pending-reason">
                                            A análise será processada em breve
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="sidebar-info-item">
                        <span class="sidebar-info-label">Canal:</span>
                        <span class="sidebar-info-value" data-field="channel">-</span>
                    </div>

                    <!-- Informações WhatsApp (mostrar apenas se canal for WhatsApp) -->
                    <div class="sidebar-info-item" id="sidebar-whatsapp-info" style="display: none;">
                        <span class="sidebar-info-label">Integração:</span>
                        <span class="sidebar-info-value" data-field="whatsapp_account_name">-</span>
                    </div>
                    
                    <div class="sidebar-info-item" id="sidebar-whatsapp-phone" style="display: none;">
                        <span class="sidebar-info-label">Número WhatsApp:</span>
                        <span class="sidebar-info-value" data-field="whatsapp_account_phone">-</span>
                    </div>
                    
                    <div class="sidebar-info-item" id="sidebar-department-item" style="display: none;">
                        <span class="sidebar-info-label">Setor:</span>
                        <span class="sidebar-info-value" data-field="department_name">-</span>
                    </div>
                    
                    <div class="sidebar-info-item">
                        <span class="sidebar-info-label">Agente:</span>
                        <span class="sidebar-info-value" data-field="agent_name">Não atribuído</span>
                    </div>
                    
                    <div class="sidebar-info-item">
                        <span class="sidebar-info-label">Criada em:</span>
                        <span class="sidebar-info-value" data-field="created_at">-</span>
                    </div>
                </div>
                
                <div class="separator my-5"></div>
                
                <!-- Ações Rápidas -->
                <div class="sidebar-section" id="sidebar-quick-actions-section" style="display:none;">
                    <div class="sidebar-section-title">Ações Rápidas</div>
                    <div id="sidebar-action-buttons" class="d-flex flex-column gap-2">
                        <div class="text-muted fs-7">Carregando ações...</div>
                    </div>
                </div>
                
                <div class="separator my-5"></div>
                
                <!-- Participantes -->
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Participantes</div>
                    
                    <div class="participants-list d-flex flex-wrap gap-2 mb-3" id="participants-list">
                        <div class="text-muted fs-7">Carregando...</div>
                    </div>
                    
                    <button class="btn btn-sm btn-light-primary w-100" id="sidebar-add-participant-btn" style="display: none;" onclick="showAddParticipantModal()">
                        <i class="ki-duotone ki-plus fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Adicionar Participante
                    </button>
                    
                    <button class="btn btn-sm btn-light-danger w-100 mt-2" id="sidebar-leave-conversation-btn" style="display: none;" onclick="leaveConversation()">
                        <i class="ki-duotone ki-exit-right-corner fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Sair da Conversa
                    </button>
                </div>
                
                <div class="separator my-5"></div>
                
                <!-- Tags -->
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Tags</div>
                    
                    <div class="conversation-tags-list d-flex flex-wrap gap-2 mb-3">
                        <div class="text-muted fs-7">Nenhuma tag</div>
                    </div>
                    
                    <button class="btn btn-sm btn-light-primary w-100" id="sidebar-manage-tags-btn" style="display: none;" onclick="manageTags(0)">
                        <i class="ki-duotone ki-plus fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Gerenciar Tags
                    </button>
                </div>
                
                <div class="separator my-5"></div>
                
                <!-- Ações -->
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Ações</div>
                    
                    <button class="btn btn-sm btn-light-warning w-100 mb-2" id="sidebar-escalate-btn" style="display: none;" onclick="escalateFromAI(0)">
                        <i class="ki-duotone ki-arrow-up fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Escalar para Humano
                    </button>
                    
                    <button class="btn btn-sm btn-light-primary w-100 mb-2" id="sidebar-assign-btn" style="display: none;" onclick="assignConversation(0)">
                        <i class="ki-duotone ki-user-tick fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Atribuir/Reatribuir
                    </button>
                    
                    <button class="btn btn-sm btn-light-primary w-100 mb-2" id="sidebar-department-btn" style="display: none;" onclick="changeDepartment(0)">
                        <i class="ki-duotone ki-arrows-circle fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Mudar Setor
                    </button>
                    
                    <button class="btn btn-sm btn-light-success w-100 mb-2" id="sidebar-close-btn" style="display: none;" onclick="closeConversation(0)">
                        <i class="ki-duotone ki-check-circle fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Encerrar Conversa
                    </button>
                    
                    <button class="btn btn-sm btn-light-info w-100 mb-2" id="sidebar-reopen-btn" style="display: none;" onclick="reopenConversation(0)">
                        <i class="ki-duotone ki-entrance-right fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Reabrir Conversa
                    </button>
                    
                    <button class="btn btn-sm btn-light-danger w-100" id="sidebar-spam-btn" style="display: none;" onclick="markAsSpam(0)">
                        <i class="ki-duotone ki-shield-cross fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Marcar como Spam
                    </button>
                </div>
                
            </div>
            
            <!-- ABA: TIMELINE -->
            <div class="tab-pane fade" id="kt_tab_timeline">
                
                <div class="timeline timeline-border-dashed">
                    <!-- Timeline será preenchida via JS quando necessário -->
                </div>
                
                <!-- Adicionar nota -->
                <div class="separator my-5"></div>
                
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Nova Nota Interna</div>
                    <textarea class="form-control form-control-sm mb-3" id="newNoteText" rows="3" placeholder="Digite sua nota..."></textarea>
                    <button class="btn btn-sm btn-primary w-100" id="sidebar-add-note-btn" style="display: none;" onclick="addNote(0)">
                        <i class="ki-duotone ki-plus fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Adicionar Nota
                    </button>
                </div>
                
            </div>
            
            <!-- ABA: HISTÓRICO -->
            <div class="tab-pane fade" id="kt_tab_history">
                
                <!-- Estatísticas -->
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Estatísticas do Contato</div>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <div class="text-center">
                            <div class="fs-2 fw-bold text-primary" id="history-conversations-count">-</div>
                            <div class="fs-7 text-muted">Conversas</div>
                        </div>
                        <div class="text-center">
                            <div class="fs-2 fw-bold text-success" id="history-avg-time">-</div>
                            <div class="fs-7 text-muted">Tempo Médio</div>
                        </div>
                        <div class="text-center">
                            <div class="fs-2 fw-bold text-warning" id="history-satisfaction">-</div>
                            <div class="fs-7 text-muted">Satisfação</div>
                        </div>
                    </div>
                </div>
                
                <div class="separator my-5"></div>
                
                <!-- Conversas anteriores -->
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Conversas Anteriores</div>
                    
                    <div id="history-previous-conversations" class="text-center py-5">
                        <p class="text-muted fs-7">Nenhuma conversa anterior</p>
                    </div>
                </div>
                
            </div>
            
            <!-- ABA: PEDIDOS WOOCOMMERCE -->
            <div class="tab-pane fade" id="kt_tab_orders">
                
                <!-- Filtros -->
                <div class="sidebar-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="sidebar-section-title">Pedidos WooCommerce</div>
                        <button class="btn btn-sm btn-icon btn-light-primary" id="btn-refresh-woocommerce-orders" title="Atualizar">
                            <i class="ki-duotone ki-arrows-loop fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </button>
                    </div>
                    
                    <div class="d-flex flex-column gap-2 mb-3">
                        <select class="form-select form-select-sm" id="woocommerce-integration-filter">
                            <option value="">Todas as lojas</option>
                        </select>
                        <select class="form-select form-select-sm" id="woocommerce-status-filter">
                            <option value="">Todos os status</option>
                            <option value="pending">Pendente</option>
                            <option value="processing">Processando</option>
                            <option value="on-hold">Em espera</option>
                            <option value="completed">Concluído</option>
                            <option value="cancelled">Cancelado</option>
                            <option value="refunded">Reembolsado</option>
                            <option value="failed">Falhou</option>
                        </select>
                    </div>
                </div>
                
                <div class="separator my-3"></div>
                
                <!-- Lista de pedidos -->
                <div class="sidebar-section">
                    <div id="woocommerce-orders-list" class="text-center py-5">
                        <div class="text-muted fs-7">Clique na aba para carregar pedidos</div>
                    </div>
                </div>
                
            </div>
            
        </div>
    </div>
    
</div>

<script>
console.log('📋📋📋 SIDEBAR-CONVERSATION.PHP INICIANDO... 📋📋📋');
console.log('📋 sidebar-conversation.php carregado');

// ============================================================================
// DEFINIR FUNÇÃO moveConversationStage IMEDIATAMENTE (antes de tudo)
// ============================================================================
window.moveConversationStage = function() {
    console.log('✅ moveConversationStage chamada!');
    console.log('📊 currentConversationId:', window.currentConversationId);
    
    const conversationId = window.currentConversationId || 0;
    if (!conversationId) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma conversa primeiro'
        });
        return;
    }
    
    // Carregar funis e etapas
    fetch('<?= \App\Helpers\Url::to("/funnels") ?>', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success || !data.funnels || data.funnels.length === 0) {
            throw new Error('Nenhum funil disponível');
        }
        
        const funnels = data.funnels;
        const funnelOptions = funnels.map(f => `<option value="${f.id}">${f.name}</option>`).join('');
        
        Swal.fire({
            title: 'Mover Conversa',
            html: `
                <div class="mb-4">
                    <label class="form-label">Selecione o Funil:</label>
                    <select id="swal-funnel-select" class="form-select">
                        <option value="">Selecione...</option>
                        ${funnelOptions}
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label">Selecione a Etapa:</label>
                    <select id="swal-stage-select" class="form-select" disabled>
                        <option value="">Selecione um funil primeiro</option>
                    </select>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Mover',
            cancelButtonText: 'Cancelar',
            didOpen: () => {
                const funnelSelect = document.getElementById('swal-funnel-select');
                const stageSelect = document.getElementById('swal-stage-select');
                
                funnelSelect.addEventListener('change', (e) => {
                    const funnelId = e.target.value;
                    if (!funnelId) {
                        stageSelect.disabled = true;
                        stageSelect.innerHTML = '<option value="">Selecione um funil primeiro</option>';
                        return;
                    }
                    
                    // Carregar etapas do funil
                    fetch(`<?= \App\Helpers\Url::to("/funnels") ?>/${funnelId}/stages/json`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.stages) {
                            const stageOptions = data.stages.map(s => 
                                `<option value="${s.id}">${s.name}</option>`
                            ).join('');
                            stageSelect.innerHTML = `<option value="">Selecione...</option>${stageOptions}`;
                            stageSelect.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao carregar etapas:', error);
                        stageSelect.innerHTML = '<option value="">Erro ao carregar etapas</option>';
                    });
                });
            },
            preConfirm: () => {
                const funnelId = document.getElementById('swal-funnel-select').value;
                const stageId = document.getElementById('swal-stage-select').value;
                
                if (!funnelId || !stageId) {
                    Swal.showValidationMessage('Selecione um funil e uma etapa');
                    return false;
                }
                
                return { funnelId, stageId };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const { funnelId, stageId } = result.value;
                
                // Fazer requisição para mover conversa
                const formData = new FormData();
                formData.append('stage_id', stageId);
                
                fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/move-stage`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('Erro HTTP:', response.status, text);
                            throw new Error(`HTTP ${response.status}: ${text.substring(0, 200)}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: 'Conversa movida com sucesso',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        
                        // Recarregar conversa
                        if (typeof selectConversation === 'function') {
                            selectConversation(conversationId);
                        }
                    } else {
                        throw new Error(data.message || 'Erro ao mover conversa');
                    }
                })
                .catch(error => {
                    console.error('Erro ao mover conversa (2):', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: error.message
                    });
                });
            }
        });
    })
    .catch(error => {
        console.error('Erro ao carregar funis:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao carregar funis: ' + error.message
        });
    });
};

console.log('✅ window.moveConversationStage definida IMEDIATAMENTE!');
console.log('🔍 Tipo:', typeof window.moveConversationStage);

// ============================================================================
// DEFINIR FUNÇÃO markAsSpam IMEDIATAMENTE (antes de tudo)
// ============================================================================
window.markAsSpam = function(conversationId) {
    console.log('🚫 markAsSpam chamada! conversationId:', conversationId);
    
    const convId = conversationId || window.currentConversationId || 0;
    if (!convId) {
        alert('Nenhuma conversa selecionada');
        return;
    }
    
    if (!confirm('Deseja realmente marcar esta conversa como spam? Esta ação não pode ser desfeita e a conversa será fechada automaticamente.')) {
        return;
    }
    
    fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${convId}/spam`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: 'Conversa marcada como spam',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Erro ao marcar como spam: ' + (data.message || 'Erro desconhecido')
            });
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao marcar como spam'
        });
    });
};

console.log('✅ window.markAsSpam definida IMEDIATAMENTE!');

// ============================================================================
// DEFINIR STUBS DE TODAS AS FUNÇÕES DO SIDEBAR (serão sobrescritas depois)
// ============================================================================
window.editContact = window.editContact || function(id) { console.log('editContact:', id); };
window.manageContactAgents = window.manageContactAgents || function(id) { console.log('manageContactAgents:', id); };
window.showAIHistory = window.showAIHistory || function() { console.log('showAIHistory'); };
window.removeAIAgent = window.removeAIAgent || function() { console.log('removeAIAgent'); };
window.showAddAIAgentModal = window.showAddAIAgentModal || function() { console.log('showAddAIAgentModal'); };
window.showAddParticipantModal = window.showAddParticipantModal || function() { console.log('showAddParticipantModal'); };
window.manageTags = window.manageTags || function(id) { console.log('manageTags:', id); };
window.escalateFromAI = window.escalateFromAI || function(id) { console.log('escalateFromAI:', id); };
window.assignConversation = window.assignConversation || function(id) { console.log('assignConversation:', id); };
window.changeDepartment = window.changeDepartment || function(id) { console.log('changeDepartment:', id); };
window.closeConversation = window.closeConversation || function(id) { console.log('closeConversation:', id); };
window.reopenConversation = window.reopenConversation || function(id) { console.log('reopenConversation:', id); };
window.addNote = window.addNote || function(id) { console.log('addNote:', id); };

console.log('✅ Stubs de funções do sidebar definidos!');

// Expor função globalmente para debug e uso direto
window.debugMoveStage = function() {
    console.log('🐛 DEBUG: Verificando estado do botão e função');
    console.log('Botão existe:', !!document.getElementById('sidebar-move-stage-btn'));
    console.log('Função existe:', typeof window.moveConversationStage);
    console.log('currentConversationId:', window.currentConversationId);
    if (typeof window.moveConversationStage === 'function') {
        window.moveConversationStage();
    }
};

// Funções de ação do sidebar
window.editContact = function(contactId) {
    const contactIdValue = contactId || window.currentConversation?.contact_id || 0;
    if (!contactIdValue) {
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                           document.body.classList.contains('dark-mode') ||
                           window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Contato não encontrado',
            colorScheme: isDarkMode ? 'dark' : 'light'
        });
        return;
    }
    
    // Carregar dados do contato
    fetch(`<?= \App\Helpers\Url::to('/contacts') ?>/${contactIdValue}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Erro ao carregar contato');
        }
        return response.json();
    })
    .then(data => {
        if (!data.success || !data.contact) {
            throw new Error('Erro ao carregar dados do contato');
        }
        
        const contact = data.contact;
        
        // Preencher formulário
        document.getElementById('editContactId').value = contact.id;
        document.getElementById('editContactName').value = contact.name || '';
        document.getElementById('editContactLastName').value = contact.last_name || '';
        document.getElementById('editContactEmail').value = contact.email || '';
        document.getElementById('editContactPhone').value = contact.phone || '';
        document.getElementById('editContactCity').value = contact.city || '';
        document.getElementById('editContactCountry').value = contact.country || '';
        document.getElementById('editContactCompany').value = contact.company || '';
        document.getElementById('editContactBio').value = contact.bio || '';
        
        // Abrir modal
        const modal = new bootstrap.Modal(document.getElementById('kt_modal_edit_contact'));
        modal.show();
    })
    .catch(error => {
        console.error('Erro ao carregar contato:', error);
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                           document.body.classList.contains('dark-mode') ||
                           window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao carregar dados do contato: ' + error.message,
            colorScheme: isDarkMode ? 'dark' : 'light'
        });
    });
};

window.assignConversation = function(conversationId) {
    // TODO: Implementar modal de atribuição
    alert('Modal de atribuição em desenvolvimento');
};

window.changeDepartment = function(conversationId) {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_change_department'));
    const conversationIdValue = conversationId || window.currentConversationId || 0;
    document.getElementById('changeDepartmentConversationId').value = conversationIdValue;
    
    // Carregar setor atual da conversa do sidebar
    const departmentNameEl = document.querySelector('[data-field="department_name"]');
    const currentDepartmentId = departmentNameEl?.dataset.departmentId || '';
    
    const selectEl = document.getElementById('changeDepartmentSelect');
    if (selectEl) {
        selectEl.value = currentDepartmentId || '';
    }
    
    modal.show();
};

window.manageTags = function(conversationId) {
    // TODO: Implementar modal de gerenciamento de tags
    alert('Modal de tags em desenvolvimento');
};

window.closeConversation = function(conversationId) {
    if (!confirm('Deseja realmente encerrar esta conversa?')) {
        return;
    }
    
    fetch(`/conversations/${conversationId}/close`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Erro ao encerrar conversa: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao encerrar conversa');
    });
};

window.reopenConversation = function(conversationId) {
    if (!confirm('Deseja realmente reabrir esta conversa?')) {
        return;
    }
    
    fetch(`/conversations/${conversationId}/reopen`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Erro ao reabrir conversa: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao reabrir conversa');
    });
};

window.addNote = function(conversationId) {
    const noteText = document.getElementById('newNoteText');
    const content = noteText?.value.trim() || '';
    
    if (!content) {
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                           document.body.classList.contains('dark-mode') ||
                           window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Digite uma nota antes de salvar',
            colorScheme: isDarkMode ? 'dark' : 'light'
        });
        return;
    }
    
    const conversationIdValue = conversationId || window.currentConversationId || 0;
    const btn = document.getElementById('sidebar-add-note-btn');
    const originalText = btn?.innerHTML || '';
    
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
    }
    
    const formData = new FormData();
    formData.append('content', content);
    formData.append('is_private', '0'); // Por padrão, notas são públicas
    
    fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationIdValue}/notes`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Limpar campo de texto
            if (noteText) {
                noteText.value = '';
            }
            
            const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                               document.body.classList.contains('dark-mode') ||
                               window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: data.message || 'Nota adicionada com sucesso',
                colorScheme: isDarkMode ? 'dark' : 'light',
                timer: 2000,
                showConfirmButton: false
            });
            
            // Recarregar timeline
            if (typeof loadTimeline === 'function') {
                loadTimeline(conversationIdValue);
            }
        } else {
            throw new Error(data.message || 'Erro ao adicionar nota');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                           document.body.classList.contains('dark-mode') ||
                           window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message || 'Erro ao adicionar nota',
            colorScheme: isDarkMode ? 'dark' : 'light'
        });
    })
    .finally(() => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
};

// ============================================================================
// FUNÇÕES DE GERENCIAMENTO DE AGENTES DE IA
// ============================================================================

/**
 * Carregar status da IA na conversa
 */
window.loadAIAgentStatus = function(conversationId) {
    console.log('loadAIAgentStatus chamado com conversationId:', conversationId);
    
    if (!conversationId) {
        console.warn('loadAIAgentStatus: conversationId não fornecido');
        updateAIAgentSidebar({ has_ai: false });
        return;
    }
    
    // Mostrar estado de carregamento
    const statusDiv = document.getElementById('sidebar-ai-status');
    if (statusDiv) {
        statusDiv.innerHTML = '<div class="text-muted fs-7">Carregando...</div>';
    } else {
        console.error('Elemento sidebar-ai-status não encontrado!');
    }
    
    const url = `<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/ai-status`;
    console.log('Fazendo requisição para:', url);
    
    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        console.log('Resposta recebida:', response.status, response.statusText);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Dados recebidos:', data);
        if (data.success) {
            updateAIAgentSidebar(data.data);
            if (typeof updateAIActiveBanner === 'function') {
                updateAIActiveBanner(data.data, conversationId);
            }
        } else {
            console.error('Erro ao carregar status da IA:', data.message);
            updateAIAgentSidebar({ has_ai: false });
            if (typeof updateAIActiveBanner === 'function') {
                updateAIActiveBanner({ has_ai: false }, conversationId);
            }
        }
    })
    .catch(error => {
        console.error('Erro ao carregar status da IA:', error);
        updateAIAgentSidebar({ has_ai: false });
        if (typeof updateAIActiveBanner === 'function') {
            updateAIActiveBanner({ has_ai: false }, conversationId);
        }
    });
};

/**
 * Atualizar sidebar com status da automação
 */
window.updateAutomationSidebar = function(data) {
    const statusDiv = document.getElementById('sidebar-automation-status');
    if (!statusDiv) {
        console.error('sidebar-automation-status não encontrado');
        return;
    }
    
    if (!data || !data.has_automation || !data.automation) {
        statusDiv.innerHTML = '<div class="text-muted fs-7">Nenhuma automação ativa</div>';
        return;
    }
    
    const automation = data.automation;
    const execStatus = automation.execution_status || 'unknown';
    const autoStatus = automation.automation_status || 'inactive';
    const lastExec = automation.last_execution_at ? formatTime(automation.last_execution_at) : '—';
    
    statusDiv.innerHTML = `
        <div class="d-flex flex-column gap-1">
            <div class="d-flex align-items-center gap-2">
                <span class="badge ${autoStatus === 'active' ? 'badge-success' : 'badge-light'}">${autoStatus === 'active' ? 'Ativa' : 'Inativa'}</span>
                ${automation.trigger_type ? `<span class="badge badge-light">${escapeHtml(automation.trigger_type)}</span>` : ''}
            </div>
            <div class="fw-semibold">${escapeHtml(automation.name || 'Automação')}</div>
            <div class="text-muted fs-8">Execução: ${escapeHtml(execStatus)}</div>
            <div class="text-muted fs-8">Última: ${lastExec}</div>
        </div>
    `;
};

/**
 * Carregar status da automação
 */
window.loadAutomationStatus = function(conversationId) {
    if (!conversationId) {
        updateAutomationSidebar({ has_automation: false });
        return;
    }
    
    const statusDiv = document.getElementById('sidebar-automation-status');
    if (statusDiv) {
        statusDiv.innerHTML = '<div class="text-muted fs-7">Carregando...</div>';
    }
    
    const url = `<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/automation-status`;
    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateAutomationSidebar(data.data);
        } else {
            updateAutomationSidebar({ has_automation: false });
        }
    })
    .catch(() => {
        updateAutomationSidebar({ has_automation: false });
    });
};
console.log('✅ loadAIAgentStatus definida:', typeof window.loadAIAgentStatus);

/**
 * Atualizar sidebar com status da IA
 */
window.updateAIAgentSidebar = function(status) {
    console.log('updateAIAgentSidebar chamado com status:', status);
    
    const section = document.getElementById('sidebar-ai-agent-section');
    const statusDiv = document.getElementById('sidebar-ai-status');
    const actionsDiv = document.getElementById('sidebar-ai-actions');
    const addSection = document.getElementById('sidebar-ai-add-section');
    
    console.log('Elementos encontrados:', {
        section: !!section,
        statusDiv: !!statusDiv,
        actionsDiv: !!actionsDiv,
        addSection: !!addSection
    });
    
    if (!statusDiv) {
        console.error('updateAIAgentSidebar: statusDiv não encontrado!');
        return;
    }
    
    if (status.has_ai && status.ai_agent) {
        // Tem IA ativa
        const aiAgent = status.ai_agent;
        const aiConv = status.ai_conversation;
        
        // Calcular tempo desde última interação
        let lastInteractionText = 'Nunca';
        if (aiConv.last_interaction) {
            const lastInteraction = new Date(aiConv.last_interaction);
            const now = new Date();
            const diffMs = now - lastInteraction;
            const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
            const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
            
            if (diffHours > 0) {
                lastInteractionText = `Há ${diffHours}h`;
            } else if (diffMinutes > 0) {
                lastInteractionText = `Há ${diffMinutes}min`;
            } else {
                lastInteractionText = 'Agora';
            }
        }
        
        statusDiv.innerHTML = `
            <div class="d-flex align-items-center mb-2">
                <span class="badge badge-success me-2">✅ Ativo</span>
                <span class="fw-bold">${escapeHtml(aiAgent.name)}</span>
            </div>
            <div class="fs-7 text-muted mb-1">
                Tipo: ${escapeHtml(aiAgent.type || 'GENERAL')}
            </div>
            <div class="fs-7 text-muted mb-1">
                Mensagens: ${status.messages_count}
            </div>
            ${status.tools_used.length > 0 ? `
                <div class="fs-7 text-muted mb-1">
                    🔧 Tools: ${status.tools_used.join(', ')}
                </div>
            ` : ''}
            <div class="fs-7 text-muted">
                Última interação: ${lastInteractionText}
            </div>
        `;
        
        if (actionsDiv) actionsDiv.style.display = 'block';
        if (addSection) addSection.style.display = 'none';
    } else {
        // Não tem IA
        console.log('Atualizando sidebar: Sem IA ativa');
        statusDiv.innerHTML = `
            <div class="d-flex align-items-center mb-2">
                <span class="badge badge-secondary me-2">⚪ Inativo</span>
            </div>
            <div class="fs-7 text-muted">
                Nenhum agente de IA ativo nesta conversa
            </div>
        `;
        
        if (actionsDiv) actionsDiv.style.display = 'none';
        if (addSection) addSection.style.display = 'block';
        console.log('Sidebar atualizado: Sem IA - HTML inserido');
    }
    
    // Atualizar banner de IA ativa (se a função existir)
    if (typeof updateAIActiveBanner === 'function') {
        const conversationId = window.currentConversationId || 0;
        updateAIActiveBanner(status, conversationId);
    }
};

/**
 * Mostrar modal de adicionar agente de IA
 */
window.showAddAIAgentModal = function() {
    console.log('🤖 [sidebar] showAddAIAgentModal chamado');
    console.log('🔍 [sidebar] window.currentConversationId:', window.currentConversationId);
    console.log('🔍 [sidebar] typeof window.currentConversationId:', typeof window.currentConversationId);
    
    const conversationId = window.currentConversationId || 0;
    console.log('🔍 [sidebar] conversationId após || 0:', conversationId);
    
    if (!conversationId) {
        console.warn('⚠️ [sidebar] conversationId vazio ou zero, mostrando alerta');
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma conversa primeiro'
        });
        return;
    }
    
    console.log('✅ [sidebar] conversationId válido:', conversationId);
    
    // Carregar agentes disponíveis
    fetch(`<?= \App\Helpers\Url::to('/ai-agents/available') ?>`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success || !data.data || data.data.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Atenção',
                text: 'Nenhum agente de IA disponível'
            });
            return;
        }
        
        const agents = data.data;
        const agentOptions = agents.map(agent => 
            `<option value="${agent.id}">${agent.name} (${agent.agent_type})</option>`
        ).join('');
        
        Swal.fire({
            title: 'Adicionar Agente de IA',
            html: `
                <div class="text-start">
                    <div class="mb-4">
                        <label class="form-label">Selecione o agente:</label>
                        <select id="swal-ai-agent-select" class="form-select">
                            <option value="">Selecione...</option>
                            ${agentOptions}
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" id="swal-process-immediately" checked>
                            <span class="form-check-label">
                                Processar mensagens imediatamente
                            </span>
                        </label>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" id="swal-assume-conversation">
                            <span class="form-check-label">
                                Assumir conversa (remover agente humano se houver)
                            </span>
                        </label>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" id="swal-only-if-unassigned">
                            <span class="form-check-label">
                                Apenas se não tiver agente atribuído
                            </span>
                        </label>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Adicionar IA',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const agentId = document.getElementById('swal-ai-agent-select').value;
                if (!agentId) {
                    Swal.showValidationMessage('Selecione um agente de IA');
                    return false;
                }
                
                return {
                    ai_agent_id: agentId,
                    process_immediately: document.getElementById('swal-process-immediately').checked,
                    assume_conversation: document.getElementById('swal-assume-conversation').checked,
                    only_if_unassigned: document.getElementById('swal-only-if-unassigned').checked
                };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                addAIAgentToConversation(conversationId, result.value);
            }
        });
    })
    .catch(error => {
        console.error('Erro ao carregar agentes:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao carregar agentes de IA disponíveis'
        });
    });
};

/**
 * Adicionar agente de IA à conversa
 */
window.addAIAgentToConversation = function(conversationId, data) {
    const btn = Swal.getConfirmButton();
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adicionando...';
    
    fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/ai-agents`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: result.message || 'Agente de IA adicionado com sucesso',
                timer: 2000,
                showConfirmButton: false
            });
            
            // Recarregar status da IA
            loadAIAgentStatus(conversationId);
            
            // Recarregar conversa se necessário
            if (typeof selectConversation === 'function') {
                selectConversation(conversationId);
            }
        } else {
            throw new Error(result.message || 'Erro ao adicionar agente de IA');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message || 'Erro ao adicionar agente de IA'
        });
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
};

/**
 * Mostrar histórico de mensagens da IA
 */
window.showAIHistory = function() {
    const conversationId = window.currentConversationId || 0;
    if (!conversationId) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma conversa primeiro'
        });
        return;
    }
    
    // Carregar mensagens da IA
    fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/ai-messages?limit=50`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Erro ao carregar histórico');
        }
        
        const messages = data.data || [];
        
        if (messages.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Histórico',
                text: 'Nenhuma mensagem da IA encontrada'
            });
            return;
        }
        
        // Carregar nome do agente
        fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/ai-status`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(statusData => {
            const agentName = statusData.success && statusData.data?.ai_agent 
                ? statusData.data.ai_agent.name 
                : 'Agente de IA';
            
            // Formatar mensagens
            const messagesHtml = messages.map(msg => {
                const date = new Date(msg.created_at);
                const formattedDate = date.toLocaleString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                const toolsHtml = msg.tools_used && msg.tools_used.length > 0
                    ? `<div class="mt-2"><small class="text-muted">🔧 Tools: ${msg.tools_used.join(', ')}</small></div>`
                    : '';
                
                return `
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <small class="text-muted">${formattedDate}</small>
                        </div>
                        <div class="text-gray-800">${escapeHtml(msg.content)}</div>
                        ${toolsHtml}
                    </div>
                `;
            }).join('');
            
            Swal.fire({
                title: `Histórico - ${agentName}`,
                html: `
                    <div style="max-height: 400px; overflow-y: auto; text-align: left;">
                        ${messagesHtml}
                    </div>
                `,
                width: '600px',
                showConfirmButton: true,
                confirmButtonText: 'Fechar'
            });
        })
    .catch(error => {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message || 'Erro ao carregar histórico da IA'
        });
    });
};

/**
 * Remover agente de IA da conversa
 */
window.removeAIAgent = function() {
    const conversationId = window.currentConversationId || 0;
    if (!conversationId) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma conversa primeiro'
        });
        return;
    }
    
    Swal.fire({
        title: 'Remover Agente de IA',
        html: `
            <div class="text-start">
                <p>Deseja realmente remover o agente de IA desta conversa?</p>
                
                <div class="mb-3">
                    <label class="form-check form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" id="swal-assign-to-human" checked>
                        <span class="form-check-label">
                            Atribuir a agente humano após remover
                        </span>
                    </label>
                </div>
                
                <div id="swal-human-agent-select-container" style="display: none;">
                    <label class="form-label">Selecione o agente:</label>
                    <select id="swal-human-agent-select" class="form-select">
                        <option value="">Distribuição automática</option>
                    </select>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Remover',
        cancelButtonText: 'Cancelar',
        didOpen: () => {
            const assignCheckbox = document.getElementById('swal-assign-to-human');
            const selectContainer = document.getElementById('swal-human-agent-select-container');
            
            assignCheckbox.addEventListener('change', function() {
                selectContainer.style.display = this.checked ? 'block' : 'none';
                
                if (this.checked) {
                    // Carregar agentes disponíveis (opcional - pode ser deixado vazio para distribuição automática)
                    // Por enquanto, deixar apenas "Distribuição automática"
                }
            });
        },
        preConfirm: () => {
            const assignToHuman = document.getElementById('swal-assign-to-human').checked;
            const humanAgentId = document.getElementById('swal-human-agent-select').value;
            
            return {
                assign_to_human: assignToHuman,
                human_agent_id: humanAgentId || null,
                reason: 'Removido manualmente pelo usuário'
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const btn = Swal.getConfirmButton();
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Removendo...';
            
            fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/ai-agents`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(result.value)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: data.message || 'Agente de IA removido com sucesso',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // Recarregar status da IA
                    loadAIAgentStatus(conversationId);
                    
                    // Recarregar conversa se necessário
                    if (typeof selectConversation === 'function') {
                        selectConversation(conversationId);
                    }
                } else {
                    throw new Error(data.message || 'Erro ao remover agente de IA');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: error.message || 'Erro ao remover agente de IA'
                });
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        }
    });
};

// Função auxiliar para escapar HTML
window.escapeHtml = function(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
};

console.log('✅✅✅ TODAS AS FUNÇÕES DO SIDEBAR CARREGADAS ✅✅✅');
console.log('✅ Todas as funções do sidebar carregadas:', {
    editContact: typeof window.editContact,
    loadAIAgentStatus: typeof window.loadAIAgentStatus,
    updateAIAgentSidebar: typeof window.updateAIAgentSidebar,
    showAddAIAgentModal: typeof window.showAddAIAgentModal,
    showAIHistory: typeof window.showAIHistory,
    removeAIAgent: typeof window.removeAIAgent,
    loadWooCommerceOrders: typeof window.loadWooCommerceOrders
});

// ============================================================================
// FUNÇÕES WOOCOMMERCE
// ============================================================================

console.log('🛒🛒🛒 INICIANDO CARREGAMENTO DAS FUNÇÕES WOOCOMMERCE 🛒🛒🛒');

/**
 * Carregar pedidos do WooCommerce para o contato da conversa atual
 */
window.loadWooCommerceOrders = function() {
    console.log('🛒 loadWooCommerceOrders chamada!');
    console.log('  - currentConversationId:', window.currentConversationId);
    console.log('  - currentConversation:', window.currentConversation);
    
    const conversationId = window.currentConversationId;
    if (!conversationId) {
        console.warn('⚠️ Nenhuma conversa selecionada');
        const ordersList = document.getElementById('woocommerce-orders-list');
        if (ordersList) {
            ordersList.innerHTML = '<div class="text-muted fs-7">Selecione uma conversa primeiro</div>';
        }
        return;
    }
    
    // Obter contact_id da conversa atual
    let contactId = null;
    if (window.currentConversation?.contact_id) {
        contactId = window.currentConversation.contact_id;
        console.log('✅ ContactId obtido de window.currentConversation:', contactId);
    } else {
        const sidebar = document.getElementById('conversationSidebar');
        contactId = sidebar?.dataset?.contactId;
        console.log('✅ ContactId obtido do sidebar dataset:', contactId);
    }
    
    if (!contactId) {
        console.error('❌ ContactId não encontrado!');
        const ordersList = document.getElementById('woocommerce-orders-list');
        if (ordersList) {
            ordersList.innerHTML = '<div class="text-muted fs-7">Erro: ID do contato não encontrado</div>';
        }
        return;
    }
    
    console.log('📞 Chamando renderWooCommerceOrders com contactId:', contactId);
    renderWooCommerceOrders(contactId);
};

/**
 * Renderizar pedidos do WooCommerce
 */
function renderWooCommerceOrders(contactId) {
    console.log('📦 renderWooCommerceOrders iniciada com contactId:', contactId);
    
    const ordersList = document.getElementById('woocommerce-orders-list');
    if (!ordersList) {
        console.error('❌ Elemento woocommerce-orders-list não encontrado!');
        return;
    }
    
    const integrationFilter = document.getElementById('woocommerce-integration-filter')?.value || '';
    const statusFilter = document.getElementById('woocommerce-status-filter')?.value || '';
    
    console.log('🔍 Filtros:', { integrationFilter, statusFilter });
    
    ordersList.innerHTML = '<div class="text-muted fs-7">Carregando pedidos...</div>';
    
    let url = `<?= \App\Helpers\Url::to('/integrations/woocommerce/contacts') ?>/${contactId}/orders`;
    if (integrationFilter) {
        url += `?integration_id=${integrationFilter}`;
    }
    
    console.log('🌐 Fazendo requisição para:', url);
    
    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        console.log('📡 Response recebida:', response.status, response.statusText);
        return response.json();
    })
    .then(data => {
        console.log('📦 Dados recebidos:', data);
        if (!data.success) {
            throw new Error(data.message || 'Erro ao carregar pedidos');
        }
        
        const orders = data.orders || [];
        
        // Filtrar por status se necessário
        let filteredOrders = orders;
        if (statusFilter) {
            filteredOrders = orders.filter(order => order.status === statusFilter);
        }
        
        if (filteredOrders.length === 0) {
            ordersList.innerHTML = `
                <div class="text-center py-5">
                    <i class="ki-duotone ki-information-5 fs-3x text-muted mb-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <p class="text-muted fs-7">Nenhum pedido encontrado</p>
                </div>
            `;
            return;
        }
        
        // Renderizar lista de pedidos
        let html = '<div class="d-flex flex-column gap-3">';
        
        filteredOrders.forEach(order => {
            const orderDate = new Date(order.date_created).toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const statusColors = {
                'pending': 'warning',
                'processing': 'info',
                'on-hold': 'warning',
                'completed': 'success',
                'cancelled': 'danger',
                'refunded': 'secondary',
                'failed': 'danger'
            };
            
            const statusLabels = {
                'pending': 'Pendente',
                'processing': 'Processando',
                'on-hold': 'Em espera',
                'completed': 'Concluído',
                'cancelled': 'Cancelado',
                'refunded': 'Reembolsado',
                'failed': 'Falhou'
            };
            
            const statusColor = statusColors[order.status] || 'secondary';
            const statusLabel = statusLabels[order.status] || order.status;
            const total = parseFloat(order.total || 0).toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            });
            
            html += `
                <div class="card card-flush card-hover">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-bold fs-6 text-gray-800">Pedido #${order.id}</div>
                                <div class="fs-7 text-muted">${orderDate}</div>
                            </div>
                            <span class="badge badge-light-${statusColor}">${statusLabel}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="fs-5 fw-bold text-primary">${total}</div>
                            ${order.number ? `<div class="fs-7 text-muted">Nº ${order.number}</div>` : ''}
                        </div>
                        ${order.line_items && order.line_items.length > 0 ? `
                            <div class="mt-3 pt-3 border-top">
                                <div class="fs-7 fw-semibold text-gray-700 mb-2">Itens:</div>
                                <div class="d-flex flex-column gap-1">
                                    ${order.line_items.slice(0, 3).map(item => `
                                        <div class="d-flex justify-content-between">
                                            <span class="fs-7">${(item.name || 'Produto').substring(0, 30)}${(item.name || '').length > 30 ? '...' : ''} x${item.quantity || 1}</span>
                                            <span class="fs-7 fw-semibold">${parseFloat(item.total || 0).toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'})}</span>
                                        </div>
                                    `).join('')}
                                    ${order.line_items.length > 3 ? `<div class="fs-7 text-muted">+${order.line_items.length - 3} mais</div>` : ''}
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        ordersList.innerHTML = html;
        
        // Carregar lista de integrações para o filtro
        loadWooCommerceIntegrations();
    })
    .catch(error => {
        console.error('Erro ao carregar pedidos WooCommerce:', error);
        ordersList.innerHTML = `
            <div class="text-center py-5">
                <i class="ki-duotone ki-information-5 fs-3x text-danger mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <p class="text-danger fs-7">Erro ao carregar pedidos</p>
                <p class="text-muted fs-8">${error.message}</p>
            </div>
        `;
    });
}

/**
 * Carregar lista de integrações WooCommerce para o filtro
 */
window.loadWooCommerceIntegrations = function() {
    const filterSelect = document.getElementById('woocommerce-integration-filter');
    if (!filterSelect) {
        console.warn('⚠️ Select de integrações não encontrado');
        return;
    }

    // Evitar recarregar se já fizemos uma carga bem-sucedida e não houve pedido de refresh
    if (filterSelect.dataset.loaded === '1') {
        console.log('✅ Integrações já carregadas (cache em memória)');
        return;
    }

    console.log('🔍 Carregando integrações WooCommerce...');

    // Mostrar estado de carregamento e evitar interação
    const placeholder = filterSelect.options[0];
    placeholder.textContent = 'Carregando lojas...';
    filterSelect.disabled = true;

    fetch('<?= \App\Helpers\Url::to('/integrations/woocommerce') ?>', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        console.log('📡 Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('📦 Dados recebidos:', data);

        // Limpar opções anteriores mantendo apenas o placeholder
        while (filterSelect.options.length > 1) {
            filterSelect.remove(1);
        }

        const seen = new Set();
        if (data.integrations && Array.isArray(data.integrations)) {
            console.log(`✅ ${data.integrations.length} integração(ões) encontrada(s)`);
            data.integrations.forEach(integration => {
                const key = `${integration.id}-${integration.name}`;
                if (seen.has(key)) {
                    return; // evita duplicados
                }
                seen.add(key);
                const option = document.createElement('option');
                option.value = integration.id;
                option.textContent = integration.name;
                filterSelect.appendChild(option);
                console.log(`  ➕ Adicionada: ${integration.name} (ID: ${integration.id})`);
            });
            // Marcar como carregado com sucesso
            filterSelect.dataset.loaded = '1';
        } else {
            console.warn('⚠️ Nenhuma integração encontrada ou formato inválido');
            filterSelect.dataset.loaded = '0';
        }
    })
    .catch(error => {
        console.error('❌ Erro ao carregar integrações:', error);
        filterSelect.dataset.loaded = '0';
    })
    .finally(() => {
        placeholder.textContent = 'Todas as lojas';
        filterSelect.disabled = false;
    });
}

// Carregar pedidos quando a aba for clicada (apenas na primeira vez ou ao forçar)
document.addEventListener('DOMContentLoaded', function() {
    let ordersLoaded = false; // Flag para evitar carregamentos desnecessários
    
    const ordersTab = document.querySelector('a[href="#kt_tab_orders"]');
    if (ordersTab) {
        ordersTab.addEventListener('shown.bs.tab', function() {
            console.log('🛒 Aba de pedidos aberta');
            if (!ordersLoaded) {
                console.log('🛒 Carregando pedidos pela primeira vez...');
                ordersLoaded = true;
                if (typeof window.loadWooCommerceOrders === 'function') {
                    window.loadWooCommerceOrders();
                } else {
                    console.error('❌ Função loadWooCommerceOrders não encontrada');
                }
            } else {
                console.log('🛒 Pedidos já carregados, pulando...');
            }
        });
        console.log('✅ Event listener de pedidos WooCommerce registrado');
    } else {
        console.warn('⚠️ Aba de pedidos não encontrada');
    }
    
    // Botão de atualizar pedidos (força recarregamento)
    const refreshBtn = document.getElementById('btn-refresh-woocommerce-orders');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            console.log('🔄 Forçando atualização de pedidos WooCommerce...');
            ordersLoaded = false; // Reset flag para forçar recarregamento
            if (typeof window.loadWooCommerceOrders === 'function') {
                window.loadWooCommerceOrders();
            } else {
                console.error('❌ Função loadWooCommerceOrders não encontrada');
            }
            ordersLoaded = true;
        });
        console.log('✅ Event listener do botão de atualizar registrado');
    }
    
    // Filtros de integração e status (forçam recarregamento)
    const integrationFilter = document.getElementById('woocommerce-integration-filter');
    const statusFilter = document.getElementById('woocommerce-status-filter');
    
    if (integrationFilter) {
        integrationFilter.addEventListener('change', function() {
            console.log('🔍 Filtro de integração alterado:', this.value);
            if (ordersLoaded && typeof window.loadWooCommerceOrders === 'function') {
                console.log('🔄 Recarregando pedidos após filtro...');
                window.loadWooCommerceOrders();
            }
        });
        console.log('✅ Event listener do filtro de integração registrado');
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            console.log('📊 Filtro de status alterado:', this.value);
            if (ordersLoaded && typeof window.loadWooCommerceOrders === 'function') {
                console.log('🔄 Recarregando pedidos após filtro...');
                window.loadWooCommerceOrders();
            }
        });
        console.log('✅ Event listener do filtro de status registrado');
    }

    // Garantir que o select de lojas seja populado assim que a página carregar
    if (typeof window.loadWooCommerceIntegrations === 'function') {
        console.log('🔍 Carregando integrações WooCommerce (onload)...');
        window.loadWooCommerceIntegrations();
    } else {
        console.warn('⚠️ loadWooCommerceIntegrations não disponível no onload');
    }
});

// Debug: verificar se a função está disponível
console.log('🔍 loadWooCommerceOrders disponível?', typeof window.loadWooCommerceOrders);
console.log('✅ loadConversationSLA já registrada anteriormente');

// ========== FUNÇÃO loadConversationSLA (REMOVIDA - DEFINIDA NO INÍCIO DO ARQUIVO) ==========
// A função foi movida para o início do arquivo para estar disponível imediatamente
/*window.loadConversationSLA = function(conversationId) {
    if (!conversationId) return;
    
    const loadingEl = document.getElementById('sla-loading');
    const contentEl = document.getElementById('sla-content');
    const statusBadge = document.getElementById('sla-status-badge');
    
    if (loadingEl) loadingEl.style.display = 'block';
    if (contentEl) contentEl.style.display = 'none';
    if (statusBadge) statusBadge.textContent = '...';
    
    console.log('🔍 Carregando SLA para conversa:', conversationId);
    
    fetch(`<?= \App\Helpers\Url::to('/conversations/sla-details') ?>?id=${conversationId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        console.log('📥 Resposta SLA recebida:', {
            status: response.status,
            statusText: response.statusText,
            ok: response.ok,
            url: response.url
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return response.text().then(text => {
            console.log('📄 Resposta em texto:', text.substring(0, 500));
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('❌ Erro ao fazer parse do JSON:', e);
                console.error('📄 Texto completo:', text);
                throw new Error('Resposta não é JSON válido');
            }
        });
    })
    .then(data => {
        console.log('📊 Dados SLA parseados:', data);
        if (data.success && data.sla) {
            const sla = data.sla;
            
            if (loadingEl) loadingEl.style.display = 'none';
            if (contentEl) contentEl.style.display = 'block';
            
            // Atualizar badge de status
            let badgeClass = 'badge-light-success';
            let badgeText = '✓ No prazo';
            
            if (sla.status_indicator === 'exceeded') {
                badgeClass = 'badge-light-danger';
                badgeText = '✗ Excedido';
            } else if (sla.status_indicator === 'warning') {
                badgeClass = 'badge-light-warning';
                badgeText = '⚠ Alerta';
            } else if (!sla.should_start) {
                badgeClass = 'badge-light-secondary';
                badgeText = '⏸ Aguardando';
            }
            
            if (statusBadge) {
                statusBadge.className = `badge badge-sm ${badgeClass}`;
                statusBadge.textContent = badgeText;
            }
            
            // Atualizar progresso
            const progressBar = document.getElementById('sla-progress-bar');
            const elapsedTimeEl = document.getElementById('sla-elapsed-time');
            const percentageEl = document.getElementById('sla-percentage');
            const targetEl = document.getElementById('sla-target');
            
            if (progressBar) {
                let barClass = 'bg-success';
                if (sla.percentage >= 100) barClass = 'bg-danger';
                else if (sla.percentage >= 80) barClass = 'bg-warning';
                
                progressBar.className = `progress-bar progress-bar-striped ${sla.percentage < 100 ? 'progress-bar-animated' : ''} ${barClass}`;
                progressBar.style.width = Math.min(100, sla.percentage) + '%';
            }
            
            if (elapsedTimeEl) {
                elapsedTimeEl.textContent = `${sla.elapsed_minutes} min`;
                elapsedTimeEl.className = `fs-6 fw-bold ${sla.percentage >= 100 ? 'text-danger' : sla.percentage >= 80 ? 'text-warning' : 'text-success'}`;
            }
            
            if (percentageEl) {
                percentageEl.textContent = `${sla.percentage}%`;
                percentageEl.className = `fs-8 fw-bold ${sla.percentage >= 100 ? 'text-danger' : sla.percentage >= 80 ? 'text-warning' : 'text-success'}`;
            }
            
            if (targetEl) {
                targetEl.textContent = `${sla.first_response_sla} min`;
            }
            
            // Detalhes
            const ruleNameEl = document.getElementById('sla-rule-name');
            const startTimeEl = document.getElementById('sla-start-time');
            
            if (ruleNameEl) ruleNameEl.textContent = sla.sla_rule || 'Global';
            if (startTimeEl) {
                // Converter data do servidor (formato: 2026-01-20 14:20:00) para objeto Date
                const startDate = new Date(sla.start_time.replace(' ', 'T'));
                startTimeEl.textContent = startDate.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
            }
            
            // Excedido
            const exceededContainer = document.getElementById('sla-exceeded-container');
            const exceededByEl = document.getElementById('sla-exceeded-by');
            
            if (exceededContainer && exceededByEl) {
                if (sla.percentage > 100) {
                    exceededContainer.style.display = 'flex';
                    const exceededMinutes = sla.elapsed_minutes - sla.first_response_sla;
                    exceededByEl.textContent = `+${exceededMinutes.toFixed(0)} min`;
                } else {
                    exceededContainer.style.display = 'none';
                }
            }
            
            // Timeline
            const timelineEl = document.getElementById('sla-timeline');
            if (timelineEl && sla.timeline) {
                let timelineHtml = '';
                
                sla.timeline.forEach((event, index) => {
                    const time = new Date(event.time);
                    const timeStr = time.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
                    
                    if (event.type === 'agent_response') {
                        const agentType = event.is_ai ? '🤖 IA' : '👤 Agente';
                        timelineHtml += `
                            <div class="timeline-sla-item agent">
                                <div class="fs-8 text-muted">${timeStr}</div>
                                <div class="fs-7 fw-semibold text-primary">${agentType} respondeu</div>
                                ${event.content_preview ? `<div class="fs-8 text-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;">${event.content_preview}</div>` : ''}
                            </div>
                        `;
                    } else if (event.type === 'contact_message') {
                        const slaClass = event.sla_active ? 'sla-active' : '';
                        const slaIcon = event.sla_active ? '🔴 ' : '';
                        const minSince = event.minutes_since_agent ? `(${event.minutes_since_agent.toFixed(1)} min depois)` : '';
                        
                        timelineHtml += `
                            <div class="timeline-sla-item contact ${slaClass}">
                                <div class="fs-8 text-muted">${timeStr}</div>
                                <div class="fs-7 fw-semibold text-success">${slaIcon}Cliente enviou</div>
                                ${minSince ? `<div class="fs-8 text-muted">${minSince}</div>` : ''}
                                ${event.content_preview ? `<div class="fs-8 text-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;">${event.content_preview}</div>` : ''}
                            </div>
                        `;
                    }
                });
                
                timelineEl.innerHTML = timelineHtml || '<div class="text-muted fs-8">Nenhum evento ainda</div>';
            }
            
            // Badges informativos
            const badgesContainer = document.getElementById('sla-badges-container');
            if (badgesContainer) {
                let badgesHtml = '';
                
                if (sla.is_paused) {
                    badgesHtml += '<span class="badge badge-light-warning fs-8">⏸ Pausado</span>';
                }
                
                if (sla.warning_sent) {
                    badgesHtml += '<span class="badge badge-light-info fs-8">🔔 Alerta enviado</span>';
                }
                
                if (sla.reassignment_count > 0) {
                    badgesHtml += `<span class="badge badge-light-danger fs-8">🔄 ${sla.reassignment_count}x reatribuída</span>`;
                }
                
                if (sla.paused_duration > 0) {
                    badgesHtml += `<span class="badge badge-light-secondary fs-8">⏱ ${sla.paused_duration}min pausado</span>`;
                }
                
                if (!sla.should_start) {
                    badgesHtml += `<span class="badge badge-light-info fs-8">⏳ Delay de ${sla.delay_minutes}min</span>`;
                }
                
                badgesContainer.innerHTML = badgesHtml || '<span class="text-muted fs-8">Sem eventos especiais</span>';
            }
            
            // Auto-atualizar a cada 30 segundos se SLA estiver ativo
            if (sla.should_start && !sla.is_within_sla && sla.status !== 'closed') {
                setTimeout(() => {
                    if (window.currentConversationId === conversationId) {
                        window.loadConversationSLA(conversationId);
                    }
                }, 30000);
            }
        }
    })
    .catch(error => {
        console.error('❌ Erro ao carregar SLA:', error);
        if (loadingEl) {
            loadingEl.style.display = 'none';
        }
        if (contentEl) {
            contentEl.style.display = 'block';
            contentEl.innerHTML = `
                <div class="alert alert-danger d-flex align-items-center p-3">
                    <i class="ki-duotone ki-information fs-2 text-danger me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="fs-7">
                        <strong>Erro ao carregar SLA</strong><br>
                        <span class="text-muted">${error.message || 'Erro desconhecido'}</span>
                    </div>
                </div>
            `;
        }
    });
};

console.log('✅ Função loadConversationSLA registrada');
console.log('🔍 URL do endpoint SLA:', '<?= \App\Helpers\Url::to('/conversations/sla-details') ?>');*/
</script>

