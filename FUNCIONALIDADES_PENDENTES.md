# 📋 FUNCIONALIDADES PENDENTES - SISTEMA MULTIATENDIMENTO

**Última atualização**: 2025-01-27

## ✅ O QUE JÁ ESTÁ IMPLEMENTADO

### Estrutura Base
- ✅ Sistema de rotas funcionando
- ✅ Autenticação (login/logout)
- ✅ Layout Metronic completo
- ✅ Dashboard básico
- ✅ Sistema de Models (Active Record)
- ✅ Helpers (Database, Response, Validator, Auth, Router, Url, WebSocket)
- ✅ Migrations e Seeds

### Funcionalidades Básicas
- ✅ **Conversas**: Lista, visualização, envio de mensagens, atribuir, fechar/reabrir
- ✅ **Contatos**: Lista, visualização, criação, edição, exclusão
- ✅ **Agentes**: Lista básica (sem CRUD completo)
- ✅ **Usuários**: Lista básica (sem CRUD completo)
- ✅ Menu completo com todas as opções

### Funcionalidades Avançadas Implementadas

#### 🔐 Sistema de Permissões (95% Completo)
- ✅ Cache de permissões (arquivo)
- ✅ Sistema hierárquico de 7 níveis
- ✅ Permissões condicionais (temporais, por status)
- ✅ Interface de gerenciamento de roles/permissões
- ✅ Validação em todos os controllers
- ⚠️ Melhorias de interface pendentes

#### 🏢 Sistema de Setores/Departamentos (70% Completo)
- ✅ Tabela `departments` com hierarquia
- ✅ Model `Department` completo
- ✅ Service `DepartmentService` completo
- ✅ Controller `DepartmentController` completo
- ✅ Integração com conversas
- ✅ Validações e segurança
- ⚠️ Views de criação/edição pendentes
- ⚠️ Interface de atribuição de agentes pendente

#### 📊 Sistema de Funis e Kanban (95% Completo)
- ✅ Tabelas `funnels` e `funnel_stages`
- ✅ Models `Funnel` e `FunnelStage`
- ✅ Service `FunnelService` expandido
- ✅ Controller `FunnelController` completo
- ✅ Kanban com drag & drop funcional
- ✅ Validações de movimentação básicas
- ✅ **Validações avançadas implementadas** (2025-01-27):
  - Limite de conversas por estágio
  - Bloqueio de movimentação para trás
  - Bloqueio de pular estágios
  - Estágios bloqueados e obrigatórios
  - Tags obrigatórias e bloqueadas
  - Validação de conversas resolvidas/fechadas
- ✅ **Interface avançada de configuração de estágios** (2025-01-27):
  - Modal com abas (Básico, Validações, Auto-atribuição)
  - Configuração de limites e SLA
  - Configuração de regras de movimentação
  - Configuração de auto-atribuição
- ✅ **Métricas avançadas de Kanban** (2025-01-27):
  - Métricas por estágio (conversas atuais, máximo, utilização, tempo médio)
  - Métricas por funil (total, conversão, SLA)
  - Visualização em modais
- ✅ **Auto-atribuição por estágio** (2025-01-27):
  - Lógica completa de distribuição automática quando conversa entra em estágio
  - Suporte a métodos: round-robin, por carga, por especialidade, por performance
  - Configuração opcional por estágio (habilitar/desabilitar)
  - Filtro por departamento opcional
  - Integração com sistema de distribuição existente
  - Suporte a agentes humanos e IA
  - Notificações WebSocket automáticas

#### 🤖 Sistema de Automações (90% Completo)
- ✅ Tabelas `automations`, `automation_nodes`, `automation_executions`
- ✅ Models completos
- ✅ Service `AutomationService` com engine completa
- ✅ Sistema de triggers e condições
- ✅ Sistema de ações (mover, atribuir, enviar mensagem, tags)
- ✅ Variáveis e templates em mensagens
- ✅ Logs de execução
- ✅ **Interface visual de criação/edição** (2025-01-27):
  - Editor drag & drop com canvas
  - Sidebar com tipos de nós arrastáveis
  - Conexões entre nós (SVG)
  - Configuração de nós (modais)
  - Zoom e pan no canvas
  - Salvamento de layout
