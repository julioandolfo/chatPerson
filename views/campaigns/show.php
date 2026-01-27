<?php
$layout = 'layouts.metronic.app';
$title = $campaign['name'] ?? 'Campanha';
$pageTitle = 'Detalhes da Campanha';

ob_start();
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    <?php echo htmlspecialchars($campaign['name']); ?>
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="/dashboard" class="text-muted text-hover-primary">Home</a>
                    </li>
                    <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                    <li class="breadcrumb-item text-muted">
                        <a href="/campaigns" class="text-muted text-hover-primary">Campanhas</a>
                    </li>
                    <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                    <li class="breadcrumb-item text-muted">#<?php echo $campaign['id']; ?></li>
                </ul>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="/campaigns/<?php echo $campaign['id']; ?>/export" target="_blank" class="btn btn-sm btn-light">
                    <i class="ki-duotone ki-file-down fs-3"></i>
                    Exportar Relatório
                </a>
                
                <?php if ($campaign['status'] === 'draft' || $campaign['status'] === 'scheduled'): ?>
                <button class="btn btn-sm btn-light" onclick="prepareCampaign()" title="Pré-visualizar quantos contatos serão enviados">
                    <i class="ki-duotone ki-verify fs-3"></i>
                    Preparar
                </button>
                <button class="btn btn-sm btn-primary" onclick="startCampaign()">
                    <i class="ki-duotone ki-play fs-3"></i>
                    Iniciar Campanha
                </button>
                <?php endif; ?>
                
                <?php if ($campaign['status'] === 'running'): ?>
                <button class="btn btn-sm btn-warning" onclick="pauseCampaign()">
                    <i class="ki-duotone ki-pause fs-3"></i>
                    Pausar
                </button>
                <?php endif; ?>
                
                <?php if ($campaign['status'] === 'paused'): ?>
                <button class="btn btn-sm btn-primary" onclick="resumeCampaign()">
                    <i class="ki-duotone ki-play fs-3"></i>
                    Retomar
                </button>
                <?php endif; ?>
                
                <?php if (in_array($campaign['status'], ['running', 'scheduled', 'paused', 'draft'])): ?>
                <button class="btn btn-sm btn-info" onclick="forceSend()" title="Envia imediatamente para o próximo contato (ignora limites)">
                    <i class="ki-duotone ki-send fs-3"></i>
                    Forçar Disparo
                </button>
                <?php endif; ?>
                
                <?php if (in_array($campaign['status'], ['paused', 'completed', 'cancelled', 'draft'])): ?>
                <div class="dropdown">
                    <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="ki-duotone ki-arrow-circle fs-3"></i>
                        Reiniciar
                    </button>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="#" onclick="restartCampaign(false); return false;">
                                <i class="ki-duotone ki-arrows-loop fs-4 me-2 text-danger"></i>
                                <span class="fw-bold">Reiniciar Completamente</span>
                                <br><small class="text-muted">Apaga tudo e envia para todos os contatos novamente</small>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="restartCampaign(true); return false;">
                                <i class="ki-duotone ki-arrow-up-refraction fs-4 me-2 text-warning"></i>
                                <span class="fw-bold">Reenviar Apenas Falhas</span>
                                <br><small class="text-muted">Mantém enviados e reenvia apenas os que falharam</small>
                            </a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
                
                <button class="btn btn-sm btn-light" onclick="location.reload()">
                    <i class="ki-duotone ki-arrows-circle fs-3"></i>
                    Atualizar
                </button>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <!-- Status e Progress -->
            <div class="row g-5 g-xl-8 mb-5">
                <div class="col-xl-12">
                    <div class="card card-flush">
                        <div class="card-body d-flex align-items-center py-5">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="fs-4 fw-bold me-3">Status:</span>
                                    <?php 
                                    $statusBadges = [
                                        'draft' => '<span class="badge badge-light-secondary fs-5">Rascunho</span>',
                                        'scheduled' => '<span class="badge badge-light-info fs-5">Agendada</span>',
                                        'running' => '<span class="badge badge-light-primary fs-5"><span class="spinner-border spinner-border-sm me-2"></span>Em Execução</span>',
                                        'paused' => '<span class="badge badge-light-warning fs-5">Pausada</span>',
                                        'completed' => '<span class="badge badge-light-success fs-5">Concluída</span>',
                                        'cancelled' => '<span class="badge badge-light-danger fs-5">Cancelada</span>'
                                    ];
                                    echo $statusBadges[$campaign['status']] ?? '';
                                    ?>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="fs-6 text-muted me-3">Progresso:</span>
                                    <div class="progress h-8px w-300px me-3">
                                        <div class="progress-bar bg-primary" role="progressbar" 
                                             style="width: <?php echo $stats['progress']; ?>%"></div>
                                    </div>
                                    <span class="fw-bold fs-6"><?php echo number_format($stats['progress'], 1); ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Cards de Estatísticas -->
            <div class="row g-5 g-xl-8 mb-5">
                <!-- Total Contatos -->
                <div class="col-xl-3">
                    <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-xl-100" 
                         style="background-color: #F1416C;background-image:url('assets/media/patterns/vector-1.png')">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2"><?php echo $stats['total_contacts']; ?></span>
                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Total de Contatos</span>
                            </div>
                        </div>
                        <div class="card-body d-flex align-items-end pt-0">
                            <div class="d-flex align-items-center flex-column mt-3 w-100">
                                <div class="d-flex justify-content-between fw-bold fs-6 text-white opacity-75 w-100 mt-auto mb-2">
                                    <span>Progresso</span>
                                    <span><?php echo number_format($stats['progress'], 1); ?>%</span>
                                </div>
                                <div class="h-8px mx-3 w-100 bg-white bg-opacity-50 rounded">
                                    <div class="bg-white rounded h-8px" role="progressbar" 
                                         style="width: <?php echo $stats['progress']; ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Enviadas -->
                <div class="col-xl-3">
                    <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-xl-100" 
                         style="background-color: #7239EA;background-image:url('assets/media/patterns/vector-1.png')">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2"><?php echo $stats['total_sent']; ?></span>
                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Mensagens Enviadas</span>
                            </div>
                        </div>
                        <div class="card-body d-flex align-items-end pt-0">
                            <div class="d-flex align-items-center flex-column mt-3 w-100">
                                <div class="d-flex justify-content-between fw-bold fs-6 text-white opacity-75 w-100 mt-auto mb-2">
                                    <span>Taxa</span>
                                    <span><?php echo $stats['total_contacts'] > 0 ? number_format(($stats['total_sent'] / $stats['total_contacts']) * 100, 1) : 0; ?>%</span>
                                </div>
                                <div class="h-8px mx-3 w-100 bg-white bg-opacity-50 rounded">
                                    <div class="bg-white rounded h-8px" role="progressbar" 
                                         style="width: <?php echo $stats['total_contacts'] > 0 ? ($stats['total_sent'] / $stats['total_contacts']) * 100 : 0; ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Entregues -->
                <div class="col-xl-3">
                    <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-xl-100" 
                         style="background-color: #50CD89;background-image:url('assets/media/patterns/vector-1.png')">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2"><?php echo $stats['total_delivered']; ?></span>
                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Entregues</span>
                            </div>
                        </div>
                        <div class="card-body d-flex align-items-end pt-0">
                            <div class="d-flex align-items-center flex-column mt-3 w-100">
                                <div class="d-flex justify-content-between fw-bold fs-6 text-white opacity-75 w-100 mt-auto mb-2">
                                    <span>Taxa</span>
                                    <span><?php echo number_format($stats['delivery_rate'], 1); ?>%</span>
                                </div>
                                <div class="h-8px mx-3 w-100 bg-white bg-opacity-50 rounded">
                                    <div class="bg-white rounded h-8px" role="progressbar" 
                                         style="width: <?php echo $stats['delivery_rate']; ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Respondidas -->
                <div class="col-xl-3">
                    <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-xl-100" 
                         style="background-color: #009EF7;background-image:url('assets/media/patterns/vector-1.png')">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2"><?php echo $stats['total_replied']; ?></span>
                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Respostas</span>
                            </div>
                        </div>
                        <div class="card-body d-flex align-items-end pt-0">
                            <div class="d-flex align-items-center flex-column mt-3 w-100">
                                <div class="d-flex justify-content-between fw-bold fs-6 text-white opacity-75 w-100 mt-auto mb-2">
                                    <span>Taxa</span>
                                    <span><?php echo number_format($stats['reply_rate'], 1); ?>%</span>
                                </div>
                                <div class="h-8px mx-3 w-100 bg-white bg-opacity-50 rounded">
                                    <div class="bg-white rounded h-8px" role="progressbar" 
                                         style="width: <?php echo min($stats['reply_rate'], 100); ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Detalhes da Campanha e Lista -->
            <div class="row g-5 mb-5">
                <div class="col-xl-4">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">Detalhes da Campanha</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div class="mb-7">
                                <div class="d-flex flex-stack mb-3">
                                    <span class="fw-bold text-gray-600">Descrição:</span>
                                    <span class="text-gray-800"><?php echo htmlspecialchars($campaign['description'] ?? '-'); ?></span>
                                </div>
                                <div class="d-flex flex-stack mb-3">
                                    <span class="fw-bold text-gray-600">Lista de Contatos:</span>
                                    <?php if ($contactList): ?>
                                    <a href="/contact-lists/<?php echo $contactList['id']; ?>" class="text-primary fw-bold">
                                        <?php echo htmlspecialchars($contactList['name']); ?>
                                        <span class="badge badge-light-primary ms-2"><?php echo $contactList['total_contacts']; ?> contatos</span>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex flex-stack mb-3">
                                    <span class="fw-bold text-gray-600">Canal:</span>
                                    <span class="badge badge-light-primary"><?php echo strtoupper($campaign['channel']); ?></span>
                                </div>
                                <div class="d-flex flex-stack mb-3">
                                    <span class="fw-bold text-gray-600">Estratégia de Rotação:</span>
                                    <span class="text-gray-800"><?php echo ucfirst(str_replace('_', ' ', $campaign['rotation_strategy'])); ?></span>
                                </div>
                                <div class="d-flex flex-stack mb-3">
                                    <span class="fw-bold text-gray-600">Cadência:</span>
                                    <span class="text-gray-800"><?php echo $campaign['send_rate_per_minute']; ?> msgs/min</span>
                                </div>
                                <?php if (!empty($campaign['daily_limit'])): ?>
                                <div class="d-flex flex-stack mb-3">
                                    <span class="fw-bold text-gray-600">Limite Diário:</span>
                                    <span class="text-gray-800"><?php echo $campaign['daily_limit']; ?> msgs/dia</span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($campaign['ai_message_enabled'])): ?>
                                <div class="d-flex flex-stack mb-3">
                                    <span class="fw-bold text-gray-600">Mensagem com IA:</span>
                                    <span class="badge badge-light-success">Ativo</span>
                                </div>
                                <?php endif; ?>
                                <div class="d-flex flex-stack mb-3">
                                    <span class="fw-bold text-gray-600">Criada em:</span>
                                    <span class="text-gray-800"><?php echo date('d/m/Y H:i', strtotime($campaign['created_at'])); ?></span>
                                </div>
                                <?php if ($campaign['started_at']): ?>
                                <div class="d-flex flex-stack mb-3">
                                    <span class="fw-bold text-gray-600">Iniciada em:</span>
                                    <span class="text-gray-800"><?php echo date('d/m/Y H:i', strtotime($campaign['started_at'])); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">Funil de Conversão</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div id="funnel_chart" style="height: 280px;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">Status das Mensagens</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div class="d-flex flex-column">
                                <a href="?status_filter=pending" class="d-flex align-items-center mb-3 <?php echo $statusFilter === 'pending' ? 'fw-bold' : ''; ?>">
                                    <span class="bullet bullet-vertical h-40px bg-warning me-3"></span>
                                    <div class="flex-grow-1">
                                        <span class="text-gray-800 fs-6">Pendentes</span>
                                    </div>
                                    <span class="badge badge-light-warning fs-6"><?php echo $statusCounts['pending']; ?></span>
                                </a>
                                <a href="?status_filter=sent" class="d-flex align-items-center mb-3 <?php echo $statusFilter === 'sent' ? 'fw-bold' : ''; ?>">
                                    <span class="bullet bullet-vertical h-40px bg-info me-3"></span>
                                    <div class="flex-grow-1">
                                        <span class="text-gray-800 fs-6">Enviadas</span>
                                    </div>
                                    <span class="badge badge-light-info fs-6"><?php echo $statusCounts['sent']; ?></span>
                                </a>
                                <a href="?status_filter=delivered" class="d-flex align-items-center mb-3 <?php echo $statusFilter === 'delivered' ? 'fw-bold' : ''; ?>">
                                    <span class="bullet bullet-vertical h-40px bg-primary me-3"></span>
                                    <div class="flex-grow-1">
                                        <span class="text-gray-800 fs-6">Entregues</span>
                                    </div>
                                    <span class="badge badge-light-primary fs-6"><?php echo $statusCounts['delivered']; ?></span>
                                </a>
                                <a href="?status_filter=replied" class="d-flex align-items-center mb-3 <?php echo $statusFilter === 'replied' ? 'fw-bold' : ''; ?>">
                                    <span class="bullet bullet-vertical h-40px bg-success me-3"></span>
                                    <div class="flex-grow-1">
                                        <span class="text-gray-800 fs-6">Respondidas</span>
                                    </div>
                                    <span class="badge badge-light-success fs-6"><?php echo $statusCounts['replied']; ?></span>
                                </a>
                                <a href="?status_filter=failed" class="d-flex align-items-center mb-3 <?php echo $statusFilter === 'failed' ? 'fw-bold' : ''; ?>">
                                    <span class="bullet bullet-vertical h-40px bg-danger me-3"></span>
                                    <div class="flex-grow-1">
                                        <span class="text-gray-800 fs-6">Falharam</span>
                                    </div>
                                    <span class="badge badge-light-danger fs-6"><?php echo $statusCounts['failed']; ?></span>
                                </a>
                                <a href="?status_filter=skipped" class="d-flex align-items-center mb-3 <?php echo $statusFilter === 'skipped' ? 'fw-bold' : ''; ?>">
                                    <span class="bullet bullet-vertical h-40px bg-secondary me-3"></span>
                                    <div class="flex-grow-1">
                                        <span class="text-gray-800 fs-6">Puladas</span>
                                    </div>
                                    <span class="badge badge-light-secondary fs-6"><?php echo $statusCounts['skipped']; ?></span>
                                </a>
                                <?php if ($statusFilter): ?>
                                <a href="?" class="btn btn-sm btn-light mt-2">
                                    <i class="ki-duotone ki-cross fs-4"></i> Limpar Filtro
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Lista de Contatos/Mensagens -->
            <div class="row g-5 mb-5">
                <div class="col-xl-12">
                    <div class="card card-flush">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">
                                    Contatos da Campanha
                                    <?php if ($statusFilter): ?>
                                    <span class="badge badge-light-primary ms-2"><?php echo ucfirst($statusFilter); ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="text-gray-500 mt-1 fw-semibold fs-6">
                                    <?php echo $totalMessages; ?> mensagens 
                                    <?php if ($statusFilter): ?>(filtradas)<?php endif; ?>
                                </span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <?php if (empty($messages)): ?>
                            <div class="text-center py-10">
                                <i class="ki-duotone ki-message-text-2 fs-3x text-gray-400 mb-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <p class="text-gray-500 fs-5">
                                    <?php if ($statusFilter): ?>
                                    Nenhuma mensagem com status "<?php echo $statusFilter; ?>"
                                    <?php else: ?>
                                    Nenhuma mensagem preparada. Clique em "Iniciar Campanha" para preparar e enviar.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th class="min-w-200px">Contato</th>
                                            <th class="min-w-100px">Telefone</th>
                                            <th class="min-w-80px">Status</th>
                                            <th class="min-w-120px">Conta Envio</th>
                                            <th class="min-w-100px">Enviado em</th>
                                            <th class="min-w-150px">Detalhes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($messages as $msg): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="symbol symbol-40px me-3">
                                                        <?php if (!empty($msg['contact_avatar'])): ?>
                                                        <img src="<?php echo htmlspecialchars($msg['contact_avatar']); ?>" alt="">
                                                        <?php else: ?>
                                                        <div class="symbol-label fs-6 fw-bold bg-light-primary text-primary">
                                                            <?php echo strtoupper(substr($msg['contact_name'] ?? '?', 0, 1)); ?>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <span class="text-gray-900 fw-bold"><?php echo htmlspecialchars($msg['contact_name'] ?? 'Sem nome'); ?></span>
                                                        <?php if (!empty($msg['contact_email'])): ?>
                                                        <span class="text-muted d-block fs-7"><?php echo htmlspecialchars($msg['contact_email']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-gray-800"><?php echo htmlspecialchars($msg['contact_phone'] ?? '-'); ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $statusBadges = [
                                                    'pending' => '<span class="badge badge-light-warning">Pendente</span>',
                                                    'sent' => '<span class="badge badge-light-info">Enviada</span>',
                                                    'delivered' => '<span class="badge badge-light-primary">Entregue</span>',
                                                    'read' => '<span class="badge badge-light-success">Lida</span>',
                                                    'replied' => '<span class="badge badge-success">Respondida</span>',
                                                    'failed' => '<span class="badge badge-light-danger">Falhou</span>',
                                                    'skipped' => '<span class="badge badge-light-secondary">Pulada</span>',
                                                ];
                                                echo $statusBadges[$msg['status']] ?? '<span class="badge badge-light">' . $msg['status'] . '</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <span class="text-gray-600 fs-7"><?php echo htmlspecialchars($msg['account_name'] ?? '-'); ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($msg['sent_at'])): ?>
                                                <span class="text-gray-600 fs-7"><?php echo date('d/m/Y H:i', strtotime($msg['sent_at'])); ?></span>
                                                <?php else: ?>
                                                <span class="text-muted fs-7">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($msg['status'] === 'failed' && !empty($msg['error_message'])): ?>
                                                <span class="text-danger fs-7" title="<?php echo htmlspecialchars($msg['error_message']); ?>">
                                                    <i class="ki-duotone ki-information-5 text-danger fs-4">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                        <span class="path3"></span>
                                                    </i>
                                                    <?php echo htmlspecialchars(substr($msg['error_message'], 0, 30)); ?>...
                                                </span>
                                                <?php elseif ($msg['status'] === 'skipped' && !empty($msg['skip_reason'])): ?>
                                                <span class="text-muted fs-7">
                                                    <?php echo htmlspecialchars($msg['skip_reason']); ?>
                                                </span>
                                                <?php elseif (!empty($msg['replied_at'])): ?>
                                                <span class="text-success fs-7">
                                                    <i class="ki-duotone ki-check-circle text-success fs-4">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                    Respondeu em <?php echo date('d/m H:i', strtotime($msg['replied_at'])); ?>
                                                </span>
                                                <?php elseif (!empty($msg['read_at'])): ?>
                                                <span class="text-primary fs-7">Lida em <?php echo date('d/m H:i', strtotime($msg['read_at'])); ?></span>
                                                <?php elseif (!empty($msg['delivered_at'])): ?>
                                                <span class="text-muted fs-7">Entregue em <?php echo date('d/m H:i', strtotime($msg['delivered_at'])); ?></span>
                                                <?php else: ?>
                                                <span class="text-muted fs-7">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Paginação -->
                            <?php if ($totalPages > 1): ?>
                            <div class="d-flex justify-content-between align-items-center mt-5">
                                <div class="text-muted">
                                    Página <?php echo $currentPage; ?> de <?php echo $totalPages; ?>
                                </div>
                                <div class="d-flex gap-2">
                                    <?php if ($currentPage > 1): ?>
                                    <a href="?page=<?php echo $currentPage - 1; ?><?php echo $statusFilter ? '&status_filter=' . $statusFilter : ''; ?>" 
                                       class="btn btn-sm btn-light">
                                        <i class="ki-duotone ki-arrow-left fs-4"></i> Anterior
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($currentPage < $totalPages): ?>
                                    <a href="?page=<?php echo $currentPage + 1; ?><?php echo $statusFilter ? '&status_filter=' . $statusFilter : ''; ?>" 
                                       class="btn btn-sm btn-light">
                                        Próxima <i class="ki-duotone ki-arrow-right fs-4"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
