# 🧪 Teste: Salvar Tiers e Condições

## 📋 Passo a Passo:

### 1. Limpar log anterior
```bash
# Windows PowerShell
echo "" > logs/goals.log
```

### 2. Criar/Editar uma Meta

1. Acesse: **`/goals/create`** ou **`/goals/edit?id=X`**

2. Preencha os dados básicos da meta:
   - Nome: "Teste Tiers"
   - Tipo: Faturamento
   - Valor: 100000
   - Período: Mensal
   - Datas: Janeiro 2026

3. **Ative "Habilitar Sistema de Bonificações"**

4. Configure OTE:
   - Salário Base: R$ 3.000
   - Comissão Target: R$ 2.000

5. **Adicione 2 Tiers**:
   
   **Tier 1:**
   - Nome: `Bronze`
   - % Mínimo: `50`
   - Valor Bônus: `600`
   - Cor: Bronze 🥉
   - Ordem: `0`
   
   **Tier 2:**
   - Nome: `Prata`
   - % Mínimo: `70`
   - Valor Bônus: `1000`
   - Cor: Prata 🥈
   - Ordem: `1`

6. **Ative "Habilitar Condições de Ativação"**

7. **Adicione 1 Condição**:
   - Métrica: `Taxa de Conversão`
   - Operador: `>=`
   - Valor Mínimo: `15`
   - Marque: ✅ **Obrigatória**
   - Modificador: `0.5`
   - Descrição: `Conversão mínima`

8. Clique em **"Criar Meta"** ou **"Atualizar Meta"**

### 3. Ver os Logs

1. Acesse: **`http://localhost/view-all-logs.php`**

2. Clique no botão **🎯 Metas/OTE** (dourado no topo)

3. Você verá logs como:

```
[2026-01-21 10:20:15] goals.INFO: Store meta - payload: {"name":"Teste Tiers",...}
[2026-01-21 10:20:15] goals.INFO: saveBonusTiers - goalId: 5
[2026-01-21 10:20:15] goals.INFO: saveBonusTiers - tiers raw: Array ( [0] => Array (...) )
[2026-01-21 10:20:15] goals.INFO: saveBonusTiers - tiers is_array: YES
[2026-01-21 10:20:15] goals.INFO: saveBonusTiers - tiers empty: NO
[2026-01-21 10:20:15] goals.INFO: saveGoalConditions - goalId: 5
[2026-01-21 10:20:15] goals.INFO: saveGoalConditions - conditions raw: Array ( [0] => Array (...) )
[2026-01-21 10:20:15] goals.INFO: saveGoalConditions - conditions is_array: YES
[2026-01-21 10:20:15] goals.INFO: saveGoalConditions - conditions empty: NO
```

### 4. Verificar no Banco de Dados

```sql
-- Ver a meta criada
SELECT id, name, enable_bonus, enable_bonus_conditions FROM goals ORDER BY id DESC LIMIT 1;

-- Ver os tiers (use o ID da meta acima)
SELECT * FROM goal_bonus_tiers WHERE goal_id = X;

-- Ver as condições (use o ID da meta acima)
SELECT * FROM goal_bonus_conditions WHERE goal_id = X;
```

## ✅ Resultado Esperado:

### Se FUNCIONOU:
- ✅ Logs mostram: `tiers is_array: YES`, `tiers empty: NO`
- ✅ Logs mostram: `conditions is_array: YES`, `conditions empty: NO`
- ✅ `SELECT` retorna os 2 tiers criados
- ✅ `SELECT` retorna a 1 condição criada

### Se NÃO FUNCIONOU:

#### Cenário 1: Logs mostram `empty: YES`
**Problema**: Dados não estão chegando do formulário  
**Solução**: Verificar se os inputs têm `name="tiers[0][tier_name]"`, etc

#### Cenário 2: Logs mostram `is_array: NO`
**Problema**: `Request::post()` não está retornando array  
**Solução**: Verificar `app/Helpers/Request.php`

#### Cenário 3: Logs OK, mas SELECT retorna vazio
**Problema**: Erro no INSERT SQL  
**Solução**: Verificar erros de SQL nos logs

## 📸 Compartilhe:

Se não funcionar, me envie:

1. **Screenshot** de `view-all-logs.php` (seção Metas/OTE)
2. **Resultado SQL** das queries acima
3. **Print** da tela de criação de meta (mostrando os campos preenchidos)

---

**Acesso rápido**: `http://localhost/view-all-logs.php` → 🎯 **Metas/OTE**
