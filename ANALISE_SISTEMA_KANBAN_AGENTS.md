# 📊 ANÁLISE COMPLETA - SISTEMA DE AGENTES DE IA PARA KANBAN

**Data**: 09/01/2025  
**Status**: Sistema Implementado (com 1 bug identificado e corrigido)

---

## 🎯 VISÃO GERAL

O **Sistema de Agentes de IA para Kanban** é um módulo completo que permite criar agentes virtuais especializados que:
- Monitoram funis e etapas específicas do Kanban
- Executam periodicamente (agendado, por intervalo ou manualmente)
- Analisam conversas usando OpenAI
- Avaliam condições configuráveis
- Executam ações automáticas (followup, movimentação, tags, notas, etc)

---

## 🏗️ ARQUITETURA DO SISTEMA

### 1. Estrutura de Dados (3 Tabelas Principais)

#### 1.1. `ai_kanban_agents`
**Propósito**: Configuração dos agentes Kanban

**Campos Principais**:
- `id`: ID único do agente
- `name`: Nome do agente
- `description`: Descrição do agente
- `agent_type`: Tipo do agente
  - `kanban_followup`: Followup automático
  - `kanban_analyzer`: Analisador de conversas
  - `kanban_manager`: Gerenciador de funis
  - `kanban_custom`: Customizado
- `prompt`: Prompt para análise com IA
- `model`: Modelo OpenAI (gpt-4, gpt-3.5-turbo, etc)
- `temperature`: Temperature (0.0 a 2.0)
- `max_tokens`: Máximo de tokens na resposta
- `enabled`: Se o agente está ativo
- `target_funnel_ids`: JSON com IDs dos funis alvo (null = todos)
- `target_stage_ids`: JSON com IDs das etapas alvo (null = todas)
- `execution_type`: Tipo de execução
  - `interval`: Por intervalo (ex: a cada 48 horas)
  - `schedule`: Por agendamento (ex: Segunda/Quarta/Sexta às 9h)
  - `manual`: Apenas execução manual
- `execution_interval_hours`: Intervalo em horas (se tipo = interval)
- `execution_schedule`: JSON com agendamento (se tipo = schedule)
  - Formato: `{"days": [1,3,5], "time": "09:00"}`
- `last_execution_at`: Última execução
- `next_execution_at`: Próxima execução agendada
- `conditions`: JSON com condições para ativação
- `actions`: JSON com ações a executar
- `settings`: JSON com configurações extras
- `max_conversations_per_execution`: Limite de conversas por execução (padrão: 50)

#### 1.2. `ai_kanban_agent_executions`
**Propósito**: Histórico de execuções dos agentes

**Campos Principais**:
- `id`: ID único da execução
- `ai_kanban_agent_id`: ID do agente Kanban
- `execution_type`: Tipo de execução (scheduled, manual, triggered)
- `started_at`: Início da execução
- `completed_at`: Fim da execução
- `status`: Status da execução
  - `running`: Em execução
  - `completed`: Concluída com sucesso
  - `failed`: Falhou
  - `cancelled`: Cancelada
- `conversations_analyzed`: Quantidade de conversas analisadas
- `conversations_acted_upon`: Conversas que tiveram ações executadas
- `actions_executed`: Total de ações executadas
- `errors_count`: Total de erros
- `results`: JSON com detalhes da execução
- `error_message`: Mensagem de erro (se houver)

#### 1.3. `ai_kanban_agent_actions_log`
**Propósito**: Log detalhado de cada ação executada

**Campos Principais**:
- `id`: ID único do log
- `ai_kanban_agent_id`: ID do agente Kanban
- `execution_id`: ID da execução
- `conversation_id`: ID da conversa
- `analysis_summary`: Resumo da análise feita pela IA
- `analysis_score`: Score de confiança (0-100)
- `conditions_met`: Se condições foram atendidas
- `conditions_details`: JSON com detalhes de quais condições foram atendidas
- `actions_executed`: JSON com ações executadas e resultados
- `success`: Se execução foi bem-sucedida
- `error_message`: Mensagem de erro (se houver)
- `executed_at`: Data/hora da execução

---

## 🔧 COMPONENTES DO SISTEMA

