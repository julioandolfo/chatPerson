<?php
/**
 * Página de debug para conversas.
 * Permite consultar conversa, participantes, permissões e últimos logs filtrados.
 */

// Carregar config e autoload (mesmo stack do index.php)
$appConfig = require __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Helpers/autoload.php';

// Timezone
if (!empty($appConfig['timezone'])) {
    date_default_timezone_set($appConfig['timezone']);
}

use App\Helpers\Database;

// Inputs
$conversationId = isset($_GET['cid']) ? (int)$_GET['cid'] : null;
$userId         = isset($_GET['uid']) ? (int)$_GET['uid'] : null;

// Helpers simples
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Buscar dados se CID fornecido
$conversation = null;
$participants = [];
$permissions  = [];
$logLines     = [];

if ($conversationId) {
    $conversation = Database::fetch("
        SELECT c.*, f.name AS funnel_name, fs.name AS stage_name
        FROM conversations c
        LEFT JOIN funnels f ON f.id = c.funnel_id
        LEFT JOIN funnel_stages fs ON fs.id = c.funnel_stage_id
        WHERE c.id = ?
    ", [$conversationId]);

    $participants = Database::fetchAll("
        SELECT * FROM conversation_participants
        WHERE conversation_id = ?
    ", [$conversationId]);

    if ($userId && $conversation) {
        $permissions = Database::fetchAll("
            SELECT afp.*
            FROM agent_funnel_permissions afp
            WHERE afp.user_id = ?
              AND (afp.funnel_id IS NULL OR afp.funnel_id = ?)
              AND (afp.stage_id IS NULL OR afp.stage_id = ?)
        ", [$userId, $conversation['funnel_id'] ?? 0, $conversation['funnel_stage_id'] ?? 0]);
    }

    // Logs filtrados
    $logFile = __DIR__ . '/../logs/conversas.log';
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $lines = array_reverse($lines); // mais recentes primeiro
        $filtered = array_filter($lines, function($line) use ($conversationId) {
            return strpos($line, (string)$conversationId) !== false;
        });
        $logLines = array_slice($filtered, 0, 100); // limita 100 linhas
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Debug Conversa</title>
    <style>
        body { font-family: monospace; background:#1e1e1e; color:#d4d4d4; padding:20px; }
        h1 { color:#4ec9b0; }
        h2 { color:#569cd6; border-bottom:1px solid #333; padding-bottom:4px; }
        .block { margin-bottom:20px; }
        pre { background:#252526; padding:10px; border-left:3px solid #007acc; white-space:pre-wrap; word-break:break-all; }
        label { display:block; margin-bottom:6px; }
        input { padding:6px; width:220px; }
        button { padding:6px 12px; }
    </style>
</head>
<body>
    <h1>Debug de Conversa</h1>
    <form method="get">
        <label>ID da conversa: <input type="number" name="cid" value="<?= h($conversationId ?? '') ?>"></label>
        <label>ID do usuário (opcional): <input type="number" name="uid" value="<?= h($userId ?? '') ?>"></label>
        <button type="submit">Consultar</button>
    </form>

<?php if ($conversationId): ?>
    <div class="block">
        <h2>Conversa</h2>
        <pre><?= h(print_r($conversation, true)) ?></pre>
    </div>

    <div class="block">
        <h2>Participantes</h2>
        <pre><?= h(print_r($participants, true)) ?></pre>
    </div>

    <?php if ($userId): ?>
    <div class="block">
        <h2>Permissões do usuário <?= h($userId) ?></h2>
        <pre><?= h(print_r($permissions, true)) ?></pre>
    </div>
    <?php endif; ?>

    <div class="block">
        <h2>Logs (conversas.log) contendo "<?= h($conversationId) ?>"</h2>
        <?php if (!empty($logLines)): ?>
            <pre><?= h(implode('', $logLines)) ?></pre>
        <?php else: ?>
            <pre>Nenhuma linha encontrada ou arquivo ausente.</pre>
        <?php endif; ?>
    </div>
<?php endif; ?>
</body>
</html>
