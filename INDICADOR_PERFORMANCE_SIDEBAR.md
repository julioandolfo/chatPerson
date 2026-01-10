# Indicador de Performance na Sidebar - 2026-01-10

## ✅ Implementação Completa

Adicionado indicador visual de performance do agente diretamente na sidebar da conversa, similar ao indicador de sentimento.

---

## 📍 Localização

O indicador aparece na **sidebar direita** da conversa, na seção "Informações da Conversa", logo após o indicador de sentimento.

**Visível apenas para conversas fechadas** (`status = 'closed'`)

---

## 🎨 Visual do Indicador

```
┌─────────────────────────────────────┐
│ 📊 Performance:                     │
│                                     │
│ Nota Geral:          🌟 4.75/5.00 │
│ ████████████████████░░░░ 95%       │
│                                     │
│ ✓ Excelente proatividade           │
│ ⚠ Melhorar tempo de resposta       │
│                                     │
│ [👁️ Ver Análise Completa]          │
└─────────────────────────────────────┘
```

### Cores por Nota:
- **🌟 4.5 - 5.0**: Verde (`success`) - Excelente
- **😊 3.5 - 4.4**: Azul (`primary`) - Bom
- **😐 2.5 - 3.4**: Amarelo (`warning`) - Regular
- **😟 0.0 - 2.4**: Vermelho (`danger`) - Precisa Melhorar

---

## 🔧 Arquivos Modificados

### 1. **views/conversations/sidebar-conversation.php**
Adicionado HTML do indicador:

```html
<!-- Performance do Agente -->
<div class="sidebar-info-item" id="agent-performance-info" style="display: none;">
    <span class="sidebar-info-label">
        <i class="ki-duotone ki-chart-line-up fs-5 text-primary me-1">
            <span class="path1"></span>
            <span class="path2"></span>
        </i>
        Performance:
    </span>
    <div class="mt-2">
        <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="fs-7 text-muted">Nota Geral:</span>
            <span class="badge badge-lg" id="performance-overall-badge">-</span>
        </div>
        <div class="progress" style="height: 8px;">
            <div class="progress-bar" id="performance-progress" role="progressbar" style="width: 0%;"></div>
        </div>
        <div class="fs-8 text-muted mt-2" id="performance-details">
            Analisando...
        </div>
        <a href="#" id="performance-view-link" class="btn btn-sm btn-light-primary w-100 mt-2" style="display: none;">
            <i class="ki-duotone ki-eye fs-5">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            Ver Análise Completa
        </a>
    </div>
</div>
```

### 2. **views/conversations/index.php**
Adicionadas funções JavaScript:

#### `loadAgentPerformance(conversationId)`
- Faz requisição AJAX para `/conversations/{id}/performance`
- Atualiza badge com nota e emoji
- Atualiza barra de progresso
- Mostra top 1 ponto forte e top 1 ponto fraco
- Exibe botão para ver análise completa

#### Chamada automática:
```javascript
// Carregar performance do agente (apenas para conversas fechadas)
if (conversation.id && conversation.status === 'closed') {
    loadAgentPerformance(conversation.id);
}
```

### 3. **app/Controllers/ConversationController.php**
Adicionado método `getPerformance($id)`:

```php
public function getPerformance($id): void
{
    // Validações de permissão
    // Busca análise de performance
    $analysis = \App\Models\AgentPerformanceAnalysis::getByConversation($conversationId);
    
    Response::json([
        'success' => true,
        'analysis' => $analysis
    ]);
}
```

### 4. **routes/web.php**
Adicionada rota:

```php
Router::get('/conversations/{id}/performance', [ConversationController::class, 'getPerformance'], ['Authentication']);
```

---

## 🔐 Permissões

O indicador respeita as seguintes permissões:
- `conversations.view.own` - Ver próprias conversas
- `conversations.view.all` - Ver todas as conversas
- `agent_performance.view.own` - Ver própria performance
- `agent_performance.view.team` - Ver performance do time

---

## 📊 Dados Exibidos

### Badge de Nota:
- Emoji baseado na nota
- Nota formatada (ex: 4.75/5.00)
- Cor do badge

### Barra de Progresso:
- Porcentagem visual (nota/5 * 100)
- Cor baseada na nota

### Detalhes Rápidos:
- ✓ 1º ponto forte (verde)
- ⚠ 1º ponto fraco (amarelo)

### Botão de Ação:
- Link para `/agent-performance/conversation/{id}`
- Análise completa com todas as dimensões

---

## 🔄 Fluxo de Funcionamento