### 1. Models (app/Models/)

#### AIKanbanAgent.php
**Responsabilidade**: Gerenciar dados dos agentes Kanban

**Métodos Principais**:
- `getReadyForExecution()`: Retorna agentes prontos para executar
- `getExecutions(agentId, limit)`: Retorna execuções de um agente
- `getActionLogs(agentId, limit)`: Retorna logs de ações de um agente
- `updateNextExecution(agentId)`: Atualiza próxima execução
- `whereActive()`: Retorna agentes ativos
- `find(id)`: Busca agente e decodifica campos JSON
- `create(data)`: Cria agente e codifica campos JSON
- `update(id, data)`: Atualiza agente e codifica campos JSON

**Observação**: Campos JSON (target_funnel_ids, target_stage_ids, execution_schedule, conditions, actions, settings) são automaticamente codificados/decodificados.

#### AIKanbanAgentExecution.php
**Responsabilidade**: Gerenciar execuções dos agentes

**Métodos Principais**:
- `createExecution(agentId, executionType)`: Cria nova execução
- `completeExecution(executionId, stats, errorMessage)`: Finaliza execução
- `getRecent(limit)`: Retorna execuções recentes (todas)
- `find(id)`: Busca execução e decodifica campos JSON

#### AIKanbanAgentActionLog.php
**Responsabilidade**: Gerenciar logs de ações

**Métodos Principais**:
- `createLog(data)`: Cria log de ação
- `getByConversation(conversationId, limit)`: Retorna logs de uma conversa
- `find(id)`: Busca log e decodifica campos JSON
- `all()`: Retorna todos os logs com decodificação JSON

---

### 2. Service (app/Services/)

#### KanbanAgentService.php
**Responsabilidade**: Lógica de negócio dos agentes Kanban

**Métodos Principais**:

##### Execução
- `executeReadyAgents()`: Executa todos os agentes prontos
- `executeAgent(agentId, executionType)`: Executa um agente específico
- `getTargetConversations(agent)`: Busca conversas alvo do agente

##### Análise com IA
- `analyzeConversation(agent, conversation)`: Analisa conversa com OpenAI
- `buildConversationContext()`: Monta contexto da conversa
- `buildAnalysisPrompt()`: Monta prompt de análise
- `callOpenAI()`: Chama API OpenAI
- `parseAnalysisResponse()`: Parseia resposta da IA

##### Avaliação de Condições
- `evaluateConditions(conditions, conversation, analysis)`: Avalia condições (público para testes)
- `evaluateSingleCondition()`: Avalia condição única
- `compare()`: Compara valores
- `applyLogicOperator()`: Aplica operador lógico (AND, OR, NOT)

##### Execução de Ações
- `executeActions()`: Executa todas as ações
- `executeSingleAction()`: Executa ação única
- **Ações Disponíveis**:
  - `actionSendFollowupMessage()`: Envia mensagem de followup
  - `actionMoveToStage()`: Move para etapa específica
  - `actionMoveToNextStage()`: Move para próxima etapa
  - `actionAssignToAgent()`: Atribui a agente humano
  - `actionAssignAIAgent()`: Atribui agente de IA
  - `actionAddTag()`: Adiciona tags
  - `actionCreateSummary()`: Cria resumo
  - `actionCreateNote()`: Cria nota

##### Utilitários
- `generateFollowupMessage()`: Gera mensagem com IA
- `processTemplate()`: Processa templates
- `getRoundRobinAgent()`: Obtém agente por round-robin
- `getSystemUserId()`: Obtém ID do usuário do sistema

---

### 3. Controller (app/Controllers/)

#### KanbanAgentController.php
**Responsabilidade**: Gerenciar requisições HTTP dos agentes Kanban

**Métodos (Rotas)**:

##### Listagem e Visualização
- `index()`: GET `/kanban-agents` - Lista agentes com filtros
- `show(id)`: GET `/kanban-agents/{id}` - Mostra agente específico
- `getSystemData()`: GET `/kanban-agents/system-data` - Retorna dados do sistema para formulários

