# 📋 Projeto: Ações de Conversa e Agendamento de Mensagens

## 🎯 Objetivo

Implementar um sistema completo de ações rápidas para conversas e agendamento de mensagens/lembretes.

---

## 📦 Funcionalidades Propostas

### 1. **Dropdown de Ações na Lista de Conversas**

**Localização**: Substituir o botão atual de Fixar/Desfixar por um dropdown com múltiplas ações.

**Ações disponíveis**:
- ✅ **Fixar/Desfixar** (já existe, apenas mover para dropdown)
- ✅ **Marcar como Não Lido** (marcar todas mensagens como não lidas)
- ✅ **Marcar como Lido** (marcar todas mensagens como lidas)
- 🔔 **Agendar Lembrete** (novo - ver detalhes abaixo)

**UI/UX**:
- Ícone de 3 pontos verticais (`⋮`) ou seta dropdown
- Dropdown aparece ao lado direito do item da conversa
- Ações com ícones e textos claros
- Feedback visual após ação (toast/notificação)

---

### 2. **Agendar Mensagem no Chat**

**Localização**: Botão ao lado do botão de gravar áudio na barra de input do chat.

**Funcionalidade**:
- Abre modal para agendar envio de mensagem
- Campos:
  - **Mensagem** (textarea, suporta anexos?)
  - **Data e Hora** (datetime picker)
  - **Opção**: Enviar apenas se conversa ainda estiver aberta
  - **Opção**: Cancelar se já foi respondida

**UI/UX**:
- Ícone de calendário/relógio
- Modal estilo Metronic
- Preview da mensagem agendada
- Lista de mensagens agendadas pendentes (opcional)

---

### 3. **Sistema de Lembretes**

**Conceito**: Criar um lembrete para retornar à conversa em um momento específico.

**Funcionalidade**:
- Ao clicar em "Agendar Lembrete" no dropdown:
  - Abre modal simples com:
    - **Data e Hora** do lembrete
    - **Nota opcional** (ex: "Verificar se cliente respondeu")
  - Quando chegar a hora:
    - **Notificação** no sistema
    - **Badge** na conversa indicando lembrete ativo
    - Opção de marcar como resolvido

**Casos de uso**:
- "Lembrar de responder amanhã às 10h"
- "Verificar se cliente pagou em 3 dias"
- "Retornar contato em 1 semana"

---

## 🗄️ Estrutura de Banco de Dados

### Nova Tabela: `scheduled_messages`

```sql
CREATE TABLE scheduled_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    user_id INT NOT NULL COMMENT 'Quem agendou',
    content TEXT NOT NULL COMMENT 'Conteúdo da mensagem',
    attachments JSON NULL COMMENT 'Anexos (se houver)',
    scheduled_at DATETIME NOT NULL COMMENT 'Data/hora agendada',
    sent_at DATETIME NULL COMMENT 'Quando foi enviada (NULL = pendente)',
    status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, sent, cancelled, failed',
    cancel_if_resolved TINYINT(1) DEFAULT 0 COMMENT 'Cancelar se conversa foi resolvida',
    cancel_if_responded TINYINT(1) DEFAULT 0 COMMENT 'Cancelar se já foi respondida',
    error_message TEXT NULL COMMENT 'Erro ao enviar (se houver)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_user_id (user_id),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_status (status),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Nova Tabela: `conversation_reminders`

```sql
CREATE TABLE conversation_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    user_id INT NOT NULL COMMENT 'Quem criou o lembrete',
    reminder_at DATETIME NOT NULL COMMENT 'Data/hora do lembrete',
    note TEXT NULL COMMENT 'Nota opcional',
    is_resolved TINYINT(1) DEFAULT 0 COMMENT 'Se foi resolvido/marcado como feito',
    resolved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_user_id (user_id),
    INDEX idx_reminder_at (reminder_at),
    INDEX idx_is_resolved (is_resolved),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Alterações em `conversations` (se necessário)

- Já temos `pinned` e `pinned_at` ✅
- Podemos adicionar `last_read_at` para rastrear última leitura (opcional)

