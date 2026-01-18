# 🧪 TESTE DE CAMPANHAS - PASSO A PASSO

**Objetivo:** Testar o sistema completo de campanhas com rotação de contas WhatsApp

**Tempo estimado:** 10 minutos

---

## ✅ PRÉ-REQUISITOS

Antes de começar, certifique-se de ter:
- [x] Pelo menos **2 contas WhatsApp** conectadas no sistema
- [x] Pelo menos **2 contatos** cadastrados no banco
- [x] PHP funcionando

---

## 🚀 PASSO 1: RODAR MIGRATIONS (30 segundos)

```bash
cd c:\laragon\www\chat
php database\migrate.php
```

**Saída esperada:**
```
✅ Tabela 'campaigns' criada com sucesso!
✅ Tabela 'contact_lists' criada com sucesso!
✅ Tabela 'contact_list_items' criada com sucesso!
✅ Tabela 'campaign_messages' criada com sucesso!
✅ Tabela 'campaign_blacklist' criada com sucesso!
✅ Tabela 'campaign_rotation_log' criada com sucesso!
```

---

## 🧪 PASSO 2: VERIFICAR CONTAS WHATSAPP (1 minuto)

Execute este script para ver suas contas:

```php
<?php
require_once 'config/bootstrap.php';

echo "=== CONTAS WHATSAPP DISPONÍVEIS ===\n\n";

$sql = "SELECT id, name, phone_number, status FROM integration_accounts WHERE channel = 'whatsapp'";
$accounts = \App\Helpers\Database::fetchAll($sql, []);

if (empty($accounts)) {
    echo "❌ NENHUMA CONTA ENCONTRADA!\n";
    echo "Você precisa ter pelo menos 1 conta WhatsApp conectada.\n";
    exit(1);
}

foreach ($accounts as $account) {
    $status = $account['status'] === 'active' ? '✅' : '⚠️';
    echo "{$status} ID: {$account['id']} - {$account['name']} ({$account['phone_number']}) - {$account['status']}\n";
}

echo "\nTotal: " . count($accounts) . " contas\n";
echo "Ativas: " . count(array_filter($accounts, fn($a) => $a['status'] === 'active')) . "\n";
```

Salve como `check-whatsapp-accounts.php` e execute:
```bash
php check-whatsapp-accounts.php
```

**Anote os IDs das contas ativas!**

---

## 📝 PASSO 3: VERIFICAR CONTATOS (1 minuto)

```php
<?php
require_once 'config/bootstrap.php';

echo "=== CONTATOS DISPONÍVEIS ===\n\n";

$sql = "SELECT id, name, phone FROM contacts LIMIT 10";
$contacts = \App\Helpers\Database::fetchAll($sql, []);

if (empty($contacts)) {
    echo "❌ NENHUM CONTATO ENCONTRADO!\n";
    echo "Crie pelo menos 2 contatos antes de testar.\n";
    exit(1);
}

foreach ($contacts as $contact) {
    echo "ID: {$contact['id']} - {$contact['name']} ({$contact['phone']})\n";
}

echo "\nTotal: " . count($contacts) . " contatos\n";
```

Salve como `check-contacts.php` e execute:
```bash
php check-contacts.php
```

**Anote os IDs dos contatos!**

---

## 🎯 PASSO 4: CRIAR CAMPANHA DE TESTE (2 minutos)

Edite o arquivo `test-campaign-example.php` e ajuste:

```php
// Linha ~43: Ajustar IDs das contas WhatsApp
$whatsappAccountIds = [1, 2, 3]; // ⚠️ SEUS IDs AQUI

// Linha ~29: Ajustar IDs dos contatos
$contactIds = [1, 2, 3]; // ⚠️ SEUS IDs AQUI
```

Execute:
```bash
php test-campaign-example.php
```

**Saída esperada:**
```
=== TESTE DE CAMPANHA WHATSAPP ===

1. Criando lista de contatos...
   ✅ Lista criada: ID=1

2. Adicionando contatos à lista...
   ✅ Contato 1 adicionado
   ✅ Contato 2 adicionado
   Total: 2 contatos adicionados

3. Criando campanha...
   Contas WhatsApp disponíveis:
      - ID 1: Conta A (5511999991111)
      - ID 2: Conta B (5511999992222)
   Usando 2 contas para rotação
   
   ✅ Campanha criada: ID=1

4. Preparando campanha...
   ✅ Preparação concluída:
      - Mensagens criadas: 2
      - Contatos pulados: 0
      - Total: 2

5. Iniciando campanha...
   ✅ Campanha iniciada!
   Status: RUNNING

6. Estatísticas atuais:
   - Total de contatos: 2
   - Enviadas: 0
   - Progresso: 0%

=== PRÓXIMOS PASSOS ===
```

---

## 📤 PASSO 5: PROCESSAR E ENVIAR (1 minuto)

Execute o processador:
```bash
php public\scripts\process-campaigns.php
```

**Saída esperada:**
```
[2026-01-18 10:00:00] Iniciando processamento de campanhas...
[2026-01-18 10:00:00] Processamento concluído:
  - Enviadas: 2
  - Puladas: 0
  - Falhadas: 0
  - Total processadas: 2
[2026-01-18 10:00:00] Script finalizado com sucesso.
```