##### CRUD
- `create()`: GET `/kanban-agents/create` - Formulário de criação
- `store()`: POST `/kanban-agents` - Salva novo agente
- `edit(id)`: GET `/kanban-agents/{id}/edit` - Formulário de edição
- `update(id)`: POST `/kanban-agents/{id}` - Atualiza agente
- `delete(id)`: DELETE `/kanban-agents/{id}` - Deleta agente

##### Execução e Testes
- `execute(id)`: POST `/kanban-agents/{id}/execute` - Executa agente manualmente
- `testConditions(id)`: POST `/kanban-agents/{id}/test-conditions` - Testa condições em uma conversa

---

### 4. Views (views/kanban-agents/)

#### index.php
**Propósito**: Lista todos os agentes Kanban

**Recursos**:
- Filtros por tipo de agente, status (enabled), busca por nome/descrição
- Tabela com: Nome, Tipo, Funis/Etapas, Execução, Status, Última/Próxima Execução
- Botões: Ver Detalhes, Editar, Executar Manualmente, Deletar

#### show.php
**Propósito**: Exibe detalhes de um agente específico

**Recursos**:
- Informações gerais: Nome, Descrição, Tipo, Status, Modelo IA
- Configurações: Funis/Etapas alvo, Tipo de execução, Condições, Ações
- Últimas execuções (tabela)
- Logs de ações (tabela)
- Botões: Executar Manualmente, Editar, Voltar

#### create.php
**Propósito**: Formulário para criar novo agente

**Recursos**:
- Formulário com todos os campos configuráveis
- Seleção de funis e etapas
- Builder de condições (JSON)
- Builder de ações (JSON)
- Validação frontend

#### edit.php
**Propósito**: Formulário para editar agente existente

**Recursos**:
- Mesmos recursos do create.php
- Campos preenchidos com dados atuais

---

### 5. Script de Execução Automática

#### public/run-kanban-agents.php
**Propósito**: Script para executar agentes periodicamente via cron

**Funcionamento**:
1. Chama `KanbanAgentService::executeReadyAgents()`
2. Para cada agente retornado:
   - Executa o agente
   - Registra logs de sucesso/erro
3. Exibe resumo da execução

**Configuração Cron Recomendada**:
```bash
# Executar a cada 5 minutos
*/5 * * * * cd /var/www/html && php public/run-kanban-agents.php >> storage/logs/kanban-agents-cron.log 2>&1
```

**Observação**: O script verifica automaticamente quais agentes devem ser executados baseado no campo `next_execution_at`.

---

## 🔄 FLUXO DE EXECUÇÃO COMPLETO

### 1. Execução Iniciada (Manual ou Automática)

```
[Início]
   ↓
[KanbanAgentService::executeAgent()]
   ↓
[Criar registro de execução (AIKanbanAgentExecution)]
   ↓
[Buscar conversas alvo dos funis/etapas configurados]
   ↓
[Para cada conversa (até max_conversations_per_execution)]
```

### 2. Análise de Conversa

```
[Buscar mensagens da conversa (últimas 20)]
   ↓
[Buscar informações do contato]
   ↓
[Buscar informações do funil/etapa]
   ↓
[Montar contexto completo]
   ↓
[Montar prompt com o prompt do agente + contexto]
   ↓
[Chamar OpenAI API]
   ↓
[Parsear resposta JSON]
   ↓
[Retornar análise: {summary, score, sentiment, urgency, recommendations}]
```

### 3. Avaliação de Condições

```
[Receber condições configuradas]
   ↓
[Para cada condição no array de condições]
   ↓
[Avaliar condição única baseada no tipo:]
   - conversation_status: Status da conversa
   - conversation_priority: Prioridade
   - last_message_hours: Horas desde última mensagem
   - last_message_from: De quem foi a última mensagem
   - client_no_response_minutes: Minutos sem resposta do cliente
   - agent_no_response_minutes: Minutos sem resposta do agente
   - stage_duration_hours: Horas na etapa atual
   - ai_analysis_score: Score da análise IA
   - ai_sentiment: Sentimento da análise
   - ai_urgency: Urgência da análise
   ↓
[Aplicar operador lógico (AND, OR, NOT)]
   ↓
[Retornar resultado: {met: true/false, details: []}]
```

