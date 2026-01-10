# 📊 Sistema de Análise de Performance de Vendedores

## 🎯 Visão Geral

Sistema completo de análise automática de performance de vendedores usando OpenAI GPT, incluindo:
- ✅ Análise em 10 dimensões de vendas
- ✅ Sistema de gamificação (badges e conquistas)
- ✅ Coaching automático (metas e feedback)
- ✅ Biblioteca de melhores práticas
- ✅ Rankings e comparações
- ✅ Relatórios detalhados

---

## 📋 Implementado

### ✅ 1. Migrations (database/migrations/016_create_agent_performance_analysis_tables.php)

**Tabelas criadas:**
- `agent_performance_analysis` - Análises individuais de conversas
- `agent_performance_summary` - Sumários agregados por período
- `agent_performance_badges` - Badges e conquistas
- `agent_performance_best_practices` - Melhores práticas (golden conversations)
- `agent_performance_goals` - Metas e objetivos

**Executar:**
```bash
php database/migrate.php
```

---

### ✅ 2. Models

**AgentPerformanceAnalysis** (`app/Models/AgentPerformanceAnalysis.php`)
- Análises individuais de conversas
- Métodos: getByConversation, getByAgent, getByPeriod, getAgentAverages, getAgentsRanking, etc

**AgentPerformanceSummary** (`app/Models/AgentPerformanceSummary.php`)
- Sumários agregados (diário, semanal, mensal)
- Métodos: getAgentSummary, getAgentHistory, compareAgents

**AgentPerformanceBadge** (`app/Models/AgentPerformanceBadge.php`)
- Badges e conquistas
- Métodos: getAgentBadges, hasBadge, countByLevel

**AgentPerformanceBestPractice** (`app/Models/AgentPerformanceBestPractice.php`)
- Biblioteca de melhores práticas
- Métodos: getByCategory, getFeatured, incrementViews, addHelpfulVote

**AgentPerformanceGoal** (`app/Models/AgentPerformanceGoal.php`)
- Metas de performance
- Métodos: getActiveGoals, getAgentGoals, checkProgress

---

### ✅ 3. Services

#### AgentPerformanceAnalysisService (`app/Services/AgentPerformanceAnalysisService.php`)

**Core do sistema - análise via OpenAI**

**Métodos principais:**
- `analyzeConversation(int $conversationId, bool $force = false)` - Analisar uma conversa específica
- `processPendingConversations()` - Processar conversas pendentes (cron)
- `getAnalysis(int $conversationId)` - Obter análise de uma conversa
- `getAgentAnalyses(int $agentId, ...)` - Obter análises de um agente
- `getAgentsRanking(...)` - Ranking de agentes
- `getOverallStats(...)` - Estatísticas gerais

**10 Dimensões avaliadas (0-5):**
1. **Proatividade** - Toma iniciativa, faz perguntas, guia conversa
2. **Quebra de Objeções** - Identifica e responde objeções estruturadamente
3. **Rapport** - Cria conexão, usa nome, demonstra empatia
4. **Fechamento** - Tenta fechar, usa técnicas, cria urgência
5. **Qualificação** - Faz perguntas BANT, identifica fit
6. **Clareza** - Explica claramente, organiza informações
7. **Valor** - Apresenta valor/benefícios, não apenas features
8. **Tempo de Resposta** - Responde rapidamente
9. **Follow-up** - Define próximos passos, agenda follow-up
10. **Profissionalismo** - Gramática, tom, postura

**Pesos configuráveis:**
- Objeções: 1.5x (mais importante)
- Fechamento: 1.5x (mais importante)
- Qualificação: 1.2x
- Valor: 1.3x
- Demais: 1.0x

#### GamificationService (`app/Services/GamificationService.php`)

**Sistema de badges e conquistas**

