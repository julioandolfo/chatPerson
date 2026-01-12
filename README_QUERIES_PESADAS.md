# 🎯 Resumo Executivo - Queries Pesadas Identificadas

## 📍 Resposta Direta à Sua Pergunta

Você pediu para identificar **onde estão rodando as 2 queries mais pesadas do slow.log**.

### Aqui está a resposta:

---

## 🥇 QUERY #1 - Tempo Médio de Resposta (3+ segundos)

### 📍 Localização
```
Arquivo: app/Controllers/ContactController.php
Linha:   315-339
Método:  getHistoryMetrics($id)
Rota:    GET /contacts/{id}/history
```

### 🎯 Quando Executa
- **A CADA clique em uma conversa diferente** na lista do sidebar
- Função JavaScript que chama: `loadContactHistory()` (linha 9016 de `views/conversations/index.php`)

### 💥 Por Que é Crítica
- Subquery correlacionada que examina ~217k linhas
- Usuário navegando entre conversas = múltiplas execuções
- **Principal vilã do seu slow.log**

---

## 🥈 QUERY #2 - Ranking de Agentes (1+ segundo)

### 📍 Localização
```
Arquivo: app/Services/AgentPerformanceService.php
Linha:   253-284
Método:  getAgentsRanking($dateFrom, $dateTo, $limit)
```

### 🎯 Quando Executa
1. **Dashboard**: `DashboardController::index()` (linha 45)
   - Chamada: `DashboardService::getTopAgents()` → `AgentPerformanceService::getAgentsRanking()`
   
2. **Analytics**: `AnalyticsController::getAgentsPerformance()` (linha 306)
   - Chamada direta: `AgentPerformanceService::getAgentsRanking()`

### 💥 Por Que é Pesada
- LEFT JOIN em conversations + messages
- Examina ~768k linhas
- Executa a cada load do dashboard e mudança de filtros

---

## 📊 Tabela de Referência Rápida

| Query | Arquivo | Linha | Método | Rota | Frequência |
|-------|---------|-------|--------|------|------------|
| **#1** | ContactController.php | 315 | getHistoryMetrics() | GET /contacts/{id}/history | 🔴 A cada clique |
| **#2** | AgentPerformanceService.php | 253 | getAgentsRanking() | GET /dashboard<br>GET /api/analytics/agents | 🟡 A cada load/filtro |

---

## 🛠️ Solução Rápida (15 minutos)

Implementar **cache em arquivo** para ambas as queries.

### Resultado Esperado:
- ✅ Query #1: de 3s para 0.01s (na maioria das vezes)
- ✅ Query #2: de 1s para 0.05s (na maioria das vezes)
- ✅ CPU: de 70% para 30%
- ✅ Slow log: de 100+ para 10-20 queries/hora

### Como Fazer:
👉 Ver arquivo **`SOLUCAO_QUERIES_PESADAS.md`** com código pronto para copiar/colar

---

## 📚 Documentação Completa

Criei 4 documentos para você:

1. **`README_QUERIES_PESADAS.md`** ← Você está aqui (resumo executivo)
2. **`QUERIES_PESADAS_MAPEAMENTO.md`** → Detalhamento técnico completo
3. **`SOLUCAO_QUERIES_PESADAS.md`** → Código pronto para implementar cache
4. **`FLUXO_QUERIES_PESADAS.md`** → Diagramas visuais de fluxo

---

## 🚀 Próximos Passos Sugeridos

### Imediato (hoje):
1. ✅ Implementar cache na Query #1 (ContactController)
2. ✅ Implementar cache na Query #2 (AgentPerformanceService)
3. ✅ Monitorar slow.log para confirmar melhoria

### Curto Prazo (esta semana):
4. Adicionar índices compostos:
   ```sql
   CREATE INDEX idx_msg_conv_sender_date ON messages(conversation_id, sender_type, created_at);
   CREATE INDEX idx_conv_contact ON conversations(contact_id);
   CREATE INDEX idx_conv_agent_date ON conversations(agent_id, created_at, status);
   ```

### Médio Prazo (próximas semanas):
5. Substituir subquery correlacionada por window function (MySQL 8.0+)
6. Criar tabela materializada para histórico de contatos
7. Implementar job assíncrono para pré-calcular métricas

---

## ⚠️ Prioridade de Ação

```
🔴 CRÍTICO - FAZER HOJE
   ↓
   Query #1 (ContactController)
   - É a mais pesada
   - Executa centenas de vezes por dia
   - Impacto direto na UX do usuário
   
   ↓
   
🟡 IMPORTANTE - FAZER ESTA SEMANA
   ↓
   Query #2 (AgentPerformanceService)
   - Pesada mas menos frequente
   - Impacta apenas load do dashboard
   
   ↓
   
🟢 MELHORIAS FUTURAS
   ↓
   Índices, otimizações de query, tabelas materializadas
```

---

## 📞 Precisa de Mais Ajuda?

Se precisar de:
- ✅ Implementação do código de cache → Ver `SOLUCAO_QUERIES_PESADAS.md`
- ✅ Entender o fluxo completo → Ver `FLUXO_QUERIES_PESADAS.md`
- ✅ Detalhes técnicos → Ver `QUERIES_PESADAS_MAPEAMENTO.md`

---

**Data**: 2026-01-12  
**Identificação**: ✅ Completa  
**Solução**: ✅ Documentada  
**Pronto para**: 🚀 Implementação

