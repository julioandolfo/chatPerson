# EXEMPLOS PRÁTICOS - IMPLEMENTAÇÃO CHATWOOT COM METRONIC

## 📁 ESTRUTURA DE ARQUIVOS COMPLETA

```
chat/
├── public/
│   ├── assets/
│   │   ├── css/
│   │   │   ├── metronic/              # CSS do Metronic
│   │   │   └── custom/
│   │   │       ├── chatwoot-layout.css
│   │   │       ├── chat.css
│   │   │       └── conversation-list.css
│   │   │
│   │   ├── js/
│   │   │   ├── metronic/              # JS do Metronic
│   │   │   └── custom/
│   │   │       ├── chat.js
│   │   │       ├── conversation-list.js
│   │   │       └── websocket.js
│   │   │
│   │   └── plugins/
│   │       └── sortablejs/            # Para Kanban
│   │
│   └── index.php
│
├── views/
│   ├── layouts/
│   │   └── metronic/
│   │       ├── chatwoot-layout.php    # Layout principal
│   │       ├── header.php
│   │       └── sidebar.php
│   │
│   ├── conversations/
│   │   ├── index.php                  # Página principal
│   │   └── partials/
│   │       ├── list-header.php
│   │       ├── conversation-item.php
│   │       ├── chat-header.php
│   │       ├── chat-messages.php
│   │       └── chat-input.php
│   │
│   └── components/
│       └── message-bubble.php
│
└── app/
    └── Controllers/
        └── ConversationController.php
```

---

## 🎨 CSS CUSTOMIZADO COMPLETO

### `public/assets/css/custom/chatwoot-layout.css`

