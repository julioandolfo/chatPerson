<?php
$layout = 'layouts.metronic.app';
$title = 'Contato - ' . htmlspecialchars($contact['name'] ?? '');

// Content
ob_start();
?>
<!--begin::Layout-->
<div class="d-flex flex-column flex-xl-row">
    <!--begin::Sidebar-->
    <div class="flex-column flex-lg-row-auto w-100 w-xl-350px mb-10">
        <!--begin::Card - Informações do Contato-->
        <div class="card card-flush mb-5">
            <div class="card-body pt-15">
                    <div class="d-flex flex-center flex-column mb-5">
                    <div class="symbol symbol-100px mb-5 position-relative">
                        <?php if (!empty($contact['avatar'])): ?>
                            <img src="<?= htmlspecialchars($contact['avatar']) ?>" alt="<?= htmlspecialchars($contact['name']) ?>" class="symbol-label" />
                        <?php else: ?>
                            <div class="symbol-label fs-2x fw-semibold text-primary bg-light-primary">
                                <?= mb_substr(htmlspecialchars($contact['name'] ?? 'U'), 0, 1) ?>
                            </div>
                        <?php endif; ?>
                        <button type="button" class="btn btn-icon btn-sm btn-light-primary position-absolute bottom-0 end-0 rounded-circle" onclick="document.getElementById('avatar_upload').click()" title="Alterar avatar">
                            <i class="ki-duotone ki-pencil fs-6">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </button>
                        <input type="file" id="avatar_upload" name="avatar" accept="image/*" style="display: none;" onchange="uploadAvatar(<?= $contact['id'] ?>, this.files[0])" />
                    </div>
                    <h3 class="text-gray-800 fw-bold mb-3">
                        <?= htmlspecialchars($contact['name'] ?? '') ?>
                        <?php if (!empty($contact['last_name'])): ?>
                            <?= htmlspecialchars($contact['last_name']) ?>
                        <?php endif; ?>
                    </h3>
                    <div class="mb-9">
                        <?php if (!empty($contact['phone'])): ?>
                            <div class="text-gray-600 fs-4 fw-semibold mb-2"><?= htmlspecialchars($contact['phone']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($contact['whatsapp_id'])): ?>
                            <div class="text-gray-500 fs-7 d-flex align-items-center justify-content-center">
                                <i class="ki-duotone ki-abstract-26 fs-5 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <?= htmlspecialchars($contact['whatsapp_id']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="text-center mb-5">
                        <div class="text-muted fs-7 mb-2">
                            <i class="ki-duotone ki-calendar fs-6 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                            Criado <?= $contact['created_at'] ? date('d/m/Y', strtotime($contact['created_at'])) : '-' ?>
                            <?php if (!empty($contact['last_activity_at'])): ?>
                                • Última atividade <?= date('d/m/Y H:i', strtotime($contact['last_activity_at'])) ?>
                            <?php endif; ?>
                        </div>
                        <?php
                        // Obter tags do contato (se houver sistema de tags)
                        $contactTags = [];
                        if (class_exists('\App\Models\Tag')) {
                            try {
                                $contactTags = \App\Models\Tag::getByContact($contact['id'] ?? 0);
                            } catch (\Exception $e) {
                                // Sistema de tags pode não estar implementado ainda
                            }
                        }
                        ?>
                        <?php if (!empty($contactTags)): ?>
                            <div class="d-flex flex-wrap gap-2 justify-content-center">
                                <?php foreach ($contactTags as $tag): ?>
                                    <span class="badge badge-light-primary"><?= htmlspecialchars($tag['name']) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="addTag(<?= $contact['id'] ?>)">
                                <i class="ki-duotone ki-plus fs-6"></i>
                                Etiqueta
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <!--begin::Ações de Conversa-->
                    <?php
                    // Buscar conversa ABERTA do contato
                    $openConversation = \App\Helpers\Database::fetch(
                        "SELECT id, agent_id FROM conversations 
                         WHERE contact_id = ? AND status = 'open' 
                         ORDER BY updated_at DESC LIMIT 1",
                        [$contact['id']]
                    );
                    ?>
                    <div class="d-flex justify-content-center gap-2 mt-5">
                        <?php if ($openConversation): ?>
                            <a href="<?= \App\Helpers\Url::to('/conversations?id=' . $openConversation['id']) ?>" class="btn btn-success">
                                <i class="ki-duotone ki-message-text-2 fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                Ir para Conversa
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn btn-primary" onclick="openNewConversationModal(<?= $contact['id'] ?>, '<?= htmlspecialchars(addslashes($contact['name'] ?? ''), ENT_QUOTES) ?>', '<?= htmlspecialchars($contact['phone'] ?? '', ENT_QUOTES) ?>')">
                                <i class="ki-duotone ki-message-add fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                Iniciar Nova Conversa
                            </button>
                        <?php endif; ?>
                    </div>
                    <!--end::Ações de Conversa-->
                </div>
            </div>
        </div>
        <!--end::Card - Informações do Contato-->
    </div>
    <!--end::Sidebar-->

    <!--begin::Content-->
    <div class="flex-lg-row-fluid ms-xl-10">
        <!--begin::Card - Editar Contato-->
        <div class="card">
            <div class="card-header border-0 pt-5">
                <div class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Alterar detalhes do contato</span>
                </div>
                <div class="card-toolbar">
                    <a href="<?= \App\Helpers\Url::to('/contacts') ?>" class="btn btn-sm btn-light">
                        <i class="ki-duotone ki-arrow-left fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Voltar
                    </a>
                </div>
            </div>
            <form id="kt_contact_edit_form" class="form">
                <div class="card-body pt-0">
                    <div class="row g-5">
                        <!-- Avatar -->
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Avatar</label>
                            <div class="d-flex align-items-center gap-5">
                                <div class="symbol symbol-60px">
                                    <?php if (!empty($contact['avatar'])): ?>
                                        <img src="<?= htmlspecialchars($contact['avatar']) ?>" alt="Avatar" class="symbol-label" id="avatar_preview" />
                                    <?php else: ?>
                                        <div class="symbol-label fs-2x fw-semibold text-primary bg-light-primary" id="avatar_preview">
                                            <?= mb_substr(htmlspecialchars($contact['name'] ?? 'U'), 0, 1) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" name="avatar_file" class="form-control form-control-solid" accept="image/*" onchange="previewAvatar(this)" />
                                    <div class="form-text">Formatos aceitos: JPG, PNG, GIF. Tamanho máximo: 2MB</div>
                                </div>
                            </div>
                        </div>
                        <!-- Nome -->
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Nome</label>
                            <input type="text" name="name" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($contact['name'] ?? '') ?>" required />
                        </div>
                        <!-- Sobrenome -->
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Sobrenome</label>
                            <input type="text" name="last_name" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($contact['last_name'] ?? '') ?>" 
                                   placeholder="Digite o sobrenome" />
                        </div>
                        <!-- Email -->
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">E-mail</label>
                            <input type="email" name="email" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($contact['email'] ?? '') ?>" 
                                   placeholder="Digite o endereço de e-mail" />
                        </div>
                        <!-- Telefone -->
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Telefone</label>
                            <div class="input-group">
                                <select class="form-select form-select-solid" name="phone_country_code" style="max-width: 100px;">
                                    <option value="BR" selected>BR</option>
                                    <option value="US">US</option>
                                    <option value="AR">AR</option>
                                    <option value="MX">MX</option>
                                </select>
                                <span class="input-group-text">+55</span>
                                <input type="text" name="phone" class="form-control form-control-solid" 
                                       value="<?= htmlspecialchars($contact['phone'] ?? '') ?>" 
                                       placeholder="4796544996" />
                            </div>
                        </div>
                        <!-- Cidade -->
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Cidade</label>
                            <input type="text" name="city" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($contact['city'] ?? '') ?>" 
                                   placeholder="Digite o nome da cidade" />
                        </div>
                        <!-- País -->
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">País</label>
                            <select name="country" class="form-select form-select-solid">
                                <option value="">Selecione o país</option>
                                <option value="BR" <?= ($contact['country'] ?? '') === 'BR' ? 'selected' : '' ?>>Brasil</option>
                                <option value="US" <?= ($contact['country'] ?? '') === 'US' ? 'selected' : '' ?>>Estados Unidos</option>
                                <option value="AR" <?= ($contact['country'] ?? '') === 'AR' ? 'selected' : '' ?>>Argentina</option>
                                <option value="MX" <?= ($contact['country'] ?? '') === 'MX' ? 'selected' : '' ?>>México</option>
                                <option value="PT" <?= ($contact['country'] ?? '') === 'PT' ? 'selected' : '' ?>>Portugal</option>
                            </select>
                        </div>
                        <!-- Biografia -->
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Biografia</label>
                            <textarea name="bio" class="form-control form-control-solid" rows="3" 
                                      placeholder="Digite uma biografia"><?= htmlspecialchars($contact['bio'] ?? '') ?></textarea>
                        </div>
                        <!-- Empresa -->
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Empresa</label>
                            <input type="text" name="company" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($contact['company'] ?? '') ?>" 
                                   placeholder="Digite o nome da empresa" />
                        </div>
                    </div>
                    
                    <!-- Redes Sociais -->
                    <div class="separator my-5"></div>
                    <div class="mb-5">
                        <h4 class="fw-bold mb-4">Editar redes sociais</h4>
                        <?php
                        $socialMedia = [];
                        if (!empty($contact['social_media'])) {
                            $socialMedia = is_string($contact['social_media']) 
                                ? json_decode($contact['social_media'], true) ?? [] 
                                : $contact['social_media'];
                        }
                        ?>
                        <div class="row g-3">
                            <!-- LinkedIn -->
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text bg-light-primary">
                                        <i class="fab fa-linkedin fs-4 text-primary"></i>
                                    </span>
                                    <input type="url" name="social_media[linkedin]" class="form-control form-control-solid" 
                                           value="<?= htmlspecialchars($socialMedia['linkedin'] ?? '') ?>" 
                                           placeholder="https://linkedin.com/in/usuario" />
                                </div>
                            </div>
                            <!-- Facebook -->
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text bg-light-info">
                                        <i class="fab fa-facebook fs-4 text-info"></i>
                                    </span>
                                    <input type="url" name="social_media[facebook]" class="form-control form-control-solid" 
                                           value="<?= htmlspecialchars($socialMedia['facebook'] ?? '') ?>" 
                                           placeholder="https://facebook.com/usuario" />
                                </div>
                            </div>
                            <!-- Instagram -->
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text bg-light-danger">
                                        <i class="fab fa-instagram fs-4 text-danger"></i>
                                    </span>
                                    <input type="url" name="social_media[instagram]" class="form-control form-control-solid" 
                                           value="<?= htmlspecialchars($socialMedia['instagram'] ?? '') ?>" 
                                           placeholder="https://instagram.com/usuario" />
                                </div>
                            </div>
                            <!-- Twitter/X -->
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text bg-light-dark">
                                        <i class="fab fa-twitter fs-4 text-dark"></i>
                                    </span>
                                    <input type="url" name="social_media[twitter]" class="form-control form-control-solid" 
                                           value="<?= htmlspecialchars($socialMedia['twitter'] ?? '') ?>" 
                                           placeholder="https://twitter.com/usuario" />
                                </div>
                            </div>
                            <!-- GitHub -->
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text bg-light-dark">
                                        <i class="fab fa-github fs-4 text-dark"></i>
                                    </span>
                                    <input type="url" name="social_media[github]" class="form-control form-control-solid" 
                                           value="<?= htmlspecialchars($socialMedia['github'] ?? '') ?>" 
                                           placeholder="https://github.com/usuario" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end py-6 px-9">
                    <button type="reset" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_contact_edit_submit" class="btn btn-primary">
                        <span class="indicator-label">Atualizar contato</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
        <!--end::Card - Editar Contato-->
    </div>
    <!--end::Content-->
</div>
<!--end::Layout-->

<!--begin::Modal - Adicionar Tag-->
<div class="modal fade" id="kt_modal_add_tag" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Adicionar Etiqueta</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_add_tag_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Etiqueta</label>
                        <input type="text" name="tag_name" class="form-control form-control-solid" 
                               placeholder="Digite o nome da etiqueta" required />
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Adicionar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Adicionar Tag-->

<script>
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("kt_contact_edit_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_contact_edit_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            
            // Processar telefone com código do país
            const phoneCountryCode = formData.get('phone_country_code') || 'BR';
            const phone = formData.get('phone') || '';
            if (phone) {
                formData.set('phone', phone);
            }
            
            // Processar redes sociais
            const socialMedia = {};
            ['linkedin', 'facebook', 'instagram', 'twitter', 'github'].forEach(platform => {
                const value = formData.get(`social_media[${platform}]`);
                if (value) {
                    socialMedia[platform] = value;
                }
            });
            formData.delete('social_media[linkedin]');
            formData.delete('social_media[facebook]');
            formData.delete('social_media[instagram]');
            formData.delete('social_media[twitter]');
            formData.delete('social_media[github]');
            formData.append('social_media', JSON.stringify(socialMedia));
            
            fetch("<?= \App\Helpers\Url::to('/contacts/' . $contact['id']) ?>", {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao atualizar contato"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao atualizar contato");
            });
        });
    }
});

function addTag(contactId) {
    const modal = new bootstrap.Modal(document.getElementById("kt_modal_add_tag"));
    modal.show();
    
    const form = document.getElementById("kt_modal_add_tag_form");
    form.onsubmit = function(e) {
        e.preventDefault();
        // TODO: Implementar adição de tag
        alert('Sistema de tags será implementado em breve');
        modal.hide();
    };
}

function editContact(id) {
    // Scroll para o formulário de edição
    document.getElementById("kt_contact_edit_form")?.scrollIntoView({ behavior: 'smooth' });
}

function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('avatar_preview');
            if (preview.tagName === 'IMG') {
                preview.src = e.target.result;
            } else {
                preview.innerHTML = `<img src="${e.target.result}" alt="Avatar" class="symbol-label" />`;
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function uploadAvatar(contactId, file) {
    if (!file) return;
    
    const formData = new FormData();
    formData.append('avatar', file);
    
    fetch("<?= \App\Helpers\Url::to('/contacts/') ?>" + contactId + "/avatar", {
        method: "POST",
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert("Erro: " + (data.message || "Erro ao fazer upload do avatar"));
        }
    })
    .catch(error => {
        alert("Erro ao fazer upload do avatar");
    });
}

// ✅ NOVO: Abrir modal de nova conversa com dados do contato
function openNewConversationModal(contactId, contactName, contactPhone) {
    if (typeof Swal === 'undefined') {
        alert('Erro: SweetAlert não carregado');
        return;
    }
    
    // Buscar integrações WhatsApp disponíveis
    fetch('<?= \App\Helpers\Url::to('/api/whatsapp/accounts') ?>', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(accountsData => {
        const accounts = accountsData.accounts || [];
        let accountsOptions = '<option value="">Selecione uma integração</option>';
        accounts.forEach(acc => {
            if (acc.status === 'active') {
                accountsOptions += `<option value="${acc.id}">${acc.name} (${acc.phone_number || 'Sem número'})</option>`;
            }
        });
        
        Swal.fire({
            title: 'Iniciar Nova Conversa',
            html: `
                <div class="text-start">
                    <div class="mb-4 p-3 bg-light-primary rounded">
                        <div class="d-flex align-items-center">
                            <i class="ki-duotone ki-profile-user fs-2x text-primary me-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div>
                                <div class="fw-bold">${contactName || 'Contato'}</div>
                                <div class="text-muted fs-7">${contactPhone || 'Sem telefone'}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label required">Integração WhatsApp</label>
                        <select id="swal_whatsapp_account" class="form-select">
                            ${accountsOptions}
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label required">Mensagem inicial</label>
                        <textarea id="swal_message" class="form-control" rows="3" placeholder="Digite a mensagem..."></textarea>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="ki-duotone ki-send fs-4 me-1"><span class="path1"></span><span class="path2"></span></i> Enviar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#009ef7',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                const whatsappAccountId = document.getElementById('swal_whatsapp_account').value;
                const message = document.getElementById('swal_message').value.trim();
                
                if (!whatsappAccountId) {
                    Swal.showValidationMessage('Selecione uma integração WhatsApp');
                    return false;
                }
                if (!message) {
                    Swal.showValidationMessage('Digite uma mensagem');
                    return false;
                }
                
                return fetch('<?= \App\Helpers\Url::to('/conversations/new') ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        channel: 'whatsapp',
                        whatsapp_account_id: whatsappAccountId,
                        name: contactName,
                        phone: contactPhone,
                        message: message
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Erro ao criar conversa');
                    }
                    return data;
                })
                .catch(error => {
                    Swal.showValidationMessage(error.message);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                Swal.fire({
                    icon: 'success',
                    title: 'Conversa iniciada!',
                    text: 'Redirecionando...',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    if (result.value.conversation_id) {
                        window.location.href = '<?= \App\Helpers\Url::to('/conversations') ?>?id=' + result.value.conversation_id;
                    } else {
                        window.location.href = '<?= \App\Helpers\Url::to('/conversations') ?>';
                    }
                });
            }
        });
    })
    .catch(error => {
        console.error('Erro ao carregar integrações:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Não foi possível carregar as integrações WhatsApp'
        });
    });
}
</script>

<?php 
$content = ob_get_clean(); 
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>

