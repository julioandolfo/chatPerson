# Correção: Filtro de Canal/Conta no Gatilho de Automações

## Problema Relatado

As automações não estavam respeitando o filtro de canal configurado no nó de gatilho (trigger).

**Exemplo:**
- Automação configurada para canal **WhatsApp** e **Todas as Contas**
- Mensagem recebida pelo **Instagram**
- ❌ A automação era executada mesmo assim (incorreto)

## Causa Raiz

Quando o nó de gatilho (trigger) era configurado e salvo:

1. ✅ Os dados eram salvos corretamente em `automation_nodes` (tabela de nós)
2. ❌ O campo `trigger_config` na tabela `automations` **NÃO era atualizado**

A validação das automações usa `trigger_config` da tabela `automations` para filtrar:
- Canal (whatsapp, instagram, facebook, etc)
- Conta de integração
- Outros critérios específicos do trigger

Como esse campo não era sincronizado com os dados do nó, os filtros não funcionavam.

## Solução Implementada

### 1. Método Auxiliar para Sincronização

Criado método `updateTriggerConfigFromNode()` em `AutomationService.php`:

```php
private static function updateTriggerConfigFromNode(int $automationId, array $nodeData): void
{
    // Extrai campos relevantes do node_data
    $triggerConfig = [];
    
    // Canal
    if (isset($nodeData['channel']) && !empty($nodeData['channel'])) {
        $triggerConfig['channel'] = $nodeData['channel'];
    }
    
    // Conta de integração
    if (isset($nodeData['integration_account_id']) && !empty($nodeData['integration_account_id'])) {
        $triggerConfig['integration_account_id'] = $nodeData['integration_account_id'];
    }
    
    // Conta WhatsApp legacy
    if (isset($nodeData['whatsapp_account_id']) && !empty($nodeData['whatsapp_account_id'])) {
        $triggerConfig['whatsapp_account_id'] = $nodeData['whatsapp_account_id'];
    }
    
    // Palavra-chave, campo, estágios, tempo de espera, webhook, etc.
    // ... outros campos específicos de cada tipo de trigger
    
    // Atualiza o trigger_config na automação
    Automation::update($automationId, [
        'trigger_config' => json_encode($triggerConfig)
    ]);
}
```

### 2. Atualização no Método `createNode()`

Quando um novo nó trigger é criado:

```php
public static function createNode(int $automationId, array $data): int
{
    // ... validações ...
    
    // Se for nó trigger, atualizar trigger_config da automação
    if ($data['node_type'] === 'trigger') {
        self::updateTriggerConfigFromNode($automationId, $data['node_data']);
    }
    
    // ... criar nó no banco ...
}
```

### 3. Atualização no Método `updateNode()`

Quando um nó trigger é atualizado:

```php
public static function updateNode(int $nodeId, array $data): bool
{
    // ... buscar nó ...
    
    // Se for nó trigger, atualizar trigger_config da automação
    $nodeType = $data['node_type'] ?? $node['node_type'];
    if ($nodeType === 'trigger' && isset($data['node_data']) && is_array($data['node_data'])) {
        self::updateTriggerConfigFromNode($node['automation_id'], $data['node_data']);
    }
    
    // ... atualizar nó no banco ...
}
```

### 4. Logging Detalhado para Debug

Melhorado o método `matchesTriggerConfig()` em `Automation.php` com logs detalhados:

```php
private static function matchesTriggerConfig(?array $config, array $data): bool
{
    \App\Helpers\Logger::automation("  matchesTriggerConfig: Verificando config=" . json_encode($config));
    
    foreach ($config as $key => $value) {
        // Comparação normal (inclui canal)
        if ($data[$key] != $value) {
            \App\Helpers\Logger::automation("    ✗ Campo '{$key}' não corresponde: esperado='{$value}', recebido='{$data[$key]}' - REJEITADO");
            return false;
        }
        \App\Helpers\Logger::automation("    ✓ Campo '{$key}' corresponde: '{$value}'");
    }
    
    return true;
}
```

## Campos Sincronizados

O `trigger_config` agora é automaticamente sincronizado com os seguintes campos do nó:

### Todos os tipos de trigger:
- `channel` - Canal da integração
- `integration_account_id` - ID da conta de integração
- `whatsapp_account_id` - ID da conta WhatsApp (legacy)