```css
/* ============================================
   CHATWOOT-LIKE LAYOUT COM METRONIC
   ============================================ */

:root {
    /* Cores Chatwoot */
    --chatwoot-primary: #1f93ff;
    --chatwoot-primary-dark: #0066cc;
    --chatwoot-success: #00d97e;
    --chatwoot-danger: #ff4757;
    --chatwoot-warning: #ffa502;
    --chatwoot-info: #17a2b8;
    
    /* Backgrounds */
    --chatwoot-bg-primary: #ffffff;
    --chatwoot-bg-secondary: #f9f9f9;
    --chatwoot-bg-tertiary: #f5f8fa;
    --chatwoot-bg-dark: #1e1e2d;
    
    /* Text */
    --chatwoot-text-primary: #1f2937;
    --chatwoot-text-secondary: #6b7280;
    --chatwoot-text-muted: #9ca3af;
    
    /* Borders */
    --chatwoot-border: #e4e6ef;
    --chatwoot-border-light: #f1f3f5;
    
    /* Shadows */
    --chatwoot-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --chatwoot-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    --chatwoot-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

/* ============================================
   LAYOUT PRINCIPAL - 3 COLUNAS
   ============================================ */

.page {
    height: calc(100vh - 60px); /* Altura menos header */
    overflow: hidden;
}

/* Sidebar Esquerda */
#kt_sidebar {
    width: 70px;
    background-color: var(--chatwoot-bg-dark);
    border-right: 1px solid var(--chatwoot-border);
    transition: width 0.3s ease;
    z-index: 100;
}

#kt_sidebar.expanded {
    width: 250px;
}

#kt_sidebar .sidebar-logo {
    padding: 1.5rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

#kt_sidebar .sidebar-logo img {
    max-width: 100%;
    height: auto;
}

/* Menu Sidebar */
.sidebar-menu {
    padding: 1rem 0;
}

.sidebar-menu .menu-item {
    margin: 0.25rem 0.5rem;
}

.sidebar-menu .menu-link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    color: rgba(255, 255, 255, 0.7);
    border-radius: 0.5rem;
    transition: all 0.2s ease;
    text-decoration: none;
}

.sidebar-menu .menu-link:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.9);
}

.sidebar-menu .menu-link.active {
    background-color: var(--chatwoot-primary);
    color: #fff;
}

.sidebar-menu .menu-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
}

.sidebar-menu .menu-title {
    white-space: nowrap;
    overflow: hidden;
    opacity: 0;
    transition: opacity 0.3s ease;
}

#kt_sidebar.expanded .menu-title {
    opacity: 1;
}

.sidebar-menu .menu-badge {
    margin-left: auto;
    opacity: 0;
    transition: opacity 0.3s ease;
}

#kt_sidebar.expanded .menu-badge {
    opacity: 1;
}

/* ============================================
   LISTA DE CONVERSAS (COLUNA CENTRAL)
   ============================================ */

.conversation-list-panel {
    width: 380px;
    background-color: var(--chatwoot-bg-secondary);
    border-right: 1px solid var(--chatwoot-border);
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

.conversation-list-header {
    background-color: var(--chatwoot-bg-primary);
    padding: 1.25rem;
    border-bottom: 1px solid var(--chatwoot-border);
    position: sticky;
    top: 0;
    z-index: 10;
    box-shadow: var(--chatwoot-shadow-sm);
}

.conversation-list-header h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    color: var(--chatwoot-text-primary);
}

.conversation-list-header .form-control {
    border-radius: 0.5rem;
    border: 1px solid var(--chatwoot-border);
    padding: 0.625rem 2.5rem 0.625rem 1rem;
}

.conversation-list-header .position-relative i {
    color: var(--chatwoot-text-muted);
}

.conversation-list-body {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem 0;
}

.conversation-item {
    padding: 1rem 1.25rem;
    background-color: var(--chatwoot-bg-primary);
    border-bottom: 1px solid var(--chatwoot-border-light);
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.conversation-item:hover {
    background-color: var(--chatwoot-bg-tertiary);
}

.conversation-item.active {
    background-color: #e8f4f8;
    border-left: 3px solid var(--chatwoot-primary);
}

.conversation-item.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background-color: var(--chatwoot-primary);
}

.conversation-item .symbol {
    flex-shrink: 0;
}

.conversation-item .symbol-label {
    border-radius: 50%;
}

.conversation-item .symbol-badge {
    width: 12px;
    height: 12px;
    border: 2px solid #fff;
    border-radius: 50%;
    position: absolute;
    bottom: 0;
    right: 0;
}

.conversation-item .fw-bold {
    font-size: 0.95rem;
    color: var(--chatwoot-text-primary);
    margin-bottom: 0.25rem;
}

.conversation-item .text-muted {
    font-size: 0.75rem;
    color: var(--chatwoot-text-muted);
}

.conversation-item .badge-circle {
    min-width: 20px;
    height: 20px;
    padding: 0 0.5rem;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ============================================
   JANELA DE CHAT (COLUNA DIREITA)
   ============================================ */

.chat-window-panel {
    flex: 1;
    background-color: var(--chatwoot-bg-tertiary);
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

.chat-header {
    background-color: var(--chatwoot-bg-primary);
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--chatwoot-border);
    position: sticky;
    top: 0;
    z-index: 10;
    box-shadow: var(--chatwoot-shadow-sm);
}

.chat-header .symbol {
    flex-shrink: 0;
}

.chat-header .fw-bold {
    font-size: 1rem;
    color: var(--chatwoot-text-primary);
    margin-bottom: 0.125rem;
}

.chat-header .text-muted {
    font-size: 0.75rem;
    color: var(--chatwoot-text-muted);
}

.chat-header .btn-icon {
    width: 36px;
    height: 36px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    background-image: 
        radial-gradient(circle at 1px 1px, rgba(0,0,0,0.05) 1px, transparent 0);
    background-size: 20px 20px;
}

.chat-messages .d-flex {
    margin-bottom: 1rem;
}

.chat-messages .d-flex:last-child {
    margin-bottom: 0;
}

.message-bubble {
    display: inline-block;
    padding: 0.75rem 1rem;
    border-radius: 1rem;
    max-width: 70%;
    word-wrap: break-word;
    line-height: 1.5;
    font-size: 0.9rem;
}

.message-received {
    background-color: var(--chatwoot-bg-primary);
    border-top-left-radius: 0.25rem;
    color: var(--chatwoot-text-primary);
    box-shadow: var(--chatwoot-shadow-sm);
}

.message-sent {
    background-color: var(--chatwoot-primary);
    color: #fff;
    border-top-right-radius: 0.25rem;
    box-shadow: var(--chatwoot-shadow-sm);
}

.chat-input {
    background-color: var(--chatwoot-bg-primary);
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--chatwoot-border);
    position: sticky;
    bottom: 0;
    z-index: 10;
}

.chat-input .form-control {
    border-radius: 0.5rem;
    border: 1px solid var(--chatwoot-border);
    resize: none;
    min-height: 44px;
    max-height: 120px;
}

.chat-input .btn-primary {
    min-width: 44px;
    height: 44px;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ============================================
   RESPONSIVIDADE
   ============================================ */

@media (max-width: 991.98px) {
    .conversation-list-panel {
        width: 320px;
    }
}

@media (max-width: 767.98px) {
    #kt_sidebar {
        transform: translateX(-100%);
        position: fixed;
        height: 100vh;
        z-index: 1000;
    }
    
    #kt_sidebar.show {
        transform: translateX(0);
    }
    
    .conversation-list-panel {
        width: 100%;
        display: none;
    }
    
    .conversation-list-panel.show {
        display: flex;
    }
    
    .chat-window-panel {
        display: none;
    }
    
    .chat-window-panel.show {
        display: flex;
    }
}

/* ============================================
   ANIMAÇÕES
   ============================================ */

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.conversation-item {
    animation: slideIn 0.3s ease;
}

.message-bubble {
    animation: slideIn 0.2s ease;
}

/* ============================================
   SCROLLBAR CUSTOMIZADA
   ============================================ */

.conversation-list-body::-webkit-scrollbar,
.chat-messages::-webkit-scrollbar {
    width: 6px;
}

.conversation-list-body::-webkit-scrollbar-track,
.chat-messages::-webkit-scrollbar-track {
    background: transparent;
}

.conversation-list-body::-webkit-scrollbar-thumb,
.chat-messages::-webkit-scrollbar-thumb {
    background: var(--chatwoot-border);
    border-radius: 3px;
}

.conversation-list-body::-webkit-scrollbar-thumb:hover,
.chat-messages::-webkit-scrollbar-thumb:hover {
    background: var(--chatwoot-text-muted);
}
```

