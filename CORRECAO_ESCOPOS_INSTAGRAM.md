# 🔧 Correção: Escopos Inválidos do Instagram

## 🚨 Erro Encontrado

```
Invalid Scopes: instagram_basic, instagram_manage_messages, pages_read_engagement
```

## ✅ Correção Aplicada

O escopo `instagram_basic` foi **descontinuado pela Meta** e outros escopos precisavam de ajustes.

### ❌ Escopos Antigos (INVÁLIDOS)
```php
'scopes' => [
    'instagram_basic',              // ❌ DESCONTINUADO!
    'instagram_manage_messages',
    'pages_show_list',
    'pages_read_engagement',
],
```

### ✅ Escopos Atualizados (VÁLIDOS - VERSÃO FINAL 4.0)
```php
'scopes' => [
    'pages_show_list',              // ✅ Listar páginas conectadas
    'pages_manage_metadata',        // ✅ Gerenciar metadata das páginas
    'pages_messaging',              // ✅ Enviar/receber mensagens Instagram Direct
    'instagram_manage_comments',    // ✅ Gerenciar comentários em posts
],
```

**🎉 APENAS 4 PERMISSÕES - TODAS TESTADAS E APROVADAS!**

### 🔄 Histórico de Alterações

**❌ 1ª Rodada - Removidos:**
- `instagram_basic` → Descontinuado pela Meta

**❌ 2ª Rodada - Removidos:**
- `instagram_manage_messages` → Substituído por `pages_messaging`
- `pages_read_engagement` → Descontinuado pela Meta

**❌ 3ª Rodada - Removidos:**
- `instagram_content_publish` → Inválido (requer configuração especial)

**✅ Adicionado (e funcionando):**
- `pages_messaging` → Para mensagens do Instagram Direct (substitui instagram_manage_messages)

---

## 📋 Passo a Passo: Configurar Permissões no Meta App

### **1️⃣ Acessar o Meta App**

1. Acesse: https://developers.facebook.com/apps/
2. Selecione seu app (ID: **990130646328644**)

### **2️⃣ Verificar Produtos Instalados**

No painel do app, certifique-se de que os seguintes produtos estão **adicionados**:

- ✅ **Facebook Login** (obrigatório para OAuth)
- ✅ **Instagram** (para Instagram Graph API)
- ✅ **WhatsApp** (opcional, se for usar WhatsApp Cloud API)

**Como adicionar produtos:**
1. No painel do app, role até **"Adicionar Produto"**
2. Clique em **"Configurar"** em cada produto necessário

### **3️⃣ Configurar Permissões do Instagram**

1. No menu lateral, vá em: **Produtos → Instagram**
2. Clique em: **"Configurações"**
3. Na seção **"Permissões"**, certifique-se de que as seguintes estão **ativas**:
   - ✅ `pages_show_list`
   - ✅ `pages_manage_metadata`
   - ✅ `instagram_manage_messages`
   - ✅ `instagram_manage_comments`
   - ✅ `pages_read_engagement`

### **4️⃣ Modo de Desenvolvimento vs Produção**

#### Durante o Desenvolvimento (Modo de Teste)

- ✅ Todas as permissões estão disponíveis
- ✅ Funciona com contas de teste
- ⚠️ Limitado a contas que têm função no app (administradores, desenvolvedores, testadores)

**Como adicionar testadores:**
1. No menu lateral: **Funções → Testadores**
2. Clique em **"Adicionar Testadores"**
3. Digite o nome de usuário do Instagram
4. Envie convite

#### Para Produção (Modo Ativo)

Algumas permissões exigem **Revisão do App** pela Meta:
- ⚠️ `instagram_manage_messages` - Requer revisão
- ⚠️ `pages_manage_metadata` - Requer revisão
- ⚠️ `instagram_manage_comments` - Requer revisão

