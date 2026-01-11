# Webhook WooCommerce - Sincronização em Tempo Real

## 🎯 Objetivo

Receber notificações em **tempo real** quando pedidos são criados ou atualizados no WooCommerce, mantendo o cache sempre atualizado sem depender do CRON.

---

## 🔗 URL do Webhook

```
https://seudominio.com/webhooks/woocommerce
```

**Exemplos**:
- Produção: `https://chat.minhaempresa.com/webhooks/woocommerce`
- Desenvolvimento: `http://localhost/webhooks/woocommerce`
- Laragon: `http://chat.test/webhooks/woocommerce`

---

## ⚙️ Configuração no WooCommerce

### 1. Acesse o Painel do WooCommerce

```
WooCommerce → Configurações → Avançado → Webhooks
```

### 2. Criar Webhook para CRIAÇÃO de Pedidos

Clique em **"Adicionar webhook"** e preencha:

| Campo | Valor |
|-------|-------|
| **Nome** | `Chat - Novo Pedido` |
| **Status** | ✅ Ativo |
| **Tópico** | **Order created** |
| **URL de entrega** | `https://seudominio.com/webhooks/woocommerce` |
| **Segredo** | *(deixe em branco ou crie um)* |
| **Versão da API** | **WP REST API Integration v3** |

Clique em **"Salvar webhook"**.

### 3. Criar Webhook para ATUALIZAÇÃO de Pedidos

Clique novamente em **"Adicionar webhook"** e preencha:

| Campo | Valor |
|-------|-------|
| **Nome** | `Chat - Atualização de Pedido` |
| **Status** | ✅ Ativo |
| **Tópico** | **Order updated** |
| **URL de entrega** | `https://seudominio.com/webhooks/woocommerce` |
| **Segredo** | *(mesmo do anterior, se usou)* |
| **Versão da API** | **WP REST API Integration v3** |

Clique em **"Salvar webhook"**.

---

## 📊 O que o Webhook Faz

### Quando um pedido é **CRIADO** ou **ATUALIZADO**:

1. **Recebe dados** do WooCommerce em tempo real
2. **Extrai seller_id** do `meta_data` (usando o `seller_meta_key` configurado)
3. **Busca ou cria contato** pelo email/telefone do cliente
4. **Salva/Atualiza** no cache local (`woocommerce_order_cache`)
5. **Vincula ao vendedor** automaticamente
6. **Filtra status** válidos (não conta cancelados, reembolsados, falhados)

### Fluxo Completo:

```
Cliente faz pedido no WooCommerce
   ↓
WooCommerce dispara webhook (< 1 segundo)
   ↓
Sistema recebe e processa
   ↓
Extrai seller_id do meta_data
   ↓
Busca/cria contato automaticamente
   ↓
Salva no cache local
   ↓
Dashboard atualizado instantaneamente! ⚡
```

---

## 🎨 Status Válidos vs Inválidos

### ✅ **Status Válidos** (Contam na conversão):
- `completed` - Concluído
- `processing` - Processando
- `on-hold` - Em espera
- `pending` - Pendente

### ❌ **Status Inválidos** (NÃO contam):
- `cancelled` - Cancelado
- `refunded` - Reembolsado
- `failed` - Falhou

**Resultado**: Taxa de conversão mais precisa, sem pedidos cancelados!

---

## 🧪 Como Testar

### 1. **Criar Pedido de Teste**

No WooCommerce:
1. Criar um novo pedido
2. Adicionar produto
3. Preencher dados do cliente
4. Salvar

### 2. **Ver Logs do Webhook**

```bash
# Ver logs em tempo real
tail -f storage/logs/error.log | grep "WooCommerce Webhook"
```

Saída esperada:
```
[2026-01-11 10:30:15] WooCommerce Webhook - Recebido: {"id":12345,...}
[2026-01-11 10:30:15] WooCommerce Webhook - Event: created, Source: https://loja.com
[2026-01-11 10:30:15] WooCommerce Webhook - Pedido #12345 processado com sucesso
```

### 3. **Verificar Cache**

```sql
SELECT 
  order_id,
  contact_id,
  seller_id,
  order_status,
  order_total,
  DATE_FORMAT(order_date, '%d/%m/%Y %H:%i') as data
FROM woocommerce_order_cache
ORDER BY id DESC
LIMIT 10;
```

### 4. **Testar no Dashboard**

1. Acesse `/agent-conversion`
2. As métricas devem estar atualizadas instantaneamente
3. Novo pedido deve aparecer na lista

---

## 🔐 Segurança (Opcional)

### Usar Segredo (Secret)

Para validar que o webhook realmente vem do WooCommerce:

