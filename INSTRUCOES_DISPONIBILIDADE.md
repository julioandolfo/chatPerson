# Sistema de Disponibilidade Dinâmica - Instruções

## Problema Identificado

Agentes que fecham o navegador sem fazer logout ficam marcados como "online" indefinidamente, pois não há processo periódico para verificar e atualizar o status.

## Solução Implementada

### 1. Verificação Automática via Cron

Criado script `public/check-availability.php` que deve ser executado periodicamente (recomendado: a cada 5 minutos).

#### Configurar no Servidor Linux:

```bash
# Editar crontab
crontab -e

# Adicionar linha (executar a cada 5 minutos)
*/5 * * * * php /var/www/html/public/check-availability.php >> /var/www/html/storage/logs/availability-cron.log 2>&1
```

#### Configurar no Windows (Laragon):

1. Abrir "Agendador de Tarefas" do Windows
2. Criar nova tarefa básica
3. Nome: "Check Agent Availability"
4. Gatilho: Repetir a cada 5 minutos
5. Ação: Iniciar programa
   - Programa: `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe`
   - Argumentos: `C:\laragon\www\chat\public\check-availability.php`

### 2. Verificação via HTTP (Alternativa)

Se não puder configurar cron no servidor, use um serviço externo como:
- **cron-job.org** (gratuito)
- **EasyCron**
- **Qualquer serviço de cron HTTP**

**URL para configurar:**
```
https://seudominio.com/cron-availability.php
```

**Frequência recomendada:** A cada 5 minutos

## Como Funciona

### Fluxo de Verificação:

1. **Script roda periodicamente** (a cada 5 minutos via cron)
2. **Busca agentes** com status `online`, `away` ou `busy`
3. **Para cada agente, verifica:**
   - `last_seen_at` (último heartbeat recebido)
   - `last_activity_at` (última atividade real)
4. **Aplica regras:**
   - Se `last_seen_at` > `offline_timeout_minutes` (padrão: 5min) → marca como **offline**
   - Se `last_activity_at` > `away_timeout_minutes` (padrão: 15min) → marca como **away**
5. **Notifica via WebSocket** (se houver clientes conectados)
6. **Registra no histórico** (`user_availability_history`)

### Configurações (em `/settings?tab=availability`):

- **Timeout para Offline**: Minutos sem heartbeat para marcar como offline (padrão: 5)
- **Timeout para Away**: Minutos sem atividade para marcar como ausente (padrão: 15)
- **Auto Online no Login**: Marcar como online ao fazer login (padrão: sim)
- **Auto Offline no Logout**: Marcar como offline ao fazer logout (padrão: sim)

## Diferença entre `last_seen_at` e `last_activity_at`

- **`last_seen_at`**: Atualizado a cada heartbeat (ping) - indica que o navegador está aberto
- **`last_activity_at`**: Atualizado apenas com atividade real (mouse, teclado, click) - indica que o usuário está ativo

## Heartbeat

### WebSocket:
- Envia `ping` a cada 30 segundos (configurável)
- Atualiza `last_seen_at`

### Polling:
- Faz requisição a cada 3 segundos (configurável)
- Atualiza `last_seen_at` automaticamente

## Solução Imediata (Temporária)

Para corrigir agentes que estão online há dias, execute manualmente:

```bash
php public/check-availability.php
```

Ou acesse via navegador:
```
https://seudominio.com/cron-availability.php
```

Isso irá verificar todos os agentes e atualizar os status conforme as regras.

## Próximos Passos

1. **Executar migrations:**
   ```bash
   php database/migrate.php
   ```

2. **Executar seed de configurações:**
   ```bash
   php database/seed.php 006_create_availability_settings
   ```

3. **Configurar cron no servidor** (escolha uma opção):
   - Opção A: Cron nativo (Linux)
   - Opção B: Agendador de Tarefas (Windows)
   - Opção C: Serviço HTTP externo (cron-job.org)

4. **Testar:**
   - Fazer login → deve marcar como online
   - Fechar navegador → após 5 minutos, deve marcar como offline
   - Ficar inativo → após 15 minutos, deve marcar como away

## Monitoramento

Para ver o log de execução do cron:
```bash
tail -f storage/logs/availability-cron.log
```

Ou verificar manualmente:
```bash
php public/check-availability.php
```

## Troubleshooting

### Agentes não mudam de status automaticamente
- Verificar se o cron está rodando
- Verificar logs em `storage/logs/availability-cron.log`
- Executar manualmente para testar: `php public/check-availability.php`

### Configurações não estão sendo aplicadas
- Verificar se o seed foi executado
- Acessar `/settings?tab=availability` e salvar as configurações

### Heartbeat não está funcionando
- Verificar se `ActivityTracker.js` está carregado (console do navegador)
- Verificar se WebSocket ou Polling está ativo
- Verificar configurações em `/settings?tab=websocket`