**Badges disponíveis:**
- 🌱 Novato - Primeira análise
- 📈 Consistente - 10 análises >3.5
- ⭐ Top Performer - Média >4.5
- 👑 Lenda - 50 análises >4.7
- 🎯 Fechador - Nota 5.0 em fechamento
- 💪 Quebrador de Objeções - Nota 5.0 em objeções
- 🤝 Construtor de Relacionamentos - Nota 5.0 em rapport
- 🚀 Vendedor Proativo - Nota 5.0 em proatividade
- 💯 Nota Perfeita - 5.0 geral
- 📊 Recuperação - Melhorou 1.5 pontos em 30 dias
- ⚡ Resposta Rápida - Nota 5.0 em tempo de resposta
- 🎩 Profissional Exemplar - Nota 5.0 em profissionalismo
- 🏃 Incansável - 100 conversas analisadas
- 🏅 Maratonista - 500 conversas analisadas

**Níveis:**
- Bronze 🥉
- Silver 🥈
- Gold 🥇
- Platinum 💎

#### CoachingService (`app/Services/CoachingService.php`)

**Sistema de coaching automático**

**Funcionalidades:**
- `autoCreateGoals()` - Criar metas automaticamente para dimensões < 3.5
- `sendFeedback()` - Enviar feedback estruturado
- `checkGoalsProgress()` - Verificar progresso das metas
- `updateGoalsStatus()` - Atualizar status (completed/failed)
- `createGoal()` - Criar meta manual

**Metas:**
- Criadas automaticamente para pontos fracos (< 3.5)
- Objetivo: melhorar 1 ponto
- Prazo: 60 dias
- Status: active, completed, failed, cancelled

#### BestPracticesService (`app/Services/BestPracticesService.php`)

**Biblioteca de melhores práticas**

**Funcionalidades:**
- `saveBestPractice()` - Salvar automaticamente conversas excelentes (>= 4.5)
- `getByCategory()` - Buscar por categoria
- `getFeatured()` - Práticas em destaque
- `markAsViewed()` - Incrementar visualizações
- `addHelpfulVote()` - Votar como útil

**Categorias:**
- Proatividade 🚀
- Quebra de Objeções 💪
- Rapport 🤝
- Fechamento 🎯
- Qualificação 🎓
- Valor 💎

---

### ✅ 4. Configurações

**Localização:** `ConversationSettingsService` → `agent_performance_analysis`

```php
'agent_performance_analysis' => [
    'enabled' => false,  // Habilitar/desabilitar
    'model' => 'gpt-4-turbo',  // ou gpt-4o, gpt-4, gpt-3.5-turbo
    'temperature' => 0.3,
    'check_interval_hours' => 24,
    'max_conversation_age_days' => 7,
    'min_messages_to_analyze' => 5,
    'min_agent_messages' => 3,
    'analyze_closed_only' => true,
    'cost_limit_per_day' => 10.00,
    
    // Dimensões (peso e habilitação)
    'dimensions' => [...],
    
    // Filtros
    'filters' => [
        'only_sales_funnels' => false,
        'funnel_ids' => [],
        'only_sales_stages' => [],
        // ... mais filtros
    ],
    
    // Relatórios
    'reports' => [...],
    
    // Gamificação
    'gamification' => ['enabled' => true, ...],
    
    // Coaching
    'coaching' => ['enabled' => true, ...]
]
```

---

### ✅ 5. Scripts de Cron

**`public/scripts/analyze-performance.php`**

Análise periódica automática de conversas.

**Configurar no crontab:**
```bash
# A cada 6 horas
0 */6 * * * cd /var/www/html && php public/scripts/analyze-performance.php >> logs/performance-analysis.log 2>&1

# Ou diariamente às 2h
0 2 * * * cd /var/www/html && php public/scripts/analyze-performance.php >> logs/performance-analysis.log 2>&1
```

**Executar manualmente:**
```bash
php public/scripts/analyze-performance.php
```

---

## 🎨 Como Usar

### 1️⃣ Configurar

