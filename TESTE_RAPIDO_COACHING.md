# 🚀 Teste Rápido - Coaching em Tempo Real

## ✅ Checklist Pré-Teste

- [ ] Migration executada (`realtime_coaching_hints` existe)
- [ ] Coaching habilitado em `/settings`
- [ ] API Key da OpenAI configurada
- [ ] Worker rodando (veja abaixo)

---

## 🎯 Teste em 3 Passos

### 1️⃣ Iniciar Worker

**Escolha UMA das opções:**

#### Opção A: Teste Rápido (Terminal)
```bash
# Rode isso e deixe aberto
php public/scripts/coaching-worker-standalone.php
```

#### Opção B: Produção (Cron - Coolify/Linux)
```bash
# Adicione ao crontab
* * * * * cd /var/www/html && flock -n /tmp/coaching.lock php public/scripts/process-coaching-queue-standalone.php >> storage/logs/coaching-cron.log 2>&1
```

#### Opção C: Background (Linux)
```bash
nohup php public/scripts/coaching-worker-standalone.php >> storage/logs/coaching-worker.log 2>&1 &
```

---

### 2️⃣ Enviar Mensagem de Teste

Envie do WhatsApp (mínimo 10 caracteres):
```
Olá, gostaria de fazer uma compra de 3 produtos
```

---

### 3️⃣ Verificar Resultado

#### Ver Logs (Navegador)
```
http://seu-dominio/view-all-logs.php
```
Clique em **"Coaching"**

#### Ver Logs (Terminal - Linux)
```bash
tail -f logs/coaching.log
```

#### Ver Logs (PowerShell - Windows)
```powershell
Get-Content logs\coaching.log -Wait -Tail 50
```

---

## 📊 Logs Esperados

### ✅ Sucesso - Você deve ver:

```log
[XX:XX:XX] 📩 Nova mensagem recebida - ID: XXXX
[XX:XX:XX] ✅ Coaching está HABILITADO
[XX:XX:XX] 📝 Mensagem: "Olá, gostaria..." (tamanho: 35 chars)
[XX:XX:XX] ✅ FILTRO 1: OK - É mensagem de cliente
[XX:XX:XX] ✅ FILTRO 2: OK - Tamanho adequado
[XX:XX:XX] ✅✅✅ TODOS OS FILTROS PASSARAM!
[XX:XX:XX] ✅ Mensagem adicionada na fila

# 3-10 segundos depois...
[XX:XX:XX] ⚙️ === PROCESSANDO FILA DE COACHING ===
[XX:XX:XX] 🤖 Chamando OpenAI...
[XX:XX:XX] ✅ Resposta recebida
[XX:XX:XX] 💾 Hint salvo: ID #XX
[XX:XX:XX] ✅ Hint enviado via WebSocket!
```

### 🎉 Na Tela

Um **card vai aparecer** no canto inferior direito da página de conversas:

```
╔════════════════════════════════╗
║  🔔 SINAL DE COMPRA           ║
╠════════════════════════════════╣
║  Cliente demonstrou interesse ║
║  em realizar compra.          ║
║                                ║
║  💡 Sugestões:                 ║
║  • Pergunte qual produto      ║
║  • Ofereça desconto           ║
╚════════════════════════════════╝
```

---

## 🐛 Problemas Comuns

### ❌ "Nada aparece nos logs"

**Causa:** Worker não está rodando ou falhou ao iniciar

**Solução:**
```bash
# Testar manualmente
php public/scripts/process-coaching-queue-standalone.php

# Ver erros
cat storage/logs/coaching-cron.log
```

---

### ❌ "Mensagem muito curta (0 < 10 chars)"

**Causa:** Conteúdo da mensagem está vazio

**Solução:**
- ✅ Já corrigido! Atualize o código (`$message['content']` em vez de `$message['body']`)
- Envie uma nova mensagem de teste

---

### ❌ "Coaching DESABILITADO"

**Causa:** Configuração desligada

**Solução:**
1. Acesse `/settings`
2. Aba **Conversas**
3. Seção **Coaching em Tempo Real**
4. Marque ✅ **Habilitar**
5. Salvar

---

### ❌ "API Key não configurada"

**Solução:**
```sql
INSERT INTO settings (`key`, `value`, `type`, `group`)
VALUES ('openai_api_key', 'sk-proj-SUACHAVE', 'string', 'ai')
ON DUPLICATE KEY UPDATE `value` = 'sk-proj-SUACHAVE';
```

---

### ❌ "Fila não processa"

**Verificar se worker está rodando:**

```bash
# Linux
ps aux | grep coaching

# Ver logs
tail -f storage/logs/coaching-worker.log
tail -f storage/logs/coaching-cron.log
```

---

## 📁 Arquivos de Log

| Arquivo | Conteúdo |
|---------|----------|
| `logs/coaching.log` | **Logs detalhados** do sistema (mensagens, filtros, IA, hints) |
| `storage/logs/coaching-worker.log` | Logs do worker contínuo (resumo) |
| `storage/logs/coaching-cron.log` | Logs do cron job (se usar cron) |

---

## 🔍 Diagnóstico Rápido

### Verificar Tudo de Uma Vez

**Navegador:**
```
http://seu-dominio/debug-coaching-simple.php
```

**SQL:**
```sql
-- Ver fila
SELECT * FROM coaching_queue WHERE status = 'pending';

-- Ver hints gerados
SELECT * FROM realtime_coaching_hints ORDER BY created_at DESC LIMIT 5;

-- Ver configurações
SELECT * FROM settings WHERE `key` = 'conversation_settings';
```

---

## ✅ Teste Bem-Sucedido

Quando funcionar, você verá:

1. ✅ Logs mostram mensagem sendo processada
2. ✅ Worker processa em 3-10 segundos
3. ✅ Hint aparece na tela (canto inferior direito)
4. ✅ Hint tem tipo, texto e sugestões
5. ✅ Botões "Útil" e "Não útil" funcionam

---

## 🆘 Ainda com Problemas?

1. **Ver logs de erro do PHP:**
   ```bash
   tail -f logs/app.log
   ```

2. **Console do navegador (F12):**
   - Procure por `[Coaching]`
   - Deve mostrar: `✅ Coaching em Tempo Real inicializado`

3. **Verificar banco:**
   ```sql
   SHOW TABLES LIKE '%coaching%';
   -- Deve mostrar: coaching_queue, realtime_coaching_hints, realtime_coaching_cache
   ```

---

**Última Atualização:** 2026-01-10 21:30
**Status:** ✅ Pronto para teste
