# 🧠 PLANO DETALHADO - SISTEMA RAG (RETRIEVAL-AUGMENTED GENERATION)

**Data**: 2025-01-27  
**Status**: Planejamento  
**Tecnologia**: PostgreSQL + pgvector

---

## 📋 VISÃO GERAL

Sistema RAG que permite agentes de IA:
- **Trabalhar por mais tempo** (contexto persistente)
- **Analisar links e guardar informações** (web scraping + vetorização)
- **Base de conhecimento** (500 produtos, informações de compra, etc)
- **Sistema de treinamento/feedback loop** (ver perguntas não respondidas e alimentar conhecimento)
- **Melhoria contínua** (agente fica mais inteligente com o tempo)

---

## 🎯 OBJETIVOS PRINCIPAIS

1. **Base de Conhecimento Vetorizada**
   - Armazenar informações de produtos, FAQ, documentos
   - Busca semântica usando embeddings
   - Atualização incremental

2. **Análise e Armazenamento de Links**
   - Web scraping de URLs fornecidas
   - Extração de conteúdo relevante
   - Vetorização e armazenamento

3. **Sistema de Feedback Loop**
   - Identificar perguntas não respondidas adequadamente
   - Interface para revisar e adicionar respostas corretas
   - Treinamento incremental do agente

4. **Contexto Persistente**
   - Memória de longo prazo por agente
   - Histórico de interações importantes
   - Informações extraídas de conversas anteriores

---

## 🏗️ ARQUITETURA PROPOSTA

### 1. Estrutura de Dados (PostgreSQL + pgvector)

#### Tabela: `ai_knowledge_base`
```sql
CREATE TABLE ai_knowledge_base (
    id SERIAL PRIMARY KEY,
    ai_agent_id INT NOT NULL,
    content_type VARCHAR(50) NOT NULL, -- 'product', 'faq', 'document', 'scraped_url', 'conversation_extract'
    title VARCHAR(500),
    content TEXT NOT NULL,
    source_url VARCHAR(1000), -- URL original (se aplicável)
    metadata JSONB, -- Informações adicionais (ex: preço, categoria, etc)
    embedding vector(1536), -- Embedding OpenAI (1536 dimensões para text-embedding-3-small)
    chunk_index INT DEFAULT 0, -- Para documentos grandes divididos em chunks
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE CASCADE
);

CREATE INDEX idx_knowledge_agent ON ai_knowledge_base(ai_agent_id);
CREATE INDEX idx_knowledge_type ON ai_knowledge_base(content_type);
CREATE INDEX idx_knowledge_embedding ON ai_knowledge_base USING ivfflat (embedding vector_cosine_ops);
```

#### Tabela: `ai_feedback_loop`
```sql
CREATE TABLE ai_feedback_loop (
    id SERIAL PRIMARY KEY,
    ai_agent_id INT NOT NULL,
    conversation_id INT NOT NULL,
    message_id INT NOT NULL, -- Mensagem do cliente que não foi respondida adequadamente
    user_question TEXT NOT NULL,
    ai_response TEXT, -- Resposta original da IA
    correct_answer TEXT, -- Resposta correta fornecida pelo humano
    status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'reviewed', 'added_to_kb', 'ignored'
    reviewed_by_user_id INT,
    reviewed_at TIMESTAMP,
    added_to_kb BOOLEAN DEFAULT FALSE,
    knowledge_base_id INT, -- ID do registro criado na knowledge base
    created_at TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (knowledge_base_id) REFERENCES ai_knowledge_base(id) ON DELETE SET NULL
);

CREATE INDEX idx_feedback_agent ON ai_feedback_loop(ai_agent_id);
CREATE INDEX idx_feedback_status ON ai_feedback_loop(status);
CREATE INDEX idx_feedback_pending ON ai_feedback_loop(ai_agent_id, status) WHERE status = 'pending';
```

