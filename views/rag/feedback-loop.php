<?php
$layout = 'layouts.metronic.app';
$title = 'Feedback Loop - ' . htmlspecialchars($agent['name'] ?? '');

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <h3 class="fw-bold m-0">Feedback Loop - Treinamento</h3>
                <span class="text-muted fs-7 mt-1"><?= htmlspecialchars($agent['name']) ?> - <?= $pendingCount ?> pendentes</span>
            </div>
        </div>
        <div class="card-toolbar">
            <a href="<?= \App\Helpers\Url::to('/ai-agents/' . $agent['id']) ?>" class="btn btn-sm btn-light me-3">
                <i class="ki-duotone ki-arrow-left fs-2"></i>
                Voltar
            </a>
        </div>
    </div>
    <div class="card-body pt-0">
        <!--begin::Filtros-->
        <div class="d-flex gap-2 mb-5">
            <a href="?status=pending" class="btn btn-sm <?= $status === 'pending' ? 'btn-primary' : 'btn-light' ?>">
                Pendentes (<?= $pendingCount ?>)
            </a>
            <a href="?status=reviewed" class="btn btn-sm <?= $status === 'reviewed' ? 'btn-primary' : 'btn-light' ?>">
                Revisados
            </a>
            <a href="?status=ignored" class="btn btn-sm <?= $status === 'ignored' ? 'btn-primary' : 'btn-light' ?>">
                Ignorados
            </a>
            <a href="?status=all" class="btn btn-sm <?= $status === 'all' ? 'btn-primary' : 'btn-light' ?>">
                Todos
            </a>
        </div>
        <!--end::Filtros-->

        <!--begin::Lista de Feedbacks-->
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                <thead>
                    <tr class="fw-bold text-muted">
                        <th class="min-w-200px">Pergunta do Usuário</th>
                        <th class="min-w-200px">Resposta da IA</th>
                        <th class="min-w-200px">Resposta Correta</th>
                        <th class="min-w-100px">Status</th>
                        <th class="min-w-100px">Data</th>
                        <th class="text-end min-w-150px">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($feedbacks)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-10">
                            <div class="text-muted">Nenhum feedback encontrado.</div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($feedbacks as $feedback): ?>
                    <tr>
                        <td>
                            <div class="text-gray-800"><?= htmlspecialchars($feedback['user_question']) ?></div>
                        </td>
                        <td>
                            <div class="text-gray-600"><?= htmlspecialchars(mb_substr($feedback['ai_response'] ?? '', 0, 150)) ?><?= mb_strlen($feedback['ai_response'] ?? '') > 150 ? '...' : '' ?></div>
                        </td>
                        <td>
                            <?php if ($feedback['correct_answer']): ?>
                            <div class="text-success"><?= htmlspecialchars(mb_substr($feedback['correct_answer'], 0, 150)) ?><?= mb_strlen($feedback['correct_answer']) > 150 ? '...' : '' ?></div>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $statusBadge = [
                                'pending' => 'warning',
                                'reviewed' => 'success',
                                'ignored' => 'secondary'
                            ];
                            $statusLabel = [
                                'pending' => 'Pendente',
                                'reviewed' => 'Revisado',
                                'ignored' => 'Ignorado'
                            ];
                            ?>
                            <span class="badge badge-light-<?= $statusBadge[$feedback['status']] ?? 'secondary' ?>">
                                <?= $statusLabel[$feedback['status']] ?? $feedback['status'] ?>
                            </span>
                            <?php if ($feedback['added_to_kb']): ?>
                            <span class="badge badge-light-success ms-1">Na KB</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="text-muted"><?= date('d/m/Y H:i', strtotime($feedback['created_at'])) ?></span>
                        </td>
                        <td class="text-end">
                            <?php if ($feedback['status'] === 'pending' && \App\Helpers\Permission::can('ai_agents.edit')): ?>
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="reviewFeedback(<?= $feedback['id'] ?>, '<?= htmlspecialchars(addslashes($feedback['user_question'])) ?>', '<?= htmlspecialchars(addslashes($feedback['ai_response'] ?? '')) ?>')">
                                <i class="ki-duotone ki-check fs-5"></i>
                                Revisar
                            </button>
                            <button type="button" class="btn btn-sm btn-light-secondary" onclick="ignoreFeedback(<?= $feedback['id'] ?>)">
                                <i class="ki-duotone ki-cross fs-5"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!--end::Lista de Feedbacks-->
    </div>
</div>
<!--end::Card-->

<!--begin::Modal Revisar Feedback-->
<div class="modal fade" id="kt_modal_review_feedback" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Revisar Feedback</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form_review_feedback">
                <input type="hidden" name="feedback_id" id="review_feedback_id">
                <div class="modal-body">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Pergunta do Usuário</label>
                        <div class="form-control bg-light" id="review_user_question" readonly></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Resposta da IA</label>
                        <div class="form-control bg-light" id="review_ai_response" readonly></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Resposta Correta *</label>
                        <textarea name="correct_answer" id="review_correct_answer" class="form-control" rows="6" required placeholder="Digite a resposta correta..."></textarea>
                    </div>
                    <div class="fv-row mb-7">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="add_to_kb" id="review_add_to_kb" value="1" checked>
                            <label class="form-check-label" for="review_add_to_kb">
                                Adicionar à Knowledge Base após revisão
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal Revisar Feedback-->

<script>
function reviewFeedback(id, question, aiResponse) {
    document.getElementById('review_feedback_id').value = id;
    document.getElementById('review_user_question').textContent = question;
    document.getElementById('review_ai_response').textContent = aiResponse || 'Sem resposta';
    document.getElementById('review_correct_answer').value = '';
    document.getElementById('review_add_to_kb').checked = true;
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_review_feedback'));
    modal.show();
}

function ignoreFeedback(id) {
    Swal.fire({
        title: 'Ignorar feedback?',
        text: 'Este feedback será marcado como ignorado.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, ignorar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/ai-agents/<?= $agent['id'] ?>/rag/feedback-loop/${id}/ignore`, {
                method: 'POST'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            });
        }
    });
}

document.getElementById('form_review_feedback').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const feedbackId = formData.get('feedback_id');
    const btn = this.querySelector('button[type="submit"]');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;

    fetch(`/ai-agents/<?= $agent['id'] ?>/rag/feedback-loop/${feedbackId}/review`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;

        if (data.success) {
            Swal.fire('Sucesso!', data.message + (data.added_to_kb ? ' Conhecimento adicionado à KB!' : ''), 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Erro', data.message, 'error');
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        Swal.fire('Erro', 'Erro ao revisar feedback', 'error');
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>

