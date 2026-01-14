# ✅ CORREÇÃO: Coaching Só Roda Se Habilitado

**Data**: 2026-01-13  
**Problema**: Coaching estava iniciando polling mesmo quando desabilitado  
**Solução**: Verificar se coaching está habilitado antes de iniciar qualquer funcionalidade

---

## 🔧 MUDANÇAS APLICADAS

### 1️⃣ Nova Rota API

**Arquivo**: `routes/web.php`

**Adicionado**:
```php
Router::get('/api/coaching/settings', [RealtimeCoachingController::class, 'getSettings'], ['Authentication']);
```

**Endpoint**: `GET /api/coaching/settings`  
**Retorna**:
```json
{
  "success": true,
  "settings": {
    "coaching_enabled": "1",
    "auto_show_hint": true,
    "hint_display_duration": 30,
    "play_sound": false,
    "enabled": true
  }
}
```

---

### 2️⃣ Novo Método no Controller

**Arquivo**: `app/Controllers/RealtimeCoachingController.php`

**Adicionado**:
```php
public function getSettings(): void
{
    $settings = \App\Services\RealtimeCoachingService::getSettings();
    
    Response::json([
        'success' => true,
        'settings' => $settings
    ]);
}
```

---

### 3️⃣ Verificação no JavaScript

**Arquivo**: `public/assets/js/realtime-coaching.js`

#### 3.1 - Modificado `loadSettings()` - Verificar se está habilitado

**ANTES**:
```javascript
async loadSettings() {
    try {
        // Em produção, carregar do backend
        // Por enquanto, usar padrões
        this.settings = {
            enabled: true, // Será controlado pelas configs do sistema
            auto_show_hint: true,
            hint_display_duration: 30,
            play_sound: false
        };
    } catch (error) {
        console.error('[Coaching] Erro ao carregar configurações:', error);
    }
}
```

**DEPOIS**:
```javascript
async loadSettings() {
    try {
        // ✅ Verificar se coaching está habilitado no servidor
        const response = await fetch('/api/coaching/settings');
        const data = await response.json();
        
        if (data.success && data.settings) {
            this.settings = {
                enabled: data.settings.coaching_enabled === '1' || data.settings.coaching_enabled === true,
                auto_show_hint: data.settings.auto_show_hint !== false,
                hint_display_duration: parseInt(data.settings.hint_display_duration) || 30,
                play_sound: data.settings.play_sound === true
            };
            
            console.log('[Coaching] Configurações carregadas:', this.settings);
        } else {
            // ✅ Se não conseguir carregar, assumir desabilitado por segurança
            console.warn('[Coaching] Não foi possível carregar configurações - desabilitando coaching');
            this.settings.enabled = false;
        }
    } catch (error) {
        console.error('[Coaching] Erro ao carregar configurações:', error);
        // ✅ Em caso de erro, desabilitar por segurança
        this.settings.enabled = false;
    }
}
```

---

#### 3.2 - Modificado `init()` - Não iniciar se desabilitado

**ANTES**:
```javascript
init() {
    console.log('[Coaching] Inicializando sistema de coaching em tempo real');
    
    // Carregar configurações
    this.loadSettings();
    
    // Configurar WebSocket listener
    this.setupWebSocketListener();
    
    // Iniciar polling (fallback)
    this.startPolling();
    
    // Listener para mudança de conversa
    document.addEventListener('conversationChanged', (e) => {
        this.onConversationChanged(e.detail.conversationId);
    });
}
```

**DEPOIS**:
```javascript
async init() {
    console.log('[Coaching] Inicializando sistema de coaching em tempo real');
    
    // ✅ Carregar configurações e verificar se está habilitado
    await this.loadSettings();
    
    // ✅ Se desabilitado, não iniciar nada
    if (!this.settings.enabled) {
        console.log('[Coaching] ❌ Coaching desabilitado - não iniciando polling nem listeners');
        return;
    }
    
    console.log('[Coaching] ✅ Coaching habilitado - iniciando sistema');
    
    // Configurar WebSocket listener
    this.setupWebSocketListener();
    
    // Iniciar polling (fallback)
    this.startPolling();
    
    // Listener para mudança de conversa
    document.addEventListener('conversationChanged', (e) => {
        this.onConversationChanged(e.detail.conversationId);
    });
}
```

---

#### 3.3 - Modificado `pollPendingHints()` - Verificar e parar se desabilitado

**ANTES**:
```javascript
async pollPendingHints() {
    if (!this.currentConversationId) {
        return;
    }
    
    try {
        const response = await fetch(`/coaching/pending-hints?conversation_id=${this.currentConversationId}&seconds=10`);
        const data = await response.json();
        
        if (data.success && data.hints && data.hints.length > 0) {
            console.log('[Coaching] Polling encontrou ' + data.hints.length + ' hint(s)');
            
            // Processar cada hint
            data.hints.forEach(hint => {
                if (!this.displayedHints.has(hint.id)) {
                    this.handleNewHint(hint);
                }
            });
        }
    } catch (error) {
        console.error('[Coaching] Erro no polling:', error);
    }
}
```

