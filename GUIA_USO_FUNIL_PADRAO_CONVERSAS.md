# 📘 GUIA: Usando Funil/Etapa Padrão na Criação de Conversas

**Para Desenvolvedores**  
**Data**: 2025-01-17

---

## 🎯 OBJETIVO

Este guia explica como modificar o `ConversationService::create()` para usar o sistema de funil/etapa padrão implementado.

---

## 📋 LÓGICA DE PRIORIDADE

Ao criar uma conversa, o sistema deve seguir esta ordem de prioridade para definir o funil/etapa:

```
1. AUTOMAÇÃO ESPECÍFICA (se existir)
   ↓
2. CONFIGURAÇÃO DA INTEGRAÇÃO (se configurado)
   ↓
3. PADRÃO DO SISTEMA (fallback)
```

---

## 💻 IMPLEMENTAÇÃO

### Local: `app/Services/ConversationService.php`

### Método: `create(array $data): int`

```php
public static function create(array $data): int
{
    // ... validações ...
    
    // ===========================================================
    // NOVO: Lógica de Funil/Etapa Padrão
    // ===========================================================
    
    $funnelId = null;
    $stageId = null;
    
    // 1. PRIORIDADE: Automação específica (se fornecida)
    if (!empty($data['funnel_id']) && !empty($data['stage_id'])) {
        $funnelId = $data['funnel_id'];
        $stageId = $data['stage_id'];
        error_log("Conversa usando funil/etapa da AUTOMAÇÃO: Funil {$funnelId}, Etapa {$stageId}");
    }
    
    // 2. PRIORIDADE: Configuração da Integração (se configurado)
    elseif (!empty($data['channel_account_id']) && $data['channel'] === 'whatsapp') {
        $whatsappAccount = \App\Models\WhatsAppAccount::find($data['channel_account_id']);
        
        if ($whatsappAccount && !empty($whatsappAccount['default_funnel_id'])) {
            $funnelId = $whatsappAccount['default_funnel_id'];
            $stageId = $whatsappAccount['default_stage_id'] ?? null;
            
            // Se stage_id não foi configurado, usar primeira etapa do funil
            if (!$stageId) {
                $stages = \App\Models\FunnelStage::where('funnel_id', '=', $funnelId);
                if (!empty($stages)) {
                    usort($stages, fn($a, $b) => ($a['position'] ?? 0) - ($b['position'] ?? 0));
                    $stageId = $stages[0]['id'];
                }
            }
            
            error_log("Conversa usando funil/etapa da INTEGRAÇÃO: Funil {$funnelId}, Etapa {$stageId}");
        }
    }
    
    // 3. FALLBACK: Padrão do Sistema
    if (!$funnelId || !$stageId) {
        $defaultConfig = \App\Models\Setting::get('system_default_funnel_stage');
        
        if ($defaultConfig) {
            $config = json_decode($defaultConfig, true);
            $funnelId = $config['funnel_id'] ?? null;
            $stageId = $config['stage_id'] ?? null;
            error_log("Conversa usando funil/etapa PADRÃO DO SISTEMA: Funil {$funnelId}, Etapa {$stageId}");
        }
    }
    
    // Aplicar funil/etapa na conversa
    if ($funnelId && $stageId) {
        $data['funnel_id'] = $funnelId;
        $data['stage_id'] = $stageId;
    } else {
        error_log("AVISO: Nenhum funil/etapa encontrado! Conversa será criada sem funil.");
    }
    
    // ===========================================================
    // FIM: Lógica de Funil/Etapa Padrão
    // ===========================================================
    
    // ... resto da criação da conversa ...
    
    return Conversation::create($data);
}
```

---

## 🔍 DETALHAMENTO

### 1. Automação Específica

```php
if (!empty($data['funnel_id']) && !empty($data['stage_id'])) {
    // Usar valores passados pela automação
    $funnelId = $data['funnel_id'];
    $stageId = $data['stage_id'];
}
```

**Quando?**
- Automação foi acionada (chatbot, keywords, etc)
- Automação definiu funil/etapa específico
- Exemplo: Cliente digitou "1" → vai para Funil Comercial, Etapa Novo Lead

---

### 2. Configuração da Integração

```php
elseif (!empty($data['channel_account_id']) && $data['channel'] === 'whatsapp') {
    $whatsappAccount = \App\Models\WhatsAppAccount::find($data['channel_account_id']);
    
    if ($whatsappAccount && !empty($whatsappAccount['default_funnel_id'])) {
        $funnelId = $whatsappAccount['default_funnel_id'];
        $stageId = $whatsappAccount['default_stage_id'] ?? null;
        
        // Se etapa não configurada, usar primeira do funil
        if (!$stageId) {
            $stages = \App\Models\FunnelStage::where('funnel_id', '=', $funnelId);
            usort($stages, fn($a, $b) => ($a['position'] ?? 0) - ($b['position'] ?? 0));
            $stageId = $stages[0]['id'] ?? null;
        }
    }
}
```

**Quando?**
- Nenhuma automação específica
- Conta WhatsApp tem funil/etapa configurado
- Exemplo: WhatsApp de Vendas → sempre vai para Funil Comercial

---

### 3. Padrão do Sistema

```php
if (!$funnelId || !$stageId) {
    $defaultConfig = \App\Models\Setting::get('system_default_funnel_stage');
    $config = json_decode($defaultConfig, true);
    $funnelId = $config['funnel_id'] ?? null;
    $stageId = $config['stage_id'] ?? null;
}
```

**Quando?**
- Nenhuma automação específica
- Integração não tem funil configurado
- Exemplo: Primeira mensagem de cliente em conta sem configuração

---

