<?php
/**
 * Service GamificationService
 * Sistema de gamificação: badges, conquistas, níveis
 */

namespace App\Services;

use App\Models\AgentPerformanceBadge;
use App\Helpers\Logger;

class GamificationService
{
    /**
     * Definição de badges
     */
    const BADGES = [
        // Badges por nível geral
        'rookie' => [
            'name' => 'Novato',
            'description' => 'Primeira análise completada!',
            'icon' => '🌱',
            'level' => 'bronze',
            'criteria' => 'first_analysis'
        ],
        'consistent' => [
            'name' => 'Consistente',
            'description' => '10 análises com nota acima de 3.5',
            'icon' => '📈',
            'level' => 'silver',
            'criteria' => 'consistent_performance'
        ],
        'top_performer' => [
            'name' => 'Top Performer',
            'description' => 'Média geral acima de 4.5',
            'icon' => '⭐',
            'level' => 'gold',
            'criteria' => 'high_average'
        ],
        'legend' => [
            'name' => 'Lenda',
            'description' => '50 análises com média acima de 4.7',
            'icon' => '👑',
            'level' => 'platinum',
            'criteria' => 'exceptional_consistent'
        ],
        
        // Badges por dimensão
        'closer' => [
            'name' => 'Fechador',
            'description' => 'Excelência em técnicas de fechamento (5.0)',
            'icon' => '🎯',
            'level' => 'gold',
            'criteria' => 'closing_mastery'
        ],
        'objection_buster' => [
            'name' => 'Quebrador de Objeções',
            'description' => 'Excelência em quebra de objeções (5.0)',
            'icon' => '💪',
            'level' => 'gold',
            'criteria' => 'objection_mastery'
        ],
        'relationship_builder' => [
            'name' => 'Construtor de Relacionamentos',
            'description' => 'Excelência em rapport (5.0)',
            'icon' => '🤝',
            'level' => 'gold',
            'criteria' => 'rapport_mastery'
        ],
        'proactive_seller' => [
            'name' => 'Vendedor Proativo',
            'description' => 'Excelência em proatividade (5.0)',
            'icon' => '🚀',
            'level' => 'gold',
            'criteria' => 'proactivity_mastery'
        ],
        
        // Badges de conquistas específicas
        'perfect_score' => [
            'name' => 'Nota Perfeita',
            'description' => 'Alcançou 5.0 em uma análise',
            'icon' => '💯',
            'level' => 'platinum',
            'criteria' => 'perfect_score'
        ],
        'comeback_kid' => [
            'name' => 'Recuperação',
            'description' => 'Melhorou 1.5 pontos em 30 dias',
            'icon' => '📊',
            'level' => 'silver',
            'criteria' => 'improvement'
        ],
        'fast_responder' => [
            'name' => 'Resposta Rápida',
            'description' => 'Tempo de resposta consistente (5.0)',
            'icon' => '⚡',
            'level' => 'gold',
            'criteria' => 'response_time_mastery'
        ],
        'professional' => [
            'name' => 'Profissional Exemplar',
            'description' => 'Profissionalismo perfeito (5.0)',
            'icon' => '🎩',
            'level' => 'gold',
            'criteria' => 'professionalism_mastery'
        ],
        
        // Badges de volume
        'grinder' => [
            'name' => 'Incansável',
            'description' => '100 conversas analisadas',
            'icon' => '🏃',
            'level' => 'silver',
            'criteria' => 'volume_100'
        ],
        'marathon' => [
            'name' => 'Maratonista',
            'description' => '500 conversas analisadas',
            'icon' => '🏅',
            'level' => 'platinum',
            'criteria' => 'volume_500'
        ]
    ];
    
    /**
     * Verificar e premiar badges baseado em análise
     */
    public static function checkAndAwardBadges(array $analysis): array
    {
        $agentId = $analysis['agent_id'];
        $awardedBadges = [];
        
        try {
            // Badge: Primeira análise
            if (!AgentPerformanceBadge::hasBadge($agentId, 'rookie')) {
                $awardedBadges[] = self::awardBadge($agentId, 'rookie', $analysis);
            }
            
            // Badge: Nota perfeita
            if ($analysis['overall_score'] >= 5.0 && !AgentPerformanceBadge::hasBadge($agentId, 'perfect_score')) {
                $awardedBadges[] = self::awardBadge($agentId, 'perfect_score', $analysis);
            }
            
            // Badges por dimensão (nota 5.0)
            $dimensionBadges = [
                'closing_techniques_score' => 'closer',
                'objection_handling_score' => 'objection_buster',
                'rapport_score' => 'relationship_builder',
                'proactivity_score' => 'proactive_seller',
                'response_time_score' => 'fast_responder',
                'professionalism_score' => 'professional'
            ];
            
            foreach ($dimensionBadges as $dimension => $badgeType) {
                if (isset($analysis[$dimension]) && $analysis[$dimension] >= 5.0) {
                    if (!AgentPerformanceBadge::hasBadge($agentId, $badgeType)) {
                        $awardedBadges[] = self::awardBadge($agentId, $badgeType, $analysis);
                    }
                }
            }
            
            // Badges baseados em histórico
            $awardedBadges = array_merge($awardedBadges, self::checkHistoricalBadges($agentId));
            
        } catch (\Exception $e) {
            Logger::error("GamificationService::checkAndAwardBadges - Erro: " . $e->getMessage());
        }
        
        return array_filter($awardedBadges);
    }
    
