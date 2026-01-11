<?php
/**
 * View: Dashboard de Coaching - Visão Geral
 */

use App\Helpers\Url;

$acceptanceRate = $dashboard['acceptance_rate'];
$roi = $dashboard['roi'];
$conversionImpact = $dashboard['conversion_impact'];
$hintQuality = $dashboard['hint_quality'];
$suggestionUsage = $dashboard['suggestion_usage'];
?>

<!-- Cabeçalho -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6">
    <div>
        <h1 class="fs-2x fw-bold text-gray-900 mb-2">
            ⚡ Dashboard de Coaching em Tempo Real
        </h1>
        <p class="text-gray-600 fs-5">
            Métricas e insights do sistema de coaching com IA
        </p>
    </div>
    
    <div class="d-flex gap-2 mt-4 mt-md-0">
        <!-- Filtro de Período -->
        <select id="periodFilter" class="form-select form-select-sm" style="width: 150px;">
            <option value="today" <?= $selectedPeriod === 'today' ? 'selected' : '' ?>>Hoje</option>
            <option value="week" <?= $selectedPeriod === 'week' ? 'selected' : '' ?>>Esta Semana</option>
            <option value="month" <?= $selectedPeriod === 'month' ? 'selected' : '' ?>>Este Mês</option>
        </select>
        
        <?php if ($canViewAll && !empty($agents)): ?>
        <!-- Filtro de Agente -->
        <select id="agentFilter" class="form-select form-select-sm" style="width: 200px;">
            <option value="">Todos os Agentes</option>
            <?php foreach ($agents as $agent): ?>
            <option value="<?= $agent['id'] ?>" <?= $selectedAgent == $agent['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($agent['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        
        <!-- Export CSV -->
        <a href="<?= Url::to('/coaching/export/csv') ?>?period=<?= $selectedPeriod ?><?= $selectedAgent ? '&agent_id='.$selectedAgent : '' ?>" 
           class="btn btn-sm btn-light-primary">
            <i class="ki-duotone ki-file-down fs-3">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            Export CSV
        </a>
    </div>
</div>

<!-- Cards de KPIs Principais -->
<div class="row g-5 g-xl-8 mb-8">
    <!-- Taxa de Aceitação -->
    <div class="col-xl-3 col-md-6">
        <div class="card card-flush h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center mb-5">
                    <div class="symbol symbol-50px me-3">
                        <span class="symbol-label bg-light-success">
                            <i class="ki-duotone ki-check-circle fs-2x text-success">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    </div>
                    <div>
                        <div class="fs-7 text-gray-600">Taxa de Aceitação</div>
                        <div class="fs-2x fw-bold text-gray-900"><?= $acceptanceRate['acceptance_rate'] ?>%</div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge badge-light-<?= $acceptanceRate['status'] === 'good' ? 'success' : ($acceptanceRate['status'] === 'warning' ? 'warning' : 'danger') ?>">
                            <?= $acceptanceRate['status'] === 'good' ? '✓ Bom' : ($acceptanceRate['status'] === 'warning' ? '⚠ Atenção' : '✗ Crítico') ?>
                        </span>
                    </div>
                    <div class="text-gray-600 fs-7">
                        Meta: <?= $acceptanceRate['target'] ?>%
                    </div>
                </div>
                
                <div class="separator my-3"></div>
                
                <div class="d-flex justify-content-between fs-7 text-gray-600">
                    <span>Total: <?= $acceptanceRate['total_hints'] ?> hints</span>
                    <span class="text-success">✓ <?= $acceptanceRate['helpful_hints'] ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ROI -->
    <div class="col-xl-3 col-md-6">
        <div class="card card-flush h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center mb-5">
                    <div class="symbol symbol-50px me-3">
                        <span class="symbol-label bg-light-primary">
                            <i class="ki-duotone ki-chart-line-up fs-2x text-primary">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    </div>
                    <div>
                        <div class="fs-7 text-gray-600">ROI do Coaching</div>
                        <div class="fs-2x fw-bold text-gray-900"><?= number_format($roi['roi_percentage'], 0) ?>%</div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge badge-light-<?= $roi['status'] === 'excellent' ? 'success' : ($roi['status'] === 'good' ? 'primary' : 'warning') ?>">
                            <?= $roi['status'] === 'excellent' ? '🏆 Excelente' : ($roi['status'] === 'good' ? '✓ Bom' : '⚠ OK') ?>
                        </span>
                    </div>
                    <div class="text-gray-600 fs-7">
                        Meta: <?= $roi['target'] ?>%
                    </div>
                </div>
                
                <div class="separator my-3"></div>
                
                <div class="d-flex justify-content-between fs-7 text-gray-600">
                    <span>Custo: R$ <?= number_format($roi['total_cost'], 2, ',', '.') ?></span>
                    <span class="text-success">R$ <?= number_format($roi['total_return'], 0, ',', '.') ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Impacto na Conversão -->
    <div class="col-xl-3 col-md-6">
        <div class="card card-flush h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center mb-5">
                    <div class="symbol symbol-50px me-3">
                        <span class="symbol-label bg-light-warning">
                            <i class="ki-duotone ki-arrow-up fs-2x text-warning">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    </div>
                    <div>
                        <div class="fs-7 text-gray-600">Melhoria na Conversão</div>
                        <div class="fs-2x fw-bold text-gray-900">+<?= $conversionImpact['improvement_percentage'] ?>%</div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge badge-light-<?= $conversionImpact['status'] === 'excellent' ? 'success' : ($conversionImpact['status'] === 'good' ? 'primary' : 'warning') ?>">
                            <?= $conversionImpact['status'] === 'excellent' ? '🎯 Excelente' : ($conversionImpact['status'] === 'good' ? '✓ Bom' : '⚠ OK') ?>
                        </span>
                    </div>
                    <div class="text-gray-600 fs-7">
                        Meta: +<?= $conversionImpact['target'] ?>%
                    </div>
                </div>
                
                <div class="separator my-3"></div>
                
                <div class="d-flex justify-content-between fs-7 text-gray-600">
                    <span>Com: <?= $conversionImpact['with_coaching']['conversion_rate'] ?>%</span>
                    <span>Sem: <?= $conversionImpact['without_coaching']['conversion_rate'] ?>%</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Uso de Sugestões -->
    <div class="col-xl-3 col-md-6">
        <div class="card card-flush h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center mb-5">
                    <div class="symbol symbol-50px me-3">
                        <span class="symbol-label bg-light-info">
                            <i class="ki-duotone ki-mouse-circle fs-2x text-info">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    </div>
                    <div>
                        <div class="fs-7 text-gray-600">Uso de Sugestões</div>
                        <div class="fs-2x fw-bold text-gray-900"><?= $suggestionUsage['usage_rate'] ?>%</div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge badge-light-<?= $suggestionUsage['status'] === 'excellent' ? 'success' : ($suggestionUsage['status'] === 'good' ? 'primary' : 'warning') ?>">
                            <?= $suggestionUsage['status'] === 'excellent' ? '💡 Excelente' : ($suggestionUsage['status'] === 'good' ? '✓ Bom' : '⚠ OK') ?>
                        </span>
                    </div>
                    <div class="text-gray-600 fs-7">
                        Meta: <?= $suggestionUsage['target'] ?>%
                    </div>
                </div>
                
                <div class="separator my-3"></div>
                
                <div class="d-flex justify-content-between fs-7 text-gray-600">
                    <span>Sugestões: <?= $suggestionUsage['hints_with_suggestions'] ?></span>
                    <span class="text-success">✓ <?= $suggestionUsage['suggestions_used'] ?> usadas</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Estatísticas Globais e Ranking -->
<div class="row g-5 g-xl-8 mb-8">
    <!-- Estatísticas Globais -->
    <div class="col-xl-6">
        <div class="card card-flush h-100">
            <div class="card-header">
                <h3 class="card-title">📊 Estatísticas Globais</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($globalStats)): ?>
                <div class="row g-5">
                    <div class="col-6">
                        <div class="border border-gray-300 border-dashed rounded p-4">
                            <div class="fs-2x fw-bold text-gray-900"><?= $globalStats['total_agents'] ?? 0 ?></div>
                            <div class="fs-7 text-gray-600">Agentes Ativos</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border border-gray-300 border-dashed rounded p-4">
                            <div class="fs-2x fw-bold text-gray-900"><?= $globalStats['total_hints'] ?? 0 ?></div>
                            <div class="fs-7 text-gray-600">Total de Hints</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border border-gray-300 border-dashed rounded p-4">
                            <div class="fs-2x fw-bold text-success">R$ <?= number_format($globalStats['total_sales'] ?? 0, 0, ',', '.') ?></div>
                            <div class="fs-7 text-gray-600">Vendas Geradas</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border border-gray-300 border-dashed rounded p-4">
                            <div class="fs-2x fw-bold text-primary"><?= number_format($globalStats['avg_conversion_improvement'] ?? 0, 1) ?>%</div>
                            <div class="fs-7 text-gray-600">Melhoria Média</div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center text-gray-600 py-10">
                    Aguardando dados...
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Top 5 Agentes -->
    <?php if ($canViewAll && !empty($ranking)): ?>
    <div class="col-xl-6">
        <div class="card card-flush h-100">
            <div class="card-header">
                <h3 class="card-title">🏆 Top 5 Agentes</h3>
                <div class="card-toolbar">
                    <span class="badge badge-light-primary">Esta Semana</span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle gs-0 gy-3">
                        <thead>
                            <tr class="fw-bold text-gray-600 fs-7">
                                <th>#</th>
                                <th>Agente</th>
                                <th class="text-end">Taxa</th>
                                <th class="text-end">Hints</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($ranking, 0, 5) as $index => $agent): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-light-<?= $index === 0 ? 'warning' : ($index === 1 ? 'secondary' : 'light') ?>">
                                        <?= $index + 1 ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= Url::to('/coaching/agent/' . $agent['agent_id']) ?>" class="text-gray-900 fw-bold text-hover-primary">
                                        <?= htmlspecialchars($agent['agent_name']) ?>
                                    </a>
                                </td>
                                <td class="text-end">
                                    <span class="badge badge-light-success">
                                        <?= $agent['total_hints_received'] > 0 ? round(($agent['total_hints_helpful'] / $agent['total_hints_received']) * 100, 1) : 0 ?>%
                                    </span>
                                </td>
                                <td class="text-end text-gray-600">
                                    <?= $agent['total_hints_received'] ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Conversas com Maior Impacto -->
