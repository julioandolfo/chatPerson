# 🎯 EXEMPLO PRÁTICO: TRIAGEM AUTOMATIZADA

**Cenário Real**: Empresa com WhatsApp que precisa direcionar clientes para Comercial ou Suporte

---

## 📱 CENÁRIO

**Empresa**: Loja de Software  
**Canais**: WhatsApp, Email, Chat  
**Setores**: Comercial, Suporte, Financeiro  
**Objetivo**: Direcionar automaticamente clientes para o setor correto

---

## 🎬 PASSO-A-PASSO COMPLETO

### PARTE 1: CONFIGURAÇÃO INICIAL

#### 1.1 Criar Funis e Etapas

**Funil 1: "Vendas/Comercial"**
```
Acessar: /funnels
Clicar: "Novo Funil"

Nome: Vendas
Descrição: Funil de vendas e pré-venda
Cor: #007bff (azul)

Etapas:
- Novo Lead
- Em Contato
- Proposta Enviada
- Negociação
- Ganho/Perdido
```

**Funil 2: "Pós-Venda/Suporte"**
```
Nome: Suporte
Descrição: Funil de atendimento pós-venda
Cor: #28a745 (verde)

Etapas:
- Novo Ticket
- Em Atendimento
- Aguardando Cliente
- Resolvido
```

#### 1.2 Configurar Auto-Atribuição nas Etapas

**Etapa: "Novo Lead" (Funil Vendas)**
```
Acessar: /funnels/1/kanban
Clicar no botão "⋮" da etapa "Novo Lead"
Selecionar: "Editar"
Ir para aba: "Auto-atribuição"

✅ Marcar: "Auto-atribuir conversas ao entrar no estágio"

Configurações:
- Departamento: [Selecionar] Comercial
- Método: Round-Robin
- Apenas agentes online: ✅ Sim

Salvar
```

**Etapa: "Novo Ticket" (Funil Suporte)**
```
Acessar: /funnels/2/kanban
Editar etapa "Novo Ticket"
Aba "Auto-atribuição"

✅ Auto-atribuir: SIM

Configurações:
- Departamento: Suporte
- Método: Por Carga (distribui para quem tem menos conversas)
- Apenas agentes online: ✅ Sim

Salvar
```

---

### PARTE 2: CRIAR AUTOMAÇÃO DE TRIAGEM

#### 2.1 Criar Nova Automação

```
Acessar: /automations
Clicar: "Nova Automação"

Formulário:
- Nome: Triagem WhatsApp - Comercial/Suporte
- Descrição: Direciona clientes automaticamente para setor correto
- Tipo de Gatilho: Nova Conversa
- Condições do Gatilho:
  ✅ Canal: WhatsApp
  [ ] Funil: (deixar vazio - aplica a todos)
  [ ] Estágio: (deixar vazio)
- Status: Ativa

Clicar: "Criar e Editar"
```

#### 2.2 Montar Fluxo Visual

Você será redirecionado para o editor visual. Agora vamos montar o fluxo:

**Nó 1 - Trigger (já existe)**
```
Tipo: trigger
Gatilho: Nova Conversa
Canal: whatsapp
```

**Adicionar Nó 2 - Chatbot de Boas-Vindas**
```
1. Arraste "Chatbot" do painel lateral direito para o canvas
2. Conecte: Clique no círculo azul do Trigger, arraste até o novo nó
3. Clique no nó "Chatbot" para configurar

Configuração:
- Tipo de Chatbot: [Selecionar] Menu com Opções
- Mensagem Inicial: 
  "Olá {{contact.name}}! 👋
   
   Seja bem-vindo(a) à Nossa Empresa!
   
   Para agilizar seu atendimento, selecione uma opção:
   
   1️⃣ - Quero comprar / Saber mais sobre produtos
   2️⃣ - Preciso de suporte técnico
   3️⃣ - Falar com atendente
   
   Digite o número da opção desejada."

- Opções do Menu:
  [Adicionar]
  Opção 1: "1 - Vendas/Comercial"
  [Adicionar]
  Opção 2: "2 - Suporte Técnico"
  [Adicionar]
  Opção 3: "3 - Atendente Humano"

- Tempo de Espera: 300 segundos (5 minutos)
- Ação ao Timeout: [Selecionar] Atribuir a um Agente

Salvar
```

