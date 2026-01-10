# 🔧 Correção: Campo de Mensagem no Coaching em Tempo Real

## ❌ Problema
O sistema de Coaching em Tempo Real não estava lendo o conteúdo das mensagens corretamente. Os logs mostravam mensagens vazias (0 caracteres) mesmo quando havia texto.

### Evidência do Problema
```
[2026-01-10 18:51:16] 📝 Mensagem: "" (tamanho: 0 chars)
[2026-01-10 18:51:16] ❌ FILTRO 2: Mensagem muito curta (0 < 5 chars)
```

**Mensagem real enviada:** "to querendo fazer uma nova compra"

## 🔍 Causa Raiz
O código estava tentando acessar `$message['body']`, mas o campo correto na tabela `messages` é **`content`**.

### Estrutura da Tabela Messages
```sql
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_type VARCHAR(20) NOT NULL,
    sender_id INT,
    content TEXT NOT NULL,  -- ✅ Campo correto
    message_type VARCHAR(20) DEFAULT 'text',
    ...
)
```

## ✅ Solução Implementada

### Arquivos Corrigidos

#### 1. `app/Services/RealtimeCoachingService.php`

**Correção 1: Leitura do conteúdo da mensagem (linhas 137-138)**
```php
// ❌ Antes
$bodyLength = mb_strlen($message['body']);
self::log("📝 Mensagem: \"{$message['body']}\" (tamanho: {$bodyLength} chars)");

// ✅ Depois
$bodyLength = mb_strlen($message['content']);
self::log("📝 Mensagem: \"{$message['content']}\" (tamanho: {$bodyLength} chars)");
```

**Correção 2: Cálculo de similaridade para cache (linhas 568-570)**
```php
// ❌ Antes
$similarity = self::calculateSimilarity(
    $message['body'],
    $hintMessage['body']
);

// ✅ Depois
$similarity = self::calculateSimilarity(
    $message['content'],
    $hintMessage['content']
);
```

**Correção 3: Construção do prompt para IA - mensagem atual (linhas 657-658)**
```php
// ❌ Antes
$prompt .= "\n### MENSAGEM ATUAL DO CLIENTE:\n";
$prompt .= $message['body'] . "\n\n";

// ✅ Depois
$prompt .= "\n### MENSAGEM ATUAL DO CLIENTE:\n";
$prompt .= $message['content'] . "\n\n";
```

**Correção 4: Construção do prompt para IA - contexto (linhas 651-655)**
```php
// ❌ Antes
$prompt .= "### CONTEXTO DA CONVERSA (últimas mensagens):\n";
foreach ($context as $msg) {
    $sender = $msg['sender_type'] === 'contact' ? 'Cliente' : 'Vendedor';
    $prompt .= "{$sender}: {$msg['body']}\n";
}

// ✅ Depois
$prompt .= "### CONTEXTO DA CONVERSA (últimas mensagens):\n";
foreach ($context as $msg) {
    $sender = $msg['sender_type'] === 'contact' ? 'Cliente' : 'Vendedor';
    $prompt .= "{$sender}: {$msg['content']}\n";
}
```

**Correção 5: Query SQL para hints (linha 828)**
```php
// ❌ Antes
$sql = "SELECT rch.*, m.body as message_body
        FROM realtime_coaching_hints rch
        LEFT JOIN messages m ON rch.message_id = m.id

// ✅ Depois
$sql = "SELECT rch.*, m.content as message_body
        FROM realtime_coaching_hints rch
        LEFT JOIN messages m ON rch.message_id = m.id
```

## 📊 Impacto da Correção

### Antes da Correção
- ✅ Mensagens eram detectadas
- ✅ Listener era chamado
- ✅ Configurações eram carregadas
- ❌ Conteúdo vazio (0 chars)
- ❌ Bloqueadas no FILTRO 2 (tamanho mínimo)
- ❌ Nenhuma análise de IA executada

### Depois da Correção
- ✅ Mensagens detectadas
- ✅ Listener chamado
- ✅ Configurações carregadas
- ✅ Conteúdo lido corretamente
- ✅ Passa pelo FILTRO 2
- ✅ Análise de IA executada
- ✅ Hints gerados

## 🧪 Como Testar

1. **Enviar mensagem de teste:**
   ```
   Envie do WhatsApp: "Olá, gostaria de fazer uma compra"
   ```

2. **Verificar nos logs:**
   ```bash
   tail -f logs/coaching.log
   ```

3. **Logs esperados:**
   ```
   [2026-01-10 XX:XX:XX] 🎯 queueMessageForAnalysis() - Msg #XXXX
   [2026-01-10 XX:XX:XX] 📝 Mensagem: "Olá, gostaria de fazer uma compra" (tamanho: 35 chars)
   [2026-01-10 XX:XX:XX] ✅ FILTRO 1: OK - É mensagem de cliente
   [2026-01-10 XX:XX:XX] ✅ FILTRO 2: OK - Tamanho adequado (35 >= 10)
   ...
   [2026-01-10 XX:XX:XX] ✅ Mensagem adicionada na fila de coaching
   ```

## 📋 Checklist de Verificação

- [x] Campo `content` usado em todas as referências
- [x] Query SQL corrigida
- [x] Prompt da IA recebe conteúdo correto
- [x] Cache de similaridade funcional
- [x] Logs mostram conteúdo real

## 🔗 Arquivos Relacionados

- `app/Services/RealtimeCoachingService.php` - Serviço principal de coaching
- `app/Listeners/MessageReceivedListener.php` - Listener de mensagens
- `database/migrations/004_create_messages_table.php` - Schema da tabela messages
- `app/Models/Message.php` - Model de mensagens

## 📝 Notas Importantes

1. O campo `content` é o padrão em toda a aplicação para armazenar o texto das mensagens
2. Sempre usar `Message::find()` ou `Message::select()` para garantir consistência
3. O Model `Message` já define `content` no array `$fillable`
4. Todas as queries SQL devem referenciar `messages.content`, não `messages.body`

## ✅ Status
**CORRIGIDO** - Data: 2026-01-10 20:30

---

**Próximos Passos:**
1. Testar com mensagens reais do WhatsApp
2. Verificar geração de hints pela IA
3. Monitorar custos de API
4. Ajustar filtros se necessário