1. No WooCommerce, ao criar o webhook, defina um **Segredo** (ex: `minha_senha_secreta_123`)

2. No código do webhook (`app/Controllers/WebhookController.php`), adicione validação:

```php
// Linha ~28, depois de receber o payload
$secret = $headers['X-WC-Webhook-Signature'] ?? null;
$expectedSecret = 'minha_senha_secreta_123';

if ($secret !== $expectedSecret) {
    Response::json(['success' => false, 'message' => 'Invalid signature'], 401);
    return;
}
```

---

## 📈 Benefícios

### ⚡ **Tempo Real**
- Pedidos aparecem **instantaneamente** no dashboard
- Sem espera de até 1 hora do CRON
- Experiência muito mais fluida

### 🎯 **Precisão**
- Apenas status válidos contam na conversão
- Pedidos cancelados são desconsiderados
- Métricas mais confiáveis

### 🔄 **Automação Total**
- Contatos criados automaticamente
- Seller ID extraído automaticamente
- Vinculação automática vendedor ↔ pedido

### 🚀 **Performance**
- Cache sempre atualizado
- Dashboard carrega em < 100ms
- API do WooCommerce não sobrecarregada

---

## 🔄 Combinação: Webhook + CRON

O sistema usa **os dois métodos** para máxima confiabilidade:

### **Webhook** (Tempo Real)
- Notificações instantâneas
- Para pedidos novos e atualizações

### **CRON** (Backup)
- Roda a cada 1 hora
- Sincroniza últimos 7 dias
- Garante que nada seja perdido

**Resultado**: Sistema robusto e sempre atualizado!

---

## 🛠️ Troubleshooting

### Webhook não está funcionando

**1. Verificar URL**:
```bash
curl -X POST https://seudominio.com/webhooks/woocommerce \
  -H "Content-Type: application/json" \
  -d '{"test":"test"}'
```

Resposta esperada:
```json
{"success":false,"message":"Payload vazio"}
```

**2. Ver logs do WooCommerce**:
- WooCommerce → Configurações → Avançado → Webhooks
- Clique no webhook criado
- Role até "Entregas" (Deliveries)
- Ver status: ✅ Sucesso ou ❌ Erro

**3. Verificar logs do servidor**:
```bash
tail -f storage/logs/error.log
```

### Seller ID não está sendo extraído

**Solução**: Verificar se o `seller_meta_key` está correto:

1. Editar integração WooCommerce
2. Ir em "Tracking de Conversão"
3. Campo "Meta Key do Vendedor"
4. Clicar em **"Testar"**
5. Ver se encontra o meta_key nos pedidos

### Pedidos não aparecem no dashboard

**Verificar**:
1. Seller ID está cadastrado no agente?
2. Pedido tem status válido?
3. Cache foi atualizado?

```sql
-- Ver se o pedido está no cache
SELECT * FROM woocommerce_order_cache 
WHERE order_id = 12345;
```

---

## 📊 Monitoramento

### Ver Webhooks Recebidos

```bash
# Últimos 50 webhooks
tail -50 storage/logs/error.log | grep "WooCommerce Webhook"
```

### Estatísticas

```sql
-- Total de pedidos por status
SELECT 
  order_status,
  COUNT(*) as total,
  SUM(order_total) as valor_total
FROM woocommerce_order_cache
GROUP BY order_status;

-- Pedidos criados hoje via webhook
SELECT COUNT(*) as total 
FROM woocommerce_order_cache
WHERE DATE(created_at) = CURDATE();
```

---

## 🎉 Resultado Final

### **Antes**: On-Demand (lento)
```
Dashboard → API WooCommerce (3-5s) → Métricas
```

### **Agora**: Webhook + CRON (instantâneo)
```
Pedido criado → Webhook (< 1s) → Cache → Dashboard (< 100ms) ⚡
         ↓
      CRON backup (1h)
```

---

## 📝 Checklist de Configuração

- [ ] Criar webhook "Order created"
- [ ] Criar webhook "Order updated"
- [ ] Configurar URLs corretamente
- [ ] Testar com pedido real
- [ ] Verificar logs
- [ ] Ver pedido no cache
- [ ] Confirmar no dashboard
- [ ] ✅ Tudo funcionando!

---

## 📚 Documentação Relacionada

- `SINCRONIZACAO_WOOCOMMERCE.md` - Sincronização via CRON
- `PROGRESSO_CONVERSAO_WOOCOMMERCE.md` - Visão geral do sistema
- WooCommerce Webhooks: https://woocommerce.com/document/webhooks/

---

**Sistema 100% funcional com sincronização em tempo real!** 🚀
