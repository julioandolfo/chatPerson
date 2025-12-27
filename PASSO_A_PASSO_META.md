# 🚀 PASSO A PASSO - Configurar Meta (Instagram + WhatsApp)

## ⚠️ ORDEM CORRETA (Você está aqui → Passo 1)

1. ✅ **Criar App no Meta for Developers** ← **COMECE AQUI**
2. ✅ Configurar credenciais no sistema
3. ✅ Rodar migrations
4. ✅ Conectar contas via interface

---

## 📱 PASSO 1: Criar App no Meta for Developers (5-10 min)

### 1.1 Acessar Meta for Developers

🔗 **Acesse:** https://developers.facebook.com/apps/

- Faça login com sua conta Facebook/Instagram Business

### 1.2 Criar Novo App

1. Clique em **"Criar App"** (ou "Create App")
2. Escolha o tipo: **"Negócio"** (Business)
3. Preencha:
   - **Nome do App:** "ChatSystem" (ou o nome que preferir)
   - **Email de contato:** seu_email@exemplo.com
   - **Conta comercial:** Selecione ou crie uma
4. Clique em **"Criar App"**

### 1.3 Adicionar Produtos

#### Instagram:
1. Na página do app, role até **"Adicionar Produto"**
2. Encontre **"Instagram"**
3. Clique em **"Configurar"**

#### WhatsApp:
1. Role até **"Adicionar Produto"**
2. Encontre **"WhatsApp"**
3. Clique em **"Configurar"**

#### Facebook Login (obrigatório para OAuth):
1. Role até **"Adicionar Produto"**
2. Encontre **"Facebook Login"**
3. Clique em **"Configurar"**

### 1.4 Obter Credenciais

1. No menu lateral, vá em **"Configurações > Básico"**
2. **Copie e anote:**
   ```
   App ID: 123456789012345
   App Secret: [clique em "Mostrar" e copie]
   ```

### 1.5 Configurar OAuth Redirect

1. No menu lateral, vá em **"Produtos > Facebook Login > Configurações"**
2. Em **"URIs de redirecionamento do OAuth válidos"**, adicione:
   ```
   http://localhost/integrations/meta/oauth/callback
   https://seudominio.com/integrations/meta/oauth/callback
   ```
3. Clique em **"Salvar alterações"**

### 1.6 Configurar Webhook (para receber mensagens)

1. No menu lateral, vá em **"Produtos > Webhooks"**
2. Clique em **"Configurar"**
3. Preencha:
   - **URL de callback:** `https://seudominio.com/webhooks/meta`
   - **Token de verificação:** `gerar_token_seguro_123` (anote!)
4. Clique em **"Verificar e salvar"**

⚠️ **IMPORTANTE:** O webhook só funcionará em produção (HTTPS). Em localhost, você receberá mensagens via polling.

### 1.7 Domínio do App (Opcional mas recomendado)

1. No menu lateral, vá em **"Configurações > Básico"**
2. Role até **"Domínios do App"**
3. Adicione: `seudominio.com` (sem http/https)
4. Clique em **"Adicionar domínio"**

---

## ⚙️ PASSO 2: Configurar Credenciais no Sistema (2 min)

### 2.1 Editar arquivo de configuração

Abra o arquivo `config/meta.php` e preencha:

```php
'app_id' => '123456789012345', // ← COLE o App ID aqui
'app_secret' => 'abc123def456...', // ← COLE o App Secret aqui
```

**OU** defina variáveis de ambiente (`.env`):

```env
META_APP_ID=123456789012345
META_APP_SECRET=abc123def456...
META_WEBHOOK_VERIFY_TOKEN=gerar_token_seguro_123
APP_URL=http://localhost
```

### 2.2 Gerar Token de Webhook (se ainda não fez)

**Windows PowerShell:**
```powershell
# Gerar token aleatório seguro
-join ((48..57) + (65..90) + (97..122) | Get-Random -Count 32 | ForEach-Object {[char]$_})
```

**Linux/Mac:**
```bash
openssl rand -hex 32
```

Copie o resultado e adicione em `config/meta.php`:

```php
'webhook_verify_token' => 'TOKEN_GERADO_AQUI',
```

---

## 💾 PASSO 3: Rodar Migrations (1 min)

### Via Terminal:

```bash
cd database/migrations
php migrate.php
```

### Ou manualmente via MySQL:

```bash
php database/migrations/085_create_meta_oauth_tokens.php
php database/migrations/086_create_instagram_accounts.php
php database/migrations/087_create_whatsapp_phones.php
php database/migrations/088_add_meta_fields_to_contacts.php
```

### Verificar:

```sql
SHOW TABLES LIKE '%meta%';
SHOW TABLES LIKE '%instagram%';
SHOW TABLES LIKE '%whatsapp%';

-- Deve retornar:
-- meta_oauth_tokens
-- instagram_accounts
-- whatsapp_phones
-- (+ alterações em contacts)
```

