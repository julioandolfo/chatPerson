# Sistema de Logs Completo - Kanban Agents

## ✅ Implementado em: 2026-01-10

---

## 📊 Logs Centralizados

Todo o sistema de Kanban Agents agora loga em:
- **`logs/kanban_agents.log`** - Logs detalhados de execução
- **`storage/logs/kanban-agents-cron.log`** - Logs do cron job

### Ver Logs no Browser

Acesse: **`/view-all-logs.php`**

Botões de navegação rápida:
- **Kanban Agents** - Pula para logs de execução manual/automática
- **Kanban Agents Cron** - Pula para logs do scheduler

---

## 🔍 O Que é Logado

### 1. **Início da Execução**
```
[INFO] KanbanAgentService::executeAgent - Iniciando execução do agente 1 (tipo: manual)
[INFO] KanbanAgentService::executeAgent - Agente 'Leads Parados Entrada' (ID: 1) carregado com sucesso
```

### 2. **Busca de Conversas**
```
[INFO] KanbanAgentService::executeAgent - Buscando conversas alvo (funis: [2], etapas: [11])
[INFO] KanbanAgentService::executeAgent - Total de conversas encontradas: 57
```

### 3. **Filtro de Condições Básicas (SEM IA)**
```
[INFO] KanbanAgentService::executeAgent - Separando condições (com e sem IA)
[INFO] KanbanAgentService::executeAgent - Condições sem IA: 1
[INFO] KanbanAgentService::executeAgent - Condições com IA: 0
[INFO] KanbanAgentService::executeAgent - Filtrando conversas com condições básicas (sem IA)...
[INFO] KanbanAgentService::executeAgent - Conversas que passaram no filtro básico: 12 de 57
```

### 4. **Limitação e Análise com IA**
```
[INFO] KanbanAgentService::executeAgent - Limitando análise a 2 conversas (total filtradas: 12)
[INFO] KanbanAgentService::executeAgent - Iniciando análise de 2 conversas com IA
[INFO] KanbanAgentService::executeAgent - ===== Conversa 1/2 =====
[INFO] KanbanAgentService::executeAgent - Chamando OpenAI para análise da conversa 654
[INFO] KanbanAgentService::executeAgent - Análise concluída: Score=70, Sentiment=neutral, Urgency=low
```

### 5. **Execução de Ações**
```
[INFO] KanbanAgentService::executeAgent - Condições ATENDIDAS para conversa 654
[INFO] KanbanAgentService::executeAgent - Executando ações para conversa 654
[INFO] KanbanAgentService::executeAgent - Ações executadas: 3 sucesso(s), 0 erro(s)
```

### 6. **Finalização**
```
[INFO] KanbanAgentService::executeAgent - Loop de conversas finalizado. Total processadas: 2
[INFO] KanbanAgentService::executeAgent - Finalizando execução 13
[INFO] KanbanAgentService::executeAgent - ===== EXECUÇÃO FINALIZADA COM SUCESSO =====
```

---

## 🚀 Nova Lógica de Filtro Inteligente

### ❌ ANTES (Ineficiente):
1. Busca 57 conversas
2. **Limita a 2** (qualquer 2)
3. Analisa com IA (custo!)
4. Avalia condições
5. Executa ações

**Problema**: Analisava conversas que não precisavam!

---

### ✅ AGORA (Eficiente):

1. **Busca 57 conversas** no funil/etapa alvo
2. **Separa condições**:
   - Sem IA: `stage_duration_hours`, `has_tag`, `no_tag`, `assigned_to`, `unassigned`, `has_messages`
   - Com IA: `sentiment`, `score`, `urgency`
3. **Avalia condições SEM IA** em TODAS as 57 conversas (rápido!)
4. **Resultado**: 12 conversas passaram no filtro
5. **Limita a 2** conversas (das 12 filtradas)
6. **Analisa COM IA** apenas as 2 (economia!)
7. **Avalia condições DE IA**
8. **Executa ações** se passou em tudo

**Benefícios**:
- ✅ Mais eficiente
- ✅ Economiza chamadas de IA
- ✅ Mais rápido
- ✅ Analisa as conversas CORRETAS

---

## 📈 Estatísticas Completas

Agora o retorno inclui:

```json
{
  "success": true,
  "message": "57 conversas encontradas, 12 passaram no filtro básico, 2 analisadas com IA, 2 com ações executadas.",
  "stats": {
    "conversations_found": 57,
    "conversations_filtered": 12,
    "conversations_analyzed": 2,
    "conversations_acted_upon": 2,
    "actions_executed": 6,
    "errors_count": 0
  }
}
```

---

## 🐛 Debug de Erros

Se algo der errado, os logs mostram:

```
[ERROR] KanbanAgentService::executeAgent - ERRO ao processar conversa 654
[ERROR] KanbanAgentService::executeAgent - Tipo: Exception
[ERROR] KanbanAgentService::executeAgent - Mensagem: Erro na API OpenAI
[ERROR] KanbanAgentService::executeAgent - Arquivo: KanbanAgentService.php (linha 125)
[ERROR] KanbanAgentService::executeAgent - Stack trace: ...
```

---

## 📝 Logs de Ações por Conversa

### Estrutura dos Logs (Temporariamente Desabilitados)

**Nota**: Os logs individuais de ação (`AIKanbanAgentActionLog::createLog()`) estão **temporariamente desabilitados** devido a um fatal error não identificado. A funcionalidade principal (análise e execução de ações) continua funcionando normalmente.

Quando reabilitado, cada conversa processada terá um registro em `ai_kanban_agent_actions_log` com:
- Resumo da análise
- Score/Sentiment/Urgency
- Condições atendidas (sim/não)
- Detalhes das condições
- Ações executadas
- Sucesso/erro

---

## 🔧 Como Testar

1. **Acesse** `/kanban-agents`
2. **Clique** em "Rodar Agora" em um agente
3. **Veja** no modal de sucesso:
   ```
   57 conversas encontradas, 12 passaram no filtro básico, 
   2 analisadas com IA, 2 com ações executadas.
   ```
4. **Acesse** `/view-all-logs.php`
5. **Clique** no botão "Kanban Agents"
6. **Veja** todos os logs detalhados

---

## ✨ Melhorias Futuras

- [ ] Reabilitar logs individuais de ação (investigar fatal error)
- [ ] Dashboard de estatísticas
- [ ] Gráficos de eficiência (% de conversas filtradas vs analisadas)
- [ ] Exportar logs em CSV/JSON
- [ ] Alertas automáticos de erros

---

## 📚 Arquivos Modificados

1. **`app/Services/KanbanAgentService.php`**
   - Nova lógica de separação de condições
   - Filtro inteligente sem IA primeiro
   - Logs detalhados em cada etapa

2. **`public/view-all-logs.php`**
   - Adicionado `logs/kanban_agents.log`
   - Adicionado `storage/logs/kanban-agents-cron.log`
   - Botões de navegação

3. **`app/Models/Model.php`**
   - Removidos logs excessivos (restaurado ao original)

4. **`app/Helpers/Database.php`**
   - Removidos logs excessivos (restaurado ao original)

5. **`app/Models/AIKanbanAgentActionLog.php`**
   - Removidos logs excessivos (restaurado ao original)

---

**Data da Implementação**: 2026-01-10  
**Desenvolvido com**: Claude Sonnet 4.5 + Cursor AI  
**Status**: ✅ Funcional (logs de ação individuais temporariamente desabilitados)
