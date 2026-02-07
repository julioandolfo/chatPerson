<?php
/**
 * Job WhatsAppConnectionMonitoringJob
 * Verifica periodicamente a conexão das contas WhatsApp
 * e cria alertas quando detecta desconexões
 */

namespace App\Jobs;

use App\Models\IntegrationAccount;
use App\Models\SystemAlert;
use App\Services\WhatsAppService;
use App\Services\NotificationService;
use App\Helpers\Logger;

class WhatsAppConnectionMonitoringJob
{
    // Número de falhas consecutivas antes de criar alerta crítico
    const FAILURES_FOR_CRITICAL = 2;
    
    // Número de falhas consecutivas antes de criar alerta warning
    const FAILURES_FOR_WARNING = 1;

    /**
     * Executar job de monitoramento de conexão WhatsApp
     */
    public static function run(): array
    {
        $results = [
            'checked' => 0,
            'connected' => 0,
            'disconnected' => 0,
            'errors' => 0,
            'alerts_created' => 0,
            'alerts_resolved' => 0,
            'details' => []
        ];
        
        try {
            Logger::info("WhatsAppConnectionMonitoringJob - Iniciando verificação");
            
            // Buscar todas as contas (não apenas ativas, para detectar desconexões)
            $accounts = IntegrationAccount::getAllWhatsApp();
            
            foreach ($accounts as $account) {
                $results['checked']++;
                
                try {
                    $checkResult = self::checkAccount($account);
                    $results['details'][] = $checkResult;
                    
                    if ($checkResult['connected']) {
                        $results['connected']++;
                        
                        // Se estava com alerta, resolver
                        if ($checkResult['alert_resolved']) {
                            $results['alerts_resolved']++;
                        }
                    } else {
                        $results['disconnected']++;
                        
                        // Se criou alerta
                        if ($checkResult['alert_created']) {
                            $results['alerts_created']++;
                        }
                    }
                } catch (\Exception $e) {
                    $results['errors']++;
                    $results['details'][] = [
                        'account_id' => $account['id'],
                        'account_name' => $account['name'],
                        'error' => $e->getMessage()
                    ];
                    Logger::error("WhatsAppConnectionMonitoringJob - Erro na conta {$account['id']}: " . $e->getMessage());
                }
            }
            
            Logger::info("WhatsAppConnectionMonitoringJob - Finalizado: " . json_encode([
                'checked' => $results['checked'],
                'connected' => $results['connected'],
                'disconnected' => $results['disconnected'],
                'alerts_created' => $results['alerts_created'],
                'alerts_resolved' => $results['alerts_resolved']
            ]));
            
        } catch (\Exception $e) {
            Logger::error("WhatsAppConnectionMonitoringJob - Erro geral: " . $e->getMessage());
            throw $e;
        }
        
        return $results;
    }
    
    /**
     * Verificar uma conta específica
     */
    private static function checkAccount(array $account): array
    {
        $result = [
            'account_id' => $account['id'],
            'account_name' => $account['name'],
            'phone_number' => $account['phone_number'],
            'previous_status' => $account['status'],
            'connected' => false,
            'message' => '',
            'alert_created' => false,
            'alert_resolved' => false
        ];
        
        // Verificar conexão real (sempre forçar verificação na API)
        $connectionResult = WhatsAppService::verifyRealConnection($account['id'], false);
        $result['connected'] = $connectionResult['connected'];
        $result['message'] = $connectionResult['message'];
        
        $now = date('Y-m-d H:i:s');
        $consecutiveFailures = (int)($account['consecutive_failures'] ?? 0);
        
        if ($connectionResult['connected']) {
            // Conexão OK - resetar contadores e resolver alertas
            $updateData = [
                'status' => 'active',
                'last_connection_check' => $now,
                'last_connection_result' => 'connected',
                'last_connection_message' => 'Conexão verificada com sucesso',
                'consecutive_failures' => 0
            ];
            IntegrationAccount::update($account['id'], $updateData);
            
            // Resolver alertas anteriores para esta conta
            $resolved = SystemAlert::resolveByTypeAndResource(
                SystemAlert::TYPE_WHATSAPP_DISCONNECTED, 
                "conta:{$account['id']}"
            );
            
            if ($resolved > 0) {
                $result['alert_resolved'] = true;
                Logger::info("WhatsAppConnectionMonitoringJob - Alerta resolvido para conta {$account['name']}");
            }
            
        } else {
            // Conexão falhou - incrementar contador e criar alerta se necessário
            $consecutiveFailures++;
            
            $updateData = [
                'status' => 'disconnected',
                'last_connection_check' => $now,
                'last_connection_result' => 'disconnected',
                'last_connection_message' => $connectionResult['message'],
                'consecutive_failures' => $consecutiveFailures
            ];
            IntegrationAccount::update($account['id'], $updateData);
            
            // Criar alerta se atingiu limite de falhas
            if ($consecutiveFailures >= self::FAILURES_FOR_WARNING) {
                $alertCreated = self::createDisconnectionAlert($account, $consecutiveFailures, $connectionResult['message']);
                $result['alert_created'] = $alertCreated;
            }
        }
        
        return $result;
    }
    
