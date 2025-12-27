# ⚡ QUICK START - INTEGRAÇÃO META

## 🚀 Começar em 5 Minutos

### PASSO 1: Configurar Credenciais (2 min)

```bash
# 1. Copiar arquivo de configuração exemplo
cp config/meta.example.php config/meta.php

# 2. Editar config/meta.php
nano config/meta.php

# Preencher:
# - app_id: SEU_APP_ID (obtido no Meta for Developers)
# - app_secret: SEU_APP_SECRET
# - webhook_verify_token: GERAR_TOKEN_SEGURO
# - oauth.redirect_uri: https://SEUDOMINIO.com/integrations/meta/oauth/callback
```

**Gerar token seguro:**
```bash
openssl rand -hex 32
# Copie o resultado e cole em webhook_verify_token
```

---

### PASSO 2: Executar Migrations (1 min)

```bash
cd database/migrations
php migrate.php

# Ou manualmente:
# mysql -u root -p seubd < 085_create_meta_oauth_tokens.php
# mysql -u root -p seubd < 086_create_instagram_accounts.php
# mysql -u root -p seubd < 087_create_whatsapp_phones.php
# mysql -u root -p seubd < 088_add_meta_fields_to_contacts.php
```

**Verificar:**
```sql
SHOW TABLES LIKE '%meta%';
SHOW TABLES LIKE '%instagram%';
SHOW TABLES LIKE '%whatsapp%';
```

---

### PASSO 3: Configurar no Meta for Developers (2 min)

1. Acesse: [https://developers.facebook.com/apps/](https://developers.facebook.com/apps/)
2. Crie ou selecione um app
3. **Adicionar Produtos > Instagram + WhatsApp**
4. **Facebook Login > Configurações:**
   - URIs de redirecionamento: `https://SEUDOMINIO.com/integrations/meta/oauth/callback`
5. **Webhooks > Configurar:**
   - URL: `https://SEUDOMINIO.com/webhooks/meta`
   - Token de verificação: (o mesmo de `config/meta.php`)
   - Campos: `messages`, `message_status`

---

### PASSO 4: Conectar Contas (1 min)

#### Via Interface Web:
1. Login no sistema
2. **Menu > Integrações > Meta (Instagram + WhatsApp)**
3. Clicar em **"Conectar Conta Meta"**
4. Escolher: **Instagram**, **WhatsApp** ou **Ambos**
5. Autorizar no Facebook/Instagram
6. ✅ Pronto!

#### Para WhatsApp (adicional):
1. Na mesma página, clicar em **"Adicionar Número"**
2. Preencher:
   - **Phone Number ID**: (no painel Meta)
   - **Número**: `+5511999999999`
   - **WABA ID**: (no painel Meta)
   - **Meta User ID**: (do token OAuth)
3. Salvar
4. ✅ Pronto!

---

## 🧪 TESTAR

### Teste 1: Webhook (30 seg)

```bash
# GET (verificação)
curl "https://SEUDOMINIO.com/webhooks/meta?hub.mode=subscribe&hub.challenge=12345&hub.verify_token=SEU_TOKEN"
# ✅ Deve retornar: 12345

# POST (simulação)
curl -X POST https://SEUDOMINIO.com/webhooks/meta \
  -H "Content-Type: application/json" \
  -d '{"object":"instagram","entry":[]}'
# ✅ Deve retornar: {"status":"ok"}
```

### Teste 2: Enviar Mensagem Instagram (30 seg)

1. Na interface, clicar em **"Testar Mensagem"** (Instagram)
2. Inserir:
   - **Instagram User ID**: (numérico, do destinatário)
   - **Mensagem**: `Olá, teste da integração!`
3. Enviar
4. ✅ Verificar no Instagram Direct

### Teste 3: Enviar Mensagem WhatsApp (30 seg)

1. Clicar em **"Testar Mensagem"** (WhatsApp)
2. Inserir:
   - **Número**: `+5511999999999`
   - **Mensagem**: `Teste WhatsApp API`
3. Enviar
4. ✅ Verificar no WhatsApp

---

## 📊 VERIFICAR STATUS

### Logs em Tempo Real

```bash
tail -f storage/logs/meta.log
```

### Via Interface

1. **Menu > Integrações > Meta**
2. Clicar em **"Ver Logs"**
3. Buscar por erros ou eventos

### Status das Contas

**Instagram:**
- ✅ Badge Verde = Conectado
- ❌ Badge Vermelho = Desconectado ou token expirado

**WhatsApp:**
- ✅ Badge Verde = Conectado
- ⚠️ Badge Amarelo = Sandbox (teste)
- ❌ Badge Vermelho = Desconectado

**Qualidade WhatsApp:**
- 🟢 GREEN = Excelente (envio liberado)
- 🟡 YELLOW = Atenção (limite reduzido)
- 🔴 RED = Crítico (risco de bloqueio)

---

## 🐛 PROBLEMAS COMUNS

### ❌ "Invalid OAuth access token"
**Solução:** Reconectar conta (botão "Conectar Conta Meta")

### ❌ "Webhook signature validation failed"
**Solução:** Verificar `app_secret` em `config/meta.php`

### ❌ "Rate limit exceeded"
**Solução:** Aguardar alguns minutos (Instagram: 200/hora, WhatsApp: 80/seg)

### ❌ Instagram: "User is not receiving messages"
**Solução:** Usuário precisa enviar mensagem primeiro (limitação do Instagram)

---

## 📚 DOCUMENTAÇÃO COMPLETA

- **Setup Detalhado:** `INTEGRACAO_META_COMPLETA.md`
- **Changelog:** `CHANGELOG_META_INTEGRATION.md`
- **Configuração:** `config/meta.example.php`

---

## ✅ CHECKLIST RÁPIDO

- [ ] `config/meta.php` configurado
- [ ] Migrations executadas
- [ ] App Meta criado
- [ ] Produtos Instagram + WhatsApp adicionados
- [ ] Webhook configurado no Meta
- [ ] Contas conectadas via OAuth
- [ ] Teste de mensagem Instagram ✅
- [ ] Teste de mensagem WhatsApp ✅
- [ ] Logs funcionando

---

## 🎉 SUCESSO!

Se todos os itens acima estão ✅, sua integração está **100% FUNCIONAL!**

🚀 **Agora você pode:**
- Receber mensagens do Instagram Direct
- Receber mensagens do WhatsApp
- Responder conversas pela interface
- Usar automações
- Integrar com Notificame, Quepasa, etc.

---

**⏱️ Tempo total: ~5-10 minutos**
**🎯 Dificuldade: Fácil**
**💰 Custo: Grátis (dentro dos limites da Meta)**


