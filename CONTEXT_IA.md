# CONTEXTO DO SISTEMA - DOCUMENTAÇÃO PARA IA

> **IMPORTANTE**: Este arquivo contém o contexto completo do sistema para facilitar o entendimento de IAs em novos contextos de conversa.

## 📋 VISÃO GERAL DO PROJETO

### Nome do Projeto
**Sistema Multiatendimento / Multiatendentes / Multicanal** (similar ao Chatwoot)

### Tecnologias
- **Backend**: PHP 8.1+ (Vanilla PHP, sem framework específico)
- **Banco de Dados**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Tema Base**: Metronic 8 (Demo 3 - Compact Sidebar)
- **WebSocket**: Para tempo real (Ratchet ou ReactPHP)
- **APIs Externas**: Quepasa API e Evolution API (WhatsApp)

### Objetivo
Sistema completo de atendimento multicanal com:
- Múltiplos canais (WhatsApp inicialmente)
- Múltiplos atendentes/agentes
- Sistema de permissões avançado
- Funis com Kanban
- Automações complexas
- Layout similar ao Chatwoot 4

---

## 🏗️ ARQUITETURA DO SISTEMA

### Estrutura de Diretórios

```
chat/
├── api/                          # API REST
│   ├── v1/                       # Versão 1 da API
│   └── middleware/               # Middlewares da API
│
├── app/                          # Lógica da aplicação (MVC)
│   ├── Controllers/              # Controladores
│   ├── Models/                   # Modelos (ORM/Active Record)
│   ├── Services/                 # Serviços de negócio
│   ├── Middleware/               # Middlewares
│   ├── Helpers/                  # Funções auxiliares
│   └── Jobs/                     # Tarefas em background
│
├── config/                       # Configurações
│   ├── database.php              # Config do banco
│   ├── app.php                   # Config geral
│   ├── permissions.php           # Config de permissões
│   ├── whatsapp.php              # Config WhatsApp
│   └── automations.php           # Config automações
│
├── database/                     # Migrações e seeds
│   ├── migrations/               # Migrações do banco
│   └── seeds/                    # Seeds (dados iniciais)
│
├── public/                       # Arquivos públicos
│   ├── index.php                 # Entry point
│   ├── websocket.php            # Servidor WebSocket
│   ├── whatsapp-webhook.php     # Webhook WhatsApp
│   └── assets/                   # Assets estáticos
│       ├── css/                  # CSS (Metronic + custom)
│       ├── js/                   # JavaScript (Metronic + custom)
│       ├── plugins/              # Plugins JS
│       └── media/                # Imagens, ícones, etc
│
├── views/                        # Templates/Páginas PHP
│   ├── layouts/                  # Layouts base
│   │   └── metronic/             # Layout Metronic
│   ├── conversations/             # Páginas de conversas
│   ├── contacts/                 # Páginas de contatos
│   ├── agents/                   # Páginas de agentes
│   ├── funnels/                  # Páginas de funis
│   ├── automations/               # Páginas de automações
│   └── components/               # Componentes reutilizáveis
│
├── metronic/                     # ⚠️ REFERÊNCIA APENAS - Não usar diretamente
│   └── ...                       # Arquivos originais do Metronic
│
└── docs/                         # Documentação
    ├── CONTEXT_IA.md            # Este arquivo
    ├── ARQUITETURA.md            # Arquitetura detalhada
    └── API.md                    # Documentação da API
```

### ⚠️ IMPORTANTE SOBRE METRONIC
- A pasta `metronic/` contém os arquivos originais do tema
- **NÃO referenciar diretamente** arquivos de `metronic/` no código
- Copiar arquivos necessários para `public/assets/` quando necessário
- Usar `public/assets/` como caminho padrão no sistema

---

## 📊 ESTRUTURA DO BANCO DE DADOS

### Tabelas Principais

#### 1. **users** - Usuários do sistema
- `id`, `email`, `password`, `name`, `role`, `status`, `created_at`, `updated_at`

