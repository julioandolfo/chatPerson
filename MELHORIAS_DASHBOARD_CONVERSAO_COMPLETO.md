# Melhorias Dashboard - Conversão WooCommerce e Times

## 📋 Resumo das Implementações

Este documento descreve as melhorias implementadas no dashboard principal, incluindo métricas de conversão para times e funcionalidades de sincronização WooCommerce.

---

## ✅ Implementações Realizadas

### 1. Métricas de Conversão nos Times

#### **Objetivo**
Adicionar métricas de conversão WooCommerce na tabela de Performance dos Times no dashboard.

#### **Alterações**

**`app/Controllers/DashboardController.php`:**
- Adicionado método `getTeamConversionMetrics()` para calcular métricas de conversão por time
- Modificado a busca de métricas de times para incluir dados de conversão:
  - `conversion_rate_sales`: Taxa de conversão (conversas → vendas)
  - `total_revenue`: Faturamento total do time
  - `avg_ticket`: Ticket médio do time
  - `total_orders`: Total de pedidos/vendas

**Lógica de Cálculo:**
1. Busca todos os membros do time que são vendedores (têm `woocommerce_seller_id`)
2. Para cada vendedor, busca suas métricas de conversão
3. Soma total de pedidos e faturamento
4. Calcula ticket médio: `faturamento_total / total_pedidos`
5. Busca total de conversas do time no período
6. Calcula taxa de conversão: `(total_pedidos / total_conversas) * 100`

**`views/dashboard/index.php`:**
- Adicionadas 4 novas colunas na tabela de Performance dos Times:
  - **Vendas**: Badge verde com total de pedidos
  - **Taxa Conversão**: Percentual com barra de progresso colorida (verde ≥30%, amarelo ≥15%, vermelho <15%)
  - **Faturamento**: Valor formatado em moeda (verde)
  - **Ticket Médio**: Valor formatado em moeda

---

### 2. Rankings de Vendas

#### **Objetivo**
Criar 3 rankings diferentes de vendedores no dashboard.

#### **Alterações**

**`app/Controllers/DashboardController.php`:**
- Criados 3 arrays de ranking:
  - `$rankingByRevenue`: Top 5 vendedores por faturamento total
  - `$rankingByConversion`: Top 5 vendedores por taxa de conversão
  - `$rankingByTicket`: Top 5 vendedores por ticket médio

**`views/dashboard/index.php`:**
- Adicionada seção "Rankings de Vendas" com 3 cards lado a lado:

