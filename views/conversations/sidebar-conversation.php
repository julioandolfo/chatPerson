<!-- Sidebar Direita - Detalhes da Conversa -->
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
    z-index: 1000;
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
                
                <!-- Informações da Conversa -->
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Conversa</div>
                    
                    <div class="sidebar-info-item">
                        <span class="sidebar-info-label">Status:</span>
                        <span class="sidebar-info-value" data-field="status">-</span>
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
                            <div class="fs-2 fw-bold text-primary">-</div>
                            <div class="fs-7 text-muted">Conversas</div>
                        </div>
                        <div class="text-center">
                            <div class="fs-2 fw-bold text-success">-</div>
                            <div class="fs-7 text-muted">Tempo Médio</div>
                        </div>
                        <div class="text-center">
                            <div class="fs-2 fw-bold text-warning">-</div>
                            <div class="fs-7 text-muted">Satisfação</div>
                        </div>
                    </div>
                </div>
                
                <div class="separator my-5"></div>
                
                <!-- Conversas anteriores -->
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Conversas Anteriores</div>
                    
                    <div class="text-center py-5">
                        <p class="text-muted fs-7">Nenhuma conversa anterior</p>
                    </div>
                </div>
                
            </div>
            
        </div>
    </div>
    
</div>

<script>
// Funções de ação do sidebar
function editContact(contactId) {
    window.location.href = '<?= \App\Helpers\Url::to("/contacts") ?>/' + contactId;
}

function assignConversation(conversationId) {
    // TODO: Implementar modal de atribuição
    alert('Modal de atribuição em desenvolvimento');
}

function changeDepartment(conversationId) {
    // TODO: Implementar modal de mudança de setor
    alert('Modal de mudança de setor em desenvolvimento');
}

function manageTags(conversationId) {
    // TODO: Implementar modal de gerenciamento de tags
    alert('Modal de tags em desenvolvimento');
}

function closeConversation(conversationId) {
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
}

function reopenConversation(conversationId) {
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
}

function markAsSpam(conversationId) {
    if (!confirm('Deseja realmente marcar esta conversa como spam? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    // TODO: Implementar endpoint de spam
    alert('Funcionalidade de spam em desenvolvimento');
}

function addNote(conversationId) {
    const noteText = document.getElementById('newNoteText').value.trim();
    
    if (!noteText) {
        alert('Digite uma nota antes de salvar');
        return;
    }
    
    // TODO: Implementar endpoint de notas
    alert('Funcionalidade de notas em desenvolvimento');
}
</script>

