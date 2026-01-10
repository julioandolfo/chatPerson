# 🎯 Como Testar o Sistema de Coaching em Tempo Real

## ✅ O Que Foi Corrigido

###  1. Campo de Mensagem
- ❌ **Antes**: Código usava `$message['body']` (campo inexistente)
- ✅ **Depois**: Corrigido para `$message['content']` (campo correto)
- **Impacto**: Agora as mensagens são lidas corretamente

### 2. Migrations
- ❌ **Antes**: Migration não era executada pelo `php scripts/migrate.php`
- ✅ **Depois**: Nome da função corrigido para `up_create_realtime_coaching_tables()`
- **Arquivo**: `database/migrations/017_create_realtime_coaching_tables.php`

### 3. Frontend
- ❌ **Antes**: CSS/JS do coaching NÃO estavam incluídos na página
- ✅ **Depois**: Adicionados CSS, JS e container HTML
- **Arquivos Modificados**: `views/conversations/index.php`

## 🚀 Passos para Testar

### Passo 1: Executar Migration (se ainda não fez)

```bash
# Opção 1: Via navegador
# Acesse: http://seu-dominio/execute-coaching-migration.php

# Opção 2: Via terminal
php scripts/migrate.php
```

**Verificar:**
- ✅ Tabelas `realtime_coaching_hints` e `realtime_coaching_cache` criadas
- ✅ Arquivo `logs/coaching.log` criado

---

### Passo 2: Iniciar Worker de Processamento da Fila

O sistema usa uma **fila assíncrona** para processar mensagens. Você precisa iniciar o worker:

#### ⭐ Opção A: Worker Contínuo (Recomendado para Produção)

```bash
# Via terminal (mantém rodando em loop infinito)
php public/scripts/coaching-worker-standalone.php

# OU em background (Linux)
nohup php public/scripts/coaching-worker-standalone.php > /dev/null 2>&1 &
```

**Para parar o worker gracefully:**
```bash
touch storage/coaching-worker-stop.txt
```

#### Opção B: Cron Job (Alternativa - Coolify/Produção)

Se não puder manter um processo rodando, use cron para executar a cada minuto:

```bash
# Adicionar ao crontab (Linux/Coolify)
* * * * * cd /var/www/html && php public/scripts/process-coaching-queue-standalone.php >> storage/logs/coaching-cron.log 2>&1

# OU com flock para evitar execuções simultâneas (RECOMENDADO)
* * * * * cd /var/www/html && flock -n /tmp/coaching.lock php public/scripts/process-coaching-queue-standalone.php >> storage/logs/coaching-cron.log 2>&1
```

#### Opção C: Windows (Task Scheduler)

```powershell
# Criar tarefa que executa a cada minuto
schtasks /create /tn "CoachingWorker" /tr "php c:\laragon\www\chat\public\scripts\process-coaching-queue-standalone.php" /sc minute /mo 1 /f
```

#### Opção D: Coolify Scheduled Task

No Coolify, adicione um **Scheduled Task**:
- **Command:** `php public/scripts/process-coaching-queue-standalone.php`
- **Schedule:** `* * * * *` (a cada minuto)

---

### Passo 3: Verificar Configurações

1. Acesse: `/settings` → Aba **Conversas**
2. Seção: **Coaching em Tempo Real (IA)**
3. Verifique se está **HABILITADO** ✅
4. Configurações recomendadas para teste:

```
✅ Habilitar Coaching em Tempo Real
🤖 Modelo: gpt-3.5-turbo
🌡️ Temperature: 0.5
⏱️ Intervalo Mínimo: 10 segundos
📏 Tamanho Mínimo da Mensagem: 10 caracteres
✅ Usar Fila: SIM
```

---

### Passo 4: Testar com Mensagem Real

1. **Envie uma mensagem do WhatsApp**
   - Exemplo: "Olá, gostaria de fazer uma compra de 3 produtos"
   - A mensagem deve ter pelo menos 10 caracteres

