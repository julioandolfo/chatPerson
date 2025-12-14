# 📊 Proposta: Sistema de Análise de Sentimento com OpenAI

## 🎯 Objetivo
Implementar análise de sentimento automática usando OpenAI GPT para mensagens de conversas, armazenando histórico e permitindo configurações flexíveis de periodicidade e período de análise.

---

## 🏗️ Arquitetura Proposta

### 1. **Tabela de Armazenamento**
Criar tabela `conversation_sentiments` para armazenar análises:

```sql
CREATE TABLE conversation_sentiments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    message_id INT NULL COMMENT 'ID da mensagem analisada (NULL = análise geral da conversa)',
    sentiment_score DECIMAL(3,2) NOT NULL COMMENT 'Score -1.0 (negativo) a 1.0 (positivo)',
    sentiment_label VARCHAR(20) NOT NULL COMMENT 'positive, neutral, negative',
    emotions JSON NULL COMMENT 'Emoções detectadas: {frustration: 0.8, satisfaction: 0.2, ...}',
    urgency_level VARCHAR(20) NULL COMMENT 'low, medium, high, critical',
    confidence DECIMAL(3,2) DEFAULT 0.0 COMMENT 'Confiança da análise (0.0 a 1.0)',
    analysis_text TEXT NULL COMMENT 'Texto explicativo da análise',
    tokens_used INT DEFAULT 0 COMMENT 'Tokens OpenAI utilizados',
    cost DECIMAL(10,6) DEFAULT 0 COMMENT 'Custo em USD',
    analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Quando foi analisado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_message_id (message_id),
    INDEX idx_sentiment_label (sentiment_label),
    INDEX idx_analyzed_at (analyzed_at),
    INDEX idx_conversation_analyzed (conversation_id, analyzed_at),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. **Configurações no Sistema**

Adicionar seção `sentiment_analysis` em `conversation_settings`:

```php
'sentiment_analysis' => [
    'enabled' => true, // Habilitar análise de sentimento
    'model' => 'gpt-3.5-turbo', // Modelo OpenAI (gpt-3.5-turbo é mais barato)
    'check_interval_minutes' => 30, // A cada quantos minutos verificar conversas abertas
    'max_conversation_age_days' => 30, // Não analisar conversas abertas há mais de X dias
    'analyze_on_new_message' => true, // Analisar automaticamente quando nova mensagem chegar
    'analyze_on_message_count' => 5, // Analisar após X mensagens do contato
    'min_messages_to_analyze' => 3, // Mínimo de mensagens para fazer análise
    'store_per_message' => false, // Armazenar análise por mensagem (false = apenas geral)
    'include_emotions' => true, // Incluir análise de emoções específicas
    'include_urgency' => true, // Incluir nível de urgência
    'auto_tag_negative' => true, // Adicionar tag automaticamente se sentimento negativo
    'negative_tag_id' => null, // ID da tag para adicionar (se auto_tag_negative = true)
    'alert_on_critical' => true, // Alertar quando urgência crítica detectada
    'cost_limit_per_day' => 5.00, // Limite de custo diário em USD (0 = ilimitado)
]
```

### 3. **Service: SentimentAnalysisService**

Criar `app/Services/SentimentAnalysisService.php` com métodos:

```php
class SentimentAnalysisService
{
    /**
     * Analisar sentimento de uma conversa usando OpenAI
     */
    public static function analyzeConversation(int $conversationId, ?int $messageId = null): ?array
    
    /**
     * Verificar e processar conversas pendentes de análise (cron job)
     */
    public static function processPendingConversations(): array
    
    /**
     * Obter sentimento atual de uma conversa
     */
    public static function getCurrentSentiment(int $conversationId): ?array
    
    /**
     * Obter histórico de sentimentos de uma conversa
     */
    public static function getSentimentHistory(int $conversationId, int $limit = 50): array
    
    /**
     * Calcular sentimento médio de um contato (para histórico)
     */
    public static function getContactAverageSentiment(int $contactId): ?float
}
```

### 4. **Integração com OpenAI**

Usar `OpenAIService` existente, mas criar prompt específico:

```php
private static function buildSentimentPrompt(array $messages, bool $includeEmotions, bool $includeUrgency): string
{
    $history = self::formatMessagesForAnalysis($messages);
    
    $prompt = "Analise o sentimento e emoções expressas na seguinte conversa de atendimento:\n\n";
    $prompt .= "Histórico da conversa:\n{$history}\n\n";
    $prompt .= "Retorne APENAS um JSON válido com a seguinte estrutura:\n";
    $prompt .= "{\n";
    $prompt .= "  \"sentiment_score\": -1.0 a 1.0 (decimal),\n";
    $prompt .= "  \"sentiment_label\": \"positive\" | \"neutral\" | \"negative\",\n";
    
    if ($includeEmotions) {
        $prompt .= "  \"emotions\": {\n";
        $prompt .= "    \"frustration\": 0.0 a 1.0,\n";
        $prompt .= "    \"satisfaction\": 0.0 a 1.0,\n";
        $prompt .= "    \"anxiety\": 0.0 a 1.0,\n";
        $prompt .= "    \"anger\": 0.0 a 1.0,\n";
        $prompt .= "    \"happiness\": 0.0 a 1.0,\n";
        $prompt .= "    \"confusion\": 0.0 a 1.0\n";
        $prompt .= "  },\n";
    }
    
    if ($includeUrgency) {
        $prompt .= "  \"urgency_level\": \"low\" | \"medium\" | \"high\" | \"critical\",\n";
    }
    
    $prompt .= "  \"confidence\": 0.0 a 1.0,\n";
    $prompt .= "  \"analysis_text\": \"Breve explicação do sentimento detectado\"\n";
    $prompt .= "}\n\n";
    $prompt .= "IMPORTANTE: Retorne APENAS o JSON, sem markdown, sem explicações adicionais.";
    
    return $prompt;
}
```

### 5. **Cron Job / Scheduled Task**

Criar script `public/scripts/analyze-sentiments.php` para rodar periodicamente:

```php
<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\SentimentAnalysisService;

