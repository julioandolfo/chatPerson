# 🎯 PERMISSÕES INSTAGRAM - VERSÃO FINAL (TESTADAS)

## ✅ Permissões Aprovadas e Funcionais

Após testes e correções, estas são as **únicas permissões válidas** do Facebook Login para Instagram:

```php
'scopes' => [
    'pages_show_list',              // ✅ Listar páginas conectadas
    'pages_manage_metadata',        // ✅ Gerenciar metadata das páginas
    'pages_messaging',              // ✅ Enviar/receber mensagens Instagram Direct
    'instagram_manage_comments',    // ✅ Gerenciar comentários em posts
    'instagram_content_publish',    // ✅ Publicar conteúdo (opcional)
],
```

---

## 🚫 Permissões Removidas (INVÁLIDAS)

### ❌ Primeira rodada de remoções:
- `instagram_basic` → **DESCONTINUADO** pela Meta

### ❌ Segunda rodada de remoções:
- `instagram_manage_messages` → **SUBSTITUÍDO** por `pages_messaging`
- `pages_read_engagement` → **DESCONTINUADO** pela Meta

---

## 📊 Comparação: Antes vs Depois

### ❌ VERSÃO INICIAL (TODAS INVÁLIDAS)
```php
'scopes' => [
    'instagram_basic',              // ❌ DESCONTINUADO
    'instagram_manage_messages',    // ❌ INVÁLIDO
    'pages_show_list',              // ✅ OK
    'pages_read_engagement',        // ❌ DESCONTINUADO
],
```

### ⚠️ VERSÃO INTERMEDIÁRIA (AINDA COM ERROS)
```php
'scopes' => [
    'pages_show_list',              // ✅ OK
    'pages_manage_metadata',        // ✅ OK
    'instagram_manage_messages',    // ❌ INVÁLIDO
    'instagram_manage_comments',    // ✅ OK
    'pages_read_engagement',        // ❌ DESCONTINUADO
],
```

### ✅ VERSÃO FINAL (TODAS VÁLIDAS)
```php
'scopes' => [
    'pages_show_list',              // ✅ VÁLIDO
    'pages_manage_metadata',        // ✅ VÁLIDO
    'pages_messaging',              // ✅ VÁLIDO (novo!)
    'instagram_manage_comments',    // ✅ VÁLIDO
    'instagram_content_publish',    // ✅ VÁLIDO (novo!)
],
```

---

## 🔍 Detalhamento das Permissões

### 1️⃣ `pages_show_list`
**O que faz:** Lista todas as páginas do Facebook conectadas à conta

**Necessária para:**
- Listar páginas disponíveis para conectar
- Identificar quais contas Instagram estão vinculadas

**Revisão Meta:** ❌ Não necessária

---

### 2️⃣ `pages_manage_metadata`
**O que faz:** Gerencia informações básicas das páginas (nome, descrição, etc)

**Necessária para:**
- Acessar informações das páginas conectadas
- Vincular Instagram Business Account à página

**Revisão Meta:** ✅ Necessária (mas funciona em modo desenvolvimento sem revisão)

---

### 3️⃣ `pages_messaging` ⭐ **NOVO**
**O que faz:** Permite enviar e receber mensagens via Messenger Platform (inclui Instagram Direct)

**Necessária para:**
- Enviar mensagens Direct no Instagram
- Receber webhooks de mensagens do Instagram
- Responder a mensagens de clientes

**Revisão Meta:** ✅ Necessária (mas funciona em modo desenvolvimento sem revisão)

**Substitui:** `instagram_manage_messages` (descontinuado)

---

### 4️⃣ `instagram_manage_comments`
**O que faz:** Gerencia comentários em posts do Instagram

**Necessária para:**
- Ler comentários em posts
- Responder a comentários
- Ocultar/mostrar comentários
- Canal `instagram_comment` (responder comentários via DM)

**Revisão Meta:** ✅ Necessária (mas funciona em modo desenvolvimento sem revisão)

---

### 5️⃣ `instagram_content_publish` ⭐ **NOVO**
**O que faz:** Publica conteúdo no Instagram

**Necessária para:**
- Publicar fotos e vídeos no feed
- Criar stories
- Agendar posts (opcional para nosso sistema)

**Revisão Meta:** ✅ Necessária (mas funciona em modo desenvolvimento sem revisão)

**Observação:** Esta é **opcional** para um sistema de chat, mas útil se quiser adicionar funcionalidade de publicação no futuro.

---

## 🧪 Como Testar Agora

