<?php
/**
 * Script para testar o salvamento de Tiers e Condições
 * Simula o POST do formulário
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Helpers\Logger;
use App\Helpers\Database;

echo "<pre style='background:#1e1e1e;color:#d4d4d4;padding:20px;font-family:monospace;font-size:12px;'>";
echo "<div style='color:#4ec9b0;font-size:16px;border-bottom:2px solid #4ec9b0;padding-bottom:10px;margin-bottom:20px;'>";
echo "🧪 TESTE: Salvar Tiers e Condições\n";
echo "</div>";

// Simular dados de POST
$_POST = [
    'enable_bonus' => '1',
    'enable_bonus_conditions' => '1',
    'tiers' => [
        [
            'tier_name' => 'Bronze',
            'threshold_percentage' => '50',
            'bonus_amount' => '600',
            'tier_color' => 'bronze',
            'tier_order' => '0'
        ],
        [
            'tier_name' => 'Prata',
            'threshold_percentage' => '70',
            'bonus_amount' => '1000',
            'tier_color' => 'silver',
            'tier_order' => '1'
        ]
    ],
    'conditions' => [
        [
            'metric_key' => 'conversion_rate',
            'operator' => '>=',
            'threshold_value' => '15',
            'is_required' => '1',
            'bonus_modifier' => '0.5',
            'description' => 'Conversão mínima 15%',
            'check_order' => '0'
        ]
    ]
];

// Escolher uma meta existente para teste
echo "<div style='margin:15px 0;'><strong style='color:#569cd6;'>1. Buscando metas existentes...</strong>\n";
$goals = Database::fetchAll("SELECT id, name FROM goals ORDER BY id DESC LIMIT 5");
if (empty($goals)) {
    echo "   <span style='color:#f48771;'>❌ Nenhuma meta encontrada!</span>\n";
    echo "   <span style='color:#dcdcaa;'>Crie uma meta primeiro em /goals/create</span>\n";
    echo "</div></pre>";
    exit;
}

echo "   <span style='color:#4ec9b0;'>✅ " . count($goals) . " metas encontradas:</span>\n";
foreach ($goals as $goal) {
    echo "   <span style='color:#888;'>   - ID {$goal['id']}: {$goal['name']}</span>\n";
}
$testGoalId = $goals[0]['id'];
echo "   <span style='color:#dcdcaa;'>→ Usando meta ID {$testGoalId} para teste</span>\n";
echo "</div>";

// Testar função saveBonusTiers
echo "<div style='margin:15px 0;'><strong style='color:#569cd6;'>2. Testando salvamento de Tiers...</strong>\n";

Logger::info("test-save-tiers.php - Iniciando teste para meta ID {$testGoalId}", 'goals.log');

$tiers = $_POST['tiers'] ?? [];
Logger::info('test-save-tiers.php - tiers raw: ' . print_r($tiers, true), 'goals.log');
Logger::info('test-save-tiers.php - tiers is_array: ' . (is_array($tiers) ? 'YES' : 'NO'), 'goals.log');
Logger::info('test-save-tiers.php - tiers empty: ' . (empty($tiers) ? 'YES' : 'NO'), 'goals.log');
Logger::info('test-save-tiers.php - tiers count: ' . count($tiers), 'goals.log');

if (!empty($tiers)) {
    // Limpar tiers existentes
    Database::query("DELETE FROM goal_bonus_tiers WHERE goal_id = ?", [$testGoalId]);
    echo "   <span style='color:#888;'>   Tiers antigos removidos</span>\n";
    
    $saved = 0;
    foreach ($tiers as $index => $tier) {
        Logger::info("test-save-tiers.php - Tier {$index}: " . json_encode($tier), 'goals.log');
        
        $tierData = [
            'goal_id' => $testGoalId,
            'tier_name' => $tier['tier_name'] ?? '',
            'threshold_percentage' => floatval($tier['threshold_percentage'] ?? 0),
            'bonus_amount' => floatval($tier['bonus_amount'] ?? 0),
            'tier_color' => $tier['tier_color'] ?? 'blue',
            'tier_order' => intval($tier['tier_order'] ?? 0),
            'is_cumulative' => 0
        ];
        
        try {
            $sql = "INSERT INTO goal_bonus_tiers 
                    (goal_id, tier_name, threshold_percentage, bonus_amount, tier_color, tier_order, is_cumulative)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            Database::query($sql, [
                $tierData['goal_id'],
                $tierData['tier_name'],
                $tierData['threshold_percentage'],
                $tierData['bonus_amount'],
                $tierData['tier_color'],
                $tierData['tier_order'],
                $tierData['is_cumulative']
            ]);
            
            $saved++;
            Logger::info("test-save-tiers.php - Tier '{$tierData['tier_name']}' salvo com sucesso!", 'goals.log');
            echo "   <span style='color:#4ec9b0;'>   ✅ Tier '{$tierData['tier_name']}' salvo</span>\n";
        } catch (\Exception $e) {
            Logger::error("test-save-tiers.php - Erro ao salvar tier: " . $e->getMessage(), 'goals.log');
            echo "   <span style='color:#f48771;'>   ❌ Erro: " . htmlspecialchars($e->getMessage()) . "</span>\n";
        }
    }
    
    echo "   <span style='color:#4ec9b0;'>✅ Total salvos: {$saved}/{" . count($tiers) . "}</span>\n";
} else {
    echo "   <span style='color:#dcdcaa;'>⚠️ Nenhum tier para salvar</span>\n";
}
echo "</div>";

// Testar função saveGoalConditions
echo "<div style='margin:15px 0;'><strong style='color:#569cd6;'>3. Testando salvamento de Condições...</strong>\n";

$conditions = $_POST['conditions'] ?? [];
Logger::info('test-save-tiers.php - conditions raw: ' . print_r($conditions, true), 'goals.log');
Logger::info('test-save-tiers.php - conditions is_array: ' . (is_array($conditions) ? 'YES' : 'NO'), 'goals.log');
Logger::info('test-save-tiers.php - conditions empty: ' . (empty($conditions) ? 'YES' : 'NO'), 'goals.log');
Logger::info('test-save-tiers.php - conditions count: ' . count($conditions), 'goals.log');

if (!empty($conditions)) {
    // Limpar condições existentes
    Database::query("DELETE FROM goal_bonus_conditions WHERE goal_id = ?", [$testGoalId]);
    echo "   <span style='color:#888;'>   Condições antigas removidas</span>\n";
    
    $saved = 0;
    foreach ($conditions as $index => $condition) {
        Logger::info("test-save-tiers.php - Condition {$index}: " . json_encode($condition), 'goals.log');
        
        $conditionData = [
            'goal_id' => $testGoalId,
            'metric_key' => $condition['metric_key'] ?? '',
            'operator' => $condition['operator'] ?? '>=',
            'threshold_value' => floatval($condition['threshold_value'] ?? 0),
            'is_required' => isset($condition['is_required']) && $condition['is_required'] === '1' ? 1 : 0,
            'bonus_modifier' => floatval($condition['bonus_modifier'] ?? 1.0),
            'description' => $condition['description'] ?? '',
            'check_order' => intval($condition['check_order'] ?? 0)
        ];
        
        try {
            $sql = "INSERT INTO goal_bonus_conditions 
                    (goal_id, metric_key, operator, threshold_value, is_required, bonus_modifier, description, check_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            Database::query($sql, [
                $conditionData['goal_id'],
                $conditionData['metric_key'],
                $conditionData['operator'],
                $conditionData['threshold_value'],
                $conditionData['is_required'],
                $conditionData['bonus_modifier'],
                $conditionData['description'],
                $conditionData['check_order']
            ]);
            
            $saved++;
            Logger::info("test-save-tiers.php - Condition '{$conditionData['description']}' salva com sucesso!", 'goals.log');
            echo "   <span style='color:#4ec9b0;'>   ✅ Condition '{$conditionData['description']}' salva</span>\n";
        } catch (\Exception $e) {
            Logger::error("test-save-tiers.php - Erro ao salvar condição: " . $e->getMessage(), 'goals.log');
            echo "   <span style='color:#f48771;'>   ❌ Erro: " . htmlspecialchars($e->getMessage()) . "</span>\n";
        }
    }
    
    echo "   <span style='color:#4ec9b0;'>✅ Total salvas: {$saved}/{" . count($conditions) . "}</span>\n";
} else {
    echo "   <span style='color:#dcdcaa;'>⚠️ Nenhuma condição para salvar</span>\n";
}
echo "</div>";

// Verificar resultados no banco
echo "<div style='margin:15px 0;'><strong style='color:#569cd6;'>4. Verificando no banco de dados...</strong>\n";

$tiersInDb = Database::fetchAll("SELECT * FROM goal_bonus_tiers WHERE goal_id = ?", [$testGoalId]);
echo "   <span style='color:#" . (count($tiersInDb) > 0 ? '4ec9b0' : 'dcdcaa') . "';'>Tiers no banco: " . count($tiersInDb) . "</span>\n";
if (!empty($tiersInDb)) {
    foreach ($tiersInDb as $tier) {
        echo "   <span style='color:#888;'>   - {$tier['tier_name']}: {$tier['threshold_percentage']}% = R$ {$tier['bonus_amount']}</span>\n";
    }
}

$conditionsInDb = Database::fetchAll("SELECT * FROM goal_bonus_conditions WHERE goal_id = ?", [$testGoalId]);
echo "   <span style='color:#" . (count($conditionsInDb) > 0 ? '4ec9b0' : 'dcdcaa') . "';'>Condições no banco: " . count($conditionsInDb) . "</span>\n";
if (!empty($conditionsInDb)) {
    foreach ($conditionsInDb as $cond) {
        echo "   <span style='color:#888;'>   - {$cond['description']}: {$cond['metric_key']} {$cond['operator']} {$cond['threshold_value']}</span>\n";
    }
}
echo "</div>";

// Instruções finais
echo "<div style='margin:20px 0;padding:15px;background:#3c3c1e;border-left:3px solid #dcdcaa;'>";
echo "<strong style='color:#dcdcaa;'>📋 Resultado:</strong>\n\n";
if (!empty($tiersInDb) && !empty($conditionsInDb)) {
    echo "<span style='color:#4ec9b0;'>✅ TESTE BEM-SUCEDIDO!</span>\n";
    echo "   Os tiers e condições foram salvos corretamente.\n\n";
    echo "Próximos passos:\n";
    echo "1. Veja os logs em: <a href='/view-all-logs.php' style='color:#569cd6;'>/view-all-logs.php</a>\n";
    echo "2. Teste salvando pela interface: <a href='/goals/edit?id={$testGoalId}' style='color:#569cd6;'>/goals/edit?id={$testGoalId}</a>\n";
} else {
    echo "<span style='color:#f48771;'>❌ TESTE FALHOU!</span>\n";
    echo "   Verifique os logs para mais detalhes.\n";
}
echo "</div>";

Logger::info("test-save-tiers.php - Teste concluído", 'goals.log');

echo "\n<div style='border-top:2px solid #4ec9b0;padding-top:15px;margin-top:20px;color:#4ec9b0;font-size:14px;'>";
echo "✅ TESTE COMPLETO!\n";
echo "</div>";

echo "</pre>";
