# 📊 Sistema de Logs do Dashboard

## 📁 Estrutura

```
logs/
  └── dash.log          # Log detalhado do dashboard
public/
  └── view-dash-logs.php # Visualizador de logs
```

## 🚀 Como Usar

### 1. Visualizar Logs em Tempo Real

Acesse o visualizador de logs:
```
http://seu-dominio/view-dash-logs.php
```

**Recursos:**
- ✅ Visualização colorida e formatada
- ✅ Estatísticas (Total de linhas, Erros, Avisos, Último log)
- ✅ Auto-refresh (5 segundos)
- ✅ Limpar logs com um clique
- ✅ Últimas 100 linhas (mais recentes primeiro)

### 2. Visualizar Logs Direto no Servidor

```bash
# Ver logs em tempo real
tail -f logs/dash.log

# Ver últimas 50 linhas
tail -n 50 logs/dash.log

# Buscar por palavra-chave
grep "myConversations" logs/dash.log

# Contar erros
grep -c "ERRO" logs/dash.log
```

### 3. Limpar Logs

**Via navegador:**
```
http://seu-dominio/view-dash-logs.php
# Clicar no botão "🗑️ Limpar Logs"
```

**Via terminal:**
```bash
> logs/dash.log
# ou
echo "" > logs/dash.log
```

## 📝 Formato dos Logs

```
[2025-12-18 16:52:08] Carregando stats para userId=1, dateFrom=2025-12-01, dateTo=2025-12-18 16:52:08
[2025-12-18 16:52:08] [DashboardService] getGeneralStats: userId=1, dateFrom=2025-12-01, dateTo=2025-12-18 16:52:08
[2025-12-18 16:52:08] [DashboardService] totalConversations=14
[2025-12-18 16:52:08] [DashboardService] openConversations=14
[2025-12-18 16:52:08] [DashboardService] closedConversations=0
[2025-12-18 16:52:08] [DashboardService] myConversations=1, myOpenConversations=1
[2025-12-18 16:52:08] [DashboardService] getMyConversations: userId=1, total=1
[2025-12-18 16:52:08] [DashboardService] unassignedConversations=10
[2025-12-18 16:52:08] [DashboardService] getUnassignedConversations: total=10
[2025-12-18 16:52:08] [DashboardService] avgFirstResponseTime=6.33, avgResponseTime=6.33
[2025-12-18 16:52:08] generalStats = {"conversations":{"total":14,"open":14,"closed":0,...}}
[2025-12-18 16:52:08] Passando dados para view
```

## 🔍 O Que Procurar

### ✅ Dashboard Funcionando Corretamente

Você deve ver:
```
myConversations=1, myOpenConversations=1
unassignedConversations=10
avgFirstResponseTime=6.33
totalConversations=14
Passando dados para view
```

### ❌ Problemas Comuns

**1. Métricas zeradas:**
```
totalConversations=0
myConversations=0
```
**Causa:** Filtro de data muito restritivo ou sem dados no período
**Solução:** Verificar `dateFrom` e `dateTo` nos logs

**2. Erro SQL:**
```
ERRO CRÍTICO: SQLSTATE[42000]: Syntax error...
```
**Causa:** Query SQL malformada
**Solução:** Verificar stack trace no log

**3. Dados não chegam na view:**
```
Carregando stats...
(sem log "Passando dados para view")
```
**Causa:** Exception silenciosa no DashboardService
**Solução:** Verificar logs por "ERRO CRÍTICO"

**4. JSON malformado:**
```
generalStats = {"conversations":{"total":14,"open"...
(sem fechar o JSON)
```
**Causa:** Dados corrompidos
**Solução:** Verificar estrutura do array retornado

## 🧪 Fluxo de Debug

### Passo 1: Acessar Dashboard
```
http://seu-dominio/dashboard
```

### Passo 2: Abrir Visualizador de Logs
```
http://seu-dominio/view-dash-logs.php
```

### Passo 3: Verificar Sequência de Logs

**Sequência esperada:**
1. ✅ `Carregando stats para userId=X`
2. ✅ `[DashboardService] getGeneralStats: userId=X`
3. ✅ `totalConversations=Y`
4. ✅ `openConversations=Z`
5. ✅ `myConversations=A, myOpenConversations=B`
6. ✅ `unassignedConversations=C`
7. ✅ `avgFirstResponseTime=D.DD`
8. ✅ `generalStats = {...}`
9. ✅ `Passando dados para view`

**Se a sequência parar em algum ponto**, o problema está naquele método específico.

### Passo 4: Comparar com Teste

Execute:
```
http://seu-dominio/test-dashboard-metrics.php
```

Compare os valores:
- **Teste:** `Total de Conversas do Agente = 1`
- **Log:** `myConversations=1`

Se os valores forem **diferentes**, há um problema na query SQL.

Se os valores forem **iguais**, mas o dashboard mostra `0`, o problema está na view ou JavaScript.

## 🎯 Checklist de Debug

- [ ] Logs mostram valores corretos? (myConversations, unassignedConversations, etc)
- [ ] Teste mostra os mesmos valores?
- [ ] Log mostra "Passando dados para view"?
- [ ] Não há "ERRO CRÍTICO" nos logs?
- [ ] JSON do generalStats está completo?
- [ ] Console do navegador (F12) mostra erros JavaScript?
- [ ] Cache do navegador foi limpo (Ctrl+Shift+R)?

## 🛠️ Manutenção

### Rotação de Logs

Se o arquivo `logs/dash.log` ficar muito grande, você pode rotacioná-lo:

```bash
# Fazer backup
mv logs/dash.log logs/dash.log.backup

# Criar novo arquivo vazio
touch logs/dash.log
chmod 644 logs/dash.log
```

### Desabilitar Logs

Para desabilitar os logs (em produção, por exemplo), comente as chamadas `self::logDash()` em:
- `app/Controllers/DashboardController.php`
- `app/Services/DashboardService.php`

## 📚 Arquivos Relacionados

- `app/Controllers/DashboardController.php` - Adiciona logs no controller
- `app/Services/DashboardService.php` - Adiciona logs no service
- `public/view-dash-logs.php` - Visualizador web de logs
- `public/test-dashboard-metrics.php` - Script de teste de métricas
- `logs/dash.log` - Arquivo de log

---

**Data:** 18/12/2024
**Autor:** AI Assistant
**Status:** ✅ Ativo

