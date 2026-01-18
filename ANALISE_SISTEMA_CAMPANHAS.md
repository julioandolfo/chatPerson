# 📊 ANÁLISE COMPLETA DO SISTEMA PARA IMPLEMENTAÇÃO DE CAMPANHAS

**Data:** 18/01/2026  
**Objetivo:** Compreensão profunda do sistema atual para desenvolver módulo de Campanhas de Disparo em Massa

---

## 📋 SUMÁRIO EXECUTIVO

O sistema é um **multiatendimento multicanal** desenvolvido em **PHP Vanilla** com arquitetura MVC robusta, integrações com WhatsApp (Quepasa, Evolution, WhatsApp Official), Instagram, Facebook e outros 14 canais, sistema de automações complexo, funis kanban, tags, templates de mensagens e agendamento.

### Componentes Críticos Identificados
1. ✅ **IntegrationAccount** - Abstração unificada para todos canais
2. ✅ **WhatsAppService** - Envio de mensagens WhatsApp
3. ✅ **AutomationService** - Engine de automações com delays e condições
4. ✅ **ScheduledMessageService** - Agendamento de mensagens individuais
5. ✅ **ConversationService** - Criação e gestão de conversas
6. ✅ **Contact** - Gestão de contatos com normalização de telefone
7. ✅ **Tag** - Sistema de tags para segmentação
8. ✅ **MessageTemplate** - Templates reutilizáveis
9. ✅ **Funnel/FunnelStage** - Funis e etapas para organização

---

## 🔍 ANÁLISE DA ARQUITETURA ATUAL

### 1. Sistema de Canais e Integrações

#### 1.1 Estrutura de Integração (`integration_accounts`)
```sql
CREATE TABLE integration_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),           -- Nome da conta
    provider VARCHAR(50),         -- notificame, whatsapp_official, quepasa, evolution
    channel VARCHAR(50),          -- whatsapp, instagram, facebook, etc (14 canais)
    api_token VARCHAR(500),       -- Token da API
    api_url VARCHAR(500),         -- URL base
    account_id VARCHAR(255),      -- ID externo
    phone_number VARCHAR(50),     -- Para WhatsApp
    username VARCHAR(255),        -- Para Instagram, etc
    status VARCHAR(20),           -- active, inactive, disconnected, error
    config JSON,                  -- Configurações específicas
    webhook_url VARCHAR(500),
    webhook_secret VARCHAR(255),
    default_funnel_id INT,        -- Funil padrão
    default_stage_id INT,         -- Etapa padrão
    last_sync_at TIMESTAMP,
    error_message TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)
```

**Observações:**
- ✅ Suporta múltiplas contas por canal
- ✅ Cada conta pode ter provider diferente
- ✅ Status tracking para verificar saúde da conta
- ✅ Config JSON permite extensibilidade

#### 1.2 Serviço de Integração (`IntegrationService`)
```php
// Factory pattern para diferentes providers
public static function getService(string $provider): object
public static function sendMessage(int $accountId, string $to, string $message, array $options = []): array
public static function checkStatus(int $accountId): array
public static function getActiveAccount(string $channel, string $provider = null): ?array
```

**Fluxo de Envio:**
```
ConversationService::sendMessage()
    ↓
IntegrationService::sendMessage($accountId, $to, $message)
    ↓
getService($provider) → WhatsAppService | NotificameService | etc
    ↓
API Externa (Quepasa, Meta, etc)
```

---

### 2. Sistema de Contatos

#### 2.1 Estrutura (`contacts`)
```sql
CREATE TABLE contacts (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),              -- Suporta formatos diversos
    whatsapp_id VARCHAR(255),       -- ID oficial WhatsApp (@s.whatsapp.net)
    identifier VARCHAR(255),        -- Para Instagram, Facebook, etc
    avatar VARCHAR(255),
    city VARCHAR(255),
    country VARCHAR(255),
    bio TEXT,
    company VARCHAR(255),
    social_media JSON,
    custom_attributes JSON,         -- Atributos customizados
    last_activity_at TIMESTAMP,
    primary_agent_id INT,          -- Agente principal
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)
```

**Recursos Importantes:**
- ✅ **Normalização de telefone** - Remove formatações, detecta @s.whatsapp.net, @lid, etc
- ✅ **Busca inteligente** - `findByPhoneNormalized()` tenta múltiplas variações (com/sem 9º dígito)
- ✅ **Identifier** - Suporta IDs de redes sociais (Instagram, Facebook, etc)
- ✅ **Custom Attributes** - Permite adicionar campos personalizados
- ✅ **findOrCreate()** - Busca ou cria contato automaticamente

---

### 3. Sistema de Conversas

#### 3.1 Estrutura (`conversations`)
```sql
CREATE TABLE conversations (
    id INT PRIMARY KEY,
    contact_id INT,
    agent_id INT,                   -- Agente atribuído
    department_id INT,              -- Setor
    channel VARCHAR(50),            -- whatsapp, instagram, etc
    status VARCHAR(20),             -- open, closed, resolved
    funnel_id INT,                  -- Funil
    funnel_stage_id INT,           -- Etapa do funil
    whatsapp_account_id INT,       -- Legacy
    integration_account_id INT,    -- Nova estrutura unificada
    pinned BOOLEAN,
    is_spam BOOLEAN,
    metadata JSON,
    priority VARCHAR(20),          -- low, medium, high, urgent
    first_response_at TIMESTAMP,
    assigned_at TIMESTAMP,
    resolved_at TIMESTAMP,
    moved_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)
```

**Observações:**
- ✅ Vincula contato + canal + conta de integração
- ✅ Suporta atribuição a agentes e setores
- ✅ Integrado com funis/kanban
- ✅ Tracking de SLA (first_response, resolved_at)

---

### 4. Sistema de Mensagens

