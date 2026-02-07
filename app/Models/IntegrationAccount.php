<?php
/**
 * Model IntegrationAccount
 * Model unificado para todas as integrações (Notificame, WhatsApp Official, Quepasa, Evolution)
 */

namespace App\Models;

class IntegrationAccount extends Model
{
    protected string $table = 'integration_accounts';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name', 'provider', 'channel', 'api_token', 'api_url', 
        'account_id', 'phone_number', 'username', 'status', 
        'config', 'webhook_url', 'webhook_secret', 
        'default_funnel_id', 'default_stage_id', 
        'last_sync_at', 'error_message',
        'whatsapp_id',
        // Campos Quepasa (migrados de whatsapp_accounts)
        'quepasa_user', 'quepasa_trackid', 'quepasa_chatid', 'quepasa_token',
        'instance_id', 'api_key',
        // Campos WaVoIP
        'wavoip_token', 'wavoip_enabled',
        // Campos de monitoramento de conexão
        'last_connection_check', 'last_connection_result',
        'last_connection_message', 'consecutive_failures',
        // Campos de limite de novas conversas (rate limit)
        'new_conv_limit_enabled',
        'new_conv_limit_count',
        'new_conv_limit_period',
        'new_conv_limit_period_value'
    ];
    protected bool $timestamps = true;

    /**
     * Buscar contas por provider e channel
     */
    public static function findByProviderChannel(string $provider, string $channel): array
    {
        $sql = "SELECT * FROM integration_accounts WHERE provider = ? AND channel = ?";
        return \App\Helpers\Database::fetchAll($sql, [$provider, $channel]);
    }

    /**
     * Buscar conta por provider, channel e phone_number
     */
    public static function findByProviderChannelPhone(string $provider, string $channel, string $phoneNumber): ?array
    {
        $sql = "SELECT * FROM integration_accounts 
                WHERE provider = ? AND channel = ? AND phone_number = ? 
                LIMIT 1";
        return \App\Helpers\Database::fetch($sql, [$provider, $channel, $phoneNumber]);
    }

    /**
     * Buscar por número de telefone
     */
    public static function findByPhone(string $phoneNumber, string $channel = 'whatsapp'): ?array
    {
        $sql = "SELECT * FROM integration_accounts 
                WHERE phone_number = ? AND channel = ? 
                LIMIT 1";
        return \App\Helpers\Database::fetch($sql, [$phoneNumber, $channel]);
    }

    /**
     * Obter contas ativas
     */
    public static function getActive(string $channel = null): array
    {
        if ($channel) {
            $sql = "SELECT * FROM integration_accounts WHERE status = 'active' AND channel = ?";
            return \App\Helpers\Database::fetchAll($sql, [$channel]);
        }

        $sql = "SELECT * FROM integration_accounts WHERE status = 'active'";
        return \App\Helpers\Database::fetchAll($sql, []);
    }

    /**
     * Obter contas por canal
     */
    public static function getByChannel(string $channel): array
    {
        $sql = "SELECT * FROM integration_accounts WHERE channel = ?";
        return \App\Helpers\Database::fetchAll($sql, [$channel]);
    }

    /**
     * Obter primeira conta ativa
     */
    public static function getFirstActive(string $channel = null): ?array
    {
        $accounts = self::getActive($channel);
        return !empty($accounts) ? $accounts[0] : null;
    }

    /**
     * Obter contas por provider
     */
    public static function getByProvider(string $provider): array
    {
        $sql = "SELECT * FROM integration_accounts WHERE provider = ?";
        return \App\Helpers\Database::fetchAll($sql, [$provider]);
    }

    /**
     * Buscar conta por webhook URL
     */
    public static function findByWebhookUrl(string $webhookUrl): ?array
    {
        $sql = "SELECT * FROM integration_accounts WHERE webhook_url = ? LIMIT 1";
        return \App\Helpers\Database::fetch($sql, [$webhookUrl]);
    }

    /**
     * Obter configuração JSON decodificada
     */
    public function getConfig(): array
    {
        $config = $this->attributes['config'] ?? null;
        if (is_string($config)) {
            return json_decode($config, true) ?? [];
        }
        return is_array($config) ? $config : [];
    }

    /**
     * Definir configuração JSON
     */
    public function setConfig(array $config): void
    {
        $this->attributes['config'] = json_encode($config);
    }

    // ========================================
    // MÉTODOS DE COMPATIBILIDADE COM WHATSAPP_ACCOUNTS
    // ========================================

    /**
     * Obter contas WhatsApp ativas (substitui WhatsAppAccount::getActive())
     */
    public static function getActiveWhatsApp(): array
    {
        return self::getActive('whatsapp');
    }

    /**
     * Buscar conta WhatsApp por telefone (substitui WhatsAppAccount::findByPhone())
     */
    public static function findWhatsAppByPhone(string $phoneNumber): ?array
    {
        return self::findByPhone($phoneNumber, 'whatsapp');
    }

    /**
     * Obter todas as contas WhatsApp (ativas e inativas)
     */
    public static function getAllWhatsApp(): array
    {
        return self::getByChannel('whatsapp');
    }

    /**
     * Retorna o quepasa_token (compatibilidade com código legado)
     * integration_accounts usa api_token, whatsapp_accounts usava quepasa_token
     */
    public static function getQuepasaToken(array $account): ?string
    {
        $accountId = $account['id'] ?? '?';
        $accountName = $account['name'] ?? '?';
        
        // Primeiro tenta api_token (integration_accounts)
        if (!empty($account['api_token'])) {
            return $account['api_token'];
        }
        // Fallback: quepasa_token (se veio de whatsapp_accounts)
        if (!empty($account['quepasa_token'])) {
            \App\Helpers\Logger::unificacao("[FALLBACK] getQuepasaToken: Conta IA#{$accountId} ({$accountName}) - usando quepasa_token direto (campo legado presente no array)");
            return $account['quepasa_token'];
        }
        // Último fallback: buscar em whatsapp_accounts pelo telefone
        if (!empty($account['phone_number'])) {
            \App\Helpers\Logger::unificacao("[FALLBACK] getQuepasaToken: Conta IA#{$accountId} ({$accountName}) - api_token vazio, buscando em whatsapp_accounts por phone={$account['phone_number']}");
            $waAccount = \App\Helpers\Database::fetch(
                "SELECT quepasa_token FROM whatsapp_accounts WHERE phone_number = ? AND quepasa_token IS NOT NULL AND quepasa_token != '' LIMIT 1",
                [$account['phone_number']]
            );
            if ($waAccount && !empty($waAccount['quepasa_token'])) {
                \App\Helpers\Logger::unificacao("[FALLBACK] getQuepasaToken: ✅ Token encontrado em whatsapp_accounts para phone={$account['phone_number']}");
                return $waAccount['quepasa_token'];
            }
            \App\Helpers\Logger::unificacao("[ERROR] getQuepasaToken: ❌ Nenhum token encontrado para conta IA#{$accountId} ({$accountName}) - nem api_token nem quepasa_token");
        }
        return null;
    }

    /**
     * Obter dados de config Quepasa a partir do campo config JSON
     */
    public static function getQuepasaConfig(array $account): array
    {
        $config = is_string($account['config'] ?? null) 
            ? (json_decode($account['config'], true) ?? []) 
            : ($account['config'] ?? []);
        
        return [
            'quepasa_user' => $config['quepasa_user'] ?? null,
            'quepasa_trackid' => $config['quepasa_trackid'] ?? null,
            'quepasa_chatid' => $config['quepasa_chatid'] ?? null,
            'instance_id' => $config['instance_id'] ?? ($account['account_id'] ?? null),
        ];
    }

    // ========================================
    // MÉTODOS DE TRADUÇÃO WHATSAPP_ACCOUNTS <-> INTEGRATION_ACCOUNTS
    // ========================================

    /**
     * Buscar integration_account pelo whatsapp_account_id
     * Usa o campo whatsapp_id para tradução direta
     * 
     * @param int $whatsappAccountId ID da tabela whatsapp_accounts
     * @return array|null Conta de integração correspondente
     */
    public static function findByWhatsAppAccountId(int $whatsappAccountId): ?array
    {
        // Primeiro tenta pelo campo whatsapp_id (mais confiável)
        $sql = "SELECT * FROM integration_accounts WHERE whatsapp_id = ? LIMIT 1";
        $account = \App\Helpers\Database::fetch($sql, [$whatsappAccountId]);
        
        if ($account) {
            return $account;
        }
        
        // Fallback: buscar pelo phone_number da whatsapp_accounts
        $waAccount = \App\Helpers\Database::fetch(
            "SELECT phone_number FROM whatsapp_accounts WHERE id = ? LIMIT 1",
            [$whatsappAccountId]
        );
        if ($waAccount && !empty($waAccount['phone_number'])) {
            return self::findByPhone($waAccount['phone_number'], 'whatsapp');
        }
        
        return null;
    }

    /**
     * Obter o integration_account_id a partir do whatsapp_account_id
     * 
     * @param int $whatsappAccountId ID da tabela whatsapp_accounts
     * @return int|null ID da tabela integration_accounts
     */
    public static function getIntegrationIdFromWhatsAppId(int $whatsappAccountId): ?int
    {
        $account = self::findByWhatsAppAccountId($whatsappAccountId);
        return $account ? (int)$account['id'] : null;
    }

    /**
     * Obter o whatsapp_account_id a partir do integration_account_id
     * 
     * @param int $integrationAccountId ID da tabela integration_accounts
     * @return int|null ID da tabela whatsapp_accounts
     */
    public static function getWhatsAppIdFromIntegrationId(int $integrationAccountId): ?int
    {
        $account = self::find($integrationAccountId);
        
        if (!$account) {
            return null;
        }
        
        // Primeiro verifica o campo whatsapp_id
        if (!empty($account['whatsapp_id'])) {
            return (int)$account['whatsapp_id'];
        }
        
        // Fallback: buscar pelo phone_number na whatsapp_accounts
        if (!empty($account['phone_number'])) {
            $waAccount = \App\Helpers\Database::fetch(
                "SELECT id FROM whatsapp_accounts WHERE phone_number = ? LIMIT 1",
                [$account['phone_number']]
            );
            return $waAccount ? (int)$waAccount['id'] : null;
        }
        
        return null;
    }

    /**
     * Resolver qual conta usar para envio de mensagens
     * Sempre retorna o integration_account_id correto
     * 
     * @param array $conversation Array da conversa com whatsapp_account_id e/ou integration_account_id
     * @return int|null ID da tabela integration_accounts para usar no envio
     */
    public static function resolveAccountForSending(array $conversation): ?int
    {
        $convId = $conversation['id'] ?? '?';
        
        // Prioridade 1: integration_account_id (já é o ID correto)
        if (!empty($conversation['integration_account_id'])) {
            return (int)$conversation['integration_account_id'];
        }
        
        // Prioridade 2: traduzir whatsapp_account_id para integration_account_id
        if (!empty($conversation['whatsapp_account_id'])) {
            \App\Helpers\Logger::unificacao("[RESOLVE] Conversa #{$convId}: sem integration_account_id, tentando resolver via whatsapp_account_id={$conversation['whatsapp_account_id']}");
            $integrationId = self::getIntegrationIdFromWhatsAppId((int)$conversation['whatsapp_account_id']);
            if ($integrationId) {
                \App\Helpers\Logger::unificacao("[RESOLVE] Conversa #{$convId}: ✅ Resolvido whatsapp_account_id={$conversation['whatsapp_account_id']} → integration_account_id={$integrationId}");
                return $integrationId;
            }
            \App\Helpers\Logger::unificacao("[ERROR] Conversa #{$convId}: ❌ Não foi possível resolver whatsapp_account_id={$conversation['whatsapp_account_id']} para integration_account_id");
        }
        
        \App\Helpers\Logger::unificacao("[ERROR] Conversa #{$convId}: ❌ Nenhum account_id disponível para envio");
        return null;
    }

    // ========================================
    // MÉTODOS PARA WhatsAppService (substituem WhatsAppAccount::)
    // ========================================

    /**
     * Buscar contas WhatsApp por campo (substitui WhatsAppAccount::where())
     * Retorna array de contas que correspondem ao filtro
     */
    public static function whereWhatsApp(string $field, string $operator, $value): array
    {
        $sql = "SELECT * FROM integration_accounts WHERE channel = 'whatsapp' AND `{$field}` {$operator} ?";
        return \App\Helpers\Database::fetchAll($sql, [$value]);
    }

    /**
     * Atualizar conta em integration_accounts E sincronizar com whatsapp_accounts
     * Substitui WhatsAppAccount::update() garantindo ambas as tabelas atualizadas
     */
    public static function updateWithSync(int $id, array $data): bool
    {
        // Atualizar integration_accounts
        $result = self::update($id, $data);
        
        // Sincronizar com whatsapp_accounts (backward compat)
        try {
            $account = self::find($id);
            if ($account) {
                $waId = $account['whatsapp_id'] ?? null;
                if (!$waId && !empty($account['phone_number'])) {
                    $wa = \App\Helpers\Database::fetch(
                        "SELECT id FROM whatsapp_accounts WHERE phone_number = ? LIMIT 1",
                        [$account['phone_number']]
                    );
                    $waId = $wa['id'] ?? null;
                }
                if ($waId) {
                    // Mapear campos que existem em whatsapp_accounts
                    $waFields = ['name', 'phone_number', 'provider', 'api_url', 'api_key', 'status',
                        'quepasa_user', 'quepasa_trackid', 'quepasa_chatid', 'quepasa_token',
                        'instance_id', 'wavoip_token', 'wavoip_enabled', 'default_funnel_id', 'default_stage_id',
                        'last_connection_check', 'last_connection_result', 'last_connection_message', 'consecutive_failures',
                        'config'];
                    $waUpdate = array_intersect_key($data, array_flip($waFields));
                    if (!empty($waUpdate)) {
                        // Atualizar whatsapp_accounts via SQL direto
                        $setParts = [];
                        $params = [];
                        foreach ($waUpdate as $field => $value) {
                            $setParts[] = "`{$field}` = ?";
                            $params[] = $value;
                        }
                        $params[] = $waId;
                        \App\Helpers\Database::getInstance()->prepare(
                            "UPDATE whatsapp_accounts SET " . implode(', ', $setParts) . " WHERE id = ?"
                        )->execute($params);
                    }
                }
            }
        } catch (\Exception $e) {
            // Não falhar se sync legado der erro
            \App\Helpers\Logger::unificacao("[SYNC] ⚠️ Erro ao sincronizar IA#{$id} com whatsapp_accounts: " . $e->getMessage());
        }
        
        return $result;
    }

    /**
     * Criar conta WhatsApp em integration_accounts E sincronizar com whatsapp_accounts
     * Substitui WhatsAppAccount::create()
     */
    public static function createWhatsApp(array $data): int
    {
        // Garantir channel = whatsapp
        $data['channel'] = 'whatsapp';
        
        // Mapear quepasa_token → api_token se necessário
        if (!empty($data['quepasa_token']) && empty($data['api_token'])) {
            $data['api_token'] = $data['quepasa_token'];
        }
        
        // Mapear provider
        if (empty($data['provider'])) {
            $data['provider'] = 'quepasa';
        }
        
        $integrationId = self::create($data);
        
        // Sincronizar com whatsapp_accounts
        try {
            self::syncToWhatsAppAccounts($integrationId, $data);
        } catch (\Exception $e) {
            \App\Helpers\Logger::unificacao("[SYNC] ⚠️ Erro ao criar sync whatsapp_accounts para IA#{$integrationId}: " . $e->getMessage());
        }
        
        return $integrationId;
    }

    // ========================================
    // SINCRONIZAÇÃO AUTOMÁTICA COM WHATSAPP_ACCOUNTS
    // ========================================

    /**
     * Criar conta de integração COM sincronização automática
     * Se for canal WhatsApp, também cria/atualiza em whatsapp_accounts
     * 
     * @param array $data Dados da conta
     * @return int ID da conta criada
     */
    public static function createWithSync(array $data): int
    {
        // Criar em integration_accounts
        $integrationId = self::create($data);
        
        // Se for WhatsApp, sincronizar com whatsapp_accounts
        if (($data['channel'] ?? '') === 'whatsapp' && !empty($data['phone_number'])) {
            try {
                self::syncToWhatsAppAccounts($integrationId, $data);
            } catch (\Exception $e) {
                \App\Helpers\Logger::error("IntegrationAccount::createWithSync - Erro ao sincronizar: " . $e->getMessage());
            }
        }
        
        return $integrationId;
    }

    /**
     * Sincronizar conta de integração com whatsapp_accounts
     * Cria ou atualiza a entrada correspondente em whatsapp_accounts
     * 
     * @param int $integrationId ID da conta de integração
     * @param array $data Dados da conta
     */
    public static function syncToWhatsAppAccounts(int $integrationId, array $data): void
    {
        // Verificar se já existe em whatsapp_accounts pelo phone_number
        $existingWa = \App\Helpers\Database::fetch(
            "SELECT * FROM whatsapp_accounts WHERE phone_number = ? LIMIT 1",
            [$data['phone_number']]
        );
        
        if ($existingWa) {
            // Atualizar whatsapp_id na integration_accounts se não estiver definido
            $integration = self::find($integrationId);
            if ($integration && empty($integration['whatsapp_id'])) {
                self::update($integrationId, ['whatsapp_id' => $existingWa['id']]);
            }
        } else {
            // Criar nova entrada em whatsapp_accounts
            $waData = [
                'name' => $data['name'],
                'phone_number' => $data['phone_number'],
                'provider' => $data['provider'] ?? 'quepasa',
                'api_url' => $data['api_url'] ?? null,
                'api_key' => $data['api_token'] ?? null,
                'quepasa_token' => $data['api_token'] ?? null,
                'status' => $data['status'] ?? 'inactive',
                'default_funnel_id' => $data['default_funnel_id'] ?? null,
                'default_stage_id' => $data['default_stage_id'] ?? null,
            ];
            
            // Extrair dados do config se existir
            $config = is_string($data['config'] ?? null) 
                ? json_decode($data['config'], true) 
                : ($data['config'] ?? []);
            
            if (!empty($config['quepasa_user'])) {
                $waData['quepasa_user'] = $config['quepasa_user'];
            }
            if (!empty($config['quepasa_trackid'])) {
                $waData['quepasa_trackid'] = $config['quepasa_trackid'];
            }
            if (!empty($config['quepasa_chatid'])) {
                $waData['quepasa_chatid'] = $config['quepasa_chatid'];
            }
            
            // Criar em whatsapp_accounts via SQL direto (sem depender do model)
            $waFields = array_keys($waData);
            $waPlaceholders = implode(', ', array_fill(0, count($waFields), '?'));
            $waColumns = implode(', ', array_map(fn($f) => "`{$f}`", $waFields));
            $stmt = \App\Helpers\Database::getInstance()->prepare(
                "INSERT INTO whatsapp_accounts ({$waColumns}) VALUES ({$waPlaceholders})"
            );
            $stmt->execute(array_values($waData));
            $waId = \App\Helpers\Database::getInstance()->lastInsertId();
            
            // Atualizar whatsapp_id na integration_accounts
            self::update($integrationId, ['whatsapp_id' => $waId]);
        }
    }
}

