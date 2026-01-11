# ✅ Correção: Scripts Standalone para Coaching

## 🔧 Problema Identificado
Os scripts de cron jobs criados inicialmente dependiam do Composer autoload (`vendor/autoload.php`), o que poderia causar problemas em ambientes como Coolify onde o Composer pode não estar disponível ou configurado corretamente.

## ✅ Solução Aplicada
Convertidos todos os scripts para **STANDALONE**, seguindo o padrão de `coaching-worker-standalone.php`:

### Scripts Corrigidos

#### 1. `public/scripts/process-coaching-learning.php`
**Antes:**
```php
// Autoload
require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap
require_once __DIR__ . '/../../config/bootstrap.php';
```

**Depois:**
```php
#!/usr/bin/env php
<?php
/**
 * Script STANDALONE - Não depende do Composer
 */

// Garantir que estamos no diretório correto
$rootDir = dirname(dirname(__DIR__));
chdir($rootDir);

// Carregar bootstrap (que já tem o autoloader nativo)
require_once $rootDir . '/config/bootstrap.php';
```

#### 2. `public/scripts/aggregate-coaching-metrics.php`
**Antes:**
```php
require_once __DIR__ . '/../../bootstrap.php';
```

**Depois:**
```php
#!/usr/bin/env php
<?php
/**
 * Script STANDALONE - Não depende do Composer
 */

// Garantir que estamos no diretório correto
$rootDir = dirname(dirname(__DIR__));
chdir($rootDir);

// Carregar bootstrap (que já tem o autoloader nativo)
require_once $rootDir . '/config/bootstrap.php';

// Garantir que o diretório de logs existe
$logDir = $rootDir . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
```

## 📝 Mudanças Aplicadas

### 1. Shebang
```php
#!/usr/bin/env php
```
- Permite executar diretamente: `./public/scripts/script.php`
- Opcional, mas boa prática

### 2. Resolução de Diretório
```php
$rootDir = dirname(dirname(__DIR__));
chdir($rootDir);
```
- Garante que o script sempre roda do diretório raiz
- Independente de onde foi chamado

### 3. Bootstrap Nativo
```php
require_once $rootDir . '/config/bootstrap.php';
```
- Usa o autoloader nativo do sistema
- Não depende do Composer
- Funciona em qualquer ambiente

### 4. Criação de Diretórios
```php
$logDir = $rootDir . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
```
- Garante que o diretório de logs existe
- Evita erros de "directory not found"

## ✅ Benefícios

### 1. Compatibilidade
- ✅ Funciona sem Composer
- ✅ Funciona em Coolify
- ✅ Funciona em qualquer servidor
- ✅ Não precisa de `composer install`

### 2. Confiabilidade
- ✅ Sempre encontra o diretório correto
- ✅ Sempre carrega as classes necessárias
- ✅ Cria diretórios automaticamente
- ✅ Logs mais informativos

### 3. Manutenção
- ✅ Padrão consistente em todos os scripts
- ✅ Fácil de entender
- ✅ Fácil de debugar
- ✅ Mesma estrutura do worker

## 🚀 Como Usar

### Execução Manual
```bash
# No diretório raiz do projeto
php public/scripts/process-coaching-learning.php
php public/scripts/aggregate-coaching-metrics.php
```

### Cron Jobs
```bash
# Aprendizado RAG (01:00 diariamente)
0 1 * * * cd /var/www/html && php public/scripts/process-coaching-learning.php >> logs/coaching-learning.log 2>&1

# Agregação de métricas (02:00 diariamente)
0 2 * * * cd /var/www/html && php public/scripts/aggregate-coaching-metrics.php >> logs/coaching-metrics.log 2>&1
```

### Coolify Scheduled Tasks
**Task 1: Aprendizado RAG**
- Comando: `php /var/www/html/public/scripts/process-coaching-learning.php`
- Schedule: `0 1 * * *`
- Enabled: ✓

**Task 2: Agregação de Métricas**
- Comando: `php /var/www/html/public/scripts/aggregate-coaching-metrics.php`
- Schedule: `0 2 * * *`
- Enabled: ✓

## 🔍 Verificação

### 1. Testar Manualmente
```bash
php public/scripts/process-coaching-learning.php
```

**Saída esperada:**
```
🧠 === PROCESSAMENTO DE APRENDIZADO DE COACHING ===
📅 Data: 2026-01-11 14:00:00
📁 Root Dir: /var/www/html

📊 Processando hints de ontem...
✅ Processamento concluído!
```

### 2. Verificar Logs
```bash
# Ver últimas 20 linhas
tail -20 logs/coaching-learning.log
tail -20 logs/coaching-metrics.log
```

### 3. Verificar Permissões
```bash
# Dar permissão de execução (opcional)
chmod +x public/scripts/process-coaching-learning.php
chmod +x public/scripts/aggregate-coaching-metrics.php
```

## 📋 Checklist de Scripts Standalone

| Script | Status | Depende Composer? |
|--------|--------|-------------------|
| `coaching-worker-standalone.php` | ✅ | ❌ Não |
| `process-coaching-queue-standalone.php` | ✅ | ❌ Não |
| `process-coaching-learning.php` | ✅ | ❌ Não |
| `aggregate-coaching-metrics.php` | ✅ | ❌ Não |

**Todos os scripts de coaching agora são STANDALONE! 🎉**

## 🐛 Troubleshooting

### Erro: "bootstrap.php not found"
```bash
# Verificar se está no diretório correto
pwd
# Deve retornar: /var/www/html (ou seu path)

# Verificar se bootstrap.php existe
ls -la config/bootstrap.php
```

### Erro: "Class not found"
```bash
# Verificar autoloader nativo
cat config/bootstrap.php | grep autoload
```

### Erro: "Permission denied"
```bash
# Dar permissão de execução
chmod +x public/scripts/*.php

# Ou executar com php explicitamente
php public/scripts/process-coaching-learning.php
```

---

**Status:** ✅ Correção Aplicada  
**Data:** 11/01/2026  
**Todos os scripts agora são STANDALONE e compatíveis com Coolify!**
