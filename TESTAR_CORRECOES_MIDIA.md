# 🧪 Guia de Teste - Correções de Mídia Quepasa

## 📱 Cenários de Teste

### ✅ 1. ÁUDIO no iOS (Principal Problema)

**Antes:** Áudio não carregava no iOS (apenas Android funcionava)  
**Agora:** Deve funcionar em ambos

#### Como testar:

1. **Grave um áudio pelo sistema:**
   - Acesse uma conversa WhatsApp no sistema
   - Clique no botão de gravar áudio (🎤)
   - Grave uma mensagem de teste (ex: "Testando áudio corrigido")
   - Envie para um contato

2. **Teste no iOS:**
   - Abra o WhatsApp em um iPhone
   - Verifique se o áudio aparece
   - ✅ Deve mostrar o player de áudio
   - ✅ Deve tocar ao clicar

3. **Teste no Android:**
   - Abra o WhatsApp em um Android
   - Verifique se o áudio continua funcionando
   - ✅ Não deve quebrar nada

4. **Verificar logs:**
   ```bash
   tail -f storage/logs/quepasa-*.log
   ```
   
   Procure por:
   ```
   ✅ Payload ÁUDIO simplificado configurado:
     url: https://...
     fileName: audio.ogg
     text: '(espaço)'
   ```

---

### ✅ 2. VÍDEO (Não estava enviando)

**Antes:** Erro 400 - "text not found, do not send empty messages"  
**Agora:** Deve enviar normalmente

#### Como testar:

1. **Anexe um vídeo:**
   - Acesse uma conversa WhatsApp
   - Clique no botão de anexar (📎)
   - Selecione um vídeo MP4 (pequeno, para testar rápido)
   - Envie a mensagem

2. **Verificar envio:**
   - ✅ Não deve dar erro 400
   - ✅ Vídeo deve aparecer no WhatsApp do destinatário
   - ✅ Deve ser possível assistir ao vídeo

3. **Verificar logs:**
   ```bash
   tail -f storage/logs/quepasa-*.log
   ```
   
   Procure por:
   ```
   Payload video configurado:
     url: https://...
     fileName: video.mp4
     text: '(espaço)'
   ```

---

### ✅ 3. DOCUMENTO (Dava erro)

**Antes:** Erro 400 - "text not found, do not send empty messages"  
**Agora:** Deve enviar normalmente

#### Como testar:

1. **Anexe um documento:**
   - Acesse uma conversa WhatsApp
   - Clique no botão de anexar (📎)
   - Selecione um PDF ou DOC
   - Envie a mensagem

2. **Verificar envio:**
   - ✅ Não deve dar erro 400
   - ✅ Documento deve aparecer no WhatsApp
   - ✅ Deve ser possível baixar/abrir

3. **Verificar logs:**
   ```bash
   tail -f storage/logs/quepasa-*.log
   ```
   
   Procure por:
   ```
   Payload document configurado:
     url: https://...
     fileName: documento.pdf
     text: '(espaço)'
   ```

---

## 🔍 Validação Técnica

### Verificar Payload no Log

Após enviar qualquer mídia, verifique o log completo:

```bash
# Ver últimas 50 linhas do log Quepasa
tail -50 storage/logs/quepasa-*.log
```

#### Payload Esperado para ÁUDIO:
```json
{
  "chatId": "5511999999999@s.whatsapp.net",
  "url": "https://seudominio.com/assets/media/attachments/123/audio.ogg",
  "fileName": "audio.ogg",
  "text": " "
}
```

#### Payload Esperado para VÍDEO:
```json
{
  "chatId": "5511999999999@s.whatsapp.net",
  "url": "https://seudominio.com/assets/media/attachments/123/video.mp4",
  "fileName": "video.mp4",
  "text": " "
}
```

#### Payload Esperado para DOCUMENTO:
```json
{
  "chatId": "5511999999999@s.whatsapp.net",
  "url": "https://seudominio.com/assets/media/attachments/123/documento.pdf",
  "fileName": "documento.pdf",
  "text": " "
}
```

---

## ⚠️ Problemas Conhecidos a Verificar

### Se áudio ainda não funcionar no iOS:

1. **Verificar conversão WebM → OGG:**
   ```bash
   # Verificar se ffmpeg está instalado
   ffmpeg -version
   ```
   
   Se não estiver instalado, o áudio não será convertido.

2. **Verificar URL pública:**
   ```bash
   curl -I https://seudominio.com/assets/media/attachments/123/audio.ogg
   ```
   
   Deve retornar HTTP 200.

