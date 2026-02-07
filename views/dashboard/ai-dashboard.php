<?php
$layout = 'layouts.metronic.app';
$title = 'Dashboard de IA';

ob_start();
?>
<!--begin::Card - Filtros de Período-->
<div class="card mb-5">
    <div class="card-body">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <i class="ki-duotone ki-robot fs-2x text-primary">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <h3 class="fw-bold mb-0">Dashboard de Inteligência Artificial</h3>
            </div>
            <div class="d-flex align-items-center gap-3 ms-auto">
                <label class="fw-semibold fs-6 mb-0">Período:</label>
                <input type="date" id="kt_ai_dashboard_date_from" class="form-control form-control-solid" 
                       value="<?= htmlspecialchars($dateFrom) ?>" style="width: 150px;" />
                <span class="text-muted">até</span>
                <input type="date" id="kt_ai_dashboard_date_to" class="form-control form-control-solid" 
                       value="<?= htmlspecialchars(date('Y-m-d', strtotime($dateTo))) ?>" style="width: 150px;" />
                <button type="button" class="btn btn-sm btn-primary" onclick="loadAIDashboard()">
                    <i class="ki-duotone ki-magnifier fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Filtrar
                </button>
            </div>
        </div>
    </div>
</div>
<!--end::Card - Filtros de Período-->

<!--begin::Row - Cards Principais-->
<div class="row g-5 mb-5">
    <!--begin::Col - Conversas IA-->
    <div class="col-xl-3">
        <div class="card bg-light-primary h-100">
            <div class="card-body">
                <i class="ki-duotone ki-message-programming fs-2x text-primary mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($aiMetrics['total_ai_conversations'] ?? 0) ?></div>
                <div class="fw-semibold text-gray-500">Conversas com IA</div>
                <div class="text-muted fs-7 mt-1">Período selecionado</div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Mensagens IA-->
    <div class="col-xl-3">
        <div class="card bg-light-info h-100">
            <div class="card-body">
                <i class="ki-duotone ki-message-text-2 fs-2x text-info mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($aiMetrics['total_ai_messages'] ?? 0) ?></div>
                <div class="fw-semibold text-gray-500">Mensagens Enviadas</div>
                <div class="text-muted fs-7 mt-1">Respostas da IA</div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Taxa de Resolução IA-->
    <div class="col-xl-3">
        <div class="card bg-light-success h-100">
            <div class="card-body">
                <i class="ki-duotone ki-check-circle fs-2x text-success mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($aiMetrics['ai_resolution_rate'] ?? 0, 1) ?>%</div>
                <div class="fw-semibold text-gray-500">Taxa de Resolução</div>
                <div class="text-muted fs-7 mt-1">Resolvido sem escalonar</div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Taxa de Escalonamento-->
    <div class="col-xl-3">
        <div class="card bg-light-warning h-100">
            <div class="card-body">
                <i class="ki-duotone ki-arrow-up-right fs-2x text-warning mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($aiMetrics['ai_escalation_rate'] ?? 0, 1) ?>%</div>
                <div class="fw-semibold text-gray-500">Taxa de Escalonamento</div>
                <div class="text-muted fs-7 mt-1">Transferido para humano</div>
            </div>
        </div>
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->

