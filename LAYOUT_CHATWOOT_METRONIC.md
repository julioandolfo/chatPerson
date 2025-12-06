# GUIA DE IMPLEMENTAÇÃO - LAYOUT CHATWOOT 4 COM METRONIC

## 📋 ESTRUTURA DO LAYOUT CHATWOOT 4

### Layout Característico do Chatwoot:
```
┌─────────────────────────────────────────────────────────────┐
│ HEADER (Top Bar)                                            │
│ [Logo] [Busca] [Notificações] [Perfil]                    │
├──────────┬──────────────────────┬───────────────────────────┤
│          │                      │                           │
│ SIDEBAR  │  LISTA CONVERSAS    │   JANELA DE CHAT         │
│          │  (Meio)             │   (Direita)              │
│ - Home   │                      │                           │
│ - Inbox  │  [Conversa 1]       │  [Header Contato]        │
│ - Contatos│  [Conversa 2]       │  ┌─────────────────────┐ │
│ - Reports│  [Conversa 3]       │  │ Mensagens...        │ │
│ - Settings│  [Conversa 4]       │  │                     │ │
│          │                      │  │                     │ │
│          │  [Scroll]            │  └─────────────────────┘ │
│          │                      │  [Input Mensagem]        │
│          │                      │                           │
└──────────┴──────────────────────┴───────────────────────────┘
```

### Características Principais:
1. **Sidebar Esquerda**: Navegação principal (compacta)
2. **Lista de Conversas**: Painel central com lista de conversas
3. **Janela de Chat**: Área de mensagens à direita
4. **Layout Responsivo**: Em mobile, lista e chat alternam
5. **Header Fixo**: Barra superior com busca e ações

---

## 🎯 DEMO METRONIC RECOMENDADA

### Demo 3 - Compact Sidebar ⭐ IDEAL
**Link**: https://preview.keenthemes.com/metronic8/demo3/index.html

**Por quê?**
- Sidebar compacta (economiza espaço)
- Layout limpo e focado
- Fácil de adaptar para 3 colunas
- Componentes de chat disponíveis
- Design moderno

---

## 📐 ESTRUTURA HTML/CSS PROPOSTA

### Layout Base (3 Colunas)

```html
<!-- Estrutura Principal -->
<div class="d-flex flex-column flex-root">
    <!-- Header -->
    <div id="kt_header" class="header">
        <!-- Header content -->
    </div>
    
    <!-- Wrapper -->
    <div class="d-flex flex-row flex-column-fluid page">
        <!-- Sidebar Esquerda -->
        <div id="kt_sidebar" class="sidebar sidebar-dark">
            <!-- Menu navegação -->
        </div>
        
        <!-- Conteúdo Principal (2 colunas) -->
        <div class="d-flex flex-column flex-row-fluid">
            <!-- Lista de Conversas (Coluna Esquerda) -->
            <div id="conversation-list" class="conversation-list-panel">
                <!-- Lista de conversas -->
            </div>
            
            <!-- Janela de Chat (Coluna Direita) -->
            <div id="chat-window" class="chat-window-panel">
                <!-- Área de chat -->
            </div>
        </div>
    </div>
</div>
```

---

## 🎨 COMPONENTES ESPECÍFICOS

### 1. SIDEBAR ESQUERDA (Navegação)

**Baseado em**: Metronic Sidebar Component

```html
<!-- Sidebar Compacta -->
<div class="sidebar sidebar-dark" id="kt_sidebar">
    <div class="sidebar-logo">
        <a href="/">
            <img src="logo.png" alt="Logo" />
        </a>
    </div>
    
    <div class="sidebar-menu">
        <div class="menu-item">
            <a href="/dashboard" class="menu-link active">
                <span class="menu-icon">
                    <i class="ki-duotone ki-home fs-2"></i>
                </span>
                <span class="menu-title">Dashboard</span>
            </a>
        </div>
        
        <div class="menu-item">
            <a href="/conversations" class="menu-link">
                <span class="menu-icon">
                    <i class="ki-duotone ki-chat fs-2"></i>
                </span>
                <span class="menu-title">Conversas</span>
                <span class="menu-badge badge badge-danger">12</span>
            </a>
        </div>
        
        <div class="menu-item">
            <a href="/contacts" class="menu-link">
                <span class="menu-icon">
                    <i class="ki-duotone ki-user fs-2"></i>
                </span>
                <span class="menu-title">Contatos</span>
            </a>
        </div>
        
        <div class="menu-item">
            <a href="/reports" class="menu-link">
                <span class="menu-icon">
                    <i class="ki-duotone ki-chart fs-2"></i>
                </span>
                <span class="menu-title">Relatórios</span>
            </a>
        </div>
        
        <div class="menu-item">
            <a href="/settings" class="menu-link">
                <span class="menu-icon">
                    <i class="ki-duotone ki-setting fs-2"></i>
                </span>
                <span class="menu-title">Configurações</span>
            </a>
        </div>
    </div>
</div>
```

