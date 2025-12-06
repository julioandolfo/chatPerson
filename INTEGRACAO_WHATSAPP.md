# 📱 Integração WhatsApp - Quepasa API (Self-Hosted)

## ✅ Funcionalidades Implementadas

- ✅ CRUD completo de contas WhatsApp
- ✅ Geração de QR Code para conexão via `/scan`
- ✅ Geração automática de token Quepasa
- ✅ Verificação de status da conexão
- ✅ Desconexão de contas
- ✅ Envio de mensagens via API (`/send`)
- ✅ Configuração automática de webhook (`/webhook`)
- ✅ Recebimento de mensagens via webhook
- ✅ Processamento automático de mensagens recebidas
- ✅ Criação automática de contatos e conversas

## 🔧 Configuração

### 1. Executar Migration

Primeiro, execute a migration para adicionar os campos necessários:

```bash
php database/migrate.php
```

Ou execute manualmente a migration `022_add_quepasa_fields_to_whatsapp_accounts.php`

### 2. Criar Conta WhatsApp no Sistema

1. Acesse **Integrações > WhatsApp**
2. Clique em **Nova Conta WhatsApp**
3. Preencha os dados:
   - **Nome da Conta**: Nome identificador (ex: "WhatsApp Principal")
   - **Número do WhatsApp**: Número completo com código do país (ex: 5511999999999)
   - **Provider**: Selecione "Quepasa API"
   - **URL da API**: URL da sua instalação Quepasa (ex: https://whats.seudominio.com)
   - **Quepasa User**: **Identificador único do usuário** (ex: `julio`, `personizi`, `meu-sistema`, etc.)
     - Este é um identificador que você escolhe para identificar quem está fazendo a requisição
     - Pode ser qualquer string (sem espaços, preferencialmente)
     - Exemplos: seu nome, nome da empresa, nome do sistema
     - Este valor será usado no header `X-QUEPASA-USER`
   - **Track ID** (Opcional): ID para rastreamento (deixe vazio para usar o nome da conta)
     - Usado no header `X-QUEPASA-TRACKID` para identificar de onde vem as mensagens
   - **Token Quepasa**: o sistema gera automaticamente (pode ser copiado/renovado nas configurações)
     - Este token identifica a conexão junto à Quepasa (enviado no header `X-QUEPASA-TOKEN`)
     - Ele nunca pode ficar vazio; o mesmo token deve ser usado em todas as requisições daquela conta

4. Clique em **Criar Conta**

**Notas importantes**

- **Quepasa User**: campo obrigatório e serve como identificador único. Pode ser qualquer string que você escolher, como:
  - Seu nome: `julio`
  - Nome da empresa: `personizi`
  - Nome do sistema: `chat-sistema`
- **Token Quepasa**: gerado automaticamente na criação da conta (ex.: `0f3a9c6e3a4e4b0c...`). Guarde esse token, pois ele será enviado em todas as requisições (`X-QUEPASA-TOKEN`) e identifica a sessão no servidor Quepasa.

### 3. Conectar WhatsApp via QR Code

1. Na lista de contas, clique no botão **QR Code** da conta desejada
2. O sistema chamará o endpoint `/scan` da Quepasa API usando o token configurado
3. Um modal será aberto com o QR Code (imagem PNG em base64) retornado pela API
5. Abra o WhatsApp no celular
6. Vá em **Configurações > Aparelhos conectados > Conectar um aparelho**
7. Escaneie o QR Code exibido no sistema
8. Após escanear, o `chatid` será salvo automaticamente

### 4. Verificar Status da Conexão

1. Clique no botão de **informações** (ícone "i") na conta
2. O sistema verificará se há um `chatid` salvo
3. Se conectado, o status mudará para "Conectado" (verde)

### 5. Configurar Webhook Automaticamente

Após conectar, você pode configurar o webhook automaticamente:

1. O sistema pode configurar o webhook chamando o endpoint `/webhook` da Quepasa
2. O webhook será configurado para: `https://seudominio.com/whatsapp-webhook`
3. Todas as mensagens recebidas serão enviadas para esse webhook

### 5. Testar Envio de Mensagem

1. Na conta conectada, você pode enviar mensagens de teste através da API
2. As mensagens serão enviadas diretamente via Quepasa API

## 📋 Estrutura de Dados

### Tabela `whatsapp_accounts`

- `id`: ID da conta
- `name`: Nome da conta
- `phone_number`: Número do WhatsApp
- `provider`: Provider usado (quepasa, evolution)
- `api_url`: URL da API (ex: https://whats.seudominio.com)
- `quepasa_user`: Identificador do usuário (X-QUEPASA-USER)
- `quepasa_token`: Token gerado pelo `/scan` (X-QUEPASA-TOKEN)
- `quepasa_trackid`: Track ID para rastreamento (X-QUEPASA-TRACKID)
- `quepasa_chatid`: Chat ID retornado pelo scan (X-QUEPASA-CHATID)
- `api_key`: Chave de autenticação (opcional, não usado na self-hosted)
- `instance_id`: ID da instância (não usado na self-hosted)
- `status`: Status (active, inactive, disconnected)
- `config`: Configurações adicionais (JSON)

## 🔌 Endpoints da API

### Criar Conta
```
POST /integrations/whatsapp
```

### Obter QR Code
```
GET /integrations/whatsapp/{id}/qrcode
```
**Endpoint Quepasa**: `POST /scan`  
**Headers**: 
- `X-QUEPASA-USER`: Identificador do usuário
- `X-QUEPASA-TOKEN`: Token (vazio na primeira vez, depois usa o token salvo)

**Resposta**: Retorna `qrcode`, `token`, `trackid`, `chatid`

### Verificar Status
```
GET /integrations/whatsapp/{id}/status
```

### Desconectar
```
POST /integrations/whatsapp/{id}/disconnect
```

### Atualizar Conta
```
POST /integrations/whatsapp/{id}
```

### Deletar Conta
```
DELETE /integrations/whatsapp/{id}
```

### Enviar Mensagem de Teste
```
POST /integrations/whatsapp/{id}/test
Body: {
    "to": "5511999999999",
    "message": "Mensagem de teste"
}
```
**Endpoint Quepasa**: `POST /send`  
**Headers**: 
- `X-QUEPASA-TOKEN`: Token salvo
- `X-QUEPASA-TRACKID`: Track ID
- `X-QUEPASA-CHATID`: Número + @s.whatsapp.net

### Configurar Webhook
```
POST /integrations/whatsapp/{id}/webhook
Body: {
    "webhook_url": "https://seudominio.com/whatsapp-webhook" (opcional, usa padrão se não informado)
}
```
**Endpoint Quepasa**: `POST /webhook`  
**Body**: 
```json
{
    "url": "https://seudominio.com/whatsapp-webhook",
    "forwardinternal": true,
    "trackid": "meu-sistema",
    "extra": {}
}
```

### Webhook (Público)
```
POST /whatsapp-webhook
```

## 📨 Formato do Webhook

O webhook recebe mensagens no seguinte formato:

```json
{
    "from": "5511999999999@s.whatsapp.net",
    "text": "Texto da mensagem",
    "id": "message_id",
    "timestamp": 1234567890,
    "trackid": "meu-sistema",
    "chatid": "5511999999999@s.whatsapp.net"
}
```

O sistema identifica a conta pelo `trackid` ou `chatid` recebidos no webhook.

## 🔄 Fluxo de Processamento

1. **Mensagem Recebida**: Quepasa envia webhook para `/whatsapp-webhook`
2. **Processamento**: Sistema identifica a conta pelo número ou instance_id
3. **Criação de Contato**: Se não existir, cria novo contato
4. **Criação de Conversa**: Se não existir, cria nova conversa
5. **Criação de Mensagem**: Salva a mensagem no banco
6. **Disparo de Automações**: Dispara automações do tipo `message_received`

## 🛠️ Troubleshooting

### QR Code não aparece
- Verifique se a URL da API está correta
- Verifique se a API Key está correta (se necessário)
- Verifique os logs em `logs/app.log`

### Mensagens não são recebidas
- Verifique se o webhook está configurado corretamente no Quepasa
- Verifique se a URL do webhook está acessível publicamente
- Verifique os logs em `logs/app.log`

### Status sempre desconectado
- Verifique se o WhatsApp está realmente conectado no celular
- Verifique se a URL da API está correta
- Tente desconectar e conectar novamente

## 📝 Notas Importantes

- O QR Code expira após 60 segundos (padrão)
- É necessário gerar um novo QR Code se o anterior expirar
- O sistema atualiza automaticamente o status quando você verifica
- Mensagens recebidas criam automaticamente contatos e conversas
- O webhook deve ser acessível publicamente (não funciona em localhost sem túnel)

## 🔐 Permissões Necessárias

- `whatsapp.view`: Visualizar contas WhatsApp
- `whatsapp.create`: Criar contas WhatsApp
- `whatsapp.edit`: Editar e desconectar contas
- `whatsapp.delete`: Deletar contas

---

**Última atualização**: 2025-01-27

