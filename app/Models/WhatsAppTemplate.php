<?php

namespace App\Models;

use App\Helpers\Database;

/**
 * Model WhatsAppTemplate
 * 
 * Gerencia templates de mensagem do WhatsApp Cloud API
 */
class WhatsAppTemplate extends Model
{
    protected string $table = 'whatsapp_templates';
    protected string $primaryKey = 'id';
    protected bool $timestamps = true;
    
    protected array $fillable = [
        'waba_id',
        'whatsapp_phone_id',
        'template_id',
        'name',
        'display_name',
        'language',
        'category',
        'status',
        'quality_score',
        'header_type',
        'header_text',
        'header_media_url',
        'body_text',
        'footer_text',
        'buttons',
        'components',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
        'last_sent_at',
        'rejection_reason',
        'last_synced_at',
    ];
    
    /**
     * Buscar templates por WABA ID
     */
    public static function getByWabaId(string $wabaId): array
    {
        $sql = "SELECT * FROM whatsapp_templates WHERE waba_id = ? ORDER BY name ASC";
        return Database::fetchAll($sql, [$wabaId]);
    }
    
    /**
     * Buscar templates aprovados por WABA ID
     */
    public static function getApproved(string $wabaId): array
    {
        $sql = "SELECT * FROM whatsapp_templates WHERE waba_id = ? AND status = 'APPROVED' ORDER BY name ASC";
        return Database::fetchAll($sql, [$wabaId]);
    }
    
    /**
     * Buscar por nome e idioma
     */
    public static function findByNameAndLanguage(string $wabaId, string $name, string $language): ?array
    {
        $sql = "SELECT * FROM whatsapp_templates WHERE waba_id = ? AND name = ? AND language = ? LIMIT 1";
        return Database::fetch($sql, [$wabaId, $name, $language]);
    }
    
    /**
     * Buscar por template_id da Meta
     */
    public static function findByTemplateId(string $templateId): ?array
    {
        return self::whereFirst('template_id', '=', $templateId);
    }
    
    /**
     * Buscar templates pendentes de aprovação
     */
    public static function getPending(string $wabaId): array
    {
        $sql = "SELECT * FROM whatsapp_templates WHERE waba_id = ? AND status = 'PENDING' ORDER BY created_at DESC";
        return Database::fetchAll($sql, [$wabaId]);
    }
    
    /**
     * Buscar templates rejeitados
     */
    public static function getRejected(string $wabaId): array
    {
        $sql = "SELECT * FROM whatsapp_templates WHERE waba_id = ? AND status = 'REJECTED' ORDER BY updated_at DESC";
        return Database::fetchAll($sql, [$wabaId]);
    }
    
    /**
     * Buscar drafts (rascunhos locais)
     */
    public static function getDrafts(string $wabaId): array
    {
        $sql = "SELECT * FROM whatsapp_templates WHERE waba_id = ? AND status = 'DRAFT' ORDER BY created_at DESC";
        return Database::fetchAll($sql, [$wabaId]);
    }
    
    /**
     * Buscar por categoria
     */
    public static function getByCategory(string $wabaId, string $category): array
    {
        $sql = "SELECT * FROM whatsapp_templates WHERE waba_id = ? AND category = ? ORDER BY name ASC";
        return Database::fetchAll($sql, [$wabaId, $category]);
    }
    
    /**
     * Incrementar contagem de envio
     */
    public static function incrementSent(int $id): bool
    {
        $sql = "UPDATE whatsapp_templates SET sent_count = sent_count + 1, last_sent_at = NOW() WHERE id = ?";
        return Database::getInstance()->prepare($sql)->execute([$id]);
    }
    
    /**
     * Incrementar contagem de entrega
     */
    public static function incrementDelivered(int $id): bool
    {
        $sql = "UPDATE whatsapp_templates SET delivered_count = delivered_count + 1 WHERE id = ?";
        return Database::getInstance()->prepare($sql)->execute([$id]);
    }
    
    /**
     * Incrementar contagem de leitura
     */
    public static function incrementRead(int $id): bool
    {
        $sql = "UPDATE whatsapp_templates SET read_count = read_count + 1 WHERE id = ?";
        return Database::getInstance()->prepare($sql)->execute([$id]);
    }
    
    /**
     * Incrementar contagem de falha
     */
    public static function incrementFailed(int $id): bool
    {
        $sql = "UPDATE whatsapp_templates SET failed_count = failed_count + 1 WHERE id = ?";
        return Database::getInstance()->prepare($sql)->execute([$id]);
    }
    
    /**
     * Decodificar botões JSON
     */
    public static function getButtons(array $template): array
    {
        if (empty($template['buttons'])) {
            return [];
        }
        if (is_string($template['buttons'])) {
            return json_decode($template['buttons'], true) ?? [];
        }
        return is_array($template['buttons']) ? $template['buttons'] : [];
    }
    
    /**
     * Decodificar componentes JSON
     */
    public static function getComponents(array $template): array
    {
        if (empty($template['components'])) {
            return [];
        }
        if (is_string($template['components'])) {
            return json_decode($template['components'], true) ?? [];
        }
        return is_array($template['components']) ? $template['components'] : [];
    }
    
    /**
     * Contar variáveis no body_text ({{1}}, {{2}}, etc.)
     */
    public static function countVariables(array $template): int
    {
        preg_match_all('/\{\{(\d+)\}\}/', $template['body_text'] ?? '', $matches);
        return count($matches[0]);
    }
    
    /**
     * Obter estatísticas resumidas por WABA
     */
    public static function getStats(string $wabaId): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'DRAFT' THEN 1 ELSE 0 END) as drafts,
                    SUM(sent_count) as total_sent,
                    SUM(delivered_count) as total_delivered,
                    SUM(read_count) as total_read
                FROM whatsapp_templates WHERE waba_id = ?";
        return Database::fetch($sql, [$wabaId]) ?: [
            'total' => 0, 'approved' => 0, 'pending' => 0, 
            'rejected' => 0, 'drafts' => 0,
            'total_sent' => 0, 'total_delivered' => 0, 'total_read' => 0,
        ];
    }
}
