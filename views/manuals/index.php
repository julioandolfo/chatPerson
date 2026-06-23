<?php
/**
 * View: Gerador de Manuais a partir de conversas
 */
$layout = 'layouts.metronic.app';
$title = 'Gerador de Manuais';

use App\Helpers\Url;

ob_start();

$agents = $agents ?? [];
$manuals = $manuals ?? [];
?>
<div class="d-flex flex-column flex-column-fluid p-6">

    <?php if (!empty($flash_error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($flash_error) ?></div>
    <?php endif; ?>

    <div class="card mb-6">
        <div class="card-header">
            <div class="card-title">
                <h2 class="fw-bold">📘 Gerar Manual a partir de Conversas</h2>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted">
                Lê as conversas <strong>resolvidas</strong> de um agente no período e usa IA para sintetizar um
                manual de normas, decisões e ações — anonimizando dados pessoais antes do envio.
            </p>

            <div class="row g-4">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Agente (origem)</label>
                    <select id="m_agent" class="form-select form-select-sm">
                        <option value="">Todos os agentes</option>
                        <?php foreach ($agents as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">De</label>
                    <input type="date" id="m_from" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Até</label>
                    <input type="date" id="m_to" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Máx. conversas</label>
                    <input type="number" id="m_limit" class="form-control form-control-sm" value="30" min="1" max="30">
                    <div class="form-text">Limite por geração: 30 (síncrono).</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Título do manual</label>
                    <input type="text" id="m_title" class="form-control form-control-sm" placeholder="Ex: Manual CS/Pós-venda">
                </div>
            </div>

            <div class="d-flex align-items-center gap-3 mt-5">
                <button class="btn btn-light-primary btn-sm" id="m_preview_btn" onclick="previewManual()">
                    🔍 Pré-visualizar (volume e custo)
                </button>
                <button class="btn btn-primary btn-sm" id="m_generate_btn" onclick="generateManual()" disabled>
                    ⚙️ Gerar manual
                </button>
                <span id="m_preview_result" class="text-muted fs-7"></span>
            </div>

            <div id="m_progress" class="alert alert-info mt-4 d-none">
                <span class="spinner-border spinner-border-sm me-2"></span>
                Gerando manual a partir das conversas… isso pode levar 1–2 minutos. Não feche a página.
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><h3 class="fw-bold">Manuais gerados</h3></div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-row-bordered align-middle">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th>Título</th><th>Agente</th><th>Conversas</th><th>Custo</th>
                            <th>Status</th><th>Criado</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($manuals)): ?>
                            <tr><td colspan="7" class="text-muted text-center py-5">Nenhum manual gerado ainda.</td></tr>
                        <?php else: foreach ($manuals as $m): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($m['title']) ?></td>
                                <td><?= htmlspecialchars($m['agent_name'] ?? 'Todos') ?></td>
                                <td><?= (int)($m['total_conversations'] ?? 0) ?></td>
                                <td>$<?= number_format((float)($m['cost'] ?? 0), 4) ?></td>
                                <td>
                                    <span class="badge badge-light-<?= $m['status'] === 'published' ? 'success' : 'warning' ?>">
                                        <?= $m['status'] === 'published' ? 'Publicado (RAG)' : 'Rascunho' ?>
                                    </span>
                                </td>
                                <td class="text-muted fs-7"><?= htmlspecialchars($m['created_at']) ?></td>
                                <td>
                                    <a href="<?= Url::to('/manuals/view?id=' . (int)$m['id']) ?>" class="btn btn-sm btn-light-primary">Abrir</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function _mParams() {
    return {
        agent_id: document.getElementById('m_agent').value,
        date_from: document.getElementById('m_from').value,
        date_to: document.getElementById('m_to').value,
        limit: document.getElementById('m_limit').value,
        title: document.getElementById('m_title').value
    };
}

function previewManual() {
    const p = _mParams();
    const btn = document.getElementById('m_preview_btn');
    const res = document.getElementById('m_preview_result');
    btn.disabled = true; res.textContent = 'Calculando…';
    const qs = new URLSearchParams({ agent_id: p.agent_id, date_from: p.date_from, date_to: p.date_to, limit: p.limit });
    fetch('<?= Url::to('/manuals/preview') ?>?' + qs.toString(), {
        credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(j => {
        btn.disabled = false;
        if (!j.success) { res.textContent = 'Erro: ' + (j.message || ''); return; }
        const d = j.data;
        res.innerHTML = `<strong>${d.conversations}</strong> conversas · ~${Number(d.estimated_tokens).toLocaleString()} tokens · custo estimado <strong>$${d.estimated_cost}</strong>`;
        document.getElementById('m_generate_btn').disabled = d.conversations < 1;
    })
    .catch(e => { btn.disabled = false; res.textContent = 'Falha: ' + e; });
}

function generateManual() {
    const p = _mParams();
    if (!confirm('Gerar o manual agora? Isso consome tokens da OpenAI.')) return;
    const gen = document.getElementById('m_generate_btn');
    gen.disabled = true;
    document.getElementById('m_progress').classList.remove('d-none');
    const body = new URLSearchParams(p);
    fetch('<?= Url::to('/manuals/generate') ?>', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(j => {
        if (j.success && j.redirect) { window.location.href = j.redirect; return; }
        document.getElementById('m_progress').classList.add('d-none');
        gen.disabled = false;
        alert('Erro ao gerar: ' + (j.message || 'desconhecido'));
    })
    .catch(e => {
        document.getElementById('m_progress').classList.add('d-none');
        gen.disabled = false;
        alert('Falha: ' + e);
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
