<?php
/**
 * EmailIntegrationController
 * Gestão de contas de Email (IMAP/SMTP) e regras de ingestão.
 * As contas são armazenadas em integration_accounts (provider='imap', channel='email').
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Helpers\Encryption;
use App\Helpers\Url;
use App\Models\IntegrationAccount;
use App\Models\EmailRule;
use App\Models\EmailIngestionLog;
use App\Services\EmailService;
use App\Services\Email\ImapClient;
use App\Services\Email\SmtpMailer;

class EmailIntegrationController
{
    /* ===================== Páginas ===================== */

    public function index(): void
    {
        Permission::abortIfCannot('integrations.view');

        $accounts = IntegrationAccount::getByProvider(EmailService::PROVIDER);
        // Decodifica config para exibição (sem expor senha)
        foreach ($accounts as &$acc) {
            $cfg = is_string($acc['config'] ?? null) ? (json_decode($acc['config'], true) ?: []) : ($acc['config'] ?? []);
            unset($cfg['auth_pass_enc'], $cfg['auth_pass']);
            $acc['cfg'] = $cfg;
            $acc['rules'] = EmailRule::getForAccount((int)$acc['id'], false);
            $acc['logs'] = EmailIngestionLog::recent((int)$acc['id'], 20);
        }
        unset($acc);

        $funnels = \App\Models\Funnel::all();
        $departments = \App\Models\Department::getActive();
        $agents = \App\Models\User::getActiveAgents();

        Response::view('email-integration/index', [
            'accounts'    => $accounts,
            'funnels'     => $funnels,
            'departments' => $departments,
            'agents'      => $agents,
        ]);
    }

    /* ===================== Contas ===================== */

    public function storeAccount(): void
    {
        Permission::abortIfCannot('integrations.create');

        $name = trim((string)Request::post('name', ''));
        $email = trim((string)Request::post('auth_user', ''));
        if ($name === '' || $email === '') {
            self::back('error', 'Informe o nome e o email da conta.');
            return;
        }

        $config = self::buildConfig(Request::all(), []);

        $id = IntegrationAccount::create([
            'name'              => $name,
            'provider'          => EmailService::PROVIDER,
            'channel'           => 'email',
            'username'          => $email,
            'status'            => 'active',
            'config'            => json_encode($config, JSON_UNESCAPED_UNICODE),
            'default_funnel_id' => self::intOrNull(Request::post('default_funnel_id')),
            'default_stage_id'  => self::intOrNull(Request::post('default_stage_id')),
        ]);

        self::back('ok', $id ? 'Conta de email criada.' : 'Não foi possível criar a conta.', $id ?: null);
    }

    public function updateAccount(string $id): void
    {
        Permission::abortIfCannot('integrations.edit');
        $accountId = (int)$id;
        $account = IntegrationAccount::find($accountId);
        if (!$account || ($account['provider'] ?? '') !== EmailService::PROVIDER) {
            self::back('error', 'Conta não encontrada.');
            return;
        }

        $existing = is_string($account['config'] ?? null) ? (json_decode($account['config'], true) ?: []) : ($account['config'] ?? []);
        $config = self::buildConfig(Request::all(), $existing);

        IntegrationAccount::update($accountId, [
            'name'              => trim((string)Request::post('name', $account['name'])),
            'username'          => trim((string)Request::post('auth_user', $account['username'])),
            'status'            => Request::post('status', $account['status']) === 'inactive' ? 'inactive' : 'active',
            'config'            => json_encode($config, JSON_UNESCAPED_UNICODE),
            'default_funnel_id' => self::intOrNull(Request::post('default_funnel_id')),
            'default_stage_id'  => self::intOrNull(Request::post('default_stage_id')),
        ]);

        self::back('ok', 'Conta atualizada.', $accountId);
    }

    public function deleteAccount(string $id): void
    {
        Permission::abortIfCannot('integrations.delete');
        IntegrationAccount::delete((int)$id);
        self::back('ok', 'Conta removida.');
    }

    /* ===================== Teste / Poll (JSON) ===================== */

    public function testAccount(string $id): void
    {
        Permission::abortIfCannot('integrations.view');
        $account = IntegrationAccount::find((int)$id);
        if (!$account) {
            Response::error('Conta não encontrada', 404);
            return;
        }
        $cfg = EmailService::config($account);
        $imap = ImapClient::testConnection($cfg);
        $smtp = SmtpMailer::testConnection($cfg);

        Response::json([
            'success' => ($imap['success'] && $smtp['success']),
            'imap'    => $imap,
            'smtp'    => $smtp,
        ]);
    }

    public function pollAccount(string $id): void
    {
        Permission::abortIfCannot('integrations.manage');
        $account = IntegrationAccount::find((int)$id);
        if (!$account || ($account['provider'] ?? '') !== EmailService::PROVIDER) {
            Response::error('Conta de email não encontrada', 404);
            return;
        }
        try {
            $stats = EmailService::pollAccount($account);
            Response::success(['stats' => $stats], 'Poll executado.');
        } catch (\Throwable $e) {
            Response::error('Falha no poll: ' . $e->getMessage(), 500);
        }
    }

    /* ===================== Regras ===================== */

    public function storeRule(string $accountId): void
    {
        Permission::abortIfCannot('integrations.edit');
        $accId = (int)$accountId;
        $account = IntegrationAccount::find($accId);
        if (!$account || ($account['provider'] ?? '') !== EmailService::PROVIDER) {
            self::back('error', 'Conta não encontrada.');
            return;
        }

        $payload = self::buildRulePayload(Request::all(), $accId);
        EmailRule::create($payload);
        self::back('ok', 'Regra criada.', $accId);
    }

    public function updateRule(string $ruleId): void
    {
        Permission::abortIfCannot('integrations.edit');
        $rule = EmailRule::find((int)$ruleId);
        if (!$rule) {
            self::back('error', 'Regra não encontrada.');
            return;
        }
        $accId = (int)$rule['integration_account_id'];
        $payload = self::buildRulePayload(Request::all(), $accId);
        // Não troca a conta da regra
        unset($payload['integration_account_id']);
        EmailRule::update((int)$ruleId, $payload);
        self::back('ok', 'Regra atualizada.', $accId);
    }

    public function deleteRule(string $ruleId): void
    {
        Permission::abortIfCannot('integrations.edit');
        $rule = EmailRule::find((int)$ruleId);
        $accId = $rule ? (int)$rule['integration_account_id'] : null;
        EmailRule::delete((int)$ruleId);
        self::back('ok', 'Regra removida.', $accId);
    }

    /* ===================== Helpers ===================== */

    /**
     * Monta a config (JSON) da conta a partir do request, preservando segredos quando vazios.
     */
    private static function buildConfig(array $in, array $existing): array
    {
        $cfg = $existing;

        $cfg['imap_host']      = trim((string)($in['imap_host'] ?? ($existing['imap_host'] ?? '')));
        $cfg['imap_port']      = (int)($in['imap_port'] ?? ($existing['imap_port'] ?? 993));
        $cfg['imap_encryption']= in_array(($in['imap_encryption'] ?? ''), ['ssl', 'tls', 'none'], true) ? $in['imap_encryption'] : ($existing['imap_encryption'] ?? 'ssl');
        $cfg['imap_validate_cert'] = !empty($in['imap_validate_cert']);
        $cfg['imap_folder']    = trim((string)($in['imap_folder'] ?? ($existing['imap_folder'] ?? 'INBOX'))) ?: 'INBOX';

        $cfg['smtp_host']      = trim((string)($in['smtp_host'] ?? ($existing['smtp_host'] ?? '')));
        $cfg['smtp_port']      = (int)($in['smtp_port'] ?? ($existing['smtp_port'] ?? 587));
        $cfg['smtp_encryption']= in_array(($in['smtp_encryption'] ?? ''), ['ssl', 'tls', 'none'], true) ? $in['smtp_encryption'] : ($existing['smtp_encryption'] ?? 'tls');

        $cfg['auth_user']      = trim((string)($in['auth_user'] ?? ($existing['auth_user'] ?? '')));
        $cfg['from_name']      = trim((string)($in['from_name'] ?? ($existing['from_name'] ?? '')));
        $cfg['from_address']   = trim((string)($in['from_address'] ?? '')) ?: $cfg['auth_user'];

        $cfg['unmatched_action'] = (($in['unmatched_action'] ?? '') === 'ingest') ? 'ingest' : 'ignore';
        $cfg['poll_lookback_days'] = max(1, (int)($in['poll_lookback_days'] ?? ($existing['poll_lookback_days'] ?? 2)));
        $cfg['strip_quoted']   = !empty($in['strip_quoted']);

        // Senha: só re-encripta se um novo valor não-vazio foi enviado
        $newPass = (string)($in['auth_pass'] ?? '');
        if ($newPass !== '') {
            $cfg['auth_pass_enc'] = Encryption::encrypt($newPass);
        } elseif (!empty($existing['auth_pass_enc'])) {
            $cfg['auth_pass_enc'] = $existing['auth_pass_enc'];
        }

        // Preserva watermark
        $cfg['last_uid'] = (int)($existing['last_uid'] ?? 0);

        return $cfg;
    }

    /**
     * Monta o payload de uma regra a partir do formulário simples
     * (palavras-chave + onde procurar) ou de JSON avançado.
     */
    private static function buildRulePayload(array $in, int $accountId): array
    {
        // Condições: modo avançado (JSON) tem prioridade se enviado e válido
        $conditions = [];
        $advanced = trim((string)($in['conditions_json'] ?? ''));
        if ($advanced !== '') {
            $decoded = json_decode($advanced, true);
            if (is_array($decoded)) {
                $conditions = $decoded;
            }
        }

        if (empty($conditions)) {
            $keywordsRaw = (string)($in['keywords'] ?? '');
            $keywords = array_filter(array_map('trim', explode(',', $keywordsRaw)), fn($k) => $k !== '');

            $searchIn = $in['search_in'] ?? ['subject', 'body'];
            if (!is_array($searchIn)) {
                $searchIn = [$searchIn];
            }
            $searchIn = array_values(array_intersect($searchIn, ['subject', 'body', 'from', 'from_domain']));
            if (empty($searchIn)) {
                $searchIn = ['subject', 'body'];
            }

            foreach ($keywords as $kw) {
                foreach ($searchIn as $field) {
                    $conditions[] = ['field' => $field, 'op' => 'contains', 'value' => $kw];
                }
            }
        }

        // Ações
        $actions = [
            'ingest'   => !isset($in['ingest']) || !empty($in['ingest']),
            '_builder' => [
                'keywords'  => (string)($in['keywords'] ?? ''),
                'search_in' => $in['search_in'] ?? ['subject', 'body'],
            ],
        ];
        foreach (['funnel_id', 'stage_id', 'department_id', 'agent_id'] as $k) {
            $v = self::intOrNull($in[$k] ?? null);
            if ($v !== null) {
                $actions[$k] = $v;
            }
        }
        $priority = trim((string)($in['action_priority'] ?? ''));
        if (in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $actions['priority'] = $priority;
        }
        $tag = trim((string)($in['action_tag'] ?? ''));
        if ($tag !== '') {
            $actions['tag'] = $tag;
        }

        return [
            'integration_account_id' => $accountId,
            'name'          => trim((string)($in['name'] ?? 'Regra')) ?: 'Regra',
            'priority'      => (int)($in['priority'] ?? 0),
            'match_type'    => (($in['match_type'] ?? 'any') === 'all') ? 'all' : 'any',
            'conditions'    => $conditions,
            'actions'       => $actions,
            'stop_on_match' => isset($in['stop_on_match']) ? 1 : 0,
            'is_active'     => isset($in['is_active']) ? 1 : 0,
        ];
    }

    private static function intOrNull($v): ?int
    {
        if ($v === null || $v === '' || (int)$v === 0) {
            return null;
        }
        return (int)$v;
    }

    private static function back(string $type, string $msg, ?int $accountId = null): void
    {
        $url = Url::to('/email-integration') . '?' . http_build_query(array_filter([
            $type   => 1,
            'msg'   => $msg,
            'account' => $accountId,
        ]));
        Response::redirect($url);
    }
}
