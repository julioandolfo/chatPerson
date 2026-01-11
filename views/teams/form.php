<?php
$layout = 'layouts.metronic.app';
$isEdit = isset($team) && !empty($team);
$title = $isEdit ? 'Editar Time' : 'Criar Time';

ob_start();
?>

<!--begin::Toolbar-->
<div class="d-flex flex-wrap flex-stack pb-7">
    <div class="d-flex flex-wrap align-items-center my-1">
        <h3 class="fw-bold me-5 my-1"><?= $title ?></h3>
    </div>
    <div class="d-flex align-items-center my-1">
        <a href="/teams" class="btn btn-sm btn-light">
            <i class="ki-duotone ki-arrow-left fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            Voltar
        </a>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Form-->
<form id="kt_team_form" method="POST" action="<?= $isEdit ? '/teams/update' : '/teams' ?>">
    <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="<?= $team['id'] ?>">
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <!--begin::Card-->
            <div class="card mb-5">
                <div class="card-header">
                    <h3 class="card-title">Informações do Time</h3>
                </div>
                <div class="card-body">
                    <!--begin::Input group-->
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome do Time</label>
                        <input type="text" name="name" class="form-control form-control-solid" placeholder="Ex: Time de Vendas A" value="<?= htmlspecialchars($team['name'] ?? '') ?>" required />
                    </div>
                    <!--end::Input group-->

                    <!--begin::Input group-->
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" class="form-control form-control-solid" rows="4" placeholder="Descrição do time..."><?= htmlspecialchars($team['description'] ?? '') ?></textarea>
                    </div>
                    <!--end::Input group-->

                    <!--begin::Input group-->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Cor de Identificação</label>
                                <input type="color" name="color" class="form-control form-control-solid form-control-color" value="<?= htmlspecialchars($team['color'] ?? '#009ef7') ?>" />
                                <div class="form-text">Cor para identificação visual do time</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Status</label>
                                <select name="is_active" class="form-select form-select-solid">
                                    <option value="1" <?= ($team['is_active'] ?? 1) == 1 ? 'selected' : '' ?>>Ativo</option>
                                    <option value="0" <?= ($team['is_active'] ?? 1) == 0 ? 'selected' : '' ?>>Inativo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <!--end::Input group-->
                </div>
            </div>
            <!--end::Card-->

            <!--begin::Card-->
            <div class="card mb-5">
                <div class="card-header">
                    <h3 class="card-title">Membros do Time</h3>
                </div>
                <div class="card-body">
                    <div class="fv-row">
                        <label class="fw-semibold fs-6 mb-2">Selecionar Agentes</label>
                        <select name="member_ids[]" class="form-select form-select-solid" data-control="select2" data-placeholder="Selecione os agentes" data-allow-clear="true" multiple>
                            <?php foreach ($agents as $agent): ?>
                            <option value="<?= $agent['id'] ?>" 
                                <?php if ($isEdit && !empty($team['members'])): ?>
                                    <?php foreach ($team['members'] as $member): ?>
                                        <?= $member['id'] == $agent['id'] ? 'selected' : '' ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            >
                                <?= htmlspecialchars($agent['name']) ?> (<?= htmlspecialchars($agent['email']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Selecione os agentes que farão parte do time</div>
                    </div>
                </div>
            </div>
            <!--end::Card-->
        </div>

        <div class="col-lg-4">
            <!--begin::Card-->
            <div class="card mb-5">
                <div class="card-header">
                    <h3 class="card-title">Configurações</h3>
                </div>
                <div class="card-body">
                    <!--begin::Input group-->
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Líder do Time</label>
                        <select name="leader_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Selecione o líder" data-allow-clear="true">
                            <option value="">Nenhum</option>
                            <?php foreach ($agents as $agent): ?>
                            <option value="<?= $agent['id'] ?>" <?= ($team['leader_id'] ?? 0) == $agent['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($agent['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Líder responsável pelo time</div>
                    </div>
                    <!--end::Input group-->

                    <!--begin::Input group-->
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Setor</label>
                        <select name="department_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Selecione o setor" data-allow-clear="true">
                            <option value="">Nenhum</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= ($team['department_id'] ?? 0) == $dept['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Setor ao qual o time pertence</div>
                    </div>
                    <!--end::Input group-->
                </div>
            </div>
            <!--end::Card-->

            <!--begin::Actions-->
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        <i class="ki-duotone ki-check fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <?= $isEdit ? 'Atualizar Time' : 'Criar Time' ?>
                    </button>
                    <a href="/teams" class="btn btn-light w-100">
                        <i class="ki-duotone ki-cross fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Cancelar
                    </a>
                </div>
            </div>
            <!--end::Actions-->
        </div>
    </div>
</form>
<!--end::Form-->

<script>
// Inicializar Select2
$(document).ready(function() {
    $('[data-control="select2"]').select2();
});

// Validação do formulário
var validator = FormValidation.formValidation(
    document.getElementById('kt_team_form'),
    {
        fields: {
            'name': {
                validators: {
                    notEmpty: {
                        message: 'Nome do time é obrigatório'
                    }
                }
            }
        },
        plugins: {
            trigger: new FormValidation.plugins.Trigger(),
            bootstrap: new FormValidation.plugins.Bootstrap5({
                rowSelector: '.fv-row',
                eleInvalidClass: '',
                eleValidClass: ''
            })
        }
    }
);

// Submit do formulário
document.getElementById('kt_team_form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    validator.validate().then(function(status) {
        if (status == 'Valid') {
            e.target.submit();
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