### 4. Execução de Ações (Se Condições Atendidas)

```
[Para cada ação no array de ações]
   ↓
[Se ação está habilitada]
   ↓
[Executar ação baseada no tipo:]
   - analyze_conversation: Apenas retornar análise já feita
   - send_followup_message: Enviar mensagem de followup
   - move_to_stage: Mover para etapa específica
   - move_to_next_stage: Mover para próxima etapa
   - assign_to_agent: Atribuir a agente humano
   - assign_ai_agent: Atribuir agente de IA
   - add_tag: Adicionar tags
   - create_summary: Criar resumo
   - create_note: Criar nota
   ↓
[Registrar resultado da ação]
```

### 5. Finalização

```
[Criar log de ação (AIKanbanAgentActionLog)]
   ↓
[Atualizar estatísticas da execução]
   ↓
[Após processar todas as conversas]
   ↓
[Finalizar execução (AIKanbanAgentExecution)]
   ↓
[Atualizar próxima execução do agente (next_execution_at)]
   ↓
[Fim]
```

---

## 🔗 INTEGRAÇÕES E DEPENDÊNCIAS

### Integrações Internas

#### 1. Sistema de Conversas
- **Dependência**: `App\Models\Conversation`, `App\Services\ConversationService`
- **Uso**: 
  - Buscar conversas dos funis/etapas
  - Enviar mensagens de followup
  - Atualizar status/atribuição

#### 2. Sistema de Mensagens
- **Dependência**: `App\Models\Message`
- **Uso**: 
  - Buscar histórico de mensagens
  - Analisar última mensagem (de quem, quando)

#### 3. Sistema de Contatos
- **Dependência**: `App\Models\Contact`
- **Uso**: 
  - Obter informações do contato para contexto
  - Usar variáveis em templates ({contact_name})

#### 4. Sistema de Funis/Kanban
- **Dependência**: `App\Models\Funnel`, `App\Models\FunnelStage`
- **Uso**: 
  - Filtrar conversas por funis/etapas
  - Mover conversas entre etapas
  - Obter informações de funis/etapas para contexto

#### 5. Sistema de Tags
- **Dependência**: `App\Services\TagService`, `App\Models\Tag`
- **Uso**: 
  - Adicionar/remover tags de conversas
  - Usar tags como condições

#### 6. Sistema de Notas
- **Dependência**: `App\Services\ConversationNoteService`
- **Uso**: 
  - Criar resumos
  - Criar notas internas/externas

#### 7. Sistema de Agentes de IA (Automações)
- **Dependência**: `App\Models\AIAgent`
- **Uso**: 
  - Atribuir agentes de IA a conversas
  - **Observação**: São sistemas SEPARADOS (Kanban vs Automações)

#### 8. Sistema de Usuários
- **Dependência**: `App\Models\User`
- **Uso**: 
  - Atribuir conversas a agentes humanos
  - Obter usuário do sistema para criar notas

#### 9. Sistema de Departamentos
- **Dependência**: `App\Services\DepartmentService`
- **Uso**: 
  - Filtrar agentes por departamento
  - Atribuir conversas por departamento

### Integrações Externas

#### 1. OpenAI API
- **Dependência**: API OpenAI (GPT-4, GPT-3.5-turbo)
- **Uso**: 
  - Análise de conversas
  - Geração de mensagens de followup
  - Extração de insights
- **Configuração**: 
  - API Key em `settings` (chave: `openai_api_key`)
  - Endpoint: `https://api.openai.com/v1/chat/completions`
- **Formato de Requisição**:
  ```json
  {
    "model": "gpt-4",
    "messages": [
      {"role": "system", "content": "Você é um assistente..."},
      {"role": "user", "content": "Prompt + Contexto"}
    ],
    "temperature": 0.7,
    "max_tokens": 2000
  }
  ```
- **Formato de Resposta Esperado**:
  ```json
  {
    "summary": "Resumo da conversa",
    "score": 85,
    "sentiment": "positive",
    "urgency": "medium",
    "recommendations": ["Recomendação 1", "Recomendação 2"]
  }
  ```

### Dependências de Sistema

