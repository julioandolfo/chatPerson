# Configuração de Cron Jobs e Instalação de Ferramentas

## 📋 Cron Jobs Necessários

Você precisa configurar **2 cron jobs** no servidor para processar mensagens agendadas e lembretes automaticamente.

### 1. Processar Mensagens Agendadas
**Script:** `public/scripts/process-scheduled-messages.php`  
**Frequência:** A cada 1 minuto  
**Comando:**
```bash
* * * * * php /caminho/completo/para/public/scripts/process-scheduled-messages.php >> /caminho/para/logs/scheduled-messages.log 2>&1
```

**Exemplo (ajuste o caminho):**
```bash
* * * * * php /home/chatperson/public_html/public/scripts/process-scheduled-messages.php >> /home/chatperson/public_html/storage/logs/scheduled-messages.log 2>&1
```

### 2. Processar Lembretes
**Script:** `public/scripts/process-reminders.php`  
**Frequência:** A cada 1 minuto  
**Comando:**
```bash
* * * * * php /caminho/completo/para/public/scripts/process-reminders.php >> /caminho/para/logs/reminders.log 2>&1
```

**Exemplo (ajuste o caminho):**
```bash
* * * * * php /home/chatperson/public_html/public/scripts/process-reminders.php >> /home/chatperson/public_html/storage/logs/reminders.log 2>&1
```

### 📝 Como Configurar no cPanel

1. Acesse **cPanel** → **Cron Jobs**
2. Adicione cada comando acima como um **Cron Job** separado
3. Configure a frequência como **"Every Minute"** ou use `* * * * *`
4. Salve

### 📝 Como Configurar via SSH

1. Acesse o servidor via SSH
2. Execute: `crontab -e`
3. Adicione as duas linhas acima
4. Salve e saia (no vim: `:wq`, no nano: `Ctrl+X` depois `Y`)

### ✅ Verificar se está funcionando

Após configurar, verifique os logs:
```bash
tail -f /caminho/para/logs/scheduled-messages.log
tail -f /caminho/para/logs/reminders.log
```

---

## 🎵 Conversão de Áudio - FFmpeg

Para converter áudios WebM para OGG/Opus (formato nativo do WhatsApp), você precisa instalar **FFmpeg**.

### O que é FFmpeg?
FFmpeg é uma ferramenta de linha de comando para processar arquivos de áudio e vídeo. O sistema usa:
- **ffmpeg** - Para converter WebM → OGG/Opus
- **ffprobe** - Para detectar se um arquivo WebM contém apenas áudio ou também vídeo

### 📦 Instalação no Linux (Ubuntu/Debian)

```bash
sudo apt update
sudo apt install ffmpeg -y
```

### 📦 Instalação no Linux (CentOS/RHEL)

```bash
sudo yum install epel-release -y
sudo yum install ffmpeg -y
```

### 📦 Instalação no Linux (via Snap)

```bash
sudo snap install ffmpeg
```

### 📦 Instalação no Windows

1. Baixe FFmpeg de: https://ffmpeg.org/download.html
2. Extraia para `C:\ffmpeg\`
3. Adicione `C:\ffmpeg\bin` ao PATH do sistema
4. Reinicie o servidor/PHP

### ✅ Verificar Instalação

Após instalar, verifique se está funcionando:

```bash
ffmpeg -version
ffprobe -version
```

Você deve ver informações sobre a versão instalada.

### 🔧 Verificar se PHP pode executar FFmpeg

Crie um arquivo de teste PHP:

```php
<?php
// test_ffmpeg.php
$ffmpeg = shell_exec('which ffmpeg 2>&1');
$ffprobe = shell_exec('which ffprobe 2>&1');

echo "FFmpeg: " . ($ffmpeg ? trim($ffmpeg) : 'NÃO ENCONTRADO') . "\n";
echo "FFprobe: " . ($ffprobe ? trim($ffprobe) : 'NÃO ENCONTRADO') . "\n";

// Testar execução
$version = shell_exec('ffmpeg -version 2>&1');
echo "\nVersão FFmpeg:\n" . substr($version, 0, 200);
```

Execute: `php test_ffmpeg.php`

### ⚠️ Importante: Permissões PHP

Certifique-se de que o PHP pode executar comandos externos:

1. Verifique se `shell_exec()` e `exec()` não estão desabilitadas no `php.ini`:
   ```ini
   disable_functions =  ; (não deve conter shell_exec ou exec)
   ```

2. Se estiver usando cPanel, pode ser necessário habilitar via **Select PHP Version** → **Extensions** → **Enable shell_exec**

### 🔍 Onde o Sistema Procura FFmpeg

O sistema procura FFmpeg/FFprobe nos seguintes locais (em ordem):

**Linux:**
- `ffmpeg` / `ffprobe` (no PATH)
- `/usr/bin/ffmpeg` / `/usr/bin/ffprobe`
- `/usr/local/bin/ffmpeg` / `/usr/local/bin/ffprobe`

**Windows:**
- `C:\ffmpeg\bin\ffmpeg.exe` / `C:\ffmpeg\bin\ffprobe.exe`

### 📊 Funcionamento sem FFmpeg

Se FFmpeg não estiver disponível:
- ✅ O sistema continua funcionando normalmente
- ⚠️ Áudios WebM não serão convertidos automaticamente
- ⚠️ O sistema tentará identificar áudios por heurística (nome do arquivo, tamanho)
- ⚠️ Áudios podem não tocar nativamente no WhatsApp (aparecer como download)

### 🎯 Recomendação

**Instale FFmpeg** para garantir que:
- Áudios gravados no chat sejam convertidos corretamente
- Áudios toquem nativamente no WhatsApp (não como download)
- Melhor experiência do usuário

---

## 📋 Resumo Rápido

### Cron Jobs (2):
```bash
* * * * * php /caminho/para/public/scripts/process-scheduled-messages.php >> /caminho/para/logs/scheduled-messages.log 2>&1
* * * * * php /caminho/para/public/scripts/process-reminders.php >> /caminho/para/logs/reminders.log 2>&1
```

### Instalação FFmpeg:
```bash
sudo apt install ffmpeg -y  # Ubuntu/Debian
```

### Verificar:
```bash
ffmpeg -version
ffprobe -version
```

---

## 🆘 Troubleshooting

### Cron não está executando?
1. Verifique os logs: `tail -f /caminho/para/logs/scheduled-messages.log`
2. Verifique permissões: `chmod +x public/scripts/process-*.php`
3. Teste manualmente: `php public/scripts/process-scheduled-messages.php`

### FFmpeg não encontrado?
1. Verifique instalação: `which ffmpeg`
2. Verifique PATH: `echo $PATH`
3. Teste manualmente: `ffmpeg -version`
4. Verifique permissões PHP: `php -i | grep disable_functions`

### Conversão de áudio não funciona?
1. Verifique logs em `storage/logs/quepasa.log`
2. Procure por mensagens: `AttachmentService::convertWebmToOpus`
3. Verifique se `shell_exec` está habilitado no PHP
4. Teste manualmente: `ffmpeg -i arquivo.webm -c:a libopus -b:a 96k -vn arquivo.ogg`

