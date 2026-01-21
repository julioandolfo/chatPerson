# Melhorias no Tratamento de Quota da OpenAI

## 📋 Sumário

Este documento descreve as melhorias implementadas no sistema para lidar de forma mais robusta com erros de quota excedida da API da OpenAI.

## ❌ Problema Anterior

Quando a quota da OpenAI era excedida (HTTP 429 - `insufficient_quota`), o sistema:

1. **Falhava completamente** - Lançava exception não tratada
2. **Não diferenciava** tipos de erro 429 (quota vs rate limit)
3. **Não notificava** os administradores
4. **Não tinha fallback** - O sistema parava de funcionar
5. **Logs genéricos** - Difícil diagnosticar o problema

## ✅ Melhorias Implementadas

### 1. Tratamento Específico de Erros da OpenAI

**Arquivo modificado:** `app/Services/KanbanAgentService.php`

#### a) Método `callOpenAI()` aprimorado

- ✅ **Detecção específica** de erro de quota excedida (`insufficient_quota`)
- ✅ **Detecção específica** de rate limit temporário (`rate_limit_exceeded`)
- ✅ **Logging detalhado** com tipo de erro, código e mensagem
- ✅ **Timeouts configurados** (30s execução, 10s conexão)
- ✅ **Tratamento de erros cURL**
- ✅ **Validação de resposta** da API

```php
// Exemplo de tratamento
if ($httpCode === 429 && $errorCode === 'insufficient_quota') {
    self::logError("QUOTA DA OPENAI EXCEDIDA! Verifique seu plano e faturamento.");
    self::createQuotaExceededAlert();
    throw new \Exception("QUOTA_EXCEEDED: ...", 429);
}
```

#### b) Método `analyzeConversation()` com fallback

- ✅ **Fallback gracioso** - Retorna análise padrão ao invés de falhar
- ✅ **Identifica erro** pela exception code e message
- ✅ **Análise neutra** quando quota excedida (score 50, sentiment neutral)
- ✅ **Sistema continua funcionando** parcialmente

```php
// Retorno em caso de quota excedida
return [
    'summary' => 'Análise temporariamente indisponível (quota da OpenAI excedida).',
    'score' => 50, // Score neutro
    'sentiment' => 'neutral',
    'urgency' => 'low',
    'error' => 'quota_exceeded',
    'error_message' => 'Quota da OpenAI excedida'
];
```

### 2. Sistema de Alertas

**Nova tabela:** `system_alerts`

#### Estrutura da tabela

```sql
CREATE TABLE system_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(100) NOT NULL,              -- Tipo do alerta
    severity ENUM('info', 'warning', 'critical'), -- Nível de severidade
    title VARCHAR(255) NOT NULL,             -- Título do alerta
    message TEXT NOT NULL,                   -- Mensagem detalhada
    action_url VARCHAR(500) NULL,            -- URL para ação
    is_read BOOLEAN DEFAULT FALSE,           -- Se foi lido
    is_resolved BOOLEAN DEFAULT FALSE,       -- Se foi resolvido
    read_by INT NULL,                        -- Quem leu
    read_at TIMESTAMP NULL,                  -- Quando leu
    resolved_by INT NULL,                    -- Quem resolveu
    resolved_at TIMESTAMP NULL,              -- Quando resolveu
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Funcionalidade

- ✅ **Cria alerta automático** quando quota é excedida
- ✅ **Evita duplicatas** - Apenas 1 alerta por 24h
- ✅ **Link direto** para billing da OpenAI
- ✅ **Severidade crítica** para alertar imediatamente
- ✅ **Rastreamento** de leitura e resolução

### 3. Logging Aprimorado

- ✅ **Logs específicos** para cada tipo de erro
- ✅ **Stack trace completo** em caso de erros
- ✅ **Informações detalhadas** do erro da OpenAI (type, code, message)
- ✅ **Separação clara** entre warning e error

### 4. Fluxo de Tratamento

```
┌─────────────────────────┐
│ Agente tenta analisar   │
│ conversa com OpenAI     │
└───────────┬─────────────┘
            │
            ▼
┌─────────────────────────┐
│ Chama OpenAI API        │
└───────────┬─────────────┘
            │
            ▼
      ┌─────────┐
      │ Sucesso?│
      └────┬────┘
           │
    ┌──────┴──────┐
    │             │
   SIM           NÃO
    │             │
    ▼             ▼
┌────────┐  ┌──────────────┐
│Retorna │  │ Identifica   │
│análise │  │ tipo de erro │
└────────┘  └──────┬───────┘
                   │
         ┌─────────┴─────────┐
         │                   │
         ▼                   ▼
   ┌───────────┐      ┌────────────┐
   │ Quota     │      │ Rate Limit │
   │ Excedida  │      │ Temporário │
   └─────┬─────┘      └──────┬─────┘
         │                   │
         ▼                   ▼
   ┌───────────┐      ┌────────────┐
   │• Cria     │      │• Aguarda   │
   │  alerta   │      │  2s        │
   │• Log      │      │• Log       │
   │  crítico  │      │  warning   │
   │• Retorna  │      │• Retorna   │
   │  análise  │      │  análise   │
   │  padrão   │      │  padrão    │
   └───────────┘      └────────────┘