---

## 📄 EXEMPLO DE LAYOUT PHP

### `views/layouts/metronic/chatwoot-layout.php`

```php
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Sistema Multiatendimento' ?></title>
    
    <!-- Metronic CSS -->
    <link href="<?= asset('assets/css/metronic/plugins.bundle.css') ?>" rel="stylesheet">
    <link href="<?= asset('assets/css/metronic/style.bundle.css') ?>" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?= asset('assets/css/custom/chatwoot-layout.css') ?>" rel="stylesheet">
    <link href="<?= asset('assets/css/custom/chat.css') ?>" rel="stylesheet">
    <link href="<?= asset('assets/css/custom/conversation-list.css') ?>" rel="stylesheet">
    
    <?= $head ?? '' ?>
</head>
<body class="header-fixed header-tablet-and-mobile-fixed sidebar-enabled">
    <!-- Header -->
    <?php include __DIR__ . '/header.php'; ?>
    
    <!-- Wrapper -->
    <div class="d-flex flex-column flex-root">
        <div class="page d-flex flex-row flex-column-fluid">
            <!-- Sidebar -->
            <?php include __DIR__ . '/sidebar.php'; ?>
            
            <!-- Content -->
            <div class="wrapper d-flex flex-column flex-row-fluid">
                <div class="content d-flex flex-column flex-column-fluid">
                    <?= $content ?? '' ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Metronic JS -->
    <script src="<?= asset('assets/js/metronic/plugins.bundle.js') ?>"></script>
    <script src="<?= asset('assets/js/metronic/scripts.bundle.js') ?>"></script>
    
    <!-- Custom JS -->
    <script src="<?= asset('assets/js/custom/layout.js') ?>"></script>
    
    <?= $scripts ?? '' ?>
</body>
</html>
```

---

## 📄 EXEMPLO DE PÁGINA DE CONVERSAS

### `views/conversations/index.php`

