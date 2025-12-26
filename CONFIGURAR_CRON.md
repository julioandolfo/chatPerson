# Configuração do Cron para Processamento de IA

## ⚠️ IMPORTANTE

O sistema de buffer de mensagens da IA precisa de um cron rodando **A CADA 1 MINUTO** para funcionar corretamente.

## Como funciona

1. **Cliente envia mensagem** → Sistema cria buffer com timer de 5 segundos
2. **Background script tenta processar** → Executado imediatamente em background
3. **Fallback de segurança** → Se background falhar, arquivo JSON fica aguardando
4. **Cron processa fallbacks** → A cada 1 minuto, verifica e processa buffers pendentes

## Configuração

### Linux/Unix (Crontab)

```bash
# Editar crontab
crontab -e

# Adicionar linha (executar a cada 1 minuto)
* * * * * php /caminho/completo/para/public/run-scheduled-jobs.php
```

### Windows (Agendador de Tarefas)

1. Abra **Agendador de Tarefas** (Task Scheduler)
2. Clique em **"Criar Tarefa Básica"**
3. Nome: `Chat - Processar Buffers de IA`
4. Gatilho: **Diariamente**
5. Repetir tarefa a cada: **1 minuto**
6. Duração: **Indefinidamente**
7. Ação: **Iniciar um programa**
   - Programa: `C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe`
   - Argumentos: `C:\laragon\www\chat\public\run-scheduled-jobs.php`
   - Iniciar em: `C:\laragon\www\chat`

### Verificar se está funcionando

```bash
# Verificar logs
tail -f storage/logs/app.log | grep "Background script"

# Verificar arquivos de buffer pendentes
ls -la storage/ai_buffers/

# Executar manualmente para testar
php public/process-ai-buffers.php
```

## Fluxo Completo

```
Cliente envia REPLY
    ↓
Sistema detecta e cria buffer
    ↓
Salva JSON em storage/ai_buffers/ (fallback)
    ↓
Tenta executar em background IMEDIATAMENTE
    ↓
┌─────────────────────────────────┬────────────────────────────────┐
│ Sucesso (99% dos casos)         │ Falha (problema no sistema)    │
├─────────────────────────────────┼────────────────────────────────┤
│ 1. Aguarda 5s                   │ 1. Arquivo JSON permanece      │
│ 2. IA processa                  │ 2. Cron roda (até 1 min)       │
│ 3. Remove JSON                  │ 3. Cron processa JSON          │
│ 4. Cliente recebe resposta      │ 4. Remove JSON                 │
│    em ~5-10 segundos ✅         │ 5. Cliente recebe em ~1-6 min  │
└─────────────────────────────────┴────────────────────────────────┘
```

## Tempos Esperados

- **Normal**: Cliente recebe resposta em 5-10 segundos
- **Fallback**: Cliente recebe resposta em até 1-6 minutos (depende quando cron rodar)
- **Sem cron**: Sistema não responde (arquivo fica travado)

## Troubleshooting

### Problema: IA não responde

1. Verificar se cron está rodando:
   ```bash
   ps aux | grep run-scheduled-jobs.php
   ```

2. Verificar arquivos pendentes:
   ```bash
   ls -la storage/ai_buffers/
   ```

3. Processar manualmente:
   ```bash
   php public/process-ai-buffers.php
   ```

4. Verificar logs:
   ```bash
   tail -f storage/logs/app.log
   ```

### Problema: Muitos arquivos em ai_buffers

Se houver muitos arquivos JSON acumulados, significa que:
- Cron não está rodando
- Background scripts não estão funcionando
- Há erro no processamento

**Solução**: Execute manualmente e verifique logs de erro
```bash
php public/process-ai-buffers.php
```

