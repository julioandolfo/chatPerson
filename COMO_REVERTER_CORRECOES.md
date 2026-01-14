# 🔄 Como Reverter as Correções de Mídia

Se as correções causarem algum problema ou você quiser voltar ao código anterior, siga este guia.

---

## 📦 Arquivos de Backup Disponíveis

Os seguintes backups foram criados automaticamente:

```
✅ app/Services/WhatsAppService.php.backup
✅ app/Services/ConversationService.php.backup
```

---

## 🔙 Método 1: Reverter via PowerShell (Windows)

### Passo a Passo:

1. **Abra o PowerShell como Administrador**
   - Pressione `Win + X`
   - Clique em "Windows PowerShell (Admin)"

2. **Navegue até a pasta do projeto:**
   ```powershell
   cd C:\laragon\www\chat
   ```

3. **Execute o comando de restauração:**
   ```powershell
   Copy-Item "app\Services\WhatsAppService.php.backup" -Destination "app\Services\WhatsAppService.php" -Force
   ```

4. **Verifique se foi restaurado:**
   ```powershell
   Get-Item "app\Services\WhatsAppService.php" | Select-Object LastWriteTime
   ```

5. **Pronto!** O arquivo foi restaurado para a versão anterior.

---

## 🔙 Método 2: Reverter Manualmente (Explorador de Arquivos)

### Passo a Passo:

1. **Abra o explorador de arquivos:**
   - Navegue até: `C:\laragon\www\chat\app\Services\`

2. **Renomeie o arquivo atual (opcional - para manter como backup da tentativa):**
   - Clique com botão direito em `WhatsAppService.php`
   - Renomeie para: `WhatsAppService.php.tentativa`

3. **Copie o backup:**
   - Clique com botão direito em `WhatsAppService.php.backup`
   - Clique em "Copiar"
   - Clique com botão direito em área vazia
   - Clique em "Colar"

4. **Renomeie a cópia:**
   - Renomeie o arquivo colado de `WhatsAppService.php.backup - Cópia` para `WhatsAppService.php`

5. **Pronto!** Arquivo restaurado.

---

## 🔙 Método 3: Reverter via Git (Se usar controle de versão)

Se você usa Git e commitou antes das mudanças:

```bash
# Ver histórico de commits
git log --oneline -10

# Reverter para commit específico
git checkout <commit-hash> -- app/Services/WhatsAppService.php

# Ou descartar mudanças não commitadas
git checkout -- app/Services/WhatsAppService.php

# Ou fazer reset do arquivo específico
git restore app/Services/WhatsAppService.php
```

---

## ✅ Verificar se Reverteu Corretamente

Após reverter, abra o arquivo `app/Services/WhatsAppService.php` e procure por:

### ❌ Se ainda estiver com as CORREÇÕES (novo):
Você verá (por volta da linha 655):
```php
// ✅ CORREÇÃO: Payload simplificado conforme documentação Quepasa
$payload['url'] = $mediaUrl;
$payload['fileName'] = $audioFileName;
$payload['text'] = $captionTrim !== '' ? $captionTrim : ' ';
```

### ✅ Se REVERTEU para versão ANTIGA (backup):
Você verá (por volta da linha 655):
```php
// Enviar como áudio PTT (opção recomendada pelo Quepasa/WhatsApp)
$payload['audio'] = [
    'url' => $mediaUrl,
    'mimetype' => 'application/ogg',
    'filename' => ($mediaName ? preg_replace('/\.ogg$/i', '.ogx', $mediaName) : 'audio.ogx'),
    'ptt' => true,
    'voice' => true,
    'caption' => $captionTrim === '' ? null : $captionTrim
];
```

---

## 🧹 Limpeza (Opcional)

Se quiser remover os arquivos de backup após confirmar que tudo funciona:

```powershell
# PowerShell
Remove-Item "C:\laragon\www\chat\app\Services\WhatsAppService.php.backup"
Remove-Item "C:\laragon\www\chat\app\Services\ConversationService.php.backup"
```

**⚠️ ATENÇÃO:** Só faça isso se tiver CERTEZA que as correções funcionam ou se já commitou no Git.

---

## 🆘 Problemas ao Reverter

### Erro: "Acesso negado"
- Execute o PowerShell como Administrador
- Ou feche qualquer editor que tenha o arquivo aberto

### Erro: "Arquivo não encontrado"
- Verifique se está na pasta correta: `C:\laragon\www\chat`
- Liste os arquivos: `Get-ChildItem "app\Services\*.backup"`

### Erro: "Não consigo reverter"
- Baixe o arquivo backup direto do Git (se tiver)
- Ou peça ajuda com os logs de erro específicos

---

## 📞 Contato para Suporte

Se precisar de ajuda para reverter:

1. **Verifique os logs de erro:**
   ```bash
   tail -50 storage/logs/quepasa-*.log
   ```

2. **Tire um print da mensagem de erro**

3. **Anote qual teste falhou:**
   - [ ] Áudio iOS
   - [ ] Áudio Android
   - [ ] Vídeo
   - [ ] Documento

4. **Compartilhe:**
   - O erro específico
   - Qual dispositivo/sistema operacional
   - Se o backup existe: `ls app/Services/*.backup`

---

## 🔄 Reverter e Tentar Novamente

Se reverteu mas quer tentar as correções novamente:

1. **Primeiro, delete o arquivo atual:**
   ```powershell
   Remove-Item "app\Services\WhatsAppService.php"
   ```

2. **Copie o backup da tentativa (se salvou):**
   ```powershell
   Copy-Item "app\Services\WhatsAppService.php.tentativa" -Destination "app\Services\WhatsAppService.php"
   ```

Ou peça para recriar as correções com ajustes específicos.

---

## 📋 Checklist de Reversão

- [ ] Backup existe em `app/Services/WhatsAppService.php.backup`
- [ ] Executei o comando de cópia/restauração
- [ ] Verifiquei que o arquivo foi restaurado (data/hora de modificação mudou)
- [ ] Abri o arquivo e confirmei que está com código antigo
- [ ] Testei que o sistema voltou a funcionar como antes
- [ ] (Opcional) Removi os arquivos de backup após confirmar

---

**Tempo estimado para reverter:** 2-5 minutos

**Risco:** Baixo (backup disponível)

**Impacto:** Sistema volta ao comportamento anterior (áudio não funciona no iOS, vídeo/documento dá erro)
