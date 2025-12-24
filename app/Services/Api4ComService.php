<?php
/**
 * Service Api4ComService
 * Integração com Api4Com API para chamadas telefônicas
 */

namespace App\Services;

use App\Models\Api4ComAccount;
use App\Models\Api4ComExtension;
use App\Models\Api4ComCall;
use App\Models\Contact;
use App\Models\Conversation;
use App\Helpers\Validator;
use App\Helpers\Logger;

class Api4ComService
{
    /**
     * Criar chamada de voz (outbound)
     * 
     * @param array $data Dados da chamada
     * @return array Chamada criada
     */
    public static function createCall(array $data): array
    {
        // Validar dados
        $errors = Validator::validate($data, [
            'api4com_account_id' => 'required|integer',
            'contact_id' => 'required|integer',
            'to_number' => 'required|string',
            'agent_id' => 'nullable|integer',
            'conversation_id' => 'nullable|integer',
            'extension_id' => 'nullable|integer'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Verificar conta Api4Com
        $account = Api4ComAccount::find($data['api4com_account_id']);
        if (!$account || !$account['enabled']) {
            throw new \InvalidArgumentException('Conta Api4Com não encontrada ou desabilitada');
        }

        // Verificar contato
        $contact = Contact::find($data['contact_id']);
        if (!$contact) {
            throw new \InvalidArgumentException('Contato não encontrado');
        }

        // Buscar ramal do agente
        $extension = null;
        if (!empty($data['extension_id'])) {
            $extension = Api4ComExtension::find($data['extension_id']);
        } elseif (!empty($data['agent_id'])) {
            $extensions = Api4ComExtension::getByUser($data['agent_id']);
            $extension = !empty($extensions) ? $extensions[0] : null;
        }

        if (!$extension || $extension['status'] !== 'active') {
            throw new \InvalidArgumentException('Ramal não encontrado ou inativo para este usuário');
        }

        // Normalizar número de destino
        $toNumber = self::normalizePhoneNumber($data['to_number']);
        $fromNumber = $extension['extension_number'] ?? '';

        // Criar registro da chamada no banco
        $callData = [
            'api4com_account_id' => $account['id'],
            'api4com_extension_id' => $extension['id'],
            'contact_id' => $contact['id'],
            'agent_id' => $data['agent_id'] ?? null,
            'conversation_id' => $data['conversation_id'] ?? null,
            'direction' => 'outbound',
            'status' => 'initiated',
            'from_number' => $fromNumber,
            'to_number' => $toNumber,
            'metadata' => json_encode([
                'created_by' => $data['agent_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ])
        ];

        $callId = Api4ComCall::create($callData);
        $call = Api4ComCall::find($callId);

        // Iniciar chamada via Api4Com API
        try {
            $api4comResponse = self::initiateApi4ComCall($account, $extension, $toNumber);
            
            if ($api4comResponse['success']) {
                // Atualizar com ID da Api4Com
                Api4ComCall::update($callId, [
                    'api4com_call_id' => $api4comResponse['call_id'] ?? null,
                    'status' => 'ringing',
                    'started_at' => date('Y-m-d H:i:s'),
                    'metadata' => json_encode(array_merge(
                        json_decode($call['metadata'] ?? '{}', true),
                        ['api4com_response' => $api4comResponse]
                    ))
                ]);

                Logger::info("Api4ComService::createCall - Chamada iniciada: Call ID {$callId}, Api4Com ID: " . ($api4comResponse['call_id'] ?? 'N/A'));

                return Api4ComCall::find($callId);
            } else {
                // Falha na API Api4Com
                Api4ComCall::updateStatus($callId, 'failed', [
                    'error_message' => $api4comResponse['error'] ?? 'Erro desconhecido ao iniciar chamada'
                ]);
                throw new \Exception('Erro ao iniciar chamada: ' . ($api4comResponse['error'] ?? 'Erro desconhecido'));
            }
        } catch (\Exception $e) {
            Logger::error("Api4ComService::createCall - Erro: " . $e->getMessage());
            Api4ComCall::updateStatus($callId, 'failed', [
                'error_message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Iniciar chamada via API Api4Com
     * 
     * @param array $account Conta Api4Com
     * @param array $extension Ramal
     * @param string $toNumber Número de destino
     * @return array Resposta da API
     */
    private static function initiateApi4ComCall(array $account, array $extension, string $toNumber): array
    {
        $apiUrl = rtrim($account['api_url'], '/');
        $token = $account['api_token'];
        $extensionId = $extension['extension_id'] ?? $extension['extension_number'];
        
        // Endpoint: POST /dialer
        $url = $apiUrl . '/dialer';
        
        $payload = [
            'extension' => $extensionId,
            'phone' => $toNumber
        ];
        
        // Adicionar metadata se houver dados da chamada
        if (!empty($extension['metadata'])) {
            $metadata = json_decode($extension['metadata'], true);
            if (is_array($metadata)) {
                $payload['metadata'] = $metadata;
            }
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => 'Erro de conexão: ' . $error
            ];
        }

        $data = json_decode($response, true);
        
        Logger::info("Api4ComService::initiateApi4ComCall - HTTP {$httpCode}, Response: " . substr($response, 0, 500));

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'call_id' => $data['id'] ?? $data['call_id'] ?? null,
                'data' => $data
            ];
        } else {
            // Tratar erro corretamente (pode ser array ou string)
            $errorMsg = "HTTP {$httpCode}";
            if (is_array($data)) {
                if (isset($data['error'])) {
                    $errorMsg = is_array($data['error']) ? json_encode($data['error']) : $data['error'];
                } elseif (isset($data['message'])) {
                    $errorMsg = is_array($data['message']) ? json_encode($data['message']) : $data['message'];
                } elseif (isset($data['msg'])) {
                    $errorMsg = $data['msg'];
                } else {
                    $errorMsg = "HTTP {$httpCode}: " . json_encode($data);
                }
            } elseif (!empty($response)) {
                $errorMsg = "HTTP {$httpCode}: " . substr($response, 0, 200);
            }
            
            return [
                'success' => false,
                'error' => $errorMsg
            ];
        }
    }

    /**
     * Processar webhook do Api4Com (atualizar status da chamada)
     * 
     * @param array $webhookData Dados do webhook
     * @return bool Sucesso
     */
    public static function processWebhook(array $webhookData): bool
    {
        Logger::info("Api4ComService::processWebhook - Webhook recebido: " . json_encode($webhookData));

        // Identificar chamada por api4com_call_id
        $call = null;
        
        if (!empty($webhookData['call_id']) || !empty($webhookData['id'])) {
            $callId = $webhookData['call_id'] ?? $webhookData['id'];
            $call = Api4ComCall::findByApi4ComId($callId);
        }

        if (!$call) {
            Logger::warning("Api4ComService::processWebhook - Chamada não encontrada para webhook");
            return false;
        }

        // Mapear evento/status do Api4Com para nosso sistema
        $event = $webhookData['event'] ?? $webhookData['status'] ?? 'unknown';
        $status = self::mapApi4ComStatus($event);
        
        $updateData = [
            'status' => $status
        ];

        // Atualizar timestamps baseado no evento
        if (in_array($event, ['call.answered', 'call.ringing']) || $status === 'answered') {
            if (!empty($webhookData['answered_at'])) {
                $updateData['answered_at'] = $webhookData['answered_at'];
            } elseif ($status === 'answered') {
                $updateData['answered_at'] = date('Y-m-d H:i:s');
            }
        }
        
        if (in_array($event, ['call.ended', 'call.failed', 'call.cancelled']) || in_array($status, ['ended', 'failed', 'cancelled'])) {
            if (!empty($webhookData['ended_at'])) {
                $updateData['ended_at'] = $webhookData['ended_at'];
            } else {
                $updateData['ended_at'] = date('Y-m-d H:i:s');
            }
            
            if (isset($webhookData['duration'])) {
                $updateData['duration'] = (int)$webhookData['duration'];
            } elseif ($call['started_at']) {
                // Calcular duração
                $start = strtotime($call['started_at']);
                $end = time();
                $updateData['duration'] = max(0, $end - $start);
            }
        }

        if ($event === 'call.ringing' || $status === 'ringing') {
            if (!empty($webhookData['started_at'])) {
                $updateData['started_at'] = $webhookData['started_at'];
            } elseif (!$call['started_at']) {
                $updateData['started_at'] = date('Y-m-d H:i:s');
            }
        }

        if (!empty($webhookData['recording_url']) || !empty($webhookData['record_url'])) {
            $updateData['recording_url'] = $webhookData['recording_url'] ?? $webhookData['record_url'];
        }

        if (!empty($webhookData['error'])) {
            $updateData['error_message'] = $webhookData['error'];
        }

        // Atualizar metadata
        $metadata = json_decode($call['metadata'] ?? '{}', true);
        $metadata['webhook_data'][] = $webhookData;
        $updateData['metadata'] = json_encode($metadata);

        Api4ComCall::update($call['id'], $updateData);

        Logger::info("Api4ComService::processWebhook - Chamada {$call['id']} atualizada para status: {$status}");

        // Notificar via WebSocket se necessário
        if ($call['conversation_id']) {
            \App\Helpers\WebSocket::notifyConversationUpdate($call['conversation_id'], [
                'type' => 'api4com_call_updated',
                'call_id' => $call['id'],
                'status' => $status
            ]);
        }

        return true;
    }

    /**
     * Mapear status do Api4Com para nosso sistema
     */
    private static function mapApi4ComStatus(string $event): string
    {
        $statusMap = [
            'call.initiated' => 'initiated',
            'call.ringing' => 'ringing',
            'call.answered' => 'answered',
            'call.ended' => 'ended',
            'call.failed' => 'failed',
            'call.cancelled' => 'cancelled',
            'call.busy' => 'failed',
            'call.no-answer' => 'failed',
            'initiated' => 'initiated',
            'ringing' => 'ringing',
            'answered' => 'answered',
            'ended' => 'ended',
            'failed' => 'failed',
            'cancelled' => 'cancelled'
        ];

        return $statusMap[strtolower($event)] ?? 'initiated';
    }

    /**
     * Encerrar chamada
     */
    public static function endCall(int $callId): bool
    {
        $call = Api4ComCall::find($callId);
        if (!$call) {
            throw new \InvalidArgumentException('Chamada não encontrada');
        }

        if (in_array($call['status'], ['ended', 'failed', 'cancelled'])) {
            return true; // Já finalizada
        }

        $account = Api4ComAccount::find($call['api4com_account_id']);
        if (!$account || !$account['enabled']) {
            throw new \InvalidArgumentException('Conta Api4Com não encontrada ou desabilitada');
        }

        // Encerrar via API Api4Com se tiver call_id
        if (!empty($call['api4com_call_id'])) {
            try {
                self::endApi4ComCall($account, $call['api4com_call_id']);
            } catch (\Exception $e) {
                Logger::error("Api4ComService::endCall - Erro ao encerrar via API: " . $e->getMessage());
            }
        }

        // Atualizar status
        Api4ComCall::updateStatus($callId, 'ended', [
            'ended_at' => date('Y-m-d H:i:s'),
            'duration' => $call['started_at'] ? (time() - strtotime($call['started_at'])) : 0
        ]);

        return true;
    }

    /**
     * Encerrar chamada via API Api4Com
     */
    private static function endApi4ComCall(array $account, string $api4comCallId): void
    {
        $apiUrl = rtrim($account['api_url'], '/');
        $url = $apiUrl . '/calls/' . $api4comCallId . '/hangup';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $account['api_token']
            ],
            CURLOPT_TIMEOUT => 10
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Normalizar número de telefone
     */
    private static function normalizePhoneNumber(string $phone): string
    {
        // Remover caracteres especiais
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return $phone;
    }

    /**
     * Sincronizar ramal do usuário
     */
    public static function syncExtension(int $userId, int $accountId, array $extensionData): int
    {
        $existing = Api4ComExtension::findByUserAndAccount($userId, $accountId);
        
        $data = [
            'user_id' => $userId,
            'api4com_account_id' => $accountId,
            'extension_id' => $extensionData['extension_id'] ?? null,
            'extension_number' => $extensionData['extension_number'] ?? null,
            'sip_username' => $extensionData['sip_username'] ?? null,
            'sip_password' => !empty($extensionData['sip_password']) 
                ? password_hash($extensionData['sip_password'], PASSWORD_DEFAULT) 
                : null,
            'status' => $extensionData['status'] ?? 'active',
            'metadata' => json_encode($extensionData['metadata'] ?? [])
        ];

        if ($existing) {
            // Atualizar senha apenas se fornecida
            if (empty($data['sip_password'])) {
                unset($data['sip_password']);
            }
            Api4ComExtension::update($existing['id'], $data);
            return $existing['id'];
        } else {
            return Api4ComExtension::create($data);
        }
    }

    /**
     * Buscar ramais da API Api4Com
     * 
     * @param int $accountId ID da conta
     * @return array Lista de ramais
     */
    public static function fetchExtensionsFromApi(int $accountId): array
    {
        $account = Api4ComAccount::find($accountId);
        if (!$account) {
            throw new \InvalidArgumentException('Conta Api4Com não encontrada');
        }

        $apiUrl = rtrim($account['api_url'], '/');
        $token = $account['api_token'];
        
        // Endpoint: GET /extensions (pode variar conforme API)
        $url = $apiUrl . '/extensions';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Logger::error("Api4ComService::fetchExtensionsFromApi - Erro: " . $error);
            throw new \Exception('Erro de conexão: ' . $error);
        }

        $data = json_decode($response, true);

        Logger::info("Api4ComService::fetchExtensionsFromApi - HTTP {$httpCode}, Response: " . substr($response, 0, 500));

        if ($httpCode >= 200 && $httpCode < 300) {
            // Normalizar resposta (pode ser array direto ou dentro de data/extensions)
            $extensions = $data['data'] ?? $data['extensions'] ?? $data ?? [];
            
            // Se não é array indexado, pode ser que a resposta veio em outro formato
            if (!is_array($extensions) || (is_array($extensions) && !isset($extensions[0]) && !empty($extensions))) {
                // Pode ser um objeto único ou formato diferente
                Logger::info("Api4ComService::fetchExtensionsFromApi - Formato diferente, tentando normalizar");
                if (isset($extensions['extensions'])) {
                    $extensions = $extensions['extensions'];
                } elseif (isset($extensions['items'])) {
                    $extensions = $extensions['items'];
                } elseif (isset($extensions['results'])) {
                    $extensions = $extensions['results'];
                }
            }
            
            // Garantir que é array
            if (!is_array($extensions)) {
                $extensions = [];
            }
            
            Logger::info("Api4ComService::fetchExtensionsFromApi - Encontrados " . count($extensions) . " ramais");
            
            return $extensions;
        } else {
            // Melhorar tratamento de erro
            $errorMsg = "HTTP {$httpCode}";
            if (is_array($data)) {
                if (isset($data['error'])) {
                    $errorMsg = is_array($data['error']) ? json_encode($data['error']) : $data['error'];
                } elseif (isset($data['message'])) {
                    $errorMsg = is_array($data['message']) ? json_encode($data['message']) : $data['message'];
                } elseif (isset($data['msg'])) {
                    $errorMsg = $data['msg'];
                } else {
                    $errorMsg = "HTTP {$httpCode}: " . json_encode($data);
                }
            } elseif (!empty($response)) {
                $errorMsg = "HTTP {$httpCode}: " . substr($response, 0, 200);
            }
            
            Logger::error("Api4ComService::fetchExtensionsFromApi - Erro API: " . $errorMsg);
            throw new \Exception('Erro na API: ' . $errorMsg);
        }
    }

    /**
     * Sincronizar todos os ramais de uma conta
     * Busca da API e salva no banco
     * 
     * @param int $accountId ID da conta
     * @return array Resultado da sincronização
     */
    public static function syncAllExtensions(int $accountId): array
    {
        $extensions = self::fetchExtensionsFromApi($accountId);
        
        $synced = 0;
        $errors = [];
        
        foreach ($extensions as $ext) {
            try {
                // Normalizar dados do ramal
                $extensionId = $ext['id'] ?? $ext['extension_id'] ?? null;
                $extensionNumber = $ext['number'] ?? $ext['extension'] ?? $ext['extension_number'] ?? null;
                $name = $ext['name'] ?? $ext['description'] ?? "Ramal {$extensionNumber}";
                
                if (!$extensionId && !$extensionNumber) {
                    continue;
                }
                
                // Verificar se já existe
                $existing = Api4ComExtension::findByExtensionId($extensionId, $accountId);
                
                $data = [
                    'api4com_account_id' => $accountId,
                    'extension_id' => $extensionId,
                    'extension_number' => $extensionNumber,
                    'sip_username' => $ext['sip_username'] ?? $ext['username'] ?? null,
                    'status' => $ext['status'] ?? 'active',
                    'metadata' => json_encode([
                        'name' => $name,
                        'synced_at' => date('Y-m-d H:i:s'),
                        'original_data' => $ext
                    ])
                ];
                
                if ($existing) {
                    // Manter user_id e sip_password existentes
                    unset($data['user_id']);
                    unset($data['sip_password']);
                    Api4ComExtension::update($existing['id'], $data);
                } else {
                    Api4ComExtension::create($data);
                }
                
                $synced++;
            } catch (\Exception $e) {
                $errors[] = "Ramal {$extensionNumber}: " . $e->getMessage();
            }
        }
        
        return [
            'synced' => $synced,
            'total' => count($extensions),
            'errors' => $errors
        ];
    }

    /**
     * Listar ramais de uma conta
     */
    public static function getExtensions(int $accountId): array
    {
        return Api4ComExtension::where('api4com_account_id', '=', $accountId);
    }

    /**
     * Associar ramal a usuário
     */
    public static function assignExtensionToUser(int $extensionId, ?int $userId): bool
    {
        $extension = Api4ComExtension::find($extensionId);
        if (!$extension) {
            throw new \InvalidArgumentException('Ramal não encontrado');
        }

        // Se já tem usuário associado e está atribuindo a outro, desassociar primeiro
        if ($userId) {
            // Desassociar outros ramais deste usuário na mesma conta
            $existingExtensions = Api4ComExtension::where('user_id', '=', $userId);
            foreach ($existingExtensions as $ext) {
                if ($ext['api4com_account_id'] == $extension['api4com_account_id'] && $ext['id'] != $extensionId) {
                    Api4ComExtension::update($ext['id'], ['user_id' => null]);
                }
            }
        }

        return Api4ComExtension::update($extensionId, ['user_id' => $userId]) !== false;
    }

    /**
     * Obter estatísticas de chamadas
     */
    public static function getStatistics(?int $agentId = null, ?int $contactId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $db = \App\Helpers\Database::getInstance();
        
        $where = ['1=1'];
        $params = [];

        if ($agentId) {
            $where[] = 'agent_id = ?';
            $params[] = $agentId;
        }

        if ($contactId) {
            $where[] = 'contact_id = ?';
            $params[] = $contactId;
        }

        if ($dateFrom) {
            $where[] = 'created_at >= ?';
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $where[] = 'created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT 
                    COUNT(*) as total_calls,
                    SUM(CASE WHEN status = 'answered' THEN 1 ELSE 0 END) as answered_calls,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_calls,
                    SUM(CASE WHEN status = 'ended' THEN duration ELSE 0 END) as total_duration,
                    AVG(CASE WHEN status = 'ended' THEN duration ELSE NULL END) as avg_duration
                FROM api4com_calls
                WHERE {$whereClause}";

        $stats = $db->fetch($sql, $params);

        return [
            'total_calls' => (int)($stats['total_calls'] ?? 0),
            'answered_calls' => (int)($stats['answered_calls'] ?? 0),
            'failed_calls' => (int)($stats['failed_calls'] ?? 0),
            'total_duration' => (int)($stats['total_duration'] ?? 0),
            'avg_duration' => round((float)($stats['avg_duration'] ?? 0), 2),
            'answer_rate' => ($stats['total_calls'] > 0) 
                ? round(($stats['answered_calls'] / $stats['total_calls']) * 100, 2) 
                : 0
        ];
    }
}

