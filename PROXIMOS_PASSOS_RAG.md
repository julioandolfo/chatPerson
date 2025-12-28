# 🚀 PRÓXIMOS PASSOS - IMPLEMENTAÇÃO DO SISTEMA RAG

**Data**: 2025-01-27  
**Status**: ✅ PostgreSQL + pgvector configurado e funcionando

---

## ✅ O QUE JÁ ESTÁ PRONTO

- ✅ PostgreSQL instalado e configurado
- ✅ Extensão pgvector instalada
- ✅ Configurações salvas no sistema
- ✅ Helper PostgreSQL criado (`app/Helpers/PostgreSQL.php`)
- ✅ Interface de configurações funcionando
- ✅ **Migrations do sistema RAG criadas** (060, 061, 062, 063)
- ✅ **Script de execução criado** (`public/run-rag-migrations.php`)

---

## 📋 PRÓXIMOS PASSOS - ORDEM DE IMPLEMENTAÇÃO

### FASE 1: Estrutura de Dados (Prioridade ALTA) ⭐

#### 1.1 Criar Migrations do Sistema RAG

**Arquivos a criar:**

1. **`database/migrations/060_create_ai_knowledge_base_table.php`**
   - Tabela principal para armazenar conhecimentos
   - Campo `embedding vector(1536)` para vetores
   - Índices para busca semântica

2. **`database/migrations/061_create_ai_feedback_loop_table.php`**
   - Tabela para feedback loop (perguntas não respondidas)
   - Status: pending, reviewed, added_to_kb, ignored

3. **`database/migrations/062_create_ai_url_scraping_table.php`**
   - Tabela para URLs sendo processadas
   - Status: pending, processing, completed, failed

4. **`database/migrations/063_create_ai_agent_memory_table.php`**
   - Tabela para memória persistente dos agentes
   - Tipos: fact, preference, context, extracted_info

**Tempo estimado**: 1-2 horas

#### 1.2 Criar Models

**Arquivos a criar:**

1. **`app/Models/AIKnowledgeBase.php`**
   - CRUD básico
   - Métodos para busca semântica
   - Métodos para gerenciar embeddings

2. **`app/Models/AIFeedbackLoop.php`**
   - CRUD básico
   - Métodos para listar pendentes
   - Métodos para revisar feedbacks

3. **`app/Models/AIUrlScraping.php`**
   - CRUD básico
   - Métodos para processar URLs

4. **`app/Models/AIAgentMemory.php`**
   - CRUD básico
   - Métodos para buscar memórias relevantes

**Tempo estimado**: 2-3 horas

---

### FASE 2: Serviços Core (Prioridade ALTA) ⭐

#### 2.1 Criar RAGService

**Arquivo**: `app/Services/RAGService.php`

**Métodos principais:**

```php
// Buscar contexto relevante da knowledge base
public static function searchRelevantContext(int $agentId, string $query, int $limit = 5): array

// Adicionar conhecimento à base
public static function addKnowledge(int $agentId, string $content, string $contentType, array $metadata = []): int

// Gerar embedding usando OpenAI
public static function generateEmbedding(string $text): array

// Buscar similaridade no pgvector
public static function findSimilar(int $agentId, array $queryEmbedding, int $limit = 5): array
```

**Tempo estimado**: 3-4 horas

#### 2.2 Criar EmbeddingService

**Arquivo**: `app/Services/EmbeddingService.php`

**Métodos principais:**

```php
// Gerar embedding usando OpenAI API
public static function generate(string $text, string $model = 'text-embedding-3-small'): array

// Gerar embeddings em batch
public static function generateBatch(array $texts): array

// Cachear embedding
public static function getCached(string $text): ?array
```

**Tempo estimado**: 2-3 horas

#### 2.3 Integrar RAG no OpenAIService

**Modificar**: `app/Services/OpenAIService.php`

**O que fazer:**

1. No método `processMessage()`, antes de chamar OpenAI:
   - Buscar contexto relevante usando `RAGService::searchRelevantContext()`
   - Adicionar contexto ao prompt do sistema

2. Após receber resposta da IA:
   - Se resposta é boa, extrair informações importantes
   - Salvar em `ai_agent_memory` se relevante

**Tempo estimado**: 2-3 horas

---

### FASE 3: Sistema de Feedback Loop (Prioridade MÉDIA)

#### 3.1 Criar FeedbackLoopService

**Arquivo**: `app/Services/FeedbackLoopService.php`

**Métodos principais:**

```php
// Registrar feedback negativo
public static function registerFeedback(int $agentId, int $conversationId, int $messageId, string $question, string $aiResponse): int

// Revisar feedback e adicionar resposta correta
public static function reviewFeedback(int $feedbackId, int $userId, string $correctAnswer, bool $addToKB = true): bool

// Obter feedbacks pendentes
public static function getPendingFeedbacks(int $agentId, int $limit = 50): array
```

**Tempo estimado**: 2-3 horas

#### 3.2 Detectar Respostas Inadequadas

**Modificar**: `app/Services/OpenAIService.php`

**O que fazer:**

- Detectar sinais de resposta inadequada:
  - Cliente pede esclarecimento
  - Cliente diz "não entendi"
  - Cliente escala para humano
  - Score de confiança baixo (< 0.7)

- Registrar automaticamente em `ai_feedback_loop`

**Tempo estimado**: 2 horas

#### 3.3 Criar Interface de Revisão

**Arquivo**: `views/ai-agents/feedback.php`

**Funcionalidades:**

- Listar feedbacks pendentes
- Ver pergunta original + resposta da IA
- Campo para resposta correta
- Checkbox "Adicionar à knowledge base"
- Botão "Salvar e Adicionar"

