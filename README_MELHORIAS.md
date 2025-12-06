# 🚀 GUIA DE CONFIGURAÇÃO DAS MELHORIAS APLICADAS

**Data**: 2025-01-27

---

## 📋 MELHORIAS IMPLEMENTADAS

### ✅ Correções Críticas
1. **WhatsAppService** - Agora usa ConversationService corretamente
2. **WhatsAppService** - Automações funcionam para mensagens WhatsApp

### ✅ Novas Funcionalidades
3. **Sistema de Monitoramento de SLA** - Completo e funcional
4. **Sistema de Followup Automático** - Integrado com jobs
5. **Campo resolved_at** - Adicionado ao fechar conversas

---

## ⚙️ CONFIGURAÇÃO NECESSÁRIA

### 1. Configurar Cron/Task Scheduler

#### Linux/Mac (Cron)
```bash
# Editar crontab
crontab -e

# Adicionar linha (executa a cada 5 minutos)
*/5 * * * * php /caminho/absoluto/para/public/run-scheduled-jobs.php >> /caminho/para/logs/jobs.log 2>&1
```

#### Windows (Task Scheduler)
1. Abrir **Agendador de Tarefas**
2. Criar **Tarefa Básica**
3. Nome: "Chat Scheduled Jobs"
4. Gatilho: **Diariamente** ou **Quando o computador iniciar**
5. Ação: **Iniciar um programa**
6. Programa: `php.exe`
7. Argumentos: `C:\laragon\www\chat\public\run-scheduled-jobs.php`
8. Iniciar em: `C:\laragon\www\chat\public`
9. Configurar para executar a cada 5 minutos (propriedades avançadas)

#### Teste Manual
```bash
# Executar manualmente para testar
php public/run-scheduled-jobs.php

# Ou com followup forçado
php public/run-scheduled-jobs.php?force_followup=1
```

---

### 2. Configurar SLA nas Configurações

1. Acessar: **Configurações > Conversas**
2. Configurar:
   - **SLA de Primeira Resposta**: Tempo em minutos (ex: 15)
   - **SLA de Resolução**: Tempo em minutos (ex: 60)
   - **Habilitar Monitoramento de SLA**: ✅ Sim
   - **Reatribuir Automaticamente após SLA**: ✅ Sim (opcional)
   - **Minutos após SLA para reatribuir**: 30 (opcional)

---

### 3. Verificar Permissões de Arquivos

```bash
# Garantir que script pode ser executado
chmod +x public/run-scheduled-jobs.php

# Garantir que logs podem ser escritos
chmod 755 logs/
```

---

## 🧪 TESTES

### Teste 1: Monitoramento de SLA

1. Criar conversa de teste
2. Não responder como agente
3. Aguardar SLA exceder (ou ajustar tempo no código para teste)
4. Executar: `php public/run-scheduled-jobs.php`
5. Verificar se conversa foi reatribuída (se configurado)

### Teste 2: Followup Automático

1. Fechar uma conversa
2. Executar: `php public/run-scheduled-jobs.php?force_followup=1`
3. Verificar se followup foi processado

### Teste 3: WhatsApp com Integrações

1. Enviar mensagem via WhatsApp
2. Verificar se:
   - Conversa é criada
   - Atribuição automática funciona
   - Automações são executadas
   - WebSocket notifica

---

## 📊 MONITORAMENTO

### Logs

Os jobs registram logs em:
- `logs/app.log` - Logs gerais
- `logs/jobs.log` - Logs dos jobs (se configurado no cron)

### Verificar Execução

```bash
# Ver últimos logs
tail -f logs/app.log | grep "SLA\|Followup"

# Ver erros
tail -f logs/app.log | grep "ERRO\|Error"
```

---

## 🔧 TROUBLESHOOTING

### Jobs não executam
- Verificar se cron/task scheduler está configurado
- Verificar permissões de arquivos
- Verificar se PHP está no PATH
- Testar execução manual

### SLA não funciona
- Verificar se monitoramento está habilitado nas configurações
- Verificar logs para erros
- Verificar se há conversas abertas para monitorar

### Followup não funciona
- Verificar se há conversas fechadas há mais de 3 dias
- Verificar se há agentes de IA de followup configurados
- Executar manualmente com `?force_followup=1`

---

## 📝 NOTAS IMPORTANTES

1. **Performance**: Jobs são executados a cada 5 minutos. Ajustar frequência conforme necessário.

2. **Followup**: Por padrão executa apenas quando minuto é 0 (uma vez por hora). Usar `?force_followup=1` para forçar execução.

3. **SLA**: Verifica até 100 conversas por execução. Ajustar limite se necessário.

4. **Reatribuição**: Só funciona se configurado nas configurações avançadas de conversas.

---

**Última atualização**: 2025-01-27