**CSS Customizado**:
```css
.sidebar {
    width: 70px; /* Compacta quando fechada */
    transition: width 0.3s ease;
}

.sidebar.expanded {
    width: 250px; /* Expandida */
}

.sidebar-menu .menu-item {
    padding: 0.75rem 1rem;
}

.sidebar-menu .menu-link {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    border-radius: 0.5rem;
    transition: background-color 0.2s;
}

.sidebar-menu .menu-link:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.sidebar-menu .menu-link.active {
    background-color: rgba(255, 255, 255, 0.15);
}
```

---

### 2. LISTA DE CONVERSAS (Painel Central)

**Baseado em**: Metronic List Component + Custom Chat List

```html
<!-- Painel Lista de Conversas -->
<div class="conversation-list-panel d-flex flex-column">
    <!-- Header da Lista -->
    <div class="conversation-list-header p-4 border-bottom">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="fw-bold m-0">Conversas</h3>
            <button class="btn btn-sm btn-primary">
                <i class="ki-duotone ki-plus fs-5"></i>
                Nova Conversa
            </button>
        </div>
        
        <!-- Busca -->
        <div class="position-relative">
            <input 
                type="text" 
                class="form-control form-control-solid" 
                placeholder="Buscar conversas..."
                id="search-conversations"
            />
            <i class="ki-duotone ki-magnifier fs-2 position-absolute end-0 top-50 translate-middle-y me-3"></i>
        </div>
        
        <!-- Filtros -->
        <div class="d-flex gap-2 mt-3">
            <select class="form-select form-select-sm">
                <option>Todas</option>
                <option>Não atribuídas</option>
                <option>Minhas</option>
                <option>Resolvidas</option>
            </select>
            <select class="form-select form-select-sm">
                <option>Todos os Inboxes</option>
                <option>WhatsApp</option>
                <option>Email</option>
            </select>
        </div>
    </div>
    
    <!-- Lista de Conversas -->
    <div class="conversation-list-body flex-grow-1 overflow-auto">
        <!-- Item de Conversa -->
        <div class="conversation-item p-3 border-bottom cursor-pointer" data-conversation-id="1">
            <div class="d-flex align-items-start">
                <!-- Avatar -->
                <div class="symbol symbol-45px me-3">
                    <img src="avatar.jpg" alt="Avatar" class="symbol-label" />
                    <span class="symbol-badge symbol-badge-bottom bg-success"></span>
                </div>
                
                <!-- Conteúdo -->
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div class="fw-bold text-gray-800 text-truncate">
                            João Silva
                        </div>
                        <span class="text-muted fs-7">14:30</span>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted fs-7 text-truncate me-2">
                            Olá, preciso de ajuda com...
                        </div>
                        <span class="badge badge-circle badge-danger">3</span>
                    </div>
                    
                    <!-- Tags -->
                    <div class="d-flex gap-1 mt-2">
                        <span class="badge badge-light-primary badge-sm">VIP</span>
                        <span class="badge badge-light-success badge-sm">WhatsApp</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mais itens... -->
    </div>
</div>
```

**CSS Customizado**:
```css
.conversation-list-panel {
    width: 380px;
    border-right: 1px solid #e4e6ef;
    background-color: #f9f9f9;
}

.conversation-list-header {
    background-color: #fff;
    position: sticky;
    top: 0;
    z-index: 10;
}

.conversation-item {
    background-color: #fff;
    transition: background-color 0.2s;
}

.conversation-item:hover {
    background-color: #f5f8fa;
}

.conversation-item.active {
    background-color: #e8f4f8;
    border-left: 3px solid #009ef7;
}

.conversation-item .symbol-badge {
    width: 12px;
    height: 12px;
    border: 2px solid #fff;
}
```

