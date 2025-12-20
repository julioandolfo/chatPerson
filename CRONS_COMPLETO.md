# 📋 Configuração Completa de Cron Jobs

**Data de Atualização:** 2025-01-27

Este documento lista **TODOS** os cron jobs que devem ser configurados no servidor para o sistema funcionar corretamente.

---

## 🎯 Resumo Executivo

Você precisa configurar **3 cron jobs principais** no servidor:

1. ✅ **Processar Mensagens Agendadas** - A cada 1 minuto
2. ✅ **Processar Lembretes** - A cada 1 minuto  
3. ✅ **Executar Jobs Agendados** - A cada 5 minutos

Além disso, você precisa manter o **Servidor WebSocket** rodando em background (não é cron, mas processo contínuo).

---

## 📝 Cron Jobs Detalhados

### 1. Processar Mensagens Agendadas

**Script:** `public/scripts/process-scheduled-messages.php`  
**Frequência:** A cada 1 minuto  
**Função:** Processa mensagens que foram agendadas para envio futuro

**Comando Cron:**
```bash
* * * * * php /caminho/completo/para/public/scripts/process-scheduled-messages.php >> /caminho/para/storage/logs/scheduled-messages.log 2>&1
```

**Exemplo (ajuste o caminho):**
```bash
* * * * * php /home/chatperson/public_html/public/scripts/process-scheduled-messages.php >> /home/chatperson/public_html/storage/logs/scheduled-messages.log 2>&1
```

**Windows (Task Scheduler):**
- Criar tarefa agendada
- Executar: `php C:\laragon\www\chat\public\scripts\process-scheduled-messages.php`
- Repetir a cada 1 minuto

---

### 2. Processar Lembretes

**Script:** `public/scripts/process-reminders.php`  
**Frequência:** A cada 1 minuto  
**Função:** Processa lembretes pendentes e envia notificações

**Comando Cron:**
```bash
* * * * * php /caminho/completo/para/public/scripts/process-reminders.php >> /caminho/para/storage/logs/reminders.log 2>&1
```

**Exemplo (ajuste o caminho):**
```bash
* * * * * php /home/chatperson/public_html/public/scripts/process-reminders.php >> /home/chatperson/public_html/storage/logs/reminders.log 2>&1
```

**Windows (Task Scheduler):**
- Criar tarefa agendada
- Executar: `php C:\laragon\www\chat\public\scripts\process-reminders.php`
- Repetir a cada 1 minuto

---

### 3. Executar Jobs Agendados (PRINCIPAL)

**Script:** `public/run-scheduled-jobs.php`  
**Frequência:** A cada 5 minutos  
**Função:** Executa múltiplos jobs importantes do sistema

**Este script executa os seguintes jobs:**

#### 3.1. SLAMonitoringJob
- **Frequência:** A cada 5 minutos (sempre)
- **Função:** Monitora SLA de conversas e reatribui automaticamente se configurado

#### 3.2. FollowupJob
- **Frequência:** A cada hora (apenas quando minuto = 0)
- **Função:** Executa followups automáticos para conversas fechadas

#### 3.3. AICostMonitoringJob
- **Frequência:** A cada hora (apenas quando minuto = 0)
- **Função:** Monitora custos de agentes de IA e cria alertas
- **Extra:** Reseta limites mensais no dia 1 de cada mês

#### 3.4. AutomationDelayJob
- **Frequência:** A cada 5 minutos (sempre)
- **Função:** Processa delays agendados de automações
- **Extra:** Limpa delays antigos às 2h da manhã

**Comando Cron:**
```bash
*/5 * * * * php /caminho/completo/para/public/run-scheduled-jobs.php >> /caminho/para/storage/logs/jobs.log 2>&1
```

**Exemplo (ajuste o caminho):**
```bash
*/5 * * * * php /home/chatperson/public_html/public/run-scheduled-jobs.php >> /home/chatperson/public_html/storage/logs/jobs.log 2>&1
```

**Windows (Task Scheduler):**
- Criar tarefa agendada
- Executar: `php C:\laragon\www\chat\public\run-scheduled-jobs.php`
- Repetir a cada 5 minutos

---

## 🌐 Servidor WebSocket (Processo Contínuo)

**Script:** `public/websocket-server.php`  
**Tipo:** Processo contínuo (não é cron)  
**Porta:** 8080 (padrão)  
**Função:** Fornece atualizações em tempo real para o frontend

