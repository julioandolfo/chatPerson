# 🔄 Guia: CRON vs Worker para Métricas de Contatos

## 📊 Duas Opções Disponíveis

Você tem **2 formas** de executar o cálculo de métricas:

### 1️⃣ CRON Job (Recomendado para Maioria)
```bash
cron/calculate-contact-metrics.php
```
- ✅ Executa 1x e para
- ✅ Ideal para crontab
- ✅ Menor consumo de recursos
- ✅ Mais simples de gerenciar
- ⏰ Roda a cada X minutos

### 2️⃣ Worker Contínuo (Recomendado para Alto Volume)
```bash
cron/contact-metrics-worker.php
```
- ✅ Loop infinito (daemon)
- ✅ Processa continuamente
- ✅ Menor latência (mais tempo real)
- ✅ Ideal para Supervisor/systemd
- ⏰ Roda 24/7

---

## 🎯 Quando Usar Cada Um?

### Use CRON se:
- ✅ Tem menos de 1000 contatos ativos
- ✅ Não precisa de dados super atualizados
- ✅ Quer simplicidade (apenas adicionar ao crontab)
- ✅ Quer economizar recursos

### Use Worker se:
- ✅ Tem mais de 1000 contatos ativos
- ✅ Precisa de dados quase em tempo real
- ✅ Tem Supervisor ou systemd configurado
- ✅ Quer processar continuamente

---

## 🚀 Opção 1: CRON Job (Standalone)

### Instalação

#### Passo 1: Dar permissão de execução
```bash
chmod +x cron/calculate-contact-metrics.php
```

#### Passo 2: Testar manualmente
```bash
php cron/calculate-contact-metrics.php
```

Saída esperada:
```
═══════════════════════════════════════════════════════════════
🚀 CRON: Calculando métricas de contatos (Standalone)
═══════════════════════════════════════════════════════════════
📁 Root Dir: /var/www/html
⏰ Início: 2026-01-12 10:30:00
📊 Lote: 100 contatos

───────────────────────────────────────────────────────────────
✅ RESULTADO
───────────────────────────────────────────────────────────────
Processados: 45
Erros: 0
Pulados: 0

Tempo: 12.35s
Memória: 15.23MB

Média: 0.274s por contato
───────────────────────────────────────────────────────────────
```

#### Passo 3: Adicionar ao crontab

```bash
# Editar crontab
crontab -e

# Adicionar uma das linhas abaixo:

# A cada 30 minutos (Recomendado)
*/30 * * * * cd /var/www/html && php cron/calculate-contact-metrics.php >> logs/cron-metrics.log 2>&1

# A cada 15 minutos (Se precisar dados mais atualizados)
*/15 * * * * cd /var/www/html && php cron/calculate-contact-metrics.php >> logs/cron-metrics.log 2>&1

# A cada hora (Se tem poucos contatos)
0 * * * * cd /var/www/html && php cron/calculate-contact-metrics.php >> logs/cron-metrics.log 2>&1
```

#### Passo 4: Verificar logs
```bash
tail -f logs/cron-metrics.log
```

---

## 🔄 Opção 2: Worker Contínuo (Daemon)

### Instalação

#### Passo 1: Dar permissão de execução
```bash
chmod +x cron/contact-metrics-worker.php
```

#### Passo 2: Testar manualmente
```bash
php cron/contact-metrics-worker.php
```

Saída esperada:
```
═══════════════════════════════════════════════════════════════
🚀 Contact Metrics Worker iniciado (Standalone)
═══════════════════════════════════════════════════════════════
📁 Root Dir: /var/www/html
📝 Log File: /var/www/html/logs/contact-metrics-worker.log

⚙️ Configurações:
   Lote: 50 contatos
   Intervalo: 60s
   Memória máxima: 128MB

✅ Ciclo #1 | Processados: 23 | Erros: 0 | Tempo: 5.12s
✅ Ciclo #2 | Processados: 15 | Erros: 0 | Tempo: 3.45s
...
```

