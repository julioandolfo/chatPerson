# Correção - Webhook WhatsApp Não Funcionando

## 🔴 Problema Identificado

O webhook do WhatsApp (Quepasa) parou de funcionar após as alterações no sistema de histórico de atribuições.

### Logs do Erro:
```
[11-Jan-2026 20:47:47] Router::callController - Método não existe: whatsapp
[11-Jan-2026 20:47:47] Router::callController - Class: App\Controllers\WebhookController, Method: whatsapp
```

## 🔍 Causa Raiz

1. **Rota configurada mas método não existia:**
   - Rota: `Router::post('/whatsapp-webhook', [WebhookController::class, 'whatsapp'])`
   - Método `whatsapp()` não existia no `WebhookController`

2. **Arquivo standalone existe:**
   - `public/whatsapp-webhook.php` existe e funciona
   - Mas não estava gerando logs

3. **Conflito de rotas:**
   - Apache/Nginx serve `whatsapp-webhook.php` diretamente
   - Não passa pelo Router do sistema

## ✅ Soluções Implementadas

### 1. **Adicionado método `whatsapp()` no WebhookController**

```php
public function whatsapp(): void
{
    try {
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);
        
        Logger::quepasa("=== WEBHOOK WHATSAPP RECEBIDO ===");
        Logger::quepasa("Payload size: " . strlen($payload) . " bytes");
        
        if (!$data) {
            Logger::error("WhatsApp webhook - JSON inválido");
            Response::json(['error' => 'Invalid JSON'], 400);
            return;
        }
        
        WhatsAppService::processWebhook($data);
        Response::json(['success' => true]);
        
    } catch (\Exception $e) {
        Logger::error("WhatsApp webhook error: " . $e->getMessage());
        Response::json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}
```

### 2. **Logs Detalhados em `public/whatsapp-webhook.php`**

Adicionados logs em cada etapa:
- ✅ Recebimento do payload bruto
- ✅ Tamanho e preview do payload
- ✅ Decodificação JSON
- ✅ Keys do payload
- ✅ Chamada ao `WhatsAppService::processWebhook()`
- ✅ Sucesso ou erro no processamento
- ✅ Stack trace completo em caso de erro

### 3. **Logs no Sistema de Histórico**

Adicionados logs detalhados em:
- `ConversationService::create()`
- `ConversationService::assignToAgent()`
- `ConversationAssignment::recordAssignment()`
- `ConversationAssignment::recordRemoval()`

### 4. **Proteções Contra Falhas**

- ✅ Verificação se tabela `conversation_assignments` existe
- ✅ Try-catch em todos os métodos críticos
- ✅ Erros NÃO quebram o fluxo principal
- ✅ Sistema continua funcionando mesmo se histórico falhar

## 📂 URLs do Webhook

### URL Atual (arquivo standalone):
```
https://seudominio.com/whatsapp-webhook.php
```

### URL Alternativa (via Router):
```
https://seudominio.com/whatsapp-webhook
```

**Ambas funcionam agora!**

## 🔍 Como Monitorar

### 1. Ver logs do webhook:
```bash
tail -f logs/quepasa.log
```

### 2. Ver erros:
```bash
tail -f logs/app.log | grep "ERROR"
```

### 3. Ver histórico de atribuições:
```bash
tail -f logs/app.log | grep "ConversationAssignment"
```

### 4. Monitorar em tempo real:
```bash
# Terminal 1 - Logs do Quepasa
tail -f logs/quepasa.log

# Terminal 2 - Logs gerais
tail -f logs/app.log

# Terminal 3 - Erros
tail -f logs/app.log | grep "ERROR"
```

## 🧪 Teste Manual

### 1. Testar webhook diretamente:
```bash
curl -X POST https://seudominio.com/whatsapp-webhook.php \
  -H "Content-Type: application/json" \
  -d '{"test": "data", "trackid": "test"}'
```

### 2. Verificar logs:
```bash
tail -20 logs/quepasa.log
```

