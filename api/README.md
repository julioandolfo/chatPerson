# 📡 API REST - Sistema Multiatendimento

API REST completa para integração com o sistema de multiatendimento.

---

## 🆕 Novidades (01/02/2025)

### ⭐ Novo Endpoint: Envio Direto de Mensagens WhatsApp

Criado endpoint `POST /api/v1/messages/send` para envio direto de mensagens via WhatsApp, ideal para integrações externas.

**Benefícios:**
- ✅ Não precisa criar conversa antes
- ✅ Cria contato automaticamente
- ✅ Cria conversa automaticamente
- ✅ Retorna IDs de mensagem e conversa
- ✅ Integração simplificada para WordPress/Personizi

**Endpoints atualizados:**
- ✅ `GET /api/v1/whatsapp-accounts` - Lista contas WhatsApp
- ✅ `GET /api/v1/whatsapp-accounts/:id` - Obter conta específica
- ⭐ `POST /api/v1/messages/send` - **NOVO** - Envio direto de mensagens

**Documentação específica para Personizi:**
- 📘 `/DOCUMENTACAO_PERSONIZI_CORRIGIDA.md` - Documentação técnica completa
- 🚨 `/CORRECOES_PERSONIZI_URGENTE.md` - Correções em 7 minutos
- 📖 `/INTEGRACAO_PERSONIZI.md` - Guia de integração passo a passo
- 🔍 `/diagnostico-personizi.php` - Ferramenta de diagnóstico visual

---

## 🚀 Início Rápido

### 1. Executar Migrations

```bash
# Executar migrations para criar tabelas da API
php public/index.php # As migrations são executadas automaticamente
```

### 2. Gerar Token de API

Acesse: **Configurações > API & Tokens** no painel web e gere um novo token.

### 3. Fazer Primeira Requisição

```bash
curl -X GET "https://seudominio.com/api/v1/conversations" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

---

## 🔐 Autenticação

### Opção 1: JWT (Recomendado para aplicações frontend)

```bash
# Login
curl -X POST "https://seudominio.com/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "agente@empresa.com",
    "password": "senha123"
  }'

