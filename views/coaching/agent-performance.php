<?php
/**
 * View: Performance Detalhada de Agente - Coaching
 */

use App\Helpers\Url;

$acceptanceRate = $dashboard['acceptance_rate'];
$learningSpeed = $dashboard['learning_speed'];
?>

<!-- Cabeçalho -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6">
    <div>
        <a href="<?= Url::to('/coaching/dashboard') ?>" class="btn btn-sm btn-light-primary mb-3">
            <i class="ki-duotone ki-arrow-left fs-3">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            Voltar ao Dashboard
        </a>
        <h1 class="fs-2x fw-bold text-gray-900 mb-2">
            👤 <?= htmlspecialchars($agent['name']) ?>
        </h1>
        <p class="text-gray-600 fs-5">
            Performance detalhada de coaching
        </p>
    </div>
    
    <div class="d-flex gap-2 mt-4 mt-md-0">
        <select id="periodFilter" class="form-select form-select-sm" style="width: 150px;">
            <option value="today" <?= $selectedPeriod === 'today' ? 'selected' : '' ?>>Hoje</option>
            <option value="week" <?= $selectedPeriod === 'week' ? 'selected' : '' ?>>Esta Semana</option>
            <option value="month" <?= $selectedPeriod === 'month' ? 'selected' : '' ?>>Este Mês</option>
        </select>
    </div>
</div>