### Específicos por tipo:
- **message_received**: `keyword` (palavra-chave)
- **conversation_updated**: `field` (campo que mudou)
- **conversation_moved**: `from_stage_id`, `to_stage_id` (estágios origem/destino)
- **inactivity**: `wait_time_value`, `wait_time_unit`, `only_open_conversations`
- **webhook**: `webhook_url`

## Como Testar

1. **Criar/Editar uma automação:**
   - Acesse Automações
   - Crie/edite uma automação
   - Configure o nó de gatilho (trigger)
   - Selecione um canal específico (ex: WhatsApp)
   - Selecione "Todas as Contas" ou uma conta específica
   - Salve o layout

2. **Verificar no banco:**
   ```sql
   SELECT id, name, trigger_type, trigger_config 
   FROM automations 
   WHERE id = [ID_DA_AUTOMACAO];
   ```
   
   O campo `trigger_config` deve conter:
   ```json
   {
     "channel": "whatsapp",
     "integration_account_id": null
   }
   ```

3. **Testar execução:**
   - Envie mensagem pelo canal correto (WhatsApp) → ✅ Automação deve executar
   - Envie mensagem por outro canal (Instagram) → ❌ Automação NÃO deve executar

4. **Verificar logs:**
   ```
   tail -f storage/logs/automation_[data].log
   ```
   
   Procure por:
   ```
   matchesTriggerConfig: Verificando config={"channel":"whatsapp"}
   ✓ Campo 'channel' corresponde: 'whatsapp'
   TODOS os critérios atendidos - ACEITO
   ```
   
   Ou:
   ```
   ✗ Campo 'channel' não corresponde: esperado='whatsapp', recebido='instagram' - REJEITADO
   ```

## Impacto

### ✅ Benefícios:
- Filtros de canal/conta agora funcionam corretamente
- Automações mais precisas e seguras
- Melhor controle de execução
- Logs detalhados para debug

### ⚠️ Observações:
- Automações antigas (criadas antes dessa correção) precisam ter o nó trigger editado e salvo novamente para sincronizar o `trigger_config`
- Ou pode rodar um script de migração para sincronizar automaticamente

## Script de Migração (Opcional)

Se necessário, criar script para sincronizar automações existentes:

```php
// database/migrations/XXX_sync_trigger_config.php

function up_sync_trigger_config() {
    global $pdo;
    
    // Buscar todas as automações
    $sql = "SELECT a.id, a.trigger_type, an.node_data 
            FROM automations a
            INNER JOIN automation_nodes an ON an.automation_id = a.id
            WHERE an.node_type = 'trigger'";
    
    $stmt = $pdo->query($sql);
    $automations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($automations as $auto) {
        $nodeData = json_decode($auto['node_data'], true);
        
        // Extrair campos relevantes
        $triggerConfig = [];
        if (!empty($nodeData['channel'])) {
            $triggerConfig['channel'] = $nodeData['channel'];
        }
        if (!empty($nodeData['integration_account_id'])) {
            $triggerConfig['integration_account_id'] = $nodeData['integration_account_id'];
        }
        if (!empty($nodeData['whatsapp_account_id'])) {
            $triggerConfig['whatsapp_account_id'] = $nodeData['whatsapp_account_id'];
        }
        
        // Atualizar trigger_config
        $updateSql = "UPDATE automations SET trigger_config = ? WHERE id = ?";
        $pdo->prepare($updateSql)->execute([
            json_encode($triggerConfig),
            $auto['id']
        ]);
        
        echo "✅ Automação #{$auto['id']} sincronizada\n";
    }
    
    echo "✅ Sincronização concluída!\n";
}
```

## Arquivos Modificados

1. `app/Services/AutomationService.php`
   - Método `createNode()` - Adiciona sincronização do trigger_config
   - Método `updateNode()` - Adiciona sincronização do trigger_config
   - Método `updateTriggerConfigFromNode()` - NOVO

2. `app/Models/Automation.php`
   - Método `matchesTriggerConfig()` - Adiciona logging detalhado

3. `views/automations/show.php`
   - Correção da variável `whatsappOptionsHtml` (não definida)

## Conclusão

O filtro de canal/conta no gatilho das automações agora funciona corretamente. Todas as configurações do nó trigger são automaticamente sincronizadas com o `trigger_config` da automação, garantindo que os filtros sejam aplicados corretamente durante a execução.