**Adicionar Nó 3 - Condição (Opção 1 - Comercial)**
```
1. Arraste "Condição" para o canvas
2. Conecte: Chatbot → Condição
3. Configure:

- Campo: [Selecionar] Última Mensagem → contact.phone (usar campo customizado)
  OU criar condição simples:
  Campo: [Digitar] "message_content"
  
- Operador: contains (Contém)
- Valor: 1

Nota: Você pode adicionar múltiplas condições (OR):
- contains "1" OU
- contains "comercial" OU
- contains "vendas"

Salvar
```

**Adicionar Nó 4 - Enviar Mensagem de Confirmação**
```
1. Arraste "Enviar Mensagem" para o canvas
2. Conecte: Condição (saída TRUE) → Enviar Mensagem
3. Configure:

Mensagem:
"Perfeito! 👍

Você será direcionado para nossa equipe de Vendas.
Um de nossos consultores entrará em contato em breve!

Aguarde um momento..."

Preview: [Clicar para ver como ficará]

Salvar
```

**Adicionar Nó 5 - Mover para Funil Comercial**
```
1. Arraste "Mover para Estágio" para o canvas
2. Conecte: Enviar Mensagem → Mover
3. Configure:

- Funil: [Selecionar] Vendas
  (Ao selecionar, carrega automaticamente os estágios)
  
- Estágio: [Selecionar] Novo Lead

- ✅ Validar Regras: SIM
  (Verifica se o estágio não está cheio, etc)

Salvar
```

**Adicionar Nó 6 - Condição (Opção 2 - Suporte)**
```
1. Arraste "Condição" para o canvas
2. Conecte: Chatbot → Condição (nova conexão)
3. Configure:

- Campo: message_content
- Operador: contains
- Valor: 2

Salvar
```

**Adicionar Nó 7 - Enviar Mensagem Suporte**
```
1. Arraste "Enviar Mensagem"
2. Conecte: Condição (TRUE) → Enviar
3. Configure:

Mensagem:
"Entendido! 🛠️

Você será direcionado para nossa equipe de Suporte.
Descreva brevemente o problema e um técnico irá atendê-lo!

Aguarde..."

Salvar
```

**Adicionar Nó 8 - Mover para Funil Suporte**
```
1. Arraste "Mover para Estágio"
2. Conecte: Enviar → Mover
3. Configure:

- Funil: Suporte
- Estágio: Novo Ticket
- ✅ Validar Regras: SIM

Salvar
```

**Adicionar Nó 9 - Condição (Opção 3 - Atendente)**
```
1. Arraste "Condição"
2. Conecte: Chatbot → Condição
3. Configure:

- Campo: message_content
- Operador: contains
- Valor: 3

Salvar
```

**Adicionar Nó 10 - Atribuir Agente Direto**
```
1. Arraste "Atribuir Agente"
2. Conecte: Condição (TRUE) → Atribuir
3. Configure:

- Agente: [Deixar vazio para auto-atribuição OU selecionar agente específico]
- ✅ Notificar Agente: SIM

Salvar
```

#### 2.3 Salvar e Ativar

```
1. Clicar em "Salvar Layout" (botão no topo)
2. Aguardar confirmação: "Layout salvo com sucesso!"
3. Voltar para /automations
4. Verificar que está com status "Ativa" ✅
```

---

### PARTE 3: TESTAR O FLUXO

#### 3.1 Teste Rápido (Simulado)

```
1. Em /automations, clicar em "Ver" na automação criada
2. Clicar no dropdown "Teste" → "Teste Rápido"
3. Aguardar execução
4. Verificar resultado:
   - ✅ Chatbot enviou mensagem
   - ✅ Condições avaliadas
   - ✅ Conversa movida
   - ✅ Agente atribuído
```

#### 3.2 Teste Real (Com WhatsApp)

```
1. Envie mensagem do seu WhatsApp para o número conectado
2. Aguarde mensagem do chatbot (1-3 segundos)
3. Responda com "1"
4. Verifique:
   - Recebeu mensagem de confirmação
   - Em /conversations, conversa aparece no funil "Vendas", etapa "Novo Lead"
   - Conversa está atribuída a um agente do setor Comercial
   - Agente recebeu notificação
```

