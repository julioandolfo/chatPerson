<?php
/**
 * Script para limpar cache de permissões após correção
 */

echo "<!DOCTYPE html>";
echo "<html><head>";
echo "<meta charset='UTF-8'>";
echo "<title>Limpeza de Cache de Permissões</title>";
echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
h1 { color: #333; }
.success { color: #22c55e; }
.error { color: #ef4444; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
th { background-color: #f5f5f5; font-weight: bold; }
.btn { display: inline-block; padding: 10px 20px; background: #009ef7; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
</style>";
echo "</head><body>";

echo "<h1>🧹 Limpeza de Cache de Permissões</h1>";
echo "<p>Este script limpa o cache de permissões e conversas para aplicar as correções.</p>";

echo "<hr>";

// Limpar cache de permissões manualmente
$cacheDir = __DIR__ . '/../storage/cache/permissions/';
$count = 0;

if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
            $count++;
        }
    }
    echo "<p class='success'>✅ <strong>Cache de permissões limpo!</strong> ($count arquivos removidos)</p>";
} else {
    echo "<p>ℹ️ Diretório de cache de permissões não existe ainda.</p>";
}

// Limpar cache de conversas manualmente
$cacheDir2 = __DIR__ . '/../storage/cache/conversations/';
$count2 = 0;

if (is_dir($cacheDir2)) {
    $files2 = glob($cacheDir2 . '*');
    foreach ($files2 as $file) {
        if (is_file($file)) {
            @unlink($file);
            $count2++;
        }
    }
    echo "<p class='success'>✅ <strong>Cache de conversas limpo!</strong> ($count2 arquivos removidos)</p>";
} else {
    echo "<p>ℹ️ Diretório de cache de conversas não existe ainda.</p>";
}

echo "<hr>";

// Testar as correções
echo "<h2>🧪 Testando Correções</h2>";

require_once __DIR__ . '/../config/database.php';

// Conectar ao banco
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    echo "<p class='error'>❌ Erro ao conectar ao banco: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</body></html>";
    exit;
}

// Buscar usuários de teste
$sql = "SELECT u.id, u.name, u.email, r.name as role_name, r.level
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        ORDER BY r.level ASC, u.name ASC
        LIMIT 10";

$users = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f5f5f5;'>";
echo "<th style='padding: 8px;'>ID</th>";
echo "<th style='padding: 8px;'>Nome</th>";
echo "<th style='padding: 8px;'>Role</th>";
echo "<th style='padding: 8px;'>Level</th>";
echo "<th style='padding: 8px;'>É Super Admin?</th>";
echo "<th style='padding: 8px;'>É Admin?</th>";
echo "<th style='padding: 8px;'>Pode ver todas?</th>";
echo "<th style='padding: 8px;'>Pode ver próprias?</th>";
echo "</tr>";

foreach ($users as $user) {
    // Verificar manualmente baseado no level
    $level = $user['level'] ?? 999;
    $isSuperAdmin = ($level <= 0);
    $isAdmin = ($level <= 1);
    
    // Verificar permissões no banco
    $sqlPerm = "SELECT p.slug 
                FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                INNER JOIN user_roles ur ON rp.role_id = ur.role_id
                WHERE ur.user_id = ? AND p.slug IN ('conversations.view.all', 'conversations.view.own')";
    $stmtPerm = $pdo->prepare($sqlPerm);
    $stmtPerm->execute([$user['id']]);
    $permissions = $stmtPerm->fetchAll(PDO::FETCH_COLUMN);
    
    $canViewAll = in_array('conversations.view.all', $permissions) || $isSuperAdmin || $isAdmin;
    $canViewOwn = in_array('conversations.view.own', $permissions);
    
    $bgColor = '';
    if ($isSuperAdmin) {
        $bgColor = 'background: #ffebee;'; // Vermelho claro
    } elseif ($isAdmin) {
        $bgColor = 'background: #fff3e0;'; // Laranja claro
    } elseif ($canViewAll) {
        $bgColor = 'background: #fff9c4;'; // Amarelo claro
    }
    
    echo "<tr style='{$bgColor}'>";
    echo "<td style='padding: 8px;'>{$user['id']}</td>";
    echo "<td style='padding: 8px;'>" . htmlspecialchars($user['name']) . "</td>";
    echo "<td style='padding: 8px;'>" . htmlspecialchars($user['role_name'] ?? 'Sem role') . "</td>";
    echo "<td style='padding: 8px;'>" . ($user['level'] ?? 'N/A') . "</td>";
    echo "<td style='padding: 8px;'>" . ($isSuperAdmin ? '🔴 SIM' : 'Não') . "</td>";
    echo "<td style='padding: 8px;'>" . ($isAdmin ? '🟠 SIM' : 'Não') . "</td>";
    echo "<td style='padding: 8px;'>" . ($canViewAll ? '🟡 SIM' : 'Não') . "</td>";
    echo "<td style='padding: 8px;'>" . ($canViewOwn ? '🟢 SIM' : 'Não') . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";

echo "<h2>📊 Resultado Esperado</h2>";
echo "<ul>";
echo "<li><strong>Super Admin (level 0):</strong> Deve ter acesso a TUDO (🔴)</li>";
echo "<li><strong>Admin (level 1):</strong> Deve ter acesso a TUDO (🟠)</li>";
echo "<li><strong>Supervisor (level 2):</strong> Pode ter 'ver todas' dependendo das permissões (🟡)</li>";
echo "<li><strong>Agente (level 4):</strong> Deve ver APENAS suas próprias conversas (🟢)</li>";
echo "<li><strong>Agente Júnior (level 5):</strong> Deve ver APENAS suas próprias conversas (🟢)</li>";
echo "</ul>";

echo "<hr>";

echo "<h2>✅ Próximos Passos</h2>";
echo "<ol>";
echo "<li>Faça logout do sistema</li>";
echo "<li>Faça login novamente como Agente</li>";
echo "<li>Verifique se ele vê APENAS suas próprias conversas</li>";
echo "<li>Faça login como Admin para verificar se ainda vê tudo</li>";
echo "</ol>";

echo "<p><a href='/conversations' class='btn'>Ir para Conversas</a></p>";

echo "</body></html>";

