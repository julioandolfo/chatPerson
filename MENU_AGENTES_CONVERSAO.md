# Menu de Conversão para Agentes

**Data:** 11/01/2026  
**Status:** ✅ Implementado

## Objetivo
Permitir que os agentes acompanhem suas próprias conversões WooCommerce através do menu lateral, sem precisar de permissões de administrador.

## Implementação

### 1. Sidebar Menu (`views/layouts/metronic/sidebar.php`)
- ✅ Adicionado item **"Minhas Conversões"** no menu **Performance**
- ✅ Link aponta para `/agent-conversion/agent?id={ID_DO_AGENTE}`
- ✅ Fica ao lado de "Minha Performance"
- ✅ Disponível para todos os agentes autenticados

```php
<div class="menu-item">
    <a class="menu-link <?= isActive('/agent-conversion/agent', $currentUri) ? 'active' : '' ?>" 
       href="<?= \App\Helpers\Url::to('/agent-conversion/agent?id=' . \App\Helpers\Auth::user()['id']) ?>">
        <span class="menu-bullet">
            <span class="bullet bullet-dot"></span>
        </span>
        <span class="menu-title">Minhas Conversões</span>
    </a>
</div>
```

### 2. Controller (`app/Controllers/AgentConversionController.php`)
- ✅ Atualizado método `show()` para permitir que agentes vejam suas próprias conversões
- ✅ Mantida restrição de administrador para ver conversões de outros agentes
- ✅ Lógica de permissão: "Você pode ver o seu próprio OU ser admin para ver de outros"

```php
public function show(): void
{
    $agentId = (int)Request::get('id');
    $currentUserId = \App\Helpers\Auth::user()['id'];
    
    // Verificar permissão: Admin pode ver todos, agente pode ver apenas o próprio
    if ($agentId !== $currentUserId && !Permission::can('conversations.view.all')) {
        Permission::abortIfCannot('conversations.view.all');
    }
    
    // ... resto do código
}
```

## Funcionalidades Disponíveis para Agentes

### Menu Performance (Visível para todos agentes)
1. **Dashboard** (somente admin) - `conversations.view.all`
2. **Ranking** (somente admin) - `conversations.view.all`
3. **Comparar** (somente admin) - `conversations.view.all`
4. ✅ **Minha Performance** - todos podem ver
5. ✅ **Minhas Conversões** - todos podem ver (NOVO)
6. **Melhores Práticas** (se tiver permissão) - `agent_performance.best_practices`
7. **Minhas Metas** (se tiver permissão) - `agent_performance.goals.view`

## Informações Exibidas em "Minhas Conversões"

### Métricas do Período
- 💰 **Faturamento Total** - Soma de todos os pedidos válidos
- 🛒 **Pedidos Gerados** - Total de pedidos vinculados
- 💬 **Conversas Atendidas** - Total de conversas atribuídas (histórico completo)
- 📊 **Taxa de Conversão** - `(Pedidos / Conversas) × 100%`
- 💵 **Ticket Médio** - `Faturamento / Pedidos`

### Pedidos Recentes
- ID do Pedido (link para WooCommerce)
- Cliente (Nome, Email, Telefone)
- Status do Pedido
- Valor Total
- Data de Criação
- Conversa Relacionada (se houver)

## Status dos Pedidos Válidos
Apenas pedidos com os seguintes status contam para conversão:
- ✅ `processing` - Em Processamento
- ✅ `completed` - Completo
- ✅ `producao` - Em Produção
- ✅ `designer` - Designer
- ✅ `pedido-enviado` - Pedido Enviado
- ✅ `pedido-entregue` - Pedido Entregue

Status **ignorados** (não contam):
- ❌ `pending` - Pendente
- ❌ `on-hold` - Em Espera
- ❌ `cancelled` - Cancelado
- ❌ `refunded` - Reembolsado
- ❌ `failed` - Falhado

## Rotas

| Rota | Método | Permissão | Descrição |
|------|--------|-----------|-----------|
| `/agent-conversion` | GET | `conversations.view.all` | Dashboard geral (admin) |
| `/agent-conversion/agent?id={id}` | GET | Próprio ID OU admin | Conversões do agente |
| `/api/agent-conversion/metrics` | GET | `conversations.view.all` | API de métricas (admin) |
| `/api/agent-conversion/sync` | POST | `conversations.view.all` | Sincronizar pedidos (admin) |

## Testes Realizados

### ✅ Testes de Acesso
- [x] Agente consegue acessar `/agent-conversion/agent?id={seu_id}`
- [x] Agente **não** consegue acessar conversões de outros agentes
- [x] Admin consegue acessar conversões de qualquer agente
- [x] Link aparece no menu para todos os agentes
- [x] Link fica "active" quando na página de conversões

### ✅ Testes de Dados
- [x] Métricas calculadas corretamente (faturamento, pedidos, taxa)
- [x] Apenas pedidos com status válidos são contados
- [x] Conversas históricas (reatribuições) são contadas
- [x] Pedidos recentes exibidos com dados corretos
- [x] Links para WooCommerce funcionando

## Documentação Relacionada
- `SISTEMA_CONVERSAO_WOOCOMMERCE.md` - Sistema completo de conversão
- `WEBHOOK_WOOCOMMERCE.md` - Sincronização via webhook
- `SINCRONIZACAO_WOOCOMMERCE.md` - CRON de sincronização
- `HISTORICO_ATRIBUICAO_CONVERSAS.md` - Sistema de histórico

## Próximos Passos (Opcional)
- [ ] Adicionar gráficos de evolução de conversão
- [ ] Permitir comparação com período anterior
- [ ] Notificar agente quando atingir meta de conversão
- [ ] Exportar relatório em PDF/Excel
- [ ] Ranking pessoal (posição no time)

---

**Resumo:** Os agentes agora podem acompanhar suas próprias conversões WooCommerce diretamente pelo menu, sem depender de administradores. O sistema respeita as permissões e só permite que cada agente veja seus próprios dados, mantendo a segurança do sistema. 🎯
