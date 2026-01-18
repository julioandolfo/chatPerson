# 🚀 SETUP DE CAMPANHAS - GUIA RÁPIDO

**Data:** 18/01/2026

Este guia mostra como configurar e usar o novo módulo de Campanhas.

---

## 📋 PASSO 1: RODAR MIGRATIONS

Execute as migrations para criar as tabelas:

```bash
cd c:\laragon\www\chat
php database/migrate.php
```

Serão criadas 6 novas tabelas:
- ✅ `campaigns` - Campanhas principais
- ✅ `contact_lists` - Listas de contatos
- ✅ `contact_list_items` - Itens das listas
- ✅ `campaign_messages` - Mensagens individuais
- ✅ `campaign_blacklist` - Blacklist
- ✅ `campaign_rotation_log` - Log de rotação

---

## ⏰ PASSO 2: CONFIGURAR CRON JOB

### Windows (Task Scheduler)

1. Abrir **Agendador de Tarefas**
2. Criar **Nova Tarefa Básica**
3. Nome: `Processar Campanhas`
4. Disparador: **Diariamente**
5. Ação: **Iniciar um programa**
   - Programa: `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe`
   - Argumentos: `C:\laragon\www\chat\public\scripts\process-campaigns.php`
6. Em **Disparadores**, editar e marcar:
   - ✅ **Repetir tarefa a cada: 1 minuto**
   - ✅ **Por duração de: Indefinidamente**

### Linux/Mac

```bash
# Editar crontab
crontab -e

# Adicionar linha:
* * * * * php /var/www/html/public/scripts/process-campaigns.php >> /var/www/html/logs/campaigns.log 2>&1
```

---

## 🧪 PASSO 3: TESTAR PROCESSAMENTO MANUAL

Antes de configurar o cron, teste manualmente:

```bash
php public/scripts/process-campaigns.php
```

Saída esperada:
```
[2026-01-18 10:00:00] Iniciando processamento de campanhas...
[2026-01-18 10:00:00] Processamento concluído:
  - Enviadas: 0
  - Puladas: 0
  - Falhadas: 0
  - Total processadas: 0
[2026-01-18 10:00:00] Script finalizado com sucesso.
```

---

## 📝 PASSO 4: CRIAR PRIMEIRA CAMPANHA (Manual via PHP)

Crie um arquivo de teste `test-campaign.php`:

```php
<?php
require_once 'config/bootstrap.php';

use App\Services\CampaignService;
use App\Services\ContactListService;

// 1. Criar lista de contatos
$listId = ContactListService::create([
    'name' => 'Teste Campanha',
    'description' => 'Lista de teste',
    'created_by' => 1
]);

echo "Lista criada: ID={$listId}\n";

// 2. Adicionar contatos (exemplo)
ContactListService::addContact($listId, 1); // Substitua pelo ID de um contato real
ContactListService::addContact($listId, 2);

// 3. Criar campanha
$campaignId = CampaignService::create([
    'name' => 'Campanha Teste WhatsApp',
    'description' => 'Teste de envio em massa',
    'channel' => 'whatsapp',
    'target_type' => 'list',
    'contact_list_id' => $listId,
    'message_content' => 'Olá {{nome}}! Esta é uma mensagem de teste.',
    'integration_account_ids' => [1, 2], // Substitua pelos IDs das suas contas WhatsApp
    'rotation_strategy' => 'round_robin',
    'send_rate_per_minute' => 10,
    'send_interval_seconds' => 6,
    'send_window_start' => '09:00:00',
    'send_window_end' => '18:00:00',
    'send_days' => [1,2,3,4,5], // Segunda a Sexta
    'create_conversation' => true,
    'created_by' => 1
]);

echo "Campanha criada: ID={$campaignId}\n";

// 4. Preparar campanha (criar registros de mensagens)
$result = CampaignService::prepare($campaignId);
echo "Preparação concluída: {$result['created']} mensagens criadas\n";

// 5. Iniciar campanha
CampaignService::start($campaignId);
echo "Campanha iniciada!\n";
```

Execute:
```bash
php test-campaign.php
```

---

## 🔍 VERIFICAR STATUS

### Ver campanhas ativas

```php
<?php
require_once 'config/bootstrap.php';

$campaigns = \App\Models\Campaign::getActive();
foreach ($campaigns as $campaign) {
    echo "ID: {$campaign['id']} - {$campaign['name']} - Status: {$campaign['status']}\n";
    
    $stats = \App\Services\CampaignService::getStats($campaign['id']);
    print_r($stats);
}
```

### Ver mensagens pendentes

```php
<?php
require_once 'config/bootstrap.php';

$messages = \App\Models\CampaignMessage::getPending(1); // ID da campanha
foreach ($messages as $msg) {
    echo "ID: {$msg['id']} - Contato: {$msg['contact_name']} - Status: {$msg['status']}\n";
}
```

---

## 🎯 FLUXO COMPLETO DE USO

