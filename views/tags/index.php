<?php
$layout = 'layouts.metronic.app';
$title = 'Tags';

/**
 * Helper para converter hex para RGB
 */
function hexToRgb($hex) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) < 6) {
        $hex = str_pad($hex, 6, '0');
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "$r, $g, $b";
}

/**
 * Helper para obter cor de contraste (branco ou preto)
 */
function getContrastColor($hex) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) < 6) {
        $hex = str_pad($hex, 6, '0');
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    return $brightness > 128 ? '#000000' : '#FFFFFF';
}

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Tags</h3>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2">
                <input type="text" data-kt-filter="search" class="form-control form-control-solid w-250px" placeholder="Buscar tags..." />
                <?php if (\App\Helpers\Permission::can('tags.create')): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_tag">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Nova Tag
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <strong>Erro:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($tags)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-tag fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma tag encontrada</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando uma nova tag.</div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($tags as $tag): 
                    $tagColor = $tag['color'] ?? '#009EF7';
                    $tagName = htmlspecialchars($tag['name']);
                    $tagDescription = htmlspecialchars($tag['description'] ?? '');
                    $tagId = $tag['id'];
                ?>
                    <div class="col-md-6 col-lg-4 col-xl-3">
                        <div class="card card-flush h-100 tag-card" style="border-left: 4px solid <?= $tagColor ?>;">
                            <div class="card-body d-flex flex-column p-6">
                                <!-- Header com ícone e nome -->
                                <div class="d-flex align-items-center mb-4">
                                    <div class="symbol symbol-50px symbol-circle me-4 flex-shrink-0" 
                                         style="background-color: <?= $tagColor ?>; box-shadow: 0 0 0 3px rgba(<?= hexToRgb($tagColor) ?>, 0.1);">
                                        <div class="symbol-label fw-bold fs-4" style="color: <?= getContrastColor($tagColor) ?>;">
                                            <?= mb_strtoupper(mb_substr($tagName, 0, 1)) ?>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <h5 class="fw-bold text-gray-800 mb-0 fs-5"><?= $tagName ?></h5>
                                    </div>
                                </div>
                                
                                <!-- Descrição -->
                                <?php if (!empty($tag['description'])): ?>
                                    <p class="text-gray-600 fs-7 mb-4 line-clamp-2"><?= $tagDescription ?></p>
                                <?php else: ?>
                                    <p class="text-gray-400 fs-7 mb-4 fst-italic">Sem descrição</p>
                                <?php endif; ?>
                                
                                <!-- Preview da cor -->
                                <div class="mb-4">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="text-muted fs-7 fw-semibold me-2">Cor:</span>
                                        <span class="badge badge-lg" style="background-color: <?= $tagColor ?>; color: <?= getContrastColor($tagColor) ?>;">
                                            <?= strtoupper($tagColor) ?>
                                        </span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <div class="flex-grow-1" style="height: 32px; background: linear-gradient(90deg, <?= $tagColor ?> 0%, <?= $tagColor ?> 100%); border-radius: 6px; border: 1px solid rgba(0,0,0,0.1);"></div>
                                        <div style="width: 32px; height: 32px; background-color: <?= $tagColor ?>; border-radius: 6px; border: 1px solid rgba(0,0,0,0.1);"></div>
                                    </div>
                                </div>
                                
                                <!-- Ações -->
                                <div class="mt-auto d-flex justify-content-end gap-2 pt-4 border-top">
                                    <?php if (\App\Helpers\Permission::can('tags.edit')): ?>
                                    <button type="button" class="btn btn-sm btn-icon btn-light btn-active-light-primary" 
                                            onclick="editTag(<?= $tagId ?>, '<?= htmlspecialchars($tag['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($tagColor, ENT_QUOTES) ?>', '<?= htmlspecialchars($tag['description'] ?? '', ENT_QUOTES) ?>')"
                                            title="Editar tag">
                                        <i class="ki-duotone ki-pencil fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (\App\Helpers\Permission::can('tags.delete')): ?>
                                    <button type="button" class="btn btn-sm btn-icon btn-light-danger" 
                                            onclick="deleteTag(<?= $tagId ?>, '<?= htmlspecialchars($tag['name'], ENT_QUOTES) ?>')"
                                            title="Deletar tag">
                                        <i class="ki-duotone ki-trash fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<!--end::Card-->

<!--begin::Modal - Nova Tag-->
<?php if (\App\Helpers\Permission::can('tags.create')): ?>
<div class="modal fade" id="kt_modal_new_tag" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Nova Tag</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_new_tag_form" class="form" action="<?= \App\Helpers\Url::to('/tags') ?>" method="POST">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" class="form-control form-control-solid" placeholder="Nome da tag" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Cor</label>
                        <input type="color" name="color" class="form-control form-control-solid" value="#009EF7" />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" class="form-control form-control-solid" rows="3" placeholder="Descrição opcional"></textarea>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_new_tag_submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Nova Tag-->

<!--begin::Modal - Editar Tag-->
<?php if (\App\Helpers\Permission::can('tags.edit')): ?>
<div class="modal fade" id="kt_modal_edit_tag" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Tag</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_edit_tag_form" class="form" method="POST">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" id="edit_tag_name" class="form-control form-control-solid" placeholder="Nome da tag" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Cor</label>
                        <input type="color" name="color" id="edit_tag_color" class="form-control form-control-solid" value="#009EF7" />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" id="edit_tag_description" class="form-control form-control-solid" rows="3" placeholder="Descrição opcional"></textarea>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_edit_tag_submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Editar Tag-->

<?php 
$content = ob_get_clean(); 
$scripts = '
<style>
.tag-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.tag-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.querySelector("[data-kt-filter=\"search\"]");
    if (searchInput) {
        searchInput.addEventListener("keyup", function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll(".col-md-6, .col-lg-4, .col-xl-3");
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm) ? "" : "none";
            });
        });
    }
    
    const form = document.getElementById("kt_modal_new_tag_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_new_tag_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            fetch(form.action, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new URLSearchParams(new FormData(form))
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_new_tag"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao criar tag"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao criar tag");
            });
        });
    }
    
    const editForm = document.getElementById("kt_modal_edit_tag_form");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_edit_tag_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const tagId = editForm.getAttribute("data-tag-id");
            
            fetch("' . \App\Helpers\Url::to('/tags') . '/" + tagId, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new URLSearchParams(new FormData(editForm))
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_edit_tag"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao atualizar tag"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao atualizar tag");
            });
        });
    }
    
    window.editTag = function(tagId, name, color, description) {
        document.getElementById("edit_tag_name").value = name;
        document.getElementById("edit_tag_color").value = color;
        document.getElementById("edit_tag_description").value = description;
        document.getElementById("kt_modal_edit_tag_form").setAttribute("data-tag-id", tagId);
        
        const modal = new bootstrap.Modal(document.getElementById("kt_modal_edit_tag"));
        modal.show();
    };
    
    window.deleteTag = function(tagId, tagName) {
        if (!confirm("Tem certeza que deseja deletar a tag \"" + tagName + "\"?\\n\\nEsta ação não pode ser desfeita.")) {
            return;
        }
        
        fetch("' . \App\Helpers\Url::to('/tags') . '/" + tagId, {
            method: "DELETE",
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert("Erro: " + (data.message || "Erro ao deletar tag"));
            }
        })
        .catch(error => {
            alert("Erro ao deletar tag");
        });
    };
});
</script>';

include __DIR__ . '/../layouts/metronic/app.php';
?>

