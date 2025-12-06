
// Selecionar conversa (carregar via AJAX sem recarregar página)
// Sistema de Polling (fallback quando WebSocket não está disponível)
// Declarar variáveis e funções ANTES de serem usadas
let pollingInterval = null;
let lastMessageId = null;
let currentPollingConversationId = null;

// Sistema de Paginação Infinita
let isLoadingMessages = false;
let hasMoreMessages = true;
let oldestMessageId = null;
let currentConversationId = null;

/**
 * Iniciar polling (verificação periódica de novas mensagens)
 */
function startPolling(conversationId) {
    // Parar polling anterior se existir
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
    
    currentPollingConversationId = conversationId;
    
    // Se não houver conversa selecionada, não fazer polling
    if (!conversationId) {
        return;
    }
    
    // Verificar novas mensagens a cada 3 segundos
    pollingInterval = setInterval(() => {
        checkForNewMessages(conversationId);
    }, 3000);
    
    console.log('📡 Polling iniciado para conversa ' + conversationId + ' (WebSocket não disponível)');
}

/**
 * Parar polling
 */
function stopPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
        console.log('📡 Polling parado');
    }
    currentPollingConversationId = null;
}

/**
 * Verificar novas mensagens via AJAX
 */
function checkForNewMessages(conversationId) {
    if (!conversationId) return;
    
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return;
    
    // Buscar apenas mensagens novas
    fetch(`/${conversationId}?last_message_id=${lastMessageId || 0}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.messages && data.messages.length > 0) {
            // Adicionar apenas mensagens novas
            data.messages.forEach(msg => {
                const existingMsg = chatMessages.querySelector(`[data-message-id="${msg.id}"]`);
                if (!existingMsg) {
                    addMessageToChat(msg);
                    lastMessageId = Math.max(lastMessageId || 0, msg.id || 0);
                }
            });
            
            // Atualizar lista de conversas também
            if (data.messages.length > 0) {
                updateConversationListPreview(conversationId, data.messages[data.messages.length - 1]);
            }
        }
    })
    .catch(error => {
        // Silenciar erros de polling (normal quando não há novas mensagens)
        if (error.message && !error.message.includes('404')) {
            console.error('Erro ao verificar novas mensagens:', error);
        }
    });
}

/**
 * Atualizar preview da conversa na lista
 */
function updateConversationListPreview(conversationId, lastMessage) {
    const conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
    if (conversationItem && lastMessage) {
        const preview = conversationItem.querySelector('.conversation-item-preview');
        const time = conversationItem.querySelector('.conversation-item-time');
        
        if (preview) {
            const content = lastMessage.content || '';
            preview.textContent = content.substring(0, 60) + (content.length > 60 ? '...' : '');
        }
        if (time) {
            time.textContent = 'Agora';
        }
        
        // Mover para o topo
        const list = conversationItem.parentElement;
        if (list && conversationItem !== list.firstElementChild) {
            list.insertBefore(conversationItem, list.firstElementChild);
        }
    }
}

function selectConversation(id) {
    // Marcar conversa como ativa na lista
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('active');
    });
    const conversationItem = document.querySelector(`[data-conversation-id="${id}"]`);
    if (conversationItem) {
        conversationItem.classList.add('active');
    }
    
    // Mostrar header e input (se estiverem ocultos)
    const chatHeader = document.getElementById('chatHeader');
    const chatInput = document.getElementById('chatInput');
    if (chatHeader) chatHeader.style.display = '';
    if (chatInput) chatInput.style.display = '';
    
    // Mostrar loading
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) {
        console.error('Elemento chatMessages não encontrado');
        return;
    }
    
    chatMessages.innerHTML = `
        <div class="d-flex align-items-center justify-content-center" style="height: 100%;">
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
                <div class="text-muted">Carregando conversa...</div>
            </div>
        </div>
    `;
    
    // Fazer requisição AJAX
    fetch(`/${id}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.conversation) {
            // Atualizar URL sem recarregar
            const newUrl = `?id=${id}`;
            window.history.pushState({ conversationId: id }, '', newUrl);
            
            // Remover badge de não lidas da conversa atual na lista
            if (conversationItem) {
                const badge = conversationItem.querySelector('.conversation-item-badge');
                if (badge) {
                    badge.remove();
                }
            }
            
            // Atualizar header do chat
            updateChatHeader(data.conversation);
            
            // Resetar paginação
            currentConversationId = id;
            isLoadingMessages = false;
            hasMoreMessages = true;
            oldestMessageId = null;
            
            // Atualizar mensagens
            const messages = data.messages || [];
            if (messages.length > 0) {
                oldestMessageId = messages[0].id; // Primeira mensagem (mais antiga)
            }
            updateChatMessages(messages, true);
            
            // Atualizar sidebar
            updateConversationSidebar(data.conversation, data.tags || []);
            
            // Atualizar timeline quando conversa é selecionada
            updateConversationTimeline(data.conversation.id);
            
            // Scroll para última mensagem
            setTimeout(() => {
                const chatMessagesEl = document.getElementById('chatMessages');
                if (chatMessagesEl) {
                    chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
                }
            }, 100);
            
            // Adicionar listener de scroll para paginação infinita
            setupInfiniteScroll();
            
            // Inscrever no WebSocket para esta conversa
            if (typeof window.wsClient !== 'undefined' && window.wsClient.connected) {
                window.wsClient.subscribe(id);
                // Parar polling se WebSocket estiver conectado
                stopPolling();
            } else {
                // Se WebSocket não estiver disponível, iniciar polling
                startPolling(id);
            }
            
            // Atualizar último ID de mensagem conhecido
            if (data.messages && data.messages.length > 0) {
                const lastMsg = data.messages[data.messages.length - 1];
                if (lastMsg.id) {
                    lastMessageId = lastMsg.id;
                }
            }
        } else {
            alert('Erro ao carregar conversa: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro ao carregar conversa:', error);
        alert('Erro ao carregar conversa');
    });
}

// Atualizar header do chat
function updateChatHeader(conversation) {
    const header = document.getElementById('chatHeader');
    if (!header) return;
    
    const contactName = conversation.contact_name || 'Sem nome';
    const initials = getInitials(contactName);
    const channel = conversation.channel || 'chat';
    const status = conversation.status || 'open';
    
    const channelIcon = {
        'whatsapp': '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="#25D366" style="vertical-align: middle; margin-right: 4px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg> WhatsApp',
        'email': '✉️ Email',
        'chat': '💬 Chat'
    }[channel] || '💬 Chat';
    
    const statusClass = {
        'open': 'success',
        'resolved': 'info',
        'closed': 'dark'
    }[status] || 'secondary';
    
    const statusText = {
        'open': 'Aberta',
        'resolved': 'Resolvida',
        'closed': 'Fechada'
    }[status] || 'Desconhecida';
    
    // Atualizar avatar
    const avatarLabel = header.querySelector('.symbol-label');
    if (avatarLabel) {
        avatarLabel.textContent = initials;
    }
    
    // Atualizar nome
    const nameElement = header.querySelector('.chat-header-title');
    if (nameElement) {
        nameElement.textContent = contactName;
    }
    
    // Atualizar canal e status
    const subtitleElement = header.querySelector('.chat-header-subtitle');
    if (subtitleElement) {
        subtitleElement.innerHTML = `${channelIcon} • <span class="badge badge-sm badge-light-${statusClass}">${statusText}</span>`;
    }
}

// Atualizar mensagens do chat
function updateChatMessages(messages, isInitialLoad = false) {
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages || !chatMessages.parentElement) return;
    
    if (!messages || messages.length === 0) {
        if (isInitialLoad) {
            chatMessages.innerHTML = `
                <div class="d-flex align-items-center justify-content-center" style="height: 100%;">
                    <div class="text-center text-muted">
                        <i class="ki-duotone ki-chat fs-3x mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="fw-semibold">Nenhuma mensagem</div>
                        <div class="fs-7">Envie a primeira mensagem</div>
                    </div>
                </div>
            `;
        }
        return;
    }
    
    // Se for carregamento inicial, limpar tudo
    if (isInitialLoad) {
        chatMessages.innerHTML = '';
    }
    
    // Adicionar mensagens
    messages.forEach(msg => {
        const messageDiv = addMessageToChat(msg);
    });
    
    // Observar elementos lazy após adicionar todas as mensagens
    if (chatMessages) {
        observeNewLazyElements(chatMessages);
    }
    
    // Scroll para última mensagem apenas no carregamento inicial
    if (isInitialLoad) {
        setTimeout(() => {
            if (chatMessages && chatMessages.scrollHeight !== undefined) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }, 100);
    }
}

// Configurar scroll infinito
function setupInfiniteScroll() {
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return;
    
    // Remover listener anterior se existir
    chatMessages.removeEventListener('scroll', handleScroll);
    
    // Adicionar novo listener
    chatMessages.addEventListener('scroll', handleScroll);
}

// Handler de scroll para paginação infinita
function handleScroll(event) {
    const chatMessages = event.target;
    
    // Se estiver carregando ou não houver mais mensagens, não fazer nada
    if (isLoadingMessages || !hasMoreMessages || !currentConversationId) {
        return;
    }
    
    // Se scroll estiver próximo do topo (50px), carregar mais mensagens
    if (chatMessages.scrollTop <= 50) {
        loadMoreMessages();
    }
}

// Carregar mais mensagens antigas
async function loadMoreMessages() {
    if (isLoadingMessages || !hasMoreMessages || !currentConversationId || !oldestMessageId) {
        return;
    }
    
    isLoadingMessages = true;
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) {
        isLoadingMessages = false;
        return;
    }
    
    // Salvar posição atual do scroll
    const scrollHeightBefore = chatMessages.scrollHeight;
    
    // Adicionar indicador de carregamento
    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'messages-loading';
    loadingIndicator.id = 'messagesLoadingIndicator';
    loadingIndicator.innerHTML = `
        <div class="spinner-border spinner-border-sm text-primary" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
        Carregando mensagens antigas...
    `;
    chatMessages.insertBefore(loadingIndicator, chatMessages.firstChild);
    
    try {
        const url = `/${currentConversationId}/messages?limit=50&before_id=${oldestMessageId}`;
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.messages && data.messages.length > 0) {
            // Adicionar mensagens no início do chat
            data.messages.forEach(msg => {
                const messageDiv = addMessageToChat(msg);
                chatMessages.insertBefore(messageDiv, loadingIndicator.nextSibling);
            });
            
            // Atualizar oldestMessageId
            oldestMessageId = data.messages[0].id;
            
            // Atualizar flag hasMoreMessages
            hasMoreMessages = data.has_more !== false;
            
            // Restaurar posição do scroll
            const scrollHeightAfter = chatMessages.scrollHeight;
            const scrollDiff = scrollHeightAfter - scrollHeightBefore;
            chatMessages.scrollTop = scrollDiff;
        } else {
            // Não há mais mensagens
            hasMoreMessages = false;
        }
    } catch (error) {
        console.error('Erro ao carregar mensagens antigas:', error);
    } finally {
        // Remover indicador de carregamento
        const indicator = document.getElementById('messagesLoadingIndicator');
        if (indicator) {
            indicator.remove();
        }
        isLoadingMessages = false;
    }
}

// Atualizar sidebar da conversa
function updateConversationSidebar(conversation, tags) {
    // Atualizar informações básicas
    const sidebar = document.getElementById('conversationSidebar');
    if (!sidebar) return;
    
    // Atualizar ID da conversa no sidebar
    sidebar.dataset.conversationId = conversation.id;
    
    // Atualizar iniciais do contato
    const initialsEl = sidebar.querySelector('#sidebar-contact-initials');
    if (initialsEl && conversation.contact_name) {
        const name = conversation.contact_name;
        const parts = name.split(' ');
        const initials = (parts[0].charAt(0) + (parts[1] ? parts[1].charAt(0) : '')).toUpperCase();
        initialsEl.textContent = initials;
    }
    
    // Atualizar informações do contato
    const contactNameEl = sidebar.querySelector('[data-field="contact_name"]');
    if (contactNameEl) contactNameEl.textContent = conversation.contact_name || '-';
    
    const contactEmailEl = sidebar.querySelector('[data-field="contact_email"]');
    if (contactEmailEl) contactEmailEl.textContent = conversation.contact_email || '-';
    
    const contactPhoneEls = sidebar.querySelectorAll('[data-field="contact_phone"]');
    contactPhoneEls.forEach(el => {
        el.textContent = conversation.contact_phone || '-';
    });
    
    // Atualizar informações da conversa
    const conversationStatusEl = sidebar.querySelector('[data-field="status"]');
    if (conversationStatusEl) {
        const statusText = {
            'open': 'Aberta',
            'resolved': 'Resolvida',
            'closed': 'Fechada'
        }[conversation.status] || conversation.status;
        const statusClass = {
            'open': 'success',
            'resolved': 'info',
            'closed': 'dark'
        }[conversation.status] || 'secondary';
        conversationStatusEl.innerHTML = `<span class="badge badge-light-${statusClass}">${statusText}</span>`;
    }
    
    const conversationChannelEl = sidebar.querySelector('[data-field="channel"]');
    if (conversationChannelEl) {
        const channelText = {
            'whatsapp': '📱 WhatsApp',
            'email': '✉️ Email',
            'chat': '💬 Chat'
        }[conversation.channel] || conversation.channel;
        conversationChannelEl.textContent = channelText;
    }
    
    // Atualizar setor
    const departmentEl = sidebar.querySelector('[data-field="department_name"]');
    const departmentItem = sidebar.querySelector('#sidebar-department-item');
    if (departmentEl && departmentItem) {
        if (conversation.department_name) {
            departmentEl.textContent = conversation.department_name;
            departmentItem.style.display = 'flex';
        } else {
            departmentItem.style.display = 'none';
        }
    }
    
    // Atualizar agente
    const agentNameEl = sidebar.querySelector('[data-field="agent_name"]');
    if (agentNameEl) {
        agentNameEl.textContent = conversation.agent_name || 'Não atribuído';
        if (!conversation.agent_name) {
            agentNameEl.classList.add('text-muted');
        } else {
            agentNameEl.classList.remove('text-muted');
        }
    }
    
    // Atualizar data de criação
    const createdAtEl = sidebar.querySelector('[data-field="created_at"]');
    if (createdAtEl && conversation.created_at) {
        const date = new Date(conversation.created_at);
        const formattedDate = date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' +
                              date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        createdAtEl.textContent = formattedDate;
    }
    
    // Atualizar tags
    const tagsContainer = sidebar.querySelector('.conversation-tags-list');
    if (tagsContainer) {
        if (tags && tags.length > 0) {
            tagsContainer.innerHTML = tags.map(tag => `
                <span class="badge badge-lg" style="background-color: ${tag.color}20; color: ${tag.color};">
                    ${escapeHtml(tag.name)}
                </span>
            `).join('');
        } else {
            tagsContainer.innerHTML = '<div class="text-muted fs-7">Nenhuma tag</div>';
        }
    }
    
    // Atualizar botões com IDs corretos
    const editContactBtn = sidebar.querySelector('#sidebar-edit-contact-btn');
    if (editContactBtn && conversation.contact_id) {
        editContactBtn.setAttribute('onclick', `editContact(${conversation.contact_id})`);
        editContactBtn.style.display = '';
    }
    
    const manageTagsBtn = sidebar.querySelector('#sidebar-manage-tags-btn');
    if (manageTagsBtn && conversation.id) {
        manageTagsBtn.setAttribute('onclick', `manageTags(${conversation.id})`);
        manageTagsBtn.style.display = '';
    }
    
    // Verificar se conversa está com agente de IA
    const escalateBtn = sidebar.querySelector('#sidebar-escalate-btn');
    if (escalateBtn && conversation.id) {
        // Verificar se conversa está com IA (buscar via API)
        checkIfConversationHasAI(conversation.id).then(hasAI => {
            if (hasAI) {
                escalateBtn.setAttribute('onclick', `escalateFromAI(${conversation.id})`);
                escalateBtn.style.display = '';
            } else {
                escalateBtn.style.display = 'none';
            }
        }).catch(() => {
            escalateBtn.style.display = 'none';
        });
    }
    
    const assignBtn = sidebar.querySelector('#sidebar-assign-btn');
    if (assignBtn && conversation.id) {
        assignBtn.setAttribute('onclick', `assignConversation(${conversation.id})`);
        assignBtn.style.display = '';
    }
    
    const departmentBtn = sidebar.querySelector('#sidebar-department-btn');
    if (departmentBtn && conversation.id) {
        departmentBtn.setAttribute('onclick', `changeDepartment(${conversation.id})`);
        departmentBtn.style.display = '';
    }
    
    const closeBtn = sidebar.querySelector('#sidebar-close-btn');
    const reopenBtn = sidebar.querySelector('#sidebar-reopen-btn');
    if (conversation.id) {
        if (conversation.status === 'open') {
            if (closeBtn) {
                closeBtn.setAttribute('onclick', `closeConversation(${conversation.id})`);
                closeBtn.style.display = '';
            }
            if (reopenBtn) reopenBtn.style.display = 'none';
        } else {
            if (reopenBtn) {
                reopenBtn.setAttribute('onclick', `reopenConversation(${conversation.id})`);
                reopenBtn.style.display = '';
            }
            if (closeBtn) closeBtn.style.display = 'none';
        }
    }
    
    const spamBtn = sidebar.querySelector('#sidebar-spam-btn');
    if (spamBtn && conversation.id) {
        spamBtn.setAttribute('onclick', `markAsSpam(${conversation.id})`);
        spamBtn.style.display = '';
    }
    
    const addNoteBtn = sidebar.querySelector('#sidebar-add-note-btn');
    if (addNoteBtn && conversation.id) {
        addNoteBtn.setAttribute('onclick', `addNote(${conversation.id})`);
        addNoteBtn.style.display = '';
    }
    
    // Atualizar timeline
    updateConversationTimeline(conversation.id);
}