3. **Verificar extensão do arquivo:**
   - Deve ser `.ogg` (não `.ogx`)
   - Verifique no sistema de arquivos:
   ```bash
   ls -la public/assets/media/attachments/*/
   ```

### Se vídeo/documento ainda dá erro:

1. **Verificar campo text no payload:**
   - Veja o log: deve ter `"text": " "` ou `"text": "legenda"`
   - Se aparecer `"text": null`, ainda há problema

2. **Verificar URL acessível:**
   ```bash
   curl -I https://seudominio.com/assets/media/attachments/123/video.mp4
   ```

---

## 📊 Checklist de Validação

### Áudio:
- [ ] Gravação funciona no sistema
- [ ] Upload do arquivo .webm → conversão para .ogg
- [ ] Arquivo final tem extensão .ogg (não .ogx)
- [ ] Envio não dá erro
- [ ] Áudio carrega no iOS
- [ ] Áudio continua funcionando no Android
- [ ] Payload no log está simplificado (sem `audio: {...}`)

### Vídeo:
- [ ] Upload do vídeo funciona
- [ ] Envio não dá erro 400
- [ ] Vídeo aparece no WhatsApp
- [ ] Vídeo pode ser assistido
- [ ] Payload no log tem campo `text`

### Documento:
- [ ] Upload do documento funciona
- [ ] Envio não dá erro 400
- [ ] Documento aparece no WhatsApp
- [ ] Documento pode ser baixado
- [ ] Payload no log tem campo `text`

---

## 🔄 Se Precisar Reverter

**Código anterior ainda disponível em:**
- `app/Services/WhatsAppService.php.backup`
- `app/Services/ConversationService.php.backup`

**Para reverter:**
```powershell
# PowerShell (Windows)
Copy-Item "c:\laragon\www\chat\app\Services\WhatsAppService.php.backup" -Destination "c:\laragon\www\chat\app\Services\WhatsAppService.php" -Force
```

---

## 📞 Teste Completo Passo a Passo

### Roteiro Completo:

1. **Preparar ambiente de teste:**
   - Tenha um iPhone disponível com WhatsApp
   - Tenha um Android disponível com WhatsApp
   - Acesse o sistema em: http://localhost/chat

2. **Teste 1 - Áudio no iOS:**
   - [ ] Grave áudio pelo sistema
   - [ ] Envie para iPhone
   - [ ] Verifique se carrega e toca
   - [ ] ✅ PASSOU | ❌ FALHOU

3. **Teste 2 - Áudio no Android:**
   - [ ] Grave áudio pelo sistema
   - [ ] Envie para Android
   - [ ] Verifique se continua funcionando
   - [ ] ✅ PASSOU | ❌ FALHOU

4. **Teste 3 - Vídeo:**
   - [ ] Anexe vídeo MP4
   - [ ] Envie mensagem
   - [ ] Verifique se não dá erro
   - [ ] Verifique se aparece no WhatsApp
   - [ ] ✅ PASSOU | ❌ FALHOU

5. **Teste 4 - Documento:**
   - [ ] Anexe PDF ou DOC
   - [ ] Envie mensagem
   - [ ] Verifique se não dá erro
   - [ ] Verifique se aparece no WhatsApp
   - [ ] ✅ PASSOU | ❌ FALHOU

6. **Teste 5 - Com legenda:**
   - [ ] Anexe qualquer mídia
   - [ ] Digite uma legenda
   - [ ] Envie
   - [ ] Verifique se legenda aparece
   - [ ] ✅ PASSOU | ❌ FALHOU

---

## 📝 Reportar Resultados

Após testar, anote os resultados:

```
=== RESULTADOS DOS TESTES ===

Data: ___________
Hora: ___________

[ ] Áudio iOS: ✅ PASSOU | ❌ FALHOU
    Detalhes: _________________________________

[ ] Áudio Android: ✅ PASSOU | ❌ FALHOU  
    Detalhes: _________________________________

[ ] Vídeo: ✅ PASSOU | ❌ FALHOU
    Detalhes: _________________________________

[ ] Documento: ✅ PASSOU | ❌ FALHOU
    Detalhes: _________________________________

[ ] Legendas: ✅ PASSOU | ❌ FALHOU
    Detalhes: _________________________________

Observações Gerais:
_________________________________________________
_________________________________________________
```

---

**Boa sorte com os testes! 🚀**