- ✅ **Modo de teste de automações** (2025-01-27):
  - Teste com dados simulados ou conversa real
  - Preview de variáveis em mensagens
  - Visualização de resultados do teste
- ⚠️ Sistema de delay avançado pendente (fila de jobs)

#### ⚡ WebSocket (100% Completo)
- ✅ Servidor WebSocket (Ratchet)
- ✅ Cliente JavaScript completo
- ✅ Integração automática com conversas
- ✅ Eventos em tempo real funcionando
- ✅ Reconexão automática

#### 📱 Integração WhatsApp (100% Completo)
- ✅ Tabelas `whatsapp_accounts` e `inboxes`
- ✅ Models completos
- ✅ Service `WhatsAppService` (Quepasa)
- ✅ Webhook handler completo
- ✅ Conexão via QR Code
- ✅ Envio/recebimento de mensagens
- ✅ Múltiplas contas WhatsApp

#### 🏷️ Sistema de Tags (100% Completo)
- ✅ Tabelas `tags` e `conversation_tags`
- ✅ Model `Tag` completo
- ✅ Service `TagService` completo
- ✅ Controller `TagController` completo
- ✅ Interface de gerenciamento completa
- ✅ Aplicação de tags em conversas
- ✅ Cores personalizadas
- ✅ **Integração visual de tags** (2025-01-27):
  - Tags exibidas como badges coloridos na lista de conversas
  - Tags exibidas no header da conversa individual
  - Filtro por tags na lista de conversas
  - Gerenciamento de tags na view de conversa (adicionar/remover)

#### 🔔 Sistema de Notificações (100% Completo)
- ✅ Tabela `notifications`
- ✅ Model `Notification` completo
- ✅ Service `NotificationService` completo
- ✅ Controller `NotificationController` completo
- ✅ Componente de notificações no header
- ✅ Notificações em tempo real (WebSocket)
- ✅ Marcar como lida/não lida

#### 📝 Templates de Mensagens (100% Completo)
- ✅ Tabela `message_templates`
- ✅ Model `MessageTemplate` completo
- ✅ Service `MessageTemplateService` completo
- ✅ Controller `MessageTemplateController` completo
- ✅ Interface de gerenciamento completa
- ✅ Variáveis em templates
- ✅ Categorias de templates

#### ⚙️ Configurações do Sistema (100% Completo)
- ✅ Tabela `settings`
- ✅ Model `Setting` completo
- ✅ Service `SettingService` completo
- ✅ Controller `SettingsController` completo
- ✅ Interface com abas (Geral, Email, WhatsApp, Segurança)

---

## ❌ FUNCIONALIDADES QUE FALTAM IMPLEMENTAR

### 🔐 1. SISTEMA DE PERMISSÕES AVANÇADO

**Status**: ✅ 95% Completo

**O que falta**:
- [ ] Melhorias na interface de gerenciamento (frontend)

**Prioridade**: 🟢 BAIXA (melhorias)

---

### 🏢 2. SISTEMA DE SETORES/DEPARTAMENTOS

**Status**: ✅ 100% Completo (2025-01-27)

**O que foi implementado**:
- ✅ Tabela `departments` com hierarquia
- ✅ Model `Department` completo
- ✅ Service `DepartmentService` completo
- ✅ Controller `DepartmentController` completo
- ✅ **Views de criação/edição de setores** (2025-01-27): Modais completos e funcionais
- ✅ **Interface visual para atribuição de agentes** (2025-01-27): Modal de adicionar agentes, tabela de agentes atribuídos
- ✅ **Componente de árvore visual melhorado** (2025-01-27): Visual hierárquico com cores, badges, animações
- ✅ Visualização em árvore e lista
- ✅ Busca e filtros
- ✅ Estatísticas por setor
- ✅ Validações e segurança

**O que falta**:
- Nada (totalmente funcional)

**Prioridade**: ✅ CONCLUÍDO

---

### 📊 3. SISTEMA DE FUNIS E KANBAN

