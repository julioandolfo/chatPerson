# 🛣️ ROTAS DE CAMPANHAS - REFERÊNCIA RÁPIDA

**Data:** 18/01/2026

Este documento lista todas as rotas disponíveis para o módulo de Campanhas.

---

## 📋 ADICIONAR NO `routes/web.php`

Adicione estas rotas no arquivo `routes/web.php`:

```php
<?php
// ============================================
// CAMPANHAS - Rotas
// ============================================

use App\Controllers\CampaignController;
use App\Controllers\ContactListController;

// LISTAS DE CONTATOS
Router::get('/contact-lists', [ContactListController::class, 'index'], ['Authentication']);
Router::get('/contact-lists/create', [ContactListController::class, 'create'], ['Authentication']);
Router::post('/contact-lists', [ContactListController::class, 'store'], ['Authentication']);
Router::get('/contact-lists/{id}', [ContactListController::class, 'show'], ['Authentication']);
Router::get('/contact-lists/{id}/edit', [ContactListController::class, 'edit'], ['Authentication']);
Router::post('/contact-lists/{id}', [ContactListController::class, 'update'], ['Authentication']);
Router::delete('/contact-lists/{id}', [ContactListController::class, 'destroy'], ['Authentication']);

// Gerenciar contatos da lista
Router::post('/contact-lists/{id}/contacts', [ContactListController::class, 'addContact'], ['Authentication']);
Router::delete('/contact-lists/{id}/contacts', [ContactListController::class, 'removeContact'], ['Authentication']);
Router::post('/contact-lists/{id}/import-csv', [ContactListController::class, 'importCsv'], ['Authentication']);
Router::post('/contact-lists/{id}/clear', [ContactListController::class, 'clear'], ['Authentication']);

// API
Router::get('/api/contact-lists/{id}/contacts', [ContactListController::class, 'contacts'], ['Authentication']);
Router::get('/api/contacts/search', [ContactListController::class, 'searchContacts'], ['Authentication']);

// CAMPANHAS
Router::get('/campaigns', [CampaignController::class, 'index'], ['Authentication']);
Router::get('/campaigns/create', [CampaignController::class, 'create'], ['Authentication']);
Router::post('/campaigns', [CampaignController::class, 'store'], ['Authentication']);
Router::get('/campaigns/{id}', [CampaignController::class, 'show'], ['Authentication']);
Router::get('/campaigns/{id}/edit', [CampaignController::class, 'edit'], ['Authentication']);
Router::post('/campaigns/{id}', [CampaignController::class, 'update'], ['Authentication']);
Router::delete('/campaigns/{id}', [CampaignController::class, 'destroy'], ['Authentication']);

// Controle de campanha
Router::post('/campaigns/{id}/prepare', [CampaignController::class, 'prepare'], ['Authentication']);
Router::post('/campaigns/{id}/start', [CampaignController::class, 'start'], ['Authentication']);
Router::post('/campaigns/{id}/pause', [CampaignController::class, 'pause'], ['Authentication']);
Router::post('/campaigns/{id}/resume', [CampaignController::class, 'resume'], ['Authentication']);
Router::post('/campaigns/{id}/cancel', [CampaignController::class, 'cancel'], ['Authentication']);

// API
Router::get('/api/campaigns', [CampaignController::class, 'list'], ['Authentication']);
Router::get('/api/campaigns/{id}/stats', [CampaignController::class, 'stats'], ['Authentication']);
```

---

## 🔗 ROTAS DISPONÍVEIS

### **LISTAS DE CONTATOS**

| Método | Rota | Ação | Descrição |
|--------|------|------|-----------|
| GET | `/contact-lists` | index | Lista todas as listas |
| GET | `/contact-lists/create` | create | Form criar lista |
| POST | `/contact-lists` | store | Salvar nova lista |
| GET | `/contact-lists/{id}` | show | Ver detalhes da lista |
| GET | `/contact-lists/{id}/edit` | edit | Form editar lista |
| POST | `/contact-lists/{id}` | update | Atualizar lista |
| DELETE | `/contact-lists/{id}` | destroy | Deletar lista |

#### Gerenciar Contatos
| Método | Rota | Ação | Descrição |
|--------|------|------|-----------|
| POST | `/contact-lists/{id}/contacts` | addContact | Adicionar contato |
| DELETE | `/contact-lists/{id}/contacts` | removeContact | Remover contato |
| POST | `/contact-lists/{id}/import-csv` | importCsv | Upload CSV |
| POST | `/contact-lists/{id}/clear` | clear | Limpar lista |

#### API
| Método | Rota | Ação | Descrição |
|--------|------|------|-----------|
| GET | `/api/contact-lists/{id}/contacts` | contacts | Listar contatos (JSON) |
| GET | `/api/contacts/search?q=nome` | searchContacts | Buscar contatos (JSON) |

---

### **CAMPANHAS**

| Método | Rota | Ação | Descrição |
|--------|------|------|-----------|
| GET | `/campaigns` | index | Lista todas campanhas |
| GET | `/campaigns/create` | create | Form criar campanha |
| POST | `/campaigns` | store | Salvar nova campanha |
| GET | `/campaigns/{id}` | show | Ver detalhes da campanha |
| GET | `/campaigns/{id}/edit` | edit | Form editar campanha |
| POST | `/campaigns/{id}` | update | Atualizar campanha |
| DELETE | `/campaigns/{id}` | destroy | Deletar campanha |