#### Tabela: `ai_url_scraping`
```sql
CREATE TABLE ai_url_scraping (
    id SERIAL PRIMARY KEY,
    ai_agent_id INT NOT NULL,
    url VARCHAR(1000) NOT NULL,
    title VARCHAR(500),
    content TEXT,
    scraped_at TIMESTAMP DEFAULT NOW(),
    status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'processing', 'completed', 'failed'
    error_message TEXT,
    chunks_created INT DEFAULT 0,
    FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE CASCADE
);

CREATE INDEX idx_scraping_agent ON ai_url_scraping(ai_agent_id);
CREATE INDEX idx_scraping_status ON ai_url_scraping(status);
CREATE UNIQUE INDEX idx_scraping_url_agent ON ai_url_scraping(ai_agent_id, url);
```

#### Tabela: `ai_agent_memory`
```sql
CREATE TABLE ai_agent_memory (
    id SERIAL PRIMARY KEY,
    ai_agent_id INT NOT NULL,
    conversation_id INT NOT NULL,
    memory_type VARCHAR(50) NOT NULL, -- 'fact', 'preference', 'context', 'extracted_info'
    key VARCHAR(255), -- Chave identificadora (ex: 'contact_email', 'product_interest')
    value TEXT NOT NULL,
    importance DECIMAL(3,2) DEFAULT 0.5, -- 0.0 a 1.0
    expires_at TIMESTAMP, -- NULL = permanente
    created_at TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
);

CREATE INDEX idx_memory_agent ON ai_agent_memory(ai_agent_id);
CREATE INDEX idx_memory_conversation ON ai_agent_memory(conversation_id);
CREATE INDEX idx_memory_key ON ai_agent_memory(ai_agent_id, key);
```

---

## 🔄 FLUXO DE FUNCIONAMENTO

### 1. Processamento de Mensagem com RAG

```
1. Cliente envia mensagem
   ↓
2. Sistema busca contexto relevante na knowledge base:
   a) Gera embedding da mensagem do cliente
   b) Busca similaridade no pgvector (cosine similarity)
   c) Retorna top 5-10 chunks mais relevantes
   ↓
3. Monta prompt com:
   - Prompt do agente
   - Contexto relevante da knowledge base
   - Memória persistente do agente
   - Histórico da conversa
   - Tools disponíveis
   ↓
4. Chama OpenAI API
   ↓
5. Se resposta não é confiável ou cliente pede esclarecimento:
   a) Registra em feedback_loop (status: pending)
   b) Escala para humano se necessário
   ↓
6. Se resposta é boa:
   a) Extrai informações importantes para memória
   b) Salva em ai_agent_memory
   ↓
7. Envia resposta ao cliente
```

### 2. Sistema de Feedback Loop

```
1. Agente responde pergunta do cliente
   ↓
2. Sistema detecta sinais de resposta inadequada:
   - Cliente pede esclarecimento
   - Cliente diz "não entendi"
   - Cliente escala para humano
   - Resposta tem baixa confiança (score < 0.7)
   ↓
3. Registra em ai_feedback_loop (status: pending)
   ↓
4. Admin/Agente revisa:
   - Vê pergunta original
   - Vê resposta da IA
   - Fornece resposta correta
   ↓
5. Sistema adiciona à knowledge base:
   a) Cria embedding da resposta correta
   b) Salva em ai_knowledge_base
   c) Atualiza feedback_loop (status: added_to_kb)
   ↓
6. Próxima vez que pergunta similar aparecer:
   - Sistema encontra resposta correta na KB
   - Agente responde corretamente
```

### 3. Análise e Armazenamento de Links

```
1. Admin fornece URL para o agente
   OU
   Cliente envia link durante conversa
   ↓
2. Sistema registra em ai_url_scraping (status: pending)
   ↓
3. Job em background processa:
   a) Faz web scraping da URL
   b) Extrai conteúdo relevante (texto, títulos, etc)
   c) Remove HTML, limpa texto
   d) Divide em chunks (máx 1000 tokens cada)
   ↓
4. Para cada chunk:
   a) Gera embedding usando OpenAI
   b) Salva em ai_knowledge_base
   c) Associa ao ai_url_scraping
   ↓
5. Atualiza ai_url_scraping (status: completed, chunks_created: X)
```

---

## 🛠️ COMPONENTES A IMPLEMENTAR

### 1. Services

