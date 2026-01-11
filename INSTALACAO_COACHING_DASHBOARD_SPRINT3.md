# 🧠 Sprint 3: Integração RAG - Instalação e Teste

## ✅ O que foi implementado

### 1. EmbeddingService
- **`app/Services/EmbeddingService.php`**
  - `generate()` - Gera embedding para um texto
  - `generateBatch()` - Gera embeddings para múltiplos textos
  - `cosineSimilarity()` - Calcula similaridade entre embeddings
  - Usa modelo `text-embedding-3-small` (1536 dimensões)
  - Custo: ~$0.00002 por 1k tokens (muito barato)

### 2. CoachingLearningService (Corrigido)
- **`app/Services/CoachingLearningService.php`**
  - `processSuccessfulHints()` - Extrai conhecimento de hints úteis
  - `findSimilarKnowledge()` - Busca hints similares no RAG
  - `discoverPatterns()` - Identifica padrões semanalmente
  - `incrementReuseCount()` - Rastreia reutilização
  - Corrigido: `PostgreSQL::getInstance()` → `PostgreSQL::getConnection()`

### 3. Script de Processamento Diário (STANDALONE)
- **`public/scripts/process-coaching-learning.php`**
  - ⚡ **STANDALONE** - Não depende do Composer
  - Executa diariamente via cron (01:00)
  - Processa hints de ontem
  - Extrai conhecimento para RAG
  - Descobre padrões semanalmente (domingo)

### 4. Tabela PostgreSQL
- **`coaching_knowledge_base`** (já criada na migration 064)
  - Armazena conhecimento extraído
  - Embeddings vetoriais (1536 dimensões)
  - Busca por similaridade com `<=>` operator
  - Índice HNSW para busca rápida

## 🚀 Como Instalar

### 1. Executar Migration PostgreSQL
```bash
php scripts/migrate.php
```

Isso criará a tabela `coaching_knowledge_base` no PostgreSQL.

### 2. Configurar Cron Job
Adicione ao crontab:

```bash
# Processar aprendizado de coaching (diário às 01:00)
0 1 * * * cd /var/www/html && php public/scripts/process-coaching-learning.php >> logs/coaching-learning.log 2>&1
```

**No Coolify:**
1. Vá em **"Scheduled Tasks"**
2. Adicione novo task:
   - **Comando:** `php /var/www/html/public/scripts/process-coaching-learning.php`
   - **Schedule:** `0 1 * * *` (01:00 diariamente)
   - **Enabled:** ✓

### 3. Testar Manualmente
```bash
php public/scripts/process-coaching-learning.php
```

**Saída esperada:**
```
🧠 === PROCESSAMENTO DE APRENDIZADO DE COACHING ===
📅 Data: 2026-01-11 14:00:00
📁 Root Dir: /var/www/html

📊 Processando hints de ontem...
✅ Processamento concluído!

📈 Estatísticas:
   Data: 2026-01-10
   Total de hints: 5
   Processados: 3
   Pulados: 2
   Erros: 0

✅ Script finalizado com sucesso!
```

## 🔍 Como Funciona

### Fluxo de Aprendizado

1. **Hint é gerado** → Agente marca como "útil" → Conversa converte
2. **Cron diário** → Processa hints úteis de ontem
3. **Extração de conhecimento:**
   - Busca mensagem do cliente
   - Busca contexto (5 mensagens anteriores)
   - Busca resposta bem-sucedida do agente
   - Gera embedding (OpenAI)
   - Salva no PostgreSQL

4. **Próximo hint:**
   - Sistema busca conhecimento similar no RAG
   - Usa exemplos passados para melhorar prompt
   - Gera hint mais preciso

### Score de Qualidade (1-5)

| Critério | Pontos |
|----------|--------|
| Base | 3 |
| Conversa converteu | +1 |
| Performance melhorou (≥4.0) | +1 |
| Sugestões foram usadas | +0.5 |
| Tem valor de venda | +0.5 |

**Apenas hints com score ≥ 4 vão para o RAG**

### Busca por Similaridade

```sql
SELECT * 
FROM coaching_knowledge_base
WHERE feedback_score >= 4
ORDER BY embedding <=> '[embedding_do_contexto]'::vector
LIMIT 5
```

- Usa operador `<=>` (distância de cosseno)
- Retorna apenas similaridade > 0.7
- Ordena por mais similar primeiro

## 📊 Estrutura dos Dados

### coaching_knowledge_base (PostgreSQL)

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | SERIAL | ID único |
| `situation_type` | VARCHAR(50) | Tipo (objeção, sinal_compra, etc) |
| `client_message` | TEXT | Mensagem do cliente |
| `conversation_context` | TEXT | Contexto (5 msgs anteriores) |
| `successful_response` | TEXT | Resposta que funcionou |
| `agent_action` | VARCHAR(100) | Ação do agente |
| `conversation_outcome` | VARCHAR(50) | Resultado (converted, lost) |
| `sales_value` | DECIMAL | Valor da venda |
| `time_to_outcome_minutes` | INT | Tempo até conversão |
| `agent_id` | INT | ID do agente |
| `conversation_id` | INT | ID da conversa |
| `hint_id` | INT | ID do hint original |
| `department` | VARCHAR(100) | Setor |
| `funnel_stage` | VARCHAR(100) | Etapa do funil |
| `feedback_score` | INT | Score 1-5 |
| `embedding` | vector(1536) | Embedding vetorial |
| `times_reused` | INT | Quantas vezes reutilizado |
| `success_rate` | DECIMAL | Taxa de sucesso |
| `created_at` | TIMESTAMP | Data criação |
| `updated_at` | TIMESTAMP | Data atualização |

