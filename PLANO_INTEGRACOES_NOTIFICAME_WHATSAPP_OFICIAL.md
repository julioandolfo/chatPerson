# 📋 PLANO DE IMPLEMENTAÇÃO: INTEGRAÇÕES NOTIFICAME E WHATSAPP API OFICIAL

## 📊 ANÁLISE DO SISTEMA ATUAL

### ✅ Integrações Existentes

#### 1. **Quepasa API** (100% Implementado)
- **Status**: Completo e funcional
- **Arquivos principais**:
  - `app/Services/WhatsAppService.php` - Lógica de negócio
  - `app/Models/WhatsAppAccount.php` - Model de dados
  - `app/Controllers/IntegrationController.php` - Controller HTTP
  - `public/whatsapp-webhook.php` - Endpoint de webhook
- **Funcionalidades**:
  - ✅ CRUD de contas WhatsApp
  - ✅ Geração de QR Code
  - ✅ Verificação de status de conexão
  - ✅ Envio de mensagens (texto, mídia, áudio, documentos, stickers)
  - ✅ Recebimento via webhook
  - ✅ Configuração automática de webhook
  - ✅ Suporte a respostas/replies
  - ✅ Identificação de mensagens enviadas vs recebidas

#### 2. **Evolution API** (0% Implementado)
- **Status**: Apenas estrutura preparada
- **Observações**: Existem referências no código mas nenhuma implementação funcional

### 🗄️ Estrutura de Dados Atual

#### Tabela `whatsapp_accounts`
```sql
- id (INT)
- name (VARCHAR)
- phone_number (VARCHAR) - UNIQUE
- provider (VARCHAR) - 'quepasa', 'evolution'
- api_url (VARCHAR)
- api_key (VARCHAR)
- instance_id (VARCHAR)
- status (VARCHAR) - 'active', 'inactive', 'disconnected'
- config (JSON)
- quepasa_user (VARCHAR)
- quepasa_token (VARCHAR)
- quepasa_trackid (VARCHAR)
- quepasa_chatid (VARCHAR)
- default_funnel_id (INT)
- default_stage_id (INT)
- wavoip_token (VARCHAR)
- wavoip_enabled (BOOLEAN)
- created_at, updated_at
```

#### Tabela `conversations`
```sql
- id (INT)
- contact_id (INT)
- channel (VARCHAR) - 'whatsapp', 'email', 'chat', 'telegram'
- whatsapp_account_id (INT) - FK para whatsapp_accounts
- funnel_id (INT)
- stage_id (INT)
- assigned_to (INT)
- status (VARCHAR)
- ...
```

#### Tabela `messages`
```sql
- id (INT)
- conversation_id (INT)
- contact_id (INT)
- user_id (INT) - NULL se mensagem do contato
- content (TEXT)
- type (VARCHAR) - 'text', 'image', 'audio', 'video', 'document', 'sticker'
- metadata (JSON)
- external_id (VARCHAR) - ID da mensagem na API externa
- direction (VARCHAR) - 'inbound', 'outbound'
- ...
```

### 🔄 Fluxo Atual de Mensagens

1. **Webhook recebe mensagem** → `public/whatsapp-webhook.php`
2. **Processa payload** → `WhatsAppService::processWebhook()`
3. **Identifica conta WhatsApp** → Busca por trackid, chatid, wid, phone
4. **Cria/encontra contato** → `ContactService`
5. **Cria/encontra conversa** → `ConversationService::create()`
6. **Salva mensagem** → `Message::create()`
7. **Notifica via WebSocket** → `WebSocketService::notify*()`
8. **Executa automações** → `AutomationService`

---

## 🎯 INTEGRAÇÃO 1: NOTIFICAME API

### 📚 Análise da Documentação Notificame

#### Canais Disponíveis
1. **WhatsApp** ✅
2. **Instagram** ✅
3. **Facebook** ✅
4. **Telegram** ✅
5. **Mercado Livre** ✅
6. **WebChat** ✅
7. **Email** ✅
8. **OLX** ✅
9. **LinkedIn** ✅
10. **Google Business** ✅
11. **Youtube** ✅
12. **TikTok** ✅

#### Funcionalidades por Canal
- ✅ Envio de mensagens (texto, mídia)
- ✅ Recebimento via webhook
- ✅ Templates de mensagens
- ✅ Mensagens interativas (botões, listas)
- ✅ Status de entrega/leitura
- ✅ Bloqueio de usuários
- ✅ Publicação de posts (alguns canais)
- ✅ Listagem de reviews (Google Business)
- ✅ Health check da API

#### Autenticação
- **Header**: `X-Api-Token: {seu_token}`
- **Base URL**: `https://app.notificame.com.br/api/v1/`

### 🏗️ Arquitetura Proposta

#### 1. **Estrutura de Dados**

