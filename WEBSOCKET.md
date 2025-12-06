# 🌐 WebSocket - Tempo Real

## 📋 Visão Geral

O sistema utiliza WebSocket para comunicação em tempo real entre servidor e clientes, permitindo atualizações instantâneas de mensagens, conversas e status de agentes.

## 🚀 Instalação

### 1. Instalar Dependências

```bash
composer require cboden/ratchet
```

### 2. Iniciar Servidor WebSocket

```bash
php public/websocket-server.php
```

O servidor será iniciado na porta **8080** por padrão.

### 3. Configurar Proxy Reverso (Produção)

Para produção, configure um proxy reverso (Nginx/Apache) para rotear requisições WebSocket:

**Nginx:**
```nginx
location /ws {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}
```

## 📡 Eventos Disponíveis

### Eventos do Cliente → Servidor

#### Autenticação
```javascript
wsClient.send({
    type: 'auth',
    user_id: 123
});
```

#### Inscrever em Conversa
```javascript
wsClient.send({
    type: 'subscribe',
    conversation_id: 456
});
```

#### Indicador de Digitação
```javascript
wsClient.send({
    type: 'typing',
    conversation_id: 456,
    is_typing: true
});
```

#### Heartbeat
```javascript
wsClient.send({
    type: 'ping'
});
```

### Eventos do Servidor → Cliente

#### Nova Mensagem
```json
{
    "event": "new_message",
    "data": {
        "conversation_id": 456,
        "message": {
            "id": 789,
            "content": "Mensagem de texto",
            "sender_type": "agent",
            "sender_id": 123,
            "created_at": "2025-01-27 10:30:00"
        }
    }
}
```

#### Conversa Atualizada
```json
{
    "event": "conversation_updated",
    "data": {
        "conversation_id": 456,
        "conversation": {
            "id": 456,
            "status": "open",
            "agent_id": 123,
            ...
        }
    }
}
```

#### Nova Conversa
```json
{
    "event": "new_conversation",
    "data": {
        "conversation": {
            "id": 789,
            "contact_name": "João Silva",
            "status": "open",
            ...
        }
    }
}
```

#### Status de Agente
```json
{
    "event": "agent_status",
    "data": {
        "agent_id": 123,
        "status": "online" // ou "offline"
    }
}
```

#### Indicador de Digitação
```json
{
    "event": "typing",
    "data": {
        "conversation_id": 456,
        "user_id": 123,
        "is_typing": true
    }
}
```

#### Leitura de Mensagem
```json
{
    "event": "message_read",
    "data": {
        "conversation_id": 456,
        "message_id": 789,
        "user_id": 123
    }
}
```

## 💻 Uso no Frontend

### 1. Incluir Cliente WebSocket

```html
<script src="/assets/js/websocket-client.js"></script>
```

### 2. Conectar e Autenticar

```javascript
// Obter ID do usuário logado (do PHP ou localStorage)
const userId = <?= \App\Helpers\Auth::id() ?>;

// Conectar
window.wsClient.connect(userId);

// Registrar handlers
window.wsClient.on('connected', () => {
    console.log('WebSocket conectado!');
});

window.wsClient.on('new_message', (data) => {
    // Adicionar mensagem à interface
    addMessageToChat(data.message);
});

window.wsClient.on('conversation_updated', (data) => {
    // Atualizar lista de conversas
    updateConversationList(data.conversation);
});

window.wsClient.on('typing', (data) => {
    // Mostrar indicador de digitação
    showTypingIndicator(data.user_id, data.is_typing);
});
```

### 3. Inscrever em Conversa

```javascript
// Quando abrir uma conversa
window.wsClient.subscribe(conversationId);

// Quando fechar uma conversa
window.wsClient.unsubscribe(conversationId);
```

### 4. Enviar Indicador de Digitação

```javascript
let typingTimeout;

document.getElementById('message-input').addEventListener('input', () => {
    window.wsClient.sendTyping(conversationId, true);
    
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
        window.wsClient.sendTyping(conversationId, false);
    }, 3000);
});
```

## 🔧 Uso no Backend

### Enviar Notificações

```php
use App\Helpers\WebSocket;

// Notificar nova mensagem
WebSocket::notifyNewMessage($conversationId, $messageData);

// Notificar atualização de conversa
WebSocket::notifyConversationUpdated($conversationId, $conversationData);

// Notificar nova conversa
WebSocket::notifyNewConversation($conversationData);

// Notificar status de agente
WebSocket::notifyAgentStatus($agentId, 'online');

// Notificar indicador de digitação
WebSocket::notifyTyping($conversationId, $userId, true);

// Notificar leitura de mensagem
WebSocket::notifyMessageRead($conversationId, $messageId, $userId);

// Notificar usuário específico
WebSocket::notifyUser($userId, 'custom_event', $data);
```

## 🔄 Integração Automática

O sistema já está integrado automaticamente nos seguintes pontos:

- ✅ **Envio de mensagens** - Notifica automaticamente quando uma mensagem é enviada
- ✅ **Atribuição de conversas** - Notifica quando uma conversa é atribuída a um agente
- ✅ **Fechar/Reabrir conversas** - Notifica mudanças de status
- ✅ **Nova conversa** - Notifica quando uma nova conversa é criada

## 🛠️ Configuração

### Alterar Porta do WebSocket

Edite `public/websocket-server.php`:

```php
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatWebSocketServer()
        )
    ),
    8080 // Altere aqui
);
```

### Alterar URL do Cliente

Edite `public/assets/js/websocket-client.js`:

```javascript
const wsUrl = `${protocol}//${window.location.hostname}:8080`; // Altere aqui
```

## 📝 Notas Importantes

- O servidor WebSocket deve estar rodando para que as notificações funcionem
- Em desenvolvimento, execute o servidor em um terminal separado
- Em produção, configure um supervisor (como Supervisor ou PM2) para manter o servidor rodando
- O servidor WebSocket precisa ter acesso ao banco de dados para autenticação

## 🐛 Troubleshooting

### WebSocket não conecta
- Verifique se o servidor está rodando na porta 8080
- Verifique se o firewall permite conexões na porta 8080
- Verifique os logs do servidor WebSocket

### Mensagens não aparecem em tempo real
- Verifique se o cliente está conectado (`wsClient.connected`)
- Verifique se está inscrito na conversa (`wsClient.subscribe()`)
- Verifique os logs do navegador (Console)

### Reconexão automática não funciona
- Verifique se o `userId` está sendo passado corretamente
- Verifique os logs do servidor para erros de autenticação

---

**Última atualização**: 2025-01-27

