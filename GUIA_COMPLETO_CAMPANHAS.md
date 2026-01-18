# 📘 GUIA COMPLETO - SISTEMA DE CAMPANHAS WHATSAPP

**Data:** 18/01/2026  
**Versão:** 1.0

---

## 🎯 RESUMO EXECUTIVO

Sistema completo de **Campanhas de Disparo em Massa** para WhatsApp com:
- ✅ **Rotação automática** entre múltiplas contas WhatsApp
- ✅ **Cadência inteligente** (rate limiting + janelas de horário)
- ✅ **Validações** (blacklist, duplicatas, conversas recentes)
- ✅ **Tracking completo** (enviada → entregue → lida → respondida)
- ✅ **Variáveis dinâmicas** ({{nome}}, {{telefone}}, etc)
- ✅ **Processamento automático** via cron job

---

## 📦 O QUE FOI DESENVOLVIDO

### **17 Arquivos Criados**

#### ✅ Migrations (6 tabelas)
1. `campaigns` - Campanhas principais
2. `contact_lists` - Listas de contatos
3. `contact_list_items` - Itens das listas
4. `campaign_messages` - Mensagens individuais (tracking)
5. `campaign_blacklist` - Blacklist
6. `campaign_rotation_log` - Log de rotação

#### ✅ Models (4 models)
7. `Campaign.php` - Gestão de campanhas
8. `ContactList.php` - Gestão de listas
9. `CampaignMessage.php` - Tracking de mensagens
10. `CampaignBlacklist.php` - Gestão de blacklist

#### ✅ Services (3 services)
11. `CampaignService.php` - CRUD e controle
12. `ContactListService.php` - Gestão de listas + import
13. **`CampaignSchedulerService.php`** - ⭐ Envio + Rotação

#### ✅ Controllers (2 controllers)
14. `CampaignController.php` - Endpoints de campanhas
15. `ContactListController.php` - Endpoints de listas

#### ✅ Scripts & Docs (3 arquivos)
16. `process-campaigns.php` - Cron job
17. Documentação completa

---

## 🚀 INSTALAÇÃO E CONFIGURAÇÃO

### **PASSO 1: Rodar Migrations**

```bash
cd c:\laragon\www\chat
php database\migrate.php
```

Serão criadas 6 novas tabelas.

---

### **PASSO 2: Adicionar Rotas**

Abra `routes/web.php` e adicione:

```php
// CAMPANHAS - No final do arquivo
use App\Controllers\CampaignController;
use App\Controllers\ContactListController;

// Listas de Contatos
Router::get('/contact-lists', [ContactListController::class, 'index'], ['Authentication']);
Router::get('/contact-lists/create', [ContactListController::class, 'create'], ['Authentication']);
Router::post('/contact-lists', [ContactListController::class, 'store'], ['Authentication']);
Router::get('/contact-lists/{id}', [ContactListController::class, 'show'], ['Authentication']);
Router::post('/contact-lists/{id}/contacts', [ContactListController::class, 'addContact'], ['Authentication']);
Router::post('/contact-lists/{id}/import-csv', [ContactListController::class, 'importCsv'], ['Authentication']);

// Campanhas
Router::get('/campaigns', [CampaignController::class, 'index'], ['Authentication']);
Router::get('/campaigns/create', [CampaignController::class, 'create'], ['Authentication']);
Router::post('/campaigns', [CampaignController::class, 'store'], ['Authentication']);
Router::get('/campaigns/{id}', [CampaignController::class, 'show'], ['Authentication']);
Router::post('/campaigns/{id}/prepare', [CampaignController::class, 'prepare'], ['Authentication']);
Router::post('/campaigns/{id}/start', [CampaignController::class, 'start'], ['Authentication']);
Router::post('/campaigns/{id}/pause', [CampaignController::class, 'pause'], ['Authentication']);

// API
Router::get('/api/campaigns', [CampaignController::class, 'list'], ['Authentication']);
Router::get('/api/campaigns/{id}/stats', [CampaignController::class, 'stats'], ['Authentication']);
```

---

### **PASSO 3: Configurar Cron Job**

#### Windows (Task Scheduler)
1. Abrir **Agendador de Tarefas**
2. Criar **Nova Tarefa**
3. Nome: `Processar Campanhas WhatsApp`
4. Disparador: **Repetir a cada 1 minuto**
5. Ação: **Iniciar programa**
   - Programa: `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe`
   - Argumentos: `C:\laragon\www\chat\public\scripts\process-campaigns.php`