---

### 3. JANELA DE CHAT (Painel Direito)

**Baseado em**: Metronic Chat Component + Custom Chat Window

```html
<!-- Janela de Chat -->
<div class="chat-window-panel d-flex flex-column">
    <!-- Header do Chat -->
    <div class="chat-header p-3 border-bottom bg-white">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <!-- Avatar -->
                <div class="symbol symbol-40px me-3">
                    <img src="avatar.jpg" alt="Avatar" class="symbol-label" />
                    <span class="symbol-badge symbol-badge-bottom bg-success"></span>
                </div>
                
                <!-- Info Contato -->
                <div>
                    <div class="fw-bold text-gray-800">João Silva</div>
                    <div class="text-muted fs-7">Online • WhatsApp</div>
                </div>
            </div>
            
            <!-- Ações -->
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-icon btn-light">
                    <i class="ki-duotone ki-user fs-5"></i>
                </button>
                <button class="btn btn-sm btn-icon btn-light">
                    <i class="ki-duotone ki-setting fs-5"></i>
                </button>
                <button class="btn btn-sm btn-icon btn-light">
                    <i class="ki-duotone ki-cross fs-5"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Área de Mensagens -->
    <div class="chat-messages flex-grow-1 overflow-auto p-4" id="chat-messages">
        <!-- Mensagem Recebida -->
        <div class="d-flex mb-4">
            <div class="symbol symbol-35px me-3">
                <img src="avatar.jpg" alt="Avatar" class="symbol-label" />
            </div>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center mb-1">
                    <span class="fw-bold text-gray-800 me-2">João Silva</span>
                    <span class="text-muted fs-7">14:25</span>
                </div>
                <div class="message-bubble message-received">
                    Olá, preciso de ajuda com meu pedido #12345
                </div>
            </div>
        </div>
        
        <!-- Mensagem Enviada -->
        <div class="d-flex mb-4 justify-content-end">
            <div class="flex-grow-1 text-end">
                <div class="d-flex align-items-center justify-content-end mb-1">
                    <span class="text-muted fs-7 me-2">14:26</span>
                    <span class="fw-bold text-gray-800">Você</span>
                </div>
                <div class="message-bubble message-sent">
                    Olá João! Como posso ajudá-lo hoje?
                </div>
            </div>
            <div class="symbol symbol-35px ms-3">
                <img src="agent-avatar.jpg" alt="Avatar" class="symbol-label" />
            </div>
        </div>
        
        <!-- Mais mensagens... -->
    </div>
    
    <!-- Input de Mensagem -->
    <div class="chat-input p-3 border-top bg-white">
        <!-- Anexos/Ações -->
        <div class="d-flex align-items-center gap-2 mb-2">
            <button class="btn btn-sm btn-icon btn-light">
                <i class="ki-duotone ki-paper-clip fs-5"></i>
            </button>
            <button class="btn btn-sm btn-icon btn-light">
                <i class="ki-duotone ki-picture fs-5"></i>
            </button>
            <button class="btn btn-sm btn-icon btn-light">
                <i class="ki-duotone ki-smile fs-5"></i>
            </button>
        </div>
        
        <!-- Input -->
        <div class="d-flex align-items-end gap-2">
            <textarea 
                class="form-control form-control-solid" 
                rows="2" 
                placeholder="Digite sua mensagem..."
                id="message-input"
            ></textarea>
            <button class="btn btn-primary" id="send-message">
                <i class="ki-duotone ki-send fs-5"></i>
            </button>
        </div>
    </div>
</div>
```