#### `RAGService.php`
```php
class RAGService
{
    // Buscar contexto relevante da knowledge base
    public static function searchRelevantContext(int $agentId, string $query, int $limit = 5): array
    
    // Adicionar conhecimento à base
    public static function addKnowledge(int $agentId, string $content, string $contentType, array $metadata = []): int
    
    // Gerar embedding usando OpenAI
    public static function generateEmbedding(string $text): array
    
    // Buscar similaridade no pgvector
    public static function findSimilar(int $agentId, array $queryEmbedding, int $limit = 5): array
}
```

#### `URLScrapingService.php`
```php
class URLScrapingService
{
    // Adicionar URL para scraping
    public static function addUrl(int $agentId, string $url): int
    
    // Processar URL (web scraping)
    public static function processUrl(int $scrapingId): bool
    
    // Dividir conteúdo em chunks
    public static function chunkContent(string $content, int $maxTokens = 1000): array
}
```

#### `FeedbackLoopService.php`
```php
class FeedbackLoopService
{
    // Registrar feedback negativo
    public static function registerFeedback(int $agentId, int $conversationId, int $messageId, string $question, string $aiResponse): int
    
    // Revisar feedback e adicionar resposta correta
    public static function reviewFeedback(int $feedbackId, int $userId, string $correctAnswer, bool $addToKB = true): bool
    
    // Obter feedbacks pendentes
    public static function getPendingFeedbacks(int $agentId, int $limit = 50): array
}
```

#### `AgentMemoryService.php`
```php
class AgentMemoryService
{
    // Salvar memória
    public static function saveMemory(int $agentId, int $conversationId, string $type, string $key, string $value, float $importance = 0.5): int
    
    // Buscar memórias relevantes
    public static function getRelevantMemories(int $agentId, int $conversationId): array
    
    // Extrair informações importantes da conversa
    public static function extractImportantInfo(int $agentId, int $conversationId, array $messages): array
}
```

### 2. Models

- `AIKnowledgeBase.php` - Model para knowledge base
- `AIFeedbackLoop.php` - Model para feedback loop
- `AIUrlScraping.php` - Model para URLs sendo processadas
- `AIAgentMemory.php` - Model para memória persistente

### 3. Controllers

- `RAGController.php` - Gerenciar knowledge base
- `FeedbackLoopController.php` - Interface de revisão de feedbacks
- `URLScrapingController.php` - Adicionar/processar URLs

### 4. Migrations

- `060_create_ai_knowledge_base_table.php`
- `061_create_ai_feedback_loop_table.php`
- `062_create_ai_url_scraping_table.php`
- `063_create_ai_agent_memory_table.php`
- `064_add_pgvector_extension.php` - Instalar extensão pgvector

### 5. Jobs (Background Processing)

- `ProcessURLScrapingJob.php` - Processar URLs em background
- `GenerateEmbeddingsJob.php` - Gerar embeddings em background
- `ExtractConversationInfoJob.php` - Extrair informações importantes de conversas

---

## 🔌 INTEGRAÇÃO COM OPENAI

### Embeddings

**Model Recomendado**: `text-embedding-3-small` (1536 dimensões)
- Mais barato: $0.02 por 1M tokens
- Boa qualidade para busca semântica
- Rápido

**Alternativa**: `text-embedding-3-large` (3072 dimensões)
- Melhor qualidade
- Mais caro: $0.13 por 1M tokens
- Mais lento

### Processo de Geração de Embedding

```php
// 1. Preparar texto (limpar, normalizar)
$cleanText = self::cleanText($text);

// 2. Chamar OpenAI Embeddings API
$response = self::callOpenAIEmbeddingsAPI($cleanText);

// 3. Obter vetor (1536 dimensões)
$embedding = $response['data'][0]['embedding'];

// 4. Salvar no PostgreSQL com pgvector
// INSERT INTO ai_knowledge_base (embedding) VALUES ($1::vector)
```

---

## 📊 BUSCA SEMÂNTICA COM pgvector

### Exemplo de Query

```sql
-- Buscar chunks mais similares
SELECT 
    id,
    title,
    content,
    content_type,
    1 - (embedding <=> $1::vector) as similarity
FROM ai_knowledge_base
WHERE ai_agent_id = $2
ORDER BY embedding <=> $1::vector
LIMIT 5;
```

