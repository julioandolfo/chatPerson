# Guia de Configuração do Scheduler de Automações

## 📋 O Que É

O Scheduler de Automações é um script que executa periodicamente para processar automações baseadas em tempo:
- ⏰ **Tempo sem resposta do cliente**
- ⏰ **Tempo sem resposta do agente**
- 📅 **Agendamentos** (diário, semanal)

## 🔧 Arquivos Criados

1. ✅ `app/Services/AutomationSchedulerService.php` - Service de processamento
2. ✅ `public/automation-scheduler.php` - Script do cronjob

## 🧪 Teste Manual (IMPORTANTE - Fazer Primeiro!)

Antes de configurar o cronjob, teste manualmente:

### Windows (Laragon)

```bash
# Abrir PowerShell ou CMD
cd C:\laragon\www\chat
php public/automation-scheduler.php
```

### Linux/Mac

```bash
cd /path/to/project
php public/automation-scheduler.php
```

### ✅ Saída Esperada

```
================================================================================
[2025-12-21 17:00:00] AUTOMATION SCHEDULER INICIADO
================================================================================

[17:00:00] Processando gatilhos 'time_based'...
=== Processando gatilhos 'time_based' ===
Encontradas 0 automações ativas.
=== Fim do processamento 'time_based' ===

[17:00:00] Processando gatilhos 'no_customer_response'...
=== Processando gatilhos 'no_customer_response' ===
Encontradas 0 automações ativas.
=== Fim do processamento 'no_customer_response' ===

[17:00:00] Processando gatilhos 'no_agent_response'...
=== Processando gatilhos 'no_agent_response' ===
Encontradas 0 automações ativas.
=== Fim do processamento 'no_agent_response' ===

================================================================================
[2025-12-21 17:00:00] ✅ Scheduler executado com sucesso!
Tempo de execução: 0.123s
================================================================================
```

## ⚙️ Configuração do Cronjob

Após o teste manual bem-sucedido, configure para executar automaticamente.

### 🪟 Windows (Agendador de Tarefas)

#### Método 1: Interface Gráfica

1. **Abrir Agendador de Tarefas**
   - Pressionar `Win + R`
   - Digitar: `taskschd.msc`
   - Pressionar Enter

2. **Criar Nova Tarefa**
   - Clicar em "Criar Tarefa..." (no menu direito)

3. **Aba "Geral"**
   - Nome: `Chat Automation Scheduler`
   - Descrição: `Executa automações baseadas em tempo`
   - ☑️ Executar estando o usuário conectado ou não
   - ☑️ Executar com privilégios mais altos (se necessário)

4. **Aba "Gatilhos"**
   - Clicar "Novo..."
   - Iniciar a tarefa: **Em um agendamento**
   - Configurações: **Diariamente**
   - Hora de início: **00:00:00**
   - ☑️ **Repetir tarefa a cada:** `1 minuto`
   - **por tempo de:** `Indefinidamente`
   - ☑️ Habilitado
   - Clicar "OK"

5. **Aba "Ações"**
   - Clicar "Novo..."
   - Ação: **Iniciar um programa**
   - Programa/script: `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe`
     - ⚠️ **Ajuste a versão do PHP** conforme seu Laragon
   - Adicionar argumentos: `public\automation-scheduler.php`
   - Iniciar em: `C:\laragon\www\chat`
   - Clicar "OK"

6. **Aba "Condições"**
   - ☐ Desmarcar "Iniciar a tarefa apenas se o computador estiver conectado à alimentação CA"
   - ☐ Desmarcar "Parar se o computador passar a ser alimentado por bateria"

7. **Aba "Configurações"**
   - ☑️ Permitir que a tarefa seja executada sob demanda
   - ☑️ Executar a tarefa assim que possível após uma inicialização agendada ser perdida
   - ☐ Se a tarefa falhar, reiniciar a cada: `1 minuto`

8. **Salvar**
   - Clicar "OK"
   - Inserir senha do usuário se solicitado

#### Método 2: PowerShell (Automático)

Crie um arquivo `setup-scheduler.ps1`:

```powershell
# setup-scheduler.ps1
$action = New-ScheduledTaskAction -Execute 'C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe' `
    -Argument 'public\automation-scheduler.php' `
    -WorkingDirectory 'C:\laragon\www\chat'

$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration ([TimeSpan]::MaxValue)

$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit (New-TimeSpan -Minutes 5)

Register-ScheduledTask -TaskName "Chat Automation Scheduler" `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Description "Executa automações baseadas em tempo" `
    -Force

Write-Host "✅ Tarefa agendada criada com sucesso!"
Write-Host "Verifique no Agendador de Tarefas: taskschd.msc"
```

Execute como Administrador:

```powershell
powershell -ExecutionPolicy Bypass -File setup-scheduler.ps1
```

### 🐧 Linux/Mac (Crontab)

#### Editar Crontab

```bash
crontab -e
```

#### Adicionar Linha

```bash
# Executar a cada 1 minuto
* * * * * cd /path/to/project && php public/automation-scheduler.php >> storage/logs/scheduler.log 2>&1

# OU com caminho absoluto do PHP
* * * * * cd /var/www/chat && /usr/bin/php public/automation-scheduler.php >> storage/logs/scheduler.log 2>&1
```

**Explicação:**
- `* * * * *` = A cada minuto
- `cd /path/to/project` = Navegar para o diretório do projeto
- `php public/automation-scheduler.php` = Executar o script
- `>> storage/logs/scheduler.log` = Redirecionar saída para log
- `2>&1` = Redirecionar erros para o mesmo log

#### Verificar Crontab

```bash
crontab -l
```

#### Verificar Logs

```bash
tail -f storage/logs/scheduler.log
```

## 📊 Monitoramento