<?php if (!empty($topConversations)): ?>
<div class="card card-flush mb-8">
    <div class="card-header">
        <h3 class="card-title">💎 Conversas com Maior Impacto</h3>
        <div class="card-toolbar">
            <a href="<?= Url::to('/coaching/top-conversations') ?>" class="btn btn-sm btn-light-primary">
                Ver Todas
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle gs-0 gy-4">
                <thead>
                    <tr class="fw-bold text-gray-600 fs-7">
                        <th>Conversa</th>
                        <th>Agente</th>
                        <th class="text-center">Hints</th>
                        <th class="text-center">Resultado</th>
                        <th class="text-end">Valor</th>
                        <th class="text-end">Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($topConversations, 0, 10) as $conv): ?>
                    <tr>
                        <td>
                            <a href="<?= Url::to('/conversations/' . $conv['conversation_id']) ?>" class="text-gray-900 fw-bold text-hover-primary" target="_blank">
                                #<?= $conv['conversation_id'] ?> - <?= htmlspecialchars($conv['contact_name'] ?? 'Sem nome') ?>
                            </a>
                        </td>
                        <td>
                            <span class="text-gray-600"><?= htmlspecialchars($conv['agent_name']) ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-light-primary"><?= $conv['total_hints'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-light-<?= $conv['conversation_outcome'] === 'converted' ? 'success' : 'info' ?>">
                                <?= $conv['conversation_outcome'] === 'converted' ? '✓ Convertida' : ucfirst($conv['conversation_outcome']) ?>
                            </span>
                        </td>
                        <td class="text-end text-success fw-bold">
                            R$ <?= number_format($conv['sales_value'], 2, ',', '.') ?>
                        </td>
                        <td class="text-end">
                            <div class="rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-label <?= $i <= $conv['performance_improvement_score'] ? 'checked' : '' ?>">
                                    <i class="ki-duotone ki-star fs-6"></i>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Filtros
document.getElementById('periodFilter')?.addEventListener('change', function() {
    const period = this.value;
    const agentId = document.getElementById('agentFilter')?.value || '';
    window.location.href = `<?= Url::to('/coaching/dashboard') ?>?period=${period}${agentId ? '&agent_id=' + agentId : ''}`;
});

document.getElementById('agentFilter')?.addEventListener('change', function() {
    const agentId = this.value;
    const period = document.getElementById('periodFilter')?.value || 'week';
    window.location.href = `<?= Url::to('/coaching/dashboard') ?>?period=${period}${agentId ? '&agent_id=' + agentId : ''}`;
});
</script>