##### Nova Tabela: `integration_accounts`
```sql
CREATE TABLE integration_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'Nome da conta',
    provider VARCHAR(50) NOT NULL COMMENT 'notificame, whatsapp_official',
    channel VARCHAR(50) NOT NULL COMMENT 'whatsapp, instagram, facebook, telegram, mercadolivre, webchat, email, olx, linkedin, google_business, youtube, tiktok',
    api_token VARCHAR(500) NULL COMMENT 'Token da API',
    api_url VARCHAR(500) NULL DEFAULT 'https://app.notificame.com.br/api/v1/' COMMENT 'URL base da API',
    account_id VARCHAR(255) NULL COMMENT 'ID da conta na plataforma externa',
    phone_number VARCHAR(50) NULL COMMENT 'Número (para WhatsApp)',
    username VARCHAR(255) NULL COMMENT 'Username (para Instagram, Telegram, etc)',
    status VARCHAR(20) DEFAULT 'active' COMMENT 'active, inactive, disconnected, error',
    config JSON NULL COMMENT 'Configurações específicas do canal',
    webhook_url VARCHAR(500) NULL COMMENT 'URL do webhook configurada',
    webhook_secret VARCHAR(255) NULL COMMENT 'Secret para validar webhooks',
    default_funnel_id INT NULL COMMENT 'Funil padrão',
    default_stage_id INT NULL COMMENT 'Etapa padrão',
    last_sync_at TIMESTAMP NULL COMMENT 'Última sincronização',
    error_message TEXT NULL COMMENT 'Última mensagem de erro',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_provider_channel (provider, channel),
    INDEX idx_status (status),
    INDEX idx_phone_number (phone_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Campos `config` (JSON) por canal**:
- **WhatsApp**: `{ "phone_id": "...", "business_account_id": "..." }`
- **Instagram**: `{ "instagram_account_id": "...", "page_id": "..." }`
- **Facebook**: `{ "page_id": "...", "app_id": "..." }`
- **Telegram**: `{ "bot_token": "...", "chat_id": "..." }`
- **Mercado Livre**: `{ "user_id": "...", "app_id": "..." }`
- **Email**: `{ "smtp_config": {...}, "from_email": "...", "from_name": "..." }`
- **Outros**: Específicos conforme documentação

##### Migração da Tabela `whatsapp_accounts`
- **Opção 1**: Manter `whatsapp_accounts` apenas para Quepasa/Evolution
- **Opção 2**: Migrar tudo para `integration_accounts` (RECOMENDADO)
- **Decisão**: **Opção 2** - Unificar todas as integrações em uma única tabela

##### Atualização da Tabela `conversations`
- Adicionar campo `integration_account_id` (INT) - FK para `integration_accounts`
- Manter `whatsapp_account_id` para compatibilidade (deprecated)
- Atualizar `channel` para suportar novos canais:
  - `whatsapp`, `instagram`, `facebook`, `telegram`, `mercadolivre`, `webchat`, `email`, `olx`, `linkedin`, `google_business`, `youtube`, `tiktok`

#### 2. **Services**

##### `app/Services/NotificameService.php`
```php
namespace App\Services;

class NotificameService
{
    // Constantes
    const BASE_URL = 'https://app.notificame.com.br/api/v1/';
    const CHANNELS = [
        'whatsapp', 'instagram', 'facebook', 'telegram', 
        'mercadolivre', 'webchat', 'email', 'olx', 
        'linkedin', 'google_business', 'youtube', 'tiktok'
    ];
    
    // CRUD de Contas
    public static function createAccount(array $data): int
    public static function updateAccount(int $accountId, array $data): bool
    public static function deleteAccount(int $accountId): bool
    public static function getAccount(int $accountId): ?array
    public static function listAccounts(string $channel = null): array
    
    // Conexão/Status
    public static function checkConnection(int $accountId): array
    public static function getHealthStatus(): array
    
    // Envio de Mensagens
    public static function sendMessage(int $accountId, string $to, string $message, array $options = []): array
    public static function sendMedia(int $accountId, string $to, string $mediaUrl, string $type, array $options = []): array
    public static function sendTemplate(int $accountId, string $to, string $templateName, array $params = []): array
    public static function sendInteractive(int $accountId, string $to, array $interactiveData): array
    
    // Webhooks
    public static function configureWebhook(int $accountId, string $webhookUrl, array $events = []): bool
    public static function processWebhook(array $payload, string $channel): void
    
    // Templates
    public static function listTemplates(int $accountId): array
    public static function createTemplate(int $accountId, array $templateData): array
    
    // Utilitários
    public static function normalizePhoneNumber(string $phone): string
    public static function validateChannel(string $channel): bool
    private static function makeRequest(string $endpoint, string $method, array $data = [], string $token): array
    private static function getAccountToken(int $accountId): string
}
```

**Endpoints Notificame por Canal**:
- **WhatsApp**: `/whatsapp/send`, `/whatsapp/templates`, `/whatsapp/webhook`
- **Instagram**: `/instagram/send`, `/instagram/posts`, `/instagram/webhook`
- **Facebook**: `/facebook/send`, `/facebook/posts`, `/facebook/webhook`
- **Telegram**: `/telegram/send`, `/telegram/webhook`
- **Mercado Livre**: `/mercadolivre/send`, `/mercadolivre/webhook`
- **WebChat**: `/webchat/send`, `/webchat/webhook`
- **Email**: `/email/send`, `/email/webhook`
- **OLX**: `/olx/send`, `/olx/webhook`
- **LinkedIn**: `/linkedin/send`, `/linkedin/posts`, `/linkedin/webhook`
- **Google Business**: `/google-business/send`, `/google-business/reviews`, `/google-business/webhook`
- **Youtube**: `/youtube/send`, `/youtube/comments`, `/youtube/webhook`
- **TikTok**: `/tiktok/send`, `/tiktok/webhook`

##### `app/Services/IntegrationService.php` (Abstração Unificada)
```php
namespace App\Services;

