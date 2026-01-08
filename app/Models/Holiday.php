<?php
/**
 * Model Holiday
 * Feriados (fixos e recorrentes)
 */

namespace App\Models;

use App\Helpers\Database;

class Holiday extends Model
{
    protected string $table = 'holidays';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name', 'date', 'is_recurring'
    ];
    protected bool $timestamps = true;
    
    /**
     * Obter todos os feriados
     */
    public static function getAll(): array
    {
        $sql = "SELECT * FROM holidays ORDER BY date ASC";
        return Database::fetchAll($sql);
    }
    
    /**
     * Obter feriados do ano atual
     */
    public static function getCurrentYearHolidays(): array
    {
        $year = date('Y');
        $sql = "SELECT * FROM holidays 
                WHERE YEAR(date) = ? 
                OR is_recurring = 1
                ORDER BY MONTH(date), DAY(date)";
        
        return Database::fetchAll($sql, [$year]);
    }
    
    /**
     * Obter próximos feriados
     */
    public static function getUpcomingHolidays(int $limit = 5): array
    {
        $today = date('Y-m-d');
        $year = date('Y');
        
        $sql = "SELECT * FROM holidays 
                WHERE date >= ? 
                OR (is_recurring = 1 AND CONCAT(?, SUBSTRING(date, 5)) >= ?)
                ORDER BY 
                    CASE 
                        WHEN is_recurring = 1 THEN CONCAT(?, SUBSTRING(date, 5))
                        ELSE date 
                    END ASC
                LIMIT ?";
        
        return Database::fetchAll($sql, [$today, $year, $today, $year, $limit]);
    }
    
    /**
     * Verificar se uma data é feriado
     */
    public static function isHoliday(string $date): bool
    {
        // Verificar feriado exato
        $sql = "SELECT COUNT(*) as count FROM holidays WHERE date = ?";
        $result = Database::fetch($sql, [$date]);
        
        if ($result && $result['count'] > 0) {
            return true;
        }
        
        // Verificar feriado recorrente (apenas mês e dia)
        $monthDay = substr($date, 5); // MM-DD
        $sql = "SELECT COUNT(*) as count FROM holidays 
                WHERE is_recurring = 1 
                AND SUBSTRING(date, 6) = ?";
        $result = Database::fetch($sql, [$monthDay]);
        
        return $result && $result['count'] > 0;
    }
    
    /**
     * Adicionar feriados recorrentes para um ano específico
     * Útil para popular feriados automaticamente
     */
    public static function populateRecurringForYear(int $year): int
    {
        $sql = "SELECT * FROM holidays WHERE is_recurring = 1";
        $recurring = Database::fetchAll($sql);
        
        $added = 0;
        foreach ($recurring as $holiday) {
            $monthDay = substr($holiday['date'], 5); // MM-DD
            $newDate = "$year-$monthDay";
            
            // Verificar se já existe
            $exists = Database::fetch("SELECT COUNT(*) as count FROM holidays WHERE date = ?", [$newDate]);
            
            if (!$exists || $exists['count'] == 0) {
                self::create([
                    'name' => $holiday['name'] . " $year",
                    'date' => $newDate,
                    'is_recurring' => 0
                ]);
                $added++;
            }
        }
        
        return $added;
    }
}