```php
<?php
$layout = 'layouts.metronic.chatwoot-layout';
$title = 'Conversas';
?>

@extends($layout)

@section('content')
<div class="d-flex flex-row h-100">
    <!-- Lista de Conversas -->
    <div class="conversation-list-panel d-flex flex-column">
        <!-- Header da Lista -->
        <div class="conversation-list-header">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h3 class="fw-bold m-0">Conversas</h3>
                <button class="btn btn-sm btn-primary" id="new-conversation">
                    <i class="ki-duotone ki-plus fs-5"></i>
                    <span class="d-none d-md-inline">Nova</span>
                </button>
            </div>
            
            <!-- Busca -->
            <div class="position-relative mb-3">
                <input 
                    type="text" 
                    class="form-control form-control-solid" 
                    placeholder="Buscar conversas..."
                    id="search-conversations"
                />
                <i class="ki-duotone ki-magnifier fs-2 position-absolute end-0 top-50 translate-middle-y me-3 text-muted"></i>
            </div>
            
            <!-- Filtros -->
            <div class="d-flex gap-2">
                <select class="form-select form-select-sm" id="filter-status">
                    <option value="">Todas</option>
                    <option value="open">Abertas</option>
                    <option value="pending">Pendentes</option>
                    <option value="resolved">Resolvidas</option>
                </select>
                <select class="form-select form-select-sm" id="filter-inbox">
                    <option value="">Todos os Inboxes</option>
                    <?php foreach ($inboxes as $inbox): ?>
                        <option value="<?= $inbox->id ?>"><?= htmlspecialchars($inbox->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Lista de Conversas -->
        <div class="conversation-list-body" id="conversation-list">
            <?php if (empty($conversations)): ?>
                <div class="text-center p-5 text-muted">
                    <i class="ki-duotone ki-chat fs-3x mb-3"></i>
                    <p>Nenhuma conversa encontrada</p>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $conversation): ?>
                    <?php include __DIR__ . '/partials/conversation-item.php'; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Janela de Chat -->
    <div class="chat-window-panel d-flex flex-column" id="chat-window">
        <?php include __DIR__ . '/partials/chat-header.php'; ?>
        <?php include __DIR__ . '/partials/chat-messages.php'; ?>
        <?php include __DIR__ . '/partials/chat-input.php'; ?>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Inicializar chat
    const chatApp = new ChatApp({
        conversationList: document.getElementById('conversation-list'),
        chatWindow: document.getElementById('chat-window'),
        searchInput: document.getElementById('search-conversations'),
        filterStatus: document.getElementById('filter-status'),
        filterInbox: document.getElementById('filter-inbox')
    });
    
    chatApp.init();
</script>
@endsection
```

---

## 📄 COMPONENTE DE ITEM DE CONVERSA

### `views/conversations/partials/conversation-item.php`

```php
<?php
$conversation = $conversation ?? [];
$contact = $conversation['contact'] ?? [];
$lastMessage = $conversation['last_message'] ?? null;
$unreadCount = $conversation['unread_count'] ?? 0;
$isActive = $conversation['id'] == ($activeConversationId ?? null);
?>

<div class="conversation-item <?= $isActive ? 'active' : '' ?>" 
     data-conversation-id="<?= $conversation['id'] ?>"
     onclick="loadConversation(<?= $conversation['id'] ?>)">
    <div class="d-flex align-items-start">
        <!-- Avatar -->
        <div class="symbol symbol-45px me-3">
            <img src="<?= $contact['avatar'] ?? asset('assets/media/avatars/blank.png') ?>" 
                 alt="<?= htmlspecialchars($contact['name']) ?>" 
                 class="symbol-label" />
            <?php if ($contact['online'] ?? false): ?>
                <span class="symbol-badge symbol-badge-bottom bg-success"></span>
            <?php endif; ?>
        </div>
        
        <!-- Conteúdo -->
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <div class="fw-bold text-gray-800 text-truncate">
                    <?= htmlspecialchars($contact['name']) ?>
                </div>
                <span class="text-muted fs-7">
                    <?= $conversation['updated_at'] ? timeAgo($conversation['updated_at']) : '' ?>
                </span>
            </div>
            
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted fs-7 text-truncate me-2">
                    <?php if ($lastMessage): ?>
                        <?= htmlspecialchars(mb_substr($lastMessage['content'], 0, 50)) ?>
                        <?= mb_strlen($lastMessage['content']) > 50 ? '...' : '' ?>
                    <?php else: ?>
                        <em>Nenhuma mensagem</em>
                    <?php endif; ?>
                </div>
                <?php if ($unreadCount > 0): ?>
                    <span class="badge badge-circle badge-danger"><?= $unreadCount ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Tags -->
            <?php if (!empty($conversation['tags'])): ?>
                <div class="d-flex gap-1 mt-2">
                    <?php foreach (array_slice($conversation['tags'], 0, 2) as $tag): ?>
                        <span class="badge badge-light-primary badge-sm">
                            <?= htmlspecialchars($tag['name']) ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if (count($conversation['tags']) > 2): ?>
                        <span class="badge badge-light badge-sm">
                            +<?= count($conversation['tags']) - 2 ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
```

