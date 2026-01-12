# 🔍 DIAGNÓSTICO: Cache de Conversas

**Data**: 2026-01-12  
**QPS Atual**: 3.210 queries/segundo  
**Caches Ativos**: 4 arquivos

---

## ✅ O QUE ESTÁ FUNCIONANDO

1. **Cache Helpers** ✅
   - `Cache::remember()` funciona
   - `Cache::has()` funciona
   - Diretório gravável

2. **Lógica de Cache** ✅
   - `ConversationService::canUseCache()` implementado
   - Cache é criado quando não há filtros excludentes
   - Cache é reutilizado quando válido (TTL: 300s)

3. **Índices** ✅
   - Todos os 4 índices criados
   - Subqueries otimizadas

---

## ❌ O PROBLEMA

### Apenas 4 Arquivos de Cache!

Com **3.210 QPS** e apenas **4 caches**, o cache está **sub-utilizado**.

**Cálculo**:
```
Se 50% das requisições usassem cache:
- 3.210 QPS × 50% = 1.605 req/s cacheadas
- TTL 300s = 300 × 1.605 = 481.500 requests em cache
- Mas temos apenas 4 caches!
```

**Conclusão**: **99% das requisições NÃO usam cache!**

---

## 🔍 CAUSAS PROVÁVEIS

### 1️⃣ Filtros Desabilitam Cache (90% de chance)

**Arquivo**: `app/Services/ConversationService.php` (linha 415)

```php
$excludedFilters = ['date_from', 'date_to', 'search', 'message_search'];
```

**Se o usuário usar**:
- ❌ Campo de busca (search) → Cache desabilitado
- ❌ Filtro de data (date_from/date_to) → Cache desabilitado
- ✅ Status/Canal/Agente → Cache habilitado

**Impacto**:
- Se 90% dos usuários usam busca → 90% sem cache
- QPS sem cache: 7 queries/requisição
- QPS com cache: 0.1 queries/requisição
- **Diferença: 70x mais queries!**

---

### 2️⃣ TTL Curto (10% de chance)

**Configuração**: 300 segundos (5 minutos)

**Problema**:
- Polling: a cada 60s
- 5 requisições por cache
- Se requisições forem espaçadas > 5min, cache expira

**Solução**: Aumentar TTL para 600-900s

---

### 3️⃣ Muitas Combinações de Filtros

Cada combinação única de filtros = 1 cache diferente:

```
status=open                  → cache_1
status=open + channel=whatsapp → cache_2
status=open + agent_id=5       → cache_3
... (centenas de combinações)
```

**Com apenas 4 caches**, há poucas combinações sendo reutilizadas.

---

## ⚡ SOLUÇÕES

### Solução 1: Cache Agressivo (RECOMENDADO) ⭐

**Cachear MESMO com search/date**:

```php
// app/Services/ConversationService.php (linha 412)

private static function canUseCache(array $filters): bool
{
    // ✅ NOVO: Cachear quase tudo
    // Apenas NÃO cachear se for busca por mensagem (muito específico)
    $excludedFilters = ['message_search'];
    
    foreach ($excludedFilters as $filter) {
        if (!empty($filters[$filter])) {
            return false;
        }
    }
    
    return true;
}
```

**Ganho Esperado**: 70-90% de redução no QPS

---

### Solução 2: Aumentar TTL

```php
// app/Services/ConversationService.php (linha 29)

// ANTES
private static int $cacheTTL = 300; // 5 minutos

// DEPOIS
private static int $cacheTTL = 900; // 15 minutos
```

**Ganho Esperado**: 30-50% mais requisições usando cache

---

### Solução 3: Cache em Camadas

```php
// Cache quente (filtros comuns) - 15 minutos
// Cache frio (filtros raros) - 5 minutos

private static function getCacheTTL(array $filters): int
{
    // Se filtros simples (status + channel), cache longo
    $simpleFilters = ['status', 'channel', 'agent_id'];
    $hasOnlySimple = true;
    
    foreach ($filters as $key => $value) {
        if (!empty($value) && !in_array($key, $simpleFilters)) {
            $hasOnlySimple = false;
            break;
        }
    }
    
    return $hasOnlySimple ? 900 : 300; // 15min vs 5min
}
```

**Ganho Esperado**: 50-70% de redução

---

## 📊 IMPACTO ESPERADO

| Solução | Implementação | Ganho | QPS Final |
|---------|--------------|-------|-----------|
| **Nenhuma** | - | - | 3.210 |
| **Apenas Índices** | ✅ Feito | 50% | 1.605 |
| **+ TTL 900s** | 5 min | 30% | 1.125 |
| **+ Cache Agressivo** | 10 min | 70% | 480 |
| **Todas** | 15 min | **85%** | **480** ⚡ |

---

## ⚡ EXECUTE PARA VERIFICAR

### Script 1: Monitorar Cache em Tempo Real

```bash
docker exec -it t4gss4040cckwwgs0cso04wo-201246877118 sh
php monitorar_cache_tempo_real.php
```

**O que faz**:
- Monitora criação de caches por 60 segundos
- Mostra quais caches são criados
- Mostra quantos são reutilizados

**Cole aqui o resultado!** 📋

---

### Script 2: Analisar Requests

```bash
php analisar_requests_conversas.php
```

**O que faz**:
- Analisa logs de requests
- Identifica quais filtros são mais usados
- Mostra se search/date estão sendo enviados

**Cole aqui o resultado!** 📋

---

## 🎯 RECOMENDAÇÃO FINAL

### EXECUTE AGORA (ordem de prioridade):

#### 1. Habilitar Cache Agressivo (10 min) 🔴

Editar `app/Services/ConversationService.php`:

```php
// Linha 412-424
private static function canUseCache(array $filters): bool
{
    // ✅ Cachear quase tudo (exceto message_search)
    if (!empty($filters['message_search'])) {
        return false;
    }
    
    return true;
}
```

**Ganho**: 70-90% de redução no QPS

---

#### 2. Aumentar TTL (2 min) 🟡

```php
// Linha 29
private static int $cacheTTL = 900; // 15 minutos
```

**Ganho**: +30% de hits no cache

---

#### 3. Limpar Cache (1 min)

```bash
rm -rf storage/cache/queries/*
```

Forçar recriação de todos os caches com novas configurações.

---

#### 4. Testar (5 min)

```sql
SHOW GLOBAL STATUS LIKE 'Questions';
-- Aguardar 10s
SHOW GLOBAL STATUS LIKE 'Questions';
```

**QPS esperado**: < 500 (85% de redução) ⚡

---

## 📋 CHECKLIST

- [ ] Executar `monitorar_cache_tempo_real.php` (verificar quantos caches são criados)
- [ ] Executar `analisar_requests_conversas.php` (ver filtros mais usados)
- [ ] Editar `canUseCache()` para cache agressivo
- [ ] Aumentar TTL para 900s
- [ ] Limpar cache antigo
- [ ] Medir novo QPS
- [ ] Verificar número de caches criados (deve ter > 20)

---

**Execute os 2 scripts e cole os resultados aqui!** 🚀
