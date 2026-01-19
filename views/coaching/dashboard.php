<?php
/**
 * View: Dashboard de Coaching - Visão Geral
 */

$layout = 'layouts.metronic.app';
$title = $title ?? 'Dashboard de Coaching';

use App\Helpers\Url;

// Content
ob_start();

$acceptanceRate = $dashboard['acceptance_rate'] ?? [];
$roi = $dashboard['roi'] ?? [];
$conversionImpact = $dashboard['conversion_impact'] ?? [];
$hintQuality = $dashboard['hint_quality'] ?? [];
$suggestionUsage = $dashboard['suggestion_usage'] ?? [];
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

<!-- Conversas Analisadas com Métricas Detalhadas -->
<?php if (!empty($analyzedConversations['conversations'])): ?>
<div class="card card-flush mb-8">
    <div class="card-header">
        <h3 class="card-title">📋 Conversas Analisadas - Métricas de Coaching</h3>
        <div class="card-toolbar">
            <span class="badge badge-light-primary fs-7">
                <?= $analyzedConversations['total'] ?> conversas no período
            </span>
        </div>
    </div>
    <div class="card-body">
        <div id="analyzedConversationsList">
            <?php foreach ($analyzedConversations['conversations'] as $conv): ?>
            <div class="border border-gray-300 rounded p-5 mb-4 analyzed-conversation-item">
                <!-- Cabeçalho da Conversa -->
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div class="d-flex align-items-center flex-grow-1">
                        <div class="symbol symbol-45px me-4">
                            <?php if ($conv['agent_avatar']): ?>
                            <img src="<?= htmlspecialchars($conv['agent_avatar']) ?>" alt="Avatar" />
                            <?php else: ?>
                            <div class="symbol-label bg-light-primary text-primary fs-5 fw-bold">
                                <?= strtoupper(substr($conv['agent_name'], 0, 1)) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-1">
                                <a href="<?= Url::to('/conversations/' . $conv['id']) ?>" class="text-gray-900 fw-bold fs-5 text-hover-primary me-2" target="_blank">
                                    #<?= $conv['id'] ?> - <?= htmlspecialchars($conv['contact_name'] ?? 'Sem nome') ?>
                                </a>
                                <span class="badge badge-light-<?= $conv['status_badge']['class'] ?> fs-8">
                                    <?= $conv['status_badge']['text'] ?>
                                </span>
                            </div>
                            <div class="text-gray-600 fs-7">
                                <i class="ki-duotone ki-user fs-6"><span class="path1"></span><span class="path2"></span></i>
                                <?= htmlspecialchars($conv['agent_name']) ?>
                                <span class="mx-2">•</span>
                                <i class="ki-duotone ki-calendar fs-6"><span class="path1"></span><span class="path2"></span></i>
                                <?= date('d/m/Y H:i', strtotime($conv['created_at'])) ?>
                                <span class="mx-2">•</span>
                                <?= ucfirst($conv['channel']) ?>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-light-primary" onclick="showConversationDetails(<?= $conv['id'] ?>)">
                        <i class="ki-duotone ki-eye fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        Ver Detalhes
                    </button>
                </div>
                
                <!-- Métricas em Cards -->
                <div class="row g-3 mb-4">
                    <!-- Score Geral -->
                    <?php if ($conv['overall_score']): ?>
                    <div class="col-md-2">
                        <div class="bg-light-primary rounded p-3 text-center">
                            <div class="fs-2x fw-bold text-primary"><?= $conv['overall_score_formatted'] ?></div>
                            <div class="fs-8 text-gray-600">Score Geral</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Hints -->
                    <?php if ($conv['total_hints']): ?>
                    <div class="col-md-2">
                        <div class="bg-light-info rounded p-3 text-center">
                            <div class="fs-2x fw-bold text-info"><?= $conv['total_hints'] ?></div>
                            <div class="fs-8 text-gray-600">Hints Dados</div>
                            <div class="fs-9 text-success">✓ <?= $conv['hints_helpful'] ?? 0 ?> úteis</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Resultado -->
                    <?php if ($conv['conversation_outcome']): ?>
                    <div class="col-md-2">
                        <div class="bg-light-<?= $conv['outcome_badge']['class'] ?> rounded p-3 text-center">
                            <div class="fs-5 fw-bold text-<?= $conv['outcome_badge']['class'] ?>">
                                <i class="ki-duotone ki-<?= $conv['outcome_badge']['icon'] ?> fs-2x"></i>
                            </div>
                            <div class="fs-8 text-gray-600"><?= $conv['outcome_badge']['text'] ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Valor de Venda -->
                    <?php if ($conv['sales_value'] > 0): ?>
                    <div class="col-md-2">
                        <div class="bg-light-success rounded p-3 text-center">
                            <div class="fs-5 fw-bold text-success">R$ <?= $conv['sales_value_formatted'] ?></div>
                            <div class="fs-8 text-gray-600">Valor Venda</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Melhoria -->
                    <?php if ($conv['performance_improvement_score']): ?>
                    <div class="col-md-2">
                        <div class="bg-light-warning rounded p-3 text-center">
                            <div class="fs-2x fw-bold text-warning"><?= $conv['performance_improvement_score_formatted'] ?></div>
                            <div class="fs-8 text-gray-600">Melhoria</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Sugestões Usadas -->
                    <?php if (isset($conv['suggestions_used'])): ?>
                    <div class="col-md-2">
                        <div class="bg-light-dark rounded p-3 text-center">
                            <div class="fs-2x fw-bold text-dark"><?= $conv['suggestions_used'] ?></div>
                            <div class="fs-8 text-gray-600">Sugestões Usadas</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- 10 Dimensões de Performance (se disponível) -->
                <?php if ($conv['overall_score']): ?>
                <div class="separator my-3"></div>
                <div class="fs-6 fw-bold text-gray-700 mb-3">📊 Dimensões de Performance</div>
                <div class="row g-2">
                    <?php
                    $dimensions = [
                        'proactivity_score' => '🎯 Proatividade',
                        'objection_handling_score' => '🛡️ Quebra de Objeções',
                        'rapport_score' => '🤝 Rapport',
                        'closing_techniques_score' => '✅ Técnicas de Fechamento',
                        'qualification_score' => '🔍 Qualificação',
                        'clarity_score' => '💬 Clareza',
                        'value_proposition_score' => '💎 Proposta de Valor',
                        'response_time_score' => '⚡ Tempo de Resposta',
                        'follow_up_score' => '📅 Follow-up',
                        'professionalism_score' => '🎩 Profissionalismo'
                    ];
                    
                    foreach ($dimensions as $key => $label):
                        if (!empty($conv[$key])):
                            $score = (float)$conv[$key];
                            $scoreColor = $score >= 4 ? 'success' : ($score >= 3 ? 'primary' : ($score >= 2 ? 'warning' : 'danger'));
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                            <span class="fs-8 text-gray-700"><?= $label ?></span>
                            <span class="badge badge-light-<?= $scoreColor ?> fs-8"><?= number_format($score, 1) ?>/5</span>
                        </div>
                    </div>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>
                <?php endif; ?>
                
                <!-- Pontos Fortes e Fracos (resumido) -->
                <?php if (!empty($conv['strengths']) || !empty($conv['weaknesses'])): ?>
                <div class="separator my-3"></div>
                <div class="row g-3">
                    <?php if (!empty($conv['strengths'])): ?>
                    <div class="col-md-6">
                        <div class="fs-7 fw-bold text-success mb-2">✅ Pontos Fortes</div>
                        <ul class="fs-8 text-gray-600 mb-0">
                            <?php foreach (array_slice($conv['strengths'], 0, 3) as $strength): ?>
                            <li><?= htmlspecialchars($strength) ?></li>
                            <?php endforeach; ?>
                            <?php if (count($conv['strengths']) > 3): ?>
                            <li class="text-primary"><em>+ <?= count($conv['strengths']) - 3 ?> mais...</em></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($conv['weaknesses'])): ?>
                    <div class="col-md-6">
                        <div class="fs-7 fw-bold text-danger mb-2">⚠️ Pontos a Melhorar</div>
                        <ul class="fs-8 text-gray-600 mb-0">
                            <?php foreach (array_slice($conv['weaknesses'], 0, 3) as $weakness): ?>
                            <li><?= htmlspecialchars($weakness) ?></li>
                            <?php endforeach; ?>
                            <?php if (count($conv['weaknesses']) > 3): ?>
                            <li class="text-primary"><em>+ <?= count($conv['weaknesses']) - 3 ?> mais...</em></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Botão Carregar Mais -->
        <?php if ($analyzedConversations['has_more']): ?>
        <div class="text-center mt-5">
            <button 
                id="loadMoreConversations" 
                class="btn btn-light-primary" 
                data-page="2"
                data-period="<?= $selectedPeriod ?>"
                data-agent-id="<?= $selectedAgent ?? '' ?>">
                <i class="ki-duotone ki-arrow-down fs-3"><span class="path1"></span><span class="path2"></span></i>
                Carregar Mais Conversas
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="card card-flush mb-8">
    <div class="card-body text-center py-10">
        <i class="ki-duotone ki-information-5 fs-5x text-gray-400 mb-5">
            <span class="path1"></span>
            <span class="path2"></span>
            <span class="path3"></span>
        </i>
        <div class="fs-4 fw-bold text-gray-600">Nenhuma conversa analisada no período selecionado</div>
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

