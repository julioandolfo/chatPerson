<?php
/**
 * Controller de Anexos
 * Gerencia download, visualização e exclusão de anexos
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\AttachmentService;
use App\Models\Conversation;

class AttachmentController
{
    /**
     * Download de anexo
     */
    public function download(string $path): void
    {
        // ✅ CORRIGIDO: Verificar permissão correta
        if (!Permission::can('conversations.view.own') && !Permission::can('conversations.view.all')) {
            Permission::abortIfCannot('conversations.view.own');
        }
        
        // Decodificar path (pode vir codificado)
        $path = urldecode($path);
        
        // Validar path (prevenir directory traversal)
        if (strpos($path, '..') !== false || strpos($path, '/') === 0) {
            Response::forbidden('Caminho inválido');
            return;
        }
        
        // Verificar se arquivo existe
        $fullPath = __DIR__ . '/../../public/' . $path;
        if (!file_exists($fullPath)) {
            Response::notFound('Arquivo não encontrado');
            return;
        }
        
        // Obter informações do arquivo
        $info = AttachmentService::getInfo($path);
        if (!$info) {
            Response::notFound('Arquivo não encontrado');
            return;
        }
        
        // Verificar se usuário tem acesso à conversa relacionada
        // Extrair conversation_id do path (formato: assets/media/attachments/{conversation_id}/...)
        $pathParts = explode('/', $path);
        if (count($pathParts) >= 4 && $pathParts[2] === 'attachments') {
            $conversationId = (int)$pathParts[3];
            if ($conversationId > 0) {
                $conversation = Conversation::find($conversationId);
                if (!$conversation) {
                    Response::forbidden('Conversa não encontrada');
                    return;
                }
                
                // Verificar permissão de visualização da conversa
                if (!Permission::can('conversations.view.all') && 
                    !Permission::can('conversations.view.own') &&
                    $conversation['agent_id'] !== \App\Helpers\Auth::id()) {
                    Response::forbidden('Você não tem permissão para acessar este arquivo');
                    return;
                }
            }
        }
        
        // Enviar arquivo
        header('Content-Type: ' . $info['mime_type']);
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . $info['size']);
        header('Cache-Control: private, max-age=3600');
        
        readfile($fullPath);
        exit;
    }
    
    /**
     * Visualizar anexo (para imagens, PDFs, etc)
     */
    public function view(string $path): void
    {
        // ✅ CORRIGIDO: Verificar permissão correta
        if (!Permission::can('conversations.view.own') && !Permission::can('conversations.view.all')) {
            Permission::abortIfCannot('conversations.view.own');
        }
        
        // Decodificar path
        $path = urldecode($path);
        
        // Validar path
        if (strpos($path, '..') !== false || strpos($path, '/') === 0) {
            Response::forbidden('Caminho inválido');
            return;
        }
        
        // Verificar se arquivo existe
        $fullPath = __DIR__ . '/../../public/' . $path;
        if (!file_exists($fullPath)) {
            Response::notFound('Arquivo não encontrado');
            return;
        }
        
        // Obter informações do arquivo
        $info = AttachmentService::getInfo($path);
        if (!$info) {
            Response::notFound('Arquivo não encontrado');
            return;
        }
        
        // Verificar acesso à conversa (mesma lógica do download)
        $pathParts = explode('/', $path);
        if (count($pathParts) >= 4 && $pathParts[2] === 'attachments') {
            $conversationId = (int)$pathParts[3];
            if ($conversationId > 0) {
                $conversation = Conversation::find($conversationId);
                if (!$conversation) {
                    Response::forbidden('Conversa não encontrada');
                    return;
                }
                
                if (!Permission::can('conversations.view.all') && 
                    !Permission::can('conversations.view.own') &&
                    $conversation['agent_id'] !== \App\Helpers\Auth::id()) {
                    Response::forbidden('Você não tem permissão para acessar este arquivo');
                    return;
                }
            }
        }
        
        // Enviar arquivo para visualização inline
        header('Content-Type: ' . $info['mime_type']);
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        header('Content-Length: ' . $info['size']);
        header('Cache-Control: private, max-age=3600');
        
        readfile($fullPath);
        exit;
    }
    
    /**
     * Listar todos os anexos de uma conversa
     */
    public function listByConversation(int $conversationId): void
    {
        // ✅ CORRIGIDO: Verificar permissão correta
        if (!Permission::can('conversations.view.own') && !Permission::can('conversations.view.all')) {
            Permission::abortIfCannot('conversations.view.own');
        }
        
        // Verificar acesso à conversa
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
            return;
        }
        
        // Verificar permissão
        if (!\App\Helpers\Permission::can('conversations.view.all') && 
            !\App\Helpers\Permission::can('conversations.view.own') &&
            $conversation['agent_id'] !== \App\Helpers\Auth::id()) {
            Response::json(['success' => false, 'message' => 'Sem permissão'], 403);
            return;
        }
        
        // Obter todas as mensagens com anexos
        $messages = \App\Models\Message::getByConversation($conversationId, 1000, 0);
        
        $attachments = [];
        foreach ($messages as $message) {
            if (!empty($message['attachments']) && is_array($message['attachments'])) {
                foreach ($message['attachments'] as $attachment) {
                    $attachment['message_id'] = $message['id'];
                    $attachment['message_created_at'] = $message['created_at'];
                    $attachment['sender_type'] = $message['sender_type'];
                    $attachments[] = $attachment;
                }
            }
        }
        
        // Ordenar por data (mais recente primeiro)
        usort($attachments, function($a, $b) {
            return strtotime($b['message_created_at']) - strtotime($a['message_created_at']);
        });
        
        Response::json([
            'success' => true,
            'attachments' => $attachments,
            'total' => count($attachments)
        ]);
    }
    
    /**
     * Deletar anexo
     */
    public function delete(string $path): void
    {
        Permission::abortIfCannot('conversations.edit');
        
        // Decodificar path
        $path = urldecode($path);
        
        // Validar path
        if (strpos($path, '..') !== false || strpos($path, '/') === 0) {
            Response::json(['success' => false, 'message' => 'Caminho inválido'], 400);
            return;
        }
        
        // Verificar acesso à conversa
        $pathParts = explode('/', $path);
        if (count($pathParts) >= 4 && $pathParts[2] === 'attachments') {
            $conversationId = (int)$pathParts[3];
            if ($conversationId > 0) {
                $conversation = Conversation::find($conversationId);
                if (!$conversation) {
                    Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
                    return;
                }
                
                if (!Permission::can('conversations.edit.all') && 
                    $conversation['agent_id'] !== \App\Helpers\Auth::id()) {
                    Response::json(['success' => false, 'message' => 'Sem permissão'], 403);
                    return;
                }
            }
        }
        
        // Deletar arquivo
        if (AttachmentService::delete($path)) {
            Response::json(['success' => true, 'message' => 'Anexo deletado com sucesso']);
        } else {
            Response::json(['success' => false, 'message' => 'Erro ao deletar anexo'], 500);
        }
    }
}

