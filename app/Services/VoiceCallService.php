<?php
/**
 * Service VoiceCallService
 * Integração com WavoIP API para chamadas de voz via WhatsApp
 */

namespace App\Services;

use App\Models\VoiceCall;
use App\Models\IntegrationAccount;
use App\Models\Contact;
use App\Models\Conversation;
use App\Helpers\Validator;
use App\Helpers\Logger;

class VoiceCallService
{
    /**
     * URL base da API WavoIP
     */
    private static string $wavoipApiUrl = 'https://api.wavoip.com';

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
            'whatsapp_account_id' => 'required|integer',
            'contact_id' => 'required|integer',
            'to_number' => 'required|string|max:50',
            'agent_id' => 'nullable|integer',
            'conversation_id' => 'nullable|integer'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Verificar conta WhatsApp (integration_accounts unificado)
        $account = IntegrationAccount::find($data['whatsapp_account_id']);
        if (!$account) {
            throw new \InvalidArgumentException('Conta WhatsApp não encontrada');
        }

        // Verificar se WavoIP está habilitado
        if (empty($account['wavoip_enabled']) || empty($account['wavoip_token'])) {
            throw new \InvalidArgumentException('WavoIP não está configurado para esta conta');
        }

        // Verificar contato
        $contact = Contact::find($data['contact_id']);
        if (!$contact) {
            throw new \InvalidArgumentException('Contato não encontrado');
        }

        // Normalizar número de destino
        $toNumber = self::normalizePhoneNumber($data['to_number']);
        $fromNumber = self::normalizePhoneNumber($account['phone_number']);