# Resposta:
{
  "success": true,
  "data": {
    "user": { ... },
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}

# Usar token nas requisições
curl -X GET "https://seudominio.com/api/v1/conversations" \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
```

### Opção 2: API Token (Recomendado para integrações backend)

```bash
# Gerar token no painel web (Configurações > API & Tokens)
# Usar token nas requisições

curl -X GET "https://seudominio.com/api/v1/conversations" \
  -H "Authorization: Token SEU_TOKEN_AQUI"

# Ou via header X-API-Key
curl -X GET "https://seudominio.com/api/v1/conversations" \
  -H "X-API-Key: SEU_TOKEN_AQUI"
```

---

## 📚 Endpoints Disponíveis

### Autenticação

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/api/v1/auth/login` | Login (obter JWT) |
| POST | `/api/v1/auth/refresh` | Renovar JWT |
| POST | `/api/v1/auth/logout` | Logout |
| GET | `/api/v1/auth/me` | Dados do usuário autenticado |

### Conversas

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/conversations` | Listar conversas |
| POST | `/api/v1/conversations` | Criar conversa |
| GET | `/api/v1/conversations/:id` | Obter conversa |
| PUT | `/api/v1/conversations/:id` | Atualizar conversa |
| DELETE | `/api/v1/conversations/:id` | Deletar conversa |
| POST | `/api/v1/conversations/:id/assign` | Atribuir conversa |
| POST | `/api/v1/conversations/:id/close` | Encerrar conversa |
| POST | `/api/v1/conversations/:id/reopen` | Reabrir conversa |
| POST | `/api/v1/conversations/:id/move-stage` | Mover no funil |
| PUT | `/api/v1/conversations/:id/department` | Mudar setor |
| POST | `/api/v1/conversations/:id/tags` | Adicionar tag |
| DELETE | `/api/v1/conversations/:id/tags/:tagId` | Remover tag |

### Mensagens

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/conversations/:id/messages` | Listar mensagens |
| POST | `/api/v1/conversations/:id/messages` | Enviar mensagem em conversa existente |
| GET | `/api/v1/messages/:id` | Obter mensagem |
| POST | `/api/v1/messages/send` | **Enviar mensagem WhatsApp direta** ⭐ |

#### Enviar Mensagem WhatsApp Direta (Novo) ⭐

Endpoint especial para envio direto de mensagens via WhatsApp, ideal para integrações externas como Personizi, WordPress, etc.

**POST** `/api/v1/messages/send`

**Body JSON:**
```json
{
  "to": "5511999999999",
  "from": "5511916127354",
  "message": "Texto da mensagem",
  "contact_name": "Nome do Contato" (opcional)
}
```

**Campos:**
- `to` (obrigatório): Número do destinatário com código do país (apenas dígitos)
- `from` (obrigatório): Número da conta WhatsApp remetente
- `message` (obrigatório): Texto da mensagem (máx 4096 caracteres)
- `contact_name` (opcional): Nome do contato

**Resposta de Sucesso (201):**
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

**Comportamento:**
- Busca ou cria o contato automaticamente
- Busca ou cria a conversa automaticamente
- Salva mensagem no banco de dados
- Envia via provedor (Quepasa, etc)
- Retorna IDs da mensagem e conversa criadas

### Participantes

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/conversations/:id/participants` | Listar participantes |
| POST | `/api/v1/conversations/:id/participants` | Adicionar participante |
| DELETE | `/api/v1/conversations/:id/participants/:userId` | Remover participante |

### Contatos

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/contacts` | Listar contatos |
| POST | `/api/v1/contacts` | Criar contato |
| GET | `/api/v1/contacts/:id` | Obter contato |
| PUT | `/api/v1/contacts/:id` | Atualizar contato |
| DELETE | `/api/v1/contacts/:id` | Deletar contato |
| GET | `/api/v1/contacts/:id/conversations` | Conversas do contato |

### Agentes

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/agents` | Listar agentes |
| GET | `/api/v1/agents/:id` | Obter agente |
| GET | `/api/v1/agents/:id/stats` | Estatísticas do agente |

### Setores

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/departments` | Listar setores |
| GET | `/api/v1/departments/:id` | Obter setor |

### Funis

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/funnels` | Listar funis |
| GET | `/api/v1/funnels/:id` | Obter funil |
| GET | `/api/v1/funnels/:id/stages` | Listar etapas |
| GET | `/api/v1/funnels/:id/conversations` | Conversas do funil |

### Tags

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/tags` | Listar tags |
| POST | `/api/v1/tags` | Criar tag |
| GET | `/api/v1/tags/:id` | Obter tag |
| PUT | `/api/v1/tags/:id` | Atualizar tag |
| DELETE | `/api/v1/tags/:id` | Deletar tag |

### Contas WhatsApp

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/whatsapp-accounts` | Listar contas WhatsApp |
| GET | `/api/v1/whatsapp-accounts/:id` | Obter conta WhatsApp específica |

**Filtros disponíveis (GET /whatsapp-accounts):**
- `status`: Filtrar por status (`active`, `inactive`, `disconnected`)
- `page`: Número da página (padrão: 1)
- `per_page`: Itens por página (padrão: 20, máximo: 100)

**Exemplo:**
```bash
curl -X GET "https://seudominio.com/api/v1/whatsapp-accounts?status=active&page=1&per_page=20" \
  -H "Authorization: Bearer SEU_TOKEN"
```

---

## 📖 Exemplos de Uso

### Enviar Mensagem WhatsApp Diretamente (Novo) ⭐

**Recomendado para integrações externas** - Não precisa criar conversa antes!

```bash
curl -X POST "https://seudominio.com/api/v1/messages/send" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "5511999999999",
    "from": "5511916127354",
    "message": "Olá! Esta é uma mensagem via API",
    "contact_name": "João Silva"
  }'

# Resposta:
{
  "success": true,
  "data": {
    "message_id": "12345",
    "conversation_id": "789",
    "status": "sent",
    "external_message_id": "msg_xyz"
  },
  "message": "Mensagem enviada com sucesso"
}
```

### Criar Conversa e Enviar Mensagem (Método Tradicional)

```bash
# 1. Criar conversa
curl -X POST "https://seudominio.com/api/v1/conversations" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "contact_id": 123,
    "channel": "whatsapp",
    "agent_id": 5,
    "department_id": 2,
    "funnel_id": 1,
    "stage_id": 3
  }'

# Resposta:
{
  "success": true,
  "data": {
    "id": 456,
    "contact_id": 123,
    "channel": "whatsapp",
    "status": "open",
    "agent_id": 5,
    "created_at": "2025-01-05 10:30:00"
  },
  "message": "Conversa criada com sucesso"
}

# 2. Enviar mensagem
curl -X POST "https://seudominio.com/api/v1/conversations/456/messages" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "body": "Olá! Como posso ajudar?",
    "type": "text"
  }'
```

### Listar Conversas com Filtros

```bash
curl -X GET "https://seudominio.com/api/v1/conversations?status=open&agent_id=5&page=1&per_page=20" \
  -H "Authorization: Bearer SEU_TOKEN"
```

### Atribuir e Mover no Funil

```bash
# Atribuir conversa
curl -X POST "https://seudominio.com/api/v1/conversations/456/assign" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"agent_id": 7}'

# Mover no funil
curl -X POST "https://seudominio.com/api/v1/conversations/456/move-stage" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "funnel_id": 1,
    "stage_id": 4
  }'
```

### Adicionar Participante

```bash
curl -X POST "https://seudominio.com/api/v1/conversations/456/participants" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 10,
    "role": "observer"
  }'
