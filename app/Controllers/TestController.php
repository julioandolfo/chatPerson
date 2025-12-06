<?php
/**
 * Controller de Testes
 * Página simples para testar conversas demo simulando o contato
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\ConversationService;
use App\Models\Message;
use App\Models\Contact;
use App\Models\Conversation;
use App\Controllers\ConversationController;

class TestController
{
    /**
     * Página de teste - Simular contato
     */
    public function index(): void
    {
        // Verificar permissão básica
        Permission::abortIfCannot('conversations.view');
        
        // Obter conversas demo
        $demoConversations = self::getDemoConversations();
        
        Response::view('test/index', [
            'conversations' => $demoConversations
        ]);
    }
    
    /**
     * Carregar conversa para teste
     */
    public function loadConversation(int $id): void
    {
        // Verificar permissão
        Permission::abortIfCannot('conversations.view');
        
        // Garantir que conversa demo existe
        $conversation = ConversationService::getConversation($id);
        if (!$conversation) {
            // Tentar criar se for demo
            if ($id >= 1 && $id <= 9) {
                $conversation = ConversationController::ensureDemoConversationExists($id);
            }
        }
        
        if (!$conversation) {
            Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
            return;
        }
        
        // Formatar mensagens para incluir type e direction
        $messages = $conversation['messages'] ?? [];
        foreach ($messages as &$msg) {
            // Determinar type baseado em message_type
            if (($msg['message_type'] ?? 'text') === 'note') {
                $msg['type'] = 'note';
            } else {
                $msg['type'] = 'message';
            }
            
            // Determinar direction baseado em sender_type
            if (($msg['sender_type'] ?? '') === 'agent') {
                $msg['direction'] = 'outgoing';
            } else {
                $msg['direction'] = 'incoming';
            }
        }
        unset($msg);
        
        Response::json([
            'success' => true,
            'conversation' => $conversation,
            'messages' => $messages
        ]);
    }
    
    /**
     * Enviar mensagem como contato
     */
    public function sendMessage(int $id): void
    {
        // Verificar permissão
        Permission::abortIfCannot('conversations.view');
        
        $conversation = Conversation::find($id);
        if (!$conversation) {
            Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
            return;
        }
        
        $content = Request::post('message', '');
        if (empty($content)) {
            Response::json(['success' => false, 'message' => 'Mensagem não pode estar vazia'], 400);
            return;
        }
        
        // Enviar mensagem como contato
        $messageId = Message::createMessage([
            'conversation_id' => $id,
            'sender_id' => $conversation['contact_id'],
            'sender_type' => 'contact',
            'content' => $content,
            'message_type' => 'text',
            'status' => 'sent'
        ]);
        
        if ($messageId) {
            // Atualizar updated_at da conversa
            Conversation::update($id, []);
            
            // Obter mensagem criada
            $message = Message::getMessagesWithSenderDetails($id);
            $createdMessage = null;
            foreach ($message as $msg) {
                if ($msg['id'] == $messageId) {
                    $createdMessage = $msg;
                    break;
                }
            }
            
            // Formatar para frontend
            if ($createdMessage) {
                $createdMessage['type'] = 'message';
                $createdMessage['direction'] = 'incoming';
            }
            
            // Notificar via WebSocket
            try {
                \App\Helpers\WebSocket::notifyNewMessage($id, $createdMessage);
            } catch (\Exception $e) {
                error_log("Erro ao notificar WebSocket: " . $e->getMessage());
            }
            
            Response::json([
                'success' => true,
                'message' => $createdMessage
            ]);
        } else {
            Response::json(['success' => false, 'message' => 'Erro ao enviar mensagem'], 500);
        }
    }
    
    /**
     * Obter conversas demo (método privado)
     */
    private static function getDemoConversations(): array
    {
        $now = time();
        return [
            ['id' => 1, 'name' => 'Maria Silva', 'channel' => 'WhatsApp', 'last_message' => 'Olá, preciso de ajuda com meu pedido #12345'],
            ['id' => 2, 'name' => 'Carlos Oliveira', 'channel' => 'Email', 'last_message' => 'Gostaria de saber mais sobre os planos disponíveis'],
            ['id' => 3, 'name' => 'Ana Costa', 'channel' => 'WhatsApp', 'last_message' => 'Obrigada pela ajuda! Problema resolvido.'],
            ['id' => 4, 'name' => 'Roberto Santos', 'channel' => 'Chat', 'last_message' => 'Quando meu produto será entregue?'],
            ['id' => 5, 'name' => 'Juliana Ferreira', 'channel' => 'WhatsApp', 'last_message' => 'Preciso cancelar minha assinatura'],
            ['id' => 6, 'name' => 'Pedro Almeida', 'channel' => 'Email', 'last_message' => 'Gostaria de fazer uma reclamação sobre o atendimento'],
            ['id' => 7, 'name' => 'Fernanda Lima', 'channel' => 'WhatsApp', 'last_message' => 'Tudo certo, obrigada!'],
            ['id' => 8, 'name' => 'Lucas Martins', 'channel' => 'Chat', 'last_message' => 'Como faço para alterar minha senha?'],
            ['id' => 9, 'name' => 'Patricia Souza', 'channel' => 'WhatsApp', 'last_message' => 'Gostaria de informações sobre o produto X'],
        ];
    }
}

