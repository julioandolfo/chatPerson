<?php
/**
 * Debug de configurações de som do usuário
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

use App\Helpers\Auth;
use App\Helpers\Database;
use App\Models\UserSoundSettings;

session_start();
$userId = Auth::id();

if (!$userId) {
    die('Faça login primeiro');
}

echo "<h2>Debug de Som - Usuário #$userId</h2>";
echo "<pre>";

// 1. Buscar configurações atuais
$sql = "SELECT * FROM user_sound_settings WHERE user_id = ?";
$settings = Database::fetch($sql, [$userId]);

if (!$settings) {
    echo "❌ Nenhuma configuração encontrada para o usuário.\n";
} else {
    echo "✅ Configurações encontradas:\n\n";
    
    $soundFields = [
        'new_conversation_sound',
        'new_message_sound',
        'conversation_assigned_sound',
        'invite_received_sound',
        'sla_warning_sound',
        'sla_breached_sound',
        'mention_received_sound'
    ];
    
    foreach ($soundFields as $field) {
        $soundFile = $settings[$field] ?? 'NÃO DEFINIDO';
        $enabledField = str_replace('_sound', '_enabled', $field);
        $enabled = $settings[$enabledField] ?? 1;
        
        $filePath = __DIR__ . '/assets/sounds/' . $soundFile;
        $exists = file_exists($filePath);
        
        echo sprintf(
            "%s %s:\n   Som: %s\n   Ativo: %s\n   Arquivo existe: %s\n\n",
            $enabled ? '🔊' : '🔇',
            $field,
            $soundFile,
            $enabled ? 'SIM' : 'NÃO',
            $exists ? '✅ SIM' : '❌ NÃO - PROBLEMA!'
        );
    }
}

// 2. Listar arquivos no diretório
echo "\n📂 Arquivos em /public/assets/sounds/:\n";
$soundsDir = __DIR__ . '/assets/sounds';
$files = glob($soundsDir . '/*.*');

if (empty($files)) {
    echo "   ⚠️ VAZIO!\n";
} else {
    foreach ($files as $file) {
        echo "   - " . basename($file) . "\n";
    }
}

// 3. Sons customizados no banco
echo "\n🎵 Sons customizados cadastrados:\n";
$sql = "SELECT * FROM custom_sounds WHERE user_id IS NULL OR user_id = ? ORDER BY created_at DESC";
$customSounds = Database::fetchAll($sql, [$userId]);

if (empty($customSounds)) {
    echo "   Nenhum.\n";
} else {
    foreach ($customSounds as $sound) {
        $filePath = $soundsDir . '/' . $sound['filename'];
        $exists = file_exists($filePath);
        
        echo sprintf(
            "   %s %s (%s)\n      Arquivo: %s\n      Existe: %s\n",
            $exists ? '✅' : '❌',
            $sound['name'],
            $sound['filename'],
            $filePath,
            $exists ? 'SIM' : 'NÃO'
        );
    }
}

echo "</pre>";

// Formulário para testar atualização
echo "<hr>";
echo "<h3>Testar Atualização Manual</h3>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $soundFile = $_POST['sound_file'] ?? '';
    
    if ($soundFile) {
        $sql = "UPDATE user_sound_settings SET new_conversation_sound = ? WHERE user_id = ?";
        Database::query($sql, [$soundFile, $userId]);
        echo "<p style='color:green'>✅ new_conversation_sound atualizado para: <strong>$soundFile</strong></p>";
        echo "<p>Recarregue a página e verifique se funcionou.</p>";
    }
}

?>
<form method="POST">
    <label>Definir som para Nova Conversa:</label><br>
    <select name="sound_file" style="padding: 10px; width: 300px;">
        <option value="">-- Selecione --</option>
        <?php foreach ($files as $file): ?>
        <option value="<?= basename($file) ?>"><?= basename($file) ?></option>
        <?php endforeach; ?>
        <?php foreach ($customSounds as $sound): ?>
        <option value="<?= $sound['filename'] ?>"><?= $sound['name'] ?> (customizado)</option>
        <?php endforeach; ?>
    </select>
    <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer;">
        Atualizar Manualmente
    </button>
</form>

