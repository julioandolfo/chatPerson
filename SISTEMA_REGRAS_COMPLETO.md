# SISTEMA DE REGRAS COMPLETO - MULTIATENDIMENTO

## 📋 ÍNDICE
1. [Sistema de Permissões Avançado](#1-sistema-de-permissões-avançado)
2. [Sistema Kanban Avançado](#2-sistema-kanban-avançado)
3. [Sistema de Automações Avançado](#3-sistema-de-automações-avançado)

---

## 1. SISTEMA DE PERMISSÕES AVANÇADO

### 1.1 Estrutura Hierárquica de Permissões

#### 1.1.1 Níveis de Acesso (Hierarquia)
```
Nível 0: Super Admin (Acesso Total)
├── Nível 1: Admin (Acesso Completo exceto configurações críticas)
│   ├── Nível 2: Supervisor (Gerenciar equipe e setores)
│   │   ├── Nível 3: Agente Sênior (Acesso amplo + mentoria)
│   │   │   ├── Nível 4: Agente (Acesso padrão)
│   │   │   └── Nível 5: Agente Júnior (Acesso limitado)
│   │   └── Nível 6: Visualizador (Somente leitura)
│   └── Nível 7: API User (Acesso via API apenas)
```

#### 1.1.2 Tipos de Permissões

**A. Permissões de Visualização (View)**
- `conversations.view.own` - Ver apenas conversas próprias
- `conversations.view.assigned` - Ver conversas atribuídas
- `conversations.view.department` - Ver conversas do setor
- `conversations.view.department_tree` - Ver conversas do setor + filhos (hierarquia)
- `conversations.view.team` - Ver conversas da equipe
- `conversations.view.all` - Ver todas as conversas
- `conversations.view.archived` - Ver conversas arquivadas
- `conversations.view.deleted` - Ver conversas deletadas
- `conversations.view.by_status` - Ver por status específico
- `conversations.view.by_priority` - Ver por prioridade específica
- `conversations.view.by_funnel` - Ver por funil específico
- `conversations.view.by_tag` - Ver por tag específica
- `conversations.view.by_date_range` - Ver por período
- `conversations.view.by_agent` - Ver conversas de agente específico
- `conversations.view.by_contact` - Ver conversas de contato específico
- `conversations.view.by_inbox` - Ver conversas de inbox específica

**B. Permissões de Edição (Edit)**
- `conversations.edit.own` - Editar apenas próprias conversas
- `conversations.edit.assigned` - Editar conversas atribuídas
- `conversations.edit.department` - Editar conversas do setor
- `conversations.edit.team` - Editar conversas da equipe
- `conversations.edit.all` - Editar todas as conversas
- `conversations.edit.status` - Alterar status
- `conversations.edit.priority` - Alterar prioridade
- `conversations.edit.assign` - Atribuir conversas
- `conversations.edit.reassign` - Reatribuir conversas
- `conversations.edit.tags` - Gerenciar tags
- `conversations.edit.funnel` - Mover entre funis
- `conversations.edit.stage` - Mover entre estágios
- `conversations.edit.notes` - Adicionar notas internas
- `conversations.edit.custom_fields` - Editar campos customizados

**C. Permissões de Mensagens**
- `messages.send.own` - Enviar em conversas próprias
- `messages.send.assigned` - Enviar em conversas atribuídas
- `messages.send.department` - Enviar em conversas do setor
- `messages.send.all` - Enviar em qualquer conversa
- `messages.send.bulk` - Envio em massa
- `messages.send.templates` - Usar templates
- `messages.send.attachments` - Enviar anexos
- `messages.send.media` - Enviar mídia (imagens/vídeos)
- `messages.edit.own` - Editar próprias mensagens
- `messages.edit.all` - Editar qualquer mensagem
- `messages.delete.own` - Deletar próprias mensagens
- `messages.delete.all` - Deletar qualquer mensagem
- `messages.forward` - Encaminhar mensagens
- `messages.mark_read` - Marcar como lida

**D. Permissões de Contatos**
- `contacts.view.own` - Ver contatos de conversas próprias
- `contacts.view.department` - Ver contatos do setor
- `contacts.view.all` - Ver todos os contatos
- `contacts.create` - Criar contatos
- `contacts.edit.own` - Editar contatos próprios
- `contacts.edit.all` - Editar qualquer contato
- `contacts.delete` - Deletar contatos
- `contacts.merge` - Mesclar contatos duplicados
- `contacts.export` - Exportar contatos
- `contacts.import` - Importar contatos

**E. Permissões de Agentes**
- `agents.view` - Ver agentes
- `agents.view.department` - Ver agentes do setor
- `agents.view.all` - Ver todos os agentes
- `agents.create` - Criar agentes
- `agents.edit.own` - Editar próprio perfil
- `agents.edit.department` - Editar agentes do setor
- `agents.edit.all` - Editar qualquer agente
- `agents.delete` - Deletar agentes
- `agents.assign_conversations` - Atribuir conversas a agentes
- `agents.view_activity` - Ver atividade dos agentes
- `agents.view_reports` - Ver relatórios de agentes

**F. Permissões de Setores**
- `departments.view` - Ver setores
- `departments.view.hierarchy` - Ver hierarquia completa
- `departments.create` - Criar setores
- `departments.edit.own` - Editar próprio setor
- `departments.edit.all` - Editar qualquer setor
- `departments.delete` - Deletar setores
- `departments.assign_agents` - Atribuir agentes a setores
- `departments.view_conversations` - Ver conversas do setor

**G. Permissões de Funis**
- `funnels.view` - Ver funis
- `funnels.view.own` - Ver funis próprios
- `funnels.view.department` - Ver funis do setor
- `funnels.view.all` - Ver todos os funis
- `funnels.create` - Criar funis
- `funnels.edit.own` - Editar funis próprios
- `funnels.edit.all` - Editar qualquer funil
- `funnels.delete` - Deletar funis
- `funnels.move_conversations` - Mover conversas entre funis
- `funnels.manage_stages` - Gerenciar estágios

**H. Permissões de Automações**
- `automations.view` - Ver automações
- `automations.view.own` - Ver automações próprias
- `automations.view.department` - Ver automações do setor
- `automations.view.all` - Ver todas as automações
- `automations.create` - Criar automações
- `automations.edit.own` - Editar automações próprias
- `automations.edit.all` - Editar qualquer automação
- `automations.delete` - Deletar automações
- `automations.activate` - Ativar/desativar automações
- `automations.view_logs` - Ver logs de execução

**I. Permissões de Kanban**
- `kanban.view` - Ver visualização Kanban
- `kanban.view.own` - Ver Kanban de funis próprios
- `kanban.view.department` - Ver Kanban do setor
- `kanban.view.all` - Ver todos os Kanbans
- `kanban.drag_drop.own` - Arrastar conversas próprias
- `kanban.drag_drop.assigned` - Arrastar conversas atribuídas
- `kanban.drag_drop.department` - Arrastar conversas do setor
- `kanban.drag_drop.all` - Arrastar qualquer conversa
- `kanban.bulk_move` - Mover múltiplas conversas
- `kanban.filter` - Usar filtros avançados

**J. Permissões de Inboxes/Canais**
- `inboxes.view` - Ver inboxes
- `inboxes.view.assigned` - Ver inboxes atribuídas
- `inboxes.view.all` - Ver todas as inboxes
- `inboxes.create` - Criar inboxes
- `inboxes.edit` - Editar inboxes
- `inboxes.delete` - Deletar inboxes
- `inboxes.configure` - Configurar inboxes

**K. Permissões de WhatsApp**
- `whatsapp.view` - Ver contas WhatsApp
- `whatsapp.view.own` - Ver contas próprias
- `whatsapp.view.all` - Ver todas as contas
- `whatsapp.create` - Criar contas
- `whatsapp.connect` - Conectar contas
- `whatsapp.disconnect` - Desconectar contas
- `whatsapp.send` - Enviar mensagens
- `whatsapp.send.bulk` - Envio em massa
- `whatsapp.view_qrcode` - Ver QR Code
- `whatsapp.manage` - Gerenciar configurações

**L. Permissões de Relatórios**
- `reports.view` - Ver relatórios
- `reports.view.own` - Ver relatórios próprios
- `reports.view.department` - Ver relatórios do setor
- `reports.view.all` - Ver todos os relatórios
- `reports.export` - Exportar relatórios
- `reports.custom` - Criar relatórios customizados

**M. Permissões Administrativas**
- `admin.users` - Gerenciar usuários
- `admin.roles` - Gerenciar roles
- `admin.permissions` - Gerenciar permissões
- `admin.settings` - Configurações gerais
- `admin.integrations` - Gerenciar integrações
- `admin.webhooks` - Gerenciar webhooks
- `admin.backup` - Backup e restore
- `admin.logs` - Ver logs do sistema
- `admin.audit` - Auditoria completa

### 1.2 Regras de Permissão Condicionais

#### 1.2.1 Regras Baseadas em Contexto

**A. Regras Temporais**
- Horário de trabalho: Permissões ativas apenas em horário comercial
- Dias da semana: Permissões diferentes por dia
- Fuso horário: Permissões baseadas no timezone do agente
- Expiração: Permissões temporárias com data de expiração

**B. Regras Baseadas em Status**
- Conversa aberta: Permissões de edição ativas
- Conversa resolvida: Apenas visualização
- Conversa arquivada: Permissões limitadas
- Conversa deletada: Apenas admin pode ver

**C. Regras Baseadas em Atribuição**
- Conversa não atribuída: Apenas supervisores podem atribuir
- Conversa atribuída: Agente atribuído tem permissões completas
- Conversa reatribuída: Histórico de permissões mantido

**D. Regras Baseadas em Prioridade**
- Alta prioridade: Permissões especiais para supervisores
- Baixa prioridade: Permissões padrão
- Urgente: Notificações e permissões ampliadas

**E. Regras Baseadas em Tags**
- Tag "VIP": Permissões especiais
- Tag "Bloqueado": Permissões limitadas
- Tag "Teste": Permissões de desenvolvimento

**F. Regras Baseadas em Funil/Estágio**
- Estágio inicial: Permissões de criação
- Estágio intermediário: Permissões de edição
- Estágio final: Permissões de visualização apenas

### 1.3 Permissões Dinâmicas e Herança

#### 1.3.1 Herança de Permissões
```
Super Admin
  └── Herda todas as permissões

Admin
  └── Herda permissões de Supervisor + Admin específicas

Supervisor
  └── Herda permissões de Agente Sênior + Supervisor específicas

Agente Sênior
  └── Herda permissões de Agente + Agente Sênior específicas

Agente
  └── Permissões base

Agente Júnior
  └── Permissões limitadas (subconjunto de Agente)
```

#### 1.3.2 Permissões por Setor (Hierarquia)
```
Setor Raiz (Ex: Vendas)
├── Setor Filho 1 (Ex: Vendas Online)
│   ├── Agente tem acesso ao próprio setor
│   └── Supervisor tem acesso ao setor + filhos
└── Setor Filho 2 (Ex: Vendas Presencial)
    ├── Agente tem acesso ao próprio setor
    └── Supervisor tem acesso ao setor + filhos
```

#### 1.3.3 Permissões por Equipe
- Agente pode ver conversas da equipe
- Supervisor pode gerenciar equipe
- Permissões podem ser sobrepostas por setor

### 1.4 Permissões Granulares por Campo

#### 1.4.1 Campos Customizados
- `custom_field.view.{field_name}` - Ver campo específico
- `custom_field.edit.{field_name}` - Editar campo específico
- `custom_field.delete.{field_name}` - Deletar campo específico

#### 1.4.2 Campos do Sistema
- `conversation.view.assignee` - Ver agente atribuído
- `conversation.view.priority` - Ver prioridade
- `conversation.view.tags` - Ver tags
- `conversation.view.notes` - Ver notas internas
- `conversation.view.history` - Ver histórico completo

### 1.5 Validação de Permissões em Tempo Real

#### 1.5.1 Verificações Obrigatórias
- Antes de visualizar conversa
- Antes de editar conversa
- Antes de enviar mensagem
- Antes de mover no Kanban
- Antes de executar automação
- Antes de acessar relatório

#### 1.5.2 Cache de Permissões
- Cache em memória (Redis) para performance
- Invalidação automática quando permissões mudam
- TTL configurável por tipo de permissão

---

## 2. SISTEMA KANBAN AVANÇADO

### 2.1 Estrutura de Funis e Estágios

#### 2.1.1 Tipos de Funis
- **Funis de Vendas**: Lead → Qualificação → Proposta → Fechamento
- **Funis de Suporte**: Novo → Em Andamento → Aguardando → Resolvido
- **Funis de Onboarding**: Cadastro → Validação → Ativação → Concluído
- **Funis Customizados**: Configuráveis pelo usuário

#### 2.1.2 Propriedades de Estágio

**A. Propriedades Básicas**
- Nome do estágio
- Posição (ordem)
- Cor (hexadecimal)
- Ícone
- Descrição
- Limite de conversas simultâneas
- Tempo máximo no estágio (SLA)

**B. Propriedades de Atribuição**
- Auto-atribuição: Automático/Manual/Nenhum
- Departamento padrão para auto-atribuição
- Agente padrão para auto-atribuição
- Distribuição: Round-robin/Por carga/Por especialidade
- Regras de reatribuição automática

**C. Propriedades de Movimentação**
- Permitir mover para estágios anteriores: Sim/Não
- Permitir pular estágios: Sim/Não
- Estágios bloqueados (não pode mover para)
- Estágios obrigatórios (deve passar por)
- Validações antes de mover

**D. Propriedades de Notificação**
- Notificar agente ao entrar no estágio
- Notificar supervisor ao entrar no estágio
- Notificar contato ao entrar no estágio
- Template de notificação

**E. Propriedades de Automação**
- Automações que executam ao entrar
- Automações que executam ao sair
- Automações que executam enquanto está no estágio
- Condições para execução

### 2.2 Regras de Movimentação no Kanban

#### 2.2.1 Regras de Validação

**A. Validações Obrigatórias**
- Campos obrigatórios preenchidos
- Tags específicas presentes
- Status da conversa válido
- Permissões do usuário
- Horário permitido para movimentação

**B. Validações Condicionais**
- Se conversa tem tag X, não pode mover para estágio Y
- Se conversa está atribuída a agente X, pode mover apenas para estágios Y e Z
- Se conversa tem prioridade alta, pode pular estágios intermediários
- Se conversa está no estágio X há mais de Y horas, pode mover para Z

**C. Validações de Negócio**
- Conversa não pode voltar para estágio anterior após ser resolvida
- Conversa não pode pular estágio de aprovação
- Conversa não pode sair do funil sem passar por estágio final
- Conversa não pode entrar em estágio sem pré-requisitos

#### 2.2.2 Regras de Auto-Atribuição

**A. Round-Robin**
- Distribuição igual entre agentes disponíveis
- Considera carga atual de conversas
- Considera especialidade do agente
- Considera horário de trabalho

**B. Por Carga**
- Atribui ao agente com menor carga
- Considera limite máximo de conversas
- Considera conversas por estágio
- Considera conversas por prioridade

**C. Por Especialidade**
- Atribui baseado em tags da conversa
- Atribui baseado em histórico do contato
- Atribui baseado em tipo de inbox
- Atribui baseado em idioma

**D. Por Performance**
- Atribui ao agente com melhor tempo de resposta
- Atribui ao agente com maior taxa de resolução
- Atribui ao agente com melhor avaliação
- Considera métricas históricas

#### 2.2.3 Regras de Movimentação Automática

**A. Por Tempo**
- Mover após X horas sem resposta
- Mover após X horas sem atividade
- Mover após X dias no estágio
- Mover em horário específico

**B. Por Condição**
- Mover quando tag específica é adicionada
- Mover quando mensagem é recebida
- Mover quando campo customizado muda
- Mover quando automação é executada

**C. Por Evento Externo**
- Mover quando webhook é recebido
- Mover quando integração externa atualiza
- Mover quando API é chamada
- Mover quando sistema externo notifica

### 2.3 Visualização e Filtros Kanban

#### 2.3.1 Tipos de Visualização

**A. Visualização Padrão**
- Colunas por estágio
- Cards por conversa
- Drag & drop habilitado
- Contadores por estágio

**B. Visualização por Agente**
- Colunas por agente
- Cards por conversa atribuída
- Filtro por setor/equipe

**C. Visualização por Prioridade**
- Colunas por prioridade
- Cards por conversa
- Cores diferenciadas

**D. Visualização por Tag**
- Colunas por tag principal
- Cards por conversa
- Múltiplas tags visíveis

**E. Visualização por Data**
- Colunas por período (Hoje/Amanhã/Esta Semana)
- Cards por conversa
- Filtro por data de criação/atualização

#### 2.3.2 Filtros Avançados

**A. Filtros Básicos**
- Por funil
- Por estágio
- Por agente
- Por setor
- Por inbox
- Por status
- Por prioridade
- Por tags

**B. Filtros Temporais**
- Por data de criação
- Por data de atualização
- Por data de última mensagem
- Por tempo no estágio
- Por tempo sem resposta
- Por horário específico

**C. Filtros de Conteúdo**
- Por palavra-chave na mensagem
- Por nome do contato
- Por email do contato
- Por telefone do contato
- Por campos customizados

**D. Filtros Compostos**
- E (AND): Todas as condições devem ser verdadeiras
- OU (OR): Pelo menos uma condição deve ser verdadeira
- NÃO (NOT): Condição não deve ser verdadeira
- Agrupamento de condições

**E. Filtros Salvos**
- Salvar filtros como favoritos
- Compartilhar filtros com equipe
- Filtros padrão por usuário
- Filtros por role/permissão

#### 2.3.3 Ordenação

**A. Ordenação Padrão**
- Por data de atualização (mais recente primeiro)
- Por prioridade (alta primeiro)
- Por data de criação (mais antiga primeiro)
- Por tempo no estágio (mais tempo primeiro)

**B. Ordenação Customizada**
- Por campo customizado
- Por avaliação do contato
- Por valor estimado
- Por número de mensagens
- Por última atividade

### 2.4 Ações em Massa no Kanban

#### 2.4.1 Seleção Múltipla
- Selecionar todas as conversas visíveis
- Selecionar por filtro
- Selecionar manualmente
- Desmarcar todas

#### 2.4.2 Ações Disponíveis
- Mover para estágio
- Atribuir a agente
- Adicionar tag
- Remover tag
- Alterar prioridade
- Alterar status
- Adicionar nota
- Arquivar
- Deletar (com permissão)

### 2.5 Métricas e Indicadores no Kanban

#### 2.5.1 Métricas por Estágio
- Total de conversas
- Conversas novas (últimas 24h)
- Tempo médio no estágio
- Taxa de conversão para próximo estágio
- Taxa de abandono do estágio
- Conversas bloqueadas (sem movimento)

#### 2.5.2 Métricas por Funil
- Taxa de conversão geral
- Tempo médio no funil
- Conversas por estágio
- Conversas por agente
- Conversas por setor
- Conversas por período

#### 2.5.3 Alertas e Notificações
- Estágio com muitas conversas (limite excedido)
- Conversa parada há muito tempo
- SLA próximo de vencer
- Conversa sem atribuição
- Conversa sem resposta há X tempo

### 2.6 Histórico e Auditoria

#### 2.6.1 Log de Movimentações
- Quem moveu
- Quando moveu
- De qual estágio
- Para qual estágio
- Motivo (se informado)
- Automação que moveu (se aplicável)

#### 2.6.2 Timeline de Movimentações
- Visualização cronológica
- Filtro por período
- Filtro por usuário
- Filtro por conversa
- Exportação de histórico

---

## 3. SISTEMA DE AUTOMAÇÕES AVANÇADO

### 3.1 Tipos de Triggers (Gatilhos)

#### 3.1.1 Triggers de Conversa

**A. Criação de Conversa**
- Nova conversa criada
- Nova conversa criada em inbox específica
- Nova conversa criada em funil específico
- Nova conversa criada por canal específico
- Nova conversa criada em horário específico
- Nova conversa criada com tag específica
- Nova conversa criada por contato específico
- Nova conversa criada com palavra-chave

**B. Atualização de Conversa**
- Status da conversa mudou
- Prioridade da conversa mudou
- Agente atribuído mudou
- Setor atribuído mudou
- Tags adicionadas/removidas
- Campos customizados alterados
- Nota interna adicionada

**C. Movimentação no Funil**
- Conversa entrou em estágio específico
- Conversa saiu de estágio específico
- Conversa mudou de funil
- Conversa está em estágio há X tempo
- Conversa não mudou de estágio há X tempo

**D. Resolução de Conversa**
- Conversa foi resolvida
- Conversa foi arquivada
- Conversa foi deletada
- Conversa foi reaberta

#### 3.1.2 Triggers de Mensagem

**A. Recebimento de Mensagem**
- Nova mensagem recebida
- Nova mensagem recebida de contato específico
- Nova mensagem recebida em conversa específica
- Nova mensagem recebida em inbox específica
- Nova mensagem recebida contém palavra-chave
- Nova mensagem recebida contém anexo
- Nova mensagem recebida em horário específico

**B. Envio de Mensagem**
- Mensagem enviada
- Mensagem enviada para contato específico
- Mensagem enviada em conversa específica
- Mensagem enviada com template específico

**C. Status de Mensagem**
- Mensagem foi entregue
- Mensagem foi lida
- Mensagem falhou ao enviar

#### 3.1.3 Triggers Temporais

**A. Agendados**
- Em data/hora específica
- Diariamente em horário específico
- Semanalmente em dia/hora específica
- Mensalmente em dia/hora específica
- Anualmente em data/hora específica

**B. Baseados em Tempo**
- Após X minutos/horas/dias da criação da conversa
- Após X minutos/horas/dias da última mensagem
- Após X minutos/horas/dias no estágio atual
- Após X minutos/horas/dias sem resposta
- Após X minutos/horas/dias sem atividade
- Antes de X minutos/horas/dias do SLA

**C. Baseados em Horário**
- Durante horário comercial
- Fora do horário comercial
- Em dias úteis
- Em fins de semana
- Em feriados
- Em horário específico do dia

#### 3.1.4 Triggers de Contato

**A. Criação/Atualização**
- Novo contato criado
- Contato atualizado
- Campo customizado do contato alterado
- Tag adicionada ao contato
- Contato mesclado

**B. Atividade do Contato**
- Contato enviou primeira mensagem
- Contato não enviou mensagem há X tempo
- Contato voltou após X tempo inativo
- Contato completou formulário
- Contato visitou página específica

#### 3.1.5 Triggers de Agente

**A. Atividade**
- Agente entrou online
- Agente saiu offline
- Agente atribuído a conversa
- Agente reatribuído de conversa
- Agente atingiu limite de conversas

**B. Performance**
- Agente resolveu X conversas hoje
- Agente tem tempo médio de resposta acima de X
- Agente não respondeu há X tempo

#### 3.1.6 Triggers Externos

**A. Webhooks**
- Webhook recebido de URL específica
- Webhook recebido com payload específico
- Webhook recebido com header específico

**B. APIs**
- Chamada de API específica
- Resposta de API específica
- Erro em chamada de API

**C. Integrações**
- Evento de integração externa
- Sincronização concluída
- Erro em integração

### 3.2 Condições (Filtros)

#### 3.2.1 Condições de Conversa

**A. Propriedades Básicas**
- Status é igual/diferente de X
- Prioridade é igual/diferente de X
- Funil é igual/diferente de X
- Estágio é igual/diferente de X
- Inbox é igual/diferente de X
- Canal é igual/diferente de X

**B. Atribuição**
- Está atribuída/Não está atribuída
- Atribuída a agente específico
- Atribuída a setor específico
- Atribuída a equipe específica
- Não atribuída há X tempo

**C. Tags**
- Tem tag X
- Não tem tag X
- Tem todas as tags [X, Y, Z]
- Tem pelo menos uma tag [X, Y, Z]
- Não tem nenhuma tag [X, Y, Z]

**D. Tempo**
- Criada há mais/menos de X tempo
- Atualizada há mais/menos de X tempo
- Última mensagem há mais/menos de X tempo
- No estágio há mais/menos de X tempo
- Sem resposta há mais/menos de X tempo
- Sem atividade há mais/menos de X tempo

**E. Campos Customizados**
- Campo X é igual/diferente de Y
- Campo X contém Y
- Campo X é maior/menor que Y
- Campo X está vazio/preenchido
- Campo X está em lista [Y, Z]

#### 3.2.2 Condições de Mensagem

**A. Propriedades**
- Tipo é texto/imagem/vídeo/áudio/arquivo
- Contém palavra-chave X
- Não contém palavra-chave X
- Contém anexo
- Não contém anexo
- Enviada por agente/contato
- Enviada há mais/menos de X tempo

**B. Conteúdo**
- Mensagem contém emoji
- Mensagem contém link
- Mensagem contém número de telefone
- Mensagem contém email
- Mensagem tem mais de X caracteres
- Mensagem tem menos de X caracteres

#### 3.2.3 Condições de Contato

**A. Propriedades**
- Nome contém X
- Email é igual/diferente de X
- Telefone é igual/diferente de X
- Tem tag X
- Não tem tag X
- Campo customizado X é igual a Y

**B. Histórico**
- Tem X conversas anteriores
- Não tem conversas anteriores
- Última conversa há mais/menos de X tempo
- Resolveu X conversas anteriormente
- Tem avaliação média acima/abaixo de X

#### 3.2.4 Condições de Agente

**A. Status**
- Está online/offline
- Está disponível/ocupado/ausente
- Tem X conversas atribuídas
- Tem menos/mais de X conversas atribuídas

**B. Performance**
- Tempo médio de resposta é maior/menor que X
- Taxa de resolução é maior/menor que X
- Avaliação média é maior/menor que X

#### 3.2.5 Condições Compostas

**A. Operadores Lógicos**
- E (AND): Todas as condições devem ser verdadeiras
- OU (OR): Pelo menos uma condição deve ser verdadeira
- NÃO (NOT): Condição não deve ser verdadeira
- XOR: Apenas uma condição deve ser verdadeira

**B. Agrupamento**
- Agrupar condições com parênteses
- Prioridade de avaliação
- Avaliação sequencial ou paralela

**C. Condições Aninhadas**
- Condições dentro de condições
- Múltiplos níveis de aninhamento
- Avaliação recursiva

### 3.3 Ações (Actions)

#### 3.3.1 Ações de Conversa

**A. Movimentação**
- Mover para estágio específico
- Mover para funil específico
- Mover para próximo estágio
- Mover para estágio anterior
- Não mover (bloquear movimentação)

**B. Atribuição**
- Atribuir a agente específico
- Atribuir a setor específico
- Atribuir a equipe específica
- Reatribuir (redistribuir)
- Remover atribuição

**C. Status e Prioridade**
- Alterar status para X
- Alterar prioridade para X
- Aumentar prioridade
- Diminuir prioridade

**D. Tags**
- Adicionar tag X
- Remover tag X
- Substituir tags [X] por [Y]
- Limpar todas as tags

**E. Campos Customizados**
- Definir campo X como Y
- Incrementar campo X em Y
- Decrementar campo X em Y
- Limpar campo X

**F. Notas**
- Adicionar nota interna
- Adicionar nota pública
- Adicionar lembrete

**G. Resolução**
- Resolver conversa
- Arquivar conversa
- Reabrir conversa
- Deletar conversa (com confirmação)

#### 3.3.2 Ações de Mensagem

**A. Envio**
- Enviar mensagem de texto
- Enviar mensagem com template
- Enviar mensagem com anexo
- Enviar mensagem agendada
- Enviar mensagem em massa

**B. Templates**
- Usar template X
- Substituir variáveis no template
- Personalizar template dinamicamente

**C. Anexos**
- Anexar arquivo específico
- Anexar arquivo de URL
- Anexar arquivo de campo customizado

#### 3.3.3 Ações de Notificação

**A. Notificações Internas**
- Notificar agente específico
- Notificar setor específico
- Notificar equipe específica
- Notificar supervisor
- Notificar admin

**B. Notificações Externas**
- Enviar email
- Enviar SMS
- Enviar push notification
- Enviar webhook
- Enviar para Slack/Discord

**C. Templates de Notificação**
- Usar template de email
- Usar template de SMS
- Personalizar conteúdo

#### 3.3.4 Ações de Integração

**A. Webhooks**
- Enviar webhook para URL
- Enviar webhook com payload customizado
- Enviar webhook com headers customizados
- Aguardar resposta do webhook

**B. APIs**
- Chamar API externa
- Chamar API com autenticação
- Processar resposta da API
- Tratar erros da API

**C. Sincronização**
- Sincronizar com CRM externo
- Sincronizar com sistema de vendas
- Sincronizar com banco de dados externo

#### 3.3.5 Ações de Tarefa

**A. Criação**
- Criar tarefa para agente
- Criar tarefa para setor
- Criar tarefa com prazo
- Criar tarefa com prioridade

**B. Lembretes**
- Criar lembrete
- Agendar lembrete
- Lembrete recorrente

#### 3.3.6 Ações de Delay/Aguardo

**A. Delays Temporais**
- Aguardar X minutos/horas/dias
- Aguardar até data/hora específica
- Aguardar até próximo horário comercial
- Aguardar até próximo dia útil

**B. Delays Condicionais**
- Aguardar até condição ser verdadeira
- Aguardar até evento ocorrer
- Aguardar até webhook ser recebido
- Aguardar até API responder

#### 3.3.7 Ações de Loop/Repetição

**A. Loops**
- Repetir ação X vezes
- Repetir ação até condição ser verdadeira
- Repetir ação para cada item em lista
- Repetir ação com delay entre iterações

**B. Condições de Parada**
- Parar se condição for verdadeira
- Parar se erro ocorrer
- Parar após X tentativas
- Continuar mesmo se erro ocorrer

### 3.4 Fluxo de Execução de Automações

#### 3.4.1 Ordem de Execução

**A. Sequencial**
- Executar ações em ordem
- Aguardar conclusão de cada ação
- Continuar mesmo se ação falhar (configurável)
- Parar se ação crítica falhar

**B. Paralelo**
- Executar múltiplas ações simultaneamente
- Aguardar todas concluírem
- Continuar se algumas falharem
- Parar se ação crítica falhar

**C. Condicional**
- Executar ação apenas se condição for verdadeira
- Executar ação A ou B baseado em condição
- Executar ação A, depois B se A for bem-sucedida

#### 3.4.2 Tratamento de Erros

**A. Tipos de Erro**
- Erro de validação
- Erro de permissão
- Erro de integração
- Erro de timeout
- Erro genérico

**B. Ações em Caso de Erro**
- Parar execução
- Continuar execução
- Tentar novamente (com limite)
- Executar ação alternativa
- Registrar erro e notificar

**C. Logs de Erro**
- Registrar todos os erros
- Incluir stack trace
- Incluir contexto da execução
- Notificar administradores

#### 3.4.3 Performance e Limites

**A. Limites de Execução**
- Máximo de automações por conversa simultâneas
- Máximo de ações por automação
- Tempo máximo de execução
- Memória máxima utilizada

**B. Otimização**
- Cache de resultados
- Execução assíncrona para ações pesadas
- Queue para ações demoradas
- Batch processing para ações em massa

**C. Monitoramento**
- Tempo de execução
- Taxa de sucesso/erro
- Uso de recursos
- Alertas de performance

### 3.5 Variáveis e Templates

#### 3.5.1 Variáveis Disponíveis

**A. Variáveis de Conversa**
- `{{conversation.id}}`
- `{{conversation.status}}`
- `{{conversation.priority}}`
- `{{conversation.funnel}}`
- `{{conversation.stage}}`
- `{{conversation.created_at}}`
- `{{conversation.updated_at}}`
- `{{conversation.agent.name}}`
- `{{conversation.department.name}}`

**B. Variáveis de Contato**
- `{{contact.name}}`
- `{{contact.email}}`
- `{{contact.phone}}`
- `{{contact.custom_field.X}}`

**C. Variáveis de Mensagem**
- `{{message.content}}`
- `{{message.sender}}`
- `{{message.created_at}}`

**D. Variáveis de Agente**
- `{{agent.name}}`
- `{{agent.email}}`
- `{{agent.department}}`

**E. Variáveis de Sistema**
- `{{system.date}}`
- `{{system.time}}`
- `{{system.company_name}}`

#### 3.5.2 Funções Disponíveis

**A. Funções de String**
- `upper()`, `lower()`, `capitalize()`
- `trim()`, `replace()`, `substring()`
- `contains()`, `startsWith()`, `endsWith()`

**B. Funções de Data**
- `format_date()`, `add_days()`, `subtract_days()`
- `is_weekend()`, `is_business_hours()`

**C. Funções Matemáticas**
- `add()`, `subtract()`, `multiply()`, `divide()`
- `round()`, `ceil()`, `floor()`

**D. Funções Condicionais**
- `if()`, `if_else()`, `switch()`
- `equals()`, `not_equals()`, `greater_than()`

### 3.6 Testes e Debugging

#### 3.6.1 Modo de Teste
- Executar automação em modo teste
- Não executar ações reais
- Simular resultados
- Mostrar preview das ações

#### 3.6.2 Logs de Execução
- Log detalhado de cada passo
- Valores das variáveis em cada passo
- Resultado de cada condição
- Tempo de execução de cada ação

#### 3.6.3 Validação
- Validar sintaxe antes de salvar
- Validar condições antes de ativar
- Validar ações antes de executar
- Alertar sobre possíveis problemas

### 3.7 Automações Pré-Configuradas

#### 3.7.1 Automações de Boas-Vindas
- Saudação automática na primeira mensagem
- Envio de FAQ após X tempo sem resposta
- Redirecionamento para chatbot

#### 3.7.2 Automações de SLA
- Alertar quando SLA está próximo de vencer
- Escalar para supervisor quando SLA vence
- Mover para estágio específico quando SLA vence

#### 3.7.3 Automações de Distribuição
- Distribuir conversas não atribuídas
- Rebalancear carga entre agentes
- Atribuir baseado em especialidade

#### 3.7.4 Automações de Follow-up
- Enviar mensagem após resolução
- Solicitar feedback após X dias
- Reabrir conversa se contato responder

---

## 4. INTEGRAÇÃO ENTRE SISTEMAS

### 4.1 Permissões + Kanban
- Verificar permissões antes de mover no Kanban
- Registrar movimentação no log de auditoria
- Notificar quando conversa é movida sem permissão

### 4.2 Permissões + Automações
- Verificar permissões antes de executar automação
- Automações podem ter permissões específicas
- Log de automações executadas com permissões

### 4.3 Kanban + Automações
- Automações podem mover conversas no Kanban
- Automações podem ser acionadas por movimentação no Kanban
- Validações do Kanban aplicadas a automações

---

## 5. CONFIGURAÇÕES E PERSONALIZAÇÃO

### 5.1 Configurações Globais
- Habilitar/desabilitar funcionalidades
- Limites globais
- Padrões globais
- Políticas de segurança

### 5.2 Configurações por Setor
- Permissões padrão do setor
- Funis padrão do setor
- Automações padrão do setor
- Regras específicas do setor

### 5.3 Configurações por Funil
- Regras específicas do funil
- Automações específicas do funil
- Permissões específicas do funil
- Validações específicas do funil

---

Este documento define um sistema completo e robusto de regras para permissões, Kanban e automações. Todas essas regras serão implementadas no código do sistema.

