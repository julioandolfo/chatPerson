<?php
/**
 * Script para diagnosticar problemas com sons
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

use App\Helpers\Auth;
use App\Helpers\Database;
use App\Services\SoundNotificationService;

// Verificar autenticação
session_start();
$userId = Auth::id();

echo "<h2>Diagnóstico de Sons</h2>";
echo "<pre>";

// 1. Verificar diretório de sons
$soundsDir = __DIR__ . '/assets/sounds';
echo "📁 Diretório de sons: $soundsDir\n";
echo "   Existe: " . (is_dir($soundsDir) ? '✅ SIM' : '❌ NÃO') . "\n";
echo "   Gravável: " . (is_writable($soundsDir) ? '✅ SIM' : '❌ NÃO') . "\n";

// 2. Listar arquivos no diretório
echo "\n📂 Arquivos no diretório:\n";
if (is_dir($soundsDir)) {
    $files = glob($soundsDir . '/*.*');
    if (empty($files)) {
        echo "   ⚠️ VAZIO - Nenhum arquivo encontrado!\n";
    } else {
        foreach ($files as $file) {
            $filename = basename($file);
            $size = filesize($file);
            echo "   - $filename (" . number_format($size / 1024, 1) . " KB)\n";
        }
    }
} else {
    echo "   ❌ Diretório não existe!\n";
}

// 3. Verificar sons esperados
echo "\n🔊 Sons padrão esperados:\n";
$defaultSounds = [
    'new-conversation.mp3',
    'new-message.mp3',
    'assigned.mp3',
    'invite.mp3',
    'sla-warning.mp3',
    'sla-breached.mp3',
    'mention.mp3'
];

foreach ($defaultSounds as $sound) {
    $path = $soundsDir . '/' . $sound;
    $exists = file_exists($path);
    echo "   " . ($exists ? '✅' : '❌') . " $sound" . ($exists ? '' : ' (FALTANDO!)') . "\n";
}

// 4. Verificar sons customizados no banco
echo "\n🎵 Sons customizados no banco de dados:\n";
try {
    $sql = "SELECT * FROM custom_sounds ORDER BY created_at DESC LIMIT 10";
    $customSounds = Database::fetchAll($sql);
    
    if (empty($customSounds)) {
        echo "   Nenhum som customizado cadastrado.\n";
    } else {
        foreach ($customSounds as $sound) {
            $filePath = $soundsDir . '/' . $sound['filename'];
            $exists = file_exists($filePath);
            echo "   " . ($exists ? '✅' : '❌') . " {$sound['name']} ({$sound['filename']})\n";
            echo "      ID: {$sound['id']}, User: " . ($sound['user_id'] ?? 'Sistema') . "\n";
            echo "      Arquivo existe: " . ($exists ? 'SIM' : 'NÃO - ARQUIVO FALTANDO!') . "\n";
            if (!$exists && !empty($sound['file_path'])) {
                echo "      Path salvo: {$sound['file_path']}\n";
                echo "      Path esperado: $filePath\n";
            }
        }
    }
} catch (Exception $e) {
    echo "   ❌ Erro ao consultar banco: " . $e->getMessage() . "\n";
}

// 5. Verificar configurações do usuário
if ($userId) {
    echo "\n⚙️ Configurações do usuário $userId:\n";
    try {
        $sql = "SELECT * FROM user_sound_settings WHERE user_id = ?";
        $settings = Database::fetch($sql, [$userId]);
        
        if ($settings) {
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
                $soundFile = $settings[$field] ?? 'não definido';
                $exists = file_exists($soundsDir . '/' . $soundFile);
                $enabled = $settings[str_replace('_sound', '_enabled', $field)] ?? 1;
                echo "   " . ($enabled ? ($exists ? '✅' : '❌') : '⏸️') . " $field: $soundFile";
                if (!$exists && $enabled) {
                    echo " (ARQUIVO NÃO EXISTE!)";
                }
                echo "\n";
            }
        } else {
            echo "   Usando configurações padrão.\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Erro: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n⚠️ Usuário não autenticado - faça login primeiro.\n";
}

echo "</pre>";

// Botão para copiar sons de exemplo
echo "<hr>";
echo "<h3>Ações</h3>";
echo "<p>Se os sons padrão estão faltando, você precisa fazer upload deles via Git/FTP ou criar sons de teste.</p>";