---

## 🎯 FLUXOGRAMA VISUAL

```
┌─────────────────────────────────────────────────────┐
│ CLIENTE ENVIA MENSAGEM VIA WHATSAPP                 │
│ "Olá!"                                              │
└───────────────────┬─────────────────────────────────┘
                    │
                    ↓
┌─────────────────────────────────────────────────────┐
│ [TRIGGER] Nova Conversa Detectada                   │
│ Canal: WhatsApp ✅                                   │
└───────────────────┬─────────────────────────────────┘
                    │
                    ↓
┌─────────────────────────────────────────────────────┐
│ [CHATBOT] Envia Menu de Opções                      │
│ "Olá {{contact.name}}!                              │
│  1 - Comercial                                      │
│  2 - Suporte                                        │
│  3 - Atendente"                                     │
└───────────────────┬─────────────────────────────────┘
                    │
                    ↓
        ┌───────────┴───────────┬─────────────────┐
        │                       │                 │
        ↓                       ↓                 ↓
┌────────────────┐    ┌────────────────┐   ┌────────────────┐
│ CLIENTE: "1"   │    │ CLIENTE: "2"   │   │ CLIENTE: "3"   │
└───────┬────────┘    └───────┬────────┘   └───────┬────────┘
        │                     │                     │
        ↓                     ↓                     ↓
┌────────────────┐    ┌────────────────┐   ┌────────────────┐
│ [CONDITION]    │    │ [CONDITION]    │   │ [CONDITION]    │
│ contains "1"   │    │ contains "2"   │   │ contains "3"   │
│ ✅ TRUE        │    │ ✅ TRUE        │   │ ✅ TRUE        │
└───────┬────────┘    └───────┬────────┘   └───────┬────────┘
        │                     │                     │
        ↓                     ↓                     ↓
┌────────────────┐    ┌────────────────┐   ┌────────────────┐
│ [SEND MSG]     │    │ [SEND MSG]     │   │ [ASSIGN]       │
│ "Direcionando  │    │ "Direcionando  │   │ Atribuir a     │
│  para Vendas"  │    │  para Suporte" │   │ agente         │
└───────┬────────┘    └───────┬────────┘   └────────────────┘
        │                     │                     
        ↓                     ↓                     
┌────────────────┐    ┌────────────────┐            
│ [MOVE STAGE]   │    │ [MOVE STAGE]   │            
│ Funil: Vendas  │    │ Funil: Suporte │            
│ Etapa: Novo    │    │ Etapa: Novo    │            
│        Lead    │    │        Ticket  │            
└───────┬────────┘    └───────┬────────┘            
        │                     │                     
        ↓                     ↓                     
┌────────────────────────────────────────┐          
│ AUTO-ATRIBUIÇÃO DA ETAPA                │          
│ - Busca agentes do setor configurado   │          
│ - Aplica método (round-robin/carga)    │          
│ - Atribui ao próximo agente disponível │          
│ - Envia notificação                    │          
└────────────────────────────────────────┘          
```

---

## 📊 RESULTADO ESPERADO

### Quando cliente escolhe "1" (Comercial)

**Backend:**
```
[15:30:45] Conversa #123 criada
[15:30:45] Automação "Triagem WhatsApp" acionada
[15:30:46] Chatbot enviou menu de opções
[15:31:12] Cliente respondeu: "1"
[15:31:12] Condição avaliada: contains "1" = TRUE
[15:31:13] Mensagem enviada: "Direcionando para Vendas..."
[15:31:13] Conversa movida: Funil Vendas, Etapa Novo Lead
[15:31:14] Auto-atribuição acionada: Setor Comercial, Round-Robin
[15:31:14] Próximo agente: Maria (ID: 5)
[15:31:14] Conversa atribuída: agent_id = 5
[15:31:14] Notificação enviada para Maria
```

**Tela do Agente (Maria):**
```
🔔 Nova conversa atribuída!

Contato: João Silva
Canal: WhatsApp
Origem: Triagem Automática
Funil: Vendas → Novo Lead
Última mensagem: "1"

[Abrir Conversa]
```

