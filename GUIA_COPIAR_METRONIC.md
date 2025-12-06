# GUIA PARA COPIAR ARQUIVOS DO METRONIC

## ⚠️ IMPORTANTE
- A pasta `metronic/` contém os arquivos **originais** do tema
- **NÃO referenciar diretamente** arquivos de `metronic/` no código
- Copiar apenas os arquivos necessários para `public/assets/`
- Manter estrutura organizada em `public/assets/`

---

## 📁 ESTRUTURA DE DESTINO

```
public/
└── assets/
    ├── css/
    │   ├── metronic/          # CSS do Metronic
    │   └── custom/            # CSS customizado
    ├── js/
    │   ├── metronic/          # JS do Metronic
    │   └── custom/            # JS customizado
    ├── plugins/               # Plugins JS/CSS
    └── media/                 # Imagens, ícones, etc
```

---

## 📋 ARQUIVOS NECESSÁRIOS DO METRONIC

### 1. CSS (Obrigatório)

**De**: `metronic/assets/css/`
**Para**: `public/assets/css/metronic/`

```
✅ style.bundle.css          # CSS principal do Metronic
```

**De**: `metronic/assets/plugins/global/`
**Para**: `public/assets/plugins/global/`

```
✅ plugins.bundle.css        # Plugins CSS globais
```

### 2. JavaScript (Obrigatório)

**De**: `metronic/assets/js/`
**Para**: `public/assets/js/metronic/`

```
✅ scripts.bundle.js         # Scripts principais
✅ widgets.bundle.js         # Widgets
```

**De**: `metronic/assets/js/custom/`
**Para**: `public/assets/js/metronic/custom/`

```
✅ apps/chat/chat.js         # Componente de chat (referência)
✅ layout/                   # Scripts de layout
```

### 3. Plugins (Conforme Necessário)

**De**: `metronic/assets/plugins/`
**Para**: `public/assets/plugins/`

```
✅ custom/                   # Plugins customizados
✅ global/                   # Plugins globais (fonts, etc)
```

### 4. Media (Conforme Necessário)

**De**: `metronic/assets/media/`
**Para**: `public/assets/media/`

```
✅ logos/                    # Logos do sistema
✅ icons/                    # Ícones (se necessário)
✅ avatars/                  # Avatares padrão
✅ illustrations/            # Ilustrações (se necessário)
```

### 5. Fonts/Icons (Obrigatório)

**De**: `metronic/assets/plugins/global/fonts/`
**Para**: `public/assets/plugins/global/fonts/`

```
✅ KeenIcons/                # Fontes de ícones
```

---

## 🔧 PROCESSO DE CÓPIA

### Opção 1: Cópia Manual (Recomendado para início)

1. **Criar estrutura de pastas**:
```bash
mkdir -p public/assets/css/metronic
mkdir -p public/assets/js/metronic
mkdir -p public/assets/plugins
mkdir -p public/assets/media
```

2. **Copiar arquivos CSS**:
```bash
# Windows PowerShell
Copy-Item "metronic\assets\css\style.bundle.css" "public\assets\css\metronic\"
Copy-Item "metronic\assets\plugins\global\plugins.bundle.css" "public\assets\plugins\global\"
```

3. **Copiar arquivos JS**:
```bash
Copy-Item "metronic\assets\js\scripts.bundle.js" "public\assets\js\metronic\"
Copy-Item "metronic\assets\js\widgets.bundle.js" "public\assets\js\metronic\"
```

4. **Copiar plugins necessários**:
```bash
# Copiar apenas plugins que serão usados
Copy-Item "metronic\assets\plugins\custom\*" "public\assets\plugins\custom\" -Recurse
```

5. **Copiar media**:
```bash
# Copiar apenas o necessário
Copy-Item "metronic\assets\media\logos" "public\assets\media\" -Recurse
Copy-Item "metronic\assets\media\avatars" "public\assets\media\" -Recurse
```

### Opção 2: Script PHP (Para automatizar)

Criar arquivo `scripts/copy-metronic.php`:

