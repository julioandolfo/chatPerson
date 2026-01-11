# 🎨 Sprint 2: Dashboard Frontend - Instalação e Teste

## ✅ O que foi implementado

### 1. Controller
- **`app/Controllers/CoachingDashboardController.php`**
  - `index()` - Dashboard principal com visão geral
  - `agentPerformance()` - Performance detalhada de um agente
  - `topConversations()` - Conversas com maior impacto
  - `getDashboardData()` - API JSON para dados do dashboard
  - `getPerformanceHistory()` - API JSON para gráficos
  - `exportCSV()` - Export de métricas em CSV

### 2. Rotas
Adicionadas em `routes/web.php`:
```php
Router::get('/coaching/dashboard', [CoachingDashboardController::class, 'index']);
Router::get('/coaching/agent/{agentId}', [CoachingDashboardController::class, 'agentPerformance']);
Router::get('/coaching/top-conversations', [CoachingDashboardController::class, 'topConversations']);
Router::get('/api/coaching/dashboard/data', [CoachingDashboardController::class, 'getDashboardData']);
Router::get('/api/coaching/dashboard/history', [CoachingDashboardController::class, 'getPerformanceHistory']);
Router::get('/coaching/export/csv', [CoachingDashboardController::class, 'exportCSV']);
```

### 3. Views
- **`views/coaching/dashboard.php`** - Dashboard principal
  - 4 KPIs principais (Taxa de Aceitação, ROI, Impacto na Conversão, Uso de Sugestões)
  - Estatísticas globais
  - Top 5 agentes (para admins/supervisores)
  - Top 10 conversas com maior impacto
  - Filtros por período e agente
  - Export CSV

- **`views/coaching/agent-performance.php`** - Performance detalhada
  - 4 KPIs do agente
  - Gráfico de evolução (Chart.js)
  - Lista de conversas com coaching
  - Histórico de 30 dias

### 4. Menu Sidebar
- Adicionado item "Coaching IA" com ícone de foguete e badge verde pulsante
- Localizado após o menu "Performance"
- Visível apenas para usuários com permissão `coaching.view`

## 🚀 Como Testar

### 1. Acessar o Dashboard
```
https://seu-dominio.com.br/coaching/dashboard
```

### 2. Filtros Disponíveis
- **Período:**
  - Hoje
  - Esta Semana
  - Este Mês

- **Agente:** (apenas para admins/supervisores)
  - Todos os Agentes
  - Agente específico

### 3. Funcionalidades

#### Dashboard Principal
1. Visualizar 4 KPIs principais
2. Ver estatísticas globais (total de agentes, hints, vendas)
3. Ver ranking dos top 5 agentes
4. Ver top 10 conversas com maior impacto
5. Exportar dados em CSV

#### Performance por Agente
1. Clicar em um agente no ranking
2. Ver KPIs específicos do agente
3. Ver gráfico de evolução (últimos 30 dias)
4. Ver lista de conversas com coaching

#### Export CSV
1. Selecionar período e agente (opcional)
2. Clicar em "Export CSV"
3. Arquivo será baixado com todas as métricas

## 📊 Estrutura dos Dados

### KPIs Calculados
1. **Taxa de Aceitação**
   - Fórmula: `(hints_úteis / total_hints) * 100`
   - Meta: 70%
   - Status: good (≥70%), warning (50-69%), critical (<50%)

2. **ROI**
   - Fórmula: `((retorno - custo) / custo) * 100`
   - Meta: 300%
   - Status: excellent (≥500%), good (300-499%), ok (<300%)

3. **Impacto na Conversão**
   - Fórmula: `((taxa_com - taxa_sem) / taxa_sem) * 100`
   - Meta: 15%
   - Status: excellent (≥20%), good (15-19%), ok (<15%)

4. **Uso de Sugestões**
   - Fórmula: `(sugestões_usadas / hints_com_sugestões) * 100`
   - Meta: 40%
   - Status: excellent (≥50%), good (40-49%), ok (<40%)

5. **Velocidade de Aprendizado**
   - Fórmula: `((taxa_atual - taxa_inicial) / taxa_inicial) * 100`
   - Meta: 20%
   - Status: excellent (≥30%), good (20-29%), needs_improvement (<20%)

6. **Qualidade dos Hints**
   - Fórmula: `(hints_úteis / total_hints) * 100`
   - Meta: 75%
   - Status: excellent (≥80%), good (75-79%), needs_improvement (<75%)

