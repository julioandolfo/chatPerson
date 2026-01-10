<?php

namespace App\Services;

use App\Models\RealtimeCoachingHint;
use App\Models\Conversation;
use App\Models\Message;
use App\Helpers\Database;

/**
 * Serviço de Coaching em Tempo Real
 * 
 * Analisa mensagens durante a conversa e fornece dicas imediatas aos agentes.
 * Usa fila assíncrona e rate limiting para suportar alto volume.
 */
class RealtimeCoachingService
{
    // Tracking em memória
    private static array $lastAnalysis = []; // [agent_id => timestamp]
    private static array $analysisCount = []; // [minute => count]
    private static array $costTracking = []; // [hour => cost, day => cost]
    
    /**
     * Log helper
     */
    private static function log(string $message, string $level = 'info'): void
    {
        $logFile = __DIR__ . '/../../logs/coaching.log';
        $timestamp = date('Y-m-d H:i:s');
        $icon = match($level) {
            'error' => '❌',
            'success' => '✅',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            'debug' => '🔍',
            default => '•'
        };
        @file_put_contents($logFile, "[{$timestamp}] {$icon} {$message}\n", FILE_APPEND);
    }
    
    /**
     * Obter configurações de coaching em tempo real
     */
    public static function getSettings(): array
    {
        $settings = ConversationSettingsService::getSettings();
        return $settings['realtime_coaching'] ?? self::getDefaultSettings();
    }
    
    /**
     * Configurações padrão
     */
    public static function getDefaultSettings(): array
    {
        return [
            'enabled' => false,
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.5,
            'max_analyses_per_minute' => 10,
            'min_interval_between_analyses' => 10,
            'use_queue' => true,
            'queue_processing_delay' => 3,
            'max_queue_size' => 100,
            'analyze_only_client_messages' => true,
            'min_message_length' => 10,
            'skip_if_agent_typing' => true,
            'use_cache' => true,
            'cache_ttl_minutes' => 60,
            'cache_similarity_threshold' => 0.85,
            'cost_limit_per_hour' => 1.00,
            'cost_limit_per_day' => 10.00,
            'hint_types' => [
                'objection' => true,
                'opportunity' => true,
                'question' => true,
                'negative_sentiment' => true,
                'buying_signal' => true,
                'closing_opportunity' => true,
                'escalation_needed' => true,
            ],
            'auto_show_hint' => true,
            'hint_display_duration' => 30,
            'play_sound' => false,
        ];
    }
    
    /**
     * Adicionar mensagem na fila para análise
     */
    public static function queueMessageForAnalysis(int $messageId, int $conversationId, int $agentId): bool
    {
        $settings = self::getSettings();
        
        self::log("🎯 queueMessageForAnalysis() - Msg #{$messageId}, Conv #{$conversationId}, Agent #{$agentId}");
        
        if (!$settings['enabled']) {
            self::log("❌ Coaching DESABILITADO nas configurações - enabled=false", 'warning');
            return false;
        }
        
        self::log("✅ Coaching está HABILITADO - Prosseguindo com verificações...", 'success');
        
        // Verificar se deve analisar
        if (!self::shouldAnalyze($messageId, $agentId, $settings)) {
            self::log("⏭️ Mensagem NÃO será analisada (bloqueada por filtros)", 'warning');
            return false;
        }
        
        self::log("✅ Mensagem PASSOU em todos os filtros!");
        
        // Adicionar na fila
        if ($settings['use_queue']) {
            self::log("📋 Modo FILA ativado - Adicionando mensagem na fila");
            self::addToQueue($messageId, $conversationId, $agentId);
            self::log("✅ Mensagem adicionada na fila com sucesso!", 'success');
            return true;
        } else {
            self::log("⚡ Modo SÍNCRONO ativado - Analisando IMEDIATAMENTE");
            return self::analyzeMessageNow($messageId, $conversationId, $agentId);
        }
    }
    
