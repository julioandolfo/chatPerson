<?php
/**
 * Script para limpar cache de permissões após correção
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Services/PermissionService.php';
require_once __DIR__ . '/../app/Services/ConversationService.php';

echo "<h1>🧹 Limpeza de Cache de Permissões</h1>";
echo "<p>Este script limpa o cache de permissões e conversas para aplicar as correções.</p>";

echo "<hr>";

// Limpar cache de permissões
try {
    \App\Services\PermissionService::clearAllCache();
    echo "<p>✅ <strong>Cache de permissões limpo!</strong></p>";
} catch (Exception $e) {
    echo "<p>❌ Erro ao limpar cache de permissões: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Limpar cache de conversas
try {
    \App\Services\ConversationService::clearAllCache();
    echo "<p>✅ <strong>Cache de conversas limpo!</strong></p>";
} catch (Exception $e) {
    echo "<p>❌ Erro ao limpar cache de conversas: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";

// Testar as correções
echo "<h2>🧪 Testando Correções</h2>";

$db = \App\Helpers\Database::getInstance();

// Buscar usuários de teste
$sql = "SELECT u.id, u.name, u.email, r.name as role_name, r.level
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        ORDER BY r.level ASC, u.name ASC
        LIMIT 10";

$users = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

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
    $isSuperAdmin = \App\Services\PermissionService::isSuperAdmin($user['id']);
    $isAdmin = \App\Services\PermissionService::isAdmin($user['id']);
    $canViewAll = \App\Services\PermissionService::hasPermission($user['id'], 'conversations.view.all');
    $canViewOwn = \App\Services\PermissionService::hasPermission($user['id'], 'conversations.view.own');
    
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

echo "<p><a href='/conversations' style='padding: 10px 20px; background: #009ef7; color: white; text-decoration: none; border-radius: 4px;'>Ir para Conversas</a></p>";

