# 🚨 CORREÇÃO URGENTE: Permissões de Conversas

## 🐛 O Problema

Um **bug crítico** estava permitindo que TODOS os agentes vissem TODAS as conversas, independente de estarem atribuídas a eles ou não.

### Causa Raiz

No arquivo `app/Services/PermissionService.php`, a lógica de verificação de níveis hierárquicos estava **INVERTIDA**:

```php
// ❌ ERRADO (ANTES)
public static function isSuperAdmin(int $userId): bool
{
    return User::hasRole($userId, 'super-admin') || User::getMaxLevel($userId) >= 0;
    // >= 0 significa níveis 0,1,2,3,4,5,6 = TODOS!
}

public static function isAdmin(int $userId): bool
{
    return User::hasRole($userId, 'admin') || User::getMaxLevel($userId) >= 1;
    // >= 1 significa níveis 1,2,3,4,5,6 = QUASE TODOS!
}
```

###Hierarquia de Níveis

No sistema, **quanto MENOR o nível, MAIOR o poder**:

| Nível | Role | Descrição |
|-------|------|-----------|
| **0** | Super Admin | Acesso total ao sistema |
| **1** | Admin | Administrador do sistema |
| **2** | Supervisor | Supervisor de equipe |
| **3** | Agente Sênior | Agente com acesso ampliado |
| **4** | **Agente** | **Agente padrão** |
| **5** | Agente Júnior | Agente com acesso limitado |
| **6** | Visualizador | Apenas visualização |

### O Impacto

**Antes da correção:**
- Um Agente (level 4) era considerado "Super Admin" porque `4 >= 0` = true ❌
- Um Agente Júnior (level 5) era considerado "Admin" porque `5 >= 1` = true ❌
- **Resultado:** TODOS podiam ver TODAS as conversas! 🚨

## ✅ A Solução

Corrigi a lógica para usar `<=` (menor ou igual):

```php
// ✅ CORRETO (AGORA)
public static function isSuperAdmin(int $userId): bool
{
    // Level 0 = Super Admin (quanto menor o nível, maior o poder)
    return User::hasRole($userId, 'super-admin') || User::getMaxLevel($userId) <= 0;
}

public static function isAdmin(int $userId): bool
{
    // Level 0-1 = Super Admin e Admin (quanto menor o nível, maior o poder)
    return User::hasRole($userId, 'admin') || User::getMaxLevel($userId) <= 1;
}
```

**Agora:**
- Apenas level 0 é Super Admin ✅
- Apenas levels 0-1 são considerados Admins ✅
- Agentes (level 4+) veem APENAS suas próprias conversas ✅

## 🧪 Como Testar

### Passo 1: Limpar Cache
Acesse:
```
http://seu-dominio/clear-permissions-cache.php
```

Este script irá:
1. Limpar cache de permissões
2. Limpar cache de conversas
3. Mostrar uma tabela com todos os usuários e suas permissões corrigidas

### Passo 2: Testar com Agente
1. Faça logout
2. Faça login com um usuário **Agente** (não admin)
3. Acesse `/conversations`
4. **Resultado esperado:** Deve ver APENAS suas próprias conversas
5. **Não deve ver:** Conversas atribuídas a outros agentes

### Passo 3: Testar com Admin
1. Faça logout
2. Faça login com um usuário **Admin** ou **Super Admin**
3. Acesse `/conversations`
4. **Resultado esperado:** Deve ver TODAS as conversas do sistema

### Passo 4: Testar Atribuição
1. Como Admin, crie ou abra uma conversa
2. Atribua ela a outro agente
3. Faça logout e login como Agente
4. **Resultado esperado:** Não deve ver a conversa atribuída ao outro agente

## 📊 Regras de Permissão

Após a correção, as regras são:

### Super Admin e Admin
- ✅ Veem **TODAS** as conversas
- ✅ Podem editar **TODAS** as conversas
- ✅ Podem atribuir qualquer conversa

### Agente (padrão)
- ✅ Vê **apenas conversas atribuídas a ele** (precisa ter `conversations.view.own`)
- ✅ Vê conversas onde é **participante**
- ✅ Vê conversas do **seu setor** (se tiver `conversations.view.department`)
- ❌ **NÃO vê** conversas de outros agentes

### Permissões Específicas
- `conversations.view.all` - Ver todas (Admin/Supervisor)
- `conversations.view.own` - Ver apenas próprias (Agente)
- `conversations.view.department` - Ver do setor (Supervisor)

## 🔧 Arquivos Modificados

1. **`app/Services/PermissionService.php`**
   - Linha 170: `>= 0` → `<= 0`
   - Linha 178: `>= 1` → `<= 1`

2. **`public/clear-permissions-cache.php`** (novo)
   - Script de limpeza de cache e teste

3. **`CORRECAO_PERMISSOES_URGENTE.md`** (este arquivo)
   - Documentação do problema e solução

## ⚠️ Ações Imediatas

1. **Rodar o script de limpeza:** `http://seu-dominio/clear-permissions-cache.php`
2. **Pedir para TODOS os usuários fazerem logout e login novamente**
3. **Testar com cada tipo de usuário** (Admin, Agente, etc)
4. **Verificar logs** para confirmar que não há mais acessos indevidos

## 🔒 Segurança

Este era um **bug crítico de segurança** que permitia:
- Agentes verem conversas de clientes de outros agentes
- Possível vazamento de informações sensíveis
- Violação de privacidade

**A correção é URGENTE e deve ser aplicada imediatamente!**

## 📞 Suporte

Se após aplicar a correção:
- Admins não conseguem ver todas as conversas
- Agentes não conseguem ver nenhuma conversa
- Há erros de permissão

Verifique:
1. O cache foi limpo?
2. Os usuários fizeram logout/login?
3. As roles estão corretamente atribuídas na tabela `user_roles`?
4. Os níveis das roles estão corretos na tabela `roles`?

---

**Data da Correção:** <?= date('d/m/Y H:i') ?>  
**Criticidade:** 🔴 ALTA  
**Status:** ✅ CORRIGIDO

