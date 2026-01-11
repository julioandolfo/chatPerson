<?php
/**
 * Página principal de Analytics com abas
 */
$layout = 'layouts.metronic.app';
$title = 'Analytics';

$filters = $filters ?? [];
$departments = $departments ?? [];
$agents = $agents ?? [];

// Content
ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Analytics</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        
        <!-- Filtros Globais -->
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="card-title">Filtros</h3>
            </div>
            <div class="card-body">
                <form id="analytics-filters-form" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Data Inicial</label>
                        <input type="date" name="start_date" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'))) ?>" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Data Final</label>
                        <input type="date" name="end_date" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($filters['end_date'] ?? date('Y-m-d')) ?>" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Setor</label>
                        <select name="department_id" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>" <?= ($filters['department_id'] ?? '') == $dept['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Agente</label>
                        <select name="agent_id" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <?php foreach ($agents as $agent): ?>
                                <option value="<?= $agent['id'] ?>" <?= ($filters['agent_id'] ?? '') == $agent['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($agent['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Canal</label>
                        <select name="channel" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <option value="whatsapp" <?= ($filters['channel'] ?? '') == 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
                            <option value="web" <?= ($filters['channel'] ?? '') == 'web' ? 'selected' : '' ?>>Web</option>
                            <option value="email" <?= ($filters['channel'] ?? '') == 'email' ? 'selected' : '' ?>>Email</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Funil</label>
                        <select name="funnel_id" class="form-select form-select-solid" id="filter-funnel">
                            <option value="">Todos</option>
                            <?php foreach ($funnels ?? [] as $funnel): ?>
                                <option value="<?= $funnel['id'] ?>" <?= ($filters['funnel_id'] ?? '') == $funnel['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($funnel['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ki-duotone ki-magnifier fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold">
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary ms-0 me-10 active" data-bs-toggle="tab" href="#kt_tab_conversations">
                    Conversas
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary me-10" data-bs-toggle="tab" href="#kt_tab_agents">
                    Agentes
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary me-10" data-bs-toggle="tab" href="#kt_tab_sentiment">
                    Sentimento
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary me-10" data-bs-toggle="tab" href="#kt_tab_sla">
                    SLA
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary me-10" data-bs-toggle="tab" href="#kt_tab_tags">
                    Tags
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary me-10" data-bs-toggle="tab" href="#kt_tab_funnel">
                    Funil
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary me-10" data-bs-toggle="tab" href="#kt_tab_automations">
                    Automações
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link text-active-primary me-10" data-bs-toggle="tab" href="#kt_tab_ai">
                    Inteligência Artificial
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="kt_tab_content">
            
            <!-- Tab: Conversas -->
            <div class="tab-pane fade show active" id="kt_tab_conversations" role="tabpanel">
                <div class="mt-5">
                    <!-- Comparação Temporal -->
                    <div class="card mb-5" id="time-comparison-card" style="display: none;">
                        <div class="card-header">
                            <h3 class="card-title">Comparação com Período Anterior</h3>
                        </div>
                        <div class="card-body">
                            <div class="row g-5" id="time-comparison-stats">
                                <!-- Será preenchido via JavaScript -->
                            </div>
                        </div>
                    </div>

                    <!-- Estatísticas Gerais -->
                    <div class="row g-5 mb-5" id="conversations-stats">
                        <div class="col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="text-gray-500 fw-semibold fs-6 mb-1">Total de Conversas</div>
                                            <div class="fs-2hx fw-bold text-gray-800" id="stat-total-conversations">-</div>
                                        </div>
                                        <div class="symbol symbol-50px">
                                            <div class="symbol-label bg-light-primary">
                                                <i class="ki-duotone ki-chat fs-2x text-primary">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="text-gray-500 fw-semibold fs-6 mb-1">Conversas Abertas</div>
                                            <div class="fs-2hx fw-bold text-warning" id="stat-open-conversations">-</div>
                                        </div>
                                        <div class="symbol symbol-50px">
                                            <div class="symbol-label bg-light-warning">
                                                <i class="ki-duotone ki-chat-text fs-2x text-warning">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="text-gray-500 fw-semibold fs-6 mb-1">Taxa de Resolução</div>
                                            <div class="fs-2hx fw-bold text-success" id="stat-resolution-rate">-</div>
                                        </div>
                                        <div class="symbol symbol-50px">
                                            <div class="symbol-label bg-light-success">
                                                <i class="ki-duotone ki-check-circle fs-2x text-success">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="text-gray-500 fw-semibold fs-6 mb-1">Total de Mensagens</div>
                                            <div class="fs-2hx fw-bold text-gray-800" id="stat-total-messages">-</div>
                                        </div>
                                        <div class="symbol symbol-50px">
                                            <div class="symbol-label bg-light-info">
                                                <i class="ki-duotone ki-message-text fs-2x text-info">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gráficos -->
                    <div class="row g-5 mb-5">
                        <div class="col-xl-8">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Evolução de Conversas</h3>
                                </div>
                                <div class="card-body">
                                    <div id="chart-conversations-evolution" style="height: 300px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Por Status</h3>
                                </div>
                                <div class="card-body">
                                    <div id="chart-conversations-status" style="height: 300px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-5 mb-5">
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Por Canal</h3>
                                </div>
                                <div class="card-body">
                                    <div id="chart-conversations-channel" style="height: 250px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Evolução de Mensagens</h3>
                                </div>
                                <div class="card-body">
                                    <div id="chart-messages-evolution" style="height: 250px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Agentes -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Top 10 Agentes</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th class="min-w-50px">#</th>
                                            <th class="min-w-150px">Agente</th>
                                            <th class="min-w-100px">Total</th>
                                            <th class="min-w-100px">Fechadas</th>
                                            <th class="min-w-100px">Taxa</th>
                                        </tr>
                                    </thead>
                                    <tbody id="top-agents-table">
                                        <tr>
                                            <td colspan="5" class="text-center py-5">
                                                <p class="text-muted">Carregando...</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Agentes -->
            <div class="tab-pane fade" id="kt_tab_agents" role="tabpanel">
                <div class="mt-5">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Ranking de Agentes</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th class="min-w-50px">#</th>
                                            <th class="min-w-150px">Agente</th>
                                            <th class="min-w-100px">Total</th>
                                            <th class="min-w-100px">Fechadas</th>
                                            <th class="min-w-100px">Taxa</th>
                                            <th class="min-w-100px">Tempo Médio</th>
                                        </tr>
                                    </thead>
                                    <tbody id="agents-ranking-table">
                                        <tr>
                                            <td colspan="6" class="text-center py-5">
                                                <p class="text-muted">Carregando...</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Sentimento -->
            <div class="tab-pane fade" id="kt_tab_sentiment" role="tabpanel">
                <div class="mt-5">
                    <div class="alert alert-info">
                        <i class="ki-duotone ki-information fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <span class="ms-2">Para análise detalhada de sentimento, acesse a página dedicada: 
                            <a href="<?= \App\Helpers\Url::to('/analytics/sentiment') ?>" class="fw-bold">Analytics de Sentimento</a>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tab: SLA -->
            <div class="tab-pane fade" id="kt_tab_sla" role="tabpanel">
                <div class="mt-5">
                    <!-- Estatísticas SLA -->
                    <div class="row g-5 mb-5" id="sla-stats">
                        <div class="col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="text-gray-500 fw-semibold fs-6 mb-1">SLA 5min</div>
                                            <div class="fs-2hx fw-bold text-success" id="stat-sla-5min">-</div>
                                        </div>
                                        <div class="symbol symbol-50px">
                                            <div class="symbol-label bg-light-success">
                                                <i class="ki-duotone ki-time fs-2x text-success">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="text-gray-500 fw-semibold fs-6 mb-1">SLA 15min</div>
                                            <div class="fs-2hx fw-bold text-info" id="stat-sla-15min">-</div>
                                        </div>
                                        <div class="symbol symbol-50px">
                                            <div class="symbol-label bg-light-info">
                                                <i class="ki-duotone ki-time fs-2x text-info">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="text-gray-500 fw-semibold fs-6 mb-1">SLA 30min</div>
                                            <div class="fs-2hx fw-bold text-warning" id="stat-sla-30min">-</div>
                                        </div>
                                        <div class="symbol symbol-50px">
                                            <div class="symbol-label bg-light-warning">
                                                <i class="ki-duotone ki-time fs-2x text-warning">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="text-gray-500 fw-semibold fs-6 mb-1">Tempo Médio</div>
                                            <div class="fs-2hx fw-bold text-gray-800" id="stat-avg-response-time">-</div>
                                        </div>
                                        <div class="symbol symbol-50px">
                                            <div class="symbol-label bg-light-primary">
                                                <i class="ki-duotone ki-clock fs-2x text-primary">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gráfico de SLA -->
                    <div class="row g-5 mb-5">
                        <div class="col-xl-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Taxa de Cumprimento de SLA</h3>
                                </div>
                                <div class="card-body">
                                    <div id="chart-sla-compliance" style="height: 300px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Tags -->
            <div class="tab-pane fade" id="kt_tab_tags" role="tabpanel">
                <div class="mt-5">
                    <!-- Top Tags -->
                    <div class="card mb-5">
                        <div class="card-header">
                            <h3 class="card-title">Top 20 Tags Mais Utilizadas</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th class="min-w-50px">#</th>
                                            <th class="min-w-150px">Tag</th>
                                            <th class="min-w-100px">Uso</th>
                                            <th class="min-w-100px">Fechadas</th>
                                            <th class="min-w-100px">Tempo Médio</th>
                                        </tr>
                                    </thead>
                                    <tbody id="top-tags-table">
                                        <tr>
                                            <td colspan="5" class="text-center py-5">
                                                <p class="text-muted">Carregando...</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Gráfico de Tags -->
                    <div class="row g-5 mb-5">
                        <div class="col-xl-8">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Evolução de Tags</h3>
                                </div>
                                <div class="card-body">
                                    <div id="chart-tags-evolution" style="height: 300px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Tags por Status</h3>
                                </div>
                                <div class="card-body">
                                    <div id="chart-tags-status" style="height: 300px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Funil -->
            <div class="tab-pane fade" id="kt_tab_funnel" role="tabpanel">
                <div class="mt-5">
                    <!-- Estatísticas por Estágio -->
                    <div class="card mb-5">
                        <div class="card-header">
                            <h3 class="card-title">Conversas por Estágio do Funil</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th class="min-w-50px">#</th>
                                            <th class="min-w-150px">Estágio</th>
                                            <th class="min-w-100px">Conversas</th>
                                            <th class="min-w-100px">Fechadas</th>
                                            <th class="min-w-100px">Tempo Médio</th>
                                        </tr>
                                    </thead>
                                    <tbody id="funnel-stages-table">
                                        <tr>
                                            <td colspan="5" class="text-center py-5">
                                                <p class="text-muted">Carregando...</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Gráfico de Funil -->
                    <div class="row g-5 mb-5">
                        <div class="col-xl-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Distribuição de Conversas por Estágio</h3>
                                </div>
                                <div class="card-body">
                                    <div id="chart-funnel-stages" style="height: 400px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Inteligência Artificial -->
            <div class="tab-pane fade" id="kt_tab_ai" role="tabpanel">
                <div class="mt-5">
                    <!-- Cards Principais de IA -->
                    <div class="row g-5 mb-5">
                        <div class="col-xl-3">
                            <div class="card bg-light-primary h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-50px me-3">
                                            <div class="symbol-label bg-primary">
                                                <i class="ki-duotone ki-robot fs-2x text-white">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="text-gray-500 fw-semibold fs-7 mb-1">Conversas com IA</div>
                                            <div class="fs-2hx fw-bold text-gray-800" id="stat-ai-conversations">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3">
                            <div class="card bg-light-success h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-50px me-3">
                                            <div class="symbol-label bg-success">
                                                <i class="ki-duotone ki-heart fs-2x text-white">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="text-gray-500 fw-semibold fs-7 mb-1">Análises de Sentimento</div>
                                            <div class="fs-2hx fw-bold text-gray-800" id="stat-sentiment-analyses">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3">
                            <div class="card bg-light-info h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-50px me-3">
                                            <div class="symbol-label bg-info">
                                                <i class="ki-duotone ki-chart-line-up fs-2x text-white">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="text-gray-500 fw-semibold fs-7 mb-1">Análises de Performance</div>
                                            <div class="fs-2hx fw-bold text-gray-800" id="stat-performance-analyses">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3">
                            <div class="card bg-light-warning h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-50px me-3">
                                            <div class="symbol-label bg-warning">
                                                <i class="ki-duotone ki-dollar fs-2x text-white">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="text-gray-500 fw-semibold fs-7 mb-1">Custo Total de IA</div>
                                            <div class="fs-2hx fw-bold text-gray-800" id="stat-ai-total-cost">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Breakdown de Custos por Serviço -->
                    <div class="card mb-5">
                        <div class="card-header">
                            <h3 class="card-title">💰 Breakdown de Custos por Serviço de IA</h3>
                        </div>
                        <div class="card-body">
                            <div class="row g-5" id="ai-cost-breakdown">
                                <!-- Será preenchido via JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gráficos de IA -->
                    <div class="row g-5 mb-5">
                        <div class="col-xl-8">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Evolução de Uso de IA</h3>
                                </div>
                                <div class="card-body">
                                    <div id="chart-ai-evolution" style="height: 300px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Distribuição de Custos</h3>
                                </div>
                                <div class="card-body">
                                    <div id="chart-ai-cost-distribution" style="height: 300px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabelas Detalhadas -->
                    <div class="row g-5 mb-5">
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Top Agentes de IA</h3>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                            <thead>
                                                <tr class="fw-bold text-muted">
                                                    <th class="min-w-50px">#</th>
                                                    <th class="min-w-150px">Agente</th>
                                                    <th class="min-w-100px">Conversas</th>
                                                    <th class="min-w-100px">Tokens</th>
                                                    <th class="min-w-100px">Custo</th>
                                                </tr>
                                            </thead>
                                            <tbody id="ai-agents-ranking">
                                                <tr>
                                                    <td colspan="5" class="text-center py-5">
                                                        <p class="text-muted">Carregando...</p>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Coaching em Tempo Real</h3>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                            <thead>
                                                <tr class="fw-bold text-muted">
                                                    <th class="min-w-150px">Tipo de Dica</th>
                                                    <th class="min-w-100px">Quantidade</th>
                                                    <th class="min-w-100px">Tokens</th>
                                                    <th class="min-w-100px">Custo</th>
                                                </tr>
                                            </thead>
                                            <tbody id="coaching-hints-stats">
                                                <tr>
                                                    <td colspan="4" class="text-center py-5">
                                                        <p class="text-muted">Carregando...</p>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Análise de Performance de Vendedores -->
                    <div class="card mb-5">
                        <div class="card-header">
                            <h3 class="card-title">📊 Análise de Performance de Vendedores</h3>
                        </div>
                        <div class="card-body">
                            <div class="row g-5" id="performance-analysis-stats">
                                <!-- Será preenchido via JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<!--end::Card-->

<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
let conversationsEvolutionChart, conversationsStatusChart, conversationsChannelChart, messagesEvolutionChart;
let slaComplianceChart, tagsEvolutionChart, tagsStatusChart, funnelStagesChart;
let automationsEvolutionChart, aiUsageChart, aiCostModelChart;

document.addEventListener('DOMContentLoaded', function() {
    // Carregar dados iniciais
    loadConversationsData();
    
    // Formulário de filtros
    document.getElementById('analytics-filters-form').addEventListener('submit', function(e) {
        e.preventDefault();
        loadConversationsData();
        loadAgentsData();
        loadTagsData();
        loadFunnelData();
    });
    
    // Quando trocar de aba, carregar dados se necessário
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            const targetId = e.target.getAttribute('href');
            if (targetId === '#kt_tab_agents' && !window.agentsDataLoaded) {
                loadAgentsData();
                window.agentsDataLoaded = true;
            } else if (targetId === '#kt_tab_tags' && !window.tagsDataLoaded) {
                loadTagsData();
                window.tagsDataLoaded = true;
            } else if (targetId === '#kt_tab_funnel' && !window.funnelDataLoaded) {
                loadFunnelData();
                window.funnelDataLoaded = true;
            } else if (targetId === '#kt_tab_automations' && !window.automationsDataLoaded) {
                loadAutomationsData();
                window.automationsDataLoaded = true;
            } else if (targetId === '#kt_tab_ai' && !window.aiDataLoaded) {
                loadAIData();
                window.aiDataLoaded = true;
            }
        });
    });
    
    // Carregar comparação temporal
    loadTimeComparison();
});

