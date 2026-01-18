# 🔧 Solução: Registros Duplicados no Histórico de Atribuições

## 📋 Problema Identificado

No modal "VER DETALHES" dos cards do kanban, o histórico de atribuições mostrava múltiplos registros do **mesmo agente** se auto-atribuindo várias vezes em poucos segundos:

```
┌──────────────────────────────────────────────────┐
│ Histórico de Atribuições                         │
├─────────────┬─────────────┬─────────────────────┤
│ Luan Melo   │ Luan Melo   │ 16/01/2026, 16:56   │
│ Luan Melo   │ Luan Melo   │ 16/01/2026, 12:57   │
│ Luan Melo   │ Luan Melo   │ 16/01/2026, 12:56   │ ❌ Duplicatas
│ Luan Melo   │ Luan Melo   │ 16/01/2026, 12:56   │ ❌ em segundos
│ Luan Melo   │ Luan Melo   │ 16/01/2026, 12:27   │
│ Luan Melo   │ Luan Melo   │ 16/01/2026, 12:26   │
└─────────────┴─────────────┴─────────────────────┘
```

## 🔍 Causa Raiz

O método `ConversationService::assignToAgent()` estava **sempre registrando** uma nova atribuição no histórico, mesmo quando o agente já era o mesmo:

```php
// ❌ CÓDIGO PROBLEMÁTICO (antes)
$oldAgentId = $conversation['agent_id'] ?? null;
Conversation::update($conversationId, ['agent_id' => $agentId]);

// SEMPRE registra, mesmo se oldAgentId == agentId
\App\Models\ConversationAssignment::recordAssignment(
    $conversationId,
    $agentId,
    $currentUserId
);
```

### Quando isso acontecia:

1. **Cliques múltiplos**: Usuário clica várias vezes no botão "Atribuir"
2. **Auto-salvamento**: Sistema salvando conversas periodicamente
3. **Webhooks duplicados**: WhatsApp/Notifica.me enviando webhook múltiplas vezes
4. **Drag & Drop**: Movimentação rápida de cards no kanban
5. **Auto-atribuição**: Agente enviando mensagens rapidamente

## ✅ Soluções Aplicadas

### 1. Verificação de Mudança Real (ConversationService.php)

Adicionada verificação para **só registrar se o agente mudou de fato**:

```php
// ✅ CÓDIGO CORRIGIDO (depois)
$oldAgentId = $conversation['agent_id'] ?? null;
$agentChanged = ($oldAgentId != $agentId);

Conversation::update($conversationId, ['agent_id' => $agentId]);

// Só registra se houve mudança
if ($agentChanged) {
    Logger::info("Agente mudou de {$oldAgentId} para {$agentId}, registrando histórico");
    
    \App\Models\ConversationAssignment::recordAssignment(
        $conversationId,
        $agentId,
        $currentUserId
    );
} else {
    Logger::info("Agente não mudou ({$agentId}), não registrando no histórico");
}
```

### 2. Proteção Contra Duplicatas em Sequência (ConversationAssignment.php)

Adicionada verificação para **evitar registros duplicados em menos de 10 segundos**:

```php
// ✅ PROTEÇÃO ADICIONAL
$recentAssignment = Database::fetch(
    "SELECT id, assigned_at FROM conversation_assignments 
     WHERE conversation_id = ? 
     AND agent_id = ? 
     AND removed_at IS NULL
     AND assigned_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
     ORDER BY assigned_at DESC 
     LIMIT 1",
    [$conversationId, $agentId]
);

if ($recentAssignment) {
    Logger::warning("Registro duplicado detectado (menos de 10s), pulando");
    return (int)$recentAssignment['id'];
}

// Continua apenas se não houver registro recente
```

### 3. Script de Limpeza de Duplicatas Existentes

Criado script SQL para **remover duplicatas já existentes**:

**Arquivo:** `LIMPAR_DUPLICATAS_ATRIBUICOES.sql`

**O que faz:**
1. ✅ Cria backup automático
2. ✅ Identifica duplicatas (mesmo agente em menos de 60 segundos)
3. ✅ Remove registros duplicados (mantém apenas o primeiro)
4. ✅ Gera relatórios antes/depois
5. ✅ Verifica se a limpeza foi bem-sucedida

## 🚀 Como Aplicar as Correções

### Passo 1: Código já está atualizado ✅

Os arquivos foram modificados:
- ✅ `app/Services/ConversationService.php` (linha 620-650)
- ✅ `app/Models/ConversationAssignment.php` (linha 42-83)

### Passo 2: Limpar duplicatas existentes no banco

Execute o script SQL para limpar os registros antigos:

```bash
# Opção 1 - Terminal
mysql -u root -p nome_do_banco < LIMPAR_DUPLICATAS_ATRIBUICOES.sql

# Opção 2 - phpMyAdmin
# 1. Abra phpMyAdmin
# 2. Selecione o banco de dados
# 3. Vá em "SQL"
# 4. Cole o conteúdo do arquivo LIMPAR_DUPLICATAS_ATRIBUICOES.sql
# 5. Execute
```

### Passo 3: Verificar resultado

Após executar o script, verifique:

1. **Console do MySQL/phpMyAdmin:**
   - Deve mostrar quantos registros foram deletados
   - Deve mostrar estatísticas antes/depois

