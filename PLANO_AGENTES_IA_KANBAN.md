# 🤖 PLANO DETALHADO - AGENTES DE IA PARA KANBAN

**Data**: 2025-01-27  
**Status**: Planejamento  
**Tipo**: Agentes Especializados para Gestão de Funis e Etapas

---

## 📋 VISÃO GERAL

Sistema de **Agentes de IA Especializados para Kanban** que permite criar agentes virtuais que:
- **Monitoram funis e etapas específicas** do Kanban
- **Executam em intervalos configuráveis** (ex: a cada 2 dias, diariamente, semanalmente)
- **Analisam conversas** de funis/etapas específicas
- **Tomam decisões inteligentes** baseadas em condições configuráveis
- **Executam ações automáticas** (followup, mudança de etapa, resumo, atribuição, etc)

### ⚠️ IMPORTANTE: SEPARAÇÃO DOS AGENTES ATUAIS

**Estes agentes são DIFERENTES dos agentes de IA atuais:**
- **Agentes Atuais**: Funcionam nas **automações** e atendem conversas em tempo real
- **Agentes Kanban**: Funcionam de forma **agendada/periódica** e analisam múltiplas conversas

**Por que separar?**
- Evita quebrar o funcionamento dos agentes atuais
- Permite lógica específica para Kanban
- Facilita manutenção e evolução independente
- Melhor organização e clareza do sistema

---

## 🎯 OBJETIVOS PRINCIPAIS

1. **Monitoramento Inteligente de Funis**
   - Agentes que monitoram funis/etapas específicas
   - Análise periódica de conversas
   - Identificação de oportunidades e problemas

2. **Followup Automático Contextual**
   - Followup baseado em análise de conversa
   - Mensagens personalizadas por contexto
   - Reengajamento inteligente

3. **Gestão Automática de Etapas**
   - Mudança automática de etapa baseada em análise
   - Resumos automáticos de conversas
   - Atribuição inteligente de agentes

4. **Condições e Execuções Flexíveis**
   - Múltiplas condições configuráveis
   - Múltiplas ações executáveis
   - Lógica complexa (AND, OR, NOT)

---

## 🏗️ ARQUITETURA PROPOSTA

### 1. Estrutura de Dados

#### Tabela: `ai_kanban_agents`
```sql
CREATE TABLE ai_kanban_agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    agent_type VARCHAR(50) NOT NULL, -- 'kanban_followup', 'kanban_analyzer', 'kanban_manager', 'kanban_custom'
    prompt TEXT NOT NULL, -- Prompt específico para análise de conversas do Kanban
    model VARCHAR(100) DEFAULT 'gpt-4',
    temperature DECIMAL(3,2) DEFAULT 0.7,
    max_tokens INT DEFAULT 2000,
    enabled BOOLEAN DEFAULT TRUE,
    
    -- Configuração de Funis e Etapas
    target_funnel_ids JSON NULL, -- [1, 2, 3] ou NULL = todos
    target_stage_ids JSON NULL, -- [5, 6, 7] ou NULL = todas as etapas dos funis selecionados
    
    -- Configuração de Execução
    execution_type VARCHAR(50) NOT NULL, -- 'interval', 'schedule', 'manual'
    execution_interval_hours INT NULL, -- Para execution_type = 'interval' (ex: 48 = a cada 2 dias)
    execution_schedule JSON NULL, -- Para execution_type = 'schedule' (ex: {"days": [1,3,5], "time": "09:00"})
    last_execution_at TIMESTAMP NULL,
    next_execution_at TIMESTAMP NULL,
    
    -- Condições de Ativação
    conditions JSON NOT NULL, -- Array de condições (ver seção "Condições")
    
    -- Ações a Executar
    actions JSON NOT NULL, -- Array de ações (ver seção "Ações")
    
    -- Configurações Extras
    settings JSON NULL, -- Configurações específicas do agente
    max_conversations_per_execution INT DEFAULT 50, -- Limite de conversas analisadas por execução
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### Tabela: `ai_kanban_agent_executions`
```sql
CREATE TABLE ai_kanban_agent_executions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ai_kanban_agent_id INT NOT NULL,
    execution_type VARCHAR(50) NOT NULL, -- 'scheduled', 'manual', 'triggered'
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    status VARCHAR(50) DEFAULT 'running', -- 'running', 'completed', 'failed', 'cancelled'
    
    -- Estatísticas
    conversations_analyzed INT DEFAULT 0,
    conversations_acted_upon INT DEFAULT 0,
    actions_executed INT DEFAULT 0,
    errors_count INT DEFAULT 0,
    
    -- Resultados
    results JSON NULL, -- Detalhes da execução
    error_message TEXT NULL,
    
    FOREIGN KEY (ai_kanban_agent_id) REFERENCES ai_kanban_agents(id) ON DELETE CASCADE
);