function loadConversationsData() {
    const form = document.getElementById('analytics-filters-form');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    
    fetch(`<?= \App\Helpers\Url::to('/analytics/conversations/data') ?>?${params.toString()}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Erro ao carregar dados:', data.message);
            return;
        }
        
        updateConversationsStats(data.stats);
        updateConversationsEvolutionChart(data.evolution);
        updateConversationsStatusChart(data.by_status);
        updateConversationsChannelChart(data.by_channel);
        updateMessagesEvolutionChart(data.messages_evolution);
        updateSLAMetrics(data.sla_metrics);
        updateSLAComplianceChart(data.sla_metrics);
        updateTopAgentsTable(data.top_agents);
    })
    .catch(error => {
        console.error('Erro ao carregar analytics:', error);
    });
}

function loadTagsData() {
    const form = document.getElementById('analytics-filters-form');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    
    fetch(`<?= \App\Helpers\Url::to('/analytics/tags/data') ?>?${params.toString()}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Erro ao carregar dados de tags:', data.message);
            return;
        }
        
        updateTopTagsTable(data.top_tags);
        updateTagsEvolutionChart(data.tags_over_time);
        updateTagsStatusChart(data.tags_by_status);
    })
    .catch(error => {
        console.error('Erro ao carregar dados de tags:', error);
    });
}

