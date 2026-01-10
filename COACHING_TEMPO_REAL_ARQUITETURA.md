# Coaching em Tempo Real - Arquitetura e Performance

## ✅ **SIM, é EXATAMENTE isso!**

O sistema **NÃO é tempo real instantâneo**. É **"quase tempo real"** com **fila inteligente** para suportar **50 msgs/segundo**.

---

## 🔄 **Como Funciona:**

```
┌─────────────────────────────────────────────────────────────┐
│ FLUXO COMPLETO                                              │
└─────────────────────────────────────────────────────────────┘

1️⃣ Cliente envia mensagem
   ↓
2️⃣ Sistema salva mensagem normalmente (RÁPIDO)
   ↓
3️⃣ Verifica SE deve analisar:
   ├─ ✅ É mensagem do cliente?
   ├─ ✅ Tem > 10 caracteres?
   ├─ ✅ Agente não analisou nos últimos 10s?
   ├─ ✅ Não ultrapassou limite/minuto?
   └─ ✅ Coaching habilitado?
   ↓
4️⃣ SE SIM → Adiciona na FILA (não bloqueia!)
   ↓
5️⃣ Worker processa fila a cada 3 segundos
   ↓
6️⃣ Analisa com OpenAI (1-2 segundos)
   ↓
7️⃣ Salva dica no banco
   ↓
8️⃣ Envia via WebSocket para agente
   ↓
9️⃣ Agente vê dica (3-5 segundos depois da msg)
```

---

## ⚡ **Performance com 50 msgs/segundo:**

### **Cenário Real:**

```
50 msgs/segundo = 3.000 msgs/minuto

Filtros aplicados:
├─ 50% são de agentes (não analisa) → 25 msgs/seg
├─ 30% < 10 caracteres (não analisa) → 17,5 msgs/seg
├─ Rate limit: 1 análise/agente/10s → ~3-5 análises/seg
└─ Resultado: 3-5 análises/segundo ✅
```

### **Recursos Necessários:**

| Métrica | Valor | Observação |
|---------|-------|------------|
| **Análises/seg** | 3-5 | Controlado por rate limit |
| **Latência** | 3-8s | Aceitável para coaching |
| **Custo/hora** | $0.15-0.30 | Com GPT-3.5-turbo |
| **Custo/dia** | $3.60-7.20 | Dentro do limite |
| **CPU** | Baixo | Fila assíncrona |
| **Memória** | ~50MB | Cache + fila |
| **Banco** | Mínimo | Só salva resultado |

---

## 📋 **Sistema de Fila:**

### **Como Funciona:**

```php
// Exemplo simplificado
class CoachingQueue {
    private static $queue = [];
    private static $processing = false;
    
    // Adicionar na fila (RÁPIDO - não bloqueia)
    public static function add($conversationId, $message, $agentId) {
        // Verifica rate limit
        if (self::canAnalyze($agentId)) {
            self::$queue[] = [
                'conversation_id' => $conversationId,
                'message' => $message,
                'agent_id' => $agentId,
                'added_at' => time()
            ];
        }
    }
    
    // Worker processa fila (background)
    public static function process() {
        if (self::$processing) return;
        self::$processing = true;
        
        // Pega até 10 itens da fila
        $batch = array_splice(self::$queue, 0, 10);
        
        foreach ($batch as $item) {
            // Debouncing: espera 3 segundos
            if (time() - $item['added_at'] >= 3) {
                self::analyzeAndSend($item);
            }
        }
        
        self::$processing = false;
    }
}
```

---

## ⚙️ **Configurações Disponíveis:**

### **1. Rate Limiting** ⚡

```php
'max_analyses_per_minute' => 10
```
- **O que faz:** Limita análises globais por minuto
- **Recomendado:** 10-20 (ajuste conforme volume)
- **Impacto:** Controla custo e carga

```php
'min_interval_between_analyses' => 10
```
- **O que faz:** Mínimo de segundos entre análises do MESMO agente
- **Recomendado:** 10-15 segundos
- **Impacto:** Evita spam de dicas

---

### **2. Fila e Processamento** 📋

```php
'use_queue' => true
```
- **O que faz:** Usa fila assíncrona (RECOMENDADO)
- **false:** Analisa imediatamente (bloqueia requisição)
- **true:** Adiciona na fila (não bloqueia) ✅

```php
'queue_processing_delay' => 3
```
- **O que faz:** Espera X segundos antes de processar (debouncing)
- **Recomendado:** 2-5 segundos
- **Impacto:** Agrupa mensagens rápidas

```php
'max_queue_size' => 100
```
- **O que faz:** Máximo de itens na fila
- **Recomendado:** 50-200
- **Impacto:** Evita sobrecarga

---

### **3. Filtros** 🎯

```php
'analyze_only_client_messages' => true
```
- **O que faz:** Só analisa mensagens do CLIENTE
- **Recomendado:** true (economiza 50%)

```php
'min_message_length' => 10
```
- **O que faz:** Ignora mensagens curtas ("ok", "sim")
- **Recomendado:** 10-20 caracteres