CREATE INDEX idx_execution_agent ON ai_kanban_agent_executions(ai_kanban_agent_id);
CREATE INDEX idx_execution_status ON ai_kanban_agent_executions(status);
CREATE INDEX idx_execution_started ON ai_kanban_agent_executions(started_at);
```

#### Tabela: `ai_kanban_agent_actions_log`
```sql
CREATE TABLE ai_kanban_agent_actions_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ai_kanban_agent_id INT NOT NULL,
    execution_id INT NOT NULL,
    conversation_id INT NOT NULL,
    
    -- Análise
    analysis_summary TEXT NULL, -- Resumo da análise feita pela IA
    analysis_score DECIMAL(5,2) NULL, -- Score de confiança da análise (0-100)
    
    -- Condições Avaliadas
    conditions_met BOOLEAN DEFAULT FALSE,
    conditions_details JSON NULL, -- Detalhes de quais condições foram atendidas
    
    -- Ações Executadas
    actions_executed JSON NOT NULL, -- Array de ações executadas com resultados
    
    -- Resultado
    success BOOLEAN DEFAULT FALSE,
    error_message TEXT NULL,
    
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (ai_kanban_agent_id) REFERENCES ai_kanban_agents(id) ON DELETE CASCADE,
    FOREIGN KEY (execution_id) REFERENCES ai_kanban_agent_executions(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
);

CREATE INDEX idx_action_log_agent ON ai_kanban_agent_actions_log(ai_kanban_agent_id);
CREATE INDEX idx_action_log_execution ON ai_kanban_agent_actions_log(execution_id);
CREATE INDEX idx_action_log_conversation ON ai_kanban_agent_actions_log(conversation_id);
```

---

## 🔄 FLUXO DE FUNCIONAMENTO

### 1. Execução Periódica do Agente

```
1. Sistema verifica agentes Kanban ativos com next_execution_at <= NOW()
   ↓
2. Para cada agente:
   a) Busca conversas do funil/etapa configurados
   b) Filtra conversas conforme condições básicas (status, tags, etc)
   c) Limita a max_conversations_per_execution
   ↓
3. Cria registro em ai_kanban_agent_executions (status: running)
   ↓
4. Para cada conversa encontrada:
   a) Monta contexto completo da conversa:
      - Histórico de mensagens (últimas 20)
      - Informações do contato
      - Informações do funil/etapa atual
      - Tags e metadados
      - Histórico de atividades
   b) Chama OpenAI com prompt do agente + contexto
   c) IA analisa e retorna:
      - Análise da conversa
      - Score de confiança
      - Recomendações de ações
   d) Sistema avalia condições configuradas
   e) Se condições atendidas:
      - Executa ações configuradas
      - Registra em ai_kanban_agent_actions_log
   ↓
5. Atualiza execution:
   - Status: completed
   - Estatísticas
   - Próxima execução (next_execution_at)
   ↓