function loadFunnelData() {
    const form = document.getElementById('analytics-filters-form');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    
    fetch(`<?= \App\Helpers\Url::to('/analytics/funnel/data') ?>?${params.toString()}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Erro ao carregar dados de funil:', data.message);
            return;
        }
        
        updateFunnelStagesTable(data.stages_data);
        updateFunnelStagesChart(data.stages_data);
    })
    .catch(error => {
        console.error('Erro ao carregar dados de funil:', error);
    });
}

function loadAgentsData() {
    const form = document.getElementById('analytics-filters-form');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    
    fetch(`<?= \App\Helpers\Url::to('/analytics/agents/data') ?>?${params.toString()}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Erro ao carregar dados de agentes:', data.message);
            return;
        }
        
        updateAgentsRankingTable(data.ranking);
    })
    .catch(error => {
        console.error('Erro ao carregar dados de agentes:', error);
    });
}

function updateConversationsStats(stats) {
    const convs = stats.conversations || {};
    document.getElementById('stat-total-conversations').textContent = convs.total || 0;
    document.getElementById('stat-open-conversations').textContent = convs.open || 0;
    document.getElementById('stat-resolution-rate').textContent = (stats.metrics?.resolution_rate || 0).toFixed(1) + '%';
    document.getElementById('stat-total-messages').textContent = stats.messages?.total || 0;
}

