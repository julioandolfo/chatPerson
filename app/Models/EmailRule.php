<?php
/**
 * Model EmailRule
 * Regras de validação/ingestão de email por conta (integration_accounts, channel=email).
 */

namespace App\Models;

class EmailRule extends Model
{
    protected string $table = 'email_ingestion_rules';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'integration_account_id', 'name', 'priority', 'match_type',
        'conditions', 'actions', 'stop_on_match', 'is_active'
    ];
    protected array $jsonFields = ['conditions', 'actions'];
    protected bool $timestamps = true;

    /**
     * Regras de uma conta, ordenadas por prioridade. Decodifica os campos JSON.
     */
    public static function getForAccount(int $accountId, bool $onlyActive = true): array
    {
        $sql = "SELECT * FROM email_ingestion_rules WHERE integration_account_id = ?";
        if ($onlyActive) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY priority ASC, id ASC";

        $rows = \App\Helpers\Database::fetchAll($sql, [$accountId]);
        foreach ($rows as &$row) {
            $row['conditions'] = self::decodeJson($row['conditions'] ?? null, []);
            $row['actions'] = self::decodeJson($row['actions'] ?? null, []);
        }
        unset($row);
        return $rows;
    }

    private static function decodeJson($value, $default)
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : $default;
        }
        return $default;
    }
}