<!-- KPIs do Agente -->
<div class="row g-5 g-xl-8 mb-8">
    <!-- Taxa de Aceitação -->
    <div class="col-xl-3 col-md-6">
        <div class="card card-flush h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="symbol symbol-40px me-3">
                        <span class="symbol-label bg-light-success">
                            <i class="ki-duotone ki-check-circle fs-2 text-success">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    </div>
                    <div>
                        <div class="fs-7 text-gray-600">Taxa de Aceitação</div>
                        <div class="fs-2 fw-bold text-gray-900"><?= $acceptanceRate['acceptance_rate'] ?>%</div>
                    </div>
                </div>
                <div class="progress h-6px">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $acceptanceRate['acceptance_rate'] ?>%" aria-valuenow="<?= $acceptanceRate['acceptance_rate'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="fs-7 text-gray-600 mt-2">
                    <?= $acceptanceRate['helpful_hints'] ?> de <?= $acceptanceRate['total_hints'] ?> hints úteis
                </div>
            </div>
        </div>
    </div>
    
    <!-- Velocidade de Aprendizado -->
    <div class="col-xl-3 col-md-6">
        <div class="card card-flush h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="symbol symbol-40px me-3">
                        <span class="symbol-label bg-light-primary">
                            <i class="ki-duotone ki-rocket fs-2 text-primary">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    </div>
                    <div>
                        <div class="fs-7 text-gray-600">Velocidade de Aprendizado</div>
                        <div class="fs-2 fw-bold text-gray-900"><?= $learningSpeed['learning_speed'] ?>%</div>
                    </div>
                </div>
                <div class="progress h-6px">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= min($learningSpeed['learning_speed'], 100) ?>%" aria-valuenow="<?= $learningSpeed['learning_speed'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="fs-7 text-gray-600 mt-2">
                    <?= $learningSpeed['status'] === 'excellent' ? '🚀 Excelente' : ($learningSpeed['status'] === 'good' ? '✓ Bom' : '⚠ Atenção') ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Hints Recebidos -->
    <div class="col-xl-3 col-md-6">
        <div class="card card-flush h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="symbol symbol-40px me-3">
                        <span class="symbol-label bg-light-warning">
                            <i class="ki-duotone ki-notification-bing fs-2 text-warning">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </span>
                    </div>
                    <div>
                        <div class="fs-7 text-gray-600">Hints Recebidos</div>
                        <div class="fs-2 fw-bold text-gray-900"><?= $acceptanceRate['total_hints'] ?></div>
                    </div>
                </div>
                <div class="fs-7 text-gray-600">
                    <?= $acceptanceRate['helpful_hints'] ?> úteis · <?= $acceptanceRate['total_hints'] - $acceptanceRate['helpful_hints'] ?> ignorados
                </div>
            </div>
        </div>
    </div>
    
    <!-- Impacto em Vendas -->
    <div class="col-xl-3 col-md-6">
        <div class="card card-flush h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="symbol symbol-40px me-3">
                        <span class="symbol-label bg-light-success">
                            <i class="ki-duotone ki-dollar fs-2 text-success">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </span>
                    </div>
                    <div>
                        <div class="fs-7 text-gray-600">Vendas Geradas</div>
                        <div class="fs-2 fw-bold text-gray-900">R$ <?= number_format($impactStats['total_sales'] ?? 0, 0, ',', '.') ?></div>
                    </div>
                </div>
                <div class="fs-7 text-gray-600">
                    <?= $impactStats['converted_conversations'] ?? 0 ?> conversões
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Gráfico de Evolução -->
<div class="card card-flush mb-8">
    <div class="card-header">
        <h3 class="card-title">📈 Evolução da Performance</h3>
        <div class="card-toolbar">
            <span class="badge badge-light-primary">Últimos 30 dias</span>
        </div>
    </div>
    <div class="card-body">
        <canvas id="performanceChart" height="80"></canvas>
    </div>
</div>

<!-- Conversas com Impacto -->
<div class="card card-flush">
    <div class="card-header">
        <h3 class="card-title">💬 Conversas com Coaching</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($conversations)): ?>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle gs-0 gy-4">
                <thead>
                    <tr class="fw-bold text-gray-600 fs-7">
                        <th>Conversa</th>
                        <th class="text-center">Hints</th>
                        <th class="text-center">Resultado</th>
                        <th class="text-end">Valor</th>
                        <th class="text-center">Feedback</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conversations as $conv): ?>
                    <tr>
                        <td>
                            <a href="<?= Url::to('/conversations/' . $conv['conversation_id']) ?>" class="text-gray-900 fw-bold text-hover-primary" target="_blank">
                                #<?= $conv['conversation_id'] ?> - <?= htmlspecialchars($conv['contact_name'] ?? 'Sem nome') ?>
                            </a>
                            <div class="text-gray-600 fs-7"><?= date('d/m/Y H:i', strtotime($conv['created_at'])) ?></div>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-light-primary"><?= $conv['total_hints'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-light-<?= $conv['conversation_outcome'] === 'converted' ? 'success' : ($conv['conversation_outcome'] === 'lost' ? 'danger' : 'warning') ?>">
                                <?= $conv['conversation_outcome'] === 'converted' ? '✓ Convertida' : ($conv['conversation_outcome'] === 'lost' ? '✗ Perdida' : ucfirst($conv['conversation_outcome'])) ?>
                            </span>
                        </td>
                        <td class="text-end text-success fw-bold">
                            <?= $conv['sales_value'] > 0 ? 'R$ ' . number_format($conv['sales_value'], 2, ',', '.') : '-' ?>
                        </td>
                        <td class="text-center">
                            <?php if ($conv['agent_feedback']): ?>
                            <span class="badge badge-light-<?= $conv['agent_feedback'] === 'very_helpful' ? 'success' : ($conv['agent_feedback'] === 'helpful' ? 'primary' : 'warning') ?>">
                                <?= $conv['agent_feedback'] === 'very_helpful' ? '🌟 Muito útil' : ($conv['agent_feedback'] === 'helpful' ? '👍 Útil' : '😐 Neutro') ?>
                            </span>
                            <?php else: ?>
                            <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center text-gray-600 py-10">
            Nenhuma conversa com coaching neste período
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Gráfico de Performance
const ctx = document.getElementById('performanceChart');

fetch(`<?= Url::to('/api/coaching/dashboard/history') ?>?agent_id=<?= $agent['id'] ?>&period=daily&limit=30`)
    .then(response => response.json())
    .then(result => {
        if (result.success && result.data) {
            new Chart(ctx, {
                type: 'line',
                data: result.data,
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    })
    .catch(error => console.error('Erro ao carregar gráfico:', error));

// Filtro de período
document.getElementById('periodFilter')?.addEventListener('change', function() {
    const period = this.value;
    window.location.href = `<?= Url::to('/coaching/agent/' . $agent['id']) ?>?period=${period}`;
});
</script>
