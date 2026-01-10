<?php
/**
 * Service BestPracticesService
 * Biblioteca de melhores práticas (golden conversations)
 */

namespace App\Services;

use App\Models\AgentPerformanceBestPractice;
use App\Models\Conversation;
use App\Models\Message;
use App\Helpers\Database;
use App\Helpers\Logger;

class BestPracticesService
{
    /**
     * Salvar melhor prática baseada em análise
     */
    public static function saveBestPractice(array $analysis, array $conversation): array
    {
        $saved = [];
        
        try {
            $conversationId = $analysis['conversation_id'];
            $agentId = $analysis['agent_id'];
            $analysisId = $analysis['id'];
            $overallScore = $analysis['overall_score'];
            
            // Identificar dimensões com nota excelente (>= 4.5)
            $excellentDimensions = [];
            $dimensions = [
                'proactivity_score' => ['key' => 'proactivity', 'name' => 'Proatividade'],
                'objection_handling_score' => ['key' => 'objection_handling', 'name' => 'Quebra de Objeções'],
                'rapport_score' => ['key' => 'rapport', 'name' => 'Rapport'],
                'closing_techniques_score' => ['key' => 'closing', 'name' => 'Fechamento'],
                'qualification_score' => ['key' => 'qualification', 'name' => 'Qualificação'],
                'value_proposition_score' => ['key' => 'value', 'name' => 'Valor']
            ];
            
            foreach ($dimensions as $scoreKey => $info) {
                if (isset($analysis[$scoreKey]) && $analysis[$scoreKey] >= 4.5) {
                    $excellentDimensions[] = $info;
                }
            }
            
            // Se não tem dimensões excelentes, não salvar
            if (empty($excellentDimensions)) {
                return [];
            }
            
            // Obter mensagens da conversa
            $messages = Database::fetchAll(
                "SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC",
                [$conversationId]
            );
            
            // Para cada dimensão excelente, criar uma prática
            foreach ($excellentDimensions as $dimension) {
                $category = $dimension['key'];
                $categoryName = $dimension['name'];
                
                // Verificar se já existe prática desta conversa nesta categoria
                $existing = Database::fetch(
                    "SELECT id FROM agent_performance_best_practices 
                     WHERE conversation_id = ? AND category = ?",
                    [$conversationId, $category]
                );
                
                if ($existing) continue;
                
                // Identificar trecho relevante
                $excerpt = self::extractRelevantExcerpt($messages, $category, $analysis);
                
                // Criar título
                $title = self::generateTitle($category, $overallScore);
                
                // Criar descrição
                $description = self::generateDescription($category, $analysis);
                
                $practiceData = [
                    'conversation_id' => $conversationId,
                    'agent_id' => $agentId,
                    'analysis_id' => $analysisId,
                    'category' => $category,
                    'title' => $title,
                    'description' => $description,
                    'excerpt' => $excerpt,
                    'score' => $analysis[$scoreKey],
                    'is_featured' => $overallScore >= 4.8 ? 1 : 0
                ];
                
                $practiceId = AgentPerformanceBestPractice::create($practiceData);
                $practiceData['id'] = $practiceId;
                $saved[] = $practiceData;
                
                Logger::log("BestPracticesService - Melhor prática salva: {$categoryName} (Conversa #{$conversationId})");
            }
            
        } catch (\Exception $e) {
            Logger::error("BestPracticesService::saveBestPractice - Erro: " . $e->getMessage());
        }
        
        return $saved;
    }
    
    /**
     * Extrair trecho relevante da conversa
     */
    private static function extractRelevantExcerpt(array $messages, string $category, array $analysis): string
    {
        // Tentar identificar momentos-chave relacionados à categoria
        $keyMoments = json_decode($analysis['key_moments'] ?? '[]', true) ?: [];
        
        // Limitar a 5 mensagens mais relevantes
        $relevantMessages = array_slice($messages, 0, min(10, count($messages)));
        
        $excerpt = "";
        foreach ($relevantMessages as $msg) {
            $sender = ($msg['sender_type'] === 'agent') ? 'Vendedor' : 'Cliente';
            $time = date('H:i', strtotime($msg['created_at']));
            $content = mb_substr($msg['content'], 0, 150);
            $excerpt .= "[{$time}] {$sender}: {$content}\n";
        }
        
        return trim($excerpt);
    }
    
    /**
     * Gerar título para prática
     */
    private static function generateTitle(string $category, float $score): string
    {
        $titles = [
            'proactivity' => 'Exemplo de Proatividade Excepcional',
            'objection_handling' => 'Como Quebrar Objeções com Maestria',
            'rapport' => 'Construindo Rapport de Forma Natural',
            'closing' => 'Técnica de Fechamento Eficaz',
            'qualification' => 'Qualificação Inteligente do Lead',
            'value' => 'Apresentação de Valor que Convence'
        ];
        
        $baseTitle = $titles[$category] ?? 'Melhor Prática de Vendas';
        $stars = str_repeat('⭐', min(5, floor($score)));
        
        return "{$baseTitle} {$stars}";
    }
    
    /**
     * Gerar descrição para prática
     */
    private static function generateDescription(string $category, array $analysis): string
    {
        $detailedAnalysis = $analysis['detailed_analysis'] ?? '';
        
        // Pegar primeiro parágrafo da análise detalhada
        $paragraphs = explode("\n\n", $detailedAnalysis);
        $firstParagraph = $paragraphs[0] ?? '';
        
        return mb_substr($firstParagraph, 0, 300) . (mb_strlen($firstParagraph) > 300 ? '...' : '');
    }
    
    /**
     * Obter práticas por categoria
     */
    public static function getByCategory(string $category, int $limit = 20): array
    {
        return AgentPerformanceBestPractice::getByCategory($category, $limit);
    }
    
    /**
     * Obter práticas em destaque
     */
    public static function getFeatured(int $limit = 10): array
    {
        return AgentPerformanceBestPractice::getFeatured($limit);
    }
    
    /**
     * Obter todas as categorias disponíveis
     */
    public static function getCategories(): array
    {
        return [
            'proactivity' => ['name' => 'Proatividade', 'icon' => '🚀'],
            'objection_handling' => ['name' => 'Quebra de Objeções', 'icon' => '💪'],
            'rapport' => ['name' => 'Rapport', 'icon' => '🤝'],
            'closing' => ['name' => 'Fechamento', 'icon' => '🎯'],
            'qualification' => ['name' => 'Qualificação', 'icon' => '🎓'],
            'value' => ['name' => 'Valor', 'icon' => '💎']
        ];
    }
    
    /**
     * Marcar como visualizado
     */
    public static function markAsViewed(int $practiceId): bool
    {
        return AgentPerformanceBestPractice::incrementViews($practiceId);
    }
    
    /**
     * Adicionar voto útil
     */
    public static function addHelpfulVote(int $practiceId): bool
    {
        return AgentPerformanceBestPractice::addHelpfulVote($practiceId);
    }
}