---

## 📄 COMPONENTE DE MENSAGEM

### `views/conversations/partials/message-bubble.php`

```php
<?php
$message = $message ?? [];
$isSent = $message['sender_type'] === 'agent';
$sender = $message['sender'] ?? [];
?>

<div class="d-flex mb-4 <?= $isSent ? 'justify-content-end' : '' ?>">
    <?php if (!$isSent): ?>
        <div class="symbol symbol-35px me-3">
            <img src="<?= $sender['avatar'] ?? asset('assets/media/avatars/blank.png') ?>" 
                 alt="<?= htmlspecialchars($sender['name']) ?>" 
                 class="symbol-label" />
        </div>
    <?php endif; ?>
    
    <div class="flex-grow-1 <?= $isSent ? 'text-end' : '' ?>">
        <div class="d-flex align-items-center mb-1 <?= $isSent ? 'justify-content-end' : '' ?>">
            <?php if (!$isSent): ?>
                <span class="fw-bold text-gray-800 me-2">
                    <?= htmlspecialchars($sender['name']) ?>
                </span>
            <?php endif; ?>
            <span class="text-muted fs-7">
                <?= date('H:i', strtotime($message['created_at'])) ?>
            </span>
            <?php if ($isSent): ?>
                <span class="fw-bold text-gray-800 ms-2">Você</span>
            <?php endif; ?>
        </div>
        
        <div class="message-bubble <?= $isSent ? 'message-sent' : 'message-received' ?>">
            <?= nl2br(htmlspecialchars($message['content'])) ?>
            
            <!-- Anexos -->
            <?php if (!empty($message['attachments'])): ?>
                <div class="mt-2">
                    <?php foreach ($message['attachments'] as $attachment): ?>
                        <a href="<?= $attachment['url'] ?>" 
                           target="_blank" 
                           class="btn btn-sm btn-light me-2">
                            <i class="ki-duotone ki-file fs-5"></i>
                            <?= htmlspecialchars($attachment['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($isSent): ?>
        <div class="symbol symbol-35px ms-3">
            <img src="<?= auth()->user()->avatar ?? asset('assets/media/avatars/blank.png') ?>" 
                 alt="Você" 
                 class="symbol-label" />
        </div>
    <?php endif; ?>
</div>
```

---

## 🚀 JAVASCRIPT BÁSICO

### `public/assets/js/custom/chat.js`