**DEPOIS**:
```javascript
async pollPendingHints() {
    // ✅ Verificar se coaching está habilitado
    if (!this.settings.enabled) {
        console.log('[Coaching] Coaching desabilitado - parando polling');
        this.stopPolling();
        return;
    }
    
    if (!this.currentConversationId) {
        return;
    }
    
    try {
        const response = await fetch(`/coaching/pending-hints?conversation_id=${this.currentConversationId}&seconds=10`);
        const data = await response.json();
        
        // ✅ Verificar se foi desabilitado no servidor
        if (data.enabled === false) {
            console.log('[Coaching] Coaching foi desabilitado no servidor - parando polling');
            this.settings.enabled = false;
            this.stopPolling();
            return;
        }
        
        if (data.success && data.hints && data.hints.length > 0) {
            console.log('[Coaching] Polling encontrou ' + data.hints.length + ' hint(s)');
            
            // Processar cada hint
            data.hints.forEach(hint => {
                if (!this.displayedHints.has(hint.id)) {
                    this.handleNewHint(hint);
                }
            });
        }
    } catch (error) {
        console.error('[Coaching] Erro no polling:', error);
    }
}
```

---

#### 3.4 - Adicionado `stopPolling()` - Parar polling

**NOVO MÉTODO**:
```javascript
/**
 * Parar polling
 */
stopPolling() {
    if (this.pollingInterval) {
        clearInterval(this.pollingInterval);
        this.pollingInterval = null;
        console.log('[Coaching] Polling parado');
    }
}
```

---

## 🎯 IMPACTO

### Antes (Coaching Desabilitado)

```
Polling de coaching: A cada 60s (mesmo desabilitado) ❌
Queries/hora: 60
Waste: 100% (queries desnecessárias)
```

### Depois (Coaching Desabilitado)

```
Polling de coaching: NÃO INICIA ✅
Queries/hora: 0
Economia: 60 queries/hora por usuário ⚡
```

---

## 📊 ECONOMIA ESTIMADA

**Se 10 agentes com coaching desabilitado**:
- **Antes**: 600 queries/hora
- **Depois**: 0 queries/hora
- **Economia**: 100% ⚡⚡⚡

**Se 50 agentes com coaching desabilitado**:
- **Antes**: 3.000 queries/hora
- **Depois**: 0 queries/hora
- **Economia**: 100% ⚡⚡⚡

---

## 🧪 COMO TESTAR

### 1️⃣ Verificar se API funciona

```bash
curl -X GET "https://seu-dominio.com/api/coaching/settings" \
  -H "Cookie: session=..."
```

**Resposta esperada**:
```json
{
  "success": true,
  "settings": {
    "coaching_enabled": "0",
    "enabled": false
  }
}
```

---

### 2️⃣ Verificar Console do Navegador

**Com Coaching DESABILITADO**:
```
[Coaching] Inicializando sistema de coaching em tempo real
[Coaching] Configurações carregadas: {enabled: false, ...}
[Coaching] ❌ Coaching desabilitado - não iniciando polling nem listeners
```

**Com Coaching HABILITADO**:
```
[Coaching] Inicializando sistema de coaching em tempo real
[Coaching] Configurações carregadas: {enabled: true, ...}
[Coaching] ✅ Coaching habilitado - iniciando sistema
[Coaching] WebSocket listener configurado
[Coaching] Polling iniciado (a cada 60s)
```

---

### 3️⃣ Verificar QPS

```bash
docker exec -it SEU_CONTAINER sh

# Medir QPS antes
mysql -uchatperson -p chat_person
SHOW GLOBAL STATUS LIKE 'Questions';
# Aguardar 10s
SHOW GLOBAL STATUS LIKE 'Questions';
# Calcular: (valor2 - valor1) / 10

# Desabilitar coaching nas configurações
# Recarregar página (Ctrl + Shift + R)

# Medir QPS depois
SHOW GLOBAL STATUS LIKE 'Questions';
# Aguardar 10s
SHOW GLOBAL STATUS LIKE 'Questions';
# Calcular: (valor2 - valor1) / 10
```

---

## 📁 ARQUIVOS MODIFICADOS

1. ✅ `routes/web.php` - Nova rota `/api/coaching/settings`
2. ✅ `app/Controllers/RealtimeCoachingController.php` - Método `getSettings()`
3. ✅ `public/assets/js/realtime-coaching.js` - Verificações de habilitado

---

## ✅ RESULTADO FINAL

**Coaching RESPEITA as configurações do sistema!**

- ✅ Se desabilitado → Não inicia polling nem listeners
- ✅ Se habilitado → Funciona normalmente
- ✅ Se desabilitado durante execução → Para polling automaticamente
- ✅ Economia de recursos quando não utilizado
- ✅ Melhor performance do sistema

---

**🎉 Correção Aplicada com Sucesso!** ⚡
