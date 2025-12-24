<?php
/**
 * Script de Debug: Ler conteúdo do arquivo de áudio corrompido
 * Acesse: http://seudominio.com/debug-audio-file.php
 */

// Diretório dos arquivos TTS
$ttsDir = __DIR__ . '/assets/media/tts/';

// Listar todos os arquivos
$files = glob($ttsDir . 'tts_elevenlabs_*.mp3');

echo "<h1>🔍 Debug: Arquivos TTS ElevenLabs</h1>";
echo "<p><strong>Diretório:</strong> {$ttsDir}</p>";
echo "<hr>";

if (empty($files)) {
    echo "<p style='color: red;'>❌ Nenhum arquivo TTS do ElevenLabs encontrado!</p>";
    exit;
}

// Ordenar por data (mais recente primeiro)
usort($files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

foreach ($files as $file) {
    $filename = basename($file);
    $filesize = filesize($file);
    $filetime = date('Y-m-d H:i:s', filemtime($file));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $file);
    finfo_close($finfo);
    
    echo "<div style='border: 1px solid #ccc; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h3>📄 {$filename}</h3>";
    echo "<p><strong>Tamanho:</strong> {$filesize} bytes</p>";
    echo "<p><strong>Data:</strong> {$filetime}</p>";
    echo "<p><strong>MIME Detectado:</strong> {$detectedMime}</p>";
    
    // Se arquivo pequeno (< 1KB), é provavelmente um erro
    if ($filesize < 1024) {
        echo "<p style='color: orange;'>⚠️ <strong>ARQUIVO SUSPEITO!</strong> Muito pequeno para ser áudio válido.</p>";
        
        // Ler conteúdo
        $content = file_get_contents($file);
        
        echo "<h4>📝 Conteúdo do Arquivo:</h4>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto;'>";
        echo htmlspecialchars($content);
        echo "</pre>";
        
        // Tentar decodificar como JSON
        $json = json_decode($content, true);
        if ($json) {
            echo "<h4>🔍 Conteúdo Decodificado (JSON):</h4>";
            echo "<pre style='background: #ffe6e6; padding: 10px; border-radius: 5px; overflow-x: auto;'>";
            echo htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "</pre>";
            
            // Extrair mensagem de erro
            if (isset($json['detail'])) {
                $errorMsg = is_array($json['detail']) 
                    ? ($json['detail']['message'] ?? $json['detail']['msg'] ?? json_encode($json['detail']))
                    : $json['detail'];
                echo "<p style='color: red; font-size: 18px;'><strong>❌ ERRO:</strong> {$errorMsg}</p>";
            }
        }
    } else {
        echo "<p style='color: green;'>✅ Arquivo parece válido (tamanho adequado).</p>";
        echo "<p><a href='/assets/media/tts/{$filename}' target='_blank'>▶️ Reproduzir Áudio</a></p>";
    }
    
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='/view-all-logs.php'>← Voltar para Logs</a></p>";

