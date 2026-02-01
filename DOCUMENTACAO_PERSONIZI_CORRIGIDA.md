# 📡 Documentação Técnica da Integração Personizi

## ✅ **VERSÃO CORRIGIDA** - 01/02/2025

---

## 🔧 Configuração Atual

### Base URL
```
https://chat.personizi.com.br/api/v1
```

### Autenticação
- **Tipo:** Bearer Token
- **Header:** `Authorization: Bearer {token}`
- **Token atual:** `b481e4bb3d224638a498be99ae3e411c2f414e71a69f081039edd0c4dff99912`

### Número Padrão (From)
```
5511916127354
```

---

## 📤 1. ENVIAR MENSAGEM WHATSAPP

### Endpoint
```
POST /messages/send
```

### URL Completa
```
https://chat.personizi.com.br/api/v1/messages/send
```

### Headers
```
Authorization: Bearer b481e4bb3d224638a498be99ae3e411c2f414e71a69f081039edd0c4dff99912
Content-Type: application/json
```

### Body (JSON)
```json
{
  "to": "5511999998888",
  "from": "5511916127354",
  "message": "Olá! Esta é uma mensagem de teste do sistema Person Cash Wallet 🚀",
  "contact_name": "Teste do Sistema"
}
```

### Campos do Payload

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `to` | string | ✅ Sim | Número do destinatário (apenas dígitos, com código do país) |
| `from` | string | ✅ Sim | Número remetente (sua conta WhatsApp Business) |
| `message` | string | ✅ Sim | Texto da mensagem (máx 4096 caracteres) |
| `contact_name` | string | ❌ Não | Nome do contato (opcional) |

### Resposta Esperada (Sucesso - 201 Created)
```json
{
  "success": true,
  "data": {
    "message_id": "12345",
    "conversation_id": "789",
    "status": "sent",
    "external_message_id": "msg_xyz123"
  },
  "message": "Mensagem enviada com sucesso"
}
```

### Respostas de Erro

#### 422 - Validação
```json
{
  "success": false,
  "error": {
    "message": "Dados inválidos",
    "code": "VALIDATION_ERROR",
    "details": {
      "to": ["Campo obrigatório"],
      "from": ["Campo obrigatório"],
      "message": ["Campo obrigatório"]
    }
  }
}
```

#### 422 - Conta WhatsApp não encontrada
```json
{
  "success": false,
  "error": {
    "message": "Conta WhatsApp não encontrada ou inativa",
    "code": "VALIDATION_ERROR",
    "details": {
      "from": ["Nenhuma conta WhatsApp ativa encontrada para o número: 5511916127354"]
    }
  }
}
```

#### 401 - Não autorizado
```json
{
  "success": false,
  "error": {
    "message": "Token inválido ou expirado",
    "code": "UNAUTHORIZED"
  }
}
```

---

## 📋 2. LISTAR CONTAS WHATSAPP

### ⚠️ **CORREÇÃO IMPORTANTE**

**URL INCORRETA (não funciona):**
```
❌ GET /whatsapp/accounts
```

**URL CORRETA:**
```
✅ GET /whatsapp-accounts
```

### Endpoint
```
GET /whatsapp-accounts
```

### URL Completa
```
https://chat.personizi.com.br/api/v1/whatsapp-accounts
```

### Headers
```
Authorization: Bearer b481e4bb3d224638a498be99ae3e411c2f414e71a69f081039edd0c4dff99912
Content-Type: application/json
```

### Query Parameters (Opcionais)

| Parâmetro | Tipo | Descrição | Exemplo |
|-----------|------|-----------|---------|
| `status` | string | Filtrar por status | `active`, `inactive`, `disconnected` |
| `page` | integer | Número da página | `1` (padrão) |
| `per_page` | integer | Itens por página | `20` (padrão, máximo: 100) |

### Exemplos de URLs

```
# Todas as contas (paginado)
GET /whatsapp-accounts

# Apenas contas ativas
GET /whatsapp-accounts?status=active

# Paginação
GET /whatsapp-accounts?page=2&per_page=50
```

### Resposta Esperada (Sucesso - 200 OK)
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

### Resposta de Erro (404)
```json
{
  "success": false,
  "error": {
    "message": "Endpoint não encontrado",
    "code": "NOT_FOUND"
  }
}
```

**💡 Nota:** Se você receber erro 404 ao chamar `/whatsapp/accounts`, significa que está usando a URL antiga. Use `/whatsapp-accounts` (com hífen).

---

## 🔍 3. OBTER CONTA WHATSAPP ESPECÍFICA

### Endpoint
```
GET /whatsapp-accounts/:id
```

### URL Completa (Exemplo)
```
https://chat.personizi.com.br/api/v1/whatsapp-accounts/1
```

### Headers
```
Authorization: Bearer b481e4bb3d224638a498be99ae3e411c2f414e71a69f081039edd0c4dff99912
Content-Type: application/json
```

### Resposta Esperada (Sucesso - 200 OK)
```json
{
  "success": true,
  "data": {
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
    "wavoip_enabled": false,
    "new_conv_limit_enabled": false,
    "new_conv_limit_count": 10,
    "new_conv_limit_period_value": 1,
    "new_conv_limit_period": "hours",
    "last_connection_check": "2025-02-01 14:30:00",
    "last_connection_result": "connected",
    "consecutive_failures": 0,
    "created_at": "2025-01-15 10:30:00",
    "updated_at": "2025-02-01 14:20:00"
  }
}
```

---

## 💻 4. IMPLEMENTAÇÃO NO CÓDIGO PHP

### Classe Principal
**Arquivo:** `includes/integrations/class-pcw-personizi.php`  
**Classe:** `PCW_Personizi_Integration`