```

## 🚀 Como Executar

### 1. Rodar a Migration

```bash
php database/run_migrations.php
```

Ou acesse via navegador:
```
http://localhost/chat/database/run_migrations.php
```

### 2. Verificar Logs

Os logs estarão em:
```
storage/logs/kanban_agents.log
```

Busque por:
- `QUOTA DA OPENAI EXCEDIDA`
- `RATE LIMIT`
- `OpenAI API Error`

### 3. Monitorar Alertas

**Via SQL:**
```sql
SELECT * FROM system_alerts 
WHERE type = 'openai_quota_exceeded' 
AND is_resolved = FALSE 
ORDER BY created_at DESC;
```

**Via código:**
```php
$alerts = Database::fetchAll(
    "SELECT * FROM system_alerts 
     WHERE is_resolved = FALSE 
     ORDER BY created_at DESC"
);
```

## 📊 Tipos de Erro Tratados

| Erro | Código HTTP | Tipo | Tratamento |
|------|-------------|------|------------|
| **Quota Excedida** | 429 | `insufficient_quota` | ❌ Cria alerta crítico<br>✅ Retorna análise padrão<br>📝 Log detalhado |
| **Rate Limit** | 429 | `rate_limit_exceeded` | ⏸️ Aguarda 2s<br>✅ Retorna análise padrão<br>⚠️ Log warning |
| **API Key Inválida** | 401 | `invalid_api_key` | ❌ Exception<br>📝 Log error |
| **Erro de Rede** | - | cURL error | ❌ Exception<br>📝 Log error |
| **Timeout** | - | Timeout | ❌ Exception (após 30s)<br>📝 Log error |

## 🔍 Como Testar

### 1. Simular Quota Excedida (Desenvolvimento)

**Opção A: API Key inválida temporariamente**
```php
// Em Settings, altere temporariamente a API Key
Setting::set('openai_api_key', 'sk-invalida');
```

**Opção B: Forçar erro no código (temporário)**
```php
// Em callOpenAI(), após linha 742, adicione:
$httpCode = 429;
$response = json_encode([
    'error' => [
        'message' => 'You exceeded your current quota',
        'type' => 'insufficient_quota',
        'code' => 'insufficient_quota'
    ]
]);
```

### 2. Verificar Comportamento

1. ✅ Envie uma mensagem que acione um agente Kanban
2. ✅ Verifique que o sistema **não quebrou**
3. ✅ Verifique os logs em `storage/logs/kanban_agents.log`
4. ✅ Verifique se alerta foi criado em `system_alerts`
5. ✅ Verifique que a conversa continua funcionando

## 📝 Próximos Passos (Opcional)

### Interface de Alertas

Criar uma página de administração para visualizar e gerenciar alertas:

```php
// public/admin/system-alerts.php
<?php
require_once '../config/bootstrap.php';

$alerts = Database::fetchAll(
    "SELECT * FROM system_alerts 
     WHERE is_resolved = FALSE 
     ORDER BY severity DESC, created_at DESC"
);

// Renderizar interface com:
// - Badge de severidade (crítico, warning, info)
// - Título e mensagem
// - Botão para ação
// - Botão para marcar como resolvido
?>
```

### Notificação por Email

Enviar email quando quota for excedida:

```php
// Em createQuotaExceededAlert()
\App\Services\EmailService::sendToAdmins(
    'URGENTE: Quota da OpenAI Excedida',
    'A quota da API da OpenAI foi excedida...'
);
```

### Dashboard de Uso da OpenAI

Criar métrica de uso:
- Número de chamadas por dia
- Tokens consumidos
- Custo estimado
- Alerta quando atingir 80% da quota

## 🔗 Links Úteis

- [OpenAI Error Codes](https://platform.openai.com/docs/guides/error-codes/api-errors)
- [OpenAI Billing Dashboard](https://platform.openai.com/account/billing)
- [OpenAI Rate Limits](https://platform.openai.com/docs/guides/rate-limits)
- [OpenAI Usage Dashboard](https://platform.openai.com/account/usage)

## 📞 Suporte

Em caso de dúvidas ou problemas:

1. Verifique os logs em `storage/logs/kanban_agents.log`
2. Verifique os alertas em `system_alerts`
3. Acesse o [Billing da OpenAI](https://platform.openai.com/account/billing)
4. Verifique se o método de pagamento está ativo
5. Verifique se o plano permite o número de requests

---

**Data de Implementação:** 2026-01-21  
**Versão:** 1.0  
**Autor:** Sistema de Melhorias