#### 4.1 Estrutura (`messages`)
```sql
CREATE TABLE messages (
    id INT PRIMARY KEY,
    conversation_id INT,
    sender_type VARCHAR(20),       -- contact, agent, ai_agent, system
    sender_id INT,
    content TEXT,
    message_type VARCHAR(20),      -- text, image, video, audio, document, location
    attachments JSON,              -- URLs de anexos
    status VARCHAR(20),            -- pending, sent, delivered, read, failed
    delivered_at TIMESTAMP,
    read_at TIMESTAMP,
    error_message TEXT,
    external_id VARCHAR(255),      -- ID da mensagem na API externa
    quoted_message_id INT,         -- Resposta/citação
    quoted_sender_name VARCHAR(255),
    quoted_text TEXT,
    ai_agent_id INT,              -- Se foi enviada por IA
    created_at TIMESTAMP
)
```

**Recursos:**
- ✅ Suporta múltiplos tipos de mídia
- ✅ Tracking de entrega e leitura
- ✅ Citações/respostas
- ✅ Integração com IA

---

### 5. Sistema de Mensagens Agendadas

#### 5.1 Estrutura (`scheduled_messages`)
```sql
CREATE TABLE scheduled_messages (
    id INT PRIMARY KEY,
    conversation_id INT,
    user_id INT,
    content TEXT,
    attachments JSON,
    scheduled_at TIMESTAMP,        -- Quando enviar
    status VARCHAR(20),            -- pending, sent, cancelled, failed
    sent_at TIMESTAMP,
    error_message TEXT,
    cancel_if_resolved BOOLEAN,    -- Cancelar se conversa fechada
    cancel_if_responded BOOLEAN,   -- Cancelar se cliente respondeu
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)
```

**Processamento:**
- ✅ Cron job a cada 1 minuto (`process-scheduled-messages.php`)
- ✅ Service: `ScheduledMessageService::processPending()`
- ✅ Validações antes de enviar (status da conversa, resposta do cliente)

---

### 6. Sistema de Automações

#### 6.1 Estrutura (`automations`)
```sql
CREATE TABLE automations (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    description TEXT,
    trigger_type VARCHAR(50),      -- new_conversation, message_received, etc
    trigger_config JSON,           -- Condições (canal, conta, palavra-chave, etc)
    funnel_id INT,                 -- Filtrar por funil
    stage_id INT,                  -- Filtrar por etapa
    status VARCHAR(20),            -- active, inactive
    is_active BOOLEAN,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)
```

#### 6.2 Nós de Automação (`automation_nodes`)
```sql
CREATE TABLE automation_nodes (
    id INT PRIMARY KEY,
    automation_id INT,
    node_type VARCHAR(50),         -- trigger, condition, action, delay, etc
    node_data JSON,                -- Configuração específica do nó
    position_x INT,
    position_y INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)
```

**Tipos de Nós:**
- ✅ **trigger** - Gatilho (nova conversa, mensagem, etc)
- ✅ **condition** - Condição (contém palavra, canal específico, tag, etc)
- ✅ **action** - Ação (enviar mensagem, atribuir agente, adicionar tag, mover funil, etc)
- ✅ **delay** - Atraso (aguardar X minutos/horas/dias)
- ✅ **ai_analysis** - Análise de IA

**Engine de Execução:**
```php
AutomationService::executeAutomation($automationId, $conversationId, $context)
    ↓
1. Busca automação com nós
2. Executa nó trigger (já validado)
3. Para cada nó sequencial:
   - Se condition: avalia condição
   - Se action: executa ação
   - Se delay: agenda execução futura
4. Registra em automation_executions
```

**Delays:**
- ✅ Armazenados em `automation_delays` com `execute_at`
- ✅ Processados por cron job a cada 5 minutos
- ✅ Suporta cancelamento se condições mudarem

---

### 7. Sistema de Tags

#### 7.1 Estrutura (`tags`)
```sql
CREATE TABLE tags (
    id INT PRIMARY KEY,
    name VARCHAR(100) UNIQUE,
    color VARCHAR(7),              -- Hexadecimal
    description TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)
```

#### 7.2 Relacionamento (`conversation_tags`)
```sql
CREATE TABLE conversation_tags (
    conversation_id INT,
    tag_id INT,
    PRIMARY KEY (conversation_id, tag_id)
)
```

**Recursos:**
- ✅ Adicionar/remover tags de conversas
- ✅ Buscar conversas por tags
- ✅ Buscar tags de contatos (através de suas conversas)
- ✅ Usado em filtros e automações

---

### 8. Sistema de Templates

#### 8.1 Estrutura (`message_templates`)
```sql
CREATE TABLE message_templates (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    category VARCHAR(100),
    content TEXT,                  -- Suporta variáveis {{nome}}, {{email}}, etc
    description TEXT,
    department_id INT,             -- Filtrar por setor
    channel VARCHAR(50),           -- Filtrar por canal
    is_active BOOLEAN,
    user_id INT,                   -- NULL = global, ID = pessoal
    usage_count INT DEFAULT 0,     -- Tracking de uso
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)
```

**Processamento de Variáveis:**
```php
MessageTemplate::processTemplate($content, [
    'nome' => $contact['name'],
    'email' => $contact['email'],
    'empresa' => $contact['company'],
    // ... mais variáveis
])
```

**Recursos:**
- ✅ Templates globais e pessoais
- ✅ Filtros por setor e canal
- ✅ Variáveis dinâmicas
- ✅ Contador de uso

---

### 9. Sistema de Funis (Kanban)

#### 9.1 Estrutura (`funnels`)
```sql
CREATE TABLE funnels (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    description TEXT,
    ai_description TEXT,           -- Descrição para IA entender
    color VARCHAR(7),
    is_default BOOLEAN,
    status VARCHAR(20),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)
```

