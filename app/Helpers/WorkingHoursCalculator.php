<?php
/**
 * Helper WorkingHoursCalculator
 * Calcula minutos dentro de horário de trabalho considerando:
 * - Dias da semana configuráveis
 * - Horários diferentes por dia
 * - Feriados
 * - Finais de semana
 */

namespace App\Helpers;

use App\Helpers\Database;

class WorkingHoursCalculator
{
    private static ?array $config = null;
    private static ?array $holidays = null;
    
    /**
     * Calcular minutos dentro do horário de trabalho entre duas datas
     * Considera:
     * - Dias úteis configurados
     * - Horário de início e fim por dia
     * - Horário de almoço (intervalo não contado)
     * - Feriados
     */
    public static function calculateWorkingMinutes(\DateTime $start, \DateTime $end): int
    {
        if ($end <= $start) {
            return 0;
        }
        
        self::loadConfig();
        
        $totalMinutes = 0;
        $cursor = clone $start;
        
        // Processar dia por dia
        while ($cursor < $end) {
            $dayOfWeek = (int)$cursor->format('w'); // 0 = Domingo
            $dateStr = $cursor->format('Y-m-d');
            
            // Verificar se é feriado
            if (self::isHoliday($dateStr)) {
                // Pular para o próximo dia
                $cursor->modify('+1 day')->setTime(0, 0, 0);
                continue;
            }
            
            // Pegar configuração do dia
            $dayConfig = self::$config[$dayOfWeek] ?? null;
            
            // Se não é dia útil, pular
            if (!$dayConfig || !$dayConfig['is_working_day']) {
                $cursor->modify('+1 day')->setTime(0, 0, 0);
                continue;
            }
            
            // Horários do dia
            $dayStart = clone $cursor;
            $dayStart->setTime(
                (int)substr($dayConfig['start_time'], 0, 2),
                (int)substr($dayConfig['start_time'], 3, 2),
                0
            );
            
            $dayEnd = clone $cursor;
            $dayEnd->setTime(
                (int)substr($dayConfig['end_time'], 0, 2),
                (int)substr($dayConfig['end_time'], 3, 2),
                0
            );
            
            // Calcular janela de tempo para este dia
            $windowStart = max($cursor, $dayStart);
            $windowEnd = min($end, $dayEnd);
            
            if ($windowEnd > $windowStart) {
                $minutes = ($windowEnd->getTimestamp() - $windowStart->getTimestamp()) / 60;
                
                // Descontar horário de almoço se habilitado
                if (!empty($dayConfig['lunch_enabled']) && 
                    !empty($dayConfig['lunch_start']) && 
                    !empty($dayConfig['lunch_end'])) {
                    
                    $lunchStart = clone $cursor;
                    $lunchStart->setTime(
                        (int)substr($dayConfig['lunch_start'], 0, 2),
                        (int)substr($dayConfig['lunch_start'], 3, 2),
                        0
                    );
                    
                    $lunchEnd = clone $cursor;
                    $lunchEnd->setTime(
                        (int)substr($dayConfig['lunch_end'], 0, 2),
                        (int)substr($dayConfig['lunch_end'], 3, 2),
                        0
                    );
                    
                    // Calcular sobreposição com o período de almoço
                    $lunchOverlapStart = max($windowStart, $lunchStart);
                    $lunchOverlapEnd = min($windowEnd, $lunchEnd);
                    
                    if ($lunchOverlapEnd > $lunchOverlapStart) {
                        $lunchMinutes = ($lunchOverlapEnd->getTimestamp() - $lunchOverlapStart->getTimestamp()) / 60;
                        $minutes -= $lunchMinutes;
                    }
                }
                
                $totalMinutes += max(0, (int)$minutes);
            }
            
            // Avançar para o próximo dia
            $cursor->modify('+1 day')->setTime(0, 0, 0);
        }
        
        return $totalMinutes;
    }
    