    /**
     * Verificar se deve analisar a mensagem
     */
    private static function shouldAnalyze(int $messageId, int $agentId, array $settings): bool
    {
        self::log("🔍 === INICIANDO VERIFICAÇÃO DE FILTROS ===");
        
        // Obter mensagem
        $message = Message::find($messageId);
        if (!$message) {
            self::log("❌ FILTRO 0: Mensagem não encontrada no banco", 'error');
            return false;
        }
        
        $bodyLength = mb_strlen($message['content']);
        self::log("📝 Mensagem: \"{$message['content']}\" (tamanho: {$bodyLength} chars)");
        
        // 1. Verificar se é mensagem do cliente
        if ($settings['analyze_only_client_messages'] && $message['sender_type'] !== 'contact') {
            self::log("❌ FILTRO 1: Mensagem do tipo '{$message['sender_type']}' (config: apenas clientes)", 'warning');
            return false;
        }
        self::log("✅ FILTRO 1: OK - É mensagem de cliente");
        
        // 2. Verificar tamanho mínimo
        $minLength = $settings['min_message_length'];
        if ($bodyLength < $minLength) {
            self::log("❌ FILTRO 2: Mensagem muito curta ({$bodyLength} < {$minLength} chars)", 'warning');
            return false;
        }
        self::log("✅ FILTRO 2: OK - Tamanho adequado ({$bodyLength} >= {$minLength})");
        
        // 3. Verificar rate limit global (análises/minuto)
        $maxPerMinute = $settings['max_analyses_per_minute'];
        $currentCount = self::$analysisCount[date('Y-m-d H:i')] ?? 0;
        if (!self::checkGlobalRateLimit($maxPerMinute)) {
            self::log("❌ FILTRO 3: Rate limit global excedido ({$currentCount}/{$maxPerMinute} análises/min)", 'warning');
            return false;
        }
        self::log("✅ FILTRO 3: OK - Rate limit global ({$currentCount}/{$maxPerMinute})");
        
        // 4. Verificar intervalo mínimo entre análises do mesmo agente
        $minInterval = $settings['min_interval_between_analyses'];
        $lastTime = self::$lastAnalysis[$agentId] ?? 0;
        $elapsed = time() - $lastTime;
        if (!self::checkAgentInterval($agentId, $minInterval)) {
            self::log("❌ FILTRO 4: Agente #{$agentId} analisado há {$elapsed}s (min: {$minInterval}s)", 'warning');
            return false;
        }
        self::log("✅ FILTRO 4: OK - Intervalo agente ({$elapsed}s >= {$minInterval}s)");
        
        // 5. Verificar tamanho da fila
        if ($settings['use_queue']) {
            $queueSize = self::getQueueSize();
            $maxQueueSize = $settings['max_queue_size'];
            if ($queueSize >= $maxQueueSize) {
                self::log("❌ FILTRO 5: Fila cheia ({$queueSize}/{$maxQueueSize})", 'warning');
                return false;
            }
            self::log("✅ FILTRO 5: OK - Fila disponível ({$queueSize}/{$maxQueueSize})");
        }
        
        // 6. Verificar limite de custo
        $currentHour = date('Y-m-d H');
        $currentDay = date('Y-m-d');
        $hourCost = self::$costTracking['hour_' . $currentHour] ?? 0;
        $dayCost = self::$costTracking['day_' . $currentDay] ?? 0;
        $hourLimit = $settings['cost_limit_per_hour'];
        $dayLimit = $settings['cost_limit_per_day'];
        
        if (!self::checkCostLimits($settings)) {
            self::log("❌ FILTRO 6: Limite de custo excedido (Hora: \${$hourCost}/\${$hourLimit}, Dia: \${$dayCost}/\${$dayLimit})", 'error');
            return false;
        }
        self::log("✅ FILTRO 6: OK - Dentro do limite (Hora: \${$hourCost}/\${$hourLimit}, Dia: \${$dayCost}/\${$dayLimit})");
        
        self::log("✅✅✅ TODOS OS FILTROS PASSARAM! Mensagem será analisada!", 'success');
        return true;
    }
    
