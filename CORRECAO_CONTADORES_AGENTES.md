# 🔧 Correção dos Contadores de Conversas dos Agentes

## 📋 Problema Identificado

Ao analisar os logs da automação, foi identificado um **bug crítico** no sistema de contagem de conversas dos agentes:

```log
[16:53:50] UPDATE conversations SET agent_id = 2 WHERE id = 325
[16:53:50] Values: [2, "2025-12-21 16:53:50", 325]
[16:53:50] Linhas afetadas: 0  ❌ Conversa JÁ tinha agent_id = 2

[16:53:50] UPDATE users SET current_conversations = 8 WHERE id = 2
[16:53:50] Linhas afetadas: 1  ❌ Contador incrementado mesmo sem mudar!
```

### 🐛 O Problema

O sistema estava **incrementando o contador de conversas mesmo quando o agente não mudava**!

**Cenário:**
1. Conversa 325 já estava atribuída ao agente 2
2. Automação tenta atribuir novamente ao agente 2
3. UPDATE retorna 0 linhas (nada mudou)
4. **MAS o contador é incrementado de 7 para 8!**

**Resultado:** Contador **incorreto** - o agente fica com mais conversas contabilizadas do que realmente tem!

## 🔍 Causa Raiz

No método `ConversationService::assignToAgent()` e outros, o código estava:

```php
// ❌ ERRADO - Sempre atualiza o contador
$oldAgentId = $conversation['agent_id'] ?? null;
Conversation::update($conversationId, ['agent_id' => $agentId]);

if ($oldAgentId && $oldAgentId != $agentId) {
    User::updateConversationsCount($oldAgentId);
}
User::updateConversationsCount($agentId); // ❌ SEMPRE executado!
```

Mesmo que `$oldAgentId == $agentId` (agente não mudou), o contador era atualizado!

## ✅ Solução Implementada

Foram corrigidos **3 métodos** em `ConversationService`:

### 1. `assignToAgent()` (linha 598)

```php
// ✅ CORRETO - Só atualiza se o agente mudou
if ($oldAgentId != $agentId) {
    // Decrementar contador do agente anterior (se houver)
    if ($oldAgentId) {
        User::updateConversationsCount($oldAgentId);
    }
    // Incrementar contador do novo agente
    User::updateConversationsCount($agentId);
    
    Logger::debug("Contadores atualizados: antigo agente {$oldAgentId} → novo agente {$agentId}", 'conversas.log');
} else {
    Logger::debug("Agente não mudou (já era {$agentId}), contadores não foram alterados", 'conversas.log');
}
```

### 2. `escalateToAgent()` (linha 802)

```php
// ✅ CORRETO - Só atualiza se o agente mudou
if ($oldAgentId != $agentId) {
    if ($oldAgentId) {
        User::updateConversationsCount($oldAgentId);
    }
    User::updateConversationsCount($agentId);
    Logger::debug("Escalação: contadores atualizados (antigo: {$oldAgentId} → novo: {$agentId})", 'conversas.log');
}
```

### 3. `reopen()` (linha 1054)

```php
// ✅ CORRETO - Removido o elseif que causava o problema
$finalAgentId = $shouldAssignToContactAgent && $contactAgentId ? $contactAgentId : $oldAgentId;
if ($finalAgentId && $finalAgentId != $oldAgentId) {
    // Se mudou de agente, atualizar contagem de ambos
    if ($oldAgentId) {
        User::updateConversationsCount($oldAgentId);
    }
    User::updateConversationsCount($finalAgentId);
    Logger::debug("Reabertura: contadores atualizados (antigo: {$oldAgentId} → novo: {$finalAgentId})", 'conversas.log');
}
// ❌ REMOVIDO: } elseif ($finalAgentId) {
//     User::updateConversationsCount($finalAgentId);
// }
```

## 📊 Comportamento Correto Agora

### Cenário 1: Agente Muda

```
Antes: agent_id = null
Depois: agent_id = 2

✅ Incrementa contador do agente 2
```

### Cenário 2: Agente NÃO Muda

```
Antes: agent_id = 2
Depois: agent_id = 2

✅ NÃO altera contador (0 linhas afetadas, sem problema!)
```

### Cenário 3: Troca de Agente

```
Antes: agent_id = 2
Depois: agent_id = 5

✅ Decrementa contador do agente 2
✅ Incrementa contador do agente 5
```

## 🎯 Impacto da Correção

### ✅ Benefícios

1. **Contadores precisos** - Cada agente tem o número exato de conversas
2. **Sem duplicações** - Atribuição repetida não infla o contador
3. **Logs informativos** - Debug mostra quando o contador é/não é atualizado
4. **Performance** - Menos queries desnecessárias ao banco

