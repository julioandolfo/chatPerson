# Correção: Card "Agentes Online" no Dashboard

## 📋 Problema Identificado

O card "Agentes Online" no dashboard principal não estava mostrando corretamente quando os agentes estavam online. O contador frequentemente mostrava valores incorretos, não refletindo o estado real dos agentes conectados.

### Causa Raiz

A função `getOnlineAgents()` no `DashboardService` estava apenas verificando o campo `availability_status = 'online'` no banco de dados, sem considerar se o agente realmente estava ativo no momento (baseado no heartbeat).

**Código anterior:**
```php
private static function getOnlineAgents(): int
{
    $sql = "SELECT COUNT(*) as total FROM users 
            WHERE role IN ('agent', 'admin', 'supervisor') 
            AND status = 'active' 
            AND availability_status = 'online'";
    $result = \App\Helpers\Database::fetch($sql);
    return (int)($result['total'] ?? 0);
}
```

### Problemas com a Abordagem Anterior

1. **Dependência do Cron**: O campo `availability_status` só era atualizado quando o script `check-availability.php` rodava via cron
2. **Status Desatualizado**: Se um agente fechasse o navegador sem fazer logout, permanecia como "online" até o próximo cron
3. **Heartbeat Ignorado**: O sistema de heartbeat existia (`last_seen_at`), mas não era usado para determinar se um agente estava realmente online

## ✅ Solução Implementada

A função foi atualizada para verificar **dois critérios** ao contar agentes online:

1. **Status no Banco**: `availability_status = 'online'`
2. **Heartbeat Recente**: `last_seen_at` atualizado nos últimos X minutos (configurável)

**Código corrigido:**
```php
private static function getOnlineAgents(): int
{
    // Obter configurações de disponibilidade
    $settings = \App\Services\AvailabilityService::getSettings();
    $offlineTimeoutMinutes = $settings['offline_timeout_minutes'];
    
    // Calcular o timestamp mínimo para considerar online
    // Um agente é considerado online se:
    // 1. availability_status = 'online' E
    // 2. last_seen_at foi atualizado nos últimos X minutos (configurável)
    $sql = "SELECT COUNT(*) as total FROM users 
            WHERE role IN ('agent', 'admin', 'supervisor') 
            AND status = 'active' 
            AND availability_status = 'online'
            AND last_seen_at IS NOT NULL
            AND last_seen_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)";
    
    $result = \App\Helpers\Database::fetch($sql, [$offlineTimeoutMinutes]);
    return (int)($result['total'] ?? 0);
}
```

## 🔍 Como Funciona o Sistema de Disponibilidade

### 1. Heartbeat (Ping)

O frontend envia heartbeats periódicos para indicar que o agente está com o navegador aberto:

- **Arquivo**: `public/assets/js/activity-tracker.js`
- **Intervalo**: 30 segundos (configurável)
- **Atualiza**: Campo `last_seen_at` no banco de dados

### 2. Atividade Real

Registra quando o usuário está realmente interagindo (mouse, teclado, cliques):

- **Atualiza**: Campo `last_activity_at` no banco de dados
- **Usado para**: Detectar se agente ficou "away" (ausente)

### 3. Cron de Verificação

Script que verifica timeouts e atualiza status automaticamente:

- **Arquivo**: `public/check-availability.php`
- **Frequência recomendada**: A cada 5 minutos
- **Função**: Marcar como offline/away agentes inativos

### 4. Configurações

Timeouts configuráveis em `settings`:

- `offline_timeout_minutes`: Tempo sem heartbeat para marcar como offline (padrão: 5 min)
- `away_timeout_minutes`: Tempo sem atividade para marcar como away (padrão: 15 min)
- `heartbeat_interval_seconds`: Intervalo entre heartbeats (padrão: 30 seg)

## 📊 Benefícios da Correção

1. **Precisão em Tempo Real**: O card agora mostra apenas agentes com heartbeat ativo
2. **Independência do Cron**: Não depende mais exclusivamente do cron para contagem correta
3. **Consistência**: Alinhado com o sistema de disponibilidade já existente
4. **Configurável**: Usa a mesma configuração de timeout do resto do sistema

## 🔧 Ferramentas de Debug

### Script de Diagnóstico

Foi criado um script para facilitar o diagnóstico de problemas:

**Arquivo**: `public/debug-agents-online.php`

**Uso**: Acesse via navegador `http://seu-dominio/debug-agents-online.php`

**Funcionalidades**:
- Mostra configurações de disponibilidade
- Lista todos os agentes e seus status
- Identifica inconsistências entre status e heartbeat
- Calcula tempo desde último heartbeat/atividade
- Sugere ações corretivas

## 📝 Configuração Recomendada

### 1. Configurar Cron (Windows Task Scheduler ou Linux Crontab)

**Linux/macOS:**
```bash
# A cada 5 minutos
*/5 * * * * php /caminho/completo/public/check-availability.php >> /var/log/availability-cron.log 2>&1
```

**Windows (Task Scheduler):**
- Programa: `php.exe` (caminho completo)
- Argumentos: `C:\laragon\www\chat\public\check-availability.php`
- Frequência: A cada 5 minutos

### 2. Verificar JavaScript Carregado

Certifique-se de que o `activity-tracker.js` está sendo carregado no layout principal:

```php
<!-- No layout principal (ex: views/layouts/metronic/header.php) -->
<script src="/assets/js/activity-tracker.js"></script>
```

### 3. Verificar WebSocket (Opcional)

Se usar WebSocket, certifique-se de que o servidor está rodando:

```bash
php public/websocket-server.php
```

## 🧪 Como Testar

1. **Acesse o script de debug**:
   ```
   http://localhost/debug-agents-online.php
   ```

2. **Verifique o card no dashboard**:
   - Acesse o dashboard principal
   - Observe o card "Agentes Online"
   - Deve mostrar apenas agentes com heartbeat ativo

3. **Simule offline**:
   - Feche o navegador de um agente
   - Aguarde 5 minutos (ou o timeout configurado)
   - Execute o cron manualmente: `php public/check-availability.php`
   - Verifique se o agente foi marcado como offline
   - Refresh no dashboard para ver a atualização

4. **Verifique logs**:
   ```
   tail -f logs/dash.log
   ```

## 📚 Arquivos Relacionados

- ✅ `app/Services/DashboardService.php` - Correção aplicada
- ✅ `public/debug-agents-online.php` - Script de debug criado
- 📄 `app/Services/AvailabilityService.php` - Serviço de disponibilidade
- 📄 `public/check-availability.php` - Cron de verificação
- 📄 `public/assets/js/activity-tracker.js` - Heartbeat frontend
- 📄 `app/Controllers/RealtimeController.php` - Processamento de heartbeat
- 📄 `views/dashboard/index.php` - Dashboard principal

## 🎯 Próximos Passos

1. ✅ Testar a correção no ambiente de desenvolvimento
2. ✅ Executar o script de debug para verificar status
3. ⏳ Configurar o cron para rodar automaticamente
4. ⏳ Monitorar o comportamento do card por alguns dias
5. ⏳ Considerar adicionar cache se houver muitos agentes

## 💡 Observações

- A correção é **retrocompatível** - não quebra funcionalidades existentes
- O timeout usado é o mesmo configurado nas settings de disponibilidade
- Agentes com `availability_status` diferentes de 'online' não são contados (independente do heartbeat)
- Se o campo `last_seen_at` for `NULL`, o agente não será contado como online

---

**Data da Correção**: 01/02/2026  
**Versão**: 1.0  
**Autor**: Sistema de IA - Cursor
