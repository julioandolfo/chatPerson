<?php
/**
 * Debug: Ver o que está sendo enviado no POST ao salvar metas
 */

// Simular POST de criação de meta com tiers e condições
$_POST = [
    'name' => 'Meta Teste',
    'type' => 'revenue',
    'target_value' => 100000,
    'enable_bonus' => '1',
    'enable_bonus_conditions' => '1',
    
    // TIERS
    'tiers' => [
        0 => [
            'id' => '',
            'tier_name' => 'Bronze',
            'threshold_percentage' => 50,
            'bonus_amount' => 500,
            'tier_color' => 'bronze',
            'tier_order' => 0
        ],
        1 => [
            'id' => '',
            'tier_name' => 'Prata',
            'threshold_percentage' => 70,
            'bonus_amount' => 1000,
            'tier_color' => 'silver',
            'tier_order' => 1
        ]
    ],
    
    // CONDIÇÕES
    'conditions' => [
        0 => [
            'condition_type' => 'conversion_rate',
            'operator' => '>=',
            'min_value' => 15,
            'is_required' => '1',
            'bonus_modifier' => 0.5,
            'description' => 'Conversão mínima'
        ]
    ]
];

echo "=== POST DATA ===\n\n";
echo "Tiers:\n";
print_r($_POST['tiers'] ?? 'VAZIO');

echo "\n\nConditions:\n";
print_r($_POST['conditions'] ?? 'VAZIO');

echo "\n\n=== VERIFICAÇÃO ===\n\n";

// Simular o que o controller faz
$tiers = $_POST['tiers'] ?? null;
$conditions = $_POST['conditions'] ?? null;

echo "Tiers is_array: " . (is_array($tiers) ? 'SIM' : 'NÃO') . "\n";
echo "Tiers empty: " . (empty($tiers) ? 'SIM' : 'NÃO') . "\n";
echo "Tiers count: " . (is_array($tiers) ? count($tiers) : 0) . "\n\n";

echo "Conditions is_array: " . (is_array($conditions) ? 'SIM' : 'NÃO') . "\n";
echo "Conditions empty: " . (empty($conditions) ? 'SIM' : 'NÃO') . "\n";
echo "Conditions count: " . (is_array($conditions) ? count($conditions) : 0) . "\n\n";

// Processar tiers como o controller faz
if (!empty($tiers) && is_array($tiers)) {
    echo "=== PROCESSANDO TIERS ===\n\n";
    foreach ($tiers as $index => $tier) {
        echo "Tier {$index}:\n";
        
        // Pular tiers sem dados essenciais
        if (empty($tier['tier_name']) && empty($tier['threshold_percentage'])) {
            echo "  -> PULADO (sem dados essenciais)\n\n";
            continue;
        }
        
        $tierData = [
            'tier_name' => $tier['tier_name'] ?? 'Tier',
            'threshold_percentage' => floatval($tier['threshold_percentage'] ?? 0),
            'bonus_amount' => floatval($tier['bonus_amount'] ?? 0),
            'tier_color' => $tier['tier_color'] ?? 'bronze',
            'tier_order' => intval($tier['tier_order'] ?? 0),
            'is_cumulative' => isset($tier['is_cumulative']) ? 1 : 0
        ];
        
        print_r($tierData);
        echo "\n";
    }
}

// Processar condições como o controller faz
if (!empty($conditions) && is_array($conditions)) {
    echo "\n=== PROCESSANDO CONDIÇÕES ===\n\n";
    foreach ($conditions as $index => $condition) {
        echo "Condition {$index}:\n";
        
        if (empty($condition['condition_type']) || !isset($condition['min_value'])) {
            echo "  -> PULADO (dados incompletos)\n\n";
            continue;
        }
        
        $conditionData = [
            'condition_type' => $condition['condition_type'],
            'operator' => $condition['operator'] ?? '>=',
            'min_value' => floatval($condition['min_value']),
            'is_required' => isset($condition['is_required']) ? 1 : 0,
            'bonus_modifier' => floatval($condition['bonus_modifier'] ?? 0.5),
            'description' => $condition['description'] ?? null
        ];
        
        print_r($conditionData);
        echo "\n";
    }
}

echo "\n=== FIM DEBUG ===\n";
