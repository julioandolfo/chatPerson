# Webhooks com Payload Customizado

## Visão Geral

O sistema de webhooks do Person Cash Wallet agora suporta payloads completamente customizáveis, permitindo que você configure exatamente quais dados e em qual formato serão enviados para sistemas externos.

## Funcionalidades

- ✅ Editor JSON visual com validação em tempo real
- ✅ 50+ variáveis dinâmicas disponíveis
- ✅ Validação automática de sintaxe JSON
- ✅ Formatação automática de JSON
- ✅ Copiar variáveis com um clique
- ✅ Exemplos prontos para WhatsApp, CRM e outras integrações
- ✅ Atalhos de teclado para maior produtividade

## Variáveis Disponíveis

### Dados do Usuário
- `{{user_id}}` - ID do usuário
- `{{user_name}}` - Nome completo do usuário
- `{{user_first_name}}` - Primeiro nome
- `{{user_last_name}}` - Sobrenome
- `{{user_email}}` - E-mail
- `{{user_phone}}` - Telefone
- `{{user_cpf}}` - CPF
- `{{user_role}}` - Função do usuário
- `{{user_display_name}}` - Nome de exibição

### Wallet/Carteira
- `{{wallet_balance}}` - Saldo atual da wallet
- `{{wallet_total_earned}}` - Total ganho
- `{{wallet_total_spent}}` - Total gasto
- `{{transaction_id}}` - ID da transação
- `{{transaction_type}}` - Tipo da transação (credit/debit)
- `{{transaction_amount}}` - Valor da transação
- `{{transaction_source}}` - Origem da transação
- `{{transaction_description}}` - Descrição da transação

### Cashback
- `{{cashback_id}}` - ID do cashback
- `{{cashback_amount}}` - Valor do cashback
- `{{cashback_status}}` - Status do cashback
- `{{cashback_earned_date}}` - Data que ganhou
- `{{cashback_expires_date}}` - Data de expiração
- `{{cashback_used_date}}` - Data de uso
- `{{cashback_rule_name}}` - Nome da regra aplicada

### Níveis
- `{{level_id}}` - ID do nível
- `{{level_name}}` - Nome do nível
- `{{level_number}}` - Número do nível
- `{{level_description}}` - Descrição do nível
- `{{level_achieved_date}}` - Data de conquista
- `{{level_expires_date}}` - Data de expiração
- `{{previous_level_name}}` - Nome do nível anterior

### Pedido
- `{{order_id}}` - ID do pedido
- `{{order_number}}` - Número do pedido
- `{{order_total}}` - Valor total formatado (R$ 149,90)
- `{{order_total_raw}}` - Valor total numérico (149.90)
- `{{order_subtotal}}` - Subtotal formatado
- `{{order_subtotal_raw}}` - Subtotal numérico
- `{{order_discount}}` - Desconto formatado
- `{{order_shipping}}` - Frete formatado
- `{{order_status}}` - Status do pedido (processing, completed, etc)
- `{{order_status_label}}` - Status formatado (Pagamento Aprovado, etc)
- `{{order_date}}` - Data do pedido (dd/mm/yyyy HH:ii)
- `{{order_date_short}}` - Data curta (dd/mm/yyyy)
- `{{payment_method}}` - Método de pagamento
- `{{products_list}}` - Lista de produtos com quantidade e preço
- `{{coupons}}` - Cupons utilizados no pedido
- `{{order_notes}}` - Observações do cliente
- `{{budget_notes}}` - Observações do orçamento

### Pagamento (WC Advanced Manual Orders)
- `{{payment_link}}` - Link de pagamento com auto-login
- `{{payment_url}}` - Alias para payment_link

### Envio e Rastreio
- `{{shipping_method}}` - Método de envio selecionado
- `{{shipping_address}}` - Endereço de entrega formatado
- `{{tracking_code}}` - Código de rastreio principal
- `{{tracking_codes}}` - Todos os códigos de rastreio (separados por vírgula)
- `{{tracking_url}}` - URL de rastreio principal
- `{{tracking_urls}}` - Todas as URLs de rastreio