### Como Manter Rodando

#### Opção 1: Supervisor (Linux - Recomendado)

Crie arquivo `/etc/supervisor/conf.d/websocket.conf`:

```ini
[program:websocket]
command=php /caminho/para/projeto/public/websocket-server.php
directory=/caminho/para/projeto
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/websocket.log
```

Depois execute:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start websocket
```

#### Opção 2: PM2 (Node.js)

```bash
pm2 start public/websocket-server.php --name websocket --interpreter php
pm2 save
pm2 startup
```

#### Opção 3: systemd (Linux)

Crie arquivo `/etc/systemd/system/websocket.service`:

```ini
[Unit]
Description=WebSocket Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/caminho/para/projeto
ExecStart=/usr/bin/php /caminho/para/projeto/public/websocket-server.php
Restart=always

[Install]
WantedBy=multi-user.target
```

Depois execute:
```bash
sudo systemctl daemon-reload
sudo systemctl enable websocket
sudo systemctl start websocket
```

#### Opção 4: nohup (Temporário)

```bash
nohup php public/websocket-server.php > storage/logs/websocket.log 2>&1 &
```

#### Opção 5: Windows (Task Scheduler)

1. Criar tarefa agendada
2. Executar: `php C:\laragon\www\chat\public\websocket-server.php`
3. Configurar para executar "Quando o computador iniciar"
4. Configurar para reiniciar se falhar

**⚠️ Nota:** O sistema funciona sem WebSocket usando polling, mas o WebSocket oferece atualizações mais rápidas.

---

## 📋 Configuração Completa no cPanel

1. Acesse **cPanel** → **Cron Jobs**
2. Adicione os 3 comandos abaixo:

```bash
# 1. Mensagens Agendadas (a cada minuto)
* * * * * php /home/USUARIO/public_html/public/scripts/process-scheduled-messages.php >> /home/USUARIO/public_html/storage/logs/scheduled-messages.log 2>&1

# 2. Lembretes (a cada minuto)
* * * * * php /home/USUARIO/public_html/public/scripts/process-reminders.php >> /home/USUARIO/public_html/storage/logs/reminders.log 2>&1

# 3. Jobs Agendados (a cada 5 minutos)
*/5 * * * * php /home/USUARIO/public_html/public/run-scheduled-jobs.php >> /home/USUARIO/public_html/storage/logs/jobs.log 2>&1
```

**Substitua `USUARIO` pelo seu usuário do cPanel.**

---

## 📋 Configuração via SSH (Linux)

1. Acesse o servidor via SSH
2. Execute: `crontab -e`
3. Adicione as linhas abaixo:

```bash
# Mensagens Agendadas
* * * * * php /caminho/completo/para/public/scripts/process-scheduled-messages.php >> /caminho/para/storage/logs/scheduled-messages.log 2>&1

# Lembretes
* * * * * php /caminho/completo/para/public/scripts/process-reminders.php >> /caminho/para/storage/logs/reminders.log 2>&1

# Jobs Agendados
*/5 * * * * php /caminho/completo/para/public/run-scheduled-jobs.php >> /caminho/para/storage/logs/jobs.log 2>&1
```

4. Salve e saia (no vim: `:wq`, no nano: `Ctrl+X` depois `Y`)

---

## ✅ Verificar se Está Funcionando

### Verificar Logs dos Crons

```bash
# Mensagens agendadas
tail -f storage/logs/scheduled-messages.log

# Lembretes
tail -f storage/logs/reminders.log

# Jobs agendados
tail -f storage/logs/jobs.log

# WebSocket
tail -f storage/logs/websocket.log
```

### Testar Manualmente

```bash
# Testar mensagens agendadas
php public/scripts/process-scheduled-messages.php

# Testar lembretes
php public/scripts/process-reminders.php

# Testar jobs agendados
php public/run-scheduled-jobs.php

# Testar WebSocket
php public/websocket-server.php
```

### Verificar Cron Jobs Ativos

```bash
# Ver todos os crons do usuário atual
crontab -l