    /**
     * Verificar rate limit global
     */
    private static function checkGlobalRateLimit(int $maxPerMinute): bool
    {
        $currentMinute = date('Y-m-d H:i');
        
        // Limpar minutos antigos
        foreach (self::$analysisCount as $minute => $count) {
            if ($minute < date('Y-m-d H:i', strtotime('-5 minutes'))) {
                unset(self::$analysisCount[$minute]);
            }
        }
        
        $currentCount = self::$analysisCount[$currentMinute] ?? 0;
        return $currentCount < $maxPerMinute;
    }
    
    /**
     * Incrementar contador de análises
     */
    private static function incrementAnalysisCount(): void
    {
        $currentMinute = date('Y-m-d H:i');
        self::$analysisCount[$currentMinute] = (self::$analysisCount[$currentMinute] ?? 0) + 1;
    }
    
    /**
     * Verificar intervalo mínimo entre análises do mesmo agente
     */
    private static function checkAgentInterval(int $agentId, int $minInterval): bool
    {
        $lastTime = self::$lastAnalysis[$agentId] ?? 0;
        $elapsed = time() - $lastTime;
        return $elapsed >= $minInterval;
    }
    
    /**
     * Atualizar timestamp da última análise do agente
     */
    private static function updateAgentLastAnalysis(int $agentId): void
    {
        self::$lastAnalysis[$agentId] = time();
    }
    
    /**
     * Verificar limites de custo
     */
    private static function checkCostLimits(array $settings): bool
    {
        $currentHour = date('Y-m-d H');
        $currentDay = date('Y-m-d');
        
        // Limpar períodos antigos
        foreach (self::$costTracking as $period => $cost) {
            if (strpos($period, 'hour_') === 0 && $period < 'hour_' . date('Y-m-d H', strtotime('-2 hours'))) {
                unset(self::$costTracking[$period]);
            }
            if (strpos($period, 'day_') === 0 && $period < 'day_' . date('Y-m-d', strtotime('-2 days'))) {
                unset(self::$costTracking[$period]);
            }
        }
        
        $hourCost = self::$costTracking['hour_' . $currentHour] ?? 0;
        $dayCost = self::$costTracking['day_' . $currentDay] ?? 0;
        
        return $hourCost < $settings['cost_limit_per_hour'] && 
               $dayCost < $settings['cost_limit_per_day'];
    }
    
    /**
     * Adicionar custo ao tracking
     */
    private static function trackCost(float $cost): void
    {
        $currentHour = date('Y-m-d H');
        $currentDay = date('Y-m-d');
        
        self::$costTracking['hour_' . $currentHour] = (self::$costTracking['hour_' . $currentHour] ?? 0) + $cost;
        self::$costTracking['day_' . $currentDay] = (self::$costTracking['day_' . $currentDay] ?? 0) + $cost;
    }
    
    /**
     * Adicionar na fila (banco de dados)
     */
    private static function addToQueue(int $messageId, int $conversationId, int $agentId): void
    {
        $sql = "INSERT INTO coaching_queue (message_id, conversation_id, agent_id, status) 
                VALUES (:message_id, :conversation_id, :agent_id, 'pending')";
        
        Database::query($sql, [
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
            'agent_id' => $agentId
        ]);
        
        // Processar fila automaticamente em background (sem bloquear)
        self::triggerBackgroundProcessing();
    }
    
    /**
     * Obter tamanho da fila
     */
    private static function getQueueSize(): int
    {
        $sql = "SELECT COUNT(*) as total FROM coaching_queue WHERE status = 'pending'";
        $result = Database::fetchOne($sql);
        return (int)($result['total'] ?? 0);
    }
    