#### 9.2 Etapas (`funnel_stages`)
```sql
CREATE TABLE funnel_stages (
    id INT PRIMARY KEY,
    funnel_id INT,
    name VARCHAR(255),
    description TEXT,
    color VARCHAR(7),
    stage_order INT,              -- Ordem de exibição
    is_system BOOLEAN,            -- Etapas obrigatórias (entrada, em_andamento, concluido)
    system_type VARCHAR(50),      -- entrada, em_andamento, concluido
    sla_hours INT,                -- SLA para esta etapa
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)
```

**Observações:**
- ✅ Conversas são movidas entre etapas
- ✅ Funil padrão pode ser configurado por integração
- ✅ SLA por etapa
- ✅ Automações podem mover conversas entre etapas

---

## 🎯 ANÁLISE DE GAPS E NECESSIDADES PARA CAMPANHAS

### Funcionalidades Já Existentes (Reutilizáveis)
1. ✅ **Envio de mensagens** - `IntegrationService::sendMessage()`
2. ✅ **Gestão de contatos** - Models e normalização
3. ✅ **Múltiplos canais** - 14 canais suportados
4. ✅ **Agendamento** - `ScheduledMessageService` (individual)
5. ✅ **Templates** - `MessageTemplate` com variáveis
6. ✅ **Tags** - Sistema completo de tags
7. ✅ **Delays/Intervalos** - Engine de delays em automações
8. ✅ **Cron jobs** - Estrutura de processamento agendado

### Funcionalidades a Desenvolver
1. ❌ **Gestão de Listas** - CRUD de listas de contatos
2. ❌ **Upload em massa** - Importar CSV/Excel
3. ❌ **Segmentação avançada** - Filtros complexos (tags AND/OR, custom attributes, etc)
4. ❌ **Campanhas** - CRUD de campanhas
5. ❌ **Cadência de envio** - Controle de velocidade (X msgs/minuto)
6. ❌ **Rotação de canais** - Distribuir envios entre múltiplas contas
7. ❌ **Funis de campanha** - Etapas/ações após envio
8. ❌ **Relatórios** - Estatísticas de campanhas (enviadas, entregues, lidas, respondidas)
9. ❌ **Blacklist** - Evitar enviar para contatos específicos
10. ❌ **Validação** - Validar números antes de enviar

---

## 📐 PROPOSTA DE ARQUITETURA PARA CAMPANHAS

### 1. Estrutura de Banco de Dados

