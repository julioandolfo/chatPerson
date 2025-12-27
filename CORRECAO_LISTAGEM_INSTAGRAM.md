# 🔧 Correção: Contas Instagram Não Listadas

## 🎉 PROGRESSO!

✅ OAuth funcionou (autenticação aceita)  
❌ Contas não foram listadas

---

## 🔍 Problema Identificado

O fluxo de sincronização estava **incorreto**! O código anterior tentava usar o ID do usuário do Facebook diretamente para buscar dados do Instagram, mas o fluxo correto é:

1. Buscar **páginas do Facebook** conectadas
2. Para cada página, verificar se tem **Instagram Business Account** vinculado
3. Buscar dados da **conta Instagram** usando o Page Access Token

---

## 🔧 O Que Foi Corrigido

### **1️⃣ Novo método: `getInstagramAccounts()`**

Implementa o fluxo correto do Instagram Graph API:

```php
// Fluxo correto:
User → Facebook Pages → Instagram Business Accounts → Instagram Profiles
```

**Passo a passo:**
- ✅ Busca páginas do Facebook (`me/accounts`)
- ✅ Para cada página, verifica se tem Instagram vinculado
- ✅ Busca dados completos de cada conta Instagram
- ✅ Retorna array de contas com todos os dados

### **2️⃣ Sincronização corrigida**

```php
// ❌ ANTES (errado)
$profile = InstagramGraphService::syncProfile($metaUserId, $accessToken);

// ✅ AGORA (correto)
$instagramAccounts = $this->getInstagramAccounts($accessToken);
foreach ($instagramAccounts as $account) {
    $profile = InstagramGraphService::syncProfile($account['id'], $accessToken);
    $this->createOrUpdateIntegrationAccount('instagram', $profile, $tokenId);
}
```

### **3️⃣ Dados salvos corretamente**

- ✅ `instagram_user_id` agora é salvo corretamente
- ✅ `page_access_token` salvo no campo `config` (JSON)
- ✅ `is_active` e `is_connected` setados como `TRUE`
- ✅ Campos `name` e `status` usam nomes corretos

### **4️⃣ Logs detalhados**

Agora o sistema loga cada etapa:
- ✅ Quantas páginas foram encontradas
- ✅ Qual página está sendo verificada
- ✅ Se encontrou Instagram Business Account
- ✅ Quantas contas Instagram foram encontradas
- ✅ Erros detalhados (se houver)

---

## 🧪 TESTE AGORA (3 PASSOS)

### **Passo 1: Limpar dados antigos**

Para evitar dados corrompidos da tentativa anterior:

```sql
-- Execute no MySQL:
TRUNCATE TABLE instagram_accounts;
TRUNCATE TABLE meta_oauth_tokens;
DELETE FROM integration_accounts WHERE provider = 'meta';
```

**OU via terminal:**
```bash
cd c:\laragon\www\chat
php -r "require 'app/Helpers/Database.php'; \$db = \App\Helpers\Database::getInstance(); \$db->exec('TRUNCATE TABLE instagram_accounts'); \$db->exec('TRUNCATE TABLE meta_oauth_tokens'); \$db->exec(\"DELETE FROM integration_accounts WHERE provider = 'meta'\"); echo 'Dados limpos!';"
```

### **Passo 2: Limpar sessão**

```
http://localhost/integrations/meta?clear_session=1
```

### **Passo 3: Conectar novamente**

1. Acesse: `http://localhost/integrations/meta`
2. Clique: **"Conectar Instagram"**
3. Autorize as 4 permissões
4. Confirme

---

## 🔍 Verificar Logs

```bash
tail -f storage/logs/application.log | grep "Meta OAuth"
```

**O que você deve ver:**
```
Meta OAuth - Iniciando sincronização Instagram para user: 123456
Meta OAuth - Encontradas 2 página(s) Facebook
Meta OAuth - Verificando página: Minha Página (ID: 123456)
Meta OAuth - Conta Instagram encontrada: 987654321
Meta OAuth - Perfil Instagram carregado: @minha_conta
Meta OAuth - Encontradas 1 conta(s) Instagram
Meta OAuth - Sincronizando conta: minha_conta
```

