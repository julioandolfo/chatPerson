<?php
/**
 * Script simples para mostrar informações da conta WhatsApp
 * e gerar SQL para atualizar instance_id
 */

// Carregar autoload
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';

use App\Models\WhatsAppAccount;
use App\Helpers\Database;

// Setar header HTML
header('Content-Type: text/html; charset=utf-8');

try {
    Database::getInstance();
} catch (\Exception $e) {
    die("❌ Erro ao conectar ao banco de dados: " . $e->getMessage());
}

// Buscar todas as contas WhatsApp
$accounts = Database::fetchAll("SELECT * FROM whatsapp_accounts ORDER BY id");

?>
<!DOCTYPE html>
<html>
<head>
    <title>WhatsApp Accounts - Instance ID Helper</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .account {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .account h2 {
            margin-top: 0;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        table td:first-child {
            font-weight: bold;
            width: 200px;
            color: #666;
        }
        .sql {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            margin: 15px 0;
            overflow-x: auto;
        }
        .recommendation {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 15px 0;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status.active {
            background: #4caf50;
            color: white;
        }
        .status.inactive {
            background: #ff9800;
            color: white;
        }
        .empty {
            color: #f44336;
            font-weight: bold;
        }
        .filled {
            color: #4caf50;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>🔧 WhatsApp Accounts - Instance ID Helper</h1>
    
    <?php if (empty($accounts)): ?>
        <div class="account">
            <p>❌ Nenhuma conta WhatsApp encontrada no banco de dados.</p>
        </div>
    <?php else: ?>
        <?php foreach ($accounts as $account): ?>
            <div class="account">
                <h2>
                    #<?= $account['id'] ?> - <?= htmlspecialchars($account['name']) ?>
                    <span class="status <?= $account['status'] ?>">
                        <?= strtoupper($account['status']) ?>
                    </span>
                </h2>
                
                <table>
                    <tr>
                        <td>Provider:</td>
                        <td><?= htmlspecialchars($account['provider']) ?></td>
                    </tr>
                    <tr>
                        <td>Phone:</td>
                        <td><?= htmlspecialchars($account['phone_number']) ?></td>
                    </tr>
                    <tr>
                        <td>API URL:</td>
                        <td><?= htmlspecialchars($account['api_url']) ?></td>
                    </tr>
                    <tr>
                        <td>Quepasa User:</td>
                        <td><?= $account['quepasa_user'] ? htmlspecialchars($account['quepasa_user']) : '<span class="empty">VAZIO</span>' ?></td>
                    </tr>
                    <tr>
                        <td>Quepasa TrackID:</td>
                        <td><?= $account['quepasa_trackid'] ? htmlspecialchars($account['quepasa_trackid']) : '<span class="empty">VAZIO</span>' ?></td>
                    </tr>
                    <tr>
                        <td>Instance ID (atual):</td>
                        <td><?= $account['instance_id'] ? '<span class="filled">' . htmlspecialchars($account['instance_id']) . '</span>' : '<span class="empty">VAZIO ❌</span>' ?></td>
                    </tr>
                </table>
                
                <?php if (empty($account['instance_id'])): ?>
                    <div class="recommendation">
                        <strong>📌 Recomendação:</strong><br>
                        Use o valor de <strong>Quepasa TrackID</strong> como instance_id, pois é o que você define na criação da instância.
                    </div>
                    
                    <h3>🔧 Possíveis valores para instance_id:</h3>
                    <ul>
                        <?php if (!empty($account['quepasa_trackid'])): ?>
                            <li><strong>quepasa_trackid:</strong> <?= htmlspecialchars($account['quepasa_trackid']) ?> ⭐ (Recomendado)</li>
                        <?php endif; ?>
                        
                        <?php if (!empty($account['quepasa_user'])): ?>
                            <li><strong>quepasa_user:</strong> <?= htmlspecialchars($account['quepasa_user']) ?></li>
                        <?php endif; ?>
                        
                        <?php
                        // Extrair da URL
                        $urlParts = parse_url($account['api_url']);
                        $path = $urlParts['path'] ?? '';
                        $pathSegments = array_filter(explode('/', trim($path, '/')));
                        if (!empty($pathSegments)):
                            $urlInstance = end($pathSegments);
                        ?>
                            <li><strong>url_path:</strong> <?= htmlspecialchars($urlInstance) ?></li>
                        <?php endif; ?>
                    </ul>
                    
                    <h3>💾 SQL para atualizar:</h3>
                    <?php
                    $recommendedValue = $account['quepasa_trackid'] ?? $account['quepasa_user'] ?? ($urlInstance ?? 'SEU_INSTANCE_ID');
                    ?>
                    <div class="sql">
UPDATE whatsapp_accounts <br>
SET instance_id = '<?= addslashes($recommendedValue) ?>' <br>
WHERE id = <?= $account['id'] ?>;
                    </div>
                    
                    <p>
                        <a href="?update=<?= $account['id'] ?>&value=<?= urlencode($recommendedValue) ?>" 
                           onclick="return confirm('Confirma atualizar instance_id para: <?= addslashes($recommendedValue) ?>?')"
                           style="background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">
                            ✅ Atualizar Automaticamente
                        </a>
                    </p>
                <?php else: ?>
                    <div style="background: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px; margin: 15px 0;">
                        ✅ <strong>Instance ID já configurado!</strong> Nenhuma ação necessária.
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php
    // Processar atualização se solicitada
    if (isset($_GET['update']) && isset($_GET['value'])) {
        $accountId = (int)$_GET['update'];
        $instanceId = trim($_GET['value']);
        
        if ($accountId > 0 && !empty($instanceId)) {
            try {
                WhatsAppAccount::update($accountId, ['instance_id' => $instanceId]);
                echo "<script>alert('✅ Instance ID atualizado com sucesso!'); window.location.href = window.location.pathname;</script>";
            } catch (\Exception $e) {
                echo "<script>alert('❌ Erro: " . addslashes($e->getMessage()) . "');</script>";
            }
        }
    }
    ?>
</body>
</html>

