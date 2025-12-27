# 🔧 Solução: Erro HTTP 400 no OAuth Meta

## 🎉 BOA NOTÍCIA!

Se você está recebendo este erro:
```json
{"success":false,"error":"Erro ao processar OAuth: Erro ao trocar code por token: HTTP 400"}
```

Significa que **as permissões estão CORRETAS**! ✅

O problema agora é na **troca do code por token** (2º passo do OAuth).

---

## 🔍 Causa do Erro HTTP 400

O erro HTTP 400 ao trocar `code` por `token` geralmente ocorre por um dos motivos:

### 1️⃣ **redirect_uri diferente** (MAIS COMUM)
O `redirect_uri` enviado na troca do token deve ser **EXATAMENTE** igual ao usado na autorização.

**✅ CORRIGIDO!** O código agora usa `Url::fullUrl()` em ambos os lugares.

### 2️⃣ **App Secret incorreto**
O `app_secret` não corresponde ao App ID.

**Como verificar:**
1. Acesse: https://developers.facebook.com/apps/990130646328644/
2. Vá em: **Configurações → Básico**
3. Clique em **"Mostrar"** no campo **App Secret**
4. Copie o valor exato

### 3️⃣ **Code expirado ou já usado**
O `code` do OAuth expira em ~10 minutos e só pode ser usado **uma vez**.

**Solução:** Tente conectar novamente (novo code será gerado).

### 4️⃣ **App em modo incorreto**
O app pode estar em modo Desenvolvimento sem configuração adequada.

---

## 🛠️ PASSO A PASSO DE CORREÇÃO

### **Passo 1: Verificar Configurações** (2 min)

Execute o script de verificação:
```
http://localhost/verificar-meta-config.php
```

Este script vai verificar:
- ✅ App ID está configurado
- ✅ App Secret está configurado
- ✅ Permissões estão corretas
- ✅ API Meta está acessível
- ✅ Credenciais são válidas

### **Passo 2: Verificar App Secret** (1 min)

**No Meta for Developers:**
1. Acesse: https://developers.facebook.com/apps/990130646328644/
2. Vá em: **Configurações → Básico**
3. Localize: **App Secret**
4. Clique em: **"Mostrar"** (pode pedir confirmação)
5. **Copie o valor EXATO**

**No seu sistema:**
1. Acesse: `http://localhost/integrations/meta`
2. Na seção **"Configuração do App Meta"**
3. Cole o **App Secret** no campo correspondente
4. Clique em **"Salvar Configurações"**

### **Passo 3: Limpar Sessão** (10 seg)

```
http://localhost/integrations/meta?clear_session=1
```

### **Passo 4: Tentar Conectar Novamente** (1 min)

1. Acesse: `http://localhost/integrations/meta`
2. Clique em: **"Conectar Instagram"**
3. Autorize as 4 permissões
4. Confirme

### **Passo 5: Verificar Logs** (se ainda der erro)

```bash
tail -f storage/logs/application.log
```

Você verá:
```
Meta OAuth - Redirect URI gerado: http://localhost/integrations/meta/oauth/callback
Meta OAuth - Auth URL completa: https://www.facebook.com/dialog/oauth?...
Meta OAuth - Exchange Token - Redirect URI: http://localhost/integrations/meta/oauth/callback
Meta OAuth - Exchange Token - HTTP Code: 400
Meta OAuth - Exchange Token - Response: {"error":{"message":"...","type":"...","code":...}}
```

---

## 🚨 Erros Específicos e Soluções

### **Erro: "Invalid redirect_uri"**

**Causa:** O redirect_uri não corresponde ao configurado no Meta App.

**Solução:**
1. Acesse: https://developers.facebook.com/apps/990130646328644/
2. Vá em: **Produtos → Facebook Login → Configurações**
3. Campo: **"URIs de redirecionamento do OAuth válidos"**
4. Adicione: `http://localhost/integrations/meta/oauth/callback`
5. Clique em: **"Salvar alterações"**
6. Tente novamente