1. **Top Faturamento** 🎯
   - Ícone de dólar (verde)
   - Posição (#1 amarelo, #2 azul, #3+ roxo)
   - Nome do vendedor (link para detalhes)
   - Quantidade de vendas
   - Faturamento total em destaque

2. **Top Conversão** 📈
   - Ícone de gráfico crescente (azul)
   - Posição colorida
   - Nome do vendedor
   - Conversas → Vendas
   - Taxa de conversão em destaque

3. **Top Ticket Médio** 💰
   - Ícone de gráfico (amarelo)
   - Posição colorida
   - Nome do vendedor
   - Quantidade de vendas
   - Ticket médio em destaque

---

### 3. Botões de Configuração e Sincronização

#### **Objetivo**
Adicionar funcionalidades para configurar webhook e sincronizar pedidos manualmente.

#### **Alterações**

**`views/dashboard/index.php` - Seção Conversão WooCommerce:**

Adicionados 2 novos botões no card-toolbar:

1. **Botão "Sincronizar Agora"** (cinza claro)
   - Ícone: `ki-arrows-circle`
   - Abre modal de sincronização manual

2. **Botão "Configurar Webhook"** (cinza)
   - Ícone: `ki-setting-2`
   - Abre modal com URL do webhook

---

### 4. Modal: Configurar Webhook

#### **Funcionalidades**

**Conteúdo:**
- Alert informativo com instruções de onde configurar no WooCommerce
- Input somente leitura com a URL do webhook:
  - Formato: `https://seudominio.com/webhooks/woocommerce`
- Botão "Copiar" que copia a URL para área de transferência
- Tabela com configurações recomendadas:
  - Nome: Chat System - Pedidos
  - Status: Ativo
  - Tópico: Order created / Order updated
  - API Version: WP REST API Integration v3
- Alert de atenção sobre configurar webhooks para ambos eventos

**JavaScript:**
```javascript
function copyWebhookUrl()
```
- Copia URL usando `navigator.clipboard.writeText()`
- Exibe SweetAlert de sucesso

---

### 5. Modal: Sincronizar Pedidos

#### **Funcionalidades**

**Inputs:**
1. **Limite de Pedidos**
   - Tipo: number
   - Valor padrão: 100
   - Min: 1, Max: 500
   - Descrição: Quantidade máxima de pedidos a sincronizar

2. **Período (dias)**
   - Tipo: number
   - Valor padrão: 7
   - Min: 1, Max: 90
   - Descrição: Buscar pedidos dos últimos X dias

**Validações:**
- Limite entre 1 e 500
- Período entre 1 e 90 dias
- Exibe SweetAlert de aviso se inválido

**Processo de Sincronização:**
1. Desabilita botão e mostra spinner
2. Faz POST para `/api/woocommerce/sync-orders`
3. Envia JSON: `{ orders_limit, days_back }`
4. Aguarda resposta
5. Exibe resultado:
   - **Sucesso**: SweetAlert com estatísticas:
     - Integrações processadas
     - Pedidos processados
     - Novos contatos criados
   - **Erro**: SweetAlert com mensagem de erro

**JavaScript:**
```javascript
function syncWooCommerceOrders()
```

---

### 6. Endpoint API: Sincronização Manual

#### **Rota**
```php
Router::post('/api/woocommerce/sync-orders', [WooCommerceController::class, 'syncOrders'], ['Authentication', 'Permission:conversion.view']);
```

#### **Controller: `WooCommerceController::syncOrders()`**

**Parâmetros:**
- `orders_limit` (int): Limite de pedidos (1-500)
- `days_back` (int): Período em dias (1-90)

**Processo:**
1. **Validações** dos parâmetros
2. **Busca integrações ativas** (`WooCommerceIntegration::getActive()`)
3. **Para cada integração:**
   - Monta URL da API WooCommerce com filtros
   - Faz requisição cURL com autenticação
   - Parse da resposta JSON
   - **Para cada pedido:**
     - Extrai `seller_id` do `meta_data` usando `seller_meta_key`
     - Busca ou cria contato (email/telefone)
     - Cacheia pedido no banco local (`WooCommerceOrderCache::cacheOrder()`)
   - Atualiza `last_sync_at` da integração
   - Contabiliza estatísticas

4. **Resposta JSON:**
```json
{
  "success": true,
  "message": "Sincronização concluída",
  "integrations_processed": 2,
  "orders_processed": 45,
  "new_contacts": 3,
  "errors": []
}
```

**Tratamento de Erros:**
- HTTP code diferente de 200
- Resposta inválida da API
- Exceções durante processamento
- Lista de erros retornada no array `errors`

---

### 7. Correção de Espaçamento

#### **Problema**
Alguns cards do dashboard estavam colados uns nos outros.

#### **Solução**
- Padronizados todos os rows com classes: `g-5 g-xl-10 mb-5 mb-xl-10`
- Garante espaçamento consistente entre cards
- Responsivo (g-5 mobile, g-xl-10 desktop)

**Alterações:**
```html
<!-- Antes -->
<div class="row gy-5 g-xl-10">

<!-- Depois -->
<div class="row g-5 g-xl-10 mb-5 mb-xl-10">
```

---

## 🎯 Funcionalidades Adicionadas

### ✅ Performance dos Times - Métricas de Conversão
- [x] Coluna "Vendas"
- [x] Coluna "Taxa Conversão" (com barra de progresso)
- [x] Coluna "Faturamento"
- [x] Coluna "Ticket Médio"
- [x] Cálculo de métricas agregadas por time
- [x] Apenas para membros que são vendedores

### ✅ Rankings de Vendas
- [x] Ranking por Faturamento (Top 5)
- [x] Ranking por Taxa de Conversão (Top 5)
- [x] Ranking por Ticket Médio (Top 5)
- [x] Cards lado a lado (responsivo)
- [x] Posições com cores (#1 ouro, #2 prata, #3+ bronze)
- [x] Links para página de detalhes do agente

### ✅ Modal Webhook WooCommerce
- [x] URL do webhook exibida
- [x] Botão copiar com feedback
- [x] Instruções de configuração
- [x] Configurações recomendadas
- [x] Alert de segurança/importante

### ✅ Modal Sincronização Manual
- [x] Input limite de pedidos (1-500)
- [x] Input período em dias (1-90)
- [x] Validações client-side
- [x] Loading state no botão
- [x] Feedback de sucesso com estatísticas
- [x] Feedback de erro

### ✅ API Sincronização
- [x] Endpoint POST `/api/woocommerce/sync-orders`
- [x] Validações de parâmetros
- [x] Processamento de múltiplas integrações
- [x] Extração de seller_id do meta_data
- [x] Criação automática de contatos
- [x] Cache de pedidos
- [x] Atualização de last_sync
- [x] Retorno de estatísticas detalhadas
- [x] Tratamento de erros por integração

### ✅ Correções de Layout
- [x] Espaçamento consistente entre cards
- [x] Classes padronizadas g-5 g-xl-10 mb-5 mb-xl-10
- [x] Layout responsivo mantido

---

## 📊 Estrutura de Dados

### Métricas de Time (Conversão)
```php
[
    'team_id' => 1,
    'team_name' => 'Time Vendas',
    'team_color' => '#009ef7',
    'leader_name' => 'João Silva',
    'members_count' => 5,
    'total_conversations' => 150,
    'closed_conversations' => 120,
    'resolution_rate' => 80.0,
    'avg_first_response_time' => 300, // segundos
    // NOVOS CAMPOS:
    'conversion_rate_sales' => 25.5, // %
    'total_revenue' => 15000.00,
    'avg_ticket' => 500.00,
    'total_orders' => 30
]
```

### Resposta Sincronização
```json
{
  "success": true,
  "message": "Sincronização concluída",
  "integrations_processed": 2,
  "orders_processed": 45,
  "new_contacts": 3,
  "errors": [
    "Integração #5: HTTP 401 - Não autorizado"
  ]
}
```

---

## 🔧 Arquivos Modificados

1. **`app/Controllers/DashboardController.php`**
   - Adicionado método `getTeamConversionMetrics()`
   - Modificada lógica de busca de métricas de times
   - Criados 3 rankings de vendas
   - Adicionados ao array de resposta

2. **`app/Controllers/WooCommerceController.php`**
   - Adicionado método `syncOrders()`

3. **`routes/web.php`**
   - Adicionada rota `/api/woocommerce/sync-orders`

4. **`views/dashboard/index.php`**
   - Adicionadas 4 colunas na tabela de times
   - Adicionada seção de Rankings de Vendas (3 cards)
   - Adicionados 2 botões no card Conversão WooCommerce
   - Adicionado Modal "Configurar Webhook"
   - Adicionado Modal "Sincronizar Pedidos"
   - Adicionadas funções JavaScript:
     - `copyWebhookUrl()`
     - `syncWooCommerceOrders()`
   - Corrigido espaçamento entre cards

---

## 🚀 Como Usar

### Visualizar Métricas de Times
1. Acesse o dashboard principal
2. Role até a seção "Performance dos Times"
3. Visualize as novas colunas de conversão (se houver integrações WooCommerce ativas e vendedores cadastrados)

### Visualizar Rankings
1. Acesse o dashboard principal
2. Role até a seção "Rankings de Vendas" (abaixo da seção de Conversão WooCommerce)
3. Veja os 3 rankings lado a lado

### Configurar Webhook
1. No dashboard, seção "Conversão WooCommerce"
2. Clique em "Configurar Webhook"
3. Copie a URL do webhook
4. Acesse o WooCommerce → Configurações → Avançado → Webhooks
5. Crie 2 webhooks:
   - Event: Order created
   - Event: Order updated
6. Cole a URL em ambos

### Sincronizar Pedidos Manualmente
1. No dashboard, seção "Conversão WooCommerce"
2. Clique em "Sincronizar Agora"
3. Configure:
   - Limite de pedidos (ex: 100)
   - Período (ex: 7 dias)
4. Clique em "Sincronizar"
5. Aguarde o processamento
6. Visualize as estatísticas
7. Opcionalmente, recarregue o dashboard

---

## 🎨 Visual

### Cores dos Rankings
- **#1**: Badge amarelo (`badge-light-warning`)
- **#2**: Badge azul (`badge-light-info`)
- **#3+**: Badge roxo (`badge-light-primary`)

### Cores de Conversão
- **≥30%**: Verde (success)
- **≥15%**: Amarelo (warning)
- **<15%**: Vermelho (danger)

### Ícones Metronic 8
- Dólar: `ki-dollar`
- Gráfico crescente: `ki-chart-line-up`
- Gráfico: `ki-chart-simple`
- Sincronizar: `ki-arrows-circle`
- Configuração: `ki-setting-2`
- Copiar: `ki-copy`
- Info: `ki-information-5`

---

## 📝 Observações

1. **Permissões**: A sincronização manual requer permissão `conversion.view`
2. **Performance**: Sincronizações com muitos pedidos (ex: 500) podem levar alguns minutos
3. **Cache**: Os pedidos são cacheados localmente conforme `cache_ttl_minutes` da integração
4. **Contatos**: Novos contatos são criados automaticamente se não existirem
5. **Webhook**: É a forma recomendada para receber atualizações em tempo real
6. **CRON**: A sincronização automática via CRON continua funcionando (executa a cada hora)

---

## ✅ Testes Realizados

- [x] Exibição de métricas de conversão na tabela de times
- [x] Cálculo correto de agregações (faturamento, ticket médio, conversão)
- [x] Exibição dos 3 rankings de vendas
- [x] Ordenação correta de cada ranking
- [x] Modal de webhook abre e fecha corretamente
- [x] Botão copiar URL do webhook funciona
- [x] Modal de sincronização abre e fecha
- [x] Validações client-side funcionam
- [x] Endpoint de sincronização processa corretamente
- [x] Feedback de sucesso exibe estatísticas
- [x] Feedback de erro exibe mensagem
- [x] Espaçamento entre cards corrigido

---

### ✅ Sistema de Logs do Webhook
- [x] Método `log()` dedicado no `WebhookController`
- [x] Logs detalhados de cada etapa do processamento
- [x] Arquivo `logs/webhook.log` criado
- [x] Integração com `view-all-logs.php`
- [x] Botão de navegação rápida no visualizador
- [x] Destaque de cores (erro/sucesso/warning)
- [x] Registro de: event, source, order_id, seller_id, contact_id, action

---

## 🎯 Próximos Passos

- [ ] Adicionar gráfico de evolução de conversão ao longo do tempo
- [ ] Adicionar filtro por time no relatório completo de conversão
- [ ] Implementar validação de assinatura do webhook (segurança)
- [ ] Adicionar log de sincronizações manuais
- [ ] Criar página de histórico de sincronizações
- [ ] Rotação automática de logs de webhook

---

## 📚 Documentação Relacionada

- **`WEBHOOK_LOGS_IMPLEMENTADO.md`**: Detalhes completos do sistema de logs do webhook

---

**Data:** 11/01/2026  
**Status:** ✅ Completo e Testado