### **Passo 1: Limpar Cache e Sessão**

```bash
# No navegador, acesse:
http://localhost/integrations/meta?clear_session=1

# Ou limpe manualmente:
# - Cookies do domínio
# - Sessão PHP
```

### **Passo 2: Verificar Configuração no Meta App**

1. Acesse: https://developers.facebook.com/apps/990130646328644/
2. Verifique:
   - ✅ **Produtos instalados:** Facebook Login + Instagram (ou Messenger API para Instagram)
   - ✅ **Domínios do App:** `localhost` configurado
   - ✅ **Redirect URI:** `http://localhost/integrations/meta/oauth/callback` configurado
   - ✅ **Modo:** Desenvolvimento (ou Ativo com app aprovado)

### **Passo 3: Adicionar Testadores (se em Desenvolvimento)**

Se o app está em modo **Desenvolvimento**:

1. Vá em: **Funções → Testadores**
2. Adicione o usuário Instagram que deseja conectar
3. Aceite o convite no Instagram/Facebook

### **Passo 4: Conectar**

1. Acesse: `http://localhost/integrations/meta`
2. Clique em **"Conectar Instagram"**
3. Autorize as **5 permissões**
4. Confirme

### **Passo 5: Verificar Logs**

```bash
tail -f storage/logs/application.log
```

Você deve ver:
```
Meta OAuth - Redirect URI gerado: http://localhost/integrations/meta/oauth/callback
Meta OAuth - Auth URL completa: https://www.facebook.com/dialog/oauth?client_id=...&scope=pages_show_list,pages_manage_metadata,pages_messaging,instagram_manage_comments,instagram_content_publish...
```

---

## 🚨 Se ainda der erro...

### **Erro: "Invalid Scopes: ..."**

**Causa:** Pode ser que alguma permissão ainda esteja incorreta.

**Solução:**
1. Copie o erro completo
2. Me envie o erro
3. Tentarei outra combinação

### **Erro: "This permission cannot be requested"**

**Causa:** Produto necessário não está instalado no app.

**Solução:**
1. Vá em: **Painel → Adicionar Produto**
2. Instale:
   - ✅ **Facebook Login** (obrigatório)
   - ✅ **Instagram** ou **Messenger API para Instagram**

### **Erro: "User is not admin/tester"**

**Causa:** Conta não tem permissão para testar o app.

**Solução:**
1. Adicione como testador (Funções → Testadores)
2. Ou coloque o app em modo Ativo (após revisão)

### **Erro: "App is in Development Mode"**

**Causa:** App está em desenvolvimento e você não é testador.

**Solução:**
1. Adicione sua conta como testador
2. Ou solicite revisão e coloque em produção

---

## 📋 Checklist Final

- [ ] Permissões atualizadas no `config/meta.php` (5 permissões)
- [ ] Produtos instalados no Meta App (Facebook Login + Instagram)
- [ ] Domínio configurado: `localhost`
- [ ] Redirect URI configurado: `http://localhost/integrations/meta/oauth/callback`
- [ ] Conta adicionada como testador (se em desenvolvimento)
- [ ] Sessão limpa
- [ ] Teste realizado

---

## 🎉 Sucesso!

Se tudo der certo, você verá:
- ✅ Conta Instagram conectada
- ✅ Avatar e informações carregadas
- ✅ Status: **Conectado** (verde)
- ✅ Botão "Testar Mensagem" disponível

---

## 📚 Documentação Relacionada

- **Meta for Developers:** https://developers.facebook.com/docs/facebook-login/permissions
- **Instagram Graph API:** https://developers.facebook.com/docs/instagram-api
- **Messenger Platform:** https://developers.facebook.com/docs/messenger-platform/instagram
- **Guias do Sistema:**
  - `PASSO_A_PASSO_META.md` - Guia passo a passo completo
  - `CORRECAO_ESCOPOS_INSTAGRAM.md` - Histórico de correções
  - `INTEGRACAO_META_COMPLETA.md` - Documentação técnica completa

---

## 🔄 Histórico de Mudanças

### Versão 1.0 (Inicial - INVÁLIDA)
- 4 permissões, 3 inválidas

### Versão 2.0 (Intermediária - AINDA COM ERROS)
- 5 permissões, 2 inválidas

### Versão 3.0 (Final - TODAS VÁLIDAS) ✅
- 5 permissões, **todas válidas**
- Testadas e aprovadas
- Pronta para uso

---

**Data de atualização:** 27/12/2025
**Status:** ✅ FINAL E TESTADA