## 🎨 Design

### Tema
- **Dark Mode** compatível
- **Metronic 8** design system
- **Cores:**
  - Sucesso: Verde (`badge-success`, `text-success`)
  - Primário: Azul (`badge-primary`, `text-primary`)
  - Aviso: Amarelo (`badge-warning`, `text-warning`)
  - Perigo: Vermelho (`badge-danger`, `text-danger`)

### Ícones
- Taxa de Aceitação: `ki-check-circle`
- ROI: `ki-chart-line-up`
- Conversão: `ki-arrow-up`
- Sugestões: `ki-mouse-circle`
- Velocidade: `ki-rocket`
- Vendas: `ki-dollar`

### Badges
- **Status:**
  - 🏆 Excelente (verde)
  - ✓ Bom (azul)
  - ⚠ Atenção (amarelo)
  - ✗ Crítico (vermelho)

## 🔒 Permissões

### Permissão Necessária
- `coaching.view` - Ver dashboard de coaching

### Níveis de Acesso
1. **Admin/Supervisor (roles 1, 2, 3):**
   - Ver todos os agentes
   - Filtrar por agente
   - Ver ranking global
   - Ver estatísticas globais

2. **Agente (roles ≥ 4):**
   - Ver apenas seus próprios dados
   - Sem filtro de agente
   - Sem ranking global

## 📈 Gráficos

### Chart.js
- **Versão:** 4.4.0
- **CDN:** `https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js`
- **Tipo:** Line chart
- **Dados:**
  - Taxa de Aceitação (%)
  - Hints Recebidos (quantidade)
- **Período:** Últimos 30 dias

### Exemplo de Dados
```json
{
  "labels": ["01/01", "02/01", "03/01", ...],
  "datasets": [
    {
      "label": "Taxa de Aceitação (%)",
      "data": [75, 80, 78, ...],
      "borderColor": "rgb(75, 192, 192)"
    },
    {
      "label": "Hints Recebidos",
      "data": [5, 8, 6, ...],
      "borderColor": "rgb(54, 162, 235)"
    }
  ]
}
```

## 🐛 Troubleshooting

### Dashboard não carrega
1. Verificar se as migrations do Sprint 1 foram executadas
2. Verificar se o cron de agregação está rodando
3. Verificar permissão `coaching.view`

### Gráfico não aparece
1. Abrir console do navegador (F12)
2. Verificar se há erros de rede
3. Verificar se a API `/api/coaching/dashboard/history` está respondendo
4. Verificar se há dados históricos (mínimo 2 dias)

### Export CSV vazio
1. Verificar se há dados no período selecionado
2. Verificar se o agente tem hints recebidos
3. Verificar se as tabelas de analytics estão populadas

### Menu não aparece
1. Verificar se a permissão `coaching.view` existe
2. Verificar se o usuário tem a permissão
3. Limpar cache de permissões: `php public/clear-permissions-cache.php`

## 📝 Próximos Passos

### Sprint 3: RAG Integration (Próximo)
1. Integrar com sistema RAG existente
2. Extrair conhecimento de hints bem-sucedidos
3. Buscar hints similares no histórico
4. Melhorar prompts com contexto do RAG
5. Dashboard de aprendizados

### Sprint 4: Analytics Avançado
1. A/B Testing de prompts
2. Análise de sentimento nos hints
3. Correlação entre tipos de hint e conversão
4. Previsão de sucesso de conversas
5. Recomendações personalizadas

## 🎯 Métricas de Sucesso

### Sprint 2 está completo quando:
- ✅ Dashboard principal carrega com KPIs
- ✅ Filtros funcionam (período e agente)
- ✅ Gráfico de evolução renderiza
- ✅ Export CSV funciona
- ✅ Menu aparece no sidebar
- ✅ Performance por agente funciona
- ✅ Permissões respeitadas

## 📚 Documentação Relacionada

- `PLANO_COMPLETO_COACHING_DASHBOARD_RAG.md` - Plano completo
- `INSTALACAO_COACHING_DASHBOARD_SPRINT1.md` - Sprint 1 (Infraestrutura)
- `TESTE_COACHING_INLINE.md` - Teste de hints inline
- `CORRECAO_COACHING_API.md` - Correção da API

---

**Status:** ✅ Sprint 2 Completo
**Data:** 11/01/2026
**Próximo:** Sprint 3 - RAG Integration
