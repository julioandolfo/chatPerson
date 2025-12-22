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
                
                <!-- Funil e Etapa -->
                <div class="sidebar-section" id="sidebar-funnel-stage-section" style="display: none;">
                    <div class="sidebar-section-title d-flex justify-content-between align-items-center">
                        <span>🎯 Funil e Etapa</span>
                        <button class="btn btn-sm btn-icon btn-light-primary p-0" id="sidebar-move-stage-btn" onclick="if(typeof window.moveConversationStage === 'function') { window.moveConversationStage(); } else { console.error('moveConversationStage não definida'); }" title="Mover conversa">
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
            
        </div>
    </div>
    
</div>

<script>
console.log('📋 sidebar-conversation.php carregado');

// Adicionar listener ao botão de mover estágio (fallback)
document.addEventListener('DOMContentLoaded', function() {
    const moveStageBtn = document.getElementById('sidebar-move-stage-btn');
    if (moveStageBtn) {
        console.log('🔧 Adicionando listener ao botão de mover estágio');
        moveStageBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('🖱️ Botão de mover estágio clicado (via listener)');
            if (typeof window.moveConversationStage === 'function') {
                window.moveConversationStage();
            } else {
                console.error('❌ moveConversationStage não está definida!');
            }
        });
    } else {
        console.warn('⚠️ Botão sidebar-move-stage-btn não encontrado');
    }
});

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

window.markAsSpam = function(conversationId) {
    if (!confirm('Deseja realmente marcar esta conversa como spam? Esta ação não pode ser desfeita e a conversa será fechada automaticamente.')) {
        return;
    }
    
    fetch(`/conversations/${conversationId}/spam`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recarregar a página para atualizar o estado
            window.location.reload();
        } else {
            alert('Erro ao marcar como spam: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao marcar como spam');
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

// Função para mover conversa de funil/etapa
console.log('🔧 Definindo window.moveConversationStage...');

// Definir a função de forma global e garantida
if (typeof window.moveConversationStage !== 'function') {
    window.moveConversationStage = function() {
        console.log('✅ moveConversationStage chamada!');
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
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
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
    console.log('✅ window.moveConversationStage definida com sucesso!');
} else {
    console.warn('⚠️ window.moveConversationStage já estava definida');
}

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

console.log('✅ Todas as funções do sidebar carregadas:', {
    editContact: typeof window.editContact,
    loadAIAgentStatus: typeof window.loadAIAgentStatus,
    updateAIAgentSidebar: typeof window.updateAIAgentSidebar,
    showAddAIAgentModal: typeof window.showAddAIAgentModal,
    showAIHistory: typeof window.showAIHistory,
    removeAIAgent: typeof window.removeAIAgent
});
</script>