2. **Verifique os logs**

```bash
# PowerShell (Windows)
Get-Content logs/coaching.log -Tail 50

# Linux
tail -f logs/coaching.log
```

**Logs esperados:**

```
[2026-01-10 XX:XX:XX] 📩 Nova mensagem recebida - ID: XXXX, Conversa: XXX, Tipo: contact
[2026-01-10 XX:XX:XX] 👤 Agente atribuído: ID X
[2026-01-10 XX:XX:XX] 🎯 queueMessageForAnalysis() - Msg #XXXX
[2026-01-10 XX:XX:XX] ✅ Coaching está HABILITADO
[2026-01-10 XX:XX:XX] 📝 Mensagem: "Olá, gostaria de fazer..." (tamanho: 35 chars)
[2026-01-10 XX:XX:XX] ✅ FILTRO 1: OK - É mensagem de cliente
[2026-01-10 XX:XX:XX] ✅ FILTRO 2: OK - Tamanho adequado (35 >= 10)
[2026-01-10 XX:XX:XX] ✅ FILTRO 3: OK - Rate limit global (0/10)
[2026-01-10 XX:XX:XX] ✅ FILTRO 4: OK - Intervalo agente
[2026-01-10 XX:XX:XX] ✅ FILTRO 5: OK - Fila disponível (0/100)
[2026-01-10 XX:XX:XX] ✅ FILTRO 6: OK - Dentro do limite (Hora: $0/$1, Dia: $0/$10)
[2026-01-10 XX:XX:XX] ✅✅✅ TODOS OS FILTROS PASSARAM!
[2026-01-10 XX:XX:XX] 📋 Modo FILA ativado - Adicionando mensagem na fila
[2026-01-10 XX:XX:XX] ✅ Mensagem adicionada na fila com sucesso!
```

3. **Aguarde o worker processar** (pode levar alguns segundos)

```
[2026-01-10 XX:XX:XX] ⚙️ === PROCESSANDO FILA DE COACHING ===
[2026-01-10 XX:XX:XX] 📋 Fila: 1 itens pendentes
[2026-01-10 XX:XX:XX] 🔍 Processando item #X - Msg #XXXX
[2026-01-10 XX:XX:XX] 🤖 Enviando para OpenAI...
[2026-01-10 XX:XX:XX] ✅ Resposta recebida da OpenAI
[2026-01-10 XX:XX:XX] 💾 Hint salvo no banco: ID #XX
[2026-01-10 XX:XX:XX] 📤 Enviando hint para agente #X
[2026-01-10 XX:XX:XX] ✅ Hint enviado via WebSocket!
```

4. **Veja o hint aparecer na tela**
   - Um card vai aparecer no **canto inferior direito** da página de conversas
   - Com o tipo de hint (objeção, oportunidade, etc)
   - Texto da dica
   - Sugestões de resposta

---

## 📊 Monitoramento

### 1. Ver Todos os Logs
**Acesse:** http://seu-dominio/view-all-logs.php

Logs disponíveis:
- ✅ Coaching (hints gerados)
- 📧 Conversas (mensagens)
- 🤖 Automação
- 📱 Quepasa (WhatsApp)
- 🖥️ Aplicação

### 2. Diagnóstico Completo
**Acesse:** http://seu-dominio/debug-coaching-simple.php

Verifica:
- ✅ Conexão com banco
- ✅ Configurações
- ✅ Tabelas criadas
- ✅ Mensagens recentes
- ✅ Fila de processamento
- ✅ Hints gerados

### 3. Consultar Fila Manualmente

```sql
-- Ver itens na fila
SELECT * FROM coaching_queue WHERE status = 'pending' ORDER BY added_at DESC;

-- Ver hints gerados
SELECT * FROM realtime_coaching_hints ORDER BY created_at DESC LIMIT 10;

-- Ver cache
SELECT * FROM realtime_coaching_cache WHERE expires_at > NOW();
```

