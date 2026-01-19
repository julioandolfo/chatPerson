<?php
/**
 * Model ExternalDataSource
 * Fontes de dados externas para sincronização de contatos
 */

namespace App\Models;

use App\Helpers\Database;

class ExternalDataSource extends Model
{
    protected string $table = 'external_data_sources';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name', 'type', 'connection_config', 'table_name', 'column_mapping',
        'query_config', 'sync_frequency', 'last_sync_at', 'last_sync_status',
        'last_sync_message', 'total_records', 'status', 'created_by'
    ];
    protected bool $timestamps = true;

    /**
     * Buscar fontes ativas
     */
    public static function getActive(): array
    {
        return self::where('status', '=', 'active');
    }

    /**
     * Buscar fontes por tipo
     */
    public static function getByType(string $type): array
    {
        return self::where('type', '=', $type);
    }

    /**
     * Buscar fontes que precisam sincronizar
     */
    public static function getNeedingSync(): array
    {
        $sql = "SELECT * FROM external_data_sources 
                WHERE status = 'active' 
                AND sync_frequency != 'manual'
                AND (
                    last_sync_at IS NULL OR
                    (sync_frequency = 'hourly' AND last_sync_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR)) OR
                    (sync_frequency = 'daily' AND last_sync_at <= DATE_SUB(NOW(), INTERVAL 1 DAY)) OR
                    (sync_frequency = 'weekly' AND last_sync_at <= DATE_SUB(NOW(), INTERVAL 1 WEEK))
                )
                ORDER BY last_sync_at ASC NULLS FIRST";
        
        return Database::fetchAll($sql, []);
    }

    /**
     * Atualizar status de sincronização
     */
    public static function updateSyncStatus(int $sourceId, string $status, ?string $message = null, ?int $totalRecords = null): bool
    {
        $data = [
            'last_sync_at' => date('Y-m-d H:i:s'),
            'last_sync_status' => $status,
            'last_sync_message' => $message
        ];
        
        if ($totalRecords !== null) {
            $data['total_records'] = $totalRecords;
        }
        
        return self::update($sourceId, $data);
    }

    /**
     * Registrar log de sincronização
     */
    public static function logSync(int $sourceId, array $logData): int
    {
        $fields = ['source_id', 'started_at', 'completed_at', 'status', 'records_fetched', 
                   'records_created', 'records_updated', 'records_failed', 'error_message', 'execution_time_ms'];
        
        $logData['source_id'] = $sourceId;
        
        $values = [];
        $placeholders = [];
        $insertFields = [];
        
        foreach ($fields as $field) {
            if (isset($logData[$field])) {
                $insertFields[] = $field;
                $values[] = $logData[$field];
                $placeholders[] = '?';
            }
        }
        
        $sql = "INSERT INTO external_data_sync_logs (" . implode(', ', $insertFields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        Database::execute($sql, $values);
        return Database::getInstance()->lastInsertId();
    }

    /**
     * Buscar últimos logs de sincronização
     */
    public static function getSyncLogs(int $sourceId, int $limit = 10): array
    {
        $sql = "SELECT * FROM external_data_sync_logs 
                WHERE source_id = ? 
                ORDER BY started_at DESC 
                LIMIT ?";
        
        return Database::fetchAll($sql, [$sourceId, $limit]);
    }
}
