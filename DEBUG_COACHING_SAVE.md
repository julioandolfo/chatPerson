# 🐛 Debug: Por que Coaching não está salvando?

## ✅ Correções Aplicadas:

1. **Checkboxes corrigidos** no `SettingsController.php`
2. **JavaScript de toggle** adicionado
3. **Logs de debug** adicionados
4. **Script duplicado** removido

---

## 🧪 Como Testar:

### **1. Verificar estado atual:**

Acesse: `https://chat.personizi.com.br/debug-coaching-save.php`

Isso mostra o que está salvo no banco atualmente.

---

### **2. Testar salvamento:**

1. Ir em **Configurações > Conversas**
2. Rolar até **"Coaching em Tempo Real (IA)"**
3. **Abrir Console do Navegador** (F12 > Console)
4. **Marcar** o checkbox "Habilitar Coaching em Tempo Real"
5. **Marcar** algumas opções (ex: Usar Fila, Usar Cache, alguns tipos de hint)
6. **Clicar em Salvar**

---

### **3. Verificar logs:**

#### **No Console do Navegador:**
```
=== DEBUG COACHING ===
realtime_coaching[enabled]: 1
realtime_coaching[model]: gpt-3.5-turbo
realtime_coaching[temperature]: 0.5
... etc
======================
```

#### **No Log do PHP:**
```bash
# Linux
tail -f /var/log/apache2/error.log
# ou
tail -f /var/log/php-fpm/error.log

# Ver se aparece:
DEBUG COACHING: {"enabled":"1","model":"gpt-3.5-turbo",...}
```

---

### **4. Verificar se salvou:**

Acesse novamente: `https://chat.personizi.com.br/debug-coaching-save.php`

Deve mostrar:
```
✅ Seção realtime_coaching existe

CONFIGURAÇÕES:
-------------
enabled: true
model: gpt-3.5-turbo
...
```

---

### **5. Dar refresh na página:**

Ir em **Configurações > Conversas** novamente e verificar se as opções marcadas continuam marcadas.

---

## 🔍 Possíveis Problemas:

### **Problema 1: Console não mostra nada**
**Causa:** JavaScript não está carregando  
**Solução:** Limpar cache do navegador (Ctrl+Shift+Del)

### **Problema 2: Console mostra dados, mas não salva**
**Causa:** Erro no backend  
**Solução:** Verificar logs do PHP

### **Problema 3: Salva, mas ao dar refresh volta ao padrão**
**Causa:** Lendo de outro lugar ou cache  
**Solução:** Verificar `ConversationSettingsService::getSettings()`

### **Problema 4: Checkboxes desmarcados não salvam como false**
**Causa:** FormData não envia checkboxes desmarcados  
**Solução:** Já corrigido - agora verifica `&& $data['...']`

---

## 📝 Checklist de Debug:

- [ ] Acessar `/debug-coaching-save.php` ANTES de salvar
- [ ] Abrir Console (F12)
- [ ] Marcar opções de coaching
- [ ] Clicar em Salvar
- [ ] Ver logs no Console
- [ ] Ver logs no PHP (se tiver acesso)
- [ ] Acessar `/debug-coaching-save.php` DEPOIS de salvar
- [ ] Dar refresh na página de configurações
- [ ] Verificar se opções continuam marcadas

---

## 🎯 Se AINDA não funcionar:

Envie os seguintes dados:

1. **Output de `/debug-coaching-save.php` ANTES de salvar**
2. **Output do Console ao salvar** (copiar tudo)
3. **Output de `/debug-coaching-save.php` DEPOIS de salvar**
4. **Logs do PHP** (se tiver acesso)

---

## 🔧 Arquivos Modificados:

- `app/Controllers/SettingsController.php` (linha 660-681)
- `views/settings/conversations-tab.php` (linha 946-960, 1234-1242)
- `views/settings/action-buttons/realtime-coaching-config.php` (removido script duplicado)
- `public/debug-coaching-save.php` (novo)
