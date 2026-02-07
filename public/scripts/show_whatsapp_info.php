<?php
/**
 * Script simples para mostrar informações da conta WhatsApp
 * e gerar SQL para atualizar instance_id
 */

// Carregar autoload
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';

use App\Models\IntegrationAccount;
use App\Helpers\Database;

// Setar header HTML
header('Content-Type: text/html; charset=utf-8');

try {
    Database::getInstance();
} catch (\Exception $e) {
    die("❌ Erro ao conectar ao banco de dados: " . $e->getMessage());
}

// Buscar todas as contas WhatsApp
$accounts = Database::fetchAll("SELECT * FROM integration_accounts WHERE channel = 'whatsapp' ORDER BY id");

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
                        <td>Quepasa ChatID (WID):</td>
                        <td><?= $account['quepasa_chatid'] ? '<span class="filled">' . htmlspecialchars($account['quepasa_chatid']) . '</span>' : '<span class="empty">VAZIO ❌</span>' ?></td>
                    </tr>
                    <tr>
                        <td>Quepasa Token:</td>
                        <td><?= $account['quepasa_token'] ? '<span class="filled">***' . substr($account['quepasa_token'], -8) . '</span>' : '<span class="empty">VAZIO ❌</span>' ?></td>
                    </tr>
                </table>
                
                <?php if ($account['status'] === 'active'): ?>
                    <div style="background: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px; margin: 15px 0;">
                        ✅ <strong>Conta conectada e ativa!</strong><br>
                        Configuração está OK. O sistema usará endpoint <code>/v3/bot/{token}/picinfo</code> ou <code>/v4/bot/{token}/picinfo</code> para buscar avatares.
                    </div>
                <?php else: ?>
                    <div style="background: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; margin: 15px 0;">
                        ⚠️ <strong>Conta inativa!</strong><br>
                        Conecte via QR Code para ativar a conta e começar a receber mensagens.
                    </div>
                <?php endif; ?>
                
                <div class="recommendation">
                    <strong>ℹ️ Informação:</strong><br>
                    Esta instalação do Quepasa usa formato <strong>single-instance</strong> (sem instance_id).<br>
                    Para buscar avatares, o sistema utiliza:
                    <ul style="margin: 10px 0 0 20px;">
                        <li>Endpoint: <code>GET /v3/bot/:token/picinfo</code> ou <code>/v4/bot/:token/picinfo</code></li>
                        <li>Header: <code>X-QUEPASA-CHATID: {chatId}</code></li>
                        <li>Header: <code>X-QUEPASA-TOKEN: {token}</code></li>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
</body>
</html>

