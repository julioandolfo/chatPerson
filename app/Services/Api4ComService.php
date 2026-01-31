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

                Logger::api4com("createCall - Chamada iniciada: Call ID {$callId}, Api4Com ID: " . ($api4comResponse['call_id'] ?? 'N/A'));

                // Criar nota interna no chat
                $updatedCall = Api4ComCall::find($callId);
                self::createCallNote($updatedCall, 'started');

                return $updatedCall;
            } else {
                // Falha na API Api4Com
                Api4ComCall::updateStatus($callId, 'failed', [
                    'error_message' => $api4comResponse['error'] ?? 'Erro desconhecido ao iniciar chamada'
                ]);
                throw new \Exception('Erro ao iniciar chamada: ' . ($api4comResponse['error'] ?? 'Erro desconhecido'));
            }
        } catch (\Exception $e) {
            Logger::api4com("createCall - Erro: " . $e->getMessage(), 'ERROR');
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
        $authHeader = self::formatAuthHeader($token);
        
        // Configurações da conta (podem customizar endpoint e campos)
        $config = !empty($account['config']) ? json_decode($account['config'], true) : [];
        
        // Determinar qual valor usar para o ramal:
        // 1. Se configurado extension_value_field, usa esse campo específico
        // 2. Senão, prioriza extension_number (padrão em APIs de telefonia)
        // 3. Fallback para extension_id
        $extensionValueField = $config['extension_value_field'] ?? null;
        if ($extensionValueField && !empty($extension[$extensionValueField])) {
            $extensionValue = $extension[$extensionValueField];
        } else {
            // Priorizar extension_number (número do ramal como "1001")
            // Se não existir, usar extension_id (ID interno como "123")
            $extensionValue = !empty($extension['extension_number']) 
                ? $extension['extension_number'] 
                : ($extension['extension_id'] ?? null);
        }
        
        // Validar que temos um valor para o ramal
        if (empty($extensionValue)) {
            Logger::api4com("initiateApi4ComCall - Ramal sem extension_number ou extension_id. Extension: " . json_encode($extension), 'ERROR');
            return [
                'success' => false,
                'error' => 'Ramal não possui número ou ID configurado. Verifique a configuração do ramal em Integrações → Api4Com → Ramais'
            ];
        }
        
        // Endpoint configurável (padrão: /api/v1/dialer conforme documentação Api4Com)
        $dialerEndpoint = $config['dialer_endpoint'] ?? '/api/v1/dialer';
        $url = $apiUrl . $dialerEndpoint;
        
        // Campos configuráveis
        $extensionField = $config['extension_field'] ?? 'extension';
        $phoneField = $config['phone_field'] ?? 'phone';
        
        $payload = [
            $extensionField => $extensionValue,
            $phoneField => $toNumber
        ];
        
        // Campos adicionais configuráveis
        if (!empty($config['extra_fields']) && is_array($config['extra_fields'])) {
            $payload = array_merge($payload, $config['extra_fields']);
        }
        
        Logger::api4com("initiateApi4ComCall - URL: {$url}, Extension: {$extensionValue}, Phone: {$toNumber}, Payload: " . json_encode($payload));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                $authHeader
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);

        if ($error) {
            Logger::api4com("initiateApi4ComCall - Curl error: {$error}", 'ERROR');
            return [
                'success' => false,
                'error' => 'Erro de conexão: ' . $error
            ];
        }

        $data = json_decode($response, true);
        
        Logger::api4com("initiateApi4ComCall - HTTP {$httpCode}, Response: " . substr($response, 0, 500));
        if ($httpCode >= 500) {
            Logger::api4com("initiateApi4ComCall - HTTP {$httpCode}, URL: {$url}, Payload: " . json_encode($payload) . ", Info: " . json_encode($curlInfo), 'ERROR');
        }

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
        Logger::api4com("processWebhook - Webhook recebido: " . json_encode($webhookData));

        // Identificar chamada por api4com_call_id
        $call = null;
        
        if (!empty($webhookData['call_id']) || !empty($webhookData['id'])) {
            $callId = $webhookData['call_id'] ?? $webhookData['id'];
            Logger::api4com("processWebhook - Buscando por api4com_call_id: {$callId}");
            $call = Api4ComCall::findByApi4ComId($callId);
        }

        // Se não encontrou, tentar busca alternativa por ramal + número chamado
        if (!$call) {
            $caller = $webhookData['caller'] ?? null;
            $called = $webhookData['called'] ?? null;
            
            if ($caller && $called) {
                // Normalizar número chamado (remover 0 inicial do DDD)
                $normalizedCalled = preg_replace('/^0/', '', $called);
                $normalizedCalled = preg_replace('/[^0-9]/', '', $normalizedCalled);
                
                Logger::api4com("processWebhook - Busca alternativa: caller={$caller}, called={$called} (normalized: {$normalizedCalled})");
                
                // Buscar chamada recente (últimos 5 minutos) do mesmo ramal para o mesmo número
                $sql = "SELECT * FROM api4com_calls 
                        WHERE status IN ('initiated', 'ringing', 'answered') 
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                        AND (
                            to_number LIKE ? 
                            OR to_number LIKE ?
                            OR to_number LIKE ?
                        )
                        ORDER BY created_at DESC 
                        LIMIT 1";
                
                $call = \App\Helpers\Database::fetch($sql, [
                    '%' . $normalizedCalled,
                    '+55' . $normalizedCalled,
                    $normalizedCalled
                ]);
                
                if ($call) {
                    Logger::api4com("processWebhook - Chamada encontrada via busca alternativa: ID {$call['id']}");
                    
                    // Atualizar o api4com_call_id para futuras referências
                    if (!empty($webhookData['id'])) {
                        Api4ComCall::update($call['id'], ['api4com_call_id' => $webhookData['id']]);
                    }
                }
            }
        }

        if (!$call) {
            Logger::api4com("processWebhook - Chamada não encontrada para webhook", 'WARNING');
            return false;
        }

        // Mapear evento/status do Api4Com para nosso sistema
        // v1.4 usa 'eventType', versões anteriores usam 'event' ou 'status'
        $event = $webhookData['eventType'] ?? $webhookData['event'] ?? $webhookData['status'] ?? 'unknown';
        $status = self::mapApi4ComStatus($event);
        
        Logger::api4com("processWebhook - Evento: {$event} -> Status: {$status}");
        
        $updateData = [
            'status' => $status
        ];

        // Atualizar timestamps baseado no evento
        $answerEvents = ['call.answered', 'call.ringing', 'channel-answer', 'channel-ringing'];
        if (in_array($event, $answerEvents) || $status === 'answered') {
            if (!empty($webhookData['answeredAt'])) {
                $updateData['answered_at'] = $webhookData['answeredAt'];
            } elseif (!empty($webhookData['answered_at'])) {
                $updateData['answered_at'] = $webhookData['answered_at'];
            } elseif ($status === 'answered') {
                $updateData['answered_at'] = date('Y-m-d H:i:s');
            }
        }
        
        $endEvents = ['call.ended', 'call.failed', 'call.cancelled', 'channel-hangup'];
        if (in_array($event, $endEvents) || in_array($status, ['ended', 'failed', 'cancelled'])) {
            if (!empty($webhookData['endedAt'])) {
                $updateData['ended_at'] = $webhookData['endedAt'];
            } elseif (!empty($webhookData['ended_at'])) {
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

        $ringingEvents = ['call.ringing', 'channel-ringing', 'channel-create'];
        if (in_array($event, $ringingEvents) || $status === 'ringing') {
            if (!empty($webhookData['startedAt'])) {
                $updateData['started_at'] = $webhookData['startedAt'];
            } elseif (!empty($webhookData['started_at'])) {
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

        Logger::api4com("processWebhook - Chamada {$call['id']} atualizada para status: {$status}");

        // Criar nota interna para eventos importantes
        $updatedCall = Api4ComCall::find($call['id']);
        if ($status === 'answered') {
            self::createCallNote($updatedCall, 'answered');
        } elseif (in_array($status, ['ended', 'failed', 'cancelled'])) {
            self::createCallNote($updatedCall, 'ended');
        }

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
            // Eventos v1.4 (channel-*)
            'channel-create' => 'initiated',
            'channel-ringing' => 'ringing',
            'channel-answer' => 'answered',
            'channel-hangup' => 'ended',
            // Eventos antigos (call.*)
            'call.initiated' => 'initiated',
            'call.ringing' => 'ringing',
            'call.answered' => 'answered',
            'call.ended' => 'ended',
            'call.failed' => 'failed',
            'call.cancelled' => 'cancelled',
            'call.busy' => 'failed',
            'call.no-answer' => 'failed',
            // Status simples
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
                Logger::api4com("endCall - Erro ao encerrar via API: " . $e->getMessage(), 'ERROR');
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
     * Criar nota interna no chat sobre a ligação
     */
    public static function createCallNote(array $call, string $event = 'started'): void
    {
        try {
            Logger::api4com("createCallNote - Iniciando para evento: {$event}, call_id: " . ($call['id'] ?? 'N/A'));
            
            if (empty($call['conversation_id'])) {
                Logger::api4com("createCallNote - Sem conversation_id, abortando");
                return;
            }
            if (empty($call['agent_id'])) {
                Logger::api4com("createCallNote - Sem agent_id, abortando");
                return;
            }
            
            Logger::api4com("createCallNote - conversation_id: {$call['conversation_id']}, agent_id: {$call['agent_id']}");
            
            $agentName = 'Agente';
            if (!empty($call['agent_id'])) {
                $user = \App\Helpers\Database::fetch("SELECT name FROM users WHERE id = ?", [$call['agent_id']]);
                $agentName = $user['name'] ?? 'Agente';
            }
            
            $toNumber = $call['to_number'] ?? 'desconhecido';
            $dateTime = date('d/m/Y H:i');
            
            if ($event === 'started') {
                $emoji = '📞';
                $statusText = 'iniciou uma ligação';
                $content = "{$emoji} <strong>{$agentName}</strong> {$statusText} para <strong>{$toNumber}</strong> em {$dateTime}";
            } elseif ($event === 'answered') {
                $emoji = '✅';
                $statusText = 'A ligação foi atendida';
                $content = "{$emoji} {$statusText}";
            } else {
                // ended/failed
                $statusLabel = Api4ComCall::getStatusLabel($call['status'] ?? 'ended');
                $duration = (int)($call['duration'] ?? 0);
                $durationText = Api4ComCall::formatDuration($duration);
                
                $emoji = in_array($call['status'], ['failed', 'no_answer', 'busy', 'cancelled']) ? '❌' : '📴';
                
                $content = "{$emoji} <strong>Ligação {$statusLabel}</strong>";
                if ($duration > 0) {
                    $content .= " • Duração: <strong>{$durationText}</strong>";
                }
                if (!empty($call['error_message'])) {
                    $content .= " • Erro: {$call['error_message']}";
                }
                if (!empty($call['recording_url'])) {
                    $content .= "<br>🎙️ <a href=\"{$call['recording_url']}\" target=\"_blank\">Ouvir gravação</a>";
                }
            }
            
            // Criar mensagem do tipo 'note' no chat
            \App\Helpers\Database::query(
                "INSERT INTO messages (conversation_id, sender_type, sender_id, content, message_type, created_at) 
                 VALUES (?, 'agent', ?, ?, 'note', NOW())",
                [$call['conversation_id'], $call['agent_id'], $content]
            );
            
            // Notificar via WebSocket
            \App\Helpers\WebSocket::notifyConversationUpdate($call['conversation_id'], [
                'type' => 'new_message',
                'subtype' => 'call_note'
            ]);
            
            Logger::api4com("createCallNote - Nota criada para chamada {$call['id']} ({$event})");
            
        } catch (\Exception $e) {
            Logger::api4com("createCallNote - Erro: " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Encerrar chamada via API Api4Com
     */
    private static function endApi4ComCall(array $account, string $api4comCallId): void
    {
        $apiUrl = rtrim($account['api_url'], '/');
        $url = $apiUrl . '/calls/' . $api4comCallId . '/hangup';
        $authHeader = self::formatAuthHeader($account['api_token']);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                $authHeader
            ],
            CURLOPT_TIMEOUT => 10
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Monta header de autorização respeitando token informado
     * Doc Api4Com usa "Authorization: <token>" (sem Bearer)
     */
    private static function formatAuthHeader(string $token): string
    {
        $trimmed = trim($token);
        // Se já vier com "Bearer ", apenas retorna
        if (stripos($trimmed, 'bearer ') === 0) {
            return 'Authorization: ' . $trimmed;
        }
        // Caso contrário, envia token cru (padrão da documentação)
        return 'Authorization: ' . $trimmed;
    }

    /**
     * Normalizar número de telefone para formato internacional
     * Formato esperado pela Api4Com: +5548999999999
     */
    private static function normalizePhoneNumber(string $phone): string
    {
        $original = $phone;
        
        // Remover caracteres especiais exceto +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Se já começa com +, manter
        if (strpos($phone, '+') === 0) {
            Logger::api4com("normalizePhoneNumber - Original: {$original} → Normalizado: {$phone} (já tinha +)");
            return $phone;
        }
        
        // Remover qualquer + restante
        $phone = str_replace('+', '', $phone);
        
        // Se tem 10-11 dígitos, provavelmente é BR sem código do país
        // Adicionar +55 (Brasil)
        if (strlen($phone) >= 10 && strlen($phone) <= 11) {
            $phone = '+55' . $phone;
        } elseif (strlen($phone) > 11) {
            // Se já tem código do país, apenas adicionar +
            $phone = '+' . $phone;
        }
        
        Logger::api4com("normalizePhoneNumber - Original: {$original} → Normalizado: {$phone}");
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
     * Testar conexão com a API Api4Com
     * Valida o token usando o endpoint /api/v1/users/me
     * 
     * @param int $accountId ID da conta
     * @return array Resultado do teste
     */
    public static function testConnection(int $accountId): array
    {
        $account = Api4ComAccount::find($accountId);
        if (!$account) {
            throw new \InvalidArgumentException('Conta Api4Com não encontrada');
        }

        $apiUrl = rtrim($account['api_url'], '/');
        $token = $account['api_token'];
        $authHeader = self::formatAuthHeader($token);
        
        // Endpoint: GET /api/v1/users/me (conforme documentação de autenticação)
        $url = $apiUrl . '/api/v1/users/me';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                $authHeader
            ],
            CURLOPT_TIMEOUT => 15
        ]);

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000); // em ms
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => 'Erro de conexão: ' . $error,
                'response_time' => $responseTime
            ];
        }

        $data = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && is_array($data)) {
            return [
                'success' => true,
                'message' => 'Conexão estabelecida com sucesso!',
                'user' => [
                    'name' => $data['name'] ?? 'N/A',
                    'email' => $data['email'] ?? 'N/A',
                    'role' => $data['role'] ?? 'N/A'
                ],
                'response_time' => $responseTime,
                'http_code' => $httpCode
            ];
        } elseif ($httpCode === 401) {
            return [
                'success' => false,
                'message' => 'Token inválido ou expirado. Gere um novo token no painel Api4Com.',
                'response_time' => $responseTime,
                'http_code' => $httpCode
            ];
        } else {
            $errorMsg = 'Erro na API';
            if (is_array($data)) {
                $errorMsg = $data['error'] ?? $data['message'] ?? "HTTP {$httpCode}";
                if (is_array($errorMsg)) {
                    $errorMsg = json_encode($errorMsg);
                }
            }
            return [
                'success' => false,
                'message' => $errorMsg,
                'response_time' => $responseTime,
                'http_code' => $httpCode
            ];
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
        $authHeader = self::formatAuthHeader($token);
        
        // Endpoint: GET /api/v1/extensions (conforme documentação Api4Com)
        $url = $apiUrl . '/api/v1/extensions';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                $authHeader
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);

        if ($error) {
            Logger::api4com("fetchExtensionsFromApi - Erro: " . $error, 'ERROR');
            throw new \Exception('Erro de conexão: ' . $error);
        }

        $data = json_decode($response, true);

        Logger::api4com("fetchExtensionsFromApi - HTTP {$httpCode}, Response: " . substr($response, 0, 500));
        if ($httpCode >= 500) {
            Logger::api4com("fetchExtensionsFromApi - HTTP {$httpCode}, URL: {$url}, Info: " . json_encode($curlInfo), 'ERROR');
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            // Normalizar resposta (pode ser array direto ou dentro de data/extensions)
            $extensions = $data['data'] ?? $data['extensions'] ?? $data ?? [];
            
            // Se não é array indexado, pode ser que a resposta veio em outro formato
            if (!is_array($extensions) || (is_array($extensions) && !isset($extensions[0]) && !empty($extensions))) {
                // Pode ser um objeto único ou formato diferente
                Logger::api4com("fetchExtensionsFromApi - Formato diferente, tentando normalizar");
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
            
            Logger::api4com("fetchExtensionsFromApi - Encontrados " . count($extensions) . " ramais");
            
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
            
            Logger::api4com("fetchExtensionsFromApi - Erro API: " . $errorMsg, 'ERROR');
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
                // Normalizar dados do ramal conforme documentação Api4Com:
                // { "id": 123, "ramal": "1001", "first_name": "Silvio", "last_name": "Fernandes", "email_address": "...", "bina": "..." }
                $extensionId = $ext['id'] ?? $ext['extension_id'] ?? null;
                $extensionNumber = $ext['ramal'] ?? $ext['number'] ?? $ext['extension'] ?? $ext['extension_number'] ?? null;
                
                // Nome: first_name + last_name ou fallback
                $firstName = $ext['first_name'] ?? '';
                $lastName = $ext['last_name'] ?? '';
                $name = trim("{$firstName} {$lastName}");
                if (empty($name)) {
                    $name = $ext['name'] ?? $ext['description'] ?? "Ramal {$extensionNumber}";
                }
                
                if (!$extensionId && !$extensionNumber) {
                    continue;
                }
                
                // Verificar se já existe
                $existing = Api4ComExtension::findByExtensionId($extensionId, $accountId);
                
                $data = [
                    'api4com_account_id' => $accountId,
                    'extension_id' => $extensionId,
                    'extension_number' => $extensionNumber,
                    'sip_username' => $ext['email_address'] ?? $ext['sip_username'] ?? $ext['username'] ?? null,
                    'status' => 'active', // API não retorna status, assumir ativo
                    'metadata' => json_encode([
                        'name' => $name,
                        'bina' => $ext['bina'] ?? null,
                        'domain' => $ext['domain'] ?? null,
                        'gravar_audio' => $ext['gravar_audio'] ?? 0,
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

