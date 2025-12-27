# 🎯 INTEGRAÇÃO COMPLETA COM META (INSTAGRAM + WHATSAPP)

## 📋 ÍNDICE

1. [Visão Geral](#visão-geral)
2. [Pré-requisitos](#pré-requisitos)
3. [Setup do App Meta](#setup-do-app-meta)
4. [Configuração do Sistema](#configuração-do-sistema)
5. [Migração do Banco de Dados](#migração-do-banco-de-dados)
6. [Configuração de Webhooks](#configuração-de-webhooks)
7. [Conectando Instagram](#conectando-instagram)
8. [Conectando WhatsApp](#conectando-whatsapp)
9. [Enviando Mensagens](#enviando-mensagens)
10. [Troubleshooting](#troubleshooting)

---

## 🌟 VISÃO GERAL

Esta integração permite:

✅ **Instagram Graph API**
- Direct Messages (DM)
- Perfil completo
- Avatares em HD
- Webhook em tempo real

✅ **WhatsApp Cloud API**
- Enviar/receber mensagens
- Templates aprovados
- Mídia (foto, vídeo, áudio, documento)
- Status de mensagens em tempo real

✅ **Infraestrutura Unificada**
- OAuth único
- Webhooks unificados
- Rate limiting global
- Logs centralizados

---

## 📦 PRÉ-REQUISITOS

### 1. **Facebook Business Manager**
- Conta no Facebook Business Manager
- Empresa verificada

### 2. **Número de Telefone (para WhatsApp)**
- Número de telefone válido
- Não pode estar vinculado a WhatsApp pessoal
- Recomendado: número fixo ou chip empresarial

### 3. **Servidor Web**
- HTTPS obrigatório (Meta requer SSL)
- Domínio verificado

---

## 🚀 SETUP DO APP META

### PASSO 1: Criar App no Meta for Developers

1. Acesse: [https://developers.facebook.com/apps/](https://developers.facebook.com/apps/)
2. Clique em **"Criar App"**
3. Escolha tipo: **"Negócio"**
4. Preencha:
   - Nome do App: `Seu Sistema de Chat`
   - E-mail de contato
   - Empresa vinculada (Business Manager)

### PASSO 2: Adicionar Produtos

#### Instagram Graph API:
1. No painel do app, clique em **"Adicionar Produto"**
2. Selecione **"Instagram"**
3. Configure:
   - Produtos > Instagram > Configurações
   - Adicione um usuário de teste (sua conta Instagram Business)

#### WhatsApp Cloud API:
1. Clique em **"Adicionar Produto"**
2. Selecione **"WhatsApp"**
3. Configure:
   - Produtos > WhatsApp > Introdução
   - Selecione conta Business (ou crie uma)
   - Adicione número de telefone
   - Verifique o número (SMS)

### PASSO 3: Obter Credenciais

1. Vá em **Configurações > Básico**
2. Anote:
   - **App ID**: `123456789012345`
   - **App Secret**: `abc123def456...` (clique em "Mostrar")

### PASSO 4: Configurar OAuth

1. Vá em **Produtos > Facebook Login > Configurações**
2. Em **"URIs de redirecionamento do OAuth válidos"**, adicione:
   ```
   https://seudominio.com/integrations/meta/oauth/callback
   ```

### PASSO 5: Verificar Domínio

1. Vá em **Configurações > Básico**
2. Role até **"Domínios do App"**
3. Adicione: `seudominio.com`
4. Siga as instruções para verificação (DNS ou upload de arquivo)

---

## ⚙️ CONFIGURAÇÃO DO SISTEMA

### PASSO 1: Variáveis de Ambiente

Edite seu arquivo `.env` ou adicione ao `config/meta.php`:

```env
# Meta (Instagram + WhatsApp)
META_APP_ID=123456789012345
META_APP_SECRET=abc123def456...
META_WEBHOOK_VERIFY_TOKEN=seu_token_seguro_aqui_$(openssl rand -hex 32)
APP_URL=https://seudominio.com
```

### PASSO 2: Editar `config/meta.php`

O arquivo já foi criado em `config/meta.php`. Certifique-se de que as variáveis acima estejam definidas.

---

## 💾 MIGRAÇÃO DO BANCO DE DADOS

Execute as migrations:

```bash
cd database/migrations
php migrate.php
```

Ou execute manualmente:

```sql
-- Migrations:
-- 085_create_meta_oauth_tokens.php
-- 086_create_instagram_accounts.php
-- 087_create_whatsapp_phones.php
-- 088_add_meta_fields_to_contacts.php
```

Verificar se as tabelas foram criadas:

```sql
SHOW TABLES LIKE '%meta%';
SHOW TABLES LIKE '%instagram%';
SHOW TABLES LIKE '%whatsapp%';
```

---

## 🔗 CONFIGURAÇÃO DE WEBHOOKS

### PASSO 1: Configurar URL no Meta

#### Instagram:
1. No painel do app, vá em **Produtos > Instagram > Configurações**
2. Em **"Webhooks"**, clique em **"Configurar"**
3. URL do callback: `https://seudominio.com/webhooks/meta`
4. Token de verificação: (o mesmo de `META_WEBHOOK_VERIFY_TOKEN`)
5. Selecione campos:
   - `messages`
   - `message_reactions`
   - `messaging_seen`

#### WhatsApp:
1. Vá em **Produtos > WhatsApp > Configurações**
2. Em **"Webhook"**, clique em **"Configurar"**
3. URL do callback: `https://seudominio.com/webhooks/meta`
4. Token de verificação: (o mesmo de `META_WEBHOOK_VERIFY_TOKEN`)
5. Selecione campos:
   - `messages`
   - `message_status`
   - `messaging_postbacks`

### PASSO 2: Testar Webhook

```bash
# Teste GET (verificação)
curl "https://seudominio.com/webhooks/meta?hub.mode=subscribe&hub.challenge=12345&hub.verify_token=SEU_TOKEN"
# Deve retornar: 12345

# Teste POST (simulação)
curl -X POST https://seudominio.com/webhooks/meta \
  -H "Content-Type: application/json" \
  -d '{"object":"instagram","entry":[{"id":"123","messaging":[]}]}'
# Deve retornar: {"status":"ok"}
```

---

## 📱 CONECTANDO INSTAGRAM

### PASSO 1: Acessar Interface

1. Login no sistema
2. Vá em **Menu > Integrações > Meta (Instagram + WhatsApp)**
3. Clique em **"Conectar Conta Meta"**
4. Selecione **"Instagram"** ou **"Ambos"**

### PASSO 2: Autorizar

1. Você será redirecionado para o Facebook
2. Faça login com sua conta Facebook (vinculada à conta Instagram Business)
3. Autorize as permissões:
   - `instagram_basic`
   - `instagram_manage_messages`
   - `pages_show_list`
   - `pages_read_engagement`
4. Confirme

### PASSO 3: Verificar

Você será redirecionado de volta ao sistema e verá:
- ✅ Conta Instagram conectada
- Avatar, nome de usuário, seguidores
- Status: **Conectado** (verde)

### PASSO 4: Testar

1. Clique no botão **"Testar Mensagem"**
2. Insira:
   - **Instagram User ID** (numérico) do destinatário
   - Mensagem de teste
3. Envie
4. Verifique se chegou no Instagram Direct

---

## 💬 CONECTANDO WHATSAPP

### OPÇÃO 1: OAuth Automático (Recomendado)

1. Vá em **Menu > Integrações > Meta**
2. Clique em **"Conectar Conta Meta"**
3. Selecione **"WhatsApp"** ou **"Ambos"**
4. Autorize
5. Depois, clique em **"Adicionar Número"** e preencha:
   - **Phone Number ID**: (obtido no painel Meta)
   - **Número**: `+5511999999999`
   - **WABA ID**: (obtido no painel Meta)
   - **Meta User ID**: (ID do token OAuth)

### OPÇÃO 2: Manual

#### Obter IDs no Meta:

1. Acesse [https://developers.facebook.com/apps/](https://developers.facebook.com/apps/)
2. Abra seu app
3. Vá em **Produtos > WhatsApp > Introdução**
4. Encontre:
   - **Phone Number ID**: `123456789012345`
   - **WhatsApp Business Account ID (WABA ID)**: `987654321098765`

#### Adicionar no Sistema:

1. Clique em **"Adicionar Número"**
2. Preencha os campos
3. Salve

#### Verificar:

- Status: **Conectado** (verde)
- Qualidade: **GREEN**
- Modo: **LIVE** (ou SANDBOX para testes)

### PASSO 3: Testar

1. Clique em **"Testar Mensagem"**
2. Insira:
   - **Número WhatsApp**: `+5511999999999` (com `+` e código do país)
   - Mensagem de teste
3. Envie
4. Verifique se chegou no WhatsApp

---

## 📤 ENVIANDO MENSAGENS

### Via Interface (Conversas)

Funciona automaticamente! Quando um contato Instagram ou WhatsApp enviar mensagem:

1. Conversa é criada automaticamente
2. Responda normalmente na interface
3. Mensagem é enviada via Meta API

### Via API (Programático)

#### Instagram:

```php
use App\Services\InstagramGraphService;

$result = InstagramGraphService::sendMessage(
    $recipientId,      // Instagram User ID (numérico)
    $message,          // Texto da mensagem
    $accessToken       // Token OAuth
);
```

#### WhatsApp:

```php
use App\Services\WhatsAppCloudService;

// Mensagem de texto
$result = WhatsAppCloudService::sendTextMessage(
    $phoneNumberId,    // Phone Number ID (Meta)
    $to,               // +5511999999999
    $text,             // Texto da mensagem
    $accessToken       // Token OAuth
);

// Template (para iniciar conversa)
$result = WhatsAppCloudService::sendTemplateMessage(
    $phoneNumberId,    // Phone Number ID (Meta)
    $to,               // +5511999999999
    $templateName,     // Nome do template aprovado
    $languageCode,     // pt_BR
    $parameters,       // ['Nome do cliente', 'Código 123']
    $accessToken       // Token OAuth
);

// Mídia
$result = WhatsAppCloudService::sendMedia(
    $phoneNumberId,    // Phone Number ID (Meta)
    $to,               // +5511999999999
    $mediaType,        // image, video, audio, document
    $mediaUrl,         // URL pública da mídia
    $caption,          // Legenda (opcional)
    $accessToken       // Token OAuth
);
```

---

## 🔧 TROUBLESHOOTING

### ❌ Erro: "Invalid OAuth access token"

**Causa**: Token expirado (60 dias)

**Solução**:
1. Vá em **Integrações > Meta**
2. Clique em **"Conectar Conta Meta"** novamente
3. Autorize novamente

### ❌ Erro: "Webhook signature validation failed"

**Causa**: `META_APP_SECRET` incorreto

**Solução**:
1. Verifique `config/meta.php` ou `.env`
2. Compare com o App Secret no painel Meta
3. Reinicie o servidor

### ❌ Erro: "Phone number not connected to a business account"

**Causa**: Número WhatsApp não está em conta Business

**Solução**:
1. Acesse [https://business.facebook.com/](https://business.facebook.com/)
2. Vá em **Configurações > Contas do WhatsApp**
3. Verifique se o número está vinculado

### ❌ Erro: "Rate limit exceeded"

**Causa**: Muitas requisições em pouco tempo

**Solução**:
- Instagram: máx. 200 requests/hora por usuário
- WhatsApp: máx. 80 mensagens/segundo
- Aguarde alguns minutos

### ❌ Instagram: "This user is not receiving messages from you right now"

**Causa**: Usuário precisa iniciar a conversa primeiro (limitação do Instagram)

**Solução**:
- Peça para o usuário enviar uma mensagem primeiro
- Depois você pode responder por 24 horas

### ❌ WhatsApp: "Messaging limit tier reached"

**Causa**: Limite de mensagens atingido (TIER_1K = 1.000/dia)

**Solução**:
- Aguarde até o próximo dia
- Solicite aumento de tier no Meta (baseado em qualidade)

---

## 📊 MONITORAMENTO

### Ver Logs

**Via Interface**:
- Vá em **Integrações > Meta**
- Clique em **"Ver Logs"**

**Via SSH**:
```bash
tail -f storage/logs/meta.log
```

### Verificar Qualidade WhatsApp

1. Acesse [https://business.facebook.com/](https://business.facebook.com/)
2. Vá em **Contas do WhatsApp > [Sua Conta] > Insights**
3. Monitore:
   - Qualidade do número (GREEN = bom)
   - Taxa de resposta
   - Taxa de bloqueio

---

## 🎉 CONCLUSÃO

Sua integração Meta (Instagram + WhatsApp) está **100% funcional**!

### ✅ Checklist Final:

- [ ] App Meta criado
- [ ] Produtos Instagram + WhatsApp adicionados
- [ ] Variáveis de ambiente configuradas
- [ ] Migrations executadas
- [ ] Webhooks configurados e testados
- [ ] Instagram conectado e testado
- [ ] WhatsApp conectado e testado
- [ ] Mensagens enviadas com sucesso
- [ ] Logs funcionando

### 📚 Documentação Adicional:

- [Instagram Graph API](https://developers.facebook.com/docs/instagram-api/)
- [WhatsApp Cloud API](https://developers.facebook.com/docs/whatsapp/cloud-api/)
- [Meta Webhooks](https://developers.facebook.com/docs/graph-api/webhooks/)

---

**Desenvolvido com ❤️ para o Sistema de Chat Multicanal**


