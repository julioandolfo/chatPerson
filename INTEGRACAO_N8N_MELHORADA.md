# 🔗 Integração N8N Melhorada - Disparo e Coleta

## 📋 Resumo

Sistema completo de integração com N8N que suporta:
- ✅ Diferentes métodos HTTP (GET, POST, PUT, DELETE, PATCH)
- ✅ Configuração flexível de webhooks
- ✅ Headers customizados
- ✅ Disparo de workflows
- ✅ Coleta de dados via API
- ✅ Suporte a webhooks de teste e produção

## 🛠️ Tools Disponíveis

### 1. Chamar Webhook N8N (`chamar_webhook_n8n`)

**Uso**: Disparar workflows N8N via webhook com suporte a diferentes métodos HTTP.

**Parâmetros**:
- `webhook_id` (string, obrigatório) - ID do webhook ou URL completa
- `method` (string, opcional) - Método HTTP: GET, POST, PUT, DELETE, PATCH (padrão: POST)
- `data` (object, opcional) - Dados para o body (POST, PUT, PATCH)
- `query_params` (object, opcional) - Parâmetros de query string
- `headers` (object, opcional) - Headers HTTP customizados

**Exemplos de Uso**:

```json
// Disparar workflow com POST (padrão)
{
  "webhook_id": "abc123",
  "method": "POST",
  "data": {
    "contact_id": 123,
    "message": "Olá!",
    "action": "send_email"
  }
}

// Coletar dados com GET
{
  "webhook_id": "abc123",
  "method": "GET",
  "query_params": {
    "contact_id": 123,
    "status": "active"
  }
}

// Usar URL completa
{
  "webhook_id": "https://n8n.exemplo.com/webhook-test/xyz789",
  "method": "POST",
  "data": {
    "test": true
  }
}
```

### 2. Consultar API N8N (`consultar_api_n8n`)

**Uso**: Consultar a API REST do N8N para buscar dados ou executar operações administrativas.

**Parâmetros**:
- `endpoint` (string, obrigatório) - Endpoint da API ou URL completa
- `method` (string, opcional) - Método HTTP (padrão: GET)
- `query_params` (object, opcional) - Parâmetros de query
- `data` (object, opcional) - Dados para o body
- `headers` (object, opcional) - Headers customizados

**Exemplos de Uso**:

```json
// Listar workflows
{
  "endpoint": "workflows",
  "method": "GET"
}

// Buscar execuções
{
  "endpoint": "executions",
  "method": "GET",
  "query_params": {
    "workflowId": 123,
    "limit": 10
  }
}

// Executar workflow via API
{
  "endpoint": "workflows/123/execute",
  "method": "POST",
  "data": {
    "data": {
      "contact_id": 456
    }
  }
}
```

### 3. Executar Workflow N8N (`executar_workflow_n8n`)

**Uso**: Compatibilidade com versão anterior. Executa workflow via webhook POST.

**Parâmetros**:
- `workflow_id` (string, obrigatório) - ID do workflow
- `data` (object, opcional) - Dados para enviar
- `method` (string, opcional) - Método HTTP (padrão: POST)

## ⚙️ Configuração da Tool

### Campos de Configuração

1. **n8n_url** (obrigatório)
   - URL base da instalação do N8N
   - Exemplo: `https://n8n.exemplo.com`

2. **webhook_id** (opcional)
   - ID padrão do webhook
   - Pode ser sobrescrito na chamada
   - Exemplo: `abc123`

3. **webhook_path** (opcional, padrão: `/webhook`)
   - Caminho base dos webhooks
   - Suporta: `/webhook`, `/webhook-test`, `/webhook-prod`
   - Exemplo: `/webhook-test`

4. **api_key** (opcional)
   - Chave de API do N8N para autenticação
   - Adiciona header `X-N8N-API-KEY`

5. **default_method** (opcional, padrão: `POST`)
   - Método HTTP padrão
   - Opções: GET, POST, PUT, DELETE, PATCH

6. **timeout** (opcional, padrão: `60`)
   - Timeout em segundos
   - Recomendado: 60 para webhooks, 30 para API

7. **custom_headers** (opcional)
   - Headers HTTP customizados em JSON
   - Exemplo: `{"X-Custom-Header": "value", "Authorization": "Bearer token"}`

### Exemplo de Configuração Completa

```json
{
  "n8n_url": "https://n8n.exemplo.com",
  "webhook_id": "abc123",
  "webhook_path": "/webhook",
  "api_key": "sua-api-key-aqui",
  "default_method": "POST",
  "timeout": 60,
  "custom_headers": {
    "X-Custom-Header": "value"
  }
}
```