#### 1. Cron (Linux) ou Task Scheduler (Windows)
- **Uso**: Executar script `run-kanban-agents.php` periodicamente
- **Recomendação**: A cada 5 minutos

#### 2. PHP 8.1+
- **Recursos Usados**: 
  - Namespaces
  - Typed properties
  - JSON functions

#### 3. MySQL 8.0+
- **Recursos Usados**: 
  - JSON data type
  - Foreign keys com CASCADE

---

## ⚙️ SISTEMA DE CONDIÇÕES

### Tipos de Condições Disponíveis

#### 1. Condições de Status da Conversa
- `conversation_status`: Status da conversa (open, closed, resolved, pending, spam)
- `conversation_priority`: Prioridade (low, normal, medium, high, urgent)

#### 2. Condições de Tempo
- `last_message_hours`: Horas desde última mensagem (qualquer)
- `client_no_response_minutes`: Minutos sem resposta do cliente
- `agent_no_response_minutes`: Minutos sem resposta do agente
- `stage_duration_hours`: Horas na etapa atual

#### 3. Condições de Mensagens
- `last_message_from`: De quem foi a última mensagem (contact, agent, system)

#### 4. Condições de Análise IA
- `ai_analysis_score`: Score da análise (0-100)
- `ai_sentiment`: Sentimento (positive, neutral, negative)
- `ai_urgency`: Urgência (low, medium, high)

### Operadores de Comparação

- `equals`: Igual (==)
- `not_equals`: Diferente (!=)
- `greater_than`: Maior que (>)
- `less_than`: Menor que (<)
- `greater_or_equal`: Maior ou igual (>=)
- `less_or_equal`: Menor ou igual (<=)
- `includes`: Incluído em array
- `not_includes`: Não incluído em array

### Operadores Lógicos

- `AND`: Todas as condições devem ser verdadeiras
- `OR`: Pelo menos uma condição deve ser verdadeira
- `NOT`: Nenhuma condição deve ser verdadeira

### Estrutura JSON das Condições

```json
{
  "operator": "AND",
  "conditions": [
    {
      "type": "conversation_status",
      "operator": "equals",
      "value": "open"
    },
    {
      "type": "last_message_hours",
      "operator": "greater_than",
      "value": 24
    },
    {
      "type": "last_message_from",
      "operator": "equals",
      "value": "agent"
    }
  ]
}
```

---

## 🎬 SISTEMA DE AÇÕES

### Tipos de Ações Disponíveis

#### 1. Análise
- **Tipo**: `analyze_conversation`
- **Descrição**: Apenas retorna análise já feita (usado para logging)
- **Configuração**: Nenhuma

#### 2. Mensagens
- **Tipo**: `send_followup_message`
- **Descrição**: Envia mensagem de followup
- **Configuração**:
  - `use_ai_generated`: true/false (gerar com IA ou usar template)
  - `template`: Template da mensagem (se não usar IA)
  - **Variáveis disponíveis**: `{contact_name}`, `{analysis_summary}`, `{conversation_id}`

#### 3. Movimentação
- **Tipo**: `move_to_stage`
- **Descrição**: Move para etapa específica
- **Configuração**:
  - `stage_id`: ID da etapa de destino

- **Tipo**: `move_to_next_stage`
- **Descrição**: Move para próxima etapa do funil
- **Configuração**: Nenhuma

#### 4. Atribuição
- **Tipo**: `assign_to_agent`
- **Descrição**: Atribui a agente humano
- **Configuração**:
  - `method`: Método de seleção
    - `round_robin`: Agente com menos conversas ativas
    - (outros métodos podem ser implementados)
  - `department_id`: ID do departamento (opcional, para filtrar agentes)

- **Tipo**: `assign_ai_agent`
- **Descrição**: Atribui agente de IA
- **Configuração**:
  - `ai_agent_id`: ID do agente de IA

#### 5. Tags
- **Tipo**: `add_tag`
- **Descrição**: Adiciona tags à conversa
- **Configuração**:
  - `tags`: Array de IDs ou nomes de tags

#### 6. Resumos e Notas
- **Tipo**: `create_summary`
- **Descrição**: Cria resumo da análise
- **Configuração**:
  - `summary_type`: Tipo do resumo
    - `internal`: Nota interna (privada)
    - `external`: Nota externa (visível para cliente)
  - `include_recommendations`: true/false (incluir recomendações)