---

## 🔄 Fluxos de Trabalho

### Fluxo 1: Marcar como Lido/Não Lido

```
1. Usuário clica no dropdown → "Marcar como Lido"
2. Frontend chama: POST /conversations/{id}/mark-read
3. Backend:
   - Atualiza read_at de todas mensagens do contato = NULL → NOW()
   - Invalida cache
   - Retorna sucesso
4. Frontend:
   - Remove badge de não lido
   - Atualiza contador global
   - Mostra toast de confirmação
```

### Fluxo 2: Agendar Mensagem

```
1. Usuário clica em "Agendar Mensagem" no chat
2. Modal abre com formulário
3. Usuário preenche mensagem, data/hora, opções
4. Frontend chama: POST /conversations/{id}/schedule-message
5. Backend:
   - Valida data/hora (deve ser futuro)
   - Cria registro em scheduled_messages
   - Retorna sucesso
6. Frontend:
   - Fecha modal
   - Mostra toast: "Mensagem agendada para {data/hora}"
   - Opcional: Mostra badge na conversa indicando mensagem agendada
```

### Fluxo 3: Processar Mensagens Agendadas (Cron/Job)

```
1. Job roda a cada minuto (ou via cron)
2. Busca scheduled_messages onde:
   - status = 'pending'
   - scheduled_at <= NOW()
3. Para cada mensagem:
   - Verifica condições de cancelamento (se conversa resolvida/respondida)
   - Se OK, envia mensagem via ConversationService
   - Atualiza status = 'sent' ou 'failed'
   - Se falhou, salva error_message
```

### Fluxo 4: Criar Lembrete

```
1. Usuário clica no dropdown → "Agendar Lembrete"
2. Modal abre com:
   - Campo de data/hora
   - Campo de nota (opcional)
3. Frontend chama: POST /conversations/{id}/reminders
4. Backend:
   - Valida data/hora (deve ser futuro)
   - Cria registro em conversation_reminders
   - Retorna sucesso
5. Frontend:
   - Fecha modal
   - Mostra toast: "Lembrete criado para {data/hora}"
   - Badge aparece na conversa
```

### Fluxo 5: Processar Lembretes (Cron/Job)

```
1. Job roda a cada minuto
2. Busca conversation_reminders onde:
   - is_resolved = 0
   - reminder_at <= NOW()
3. Para cada lembrete:
   - Cria notificação para o usuário
   - Badge aparece na conversa
   - Usuário pode marcar como resolvido
```

---

## 🎨 UI/UX - Detalhamento

### Dropdown de Ações na Lista

**Posicionamento**:
- Botão de 3 pontos (`⋮`) ou ícone de ações
- Ao lado direito do item da conversa
- Sempre visível (hover ou sempre)

**Itens do dropdown**:
```
┌─────────────────────────────┐
│ 📌 Fixar                    │
│ 👁️ Marcar como Lido         │
│ 🔴 Marcar como Não Lido     │
│ 🔔 Agendar Lembrete         │
└─────────────────────────────┘
```

**Estados**:
- Se fixada: "📌 Desfixar"
- Se todas lidas: "🔴 Marcar como Não Lido" (desabilitar "Marcar como Lido"?)
- Se tem lembrete ativo: Mostrar badge no item

### Modal de Agendar Mensagem

**Layout**:
```
┌─────────────────────────────────────┐
│  Agendar Mensagem              [X] │
├─────────────────────────────────────┤
│                                     │
│  Mensagem:                         │
│  ┌─────────────────────────────┐  │
│  │ Digite sua mensagem aqui...  │  │
│  │                               │  │
│  └─────────────────────────────┘  │
│                                     │
│  📎 Anexar arquivo (opcional)      │
│                                     │
│  Data e Hora:                      │
│  ┌─────────────────────────────┐  │
│  │ [📅] 08/12/2025  [🕐] 14:30 │  │
│  └─────────────────────────────┘  │
│                                     │
│  ⚙️ Opções:                        │
│  ☐ Cancelar se conversa foi        │
│     resolvida                      │
│  ☐ Cancelar se já foi respondida   │
│                                     │
│  ┌──────────┐  ┌──────────────┐  │
│  │ Cancelar │  │  Agendar     │  │
│  └──────────┘  └──────────────┘  │
└─────────────────────────────────────┘
```

