<?php
/**
 * Service para controle de rate limit de novas conversas manuais
 * 
 * Limita a quantidade de novas conversas que podem ser criadas
 * manualmente por integração em um período de tempo.
 */

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Logger;

class NewConversationRateLimitService
{
    /**
     * Verificar se pode criar nova conversa com base no limite configurado
     * 
     * @param int $accountId ID da conta (whatsapp_accounts ou integration_accounts)
     * @param string $accountType Tipo: 'whatsapp' ou 'integration'
     * @return array ['allowed' => bool, 'message' => string, 'remaining' => int, 'reset_in' => int]
     */
    public static function canCreateNewConversation(int $accountId, string $accountType = 'whatsapp'): array
    {
        // Buscar configuração de limite da conta
        $account = self::getAccountWithLimits($accountId, $accountType);
        
        if (!$account) {
            return [
                'allowed' => true,
                'message' => 'Conta não encontrada, limite não aplicado',
                'remaining' => -1,
                'reset_in' => 0
            ];
        }
        
        // Se limite não está habilitado, permitir
        if (empty($account['new_conv_limit_enabled']) || $account['new_conv_limit_enabled'] == 0) {
            return [
                'allowed' => true,
                'message' => 'Limite não habilitado',
                'remaining' => -1,
                'reset_in' => 0
            ];
        }
        
        $limitCount = (int)($account['new_conv_limit_count'] ?? 10);
        $limitPeriod = $account['new_conv_limit_period'] ?? 'hours';
        $limitPeriodValue = (int)($account['new_conv_limit_period_value'] ?? 1);
        
        // Calcular período em segundos
        $periodSeconds = self::getPeriodInSeconds($limitPeriod, $limitPeriodValue);
        
        // Contar conversas criadas no período
        $currentCount = self::countRecentConversations($accountId, $accountType, $periodSeconds);
        
        $remaining = $limitCount - $currentCount;
        
        if ($currentCount >= $limitCount) {
            // Limite atingido - calcular tempo para reset
            $resetIn = self::getTimeUntilReset($accountId, $accountType, $periodSeconds);
            
            $periodLabel = self::getPeriodLabel($limitPeriod, $limitPeriodValue);
            
            Logger::info("NewConversationRateLimit - BLOQUEADO: account_id={$accountId}, type={$accountType}, count={$currentCount}/{$limitCount}, period={$periodLabel}");
            
            return [
                'allowed' => false,
                'message' => "Limite de {$limitCount} novas conversas a cada {$periodLabel} atingido. Aguarde " . self::formatTimeRemaining($resetIn) . " para enviar novamente.",
                'remaining' => 0,
                'reset_in' => $resetIn,
                'limit' => $limitCount,
                'current' => $currentCount,
                'period' => $periodLabel
            ];
        }
        
        Logger::info("NewConversationRateLimit - PERMITIDO: account_id={$accountId}, type={$accountType}, count={$currentCount}/{$limitCount}");
        
        return [
            'allowed' => true,
            'message' => "OK",
            'remaining' => $remaining,
            'reset_in' => 0,
            'limit' => $limitCount,
            'current' => $currentCount
        ];
    }
    
