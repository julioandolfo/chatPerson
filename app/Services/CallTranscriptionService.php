<?php
/**
 * Service CallTranscriptionService
 * Transcrição de áudio de chamadas usando OpenAI Whisper
 */

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Logger;

class CallTranscriptionService
{
    private static string $whisperEndpoint = 'https://api.openai.com/v1/audio/transcriptions';
    private static string $model = 'whisper-1';
    
    // Custo aproximado do Whisper: $0.006 por minuto
    private static float $costPerMinute = 0.006;

    /**
     * Obter API Key do OpenAI
     */
    private static function getApiKey(): ?string
    {
        // Tentar buscar do banco de dados primeiro
        $setting = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'openai_api_key' LIMIT 1");
        if (!empty($setting['value'])) {
            return $setting['value'];
        }
        
        // Fallback para variável de ambiente
        return $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: null;
    }

    /**
     * Transcrever áudio de uma URL
     * 
     * @param string $audioUrl URL do arquivo de áudio (MP3, WAV, etc)
     * @param string $language Idioma esperado (pt, en, es, etc)
     * @return array ['success' => bool, 'transcription' => string, 'language' => string, 'duration' => int, 'cost' => float]
     */
    public static function transcribe(string $audioUrl, string $language = 'pt'): array
    {
        $startTime = microtime(true);
        
        Logger::api4com("CallTranscriptionService - Iniciando transcrição: {$audioUrl}");
        
        $apiKey = self::getApiKey();
        if (!$apiKey) {
            Logger::api4com("CallTranscriptionService - API Key do OpenAI não configurada", 'ERROR');
            return [
                'success' => false,
                'error' => 'API Key do OpenAI não configurada'
            ];
        }

        try {
            // Baixar o arquivo de áudio
            $tempFile = self::downloadAudio($audioUrl);
            if (!$tempFile) {
                return [
                    'success' => false,
                    'error' => 'Falha ao baixar arquivo de áudio'
                ];
            }

            // Obter duração do áudio (se possível)
            $duration = self::getAudioDuration($tempFile);

            // Preparar dados para o Whisper
            $postFields = [
                'file' => new \CURLFile($tempFile, 'audio/mpeg', 'audio.mp3'),
                'model' => self::$model,
                'language' => $language,
                'response_format' => 'json'
            ];

            // Chamar API do Whisper
            $ch = curl_init(self::$whisperEndpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey
                ],
                CURLOPT_TIMEOUT => 120 // 2 minutos de timeout
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            // Remover arquivo temporário
            @unlink($tempFile);

            if ($error) {
                Logger::api4com("CallTranscriptionService - Erro cURL: {$error}", 'ERROR');
                return [
                    'success' => false,
                    'error' => 'Erro de conexão: ' . $error
                ];
            }

            if ($httpCode !== 200) {
                Logger::api4com("CallTranscriptionService - HTTP {$httpCode}: {$response}", 'ERROR');
                return [
                    'success' => false,
                    'error' => "HTTP {$httpCode}: " . substr($response, 0, 200)
                ];
            }

            $data = json_decode($response, true);
            if (!isset($data['text'])) {
                Logger::api4com("CallTranscriptionService - Resposta inválida: {$response}", 'ERROR');
                return [
                    'success' => false,
                    'error' => 'Resposta inválida do Whisper'
                ];
            }

            $transcription = trim($data['text']);
            $detectedLanguage = $data['language'] ?? $language;
            $cost = ($duration / 60) * self::$costPerMinute;
            $processingTime = (int)((microtime(true) - $startTime) * 1000);

            Logger::api4com("CallTranscriptionService - Transcrição concluída: {$duration}s, custo: \${$cost}, tempo: {$processingTime}ms");

            return [
                'success' => true,
                'transcription' => $transcription,
                'language' => $detectedLanguage,
                'duration' => $duration,
                'cost' => round($cost, 6),
                'processing_time_ms' => $processingTime
            ];

        } catch (\Exception $e) {
            Logger::api4com("CallTranscriptionService - Exceção: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Baixar arquivo de áudio para arquivo temporário
     */
    private static function downloadAudio(string $url): ?string
    {
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/call_audio_' . uniqid() . '.mp3';

        Logger::api4com("CallTranscriptionService - Baixando áudio para: {$tempFile}");

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $audio = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200 || empty($audio)) {
            Logger::api4com("CallTranscriptionService - Falha ao baixar: HTTP {$httpCode}, Error: {$error}", 'ERROR');
            return null;
        }

        if (file_put_contents($tempFile, $audio) === false) {
            Logger::api4com("CallTranscriptionService - Falha ao salvar arquivo temporário", 'ERROR');
            return null;
        }

        $fileSize = filesize($tempFile);
        Logger::api4com("CallTranscriptionService - Áudio baixado: {$fileSize} bytes");

        return $tempFile;
    }

    /**
     * Obter duração do áudio em segundos
     */
    private static function getAudioDuration(string $filePath): int
    {
        // Tentar usar getID3 se disponível
        if (class_exists('getID3')) {
            try {
                $getID3 = new \getID3();
                $info = $getID3->analyze($filePath);
                return (int)($info['playtime_seconds'] ?? 0);
            } catch (\Exception $e) {
                // Ignorar
            }
        }

        // Tentar usar ffprobe se disponível
        $ffprobe = shell_exec("which ffprobe 2>/dev/null");
        if ($ffprobe) {
            $duration = shell_exec("ffprobe -i " . escapeshellarg($filePath) . " -show_entries format=duration -v quiet -of csv='p=0' 2>/dev/null");
            if ($duration) {
                return (int)floatval(trim($duration));
            }
        }

        // Fallback: estimar baseado no tamanho do arquivo
        // MP3 128kbps ≈ 1MB por minuto
        $fileSize = filesize($filePath);
        $estimatedMinutes = $fileSize / (1024 * 1024);
        return (int)($estimatedMinutes * 60);
    }

    /**
     * Verificar se transcrição está habilitada
     */
    public static function isEnabled(): bool
    {
        $setting = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'call_transcription_enabled' LIMIT 1");
        return ($setting['value'] ?? '1') === '1';
    }

    /**
     * Obter custo estimado para uma duração
     */
    public static function estimateCost(int $durationSeconds): float
    {
        return round(($durationSeconds / 60) * self::$costPerMinute, 4);
    }
}
