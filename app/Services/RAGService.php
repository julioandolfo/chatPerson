<?php
/**
 * Service RAGService
 * Retrieval Augmented Generation - Busca semântica e integração com knowledge base
 */

namespace App\Services;

use App\Models\AIKnowledgeBase;
use App\Models\AIAgentMemory;
use App\Services\EmbeddingService;
use App\Helpers\Logger;

class RAGService
{
    const DEFAULT_LIMIT = 5;
    const DEFAULT_THRESHOLD = 0.7; // Limiar de similaridade (0-1)
    const MAX_CONTEXT_LENGTH = 2000; // Máximo de tokens no contexto

    /**
     * Buscar contexto relevante da knowledge base
     * 
     * @param int $agentId ID do agente
     * @param string $query Query do usuário
     * @param int $limit Número de resultados
     * @param float $threshold Limiar de similaridade
     * @return array Array de conhecimentos relevantes
     */
    public static function searchRelevantContext(int $agentId, string $query, int $limit = self::DEFAULT_LIMIT, float $threshold = self::DEFAULT_THRESHOLD): array
    {
        try {
            // Verificar se PostgreSQL está disponível
            if (!\App\Helpers\PostgreSQL::isAvailable()) {
                Logger::warning("RAGService::searchRelevantContext - PostgreSQL não disponível");
                return [];
            }

            // Gerar embedding da query
            $queryEmbedding = EmbeddingService::generate($query);
            
            // Buscar conhecimentos similares
            $results = AIKnowledgeBase::findSimilar($agentId, $queryEmbedding, $limit, $threshold);
            
            // Formatar resultados
            $context = [];
            foreach ($results as $result) {
                $context[] = [
                    'id' => $result['id'],
                    'title' => $result['title'],
                    'content' => $result['content'],
                    'content_type' => $result['content_type'],
                    'source_url' => $result['source_url'],
                    'similarity' => round((float)$result['similarity'], 3),
                    'metadata' => $result['metadata'] ? json_decode($result['metadata'], true) : null
                ];
            }

            Logger::info("RAGService::searchRelevantContext - Encontrados " . count($context) . " conhecimentos relevantes para agente {$agentId}");

            return $context;

        } catch (\Exception $e) {
            Logger::error("RAGService::searchRelevantContext - Erro: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Adicionar conhecimento à base
     * 
     * @param int $agentId ID do agente
     * @param string $content Conteúdo do conhecimento
     * @param string $contentType Tipo de conteúdo (manual, url, product, faq, etc)
     * @param array $metadata Metadados adicionais
     * @param string|null $title Título (opcional)
     * @param string|null $sourceUrl URL de origem (opcional)
     * @return int ID do conhecimento criado
     */
    public static function addKnowledge(int $agentId, string $content, string $contentType, array $metadata = [], ?string $title = null, ?string $sourceUrl = null): int
    {
        try {
            // Gerar embedding do conteúdo
            $embedding = EmbeddingService::generate($content);
            
            // Criar conhecimento
            $knowledgeId = AIKnowledgeBase::createWithEmbedding([
                'ai_agent_id' => $agentId,
                'content_type' => $contentType,
                'title' => $title,
                'content' => $content,
                'source_url' => $sourceUrl,
                'metadata' => !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null
            ], $embedding);

            Logger::info("RAGService::addKnowledge - Conhecimento adicionado: ID {$knowledgeId}, Agente {$agentId}, Tipo: {$contentType}");

            return $knowledgeId;

        } catch (\Exception $e) {
            Logger::error("RAGService::addKnowledge - Erro: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Adicionar múltiplos conhecimentos (chunks) de uma vez
     * 
     * @param int $agentId ID do agente
     * @param array $chunks Array de chunks [['content' => '...', 'title' => '...', 'metadata' => [...]], ...]
     * @param string $contentType Tipo de conteúdo
     * @param string|null $sourceUrl URL de origem
     * @return array Array de IDs criados
     */
    public static function addKnowledgeChunks(int $agentId, array $chunks, string $contentType, ?string $sourceUrl = null): array
    {
        $ids = [];
        
        try {
            // Gerar embeddings em batch
            $texts = array_column($chunks, 'content');
            $embeddings = EmbeddingService::generateBatch($texts);
            
            // Criar conhecimentos
            foreach ($chunks as $index => $chunk) {
                if (isset($embeddings[$index])) {
                    $knowledgeId = AIKnowledgeBase::createWithEmbedding([
                        'ai_agent_id' => $agentId,
                        'content_type' => $contentType,
                        'title' => $chunk['title'] ?? null,
                        'content' => $chunk['content'],
                        'source_url' => $sourceUrl,
                        'metadata' => isset($chunk['metadata']) ? json_encode($chunk['metadata'], JSON_UNESCAPED_UNICODE) : null,
                        'chunk_index' => $chunk['chunk_index'] ?? $index
                    ], $embeddings[$index]);
                    
                    $ids[] = $knowledgeId;
                }
            }

            Logger::info("RAGService::addKnowledgeChunks - Adicionados " . count($ids) . " chunks para agente {$agentId}");

        } catch (\Exception $e) {
            Logger::error("RAGService::addKnowledgeChunks - Erro: " . $e->getMessage());
            throw $e;
        }

        return $ids;
    }

    /**
     * Formatar contexto para incluir no prompt do sistema
     * 
     * @param array $context Array de conhecimentos relevantes
     * @return string Contexto formatado
     */
    public static function formatContextForPrompt(array $context): string
    {
        if (empty($context)) {
            return '';
        }

        $formatted = "\n\n## Conhecimento Relevante:\n\n";
        
        foreach ($context as $index => $item) {
            $formatted .= "### " . ($item['title'] ?? "Conhecimento " . ($index + 1)) . "\n";
            if ($item['source_url']) {
                $formatted .= "Fonte: {$item['source_url']}\n";
            }
            $formatted .= $item['content'] . "\n\n";
        }

        return $formatted;
    }

    /**
     * Buscar memórias relevantes do agente
     * 
     * @param int $agentId ID do agente
     * @param int $conversationId ID da conversa
     * @return array Array de memórias
     */
    public static function getRelevantMemories(int $agentId, int $conversationId): array
    {
        try {
            if (!\App\Helpers\PostgreSQL::isAvailable()) {
                return [];
            }

            $memories = AIAgentMemory::getByAgent($agentId, $conversationId, 10);
            
            // Formatar memórias
            $formatted = [];
            foreach ($memories as $memory) {
                $formatted[] = [
                    'type' => $memory['memory_type'],
                    'key' => $memory['key'],
                    'value' => $memory['value'],
                    'importance' => (float)$memory['importance']
                ];
            }

            return $formatted;

        } catch (\Exception $e) {
            Logger::error("RAGService::getRelevantMemories - Erro: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Formatar memórias para incluir no prompt
     * 
     * @param array $memories Array de memórias
     * @return string Memórias formatadas
     */
    public static function formatMemoriesForPrompt(array $memories): string
    {
        if (empty($memories)) {
            return '';
        }

        $formatted = "\n\n## Memórias Relevantes:\n\n";
        
        foreach ($memories as $memory) {
            $formatted .= "- **{$memory['key']}**: {$memory['value']}\n";
        }

        return $formatted;
    }

    /**
     * Obter contexto completo (knowledge base + memórias) formatado para prompt
     * 
     * @param int $agentId ID do agente
     * @param string $query Query do usuário
     * @param int $conversationId ID da conversa (opcional)
     * @return string Contexto completo formatado
     */
    public static function getFullContext(int $agentId, string $query, ?int $conversationId = null): string
    {
        $context = [];
        
        // Buscar conhecimento relevante
        $knowledge = self::searchRelevantContext($agentId, $query);
        if (!empty($knowledge)) {
            $context[] = self::formatContextForPrompt($knowledge);
        }
        
        // Buscar memórias se houver conversa
        if ($conversationId) {
            $memories = self::getRelevantMemories($agentId, $conversationId);
            if (!empty($memories)) {
                $context[] = self::formatMemoriesForPrompt($memories);
            }
        }
        
        return implode("\n", $context);
    }

    /**
     * Contar conhecimentos do agente
     */
    public static function countKnowledge(int $agentId): int
    {
        try {
            return AIKnowledgeBase::countByAgent($agentId);
        } catch (\Exception $e) {
            Logger::error("RAGService::countKnowledge - Erro: " . $e->getMessage());
            return 0;
        }
    }
}

