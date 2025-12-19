<?php
/**
 * Testar conteúdo do arquivo AgentPerformanceService.php
 */

$file = __DIR__ . '/../app/Services/AgentPerformanceService.php';
$content = file_get_contents($file);

// Pegar linhas 280-285
$lines = explode("\n", $content);
echo '<h1>Conteúdo Real do Arquivo (Linhas 280-285)</h1>';
echo '<pre>';
for ($i = 279; $i <= 284; $i++) {
    $lineNum = $i + 1;
    echo "Linha $lineNum: " . htmlspecialchars($lines[$i] ?? '') . "\n";
}
echo '</pre>';

// Verificar se tem os casts
if (strpos($content, '(int)round($minutes % 60)') !== false) {
    echo '<p style="color: green;">✅ O cast (int)round() está presente no arquivo!</p>';
} else {
    echo '<p style="color: red;">❌ O cast (int)round() NÃO está presente no arquivo!</p>';
}

// Info do arquivo
echo '<h2>Informações do Arquivo</h2>';
echo '<pre>';
echo 'Caminho: ' . $file . "\n";
echo 'Última modificação: ' . date('Y-m-d H:i:s', filemtime($file)) . "\n";
echo 'Tamanho: ' . filesize($file) . ' bytes' . "\n";
echo '</pre>';

echo '<hr>';
echo '<p><a href="javascript:history.back()">← Voltar</a></p>';

