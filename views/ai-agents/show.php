<?php
$layout = 'layouts.metronic.app';
$title = 'Agente de IA - ' . htmlspecialchars($agent['name'] ?? '');

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <h3 class="fw-bold m-0"><?= htmlspecialchars($agent['name']) ?></h3>
                <span class="text-muted fs-7 mt-1"><?= htmlspecialchars($agent['description'] ?? '') ?></span>
            </div>
        </div>
        <div class="card-toolbar">
            <?php if (\App\Helpers\Permission::can('ai_agents.edit')): ?>
            <button type="button" class="btn btn-sm btn-light-primary me-3" data-bs-toggle="modal" data-bs-target="#kt_modal_edit_ai_agent">
                <i class="ki-duotone ki-pencil fs-2"></i>
                Editar
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="row">
            <div class="col-lg-8">
                <!--begin::Informações Básicas-->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Informações Básicas</h3>
                    </div>
                    <div class="card-body">
                        <div class="row mb-7">
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">Tipo</label>
                                <div>
                                    <span class="badge badge-light-info"><?= htmlspecialchars($agent['agent_type']) ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">Modelo</label>
                                <div>
                                    <span class="text-gray-800"><?= htmlspecialchars($agent['model'] ?? 'gpt-4') ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-7">
                            <div class="col-md-4">
                                <label class="fw-semibold fs-6 mb-2">Temperature</label>
                                <div>
                                    <span class="text-gray-800"><?= htmlspecialchars($agent['temperature'] ?? '0.7') ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="fw-semibold fs-6 mb-2">Max Tokens</label>
                                <div>
                                    <span class="text-gray-800"><?= htmlspecialchars($agent['max_tokens'] ?? '2000') ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="fw-semibold fs-6 mb-2">Status</label>
                                <div>
                                    <?php if ($agent['enabled']): ?>
                                        <span class="badge badge-light-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge badge-light-secondary">Inativo</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">Conversas</label>
                                <div>
                                    <?php 
                                    $current = $agent['current_conversations'] ?? 0;
                                    $max = $agent['max_conversations'] ?? null;
                                    ?>
                                    <span class="text-gray-800"><?= $current ?> / <?= $max ?? '∞' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Informações Básicas-->
                
                <!--begin::Prompt-->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Prompt do Sistema</h3>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="copyPromptToClipboard()">
                                <i class="ki-duotone ki-copy fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Copiar
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="bg-light p-5 rounded position-relative">
                            <pre id="agent_prompt_display" class="text-gray-800 fs-6 mb-0" style="white-space: pre-wrap; font-family: 'Courier New', monospace;"><?= htmlspecialchars($agent['prompt']) ?></pre>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex gap-2">
                                <span class="badge badge-light-primary"><?= number_format(mb_strlen($agent['prompt'] ?? '')) ?> caracteres</span>
                                <span class="badge badge-light-info">≈ <?= number_format(mb_strlen($agent['prompt'] ?? '') / 4) ?> tokens</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Prompt-->
                
                <!--begin::RAG - Sistema de Conhecimento-->
                <?php if (\App\Helpers\PostgreSQL::isAvailable()): ?>
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">🧠 Sistema RAG (Knowledge Base)</h3>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <a href="<?= \App\Helpers\Url::to('/ai-agents/' . $agent['id'] . '/rag/knowledge-base') ?>" class="card bg-light-primary hoverable h-100">
                                    <div class="card-body d-flex align-items-center gap-3">
                                        <i class="ki-duotone ki-book fs-2x text-primary">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                            <span class="path5"></span>
                                        </i>
                                        <div>
                                            <div class="fw-bold">Knowledge Base</div>
                                            <div class="text-muted fs-7">Gerenciar conhecimentos</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="<?= \App\Helpers\Url::to('/ai-agents/' . $agent['id'] . '/rag/feedback-loop') ?>" class="card bg-light-warning hoverable h-100">
                                    <div class="card-body d-flex align-items-center gap-3">
                                        <i class="ki-duotone ki-message-question fs-2x text-warning">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <div>
                                            <div class="fw-bold">Feedback Loop</div>
                                            <div class="text-muted fs-7">Treinar e revisar</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="<?= \App\Helpers\Url::to('/ai-agents/' . $agent['id'] . '/rag/urls') ?>" class="card bg-light-info hoverable h-100">
                                    <div class="card-body d-flex align-items-center gap-3">
                                        <i class="ki-duotone ki-link fs-2x text-info">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <div>
                                            <div class="fw-bold">URLs</div>
                                            <div class="text-muted fs-7">Processar páginas web</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="<?= \App\Helpers\Url::to('/ai-agents/' . $agent['id'] . '/rag/memory') ?>" class="card bg-light-success hoverable h-100">
                                    <div class="card-body d-flex align-items-center gap-3">
                                        <i class="ki-duotone ki-brain fs-2x text-success">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <div>
                                            <div class="fw-bold">Memórias</div>
                                            <div class="text-muted fs-7">Memória persistente</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <!--end::RAG-->
                
                <?php 
                // Calcular tools disponíveis para adicionar
                $availableTools = [];
                foreach ($allTools as $tool) {
                    $alreadyAdded = false;
                    foreach ($agent['tools'] ?? [] as $agentTool) {
                        if ($agentTool['id'] == $tool['id']) {
                            $alreadyAdded = true;
                            break;
                        }
                    }
                    if (!$alreadyAdded) {
                        $availableTools[] = $tool;
                    }
                }
                ?>
                
                <!--begin::Tools-->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Tools Disponíveis</h3>
                        <?php if (\App\Helpers\Permission::can('ai_agents.edit')): ?>
                        <div class="card-toolbar">
                            <?php if (!empty($availableTools)): ?>
                            <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_add_tool">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Adicionar Tool
                            </button>
                            <?php else: ?>
                            <a href="<?= \App\Helpers\Url::to('/ai-tools') ?>" class="btn btn-sm btn-light-info">
                                <i class="ki-duotone ki-setting-2 fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Gerenciar Tools
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($agent['tools'])): ?>
                            <div class="text-center py-10">
                                <i class="ki-duotone ki-setting-2 fs-3x text-gray-400 mb-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma tool configurada</h3>
                                <div class="text-gray-500 fs-6 mb-7">
                                    <?php if (empty($allTools)): ?>
                                        Não há tools disponíveis no sistema. 
                                        <a href="<?= \App\Helpers\Url::to('/ai-tools') ?>" class="text-primary">Criar primeira tool</a>
                                    <?php else: ?>
                                        Adicione tools para este agente clicando no botão acima.
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle table-row-dashed fs-6 gy-5">
                                    <thead>
                                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                            <th class="min-w-200px">Nome</th>
                                            <th class="min-w-100px">Tipo</th>
                                            <th class="min-w-150px">Descrição</th>
                                            <th class="min-w-100px">Status</th>
                                            <th class="text-end min-w-70px">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($agent['tools'] as $tool): ?>
                                            <tr>
                                                <td>
                                                    <span class="text-gray-800 fw-bold"><?= htmlspecialchars($tool['name']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-light-primary"><?= htmlspecialchars($tool['tool_type']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-gray-600"><?= htmlspecialchars(mb_substr($tool['description'] ?? '', 0, 50)) ?><?= mb_strlen($tool['description'] ?? '') > 50 ? '...' : '' ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($tool['tool_enabled'] ?? true): ?>
                                                        <span class="badge badge-light-success">Ativa</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-light-secondary">Inativa</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if (\App\Helpers\Permission::can('ai_agents.edit')): ?>
                                                    <button type="button" class="btn btn-sm btn-light-danger" onclick="removeTool(<?= $agent['id'] ?>, <?= $tool['id'] ?>)">
                                                        Remover
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!--end::Tools-->
            </div>
            
            <div class="col-lg-4">
                <!--begin::Performance Detalhada-->
                <?php 
                // Buscar métricas detalhadas do agente
                $performanceStats = null;
                if (!empty($agent['id'])) {
                    try {
                        $performanceStats = \App\Services\AIAgentPerformanceService::getPerformanceStats($agent['id']);
                    } catch (\Exception $e) {
                        error_log("Erro ao buscar métricas do agente IA: " . $e->getMessage());
                    }
                }
                ?>
                
                <!--begin::Card - Resumo Principal-->
                <div class="card mb-5 bg-light-primary">
                    <div class="card-body py-4">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="symbol symbol-45px">
                                <div class="symbol-label fs-2x bg-primary text-white">🤖</div>
                            </div>
                            <div>
                                <h4 class="fw-bold mb-0">Performance</h4>
                                <span class="text-muted fs-7">Últimos 30 dias</span>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="border rounded p-3 flex-fill text-center bg-white">
                                <span class="fw-bold fs-3 text-gray-800 d-block"><?= number_format($performanceStats['total_conversations'] ?? $agent['stats']['total_conversations'] ?? 0) ?></span>
                                <span class="text-muted fs-7">Conversas</span>
                            </div>
                            <div class="border rounded p-3 flex-fill text-center bg-white">
                                <span class="fw-bold fs-3 text-success d-block"><?= number_format($performanceStats['resolution_rate'] ?? 0, 0) ?>%</span>
                                <span class="text-muted fs-7">Resolução</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Card-->
                
                <!--begin::Estatísticas Detalhadas-->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">📊 Estatísticas Detalhadas</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-4">
                            <!--begin::Conversas-->
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <div class="d-flex align-items-center">
                                    <i class="ki-duotone ki-message-programming fs-2 text-primary me-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <span class="fw-semibold text-gray-700">Total de Conversas</span>
                                </div>
                                <span class="fw-bold text-gray-900 fs-4"><?= number_format($performanceStats['total_conversations'] ?? $agent['stats']['total_conversations'] ?? 0) ?></span>
                            </div>
                            <!--end::Conversas-->
                            
                            <!--begin::Ativas-->
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <div class="d-flex align-items-center">
                                    <i class="ki-duotone ki-timer fs-2 text-warning me-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <span class="fw-semibold text-gray-700">Conversas Ativas</span>
                                </div>
                                <span class="badge badge-light-warning fs-6"><?= number_format($performanceStats['active_conversations'] ?? $agent['current_conversations'] ?? 0) ?></span>
                            </div>
                            <!--end::Ativas-->
                            
                            <!--begin::Resolvidas-->
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <div class="d-flex align-items-center">
                                    <i class="ki-duotone ki-check-circle fs-2 text-success me-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <span class="fw-semibold text-gray-700">Resolvidas (sem escalar)</span>
                                </div>
                                <span class="badge badge-light-success fs-6"><?= number_format($performanceStats['resolved_conversations'] ?? $agent['stats']['completed_conversations'] ?? 0) ?></span>
                            </div>
                            <!--end::Resolvidas-->
                            
                            <!--begin::Escalonadas-->
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <div class="d-flex align-items-center">
                                    <i class="ki-duotone ki-arrow-up-right fs-2 text-danger me-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <span class="fw-semibold text-gray-700">Escalonadas p/ Humano</span>
                                </div>
                                <span class="badge badge-light-danger fs-6"><?= number_format($performanceStats['escalated_conversations'] ?? $agent['stats']['escalated_conversations'] ?? 0) ?></span>
                            </div>
                            <!--end::Escalonadas-->
                            
                            <!--begin::Mensagens-->
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <div class="d-flex align-items-center">
                                    <i class="ki-duotone ki-message-text-2 fs-2 text-info me-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <span class="fw-semibold text-gray-700">Mensagens Enviadas</span>
                                </div>
                                <span class="fw-bold text-gray-900 fs-4"><?= number_format($performanceStats['total_messages'] ?? 0) ?></span>
                            </div>
                            <!--end::Mensagens-->
                            
                            <!--begin::Tempo Resposta-->
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light-success rounded">
                                <div class="d-flex align-items-center">
                                    <i class="ki-duotone ki-time fs-2 text-success me-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <span class="fw-semibold text-gray-700">Tempo Médio Resposta</span>
                                </div>
                                <span class="fw-bold text-success fs-5"><?= htmlspecialchars($performanceStats['avg_response_time_formatted'] ?? '-') ?></span>
                            </div>
                            <!--end::Tempo Resposta-->
                        </div>
                    </div>
                </div>
                <!--end::Estatísticas Detalhadas-->
                
                <!--begin::Custos e Tokens-->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">💰 Custos e Tokens</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-4">
                            <!--begin::Tokens Total-->
                            <div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="fw-semibold text-gray-700">Total de Tokens</span>
                                    <span class="fw-bold text-gray-900">
                                        <?php
                                        $totalTokens = $performanceStats['total_tokens'] ?? $agent['stats']['total_tokens'] ?? 0;
                                        if ($totalTokens >= 1000000) {
                                            echo number_format($totalTokens / 1000000, 2) . 'M';
                                        } elseif ($totalTokens >= 1000) {
                                            echo number_format($totalTokens / 1000, 1) . 'K';
                                        } else {
                                            echo number_format($totalTokens);
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="progress h-8px">
                                    <div class="progress-bar bg-primary" style="width: 100%"></div>
                                </div>
                                <div class="d-flex justify-content-between text-muted fs-7 mt-1">
                                    <span>Prompt: <?= number_format($performanceStats['tokens_prompt'] ?? 0) ?></span>
                                    <span>Completion: <?= number_format($performanceStats['tokens_completion'] ?? 0) ?></span>
                                </div>
                            </div>
                            <!--end::Tokens Total-->
                            
                            <!--begin::Custo Total-->
                            <div class="border-top pt-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold text-gray-700">Custo Total (USD)</span>
                                    <span class="fw-bold text-gray-900 fs-3">
                                        $<?= number_format($performanceStats['total_cost'] ?? $agent['stats']['total_cost'] ?? 0, 4) ?>
                                    </span>
                                </div>
                                <?php 
                                $usdToBrl = 5.20;
                                $costBrl = ($performanceStats['total_cost'] ?? $agent['stats']['total_cost'] ?? 0) * $usdToBrl;
                                ?>
                                <div class="text-muted fs-7 text-end">
                                    ≈ R$ <?= number_format($costBrl, 2, ',', '.') ?>
                                </div>
                            </div>
                            <!--end::Custo Total-->
                            
                            <!--begin::Custo Médio-->
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="fw-semibold text-gray-700">Custo Médio/Conversa</span>
                                <span class="fw-bold text-gray-900">
                                    $<?= number_format($performanceStats['avg_cost_per_conversation'] ?? 0, 4) ?>
                                </span>
                            </div>
                            <!--end::Custo Médio-->
                        </div>
                    </div>
                </div>
                <!--end::Custos e Tokens-->
                
                <!--begin::Tools Utilizadas-->
                <?php if (!empty($performanceStats['tools_used'])): ?>
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">🔧 Tools Mais Utilizadas</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-3">
                            <?php 
                            $toolsUsed = $performanceStats['tools_used'];
                            $maxUsage = max($toolsUsed) ?: 1;
                            $count = 0;
                            foreach ($toolsUsed as $toolName => $usage): 
                                if ($count >= 5) break; // Mostrar apenas top 5
                                $percentage = ($usage / $maxUsage) * 100;
                            ?>
                            <div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-gray-700"><?= htmlspecialchars($toolName) ?></span>
                                    <span class="fw-bold text-gray-800"><?= number_format($usage) ?>x</span>
                                </div>
                                <div class="progress h-6px">
                                    <div class="progress-bar bg-info" style="width: <?= $percentage ?>%"></div>
                                </div>
                            </div>
                            <?php 
                                $count++;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <!--end::Tools Utilizadas-->
                
                <!--begin::Histórico de Execuções de Tools-->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">🔧 Histórico de Execuções de Tools</h3>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="loadToolExecutions(<?= $agent['id'] ?>, 1)">
                                <i class="ki-duotone ki-arrows-circle fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Atualizar
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="tool_executions_container">
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Histórico de Execuções de Tools-->
                
            </div>
        </div>
        
        <!--begin::Conversas - largura total-->
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="card-title">Conversas Atendidas pela IA</h3>
            </div>
            <div class="card-body">
                <div id="ai_conversations_container">
                    <div class="text-center py-10">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
                <div id="ai_conversations_pagination" class="d-flex justify-content-between align-items-center mt-5" style="display: none !important;">
                    <div class="text-muted fs-7">
                        <span id="pagination_info">Carregando...</span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-light" id="prev_page_btn" disabled>
                            <i class="ki-duotone ki-arrow-left fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Anterior
                        </button>
                        <button type="button" class="btn btn-sm btn-light" id="next_page_btn" disabled>
                            Próxima
                            <i class="ki-duotone ki-arrow-right fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Conversas - largura total-->
    </div>
</div>
<!--end::Card-->

<?php 
// Calcular tools disponíveis para adicionar
$availableTools = [];
foreach ($allTools as $tool) {
    $alreadyAdded = false;
    foreach ($agent['tools'] ?? [] as $agentTool) {
        if ($agentTool['id'] == $tool['id']) {
            $alreadyAdded = true;
            break;
        }
    }
    if (!$alreadyAdded) {
        $availableTools[] = $tool;
    }
}
?>

<!--begin::Modal - Adicionar Tool-->
<div class="modal fade" id="kt_modal_add_tool" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Adicionar Tool ao Agente</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_add_tool_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <input type="hidden" name="agent_id" value="<?= $agent['id'] ?>" />
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Tool</label>
                        <?php if (empty($availableTools)): ?>
                            <div class="alert alert-warning">
                                <i class="ki-duotone ki-information-5 fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Todas as tools disponíveis já foram adicionadas a este agente. 
                                <a href="<?= \App\Helpers\Url::to('/ai-tools') ?>" class="alert-link">Criar nova tool</a>
                            </div>
                        <?php else: ?>
                            <select name="tool_id" class="form-select form-select-solid" required>
                                <option value="">Selecione uma tool</option>
                                <?php foreach ($availableTools as $tool): ?>
                                    <option value="<?= $tool['id'] ?>"><?= htmlspecialchars($tool['name']) ?> (<?= htmlspecialchars($tool['tool_type']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="enabled" class="form-check-input me-2" checked />
                            Tool ativa para este agente
                        </label>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <?php if (!empty($availableTools)): ?>
                    <button type="submit" id="kt_modal_add_tool_submit" class="btn btn-primary">
                        <span class="indicator-label">Adicionar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                    <?php else: ?>
                    <a href="<?= \App\Helpers\Url::to('/ai-tools') ?>" class="btn btn-primary">
                        Criar Nova Tool
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Adicionar Tool-->

<!--begin::Modal - Editar Agente de IA-->
<div class="modal fade" id="kt_modal_edit_ai_agent" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Agente de IA</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_edit_ai_agent_form" class="form">
                <input type="hidden" name="agent_id" value="<?= $agent['id'] ?>" />
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" id="edit_agent_name" class="form-control form-control-solid" placeholder="Nome do agente" value="<?= htmlspecialchars($agent['name']) ?>" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" id="edit_agent_description" class="form-control form-control-solid" rows="3" placeholder="Descrição do agente"><?= htmlspecialchars($agent['description'] ?? '') ?></textarea>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Tipo</label>
                        <select name="agent_type" id="edit_agent_type" class="form-select form-select-solid" required>
                            <option value="SDR" <?= ($agent['agent_type'] ?? '') === 'SDR' ? 'selected' : '' ?>>SDR (Sales Development Representative)</option>
                            <option value="CS" <?= ($agent['agent_type'] ?? '') === 'CS' ? 'selected' : '' ?>>CS (Customer Success)</option>
                            <option value="CLOSER" <?= ($agent['agent_type'] ?? '') === 'CLOSER' ? 'selected' : '' ?>>CLOSER (Fechamento de Vendas)</option>
                            <option value="FOLLOWUP" <?= ($agent['agent_type'] ?? '') === 'FOLLOWUP' ? 'selected' : '' ?>>FOLLOWUP (Follow-up Automático)</option>
                            <option value="SUPPORT" <?= ($agent['agent_type'] ?? '') === 'SUPPORT' ? 'selected' : '' ?>>SUPPORT (Suporte Técnico)</option>
                            <option value="ONBOARDING" <?= ($agent['agent_type'] ?? '') === 'ONBOARDING' ? 'selected' : '' ?>>ONBOARDING (Onboarding)</option>
                            <option value="GENERAL" <?= ($agent['agent_type'] ?? '') === 'GENERAL' ? 'selected' : '' ?>>GENERAL (Geral)</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Prompt do Sistema</label>
                        <textarea name="prompt" id="edit_agent_prompt" class="form-control form-control-solid" rows="8" placeholder="Digite o prompt do sistema que define o comportamento do agente..." required><?= htmlspecialchars($agent['prompt']) ?></textarea>
                        <div class="form-text">Este prompt será usado como instrução para o agente de IA. Seja específico sobre o papel, tom e comportamento esperado.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Modelo</label>
                                <select name="model" id="edit_agent_model" class="form-select form-select-solid">
                                    <option value="gpt-4o" <?= ($agent['model'] ?? '') === 'gpt-4o' ? 'selected' : '' ?>>GPT-4o</option>
                                    <option value="gpt-4" <?= ($agent['model'] ?? 'gpt-4') === 'gpt-4' ? 'selected' : '' ?>>GPT-4</option>
                                    <option value="gpt-4-turbo" <?= ($agent['model'] ?? '') === 'gpt-4-turbo' ? 'selected' : '' ?>>GPT-4 Turbo</option>
                                    <option value="gpt-3.5-turbo" <?= ($agent['model'] ?? '') === 'gpt-3.5-turbo' ? 'selected' : '' ?>>GPT-3.5 Turbo</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Temperature</label>
                                <input type="number" name="temperature" id="edit_agent_temperature" class="form-control form-control-solid" value="<?= htmlspecialchars($agent['temperature'] ?? '0.7') ?>" step="0.1" min="0" max="2" />
                                <div class="form-text">0.0 = Determinístico, 2.0 = Criativo</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Max Tokens</label>
                                <input type="number" name="max_tokens" id="edit_agent_max_tokens" class="form-control form-control-solid" value="<?= htmlspecialchars($agent['max_tokens'] ?? '2000') ?>" min="1" />
                            </div>
                        </div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Limite de Conversas Simultâneas</label>
                        <input type="number" name="max_conversations" id="edit_agent_max_conversations" class="form-control form-control-solid" placeholder="Deixe em branco para ilimitado" value="<?= htmlspecialchars($agent['max_conversations'] ?? '') ?>" min="1" />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="enabled" id="edit_agent_enabled" class="form-check-input me-2" <?= ($agent['enabled'] ?? true) ? 'checked' : '' ?> />
                            Agente ativo
                        </label>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_edit_ai_agent_submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Editar Agente de IA-->

<!--begin::Modal - Histórico da Conversa-->
<div class="modal fade" id="kt_modal_conversation_history" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Histórico da Conversa</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div id="conversation_history_content">
                    <div class="text-center py-10">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer flex-center">
                <button type="button" data-bs-dismiss="modal" class="btn btn-light">Fechar</button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal - Histórico da Conversa-->

<?php 
$content = ob_get_clean(); 
$agentsUrl = \App\Helpers\Url::to('/ai-agents');
$conversationsUrl = \App\Helpers\Url::to('/conversations');
$agentId = (int)($agent['id'] ?? 0);

$scriptTemplate = <<<'HTML'
<script>
const AGENTS_URL = __AGENTS_URL__;
const CONVERSATIONS_URL = __CONVERSATIONS_URL__;

function removeTool(agentId, toolId) {
    Swal.fire({
        title: "Tem certeza?",
        text: "Esta tool será removida do agente. Esta ação não pode ser desfeita.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sim, remover",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#d33"
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`${AGENTS_URL}/${agentId}/tools/${toolId}`, {
                method: "DELETE",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Sucesso!",
                        text: data.message || "Tool removida com sucesso!",
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Erro",
                        text: data.message || "Erro ao remover tool"
                    });
                }
            })
            .catch(() => {
                Swal.fire({
                    icon: "error",
                    title: "Erro",
                    text: "Erro ao remover tool"
                });
            });
        }
    });
}

