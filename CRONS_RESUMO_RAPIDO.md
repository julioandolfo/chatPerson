# ⚡ Resumo Rápido - Crons Necessários

## 🎯 3 Crons Principais

### 1️⃣ Mensagens Agendadas (1 minuto)
```bash
* * * * * php /CAMINHO/public/scripts/process-scheduled-messages.php >> /CAMINHO/storage/logs/scheduled-messages.log 2>&1
```

### 2️⃣ Lembretes (1 minuto)
```bash
* * * * * php /CAMINHO/public/scripts/process-reminders.php >> /CAMINHO/storage/logs/reminders.log 2>&1
```

### 3️⃣ Jobs Agendados (5 minutos) ⭐ PRINCIPAL
```bash
*/5 * * * * php /CAMINHO/public/run-scheduled-jobs.php >> /CAMINHO/storage/logs/jobs.log 2>&1
```

**Este cron executa:**
- ✅ Monitoramento de SLA (a cada 5 min)
- ✅ Followups automáticos (a cada hora)
- ✅ Monitoramento de custos IA (a cada hora)
- ✅ Delays de automações (a cada 5 min)

---

## 🌐 WebSocket (Processo Contínuo)

**Não é cron!** Precisa rodar em background:

```bash
# Opção 1: Supervisor (Linux)
sudo supervisorctl start websocket

# Opção 2: PM2
pm2 start public/websocket-server.php --name websocket --interpreter php

# Opção 3: nohup (temporário)
nohup php public/websocket-server.php > storage/logs/websocket.log 2>&1 &
```

---

## ✅ Verificar Configuração

Execute o script de verificação:

```bash
php public/check-crons.php
```

---

## 📖 Documentação Completa

Consulte `CRONS_COMPLETO.md` para detalhes completos.

