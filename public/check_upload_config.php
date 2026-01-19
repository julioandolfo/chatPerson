<?php
/**
 * Verificador de Configurações de Upload
 * Acesse: http://localhost/check_upload_config.php
 * 
 * ⚠️ DELETE este arquivo após verificar!
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Upload</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .config-item {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            margin: 8px 0;
            background: #f9f9f9;
            border-radius: 4px;
            border-left: 4px solid #2196F3;
        }
        .config-label {
            font-weight: 600;
            color: #555;
        }
        .config-value {
            font-family: monospace;
            background: #e8e8e8;
            padding: 4px 12px;
            border-radius: 3px;
        }
        .status {
            padding: 8px 16px;
            border-radius: 4px;
            margin: 20px 0;
            font-weight: 600;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .recommendation {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border-left: 4px solid #2196F3;
        }
        .delete-warning {
            background: #ffebee;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border: 2px solid #f44336;
            color: #c62828;
            font-weight: 600;
        }
        code {
            background: #272822;
            color: #f8f8f2;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Verificação de Configurações de Upload</h1>
        
        <?php
        // Converter para bytes
        function convertToBytes($value) {
            $value = trim($value);
            $last = strtolower($value[strlen($value)-1]);
            $value = (int) $value;
            switch($last) {
                case 'g': $value *= 1024;
                case 'm': $value *= 1024;
                case 'k': $value *= 1024;
            }
            return $value;
        }
        
        // Converter bytes para formato legível
        function formatBytes($bytes) {
            if ($bytes >= 1073741824) {
                return number_format($bytes / 1073741824, 2) . ' GB';
            } elseif ($bytes >= 1048576) {
                return number_format($bytes / 1048576, 2) . ' MB';
            } elseif ($bytes >= 1024) {
                return number_format($bytes / 1024, 2) . ' KB';
            } else {
                return $bytes . ' bytes';
            }
        }
        
        $configs = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'memory_limit' => ini_get('memory_limit'),
            'max_file_uploads' => ini_get('max_file_uploads')
        ];
        
        $uploadMaxBytes = convertToBytes($configs['upload_max_filesize']);
        $postMaxBytes = convertToBytes($configs['post_max_size']);
        $memoryBytes = convertToBytes($configs['memory_limit']);
        
        // Verificar status
        $allGood = true;
        $warnings = [];
        
        if ($uploadMaxBytes < 10485760) { // Menos de 10MB
            $allGood = false;
            $warnings[] = "upload_max_filesize muito baixo (mínimo recomendado: 16MB)";
        }
        
        if ($postMaxBytes < $uploadMaxBytes) {
            $allGood = false;
            $warnings[] = "post_max_size deve ser maior que upload_max_filesize";
        }
        
        if ($configs['max_execution_time'] < 60) {
            $allGood = false;
            $warnings[] = "max_execution_time muito baixo para arquivos grandes (recomendado: 300)";
        }
        
        if ($memoryBytes < 134217728) { // Menos de 128MB
            $warnings[] = "memory_limit pode ser insuficiente para arquivos grandes (recomendado: 256M)";
        }
        ?>
        
        <?php if ($allGood && empty($warnings)): ?>
            <div class="status success">
                ✅ Todas as configurações estão OK para upload de arquivos grandes!
            </div>
        <?php elseif (!empty($warnings)): ?>
            <div class="status warning">
                ⚠️ Algumas configurações podem causar problemas:
                <ul style="margin: 10px 0 0 20px;">
                    <?php foreach ($warnings as $warning): ?>
                        <li><?= htmlspecialchars($warning) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="status error">
                ❌ Configurações inadequadas para upload de arquivos grandes!
            </div>
        <?php endif; ?>
        
        <h2 style="margin-top: 30px;">📊 Configurações Atuais</h2>
        
        <div class="config-item">
            <span class="config-label">Upload Máximo por Arquivo:</span>
            <span class="config-value"><?= $configs['upload_max_filesize'] ?> (<?= formatBytes($uploadMaxBytes) ?>)</span>
        </div>
        
        <div class="config-item">
            <span class="config-label">Tamanho Máximo do POST:</span>
            <span class="config-value"><?= $configs['post_max_size'] ?> (<?= formatBytes($postMaxBytes) ?>)</span>
        </div>
        
        <div class="config-item">
            <span class="config-label">Tempo Máximo de Execução:</span>
            <span class="config-value"><?= $configs['max_execution_time'] ?> segundos</span>
        </div>
        
        <div class="config-item">
            <span class="config-label">Tempo Máximo de Input:</span>
            <span class="config-value"><?= $configs['max_input_time'] ?> segundos</span>
        </div>
        
        <div class="config-item">
            <span class="config-label">Limite de Memória:</span>
            <span class="config-value"><?= $configs['memory_limit'] ?> (<?= formatBytes($memoryBytes) ?>)</span>
        </div>
        
        <div class="config-item">
            <span class="config-label">Máximo de Arquivos por Upload:</span>
            <span class="config-value"><?= $configs['max_file_uploads'] ?></span>
        </div>
        
        <?php if (!$allGood || !empty($warnings)): ?>
            <div class="recommendation">
                <h3 style="margin-top: 0;">💡 Recomendações</h3>
                <p>Para upload de arquivos grandes (até 100MB), configure:</p>
                <ul>
                    <li><code>upload_max_filesize = 100M</code></li>
                    <li><code>post_max_size = 105M</code></li>
                    <li><code>max_execution_time = 300</code></li>
                    <li><code>max_input_time = 300</code></li>
                    <li><code>memory_limit = 256M</code></li>
                </ul>
                
                <p style="margin-top: 15px;"><strong>Como aplicar:</strong></p>
                <ol>
                    <li>Abra o Laragon</li>
                    <li>Menu → PHP → php.ini</li>
                    <li>Altere as configurações acima</li>
                    <li>Salve o arquivo</li>
                    <li>Reinicie o Laragon (Stop All → Start All)</li>
                    <li>Recarregue esta página para verificar</li>
                </ol>
            </div>
        <?php endif; ?>
        
        <div class="delete-warning">
            ⚠️ <strong>IMPORTANTE:</strong> Delete este arquivo após verificar as configurações!
            <br>
            <code>public/check_upload_config.php</code>
        </div>
        
        <p style="color: #666; font-size: 12px; margin-top: 20px;">
            Arquivo de verificação criado em: <?= date('d/m/Y H:i:s') ?>
        </p>
    </div>
</body>
</html>