**Tela do Cliente (WhatsApp):**
```
Cliente: "Olá!"

Sistema: "Olá João Silva! 👋
         
         Seja bem-vindo(a) à Nossa Empresa!
         
         Para agilizar seu atendimento, selecione:
         
         1️⃣ - Quero comprar
         2️⃣ - Preciso de suporte
         3️⃣ - Falar com atendente"

Cliente: "1"

Sistema: "Perfeito! 👍
         Você será direcionado para Vendas.
         Aguarde..."

[3 segundos depois]

Maria (Vendas): "Olá João! Sou a Maria da equipe de Vendas.
                 Como posso ajudá-lo hoje?"
```

---

## ⚙️ CONFIGURAÇÕES OPCIONAIS

### Adicionar Delay Entre Mensagens

Para parecer mais natural, adicione delays:

```
[CHATBOT] → [DELAY 2s] → [SEND MSG] → [DELAY 3s] → [MOVE STAGE]
```

Configurar Delay:
```
1. Arraste nó "Delay"
2. Conecte entre nós
3. Configure:
   - Tempo: 2
   - Unidade: segundos
```

### Adicionar Tags Automáticas

Para rastrear origem:

```
Após [MOVE STAGE], adicionar:

[SET TAG]
- Tag: "origem_triagem_whatsapp"
- Ação: Adicionar
```

### Enviar Métricas para Analytics

```
Após atribuição, adicionar:

[SEND MESSAGE para API]
- URL: https://analytics.empresa.com/track
- Dados: {
    "event": "conversa_triada",
    "canal": "whatsapp",
    "destino": "comercial"
  }
```

---

## 📈 MONITORAMENTO

### Verificar Logs de Execução

```
1. Acessar: /automations
2. Clicar em "Ver" na automação
3. Ir para aba "Logs de Execução"
4. Verificar:
   - Quantas vezes foi executada
   - Taxa de sucesso
   - Erros (se houver)
   - Tempo médio de execução
```

### Métricas Importantes

```
Acessar: /analytics

Métricas de Automação:
- Total de execuções: 156
- Taxa de sucesso: 98.7%
- Distribuição por opção:
  - Opção 1 (Comercial): 65%
  - Opção 2 (Suporte): 30%
  - Opção 3 (Atendente): 5%
  
Tempo médio de triagem: 45 segundos
Conversas auto-atribuídas: 95%
```

---

## 🔧 AJUSTES E MELHORIAS

### Se clientes não estão respondendo:

**Problema:** Timeout muito curto  
**Solução:** Aumentar para 600s (10 min)

**Problema:** Mensagem confusa  
**Solução:** Simplificar menu, usar emojis

### Se está atribuindo para agente errado:

**Problema:** Setor não configurado na etapa  
**Solução:** Revisar configuração de auto-atribuição

**Problema:** Todos os agentes offline  
**Solução:** Desmarcar "Apenas agentes online"

### Se automação não está executando:

**Problema:** Automação inativa  
**Solução:** Ativar em /automations

**Problema:** Trigger não corresponde  
**Solução:** Verificar canal no gatilho

---

## ✅ CHECKLIST FINAL

Antes de colocar em produção:

- [ ] Funis criados (Vendas, Suporte)
- [ ] Etapas criadas (Novo Lead, Novo Ticket)
- [ ] Auto-atribuição configurada nas etapas de entrada
- [ ] Setores criados e agentes vinculados
- [ ] Automação criada e ativa
- [ ] Todos os nós conectados
- [ ] Teste simulado executado com sucesso
- [ ] Teste real com WhatsApp bem-sucedido
- [ ] Agentes notificados corretamente
- [ ] Logs verificados (sem erros)
- [ ] Métricas configuradas
- [ ] Equipe treinada

---

## 🎉 PRONTO!

Seu sistema de triagem automatizada está **100% funcional**!

**Tempo de setup:** ~15 minutos  
**Esforço de programação:** ZERO ✅  
**Tudo visual:** SIM ✅  
**Taxa de sucesso esperada:** >95% ✅

---

**Última atualização**: 2025-01-17