**CSS Customizado**:
```css
.chat-window-panel {
    flex: 1;
    background-color: #f5f8fa;
}

.chat-header {
    position: sticky;
    top: 0;
    z-index: 10;
}

.chat-messages {
    background-image: url('data:image/svg+xml,...'); /* Padrão de fundo */
    min-height: 400px;
}

.message-bubble {
    display: inline-block;
    padding: 0.75rem 1rem;
    border-radius: 1rem;
    max-width: 70%;
    word-wrap: break-word;
}

.message-received {
    background-color: #fff;
    border-top-left-radius: 0.25rem;
}

.message-sent {
    background-color: #009ef7;
    color: #fff;
    border-top-right-radius: 0.25rem;
}

.chat-input {
    position: sticky;
    bottom: 0;
    z-index: 10;
}

.chat-input textarea {
    resize: none;
}
```

---

## 📱 RESPONSIVIDADE

### Mobile (< 768px):
```css
@media (max-width: 767.98px) {
    .conversation-list-panel {
        width: 100%;
        display: none; /* Escondido por padrão */
    }
    
    .conversation-list-panel.show {
        display: flex;
    }
    
    .chat-window-panel {
        display: none; /* Escondido por padrão */
    }
    
    .chat-window-panel.show {
        display: flex;
    }
}
```

**JavaScript para alternar**:
```javascript
// Alternar entre lista e chat no mobile
function toggleMobileView(view) {
    if (window.innerWidth < 768) {
        if (view === 'list') {
            document.querySelector('.conversation-list-panel').classList.add('show');
            document.querySelector('.chat-window-panel').classList.remove('show');
        } else {
            document.querySelector('.conversation-list-panel').classList.remove('show');
            document.querySelector('.chat-window-panel').classList.add('show');
        }
    }
}
```

---

## 🎨 CORES E ESTILOS (Chatwoot-like)

### Paleta de Cores:
```css
:root {
    /* Cores Principais */
    --chatwoot-primary: #1f93ff;
    --chatwoot-primary-dark: #0066cc;
    --chatwoot-success: #00d97e;
    --chatwoot-danger: #ff4757;
    --chatwoot-warning: #ffa502;
    
    /* Cores de Fundo */
    --chatwoot-bg-primary: #ffffff;
    --chatwoot-bg-secondary: #f9f9f9;
    --chatwoot-bg-tertiary: #f5f8fa;
    
    /* Cores de Texto */
    --chatwoot-text-primary: #1f2937;
    --chatwoot-text-secondary: #6b7280;
    --chatwoot-text-muted: #9ca3af;
    
    /* Bordas */
    --chatwoot-border: #e4e6ef;
    --chatwoot-border-light: #f1f3f5;
}

/* Aplicar no Metronic */
.bs-primary {
    background-color: var(--chatwoot-primary) !important;
}

.text-primary {
    color: var(--chatwoot-primary) !important;
}

.border-primary {
    border-color: var(--chatwoot-primary) !important;
}
```

---

## 🔧 COMPONENTES METRONIC A UTILIZAR

### 1. Layout Base
- `sidebar` - Sidebar component
- `header` - Header component
- `wrapper` - Page wrapper

### 2. Componentes de UI
- `symbol` - Para avatares
- `badge` - Para contadores e tags
- `form-control` - Para inputs
- `btn` - Botões

### 3. Componentes de Chat (se disponível)
- Chat widget
- Message list
- Typing indicator

### 4. Componentes Adicionais
- `dropdown` - Menus dropdown
- `modal` - Modais
- `tooltip` - Tooltips
- `spinner` - Loading

---

## 📦 ESTRUTURA DE ARQUIVOS

```
public/
├── assets/
│   ├── css/
│   │   ├── metronic/          # CSS do Metronic
│   │   └── custom/
│   │       ├── chatwoot-layout.css    # Layout customizado
│   │       ├── chat.css               # Estilos de chat
│   │       └── conversation-list.css  # Lista de conversas
│   │
│   ├── js/
│   │   ├── metronic/          # JS do Metronic
│   │   └── custom/
│   │       ├── chat.js                # Lógica de chat
│   │       ├── conversation-list.js   # Lista de conversas
│   │       └── layout.js               # Layout responsivo
│   │
│   └── plugins/
│       ├── sortablejs/        # Drag & drop (Kanban)
│       └── ...
│
views/
├── layouts/
│   └── metronic/
│       ├── header.php
│       ├── sidebar.php
│       └── chatwoot-layout.php    # Layout principal
│
├── conversations/
│   ├── index.php              # Página principal (3 colunas)
│   └── chat.php               # Componente de chat
│
└── components/
    ├── conversation-item.php  # Item da lista
    └── message-bubble.php     # Mensagem
```