```php
'skip_if_agent_typing' => true
```
- **O que faz:** Não analisa se agente já está digitando
- **Recomendado:** true (evita interrupção)

---

### **4. Cache** 💾

```php
'use_cache' => true
```
- **O que faz:** Reutiliza análises similares
- **Recomendado:** true (economiza 30-40%)

```php
'cache_ttl_minutes' => 60
```
- **O que faz:** Tempo de validade do cache
- **Recomendado:** 30-120 minutos

```php
'cache_similarity_threshold' => 0.85
```
- **O que faz:** % de similaridade para usar cache
- **Recomendado:** 0.80-0.90 (85% = muito similar)

---

### **5. Custo e Limites** 💰

```php
'cost_limit_per_hour' => 1.00
```
- **O que faz:** Para análises se ultrapassar $1/hora
- **Recomendado:** $0.50-2.00

```php
'cost_limit_per_day' => 10.00
```
- **O que faz:** Limite diário total
- **Recomendado:** $5-20

---

## 📊 **Exemplo de Configuração para 50 msgs/seg:**

### **Configuração Conservadora** (Baixo custo)

```php
'realtime_coaching' => [
    'enabled' => true,
    'model' => 'gpt-3.5-turbo', // Rápido e barato
    'temperature' => 0.5,
    
    'max_analyses_per_minute' => 10, // Só 10/min
    'min_interval_between_analyses' => 15, // 15s entre análises
    
    'use_queue' => true,
    'queue_processing_delay' => 5, // Espera 5s
    'max_queue_size' => 50,
    
    'analyze_only_client_messages' => true,
    'min_message_length' => 15,
    'skip_if_agent_typing' => true,
    
    'use_cache' => true,
    'cache_ttl_minutes' => 120,
    'cache_similarity_threshold' => 0.90, // Cache agressivo
    
    'cost_limit_per_hour' => 0.50,
    'cost_limit_per_day' => 5.00,
]
```

**Resultado:**
- ~2-3 análises/segundo
- Custo: ~$2-3/dia
- Latência: 5-8 segundos
- CPU: Baixo

---

### **Configuração Agressiva** (Mais análises)

```php
'realtime_coaching' => [
    'enabled' => true,
    'model' => 'gpt-3.5-turbo',
    'temperature' => 0.5,
    
    'max_analyses_per_minute' => 30, // Até 30/min
    'min_interval_between_analyses' => 5, // Só 5s
    
    'use_queue' => true,
    'queue_processing_delay' => 2, // Mais rápido
    'max_queue_size' => 200,
    
    'analyze_only_client_messages' => true,
    'min_message_length' => 10,
    'skip_if_agent_typing' => false, // Sempre analisa
    
    'use_cache' => true,
    'cache_ttl_minutes' => 30,
    'cache_similarity_threshold' => 0.80, // Cache moderado
    
    'cost_limit_per_hour' => 2.00,
    'cost_limit_per_day' => 20.00,
]
```

**Resultado:**
- ~5-8 análises/segundo
- Custo: ~$10-15/dia
- Latência: 2-5 segundos
- CPU: Médio

---

## 🚀 **Worker de Processamento:**

### **Opção 1: Cron Job** (Simples)

```bash
# Processa fila a cada 5 segundos
*/5 * * * * cd /var/www/html && php public/scripts/process-coaching-queue.php
```

### **Opção 2: Supervisor** (Recomendado)

```ini
[program:coaching-worker]
command=php /var/www/html/public/scripts/coaching-worker.php
autostart=true
autorestart=true
numprocs=2
```

### **Opção 3: ReactPHP** (Avançado)

```php
// Worker em loop contínuo
$loop = React\EventLoop\Factory::create();
$loop->addPeriodicTimer(3, function() {
    CoachingQueue::process();
});
$loop->run();
```

---

## 📈 **Monitoramento:**

### **Métricas Importantes:**

```sql
-- Tamanho da fila
SELECT COUNT(*) FROM realtime_coaching_queue WHERE processed = 0;

-- Análises por minuto
SELECT COUNT(*) FROM realtime_coaching_hints 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE);

-- Custo por hora
SELECT SUM(cost) FROM realtime_coaching_hints 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Taxa de uso de cache
SELECT 
    SUM(CASE WHEN from_cache = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100 as cache_rate
FROM realtime_coaching_hints;
```

---

## ✅ **Resumo:**

| Aspecto | Comportamento |
|---------|---------------|
| **Tempo Real?** | Não, "quase tempo real" (3-8s delay) |
| **Usa Fila?** | Sim, fila assíncrona |
| **Bloqueia envio?** | Não, análise é background |
| **50 msgs/seg?** | Suporta com rate limiting |
| **Custo?** | $2-15/dia (configurável) |
| **Escalável?** | Sim, adicione workers |

---

**Pronto para implementar?** 🚀

Este design garante:
- ✅ Sistema continua rápido
- ✅ Não sobrecarrega
- ✅ Custo controlado
- ✅ Dicas úteis em tempo hábil
