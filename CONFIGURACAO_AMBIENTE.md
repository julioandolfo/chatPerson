# Configuração de Ambiente - Subdiretório vs Raiz

## 📋 Visão Geral

O sistema foi configurado para funcionar automaticamente tanto em:
- **Subdiretório**: `/chat/` (desenvolvimento no Laragon)
- **Raiz**: `/` (produção no servidor)

## 🔧 Como Funciona

### Detecção Automática

O sistema detecta automaticamente o ambiente através do helper `Url`:

```php
use App\Helpers\Url;

// Em subdiretório (/chat/): retorna '/chat'
// Na raiz (/): retorna ''
$basePath = Url::basePath();
```

### Gerar URLs

Sempre use os helpers para gerar URLs:

```php
// URL de rota
Url::to('/dashboard');        // /chat/dashboard ou /dashboard

// URL de asset
Url::asset('css/style.css');  // /chat/assets/css/style.css ou /assets/css/style.css

// URL de API
Url::api('conversations');    // /chat/api/v1/conversations ou /api/v1/conversations
```

## 📝 Uso nas Views

### Antes (❌ Errado):
```php
<link href="/assets/css/style.css" rel="stylesheet">
<a href="/dashboard">Dashboard</a>
```

### Depois (✅ Correto):
```php
<link href="<?= Url::asset('css/style.css') ?>" rel="stylesheet">
<a href="<?= Url::to('/dashboard') ?>">Dashboard</a>
```

## 🚀 Ambientes

### Desenvolvimento (Laragon)
- **URL**: `http://localhost/chat/public/` ou `http://chat.local`
- **Base Path**: `/chat` (detectado automaticamente)
- **Assets**: `/chat/assets/...`

### Produção (Servidor)
- **URL**: `https://seudominio.com/`
- **Base Path**: `` (vazio, detectado automaticamente)
- **Assets**: `/assets/...`

## ✅ Checklist para Novos Arquivos

Ao criar novos arquivos, sempre:

- [ ] Usar `Url::to()` para rotas
- [ ] Usar `Url::asset()` para assets (CSS, JS, imagens)
- [ ] Usar `Url::api()` para endpoints da API
- [ ] Nunca usar caminhos absolutos hardcoded (`/dashboard`, `/assets/...`)

## 🔍 Exemplos Práticos

### Em um Controller:
```php
use App\Helpers\Url;
use App\Helpers\Response;

// Redirecionar
Response::redirect(Url::to('/dashboard'));

// Retornar JSON com URL
return Response::json([
    'redirect' => Url::to('/conversations/123')
]);
```

### Em uma View:
```php
<?php use App\Helpers\Url; ?>

<!-- CSS -->
<link href="<?= Url::asset('css/custom.css') ?>" rel="stylesheet">

<!-- JavaScript -->
<script src="<?= Url::asset('js/app.js') ?>"></script>

<!-- Links -->
<a href="<?= Url::to('/dashboard') ?>">Dashboard</a>
<a href="<?= Url::to('/conversations/' . $id) ?>">Ver Conversa</a>

<!-- Imagens -->
<img src="<?= Url::asset('media/logo.png') ?>" alt="Logo">

<!-- Formulários -->
<form action="<?= Url::to('/login') ?>" method="POST">
```

### Em JavaScript (se necessário):
```javascript
// Definir base path no JavaScript
const BASE_PATH = '<?= Url::basePath() ?>';

// Usar em requisições AJAX
fetch(BASE_PATH + '/api/v1/conversations')
```

## 🛠️ Configuração Manual (Opcional)

Se precisar forçar um base path específico, edite `app/Helpers/Url.php`:

```php
public static function basePath(): string
{
    // Forçar base path (descomente se necessário)
    // return '/chat';
    
    // Detecção automática (padrão)
    // ...
}
```

## 📚 Arquivos Atualizados

Os seguintes arquivos já foram atualizados para usar os helpers:

- ✅ `views/layouts/metronic/chatwoot-layout.php`
- ✅ `views/layouts/metronic/header.php`
- ✅ `views/layouts/metronic/sidebar.php`
- ✅ `views/auth/login.php`
- ✅ `views/errors/404.php`
- ✅ `views/errors/403.php`
- ✅ `views/conversations/index.php`
- ✅ `views/conversations/show.php`
- ✅ `app/Helpers/Router.php`
- ✅ `app/Helpers/Response.php`

## ⚠️ Importante

- **Nunca** use caminhos absolutos hardcoded
- **Sempre** use os helpers `Url::to()`, `Url::asset()`, `Url::api()`
- O sistema detecta automaticamente o ambiente
- Funciona tanto em desenvolvimento quanto em produção

---

**Última atualização**: Sistema configurado para funcionar em ambos os ambientes automaticamente.