#### 1.1 Tabela: `campaigns`
```sql
CREATE TABLE campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    
    -- SEGMENTAÇÃO
    target_type VARCHAR(50) DEFAULT 'list',  -- list, filter, custom
    contact_list_id INT,                     -- Se target_type = 'list'
    filter_config JSON,                       -- Se target_type = 'filter'
    
    -- MENSAGEM
    message_template_id INT,                  -- Template a usar
    message_content TEXT,                     -- Ou conteúdo direto
    message_variables JSON,                   -- Variáveis globais
    attachments JSON,                         -- Anexos (imagens, docs, etc)
    
    -- CANAIS
    channel VARCHAR(50),                      -- whatsapp, instagram, etc
    integration_account_ids JSON,             -- Array de IDs de contas
    rotation_strategy VARCHAR(50),            -- round_robin, random, by_load
    
    -- AGENDAMENTO E CADÊNCIA
    scheduled_at TIMESTAMP,                   -- Quando iniciar
    send_strategy VARCHAR(50),                -- immediate, scheduled, drip
    send_rate_per_minute INT DEFAULT 10,     -- Mensagens por minuto
    send_interval_seconds INT DEFAULT 6,      -- Intervalo entre mensagens
    send_window_start TIME,                   -- Horário inicial (ex: 09:00)
    send_window_end TIME,                     -- Horário final (ex: 18:00)
    send_days JSON,                           -- Dias da semana [1,2,3,4,5]
    timezone VARCHAR(50) DEFAULT 'America/Sao_Paulo',
    
    -- FUNIL/ETAPAS
    funnel_id INT,                            -- Funil de campanha
    initial_stage_id INT,                     -- Etapa inicial
    auto_move_on_reply BOOLEAN DEFAULT FALSE, -- Mover ao responder
    reply_stage_id INT,                       -- Etapa ao responder
    
    -- STATUS E CONTROLE
    status VARCHAR(50) DEFAULT 'draft',       -- draft, scheduled, running, paused, completed, cancelled
    priority INT DEFAULT 0,                   -- Prioridade (maior = mais importante)
    
    -- CONFIGURAÇÕES AVANÇADAS
    skip_duplicates BOOLEAN DEFAULT TRUE,     -- Não enviar se já enviou antes
    skip_recent_conversations BOOLEAN DEFAULT TRUE, -- Não enviar se tem conversa ativa
    skip_recent_hours INT DEFAULT 24,         -- Horas para considerar recente
    respect_blacklist BOOLEAN DEFAULT TRUE,   -- Respeitar blacklist
    create_conversation BOOLEAN DEFAULT TRUE, -- Criar conversa ao enviar
    tag_on_send VARCHAR(100),                 -- Tag a adicionar ao enviar
    
    -- ESTATÍSTICAS
    total_contacts INT DEFAULT 0,
    total_sent INT DEFAULT 0,
    total_delivered INT DEFAULT 0,
    total_read INT DEFAULT 0,
    total_replied INT DEFAULT 0,
    total_failed INT DEFAULT 0,
    total_skipped INT DEFAULT 0,
    
    -- TRACKING
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    paused_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    
    -- AUDIT
    created_by INT,                           -- Usuário que criou
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (contact_list_id) REFERENCES contact_lists(id) ON DELETE SET NULL,
    FOREIGN KEY (message_template_id) REFERENCES message_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (funnel_id) REFERENCES funnels(id) ON DELETE SET NULL,
    FOREIGN KEY (initial_stage_id) REFERENCES funnel_stages(id) ON DELETE SET NULL,
    FOREIGN KEY (reply_stage_id) REFERENCES funnel_stages(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_channel (channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 1.2 Tabela: `contact_lists`
```sql
CREATE TABLE contact_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    
    -- FILTROS AUTOMÁTICOS
    is_dynamic BOOLEAN DEFAULT FALSE,         -- Lista dinâmica (recalcula sempre)
    filter_config JSON,                       -- Filtros se is_dynamic = TRUE
    
    -- ESTATÍSTICAS
    total_contacts INT DEFAULT 0,
    last_calculated_at TIMESTAMP,
    
    -- AUDIT
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 1.3 Tabela: `contact_list_items`
```sql
CREATE TABLE contact_list_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_list_id INT NOT NULL,
    contact_id INT NOT NULL,
    custom_variables JSON,                    -- Variáveis específicas deste contato
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (contact_list_id) REFERENCES contact_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    UNIQUE KEY unique_list_contact (contact_list_id, contact_id),
    INDEX idx_contact_list_id (contact_list_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 1.4 Tabela: `campaign_messages`
```sql
CREATE TABLE campaign_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    contact_id INT NOT NULL,
    conversation_id INT,                      -- Criada para esta mensagem
    message_id INT,                           -- ID da mensagem enviada
    integration_account_id INT,               -- Conta usada para enviar
    
    -- CONTEÚDO
    content TEXT,                             -- Conteúdo processado (com variáveis)
    attachments JSON,
    
    -- STATUS
    status VARCHAR(50) DEFAULT 'pending',     -- pending, scheduled, sending, sent, delivered, read, replied, failed, skipped
    error_message TEXT,
    skip_reason VARCHAR(255),                 -- Motivo se skipped
    
    -- TRACKING
    scheduled_at TIMESTAMP,                   -- Quando foi agendado
    sent_at TIMESTAMP,
    delivered_at TIMESTAMP,
    read_at TIMESTAMP,
    replied_at TIMESTAMP,
    failed_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL,
    FOREIGN KEY (integration_account_id) REFERENCES integration_accounts(id) ON DELETE SET NULL,
    
    INDEX idx_campaign_id (campaign_id),
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_campaign_contact (campaign_id, contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 1.5 Tabela: `campaign_blacklist`
```sql
CREATE TABLE campaign_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT,
    phone VARCHAR(50),
    reason VARCHAR(255),
    added_by INT,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_phone (phone),
    INDEX idx_contact_id (contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 1.6 Tabela: `campaign_stages` (Etapas de Campanha)
```sql
CREATE TABLE campaign_stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    stage_order INT NOT NULL,
    name VARCHAR(255),
    
    -- AÇÃO
    action_type VARCHAR(50),                  -- send_message, wait, condition, tag, move_funnel
    action_config JSON,                       -- Configuração da ação
    
    -- DELAY
    delay_value INT,                          -- Valor do delay
    delay_unit VARCHAR(20),                   -- minutes, hours, days
    
    -- CONDIÇÃO
    condition_type VARCHAR(50),               -- replied, not_replied, delivered, read, clicked, etc
    condition_config JSON,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    INDEX idx_campaign_order (campaign_id, stage_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2. Services

#### 2.1 `CampaignService.php`
```php
namespace App\Services;

class CampaignService
{
    // CRUD Campanhas
    public static function create(array $data): int
    public static function update(int $campaignId, array $data): bool
    public static function delete(int $campaignId): bool
    public static function get(int $campaignId): ?array
    public static function getAll(array $filters = []): array
    
    // Controle de Status
    public static function start(int $campaignId): bool
    public static function pause(int $campaignId): bool
    public static function resume(int $campaignId): bool
    public static function cancel(int $campaignId): bool
    
    // Preparação
    public static function prepare(int $campaignId): array        // Prepara mensagens
    public static function validate(int $campaignId): array       // Valida configuração
    public static function estimateCompletion(int $campaignId): array  // Estima tempo
    
    // Processamento
    public static function processNext(int $limit = 10): array    // Processa próximas mensagens
    
    // Estatísticas
    public static function getStats(int $campaignId): array
    public static function getProgress(int $campaignId): array
}
```

#### 2.2 `ContactListService.php`
```php
namespace App\Services;

class ContactListService
{
    // CRUD Listas
    public static function create(array $data): int
    public static function update(int $listId, array $data): bool
    public static function delete(int $listId): bool
    public static function get(int $listId): ?array
    public static function getAll(array $filters = []): array
    
    // Gestão de Contatos
    public static function addContact(int $listId, int $contactId, array $variables = []): bool
    public static function addContacts(int $listId, array $contactIds): int  // Retorna quantidade adicionada
    public static function removeContact(int $listId, int $contactId): bool
    public static function clearList(int $listId): bool
    
    // Import/Export
    public static function importFromCsv(int $listId, string $filePath, array $mapping = []): array
    public static function importFromExcel(int $listId, string $filePath, array $mapping = []): array
    public static function exportToCsv(int $listId): string  // Retorna caminho do arquivo
    
    // Listas Dinâmicas
    public static function recalculate(int $listId): int      // Retorna quantidade de contatos
    public static function getContactsByFilter(array $filters): array
    
    // Estatísticas
    public static function getStats(int $listId): array
}
```

#### 2.3 `CampaignSchedulerService.php`
```php
namespace App\Services;

class CampaignSchedulerService
{
    // Processamento Principal
    public static function processPending(int $limit = 50): array
    
    // Rotação de Contas
    public static function selectAccount(array $accountIds, string $strategy = 'round_robin'): ?int
    
    // Validações
    public static function canSendNow(int $campaignId): bool
    public static function isWithinSendWindow(int $campaignId): bool
    public static function shouldSkipContact(int $campaignId, int $contactId): array
    
    // Rate Limiting
    public static function applyCadence(int $campaignId, int $messageCount): void
    public static function getCampaignRateLimit(int $campaignId): array
}
```

#### 2.4 `CampaignBlacklistService.php`
```php
namespace App\Services;

class CampaignBlacklistService
{
    public static function add(int $contactId, string $reason, int $userId): bool
    public static function addByPhone(string $phone, string $reason, int $userId): bool
    public static function remove(int $blacklistId): bool
    public static function isBlacklisted(int $contactId): bool
    public static function isPhoneBlacklisted(string $phone): bool
    public static function getAll(array $filters = []): array
}
```

---

### 3. Controllers

#### 3.1 `CampaignController.php`
```php
namespace App\Controllers;

class CampaignController
{
    // Views
    public function index(): void              // Lista campanhas
    public function create(): void             // Form criar
    public function edit(int $id): void        // Form editar
    public function show(int $id): void        // Detalhes + estatísticas
    
    // Actions
    public function store(): void              // POST criar
    public function update(int $id): void      // PUT/POST atualizar
    public function destroy(int $id): void     // DELETE
    
    // Status
    public function start(int $id): void       // POST start
    public function pause(int $id): void       // POST pause
    public function resume(int $id): void      // POST resume
    public function cancel(int $id): void      // POST cancel
    
    // API
    public function stats(int $id): void       // GET stats (JSON)
    public function progress(int $id): void    // GET progress (JSON)
    public function validate(int $id): void    // POST validate (JSON)
}
```

#### 3.2 `ContactListController.php`
```php
namespace App\Controllers;

class ContactListController
{
    // Views
    public function index(): void
    public function create(): void
    public function edit(int $id): void
    public function show(int $id): void
    
    // Actions
    public function store(): void
    public function update(int $id): void
    public function destroy(int $id): void
    
    // Gestão de Contatos
    public function addContact(int $id): void       // POST adicionar contato
    public function removeContact(int $id): void    // DELETE remover contato
    public function importCsv(int $id): void        // POST upload CSV
    public function exportCsv(int $id): void        // GET download CSV
    
    // API
    public function contacts(int $id): void         // GET lista contatos (JSON, paginado)
}
```

---

### 4. Cron Jobs

#### 4.1 `public/scripts/process-campaigns.php`
```php
<?php
/**
 * Processa mensagens pendentes de campanhas
 * Rodar a cada 1 minuto
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\CampaignSchedulerService;
use App\Helpers\Logger;

Logger::info("=== INICIANDO PROCESSAMENTO DE CAMPANHAS ===");

try {
    $processed = CampaignSchedulerService::processPending(50);
    
    Logger::info("Processadas {count} mensagens", ['count' => count($processed)]);
    
    // Estatísticas
    $sent = 0;
    $failed = 0;
    $skipped = 0;
    
    foreach ($processed as $result) {
        if ($result['status'] === 'sent') $sent++;
        elseif ($result['status'] === 'failed') $failed++;
        elseif ($result['status'] === 'skipped') $skipped++;
    }
    
    Logger::info("Resultado: Enviadas={$sent}, Falhadas={$failed}, Puladas={$skipped}");
    
} catch (\Exception $e) {
    Logger::error("Erro ao processar campanhas: " . $e->getMessage());
    exit(1);
}

Logger::info("=== PROCESSAMENTO CONCLUÍDO ===");
```

**Crontab:**
```bash
# Processar campanhas a cada 1 minuto
* * * * * php /caminho/para/public/scripts/process-campaigns.php >> /caminho/para/logs/campaigns.log 2>&1
```

---

## 🚀 SUGESTÕES E MELHORIAS

### 1. Rotação de Canais - Estratégias

#### Round Robin (Recomendado)
```php
private static int $lastUsedAccountIndex = 0;

public static function selectAccountRoundRobin(array $accountIds): ?int
{
    if (empty($accountIds)) return null;
    
    $index = self::$lastUsedAccountIndex % count($accountIds);
    $accountId = $accountIds[$index];
    
    self::$lastUsedAccountIndex++;
    
    return $accountId;
}
```

#### Por Carga (Load Balancing)
```php
public static function selectAccountByLoad(array $accountIds): ?int
{
    if (empty($accountIds)) return null;
    
    $loads = [];
    foreach ($accountIds as $accountId) {
        // Contar mensagens enviadas nas últimas 24h
        $loads[$accountId] = self::getAccountLoad($accountId);
    }
    
    // Retornar conta com menor carga
    asort($loads);
    return array_key_first($loads);
}
```

#### Por Status (Health Check)
```php
public static function selectAccountByStatus(array $accountIds): ?int
{
    foreach ($accountIds as $accountId) {
        $account = IntegrationAccount::find($accountId);
        
        // Verificar se está ativa e sem erros recentes
        if ($account['status'] === 'active' && empty($account['error_message'])) {
            return $accountId;
        }
    }
    
    return null;  // Nenhuma conta saudável
}
```

---

### 2. Cadência e Rate Limiting

#### Estratégia de Envio
```php
public static function applyCadence(int $campaignId, int $messageCount): void
{
    $campaign = Campaign::find($campaignId);
    
    $ratePerMinute = $campaign['send_rate_per_minute'] ?? 10;
    $intervalSeconds = $campaign['send_interval_seconds'] ?? 6;
    
    // Se já enviou mais que o permitido por minuto, aguardar
    $lastMinuteCount = self::getLastMinuteCount($campaignId);
    
    if ($lastMinuteCount >= $ratePerMinute) {
        // Aguardar até o próximo minuto
        sleep(60 - (time() % 60));
    }
    
    // Aplicar intervalo entre mensagens
    usleep($intervalSeconds * 1000000);
}
```

#### Janela de Envio
```php
public static function isWithinSendWindow(int $campaignId): bool
{
    $campaign = Campaign::find($campaignId);
    
    $timezone = new \DateTimeZone($campaign['timezone'] ?? 'America/Sao_Paulo');
    $now = new \DateTime('now', $timezone);
    
    $currentTime = $now->format('H:i:s');
    $currentDay = (int)$now->format('N');  // 1 = Segunda, 7 = Domingo
    
    $startTime = $campaign['send_window_start'];
    $endTime = $campaign['send_window_end'];
    $allowedDays = json_decode($campaign['send_days'] ?? '[]', true);
    
    // Verificar dia da semana
    if (!empty($allowedDays) && !in_array($currentDay, $allowedDays)) {
        return false;
    }
    
    // Verificar horário
    if ($currentTime < $startTime || $currentTime > $endTime) {
        return false;
    }
    
    return true;
}
```

---

### 3. Validações Antes de Enviar

```php
public static function shouldSkipContact(int $campaignId, int $contactId): array
{
    $campaign = Campaign::find($campaignId);
    $contact = Contact::find($contactId);
    
    // 1. Verificar blacklist
    if ($campaign['respect_blacklist'] && CampaignBlacklistService::isBlacklisted($contactId)) {
        return ['skip' => true, 'reason' => 'Contato na blacklist'];
    }
    
    // 2. Verificar se já enviou nesta campanha
    if ($campaign['skip_duplicates']) {
        $alreadySent = CampaignMessage::where('campaign_id', $campaignId)
            ->where('contact_id', $contactId)
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->first();
        
        if ($alreadySent) {
            return ['skip' => true, 'reason' => 'Já enviou nesta campanha'];
        }
    }
    
    // 3. Verificar conversas recentes
    if ($campaign['skip_recent_conversations']) {
        $hours = $campaign['skip_recent_hours'] ?? 24;
        $recentConv = Conversation::where('contact_id', $contactId)
            ->where('updated_at', '>', date('Y-m-d H:i:s', strtotime("-{$hours} hours")))
            ->first();
        
        if ($recentConv) {
            return ['skip' => true, 'reason' => 'Conversa ativa recente'];
        }
    }
    
    // 4. Validar telefone/identifier
    if ($campaign['channel'] === 'whatsapp') {
        if (empty($contact['phone'])) {
            return ['skip' => true, 'reason' => 'Contato sem telefone'];
        }
    } elseif (in_array($campaign['channel'], ['instagram', 'facebook'])) {
        if (empty($contact['identifier'])) {
            return ['skip' => true, 'reason' => 'Contato sem identifier'];
        }
    }
    
    return ['skip' => false, 'reason' => null];
}
```

---

### 4. Processamento de Variáveis

```php
public static function processMessageVariables(string $content, int $contactId, array $customVars = []): string
{
    $contact = Contact::find($contactId);
    
    // Variáveis padrão
    $variables = [
        'nome' => $contact['name'] ?? '',
        'primeiro_nome' => explode(' ', $contact['name'] ?? '')[0],
        'sobrenome' => $contact['last_name'] ?? '',
        'email' => $contact['email'] ?? '',
        'telefone' => $contact['phone'] ?? '',
        'cidade' => $contact['city'] ?? '',
        'pais' => $contact['country'] ?? '',
        'empresa' => $contact['company'] ?? '',
    ];
    
    // Custom attributes
    if (!empty($contact['custom_attributes'])) {
        $customAttrs = json_decode($contact['custom_attributes'], true) ?? [];
        foreach ($customAttrs as $key => $value) {
            $variables[$key] = $value;
        }
    }
    
    // Variáveis específicas desta campanha/contato
    $variables = array_merge($variables, $customVars);
    
    // Processar template
    return MessageTemplate::processTemplate($content, $variables);
}
```

---

### 5. Funis de Campanha (Drip Campaigns)

**Exemplo: Sequência de 3 mensagens**

```
Etapa 1: Enviar Mensagem Inicial
   ↓
Aguardar 1 dia
   ↓
Etapa 2: SE respondeu → Mover para funil "Interessados"
          SE NÃO respondeu → Enviar Mensagem de Lembrete
   ↓
Aguardar 3 dias
   ↓
Etapa 3: SE ainda NÃO respondeu → Enviar Última Mensagem
          SE respondeu → Mover para funil "Engajados"
```

**Implementação:**
```php
// campaign_stages
// stage_order=1, action_type=send_message, action_config={"message": "Olá!"}
// stage_order=2, action_type=wait, delay_value=1, delay_unit=days
// stage_order=3, action_type=condition, condition_type=replied
//    - TRUE: action_type=move_funnel, action_config={"funnel_id": X}
//    - FALSE: action_type=send_message, action_config={"message": "Lembrete..."}
// etc
```

---

### 6. Relatórios e Dashboards

#### Métricas Principais
```php
public static function getStats(int $campaignId): array
{
    $campaign = Campaign::find($campaignId);
    
    return [
        // Contatos
        'total_contacts' => $campaign['total_contacts'],
        
        // Envios
        'total_sent' => $campaign['total_sent'],
        'total_pending' => CampaignMessage::where('campaign_id', $campaignId)
            ->whereIn('status', ['pending', 'scheduled'])
            ->count(),
        
        // Entregas
        'total_delivered' => $campaign['total_delivered'],
        'delivery_rate' => $campaign['total_sent'] > 0 
            ? ($campaign['total_delivered'] / $campaign['total_sent']) * 100 
            : 0,
        
        // Leituras
        'total_read' => $campaign['total_read'],
        'read_rate' => $campaign['total_delivered'] > 0 
            ? ($campaign['total_read'] / $campaign['total_delivered']) * 100 
            : 0,
        
        // Respostas
        'total_replied' => $campaign['total_replied'],
        'reply_rate' => $campaign['total_delivered'] > 0 
            ? ($campaign['total_replied'] / $campaign['total_delivered']) * 100 
            : 0,
        
        // Falhas
        'total_failed' => $campaign['total_failed'],
        'failure_rate' => $campaign['total_sent'] > 0 
            ? ($campaign['total_failed'] / $campaign['total_sent']) * 100 
            : 0,
        
        // Puladas
        'total_skipped' => $campaign['total_skipped'],
        
        // Progresso
        'progress_percent' => $campaign['total_contacts'] > 0
            ? (($campaign['total_sent'] + $campaign['total_failed'] + $campaign['total_skipped']) / $campaign['total_contacts']) * 100
            : 0,
        
        // Tempo estimado
        'estimated_completion' => self::estimateCompletion($campaignId),
    ];
}
```

---

## 🎨 UI/UX - Sugestões

### 1. Tela de Criação de Campanha (Wizard Multi-Step)

#### Step 1: Informações Básicas
- Nome da campanha
- Descrição
- Canal (WhatsApp, Instagram, etc)
- Selecionar contas de integração (múltiplas)

#### Step 2: Segmentação
- Opção 1: Selecionar lista existente
- Opção 2: Filtros dinâmicos
  - Tags (AND/OR)
  - Funil/Etapa
  - Última atividade
  - Custom attributes
  - Etc
- Opção 3: Upload CSV/Excel
- Opção 4: Adicionar manualmente

#### Step 3: Mensagem
- Selecionar template OU escrever
- Editor WYSIWYG
- Preview com variáveis
- Anexos (imagens, vídeos, documentos)
- Testar mensagem (enviar para si mesmo)

#### Step 4: Agendamento e Cadência
- Enviar agora OU agendar
- Taxa de envio (msgs/minuto)
- Janela de horário (09:00 - 18:00)
- Dias da semana
- Timezone

#### Step 5: Configurações Avançadas
- Pular duplicatas
- Pular conversas recentes (X horas)
- Respeitar blacklist
- Criar conversa automaticamente
- Adicionar tag ao enviar
- Funil destino
- Mover ao responder

#### Step 6: Revisão e Confirmação
- Resumo de tudo
- Validações
- Estimativa de tempo
- Botão "Iniciar Campanha"

---

### 2. Dashboard de Campanhas

#### Cards de Métricas (KPIs)
```
┌──────────────┬──────────────┬──────────────┬──────────────┐
│  📨 Enviadas │  ✅ Entregues │  👁️ Lidas     │  💬 Respostas │
│  1,234       │  1,150 (93%) │  800 (69%)   │  120 (15%)   │
└──────────────┴──────────────┴──────────────┴──────────────┘
```

#### Lista de Campanhas
```
┌─────────────────────────────────────────────────────────────┐
│ Nome         │ Status    │ Progresso │ Taxa Resposta │ Ações │
├─────────────────────────────────────────────────────────────┤
│ Black Friday │ ▶️ Rodando │ 45%       │ 18%          │ ⏸️ ✏️ 📊 │
│ Natal 2026   │ ⏰ Agendada│ 0%        │ -            │ ▶️ ✏️ 🗑️ │
│ Boas-vindas  │ ✅ Completa│ 100%      │ 23%          │ 📊 📋    │
└─────────────────────────────────────────────────────────────┘
```

#### Gráficos
- Linha temporal: Envios por hora/dia
- Funil: Enviadas → Entregues → Lidas → Respondidas
- Pizza: Status das mensagens
- Barras: Comparação entre campanhas

---

### 3. Tela de Detalhes da Campanha

#### Abas
1. **Visão Geral** - Métricas e status
2. **Mensagens** - Lista de mensagens individuais (com filtros)
3. **Contatos** - Lista de contatos da campanha
4. **Etapas** - Funil/sequência da campanha (se aplicável)
5. **Logs** - Histórico de eventos

#### Ações
- ▶️ Iniciar
- ⏸️ Pausar
- ▶️ Retomar
- ❌ Cancelar
- 📊 Exportar relatório
- 📋 Duplicar campanha

---

## 🔧 FLUXO DE PROCESSAMENTO

### Fluxo Completo de Campanha

```
1. CRIAÇÃO
   ├─ CampaignController::store()
   ├─ CampaignService::create()
   └─ Campaign (status=draft)

2. PREPARAÇÃO
   ├─ CampaignService::prepare()
   ├─ Resolver lista de contatos
   ├─ Validar contatos
   ├─ Criar registros em campaign_messages (status=pending)
   └─ Atualizar campaign.total_contacts

3. INICIAR
   ├─ CampaignController::start()
   ├─ CampaignService::start()
   ├─ Validações finais
   └─ Atualizar campaign.status = 'running'

4. PROCESSAMENTO (Cron: a cada 1 minuto)
   ├─ CampaignSchedulerService::processPending()
   ├─ Buscar campanhas ativas (status=running)
   ├─ Verificar janela de envio
   ├─ Buscar próximas X mensagens (status=pending, scheduled_at <= NOW)
   ├─ Para cada mensagem:
   │  ├─ Validar contato (blacklist, duplicatas, conversa recente)
   │  ├─ Se skip: marcar como skipped
   │  ├─ Se ok:
   │  │  ├─ Selecionar conta (rotação)
   │  │  ├─ Processar variáveis
   │  │  ├─ Criar conversa (se configurado)
   │  │  ├─ Enviar via IntegrationService::sendMessage()
   │  │  ├─ Criar registro em messages
   │  │  ├─ Atualizar campaign_messages (status=sent/failed)
   │  │  └─ Adicionar tag (se configurado)
   │  └─ Aplicar cadência (delay entre envios)
   └─ Atualizar estatísticas da campanha

5. TRACKING (Webhooks de canal)
   ├─ Receber webhook de entrega/leitura/resposta
   ├─ Atualizar message (delivered_at, read_at)
   ├─ Atualizar campaign_messages
   ├─ Se resposta: detectar em CampaignMessage e marcar replied_at
   └─ Atualizar estatísticas da campanha

6. CONCLUSÃO
   ├─ Verificar se todas as mensagens foram processadas
   ├─ Atualizar campaign.status = 'completed'
   └─ Atualizar campaign.completed_at
```

---

## 📝 CHECKLIST DE IMPLEMENTAÇÃO

### Fase 1: Banco de Dados e Models ✅
- [ ] Migration: `campaigns`
- [ ] Migration: `contact_lists`
- [ ] Migration: `contact_list_items`
- [ ] Migration: `campaign_messages`
- [ ] Migration: `campaign_blacklist`
- [ ] Migration: `campaign_stages`
- [ ] Model: `Campaign.php`
- [ ] Model: `ContactList.php`
- [ ] Model: `ContactListItem.php`
- [ ] Model: `CampaignMessage.php`
- [ ] Model: `CampaignBlacklist.php`
- [ ] Model: `CampaignStage.php`

### Fase 2: Services ✅
- [ ] Service: `CampaignService.php`
- [ ] Service: `ContactListService.php`
- [ ] Service: `CampaignSchedulerService.php`
- [ ] Service: `CampaignBlacklistService.php`

### Fase 3: Controllers ✅
- [ ] Controller: `CampaignController.php`
- [ ] Controller: `ContactListController.php`
- [ ] Controller: `CampaignBlacklistController.php`

### Fase 4: Views ✅
- [ ] View: `campaigns/index.php` (lista)
- [ ] View: `campaigns/create.php` (wizard)
- [ ] View: `campaigns/edit.php`
- [ ] View: `campaigns/show.php` (detalhes + stats)
- [ ] View: `contact-lists/index.php`
- [ ] View: `contact-lists/create.php`
- [ ] View: `contact-lists/show.php`

### Fase 5: Cron e Processamento ✅
- [ ] Script: `public/scripts/process-campaigns.php`
- [ ] Configurar cron job (1 minuto)
- [ ] Testar processamento manual

### Fase 6: Testes e Validações ✅
- [ ] Testar envio simples (1 contato)
- [ ] Testar envio em massa (100+ contatos)
- [ ] Testar rotação de contas
- [ ] Testar cadência e rate limiting
- [ ] Testar janela de envio
- [ ] Testar blacklist
- [ ] Testar pular duplicatas
- [ ] Testar webhooks (tracking)

### Fase 7: Features Avançadas ✅
- [ ] Import CSV/Excel
- [ ] Export relatórios
- [ ] Funis/Etapas de campanha (drip)
- [ ] Listas dinâmicas (filtros)
- [ ] Templates com preview
- [ ] Validação de números (API WhatsApp)

---

## 💡 MELHORIAS E INOVAÇÕES SUGERIDAS

### 1. **Validação de Números em Tempo Real**
- Integrar com API do WhatsApp para verificar se número existe antes de enviar
- Marcar contatos inválidos automaticamente
- Evitar desperdício de envios

### 2. **A/B Testing**
- Criar variantes de mensagens (A, B, C)
- Distribuir automaticamente entre contatos
- Comparar taxa de resposta

### 3. **Smart Timing**
- IA analisa histórico de respostas do contato
- Sugere melhor horário para enviar
- Aumenta taxa de engajamento

### 4. **Follow-up Automático**
- Se não responder em X dias, enviar lembrete
- Se responder, mover para outro funil
- Personalizar sequência baseado em comportamento

### 5. **Integração com CRM**
- Sincronizar contatos com Pipedrive, HubSpot, etc
- Atualizar status no CRM após campanha
- Criar deals/oportunidades automaticamente

### 6. **Chatbot Pós-Campanha**
- Se cliente responder, ativar chatbot/IA
- Qualificar lead automaticamente
- Agendar reunião/atendimento

### 7. **Segmentação por IA**
- IA analisa conversas anteriores
- Sugere melhores contatos para campanha
- Prediz taxa de resposta

### 8. **Templates Dinâmicos com Imagens**
- Gerar imagens personalizadas (nome, código QR, etc)
- Usar serviços como Canva API
- Maior engajamento visual

### 9. **Compliance e LGPD**
- Link para opt-out automático
- Registro de consentimento
- Histórico de comunicações

### 10. **Gamificação**
- Ranking de campanhas mais efetivas
- Badges para atingir metas
- Competição entre agentes/setores

---

## 🎯 CONCLUSÃO E RECOMENDAÇÕES

### Arquitetura Recomendada
1. ✅ **Usar sistema de listas** (flexível, reutilizável)
2. ✅ **Rotação round-robin** (simples e eficaz)
3. ✅ **Cadência configurável** (msgs/minuto + intervalo)
4. ✅ **Janela de envio** (horário + dias da semana)
5. ✅ **Validações robustas** (blacklist, duplicatas, conversas recentes)
6. ✅ **Tracking completo** (enviada, entregue, lida, respondida)
7. ✅ **Funis opcionais** (drip campaigns avançadas)

### Próximos Passos
1. **Revisar e aprovar** esta proposta de arquitetura
2. **Criar migrations** conforme especificado
3. **Implementar Models** básicos
4. **Desenvolver Services** principais
5. **Criar UI/UX** (começar pelo wizard)
6. **Implementar cron job** de processamento
7. **Testar com volume pequeno** (10-50 mensagens)
8. **Escalar gradualmente** (100, 500, 1000+)
9. **Monitorar performance** (queries, memória, tempo)
10. **Iterar e melhorar** baseado em feedback

### Estimativa de Tempo
- **Fase 1-2** (DB + Models + Services): 3-5 dias
- **Fase 3-4** (Controllers + Views básicas): 5-7 dias
- **Fase 5** (Cron + Processamento): 2-3 dias
- **Fase 6** (Testes): 2-3 dias
- **Fase 7** (Features avançadas): 5-10 dias
- **TOTAL**: 17-28 dias úteis (3-6 semanas)

---

**Documento criado em:** 18/01/2026  
**Autor:** IA Assistant (Claude Sonnet 4.5)  
**Versão:** 1.0
