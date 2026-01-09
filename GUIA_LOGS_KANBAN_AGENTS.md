# 📊 GUIA COMPLETO - Logs dos Agentes de Kanban

**Data**: 09/01/2025  
**Status**: Sistema de Logs Implementado

---

## 🎯 VISÃO GERAL

O sistema de Agentes de Kanban agora possui **logging completo e detalhado** de todas as operações, permitindo:
- ✅ Rastrear execuções passo a passo
- ✅ Identificar problemas rapidamente
- ✅ Auditar ações executadas
- ✅ Monitorar performance
- ✅ Debug de configurações

---

## 📁 ARQUIVOS DE LOG

### 1. **`logs/kanban_agents.log`**
**Propósito**: Log principal das operações dos Agentes de Kanban

**Conteúdo**:
- Execuções de agentes (início, fim, estatísticas)
- Análises de conversas
- Avaliação de condições
- Execução de ações
- Erros e exceções

**Exemplo**:
```
[2025-01-09 14:30:00] INFO: KanbanAgentService::executeAgent - Iniciando execução do agente 1 (tipo: manual)
[2025-01-09 14:30:01] INFO: KanbanAgentService::executeAgent - Agente 'Followup Em Orçamento' (ID: 1) carregado com sucesso
[2025-01-09 14:30:02] INFO: KanbanAgentService::executeAgent - Total de conversas encontradas: 15
[2025-01-09 14:30:05] INFO: KanbanAgentService::executeAgent - Analisando conversa 123
[2025-01-09 14:30:08] INFO: KanbanAgentService::executeAgent - Condições ATENDIDAS para conversa 123
[2025-01-09 14:30:10] INFO: KanbanAgentService::actionAddTag - Tag 'followup_enviado' adicionada com sucesso
[2025-01-09 14:30:12] INFO: KanbanAgentService::executeAgent - Agente executado com sucesso. 15 conversas analisadas, 8 com ações executadas
```

### 2. **`storage/logs/kanban-agents-cron.log`**
**Propósito**: Log das execuções via cron (automáticas)

**Conteúdo**:
- Saída do script `run-kanban-agents.php`
- Resumo de cada execução
- Erros do cron

**Exemplo**:
```
[2025-01-09 15:00:00] INFO: run-kanban-agents.php - Iniciando execução de agentes Kanban
[2025-01-09 15:00:05] INFO: run-kanban-agents.php - Agente 1 (Followup Em Orçamento) executado com sucesso
[2025-01-09 15:00:06] INFO: run-kanban-agents.php - Execução concluída: 1 sucesso(s), 0 erro(s)
✅ Execução concluída: 1 sucesso(s), 0 erro(s)
```

---

## 🔍 O QUE É LOGADO

### Nível 1: Execução do Agente

```
✅ Início da execução (ID, tipo, nome do agente)
✅ Carregamento do agente (configurações)
✅ Busca de conversas alvo (funis, etapas, filtros)
✅ Total de conversas encontradas
✅ Limite aplicado (se houver)
✅ Estatísticas finais (conversas analisadas, ações executadas, erros)
✅ Próxima execução agendada
```

### Nível 2: Análise de Conversa

```
✅ Conversa sendo analisada (ID)
✅ Chamada à OpenAI API
✅ Resultado da análise (score, sentiment, urgency)
```

### Nível 3: Avaliação de Condições

```
✅ Início da avaliação
✅ Resultado (ATENDIDAS ou NÃO ATENDIDAS)
✅ Detalhes de cada condição avaliada
```

### Nível 4: Execução de Ações

```
✅ Tipo de ação sendo executada
✅ Parâmetros da ação
✅ Resultado da ação (sucesso/erro)
✅ Dados específicos (ex: tags adicionadas, mensagem enviada)
```

### Nível 5: Erros

```
❌ Erros críticos (stack trace completo)
⚠️ Warnings (ex: tag não encontrada)
```

---

## 📺 VISUALIZADOR DE LOGS

### Como Acessar