// Atualizar timeline da conversa
function updateConversationTimeline(conversationId) {
    if (!conversationId) return;
    
    const timelineContainer = document.querySelector('#kt_tab_timeline .timeline');
    if (!timelineContainer) return;
    
    // Mostrar loading
    timelineContainer.innerHTML = `
        <div class="text-center py-5">
            <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
            <div class="text-muted fs-7 mt-2">Carregando timeline...</div>
        </div>
    `;
    
    // Buscar atividades da conversa (mensagens, atribuições, fechamentos, etc)
    fetch(`/${conversationId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.conversation) {
                timelineContainer.innerHTML = '<div class="text-muted fs-7 text-center py-5">Erro ao carregar timeline</div>';
                return;
            }
            
            const conv = data.conversation;
            const messages = data.messages || [];
            let timelineHtml = '';
            
            // Evento de criação
            if (conv.created_at) {
                const date = new Date(conv.created_at);
                timelineHtml += `
                    <div class="timeline-item">
                        <div class="timeline-line w-40px"></div>
                        <div class="timeline-icon symbol symbol-circle symbol-40px">
                            <div class="symbol-label bg-light-primary">
                                <i class="ki-duotone ki-message-text-2 fs-2 text-primary">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                            </div>
                        </div>
                        <div class="timeline-content mb-10">
                            <div class="fw-semibold text-gray-800">Conversa criada</div>
                            <div class="text-muted fs-7">${formatDateTime(conv.created_at)}</div>
                        </div>
                    </div>
                `;
            }
            
            // Atribuição
            if (conv.assigned_to && conv.assigned_at) {
                timelineHtml += `
                    <div class="timeline-item">
                        <div class="timeline-line w-40px"></div>
                        <div class="timeline-icon symbol symbol-circle symbol-40px">
                            <div class="symbol-label bg-light-info">
                                <i class="ki-duotone ki-profile-user fs-2 text-info">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </div>
                        </div>
                        <div class="timeline-content mb-10">
                            <div class="fw-semibold text-gray-800">Atribuída a ${escapeHtml(conv.agent_name || 'Agente')}</div>
                            <div class="text-muted fs-7">${formatDateTime(conv.assigned_at)}</div>
                        </div>
                    </div>
                `;
            }
            
            // Mensagens importantes (primeira e última)
            if (messages.length > 0) {
                const firstMsg = messages[0];
                const lastMsg = messages[messages.length - 1];
                
                if (firstMsg && firstMsg.id !== lastMsg?.id) {
                    timelineHtml += `
                        <div class="timeline-item">
                            <div class="timeline-line w-40px"></div>
                            <div class="timeline-icon symbol symbol-circle symbol-40px">
                                <div class="symbol-label bg-light-success">
                                    <i class="ki-duotone ki-message fs-2 text-success">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                            <div class="timeline-content mb-10">
                                <div class="fw-semibold text-gray-800">Primeira mensagem</div>
                                <div class="text-muted fs-7">${formatDateTime(firstMsg.created_at)}</div>
                            </div>
                        </div>
                    `;
                }
                
                if (lastMsg) {
                    timelineHtml += `
                        <div class="timeline-item">
                            <div class="timeline-line w-40px"></div>
                            <div class="timeline-icon symbol symbol-circle symbol-40px">
                                <div class="symbol-label bg-light-warning">
                                    <i class="ki-duotone ki-message fs-2 text-warning">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                            <div class="timeline-content mb-10">
                                <div class="fw-semibold text-gray-800">Última mensagem</div>
                                <div class="text-muted fs-7">${formatDateTime(lastMsg.created_at)}</div>
                            </div>
                        </div>
                    `;
                }
            }
            
            // Status
            if (conv.status === 'closed' && conv.closed_at) {
                timelineHtml += `
                    <div class="timeline-item">
                        <div class="timeline-line w-40px"></div>
                        <div class="timeline-icon symbol symbol-circle symbol-40px">
                            <div class="symbol-label bg-light-dark">
                                <i class="ki-duotone ki-cross-circle fs-2 text-dark">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </div>
                        </div>
                        <div class="timeline-content mb-10">
                            <div class="fw-semibold text-gray-800">Conversa fechada</div>
                            <div class="text-muted fs-7">${formatDateTime(conv.closed_at)}</div>
                        </div>
                    </div>
                `;
            }
            
            if (timelineHtml === '') {
                timelineHtml = '<div class="text-muted fs-7 text-center py-5">Nenhum evento na timeline</div>';
            }
            
            timelineContainer.innerHTML = timelineHtml;
        })
        .catch(error => {
            console.error('Erro ao carregar timeline:', error);
            timelineContainer.innerHTML = '<div class="text-muted fs-7 text-center py-5">Erro ao carregar timeline</div>';
        });
}

function formatDateTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', { 
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}


// Auto-scroll para última mensagem
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Restaurar estado do sidebar
    const sidebarOpen = localStorage.getItem('conversationSidebarOpen') === 'true';
    if (sidebarOpen) {
        document.getElementById('conversationSidebar')?.classList.add('open');
    }
    
    // Inicializar seletor rápido de templates
    initTemplateQuickSelect();
    
    // Inicializar busca de mensagens
    const messageSearchInput = document.getElementById('messageSearch');
    if (messageSearchInput) {
        messageSearchInput.addEventListener('keyup', function(e) {
            searchMessagesInConversation(e);
        });
    }
    
    // Handler do formulário de escalação
    const escalateForm = document.getElementById('escalateForm');
    if (escalateForm) {
        escalateForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const conversationId = document.getElementById('escalateConversationId').value;
            const agentId = document.getElementById('escalateAgent').value;
            
            if (!conversationId) {
                const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                                   document.body.classList.contains('dark-mode') ||
                                   window.matchMedia('(prefers-color-scheme: dark)').matches;
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'ID da conversa não encontrado',
                    colorScheme: isDarkMode ? 'dark' : 'light'
                });
                return;
            }
            
            const btn = escalateForm.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Escalando...';
            
            try {
                const formData = new FormData();
                if (agentId) {
                    formData.append('agent_id', agentId);
                }
                
                const response = await fetch(`/${conversationId}/escalate`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                                   document.body.classList.contains('dark-mode') ||
                                   window.matchMedia('(prefers-color-scheme: dark)').matches;
                
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('kt_modal_escalate')).hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: data.message || 'Conversa escalada com sucesso',
                        colorScheme: isDarkMode ? 'dark' : 'light',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // Recarregar detalhes da conversa
                    if (currentConversationId) {
                        loadConversationDetails(currentConversationId);
                    }
                    
                    // Recarregar lista de conversas
                    refreshConversationList();
                } else {
                    throw new Error(data.message || 'Erro ao escalar conversa');
                }
            } catch (error) {
                const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                                   document.body.classList.contains('dark-mode') ||
                                   window.matchMedia('(prefers-color-scheme: dark)').matches;
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: error.message || 'Erro ao escalar conversa',
                    colorScheme: isDarkMode ? 'dark' : 'light'
                });
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    }
    
    // Se houver conversa selecionada no carregamento inicial, atualizar sidebar
    const selectedConversationId = ;
    const selectedConversation = ;
    
    if (selectedConversationId) {
        // IMPORTANTE: Definir currentConversationId para que funcionalidades como Assistente IA funcionem
        currentConversationId = parseInt(selectedConversationId);
        
        // Marcar conversa como ativa na lista
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.remove('active');
        });
        const conversationItem = document.querySelector(`[data-conversation-id="${selectedConversationId}"]`);
        if (conversationItem) {
            conversationItem.classList.add('active');
        }
        
        // Se já temos dados da conversa do PHP, usar diretamente
        if (selectedConversation) {
            // Buscar tags da conversa
            fetch(`/${selectedConversationId}/tags`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                const tags = data.success && data.tags ? data.tags : [];
                // Atualizar sidebar com dados da conversa
                updateConversationSidebar(selectedConversation, tags);
                // Atualizar timeline
                updateConversationTimeline(selectedConversationId);
            })
            .catch(error => {
                console.error('Erro ao carregar tags:', error);
                // Mesmo sem tags, atualizar sidebar
                updateConversationSidebar(selectedConversation, []);
                updateConversationTimeline(selectedConversationId);
            });
        } else {
            // Se não temos dados completos, buscar via AJAX
            fetch(`/${selectedConversationId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.conversation) {
                    const tags = data.tags || [];
                    // Atualizar sidebar com dados da conversa
                    updateConversationSidebar(data.conversation, tags);
                    // Atualizar timeline
                    updateConversationTimeline(selectedConversationId);
                }
            })
            .catch(error => {
                console.error('Erro ao carregar conversa:', error);
            });
        }
    }
    
    // Suportar navegação pelo histórico do navegador
    window.addEventListener('popstate', function(event) {
        const urlParams = new URLSearchParams(window.location.search);
        const conversationId = urlParams.get('id');
        if (conversationId) {
            selectConversation(parseInt(conversationId));
        } else {
            // Se não tem ID, limpar chat e resetar currentConversationId
            currentConversationId = null;
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.innerHTML = `
                    <div class="d-flex align-items-center justify-content-center" style="height: 100%;">
                        <div class="text-center text-muted">
                            <i class="ki-duotone ki-chat fs-3x mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="fw-semibold">Selecione uma conversa</div>
                            <div class="fs-7">Escolha uma conversa da lista para começar</div>
                        </div>
                    </div>
                `;
            }
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
        }
    });
});

// Auto-expand textarea
document.getElementById('messageInput')?.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 150) + 'px';
});

// Enviar mensagem (Enter)
document.getElementById('messageInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

// Busca com debounce para AJAX
let conversationsSearchDebounce = null;

document.getElementById('kt_conversations_search')?.addEventListener('input', function(e) {
    // Limpar debounce anterior
    if (conversationsSearchDebounce) {
        clearTimeout(conversationsSearchDebounce);
    }
    
    // Debounce: aguardar 500ms após parar de digitar
    conversationsSearchDebounce = setTimeout(() => {
        applyFilters();
    }, 500);
});

document.getElementById('kt_conversations_search')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        if (conversationsSearchDebounce) {
            clearTimeout(conversationsSearchDebounce);
        }
        applyFilters();
    }
});

// Filtros
document.querySelectorAll('#filter_status, #filter_channel, #filter_department, #filter_tag').forEach(select => {
    if (select) {
        select.addEventListener('change', applyFilters);
    }
});

function applyFilters() {
    const search = document.getElementById('kt_conversations_search')?.value || '';
    const status = document.getElementById('filter_status')?.value || '';
    const channel = document.getElementById('filter_channel')?.value || '';
    const department = document.getElementById('filter_department')?.value || '';
    const tag = document.getElementById('filter_tag')?.value || '';
    
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (status) params.append('status', status);
    if (channel) params.append('channel', channel);
    if (department) params.append('department_id', department);
    if (tag) params.append('tag_id', tag);
    
    // Manter filtros avançados da URL e preservar ID da conversa selecionada
    const urlParams = new URLSearchParams(window.location.search);
    ['unanswered', 'answered', 'date_from', 'date_to', 'pinned', 'order_by', 'order_dir'].forEach(key => {
        if (urlParams.has(key)) {
            params.append(key, urlParams.get(key));
        }
    });
    
    // Preservar ID da conversa selecionada se houver
    const currentConversationId = urlParams.get('id');
    if (currentConversationId) {
        params.append('id', currentConversationId);
    }
    
    // Atualizar URL sem recarregar página
    const newUrl = '' + (params.toString() ? '?' + params.toString() : '');
    window.history.pushState({ filters: params.toString() }, '', newUrl);
    
    // Buscar conversas via AJAX
    refreshConversationList(params);
}

function refreshConversationList(params = null) {
    console.log('refreshConversationList chamado com params:', params);
    const conversationsList = document.querySelector('.conversations-list-items');
    if (!conversationsList) {
        console.error('Elemento .conversations-list-items não encontrado!');
        return;
    }
    
    // Mostrar loading
    const originalContent = conversationsList.innerHTML;
    conversationsList.innerHTML = `
        <div class="d-flex align-items-center justify-content-center py-10">
            <div class="text-center">
                <span class="spinner-border spinner-border-sm text-primary mb-3" role="status"></span>
                <div class="text-muted fs-7">Buscando conversas...</div>
            </div>
        </div>
    `;
    
    // Construir URL
    let url = '';
    if (params) {
        url += '?' + params.toString();
    } else {
        // Usar parâmetros da URL atual
        url += window.location.search;
    }
    
    // Adicionar header para retornar JSON
    url += (url.includes('?') ? '&' : '?') + 'format=json';
    
    console.log('Buscando conversas na URL:', url);
    
    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        // Verificar se a resposta é JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Resposta não é JSON:', text.substring(0, 500));
                console.error('URL:', url);
                console.error('Status:', response.status);
                throw new Error('Resposta não é JSON. Status: ' + response.status);
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('Resposta da busca:', data);
        
        if (!data.success || !data.conversations) {
            console.error('Erro na resposta:', data);
            conversationsList.innerHTML = `
                <div class="text-center py-10">
                    <div class="text-muted">Erro ao carregar conversas</div>
                    <div class="text-muted fs-7">${data.message || 'Erro desconhecido'}</div>
                </div>
            `;
            return;
        }
        
        const conversations = data.conversations;
        console.log('Conversas encontradas:', conversations.length);
        // Obter ID da conversa selecionada da URL atual
        const urlParams = new URLSearchParams(window.location.search);
        const selectedConversationId = urlParams.get('id') ? parseInt(urlParams.get('id')) : null;
        
        if (conversations.length === 0) {
            conversationsList.innerHTML = `
                <div class="text-center py-10">
                    <i class="ki-duotone ki-message-text fs-3x text-gray-400 mb-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <h5>Nenhuma conversa encontrada</h5>
                    <p class="text-muted">Tente ajustar os filtros de busca</p>
                </div>
            `;
            return;
        }
        
        // Renderizar conversas
        let html = '';
        conversations.forEach(conv => {
            const channelIcon = conv.channel === 'whatsapp' 
                ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#25D366" style="vertical-align: middle;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>'
                : conv.channel === 'email' ? '✉️' : '💬';
            
            const channelName = conv.channel === 'whatsapp' ? 'WhatsApp' : (conv.channel === 'email' ? 'Email' : 'Chat');
            
            const isActive = selectedConversationId == conv.id;
            const name = conv.contact_name || 'NN';
            const parts = name.split(' ');
            const initials = (parts[0].charAt(0) + (parts[1] ? parts[1].charAt(0) : '')).toUpperCase();
            
            const lastMessage = conv.last_message || '';
            const lastMessagePreview = lastMessage.length > 50 ? lastMessage.substring(0, 50) + '...' : lastMessage;
            
            const unreadCount = conv.unread_count || 0;
            const pinned = conv.pinned || 0;
            
            // Tags
            let tagsHtml = '';
            if (conv.tags_data) {
                const tags = conv.tags_data.split('|||');
                tags.slice(0, 2).forEach(tagStr => {
                    const [tagId, tagName, tagColor] = tagStr.split(':');
                    if (tagName) {
                        tagsHtml += `<span class="badge badge-sm" style="background-color: ${tagColor || '#009ef7'}20; color: ${tagColor || '#009ef7'};">${escapeHtml(tagName)}</span>`;
                    }
                });
            }
            
            html += `
                <div class="conversation-item ${isActive ? 'active' : ''} ${pinned ? 'pinned' : ''}" 
                     data-conversation-id="${conv.id}"
                     onclick="selectConversation(${conv.id})">
                    <div class="d-flex gap-3 w-100">
                        <div class="symbol symbol-45px flex-shrink-0">
                            <div class="symbol-label bg-light-primary text-primary fw-bold">${initials}</div>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="conversation-item-header">
                                <div class="conversation-item-name d-flex align-items-center gap-2">
                                    ${pinned ? '<i class="ki-duotone ki-pin fs-7 text-warning" title="Fixada"><span class="path1"></span><span class="path2"></span></i>' : ''}
                                    ${escapeHtml(name)}
                                </div>
                                <div class="conversation-item-time d-flex align-items-center gap-2">
                                    ${formatTime(conv.last_message_at || conv.updated_at)}
                                    <button type="button" class="btn btn-sm btn-icon btn-light p-0" 
                                            onclick="event.stopPropagation(); togglePin(${conv.id}, ${pinned ? 'true' : 'false'})" 
                                            title="${pinned ? 'Desfixar' : 'Fixar'}">
                                        <i class="ki-duotone ki-pin fs-7 ${pinned ? 'text-warning' : 'text-muted'}">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                </div>
                            </div>
                            <div class="conversation-item-preview">${escapeHtml(lastMessagePreview || 'Sem mensagens')}</div>
                            ${conv.search_match_type ? `
                                <div class="conversation-item-search-match mt-1">
                                    <span class="badge badge-sm badge-light-info">
                                        <i class="ki-duotone ki-magnifier fs-7 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        ${conv.search_match_type === 'name' ? 'Nome' : 
                                          conv.search_match_type === 'phone' ? 'Telefone' : 
                                          conv.search_match_type === 'email' ? 'Email' : 
                                          'Mensagem'}: 
                                        <span class="fw-semibold">${escapeHtml((conv.search_match_text || '').substring(0, 40))}${(conv.search_match_text || '').length > 40 ? '...' : ''}</span>
                                    </span>
                                </div>
                            ` : ''}
                            <div class="conversation-item-meta">
                                <span class="conversation-item-channel">${channelIcon} ${channelName}</span>
                                ${tagsHtml}
                                ${unreadCount > 0 ? `<span class="conversation-item-badge">${unreadCount}</span>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        conversationsList.innerHTML = html;
    })
    .catch(error => {
        console.error('Erro ao buscar conversas:', error);
        conversationsList.innerHTML = `
            <div class="text-center py-10">
                <div class="text-danger">Erro ao carregar conversas</div>
                <div class="text-muted fs-7 mt-2">${error.message || 'Erro desconhecido'}</div>
                <button class="btn btn-sm btn-light mt-3" onclick="location.reload()">Recarregar página</button>
            </div>
        `;
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'Agora';
    if (diffMins < 60) return `${diffMins}min`;
    if (diffHours < 24) return `${diffHours}h`;
    if (diffDays < 7) return `${diffDays}d`;
    
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

function openAdvancedFilters() {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_advanced_filters'));
    modal.show();
}

function applyAdvancedFilters() {
    const form = document.getElementById('advancedFiltersForm');
    const formData = new FormData(form);
    
    const params = new URLSearchParams();
    
    // Filtros básicos (manter)
    const search = document.getElementById('kt_conversations_search')?.value || '';
    const status = document.getElementById('filter_status')?.value || '';
    const channel = document.getElementById('filter_channel')?.value || '';
    const department = document.getElementById('filter_department')?.value || '';
    const tag = document.getElementById('filter_tag')?.value || '';
    
    if (search) params.append('search', search);
    if (status) params.append('status', status);
    if (channel) params.append('channel', channel);
    if (department) params.append('department_id', department);
    if (tag) params.append('tag_id', tag);
    
    // Filtros avançados
    const responseStatus = formData.get('response_status');
    if (responseStatus === 'unanswered') {
        params.append('unanswered', '1');
    } else if (responseStatus === 'answered') {
        params.append('answered', '1');
    }
    
    const dateFrom = formData.get('date_from');
    if (dateFrom) params.append('date_from', dateFrom);
    
    const dateTo = formData.get('date_to');
    if (dateTo) params.append('date_to', dateTo);
    
    const pinned = formData.get('pinned');
    if (pinned !== null && pinned !== '') {
        params.append('pinned', pinned);
    }
    
    const orderBy = formData.get('order_by');
    if (orderBy) params.append('order_by', orderBy);
    
    const orderDir = formData.get('order_dir');
    if (orderDir) params.append('order_dir', orderDir);
    
    // Fechar modal e aplicar filtros
    bootstrap.Modal.getInstance(document.getElementById('kt_modal_advanced_filters')).hide();
    window.location.href = '' + (params.toString() ? '?' + params.toString() : '');
}

function clearAllFilters() {
    window.location.href = '';
}

function togglePin(conversationId, isPinned) {
    const url = isPinned 
        ? `/${conversationId}/unpin`
        : `/${conversationId}/pin`;
    
    fetch(url, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recarregar página para atualizar lista
            window.location.reload();
        } else {
            alert('Erro ao ' + (isPinned ? 'desfixar' : 'fixar') + ' conversa: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao ' + (isPinned ? 'desfixar' : 'fixar') + ' conversa');
    });
}

// Buscar mensagens dentro da conversa
let messageSearchTimeout = null;
// Variáveis globais para navegação de busca
let messageSearchResults = [];
let currentSearchIndex = -1;
let currentSearchTerm = '';
let messageSearchFilters = {
    message_type: null,
    sender_type: null,
    sender_id: null,
    date_from: null,
    date_to: null,
    has_attachments: null
};

// Função para destacar texto encontrado
function highlightSearchTerm(text, searchTerm) {
    if (!searchTerm) return escapeHtml(text);
    
    const regex = new RegExp(`(${escapeRegex(searchTerm)})`, 'gi');
    return escapeHtml(text).replace(regex, '<mark class="bg-warning text-dark" style="padding: 2px 4px; border-radius: 3px;">$1</mark>');
}

// Função para escapar caracteres especiais em regex
function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function searchMessagesInConversation(event) {
    const searchInput = event.target;
    const searchTerm = searchInput.value.trim();
    const resultsDiv = document.getElementById('messageSearchResults');
    
    // Obter ID da conversa da URL ou variável global
    let conversationId = currentConversationId;
    if (!conversationId) {
        const urlParams = new URLSearchParams(window.location.search);
        conversationId = urlParams.get('id') ? parseInt(urlParams.get('id')) : null;
    }
    
    // Limpar timeout anterior
    if (messageSearchTimeout) {
        clearTimeout(messageSearchTimeout);
    }
    
    // Verificar se há filtros ativos
    const hasActiveFilters = Object.values(messageSearchFilters).some(v => v !== null && v !== '');
    
    // Se campo vazio e não há filtros, esconder resultados
    if (!searchTerm && !hasActiveFilters) {
        resultsDiv.classList.add('d-none');
        messageSearchResults = [];
        currentSearchIndex = -1;
        currentSearchTerm = '';
        return;
    }
    
    // Aguardar 300ms antes de buscar (debounce)
    messageSearchTimeout = setTimeout(() => {
        if (!conversationId) {
            resultsDiv.innerHTML = '<div class="p-3 text-muted">Nenhuma conversa selecionada</div>';
            resultsDiv.classList.remove('d-none');
            return;
        }
        
        // Construir URL com filtros
        const params = new URLSearchParams();
        if (searchTerm) {
            params.append('q', searchTerm);
        }
        
        // Adicionar filtros ativos
        Object.keys(messageSearchFilters).forEach(key => {
            const value = messageSearchFilters[key];
            if (value !== null && value !== '') {
                params.append(key, value);
            }
        });
        
        const url = `/${conversationId}/search-messages?${params.toString()}`;
        
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages && data.messages.length > 0) {
                // Salvar resultados para navegação
                messageSearchResults = data.messages;
                currentSearchTerm = searchTerm;
                currentSearchIndex = -1;
                
                const total = data.messages.length;
                let html = `
                    <div class="p-2 border-bottom bg-light d-flex justify-content-between align-items-center">
                        <small class="text-muted fw-semibold">${total} mensagem(ns) encontrada(s)</small>
                        <div class="d-flex gap-2 align-items-center">
                            <button class="btn btn-sm btn-icon btn-light-primary" onclick="navigateSearchResults(-1)" title="Anterior (↑)" id="searchPrevBtn" style="padding: 2px 6px;">
                                <i class="ki-duotone ki-up fs-6">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </button>
                            <small class="text-muted" id="searchCounter">-</small>
                            <button class="btn btn-sm btn-icon btn-light-primary" onclick="navigateSearchResults(1)" title="Próximo (↓)" id="searchNextBtn" style="padding: 2px 6px;">
                                <i class="ki-duotone ki-down fs-6">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </button>
                        </div>
                    </div>
                `;
                
                data.messages.forEach((msg, index) => {
                    const content = msg.content || '';
                    // Encontrar posição do termo no conteúdo para mostrar contexto relevante
                    const searchLower = searchTerm.toLowerCase();
                    const contentLower = content.toLowerCase();
                    const termIndex = contentLower.indexOf(searchLower);
                    
                    let preview = '';
                    if (termIndex >= 0) {
                        // Mostrar contexto ao redor do termo encontrado
                        const start = Math.max(0, termIndex - 30);
                        const end = Math.min(content.length, termIndex + searchTerm.length + 30);
                        preview = content.substring(start, end);
                        if (start > 0) preview = '...' + preview;
                        if (end < content.length) preview = preview + '...';
                    } else {
                        preview = content.length > 80 ? content.substring(0, 80) + '...' : content;
                    }
                    
                    const time = msg.created_at ? new Date(msg.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
                    html += `
                        <div class="message-search-result p-3 border-bottom cursor-pointer hover-bg-light" 
                             data-message-id="${msg.id}" 
                             data-index="${index}"
                             onclick="selectSearchResult(${index}, true)">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="fw-semibold">${escapeHtml(msg.sender_name || 'Remetente')}</span>
                                <span class="text-muted small">${time}</span>
                            </div>
                            <div class="text-muted small">${highlightSearchTerm(preview, searchTerm)}</div>
                        </div>
                    `;
                });
                resultsDiv.innerHTML = html;
                updateSearchCounter();
            } else {
                resultsDiv.innerHTML = '<div class="p-3 text-muted">Nenhuma mensagem encontrada</div>';
                messageSearchResults = [];
                currentSearchIndex = -1;
            }
            resultsDiv.classList.remove('d-none');
        })
        .catch(error => {
            console.error('Erro ao buscar mensagens:', error);
            resultsDiv.innerHTML = '<div class="p-3 text-danger">Erro ao buscar mensagens</div>';
            resultsDiv.classList.remove('d-none');
            messageSearchResults = [];
            currentSearchIndex = -1;
        });
    }, 300);
}