**Plugins suportados para rastreio:**
- Melhor Envio
- WooCommerce Correios
- WooCommerce Shipment Tracking
- Advanced Shipment Tracking
- Jadlog
- Frenet

### Datas de Produção/Entrega
- `{{departure_date}}` - Data de saída/produção
- `{{delivery_date}}` - Data de entrega prevista

### Evento
- `{{event_type}}` - Tipo do evento
- `{{event_timestamp}}` - Timestamp do evento
- `{{event_date}}` - Data do evento
- `{{event_time}}` - Hora do evento

### Site
- `{{site_name}}` - Nome do site
- `{{site_url}}` - URL do site
- `{{admin_email}}` - E-mail do admin

## Exemplos de Uso

### Exemplo 1: Integração com WhatsApp - Confirmação de Pedido

```json
{
  "phone": "{{customer_phone}}",
  "message": "Olá {{customer_first_name}}! 🎉\n\nSeu pedido #{{order_number}} foi confirmado!\n\n💰 Total: {{order_total}}\n💳 Pagamento: {{payment_method}}\n\nLink para pagamento: {{payment_link}}\n\n{{site_name}}",
  "template": "order_confirmed"
}
```

### Exemplo 2: WhatsApp - Pedido Enviado com Rastreio

```json
{
  "phone": "{{customer_phone}}",
  "message": "Olá {{customer_first_name}}! 🚚\n\nSeu pedido #{{order_number}} foi enviado!\n\n📦 Código de rastreio: {{tracking_code}}\n🔗 Rastreie aqui: {{tracking_url}}\n\n📅 Previsão de entrega: {{delivery_date}}\n\n{{site_name}}",
  "template": "order_shipped"
}
```

### Exemplo 3: WhatsApp - Cashback Ganho

```json
{
  "phone": "{{user_phone}}",
  "message": "Olá {{user_first_name}}! Você ganhou R$ {{cashback_amount}} de cashback! 🎉",
  "template": "cashback_earned"
}
```

### Exemplo 2: Atualizar CRM

```json
{
  "contact": {
    "email": "{{user_email}}",
    "name": "{{user_name}}",
    "phone": "{{user_phone}}",
    "custom_fields": {
      "wallet_balance": "{{wallet_balance}}",
      "total_earned": "{{wallet_total_earned}}",
      "customer_level": "{{level_name}}",
      "last_order": "{{order_number}}"
    }
  },
  "event_type": "{{event_type}}",
  "timestamp": "{{event_timestamp}}"
}
```

### Exemplo 3: Slack/Discord Notification

```json
{
  "text": "Novo evento: {{event_type}}",
  "blocks": [
    {
      "type": "section",
      "text": {
        "type": "mrkdwn",
        "text": "*Cliente:* {{user_name}}\n*Email:* {{user_email}}\n*Telefone:* {{user_phone}}"
      }
    },
    {
      "type": "section",
      "text": {
        "type": "mrkdwn",
        "text": "*Saldo:* R$ {{wallet_balance}}\n*Cashback:* R$ {{cashback_amount}}\n*Nível:* {{level_name}}"
      }
    }
  ]
}
```

### Exemplo 4: Zapier/Make.com

```json
{
  "trigger": "{{event_type}}",
  "customer": {
    "id": "{{user_id}}",
    "name": "{{user_name}}",
    "email": "{{user_email}}",
    "phone": "{{user_phone}}",
    "cpf": "{{user_cpf}}"
  },
  "wallet": {
    "balance": "{{wallet_balance}}",
    "total_earned": "{{wallet_total_earned}}",
    "total_spent": "{{wallet_total_spent}}"
  },
  "order": {
    "id": "{{order_id}}",
    "number": "{{order_number}}",
    "total": "{{order_total}}",
    "status": "{{order_status}}"
  },
  "site": {
    "name": "{{site_name}}",
    "url": "{{site_url}}"
  },
  "timestamp": "{{event_timestamp}}"
}
```

### Exemplo 5: API Personalizada

