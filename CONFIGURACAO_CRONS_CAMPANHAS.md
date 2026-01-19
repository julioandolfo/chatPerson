# ⏰ CONFIGURAÇÃO DE CRONS - SISTEMA DE CAMPANHAS

## 📋 Crons Obrigatórios para Campanhas

Aqui estão **TODOS** os crons que você precisa configurar para o sistema de campanhas funcionar completamente.

---

## 🔴 **1. PROCESSAR CAMPANHAS** (CRÍTICO!)

**Arquivo:** `public/scripts/process-campaigns.php`

**O que faz:**
- Processa fila de envios de campanhas ativas
- Envia mensagens agendadas de campanhas
- Processa até 50 mensagens por execução
- Respeita horários de envio configurados
- Gerencia taxa de envio (throttling)

**Frequência:** ⚡ **A CADA 1 MINUTO**

### Windows (Task Scheduler)

```
Nome: Chat - Processar Campanhas
Programa: C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe
Argumentos: C:\laragon\www\chat\public\scripts\process-campaigns.php
Repetir: A cada 1 minuto
Duração: Indefinidamente
Executar mesmo se usuário não estiver conectado: ✅
```

### Linux (crontab)

```bash
# Editar crontab
crontab -e

# Adicionar linha
* * * * * php /var/www/html/public/scripts/process-campaigns.php >> /var/log/chat-campaigns.log 2>&1
```

---

## 🔵 **2. PROCESSAR SEQUÊNCIAS DRIP** (IMPORTANTE)

**Arquivo:** `public/scripts/process-drip-sequences.php`

**O que faz:**
- Processa sequências drip (campanhas gotejamento)
- Envia próximo passo da sequência para cada contato
- Verifica delays entre etapas
- Gerencia progressão automática

**Frequência:** ⏱️ **A CADA 1 HORA**

### Windows (Task Scheduler)

```
Nome: Chat - Processar Sequências Drip
Programa: C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe
Argumentos: C:\laragon\www\chat\public\scripts\process-drip-sequences.php
Repetir: A cada 1 hora
Duração: Indefinidamente
Executar mesmo se usuário não estiver conectado: ✅
```

### Linux (crontab)

```bash
0 * * * * php /var/www/html/public/scripts/process-drip-sequences.php >> /var/log/chat-drip.log 2>&1
```

---

## 🟢 **3. PROCESSAR FONTES EXTERNAS** (NOVO!)

**Arquivo:** `public/scripts/process-external-sources.php`

**O que faz:**
- Sincroniza contatos de bancos externos (MySQL, PostgreSQL)
- Importa novos contatos automaticamente
- Atualiza contatos existentes
- Processa fontes com sync automático habilitado

**Frequência:** ⏱️ **A CADA 1 HORA**

### Windows (Task Scheduler)

```
Nome: Chat - Sincronizar Fontes Externas
Programa: C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe
Argumentos: C:\laragon\www\chat\public\scripts\process-external-sources.php
Repetir: A cada 1 hora
Duração: Indefinidamente
Executar mesmo se usuário não estiver conectado: ✅
```

### Linux (crontab)

```bash
0 * * * * php /var/www/html/public/scripts/process-external-sources.php >> /var/log/chat-external-sources.log 2>&1
```

---

## 🟡 **4. PROCESSAR MENSAGENS AGENDADAS** (OPCIONAL)

**Arquivo:** `public/scripts/process-scheduled-messages.php`

**O que faz:**
- Envia mensagens individuais agendadas
- Diferente de campanhas, são mensagens avulsas agendadas
- Processa até 50 mensagens por execução

**Frequência:** ⚡ **A CADA 1 MINUTO**

### Windows (Task Scheduler)

```
Nome: Chat - Processar Mensagens Agendadas
Programa: C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe
Argumentos: C:\laragon\www\chat\public\scripts\process-scheduled-messages.php
Repetir: A cada 1 minuto
Duração: Indefinidamente
Executar mesmo se usuário não estiver conectado: ✅
```

### Linux (crontab)

```bash
* * * * * php /var/www/html/public/scripts/process-scheduled-messages.php >> /var/log/chat-scheduled.log 2>&1
```

---

## 🟣 **5. PROCESSAR LEMBRETES** (OPCIONAL)

**Arquivo:** `public/scripts/process-reminders.php`