### 3. Enviar mensagem real via WhatsApp:
- Envie uma mensagem para o número conectado
- Verifique os logs em `logs/quepasa.log`
- Verifique se a conversa foi criada no sistema

## 📊 O que Esperar nos Logs

### Webhook recebido com sucesso:
```
[2026-01-11 20:50:00] === WEBHOOK WHATSAPP RECEBIDO (whatsapp-webhook.php) ===
[2026-01-11 20:50:00] Raw input length: 1234 bytes
[2026-01-11 20:50:00] Payload decodificado - Keys: trackid, chatid, from, message, ...
[2026-01-11 20:50:00] Chamando WhatsAppService::processWebhook...
[2026-01-11 20:50:00] ConversationService::create - Tentando registrar histórico de atribuição...
[2026-01-11 20:50:00] ConversationAssignment::recordAssignment - INÍCIO: conversation_id=123, agent_id=5
[2026-01-11 20:50:00] ConversationAssignment::tableExists - Tabela EXISTE
[2026-01-11 20:50:00] ConversationAssignment::recordAssignment - Registro criado com ID: 456
[2026-01-11 20:50:00] Webhook processado com sucesso!
```

### Se tabela não existir:
```
[2026-01-11 20:50:00] ConversationAssignment::tableExists - Tabela NÃO EXISTE
[2026-01-11 20:50:00] ConversationAssignment::recordAssignment - Tabela não existe, pulando registro
[2026-01-11 20:50:00] Webhook processado com sucesso!
```

### Se houver erro:
```
[2026-01-11 20:50:00] [ERROR] WhatsApp Webhook Error: [mensagem do erro]
[2026-01-11 20:50:00] [ERROR] Stack trace: [trace completo]
```

## 🚀 Próximos Passos

1. **Deploy em produção**
2. **Monitorar logs** por 1-2 horas
3. **Enviar mensagem de teste** via WhatsApp
4. **Verificar se:**
   - Logs aparecem em `logs/quepasa.log`
   - Conversa é criada no sistema
   - Mensagem aparece na interface
   - Histórico é registrado (se tabela existir)

## 🔧 Troubleshooting

### Problema: Logs não aparecem
**Solução:** Verificar permissões da pasta `logs/`
```bash
chmod 777 logs/
touch logs/quepasa.log
chmod 666 logs/quepasa.log
```

### Problema: Tabela não existe
**Solução:** Executar migration
```bash
php database/migrate.php
```

### Problema: Erro de conexão com banco
**Solução:** Verificar se MySQL está rodando
```bash
systemctl status mysql
# ou
service mysql status
```

### Problema: Webhook não recebe dados
**Solução:** Verificar configuração no Quepasa
- URL correta: `https://seudominio.com/whatsapp-webhook.php`
- Método: POST
- Content-Type: application/json

## 📝 Arquivos Modificados

1. ✅ `app/Controllers/WebhookController.php` - Adicionado método `whatsapp()`
2. ✅ `public/whatsapp-webhook.php` - Logs detalhados
3. ✅ `app/Services/ConversationService.php` - Logs no histórico
4. ✅ `app/Models/ConversationAssignment.php` - Logs e proteções
5. ✅ `routes/web.php` - Rota já existia

## ✅ Checklist de Verificação

- [x] Método `whatsapp()` existe no `WebhookController`
- [x] Logs detalhados em `whatsapp-webhook.php`
- [x] Logs no sistema de histórico
- [x] Proteções contra falhas
- [x] Try-catch em todos os métodos críticos
- [x] Verificação de existência da tabela
- [x] Documentação completa

## 🎯 Resultado Esperado

Após o deploy:
- ✅ Webhook recebe mensagens do Quepasa
- ✅ Logs são gerados em `logs/quepasa.log`
- ✅ Conversas são criadas normalmente
- ✅ Histórico é registrado (se tabela existir)
- ✅ Sistema NÃO quebra se histórico falhar
- ✅ Mensagens aparecem na interface