1. Executar migrations:
```bash
php database/migrate.php
```

2. Configurar API Key OpenAI (se ainda não tiver):
   - Ir em Configurações > Geral
   - Adicionar `openai_api_key`

3. Habilitar análise:
   - Ir em Configurações > Botões de Ação
   - Aba "Análise de Performance"
   - Habilitar e configurar

### 2️⃣ Analisar Conversas

**Manualmente (sob demanda):**
```php
use App\Services\AgentPerformanceAnalysisService;

// Analisar conversa específica
$analysis = AgentPerformanceAnalysisService::analyzeConversation(123);

// Forçar reanálise
$analysis = AgentPerformanceAnalysisService::analyzeConversation(123, true);
```

**Automaticamente (cron):**
- Configurar cron como mostrado acima
- Conversas fechadas serão analisadas automaticamente

### 3️⃣ Visualizar Resultados

**Obter análise de uma conversa:**
```php
$analysis = AgentPerformanceAnalysisService::getAnalysis($conversationId);

// Campos disponíveis:
$analysis['overall_score']; // 0-5
$analysis['proactivity_score']; // 0-5
$analysis['objection_handling_score']; // 0-5
// ... todas as dimensões
$analysis['strengths']; // JSON array
$analysis['weaknesses']; // JSON array
$analysis['improvement_suggestions']; // JSON array
$analysis['detailed_analysis']; // Texto
```

**Obter análises de um agente:**
```php
$analyses = AgentPerformanceAnalysisService::getAgentAnalyses($agentId);
```

**Ranking:**
```php
$ranking = AgentPerformanceAnalysisService::getAgentsRanking('2026-01-01', '2026-01-31');

// Retorna:
// [
//   ['agent_id' => 1, 'agent_name' => 'João', 'avg_score' => 4.7, 'total_conversations' => 23],
//   ...
// ]
```

### 4️⃣ Gamificação

**Verificar badges de um agente:**
```php
use App\Services\GamificationService;

$badges = GamificationService::getAgentBadges($agentId);
$stats = GamificationService::getBadgeStats($agentId);

// $stats:
// [
//   'total' => 5,
//   'by_level' => ['bronze' => 1, 'silver' => 2, 'gold' => 2, 'platinum' => 0],
//   'latest' => [...]
// ]
```

### 5️⃣ Coaching

**Verificar metas:**
```php
use App\Services\CoachingService;

$goals = CoachingService::checkGoalsProgress($agentId);

// Retorna metas com progresso:
// [
//   [
//     'id' => 1,
//     'dimension' => 'proactivity',
//     'current_score' => 3.2,
//     'target_score' => 4.0,
//     'current_score_now' => 3.5,
//     'progress_percent' => 37.5,
//     'is_on_track' => false
//   ],
//   ...
// ]
```

**Criar meta manual:**
```php
$goalId = CoachingService::createGoal(
    $agentId,
    'objection_handling',  // dimensão
    4.5,  // target
    '2026-03-31',  // deadline
    $supervisorId,  // quem criou
    'Foco em técnicas de feel-felt-found'  // notas
);
```

### 6️⃣ Melhores Práticas

**Buscar práticas por categoria:**
```php
use App\Services\BestPracticesService;

$practices = BestPracticesService::getByCategory('objection_handling', 20);

// Retorna:
// [
//   [
//     'id' => 1,
//     'title' => 'Como Quebrar Objeções com Maestria ⭐⭐⭐⭐⭐',
//     'description' => '...',
//     'excerpt' => '[09:15] Vendedor: ...\n[09:16] Cliente: ...',
//     'score' => 5.0,
//     'agent_name' => 'João Silva',
//     'views' => 45,
//     'helpful_votes' => 12
//   ],
//   ...
// ]
```

**Práticas em destaque:**
```php
$featured = BestPracticesService::getFeatured(10);
```