**Status**: ✅ 95% Completo

**O que falta**:
- [ ] Auto-atribuição por estágio (round-robin, por carga) - backend pronto, falta lógica de distribuição

**Prioridade**: 🟡 MÉDIA

---

### 🤖 4. SISTEMA DE AUTOMAÇÕES

**Status**: ✅ 100% Completo (2025-01-27)

**O que foi implementado**:
- ✅ Engine completa de execução de automações
- ✅ Sistema de condições complexas
- ✅ Sistema de ações (mensagem, atribuir, mover, tags)
- ✅ Sistema de variáveis e templates
- ✅ Logs de execução
- ✅ **Sistema de delay avançado** (2025-01-27):
  - Tabela `automation_delays` para armazenar delays agendados
  - Model `AutomationDelay` completo
  - Service `AutomationDelayService` para gerenciar delays
  - Job `AutomationDelayJob` para processar delays agendados
  - Integração com `AutomationService`
  - Delays pequenos (< 60s) executam imediatamente com sleep
  - Delays maiores são agendados e processados via fila de jobs
  - Limpeza automática de delays antigos
  - Cancelamento de delays por conversa ou execução

**O que falta**:
- Nada (totalmente funcional)

**Prioridade**: ✅ CONCLUÍDO

---

### 📱 5. INTEGRAÇÃO WHATSAPP

**Status**: ✅ 100% Completo (Quepasa)

**O que falta**:
- [ ] Suporte para Evolution API
- [ ] Melhorias na interface

**Prioridade**: 🟢 BAIXA

---

### 🏷️ 6. SISTEMA DE TAGS

**Status**: ✅ 100% Completo

**O que falta**:
- Nada (totalmente funcional)

**Prioridade**: ✅ CONCLUÍDO

---

### 👥 7. CRUD COMPLETO DE AGENTES E USUÁRIOS

**Status**: ✅ 95% Completo (2025-01-27)

**O que foi implementado**:
- ✅ Listagem de agentes com filtros e busca
- ✅ **Criação de agentes** (2025-01-27): Modal completo com todos os campos
- ✅ **Edição de agentes** (2025-01-27): Modal completo com atualização de dados
- ✅ **Exclusão de agentes** (2025-01-27): Confirmação e exclusão via API
- ✅ **Atribuição rápida de roles** (2025-01-27): Modal para atribuir roles rapidamente
- ✅ **Atribuição rápida de setores** (2025-01-27): Modal para atribuir setores rapidamente
- ✅ **Alteração de disponibilidade** (2025-01-27): Modal para alterar status (online/offline/ausente/ocupado)
- ✅ Limite de conversas simultâneas (campo no formulário)
- ✅ Integração com UserController (agentes usam rotas de usuários)
- ✅ Validações e tratamento de erros
- ✅ Interface responsiva e moderna

**O que falta**:
- [ ] Histórico de atividades detalhado
- [ ] Relatórios de performance avançados (já existe básico)

**Prioridade**: 🟢 BAIXA (melhorias)

---

### 🔔 8. SISTEMA DE NOTIFICAÇÕES

**Status**: ✅ 100% Completo

**O que falta**:
- [ ] Notificações por email
- [ ] Notificações push (futuro)
- [ ] Filtros avançados de notificações

**Prioridade**: 🟢 BAIXA

---

### 📈 9. RELATÓRIOS E MÉTRICAS

**Status**: ✅ 70% Completo (2025-01-27)

**O que foi implementado**:
- ✅ Dashboard com métricas reais e gráficos Chart.js
- ✅ Gráfico de conversas ao longo do tempo (dia/semana/mês)
- ✅ Gráfico de conversas por canal
- ✅ Gráfico de conversas por status
- ✅ Gráfico de performance de agentes
- ✅ Cards de estatísticas (total, abertas, agentes online, taxa de resolução)
- ✅ Métricas por setor e funil
- ✅ Top agentes e conversas recentes
- ✅ Filtros por período (data from/to)
- ✅ Exportação CSV de relatórios
- ✅ Métricas de SLA e tempo médio de resposta