6. Notifica via WebSocket (opcional)
```

### 2. Exemplo Prático: Agente de Followup "Em Orçamento"

**Configuração:**
- **Funil**: Comercial
- **Etapa**: Em Orçamento
- **Execução**: A cada 2 dias (48 horas)
- **Condições**:
  - Conversa sem resposta há mais de 24 horas
  - Última mensagem foi do agente (não do contato)
  - Conversa não está fechada
- **Ações**:
  - Analisar contexto da conversa
  - Gerar mensagem de followup personalizada
  - Enviar mensagem ao contato
  - Criar resumo da análise

**Execução:**
```
1. Sistema executa agente (a cada 2 dias)
   ↓
2. Busca conversas:
   - Funil: Comercial
   - Etapa: Em Orçamento
   - Status: open
   ↓
3. Para cada conversa:
   a) Verifica condições:
      - Sem resposta há 24h? ✅
      - Última mensagem do agente? ✅
      - Não está fechada? ✅
   b) Se todas condições atendidas:
      - Monta contexto completo
      - Chama OpenAI com prompt:
        "Analise esta conversa de orçamento. 
         O cliente está interessado? Precisa de followup?
         Gere uma mensagem de followup personalizada."
      - IA retorna análise + mensagem
      - Sistema envia mensagem ao contato
      - Cria resumo da análise
      - Registra ação executada
