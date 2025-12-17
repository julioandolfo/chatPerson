# Reabertura Automática de Conversas

## 📋 Visão Geral

Sistema inteligente de reabertura de conversas fechadas/resolvidas baseado em **Período de Graça** configurável.

---

## 🎯 Funcionalidade

Quando uma conversa **fechada** ou **resolvida** recebe uma nova mensagem do cliente:

### **Cenário 1: Dentro do Período Mínimo** ⏱️ (< 10 min)
- **Ação:** **NÃO reabre** a conversa (continua fechada)
- **Mensagem:** É salva no banco de dados
- **Conversa:** Continua com status `closed` ou `resolved`
- **Notificação:** NÃO envia notificação aos agentes
- **Uso:** Ignorar mensagens rápidas tipo "OK", "Obrigado", "Entendi" após fechamento

### **Cenário 2: Após o Período Mínimo** 🔄 (>= 10 min)
- **Ação:** **Reabre** como **NOVA** conversa
- **Regras:** Aplica **TODAS** as regras de nova conversa:
  - ✅ Auto-atribuição (se configurado)
  - ✅ Funil/Etapa padrão da integração ou sistema
  - ✅ Automações de boas-vindas
  - ✅ Chatbot inicial (se configurado)
  - ✅ Distribuição por setor/departamento
- **Uso:** Cliente voltou após tempo suficiente - tratado como novo atendimento

---

## ⚙️ Configuração

### **Local:** `/settings` → Configurações Gerais

**Campo:** `Período Mínimo para Reabertura (minutos)`

**Valores Sugeridos:**
| Tempo | Uso | Comportamento |
|-------|-----|---------------|
| `0` min | Sempre reabrir | Qualquer mensagem reabre imediatamente |
| `5` min | Muito curto | Ignora confirmações rápidas |
| `10` min | **Padrão recomendado** | Ignora "Ok", "Obrigado" dentro de 10 min |
| `30` min | Médio | Cliente pode fazer perguntas de follow-up |
| `60` min | Longo | Apenas conversas realmente novas reabrem |

---

## 📐 Lógica de Funcionamento

```
┌──────────────────────────────────┐
│ Mensagem recebida                │
└──────────────┬───────────────────┘
               │
               ▼
     ┌─────────────────┐
     │ Buscar conversa │
     └────────┬────────┘
              │
     ┌────────▼────────┐
     │ Status: closed  │   ❌ Não → Processar normalmente
     │   ou resolved?  │
     └────────┬────────┘
              │ ✅ Sim
              ▼
   ┌──────────────────────┐
   │ Calcular tempo desde │
   │    fechamento        │
   └──────────┬───────────┘
              │
   ┌──────────▼───────────┐
   │ Tempo > Período de   │
   │    Graça?            │
   └──────────┬───────────┘
              │
       ┌──────┴──────┐
       │             │
    ✅ Sim         ❌ Não
       │             │
       ▼             ▼
 ┌─────────┐   ┌──────────┐
 │ NOVA    │   │ Apenas   │
 │ CONVERSA│   │ REABRIR  │
 │         │   │          │
 │ + Regras│   │ Sem      │
 │ + Funil │   │ Regras   │
 │ + Auto  │   │          │
 └─────────┘   └──────────┘
```

---

## 🔍 Exemplo Prático

### **Situação:**
- Cliente: "Quero comprar um produto"
- Agente: Atende, finaliza venda
- Status: `closed`
- Período Mínimo para Reabertura: `10 minutos`

### **Teste 1: Mensagem em 2 minutos** 🚫
```
Cliente: "Ok, obrigado!"
└─> 🚫 NÃO reabre conversa
    └─> Mensagem é salva no banco
    └─> Conversa continua fechada
    └─> NÃO notifica agentes
    └─> Ideal para confirmações rápidas
```

### **Teste 2: Mensagem em 15 minutos** 🔄
```
Cliente: "Preciso de outro produto"
└─> 🔄 Cria NOVA conversa
    └─> Aplica auto-atribuição
    └─> Define funil/etapa padrão
    └─> Dispara chatbot de boas-vindas
    └─> Aplica regras de nova conversa
```

---

## 📂 Arquivos Modificados

### 1. **Configuração**
- `app/Services/SettingService.php`
  - Adicionado: `conversation_reopen_grace_period_minutes` (padrão: 60)
  
