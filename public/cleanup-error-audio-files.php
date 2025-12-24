<?php
/**
 * Script para limpar arquivos de áudio corrompidos (erros JSON)
 * Acesse: http://seudominio.com/cleanup-error-audio-files.php
 */

// Diretório dos arquivos TTS
$ttsDir = __DIR__ . '/assets/media/tts/';

// Listar todos os arquivos
$files = glob($ttsDir . 'tts_elevenlabs_*.mp3');

echo "<h1>🧹 Limpeza: Arquivos de Áudio Corrompidos</h1>";
echo "<p><strong>Diretório:</strong> {$ttsDir}</p>";
echo "<hr>";

if (empty($files)) {
    echo "<p style='color: orange;'>⚠️ Nenhum arquivo TTS do ElevenLabs encontrado!</p>";
    exit;
}

$deletedCount = 0;
$totalSize = 0;

foreach ($files as $file) {
    $filename = basename($file);
    $filesize = filesize($file);
    
    // Se arquivo pequeno (< 1KB), é provavelmente um erro JSON
    if ($filesize < 1024) {
        $content = file_get_contents($file);
        $json = @json_decode($content, true);
        
        // Verificar se é realmente um erro JSON
        if ($json && isset($json['detail'])) {
            $errorMsg = $json['detail']['message'] ?? 'Erro desconhecido';
            
            echo "<div style='border: 1px solid #f44336; padding: 10px; margin: 5px 0; border-radius: 5px; background: #ffebee;'>";
            echo "<p>❌ <strong>{$filename}</strong> ({$filesize} bytes)</p>";
            echo "<p style='color: #d32f2f;'><strong>Erro:</strong> {$errorMsg}</p>";
            
            // Deletar arquivo
            if (@unlink($file)) {
                echo "<p style='color: green;'>✅ Arquivo deletado com sucesso!</p>";
                $deletedCount++;
                $totalSize += $filesize;
            } else {
                echo "<p style='color: red;'>⚠️ Falha ao deletar arquivo.</p>";
            }
            
            echo "</div>";
        }
    }
}

echo "<hr>";
echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 5px; border-left: 4px solid #2196f3;'>";
echo "<h3>📊 Resumo:</h3>";
echo "<p><strong>Arquivos deletados:</strong> {$deletedCount}</p>";
echo "<p><strong>Espaço liberado:</strong> " . number_format($totalSize) . " bytes</p>";
echo "</div>";

if ($deletedCount > 0) {
    echo "<p style='color: green; font-size: 18px;'>✅ Limpeza concluída!</p>";
} else {
    echo "<p style='color: blue;'>ℹ️ Nenhum arquivo corrompido encontrado.</p>";
}

echo "<hr>";
echo "<p><a href='/view-all-logs.php'>← Voltar para Logs</a> | <a href='/debug-audio-file.php'>Ver Arquivos Restantes</a></p>";