**O que faz:**
- Envia lembretes automáticos configurados
- Notifica agentes sobre tarefas pendentes
- Alertas de follow-up

**Frequência:** ⏱️ **A CADA 5 MINUTOS**

### Windows (Task Scheduler)

```
Nome: Chat - Processar Lembretes
Programa: C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe
Argumentos: C:\laragon\www\chat\public\scripts\process-reminders.php
Repetir: A cada 5 minutos
Duração: Indefinidamente
Executar mesmo se usuário não estiver conectado: ✅
```

### Linux (crontab)

```bash
*/5 * * * * php /var/www/html/public/scripts/process-reminders.php >> /var/log/chat-reminders.log 2>&1
```

---

## 📊 RESUMO DE PRIORIDADES

| Cron | Frequência | Prioridade | Para que serve |
|------|-----------|------------|----------------|
| **process-campaigns.php** | 1 minuto | 🔴 CRÍTICO | Enviar campanhas em massa |
| **process-drip-sequences.php** | 1 hora | 🔵 IMPORTANTE | Sequências gotejamento |
| **process-external-sources.php** | 1 hora | 🟢 IMPORTANTE | Sincronizar bancos externos |
| **process-scheduled-messages.php** | 1 minuto | 🟡 OPCIONAL | Mensagens avulsas agendadas |
| **process-reminders.php** | 5 minutos | 🟣 OPCIONAL | Lembretes e alertas |

---

## 🪟 WINDOWS - GUIA PASSO A PASSO

### 1. Abrir Task Scheduler

```
Win + R → taskschd.msc → Enter
```

### 2. Criar Nova Tarefa

1. Clique com botão direito em **"Biblioteca do Agendador de Tarefas"**
2. Selecione **"Criar Tarefa..."**

### 3. Aba "Geral"

- **Nome:** Chat - Processar Campanhas
- **Descrição:** Processa fila de envios de campanhas
- ✅ Executar estando o usuário conectado ou não
- ✅ Executar com privilégios mais altos
- **Configurar para:** Windows 10

### 4. Aba "Disparadores"

1. Clique em **"Novo..."**
2. **Iniciar a tarefa:** Em um agendamento
3. **Configurações:** Diariamente
4. **Repetir tarefa a cada:** 1 minuto
5. **Duração:** Indefinidamente
6. ✅ Habilitado

### 5. Aba "Ações"

1. Clique em **"Novo..."**
2. **Ação:** Iniciar um programa
3. **Programa/script:**
   ```
   C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe
   ```
4. **Argumentos:**
   ```
   C:\laragon\www\chat\public\scripts\process-campaigns.php
   ```
5. **Iniciar em:** (deixe vazio)

### 6. Aba "Condições"

- ❌ Desmarque tudo (não queremos restrições)

### 7. Aba "Configurações"

- ✅ Permitir que a tarefa seja executada sob demanda
- ✅ Se a tarefa falhar, reiniciar a cada: 1 minuto
- ✅ Parar a tarefa se ela for executada por: 1 hora
- ✅ Se a tarefa em execução não terminar quando solicitada: Parar a tarefa existente

### 8. Salvar

Clique em **OK** e digite a senha do Windows se solicitado.

### 9. Repetir para os Outros Crons

Repita os passos 2-8 para cada cron, ajustando:
- Nome da tarefa
- Caminho do script PHP
- Frequência do disparador

---

## 🐧 LINUX - GUIA PASSO A PASSO

### 1. Editar Crontab

```bash
crontab -e
```

### 2. Adicionar Todas as Linhas

```bash
# ========================================
# CRONS DO SISTEMA DE CAMPANHAS
# ========================================

# Processar campanhas (a cada 1 minuto) - CRÍTICO
* * * * * php /var/www/html/public/scripts/process-campaigns.php >> /var/log/chat-campaigns.log 2>&1

# Processar sequências drip (a cada 1 hora)
0 * * * * php /var/www/html/public/scripts/process-drip-sequences.php >> /var/log/chat-drip.log 2>&1

# Sincronizar fontes externas (a cada 1 hora)
0 * * * * php /var/www/html/public/scripts/process-external-sources.php >> /var/log/chat-external-sources.log 2>&1

# Processar mensagens agendadas (a cada 1 minuto) - opcional
* * * * * php /var/www/html/public/scripts/process-scheduled-messages.php >> /var/log/chat-scheduled.log 2>&1

# Processar lembretes (a cada 5 minutos) - opcional
*/5 * * * * php /var/www/html/public/scripts/process-reminders.php >> /var/log/chat-reminders.log 2>&1
```

