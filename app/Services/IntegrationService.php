<?php
/**
 * Service IntegrationService
 * Abstração unificada para gerenciar diferentes providers de integração
 */

namespace App\Services;

use App\Models\IntegrationAccount;

class IntegrationService
{
    /**
     * Factory para obter service correto baseado no provider
     */
    public static function getService(string $provider): object
    {
        switch ($provider) {
            case 'notificame':
                return new NotificameService();
            case 'whatsapp_official':
                return new WhatsAppOfficialService();
            case 'quepasa':
            case 'evolution':
                return new WhatsAppService();
            default:
                throw new \InvalidArgumentException("Provider não suportado: {$provider}");
        }
    }

    /**
     * Enviar mensagem através de qualquer provider
     */
    public static function sendMessage(int $accountId, string $to, string $message, array $options = []): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account) {
            throw new \Exception("Conta de integração não encontrada: {$accountId}");
        }

        \App\Helpers\Logger::info("IntegrationService::sendMessage - accountId={$accountId}, provider={$account['provider']}, channel={$account['channel']}, to={$to}, messageLen=" . strlen($message));

        $service = self::getService($account['provider']);
        
        if (method_exists($service, 'sendMessage')) {
            return $service->sendMessage($accountId, $to, $message, $options);
        }

        throw new \Exception("Método sendMessage não implementado para provider: {$account['provider']}");
    }

    /**
     * Processar webhook de qualquer provider
     */
    public static function processWebhook(array $payload, string $provider, string $channel = null): void
    {
        $service = self::getService($provider);
        
        if ($provider === 'notificame' && $channel) {
            if (method_exists($service, 'processWebhook')) {
                $service->processWebhook($payload, $channel);
                return;
            }
        } elseif ($provider === 'whatsapp_official') {
            if (method_exists($service, 'processWebhook')) {
                $service->processWebhook($payload);
                return;
            }
        } elseif (in_array($provider, ['quepasa', 'evolution'])) {
            if (method_exists($service, 'processWebhook')) {
                $service->processWebhook($payload);
                return;
            }
        }

        throw new \Exception("Processamento de webhook não implementado para provider: {$provider}");
    }

    /**
     * Verificar status de conexão
     */
    public static function checkStatus(int $accountId): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account) {
            throw new \Exception("Conta de integração não encontrada: {$accountId}");
        }

        $service = self::getService($account['provider']);
        
        if (method_exists($service, 'checkConnection')) {
            return $service->checkConnection($accountId);
        }

        return [
            'status' => $account['status'],
            'connected' => $account['status'] === 'active',
            'message' => 'Status não disponível para este provider'
        ];
    }

    /**
     * Obter conta de integração ativa por canal
     */
    public static function getActiveAccount(string $channel, string $provider = null): ?array
    {
        if ($provider) {
            // Buscar todas as contas ativas do canal e filtrar por provider
            $accounts = IntegrationAccount::getActive($channel);
            foreach ($accounts as $account) {
                if ($account['provider'] === $provider) {
                    return $account;
                }
            }
            return null;
        }

        return IntegrationAccount::getFirstActive($channel);
    }

    /**
     * Listar todas as contas de integração
     */
    public static function listAccounts(string $provider = null, string $channel = null): array
    {
        // Buscar todas as contas
        $accounts = IntegrationAccount::all();
        
        // Filtrar por provider se especificado
        if ($provider) {
            $accounts = array_filter($accounts, function($account) use ($provider) {
                return $account['provider'] === $provider;
            });
            $accounts = array_values($accounts);
        }
        
        // Filtrar por channel se especificado
        if ($channel) {
            $accounts = array_filter($accounts, function($account) use ($channel) {
                return $account['channel'] === $channel;
            });
            $accounts = array_values($accounts);
        }
        
        // Ordenar por nome
        usort($accounts, function($a, $b) {
            return strcmp($a['name'] ?? '', $b['name'] ?? '');
        });
        
        return $accounts;
    }
}