**Operadores pgvector**:
- `<=>` - Cosine distance (recomendado para embeddings)
- `<->` - Euclidean distance
- `<#>` - Negative inner product

### Otimização com Índices

```sql
-- Criar índice IVFFlat (mais rápido para busca)
CREATE INDEX idx_knowledge_embedding ON ai_knowledge_base 
USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 100);

-- Para bases muito grandes (> 1M registros), usar HNSW
CREATE INDEX idx_knowledge_embedding_hnsw ON ai_knowledge_base 
USING hnsw (embedding vector_cosine_ops);
```

---

## 🎨 INTERFACE DE USUÁRIO

### 1. Página de Knowledge Base (`/ai-agents/{id}/knowledge-base`)

**Funcionalidades**:
- Listar todos os conhecimentos do agente
- Buscar por texto (busca semântica)
- Adicionar conhecimento manualmente
- Importar de URL
- Importar produtos (CSV/JSON)
- Editar/Excluir conhecimentos
- Visualizar similaridade entre conhecimentos

**Componentes**:
- Tabela de conhecimentos com filtros
- Modal para adicionar conhecimento
- Modal para importar URL
- Modal para importar produtos em massa
- Visualização de chunks (se documento grande)

### 2. Página de Feedback Loop (`/ai-agents/{id}/feedback`)

**Funcionalidades**:
- Listar feedbacks pendentes
- Ver pergunta original + resposta da IA
- Fornecer resposta correta
- Adicionar à knowledge base automaticamente
- Ignorar feedback (marcar como ignorado)
- Histórico de feedbacks revisados

**Componentes**:
- Lista de feedbacks pendentes (prioridade alta)
- Card de revisão com:
  - Pergunta do cliente
  - Resposta da IA (com score de confiança)
  - Campo para resposta correta
  - Checkbox "Adicionar à knowledge base"
  - Botão "Salvar e Adicionar"
  - Botão "Ignorar"

### 3. Página de URLs (`/ai-agents/{id}/urls`)

**Funcionalidades**:
- Listar URLs sendo processadas
- Adicionar nova URL
- Ver status de processamento
- Ver chunks criados
- Reprocessar URL (se falhou)

**Componentes**:
- Tabela de URLs com status
- Modal para adicionar URL
- Progress bar para URLs em processamento
- Visualização de conteúdo extraído

### 4. Página de Memória (`/ai-agents/{id}/memory`)

**Funcionalidades**:
- Ver memórias do agente
- Filtrar por tipo (fact, preference, context)
- Ver memórias por conversa
- Editar/Excluir memórias
- Ver importância de cada memória

**Componentes**:
- Lista de memórias com filtros
- Cards de memória com:
  - Tipo e importância
  - Chave e valor
  - Conversa associada
  - Data de criação

---

## 🔄 FLUXO DE TREINAMENTO INCREMENTAL

### Processo Completo

```
1. Agente responde pergunta
   ↓
2. Sistema avalia qualidade:
   - Score de confiança da resposta
   - Feedback do cliente (explícito ou implícito)
   - Escalação para humano
   ↓
3. Se qualidade baixa:
   a) Registra em feedback_loop
   b) Notifica admin/agente para revisar
   ↓
4. Admin revisa e fornece resposta correta
   ↓
5. Sistema adiciona à knowledge base:
   a) Gera embedding da resposta correta
   b) Salva com metadata (tipo, tags, etc)
   ↓
6. Próxima pergunta similar:
   a) Busca na KB encontra resposta correta
   b) Agente responde melhor
   ↓
7. Loop continua → Agente melhora continuamente
```

### Métricas de Melhoria

- **Taxa de Respostas Corretas**: % de respostas que não precisaram de revisão
- **Taxa de Escalação**: % de conversas escaladas para humano
- **Score Médio de Confiança**: Confiança média das respostas
- **Feedbacks Pendentes**: Quantidade de feedbacks aguardando revisão
- **Conhecimentos Adicionados**: Total de conhecimentos na base

---