```

---

## 🎛️ SISTEMA DE CONDIÇÕES

### Estrutura de Condições

```json
{
  "operator": "AND", // AND, OR, NOT
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
    },
    {
      "type": "has_tag",
      "operator": "includes",
      "value": ["orçamento", "interessado"]
    },
    {
      "type": "stage_duration_hours",
      "operator": "greater_than",
      "value": 48
    },
    {
      "type": "conversation_priority",
      "operator": "equals",
      "value": "high"
    },
    {
      "type": "contact_has_field",
      "field": "email",
      "operator": "not_empty"
    },
    {
      "type": "message_count",
      "operator": "greater_than",
      "value": 5
    },
    {
      "type": "ai_analysis_score",
      "operator": "greater_than",
      "value": 70
    },
    {
      "type": "custom_sql",
      "query": "SELECT COUNT(*) FROM messages WHERE conversation_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
      "operator": "greater_than",
      "value": 10
    }
  ]
}
```

### Tipos de Condições Disponíveis

#### 1. Condições de Conversa
- `conversation_status`: Status da conversa (open, closed, resolved, etc)
- `conversation_priority`: Prioridade (low, normal, high, urgent)
- `conversation_assigned`: Se está atribuída a agente específico
- `conversation_unassigned`: Se não está atribuída
- `stage_duration_hours`: Tempo no estágio atual (horas)
- `funnel_id`: ID do funil
- `stage_id`: ID do estágio

#### 2. Condições de Mensagens
- `last_message_hours`: Horas desde última mensagem
- `last_message_from`: Última mensagem de quem (contact, agent, system)
- `message_count`: Total de mensagens na conversa
- `message_count_last_24h`: Mensagens nas últimas 24h
- `has_unread_messages`: Tem mensagens não lidas

#### 3. Condições de Tags
- `has_tag`: Tem tag específica
- `has_all_tags`: Tem todas as tags especificadas
- `has_any_tag`: Tem qualquer uma das tags
- `missing_tag`: Não tem tag específica
- `has_blocked_tag`: Tem tag bloqueada

#### 4. Condições de Contato
- `contact_has_field`: Contato tem campo preenchido (email, phone, etc)
- `contact_field_equals`: Campo do contato igual a valor
- `contact_created_days`: Dias desde criação do contato
- `contact_last_contact_days`: Dias desde último contato

#### 5. Condições de Análise IA
- `ai_analysis_score`: Score de análise da IA (0-100)
- `ai_sentiment`: Sentimento detectado (positive, neutral, negative)
- `ai_urgency`: Urgência detectada (low, medium, high)
- `ai_recommendation`: Recomendação da IA (followup, escalate, close, etc)

#### 6. Condições Customizadas
- `custom_sql`: Query SQL customizada (retorna número)
- `custom_php`: Código PHP customizado (retorna boolean)

### Operadores Disponíveis

- `equals`: Igual a
- `not_equals`: Diferente de
- `greater_than`: Maior que
- `less_than`: Menor que
- `greater_or_equal`: Maior ou igual
- `less_or_equal`: Menor ou igual
- `includes`: Contém (para arrays)
- `not_includes`: Não contém (para arrays)
- `is_empty`: Está vazio
- `not_empty`: Não está vazio
- `matches_regex`: Corresponde a regex
- `between`: Entre dois valores

---

## ⚡ SISTEMA DE AÇÕES

### Estrutura de Ações

```json
{
  "actions": [
    {
      "type": "analyze_conversation",
      "enabled": true,
      "config": {
        "include_summary": true,
        "include_sentiment": true,
        "include_recommendations": true
      }
    },
    {
      "type": "send_followup_message",
      "enabled": true,
      "config": {
        "use_ai_generated": true,
        "template": "Olá {contact_name}, vi que você estava interessado em {product_name}. Posso ajudar com alguma dúvida?",
        "channel": "whatsapp"
      }
    },
    {
      "type": "move_to_stage",
      "enabled": true,
      "config": {
        "stage_id": 10,
        "add_note": true,
        "note_template": "Movido automaticamente após análise: {analysis_summary}"
      }
    },
    {
      "type": "assign_to_agent",
      "enabled": true,
      "config": {
        "method": "round_robin",
        "department_id": 2,
        "priority": "high"
      }
    },
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
        "tags": ["followup_enviado", "analisado_ia"]
      }
    },
    {
      "type": "update_priority",
      "enabled": true,
      "config": {
        "priority": "high"
      }
    },
    {
      "type": "create_note",
      "enabled": true,
      "config": {
        "note": "Análise automática: {analysis_summary}",
        "is_internal": true
      }
    },
    {
      "type": "trigger_automation",
      "enabled": true,
      "config": {
        "automation_id": 5
      }
    },
    {
      "type": "send_notification",
      "enabled": true,
      "config": {
        "recipients": ["supervisor", "assigned_agent"],
        "message": "Conversa precisa de atenção: {analysis_summary}"
      }
    }
  ]
}
```

### Tipos de Ações Disponíveis

#### 1. Análise e Processamento
- `analyze_conversation`: Analisa conversa com IA e retorna insights
- `create_summary`: Cria resumo da conversa (interno ou para contato)
- `extract_information`: Extrai informações importantes da conversa

#### 2. Mensagens
- `send_followup_message`: Envia mensagem de followup ao contato
- `send_template_message`: Envia mensagem usando template
- `send_ai_generated_message`: Gera e envia mensagem usando IA

#### 3. Movimentação de Etapas
- `move_to_stage`: Move conversa para etapa específica
- `move_to_next_stage`: Move para próxima etapa do funil
- `move_to_previous_stage`: Move para etapa anterior
- `move_to_funnel`: Move para funil diferente

#### 4. Atribuição
- `assign_to_agent`: Atribui conversa a agente específico
- `assign_to_department`: Atribui a departamento (distribuição automática)
- `unassign`: Remove atribuição

#### 5. Tags e Metadados
- `add_tag`: Adiciona tags à conversa
- `remove_tag`: Remove tags da conversa
- `update_priority`: Atualiza prioridade da conversa
- `update_custom_field`: Atualiza campo customizado

#### 6. Notas e Atividades
- `create_note`: Cria nota interna ou externa
- `create_activity`: Cria atividade no histórico
- `add_internal_comment`: Adiciona comentário interno

#### 7. Automações e Integrações
- `trigger_automation`: Dispara automação específica
- `call_webhook`: Chama webhook externo
- `execute_custom_action`: Executa ação customizada (PHP)

#### 8. Notificações
- `send_notification`: Envia notificação a usuários
- `send_email`: Envia email
- `create_alert`: Cria alerta no sistema

### Ordem de Execução

As ações são executadas **sequencialmente** na ordem definida. Se uma ação falhar:
- **continue_on_error**: Continua executando próximas ações
- **stop_on_error**: Para execução (padrão)

---

## 📊 EXEMPLOS DE CONFIGURAÇÃO

### Exemplo 1: Agente de Followup "Em Orçamento"

```json
{
  "name": "Followup - Em Orçamento",
  "description": "A cada 2 dias, analisa conversas em orçamento e envia followup",
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
  },
  "actions": [
    {
      "type": "analyze_conversation",
      "enabled": true,
      "config": {
        "include_summary": true,
        "include_recommendations": true
      }
    },
    {
      "type": "send_followup_message",
      "enabled": true,
      "config": {
        "use_ai_generated": true,
        "channel": "whatsapp"
      }
    },
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
        "tags": ["followup_enviado"]
      }
    }
  ]
}
```

### Exemplo 2: Agente de Análise e Movimentação

```json
{
  "name": "Analisador - Qualificação para Proposta",
  "description": "Diariamente analisa conversas em Qualificação e move para Proposta se pronto",
  "agent_type": "kanban_analyzer",
  "target_funnel_ids": [1],
  "target_stage_ids": [3],
  "execution_type": "schedule",
  "execution_schedule": {
    "days": [1, 2, 3, 4, 5],
    "time": "09:00"
  },
  "conditions": {
    "operator": "AND",
    "conditions": [
      {
        "type": "conversation_status",
        "operator": "equals",
        "value": "open"
      },
      {
        "type": "stage_duration_hours",
        "operator": "greater_than",
        "value": 24
      },
      {
        "type": "ai_analysis_score",
        "operator": "greater_than",
        "value": 80
      }
    ]
  },
  "actions": [
    {
      "type": "analyze_conversation",
      "enabled": true,
      "config": {
        "include_summary": true,
        "include_recommendations": true,
        "check_readiness": true
      }
    },
    {
      "type": "move_to_stage",
      "enabled": true,
      "config": {
        "stage_id": 4,
        "add_note": true,
        "note_template": "Movido automaticamente após análise IA. Score: {analysis_score}. Resumo: {analysis_summary}"
      },
      "conditions": {
        "ai_recommendation": "move_to_proposal"
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
      "type": "create_summary",
      "enabled": true,
      "config": {
        "summary_type": "internal"
      }
    }
  ]
}
```

### Exemplo 3: Agente de Resumo e Atribuição

```json
{
  "name": "Resumidor - Conversas Antigas",
  "description": "Semanalmente cria resumos de conversas antigas e atribui a agentes",
  "agent_type": "kanban_manager",
  "target_funnel_ids": null,
  "target_stage_ids": null,
  "execution_type": "schedule",
  "execution_schedule": {
    "days": [1],
    "time": "08:00"
  },
  "conditions": {
    "operator": "AND",
    "conditions": [
      {
        "type": "conversation_status",
        "operator": "equals",
        "value": "open"
      },
      {
        "type": "stage_duration_hours",
        "operator": "greater_than",
        "value": 168
      },
      {
        "type": "conversation_unassigned",
        "operator": "equals",
        "value": true
      }
    ]
  },
  "actions": [
    {
      "type": "analyze_conversation",
      "enabled": true,
      "config": {
        "include_summary": true,
        "include_key_points": true
      }
    },
    {
      "type": "create_summary",
      "enabled": true,
      "config": {
        "summary_type": "internal",
        "format": "detailed"
      }
    },
    {
      "type": "assign_to_department",
      "enabled": true,
      "config": {
        "method": "by_load",
        "department_id": 1
      }
    },
    {
      "type": "update_priority",
      "enabled": true,
      "config": {
        "priority": "normal"
      }
    },
    {
      "type": "add_tag",
      "enabled": true,
      "config": {
        "tags": ["resumido_ia", "atribuido_automatico"]
      }
    }
  ]
}
```

---

## 🛠️ COMPONENTES A IMPLEMENTAR

### 1. Models

- `AIKanbanAgent.php` - Model para agentes Kanban
- `AIKanbanAgentExecution.php` - Model para execuções
- `AIKanbanAgentActionLog.php` - Model para logs de ações

### 2. Services

- `AIKanbanAgentService.php` - CRUD e lógica de negócio
- `AIKanbanExecutionService.php` - Lógica de execução dos agentes
- `AIKanbanConditionService.php` - Avaliação de condições
- `AIKanbanActionService.php` - Execução de ações

### 3. Controllers

- `AIKanbanAgentController.php` - Endpoints para gerenciar agentes
- `AIKanbanExecutionController.php` - Endpoints para execuções e logs

### 4. Jobs (Background Processing)

- `ExecuteKanbanAgentsJob.php` - Job que executa agentes agendados
- `ProcessKanbanConversationJob.php` - Job para processar conversa individual

### 5. Migrations

- `065_create_ai_kanban_agents_table.php`
- `066_create_ai_kanban_agent_executions_table.php`
- `067_create_ai_kanban_agent_actions_log_table.php`

### 6. Views

- `views/ai-kanban-agents/index.php` - Lista de agentes
- `views/ai-kanban-agents/create.php` - Criar agente
- `views/ai-kanban-agents/edit.php` - Editar agente
- `views/ai-kanban-agents/show.php` - Detalhes do agente
- `views/ai-kanban-agents/executions.php` - Histórico de execuções
- `views/ai-kanban-agents/actions-log.php` - Log de ações

---

## 🔄 INTEGRAÇÃO COM SISTEMA ATUAL

### 1. Integração com Funis

- Usa tabelas existentes: `funnels`, `funnel_stages`, `conversations`
- Usa `FunnelService::moveConversation()` para mover conversas
- Usa `Funnel::getConversationsByStage()` para buscar conversas

### 2. Integração com OpenAI

- Usa `OpenAIService` existente para chamadas à API
- Cria contexto específico para análise de conversas do Kanban
- Usa mesmo sistema de tools (se necessário)

### 3. Integração com Automações

- Pode disparar automações existentes via ação `trigger_automation`
- Não interfere com automações atuais

### 4. Integração com WebSocket

- Notifica execuções em tempo real
- Notifica ações executadas

---

## 🚀 IMPLEMENTAÇÃO POR FASES

### Fase 1: Estrutura Base (Semana 1-2)

**Objetivo**: Criar estrutura de dados e models básicos

**Tarefas**:
1. ✅ Criar migrations das tabelas
2. ✅ Criar Models básicos
3. ✅ Criar Service básico (CRUD)
4. ✅ Criar Controller básico
5. ✅ Criar rotas

**Entregáveis**:
- Tabelas criadas
- Models funcionando
- CRUD básico funcionando

### Fase 2: Sistema de Condições (Semana 2-3)

**Objetivo**: Implementar avaliação de condições

**Tarefas**:
1. ✅ Criar `AIKanbanConditionService`
2. ✅ Implementar todos os tipos de condições
3. ✅ Implementar operadores (AND, OR, NOT)
4. ✅ Testes de condições

**Entregáveis**:
- Sistema de condições funcionando
- Testes passando

### Fase 3: Sistema de Ações (Semana 3-4)

**Objetivo**: Implementar execução de ações

**Tarefas**:
1. ✅ Criar `AIKanbanActionService`
2. ✅ Implementar todos os tipos de ações
3. ✅ Integração com serviços existentes
4. ✅ Testes de ações

**Entregáveis**:
- Sistema de ações funcionando
- Integrações funcionando

### Fase 4: Sistema de Execução (Semana 4-5)

**Objetivo**: Implementar execução periódica dos agentes

**Tarefas**:
1. ✅ Criar `AIKanbanExecutionService`
2. ✅ Criar Job de execução periódica
3. ✅ Integração com OpenAI para análise
4. ✅ Sistema de agendamento
5. ✅ Logs e rastreamento

**Entregáveis**:
- Execução periódica funcionando
- Logs completos
- Agendamento funcionando

### Fase 5: Interface Completa (Semana 5-6)

**Objetivo**: Criar todas as interfaces de usuário

**Tarefas**:
1. ✅ Página de listagem de agentes
2. ✅ Página de criação/edição
3. ✅ Interface de configuração de condições
4. ✅ Interface de configuração de ações
5. ✅ Página de execuções e logs
6. ✅ Dashboard de estatísticas

**Entregáveis**:
- Todas as interfaces funcionando
- UX completa e intuitiva

### Fase 6: Testes e Melhorias (Semana 6-7)

**Objetivo**: Testes completos e melhorias

**Tarefas**:
1. ✅ Testes de integração
2. ✅ Testes de performance
3. ✅ Otimizações
4. ✅ Documentação final
5. ✅ Treinamento

**Entregáveis**:
- Sistema completo e testado
- Documentação completa

---

## 💡 MELHORIAS FUTURAS

### 1. Machine Learning

- Aprender padrões de quando seguir recomendações da IA
- Ajustar automaticamente condições baseado em resultados
- Otimizar ações baseado em taxa de sucesso

### 2. Análise Avançada

- Análise de sentimento mais profunda
- Detecção de intenção de compra
- Previsão de conversão
- Análise de risco de perda

### 3. Ações Mais Inteligentes

- Sugestões de ações baseadas em análise
- A/B testing de mensagens de followup
- Otimização automática de timing

### 4. Integração com RAG

- Usar conhecimento da base RAG para análise
- Melhorar recomendações com contexto histórico
- Aprender com feedbacks anteriores

---

## 📈 MÉTRICAS E ANALYTICS

### Métricas de Agente

- Total de execuções
- Conversas analisadas
- Conversas com ações executadas
- Taxa de sucesso de ações
- Tempo médio de execução
- Custo por execução (tokens OpenAI)

### Métricas de Ações

- Ações mais executadas
- Taxa de sucesso por tipo de ação
- Impacto nas conversas (movimentações, atribuições, etc)
- ROI das ações

### Métricas de Condições

- Condições mais frequentes
- Taxa de ativação (quantas vezes condições foram atendidas)
- Efetividade das condições

---

## 🔒 SEGURANÇA E VALIDAÇÃO

### Validações Necessárias

1. **Condições**:
   - Validar estrutura JSON
   - Validar tipos de condições
   - Validar operadores
   - Sanitizar valores de condições SQL customizadas

2. **Ações**:
   - Validar estrutura JSON
   - Validar tipos de ações
   - Validar permissões antes de executar ações
   - Rate limiting de ações

3. **Execução**:
   - Limitar número de conversas por execução
   - Timeout de execução
   - Prevenir execuções simultâneas do mesmo agente
   - Logs de todas as ações

---

## 💰 ESTIMATIVA DE CUSTOS

### OpenAI API

**Custo por Execução**:
- 50 conversas analisadas
- ~2000 tokens por conversa (contexto + análise)
- Total: 100K tokens por execução
- Custo (GPT-4): ~$3.00 por execução

**Custo Mensal Estimado**:
- Agente executando a cada 2 dias = 15 execuções/mês
- 15 execuções × $3.00 = **$45/mês por agente**

**Otimizações**:
- Usar GPT-3.5-turbo para análises simples (~$0.30 por execução)
- Cachear análises de conversas recentes
- Limitar contexto histórico

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

### Infraestrutura
- [ ] Migrations criadas e executadas
- [ ] Models criados
- [ ] Services criados
- [ ] Controllers criados
- [ ] Rotas configuradas

### Backend
- [ ] Sistema de condições implementado
- [ ] Sistema de ações implementado
- [ ] Sistema de execução implementado
- [ ] Integração com OpenAI
- [ ] Integração com Funis
- [ ] Jobs de background criados

### Frontend
- [ ] Página de listagem
- [ ] Página de criação/edição
- [ ] Interface de condições
- [ ] Interface de ações
- [ ] Página de execuções
- [ ] Página de logs
- [ ] Dashboard de estatísticas

### Testes
- [ ] Testes unitários
- [ ] Testes de integração
- [ ] Testes de performance
- [ ] Testes de segurança

---

**Última atualização**: 2025-01-27  
**Versão do Plano**: 1.0