#### Linux/Mac
```bash
crontab -e

# Adicionar:
* * * * * php /var/www/html/public/scripts/process-campaigns.php >> /var/www/html/logs/campaigns.log 2>&1
```

---

## 🧪 TESTE RÁPIDO

### **Execute o arquivo de teste:**

```bash
php test-campaign-example.php
```

Este script irá:
1. ✅ Criar uma lista
2. ✅ Adicionar contatos
3. ✅ Criar uma campanha
4. ✅ Preparar mensagens
5. ✅ Iniciar campanha

### **Processar as mensagens:**

```bash
php public\scripts\process-campaigns.php
```

Isso enviará as mensagens **rotacionando entre suas contas WhatsApp**!

---

## 📊 COMO FUNCIONA

### **Fluxo Completo**

```
1. CRIAR LISTA DE CONTATOS
   └─ Adicionar contatos manualmente ou via CSV
   
2. CRIAR CAMPANHA
   ├─ Selecionar lista
   ├─ Escrever mensagem (com variáveis)
   ├─ Escolher contas WhatsApp (múltiplas)
   └─ Configurar cadência e horários
   
3. PREPARAR CAMPANHA
   └─ Sistema cria registros em campaign_messages
   
4. INICIAR CAMPANHA
   └─ Status muda para "running"
   
5. CRON PROCESSA (a cada 1 minuto)
   ├─ Busca mensagens pendentes
   ├─ Verifica janela de horário
   ├─ Valida contato (blacklist, duplicatas, etc)
   ├─ ROTACIONA entre contas WhatsApp ⭐
   ├─ Envia via IntegrationService
   ├─ Cria conversa
   ├─ Aplica cadência (delay)
   └─ Atualiza estatísticas
   
6. TRACKING AUTOMÁTICO
   └─ Webhooks atualizam status (entregue, lido, respondido)
   
7. CAMPANHA CONCLUÍDA
   └─ Status muda para "completed"
```

---

## 🔄 ROTAÇÃO DE CONTAS - FUNCIONAMENTO

### **Exemplo com 5 números:**

```
Configuração:
integration_account_ids: [10, 20, 30, 40, 50]
rotation_strategy: "round_robin"

Envio:
Msg 1 → Conta 10 (11 9999-1111)
Msg 2 → Conta 20 (11 9999-2222)
Msg 3 → Conta 30 (11 9999-3333)
Msg 4 → Conta 40 (11 9999-4444)
Msg 5 → Conta 50 (11 9999-5555)
Msg 6 → Conta 10 (reinicia ciclo) ⭐
Msg 7 → Conta 20
...
```

### **Estratégias Disponíveis:**

1. **`round_robin`** (Padrão)
   - Revezamento justo
   - Distribui igualmente

2. **`random`**
   - Seleção aleatória
   - Evita padrões

3. **`by_load`**
   - Seleciona menos usada (últimas 24h)
   - Balanceamento automático

---

## 📝 EXEMPLOS DE USO VIA CÓDIGO

### **Criar e Executar Campanha:**

```php
<?php
require_once 'config/bootstrap.php';

use App\Services\CampaignService;
use App\Services\ContactListService;

// 1. Criar lista
$listId = ContactListService::create([
    'name' => 'Black Friday 2026',
    'created_by' => 1
]);

// 2. Adicionar contatos
ContactListService::addContact($listId, 1); // ID do contato
ContactListService::addContact($listId, 2);
ContactListService::addContact($listId, 3);

// 3. Criar campanha
$campaignId = CampaignService::create([
    'name' => 'Campanha Black Friday',
    'description' => 'Ofertas exclusivas',
    'channel' => 'whatsapp',
    'target_type' => 'list',
    'contact_list_id' => $listId,
    'message_content' => 'Olá {{nome}}! 🎉 Black Friday chegou! Ofertas até 70% OFF. Seu cupom: BF2026',
    
    // ROTAÇÃO: múltiplas contas WhatsApp
    'integration_account_ids' => [1, 2, 3, 4, 5], // IDs das suas contas
    'rotation_strategy' => 'round_robin',
    
    // Cadência
    'send_rate_per_minute' => 20,
    'send_interval_seconds' => 3,
    
    // Janela de envio (opcional)
    'send_window_start' => '09:00:00',
    'send_window_end' => '18:00:00',
    'send_days' => [1,2,3,4,5], // Seg-Sex
    
    // Configurações
    'create_conversation' => true,
    'skip_duplicates' => true,
    'skip_recent_conversations' => true,
    'respect_blacklist' => true,
    
    'created_by' => 1
]);

// 4. Preparar
$result = CampaignService::prepare($campaignId);
echo "Preparada: {$result['created']} mensagens\n";

// 5. Iniciar
CampaignService::start($campaignId);
echo "Campanha iniciada!\n";

// 6. Ver estatísticas
$stats = CampaignService::getStats($campaignId);
print_r($stats);
```