### **Erro: "Invalid verification code"**

**Causa:** O code expirou ou já foi usado.

**Solução:**
1. Limpe a sessão: `http://localhost/integrations/meta?clear_session=1`
2. Tente conectar novamente (novo code será gerado)

### **Erro: "Invalid app_secret"**

**Causa:** O App Secret está incorreto.

**Solução:**
1. Verifique o App Secret no Meta for Developers
2. Certifique-se de copiar o valor **EXATO** (sem espaços no início/fim)
3. Reconfigure no sistema: `http://localhost/integrations/meta`
4. Salve e tente novamente

### **Erro: "App is in development mode"**

**Causa:** App em desenvolvimento sem testadores configurados.

**Solução:**
1. Vá em: **Funções → Testadores**
2. Adicione sua conta Instagram como testador
3. Aceite o convite
4. Tente novamente

---

## 🔬 Diagnóstico Avançado

### **Ver Resposta Completa da Meta**

Os logs agora mostram a resposta completa do erro:

```bash
tail -f storage/logs/application.log | grep "Meta OAuth"
```

Exemplo de saída:
```
Meta OAuth - Exchange Token - Response: {
  "error": {
    "message": "Invalid redirect_uri: Given URL is not allowed by the Application configuration.",
    "type": "OAuthException",
    "code": 191,
    "fbtrace_id": "ABC123..."
  }
}
```

### **Códigos de Erro Comuns**

| Código | Significado | Solução |
|--------|-------------|---------|
| 100 | Invalid parameter | Verificar todos os parâmetros (app_id, app_secret, code, redirect_uri) |
| 190 | Access token invalid | Code expirado, gerar novo |
| 191 | Redirect URI mismatch | Configurar redirect_uri no Meta App |
| 400 | Invalid OAuth parameters | Verificar App Secret e redirect_uri |

---

## ✅ Checklist de Verificação

- [ ] Script de verificação executado (`/verificar-meta-config.php`)
- [ ] App ID está correto
- [ ] App Secret está correto (copiar do Meta for Developers)
- [ ] Redirect URI configurado no Meta App: `http://localhost/integrations/meta/oauth/callback`
- [ ] Domínio `localhost` configurado em: Domínios do App
- [ ] Produtos instalados: Facebook Login + Instagram
- [ ] Conta adicionada como testador (se em modo Desenvolvimento)
- [ ] Sessão limpa
- [ ] Logs verificados
- [ ] Nova tentativa realizada

---

## 📊 O Que Foi Corrigido

### **Antes:**
```php
// Na autorização
$redirectUri = Url::fullUrl('/integrations/meta/oauth/callback');  ✅

// Na troca do token
'redirect_uri' => self::$config['oauth']['redirect_uri'],  ❌ (diferente!)
```

### **Depois:**
```php
// Na autorização
$redirectUri = Url::fullUrl('/integrations/meta/oauth/callback');  ✅

// Na troca do token
$redirectUri = Url::fullUrl('/integrations/meta/oauth/callback');  ✅ (igual!)
```

**🎉 Agora usa a MESMA URL em ambos os lugares!**

---

## 🎯 Próximos Passos

1. Execute: `http://localhost/verificar-meta-config.php`
2. Verifique o App Secret
3. Limpe a sessão
4. Tente conectar novamente
5. **Se funcionar:** Você verá a conta conectada! 🎉
6. **Se ainda der erro:** Copie a mensagem completa dos logs e me envie

---

## 📚 Arquivos Relacionados

- **Verificação de Config:** `public/verificar-meta-config.php` (NOVO)
- **Controller OAuth:** `app/Controllers/MetaOAuthController.php` (CORRIGIDO)
- **Guia Completo:** `META_PRONTO_PARA_USAR.md`
- **Permissões:** `PERMISSOES_INSTAGRAM_FINAIS.md`

---

**Execute o script de verificação e me diga o resultado!** 🔍

