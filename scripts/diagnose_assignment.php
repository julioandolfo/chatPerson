<?php
/**
 * Diagnóstico de distribuição de conversas (round-robin / atribuição avançada)
 *
 * Uso:
 *   php scripts/diagnose_assignment.php [agentIdA] [agentIdB] [dateFrom] [dateTo]
 *
 * Exemplo (Gustavo=10226, Luan=4, mês atual):
 *   php scripts/diagnose_assignment.php 10226 4
 *   php scripts/diagnose_assignment.php 10226 4 2026-06-01 2026-06-17
 *
 * Não altera NADA no banco — apenas leitura. Cole a saída inteira de volta no chat.
 */

$agentA   = (int)($argv[1] ?? 10226);
$agentB   = (int)($argv[2] ?? 4);
$dateFrom = $argv[3] ?? date('Y-m-01');
$dateTo   = ($argv[4] ?? date('Y-m-d')) . ' 23:59:59';
$ids      = [$agentA, $agentB];
$inList   = implode(',', array_map('intval', $ids));

$dbConfig = require __DIR__ . '/../config/database.php';
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options'] ?? []
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "ERRO ao conectar: " . $e->getMessage() . "\n";
    exit(1);
}

function section($t) { echo "\n========== {$t} ==========\n"; }
function q(PDO $pdo, $sql, $params = []) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
function table(array $rows) {
    if (!$rows) { echo "(sem linhas)\n"; return; }
    $cols = array_keys($rows[0]);
    $w = [];
    foreach ($cols as $c) {
        $w[$c] = strlen($c);
        foreach ($rows as $r) { $w[$c] = max($w[$c], strlen((string)($r[$c] ?? ''))); }
    }
    $line = function($vals) use ($cols, $w) {
        $out = [];
        foreach ($cols as $c) { $out[] = str_pad((string)($vals[$c] ?? ''), $w[$c]); }
        return '| ' . implode(' | ', $out) . ' |';
    };
    echo $line(array_combine($cols, $cols)) . "\n";
    echo '|' . implode('|', array_map(fn($c) => str_repeat('-', $w[$c] + 2), $cols)) . "|\n";
    foreach ($rows as $r) { echo $line($r) . "\n"; }
}
function safe(callable $fn) {
    try { $fn(); } catch (\Throwable $e) { echo "(consulta falhou: " . $e->getMessage() . ")\n"; }
}

echo "DIAGNÓSTICO DE ATRIBUIÇÃO — período {$dateFrom} .. {$dateTo}\n";
echo "Agentes analisados: {$agentA} e {$agentB}\n";

