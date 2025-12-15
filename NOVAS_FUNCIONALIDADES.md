# 🆕 NOVAS FUNCIONALIDADES PLANEJADAS

**Data**: 2025-01-27

---

## 📋 ÍNDICE

1. [Configurações Avançadas de Conversas](#1-configurações-avançadas-de-conversas)
2. [Sistema de Agentes de IA](#2-sistema-de-agentes-de-ia)

---

## 1. CONFIGURAÇÕES AVANÇADAS DE CONVERSAS

### 📊 Visão Geral

Sistema completo de configurações para gerenciar conversas de forma avançada, incluindo limites, SLA, distribuição inteligente, reatribuição automática e priorização.

### 🎯 Funcionalidades Principais

#### 1.1 Limites e Capacidade
- Max conversas abertas por agente (global e por setor/funil/prioridade)
- Max conversas sem resposta por setor
- Max conversas por estágio/funil
- Limites por tipo de canal e horário

#### 1.2 SLA e Timeouts
- SLA de resposta (configurável por prioridade, setor, funil, canal, horário)
- SLA de resolução
- Timeouts de inatividade
- Alertas antes/depois do SLA

#### 1.3 Distribuição e Atribuição
- Métodos: Round-Robin, Por Carga, Por Especialidade, Por Performance
- Distribuição percentual por agente/setor
- Regras de atribuição (online, disponível, horário, capacidade)
- Balanceamento automático

#### 1.4 Reatribuição Automática
- Reatribuição após SLA excedido
- Reatribuição por inatividade
- Reatribuição por condições (tags, prioridade, estágio)
- Regras de reatribuição (máximo, tempo mínimo, condições)

#### 1.5 Priorização e Filas
- Níveis de prioridade (baixa, normal, alta, urgente)
- Critérios de priorização automática
- Ordenação de filas (prioridade + SLA, data, atividade)

### 📁 Estrutura de Dados

Armazenado em tabela `settings` com chave `conversation_settings`:

```json
{
  "max_conversations_per_agent": 10,
  "max_conversations_per_agent_by_department": {},
  "max_conversations_per_agent_by_funnel": {},
  "sla_response_time_minutes": 30,
  "sla_response_time_by_priority": {},
  "distribution_method": "round_robin",
  "distribution_percentages_by_agent": {},
  "auto_reassign_after_sla": true,
  "auto_reassign_after_sla_minutes": 15,
  "priority_levels": ["low", "normal", "high", "urgent"],
  "auto_balance_enabled": false
}
```

### 🔗 Integração

- Integração com `ConversationService`
- Integração com sistema de distribuição existente
- Integração com sistema de notificações
- Interface na página de Configurações (nova aba "Conversas")

---

## 2. SISTEMA DE AGENTES DE IA

### 📊 Visão Geral

Sistema completo de agentes de IA que permite criar agentes virtuais especializados (SDR, CS, CLOSER, etc) com prompts personalizados e tools (ferramentas) específicas. Integração com OpenAI para processamento de conversas e execução de ações através de tools.

### 🎯 Funcionalidades Principais

#### 2.1 Agentes de IA
- Criação de agentes especializados por função
- Configuração de prompts personalizados
- Configuração de modelo (GPT-4, GPT-3.5-turbo, etc)
- Configuração de temperatura e max_tokens
- Atribuição de tools específicas
- Configurações de comportamento (auto-responder, escalação, etc)

#### 2.2 Sistema de Tools
- Tools extensíveis e configuráveis
- Validação de segurança
- Execução assíncrona quando necessário
- Logs de execução

**Tipos de Tools Disponíveis**:

**A. WooCommerce Tools**
- `buscar_pedido_woocommerce` - Busca pedido por ID
- `buscar_produto_woocommerce` - Busca produto por ID/SKU/nome
- `criar_pedido_woocommerce` - Cria novo pedido
- `atualizar_status_pedido` - Atualiza status do pedido

**B. Database Tools**
- `consultar_banco_dados` - Consulta SQL segura em tabelas específicas

**C. N8N Tools**
- `executar_workflow_n8n` - Executa workflow via webhook
- `buscar_dados_n8n` - Busca dados de fontes externas

**D. Document Tools**
- `buscar_documento` - Busca em documentos (PDF, DOCX)
- `extrair_texto_documento` - Extrai texto de documento específico

**E. System Tools**
- `buscar_conversas_anteriores` - Busca histórico do contato
- `buscar_informacoes_contato` - Busca dados completos do contato
- `adicionar_tag_conversa` - Adiciona tag à conversa
- `mover_para_estagio` - Move conversa para outro estágio
- `escalar_para_humano` - Escala conversa para agente humano

**F. API Tools**
- `chamar_api_externa` - Chama API externa customizada

#### 2.3 Integração com OpenAI
- Function Calling (tools)
- Processamento de prompts
- Tratamento de erros
- Rate limiting
- Controle de custos

#### 2.4 Logs e Analytics
- Registro de todas as interações
- Tools utilizadas por conversa
- Tokens consumidos
- Custo por conversa
- Taxa de escalação
- Tempo médio de resposta

### 📁 Estrutura de Dados

#### Tabelas Necessárias

**ai_agents**
- `id`, `name`, `role`, `description`, `prompt`, `model`, `temperature`, `max_tokens`, `settings` (JSON), `is_active`

**ai_tools**
- `id`, `name`, `display_name`, `description`, `type`, `category`, `schema` (JSON), `config` (JSON), `is_active`

**ai_agent_tools**
- `agent_id`, `tool_id`

**ai_conversations**
- `id`, `conversation_id`, `agent_id`, `message_id`, `ai_response_id`, `prompt_used`, `tools_called` (JSON), `tokens_used`, `cost`, `execution_time_ms`

### 🔄 Fluxo de Funcionamento

```
1. Conversa atribuída a Agente de IA
   ↓
2. Busca contexto (mensagens, contato)
   ↓
3. Monta prompt com instruções e tools
   ↓
4. Chama OpenAI API com function calling
   ↓
5. Executa tools chamadas
   ↓
6. Reenvia para OpenAI com resultados
   ↓
7. Envia resposta final
   ↓
8. Registra logs (tokens, custo, tools)
```

### 🔗 Integração com Distribuição

- Agentes de IA podem ser selecionados na distribuição automática
- Configuração por setor, funil, tags, horário
- Percentual de distribuição (X% IA, Y% humanos)

### ⚠️ Considerações de Segurança

1. **Validação de Tools**
   - Sempre validar parâmetros antes de executar
   - Sanitizar inputs para prevenir SQL injection
   - Limitar acesso a tabelas/recursos sensíveis

2. **Rate Limiting**
   - Limitar número de chamadas por agente
   - Limitar tokens por conversa
   - Prevenir abuso da API

3. **Logs e Auditoria**
   - Registrar todas as chamadas de tools
   - Registrar custos e uso de tokens
   - Permitir rastreamento de ações

4. **Escalação**
   - Sempre permitir escalação para humano
   - Detectar situações que requerem intervenção humana
   - Não bloquear acesso humano

### 📈 Melhorias Futuras

- Sistema de memória/contexto
- Fallback e escalação inteligente
- Análise de sentimento
- A/B Testing de agentes
- Cache de tools
- Sistema de memória persistente

---

## 🎯 PRIORIDADES DE IMPLEMENTAÇÃO

### Fase 1 - Configurações Avançadas de Conversas
1. Criar estrutura de dados (settings)
2. Implementar lógica de limites
3. Implementar SLA e timeouts
4. Implementar distribuição avançada
5. Implementar reatribuição automática
6. Criar interface de configuração

### Fase 2 - Sistema de Agentes de IA
1. Criar migrations (tabelas)
2. Criar Models (AIAgent, AITool, AIConversation)
3. Criar Services (AIAgentService, AIToolService, OpenAIService)
4. Implementar System Tools básicas
5. Implementar integração com OpenAI
6. Criar Controllers e Views
7. Implementar Tools externas (WooCommerce, Database, N8N)
8. Integrar com sistema de distribuição
9. Implementar logs e analytics

---

## 📚 DOCUMENTAÇÃO RELACIONADA

- `PROGRESSO_AGENTES_IA.md` - Progresso detalhado do sistema de IA
- `FUNCIONALIDADES_PENDENTES.md` - Lista completa de funcionalidades
- `CONTEXT_IA.md` - Contexto geral do sistema
- `ARQUITETURA.md` - Arquitetura técnica

---

---

## ✅ PROGRESSO DE IMPLEMENTAÇÃO

### Sistema de Agentes de IA - Status: 40% Completo

**Implementado**:
- ✅ Estrutura de dados (migrations, models, services, controllers)
- ✅ Interface de listagem e visualização de agentes e tools
- ✅ **Interface dinâmica de criação/edição de tools** (2025-01-27):
  - Campos específicos por tipo (sem JSON manual)
  - Interface para Function Schema
  - Construção automática de JSON
  - Preenchimento automático ao editar

**Pendente**:
- ⏳ Service OpenAIService (integração com OpenAI)
- ⏳ Interface de criação/edição de agentes
- ⏳ Sistema de execução de tools
- ⏳ Integração com distribuição de conversas
- ⏳ Sistema de Followup Automático

### Configurações Avançadas de Conversas - Status: 0% Completo

**Pendente**:
- ⏳ Toda a implementação (planejada)

---

**Última atualização**: 2025-01-27

---

## 🆕 FUNCIONALIDADES IMPLEMENTADAS (2025-01-27)

### ✅ Página de Analytics Completa
- Nova página `/analytics` com 8 abas completas
- Métricas de Conversas, Agentes, Sentimento, SLA, Tags, Funil, Automações e IA
- Comparação temporal automática
- Gráficos interativos com ApexCharts
- Carregamento sob demanda por aba
- Filtros avançados

### ✅ Análise de Sentimento
- Sistema completo de análise usando OpenAI
- Configurações avançadas
- Controle de custos
- Tag automática para sentimento negativo
- Exibição no sidebar
- Script de processamento em background

### ✅ Histórico do Contato
- Aba "Histórico" na sidebar da conversa
- Estatísticas do contato
- Listagem de conversas anteriores

### ✅ Timeline de Atividades
- Exibição de atividades na sidebar
- Logging automático de ações importantes
- Filtros por tipo de atividade

---