### 3. Salvar e Sair

```bash
# Nano: Ctrl + X, depois Y, depois Enter
# Vim: ESC, depois :wq, depois Enter
```

### 4. Verificar se Foi Salvo

```bash
crontab -l
```

### 5. Criar Diretório de Logs (se não existir)

```bash
sudo mkdir -p /var/log
sudo chmod 777 /var/log
```

---

## 🔍 VERIFICAR SE CRONS ESTÃO RODANDO

### Windows

1. Abrir Task Scheduler
2. Ir em **"Biblioteca do Agendador de Tarefas"**
3. Procurar pelas tarefas "Chat - ..."
4. Coluna **"Última Execução"** deve mostrar data/hora recente
5. Coluna **"Status"** deve ser **"Pronto"**

### Linux

```bash
# Ver logs em tempo real
tail -f /var/log/chat-campaigns.log

# Ver últimas 50 linhas
tail -50 /var/log/chat-campaigns.log

# Verificar se crons estão rodando
ps aux | grep process-campaigns
```

---

## ⚠️ TROUBLESHOOTING

### Problema: Cron não está rodando

**Solução Windows:**
1. Verificar se o serviço "Agendador de Tarefas" está ativo
2. Verificar permissões do usuário
3. Testar executando manualmente:
   ```cmd
   cd C:\laragon\www\chat\public\scripts
   php process-campaigns.php
   ```

**Solução Linux:**
1. Verificar se cron está ativo:
   ```bash
   sudo service cron status
   ```
2. Verificar logs do sistema:
   ```bash
   grep CRON /var/log/syslog
   ```

### Problema: Cron roda mas não envia mensagens

1. Verificar logs:
   ```bash
   tail -100 /var/log/chat-campaigns.log
   ```
2. Verificar se há campanhas ativas no banco:
   ```sql
   SELECT * FROM campaigns WHERE status = 'active' OR status = 'running';
   ```
3. Verificar se há itens na fila:
   ```sql
   SELECT * FROM campaign_queue WHERE status = 'pending' LIMIT 10;
   ```

### Problema: Erro de permissões

**Linux:**
```bash
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
```

---

## ✅ CHECKLIST FINAL

Marque tudo que você configurou:

### Windows
- [ ] Instalou Task Scheduler
- [ ] Criou tarefa "Chat - Processar Campanhas" (1 minuto)
- [ ] Criou tarefa "Chat - Processar Sequências Drip" (1 hora)
- [ ] Criou tarefa "Chat - Sincronizar Fontes Externas" (1 hora)
- [ ] (Opcional) Criou tarefa "Chat - Processar Mensagens Agendadas" (1 minuto)
- [ ] (Opcional) Criou tarefa "Chat - Processar Lembretes" (5 minutos)
- [ ] Testou executando manualmente
- [ ] Verificou que está rodando automaticamente

### Linux
- [ ] Editou crontab
- [ ] Adicionou linha process-campaigns (1 minuto)
- [ ] Adicionou linha process-drip-sequences (1 hora)
- [ ] Adicionou linha process-external-sources (1 hora)
- [ ] (Opcional) Adicionou linha process-scheduled-messages (1 minuto)
- [ ] (Opcional) Adicionou linha process-reminders (5 minutos)
- [ ] Salvou crontab
- [ ] Verificou com `crontab -l`
- [ ] Criou diretório de logs
- [ ] Testou vendo logs com `tail -f`

---

## 🎯 PRONTO!

Agora seu sistema de campanhas está 100% automatizado! 🚀

Os crons vão:
- ✅ Enviar campanhas automaticamente
- ✅ Processar sequências drip
- ✅ Sincronizar contatos de fontes externas
- ✅ Enviar mensagens agendadas
- ✅ Disparar lembretes

**Importante:** Os 3 primeiros crons são ESSENCIAIS para o sistema de campanhas funcionar!

═══════════════════════════════════════════════════════════
  Configuração de Crons - Sistema de Campanhas
  Data: 19/01/2026
  Status: ✅ GUIA COMPLETO
═══════════════════════════════════════════════════════════