---

## 🐛 Troubleshooting

### Problema 1: Mensagem não aparece nos logs

**Possíveis causas:**
1. Mensagem muito curta (< 10 caracteres)
2. Mensagem de agente (só analisa mensagens de clientes)
3. Coaching desabilitado nas configurações
4. Listener não está sendo chamado

**Solução:**
- Verifique configurações em `/settings`
- Envie mensagem com pelo menos 10 caracteres
- Verifique logs de aplicação: `logs/app.log`

### Problema 2: Fila não processa

**Possíveis causas:**
1. Worker não está rodando
2. Tabela `coaching_queue` não existe
3. API Key da OpenAI inválida

**Solução:**
```bash
# Verificar se worker está rodando
ps aux | grep coaching-worker  # Linux
# OU
tasklist | findstr php  # Windows

# Iniciar worker manualmente
php public/scripts/coaching-worker.php

# Verificar API Key
SELECT * FROM settings WHERE `key` = 'openai_api_key';
```

### Problema 3: Hint não aparece na tela

**Possíveis causas:**
1. JS não carregou
2. Container HTML não existe
3. Polling não está funcionando

**Solução:**
- Abra o console do navegador (F12)
- Procure por: `[Coaching]` nos logs
- Deve aparecer: `✅ Coaching em Tempo Real inicializado`
- Verifique se existe: `<div id="coaching-hints-container">`

### Problema 4: Erro "API Key não configurada"

**Solução:**
```sql
-- Inserir API Key
INSERT INTO settings (`key`, `value`, `type`, `group`)
VALUES ('openai_api_key', 'sk-proj-SUACHAVE', 'string', 'ai')
ON DUPLICATE KEY UPDATE `value` = 'sk-proj-SUACHAVE';
```

---

## 📝 Checklist de Funcionamento Completo

- [ ] ✅ Migration executada (`realtime_coaching_hints` existe)
- [ ] ✅ Worker rodando (ver processos)
- [ ] ✅ Coaching habilitado em `/settings`
- [ ] ✅ API Key da OpenAI configurada
- [ ] ✅ Frontend carregado (console mostra "[Coaching]")
- [ ] ✅ Mensagem enviada do WhatsApp
- [ ] ✅ Logs mostram mensagem sendo processada
- [ ] ✅ Fila processa a mensagem
- [ ] ✅ Hint aparece na tela

---

## 🎉 Resultado Esperado

Quando tudo estiver funcionando:

1. Cliente envia mensagem do WhatsApp
2. Sistema detecta (logs/coaching.log)
3. Passa pelos filtros
4. Adiciona na fila
5. Worker processa em 3-10 segundos
6. IA analisa e gera hint
7. Hint aparece em tempo real na tela do agente
8. Agente vê sugestões e pode aplicá-las

**Exemplo de Hint:**

```
╔══════════════════════════════════════╗
║  🔔 SINAL DE COMPRA DETECTADO       ║
╠══════════════════════════════════════╣
║  O cliente demonstrou interesse     ║
║  claro em realizar uma compra.      ║
║                                      ║
║  💡 Sugestões:                       ║
║  • Pergunte qual produto interessa  ║
║  • Ofereça desconto para fechar     ║
║  • Mostre depoimentos de clientes   ║
╚══════════════════════════════════════╝
   [👍 Útil]  [👎 Não útil]  [✖ Fechar]
```

---

## 🔗 Arquivos Relacionados

- `app/Services/RealtimeCoachingService.php` - Lógica principal
- `app/Listeners/MessageReceivedListener.php` - Detecção de mensagens
- `public/assets/js/realtime-coaching.js` - Frontend
- `public/scripts/coaching-worker.php` - Worker de processamento
- `views/conversations/index.php` - Página de conversas
- `logs/coaching.log` - Logs do sistema

---

**Última Atualização:** 2026-01-10 21:00
**Status:** ✅ Sistema configurado e pronto para teste
