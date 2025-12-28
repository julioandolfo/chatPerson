# ⏳ PROGRESSO - SISTEMA DE AGENTES DE IA

**Data**: 2025-01-27  
**Status**: 95% Completo (era 40%)

---

## ⚠️ IMPORTANTE: TIPOS DE AGENTES

Este sistema possui **DOIS TIPOS** de agentes de IA:

1. **Agentes de IA para Automações** (este documento)
   - Funcionam nas automações
   - Atendem conversas em tempo real
   - **Status**: 95% implementado

2. **Agentes de IA para Kanban** (documento separado)
   - Funcionam de forma agendada/periódica
   - Analisam múltiplas conversas de funis/etapas específicas
   - **Status**: Planejamento (ver `PLANO_AGENTES_IA_KANBAN.md`)

---

## 📋 VISÃO GERAL

Sistema de Agentes de IA que permite criar agentes virtuais especializados (SDR, CS, CLOSER, etc) com prompts personalizados e tools (ferramentas) específicas. Integração com OpenAI para processamento de conversas e execução de ações através de tools.

---

## 🎯 OBJETIVOS

1. Criar agentes de IA especializados por função
2. Permitir configuração de prompts personalizados
3. Sistema de tools extensível (WooCommerce, Database, N8N, Documents, etc)
4. Integração com sistema de distribuição de conversas
5. **Sistema de Followup Automático com IA**:
   - Agentes especializados em followup de conversas
   - Verificação automática de status após X tempo
   - Reengajamento de contatos inativos
   - Followup de leads frios
   - Verificação de satisfação pós-atendimento
6. Logs e analytics de uso
7. Controle de custos e rate limiting

---

## 📊 ESTRUTURA DE DADOS NECESSÁRIA

### Tabelas a Criar