// Selecionar resultado da busca
function selectSearchResult(index, closeDropdown = false) {
    if (!messageSearchResults || index < 0 || index >= messageSearchResults.length) return;
    
    const msg = messageSearchResults[index];
    currentSearchIndex = index;
    
    // Scroll até a mensagem
    scrollToMessage(msg.id);
    
    // Atualizar contador e destacar item
    updateSearchCounter();
    highlightSearchResultItem(index);
    
    // Fechar dropdown apenas se solicitado (ex: clique direto)
    if (closeDropdown) {
        document.getElementById('messageSearchResults').classList.add('d-none');
        document.getElementById('messageSearch').blur();
    }
}

// Navegar entre resultados (próximo/anterior)
function navigateSearchResults(direction) {
    if (!messageSearchResults || messageSearchResults.length === 0) return;
    
    // Atualizar índice
    currentSearchIndex += direction;
    
    // Limites
    if (currentSearchIndex < 0) {
        currentSearchIndex = messageSearchResults.length - 1;
    } else if (currentSearchIndex >= messageSearchResults.length) {
        currentSearchIndex = 0;
    }
    
    // Selecionar resultado
    selectSearchResult(currentSearchIndex);
    
    // Atualizar contador
    updateSearchCounter();
    
    // Destacar item no dropdown
    highlightSearchResultItem(currentSearchIndex);
}

// Atualizar contador de busca
function updateSearchCounter() {
    const counter = document.getElementById('searchCounter');
    if (counter && messageSearchResults.length > 0) {
        const current = currentSearchIndex >= 0 ? currentSearchIndex + 1 : '-';
        counter.textContent = `${current} / ${messageSearchResults.length}`;
    }
}

// Destacar item selecionado no dropdown
function highlightSearchResultItem(index) {
    const resultsDiv = document.getElementById('messageSearchResults');
    if (!resultsDiv) return;
    
    // Remover destaque anterior
    resultsDiv.querySelectorAll('.message-search-result').forEach(item => {
        item.classList.remove('bg-light-primary');
    });
    
    // Destacar item atual
    const currentItem = resultsDiv.querySelector(`[data-index="${index}"]`);
    if (currentItem) {
        currentItem.classList.add('bg-light-primary');
        // Scroll do item para dentro da view
        currentItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// Adicionar navegação por teclado
document.addEventListener('DOMContentLoaded', function() {
    const messageSearchInput = document.getElementById('messageSearch');
    if (messageSearchInput) {
        messageSearchInput.addEventListener('keydown', function(e) {
            const resultsDiv = document.getElementById('messageSearchResults');
            if (!resultsDiv || resultsDiv.classList.contains('d-none')) return;
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                navigateSearchResults(1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                navigateSearchResults(-1);
            } else if (e.key === 'Enter' && currentSearchIndex >= 0) {
                e.preventDefault();
                selectSearchResult(currentSearchIndex, true);
            } else if (e.key === 'Escape') {
                resultsDiv.classList.add('d-none');
                messageSearchInput.blur();
            }
        });
    }
    
    // Inicializar lazy loading
    initLazyLoading();
});

// Inicializar lazy loading de imagens e vídeos
function initLazyLoading() {
    // Verificar se Intersection Observer está disponível
    if (!('IntersectionObserver' in window)) {
        // Fallback: carregar todas as imagens/vídeos imediatamente
        document.querySelectorAll('.lazy-image[data-src]').forEach(img => {
            img.src = img.dataset.src;
            img.classList.add('loaded');
        });
        document.querySelectorAll('.lazy-video-container').forEach(container => {
            loadVideo(container);
        });
        return;
    }
    
    // Configurar Intersection Observer para imagens
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                const container = img.closest('.lazy-image-container');
                const spinner = container ? container.querySelector('.lazy-loading-spinner') : null;
                
                if (spinner) spinner.style.display = 'block';
                img.classList.add('loading');
                
                const imageUrl = img.dataset.src;
                const tempImg = new Image();
                
                tempImg.onload = function() {
                    img.src = imageUrl;
                    img.classList.remove('loading');
                    img.classList.add('loaded');
                    if (spinner) spinner.style.display = 'none';
                    observer.unobserve(img);
                };
                
                tempImg.onerror = function() {
                    img.classList.remove('loading');
                    if (spinner) spinner.style.display = 'none';
                    img.style.opacity = '0.5';
                    observer.unobserve(img);
                };
                
                tempImg.src = imageUrl;
            }
        });
    }, {
        rootMargin: '50px' // Começar a carregar 50px antes de ficar visível
    });
    
    // Observar todas as imagens lazy
    document.querySelectorAll('.lazy-image[data-src]').forEach(img => {
        imageObserver.observe(img);
    });
    
    // Configurar Intersection Observer para vídeos
    const videoObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const container = entry.target;
                loadVideo(container);
                observer.unobserve(container);
            }
        });
    }, {
        rootMargin: '100px' // Vídeos começam a carregar mais cedo
    });
    
    // Observar todos os containers de vídeo lazy
    document.querySelectorAll('.lazy-video-container').forEach(container => {
        videoObserver.observe(container);
        
        // Adicionar clique no placeholder para carregar imediatamente
        const placeholder = container.querySelector('.lazy-video-placeholder');
        if (placeholder) {
            placeholder.addEventListener('click', function() {
                loadVideo(container);
            });
        }
    });
}

// Carregar vídeo quando ficar visível ou ao clicar
function loadVideo(container) {
    const video = container.querySelector('video');
    const placeholder = container.querySelector('.lazy-video-placeholder');
    const src = container.dataset.src;
    const type = container.dataset.type;
    
    if (!video || !src) return;
    
    // Se já foi carregado, não fazer nada
    if (video.classList.contains('loaded')) return;
    
    // Carregar vídeo
    const source = video.querySelector('source');
    if (source) {
        source.src = src;
        if (type) source.type = type;
    }
    
    video.load();
    video.classList.add('loaded');
    
    if (placeholder) {
        placeholder.classList.add('loaded');
    }
    
    // Quando vídeo estiver pronto, mostrar
    video.addEventListener('loadeddata', function() {
        video.style.display = 'block';
        if (placeholder) {
            placeholder.style.display = 'none';
        }
    }, { once: true });
}

// Função para observar novos elementos adicionados dinamicamente
function observeNewLazyElements(container) {
    if (!container) return;
    
    // Observar novas imagens
    const newImages = container.querySelectorAll('.lazy-image[data-src]:not(.lazy-observed)');
    if (newImages.length > 0 && 'IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    const container = img.closest('.lazy-image-container');
                    const spinner = container ? container.querySelector('.lazy-loading-spinner') : null;
                    
                    if (spinner) spinner.style.display = 'block';
                    img.classList.add('loading');
                    
                    const imageUrl = img.dataset.src;
                    const tempImg = new Image();
                    
                    tempImg.onload = function() {
                        img.src = imageUrl;
                        img.classList.remove('loading');
                        img.classList.add('loaded');
                        if (spinner) spinner.style.display = 'none';
                        observer.unobserve(img);
                    };
                    
                    tempImg.onerror = function() {
                        img.classList.remove('loading');
                        if (spinner) spinner.style.display = 'none';
                        img.style.opacity = '0.5';
                        observer.unobserve(img);
                    };
                    
                    tempImg.src = imageUrl;
                }
            });
        }, { rootMargin: '50px' });
        
        newImages.forEach(img => {
            img.classList.add('lazy-observed');
            imageObserver.observe(img);
        });
    }
    
    // Observar novos vídeos
    const newVideos = container.querySelectorAll('.lazy-video-container:not(.lazy-observed)');
    if (newVideos.length > 0 && 'IntersectionObserver' in window) {
        const videoObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const container = entry.target;
                    loadVideo(container);
                    observer.unobserve(container);
                }
            });
        }, { rootMargin: '100px' });
        
        newVideos.forEach(container => {
            container.classList.add('lazy-observed');
            videoObserver.observe(container);
            
            const placeholder = container.querySelector('.lazy-video-placeholder');
            if (placeholder) {
                placeholder.addEventListener('click', function() {
                    loadVideo(container);
                });
            }
        });
    }
}

// Fechar resultados ao clicar fora
document.addEventListener('click', function(event) {
    const searchInput = document.getElementById('messageSearch');
    const resultsDiv = document.getElementById('messageSearchResults');
    if (searchInput && resultsDiv && !searchInput.contains(event.target) && !resultsDiv.contains(event.target)) {
        resultsDiv.classList.add('d-none');
    }
});

// Mostrar modal de filtros de busca
function showMessageSearchFilters() {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_message_search_filters'));
    modal.show();
    
    // Preencher filtros atuais
    document.getElementById('filterMessageType').value = messageSearchFilters.message_type || '';
    document.getElementById('filterSenderType').value = messageSearchFilters.sender_type || '';
    document.getElementById('filterDateFrom').value = messageSearchFilters.date_from || '';
    document.getElementById('filterDateTo').value = messageSearchFilters.date_to || '';
    document.getElementById('filterHasAttachments').checked = messageSearchFilters.has_attachments === true;
}

// Aplicar filtros de busca
function applyMessageSearchFilters() {
    // Obter valores dos filtros
    messageSearchFilters = {
        message_type: document.getElementById('filterMessageType').value || null,
        sender_type: document.getElementById('filterSenderType').value || null,
        sender_id: null, // Pode ser implementado depois com seleção de agente específico
        date_from: document.getElementById('filterDateFrom').value || null,
        date_to: document.getElementById('filterDateTo').value || null,
        has_attachments: document.getElementById('filterHasAttachments').checked || null
    };
    
    // Fechar modal
    bootstrap.Modal.getInstance(document.getElementById('kt_modal_message_search_filters')).hide();
    
    // Atualizar indicador visual de filtros ativos
    updateFiltersIndicator();
    
    // Se houver termo de busca, refazer busca com filtros
    const searchInput = document.getElementById('messageSearch');
    if (searchInput && searchInput.value.trim()) {
        searchMessagesInConversation({ target: searchInput });
    } else {
        // Se não houver termo, mostrar que filtros estão ativos
        const hasActiveFilters = Object.values(messageSearchFilters).some(v => v !== null && v !== '');
        if (hasActiveFilters) {
            // Mostrar mensagem informando que filtros estão ativos
            const resultsDiv = document.getElementById('messageSearchResults');
            if (resultsDiv) {
                resultsDiv.innerHTML = '<div class="p-3 text-muted text-center">Digite um termo de busca para aplicar os filtros</div>';
                resultsDiv.classList.remove('d-none');
            }
        }
    }
}

// Limpar filtros de busca
function clearMessageSearchFilters() {
    messageSearchFilters = {
        message_type: null,
        sender_type: null,
        sender_id: null,
        date_from: null,
        date_to: null,
        has_attachments: null
    };
    
    // Limpar formulário
    document.getElementById('filterMessageType').value = '';
    document.getElementById('filterSenderType').value = '';
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    document.getElementById('filterHasAttachments').checked = false;
    
    // Atualizar indicador
    updateFiltersIndicator();
    
    // Refazer busca se houver termo
    const searchInput = document.getElementById('messageSearch');
    if (searchInput && searchInput.value.trim()) {
        searchMessagesInConversation({ target: searchInput });
    }
}

// Atualizar indicador visual de filtros ativos
function updateFiltersIndicator() {
    const filtersBtn = document.getElementById('messageSearchFiltersBtn');
    if (!filtersBtn) return;
    
    const hasActiveFilters = Object.values(messageSearchFilters).some(v => v !== null && v !== '');
    
    if (hasActiveFilters) {
        filtersBtn.classList.add('btn-primary');
        filtersBtn.classList.remove('btn-light-primary');
        filtersBtn.setAttribute('title', 'Filtros ativos - Clique para editar');
    } else {
        filtersBtn.classList.remove('btn-primary');
        filtersBtn.classList.add('btn-light-primary');
        filtersBtn.setAttribute('title', 'Filtros avançados');
    }
}

// Adicionar mensagem ao chat dinamicamente
function addMessageToChat(message) {
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return null;

    // Evitar duplicação: se já existe mensagem com o mesmo ID, verificar se precisa reposicionar
    if (message.id) {
        const existing = chatMessages.querySelector(`[data-message-id="${message.id}"]`);
        if (existing) {
            // Verificar se precisa reposicionar baseado no timestamp
            const messageTimestamp = message.created_at ? new Date(message.created_at).getTime() : Date.now();
            const existingTimestamp = existing.getAttribute('data-timestamp');
            
            if (existingTimestamp) {
                const existingTime = parseInt(existingTimestamp);
                if (!isNaN(existingTime) && Math.abs(messageTimestamp - existingTime) > 1000) {
                    // Timestamp diferente, atualizar e reposicionar
                    existing.setAttribute('data-timestamp', messageTimestamp);
                    existing.remove(); // Remover do DOM para reposicionar
                } else {
                    // Mesma mensagem, não precisa reposicionar
                    return existing;
                }
            } else {
                // Mensagem existe mas sem timestamp, apenas retornar
                return existing;
            }
        }
    }
    
    const messageDiv = document.createElement('div');
    
    if (message.type === 'system') {
        messageDiv.className = 'chat-message system';
        messageDiv.innerHTML = `
            <div class="message-content">
                <div class="message-bubble">
                    <i class="ki-duotone ki-information fs-5 me-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    ${escapeHtml(message.content)}
                </div>
            </div>
        `;
    } else if (message.type === 'note') {
        // Notas internas ficam alinhadas à direita como mensagens enviadas
        messageDiv.className = 'chat-message note outgoing';
        const senderName = message.sender_name || 'Sistema';
        messageDiv.innerHTML = `
            <div class="message-content">
                <div class="message-bubble">
                    <div class="note-header">
                        <i class="ki-duotone ki-note fs-6">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Nota Interna • ${escapeHtml(senderName)}
                    </div>
                    ${nl2br(escapeHtml(message.content))}
                </div>
                <div class="message-time">${formatTime(message.created_at)}</div>
            </div>
        `;
    } else {
        const isIncoming = message.direction === 'incoming';
        messageDiv.className = `chat-message ${isIncoming ? 'incoming' : 'outgoing'}`;
        
        // Adicionar ID da mensagem como atributo
        if (message.id) {
            messageDiv.setAttribute('data-message-id', message.id);
            lastMessageId = Math.max(lastMessageId || 0, message.id);
        }
        
        // Verificar se é mensagem de IA
        const isAIMessage = message.ai_agent_id !== null && message.ai_agent_id !== undefined;
        const aiAgentName = message.ai_agent_name || 'Assistente IA';
        
        let avatarHtml = '';
        if (isIncoming) {
            const initials = getInitials(message.sender_name || 'NN');
            avatarHtml = `<div class="message-avatar">${initials}</div>`;
        }
        
        // Badge de IA se for mensagem de agente de IA
        let aiBadgeHtml = '';
        if (isAIMessage && !isIncoming) {
            aiBadgeHtml = `
                <div class="ai-message-badge" title="Mensagem enviada por ${escapeHtml(aiAgentName)}">
                    <i class="ki-duotone ki-robot fs-7">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                    </i>
                    <span class="ai-badge-text">${escapeHtml(aiAgentName)}</span>
                </div>
            `;
        }
        
        // Função helper para renderizar status
        function renderMessageStatusHtml(message) {
            if (!message || message.direction === 'incoming') {
                return '';
            }
            
            const status = message.status || 'sent';
            const readAt = message.read_at || null;
            const deliveredAt = message.delivered_at || null;
            const errorMessage = message.error_message || null;
            
            // Se houver erro
            if (status === 'failed' || errorMessage) {
                const errorText = escapeHtml(errorMessage || 'Erro ao enviar');
                return `<span class="message-status message-status-error" title="${errorText}">
                    <i class="ki-duotone ki-cross-circle fs-6">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <span class="message-status-label">Erro</span>
                </span>`;
            }
            
            // Se foi lida
            if (readAt) {
                const readDate = new Date(readAt).toLocaleString('pt-BR');
                return `<span class="message-status" title="Lida em ${readDate}">
                    <i class="ki-duotone ki-double-check fs-6" style="color: #0088cc;">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <span class="message-status-label">Lida</span>
                </span>`;
            }
            
            // Se foi entregue
            if (deliveredAt) {
                const deliveredDate = new Date(deliveredAt).toLocaleString('pt-BR');
                return `<span class="message-status" title="Entregue em ${deliveredDate}">
                    <i class="ki-duotone ki-double-check fs-6 text-white">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <span class="message-status-label">Entregue</span>
                </span>`;
            }
            
            // Enviado (padrão)
            return `<span class="message-status" title="Enviado">
                <i class="ki-duotone ki-check fs-6 text-white">
                    <span class="path1"></span>
                </i>
                <span class="message-status-label">Enviado</span>
            </span>`;
        }
        
        let statusHtml = renderMessageStatusHtml(message);
        
        // Renderizar anexos se houver
        let attachmentsHtml = '';
        if (message.attachments && Array.isArray(message.attachments) && message.attachments.length > 0) {
            message.attachments.forEach(att => {
                attachmentsHtml += renderAttachmentHtml(att);
            });
        }
        
        // Verificar se é mensagem citada/reply
        const hasQuote = message.quoted_message_id || (message.content && message.content.startsWith('↩️'));
        let quotedHtml = '';
        let actualContent = message.content || '';
        
        if (hasQuote) {
            let quotedText = '';
            let quotedSender = 'Remetente';
            let quotedMessageId = null;
            
            // Priorizar campos separados (novo formato)
            if (message.quoted_message_id) {
                quotedMessageId = message.quoted_message_id;
                quotedSender = message.quoted_sender_name || 'Remetente';
                quotedText = message.quoted_text || '';
                // Limitar texto citado
                if (quotedText.length > 100) {
                    quotedText = quotedText.substring(0, 100) + '...';
                }
                // Content não foi modificado no novo formato
                actualContent = message.content || '';
            } else {
                // Formato antigo (↩️ no content)
                const lines = actualContent.split('\n', 2);
                quotedText = lines[0].substring(2); // Remove "↩️ "
                quotedSender = message.quoted_sender_name || 'Remetente';
                actualContent = lines[1] || '';
                quotedMessageId = null;
            }
            
            quotedHtml = `
                <div class="quoted-message" onclick="console.log('Quoted message clicado (JS), ID:', ${quotedMessageId || 'null'}); ${quotedMessageId ? `scrollToMessage(${quotedMessageId})` : 'console.log(\'Sem ID para scroll\')'}" title="${quotedMessageId ? 'Clique para ver a mensagem original' : 'Mensagem original não disponível'}" data-quoted-id="${quotedMessageId || ''}">
                    <div class="quoted-message-header">${escapeHtml(quotedSender)}</div>
                    <div class="quoted-message-content">${escapeHtml(quotedText.length > 60 ? quotedText.substring(0, 60) + '...' : quotedText)}</div>
                </div>
            `;
        }
        
        // Adicionar botões de ação
        const replyBtn = `
            <div class="message-actions">
                <button class="message-actions-btn" onclick="replyToMessage(${message.id || 0}, '${escapeHtml(message.sender_name || 'Remetente')}', '${escapeHtml((message.content || '').substring(0, 100))}')" title="Responder">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 10 4 15 9 20"></polyline>
                        <path d="M20 4v7a4 4 0 0 1-4 4H4"></path>
                    </svg>
                </button>
                <button class="message-actions-btn" onclick="forwardMessage(${message.id || 0})" title="Encaminhar">
                    <i class="ki-duotone ki-arrow-right fs-6">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </button>
            </div>
        `;
        
        // Verificar se é apenas áudio (sem texto e sem outros anexos)
        const isAudioOnly = attachmentsHtml && attachmentsHtml.includes('audio-attachment') && !actualContent && !quotedHtml;
        const bubbleClass = isAudioOnly ? 'message-bubble audio-only' : 'message-bubble';
        
        messageDiv.innerHTML = `
            ${avatarHtml}
            <div class="message-content">
                ${replyBtn}
                ${aiBadgeHtml}
                <div class="${bubbleClass} ${isAIMessage ? 'ai-message' : ''}">
                    ${quotedHtml}
                    ${attachmentsHtml}
                    ${actualContent ? '<div class="' + ((attachmentsHtml || quotedHtml) ? 'mt-2' : '') + '">' + nl2br(escapeHtml(actualContent)) + '</div>' : ''}
                </div>
                <div class="message-time">
                    ${formatTime(message.created_at)}${statusHtml}
                </div>
            </div>
        `;
    }
    
    // Armazenar timestamp no elemento para ordenação
    const messageTimestamp = message.created_at ? new Date(message.created_at).getTime() : Date.now();
    messageDiv.setAttribute('data-timestamp', messageTimestamp);
    
    // Inserir mensagem na posição correta baseada no horário (ordem cronológica crescente)
    const allMessages = Array.from(chatMessages.children);
    
    // Encontrar posição correta para inserir (ordem crescente por timestamp)
    let insertPosition = null;
    for (let i = 0; i < allMessages.length; i++) {
        const existingMsg = allMessages[i];
        const existingTimestamp = existingMsg.getAttribute('data-timestamp');
        
        if (existingTimestamp) {
            const existingTime = parseInt(existingTimestamp);
            if (!isNaN(existingTime)) {
                // Comparar timestamps: se nova mensagem é mais antiga ou igual, inserir antes
                if (messageTimestamp <= existingTime) {
                    insertPosition = existingMsg;
                    break;
                }
            }
        } else {
            // Se mensagem existente não tem timestamp, tentar pelo ID (fallback)
            const existingId = existingMsg.getAttribute('data-message-id');
            const newId = message.id;
            if (existingId && newId && !existingId.startsWith('temp_') && !newId.toString().startsWith('temp_')) {
                const existingIdNum = parseInt(existingId);
                const newIdNum = parseInt(newId);
                if (!isNaN(existingIdNum) && !isNaN(newIdNum) && newIdNum <= existingIdNum) {
                    insertPosition = existingMsg;
                    break;
                }
            }
        }
    }
    
    // Inserir na posição correta ou no final
    if (insertPosition) {
        chatMessages.insertBefore(messageDiv, insertPosition);
    } else {
        chatMessages.appendChild(messageDiv);
    }
    
    // Atualizar último ID de mensagem conhecido
    if (message.id) {
        lastMessageId = Math.max(lastMessageId || 0, message.id);
    }
    
    // Scroll para última mensagem apenas se estiver no final do chat
    const isAtBottom = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 100;
    if (isAtBottom && chatMessages && chatMessages.scrollHeight !== undefined) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Observar novos elementos lazy (imagens e vídeos) na mensagem recém-adicionada
    observeNewLazyElements(messageDiv);
    
    return messageDiv;
}

function formatTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) {
        return 'Agora';
    } else if (diff < 3600000) {
        return Math.floor(diff / 60000) + 'min';
    } else {
        return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }
}

function getInitials(name) {
    const parts = name.split(' ');
    return parts.length > 1 
        ? (parts[0][0] + parts[1][0]).toUpperCase()
        : name.substring(0, 2).toUpperCase();
}

function nl2br(text) {
    return text.replace(/\n/g, '<br>');
}

// Variável global para armazenar mensagem sendo respondida
let replyingToMessage = null;

// Função para responder uma mensagem
function replyToMessage(messageId, senderName, messageText) {
    replyingToMessage = {
        id: messageId,
        sender_name: senderName,
        text: messageText
    };
    
    // Mostrar preview
    const preview = document.getElementById('replyPreview');
    const header = document.getElementById('replyPreviewHeader');
    const text = document.getElementById('replyPreviewText');
    
    if (preview && header && text) {
        header.textContent = senderName;
        text.textContent = messageText.length > 60 ? messageText.substring(0, 60) + '...' : messageText;
        preview.classList.remove('d-none');
    }
    
    // Focar no input
    const input = document.getElementById('messageInput');
    if (input) {
        input.focus();
    }
}

// Cancelar reply
function cancelReply() {
    replyingToMessage = null;
    const preview = document.getElementById('replyPreview');
    if (preview) {
        preview.classList.add('d-none');
    }
}

// Encaminhar mensagem
async function forwardMessage(messageId) {
    if (!messageId) return;
    
    const conversationId = ;
    if (!conversationId) {
        alert('Selecione uma conversa primeiro');
        return;
    }
    
    // Buscar lista de conversas para encaminhamento
    try {
        const response = await fetch(`?exclude=${conversationId}`);
        
        // Verificar se a resposta é JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Resposta não é JSON:', text.substring(0, 200));
            throw new Error('Resposta inválida do servidor. Verifique o console para mais detalhes.');
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Erro ao carregar conversas');
        }
        
        if (!data.conversations) {
            data.conversations = [];
        }
        
        // Criar HTML do modal
        let conversationsHtml = '';
        if (data.conversations.length === 0) {
            conversationsHtml = '<div class="text-center text-muted p-4">Nenhuma conversa disponível para encaminhamento</div>';
        } else {
            conversationsHtml = '<div class="forward-conversations-list" style="max-height: 400px; overflow-y: auto;">';
            data.conversations.forEach(conv => {
                const channelIcon = conv.channel === 'whatsapp' 
                    ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#25D366" style="vertical-align: middle;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>' 
                    : conv.channel === 'email' ? '✉️' : '💬';
                const lastMsg = conv.last_message ? (conv.last_message.length > 50 ? conv.last_message.substring(0, 50) + '...' : conv.last_message) : 'Sem mensagens';
                conversationsHtml += `
                    <div class="forward-conversation-item" onclick="selectForwardConversation(${conv.id}, ${messageId})" style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); cursor: pointer; transition: background 0.2s;">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <div class="fw-bold">${escapeHtml(conv.contact_name)}</div>
                                <div class="text-muted small" style="display: flex; align-items: center; gap: 4px;">${channelIcon} <span>${escapeHtml(lastMsg)}</span></div>
                            </div>
                            <i class="ki-duotone ki-arrow-right fs-4 text-muted">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                `;
            });
            conversationsHtml += '</div>';
        }
        
        // Mostrar modal
        Swal.fire({
            title: 'Encaminhar Mensagem',
            html: `
                <div class="text-start">
                    <p class="mb-3">Selecione a conversa para onde deseja encaminhar:</p>
                    ${conversationsHtml}
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Cancelar',
            cancelButtonText: 'Fechar',
            confirmButtonColor: '#6c757d',
            width: '500px',
            customClass: {
                popup: 'forward-modal'
            },
            didOpen: () => {
                // Adicionar hover effect
                document.querySelectorAll('.forward-conversation-item').forEach(item => {
                    item.addEventListener('mouseenter', function() {
                        this.style.background = 'rgba(255,255,255,0.05)';
                    });
                    item.addEventListener('mouseleave', function() {
                        this.style.background = '';
                    });
                });
            }
        });
        
    } catch (error) {
        console.error('Erro ao carregar conversas:', error);
        alert('Erro ao carregar lista de conversas: ' + error.message);
    }
}

// Selecionar conversa para encaminhamento
async function selectForwardConversation(targetConversationId, messageId) {
    const conversationId = ;
    
    if (!conversationId || !targetConversationId || !messageId) {
        alert('Dados inválidos');
        return;
    }
    
    // Fechar modal
    Swal.close();
    
    // Detectar tema dark
    const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                      document.body.classList.contains('dark-mode') ||
                      window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    // Mostrar loading
    Swal.fire({
        title: 'Encaminhando...',
        text: 'Aguarde enquanto encaminhamos a mensagem',
        allowOutsideClick: false,
        colorScheme: isDarkMode ? 'dark' : 'light',
        customClass: {
            popup: isDarkMode ? 'swal2-dark' : '',
            title: isDarkMode ? 'text-white' : '',
            htmlContainer: isDarkMode ? 'text-white' : ''
        },
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        const response = await fetch(`/${conversationId}/forward`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                message_id: messageId,
                target_conversation_id: targetConversationId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Mensagem Encaminhada',
                text: 'A mensagem foi encaminhada com sucesso!',
                timer: 2000,
                showConfirmButton: false,
                colorScheme: isDarkMode ? 'dark' : 'light',
                customClass: {
                    popup: isDarkMode ? 'swal2-dark' : '',
                    title: isDarkMode ? 'text-white' : '',
                    htmlContainer: isDarkMode ? 'text-white' : ''
                }
            });
        } else {
            throw new Error(data.message || 'Erro ao encaminhar mensagem');
        }
    } catch (error) {
        console.error('Erro ao encaminhar:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao encaminhar mensagem: ' + error.message,
            colorScheme: isDarkMode ? 'dark' : 'light',
            customClass: {
                popup: isDarkMode ? 'swal2-dark' : '',
                title: isDarkMode ? 'text-white' : '',
                htmlContainer: isDarkMode ? 'text-white' : ''
            }
        });
    }
}

// Scroll até mensagem específica
function scrollToMessage(messageId) {
    console.log('🔍 scrollToMessage chamado com messageId:', messageId, 'tipo:', typeof messageId);
    
    if (!messageId || messageId === 'null' || messageId === null || messageId === '') {
        console.warn('⚠️ scrollToMessage: ID de mensagem inválido:', messageId);
        return;
    }
    
    // Converter para número se for string
    const numericId = parseInt(messageId);
    if (isNaN(numericId)) {
        console.error('❌ scrollToMessage: ID não é um número válido:', messageId);
        return;
    }
    
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) {
        console.error('❌ scrollToMessage: Container de mensagens não encontrado');
        return;
    }
    
    console.log('🔍 scrollToMessage: Procurando mensagem com ID:', numericId);
    
    // Tentar encontrar a mensagem
    const messageElement = chatMessages.querySelector(`[data-message-id="${numericId}"]`);
    
    console.log('🔍 scrollToMessage: Elemento encontrado:', messageElement);
    
    if (messageElement) {
        console.log('✅ scrollToMessage: Mensagem encontrada, fazendo scroll...');
        
        // Remover highlight anterior se houver
        chatMessages.querySelectorAll('.message-highlight').forEach(el => {
            el.classList.remove('message-highlight');
        });
        
        // Adicionar classe de highlight
        messageElement.classList.add('message-highlight');
        
        // Calcular posição relativa ao container do chat
        const elementTop = messageElement.offsetTop;
        const elementHeight = messageElement.offsetHeight;
        const containerHeight = chatMessages.clientHeight;
        
        // Scroll suave até a mensagem (centralizada no container)
        const targetScroll = elementTop - (containerHeight / 2) + (elementHeight / 2);
        
        chatMessages.scrollTo({
            top: Math.max(0, targetScroll),
            behavior: 'smooth'
        });
        
        // Remover highlight após 3 segundos
        setTimeout(() => {
            messageElement.classList.remove('message-highlight');
        }, 3000);
        
        console.log('✅ scrollToMessage: Scroll executado com sucesso');
        
        // Remover destaque após 3 segundos
        setTimeout(() => {
            messageElement.style.backgroundColor = '';
            messageElement.style.border = '';
            messageElement.style.borderRadius = '';
        }, 3000);
    } else {
        // Mensagem não encontrada - pode estar em outra página ou não carregada
        console.warn('⚠️ scrollToMessage: Mensagem não encontrada com ID:', numericId);
        const allMessages = chatMessages.querySelectorAll('[data-message-id]');
        console.log('📊 scrollToMessage: Total de mensagens no DOM:', allMessages.length);
        console.log('📊 scrollToMessage: IDs disponíveis:', Array.from(allMessages).map(el => el.getAttribute('data-message-id')));
        
        // Tentar carregar mais mensagens ou mostrar aviso
        if (typeof Swal !== 'undefined' && Swal.fire) {
            Swal.fire({
                icon: 'info',
                title: 'Mensagem não encontrada',
                text: 'A mensagem pode estar em outra página do histórico. Tente rolar para cima para encontrá-la.',
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        } else {
            // Fallback se SweetAlert não estiver disponível
            alert('Mensagem não encontrada. A mensagem pode estar em outra página do histórico.');
        }
    }
}

// Variáveis para gravação de áudio
let mediaRecorder = null;
let audioChunks = [];
let isRecording = false;

// Gravar áudio
async function toggleAudioRecording() {
    const btn = document.getElementById('recordAudioBtn');
    const conversationId = ;
    
    if (!conversationId) {
        alert('Selecione uma conversa primeiro');
        return;
    }
    
    if (!isRecording) {
        // Iniciar gravação
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];
            
            mediaRecorder.ondataavailable = (event) => {
                audioChunks.push(event.data);
            };
            
            mediaRecorder.onstop = async () => {
                const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                await sendAudioMessage(audioBlob, conversationId);
                
                // Parar stream
                stream.getTracks().forEach(track => track.stop());
            };
            
            mediaRecorder.start();
            isRecording = true;
            
            // Atualizar botão
            btn.classList.add('btn-danger');
            btn.classList.remove('btn-light-primary');
            btn.title = 'Parar gravação';
            btn.innerHTML = `
                <i class="ki-duotone ki-cross fs-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
            `;
            
            // Mostrar indicador de gravação
            showRecordingIndicator();
            
        } catch (error) {
            console.error('Erro ao acessar microfone:', error);
            alert('Erro ao acessar o microfone. Verifique as permissões.');
        }
    } else {
        // Parar gravação
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
        isRecording = false;
        
        // Restaurar botão
        btn.classList.remove('btn-danger');
        btn.classList.add('btn-light-primary');
        btn.title = 'Gravar áudio';
        btn.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                <line x1="12" y1="19" x2="12" y2="23"></line>
                <line x1="8" y1="23" x2="16" y2="23"></line>
            </svg>
        `;
        
        // Esconder indicador
        hideRecordingIndicator();
    }
}

// Mostrar indicador de gravação
function showRecordingIndicator() {
    const input = document.getElementById('messageInput');
    if (input) {
        input.placeholder = '🎤 Gravando... Clique no microfone para parar';
        input.disabled = true;
    }
}

// Esconder indicador de gravação
function hideRecordingIndicator() {
    const input = document.getElementById('messageInput');
    if (input) {
        input.placeholder = 'Digite sua mensagem...';
        input.disabled = false;
    }
}

// Enviar mensagem de áudio
async function sendAudioMessage(audioBlob, conversationId) {
    try {
        // Converter para formato compatível (webm para ogg ou mp3 seria ideal, mas vamos usar webm)
        const formData = new FormData();
        formData.append('attachments[]', audioBlob, 'audio-' + Date.now() + '.webm');
        formData.append('content', '');
        
        const response = await fetch(`/${conversationId}/messages`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success && data.message) {
            // Adicionar mensagem ao chat
            addMessageToChat(data.message);
        } else {
            throw new Error(data.message || 'Erro ao enviar áudio');
        }
    } catch (error) {
        console.error('Erro ao enviar áudio:', error);
        alert('Erro ao enviar áudio: ' + error.message);
    }
}

// Enviar mensagem
function sendMessage() {
    const input = document.getElementById('messageInput');
    const isNote = document.getElementById('noteToggle').checked;
    let message = input.value.trim();
    
    // Não permitir enviar mensagem vazia (mesmo com reply deve ter algum texto)
    if (!message) {
        return;
    }
    
    const conversationId = ;
    if (!conversationId) {
        return;
    }

    // Capturar contexto de reply antes de limpar estado
    const replyContext = replyingToMessage ? {
        id: replyingToMessage.id,
        sender: replyingToMessage.sender,
        text: replyingToMessage.text
    } : null;
    
    // Preparar mensagem com reply se houver
    // IMPORTANTE: Enviar apenas o texto digitado pelo usuário
    // O backend processa o quoted_message_id separadamente
    let finalMessage = message; // Texto que será enviado ao backend (apenas o digitado)
    
    // Para preview otimista, formatar com reply se houver
    let previewMessage = message;
    if (replyContext) {
        previewMessage = `↩️ ${replyContext.text}\n\n${message}`;
    }
    
    // Mostrar loading
    const btn = event.target.closest('button') || document.querySelector('button[onclick="sendMessage()"]');
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
    
    // Adicionar mensagem otimisticamente (antes da resposta do servidor)
    const tempId = 'temp_' + Date.now();
    const tempMessage = {
        id: tempId,
        content: previewMessage, // Usar preview formatado para exibição
        direction: 'outgoing',
        type: isNote ? 'note' : 'message',
        created_at: new Date().toISOString(),
        sender_name: 'Você',
        quoted_message_id: replyContext ? replyContext.id : null,
        quoted_sender_name: replyContext ? replyContext.sender : null,
        quoted_text: replyContext ? replyContext.text : null
    };
    const messageDiv = addMessageToChat(tempMessage);
    if (messageDiv) {
        messageDiv.setAttribute('data-temp-id', tempId);
    }
    
    // Limpar input e reply
    input.value = '';
    input.style.height = 'auto';
    document.getElementById('noteToggle').checked = false;
    cancelReply();
    
    fetch(`/${conversationId}/messages`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            message: finalMessage, // Enviar apenas o texto digitado (sem formatação de reply)
            is_note: isNote,
            quoted_message_id: replyContext ? replyContext.id : null
        })
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                try {
                    const json = JSON.parse(text);
                    throw new Error(json.message || 'Erro ao enviar mensagem');
                } catch (e) {
                    if (e instanceof Error && e.message !== 'Erro ao enviar mensagem') {
                        throw e;
                    }
                    throw new Error(`Erro ${response.status}: ${text.substring(0, 100)}`);
                }
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Remover mensagem temporária e adicionar a real
            const tempMsg = document.querySelector(`[data-temp-id="${tempMessage.id}"]`);
            if (tempMsg) tempMsg.remove();
            
            if (data.message) {
                addMessageToChat(data.message);
            }
            
            // Atualizar lista de conversas
            updateConversationInList(conversationId, message);
        } else {
            // Remover mensagem temporária em caso de erro
            const tempMsg = document.querySelector(`[data-temp-id="${tempMessage.id}"]`);
            if (tempMsg) tempMsg.remove();
            
            alert('Erro ao enviar mensagem: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        // Remover mensagem temporária em caso de erro
        const tempMsg = document.querySelector(`[data-temp-id="${tempMessage.id}"]`);
        if (tempMsg) tempMsg.remove();
        
        alert('Erro ao enviar mensagem');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

function updateConversationInList(conversationId, lastMessage) {
    const conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
    if (conversationItem) {
        const preview = conversationItem.querySelector('.conversation-item-preview');
        const time = conversationItem.querySelector('.conversation-item-time');
        if (preview) preview.textContent = lastMessage.substring(0, 60) + (lastMessage.length > 60 ? '...' : '');
        if (time) time.textContent = 'Agora';
        
        // Mover para o topo da lista
        const list = conversationItem.parentElement;
        list.insertBefore(conversationItem, list.firstChild);
    }
}

// Modal de Templates
function showTemplatesModal() {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_templates'));
    modal.show();
    
    // Carregar templates
    loadTemplates();
}

function loadTemplates() {
    const templatesList = document.getElementById('templatesList');
    if (!templatesList) return;
    
    // Mostrar loading
    templatesList.innerHTML = `
        <tr>
            <td colspan="3" class="text-center text-muted py-10">
                <span class="spinner-border spinner-border-sm text-primary mb-3" role="status"></span>
                <div>Carregando templates...</div>
            </td>
        </tr>
    `;
    
    // Buscar templates disponíveis para a conversa atual
    const conversationId = currentConversationId;
    const url = '' + 
                (conversationId ? `?conversation_id=${conversationId}` : '');
    
    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.templates) {
            renderTemplates(data.templates);
        } else {
            templatesList.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-muted py-10">
                        <i class="ki-duotone ki-information-5 fs-3x mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div>Nenhum template disponível</div>
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('Erro ao carregar templates:', error);
        templatesList.innerHTML = `
            <tr>
                <td colspan="3" class="text-center text-danger py-10">
                    <div>Erro ao carregar templates</div>
                </td>
            </tr>
        `;
    });
}

function renderTemplates(templates) {
    const templatesList = document.getElementById('templatesList');
    if (!templatesList) return;
    
    if (templates.length === 0) {
        templatesList.innerHTML = `
            <tr>
                <td colspan="3" class="text-center text-muted py-10">
                    <div>Nenhum template disponível</div>
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    templates.forEach(template => {
        const category = template.category || 'Geral';
        html += `
            <tr>
                <td>
                    <div class="fw-semibold">${escapeHtml(template.name)}</div>
                    ${template.description ? `<div class="text-muted fs-7">${escapeHtml(template.description)}</div>` : ''}
                </td>
                <td>
                    <span class="badge badge-light">${escapeHtml(category)}</span>
                </td>
                <td class="text-end">
                    <button class="btn btn-sm btn-light-primary me-2" onclick="previewTemplate(${template.id})" title="Preview">
                        <i class="ki-duotone ki-eye fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="useTemplate(${template.id})" title="Usar template">
                        <i class="ki-duotone ki-check fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Usar
                    </button>
                </td>
            </tr>
        `;
    });
    
    templatesList.innerHTML = html;
    
    // Adicionar busca em tempo real
    const searchInput = document.getElementById('templateSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = templatesList.querySelectorAll('tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
}

function previewTemplate(templateId) {
    if (!currentConversationId) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma conversa primeiro para visualizar o preview com variáveis',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    fetch(`/${templateId}/preview`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            conversation_id: currentConversationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                               document.body.classList.contains('dark-mode') ||
                               window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            Swal.fire({
                title: 'Preview do Template',
                html: `
                    <div class="text-start">
                        <div class="alert alert-info mb-4" style="white-space: pre-wrap; text-align: left;">${escapeHtml(data.processed_content || data.content)}</div>
                        ${data.variables_used ? `
                            <div class="text-muted fs-7">
                                <strong>Variáveis utilizadas:</strong> ${data.variables_used.join(', ')}
                            </div>
                        ` : ''}
                    </div>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Usar este Template',
                cancelButtonText: 'Fechar',
                buttonsStyling: false,
                colorScheme: isDarkMode ? 'dark' : 'light',
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-light',
                    popup: isDarkMode ? 'swal2-dark' : '',
                    title: isDarkMode ? 'text-white' : '',
                    htmlContainer: isDarkMode ? 'text-white' : ''
                },
                width: '700px'
            }).then((result) => {
                if (result.isConfirmed) {
                    useTemplate(templateId);
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: data.message || 'Erro ao processar template'
            });
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao carregar preview do template'
        });
    });
}

function useTemplate(templateId) {
    if (!currentConversationId) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma conversa primeiro',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    fetch(`/${templateId}/process`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            conversation_id: currentConversationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.value = data.processed_content || data.content;
                messageInput.focus();
                
                // Fechar modal de templates
                const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_templates'));
                if (modal) {
                    modal.hide();
                }
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: data.message || 'Erro ao processar template'
            });
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao processar template'
        });
    });
}