- **Tipo**: `create_note`
- **Descrição**: Cria nota personalizada
- **Configuração**:
  - `note`: Conteúdo da nota (aceita variáveis)
  - `is_internal`: true/false (interna ou externa)
  - **Variáveis disponíveis**: `{contact_name}`, `{analysis_summary}`, `{conversation_id}`

### Estrutura JSON das Ações

```json
[
  {
    "type": "analyze_conversation",
    "enabled": true
  },
  {
    "type": "send_followup_message",
    "enabled": true,
    "config": {
      "use_ai_generated": false,
      "template": "Olá {contact_name}, tudo bem? Vi que você estava interessado em nossos produtos..."
    }
  },
  {
    "type": "add_tag",
    "enabled": true,
    "config": {
      "tags": [1, 5, "followup_enviado"]
    }
  },
  {
    "type": "create_summary",
    "enabled": true,
    "config": {
      "summary_type": "internal",
      "include_recommendations": true
    }
  }
]
```

---

## 🐛 BUGS IDENTIFICADOS E CORRIGIDOS

### Bug #1: Método `getExecutions()` Chamado no Model Errado

**Erro**:
```
Fatal error: Uncaught Error: Call to undefined method App\Models\AIKanbanAgentExecution::getExecutions() 
in /var/www/html/app/Controllers/KanbanAgentController.php:93
```

**Causa**:
- Linha 93 do `KanbanAgentController.php` chama: `AIKanbanAgentExecution::getExecutions($id, 20)`
- Mas o método `getExecutions()` está definido em `AIKanbanAgent` (não em `AIKanbanAgentExecution`)

**Solução**:
- Alterar linha 93 de `AIKanbanAgentExecution::getExecutions($id, 20)` para `AIKanbanAgent::getExecutions($id, 20)`

**Status**: ✅ Corrigido abaixo

---

## 📝 EXEMPLOS DE USO

### Exemplo 1: Followup Automático em "Em Orçamento"

**Cenário**: 
- Enviar followup automático para conversas na etapa "Em Orçamento" que estão há mais de 24 horas sem resposta do cliente

**Configuração do Agente**:
```json
{
  "name": "Followup Em Orçamento",
  "agent_type": "kanban_followup",
  "target_funnel_ids": [1],
  "target_stage_ids": [5],
  "execution_type": "interval",
  "execution_interval_hours": 48,
  "conditions": {
    "operator": "AND",
    "conditions": [
      {
        "type": "conversation_status",
        "operator": "equals",
        "value": "open"
      },
      {
        "type": "client_no_response_minutes",
        "operator": "greater_than",
        "value": 1440
      },
      {
        "type": "last_message_from",
        "operator": "equals",
        "value": "agent"
      }
    ]
  },
  "actions": [
    {
      "type": "send_followup_message",
      "enabled": true,
      "config": {
        "use_ai_generated": false,
        "template": "Olá {contact_name}! Tudo bem? Vi que você estava interessado em nosso orçamento. Posso ajudar com alguma dúvida?"
      }
    },
    {
      "type": "add_tag",
      "enabled": true,
      "config": {
        "tags": ["followup_enviado"]
      }
    }
  ]
}
```

### Exemplo 2: Análise de Oportunidades Paradas

**Cenário**: 
- Analisar conversas que estão há mais de 3 dias na etapa "Negociação" e criar resumo para equipe

**Configuração do Agente**:
```json
{
  "name": "Análise Negociação Parada",
  "agent_type": "kanban_analyzer",
  "target_funnel_ids": [1],
  "target_stage_ids": [6],
  "execution_type": "schedule",
  "execution_schedule": {
    "days": [1, 3, 5],
    "time": "09:00"
  },
  "conditions": {
    "operator": "AND",
    "conditions": [
      {
        "type": "stage_duration_hours",
        "operator": "greater_than",
        "value": 72
      },
      {
        "type": "conversation_status",
        "operator": "equals",
        "value": "open"
      }
    ]
  },
  "actions": [
    {
      "type": "create_summary",
      "enabled": true,
      "config": {
        "summary_type": "internal",
        "include_recommendations": true
      }
    },
    {
      "type": "add_tag",
      "enabled": true,
      "config": {
        "tags": ["analise_automatica"]
      }
    }
  ]
}
```