---

## ✅ Se Funcionar

Você verá:
- ✅ **Instagram Accounts (1)** (ou mais)
- ✅ Avatar da conta exibido
- ✅ Nome de usuário (@username)
- ✅ Número de seguidores
- ✅ Status: **Conectado** (verde)
- ✅ Última sincronização: data/hora atual

---

## 🚨 Se NÃO Funcionar

### **Erro: "Instagram Accounts (0)" mesmo após conectar**

**Causa possível 1:** Página do Facebook não tem Instagram Business vinculado

**Verificação:**
1. Acesse: https://www.facebook.com/pages/
2. Selecione sua página
3. Vá em: **Configurações → Instagram**
4. Verifique se a conta Instagram está **conectada**

**Solução:**
- Conecte uma conta Instagram Business à sua página do Facebook
- Tente novamente

---

**Causa possível 2:** Conta Instagram é **PERSONAL**, não BUSINESS

**Verificação:**
1. Abra o Instagram no celular
2. Vá no seu perfil
3. Toque no menu (☰)
4. Vá em: **Configurações → Tipo de conta**
5. Verifique se é "Conta profissional"

**Solução:**
- Converta para conta Business ou Creator
- Reconecte ao Facebook
- Tente novamente

---

**Causa possível 3:** Erro na API da Meta

**Verificação:**
Verifique os logs:
```bash
tail -30 storage/logs/application.log | grep -A5 "Meta OAuth - Erro"
```

**Solução:**
- Me envie o erro completo
- Vamos diagnosticar juntos

---

## 📊 Arquivos Modificados

| Arquivo | Mudança |
|---------|---------|
| `app/Controllers/MetaOAuthController.php` | ✅ Novo método `getInstagramAccounts()` |
| | ✅ Fluxo de sincronização corrigido |
| | ✅ Logs detalhados adicionados |
| | ✅ Campos corretos (`name`, `status`) |
| | ✅ `page_access_token` salvo no `config` |
| `app/Services/InstagramGraphService.php` | ✅ `is_active` e `is_connected` setados como TRUE |

---

## 🎯 Fluxo Completo (Técnico)

```
1. User clica "Conectar Instagram"
   ↓
2. Redirect para Facebook OAuth (4 permissões)
   ↓
3. User autoriza
   ↓
4. Callback recebe code
   ↓
5. Troca code por access_token ✅
   ↓
6. Busca páginas do Facebook (me/accounts) ✅
   ↓
7. Para cada página, verifica Instagram Business Account ✅
   ↓
8. Busca dados completos do Instagram (username, followers, etc) ✅
   ↓
9. Salva em instagram_accounts (is_active=TRUE, is_connected=TRUE) ✅
   ↓
10. Salva em integration_accounts (provider=meta, channel=instagram) ✅
    ↓
11. Vincula token à integration_account ✅
    ↓
12. Redireciona com "Sucesso!" ✅
    ↓
13. Página recarrega e lista contas ✅
```

---

## 📚 Requisitos para Funcionar

- ✅ Conta Instagram deve ser **Business** ou **Creator**
- ✅ Conta Instagram deve estar conectada a uma **Página do Facebook**
- ✅ Você deve ser **administrador** da página
- ✅ App Meta deve ter produtos: **Facebook Login** + **Instagram**
- ✅ As 4 permissões devem ser autorizadas
- ✅ Domínio e Redirect URI configurados no Meta App

---

## 🎉 TESTE AGORA!

1. Limpe os dados antigos (SQL acima)
2. Limpe a sessão
3. Conecte novamente
4. **Me envie:**
   - Print da tela mostrando as contas (ou "0 contas")
   - Últimas 50 linhas do log: `tail -50 storage/logs/application.log`

**Boa sorte! 🚀**

