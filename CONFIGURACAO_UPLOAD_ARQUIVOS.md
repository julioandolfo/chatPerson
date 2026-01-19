# 📁 Configuração de Upload de Arquivos Grandes

**Data**: 2025-01-15
**Objetivo**: Permitir upload de arquivos até 100MB

---

## 🎯 Limites Configurados

### Por Tipo de Arquivo

| Tipo | Tamanho Máximo |
|------|----------------|
| **Imagens** | 16 MB |
| **Áudios** | 16 MB |
| **Vídeos** | 64 MB |
| **Documentos** | 100 MB |

### Configurações do PHP

```ini
upload_max_filesize = 100M   # Tamanho máximo por arquivo
post_max_size = 105M         # Tamanho máximo do POST (soma de todos os arquivos)
max_execution_time = 300     # Tempo máximo de execução (5 minutos)
max_input_time = 300         # Tempo máximo para receber dados
memory_limit = 256M          # Memória máxima do script
max_file_uploads = 20        # Número máximo de arquivos por upload
```

---

## 📝 Arquivos Modificados

### 1. `public/index.php`
- Adicionadas diretivas `ini_set()` no início do arquivo
- Garante que os limites sejam aplicados via código

### 2. `public/.htaccess`
- Adicionadas diretivas `php_value` para Apache
- Aplica configurações antes do PHP processar

### 3. `.user.ini` (NOVO)
- Arquivo de configuração PHP para CGI/FastCGI
- Lido automaticamente pelo PHP-FPM

---

## ⚙️ Como Aplicar as Mudanças

### Opção 1: Reiniciar Laragon (Recomendado)
```
1. Abra o Laragon
2. Clique em "Stop All"
3. Aguarde 5 segundos
4. Clique em "Start All"
```

### Opção 2: Reiniciar apenas o Apache
```
1. No Laragon, clique com botão direito no ícone do Apache
2. Selecione "Restart"
```

---

## ✅ Como Testar

### 1. Verificar Configurações do PHP

Crie um arquivo `phpinfo.php` na pasta `public/`:

```php
<?php
phpinfo();
?>
```

Acesse: `http://localhost/phpinfo.php`

Procure por:
- `upload_max_filesize` → Deve mostrar **100M**
- `post_max_size` → Deve mostrar **105M**
- `max_execution_time` → Deve mostrar **300**
- `memory_limit` → Deve mostrar **256M**

**⚠️ IMPORTANTE**: Delete o arquivo `phpinfo.php` após testar (segurança)

### 2. Testar Upload Real

1. Abra uma conversa
2. Anexe um arquivo entre 5-20 MB
3. Clique em "Enviar"
4. **Resultado esperado**: Upload completa com sucesso

---

## 🐛 Solução de Problemas

### Problema: Ainda não consigo enviar arquivos grandes

**Causa**: Configurações do php.ini do Laragon podem sobrescrever

**Solução**:

1. **Abra o php.ini do Laragon**:
   - Laragon → Menu → PHP → php.ini

2. **Encontre e altere estas linhas**:
   ```ini
   upload_max_filesize = 100M
   post_max_size = 105M
   max_execution_time = 300
   max_input_time = 300
   memory_limit = 256M
   ```

3. **Salve o arquivo**

4. **Reinicie o Laragon** (Stop All → Start All)

### Problema: Upload trava no meio

**Possíveis causas**:
1. **Timeout da conexão**: Aumente `max_execution_time` e `max_input_time`
2. **Memória insuficiente**: Aumente `memory_limit` para 512M
3. **Conexão lenta**: Arquivos muito grandes levam tempo para upload

### Problema: Erro "Request Entity Too Large" (413)

**Causa**: Limite do Nginx (se estiver usando)

**Solução**: Edite o nginx.conf e adicione:
```nginx
client_max_body_size 100M;
```

---

## 📊 Logs e Debug

### Verificar Erros de Upload

Os erros são logados em:
- `logs/app.log` (erros gerais)
- `logs/conversas.log` (logs de conversa)
- `logs/quepasa.log` (envios para WhatsApp)

### Códigos de Erro PHP

| Código | Significado |
|--------|-------------|
| UPLOAD_ERR_INI_SIZE (1) | Arquivo excede `upload_max_filesize` |
| UPLOAD_ERR_FORM_SIZE (2) | Arquivo excede MAX_FILE_SIZE do formulário |
| UPLOAD_ERR_PARTIAL (3) | Upload parcial (conexão interrompida) |
| UPLOAD_ERR_NO_FILE (4) | Nenhum arquivo foi enviado |
| UPLOAD_ERR_NO_TMP_DIR (6) | Pasta temporária não encontrada |
| UPLOAD_ERR_CANT_WRITE (7) | Falha ao escrever no disco |

---

## 🔒 Segurança

### Tipos de Arquivo Permitidos

**Imagens**: jpg, jpeg, png, gif, webp
**Vídeos**: mp4, webm, ogg, mov, m4v
**Áudios**: mp3, wav, ogg, webm
**Documentos**: pdf, doc, docx, xls, xlsx, txt, csv

### Validações Implementadas

✅ Validação de extensão
✅ Validação de MIME type
✅ Validação de tamanho por tipo
✅ Limpeza de nome de arquivo
✅ Armazenamento seguro em pasta protegida

---

## 📚 Referências

- Arquivo de configuração: `app/Services/AttachmentService.php`
- Limites frontend: `views/conversations/index.php` (linha ~16202)
- Limites backend: `app/Services/AttachmentService.php` (linha ~14-20)

---

## ✨ Resumo

As configurações foram aplicadas em **3 locais** para máxima compatibilidade:

1. ✅ Via código (`ini_set` no index.php)
2. ✅ Via Apache (`.htaccess`)
3. ✅ Via PHP-FPM (`.user.ini`)

**Após reiniciar o Laragon**, você poderá enviar arquivos de até:
- 16 MB (imagens/áudios)
- 64 MB (vídeos)
- 100 MB (documentos)