#### 2. **roles** - Papéis/Roles
- `id`, `name`, `slug`, `description`, `created_at`, `updated_at`

#### 3. **permissions** - Permissões
- `id`, `name`, `slug`, `description`, `module`, `created_at`

#### 4. **role_permissions** - Relação roles-permissões
- `role_id`, `permission_id`

#### 5. **departments** - Setores/Departamentos
- `id`, `name`, `description`, `parent_id`, `created_at`, `updated_at`

#### 6. **agents** - Agentes/Atendentes
- `id`, `user_id`, `availability_status`, `max_conversations`, `current_conversations`, `created_at`

#### 7. **agent_departments** - Relação agentes-setores
- `agent_id`, `department_id`

#### 8. **inboxes** - Caixas de entrada (canais)
- `id`, `name`, `channel_type`, `channel_id`, `whatsapp_account_id`, `settings` (JSON), `status`, `created_at`

#### 9. **whatsapp_accounts** - Contas WhatsApp
- `id`, `name`, `provider` (quepasa/evolution), `api_url`, `api_key`, `instance_id`, `qr_code`, `status`, `connected_at`, `created_at`

#### 10. **contacts** - Contatos
- `id`, `name`, `email`, `phone`, `avatar`, `custom_attributes` (JSON), `created_at`, `updated_at`

#### 11. **conversations** - Conversas
- `id`, `inbox_id`, `contact_id`, `agent_id`, `funnel_id`, `funnel_stage_id`, `status`, `priority`, `created_at`, `updated_at`, `resolved_at`, `moved_at`

#### 12. **messages** - Mensagens
- `id`, `conversation_id`, `sender_type` (agent/contact), `sender_id`, `content`, `message_type`, `attachments` (JSON), `status`, `read_at`, `created_at`
- ✅ Sistema de anexos completo implementado (2025-01-27)

#### 13. **funnels** - Funis
- `id`, `name`, `description`, `inbox_id`, `is_active`, `created_at`, `updated_at`

#### 14. **funnel_stages** - Estágios dos Funis
- `id`, `funnel_id`, `name`, `description`, `position`, `color`, `is_default`, `created_at`, `updated_at`
- ✅ **Campos avançados adicionados** (2025-01-27):
  - `max_conversations` - Limite máximo de conversas simultâneas
  - `allow_move_back` - Permitir mover para trás
  - `allow_skip_stages` - Permitir pular estágios
  - `blocked_stages` - Estágios bloqueados (JSON)
  - `required_stages` - Estágios obrigatórios (JSON)
  - `required_tags` - Tags obrigatórias (JSON)
  - `blocked_tags` - Tags bloqueadas (JSON)
  - `auto_assign` - Auto-atribuição
  - `auto_assign_department_id` - Departamento para auto-atribuição
  - `auto_assign_method` - Método de distribuição
  - `sla_hours` - SLA em horas
  - `settings` - Configurações adicionais (JSON)

#### 14. **funnel_stages** - Estágios do funil
- `id`, `funnel_id`, `name`, `description`, `position`, `color`, `is_default`, `created_at`, `updated_at`
- ✅ **Campos avançados adicionados** (2025-01-27):
  - `max_conversations` - Limite máximo de conversas simultâneas
  - `allow_move_back` - Permitir mover para trás (BOOLEAN)
  - `allow_skip_stages` - Permitir pular estágios (BOOLEAN)
  - `blocked_stages` - Estágios bloqueados (JSON array)
  - `required_stages` - Estágios obrigatórios (JSON array)
  - `required_tags` - Tags obrigatórias (JSON array)
  - `blocked_tags` - Tags bloqueadas (JSON array)
  - `auto_assign` - Auto-atribuição (BOOLEAN)
  - `auto_assign_department_id` - Departamento para auto-atribuição
  - `auto_assign_method` - Método de distribuição (round-robin, by-load, by-specialty)
  - `sla_hours` - SLA em horas
  - `settings` - Configurações adicionais (JSON)

#### 15. **tags** - Tags
- `id`, `name`, `color`, `created_at`