## 🧪 Como Testar

### 1. Gerar Hints Úteis
1. Acesse uma conversa
2. Envie mensagem como cliente: "Preciso urgente!"
3. Worker gera hint
4. Marque hint como "👍 Útil"
5. Converta a conversa (ou simule conversão)

### 2. Executar Processamento
```bash
php public/scripts/process-coaching-learning.php
```

### 3. Verificar no PostgreSQL
```sql
-- Ver conhecimento extraído
SELECT 
    id, 
    situation_type, 
    client_message, 
    successful_response,
    feedback_score,
    times_reused
FROM coaching_knowledge_base
ORDER BY created_at DESC
LIMIT 10;

-- Testar busca por similaridade
SELECT 
    situation_type,
    client_message,
    successful_response,
    1 - (embedding <=> '[seu_embedding]'::vector) as similarity
FROM coaching_knowledge_base
WHERE feedback_score >= 4
ORDER BY embedding <=> '[seu_embedding]'::vector
LIMIT 5;
```

### 4. Verificar Logs
```bash
tail -f logs/coaching-learning.log
```

## 🔧 Troubleshooting

### PostgreSQL não disponível
```
⚠️ PostgreSQL não configurado. Pulando migration de coaching_knowledge_base.
```

**Solução:**
1. Configurar PostgreSQL em `/settings?tab=postgres`
2. Testar conexão
3. Executar migration novamente

### Erro ao gerar embedding
```
❌ EmbeddingService: OpenAI API key não configurada
```

**Solução:**
1. Configurar `openai_api_key` em `/settings`
2. Verificar se a chave tem créditos

### Nenhum hint processado
```
Total de hints: 0
Processados: 0
```

**Possíveis causas:**
1. Nenhum hint foi marcado como "útil" ontem
2. Hints já foram processados anteriormente
3. Hints não atingiram score ≥ 4

### Embedding com dimensão incorreta
```
❌ EmbeddingService: Dimensão incorreta: 3072
```

**Solução:**
- Modelo `text-embedding-3-large` tem 3072 dimensões
- Use `text-embedding-3-small` (1536 dimensões)
- Ou atualize a migration para `vector(3072)`

## 💰 Custos

### Embeddings (OpenAI)
- **Modelo:** `text-embedding-3-small`
- **Custo:** $0.00002 por 1k tokens
- **Exemplo:** 100 hints/dia × 500 tokens = 50k tokens = **$0.001/dia** (R$ 0,005)

### Armazenamento (PostgreSQL)
- **Embedding:** 1536 floats × 4 bytes = 6 KB por hint
- **Exemplo:** 1000 hints = 6 MB
- **Custo:** Praticamente zero

### Total Estimado
- **Mensal:** ~R$ 0,15 (embeddings) + R$ 0,00 (storage) = **R$ 0,15/mês**

## 📈 Benefícios

### 1. Hints Mais Precisos
- Sistema aprende com casos reais
- Usa exemplos de sucesso
- Adapta-se ao seu negócio

### 2. Economia de API
- Reutiliza conhecimento
- Menos chamadas desnecessárias
- Hints mais relevantes

### 3. Melhoria Contínua
- Descobre padrões automaticamente
- Identifica técnicas efetivas
- Sugere melhorias nos prompts

### 4. Personalização
- Aprende com seu time
- Adapta-se ao seu setor
- Considera seu funil

## 📝 Próximos Passos

### Sprint 4: Dashboard de Aprendizados
1. Tela "Biblioteca de Best Practices"
2. Visualizar conhecimento extraído
3. Filtrar por tipo, setor, taxa de sucesso
4. Ver exemplos de sucesso
5. Editar/aprovar conhecimento

### Sprint 5: A/B Testing
1. Testar diferentes prompts
2. Comparar resultados
3. Escolher melhor versão automaticamente

### Sprint 6: Coaching Personalizado
1. Prompt adaptado por agente
2. Considerar histórico individual
3. Sugestões baseadas em pontos fracos

---

**Status:** ✅ Sprint 3 Completo
**Data:** 11/01/2026
**Próximo:** Sprint 4 - Dashboard de Aprendizados

## 🎯 Checklist de Conclusão

- [x] EmbeddingService criado
- [x] CoachingLearningService corrigido
- [x] Script de processamento diário
- [x] Migration PostgreSQL
- [x] Documentação completa
- [ ] Cron job configurado (usuário deve fazer)
- [ ] Teste manual executado (usuário deve fazer)
- [ ] Primeiro conhecimento extraído (após uso real)