    /**
     * Criar alerta de desconexão
     */
    private static function createDisconnectionAlert(array $account, int $consecutiveFailures, string $errorMessage): bool
    {
        // Verificar se já existe alerta ativo para esta conta
        $resourceKey = "conta:{$account['id']}";
        if (SystemAlert::hasActiveAlert(SystemAlert::TYPE_WHATSAPP_DISCONNECTED, $resourceKey)) {
            Logger::info("WhatsAppConnectionMonitoringJob - Alerta já existe para conta {$account['name']}");
            return false;
        }
        
        // Determinar severidade baseado no número de falhas
        $severity = $consecutiveFailures >= self::FAILURES_FOR_CRITICAL 
            ? SystemAlert::SEVERITY_CRITICAL 
            : SystemAlert::SEVERITY_WARNING;
        
        // Criar alerta
        $alertId = SystemAlert::createAlert([
            'type' => SystemAlert::TYPE_WHATSAPP_DISCONNECTED,
            'severity' => $severity,
            'title' => "WhatsApp Desconectado: {$account['name']}",
            'message' => "A conta WhatsApp \"{$account['name']}\" ({$account['phone_number']}) está desconectada. " .
                         "{$resourceKey}. " .
                         "Falhas consecutivas: {$consecutiveFailures}. " .
                         "Motivo: {$errorMessage}",
            'action_url' => '/integrations/whatsapp'
        ]);
        
        Logger::info("WhatsAppConnectionMonitoringJob - Alerta criado ID={$alertId} para conta {$account['name']} (severidade: {$severity})");
        
        // Notificar admins via notificação regular também
        self::notifyAdmins($account, $severity, $errorMessage);
        
        return true;
    }
    
    /**
     * Notificar administradores sobre a desconexão
     */
    private static function notifyAdmins(array $account, string $severity, string $errorMessage): void
    {
        try {
            // Buscar usuários com role de admin ou super_admin
            $sql = "SELECT u.id FROM users u 
                    INNER JOIN roles r ON u.role_id = r.id 
                    WHERE r.slug IN ('super_admin', 'admin') AND u.status = 'active'";
            
            $stmt = \App\Helpers\Database::getInstance()->query($sql);
            $admins = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            
            if (empty($admins)) {
                Logger::info("WhatsAppConnectionMonitoringJob - Nenhum admin encontrado para notificar");
                return;
            }
            
            $title = $severity === SystemAlert::SEVERITY_CRITICAL 
                ? "🚨 WhatsApp Desconectado (CRÍTICO)" 
                : "⚠️ WhatsApp Desconectado";
            
            $message = "A conta \"{$account['name']}\" está desconectada. Reconecte via QR Code.";
            
            NotificationService::notifyMultiple($admins, [
                'type' => 'whatsapp_disconnection',
                'title' => $title,
                'message' => $message,
                'link' => '/integrations/whatsapp',
                'data' => [
                    'account_id' => $account['id'],
                    'account_name' => $account['name'],
                    'severity' => $severity
                ]
            ]);
            
            Logger::info("WhatsAppConnectionMonitoringJob - Notificação enviada para " . count($admins) . " admins");
            
        } catch (\Exception $e) {
            Logger::error("WhatsAppConnectionMonitoringJob - Erro ao notificar admins: " . $e->getMessage());
        }
    }
}
