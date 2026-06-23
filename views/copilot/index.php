<?php
/**
 * View: Copiloto de Atendimento (página dedicada)
 */
$layout = 'layouts.metronic.app';
$title = 'Copiloto de Atendimento';

use App\Helpers\Url;

ob_start();

$stats = $stats ?? ['indexed' => 0, 'pending' => 0];
?>
<div class="d-flex flex-column flex-column-fluid p-6">

    <div class="card mb-5">
        <div class="card-header">
            <div class="card-title"><h2 class="fw-bold">🤖 Copiloto de Atendimento</h2></div>
            <div class="card-toolbar">
                <span class="badge badge-light-success me-2" id="cp_indexed"><?= (int)$stats['indexed'] ?> conversas na base</span>
                <span class="badge badge-light-warning me-3" id="cp_pending"><?= (int)$stats['pending'] ?> a indexar</span>
                <button class="btn btn-sm btn-light-primary" onclick="cpReindex()" id="cp_reindex_btn">Indexar pendentes</button>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted">
                Descreva o problema do cliente. O copiloto busca <strong>casos parecidos já resolvidos</strong>
                e sugere como agir, citando as conversas de referência.
            </p>
            <div class="d-flex gap-2">
                <textarea id="cp_question" class="form-control" rows="2"
                          placeholder="Ex.: cliente recebeu o produto com defeito e quer a troca, como proceder?"></textarea>
                <button class="btn btn-primary" onclick="cpAsk()" id="cp_ask_btn" style="min-width:120px;">Perguntar</button>
            </div>
        </div>
    </div>

    <div id="cp_result" class="d-none">
        <div class="card mb-4">
            <div class="card-body">
                <h4 class="fw-bold mb-3">💡 Resposta</h4>
                <div id="cp_answer" class="text-gray-800" style="white-space: pre-wrap;"></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title"><h5 class="fw-bold">Conversas de referência</h5></div></div>
            <div class="card-body"><div id="cp_sources" class="d-flex flex-column gap-3"></div></div>
        </div>
    </div>
</div>

<script>
function cpAsk() {
    const q = document.getElementById('cp_question').value.trim();
    if (!q) { return; }
    const btn = document.getElementById('cp_ask_btn');
    btn.disabled = true; btn.textContent = 'Buscando…';
    document.getElementById('cp_result').classList.add('d-none');

    fetch('<?= Url::to('/copilot/ask') ?>', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ question: q }).toString()
    })
    .then(r => r.json())
    .then(j => {
        btn.disabled = false; btn.textContent = 'Perguntar';
        if (!j.success) { alert('Erro: ' + (j.message || '')); return; }
        const d = j.data;
        document.getElementById('cp_answer').textContent = d.answer || '';
        const src = document.getElementById('cp_sources');
        src.innerHTML = '';
        (d.sources || []).forEach(s => {
            const el = document.createElement('div');
            el.className = 'p-3 bg-light rounded';
            el.innerHTML = `<div class="d-flex justify-content-between"><span class="fw-bold">Conversa #${s.conversation_id}</span>`
                + `<span class="badge badge-light-info">${s.category || '—'} · ${Math.round((s.score||0)*100)}%</span></div>`
                + `<div class="text-muted fs-7 mt-1">${(s.summary || '').replace(/</g,'&lt;')}</div>`
                + `<a href="<?= Url::to('/conversations') ?>?conversation_id=${s.conversation_id}" class="btn btn-sm btn-light-primary mt-2">Abrir conversa</a>`;
            src.appendChild(el);
        });
        document.getElementById('cp_result').classList.remove('d-none');
    })
    .catch(e => { btn.disabled = false; btn.textContent = 'Perguntar'; alert('Falha: ' + e); });
}

function cpReindex() {
    const btn = document.getElementById('cp_reindex_btn');
    btn.disabled = true; btn.textContent = 'Indexando…';
    fetch('<?= Url::to('/copilot/reindex') ?>', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(j => {
        btn.disabled = false; btn.textContent = 'Indexar pendentes';
        if (j.success) {
            document.getElementById('cp_indexed').textContent = j.stats.indexed + ' conversas na base';
            document.getElementById('cp_pending').textContent = j.stats.pending + ' a indexar';
            alert('Indexadas ' + j.indexed + ' conversas neste lote.');
        } else { alert('Erro: ' + (j.message || '')); }
    })
    .catch(e => { btn.disabled = false; btn.textContent = 'Indexar pendentes'; alert('Falha: ' + e); });
}

document.getElementById('cp_question').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { cpAsk(); }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
