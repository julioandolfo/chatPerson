# 🔗 Exemplo de Integração dos Hooks

## 📍 Onde Adicionar os Hooks

Para que o sistema de métricas pré-computadas funcione perfeitamente, você precisa adicionar os hooks nos locais onde mensagens são criadas e conversas são atualizadas.

---

## 1️⃣ Hook: Após Criar Mensagem

### Exemplo 1: WhatsAppService (Mensagem Recebida)

**Arquivo**: `app/Services/WhatsAppService.php`

Procure por onde a mensagem é salva e adicione o hook logo após:

```php
// ANTES (exemplo)
public function processIncomingMessage($data) {
    // ... código existente ...
    
    // Salvar mensagem
    $messageId = Message::create([
        'conversation_id' => $conversationId,
        'sender_type' => 'contact',
        'content' => $content,
        // ...
    ]);
    
    // ... resto do código ...
}

// DEPOIS (com hook)
public function processIncomingMessage($data) {
    // ... código existente ...
    
    // Salvar mensagem
    $messageId = Message::create([
        'conversation_id' => $conversationId,
        'sender_type' => 'contact',
        'content' => $content,
        // ...
    ]);
    
    // ✅ ADICIONAR HOOK AQUI
    \App\Hooks\MessageHooks::afterCreate($messageId, [
        'conversation_id' => $conversationId,
        'sender_type' => 'contact'
    ]);
    
    // ... resto do código ...
}
```

### Exemplo 2: ConversationService (Mensagem Enviada)

**Arquivo**: `app/Services/ConversationService.php`

```php
// ANTES
public function sendMessage($conversationId, $content, $agentId) {
    // ... código existente ...
    
    $messageId = Message::create([
        'conversation_id' => $conversationId,
        'sender_type' => 'agent',
        'sender_id' => $agentId,
        'content' => $content,
        // ...
    ]);
    
    return $messageId;
}

// DEPOIS (com hook)
public function sendMessage($conversationId, $content, $agentId) {
    // ... código existente ...
    
    $messageId = Message::create([
        'conversation_id' => $conversationId,
        'sender_type' => 'agent',
        'sender_id' => $agentId,
        'content' => $content,
        // ...
    ]);
    
    // ✅ ADICIONAR HOOK AQUI
    \App\Hooks\MessageHooks::afterCreate($messageId, [
        'conversation_id' => $conversationId,
        'sender_type' => 'agent'
    ]);
    
    return $messageId;
}
```

---

## 2️⃣ Hook: Após Atualizar Conversa (Fechar/Reabrir)

### Exemplo 1: Fechar Conversa

**Arquivo**: `app/Services/ConversationService.php` ou `app/Controllers/ConversationController.php`

```php
// ANTES
public function closeConversation($conversationId) {
    // Buscar dados antigos
    $oldConversation = Conversation::find($conversationId);
    
    // Atualizar status
    Conversation::update($conversationId, [
        'status' => 'closed',
        'closed_at' => date('Y-m-d H:i:s')
    ]);
    
    return true;
}

// DEPOIS (com hook)
public function closeConversation($conversationId) {
    // Buscar dados antigos
    $oldConversation = Conversation::find($conversationId);
    
    // Atualizar status
    Conversation::update($conversationId, [
        'status' => 'closed',
        'closed_at' => date('Y-m-d H:i:s')
    ]);
    
    // ✅ ADICIONAR HOOK AQUI
    \App\Hooks\MessageHooks::afterConversationUpdate(
        $conversationId,
        $oldConversation, // dados antigos
        ['status' => 'closed'] // dados novos
    );
    
    return true;
}
```

### Exemplo 2: Reabrir Conversa

```php
// ANTES
public function reopenConversation($conversationId) {
    $oldConversation = Conversation::find($conversationId);
    
    Conversation::update($conversationId, [
        'status' => 'open'
    ]);
    
    return true;
}

// DEPOIS (com hook)
public function reopenConversation($conversationId) {
    $oldConversation = Conversation::find($conversationId);
    
    Conversation::update($conversationId, [
        'status' => 'open'
    ]);
    
    // ✅ ADICIONAR HOOK AQUI
    \App\Hooks\MessageHooks::afterConversationUpdate(
        $conversationId,
        $oldConversation,
        ['status' => 'open']
    );
    
    return true;
}
```

---

## 3️⃣ Locais Comuns Onde Adicionar

### 📍 Procure por estes padrões no código:

```php
// Padrão 1: Criação de mensagem
Message::create([...]);
// ✅ Adicionar hook aqui

// Padrão 2: INSERT de mensagem
Database::execute("INSERT INTO messages ...");
// ✅ Adicionar hook aqui

// Padrão 3: Atualização de conversa
Conversation::update($id, ['status' => 'closed']);
// ✅ Adicionar hook aqui

// Padrão 4: UPDATE de conversa
Database::execute("UPDATE conversations SET status = 'closed' WHERE id = ?");
// ✅ Adicionar hook aqui
```