#### 16. **conversation_tags** - Relação conversas-tags
- `conversation_id`, `tag_id`

#### 17. **automations** - Automações
- `id`, `name`, `description`, `funnel_id`, `trigger_type`, `trigger_conditions` (JSON), `is_active`, `created_at`, `updated_at`

#### 18. **automation_rules** - Regras de automação
- `id`, `automation_id`, `rule_type`, `conditions` (JSON), `actions` (JSON), `order`, `created_at`

#### 19. **automation_logs** - Logs de automações
- `id`, `automation_id`, `conversation_id`, `status`, `message`, `executed_at`

#### 20. **activities** - Atividades/Auditoria
- `id`, `user_id`, `action_type`, `target_type`, `target_id`, `metadata` (JSON), `created_at`

#### 21. **notifications** - Notificações
- `id`, `user_id`, `title`, `message`, `link`, `type`, `is_read`, `created_at`, `read_at`

#### 22. **message_templates** - Templates de Mensagens
- `id`, `name`, `subject`, `content`, `variables` (JSON), `category`, `is_active`, `created_at`, `updated_at`

#### 23. **settings** - Configurações do Sistema
- `id`, `key_name`, `value`, `type`, `category`, `description`, `created_at`, `updated_at`

#### 24. **ai_agents** - Agentes de IA (NOVO)
- `id`, `name`, `role`, `description`, `prompt`, `model`, `temperature`, `max_tokens`, `settings` (JSON), `is_active`, `created_at`, `updated_at`

#### 25. **ai_tools** - Tools/Ferramentas de IA (NOVO)
- `id`, `name`, `display_name`, `description`, `type`, `category`, `schema` (JSON), `config` (JSON), `is_active`, `created_at`, `updated_at`

#### 26. **ai_agent_tools** - Relação Agentes-Tools (NOVO)
- `agent_id`, `tool_id`

#### 27. **ai_conversations** - Logs de Conversas com IA (NOVO)
- `id`, `conversation_id`, `agent_id`, `message_id`, `ai_response_id`, `prompt_used`, `tools_called` (JSON), `tokens_used`, `cost`, `execution_time_ms`, `created_at`

---

## 🔐 SISTEMA DE PERMISSÕES

### Hierarquia de Níveis
```
Nível 0: Super Admin (Acesso Total)
├── Nível 1: Admin
│   ├── Nível 2: Supervisor
│   │   ├── Nível 3: Agente Sênior
│   │   │   ├── Nível 4: Agente
│   │   │   └── Nível 5: Agente Júnior
│   │   └── Nível 6: Visualizador
│   └── Nível 7: API User
```

### Tipos de Permissões Principais

#### Visualização de Conversas
- `conversations.view.own` - Ver apenas próprias
- `conversations.view.department` - Ver do setor
- `conversations.view.all` - Ver todas

#### Edição de Conversas
- `conversations.edit.own` - Editar próprias
- `conversations.edit.department` - Editar do setor
- `conversations.edit.all` - Editar todas

#### Mensagens
- `messages.send.own` - Enviar em próprias
- `messages.send.department` - Enviar do setor
- `messages.send.all` - Enviar em qualquer

#### Kanban
- `kanban.view` - Ver Kanban
- `kanban.drag_drop.own` - Arrastar próprias
- `kanban.drag_drop.all` - Arrastar qualquer

#### Automações
- `automations.view` - Ver automações
- `automations.create` - Criar automações
- `automations.edit` - Editar automações

**Documentação completa**: Ver `SISTEMA_REGRAS_COMPLETO.md` seção 1

---

## 📋 SISTEMA DE FUNIS E KANBAN

### Estrutura
- **Funis**: Contêm múltiplos estágios
- **Estágios**: Colunas no Kanban
- **Conversas**: Podem estar em um estágio de um funil

### Propriedades de Estágio
- Nome, posição, cor
- Auto-atribuição (agente/setor)
- Limite de conversas
- Validações antes de mover