function loadTemplates() {
    fetch('')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('templatesList');
            
            if (!data.success || !data.templates || data.templates.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="3" class="text-center text-muted py-10">
                            Nenhum template disponível
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            data.templates.forEach(template => {
                html += `
                    <tr data-template-id="${template.id}">
                        <td>
                            <div class="fw-semibold text-gray-800">${escapeHtml(template.name)}</div>
                            <div class="text-muted fs-7">${escapeHtml(template.content.substring(0, 80))}${template.content.length > 80 ? '...' : ''}</div>
                            <div class="template-preview mt-2 p-3 bg-light rounded d-none" id="preview-${template.id}" style="white-space: pre-wrap; font-size: 13px; border-left: 3px solid var(--bs-primary);">
                                <div class="fw-semibold mb-2 text-primary">Preview:</div>
                                <div class="preview-content text-gray-700"></div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-light-primary">${escapeHtml(template.category || 'Geral')}</span>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-2 justify-content-end">
                                <button class="btn btn-sm btn-light-info" onclick="previewTemplate(${template.id})" title="Ver preview">
                                    <i class="ki-duotone ki-eye fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </button>
                                <button class="btn btn-sm btn-light-primary" onclick="useTemplate(${template.id})">
                                    <i class="ki-duotone ki-check fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Usar
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
            
            // Busca de templates
            document.getElementById('templateSearch').addEventListener('input', function(e) {
                const search = e.target.value.toLowerCase();
                const rows = tbody.querySelectorAll('tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(search) ? '' : 'none';
                });
            });
        })
        .catch(error => {
            console.error('Erro ao carregar templates:', error);
            document.getElementById('templatesList').innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-danger py-10">
                        Erro ao carregar templates
                    </td>
                </tr>
            `;
        });
}

// Preview de template com variáveis preenchidas
function previewTemplate(templateId) {
    const conversationId = ;
    const previewDiv = document.getElementById(`preview-${templateId}`);
    const previewContent = previewDiv ? previewDiv.querySelector('.preview-content') : null;
    
    if (!previewDiv || !previewContent) return;
    
    // Alternar visibilidade do preview
    const isVisible = !previewDiv.classList.contains('d-none');
    
    if (isVisible) {
        // Esconder preview
        previewDiv.classList.add('d-none');
        return;
    }
    
    // Mostrar loading
    previewContent.innerHTML = '<div class="text-muted"><i class="ki-duotone ki-loader fs-6"><span class="path1"></span><span class="path2"></span></i> Carregando preview...</div>';
    previewDiv.classList.remove('d-none');
    
    // Buscar preview do template
    fetch(`/${templateId}/preview`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            conversation_id: conversationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.preview) {
            // Mostrar preview formatado
            previewContent.innerHTML = escapeHtml(data.preview).replace(/\n/g, '<br>');
        } else {
            previewContent.innerHTML = '<div class="text-danger">Erro ao gerar preview</div>';
        }
    })
    .catch(error => {
        console.error('Erro ao gerar preview:', error);
        previewContent.innerHTML = '<div class="text-danger">Erro ao carregar preview</div>';
    });
}

function useTemplate(templateId) {
    fetch(`/${templateId}/process`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            conversation_id: 
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.processed_content) {
            const input = document.getElementById('messageInput');
            input.value = data.processed_content;
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 150) + 'px';
            input.focus();
            
            bootstrap.Modal.getInstance(document.getElementById('kt_modal_templates')).hide();
        } else {
            alert('Erro ao processar template: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar template');
    });
}

// Seletor rápido de templates
let templateQuickSelectData = [];
let templateQuickSelectIndex = -1;
let templateQuickSelectDebounce = null;

function initTemplateQuickSelect() {
    const messageInput = document.getElementById('messageInput');
    const templateQuickSelect = document.getElementById('templateQuickSelect');
    const templateQuickSearch = document.getElementById('templateQuickSearch');
    const templateQuickList = document.getElementById('templateQuickList');
    
    if (!messageInput || !templateQuickSelect || !templateQuickSearch || !templateQuickList) return;
    
    // Detectar digitação de {{ no campo de mensagem
    messageInput.addEventListener('input', function(e) {
        const value = e.target.value;
        const cursorPos = e.target.selectionStart;
        const textBeforeCursor = value.substring(0, cursorPos);
        
        // Verificar se digitou {{ antes do cursor
        if (textBeforeCursor.endsWith('{{')) {
            showTemplateQuickSelect();
        } else if (textBeforeCursor.includes('{{') && !textBeforeCursor.includes('}}')) {
            // Se já tem {{ mas não fechou, manter aberto
            const lastOpen = textBeforeCursor.lastIndexOf('{{');
            const textAfterOpen = textBeforeCursor.substring(lastOpen + 2);
            if (!textAfterOpen.includes('}}')) {
                filterTemplateQuickSelect(textAfterOpen);
            } else {
                hideTemplateQuickSelect();
            }
        } else {
            hideTemplateQuickSelect();
        }
    });
    
    // Fechar ao perder foco (com delay para permitir cliques)
    messageInput.addEventListener('blur', function() {
        setTimeout(() => {
            if (!templateQuickSelect.matches(':hover') && !templateQuickSearch.matches(':focus')) {
                hideTemplateQuickSelect();
            }
        }, 200);
    });
    
    // Busca no dropdown
    templateQuickSearch.addEventListener('input', function(e) {
        filterTemplateQuickSelect(e.target.value);
    });
    
    // Navegação com teclado
    messageInput.addEventListener('keydown', function(e) {
        if (!templateQuickSelect.classList.contains('d-none')) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                navigateTemplateQuickSelect(1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                navigateTemplateQuickSelect(-1);
            } else if (e.key === 'Enter' && templateQuickSelectIndex >= 0) {
                e.preventDefault();
                selectTemplateQuickItem(templateQuickSelectIndex);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                hideTemplateQuickSelect();
            }
        }
    });
    
    // Clique no botão de templates também abre o quick select
    const templateBtn = document.querySelector('button[onclick="showTemplatesModal()"]');
    if (templateBtn) {
        templateBtn.addEventListener('click', function(e) {
            // Se segurar Shift, abre quick select ao invés do modal
            if (e.shiftKey) {
                e.preventDefault();
                showTemplateQuickSelect();
            }
        });
    }
}

function showTemplateQuickSelect() {
    const templateQuickSelect = document.getElementById('templateQuickSelect');
    const templateQuickList = document.getElementById('templateQuickList');
    
    if (!templateQuickSelect || !templateQuickList) return;
    
    templateQuickSelect.classList.remove('d-none');
    templateQuickSelectIndex = -1;
    
    // Carregar templates se ainda não carregou
    if (templateQuickSelectData.length === 0) {
        loadTemplateQuickSelect();
    } else {
        renderTemplateQuickSelect(templateQuickSelectData);
    }
}

function hideTemplateQuickSelect() {
    const templateQuickSelect = document.getElementById('templateQuickSelect');
    if (templateQuickSelect) {
        templateQuickSelect.classList.add('d-none');
        templateQuickSelectIndex = -1;
    }
}

function loadTemplateQuickSelect() {
    const templateQuickList = document.getElementById('templateQuickList');
    if (!templateQuickList) return;
    
    templateQuickList.innerHTML = `
        <div class="text-center text-muted py-5">
            <i class="ki-duotone ki-loader fs-3x mb-3">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <div>Carregando templates...</div>
        </div>
    `;
    
    fetch('')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.templates) {
                templateQuickSelectData = data.templates;
                renderTemplateQuickSelect(data.templates);
            } else {
                templateQuickList.innerHTML = `
                    <div class="text-center text-muted py-5">
                        Nenhum template disponível
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erro ao carregar templates:', error);
            templateQuickList.innerHTML = `
                <div class="text-center text-danger py-5">
                    Erro ao carregar templates
                </div>
            `;
        });
}

function renderTemplateQuickSelect(templates) {
    const templateQuickList = document.getElementById('templateQuickList');
    const templateQuickSearch = document.getElementById('templateQuickSearch');
    if (!templateQuickList) return;
    
    const searchTerm = templateQuickSearch ? templateQuickSearch.value.toLowerCase() : '';
    const filteredTemplates = templates.filter(t => {
        if (!searchTerm) return true;
        const name = (t.name || '').toLowerCase();
        const content = (t.content || '').toLowerCase();
        const category = (t.category || '').toLowerCase();
        return name.includes(searchTerm) || content.includes(searchTerm) || category.includes(searchTerm);
    });
    
    if (filteredTemplates.length === 0) {
        templateQuickList.innerHTML = `
            <div class="text-center text-muted py-5">
                Nenhum template encontrado
            </div>
        `;
        return;
    }
    
    let html = '';
    filteredTemplates.forEach((template, index) => {
        const preview = (template.content || '').substring(0, 60);
        html += `
            <div class="template-quick-item ${index === templateQuickSelectIndex ? 'selected' : ''}" 
                 data-template-id="${template.id}" 
                 data-index="${index}"
                 onclick="selectTemplateQuickItem(${index})"
                 onmouseenter="highlightTemplateQuickItem(${index})">
                <div class="template-quick-item-name">${escapeHtml(template.name || 'Sem nome')}</div>
                <div class="template-quick-item-preview">${escapeHtml(preview)}${template.content && template.content.length > 60 ? '...' : ''}</div>
                ${template.category ? `<span class="template-quick-item-category">${escapeHtml(template.category)}</span>` : ''}
            </div>
        `;
    });
    
    templateQuickList.innerHTML = html;
    
    // Scroll para item selecionado
    if (templateQuickSelectIndex >= 0) {
        const selectedItem = templateQuickList.querySelector(`[data-index="${templateQuickSelectIndex}"]`);
        if (selectedItem) {
            selectedItem.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }
}

function filterTemplateQuickSelect(searchTerm) {
    if (templateQuickSelectDebounce) {
        clearTimeout(templateQuickSelectDebounce);
    }
    
    templateQuickSelectDebounce = setTimeout(() => {
        templateQuickSelectIndex = -1;
        renderTemplateQuickSelect(templateQuickSelectData);
    }, 150);
}

function navigateTemplateQuickSelect(direction) {
    const templateQuickList = document.getElementById('templateQuickList');
    if (!templateQuickList) return;
    
    const items = templateQuickList.querySelectorAll('.template-quick-item');
    if (items.length === 0) return;
    
    templateQuickSelectIndex += direction;
    
    if (templateQuickSelectIndex < 0) {
        templateQuickSelectIndex = items.length - 1;
    } else if (templateQuickSelectIndex >= items.length) {
        templateQuickSelectIndex = 0;
    }
    
    // Atualizar visual
    items.forEach((item, index) => {
        if (index === templateQuickSelectIndex) {
            item.classList.add('selected');
            item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        } else {
            item.classList.remove('selected');
        }
    });
}

function highlightTemplateQuickItem(index) {
    templateQuickSelectIndex = index;
    const templateQuickList = document.getElementById('templateQuickList');
    if (!templateQuickList) return;
    
    const items = templateQuickList.querySelectorAll('.template-quick-item');
    items.forEach((item, i) => {
        if (i === index) {
            item.classList.add('selected');
        } else {
            item.classList.remove('selected');
        }
    });
}

function selectTemplateQuickItem(index) {
    const templateQuickList = document.getElementById('templateQuickList');
    if (!templateQuickList) return;
    
    const items = templateQuickList.querySelectorAll('.template-quick-item');
    if (index < 0 || index >= items.length) return;
    
    const item = items[index];
    const templateId = item.getAttribute('data-template-id');
    
    if (templateId) {
        useTemplateQuick(templateId);
    }
}

function useTemplateQuick(templateId) {
    const messageInput = document.getElementById('messageInput');
    if (!messageInput) return;
    
    if (!currentConversationId) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma conversa primeiro para usar templates',
            confirmButtonText: 'OK'
        });
        hideTemplateQuickSelect();
        return;
    }
    
    // Buscar e processar template
    fetch(`/${templateId}/process`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            conversation_id: currentConversationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.processed_content) {
            // Substituir {{ pelo conteúdo do template
            const currentValue = messageInput.value;
            const cursorPos = messageInput.selectionStart;
            const textBeforeCursor = currentValue.substring(0, cursorPos);
            const textAfterCursor = currentValue.substring(cursorPos);
            
            // Encontrar último {{ antes do cursor
            const lastOpen = textBeforeCursor.lastIndexOf('{{');
            if (lastOpen >= 0) {
                const newValue = currentValue.substring(0, lastOpen) + data.processed_content + textAfterCursor;
                messageInput.value = newValue;
                messageInput.style.height = 'auto';
                messageInput.style.height = Math.min(messageInput.scrollHeight, 150) + 'px';
                
                // Posicionar cursor após o conteúdo inserido
                const newCursorPos = lastOpen + data.processed_content.length;
                messageInput.setSelectionRange(newCursorPos, newCursorPos);
            } else {
                // Se não encontrou {{, apenas inserir o conteúdo
                messageInput.value = data.processed_content;
                messageInput.style.height = 'auto';
                messageInput.style.height = Math.min(messageInput.scrollHeight, 150) + 'px';
            }
            
            messageInput.focus();
            hideTemplateQuickSelect();
        } else {
            const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                               document.body.classList.contains('dark-mode') ||
                               window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: data.message || 'Erro ao processar template',
                colorScheme: isDarkMode ? 'dark' : 'light',
                customClass: {
                    popup: isDarkMode ? 'swal2-dark' : '',
                    title: isDarkMode ? 'text-white' : '',
                    htmlContainer: isDarkMode ? 'text-white' : ''
                }
            });
            hideTemplateQuickSelect();
        }
    })
    .catch(error => {
        console.error('Erro ao processar template:', error);
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                           document.body.classList.contains('dark-mode') ||
                           window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao processar template',
            colorScheme: isDarkMode ? 'dark' : 'light',
            customClass: {
                popup: isDarkMode ? 'swal2-dark' : '',
                title: isDarkMode ? 'text-white' : '',
                htmlContainer: isDarkMode ? 'text-white' : ''
            }
        });
        hideTemplateQuickSelect();
    });
}

// Modal de Variáveis
function showVariablesModal() {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_variables'));
    modal.show();
    
    // Carregar variáveis disponíveis
    loadVariables();
}

function loadVariables() {
    const variablesList = document.getElementById('variablesList');
    if (!variablesList) return;
    
    // Mostrar loading
    variablesList.innerHTML = `
        <div class="col-12 text-center py-10">
            <span class="spinner-border spinner-border-sm text-primary mb-3" role="status"></span>
            <div class="text-muted">Carregando variáveis...</div>
        </div>
    `;
    
    fetch('', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.variables) {
            renderVariables(data.variables);
        } else {
            variablesList.innerHTML = `
                <div class="col-12 text-center text-muted py-10">
                    <div>Nenhuma variável disponível</div>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Erro ao carregar variáveis:', error);
        variablesList.innerHTML = `
            <div class="col-12 text-center text-danger py-10">
                <div>Erro ao carregar variáveis</div>
            </div>
        `;
    });
}

