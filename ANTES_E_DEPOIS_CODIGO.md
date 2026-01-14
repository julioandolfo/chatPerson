# 🔄 Antes e Depois - Código Modificado

## 🎵 ÁUDIO

### ❌ CÓDIGO ANTERIOR (Não funcionava no iOS)

```php
if ($mediaType === 'audio') {
    Logger::quepasa("sendMessage - ✅ É ÁUDIO! Preparando envio como PTT...");
    
    // Forçar mimetype aceito pelo WhatsApp/Quepasa
    $mediaMime = 'audio/ogg';
    
    // ... verificações ...
    
    // Enviar como áudio PTT (opção recomendada pelo Quepasa/WhatsApp)
    $payload['audio'] = [
        'url' => $mediaUrl,
        'mimetype' => 'application/ogg',  // ❌ ERRADO
        'filename' => 'audio.ogx',         // ❌ ERRADO
        'ptt' => true,
        'voice' => true,
        'caption' => $captionTrim === '' ? null : $captionTrim
    ];

    // Compatibilidade: alguns provedores podem ler url/fileName/type no nível raiz
    $payload['type'] = 'audio';
    $payload['url'] = $mediaUrl;
    $payload['fileName'] = 'audio.ogx';  // ❌ ERRADO
    
    // Remover texto quando não há caption
    if ($captionTrim === '') {
        unset($payload['text']);  // ❌ ERRADO (Quepasa precisa de text)
    }
}
```

**Resultado no iOS:** ❌ Áudio não carrega

**Payload enviado:**
```json
{
  "chatId": "5511999999999@s.whatsapp.net",
  "audio": {
    "url": "https://.../audio.ogg",
    "mimetype": "application/ogg",
    "filename": "audio.ogx",
    "ptt": true,
    "voice": true
  },
  "type": "audio",
  "url": "https://.../audio.ogg",
  "fileName": "audio.ogx"
}
```

---

### ✅ CÓDIGO NOVO (Funciona no iOS)

```php
if ($mediaType === 'audio') {
    Logger::quepasa("sendMessage - ✅ É ÁUDIO! Preparando envio SIMPLIFICADO...");
    
    // Usar mimetype correto: audio/ogg (não application/ogg)
    if (empty($mediaMime) || !str_contains($mediaMime, 'ogg')) {
        $mediaMime = 'audio/ogg';  // ✅ CORRETO
    }
    
    // ... verificações ...
    
    // ✅ CORREÇÃO: Payload simplificado conforme documentação Quepasa
    $payload['url'] = $mediaUrl;
    
    // Ajustar nome do arquivo: usar extensão .ogg (não .ogx)
    $audioFileName = $mediaName ? $mediaName : 'audio.ogg';  // ✅ CORRETO
    if (!str_ends_with(strtolower($audioFileName), '.ogg')) {
        $audioFileName = preg_replace('/\.[^.]+$/', '.ogg', $audioFileName);
    }
    $payload['fileName'] = $audioFileName;
    
    // Campo text é OBRIGATÓRIO mesmo para mídia
    if ($captionTrim !== '') {
        $payload['text'] = $captionTrim;
    } else {
        $payload['text'] = ' ';  // ✅ CORRETO (Quepasa exige)
    }
    
    Logger::quepasa("sendMessage - ✅ Payload ÁUDIO simplificado configurado");
}
```

**Resultado no iOS:** ✅ Áudio carrega e toca normalmente

**Payload enviado:**
```json
{
  "chatId": "5511999999999@s.whatsapp.net",
  "url": "https://.../audio.ogg",
  "fileName": "audio.ogg",
  "text": " "
}
```

---

## 🎥 VÍDEO e 📄 DOCUMENTO

### ❌ CÓDIGO ANTERIOR (Erro 400)

```php
} else {
    Logger::quepasa("sendMessage - Não é áudio, enviando como mídia normal");
    
    // Para imagem/vídeo/documento manter envio por URL
    $payload['url'] = $options['media_url'];
    
    if (!empty($mediaName)) {
        $payload['fileName'] = $mediaName;
    }
    
    // ❌ FALTA: Campo 'text' obrigatório
}
```

**Resultado:** ❌ Erro 400 - "text not found, do not send empty messages"

**Payload enviado:**
```json
{
  "chatId": "5511999999999@s.whatsapp.net",
  "url": "https://.../video.mp4",
  "fileName": "video.mp4"
}
```