**Tempo estimado**: 3-4 horas

---

### FASE 4: Web Scraping (Prioridade MÉDIA)

#### 4.1 Criar URLScrapingService

**Arquivo**: `app/Services/URLScrapingService.php`

**Métodos principais:**

```php
// Adicionar URL para scraping
public static function addUrl(int $agentId, string $url): int

// Processar URL (web scraping)
public static function processUrl(int $scrapingId): bool

// Dividir conteúdo em chunks
public static function chunkContent(string $content, int $maxTokens = 1000): array
```

**Tempo estimado**: 3-4 horas

#### 4.2 Implementar Web Scraping

**Bibliotecas necessárias:**

```json
{
    "require": {
        "symfony/dom-crawler": "^6.0",
        "guzzlehttp/guzzle": "^7.0"
    }
}
```

**Tempo estimado**: 2-3 horas

#### 4.3 Criar Job de Processamento

**Arquivo**: `app/Jobs/ProcessURLScrapingJob.php`

**Funcionalidades:**

- Processar URLs em background
- Gerar embeddings para cada chunk
- Salvar na knowledge base

**Tempo estimado**: 2-3 horas

---

### FASE 5: Sistema de Memória (Prioridade BAIXA)

#### 5.1 Criar AgentMemoryService

**Arquivo**: `app/Services/AgentMemoryService.php`

**Métodos principais:**

```php
// Salvar memória
public static function saveMemory(int $agentId, int $conversationId, string $type, string $key, string $value, float $importance = 0.5): int

// Buscar memórias relevantes
public static function getRelevantMemories(int $agentId, int $conversationId): array

// Extrair informações importantes da conversa
public static function extractImportantInfo(int $agentId, int $conversationId, array $messages): array
```

**Tempo estimado**: 3-4 horas

---

### FASE 6: Interface Completa (Prioridade MÉDIA)

#### 6.1 Página de Knowledge Base

**Arquivo**: `views/ai-agents/knowledge-base.php`

**Funcionalidades:**

- Listar conhecimentos do agente
- Buscar por texto (busca semântica)
- Adicionar conhecimento manualmente
- Importar de URL
- Editar/Excluir conhecimentos

**Tempo estimado**: 4-5 horas

#### 6.2 Página de URLs

**Arquivo**: `views/ai-agents/urls.php`

**Funcionalidades:**

- Listar URLs sendo processadas
- Adicionar nova URL
- Ver status de processamento
- Ver chunks criados

**Tempo estimado**: 3-4 horas

#### 6.3 Página de Memória

**Arquivo**: `views/ai-agents/memory.php`

**Funcionalidades:**

- Ver memórias do agente
- Filtrar por tipo
- Ver memórias por conversa

**Tempo estimado**: 2-3 horas

---

## 🎯 RECOMENDAÇÃO: COMEÇAR PELA FASE 1

### Passo 1: Executar Migrations (AGORA) ⭐

**Migrations criadas e prontas:**

1. ✅ `060_create_ai_knowledge_base_table.php` - Base de conhecimento
2. ✅ `061_create_ai_feedback_loop_table.php` - Feedback loop
3. ✅ `062_create_ai_url_scraping_table.php` - URLs sendo processadas
4. ✅ `063_create_ai_agent_memory_table.php` - Memória persistente

**Como executar:**

**Opção 1: Via Web (Recomendado)**
```
http://seu-dominio.com/run-rag-migrations.php
```

**Opção 2: Via Terminal**
```bash
php public/run-rag-migrations.php
```

**Opção 3: Manualmente (se necessário)**
```php
require_once 'database/migrations/060_create_ai_knowledge_base_table.php';
up_ai_knowledge_base_table();
// Repetir para as outras migrations
```

**⚠️ IMPORTANTE**: Estas migrations criam tabelas no **PostgreSQL**, não no MySQL!

### Passo 3: Criar Models Básicos

Depois das migrations, criar os Models básicos.

---

## 📊 CRONOGRAMA SUGERIDO

### Semana 1: Estrutura Base
- ✅ Migrations (1-2 dias)
- ✅ Models básicos (2-3 dias)
- ✅ Testes de conexão e estrutura

### Semana 2: Serviços Core
- ✅ RAGService (2-3 dias)
- ✅ EmbeddingService (1-2 dias)
- ✅ Integração com OpenAIService (1-2 dias)

### Semana 3: Feedback Loop
- ✅ FeedbackLoopService (2 dias)
- ✅ Detecção automática (1 dia)
- ✅ Interface de revisão (2 dias)

### Semana 4: Web Scraping
- ✅ URLScrapingService (2-3 dias)
- ✅ Job de processamento (1-2 dias)

### Semana 5: Memória e Interface
- ✅ AgentMemoryService (2-3 dias)
- ✅ Interfaces completas (3-4 dias)

---

## 💡 DICA IMPORTANTE

**Comece pequeno e teste cada etapa:**

1. ✅ Criar migration → Testar criação de tabela
2. ✅ Criar Model → Testar CRUD básico
3. ✅ Criar RAGService básico → Testar busca simples
4. ✅ Integrar no OpenAIService → Testar com agente real
5. ✅ Adicionar features gradualmente

---

## 🔗 DOCUMENTAÇÃO DE REFERÊNCIA

- **Plano Completo**: `PLANO_SISTEMA_RAG.md`
- **Resumo Executivo**: `RESUMO_EXECUTIVO_RAG.md`
- **Guia de Instalação**: `GUIA_INSTALACAO_POSTGRES_PGVECTOR_COOLIFY.md`

---

**Próximo passo imediato**: Criar as migrations do sistema RAG! 🚀