function updateSLAMetrics(sla) {
    document.getElementById('stat-sla-5min').textContent = (sla.sla_5min_rate || 0).toFixed(1) + '%';
    document.getElementById('stat-sla-15min').textContent = (sla.sla_15min_rate || 0).toFixed(1) + '%';
    document.getElementById('stat-sla-30min').textContent = (sla.sla_30min_rate || 0).toFixed(1) + '%';
    const avgTime = sla.avg_response_time || 0;
    document.getElementById('stat-avg-response-time').textContent = avgTime > 0 ? avgTime.toFixed(0) + ' min' : '-';
}

function updateConversationsEvolutionChart(evolution) {
    const dates = evolution.map(e => e.period);
    const total = evolution.map(e => parseInt(e.total || 0));
    const open = evolution.map(e => parseInt(e.open_count || 0));
    const closed = evolution.map(e => parseInt(e.closed_count || 0));
    
    const options = {
        series: [{
            name: 'Total',
            data: total
        }, {
            name: 'Abertas',
            data: open
        }, {
            name: 'Fechadas',
            data: closed
        }],
        chart: {
            height: 300,
            type: 'line',
            toolbar: { show: false }
        },
        stroke: {
            width: [3, 2, 2],
            curve: 'smooth'
        },
        xaxis: {
            categories: dates
        },
        colors: ['#00D9FF', '#FFC700', '#50CD89'],
        legend: {
            position: 'top'
        }
    };
    
    if (conversationsEvolutionChart) {
        conversationsEvolutionChart.updateOptions(options);
    } else {
        conversationsEvolutionChart = new ApexCharts(document.querySelector("#chart-conversations-evolution"), options);
        conversationsEvolutionChart.render();
    }
}

function updateConversationsStatusChart(byStatus) {
    const labels = byStatus.map(s => {
        const statusMap = {
            'open': 'Abertas',
            'pending': 'Pendentes',
            'closed': 'Fechadas',
            'resolved': 'Resolvidas'
        };
        return statusMap[s.status] || s.status;
    });
    const values = byStatus.map(s => parseInt(s.count || 0));
    const colors = ['#FFC700', '#FF9800', '#6C757D', '#50CD89'];
    
    const options = {
        series: values,
        chart: {
            type: 'donut',
            height: 300
        },
        labels: labels,
        colors: colors,
        legend: {
            position: 'bottom'
        }
    };
    
    if (conversationsStatusChart) {
        conversationsStatusChart.updateSeries(values);
        conversationsStatusChart.updateOptions({ labels: labels });
    } else {
        conversationsStatusChart = new ApexCharts(document.querySelector("#chart-conversations-status"), options);
        conversationsStatusChart.render();
    }
}

function updateConversationsChannelChart(byChannel) {
    const labels = byChannel.map(c => c.channel);
    const values = byChannel.map(c => parseInt(c.count || 0));
    
    const options = {
        series: values,
        chart: {
            type: 'bar',
            height: 250,
            toolbar: { show: false }
        },
        plotOptions: {
            bar: {
                horizontal: true
            }
        },
        xaxis: {
            categories: labels
        },
        colors: ['#00D9FF']
    };
    
    if (conversationsChannelChart) {
        conversationsChannelChart.updateSeries(values);
        conversationsChannelChart.updateOptions({ xaxis: { categories: labels } });
    } else {
        conversationsChannelChart = new ApexCharts(document.querySelector("#chart-conversations-channel"), options);
        conversationsChannelChart.render();
    }
}

function updateMessagesEvolutionChart(evolution) {
    const dates = evolution.map(e => e.period);
    const total = evolution.map(e => parseInt(e.total || 0));
    const agent = evolution.map(e => parseInt(e.agent_messages || 0));
    const contact = evolution.map(e => parseInt(e.contact_messages || 0));
    
    const options = {
        series: [{
            name: 'Total',
            data: total
        }, {
            name: 'Agentes',
            data: agent
        }, {
            name: 'Contatos',
            data: contact
        }],
        chart: {
            height: 250,
            type: 'line',
            toolbar: { show: false }
        },
        stroke: {
            width: [3, 2, 2],
            curve: 'smooth'
        },
        xaxis: {
            categories: dates
        },
        colors: ['#00D9FF', '#50CD89', '#F1416C'],
        legend: {
            position: 'top'
        }
    };
    
    if (messagesEvolutionChart) {
        messagesEvolutionChart.updateOptions(options);
    } else {
        messagesEvolutionChart = new ApexCharts(document.querySelector("#chart-messages-evolution"), options);
        messagesEvolutionChart.render();
    }
}

function updateTopAgentsTable(agents) {
    const tbody = document.getElementById('top-agents-table');
    
    if (!agents || agents.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5"><p class="text-muted">Nenhum dado disponível</p></td></tr>';
        return;
    }
    
    tbody.innerHTML = agents.slice(0, 10).map((agent, index) => {
        return `
            <tr>
                <td>${index + 1}</td>
                <td>${escapeHtml(agent.name || '-')}</td>
                <td>${agent.total_conversations || 0}</td>
                <td>${agent.closed_conversations || 0}</td>
                <td><span class="badge badge-light-success">${(agent.resolution_rate || 0).toFixed(1)}%</span></td>
            </tr>
        `;
    }).join('');
}

function updateAgentsRankingTable(agents) {
    const tbody = document.getElementById('agents-ranking-table');
    
    if (!agents || agents.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><p class="text-muted">Nenhum dado disponível</p></td></tr>';
        return;
    }
    
    tbody.innerHTML = agents.map((agent, index) => {
        const avgTime = agent.avg_resolution_time ? formatTime(agent.avg_resolution_time) : '-';
        return `
            <tr>
                <td>${index + 1}</td>
                <td>${escapeHtml(agent.name || '-')}</td>
                <td>${agent.total_conversations || 0}</td>
                <td>${agent.closed_conversations || 0}</td>
                <td><span class="badge badge-light-success">${(agent.resolution_rate || 0).toFixed(1)}%</span></td>
                <td>${avgTime}</td>
            </tr>
        `;
    }).join('');
}

