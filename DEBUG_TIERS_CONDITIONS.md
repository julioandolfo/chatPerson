# 🔍 DEBUG: Tiers e Condições não estão salvando

## 🎯 Problema
Ao criar/editar uma meta com Tiers e Condições, eles não estão sendo salvos no banco de dados.

## 🧪 Scripts de Diagnóstico

Foram criados 2 scripts para diagnosticar o problema:

### 1️⃣ **`create-goals-log.php`** - Criar e testar o arquivo de log

**URL**: `http://seu-dominio.com/create-goals-log.php`

**O que faz**:
- ✅ Verifica se o diretório `logs/` existe
- ✅ Cria o arquivo `logs/goals.log` com permissões corretas
- ✅ Testa escrita direta no arquivo
- ✅ Testa a classe `Logger::info()`
- ✅ Mostra conteúdo atual do log
- ✅ Simula logs do GoalController

**Execute PRIMEIRO este script!**

---

### 2️⃣ **`test-save-tiers.php`** - Testar salvamento direto

**URL**: `http://seu-dominio.com/test-save-tiers.php`

**O que faz**:
- ✅ Simula dados de POST com tiers e condições
- ✅ Busca uma meta existente no banco
- ✅ Tenta salvar 2 tiers de teste
- ✅ Tenta salvar 1 condição de teste
- ✅ Verifica se foram salvos no banco
- ✅ Escreve logs detalhados em `goals.log`

**Execute DEPOIS do script 1!**

---

## 📋 Passo a Passo Completo

### Etapa 1: Preparar o ambiente
```bash
# No servidor, execute:
1. Acesse: http://seu-dominio.com/create-goals-log.php
2. Verifique se TODOS os testes passaram ✅
```

**Resultado esperado**:
```
✅ Diretório existe
✅ Arquivo existe
✅ Escrita OK!
✅ Logger::info() executado sem erros!
✅ 3 logs de teste escritos!
```

---

### Etapa 2: Testar salvamento direto
```bash
1. Acesse: http://seu-dominio.com/test-save-tiers.php
2. Verifique se os tiers e condições foram salvos
```

**Resultado esperado**:
```
✅ 2 metas encontradas
✅ Tier 'Bronze' salvo
✅ Tier 'Prata' salvo
✅ Total salvos: 2/2
✅ Condition 'Conversão mínima 15%' salva
✅ Total salvas: 1/1
Tiers no banco: 2
Condições no banco: 1
✅ TESTE BEM-SUCEDIDO!
```

---

### Etapa 3: Ver os logs
```bash
1. Acesse: http://seu-dominio.com/view-all-logs.php
2. Clique no botão: 🎯 Metas/OTE (dourado no topo)
3. Você verá TODOS os logs de teste
```

**Você deve ver linhas como**:
```
[2026-01-21 10:30:15] [INFO] test-save-tiers.php - Iniciando teste para meta ID 1
[2026-01-21 10:30:15] [INFO] test-save-tiers.php - tiers is_array: YES
[2026-01-21 10:30:15] [INFO] test-save-tiers.php - tiers empty: NO
[2026-01-21 10:30:15] [INFO] test-save-tiers.php - tiers count: 2
[2026-01-21 10:30:15] [INFO] test-save-tiers.php - Tier 'Bronze' salvo com sucesso!
[2026-01-21 10:30:15] [INFO] test-save-tiers.php - Tier 'Prata' salvo com sucesso!
```

---

### Etapa 4: Testar pela interface real
```bash
1. Acesse: http://seu-dominio.com/goals/edit?id=1 (use um ID válido)
2. Habilite "Sistema de Bonificações"
3. Adicione 2 Tiers:
   - Bronze: 50%, R$ 600
   - Prata: 70%, R$ 1000
4. Habilite "Condições de Ativação"
5. Adicione 1 Condição:
   - Taxa de Conversão >= 15%
6. Salve a meta
7. Volte para: http://seu-dominio.com/view-all-logs.php
8. Clique em 🎯 Metas/OTE
```

**Você deve ver logs do GoalController**:
```
[2026-01-21 10:35:20] [INFO] Update meta - payload: {"name":"...",...}
[2026-01-21 10:35:20] [INFO] saveBonusTiers - goalId: 1
[2026-01-21 10:35:20] [INFO] saveBonusTiers - tiers is_array: YES
[2026-01-21 10:35:20] [INFO] saveBonusTiers - tiers empty: NO
```

---

## 🔍 Cenários de Diagnóstico

### ✅ Cenário 1: Teste direto funciona, interface não
**Sintoma**: `test-save-tiers.php` salva OK, mas formulário não  
**Diagnóstico**: Problema no frontend (campos não estão enviando dados)  
**Solução**: Verificar atributos `name=""` dos inputs

### ❌ Cenário 2: Nenhum dos dois funciona
**Sintoma**: Nem teste direto nem formulário salvam  
**Diagnóstico**: Problema nas tabelas do banco  
**Solução**: Verificar se as tabelas existem:
```sql
SHOW TABLES LIKE 'goal_bonus%';
DESCRIBE goal_bonus_tiers;
DESCRIBE goal_bonus_conditions;
```

### 🟡 Cenário 3: Logs não aparecem
**Sintoma**: Tudo funciona mas logs não aparecem  
**Diagnóstico**: Permissões do arquivo de log  
**Solução**: Execute o script 1 novamente

### 🟡 Cenário 4: "tiers is_array: NO"
**Sintoma**: Log mostra que `$tiers` não é um array  
**Diagnóstico**: `Request::post('tiers')` retorna string ou null  
**Solução**: Verificar `app/Helpers/Request.php`

---

## 📊 Verificação Final no Banco

Após qualquer teste, execute:

```sql
-- Ver a última meta criada/editada
SELECT id, name, enable_bonus, enable_bonus_conditions 
FROM goals 
ORDER BY updated_at DESC 
LIMIT 1;

-- Ver tiers da meta (use o ID acima)
SELECT * FROM goal_bonus_tiers WHERE goal_id = X ORDER BY tier_order;

-- Ver condições da meta (use o ID acima)
SELECT * FROM goal_bonus_conditions WHERE goal_id = X ORDER BY check_order;
```

**Resultado esperado**:
- `enable_bonus = 1`
- `enable_bonus_conditions = 1`
- 2 linhas em `goal_bonus_tiers`
- 1 linha em `goal_bonus_conditions`

---

## 🚀 URLs de Acesso Rápido

| Script | URL | Ordem |
|--------|-----|-------|
| **Criar Log** | `/create-goals-log.php` | 1️⃣ |
| **Teste Direto** | `/test-save-tiers.php` | 2️⃣ |
| **Ver Logs** | `/view-all-logs.php` → 🎯 | 3️⃣ |
| **Editar Meta** | `/goals/edit?id=X` | 4️⃣ |

---

## 📸 O que enviar se não funcionar

Se após todos os testes o problema persistir, envie:

1. **Screenshot** de `/create-goals-log.php` (página completa)
2. **Screenshot** de `/test-save-tiers.php` (página completa)
3. **Screenshot** de `/view-all-logs.php` (seção Metas/OTE)
4. **Resultado SQL**:
   ```sql
   SELECT * FROM goal_bonus_tiers WHERE goal_id = X;
   SELECT * FROM goal_bonus_conditions WHERE goal_id = X;
   ```

---

**Última atualização**: 2026-01-21  
**Arquivos criados**:
- `public/create-goals-log.php`
- `public/test-save-tiers.php`
- `public/view-all-logs.php` (atualizado)
