<?php
/**
 * Model WorkingHoursConfig
 * Configuração de horários de trabalho por dia da semana
 */

namespace App\Models;

use App\Helpers\Database;

class WorkingHoursConfig extends Model
{
    protected string $table = 'working_hours_config';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'day_of_week', 'is_working_day', 'start_time', 'end_time'
    ];
    protected bool $timestamps = true;
    
    /**
     * Obter configuração de todos os dias
     */
    public static function getAllDays(): array
    {
        $sql = "SELECT * FROM working_hours_config ORDER BY day_of_week ASC";
        return Database::fetchAll($sql);
    }
    
    /**
     * Obter configuração de um dia específico
     */
    public static function getByDayOfWeek(int $dayOfWeek): ?array
    {
        $sql = "SELECT * FROM working_hours_config WHERE day_of_week = ?";
        return Database::fetch($sql, [$dayOfWeek]);
    }
    
    /**
     * Atualizar configuração de um dia
     */
    public static function updateDay(int $dayOfWeek, array $data): bool
    {
        $existing = self::getByDayOfWeek($dayOfWeek);
        
        if ($existing) {
            return self::update($existing['id'], $data);
        } else {
            $data['day_of_week'] = $dayOfWeek;
            $id = self::create($data);
            return $id > 0;
        }
    }
    
    /**
     * Obter nome do dia em português
     */
    public static function getDayName(int $dayOfWeek): string
    {
        $names = [
            0 => 'Domingo',
            1 => 'Segunda-feira',
            2 => 'Terça-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sábado'
        ];
        
        return $names[$dayOfWeek] ?? 'Desconhecido';
    }
}
