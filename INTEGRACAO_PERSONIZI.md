# 🔗 Guia de Integração - Personizi

Este guia explica como configurar o **Personizi** (plugin WordPress) para conectar-se corretamente à API do sistema de chat.

---

## 🔍 Problema Identificado

O erro 404 "Página não encontrada" ocorre porque o Personizi está tentando acessar um endpoint que **não existia** na API. 

**Erro anterior:**
```
❌ Falha na conexão!
Status HTTP: 404
Resposta da API: {
  "success": false,
  "message": "Página não encontrada"
}
```

---

## ✅ Solução Implementada

Foi criado um novo endpoint na API REST para listar contas WhatsApp:

```
GET /api/v1/whatsapp-accounts
```

---

## 📋 Configuração no Personizi

### 1. Token de API

O Personizi precisa de um **token de API** válido. Para gerar:

1. Acesse o painel do sistema de chat
2. Vá em **Configurações > API & Tokens**
3. Clique em **Gerar Novo Token**
4. Copie o token gerado
5. Cole no campo **Token de API** do Personizi

### 2. URL da API

Configure a URL base da API no Personizi:

```
https://chat.personizi.com.br/api/v1
```

**⚠️ Importante:**
- Use HTTPS (não HTTP)
- **NÃO** adicione `/whatsapp-accounts` no final
- A URL deve terminar em `/api/v1`

### 3. Endpoints Disponíveis

O Personizi pode usar os seguintes endpoints:

#### Listar Contas WhatsApp
```
GET /api/v1/whatsapp-accounts
```

**Parâmetros opcionais:**
- `status`: `active`, `inactive` ou `disconnected`
- `page`: Número da página (padrão: 1)
- `per_page`: Itens por página (padrão: 20)

**Exemplo de resposta:**
```json
{
  "success": true,
  "data": {
    "accounts": [
      {
        "id": 1,
        "name": "WhatsApp Principal",
        "phone_number": "5511916127354",
        "provider": "quepasa",
        "api_url": "https://whats.seudominio.com",
        "status": "active",
        "default_funnel_id": 1,
        "default_stage_id": 3,
        "default_funnel_name": "Vendas",
        "default_stage_name": "Novo Lead",
        "created_at": "2025-01-15 10:30:00",
        "updated_at": "2025-02-01 14:20:00"
      }
    ],
    "pagination": {
      "total": 1,
      "page": 1,
      "per_page": 20,
      "total_pages": 1,
      "has_next": false,
      "has_prev": false
    }
  }
}
```

#### Obter Conta Específica
```
GET /api/v1/whatsapp-accounts/:id
```

**Exemplo:**
```
GET /api/v1/whatsapp-accounts/1
```

---

## 🧪 Testar Conexão

### Usando cURL

```bash
curl -X GET "https://chat.personizi.com.br/api/v1/whatsapp-accounts" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI" \
  -H "Accept: application/json"
```

### Usando Postman

1. **Método:** GET
2. **URL:** `https://chat.personizi.com.br/api/v1/whatsapp-accounts`
3. **Headers:**
   - `Authorization`: `Bearer SEU_TOKEN_AQUI`
   - `Accept`: `application/json`

### Resposta Esperada

✅ **Sucesso (200 OK):**
```json
{
  "success": true,
  "data": {
    "accounts": [...],
    "pagination": {...}
  }
}
```

❌ **Erro de Autenticação (401):**
```json
{
  "success": false,
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Token inválido ou expirado"
  }
}
```

---

## 🔒 Segurança

### Boas Práticas

1. **Mantenha o token seguro**
   - Não compartilhe o token
   - Não exponha em código público
   - Use variáveis de ambiente