## 🔄 Fluxo de Funcionamento

### Disparo de Workflow (POST)

```
1. Agente de IA recebe mensagem
   ↓
2. IA decide usar tool chamar_webhook_n8n
   ↓
3. Sistema monta URL: {n8n_url}{webhook_path}/{webhook_id}
   ↓
4. Envia requisição POST com dados
   ↓
5. N8N processa workflow
   ↓
6. Retorna resposta ao agente
   ↓
7. Agente responde ao contato
```

### Coleta de Dados (GET)

```
1. Agente precisa de informações
   ↓
2. IA usa tool chamar_webhook_n8n com method=GET
   ↓
3. Sistema monta URL com query params
   ↓
4. N8N retorna dados
   ↓
5. Agente usa dados na resposta
```

## 📝 Exemplos Práticos

### Exemplo 1: Disparar Email via N8N

**Configuração da Tool**:
```json
{
  "n8n_url": "https://n8n.exemplo.com",
  "webhook_id": "send-email",
  "default_method": "POST"
}
```

**Chamada da IA**:
```json
{
  "webhook_id": "send-email",
  "method": "POST",
  "data": {
    "to": "cliente@exemplo.com",
    "subject": "Bem-vindo!",
    "body": "Obrigado por entrar em contato!"
  }
}
```

### Exemplo 2: Buscar Status de Pedido

**Chamada da IA**:
```json
{
  "webhook_id": "check-order",
  "method": "GET",
  "query_params": {
    "order_id": "12345"
  }
}
```

### Exemplo 3: Atualizar Status (PUT)

**Chamada da IA**:
```json
{
  "webhook_id": "update-status",
  "method": "PUT",
  "data": {
    "order_id": "12345",
    "status": "shipped"
  }
}
```

### Exemplo 4: Usar Webhook de Teste

**Configuração**:
```json
{
  "n8n_url": "https://n8n.exemplo.com",
  "webhook_path": "/webhook-test"
}
```

**Chamada**:
```json
{
  "webhook_id": "test-workflow",
  "method": "POST",
  "data": {
    "test": true
  }
}
```

## 🔐 Segurança

### Autenticação

1. **API Key do N8N**
   - Configure `api_key` na tool
   - Header automático: `X-N8N-API-KEY`

2. **Headers Customizados**
   - Use `custom_headers` para autenticação adicional
   - Exemplo: `{"Authorization": "Bearer token"}`

3. **Webhooks Públicos vs Privados**
   - Webhooks públicos: não requerem autenticação
   - Webhooks privados: configure API key

### Validação

- URLs são validadas antes da requisição
- Métodos HTTP são validados (apenas GET, POST, PUT, DELETE, PATCH)
- Timeout previne requisições infinitas
- Headers são sanitizados

## 🐛 Troubleshooting

### Erro: "URL do N8N não configurada"
- **Solução**: Configure `n8n_url` na tool

### Erro: "ID do webhook não fornecido"
- **Solução**: Forneça `webhook_id` na chamada ou configure `webhook_id` padrão

### Erro: "Erro de conexão"
- **Solução**: Verifique se o N8N está acessível e a URL está correta

### Timeout
- **Solução**: Aumente `timeout` na configuração (padrão: 60s)

### Webhook não responde
- **Solução**: Verifique se o webhook está ativo no N8N e o caminho está correto

## 📊 Validação

Use o serviço de validação para verificar a configuração:

```bash
# Validar tool específica
php public/validate-ai-tools.php --tool-id=1

# Validar integração de agente
php public/validate-ai-tools.php --agent-id=1
```

## 🎯 Casos de Uso

### 1. Automação de Email
- Disparar emails personalizados via N8N
- Coletar templates de email

### 2. Integração com CRM
- Sincronizar dados de contatos
- Atualizar status de leads

### 3. Processamento de Dados
- Enviar dados para processamento
- Coletar resultados processados

### 4. Notificações
- Disparar notificações em outros sistemas
- Enviar alertas

### 5. Coleta de Informações
- Buscar dados de APIs externas
- Consultar bancos de dados remotos

## 📚 Referências

- [Documentação N8N Webhooks](https://docs.n8n.io/workflows/webhooks/)
- [N8N REST API](https://docs.n8n.io/api/)
- [OpenAI Function Calling](https://platform.openai.com/docs/guides/function-calling)