## 🧪 TESTANDO

### Teste 1: Automação

```php
$data = [
    'contact_id' => 1,
    'channel' => 'whatsapp',
    'funnel_id' => 2,      // Da automação
    'stage_id' => 5,       // Da automação
    // ...
];

$conversationId = ConversationService::create($data);
// Deve usar Funil 2, Etapa 5 (automação)
```

### Teste 2: Integração

```php
// Configurar WhatsApp Account com funil padrão:
// default_funnel_id = 3
// default_stage_id = 7

$data = [
    'contact_id' => 1,
    'channel' => 'whatsapp',
    'channel_account_id' => 1,  // WhatsApp configurado
    // Sem funnel_id/stage_id
];

$conversationId = ConversationService::create($data);
// Deve usar Funil 3, Etapa 7 (integração)
```

### Teste 3: Padrão do Sistema

```php
// WhatsApp Account SEM funil configurado

$data = [
    'contact_id' => 1,
    'channel' => 'whatsapp',
    'channel_account_id' => 1,  // Sem default_funnel_id
    // Sem funnel_id/stage_id
];

$conversationId = ConversationService::create($data);
// Deve usar Funil 1 (Funil Entrada), Etapa 1 (Nova Entrada)
```

---

## 📊 FLUXOGRAMA

```
┌─────────────────────────────┐
│ Criar Nova Conversa         │
└──────────┬──────────────────┘
           │
           ▼
    ┌──────────────┐
    │ Automação?   │
    └──┬───────────┘
       │ Sim
       ├─────────────────────────────┐
       │ Usar funnel_id/stage_id     │
       │ da automação                │
       └─────────────────────────────┘
       │
       │ Não
       ▼
    ┌─────────────────────────┐
    │ Integração Configurada? │
    └──┬──────────────────────┘
       │ Sim
       ├─────────────────────────────┐
       │ Usar default_funnel_id/     │
       │ default_stage_id            │
       └─────────────────────────────┘
       │
       │ Não
       ▼
    ┌─────────────────────────┐
    │ Usar Padrão do Sistema  │
    │ (system_default_...)    │
    └─────────────────────────┘
           │
           ▼
    ┌──────────────────┐
    │ Criar Conversa   │
    └──────────────────┘
```

---

## ⚠️ OBSERVAÇÕES IMPORTANTES

1. **Validação**: Sempre verificar se funil/etapa existem antes de criar conversa
2. **Logs**: Adicionar logs para debug (`error_log`)
3. **Fallback**: Se nada funcionar, não deixar conversa sem funil (criar log de erro crítico)
4. **Performance**: Cachear configuração padrão do sistema se possível
5. **Outras Integrações**: Lógica similar pode ser aplicada para Email, Telegram, etc

---

## 🚀 EXEMPLO COMPLETO

```php
public static function create(array $data): int
{
    // Validações...
    
    // ============================================
    // DETERMINAR FUNIL E ETAPA
    // ============================================
    
    $funnelId = null;
    $stageId = null;
    
    // 1. Automação
    if (!empty($data['funnel_id']) && !empty($data['stage_id'])) {
        $funnelId = $data['funnel_id'];
        $stageId = $data['stage_id'];
    }
    
    // 2. Integração
    elseif (!empty($data['channel_account_id']) && $data['channel'] === 'whatsapp') {
        $account = \App\Models\WhatsAppAccount::find($data['channel_account_id']);
        if ($account && !empty($account['default_funnel_id'])) {
            $funnelId = $account['default_funnel_id'];
            $stageId = $account['default_stage_id'];
            
            // Fallback para primeira etapa
            if (!$stageId) {
                $stages = \App\Models\FunnelStage::where('funnel_id', '=', $funnelId);
                usort($stages, fn($a, $b) => ($a['position'] ?? 0) - ($b['position'] ?? 0));
                $stageId = $stages[0]['id'] ?? null;
            }
        }
    }
    
    // 3. Sistema
    if (!$funnelId || !$stageId) {
        $default = \App\Models\Setting::get('system_default_funnel_stage');
        if ($default) {
            $config = json_decode($default, true);
            $funnelId = $config['funnel_id'] ?? null;
            $stageId = $config['stage_id'] ?? null;
        }
    }
    
    // Aplicar
    if ($funnelId && $stageId) {
        $data['funnel_id'] = $funnelId;
        $data['stage_id'] = $stageId;
    } else {
        error_log("ERRO CRÍTICO: Nenhum funil/etapa definido para conversa!");
        throw new \Exception("Não foi possível determinar funil/etapa para a conversa");
    }
    
    // ============================================
    // CRIAR CONVERSA
    // ============================================
    
    $conversationId = Conversation::create($data);
    
    // Log de auditoria
    \App\Services\ActivityService::log(
        'conversation_created',
        'conversation',
        $conversationId,
        \App\Helpers\Auth::id(),
        "Conversa criada no funil {$funnelId}, etapa {$stageId}",
        ['funnel_id' => $funnelId, 'stage_id' => $stageId]
    );
    
    return $conversationId;
}
```

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

- [ ] Adicionar lógica de prioridade no `ConversationService::create()`
- [ ] Testar criação com automação
- [ ] Testar criação com integração configurada
- [ ] Testar criação sem configuração (padrão do sistema)
- [ ] Adicionar logs de debug
- [ ] Adicionar validação de funil/etapa existentes
- [ ] Testar com múltiplas contas WhatsApp
- [ ] Verificar performance
- [ ] Documentar em código (comentários)

---

**Status**: 📝 **GUIA CRIADO - AGUARDANDO IMPLEMENTAÇÃO**  
**Última Atualização**: 2025-01-17