**Pressione Ctrl+C para parar** (ou crie arquivo `storage/contact-metrics-worker-stop.txt`)

#### Passo 3: Configurar como Daemon

##### Opção A: Com Supervisor (Recomendado)

1. **Instalar Supervisor** (se não tiver):
```bash
sudo apt-get install supervisor
```

2. **Criar arquivo de configuração**:
```bash
sudo nano /etc/supervisor/conf.d/contact-metrics-worker.conf
```

3. **Adicionar configuração**:
```ini
[program:contact-metrics-worker]
command=php /var/www/html/cron/contact-metrics-worker.php
directory=/var/www/html
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/logs/contact-metrics-worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
stopwaitsecs=30
```

4. **Atualizar Supervisor**:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start contact-metrics-worker
```

5. **Verificar status**:
```bash
sudo supervisorctl status contact-metrics-worker
```

##### Opção B: Com systemd

1. **Criar arquivo de serviço**:
```bash
sudo nano /etc/systemd/system/contact-metrics-worker.service
```

2. **Adicionar configuração**:
```ini
[Unit]
Description=Contact Metrics Worker
After=mysql.service
Wants=mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/html
ExecStart=/usr/bin/php /var/www/html/cron/contact-metrics-worker.php
Restart=always
RestartSec=5
StandardOutput=append:/var/www/html/logs/contact-metrics-worker.log
StandardError=append:/var/www/html/logs/contact-metrics-worker-error.log

[Install]
WantedBy=multi-user.target
```

3. **Habilitar e iniciar**:
```bash
sudo systemctl daemon-reload
sudo systemctl enable contact-metrics-worker
sudo systemctl start contact-metrics-worker
```

4. **Verificar status**:
```bash
sudo systemctl status contact-metrics-worker
```

5. **Ver logs**:
```bash
journalctl -u contact-metrics-worker -f
```

---

## ⚙️ Configurações

### Ajustar no CRON (calculate-contact-metrics.php)

```php
// Linha ~32
$batchSize = 100; // Quantos contatos processar por execução

// Ajustar conforme necessário:
$batchSize = 200; // Servidor mais potente
$batchSize = 50;  // Servidor mais fraco
```

### Ajustar no Worker (contact-metrics-worker.php)

```php
// Linha ~58
$batchSize = 50;   // Quantos contatos por ciclo
$sleepTime = 60;   // Segundos entre ciclos
$maxMemory = 128 * 1024 * 1024; // Limite de memória

// Exemplos de ajuste:

// Processar mais rápido (menor latência)
$batchSize = 30;
$sleepTime = 30; // A cada 30 segundos

// Processar mais contatos por vez
$batchSize = 100;
$sleepTime = 120; // A cada 2 minutos

// Servidor com mais memória
$maxMemory = 256 * 1024 * 1024; // 256MB
```

---

## 🛑 Como Parar

### CRON
```bash
# Apenas remova do crontab ou espere terminar
crontab -e
# Remover/comentar a linha
```

### Worker (Modo Daemon)

#### Parada Graceful (Recomendado):
```bash
# Criar arquivo de stop
touch storage/contact-metrics-worker-stop.txt

# Worker vai parar no próximo ciclo
```

#### Parada Forçada:

**Com Supervisor:**
```bash
sudo supervisorctl stop contact-metrics-worker
```

**Com systemd:**
```bash
sudo systemctl stop contact-metrics-worker
```

**Ou encontrar processo:**
```bash
ps aux | grep contact-metrics-worker
kill -15 <PID>  # SIGTERM (graceful)
# ou
kill -9 <PID>   # SIGKILL (forçado, último recurso)
```

---

## 📊 Monitoramento

### Ver Logs em Tempo Real

**CRON:**
```bash
tail -f logs/cron-metrics.log
```

**Worker:**
```bash
tail -f logs/contact-metrics-worker.log
```

### Verificar Pendências no Banco

```sql
-- Quantos contatos precisam de recálculo?
SELECT 
    calculation_priority,
    COUNT(*) as total,
    CASE calculation_priority
        WHEN 3 THEN 'Urgente (conversa aberta + msg nova)'
        WHEN 2 THEN 'Normal (conversa aberta)'
        WHEN 1 THEN 'Baixa (conversa fechada)'
        ELSE 'Não recalcular'
    END as descricao
