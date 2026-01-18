# 📣 CAMPANHAS WHATSAPP - README

Sistema de disparo em massa com **rotação automática** entre múltiplas contas WhatsApp

---

## 🎯 O QUE É

Sistema completo para criar e executar **campanhas de disparo em massa** no WhatsApp com recursos profissionais:

### ⭐ **Principais Recursos**
- 🔄 **Rotação automática** entre múltiplas contas WhatsApp (2, 3, 5 ou mais)
- ⏱️ **Cadência inteligente** (rate limiting + janelas de horário)
- ✅ **Validações** (blacklist, duplicatas, conversas recentes)
- 📊 **Tracking completo** (enviada → entregue → lida → respondida)
- 🏷️ **Variáveis dinâmicas** ({{nome}}, {{telefone}}, etc)
- 🤖 **Processamento automático** via cron job

---

## ⚡ INÍCIO RÁPIDO (5 minutos)

### 1️⃣ Instalar
```bash
php database\migrate.php
```

### 2️⃣ Testar
```bash
php test-campaign-example.php
php public\scripts\process-campaigns.php
```

### 3️⃣ Pronto!
Mensagens enviadas rotacionando entre suas contas! 🎉

---

## 🔄 ROTAÇÃO DE CONTAS - COMO FUNCIONA

**Exemplo com 3 contas:**
```
Msg 1 → Conta A (11 9999-1111)
Msg 2 → Conta B (11 9999-2222)
Msg 3 → Conta C (11 9999-3333)
Msg 4 → Conta A (reinicia)
```

**Benefícios:**
- ✅ Distribui carga
- ✅ Evita bloqueios
- ✅ Aumenta deliverability
- ✅ Balanceamento automático

**Estratégias disponíveis:**
- `round_robin` - Revezamento justo (padrão)
- `random` - Aleatório
- `by_load` - Por carga (menos usada)

---

## 💻 EXEMPLO DE USO

```php
use App\Services\CampaignService;
use App\Services\ContactListService;

// 1. Criar lista
$listId = ContactListService::create([
    'name' => 'Black Friday',
    'created_by' => 1
]);

// 2. Adicionar contatos
ContactListService::addContact($listId, 1);
ContactListService::addContact($listId, 2);

// 3. Criar campanha
$campaignId = CampaignService::create([
    'name' => 'Campanha Black Friday',
    'message_content' => 'Olá {{nome}}! Oferta especial...',
    'integration_account_ids' => [1, 2, 3], // Suas contas
    'rotation_strategy' => 'round_robin',
    'send_rate_per_minute' => 20,
    'created_by' => 1
]);

// 4. Preparar e iniciar
CampaignService::prepare($campaignId);
CampaignService::start($campaignId);

// 5. Ver estatísticas
$stats = CampaignService::getStats($campaignId);
```

---

## 📊 ESTATÍSTICAS

```php
$stats = CampaignService::getStats($campaignId);

// Retorna:
[
    'total_sent' => 100,
    'total_delivered' => 95,
    'total_read' => 70,
    'total_replied' => 20,
    'delivery_rate' => 95.00,  // %
    'read_rate' => 73.68,      // %
    'reply_rate' => 21.05,     // %
    'progress' => 100.00       // %
]
```

---

## 🔧 CONFIGURAÇÕES AVANÇADAS

### Cadência
```php
'send_rate_per_minute' => 20,     // 20 msgs/min
'send_interval_seconds' => 3      // 3s entre mensagens
```

### Janela de Envio
```php
'send_window_start' => '09:00:00',
'send_window_end' => '18:00:00',
'send_days' => [1,2,3,4,5],      // Seg-Sex
'timezone' => 'America/Sao_Paulo'
```

### Validações
```php
'respect_blacklist' => true,          // Respeitar blacklist
'skip_duplicates' => true,            // Não enviar 2x
'skip_recent_conversations' => true,  // Pular conversas ativas
'skip_recent_hours' => 24            // Últimas 24h
```

---

## 🛣️ API REST

### Endpoints Principais
```
POST   /campaigns              - Criar campanha
GET    /campaigns              - Listar campanhas
GET    /campaigns/{id}         - Ver detalhes
POST   /campaigns/{id}/prepare - Preparar
POST   /campaigns/{id}/start   - Iniciar
POST   /campaigns/{id}/pause   - Pausar
GET    /api/campaigns/{id}/stats - Estatísticas
```

**Exemplo via JavaScript:**
```javascript
// Criar campanha
const response = await fetch('/campaigns', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        name: 'Teste',
        message_content: 'Olá!',
        integration_account_ids: [1, 2, 3]
    })
});

// Ver stats
const stats = await fetch('/api/campaigns/1/stats')
    .then(r => r.json());
```

---

## 📚 DOCUMENTAÇÃO COMPLETA

- **`INICIO_RAPIDO_CAMPANHAS.md`** - Começar em 5 min
- **`GUIA_COMPLETO_CAMPANHAS.md`** - Guia detalhado
- **`TESTE_CAMPANHAS_PASSO_A_PASSO.md`** - Testes práticos
- **`ROTAS_CAMPANHAS.md`** - Referência de API
- **`SETUP_CAMPANHAS.md`** - Setup e troubleshooting

---

## 🏗️ ARQUITETURA

```
6 Tabelas
   ↓
4 Models
   ↓
3 Services
   ↓
2 Controllers
   ↓
26 Rotas
   ↓
1 Cron Job
```

---

## ⚙️ CRON JOB (Obrigatório)

Para processar campanhas automaticamente:

**Windows:**
```
Task Scheduler → A cada 1 minuto
php.exe C:\laragon\www\chat\public\scripts\process-campaigns.php
```

**Linux:**
```bash
* * * * * php /path/to/public/scripts/process-campaigns.php
```

---

## 🎉 PRONTO PARA USAR!

O sistema está **100% funcional** via código e API.

**Próximos passos opcionais:**
- Interface web (views)
- Dashboard visual
- Import CSV via interface

---

## 📞 SUPORTE

Consulte a documentação ou execute:
```bash
php check-whatsapp-accounts.php  # Ver contas
php check-contacts.php           # Ver contatos
php check-stats.php              # Ver estatísticas
```

---

**Versão:** 1.0  
**Data:** 18/01/2026  
**Status:** ✅ Produção