```
1. Criar Lista de Contatos
   ↓
2. Adicionar Contatos à Lista
   ↓
3. Criar Campanha
   - Selecionar lista
   - Escrever mensagem
   - Escolher contas WhatsApp (múltiplas)
   - Configurar cadência
   ↓
4. Preparar Campanha
   - Sistema cria registros de campaign_messages
   ↓
5. Iniciar Campanha
   - Status muda para "running"
   ↓
6. Cron Job Processa (a cada 1 minuto)
   - Busca mensagens pendentes
   - Verifica janela de horário
   - Valida contatos (blacklist, duplicatas, etc)
   - ROTACIONA entre contas WhatsApp
   - Envia mensagem via IntegrationService
   - Cria conversa (se configurado)
   - Atualiza estatísticas
   ↓
7. Tracking Automático
   - Webhooks atualizam status (entregue, lido, respondido)
   ↓
8. Campanha Concluída
   - Status muda para "completed"
```

---

## 🔄 ROTAÇÃO DE CONTAS - COMO FUNCIONA

### Exemplo com 3 contas WhatsApp:

**Configuração:**
```php
'integration_account_ids' => [10, 20, 30], // IDs das contas
'rotation_strategy' => 'round_robin'
```

**Envio:**
```
Mensagem 1 → Conta 10
Mensagem 2 → Conta 20
Mensagem 3 → Conta 30
Mensagem 4 → Conta 10 (volta ao início)
Mensagem 5 → Conta 20
...
```

### Estratégias Disponíveis:

1. **round_robin** (Padrão) - Revezamento justo
   - Distribui igualmente entre todas as contas
   - Recomendado para a maioria dos casos

2. **random** - Aleatório
   - Seleciona conta aleatória a cada envio
   - Útil para evitar padrões

3. **by_load** - Por carga
   - Seleciona conta com menor uso nas últimas 24h
   - Balanceia automaticamente a carga

---

## 📊 ESTATÍSTICAS

### Via PHP:

```php
$stats = \App\Services\CampaignService::getStats($campaignId);

// Retorna:
[
    'total_contacts' => 100,
    'total_sent' => 85,
    'total_delivered' => 80,
    'total_read' => 60,
    'total_replied' => 15,
    'total_failed' => 5,
    'total_skipped' => 10,
    'delivery_rate' => 94.12,  // %
    'read_rate' => 75.00,      // %
    'reply_rate' => 18.75,     // %
    'failure_rate' => 5.88,    // %
    'progress' => 100.00       // %
]
```

---

## ⚙️ CONFIGURAÇÕES AVANÇADAS

### Janela de Envio

```php
'send_window_start' => '09:00:00',  // Das 9h
'send_window_end' => '18:00:00',    // Até 18h
'send_days' => [1,2,3,4,5],        // Seg-Sex (1=Seg, 7=Dom)
'timezone' => 'America/Sao_Paulo'
```

### Cadência

```php
'send_rate_per_minute' => 20,      // 20 mensagens por minuto
'send_interval_seconds' => 3       // 3 segundos entre cada mensagem
```

### Validações

```php
'respect_blacklist' => true,           // Respeitar blacklist
'skip_duplicates' => true,             // Não enviar 2x para mesmo contato
'skip_recent_conversations' => true,   // Pular se tem conversa ativa
'skip_recent_hours' => 24             // Considerar últimas 24h
```

---

## 🚨 TROUBLESHOOTING

### Campanhas não estão enviando

1. Verificar se cron está rodando:
```bash
php public/scripts/process-campaigns.php
```

2. Verificar se há mensagens pendentes:
```php
$messages = \App\Models\CampaignMessage::getPending($campaignId);
echo count($messages) . " mensagens pendentes\n";
```

3. Verificar janela de horário:
```php
$canSend = \App\Services\CampaignSchedulerService::canSendNow($campaignId);
echo $canSend ? "Pode enviar agora\n" : "Fora da janela\n";
```

4. Ver logs:
```bash
tail -f logs/app.log
```

### Conta não está rotacionando

1. Verificar se contas estão ativas:
```php
$accountIds = json_decode($campaign['integration_account_ids'], true);
foreach ($accountIds as $id) {
    $account = \App\Models\IntegrationAccount::find($id);
    echo "Conta {$id}: " . ($account['status'] ?? 'não encontrada') . "\n";
}
```

2. Ver log de rotação:
```sql
SELECT * FROM campaign_rotation_log WHERE campaign_id = 1;
```

---

## 🎉 PRÓXIMOS PASSOS

1. ✅ **Testar envio manual** (completado)
2. ✅ **Configurar cron** (necessário)
3. ⏳ **Criar interface web** (próxima fase)
4. ⏳ **Import CSV** (próxima fase)
5. ⏳ **Relatórios visuais** (próxima fase)

---

**Pronto!** Sistema de Campanhas está funcional via código. Interface web será criada em seguida.
