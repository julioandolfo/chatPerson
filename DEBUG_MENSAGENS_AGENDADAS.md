# 🐛 Debug de Mensagens Agendadas

Guia completo para debugar problemas com mensagens agendadas que não são disparadas.

---

## 🔍 Verificação Rápida

### 1. **Página de Debug Web**
Acesse: **`http://seu-dominio.com/debug-scheduled-messages.php`**

Esta página mostra:
- ✅ Estatísticas gerais
- ⏰ Mensagens atrasadas
- 📨 Últimas mensagens
- 📁 Status do script de processamento
- 📝 Status dos logs
- 🔍 Diagnóstico automático

**Botão "Processar Agora"**: Executa o processamento manualmente para testar.

---

### 2. **Verificação via Terminal**
```bash
cd /caminho/para/projeto
php public/check-crons.php
```

Este script verifica:
- Scripts existem?
- Logs foram atualizados recentemente?
- Conexão com banco de dados OK?
- Há mensagens pendentes atrasadas?
- WebSocket está rodando?

---

## 🚨 Principais Causas de Mensagens Não Disparadas

### ❌ **CAUSA #1: Cron Job NÃO está configurado**
**Sintoma**: Mensagens ficam com status "pending" mesmo depois da hora agendada.

**Solução**: Configurar cron job

#### Linux/Mac:
```bash
# Editar crontab
crontab -e

# Adicionar linha (ajuste o caminho para seu servidor):
* * * * * php /var/www/html/public/scripts/process-scheduled-messages.php >> /var/www/html/logs/scheduled-messages.log 2>&1

# Ou no Laragon/XAMPP Windows:
* * * * * php C:\laragon\www\chat\public\scripts\process-scheduled-messages.php >> C:\laragon\www\chat\logs\scheduled-messages.log 2>&1
```

#### Windows (Task Scheduler):
1. Abrir **Agendador de Tarefas**
2. Criar **Nova Tarefa**
3. **Disparadores**: A cada 1 minuto
4. **Ações**: `php.exe C:\laragon\www\chat\public\scripts\process-scheduled-messages.php`

#### Docker/Supervisor:
```ini
[program:scheduled-messages]
command=php /var/www/html/public/scripts/process-scheduled-messages.php
autostart=true
autorestart=true
stdout_logfile=/var/www/html/logs/scheduled-messages.log
stderr_logfile=/var/www/html/logs/scheduled-messages-error.log
```

---

### ❌ **CAUSA #2: Script com Erro**
**Sintoma**: Cron está configurado mas mensagens não são enviadas.

**Diagnóstico**:
```bash
# Executar script manualmente
php public/scripts/process-scheduled-messages.php

# Se houver erro, ele aparecerá aqui
```

**Verificar logs**:
```bash
tail -f logs/app.log
tail -f logs/scheduled-messages.log
```

---

### ❌ **CAUSA #3: Permissões Incorretas**
**Sintoma**: Cron roda mas não consegue acessar arquivos.

**Solução**:
```bash
# Linux/Mac
chmod -R 755 public/scripts/
chmod -R 777 logs/

# Verificar proprietário
chown -R www-data:www-data /var/www/html/
# ou
chown -R seu-usuario:seu-grupo /var/www/html/

# Windows (não necessário, mas garanta que o usuário tem permissão de escrita na pasta logs/)
```

---

### ❌ **CAUSA #4: Caminho do PHP Incorreto**
**Sintoma**: Cron não encontra o PHP.

**Encontrar caminho do PHP**:
```bash
which php
# Resultado: /usr/bin/php
```

**Usar caminho completo no cron**:
```bash
* * * * * /usr/bin/php /var/www/html/public/scripts/process-scheduled-messages.php
```

---

### ❌ **CAUSA #5: Mensagem Cancelada Automaticamente**
**Sintoma**: Mensagem some da lista de pendentes mas não foi enviada.

