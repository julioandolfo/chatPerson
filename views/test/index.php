<?php
/**
 * Página de Teste - Simular Contato
 * Permite selecionar uma conversa demo e enviar mensagens como se fosse o contato
 */

ob_start();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Conversas Demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .test-container {
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .test-header {
            background: #009ef7;
            color: white;
            padding: 20px;
        }
        .conversations-list {
            width: 300px;
            border-right: 1px solid #e0e0e0;
            height: calc(100vh - 200px);
            overflow-y: auto;
        }
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            transition: background 0.2s;
        }
        .conversation-item:hover {
            background: #f8f9fa;
        }
        .conversation-item.active {
            background: #e7f3ff;
            border-left: 3px solid #009ef7;
        }
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 200px);
        }
        .chat-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            background: #f8f9fa;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .message {
            margin-bottom: 15px;
            display: flex;
        }
        .message.incoming {
            justify-content: flex-start;
        }
        .message.outgoing {
            justify-content: flex-end;
        }
        .message-bubble {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 12px;
            word-wrap: break-word;
        }
        .message.incoming .message-bubble {
            background: white;
            border: 1px solid #e0e0e0;
        }
        .message.outgoing .message-bubble {
            background: #009ef7;
            color: white;
        }
        .chat-input {
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            background: white;
        }
        .empty-state {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-header">
            <h4 class="mb-0">🧪 Teste de Conversas Demo</h4>
            <small>Simule ser o contato e envie mensagens para testar o sistema</small>
        </div>
        
        <div class="d-flex" style="height: calc(100vh - 200px);">
            <!-- Lista de Conversas -->
            <div class="conversations-list">
                <div class="p-3 border-bottom bg-light">
                    <strong>Conversas Demo</strong>
                </div>
                <?php foreach ($conversations as $conv): ?>
                    <div class="conversation-item" data-id="<?= $conv['id'] ?>" onclick="loadConversation(<?= $conv['id'] ?>)">
                        <div class="fw-semibold"><?= htmlspecialchars($conv['name']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($conv['channel']) ?></div>
                        <div class="text-muted small mt-1" style="font-size: 11px;"><?= htmlspecialchars($conv['last_message']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Área do Chat -->
            <div class="chat-area">
                <div class="chat-header" id="chatHeader">
                    <div class="text-muted">Selecione uma conversa para começar</div>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <div class="empty-state">
                        <div>
                            <div class="mb-3">💬</div>
                            <div>Selecione uma conversa da lista</div>
                        </div>
                    </div>
                </div>
                
                <div class="chat-input" id="chatInput" style="display: none;">
                    <div class="input-group">
                        <input type="text" class="form-control" id="messageInput" placeholder="Digite sua mensagem..." onkeypress="if(event.key==='Enter') sendMessage()">
                        <button class="btn btn-primary" onclick="sendMessage()">Enviar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentConversationId = null;
        
        function loadConversation(id) {
            currentConversationId = id;
            
            // Marcar como ativa
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-id="${id}"]`).classList.add('active');
            
            // Mostrar loading
            document.getElementById('chatMessages').innerHTML = `
                <div class="empty-state">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            `;
            
            // Carregar conversa
            fetch(`<?= \App\Helpers\Url::to('/test/conversation') ?>/${id}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Atualizar header
                    const header = document.getElementById('chatHeader');
                    header.innerHTML = `
                        <div class="fw-semibold">${escapeHtml(data.conversation.contact_name || 'Contato')}</div>
                        <div class="text-muted small">${escapeHtml(data.conversation.channel || 'chat')}</div>
                    `;
                    
                    // Mostrar input
                    document.getElementById('chatInput').style.display = 'block';
                    
                    // Carregar mensagens
                    displayMessages(data.messages || []);
                } else {
                    alert('Erro ao carregar conversa: ' + (data.message || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao carregar conversa');
            });
        }
        
        function displayMessages(messages) {
            const container = document.getElementById('chatMessages');
            
            if (!messages || messages.length === 0) {
                container.innerHTML = '<div class="empty-state"><div>Nenhuma mensagem ainda</div></div>';
                return;
            }
            
            let html = '';
            messages.forEach(msg => {
                const isIncoming = (msg.direction || msg.sender_type === 'contact') ? 'incoming' : 'outgoing';
                const content = escapeHtml(msg.content || '');
                
                html += `
                    <div class="message ${isIncoming}">
                        <div class="message-bubble">${content}</div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            container.scrollTop = container.scrollHeight;
        }
        
        function sendMessage() {
            if (!currentConversationId) {
                alert('Selecione uma conversa primeiro');
                return;
            }
            
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message) {
                return;
            }
            
            // Adicionar mensagem otimisticamente
            const container = document.getElementById('chatMessages');
            container.innerHTML += `
                <div class="message incoming">
                    <div class="message-bubble">${escapeHtml(message)}</div>
                </div>
            `;
            container.scrollTop = container.scrollHeight;
            
            input.value = '';
            
            // Enviar para servidor
            fetch(`<?= \App\Helpers\Url::to('/test/conversation') ?>/${currentConversationId}/message`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ message: message })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Erro ao enviar mensagem: ' + (data.message || 'Erro desconhecido'));
                    // Remover mensagem otimista em caso de erro
                    const messages = container.querySelectorAll('.message');
                    if (messages.length > 0) {
                        messages[messages.length - 1].remove();
                    }
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao enviar mensagem');
                // Remover mensagem otimista em caso de erro
                const messages = container.querySelectorAll('.message');
                if (messages.length > 0) {
                    messages[messages.length - 1].remove();
                }
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>

<?php $content = ob_get_clean(); ?>
<?php echo $content; ?>

