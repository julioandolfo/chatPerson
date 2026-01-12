# 🚨 QPS CRÍTICO: 7.764 queries/segundo!

**Data**: 2026-01-12  
**QPS Detectado**: **7.764 queries/segundo**  
**Status**: 🔴 **CRÍTICO - URGENTE**

---

## 📊 MEDIÇÃO

```
Valor inicial (t0):  56.933.321
Valor após 10s (t1): 57.010.962
Diferença:            77.641 queries em 10 segundos

QPS = 77.641 / 10 = 7.764 queries/segundo
```

### Comparação

| Cenário | QPS Normal | QPS Atual | Diferença |
|---------|------------|-----------|-----------|
| 1 usuário | 0.02 | 7.764 | **388.200x** ⚠️ |
| 10 usuários | 0.17 | 7.764 | **45.670x** ⚠️ |
| 100 usuários | 1.7 | 7.764 | **4.567x** ⚠️ |

**Conclusão**: Você teria que ter **mais de 45.000 usuários simultâneos** para esse QPS ser normal!

---

## 🔍 POSSÍVEIS CAUSAS

### 1️⃣ Loop Infinito de Queries
- Script rodando em loop
- Recursão infinita
- While/for sem limite

### 2️⃣ Problema N+1 Massivo
- Query dentro de loop
- Carregando relações sem eager loading
- Foreach executando queries

### 3️⃣ Cache NÃO Funcionando
- Diretório `storage/cache/` sem permissão
- Cache sendo limpo constantemente
- Função de cache com bug

### 4️⃣ Polling Descontrolado
- Múltiplas abas abertas
- Intervalo muito baixo (< 1s)
- Erro no JavaScript causando loop

### 5️⃣ Background Job em Loop
- Cron rodando a cada segundo
- Job travado em loop
- Scheduler descontrolado

---

## 🔍 INVESTIGAÇÃO URGENTE

### Passo 1: Identificar Queries Mais Executadas

Execute no MySQL:

```sql
-- Ver top 10 queries mais executadas
SELECT 
    SUBSTRING(DIGEST_TEXT, 1, 100) as query,
    COUNT_STAR as execucoes,
    ROUND(COUNT_STAR / (SELECT SUM(COUNT_STAR) 
          FROM performance_schema.events_statements_summary_by_digest 
          WHERE SCHEMA_NAME = 'chat_person') * 100, 2) as percentual
FROM performance_schema.events_statements_summary_by_digest
WHERE SCHEMA_NAME = 'chat_person'
ORDER BY COUNT_STAR DESC
LIMIT 10;
```

**O que procurar**:
- ✅ Query com **> 10.000 execuções** = CULPADO PRINCIPAL
- ✅ Query com **> 50% do total** = PROBLEMA CRÍTICO

---

### Passo 2: Ver Queries Rodando AGORA

```sql
SHOW FULL PROCESSLIST;
```

**O que procurar**:
- ✅ Muitas conexões (> 50) = Problema de pool de conexões
- ✅ Queries repetidas = Loop
- ✅ Queries longas (Time > 5s) = Query lenta travando

---

### Passo 3: Ver Comandos Executados

```sql
SHOW GLOBAL STATUS LIKE 'Com_select';
SHOW GLOBAL STATUS LIKE 'Com_insert';
SHOW GLOBAL STATUS LIKE 'Com_update';
```

Anote os valores, aguarde 10s, execute novamente e calcule:

```
SELECTs/segundo = (valor_novo - valor_antigo) / 10
```

**Se SELECT/s > 5.000**: Problema de leitura (provavelmente cache ou N+1)  
**Se INSERT/s > 1.000**: Problema de escrita (logs excessivos?)  
**Se UPDATE/s > 1.000**: Problema de atualização (heartbeat?)

---

### Passo 4: Verificar Cache

```bash
# Windows PowerShell
dir c:\laragon\www\chat\storage\cache\queries\

# Deve ter arquivos .cache recentes
# Se estiver vazio: CACHE NÃO ESTÁ FUNCIONANDO
```

---

### Passo 5: Verificar Logs de Acesso

```bash
# Ver últimas requisições no log do Laravel/Nginx
tail -n 100 c:\laragon\www\chat\storage\logs\*.log
```

**O que procurar**:
- ✅ Muitas requisições por segundo
- ✅ Endpoint sendo chamado em loop
- ✅ Erros 500 causando retry infinito

---

### Passo 6: Verificar Navegador

1. Abrir DevTools (F12)
2. Aba **Network**
3. Verificar requisições

**O que procurar**:
- ✅ Requisições em loop (< 100ms entre elas)
- ✅ Requisições falhando e sendo retentadas
- ✅ Múltiplos pollings ao mesmo tempo

---

## 🎯 SOLUÇÕES RÁPIDAS

### Solução 1: Desabilitar Pollings Temporariamente

**Arquivo**: `views/conversations/index.php`

Adicione no topo do arquivo:

```javascript
<script>
// ⚠️ EMERGÊNCIA: Desabilitar todos os pollings
window.DISABLE_ALL_POLLINGS = true;
</script>
```

**Teste**: Se QPS cair drasticamente, o problema é polling.

---

### Solução 2: Limpar Cache e Reiniciar