```
http://seu-dominio/view-all-logs.php
```

### Recursos do Visualizador

✅ **Visualização em tempo real** de todos os logs  
✅ **Botão "Atualizar"** para recarregar logs  
✅ **Navegação rápida** para cada seção de log  
✅ **Cores diferentes** para tipos de mensagens:
- 🔵 Azul: Informações normais
- 🟢 Verde: Sucessos
- 🟡 Amarelo: Warnings
- 🔴 Vermelho: Erros

✅ **Últimas 100 linhas** de cada log (mais recentes primeiro)  
✅ **Timestamps destacados**  
✅ **Formato monospace** para fácil leitura

### Logs Disponíveis no Visualizador

1. **Aplicação** - Log geral da aplicação
2. **Conversas** - Log de conversas
3. **Quepasa** - Log do WhatsApp Quepasa
4. **Automação** - Log de automações
5. **AI Agent** - Log de agentes de IA (automações)
6. **AI Tools** - Log de ferramentas de IA
7. **Kanban Agents** ⭐ - Log dos Agentes de Kanban
8. **Kanban Agents Cron** ⭐ - Log das execuções automáticas
9. **Erros PHP** - Log de erros PHP

---

## 🔎 EXEMPLOS DE LOGS

### Exemplo 1: Execução Manual Bem-Sucedida

```
[2025-01-09 14:30:00] INFO: KanbanAgentService::executeAgent - Iniciando execução do agente 1 (tipo: manual)
[2025-01-09 14:30:01] INFO: KanbanAgentService::executeAgent - Agente 'Followup Em Orçamento' (ID: 1) carregado com sucesso
[2025-01-09 14:30:02] INFO: KanbanAgentService::getTargetConversations - Filtrando por funis: 1
[2025-01-09 14:30:02] INFO: KanbanAgentService::getTargetConversations - Filtrando por etapas: 5
[2025-01-09 14:30:03] INFO: KanbanAgentService::getTargetConversations - Retornando 15 conversas
[2025-01-09 14:30:03] INFO: KanbanAgentService::executeAgent - Total de conversas encontradas: 15
[2025-01-09 14:30:03] INFO: KanbanAgentService::executeAgent - Iniciando análise de 15 conversas

[2025-01-09 14:30:04] INFO: KanbanAgentService::executeAgent - Analisando conversa 123 (total analisadas: 1)
[2025-01-09 14:30:04] INFO: KanbanAgentService::executeAgent - Chamando OpenAI para análise da conversa 123
[2025-01-09 14:30:07] INFO: KanbanAgentService::executeAgent - Análise concluída para conversa 123: Score=85, Sentiment=positive, Urgency=medium
[2025-01-09 14:30:07] INFO: KanbanAgentService::executeAgent - Avaliando condições para conversa 123
[2025-01-09 14:30:08] INFO: KanbanAgentService::executeAgent - Condições ATENDIDAS para conversa 123
[2025-01-09 14:30:08] INFO: KanbanAgentService::executeAgent - Executando ações para conversa 123 (total com ações: 1)

[2025-01-09 14:30:08] INFO: KanbanAgentService::executeSingleAction - Executando ação 'add_tag' na conversa 123
[2025-01-09 14:30:08] INFO: KanbanAgentService::actionAddTag - Tags a adicionar: [1,5]
[2025-01-09 14:30:09] INFO: KanbanAgentService::actionAddTag - Adicionando tag ID 1 à conversa 123
[2025-01-09 14:30:09] INFO: KanbanAgentService::actionAddTag - Tag 'followup_enviado' adicionada com sucesso
[2025-01-09 14:30:10] INFO: KanbanAgentService::actionAddTag - Adicionando tag ID 5 à conversa 123
[2025-01-09 14:30:10] INFO: KanbanAgentService::actionAddTag - Tag 'analisado' adicionada com sucesso
[2025-01-09 14:30:10] INFO: KanbanAgentService::actionAddTag - Resultado: Tags adicionadas: followup_enviado, analisado

[2025-01-09 14:30:10] INFO: KanbanAgentService::executeAgent - Ações executadas para conversa 123: 1 sucesso(s), 0 erro(s)

... (repetir para outras conversas) ...

[2025-01-09 14:32:15] INFO: KanbanAgentService::executeAgent - Finalizando execução 1: 15 analisadas, 8 com ações, 8 ações executadas, 0 erros
[2025-01-09 14:32:16] INFO: KanbanAgentService::executeAgent - Próxima execução agendada para o agente 1
[2025-01-09 14:32:16] INFO: KanbanAgentService::executeAgent - Agente executado com sucesso. 15 conversas analisadas, 8 com ações executadas.
```