### 📁 Arquivos Prováveis:

Baseado na busca, estes arquivos provavelmente criam mensagens:

1. ✅ `app/Services/WhatsAppService.php`
2. ✅ `app/Services/ConversationService.php`
3. ✅ `app/Services/AutomationService.php`
4. ✅ `app/Services/InstagramGraphService.php`
5. ✅ `app/Services/WhatsAppCloudService.php`
6. ✅ `app/Services/OpenAIService.php`
7. ✅ `app/Services/ScheduledMessageService.php`

---

## 🔍 Como Encontrar os Locais Exatos

### Opção 1: Buscar no Código

```bash
# Procurar por criação de mensagens
grep -rn "Message::create" app/
grep -rn "INSERT INTO messages" app/

# Procurar por atualização de conversas
grep -rn "Conversation::update" app/
grep -rn "UPDATE conversations" app/
grep -rn "status.*closed" app/
```

### Opção 2: Usar IDE (VS Code / Cursor)

1. Pressione `Ctrl+Shift+F` (buscar em todos os arquivos)
2. Digite: `Message::create`
3. Adicione hook em cada resultado
4. Repita para: `Conversation::update`

---

## 📝 Template Pronto para Copiar

### Para Mensagens:

```php
// Após criar mensagem, adicione:
\App\Hooks\MessageHooks::afterCreate($messageId, [
    'conversation_id' => $conversationId,
    'sender_type' => $senderType // 'contact' ou 'agent'
]);
```

### Para Conversas (Fechar):

```php
// Após fechar conversa, adicione:
\App\Hooks\MessageHooks::afterConversationUpdate(
    $conversationId,
    $oldData, // dados antes da atualização
    ['status' => 'closed']
);
```

### Para Conversas (Reabrir):

```php
// Após reabrir conversa, adicione:
\App\Hooks\MessageHooks::afterConversationUpdate(
    $conversationId,
    $oldData,
    ['status' => 'open']
);
```

---

## ⚠️ Importante: Tratamento de Erros

Os hooks já têm tratamento de erros interno. Se der erro, não vai quebrar o fluxo principal:

```php
// ✅ Seguro - não vai quebrar se der erro
\App\Hooks\MessageHooks::afterCreate($messageId, $data);

// ❌ NÃO precisa fazer try/catch
try {
    \App\Hooks\MessageHooks::afterCreate($messageId, $data);
} catch (\Exception $e) {
    // Não precisa
}
```

---

## 🧪 Como Testar

### Teste 1: Mensagem Nova

1. Envie uma mensagem via WhatsApp
2. Verifique o log:
```bash
tail -f storage/logs/error.log | grep "MessageHooks"
```
3. Verifique o banco:
```sql
SELECT * FROM contact_metrics WHERE needs_recalculation = 1;
```

### Teste 2: Fechar Conversa

1. Feche uma conversa no sistema
2. Verifique:
```sql
SELECT * FROM contact_metrics WHERE contact_id = ? AND needs_recalculation = 1;
```

### Teste 3: CRON Recalcula

1. Execute o CRON:
```bash
php cron/calculate-contact-metrics.php
```
2. Verifique:
```sql
SELECT * FROM contact_metrics WHERE contact_id = ? AND needs_recalculation = 0;
```

---

## 🎯 Checklist de Integração

```
☐ 1. Adicionar hook em WhatsAppService::processIncomingMessage()
☐ 2. Adicionar hook em ConversationService::sendMessage()
☐ 3. Adicionar hook em AutomationService (se criar mensagens)
☐ 4. Adicionar hook em InstagramGraphService (se criar mensagens)
☐ 5. Adicionar hook em WhatsAppCloudService (se criar mensagens)
☐ 6. Adicionar hook em OpenAIService (se criar mensagens)
☐ 7. Adicionar hook em ScheduledMessageService
☐ 8. Adicionar hook ao fechar conversa
☐ 9. Adicionar hook ao reabrir conversa
☐ 10. Testar: enviar mensagem e verificar banco
☐ 11. Testar: fechar conversa e verificar banco
☐ 12. Testar: rodar CRON e verificar recálculo
```

---

## 💡 Dica: Adicionar Gradualmente

Você não precisa adicionar todos os hooks de uma vez. Comece com os principais:

### Fase 1 (Essencial):
1. WhatsAppService (mensagens recebidas)
2. ConversationService (mensagens enviadas)
3. Fechar conversa

### Fase 2 (Complementar):
4. Outros serviços de mensagens
5. Reabrir conversa
6. Automações

### Fase 3 (Otimização):
7. Ajustar prioridades
8. Monitorar logs
9. Otimizar frequência do CRON

---

**Data**: 2026-01-12  
**Versão**: 1.0  
**Status**: ✅ Guia de Integração Completo

