<?php
/**
 * View: Nova Análise de Conversas por Coorte
 */
$title = 'Nova Análise de Conversas';
ob_start();
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                    Nova análise de conversas
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted"><a href="/conversation-insights" class="text-muted">Análise de Conversas</a></li>
                    <li class="breadcrumb-item text-dark">Nova</li>
                </ul>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            <form id="insightForm">
                <div class="row g-5">
                    <!-- COLUNA ESQUERDA: filtros -->
                    <div class="col-lg-8">

                        <!-- Quem entra na análise -->
                        <div class="card mb-5">
                            <div class="card-header">
                                <div class="card-title">
                                    <h3 class="fw-bold">1. Quais conversas analisar</h3>
                                </div>
                            </div>
                            <div class="card-body">

                                <div class="row mb-6">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Período</label>
                                        <select name="days" id="days" class="form-select form-select-solid">
                                            <option value="7">Últimos 7 dias</option>
                                            <option value="15">Últimos 15 dias</option>
                                            <option value="30" selected>Últimos 30 dias</option>
                                            <option value="60">Últimos 60 dias</option>
                                            <option value="90">Últimos 90 dias</option>
                                            <option value="">Período personalizado</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">A data considera</label>
                                        <select name="date_basis" class="form-select form-select-solid">
                                            <option value="activity">Conversas com mensagens no período</option>
                                            <option value="created">Conversas criadas no período</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-6 d-none" id="customDates">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">De</label>
                                        <input type="date" name="date_from" class="form-control form-control-solid">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Até</label>
                                        <input type="date" name="date_to" class="form-control form-control-solid">
                                    </div>
                                </div>

                                <div class="separator separator-dashed my-6"></div>

                                <!-- ETAPAS -->
                                <div class="mb-6">
                                    <label class="form-label fw-semibold">
                                        Passou pela(s) etapa(s)
                                        <span class="ms-1" data-bs-toggle="tooltip"
                                              title="Considera a TRAJETÓRIA da conversa, não a etapa atual. A conversa entra mesmo que já tenha saído da etapa.">
                                            <i class="ki-duotone ki-information fs-6 text-muted"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                        </span>
                                    </label>
                                    <select name="stage_ids[]" id="stage_ids" class="form-select form-select-solid"
                                            data-control="select2" data-placeholder="Qualquer etapa" data-allow-clear="true" multiple>
                                        <?php foreach ($funnels as $funnel): ?>
                                            <optgroup label="<?= htmlspecialchars($funnel['name']) ?>">
                                                <?php foreach ($funnel['stages'] as $stage): ?>
                                                    <option value="<?= (int)$stage['id'] ?>"><?= htmlspecialchars($stage['name']) ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        <label class="form-check form-check-inline form-check-sm mt-2">
                                            <input class="form-check-input" type="radio" name="stage_match" value="any" checked>
                                            <span class="form-check-label">Passou por qualquer uma</span>
                                        </label>
                                        <label class="form-check form-check-inline form-check-sm mt-2">
                                            <input class="form-check-input" type="radio" name="stage_match" value="all">
                                            <span class="form-check-label">Passou por todas</span>
                                        </label>
                                        <label class="form-check form-check-sm form-switch mt-3">
                                            <input class="form-check-input" type="checkbox" name="stage_window" value="in_period">
                                            <span class="form-check-label">
                                                A passagem pela etapa precisa ter ocorrido <b>dentro do período</b>
                                            </span>
                                        </label>
                                        <div class="fs-8 text-muted">
                                            Desmarcado: basta que a conversa tenha passado pela etapa em algum momento.
                                        </div>
                                    </div>
                                </div>

                                <!-- TIMES -->
                                <div class="mb-6">
                                    <label class="form-label fw-semibold">Passou pelo(s) time(s)</label>
                                    <select name="team_ids[]" class="form-select form-select-solid"
                                            data-control="select2" data-placeholder="Qualquer time" data-allow-clear="true" multiple>
                                        <?php foreach ($teams as $team): ?>
                                            <option value="<?= (int)$team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">O time é expandido para seus membros.</div>
                                </div>

                                <!-- AGENTES -->
                                <div class="mb-6">
                                    <label class="form-label fw-semibold">Passou pelo(s) agente(s)</label>
                                    <select name="agent_ids[]" class="form-select form-select-solid"
                                            data-control="select2" data-placeholder="Qualquer agente" data-allow-clear="true" multiple>
                                        <?php foreach ($agents as $agent): ?>
                                            <option value="<?= (int)$agent['id'] ?>"><?= htmlspecialchars($agent['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        <div class="mt-2">
                                            <label class="form-check form-check-inline form-check-sm">
                                                <input class="form-check-input" type="radio" name="agent_basis" value="both" checked>
                                                <span class="form-check-label">Atribuída a ele <b>ou</b> respondeu</span>
                                            </label>
                                            <label class="form-check form-check-inline form-check-sm">
                                                <input class="form-check-input" type="radio" name="agent_basis" value="assignment">
                                                <span class="form-check-label">Só atribuição</span>
                                            </label>
                                            <label class="form-check form-check-inline form-check-sm">
                                                <input class="form-check-input" type="radio" name="agent_basis" value="message">
                                                <span class="form-check-label">Só quem respondeu</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="separator separator-dashed my-6"></div>

                                <!-- Filtros complementares -->
                                <div class="row">
                                    <div class="col-md-6 mb-5">
                                        <label class="form-label fw-semibold">Funis</label>
                                        <select name="funnel_ids[]" class="form-select form-select-solid"
                                                data-control="select2" data-placeholder="Todos" data-allow-clear="true" multiple>
                                            <?php foreach ($funnels as $funnel): ?>
                                                <option value="<?= (int)$funnel['id'] ?>"><?= htmlspecialchars($funnel['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-5">
                                        <label class="form-label fw-semibold">Setores</label>
                                        <select name="department_ids[]" class="form-select form-select-solid"
                                                data-control="select2" data-placeholder="Todos" data-allow-clear="true" multiple>
                                            <?php foreach ($departments as $department): ?>
                                                <option value="<?= (int)$department['id'] ?>"><?= htmlspecialchars($department['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-5">
                                        <label class="form-label fw-semibold">Canais</label>
                                        <select name="channels[]" class="form-select form-select-solid"
                                                data-control="select2" data-placeholder="Todos" data-allow-clear="true" multiple>
                                            <?php foreach ($channels as $channel): ?>
                                                <option value="<?= htmlspecialchars($channel) ?>"><?= htmlspecialchars(ucfirst($channel)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-5">
                                        <label class="form-label fw-semibold">Tags</label>
                                        <select name="tag_ids[]" class="form-select form-select-solid"
                                                data-control="select2" data-placeholder="Todas" data-allow-clear="true" multiple>
                                            <?php foreach ($tags as $tag): ?>
                                                <option value="<?= (int)$tag['id'] ?>"><?= htmlspecialchars($tag['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-5">
                                        <label class="form-label fw-semibold">Status da conversa</label>
                                        <select name="statuses[]" class="form-select form-select-solid"
                                                data-control="select2" data-placeholder="Todos" data-allow-clear="true" multiple>
                                            <option value="open">Aberta</option>
                                            <option value="pending">Pendente</option>
                                            <option value="resolved">Resolvida</option>
                                            <option value="closed">Fechada</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-5">
                                        <label class="form-label fw-semibold">Mín. de mensagens</label>
                                        <input type="number" name="min_messages" class="form-control form-control-solid" value="4" min="0">
                                        <div class="form-text">Ignora conversas curtas demais.</div>
                                    </div>
                                    <div class="col-md-3 mb-5">
                                        <label class="form-label fw-semibold">Limite de conversas</label>
                                        <input type="number" name="limit" class="form-control form-control-solid" value="300" min="1" max="2000">
                                        <div class="form-text">Teto de custo/tempo.</div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- O que quer entender -->
                        <div class="card mb-5">
                            <div class="card-header">
                                <div class="card-title">
                                    <h3 class="fw-bold">2. O que você quer entender</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-5">
                                    <label class="form-label fw-semibold">Nome da análise</label>
                                    <input type="text" name="name" class="form-control form-control-solid"
                                           placeholder="Ex.: Comercial — abandono em agosto">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold required">Contexto da análise</label>
                                    <textarea name="context_question" id="context_question" class="form-control form-control-solid" rows="4"
                                              placeholder="Ex.: Identifique em que momento o cliente desistiu da compra ou parou de responder e por quê. Aponte a última objeção não tratada e se houve falha de follow-up do vendedor."></textarea>
                                    <div class="form-text">
                                        Esse texto vai junto de cada conversa para a IA. Quanto mais específico, melhor o resultado.
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-light-primary preset-btn"
                                            data-text="Identifique em que momento o cliente desistiu da compra ou parou de responder e por quê. Aponte a última objeção não tratada e se houve falha de follow-up do vendedor.">
                                        Onde perdemos a venda
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light-primary preset-btn"
                                            data-text="Identifique as objeções mais comuns levantadas pelos clientes e como o vendedor respondeu a cada uma. Aponte objeções que ficaram sem resposta.">
                                        Objeções não tratadas
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light-primary preset-btn"
                                            data-text="Avalie a qualidade do atendimento: tempo de resposta, clareza, se o vendedor entendeu a necessidade e se conduziu a conversa para o fechamento.">
                                        Qualidade do atendimento
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light-primary preset-btn"
                                            data-text="Identifique quais conversas ainda são recuperáveis e qual a melhor abordagem de retomada para cada uma.">
                                        Oportunidades recuperáveis
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- COLUNA DIREITA: prévia -->
                    <div class="col-lg-4">
                        <div class="card sticky-top" style="top: 100px;">
                            <div class="card-header">
                                <div class="card-title">
                                    <h3 class="fw-bold">Prévia</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="previewLoading" class="text-center py-5 d-none">
                                    <span class="spinner-border spinner-border-sm text-primary"></span>
                                    <span class="text-muted ms-2">Calculando…</span>
                                </div>

                                <div id="previewContent">
                                    <div class="text-center py-5 text-muted">
                                        Ajuste os filtros para ver quantas conversas entram.
                                    </div>
                                </div>

                                <div class="separator separator-dashed my-5"></div>

                                <div class="mb-5">
                                    <label class="form-label fw-semibold fs-7">Teto de custo (US$)</label>
                                    <input type="number" name="cost_limit" class="form-control form-control-solid form-control-sm"
                                           value="25" min="0" step="0.5">
                                    <div class="form-text fs-8">A análise para ao atingir esse valor.</div>
                                </div>

                                <button type="button" id="btnPreview" class="btn btn-light-primary w-100 mb-3">
                                    Recalcular prévia
                                </button>

                                <button type="submit" id="btnSubmit" class="btn btn-primary w-100">
                                    Iniciar análise
                                </button>

                                <div class="text-muted fs-8 mt-3 text-center">
                                    O processamento roda em segundo plano.<br>
                                    Você pode fechar esta página.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery) {
        jQuery('[data-control="select2"]').select2();
    }

    const form = document.getElementById('insightForm');
    const daysSelect = document.getElementById('days');
    const customDates = document.getElementById('customDates');
    const previewContent = document.getElementById('previewContent');
    const previewLoading = document.getElementById('previewLoading');
    const btnSubmit = document.getElementById('btnSubmit');

    let previewTimer = null;
    let lastPreview = null;

    daysSelect.addEventListener('change', function () {
        customDates.classList.toggle('d-none', this.value !== '');
        schedulePreview();
    });

    document.querySelectorAll('.preset-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('context_question').value = this.dataset.text;
        });
    });

    // Recalcular ao mexer em qualquer filtro
    form.addEventListener('change', function (event) {
        if (event.target.name === 'context_question' || event.target.name === 'name') {
            return;
        }
        schedulePreview();
    });

    if (window.jQuery) {
        jQuery('[data-control="select2"]').on('change', schedulePreview);
    }

    document.getElementById('btnPreview').addEventListener('click', loadPreview);

    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(loadPreview, 600);
    }

    function buildFormData() {
        const data = new FormData(form);
        data.delete('context_question');
        data.delete('name');
        return data;
    }

    function loadPreview() {
        previewLoading.classList.remove('d-none');
        previewContent.classList.add('d-none');

        fetch('/conversation-insights/preview', {
            method: 'POST',
            body: buildFormData(),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                previewLoading.classList.add('d-none');
                previewContent.classList.remove('d-none');

                if (!data.success) {
                    previewContent.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Erro ao calcular') + '</div>';
                    return;
                }

                lastPreview = data;
                renderPreview(data);
            })
            .catch(function () {
                previewLoading.classList.add('d-none');
                previewContent.classList.remove('d-none');
                previewContent.innerHTML = '<div class="alert alert-danger">Erro de conexão.</div>';
            });
    }

    function renderPreview(data) {
        let html = '';

        html += '<div class="d-flex flex-stack mb-3">';
        html += '<span class="text-muted fw-semibold">Conversas encontradas</span>';
        html += '<span class="fs-2 fw-bold text-dark">' + data.total + '</span>';
        html += '</div>';

        html += '<div class="d-flex flex-stack mb-3">';
        html += '<span class="text-muted fw-semibold">Serão analisadas</span>';
        html += '<span class="fs-4 fw-bold text-primary">' + data.selected + '</span>';
        html += '</div>';

        html += '<div class="d-flex flex-stack mb-3">';
        html += '<span class="text-muted fw-semibold fs-7">Média de mensagens</span>';
        html += '<span class="fw-semibold fs-7">' + data.avg_messages + '</span>';
        html += '</div>';

        if (data.estimate) {
            html += '<div class="separator separator-dashed my-4"></div>';
            html += '<div class="d-flex flex-stack mb-2">';
            html += '<span class="text-muted fw-semibold">Custo estimado</span>';
            html += '<span class="fs-3 fw-bold text-success">US$ ' + Number(data.estimate.total).toFixed(2) + '</span>';
            html += '</div>';
            html += '<div class="text-muted fs-8">';
            html += 'Por conversa: ' + data.estimate.model_map + ' · Síntese: ' + data.estimate.model_reduce;
            html += '</div>';
        }

        if (data.warnings && data.warnings.length) {
            html += '<div class="separator separator-dashed my-4"></div>';
            data.warnings.forEach(function (warning) {
                html += '<div class="alert alert-warning py-2 px-3 fs-8 mb-2">' + warning + '</div>';
            });
        }

        previewContent.innerHTML = html;
        btnSubmit.disabled = data.selected === 0;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const context = document.getElementById('context_question').value.trim();

        if (!context) {
            alert('Descreva o que você quer entender com esta análise.');
            return;
        }

        if (lastPreview && lastPreview.estimate) {
            const confirmed = confirm(
                'Serão analisadas ' + lastPreview.selected + ' conversas.\n' +
                'Custo estimado: US$ ' + Number(lastPreview.estimate.total).toFixed(2) + '\n\nIniciar?'
            );
            if (!confirmed) {
                return;
            }
        }

        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Criando…';

        fetch('/conversation-insights', {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.href = data.redirect;
                    return;
                }

                alert(data.message || 'Erro ao criar a análise.');
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Iniciar análise';
            })
            .catch(function () {
                alert('Erro de conexão.');
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Iniciar análise';
            });
    });

    loadPreview();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