function formatTime(minutes) {
    if (!minutes || minutes === 0) return '-';
    if (minutes < 60) return Math.round(minutes) + ' min';
    const hours = Math.floor(minutes / 60);
    const mins = Math.round(minutes % 60);
    if (hours < 24) return hours + 'h ' + mins + 'min';
    const days = Math.floor(hours / 24);
    const hrs = hours % 24;
    return days + 'd ' + hrs + 'h';
}

function updateSLAComplianceChart(sla) {
    const options = {
        series: [
            {
                name: '5min',
                data: [sla.sla_5min_rate || 0]
            },
            {
                name: '15min',
                data: [sla.sla_15min_rate || 0]
            },
            {
                name: '30min',
                data: [sla.sla_30min_rate || 0]
            }
        ],
        chart: {
            type: 'bar',
            height: 300,
            toolbar: { show: false }
        },
        plotOptions: {
            bar: {
                horizontal: true,
                dataLabels: {
                    position: 'top'
                }
            }
        },
        dataLabels: {
            enabled: true,
            formatter: function(val) {
                return val.toFixed(1) + '%';
            }
        },
        xaxis: {
            categories: ['Taxa de Cumprimento'],
            max: 100
        },
        colors: ['#50CD89', '#00D9FF', '#FFC700'],
        legend: {
            position: 'top'
        }
    };
    
    if (slaComplianceChart) {
        slaComplianceChart.updateOptions(options);
    } else {
        slaComplianceChart = new ApexCharts(document.querySelector("#chart-sla-compliance"), options);
        slaComplianceChart.render();
    }
}

function updateTopTagsTable(tags) {
    const tbody = document.getElementById('top-tags-table');
    
    if (!tags || tags.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5"><p class="text-muted">Nenhum dado disponível</p></td></tr>';
        return;
    }
    
    tbody.innerHTML = tags.map((tag, index) => {
        const avgHours = tag.avg_resolution_hours ? tag.avg_resolution_hours.toFixed(1) + 'h' : '-';
        const color = tag.color || '#009ef7';
        return `
            <tr>
                <td>${index + 1}</td>
                <td>
                    <span class="badge" style="background-color: ${color}">${escapeHtml(tag.name || '-')}</span>
                </td>
                <td>${tag.usage_count || 0}</td>
                <td>${tag.closed_count || 0}</td>
                <td>${avgHours}</td>
            </tr>
        `;
    }).join('');
}

function updateTagsEvolutionChart(tagsOverTime) {
    // Agrupar por data e tag
    const datesMap = {};
    tagsOverTime.forEach(item => {
        if (!datesMap[item.date]) {
            datesMap[item.date] = {};
        }
        datesMap[item.date][item.tag_name] = parseInt(item.count || 0);
    });
    
    const dates = Object.keys(datesMap).sort();
    const tagNames = [...new Set(tagsOverTime.map(t => t.tag_name))].slice(0, 5); // Top 5 tags
    
    const series = tagNames.map(tagName => ({
        name: tagName,
        data: dates.map(date => datesMap[date][tagName] || 0)
    }));
    
    const options = {
        series: series,
        chart: {
            height: 300,
            type: 'line',
            toolbar: { show: false }
        },
        stroke: {
            width: 2,
            curve: 'smooth'
        },
        xaxis: {
            categories: dates
        },
        colors: ['#00D9FF', '#50CD89', '#F1416C', '#FFC700', '#7239EA'],
        legend: {
            position: 'top'
        }
    };
    
    if (tagsEvolutionChart) {
        tagsEvolutionChart.updateOptions(options);
    } else {
        tagsEvolutionChart = new ApexCharts(document.querySelector("#chart-tags-evolution"), options);
        tagsEvolutionChart.render();
    }
}

function updateTagsStatusChart(tagsByStatus) {
    // Agrupar por tag e status
    const tagsMap = {};
    tagsByStatus.forEach(item => {
        if (!tagsMap[item.name]) {
            tagsMap[item.name] = {};
        }
        tagsMap[item.name][item.status] = parseInt(item.count || 0);
    });
    
    const tagNames = Object.keys(tagsMap).slice(0, 10);
    const statuses = ['open', 'pending', 'closed', 'resolved'];
    
    const series = statuses.map(status => ({
        name: status === 'open' ? 'Abertas' : 
              status === 'pending' ? 'Pendentes' :
              status === 'closed' ? 'Fechadas' : 'Resolvidas',
        data: tagNames.map(tag => tagsMap[tag][status] || 0)
    }));
    
    const options = {
        series: series,
        chart: {
            type: 'bar',
            height: 300,
            stacked: true,
            toolbar: { show: false }
        },
        xaxis: {
            categories: tagNames
        },
        colors: ['#FFC700', '#FF9800', '#6C757D', '#50CD89'],
        legend: {
            position: 'top'
        }
    };
    
    if (tagsStatusChart) {
        tagsStatusChart.updateOptions(options);
    } else {
        tagsStatusChart = new ApexCharts(document.querySelector("#chart-tags-status"), options);
        tagsStatusChart.render();
    }
}

function updateFunnelStagesTable(stages) {
    const tbody = document.getElementById('funnel-stages-table');
    
    if (!stages || stages.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5"><p class="text-muted">Nenhum dado disponível</p></td></tr>';
        return;
    }
    
    tbody.innerHTML = stages.map((stage, index) => {
        const avgHours = stage.avg_time_hours ? stage.avg_time_hours.toFixed(1) + 'h' : '-';
        const color = stage.color || '#009ef7';
        return `
            <tr>
                <td>${stage.position || index + 1}</td>
                <td>
                    <span class="badge" style="background-color: ${color}">${escapeHtml(stage.name || '-')}</span>
                </td>
                <td>${stage.conversations_count || 0}</td>
                <td>${stage.closed_count || 0}</td>
                <td>${avgHours}</td>
            </tr>
        `;
    }).join('');
}

function updateFunnelStagesChart(stages) {
    const labels = stages.map(s => s.name);
    const values = stages.map(s => parseInt(s.conversations_count || 0));
    const colors = stages.map(s => s.color || '#009ef7');
    
    const options = {
        series: values,
        chart: {
            type: 'bar',
            height: 400,
            toolbar: { show: false }
        },
        plotOptions: {
            bar: {
                horizontal: true,
                distributed: true
            }
        },
        xaxis: {
            categories: labels
        },
        colors: colors,
        dataLabels: {
            enabled: true
        },
        legend: {
            show: false
        }
    };
    
    if (funnelStagesChart) {
        funnelStagesChart.updateSeries(values);
        funnelStagesChart.updateOptions({ xaxis: { categories: labels }, colors: colors });
    } else {
        funnelStagesChart = new ApexCharts(document.querySelector("#chart-funnel-stages"), options);
        funnelStagesChart.render();
    }
}

