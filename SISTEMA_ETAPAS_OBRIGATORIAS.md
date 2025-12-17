# 🔒 SISTEMA DE ETAPAS OBRIGATÓRIAS DOS FUNIS

**Data**: 2025-01-17  
**Status**: ✅ 100% Implementado

---

## 📋 VISÃO GERAL

Todos os funis do sistema agora possuem **3 etapas obrigatórias** que não podem ser deletadas ou renomeadas. Essas etapas garantem um fluxo consistente de gestão de conversas, desde a entrada até o fechamento.

### As 3 Etapas Obrigatórias

| # | Nome | system_stage_type | Cor Padrão | Descrição |
|---|---|---|---|---|
| 1️⃣ | **Entrada** | `entrada` | 🔵 Azul (#3b82f6) | Etapa inicial. Novas conversas e reaberturas entram aqui. |
| 2️⃣ | **Fechadas / Resolvidas** | `fechadas` | 🟢 Verde (#22c55e) | Conversas fechadas ou resolvidas. Reabrem para "Entrada" após período de graça. |
| 3️⃣ | **Perdidas** | `perdidas` | 🔴 Vermelho (#ef4444) | Conversas perdidas ou descartadas. Não reabrem automaticamente. |

---

## ⚙️ CARACTERÍSTICAS

### 🔒 Proteções Implementadas

#### 1. **Não Podem Ser Deletadas**
- Tentativa de deletar retorna erro 403
- Mensagem: "Etapas do sistema (Entrada, Fechadas/Resolvidas, Perdidas) não podem ser deletadas."
- Validação: `FunnelStage::isSystemStage($stageId)`

#### 2. **Nome e Descrição Fixos**
- Não podem ser alterados via interface ou API
- Apenas a **cor** é editável
- Backend filtra campos permitidos: apenas `color`

#### 3. **Indicador Visual**
- Badge verde "Sistema" com ícone de escudo
- Botão especial "Editar Cor" (ícone paleta)
- Dropdown de editar/deletar **não** aparece

#### 4. **Ordem Fixa**
- `stage_order` garante ordem consistente:
  - Entrada: `1` (sempre primeira)
  - Fechadas/Resolvidas: `998` (penúltima)
  - Perdidas: `999` (última)
- Outras etapas podem usar `2-997`

---

## 🔄 FLUXO AUTOMÁTICO DE CONVERSAS

### 1️⃣ **Nova Conversa → Entrada**
```
Cliente chama → ConversationService::create() →
  ✅ Busca etapa "Entrada" do funil (system_stage_type='entrada')
  ✅ Conversa criada em Funil X, Etapa "Entrada"
```

### 2️⃣ **Fechar Conversa → Fechadas / Resolvidas**
```
Agente clica "Fechar" → ConversationService::close() →
  ✅ status='closed'
  ✅ Busca etapa "Fechadas / Resolvidas" (system_stage_type='fechadas')
  ✅ funnel_stage_id atualizado
  ✅ Conversa move para etapa de fechadas DO MESMO FUNIL
```

### 3️⃣ **Reabertura Após Período de Graça → Entrada**
```
Cliente envia mensagem APÓS período de graça →
  WhatsAppService::processWebhook() →
    ✅ Detecta período passou
    ✅ Cria NOVA conversa (não reabre)
    ✅ ConversationService::create()
    ✅ Vai para etapa "Entrada" do funil correspondente
```

### 4️⃣ **Mensagem Dentro do Período de Graça → Permanece Fechada**
```
Cliente envia mensagem DENTRO do período de graça →
  WhatsAppService::processWebhook() →
    ✅ Detecta período NÃO passou
    ✅ Mensagem salva
    ✅ Conversa PERMANECE fechada (status='closed')
    ✅ NÃO move de etapa (fica em "Fechadas/Resolvidas")
```

---

## 🗄️ ESTRUTURA DE BANCO DE DADOS

### Novos Campos em `funnel_stages`

```sql
ALTER TABLE funnel_stages 
ADD COLUMN is_system_stage TINYINT(1) DEFAULT 0 COMMENT 'Etapa do sistema (não pode ser deletada/renomeada)',
ADD COLUMN system_stage_type VARCHAR(50) NULL COMMENT 'Tipo: entrada, fechadas, perdidas',
ADD COLUMN stage_order INT DEFAULT 0 COMMENT 'Ordem da etapa (1-999)';

CREATE INDEX idx_funnel_stages_system_type ON funnel_stages(funnel_id, system_stage_type);
```

### Valores para Etapas do Sistema

| Campo | Entrada | Fechadas/Resolvidas | Perdidas |
|---|---|---|---|
| `is_system_stage` | `1` | `1` | `1` |
| `system_stage_type` | `entrada` | `fechadas` | `perdidas` |
| `stage_order` | `1` | `998` | `999` |
| `is_default` | `1` | `0` | `0` |

---

## 💻 ARQUIVOS MODIFICADOS

### 1. **Migration**
- **`database/migrations/060_add_system_stages_to_funnels.php`**
  - Adiciona 3 campos à tabela `funnel_stages`
  - Cria 3 etapas obrigatórias em todos os funis existentes
  - Cria índice para performance

### 2. **Models**
- **`app/Models/FunnelStage.php`**
  - `$fillable`: Adicionado `stage_order`, `is_system_stage`, `system_stage_type`
  - `getSystemStage($funnelId, $type)`: Buscar etapa do sistema por tipo
  - `getSystemStages($funnelId)`: Buscar todas as 3 etapas do sistema
  - `isSystemStage($stageId)`: Verificar se é etapa do sistema

### 3. **Services**
- **`app/Services/FunnelService.php`**
  - `create()`: Chama `createSystemStages()` ao criar funil
  - `createSystemStages($funnelId)`: Cria as 3 etapas obrigatórias
  - `updateStage()`: Filtra campos permitidos para etapas do sistema (apenas `color`)
  
- **`app/Services/ConversationService.php`**
  - `create()`: Busca etapa "Entrada" ao criar nova conversa
  - `close()`: Move para etapa "Fechadas / Resolvidas" ao fechar

### 4. **Controllers**
- **`app/Controllers/FunnelController.php`**
  - `deleteStage()`: Valida `isSystemStage()` e retorna 403 se tentar deletar

### 5. **Views & Frontend**
- **`views/funnels/kanban.php`**
  - Badge "Sistema" com ícone de escudo
  - Botão "Editar Cor" para etapas do sistema
  - Dropdown editar/deletar apenas para etapas normais
  
- **`public/assets/js/kanban.js`**
  - `editStageColorOnly(stageId, name, currentColor)`: Modal simplificado para editar apenas cor

---

## 🧪 CENÁRIOS DE TESTE

### ✅ Teste 1: Criar Novo Funil
1. Acesse `/funnels`
2. Clique em "Novo Funil"
3. Crie funil "Teste XYZ"
4. **Resultado Esperado:**
   - ✅ Funil criado com 3 etapas obrigatórias
   - ✅ Etapa "Entrada" (azul, stage_order=1)
   - ✅ Etapa "Fechadas / Resolvidas" (verde, stage_order=998)
   - ✅ Etapa "Perdidas" (vermelho, stage_order=999)

### ✅ Teste 2: Tentar Deletar Etapa do Sistema
1. Acesse Kanban do funil
2. Tente deletar "Entrada" via dropdown
3. **Resultado Esperado:**
   - ✅ Dropdown NÃO aparece (apenas botão "Editar Cor")
   - ✅ Se tentar via API, retorna 403

### ✅ Teste 3: Tentar Renomear Etapa do Sistema
1. Clique em "Editar Cor" na etapa "Entrada"
2. Tente alterar nome
3. **Resultado Esperado:**
   - ✅ Modal mostra apenas campo de cor
   - ✅ Mensagem: "Apenas a cor pode ser alterada"

### ✅ Teste 4: Fechar Conversa
1. Abra uma conversa no Kanban (etapa qualquer)
2. Clique em "Resolver" ou "Fechar"
3. **Resultado Esperado:**
   - ✅ Conversa move para etapa "Fechadas / Resolvidas"
   - ✅ `status='closed'`
   - ✅ Aparece no Kanban, coluna "Fechadas / Resolvidas"

### ✅ Teste 5: Reabertura Após Período de Graça
1. Feche uma conversa
2. Aguarde 10+ minutos (período de graça)
3. Cliente envia mensagem
4. **Resultado Esperado:**
   - ✅ NOVA conversa criada
   - ✅ Vai para etapa "Entrada"
   - ✅ Aplica todas as regras (funil padrão, auto-atribuição)

### ✅ Teste 6: Mensagem Dentro do Período de Graça
1. Feche uma conversa
2. Cliente envia "Ok" em 2 minutos
3. **Resultado Esperado:**
   - ✅ Mensagem salva
   - ✅ Conversa permanece em "Fechadas / Resolvidas"
   - ✅ `status='closed'` (não muda)
   - ✅ NÃO aparece na lista de conversas abertas

---

## 🎨 INTERFACE DO USUÁRIO

### Indicadores Visuais

#### Etapa do Sistema
```
┌─────────────────────────────────────────────┐
│ 🛡️ Entrada   [Sistema]                      │
│ Etapa inicial do funil...                   │
│                                 [🎨 Editar Cor]│
└─────────────────────────────────────────────┘
```

#### Etapa Normal
```
┌─────────────────────────────────────────────┐
│ Qualificação                                │
│ Lead sendo qualificado...                   │
│                                      [⋮ Menu]│
└─────────────────────────────────────────────┘
```

### Badge "Sistema"
- Cor: Verde claro (`badge-light-success`)
- Ícone: `ki-shield-tick` (escudo com check)
- Tooltip: "Etapa obrigatória do sistema"

---

## 🔧 API & VALIDAÇÕES

### Backend: FunnelService::updateStage()
```php
// PROTEÇÃO: Etapas do sistema só podem ter cor alterada
if (!empty($stage['is_system_stage'])) {
    $allowedFields = ['color'];
    $data = array_intersect_key($data, array_flip($allowedFields));
    
    if (empty($data)) {
        throw new \InvalidArgumentException('Etapas do sistema só podem ter a cor alterada');
    }
}
```

### Backend: FunnelController::deleteStage()
```php
// PROTEÇÃO: Etapas do sistema não podem ser deletadas
if (\App\Models\FunnelStage::isSystemStage($stageId)) {
    Response::json([
        'success' => false,
        'message' => 'Etapas do sistema (Entrada, Fechadas/Resolvidas, Perdidas) não podem ser deletadas.'
    ], 403);
    return;
}
```

---

## 📊 ORDEM DAS ETAPAS NO KANBAN

```
┌────────────┬────────────────┬────────────────┬────────────────┐
│  Entrada   │  Qualificação  │  Negociação    │  Fechadas /    │
│ (order=1)  │   (order=2)    │  (order=3)     │  Resolvidas    │
│  🛡️ Sistema │                │                │  (order=998)   │
│            │                │                │  🛡️ Sistema     │
└────────────┴────────────────┴────────────────┴────────────────┘
                                                  Perdidas (999)
                                                  🛡️ Sistema
```

---

## 🚀 BENEFÍCIOS

### 1. **Consistência**
- Todos os funis seguem o mesmo padrão de entrada/saída
- Facilita onboarding e treinamento

### 2. **Automação**
- Sistema sabe onde colocar conversas novas (Entrada)
- Sistema sabe onde colocar conversas fechadas (Fechadas/Resolvidas)
- Reabertura automática funciona corretamente

### 3. **Reporting**
- Fácil agregar métricas de "conversas entradas"
- Fácil agregar métricas de "conversas fechadas"
- Comparações entre funis mais consistentes

### 4. **Proteção de Dados**
- Usuários não podem deletar etapas críticas
- Evita perda de conversas por acidente

---

## 🔍 QUERIES ÚTEIS

### Buscar todas as etapas do sistema
```sql
SELECT f.name AS funnel, fs.name AS stage, fs.system_stage_type, fs.color
FROM funnel_stages fs
JOIN funnels f ON fs.funnel_id = f.id
WHERE fs.is_system_stage = 1
ORDER BY f.id, fs.stage_order;
```

### Contar conversas por etapa do sistema
```sql
SELECT 
    fs.system_stage_type, 
    COUNT(c.id) AS total
FROM conversations c
JOIN funnel_stages fs ON c.funnel_stage_id = fs.id
WHERE fs.is_system_stage = 1
GROUP BY fs.system_stage_type;
```

### Verificar funis sem etapas do sistema (inconsistência)
```sql
SELECT f.id, f.name
FROM funnels f
LEFT JOIN funnel_stages fs ON f.id = fs.funnel_id AND fs.is_system_stage = 1
GROUP BY f.id, f.name
HAVING COUNT(fs.id) < 3;
```

---

## 📝 NOTAS IMPORTANTES

1. **Criação de Funil:**
   - Sempre cria as 3 etapas obrigatórias automaticamente
   - Não é necessário criar manualmente

2. **Migração de Dados:**
   - Funis antigos recebem as 3 etapas via migration 060
   - Conversas antigas permanecem em suas etapas atuais
   - Próximas reaberturas irão para "Entrada"

3. **Customização:**
   - Apenas COR é editável
   - Nome e descrição são fixos
   - Se precisar mudar descrição, atualizar migration e rodar novamente

4. **Performance:**
   - Índice criado: `idx_funnel_stages_system_type (funnel_id, system_stage_type)`
   - Queries por tipo de etapa são rápidas

5. **Ordem de Etapas:**
   - Use `stage_order` entre 2-997 para etapas customizadas
   - 1, 998, 999 são reservados

---

## 🎉 RESULTADO FINAL

✅ **Sistema 100% Funcional**
- Todos os funis têm 3 etapas obrigatórias
- Conversas fluem automaticamente entre etapas
- Interface indica claramente etapas do sistema
- Proteções impedem deleção/renomeação acidental
- Reabertura automática funciona conforme esperado

---

**Última atualização**: 2025-01-17