        // Criar registro da chamada no banco
        $callData = [
            'whatsapp_account_id' => $account['id'],
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

        $callId = VoiceCall::create($callData);
        $call = VoiceCall::find($callId);

        // Iniciar chamada via WavoIP API
        try {
            $wavoipResponse = self::initiateWavoipCall($account, $fromNumber, $toNumber);
            
            if ($wavoipResponse['success']) {
                // Atualizar com ID do WavoIP
                VoiceCall::update($callId, [
                    'wavoip_call_id' => $wavoipResponse['call_id'] ?? null,
                    'status' => 'ringing',
                    'started_at' => date('Y-m-d H:i:s'),
                    'metadata' => json_encode(array_merge(
                        json_decode($call['metadata'] ?? '{}', true),
                        ['wavoip_response' => $wavoipResponse]
                    ))
                ]);

                Logger::info("VoiceCallService::createCall - Chamada iniciada: Call ID {$callId}, WavoIP ID: " . ($wavoipResponse['call_id'] ?? 'N/A'));

                return VoiceCall::find($callId);
            } else {
                // Falha na API WavoIP
                VoiceCall::updateStatus($callId, 'failed', [
                    'error_message' => $wavoipResponse['error'] ?? 'Erro desconhecido ao iniciar chamada'
                ]);
                throw new \Exception('Erro ao iniciar chamada: ' . ($wavoipResponse['error'] ?? 'Erro desconhecido'));
            }
        } catch (\Exception $e) {
            Logger::error("VoiceCallService::createCall - Erro: " . $e->getMessage());
            VoiceCall::updateStatus($callId, 'failed', [
                'error_message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Iniciar chamada via API WavoIP
     * 
     * @param array $account Conta WhatsApp
     * @param string $fromNumber Número de origem
     * @param string $toNumber Número de destino
     * @return array Resposta da API
     */
    private static function initiateWavoipCall(array $account, string $fromNumber, string $toNumber): array
    {
        $token = $account['wavoip_token'];
        
        // Usar biblioteca wavoip-api via Node.js ou fazer requisição HTTP direta
        // Por enquanto, vamos fazer requisição HTTP direta
        
        $url = self::$wavoipApiUrl . '/api/call/start';
        
        $payload = [
            'whatsappid' => $toNumber,
            'from' => $fromNumber
        ];

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

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'call_id' => $data['call_id'] ?? $data['id'] ?? null,
                'data' => $data
            ];
        } else {
            return [
                'success' => false,
                'error' => $data['error'] ?? $data['message'] ?? "HTTP {$httpCode}: {$response}"
            ];
        }
    }

    /**
     * Processar webhook do WavoIP (atualizar status da chamada)
     * 
     * @param array $webhookData Dados do webhook
     * @return bool Sucesso
     */
    public static function processWebhook(array $webhookData): bool
    {
        Logger::info("VoiceCallService::processWebhook - Webhook recebido: " . json_encode($webhookData));

        // Identificar chamada por wavoip_call_id ou por número/contato
        $call = null;
        
        if (!empty($webhookData['call_id'])) {
            $call = VoiceCall::findByWavoipId($webhookData['call_id']);
        }

        if (!$call && !empty($webhookData['whatsappid'])) {
            // Tentar encontrar por número de destino
            $toNumber = self::normalizePhoneNumber($webhookData['whatsappid']);
            $calls = VoiceCall::where('to_number', '=', $toNumber);
            if (!empty($calls)) {
                // Pegar a mais recente não finalizada
                usort($calls, function($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
                $call = $calls[0];
            }
        }

        if (!$call) {
            Logger::warning("VoiceCallService::processWebhook - Chamada não encontrada para webhook");
            return false;
        }

        // Mapear status do WavoIP para nosso sistema
        $status = self::mapWavoipStatus($webhookData['status'] ?? $webhookData['event'] ?? 'unknown');
        
        $updateData = [
            'status' => $status
        ];

        // Atualizar timestamps baseado no evento
        if (isset($webhookData['answered_at']) || $status === 'answered') {
            $updateData['answered_at'] = date('Y-m-d H:i:s');
        }
        
        if (isset($webhookData['ended_at']) || in_array($status, ['ended', 'failed', 'cancelled'])) {
            $updateData['ended_at'] = date('Y-m-d H:i:s');
            if (isset($webhookData['duration'])) {
                $updateData['duration'] = (int)$webhookData['duration'];
            } elseif ($call['started_at']) {
                // Calcular duração
                $start = strtotime($call['started_at']);
                $end = time();
                $updateData['duration'] = max(0, $end - $start);
            }
        }

        if (!empty($webhookData['recording_url'])) {
            $updateData['recording_url'] = $webhookData['recording_url'];
        }

        if (!empty($webhookData['error'])) {
            $updateData['error_message'] = $webhookData['error'];
        }

        // Atualizar metadata
        $metadata = json_decode($call['metadata'] ?? '{}', true);
        $metadata['webhook_data'][] = $webhookData;
        $updateData['metadata'] = json_encode($metadata);

        VoiceCall::update($call['id'], $updateData);

        Logger::info("VoiceCallService::processWebhook - Chamada {$call['id']} atualizada para status: {$status}");

        // Notificar via WebSocket se necessário
        if ($call['conversation_id']) {
            \App\Helpers\WebSocket::notifyConversationUpdate($call['conversation_id'], [
                'type' => 'voice_call_updated',
                'call_id' => $call['id'],
                'status' => $status
            ]);
        }

        return true;
    }

    /**
     * Mapear status do WavoIP para nosso sistema
     */
    private static function mapWavoipStatus(string $wavoipStatus): string
    {
        $statusMap = [
            'initiated' => 'initiated',
            'ringing' => 'ringing',
            'answered' => 'answered',
            'connected' => 'answered',
            'ended' => 'ended',
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            'busy' => 'failed',
            'no-answer' => 'failed'
        ];

        return $statusMap[strtolower($wavoipStatus)] ?? 'initiated';
    }

    /**
     * Encerrar chamada
     */
    public static function endCall(int $callId): bool
    {
        $call = VoiceCall::find($callId);
        if (!$call) {
            throw new \InvalidArgumentException('Chamada não encontrada');
        }

        if (in_array($call['status'], ['ended', 'failed', 'cancelled'])) {
            return true; // Já finalizada
        }

        $account = IntegrationAccount::find($call['whatsapp_account_id']);
        if (!$account || empty($account['wavoip_token'])) {
            throw new \InvalidArgumentException('Conta WhatsApp não encontrada ou WavoIP não configurado');
        }

        // Encerrar via API WavoIP se tiver call_id
        if (!empty($call['wavoip_call_id'])) {
            try {
                self::endWavoipCall($account, $call['wavoip_call_id']);
            } catch (\Exception $e) {
                Logger::error("VoiceCallService::endCall - Erro ao encerrar via API: " . $e->getMessage());
            }
        }

        // Atualizar status
        VoiceCall::updateStatus($callId, 'ended', [
            'ended_at' => date('Y-m-d H:i:s'),
            'duration' => $call['started_at'] ? (time() - strtotime($call['started_at'])) : 0
        ]);

        return true;
    }

    /**
     * Encerrar chamada via API WavoIP
     */
    private static function endWavoipCall(array $account, string $wavoipCallId): void
    {
        $url = self::$wavoipApiUrl . '/api/call/end';
        
        $payload = [
            'call_id' => $wavoipCallId
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $account['wavoip_token']
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
                FROM voice_calls
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