function loadAutomationsData() {
    const form = document.getElementById('analytics-filters-form');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    
    fetch(`<?= \App\Helpers\Url::to('/analytics/automations/data') ?>?${params.toString()}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Erro ao carregar dados de automações:', data.message);
            return;
        }
        
        updateAutomationsStats(data.general_stats, data.success_rate);
        updateAutomationsEvolutionChart(data.evolution);
        updateTopAutomationsTable(data.top_automations);
    })
    .catch(error => {
        console.error('Erro ao carregar dados de automações:', error);
    });
}

function loadAIData() {
    const form = document.getElementById('analytics-filters-form');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    
    fetch(`<?= \App\Helpers\Url::to('/analytics/ai/data') ?>?${params.toString()}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Erro ao carregar dados de IA:', data.message);
            return;
        }
        
        console.log('Dados de IA:', data);
        
        // Atualizar cards principais
        updateAIMainStats(data.metrics);
        
        // Atualizar breakdown de custos
        updateAICostBreakdown(data.metrics?.breakdown);
        
        // Atualizar gráficos
        updateAIEvolutionChart(data.evolution);
        updateAICostDistributionChart(data.metrics?.breakdown);
        
        // Atualizar tabelas
        updateAIAgentsRankingTable(data.ai_agents);
        updateCoachingHintsTable(data.coaching_hints);
        updatePerformanceAnalysisStats(data.performance_stats);
    })
    .catch(error => {
        console.error('Erro ao carregar dados de IA:', error);
    });
}

function updateAIMainStats(metrics) {
    if (!metrics) return;
    
    document.getElementById('stat-ai-conversations').textContent = 
        (metrics.total_ai_conversations || 0).toLocaleString('pt-BR');
    
    document.getElementById('stat-sentiment-analyses').textContent = 
        (metrics.sentiment_analyses || 0).toLocaleString('pt-BR');
    
    document.getElementById('stat-performance-analyses').textContent = 
        (metrics.performance_analyses || 0).toLocaleString('pt-BR');
    
    document.getElementById('stat-ai-total-cost').textContent = 
        '$' + parseFloat(metrics.total_cost || 0).toFixed(4);
}

function updateAICostBreakdown(breakdown) {
    const container = document.getElementById('ai-cost-breakdown');
    if (!container || !breakdown) return;
    
    const services = [
        { key: 'ai_agents', name: 'Agentes de IA', icon: 'robot', color: 'primary', emoji: '🤖' },
        { key: 'sentiment_analysis', name: 'Análise de Sentimento', icon: 'heart', color: 'danger', emoji: '😊' },
        { key: 'performance_analysis', name: 'Análise de Performance', icon: 'chart-line-up', color: 'info', emoji: '📊' },
        { key: 'realtime_coaching', name: 'Coaching Tempo Real', icon: 'teacher', color: 'warning', emoji: '🎯' },
        { key: 'audio_transcription', name: 'Transcrição de Áudio', icon: 'microphone', color: 'success', emoji: '🎤' }
    ];
    
    let html = '';
    services.forEach(service => {
        const data = breakdown[service.key] || {};
        const cost = parseFloat(data.cost || 0);
        const count = parseInt(data.count || 0);
        const tokens = parseInt(data.tokens || 0);
        
        if (cost > 0 || count > 0) {
            html += `
                <div class="col-xl-4 col-md-6">
                    <div class="card bg-light-${service.color} h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="ki-duotone ki-${service.icon} fs-2x text-${service.color} me-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <div>
                                        <div class="fs-6 fw-bold text-gray-800">${service.emoji} ${service.name}</div>
                                        <div class="text-muted fs-8">${count.toLocaleString('pt-BR')} usos</div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fs-4 fw-bold text-${service.color}">$${cost.toFixed(4)}</div>
                                </div>
                            </div>
                            <div class="text-muted fs-8">
                                <i class="ki-duotone ki-data fs-6 text-${service.color}">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                ${tokens.toLocaleString('pt-BR')} tokens
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
    });
    
    container.innerHTML = html || '<p class="text-muted text-center">Nenhum dado disponível</p>';
}

function updateAIEvolutionChart(evolution) {
    const chartEl = document.querySelector("#chart-ai-evolution");
    if (!chartEl || !evolution) return;
    
    const dates = evolution.map(e => e.date);
    const conversations = evolution.map(e => parseInt(e.ai_conversations || 0));
    const sentiments = evolution.map(e => parseInt(e.sentiment_analyses || 0));
    const performance = evolution.map(e => parseInt(e.performance_analyses || 0));
    
    const options = {
        series: [{
            name: 'Conversas IA',
            data: conversations
        }, {
            name: 'Análises Sentimento',
            data: sentiments
        }, {
            name: 'Análises Performance',
            data: performance
        }],
        chart: {
            height: 300,
            type: 'line',
            toolbar: { show: false }
        },
        stroke: {
            width: [3, 3, 3],
            curve: 'smooth'
        },
        xaxis: {
            categories: dates
        },
        colors: ['#009EF7', '#F1416C', '#00D9FF'],
        legend: {
            position: 'top'
        }
    };
    
    if (window.aiEvolutionChart) {
        window.aiEvolutionChart.updateOptions(options);
    } else {
        window.aiEvolutionChart = new ApexCharts(chartEl, options);
        window.aiEvolutionChart.render();
    }
}

function updateAICostDistributionChart(breakdown) {
    const chartEl = document.querySelector("#chart-ai-cost-distribution");
    if (!chartEl || !breakdown) return;
    
    const services = [
        { key: 'ai_agents', name: 'Agentes IA' },
        { key: 'sentiment_analysis', name: 'Sentimento' },
        { key: 'performance_analysis', name: 'Performance' },
        { key: 'realtime_coaching', name: 'Coaching' },
        { key: 'audio_transcription', name: 'Transcrição' }
    ];
    
    const series = services.map(s => parseFloat(breakdown[s.key]?.cost || 0)).filter(v => v > 0);
    const labels = services.map(s => s.name).filter((_, i) => series[i] > 0);
    
    const options = {
        series: series,
        chart: {
            type: 'donut',
            height: 300
        },
        labels: labels,
        colors: ['#009EF7', '#F1416C', '#00D9FF', '#FFC700', '#50CD89'],
        legend: {
            position: 'bottom'
        }
    };
    
    if (window.aiCostDistChart) {
        window.aiCostDistChart.updateOptions(options);
    } else {
        window.aiCostDistChart = new ApexCharts(chartEl, options);
        window.aiCostDistChart.render();
    }
}

function updateAIAgentsRankingTable(agents) {
    const tbody = document.getElementById('ai-agents-ranking');
    if (!tbody) return;
    
    if (!agents || agents.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5"><p class="text-muted">Nenhum dado disponível</p></td></tr>';
        return;
    }
    
    tbody.innerHTML = agents.map((agent, index) => `
        <tr>
            <td>${index + 1}</td>
            <td>${escapeHtml(agent.name || '-')}</td>
            <td>${(agent.conversations || 0).toLocaleString('pt-BR')}</td>
            <td>${(agent.total_tokens || 0).toLocaleString('pt-BR')}</td>
            <td>$${parseFloat(agent.total_cost || 0).toFixed(4)}</td>
        </tr>
    `).join('');
}

function updateCoachingHintsTable(hints) {
    const tbody = document.getElementById('coaching-hints-stats');
    if (!tbody) return;
    
    if (!hints || hints.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-5"><p class="text-muted">Nenhum dado disponível</p></td></tr>';
        return;
    }
    
    tbody.innerHTML = hints.map(hint => `
        <tr>
            <td>${escapeHtml(hint.hint_type || '-')}</td>
            <td>${(hint.count || 0).toLocaleString('pt-BR')}</td>
            <td>${(hint.total_tokens || 0).toLocaleString('pt-BR')}</td>
            <td>$${parseFloat(hint.total_cost || 0).toFixed(4)}</td>
        </tr>
    `).join('');
}

function updatePerformanceAnalysisStats(stats) {
    const container = document.getElementById('performance-analysis-stats');
    if (!container || !stats) return;
    
    container.innerHTML = `
        <div class="col-md-3">
            <div class="d-flex flex-column">
                <span class="text-muted fs-7 mb-1">Total de Análises</span>
                <span class="fw-bold fs-2">${(stats.total || 0).toLocaleString('pt-BR')}</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="d-flex flex-column">
                <span class="text-muted fs-7 mb-1">Nota Média Geral</span>
                <span class="fw-bold fs-2 text-primary">${parseFloat(stats.avg_overall_score || 0).toFixed(2)}</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="d-flex flex-column">
                <span class="text-muted fs-7 mb-1">Custo Total</span>
                <span class="fw-bold fs-2 text-success">$${parseFloat(stats.total_cost || 0).toFixed(4)}</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="d-flex flex-column">
                <span class="text-muted fs-7 mb-1">Tokens Utilizados</span>
                <span class="fw-bold fs-2 text-info">${(stats.total_tokens || 0).toLocaleString('pt-BR')}</span>
            </div>
        </div>
    `;
}

function loadTimeComparison() {
    const form = document.getElementById('analytics-filters-form');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    
    fetch(`<?= \App\Helpers\Url::to('/analytics/comparison') ?>?${params.toString()}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Erro ao carregar comparação temporal:', data.message);
            return;
        }
        
        updateTimeComparison(data.comparison, data.current_period, data.previous_period);
    })
    .catch(error => {
        console.error('Erro ao carregar comparação temporal:', error);
    });
}