- `views/settings/index.php`
  - Campo de configuração na interface

### 2. **Lógica de Reabertura**
- `app/Services/WhatsAppService.php`
  - Método: `processWebhook()`
  - Linhas: ~2035-2070
  - Verifica status da conversa
  - Calcula tempo desde fechamento
  - Decide se reabre ou cria nova

### 3. **Filtro no Polling**
- `app/Controllers/RealtimeController.php`
  - Método: `poll()`
  - Linhas: ~247-265
  - Filtra conversas com status `closed` ou `resolved`
  - Impede que conversas fechadas apareçam na lista através do polling
  - Resolve problema de conversas fechadas piscando na tela

### 4. **Logs**
- `storage/logs/quepasa.log`
  ```log
  [INFO] processWebhook - Conversa encontrada está fechada/resolvida. Verificando período de graça...
  [INFO] processWebhook - Período de graça configurado: 60 minutos
  [INFO] processWebhook - Tempo desde fechamento: 125.5 minutos
  [INFO] processWebhook - Passou do período de graça. Criando NOVA conversa e aplicando regras...
  ```

---

## 🧪 Como Testar

### **Teste 1: Dentro do Período Mínimo** 🚫
1. Feche uma conversa manualmente
2. Envie mensagem **dentro de 10 minutos**
3. Verificar:
   - ✅ Mensagem salva no banco de dados
   - ✅ Conversa continua fechada (status = `closed`)
   - ❌ Conversa NÃO reabre
   - ❌ Agentes NÃO são notificados

### **Teste 2: Após o Período Mínimo** ✅
1. Feche uma conversa manualmente
2. Altere `updated_at` no banco para simular tempo:
   ```sql
   UPDATE conversations 
   SET updated_at = DATE_SUB(NOW(), INTERVAL 15 MINUTE)
   WHERE id = 123;
   ```
3. Envie mensagem
4. Verificar:
   - ✅ Nova conversa criada
   - ✅ Auto-atribuição aplicada
   - ✅ Funil/etapa padrão aplicado
   - ✅ Automações disparadas

---

## 🎛️ Configurações Recomendadas por Tipo de Negócio

| Tipo | Período Sugerido | Motivo |
|------|------------------|--------|
| **E-commerce** | 30-60 min | Cliente pode ter dúvidas rápidas |
| **Suporte Técnico** | 60-120 min | Troubleshooting pode demorar |
| **Vendas B2B** | 120-240 min | Negociações mais longas |
| **Pós-venda** | 60-120 min | Follow-ups podem demorar |
| **Atendimento 24/7** | 15-30 min | Respostas rápidas esperadas |

---

## 🔔 Notificações

### **Reabertura Simples (Dentro do Período)**
- WebSocket: `conversation_updated`
- Notificação: "Conversa reaberta"

### **Nova Conversa (Após Período)**
- WebSocket: `new_conversation`
- Notificação: "Nova conversa recebida"
- Automações: Todas as configuradas para novas conversas

---

## 📊 Métricas Afetadas

### **Dentro do Período:**
- Contador de reaberturas
- Tempo médio de resolução (continua contando)

### **Após o Período:**
- Nova conversa no contador
- Novo ciclo de SLA
- Nova oportunidade de conversão

---

## ⚠️ Observações Importantes

1. **Período Zero (`0`)**: Sempre cria nova conversa (sem período de graça)
2. **Conversas Abertas**: Lógica NÃO se aplica (apenas para `closed`/`resolved`)
3. **Múltiplos Canais**: Funciona para todos os canais (WhatsApp, email, chat)
4. **Histórico**: Conversas antigas são mantidas (não são apagadas)

---

## 🚀 Benefícios

✅ **Cliente:** Resposta mais contextualizada (mantém histórico recente)  
✅ **Agente:** Menos retrabalho em confirmações rápidas  
✅ **Gestor:** Métricas mais precisas (separa nova conversa de reativação)  
✅ **Sistema:** Automações aplicadas apenas quando relevante  

---

## 📝 Próximas Melhorias (Futuro)

- [ ] Período de graça diferente por canal
- [ ] Período de graça por setor/departamento
- [ ] Dashboard de reaberturas vs. novas conversas
- [ ] Regra de reabertura baseada em tags
- [ ] Notificação customizada de reabertura

---

**Implementado em:** 17/12/2024  
**Versão:** 1.0  
**Status:** ✅ Produção

