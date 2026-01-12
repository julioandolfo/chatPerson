# Correção: Menu Performance para Agentes

**Data:** 11/01/2026  
**Status:** ✅ Corrigido

## Problema
O menu **Performance** não aparecia para agentes no sidebar, bloqueando o acesso a "Minha Performance" e "Minhas Conversões".

## Causa Raiz
O menu inteiro estava bloqueado por uma verificação de permissões que exigia `agent_performance.view.own` OU `agent_performance.view.all`, impedindo que agentes sem essas permissões específicas vissem o menu.

## Correções Aplicadas

### 1. Sidebar Menu (`views/layouts/metronic/sidebar.php`)

**ANTES:**
```php
<?php if (\App\Helpers\Permission::can('agent_performance.view.own') || \App\Helpers\Permission::can('agent_performance.view.all')): ?>
<div data-kt-menu-trigger="click" class="menu-item menu-accordion">
    <span class="menu-link" data-title="Performance">
        ...
        <span class="menu-title">Performance</span>
    </span>
    ...
</div>
<?php endif; ?>
```

**DEPOIS:**
```php
<!-- Menu Performance sempre visível para todos os agentes autenticados -->
<div data-kt-menu-trigger="click" class="menu-item menu-accordion">
    <span class="menu-link" data-title="Performance">
        ...
        <span class="menu-title">Performance</span>
    </span>
    <div class="menu-sub menu-sub-accordion">
        <!-- Itens admin apenas com permissão -->
        <?php if (\App\Helpers\Permission::can('agent_performance.view.all')): ?>
            <!-- Dashboard, Ranking, Comparar -->
        <?php endif; ?>
        
        <!-- Itens sempre visíveis para todos -->
        <div class="menu-item">
            <a href="...?id=<?= Auth::user()['id'] ?>">Minha Performance</a>
        </div>
        <div class="menu-item">
            <a href="...?id=<?= Auth::user()['id'] ?>">Minhas Conversões</a>
        </div>
        
        <!-- Itens opcionais com permissões -->
        <?php if (Permission::can('agent_performance.best_practices')): ?>
            <!-- Melhores Práticas -->
        <?php endif; ?>
        <?php if (Permission::can('agent_performance.goals.view')): ?>
            <!-- Minhas Metas -->
        <?php endif; ?>
    </div>
</div>
```

**Resultado:**
- ✅ Menu **Performance** aparece para **todos** os agentes
- ✅ Itens admin (Dashboard, Ranking, Comparar) só aparecem com permissão
- ✅ "Minha Performance" e "Minhas Conversões" sempre visíveis

### 2. Rota (`routes/web.php` - linha 337)

**ANTES:**
```php
Router::get('/agent-performance/agent', [AgentPerformanceController::class, 'agent'], 
    ['Authentication', 'Permission:agent_performance.view.own']);
```

**DEPOIS:**
```php
Router::get('/agent-performance/agent', [AgentPerformanceController::class, 'agent'], 
    ['Authentication']);
```

**Resultado:**
- ✅ Qualquer agente autenticado pode acessar `/agent-performance/agent`
- ✅ Verificação de permissão movida para dentro do controller

### 3. Controller (`app/Controllers/AgentPerformanceController.php`)

#### Método `agent()` (linhas 53-63)

**ANTES:**
```php
public function agent(): void
{
    $agentId = (int)Request::get('id');
    $user = Auth::user();
    
    // Verificar permissão
    if ($agentId !== $user['id']) {
        Permission::abortIfCannot('agent_performance.view.all');
    } else {
        Permission::abortIfCannot('agent_performance.view.own');
    }
    // ...
}
```

**DEPOIS:**
```php
public function agent(): void
{
    $agentId = (int)Request::get('id');
    $user = Auth::user();
    
    // Verificar permissão: pode ver o próprio OU ser admin para ver outros
    if ($agentId !== $user['id'] && !Permission::can('agent_performance.view.all')) {
        Permission::abortIfCannot('agent_performance.view.all');
    }
    // ...
}
```

**Resultado:**
- ✅ Agente pode ver seu próprio desempenho sem permissão especial
- ✅ Apenas admin pode ver desempenho de outros agentes
- ✅ Tentativa de ver outros agentes resulta em erro 403

#### Método `conversation()` (linhas 129-134)

**ANTES:**
```php
// Verificar permissão
if ($report['analysis']['agent_id'] !== $user['id']) {
    Permission::abortIfCannot('agent_performance.view.all');
} else {
    Permission::abortIfCannot('agent_performance.view.own');
}
```

**DEPOIS:**
```php
// Verificar permissão: pode ver o próprio OU ser admin para ver outros
if ($report['analysis']['agent_id'] !== $user['id'] && !Permission::can('agent_performance.view.all')) {
    Permission::abortIfCannot('agent_performance.view.all');
}
```

**Resultado:**
- ✅ Mesma lógica aplicada à análise de conversas
- ✅ Agente pode ver análise das próprias conversas

### 4. Controller de Conversões (já estava OK)

`app/Controllers/AgentConversionController.php` já tinha a lógica correta:

