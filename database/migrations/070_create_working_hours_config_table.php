<?php
/**
 * Migration: Criar tabela de configuração de horários de trabalho
 * Suporta horários diferentes por dia da semana e feriados
 */

function up_create_working_hours_config() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    echo "🔧 Criando tabela 'working_hours_config'...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS working_hours_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        day_of_week TINYINT NOT NULL COMMENT '0=Domingo, 1=Segunda, ..., 6=Sábado',
        is_working_day TINYINT(1) DEFAULT 1 COMMENT 'Se é dia útil',
        start_time TIME DEFAULT '08:00:00' COMMENT 'Horário de início',
        end_time TIME DEFAULT '18:00:00' COMMENT 'Horário de fim',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_day (day_of_week)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "   ✅ Tabela 'working_hours_config' criada\n";
    
    echo "\n🔧 Criando tabela 'holidays'...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS holidays (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Nome do feriado',
        date DATE NOT NULL COMMENT 'Data do feriado',
        is_recurring TINYINT(1) DEFAULT 0 COMMENT 'Se repete todo ano (ex: Natal)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_date (date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "   ✅ Tabela 'holidays' criada\n";
    
    // Inserir configuração padrão (Segunda a Sexta, 08:00-18:00)
    echo "\n📝 Inserindo configuração padrão...\n";
    
    $days = [
        ['day' => 0, 'name' => 'Domingo', 'working' => 0],
        ['day' => 1, 'name' => 'Segunda-feira', 'working' => 1],
        ['day' => 2, 'name' => 'Terça-feira', 'working' => 1],
        ['day' => 3, 'name' => 'Quarta-feira', 'working' => 1],
        ['day' => 4, 'name' => 'Quinta-feira', 'working' => 1],
        ['day' => 5, 'name' => 'Sexta-feira', 'working' => 1],
        ['day' => 6, 'name' => 'Sábado', 'working' => 0]
    ];
    
    foreach ($days as $day) {
        $checkSql = "SELECT COUNT(*) as count FROM working_hours_config WHERE day_of_week = " . $day['day'];
        $result = $db->query($checkSql)->fetch(\PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            $insertSql = "INSERT INTO working_hours_config (day_of_week, is_working_day, start_time, end_time) 
                         VALUES ({$day['day']}, {$day['working']}, '08:00:00', '18:00:00')";
            $db->exec($insertSql);
            echo "   ✅ {$day['name']}: " . ($day['working'] ? '08:00-18:00' : 'Não útil') . "\n";
        }
    }
    
    // Inserir alguns feriados brasileiros padrão
    echo "\n🎉 Inserindo feriados brasileiros padrão...\n";
    
    $currentYear = date('Y');
    $holidays = [
        ['name' => 'Ano Novo', 'date' => "$currentYear-01-01", 'recurring' => 1],
        ['name' => 'Tiradentes', 'date' => "$currentYear-04-21", 'recurring' => 1],
        ['name' => 'Dia do Trabalho', 'date' => "$currentYear-05-01", 'recurring' => 1],
        ['name' => 'Independência do Brasil', 'date' => "$currentYear-09-07", 'recurring' => 1],
        ['name' => 'Nossa Senhora Aparecida', 'date' => "$currentYear-10-12", 'recurring' => 1],
        ['name' => 'Finados', 'date' => "$currentYear-11-02", 'recurring' => 1],
        ['name' => 'Proclamação da República', 'date' => "$currentYear-11-15", 'recurring' => 1],
        ['name' => 'Natal', 'date' => "$currentYear-12-25", 'recurring' => 1]
    ];
    
    foreach ($holidays as $holiday) {
        $checkSql = "SELECT COUNT(*) as count FROM holidays WHERE date = '{$holiday['date']}'";
        $result = $db->query($checkSql)->fetch(\PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            $insertSql = "INSERT INTO holidays (name, date, is_recurring) 
                         VALUES ('{$holiday['name']}', '{$holiday['date']}', {$holiday['recurring']})";
            $db->exec($insertSql);
            echo "   ✅ {$holiday['name']} - {$holiday['date']}\n";
        }
    }
    
    echo "\n✅ Migration concluída com sucesso!\n";
}

function down_create_working_hours_config() {
    $db = \App\Helpers\Database::getInstance();
    
    echo "🔧 Removendo tabelas...\n";
    
    $db->exec("DROP TABLE IF EXISTS holidays");
    echo "   ✅ Tabela 'holidays' removida\n";
    
    $db->exec("DROP TABLE IF EXISTS working_hours_config");
    echo "   ✅ Tabela 'working_hours_config' removida\n";
    
    echo "\n✅ Rollback concluído!\n";
}
