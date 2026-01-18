# ⚡ INÍCIO RÁPIDO - CAMPANHAS WHATSAPP

**Tempo estimado:** 5 minutos para primeiro teste

---

## 🎯 3 PASSOS PARA COMEÇAR

### **PASSO 1: Rodar Migrations** (30 segundos)

```bash
cd c:\laragon\www\chat
php database\migrate.php
```

✅ Criará 6 tabelas necessárias

---

### **PASSO 2: Testar Envio** (2 minutos)

Execute o script de teste:

```bash
php test-campaign-example.php
```

Este script:
1. ✅ Cria uma lista de contatos
2. ✅ Adiciona contatos à lista
3. ✅ Cria uma campanha
4. ✅ Prepara mensagens
5. ✅ Inicia a campanha

---

### **PASSO 3: Processar Mensagens** (1 minuto)

```bash
php public\scripts\process-campaigns.php
```

**Pronto!** As mensagens serão enviadas **rotacionando** entre suas contas WhatsApp! 🎉

---

## 🔥 EXEMPLO RÁPIDO VIA CÓDIGO

```php
<?php
require_once 'config/bootstrap.php';

use App\Services\CampaignService;
use App\Services\ContactListService;

// 1. Criar lista
$listId = ContactListService::create([
    'name' => 'Minha Lista',
    'created_by' => 1
]);

// 2. Adicionar contatos
ContactListService::addContact($listId, 1); // IDs dos seus contatos
ContactListService::addContact($listId, 2);

// 3. Criar campanha
$campaignId = CampaignService::create([
    'name' => 'Black Friday',
    'message_content' => 'Olá {{nome}}! Oferta especial...',
    'integration_account_ids' => [1, 2, 3], // Suas contas WhatsApp
    'created_by' => 1
]);

// 4. Preparar e iniciar
CampaignService::prepare($campaignId);
CampaignService::start($campaignId);

// 5. Ver estatísticas
$stats = CampaignService::getStats($campaignId);
print_r($stats);
```

---

## 🔄 ROTAÇÃO ENTRE 5 CONTAS - EXEMPLO REAL

```php
// Suas 5 contas WhatsApp
$campaignId = CampaignService::create([
    'name' => 'Campanha Teste',
    'message_content' => 'Olá!',
    'integration_account_ids' => [10, 20, 30, 40, 50], // IDs das contas
    'rotation_strategy' => 'round_robin',
    'created_by' => 1
]);
```

**Resultado do envio:**
```
Msg 1 → Conta 10 (11 9999-1111)
Msg 2 → Conta 20 (11 9999-2222)
Msg 3 → Conta 30 (11 9999-3333)
Msg 4 → Conta 40 (11 9999-4444)
Msg 5 → Conta 50 (11 9999-5555)
Msg 6 → Conta 10 (reinicia) ⭐
```

---

## 📊 VER ESTATÍSTICAS

```php
$stats = CampaignService::getStats($campaignId);

// Retorna:
[
    'total_sent' => 85,
    'total_delivered' => 80,
    'total_read' => 60,
    'total_replied' => 15,
    'delivery_rate' => 94.12,  // %
    'read_rate' => 75.00,      // %
    'reply_rate' => 18.75,     // %
    'progress' => 100.00       // %
]
```

---

## ⚙️ CONFIGURAR CRON (Automático)

### Windows Task Scheduler:
```
Programa: C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe
Argumentos: C:\laragon\www\chat\public\scripts\process-campaigns.php
Repetir: A cada 1 minuto
```

### Linux:
```bash
* * * * * php /path/to/public/scripts/process-campaigns.php
```

---

## 🎉 PRONTO!

Sistema 100% funcional. Próximos passos opcionais:
- ⏳ Interface web (views)
- ⏳ Import CSV via interface
- ⏳ Dashboard visual

**Mas você já pode usar tudo via código!** 🚀

---

**Documentação completa:** `GUIA_COMPLETO_CAMPANHAS.md`