class IntegrationService
{
    // Factory para obter service correto
    public static function getService(string $provider): object
    {
        switch ($provider) {
            case 'notificame':
                return new NotificameService();
            case 'quepasa':
            case 'evolution':
                return new WhatsAppService();
            case 'whatsapp_official':
                return new WhatsAppOfficialService();
            default:
                throw new \InvalidArgumentException("Provider não suportado: {$provider}");
        }
    }
    
    // Métodos unificados
    public static function sendMessage(int $accountId, string $to, string $message, array $options = []): array
    public static function processWebhook(array $payload, string $provider, string $channel): void
    public static function checkStatus(int $accountId): array
}
```

#### 3. **Models**

##### `app/Models/IntegrationAccount.php`
```php
namespace App\Models;

class IntegrationAccount extends Model
{
    protected string $table = 'integration_accounts';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name', 'provider', 'channel', 'api_token', 'api_url', 
        'account_id', 'phone_number', 'username', 'status', 
        'config', 'webhook_url', 'webhook_secret', 
        'default_funnel_id', 'default_stage_id', 
        'last_sync_at', 'error_message'
    ];
    protected bool $timestamps = true;
    
    public static function findByProviderChannel(string $provider, string $channel): array
    public static function findByPhone(string $phoneNumber, string $channel = 'whatsapp'): ?array
    public static function getActive(string $channel = null): array
    public static function getByChannel(string $channel): array
}
```

#### 4. **Controllers**

##### `app/Controllers/IntegrationController.php` (Atualizar)
```php
// Adicionar métodos:
public function notificame(): void // Listar contas Notificame
public function createNotificameAccount(): void
public function updateNotificameAccount(int $id): void
public function deleteNotificameAccount(int $id): void
public function checkNotificameStatus(int $id): void
public function sendNotificameTestMessage(int $id): void
public function configureNotificameWebhook(int $id): void
public function listNotificameTemplates(int $id): void
```

#### 5. **Webhooks**

##### `public/notificame-webhook.php` (Novo)
```php
<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Services\NotificameService;

// Receber payload
$payload = json_decode(file_get_contents('php://input'), true);

// Validar secret (se configurado)
$secret = $_GET['secret'] ?? null;
// ... validação ...

// Identificar canal do payload
$channel = $payload['channel'] ?? 'whatsapp';