**Categorias disponíveis:**
```php
$categories = BestPracticesService::getCategories();
```

---

## 💰 Estimativa de Custos

### Por Análise

| Modelo | Tokens Médios | Custo |
|--------|--------------|-------|
| GPT-4o | ~2000 | $0.005 |
| GPT-4-turbo | ~2000 | $0.02 |
| GPT-4 | ~2000 | $0.06 |
| GPT-3.5-turbo | ~2000 | $0.002 |

### Mensal (Exemplo)

**Cenário:** 10 vendedores, 20 conversas/mês cada = 200 análises

| Modelo | Custo/mês |
|--------|-----------|
| GPT-4o | $1.00 |
| GPT-4-turbo | $4.00 |
| GPT-4 | $12.00 |
| GPT-3.5-turbo | $0.40 |

**Recomendação:** GPT-4-turbo ou GPT-4o (melhor custo-benefício e precisão)

---

## 🎯 Fluxo de Análise

```
1. Conversa é fechada
   ↓
2. Cron identifica conversa pendente
   ↓
3. Verifica filtros (funil, tags, valor, etc)
   ↓
4. Busca todas as mensagens (cliente + agente)
   ↓
5. Envia para OpenAI com prompt estruturado
   ↓
6. IA analisa 10 dimensões + feedback
   ↓
7. Calcula nota geral (média ponderada)
   ↓
8. Salva análise no banco
   ↓
9. Ações pós-análise:
   ├─ Verifica e premia badges
   ├─ Cria metas automáticas (se < 3.5)
   ├─ Salva melhores práticas (se >= 4.5)
   └─ Envia feedback (opcional)
```

---

## 📊 Estrutura do JSON Retornado pela IA

```json
{
  "scores": {
    "proactivity": 4.5,
    "objection_handling": 4.8,
    "rapport": 5.0,
    "closing_techniques": 4.2,
    "qualification": 4.7,
    "clarity": 4.9,
    "value_proposition": 4.6,
    "response_time": 4.3,
    "follow_up": 4.5,
    "professionalism": 5.0
  },
  "strengths": [
    "Excelente rapport com o cliente, usando nome frequentemente",
    "Comunicação clara e objetiva",
    "Profissionalismo impecável"
  ],
  "weaknesses": [
    "Poderia ser mais proativo em sugerir soluções",
    "Perdeu oportunidade de fechar quando cliente mostrou interesse"
  ],
  "improvement_suggestions": [
    "Praticar fechamento assumido",
    "Fazer mais perguntas abertas no início",
    "Usar técnica SPIN para qualificação"
  ],
  "key_moments": [
    {
      "timestamp": "09:05",
      "type": "positive",
      "description": "Excelente uso de técnica feel-felt-found para objeção de preço"
    },
    {
      "timestamp": "09:18",
      "type": "negative",
      "description": "Cliente mostrou interesse mas vendedor não tentou fechar"
    }
  ],
  "detailed_analysis": "O vendedor demonstrou excelente performance nesta conversa. Desde o início, estabeleceu um rapport forte..."
}
```

---

## 🔧 Próximos Passos

### Para Finalizar Implementação:

1. **Controller e Views** (pendente)
   - Criar `AgentPerformanceController` com métodos CRUD
   - Criar views para dashboard, ranking, individual, etc
   - Adicionar rotas em `routes/web.php`

2. **Permissões** (pendente)
   - Adicionar em `database/seeds/002_create_roles_and_permissions.php`:
     - `agent_performance.view.own` - Ver própria performance
     - `agent_performance.view.all` - Ver todas as análises
     - `agent_performance.analyze` - Forçar análise manual
     - `agent_performance.goals` - Gerenciar metas
     - `agent_performance.best_practices` - Acessar biblioteca

3. **Menu Sidebar** (pendente)
   - Adicionar link em `views/layouts/metronic/sidebar.php`