2. **No sistema:**
   - Abra o Kanban
   - Clique em "VER DETALHES" em qualquer card
   - Verifique o "Histórico de Atribuições"
   - ✅ Não deve mais haver duplicatas consecutivas

## 🎯 Resultado Esperado

### Antes ❌

```
Luan Melo → Luan Melo → 16/01/26, 16:56
Luan Melo → Luan Melo → 16/01/26, 12:57 ← 3h59 depois (OK)
Luan Melo → Luan Melo → 16/01/26, 12:56 ← 1min depois (DUPLICATA)
Luan Melo → Luan Melo → 16/01/26, 12:56 ← 0s depois (DUPLICATA)
Luan Melo → Luan Melo → 16/01/26, 12:27 ← 29min depois (OK)
```

### Depois ✅

```
João Silva  → Admin Master → 16/01/26, 16:56  (Mudou de João para Admin)
João Silva  → Sistema/Auto. → 15/01/26, 10:30  (Primeira atribuição)
```

**Agora só registra quando:**
- ✅ É a primeira atribuição da conversa
- ✅ O agente mudou de fato (de João para Maria)
- ✅ Passou mais de 10 segundos desde a última atribuição

**Não registra quando:**
- ❌ Clique duplo no botão
- ❌ Webhook duplicado
- ❌ Já está atribuído ao mesmo agente

## 📊 Logs Adicionados

O sistema agora loga:

```
[INFO] ConversationService::assignToAgent - Agente mudou de 5 para 7, registrando histórico
[INFO] ConversationAssignment::recordAssignment - Registro criado com ID: 123

OU

[INFO] ConversationService::assignToAgent - Agente não mudou (5), não registrando no histórico

OU

[WARNING] ConversationAssignment::recordAssignment - Registro duplicado detectado (menos de 10s), pulando
```

## 📁 Arquivos Criados/Modificados

### ✅ Modificados
1. **app/Services/ConversationService.php**
   - Adicionada verificação `$agentChanged`
   - Só registra histórico se agente mudou

2. **app/Models/ConversationAssignment.php**
   - Adicionada proteção contra duplicatas em 10s
   - Verifica registros recentes antes de criar novo

### 📄 Criados
1. **LIMPAR_DUPLICATAS_ATRIBUICOES.sql** ⭐
   - Script de limpeza com backup automático
   - Remove duplicatas existentes no banco

2. **SOLUCAO_DUPLICATAS_HISTORICO.md** (este arquivo)
   - Documentação completa do problema
   - Guia de correção e verificação

## ✅ Checklist de Verificação

Após aplicar todas as correções:

- [ ] Código atualizado (já feito automaticamente)
- [ ] Script SQL executado (`LIMPAR_DUPLICATAS_ATRIBUICOES.sql`)
- [ ] Backup criado (`conversation_assignments_backup_duplicatas`)
- [ ] Registros duplicados removidos (verificar no SQL)
- [ ] Cache do navegador limpo (Ctrl+Shift+Del)
- [ ] Testado modal "VER DETALHES" no kanban
- [ ] Histórico não mostra mais duplicatas
- [ ] Logs verificados (`/var/log/php/error.log` ou console do navegador)
- [ ] Testar atribuir conversa (não deve criar duplicata)
- [ ] Testar movimentar card no kanban (não deve duplicar)

## 🧪 Como Testar

### Teste 1: Clique Duplo
1. Abra uma conversa no Kanban
2. Clique rapidamente 5x no botão "Atribuir" (se houver)
3. Verifique o histórico
4. ✅ Deve ter apenas 1 registro

### Teste 2: Drag & Drop
1. Arraste um card para outra coluna
2. Arraste o mesmo card de volta
3. Verifique o histórico
4. ✅ Deve ter 2 registros (mudança de estágio, não duplicata de agente)

### Teste 3: Auto-Atribuição
1. Envie várias mensagens rapidamente como agente
2. Verifique o histórico
3. ✅ Deve ter apenas 1 registro da auto-atribuição

## 🗑️ Limpeza (Após Confirmar)

Quando tudo estiver funcionando:

```sql
-- Deletar backup após confirmar que está tudo OK
DROP TABLE IF EXISTS conversation_assignments_backup_duplicatas;
```

## 📞 Troubleshooting

### Se ainda aparecer duplicatas:

1. **Verifique se o código foi atualizado:**
   ```bash
   grep -n "agentChanged" app/Services/ConversationService.php
   # Deve retornar a linha com a verificação
   ```

2. **Verifique se o script SQL foi executado:**
   ```sql
   SELECT COUNT(*) FROM conversation_assignments_backup_duplicatas;
   # Se retornar erro, o script não foi executado
   ```

3. **Verifique os logs:**
   ```bash
   tail -f /var/log/php/error.log | grep "ConversationAssignment"
   ```

4. **Limpe o cache de aplicação:**
   ```bash
   # Se usar Laragon
   php artisan cache:clear
   
   # Ou limpe manualmente arquivos de cache
   ```

---

**Status:** ✅ Correções aplicadas  
**Data:** 18/01/2026  
**Versão:** 1.0  
**Próxima ação:** Executar `LIMPAR_DUPLICATAS_ATRIBUICOES.sql`  
**Impacto:** Alto - resolve problema crítico de histórico poluído
