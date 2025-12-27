# 🔧 Correção: Permissão Instagram Business Account

## 📋 Problema Identificado

A API do Meta estava retornando erro **HTTP 400** ao tentar acessar o campo `instagram_business_account` das páginas do Facebook:

```
"This endpoint requires the 'pages_read_engagement' permission"
```

## ✅ Solução Implementada

Adicionada a permissão **`pages_read_engagement`** aos scopes do Instagram em `config/meta.php`:

```php
'scopes' => [
    'pages_show_list',              // ✅ Listar páginas conectadas
    'pages_manage_metadata',        // ✅ Gerenciar metadata das páginas
    'pages_messaging',              // ✅ Enviar/receber mensagens Instagram Direct
    'pages_read_engagement',        // ✅ Ler engajamento e acessar Instagram Business Account vinculado
    'instagram_manage_comments',    // ✅ Gerenciar comentários em posts
],
```

## 🚀 Permissões Finais (5 permissões válidas)

1. **`pages_show_list`** - Listar páginas conectadas
2. **`pages_manage_metadata`** - Gerenciar metadata das páginas  
3. **`pages_messaging`** - Enviar/receber mensagens Instagram Direct
4. **`pages_read_engagement`** - Ler engajamento e acessar Instagram Business Account
5. **`instagram_manage_comments`** - Gerenciar comentários em posts

## 📝 O Que Fazer Agora

### ⚠️ IMPORTANTE: Refazer OAuth

O token atual **NÃO TEM** a permissão `pages_read_engagement`, por isso não consegue listar contas Instagram.

**Passos:**

1. Acesse: `/integrations/meta`
2. Clique em **"Conectar Instagram"** novamente
3. Na tela de autorização do Facebook, **ACEITE** todas as permissões solicitadas
4. Após conectar, as contas Instagram vinculadas às páginas serão listadas automaticamente

### 🔍 Verificação

Após refazer o OAuth, execute novamente:
- `http://localhost/chat/public/testar-instagram-api.php`

Agora deve mostrar:
- ✅ `pages_read_engagement` concedida
- ✅ Instagram Business Account encontrado para páginas vinculadas

## 📚 Documentação Meta

- **Pages Read Engagement**: https://developers.facebook.com/docs/permissions/reference/pages_read_engagement
- **Instagram Business Account**: https://developers.facebook.com/docs/instagram-api/getting-started

## ✅ Resultado Esperado

Após o OAuth, o sistema vai:
1. Buscar todas as páginas Facebook (11 páginas encontradas)
2. Para cada página, verificar se tem Instagram Business Account vinculado
3. Se tiver, buscar os dados do perfil Instagram (@username, nome, seguidores, etc)
4. Criar/atualizar `integration_accounts` com canal `instagram`
5. Listar as contas Instagram na interface

---

**Status**: ✅ Correção implementada  
**Próximo passo**: Usuário refazer OAuth para obter nova permissão

