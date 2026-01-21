# 🔍 Guia Completo de Debug de Conversas

**Data**: 2026-01-20  
**Objetivo**: Investigar comportamentos estranhos em conversas específicas

---

## 🎯 **Quando Usar Este Guia**

Use estas ferramentas quando encontrar:
- ✅ Reatribuições estranhas (ex: "de Gustavo para Gustavo")
- ✅ Conversa atribuída para agente errado
- ✅ Atribuições múltiplas em curto período
- ✅ Participantes assumindo atribuição indevidamente
- ✅ Qualquer comportamento inesperado de atribuição

---

## 🛠️ **Ferramentas Disponíveis**

### 1️⃣ **Script PHP Completo** (Recomendado)
📄 Arquivo: `debug-conversation.php`

**Vantagens:**
- ✅ Análise automática de problemas
- ✅ Timeline completo (mensagens + atribuições)
- ✅ Detecção de padrões suspeitos
- ✅ Recomendações de correção
- ✅ Saída formatada e colorida

**Como usar:**
```bash
php debug-conversation.php [ID_DA_CONVERSA]
```

**Exemplo:**
```bash
php debug-conversation.php 123
```

### 2️⃣ **Queries SQL Diretas**
📄 Arquivo: `debug-conversation-simple.sql`

**Vantagens:**
- ✅ Pode executar diretamente no MySQL
- ✅ Mais rápido para verificações pontuais
- ✅ Pode copiar/colar queries específicas

**Como usar:**
1. Abrir o arquivo `debug-conversation-simple.sql`
2. Substituir `[CONVERSATION_ID]` pelo ID real
3. Executar no seu cliente MySQL (phpMyAdmin, DBeaver, etc)

---

## 📋 **Passo a Passo de Debug**

### **Cenário: Reatribuições de "Gustavo para Gustavo"**

#### **Passo 1: Executar Script PHP**

```bash
php debug-conversation.php 123
```

O script vai mostrar:

```
╔══════════════════════════════════════════════════════════════════════════╗
║            🔍 DEBUG DE CONVERSA - ID: 123                                ║
╚══════════════════════════════════════════════════════════════════════════╝

📋 INFORMAÇÕES BÁSICAS
────────────────────────────────────────────────────────────────────────────
ID: 123
Status: open
Canal: whatsapp
Agente Atual: #5
Criada em: 2026-01-20 14:24:00
Atualizada em: 2026-01-20 16:57:00

👤 CONTATO
────────────────────────────────────────────────────────────────────────────
ID: 45
Nome: João Silva
Telefone: 5542988099929
Email: joao@example.com

👥 AGENTES DO CONTATO
────────────────────────────────────────────────────────────────────────────
  • Agente #5 - Gustavo ⭐ PRINCIPAL 🔄 Auto-atribuir
    Prioridade: 0
    Criado em: 2026-01-20 14:24:00

📊 HISTÓRICO DE ATRIBUIÇÕES
────────────────────────────────────────────────────────────────────────────
1. 2026-01-20 14:24:00 - ✅ ATIVO
   Agente: #5 - Gustavo
   Atribuído por: Sistema (#0)
   Método: auto

2. 2026-01-20 14:25:00 - ❌ REMOVIDO
   Agente: #7 - Gabriel Freitas
   Atribuído por: Gustavo (#5)
   Método: manual
   ❌ Removido em: 2026-01-20 14:25:00

3. 2026-01-20 14:28:00 - ✅ ATIVO
   Agente: #5 - Gustavo
   Atribuído por: Gustavo (#5)
   Método: auto
   ⚠️  AUTO-ATRIBUIÇÃO DETECTADA: Agente atribuiu para si mesmo!
   🔴 BUG: Reatribuição para o MESMO agente (#5 → #5)

[... mais atribuições ...]

💬 MENSAGENS E EVENTOS (Timeline)
────────────────────────────────────────────────────────────────────────────
1. 2026-01-20 14:24:00 👤 MENSAGEM
   De: contact
   Conteúdo: "Olá, preciso de ajuda..."

2. 2026-01-20 14:24:00 ✅ ATRIBUIÇÃO
   Agente: #5 (Gustavo)
   Por: Sistema (#0)
   Método: auto

3. 2026-01-20 14:25:00 🧑‍💼 MENSAGEM
   De: agent #7 (Gabriel Freitas)
   Conteúdo: "Olá! Como posso ajudar?..."

4. 2026-01-20 14:28:00 🧑‍💼 MENSAGEM
   De: agent #5 (Gustavo)
   Conteúdo: "Vou assumir essa conversa..."
   ⚠️  POSSÍVEL AUTO-ATRIBUIÇÃO: Agente mudou de #7 para #5

5. 2026-01-20 14:28:00 ✅ ATRIBUIÇÃO
   Agente: #5 (Gustavo)
   Por: Gustavo (#5)
   Método: auto
   🔴 BUG: Reatribuição para o MESMO agente (#5 → #5)

[... continua ...]

🔍 ANÁLISE DE PROBLEMAS
────────────────────────────────────────────────────────────────────────────
🔴 REATRIBUIÇÕES DESNECESSÁRIAS: 8 atribuições para o mesmo agente
🔴 AUTO-ATRIBUIÇÃO POR MENSAGEM: 8 atribuições logo após envio de mensagem

💡 RECOMENDAÇÕES
────────────────────────────────────────────────────────────────────────────
1. Bug de auto-atribuição (assigned_to vs agent_id) - JÁ CORRIGIDO
   Este problema deve ter parado após a correção aplicada hoje.
```