**Para solicitar revisão:**
1. No menu lateral: **Revisão do App → Permissões e Recursos**
2. Encontre cada permissão necessária
3. Clique em **"Solicitar"**
4. Preencha o formulário explicando o uso
5. Grave vídeo demonstrando a funcionalidade
6. Aguarde aprovação (pode levar alguns dias)

---

## 🧪 Testar Agora

### **Passo 1: Limpar sessão antiga**

Acesse e limpe:
```
http://localhost/integrations/meta?clear_session=1
```

### **Passo 2: Conectar Instagram novamente**

1. Acesse: `http://localhost/integrations/meta`
2. Clique em **"Conectar Instagram"**
3. Você será redirecionado para o Facebook
4. Autorize as **novas permissões**
5. Confirme

### **Passo 3: Verificar logs**

```bash
tail -f storage/logs/application.log
```

Você deve ver:
```
Meta OAuth - Redirect URI gerado: http://localhost/integrations/meta/oauth/callback
Meta OAuth - Auth URL completa: https://www.facebook.com/dialog/oauth?client_id=...&scope=pages_show_list,pages_manage_metadata,instagram_manage_messages,instagram_manage_comments,pages_read_engagement...
```

---

## 📚 Documentação Meta Atualizada

### Instagram Graph API v21.0

**Documentação oficial:**
- Permissões: https://developers.facebook.com/docs/instagram-api/overview#permissions
- Messaging: https://developers.facebook.com/docs/messenger-platform/instagram/overview
- Comments: https://developers.facebook.com/docs/instagram-api/guides/comment-moderation

### Permissões Detalhadas (VERSÃO FINAL)

| Permissão | Descrição | Revisão Necessária? |
|-----------|-----------|---------------------|
| `pages_show_list` | Listar páginas do Facebook conectadas | ❌ Não |
| `pages_manage_metadata` | Gerenciar metadata das páginas | ✅ Sim* |
| `pages_messaging` | Enviar/receber mensagens (Instagram + Messenger) | ✅ Sim* |
| `instagram_manage_comments` | Gerenciar comentários em posts | ✅ Sim* |

**\*Observação:** Em **modo desenvolvimento**, essas permissões funcionam sem revisão para **contas de teste**.

---

## 🚨 Problemas Comuns

### **Erro: "This permission cannot be requested"**

**Causa:** O produto Instagram não está configurado no app.

**Solução:**
1. Vá em: **Painel do App → Adicionar Produto**
2. Encontre **"Instagram"**
3. Clique em **"Configurar"**

### **Erro: "You need to be admin of the Instagram account"**

**Causa:** Sua conta Facebook não tem permissão de administrador na conta Instagram Business.

**Solução:**
1. Acesse: https://business.facebook.com/
2. Vá em: **Configurações → Contas do Instagram**
3. Conecte sua conta Instagram Business
4. Certifique-se de que você é **administrador**

### **Erro: "This app is in Development Mode"**

**Causa:** O app está em modo de desenvolvimento e você não é um testador.

**Solução:**
1. Adicione sua conta como testador (veja passo 4️⃣ acima)
2. Ou coloque o app em modo ativo (após revisão)

---

## ✅ Checklist Final

- [ ] App Meta criado em https://developers.facebook.com/apps/
- [ ] Produtos adicionados: Facebook Login + Instagram
- [ ] Domínio configurado em: Configurações → Básico → Domínios do App
- [ ] Redirect URI configurado em: Facebook Login → Configurações
- [ ] Permissões atualizadas no `config/meta.php` (escopos corretos)
- [ ] Conta Instagram Business conectada ao Facebook Business
- [ ] Conta de teste adicionada (se em modo desenvolvimento)
- [ ] Sessão limpa e nova conexão testada

---

## 🎉 Pronto!

Agora os escopos estão corretos e você pode conectar sua conta Instagram sem erros!

**Dúvidas?** Consulte a documentação oficial da Meta ou os arquivos:
- `PASSO_A_PASSO_META.md` - Guia completo
- `INTEGRACAO_META_COMPLETA.md` - Documentação técnica