### Exemplo 3: Reativar Conversas Frias

**Cenário**: 
- Reativar conversas com mais de 5 dias sem resposta movendo para etapa "Follow-up" e atribuindo a agente

**Configuração do Agente**:
```json
{
  "name": "Reativação de Conversas Frias",
  "agent_type": "kanban_manager",
  "target_funnel_ids": null,
  "target_stage_ids": null,
  "execution_type": "interval",
  "execution_interval_hours": 120,
  "conditions": {
    "operator": "AND",
    "conditions": [
      {
        "type": "last_message_hours",
        "operator": "greater_than",
        "value": 120
      },
      {
        "type": "conversation_status",
        "operator": "equals",
        "value": "open"
      }
    ]
  },
  "actions": [
    {
      "type": "move_to_stage",
      "enabled": true,
      "config": {
        "stage_id": 10
      }
    },
    {
      "type": "assign_to_agent",
      "enabled": true,
      "config": {
        "method": "round_robin",
        "department_id": 2
      }
    },
    {
      "type": "create_note",
      "enabled": true,
      "config": {
        "note": "⚠️ Conversa reativada automaticamente após 5 dias de inatividade. Análise: {analysis_summary}",
        "is_internal": true
      }
    }
  ]
}
```

---

## 🚀 COMO USAR O SISTEMA

### 1. Criar um Agente Kanban

1. Acesse `/kanban-agents/create`
2. Preencha:
   - **Nome**: Nome do agente
   - **Descrição**: Descrição do propósito
   - **Tipo**: Escolha o tipo (followup, analyzer, manager, custom)
   - **Prompt**: Prompt para análise com IA
   - **Modelo IA**: Escolha o modelo (gpt-4, gpt-3.5-turbo)
   - **Temperature**: 0.0 (mais preciso) a 2.0 (mais criativo)
   - **Max Tokens**: Limite de tokens na resposta
   - **Funis/Etapas**: Selecione funis e etapas alvo (ou deixe vazio para todos)
   - **Tipo de Execução**: interval, schedule ou manual
   - **Intervalo/Agendamento**: Configure conforme tipo de execução
   - **Condições**: Configure condições usando o builder JSON
   - **Ações**: Configure ações usando o builder JSON
3. Clique em "Salvar"

### 2. Executar Manualmente (Para Testar)

1. Acesse `/kanban-agents`
2. Clique em "Executar" no agente desejado
3. Aguarde a execução
4. Veja o resultado na página de detalhes

### 3. Configurar Execução Automática

**Linux/Mac**:
```bash
# Editar crontab
crontab -e

# Adicionar linha:
*/5 * * * * cd /var/www/html && php public/run-kanban-agents.php >> storage/logs/kanban-agents-cron.log 2>&1
```

**Windows (Task Scheduler)**:
1. Abra o Agendador de Tarefas
2. Criar Nova Tarefa
3. Ação: Executar `php.exe`
4. Argumentos: `C:\path\to\public\run-kanban-agents.php`
5. Disparador: A cada 5 minutos

### 4. Monitorar Execuções

1. Acesse `/kanban-agents/{id}` para ver detalhes de um agente
2. Veja:
   - Últimas execuções (tabela)
   - Logs de ações (tabela com conversas analisadas e ações executadas)
   - Estatísticas (conversas analisadas, ações executadas, erros)

### 5. Testar Condições

1. Acesse a página de detalhes do agente
2. Clique em "Testar Condições"
3. Informe o ID de uma conversa
4. Configure as condições a testar
5. Veja o resultado (atendidas ou não)

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

### Estrutura de Dados
- ✅ Tabela `ai_kanban_agents` criada
- ✅ Tabela `ai_kanban_agent_executions` criada
- ✅ Tabela `ai_kanban_agent_actions_log` criada
- ✅ Foreign keys configuradas
- ✅ Índices otimizados