### Exemplo 2: Erro ao Executar Ação

```
[2025-01-09 15:10:00] INFO: KanbanAgentService::executeAgent - Iniciando execução do agente 2 (tipo: scheduled)
[2025-01-09 15:10:01] INFO: KanbanAgentService::executeAgent - Agente 'Teste' (ID: 2) carregado com sucesso
[2025-01-09 15:10:02] INFO: KanbanAgentService::executeAgent - Total de conversas encontradas: 5
[2025-01-09 15:10:05] INFO: KanbanAgentService::executeAgent - Analisando conversa 456
[2025-01-09 15:10:08] INFO: KanbanAgentService::executeAgent - Condições ATENDIDAS para conversa 456
[2025-01-09 15:10:08] INFO: KanbanAgentService::executeSingleAction - Executando ação 'add_tag' na conversa 456
[2025-01-09 15:10:08] INFO: KanbanAgentService::actionAddTag - Tags a adicionar: []
[2025-01-09 15:10:08] ERROR: KanbanAgentService::actionAddTag - ERRO: Nenhuma tag especificada
[2025-01-09 15:10:08] ERROR: KanbanAgentService::executeActions - Erro ao executar ação add_tag: Nenhuma tag especificada
```

### Exemplo 3: Nenhuma Conversa Encontrada

```
[2025-01-09 16:00:00] INFO: KanbanAgentService::executeAgent - Iniciando execução do agente 3 (tipo: scheduled)
[2025-01-09 16:00:01] INFO: KanbanAgentService::executeAgent - Agente 'Teste Funil' (ID: 3) carregado com sucesso
[2025-01-09 16:00:02] INFO: KanbanAgentService::getTargetConversations - Filtrando por funis: 10
[2025-01-09 16:00:02] INFO: KanbanAgentService::getTargetConversations - Filtrando por etapas: 25
[2025-01-09 16:00:03] INFO: KanbanAgentService::getTargetConversations - Retornando 0 conversas
[2025-01-09 16:00:03] INFO: KanbanAgentService::executeAgent - Total de conversas encontradas: 0
[2025-01-09 16:00:03] INFO: KanbanAgentService::executeAgent - Iniciando análise de 0 conversas
[2025-01-09 16:00:03] INFO: KanbanAgentService::executeAgent - Finalizando execução 2: 0 analisadas, 0 com ações, 0 ações executadas, 0 erros
[2025-01-09 16:00:03] INFO: KanbanAgentService::executeAgent - Agente executado com sucesso. 0 conversas analisadas, 0 com ações executadas.
```

---

## 🐛 TROUBLESHOOTING COM LOGS

### Problema 1: "0 conversas analisadas"

**Como investigar**:
1. Acesse `/view-all-logs.php`
2. Vá para a seção "Kanban Agents"
3. Procure por:
   ```
   INFO: KanbanAgentService::getTargetConversations - Retornando X conversas
   ```

**Possíveis causas**:
- ✅ Se `X = 0`: Não há conversas nos funis/etapas configurados
- ✅ Se `Filtrando por funis: [vazio]`: Funis não foram salvos corretamente
- ✅ Se `Filtrando por etapas: [vazio]`: Etapas não foram salvas corretamente

### Problema 2: "Ações não são executadas"