// Paginação de conversas analisadas
document.getElementById('loadMoreConversations')?.addEventListener('click', async function() {
    const btn = this;
    const page = parseInt(btn.dataset.page);
    const period = btn.dataset.period;
    const agentId = btn.dataset.agentId;
    
    // Loading state
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Carregando...';
    
    try {
        const url = new URL('<?= Url::to('/api/coaching/analyzed-conversations') ?>', window.location.origin);
        url.searchParams.append('page', page);
        url.searchParams.append('period', period);
        if (agentId) {
            url.searchParams.append('agent_id', agentId);
        }
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success && result.data.conversations) {
            // Renderizar novas conversas
            const conversationsHtml = renderConversations(result.data.conversations);
            document.getElementById('analyzedConversationsList').insertAdjacentHTML('beforeend', conversationsHtml);
            
            // Atualizar botão
            if (result.data.has_more) {
                btn.dataset.page = page + 1;
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            } else {
                btn.remove();
            }
        } else {
            throw new Error('Erro ao carregar conversas');
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao carregar mais conversas. Tente novamente.');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
});

// Função para renderizar conversas
function renderConversations(conversations) {
    return conversations.map(conv => {
        const dimensions = {
            'proactivity_score': '🎯 Proatividade',
            'objection_handling_score': '🛡️ Quebra de Objeções',
            'rapport_score': '🤝 Rapport',
            'closing_techniques_score': '✅ Técnicas de Fechamento',
            'qualification_score': '🔍 Qualificação',
            'clarity_score': '💬 Clareza',
            'value_proposition_score': '💎 Proposta de Valor',
            'response_time_score': '⚡ Tempo de Resposta',
            'follow_up_score': '📅 Follow-up',
            'professionalism_score': '🎩 Profissionalismo'
        };
        
        let dimensionsHtml = '';
        if (conv.overall_score) {
            dimensionsHtml = '<div class="separator my-3"></div><div class="fs-6 fw-bold text-gray-700 mb-3">📊 Dimensões de Performance</div><div class="row g-2">';
            for (const [key, label] of Object.entries(dimensions)) {
                if (conv[key]) {
                    const score = parseFloat(conv[key]);
                    const scoreColor = score >= 4 ? 'success' : (score >= 3 ? 'primary' : (score >= 2 ? 'warning' : 'danger'));
                    dimensionsHtml += `
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                <span class="fs-8 text-gray-700">${label}</span>
                                <span class="badge badge-light-${scoreColor} fs-8">${score.toFixed(1)}/5</span>
                            </div>
                        </div>`;
                }
            }
            dimensionsHtml += '</div>';
        }
        
        let strengthsHtml = '';
        let weaknessesHtml = '';
        if (conv.strengths?.length || conv.weaknesses?.length) {
            strengthsHtml = '<div class="separator my-3"></div><div class="row g-3">';
            
            if (conv.strengths?.length) {
                strengthsHtml += `
                    <div class="col-md-6">
                        <div class="fs-7 fw-bold text-success mb-2">✅ Pontos Fortes</div>
                        <ul class="fs-8 text-gray-600 mb-0">
                            ${conv.strengths.slice(0, 3).map(s => `<li>${s}</li>`).join('')}
                            ${conv.strengths.length > 3 ? `<li class="text-primary"><em>+ ${conv.strengths.length - 3} mais...</em></li>` : ''}
                        </ul>
                    </div>`;
            }
            
            if (conv.weaknesses?.length) {
                weaknessesHtml = `
                    <div class="col-md-6">
                        <div class="fs-7 fw-bold text-danger mb-2">⚠️ Pontos a Melhorar</div>
                        <ul class="fs-8 text-gray-600 mb-0">
                            ${conv.weaknesses.slice(0, 3).map(w => `<li>${w}</li>`).join('')}
                            ${conv.weaknesses.length > 3 ? `<li class="text-primary"><em>+ ${conv.weaknesses.length - 3} mais...</em></li>` : ''}
                        </ul>
                    </div>`;
            }
            
            strengthsHtml += weaknessesHtml + '</div>';
        }
        
        const createdDate = new Date(conv.created_at).toLocaleDateString('pt-BR') + ' ' + new Date(conv.created_at).toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
        
        return `
            <div class="border border-gray-300 rounded p-5 mb-4 analyzed-conversation-item">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div class="d-flex align-items-center flex-grow-1">
                        <div class="symbol symbol-45px me-4">
                            ${conv.agent_avatar ? 
                                `<img src="${conv.agent_avatar}" alt="Avatar" />` : 
                                `<div class="symbol-label bg-light-primary text-primary fs-5 fw-bold">${conv.agent_name.charAt(0).toUpperCase()}</div>`
                            }
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-1">
                                <a href="<?= Url::to('/conversations/') ?>${conv.id}" class="text-gray-900 fw-bold fs-5 text-hover-primary me-2" target="_blank">
                                    #${conv.id} - ${conv.contact_name || 'Sem nome'}
                                </a>
                                <span class="badge badge-light-${conv.status_badge.class} fs-8">${conv.status_badge.text}</span>
                            </div>
                            <div class="text-gray-600 fs-7">
                                <i class="ki-duotone ki-user fs-6"><span class="path1"></span><span class="path2"></span></i>
                                ${conv.agent_name}
                                <span class="mx-2">•</span>
                                <i class="ki-duotone ki-calendar fs-6"><span class="path1"></span><span class="path2"></span></i>
                                ${createdDate}
                                <span class="mx-2">•</span>
                                ${conv.channel.charAt(0).toUpperCase() + conv.channel.slice(1)}
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-light-primary" onclick="showConversationDetails(${conv.id})">
                        <i class="ki-duotone ki-eye fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        Ver Detalhes
                    </button>
                </div>
                
                <div class="row g-3 mb-4">
                    ${conv.overall_score ? `
                    <div class="col-md-2">
                        <div class="bg-light-primary rounded p-3 text-center">
                            <div class="fs-2x fw-bold text-primary">${conv.overall_score_formatted}</div>
                            <div class="fs-8 text-gray-600">Score Geral</div>
                        </div>
                    </div>` : ''}
                    
                    ${conv.total_hints ? `
                    <div class="col-md-2">
                        <div class="bg-light-info rounded p-3 text-center">
                            <div class="fs-2x fw-bold text-info">${conv.total_hints}</div>
                            <div class="fs-8 text-gray-600">Hints Dados</div>
                            <div class="fs-9 text-success">✓ ${conv.hints_helpful || 0} úteis</div>
                        </div>
                    </div>` : ''}
                    
                    ${conv.conversation_outcome ? `
                    <div class="col-md-2">
                        <div class="bg-light-${conv.outcome_badge.class} rounded p-3 text-center">
                            <div class="fs-5 fw-bold text-${conv.outcome_badge.class}">
                                <i class="ki-duotone ki-${conv.outcome_badge.icon} fs-2x"></i>
                            </div>
                            <div class="fs-8 text-gray-600">${conv.outcome_badge.text}</div>
                        </div>
                    </div>` : ''}
                    
                    ${conv.sales_value > 0 ? `
                    <div class="col-md-2">
                        <div class="bg-light-success rounded p-3 text-center">
                            <div class="fs-5 fw-bold text-success">R$ ${conv.sales_value_formatted}</div>
                            <div class="fs-8 text-gray-600">Valor Venda</div>
                        </div>
                    </div>` : ''}
                    
                    ${conv.performance_improvement_score ? `
                    <div class="col-md-2">
                        <div class="bg-light-warning rounded p-3 text-center">
                            <div class="fs-2x fw-bold text-warning">${conv.performance_improvement_score_formatted}</div>
                            <div class="fs-8 text-gray-600">Melhoria</div>
                        </div>
                    </div>` : ''}
                    
                    ${conv.suggestions_used !== undefined ? `
                    <div class="col-md-2">
                        <div class="bg-light-dark rounded p-3 text-center">
                            <div class="fs-2x fw-bold text-dark">${conv.suggestions_used}</div>
                            <div class="fs-8 text-gray-600">Sugestões Usadas</div>
                        </div>
                    </div>` : ''}
                </div>
                
                ${dimensionsHtml}
                ${strengthsHtml}
            </div>
        `;
    }).join('');
}

// Modal de detalhes da conversa
async function showConversationDetails(conversationId) {
    // Buscar detalhes completos via API (incluindo hints detalhados)
    try {
        const url = new URL('<?= Url::to('/api/coaching/analyzed-conversations') ?>', window.location.origin);
        url.searchParams.append('conversation_id', conversationId);
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success && result.data.conversations?.length > 0) {
            const conv = result.data.conversations[0];
            
            // Criar modal
            const modalHtml = `
                <div class="modal fade" id="conversationDetailsModal" tabindex="-1">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class="modal-title">
                                    Análise Detalhada - Conversa #${conv.id}
                                </h3>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-5">
                                    <h5 class="mb-3">📊 Resumo</h5>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="border rounded p-3">
                                                <div class="fs-7 text-gray-600">Contato</div>
                                                <div class="fw-bold">${conv.contact_name || 'Sem nome'}</div>
                                                ${conv.contact_phone ? `<div class="text-gray-600 fs-8">${conv.contact_phone}</div>` : ''}
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="border rounded p-3">
                                                <div class="fs-7 text-gray-600">Agente</div>
                                                <div class="fw-bold">${conv.agent_name}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="border rounded p-3">
                                                <div class="fs-7 text-gray-600">Status</div>
                                                <div><span class="badge badge-light-${conv.status_badge.class}">${conv.status_badge.text}</span></div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="border rounded p-3">
                                                <div class="fs-7 text-gray-600">Resultado</div>
                                                <div><span class="badge badge-light-${conv.outcome_badge?.class || 'light'}">${conv.outcome_badge?.text || 'N/A'}</span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                ${conv.coaching_hints?.length ? `
                                <div class="mb-5">
                                    <h5 class="mb-3">💡 Hints de Coaching (${conv.coaching_hints.length})</h5>
                                    <div class="accordion" id="hintsAccordion">
                                        ${conv.coaching_hints.map((hint, index) => `
                                            <div class="accordion-item">
                                                <h2 class="accordion-header">
                                                    <button class="accordion-button ${index > 0 ? 'collapsed' : ''}" type="button" data-bs-toggle="collapse" data-bs-target="#hint${hint.id}">
                                                        <span class="badge badge-light-${hint.feedback === 'helpful' ? 'success' : (hint.feedback === 'not_helpful' ? 'danger' : 'secondary')} me-2">
                                                            ${hint.feedback === 'helpful' ? '✓ Útil' : (hint.feedback === 'not_helpful' ? '✗ Não útil' : '⚪ Sem feedback')}
                                                        </span>
                                                        ${hint.hint_type.replace(/_/g, ' ').toUpperCase()} - ${new Date(hint.created_at).toLocaleTimeString('pt-BR')}
                                                    </button>
                                                </h2>
                                                <div id="hint${hint.id}" class="accordion-collapse collapse ${index === 0 ? 'show' : ''}" data-bs-parent="#hintsAccordion">
                                                    <div class="accordion-body">
                                                        <div class="mb-3">${hint.hint_text}</div>
                                                        ${hint.suggestions ? `
                                                            <div class="border-top pt-3">
                                                                <div class="fw-bold mb-2">Sugestões:</div>
                                                                <pre class="bg-light p-3 rounded">${JSON.stringify(JSON.parse(hint.suggestions), null, 2)}</pre>
                                                            </div>
                                                        ` : ''}
                                                    </div>
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                                ` : ''}
                                
                                ${conv.detailed_analysis ? `
                                <div class="mb-5">
                                    <h5 class="mb-3">📝 Análise Detalhada</h5>
                                    <div class="bg-light p-4 rounded">${conv.detailed_analysis}</div>
                                </div>
                                ` : ''}
                                
                                ${conv.improvement_suggestions ? `
                                <div class="mb-5">
                                    <h5 class="mb-3">💡 Sugestões de Melhoria</h5>
                                    <div class="bg-light p-4 rounded">
                                        <ul class="mb-0">
                                            ${JSON.parse(conv.improvement_suggestions).map(s => `<li>${s}</li>`).join('')}
                                        </ul>
                                    </div>
                                </div>
                                ` : ''}
                                
                                <div class="d-flex justify-content-end">
                                    <a href="<?= Url::to('/conversations/') ?>${conv.id}" target="_blank" class="btn btn-primary">
                                        Ver Conversa Completa
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remover modal existente se houver
            const existingModal = document.getElementById('conversationDetailsModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Adicionar e mostrar modal
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('conversationDetailsModal'));
            modal.show();
            
            // Remover modal ao fechar
            document.getElementById('conversationDetailsModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }
    } catch (error) {
        console.error('Erro ao carregar detalhes:', error);
        alert('Erro ao carregar detalhes da conversa.');
    }
}
</script>

<?php
// Fim do conteúdo
$content = ob_get_clean();

// Incluir layout
include __DIR__ . '/../' . str_replace('.', '/', $layout) . '.php';
?>