```json
{
  "action": "customer_update",
  "source": "person_cash_wallet",
  "event": "{{event_type}}",
  "data": {
    "customer": {
      "external_id": "{{user_id}}",
      "full_name": "{{user_name}}",
      "first_name": "{{user_first_name}}",
      "last_name": "{{user_last_name}}",
      "email": "{{user_email}}",
      "mobile": "{{user_phone}}",
      "document": "{{user_cpf}}"
    },
    "loyalty": {
      "points_balance": "{{wallet_balance}}",
      "lifetime_points": "{{wallet_total_earned}}",
      "tier": "{{level_name}}",
      "tier_number": "{{level_number}}"
    },
    "metadata": {
      "last_order_id": "{{order_id}}",
      "last_order_value": "{{order_total}}",
      "cashback_earned": "{{cashback_amount}}",
      "event_date": "{{event_date}}",
      "event_time": "{{event_time}}"
    }
  }
}
```

## Como Usar

### 1. Acessar a Página de Webhooks
Vá em **Person Cash Wallet → Webhooks** no menu do WordPress.

### 2. Criar ou Editar um Webhook
Clique em "Novo Webhook" ou edite um existente.

### 3. Configurar o Payload Customizado
Na seção "Payload Customizado", você encontrará:
- Um editor JSON com syntax highlighting
- Lista de variáveis disponíveis organizadas por categoria
- Exemplos prontos para copiar

### 4. Usar as Variáveis
- Clique em qualquer variável para copiá-la
- Cole no editor usando Ctrl+V (ou Cmd+V no Mac)
- As variáveis devem estar no formato `{{nome_da_variavel}}`

### 5. Validar o JSON
- Clique em "Validar JSON" para verificar se está correto
- Use "Formatar JSON" para indentar automaticamente
- Use "Restaurar Padrão" para voltar ao template inicial

### 6. Salvar e Testar
- Salve o webhook
- Use o botão "Testar Webhook" para enviar um teste

## Atalhos de Teclado

- `Ctrl+S` (ou `Cmd+S`) - Validar JSON
- `Ctrl+Shift+F` (ou `Cmd+Shift+F`) - Formatar JSON
- `Tab` - Inserir 2 espaços (indentação)

## Segurança

- O payload é validado antes de salvar
- As variáveis são substituídas no momento do envio
- A assinatura HMAC SHA256 continua sendo gerada automaticamente
- Todos os dados são sanitizados antes do envio

## Boas Práticas

1. **Sempre valide o JSON** antes de salvar
2. **Teste o webhook** após configurar
3. **Use variáveis relevantes** para o evento configurado
4. **Documente o formato** esperado pelo sistema de destino
5. **Monitore os logs** para verificar se os envios estão funcionando

## Troubleshooting

### JSON Inválido
Se o payload não passar na validação:
- Verifique vírgulas ausentes ou extras
- Certifique-se de fechar todas as chaves `{` e colchetes `[`
- Use o botão "Formatar JSON" para visualizar melhor

### Variáveis Não Substituídas
Se as variáveis aparecerem como `{{variable}}` no destino:
- Verifique se o nome da variável está correto
- Certifique-se de que os dados estão disponíveis para o evento
- Consulte os logs do webhook para ver o payload final

### Webhook Não Enviado
- Verifique se o webhook está ativo
- Confirme se a URL de destino está correta
- Verifique os logs para ver mensagens de erro

## Payload Padrão

Se não configurar um payload customizado, o formato padrão será usado:

```json
{
  "event": "{{event_type}}",
  "timestamp": "{{event_timestamp}}",
  "user": {
    "id": "{{user_id}}",
    "name": "{{user_name}}",
    "email": "{{user_email}}",
    "phone": "{{user_phone}}"
  },
  "data": {
    // Dados específicos do evento
  }
}
```

## Suporte

Para dúvidas ou problemas:
1. Verifique esta documentação
2. Consulte os logs dos webhooks
3. Entre em contato com o suporte técnico

## Changelog

### Versão 1.3.2
- ✨ Adicionado suporte a payload customizado
- ✨ 50+ variáveis dinâmicas disponíveis
- ✨ Editor JSON com validação em tempo real
- ✨ Exemplos práticos de integração
- ✨ Atalhos de teclado para produtividade