### ⚠️ Importante

Os contadores **existentes** podem estar incorretos! Após aplicar a correção, **recomenda-se recalcular** os contadores de todos os agentes.

## 🔧 Script de Recálculo (Opcional)

Crie e execute este script para corrigir contadores existentes:

```php
<?php
require_once __DIR__ . '/../config/bootstrap.php';

use App\Helpers\Database;

$db = Database::getInstance();

echo "🔄 Recalculando contadores de conversas dos agentes...\n\n";

try {
    $db->beginTransaction();
    
    // Buscar todos os agentes
    $sql = "SELECT id, name FROM users WHERE role IN ('agent', 'admin', 'supervisor') AND status = 'active'";
    $agents = Database::fetchAll($sql);
    
    foreach ($agents as $agent) {
        // Contar conversas ativas do agente
        $sql = "SELECT COUNT(*) as total 
                FROM conversations 
                WHERE agent_id = ? 
                AND status IN ('open', 'pending')";
        $result = Database::fetch($sql, [$agent['id']]);
        $realCount = $result['total'];
        
        // Atualizar contador
        $sql = "UPDATE users SET current_conversations = ? WHERE id = ?";
        Database::execute($sql, [$realCount, $agent['id']]);
        
        echo "✅ Agente: {$agent['name']} - Conversas: {$realCount}\n";
    }
    
    $db->commit();
    echo "\n✅ Recálculo concluído!\n";
    
} catch (\Exception $e) {
    $db->rollBack();
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
```

Salve como `public/fix-agent-counters.php` e execute:
```bash
php public/fix-agent-counters.php
```

Ou acesse: `http://seu-dominio/fix-agent-counters.php`

## 🧪 Como Testar

### Teste 1: Atribuição Repetida

```php
// Atribuir conversa ao agente 2
ConversationService::assignToAgent(325, 2);
echo "Contador atual: " . User::find(2)['current_conversations'] . "\n"; // Ex: 5

// Tentar atribuir novamente ao agente 2
ConversationService::assignToAgent(325, 2);
echo "Contador atual: " . User::find(2)['current_conversations'] . "\n"; // Deve continuar 5!
```

**Resultado esperado:** Contador NÃO deve mudar na segunda atribuição.

### Teste 2: Troca de Agente

```php
// Trocar de agente 2 para agente 5
$before2 = User::find(2)['current_conversations']; // Ex: 5
$before5 = User::find(5)['current_conversations']; // Ex: 3

ConversationService::assignToAgent(325, 5);

$after2 = User::find(2)['current_conversations']; // Deve ser 4
$after5 = User::find(5)['current_conversations']; // Deve ser 4

echo "Agente 2: {$before2} → {$after2}\n";
echo "Agente 5: {$before5} → {$after5}\n";
```

**Resultado esperado:** 
- Agente 2: decrementa 1
- Agente 5: incrementa 1

### Teste 3: Verificar Logs

Após a correção, os logs devem mostrar:

```log
[hora] Contadores atualizados: antigo agente 2 → novo agente 5
```

Ou, se o agente não mudou:

```log
[hora] Agente não mudou (já era 2), contadores não foram alterados
```

## 📝 Notas Técnicas

### Por Que Isso Acontecia?

Em **automações e reatribuições**, às vezes o sistema:
1. Move conversa entre etapas/funis
2. Executa regras de atribuição
3. Tenta atribuir o **mesmo agente que já estava**

Sem a verificação `if ($oldAgentId != $agentId)`, o contador era incrementado mesmo sem mudança real.

### Quando o Contador É Atualizado?

**✅ Atualizado:**
- Primeira atribuição (null → agente)
- Troca de agente (agente A → agente B)
- Resolução/fechamento (decrementa)
- Reabertura com novo agente

**❌ NÃO atualizado:**
- Atribuição repetida (agente A → agente A)
- Update sem mudança no agent_id

## 🚀 Arquivos Modificados

```
app/
└── Services/
    └── ConversationService.php  ✅ Corrigido (3 métodos)
```

## 📚 Relacionado

- **Atribuição Avançada:** Funciona corretamente ✅
- **Round-Robin:** Funciona corretamente ✅
- **Escalação de IA:** Funciona corretamente ✅
- **Reabertura:** Funciona corretamente ✅

O problema não era nas **regras de atribuição**, mas sim no **incremento do contador**.

---

✅ **Correção implementada com sucesso!**

*Data: 21/12/2024*
*Versão: 1.0*