2. **Use HTTPS**
   - Sempre use conexão segura (https://)
   - Verifique o certificado SSL

3. **Revogue tokens comprometidos**
   - Se suspeitar de vazamento, revogue o token
   - Gere um novo token imediatamente

4. **Monitore o uso**
   - Acompanhe os logs de API
   - Verifique requisições suspeitas

---

## 🚦 Rate Limiting

A API possui limite de requisições:

- **Padrão**: 100 requisições/minuto por token
- **Headers de resposta:**
  - `X-RateLimit-Limit`: Limite total
  - `X-RateLimit-Remaining`: Requisições restantes
  - `X-RateLimit-Reset`: Timestamp do reset

**Quando exceder:**
```
HTTP/1.1 429 Too Many Requests
Retry-After: 45

{
  "success": false,
  "error": {
    "code": "TOO_MANY_REQUESTS",
    "message": "Limite de 100 requisições por minuto excedido"
  }
}
```

---

## 🐛 Troubleshooting

### Erro 404 - Not Found

**Causa:** URL incorreta ou endpoint não existe

**Solução:**
- Verifique se a URL está correta: `https://chat.personizi.com.br/api/v1/whatsapp-accounts`
- Confirme que você aplicou as correções descritas neste guia

### Erro 401 - Unauthorized

**Causa:** Token inválido, expirado ou ausente

**Solução:**
- Verifique se o token está correto
- Gere um novo token no painel
- Confirme que está enviando o header: `Authorization: Bearer TOKEN`

### Erro 403 - Forbidden

**Causa:** Usuário não tem permissão para acessar este recurso

**Solução:**
- Verifique as permissões do usuário associado ao token
- Usuário deve ter permissão `whatsapp.view`

### Erro 429 - Too Many Requests

**Causa:** Limite de requisições excedido

**Solução:**
- Aguarde o tempo indicado em `Retry-After`
- Implemente cache no Personizi
- Considere aumentar o limite do token

### Erro 500 - Server Error

**Causa:** Erro interno no servidor

**Solução:**
- Verifique os logs do servidor: `logs/app.log`
- Entre em contato com o suporte

---

## 📊 Monitoramento

### Ver Logs de API

1. Acesse o painel do sistema
2. Vá em **Configurações > API & Tokens > Logs**
3. Filtre por token do Personizi
4. Analise requisições e respostas

### Informações nos Logs

- Endpoint acessado
- Método HTTP
- Request e Response
- Tempo de execução
- IP de origem
- Status HTTP

---

## 📝 Checklist de Configuração

Use este checklist para garantir que tudo está configurado corretamente:

- [ ] Token de API gerado no painel
- [ ] Token copiado e colado no Personizi
- [ ] URL da API configurada: `https://chat.personizi.com.br/api/v1`
- [ ] HTTPS habilitado (não HTTP)
- [ ] Teste de conexão realizado com sucesso
- [ ] Contas WhatsApp aparecem no Personizi
- [ ] Logs da API sem erros
- [ ] Rate limiting adequado

---

## 💡 Exemplo Completo de Integração

```php
<?php
// Exemplo de código PHP para integrar com a API

$apiBaseUrl = 'https://chat.personizi.com.br/api/v1';
$apiToken = 'SEU_TOKEN_AQUI';

// Função para fazer requisição à API
function callAPI($endpoint, $method = 'GET', $data = null) {
    global $apiBaseUrl, $apiToken;
    
    $url = $apiBaseUrl . $endpoint;
    
    $args = [
        'method' => $method,
        'headers' => [
            'Authorization' => 'Bearer ' . $apiToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]
    ];
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $args['body'] = json_encode($data);
    }
    
    $response = wp_remote_request($url, $args);
    
    if (is_wp_error($response)) {
        return [
            'success' => false,
            'error' => $response->get_error_message()
        ];
    }
    
    $statusCode = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    return [
        'success' => $statusCode >= 200 && $statusCode < 300,
        'status' => $statusCode,
        'data' => $data
    ];
}

// Listar contas WhatsApp
$result = callAPI('/whatsapp-accounts?status=active');

if ($result['success']) {
    $accounts = $result['data']['data']['accounts'];
    foreach ($accounts as $account) {
        echo "Conta: {$account['name']} ({$account['phone_number']})\n";
    }
} else {
    echo "Erro: " . $result['error'] . "\n";
}
```

---

## 🎉 Pronto!

Após seguir este guia, o Personizi deve estar conectado corretamente à API do sistema de chat.

**Dúvidas ou problemas?**
- Consulte os logs da API
- Verifique este guia novamente
- Entre em contato com o suporte técnico

---

**Última atualização:** 01/02/2025
**Versão da API:** v1
**Versão do guia:** 1.0
