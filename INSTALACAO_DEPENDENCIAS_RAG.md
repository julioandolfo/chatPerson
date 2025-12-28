# 📦 INSTALAÇÃO DE DEPENDÊNCIAS - SISTEMA RAG

**Data**: 2025-01-27

---

## 🔧 DEPENDÊNCIAS NECESSÁRIAS

O sistema RAG requer as seguintes bibliotecas PHP:

- `symfony/dom-crawler` - Para parsing HTML e web scraping
- `guzzlehttp/guzzle` - Para requisições HTTP
- `symfony/css-selector` - Para seletores CSS (usado pelo DomCrawler)

---

## 📥 COMO INSTALAR

### Opção 1: Via Composer (Recomendado)

```bash
cd /caminho/para/projeto
composer require symfony/dom-crawler:^6.0
composer require guzzlehttp/guzzle:^7.0
composer require symfony/css-selector:^6.0
```

### Opção 2: Instalação Manual

Se não tiver Composer instalado:

1. **Instalar Composer**:
```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
```

2. **Instalar dependências**:
```bash
php composer.phar require symfony/dom-crawler:^6.0
php composer.phar require guzzlehttp/guzzle:^7.0
php composer.phar require symfony/css-selector:^6.0
```

---

## ✅ VERIFICAR INSTALAÇÃO

Após instalar, verifique se as dependências estão disponíveis:

```bash
php -r "require 'vendor/autoload.php'; echo 'Dependências OK!';"
```

---

## 🔄 ATUALIZAR AUTOLOAD

Após instalar, atualize o autoload:

```bash
composer dump-autoload
```

---

## 📝 NOTAS

- As dependências já foram adicionadas ao `composer.json`
- Execute `composer install` ou `composer update` para instalar
- Se estiver usando Docker, adicione ao Dockerfile se necessário

---

## 🚀 PRÓXIMOS PASSOS

Após instalar as dependências:

1. ✅ Sistema RAG estará 100% funcional
2. ✅ Web scraping funcionando
3. ✅ Crawling de URLs funcionando
4. ✅ Processamento em background funcionando

