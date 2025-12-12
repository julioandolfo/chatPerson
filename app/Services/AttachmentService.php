<?php
/**
 * Service AttachmentService
 * Gerencia upload, armazenamento e download de anexos
 */

namespace App\Services;

use App\Helpers\Logger;

class AttachmentService
{
    private static string $uploadDir = __DIR__ . '/../../public/assets/media/attachments/';
    private static int $maxFileSize = 10 * 1024 * 1024; // 10MB
    private static array $allowedTypes = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'video' => ['mp4', 'webm', 'ogg'],
        'audio' => ['mp3', 'wav', 'ogg', 'webm'], // webm (audio/webm) será convertido para ogg/opus
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv']
    ];

    /**
     * Upload de arquivo
     */
    public static function upload(array $file, int $conversationId, ?int $messageId = null): array
    {
        // Validar arquivo
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('Erro ao fazer upload do arquivo');
        }

        // Validar tamanho
        if ($file['size'] > self::$maxFileSize) {
            throw new \Exception('Arquivo muito grande. Tamanho máximo: ' . (self::$maxFileSize / 1024 / 1024) . 'MB');
        }

        // Obter extensão
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mimeType = self::getMimeType($file['tmp_name']);

        // Validar tipo
        $fileType = self::getFileType($extension, $mimeType);
        if (!$fileType) {
            throw new \Exception('Tipo de arquivo não permitido');
        }

        // Criar diretório se não existir
        $conversationDir = self::$uploadDir . $conversationId . '/';
        if (!is_dir($conversationDir)) {
            mkdir($conversationDir, 0755, true);
        }

        // Gerar nome único
        $filename = uniqid('msg_', true) . '_' . time() . '.' . $extension;
        $filepath = $conversationDir . $filename;

        // Mover arquivo
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new \Exception('Erro ao salvar arquivo');
        }

        // Se for áudio webm, converter para ogg/opus para compatibilidade com WhatsApp
        // IMPORTANTE: Arquivos gravados como áudio podem vir como video/webm (sem stream de vídeo)
        // Precisamos verificar se é realmente apenas áudio
        Logger::quepasa("AttachmentService::upload - Verificando conversão: fileType={$fileType}, mimeType={$mimeType}, extension={$extension}");
        
        $isAudioWebm = false;
        
        // Caso 1: Já detectado como áudio
        if ($fileType === 'audio' && (str_contains($mimeType, 'webm') || $extension === 'webm')) {
            $isAudioWebm = true;
            Logger::quepasa("AttachmentService::upload - Detectado como áudio webm (fileType=audio)");
        }
        
        // Caso 2: Detectado como video/webm mas pode ser apenas áudio (gravação de áudio do navegador)
        if (!$isAudioWebm && $extension === 'webm' && str_contains($mimeType, 'webm')) {
            Logger::quepasa("AttachmentService::upload - Arquivo webm detectado, verificando se é apenas áudio...");
            
            // Verificar se arquivo tem apenas stream de áudio (sem vídeo)
            // Usar ffprobe se disponível, ou verificar tamanho/nome do arquivo
            $isAudioOnly = self::isWebmAudioOnly($filepath);
            
            if ($isAudioOnly) {
                $isAudioWebm = true;
                Logger::quepasa("AttachmentService::upload - Confirmado: é áudio webm (sem stream de vídeo)");
                // Atualizar fileType para audio
                $fileType = 'audio';
            } else {
                Logger::quepasa("AttachmentService::upload - É vídeo webm (tem stream de vídeo), não converter");
            }
        }
        
        if ($isAudioWebm) {
            Logger::quepasa("AttachmentService::upload - ✅ ÁUDIO WEBM DETECTADO! Iniciando conversão para OGG/Opus...");
            Logger::quepasa("AttachmentService::upload - Arquivo original: {$filepath} (" . filesize($filepath) . " bytes)");
            
            $conversion = self::convertWebmToOpus($filepath, $conversationDir);
            
            Logger::quepasa("AttachmentService::upload - Resultado da conversão: " . json_encode($conversion));
            
            if ($conversion['success']) {
                $filename = $conversion['filename'];
                $filepath = $conversion['filepath'];
                $mimeType = $conversion['mime_type'];
                $extension = 'ogg';
                $file['size'] = $conversion['size'];
                
                Logger::quepasa("AttachmentService::upload - ✅ CONVERSÃO BEM-SUCEDIDA!");
                Logger::quepasa("AttachmentService::upload - Arquivo convertido: {$filepath} ({$file['size']} bytes)");
                Logger::quepasa("AttachmentService::upload - Novo mime_type: {$mimeType}");
                Logger::quepasa("AttachmentService::upload - Nova extensão: {$extension}");
            } else {
                Logger::quepasa("AttachmentService::upload - ❌ CONVERSÃO FALHOU: " . $conversion['error']);
                Logger::quepasa("AttachmentService::upload - Mantendo arquivo original: {$filepath}");
            }
        } else {
            Logger::quepasa("AttachmentService::upload - Não é áudio webm, pulando conversão");
        }

        // Ajuste: se identificamos como áudio mas mime veio como video/webm, alinhar para audio/webm
        if ($fileType === 'audio' && str_contains($mimeType, 'video/webm')) {
            $mimeType = 'audio/webm';
            Logger::quepasa("AttachmentService::upload - Ajustando mime_type de video/webm para audio/webm (arquivo marcado como áudio)");
        }

        // Retornar informações do arquivo (considerando possível conversão)
        $result = [
            'filename' => $filename,
            'original_name' => $file['name'],
            'path' => 'assets/media/attachments/' . $conversationId . '/' . $filename,
            'url' => \App\Helpers\Url::to('assets/media/attachments/' . $conversationId . '/' . $filename),
            'type' => $fileType, // Pode ter sido atualizado para 'audio' se era 'video' mas era apenas áudio
            'mime_type' => $mimeType, // Pode ter sido atualizado para 'audio/ogg; codecs=opus' após conversão
            'size' => $file['size'],
            'extension' => $extension // Pode ter sido atualizado para 'ogg' após conversão
        ];
        
        Logger::quepasa("AttachmentService::upload - Retornando informações do arquivo:");
        Logger::quepasa("AttachmentService::upload -   type: {$result['type']}");
        Logger::quepasa("AttachmentService::upload -   mime_type: {$result['mime_type']}");
        Logger::quepasa("AttachmentService::upload -   extension: {$result['extension']}");
        
        return $result;
    }

    /**
     * Obter tipo MIME do arquivo
     */
    private static function getMimeType(string $filepath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filepath);
            finfo_close($finfo);
            return $mimeType ?: 'application/octet-stream';
        }
        
        // Fallback
        return mime_content_type($filepath) ?: 'application/octet-stream';
    }

    /**
     * Determinar tipo de arquivo (image, video, audio, document)
     */
    private static function getFileType(string $extension, string $mimeType): ?string
    {
        foreach (self::$allowedTypes as $type => $extensions) {
            if (in_array($extension, $extensions)) {
                $mimePrefix = explode('/', $mimeType)[0];
                
                // Caso especial: webm gravado como áudio (audio/webm)
                if ($extension === 'webm' && $mimePrefix === 'audio') {
                    return 'audio';
                }

                if ($type === 'image' && $mimePrefix === 'image') return 'image';
                if ($type === 'video' && $mimePrefix === 'video') return 'video';
                if ($type === 'audio' && $mimePrefix === 'audio') return 'audio';
                if ($type === 'document') return 'document';
            }
        }
        
        return null;
    }

    /**
     * Salvar anexo de URL (para WhatsApp)
     */
    public static function saveFromUrl(string $url, int $conversationId, ?string $originalName = null): array
    {
        // Baixar arquivo
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $fileContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($fileContent)) {
            throw new \Exception('Erro ao baixar arquivo da URL');
        }

        // Criar diretório
        $conversationDir = self::$uploadDir . $conversationId . '/';
        if (!is_dir($conversationDir)) {
            mkdir($conversationDir, 0755, true);
        }

        // Determinar extensão
        $extension = self::getExtensionFromMimeType($contentType);
        if (!$extension) {
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'bin';
        }

        // Gerar nome único
        $filename = uniqid('whatsapp_', true) . '_' . time() . '.' . $extension;
        $filepath = $conversationDir . $filename;

        // Salvar arquivo
        if (file_put_contents($filepath, $fileContent) === false) {
            throw new \Exception('Erro ao salvar arquivo');
        }

        $fileType = self::getFileType($extension, $contentType);
        
        return [
            'filename' => $filename,
            'original_name' => $originalName ?: $filename,
            'path' => 'assets/media/attachments/' . $conversationId . '/' . $filename,
            'url' => \App\Helpers\Url::to('assets/media/attachments/' . $conversationId . '/' . $filename),
            'type' => $fileType ?: 'document',
            'mime_type' => $contentType,
            'size' => strlen($fileContent),
            'extension' => $extension
        ];
    }

    /**
     * Obter extensão a partir do MIME type
     */
    private static function getExtensionFromMimeType(string $mimeType): ?string
    {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'audio/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/ogg; codecs=opus' => 'ogg',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt'
        ];
        
        return $mimeMap[$mimeType] ?? null;
    }

    /**
     * Verificar se arquivo WebM contém apenas áudio (sem stream de vídeo)
     */
    private static function isWebmAudioOnly(string $filepath): bool
    {
        Logger::quepasa("AttachmentService::isWebmAudioOnly - Verificando se {$filepath} é apenas áudio...");
        
        // Verificar se arquivo existe
        if (!file_exists($filepath)) {
            Logger::quepasa("AttachmentService::isWebmAudioOnly - Arquivo não existe, assumindo que é vídeo");
            return false;
        }

        // Verificar se funções de execução estão disponíveis
        $execAvailable = self::areExecFunctionsAvailable();
        if (!$execAvailable) {
            // Sem shell_exec/exec, não dá para usar ffprobe, mas ainda podemos tentar heurística
            Logger::quepasa("AttachmentService::isWebmAudioOnly - shell_exec/exec desabilitadas; pulando ffprobe e usando heurística");
        }
        
        if ($execAvailable) {
            // Método 1: Verificar se tem ffprobe disponível (mais preciso)
            $ffprobePath = trim((string) shell_exec('command -v ffprobe 2>/dev/null'));
            if (empty($ffprobePath)) {
                $possiblePaths = ['ffprobe', '/usr/bin/ffprobe', '/usr/local/bin/ffprobe', 'C:\\ffmpeg\\bin\\ffprobe.exe'];
                foreach ($possiblePaths as $path) {
                    $test = shell_exec("which {$path} 2>/dev/null");
                    if ($test || file_exists($path)) {
                        $ffprobePath = $path;
                        Logger::quepasa("AttachmentService::isWebmAudioOnly - ffprobe encontrado em: {$ffprobePath}");
                        break;
                    }
                }
            }
            
            if (!empty($ffprobePath)) {
                Logger::quepasa("AttachmentService::isWebmAudioOnly - Usando ffprobe para verificar streams...");
                $cmd = escapeshellcmd($ffprobePath) . ' -v error -select_streams v:0 -show_entries stream=codec_type -of json ' . escapeshellarg($filepath) . ' 2>&1';
                
                // Usar método mais simples e rápido (sem JSON para evitar parsing lento)
                $cmd2 = escapeshellcmd($ffprobePath) . ' -v error -select_streams v:0 -show_entries stream=codec_type -of default=noprint_wrappers=1 ' . escapeshellarg($filepath) . ' 2>&1';
                $output2 = shell_exec($cmd2);
                
                Logger::quepasa("AttachmentService::isWebmAudioOnly - ffprobe output: " . substr($output2 ?? 'VAZIO', 0, 200));
                
                // Se não encontrou stream de vídeo (output vazio ou sem codec_type=video), é apenas áudio
                if (empty(trim($output2 ?? '')) || strpos($output2 ?? '', 'codec_type=video') === false) {
                    Logger::quepasa("AttachmentService::isWebmAudioOnly - ✅ Nenhum stream de vídeo encontrado - é apenas áudio");
                    return true;
                } else {
                    Logger::quepasa("AttachmentService::isWebmAudioOnly - ❌ Stream de vídeo encontrado - é vídeo");
                    return false;
                }
            }
        }
        
        // Método 2: Heurística baseada em tamanho/nome (fallback rápido)
        // Arquivos de áudio gravados geralmente são menores que vídeos
        $filename = basename($filepath);
        $size = filesize($filepath);
        
        Logger::quepasa("AttachmentService::isWebmAudioOnly - ffprobe não disponível, usando heurística: filename={$filename}, size={$size} bytes");
        
        // Se nome contém indicadores de áudio
        if (stripos($filename, 'audio') !== false || stripos($filename, 'record') !== false || stripos($filename, 'msg_') !== false) {
            Logger::quepasa("AttachmentService::isWebmAudioOnly - Nome sugere áudio (contém 'audio', 'record' ou 'msg_'), assumindo que é apenas áudio");
            return true;
        }
        
        // Se tamanho é pequeno (< 5MB), provavelmente é áudio
        // Vídeos geralmente são maiores
        if ($size < 5 * 1024 * 1024) {
            Logger::quepasa("AttachmentService::isWebmAudioOnly - Tamanho pequeno ({$size} bytes < 5MB), assumindo que é apenas áudio");
            return true;
        }
        
        Logger::quepasa("AttachmentService::isWebmAudioOnly - Não foi possível determinar, assumindo que é vídeo");
        return false;
    }

    /**
     * Converter áudio WebM para OGG/Opus (compatibilidade com player nativo do WhatsApp)
     */
    private static function convertWebmToOpus(string $sourcePath, string $conversationDir): array
    {
        Logger::quepasa("AttachmentService::convertWebmToOpus - INICIANDO conversão");
        Logger::quepasa("AttachmentService::convertWebmToOpus - Arquivo origem: {$sourcePath}");
        Logger::quepasa("AttachmentService::convertWebmToOpus - Arquivo existe: " . (file_exists($sourcePath) ? 'SIM' : 'NÃO'));
        Logger::quepasa("AttachmentService::convertWebmToOpus - Tamanho origem: " . (file_exists($sourcePath) ? filesize($sourcePath) : 0) . " bytes");

        // Verificar se funções de execução estão disponíveis
        if (!self::areExecFunctionsAvailable()) {
            Logger::quepasa("AttachmentService::convertWebmToOpus - ❌ shell_exec/exec desabilitadas; não é possível converter áudio");
            return ['success' => false, 'error' => 'funções de shell desabilitadas (shell_exec/exec)'];
        }
        
        $targetFilename = uniqid('msg_', true) . '_' . time() . '.ogg';
        $targetPath = $conversationDir . $targetFilename;
        
        Logger::quepasa("AttachmentService::convertWebmToOpus - Arquivo destino: {$targetPath}");

        // Verificar ffmpeg
        Logger::quepasa("AttachmentService::convertWebmToOpus - Verificando se ffmpeg está disponível...");
        $ffmpegPath = trim((string) shell_exec('command -v ffmpeg'));
        
        if (empty($ffmpegPath)) {
            // Tentar outros caminhos comuns
            $possiblePaths = ['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'C:\\ffmpeg\\bin\\ffmpeg.exe'];
            foreach ($possiblePaths as $path) {
                if (file_exists($path) || shell_exec("which {$path} 2>/dev/null")) {
                    $ffmpegPath = $path;
                    Logger::quepasa("AttachmentService::convertWebmToOpus - ffmpeg encontrado em: {$ffmpegPath}");
                    break;
                }
            }
        }
        
        if (empty($ffmpegPath)) {
            Logger::quepasa("AttachmentService::convertWebmToOpus - ❌ ffmpeg NÃO encontrado no PATH");
            Logger::quepasa("AttachmentService::convertWebmToOpus - Tentando executar 'ffmpeg -version' para verificar...");
            $versionCheck = shell_exec('ffmpeg -version 2>&1');
            Logger::quepasa("AttachmentService::convertWebmToOpus - Resultado 'ffmpeg -version': " . substr($versionCheck ?? 'NENHUM RESULTADO', 0, 200));
            return ['success' => false, 'error' => 'ffmpeg não encontrado no PATH'];
        }
        
        Logger::quepasa("AttachmentService::convertWebmToOpus - ✅ ffmpeg encontrado: {$ffmpegPath}");
        
        // Verificar versão do ffmpeg
        $versionOutput = shell_exec(escapeshellcmd($ffmpegPath) . ' -version 2>&1');
        Logger::quepasa("AttachmentService::convertWebmToOpus - Versão ffmpeg: " . substr($versionOutput ?? 'NÃO DISPONÍVEL', 0, 100));

        $cmd = escapeshellcmd($ffmpegPath) . ' -y -i ' . escapeshellarg($sourcePath) . ' -c:a libopus -b:a 96k -vn ' . escapeshellarg($targetPath) . ' 2>&1';
        Logger::quepasa("AttachmentService::convertWebmToOpus - Comando: {$cmd}");
        
        Logger::quepasa("AttachmentService::convertWebmToOpus - Executando conversão...");
        exec($cmd, $output, $exitCode);
        
        Logger::quepasa("AttachmentService::convertWebmToOpus - Exit code: {$exitCode}");
        Logger::quepasa("AttachmentService::convertWebmToOpus - Output: " . implode("\n", $output));

        if ($exitCode !== 0) {
            Logger::quepasa("AttachmentService::convertWebmToOpus - ❌ ffmpeg retornou erro (exit code: {$exitCode})");
            return ['success' => false, 'error' => 'ffmpeg falhou (exit code ' . $exitCode . '): ' . implode("\n", array_slice($output, 0, 10))];
        }
        
        if (!file_exists($targetPath)) {
            Logger::quepasa("AttachmentService::convertWebmToOpus - ❌ Arquivo destino NÃO foi criado: {$targetPath}");
            return ['success' => false, 'error' => 'Arquivo destino não foi criado'];
        }
        
        $targetSize = filesize($targetPath);
        if ($targetSize === 0) {
            Logger::quepasa("AttachmentService::convertWebmToOpus - ❌ Arquivo destino está VAZIO (0 bytes)");
            @unlink($targetPath);
            return ['success' => false, 'error' => 'Arquivo destino está vazio'];
        }
        
        Logger::quepasa("AttachmentService::convertWebmToOpus - ✅ Arquivo convertido criado: {$targetPath} ({$targetSize} bytes)");

        // Remover original
        Logger::quepasa("AttachmentService::convertWebmToOpus - Removendo arquivo original: {$sourcePath}");
        @unlink($sourcePath);

        Logger::quepasa("AttachmentService::convertWebmToOpus - ✅ CONVERSÃO CONCLUÍDA COM SUCESSO!");
        
        return [
            'success' => true,
            'filename' => $targetFilename,
            'filepath' => $targetPath,
            'mime_type' => 'audio/ogg; codecs=opus',
            'size' => $targetSize
        ];
    }

    /**
     * Verificar se shell_exec/exec estão disponíveis e não desabilitadas
     */
    private static function areExecFunctionsAvailable(): bool
    {
        if (!function_exists('shell_exec') || !function_exists('exec')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $disabled = array_filter($disabled);

        if (in_array('shell_exec', $disabled, true) || in_array('exec', $disabled, true)) {
            return false;
        }

        return true;
    }

    /**
     * Deletar anexo
     */
    public static function delete(string $path): bool
    {
        $fullPath = __DIR__ . '/../../public/' . $path;
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }

    /**
     * Obter informações do anexo
     */
    public static function getInfo(string $path): ?array
    {
        $fullPath = __DIR__ . '/../../public/' . $path;
        if (!file_exists($fullPath)) {
            return null;
        }

        return [
            'path' => $path,
            'url' => \App\Helpers\Url::to($path),
            'size' => filesize($fullPath),
            'mime_type' => self::getMimeType($fullPath),
            'exists' => true
        ];
    }

    /**
     * Validar arquivo antes do upload
     */
    public static function validateFile(array $file): array
    {
        $errors = [];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Erro ao fazer upload do arquivo';
            return $errors;
        }

        if ($file['size'] > self::$maxFileSize) {
            $errors[] = 'Arquivo muito grande. Tamanho máximo: ' . (self::$maxFileSize / 1024 / 1024) . 'MB';
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mimeType = self::getMimeType($file['tmp_name']);
        $fileType = self::getFileType($extension, $mimeType);

        if (!$fileType) {
            $errors[] = 'Tipo de arquivo não permitido';
        }

        return $errors;
    }
}