    /**
     * Verificar se uma data é feriado
     */
    private static function isHoliday(string $date): bool
    {
        if (self::$holidays === null) {
            self::loadHolidays();
        }
        
        // Verificar feriado exato
        if (in_array($date, self::$holidays['exact'])) {
            return true;
        }
        
        // Verificar feriado recorrente (apenas mês e dia)
        $monthDay = substr($date, 5); // MM-DD
        if (in_array($monthDay, self::$holidays['recurring'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Carregar configuração de horários
     */
    private static function loadConfig(): void
    {
        if (self::$config !== null) {
            return;
        }
        
        try {
            $db = Database::getInstance();
            
            // Verificar se tabela existe
            $tables = $db->query("SHOW TABLES LIKE 'working_hours_config'")->fetchAll();
            if (empty($tables)) {
                // Tabela não existe, usar configuração padrão
                self::loadDefaultConfig();
                return;
            }
            
            // Verificar se colunas de almoço existem
            $columns = $db->query("SHOW COLUMNS FROM working_hours_config LIKE 'lunch_enabled'")->fetchAll();
            $hasLunchColumns = !empty($columns);
            
            if ($hasLunchColumns) {
                $sql = "SELECT day_of_week, is_working_day, start_time, end_time, 
                               lunch_enabled, lunch_start, lunch_end 
                        FROM working_hours_config 
                        ORDER BY day_of_week";
            } else {
                $sql = "SELECT day_of_week, is_working_day, start_time, end_time 
                        FROM working_hours_config 
                        ORDER BY day_of_week";
            }
            
            $rows = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($rows)) {
                self::loadDefaultConfig();
                return;
            }
            
            self::$config = [];
            foreach ($rows as $row) {
                self::$config[(int)$row['day_of_week']] = [
                    'is_working_day' => (bool)$row['is_working_day'],
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                    'lunch_enabled' => (bool)($row['lunch_enabled'] ?? false),
                    'lunch_start' => $row['lunch_start'] ?? '12:00:00',
                    'lunch_end' => $row['lunch_end'] ?? '13:00:00'
                ];
            }
        } catch (\Exception $e) {
            error_log("Erro ao carregar configuração de horários: " . $e->getMessage());
            self::loadDefaultConfig();
        }
    }
    
    /**
     * Carregar configuração padrão
     */
    private static function loadDefaultConfig(): void
    {
        self::$config = [
            0 => ['is_working_day' => false, 'start_time' => '08:00:00', 'end_time' => '18:00:00', 'lunch_enabled' => false, 'lunch_start' => '12:00:00', 'lunch_end' => '13:00:00'], // Domingo
            1 => ['is_working_day' => true, 'start_time' => '08:00:00', 'end_time' => '18:00:00', 'lunch_enabled' => true, 'lunch_start' => '12:00:00', 'lunch_end' => '13:00:00'],  // Segunda
            2 => ['is_working_day' => true, 'start_time' => '08:00:00', 'end_time' => '18:00:00', 'lunch_enabled' => true, 'lunch_start' => '12:00:00', 'lunch_end' => '13:00:00'],  // Terça
            3 => ['is_working_day' => true, 'start_time' => '08:00:00', 'end_time' => '18:00:00', 'lunch_enabled' => true, 'lunch_start' => '12:00:00', 'lunch_end' => '13:00:00'],  // Quarta
            4 => ['is_working_day' => true, 'start_time' => '08:00:00', 'end_time' => '18:00:00', 'lunch_enabled' => true, 'lunch_start' => '12:00:00', 'lunch_end' => '13:00:00'],  // Quinta
            5 => ['is_working_day' => true, 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'lunch_enabled' => true, 'lunch_start' => '12:00:00', 'lunch_end' => '13:00:00'],  // Sexta (termina mais cedo)
            6 => ['is_working_day' => false, 'start_time' => '08:00:00', 'end_time' => '12:00:00', 'lunch_enabled' => false, 'lunch_start' => '12:00:00', 'lunch_end' => '13:00:00'], // Sábado
        ];
    }
    
    /**
     * Carregar feriados
     */
    private static function loadHolidays(): void
    {
        self::$holidays = [
            'exact' => [],
            'recurring' => []
        ];
        
        try {
            $db = Database::getInstance();
            
            // Verificar se tabela existe
            $tables = $db->query("SHOW TABLES LIKE 'holidays'")->fetchAll();
            if (empty($tables)) {
                return;
            }
            
            $sql = "SELECT date, is_recurring FROM holidays";
            $rows = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($rows as $row) {
                if ($row['is_recurring']) {
                    // Armazenar apenas MM-DD para feriados recorrentes
                    self::$holidays['recurring'][] = substr($row['date'], 5);
                } else {
                    // Armazenar data completa
                    self::$holidays['exact'][] = $row['date'];
                }
            }
        } catch (\Exception $e) {
            error_log("Erro ao carregar feriados: " . $e->getMessage());
        }
    }
    
    /**
     * Limpar cache (útil após alterar configurações)
     */
    public static function clearCache(): void
    {
        self::$config = null;
        self::$holidays = null;
    }
    
    /**
     * Verificar se horário de trabalho está habilitado
     */
    public static function isEnabled(): bool
    {
        $settings = \App\Services\ConversationSettingsService::getSettings();
        return $settings['sla']['working_hours_enabled'] ?? false;
    }
    
    /**
     * Calcular minutos considerando configuração (se habilitado, usa working hours; se não, usa tempo corrido)
     */
    public static function calculateMinutes(\DateTime $start, \DateTime $end): int
    {
        if (!self::isEnabled()) {
            // Working hours desabilitado, usar tempo corrido
            $diff = $end->getTimestamp() - $start->getTimestamp();
            return (int)($diff / 60);
        }
        
        // Working hours habilitado, calcular apenas minutos úteis
        return self::calculateWorkingMinutes($start, $end);
    }
    
    /**
     * Obter próximo horário de trabalho (útil para calcular quando SLA vai vencer)
     */
    public static function getNextWorkingTime(\DateTime $from): \DateTime
    {
        self::loadConfig();
        
        $cursor = clone $from;
        $maxIterations = 365; // Evitar loop infinito
        $iterations = 0;
        
        while ($iterations < $maxIterations) {
            $dayOfWeek = (int)$cursor->format('w');
            $dateStr = $cursor->format('Y-m-d');
            
            // Verificar se é feriado
            if (self::isHoliday($dateStr)) {
                $cursor->modify('+1 day')->setTime(0, 0, 0);
                $iterations++;
                continue;
            }
            
            // Pegar configuração do dia
            $dayConfig = self::$config[$dayOfWeek] ?? null;
            
            // Se não é dia útil, avançar
            if (!$dayConfig || !$dayConfig['is_working_day']) {
                $cursor->modify('+1 day')->setTime(0, 0, 0);
                $iterations++;
                continue;
            }
            
            // Horário de início do dia
            $dayStart = clone $cursor;
            $dayStart->setTime(
                (int)substr($dayConfig['start_time'], 0, 2),
                (int)substr($dayConfig['start_time'], 3, 2),
                0
            );
            
            // Se ainda estamos antes do início, retornar o início
            if ($cursor < $dayStart) {
                return $dayStart;
            }
            
            // Horário de fim do dia
            $dayEnd = clone $cursor;
            $dayEnd->setTime(
                (int)substr($dayConfig['end_time'], 0, 2),
                (int)substr($dayConfig['end_time'], 3, 2),
                0
            );
            
            // Se estamos dentro do horário, retornar agora
            if ($cursor >= $dayStart && $cursor < $dayEnd) {
                return $cursor;
            }
            
            // Passou do horário, ir para o próximo dia
            $cursor->modify('+1 day')->setTime(0, 0, 0);
            $iterations++;
        }
        
        // Fallback: retornar o que foi passado
        return $from;
    }
}