const campaignId = <?php echo $campaign['id']; ?>;
const stats = <?php echo json_encode($stats); ?>;

// Funnel Chart
var funnelOptions = {
    series: [{
        name: 'Quantidade',
        data: [
            stats.total_contacts,
            stats.total_sent,
            stats.total_delivered,
            stats.total_read,
            stats.total_replied
        ]
    }],
    chart: {
        type: 'bar',
        height: 350
    },
    plotOptions: {
        bar: {
            borderRadius: 0,
            horizontal: true,
            barHeight: '80%',
            isFunnel: true
        }
    },
    dataLabels: {
        enabled: true,
        formatter: function(val, opt) {
            return opt.w.globals.labels[opt.dataPointIndex] + ':  ' + val;
        },
        dropShadow: {
            enabled: true
        }
    },
    xaxis: {
        categories: ['Total Contatos', 'Enviadas', 'Entregues', 'Lidas', 'Respondidas']
    },
    legend: {
        show: false
    },
    colors: ['#F1416C', '#7239EA', '#50CD89', '#FFC700', '#009EF7']
};

var funnelChart = new ApexCharts(document.querySelector("#funnel_chart"), funnelOptions);
funnelChart.render();

// Funções de controle
function prepareCampaign() {
    if (!confirm('Deseja preparar esta campanha? Isso irá criar as mensagens individuais.')) return;
    
    fetch(`/campaigns/${campaignId}/prepare`, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success(data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(data.message);
            }
        })
        .catch(err => toastr.error('Erro de rede'));
}

