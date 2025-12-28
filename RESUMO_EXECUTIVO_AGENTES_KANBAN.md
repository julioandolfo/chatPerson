# 📋 RESUMO EXECUTIVO - AGENTES DE IA PARA KANBAN

**Data**: 2025-01-27  
**Status**: Planejamento

---

## 🎯 O QUE É?

Sistema de **Agentes de IA Especializados para Kanban** que monitoram funis e etapas específicas, analisam conversas periodicamente e executam ações automáticas baseadas em condições configuráveis.

---

## ⚠️ DIFERENÇA DOS AGENTES ATUAIS

| Característica | Agentes Atuais (Automações) | Agentes Kanban (Novo) |
|---|---|---|
| **Quando executam** | Tempo real (quando mensagem chega) | Periódico (a cada X horas/dias) |
| **O que analisam** | Uma conversa por vez | Múltiplas conversas de funil/etapa |
| **Onde funcionam** | Nas automações | No Kanban (funis/etapas) |
| **Objetivo** | Atender conversas | Monitorar e gerenciar conversas |

**São sistemas SEPARADOS para não quebrar o funcionamento atual.**

---

## 💡 EXEMPLO PRÁTICO

**Cenário**: Você tem um funil "Comercial" com etapa "Em Orçamento". Quer que um agente de IA:

1. **A cada 2 dias**, analise todas as conversas dessa etapa
2. **Verifique condições**:
   - Conversa sem resposta há mais de 24 horas?
   - Última mensagem foi do agente (não do cliente)?
   - Conversa não está fechada?
3. **Se condições atendidas**, execute ações:
   - Analise o contexto da conversa com IA
   - Gere mensagem de followup personalizada
   - Envie mensagem ao contato
   - Crie resumo da análise
   - Adicione tag "followup_enviado"

**Resultado**: Followup automático e inteligente sem intervenção manual!

---

## 🎛️ RECURSOS PRINCIPAIS

### 1. Configuração de Funis e Etapas
- Escolher funis específicos (ou todos)
- Escolher etapas específicas (ou todas)
- Múltiplos funis/etapas por agente

### 2. Execução Periódica
- **Por intervalo**: A cada X horas (ex: 48h = 2 dias)
- **Por agendamento**: Dias e horários específicos (ex: Segunda/Quarta/Sexta às 9h)
- **Manual**: Executar quando quiser

### 3. Sistema de Condições Flexível
- **Múltiplos tipos**: Status, mensagens, tags, contato, análise IA, etc
- **Operadores**: AND, OR, NOT
- **Condições customizadas**: SQL ou PHP

### 4. Sistema de Ações Completo
- **Análise**: Analisar conversa com IA
- **Mensagens**: Enviar followup, templates, geradas por IA
- **Movimentação**: Mover para etapa, próximo, anterior, outro funil
- **Atribuição**: Atribuir a agente, departamento, remover atribuição
- **Tags**: Adicionar/remover tags
- **Resumos**: Criar resumos internos ou externos
- **Notas**: Criar notas e atividades
- **Automações**: Disparar automações existentes
- **Notificações**: Notificar usuários

---

## 📊 ESTRUTURA DE DADOS

### Tabelas Principais

1. **`ai_kanban_agents`**
   - Configuração dos agentes
   - Funis/etapas alvo
   - Condições e ações
   - Agendamento

2. **`ai_kanban_agent_executions`**
   - Histórico de execuções
   - Estatísticas (conversas analisadas, ações executadas)
   - Status e erros

3. **`ai_kanban_agent_actions_log`**
   - Log detalhado de cada ação executada
   - Análise feita pela IA
   - Resultados e erros

---

## 🔄 FLUXO DE EXECUÇÃO

```
1. Sistema verifica agentes com next_execution_at <= NOW()
   ↓
2. Para cada agente:
   - Busca conversas do funil/etapa configurados
   - Filtra conforme condições básicas
   ↓
3. Para cada conversa:
   - Monta contexto completo
   - Chama OpenAI para análise
   - Avalia condições configuradas
   - Se condições atendidas: executa ações
   ↓
4. Registra execução e resultados
   ↓
5. Agenda próxima execução
```

---

## 📈 EXEMPLOS DE USO

### Exemplo 1: Followup "Em Orçamento"
- **Funil**: Comercial
- **Etapa**: Em Orçamento
- **Execução**: A cada 2 dias
- **Condições**: Sem resposta há 24h + última mensagem do agente
- **Ações**: Analisar → Enviar followup → Criar resumo → Adicionar tag

### Exemplo 2: Análise e Movimentação
- **Funil**: Comercial
- **Etapa**: Qualificação
- **Execução**: Diariamente às 9h
- **Condições**: No estágio há mais de 24h + score IA > 80
- **Ações**: Analisar → Mover para Proposta (se pronto) → Atribuir agente

### Exemplo 3: Resumo e Atribuição
- **Funil**: Todos
- **Etapa**: Todas
- **Execução**: Semanalmente (Segunda às 8h)
- **Condições**: Sem atribuição + no estágio há mais de 7 dias
- **Ações**: Analisar → Criar resumo → Atribuir departamento → Adicionar tag

---

## 🚀 IMPLEMENTAÇÃO

### Fases Planejadas

1. **Fase 1**: Estrutura base (Migrations, Models, CRUD)
2. **Fase 2**: Sistema de condições
3. **Fase 3**: Sistema de ações
4. **Fase 4**: Sistema de execução periódica
5. **Fase 5**: Interface completa
6. **Fase 6**: Testes e melhorias

**Tempo estimado**: 6-7 semanas

---

## 💰 CUSTOS ESTIMADOS

**Por Execução** (50 conversas analisadas):
- ~100K tokens
- GPT-4: ~$3.00
- GPT-3.5-turbo: ~$0.30

**Mensal** (agente executando a cada 2 dias):
- 15 execuções/mês
- GPT-4: ~$45/mês
- GPT-3.5-turbo: ~$4.50/mês

**Otimizações**:
- Usar GPT-3.5-turbo para análises simples
- Cachear análises recentes
- Limitar contexto histórico

---

## ✅ PRÓXIMOS PASSOS

1. Revisar e aprovar plano detalhado (`PLANO_AGENTES_IA_KANBAN.md`)
2. Definir prioridades de implementação
3. Iniciar Fase 1 (estrutura base)

---

**Documentação Completa**: Ver `PLANO_AGENTES_IA_KANBAN.md`