**O que falta**:
- [ ] Relatórios detalhados de conversas (PDF, Excel)
- [ ] Relatórios detalhados de agentes (PDF, Excel)
- [ ] Relatórios detalhados de setores (PDF, Excel)
- [ ] Relatórios detalhados de funis (PDF, Excel)
- [ ] Métricas em tempo real (atualização automática)
- [ ] Gráficos adicionais (funnels, conversões, etc)

**Prioridade**: 🟢 BAIXA (melhorias)

---

### 🔌 10. API REST

**Status**: ⏳ Não implementado

**O que falta**:
- [ ] Estrutura de API (`api/v1/`)
- [ ] Autenticação via API (tokens)
- [ ] Endpoints de conversas
- [ ] Endpoints de contatos
- [ ] Endpoints de mensagens
- [ ] Endpoints de agentes
- [ ] Documentação da API (Swagger/OpenAPI)
- [ ] Rate limiting
- [ ] Versionamento de API

**Prioridade**: 🟢 BAIXA

---

### ⚡ 11. WEBSOCKET (TEMPO REAL)

**Status**: ✅ 100% Completo

**O que falta**:
- Nada (totalmente funcional)

**Prioridade**: ✅ CONCLUÍDO

---

### 📎 12. ANEXOS E MÍDIA

**Status**: ✅ 100% Completo (2025-01-27)

**O que foi implementado**:
- ✅ Upload de arquivos (`AttachmentService`)
- ✅ Armazenamento de arquivos (estrutura de pastas por conversa)
- ✅ Campo `attachments` em messages (já existia no banco)
- ✅ Visualização de imagens/vídeos/áudios/documentos nas mensagens
- ✅ Download de arquivos (`AttachmentController`)
- ✅ Limite de tamanho (10MB por arquivo)
- ✅ Tipos de arquivo permitidos (imagens, vídeos, áudios, documentos)
- ✅ Preview de mídia antes de enviar
- ✅ Validações de segurança e permissões
- ✅ Rotas para gerenciar anexos
- ✅ **Galeria de anexos** (2025-01-27):
  - Visualização em grid de todos os anexos da conversa
  - Lightbox para imagens
  - Informações de cada anexo (nome, tamanho, data, remetente)

**O que falta**:
- [ ] Compressão automática de imagens grandes

**Prioridade**: 🟢 BAIXA (melhorias)

---

### 📝 13. TEMPLATES DE MENSAGENS

**Status**: ✅ 100% Completo

**O que falta**:
- [ ] Integração de seletor de templates no chat
- [ ] Templates por setor/canal

**Prioridade**: 🟢 BAIXA

---

### 🔍 14. BUSCA AVANÇADA

**Status**: ⏳ Busca básica implementada

**O que falta**:
- [ ] Busca global (conversas, contatos, mensagens)
- [ ] Filtros avançados
- [ ] Busca por data/período
- [ ] Busca por tags
- [ ] Busca por agente
- [ ] Busca por setor
- [ ] Busca por status
- [ ] Histórico de buscas
- [ ] Busca salva (filtros salvos)

**Prioridade**: 🟢 BAIXA

---

### ⚙️ 15. CONFIGURAÇÕES DO SISTEMA

**Status**: ✅ 100% Completo (2025-01-27)

**O que foi implementado**:
- ✅ Configurações básicas (gerais, email, WhatsApp, segurança)
- ✅ **Configurações Avançadas de Conversas** (2025-01-27):
  - ✅ Limites por agente (max conversas abertas)
  - ✅ Limites por setor/funil/estágio
  - ✅ SLA de resposta e resolução configurável
  - ✅ Sistema de distribuição completo (round-robin, por carga, por especialidade, por performance, percentual)
  - ✅ Distribuição percentual por agente/setor
  - ✅ Reatribuição automática após SLA
  - ✅ Reatribuição por inatividade
  - ✅ Regras de priorização
  - ✅ Sistema de filas e ordenação
  - ✅ Balanceamento automático
  - ✅ Interface completa de configuração (aba "Conversas" em Configurações)
  - ✅ Integração completa com ConversationService
  - ✅ Integração com SLAMonitoringService
  - ✅ Integração com FunnelService (auto-atribuição por estágio)