### Verificar se Está Funcionando

#### 1. Logs em Tempo Real

**Windows:**
```bash
Get-Content storage\logs\automation-2025-12-21.log -Wait
```

**Linux/Mac:**
```bash
tail -f storage/logs/automation-$(date +%Y-%m-%d).log
```

#### 2. Logs do Scheduler

**Windows:**
```bash
Get-Content storage\logs\scheduler.log -Tail 50
```

**Linux/Mac:**
```bash
tail -50 storage/logs/scheduler.log
```

#### 3. Última Execução

Verifique a tabela `automation_executions`:

```sql
SELECT 
    ae.id,
    ae.automation_id,
    a.name as automation_name,
    ae.conversation_id,
    ae.status,
    ae.created_at
FROM automation_executions ae
JOIN automations a ON a.id = ae.automation_id
WHERE a.trigger_type IN ('no_customer_response', 'no_agent_response', 'time_based')
ORDER BY ae.created_at DESC
LIMIT 10;
```

## 🧪 Teste Completo do Fluxo

### 1. Criar Automação de Teste

1. Acesse `/automations`
2. Criar nova automação:
   - **Nome:** "Teste - Reengajamento 1 minuto"
   - **Gatilho:** "Tempo sem Resposta do Cliente"
   - **Tempo:** `1` minuto
   - **Status:** Ativa

3. Adicionar nós:
   - **Ação:** Enviar mensagem
   - **Conteúdo:** "Olá! Notei que você não respondeu. Ainda posso ajudar?"

4. Salvar automação

### 2. Criar Situação de Teste

1. Abrir uma conversa
2. Enviar mensagem **como agente**
3. Cliente **não responde**
4. Aguardar 1 minuto

### 3. Aguardar Scheduler

- Se configurado corretamente, em até **1 minuto** o scheduler irá:
  1. Detectar a conversa sem resposta do cliente
  2. Executar a automação
  3. Enviar a mensagem de reengajamento

### 4. Verificar Execução

- ✅ Mensagem enviada na conversa
- ✅ Log em `storage/logs/automation-YYYY-MM-DD.log`
- ✅ Registro em `automation_executions`

## ❌ Solução de Problemas

### Problema: Script não executa

**Verificar:**
1. ✅ Cronjob/Task configurado corretamente
2. ✅ Caminho do PHP correto
3. ✅ Caminho do projeto correto
4. ✅ Permissões de execução (Linux: `chmod +x public/automation-scheduler.php`)

**Windows - Verificar no Histórico:**
1. Abrir Agendador de Tarefas
2. Localizar "Chat Automation Scheduler"
3. Aba "Histórico" → Ver últimas execuções

### Problema: Automações não executam

**Verificar:**
1. ✅ Automação está **Ativa** (`status = 'active'` e `is_active = true`)
2. ✅ Conversas atendem os critérios (funil, estágio, status)
3. ✅ Tempo configurado já passou
4. ✅ Logs em `storage/logs/automation-YYYY-MM-DD.log`

### Problema: Execuções duplicadas

**Solução:** O sistema já previne isso!
- Verifica se automação foi executada nos últimos 10 minutos
- Não executa novamente se já foi executada recentemente

### Problema: Logs vazios

**Verificar:**
1. ✅ Pasta `storage/logs/` existe e tem permissões de escrita
2. ✅ Script tem permissão para criar arquivos

**Windows:**
```bash
mkdir storage\logs -Force
```

**Linux/Mac:**
```bash
mkdir -p storage/logs
chmod -R 775 storage/logs
```

## 📈 Performance

### Tempo de Execução Esperado

- **0 automações:** ~0.01s
- **10 automações, 100 conversas:** ~0.5s
- **50 automações, 1000 conversas:** ~2-5s

### Otimização

Se o scheduler demorar mais de 10 segundos:
1. Adicionar índices no banco:
   ```sql
   CREATE INDEX idx_messages_conv_created ON messages(conversation_id, created_at);
   CREATE INDEX idx_conversations_status ON conversations(status, funnel_id, funnel_stage_id);
   CREATE INDEX idx_automation_executions_recent ON automation_executions(automation_id, conversation_id, created_at);
   ```

2. Aumentar intervalo para 2 ou 5 minutos:
   ```bash
   # Crontab: a cada 2 minutos
   */2 * * * * cd /path/to/project && php public/automation-scheduler.php
   ```

## 📝 Logs e Auditoria

### Logs Gerados

1. **automation-YYYY-MM-DD.log**
   - Detalhes de cada execução
   - Conversas processadas
   - Erros detalhados

2. **scheduler.log** (Cronjob)
   - Saída do script
   - Horário de cada execução
   - Tempo de processamento

### Retenção de Logs

**Linux/Mac - Rotação automática:**
```bash
# /etc/logrotate.d/chat-automation
/path/to/project/storage/logs/scheduler.log {
    daily
    rotate 7
    compress
    missingok
    notifempty
}
```

## ✅ Checklist de Configuração

- [ ] Teste manual executado com sucesso
- [ ] Cronjob/Task configurado
- [ ] Primeira execução automática confirmada
- [ ] Logs sendo gerados corretamente
- [ ] Automação de teste criada e testada
- [ ] Documentação atualizada
- [ ] Equipe notificada

## 🎯 Próximos Passos

Após configuração bem-sucedida:
1. ✅ Criar automações reais
2. ✅ Monitorar logs por 24h
3. ✅ Ajustar tempos conforme necessário
4. ✅ Documentar casos de uso da equipe

## 📞 Suporte

Em caso de problemas:
1. Verificar logs detalhados
2. Executar teste manual
3. Consultar esta documentação
4. Verificar permissões e caminhos

---

**Última atualização:** 21/12/2025