**Motivos**:
- ✅ Opção **"Cancelar se conversa foi resolvida"** estava marcada e a conversa foi resolvida
- ✅ Opção **"Cancelar se já foi respondida"** estava marcada e o contato respondeu

**Verificar**:
```sql
SELECT * FROM scheduled_messages 
WHERE status = 'cancelled' 
ORDER BY updated_at DESC 
LIMIT 10;
```

---

### ❌ **CAUSA #6: Erro no Envio da Mensagem**
**Sintoma**: Status muda para "failed".

**Verificar mensagem de erro**:
```sql
SELECT id, conversation_id, status, error_message, scheduled_at 
FROM scheduled_messages 
WHERE status = 'failed' 
ORDER BY updated_at DESC 
LIMIT 10;
```

**Causas comuns**:
- Canal WhatsApp desconectado
- Número bloqueado
- Erro de API
- Conteúdo inválido

---

## 📊 Queries SQL Úteis

### Ver todas as mensagens pendentes atrasadas:
```sql
SELECT 
    sm.id,
    sm.scheduled_at,
    TIMESTAMPDIFF(MINUTE, sm.scheduled_at, NOW()) as minutes_late,
    ct.name as contact_name,
    ct.phone,
    u.name as user_name,
    sm.content
FROM scheduled_messages sm
LEFT JOIN conversations c ON sm.conversation_id = c.id
LEFT JOIN contacts ct ON c.contact_id = ct.id
LEFT JOIN users u ON sm.user_id = u.id
WHERE sm.status = 'pending' 
AND sm.scheduled_at <= NOW()
ORDER BY sm.scheduled_at ASC;
```

### Ver estatísticas por status:
```sql
SELECT 
    status,
    COUNT(*) as total,
    MIN(scheduled_at) as primeira,
    MAX(scheduled_at) as ultima
FROM scheduled_messages
GROUP BY status;
```

### Ver mensagens falhadas hoje com erro:
```sql
SELECT 
    id,
    conversation_id,
    scheduled_at,
    error_message,
    updated_at
FROM scheduled_messages
WHERE status = 'failed'
AND DATE(updated_at) = CURDATE()
ORDER BY updated_at DESC;
```

### Ver taxa de sucesso:
```sql
SELECT 
    DATE(scheduled_at) as data,
    COUNT(*) as total,
    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as enviadas,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as falhas,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as canceladas,
    ROUND(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as taxa_sucesso
FROM scheduled_messages
WHERE scheduled_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY DATE(scheduled_at)
ORDER BY data DESC;
```

---

## 🧪 Testes Manuais

### 1. **Testar Processamento Manual**
```bash
# Executar script manualmente
php public/scripts/process-scheduled-messages.php

# Saída esperada:
# [2025-12-21 17:00:00] Iniciando processamento de mensagens agendadas...
# [2025-12-21 17:00:01] Processamento concluído:
#   - Enviadas: 5
#   - Canceladas: 0
#   - Falhadas: 0
#   - Total processadas: 5
# [2025-12-21 17:00:01] Script finalizado com sucesso.
```

### 2. **Agendar Mensagem de Teste**
```php
// Criar mensagem agendada para daqui a 2 minutos
$conversationId = 1; // ID de uma conversa existente
$userId = 1; // Seu ID de usuário

$scheduledAt = date('Y-m-d H:i:s', strtotime('+2 minutes'));

$messageId = \App\Services\ScheduledMessageService::schedule(
    $conversationId,
    $userId,
    'Mensagem de teste agendada',
    $scheduledAt,
    [], // sem anexos
    false, // não cancelar se resolvida
    false  // não cancelar se respondida
);

echo "Mensagem agendada: ID = {$messageId}, Horário = {$scheduledAt}\n";
```

### 3. **Verificar Logs em Tempo Real**
```bash
# Terminal 1: Executar cron manualmente em loop
while true; do 
    php public/scripts/process-scheduled-messages.php
    sleep 60
done

# Terminal 2: Acompanhar logs
tail -f logs/scheduled-messages.log logs/app.log
```

