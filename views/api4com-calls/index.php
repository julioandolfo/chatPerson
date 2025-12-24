<?php
$layout = 'layouts.metronic.app';
$title = 'Chamadas Api4Com';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Chamadas Api4Com</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        <!--begin::Filters-->
        <div class="card mb-5">
            <div class="card-body">
                <form id="kt_api4com_calls_filters" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <option value="initiated">Iniciada</option>
                            <option value="ringing">Tocando</option>
                            <option value="answered">Atendida</option>
                            <option value="ended">Finalizada</option>
                            <option value="failed">Falhou</option>
                            <option value="cancelled">Cancelada</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Conta Api4Com</label>
                        <select name="api4com_account_id" class="form-select form-select-solid">
                            <option value="">Todas</option>
                            <?php foreach ($api4com_accounts as $account): ?>
                                <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data Inicial</label>
                        <input type="date" name="date_from" class="form-control form-control-solid" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data Final</label>
                        <input type="date" name="date_to" class="form-control form-control-solid" />
                    </div>
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <button type="reset" class="btn btn-light">Limpar</button>
                    </div>
                </form>
            </div>
        </div>
        <!--end::Filters-->

        <?php if (empty($calls)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-phone fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma chamada encontrada</h3>
                <div class="text-gray-500 fs-6 mb-7">Inicie uma chamada a partir de uma conversa ou ajuste os filtros.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th>Contato</th>
                            <th>Número</th>
                            <th>Agente</th>
                            <th>Conta Api4Com</th>
                            <th>Status</th>
                            <th>Duração</th>
                            <th>Data/Hora</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calls as $call): ?>
                            <?php
                            $statusClass = [
                                'initiated' => 'warning',
                                'ringing' => 'info',
                                'answered' => 'success',
                                'ended' => 'secondary',
                                'failed' => 'danger',
                                'cancelled' => 'dark'
                            ];
                            $statusText = [
                                'initiated' => 'Iniciada',
                                'ringing' => 'Tocando',
                                'answered' => 'Atendida',
                                'ended' => 'Finalizada',
                                'failed' => 'Falhou',
                                'cancelled' => 'Cancelada'
                            ];
                            $currentStatus = $call['status'] ?? 'initiated';
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-45px me-5">
                                            <div class="symbol-label fs-3 bg-light-primary text-primary">
                                                <?= strtoupper(substr($call['contact_name'] ?? 'N', 0, 1)) ?>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-start flex-column">
                                            <span class="text-dark fw-bold text-hover-primary fs-6">
                                                <?= htmlspecialchars($call['contact_name'] ?? 'Sem nome') ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted fw-semibold text-muted d-block fs-7">
                                        <?= htmlspecialchars($call['to_number'] ?? '') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($call['agent_name']): ?>
                                        <span class="text-dark fw-semibold d-block fs-7">
                                            <?= htmlspecialchars($call['agent_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-dark fw-semibold d-block fs-7">
                                        <?= htmlspecialchars($call['api4com_account_name'] ?? '') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-light-<?= $statusClass[$currentStatus] ?? 'secondary' ?>">
                                        <?= $statusText[$currentStatus] ?? 'Desconhecido' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($call['duration'] > 0): ?>
                                        <?php
                                        $minutes = floor($call['duration'] / 60);
                                        $seconds = $call['duration'] % 60;
                                        echo sprintf('%02d:%02d', $minutes, $seconds);
                                        ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-muted fw-semibold text-muted d-block fs-7">
                                        <?= date('d/m/Y H:i', strtotime($call['created_at'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="#" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" 
                                       onclick="viewCall(<?= $call['id'] ?>)"
                                       title="Ver Detalhes">
                                        <i class="ki-duotone ki-eye fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                    </a>
                                    <?php if (in_array($currentStatus, ['initiated', 'ringing', 'answered'])): ?>
                                    <a href="#" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm" 
                                       onclick="endCall(<?= $call['id'] ?>)"
                                       title="Encerrar Chamada">
                                        <i class="ki-duotone ki-cross fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!--begin::Pagination-->
            <?php if ($pagination['pages'] > 1): ?>
            <div class="d-flex flex-stack flex-wrap pt-10">
                <div class="fs-6 fw-semibold text-gray-700">
                    Mostrando <?= ($pagination['page'] - 1) * $pagination['limit'] + 1 ?> a <?= min($pagination['page'] * $pagination['limit'], $pagination['total']) ?> de <?= $pagination['total'] ?> chamadas
                </div>
                <ul class="pagination">
                    <?php if ($pagination['page'] > 1): ?>
                        <li class="page-item previous">
                            <a href="?page=<?= $pagination['page'] - 1 ?>" class="page-link">
                                <i class="previous"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = max(1, $pagination['page'] - 2); $i <= min($pagination['pages'], $pagination['page'] + 2); $i++): ?>
                        <li class="page-item <?= $i == $pagination['page'] ? 'active' : '' ?>">
                            <a href="?page=<?= $i ?>" class="page-link"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($pagination['page'] < $pagination['pages']): ?>
                        <li class="page-item next">
                            <a href="?page=<?= $pagination['page'] + 1 ?>" class="page-link">
                                <i class="next"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
            <!--end::Pagination-->
        <?php endif; ?>
    </div>
</div>
<!--end::Card-->

<?php 
$content = ob_get_clean();
$scripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const filterForm = document.getElementById("kt_api4com_calls_filters");
    if (filterForm) {
        filterForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const formData = new FormData(filterForm);
            const params = new URLSearchParams(formData);
            window.location.href = "?' . http_build_query($filters) . '&" + params.toString();
        });
    }
});

function viewCall(callId) {
    fetch("' . \App\Helpers\Url::to('/api4com-calls') . '/" + callId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const call = data.call;
                alert("Detalhes da Chamada:\\n\\n" +
                      "Status: " + call.status + "\\n" +
                      "Duração: " + (call.duration || 0) + "s\\n" +
                      "De: " + call.from_number + "\\n" +
                      "Para: " + call.to_number);
            }
        });
}

function endCall(callId) {
    if (!confirm("Tem certeza que deseja encerrar esta chamada?")) {
        return;
    }
    
    fetch("' . \App\Helpers\Url::to('/api4com-calls') . '/" + callId + "/end", {
        method: "POST",
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert("Erro: " + (data.message || "Erro ao encerrar chamada"));
        }
    });
}
</script>';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>