// Processar conversas pendentes
$result = SentimentAnalysisService::processPendingConversations();

echo "Análises processadas: " . $result['processed'] . "\n";
echo "Erros: " . $result['errors'] . "\n";
echo "Custo total: $" . number_format($result['cost'], 4) . "\n";
```

**Agendar no cron:**
```bash
# A cada 30 minutos (ou conforme configuração)
*/30 * * * * cd /var/www/html && php public/scripts/analyze-sentiments.php >> logs/sentiment-analysis.log 2>&1
```

### 6. **Integração em Tempo Real**

Modificar `ConversationService::sendMessage()` para analisar automaticamente:

```php
// Após criar mensagem
if ($settings['sentiment_analysis']['analyze_on_new_message'] ?? false) {
    $messageCount = Message::where('conversation_id', '=', $conversationId)
        ->where('sender_type', '=', 'contact')
        ->count();
    
    $minMessages = $settings['sentiment_analysis']['min_messages_to_analyze'] ?? 3;
    $analyzeOnCount = $settings['sentiment_analysis']['analyze_on_message_count'] ?? 5;
    
    if ($messageCount >= $minMessages && ($messageCount % $analyzeOnCount === 0)) {
        // Analisar em background (não bloquear resposta)
        try {
            SentimentAnalysisService::analyzeConversation($conversationId);
        } catch (\Exception $e) {
            Logger::error("Erro ao analisar sentimento: " . $e->getMessage());
        }
    }
}
```

### 7. **Exibição no Frontend**

**Sidebar da Conversa:**
- Badge de sentimento (🟢 positivo, 🟡 neutro, 🔴 negativo)
- Score numérico (-1.0 a 1.0)
- Emoções detectadas (se disponível)
- Nível de urgência (se disponível)

**Timeline:**
- Eventos de mudança de sentimento
- Gráfico de evolução do sentimento ao longo do tempo

**Histórico do Contato:**
- Sentimento médio nas conversas anteriores
- Tendência (melhorando/piorando)

### 8. **Controle de Custos**

- Verificar limite diário antes de cada análise
- Usar `gpt-3.5-turbo` por padrão (mais barato que GPT-4)
- Cache de análises recentes (não re-analisar se já analisado nas últimas X horas)
- Log de custos em `ai_conversations` ou tabela separada

---

## 📋 Checklist de Implementação

- [ ] Criar migration `055_create_conversation_sentiments_table.php`
- [ ] Criar Model `ConversationSentiment.php`
- [ ] Criar Service `SentimentAnalysisService.php`
- [ ] Adicionar configurações em `ConversationSettingsService::getDefaultSettings()`
- [ ] Adicionar UI de configurações em `views/settings/conversations-tab.php`
- [ ] Modificar `SettingsController::saveConversations()` para salvar novas configs
- [ ] Integrar análise automática em `ConversationService::sendMessage()`
- [ ] Criar script cron `public/scripts/analyze-sentiments.php`
- [ ] Adicionar exibição no sidebar (`views/conversations/sidebar-conversation.php`)
- [ ] Adicionar eventos no timeline quando sentimento mudar
- [ ] Integrar com histórico do contato (média de sentimento)
- [ ] Adicionar rota API `GET /conversations/{id}/sentiment`
- [ ] Testes e validação

---

## 💡 Considerações

1. **Custo**: GPT-3.5-turbo custa ~$0.0015 por 1K tokens. Uma análise típica usa ~500-1000 tokens = ~$0.00075-0.0015 por análise.

2. **Performance**: Análises devem ser assíncronas (background) para não bloquear criação de mensagens.

3. **Precisão**: GPT-3.5-turbo é suficiente para análise de sentimento. GPT-4 só se precisar de análise muito complexa.

4. **Cache**: Não re-analisar conversas que já foram analisadas recentemente (ex: última análise < 1 hora).

5. **Limites**: Respeitar rate limits da OpenAI e custo diário configurado.

---

## ❓ Perguntas para Validar

1. **Periodicidade**: A cada 30 minutos está bom ou prefere configurável (15min, 1h, etc)?
2. **Idade máxima**: 30 dias está bom ou prefere outro valor?
3. **Análise por mensagem**: Quer análise individual de cada mensagem ou apenas geral da conversa?
4. **Tags automáticas**: Quer adicionar tag automaticamente quando sentimento negativo?
5. **Alertas**: Quer notificar agentes quando urgência crítica for detectada?
6. **Modelo**: GPT-3.5-turbo (mais barato) ou GPT-4 (mais preciso)?

---

## 🚀 Próximos Passos

Após aprovação desta proposta, implementarei:
1. Estrutura de banco de dados
2. Service de análise
3. Configurações
4. Integração automática
5. Exibição no frontend
6. Script de cron

**Tempo estimado**: 4-6 horas de desenvolvimento