function startCampaign() {
    if (!confirm('Deseja iniciar esta campanha?')) return;
    
    fetch(`/campaigns/${campaignId}/start`, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success('Campanha iniciada!');
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(data.message);
            }
        })
        .catch(err => toastr.error('Erro de rede'));
}

function pauseCampaign() {
    if (!confirm('Deseja pausar esta campanha?')) return;
    
    fetch(`/campaigns/${campaignId}/pause`, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success('Campanha pausada!');
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(data.message);
            }
        })
        .catch(err => toastr.error('Erro de rede'));
}

function resumeCampaign() {
    if (!confirm('Deseja retomar esta campanha?')) return;
    
    fetch(`/campaigns/${campaignId}/resume`, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success('Campanha retomada!');
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(data.message);
            }
        })
        .catch(err => toastr.error('Erro de rede'));
}

function forceSend() {
    if (!confirm('Enviar imediatamente para o próximo contato da lista?\n\nIsso ignora limites e intervalos configurados.')) return;
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
    btn.disabled = true;
    
    fetch(`/campaigns/${campaignId}/force-send`, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success(data.message);
                setTimeout(() => location.reload(), 2000);
            } else {
                toastr.error(data.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(err => {
            toastr.error('Erro de rede');
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

function restartCampaign(keepSent) {
    const msg = keepSent 
        ? 'Deseja reenviar apenas as mensagens que falharam?\n\nAs mensagens já enviadas serão mantidas.'
        : 'Deseja reiniciar completamente a campanha?\n\n⚠️ ATENÇÃO: Isso apagará TODO o histórico e enviará para TODOS os contatos novamente!';
    
    if (!confirm(msg)) return;
    
    // Segunda confirmação para reinício completo
    if (!keepSent) {
        if (!confirm('TEM CERTEZA? Esta ação não pode ser desfeita!')) return;
    }
    
    fetch(`/campaigns/${campaignId}/restart`, { 
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `keep_sent=${keepSent ? '1' : '0'}`
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success(data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(data.message);
            }
        })
        .catch(err => toastr.error('Erro de rede'));
}

// Auto-refresh se estiver rodando
<?php if ($campaign['status'] === 'running'): ?>
setInterval(() => location.reload(), 30000); // 30 segundos
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/metronic/app.php';
?>