// Processar webhook
try {
    NotificameService::processWebhook($payload, $channel);
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

#### 6. **Views**

##### `views/integrations/notificame/index.php` (Novo)
- Lista de contas Notificame por canal
- Formulário de criação/edição
- Status de conexão
- Teste de envio
- Configuração de webhook
- Lista de templates

##### `views/integrations/notificame/channels/` (Novo)
- `whatsapp.php` - Configuração específica WhatsApp
- `instagram.php` - Configuração específica Instagram
- `facebook.php` - Configuração específica Facebook
- `telegram.php` - Configuração específica Telegram
- `email.php` - Configuração específica Email
- `mercadolivre.php` - Configuração específica Mercado Livre
- `webchat.php` - Configuração específica WebChat
- `olx.php` - Configuração específica OLX
- `linkedin.php` - Configuração específica LinkedIn
- `google-business.php` - Configuração específica Google Business
- `youtube.php` - Configuração específica Youtube
- `tiktok.php` - Configuração específica TikTok

#### 7. **Rotas**

##### `routes/web.php` (Adicionar)
```php
// Notificame
Router::get('/integrations/notificame', [IntegrationController::class, 'notificame'], ['Authentication']);
Router::post('/integrations/notificame/accounts', [IntegrationController::class, 'createNotificameAccount'], ['Authentication', 'Permission:integrations.create']);
Router::put('/integrations/notificame/accounts/{id}', [IntegrationController::class, 'updateNotificameAccount'], ['Authentication', 'Permission:integrations.edit']);
Router::delete('/integrations/notificame/accounts/{id}', [IntegrationController::class, 'deleteNotificameAccount'], ['Authentication', 'Permission:integrations.delete']);
Router::get('/integrations/notificame/accounts/{id}/status', [IntegrationController::class, 'checkNotificameStatus'], ['Authentication']);
Router::post('/integrations/notificame/accounts/{id}/test', [IntegrationController::class, 'sendNotificameTestMessage'], ['Authentication']);
Router::post('/integrations/notificame/accounts/{id}/webhook', [IntegrationController::class, 'configureNotificameWebhook'], ['Authentication', 'Permission:integrations.edit']);
Router::get('/integrations/notificame/accounts/{id}/templates', [IntegrationController::class, 'listNotificameTemplates'], ['Authentication']);

// Webhook Notificame
Router::post('/webhooks/notificame', 'notificame-webhook.php');
```

#### 8. **Permissões**

##### `database/seeds/002_create_roles_and_permissions.php` (Adicionar)
```php
// Permissões Notificame
Permission::create(['name' => 'Visualizar Integrações Notificame', 'slug' => 'notificame.view', 'module' => 'integrations']);
Permission::create(['name' => 'Criar Contas Notificame', 'slug' => 'notificame.create', 'module' => 'integrations']);
Permission::create(['name' => 'Editar Contas Notificame', 'slug' => 'notificame.edit', 'module' => 'integrations']);
Permission::create(['name' => 'Deletar Contas Notificame', 'slug' => 'notificame.delete', 'module' => 'integrations']);
Permission::create(['name' => 'Enviar Mensagens Notificame', 'slug' => 'notificame.send', 'module' => 'integrations']);
```

#### 9. **Menu**

##### `views/layouts/metronic/sidebar.php` (Adicionar)
```php
// Adicionar item de menu para Notificame
<li class="menu-item">
    <a href="/integrations/notificame" class="menu-link">
        <i class="menu-icon fs-2 bi bi-chat-dots"></i>
        <span class="menu-title">Notificame</span>
    </a>
</li>
```

### 📝 Implementação Detalhada por Canal

#### **WhatsApp (Notificame)**
- Endpoint: `/whatsapp/send`
- Payload: `{ "to": "5511999999999", "message": "...", "media": {...} }`
- Webhook: Recebe eventos de mensagens, status, etc.

#### **Instagram**
- Endpoint: `/instagram/send`
- Payload: `{ "to": "@username", "message": "...", "media": {...} }`
- Webhook: Recebe mensagens diretas, comentários, etc.
- Extra: Publicação de posts (`/instagram/posts`)

#### **Facebook**
- Endpoint: `/facebook/send`
- Payload: `{ "to": "page_id", "message": "...", "media": {...} }`
- Webhook: Recebe mensagens, comentários, reações
- Extra: Publicação de posts (`/facebook/posts`)

#### **Telegram**
- Endpoint: `/telegram/send`
- Payload: `{ "to": "@username", "message": "...", "media": {...} }`
- Webhook: Recebe mensagens, comandos, etc.

#### **Mercado Livre**
- Endpoint: `/mercadolivre/send`
- Payload: `{ "to": "user_id", "message": "...", "order_id": "..." }`
- Webhook: Recebe mensagens de perguntas, respostas

#### **WebChat**
- Endpoint: `/webchat/send`
- Payload: `{ "to": "session_id", "message": "...", "media": {...} }`
- Webhook: Recebe mensagens do chat widget

#### **Email**
- Endpoint: `/email/send`
- Payload: `{ "to": "email@example.com", "subject": "...", "body": "...", "attachments": [...] }`
- Webhook: Recebe respostas de email

#### **OLX**
- Endpoint: `/olx/send`
- Payload: `{ "to": "user_id", "message": "...", "ad_id": "..." }`
- Webhook: Recebe mensagens de anúncios

#### **LinkedIn**
- Endpoint: `/linkedin/send`
- Payload: `{ "to": "profile_id", "message": "...", "media": {...} }`
- Webhook: Recebe mensagens, conexões
- Extra: Publicação de posts (`/linkedin/posts`)

#### **Google Business**
- Endpoint: `/google-business/send`
- Payload: `{ "to": "location_id", "message": "...", "media": {...} }`
- Webhook: Recebe mensagens, reviews
- Extra: Listar reviews (`/google-business/reviews`)

#### **Youtube**
- Endpoint: `/youtube/send`
- Payload: `{ "to": "channel_id", "message": "...", "video_id": "..." }`
- Webhook: Recebe comentários, mensagens

#### **TikTok**
- Endpoint: `/tiktok/send`
- Payload: `{ "to": "user_id", "message": "...", "media": {...} }`
- Webhook: Recebe mensagens, comentários

### 🔄 Processamento de Webhooks Notificame

```php
public static function processWebhook(array $payload, string $channel): void
{
    // 1. Identificar conta pela configuração do webhook
    $account = self::findAccountByWebhook($payload);
    
    // 2. Extrair dados da mensagem
    $messageData = self::extractMessageData($payload, $channel);
    
    // 3. Criar/encontrar contato
    $contact = ContactService::findOrCreate([
        'identifier' => $messageData['from'],
        'channel' => $channel,
        'name' => $messageData['name'] ?? null
    ]);
    
    // 4. Criar/encontrar conversa
    $conversation = ConversationService::findOrCreate([
        'contact_id' => $contact['id'],
        'channel' => $channel,
        'integration_account_id' => $account['id']
    ]);
    
    // 5. Salvar mensagem
    Message::create([
        'conversation_id' => $conversation['id'],
        'contact_id' => $contact['id'],
        'content' => $messageData['content'],
        'type' => $messageData['type'],
        'external_id' => $messageData['external_id'],
        'direction' => 'inbound',
        'metadata' => $messageData['metadata']
    ]);
    
    // 6. Notificar via WebSocket
    WebSocketService::notifyNewMessage($conversation['id']);
    
    // 7. Executar automações
    AutomationService::trigger('message.received', [
        'conversation_id' => $conversation['id'],
        'channel' => $channel
    ]);
}
```

---

## 🎯 INTEGRAÇÃO 2: WHATSAPP API OFICIAL (META)

### 📚 Análise da WhatsApp Business API

#### Requisitos
- **Conta Business**: WhatsApp Business Account (WABA)
- **App no Meta**: App criado no Meta for Developers
- **Verificação**: Verificação de negócio (Business Verification)
- **Número**: Número de telefone verificado
- **Token de Acesso**: Access Token do Graph API
- **Webhook**: Configuração de webhook no Meta

#### Funcionalidades
- ✅ Envio de mensagens (texto, mídia, templates)
- ✅ Recebimento via webhook
- ✅ Templates de mensagens (aprovados pelo Meta)
- ✅ Mensagens interativas (botões, listas)
- ✅ Status de entrega/leitura
- ✅ Perfis de negócio
- ✅ Mídia (upload/download)
- ✅ Etiquetas de conversas

#### Autenticação
- **Graph API**: `https://graph.facebook.com/v18.0/`
- **Header**: `Authorization: Bearer {access_token}`
- **App ID/Secret**: Para validar webhooks

### 🏗️ Arquitetura Proposta

#### 1. **Estrutura de Dados**

##### Usar Tabela `integration_accounts` (mesma da Notificame)
- `provider` = `'whatsapp_official'`
- `channel` = `'whatsapp'`
- `api_token` = Access Token do Meta
- `config` (JSON):
```json
{
    "phone_number_id": "...",
    "business_account_id": "...",
    "app_id": "...",
    "app_secret": "...",
    "verify_token": "...",
    "webhook_secret": "...",
    "waba_id": "..."
}
```

#### 2. **Services**

##### `app/Services/WhatsAppOfficialService.php`
```php
namespace App\Services;

class WhatsAppOfficialService
{
    const BASE_URL = 'https://graph.facebook.com/v18.0/';
    
    // CRUD de Contas
    public static function createAccount(array $data): int
    public static function updateAccount(int $accountId, array $data): bool
    public static function deleteAccount(int $accountId): bool
    public static function getAccount(int $accountId): ?array
    
    // Conexão/Status
    public static function checkConnection(int $accountId): array
    public static function verifyWebhook(string $mode, string $token, string $challenge): ?string
    
    // Envio de Mensagens
    public static function sendMessage(int $accountId, string $to, string $message, array $options = []): array
    public static function sendTemplate(int $accountId, string $to, string $templateName, string $language, array $params = []): array
    public static function sendMedia(int $accountId, string $to, string $mediaUrl, string $type, array $options = []): array
    public static function sendInteractive(int $accountId, string $to, array $interactiveData): array
    
    // Templates
    public static function listTemplates(int $accountId): array
    public static function createTemplate(int $accountId, array $templateData): array
    
    // Mídia
    public static function uploadMedia(int $accountId, string $filePath, string $type): array
    public static function downloadMedia(int $accountId, string $mediaId): string
    
    // Webhooks
    public static function configureWebhook(int $accountId, string $webhookUrl, string $verifyToken): bool
    public static function processWebhook(array $payload): void
    
    // Perfil de Negócio
    public static function getBusinessProfile(int $accountId): array
    public static function updateBusinessProfile(int $accountId, array $data): bool
    
    // Utilitários
    public static function normalizePhoneNumber(string $phone): string
    private static function makeRequest(string $endpoint, string $method, array $data = [], string $token): array
    private static function getAccountToken(int $accountId): string
    private static function validateWebhookSignature(string $payload, string $signature, string $secret): bool
}
```

**Endpoints WhatsApp Official**:
- **Enviar Mensagem**: `POST /{phone_number_id}/messages`
- **Templates**: `GET /{waba_id}/message_templates`
- **Upload Mídia**: `POST /{phone_number_id}/media`
- **Download Mídia**: `GET /{media_id}`
- **Perfil**: `GET /{phone_number_id}/whatsapp_business_profile`
- **Webhook**: Configurado no Meta App Dashboard

#### 3. **Controllers**

##### `app/Controllers/IntegrationController.php` (Adicionar)
```php
public function whatsappOfficial(): void // Listar contas WhatsApp Official
public function createWhatsAppOfficialAccount(): void
public function updateWhatsAppOfficialAccount(int $id): void
public function deleteWhatsAppOfficialAccount(int $id): void
public function checkWhatsAppOfficialStatus(int $id): void
public function sendWhatsAppOfficialTestMessage(int $id): void
public function configureWhatsAppOfficialWebhook(int $id): void
public function listWhatsAppOfficialTemplates(int $id): void
public function uploadWhatsAppOfficialMedia(int $id): void
public function getWhatsAppOfficialBusinessProfile(int $id): void
```

#### 4. **Webhooks**

##### `public/whatsapp-official-webhook.php` (Novo)
```php
<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Services\WhatsAppOfficialService;

// Verificação do webhook (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? null;
    $token = $_GET['hub_verify_token'] ?? null;
    $challenge = $_GET['hub_challenge'] ?? null;
    
    $result = WhatsAppOfficialService::verifyWebhook($mode, $token, $challenge);
    if ($result) {
        echo $result;
        exit;
    }
    http_response_code(403);
    exit;
}

// Receber eventos (POST)
$payload = json_decode(file_get_contents('php://input'), true);

// Validar assinatura
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? null;
// ... validação ...

// Processar webhook
try {
    WhatsAppOfficialService::processWebhook($payload);
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

#### 5. **Views**

##### `views/integrations/whatsapp-official/index.php` (Novo)
- Lista de contas WhatsApp Official
- Formulário de criação/edição
- Status de conexão
- Teste de envio
- Configuração de webhook
- Lista de templates
- Upload de mídia
- Perfil de negócio

#### 6. **Rotas**

##### `routes/web.php` (Adicionar)
```php
// WhatsApp Official
Router::get('/integrations/whatsapp-official', [IntegrationController::class, 'whatsappOfficial'], ['Authentication']);
Router::post('/integrations/whatsapp-official/accounts', [IntegrationController::class, 'createWhatsAppOfficialAccount'], ['Authentication', 'Permission:integrations.create']);
Router::put('/integrations/whatsapp-official/accounts/{id}', [IntegrationController::class, 'updateWhatsAppOfficialAccount'], ['Authentication', 'Permission:integrations.edit']);
Router::delete('/integrations/whatsapp-official/accounts/{id}', [IntegrationController::class, 'deleteWhatsAppOfficialAccount'], ['Authentication', 'Permission:integrations.delete']);
Router::get('/integrations/whatsapp-official/accounts/{id}/status', [IntegrationController::class, 'checkWhatsAppOfficialStatus'], ['Authentication']);
Router::post('/integrations/whatsapp-official/accounts/{id}/test', [IntegrationController::class, 'sendWhatsAppOfficialTestMessage'], ['Authentication']);
Router::post('/integrations/whatsapp-official/accounts/{id}/webhook', [IntegrationController::class, 'configureWhatsAppOfficialWebhook'], ['Authentication', 'Permission:integrations.edit']);
Router::get('/integrations/whatsapp-official/accounts/{id}/templates', [IntegrationController::class, 'listWhatsAppOfficialTemplates'], ['Authentication']);
Router::post('/integrations/whatsapp-official/accounts/{id}/media/upload', [IntegrationController::class, 'uploadWhatsAppOfficialMedia'], ['Authentication']);
Router::get('/integrations/whatsapp-official/accounts/{id}/business-profile', [IntegrationController::class, 'getWhatsAppOfficialBusinessProfile'], ['Authentication']);

// Webhook WhatsApp Official
Router::get('/webhooks/whatsapp-official', 'whatsapp-official-webhook.php');
Router::post('/webhooks/whatsapp-official', 'whatsapp-official-webhook.php');
```

#### 7. **Permissões**

##### `database/seeds/002_create_roles_and_permissions.php` (Adicionar)
```php
// Permissões WhatsApp Official
Permission::create(['name' => 'Visualizar Integrações WhatsApp Official', 'slug' => 'whatsapp_official.view', 'module' => 'integrations']);
Permission::create(['name' => 'Criar Contas WhatsApp Official', 'slug' => 'whatsapp_official.create', 'module' => 'integrations']);
Permission::create(['name' => 'Editar Contas WhatsApp Official', 'slug' => 'whatsapp_official.edit', 'module' => 'integrations']);
Permission::create(['name' => 'Deletar Contas WhatsApp Official', 'slug' => 'whatsapp_official.delete', 'module' => 'integrations']);
Permission::create(['name' => 'Enviar Mensagens WhatsApp Official', 'slug' => 'whatsapp_official.send', 'module' => 'integrations']);
```

#### 8. **Menu**

##### `views/layouts/metronic/sidebar.php` (Adicionar)
```php
// Adicionar item de menu para WhatsApp Official
<li class="menu-item">
    <a href="/integrations/whatsapp-official" class="menu-link">
        <i class="menu-icon fs-2 bi bi-whatsapp"></i>
        <span class="menu-title">WhatsApp Official</span>
    </a>
</li>
```

### 🔄 Processamento de Webhooks WhatsApp Official

```php
public static function processWebhook(array $payload): void
{
    // Payload do Meta vem no formato:
    // {
    //   "object": "whatsapp_business_account",
    //   "entry": [{
    //     "id": "...",
    //     "changes": [{
    //       "value": {
    //         "messaging_product": "whatsapp",
    //         "metadata": {...},
    //         "messages": [...],
    //         "statuses": [...]
    //       }
    //     }]
    //   }]
    // }
    
    if (!isset($payload['entry'])) {
        return;
    }
    
    foreach ($payload['entry'] as $entry) {
        $accountId = $entry['id'] ?? null;
        
        if (!isset($entry['changes'])) {
            continue;
        }
        
        foreach ($entry['changes'] as $change) {
            $value = $change['value'] ?? [];
            
            // Processar mensagens recebidas
            if (isset($value['messages'])) {
                foreach ($value['messages'] as $message) {
                    self::processIncomingMessage($accountId, $message, $value['metadata']);
                }
            }
            
            // Processar status de mensagens
            if (isset($value['statuses'])) {
                foreach ($value['statuses'] as $status) {
                    self::processMessageStatus($accountId, $status);
                }
            }
        }
    }
}

private static function processIncomingMessage(string $accountId, array $message, array $metadata): void
{
    // 1. Encontrar conta pela phone_number_id
    $account = IntegrationAccount::findByProviderChannel('whatsapp_official', 'whatsapp');
    // Filtrar pela phone_number_id no config
    
    // 2. Extrair dados
    $from = $message['from'];
    $messageId = $message['id'];
    $type = $message['type'];
    $content = self::extractContent($message, $type);
    
    // 3. Criar/encontrar contato
    $contact = ContactService::findOrCreate([
        'identifier' => $from,
        'channel' => 'whatsapp',
        'name' => $message['profile']['name'] ?? null
    ]);
    
    // 4. Criar/encontrar conversa
    $conversation = ConversationService::findOrCreate([
        'contact_id' => $contact['id'],
        'channel' => 'whatsapp',
        'integration_account_id' => $account['id']
    ]);
    
    // 5. Salvar mensagem
    Message::create([
        'conversation_id' => $conversation['id'],
        'contact_id' => $contact['id'],
        'content' => $content,
        'type' => $type,
        'external_id' => $messageId,
        'direction' => 'inbound',
        'metadata' => [
            'timestamp' => $message['timestamp'],
            'metadata' => $metadata
        ]
    ]);
    
    // 6. Notificar via WebSocket
    WebSocketService::notifyNewMessage($conversation['id']);
    
    // 7. Executar automações
    AutomationService::trigger('message.received', [
        'conversation_id' => $conversation['id'],
        'channel' => 'whatsapp'
    ]);
}
```

---

## 🔄 MIGRAÇÃO DE DADOS

### Migração de `whatsapp_accounts` para `integration_accounts`

#### Migration: `database/migrations/XXX_migrate_whatsapp_to_integration_accounts.php`
```php
function up_migrate_whatsapp_to_integration_accounts() {
    global $pdo;
    
    // 1. Criar tabela integration_accounts (se não existir)
    // ... código da criação ...
    
    // 2. Migrar dados de whatsapp_accounts para integration_accounts
    $sql = "
        INSERT INTO integration_accounts 
        (name, provider, channel, phone_number, api_url, api_key, instance_id, status, config, default_funnel_id, default_stage_id, created_at, updated_at)
        SELECT 
            name,
            provider,
            'whatsapp' as channel,
            phone_number,
            api_url,
            api_key,
            instance_id,
            status,
            JSON_OBJECT(
                'quepasa_user', quepasa_user,
                'quepasa_token', quepasa_token,
                'quepasa_trackid', quepasa_trackid,
                'quepasa_chatid', quepasa_chatid,
                'wavoip_token', wavoip_token,
                'wavoip_enabled', wavoip_enabled
            ) as config,
            default_funnel_id,
            default_stage_id,
            created_at,
            updated_at
        FROM whatsapp_accounts
    ";
    
    $pdo->exec($sql);
    
    // 3. Atualizar conversations para usar integration_account_id
    $sql = "
        UPDATE conversations c
        INNER JOIN whatsapp_accounts wa ON c.whatsapp_account_id = wa.id
        INNER JOIN integration_accounts ia ON ia.phone_number = wa.phone_number 
            AND ia.provider = wa.provider 
            AND ia.channel = 'whatsapp'
        SET c.integration_account_id = ia.id
        WHERE c.whatsapp_account_id IS NOT NULL
    ";
    
    $pdo->exec($sql);
    
    echo "✅ Migração concluída!\n";
}
```

### Atualização de `conversations`

#### Migration: `database/migrations/XXX_add_integration_account_to_conversations.php`
```php
function up_add_integration_account_to_conversations() {
    global $pdo;
    
    // Adicionar coluna integration_account_id
    $sql = "ALTER TABLE conversations 
            ADD COLUMN integration_account_id INT NULL AFTER whatsapp_account_id,
            ADD INDEX idx_integration_account_id (integration_account_id),
            ADD FOREIGN KEY (integration_account_id) REFERENCES integration_accounts(id) ON DELETE SET NULL";
    
    $pdo->exec($sql);
    
    // Atualizar channel para suportar novos canais
    $sql = "ALTER TABLE conversations 
            MODIFY COLUMN channel VARCHAR(50) NOT NULL 
            COMMENT 'whatsapp, instagram, facebook, telegram, mercadolivre, webchat, email, olx, linkedin, google_business, youtube, tiktok'";
    
    $pdo->exec($sql);
    
    echo "✅ Tabela conversations atualizada!\n";
}
```

---

## 📋 CHECKLIST DE IMPLEMENTAÇÃO

### Fase 1: Estrutura Base
- [ ] Criar migration `integration_accounts`
- [ ] Criar migration `add_integration_account_to_conversations`
- [ ] Criar migration `migrate_whatsapp_to_integration_accounts`
- [ ] Criar Model `IntegrationAccount`
- [ ] Criar Service `IntegrationService` (abstração)
- [ ] Atualizar `ConversationService` para usar `integration_account_id`

### Fase 2: Notificame
- [ ] Criar Service `NotificameService`
- [ ] Criar Controller methods para Notificame
- [ ] Criar webhook `notificame-webhook.php`
- [ ] Criar views para Notificame
- [ ] Adicionar rotas Notificame
- [ ] Adicionar permissões Notificame
- [ ] Adicionar menu Notificame
- [ ] Implementar suporte a todos os 12 canais

### Fase 3: WhatsApp Official
- [ ] Criar Service `WhatsAppOfficialService`
- [ ] Criar Controller methods para WhatsApp Official
- [ ] Criar webhook `whatsapp-official-webhook.php`
- [ ] Criar views para WhatsApp Official
- [ ] Adicionar rotas WhatsApp Official
- [ ] Adicionar permissões WhatsApp Official
- [ ] Adicionar menu WhatsApp Official

### Fase 4: Integração com Sistema
- [ ] Atualizar `MessageService` para suportar novos canais
- [ ] Atualizar `ConversationService` para novos canais
- [ ] Atualizar `ContactService` para novos canais
- [ ] Atualizar `AutomationService` para novos canais
- [ ] Atualizar WebSocket para novos canais
- [ ] Atualizar views de conversas para novos canais

### Fase 5: Testes e Validação
- [ ] Testar criação de contas Notificame (todos os canais)
- [ ] Testar envio de mensagens Notificame
- [ ] Testar webhooks Notificame
- [ ] Testar criação de contas WhatsApp Official
- [ ] Testar envio de mensagens WhatsApp Official
- [ ] Testar webhooks WhatsApp Official
- [ ] Testar migração de dados
- [ ] Validar permissões

### Fase 6: Documentação
- [ ] Documentar API Notificame
- [ ] Documentar API WhatsApp Official
- [ ] Atualizar `CONTEXT_IA.md`
- [ ] Atualizar `ARQUITETURA.md`
- [ ] Criar guia de configuração

---

## 🎯 ORDEM DE EXECUÇÃO RECOMENDADA

1. **Estrutura Base** (Fase 1)
   - Criar tabelas e models
   - Migrar dados existentes
   - Criar abstração `IntegrationService`

2. **Notificame** (Fase 2)
   - Implementar service completo
   - Implementar webhook
   - Implementar views e controllers
   - Testar com WhatsApp primeiro, depois expandir para outros canais

3. **WhatsApp Official** (Fase 3)
   - Implementar service completo
   - Implementar webhook
   - Implementar views e controllers
   - Testar integração completa

4. **Integração com Sistema** (Fase 4)
   - Atualizar serviços existentes
   - Garantir compatibilidade
   - Testar fluxo completo

5. **Testes e Documentação** (Fases 5 e 6)
   - Testes completos
   - Documentação atualizada

---

## ⚠️ CONSIDERAÇÕES IMPORTANTES

### Compatibilidade com Sistema Existente
- Manter `whatsapp_account_id` em `conversations` por compatibilidade (deprecated)
- Criar aliases/métodos de compatibilidade em `WhatsAppService`
- Migração gradual de código existente

### Performance
- Cache de contas de integração
- Rate limiting nas APIs externas
- Queue para envio de mensagens em massa
- Retry logic para falhas de API

### Segurança
- Criptografar tokens de API no banco
- Validação de assinatura de webhooks
- Rate limiting em webhooks
- Logs de auditoria

### Escalabilidade
- Suporte a múltiplas contas do mesmo canal
- Distribuição de carga entre contas
- Fallback automático em caso de falha

---

## 📊 RESUMO

### Arquivos a Criar
- `database/migrations/XXX_create_integration_accounts_table.php`
- `database/migrations/XXX_add_integration_account_to_conversations.php`
- `database/migrations/XXX_migrate_whatsapp_to_integration_accounts.php`
- `app/Models/IntegrationAccount.php`
- `app/Services/NotificameService.php`
- `app/Services/WhatsAppOfficialService.php`
- `app/Services/IntegrationService.php`
- `public/notificame-webhook.php`
- `public/whatsapp-official-webhook.php`
- `views/integrations/notificame/index.php`
- `views/integrations/whatsapp-official/index.php`
- `views/integrations/notificame/channels/*.php` (12 arquivos)

### Arquivos a Modificar
- `app/Controllers/IntegrationController.php`
- `app/Services/ConversationService.php`
- `app/Services/MessageService.php` (se existir)
- `app/Services/ContactService.php`
- `routes/web.php`
- `database/seeds/002_create_roles_and_permissions.php`
- `views/layouts/metronic/sidebar.php`
- `CONTEXT_IA.md`
- `ARQUITETURA.md`

### Estatísticas
- **Novos Canais**: 12 (Notificame) + 1 (WhatsApp Official) = 13 canais
- **Novos Services**: 3 (`NotificameService`, `WhatsAppOfficialService`, `IntegrationService`)
- **Novos Models**: 1 (`IntegrationAccount`)
- **Novas Migrations**: 3
- **Novos Controllers Methods**: ~20
- **Novas Views**: ~15
- **Novas Rotas**: ~25
- **Novas Permissões**: ~10

---

## ✅ APROVAÇÃO NECESSÁRIA

Este plano está completo e pronto para execução. Por favor, revise e aprove antes de iniciar a implementação.

**Pontos para revisão**:
1. ✅ Estrutura de dados proposta (`integration_accounts`)
2. ✅ Migração de `whatsapp_accounts` para `integration_accounts`
3. ✅ Suporte a todos os 12 canais do Notificame
4. ✅ Integração com WhatsApp Official
5. ✅ Ordem de execução proposta
6. ✅ Compatibilidade com sistema existente

**Aguardando aprovação para iniciar implementação** 🚀