**Como investigar**:
1. Acesse `/view-all-logs.php`
2. Procure por:
   ```
   INFO: KanbanAgentService::executeAgent - Condições NÃO ATENDIDAS
   ```

**Possíveis causas**:
- ✅ Condições muito restritivas
- ✅ Dados das conversas não atendem às condições
- ✅ Análise da IA retornou valores diferentes do esperado

### Problema 3: "Erro ao adicionar tags"

**Como investigar**:
1. Acesse `/view-all-logs.php`
2. Procure por:
   ```
   ERROR: KanbanAgentService::actionAddTag
   ```

**Possíveis causas**:
- ✅ Array de tags vazio (`Tags a adicionar: []`)
- ✅ Tag não existe no banco de dados
- ✅ Permissão insuficiente para adicionar tags

### Problema 4: "Agente não executa automaticamente"

**Como investigar**:
1. Verifique o cron:
   ```bash
   crontab -l
   ```
2. Acesse `/view-all-logs.php` → "Kanban Agents Cron"
3. Procure por execuções recentes

**Possíveis causas**:
- ✅ Cron não está configurado
- ✅ Cron está com erro de permissão
- ✅ Próxima execução ainda não chegou

---

## 📊 MONITORAMENTO

### Métricas a Acompanhar

1. **Taxa de Conversas Analisadas**
   - Procurar por: `conversas analisadas`
   - Esperado: > 0 se houver conversas nos funis/etapas

2. **Taxa de Condições Atendidas**
   - Procurar por: `Condições ATENDIDAS`
   - Comparar com: `Condições NÃO ATENDIDAS`

3. **Taxa de Sucesso de Ações**
   - Procurar por: `ações executadas`
   - Comparar com: `erro(s)`

4. **Tempo de Execução**
   - Calcular diferença entre:
     - `Iniciando execução do agente`
     - `Agente executado com sucesso`

### Comandos Úteis

```bash
# Ver últimas 50 linhas do log
tail -n 50 logs/kanban_agents.log

# Ver log em tempo real
tail -f logs/kanban_agents.log

# Buscar erros
grep -i "error" logs/kanban_agents.log

# Contar execuções bem-sucedidas hoje
grep "$(date +%Y-%m-%d)" logs/kanban_agents.log | grep "Agente executado com sucesso" | wc -l

# Ver estatísticas de conversas analisadas
grep "conversas analisadas" logs/kanban_agents.log | tail -n 10
```

---

## 🎯 BOAS PRÁTICAS

### 1. **Monitorar Regularmente**
- Acesse `/view-all-logs.php` diariamente
- Verifique se há erros recentes
- Acompanhe estatísticas de execução

### 2. **Limpar Logs Antigos**
```bash
# Manter apenas últimos 7 dias
find logs/ -name "*.log" -mtime +7 -delete

# Ou rotacionar logs
logrotate -f /path/to/logrotate.conf
```

### 3. **Alertas**
Configure alertas para:
- Erros críticos
- Taxa de sucesso baixa
- Nenhuma conversa analisada por muito tempo

### 4. **Backup de Logs**
```bash
# Backup diário
tar -czf logs_backup_$(date +%Y%m%d).tar.gz logs/
```

---

## 📄 ARQUIVOS RELACIONADOS

- ✅ `app/Services/KanbanAgentService.php` - Service com logs implementados
- ✅ `public/view-all-logs.php` - Visualizador de logs
- ✅ `logs/kanban_agents.log` - Log principal
- ✅ `storage/logs/kanban-agents-cron.log` - Log do cron

---

## ✅ RESUMO

**O sistema de logs dos Agentes de Kanban permite**:

✅ **Rastreamento completo** de todas as operações  
✅ **Identificação rápida** de problemas  
✅ **Auditoria** de ações executadas  
✅ **Monitoramento** de performance  
✅ **Debug** de configurações  
✅ **Visualização amigável** via interface web

**Acesse agora**: `http://seu-dominio/view-all-logs.php` e veja os logs em tempo real! 🚀

---

**Fim do Guia de Logs** 📊