// 1) Snapshot dos agentes + portão de elegibilidade (a causa nº1 suspeita)
section("1) USERS — elegibilidade do round-robin (online / queue / capacidade)");
safe(function () use ($pdo, $inList) {
    $rows = q($pdo, "
        SELECT u.id, u.name, u.status, u.role,
               u.availability_status, u.queue_enabled,
               u.current_conversations AS contador_atual,
               u.max_conversations    AS limite,
               (SELECT COUNT(*) FROM conversations c
                 WHERE c.agent_id = u.id AND c.status IN ('open','pending')) AS abertas_reais
        FROM users u
        WHERE u.id IN ($inList)
        ORDER BY u.id");
    table($rows);
    echo "\nLeitura: se 'contador_atual' >> 'abertas_reais' e há 'limite', o agente é EXCLUÍDO por engano (contador preso).\n";
    echo "Se 'availability_status' != 'online' ou 'queue_enabled' = 0, o agente fica FORA do sorteio.\n";
});

// 2) Veredito de elegibilidade AGORA (reproduz o filtro getAvailableAgents)
section("2) PASSA NO PORTÃO AGORA? (reproduz getAvailableAgents)");
safe(function () use ($pdo, $inList) {
    $rows = q($pdo, "
        SELECT u.id, u.name,
               CASE WHEN u.status='active' THEN 'ok' ELSE 'BLOQUEIA' END AS status_ativo,
               CASE WHEN (u.queue_enabled IS NULL OR u.queue_enabled=1) THEN 'ok' ELSE 'BLOQUEIA' END AS fila,
               CASE WHEN u.availability_status='online' THEN 'ok' ELSE 'BLOQUEIA(offline)' END AS online,
               CASE WHEN (u.max_conversations IS NULL OR u.current_conversations < u.max_conversations)
                    THEN 'ok' ELSE 'BLOQUEIA(no limite)' END AS capacidade
        FROM users u WHERE u.id IN ($inList) ORDER BY u.id");
    table($rows);
});

// 3) Posição na fila do round-robin (MAX assigned_at só de open/pending)
section("3) FILA DO ROUND-ROBIN — last_assignment_at (mais ANTIGO é escolhido primeiro)");
safe(function () use ($pdo, $inList) {
    $rows = q($pdo, "
        SELECT u.id, u.name,
               MAX(COALESCE(c.assigned_at, c.created_at)) AS last_assignment_at,
               COUNT(c.id) AS abertas_pendentes
        FROM users u
        LEFT JOIN conversations c ON u.id = c.agent_id AND c.status IN ('open','pending')
        WHERE u.id IN ($inList)
        GROUP BY u.id, u.name
        ORDER BY last_assignment_at IS NULL DESC, last_assignment_at ASC");
    table($rows);
    echo "\nLeitura: NULL = fila vazia = tratado como 1970 = vai pro TOPO e é escolhido. Quem fecha rápido volta ao topo.\n";
});

// 4) Departamentos
section("4) DEPARTAMENTOS (agent_departments)");
safe(function () use ($pdo, $inList) {
    table(q($pdo, "SELECT user_id, department_id FROM agent_departments WHERE user_id IN ($inList) ORDER BY user_id"));
});

// 5) Permissões de funil/etapa (exclusão silenciosa)
section("5) PERMISSÕES DE FUNIL/ETAPA (agent_funnel_permissions)");
safe(function () use ($pdo, $inList) {
    $rows = q($pdo, "SELECT user_id, COUNT(*) AS qtd_permissoes,
                            SUM(permission_type='view') AS qtd_view
                     FROM agent_funnel_permissions WHERE user_id IN ($inList) GROUP BY user_id ORDER BY user_id");
    table($rows);
    echo "\n(Se um agente tem MENOS permissões 'view', ele é excluído das filas dos funis que não enxerga.)\n";
});

// 6) Distribuição real de NOVAS conversas no período (reproduz o skew 159 vs 45)
section("6) NOVAS CONVERSAS NO PERÍODO por agente (criadas no intervalo)");
safe(function () use ($pdo, $inList, $dateFrom, $dateTo) {
    $rows = q($pdo, "
        SELECT c.agent_id,
               COUNT(*) AS novas_total,
               SUM(CASE WHEN c.id = (SELECT MIN(c2.id) FROM conversations c2 WHERE c2.contact_id=c.contact_id)
                        THEN 1 ELSE 0 END) AS primeira_vida
        FROM conversations c
        WHERE c.agent_id IN ($inList)
          AND c.created_at >= ? AND c.created_at <= ?
        GROUP BY c.agent_id ORDER BY c.agent_id", [$dateFrom, $dateTo]);
    table($rows);
});

// 7) Visão geral: TOP agentes por volume no período (todos, não só os 2)
section("7) TOP 12 AGENTES por NOVAS conversas no período (panorama do rodízio)");
safe(function () use ($pdo, $dateFrom, $dateTo) {
    $rows = q($pdo, "
        SELECT c.agent_id, u.name, u.availability_status, u.queue_enabled,
               u.current_conversations, u.max_conversations,
               COUNT(*) AS novas
        FROM conversations c
        LEFT JOIN users u ON u.id = c.agent_id
        WHERE c.created_at >= ? AND c.created_at <= ? AND c.agent_id IS NOT NULL
        GROUP BY c.agent_id, u.name, u.availability_status, u.queue_enabled, u.current_conversations, u.max_conversations
        ORDER BY novas DESC LIMIT 12", [$dateFrom, $dateTo]);
    table($rows);
});

// 8) Configurações de distribuição (método e flags), se acessíveis
section("8) CONFIG DE DISTRIBUIÇÃO (settings)");
safe(function () use ($pdo) {
    $rows = q($pdo, "SELECT `key`, `value` FROM settings
                     WHERE `key` LIKE '%distribution%' OR `key` LIKE '%conversation%' OR `key` LIKE '%contact_agent%'
                     ORDER BY `key` LIMIT 20");
    if (!$rows) { echo "(nenhuma chave de settings encontrada com esse filtro — pode estar em outra tabela/estrutura)\n"; return; }
    foreach ($rows as $r) {
        $val = $r['value'];
        if (strlen($val) > 400) $val = substr($val, 0, 400) . '…';
        echo "- {$r['key']}: {$val}\n";
    }
});

echo "\n========== FIM ==========\n";
echo "Cole TODA a saída acima de volta no chat.\n";