---

### ✅ CÓDIGO NOVO (Funciona)

```php
} else {
    Logger::quepasa("sendMessage - Não é áudio, enviando como mídia normal");
    
    // ✅ CORREÇÃO: Para vídeo/documento também precisa do campo text
    $payload['url'] = $options['media_url'];
    
    if (!empty($mediaName)) {
        $payload['fileName'] = $mediaName;
    }
    
    // Campo text é OBRIGATÓRIO mesmo para mídia
    if ($captionTrim !== '') {
        $payload['text'] = $captionTrim;
    } else {
        $payload['text'] = ' ';  // ✅ CORRETO (Quepasa exige)
    }
    
    Logger::quepasa("sendMessage - Payload {$mediaType} configurado");
}
```

**Resultado:** ✅ Envia sem erro

**Payload enviado:**
```json
{
  "chatId": "5511999999999@s.whatsapp.net",
  "url": "https://.../video.mp4",
  "fileName": "video.mp4",
  "text": " "
}
```

---

## 📊 Resumo das Mudanças

### ÁUDIO:

| Item | Antes | Depois |
|------|-------|--------|
| **Estrutura** | Aninhada (`audio: {...}`) | Simples (campos no root) |
| **Mimetype** | `application/ogg` | `audio/ogg` (comentado) |
| **Extensão** | `.ogx` | `.ogg` |
| **Campo text** | Removido se vazio | Sempre presente (espaço) |
| **Compatibilidade iOS** | ❌ Não funciona | ✅ Funciona |

### VÍDEO/DOCUMENTO:

| Item | Antes | Depois |
|------|-------|--------|
| **Campo text** | ❌ Ausente | ✅ Sempre presente |
| **Erro 400** | ✅ Sim | ❌ Não |
| **Envia** | ❌ Não | ✅ Sim |

---

## 🔍 Onde Encontrar no Código

**Arquivo:** `app/Services/WhatsAppService.php`

**Linhas modificadas:** Aproximadamente 606 a 688

**Função:** `public static function sendMessage(...)`

**Bloco:** Dentro de `if ($provider === 'quepasa')` → Seção de mídia

---

## 📝 Como Identificar Versão Atual

Abra `app/Services/WhatsAppService.php` e procure por volta da linha 655:

### Se encontrar isso = VERSÃO ANTIGA:
```php
$payload['audio'] = [
    'url' => $mediaUrl,
    'mimetype' => 'application/ogg',
```

### Se encontrar isso = VERSÃO NOVA (CORRIGIDA):
```php
// ✅ CORREÇÃO: Payload simplificado conforme documentação Quepasa
$payload['url'] = $mediaUrl;
```

---

## 🎯 Impacto das Mudanças

### Positivo:
- ✅ Áudio funciona no iOS
- ✅ Vídeo envia sem erro
- ✅ Documento envia sem erro
- ✅ Payload mais simples e limpo
- ✅ Compatível com documentação oficial Quepasa
- ✅ Logs mais claros

### Neutro:
- 🔄 Áudio continua funcionando no Android (sem mudança)
- 🔄 Imagens não foram afetadas (já funcionavam)
- 🔄 Conversão WebM → OGG continua automática

### Negativo:
- ❌ Nenhum impacto negativo esperado

---

## 🔗 Referências

**Documentação Quepasa:** `QUEPASA_API_DOCUMENTATION.md`  
**Linhas relevantes:** 64, 156-176, 293-305

**Trechos importantes:**

> Linha 64: "O campo `text` NÃO pode estar vazio quando enviando mídia via `url`"

> Linha 156-176: "Para áudio: Recomenda-se usar `content` com base64 (...) ou formato simples com `url` + `text` + `fileName`"

---

## 📞 Validação Visual

Após aplicar as correções, os logs devem mostrar:

### ANTES (antigo):
```
sendMessage - Payload audio configurado:
sendMessage -   url: https://...
sendMessage -   mimetype: application/ogg
sendMessage -   filename: audio.ogx
sendMessage -   ptt: true
```

### DEPOIS (novo):
```
sendMessage - ✅ Payload ÁUDIO simplificado configurado:
sendMessage -   url: https://...
sendMessage -   fileName: audio.ogg
sendMessage -   text: '(espaço)'
```

---

**Fim do documento**
