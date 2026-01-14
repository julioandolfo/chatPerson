# 📚 MySQL Query Cache Explicado

**Data**: 2026-01-13  
**Para**: Entender e configurar MySQL Query Cache

---

## 🤔 O QUE É MYSQL QUERY CACHE?

O **MySQL Query Cache** é um sistema de cache **NATIVO do MySQL** que armazena o resultado completo de queries SELECT na memória RAM.

### Como Funciona?

```
1️⃣ Aplicação → MySQL: "SELECT * FROM users WHERE id = 1"
2️⃣ MySQL verifica se já executou essa query exata antes
3️⃣ Se SIM → Retorna resultado do cache (RAM) ⚡ SUPER RÁPIDO
4️⃣ Se NÃO → Executa query → Salva resultado no cache → Retorna
```

---

## ⚡ VANTAGENS

### 1. Extremamente Rápido
- **Sem acesso a disco** - Dados estão na RAM
- **Sem processamento** - Não precisa executar a query novamente
- **10-100x mais rápido** que executar a query

### 2. Reduz Carga no Banco
- CPU do MySQL fica livre
- Menos I/O de disco
- Mais queries simultâneas

### 3. Zero Código
- Não precisa mudar nada no código PHP
- Funciona automaticamente
- Transparente para aplicação

---

## ❌ DESVANTAGENS

### 1. Invalidação Automática
**QUALQUER** alteração na tabela invalida **TODO** o cache daquela tabela.

```sql
-- ✅ Cache está ativo
SELECT * FROM users; -- Cache HIT ⚡

-- ❌ Alguém faz um INSERT/UPDATE/DELETE
INSERT INTO users VALUES (...);

-- ❌ Cache é COMPLETAMENTE invalidado
SELECT * FROM users; -- Cache MISS (precisa executar query de novo)
```

**Problema**: Em tabelas com muitas escritas (INSERT/UPDATE/DELETE), o cache é invalidado constantemente e vira desperdício.

---

### 2. Tabelas Boas vs Ruins para Cache

#### ✅ BOAS para Query Cache
Tabelas de **LEITURA** (muitos SELECTs, poucos writes):
- `settings` - Configurações do sistema
- `departments` - Setores (mudam pouco)
- `funnels` - Funis (mudam pouco)
- `funnel_stages` - Etapas de funis
- `roles` - Perfis de usuários
- `permissions` - Permissões
- `tags` - Tags

#### ❌ RUINS para Query Cache
Tabelas de **ESCRITA** (muitos INSERTs/UPDATEs):
- `messages` - Novas mensagens a cada segundo
- `conversations` - Status muda constantemente
- `activities` - Logs de atividade
- `realtime_coaching_hints` - Hints em tempo real

---

### 3. Query Precisa Ser IDÊNTICA

O MySQL compara a query **byte a byte**. Se mudar **QUALQUER COISA**, é cache miss.

```sql
-- Query 1
SELECT * FROM users WHERE id = 1;  -- Cache MISS (primeira vez)

-- Query 2 (MESMA query)
SELECT * FROM users WHERE id = 1;  -- Cache HIT ⚡

-- Query 3 (diferente - espaço extra)
SELECT  * FROM users WHERE id = 1; -- Cache MISS ❌

-- Query 4 (diferente - maiúscula)
select * from users where id = 1;  -- Cache MISS ❌

-- Query 5 (diferente - parâmetro)
SELECT * FROM users WHERE id = 2;  -- Cache MISS ❌
```

**Problema**: Queries dinâmicas (com diferentes parâmetros) raramente aproveitam o cache.

---

### 4. Removido no MySQL 8.0

**⚠️ IMPORTANTE**: O MySQL Query Cache foi **DESCONTINUADO** no MySQL 8.0 e **REMOVIDO** completamente no MySQL 8.0.16+.

**Por quê?**
- Causava gargalos em sistemas multi-core
- Lock global do cache causava contenção
- Invalidação agressiva tornava inútil em muitos casos
- Cache em aplicação (Redis, Memcached) é melhor

---

## 🔍 VERIFICAR SE TEM QUERY CACHE

