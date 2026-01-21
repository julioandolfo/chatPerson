# Debug: Tiers e Condições não salvando

## 🔍 O que foi feito:

### 1. **Logs Adicionados no Controller**

Adicionei logs detalhados em `app/Controllers/GoalController.php` nos métodos:
- `saveBonusTiers()`
- `saveGoalConditions()`

Os logs agora mostram:
- Se os dados estão chegando
- Se são arrays
- Se estão vazios
- O conteúdo completo

### 2. **Debug no Formulário (JavaScript)**

Adicionei um listener no submit do formulário (`views/goals/form.php`) que mostra no **Console do Navegador**:
- Quantos tiers estão sendo enviados
- Quantas conditions estão sendo enviadas
- Se os checkboxes `enable_bonus` e `enable_bonus_conditions` estão marcados
- Aviso se nenhum dado está sendo enviado

## 🧪 Como Testar:

### Passo 1: Abrir Console do Navegador
1. Acesse `/goals/create` ou `/goals/edit?id=X`
2. Pressione `F12` para abrir DevTools
3. Vá na aba **Console**

### Passo 2: Preencher o Formulário
1. Preencha os dados básicos da meta
2. **Habilite** "Sistema de Bonificações"
3. **Adicione Tiers**:
   - Clique em "Adicionar Tier"
   - Preencha: Nome, % Mínimo, Valor Bônus
   - Adicione pelo menos 2 tiers
4. **Adicione Condições** (opcional):
   - Habilite "Condições de Ativação"
   - Clique em "Adicionar Condição"
   - Preencha: Métrica, Operador, Valor

### Passo 3: Salvar e Verificar Console
1. Clique em **"Criar Meta"** ou **"Atualizar Meta"**
2. **No Console do Navegador**, você verá:

```javascript
=== FORM SUBMIT DEBUG ===
Form action: http://localhost/goals/store
Form method: POST
Tiers encontrados: [
  {key: "tiers[0][tier_name]", value: "Bronze"},
  {key: "tiers[0][threshold_percentage]", value: "50"},
  {key: "tiers[0][bonus_amount]", value: "500"},
  ...
]
Conditions encontradas: [
  {key: "conditions[0][condition_type]", value: "conversion_rate"},
  ...
]
enable_bonus: 1
enable_bonus_conditions: 1
```

### Passo 4: Verificar Logs do Servidor
1. Acesse os logs do PHP (geralmente em `storage/logs/` ou `/var/log/`)
2. Procure por linhas com `[goals]`:

```
[2026-01-21 12:34:56] goals.INFO: saveBonusTiers - goalId: 5
[2026-01-21 12:34:56] goals.INFO: saveBonusTiers - tiers raw: Array (...)
[2026-01-21 12:34:56] goals.INFO: saveBonusTiers - tiers is_array: YES
[2026-01-21 12:34:56] goals.INFO: saveBonusTiers - tiers empty: NO
```

## 🐛 Possíveis Problemas:

### Problema 1: Console mostra "NENHUM" tier/condition
**Causa**: Os campos não estão sendo preenchidos ou o JavaScript não está encontrando os inputs  
**Solução**: Verificar se os inputs têm o atributo `name` correto (`tiers[0][tier_name]`, etc)

### Problema 2: Console mostra dados, mas logs do servidor mostram "empty: YES"
**Causa**: O `Request::post()` não está pegando arrays corretamente  
**Solução**: Verificar se o formulário está usando `method="POST"` e `enctype` correto

### Problema 3: Logs mostram arrays, mas não salva no banco
**Causa**: Erro no SQL ou validação  
**Solução**: Verificar se as tabelas `goal_bonus_tiers` e `goal_bonus_conditions` existem

## 📊 Verificar no Banco de Dados:

```sql
-- Ver tiers de uma meta
SELECT * FROM goal_bonus_tiers WHERE goal_id = 1;

-- Ver condições de uma meta
SELECT * FROM goal_bonus_conditions WHERE goal_id = 1;

-- Ver se as tabelas existem
SHOW TABLES LIKE 'goal_bonus%';
```

## 🔧 Próximos Passos (se ainda não funcionar):

1. **Compartilhe o output do Console do navegador**
2. **Compartilhe os logs do servidor** (linhas com `[goals]`)
3. **Execute as queries SQL** acima e compartilhe o resultado

---

**Arquivos modificados**:
- `app/Controllers/GoalController.php` - Logs adicionados
- `views/goals/form.php` - Debug JavaScript adicionado
