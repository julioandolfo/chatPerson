<?php
$layout = 'layouts.metronic.app';
$title = 'WhatsApp CoEx - API Oficial + App';

$metaConfig = $metaConfig ?? [];
$phones = $phones ?? [];
$tokens = $tokens ?? [];
$activeTab = $activeTab ?? 'overview';
$templates = $templates ?? [];
$templateStats = $templateStats ?? [];
$selectedWabaId = $selectedWabaId ?? '';
$templateFilter = $templateFilter ?? 'all';

// Obter WABAs únicos dos phones
$wabas = [];
foreach ($phones as $phone) {
    if (!empty($phone['waba_id']) && !isset($wabas[$phone['waba_id']])) {
        $wabas[$phone['waba_id']] = [
            'id' => $phone['waba_id'],
            'name' => $phone['verified_name'] ?? 'WABA ' . $phone['waba_id'],
        ];
    }
}

ob_start();
?>

<!--begin::Page Header-->
<div class="card mb-5">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="#25D366" viewBox="0 0 24 24" class="me-2">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
                WhatsApp CoEx <span class="badge badge-light-success ms-2 fs-8">API Oficial</span>
            </h3>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2">
                <?php if (!empty($metaConfig['app_id'])): ?>
                <button class="btn btn-sm btn-success" onclick="launchEmbeddedSignup()">
                    <i class="ki-duotone ki-plus fs-3"></i>
                    Conectar Número (CoEx)
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-body pt-0 pb-3">
        <p class="text-muted mb-0">
            Gerencie seus números WhatsApp via API Oficial com Coexistence - use o app e a API simultaneamente no mesmo número.
        </p>
    </div>
</div>

<!--begin::Tabs Navigation-->
<ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x mb-5 fs-6">
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'overview' ? 'active' : '' ?>" data-bs-toggle="tab" href="#tab_overview">
            <i class="ki-duotone ki-home fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
            Visão Geral
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'templates' ? 'active' : '' ?>" data-bs-toggle="tab" href="#tab_templates">
            <i class="ki-duotone ki-document fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
            Templates
            <?php if (!empty($templateStats['pending'])): ?>
                <span class="badge badge-warning ms-1"><?= $templateStats['pending'] ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'config' ? 'active' : '' ?>" data-bs-toggle="tab" href="#tab_config">
            <i class="ki-duotone ki-setting-2 fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
            Configurações
        </a>
    </li>
</ul>