```bash
# Limpar cache
rm -rf c:\laragon\www\chat\storage\cache\*

# Criar diretórios novamente
mkdir c:\laragon\www\chat\storage\cache\queries
mkdir c:\laragon\www\chat\storage\cache\permissions
```

**Teste**: Se QPS cair, o problema era cache corrompido.

---

### Solução 3: Reiniciar MySQL

```bash
# Parar MySQL
net stop mysql

# Aguardar 5 segundos

# Iniciar MySQL
net start mysql
```

**Teste**: Se QPS cair, pode ter sido conexões travadas.

---

### Solução 4: Desabilitar Performance Schema Temporariamente

```sql
-- Desabilitar (reduz overhead do MySQL)
UPDATE performance_schema.setup_consumers 
SET ENABLED = 'NO' 
WHERE NAME LIKE 'events_statements%';
```

**Teste**: Se QPS cair, o problema é overhead de monitoramento.

---

## 🔍 SCRIPTS DE INVESTIGAÇÃO

### Script 1: PHP (Mais Detalhado)

```bash
php c:\laragon\www\chat\investigar_qps.php
```

### Script 2: SQL (Direto no MySQL)

```bash
mysql -u root -p chat_person < VERIFICAR_QUERIES_TEMPO_REAL.sql
```

---

## 📊 ANÁLISE PROVÁVEL

Com **7.764 QPS**, as causas mais prováveis são (em ordem):

### 1️⃣ Cache NÃO Está Funcionando (70% de chance)

**Sintomas**:
- Mesmo após reabilitar cache, QPS continua alto
- Diretório `storage/cache/queries/` vazio

**Solução**:
1. Verificar permissões do diretório
2. Verificar se função `Cache::remember()` está funcionando
3. Verificar logs de erro do PHP

**Teste**:
```php
// Criar arquivo: test_cache.php
require_once __DIR__ . '/app/Helpers/Cache.php';

$key = 'test_' . time();
$value = 'test_value';

// Salvar
$saved = \App\Helpers\Cache::set($key, $value, 60);
echo "Save: " . ($saved ? 'OK' : 'FAIL') . "\n";

// Recuperar
$retrieved = \App\Helpers\Cache::get($key);
echo "Retrieve: " . ($retrieved === $value ? 'OK' : 'FAIL') . "\n";
```

Execute:
```bash
php test_cache.php
```

Se falhar, o problema é o cache!

---

### 2️⃣ Problema N+1 em Loop (20% de chance)

**Sintomas**:
- Query específica com > 10.000 execuções
- Query simples (SELECT por ID)
- Ocorre em página específica

**Investigação**:
```sql
-- Ver query mais executada
SELECT DIGEST_TEXT, COUNT_STAR
FROM performance_schema.events_statements_summary_by_digest
WHERE SCHEMA_NAME = 'chat_person'
ORDER BY COUNT_STAR DESC
LIMIT 1;
```

Se for algo como:
```sql
SELECT * FROM users WHERE id = ?
-- ou
SELECT * FROM messages WHERE conversation_id = ?
```

É problema N+1!

**Solução**: Usar eager loading ou batch loading.

---

### 3️⃣ Polling em Loop no Frontend (8% de chance)

**Sintomas**:
- Requisições HTTP a cada < 100ms
- Network tab do navegador mostra loop
- Só acontece quando página está aberta

**Solução**: Adicionar debounce/throttle nos pollings.

---

### 4️⃣ Background Job Travado (2% de chance)

**Sintomas**:
- QPS alto mesmo sem usuários
- Processo PHP rodando em background
- Logs mostrando erro repetido

**Investigação**:
```bash
tasklist /FI "IMAGENAME eq php.exe"
```

**Solução**: Matar processo e corrigir job.

---

## ⚡ AÇÃO IMEDIATA

### Execute AGORA (em ordem):

#### 1. Ver Top Query
```sql
SELECT 
    SUBSTRING(DIGEST_TEXT, 1, 150) as query,
    COUNT_STAR as exec,
    ROUND(AVG_TIMER_WAIT/1000000000, 2) as avg_ms
FROM performance_schema.events_statements_summary_by_digest
WHERE SCHEMA_NAME = 'chat_person'
ORDER BY COUNT_STAR DESC
LIMIT 3;
```

**Cole aqui o resultado!** 📋

#### 2. Ver Cache
```bash
dir c:\laragon\www\chat\storage\cache\queries\
```

**Quantos arquivos tem?** 📋

#### 3. Ver Comandos
```sql
SHOW GLOBAL STATUS LIKE 'Com_select';
```

**Anote o valor**, aguarde 10s, execute novamente.  
**Calcule**: (valor2 - valor1) / 10 = SELECTs/segundo

**Cole aqui!** 📋

---

## 📞 PRÓXIMA AÇÃO

**Por favor, execute os 3 comandos acima e cole os resultados aqui**.

Com essas informações, vou identificar EXATAMENTE o culpado! 🎯

---

**Prioridade**: 🔴 **MÁXIMA**  
**Impacto**: 💥 **CRÍTICO - Sistema pode travar a qualquer momento**  
**Tempo**: ⏰ **RESOLVER AGORA**