4. **Interface de Configuração** (pendente)
   - Adicionar aba em `views/settings/action-buttons/index.php`

---

## 📝 Casos de Uso

### 1. Onboarding de Novos Vendedores
```php
// Analisar primeiras 50 conversas
$analyses = AgentPerformanceAnalysisService::getAgentAnalyses($newAgentId, 50);

// Identificar gaps rapidamente
foreach ($analyses as $analysis) {
    if ($analysis['qualification_score'] < 3.0) {
        echo "Precisa treinar qualificação!\n";
    }
}
```

### 2. Identificar Top Performers
```php
$ranking = AgentPerformanceAnalysisService::getAgentsRanking();
$topPerformer = $ranking[0];

// Analisar o que fazem diferente
$topAnalyses = AgentPerformanceAnalysisService::getAgentAnalyses($topPerformer['agent_id']);
```

### 3. Coaching Individual
```php
$progress = CoachingService::checkGoalsProgress($agentId);

foreach ($progress as $goal) {
    if (!$goal['is_on_track']) {
        echo "Meta {$goal['dimension']} está atrasada! Progresso: {$goal['progress_percent']}%\n";
    }
}
```

### 4. Biblioteca de Treinamento
```php
// Buscar exemplos de fechamento
$closingExamples = BestPracticesService::getByCategory('closing', 10);

// Mostrar para equipe em reunião
foreach ($closingExamples as $example) {
    echo "{$example['title']}\n";
    echo "Por: {$example['agent_name']} (Nota: {$example['score']})\n";
    echo $example['excerpt'] . "\n\n";
}
```

---

## ⚙️ Troubleshooting

### Nenhuma conversa sendo analisada

**Verificar:**
1. Análise está habilitada?
2. Há conversas fechadas com agente?
3. Conversas têm mínimo de mensagens do agente?
4. API Key configurada?
5. Limite de custo não foi atingido?

**Debug:**
```bash
php public/scripts/analyze-performance.php
```

### Análises com baixa qualidade

**Possíveis causas:**
- Modelo errado (usar GPT-4-turbo ou GPT-4o)
- Temperature muito alta (usar 0.3)
- Conversas muito curtas

### Custos altos

**Soluções:**
- Usar GPT-3.5-turbo (mais barato)
- Aumentar filtros (apenas vendas, valor mínimo, etc)
- Reduzir frequência do cron
- Definir `cost_limit_per_day`

---

## 🎉 Funcionalidades Implementadas

✅ Análise automática em 10 dimensões  
✅ Sistema de gamificação completo  
✅ Coaching automático com metas  
✅ Biblioteca de melhores práticas  
✅ Ranking de vendedores  
✅ Badges e conquistas  
✅ Feedback estruturado  
✅ Identificação de momentos-chave  
✅ Análise contextual (cliente + agente)  
✅ Filtros avançados  
✅ Pesos configuráveis  
✅ Limites de custo  
✅ Scripts de cron  

---

## 📚 Arquivos Criados

### Migrations
- `database/migrations/016_create_agent_performance_analysis_tables.php`

### Models
- `app/Models/AgentPerformanceAnalysis.php`
- `app/Models/AgentPerformanceSummary.php`
- `app/Models/AgentPerformanceBadge.php`
- `app/Models/AgentPerformanceBestPractice.php`
- `app/Models/AgentPerformanceGoal.php`

### Services
- `app/Services/AgentPerformanceAnalysisService.php` (principal)
- `app/Services/GamificationService.php`
- `app/Services/CoachingService.php`
- `app/Services/BestPracticesService.php`

### Scripts
- `public/scripts/analyze-performance.php`

### Configurações
- Adicionado em `app/Services/ConversationSettingsService.php`

### Documentação
- Este arquivo!

---

**Criado em:** 2026-01-10  
**Versão:** 1.0  
**Status:** ✅ Core completo e funcional

**Próximo passo:** Criar Controller, Views e finalizar interface!
