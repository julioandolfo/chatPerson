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
        'whatsapp_id' // ID correspondente em whatsapp_accounts
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
        $waAccount = WhatsAppAccount::find($whatsappAccountId);
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
        
        // Fallback: buscar pelo phone_number
        if (!empty($account['phone_number'])) {
            $waAccount = WhatsAppAccount::findByPhone($account['phone_number']);
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
        // Prioridade 1: integration_account_id (já é o ID correto)
        if (!empty($conversation['integration_account_id'])) {
            return (int)$conversation['integration_account_id'];
        }
        
        // Prioridade 2: traduzir whatsapp_account_id para integration_account_id
        if (!empty($conversation['whatsapp_account_id'])) {
            $integrationId = self::getIntegrationIdFromWhatsAppId((int)$conversation['whatsapp_account_id']);
            if ($integrationId) {
                return $integrationId;
            }
        }
        
        return null;
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
        $existingWa = WhatsAppAccount::findByPhone($data['phone_number']);
        
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
            
            $waId = WhatsAppAccount::create($waData);
            
            // Atualizar whatsapp_id na integration_accounts
            self::update($integrationId, ['whatsapp_id' => $waId]);
        }
    }
}