<!--begin::Row - Breakdown de Custos de IA-->
<div class="row g-5 mb-5">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">
                    <i class="ki-duotone ki-dollar fs-2x text-success me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    💰 Breakdown de Custos de IA
                </h3>
                <div class="card-toolbar">
                    <div class="d-flex flex-column align-items-end">
                        <span class="badge badge-light-success fs-5 mb-1">
                            Total: $<?= number_format($aiMetrics['total_cost'] ?? 0, 4) ?>
                        </span>
                        <span class="text-muted fs-8">
                            <?= number_format($aiMetrics['total_tokens'] ?? 0) ?> tokens
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body pt-5">
                <div class="row g-5">
                    
                    <?php
                    $breakdown = $aiMetrics['breakdown'] ?? [];
                    $breakdownItems = [
                        'ai_agents' => [
                            'title' => 'Agentes de IA',
                            'icon' => 'ki-robot',
                            'color' => 'primary',
                            'emoji' => '🤖'
                        ],
                        'sentiment_analysis' => [
                            'title' => 'Análise de Sentimento',
                            'icon' => 'ki-heart',
                            'color' => 'danger',
                            'emoji' => '😊'
                        ],
                        'performance_analysis' => [
                            'title' => 'Análise de Performance',
                            'icon' => 'ki-chart-line-up',
                            'color' => 'info',
                            'emoji' => '📊'
                        ],
                        'realtime_coaching' => [
                            'title' => 'Coaching Tempo Real',
                            'icon' => 'ki-teacher',
                            'color' => 'warning',
                            'emoji' => '🎯'
                        ],
                        'audio_transcription' => [
                            'title' => 'Transcrição de Áudio',
                            'icon' => 'ki-microphone',
                            'color' => 'success',
                            'emoji' => '🎤'
                        ]
                    ];
                    
                    foreach ($breakdownItems as $key => $item):
                        $data = $breakdown[$key] ?? [];
                        $cost = $data['cost'] ?? 0;
                        $tokens = $data['tokens'] ?? 0;
                        $count = $data['count'] ?? 0;
                        
                        // Pular se custo for zero
                        if ($cost == 0 && $count == 0) continue;
                        
                        // Calcular percentual do custo total
                        $totalCost = $aiMetrics['total_cost'] ?? 0;
                        $percentage = $totalCost > 0 ? round(($cost / $totalCost) * 100, 1) : 0;
                    ?>
                    
                    <!-- Item -->
                    <div class="col-xl-4 col-md-6">
                        <div class="card bg-light-<?= $item['color'] ?> h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="ki-duotone <?= $item['icon'] ?> fs-2x text-<?= $item['color'] ?> me-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <div>
                                            <div class="fs-6 fw-bold text-gray-800"><?= $item['emoji'] ?> <?= $item['title'] ?></div>
                                            <div class="text-muted fs-8"><?= number_format($count) ?> <?= $count == 1 ? 'uso' : 'usos' ?></div>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fs-4 fw-bold text-<?= $item['color'] ?>">$<?= number_format($cost, 4) ?></div>
                                        <div class="badge badge-light-<?= $item['color'] ?> fs-9"><?= $percentage ?>%</div>
                                    </div>
                                </div>
                                
                                <!-- Barra de progresso -->
                                <div class="progress h-6px w-100 mb-2">
                                    <div class="progress-bar bg-<?= $item['color'] ?>" 
                                         style="width: <?= $percentage ?>%"></div>
                                </div>
                                
                                <!-- Tokens -->
                                <div class="text-muted fs-8">
                                    <i class="ki-duotone ki-data fs-6 text-<?= $item['color'] ?>">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <?= number_format($tokens) ?> tokens
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php endforeach; ?>
                    
                </div>
                
                <!-- Resumo consolidado -->
                <div class="separator my-5"></div>
                <div class="row g-5">
                    <div class="col-md-3">
                        <div class="d-flex flex-column">
                            <span class="text-muted fs-7 mb-1">Custo Total</span>
                            <span class="fw-bold text-success fs-3">$<?= number_format($aiMetrics['total_cost'] ?? 0, 4) ?></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex flex-column">
                            <span class="text-muted fs-7 mb-1">Tokens Totais</span>
                            <span class="fw-bold text-info fs-3"><?= number_format($aiMetrics['total_tokens'] ?? 0) ?></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex flex-column">
                            <span class="text-muted fs-7 mb-1">Custo Médio / Conversa</span>
                            <span class="fw-bold text-primary fs-3">
                                <?php
                                $totalConv = $aiMetrics['total_ai_conversations'] ?? 0;
                                $avgCost = $totalConv > 0 ? ($aiMetrics['total_cost'] ?? 0) / $totalConv : 0;
                                echo '$' . number_format($avgCost, 4);
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex flex-column">
                            <span class="text-muted fs-7 mb-1">Custo Médio / Token</span>
                            <span class="fw-bold text-warning fs-3">
                                <?php
                                $totalTokens = $aiMetrics['total_tokens'] ?? 0;
                                $avgTokenCost = $totalTokens > 0 ? ($aiMetrics['total_cost'] ?? 0) / $totalTokens : 0;
                                echo '$' . number_format($avgTokenCost, 6);
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Alerta de custo alto -->
                <?php if (($aiMetrics['total_cost'] ?? 0) > 10): ?>
                <div class="alert alert-warning d-flex align-items-center mt-5">
                    <i class="ki-duotone ki-information fs-2x text-warning me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div>
                        <h4 class="mb-1">⚠️ Custo Elevado Detectado</h4>
                        <p class="mb-0">
                            O custo total de IA no período está acima de $10.00. 
                            Considere revisar as configurações de uso e limites diários.
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!--end::Row-->