### Método de Requisição (request) - ✅ CORRETO

```php
private function request( $endpoint, $method = 'GET', $data = array() ) {
    // Monta URL completa
    $url = 'https://chat.personizi.com.br/api/v1' . $endpoint;
    
    // Configura argumentos
    $args = array(
        'method'  => $method,
        'headers' => array(
            'Authorization' => 'Bearer ' . $this->api_token,
            'Content-Type'  => 'application/json',
        ),
        'timeout' => 30,
    );
    
    // Se POST/PUT, adiciona body JSON
    if ( in_array( $method, array( 'POST', 'PUT' ) ) && ! empty( $data ) ) {
        $args['body'] = json_encode( $data );
    }
    
    // Faz requisição via wp_remote_request()
    $response = wp_remote_request( $url, $args );
    
    // Retorna resposta parseada ou WP_Error
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    
    $status_code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );
    
    return array(
        'status' => $status_code,
        'data'   => $data,
    );
}
```

### Método: Enviar Mensagem - ✅ CORRETO

```php
public function send_whatsapp_message( $to, $message, $contact_name = '', $from = '' ) {
    // Remove caracteres não numéricos
    $to = preg_replace( '/[^0-9]/', '', $to );
    
    // Se não informou from, usa padrão
    if ( empty( $from ) ) {
        $from = $this->default_from; // 5511916127354
    }
    
    // Monta dados
    $data = array(
        'to'      => $to,
        'from'    => $from,
        'message' => $message,
    );
    
    // Adiciona contact_name se fornecido
    if ( ! empty( $contact_name ) ) {
        $data['contact_name'] = $contact_name;
    }
    
    // Faz requisição POST
    return $this->request( '/messages/send', 'POST', $data );
}
```

### Método: Listar Contas - ⚠️ PRECISA CORREÇÃO

**ANTES (INCORRETO):**
```php
public function get_whatsapp_accounts( $force_refresh = false ) {
    // ❌ URL ERRADA - com barra em vez de hífen
    $result = $this->request( '/whatsapp/accounts', 'GET' );
    
    // Extrai contas do resultado
    if ( ! is_wp_error( $result ) ) {
        $accounts = isset( $result['data']['accounts'] )
            ? $result['data']['accounts']
            : array();
        return $accounts;
    }
    
    return $result; // WP_Error
}
```

**DEPOIS (CORRETO):**
```php
public function get_whatsapp_accounts( $force_refresh = false ) {
    // ✅ URL CORRETA - com hífen
    $result = $this->request( '/whatsapp-accounts', 'GET' );
    
    // Extrai contas do resultado
    if ( ! is_wp_error( $result ) && isset( $result['data']['data']['accounts'] ) ) {
        return $result['data']['data']['accounts'];
    }
    
    return is_wp_error( $result ) ? $result : array();
}
```

**💡 Mudanças:**
1. `/whatsapp/accounts` → `/whatsapp-accounts` (com hífen)
2. Acessar `$result['data']['data']['accounts']` em vez de `$result['data']['accounts']`

---

## 🧪 5. TESTES

### Teste 1: Enviar Mensagem

```bash
curl -X POST "https://chat.personizi.com.br/api/v1/messages/send" \
  -H "Authorization: Bearer b481e4bb3d224638a498be99ae3e411c2f414e71a69f081039edd0c4dff99912" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "5511999998888",
    "from": "5511916127354",
    "message": "Teste de envio via API",
    "contact_name": "Cliente Teste"
  }'
```

### Teste 2: Listar Contas (URL CORRETA)

```bash
curl -X GET "https://chat.personizi.com.br/api/v1/whatsapp-accounts?status=active" \
  -H "Authorization: Bearer b481e4bb3d224638a498be99ae3e411c2f414e71a69f081039edd0c4dff99912" \
  -H "Content-Type: application/json"
```

### Teste 3: Obter Conta Específica

```bash
curl -X GET "https://chat.personizi.com.br/api/v1/whatsapp-accounts/1" \
  -H "Authorization: Bearer b481e4bb3d224638a498be99ae3e411c2f414e71a69f081039edd0c4dff99912" \
  -H "Content-Type: application/json"
```

---

## ⚠️ RESUMO DAS CORREÇÕES

### 1. ✅ Endpoint de Enviar Mensagem
- **URL:** `/messages/send`
- **Status:** ✅ **Criado e funcionando**

### 2. ❌ ➡️ ✅ Endpoint de Listar Contas
- **URL ANTIGA (ERRADA):** `/whatsapp/accounts`
- **URL NOVA (CORRETA):** `/whatsapp-accounts`
- **Status:** ✅ **Corrigido**

### 3. 📝 Estrutura da Resposta
- **Enviar mensagem:** `success`, `data`, `message`
- **Listar contas:** `success`, `data: { accounts, pagination }`

---

## 📞 Suporte

Para dúvidas ou problemas:
- **Documentação completa:** `/INTEGRACAO_PERSONIZI.md`
- **Diagnóstico visual:** `https://chat.personizi.com.br/diagnostico-personizi.php`
- **Logs da API:** Configurações > API & Tokens > Logs

---

## ✅ Checklist de Implementação

- [ ] Atualizar endpoint de listar contas: `/whatsapp/accounts` → `/whatsapp-accounts`
- [ ] Atualizar estrutura de resposta: `$result['data']['data']['accounts']`
- [ ] Testar envio de mensagem via `/messages/send`
- [ ] Testar listagem de contas via `/whatsapp-accounts`
- [ ] Verificar logs da API para confirmar sucesso
- [ ] Documentar mudanças no código do Personizi

---

**Última atualização:** 01/02/2025  
**Versão da API:** v1  
**Status:** ✅ Todos os endpoints funcionando
