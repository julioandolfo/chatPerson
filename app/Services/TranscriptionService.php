<?php
/**
 * Service TranscriptionService
 * Transcrição de áudio usando OpenAI Whisper API
 */

namespace App\Services;

use App\Models\Setting;
use App\Helpers\Logger;
use App\Services\ConversationSettingsService;

class TranscriptionService
{
    const API_URL = 'https://api.openai.com/v1/audio/transcriptions';
    const MAX_FILE_SIZE = 25 * 1024 * 1024; // 25MB (limite da OpenAI)
    const SUPPORTED_FORMATS = ['mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'wav', 'webm'];
    const MAX_RETRIES = 3;
    const RETRY_DELAY = 1; // segundos

    /**
     * Obter API Key das configurações
     */
    private static function getApiKey(): ?string
    {
        $apiKey = Setting::get('openai_api_key');
        if (empty($apiKey)) {
            // Tentar variável de ambiente como fallback
            $apiKey = getenv('OPENAI_API_KEY') ?: null;
        }
        return $apiKey;
    }

    /**
     * Verificar se arquivo de áudio é suportado
     */
    public static function isSupportedFormat(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, self::SUPPORTED_FORMATS);
    }

    /**
     * Verificar tamanho do arquivo
     */
    public static function isValidSize(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }
        $size = filesize($filePath);
        return $size > 0 && $size <= self::MAX_FILE_SIZE;
    }

    /**
     * Transcrever áudio usando OpenAI Whisper
     * 
     * @param string $audioFilePath Caminho completo do arquivo de áudio
     * @param array $options Opções adicionais:
     *   - 'model' => 'whisper-1' (padrão)
     *   - 'language' => 'pt' (opcional, código ISO 639-1)
     *   - 'prompt' => string (opcional, contexto para melhorar transcrição)
     *   - 'response_format' => 'json' (padrão) ou 'text', 'srt', 'verbose_json', 'vtt'
     *   - 'temperature' => 0.0 (opcional, 0.0 a 1.0)
     * @return array ['success' => bool, 'text' => string, 'error' => string|null, 'cost' => float]
     */
    public static function transcribe(string $audioFilePath, array $options = []): array
    {
        $startTime = microtime(true);
        
        Logger::info("TranscriptionService::transcribe - Iniciando transcrição", [
            'file' => $audioFilePath,
            'size' => file_exists($audioFilePath) ? filesize($audioFilePath) : 0,
            'options' => $options
        ]);

        // Validar arquivo
        if (!file_exists($audioFilePath)) {
            Logger::error("TranscriptionService::transcribe - Arquivo não encontrado: {$audioFilePath}");
            return [
                'success' => false,
                'text' => '',
                'error' => 'Arquivo de áudio não encontrado',
                'cost' => 0.0
            ];
        }

        // Validar formato
        if (!self::isSupportedFormat($audioFilePath)) {
            $extension = pathinfo($audioFilePath, PATHINFO_EXTENSION);
            Logger::error("TranscriptionService::transcribe - Formato não suportado: {$extension}");
            return [
                'success' => false,
                'text' => '',
                'error' => "Formato de áudio não suportado: {$extension}",
                'cost' => 0.0
            ];
        }

        // Validar tamanho
        if (!self::isValidSize($audioFilePath)) {
            $size = filesize($audioFilePath);
            Logger::error("TranscriptionService::transcribe - Arquivo muito grande: {$size} bytes");
            return [
                'success' => false,
                'text' => '',
                'error' => 'Arquivo muito grande. Tamanho máximo: ' . (self::MAX_FILE_SIZE / 1024 / 1024) . 'MB',
                'cost' => 0.0
            ];
        }

        // Obter API Key
        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            Logger::error("TranscriptionService::transcribe - API Key não configurada");
            return [
                'success' => false,
                'text' => '',
                'error' => 'API Key da OpenAI não configurada',
                'cost' => 0.0
            ];
        }

        // Preparar opções
        $model = $options['model'] ?? 'whisper-1';
        $language = $options['language'] ?? 'pt'; // Português por padrão
        $responseFormat = $options['response_format'] ?? 'json';
        $prompt = $options['prompt'] ?? null;
        $temperature = isset($options['temperature']) ? (float)$options['temperature'] : null;

        // Preparar dados para multipart/form-data
        $postData = [
            'file' => new \CURLFile($audioFilePath),
            'model' => $model,
            'language' => $language,
            'response_format' => $responseFormat
        ];

        if ($prompt !== null) {
            $postData['prompt'] = $prompt;
        }

        if ($temperature !== null) {
            $postData['temperature'] = $temperature;
        }

        // Fazer requisição com retry
        $attempt = 0;
        $lastError = null;

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;
            
            try {
                $ch = curl_init(self::API_URL);
                
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $apiKey
                    ],
                    CURLOPT_POSTFIELDS => $postData,
                    CURLOPT_TIMEOUT => 300, // 5 minutos (áudios podem ser longos)
                    CURLOPT_CONNECTTIMEOUT => 30
                ]);

                Logger::info("TranscriptionService::transcribe - Enviando requisição (tentativa {$attempt}/" . self::MAX_RETRIES . ")");

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($curlError) {
                    throw new \Exception("Erro cURL: {$curlError}");
                }

                if ($httpCode !== 200) {
                    $errorData = json_decode($response, true);
                    $errorMessage = $errorData['error']['message'] ?? "HTTP {$httpCode}";
                    throw new \Exception("Erro da API: {$errorMessage}");
                }

                // Processar resposta
                $responseData = json_decode($response, true);
                
                if ($responseFormat === 'json' || $responseFormat === 'verbose_json') {
                    $text = $responseData['text'] ?? '';
                } else {
                    $text = $response; // text, srt, vtt retornam texto direto
                }

                // Calcular custo (Whisper: $0.006 por minuto)
                $duration = self::estimateAudioDuration($audioFilePath);
                $cost = ($duration / 60) * 0.006; // Converter segundos para minutos e multiplicar pelo preço

                $executionTime = microtime(true) - $startTime;

                Logger::info("TranscriptionService::transcribe - ✅ Transcrição bem-sucedida", [
                    'text_length' => strlen($text),
                    'duration_seconds' => $duration,
                    'cost' => $cost,
                    'execution_time' => round($executionTime, 2)
                ]);

                return [
                    'success' => true,
                    'text' => $text,
                    'error' => null,
                    'cost' => $cost,
                    'duration' => $duration,
                    'execution_time' => $executionTime
                ];

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Logger::error("TranscriptionService::transcribe - Erro na tentativa {$attempt}: {$lastError}");

                if ($attempt < self::MAX_RETRIES) {
                    sleep(self::RETRY_DELAY * $attempt); // Backoff exponencial
                }
            }
        }

        // Todas as tentativas falharam
        Logger::error("TranscriptionService::transcribe - ❌ Falha após " . self::MAX_RETRIES . " tentativas: {$lastError}");

        return [
            'success' => false,
            'text' => '',
            'error' => $lastError ?? 'Erro desconhecido na transcrição',
            'cost' => 0.0
        ];
    }

    /**
     * Estimar duração do áudio (aproximada)
     * Usa tamanho do arquivo e bitrate estimado
     */
    private static function estimateAudioDuration(string $filePath): float
    {
        // Tentar usar ffprobe se disponível (mais preciso)
        if (function_exists('shell_exec')) {
            $ffprobePath = self::findFFprobe();
            if ($ffprobePath) {
                $command = escapeshellarg($ffprobePath) . ' -v quiet -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($filePath);
                $output = @shell_exec($command);
                if ($output && is_numeric(trim($output))) {
                    return (float)trim($output);
                }
            }
        }

        // Fallback: estimativa baseada em tamanho
        // Assumir bitrate médio de 64kbps para áudio de voz
        $fileSize = filesize($filePath);
        $estimatedBitrate = 64 * 1024; // 64 kbps em bytes por segundo
        $duration = ($fileSize * 8) / ($estimatedBitrate * 8); // Converter bytes para bits e dividir por bitrate

        return max(1.0, round($duration, 2)); // Mínimo 1 segundo
    }

    /**
     * Encontrar caminho do ffprobe
     */
    private static function findFFprobe(): ?string
    {
        $possiblePaths = [
            '/usr/bin/ffprobe',
            '/usr/local/bin/ffprobe',
            'ffprobe', // No PATH
            'C:\\ffmpeg\\bin\\ffprobe.exe', // Windows comum
        ];

        foreach ($possiblePaths as $path) {
            if ($path === 'ffprobe' || file_exists($path)) {
                $test = @shell_exec(escapeshellarg($path) . ' -version 2>&1');
                if ($test && strpos($test, 'ffprobe') !== false) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * Verificar se transcrição está habilitada nas configurações
     */
    public static function isEnabled(): bool
    {
        $settings = ConversationSettingsService::getSettings();
        return !empty($settings['audio_transcription']['enabled']);
    }

    /**
     * Obter configurações de transcrição
     */
    public static function getSettings(): array
    {
        $settings = ConversationSettingsService::getSettings();
        $transcriptionSettings = $settings['audio_transcription'] ?? [];
        
        return array_merge([
            'enabled' => false,
            'auto_transcribe' => true, // Transcrever automaticamente quando áudio chega
            'only_for_ai_agents' => true, // Só transcrever se conversa tem agente de IA
            'language' => 'pt', // Português por padrão
            'model' => 'whisper-1',
            'update_message_content' => true, // Atualizar conteúdo da mensagem com texto transcrito
            'max_file_size_mb' => 25, // Limite de tamanho
            'cost_limit_per_day' => 10.00 // Limite de custo diário
        ], $transcriptionSettings);
    }
}

