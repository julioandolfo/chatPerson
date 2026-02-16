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
    private static int $maxFileSize = 10 * 1024 * 1024; // 10MB (default)
    private static array $maxFileSizes = [
        'image' => 16 * 1024 * 1024, // 16MB
        'video' => 200 * 1024 * 1024, // 200MB (aceita maiores pois serão comprimidos)
        'audio' => 16 * 1024 * 1024, // 16MB
        'document' => 100 * 1024 * 1024 // 100MB
    ];

    // Configurações de compressão de vídeo
    private static array $videoCompression = [
        'enabled' => true,
        'min_size_to_compress' => 5 * 1024 * 1024, // Comprimir vídeos maiores que 5MB
        'max_width' => 1280,                         // Resolução máxima largura
        'max_height' => 720,                          // Resolução máxima altura
        'crf' => 28,                                  // Qualidade (0-51, menor=melhor, 28=bom para chat)
        'preset' => 'fast',                           // Velocidade de encoding (ultrafast|fast|medium)
        'audio_bitrate' => '128k',                    // Bitrate do áudio
        'max_duration' => 300,                         // Duração máxima do vídeo em segundos (5 min), 0=sem limite
    ];
    private static array $allowedTypes = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'video' => ['mp4', 'webm', 'ogg', 'mov', 'm4v'],
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

        // Obter extensão
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mimeType = self::getMimeType($file['tmp_name']);

        // Validar tipo
        $fileType = self::getFileType($extension, $mimeType);
        if (!$fileType) {
            throw new \Exception('Tipo de arquivo não permitido');
        }

        // Validar tamanho (por tipo)
        $maxSize = self::$maxFileSizes[$fileType] ?? self::$maxFileSize;
        if ($file['size'] > $maxSize) {
            throw new \Exception('Arquivo muito grande. Tamanho máximo: ' . (int)($maxSize / 1024 / 1024) . 'MB');
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

        // ═══════════════════════════════════════════════════════════════
        // COMPRESSÃO DE VÍDEO: Reduzir tamanho/qualidade de vídeos grandes
        // ═══════════════════════════════════════════════════════════════
        if ($fileType === 'video' && self::$videoCompression['enabled']) {
            $originalSize = filesize($filepath);
            $minSize = self::$videoCompression['min_size_to_compress'];
            
            Logger::quepasa("AttachmentService::upload - Verificando compressão de vídeo: tamanho={$originalSize} bytes, mínimo para comprimir={$minSize} bytes");
            
            if ($originalSize > $minSize) {
                Logger::quepasa("AttachmentService::upload - 🎬 Vídeo acima do limite, iniciando compressão...");
                
                $compression = self::compressVideo($filepath, $conversationDir, $extension);
                
                if ($compression['success']) {
                    $oldSize = $originalSize;
                    $newSize = $compression['size'];
                    $reduction = round((1 - ($newSize / $oldSize)) * 100, 1);
                    
                    // Substituir informações do arquivo
                    $filename = $compression['filename'];
                    $filepath = $compression['filepath'];
                    $extension = $compression['extension'];
                    $mimeType = $compression['mime_type'];
                    $file['size'] = $newSize;
                    
                    Logger::quepasa("AttachmentService::upload - ✅ VÍDEO COMPRIMIDO! {$oldSize} → {$newSize} bytes (redução de {$reduction}%)");
                } else {
                    Logger::quepasa("AttachmentService::upload - ⚠️ Compressão falhou: " . $compression['error'] . " - mantendo vídeo original");
                }
            } else {
                Logger::quepasa("AttachmentService::upload - Vídeo pequeno ({$originalSize} bytes), sem necessidade de compressão");
            }
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
        $mimeType = strtolower(trim($mimeType));
        $mimePrefix = strtolower(explode('/', $mimeType)[0] ?? '');
        foreach (self::$allowedTypes as $type => $extensions) {
            if (in_array($extension, $extensions)) {
                // Caso especial: webm gravado como áudio (audio/webm)
                if ($extension === 'webm' && $mimePrefix === 'audio') {
                    return 'audio';
                }

                if ($type === 'image' && $mimePrefix === 'image') return 'image';
                if ($type === 'video' && $mimePrefix === 'video') return 'video';
                if ($type === 'audio' && $mimePrefix === 'audio') return 'audio';
                if ($type === 'document') return 'document';

                // Fallback: alguns uploads vêm como application/octet-stream
                if ($mimePrefix === 'application' && $mimeType === 'application/octet-stream') {
                    return $type;
                }
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

        // Determinar extensão: prioridade = nome original > URL path > Content-Type > fallback
        $extension = null;
        
        // 1. Tentar extensão do nome original (mais confiável para .cdr, .psd, etc.)
        if ($originalName) {
            $origExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!empty($origExt) && $origExt !== 'bin') {
                $extension = $origExt;
            }
        }
        
        // 2. Tentar extensão da URL
        if (!$extension) {
            $urlExt = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (!empty($urlExt) && $urlExt !== 'bin') {
                $extension = $urlExt;
            }
        }
        
        // 3. Tentar pelo Content-Type
        if (!$extension && $contentType) {
            $extension = self::getExtensionFromMimeType($contentType);
        }
        
        // 4. Fallback
        if (!$extension) {
            $extension = 'bin';
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
        // Limpar MIME type (remover parâmetros como charset, codecs, etc.)
        $cleanMime = strtolower(trim(explode(';', $mimeType)[0]));
        
        $mimeMap = [
            // Imagens
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'image/svg+xml' => 'svg',
            'image/x-icon' => 'ico',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            // Vídeos
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'video/x-m4v' => 'm4v',
            'video/x-msvideo' => 'avi',
            'video/x-ms-wmv' => 'wmv',
            'video/x-matroska' => 'mkv',
            'video/3gpp' => '3gp',
            // Áudio
            'audio/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/x-m4a' => 'm4a',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/opus' => 'ogg',
            'audio/aac' => 'aac',
            'audio/flac' => 'flac',
            'audio/amr' => 'amr',
            // Documentos
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/vnd.oasis.opendocument.text' => 'odt',
            'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
            'application/rtf' => 'rtf',
            // Texto
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'text/html' => 'html',
            'application/json' => 'json',
            'application/xml' => 'xml',
            // Compactados
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
            'application/x-rar-compressed' => 'rar',
            'application/vnd.rar' => 'rar',
            'application/x-7z-compressed' => '7z',
            'application/gzip' => 'gz',
            // Design / Gráficos
            'application/postscript' => 'ai',
            'application/x-photoshop' => 'psd',
            'image/vnd.adobe.photoshop' => 'psd',
            'application/x-coreldraw' => 'cdr',
            'application/cdr' => 'cdr',
            'image/x-coreldraw' => 'cdr',
            'application/x-cdr' => 'cdr',
            'image/vnd.dwg' => 'dwg',
            'application/dwg' => 'dwg',
        ];
        
        return $mimeMap[$cleanMime] ?? null;
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
            $ffprobePath = self::findFfprobe();
            
            if (!empty($ffprobePath)) {
                Logger::quepasa("AttachmentService::isWebmAudioOnly - Usando ffprobe para verificar streams...");
                
                // Usar método mais simples e rápido (sem JSON para evitar parsing lento)
                $cmd2 = escapeshellarg($ffprobePath) . ' -v error -select_streams v:0 -show_entries stream=codec_type -of default=noprint_wrappers=1 ' . escapeshellarg($filepath) . ' 2>&1';
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

        // Usar método centralizado para encontrar ffmpeg
        $ffmpegPath = self::findFfmpeg();
        
        if (!$ffmpegPath) {
            Logger::quepasa("AttachmentService::convertWebmToOpus - ❌ ffmpeg NÃO encontrado");
            return ['success' => false, 'error' => 'ffmpeg não encontrado no sistema'];
        }
        
        Logger::quepasa("AttachmentService::convertWebmToOpus - ✅ ffmpeg encontrado: {$ffmpegPath}");

        $cmd = escapeshellarg($ffmpegPath) . ' -y -i ' . escapeshellarg($sourcePath) . ' -c:a libopus -b:a 96k -vn ' . escapeshellarg($targetPath) . ' 2>&1';
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
     * Encontrar o caminho do ffmpeg no sistema
     */
    private static function findFfmpeg(): ?string
    {
        if (!self::areExecFunctionsAvailable()) {
            return null;
        }

        // Verificar se existe um ffmpeg local no projeto (bin/ffmpeg)
        $localPaths = [
            __DIR__ . '/../../bin/ffmpeg.exe',
            __DIR__ . '/../../bin/ffmpeg',
        ];
        foreach ($localPaths as $path) {
            if (file_exists($path)) {
                Logger::quepasa("AttachmentService::findFfmpeg - Encontrado ffmpeg local: {$path}");
                return $path;
            }
        }

        // Verificar no PATH do sistema
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        if ($isWindows) {
            $output = shell_exec('where ffmpeg 2>nul');
            if (!empty(trim($output ?? ''))) {
                $path = trim(explode("\n", $output)[0]);
                Logger::quepasa("AttachmentService::findFfmpeg - Encontrado no PATH: {$path}");
                return $path;
            }
        } else {
            $output = shell_exec('command -v ffmpeg 2>/dev/null');
            if (!empty(trim($output ?? ''))) {
                $path = trim($output);
                Logger::quepasa("AttachmentService::findFfmpeg - Encontrado no PATH: {$path}");
                return $path;
            }
        }

        // Caminhos comuns
        $commonPaths = $isWindows ? [
            'C:\\ffmpeg\\bin\\ffmpeg.exe',
            'C:\\laragon\\bin\\ffmpeg\\ffmpeg.exe',
            'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe',
        ] : [
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
        ];

        // No Windows, buscar em pastas do WinGet (ffmpeg instalado via winget)
        if ($isWindows) {
            $wingetBase = getenv('LOCALAPPDATA') . '\\Microsoft\\WinGet\\Packages';
            if (is_dir($wingetBase)) {
                $dirs = glob($wingetBase . '\\Gyan.FFmpeg*', GLOB_ONLYDIR);
                foreach ($dirs as $dir) {
                    // Buscar recursivamente por ffmpeg.exe dentro da pasta do pacote
                    $binDirs = glob($dir . '\\*\\bin\\ffmpeg.exe');
                    foreach ($binDirs as $binPath) {
                        $commonPaths[] = $binPath;
                    }
                }
            }
        }

        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                Logger::quepasa("AttachmentService::findFfmpeg - Encontrado em caminho comum: {$path}");
                return $path;
            }
        }

        Logger::quepasa("AttachmentService::findFfmpeg - ffmpeg NÃO encontrado no sistema");
        return null;
    }

    /**
     * Encontrar o caminho do ffprobe no sistema
     */
    private static function findFfprobe(): ?string
    {
        if (!self::areExecFunctionsAvailable()) {
            return null;
        }

        // Se encontrou ffmpeg, ffprobe geralmente está no mesmo diretório
        $ffmpegPath = self::findFfmpeg();
        if ($ffmpegPath) {
            $dir = dirname($ffmpegPath);
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $ffprobePath = $dir . DIRECTORY_SEPARATOR . 'ffprobe' . ($isWindows ? '.exe' : '');
            if (file_exists($ffprobePath)) {
                return $ffprobePath;
            }
        }

        return null;
    }

    /**
     * Obter informações do vídeo usando ffprobe
     */
    private static function getVideoInfo(string $filepath): ?array
    {
        $ffprobePath = self::findFfprobe();
        if (!$ffprobePath) {
            // Tentar ffprobe diretamente
            $ffprobePath = 'ffprobe';
        }

        $cmd = escapeshellarg($ffprobePath) . ' -v error -select_streams v:0 -show_entries stream=width,height,duration,codec_name -show_entries format=duration,size -of json ' . escapeshellarg($filepath) . ' 2>&1';
        
        $output = shell_exec($cmd);
        if (empty($output)) {
            return null;
        }

        $data = json_decode($output, true);
        if (!$data) {
            return null;
        }

        $info = [
            'width' => 0,
            'height' => 0,
            'duration' => 0,
            'size' => filesize($filepath),
        ];

        // Streams de vídeo
        if (!empty($data['streams'])) {
            $stream = $data['streams'][0];
            $info['width'] = (int)($stream['width'] ?? 0);
            $info['height'] = (int)($stream['height'] ?? 0);
            if (!empty($stream['duration'])) {
                $info['duration'] = (float)$stream['duration'];
            }
        }

        // Formato (duração total)
        if (!empty($data['format']['duration'])) {
            $info['duration'] = (float)$data['format']['duration'];
        }

        return $info;
    }

    /**
     * Comprimir vídeo usando FFmpeg para reduzir tamanho
     * 
     * Reduz resolução para max 1280x720, aplica CRF 28, codec H.264/AAC
     * Ideal para vídeos de chat onde qualidade ultra-alta não é necessária
     */
    private static function compressVideo(string $sourcePath, string $conversationDir, string $originalExtension): array
    {
        Logger::quepasa("AttachmentService::compressVideo - INICIANDO compressão de vídeo");
        Logger::quepasa("AttachmentService::compressVideo - Arquivo origem: {$sourcePath}");
        Logger::quepasa("AttachmentService::compressVideo - Tamanho original: " . filesize($sourcePath) . " bytes");

        // Verificar se funções de execução estão disponíveis
        if (!self::areExecFunctionsAvailable()) {
            return ['success' => false, 'error' => 'Funções de shell (exec/shell_exec) desabilitadas'];
        }

        // Encontrar ffmpeg
        $ffmpegPath = self::findFfmpeg();
        if (!$ffmpegPath) {
            return ['success' => false, 'error' => 'FFmpeg não encontrado. Instale o FFmpeg para habilitar compressão de vídeo.'];
        }

        Logger::quepasa("AttachmentService::compressVideo - FFmpeg encontrado: {$ffmpegPath}");

        // Obter informações do vídeo original (opcional, para log)
        $videoInfo = self::getVideoInfo($sourcePath);
        if ($videoInfo) {
            Logger::quepasa("AttachmentService::compressVideo - Vídeo original: {$videoInfo['width']}x{$videoInfo['height']}, duração: {$videoInfo['duration']}s");
            
            // Verificar duração máxima
            $maxDuration = self::$videoCompression['max_duration'];
            if ($maxDuration > 0 && $videoInfo['duration'] > $maxDuration) {
                Logger::quepasa("AttachmentService::compressVideo - ⚠️ Vídeo muito longo ({$videoInfo['duration']}s > {$maxDuration}s)");
                // Não bloqueia, apenas avisa - a compressão ainda será feita
            }
        }

        // Configurações de compressão
        $maxW = self::$videoCompression['max_width'];
        $maxH = self::$videoCompression['max_height'];
        $crf = self::$videoCompression['crf'];
        $preset = self::$videoCompression['preset'];
        $audioBitrate = self::$videoCompression['audio_bitrate'];

        // Arquivo de saída (sempre mp4 para máxima compatibilidade)
        $targetFilename = uniqid('msg_', true) . '_' . time() . '.mp4';
        $targetPath = $conversationDir . $targetFilename;

        // Verificar se precisa redimensionar
        $needsResize = true;
        if ($videoInfo && $videoInfo['width'] > 0) {
            if ($videoInfo['width'] <= $maxW && $videoInfo['height'] <= $maxH) {
                $needsResize = false;
                Logger::quepasa("AttachmentService::compressVideo - Resolução já dentro do limite, apenas recodificar");
            }
        }

        // Montar comando ffmpeg
        $cmd = escapeshellarg($ffmpegPath) . ' -y -i ' . escapeshellarg($sourcePath);
        
        // Codec de vídeo H.264
        $cmd .= ' -c:v libx264';
        $cmd .= ' -crf ' . (int)$crf;
        $cmd .= ' -preset ' . escapeshellarg($preset);
        
        // Filtro de escala: reduz para max WxH mantendo proporção + arredonda para par (necessário para H.264)
        if ($needsResize) {
            $cmd .= ' -vf scale=' . (int)$maxW . ':' . (int)$maxH . ':force_original_aspect_ratio=decrease,pad=ceil(iw/2)*2:ceil(ih/2)*2';
        } else {
            $cmd .= ' -vf pad=ceil(iw/2)*2:ceil(ih/2)*2';
        }
        
        // Codec de áudio AAC
        $cmd .= ' -c:a aac -b:a ' . escapeshellarg($audioBitrate);
        
        // Otimizações
        $cmd .= ' -movflags +faststart'; // Permite streaming progressivo
        $cmd .= ' -pix_fmt yuv420p';     // Máxima compatibilidade
        $cmd .= ' -max_muxing_queue_size 9999'; // Evitar erro com vídeos complexos
        
        $cmd .= ' ' . escapeshellarg($targetPath) . ' 2>&1';

        Logger::quepasa("AttachmentService::compressVideo - Comando: {$cmd}");
        Logger::quepasa("AttachmentService::compressVideo - Executando compressão (pode demorar)...");
        
        $startTime = microtime(true);
        exec($cmd, $output, $exitCode);
        $elapsed = round(microtime(true) - $startTime, 2);
        
        Logger::quepasa("AttachmentService::compressVideo - Execução concluída em {$elapsed}s, exit code: {$exitCode}");
        
        if (!empty($output)) {
            // Logar apenas as últimas linhas do output para não poluir
            $lastLines = array_slice($output, -5);
            Logger::quepasa("AttachmentService::compressVideo - Últimas linhas output: " . implode(" | ", $lastLines));
        }

        // Verificar resultado
        if ($exitCode !== 0) {
            Logger::quepasa("AttachmentService::compressVideo - ❌ FFmpeg retornou erro (exit code: {$exitCode})");
            // Limpar arquivo parcial se existir
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }
            return [
                'success' => false,
                'error' => 'FFmpeg falhou (exit code ' . $exitCode . '): ' . implode("\n", array_slice($output, -3))
            ];
        }

        if (!file_exists($targetPath)) {
            Logger::quepasa("AttachmentService::compressVideo - ❌ Arquivo de saída não foi criado");
            return ['success' => false, 'error' => 'Arquivo comprimido não foi gerado'];
        }

        $targetSize = filesize($targetPath);
        $originalSize = filesize($sourcePath);
        
        if ($targetSize === 0) {
            Logger::quepasa("AttachmentService::compressVideo - ❌ Arquivo de saída está vazio");
            @unlink($targetPath);
            return ['success' => false, 'error' => 'Arquivo comprimido está vazio'];
        }

        // Verificar se a compressão realmente reduziu o tamanho
        // Se o arquivo comprimido for MAIOR que o original, manter o original
        if ($targetSize >= $originalSize) {
            Logger::quepasa("AttachmentService::compressVideo - ⚠️ Comprimido ({$targetSize}) >= Original ({$originalSize}), descartando compressão");
            @unlink($targetPath);
            
            // Se o original não é mp4, converter apenas o container
            if (strtolower($originalExtension) !== 'mp4') {
                return self::convertVideoToMp4($sourcePath, $conversationDir);
            }
            
            return ['success' => false, 'error' => 'Compressão não reduziu o tamanho do arquivo'];
        }

        $reduction = round((1 - ($targetSize / $originalSize)) * 100, 1);
        Logger::quepasa("AttachmentService::compressVideo - ✅ COMPRESSÃO BEM-SUCEDIDA!");
        Logger::quepasa("AttachmentService::compressVideo - Original: " . self::formatBytes($originalSize) . " → Comprimido: " . self::formatBytes($targetSize) . " (redução de {$reduction}%)");
        Logger::quepasa("AttachmentService::compressVideo - Tempo de compressão: {$elapsed}s");

        // Remover arquivo original
        @unlink($sourcePath);

        return [
            'success' => true,
            'filename' => $targetFilename,
            'filepath' => $targetPath,
            'extension' => 'mp4',
            'mime_type' => 'video/mp4',
            'size' => $targetSize,
            'original_size' => $originalSize,
            'reduction_percent' => $reduction,
            'compression_time' => $elapsed
        ];
    }

    /**
     * Converter vídeo para MP4 sem recomprimir (apenas mudar container)
     */
    private static function convertVideoToMp4(string $sourcePath, string $conversationDir): array
    {
        $ffmpegPath = self::findFfmpeg();
        if (!$ffmpegPath) {
            return ['success' => false, 'error' => 'FFmpeg não encontrado'];
        }

        $targetFilename = uniqid('msg_', true) . '_' . time() . '.mp4';
        $targetPath = $conversationDir . $targetFilename;

        // Copiar streams sem recodificar (rápido)
        $cmd = escapeshellarg($ffmpegPath) . ' -y -i ' . escapeshellarg($sourcePath)
            . ' -c copy -movflags +faststart ' . escapeshellarg($targetPath) . ' 2>&1';

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($targetPath) || filesize($targetPath) === 0) {
            if (file_exists($targetPath)) @unlink($targetPath);
            return ['success' => false, 'error' => 'Falha ao converter container para MP4'];
        }

        $targetSize = filesize($targetPath);
        @unlink($sourcePath);

        return [
            'success' => true,
            'filename' => $targetFilename,
            'filepath' => $targetPath,
            'extension' => 'mp4',
            'mime_type' => 'video/mp4',
            'size' => $targetSize,
        ];
    }

    /**
     * Formatar bytes para exibição legível
     */
    private static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
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

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mimeType = self::getMimeType($file['tmp_name']);
        $fileType = self::getFileType($extension, $mimeType);

        if (!$fileType) {
            $errors[] = 'Tipo de arquivo não permitido';
            return $errors;
        }

        $maxSize = self::$maxFileSizes[$fileType] ?? self::$maxFileSize;
        if ($file['size'] > $maxSize) {
            $errors[] = 'Arquivo muito grande. Tamanho máximo: ' . (int)($maxSize / 1024 / 1024) . 'MB';
        }

        return $errors;
    }
}

