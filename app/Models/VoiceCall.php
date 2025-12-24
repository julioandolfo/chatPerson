<?php
/**
 * Model VoiceCall
 */

namespace App\Models;

class VoiceCall extends Model
{
    protected string $table = 'voice_calls';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'conversation_id', 'whatsapp_account_id', 'contact_id', 'agent_id',
        'wavoip_call_id', 'direction', 'status', 'duration',
        'started_at', 'answered_at', 'ended_at',
        'from_number', 'to_number', 'recording_url',
        'error_message', 'metadata'
    ];
    protected bool $timestamps = true;

    /**
     * Buscar chamadas por conversa
     */
    public static function getByConversation(int $conversationId): array
    {
        return self::where('conversation_id', '=', $conversationId);
    }

    /**
     * Buscar chamadas por contato
     */
    public static function getByContact(int $contactId): array
    {
        return self::where('contact_id', '=', $contactId);
    }

    /**
     * Buscar chamadas por agente
     */
    public static function getByAgent(int $agentId): array
    {
        return self::where('agent_id', '=', $agentId);
    }

    /**
     * Buscar chamadas por conta WhatsApp
     */
    public static function getByWhatsAppAccount(int $accountId): array
    {
        return self::where('whatsapp_account_id', '=', $accountId);
    }

    /**
     * Buscar por ID do WavoIP
     */
    public static function findByWavoipId(string $wavoipCallId): ?array
    {
        return self::whereFirst('wavoip_call_id', '=', $wavoipCallId);
    }

    /**
     * Buscar chamadas ativas (não finalizadas)
     */
    public static function getActive(): array
    {
        return self::where('status', 'IN', ['initiated', 'ringing', 'answered']);
    }

    /**
     * Atualizar status da chamada
     */
    public static function updateStatus(int $callId, string $status, ?array $additionalData = null): bool
    {
        $data = ['status' => $status];
        
        // Atualizar timestamps baseado no status
        if ($status === 'answered' && !empty($additionalData['answered_at'])) {
            $data['answered_at'] = $additionalData['answered_at'];
        }
        if ($status === 'ended' && !empty($additionalData['ended_at'])) {
            $data['ended_at'] = $additionalData['ended_at'];
            if (!empty($additionalData['duration'])) {
                $data['duration'] = $additionalData['duration'];
            }
        }
        if ($status === 'ringing' && !empty($additionalData['started_at'])) {
            $data['started_at'] = $additionalData['started_at'];
        }
        
        if (!empty($additionalData['recording_url'])) {
            $data['recording_url'] = $additionalData['recording_url'];
        }
        if (!empty($additionalData['error_message'])) {
            $data['error_message'] = $additionalData['error_message'];
        }
        if (!empty($additionalData['metadata'])) {
            $data['metadata'] = json_encode($additionalData['metadata']);
        }
        
        return self::update($callId, $data);
    }
}

