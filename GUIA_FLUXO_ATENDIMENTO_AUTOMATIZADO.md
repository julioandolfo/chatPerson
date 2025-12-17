# 🤖 GUIA: FLUXO DE ATENDIMENTO AUTOMATIZADO

**Data**: 2025-01-17  
**Cenário**: Cliente → Chatbot/IA → Triagem → Direcionamento → Atribuição

---

## 📋 ÍNDICE

1. [Reconciliação: Auto-atribuição Etapa vs Configurações Gerais](#1-reconciliação-auto-atribuição)
2. [Implementação do Fluxo Completo](#2-implementação-do-fluxo-completo)
3. [Exemplos Práticos](#3-exemplos-práticos)
4. [Melhores Práticas](#4-melhores-práticas)

---

## 1. RECONCILIAÇÃO: AUTO-ATRIBUIÇÃO

### 🎯 Como Funciona Atualmente

Existem **2 níveis** de configuração de auto-atribuição:

#### Nível 1: **Configurações de Etapa** (específico)
- Configurado em cada etapa do Kanban
- Campos:
  - `auto_assign` (sim/não)
  - `auto_assign_department_id` (setor específico ou qualquer)
  - `auto_assign_method` (round-robin, by-load, by-specialty, by-performance)
- **Escopo**: Apenas quando conversa **entra nesta etapa**
- **Localização**: `/funnels/{id}/kanban` → Editar Etapa → Aba "Auto-atribuição"

#### Nível 2: **Configurações Gerais** (global)
- Configurado em Configurações do Sistema
- Campos:
  - `distribution.method` (método padrão global)
  - `distribution.enable_auto_assignment` (habilitar globalmente)
  - `distribution.assign_to_ai_agent` (considerar IA)
  - `distribution.consider_availability` (considerar status online/offline)
  - Distribuição percentual por agente/setor
  - SLA, reatribuição, priorização, etc
- **Escopo**: Todas as conversas do sistema
- **Localização**: `/settings` → Aba "Conversas Avançadas" (se implementada)

### 🔄 Como Reconciliar

**Prioridade de aplicação** (do mais específico ao mais geral):

```
1. Auto-atribuição da ETAPA (se configurada e habilitada)
   ↓ (se não configurada ou desabilitada)
2. Configurações GERAIS (fallback)
   ↓ (se não configurada)
3. Atribuição MANUAL
```

### ✅ Implementação Recomendada

```php
// Em FunnelService::handleStageAutoAssignment()

// 1. Verificar se etapa tem auto-atribuição habilitada
if (!empty($stage['auto_assign']) && $stage['auto_assign']) {
    // USAR CONFIGURAÇÕES DA ETAPA
    $departmentId = $stage['auto_assign_department_id'] ?? null;
    $method = $stage['auto_assign_method'] ?? 'round-robin';
    
    $agentId = self::assignAgentForStage(
        $conversationId, 
        $departmentId, 
        $stage['funnel_id'], 
        $stage['id'], 
        $method
    );
    
} else {
    // FALLBACK: USAR CONFIGURAÇÕES GERAIS
    $settings = ConversationSettingsService::getSettings();
    
    if ($settings['distribution']['enable_auto_assignment']) {
        $method = $settings['distribution']['method'];
        $departmentId = null; // Ou extrair de outras regras
        
        $agentId = ConversationSettingsService::assignAgent(
            $conversationId,
            $method,
            $departmentId
        );
    }
}
```

### 📊 Tabela Comparativa

| Aspecto | Etapa (Específico) | Geral (Global) |
|---|---|---|
| **Escopo** | Apenas conversa nesta etapa | Todas as conversas |
| **Prioridade** | 🔴 Alta (aplicada primeiro) | 🟡 Baixa (fallback) |
| **Configuração** | Por etapa no Kanban | Em Configurações |
| **Flexibilidade** | Alta (customizar por etapa) | Média (padrão do sistema) |
| **Quando Usar** | Etapas com necessidades específicas | Padrão para todo sistema |

### 💡 Recomendação de Uso

**Use Auto-atribuição de ETAPA quando:**
- Etapa precisa de setor específico (ex: "Aprovação Financeira" → Setor Financeiro)
- Etapa precisa de método diferente (ex: "Urgente" → by-performance)
- Etapa tem regras específicas de negócio

**Use Configurações GERAIS quando:**
- Definir comportamento padrão do sistema
- Configurar limites globais
- Definir SLA padrão
- Configurar reatribuição automática

**Melhor Prática:**
```
✅ Configurações Gerais: Definir padrões (round-robin, SLA 15min, etc)
✅ Etapas Específicas: Sobrescrever apenas quando necessário
✅ Manter maioria das etapas SEM auto-atribuição (usa geral)
✅ Usar auto-atribuição de etapa apenas para casos especiais
```

---

## 2. IMPLEMENTAÇÃO DO FLUXO COMPLETO

### 🎯 Fluxo Desejado

```
1️⃣ Cliente chama no Canal X (WhatsApp)
   ↓
2️⃣ Chatbot faz triagem
   "Olá! Como posso ajudar?"
   1 - Comercial
   2 - Pós-Venda
   ↓
3️⃣ Cliente responde (ex: "1")
   ↓
4️⃣ Sistema move para Funil/Etapa específicos
   - Resposta "1" → Funil Comercial, Etapa "Novo Lead"
   - Resposta "2" → Funil Pós-Venda, Etapa "Suporte"
   ↓
5️⃣ Auto-atribuição da etapa entra em ação
   - Atribui ao setor/agente conforme configuração da etapa
```

---

## 🛠️ COMO IMPLEMENTAR

### Solução: **USAR SISTEMA DE AUTOMAÇÕES** ✅

O sistema de automações que acabamos de completar é **PERFEITO** para este fluxo!

### 📐 Estrutura da Automação

```
┌─────────────┐
│   TRIGGER   │  → Gatilho: "Nova Conversa" (Canal = WhatsApp)
└──────┬──────┘
       │
       ↓
┌─────────────┐
│  CHATBOT    │  → Tipo: Menu
│             │     Mensagem: "Olá {{contact.name}}! Como posso ajudar?"
│             │     Opções: 
│             │     1 - Comercial
│             │     2 - Pós-Venda
└──────┬──────┘
       │
       ↓
┌─────────────┐
│  CONDITION  │  → Campo: "Última Mensagem do Contato"
│  (Opção 1)  │     Operador: "contains"
│             │     Valor: "1" ou "comercial"
└──────┬──────┘
       │ TRUE
       ↓
┌─────────────┐
│ MOVE STAGE  │  → Funil: "Vendas"
│             │     Estágio: "Novo Lead"
└──────┬──────┘
       │
       ↓
┌─────────────┐
│   (FIM)     │  → Auto-atribuição da etapa entra em ação
└─────────────┘
```

### 📝 Passo-a-Passo para Criar

#### **Passo 1: Configurar Etapas com Auto-atribuição**

Antes de criar a automação, configure as etapas de destino:

1. Acesse `/funnels/{id}/kanban`
2. Para a etapa "Novo Lead" (Funil Comercial):
   - ✅ Habilitar "Auto-atribuir conversas ao entrar no estágio"
   - ✅ Departamento: **Comercial**
   - ✅ Método: **Round-Robin** (ou por carga)
   
3. Para a etapa "Suporte" (Funil Pós-Venda):
   - ✅ Habilitar "Auto-atribuir conversas ao entrar no estágio"
   - ✅ Departamento: **Suporte**
   - ✅ Método: **Por Carga**

#### **Passo 2: Criar Automação de Triagem**

1. Acesse `/automations`
2. Clique em "Nova Automação"
3. Nome: **"Triagem WhatsApp - Comercial/Pós-Venda"**
4. Gatilho: **"Nova Conversa"**
5. Funil/Estágio: Deixe vazio (aplica a todas) OU vincule ao estágio inicial
6. Clique em "Criar e Editar"

#### **Passo 3: Montar o Fluxo no Editor Visual**

**Nó 1 - Trigger (já existe)**
- Tipo: Nova Conversa
- Canal: WhatsApp

**Adicionar Nó 2 - Chatbot**
1. Arraste "Chatbot" do painel lateral para o canvas
2. Conecte: Trigger → Chatbot
3. Configure:
   - Tipo: **Menu com Opções**
   - Mensagem: `Olá {{contact.name}}! Como posso ajudar você hoje?`
   - Opções:
     - `1 - Falar com Comercial`
     - `2 - Suporte Pós-Venda`
     - `3 - Falar com Atendente`
   - Timeout: 300 segundos
   - Ação ao Timeout: Atribuir a um Agente

**Adicionar Nó 3 - Condição (Comercial)**
1. Arraste "Condição" para o canvas
2. Conecte: Chatbot → Condição
3. Configure:
   - Campo: **Última Mensagem** (ou campo customizado)
   - Operador: **contains**
   - Valor: `1` (ou use múltiplas condições para "1", "comercial", "vendas")

**Adicionar Nó 4 - Mover para Comercial**
1. Arraste "Mover para Estágio" para o canvas
2. Conecte: Condição (TRUE) → Mover
3. Configure:
   - Funil: **Vendas/Comercial**
   - Estágio: **Novo Lead**
   - Validar Regras: ✅ Sim

**Adicionar Nó 5 - Condição (Pós-Venda)**
1. Arraste "Condição" para o canvas
2. Conecte: Chatbot → Condição
3. Configure:
   - Campo: **Última Mensagem**
   - Operador: **contains**
   - Valor: `2`

**Adicionar Nó 6 - Mover para Pós-Venda**
1. Arraste "Mover para Estágio" para o canvas
2. Conecte: Condição (TRUE) → Mover
3. Configure:
   - Funil: **Pós-Venda**
   - Estágio: **Suporte**
   - Validar Regras: ✅ Sim

**Adicionar Nó 7 - Condição (Atendente)**
1. Arraste "Condição" para o canvas
2. Conecte: Chatbot → Condição
3. Configure:
   - Campo: **Última Mensagem**
   - Operador: **contains**
   - Valor: `3`

**Adicionar Nó 8 - Atribuir Agente**
1. Arraste "Atribuir Agente" para o canvas
2. Conecte: Condição (TRUE) → Atribuir
3. Configure:
   - Agente: **Selecione um agente específico** ou deixe para auto-atribuição
   - Notificar: ✅ Sim

#### **Passo 4: Salvar e Ativar**

1. Clique em "Salvar Layout"
2. Volte para `/automations`
3. Certifique-se que está como **"Ativa"**

---

## 3. EXEMPLOS PRÁTICOS

### Exemplo 1: Triagem Simples (Comercial/Suporte)

```
AUTOMAÇÃO: "Triagem WhatsApp"

[TRIGGER: new_conversation, channel=whatsapp]
   ↓
[CHATBOT: menu]
   Mensagem: "Olá {{contact.name}}! Escolha uma opção:
              1 - Comercial
              2 - Suporte"
   ↓
[CONDITION: message contains "1"]
   ↓ TRUE
[MOVE STAGE: Funil Vendas, Estágio "Novo Lead"]
   → Auto-atribuição da etapa: Setor Comercial, Round-Robin

[CONDITION: message contains "2"]
   ↓ TRUE
[MOVE STAGE: Funil Suporte, Estágio "Novo Ticket"]
   → Auto-atribuição da etapa: Setor Suporte, Por Carga
```

### Exemplo 2: Triagem com IA (Mais Inteligente)

```
AUTOMAÇÃO: "Triagem Inteligente IA"

[TRIGGER: new_conversation, channel=whatsapp]
   ↓
[ASSIGN: AI Agent "Triagem SDR"]
   → Agente de IA faz perguntas e coleta informações
   → IA decide automaticamente: Comercial ou Suporte
   → IA adiciona TAG conforme decisão ("lead_comercial" ou "suporte_tecnico")
   ↓
[CONDITION: has_tag "lead_comercial"]
   ↓ TRUE
[MOVE STAGE: Funil Vendas, Estágio "Lead Qualificado"]
[ASSIGN: Agente específico ou Setor Vendas]

[CONDITION: has_tag "suporte_tecnico"]
   ↓ TRUE
[MOVE STAGE: Funil Suporte, Estágio "Aguardando Atendimento"]
[ASSIGN: Agente específico ou Setor Suporte]
```

### Exemplo 3: Triagem Multi-nível

```
AUTOMAÇÃO: "Triagem Avançada"

[TRIGGER: new_conversation]
   ↓
[CHATBOT: menu]
   "1 - Vendas
    2 - Suporte
    3 - Financeiro"
   ↓
[CONDITION: message contains "1"]
   ↓ TRUE
   [CHATBOT: menu]
      "Qual tipo de produto?
       1 - Software
       2 - Hardware
       3 - Consultoria"
      ↓
   [CONDITION: contains "1"]
      ↓ TRUE
      [MOVE: Vendas Software, Lead]
      [ASSIGN: Setor Software]
   
   [CONDITION: contains "2"]
      ↓ TRUE
      [MOVE: Vendas Hardware, Lead]
      [ASSIGN: Setor Hardware]
```

---

## 4. FLUXO COMPLETO PASSO-A-PASSO

### 📱 Cenário Real: Cliente entra pelo WhatsApp

#### **Etapa 1: Cliente entra no sistema**
```
- Canal: WhatsApp
- Contato: João Silva (+55 11 99999-9999)
- Primeira mensagem: "Olá!"
```
**Sistema cria conversa automaticamente**

#### **Etapa 2: Automação é disparada**
```
Trigger: "Nova Conversa" detectado
Canal: whatsapp ✅ Corresponde
→ Automação "Triagem WhatsApp" ACIONADA
```

#### **Etapa 3: Chatbot envia menu**
```
[CHATBOT executa]
→ Envia mensagem: "Olá João Silva! Como posso ajudar você hoje?
                    1 - Falar com Comercial
                    2 - Suporte Pós-Venda
                    3 - Falar com Atendente"
→ Aguarda resposta (timeout: 300s)
```

#### **Etapa 4: Cliente responde**
```
Cliente envia: "1"
```

#### **Etapa 5: Condição avalia**
```
[CONDITION: message contains "1"]
→ Avalia última mensagem: "1"
→ Resultado: TRUE ✅
→ Segue para próximo nó conectado
```

#### **Etapa 6: Move para funil/etapa**
```
[MOVE STAGE executa]
→ Funil: "Vendas/Comercial" (ID: 1)
→ Estágio: "Novo Lead" (ID: 5)
→ Conversa movida com sucesso ✅
```

#### **Etapa 7: Auto-atribuição da etapa entra em ação**
```
Sistema detecta: Conversa entrou no estágio "Novo Lead"
Etapa configurada com auto_assign = TRUE
→ Busca agentes do Setor Comercial
→ Método: Round-Robin
→ Agentes disponíveis: [Maria, Pedro, Ana]
→ Próximo na fila: Maria
→ Conversa atribuída para Maria ✅
→ Notificação enviada para Maria 🔔
```

#### **Etapa 8: Conversa pronta para atendimento**
```
✅ Conversa movida para funil/etapa corretos
✅ Agente atribuído automaticamente
✅ Agente notificado
✅ Cliente recebeu feedback (mensagens do chatbot)
```

---

## 5. CONFIGURAÇÃO RECOMENDADA

### 🎛️ Configurações de Etapas

**Funil: Vendas/Comercial**
| Etapa | Auto-atribuir? | Setor | Método |
|---|---|---|---|
| Novo Lead | ✅ SIM | Comercial | Round-Robin |
| Em Negociação | ❌ NÃO | - | (mantém agente) |
| Proposta Enviada | ❌ NÃO | - | (mantém agente) |
| Ganho | ❌ NÃO | - | - |

**Funil: Pós-Venda/Suporte**
| Etapa | Auto-atribuir? | Setor | Método |
|---|---|---|---|
| Novo Ticket | ✅ SIM | Suporte | Por Carga |
| Em Atendimento | ❌ NÃO | - | (mantém agente) |
| Aguardando Cliente | ❌ NÃO | - | (mantém agente) |
| Resolvido | ❌ NÃO | - | - |

### ⚙️ Configurações Gerais (Fallback)

Em `/settings` (quando implementar aba "Conversas Avançadas"):

```json
{
  "distribution": {
    "method": "round_robin",
    "enable_auto_assignment": true,
    "consider_availability": true,
    "assign_to_ai_agent": false
  },
  "sla": {
    "first_response_time": 15,
    "auto_reassign_on_sla_breach": true
  },
  "reassignment": {
    "enable_auto_reassignment": true,
    "reassign_on_inactivity_minutes": 60
  }
}
```

---

## 6. FLUXO COM IA (AVANÇADO)

### 🤖 Usando Agente de IA em vez de Chatbot

```
[TRIGGER: new_conversation, channel=whatsapp]
   ↓
[ASSIGN: AI Agent "SDR Triagem"]
   → IA conversa com cliente
   → IA faz perguntas inteligentes
   → IA coleta informações (nome, empresa, necessidade)
   → IA decide automaticamente:
      - Se é lead qualificado → Adiciona TAG "lead_qualificado"
      - Se precisa suporte → Adiciona TAG "suporte"
      - Se não qualificado → Adiciona TAG "low_priority"
   ↓
[CONDITION: has_tag "lead_qualificado"]
   ↓ TRUE
   [MOVE: Vendas, "Lead Qualificado"]
   [ASSIGN: Setor Vendas - Método: Por Performance]

[CONDITION: has_tag "suporte"]
   ↓ TRUE
   [MOVE: Suporte, "Novo Ticket"]
   [ASSIGN: Setor Suporte - Método: Por Carga]

[CONDITION: has_tag "low_priority"]
   ↓ TRUE
   [MOVE: Geral, "Baixa Prioridade"]
   [SET TAG: "followup_7dias"]
```

### 🎯 Vantagens da IA vs Chatbot Menu

| Aspecto | Chatbot Menu | Agente de IA |
|---|---|---|
| **Flexibilidade** | Limitado a opções fixas | Conversa natural |
| **Inteligência** | Zero (baseado em keywords) | Alta (entende contexto) |
| **Coleta de Dados** | Manual (perguntas fixas) | Automática (conversa fluida) |
| **Qualificação** | Básica | Avançada (analisa respostas) |
| **Custo** | Zero | Tokens da OpenAI |
| **Setup** | 5 minutos | 30 minutos |
| **Melhor Para** | Triagem simples | Triagem complexa |

---

## 7. EXEMPLO COMPLETO: E-COMMERCE

### 🛒 Fluxo para Loja Virtual

```
Cliente chama: "Olá, tenho uma dúvida sobre meu pedido"
   ↓
[TRIGGER: new_conversation, channel=whatsapp]
   ↓
[CHATBOT: conditional]
   Keywords: "pedido, compra, entrega, produto, dúvida"
   Mensagem: "Olá {{contact.name}}! Vi que você tem uma dúvida.
              
              1 - Rastreamento de Pedido
              2 - Problema com Produto
              3 - Cancelamento/Devolução
              4 - Falar com Atendente"
   ↓
Cliente: "1"
   ↓
[CONDITION: message contains "1"]
   ↓ TRUE
   [SEND MESSAGE: "Por favor, informe o número do seu pedido:"]
   [DELAY: 60 segundos] → Aguarda resposta
   ↓
[CONDITION: message contains número_pedido (regex)]
   ↓ TRUE
   [CHATBOT: com IA ou API WooCommerce]
      → Busca pedido
      → Envia status de rastreamento
   ↓
[CONDITION: pedido_com_problema]
   ↓ TRUE
   [MOVE: Pós-Venda, "Problema com Pedido"]
   [SET TAG: "pedido_#{numero}"]
   [ASSIGN: Setor Pós-Venda]
```

---

## 8. MELHORES PRÁTICAS

### ✅ DO's

1. **Sempre configure auto-atribuição nas etapas de entrada**
   - "Novo Lead", "Novo Ticket", "Primeira Conversa"

2. **Use chatbot menu para triagem simples** (2-4 opções)
   - Rápido, zero custo, fácil de configurar

3. **Use IA para triagem complexa** (qualificação, coleta de dados)
   - Mais inteligente, conversa natural, qualifica melhor

4. **Configure timeout em chatbots**
   - Se cliente não responder, atribua a um humano

5. **Use tags para rastrear decisões**
   - Adicione tags ao mover conversas para rastrear origem

6. **Teste antes de ativar**
   - Use "Teste Avançado" nas automações

7. **Monitore logs de execução**
   - Verifique se automações estão funcionando

### ❌ DON'Ts

1. **Não crie loops infinitos**
   - Evite: Chatbot → Condição → Chatbot (mesmo)

2. **Não esqueça de conectar nós**
   - Sistema valida isso, mas sempre revise

3. **Não use timeout muito curto**
   - Mínimo 60s para chatbot menu, 300s para IA

4. **Não sobrescreva auto-atribuição de todas as etapas**
   - Use apenas nas etapas de entrada (primeira etapa de cada funil)

5. **Não misture métodos de atribuição sem planejamento**
   - Seja consistente: ou etapa OU geral, não ambos sem motivo

---

## 9. TROUBLESHOOTING

### ❓ "Auto-atribuição não está funcionando"

**Checklist:**
1. ✅ Etapa tem `auto_assign = TRUE`?
2. ✅ Setor tem agentes disponíveis?
3. ✅ Agentes têm `availability_status = online`?
4. ✅ Agentes não atingiram `max_conversations`?
5. ✅ Conversa realmente **entrou** na etapa (não apenas foi criada)?

### ❓ "Chatbot não está enviando mensagem"

**Checklist:**
1. ✅ Automação está **ativa**?
2. ✅ Chatbot está conectado ao trigger?
3. ✅ Mensagem está preenchida?
4. ✅ Variáveis estão corretas (`{{contact.name}}`)?
5. ✅ Verifique logs em `/automations/{id}` → Aba "Logs"

### ❓ "Conversa não está sendo movida para funil/etapa"

**Checklist:**
1. ✅ Condição está conectada ao MOVE?
2. ✅ Condição está retornando TRUE? (teste com logs)
3. ✅ Funil e Estágio existem?
4. ✅ Agente tem permissão para mover?
5. ✅ Validar regras está habilitado? (pode bloquear se estágio está cheio)

---

## 10. INTEGRAÇÃO COM CONFIGURAÇÕES GERAIS

### 🔄 Fluxo de Decisão Completo

```
Nova Conversa Criada
   ↓
┌──────────────────────────────────────────┐
│ 1. AUTOMAÇÕES estão ativas?              │
│    → SIM: Executar automações            │
│    → NÃO: Ir para passo 2                │
└──────────────┬───────────────────────────┘
               ↓
┌──────────────────────────────────────────┐
│ 2. Conversa foi movida para etapa?       │
│    → SIM: Verificar auto-atribuição      │
│    → NÃO: Ir para passo 3                │
└──────────────┬───────────────────────────┘
               ↓
┌──────────────────────────────────────────┐
│ 3. Etapa tem auto-atribuição?            │
│    → SIM: USAR CONFIG DA ETAPA           │
│    → NÃO: Ir para passo 4                │
└──────────────┬───────────────────────────┘
               ↓
┌──────────────────────────────────────────┐
│ 4. Config Geral tem auto-atribuição?     │
│    → SIM: USAR CONFIG GERAL              │
│    → NÃO: Aguardar atribuição manual     │
└──────────────────────────────────────────┘
```

### 📊 Matriz de Decisão

| Cenário | Etapa Auto? | Geral Auto? | Resultado |
|---|---|---|---|
| 1 | ✅ SIM | ✅ SIM | Usa **ETAPA** |
| 2 | ✅ SIM | ❌ NÃO | Usa **ETAPA** |
| 3 | ❌ NÃO | ✅ SIM | Usa **GERAL** |
| 4 | ❌ NÃO | ❌ NÃO | **Manual** |

---

## 11. CÓDIGO DE EXEMPLO

### Implementação no Backend

```php
// Em FunnelService::handleStageAutoAssignment()

private static function handleStageAutoAssignment(int $conversationId, array $stage): void
{
    $conversation = Conversation::find($conversationId);
    if (!$conversation || !empty($conversation['agent_id'])) {
        return; // Já tem agente
    }

    // 1. Tentar auto-atribuição da ETAPA primeiro
    if (!empty($stage['auto_assign']) && $stage['auto_assign']) {
        $departmentId = $stage['auto_assign_department_id'] ?? null;
        $method = $stage['auto_assign_method'] ?? 'round-robin';
        
        $agentId = self::assignAgentForStage(
            $conversationId, 
            $departmentId, 
            $stage['funnel_id'], 
            $stage['id'], 
            $method
        );
        
        if ($agentId) {
            // Atribuído com sucesso usando config da ETAPA
            Conversation::update($conversationId, [
                'agent_id' => $agentId,
                'assigned_at' => date('Y-m-d H:i:s')
            ]);
            
            error_log("Conversa {$conversationId} atribuída via ETAPA ao agente {$agentId}");
            return;
        }
    }
    
    // 2. FALLBACK: Usar configurações GERAIS
    $settings = ConversationSettingsService::getSettings();
    
    if ($settings['distribution']['enable_auto_assignment']) {
        $method = $settings['distribution']['method'];
        
        $agentId = ConversationSettingsService::distributeConversation(
            $conversationId,
            $method,
            null // departmentId
        );
        
        if ($agentId) {
            Conversation::update($conversationId, [
                'agent_id' => $agentId,
                'assigned_at' => date('Y-m-d H:i:s')
            ]);
            
            error_log("Conversa {$conversationId} atribuída via CONFIG GERAL ao agente {$agentId}");
        }
    }
}
```

---

## 12. RESUMO EXECUTIVO

### ✅ Para implementar o fluxo desejado:

1. **Configure as etapas de destino**
   - Habilite auto-atribuição
   - Escolha setor e método
   
2. **Crie uma automação**
   - Trigger: Nova Conversa (canal específico)
   - Chatbot: Menu com opções
   - Condições: Uma para cada opção
   - Move Stage: Para funil/etapa correspondente
   
3. **Sistema faz o resto automaticamente**
   - Move conversa
   - Auto-atribuição da etapa entra em ação
   - Agente notificado
   - Cliente atendido

### 🎯 Resposta Direta às Suas Perguntas

**1. Como fazer entre etapa vs geral?**
- **Prioridade**: Etapa > Geral
- **Recomendação**: Configure etapas de entrada com auto-atribuição; deixe geral como fallback

**2. Como fazer o fluxo (canal → chatbot → funil → atribuição)?**
- **Use AUTOMAÇÕES**: Já está tudo pronto!
- **Passos**: Trigger (canal) → Chatbot (triagem) → Condition (decisão) → Move Stage (direcionar) → Auto-atribuição da etapa (atribuir)
- **Tempo de setup**: ~10 minutos

---

## 🎉 CONCLUSÃO

Seu sistema **JÁ ESTÁ PRONTO** para implementar o fluxo completo desejado!

✅ Sistema de Automações: 100%  
✅ Chatbot Visual: 100%  
✅ Auto-atribuição de Etapas: 100%  
✅ Condições e Movimentação: 100%  

**Não precisa programar nada novo**, apenas:
1. Configurar etapas
2. Criar automação visual
3. Testar
4. Ativar

**Tudo visual, sem código!** 🎉

---

**Última atualização**: 2025-01-17