```

---

## 📊 Paginação

Endpoints de listagem suportam paginação:

```bash
GET /api/v1/conversations?page=2&per_page=50
```

Resposta:

```json
{
  "success": true,
  "data": {
    "items": [ ... ],
    "pagination": {
      "total": 150,
      "page": 2,
      "per_page": 50,
      "total_pages": 3,
      "has_next": true,
      "has_prev": true
    }
  }
}
```

---

## 🚦 Rate Limiting

- **Padrão**: 100 requisições/minuto por token
- **Configurável**: Por token individual no painel web

Headers de resposta:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1704465600
```

Quando exceder:

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

## ❌ Tratamento de Erros

### Códigos de Status HTTP

| Código | Descrição |
|--------|-----------|
| 200 | OK |
| 201 | Created |
| 204 | No Content |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Server Error |

### Formato de Erro

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Dados inválidos",
    "details": {
      "contact_id": ["Campo obrigatório"],
      "channel": ["Deve ser um dos: whatsapp, email, ..."]
    }
  }
}
```

---

## 🔒 Segurança

### Boas Práticas

1. **Nunca exponha tokens**: Não commite tokens no código
2. **Use HTTPS**: Sempre em produção
3. **Revogue tokens comprometidos**: No painel web
4. **Restrinja IPs**: Configure IPs permitidos por token
5. **Monitore logs**: Acompanhe uso da API

### Permissões

A API respeita o mesmo sistema de permissões do painel web. Tokens herdam permissões do usuário.

---

## 📝 Logs

Todas as requisições são registradas em `api_logs`:

- Endpoint chamado
- Método HTTP
- Request/Response
- Tempo de execução
- IP de origem
- User Agent

Acesse logs no painel web: **Configurações > API & Tokens > Logs**

---

## 🐛 Troubleshooting

### Erro 401 - Unauthorized

- Verifique se o token está correto
- Verifique se o token não expirou
- Verifique se o usuário está ativo

### Erro 403 - Forbidden

- Verifique permissões do usuário
- Verifique se IP está permitido (se configurado)

### Erro 429 - Too Many Requests

- Aguarde o tempo indicado em `Retry-After`
- Considere aumentar o rate limit do token

### Erro 500 - Server Error

- Verifique logs do servidor
- Contate o suporte

---

## 🔄 Versionamento

A API usa versionamento na URL: `/api/v1/`

Futuras versões: `/api/v2/`, `/api/v3/`, etc.

---

## 📞 Suporte

Para dúvidas ou problemas:

- **Documentação completa**: `/api/docs/openapi.yaml`
- **Logs da API**: Configurações > API & Tokens > Logs
- **Integração Personizi**: `/DOCUMENTACAO_PERSONIZI_CORRIGIDA.md`
- **Diagnóstico Personizi**: `/diagnostico-personizi.php`
- **Suporte**: contato@seudominio.com

### Documentação Adicional

- 📚 **Índice Personizi**: `/INDICE_PERSONIZI.md` - Índice de todos os recursos
- 🚨 **Correções Urgentes**: `/CORRECOES_PERSONIZI_URGENTE.md` - Correções rápidas
- 📖 **Guia de Integração**: `/INTEGRACAO_PERSONIZI.md` - Passo a passo completo

---

## 🔗 Integrações Especiais

### Personizi (WordPress)

A API possui endpoints específicos otimizados para integração com o plugin Personizi:

**Endpoints disponíveis:**
- `POST /api/v1/messages/send` - Envio direto de mensagens
- `GET /api/v1/whatsapp-accounts` - Listar contas WhatsApp
- `GET /api/v1/whatsapp-accounts/:id` - Obter conta específica

**Documentação específica:**
- 📘 **Guia completo:** `/DOCUMENTACAO_PERSONIZI_CORRIGIDA.md`
- 🚨 **Correções urgentes:** `/CORRECOES_PERSONIZI_URGENTE.md`
- 📖 **Integração passo a passo:** `/INTEGRACAO_PERSONIZI.md`
- 🔍 **Diagnóstico visual:** `https://seudominio.com/diagnostico-personizi.php`

**Exemplo PHP (WordPress):**
```php
<?php
$api_url = 'https://chat.personizi.com.br/api/v1';
$token = 'seu_token_aqui';

// Enviar mensagem
$response = wp_remote_post($api_url . '/messages/send', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json'
    ],
    'body' => json_encode([
        'to' => '5511999999999',
        'from' => '5511916127354',
        'message' => 'Olá do WordPress!',
        'contact_name' => 'Cliente'
    ])
]);

// Listar contas
$response = wp_remote_get($api_url . '/whatsapp-accounts?status=active', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token
    ]
]);
```

---

## 🎉 Pronto!

Sua API REST está configurada e pronta para uso!

**Próximos passos**:
1. Gere seu primeiro token
2. Teste endpoints básicos
3. Integre com sua aplicação
4. Monitore logs e uso

**Para integrações Personizi:**
- Consulte a documentação específica em `/DOCUMENTACAO_PERSONIZI_CORRIGIDA.md`
- Use a ferramenta de diagnóstico em `/diagnostico-personizi.php`