### Regras de Movimentação
- Validações obrigatórias
- Validações condicionais
- Validações de negócio
- Auto-atribuição inteligente

**Documentação completa**: Ver `SISTEMA_REGRAS_COMPLETO.md` seção 2

---

## 🤖 SISTEMA DE AUTOMAÇÕES

### Tipos de Triggers
1. **Conversa**: Criação, atualização, movimentação, resolução
2. **Mensagem**: Recebimento, envio, status
3. **Temporal**: Agendados, baseados em tempo, horário
4. **Contato**: Criação, atualização, atividade
5. **Agente**: Atividade, performance
6. **Externos**: Webhooks, APIs, integrações

### Tipos de Ações
1. **Conversa**: Mover, atribuir, alterar status/tags
2. **Mensagem**: Enviar mensagem, usar template
3. **Notificação**: Notificar agente/setor, enviar email/SMS
4. **Integração**: Webhook, API, sincronização
5. **Tarefa**: Criar tarefa, lembrete
6. **Delay**: Aguardar tempo/condição

### Condições
- Operadores lógicos: AND, OR, NOT, XOR
- Agrupamento de condições
- Condições aninhadas

**Documentação completa**: Ver `SISTEMA_REGRAS_COMPLETO.md` seção 3

---

## 📱 INTEGRAÇÃO WHATSAPP

### APIs Suportadas
1. **Quepasa API** ✅ Implementado
2. **Evolution API** ⏳ Pendente

### Funcionalidades
- Múltiplas contas WhatsApp
- QR Code para conectar
- Envio/recebimento de mensagens
- Status de entrega/leitura
- Envio de mídia (imagens, documentos, áudio)

### Estrutura
- `WhatsAppAccount` model
- `WhatsAppService` (QuepasaService, EvolutionService)
- Webhook para receber mensagens
- Jobs para processar mensagens

---

## ⚙️ CONFIGURAÇÕES AVANÇADAS DE CONVERSAS (NOVO)

### Limites e Capacidade
- Max conversas abertas por agente (global e por setor/funil/prioridade)
- Max conversas sem resposta por setor
- Max conversas por estágio/funil
- Limites por tipo de canal e horário

### SLA e Timeouts
- SLA de resposta (por prioridade, setor, funil, canal, horário)
- SLA de resolução
- Timeouts de inatividade
- Alertas antes/depois do SLA

### Distribuição e Atribuição
- Métodos: Round-Robin, Por Carga, Por Especialidade, Por Performance
- Distribuição percentual por agente/setor
- Regras de atribuição (online, disponível, horário, capacidade)
- Balanceamento automático

### Reatribuição Automática
- Reatribuição após SLA excedido
- Reatribuição por inatividade
- Reatribuição por condições (tags, prioridade, estágio)
- Regras de reatribuição (máximo, tempo mínimo, condições)

### Priorização e Filas
- Níveis de prioridade (baixa, normal, alta, urgente)
- Critérios de priorização automática
- Ordenação de filas (prioridade + SLA, data, atividade)

### Estrutura
- Armazenado em tabela `settings` com chave `conversation_settings`
- JSON com todas as configurações
- Interface com seções colapsáveis

---

## 🤖 SISTEMA DE AGENTES DE IA (NOVO)

### Conceito
Agentes de IA são entidades virtuais que podem ser atribuídas a conversas, cada um com:
- Prompt personalizado
- Conjunto de tools (ferramentas) específicas
- Configurações de modelo (GPT-4, GPT-3.5-turbo, etc)
- Regras de comportamento e escalação

### Tipos de Agentes (Roles)
- **SDR** (Sales Development Representative): Qualificação inicial, captura de dados
- **CS** (Customer Success): Suporte pós-venda, resolução de problemas
- **CLOSER**: Fechamento de vendas, negociação
- **SUPPORT**: Suporte técnico geral
- **ONBOARDING**: Onboarding de novos clientes
- **CUSTOM**: Customizável pelo usuário

