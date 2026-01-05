# 📡 API REST - Sistema Multiatendimento

API REST completa para integração com o sistema de multiatendimento.

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
| POST | `/api/v1/conversations/:id/messages` | Enviar mensagem |
| GET | `/api/v1/messages/:id` | Obter mensagem |

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

---

## 📖 Exemplos de Uso

### Criar Conversa e Enviar Mensagem

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
- **Suporte**: contato@seudominio.com

---

## 🎉 Pronto!

Sua API REST está configurada e pronta para uso!

**Próximos passos**:
1. Gere seu primeiro token
2. Teste endpoints básicos
3. Integre com sua aplicação
4. Monitore logs e uso