```
1. Usuário abre conversa fechada
   ↓
2. JavaScript detecta status = 'closed'
   ↓
3. Chama loadAgentPerformance(conversationId)
   ↓
4. AJAX GET /conversations/{id}/performance
   ↓
5. Controller verifica permissões
   ↓
6. Model busca análise no banco
   ↓
7. Retorna JSON com dados
   ↓
8. JavaScript atualiza UI:
   - Badge com nota e emoji
   - Barra de progresso
   - Top ponto forte/fraco
   - Link para análise completa
   ↓
9. Indicador fica visível
```

---

## 🎯 Quando o Indicador Aparece

### ✅ SIM - Mostra indicador:
- Conversa está fechada (`status = 'closed'`)
- Análise de performance existe no banco
- Usuário tem permissão para ver

### ❌ NÃO - Oculta indicador:
- Conversa ainda aberta ou resolvida
- Análise não foi feita ainda
- Usuário sem permissão
- Erro ao carregar dados

---

## 🧪 Como Testar

### 1. **Criar uma análise de teste**
```bash
php public/scripts/analyze-performance.php
```

### 2. **Abrir uma conversa fechada**
1. Acesse o sistema
2. Abra uma conversa com status "Fechada"
3. Olhe na sidebar direita
4. Deve aparecer a seção "📊 Performance"

### 3. **Verificar dados**
- Badge mostra nota correta?
- Barra de progresso proporcional?
- Pontos forte/fraco aparecem?
- Botão "Ver Análise Completa" funciona?

---

## 🎨 Exemplos Visuais

### Excelente (4.5+):
```
Nota Geral:          🌟 4.75/5.00
████████████████████░░░░ 95% (verde)

✓ Excelente rapport com cliente
⚠ Pode melhorar follow-up
```

### Bom (3.5-4.4):
```
Nota Geral:          😊 3.85/5.00
███████████████░░░░░ 77% (azul)

✓ Boa clareza na comunicação
⚠ Precisa ser mais proativo
```

### Regular (2.5-3.4):
```
Nota Geral:          😐 2.95/5.00
████████████░░░░░░░░ 59% (amarelo)

✓ Mantém profissionalismo
⚠ Dificuldade em quebrar objeções
```

### Precisa Melhorar (< 2.5):
```
Nota Geral:          😟 2.15/5.00
████████░░░░░░░░░░░░ 43% (vermelho)

✓ Responde rapidamente
⚠ Falta técnica de fechamento
```

---

## 📱 Responsividade

O indicador é responsivo e se adapta ao tamanho da sidebar:
- Desktop: Largura completa
- Tablet: Compacto
- Mobile: Empilhado verticalmente

---

## 🔗 Integração com Dashboard

O botão "Ver Análise Completa" leva para:
```
/agent-performance/conversation/{conversationId}
```

Onde o usuário vê:
- Todas as 10 dimensões avaliadas
- Pontos fortes completos
- Pontos fracos completos
- Sugestões de melhoria
- Histórico de evolução

---

## ⚡ Performance

- **Carregamento:** Assíncrono (não bloqueia UI)
- **Cache:** Dados vêm do banco (já processados)
- **Fallback:** Se erro, indicador fica oculto
- **Timeout:** 5 segundos (configurável)

---

## 🐛 Troubleshooting

### Indicador não aparece:
1. Verificar se conversa está fechada
2. Verificar se análise existe no banco:
   ```sql
   SELECT * FROM agent_performance_analysis WHERE conversation_id = {id};
   ```
3. Verificar permissões do usuário
4. Verificar console do navegador (F12)
5. Verificar logs do PHP

### Dados incorretos:
1. Verificar formato JSON da resposta
2. Verificar se `strengths` e `weaknesses` são JSON válidos
3. Reprocessar análise se necessário

---

## 🎉 Benefícios

✅ **Visibilidade Imediata:** Agentes veem performance sem sair da conversa  
✅ **Feedback Rápido:** Identifica pontos fortes/fracos na hora  
✅ **Gamificação:** Emoji e cores motivam melhoria  
✅ **Ação Rápida:** Link direto para análise completa  
✅ **Não Intrusivo:** Só aparece quando relevante (conversa fechada)

---

## 📋 Próximas Melhorias (Opcional)

- [ ] Mostrar evolução vs conversa anterior
- [ ] Adicionar tooltip com todas as dimensões
- [ ] Badge de "Nova Análise" quando recém-processada
- [ ] Comparação com média do time
- [ ] Gráfico mini radar inline
- [ ] Notificação quando análise for concluída

---

Agora os agentes podem ver sua performance diretamente na sidebar da conversa! 🚀