## 🚀 IMPLEMENTAÇÃO POR FASES

### Fase 1: Infraestrutura Base (Semana 1-2)

**Objetivo**: Configurar PostgreSQL + pgvector e estrutura básica

**Tarefas**:
1. ✅ Instalar PostgreSQL no VPS
2. ✅ Instalar extensão pgvector
3. ✅ Criar migrations das tabelas
4. ✅ Criar Models básicos
5. ✅ Configurar conexão PostgreSQL no sistema

**Entregáveis**:
- PostgreSQL rodando com pgvector
- Tabelas criadas
- Models funcionando

### Fase 2: Serviços Core (Semana 2-3)

**Objetivo**: Implementar serviços de RAG básicos

**Tarefas**:
1. ✅ Criar `RAGService` com busca semântica
2. ✅ Criar `EmbeddingService` (integração OpenAI)
3. ✅ Integrar busca RAG no `OpenAIService`
4. ✅ Testar busca semântica

**Entregáveis**:
- Busca semântica funcionando
- Integração com OpenAI Embeddings API
- Agente usando contexto da KB

### Fase 3: Sistema de Feedback (Semana 3-4)

**Objetivo**: Implementar feedback loop completo

**Tarefas**:
1. ✅ Criar `FeedbackLoopService`
2. ✅ Detectar respostas inadequadas automaticamente
3. ✅ Interface de revisão de feedbacks
4. ✅ Adicionar à KB após revisão

**Entregáveis**:
- Sistema de feedback funcionando
- Interface de revisão
- Adição automática à KB

### Fase 4: Web Scraping (Semana 4-5)

**Objetivo**: Implementar análise e armazenamento de URLs

**Tarefas**:
1. ✅ Criar `URLScrapingService`
2. ✅ Implementar web scraping (usar biblioteca como Goutte ou Guzzle + DOM)
3. ✅ Dividir conteúdo em chunks
4. ✅ Gerar embeddings e salvar
5. ✅ Job em background para processar URLs

**Entregáveis**:
- Web scraping funcionando
- URLs sendo processadas automaticamente
- Conteúdo sendo adicionado à KB

### Fase 5: Sistema de Memória (Semana 5-6)

**Objetivo**: Implementar memória persistente

**Tarefas**:
1. ✅ Criar `AgentMemoryService`
2. ✅ Extrair informações importantes de conversas
3. ✅ Salvar memórias automaticamente
4. ✅ Usar memórias no contexto do agente

**Entregáveis**:
- Memória persistente funcionando
- Informações sendo extraídas automaticamente
- Contexto melhorado com memórias

### Fase 6: Interface Completa (Semana 6-7)

**Objetivo**: Criar todas as interfaces de usuário

**Tarefas**:
1. ✅ Página de Knowledge Base
2. ✅ Página de Feedback Loop
3. ✅ Página de URLs
4. ✅ Página de Memória
5. ✅ Melhorias de UX

**Entregáveis**:
- Todas as interfaces funcionando
- Sistema completo e testado

---

## 💡 MELHORIAS E SUGESTÕES

### 1. Chunking Inteligente

**Problema**: Dividir documentos grandes em chunks pode quebrar contexto

**Solução**: 
- Usar chunking semântico (dividir por parágrafos/tópicos)
- Overlap entre chunks (últimos 100 tokens do chunk anterior)
- Manter metadata de chunk anterior/próximo

### 2. Re-ranking de Resultados

**Problema**: Busca por similaridade pode retornar resultados não relevantes

**Solução**:
- Usar modelo de re-ranking (ex: Cohere Rerank API)
- Combinar similaridade vetorial + BM25 (busca textual)
- Score híbrido: `final_score = 0.7 * vector_score + 0.3 * text_score`

### 3. Cache de Embeddings

**Problema**: Gerar embeddings é caro e lento

**Solução**:
- Cachear embeddings de textos comuns
- Usar hash do texto como chave
- Cache em Redis ou PostgreSQL

### 4. Limpeza Automática

**Problema**: Knowledge base pode ficar desatualizada

