# 🚀 PLANO DE OTIMIZAÇÃO COMPLETA - Reduzir QPS de 33.2 para < 10

**Status Atual**: 33.2 QPS com 1 conexão ativa  
**Objetivo**: < 10 QPS (70% de redução adicional)  
**Já Reduzido**: 99.6% (de 7.764 → 33.2)

---

## 📊 ANÁLISE ATUAL

### ✅ Já Otimizado
- [x] Índices em subqueries (4 índices criados)
- [x] Cache agressivo em ConversationService
- [x] TTL aumentado para 900s
- [x] Pollings principais (badges 60s, SLA 60s, coaching 60s)
- [x] Cache em DashboardService

### ⏳ A Investigar
- [ ] Outros pollings ativos
- [ ] Services sem cache
- [ ] Controllers sem cache
- [ ] Queries em loops
- [ ] Background jobs

---

## 🔍 ETAPA 1: Identificar Todos os Pollings

### Execute:

```bash
docker exec -it t4gss4040cckwwgs0cso04wo-202508702814 sh
php identificar_todos_pollings.php
```

**O que faz**:
- Varre todos os arquivos JS
- Identifica todos os `setInterval`
- Calcula queries/hora por aba
- Lista os 5 pollings mais frequentes

**Cole aqui o resultado!** 📋

---

## 🔍 ETAPA 2: Identificar Oportunidades de Cache

### Execute:

```bash
php identificar_oportunidades_cache.php
```

**O que faz**:
- Varre todos os Services
- Identifica métodos que fazem queries SEM cache
- Lista top 10 services com mais oportunidades

**Cole aqui o resultado!** 📋

---

## 🔍 ETAPA 3: Analisar Logs de Acesso

### Execute:

```bash
# Ver últimas 100 requisições
tail -100 /var/www/html/storage/logs/access.log | grep -E "GET|POST" | awk '{print $7}' | sort | uniq -c | sort -rn | head -20
```

**O que faz**:
- Mostra top 20 endpoints mais chamados
- Identifica APIs com alto tráfego

**Cole aqui o resultado!** 📋

---

## 🎯 ÁREAS DE OTIMIZAÇÃO PREVISTAS

### 1️⃣ Pollings Adicionais

**Pollings que podem estar ativos**:
- Activity Tracker (heartbeat) - A cada 30s
- Realtime Coaching - A cada 60s (já otimizado?)
- Notification Badge - A cada 60s
- Dashboard Metrics - A cada 30s?
- Agent Status - A cada 60s?

**Otimização**: Aumentar intervalos ou adicionar cache.

---

### 2️⃣ Services Sem Cache

**Services prováveis sem cache**:
- `ContactService`
- `TagService`
- `DepartmentService`
- `FunnelService`
- `UserService`
- `MessageService`

**Otimização**: Adicionar cache em métodos de leitura.

---

### 3️⃣ Dashboard Queries

**Queries pesadas no dashboard**:
- Métricas em tempo real
- Contadores
- Gráficos

**Otimização**: 
- Cache de 2-5 minutos
- Pré-calcular métricas em background

---

### 4️⃣ Queries em Loops (N+1)

**Onde procurar**:
- Templates (views)
- Controllers com `foreach`
- Relações não eager-loaded

**Otimização**: Eager loading ou batch queries.

---

## 📊 IMPACTO ESTIMADO

| Otimização | Implementação | Ganho | QPS Final |
|------------|--------------|-------|-----------|
| **Atual** | - | - | 33.2 |
| **Otimizar pollings** | 30 min | 30% | 23.2 |
| **Cache em Services** | 2h | 40% | 14 |
| **Cache em Dashboard** | 1h | 20% | 11 |
| **Eliminar N+1** | 2h | 20% | **8.8** ⚡ |

**Ganho Total**: **73% adicional** (de 33.2 → 8.8 QPS)

---

## ⚡ EXECUTE AGORA

### Passo 1: Identificar Pollings

```bash
docker exec -it t4gss4040cckwwgs0cso04wo-202508702814 sh
php identificar_todos_pollings.php
```

### Passo 2: Identificar Oportunidades de Cache

```bash
php identificar_oportunidades_cache.php
```

### Passo 3: Analisar Logs (se existir)

```bash
ls -lh /var/www/html/storage/logs/
# Se existir access.log ou similar, analisar
```

---

## 📋 CHECKLIST

- [ ] Executar `identificar_todos_pollings.php`
- [ ] Executar `identificar_oportunidades_cache.php`
- [ ] Analisar logs de acesso
- [ ] Priorizar otimizações por impacto
- [ ] Implementar top 3 otimizações
- [ ] Medir novo QPS
- [ ] Repetir até < 10 QPS

---

## 🎯 META FINAL

```
QPS Atual:  33.2
QPS Meta:   < 10
Redução:    70%
Prazo:      2-4 horas de implementação
```

---

**Cole aqui os resultados dos 2 scripts para começarmos!** 🚀