FROM contact_metrics
WHERE needs_recalculation = 1
GROUP BY calculation_priority
ORDER BY calculation_priority DESC;
```

### Ver Última Execução

```sql
-- Últimas métricas calculadas
SELECT 
    contact_id,
    last_calculated_at,
    TIMESTAMPDIFF(MINUTE, last_calculated_at, NOW()) as minutes_ago,
    needs_recalculation,
    calculation_priority
FROM contact_metrics
ORDER BY last_calculated_at DESC
LIMIT 20;
```

---

## 🆘 Troubleshooting

### CRON não está executando?

1. **Verificar se está no crontab:**
```bash
crontab -l
```

2. **Verificar logs do sistema:**
```bash
grep CRON /var/log/syslog
```

3. **Testar caminho manualmente:**
```bash
cd /var/www/html && php cron/calculate-contact-metrics.php
```

4. **Verificar permissões:**
```bash
ls -la cron/calculate-contact-metrics.php
chmod +x cron/calculate-contact-metrics.php
```

### Worker não inicia?

1. **Verificar se bootstrap existe:**
```bash
ls -la config/bootstrap.php
```

2. **Testar manualmente:**
```bash
php cron/contact-metrics-worker.php
```

3. **Ver erro específico:**
```bash
php cron/contact-metrics-worker.php 2>&1 | tee error.log
```

### Worker para sozinho?

1. **Ver logs de erro:**
```bash
tail -100 logs/contact-metrics-worker.log
```

2. **Verificar uso de memória:**
```sql
-- Quantidade de contatos pendentes
SELECT COUNT(*) FROM contact_metrics WHERE needs_recalculation = 1;
```

3. **Aumentar memória:**
```php
// No arquivo contact-metrics-worker.php
$maxMemory = 256 * 1024 * 1024; // 256MB
```

### Processamento muito lento?

1. **Verificar índices:**
```sql
SHOW INDEX FROM messages WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM conversations WHERE Key_name LIKE 'idx_%';
```

2. **Reduzir lote:**
```php
$batchSize = 25; // Processar menos por vez
```

3. **Aumentar intervalo:**
```php
$sleepTime = 120; // 2 minutos entre ciclos
```

---

## 📋 Comparação: CRON vs Worker

| Característica | CRON | Worker |
|----------------|------|--------|
| **Execução** | 1x a cada X minutos | Contínuo (loop) |
| **Latência** | Até 30 minutos | ~1 minuto |
| **Recursos** | Baixo (apenas quando roda) | Médio (sempre rodando) |
| **Complexidade** | Simples (só crontab) | Média (precisa Supervisor) |
| **Ideal para** | < 1000 contatos | > 1000 contatos |
| **Restart** | Automático (CRON) | Manual ou Supervisor |
| **Logs** | Simples | Detalhados |

---

## 🎯 Recomendação Final

### Para 95% dos casos: Use CRON
```bash
# Adicione ao crontab
*/30 * * * * cd /var/www/html && php cron/calculate-contact-metrics.php >> logs/cron-metrics.log 2>&1
```

### Para alto volume (muitas mensagens/segundo): Use Worker
```bash
# Configure Supervisor
sudo supervisorctl start contact-metrics-worker
```

### Ou use os dois! 🚀
- **CRON**: Para recálculo periódico geral
- **Worker**: Para processar fila de prioridades altas

Ambos verificam `needs_recalculation = 1`, então não vão duplicar trabalho.

---

**Data**: 2026-01-12  
**Versão**: 1.0 - Standalone  
**Status**: ✅ Pronto para Uso

