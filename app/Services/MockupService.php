<?php
/**
 * Service MockupService
 * Orquestra a geração de mockups (IA, Manual, Híbrido)
 */

namespace App\Services;

use App\Models\MockupGeneration;
use App\Models\MockupProduct;
use App\Models\ConversationLogo;
use App\Services\DALLEService;

class MockupService
{
    /**
     * Gerar mockup com IA (GPT-4o Vision + DALL-E 3)
     */
    public static function generateWithAI(array $params): array
    {
        $conversationId = $params['conversation_id'];
        $productImagePath = $params['product_image_path'];
        $logoImagePath = $params['logo_image_path'];
        $logoConfig = $params['logo_config'];
        $userPrompt = $params['user_prompt'] ?? null;
        $size = $params['size'] ?? '1024x1024';
        $quality = $params['quality'] ?? 'standard';
        $userId = $params['user_id'] ?? null;

        $startTime = microtime(true);

        try {
            // Criar registro inicial
            $generationId = MockupGeneration::createGeneration([
                'conversation_id' => $conversationId,
                'product_id' => $params['product_id'] ?? null,
                'product_image_path' => $productImagePath,
                'logo_id' => $params['logo_id'] ?? null,
                'logo_image_path' => $logoImagePath,
                'logo_config' => $logoConfig,
                'generation_mode' => 'ai',
                'original_prompt' => $userPrompt,
                'dalle_model' => 'dall-e-3',
                'dalle_size' => $size,
                'dalle_quality' => $quality,
                'status' => 'generating',
                'generated_by' => $userId
            ]);

            // Gerar com DALL-E 3
            $result = DALLEService::generateMockup(
                $productImagePath,
                $logoImagePath,
                $logoConfig,
                $userPrompt,
                $size,
                $quality
            );

            if (!$result['success']) {
                MockupGeneration::markAsFailed($generationId, $result['error']);
                return [
                    'success' => false,
                    'error' => $result['error']
                ];
            }

            // Download da imagem gerada
            $imageUrl = $result['image_url'];
            $filename = 'mockup_' . uniqid() . '_' . time() . '.png';
            $savePath = "assets/media/mockups/{$conversationId}/{$filename}";
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $savePath;

            // Criar diretório se não existir
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            // Download
            $downloaded = DALLEService::downloadImage($imageUrl, $fullPath);
            
            if (!$downloaded) {
                MockupGeneration::markAsFailed($generationId, 'Falha ao fazer download da imagem gerada');
                return [
                    'success' => false,
                    'error' => 'Falha ao salvar imagem'
                ];
            }

            // Gerar thumbnail
            $thumbFilename = 'thumb_' . $filename;
            $thumbPath = "assets/media/mockups/{$conversationId}/{$thumbFilename}";
            $thumbFullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $thumbPath;
            DALLEService::generateThumbnail($fullPath, $thumbFullPath, 300);

            // Obter tamanho do arquivo
            $fileSize = @filesize($fullPath) ?: 0;

            // Atualizar registro
            $processingTime = round((microtime(true) - $startTime) * 1000);

            MockupGeneration::updateGeneration($generationId, [
                'optimized_prompt' => $result['optimized_prompt'],
                'gpt4_analysis' => $result['gpt4_analysis'],
                'result_image_path' => $savePath,
                'result_thumbnail_path' => $thumbPath,
                'result_size' => $fileSize,
                'status' => 'completed',
                'processing_time' => $processingTime,
                'gpt4_cost' => $result['costs']['gpt4'] ?? 0,
                'dalle_cost' => $result['costs']['dalle'] ?? 0,
                'total_cost' => $result['costs']['total'] ?? 0
            ]);

            // Incrementar uso do produto se foi usado produto salvo
            if (!empty($params['product_id'])) {
                MockupProduct::incrementUsage($params['product_id']);
            }

            return [
                'success' => true,
                'generation_id' => $generationId,
                'image_path' => $savePath,
                'thumbnail_path' => $thumbPath,
                'optimized_prompt' => $result['optimized_prompt'],
                'processing_time' => $processingTime,
                'costs' => $result['costs']
            ];

        } catch (\Exception $e) {
            if (isset($generationId)) {
                MockupGeneration::markAsFailed($generationId, $e->getMessage());
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Salvar mockup criado manualmente no canvas
     */
    public static function saveCanvasMockup(array $params): array
    {
        $conversationId = $params['conversation_id'];
        $canvasData = $params['canvas_data'];
        $userId = $params['user_id'] ?? null;

        try {
            // Criar registro
            $generationId = MockupGeneration::createGeneration([
                'conversation_id' => $conversationId,
                'product_id' => $params['product_id'] ?? null,
                'product_image_path' => $params['product_image_path'] ?? null,
                'logo_id' => $params['logo_id'] ?? null,
                'logo_image_path' => $params['logo_image_path'] ?? null,
                'logo_config' => $params['logo_config'] ?? null,
                'generation_mode' => 'manual',
                'canvas_data' => $canvasData,
                'status' => 'generating',
                'generated_by' => $userId
            ]);

            // Processar canvas para imagem (será feito pelo CanvasService)
            $result = CanvasService::renderToImage($canvasData, $conversationId);

            if (!$result['success']) {
                MockupGeneration::markAsFailed($generationId, $result['error']);
                return $result;
            }

            // Atualizar registro
            MockupGeneration::updateGeneration($generationId, [
                'result_image_path' => $result['image_path'],
                'result_thumbnail_path' => $result['thumbnail_path'],
                'result_size' => $result['file_size'],
                'status' => 'completed',
                'processing_time' => $result['processing_time']
            ]);

            return [
                'success' => true,
                'generation_id' => $generationId,
                'image_path' => $result['image_path'],
                'thumbnail_path' => $result['thumbnail_path']
            ];

        } catch (\Exception $e) {
            if (isset($generationId)) {
                MockupGeneration::markAsFailed($generationId, $e->getMessage());
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Gerar mockup híbrido (IA primeiro, depois edição no canvas)
     */
    public static function generateHybrid(array $params): array
    {
        // Primeiro gera com IA
        $aiResult = self::generateWithAI($params);
        
        if (!$aiResult['success']) {
            return $aiResult;
        }

        // Atualizar modo para híbrido
        MockupGeneration::updateGeneration($aiResult['generation_id'], [
            'generation_mode' => 'hybrid'
        ]);

        return $aiResult;
    }

    /**
     * Obter mockups de uma conversa
     */
    public static function getMockupsByConversation(int $conversationId, int $limit = 50): array
    {
        return MockupGeneration::getCompletedByConversation($conversationId, $limit);
    }

    /**
     * Enviar mockup como mensagem na conversa
     */
    public static function sendAsMessage(int $generationId, int $conversationId): array
    {
        try {
            $generation = MockupGeneration::findById($generationId);
            
            if (!$generation) {
                return [
                    'success' => false,
                    'error' => 'Mockup não encontrado'
                ];
            }

            if ($generation['status'] !== 'completed') {
                return [
                    'success' => false,
                    'error' => 'Mockup ainda não foi completado'
                ];
            }

            // Preparar attachment
            $attachment = [
                'filename' => basename($generation['result_image_path']),
                'path' => $generation['result_image_path'],
                'url' => '/' . $generation['result_image_path'],
                'type' => 'image',
                'mime_type' => 'image/png',
                'size' => $generation['result_size'] ?? 0,
                'extension' => 'png'
            ];

            // Criar mensagem usando o ConversationService
            $messageData = [
                'conversation_id' => $conversationId,
                'sender_type' => 'agent',
                'sender_id' => $generation['generated_by'],
                'content' => '🎨 Mockup gerado',
                'message_type' => 'image',
                'attachments' => [$attachment]
            ];

            $messageId = \App\Services\ConversationService::sendMessage($conversationId, $messageData);

            if ($messageId) {
                // Marcar como enviado
                MockupGeneration::markAsSent($generationId, $messageId);

                return [
                    'success' => true,
                    'message_id' => $messageId
                ];
            }

            return [
                'success' => false,
                'error' => 'Falha ao enviar mensagem'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Deletar mockup
     */
    public static function deleteMockup(int $generationId): array
    {
        try {
            $generation = MockupGeneration::findById($generationId);
            
            if (!$generation) {
                return [
                    'success' => false,
                    'error' => 'Mockup não encontrado'
                ];
            }

            // Deletar arquivos
            if (!empty($generation['result_image_path'])) {
                $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $generation['result_image_path'];
                @unlink($fullPath);
            }

            if (!empty($generation['result_thumbnail_path'])) {
                $thumbPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $generation['result_thumbnail_path'];
                @unlink($thumbPath);
            }

            // Deletar registro
            MockupGeneration::deleteGeneration($generationId);

            return [
                'success' => true
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Regenerar mockup com novos parâmetros
     */
    public static function regenerate(int $generationId, array $newParams = []): array
    {
        try {
            $original = MockupGeneration::findById($generationId);
            
            if (!$original) {
                return [
                    'success' => false,
                    'error' => 'Mockup original não encontrado'
                ];
            }

            // Mesclar parâmetros originais com novos
            $params = [
                'conversation_id' => $original['conversation_id'],
                'product_image_path' => $newParams['product_image_path'] ?? $original['product_image_path'],
                'logo_image_path' => $newParams['logo_image_path'] ?? $original['logo_image_path'],
                'logo_config' => $newParams['logo_config'] ?? $original['logo_config'],
                'user_prompt' => $newParams['user_prompt'] ?? $original['original_prompt'],
                'size' => $newParams['size'] ?? $original['dalle_size'],
                'quality' => $newParams['quality'] ?? $original['dalle_quality'],
                'user_id' => $newParams['user_id'] ?? $original['generated_by'],
                'product_id' => $original['product_id'],
                'logo_id' => $original['logo_id']
            ];

            return self::generateWithAI($params);

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