#### Controle de Campanha
| Método | Rota | Ação | Descrição |
|--------|------|------|-----------|
| POST | `/campaigns/{id}/prepare` | prepare | Preparar (criar mensagens) |
| POST | `/campaigns/{id}/start` | start | Iniciar envio |
| POST | `/campaigns/{id}/pause` | pause | Pausar envio |
| POST | `/campaigns/{id}/resume` | resume | Retomar envio |
| POST | `/campaigns/{id}/cancel` | cancel | Cancelar campanha |

#### API
| Método | Rota | Ação | Descrição |
|--------|------|------|-----------|
| GET | `/api/campaigns` | list | Listar campanhas (JSON) |
| GET | `/api/campaigns/{id}/stats` | stats | Estatísticas (JSON) |

---

## 📝 EXEMPLOS DE USO

### Via JavaScript (AJAX)

```javascript
// Criar campanha
fetch('/campaigns', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        name: 'Campanha Teste',
        channel: 'whatsapp',
        target_type: 'list',
        contact_list_id: 1,
        message_content: 'Olá {{nome}}!',
        integration_account_ids: [1, 2],
        send_rate_per_minute: 10
    })
})
.then(r => r.json())
.then(data => console.log(data));

// Preparar campanha
fetch('/campaigns/1/prepare', {method: 'POST'})
    .then(r => r.json())
    .then(data => console.log(data));

// Iniciar campanha
fetch('/campaigns/1/start', {method: 'POST'})
    .then(r => r.json())
    .then(data => console.log(data));

// Ver estatísticas
fetch('/api/campaigns/1/stats')
    .then(r => r.json())
    .then(data => console.log(data));
```

### Via cURL

```bash
# Criar lista
curl -X POST http://localhost/contact-lists \
  -H "Content-Type: application/json" \
  -d '{"name": "Minha Lista", "description": "Teste"}'

# Adicionar contato à lista
curl -X POST http://localhost/contact-lists/1/contacts \
  -H "Content-Type: application/json" \
  -d '{"contact_id": 5}'

# Criar campanha
curl -X POST http://localhost/campaigns \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Campanha Teste",
    "contact_list_id": 1,
    "message_content": "Olá!",
    "integration_account_ids": [1,2]
  }'

# Iniciar campanha
curl -X POST http://localhost/campaigns/1/start

# Ver estatísticas
curl http://localhost/api/campaigns/1/stats
```

---

## 🔐 PERMISSÕES NECESSÁRIAS

Adicione estas permissões no seed de permissões:

```php
// Em database/seeds/002_create_roles_and_permissions.php

// Campanhas
Permission::create(['name' => 'Visualizar Campanhas', 'slug' => 'campaigns.view', 'module' => 'campaigns']);
Permission::create(['name' => 'Criar Campanhas', 'slug' => 'campaigns.create', 'module' => 'campaigns']);
Permission::create(['name' => 'Editar Campanhas', 'slug' => 'campaigns.edit', 'module' => 'campaigns']);
Permission::create(['name' => 'Deletar Campanhas', 'slug' => 'campaigns.delete', 'module' => 'campaigns']);
```

---

## 🎯 FLUXO COMPLETO VIA API

```javascript
// 1. Criar lista
const listResponse = await fetch('/contact-lists', {
    method: 'POST',
    body: JSON.stringify({name: 'Minha Lista'})
});
const {list_id} = await listResponse.json();

// 2. Adicionar contatos
await fetch(`/contact-lists/${list_id}/contacts`, {
    method: 'POST',
    body: JSON.stringify({contact_id: 1})
});

// 3. Criar campanha
const campResponse = await fetch('/campaigns', {
    method: 'POST',
    body: JSON.stringify({
        name: 'Black Friday',
        contact_list_id: list_id,
        message_content: 'Olá {{nome}}! Oferta especial...',
        integration_account_ids: [1, 2, 3],
        rotation_strategy: 'round_robin',
        send_rate_per_minute: 20
    })
});
const {campaign_id} = await campResponse.json();

// 4. Preparar
await fetch(`/campaigns/${campaign_id}/prepare`, {method: 'POST'});

// 5. Iniciar
await fetch(`/campaigns/${campaign_id}/start`, {method: 'POST'});

// 6. Monitorar
setInterval(async () => {
    const stats = await fetch(`/api/campaigns/${campaign_id}/stats`).then(r => r.json());
    console.log(stats);
}, 5000); // A cada 5 segundos
```

---

## 📊 RESPOSTA DAS APIs

### GET `/api/campaigns/{id}/stats`
```json
{
  "success": true,
  "campaign": {
    "id": 1,
    "name": "Black Friday",
    "status": "running"
  },
  "stats": {
    "total_contacts": 1000,
    "total_sent": 850,
    "total_delivered": 800,
    "total_read": 600,
    "total_replied": 150,
    "total_failed": 50,
    "total_skipped": 100,
    "delivery_rate": 94.12,
    "read_rate": 75.00,
    "reply_rate": 18.75,
    "failure_rate": 5.88,
    "progress": 100.00
  }
}
```

### GET `/api/campaigns`
```json
{
  "success": true,
  "campaigns": [
    {
      "id": 1,
      "name": "Black Friday",
      "status": "running",
      "progress": 85.50
    },
    {
      "id": 2,
      "name": "Natal",
      "status": "draft",
      "progress": 0
    }
  ]
}
```

---

**Pronto para adicionar as rotas e testar!** 🚀