document.addEventListener("DOMContentLoaded", function() {
    const addToolForm = document.getElementById("kt_modal_add_tool_form");
    if (addToolForm) {
        addToolForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById("kt_modal_add_tool_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(addToolForm);
            
            fetch(`${AGENTS_URL}/${formData.get("agent_id")}/tools`, {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Sucesso!",
                        text: data.message || "Tool adicionada com sucesso!",
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Erro",
                        text: data.message || "Erro ao adicionar tool"
                    });
                }
            })
            .catch(() => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                Swal.fire({
                    icon: "error",
                    title: "Erro",
                    text: "Erro ao adicionar tool"
                });
            });
        });
    }
    
    const editAgentForm = document.getElementById("kt_modal_edit_ai_agent_form");
    if (editAgentForm) {
        editAgentForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById("kt_modal_edit_ai_agent_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(editAgentForm);
            const agentId = formData.get("agent_id");
            
            // Converter enabled checkbox para boolean
            formData.set("enabled", document.getElementById("edit_agent_enabled").checked ? "1" : "0");
            
            fetch(`${AGENTS_URL}/${agentId}`, {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Sucesso!",
                        text: data.message || "Agente de IA atualizado com sucesso!",
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Erro",
                        text: data.message || "Erro ao atualizar agente de IA"
                    });
                }
            })
            .catch(() => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                Swal.fire({
                    icon: "error",
                    title: "Erro",
                    text: "Erro ao atualizar agente de IA"
                });
            });
        });
    }
    
    // Carregar conversas do agente
    const agentId = __AGENT_ID__;
    let currentPage = 1;
    const limit = 20;
    
    function loadConversations(page = 1) {
        const container = document.getElementById("ai_conversations_container");
        const paginationDiv = document.getElementById("ai_conversations_pagination");
        
        container.innerHTML = `
            <div class="text-center py-10">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
        `;
        
        fetch(`${AGENTS_URL}/${agentId}/conversations?page=${page}&limit=${limit}`, {
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentPage = page;
                renderConversations(data.conversations, data.pagination);
                updatePagination(data.pagination);
            } else {
                container.innerHTML = `
                    <div class="text-center py-10">
                        <p class="text-muted">Erro ao carregar conversas: ${data.message || "Erro desconhecido"}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error("Erro:", error);
            container.innerHTML = `
                <div class="text-center py-10">
                    <p class="text-muted">Erro ao carregar conversas. Tente novamente.</p>
                </div>
            `;
        });
    }
    
    function renderConversations(conversations, pagination) {
        const container = document.getElementById("ai_conversations_container");
        
        if (!conversations || conversations.length === 0) {
            container.innerHTML = `
                <div class="text-center py-10">
                    <i class="ki-duotone ki-chat fs-3x text-gray-400 mb-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <h3 class="text-gray-800 fw-bold mb-2">Nenhuma conversa encontrada</h3>
                    <div class="text-gray-500 fs-6">Este agente ainda não atendeu nenhuma conversa.</div>
                </div>
            `;
            return;
        }
        
        const usdToBrl = 5.20; // Taxa de conversão
        
        container.innerHTML = `
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-150px">Contato</th>
                            <th class="min-w-100px">Status</th>
                            <th class="min-w-100px">Tokens</th>
                            <th class="min-w-100px">Custo</th>
                            <th class="min-w-150px">Data</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${conversations.map(conv => {
                            const statusClass = conv.status === "completed" ? "success" : 
                                               conv.status === "escalated" ? "warning" : 
                                               conv.status === "active" ? "primary" : "secondary";
                            const statusText = conv.status === "completed" ? "Completa" : 
                                              conv.status === "escalated" ? "Escalada" : 
                                              conv.status === "active" ? "Ativa" : "Desconhecido";
                            const cost = parseFloat(conv.cost || 0);
                            const costBrl = cost * usdToBrl;
                            const date = new Date(conv.created_at);
                            const dateStr = date.toLocaleDateString("pt-BR") + " " + date.toLocaleTimeString("pt-BR", {hour: "2-digit", minute: "2-digit"});
                            
                            return `
                                <tr>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="text-gray-800 fw-bold">${escapeHtml(conv.contact_name || "Sem nome")}</span>
                                            ${conv.contact_phone ? `<span class="text-muted fs-7">${escapeHtml(conv.contact_phone)}</span>` : ""}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-${statusClass}">${statusText}</span>
                                    </td>
                                    <td>
                                        <span class="text-gray-800">${parseInt(conv.tokens_used || 0).toLocaleString("pt-BR")}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="text-gray-800">$${cost.toFixed(4)}</span>
                                            <span class="text-muted fs-7">R$ ${costBrl.toFixed(2)}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-gray-600">${dateStr}</span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-light btn-active-light-primary view-history-btn" data-id="${conv.id}">
                                            Ver Histórico
                                        </button>
                                    </td>
                                </tr>
                            `;
                        }).join("")}
                    </tbody>
                </table>
            </div>
        `;
        
        // Adicionar event listeners aos botões
        document.querySelectorAll(".view-history-btn").forEach(btn => {
            btn.addEventListener("click", function() {
                const conversationId = this.getAttribute("data-id");
                loadConversationHistory(conversationId);
            });
        });
    }
    
    function updatePagination(pagination) {
        const paginationDiv = document.getElementById("ai_conversations_pagination");
        const prevBtn = document.getElementById("prev_page_btn");
        const nextBtn = document.getElementById("next_page_btn");
        const infoSpan = document.getElementById("pagination_info");
        
        if (pagination.total === 0) {
            paginationDiv.style.display = "none";
            return;
        }
        
        paginationDiv.style.display = "flex";
        
        const start = ((pagination.page - 1) * pagination.limit) + 1;
        const end = Math.min(pagination.page * pagination.limit, pagination.total);
        
        infoSpan.textContent = `Mostrando ${start} a ${end} de ${pagination.total} conversas`;
        
        prevBtn.disabled = pagination.page <= 1;
        nextBtn.disabled = pagination.page >= pagination.total_pages;
        
        prevBtn.onclick = () => {
            if (pagination.page > 1) {
                loadConversations(pagination.page - 1);
            }
        };
        
        nextBtn.onclick = () => {
            if (pagination.page < pagination.total_pages) {
                loadConversations(pagination.page + 1);
            }
        };
    }
    
    function loadConversationHistory(conversationId) {
        const modal = new bootstrap.Modal(document.getElementById("kt_modal_conversation_history"));
        const content = document.getElementById("conversation_history_content");
        
        content.innerHTML = `
            <div class="text-center py-10">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
        `;
        
        modal.show();
        
        fetch(`${AGENTS_URL}/${agentId}/conversations/${conversationId}/history`, {
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.history) {
                renderConversationHistory(data.history);
            } else {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="ki-duotone ki-information-5 fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Erro ao carregar histórico: ${data.message || "Erro desconhecido"}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error("Erro:", error);
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="ki-duotone ki-information-5 fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Erro ao carregar histórico. Tente novamente.
                </div>
            `;
        });
    }
    
    function renderConversationHistory(history) {
        const content = document.getElementById("conversation_history_content");
        const usdToBrl = 5.20;
        const cost = parseFloat(history.cost || 0);
        const costBrl = cost * usdToBrl;
        
        let html = `
            <div class="mb-7">
                <h4 class="fw-bold mb-5">Informações da Conversa</h4>
                <div class="row mb-5">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <span class="text-gray-600 fs-7">Contato:</span>
                            <div class="text-gray-800 fw-semibold">${escapeHtml(history.contact_name || "Sem nome")}</div>
                            ${history.contact_phone ? `<div class="text-muted fs-7">${escapeHtml(history.contact_phone)}</div>` : ""}
                            ${history.contact_email ? `<div class="text-muted fs-7">${escapeHtml(history.contact_email)}</div>` : ""}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <span class="text-gray-600 fs-7">Status:</span>
                            <div>
                                <span class="badge badge-light-${history.status === "completed" ? "success" : history.status === "escalated" ? "warning" : "primary"}">
                                    ${history.status === "completed" ? "Completa" : history.status === "escalated" ? "Escalada" : "Ativa"}
                                </span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <span class="text-gray-600 fs-7">Canal:</span>
                            <div class="text-gray-800">${escapeHtml(history.channel || "N/A")}</div>
                        </div>
                    </div>
                </div>
                <div class="row mb-5">
                    <div class="col-md-3">
                        <div class="mb-3">
                            <span class="text-gray-600 fs-7">Tokens Utilizados:</span>
                            <div class="text-gray-800 fw-bold">${parseInt(history.tokens_used || 0).toLocaleString("pt-BR")}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <span class="text-gray-600 fs-7">Custo:</span>
                            <div class="text-gray-800 fw-bold">$${cost.toFixed(4)}</div>
                            <div class="text-muted fs-7">R$ ${costBrl.toFixed(2)}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <span class="text-gray-600 fs-7">Criada em:</span>
                            <div class="text-gray-800">${new Date(history.created_at).toLocaleString("pt-BR")}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <span class="text-gray-600 fs-7">Atualizada em:</span>
                            <div class="text-gray-800">${new Date(history.updated_at).toLocaleString("pt-BR")}</div>
                        </div>
                    </div>
                </div>
                ${history.escalated_to_name ? `
                    <div class="alert alert-warning">
                        <i class="ki-duotone ki-information-5 fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Escalada para: ${escapeHtml(history.escalated_to_name)}
                    </div>
                ` : ""}
            </div>
        `;
        
        // Tools utilizadas
        if (history.tools_used && Array.isArray(history.tools_used) && history.tools_used.length > 0) {
            html += `
                <div class="mb-7">
                    <h4 class="fw-bold mb-5">Tools Utilizadas</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Tool</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${history.tools_used.map(tool => `
                                    <tr>
                                        <td><code>${escapeHtml(tool.tool || "N/A")}</code></td>
                                        <td>${escapeHtml(tool.timestamp || "N/A")}</td>
                                    </tr>
                                `).join("")}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
        
        // Mensagens da conversa
        if (history.conversation_messages && history.conversation_messages.length > 0) {
            html += `
                <div class="mb-7">
                    <h4 class="fw-bold mb-5">Mensagens da Conversa</h4>
                    <div class="timeline">
                        ${history.conversation_messages.map(msg => {
                            const isAI = msg.ai_agent_id !== null;
                            const isContact = msg.sender_type === "contact";
                            const senderName = msg.sender_name || (isAI ? "IA" : isContact ? "Cliente" : "Agente");
                            const bgClass = isAI ? "bg-light-primary" : isContact ? "bg-light-info" : "bg-light-success";
                            
                            return `
                                <div class="timeline-item mb-5">
                                    <div class="timeline-line w-40px"></div>
                                    <div class="timeline-icon symbol symbol-circle symbol-40px ${bgClass}">
                                        <i class="ki-duotone ki-message-text-2 fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                    </div>
                                    <div class="timeline-content mb-0">
                                        <div class="fw-bold mb-1">${escapeHtml(senderName)}</div>
                                        <div class="text-gray-600 mb-2">${escapeHtml(msg.content || "")}</div>
                                        <div class="text-muted fs-7">${new Date(msg.created_at).toLocaleString("pt-BR")}</div>
                                    </div>
                                </div>
                            `;
                        }).join("")}
                    </div>
                </div>
            `;
        }
        
        content.innerHTML = html;
    }
    
    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Carregar conversas ao carregar a página
    loadConversations(1);
    loadToolExecutions(agentId, 1);
});

// Função para copiar prompt para clipboard
function copyPromptToClipboard() {
    const promptText = document.getElementById('agent_prompt_display').textContent;
    navigator.clipboard.writeText(promptText).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Copiado!',
            text: 'Prompt copiado para a área de transferência',
            timer: 2000,
            showConfirmButton: false
        });
    }).catch(err => {
        console.error('Erro ao copiar:', err);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Não foi possível copiar o prompt'
        });
    });
}

// Função para carregar histórico de execuções de tools
function loadToolExecutions(agentId, page = 1) {
    const container = document.getElementById("tool_executions_container");
    container.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
        </div>
    `;
    
    fetch(`${AGENTS_URL}/${agentId}/tool-executions?page=${page}`, {
        headers: {
            "X-Requested-With": "XMLHttpRequest",
            "Accept": "application/json"
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.executions) {
            if (data.executions.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-10">
                        <i class="ki-duotone ki-setting-2 fs-3x text-gray-400 mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <h3 class="text-gray-800 fw-bold mb-2">Nenhuma execução de tool encontrada</h3>
                        <div class="text-gray-500 fs-6">As execuções de tools aparecerão aqui quando o agente começar a usar as ferramentas.</div>
                    </div>
                `;
                return;
            }
            
            let html = `
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-150px">Data/Hora</th>
                                <th class="min-w-200px">Tool</th>
                                <th class="min-w-150px">Conversa</th>
                                <th class="min-w-200px">Argumentos</th>
                                <th class="min-w-100px">Status</th>
                                <th class="text-end min-w-100px">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            data.executions.forEach(exec => {
                const date = new Date(exec.timestamp || exec.created_at);
                const formattedDate = date.toLocaleString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                const args = exec.call ? JSON.stringify(exec.call, null, 2) : '{}';
                const hasError = exec.result && exec.result.error;
                
                html += `
                    <tr>
                        <td>
                            <span class="text-gray-800">${formattedDate}</span>
                        </td>
                        <td>
                            <span class="badge badge-light-primary">${escapeHtml(exec.tool || 'N/A')}</span>
                        </td>
                        <td>
                            <a href="${CONVERSATIONS_URL}/${exec.conversation_id}" class="text-primary fw-semibold" target="_blank">
                                Conversa #${exec.conversation_id}
                            </a>
                        </td>
                        <td>
                            <code class="text-gray-600 fs-7" style="white-space: pre-wrap; max-width: 200px; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(args.substring(0, 100))}${args.length > 100 ? '...' : ''}</code>
                        </td>
                        <td>
                            ${hasError ? 
                                '<span class="badge badge-light-danger">Erro</span>' : 
                                '<span class="badge badge-light-success">Sucesso</span>'
                            }
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-light-info" onclick="showToolExecutionDetails(${JSON.stringify(exec).replace(/"/g, '&quot;')})">
                                <i class="ki-duotone ki-eye fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                Detalhes
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="alert alert-warning">
                    <i class="ki-duotone ki-information-5 fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    ${data.message || 'Erro ao carregar execuções de tools'}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        container.innerHTML = `
            <div class="alert alert-danger">
                <i class="ki-duotone ki-information-5 fs-2 me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                Erro ao carregar execuções de tools
            </div>
        `;
    });
}

// Função para mostrar detalhes de execução de tool
function showToolExecutionDetails(exec) {
    const args = exec.call ? JSON.stringify(exec.call, null, 2) : '{}';
    const result = exec.result ? JSON.stringify(exec.result, null, 2) : '{}';
    const hasError = exec.result && exec.result.error;
    
    Swal.fire({
        title: `Detalhes da Execução - ${escapeHtml(exec.tool || 'N/A')}`,
        html: `
            <div class="text-start">
                <div class="mb-4">
                    <strong class="text-gray-700">Data/Hora:</strong>
                    <div class="text-gray-800">${new Date(exec.timestamp || exec.created_at).toLocaleString('pt-BR')}</div>
                </div>
                <div class="mb-4">
                    <strong class="text-gray-700">Conversa:</strong>
                    <div class="text-gray-800">
                        <a href="${CONVERSATIONS_URL}/${exec.conversation_id}" class="text-primary" target="_blank">
                            Conversa #${exec.conversation_id}
                        </a>
                    </div>
                </div>
                <div class="mb-4">
                    <strong class="text-gray-700">Status:</strong>
                    <div>
                        ${hasError ? 
                            '<span class="badge badge-light-danger">Erro</span>' : 
                            '<span class="badge badge-light-success">Sucesso</span>'
                        }
                    </div>
                </div>
                <div class="mb-4">
                    <strong class="text-gray-700">Argumentos:</strong>
                    <pre class="bg-light p-3 rounded mt-2" style="max-height: 200px; overflow-y: auto; font-size: 0.85rem;">${escapeHtml(args)}</pre>
                </div>
                <div class="mb-4">
                    <strong class="text-gray-700">Resultado:</strong>
                    <pre class="bg-light p-3 rounded mt-2 ${hasError ? 'text-danger' : 'text-success'}" style="max-height: 200px; overflow-y: auto; font-size: 0.85rem;">${escapeHtml(result)}</pre>
                </div>
            </div>
        `,
        width: '800px',
        showConfirmButton: true,
        confirmButtonText: 'Fechar',
        customClass: {
            htmlContainer: 'text-start'
        }
    });
}
</script>
HTML;

$scripts = str_replace(
    ['__AGENTS_URL__', '__CONVERSATIONS_URL__', '__AGENT_ID__'],
    [json_encode($agentsUrl), json_encode($conversationsUrl), (string)$agentId],
    $scriptTemplate
);
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>