### Models
- ✅ `AIKanbanAgent` implementado
- ✅ `AIKanbanAgentExecution` implementado
- ✅ `AIKanbanAgentActionLog` implementado
- ✅ Métodos de busca e manipulação
- ✅ Codificação/decodificação JSON automática

### Service
- ✅ `KanbanAgentService` implementado
- ✅ Execução de agentes
- ✅ Análise com OpenAI
- ✅ Avaliação de condições (9 tipos)
- ✅ Execução de ações (9 tipos)
- ✅ Logs e estatísticas

### Controller
- ✅ `KanbanAgentController` implementado
- ✅ CRUD completo
- ✅ Execução manual
- ✅ Teste de condições
- ✅ Dados do sistema para formulários

### Views
- ✅ `index.php` - Listagem
- ✅ `show.php` - Detalhes
- ✅ `create.php` - Criação
- ✅ `edit.php` - Edição

### Rotas
- ✅ 10 rotas configuradas
- ✅ Autenticação configurada
- ✅ Permissões configuradas

### Script de Execução
- ✅ `run-kanban-agents.php` implementado
- ✅ Logs configurados
- ✅ Tratamento de erros

### Integrações
- ✅ OpenAI API
- ✅ Sistema de Conversas
- ✅ Sistema de Mensagens
- ✅ Sistema de Funis
- ✅ Sistema de Tags
- ✅ Sistema de Notas
- ✅ Sistema de Usuários

---

## 🔮 MELHORIAS FUTURAS

### Curto Prazo
1. ✅ Corrigir bug do método `getExecutions()`
2. ⏳ Interface visual para builder de condições e ações
3. ⏳ Mais tipos de condições (tags, departamento, canal, etc)
4. ⏳ Mais tipos de ações (enviar email, webhook, notificar, etc)
5. ⏳ Testes unitários e de integração

### Médio Prazo
1. ⏳ Dashboard com estatísticas dos agentes
2. ⏳ Histórico de mudanças nos agentes (audit log)
3. ⏳ Importar/exportar configurações de agentes
4. ⏳ Templates de agentes predefinidos
5. ⏳ Simulação de execução (dry-run)

### Longo Prazo
1. ⏳ Machine learning para otimizar condições
2. ⏳ A/B testing de prompts e ações
3. ⏳ Análise de sentimento avançada
4. ⏳ Integração com mais modelos de IA (Anthropic Claude, Gemini, etc)
5. ⏳ Sistema de recompensas e gamificação

---

## 📚 DOCUMENTAÇÃO RELACIONADA

- `PLANO_AGENTES_IA_KANBAN.md`: Plano detalhado do sistema
- `RESUMO_EXECUTIVO_AGENTES_KANBAN.md`: Resumo executivo
- `DOCUMENTACAO_AI_AGENTS_E_TOOLS.md`: Documentação dos Agentes de IA para Automações
- `ARQUITETURA.md`: Arquitetura geral do sistema

---

## ℹ️ OBSERVAÇÕES IMPORTANTES

### Diferença entre Agentes de IA

**Agentes de IA para Automações** (Sistema Atual):
- Funcionam em tempo real
- Respondem mensagens automaticamente
- Integrados com sistema de distribuição
- 1 agente por conversa
- Focado em atendimento

**Agentes de IA para Kanban** (Este Sistema):
- Funcionam periodicamente
- Analisam múltiplas conversas
- Não estão integrados com distribuição
- 1 agente analisa N conversas
- Focado em gestão e followup

**São sistemas SEPARADOS e COMPLEMENTARES.**

### Performance

- Cada execução pode processar até 50 conversas (configurável)
- Cada conversa é analisada com até 20 mensagens
- Cada análise consome ~500-2000 tokens (dependendo do prompt e contexto)
- Custo estimado: ~$0.01 a $0.05 por conversa analisada (com GPT-4)
- Tempo estimado: ~2-5 segundos por conversa

### Custos OpenAI

Para 1000 conversas analisadas por mês:
- Com GPT-4: ~$10-50/mês
- Com GPT-3.5-turbo: ~$1-5/mês

Recomendação: Começar com GPT-3.5-turbo para testes e migrar para GPT-4 quando necessário.

---

**Fim da Análise** 📊