### Sistema de Tools (Ferramentas)
Tools são funções que o agente de IA pode chamar durante a conversa, permitindo:
- Buscar informações externas
- Executar ações no sistema
- Integrar com serviços externos

#### Tipos de Tools Disponíveis

**A. WooCommerce Tools**
- `buscar_pedido_woocommerce`: Busca informações de pedido
- `buscar_produto_woocommerce`: Busca informações de produto
- `criar_pedido_woocommerce`: Cria novo pedido
- `atualizar_status_pedido`: Atualiza status do pedido

**B. Database Tools**
- `consultar_banco_dados`: Executa consultas SQL em tabelas específicas
- Validação de segurança (read-only, tabelas permitidas)

**C. N8N Tools**
- `executar_workflow_n8n`: Executa workflow via webhook
- `buscar_dados_n8n`: Busca dados de fontes externas

**D. Document Tools**
- `buscar_documento`: Busca em documentos (PDF, DOCX)
- `extrair_texto_documento`: Extrai texto de documento específico

**E. System Tools (Internas)**
- `buscar_conversas_anteriores`: Busca histórico do contato
- `buscar_informacoes_contato`: Busca dados completos do contato
- `adicionar_tag_conversa`: Adiciona tag à conversa
- `mover_para_estagio`: Move conversa para outro estágio
- `escalar_para_humano`: Escala conversa para agente humano

**F. API Tools (Genéricas)**
- `chamar_api_externa`: Chama API externa customizada

**G. Followup Tools (NOVO - Planejado)**
- `verificar_status_conversa`: Verifica status atual da conversa
- `verificar_ultima_interacao`: Verifica última interação do contato
- `reengajar_contato`: Envia mensagem de reengajamento
- `verificar_satisfacao`: Verifica satisfação pós-atendimento
- `verificar_leads_frios`: Identifica leads que não interagem há X tempo
- `agendar_followup`: Agenda followup futuro para a conversa

### Casos de Uso de Agentes de IA

**1. Agentes de Atendimento**
- **SDR (Sales Development Representative)**: Qualificação de leads, primeiro contato
- **CS (Customer Success)**: Suporte e relacionamento com clientes
- **CLOSER**: Fechamento de vendas, negociação
- **Suporte Técnico**: Resolução de problemas técnicos
- **Onboarding**: Acompanhamento de novos clientes

**2. Agentes de Followup (NOVO)**
- **Followup de Satisfação**: Verifica satisfação após atendimento/resolução
- **Followup de Reengajamento**: Reengaja contatos inativos
- **Followup de Leads**: Acompanha leads que não converteram
- **Followup de Vendas**: Acompanha oportunidades de venda
- **Followup de Suporte**: Verifica se problema foi resolvido

### Fluxo de Funcionamento
1. Conversa atribuída a Agente de IA
2. Sistema busca contexto (mensagens, informações do contato)
3. Monta prompt com instruções, histórico e tools disponíveis
4. Chama OpenAI API com function calling
5. Executa tools chamadas pela IA
6. Reenvia para OpenAI com resultados das tools
7. Envia resposta final ao contato
8. Registra interação (mensagens, tools, tokens, custo)

### Integração com Distribuição
- Agentes de IA podem ser selecionados na distribuição automática
- Configuração por setor, funil, tags, horário
- Percentual de distribuição (X% IA, Y% humanos)

### Sistema de Followup Automático (NOVO - Planejado)
- Agentes de IA especializados em followup de conversas
- Verificação automática de status após X tempo
- Reengajamento de contatos inativos
- Followup de leads frios
- Verificação de satisfação pós-atendimento
- Agendamento automático de followups futuros

### Estrutura
- `AIAgent` model
- `AITool` model
- `AIAgentService` e `AIToolService`
- `OpenAIService` para integração com OpenAI
- Logs em `ai_conversations` para analytics

---

## 🎨 FRONTEND E LAYOUT

### Tema Base
- **Metronic 8** - Demo 3 (Compact Sidebar)
- **Layout**: Similar ao Chatwoot 4
- **Estrutura**: 3 colunas (Sidebar + Lista + Chat)