**Solução**:
- Sistema de versionamento (manter histórico)
- Marcar conhecimentos como "obsoletos"
- Limpeza automática de conhecimentos não usados há X tempo
- Re-embedding periódico de conhecimentos importantes

### 5. Multi-Agent Knowledge Sharing

**Problema**: Cada agente tem sua própria KB (pode duplicar conhecimento)

**Solução**:
- KB global compartilhada
- KB por agente (específica)
- Sistema de herança (agente herda da KB global + específica)

### 6. Análise de Qualidade de Respostas

**Problema**: Como saber se resposta foi boa?

**Solução**:
- Score de confiança da IA (já existe)
- Análise de sentimento da resposta do cliente
- Detecção de palavras-chave ("não entendi", "obrigado", etc)
- Tempo até próxima mensagem (se cliente não responde rápido, pode ter ficado satisfeito)

### 7. A/B Testing de Conhecimentos

**Problema**: Como saber qual conhecimento é melhor?

**Solução**:
- Testar múltiplas versões de conhecimento
- Medir taxa de sucesso de cada versão
- Escolher automaticamente melhor versão

### 8. Importação em Massa

**Funcionalidades**:
- Importar produtos via CSV/JSON
- Importar FAQ via CSV
- Importar documentos (PDF, DOCX)
- API para adicionar conhecimentos programaticamente

### 9. Sistema de Tags e Categorias

**Funcionalidades**:
- Tagar conhecimentos (ex: "produto", "preço", "entrega")
- Filtrar busca por tags
- Categorias hierárquicas

### 10. Análise de Gaps de Conhecimento

**Funcionalidades**:
- Identificar perguntas frequentes sem resposta na KB
- Sugerir conhecimentos a adicionar
- Dashboard de gaps de conhecimento

---

## 📈 MÉTRICAS E ANALYTICS

### Métricas da Knowledge Base

- Total de conhecimentos
- Conhecimentos por tipo
- Taxa de uso (quantas vezes cada conhecimento foi usado)
- Conhecimentos mais úteis (top 10)
- Conhecimentos nunca usados (candidatos a remoção)

### Métricas de Feedback Loop

- Total de feedbacks pendentes
- Taxa de feedbacks revisados
- Tempo médio de revisão
- Taxa de adição à KB após revisão
- Melhoria ao longo do tempo (gráfico)

### Métricas de URLs

- Total de URLs processadas
- Taxa de sucesso de scraping
- Chunks criados por URL
- URLs mais úteis (baseado em uso)

### Métricas de Memória

- Total de memórias
- Memórias por tipo
- Memórias mais importantes
- Memórias expiradas (limpeza)

---

## 🔒 SEGURANÇA E VALIDAÇÃO

### Validações Necessárias

1. **URLs**:
   - Validar formato de URL
   - Verificar se URL é acessível
   - Rate limiting de scraping (não sobrecarregar servidor)
   - Whitelist/Blacklist de domínios

2. **Conteúdo**:
   - Sanitizar HTML de URLs
   - Validar tamanho máximo de conteúdo
   - Prevenir injection de código malicioso

3. **Embeddings**:
   - Validar dimensões do embedding (deve ser 1536)
   - Rate limiting de geração de embeddings
   - Cache para evitar duplicatas

4. **Acesso**:
   - Verificar permissões antes de adicionar conhecimento
   - Logs de todas as ações
   - Auditoria de mudanças na KB

---

## 💰 ESTIMATIVA DE CUSTOS

### OpenAI Embeddings API

**Model**: `text-embedding-3-small`
- **Preço**: $0.02 por 1M tokens
- **Exemplo**: 500 produtos × 500 tokens cada = 250K tokens = $0.005

**Custo Mensal Estimado**:
- 10.000 conhecimentos × 500 tokens = 5M tokens = **$0.10/mês**
- 100 URLs/dia × 2000 tokens = 200K tokens/dia = 6M tokens/mês = **$0.12/mês**
- **Total**: ~$0.25/mês (muito barato!)

### PostgreSQL + pgvector

- **Custo**: $0 (self-hosted)
- **Requisitos**: VPS com PostgreSQL 12+ e pgvector instalado

---

## 🎯 PRÓXIMOS PASSOS RECOMENDADOS