    /**
     * Disparar processamento em background (não bloqueia)
     */
    private static function triggerBackgroundProcessing(): void
    {
        static $lastTrigger = 0;
        
        // Só disparar a cada 3 segundos (debouncing)
        if (time() - $lastTrigger < 3) {
            return;
        }
        
        $lastTrigger = time();
        
        try {
            // Executar em background (não espera responder)
            $scriptPath = __DIR__ . '/../../public/scripts/process-coaching-queue.php';
            
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows
                pclose(popen("start /B php \"$scriptPath\" > NUL 2>&1", 'r'));
            } else {
                // Linux/Unix
                exec("php \"$scriptPath\" > /dev/null 2>&1 &");
            }
        } catch (\Exception $e) {
            error_log("Erro ao disparar processamento: " . $e->getMessage());
        }
    }
    
    /**
     * Processar fila (chamado pelo worker/cron)
     */
    public static function processQueue(): array
    {
        self::log("⚙️ === PROCESSANDO FILA DE COACHING ===", 'info');
        
        $settings = self::getSettings();
        $processed = 0;
        $errors = 0;
        $skipped = 0;
        
        // Buscar itens pendentes da fila (até 10 por vez)
        $sql = "SELECT * FROM coaching_queue 
                WHERE status = 'pending' 
                AND added_at <= DATE_SUB(NOW(), INTERVAL :delay SECOND)
                ORDER BY added_at ASC 
                LIMIT 10";
        
        $delay = $settings['queue_processing_delay'] ?? 3;
        $items = Database::fetchAll($sql, ['delay' => $delay]);
        
        $queueSize = self::getQueueSize();
        
        if (empty($items)) {
            self::log("ℹ️ Fila vazia (total pendente: {$queueSize})");
            return [
                'processed' => 0,
                'errors' => 0,
                'skipped' => 0,
                'queue_size' => $queueSize
            ];
        }
        
        self::log("📋 Encontrados " . count($items) . " itens na fila (delay: {$delay}s)");
        
        foreach ($items as $item) {
            self::log("🔄 Processando item #{$item['id']} - Msg #{$item['message_id']}, Conv #{$item['conversation_id']}");
            
            // Marcar como processando
            self::updateQueueStatus($item['id'], 'processing');
            
            try {
                $success = self::analyzeMessageNow(
                    $item['message_id'],
                    $item['conversation_id'],
                    $item['agent_id']
                );
                
                if ($success) {
                    // Marcar como completado
                    self::updateQueueStatus($item['id'], 'completed');
                    self::log("✅ Item #{$item['id']} processado com sucesso!", 'success');
                    $processed++;
                } else {
                    // Marcar como falho
                    self::updateQueueStatus($item['id'], 'failed', 'Análise retornou false');
                    self::log("⏭️ Item #{$item['id']} pulado (análise não gerou hint)", 'warning');
                    $skipped++;
                }
            } catch (\Exception $e) {
                self::log("❌ ERRO ao processar item #{$item['id']}: " . $e->getMessage(), 'error');
                error_log("Erro ao processar coaching: " . $e->getMessage());
                self::updateQueueStatus($item['id'], 'failed', $e->getMessage());
                $errors++;
            }
        }
        
        $result = [
            'processed' => $processed,
            'errors' => $errors,
            'skipped' => $skipped,
            'queue_size' => self::getQueueSize()
        ];
        
        self::log("📊 Resultado: {$processed} processados, {$errors} erros, {$skipped} pulados, {$result['queue_size']} na fila", 'info');
        
        return $result;
    }
    
    /**
     * Atualizar status do item na fila
     */
    private static function updateQueueStatus(int $id, string $status, ?string $error = null): void
    {
        $sql = "UPDATE coaching_queue 
                SET status = :status,
                    attempts = attempts + 1,
                    last_error = :error,
                    processed_at = NOW()
                WHERE id = :id";
        
        Database::query($sql, [
            'id' => $id,
            'status' => $status,
            'error' => $error
        ]);
    }
    
    /**
     * Analisar mensagem imediatamente
     */
    private static function analyzeMessageNow(int $messageId, int $conversationId, int $agentId): bool
    {
        self::log("🤖 === ANÁLISE COM IA INICIADA ===");
        self::log("📝 Msg #{$messageId}, Conv #{$conversationId}, Agent #{$agentId}");
        
        $settings = self::getSettings();
        
        // Verificar cache primeiro
        if ($settings['use_cache']) {
            self::log("💾 Verificando cache...");
            $cached = self::checkCache($messageId, $conversationId, $settings);
            if ($cached) {
                self::log("✅ CACHE HIT! Reutilizando hint anterior (economizou 1 chamada de API)", 'success');
                self::sendHintToAgent($cached, $agentId);
                return true;
            }
            self::log("❌ Cache miss - Precisa fazer análise nova");
        }
        
        // Obter mensagem e contexto
        $message = Message::find($messageId);
        if (!$message) {
            self::log("❌ Mensagem não encontrada", 'error');
            return false;
        }
        
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            self::log("❌ Conversa não encontrada", 'error');
            return false;
        }
        
        // Obter contexto (últimas 10 mensagens)
        self::log("📜 Buscando contexto da conversa (últimas 10 mensagens)...");
        $context = self::getConversationContext($conversationId, 10);
        self::log("📜 Contexto carregado: " . count($context) . " mensagens");
        
        // Analisar com IA
        try {
            self::log("🧠 Chamando OpenAI (model: {$settings['model']}, temp: {$settings['temperature']})...");
            $startTime = microtime(true);
            
            $analysis = self::analyzeWithAI($message, $context, $settings);
            
            $duration = round(microtime(true) - $startTime, 2);
            self::log("⏱️ Resposta da IA recebida em {$duration}s");
            
            if (!$analysis || empty($analysis['hint_text'])) {
                self::log("⏭️ IA não identificou situação relevante (has_hint: false)", 'warning');
                return false;
            }
            
            self::log("✅ HINT GERADO!", 'success');
            self::log("   Tipo: {$analysis['hint_type']}");
            self::log("   Texto: {$analysis['hint_text']}");
            self::log("   Tokens: {$analysis['tokens_used']}, Custo: \$" . number_format($analysis['cost'], 4));
            
            // Salvar hint
            $hintId = RealtimeCoachingHint::create([
                'conversation_id' => $conversationId,
                'agent_id' => $agentId,
                'message_id' => $messageId,
                'hint_type' => $analysis['hint_type'],
                'hint_text' => $analysis['hint_text'],
                'suggestions' => json_encode($analysis['suggestions'] ?? []),
                'model_used' => $settings['model'],
                'tokens_used' => $analysis['tokens_used'] ?? 0,
                'cost' => $analysis['cost'] ?? 0,
            ]);
            
            self::log("💾 Hint salvo no banco (ID: {$hintId})");
            
            // Tracking
            self::incrementAnalysisCount();
            self::updateAgentLastAnalysis($agentId);
            self::trackCost($analysis['cost'] ?? 0);
            
            // Enviar para o agente
            $hint = RealtimeCoachingHint::find($hintId);
            self::sendHintToAgent($hint, $agentId);
            
            return true;
            
        } catch (\Exception $e) {
            self::log("❌ ERRO CRÍTICO ao analisar: " . $e->getMessage(), 'error');
            error_log("Erro ao analisar com IA: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar cache para mensagens similares
     */
    private static function checkCache(int $messageId, int $conversationId, array $settings): ?array
    {
        $message = Message::find($messageId);
        if (!$message) {
            return null;
        }
        
        // Buscar hints recentes da mesma conversa
        $sql = "SELECT * FROM realtime_coaching_hints 
                WHERE conversation_id = :conversation_id 
                AND created_at > DATE_SUB(NOW(), INTERVAL :ttl MINUTE)
                ORDER BY created_at DESC
                LIMIT 5";
        
        $recentHints = Database::fetchAll($sql, [
            'conversation_id' => $conversationId,
            'ttl' => $settings['cache_ttl_minutes']
        ]);
        
        if (empty($recentHints)) {
            return null;
        }
        
        // Calcular similaridade (simplificado - em produção usar Levenshtein ou similar)
        foreach ($recentHints as $hint) {
            $hintMessage = Message::find($hint['message_id']);
            if (!$hintMessage) {
                continue;
            }
            
            $similarity = self::calculateSimilarity(
                $message['content'],
                $hintMessage['content']
            );
            
            if ($similarity >= $settings['cache_similarity_threshold']) {
                return $hint;
            }
        }
        
        return null;
    }
    
    /**
     * Calcular similaridade entre duas strings (0.0 a 1.0)
     */
    private static function calculateSimilarity(string $str1, string $str2): float
    {
        $str1 = mb_strtolower(trim($str1));
        $str2 = mb_strtolower(trim($str2));
        
        if ($str1 === $str2) {
            return 1.0;
        }
        
        // Usar similar_text
        similar_text($str1, $str2, $percent);
        return $percent / 100;
    }
    
    /**
     * Obter contexto da conversa (últimas N mensagens)
     */
    private static function getConversationContext(int $conversationId, int $limit = 10): array
    {
        $sql = "SELECT m.*, u.name as agent_name
                FROM messages m
                LEFT JOIN users u ON m.sender_id = u.id AND m.sender_type = 'agent'
                WHERE m.conversation_id = :conversation_id
                ORDER BY m.created_at DESC
                LIMIT :limit";
        
        $messages = Database::fetchAll($sql, [
            'conversation_id' => $conversationId,
            'limit' => $limit
        ]);
        
        return array_reverse($messages);
    }
    
    /**
     * Analisar mensagem com IA
     */
    private static function analyzeWithAI(array $message, array $context, array $settings): ?array
    {
        $apiKey = self::getApiKey();
        if (!$apiKey) {
            throw new \Exception('API Key da OpenAI não configurada');
        }
        
        // Construir prompt
        $prompt = self::buildCoachingPrompt($message, $context, $settings);
        
        // Fazer requisição
        $response = self::makeOpenAIRequest($prompt, $settings, $apiKey);
        
        if (!$response) {
            return null;
        }
        
        // Parsear resposta
        return self::parseAIResponse($response, $settings['model']);
    }
    
    /**
     * Construir prompt para IA
     */
    private static function buildCoachingPrompt(array $message, array $context, array $settings): string
    {
        $enabledHintTypes = array_keys(array_filter($settings['hint_types']));
        
        $prompt = "Você é um coach de vendas experiente. Analise a mensagem do cliente e forneça uma dica IMEDIATA e ACIONÁVEL para o vendedor.\n\n";
        
        $prompt .= "### CONTEXTO DA CONVERSA (últimas mensagens):\n";
        foreach ($context as $msg) {
            $sender = $msg['sender_type'] === 'contact' ? 'Cliente' : 'Vendedor';
            $prompt .= "{$sender}: {$msg['content']}\n";
        }
        
        $prompt .= "\n### MENSAGEM ATUAL DO CLIENTE:\n";
        $prompt .= $message['content'] . "\n\n";
        
        $prompt .= "### TIPOS DE SITUAÇÕES A DETECTAR:\n";
        $hintTypeDescriptions = [
            'objection' => 'Objeção do cliente (preço, prazo, dúvida)',
            'opportunity' => 'Oportunidade de venda (interesse, pergunta positiva)',
            'question' => 'Pergunta importante que precisa resposta técnica',
            'negative_sentiment' => 'Cliente insatisfeito ou frustrado',
            'buying_signal' => 'Sinal de compra (pronto para fechar)',
            'closing_opportunity' => 'Momento ideal para fechar venda',
            'escalation_needed' => 'Situação que precisa escalar para supervisor',
        ];
        
        foreach ($enabledHintTypes as $type) {
            $prompt .= "- {$type}: {$hintTypeDescriptions[$type]}\n";
        }
        
        $prompt .= "\n### SUA RESPOSTA DEVE SER UM JSON:\n";
        $prompt .= "{\n";
        $prompt .= '  "has_hint": true/false,  // Se encontrou situação relevante' . "\n";
        $prompt .= '  "hint_type": "objection",  // Tipo da situação' . "\n";
        $prompt .= '  "hint_text": "Cliente levantou objeção de preço",  // Descrição curta' . "\n";
        $prompt .= '  "suggestions": [  // 2-3 sugestões PRÁTICAS' . "\n";
        $prompt .= '    "Mostre o ROI em vez de falar de features",' . "\n";
        $prompt .= '    "Pergunte: Qual o custo de NÃO resolver este problema?"' . "\n";
        $prompt .= '  ]' . "\n";
        $prompt .= "}\n\n";
        
        $prompt .= "IMPORTANTE:\n";
        $prompt .= "- Seja ESPECÍFICO e ACIONÁVEL\n";
        $prompt .= "- Dicas CURTAS (máximo 2 linhas)\n";
        $prompt .= "- Se não houver situação relevante, retorne has_hint: false\n";
        $prompt .= "- SEMPRE retorne JSON válido\n";
        
        return $prompt;
    }
    
    /**
     * Fazer requisição para OpenAI
     */
    private static function makeOpenAIRequest(string $prompt, array $settings, string $apiKey): ?array
    {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => $settings['model'],
            'messages' => [
                ['role' => 'system', 'content' => 'Você é um coach de vendas experiente. Forneça dicas práticas e acionáveis.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $settings['temperature'],
            'max_tokens' => 300,
            'response_format' => ['type' => 'json_object']
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("OpenAI API Error: HTTP {$httpCode} - {$response}");
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Parsear resposta da IA
     */
    private static function parseAIResponse(array $response, string $model): ?array
    {
        if (!isset($response['choices'][0]['message']['content'])) {
            return null;
        }
        
        $content = $response['choices'][0]['message']['content'];
        $parsed = json_decode($content, true);
        
        if (!$parsed || !isset($parsed['has_hint']) || !$parsed['has_hint']) {
            return null;
        }
        
        // Calcular custo
        $tokensUsed = $response['usage']['total_tokens'] ?? 0;
        $cost = self::calculateCost($tokensUsed, $model);
        
        return [
            'hint_type' => $parsed['hint_type'] ?? 'general',
            'hint_text' => $parsed['hint_text'] ?? '',
            'suggestions' => $parsed['suggestions'] ?? [],
            'tokens_used' => $tokensUsed,
            'cost' => $cost
        ];
    }
    
    /**
     * Calcular custo da análise
     */
    private static function calculateCost(int $tokens, string $model): float
    {
        $pricesPer1kTokens = [
            'gpt-4o' => 0.005,
            'gpt-4-turbo' => 0.01,
            'gpt-4' => 0.03,
            'gpt-3.5-turbo' => 0.0015,
        ];
        
        $price = $pricesPer1kTokens[$model] ?? 0.002;
        return ($tokens / 1000) * $price;
    }
    
    /**
     * Obter API Key
     */
    private static function getApiKey(): ?string
    {
        $sql = "SELECT value FROM settings WHERE `key` = 'openai_api_key' LIMIT 1";
        $result = Database::fetchOne($sql);
        return $result['value'] ?? null;
    }
    
    /**
     * Enviar hint para o agente (WebSocket + preparar para polling)
     */
    private static function sendHintToAgent(array $hint, int $agentId): void
    {
        self::log("📤 Enviando hint para agente #{$agentId}...");
        
        // Tentar WebSocket primeiro
        try {
            $wsData = [
                'id' => $hint['id'],
                'conversation_id' => $hint['conversation_id'],
                'hint_type' => $hint['hint_type'],
                'hint_text' => $hint['hint_text'],
                'suggestions' => json_decode($hint['suggestions'], true),
                'created_at' => $hint['created_at'],
            ];
            
            // Enviar via WebSocket (se disponível)
            if (class_exists('\App\Helpers\WebSocket')) {
                \App\Helpers\WebSocket::notifyUser($agentId, 'coaching_hint', $wsData);
                self::log("✅ Hint enviado via WebSocket para agente #{$agentId}!", 'success');
            } else {
                self::log("⚠️ WebSocket não disponível - Hint ficará disponível via polling", 'warning');
            }
        } catch (\Exception $e) {
            self::log("❌ Erro ao enviar WebSocket: " . $e->getMessage(), 'error');
            error_log("Erro ao enviar WebSocket: " . $e->getMessage());
        }
        
        // Hint fica disponível para polling automaticamente (já está no banco)
        self::log("💾 Hint disponível no banco para polling (agente pode buscar quando abrir conversa)");
    }
    
    /**
     * Obter hints pendentes para polling
     */
    public static function getPendingHintsForAgent(int $agentId, int $conversationId, int $seconds = 10): array
    {
        $sql = "SELECT rch.*, m.content as message_body
                FROM realtime_coaching_hints rch
                LEFT JOIN messages m ON rch.message_id = m.id
                WHERE rch.agent_id = :agent_id
                AND rch.conversation_id = :conversation_id
                AND rch.created_at > DATE_SUB(NOW(), INTERVAL :seconds SECOND)
                ORDER BY rch.created_at DESC";
        
        $hints = Database::fetchAll($sql, [
            'agent_id' => $agentId,
            'conversation_id' => $conversationId,
            'seconds' => $seconds
        ]);
        
        foreach ($hints as &$hint) {
            $hint['suggestions'] = json_decode($hint['suggestions'], true);
        }
        
        return $hints;
    }
    
    /**
     * Obter estatísticas de coaching
     */
    public static function getStats(int $agentId, string $period = '24h'): array
    {
        $interval = match($period) {
            '1h' => 'INTERVAL 1 HOUR',
            '24h' => 'INTERVAL 24 HOUR',
            '7d' => 'INTERVAL 7 DAY',
            '30d' => 'INTERVAL 30 DAY',
            default => 'INTERVAL 24 HOUR'
        };
        
        $sql = "SELECT 
                    COUNT(*) as total_hints,
                    COUNT(DISTINCT conversation_id) as conversations_with_hints,
                    SUM(tokens_used) as total_tokens,
                    SUM(cost) as total_cost,
                    hint_type,
                    COUNT(*) as type_count
                FROM realtime_coaching_hints
                WHERE agent_id = :agent_id
                AND created_at > DATE_SUB(NOW(), {$interval})
                GROUP BY hint_type";
        
        $stats = Database::fetchAll($sql, ['agent_id' => $agentId]);
        
        return [
            'period' => $period,
            'by_type' => $stats,
            'summary' => self::summarizeStats($stats)
        ];
    }
    
    /**
     * Resumir estatísticas
     */
    private static function summarizeStats(array $stats): array
    {
        $total = 0;
        $totalCost = 0;
        $totalTokens = 0;
        
        foreach ($stats as $stat) {
            $total += $stat['type_count'];
            $totalCost += $stat['total_cost'];
            $totalTokens += $stat['total_tokens'];
        }
        
        return [
            'total_hints' => $total,
            'total_cost' => $totalCost,
            'total_tokens' => $totalTokens,
            'avg_cost_per_hint' => $total > 0 ? $totalCost / $total : 0
        ];
    }
}