---

## 🚀 IMPLEMENTAÇÃO PASSO A PASSO

### Fase 1: Setup Base
1. ✅ Instalar Metronic (Demo 3)
2. ✅ Configurar estrutura de pastas
3. ✅ Criar layout base (header + sidebar)
4. ✅ Configurar cores e variáveis CSS

### Fase 2: Layout 3 Colunas
1. ✅ Criar estrutura HTML (sidebar + lista + chat)
2. ✅ Aplicar CSS para 3 colunas
3. ✅ Testar responsividade
4. ✅ Ajustar larguras e espaçamentos

### Fase 3: Lista de Conversas
1. ✅ Criar componente de item de conversa
2. ✅ Implementar busca e filtros
3. ✅ Adicionar scroll e paginação
4. ✅ Implementar seleção ativa

### Fase 4: Janela de Chat
1. ✅ Criar header do chat
2. ✅ Área de mensagens
3. ✅ Input de mensagem
4. ✅ Envio de mensagens

### Fase 5: Funcionalidades
1. ✅ WebSocket para tempo real
2. ✅ Notificações
3. ✅ Upload de arquivos
4. ✅ Emojis e formatação

### Fase 6: Polimento
1. ✅ Animações e transições
2. ✅ Loading states
3. ✅ Error handling
4. ✅ Otimizações

---

## 🎯 DIFERENÇAS E ADAPTAÇÕES

### O que manter do Metronic:
- ✅ Sistema de cores e temas
- ✅ Componentes base (botões, inputs, etc)
- ✅ Sidebar component
- ✅ Header component
- ✅ Sistema de ícones
- ✅ Grid system

### O que customizar:
- 🔧 Layout 3 colunas específico
- 🔧 Componentes de chat customizados
- 🔧 Lista de conversas customizada
- 🔧 Cores e branding Chatwoot-like
- 🔧 Espaçamentos e tipografia

---

## 📝 EXEMPLO DE PÁGINA COMPLETA

```php
<?php
// views/conversations/index.php
?>

@extends('layouts.metronic.chatwoot-layout')

@section('content')
<div class="d-flex flex-row flex-column-fluid h-100">
    <!-- Sidebar Esquerda -->
    @include('layouts.metronic.sidebar')
    
    <!-- Conteúdo Principal -->
    <div class="d-flex flex-column flex-row-fluid">
        <!-- Lista de Conversas -->
        <div class="conversation-list-panel d-flex flex-column">
            @include('conversations.partials.list-header')
            @include('conversations.partials.conversation-list')
        </div>
        
        <!-- Janela de Chat -->
        <div class="chat-window-panel d-flex flex-column">
            @include('conversations.partials.chat-header')
            @include('conversations.partials.chat-messages')
            @include('conversations.partials.chat-input')
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/custom/chat.js') }}"></script>
<script src="{{ asset('assets/js/custom/conversation-list.js') }}"></script>
@endsection
```

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

- [ ] Instalar Metronic Demo 3
- [ ] Criar estrutura de pastas
- [ ] Configurar layout base
- [ ] Implementar sidebar customizada
- [ ] Criar lista de conversas
- [ ] Implementar janela de chat
- [ ] Aplicar estilos Chatwoot-like
- [ ] Testar responsividade
- [ ] Integrar WebSocket
- [ ] Adicionar funcionalidades de chat
- [ ] Otimizar performance
- [ ] Testar em diferentes browsers

---

## 🔗 LINKS ÚTEIS

- **Metronic Demo 3**: https://preview.keenthemes.com/metronic8/demo3/index.html
- **Metronic Docs**: https://preview.keenthemes.com/html/metronic/docs/
- **Chatwoot (Referência)**: https://www.chatwoot.com/
- **Bootstrap 5 Docs**: https://getbootstrap.com/docs/5.3/

---

Este guia fornece uma base sólida para criar um layout similar ao Chatwoot 4 usando Metronic como base. A implementação será feita passo a passo conforme o desenvolvimento do projeto.

