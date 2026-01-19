# 📋 Conversas Analisadas - Dashboard de Coaching

## Visão Geral

Esta funcionalidade permite visualizar as conversas que foram analisadas pelo sistema de coaching em tempo real, incluindo:
- Hints fornecidos aos agentes
- Métricas de performance (10 dimensões)
- Resultados e conversões
- Pontos fortes e fracos identificados

## Localização

**Rota:** `/coaching/dashboard`

**Seção:** "Conversas Analisadas - Métricas de Coaching" (abaixo dos cards principais)

## Recursos

### 1. Listagem de Conversas Analisadas

Cada conversa exibe:

#### Cabeçalho
- ID da conversa e nome do contato
- Status da conversa (aberta, pendente, resolvida, fechada)
- Nome do agente responsável
- Data/hora de criação
- Canal de comunicação

#### Cards de Métricas Rápidas
- **Score Geral** - Nota geral de performance (0-5)
- **Hints Dados** - Total de hints fornecidos + quantos foram úteis
- **Resultado** - Outcome da conversa (convertida, fechada, escalada, abandonada)
- **Valor Venda** - Valor da venda se convertida
- **Melhoria** - Score de melhoria de performance
- **Sugestões Usadas** - Quantas sugestões o agente utilizou

#### 10 Dimensões de Performance
Quando disponível, mostra avaliação detalhada em:
1. 🎯 **Proatividade** - Iniciativa e proatividade do agente
2. 🛡️ **Quebra de Objeções** - Habilidade em lidar com objeções
3. 🤝 **Rapport** - Construção de relacionamento
4. ✅ **Técnicas de Fechamento** - Habilidade de fechamento
5. 🔍 **Qualificação** - Qualificação do lead
6. 💬 **Clareza** - Clareza na comunicação
7. 💎 **Proposta de Valor** - Apresentação de valor
8. ⚡ **Tempo de Resposta** - Velocidade de resposta
9. 📅 **Follow-up** - Acompanhamento pós-atendimento
10. 🎩 **Profissionalismo** - Postura profissional

Cada dimensão recebe nota de 0 a 5 com badge colorido:
- **Verde (≥4)** - Excelente
- **Azul (≥3)** - Bom
- **Amarelo (≥2)** - Precisa melhorar
- **Vermelho (<2)** - Crítico

#### Pontos Fortes e Fracos
- Lista os principais pontos fortes identificados
- Lista os principais pontos a melhorar
- Mostra os 3 primeiros de cada com indicação de "mais..."

### 2. Paginação

- Carrega inicialmente **10 conversas**
- Botão "Carregar Mais Conversas" para buscar próximas 10
- Paginação via AJAX sem recarregar a página
- Indica total de conversas disponíveis no período

### 3. Modal de Detalhes

Ao clicar em "Ver Detalhes":

- **Resumo Completo**
  - Dados do contato (nome, telefone)
  - Dados do agente
  - Status e resultado da conversa

- **Hints de Coaching (Accordion)**
  - Lista todos os hints fornecidos
  - Mostra feedback (útil, não útil, sem feedback)
  - Tipo do hint e horário
  - Texto do hint
  - Sugestões fornecidas (JSON formatado)

- **Feedback Específico**
  - Análise textual detalhada da performance

- **Link para Conversa Completa**
  - Botão para abrir a conversa no sistema

## Filtros

A listagem respeita os filtros do dashboard:

- **Período:** Hoje / Esta Semana / Este Mês
- **Agente:** Filtrar por agente específico (apenas para admins/supervisores)

## Permissões

- **coaching.view** - Necessária para visualizar
- Agentes veem apenas suas próprias conversas
- Admins/Supervisores veem todas as conversas

## Estrutura Técnica

### Backend

#### Service
**Arquivo:** `app/Services/CoachingMetricsService.php`

**Método:** `getAnalyzedConversations()`

```php
CoachingMetricsService::getAnalyzedConversations(
    ?int $agentId = null,
    string $period = 'week',
    int $page = 1,
    int $perPage = 10
): array
```

**Retorno:**
```php
[
    'conversations' => [...], // Array de conversas com todas as métricas
    'total' => int,          // Total de conversas no período
    'page' => int,           // Página atual
    'per_page' => int,       // Itens por página
    'total_pages' => int,    // Total de páginas
    'has_more' => bool       // Se há mais conversas
]
```

