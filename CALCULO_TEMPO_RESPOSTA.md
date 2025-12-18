# 📊 Cálculo de Tempo Médio de Resposta

## 🎯 Objetivo

Calcular o tempo médio de resposta baseado na **troca real de mensagens** entre cliente e agente, não no tempo total da conversa.

## 🔄 Como Funciona

### Antes (Incorreto)
- ❌ Calculava: `tempo total da conversa` = `created_at` até `resolved_at/updated_at`
- ❌ Problema: Não reflete a velocidade de resposta real do agente

### Agora (Correto)
- ✅ Calcula: `tempo de resposta` = tempo entre **mensagem do cliente** e **primeira resposta do agente**
- ✅ Considera: **Todas as mensagens**, não apenas quando fecha/resolve
- ✅ Tempo real: Calcula em tempo real, a cada nova troca de mensagens

## 📐 Lógica do Cálculo

### Passo 1: Identificar Pares de Mensagens
Para cada mensagem do cliente, encontrar a primeira resposta do agente:

```sql
Cliente envia (10:00) → Agente responde (10:05) = 5 minutos
Cliente envia (10:10) → Agente responde (10:12) = 2 minutos
Cliente envia (10:20) → Agente responde (10:25) = 5 minutos
```

### Passo 2: Calcular Média
```
Tempo médio = (5 + 2 + 5) / 3 = 4 minutos
```

## 🗄️ Query SQL Utilizada

```sql
SELECT 
    AVG(response_times.response_time_minutes) as avg_response_time_minutes
FROM conversations c
LEFT JOIN (
    SELECT 
        m1.conversation_id,
        AVG(TIMESTAMPDIFF(MINUTE, m1.created_at, m2.created_at)) as response_time_minutes
    FROM messages m1
    INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
        AND m2.sender_type = 'agent'
        AND m2.created_at > m1.created_at
        AND m2.created_at = (
            -- Primeira resposta do agente após a mensagem do cliente
            SELECT MIN(m3.created_at)
            FROM messages m3
            WHERE m3.conversation_id = m1.conversation_id
            AND m3.sender_type = 'agent'
            AND m3.created_at > m1.created_at
        )
    WHERE m1.sender_type = 'contact'
    GROUP BY m1.conversation_id
) response_times ON response_times.conversation_id = c.id
```

## 📍 Onde Foi Aplicado

### 1. **Histórico do Contato** (`ContactController::getHistoryMetrics`)
- Calcula tempo médio de resposta para todas as conversas do contato
- Exibido na aba "Histórico" do sidebar

### 2. **Dashboard Principal** (`DashboardService`)
- `getAverageFirstResponseTime()` - Tempo médio da primeira resposta
- `getAverageResponseTime()` - Tempo médio geral de resposta
- `getAgentMetrics()` - Métricas individuais de cada agente

### 3. **Métricas de Funil/Etapas** (`FunnelService::getStageMetrics`)
- Tempo médio de resposta por etapa do funil
- Tempo médio por agente em cada etapa

### 4. **SLA Metrics** (`DashboardService::getSLAMetrics`)
- Cálculo de SLA baseado em tempo de resposta real
- Taxas de resposta em 5min, 15min, 30min

## 🧪 Como Testar

### Teste 1: Script de Validação
```bash
cd C:\laragon\www\chat
php public/test-tempo-resposta.php
```

Este script mostra:
- Conversas com troca de mensagens
- Pares de mensagens (cliente → agente)
- Tempo de resposta de cada par
- Média calculada
- Comparação com a query do sistema

### Teste 2: Interface
1. Abra uma conversa com mensagens trocadas
2. Vá na aba "Histórico" no sidebar
3. Verifique o "Tempo Médio" exibido
4. Abra o console (F12) e veja o log: `📊 Dados do histórico:`

### Teste 3: Dashboard
1. Acesse o Dashboard
2. Verifique o card "Tempo Médio de Resposta"
3. Veja os cards individuais dos agentes
4. Confira as métricas de SLA

## 📊 Exemplo Prático

### Cenário
**Conversa #123:**
- 10:00 - Cliente: "Olá, preciso de ajuda"
- 10:05 - Agente: "Olá! Como posso ajudar?" → **5 min**
- 10:10 - Cliente: "Quero saber sobre o produto X"
- 10:12 - Agente: "Claro! O produto X..." → **2 min**
- 10:20 - Cliente: "Qual o preço?"
- 10:25 - Agente: "O preço é R$ 100" → **5 min**

**Resultado:**
- Tempo médio de resposta: **(5 + 2 + 5) / 3 = 4 minutos**

### No Sistema
```json
{
  "total_conversations": 1,
  "avg_response_time_minutes": 4.0,
  "avg_response_time_seconds": 240
}
```

## 🎯 Benefícios

1. **Tempo Real**: Calcula com base em todas as mensagens, não apenas ao fechar
2. **Precisão**: Reflete a velocidade real de resposta do agente
3. **Granularidade**: Considera cada interação, não apenas o tempo total
4. **SLA Correto**: Permite medir SLA de forma precisa
5. **Métricas Individuais**: Cada agente tem seu tempo médio calculado corretamente

## ⚠️ Observações

- Se uma mensagem do cliente não tiver resposta, ela não entra no cálculo
- O tempo é calculado em minutos e depois convertido para segundos/horas conforme necessário
- Conversas sem mensagens do agente retornam `null`
- O cálculo é feito em tempo real, não precisa esperar fechar a conversa

## 🔧 Manutenção

Se precisar ajustar o cálculo no futuro:
1. Edite a subquery `response_times` nos arquivos mencionados
2. Teste com `test-tempo-resposta.php`
3. Valide no frontend (histórico e dashboard)

