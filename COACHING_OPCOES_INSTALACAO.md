# 🚀 Coaching em Tempo Real - Opções de Instalação

## ✅ **3 Formas de Usar (Escolha a mais fácil para você!)**

---

## **Opção 1: AUTOMÁTICO** ⚡ (Recomendado - Mais Fácil)

### **Como funciona:**
- Sistema dispara processamento automaticamente em background
- **NÃO precisa** de worker rodando 24/7
- **NÃO precisa** configurar cron
- Apenas **habilitar nas configurações** e pronto!

### **Instalação:**

1. **Rodar migrations:**
```bash
php public/index.php migrate
```

2. **Habilitar nas configurações:**
   - Ir em **Configurações > Conversas**
   - Rolar até **"Coaching em Tempo Real (IA)"**
   - **Habilitar** e salvar

3. **Pronto!** ✅

### **Como funciona por trás:**
```
Cliente envia msg 
↓
Sistema salva no banco (RÁPIDO)
↓
Adiciona na tabela coaching_queue
↓
Dispara processo em background automaticamente
↓
Processo analisa e envia dica
```

### **Vantagens:**
- ✅ Mais simples (zero configuração extra)
- ✅ Não precisa de supervisor/cron
- ✅ Funciona em qualquer servidor

### **Desvantagens:**
- ⚠️ Dispara um processo PHP a cada mensagem (overhead mínimo)
- ⚠️ Em servidores com `exec()` desabilitado, não funciona

---

## **Opção 2: CRON JOB** 🕐 (Alternativa Confiável)

### **Como funciona:**
- Cron executa script a cada 5-10 segundos
- Processa fila do banco de dados
- Mais previsível e controlado

### **Instalação:**

1. **Rodar migrations:**
```bash
php public/index.php migrate
```

2. **Configurar cron:**
```bash
crontab -e
```

**Adicionar:**
```bash
# Processar fila a cada 10 segundos (12x por minuto)
* * * * * cd /var/www/html && php public/scripts/process-coaching-queue.php >> /var/log/coaching.log 2>&1
* * * * * sleep 10; cd /var/www/html && php public/scripts/process-coaching-queue.php >> /var/log/coaching.log 2>&1
* * * * * sleep 20; cd /var/www/html && php public/scripts/process-coaching-queue.php >> /var/log/coaching.log 2>&1
* * * * * sleep 30; cd /var/www/html && php public/scripts/process-coaching-queue.php >> /var/log/coaching.log 2>&1
* * * * * sleep 40; cd /var/www/html && php public/scripts/process-coaching-queue.php >> /var/log/coaching.log 2>&1
* * * * * sleep 50; cd /var/www/html && php public/scripts/process-coaching-queue.php >> /var/log/coaching.log 2>&1
```

3. **Habilitar nas configurações** (mesmo da Opção 1)

### **Vantagens:**
- ✅ Mais confiável
- ✅ Funciona mesmo se `exec()` estiver desabilitado
- ✅ Logs centralizados
- ✅ Não depende de processos em background

### **Desvantagens:**
- ⚠️ Precisa configurar cron
- ⚠️ Latência de até 10 segundos

---

## **Opção 3: WORKER CONTÍNUO** 🔄 (Produção - Mais Rápido)

### **Como funciona:**
- Processo roda 24/7 em loop
- Verifica fila a cada 3 segundos
- Menor latência (mais rápido)

### **Instalação:**

1. **Rodar migrations:**
```bash
php public/index.php migrate
```

2. **Configurar Supervisor** (recomendado):

```bash
sudo nano /etc/supervisor/conf.d/coaching-worker.conf
```

**Conteúdo:**
```ini
[program:coaching-worker]
command=php /var/www/html/public/scripts/coaching-worker.php
directory=/var/www/html
user=www-data
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/coaching-worker.log
startsecs=5
stopwaitsecs=10
```

3. **Ativar supervisor:**
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start coaching-worker:*
```

4. **Verificar:**
```bash
sudo supervisorctl status
```

### **OU usar Screen/tmux (desenvolvimento):**
```bash
screen -S coaching-worker
cd /var/www/html
php public/scripts/coaching-worker.php
# Ctrl+A, D para detach
```

### **Vantagens:**
- ✅ Menor latência (3-5 segundos)
- ✅ Mais eficiente
- ✅ Ideal para produção com alto volume

### **Desvantagens:**
- ⚠️ Precisa configurar supervisor
- ⚠️ Mais complexo
- ⚠️ Precisa monitorar processo

---

## 📊 **Comparação:**

| Aspecto | Automático | Cron | Worker |
|---------|-----------|------|--------|
| **Facilidade** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐ |
| **Configuração** | Zero | Cron | Supervisor |
| **Latência** | 5-8s | 8-12s | 3-5s |
| **Confiabilidade** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Performance** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Recomendado para** | Pequeno/Médio | Médio/Grande | Grande/Produção |

---

## 🎯 **Minha Recomendação:**

### **Para a maioria dos casos:**
👉 **Opção 1 (Automático)** - Simplesmente habilitar e usar!

### **Se tiver problemas com exec():**
👉 **Opção 2 (Cron)** - Configurar e esquecer

### **Se tiver alto volume (>1000 msgs/dia):**
👉 **Opção 3 (Worker)** - Máxima performance

---

## 🔍 **Como saber qual usar?**

### **Use Opção 1 se:**
- ✅ Não quer configurar nada extra
- ✅ Servidor permite `exec()`
- ✅ Volume baixo/médio

### **Use Opção 2 se:**
- ✅ `exec()` está desabilitado
- ✅ Quer mais controle
- ✅ Já usa cron para outras coisas

### **Use Opção 3 se:**
- ✅ Alto volume de mensagens
- ✅ Precisa de menor latência possível
- ✅ Tem acesso a supervisor

---

## 🧪 **Testar qual opção está funcionando:**

```bash
# Ver fila
mysql -u root -p chat_person -e "SELECT COUNT(*) FROM coaching_queue WHERE status='pending'"

# Ver processados
mysql -u root -p chat_person -e "SELECT COUNT(*) FROM realtime_coaching_hints WHERE DATE(created_at) = CURDATE()"

# Ver logs (se usando cron)
tail -f /var/log/coaching.log

# Ver logs (se usando supervisor)
tail -f /var/log/supervisor/coaching-worker.log
```

---

## ⚠️ **Troubleshooting:**

### **Hints não aparecem:**

1. **Verificar se está habilitado:**
   - Configurações > Conversas > Coaching em Tempo Real

2. **Verificar fila:**
```sql
SELECT * FROM coaching_queue WHERE status = 'pending' ORDER BY added_at DESC LIMIT 10;
```

3. **Verificar processamento:**
```sql
SELECT * FROM coaching_queue WHERE status = 'completed' ORDER BY processed_at DESC LIMIT 10;
```

4. **Testar manualmente:**
```bash
php public/scripts/process-coaching-queue.php
```

---

## 📝 **Resumo:**

✅ **Todas as 3 opções funcionam**  
✅ **Opção 1 é a mais fácil** (recomendada para começar)  
✅ **Pode trocar de opção depois** sem problemas  
✅ **Escolha a que fizer mais sentido para seu servidor**  

---

**Agora você tem total flexibilidade!** 🎉
