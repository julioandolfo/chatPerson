<?php
/**
 * View: Manual gerado (detalhe)
 */
$layout = 'layouts.metronic.app';
$title = $manual['title'] ?? 'Manual';

use App\Helpers\Url;

ob_start();

$manual = $manual ?? [];
$divergences = $divergences ?? [];
$aiAgents = $aiAgents ?? [];

/** Conversor mínimo de Markdown → HTML (headings, bold, listas, parágrafos). */
function mdToHtml(string $md): string {
    $out = [];
    $listType = null; // null | 'ul' | 'ol'
    $closeList = function () use (&$out, &$listType) {
        if ($listType !== null) { $out[] = '</' . $listType . '>'; $listType = null; }
    };
    $openList = function (string $type) use (&$out, &$listType, $closeList) {
        if ($listType !== $type) { $closeList(); $out[] = '<' . $type . ' class="mb-3">'; $listType = $type; }
    };

    foreach (preg_split('/\r?\n/', $md) as $line) {
        $safe = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
        $safe = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $safe);
        $safe = preg_replace('/(?<!\*)\*(?!\*)(.+?)\*(?!\*)/', '<em>$1</em>', $safe);

        if (preg_match('/^\s*###\s+(.*)/', $line, $mm)) {
            $closeList();
            $out[] = '<h4 class="fw-bold mt-4 mb-2">' . htmlspecialchars($mm[1]) . '</h4>';
        } elseif (preg_match('/^\s*##\s+(.*)/', $line, $mm)) {
            $closeList();
            $out[] = '<h3 class="fw-bold mt-5 mb-3 text-primary">' . htmlspecialchars($mm[1]) . '</h3>';
        } elseif (preg_match('/^\s*#\s+(.*)/', $line, $mm)) {
            $closeList();
            $out[] = '<h2 class="fw-bolder mt-5 mb-3">' . htmlspecialchars($mm[1]) . '</h2>';
        } elseif (preg_match('/^\s*>\s?(.*)/', $safe, $mm)) {
            $closeList();
            $out[] = '<blockquote class="border-start border-4 border-primary ps-3 py-1 my-2 text-gray-700 fst-italic">' . $mm[1] . '</blockquote>';
        } elseif (preg_match('/^\s*[-*]\s+(.*)/', $safe, $mm)) {
            $openList('ul');
            $out[] = '<li>' . preg_replace('/^\s*[-*]\s+/', '', $mm[1]) . '</li>';
        } elseif (preg_match('/^\s*\d+\.\s+(.*)/', $safe, $mm)) {
            $openList('ol');
            $out[] = '<li>' . $mm[1] . '</li>';
        } elseif (trim($line) === '') {
            $closeList();
        } else {
            $closeList();
            $out[] = '<p class="mb-2">' . $safe . '</p>';
        }
    }
    $closeList();
    return implode("\n", $out);
}
?>
<div class="d-flex flex-column flex-column-fluid p-6">

    <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
        <div>
            <h1 class="fw-bolder mb-1"><?= htmlspecialchars($manual['title']) ?></h1>
            <div class="text-muted fs-7">
                Agente: <strong><?= htmlspecialchars($manual['agent_name'] ?? 'Todos') ?></strong> ·
                Base: <strong><?= (int)($manual['total_conversations'] ?? 0) ?></strong> conversas ·
                Custo: <strong>$<?= number_format((float)($manual['job_cost'] ?? 0), 4) ?></strong> ·
                Status:
                <span class="badge badge-light-<?= $manual['status'] === 'published' ? 'success' : 'warning' ?>">
                    <?= $manual['status'] === 'published' ? 'Publicado no RAG' : 'Rascunho' ?>
                </span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= Url::to('/manuals') ?>" class="btn btn-sm btn-light">← Voltar</a>
            <a href="<?= Url::to('/manuals/export?id=' . (int)$manual['id']) ?>" class="btn btn-sm btn-light-primary">⬇️ Exportar .md</a>
        </div>
    </div>

    <!-- Publicar no RAG -->
    <div class="card mb-5">
        <div class="card-body d-flex align-items-center gap-3 flex-wrap">
            <span class="fw-semibold">🧠 Publicar na base de conhecimento (RAG) de um agente de IA:</span>
            <select id="rag_agent" class="form-select form-select-sm w-auto">
                <option value="">Selecione o agente de IA…</option>
                <?php foreach ($aiAgents as $ai): ?>
                    <option value="<?= (int)$ai['id'] ?>"><?= htmlspecialchars($ai['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-primary" onclick="publishRag(<?= (int)$manual['id'] ?>)">Publicar</button>
            <span id="rag_result" class="text-muted fs-7"></span>
        </div>
    </div>

    <?php if (!empty($divergences)): ?>
    <div class="card mb-5 border border-warning">
        <div class="card-header"><div class="card-title"><h3 class="fw-bold text-warning">⚠️ Divergências de atendimento detectadas</h3></div></div>
        <div class="card-body">
            <p class="text-muted">Cenários tratados de formas diferentes entre conversas — oportunidades de padronização.</p>
            <?php foreach ($divergences as $d): ?>
                <div class="mb-4 p-3 bg-light-warning rounded">
                    <div class="fw-bold"><?= htmlspecialchars($d['cenario'] ?? 'Cenário') ?></div>
                    <?php if (!empty($d['variacoes']) && is_array($d['variacoes'])): ?>
                        <ul class="mb-1 mt-2">
                            <?php foreach ($d['variacoes'] as $v): ?>
                                <li><?= htmlspecialchars(is_string($v) ? $v : json_encode($v)) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if (!empty($d['recomendacao'])): ?>
                        <div class="text-success fw-semibold">✅ Recomendação: <?= htmlspecialchars($d['recomendacao']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="manual-content">
                <?= mdToHtml((string)($manual['content_markdown'] ?? '')) ?>
            </div>
        </div>
    </div>
</div>

<script>
function publishRag(manualId) {
    const aiId = document.getElementById('rag_agent').value;
    const res = document.getElementById('rag_result');
    if (!aiId) { res.textContent = 'Selecione um agente de IA.'; return; }
    if (!confirm('Publicar este manual na base de conhecimento do agente selecionado?')) return;
    res.textContent = 'Publicando…';
    const body = new URLSearchParams({ manual_id: manualId, ai_agent_id: aiId });
    fetch('<?= Url::to('/manuals/publish-rag') ?>', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(j => { res.textContent = j.message || (j.success ? 'OK' : 'Erro'); if (j.success) setTimeout(() => location.reload(), 1200); })
    .catch(e => { res.textContent = 'Falha: ' + e; });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