---

## 🎉 PASSO 4: Conectar Contas via Interface (1 min)

### 4.1 Acessar Interface de Integrações

1. **Login no sistema**
2. **Menu > Integrações > Meta (Instagram + WhatsApp)**
3. Você verá a tela de integrações Meta

### 4.2 Conectar Instagram

1. Clique em **"Conectar Conta Meta"**
2. Selecione **"Instagram"**
3. Será redirecionado para Facebook/Instagram
4. **Autorize** as permissões solicitadas:
   - Gerenciar mensagens do Instagram
   - Acessar informações da página
5. Será redirecionado de volta
6. ✅ **Instagram conectado!**

### 4.3 Conectar WhatsApp

1. Na mesma página, clique em **"Adicionar WhatsApp"**
2. Será redirecionado para Facebook
3. **Autorize** as permissões:
   - Gerenciar mensagens do WhatsApp
   - Enviar mensagens em nome da empresa
4. Selecione o **número de telefone** do WhatsApp Business
5. ✅ **WhatsApp conectado!**

---

## ✅ PASSO 5: Testar (2 min)

### 5.1 Testar Instagram

1. Envie uma mensagem para sua conta Instagram Business
2. No sistema, vá em **Conversas**
3. Deve aparecer a nova conversa
4. Responda pelo sistema
5. Verifique no Instagram se recebeu

### 5.2 Testar WhatsApp

1. Envie mensagem para o número WhatsApp Business
2. No sistema, vá em **Conversas**
3. Deve aparecer a nova conversa
4. Responda pelo sistema
5. Verifique no WhatsApp se recebeu

### 5.3 Verificar Logs

```bash
# Ver logs em tempo real
tail -f storage/logs/meta_*.log

# Ou abra o arquivo:
storage/logs/meta_[DATA].log
```

---

## 🚨 PROBLEMAS COMUNS

### "ID do app inválido"
**Causa:** App ID não configurado ou inválido
**Solução:** 
1. Verifique `config/meta.php`
2. Confirme que o App ID está correto
3. Verifique se não tem espaços ou caracteres extras

### "App Secret inválido"
**Causa:** App Secret incorreto
**Solução:**
1. No Meta for Developers, vá em Configurações > Básico
2. Clique em "Mostrar" no App Secret
3. Copie novamente e cole em `config/meta.php`

### "Redirect URI mismatch"
**Causa:** URL de callback não está configurada no Meta
**Solução:**
1. Vá em Produtos > Facebook Login > Configurações
2. Adicione o URL correto em "URIs de redirecionamento"
3. Salve

### Webhook não funciona
**Causa:** Webhook precisa de HTTPS
**Solução:**
- Em produção: Configure HTTPS
- Em desenvolvimento: Use ngrok ou similar
- Alternativa: Sistema usa polling automático

### Instagram não aparece para conectar
**Causa:** Conta não é Business/Creator
**Solução:**
1. Abra Instagram
2. Vá em Configurações > Conta
3. Mude para "Conta profissional"
4. Vincule à Página do Facebook

---

## 📋 CHECKLIST FINAL

Antes de considerar concluído, verifique:

- [ ] App criado no Meta for Developers
- [ ] Instagram adicionado como produto
- [ ] WhatsApp adicionado como produto
- [ ] Facebook Login adicionado
- [ ] App ID copiado para `config/meta.php`
- [ ] App Secret copiado para `config/meta.php`
- [ ] OAuth redirect configurado
- [ ] Webhook configurado (ou deixar para produção)
- [ ] Migrations executadas
- [ ] Tabelas criadas no banco
- [ ] Instagram conectado via interface
- [ ] WhatsApp conectado via interface
- [ ] Teste de envio/recebimento funcionando
- [ ] Logs sem erros

---

## 🎯 PRÓXIMOS PASSOS

Após tudo funcionando:

1. ✅ **Testar automações** com Instagram/WhatsApp
2. ✅ **Configurar templates** de mensagem no Meta
3. ✅ **Testar webhooks** em produção
4. ✅ **Monitorar logs** e uso da API
5. ✅ **Configurar rate limits** se necessário

---

## 📚 DOCUMENTAÇÃO ADICIONAL

- **Guia Completo:** `INTEGRACAO_META_COMPLETA.md`
- **Quick Start:** `QUICK_START_META.md`
- **Changelog:** `CHANGELOG_META_INTEGRATION.md`
- **Config Exemplo:** `config/meta.example.php`

---

## 🆘 PRECISA DE AJUDA?

Verifique os logs:
```bash
tail -f storage/logs/meta_*.log
tail -f storage/logs/automation_*.log
```

Consulte a documentação oficial:
- Instagram: https://developers.facebook.com/docs/instagram-api
- WhatsApp: https://developers.facebook.com/docs/whatsapp

---

**🎉 Pronto! Agora você tem Meta (Instagram + WhatsApp) integrado!**