#### **Passo 2: Identificar o Padrão**

No exemplo acima, podemos ver claramente:
- ✅ Conversa atribuída inicialmente para Gustavo (#5)
- ✅ Gustavo transfere para Gabriel (#7)
- ❌ **TODA VEZ** que Gustavo envia mensagem, conversa volta para ele
- ❌ Isso acontece porque o código estava verificando `assigned_to` (campo inexistente) em vez de `agent_id`

#### **Passo 3: Verificar se Bug Foi Corrigido**

Execute o debug **ANTES e DEPOIS** da correção:

```bash
# Testar conversa ANTIGA (antes da correção)
php debug-conversation.php 123

# Criar NOVA conversa (depois da correção)
# e testar se problema persiste
php debug-conversation.php [NOVA_CONVERSA_ID]
```

---

## 🔍 **Queries SQL Úteis**

### **1. Encontrar conversas com reatribuições suspeitas**

```sql
-- Conversas com múltiplas atribuições para o mesmo agente
SELECT 
    ca1.conversation_id,
    ca1.agent_id,
    u.name,
    COUNT(*) as total_reatribuicoes
FROM conversation_assignments ca1
INNER JOIN conversation_assignments ca2 
    ON ca1.conversation_id = ca2.conversation_id 
    AND ca2.assigned_at > ca1.assigned_at
    AND ca1.agent_id = ca2.agent_id
LEFT JOIN users u ON ca1.agent_id = u.id
WHERE ca1.assigned_at >= '2026-01-20 00:00:00'
GROUP BY ca1.conversation_id, ca1.agent_id
HAVING COUNT(*) > 3
ORDER BY total_reatribuicoes DESC;
```

### **2. Verificar auto-atribuições hoje**

```sql
-- Auto-atribuições (agente atribuiu para si mesmo)
SELECT 
    ca.conversation_id,
    ca.assigned_at,
    ca.agent_id,
    u.name,
    ca.assignment_method
FROM conversation_assignments ca
LEFT JOIN users u ON ca.agent_id = u.id
WHERE ca.agent_id = ca.assigned_by
  AND ca.assigned_at >= CURDATE()
  AND ca.assignment_method = 'auto'
ORDER BY ca.assigned_at DESC;
```

### **3. Timeline de uma conversa específica**

```sql
SET @conversation_id = 123; -- ← ALTERAR ID AQUI

SELECT * FROM (
    SELECT 
        m.created_at as quando,
        'MENSAGEM' as tipo,
        CONCAT(m.sender_type, ' #', m.sender_id) as detalhes
    FROM messages m
    WHERE m.conversation_id = @conversation_id
    
    UNION ALL
    
    SELECT 
        ca.assigned_at as quando,
        'ATRIBUIÇÃO' as tipo,
        CONCAT('Agente #', ca.agent_id, ' por #', ca.assigned_by, ' [', ca.assignment_method, ']') as detalhes
    FROM conversation_assignments ca
    WHERE ca.conversation_id = @conversation_id
) as timeline
ORDER BY quando ASC;
```

---

## 🐛 **Problemas Comuns e Como Identificar**

### **Problema 1: Reatribuição de "X para X"**

**Sintoma:**
```
Conversa atribuída de Gustavo para Gustavo
Conversa atribuída de Gustavo para Gustavo
Conversa atribuída de Gustavo para Gustavo
```

**Causa:**
- Bug no código: verificando `assigned_to` em vez de `agent_id`
- Resultado: `$isUnassigned` sempre `TRUE`
- Conversa reatribuída toda vez que agente envia mensagem

**Como identificar no debug:**
```
🔴 BUG: Reatribuição para o MESMO agente (#5 → #5)
⚠️  AUTO-ATRIBUIÇÃO DETECTADA: Agente atribuiu para si mesmo!
```

**Status:** ✅ **CORRIGIDO** em `app/Controllers/ConversationController.php` (linha 1190)

---

### **Problema 2: Participante Assume Atribuição**

**Sintoma:**
- Conversa atribuída ao Agente A
- Agente B é participante
- Agente B envia mensagem
- Conversa é reatribuída para Agente B

**Causa:**
- Mesmo bug acima (assigned_to vs agent_id)

**Como identificar no debug:**
```
3. 2026-01-20 14:28:00 🧑‍💼 MENSAGEM
   De: agent #7 (Participante)
4. 2026-01-20 14:28:00 ✅ ATRIBUIÇÃO
   Agente: #7 (Participante)
   ⚠️  POSSÍVEL AUTO-ATRIBUIÇÃO
```

**Status:** ✅ **CORRIGIDO** (mesma correção)

---

### **Problema 3: Agente do Contato Ignorado**

**Sintoma:**
- Cliente tem "Agente Principal" definido
- Cliente reabre conversa
- Sistema ignora agente principal e usa automação

**Como identificar no debug:**
```
👥 AGENTES DO CONTATO
  • Agente #5 - Gustavo ⭐ PRINCIPAL 🔄 Auto-atribuir

📊 HISTÓRICO DE ATRIBUIÇÕES
1. Atribuído para Agente #8 (ERRADO!)
   Método: automation
```

**Como verificar:**
```sql
-- Ver se contato tem agente principal
SELECT * FROM contact_agents WHERE contact_id = [ID_CONTATO];

-- Ver se conversa foi atribuída ao agente principal
SELECT * FROM conversation_assignments 
WHERE conversation_id = [ID_CONVERSA] 
  AND agent_id = [ID_AGENTE_PRINCIPAL];
```

**Status:** ✅ Sistema já prioriza agente do contato (verificado)

---

## 📊 **Análise do Seu Caso Específico**

Baseado no timeline que você mostrou:

```
Conversa criada: 20/01/2026, 14:24
Atribuída de Gustavo para Gabriel: 14:24
Atribuída de Gabriel para Gustavo: 14:25
Atribuída de Gustavo para Gustavo: 14:28 ← BUG AQUI
Atribuída de Gustavo para Gustavo: 14:31 ← BUG
Atribuída de Gustavo para Gustavo: 14:34 ← BUG
... (mais 6 vezes)
```

**Diagnóstico:**
🔴 **Bug de auto-atribuição confirmado**

**Causa:**
- Código verificava `$conversation['assigned_to']` (não existe)
- Sempre retornava `null`
- Sistema achava que conversa não estava atribuída
- Toda vez que Gustavo enviava mensagem, reatribuía para ele

**Correção aplicada:**
✅ Mudado de `assigned_to` para `agent_id` (linha 1190)

**Próximos passos:**
1. Executar debug na conversa: `php debug-conversation.php [ID]`
2. Verificar se problema parou após correção
3. Monitorar novas conversas para confirmar

---

## 🧪 **Como Testar se Correção Funcionou**

### Teste 1: Conversa Nova

```bash
1. Criar nova conversa
2. Atribuir para Agente A
3. Agente A envia mensagem
4. ✅ Verificar: Conversa deve CONTINUAR atribuída ao Agente A
5. ✅ NÃO deve aparecer reatribuição "de A para A"
```

### Teste 2: Participante

```bash
1. Criar conversa atribuída ao Agente A
2. Adicionar Agente B como participante
3. Agente B envia mensagem
4. ✅ Verificar: Conversa deve CONTINUAR atribuída ao Agente A
5. ✅ B deve permanecer apenas participante
```

### Teste 3: Timeline

```bash
php debug-conversation.php [ID_NOVA_CONVERSA]

# Deve mostrar:
✅ Nenhum problema óbvio detectado
```

---

## 📝 **Logs Importantes**

O sistema gera logs em:
- `storage/logs/conversas.log` - Logs de atribuição e fluxo
- `storage/logs/automations.log` - Logs de automações
- `storage/logs/quepasa.log` - Logs do WhatsApp

Para verificar logs de uma conversa específica:

```bash
grep "Conversa #123" storage/logs/conversas.log
grep "AUTO-ASSIGN.*#123" storage/logs/conversas.log
```

---

## 🎯 **Checklist de Debug**

Ao investigar um problema:

- [ ] Executar `php debug-conversation.php [ID]`
- [ ] Verificar seção "ANÁLISE DE PROBLEMAS"
- [ ] Verificar "HISTÓRICO DE ATRIBUIÇÕES"
- [ ] Verificar "TIMELINE" (mensagens + atribuições)
- [ ] Verificar "AGENTES DO CONTATO"
- [ ] Verificar se há reatribuições para o mesmo agente
- [ ] Verificar se há auto-atribuições após mensagens
- [ ] Verificar logs do sistema
- [ ] Testar com conversa nova após correção

---

## 🔧 **Manutenção**

Para adicionar mais análises ao script de debug, edite:
- `debug-conversation.php` - Seção "ANÁLISE DE PROBLEMAS" (linha ~300)
- `debug-conversation-simple.sql` - Adicionar novas queries

---

**Última atualização**: 2026-01-20 18:00