```javascript
class ChatApp {
    constructor(options) {
        this.conversationList = options.conversationList;
        this.chatWindow = options.chatWindow;
        this.searchInput = options.searchInput;
        this.filterStatus = options.filterStatus;
        this.filterInbox = options.filterInbox;
        this.currentConversationId = null;
        this.ws = null;
    }
    
    init() {
        this.setupEventListeners();
        this.connectWebSocket();
    }
    
    setupEventListeners() {
        // Busca
        if (this.searchInput) {
            this.searchInput.addEventListener('input', (e) => {
                this.searchConversations(e.target.value);
            });
        }
        
        // Filtros
        if (this.filterStatus) {
            this.filterStatus.addEventListener('change', () => {
                this.filterConversations();
            });
        }
        
        if (this.filterInbox) {
            this.filterInbox.addEventListener('change', () => {
                this.filterConversations();
            });
        }
        
        // Enviar mensagem
        const messageInput = document.getElementById('message-input');
        const sendButton = document.getElementById('send-message');
        
        if (messageInput && sendButton) {
            sendButton.addEventListener('click', () => {
                this.sendMessage();
            });
            
            messageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
        }
    }
    
    loadConversation(conversationId) {
        this.currentConversationId = conversationId;
        
        // Marcar como ativa
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.remove('active');
        });
        document.querySelector(`[data-conversation-id="${conversationId}"]`)?.classList.add('active');
        
        // Carregar mensagens
        this.loadMessages(conversationId);
        
        // Mobile: mostrar chat
        if (window.innerWidth < 768) {
            this.conversationList.classList.remove('show');
            this.chatWindow.classList.add('show');
        }
    }
    
    async loadMessages(conversationId) {
        try {
            const response = await fetch(`/api/conversations/${conversationId}/messages`);
            const data = await response.json();
            
            const messagesContainer = document.getElementById('chat-messages');
            messagesContainer.innerHTML = '';
            
            data.messages.forEach(message => {
                const messageElement = this.createMessageElement(message);
                messagesContainer.appendChild(messageElement);
            });
            
            // Scroll para baixo
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        } catch (error) {
            console.error('Erro ao carregar mensagens:', error);
        }
    }
    
    createMessageElement(message) {
        const div = document.createElement('div');
        div.className = `d-flex mb-4 ${message.sender_type === 'agent' ? 'justify-content-end' : ''}`;
        
        const isSent = message.sender_type === 'agent';
        const bubbleClass = isSent ? 'message-sent' : 'message-received';
        
        div.innerHTML = `
            ${!isSent ? `
                <div class="symbol symbol-35px me-3">
                    <img src="${message.sender.avatar || '/assets/media/avatars/blank.png'}" 
                         alt="${message.sender.name}" 
                         class="symbol-label" />
                </div>
            ` : ''}
            <div class="flex-grow-1 ${isSent ? 'text-end' : ''}">
                <div class="d-flex align-items-center mb-1 ${isSent ? 'justify-content-end' : ''}">
                    ${!isSent ? `<span class="fw-bold text-gray-800 me-2">${message.sender.name}</span>` : ''}
                    <span class="text-muted fs-7">${this.formatTime(message.created_at)}</span>
                    ${isSent ? `<span class="fw-bold text-gray-800 ms-2">Você</span>` : ''}
                </div>
                <div class="message-bubble ${bubbleClass}">
                    ${this.escapeHtml(message.content)}
                </div>
            </div>
            ${isSent ? `
                <div class="symbol symbol-35px ms-3">
                    <img src="/assets/media/avatars/blank.png" alt="Você" class="symbol-label" />
                </div>
            ` : ''}
        `;
        
        return div;
    }
    
    async sendMessage() {
        const input = document.getElementById('message-input');
        const content = input.value.trim();
        
        if (!content || !this.currentConversationId) {
            return;
        }
        
        try {
            const response = await fetch(`/api/conversations/${this.currentConversationId}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ content })
            });
            
            if (response.ok) {
                input.value = '';
                // Mensagem será adicionada via WebSocket
            }
        } catch (error) {
            console.error('Erro ao enviar mensagem:', error);
        }
    }
    
    connectWebSocket() {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.host}/ws`;
        
        this.ws = new WebSocket(wsUrl);
        
        this.ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleWebSocketMessage(data);
        };
        
        this.ws.onerror = (error) => {
            console.error('WebSocket error:', error);
        };
    }
    
    handleWebSocketMessage(data) {
        if (data.type === 'new_message' && data.conversation_id === this.currentConversationId) {
            const messageElement = this.createMessageElement(data.message);
            const messagesContainer = document.getElementById('chat-messages');
            messagesContainer.appendChild(messageElement);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    }
    
    searchConversations(query) {
        const items = this.conversationList.querySelectorAll('.conversation-item');
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(query.toLowerCase()) ? '' : 'none';
        });
    }
    
    filterConversations() {
        // Implementar filtros
    }
    
    formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Função global para carregar conversa
function loadConversation(conversationId) {
    if (window.chatApp) {
        window.chatApp.loadConversation(conversationId);
    }
}
```

---

Estes exemplos fornecem uma base para implementar o layout Chatwoot usando Metronic. Adapte conforme necessário ao seu projeto específico.