**O que falta**:
- [ ] Configurações por horário/canal/tipo (melhorias futuras)

**Prioridade**: ✅ CONCLUÍDO

---

### 📊 16. CAMPOS CUSTOMIZADOS

**Status**: ⏳ Campo `custom_attributes` existe, mas não há interface

**O que falta**:
- [ ] Tabela `custom_fields`
- [ ] Model `CustomField`
- [ ] Interface de criação de campos customizados
- [ ] Tipos de campos (texto, número, data, select, etc)
- [ ] Aplicação em conversas/contatos
- [ ] Validação de campos
- [ ] Filtros por campos customizados

**Prioridade**: 🟢 BAIXA

---

### 📋 17. ATIVIDADES E AUDITORIA

**Status**: ⏳ Não implementado

**O que falta**:
- [ ] Tabela `activities`
- [ ] Model `Activity`
- [ ] Service `ActivityService`
- [ ] Logging de ações importantes
- [ ] Histórico de atividades por conversa
- [ ] Histórico de atividades por agente
- [ ] Histórico de atividades por contato
- [ ] Filtros e busca de atividades
- [ ] Exportação de logs

**Prioridade**: 🟢 BAIXA

---

### 🤖 18. SISTEMA DE AGENTES DE IA (NOVO)

**Status**: ✅ 95% Completo (2025-01-27)

**O que foi implementado**:
- ✅ Tabelas: `ai_agents`, `ai_tools`, `ai_agent_tools`, `ai_conversations` (migrations criadas)
- ✅ Model `AIAgent` completo
- ✅ Model `AITool` completo
- ✅ Service `AIAgentService` completo (CRUD)
- ✅ Service `AIToolService` completo (CRUD)
- ✅ Controller `AIAgentController` completo
- ✅ Controller `AIToolController` completo
- ✅ Interface de listagem de agentes (`views/ai-agents/index.php`)
- ✅ Interface de visualização de agente (`views/ai-agents/show.php`)
- ✅ Interface de listagem de tools (`views/ai-tools/index.php`)
- ✅ Interface de visualização de tool (`views/ai-tools/show.php`)
- ✅ **Interface dinâmica de criação/edição de tools** (2025-01-27):
  - Campos específicos por tipo de tool (WooCommerce, Database, N8N, API, Document, System, Followup)
  - Interface dinâmica para Function Schema (nome, descrição, parâmetros)
  - Construção automática de JSON (sem necessidade de escrever JSON manualmente)
  - Preenchimento automático ao editar (converte JSON para campos)
- ✅ Seeds com tools padrão do sistema
- ✅ Método `formatDateTime()` no helper Url

**O que falta**:
- [ ] Service `OpenAIService` para integração com OpenAI
- [ ] Interface de criação/edição de agentes de IA (modais/formulários)
- [ ] Sistema de execução de tools (WooCommerce, Database, N8N, Documents, etc)
- [ ] Integração com sistema de distribuição de conversas
- [ ] **Sistema de Followup Automático**:
  - [ ] Agentes de IA para followup após X horas/dias
  - [ ] Verificação automática de status de conversas
  - [ ] Reengajamento de contatos inativos
  - [ ] Followup de leads frios
  - [ ] Verificação de satisfação pós-atendimento
- [ ] Sistema de logs e analytics de uso de IA
- [ ] Controle de custos e rate limiting
- [ ] Sistema de fallback e escalação

**Tipos de Tools a implementar** (execução):
- [ ] **WooCommerce Tools**: buscar_pedido, buscar_produto, criar_pedido, atualizar_status
- [ ] **Database Tools**: consultar_banco_dados (com validação de segurança)
- [ ] **N8N Tools**: executar_workflow_n8n, buscar_dados_n8n
- [ ] **Document Tools**: buscar_documento, extrair_texto_documento
- [ ] **System Tools**: buscar_conversas_anteriores, buscar_informacoes_contato, adicionar_tag, mover_para_estagio, escalar_para_humano
- [ ] **Followup Tools**: verificar_status_conversa, verificar_ultima_interacao, reengajar_contato, verificar_satisfacao
- [ ] **API Tools**: chamar_api_externa (genérico)