function updateAutomationsStats(stats, successRate) {
    const totalEl = document.getElementById('stat-automations-total');
    const successRateEl = document.getElementById('stat-automations-success-rate');
    const failedEl = document.getElementById('stat-automations-failed');
    const avgTimeEl = document.getElementById('stat-automations-avg-time');
    
    if (totalEl) totalEl.textContent = stats?.total_executions || 0;
    if (successRateEl) successRateEl.textContent = (successRate || 0).toFixed(1) + '%';
    if (failedEl) failedEl.textContent = stats?.failed || 0;
    const avgTime = stats?.avg_execution_time_seconds || 0;
    if (avgTimeEl) avgTimeEl.textContent = avgTime > 0 ? avgTime.toFixed(1) + 's' : '-';
}

function updateAutomationsEvolutionChart(evolution) {
    const chartEl = document.querySelector("#chart-automations-evolution");
    if (!chartEl) return;
    
    const dates = evolution.map(e => e.date);
    const total = evolution.map(e => parseInt(e.total || 0));
    const completed = evolution.map(e => parseInt(e.completed || 0));
    const failed = evolution.map(e => parseInt(e.failed || 0));
    
    const options = {
        series: [{
            name: 'Total',
            data: total
        }, {
            name: 'Sucesso',
            data: completed
        }, {
            name: 'Falhas',
            data: failed
        }],
        chart: {
            height: 300,
            type: 'line',
            toolbar: { show: false }
        },
        stroke: {
            width: [3, 2, 2],
            curve: 'smooth'
        },
        xaxis: {
            categories: dates
        },
        colors: ['#00D9FF', '#50CD89', '#F1416C'],
        legend: {
            position: 'top'
        }
    };
    
    if (automationsEvolutionChart) {
        automationsEvolutionChart.updateOptions(options);
    } else {
        automationsEvolutionChart = new ApexCharts(chartEl, options);
        automationsEvolutionChart.render();
    }
}

function updateTopAutomationsTable(automations) {
    const tbody = document.getElementById('top-automations-table');
    if (!tbody) return;
    
    if (!automations || automations.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><p class="text-muted">Nenhum dado disponível</p></td></tr>';
        return;
    }
    
    tbody.innerHTML = automations.map((auto, index) => {
        const successRate = auto.execution_count > 0 
            ? ((auto.completed_count / auto.execution_count) * 100).toFixed(1) 
            : '0.0';
        return `
            <tr>
                <td>${index + 1}</td>
                <td>${escapeHtml(auto.name || '-')}</td>
                <td>${auto.execution_count || 0}</td>
                <td>${auto.completed_count || 0}</td>
                <td>${auto.failed_count || 0}</td>
                <td><span class="badge badge-light-success">${successRate}%</span></td>
            </tr>
        `;
    }).join('');
}