### Ordem de Implementação Sugerida

1. **Semana 1**: Configurar PostgreSQL + pgvector no VPS
2. **Semana 2**: Criar migrations e models básicos
3. **Semana 3**: Implementar `RAGService` e busca semântica básica
4. **Semana 4**: Integrar RAG no `OpenAIService` (usar contexto da KB)
5. **Semana 5**: Implementar sistema de feedback loop básico
6. **Semana 6**: Criar interface de revisão de feedbacks
7. **Semana 7**: Implementar web scraping básico
8. **Semana 8**: Criar interfaces completas
9. **Semana 9**: Implementar sistema de memória
10. **Semana 10**: Testes, ajustes e melhorias

---

## 📚 BIBLIOTECAS E DEPENDÊNCIAS

### PHP

```json
{
    "require": {
        "symfony/dom-crawler": "^6.0", // Web scraping
        "guzzlehttp/guzzle": "^7.0", // HTTP client
        "doctrine/dbal": "^3.0" // Para trabalhar com PostgreSQL
    }
}
```

### PostgreSQL

- **PostgreSQL**: 12+ (recomendado 14+)
- **pgvector**: Extensão para vetorização
- **Instalação**: `CREATE EXTENSION vector;`

### Node.js (Opcional - para processamento pesado)

- **Puppeteer**: Para scraping de SPAs (React, Vue, etc)
- **Cheerio**: Para parsing HTML rápido

---

## 🧪 TESTES SUGERIDOS

### Testes Unitários

- Geração de embeddings
- Busca semântica
- Chunking de conteúdo
- Extração de informações

### Testes de Integração

- Fluxo completo de RAG
- Feedback loop completo
- Web scraping completo
- Sistema de memória completo

### Testes de Performance

- Busca semântica com 10K+ conhecimentos
- Geração de embeddings em batch
- Web scraping de múltiplas URLs simultâneas

---

## 📝 NOTAS IMPORTANTES

### Considerações Técnicas

1. **pgvector vs Milvus/Pinecone**:
   - **pgvector**: Self-hosted, integrado ao PostgreSQL, gratuito
   - **Milvus**: Mais performático, mas requer servidor separado
   - **Pinecone**: SaaS, mais fácil, mas pago
   - **Recomendação**: Começar com pgvector (já tem PostgreSQL)

2. **Dimensões do Embedding**:
   - OpenAI `text-embedding-3-small`: 1536 dimensões
   - OpenAI `text-embedding-3-large`: 3072 dimensões
   - **Recomendação**: Começar com `small` (mais barato, suficiente)

3. **Tamanho de Chunks**:
   - Máximo recomendado: 1000 tokens
   - Overlap recomendado: 100-200 tokens
   - **Recomendação**: 800 tokens por chunk, 150 tokens de overlap

4. **Índices pgvector**:
   - **IVFFlat**: Mais rápido para criar, bom para < 1M registros
   - **HNSW**: Mais rápido para buscar, melhor para > 1M registros
   - **Recomendação**: Começar com IVFFlat, migrar para HNSW se necessário

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

### Infraestrutura
- [ ] PostgreSQL instalado no VPS
- [ ] Extensão pgvector instalada
- [ ] Conexão PostgreSQL configurada no sistema
- [ ] Migrations criadas e executadas

### Backend
- [ ] Models criados (AIKnowledgeBase, AIFeedbackLoop, etc)
- [ ] RAGService implementado
- [ ] EmbeddingService implementado
- [ ] FeedbackLoopService implementado
- [ ] URLScrapingService implementado
- [ ] AgentMemoryService implementado
- [ ] Integração com OpenAIService

### Frontend
- [ ] Página de Knowledge Base
- [ ] Página de Feedback Loop
- [ ] Página de URLs
- [ ] Página de Memória
- [ ] Modais de adição/edição

### Jobs
- [ ] Job de processamento de URLs
- [ ] Job de geração de embeddings
- [ ] Job de extração de informações

### Testes
- [ ] Testes unitários
- [ ] Testes de integração
- [ ] Testes de performance

---

**Última atualização**: 2025-01-27  
**Versão do Plano**: 1.0

