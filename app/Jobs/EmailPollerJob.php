<?php
/**
 * EmailPollerJob
 * Percorre as contas de email ativas e ingere os emails novos.
 * Executado pelo cron public/scripts/poll-imap-emails.php.
 */

namespace App\Jobs;

use App\Models\IntegrationAccount;
use App\Services\EmailService;
use App\Helpers\Logger;

class EmailPollerJob
{
    public static function run(): array
    {
        $accounts = IntegrationAccount::getActive('email');
        $summary = ['accounts' => 0, 'fetched' => 0, 'ingested' => 0, 'ignored' => 0, 'duplicate' => 0, 'error' => 0];

        foreach ($accounts as $account) {
            if (($account['provider'] ?? '') !== EmailService::PROVIDER) {
                continue;
            }
            $summary['accounts']++;

            try {
                $stats = EmailService::pollAccount($account);
                foreach (['fetched', 'ingested', 'ignored', 'duplicate', 'error'] as $k) {
                    $summary[$k] += (int)($stats[$k] ?? 0);
                }
                Logger::log('[EMAIL] poll conta=' . $account['id'] . ' ' . json_encode($stats), 'email.log');

                // Limpa erro anterior se voltou a funcionar
                if (($account['status'] ?? '') === 'error') {
                    IntegrationAccount::update((int)$account['id'], ['status' => 'active', 'error_message' => null]);
                }
            } catch (\Throwable $e) {
                $summary['error']++;
                Logger::log('[EMAIL] poll FALHOU conta=' . ($account['id'] ?? '?') . ': ' . $e->getMessage(), 'email.log');
                try {
                    IntegrationAccount::update((int)$account['id'], [
                        'status'        => 'error',
                        'error_message' => mb_substr($e->getMessage(), 0, 250),
                    ]);
                } catch (\Throwable $e2) {
                    // ignora
                }
            }
        }

        return $summary;
    }
}