function renderVariables(variables) {
    const variablesList = document.getElementById('variablesList');
    if (!variablesList) return;
    
    let html = '';
    
    // Variáveis de contato
    if (variables.contact) {
        html += `
            <div class="col-12">
                <h5 class="fw-bold mb-3">
                    <i class="ki-duotone ki-user fs-4 text-primary me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Contato
                </h5>
            </div>
        `;
        Object.keys(variables.contact).forEach(key => {
            const varName = `{{contact.${key}}}`;
            html += `
                <div class="col-md-6">
                    <div class="card card-flush shadow-sm hover-shadow-lg cursor-pointer" onclick="insertVariable('${varName}')">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-gray-800">${escapeHtml(varName)}</div>
                                    <div class="text-muted fs-7">${escapeHtml(variables.contact[key])}</div>
                                </div>
                                <i class="ki-duotone ki-copy fs-4 text-primary">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    // Variáveis de agente
    if (variables.agent) {
        html += `
            <div class="col-12 mt-5">
                <h5 class="fw-bold mb-3">
                    <i class="ki-duotone ki-profile-user fs-4 text-success me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Agente
                </h5>
            </div>
        `;
        Object.keys(variables.agent).forEach(key => {
            const varName = `{{agent.${key}}}`;
            html += `
                <div class="col-md-6">
                    <div class="card card-flush shadow-sm hover-shadow-lg cursor-pointer" onclick="insertVariable('${varName}')">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-gray-800">${escapeHtml(varName)}</div>
                                    <div class="text-muted fs-7">${escapeHtml(variables.agent[key])}</div>
                                </div>
                                <i class="ki-duotone ki-copy fs-4 text-success">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    // Variáveis de conversa
    if (variables.conversation) {
        html += `
            <div class="col-12 mt-5">
                <h5 class="fw-bold mb-3">
                    <i class="ki-duotone ki-message-text fs-4 text-info me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Conversa
                </h5>
            </div>
        `;
        Object.keys(variables.conversation).forEach(key => {
            const varName = `{{conversation.${key}}}`;
            html += `
                <div class="col-md-6">
                    <div class="card card-flush shadow-sm hover-shadow-lg cursor-pointer" onclick="insertVariable('${varName}')">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-gray-800">${escapeHtml(varName)}</div>
                                    <div class="text-muted fs-7">${escapeHtml(variables.conversation[key])}</div>
                                </div>
                                <i class="ki-duotone ki-copy fs-4 text-info">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    // Variáveis de data/hora
    html += `
        <div class="col-12 mt-5">
            <h5 class="fw-bold mb-3">
                <i class="ki-duotone ki-time fs-4 text-warning me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Data e Hora
            </h5>
        </div>
    `;
    
    const dateVariables = {
        'date': 'Data atual (dd/mm/yyyy)',
        'time': 'Hora atual (HH:mm)',
        'datetime': 'Data e hora atuais'
    };
    
    Object.keys(dateVariables).forEach(key => {
        const varName = `{{${key}}}`;
        html += `
            <div class="col-md-4">
                <div class="card card-flush shadow-sm hover-shadow-lg cursor-pointer" onclick="insertVariable('${varName}')">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold text-gray-800">${escapeHtml(varName)}</div>
                                <div class="text-muted fs-7">${escapeHtml(dateVariables[key])}</div>
                            </div>
                            <i class="ki-duotone ki-copy fs-4 text-warning">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    variablesList.innerHTML = html;
}

function insertVariable(variable) {
    const messageInput = document.getElementById('messageInput');
    if (!messageInput) return;
    
    const startPos = messageInput.selectionStart;
    const endPos = messageInput.selectionEnd;
    const text = messageInput.value;
    
    // Inserir variável na posição do cursor
    const newText = text.substring(0, startPos) + variable + text.substring(endPos);
    messageInput.value = newText;
    
    // Reposicionar cursor após a variável inserida
    const newPos = startPos + variable.length;
    messageInput.setSelectionRange(newPos, newPos);
    messageInput.focus();
    
    // Mostrar feedback visual
    const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                       document.body.classList.contains('dark-mode') ||
                       window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    Swal.fire({
        icon: 'success',
        title: 'Variável inserida!',
        text: `Variável ${variable} inserida no campo de mensagem`,
        timer: 1500,
        showConfirmButton: false,
        colorScheme: isDarkMode ? 'dark' : 'light',
        customClass: {
            popup: isDarkMode ? 'swal2-dark' : '',
            title: isDarkMode ? 'text-white' : '',
            htmlContainer: isDarkMode ? 'text-white' : ''
        }
    });
    
    // Fechar modal após inserir
    const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_variables'));
    if (modal) {
        setTimeout(() => modal.hide(), 500);
    }
}
    
    // Carregar variáveis
    loadVariables();
}

function loadVariables() {
    const variablesList = document.getElementById('variablesList');
    if (!variablesList) return;
    
    // Mostrar loading
    variablesList.innerHTML = `
        <div class="col-12 text-center py-10">
            <span class="spinner-border spinner-border-sm text-primary mb-3" role="status"></span>
            <div class="text-muted">Carregando variáveis...</div>
        </div>
    `;
    
    fetch('', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.variables) {
            renderVariables(data.variables);
        } else {
            variablesList.innerHTML = '<div class="col-12 text-center text-muted py-10">Nenhuma variável disponível</div>';
        }
    })
    .catch(error => {
        console.error('Erro ao carregar variáveis:', error);
        variablesList.innerHTML = '<div class="col-12 text-center text-danger py-10">Erro ao carregar variáveis</div>';
    });
}

function renderVariables(variables) {
    const variablesList = document.getElementById('variablesList');
    if (!variablesList) return;
    
    let html = '';
    
    // Variáveis de contato
    if (variables.contact) {
        html += `
            <div class="col-12">
                <h5 class="fw-bold mb-3">
                    <i class="ki-duotone ki-user fs-4 text-primary me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Contato
                </h5>
            </div>
        `;
        Object.keys(variables.contact).forEach(key => {
            const varName = `{{contact.${key}}}`;
            html += `
                <div class="col-md-6">
                    <div class="card card-flush shadow-sm hover-shadow-lg cursor-pointer transition-all" onclick="insertVariable('${varName}')" style="cursor: pointer;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <code class="text-primary fw-bold fs-6 d-block mb-1">${escapeHtml(varName)}</code>
                                    <div class="text-muted fs-7">${escapeHtml(variables.contact[key])}</div>
                                </div>
                                <i class="ki-duotone ki-copy fs-4 text-primary">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    // Variáveis de agente
    if (variables.agent) {
        html += `
            <div class="col-12 mt-5">
                <h5 class="fw-bold mb-3">
                    <i class="ki-duotone ki-profile-user fs-4 text-success me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Agente
                </h5>
            </div>
        `;
        Object.keys(variables.agent).forEach(key => {
            const varName = `{{agent.${key}}}`;
            html += `
                <div class="col-md-6">
                    <div class="card card-flush shadow-sm hover-shadow-lg cursor-pointer transition-all" onclick="insertVariable('${varName}')" style="cursor: pointer;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <code class="text-success fw-bold fs-6 d-block mb-1">${escapeHtml(varName)}</code>
                                    <div class="text-muted fs-7">${escapeHtml(variables.agent[key])}</div>
                                </div>
                                <i class="ki-duotone ki-copy fs-4 text-success">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    // Variáveis de conversa
    if (variables.conversation) {
        html += `
            <div class="col-12 mt-5">
                <h5 class="fw-bold mb-3">
                    <i class="ki-duotone ki-message-text fs-4 text-info me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Conversa
                </h5>
            </div>
        `;
        Object.keys(variables.conversation).forEach(key => {
            const varName = `{{conversation.${key}}}`;
            html += `
                <div class="col-md-6">
                    <div class="card card-flush shadow-sm hover-shadow-lg cursor-pointer transition-all" onclick="insertVariable('${varName}')" style="cursor: pointer;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <code class="text-info fw-bold fs-6 d-block mb-1">${escapeHtml(varName)}</code>
                                    <div class="text-muted fs-7">${escapeHtml(variables.conversation[key])}</div>
                                </div>
                                <i class="ki-duotone ki-copy fs-4 text-info">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    // Variáveis de data/hora
    html += `
        <div class="col-12 mt-5">
            <h5 class="fw-bold mb-3">
                <i class="ki-duotone ki-time fs-4 text-warning me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Data e Hora
            </h5>
        </div>
    `;
    
    const dateVariables = {
        'date': 'Data atual (dd/mm/yyyy)',
        'time': 'Hora atual (HH:mm)',
        'datetime': 'Data e hora atuais'
    };
    
    Object.keys(dateVariables).forEach(key => {
        const varName = `{{${key}}}`;
        html += `
            <div class="col-md-4">
                <div class="card card-flush shadow-sm hover-shadow-lg cursor-pointer transition-all" onclick="insertVariable('${varName}')" style="cursor: pointer;">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <code class="text-warning fw-bold fs-6 d-block mb-1">${escapeHtml(varName)}</code>
                                <div class="text-muted fs-7">${escapeHtml(dateVariables[key])}</div>
                            </div>
                            <i class="ki-duotone ki-copy fs-4 text-warning">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    variablesList.innerHTML = html;
}

function insertVariable(variable) {
    const messageInput = document.getElementById('messageInput');
    if (!messageInput) return;
    
    const startPos = messageInput.selectionStart;
    const endPos = messageInput.selectionEnd;
    const text = messageInput.value;
    
    // Inserir variável na posição do cursor
    const newText = text.substring(0, startPos) + variable + text.substring(endPos);
    messageInput.value = newText;
    
    // Reposicionar cursor após a variável inserida
    const newPos = startPos + variable.length;
    messageInput.setSelectionRange(newPos, newPos);
    messageInput.focus();
    
    // Ajustar altura do textarea
    messageInput.style.height = 'auto';
    messageInput.style.height = Math.min(messageInput.scrollHeight, 150) + 'px';
    
    // Mostrar feedback visual
    const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                       document.body.classList.contains('dark-mode') ||
                       window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    Swal.fire({
        icon: 'success',
        title: 'Variável inserida!',
        text: `Variável ${variable} inserida no campo de mensagem`,
        timer: 1500,
        showConfirmButton: false,
        colorScheme: isDarkMode ? 'dark' : 'light',
        customClass: {
            popup: isDarkMode ? 'swal2-dark' : '',
            title: isDarkMode ? 'text-white' : '',
            htmlContainer: isDarkMode ? 'text-white' : ''
        }
    });
    
    // Fechar modal após inserir
    const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_variables'));
    if (modal) {
        setTimeout(() => modal.hide(), 500);
    }
}

// Função copyVariable mantida para compatibilidade, mas insertVariable é preferida
function copyVariable(variable) {
    insertVariable(variable);
}

// Verificar se conversa está com agente de IA
async function checkIfConversationHasAI(conversationId) {
    try {
        const response = await fetch(`/${conversationId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        const data = await response.json();
        if (data.success && data.conversation) {
            // Verificar se existe conversa de IA ativa
            return data.conversation.has_ai_agent === true || data.conversation.ai_agent_id !== null;
        }
        return false;
    } catch (error) {
        console.error('Erro ao verificar agente de IA:', error);
        return false;
    }
}

// Escalar conversa de IA para humano
function escalateFromAI(conversationId) {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_escalate'));
    document.getElementById('escalateConversationId').value = conversationId;
    document.getElementById('escalateAgent').value = '';
    modal.show();
}

// Modal de Atribuição
function assignConversation(conversationId) {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_assign'));
    modal.show();
    
    // Resetar formulário
    document.getElementById('assignForm').reset();
    
    // Salvar ID da conversa no formulário
    document.getElementById('assignForm').dataset.conversationId = conversationId;
}

// Submeter atribuição
document.getElementById('assignForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const conversationId = this.dataset.conversationId || ;
    const agentId = document.getElementById('assignAgent').value;
    const departmentId = document.getElementById('assignDepartment').value;
    
    if (!agentId) {
        alert('Selecione um agente');
        return;
    }
    
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Atribuindo...';
    
    fetch(`/${conversationId}/assign`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            agent_id: parseInt(agentId),
            department_id: departmentId ? parseInt(departmentId) : null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('kt_modal_assign')).hide();
            window.location.reload();
        } else {
            alert('Erro ao atribuir conversa: ' + (data.message || 'Erro desconhecido'));
            btn.disabled = false;
            btn.innerHTML = 'Atribuir';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao atribuir conversa');
        btn.disabled = false;
        btn.innerHTML = 'Atribuir';
    });
});

// Modal de Tags
function manageTags(conversationId) {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_tags'));
    modal.show();
    
    // Salvar ID da conversa
    document.getElementById('kt_modal_tags').dataset.conversationId = conversationId || ;
    
    // Carregar tags
    loadTagsForConversation();
}

function loadTagsForConversation() {
    const conversationId = document.getElementById('kt_modal_tags').dataset.conversationId;
    
    // Carregar tags da conversa
    fetch(`/${conversationId}/tags`)
        .then(response => response.json())
        .then(data => {
            const currentTagsDiv = document.getElementById('currentTags');
            
            if (data.success && data.tags && data.tags.length > 0) {
                let html = '<div class="w-100 mb-2"><strong>Tags Atuais:</strong></div>';
                data.tags.forEach(tag => {
                    html += `
                        <span class="badge badge-lg" style="background-color: ${tag.color}20; color: ${tag.color}; cursor: pointer;" 
                              onclick="removeTagFromList(${tag.id}, '${escapeHtml(tag.name)}')" title="Clique para remover">
                            ${escapeHtml(tag.name)}
                            <i class="ki-duotone ki-cross fs-7 ms-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    `;
                });
                currentTagsDiv.innerHTML = html;
            } else {
                currentTagsDiv.innerHTML = '<div class="w-100 text-muted">Nenhuma tag atribuída</div>';
            }
        })
        .catch(error => {
            console.error('Erro ao carregar tags da conversa:', error);
        });
    
    // Carregar todas as tags disponíveis
    fetch('')
        .then(response => response.json())
        .then(data => {
            const availableTagsDiv = document.getElementById('availableTags');
            
            if (!data.success || !data.tags || data.tags.length === 0) {
                availableTagsDiv.innerHTML = '<div class="text-muted">Nenhuma tag disponível</div>';
                return;
            }
            
            let html = '';
            data.tags.forEach(tag => {
                html += `
                    <span class="badge badge-lg" style="background-color: ${tag.color}20; color: ${tag.color}; cursor: pointer;" 
                          onclick="addTagToList(${tag.id}, '${escapeHtml(tag.name)}', '${tag.color}')" title="Clique para adicionar">
                        ${escapeHtml(tag.name)}
                        <i class="ki-duotone ki-plus fs-7 ms-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                    </span>
                `;
            });
            
            availableTagsDiv.innerHTML = html;
            
            // Busca de tags
            document.getElementById('tagSearch').addEventListener('input', function(e) {
                const search = e.target.value.toLowerCase();
                const tags = availableTagsDiv.querySelectorAll('span');
                tags.forEach(tag => {
                    const text = tag.textContent.toLowerCase();
                    tag.style.display = text.includes(search) ? '' : 'none';
                });
            });
        })
        .catch(error => {
            console.error('Erro ao carregar tags:', error);
            document.getElementById('availableTags').innerHTML = '<div class="text-danger">Erro ao carregar tags</div>';
        });
}

let selectedTags = [];

function addTagToList(tagId, tagName, tagColor) {
    if (selectedTags.find(t => t.id === tagId)) {
        return; // Já está selecionada
    }
    
    selectedTags.push({ id: tagId, name: tagName, color: tagColor });
    updateSelectedTagsDisplay();
}

function removeTagFromList(tagId, tagName) {
    selectedTags = selectedTags.filter(t => t.id !== tagId);
    updateSelectedTagsDisplay();
}

function updateSelectedTagsDisplay() {
    const currentTagsDiv = document.getElementById('currentTags');
    
    // Carregar tags atuais da conversa
    const conversationId = document.getElementById('kt_modal_tags').dataset.conversationId;
    fetch(`/${conversationId}/tags`)
        .then(response => response.json())
        .then(data => {
            const currentTagIds = data.success && data.tags ? data.tags.map(t => t.id) : [];
            
            // Combinar tags atuais com selecionadas (removendo duplicatas)
            const allTags = [...(data.success && data.tags ? data.tags : []), ...selectedTags];
            const uniqueTags = allTags.filter((tag, index, self) => 
                index === self.findIndex(t => t.id === tag.id)
            );
            
            if (uniqueTags.length > 0) {
                let html = '<div class="w-100 mb-2"><strong>Tags Selecionadas:</strong></div>';
                uniqueTags.forEach(tag => {
                    const isCurrent = currentTagIds.includes(tag.id);
                    html += `
                        <span class="badge badge-lg ${isCurrent ? '' : 'badge-light-primary'}" 
                              style="${isCurrent ? `background-color: ${tag.color}20; color: ${tag.color};` : ''} cursor: pointer;" 
                              onclick="removeTagFromList(${tag.id}, '${escapeHtml(tag.name)}')" title="Clique para remover">
                            ${escapeHtml(tag.name)}
                            <i class="ki-duotone ki-cross fs-7 ms-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    `;
                });
                currentTagsDiv.innerHTML = html;
            } else {
                currentTagsDiv.innerHTML = '<div class="w-100 text-muted">Nenhuma tag selecionada</div>';
            }
        });
}

function saveTags() {
    const conversationId = document.getElementById('kt_modal_tags').dataset.conversationId;
    
    // Carregar tags atuais
    fetch(`/${conversationId}/tags`)
        .then(response => response.json())
        .then(data => {
            const currentTagIds = data.success && data.tags ? data.tags.map(t => t.id) : [];
            const selectedTagIds = selectedTags.map(t => t.id);
            
            // Tags para adicionar (estão em selectedTags mas não nas atuais)
            const toAdd = selectedTagIds.filter(id => !currentTagIds.includes(id));
            
            // Tags para remover (estão nas atuais mas não em selectedTags)
            const toRemove = currentTagIds.filter(id => !selectedTagIds.includes(id));
            
            // Executar operações
            const promises = [
                ...toAdd.map(tagId => 
                    fetch(`/${conversationId}/tags`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ tag_id: tagId })
                    })
                ),
                ...toRemove.map(tagId => 
                    fetch(`/${conversationId}/tags/${tagId}`, {
                        method: 'DELETE'
                    })
                )
            ];
            
            Promise.all(promises)
                .then(() => {
                    bootstrap.Modal.getInstance(document.getElementById('kt_modal_tags')).hide();
                    selectedTags = [];
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Erro ao salvar tags:', error);
                    alert('Erro ao salvar tags');
                });
        });
}

// Função helper para escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Upload de arquivo
function attachFile() {
    const input = document.createElement('input');
    input.type = 'file';
    input.multiple = true;
    input.accept = 'image/*,video/*,audio/*,.pdf,.doc,.docx,.txt';
    
    input.onchange = function(e) {
        const files = Array.from(e.target.files);
        files.forEach(file => {
            uploadFile(file);
        });
    };
    
    input.click();
}

function uploadFile(file) {
    const conversationId = ;
    if (!conversationId) {
        alert('Selecione uma conversa primeiro');
        return;
    }

    const uploadId = 'upload_' + Date.now();
    const chatMessages = document.getElementById('chatMessages');
    const uploadDiv = document.createElement('div');
    uploadDiv.id = uploadId;
    uploadDiv.className = 'chat-message outgoing';
    uploadDiv.innerHTML =
        '<div class="message-content">' +
            '<div class="message-bubble">' +
                '<div class="d-flex align-items-center gap-2">' +
                    '<i class="ki-duotone ki-file-up fs-3">' +
                        '<span class="path1"></span>' +
                        '<span class="path2"></span>' +
                        '<span class="path3"></span>' +
                    '</i>' +
                    '<div class="flex-grow-1">' +
                        '<div class="fw-semibold">' + escapeHtml(file.name) + '</div>' +
                        '<div class="progress progress-sm mt-2">' +
                            '<div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';
    chatMessages.appendChild(uploadDiv);

    if (chatMessages && chatMessages.scrollHeight !== undefined) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    const maxSize = 10 * 1024 * 1024;
    if (file.size > maxSize) {
        uploadDiv.innerHTML =
            '<div class="message-content">' +
                '<div class="message-bubble text-danger">' +
                    'Arquivo muito grande. Tamanho máximo: 10MB' +
                '</div>' +
            '</div>';
        return;
    }

    const allowedTypes = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/webm', 'video/ogg',
        'audio/mp3', 'audio/wav', 'audio/ogg',
        'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv'
    ];

    const isAllowed = allowedTypes.includes(file.type) ||
        /\.(jpg|jpeg|png|gif|webp|mp4|webm|ogg|mp3|wav|pdf|doc|docx|xls|xlsx|txt|csv)$/i.test(file.name);

    if (!isAllowed) {
        uploadDiv.innerHTML =
            '<div class="message-content">' +
                '<div class="message-bubble text-danger">' +
                    'Tipo de arquivo não permitido' +
                '</div>' +
            '</div>';
        return;
    }

    const formData = new FormData();
    formData.append('attachments[]', file);
    formData.append('content', '');

    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = uploadDiv.querySelector('.attachment-preview img');
            if (img) img.src = e.target.result;
        };
        reader.readAsDataURL(file);
        uploadDiv.querySelector('.message-bubble').innerHTML =
            '<div class="attachment-preview mb-2">' +
                '<img src="" alt="' + escapeHtml(file.name) + '" style="max-width: 200px; max-height: 200px; border-radius: 8px; display: block;">' +
            '</div>' +
            '<div class="fw-semibold fs-7">' + escapeHtml(file.name) + '</div>' +
            '<div class="progress progress-sm mt-2" style="height: 4px;">' +
                '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>' +
            '</div>';
    } else {
        uploadDiv.querySelector('.message-bubble').innerHTML =
            '<div class="d-flex align-items-center gap-2">' +
                '<i class="ki-duotone ki-file-up fs-3">' +
                    '<span class="path1"></span>' +
                    '<span class="path2"></span>' +
                    '<span class="path3"></span>' +
                '</i>' +
                '<div class="flex-grow-1">' +
                    '<div class="fw-semibold">' + escapeHtml(file.name) + '</div>' +
                    '<div class="text-muted fs-7">' + formatFileSize(file.size) + '</div>' +
                '</div>' +
            '</div>' +
            '<div class="progress progress-sm mt-2" style="height: 4px;">' +
                '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>' +
            '</div>';
    }

    const xhr = new XMLHttpRequest();

    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            const progressBar = uploadDiv.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.style.width = percentComplete + '%';
            }
        }
    });

    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const data = JSON.parse(xhr.responseText);
                if (data.success && data.message) {
                    uploadDiv.remove();
                    addMessageToChat(data.message);
                    if (data.message.id) {
                        lastMessageId = Math.max(lastMessageId || 0, data.message.id);
                    }
                } else {
                    uploadDiv.innerHTML =
                        '<div class="message-content">' +
                            '<div class="message-bubble text-danger">' +
                                'Erro: ' + escapeHtml(data.message || 'Erro desconhecido') +
                            '</div>' +
                        '</div>';
                }
            } catch (e) {
                console.error('Erro ao processar resposta:', e);
                uploadDiv.innerHTML =
                    '<div class="message-content">' +
                        '<div class="message-bubble text-danger">' +
                            'Erro ao processar resposta do servidor' +
                        '</div>' +
                    '</div>';
            }
        } else {
            uploadDiv.innerHTML =
                '<div class="message-content">' +
                    '<div class="message-bubble text-danger">' +
                        'Erro ao enviar arquivo (' + xhr.status + ')' +
                    '</div>' +
                '</div>';
        }
    };

    xhr.onerror = function() {
        uploadDiv.innerHTML =
            '<div class="message-content">' +
                '<div class="message-bubble text-danger">' +
                    'Erro de conexão. Tente novamente.' +
                '</div>' +
            '</div>';
    };

    xhr.open('POST', `/${conversationId}/messages`);
    xhr.send(formData);
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function renderAttachmentHtml(attachment) {
    if (!attachment) return '';
    
    const type = attachment.type || 'document';
    const mimeType = attachment.mime_type || attachment.mimetype || '';
    
    // Renderizar localização
    if (type === 'location' && attachment.latitude && attachment.longitude) {
        const lat = attachment.latitude;
        const lng = attachment.longitude;
        const name = escapeHtml(attachment.name || 'Localização');
        const address = escapeHtml(attachment.address || '');
        const mapsUrl = `https://www.google.com/maps?q=${lat},${lng}`;
        
        return `<div class="attachment-item mb-2">
            <a href="${mapsUrl}" target="_blank" class="d-flex align-items-center gap-2 p-2 border rounded" style="text-decoration: none; color: inherit; background: rgba(255,255,255,0.05);" onclick="event.stopPropagation();">
                <i class="ki-duotone ki-geolocation fs-2 text-danger">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="flex-grow-1">
                    <div class="fw-semibold">${name}</div>
                    ${address ? `<div class="text-muted fs-7">${address}</div>` : ''}
                    <div class="text-muted fs-7">${lat}, ${lng}</div>
                </div>
                <i class="ki-duotone ki-arrow-top-right fs-4 text-primary">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
            </a>
        </div>`;
    }
    
    // Construir URL correta do anexo
    let url = attachment.url || '';
    if (!url && attachment.path) {
        // Se não tem URL mas tem path, construir URL
        if (attachment.path.startsWith('http')) {
            url = attachment.path;
        } else {
            url = `/${encodeURIComponent(attachment.path)}`;
        }
    }
    const name = escapeHtml(attachment.original_name || attachment.name || attachment.filename || 'Anexo');
    const size = attachment.size ? formatFileSize(attachment.size) : '';
    
    let html = '<div class="attachment-item mb-2">';
    
    if (type === 'image') {
        // Placeholder base64 simples
        const placeholder = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgZmlsbD0iI2YwZjBmMCIvPjwvc3ZnPg==';
        html += `<a href="${url}" target="_blank" class="d-inline-block lazy-image-container" data-src="${url}" onclick="event.stopPropagation();" style="position: relative;">
            <img src="${placeholder}" alt="${name}" data-src="${url}" class="lazy-image" style="max-width: 300px; max-height: 300px; border-radius: 8px; cursor: pointer; background: #f0f0f0; min-width: 100px; min-height: 100px;">
            <div class="lazy-loading-spinner" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: none;"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>
        </a>`;
    } else if (type === 'video') {
        html += `<div class="lazy-video-container" data-src="${url}" data-type="${mimeType || 'video/mp4'}" onclick="event.stopPropagation();">
            <div class="lazy-video-placeholder" style="max-width: 300px; max-height: 50px; border-radius: 8px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; cursor: pointer; min-height: 50px;">
                <i class="ki-duotone ki-play fs-2 text-primary"><span class="path1"></span><span class="path2"></span></i>
            </div>
            <video controls style="max-width: 300px; max-height: 50px; border-radius: 8px; display: none;" preload="none">
                <source src="" type="${mimeType || 'video/mp4'}">
                Seu navegador não suporta vídeo.
            </video>
        </div>`;
    } else if (type === 'audio' || (mimeType && mimeType.startsWith('audio/'))) {
        // Renderização ultra compacta de áudio - estilo WhatsApp
        const audioUrl = url || (attachment.path ? `/${encodeURIComponent(attachment.path)}` : '');
        html += `<div class="attachment audio-attachment" style="max-width: 250px; margin: 0;">
            <div class="d-flex align-items-center" style="background: rgba(0,0,0,0.15); border-radius: 20px; padding: 4px 8px;">
                <div class="me-2" style="flex-shrink: 0;">
                    <i class="ki-duotone ki-music fs-4 text-primary" style="min-width: 20px; font-size: 18px !important;">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
                <div class="flex-grow-1" style="min-width: 0;">
                    <audio controls style="width: 100%; height: 24px; outline: none; display: block;" preload="metadata" onclick="event.stopPropagation();">
                        <source src="${audioUrl}" type="${mimeType || 'audio/webm'}">
                        Seu navegador não suporta o elemento de áudio.
                    </audio>
                </div>
            </div>
        </div>`;
    } else {
        const downloadUrl = `/${encodeURIComponent(attachment.path || '')}/download`;
        html += `<a href="${downloadUrl}" target="_blank" class="d-flex align-items-center gap-2 p-2 border rounded" style="text-decoration: none; color: inherit; background: rgba(255,255,255,0.05);" onclick="event.stopPropagation();">
            <i class="ki-duotone ki-file fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <div class="flex-grow-1">
                <div class="fw-semibold">${name}</div>
                ${size ? `<div class="text-muted fs-7">${size}</div>` : ''}
            </div>
            <i class="ki-duotone ki-arrow-down fs-4 text-primary">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
        </a>`;
    }
    
    html += '</div>';
    return html;
}