<!--begin::Tab Content-->
<div class="tab-content">

    <!-- ==================== TAB: VISÃO GERAL ==================== -->
    <div class="tab-pane fade <?= $activeTab === 'overview' ? 'show active' : '' ?>" id="tab_overview">
        
        <?php if (empty($metaConfig['app_id'])): ?>
        <!--begin::Alerta: sem credenciais-->
        <div class="card mb-5">
            <div class="card-body">
                <div class="alert alert-warning d-flex align-items-center p-5 mb-0">
                    <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                    <div>
                        <h4 class="mb-1 text-warning">Credenciais Meta não configuradas</h4>
                        <span>Configure as credenciais do App Meta na aba <strong>Configurações</strong> ou na página de 
                            <a href="<?= \App\Helpers\Url::to('/integrations/meta') ?>">Integrações Meta</a>.
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!--begin::Números Conectados-->
        <div class="card mb-5">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h3 class="fw-bold m-0">
                        <i class="ki-duotone ki-phone fs-2 me-2"><span class="path1"></span><span class="path2"></span></i>
                        Números WhatsApp (<?= count($phones) ?>)
                    </h3>
                </div>
            </div>
            <div class="card-body pt-0">
                <?php if (empty($phones)): ?>
                    <div class="text-center py-10">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="#25D366" viewBox="0 0 24 24" class="mb-5" style="opacity: 0.3;">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                        </svg>
                        <p class="text-muted fs-5 mb-3">Nenhum número WhatsApp conectado via CoEx</p>
                        <p class="text-muted fs-7 mb-5">Clique em "Conectar Número (CoEx)" para vincular seu WhatsApp Business</p>
                        <?php if (!empty($metaConfig['app_id'])): ?>
                        <button class="btn btn-success" onclick="launchEmbeddedSignup()">
                            <i class="ki-duotone ki-plus fs-3"></i>
                            Conectar Número
                        </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th class="min-w-200px">Número</th>
                                    <th class="min-w-150px">Nome Verificado</th>
                                    <th class="min-w-100px">Qualidade</th>
                                    <th class="min-w-120px">CoEx</th>
                                    <th class="min-w-100px">Conexão</th>
                                    <th class="min-w-100px text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($phones as $phone): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="text-dark fw-bold fs-6"><?= htmlspecialchars($phone['display_phone_number'] ?? $phone['phone_number'] ?? '-') ?></span>
                                            <span class="text-muted fw-semibold d-block fs-7">WABA: <?= htmlspecialchars($phone['waba_id'] ?? '-') ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-dark fw-bold"><?= htmlspecialchars($phone['verified_name'] ?? '-') ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $qColors = ['GREEN' => 'success', 'YELLOW' => 'warning', 'RED' => 'danger', 'UNKNOWN' => 'secondary'];
                                        $qColor = $qColors[$phone['quality_rating'] ?? 'UNKNOWN'] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-light-<?= $qColor ?>"><?= $phone['quality_rating'] ?? 'N/A' ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($phone['coex_enabled'])): ?>
                                            <?php
                                            $coexColors = [
                                                'inactive' => 'secondary',
                                                'onboarding' => 'info',
                                                'syncing' => 'warning',
                                                'active' => 'success',
                                                'error' => 'danger',
                                            ];
                                            $coexColor = $coexColors[$phone['coex_status'] ?? 'inactive'] ?? 'secondary';
                                            $coexLabels = [
                                                'inactive' => 'Inativo',
                                                'onboarding' => 'Configurando',
                                                'syncing' => 'Sincronizando',
                                                'active' => 'Ativo',
                                                'error' => 'Erro',
                                            ];
                                            $coexLabel = $coexLabels[$phone['coex_status'] ?? 'inactive'] ?? 'Desconhecido';
                                            ?>
                                            <span class="badge badge-light-<?= $coexColor ?>">
                                                <i class="ki-duotone ki-<?= $phone['coex_status'] === 'active' ? 'shield-tick' : 'loading' ?> fs-7 me-1"><span class="path1"></span><span class="path2"></span></i>
                                                <?= $coexLabel ?>
                                            </span>
                                            <?php if (!empty($phone['coex_history_synced'])): ?>
                                                <span class="badge badge-light-info ms-1" title="Histórico sincronizado">
                                                    <i class="ki-duotone ki-time fs-7"><span class="path1"></span><span class="path2"></span></i>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-light-secondary">Sem CoEx</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($phone['has_valid_token']) && !empty($phone['is_connected'])): ?>
                                            <span class="badge badge-light-success">Conectado</span>
                                        <?php else: ?>
                                            <span class="badge badge-light-danger">Desconectado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <button class="btn btn-icon btn-light-info btn-sm" 
                                                    onclick="viewCoexStatus(<?= $phone['id'] ?>)"
                                                    title="Ver Detalhes CoEx">
                                                <i class="ki-duotone ki-eye fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                            </button>
                                            <button class="btn btn-icon btn-light-primary btn-sm" 
                                                    onclick="syncPhone(<?= $phone['id'] ?>)"
                                                    title="Sincronizar">
                                                <i class="ki-duotone ki-arrows-circle fs-3"><span class="path1"></span><span class="path2"></span></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!--begin::Info CoEx-->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h3 class="fw-bold m-0">
                        <i class="ki-duotone ki-information-4 fs-2 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        Como funciona o CoEx
                    </h3>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="row g-5">
                    <div class="col-md-4">
                        <div class="border rounded p-5 h-100">
                            <div class="d-flex align-items-center mb-3">
                                <span class="bullet bullet-vertical h-40px bg-success me-3"></span>
                                <h5 class="fw-bold mb-0">App WhatsApp Business</h5>
                            </div>
                            <p class="text-muted fs-7 mb-0">
                                Continue usando o app no celular para conversas pessoais, atendimento VIP e comunicação da diretoria. Sem restrições de janela de 24h.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-5 h-100">
                            <div class="d-flex align-items-center mb-3">
                                <span class="bullet bullet-vertical h-40px bg-primary me-3"></span>
                                <h5 class="fw-bold mb-0">API Cloud (Este Painel)</h5>
                            </div>
                            <p class="text-muted fs-7 mb-0">
                                Use automações, chatbots, múltiplos atendentes e campanhas. Templates obrigatórios fora da janela de 24h.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-5 h-100">
                            <div class="d-flex align-items-center mb-3">
                                <span class="bullet bullet-vertical h-40px bg-info me-3"></span>
                                <h5 class="fw-bold mb-0">Sincronização</h5>
                            </div>
                            <p class="text-muted fs-7 mb-0">
                                Mensagens aparecem em ambos os ambientes. Histórico de até 6 meses importado automaticamente na ativação.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== TAB: TEMPLATES ==================== -->
    <div class="tab-pane fade <?= $activeTab === 'templates' ? 'show active' : '' ?>" id="tab_templates">
        
        <!--begin::Templates Header-->
        <div class="card mb-5">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h3 class="fw-bold m-0">
                        <i class="ki-duotone ki-document fs-2 me-2"><span class="path1"></span><span class="path2"></span></i>
                        Templates de Mensagem
                    </h3>
                </div>
                <div class="card-toolbar">
                    <div class="d-flex align-items-center gap-2">
                        <?php if (!empty($wabas)): ?>
                        <!-- Seletor de WABA -->
                        <select id="wabaSelector" class="form-select form-select-sm form-select-solid w-200px" onchange="changeWaba(this.value)">
                            <?php foreach ($wabas as $waba): ?>
                                <option value="<?= htmlspecialchars($waba['id']) ?>" <?= $selectedWabaId === $waba['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($waba['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                        
                        <button class="btn btn-sm btn-light-primary" onclick="syncTemplates()">
                            <i class="ki-duotone ki-arrows-circle fs-3"><span class="path1"></span><span class="path2"></span></i>
                            Sincronizar da Meta
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="showCreateTemplate()">
                            <i class="ki-duotone ki-plus fs-3"></i>
                            Novo Template
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Estatísticas -->
            <?php if (!empty($templateStats)): ?>
            <div class="card-body pt-0 pb-3">
                <div class="d-flex gap-5 flex-wrap">
                    <div class="border rounded px-4 py-2">
                        <span class="text-muted fs-8 d-block">Total</span>
                        <span class="fw-bold fs-5"><?= $templateStats['total'] ?? 0 ?></span>
                    </div>
                    <div class="border rounded px-4 py-2">
                        <span class="text-muted fs-8 d-block">Aprovados</span>
                        <span class="fw-bold fs-5 text-success"><?= $templateStats['approved'] ?? 0 ?></span>
                    </div>
                    <div class="border rounded px-4 py-2">
                        <span class="text-muted fs-8 d-block">Pendentes</span>
                        <span class="fw-bold fs-5 text-warning"><?= $templateStats['pending'] ?? 0 ?></span>
                    </div>
                    <div class="border rounded px-4 py-2">
                        <span class="text-muted fs-8 d-block">Rejeitados</span>
                        <span class="fw-bold fs-5 text-danger"><?= $templateStats['rejected'] ?? 0 ?></span>
                    </div>
                    <div class="border rounded px-4 py-2">
                        <span class="text-muted fs-8 d-block">Rascunhos</span>
                        <span class="fw-bold fs-5 text-info"><?= $templateStats['drafts'] ?? 0 ?></span>
                    </div>
                    <div class="border rounded px-4 py-2">
                        <span class="text-muted fs-8 d-block">Enviados</span>
                        <span class="fw-bold fs-5"><?= number_format($templateStats['total_sent'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!--begin::Filtros-->
        <div class="d-flex gap-2 mb-5">
            <?php 
            $filters = [
                'all' => ['label' => 'Todos', 'icon' => 'ki-element-11'],
                'approved' => ['label' => 'Aprovados', 'icon' => 'ki-shield-tick'],
                'pending' => ['label' => 'Pendentes', 'icon' => 'ki-time'],
                'rejected' => ['label' => 'Rejeitados', 'icon' => 'ki-cross-circle'],
                'drafts' => ['label' => 'Rascunhos', 'icon' => 'ki-pencil'],
            ];
            foreach ($filters as $key => $f): ?>
                <button class="btn btn-sm <?= $templateFilter === $key ? 'btn-primary' : 'btn-light' ?>" 
                        onclick="filterTemplates('<?= $key ?>')">
                    <i class="ki-duotone <?= $f['icon'] ?> fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                    <?= $f['label'] ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <!--begin::Templates List-->
        <div class="card">
            <div class="card-body p-0">
                <?php if (empty($templates)): ?>
                    <div class="text-center py-10">
                        <i class="ki-duotone ki-document fs-3x text-muted mb-5"><span class="path1"></span><span class="path2"></span></i>
                        <p class="text-muted fs-5 mb-3">Nenhum template encontrado</p>
                        <p class="text-muted fs-7 mb-5">Crie um novo template ou sincronize da Meta</p>
                        <div class="d-flex justify-content-center gap-3">
                            <button class="btn btn-light-primary btn-sm" onclick="syncTemplates()">
                                <i class="ki-duotone ki-arrows-circle fs-3"><span class="path1"></span><span class="path2"></span></i>
                                Sincronizar da Meta
                            </button>
                            <button class="btn btn-primary btn-sm" onclick="showCreateTemplate()">
                                <i class="ki-duotone ki-plus fs-3"></i>
                                Novo Template
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-5 gy-3 mb-0">
                            <thead>
                                <tr class="fw-bold text-muted bg-light">
                                    <th class="min-w-200px ps-5">Template</th>
                                    <th class="min-w-100px">Categoria</th>
                                    <th class="min-w-80px">Idioma</th>
                                    <th class="min-w-100px">Status</th>
                                    <th class="min-w-80px">Qualidade</th>
                                    <th class="min-w-100px">Envios</th>
                                    <th class="min-w-120px text-end pe-5">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates as $tpl): ?>
                                <tr>
                                    <td class="ps-5">
                                        <div class="d-flex flex-column">
                                            <span class="text-dark fw-bold fs-6"><?= htmlspecialchars($tpl['display_name'] ?? $tpl['name']) ?></span>
                                            <span class="text-muted fw-semibold d-block fs-8 text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($tpl['body_text'] ?? '') ?>">
                                                <?= htmlspecialchars(mb_substr($tpl['body_text'] ?? '', 0, 80)) ?><?= mb_strlen($tpl['body_text'] ?? '') > 80 ? '...' : '' ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $catColors = ['MARKETING' => 'info', 'UTILITY' => 'primary', 'AUTHENTICATION' => 'warning'];
                                        $catColor = $catColors[$tpl['category'] ?? ''] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-light-<?= $catColor ?>"><?= $tpl['category'] ?? 'N/A' ?></span>
                                    </td>
                                    <td>
                                        <span class="text-muted fw-semibold"><?= htmlspecialchars($tpl['language'] ?? 'pt_BR') ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $sColors = [
                                            'APPROVED' => 'success', 'PENDING' => 'warning', 'REJECTED' => 'danger',
                                            'PAUSED' => 'secondary', 'DISABLED' => 'secondary', 'DRAFT' => 'info'
                                        ];
                                        $sLabels = [
                                            'APPROVED' => 'Aprovado', 'PENDING' => 'Pendente', 'REJECTED' => 'Rejeitado',
                                            'PAUSED' => 'Pausado', 'DISABLED' => 'Desabilitado', 'DRAFT' => 'Rascunho'
                                        ];
                                        $sColor = $sColors[$tpl['status'] ?? ''] ?? 'secondary';
                                        $sLabel = $sLabels[$tpl['status'] ?? ''] ?? $tpl['status'];
                                        ?>
                                        <span class="badge badge-light-<?= $sColor ?>"><?= $sLabel ?></span>
                                        <?php if ($tpl['status'] === 'REJECTED' && !empty($tpl['rejection_reason'])): ?>
                                            <i class="ki-duotone ki-information-4 fs-5 text-danger ms-1 cursor-pointer" 
                                               title="<?= htmlspecialchars($tpl['rejection_reason']) ?>"
                                               onclick="showRejectionReason(<?= $tpl['id'] ?>)">
                                                <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                            </i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($tpl['quality_score'])): ?>
                                            <?php $qualColor = $qColors[$tpl['quality_score']] ?? 'secondary'; ?>
                                            <span class="badge badge-light-<?= $qualColor ?>"><?= $tpl['quality_score'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="text-dark fw-bold"><?= number_format($tpl['sent_count'] ?? 0) ?></span>
                                        <span class="text-muted d-block fs-8">
                                            <?= number_format($tpl['delivered_count'] ?? 0) ?> entregues
                                        </span>
                                    </td>
                                    <td class="text-end pe-5">
                                        <div class="d-flex justify-content-end gap-1">
                                            <!-- Ver detalhes -->
                                            <button class="btn btn-icon btn-light-info btn-sm" 
                                                    onclick="viewTemplate(<?= $tpl['id'] ?>)" title="Ver Detalhes">
                                                <i class="ki-duotone ki-eye fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                            </button>
                                            
                                            <?php if ($tpl['status'] === 'DRAFT'): ?>
                                            <!-- Enviar para aprovação -->
                                            <button class="btn btn-icon btn-light-success btn-sm" 
                                                    onclick="submitTemplate(<?= $tpl['id'] ?>)" title="Enviar para Aprovação">
                                                <i class="ki-duotone ki-send fs-3"><span class="path1"></span><span class="path2"></span></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($tpl['status'] === 'PENDING'): ?>
                                            <!-- Verificar status -->
                                            <button class="btn btn-icon btn-light-warning btn-sm" 
                                                    onclick="checkTemplateStatus(<?= $tpl['id'] ?>)" title="Verificar Status">
                                                <i class="ki-duotone ki-arrows-circle fs-3"><span class="path1"></span><span class="path2"></span></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($tpl['status'] === 'REJECTED'): ?>
                                            <!-- Reenviar -->
                                            <button class="btn btn-icon btn-light-primary btn-sm" 
                                                    onclick="submitTemplate(<?= $tpl['id'] ?>)" title="Reenviar para Aprovação">
                                                <i class="ki-duotone ki-arrows-loop fs-3"><span class="path1"></span><span class="path2"></span></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <!-- Excluir -->
                                            <button class="btn btn-icon btn-light-danger btn-sm" 
                                                    onclick="deleteTemplate(<?= $tpl['id'] ?>, '<?= htmlspecialchars($tpl['name']) ?>')" title="Excluir">
                                                <i class="ki-duotone ki-trash fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== TAB: CONFIGURAÇÕES ==================== -->
    <div class="tab-pane fade <?= $activeTab === 'config' ? 'show active' : '' ?>" id="tab_config">
        
        <!--begin::Info sobre Embedded Signup-->
        <div class="card mb-5">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h3 class="fw-bold m-0">
                        <i class="ki-duotone ki-setting-2 fs-2 me-2"><span class="path1"></span><span class="path2"></span></i>
                        Configuração do Embedded Signup
                    </h3>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="alert alert-info d-flex align-items-start p-5 mb-5">
                    <i class="ki-duotone ki-information-4 fs-2hx text-info me-4 mt-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                    <div>
                        <h4 class="mb-2 text-info">Requisitos para WhatsApp CoEx</h4>
                        <ol class="mb-0 fs-7">
                            <li class="mb-2">Ter um <strong>App Meta</strong> configurado em <a href="https://developers.facebook.com/apps/" target="_blank">developers.facebook.com</a></li>
                            <li class="mb-2">Ser <strong>Tech Provider</strong> ou <strong>Solution Partner</strong> da Meta</li>
                            <li class="mb-2">Ter o <strong>Facebook Login for Business</strong> habilitado no App</li>
                            <li class="mb-2">Configurar o <strong>Webhook URL</strong> no App Meta</li>
                            <li class="mb-2">Subscrever os campos: <code>messages</code>, <code>smb_message_echoes</code>, <code>smb_app_state_sync</code>, <code>business_capability_update</code>, <code>account_update</code></li>
                        </ol>
                    </div>
                </div>
                
                <div class="row g-5">
                    <div class="col-md-6">
                        <label class="form-label">Webhook URL (para Meta)</label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-solid" id="coex_webhook_url"
                                   value="<?= htmlspecialchars(\App\Helpers\Url::fullUrl('/webhooks/meta')) ?>" 
                                   readonly onclick="this.select()">
                            <button class="btn btn-light-primary" type="button" onclick="copyField('coex_webhook_url')">
                                <i class="ki-duotone ki-copy fs-3"></i> Copiar
                            </button>
                        </div>
                        <div class="form-text">Configure no App Meta > WhatsApp > Configuração > Webhook URL</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Verify Token</label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-solid" id="coex_verify_token"
                                   value="<?= htmlspecialchars($metaConfig['webhook_verify_token'] ?? '') ?>" 
                                   readonly onclick="this.select()">
                            <button class="btn btn-light-primary" type="button" onclick="copyField('coex_verify_token')">
                                <i class="ki-duotone ki-copy fs-3"></i> Copiar
                            </button>
                        </div>
                        <div class="form-text">Use este token no campo "Verificar Token" do webhook</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Callback URL (Embedded Signup)</label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-solid" id="coex_callback_url"
                                   value="<?= htmlspecialchars(\App\Helpers\Url::fullUrl('/integrations/meta/oauth/callback')) ?>" 
                                   readonly onclick="this.select()">
                            <button class="btn btn-light-primary" type="button" onclick="copyField('coex_callback_url')">
                                <i class="ki-duotone ki-copy fs-3"></i> Copiar
                            </button>
                        </div>
                        <div class="form-text">Configure em Facebook Login > Settings > Valid OAuth Redirect URIs</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">App ID</label>
                        <input type="text" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($metaConfig['app_id'] ?? 'Não configurado') ?>" readonly>
                        <div class="form-text">
                            Configure na página <a href="<?= \App\Helpers\Url::to('/integrations/meta') ?>">Integrações Meta</a>
                        </div>
                    </div>
                </div>
                
                <!--begin::Webhook Fields Info-->
                <div class="separator my-7"></div>
                <h5 class="fw-bold mb-4">Campos de Webhook para Subscrever</h5>
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-2">
                        <thead>
                            <tr class="fw-bold text-muted fs-7">
                                <th>Campo</th>
                                <th>Descrição</th>
                                <th>Uso no CoEx</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>messages</code></td>
                                <td class="text-muted fs-7">Mensagens recebidas via API</td>
                                <td><span class="badge badge-light-success">Obrigatório</span></td>
                            </tr>
                            <tr>
                                <td><code>smb_message_echoes</code></td>
                                <td class="text-muted fs-7">Eco das mensagens enviadas pelo app WhatsApp Business</td>
                                <td><span class="badge badge-light-success">Obrigatório (CoEx)</span></td>
                            </tr>
                            <tr>
                                <td><code>smb_app_state_sync</code></td>
                                <td class="text-muted fs-7">Sincronização de estado (leitura, etc.) entre app e API</td>
                                <td><span class="badge badge-light-success">Obrigatório (CoEx)</span></td>
                            </tr>
                            <tr>
                                <td><code>business_capability_update</code></td>
                                <td class="text-muted fs-7">Atualização de capacidades quando CoEx é ativado</td>
                                <td><span class="badge badge-light-success">Obrigatório (CoEx)</span></td>
                            </tr>
                            <tr>
                                <td><code>account_update</code></td>
                                <td class="text-muted fs-7">Notificação quando Embedded Signup é concluído</td>
                                <td><span class="badge badge-light-warning">Recomendado</span></td>
                            </tr>
                            <tr>
                                <td><code>message_template_status_update</code></td>
                                <td class="text-muted fs-7">Mudanças de status de templates</td>
                                <td><span class="badge badge-light-warning">Recomendado</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
<!--end::Tab Content-->

<!-- ==================== MODAL: CRIAR TEMPLATE ==================== -->
<div class="modal fade" id="createTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fw-bold">
                    <i class="ki-duotone ki-plus-square fs-2 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                    Novo Template de Mensagem
                </h3>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <form id="createTemplateForm">
                <div class="modal-body">
                    <div class="row g-5">
                        <!-- WABA -->
                        <div class="col-md-6">
                            <label class="form-label required">Conta WhatsApp (WABA)</label>
                            <select name="waba_id" class="form-select form-select-solid" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($wabas as $waba): ?>
                                    <option value="<?= htmlspecialchars($waba['id']) ?>"><?= htmlspecialchars($waba['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Categoria -->
                        <div class="col-md-6">
                            <label class="form-label required">Categoria</label>
                            <select name="category" class="form-select form-select-solid" required>
                                <option value="UTILITY">Utilidade (notificações, atualizações)</option>
                                <option value="MARKETING">Marketing (promoções, ofertas)</option>
                                <option value="AUTHENTICATION">Autenticação (códigos, verificação)</option>
                            </select>
                            <div class="form-text">A categoria afeta o custo e as regras de envio</div>
                        </div>
                        
                        <!-- Nome -->
                        <div class="col-md-6">
                            <label class="form-label required">Nome do Template</label>
                            <input type="text" name="name" class="form-control form-control-solid" 
                                   placeholder="ex: pedido_confirmado" pattern="[a-z0-9_]+" required>
                            <div class="form-text">Apenas letras minúsculas, números e underscores</div>
                        </div>
                        
                        <!-- Idioma -->
                        <div class="col-md-6">
                            <label class="form-label required">Idioma</label>
                            <select name="language" class="form-select form-select-solid" required>
                                <option value="pt_BR" selected>Português (BR)</option>
                                <option value="en_US">English (US)</option>
                                <option value="es">Español</option>
                            </select>
                        </div>
                        
                        <!-- Header -->
                        <div class="col-12">
                            <label class="form-label">Cabeçalho (opcional)</label>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <select name="header_type" class="form-select form-select-solid" onchange="toggleHeaderFields(this.value)">
                                        <option value="NONE">Sem cabeçalho</option>
                                        <option value="TEXT">Texto</option>
                                        <option value="IMAGE">Imagem</option>
                                        <option value="VIDEO">Vídeo</option>
                                        <option value="DOCUMENT">Documento</option>
                                    </select>
                                </div>
                                <div class="col-md-8" id="headerTextField" style="display:none;">
                                    <input type="text" name="header_text" class="form-control form-control-solid" 
                                           placeholder="Texto do cabeçalho" maxlength="60">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Body -->
                        <div class="col-12">
                            <label class="form-label required">Corpo da Mensagem</label>
                            <textarea name="body_text" class="form-control form-control-solid" rows="5" required
                                      placeholder="Digite o texto da mensagem. Use {{1}}, {{2}} etc. para variáveis.&#10;&#10;Ex: Olá {{1}}, seu pedido #{{2}} foi confirmado!"
                                      oninput="updatePreview()"></textarea>
                            <div class="form-text">Use <code>{{1}}</code>, <code>{{2}}</code> etc. para variáveis dinâmicas (máx. 1024 caracteres)</div>
                        </div>
                        
                        <!-- Footer -->
                        <div class="col-12">
                            <label class="form-label">Rodapé (opcional)</label>
                            <input type="text" name="footer_text" class="form-control form-control-solid" 
                                   placeholder="Ex: Enviado por Sua Empresa" maxlength="60">
                        </div>
                        
                        <!-- Botões -->
                        <div class="col-12">
                            <label class="form-label">Botões (opcional, máx. 3)</label>
                            <div id="buttonsContainer">
                                <!-- Botões serão adicionados dinamicamente -->
                            </div>
                            <button type="button" class="btn btn-sm btn-light-primary mt-2" onclick="addButton()">
                                <i class="ki-duotone ki-plus fs-4"></i> Adicionar Botão
                            </button>
                        </div>
                        
                        <!-- Preview -->
                        <div class="col-12">
                            <label class="form-label">Pré-visualização</label>
                            <div class="bg-light rounded p-5" id="templatePreview">
                                <div class="d-flex justify-content-end">
                                    <div class="bg-success bg-opacity-15 rounded p-3" style="max-width: 350px;">
                                        <div id="previewHeader" class="fw-bold mb-1" style="display:none;"></div>
                                        <div id="previewBody" class="text-dark fs-7">A mensagem aparecerá aqui...</div>
                                        <div id="previewFooter" class="text-muted fs-8 mt-1" style="display:none;"></div>
                                        <div id="previewButtons" class="mt-2" style="display:none;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ki-duotone ki-check fs-3"></i>
                        Salvar Rascunho
                    </button>
                    <button type="button" class="btn btn-success" onclick="saveAndSubmit()">
                        <i class="ki-duotone ki-send fs-3"><span class="path1"></span><span class="path2"></span></i>
                        Salvar e Enviar para Aprovação
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== MODAL: VER TEMPLATE ==================== -->
<div class="modal fade" id="viewTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fw-bold">Detalhes do Template</h3>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body" id="viewTemplateContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ==================== EMBEDDED SIGNUP ====================

function launchEmbeddedSignup() {
    const appId = '<?= htmlspecialchars($metaConfig['app_id'] ?? '') ?>';
    
    if (!appId) {
        Swal.fire('Erro', 'Configure o App ID da Meta nas configurações primeiro.', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Conectar WhatsApp via CoEx',
        html: `
            <div class="text-start">
                <p class="text-muted mb-3">O Embedded Signup da Meta será aberto para vincular seu número WhatsApp Business.</p>
                <div class="alert alert-info p-3 fs-7">
                    <strong>O que acontecerá:</strong>
                    <ol class="mb-0 mt-2">
                        <li>Uma janela da Meta será aberta</li>
                        <li>Faça login com sua conta Meta/Facebook</li>
                        <li>Selecione ou crie uma conta WhatsApp Business</li>
                        <li>Vincule seu número (escaneie o QR Code no app)</li>
                        <li>O CoEx será ativado automaticamente</li>
                    </ol>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Iniciar Embedded Signup',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#25D366',
    }).then((result) => {
        if (result.isConfirmed) {
            startEmbeddedSignup(appId);
        }
    });
}

function startEmbeddedSignup(appId) {
    // Carregar Facebook SDK se não carregado
    if (typeof FB === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://connect.facebook.net/pt_BR/sdk.js';
        script.async = true;
        script.defer = true;
        script.crossOrigin = 'anonymous';
        script.onload = () => {
            FB.init({
                appId: appId,
                autoLogAppEvents: true,
                xfbml: true,
                version: 'v21.0'
            });
            doEmbeddedSignup();
        };
        document.body.appendChild(script);
    } else {
        doEmbeddedSignup();
    }
}

function doEmbeddedSignup() {
    FB.login(function(response) {
        if (response.authResponse) {
            const code = response.authResponse.code;
            
            Swal.fire({
                title: 'Processando...',
                text: 'Registrando número WhatsApp CoEx',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            fetch('<?= \App\Helpers\Url::to('/integrations/whatsapp-coex/embedded-signup') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ code: code, session_info: response })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', data.message || 'Número registrado com sucesso', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Erro', data.error || 'Erro ao registrar', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Erro', 'Erro na requisição: ' + err.message, 'error');
            });
        } else {
            Swal.fire('Cancelado', 'O processo de login foi cancelado', 'info');
        }
    }, {
        config_id: '', // Se tiver config_id do Embedded Signup
        response_type: 'code',
        override_default_response_type: true,
        extras: {
            setup: {
                // CoEx: smbiz params
                smbiz: {
                    coexistence: true // Flag CoEx!
                }
            }
        }
    });
}

// ==================== TEMPLATES ====================

function showCreateTemplate() {
    document.getElementById('createTemplateForm').reset();
    document.getElementById('buttonsContainer').innerHTML = '';
    toggleHeaderFields('NONE');
    updatePreview();
    new bootstrap.Modal(document.getElementById('createTemplateModal')).show();
}

function toggleHeaderFields(type) {
    const textField = document.getElementById('headerTextField');
    textField.style.display = type === 'TEXT' ? 'block' : 'none';
}

let buttonCount = 0;
function addButton() {
    if (buttonCount >= 3) {
        Swal.fire('Limite', 'Máximo de 3 botões permitidos', 'warning');
        return;
    }
    
    buttonCount++;
    const container = document.getElementById('buttonsContainer');
    const html = `
        <div class="row g-3 mb-2 button-row" data-index="${buttonCount}">
            <div class="col-md-3">
                <select name="buttons[${buttonCount}][type]" class="form-select form-select-sm form-select-solid" onchange="toggleButtonFields(this, ${buttonCount})">
                    <option value="QUICK_REPLY">Resposta Rápida</option>
                    <option value="URL">URL</option>
                    <option value="PHONE_NUMBER">Telefone</option>
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" name="buttons[${buttonCount}][text]" class="form-control form-control-sm form-control-solid" placeholder="Texto do botão" maxlength="25">
            </div>
            <div class="col-md-4 btn-extra-field" id="btnExtra${buttonCount}" style="display:none;">
                <input type="text" name="buttons[${buttonCount}][url]" class="form-control form-control-sm form-control-solid" placeholder="URL ou telefone">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-icon btn-sm btn-light-danger" onclick="removeButton(this)">
                    <i class="ki-duotone ki-cross fs-3"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}

function removeButton(btn) {
    btn.closest('.button-row').remove();
    buttonCount--;
}

function toggleButtonFields(select, index) {
    const extraField = document.getElementById('btnExtra' + index);
    const input = extraField.querySelector('input');
    
    if (select.value === 'URL') {
        extraField.style.display = 'block';
        input.name = `buttons[${index}][url]`;
        input.placeholder = 'https://example.com';
    } else if (select.value === 'PHONE_NUMBER') {
        extraField.style.display = 'block';
        input.name = `buttons[${index}][phone]`;
        input.placeholder = '+5511999999999';
    } else {
        extraField.style.display = 'none';
    }
}

function updatePreview() {
    const form = document.getElementById('createTemplateForm');
    const body = form.querySelector('[name="body_text"]').value || 'A mensagem aparecerá aqui...';
    const header = form.querySelector('[name="header_text"]')?.value || '';
    const footer = form.querySelector('[name="footer_text"]')?.value || '';
    const headerType = form.querySelector('[name="header_type"]').value;
    
    document.getElementById('previewBody').textContent = body;
    
    const previewHeader = document.getElementById('previewHeader');
    if (headerType === 'TEXT' && header) {
        previewHeader.textContent = header;
        previewHeader.style.display = 'block';
    } else if (headerType !== 'NONE') {
        previewHeader.innerHTML = `<span class="badge badge-light-info">[${headerType}]</span>`;
        previewHeader.style.display = 'block';
    } else {
        previewHeader.style.display = 'none';
    }
    
    const previewFooter = document.getElementById('previewFooter');
    if (footer) {
        previewFooter.textContent = footer;
        previewFooter.style.display = 'block';
    } else {
        previewFooter.style.display = 'none';
    }
}

// Criar template (rascunho)
document.getElementById('createTemplateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    saveTemplate(false);
});

function saveAndSubmit() {
    saveTemplate(true);
}

function saveTemplate(submitAfter = false) {
    const form = document.getElementById('createTemplateForm');
    const formData = new FormData(form);
    
    const data = {
        waba_id: formData.get('waba_id'),
        name: formData.get('name'),
        category: formData.get('category'),
        language: formData.get('language'),
        header_type: formData.get('header_type'),
        header_text: formData.get('header_text'),
        body_text: formData.get('body_text'),
        footer_text: formData.get('footer_text'),
        buttons: [],
    };
    
    // Coletar botões
    const btnRows = form.querySelectorAll('.button-row');
    btnRows.forEach(row => {
        const idx = row.dataset.index;
        const type = formData.get(`buttons[${idx}][type]`) || 'QUICK_REPLY';
        const text = formData.get(`buttons[${idx}][text]`) || '';
        const url = formData.get(`buttons[${idx}][url]`) || '';
        const phone = formData.get(`buttons[${idx}][phone]`) || '';
        
        if (text) {
            data.buttons.push({ type: type.toLowerCase(), text, url, phone });
        }
    });
    
    if (!data.waba_id || !data.name || !data.body_text) {
        Swal.fire('Erro', 'Preencha os campos obrigatórios', 'error');
        return;
    }
    
    Swal.fire({ title: 'Salvando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch('<?= \App\Helpers\Url::to('/integrations/whatsapp-coex/templates/create') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            if (submitAfter) {
                // Enviar para aprovação imediatamente
                submitTemplateById(result.id);
            } else {
                Swal.fire('Sucesso!', 'Rascunho salvo com sucesso', 'success')
                    .then(() => location.reload());
            }
        } else {
            Swal.fire('Erro', result.error || 'Erro ao salvar', 'error');
        }
    })
    .catch(err => Swal.fire('Erro', 'Erro na requisição', 'error'));
}

function submitTemplate(templateId) {
    Swal.fire({
        title: 'Enviar para Aprovação?',
        text: 'O template será enviado para revisão da Meta. Isso pode levar de minutos a horas.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Enviar',
        cancelButtonText: 'Cancelar',
    }).then(result => {
        if (result.isConfirmed) {
            submitTemplateById(templateId);
        }
    });
}

function submitTemplateById(templateId) {
    Swal.fire({ title: 'Enviando para Meta...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch('<?= \App\Helpers\Url::to('/integrations/whatsapp-coex/templates/submit') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ template_id: templateId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Enviado!', data.message || 'Template enviado para aprovação', 'success')
                .then(() => location.reload());
        } else {
            Swal.fire('Erro', data.error || 'Erro ao enviar', 'error');
        }
    })
    .catch(err => Swal.fire('Erro', 'Erro na requisição', 'error'));
}

function checkTemplateStatus(templateId) {
    Swal.fire({ title: 'Verificando status...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch('<?= \App\Helpers\Url::to('/integrations/whatsapp-coex/templates/check-status') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ template_id: templateId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const tpl = data.data;
            const statusLabels = {
                'APPROVED': '<span class="badge badge-success">Aprovado</span>',
                'PENDING': '<span class="badge badge-warning">Pendente</span>',
                'REJECTED': '<span class="badge badge-danger">Rejeitado</span>',
            };
            
            Swal.fire({
                title: 'Status do Template',
                html: `
                    <div class="text-start">
                        <p><strong>Nome:</strong> ${tpl.name}</p>
                        <p><strong>Status:</strong> ${statusLabels[tpl.status] || tpl.status}</p>
                        ${tpl.quality_score ? `<p><strong>Qualidade:</strong> ${tpl.quality_score}</p>` : ''}
                        ${tpl.rejection_reason ? `<p class="text-danger"><strong>Motivo da rejeição:</strong> ${tpl.rejection_reason}</p>` : ''}
                    </div>
                `,
                icon: tpl.status === 'APPROVED' ? 'success' : (tpl.status === 'REJECTED' ? 'error' : 'info'),
            }).then(() => location.reload());
        } else {
            Swal.fire('Erro', data.error || 'Erro ao verificar', 'error');
        }
    })
    .catch(err => Swal.fire('Erro', 'Erro na requisição', 'error'));
}

function syncTemplates() {
    const wabaId = document.getElementById('wabaSelector')?.value || '<?= htmlspecialchars($selectedWabaId) ?>';
    
    if (!wabaId) {
        Swal.fire('Erro', 'Selecione uma conta WABA primeiro', 'warning');
        return;
    }
    
    Swal.fire({ title: 'Sincronizando templates da Meta...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch('<?= \App\Helpers\Url::to('/integrations/whatsapp-coex/templates/sync') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ waba_id: wabaId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Sincronizado!', data.message || 'Templates sincronizados', 'success')
                .then(() => location.reload());
        } else {
            Swal.fire('Erro', data.error || 'Erro ao sincronizar', 'error');
        }
    })
    .catch(err => Swal.fire('Erro', 'Erro na requisição', 'error'));
}

function deleteTemplate(templateId, name) {
    Swal.fire({
        title: 'Excluir Template?',
        html: `Tem certeza que deseja excluir o template <strong>${name}</strong>?<br><small class="text-danger">Esta ação também remove o template da Meta.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar',
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Excluindo...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            fetch('<?= \App\Helpers\Url::to('/integrations/whatsapp-coex/templates/delete') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ template_id: templateId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Excluído!', 'Template excluído com sucesso', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Erro', data.error || 'Erro ao excluir', 'error');
                }
            })
            .catch(err => Swal.fire('Erro', 'Erro na requisição', 'error'));
        }
    });
}

function viewTemplate(templateId) {
    const modal = new bootstrap.Modal(document.getElementById('viewTemplateModal'));
    document.getElementById('viewTemplateContent').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';
    modal.show();
    
    fetch(`<?= \App\Helpers\Url::to('/integrations/whatsapp-coex/templates/get') ?>?id=${templateId}`)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const tpl = data.data;
            const statusColors = { APPROVED: 'success', PENDING: 'warning', REJECTED: 'danger', DRAFT: 'info' };
            const catColors = { MARKETING: 'info', UTILITY: 'primary', AUTHENTICATION: 'warning' };
            
            let buttonsHtml = '';
            if (tpl.buttons_decoded && tpl.buttons_decoded.length) {
                buttonsHtml = '<div class="mt-3"><strong>Botões:</strong><div class="d-flex gap-2 mt-2">';
                tpl.buttons_decoded.forEach(btn => {
                    buttonsHtml += `<span class="badge badge-light-primary p-2">${btn.text} (${btn.type})</span>`;
                });
                buttonsHtml += '</div></div>';
            }
            
            document.getElementById('viewTemplateContent').innerHTML = `
                <div class="row g-5">
                    <div class="col-md-7">
                        <h5 class="fw-bold mb-3">${tpl.display_name || tpl.name}</h5>
                        <div class="d-flex gap-2 mb-4">
                            <span class="badge badge-light-${statusColors[tpl.status] || 'secondary'}">${tpl.status}</span>
                            <span class="badge badge-light-${catColors[tpl.category] || 'secondary'}">${tpl.category}</span>
                            <span class="badge badge-light-secondary">${tpl.language}</span>
                        </div>
                        
                        ${tpl.header_type !== 'NONE' ? `<div class="mb-3"><strong>Cabeçalho (${tpl.header_type}):</strong><br><span class="text-muted">${tpl.header_text || '[' + tpl.header_type + ']'}</span></div>` : ''}
                        
                        <div class="mb-3">
                            <strong>Corpo:</strong>
                            <div class="bg-light rounded p-3 mt-1 fs-7">${(tpl.body_text || '').replace(/\n/g, '<br>').replace(/\{\{(\d+)\}\}/g, '<code>{{$1}}</code>')}</div>
                        </div>
                        
                        ${tpl.footer_text ? `<div class="mb-3"><strong>Rodapé:</strong><br><span class="text-muted">${tpl.footer_text}</span></div>` : ''}
                        
                        ${buttonsHtml}
                        
                        ${tpl.rejection_reason ? `<div class="alert alert-danger mt-3 p-3 fs-7"><strong>Motivo da rejeição:</strong> ${tpl.rejection_reason}</div>` : ''}
                    </div>
                    <div class="col-md-5">
                        <div class="bg-light rounded p-4">
                            <h6 class="fw-bold mb-3">Estatísticas</h6>
                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Enviados</span>
                                    <span class="fw-bold">${Number(tpl.sent_count || 0).toLocaleString()}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Entregues</span>
                                    <span class="fw-bold">${Number(tpl.delivered_count || 0).toLocaleString()}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Lidos</span>
                                    <span class="fw-bold">${Number(tpl.read_count || 0).toLocaleString()}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Falharam</span>
                                    <span class="fw-bold text-danger">${Number(tpl.failed_count || 0).toLocaleString()}</span>
                                </div>
                            </div>
                            ${tpl.quality_score ? `<div class="mt-3"><strong>Qualidade:</strong> <span class="badge badge-light-${statusColors[tpl.quality_score] || 'secondary'}">${tpl.quality_score}</span></div>` : ''}
                            ${tpl.variable_count > 0 ? `<div class="mt-2"><strong>Variáveis:</strong> ${tpl.variable_count}</div>` : ''}
                            ${tpl.last_synced_at ? `<div class="mt-2 text-muted fs-8">Última sync: ${tpl.last_synced_at}</div>` : ''}
                        </div>
                    </div>
                </div>
            `;
        } else {
            document.getElementById('viewTemplateContent').innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
        }
    })
    .catch(err => {
        document.getElementById('viewTemplateContent').innerHTML = '<div class="alert alert-danger">Erro ao carregar template</div>';
    });
}

function showRejectionReason(templateId) {
    viewTemplate(templateId);
}

function filterTemplates(filter) {
    const wabaId = document.getElementById('wabaSelector')?.value || '<?= htmlspecialchars($selectedWabaId) ?>';
    window.location.href = `<?= \App\Helpers\Url::to('/integrations/whatsapp-coex/templates') ?>?waba_id=${wabaId}&filter=${filter}`;
}

function changeWaba(wabaId) {
    window.location.href = `<?= \App\Helpers\Url::to('/integrations/whatsapp-coex/templates') ?>?waba_id=${wabaId}`;
}

// ==================== UTILITÁRIOS ====================

function viewCoexStatus(phoneId) {
    Swal.fire({ title: 'Carregando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(`<?= \App\Helpers\Url::to('/integrations/whatsapp-coex/status') ?>?phone_id=${phoneId}`)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const s = data.data;
            const phone = s.phone;
            Swal.fire({
                title: 'Status CoEx',
                html: `
                    <div class="text-start">
                        <table class="table table-sm">
                            <tr><td class="text-muted">Número</td><td class="fw-bold">${phone.display_phone_number || phone.phone_number}</td></tr>
                            <tr><td class="text-muted">CoEx Ativo</td><td>${s.enabled ? '<span class="badge badge-success">Sim</span>' : '<span class="badge badge-secondary">Não</span>'}</td></tr>
                            <tr><td class="text-muted">Status CoEx</td><td><span class="badge badge-light-info">${s.status}</span></td></tr>
                            <tr><td class="text-muted">Histórico Sync</td><td>${s.history_synced ? '<span class="badge badge-success">Sincronizado</span>' : '<span class="badge badge-warning">Pendente</span>'}</td></tr>
                            ${s.activated_at ? `<tr><td class="text-muted">Ativado em</td><td>${s.activated_at}</td></tr>` : ''}
                            <tr><td class="text-muted">Qualidade</td><td>${phone.quality_rating || 'N/A'}</td></tr>
                            <tr><td class="text-muted">Modo</td><td>${phone.account_mode || 'N/A'}</td></tr>
                        </table>
                    </div>
                `,
                width: '500px',
            });
        } else {
            Swal.fire('Erro', data.error, 'error');
        }
    })
    .catch(err => Swal.fire('Erro', 'Erro ao carregar status', 'error'));
}

function syncPhone(phoneId) {
    Swal.fire({ title: 'Sincronizando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch('<?= \App\Helpers\Url::to('/integrations/meta/whatsapp/sync') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ id: phoneId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Sincronizado!', 'Dados atualizados', 'success').then(() => location.reload());
        } else {
            Swal.fire('Erro', data.error || 'Erro ao sincronizar', 'error');
        }
    })
    .catch(err => Swal.fire('Erro', 'Erro na requisição', 'error'));
}

function copyField(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    field.select();
    field.setSelectionRange(0, 99999);
    try {
        document.execCommand('copy');
        Swal.fire({ icon: 'success', title: 'Copiado!', timer: 1500, showConfirmButton: false });
    } catch (err) {
        Swal.fire('Erro', 'Não foi possível copiar', 'error');
    }
}

// Input listeners para preview
document.querySelectorAll('#createTemplateForm input, #createTemplateForm textarea, #createTemplateForm select').forEach(el => {
    el.addEventListener('input', updatePreview);
    el.addEventListener('change', updatePreview);
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../' . str_replace('.', '/', $layout) . '.php';