### Modal de Agendar Lembrete

**Layout**:
```
┌─────────────────────────────────────┐
│  Agendar Lembrete              [X] │
├─────────────────────────────────────┤
│                                     │
│  Data e Hora do Lembrete:          │
│  ┌─────────────────────────────┐  │
│  │ [📅] 08/12/2025  [🕐] 10:00 │  │
│  └─────────────────────────────┘  │
│                                     │
│  Nota (opcional):                  │
│  ┌─────────────────────────────┐  │
│  │ Verificar se cliente         │  │
│  │ respondeu                    │  │
│  └─────────────────────────────┘  │
│                                     │
│  ┌──────────┐  ┌──────────────┐  │
│  │ Cancelar │  │  Criar       │  │
│  └──────────┘  └──────────────┘  │
└─────────────────────────────────────┘
```

---

## 💡 Sugestões e Melhorias

### 1. **Visualização de Mensagens Agendadas**

- **Badge** na conversa mostrando quantas mensagens estão agendadas
- **Lista** no sidebar ou modal mostrando todas mensagens agendadas pendentes
- **Opção de editar/cancelar** mensagens agendadas antes do envio

### 2. **Lembretes Recorrentes**

- Permitir criar lembretes recorrentes (ex: "Toda segunda-feira às 10h")
- Útil para follow-ups semanais

### 3. **Templates de Mensagens Agendadas**

- Salvar mensagens agendadas como templates
- Reutilizar em outras conversas

### 4. **Notificações Push**

- Notificar quando lembrete disparar
- Notificar quando mensagem agendada for enviada (ou falhar)

### 5. **Histórico de Mensagens Agendadas**

- Mostrar histórico de mensagens já enviadas
- Útil para auditoria e análise

### 6. **Agendamento em Massa**

- Selecionar múltiplas conversas
- Agendar mesma mensagem para todas

### 7. **Validações Inteligentes**

- Não permitir agendar mensagem no passado
- Avisar se data/hora está muito distante (ex: > 1 ano)
- Sugerir horários baseados em histórico de resposta do contato

### 8. **Integração com Automações**

- Permitir que automações criem mensagens agendadas
- Ex: "Se cliente não responder em 24h, agendar follow-up"

### 9. **Relatórios**

- Dashboard mostrando:
  - Mensagens agendadas por período
  - Taxa de sucesso de envio
  - Lembretes mais utilizados

### 10. **Permissões**

- Controlar quem pode agendar mensagens
- Controlar quem pode criar lembretes
- Permissões granulares por role

---

## 🔧 Implementação Técnica

### Arquivos a Criar/Modificar

#### **Migrations**
- `049_create_scheduled_messages_table.php`
- `050_create_conversation_reminders_table.php`

#### **Models**
- `app/Models/ScheduledMessage.php`
- `app/Models/ConversationReminder.php`

#### **Services**
- `app/Services/ScheduledMessageService.php`
  - `schedule()` - Agendar mensagem
  - `processPending()` - Processar mensagens pendentes (cron)
  - `cancel()` - Cancelar mensagem agendada
  - `getByConversation()` - Listar mensagens agendadas de uma conversa

- `app/Services/ReminderService.php`
  - `create()` - Criar lembrete
  - `processPending()` - Processar lembretes pendentes (cron)
  - `markResolved()` - Marcar lembrete como resolvido
  - `getByConversation()` - Listar lembretes de uma conversa

#### **Controllers**
- `app/Controllers/ConversationController.php` (modificar)
  - `markRead()` - Marcar como lido
  - `markUnread()` - Marcar como não lido
  - `scheduleMessage()` - Agendar mensagem
  - `createReminder()` - Criar lembrete
  - `getScheduledMessages()` - Listar mensagens agendadas
  - `getReminders()` - Listar lembretes