// Emoji picker (placeholder melhorado)
function toggleEmoji() {
    // TODO: Implementar emoji picker completo
    const emojis = ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗', '😚', '😙', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫', '🤔', '🤐', '🤨', '😐', '😑', '😶', '😏', '😒', '🙄', '😬', '🤥', '😌', '😔', '😪', '🤤', '😴', '😷', '🤒', '🤕', '🤢', '🤮', '🤧', '🥵', '🥶', '😶‍🌫️', '😵', '😵‍💫', '🤯', '🤠', '🥳', '🥸', '😎', '🤓', '🧐', '😕', '😟', '🙁', '☹️', '😮', '😯', '😲', '😳', '🥺', '😦', '😧', '😨', '😰', '😥', '😢', '😭', '😱', '😖', '😣', '😞', '😓', '😩', '😫', '🥱', '😤', '😡', '😠', '🤬', '😈', '👿', '💀', '☠️', '💩', '🤡', '👹', '👺', '👻', '👽', '👾', '🤖', '😺', '😸', '😹', '😻', '😼', '😽', '🙀', '😿', '😾'];
    
    // Criar modal simples de emoji
    const emojiHtml = emojis.map(emoji => `<span class="btn btn-sm btn-icon btn-light m-1" onclick="insertEmoji('${emoji}')" style="font-size: 24px; cursor: pointer;">${emoji}</span>`).join('');
    
    Swal.fire({
        title: 'Selecione um emoji',
        html: `<div style="max-height: 400px; overflow-y: auto; text-align: center; padding: 20px;">${emojiHtml}</div>`,
        width: '600px',
        showConfirmButton: false,
        showCloseButton: true
    });
}

function insertEmoji(emoji) {
    const input = document.getElementById('messageInput');
    const cursorPos = input.selectionStart;
    const textBefore = input.value.substring(0, cursorPos);
    const textAfter = input.value.substring(cursorPos);
    
    input.value = textBefore + emoji + textAfter;
    input.focus();
    input.setSelectionRange(cursorPos + emoji.length, cursorPos + emoji.length);
    
    // Ajustar altura
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 150) + 'px';
    
    Swal.close();
}

// Toggle sidebar de detalhes
function toggleConversationSidebar() {
    const sidebar = document.getElementById('conversationSidebar');
    if (sidebar) {
        sidebar.classList.toggle('open');
        localStorage.setItem('conversationSidebarOpen', sidebar.classList.contains('open'));
    }
}

// Sistema de Polling já declarado acima (antes de selectConversation)

// WebSocket - Atualizar em tempo real
if (typeof window.wsClient !== 'undefined') {
    window.wsClient.on('new_message', (data) => {
        const currentConversationId = ;
        
        // Atualizar lista de conversas
        const conversationItem = document.querySelector(`[data-conversation-id="${data.conversation_id}"]`);
        if (conversationItem) {
            const preview = conversationItem.querySelector('.conversation-item-preview');
            const time = conversationItem.querySelector('.conversation-item-time');
            const badge = conversationItem.querySelector('.conversation-item-badge');
            
            if (preview) {
                const content = data.message.content || '';
                preview.textContent = content.substring(0, 60) + (content.length > 60 ? '...' : '');
            }
            if (time) time.textContent = 'Agora';
            
            // Atualizar badge de não lidas (se não for a conversa atual)
            if (currentConversationId != data.conversation_id) {
                const currentCount = badge ? parseInt(badge.textContent) || 0 : 0;
                if (badge) {
                    badge.textContent = currentCount + 1;
                } else {
                    const badgeHtml = `<span class="conversation-item-badge">1</span>`;
                    const meta = conversationItem.querySelector('.conversation-item-meta');
                    if (meta) meta.insertAdjacentHTML('beforeend', badgeHtml);
                }
            }
            
            // Mover conversa para o topo se não for a atual
            if (currentConversationId != data.conversation_id) {
                const list = conversationItem.parentElement;
                list.insertBefore(conversationItem, list.firstChild);
            }
        }
        
        // Se é a conversa atual, adicionar mensagem dinamicamente
        if (currentConversationId == data.conversation_id && data.message) {
            addMessageToChat(data.message);
            
            // Remover badge se existir (mensagem já foi marcada como lida no backend)
            if (badge) badge.remove();
        } else {
            // Se não é a conversa atual, atualizar lista completa após um delay para garantir sincronização
            setTimeout(() => {
                refreshConversationBadges();
            }, 1000);
        }
    });
    
    // Atualizar status de mensagem via WebSocket
    window.wsClient.on('message_status_updated', (data) => {
        const messageElement = document.querySelector(`[data-message-id="${data.message_id}"]`);
        if (messageElement) {
            const statusElement = messageElement.querySelector('.message-status');
            if (statusElement) {
                // Buscar mensagem atualizada do servidor
                fetch(`/${data.conversation_id}?last_message_id=${data.message_id}`)
                    .then(res => res.json())
                    .then(result => {
                        if (result.success && result.conversation && result.conversation.messages) {
                            const updatedMessage = result.conversation.messages.find(m => m.id == data.message_id);
                            if (updatedMessage) {
                                // Atualizar status
                                const newStatusHtml = renderMessageStatusHtml(updatedMessage);
                                if (newStatusHtml) {
                                    statusElement.outerHTML = newStatusHtml;
                                }
                            }
                        }
                    })
                    .catch(err => console.error('Erro ao atualizar status:', err));
            }
        }
    });
    
    window.wsClient.on('conversation_updated', (data) => {
        const currentConversationId = ;
        
        // Atualizar badge de não lidas na lista
        const conversationItem = document.querySelector(`[data-conversation-id="${data.conversation_id}"]`);
        if (conversationItem) {
            const badge = conversationItem.querySelector('.conversation-item-badge');
            const unreadCount = data.conversation?.unread_count || 0;
            
            if (unreadCount > 0) {
                if (badge) {
                    badge.textContent = unreadCount;
                } else {
                    const badgeHtml = `<span class="conversation-item-badge">${unreadCount}</span>`;
                    const meta = conversationItem.querySelector('.conversation-item-meta');
                    if (meta) meta.insertAdjacentHTML('beforeend', badgeHtml);
                }
            } else {
                // Remover badge se não houver mensagens não lidas
                if (badge) badge.remove();
            }
        }
        
        // Se é a conversa atual, recarregar para pegar mudanças de status, atribuição, etc
        if (currentConversationId == data.conversation_id) {
            // Recarregar apenas se necessário (mudanças de status, atribuição)
            if (data.changes && (data.changes.status || data.changes.agent_id || data.changes.department_id)) {
                window.location.reload();
            }
        }
    });
    
    // Inscrever na conversa atual
    const currentConversationId = ;
    if (currentConversationId) {
        if (window.wsClient.connected) {
            window.wsClient.subscribe(currentConversationId);
            stopPolling(); // Parar polling se WebSocket estiver conectado
        } else {
            // Se WebSocket não estiver conectado, usar polling
            startPolling(currentConversationId);
        }
    }
    
    // Sistema de atualização periódica da lista de conversas (para badges de não lidas)
    // Atualizar a cada 10 segundos para verificar novas mensagens em todas as conversas
    let conversationListUpdateInterval = setInterval(() => {
        refreshConversationBadges();
    }, 10000); // 10 segundos
    
    // Carregar funcionalidades do Assistente IA quando modal for aberto
    const aiAssistantModal = document.getElementById('kt_modal_ai_assistant');
    if (aiAssistantModal) {
        aiAssistantModal.addEventListener('show.bs.modal', function() {
            loadAIAssistantFeatures();
        });
    }
} else {
    // Se WebSocket não estiver disponível, usar polling
    const currentConversationId = ;
    if (currentConversationId) {
        startPolling(currentConversationId);
    }
    
    // Sistema de atualização periódica da lista de conversas (para badges de não lidas)
    let conversationListUpdateInterval = setInterval(() => {
        refreshConversationBadges();
    }, 10000); // 10 segundos
}

/**
 * Atualizar badges de não lidas nas conversas da lista (sem recarregar toda a lista)
 */
function refreshConversationBadges() {
    // Buscar lista atualizada de conversas
    const urlParams = new URLSearchParams(window.location.search);
    const filters = {
        status: urlParams.get('status') || '',
        channel: urlParams.get('channel') || '',
        search: urlParams.get('search') || '',
        department_id: urlParams.get('department_id') || '',
        tag_id: urlParams.get('tag_id') || '',
        unanswered: urlParams.get('unanswered') || '',
        answered: urlParams.get('answered') || '',
        date_from: urlParams.get('date_from') || '',
        date_to: urlParams.get('date_to') || '',
        pinned: urlParams.get('pinned') || '',
        order_by: urlParams.get('order_by') || '',
        order_dir: urlParams.get('order_dir') || ''
    };
    
    const params = new URLSearchParams();
    Object.keys(filters).forEach(key => {
        if (filters[key]) {
            params.append(key, filters[key]);
        }
    });
    
    fetch(`?${params.toString()}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.conversations) {
            // Atualizar badges de não lidas em cada conversa da lista
            data.conversations.forEach(conv => {
                const conversationItem = document.querySelector(`[data-conversation-id="${conv.id}"]`);
                if (conversationItem) {
                    const badge = conversationItem.querySelector('.conversation-item-badge');
                    const unreadCount = conv.unread_count || 0;
                    const currentConversationId = ;
                    
                    // Não atualizar badge se for a conversa atual (já está sendo gerenciada separadamente)
                    if (currentConversationId == conv.id) {
                        return;
                    }
                    
                    if (unreadCount > 0) {
                        if (badge) {
                            badge.textContent = unreadCount;
                        } else {
                            // Criar badge se não existir
                            const badgeHtml = `<span class="conversation-item-badge">${unreadCount}</span>`;
                            const meta = conversationItem.querySelector('.conversation-item-meta');
                            if (meta) {
                                meta.insertAdjacentHTML('beforeend', badgeHtml);
                            }
                        }
                    } else {
                        // Remover badge se não houver mensagens não lidas
                        if (badge) {
                            badge.remove();
                        }
                    }
                    
                    // Atualizar preview e tempo se necessário
                    if (conv.last_message) {
                        const preview = conversationItem.querySelector('.conversation-item-preview');
                        if (preview) {
                            const content = conv.last_message.substring(0, 60);
                            preview.textContent = content + (conv.last_message.length > 60 ? '...' : '');
                        }
                    }
                }
            });
        }
    })
    .catch(error => {
        // Silenciar erros de atualização (não crítico)
        console.debug('Erro ao atualizar lista de conversas:', error);
    });
}

/**
 * Assistente IA - Funções
 */

let aiAssistantFeatures = [];
let currentAIAgent = null;

function showAIAssistantModal() {
    // Verificar disponibilidade antes de abrir o modal
    checkAIAssistantAvailability().then(availability => {
        if (!availability.available) {
            // Mostrar erros de forma amigável
            const issues = availability.issues || [];
            
            if (issues.length > 0) {
                const mainIssue = issues[0];
                let message = `<strong>${escapeHtml(mainIssue.title)}</strong><br>${escapeHtml(mainIssue.message)}`;
                
                if (issues.length > 1) {
                    message += '<br><br><strong>Outros problemas encontrados:</strong><ul class="text-start mt-2">';
                    issues.slice(1).forEach(issue => {
                        message += `<li>${escapeHtml(issue.title)}: ${escapeHtml(issue.message)}</li>`;
                    });
                    message += '</ul>';
                }
                
                // Detectar tema dark
                const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                                  document.body.classList.contains('dark-mode') ||
                                  window.matchMedia('(prefers-color-scheme: dark)').matches;
                
                if (mainIssue.action_url) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Assistente IA não disponível',
                        html: message,
                        confirmButtonText: mainIssue.action === 'configure_api_key' ? 'Ir para Configurações' : 'OK',
                        showCancelButton: true,
                        cancelButtonText: 'Cancelar',
                        buttonsStyling: false,
                        colorScheme: isDarkMode ? 'dark' : 'light',
                        customClass: {
                            confirmButton: 'btn btn-primary',
                            cancelButton: 'btn btn-light',
                            popup: isDarkMode ? 'swal2-dark' : '',
                            title: isDarkMode ? 'text-white' : '',
                            htmlContainer: isDarkMode ? 'text-white' : ''
                        }
                    }).then((result) => {
                        if (result.isConfirmed && mainIssue.action_url) {
                            window.location.href = mainIssue.action_url;
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Assistente IA não disponível',
                        html: message,
                        confirmButtonText: 'OK',
                        buttonsStyling: false,
                        colorScheme: isDarkMode ? 'dark' : 'light',
                        customClass: {
                            confirmButton: 'btn btn-primary',
                            popup: isDarkMode ? 'swal2-dark' : '',
                            title: isDarkMode ? 'text-white' : '',
                            htmlContainer: isDarkMode ? 'text-white' : ''
                        }
                    });
                }
                return;
            }
        }
        
        // Se há warnings, mostrar mas continuar
        if (availability.warnings && availability.warnings.length > 0) {
            const warning = availability.warnings[0];
            const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                              document.body.classList.contains('dark-mode') ||
                              window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            Swal.fire({
                icon: 'warning',
                title: warning.title,
                text: warning.message,
                confirmButtonText: 'Continuar',
                showCancelButton: true,
                cancelButtonText: 'Cancelar',
                buttonsStyling: false,
                colorScheme: isDarkMode ? 'dark' : 'light',
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-light',
                    popup: isDarkMode ? 'swal2-dark' : '',
                    title: isDarkMode ? 'text-white' : '',
                    htmlContainer: isDarkMode ? 'text-white' : ''
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    openAIAssistantModal();
                }
            });
            return;
        }
        
        // Tudo OK, abrir modal
        openAIAssistantModal();
    }).catch(error => {
        console.error('Erro ao verificar disponibilidade:', error);
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                          document.body.classList.contains('dark-mode') ||
                          window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Não foi possível verificar a disponibilidade do Assistente IA. Tente novamente.',
            confirmButtonText: 'OK',
            buttonsStyling: false,
            colorScheme: isDarkMode ? 'dark' : 'light',
            customClass: {
                confirmButton: 'btn btn-primary',
                popup: isDarkMode ? 'swal2-dark' : '',
                title: isDarkMode ? 'text-white' : '',
                htmlContainer: isDarkMode ? 'text-white' : ''
            }
        });
    });
}

function openAIAssistantModal() {
    if (!currentConversationId) {
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                          document.body.classList.contains('dark-mode') ||
                          window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        Swal.fire({
            icon: 'info',
            title: 'Selecione uma conversa',
            text: 'Por favor, selecione uma conversa antes de usar o Assistente IA',
            confirmButtonText: 'OK',
            buttonsStyling: false,
            colorScheme: isDarkMode ? 'dark' : 'light',
            customClass: {
                confirmButton: 'btn btn-primary',
                popup: isDarkMode ? 'swal2-dark' : '',
                title: isDarkMode ? 'text-white' : '',
                htmlContainer: isDarkMode ? 'text-white' : ''
            }
        });
        return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_ai_assistant'));
    modal.show();
    
    // Resetar estado
    const resultsDiv = document.getElementById('aiResponseResults');
    if (resultsDiv) {
        resultsDiv.classList.add('d-none');
    }
    
    // Carregar funcionalidades disponíveis
    loadAIAssistantFeatures();
}

function checkAIAssistantAvailability() {
    const featureKey = 'generate_response'; // Funcionalidade padrão
    const url = `?conversation_id=${currentConversationId || ''}&feature_key=${featureKey}`;
    
    return fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success && !data.available) {
            // Retornar dados mesmo se não disponível para mostrar erros
            return data;
        }
        return data;
    });
}

function loadAIAssistantFeatures() {
    const loading = document.getElementById('aiAssistantLoading');
    const content = document.getElementById('aiAssistantContent');
    const error = document.getElementById('aiAssistantError');
    
    loading.classList.remove('d-none');
    content.classList.add('d-none');
    error.classList.add('d-none');
    
    fetch('', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        loading.classList.add('d-none');
        
        if (data.success && data.features) {
            aiAssistantFeatures = data.features;
            renderAIFeatures(data.features);
            content.classList.remove('d-none');
            
            // Obter agente selecionado para contexto atual
            loadSelectedAgent();
        } else {
            showAIError(data.message || 'Erro ao carregar funcionalidades');
        }
    })
    .catch(error => {
        loading.classList.add('d-none');
        showAIError('Erro ao carregar funcionalidades: ' + error.message);
    });
}

function renderAIFeatures(features) {
    const otherFeaturesContainer = document.getElementById('aiOtherFeatures');
    if (!otherFeaturesContainer) return;
    
    otherFeaturesContainer.innerHTML = '';
    
    // Mapear ícones para nomes mais amigáveis
    const iconMap = {
        'ki-file-down': '📄',
        'ki-tag': '🏷️',
        'ki-heart': '❤️',
        'ki-translate': '🌐',
        'ki-pencil': '✏️',
        'ki-arrow-right': '➡️',
        'ki-information': 'ℹ️'
    };
    
    features.forEach(feature => {
        // Pular "Gerar Resposta" pois já tem card dedicado
        if (feature.feature_key === 'generate_response') {
            return;
        }
        
        const icon = feature.icon || 'ki-abstract-26';
        const emoji = iconMap[icon] || '🤖';
        const cardHtml = `
            <div class="col-md-6 col-lg-4">
                <div class="card card-flush h-100 shadow-sm hover-shadow-lg transition-all">
                    <div class="card-body d-flex flex-column p-6">
                        <div class="mb-4">
                            <div class="symbol symbol-50px mb-3">
                                <div class="symbol-label bg-light-primary">
                                    <span class="fs-2x">${emoji}</span>
                                </div>
                            </div>
                            <h5 class="fw-bold mb-2">${escapeHtml(feature.name)}</h5>
                            <p class="text-muted fs-7 mb-0">${escapeHtml(feature.description || '')}</p>
                        </div>
                        <button class="btn btn-sm btn-primary w-100 mt-auto" onclick="executeAIFeature('${feature.feature_key}')">
                            <i class="ki-duotone ki-play fs-5 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Executar
                        </button>
                    </div>
                </div>
            </div>
        `;
        otherFeaturesContainer.insertAdjacentHTML('beforeend', cardHtml);
    });
}

function loadSelectedAgent() {
    if (!currentConversationId) return;
    
    const agentInfo = document.getElementById('aiAgentInfo');
    if (agentInfo) {
        agentInfo.innerHTML = '<span class="spinner-border spinner-border-sm text-primary me-2" role="status" style="width: 12px; height: 12px;"></span>Carregando agente...';
    }
    
    fetch(`?conversation_id=${currentConversationId}&feature_key=generate_response`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.agent) {
            currentAIAgent = data.agent;
            if (agentInfo) {
                agentInfo.innerHTML = `
                    <i class="ki-duotone ki-abstract-26 fs-6 text-primary me-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                    </i>
                    <span class="fw-semibold">${escapeHtml(data.agent.name)}</span>
                    ${data.agent.model ? `<span class="text-muted ms-2">(${escapeHtml(data.agent.model)})</span>` : ''}
                `;
            }
        } else {
            if (agentInfo) {
                agentInfo.innerHTML = '<span class="text-muted">Agente padrão</span>';
            }
        }
    })
    .catch(error => {
        console.error('Erro ao carregar agente:', error);
        if (agentInfo) {
            agentInfo.innerHTML = '<span class="text-muted">Agente padrão</span>';
        }
    });
}

function generateAIResponse() {
    if (!currentConversationId) {
        alert('Selecione uma conversa primeiro');
        return;
    }
    
    const tone = document.getElementById('aiResponseTone')?.value || 'professional';
    const count = parseInt(document.getElementById('aiResponseCount')?.value || '3');
    const resultsDiv = document.getElementById('aiResponseResults');
    const suggestionsDiv = document.getElementById('aiResponseSuggestions');
    const generateBtn = document.getElementById('aiGenerateBtn');
    
    // Desabilitar botão e mostrar loading
    if (generateBtn) {
        generateBtn.disabled = true;
        generateBtn.querySelector('.indicator-label').classList.add('d-none');
        generateBtn.querySelector('.indicator-progress').classList.remove('d-none');
    }
    
    // Mostrar loading nas sugestões
    suggestionsDiv.innerHTML = `
        <div class="text-center py-10">
            <div class="mb-4">
                <span class="spinner-border spinner-border-lg text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></span>
            </div>
            <div class="fw-semibold fs-5 mb-2">Gerando respostas inteligentes...</div>
            <div class="text-muted">Analisando o contexto da conversa</div>
        </div>
    `;
    resultsDiv.classList.remove('d-none');
    
    // Scroll para resultados
    resultsDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            conversation_id: currentConversationId,
            count: count,
            tone: tone
        })
    })
    .then(response => response.json())
    .then(data => {
        // Reabilitar botão
        if (generateBtn) {
            generateBtn.disabled = false;
            generateBtn.querySelector('.indicator-label').classList.remove('d-none');
            generateBtn.querySelector('.indicator-progress').classList.add('d-none');
        }
        
        if (data.success && data.responses) {
            let html = '';
            data.responses.forEach((response, index) => {
                const toneEmoji = {
                    'professional': '💼',
                    'friendly': '😊',
                    'formal': '📋'
                }[tone] || '💬';
                
                html += `
                    <div class="card card-flush shadow-sm mb-4 hover-shadow-lg transition-all" style="animation: fadeIn 0.3s ease-in ${index * 0.1}s both;">
                        <div class="card-body p-6">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="d-flex align-items-center">
                                    <span class="badge badge-light-primary badge-lg me-2">${toneEmoji} Sugestão ${index + 1}</span>
                                    ${response.tokens_used ? `<span class="badge badge-light-info badge-sm">${response.tokens_used.toLocaleString('pt-BR')} tokens</span>` : ''}
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-icon btn-light-primary" onclick="useAIResponse(${index})" title="Usar esta resposta">
                                        <i class="ki-duotone ki-check fs-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <button class="btn btn-sm btn-icon btn-light" onclick="copyToClipboard('${escapeHtml(response.text).replace(/'/g, "\\'")}')" title="Copiar resposta">
                                        <i class="ki-duotone ki-copy fs-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-4" style="white-space: pre-wrap; line-height: 1.7;">${escapeHtml(response.text)}</div>
                            <div class="d-flex align-items-center text-muted fs-7">
                                <i class="ki-duotone ki-abstract-26 fs-6 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                <span class="fw-semibold me-2">${escapeHtml(response.agent_name || 'Assistente IA')}</span>
                                ${response.cost ? `<span class="text-muted">• R$ ${response.cost.toFixed(4).replace('.', ',')}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            suggestionsDiv.innerHTML = html;
            
            // Armazenar respostas para uso posterior
            window.aiGeneratedResponses = data.responses;
            
            // Scroll suave para primeira sugestão
            setTimeout(() => {
                const firstCard = suggestionsDiv.querySelector('.card');
                if (firstCard) {
                    firstCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }, 100);
        } else {
            suggestionsDiv.innerHTML = `
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="ki-duotone ki-information-5 fs-2x me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div>
                        <div class="fw-bold">Erro ao gerar respostas</div>
                        <div class="fs-7">${escapeHtml(data.message || 'Ocorreu um erro inesperado')}</div>
                    </div>
                </div>
            `;
        }
    })
    .catch(error => {
        // Reabilitar botão
        if (generateBtn) {
            generateBtn.disabled = false;
            generateBtn.querySelector('.indicator-label').classList.remove('d-none');
            generateBtn.querySelector('.indicator-progress').classList.add('d-none');
        }
        
        suggestionsDiv.innerHTML = `
            <div class="alert alert-danger d-flex align-items-center">
                <i class="ki-duotone ki-information-5 fs-2x me-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div>
                    <div class="fw-bold">Erro ao gerar respostas</div>
                    <div class="fs-7">${escapeHtml(error.message || 'Erro de conexão')}</div>
                </div>
            </div>
        `;
    });
}

function useAIResponse(index, responseId = null) {
    if (!window.aiGeneratedResponses || !window.aiGeneratedResponses[index]) {
        return;
    }
    
    const response = window.aiGeneratedResponses[index];
    
    // Mostrar preview antes de usar
    const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                       document.body.classList.contains('dark-mode') ||
                       window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    Swal.fire({
        title: 'Preview da Resposta',
        html: `
            <div class="text-start">
                <div class="alert alert-info mb-4" style="white-space: pre-wrap; text-align: left;">${escapeHtml(response.text)}</div>
                <div class="text-muted fs-7">
                    <i class="ki-duotone ki-abstract-26 fs-6 me-1">
                        <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                    </i>
                    ${escapeHtml(response.agent_name || 'Assistente IA')}
                    ${response.tokens_used ? ` • ${response.tokens_used.toLocaleString('pt-BR')} tokens` : ''}
                    ${response.cost ? ` • R$ ${response.cost.toFixed(4).replace('.', ',')}` : ''}
                </div>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Usar esta Resposta',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        colorScheme: isDarkMode ? 'dark' : 'light',
        customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-light',
            popup: isDarkMode ? 'swal2-dark' : '',
            title: isDarkMode ? 'text-white' : '',
            htmlContainer: isDarkMode ? 'text-white' : ''
        },
        width: '600px'
    }).then((result) => {
        if (result.isConfirmed) {
            const messageInput = document.getElementById('messageInput');
            
            if (messageInput) {
                messageInput.value = response.text;
                messageInput.focus();
                
                // Marcar como usada se tiver ID
                if (responseId) {
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            response_id: responseId
                        })
                    }).catch(err => console.error('Erro ao marcar resposta como usada:', err));
                }
                
                // Fechar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_ai_assistant'));
                if (modal) {
                    modal.hide();
                }
            }
        }
    });
}

