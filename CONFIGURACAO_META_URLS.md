# 🌐 Configuração de URLs Meta - Guia Completo

## 📋 Resumo

O sistema agora **gera automaticamente** as URLs completas necessárias para configurar o Meta App, incluindo protocolo e domínio.

---

## 🔗 URLs Geradas Automaticamente

### 1️⃣ Redirect URI (OAuth)

**Formato gerado:**
```
https://seu-dominio.com/integrations/meta/oauth/callback
```

**Onde configurar no Meta:**
- Facebook Login → Configurações → **URIs de redirecionamento válidos**

**Características:**
- ✅ URL completa com HTTPS (exceto localhost)
- ✅ Detecta automaticamente o domínio da aplicação
- ✅ Botão "Copiar" para facilitar configuração
- ✅ Campo read-only para evitar edição acidental

---

### 2️⃣ Webhook URL

**Formato gerado:**
```
https://seu-dominio.com/webhooks/meta
```

**Onde configurar no Meta:**
- Webhooks → **URL de callback**

**Características:**
- ✅ URL completa com HTTPS (exceto localhost)
- ✅ Detecta automaticamente o domínio da aplicação
- ✅ Botão "Copiar" para facilitar configuração
- ✅ Campo read-only para evitar edição acidental

---

## 🎯 Como Usar

### Passo 1: Acessar a página de configuração
```
https://seu-dominio.com/integrations/meta
```

### Passo 2: Copiar as URLs

1. **Redirect URI:**
   - Clique no botão "Copiar" ao lado do campo
   - Cole no Meta for Developers → Facebook Login → URIs de redirecionamento

2. **Webhook URL:**
   - Clique no botão "Copiar" ao lado do campo
   - Cole no Meta for Developers → Webhooks → URL de callback

### Passo 3: Configurar o Meta App

Preencha os campos:
- **App ID**: ID do seu app Meta
- **App Secret**: Secret do seu app Meta
- **Webhook Verify Token**: Token gerado (clique em "Gerar Token")

### Passo 4: Salvar

Clique em "Salvar Configurações" - as credenciais serão armazenadas com segurança em `storage/config/meta.json`.

---

## 🔒 Detecção Automática de Protocolo

### Produção (Servidor externo)
- ✅ Sempre usa **HTTPS** automaticamente
- Exemplo: `https://meusite.com/integrations/meta/oauth/callback`

### Desenvolvimento (Localhost)
- ✅ Detecta se está usando HTTP ou HTTPS
- Exemplos:
  - `http://localhost/integrations/meta/oauth/callback`
  - `http://localhost:8000/integrations/meta/oauth/callback`
  - `https://localhost/integrations/meta/oauth/callback` (se SSL configurado)

### Domínios locais (.local, .test)
- ✅ Tratados como desenvolvimento
- Exemplo: `http://chat.local/integrations/meta/oauth/callback`

---

## 🛠️ Lógica de Geração de URLs

### Código (app/Helpers/Url.php)

```php
public static function fullUrl(string $path = ''): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $relativePath = self::to($path);
    
    // Detectar localhost/desenvolvimento
    $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1']) || 
                   strpos($host, 'localhost') !== false ||
                   strpos($host, '.local') !== false;
    
    // Se não for localhost, sempre usar HTTPS
    if ($isLocalhost) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    } else {
        $protocol = 'https';
    }
    
    return $protocol . '://' . $host . $relativePath;
}
```

---

## 📁 Estrutura de Diretórios

### Antes de usar pela primeira vez

Execute o script de verificação:
```
http://seu-dominio.com/check-storage.php
```

Esse script irá:
- ✅ Verificar todos os diretórios `storage/`
- ✅ Criar diretórios ausentes
- ✅ Criar `storage/config/.gitignore`
- ✅ Criar `storage/config/README.md`
- ✅ Verificar permissões de escrita

---

## 🚨 Troubleshooting

### ❌ URL mostra "localhost" em produção

**Problema:** O servidor não está configurando `$_SERVER['HTTP_HOST']` corretamente.

**Solução 1 - Apache (.htaccess):**
```apache
# Adicionar em .htaccess
RewriteCond %{HTTP_HOST} ^(.*)$ [NC]
RewriteRule ^(.*)$ - [E=HTTP_HOST:%1]
```

**Solução 2 - Nginx:**
```nginx
# Adicionar em nginx.conf
fastcgi_param HTTP_HOST $host;
```

**Solução 3 - Verificar configuração:**
```php
// Criar um arquivo test.php na raiz:
<?php
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'NÃO DEFINIDO') . "\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'NÃO DEFINIDO') . "\n";
```

---

### ❌ URL usa HTTP em vez de HTTPS

**Problema:** Servidor reverso (proxy) não está passando o protocolo correto.

**Solução - Nginx com Proxy:**
```nginx
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
```

**Solução - Apache:**
```apache
RequestHeader set X-Forwarded-Proto "https"
```

**Solução - Código (se necessário):**
```php
// Forçar HTTPS (adicionar em app/Config.php se necessário)
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
```

---

### ❌ Erro ao salvar configurações

**Erro:** `Erro ao criar diretório: /var/www/html/app/Controllers/../../storage/config`

**Causa:** Diretório `storage/config` não existe ou sem permissões.

**Solução:**
1. Execute: `http://seu-dominio.com/check-storage.php`
2. Ou manualmente:
   ```bash
   cd /caminho/para/projeto
   mkdir -p storage/config
   chmod 755 storage/config
   ```

---

## 📚 Arquivos Relacionados

### Frontend
- `views/integrations/meta/index.php` - Interface de configuração

### Backend
- `app/Controllers/MetaIntegrationController.php` - Lógica de salvamento
- `app/Helpers/Url.php` - Geração de URLs

### Verificação
- `public/check-storage.php` - Script de verificação de diretórios

### Documentação
- `CONFIGURACAO_META_INTERFACE.md` - Guia de interface Meta
- `PASSO_A_PASSO_META.md` - Passo a passo completo

---

## ✅ Checklist de Configuração

- [ ] Acessar `/integrations/meta`
- [ ] URLs geradas corretamente (com HTTPS em produção)
- [ ] Copiar Redirect URI para Meta for Developers
- [ ] Copiar Webhook URL para Meta for Developers
- [ ] Configurar Webhook Verify Token (copiar do campo gerado)
- [ ] Preencher App ID e App Secret
- [ ] Salvar configurações
- [ ] Testar conexão OAuth
- [ ] Conectar primeira conta Instagram/WhatsApp

---

## 🎉 Pronto!

Agora o sistema gera URLs completas automaticamente, facilitando a configuração e reduzindo erros!

**Dúvidas?** Consulte `CONFIGURACAO_META_INTERFACE.md` ou `PASSO_A_PASSO_META.md`.