#### **Jobs/Cron**
- `app/Jobs/ProcessScheduledMessagesJob.php`
- `app/Jobs/ProcessRemindersJob.php`
- Ou script PHP para rodar via cron: `public/scripts/process-scheduled-messages.php`

#### **Views**
- `views/conversations/index.php` (modificar)
  - Substituir botão fixar por dropdown
  - Adicionar botão "Agendar Mensagem" no chat
  - Modais de agendamento

#### **Rotas**
- `routes/web.php` (adicionar)
  - `POST /conversations/{id}/mark-read`
  - `POST /conversations/{id}/mark-unread`
  - `POST /conversations/{id}/schedule-message`
  - `GET /conversations/{id}/scheduled-messages`
  - `DELETE /conversations/{id}/scheduled-messages/{messageId}`
  - `POST /conversations/{id}/reminders`
  - `GET /conversations/{id}/reminders`
  - `POST /reminders/{id}/resolve`

---

## ⚠️ Considerações Importantes

### 1. **Performance**
- Índices nas tabelas para queries rápidas
- Cache de mensagens agendadas pendentes
- Processar em lotes (não uma por uma)

### 2. **Segurança**
- Validar permissões antes de agendar
- Validar data/hora (não permitir passado)
- Sanitizar conteúdo da mensagem

### 3. **Confiabilidade**
- Retry automático se envio falhar
- Logs detalhados de erros
- Notificar usuário se mensagem falhar

### 4. **Timezone**
- Considerar timezone do usuário ao agendar
- Converter para UTC no banco
- Exibir no timezone do usuário na UI

### 5. **Limites**
- Limitar quantas mensagens podem ser agendadas por conversa?
- Limitar quantos lembretes por conversa?
- Rate limiting para evitar spam

---

## 📊 Priorização de Implementação

### **Fase 1 - MVP** (Essencial)
1. ✅ Dropdown de ações (Fixar, Marcar Lido/Não Lido)
2. ✅ Modal de agendar mensagem básico
3. ✅ Tabela e Model de scheduled_messages
4. ✅ Endpoint de agendar mensagem
5. ✅ Job básico para processar mensagens agendadas

### **Fase 2 - Lembretes** (Importante)
6. ✅ Tabela e Model de conversation_reminders
7. ✅ Modal de criar lembrete
8. ✅ Endpoint de criar lembrete
9. ✅ Job para processar lembretes
10. ✅ Notificações quando lembrete disparar

### **Fase 3 - Melhorias** (Desejável)
11. ✅ Badge mostrando mensagens agendadas
12. ✅ Lista de mensagens agendadas pendentes
13. ✅ Editar/cancelar mensagens agendadas
14. ✅ Histórico de mensagens enviadas
15. ✅ Validações e melhorias de UX

---

## ❓ Perguntas para Decisão

1. **Anexos em mensagens agendadas?**
   - Permitir ou não? (Sugestão: SIM, mas opcional)

2. **Mensagens agendadas aparecem no histórico antes de enviar?**
   - Mostrar como "pendente" ou só após envio? (Sugestão: Mostrar como pendente)

3. **Lembretes podem ser compartilhados entre usuários?**
   - Ou só quem criou vê? (Sugestão: Só quem criou)

4. **Mensagens agendadas podem ser editadas?**
   - Ou só cancelar e criar nova? (Sugestão: Permitir editar)

5. **Frequência do job de processamento?**
   - A cada minuto? 5 minutos? (Sugestão: 1 minuto para precisão)

---

## 🎯 Próximos Passos

1. **Revisar este documento** e aprovar/ajustar funcionalidades
2. **Decidir sobre perguntas acima**
3. **Criar migrations** das tabelas
4. **Implementar Models e Services**
5. **Implementar Controllers e Rotas**
6. **Implementar UI (dropdown, modais)**
7. **Criar Jobs/Cron para processamento**
8. **Testes e ajustes**

---

**Documento criado em**: 2025-12-08  
**Versão**: 1.0  
**Status**: Aguardando aprovação para implementação

