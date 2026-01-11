# Logs do Webhook WooCommerce - Implementado

## 📋 Resumo

Sistema de logging detalhado para o webhook do WooCommerce, com visualização integrada no painel de logs do sistema.

---

## ✅ O que foi Implementado

### 1. **Sistema de Log Dedicado no WebhookController**

Adicionado método `log()` privado que:
- Cria logs no arquivo `logs/webhook.log`
- Também registra no `error_log` padrão do PHP
- Formato: `[YYYY-MM-DD HH:MM:SS] [LEVEL] mensagem`
- Níveis: `INFO`, `SUCCESS`, `WARNING`, `ERROR`

### 2. **Logs Detalhados no Processamento do Webhook**

**Quando um webhook é recebido:**
- `=== WEBHOOK RECEBIDO ===`
- Tamanho do payload em bytes
- Event type (created/updated)
- Source (URL da loja)
- Order ID

**Durante processamento:**
- Busca de integração (por source ou padrão)
- Dados do pedido (status, total)
- Seller ID extraído do meta_data (ou aviso se não encontrado)
- Busca/criação de contato (email, telefone)
- Cache do pedido (criado ou atualizado)

**Resultado final:**
- ✅ Sucesso com detalhes JSON
- ❌ Erro com mensagem e stack trace

### 3. **Visualização no Painel de Logs**

**Arquivo: `public/view-all-logs.php`**

Adicionado:
- Item "Webhook WooCommerce" na lista de logs
- Botão de navegação rápida "🔗 Webhook" (verde)
- Exibição das últimas 100 linhas do log
- Destaque de cores:
  - 🔴 Vermelho: Erros
  - 🟢 Verde: Sucessos
  - 🟡 Amarelo: Warnings
  - 🔵 Azul: Info

---

## 📊 Exemplo de Log

```
[2026-01-11 14:39:25] [INFO] === WEBHOOK RECEBIDO ===
[2026-01-11 14:39:25] [INFO] Payload size: 2543 bytes
[2026-01-11 14:39:25] [INFO] Event: created | Source: https://loja.com.br | Order ID: 12345
[2026-01-11 14:39:25] [INFO] Buscando integração para source: https://loja.com.br
[2026-01-11 14:39:25] [INFO] ✓ Integração encontrada: #1 - Minha Loja
[2026-01-11 14:39:25] [INFO] Pedido #12345: Status=processing, Total=150.00
[2026-01-11 14:39:25] [INFO] ✓ Seller ID encontrado: 42 (meta_key: _vendor_id)
[2026-01-11 14:39:25] [INFO] Buscando contato: email=cliente@email.com, phone=11999999999
[2026-01-11 14:39:25] [INFO] ✓ Contato existente: ID=123
[2026-01-11 14:39:25] [INFO] ✓ Pedido criado no cache (cache_id: 456)
[2026-01-11 14:39:25] [SUCCESS] ✅ Pedido #12345 processado com sucesso: {"action":"created","integration_id":1,"contact_id":123,"order_id":12345,"seller_id":42,"status":"processing"}
```

---

## 🔧 Arquivos Modificados

### 1. `app/Controllers/WebhookController.php`

**Adicionado:**
```php
private static function log(string $message, string $level = 'INFO'): void
{
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/webhook.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    error_log($logMessage);
}
```

**Logs adicionados em:**
- Recebimento do webhook
- Validação de payload
- Identificação da integração
- Extração de dados do pedido
- Busca/criação de contato
- Cache do pedido
- Erros e exceções

### 2. `public/view-all-logs.php`

**Adicionado na lista de logs:**
```php
'Webhook WooCommerce' => __DIR__ . '/../logs/webhook.log',
```

**Adicionado botão de navegação:**
```html
<button class="nav-btn" onclick="document.getElementById('webhook-woocommerce-log').scrollIntoView({behavior: 'smooth'})" style="background: #4caf50">🔗 Webhook</button>
```

### 3. `logs/webhook.log`

Arquivo criado e pronto para receber logs.

---

## 🚀 Como Usar

### Visualizar Logs em Tempo Real

1. Acesse: `https://seudominio.com/view-all-logs.php`
2. Clique no botão "🔗 Webhook" (verde)
3. Ou role até a seção "Webhook WooCommerce"

### Testar o Webhook

1. Configure o webhook no WooCommerce
2. Crie ou atualize um pedido de teste
3. Verifique o log em `/view-all-logs.php`
4. Analise cada etapa do processamento

### Debug de Problemas

**Se pedidos não estão sendo processados:**
1. Verifique se o webhook foi recebido (linha `=== WEBHOOK RECEBIDO ===`)
2. Verifique se há erro de integração
3. Verifique se o seller_id foi encontrado (ou se há warning)
4. Verifique se o contato foi criado/encontrado
5. Verifique se o cache foi salvo

---

## 📝 Níveis de Log

| Nível | Uso | Exemplo |
|-------|-----|---------|
| `INFO` | Informações gerais | Webhook recebido, buscando integração |
| `SUCCESS` | Operação bem-sucedida | Pedido processado com sucesso |
| `WARNING` | Aviso não crítico | Seller ID não encontrado |
| `ERROR` | Erro crítico | Integração não encontrada, exceções |

---

## 🎨 Cores no Visualizador

- **Vermelho**: Linhas com "erro", "error", "exception"
- **Verde**: Linhas com "sucesso", "success", "✅"
- **Amarelo**: Linhas com "warning", "⚠️"
- **Azul**: Demais linhas (info)
- **Verde escuro**: Timestamps `[YYYY-MM-DD HH:MM:SS]`

---

## 🔍 Informações Registradas

### Por Webhook Recebido
- Data/hora exata
- Tamanho do payload
- Event type (created/updated/etc)
- Source (URL da loja)
- Order ID

### Durante Processamento
- Integração identificada (ID e nome)
- Status e total do pedido
- Seller ID (se encontrado)
- Email e telefone do cliente
- Contact ID (novo ou existente)
- Cache ID (novo ou atualizado)

### Em Caso de Erro
- Mensagem de erro detalhada
- Stack trace completo
- Contexto do erro (em qual etapa ocorreu)

---

## ✅ Benefícios

1. **Debug Facilitado**: Identifique rapidamente problemas no processamento
2. **Auditoria**: Histórico completo de webhooks recebidos
3. **Monitoramento**: Acompanhe a saúde da integração
4. **Rastreabilidade**: Vincule pedidos a contatos e vendedores
5. **Performance**: Identifique gargalos no processamento

---

## 🎯 Próximos Passos

- [ ] Adicionar rotação automática de logs (arquivar logs antigos)
- [ ] Criar dashboard de estatísticas de webhooks
- [ ] Implementar alertas para falhas consecutivas
- [ ] Adicionar validação de assinatura do webhook (segurança)
- [ ] Criar API para consultar logs via interface

---

**Data:** 11/01/2026  
**Arquivo de Log:** `logs/webhook.log`  
**Visualizador:** `public/view-all-logs.php`  
**Status:** ✅ Completo e Testado