### MySQL 5.7 e Anteriores

```sql
SHOW VARIABLES LIKE 'query_cache%';
```

**Resultado Esperado**:
```
+------------------------------+---------+
| Variable_name                | Value   |
+------------------------------+---------+
| query_cache_limit            | 1048576 |  -- 1MB (máximo por query)
| query_cache_min_res_unit     | 4096    |  -- 4KB (unidade mínima)
| query_cache_size             | 0       |  -- 0 = DESABILITADO ❌
| query_cache_type             | OFF     |  -- OFF = DESABILITADO ❌
| query_cache_wlock_invalidate | OFF     |
+------------------------------+---------+
```

### MySQL 8.0+

```sql
SHOW VARIABLES LIKE 'query_cache%';
```

**Resultado Esperado**:
```
Empty set (0.00 sec)
```

**Significa**: MySQL 8.0+ **NÃO TEM** Query Cache.

---

## 🎯 SEU CASO: Qual versão você tem?

Vamos descobrir:

```bash
docker exec -it t4gss4040cckwwgs0cso04wo-191453204612 sh
mysql -uchatperson -p chat_person

SELECT VERSION();
```

**Possíveis Resultados**:

### Se for MySQL 5.7 ou anterior:
```
+-------------------------+
| VERSION()               |
+-------------------------+
| 5.7.42-log              |  ✅ TEM Query Cache disponível
+-------------------------+
```

### Se for MySQL 8.0+:
```
+-------------------------+
| VERSION()               |
+-------------------------+
| 8.0.35                  |  ❌ NÃO TEM Query Cache (foi removido)
+-------------------------+
```

---

## ⚙️ COMO ATIVAR (MySQL 5.7)

### Verificar Status Atual

```sql
SHOW VARIABLES LIKE 'query_cache%';
```

### Ativar Query Cache

**Opção 1: Via SQL (Temporário - reinicia ao reboot)**

```sql
SET GLOBAL query_cache_type = 1;
SET GLOBAL query_cache_size = 67108864;  -- 64MB
```

**Opção 2: Via Configuração (Permanente)**

Adicionar no arquivo `/etc/my.cnf` ou `/etc/mysql/my.cnf`:

```ini
[mysqld]
query_cache_type = 1        # 0=OFF, 1=ON, 2=DEMAND
query_cache_size = 64M      # Tamanho do cache (64MB recomendado)
query_cache_limit = 2M      # Máximo por query (2MB)
query_cache_min_res_unit = 4K
```

Depois reiniciar MySQL:

```bash
docker restart SEU_CONTAINER
```

### Verificar Se Está Funcionando

```sql
SHOW STATUS LIKE 'Qcache%';
```

**Resultado Esperado (funcionando)**:
```
+-------------------------+---------+
| Variable_name           | Value   |
+-------------------------+---------+
| Qcache_free_blocks      | 1       |  -- Blocos livres
| Qcache_free_memory      | 67096488|  -- Memória livre
| Qcache_hits             | 12453   |  ⚡ Cache HITS (QUANTO MAIOR, MELHOR)
| Qcache_inserts          | 3421    |  -- Queries adicionadas ao cache
| Qcache_lowmem_prunes    | 0       |  -- Queries removidas por falta de memória
| Qcache_not_cached       | 1245    |  -- Queries que não foram cacheadas
| Qcache_queries_in_cache | 523     |  -- Queries atualmente no cache
| Qcache_total_blocks     | 1124    |  -- Total de blocos
+-------------------------+---------+
```

**Métrica Principal**: `Qcache_hits` - Quanto **MAIOR**, **MELHOR**!

---

## 📊 CALCULAR EFICIÊNCIA DO CACHE

```sql
SHOW STATUS LIKE 'Qcache%';
SHOW STATUS LIKE 'Com_select';
```

**Fórmula**:
```
Cache Hit Rate = Qcache_hits / (Qcache_hits + Com_select) * 100
```

**Exemplo**:
- `Qcache_hits` = 10.000
- `Com_select` = 2.000

```
Hit Rate = 10.000 / (10.000 + 2.000) * 100 = 83.3% ✅ EXCELENTE
```

