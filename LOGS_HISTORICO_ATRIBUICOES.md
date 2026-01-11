# Logs Detalhados - Sistema de Histórico de Atribuições

## 📋 Resumo

Adicionados logs detalhados em todo o sistema de histórico de atribuições de conversas para facilitar o debug em produção.

## 🔍 Locais com Logs Adicionados

### 1. **ConversationService::create()** (`app/Services/ConversationService.php`)

**Quando:** Ao criar uma nova conversa e registrar atribuição inicial

**Logs:**
```
[INFO] ConversationService::create - Tentando registrar histórico de atribuição: conversation_id=X, agent_id=Y
[INFO] ConversationService::create - Histórico de atribuição registrado com sucesso
[ERROR] ConversationService::create - ERRO ao registrar histórico de atribuição: [mensagem]
[ERROR] ConversationService::create - Stack trace: [trace]
[DEBUG] ConversationService::create - Nenhum agente para registrar no histórico (agentId=null)
```

### 2. **ConversationService::assignToAgent()** (`app/Services/ConversationService.php`)

**Quando:** Ao atribuir/reatribuir uma conversa a um agente

**Logs:**
```
[INFO] ConversationService::assignToAgent - Tentando registrar histórico: conversation_id=X, agent_id=Y, assigned_by=Z
[INFO] ConversationService::assignToAgent - Histórico registrado com sucesso
[ERROR] ConversationService::assignToAgent - ERRO ao registrar histórico: [mensagem]
[ERROR] ConversationService::assignToAgent - Stack trace: [trace]
[INFO] ConversationService::assignToAgent - Marcando remoção do agente anterior: old_agent_id=X
[INFO] ConversationService::assignToAgent - Remoção marcada com sucesso
[ERROR] ConversationService::assignToAgent - ERRO ao marcar remoção: [mensagem]
```

### 3. **ConversationAssignment::recordAssignment()** (`app/Models/ConversationAssignment.php`)

**Quando:** Ao registrar uma atribuição no histórico

**Logs:**
```
[INFO] ConversationAssignment::recordAssignment - INÍCIO: conversation_id=X, agent_id=Y, assigned_by=Z
[INFO] ConversationAssignment::tableExists - Tabela EXISTE / NÃO EXISTE
[WARNING] ConversationAssignment::recordAssignment - Tabela não existe, pulando registro
[INFO] ConversationAssignment::recordAssignment - Agente vazio, pulando registro
[INFO] ConversationAssignment::recordAssignment - Dados preparados: {json}
[INFO] ConversationAssignment::recordAssignment - Registro criado com ID: X
[ERROR] ConversationAssignment::recordAssignment - EXCEÇÃO CAPTURADA: [mensagem]
[ERROR] ConversationAssignment::recordAssignment - Stack trace: [trace]
```

### 4. **ConversationAssignment::recordRemoval()** (`app/Models/ConversationAssignment.php`)

**Quando:** Ao marcar uma atribuição como removida

**Logs:**
```
[INFO] ConversationAssignment::recordRemoval - INÍCIO: conversation_id=X, agent_id=Y
[WARNING] ConversationAssignment::recordRemoval - Tabela não existe, pulando remoção
[INFO] ConversationAssignment::recordRemoval - Resultado: sucesso / falha
[ERROR] ConversationAssignment::recordRemoval - ERRO: [mensagem]
[ERROR] ConversationAssignment::recordRemoval - Stack trace: [trace]
```

## 🛡️ Proteções Implementadas

### 1. **Verificação de Existência da Tabela**
- Método `tableExists()` com cache estático
- Verifica uma única vez se a tabela `conversation_assignments` existe
- Se não existir, pula o registro sem quebrar o fluxo

### 2. **Try-Catch Abrangente**
- Todos os métodos críticos têm try-catch
- Erros são logados mas NÃO quebram o fluxo principal
- Sistema continua funcionando mesmo se histórico falhar

### 3. **Validações**
- Verifica se `agentId` não é null antes de registrar
- Retorna 0 ou false em caso de erro (não lança exceção)

## 📂 Onde Ver os Logs

Os logs são gravados em:
- **`logs/app.log`** - Logs gerais do sistema (Logger::info, Logger::error, etc)
- **`logs/quepasa.log`** - Logs específicos do WhatsApp (se houver)

## 🔧 Como Usar para Debug

### 1. Verificar se a tabela existe:
```bash
tail -f logs/app.log | grep "tableExists"
```

### 2. Acompanhar registro de atribuições:
```bash
tail -f logs/app.log | grep "recordAssignment"
```

### 3. Ver erros específicos:
```bash
tail -f logs/app.log | grep "ERROR.*ConversationAssignment"
```

### 4. Monitorar criação de conversas:
```bash
tail -f logs/app.log | grep "ConversationService::create"
```

## ⚠️ Possíveis Problemas e Soluções

### Problema 1: Tabela não existe
**Log:** `Tabela não existe, pulando registro`
**Solução:** Executar migration:
```bash
php database/migrate.php
```

### Problema 2: Erro de permissão no banco
**Log:** `EXCEÇÃO CAPTURADA: Access denied`
**Solução:** Verificar credenciais em `config/database.php`

### Problema 3: Erro de conexão
**Log:** `Nenhuma conexão pôde ser feita`
**Solução:** Verificar se MySQL está rodando

### Problema 4: Campo removed_at não existe
**Log:** `Unknown column 'removed_at'`
**Solução:** Executar migration ou adicionar campo:
```sql
ALTER TABLE conversation_assignments ADD COLUMN removed_at DATETIME NULL;
```

## 📊 Impacto no Sistema

✅ **Sem impacto negativo:**
- Sistema continua funcionando mesmo se histórico falhar
- Logs não afetam performance (escritas assíncronas)
- Verificação de tabela é cacheada (uma vez por requisição)

✅ **Benefícios:**
- Debug facilitado em produção
- Identificação rápida de problemas
- Rastreamento completo do fluxo de atribuições

## 🚀 Próximos Passos

1. Monitorar logs em produção após deploy
2. Verificar se tabela `conversation_assignments` existe
3. Se não existir, executar migration
4. Acompanhar logs por 24h para identificar possíveis problemas
5. Ajustar nível de logs se necessário (reduzir INFO para apenas ERROR)

## 📝 Notas Importantes

- **Todos os logs são em português** para facilitar leitura
- **Stack traces completos** são incluídos em erros
- **Dados sensíveis** (senhas, tokens) NÃO são logados
- **Performance:** Logs são escritos de forma não-bloqueante