---

## 📱 Verificar no Sistema

### Via Interface Web:

1. **Agendar uma mensagem**:
   - Abrir conversa
   - Clicar no botão de "Agendar mensagem" (calendário amarelo)
   - Agendar para daqui a 2 minutos

2. **Ver mensagens agendadas**:
   - Clicar no botão verde de calendário
   - Ver lista de mensagens agendadas
   - Status deve aparecer como "Pendente"

3. **Aguardar 2 minutos**

4. **Verificar se foi enviada**:
   - Atualizar lista de mensagens agendadas
   - Status deve mudar para "Enviada"
   - Mensagem deve aparecer na conversa

---

## 🔧 Troubleshooting Avançado

### Verificar se cron está rodando (Linux):
```bash
# Ver logs do cron
sudo tail -f /var/log/cron
# ou
sudo tail -f /var/log/syslog | grep CRON
```

### Verificar processos PHP rodando:
```bash
ps aux | grep php
```

### Testar conexão com banco:
```bash
php -r "require 'config/bootstrap.php'; echo 'DB OK: ' . \App\Helpers\Database::getInstance()->query('SELECT NOW()')->fetchColumn();"
```

### Verificar se há deadlocks no MySQL:
```sql
SHOW ENGINE INNODB STATUS\G
```

### Limpar mensagens pendentes antigas (CUIDADO):
```sql
-- Ver quantas mensagens antigas pendentes existem
SELECT COUNT(*) FROM scheduled_messages 
WHERE status = 'pending' 
AND scheduled_at < DATE_SUB(NOW(), INTERVAL 1 DAY);

-- Se quiser cancelá-las:
UPDATE scheduled_messages 
SET status = 'cancelled', 
    error_message = 'Cancelada automaticamente (muito antiga)'
WHERE status = 'pending' 
AND scheduled_at < DATE_SUB(NOW(), INTERVAL 1 DAY);
```

---

## 📋 Checklist de Debug

- [ ] Acessar `/debug-scheduled-messages.php`
- [ ] Verificar se há mensagens atrasadas
- [ ] Verificar se o script existe em `public/scripts/process-scheduled-messages.php`
- [ ] Executar script manualmente: `php public/scripts/process-scheduled-messages.php`
- [ ] Verificar se cron está configurado: `crontab -l`
- [ ] Verificar logs: `tail -f logs/scheduled-messages.log`
- [ ] Verificar permissões: `ls -la public/scripts/`
- [ ] Verificar caminho do PHP: `which php`
- [ ] Testar com mensagem de teste (agendar para +2 minutos)
- [ ] Verificar status no banco: `SELECT * FROM scheduled_messages WHERE id = X`

---

## 🆘 Suporte

Se ainda não funcionar após seguir este guia:

1. ✅ Execute: `php public/check-crons.php` e copie a saída
2. ✅ Acesse `/debug-scheduled-messages.php` e tire um print
3. ✅ Verifique logs: `cat logs/app.log | grep -i "scheduled"`
4. ✅ Execute manualmente: `php public/scripts/process-scheduled-messages.php`
5. ✅ Consulte a documentação: `CRONS_COMPLETO.md`

---

## 📚 Arquivos Relacionados

- **Service**: `app/Services/ScheduledMessageService.php`
- **Model**: `app/Models/ScheduledMessage.php`
- **Controller**: `app/Controllers/ConversationController.php` (métodos `scheduleMessage`, `getScheduledMessages`, `cancelScheduledMessage`)
- **Script de Processamento**: `public/scripts/process-scheduled-messages.php`
- **Migration**: `database/migrations/049_create_scheduled_messages_table.php`
- **View**: `views/conversations/index.php` (modais e JavaScript)
- **Debug Web**: `public/debug-scheduled-messages.php` ⭐

---

**💡 Dica**: Sempre que agendar uma mensagem, use a página de debug para acompanhar o processamento em tempo real!

