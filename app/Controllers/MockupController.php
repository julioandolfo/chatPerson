<?php
/**
 * Controller de Mockups
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Auth;
use App\Services\MockupService;
use App\Services\DALLEService;
use App\Models\MockupGeneration;
use App\Models\MockupProduct;
use App\Models\ConversationLogo;
use App\Models\MockupTemplate;
use App\Models\Message;

class MockupController
{
    /**
     * Preparar resposta JSON
     */
    private function prepareJsonResponse(): array
    {
        $oldDisplayErrors = ini_get('display_errors');
        $oldErrorReporting = error_reporting();
        ini_set('display_errors', '0');
        error_reporting(0);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        ob_start();
        
        return [
            'display_errors' => $oldDisplayErrors,
            'error_reporting' => $oldErrorReporting
        ];
    }
    
    /**
     * Restaurar configurações
     */
    private function restoreAfterJsonResponse(array $config): void
    {
        ini_set('display_errors', $config['display_errors']);
        error_reporting($config['error_reporting']);
    }

    /**
     * Gerar mockup com IA (GPT-4o Vision + DALL-E 3)
     * POST /api/conversations/{id}/mockups/generate
     */
    public function generate(int $conversationId): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            // Obter dados do POST
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

            // Validações
            if (empty($data['product_image_path'])) {
                Response::json(['error' => 'Imagem do produto é obrigatória'], 400);
                return;
            }

            if (empty($data['logo_image_path'])) {
                Response::json(['error' => 'Logo é obrigatória'], 400);
                return;
            }

            if (empty($data['logo_config'])) {
                Response::json(['error' => 'Configuração da logo é obrigatória'], 400);
                return;
            }

            // Parâmetros
            $params = [
                'conversation_id' => $conversationId,
                'product_image_path' => $data['product_image_path'],
                'logo_image_path' => $data['logo_image_path'],
                'logo_config' => $data['logo_config'],
                'user_prompt' => $data['user_prompt'] ?? null,
                'size' => $data['size'] ?? '1024x1024',
                'quality' => $data['quality'] ?? 'standard',
                'user_id' => $userId,
                'product_id' => $data['product_id'] ?? null,
                'logo_id' => $data['logo_id'] ?? null
            ];

            // Gerar mockup
            $result = MockupService::generateWithAI($params);

            if ($result['success']) {
                Response::json([
                    'success' => true,
                    'data' => $result
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'error' => $result['error']
                ], 500);
            }

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Salvar mockup do canvas manual
     * POST /api/conversations/{id}/mockups/save-canvas
     */
    public function saveCanvas(int $conversationId): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

            if (empty($data['canvas_data'])) {
                Response::json(['error' => 'Dados do canvas são obrigatórios'], 400);
                return;
            }

            $params = [
                'conversation_id' => $conversationId,
                'canvas_data' => $data['canvas_data'],
                'user_id' => $userId,
                'product_id' => $data['product_id'] ?? null,
                'product_image_path' => $data['product_image_path'] ?? null,
                'logo_id' => $data['logo_id'] ?? null,
                'logo_image_path' => $data['logo_image_path'] ?? null,
                'logo_config' => $data['logo_config'] ?? null
            ];

            $result = MockupService::saveCanvasMockup($params);

            Response::json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Listar mockups de uma conversa
     * GET /api/conversations/{id}/mockups
     */
    public function list(int $conversationId): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            
            $mockups = MockupService::getMockupsByConversation($conversationId, $limit);

            Response::json([
                'success' => true,
                'data' => $mockups
            ]);

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Buscar mockup específico
     * GET /api/mockups/{id}
     */
    public function get(int $id): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $mockup = MockupGeneration::findById($id);

            if (!$mockup) {
                Response::json(['error' => 'Mockup não encontrado'], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $mockup
            ]);

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Enviar mockup como mensagem
     * POST /api/mockups/{id}/send-message
     */
    public function sendAsMessage(int $id): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $mockup = MockupGeneration::findById($id);
            if (!$mockup) {
                Response::json(['error' => 'Mockup não encontrado'], 404);
                return;
            }

            $result = MockupService::sendAsMessage($id, $mockup['conversation_id']);

            Response::json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Deletar mockup
     * DELETE /api/mockups/{id}
     */
    public function delete(int $id): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $result = MockupService::deleteMockup($id);

            Response::json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Regenerar mockup
     * POST /api/mockups/{id}/regenerate
     */
    public function regenerate(int $id): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $data['user_id'] = $userId;

            $result = MockupService::regenerate($id, $data);

            Response::json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    // ===== PRODUTOS =====

    /**
     * Listar produtos
     * GET /api/mockup-products
     */
    public function listProducts(): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $category = $_GET['category'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

            $products = MockupProduct::getByUser($userId, $category, $limit);

            Response::json([
                'success' => true,
                'data' => $products
            ]);

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Criar produto
     * POST /api/mockup-products
     */
    public function createProduct(): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $data['created_by'] = $userId;

            if (empty($data['name']) || empty($data['image_path'])) {
                Response::json(['error' => 'Nome e imagem são obrigatórios'], 400);
                return;
            }

            $productId = MockupProduct::createProduct($data);

            Response::json([
                'success' => true,
                'product_id' => $productId
            ]);

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Deletar produto
     * DELETE /api/mockup-products/{id}
     */
    public function deleteProduct(int $id): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $success = MockupProduct::deleteProduct($id);

            Response::json(['success' => $success]);

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    // ===== LOGOS =====

    /**
     * Upload de logo para conversa
     * POST /api/conversations/{id}/logos/upload
     */
    public function uploadLogo(int $conversationId): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            if (empty($_FILES['logo'])) {
                Response::json(['error' => 'Arquivo não enviado'], 400);
                return;
            }

            $file = $_FILES['logo'];

            // Validar tipo
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes)) {
                Response::json(['error' => 'Tipo de arquivo não permitido'], 400);
                return;
            }

            // Validar tamanho (máx 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                Response::json(['error' => 'Arquivo muito grande (máx 5MB)'], 400);
                return;
            }

            // Salvar arquivo
            $filename = 'logo_' . uniqid() . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $savePath = "assets/media/logos/{$conversationId}/{$filename}";
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $savePath;

            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                Response::json(['error' => 'Falha ao salvar arquivo'], 500);
                return;
            }

            // Gerar thumbnail
            $thumbFilename = 'thumb_' . $filename;
            $thumbPath = "assets/media/logos/{$conversationId}/{$thumbFilename}";
            $thumbFullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $thumbPath;
            DALLEService::generateThumbnail($fullPath, $thumbFullPath, 150);

            // Obter dimensões
            $imageInfo = @getimagesize($fullPath);
            $dimensions = $imageInfo ? ['width' => $imageInfo[0], 'height' => $imageInfo[1]] : null;

            // Salvar no banco
            $logoId = ConversationLogo::createLogo([
                'conversation_id' => $conversationId,
                'contact_id' => null,
                'logo_path' => $savePath,
                'thumbnail_path' => $thumbPath,
                'original_filename' => $file['name'],
                'file_size' => $file['size'],
                'mime_type' => $file['type'],
                'dimensions' => $dimensions,
                'is_primary' => true
            ]);

            Response::json([
                'success' => true,
                'logo_id' => $logoId,
                'logo_path' => $savePath,
                'thumbnail_path' => $thumbPath
            ]);

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Listar logos de uma conversa
     * GET /api/conversations/{id}/logos
     */
    public function listLogos(int $conversationId): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $logos = ConversationLogo::getByConversation($conversationId);

            Response::json([
                'success' => true,
                'data' => $logos
            ]);

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Deletar logo
     * DELETE /api/logos/{id}
     */
    public function deleteLogo(int $id): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $success = ConversationLogo::deleteLogo($id);

            Response::json(['success' => $success]);

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    // ===== TEMPLATES =====

    /**
     * Listar templates
     * GET /api/mockup-templates
     */
    public function listTemplates(): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $category = $_GET['category'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

            $templates = MockupTemplate::getByUser($userId, $category, $limit);

            Response::json([
                'success' => true,
                'data' => $templates
            ]);

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Criar template
     * POST /api/mockup-templates
     */
    public function createTemplate(): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $data['created_by'] = $userId;

            if (empty($data['name']) || empty($data['canvas_data'])) {
                Response::json(['error' => 'Nome e dados do canvas são obrigatórios'], 400);
                return;
            }

            $templateId = MockupTemplate::createTemplate($data);

            Response::json([
                'success' => true,
                'template_id' => $templateId
            ]);

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Deletar template
     * DELETE /api/mockup-templates/{id}
     */
    public function deleteTemplate(int $id): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            $success = MockupTemplate::deleteTemplate($id);

            Response::json(['success' => $success]);

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Buscar imagens da conversa (para seleção de produto/logo)
     * GET /api/conversations/{id}/images
     */
    public function getConversationImages(int $conversationId): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = Auth::id();
            if (!$userId) {
                Response::json(['error' => 'Não autenticado'], 401);
                return;
            }

            // Buscar mensagens com anexos de imagem
            $messages = Message::getMessagesWithSenderDetails($conversationId, 100);
            
            $images = [];
            foreach ($messages as $message) {
                if (!empty($message['attachments'])) {
                    $attachments = is_string($message['attachments']) 
                        ? json_decode($message['attachments'], true) 
                        : $message['attachments'];
                    
                    foreach ($attachments as $attachment) {
                        if (($attachment['type'] ?? '') === 'image') {
                            $images[] = [
                                'id' => $message['id'],
                                'url' => $attachment['url'] ?? $attachment['path'],
                                'path' => $attachment['path'] ?? $attachment['url'],
                                'filename' => $attachment['filename'] ?? basename($attachment['path'] ?? ''),
                                'created_at' => $message['created_at'],
                                'sender_name' => $message['sender_name'] ?? 'Desconhecido'
                            ];
                        }
                    }
                }
            }

            Response::json([
                'success' => true,
                'data' => $images
            ]);

        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }
}