function updateAIStats(stats) {
    const totalUsesEl = document.getElementById('stat-ai-total-uses');
    const totalCostEl = document.getElementById('stat-ai-total-cost');
    const totalTokensEl = document.getElementById('stat-ai-total-tokens');
    const successRateEl = document.getElementById('stat-ai-success-rate');
    
    if (totalUsesEl) totalUsesEl.textContent = stats.total_uses || 0;
    if (totalCostEl) totalCostEl.textContent = '$' + (parseFloat(stats.total_cost || 0).toFixed(2));
    if (totalTokensEl) totalTokensEl.textContent = (stats.total_tokens || 0).toLocaleString();
    if (successRateEl) successRateEl.textContent = (stats.success_rate || 0).toFixed(1) + '%';
}

function updateAIUsageChart(usageOverTime) {
    const chartEl = document.querySelector("#chart-ai-usage");
    if (!chartEl) return;
    
    const dates = usageOverTime.map(u => u.period);
    const uses = usageOverTime.map(u => parseInt(u.uses || 0));
    const tokens = usageOverTime.map(u => parseInt(u.tokens || 0));
    const cost = usageOverTime.map(u => parseFloat(u.cost || 0));
    
    const options = {
        series: [{
            name: 'Usos',
            type: 'column',
            data: uses
        }, {
            name: 'Custo (USD)',
            type: 'line',
            data: cost
        }],
        chart: {
            height: 300,
            type: 'line',
            toolbar: { show: false }
        },
        stroke: {
            width: [0, 3],
            curve: 'smooth'
        },
        xaxis: {
            categories: dates
        },
        yaxis: [{
            title: { text: 'Usos' }
        }, {
            opposite: true,
            title: { text: 'Custo (USD)' }
        }],
        colors: ['#00D9FF', '#FFC700'],
        legend: {
            position: 'top'
        }
    };
    
    if (aiUsageChart) {
        aiUsageChart.updateOptions(options);
    } else {
        aiUsageChart = new ApexCharts(chartEl, options);
        aiUsageChart.render();
    }
}

function updateAICostModelChart(costByModel) {
    const chartEl = document.querySelector("#chart-ai-cost-model");
    if (!chartEl) return;
    
    const labels = costByModel.map(c => c.model || 'N/A');
    const values = costByModel.map(c => parseFloat(c.total_cost || 0));
    
    const options = {
        series: values,
        chart: {
            type: 'donut',
            height: 300
        },
        labels: labels,
        colors: ['#00D9FF', '#50CD89', '#F1416C', '#FFC700', '#7239EA'],
        legend: {
            position: 'bottom'
        }
    };
    
    if (aiCostModelChart) {
        aiCostModelChart.updateSeries(values);
        aiCostModelChart.updateOptions({ labels: labels });
    } else {
        aiCostModelChart = new ApexCharts(chartEl, options);
        aiCostModelChart.render();
    }
}

function updateAIFeaturesTable(features) {
    const tbody = document.getElementById('ai-features-table');
    if (!tbody) return;
    
    if (!features || features.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><p class="text-muted">Nenhum dado disponível</p></td></tr>';
        return;
    }
    
    tbody.innerHTML = features.slice(0, 10).map((feature, index) => {
        const successRate = feature.total_uses > 0 
            ? ((feature.successful_uses / feature.total_uses) * 100).toFixed(1) 
            : '0.0';
        return `
            <tr>
                <td>${index + 1}</td>
                <td>${escapeHtml(feature.feature_key || '-')}</td>
                <td>${feature.total_uses || 0}</td>
                <td>${(feature.total_tokens || 0).toLocaleString()}</td>
                <td>$${parseFloat(feature.total_cost || 0).toFixed(2)}</td>
                <td><span class="badge badge-light-success">${successRate}%</span></td>
            </tr>
        `;
    }).join('');
}

function updateAIAgentsTable(agents) {
    const tbody = document.getElementById('ai-agents-table');
    if (!tbody) return;
    
    if (!agents || agents.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><p class="text-muted">Nenhum dado disponível</p></td></tr>';
        return;
    }
    
    tbody.innerHTML = agents.map((agent, index) => {
        return `
            <tr>
                <td>${index + 1}</td>
                <td>${escapeHtml(agent.name || '-')}</td>
                <td><span class="badge badge-light-info">${agent.model || '-'}</span></td>
                <td>${agent.conversations_count || 0}</td>
                <td>${(agent.total_tokens || 0).toLocaleString()}</td>
                <td>$${parseFloat(agent.total_cost || 0).toFixed(2)}</td>
            </tr>
        `;
    }).join('');
}

function updateTimeComparison(comparison, currentPeriod, previousPeriod) {
    const card = document.getElementById('time-comparison-card');
    const container = document.getElementById('time-comparison-stats');
    
    if (!comparison || Object.keys(comparison).length === 0) {
        card.style.display = 'none';
        return;
    }
    
    card.style.display = 'block';
    
    const metrics = [
        { key: 'conversations', label: 'Total de Conversas', icon: 'chat' },
        { key: 'open_conversations', label: 'Conversas Abertas', icon: 'chat-text' },
        { key: 'closed_conversations', label: 'Conversas Fechadas', icon: 'check-circle' },
        { key: 'resolution_rate', label: 'Taxa de Resolução', icon: 'chart-simple', suffix: '%' },
        { key: 'avg_response_time', label: 'Tempo Médio de Resposta', icon: 'time', suffix: ' min', inverted: true },
        { key: 'messages', label: 'Total de Mensagens', icon: 'message-text' }
    ];
    
    container.innerHTML = metrics.map(metric => {
        const data = comparison[metric.key] || {};
        const change = data.change || {};
        const changeClass = change.is_positive ? 'text-success' : 'text-danger';
        const changeIcon = change.is_positive ? 'arrow-up' : 'arrow-down';
        const changeSign = change.is_positive ? '+' : '';
        
        return `
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-50px me-3">
                                <div class="symbol-label bg-light-primary">
                                    <i class="ki-duotone ki-${metric.icon} fs-2x text-primary">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="text-gray-500 fw-semibold fs-6 mb-1">${metric.label}</div>
                                <div class="d-flex align-items-center">
                                    <div class="fs-2x fw-bold text-gray-800 me-2">
                                        ${data.current || 0}${metric.suffix || ''}
                                    </div>
                                    <div class="${changeClass}">
                                        <i class="ki-duotone ki-${changeIcon} fs-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        ${changeSign}${change.percentage || 0}%
                                    </div>
                                </div>
                                <div class="text-muted fs-7 mt-1">
                                    Período anterior: ${data.previous || 0}${metric.suffix || ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php 
$content = ob_get_clean(); 
include __DIR__ . '/../layouts/metronic/app.php';
?>

