# Atualização: Indicador de Performance - 2026-01-10

## ✅ Correção Aplicada

Removida a restrição de exibir apenas para conversas fechadas. Agora o indicador respeita as configurações do sistema.

---

## 🔧 O que mudou

### ANTES ❌
- Indicador só aparecia para `status = 'closed'`
- Ignorava configuração `analyze_on_close`
- Não mostrava feedback quando análise estava pendente

### DEPOIS ✅
- Indicador aparece sempre (se habilitado)
- Respeita configuração `analyze_on_close`
- Mostra 2 estados diferentes:
  1. **Analisado** - Com nota e dados
  2. **Aguardando** - Com razão da pendência

---

## 📊 Estados do Indicador

### 1. **Estado: Analisado** ✅
Quando já existe análise processada no banco.

```
┌─────────────────────────────────────┐
│ 📊 Performance:                     │
│                                     │
│ Nota Geral:          🌟 4.75/5.00 │
│ ████████████████████░░░░ 95%       │
│                                     │
│ ✓ Excelente proatividade           │
│ ⚠ Melhorar tempo de resposta       │
│                                     │
│ [👁️ Ver Análise Completa]          │
└─────────────────────────────────────┘
```

---

### 2. **Estado: Aguardando Análise** ⏳
Quando ainda não foi analisada.

```
┌─────────────────────────────────────┐
│ 📊 Performance:                     │
│                                     │
│         ⏱️                          │
│    Aguardando análise               │
│                                     │
│ Análise será feita quando a         │
│ conversa for fechada                │
└─────────────────────────────────────┘
```

**Mensagens possíveis:**
- ✅ "Análise será feita quando a conversa for fechada"
- ✅ "Aguardando processamento da análise"
- ✅ "Conversa em andamento - análise periódica habilitada"
- ❌ "Análise de performance desabilitada"

---

## 🎯 Quando o Indicador Aparece

### ✅ Sempre mostra SE:
1. Análise de Performance está **habilitada** nas configurações
2. Usuário tem **permissão** para visualizar
3. Conversa tem análise OU pode ter análise

### ❌ Oculta apenas SE:
- Análise de Performance está **desabilitada**
- Usuário **sem permissão**
- Erro ao carregar dados

---

## ⚙️ Lógica de Exibição

```javascript
// 1. Sempre tenta carregar (não importa o status)
if (conversation.id) {
    loadAgentPerformance(conversation.id);
}

// 2. Backend decide o que mostrar
if (has_analysis) {
    // Mostra estado "analisado" com dados
    show_analyzed_state();
} else {
    // Mostra estado "aguardando" com razão
    show_pending_state(reason);
}
```

---

## 🔄 Cenários de Uso

### Cenário 1: `analyze_on_close = true` (padrão)

| Status Conversa | Análise Existe? | O que mostra |
|-----------------|-----------------|--------------|
| Open | Não | ⏱️ "Análise será feita quando fechar" |
| Open | Sim | ✅ Nota e dados |
| Closed | Não | ⏱️ "Aguardando processamento" |
| Closed | Sim | ✅ Nota e dados |

---

### Cenário 2: `analyze_on_close = false` (análise periódica)

| Status Conversa | Análise Existe? | O que mostra |
|-----------------|-----------------|--------------|
| Open | Não | ⏱️ "Análise periódica habilitada" |
| Open | Sim | ✅ Nota e dados |
| Closed | Não | ⏱️ "Aguardando processamento" |
| Closed | Sim | ✅ Nota e dados |

---

## 📝 Arquivos Modificados

### 1. **views/conversations/sidebar-conversation.php**
- Adicionados 2 estados: `performance-analyzed-state` e `performance-pending-state`
- Estado pendente mostra ícone de relógio e mensagem

### 2. **views/conversations/index.php**
- Removida condição `conversation.status === 'closed'`
- Função `loadAgentPerformance()` agora alterna entre estados
- Mostra estado analisado ou pendente conforme resposta

### 3. **app/Controllers/ConversationController.php**
- Método `getPerformance()` agora retorna `pending_reason`
- Razão baseada em:
  - Status da conversa
  - Configuração `analyze_on_close`
  - Se sistema está habilitado

---

## 🧪 Como Testar

### Teste 1: Conversa Aberta (analyze_on_close = true)
1. Abra uma conversa com status "Open"
2. Sidebar deve mostrar:
   - ⏱️ Ícone de relógio
   - "Aguardando análise"
   - "Análise será feita quando a conversa for fechada"

### Teste 2: Conversa Fechada SEM Análise
1. Feche uma conversa
2. Sidebar deve mostrar:
   - ⏱️ Ícone de relógio
   - "Aguardando análise"
   - "Aguardando processamento da análise"

### Teste 3: Conversa Fechada COM Análise
1. Rode o script: `php public/scripts/analyze-performance.php`
2. Abra a conversa analisada
3. Sidebar deve mostrar:
   - ✅ Nota com emoji e barra de progresso
   - Ponto forte e fraco
   - Botão "Ver Análise Completa"

### Teste 4: Análise Periódica (analyze_on_close = false)
1. Desmarque "Analisar apenas ao fechar" nas configurações
2. Abra uma conversa qualquer
3. Sidebar deve mostrar:
   - ⏱️ "Conversa em andamento - análise periódica habilitada"
   - OU ✅ Dados se já foi analisada

---

## 💡 Benefícios da Mudança

✅ **Flexível:** Respeita as configurações do sistema  
✅ **Informativo:** Sempre mostra status (analisado ou aguardando)  
✅ **Transparente:** Usuário sabe o que esperar  
✅ **Não intrusivo:** Não mostra se desabilitado  
✅ **Feedback claro:** Razão da pendência é explicada

---

## 🎨 Exemplos de Mensagens

### Quando Sistema Desabilitado:
```
(indicador oculto - não aparece)
```

### Quando Aguardando (conversa aberta, analyze_on_close=true):
```
⏱️
Aguardando análise

Análise será feita quando a
conversa for fechada
```

### Quando Aguardando (conversa fechada, sem análise):
```
⏱️
Aguardando análise

Aguardando processamento da análise
```

### Quando Aguardando (conversa aberta, analyze_on_close=false):
```
⏱️
Aguardando análise

Conversa em andamento - análise
periódica habilitada
```

### Quando Analisado:
```
📊 Performance:

Nota Geral:     🌟 4.75/5.00
████████████████████░░░░ 95%

✓ Excelente proatividade
⚠ Melhorar tempo de resposta

[👁️ Ver Análise Completa]
```

---

Agora o indicador é inteligente e se adapta às configurações do sistema! 🚀
