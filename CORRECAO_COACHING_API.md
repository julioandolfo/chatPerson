# 🔧 Correção: API de Coaching e Hints Inline

## ❌ Problemas Encontrados

### 1. Erro na API
```
SyntaxError: Unexpected token '<', "<br />" is not valid JSON
```
**Causa:** Controller estava usando métodos `where()->orderBy()->get()` que não existem no Model base.

### 2. Hints Não Apareciam
**Causa:**
- `data-conversation-id` não estava no elemento correto
- Faltava polling (só WebSocket não é suficiente)
- JavaScript não detectava mudanças de conversa

---

## ✅ Correções Aplicadas

### 1️⃣ Controller - Usar SQL Direto
**Arquivo:** `app/Controllers/RealtimeCoachingController.php`

```php
// ❌ Antes (não funciona)
$hints = RealtimeCoachingHint::where('conversation_id', '=', $conversationId)
    ->where('agent_id', '=', $userId)
    ->orderBy('created_at', 'DESC')
    ->get();

// ✅ Depois (funciona)
$sql = "SELECT * FROM realtime_coaching_hints 
        WHERE conversation_id = :conversation_id 
        AND agent_id = :agent_id 
        ORDER BY created_at DESC";

$hints = \App\Helpers\Database::fetchAll($sql, [
    'conversation_id' => $conversationId,
    'agent_id' => $userId
]);
```

### 2️⃣ HTML - Adicionar `data-conversation-id`
**Arquivo:** `views/conversations/index.php`

**Linha 2777:**
```php
<!-- ❌ Antes -->
<div class="chat-messages" id="chatMessages">

<!-- ✅ Depois -->
<div class="chat-messages" 
     id="chatMessages" 
     data-conversation-id="<?= $selectedConversation['id'] ?? '' ?>">
```

**Linha 7268:** (função selectConversation)
```javascript
// ✅ Adicionar ao trocar de conversa
chatMessages.setAttribute('data-conversation-id', id);
```

### 3️⃣ JavaScript - Melhorar Detecção
**Arquivo:** `public/assets/js/coaching-inline.js`

**Mudanças:**
1. Detectar conversa no `#chatMessages` (ao invés de procurar qualquer elemento)
2. Adicionar polling a cada 10 segundos
3. Logs mais detalhados

```javascript
// ❌ Antes
const conversationIdElement = document.querySelector('[data-conversation-id]');

// ✅ Depois
const chatMessages = document.getElementById('chatMessages');
const newConversationId = chatMessages.dataset.conversationId;

// ✅ Novo: Polling
startPolling() {
    setInterval(() => {
        if (this.conversationId) {
            console.log('[CoachingInline] Polling - buscando novos hints...');
            this.loadHints();
        }
    }, 10000); // 10 segundos
}
```

---

## 🧪 Como Testar

### 1️⃣ Fazer Pull
```bash
cd /var/www/html && git pull
```

### 2️⃣ Abrir Console (F12)
```javascript
// Verificar se detectou a conversa
window.coachingInline.conversationId // deve retornar o ID

// Verificar hints
console.log(window.coachingInline.hints);

// Forçar busca manual
window.coachingInline.loadHints();
```

### 3️⃣ Testar API Manualmente
```javascript
// Buscar hints da conversa 658
fetch('/api/coaching/hints/conversation/658')
  .then(r => r.json())
  .then(data => console.log(data));
```

**Resposta esperada:**
```json
{
  "success": true,
  "hints": [
    {
      "id": 1,
      "message_id": 6790,
      "hint_type": "buying_signal",
      "hint_text": "Cliente demonstrou sinal de compra",
      "suggestions": "[\"Sugestão 1\",\"Sugestão 2\"]",
      "model_used": "gpt-3.5-turbo",
      "cost": 0.0009,
      "viewed_at": null,
      "feedback": null,
      "created_at": "2026-01-10 22:03:02"
    }
  ],
  "hints_by_message": {
    "6790": [{ ...hint acima... }]
  }
}
```

### 4️⃣ Verificar Mensagem com Hint
- Abrir conversa #658
- Procurar mensagem ID 6790 ou 6791
- **Deve ter um card roxo abaixo** ⚡

---

## 📊 Fluxo Completo

### Detecção de Conversa
```
Página carrega
    ↓
coaching-inline.js detecta #chatMessages
    ↓
Lê data-conversation-id="658"
    ↓
Chama loadHints()
    ↓
GET /api/coaching/hints/conversation/658
    ↓
API retorna JSON com hints agrupados
    ↓
Renderiza hint abaixo de cada mensagem
```

### Polling (a cada 10s)
```
setInterval (10s)
    ↓
Verifica se há conversationId
    ↓
Chama loadHints()
    ↓
Busca novos hints
    ↓
Renderiza se houver novos
```

### Mudança de Conversa
```
Usuário clica em conversa
    ↓
selectConversation(id) é chamado
    ↓
chatMessages.setAttribute('data-conversation-id', id)
    ↓
JavaScript detecta mudança (1s depois)
    ↓
Limpa hints antigos
    ↓
Busca hints da nova conversa
```

---

## 🐛 Debug - Logs Esperados

### Console do Navegador
```
[CoachingInline] Inicializado
[CoachingInline] Nova conversa detectada: 658
[CoachingInline] Hints carregados: 2 mensagens com hints
[CoachingInline] Polling - buscando novos hints...
```

### Se API Falhar
```
[CoachingInline] Erro ao carregar hints: SyntaxError...
```
→ Abrir Network tab (F12) e ver resposta da API
→ Deve ser JSON, não HTML

### Se Não Renderizar
```javascript
// Verificar se hints foram carregados
console.log(window.coachingInline.hints);
// Deve ter: { "6790": [...], "6791": [...] }

// Verificar se mensagem existe
document.querySelector('[data-message-id="6790"]');
// Deve retornar o elemento da mensagem
```

---

## ✅ Checklist Final

- [ ] API retorna JSON (não HTML)
- [ ] `#chatMessages` tem `data-conversation-id`
- [ ] JavaScript detecta conversa (ver log)
- [ ] Polling acontece a cada 10s (ver log)
- [ ] Hints aparecem abaixo das mensagens
- [ ] Hints persistem após F5
- [ ] Hints aparecem ao mudar de conversa e voltar

---

## 🚀 Próximos Passos

1. **Teste em produção** (Coolify)
2. **Envie mensagem de teste** via WhatsApp
3. **Aguarde 1 minuto** (cron processar)
4. **Abra conversa** e veja o hint
5. **F5** e veja que continua lá

---

**Se ainda não funcionar, me envie:**
1. Logs do console (F12)
2. Resposta da API (Network tab)
3. Conteúdo de `window.coachingInline.conversationId`
4. Screenshot da página

**Agora sim deve funcionar! 🚀**