#### 1. `ai_agents`
```sql
CREATE TABLE ai_agents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    role VARCHAR(50), -- sdr, cs, closer, support, onboarding, custom
    description TEXT,
    prompt TEXT NOT NULL,
    model VARCHAR(50) DEFAULT 'gpt-4',
    temperature DECIMAL(3,2) DEFAULT 0.7,
    max_tokens INT DEFAULT 1000,
    settings JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 2. `ai_tools`
```sql
CREATE TABLE ai_tools (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    display_name VARCHAR(255),
    description TEXT,
    type VARCHAR(50), -- woocommerce, database, n8n, webhook, document, system, api
    category VARCHAR(50),
    schema JSON NOT NULL, -- Schema OpenAI Function Calling
    config JSON, -- Configurações específicas da tool
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 3. `ai_agent_tools`
```sql
CREATE TABLE ai_agent_tools (
    agent_id INT,
    tool_id INT,
    PRIMARY KEY (agent_id, tool_id),
    FOREIGN KEY (agent_id) REFERENCES ai_agents(id),
    FOREIGN KEY (tool_id) REFERENCES ai_tools(id)
);
```

#### 4. `ai_conversations`
```sql
CREATE TABLE ai_conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    agent_id INT NOT NULL,
    message_id INT, -- Mensagem do contato
    ai_response_id INT, -- Mensagem da IA
    prompt_used TEXT,
    tools_called JSON, -- Tools que foram chamadas
    tokens_used INT,
    cost DECIMAL(10,4),
    execution_time_ms INT,
    created_at TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    FOREIGN KEY (agent_id) REFERENCES ai_agents(id)
);
```

---

## 🔧 COMPONENTES A IMPLEMENTAR

### 1. Models
- [x] `app/Models/AIAgent.php` ✅
- [x] `app/Models/AITool.php` ✅
- [ ] `app/Models/AIConversation.php` (não necessário ainda)

### 2. Services
- [x] `app/Services/AIAgentService.php` ✅
  - ✅ CRUD de agentes
  - ✅ Validações
  - ✅ Atribuição de tools
  
- [x] `app/Services/AIToolService.php` ✅
  - ✅ CRUD de tools
  - ✅ Validação de schemas
  - ⚠️ Execução de tools (pendente)
  
- [ ] `app/Services/OpenAIService.php`
  - Integração com OpenAI API
  - Processamento de prompts
  - Function calling
  - Tratamento de erros
  - Rate limiting

### 3. Controllers
- [x] `app/Controllers/AIAgentController.php` ✅
- [x] `app/Controllers/AIToolController.php` ✅

### 4. Views
- [x] `views/ai-agents/index.php` - Lista de agentes ✅
- [ ] `views/ai-agents/create.php` - Criar agente (modal no index)
- [ ] `views/ai-agents/edit.php` - Editar agente (modal no show)
- [x] `views/ai-agents/show.php` - Detalhes do agente ✅
- [x] `views/ai-tools/index.php` - Lista de tools ✅
- [x] `views/ai-tools/create.php` - Criar tool (modal dinâmico no index) ✅
- [x] `views/ai-tools/edit.php` - Editar tool (modal dinâmico no show) ✅
- ✅ **Interface dinâmica implementada** (2025-01-27):
  - Campos específicos por tipo de tool (sem JSON manual)
  - Interface para Function Schema (nome, descrição, parâmetros)
  - Campos de configuração dinâmicos por tipo
  - Construção automática de JSON
  - Preenchimento automático ao editar

### 5. Migrations
- [x] `database/migrations/029_create_ai_agents_table.php` ✅
- [x] `database/migrations/030_create_ai_tools_table.php` ✅
- [x] `database/migrations/031_create_ai_agent_tools_table.php` ✅
- [x] `database/migrations/032_create_ai_conversations_table.php` ✅
- [x] `database/seeds/003_create_default_ai_tools.php` ✅

---

## 🛠️ TOOLS A IMPLEMENTAR

### WooCommerce Tools
- [ ] `buscar_pedido_woocommerce` - Busca pedido por ID
- [ ] `buscar_produto_woocommerce` - Busca produto por ID/SKU/nome
- [ ] `criar_pedido_woocommerce` - Cria novo pedido
- [ ] `atualizar_status_pedido` - Atualiza status do pedido

### Database Tools
- [ ] `consultar_banco_dados` - Consulta SQL segura em tabelas específicas
  - Validação de segurança (read-only, tabelas permitidas)
  - Sanitização de inputs
  - Prevenção de SQL injection

### N8N Tools
- [ ] `executar_workflow_n8n` - Executa workflow via webhook
- [ ] `buscar_dados_n8n` - Busca dados de fontes externas

### Document Tools
- [ ] `buscar_documento` - Busca em documentos (PDF, DOCX)
- [ ] `extrair_texto_documento` - Extrai texto de documento específico

### System Tools
- [ ] `buscar_conversas_anteriores` - Busca histórico do contato
- [ ] `buscar_informacoes_contato` - Busca dados completos do contato
- [ ] `adicionar_tag_conversa` - Adiciona tag à conversa
- [ ] `mover_para_estagio` - Move conversa para outro estágio
- [ ] `escalar_para_humano` - Escala conversa para agente humano

### Followup Tools (NOVO)
- [ ] `verificar_status_conversa` - Verifica status atual da conversa
- [ ] `verificar_ultima_interacao` - Verifica última interação do contato
- [ ] `reengajar_contato` - Envia mensagem de reengajamento
- [ ] `verificar_satisfacao` - Verifica satisfação pós-atendimento
- [ ] `verificar_leads_frios` - Identifica leads que não interagem há X tempo
- [ ] `agendar_followup` - Agenda followup futuro para a conversa

### API Tools
- [ ] `chamar_api_externa` - Chama API externa customizada

---

## 🔄 FLUXO DE FUNCIONAMENTO

### 1. Quando uma conversa é atribuída a um Agente de IA

```
1. Nova mensagem recebida
   ↓
2. Sistema verifica configurações de distribuição
   ↓
3. Se configurado para usar Agente de IA:
   - Seleciona agente baseado em:
     * Role (SDR, CS, CLOSER)
     * Tags da conversa
     * Setor
     * Regras de distribuição
   ↓
4. Atribui conversa ao Agente de IA
   ↓
5. Agente de IA processa mensagem:
   a) Busca contexto (últimas mensagens, informações do contato)
   b) Monta prompt com:
      - Instruções do agente (prompt personalizado)
      - Histórico da conversa
      - Tools disponíveis
      - Informações do contato
   c) Chama OpenAI API com:
      - Model configurado
      - Temperature configurada
      - Tools disponíveis (function calling)
   d) OpenAI retorna:
      - Resposta do assistente
      - Chamadas de tools (se houver)
   e) Sistema executa tools chamadas:
      - Busca pedido WooCommerce
      - Consulta banco de dados
      - Chama N8N workflow
      - etc
   f) Se tools foram chamadas:
      - Reenvia para OpenAI com resultados das tools
      - OpenAI gera resposta final usando informações das tools
   g) Envia resposta ao contato
   ↓
6. Registra interação:
   - Mensagem do contato
   - Resposta da IA
   - Tools utilizadas
   - Tokens consumidos
   - Custo da API
```

### 2. Sistema de Followup Automático com Agentes de IA

```
1. Sistema verifica conversas que precisam de followup:
   - Conversas fechadas há X dias
   - Conversas sem resposta há X horas
   - Leads frios (sem interação há X dias)
   - Conversas que precisam verificação de satisfação
   ↓
2. Seleciona Agente de IA de Followup apropriado:
   - Baseado no tipo de followup necessário
   - Baseado no histórico da conversa
   - Baseado em tags e estágio do funil
   ↓
3. Agente de IA analisa contexto:
   a) Busca informações da conversa anterior
   b) Verifica status atual do contato
   c) Analisa histórico de interações
   d) Identifica melhor momento e abordagem
   ↓
4. Agente de IA gera mensagem de followup:
   - Personalizada baseada no contexto
   - Tom apropriado (amigável, profissional, etc)
   - Objetivo claro (verificar satisfação, reengajar, etc)
   ↓
5. Sistema envia mensagem de followup:
   - Via canal original (WhatsApp, etc)
   - Registra como mensagem automática
   - Agenda próximo followup se necessário
   ↓
6. Registra followup:
   - Tipo de followup executado
   - Resposta do contato (se houver)
   - Efetividade do followup
   - Próximo followup agendado
```

**Tipos de Agentes de Followup**:
- **Followup de Satisfação**: Verifica satisfação após atendimento/resolução
- **Followup de Reengajamento**: Reengaja contatos inativos
- **Followup de Leads**: Acompanha leads que não converteram
- **Followup de Vendas**: Acompanha oportunidades de venda
- **Followup de Suporte**: Verifica se problema foi resolvido

---

## 🔗 INTEGRAÇÃO COM DISTRIBUIÇÃO

### Nas Configurações de Conversas
- Adicionar opção: "Usar Agente de IA" na distribuição
- Selecionar qual agente de IA usar por:
  - Setor
  - Funil/Estágio
  - Tags
  - Horário
- Percentual de distribuição: X% para IA, Y% para humanos

### Exemplo de Configuração
```php
'distribution_settings' => [
    'use_ai_agents' => true,
    'ai_distribution' => [
        'sdr_leads' => [
            'agent_id' => 1, // Agente SDR
            'percentage' => 70, // 70% das conversas vão para IA
            'conditions' => [
                'tags' => ['lead', 'novo'],
                'funnel_stage' => 'Qualificação'
            ]
        ],
        'cs_suporte' => [
            'agent_id' => 2, // Agente CS
            'percentage' => 50,
            'conditions' => [
                'tags' => ['suporte', 'problema'],
                'funnel_stage' => 'Suporte'
            ]
        ]
    ]
]
```

---

## 📈 MELHORIAS FUTURAS

### Sistema de Memória/Contexto
- Armazenar informações importantes extraídas durante a conversa
- Usar essas informações em conversas futuras
- Exemplo: "Cliente mencionou que trabalha com e-commerce"

### Fallback e Escalação
- Se a IA não entender ou não souber responder:
  - Tentar reformular a pergunta
  - Se ainda não souber, escalar para humano
- Palavras-chave de escalação: "falar com humano", "supervisor", "não entendi"

### Rate Limiting e Custos
- Limitar número de mensagens por conversa
- Limitar tokens por conversa
- Alertar quando custo mensal exceder limite
- Desativar agente automaticamente se custo muito alto

### Análise de Sentimento
- Detectar frustração/insatisfação
- Escalar automaticamente se sentimento negativo
- Ajustar tom da resposta baseado no sentimento

### A/B Testing
- Criar múltiplos agentes com prompts diferentes
- Distribuir conversas entre eles
- Medir taxa de conversão/resolução
- Escolher melhor agente automaticamente

### Logs e Analytics
- Registrar todas as interações
- Tools utilizadas por conversa
- Tokens consumidos
- Custo por conversa
- Taxa de escalação
- Tempo médio de resposta

### Validação de Tools
- Validar parâmetros antes de executar
- Sanitizar inputs para prevenir SQL injection
- Rate limiting por tool
- Timeout para tools externas

### Cache de Tools
- Cachear resultados de tools que não mudam frequentemente
- Exemplo: informações de produto WooCommerce
- Reduzir chamadas desnecessárias

---

## 📚 DEPENDÊNCIAS

### PHP
- `guzzlehttp/guzzle` - Para chamadas HTTP à OpenAI API
- `openai-php/client` (opcional) - SDK oficial da OpenAI

### Configuração
- Chave API da OpenAI (`OPENAI_API_KEY`)
- Configuração de modelo padrão
- Configuração de rate limits

---

## ⚠️ CONSIDERAÇÕES DE SEGURANÇA

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

---

## 🎯 PRÓXIMOS PASSOS

1. **Criar Migrations** (1-2 horas)
   - Tabelas de agentes, tools e logs

2. **Criar Models** (2-3 horas)
   - AIAgent, AITool, AIConversation

3. **Criar Services** (4-6 horas)
   - AIAgentService, AIToolService, OpenAIService

4. **Implementar Tools Básicas** (6-8 horas)
   - System tools (buscar_conversas, buscar_contato, etc)
   - WooCommerce tools básicas

5. **Criar Controllers e Views** (4-6 horas)
   - CRUD de agentes e tools
   - Interface de configuração

6. **Integrar com Distribuição** (2-3 horas)
   - Adicionar opção nas configurações de conversas
   - Lógica de seleção de agente

7. **Testes e Ajustes** (2-3 horas)
   - Testar fluxo completo
   - Ajustar prompts e configurações

**Tempo estimado total**: 21-31 horas

---

## ✅ O QUE FOI IMPLEMENTADO (2025-01-27)

### Estrutura Base
- ✅ Migrations criadas (tabelas ai_agents, ai_tools, ai_agent_tools, ai_conversations)
- ✅ Models completos (AIAgent, AITool)
- ✅ Services completos (AIAgentService, AIToolService)
- ✅ Controllers completos (AIAgentController, AIToolController)
- ✅ Seeds com tools padrão do sistema

### Interface de Usuário
- ✅ Listagem de agentes de IA
- ✅ Visualização detalhada de agente
- ✅ Listagem de tools
- ✅ Visualização detalhada de tool
- ✅ **Interface dinâmica de criação/edição de tools**:
  - Campos específicos por tipo (WooCommerce, Database, N8N, API, Document, System, Followup)
  - Interface para Function Schema (sem JSON manual)
  - Adicionar/remover parâmetros dinamicamente
  - Construção automática de JSON
  - Preenchimento automático ao editar

### Funcionalidades
- ✅ CRUD completo de agents e tools
- ✅ Atribuição de tools a agentes
- ✅ Validações e segurança
- ✅ Método formatDateTime() no helper Url

## ⏳ O QUE FALTA IMPLEMENTAR

### Alta Prioridade
1. ✅ **Service OpenAIService** - **IMPLEMENTADO** (2025-01-27)
   - ✅ Integração com OpenAI API
   - ✅ Processamento de prompts
   - ✅ Function calling
   - ✅ Tratamento de erros
   - ✅ Rate limiting
   - ✅ Cálculo de custos

2. ✅ **Interface de criação/edição de agentes** - **IMPLEMENTADO** (2025-01-27)
   - ✅ Modal/formulário para criar agente
   - ✅ Modal/formulário para editar agente
   - ✅ Seleção de tools disponíveis
   - ✅ Configuração de prompt, modelo, temperatura

3. ✅ **Sistema de execução de tools** - **IMPLEMENTADO** (2025-01-27)
   - ✅ System Tools (buscar_conversas_anteriores, buscar_informacoes_contato, adicionar_tag/adicionar_tag_conversa, mover_para_estagio, escalar_para_humano)
   - ✅ Followup Tools (verificar_status_conversa, verificar_ultima_interacao)
   - ✅ WooCommerce Tools (buscar_pedido_woocommerce, buscar_produto_woocommerce, criar_pedido_woocommerce, atualizar_status_pedido)
   - ✅ Database Tools (consultar_banco_dados com segurança - tabelas e colunas permitidas)
   - ✅ N8N Tools (executar_workflow_n8n, buscar_dados_n8n)
   - ✅ API Tools (chamar_api_externa - chamadas genéricas a APIs)
   - ✅ Document Tools (buscar_documento, extrair_texto_documento - suporte básico para TXT, PDF e DOCX requerem bibliotecas)

### Média Prioridade
4. ✅ **Integração com distribuição de conversas** - **IMPLEMENTADO** (2025-01-27)
   - ✅ Seleção de agente de IA na distribuição
   - ✅ Configuração por setor/funil/tags
   - ✅ Percentual de distribuição
   - ✅ Processamento automático quando conversa é atribuída
   - ✅ Processamento automático de mensagens recebidas

5. ✅ **Sistema de Followup Automático** - **IMPLEMENTADO** (2025-01-27)
   - ✅ Agentes especializados em followup (6 tipos: satisfação, reengajamento, leads, vendas, suporte, geral)
   - ✅ Verificação automática de status
   - ✅ Reengajamento de contatos inativos
   - ✅ Followup de leads frios
   - ✅ Followup de oportunidades de venda
   - ✅ Verificação de satisfação pós-atendimento
   - ✅ Seleção inteligente de agente por tipo de followup
   - ✅ Mensagens contextuais baseadas no tipo de followup
   - ✅ Seed com agentes padrão criado

### Baixa Prioridade
6. ✅ **Logs e Analytics** - **IMPLEMENTADO** (2025-01-27)
   - ✅ Registro de interações (AIConversation)
   - ✅ Tokens consumidos
   - ✅ Custo por conversa
   - ✅ Taxa de escalação
   - ✅ Estatísticas por agente

7. ✅ **Controle de custos avançado** - **IMPLEMENTADO** (2025-01-27)
   - ✅ Cálculo de custos básico
   - ✅ Rate limiting por agente (mensagens/tokens por período)
   - ✅ Alertas de custo mensal (threshold e limite excedido)
   - ✅ Desativação automática quando limite excedido
   - ✅ Reset automático no início do mês
   - ✅ Métricas e estatísticas de custo

**Última atualização**: 2025-01-27
**Status geral**: 95% Completo (era 40%)

---

## 🔗 DOCUMENTAÇÃO RELACIONADA

- **Agentes de IA para Kanban**: Ver `PLANO_AGENTES_IA_KANBAN.md` (planejamento)
- **Sistema RAG**: Ver `PLANO_SISTEMA_RAG.md` (planejamento)
- **Documentação Completa**: Ver `DOCUMENTACAO_AI_AGENTS_E_TOOLS.md`