**Interpretação**:
- **> 80%**: ✅ EXCELENTE - Cache está funcionando muito bem
- **50-80%**: 🟡 BOM - Cache está ajudando
- **< 50%**: 🟠 RAZOÁVEL - Muitas queries únicas ou tabelas com muitas escritas
- **< 20%**: 🔴 RUIM - Cache não está ajudando, considere desabilitar

---

## 🎯 RECOMENDAÇÃO PARA SEU SISTEMA

### Se você tem MySQL 5.7:

#### ✅ ATIVAR Query Cache SE:
1. Você tem muitas consultas a tabelas de configuração (settings, departments, etc)
2. Seu sistema faz **MAIS leituras** que escritas
3. Você tem RAM sobrando (pelo menos 64MB livres)

**Configuração Recomendada**:
```ini
[mysqld]
query_cache_type = 1
query_cache_size = 64M      # Ajustar baseado na RAM disponível
query_cache_limit = 2M
```

---

### Se você tem MySQL 8.0+:

#### ❌ Query Cache NÃO EXISTE

**Alternativas**:

1. **Cache em Aplicação** (✅ O que você já está fazendo!)
   - `App\Helpers\Cache` com arquivos
   - Melhor que Query Cache do MySQL
   - Mais controle sobre invalidação

2. **Redis** (🚀 Melhor opção para produção)
   - Cache em memória compartilhado
   - 10-100x mais rápido que arquivos
   - Escala horizontalmente

3. **Memcached** (🚀 Alternativa ao Redis)
   - Similar ao Redis
   - Mais simples
   - Menos features

---

## 💡 COMPARAÇÃO: Query Cache vs Application Cache

| Aspecto | MySQL Query Cache | Application Cache (PHP) |
|---------|-------------------|-------------------------|
| **Velocidade** | ⚡⚡⚡ RAM | ⚡⚡ Arquivos / ⚡⚡⚡ Redis |
| **Controle** | ❌ Nenhum | ✅ Total |
| **Invalidação** | ❌ Automática (tabela inteira) | ✅ Seletiva (por chave) |
| **TTL** | ❌ Não tem | ✅ Sim (configurável) |
| **Transparente** | ✅ Sim | ❌ Precisa código |
| **Multi-tabelas** | ❌ Invalida todas | ✅ Controle fino |
| **Disponibilidade** | ❌ MySQL 5.7 | ✅ Qualquer versão |
| **Escalabilidade** | ❌ Causa gargalos | ✅ Escalável |

---

## 🎉 CONCLUSÃO

### Para MySQL 5.7:
**Vale a pena ativar** se você tem muitas consultas a tabelas de configuração. Ganho de **10-30%** em alguns casos.

### Para MySQL 8.0+:
**Não existe Query Cache**. Continue usando Application Cache (que é o que você já está fazendo e é MELHOR!).

### Recomendação Final:
**O cache que você já implementou** (`App\Helpers\Cache`) é **SUPERIOR** ao MySQL Query Cache porque:
- ✅ Controle total sobre invalidação
- ✅ TTL configurável
- ✅ Funciona em qualquer versão do MySQL
- ✅ Pode cachear dados processados (não só queries)
- ✅ Não causa gargalos

**Se quiser melhorar ainda mais**:
1. ✅ Implemente Redis (melhor opção) - **40-50% de ganho adicional**
2. ✅ Continue adicionando cache em Application (como fizemos agora)
3. ❌ Não dependa de MySQL Query Cache (obsoleto)

---

## 🧪 PRÓXIMO PASSO

**Execute no seu container**:

```bash
docker exec -it t4gss4040cckwwgs0cso04wo-191453204612 sh
mysql -uchatperson -p chat_person

-- Verificar versão do MySQL
SELECT VERSION();

-- Se for MySQL 5.7, verificar Query Cache
SHOW VARIABLES LIKE 'query_cache%';
SHOW STATUS LIKE 'Qcache%';

exit
exit
```

**Me mostre o resultado que eu te digo se vale a pena ativar ou não!** 😊

---

**Data**: 2026-01-13  
**Status**: ✅ EXPLICAÇÃO COMPLETA