### Estrutura de Layout
```
┌──────────┬──────────────────┬─────────────────┐
│ Sidebar  │ Lista Conversas │  Janela Chat    │
│ (70px)   │    (380px)       │    (flex)       │
└──────────┴──────────────────┴─────────────────┘
```

### Componentes Principais
- **Sidebar**: Navegação principal
- **Lista de Conversas**: Busca, filtros, scroll
- **Janela de Chat**: Header, mensagens, input
- **Kanban**: Drag & drop, colunas, cards

### Assets
- CSS: `public/assets/css/` (Metronic + custom)
- JS: `public/assets/js/` (Metronic + custom)
- Media: `public/assets/media/` (imagens, ícones)

**Documentação completa**: Ver `LAYOUT_CHATWOOT_METRONIC.md` e `EXEMPLO_IMPLEMENTACAO.md`

---

## 🔄 FLUXOS PRINCIPAIS

### 1. Fluxo de Conversa
1. Contato envia mensagem via WhatsApp
2. Webhook recebe mensagem
3. Sistema cria/atualiza conversa
4. Automações são verificadas
5. Conversa é atribuída (auto ou manual)
6. Agente recebe notificação
7. Agente responde
8. Mensagem é enviada via API WhatsApp

### 2. Fluxo de Permissões
1. Usuário faz requisição
2. Middleware verifica autenticação
3. Middleware verifica permissões
4. PermissãoService valida acesso
5. Cache de permissões (Redis)
6. Acesso concedido/negado

### 3. Fluxo de Kanban
1. Agente arrasta conversa
2. Validações são executadas
3. Permissões são verificadas
4. Conversa é movida para novo estágio
5. Automações do estágio são executadas
6. Histórico é registrado

### 4. Fluxo de Automação
1. Trigger é acionado
2. Condições são avaliadas
3. Se verdadeiro, ações são executadas
4. Logs são registrados
5. Notificações são enviadas (se necessário)

---

## 📝 CONVENÇÕES DE CÓDIGO

