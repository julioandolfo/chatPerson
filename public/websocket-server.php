<?php
/**
 * Servidor WebSocket usando Ratchet
 * 
 * Para executar: php public/websocket-server.php
 * 
 * Requer: composer require cboden/ratchet
 */

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// Carregar autoloader do Composer
require __DIR__ . '/../vendor/autoload.php';

// Carregar autoloader da aplicação
require_once __DIR__ . '/../app/Helpers/autoload.php';

/**
 * Classe do Servidor WebSocket
 */
class ChatWebSocketServer implements MessageComponentInterface
{
    protected $clients;
    protected $users; // Mapeia user_id para conexões

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->users = [];
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "Nova conexão: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        try {
            $data = json_decode($msg, true);
            
            if (!$data || !isset($data['type'])) {
                return;
            }

            switch ($data['type']) {
                case 'auth':
                    // Autenticar usuário
                    $userId = $data['user_id'] ?? null;
                    if ($userId) {
                        $this->users[$userId] = $from;
                        $from->userId = $userId;
                        echo "Usuário {$userId} autenticado na conexão {$from->resourceId}\n";
                        
                        // Notificar que está online
                        $this->broadcastToOthers($from, json_encode([
                            'event' => 'agent_status',
                            'data' => [
                                'agent_id' => $userId,
                                'status' => 'online'
                            ]
                        ]));
                    }
                    break;

                case 'subscribe':
                    // Inscrever em conversa específica
                    $conversationId = $data['conversation_id'] ?? null;
                    if ($conversationId) {
                        $from->subscribedConversations = $from->subscribedConversations ?? [];
                        $from->subscribedConversations[] = $conversationId;
                        echo "Conexão {$from->resourceId} inscrita na conversa {$conversationId}\n";
                    }
                    break;

                case 'typing':
                    // Indicador de digitação
                    $conversationId = $data['conversation_id'] ?? null;
                    if ($conversationId) {
                        $this->broadcastToConversation($conversationId, json_encode([
                            'event' => 'typing',
                            'data' => [
                                'conversation_id' => $conversationId,
                                'user_id' => $from->userId ?? null,
                                'is_typing' => $data['is_typing'] ?? true
                            ]
                        ]), $from);
                    }
                    break;

                case 'ping':
                    // Heartbeat
                    $from->send(json_encode(['type' => 'pong']));
                    break;
            }
        } catch (\Exception $e) {
            echo "Erro ao processar mensagem: " . $e->getMessage() . "\n";
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        
        if (isset($conn->userId)) {
            unset($this->users[$conn->userId]);
            
            // Notificar que está offline
            $this->broadcastToOthers($conn, json_encode([
                'event' => 'agent_status',
                'data' => [
                    'agent_id' => $conn->userId,
                    'status' => 'offline'
                ]
            ]));
        }
        
        echo "Conexão {$conn->resourceId} fechada\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Erro na conexão {$conn->resourceId}: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Enviar mensagem para todos exceto o remetente
     */
    protected function broadcastToOthers(ConnectionInterface $from, $msg)
    {
        foreach ($this->clients as $client) {
            if ($client !== $from) {
                $client->send($msg);
            }
        }
    }

    /**
     * Enviar mensagem para todos inscritos em uma conversa
     */
    protected function broadcastToConversation($conversationId, $msg, ConnectionInterface $from = null)
    {
        foreach ($this->clients as $client) {
            if ($client === $from) {
                continue;
            }
            
            if (isset($client->subscribedConversations) && 
                in_array($conversationId, $client->subscribedConversations)) {
                $client->send($msg);
            }
        }
    }

    /**
     * Enviar mensagem para usuário específico
     */
    public function sendToUser($userId, $msg)
    {
        if (isset($this->users[$userId])) {
            $this->users[$userId]->send($msg);
        }
    }

    /**
     * Enviar mensagem para todos os clientes
     */
    public function broadcast($msg)
    {
        foreach ($this->clients as $client) {
            $client->send($msg);
        }
    }
}

// Criar servidor WebSocket
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatWebSocketServer()
        )
    ),
    8080 // Porta do WebSocket
);

echo "Servidor WebSocket iniciado na porta 8080\n";
echo "Acesse: ws://localhost:8080\n";

$server->run();

