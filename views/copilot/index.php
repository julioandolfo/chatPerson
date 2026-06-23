<?php
/**
 * View: Copiloto de Atendimento (chat)
 */
$layout = 'layouts.metronic.app';
$title = 'Copiloto de Atendimento';

use App\Helpers\Url;

ob_start();

$stats = $stats ?? ['indexed' => 0, 'pending' => 0];
$categories = $categories ?? [];
?>
<style>
.cp-chat { display: flex; flex-direction: column; height: calc(100vh - 230px); min-height: 420px; }
.cp-messages { flex: 1 1 auto; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 14px; }
.cp-bubble { max-width: 80%; padding: 12px 16px; border-radius: 14px; white-space: pre-wrap; line-height: 1.5; }
.cp-user { align-self: flex-end; background: var(--bs-primary); color: #fff; border-bottom-right-radius: 4px; }
.cp-bot { align-self: flex-start; background: var(--bs-gray-100); color: var(--bs-gray-900); border-bottom-left-radius: 4px; }
.cp-sources { align-self: flex-start; max-width: 80%; }
.cp-inputbar { border-top: 1px solid var(--bs-border-color); padding: 12px; display: flex; gap: 8px; }
</style>

<div class="d-flex flex-column flex-column-fluid p-6">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><h2 class="fw-bold">🤖 Copiloto de Atendimento</h2></div>
            <div class="card-toolbar">
                <span class="badge badge-light-success me-2" id="cp_indexed"><?= (int)$stats['indexed'] ?> na base</span>
                <span class="badge badge-light-warning me-3" id="cp_pending"><?= (int)$stats['pending'] ?> a indexar</span>
                <button class="btn btn-sm btn-light-primary" onclick="cpToggleIndex()" id="cp_reindex_btn">Indexar pendentes</button>
                <button class="btn btn-sm btn-light" onclick="cpReset()" title="Limpar conversa">Limpar</button>
            </div>
        </div>

        <div class="card-body p-0">
            <!-- Filtros -->
            <div class="row g-2 px-4 pt-4">
                <div class="col-md-4">
                    <select id="cp_category" class="form-select form-select-sm">
                        <option value="">Todas as categorias</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="date" id="cp_from" class="form-control form-control-sm" title="Resolvidas a partir de">
                </div>
                <div class="col-md-4">
                    <input type="date" id="cp_to" class="form-control form-control-sm" title="Resolvidas até">
                </div>
            </div>

            <!-- Chat -->
            <div class="cp-chat">
                <div class="cp-messages" id="cp_messages">
                    <div class="cp-bubble cp-bot">
                        Olá! Descreva o problema do cliente e eu busco casos parecidos já resolvidos para sugerir como agir. 😊
                    </div>
                </div>
                <div class="cp-inputbar">
                    <textarea id="cp_question" class="form-control" rows="1"
                              placeholder="Descreva o problema do cliente…  (Enter envia, Shift+Enter quebra linha)"
                              style="resize:none;"></textarea>
                    <button class="btn btn-primary" onclick="cpSend()" id="cp_send_btn" style="min-width:110px;">Enviar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const cpHistory = [];      // [{role:'user'|'assistant', content}]
let cpIndexing = false;

function cpEsc(s) { return String(s == null ? '' : s).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }

function cpAddBubble(role, text) {
    const box = document.getElementById('cp_messages');
    const el = document.createElement('div');
    el.className = 'cp-bubble ' + (role === 'user' ? 'cp-user' : 'cp-bot');
    el.textContent = text;
    box.appendChild(el);
    box.scrollTop = box.scrollHeight;
    return el;
}

function cpAddSources(sources) {
    if (!sources || !sources.length) return;
    const box = document.getElementById('cp_messages');
    const wrap = document.createElement('div');
    wrap.className = 'cp-sources';
    let html = '<div class="text-muted fs-8 mb-1">Conversas de referência:</div><div class="d-flex flex-wrap gap-2">';
    sources.forEach(s => {
        html += `<a href="<?= Url::to('/conversations') ?>?conversation_id=${s.conversation_id}" target="_blank" `
            + `class="badge badge-light-primary text-decoration-none" title="${cpEsc(s.summary)}">`
            + `#${s.conversation_id} · ${cpEsc(s.category || '—')} · ${Math.round((s.score||0)*100)}%</a>`;
    });
    html += '</div>';
    wrap.innerHTML = html;
    box.appendChild(wrap);
    box.scrollTop = box.scrollHeight;
}

function cpSend() {
    const ta = document.getElementById('cp_question');
    const q = ta.value.trim();
    if (!q) return;
    const btn = document.getElementById('cp_send_btn');
    btn.disabled = true; btn.textContent = '…';
    ta.value = '';
    cpAddBubble('user', q);
    cpHistory.push({ role: 'user', content: q });

    const thinking = cpAddBubble('bot', 'Buscando casos parecidos…');

    fetch('<?= Url::to('/copilot/ask') ?>', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({
            question: q,
            category: document.getElementById('cp_category').value,
            date_from: document.getElementById('cp_from').value,
            date_to: document.getElementById('cp_to').value,
            history: JSON.stringify(cpHistory.slice(-6))
        }).toString()
    })
    .then(r => r.json())
    .then(j => {
        btn.disabled = false; btn.textContent = 'Enviar';
        const answer = (j.success && j.data) ? j.data.answer : ('Erro: ' + (j.message || 'falha'));
        thinking.textContent = answer;
        cpHistory.push({ role: 'assistant', content: answer });
        if (j.success && j.data) cpAddSources(j.data.sources);
        document.getElementById('cp_messages').scrollTop = document.getElementById('cp_messages').scrollHeight;
    })
    .catch(e => { btn.disabled = false; btn.textContent = 'Enviar'; thinking.textContent = 'Falha: ' + e; });
}

function cpReset() {
    cpHistory.length = 0;
    document.getElementById('cp_messages').innerHTML =
        '<div class="cp-bubble cp-bot">Conversa limpa. Pode mandar um novo caso. 😊</div>';
}

// Indexação em lote com progresso automático (toggle iniciar/parar).
function cpToggleIndex() {
    const btn = document.getElementById('cp_reindex_btn');
    if (cpIndexing) { cpIndexing = false; btn.textContent = 'Indexar pendentes'; return; }
    cpIndexing = true; btn.textContent = 'Parar indexação';
    cpIndexLoop();
}
function cpIndexLoop() {
    if (!cpIndexing) return;
    fetch('<?= Url::to('/copilot/reindex') ?>', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(j => {
        if (!j.success) { cpIndexing = false; document.getElementById('cp_reindex_btn').textContent = 'Indexar pendentes'; return; }
        document.getElementById('cp_indexed').textContent = j.stats.indexed + ' na base';
        document.getElementById('cp_pending').textContent = j.stats.pending + ' a indexar';
        if (cpIndexing && j.stats.pending > 0 && j.indexed > 0) { cpIndexLoop(); }
        else { cpIndexing = false; document.getElementById('cp_reindex_btn').textContent = 'Indexar pendentes'; }
    })
    .catch(() => { cpIndexing = false; document.getElementById('cp_reindex_btn').textContent = 'Indexar pendentes'; });
}

document.getElementById('cp_question').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); cpSend(); }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