    /**
     * Registrar nova conversa criada (para controle do limite)
     */
    public static function logNewConversation(int $accountId, string $accountType, int $contactId, int $conversationId, ?int $userId = null): void
    {
        try {
            Database::insert('new_conversation_log', [
                'account_type' => $accountType,
                'account_id' => $accountId,
                'user_id' => $userId,
                'contact_id' => $contactId,
                'conversation_id' => $conversationId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Logger::info("NewConversationRateLimit - LOG: account_id={$accountId}, type={$accountType}, contact={$contactId}, conv={$conversationId}");
        } catch (\Exception $e) {
            Logger::error("NewConversationRateLimit - Erro ao registrar log: " . $e->getMessage());
        }
    }
    
    /**
     * Buscar conta com configurações de limite
     */
    private static function getAccountWithLimits(int $accountId, string $accountType): ?array
    {
        $table = 'integration_accounts'; // Unificado: sempre usar integration_accounts
        
        return Database::fetch(
            "SELECT id, name, new_conv_limit_enabled, new_conv_limit_count, 
                    new_conv_limit_period, new_conv_limit_period_value 
             FROM {$table} WHERE id = ?",
            [$accountId]
        );
    }
    
    /**
     * Contar conversas criadas no período
     */
    private static function countRecentConversations(int $accountId, string $accountType, int $periodSeconds): int
    {
        $since = date('Y-m-d H:i:s', time() - $periodSeconds);
        
        $result = Database::fetch(
            "SELECT COUNT(*) as cnt FROM new_conversation_log 
             WHERE account_type = ? AND account_id = ? AND created_at >= ?",
            [$accountType, $accountId, $since]
        );
        
        return (int)($result['cnt'] ?? 0);
    }
    
    /**
     * Calcular tempo até o reset do limite
     */
    private static function getTimeUntilReset(int $accountId, string $accountType, int $periodSeconds): int
    {
        $since = date('Y-m-d H:i:s', time() - $periodSeconds);
        
        // Buscar a primeira conversa do período (a mais antiga dentro do limite)
        $oldest = Database::fetch(
            "SELECT created_at FROM new_conversation_log 
             WHERE account_type = ? AND account_id = ? AND created_at >= ?
             ORDER BY created_at ASC LIMIT 1",
            [$accountType, $accountId, $since]
        );
        
        if (!$oldest) {
            return 0;
        }
        
        $oldestTime = strtotime($oldest['created_at']);
        $resetTime = $oldestTime + $periodSeconds;
        $remaining = $resetTime - time();
        
        return max(0, $remaining);
    }
    
    /**
     * Converter período para segundos
     */
    private static function getPeriodInSeconds(string $period, int $value): int
    {
        switch ($period) {
            case 'minutes':
                return $value * 60;
            case 'hours':
                return $value * 3600;
            case 'days':
                return $value * 86400;
            default:
                return $value * 3600; // Default: horas
        }
    }
    
    /**
     * Obter label legível do período
     */
    private static function getPeriodLabel(string $period, int $value): string
    {
        $labels = [
            'minutes' => $value === 1 ? '1 minuto' : "{$value} minutos",
            'hours' => $value === 1 ? '1 hora' : "{$value} horas",
            'days' => $value === 1 ? '1 dia' : "{$value} dias"
        ];
        
        return $labels[$period] ?? "{$value} {$period}";
    }
    
    /**
     * Formatar tempo restante de forma legível
     */
    private static function formatTimeRemaining(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'alguns segundos';
        }
        
        if ($seconds < 60) {
            return "{$seconds} segundo" . ($seconds > 1 ? 's' : '');
        }
        
        $minutes = ceil($seconds / 60);
        if ($minutes < 60) {
            return "{$minutes} minuto" . ($minutes > 1 ? 's' : '');
        }
        
        $hours = floor($seconds / 3600);
        $remainingMinutes = ceil(($seconds % 3600) / 60);
        
        if ($hours < 24) {
            if ($remainingMinutes > 0) {
                return "{$hours}h {$remainingMinutes}min";
            }
            return "{$hours} hora" . ($hours > 1 ? 's' : '');
        }
        
        $days = floor($seconds / 86400);
        $remainingHours = floor(($seconds % 86400) / 3600);
        
        if ($remainingHours > 0) {
            return "{$days}d {$remainingHours}h";
        }
        return "{$days} dia" . ($days > 1 ? 's' : '');
    }
    
    /**
     * Limpar logs antigos (pode ser chamado periodicamente)
     */
    public static function cleanupOldLogs(int $daysToKeep = 7): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - ($daysToKeep * 86400));
        
        try {
            $result = Database::getInstance()->exec(
                "DELETE FROM new_conversation_log WHERE created_at < ?",
                [$cutoff]
            );
            
            Logger::info("NewConversationRateLimit - Cleanup: removidos registros anteriores a {$cutoff}");
            
            return $result;
        } catch (\Exception $e) {
            Logger::error("NewConversationRateLimit - Erro no cleanup: " . $e->getMessage());
            return 0;
        }
    }
}