<!--begin::Row - Estatísticas do Assistente IA-->
<?php
$assistantGeneral = $assistantStats['general'] ?? [];
$hasAssistantData = !empty($assistantGeneral) && ($assistantGeneral['total_uses'] ?? 0) > 0;
?>

<?php if ($hasAssistantData): ?>
<div class="row g-5 mb-5">
    <div class="col-12">
        <div class="card bg-light-success">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">
                    <i class="ki-duotone ki-abstract-26 fs-2x text-success me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    🎯 Assistente IA - Copiloto do Agente
                </h3>
                <div class="card-toolbar">
                    <span class="badge badge-lg badge-light-success fs-5">
                        <?= number_format($assistantGeneral['total_uses'] ?? 0) ?> usos
                    </span>
                </div>
            </div>
            <div class="card-body pt-5">
                <!-- Cards de métricas principais -->
                <div class="row g-5 mb-7">
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-white h-100">
                            <div class="card-body d-flex flex-column justify-content-center">
                                <div class="text-center mb-3">
                                    <i class="ki-duotone ki-check-circle fs-3x text-success">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                                <div class="text-center">
                                    <div class="fs-2 fw-bold text-gray-900 mb-1">
                                        <?= number_format($assistantGeneral['success_rate'] ?? 0, 1) ?>%
                                    </div>
                                    <div class="fs-6 fw-semibold text-gray-500">Taxa de Sucesso</div>
                                    <div class="fs-8 text-muted mt-1">
                                        <?= number_format($assistantGeneral['successful_uses'] ?? 0) ?> sucessos / 
                                        <?= number_format($assistantGeneral['failed_uses'] ?? 0) ?> falhas
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-white h-100">
                            <div class="card-body d-flex flex-column justify-content-center">
                                <div class="text-center mb-3">
                                    <i class="ki-duotone ki-dollar fs-3x text-success">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </div>
                                <div class="text-center">
                                    <div class="fs-2 fw-bold text-gray-900 mb-1">
                                        $<?= number_format($assistantGeneral['total_cost'] ?? 0, 4) ?>
                                    </div>
                                    <div class="fs-6 fw-semibold text-gray-500">Custo Total</div>
                                    <div class="fs-8 text-muted mt-1">
                                        $<?= number_format($assistantGeneral['avg_cost_per_use'] ?? 0, 4) ?> por uso
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-white h-100">
                            <div class="card-body d-flex flex-column justify-content-center">
                                <div class="text-center mb-3">
                                    <i class="ki-duotone ki-data fs-3x text-primary">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </div>
                                <div class="text-center">
                                    <div class="fs-2 fw-bold text-gray-900 mb-1">
                                        <?= number_format($assistantGeneral['total_tokens'] ?? 0) ?>
                                    </div>
                                    <div class="fs-6 fw-semibold text-gray-500">Tokens Utilizados</div>
                                    <div class="fs-8 text-muted mt-1">
                                        <?= number_format(($assistantGeneral['total_tokens'] ?? 0) / max(1, $assistantGeneral['total_uses'] ?? 1), 0) ?> por uso
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-white h-100">
                            <div class="card-body d-flex flex-column justify-content-center">
                                <div class="text-center mb-3">
                                    <i class="ki-duotone ki-timer fs-3x text-warning">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </div>
                                <div class="text-center">
                                    <div class="fs-2 fw-bold text-gray-900 mb-1">
                                        <?= number_format($assistantGeneral['avg_execution_time'] ?? 0, 0) ?>ms
                                    </div>
                                    <div class="fs-6 fw-semibold text-gray-500">Tempo Médio</div>
                                    <div class="fs-8 text-muted mt-1">
                                        Por requisição
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Estatísticas por funcionalidade -->
                <div class="separator my-5"></div>
                <h4 class="fw-bold mb-5">📊 Uso por Funcionalidade</h4>
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th class="min-w-200px">Funcionalidade</th>
                                <th class="min-w-100px text-center">Usos</th>
                                <th class="min-w-100px text-center">Taxa Sucesso</th>
                                <th class="min-w-100px text-end">Tokens</th>
                                <th class="min-w-100px text-end">Custo</th>
                                <th class="min-w-100px text-end">Tempo Médio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $byFeature = $assistantStats['by_feature'] ?? [];
                            if (empty($byFeature)):
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        Nenhum dado disponível para o período selecionado
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($byFeature as $feature): 
                                    $totalUses = (int)($feature['uses'] ?? 0);
                                    $successful = (int)($feature['successful'] ?? 0);
                                    $successRate = $totalUses > 0 ? (($successful / $totalUses) * 100) : 0;
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-45px me-3">
                                                <div class="symbol-label bg-light-primary">
                                                    <i class="ki-duotone ki-abstract-26 fs-2x text-primary">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                </div>
                                            </div>
                                            <div class="d-flex flex-column">
                                                <span class="fw-bold text-gray-800"><?= htmlspecialchars($feature['feature_name'] ?? $feature['feature_key']) ?></span>
                                                <span class="text-muted fs-8"><?= htmlspecialchars($feature['feature_key']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-light-primary fs-6"><?= number_format($totalUses) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-<?= $successRate >= 95 ? 'success' : ($successRate >= 80 ? 'warning' : 'danger') ?>">
                                            <?= number_format($successRate, 1) ?>%
                                        </span>
                                    </td>
                                    <td class="text-end fw-semibold"><?= number_format($feature['tokens'] ?? 0) ?></td>
                                    <td class="text-end fw-semibold text-success">$<?= number_format($feature['cost'] ?? 0, 4) ?></td>
                                    <td class="text-end fw-semibold"><?= number_format($feature['avg_time'] ?? 0, 0) ?>ms</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Agentes especializados -->
                <?php if (!empty($assistantStats['by_agent'])): ?>
                <div class="separator my-5"></div>
                <h4 class="fw-bold mb-5">🤖 Agentes Especializados</h4>
                <div class="row g-5">
                    <?php foreach ($assistantStats['by_agent'] as $agent): ?>
                    <div class="col-xl-4 col-md-6">
                        <div class="card bg-light-primary h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="symbol symbol-50px me-3">
                                        <div class="symbol-label bg-white">
                                            <i class="ki-duotone ki-robot fs-2x text-primary">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-gray-900 fs-6"><?= htmlspecialchars($agent['name']) ?></div>
                                        <div class="text-muted fs-8"><?= htmlspecialchars($agent['model']) ?></div>
                                    </div>
                                </div>
                                <div class="separator my-3"></div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-gray-600 fs-7">Usos:</span>
                                    <span class="fw-bold"><?= number_format($agent['uses']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-gray-600 fs-7">Tokens:</span>
                                    <span class="fw-bold"><?= number_format($agent['tokens']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-gray-600 fs-7">Custo:</span>
                                    <span class="fw-bold text-success">$<?= number_format($agent['cost'], 4) ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-gray-600 fs-7">Tempo médio:</span>
                                    <span class="fw-bold"><?= number_format($agent['avg_time'], 0) ?>ms</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Top usuários -->
                <?php if (!empty($assistantStats['top_users'])): ?>
                <div class="separator my-5"></div>
                <h4 class="fw-bold mb-5">👥 Top Usuários do Assistente</h4>
                <div class="row g-5">
                    <?php foreach (array_slice($assistantStats['top_users'], 0, 5) as $index => $user): ?>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-circle symbol-45px me-3">
                                <div class="symbol-label fs-3 fw-bold bg-light-primary text-primary">
                                    #<?= $index + 1 ?>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold text-gray-900"><?= htmlspecialchars($user['name']) ?></div>
                                <div class="text-muted fs-7">
                                    <?= number_format($user['uses']) ?> usos • 
                                    $<?= number_format($user['total_cost'], 4) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Row-->

<!--begin::Row - Métricas de Fallback de IA-->
<?php if (isset($fallbackStats)): ?>
<div class="row g-5 mb-5">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">
                    <i class="ki-duotone ki-shield-check fs-2x text-warning me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Monitoramento de Fallback de IA
                </h3>
                <div class="card-toolbar">
                    <span class="badge badge-light-warning fs-7">Sistema de Segurança</span>
                </div>
            </div>
            <div class="card-body pt-5">
                <div class="row g-5">
                    <!-- Conversas Travadas Detectadas -->
                    <div class="col-xl-3">
                        <div class="d-flex align-items-center">
                            <i class="ki-duotone ki-information-5 fs-3x text-primary me-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div>
                                <div class="fs-2x fw-bold text-gray-800"><?= number_format($fallbackStats['total_stuck'] ?? 0) ?></div>
                                <div class="text-muted fs-6">Conversas Travadas</div>
                                <div class="text-muted fs-7 mt-1">Total detectado no período</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reprocessadas -->
                    <div class="col-xl-3">
                        <div class="d-flex align-items-center">
                            <i class="ki-duotone ki-arrows-circle fs-3x text-success me-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div>
                                <div class="fs-2x fw-bold text-gray-800"><?= number_format($fallbackStats['reprocessed'] ?? 0) ?></div>
                                <div class="text-muted fs-6">Reprocessadas</div>
                                <div class="text-muted fs-7 mt-1">
                                    <?= $fallbackStats['total_stuck'] > 0 ? number_format($fallbackStats['reprocess_rate'] ?? 0, 1) : '0' ?>% de sucesso
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Escaladas -->
                    <div class="col-xl-3">
                        <div class="d-flex align-items-center">
                            <i class="ki-duotone ki-arrow-up-right fs-3x text-warning me-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div>
                                <div class="fs-2x fw-bold text-gray-800"><?= number_format($fallbackStats['escalated'] ?? 0) ?></div>
                                <div class="text-muted fs-6">Escaladas</div>
                                <div class="text-muted fs-7 mt-1">
                                    <?= $fallbackStats['total_stuck'] > 0 ? number_format($fallbackStats['escalation_rate'] ?? 0, 1) : '0' ?>% escaladas
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Atualmente Travadas -->
                    <div class="col-xl-3">
                        <div class="d-flex align-items-center">
                            <i class="ki-duotone ki-clock fs-3x text-danger me-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div>
                                <div class="fs-2x fw-bold text-gray-800"><?= number_format($fallbackStats['currently_stuck'] ?? 0) ?></div>
                                <div class="text-muted fs-6">Atualmente Travadas</div>
                                <div class="text-muted fs-7 mt-1">Aguardando processamento</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (($fallbackStats['currently_stuck'] ?? 0) > 0): ?>
                <div class="alert alert-warning d-flex align-items-center p-5 mt-5">
                    <i class="ki-duotone ki-information-5 fs-2x text-warning me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1">Atenção: Conversas Travadas Detectadas</h4>
                        <span>Há <?= number_format($fallbackStats['currently_stuck']) ?> conversa(s) aguardando resposta da IA. O sistema de fallback está monitorando e tentará reprocessar automaticamente.</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!--end::Row - Métricas de Fallback de IA-->
<?php endif; ?>

<!--begin::Row - Métricas de Custos e Tempo-->
<div class="row g-5 mb-5">
    <!--begin::Col - Tempo de Resposta IA-->
    <div class="col-xl-3">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">⚡ Tempo de Resposta IA</h3>
            </div>
            <div class="card-body pt-3">
                <div class="d-flex align-items-center">
                    <i class="ki-duotone ki-timer fs-3x text-success me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div>
                        <div class="fs-2x fw-bold text-gray-800">
                            <?php
                            $avgResponseAI = $stats['metrics']['avg_first_response_time_ai'] ?? null;
                            if ($avgResponseAI !== null && $avgResponseAI > 0) {
                                if ($avgResponseAI < 60) {
                                    echo number_format($avgResponseAI, 0) . 's';
                                } else {
                                    echo number_format($avgResponseAI / 60, 1) . 'min';
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                        <div class="text-muted fs-6">Média 1ª resposta</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Tokens Usados-->
    <div class="col-xl-3">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">🎯 Tokens Usados</h3>
            </div>
            <div class="card-body pt-3">
                <div class="d-flex align-items-center">
                    <i class="ki-duotone ki-abstract-26 fs-3x text-primary me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div>
                        <div class="fs-2x fw-bold text-gray-800">
                            <?php
                            $totalTokens = $aiMetrics['total_tokens'] ?? 0;
                            if ($totalTokens >= 1000000) {
                                echo number_format($totalTokens / 1000000, 2) . 'M';
                            } elseif ($totalTokens >= 1000) {
                                echo number_format($totalTokens / 1000, 1) . 'K';
                            } else {
                                echo number_format($totalTokens);
                            }
                            ?>
                        </div>
                        <div class="text-muted fs-6">Total de tokens</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Custo Total-->
    <div class="col-xl-3">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">💰 Custo Total</h3>
            </div>
            <div class="card-body pt-3">
                <div class="d-flex align-items-center">
                    <i class="ki-duotone ki-dollar fs-3x text-danger me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div>
                        <div class="fs-2x fw-bold text-gray-800">
                            $<?= number_format($aiMetrics['total_cost'] ?? 0, 2) ?>
                        </div>
                        <div class="text-muted fs-6">Período selecionado</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Agentes de IA Ativos-->
    <div class="col-xl-3">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">🤖 Agentes Ativos</h3>
            </div>
            <div class="card-body pt-3">
                <div class="d-flex align-items-center">
                    <i class="ki-duotone ki-technology-4 fs-3x text-info me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div>
                        <div class="fs-2x fw-bold text-gray-800">
                            <?= number_format($aiMetrics['active_ai_agents'] ?? 0) ?>
                        </div>
                        <div class="text-muted fs-6">Agentes de IA</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->

<!--begin::Row - Comparação IA vs Humano-->
<div class="row g-5 mb-5">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">📊 Comparação: IA vs Humanos</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Performance comparativa no período</span>
                </h3>
            </div>
            <div class="card-body py-3">
                <div class="row g-5">
                    <!--begin::Col - Métricas IA-->
                    <div class="col-xl-6">
                        <div class="card bg-light-primary">
                            <div class="card-header border-0 pt-5">
                                <h4 class="card-title fw-bold">
                                    <i class="ki-duotone ki-robot fs-2 text-primary me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Atendimento por IA
                                </h4>
                            </div>
                            <div class="card-body pt-3">
                                <div class="d-flex flex-column gap-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-gray-600">Conversas Atendidas:</span>
                                        <span class="fw-bold text-gray-800"><?= number_format($comparison['ai']['total_conversations'] ?? 0) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-gray-600">Resolvidas:</span>
                                        <span class="fw-bold text-success"><?= number_format($comparison['ai']['resolved_conversations'] ?? 0) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-gray-600">Taxa de Resolução:</span>
                                        <span class="badge badge-light-success"><?= number_format($comparison['ai']['resolution_rate'] ?? 0, 1) ?>%</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-gray-600">Tempo 1ª Resposta:</span>
                                        <span class="fw-bold text-primary"><?= htmlspecialchars($comparison['ai']['avg_first_response_formatted'] ?? '-') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Col-->
                    
                    <!--begin::Col - Métricas Humanos-->
                    <div class="col-xl-6">
                        <div class="card bg-light-info">
                            <div class="card-header border-0 pt-5">
                                <h4 class="card-title fw-bold">
                                    <i class="ki-duotone ki-profile-user fs-2 text-info me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Atendimento por Humanos
                                </h4>
                            </div>
                            <div class="card-body pt-3">
                                <div class="d-flex flex-column gap-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-gray-600">Conversas Atendidas:</span>
                                        <span class="fw-bold text-gray-800"><?= number_format($comparison['human']['total_conversations'] ?? 0) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-gray-600">Resolvidas:</span>
                                        <span class="fw-bold text-success"><?= number_format($comparison['human']['resolved_conversations'] ?? 0) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-gray-600">Taxa de Resolução:</span>
                                        <span class="badge badge-light-info"><?= number_format($comparison['human']['resolution_rate'] ?? 0, 1) ?>%</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-gray-600">Tempo 1ª Resposta:</span>
                                        <span class="fw-bold text-info"><?= htmlspecialchars($comparison['human']['avg_first_response_formatted'] ?? '-') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Col-->
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Row-->

<!--begin::Row - SLA Separado-->
<div class="row g-5 mb-5">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">⏱️ Cumprimento de SLA</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Taxa de resposta dentro do SLA (<?= $slaCompliance['sla_minutes'] ?? 15 ?> minutos)</span>
                </h3>
            </div>
            <div class="card-body py-3">
                <div class="row g-5">
                    <!--begin::Col - SLA Geral-->
                    <div class="col-xl-4">
                        <div class="d-flex flex-column align-items-center">
                            <div class="fs-6 fw-semibold text-gray-600 mb-3">📊 Geral (IA + Humanos)</div>
                            <div class="position-relative d-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                <svg viewBox="0 0 36 36" class="circular-chart" style="width: 100%; height: 100%;">
                                    <path class="circle-bg" stroke="#3f4254" stroke-width="3" fill="none"
                                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                    <path class="circle" stroke="#009EF7" stroke-width="3" stroke-linecap="round" fill="none"
                                          stroke-dasharray="<?= $slaCompliance['general']['rate'] ?? 0 ?>, 100"
                                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                </svg>
                                <div class="position-absolute text-center" style="font-size: 1.5rem; font-weight: 700; color: #009EF7;">
                                    <?= number_format($slaCompliance['general']['rate'] ?? 0, 0) ?>%
                                </div>
                            </div>
                            <div class="text-muted fs-7 mt-2"><?= $slaCompliance['general']['within_sla'] ?? 0 ?> de <?= $slaCompliance['general']['total'] ?? 0 ?></div>
                        </div>
                    </div>
                    <!--end::Col-->
                    
                    <!--begin::Col - SLA IA-->
                    <div class="col-xl-4">
                        <div class="d-flex flex-column align-items-center">
                            <div class="fs-6 fw-semibold text-gray-600 mb-3">🤖 Apenas IA</div>
                            <div class="position-relative d-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                <svg viewBox="0 0 36 36" class="circular-chart" style="width: 100%; height: 100%;">
                                    <path class="circle-bg" stroke="#3f4254" stroke-width="3" fill="none"
                                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                    <path class="circle" stroke="#50CD89" stroke-width="3" stroke-linecap="round" fill="none"
                                          stroke-dasharray="<?= $slaCompliance['ai']['rate'] ?? 0 ?>, 100"
                                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                </svg>
                                <div class="position-absolute text-center" style="font-size: 1.5rem; font-weight: 700; color: #50CD89;">
                                    <?= number_format($slaCompliance['ai']['rate'] ?? 0, 0) ?>%
                                </div>
                            </div>
                            <div class="text-muted fs-7 mt-2"><?= $slaCompliance['ai']['within_sla'] ?? 0 ?> de <?= $slaCompliance['ai']['total'] ?? 0 ?></div>
                        </div>
                    </div>
                    <!--end::Col-->
                    
                    <!--begin::Col - SLA Humanos-->
                    <div class="col-xl-4">
                        <div class="d-flex flex-column align-items-center">
                            <div class="fs-6 fw-semibold text-gray-600 mb-3">🧑 Apenas Humanos</div>
                            <div class="position-relative d-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                <svg viewBox="0 0 36 36" class="circular-chart" style="width: 100%; height: 100%;">
                                    <path class="circle-bg" stroke="#3f4254" stroke-width="3" fill="none"
                                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                    <path class="circle" stroke="#7239EA" stroke-width="3" stroke-linecap="round" fill="none"
                                          stroke-dasharray="<?= $slaCompliance['human']['rate'] ?? 0 ?>, 100"
                                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                </svg>
                                <div class="position-absolute text-center" style="font-size: 1.5rem; font-weight: 700; color: #7239EA;">
                                    <?= number_format($slaCompliance['human']['rate'] ?? 0, 0) ?>%
                                </div>
                            </div>
                            <div class="text-muted fs-7 mt-2"><?= $slaCompliance['human']['within_sla'] ?? 0 ?> de <?= $slaCompliance['human']['total'] ?? 0 ?></div>
                        </div>
                    </div>
                    <!--end::Col-->
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Row-->

<!--begin::Row - Ranking de Agentes de IA-->
<?php if (!empty($aiAgentsRanking)): ?>
<div class="row g-5 mb-5">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">🏆 Ranking de Agentes de IA</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Performance dos agentes de IA no período</span>
                </h3>
                <div class="card-toolbar">
                    <a href="<?= \App\Helpers\Url::to('/ai-agents') ?>" class="btn btn-sm btn-light-primary">
                        <i class="ki-duotone ki-setting-2 fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Gerenciar Agentes
                    </a>
                </div>
            </div>
            <div class="card-body py-3">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Agente</th>
                                <th class="min-w-80px">Tipo</th>
                                <th class="min-w-80px">Conversas</th>
                                <th class="min-w-80px">Resolvidas</th>
                                <th class="min-w-80px">Escalonadas</th>
                                <th class="min-w-80px">Taxa Resolução</th>
                                <th class="min-w-80px">Tokens</th>
                                <th class="min-w-80px">Custo</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <?php foreach ($aiAgentsRanking as $agent): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-40px me-3">
                                                <div class="symbol-label fs-3 fw-semibold text-primary bg-light-primary">
                                                    🤖
                                                </div>
                                            </div>
                                            <div class="d-flex flex-column">
                                                <a href="<?= \App\Helpers\Url::to('/ai-agents/' . $agent['id']) ?>" class="text-gray-800 fw-bold text-hover-primary">
                                                    <?= htmlspecialchars($agent['name'] ?? 'Sem nome') ?>
                                                </a>
                                                <span class="text-muted fs-7"><?= htmlspecialchars($agent['model'] ?? 'gpt-4') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-info"><?= htmlspecialchars($agent['agent_type'] ?? 'custom') ?></span>
                                    </td>
                                    <td>
                                        <span class="text-gray-800 fw-bold"><?= number_format($agent['total_conversations'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-success fw-bold"><?= number_format($agent['resolved_conversations'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-warning fw-bold"><?= number_format($agent['escalated_conversations'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $rate = $agent['resolution_rate'] ?? 0;
                                        $badgeClass = $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger');
                                        ?>
                                        <span class="badge badge-light-<?= $badgeClass ?>"><?= number_format($rate, 1) ?>%</span>
                                    </td>
                                    <td>
                                        <?php
                                        $tokens = $agent['total_tokens'] ?? 0;
                                        if ($tokens >= 1000) {
                                            echo number_format($tokens / 1000, 1) . 'K';
                                        } else {
                                            echo number_format($tokens);
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="text-gray-800">$<?= number_format($agent['total_cost'] ?? 0, 2) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Row-->

<style>
.circular-chart {
    display: block;
    max-width: 100%;
    max-height: 120px;
}
.circular-chart .circle-bg {
    fill: none;
}
.circular-chart .circle {
    fill: none;
    animation: progress 1s ease-out forwards;
}
@keyframes progress {
    0% {
        stroke-dasharray: 0, 100;
    }
}
</style>

<script>
function loadAIDashboard() {
    const dateFrom = document.getElementById('kt_ai_dashboard_date_from').value;
    const dateTo = document.getElementById('kt_ai_dashboard_date_to').value;
    
    window.location.href = '<?= \App\Helpers\Url::to('/dashboard/ai') ?>?date_from=' + dateFrom + '&date_to=' + dateTo;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>