function loadAIResponseHistory() {
    if (!currentConversationId) {
        return;
    }
    
    const historyDiv = document.getElementById('aiResponseHistory');
    const historyContent = document.getElementById('aiResponseHistoryContent');
    const resultsDiv = document.getElementById('aiResponseResults');
    
    if (!historyDiv || !historyContent) return;
    
    // Mostrar seção de histórico e esconder resultados atuais
    historyDiv.classList.remove('d-none');
    if (resultsDiv) {
        resultsDiv.classList.add('d-none');
    }
    
    // Scroll para histórico
    historyDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    
    fetch(`?conversation_id=${currentConversationId}&limit=20`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.history) {
            const history = data.history;
            
            if (history.length === 0) {
                historyContent.innerHTML = `
                    <div class="text-center py-10">
                        <i class="ki-duotone ki-time fs-3x text-muted mb-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="text-muted">Nenhuma resposta no histórico ainda</div>
                    </div>
                `;
                return;
            }
            
            let html = '';
            history.forEach(item => {
                const date = new Date(item.created_at);
                const formattedDate = date.toLocaleString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                const toneEmoji = {
                    'professional': '💼',
                    'friendly': '😊',
                    'formal': '📋'
                }[item.tone] || '💬';
                
                html += `
                    <div class="card card-flush shadow-sm mb-4">
                        <div class="card-body p-6">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="d-flex align-items-center">
                                    <span class="badge badge-light-primary badge-sm me-2">${toneEmoji} ${escapeHtml(item.tone || 'N/A')}</span>
                                    ${item.is_favorite ? '<span class="badge badge-light-warning badge-sm me-2">⭐ Favorita</span>' : ''}
                                    ${item.used_at ? '<span class="badge badge-light-success badge-sm">✓ Usada</span>' : ''}
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-icon btn-light-${item.is_favorite ? 'warning' : 'gray'}" 
                                            onclick="toggleFavoriteResponse(${item.id}, this)" 
                                            title="${item.is_favorite ? 'Remover dos favoritos' : 'Adicionar aos favoritos'}">
                                        <i class="ki-duotone ki-star fs-5 ${item.is_favorite ? 'text-warning' : ''}">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <button class="btn btn-sm btn-icon btn-light-primary" 
                                            onclick="useHistoryResponse(${item.id}, '${escapeHtml(item.response_text).replace(/'/g, "\\'")}')" 
                                            title="Usar esta resposta">
                                        <i class="ki-duotone ki-check fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3" style="white-space: pre-wrap; line-height: 1.7;">${escapeHtml(item.response_text)}</div>
                            <div class="d-flex justify-content-between align-items-center text-muted fs-7">
                                <div>
                                    <i class="ki-duotone ki-time fs-6 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    ${formattedDate}
                                </div>
                                <div>
                                    ${escapeHtml(item.agent_name || 'Assistente IA')}
                                    ${item.tokens_used ? ` • ${item.tokens_used.toLocaleString('pt-BR')} tokens` : ''}
                                    ${item.cost ? ` • R$ ${parseFloat(item.cost).toFixed(4).replace('.', ',')}` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            historyContent.innerHTML = html;
        } else {
            historyContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="ki-duotone ki-information-5 fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Erro ao carregar histórico
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Erro ao carregar histórico:', error);
        historyContent.innerHTML = `
            <div class="alert alert-danger">
                <i class="ki-duotone ki-information-5 fs-2 me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                Erro ao carregar histórico
            </div>
        `;
    });
}

function hideAIResponseHistory() {
    const historyDiv = document.getElementById('aiResponseHistory');
    const resultsDiv = document.getElementById('aiResponseResults');
    
    if (historyDiv) {
        historyDiv.classList.add('d-none');
    }
    if (resultsDiv) {
        resultsDiv.classList.remove('d-none');
    }
}

function toggleFavoriteResponse(responseId, buttonElement) {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            response_id: responseId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recarregar histórico
            loadAIResponseHistory();
        } else {
            alert('Erro ao atualizar favorito: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao atualizar favorito');
    });
}

function useHistoryResponse(responseId, responseText) {
    const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                       document.body.classList.contains('dark-mode') ||
                       window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    Swal.fire({
        title: 'Preview da Resposta',
        html: `
            <div class="text-start">
                <div class="alert alert-info mb-4" style="white-space: pre-wrap; text-align: left;">${escapeHtml(responseText)}</div>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Usar esta Resposta',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        colorScheme: isDarkMode ? 'dark' : 'light',
        customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-light',
            popup: isDarkMode ? 'swal2-dark' : '',
            title: isDarkMode ? 'text-white' : '',
            htmlContainer: isDarkMode ? 'text-white' : ''
        },
        width: '600px'
    }).then((result) => {
        if (result.isConfirmed) {
            const messageInput = document.getElementById('messageInput');
            
            if (messageInput) {
                messageInput.value = responseText;
                messageInput.focus();
                
                // Marcar como usada
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        response_id: responseId
                    })
                }).catch(err => console.error('Erro ao marcar resposta como usada:', err));
                
                // Fechar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_ai_assistant'));
                if (modal) {
                    modal.hide();
                }
            }
        }
    });
}

function executeAIFeature(featureKey) {
    if (!currentConversationId) {
        alert('Selecione uma conversa primeiro');
        return;
    }
    
    const feature = aiAssistantFeatures.find(f => f.feature_key === featureKey);
    if (!feature) {
        alert('Funcionalidade não encontrada');
        return;
    }
    
    // Mostrar loading
    const loadingHtml = `
        <div class="text-center py-10">
            <span class="spinner-border spinner-border-lg text-primary mb-3" role="status"></span>
            <div class="text-muted">Processando...</div>
        </div>
    `;
    
    // Criar modal temporário para mostrar resultado
    const resultModal = document.createElement('div');
    resultModal.className = 'modal fade';
    resultModal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered mw-700px">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="fw-bold">${escapeHtml(feature.name)}</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <i class="ki-duotone ki-cross fs-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                </div>
                <div class="modal-body">
                    ${loadingHtml}
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(resultModal);
    const bsModal = new bootstrap.Modal(resultModal);
    bsModal.show();
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            conversation_id: currentConversationId,
            feature_key: featureKey
        })
    })
    .then(response => response.json())
    .then(data => {
        const modalBody = resultModal.querySelector('.modal-body');
        if (data.success && data.result) {
            // Renderizar resultado baseado no tipo de funcionalidade
            modalBody.innerHTML = renderAIResult(featureKey, data.result, data, currentConversationId);
        } else {
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="ki-duotone ki-information-5 fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    ${escapeHtml(data.message || 'Erro ao executar funcionalidade')}
                </div>
            `;
        }
    })
    .catch(error => {
        const modalBody = resultModal.querySelector('.modal-body');
        modalBody.innerHTML = `
            <div class="alert alert-danger">
                <i class="ki-duotone ki-information-5 fs-2 me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                Erro: ${escapeHtml(error.message)}
            </div>
        `;
    });
    
    // Remover modal quando fechar
    resultModal.addEventListener('hidden.bs.modal', function() {
        resultModal.remove();
    });
}

function showAIError(message) {
    const error = document.getElementById('aiAssistantError');
    const errorMessage = document.getElementById('aiAssistantErrorMessage');
    
    if (error && errorMessage) {
        errorMessage.textContent = message;
        error.classList.remove('d-none');
    }
}

/**
 * Renderizar resultado do Assistente IA com formatação especial e ações rápidas
 */
function renderAIResult(featureKey, result, data, conversationId) {
    const agentInfo = `
        <div class="mt-3 d-flex align-items-center justify-content-between">
            <div class="text-muted fs-7">
                <i class="ki-duotone ki-abstract-26 fs-6">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                ${escapeHtml(data.agent_used?.name || 'Assistente IA')}
                ${data.tokens_used ? `<span class="ms-2">• ${data.tokens_used} tokens</span>` : ''}
                ${data.cost ? `<span class="ms-2">• R$ ${data.cost.toFixed(4)}</span>` : ''}
            </div>
        </div>
    `;
    
    switch (featureKey) {
        case 'suggest_tags':
            // Extrair tags do resultado (pode ser lista separada por vírgula)
            const tagsText = result.trim();
            const suggestedTags = tagsText.split(/[,;]/).map(t => t.trim()).filter(t => t);
            
            return `
                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-4">Tags Sugeridas</h5>
                        <div class="mb-4">
                            ${suggestedTags.map(tag => `
                                <span class="badge badge-lg me-2 mb-2" style="background-color: #009ef720; color: #009ef7;">
                                    ${escapeHtml(tag)}
                                </span>
                            `).join('')}
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-primary" onclick="applySuggestedTags(${conversationId}, ${JSON.stringify(suggestedTags).replace(/"/g, '&quot;')})">
                                <i class="ki-duotone ki-check fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Aplicar Tags
                            </button>
                            <button class="btn btn-sm btn-light" onclick="copyToClipboard('${escapeHtml(tagsText).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-copy fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Copiar
                            </button>
                        </div>
                        ${agentInfo}
                    </div>
                </div>
            `;
            
        case 'translate':
            return `
                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-4">Tradução</h5>
                        <div class="alert alert-light-primary d-flex align-items-start p-4 mb-4">
                            <i class="ki-duotone ki-translate fs-2x text-primary me-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="flex-grow-1" style="white-space: pre-wrap;">${escapeHtml(result)}</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-primary" onclick="useTranslatedText('${escapeHtml(result).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-check fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Usar Tradução
                            </button>
                            <button class="btn btn-sm btn-light" onclick="copyToClipboard('${escapeHtml(result).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-copy fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Copiar
                            </button>
                        </div>
                        ${agentInfo}
                    </div>
                </div>
            `;
            
        case 'improve_grammar':
            return `
                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-4">Texto Melhorado</h5>
                        <div class="alert alert-light-success d-flex align-items-start p-4 mb-4">
                            <i class="ki-duotone ki-pencil fs-2x text-success me-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="flex-grow-1" style="white-space: pre-wrap;">${escapeHtml(result)}</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-primary" onclick="useImprovedText('${escapeHtml(result).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-check fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Usar Texto Melhorado
                            </button>
                            <button class="btn btn-sm btn-light" onclick="copyToClipboard('${escapeHtml(result).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-copy fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Copiar
                            </button>
                        </div>
                        ${agentInfo}
                    </div>
                </div>
            `;
            
        case 'analyze_sentiment':
            // Detectar sentimento no resultado
            const sentimentLower = result.toLowerCase();
            let sentimentBadge = 'secondary';
            let sentimentIcon = 'ki-information';
            if (sentimentLower.includes('positivo') || sentimentLower.includes('positive')) {
                sentimentBadge = 'success';
                sentimentIcon = 'ki-like';
            } else if (sentimentLower.includes('negativo') || sentimentLower.includes('negative')) {
                sentimentBadge = 'danger';
                sentimentIcon = 'ki-dislike';
            } else if (sentimentLower.includes('neutro') || sentimentLower.includes('neutral')) {
                sentimentBadge = 'warning';
                sentimentIcon = 'ki-information';
            }
            
            return `
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <span class="badge badge-${sentimentBadge} badge-lg me-3">
                                <i class="ki-duotone ${sentimentIcon} fs-6 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Análise de Sentimento
                            </span>
                        </div>
                        <div style="white-space: pre-wrap; line-height: 1.8;">${escapeHtml(result)}</div>
                        ${agentInfo}
                    </div>
                </div>
            `;
            
        case 'summarize':
            return `
                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-4">
                            <i class="ki-duotone ki-file-down fs-2 text-primary me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Resumo da Conversa
                        </h5>
                        <div class="alert alert-light-primary p-4 mb-4" style="white-space: pre-wrap; line-height: 1.8;">${escapeHtml(result)}</div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-light" onclick="copyToClipboard('${escapeHtml(result).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-copy fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Copiar Resumo
                            </button>
                        </div>
                        ${agentInfo}
                    </div>
                </div>
            `;
            
        case 'extract_info':
            return `
                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-4">
                            <i class="ki-duotone ki-information fs-2 text-primary me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Informações Extraídas
                        </h5>
                        <div style="white-space: pre-wrap; line-height: 1.8;">${escapeHtml(result)}</div>
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-sm btn-light" onclick="copyToClipboard('${escapeHtml(result).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-copy fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Copiar
                            </button>
                        </div>
                        ${agentInfo}
                    </div>
                </div>
            `;
            
        case 'suggest_next_steps':
            return `
                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-4">
                            <i class="ki-duotone ki-arrow-right fs-2 text-primary me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Próximos Passos Sugeridos
                        </h5>
                        <div style="white-space: pre-wrap; line-height: 1.8;">${escapeHtml(result)}</div>
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-sm btn-light" onclick="copyToClipboard('${escapeHtml(result).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-copy fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Copiar
                            </button>
                        </div>
                        ${agentInfo}
                    </div>
                </div>
            `;
            
        default:
            return `
                <div class="card">
                    <div class="card-body">
                        <div style="white-space: pre-wrap;">${escapeHtml(result)}</div>
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-sm btn-light" onclick="copyToClipboard('${escapeHtml(result).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-copy fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Copiar
                            </button>
                        </div>
                        ${agentInfo}
                    </div>
                </div>
            `;
    }
}

/**
 * Aplicar tags sugeridas na conversa
 */
async function applySuggestedTags(conversationId, tags) {
    if (!conversationId || !tags || tags.length === 0) {
        alert('Nenhuma tag para aplicar');
        return;
    }
    
    // Buscar tags existentes por nome
    try {
        const tagsResponse = await fetch('');
        const tagsData = await tagsResponse.json();
        
        if (!tagsData.success || !tagsData.tags) {
            throw new Error('Erro ao carregar tags');
        }
        
        const allTags = tagsData.tags;
        const tagsToAdd = [];
        
        // Encontrar IDs das tags sugeridas
        tags.forEach(suggestedTag => {
            const tag = allTags.find(t => t.name.toLowerCase() === suggestedTag.toLowerCase());
            if (tag) {
                tagsToAdd.push(tag.id);
            }
        });
        
        if (tagsToAdd.length === 0) {
            alert('Nenhuma das tags sugeridas foi encontrada no sistema');
            return;
        }
        
        // Adicionar tags à conversa
        const promises = tagsToAdd.map(tagId => 
            fetch(`/${conversationId}/tags`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ tag_id: tagId })
            })
        );
        
        await Promise.all(promises);
        
        // Fechar modal e recarregar página para mostrar tags aplicadas
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
        });
        
        // Mostrar mensagem de sucesso
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                          document.body.classList.contains('dark-mode') ||
                          window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        Swal.fire({
            icon: 'success',
            title: 'Tags Aplicadas',
            text: `${tagsToAdd.length} tag(s) aplicada(s) com sucesso!`,
            timer: 2000,
            showConfirmButton: false,
            colorScheme: isDarkMode ? 'dark' : 'light',
            customClass: {
                popup: isDarkMode ? 'swal2-dark' : '',
                title: isDarkMode ? 'text-white' : '',
                htmlContainer: isDarkMode ? 'text-white' : ''
            }
        });
        
        // Recarregar página após um breve delay
        setTimeout(() => {
            window.location.reload();
        }, 500);
        
    } catch (error) {
        console.error('Erro ao aplicar tags:', error);
        alert('Erro ao aplicar tags: ' + error.message);
    }
}

/**
 * Usar texto traduzido na mensagem
 */
function useTranslatedText(text) {
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.value = text;
        messageInput.focus();
        
        // Fechar modal
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
        });
    }
}

/**
 * Usar texto melhorado na mensagem
 */
function useImprovedText(text) {
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.value = text;
        messageInput.focus();
        
        // Fechar modal
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
        });
    }
}

/**
 * Copiar texto para área de transferência
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                          document.body.classList.contains('dark-mode') ||
                          window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        Swal.fire({
            icon: 'success',
            title: 'Copiado!',
            text: 'Texto copiado para área de transferência',
            timer: 1500,
            showConfirmButton: false,
            colorScheme: isDarkMode ? 'dark' : 'light',
            customClass: {
                popup: isDarkMode ? 'swal2-dark' : '',
                title: isDarkMode ? 'text-white' : '',
                htmlContainer: isDarkMode ? 'text-white' : ''
            }
        });
    }).catch(err => {
        console.error('Erro ao copiar:', err);
        alert('Erro ao copiar texto');
    });
}

// Carregar funcionalidades quando modal for aberto
document.addEventListener('DOMContentLoaded', function() {
    const aiAssistantModal = document.getElementById('kt_modal_ai_assistant');
    if (aiAssistantModal) {
        aiAssistantModal.addEventListener('show.bs.modal', function() {
            loadAIAssistantFeatures();
        });
    }
    
    // Inicializar seletor rápido de templates
    initTemplateQuickSelect();
    
    // Inicializar seletor rápido de variáveis (ao digitar {{)
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('input', function(e) {
            const value = e.target.value;
            const cursorPos = e.target.selectionStart;
            const textBeforeCursor = value.substring(0, cursorPos);
            
            // Se digitar {{, mostrar seletor rápido de templates ou variáveis
            if (textBeforeCursor.endsWith('{{')) {
                // Mostrar seletor rápido de templates (pode ser expandido para variáveis também)
                showTemplateQuickSelect();
            }
        });
    }
});