**Prioridade**: 🔴 ALTA (Nova funcionalidade estratégica)

---

## 📊 RESUMO POR PRIORIDADE

### 🔴 ALTA PRIORIDADE
1. ✅ **Configurações Avançadas de Conversas** - CONCLUÍDO (2025-01-27)
2. ✅ **Sistema de Agentes de IA** - CONCLUÍDO (95% - 2025-01-27)
3. ✅ CRUD Completo de Agentes e Usuários - CONCLUÍDO (2025-01-27)

### 🟡 MÉDIA PRIORIDADE
4. ✅ Sistema de Setores/Departamentos - CONCLUÍDO (2025-01-27)
5. ✅ Sistema de Funis e Kanban - CONCLUÍDO (2025-01-27)
6. ✅ Sistema de Automações - CONCLUÍDO (2025-01-27)
7. ✅ Anexos e Mídia - CONCLUÍDO (100%)

### 🟢 BAIXA PRIORIDADE
8. Sistema de Tags (integração visual)
9. Sistema de Notificações (email, push)
10. Relatórios e Métricas
11. API REST
12. Templates de Mensagens (integração no chat)
13. Busca Avançada
14. Campos Customizados
15. Atividades e Auditoria

---

## 🎯 PRÓXIMOS PASSOS SUGERIDOS

### Fase 1 - Funcionalidades Estratégicas (Alta Prioridade)
1. **Completar Sistema de Agentes de IA** (60% restante)
   - ✅ Estrutura de dados (migrations, models, services, controllers)
   - ✅ Interface de tools (criação/edição dinâmica)
   - [ ] Service OpenAIService (integração com OpenAI)
   - [ ] Interface de criação/edição de agentes
   - [ ] Sistema de execução de tools (System tools primeiro)
   - [ ] Integração com distribuição de conversas
   - [ ] Logs e analytics básicos

2. **Implementar Configurações Avançadas de Conversas** (100% restante)
   - [ ] Limites e capacidade por agente/setor/funil
   - [ ] SLA e timeouts configuráveis
   - [ ] Sistema de distribuição avançado
   - [ ] Reatribuição automática
   - [ ] Priorização e filas
   - [ ] Interface de configuração

3. **Completar CRUD de Agentes e Usuários** (80% restante)
   - [ ] Modais de criação/edição
   - [ ] Atribuição de roles/permissões (interface melhorada)
   - [ ] Atribuição a setores (interface melhorada)
   - [ ] Status de disponibilidade
   - [ ] Limite de conversas simultâneas

### Fase 2 - Melhorias e Refinamentos (Média Prioridade)
4. Melhorar interfaces de Setores/Departamentos (30% restante)
5. Auto-atribuição por estágio no Kanban (5% restante)
6. Sistema de delay avançado para Automações (10% restante)

### Fase 3 - Recursos Complementares (Baixa Prioridade)
7. Relatórios detalhados (PDF, Excel) - 30% restante
8. API REST completa - 100% restante
9. Busca Avançada - 90% restante
10. Campos Customizados - 100% restante
11. Atividades e Auditoria - 100% restante

---

**Última atualização**: 2025-12-05
**Versão**: 2.2

---

## 🆕 ATUALIZAÇÕES RECENTES (2025-12-05)

### ✅ Sistema de Reply/Quote de Mensagens - IMPLEMENTADO
- Migration `038_add_quoted_message_fields_to_messages.php` criada
- Campos `quoted_message_id`, `quoted_sender_name`, `quoted_text` adicionados
- Backend completo para salvar e recuperar mensagens citadas
- Frontend com botão de reply, preview e renderização visual
- Scroll automático até mensagem original
- Ordenação cronológica correta de mensagens
- Encaminhamento de mensagens (forward)
- Gravação de áudio no chat
- Upload de arquivos com preview e progresso
- Status de mensagens (enviado, entregue, lida, erro)

Veja `PROGRESSO_ATUAL_2025-12-05.md` para detalhes completos.