### PHP
- **PSR-12** coding standard
- **Namespaces**: `App\` para classes principais
- **Naming**: camelCase para métodos, PascalCase para classes
- **Estrutura MVC**: Controllers → Services → Models

### JavaScript
- **ES6+** syntax
- **Classes** para componentes principais
- **Modular**: Um arquivo por funcionalidade
- **Event-driven**: Eventos para comunicação

### Banco de Dados
- **Snake_case** para nomes de tabelas e colunas
- **Timestamps**: `created_at`, `updated_at`
- **Soft deletes**: `deleted_at` quando necessário
- **JSON**: Para campos complexos (settings, metadata)

### Arquivos
- **Controllers**: `PascalCaseController.php`
- **Models**: `PascalCase.php`
- **Services**: `PascalCaseService.php`
- **Views**: `kebab-case.php`

---

## 🔗 INTEGRAÇÕES

### APIs Externas
- **Quepasa API**: WhatsApp
- **Evolution API**: WhatsApp
- **Webhooks**: Receber eventos externos

### Serviços Internos
- **WebSocket**: Tempo real (Ratchet/ReactPHP)
- **Queue**: Processamento assíncrono
- **Cache**: Redis para performance
- **Storage**: Arquivos enviados

---

## 📚 DOCUMENTAÇÃO ADICIONAL

### Arquivos de Documentação
1. **SISTEMA_REGRAS_COMPLETO.md**: Regras detalhadas de permissões, Kanban e automações
2. **LAYOUT_CHATWOOT_METRONIC.md**: Guia de implementação do layout
3. **EXEMPLO_IMPLEMENTACAO.md**: Exemplos práticos de código
4. **ANALISE_TEMAS_FRONTEND.md**: Análise de temas frontend

### Localização
- Todos os arquivos `.md` estão na raiz do projeto
- Documentação técnica em `docs/` (quando criada)

---

## 🚀 COMO ESCALAR O SISTEMA

### Ao Adicionar Novas Funcionalidades

1. **Criar Migration**: `database/migrations/XXX_create_table.php`
2. **Criar Model**: `app/Models/ModelName.php`
3. **Criar Controller**: `app/Controllers/ModelNameController.php`
4. **Criar Service**: `app/Services/ModelNameService.php`
5. **Criar Views**: `views/model-name/`
6. **Adicionar Rotas**: Em `public/index.php` ou arquivo de rotas
7. **Adicionar Permissões**: Em `config/permissions.php` e seeds
8. **Documentar**: Atualizar este arquivo e criar documentação específica

### Ao Modificar Funcionalidades Existentes

1. **Verificar Impacto**: Quais tabelas/models/views são afetados
2. **Criar Migration**: Se mudanças no banco
3. **Atualizar Models**: Se estrutura mudou
4. **Atualizar Services**: Se lógica mudou
5. **Atualizar Views**: Se interface mudou
6. **Atualizar Documentação**: Este arquivo e docs específicas

---

## ⚠️ PONTOS IMPORTANTES

### Segurança
- Sempre validar inputs
- Usar prepared statements
- Verificar permissões antes de ações
- Sanitizar outputs
- Proteger contra XSS e SQL Injection

### Performance
- Usar cache quando possível
- Otimizar queries (índices)
- Paginar listagens
- Processar tarefas pesadas em background
- Minificar assets em produção

### Manutenibilidade
- Código limpo e documentado
- Separar responsabilidades
- Usar padrões de design
- Testar funcionalidades
- Versionar código (Git)

---

## 📞 CONTEXTO DE DESENVOLVIMENTO

### Ambiente
- **Servidor Local**: Laragon (Windows)
- **PHP**: 8.1+
- **MySQL**: 8.0+
- **Navegador**: Chrome/Firefox (desenvolvimento)

### Ferramentas
- **IDE**: Cursor (com IA)
- **Git**: Controle de versão
- **Composer**: Dependências PHP (quando necessário)

### Processo de Desenvolvimento
1. Planejar funcionalidade
2. Criar estrutura (migrations, models, etc)
3. Implementar backend
4. Implementar frontend
5. Testar
6. Documentar
7. Commit

---

## 🎯 PRÓXIMOS PASSOS SUGERIDOS

### ✅ Concluído
1. ✅ Estrutura base criada
2. ✅ Documentação de contexto criada
3. ✅ Autenticação implementada
4. ✅ Sistema de permissões (95%)
5. ✅ Estrutura de banco (migrations)
6. ✅ Modelos base implementados
7. ✅ Controllers e rotas criados
8. ✅ Frontend (layout Chatwoot)
9. ✅ Integração WhatsApp (Quepasa)
10. ✅ Kanban funcional
11. ✅ Automações (85%)
12. ✅ WebSocket (100%)
13. ✅ Tags (100%)
14. ✅ Notificações (100%)
15. ✅ Templates de Mensagens (100%)
16. ✅ Configurações Básicas (100%)
17. ✅ **Sistema de Anexos e Mídia** (2025-01-27) - 100%
18. ✅ **Validações Avançadas de Kanban** (2025-01-27) - 90%
19. ✅ **Interface de Configuração de Estágios** (2025-01-27) - 100%

### ⏳ Próximas Prioridades
1. ⏳ **Configurações Avançadas de Conversas** (NOVO)
2. ⏳ **Sistema de Agentes de IA** (NOVO)
   - Agentes de atendimento (SDR, CS, CLOSER)
   - **Agentes de Followup** (NOVO - Planejado):
     - Followup de satisfação
     - Reengajamento de contatos inativos
     - Followup de leads frios
     - Verificação de satisfação pós-atendimento
3. ⏳ CRUD Completo de Agentes e Usuários
4. ⏳ Melhorias de interface (Setores, Automações)
5. ✅ **Validações avançadas de Kanban** (2025-01-27) - Concluído
6. ✅ **Anexos e Mídia** (2025-01-27) - Concluído

---

**Última atualização**: 2025-01-27
**Versão do documento**: 2.0