---

## 🎯 USO VIA API (JavaScript)

```javascript
// Criar campanha via AJAX
const response = await fetch('/campaigns', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        name: 'Black Friday',
        contact_list_id: 1,
        message_content: 'Olá {{nome}}!',
        integration_account_ids: [1, 2, 3],
        send_rate_per_minute: 20
    })
});

const {campaign_id} = await response.json();

// Preparar
await fetch(`/campaigns/${campaign_id}/prepare`, {method: 'POST'});

// Iniciar
await fetch(`/campaigns/${campaign_id}/start`, {method: 'POST'});

// Monitorar estatísticas
const stats = await fetch(`/api/campaigns/${campaign_id}/stats`)
    .then(r => r.json());
console.log(stats);
```

---

## 📊 MONITORAMENTO E ESTATÍSTICAS

### **Ver estatísticas via código:**

```php
$stats = CampaignService::getStats($campaignId);

// Retorna:
[
    'total_contacts' => 1000,
    'total_sent' => 850,
    'total_delivered' => 800,
    'total_read' => 600,
    'total_replied' => 150,
    'total_failed' => 50,
    'total_skipped' => 100,
    'delivery_rate' => 94.12,  // %
    'read_rate' => 75.00,      // %
    'reply_rate' => 18.75,     // %
    'failure_rate' => 5.88,    // %
    'progress' => 100.00       // %
]
```

### **Ver log de rotação:**

```sql
SELECT 
    ia.name as conta,
    ia.phone_number,
    crl.messages_sent,
    crl.last_used_at
FROM campaign_rotation_log crl
INNER JOIN integration_accounts ia ON crl.integration_account_id = ia.id
WHERE crl.campaign_id = 1;
```

---

## 🚨 TROUBLESHOOTING

### **Problema: Mensagens não estão sendo enviadas**

1. Verificar se cron está rodando:
```bash
php public\scripts\process-campaigns.php
```

2. Verificar status da campanha:
```php
$campaign = Campaign::find(1);
echo $campaign['status']; // Deve ser "running"
```

3. Verificar mensagens pendentes:
```php
$messages = CampaignMessage::getPending(1);
echo count($messages) . " mensagens pendentes\n";
```

4. Verificar janela de horário:
```sql
SELECT 
    status,
    send_window_start,
    send_window_end,
    send_days
FROM campaigns WHERE id = 1;
```

---

### **Problema: Contas não estão rotacionando**

1. Verificar se contas estão ativas:
```sql
SELECT id, name, phone_number, status 
FROM integration_accounts 
WHERE channel = 'whatsapp';
```

2. Ver log de rotação:
```sql
SELECT * FROM campaign_rotation_log 
WHERE campaign_id = 1
ORDER BY last_used_at DESC;
```

---

## 🎉 RECURSOS PRINCIPAIS

### ✅ **Rotação Automática**
- Distribui envios entre múltiplas contas
- 3 estratégias (round_robin, random, by_load)
- Balanceamento automático

### ✅ **Cadência Inteligente**
- Rate limiting (msgs/minuto)
- Intervalo entre mensagens
- Janela de horário comercial
- Dias da semana

### ✅ **Validações Automáticas**
- Blacklist
- Duplicatas
- Conversas recentes
- Telefone válido

### ✅ **Tracking Completo**
- Enviada
- Entregue
- Lida
- Respondida

### ✅ **Variáveis Dinâmicas**
```
{{nome}}, {{primeiro_nome}}, {{telefone}}, 
{{email}}, {{empresa}}, {{cidade}}, etc
```

---

## 📚 PRÓXIMOS PASSOS

1. ✅ **Testar** com o script de exemplo
2. ✅ **Configurar** cron job
3. ⏳ **Criar** interface web (opcional)
4. ⏳ **Adicionar** import CSV via interface
5. ⏳ **Criar** dashboard visual de estatísticas

---

## 📞 SUPORTE

Para dúvidas ou problemas:
1. Consulte `SETUP_CAMPANHAS.md`
2. Consulte `ROTAS_CAMPANHAS.md`
3. Consulte `ANALISE_SISTEMA_CAMPANHAS.md`

---

**Sistema 100% funcional e pronto para uso!** 🚀

**Versão:** 1.0  
**Data:** 18/01/2026