```php
<?php
/**
 * Script para copiar arquivos necessários do Metronic
 * Execute: php scripts/copy-metronic.php
 */

$metronicPath = __DIR__ . '/../metronic';
$publicPath = __DIR__ . '/../public/assets';

// Criar estrutura de pastas
$dirs = [
    'css/metronic',
    'js/metronic',
    'plugins/global',
    'plugins/custom',
    'media/logos',
    'media/avatars',
    'media/icons'
];

foreach ($dirs as $dir) {
    $fullPath = $publicPath . '/' . $dir;
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0755, true);
        echo "Criado: $fullPath\n";
    }
}

// Arquivos para copiar
$files = [
    // CSS
    'assets/css/style.bundle.css' => 'css/metronic/style.bundle.css',
    'assets/plugins/global/plugins.bundle.css' => 'plugins/global/plugins.bundle.css',
    
    // JS
    'assets/js/scripts.bundle.js' => 'js/metronic/scripts.bundle.js',
    'assets/js/widgets.bundle.js' => 'js/metronic/widgets.bundle.js',
    
    // Plugins (copiar diretórios inteiros)
    'assets/plugins/global/fonts' => 'plugins/global/fonts',
];

// Copiar arquivos
foreach ($files as $source => $dest) {
    $sourcePath = $metronicPath . '/' . $source;
    $destPath = $publicPath . '/' . $dest;
    
    if (is_file($sourcePath)) {
        copy($sourcePath, $destPath);
        echo "Copiado: $source -> $dest\n";
    } elseif (is_dir($sourcePath)) {
        copyDirectory($sourcePath, $destPath);
        echo "Copiado diretório: $source -> $dest\n";
    } else {
        echo "Não encontrado: $source\n";
    }
}

// Copiar diretórios de media (apenas necessários)
$mediaDirs = ['logos', 'avatars', 'icons'];
foreach ($mediaDirs as $dir) {
    $source = $metronicPath . '/assets/media/' . $dir;
    $dest = $publicPath . '/media/' . $dir;
    if (is_dir($source)) {
        copyDirectory($source, $dest);
        echo "Copiado media: $dir\n";
    }
}

function copyDirectory($source, $dest) {
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        $destPath = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        
        if ($item->isDir()) {
            if (!is_dir($destPath)) {
                mkdir($destPath, 0755, true);
            }
        } else {
            copy($item, $destPath);
        }
    }
}

echo "\nConcluído!\n";
```

### Opção 3: Script Batch (Windows)

Criar arquivo `scripts/copy-metronic.bat`:

```batch
@echo off
echo Copiando arquivos do Metronic...

REM Criar estrutura de pastas
if not exist "public\assets\css\metronic" mkdir "public\assets\css\metronic"
if not exist "public\assets\js\metronic" mkdir "public\assets\js\metronic"
if not exist "public\assets\plugins\global" mkdir "public\assets\plugins\global"
if not exist "public\assets\media\logos" mkdir "public\assets\media\logos"
if not exist "public\assets\media\avatars" mkdir "public\assets\media\avatars"

REM Copiar CSS
copy "metronic\assets\css\style.bundle.css" "public\assets\css\metronic\"
copy "metronic\assets\plugins\global\plugins.bundle.css" "public\assets\plugins\global\"

REM Copiar JS
copy "metronic\assets\js\scripts.bundle.js" "public\assets\js\metronic\"
copy "metronic\assets\js\widgets.bundle.js" "public\assets\js\metronic\"

REM Copiar fonts
xcopy "metronic\assets\plugins\global\fonts" "public\assets\plugins\global\fonts" /E /I /Y

REM Copiar media
xcopy "metronic\assets\media\logos" "public\assets\media\logos" /E /I /Y
xcopy "metronic\assets\media\avatars" "public\assets\media\avatars" /E /I /Y

echo Concluido!
pause
```

---

## 📝 CHECKLIST DE CÓPIA

### Arquivos Obrigatórios (Mínimo)
- [ ] `style.bundle.css`
- [ ] `plugins.bundle.css`
- [ ] `scripts.bundle.js`
- [ ] `widgets.bundle.js`
- [ ] Fontes (KeenIcons)

### Arquivos Opcionais (Conforme Necessário)
- [ ] Plugins customizados específicos
- [ ] Componentes JS específicos (chat, kanban, etc)
- [ ] Media (logos, avatares, ícones)
- [ ] Ilustrações

---

## 🔍 VERIFICAÇÃO

Após copiar, verificar:

1. **Estrutura criada corretamente**:
```bash
ls -R public/assets/
```

2. **Arquivos presentes**:
```bash
# Verificar se arquivos principais existem
test -f public/assets/css/metronic/style.bundle.css && echo "CSS OK" || echo "CSS FALTANDO"
test -f public/assets/js/metronic/scripts.bundle.js && echo "JS OK" || echo "JS FALTANDO"
```

3. **Permissões** (Linux/Mac):
```bash
chmod -R 755 public/assets/
```

---

## 🎯 PRÓXIMOS PASSOS

Após copiar os arquivos:

1. ✅ Verificar estrutura criada
2. ✅ Testar carregamento de CSS/JS
3. ✅ Criar layout base usando Metronic
4. ✅ Customizar conforme necessário
5. ✅ Documentar alterações

---

## 📚 REFERÊNCIAS

- **Metronic Docs**: Ver arquivos HTML em `metronic/` para exemplos
- **Componentes Chat**: `metronic/apps/chat/` para referência
- **Layout Base**: `metronic/index.html` para estrutura

---

**Nota**: Copie apenas o necessário. Não é preciso copiar tudo do Metronic, apenas os arquivos que serão utilizados no projeto.