# Ver logs do sistema (Linux)
grep CRON /var/log/syslog
```

---

## 🔍 Troubleshooting

### Cron não está executando?

1. **Verifique permissões:**
   ```bash
   chmod +x public/scripts/process-*.php
   chmod +x public/run-scheduled-jobs.php
   ```

2. **Verifique caminho do PHP:**
   ```bash
   which php
   # Use o caminho completo no cron: /usr/bin/php ou /usr/local/bin/php
   ```

3. **Verifique logs:**
   ```bash
   tail -f storage/logs/scheduled-messages.log
   tail -f storage/logs/jobs.log
   ```

4. **Teste manualmente:**
   ```bash
   php public/scripts/process-scheduled-messages.php
   php public/run-scheduled-jobs.php
   ```

5. **Verifique variáveis de ambiente:**
   - Alguns crons podem precisar de variáveis de ambiente específicas
   - Adicione no início do script: `export PATH=/usr/local/bin:/usr/bin:$PATH`

### WebSocket não está funcionando?

1. **Verifique se está rodando:**
   ```bash
   ps aux | grep websocket-server.php
   ```

2. **Verifique porta:**
   ```bash
   netstat -tulpn | grep 8080
   ```

3. **Verifique firewall:**
   - Porta 8080 deve estar aberta
   - Ou configure proxy reverso no Nginx/Apache

4. **Verifique logs:**
   ```bash
   tail -f storage/logs/websocket.log
   ```

### Jobs não estão executando?

1. **Verifique se o cron principal está rodando:**
   ```bash
   tail -f storage/logs/jobs.log
   ```

2. **Execute manualmente com debug:**
   ```bash
   php public/run-scheduled-jobs.php?force_followup=1
   ```

3. **Verifique erros no PHP:**
   ```bash
   php -l public/run-scheduled-jobs.php
   ```

---

## 📊 Resumo dos Arquivos

### Scripts de Cron:
- ✅ `public/scripts/process-scheduled-messages.php` - Mensagens agendadas
- ✅ `public/scripts/process-reminders.php` - Lembretes
- ✅ `public/run-scheduled-jobs.php` - Jobs agendados (principal)

### Jobs Executados:
- ✅ `app/Jobs/SLAMonitoringJob.php` - Monitoramento de SLA
- ✅ `app/Jobs/FollowupJob.php` - Followups automáticos
- ✅ `app/Jobs/AICostMonitoringJob.php` - Monitoramento de custos IA
- ✅ `app/Jobs/AutomationDelayJob.php` - Delays de automações

### Processos Contínuos:
- ✅ `public/websocket-server.php` - Servidor WebSocket

### Logs Gerados:
- ✅ `storage/logs/scheduled-messages.log` - Logs de mensagens agendadas
- ✅ `storage/logs/reminders.log` - Logs de lembretes
- ✅ `storage/logs/jobs.log` - Logs de jobs agendados
- ✅ `storage/logs/websocket.log` - Logs do WebSocket

---

## 🎯 Checklist de Configuração

- [ ] Configurar cron de mensagens agendadas (1 minuto)
- [ ] Configurar cron de lembretes (1 minuto)
- [ ] Configurar cron de jobs agendados (5 minutos)
- [ ] Configurar servidor WebSocket (processo contínuo)
- [ ] Criar diretório de logs: `storage/logs/`
- [ ] Verificar permissões dos scripts
- [ ] Testar execução manual de cada script
- [ ] Verificar logs após primeira execução
- [ ] Configurar monitoramento dos processos (opcional)

---

## 📝 Notas Importantes

1. **Caminhos Absolutos:** Sempre use caminhos absolutos nos crons, não caminhos relativos.

2. **Permissões:** Certifique-se de que os scripts têm permissão de execução.

3. **PHP CLI:** Use o PHP CLI (`php`) nos crons, não o PHP-FPM.

4. **Logs:** Os logs são importantes para debug. Monitore-os regularmente.

5. **WebSocket Opcional:** O sistema funciona sem WebSocket usando polling, mas o WebSocket oferece melhor performance.

6. **Frequência:** Não altere a frequência dos crons sem entender o impacto. Alguns jobs dependem de execução frequente.

7. **Recursos:** Monitore o uso de recursos do servidor. Muitos crons podem sobrecarregar o sistema.

---

## 🆘 Suporte

Se encontrar problemas:

1. Verifique os logs primeiro
2. Teste manualmente cada script
3. Verifique permissões e caminhos
4. Consulte a documentação específica de cada funcionalidade
5. Verifique se todas as dependências estão instaladas

---

**Última atualização:** 2025-01-27