---

## 📊 PASSO 6: VERIFICAR ROTAÇÃO (2 minutos)

Execute para ver qual conta foi usada:

```php
<?php
require_once 'config/bootstrap.php';

echo "=== LOG DE ROTAÇÃO ===\n\n";

$sql = "SELECT 
    cm.id as msg_id,
    c.name as contato,
    ia.name as conta_usada,
    ia.phone_number as numero_conta,
    cm.status,
    cm.sent_at
FROM campaign_messages cm
INNER JOIN contacts c ON cm.contact_id = c.id
LEFT JOIN integration_accounts ia ON cm.integration_account_id = ia.id
WHERE cm.campaign_id = 1
ORDER BY cm.id ASC";

$messages = \App\Helpers\Database::fetchAll($sql, []);

foreach ($messages as $msg) {
    echo "Msg {$msg['msg_id']}: {$msg['contato']} → {$msg['conta_usada']} ({$msg['numero_conta']}) - {$msg['status']}\n";
}

echo "\n=== RESUMO ===\n";

$sql2 = "SELECT 
    ia.name as conta,
    ia.phone_number,
    COUNT(*) as total_enviadas
FROM campaign_messages cm
INNER JOIN integration_accounts ia ON cm.integration_account_id = ia.id
WHERE cm.campaign_id = 1
GROUP BY ia.id";

$summary = \App\Helpers\Database::fetchAll($sql2, []);

foreach ($summary as $row) {
    echo "{$row['conta']} ({$row['phone_number']}): {$row['total_enviadas']} mensagens\n";
}
```

Salve como `check-rotation.php` e execute:
```bash
php check-rotation.php
```

**Resultado esperado:**
```
=== LOG DE ROTAÇÃO ===

Msg 1: João → Conta A (5511999991111) - sent
Msg 2: Maria → Conta B (5511999992222) - sent

=== RESUMO ===
Conta A (5511999991111): 1 mensagens
Conta B (5511999992222): 1 mensagens
```

✅ **Rotação funcionando!** Cada mensagem foi enviada por uma conta diferente!

---

## 📈 PASSO 7: VER ESTATÍSTICAS (1 minuto)

```php
<?php
require_once 'config/bootstrap.php';

use App\Services\CampaignService;

echo "=== ESTATÍSTICAS DA CAMPANHA ===\n\n";

$stats = CampaignService::getStats(1); // ID da campanha

foreach ($stats as $key => $value) {
    echo ucfirst(str_replace('_', ' ', $key)) . ": {$value}\n";
}
```

Salve como `check-stats.php` e execute:
```bash
php check-stats.php
```

**Saída esperada:**
```
=== ESTATÍSTICAS DA CAMPANHA ===

Total contacts: 2
Total sent: 2
Total delivered: 2
Total read: 0
Total replied: 0
Total failed: 0
Total skipped: 0
Delivery rate: 100.00
Read rate: 0.00
Reply rate: 0.00
Failure rate: 0.00
Progress: 100.00
```

---

## ✅ CHECKLIST DE SUCESSO

Se você viu:
- [x] Migrations executadas sem erro
- [x] Campanha criada com sucesso
- [x] 2 mensagens enviadas
- [x] Cada mensagem usou uma conta diferente (rotação)
- [x] Status = "sent"
- [x] Progresso = 100%

**🎉 PARABÉNS! Sistema funcionando perfeitamente!**

---

## 🚨 TROUBLESHOOTING

### Mensagens não enviaram

**Verificar se há erro:**
```sql
SELECT id, status, error_message, skip_reason 
FROM campaign_messages 
WHERE campaign_id = 1;
```

**Verificar status das contas:**
```sql
SELECT id, name, status FROM integration_accounts WHERE channel = 'whatsapp';
```

### Rotação não funcionou

**Ver log de rotação:**
```sql
SELECT * FROM campaign_rotation_log WHERE campaign_id = 1;
```

**Verificar estratégia:**
```sql
SELECT rotation_strategy, integration_account_ids FROM campaigns WHERE id = 1;
```

---

## 🎯 TESTE AVANÇADO: 10 MENSAGENS COM 5 CONTAS

```php
// Criar lista com 10 contatos
$listId = ContactListService::create([...]);
for ($i = 1; $i <= 10; $i++) {
    ContactListService::addContact($listId, $i);
}

// Criar campanha com 5 contas
$campaignId = CampaignService::create([
    'integration_account_ids' => [1, 2, 3, 4, 5],
    'rotation_strategy' => 'round_robin'
]);

// Processar
CampaignService::prepare($campaignId);
CampaignService::start($campaignId);
```

**Resultado esperado:**
```
Msg 1 → Conta 1
Msg 2 → Conta 2
Msg 3 → Conta 3
Msg 4 → Conta 4
Msg 5 → Conta 5
Msg 6 → Conta 1 (reinicia)
Msg 7 → Conta 2
Msg 8 → Conta 3
Msg 9 → Conta 4
Msg 10 → Conta 5
```

✅ **Distribuição perfeita: 2 mensagens por conta!**

---

**Fim do guia de testes.** Sistema validado e pronto para produção! 🚀