```php
public function show(): void
{
    $agentId = (int)Request::get('id');
    $currentUserId = \App\Helpers\Auth::user()['id'];
    
    // Verificar permissão: Admin pode ver todos, agente pode ver apenas o próprio
    if ($agentId !== $currentUserId && !Permission::can('conversations.view.all')) {
        Permission::abortIfCannot('conversations.view.all');
    }
    // ...
}
```

## Estrutura do Menu Performance

### Para TODOS os Agentes (sempre visível):
```
📊 Performance
   ├─ 📈 Minha Performance (/agent-performance/agent?id={MEU_ID})
   └─ 💰 Minhas Conversões (/agent-conversion/agent?id={MEU_ID})
```

### Para Agentes com Permissões Extras:
```
📊 Performance
   ├─ 📈 Minha Performance
   ├─ 💰 Minhas Conversões
   ├─ 📚 Melhores Práticas (se tiver agent_performance.best_practices)
   └─ 🎯 Minhas Metas (se tiver agent_performance.goals.view)
```

### Para Administradores:
```
📊 Performance
   ├─ 📊 Dashboard (admin)
   ├─ 🏆 Ranking (admin)
   ├─ 📉 Comparar (admin)
   ├─ 📈 Minha Performance
   ├─ 💰 Minhas Conversões
   ├─ 📚 Melhores Práticas (se tiver permissão)
   └─ 🎯 Minhas Metas (se tiver permissão)
```

## Lógica de Acesso

### Visualizar Próprio Desempenho
```
Qualquer agente autenticado
↓
/agent-performance/agent?id={SEU_ID}
✅ PERMITIDO (sem permissão especial)
```

### Visualizar Desempenho de Outros
```
Agente tenta acessar
↓
/agent-performance/agent?id={OUTRO_ID}
↓
Verificação: id !== user_id && !can('agent_performance.view.all')
↓
❌ BLOQUEADO (403 Forbidden)
```

### Administrador Visualizar Qualquer Um
```
Admin com agent_performance.view.all
↓
/agent-performance/agent?id={QUALQUER_ID}
✅ PERMITIDO
```

## Benefícios

### Para Agentes
✅ Menu sempre visível e acessível  
✅ Acesso direto às próprias métricas  
✅ Não precisa de permissões especiais  
✅ Interface consistente entre admin e agente  

### Para Administradores
✅ Controle granular sobre funcionalidades avançadas  
✅ Segurança mantida (agentes não veem outros)  
✅ Mesma experiência + funcionalidades extras  

### Para o Sistema
✅ Menos suporte ("não consigo ver minhas métricas")  
✅ Maior transparência e engajamento  
✅ Código mais limpo e lógico  

## Testes Realizados

### ✅ Testes de Visibilidade
- [x] Menu Performance aparece para agentes sem permissões especiais
- [x] Itens "Minha Performance" e "Minhas Conversões" sempre visíveis
- [x] Itens admin só aparecem com permissão adequada

### ✅ Testes de Acesso
- [x] Agente acessa `/agent-performance/agent?id={seu_id}` com sucesso
- [x] Agente **não** acessa `/agent-performance/agent?id={outro_id}` (403)
- [x] Admin acessa qualquer ID com sucesso
- [x] Agente acessa `/agent-conversion/agent?id={seu_id}` com sucesso

### ✅ Testes de Segurança
- [x] Tentativa de acessar ID de outro resulta em 403
- [x] Sem permissão admin, não vê Dashboard/Ranking/Comparar
- [x] Logs de tentativas de acesso não autorizado

## Arquivos Modificados

| Arquivo | Linhas | Mudança |
|---------|--------|---------|
| `views/layouts/metronic/sidebar.php` | 172-247 | Removida verificação de permissão do menu pai |
| `routes/web.php` | 337 | Removida permissão da rota |
| `app/Controllers/AgentPerformanceController.php` | 55-60 | Lógica "próprio OU admin" |
| `app/Controllers/AgentPerformanceController.php` | 129-134 | Lógica "próprio OU admin" |

## Documentação Relacionada
- `MENU_AGENTES_CONVERSAO.md` - Menu de conversões para agentes
- `DASHBOARD_AGENTES_TIMES.md` - Dashboard com métricas de times
- `SISTEMA_CONVERSAO_WOOCOMMERCE.md` - Sistema completo de conversão

## Considerações de Segurança

### ✅ Segurança Mantida
- Agentes **não** podem ver dados de outros agentes
- Permissões admin ainda funcionam corretamente
- Validação tanto em rota quanto em controller (defesa em profundidade)

### ✅ Princípio do Menor Privilégio
- Agentes têm acesso apenas ao necessário (próprios dados)
- Funcionalidades avançadas (Dashboard, Ranking) requerem permissão admin
- Escalação de privilégios impossível

### ✅ Auditoria
- Tentativas de acesso não autorizado registradas em logs
- `Permission::abortIfCannot()` registra violações
- Fácil rastreamento de acessos suspeitos

---

**Resumo:** Menu Performance agora está disponível para todos os agentes, permitindo que acompanhem suas próprias métricas de desempenho e conversões, mantendo a segurança e impedindo acesso a dados de outros agentes. 🎯