    /**
     * Verificar badges baseados em histórico
     */
    private static function checkHistoricalBadges(int $agentId): array
    {
        $awarded = [];
        
        try {
            $dateFrom = date('Y-m-d', strtotime('-30 days'));
            $dateTo = date('Y-m-d');
            $stats = \App\Models\AgentPerformanceAnalysis::getAgentAverages($agentId, $dateFrom, $dateTo);
            
            if (!$stats) return [];
            
            $totalAnalyses = (int)($stats['total_analyses'] ?? 0);
            $avgOverall = (float)($stats['avg_overall'] ?? 0);
            
            // Badge: Consistente
            if ($totalAnalyses >= 10 && $avgOverall >= 3.5) {
                if (!AgentPerformanceBadge::hasBadge($agentId, 'consistent')) {
                    $awarded[] = self::awardBadge($agentId, 'consistent', ['avg_score' => $avgOverall]);
                }
            }
            
            // Badge: Top Performer
            if ($avgOverall >= 4.5) {
                if (!AgentPerformanceBadge::hasBadge($agentId, 'top_performer')) {
                    $awarded[] = self::awardBadge($agentId, 'top_performer', ['avg_score' => $avgOverall]);
                }
            }
            
            // Badge: Lenda
            if ($totalAnalyses >= 50 && $avgOverall >= 4.7) {
                if (!AgentPerformanceBadge::hasBadge($agentId, 'legend')) {
                    $awarded[] = self::awardBadge($agentId, 'legend', ['avg_score' => $avgOverall, 'total' => $totalAnalyses]);
                }
            }
            
            // Badges de volume
            if ($totalAnalyses >= 100 && !AgentPerformanceBadge::hasBadge($agentId, 'grinder')) {
                $awarded[] = self::awardBadge($agentId, 'grinder', ['total' => $totalAnalyses]);
            }
            
            if ($totalAnalyses >= 500 && !AgentPerformanceBadge::hasBadge($agentId, 'marathon')) {
                $awarded[] = self::awardBadge($agentId, 'marathon', ['total' => $totalAnalyses]);
            }
            
        } catch (\Exception $e) {
            Logger::error("GamificationService::checkHistoricalBadges - Erro: " . $e->getMessage());
        }
        
        return $awarded;
    }
    
    /**
     * Premiar badge
     */
    private static function awardBadge(int $agentId, string $badgeType, array $relatedData = []): ?array
    {
        if (!isset(self::BADGES[$badgeType])) {
            return null;
        }
        
        $badge = self::BADGES[$badgeType];
        
        $data = [
            'agent_id' => $agentId,
            'badge_type' => $badgeType,
            'badge_name' => $badge['name'],
            'badge_description' => $badge['description'],
            'badge_icon' => $badge['icon'],
            'badge_level' => $badge['level'],
            'related_data' => json_encode($relatedData, JSON_UNESCAPED_UNICODE),
            'earned_at' => date('Y-m-d H:i:s')
        ];
        
        $badgeId = AgentPerformanceBadge::create($data);
        $data['id'] = $badgeId;
        
        Logger::log("GamificationService - Badge '{$badge['name']}' conquistado pelo agente {$agentId}!");
        
        // Enviar notificação (implementar se necessário)
        // NotificationService::sendBadgeNotification($agentId, $data);
        
        return $data;
    }
    
    /**
     * Obter badges de um agente
     */
    public static function getAgentBadges(int $agentId): array
    {
        return AgentPerformanceBadge::getAgentBadges($agentId);
    }
    
    /**
     * Obter estatísticas de badges
     */
    public static function getBadgeStats(int $agentId): array
    {
        $badges = AgentPerformanceBadge::getAgentBadges($agentId);
        $byLevel = AgentPerformanceBadge::countByLevel($agentId);
        
        $stats = [
            'total' => count($badges),
            'by_level' => [
                'bronze' => 0,
                'silver' => 0,
                'gold' => 0,
                'platinum' => 0
            ],
            'latest' => array_slice($badges, 0, 5)
        ];
        
        foreach ($byLevel as $row) {
            $stats['by_level'][$row['badge_level']] = (int)$row['count'];
        }
        
        return $stats;
    }
}