#### Controller
**Arquivo:** `app/Controllers/CoachingDashboardController.php`

**Métodos:**
- `index()` - Renderiza dashboard com primeiras 10 conversas
- `getAnalyzedConversationsAjax()` - API para paginação e detalhes

#### Rotas
**Arquivo:** `routes/web.php`

```php
// Dashboard principal
Router::get('/coaching/dashboard', [CoachingDashboardController::class, 'index']);

// API AJAX para paginação
Router::get('/api/coaching/analyzed-conversations', [CoachingDashboardController::class, 'getAnalyzedConversationsAjax']);
```

### Frontend

#### View
**Arquivo:** `views/coaching/dashboard.php`

**Seção:** Conversas Analisadas (após os cards de KPIs e ranking)

#### JavaScript

**Funções:**
- `loadMoreConversations` - Event listener do botão "Carregar Mais"
- `renderConversations(conversations)` - Renderiza HTML das conversas
- `showConversationDetails(conversationId)` - Abre modal com detalhes

**Endpoint AJAX:**
```javascript
GET /api/coaching/analyzed-conversations
Params:
  - page: número da página
  - period: today|week|month
  - agent_id: (opcional) filtrar por agente
  - conversation_id: (opcional) buscar conversa específica
```

## Tabelas Utilizadas

### `coaching_conversation_impact`
Armazena métricas de impacto do coaching na conversa:
- Hints utilizados (total, úteis, não úteis)
- Tempo de resposta antes/depois
- Resultado da conversa (outcome)
- Valor de venda
- Score de melhoria

### `agent_performance_analysis`
Armazena análise detalhada de performance:
- 10 dimensões de avaliação (scores 0-5)
- Score geral
- Pontos fortes (JSON)
- Pontos fracos (JSON)
- Feedback específico (texto)

### `realtime_coaching_hints`
Armazena hints fornecidos em tempo real:
- Tipo do hint
- Texto do hint
- Sugestões (JSON)
- Feedback (helpful, not_helpful, null)
- Data/hora de visualização

### `conversations`, `users`, `contacts`
Joins para dados básicos de conversa, agente e contato

## Exemplos de Uso

### Visualizar Conversas do Período
1. Acesse `/coaching/dashboard`
2. Selecione período desejado (hoje/semana/mês)
3. Role até "Conversas Analisadas"
4. Veja as últimas 10 conversas com métricas

### Carregar Mais Conversas
1. Na seção "Conversas Analisadas"
2. Clique em "Carregar Mais Conversas"
3. Mais 10 conversas serão adicionadas à lista

### Ver Detalhes de uma Conversa
1. Clique em "Ver Detalhes" na conversa desejada
2. Modal abrirá com:
   - Resumo completo
   - Todos os hints dados (accordion)
   - Feedback específico
3. Clique em "Ver Conversa Completa" para abrir no sistema

### Filtrar por Agente (Admins)
1. No topo do dashboard, selecione um agente no filtro
2. Apenas conversas daquele agente serão exibidas

## Futuras Melhorias

- [ ] Export CSV das conversas analisadas
- [ ] Filtros adicionais (canal, resultado, score mínimo)
- [ ] Gráficos de evolução de métricas ao longo do tempo
- [ ] Comparação entre agentes
- [ ] Anotações e comentários do supervisor
- [ ] Sistema de metas por dimensão
- [ ] Alertas para scores baixos
- [ ] Relatórios PDF individuais por conversa

## Troubleshooting

### Conversas não aparecem
- Verificar se há conversas com coaching no período
- Verificar permissão `coaching.view`
- Verificar se tabelas `coaching_conversation_impact` e `agent_performance_analysis` existem

### Botão "Carregar Mais" não funciona
- Verificar console do navegador para erros JavaScript
- Verificar se rota `/api/coaching/analyzed-conversations` está registrada
- Verificar permissões de API

### Modal não abre
- Verificar se Bootstrap está carregado
- Verificar console para erros de fetch
- Verificar se conversa específica existe

## Logs

Para debug, verificar:
- Console do navegador (Network tab)
- Logs do PHP em `storage/logs/`
- Response da API em `/api/coaching/analyzed-conversations`
