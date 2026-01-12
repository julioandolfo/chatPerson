# ⚡ EXECUTE AGORA - Investigação QPS Alto

**QPS Atual**: 7.764 queries/segundo 🚨  
**Status**: CRÍTICO

---

## 🎯 PASSO 1: Script PHP (Mais Fácil)

Execute este comando no PowerShell/CMD:

```bash
cd c:\laragon\www\chat
php investigar_qps_simples.php
```

**O que ele faz:**
- ✅ Ver conexões ativas
- ✅ Ver comandos mais executados
- ✅ Testar se cache está funcionando
- ✅ Verificar permissões
- ✅ Diagnóstico automático

**⚠️ ATENÇÃO AO RESULTADO:**
- Se disser "NENHUM ARQUIVO DE CACHE" → Cache não está funcionando (CAUSA PROVÁVEL!)
- Se "Teste de cache: FALHOU" → Cache não está funcionando (CAUSA PROVÁVEL!)
- Se "Conexões ativas > 50" → Problema de pool de conexões

---

## 🎯 PASSO 2: MySQL (Alternativo)

Se preferir SQL, execute no MySQL:

### 2.1 Ver Processos Ativos

```sql
SHOW FULL PROCESSLIST;
```

**O que procurar:**
- Muitas linhas (> 50) = problema de conexões
- Query repetida várias vezes = loop
- Query com Time > 5 = query travada

### 2.2 Ver Comandos Executados

```sql
SHOW GLOBAL STATUS LIKE 'Com_select';
```

**Anote o valor**, aguarde 10 segundos, execute novamente.

**Calcule**: `(valor2 - valor1) / 10` = SELECTs/segundo

**Se > 5.000 SELECTs/s**: Problema de leitura (cache não funciona ou N+1)

### 2.3 Ver Conexões

```sql
SHOW GLOBAL STATUS LIKE 'Threads_connected';
```

**Se > 50**: Muitas conexões abertas

---

## 🎯 PASSO 3: Verificar Cache Manualmente

No Windows Explorer ou PowerShell:

```powershell
dir c:\laragon\www\chat\storage\cache\queries\
```

**O que esperar:**
- ✅ Vários arquivos `.cache` recentes (modificados há poucos minutos)
- ❌ Diretório vazio = **CACHE NÃO FUNCIONA** (causa do QPS alto!)

---

## 🎯 PASSO 4: Verificar Browser

1. Abrir DevTools (F12)
2. Aba **Network**
3. Limpar (ícone 🚫)
4. Aguardar 10 segundos

**O que procurar:**
- Requisições muito frequentes (< 1s entre elas)
- Requisições em loop (mesma URL repetida)
- Erros 500/404 sendo retentados

---

## 🔍 POSSÍVEIS CAUSAS (em ordem de probabilidade)

### 1️⃣ Cache NÃO está funcionando (80% de chance)

**Sintomas:**
- Diretório `storage/cache/queries/` vazio
- Teste de cache falha
- QPS alto mesmo após correção

**Solução Rápida:**

```bash
# Verificar se diretório existe e tem permissão
cd c:\laragon\www\chat
mkdir storage\cache\queries -Force
mkdir storage\cache\permissions -Force

# Dar permissões (Windows)
icacls storage\cache /grant Everyone:(OI)(CI)F /T
```

Depois, **limpe o browser cache** (Ctrl+Shift+Delete) e recarregue a página.

---

### 2️⃣ ConversationService ainda com cache desabilitado

**Verificar:**

```bash
findstr /n "canUseCache = false" app\Services\ConversationService.php
```

**Se encontrar algo**: Cache ainda está desabilitado!

**Deve estar:**
```php
$canUseCache = self::canUseCache($filters);
```

---

### 3️⃣ Problema N+1 em loop

**Sintomas:**
- Query simples sendo executada milhares de vezes
- Exemplo: `SELECT * FROM users WHERE id = ?` executado 10.000x

**Causa:** Loop no código fazendo query a cada iteração.

**Onde procurar:**
- `ConversationService::getAll()`
- `Conversation::getAll()`
- Templates que fazem queries

---

### 4️⃣ Polling descontrolado

**Sintomas:**
- Requisições HTTP a cada < 1 segundo
- Network tab mostra loop infinito

**Solução Temporária:**

Edite `views/conversations/index.php` e adicione no topo:

```javascript
<script>
// EMERGÊNCIA: Desabilitar pollings
window.DISABLE_ALL_POLLINGS = true;
console.log('⚠️ POLLINGS DESABILITADOS');
</script>
```

Depois recarregue a página e veja se QPS cai.

---

## ✅ CHECKLIST

Execute em ordem:

- [ ] **1. Rodar `php investigar_qps_simples.php`**
- [ ] **2. Verificar resultado do teste de cache**
- [ ] **3. Verificar diretório `storage/cache/queries/`**
- [ ] **4. Se cache não funciona, criar diretórios e dar permissões**
- [ ] **5. Limpar cache do browser (Ctrl+Shift+Delete)**
- [ ] **6. Recarregar página**
- [ ] **7. Medir QPS novamente**

---

## 📊 APÓS EXECUTAR

**Cole aqui:**
1. ✅ Output completo do `investigar_qps_simples.php`
2. ✅ Quantidade de arquivos em `storage/cache/queries/`
3. ✅ Novo QPS após as correções

Com essas informações vou identificar o culpado exato! 🎯

---

**Prioridade**: 🔴 MÁXIMA  
**Tempo estimado**: 5 minutos  
**Impacto esperado**: Redução de 80-90% no QPS
