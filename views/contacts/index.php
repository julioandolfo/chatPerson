<?php
$layout = 'layouts.metronic.app';
$title = 'Contatos';

// Content
ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center gap-2">
                <div class="d-flex align-items-center position-relative my-1">
                    <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <input type="text" id="kt_contacts_search" class="form-control form-control-solid w-250px ps-13" placeholder="Buscar contatos..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>" />
                </div>
                <button type="button" id="kt_contacts_search_btn" class="btn btn-primary">
                    <i class="ki-duotone ki-magnifier fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Buscar
                </button>
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex justify-content-end" data-kt-contacts-table-toolbar="base">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_contact">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Novo Contato
                </button>
            </div>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($contacts)): ?>
            <!--begin::Empty state-->
            <div class="text-center py-10">
                <i class="ki-duotone ki-profile-user fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhum contato encontrado</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando um novo contato ou aguarde novos contatos serem criados automaticamente.</div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_contact">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Novo Contato
                </button>
            </div>
            <!--end::Empty state-->
        <?php else: ?>
            <!--begin::Table-->
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_contacts_table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-125px">Contato</th>
                            <th class="min-w-125px">Email</th>
                            <th class="min-w-125px">Telefone</th>
                            <th class="min-w-100px">Conversas</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 fw-semibold">
                        <?php foreach ($contacts as $contact): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-45px me-5">
                                            <?php if (!empty($contact['avatar'])): ?>
                                                <img src="<?= htmlspecialchars($contact['avatar']) ?>" alt="<?= htmlspecialchars($contact['name']) ?>" />
                                            <?php else: ?>
                                                <div class="symbol-label fs-3 fw-semibold text-primary bg-light-primary">
                                                    <?= mb_substr(htmlspecialchars($contact['name']), 0, 1) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex justify-content-start flex-column">
                                            <a href="<?= \App\Helpers\Url::to('/contacts/' . $contact['id']) ?>" class="text-gray-800 fw-bold text-hover-primary mb-1">
                                                <?= htmlspecialchars($contact['name']) ?>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($contact['email'])): ?>
                                        <a href="mailto:<?= htmlspecialchars($contact['email']) ?>" class="text-gray-600 text-hover-primary">
                                            <?= htmlspecialchars($contact['email']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($contact['phone'])): ?>
                                        <a href="tel:<?= htmlspecialchars($contact['phone']) ?>" class="text-gray-600 text-hover-primary">
                                            <?= htmlspecialchars($contact['phone']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $conversationsCount = \App\Models\Conversation::where('contact_id', '=', $contact['id']);
                                    $count = count($conversationsCount);
                                    ?>
                                    <?php if ($count > 0): ?>
                                        <a href="<?= \App\Helpers\Url::to('/conversations?contact_id=' . $contact['id']) ?>" 
                                           class="badge badge-light-primary text-hover-primary" 
                                           data-bs-toggle="tooltip" 
                                           title="Clique para ver todas as conversas">
                                            <?= $count ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge badge-light-secondary"><?= $count ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php
                                    // ✅ Buscar conversa ABERTA do contato (não qualquer conversa)
                                    $openConversation = \App\Helpers\Database::fetch(
                                        "SELECT id, agent_id FROM conversations 
                                         WHERE contact_id = ? AND status = 'open' 
                                         ORDER BY updated_at DESC LIMIT 1",
                                        [$contact['id']]
                                    );
                                    ?>
                                    
                                    <?php if ($count > 0): ?>
                                        <!-- Botão para ver histórico de conversas -->
                                        <a href="<?= \App\Helpers\Url::to('/conversations?contact_id=' . $contact['id']) ?>" 
                                           class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1" 
                                           data-bs-toggle="tooltip" 
                                           title="Ver histórico de conversas">
                                            <i class="ki-duotone ki-message-text fs-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($openConversation): ?>
                                        <!-- Conversa aberta existe - botão para ir -->
                                        <a href="<?= \App\Helpers\Url::to('/conversations?id=' . $openConversation['id']) ?>" 
                                           class="btn btn-icon btn-bg-light btn-active-color-success btn-sm me-1" 
                                           data-bs-toggle="tooltip" 
                                           title="Ir para conversa aberta">
                                            <i class="ki-duotone ki-message-text-2 fs-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                        </a>
                                    <?php else: ?>
                                        <!-- Nenhuma conversa aberta - botão para iniciar nova -->
                                        <button type="button" 
                                                class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" 
                                                data-bs-toggle="tooltip" 
                                                title="Iniciar nova conversa"
                                                onclick="openNewConversationModal(<?= $contact['id'] ?>, '<?= htmlspecialchars(addslashes($contact['name'] ?? ''), ENT_QUOTES) ?>', '<?= htmlspecialchars($contact['phone'] ?? '', ENT_QUOTES) ?>')">
                                            <i class="ki-duotone ki-message-add fs-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <a href="<?= \App\Helpers\Url::to('/contacts/' . $contact['id']) ?>" 
                                       class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" 
                                       data-bs-toggle="tooltip" 
                                       title="Ver detalhes do contato">
                                        <i class="ki-duotone ki-eye fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm" 
                                            data-bs-toggle="tooltip" 
                                            title="Editar contato" 
                                            onclick="editContact(<?= $contact['id'] ?>)">
                                        <i class="ki-duotone ki-pencil fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!--end::Table-->

            <!--begin::Pagination-->
            <?php if (isset($pagination) && $pagination['pages'] > 1): ?>
            <div class="d-flex flex-stack flex-wrap pt-10">
                <div class="fs-6 fw-semibold text-gray-700">
                    Mostrando <?= ($pagination['page'] - 1) * $pagination['limit'] + 1 ?> a <?= min($pagination['page'] * $pagination['limit'], $pagination['total']) ?> de <?= $pagination['total'] ?> contatos
                </div>
                <ul class="pagination">
                    <?php if ($pagination['page'] > 1): ?>
                        <li class="page-item previous">
                            <a href="?page=<?= $pagination['page'] - 1 ?><?= !empty($filters['search']) ? '&search=' . urlencode($filters['search']) : '' ?>" class="page-link">
                                <i class="previous"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = max(1, $pagination['page'] - 2); $i <= min($pagination['pages'], $pagination['page'] + 2); $i++): ?>
                        <li class="page-item <?= $i == $pagination['page'] ? 'active' : '' ?>">
                            <a href="?page=<?= $i ?><?= !empty($filters['search']) ? '&search=' . urlencode($filters['search']) : '' ?>" class="page-link"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($pagination['page'] < $pagination['pages']): ?>
                        <li class="page-item next">
                            <a href="?page=<?= $pagination['page'] + 1 ?><?= !empty($filters['search']) ? '&search=' . urlencode($filters['search']) : '' ?>" class="page-link">
                                <i class="next"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php elseif (isset($pagination) && $pagination['total'] > 0): ?>
            <div class="d-flex flex-stack flex-wrap pt-10">
                <div class="fs-6 fw-semibold text-gray-700">
                    Mostrando <?= $pagination['total'] ?> contato<?= $pagination['total'] > 1 ? 's' : '' ?>
                </div>
            </div>
            <?php endif; ?>
            <!--end::Pagination-->
        <?php endif; ?>
    </div>
</div>
<!--end::Card-->

<!--begin::Modal - Novo Contato-->
<div class="modal fade" id="kt_modal_new_contact" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Novo Contato</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_new_contact_form" class="form" action="<?= \App\Helpers\Url::to('/contacts') ?>" method="POST">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="mb-5">
                        <h4 class="fw-bold mb-4">Informações Básicas</h4>
                        <div class="row g-5">
                            <!-- Avatar -->
                            <div class="col-md-12">
                                <label class="fw-semibold fs-6 mb-2">Avatar</label>
                                <div class="d-flex align-items-center gap-5">
                                    <div class="symbol symbol-60px">
                                        <div class="symbol-label fs-2x fw-semibold text-primary bg-light-primary" id="new_avatar_preview">U</div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <input type="file" name="avatar_file" class="form-control form-control-solid" accept="image/*" onchange="previewNewAvatar(this)" />
                                        <div class="form-text">Formatos aceitos: JPG, PNG, GIF. Tamanho máximo: 2MB</div>
                                    </div>
                                </div>
                            </div>
                            <!-- Nome -->
                            <div class="col-md-6">
                                <label class="required fw-semibold fs-6 mb-2">Nome</label>
                                <input type="text" name="name" class="form-control form-control-solid" 
                                       placeholder="Digite o nome" required />
                            </div>
                            <!-- Sobrenome -->
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">Sobrenome</label>
                                <input type="text" name="last_name" class="form-control form-control-solid" 
                                       placeholder="Digite o sobrenome" />
                            </div>
                            <!-- Email -->
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">E-mail</label>
                                <input type="email" name="email" class="form-control form-control-solid" 
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
                                           placeholder="4796544996" />
                                </div>
                            </div>
                            <!-- WhatsApp ID -->
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">WhatsApp ID</label>
                                <input type="text" name="whatsapp_id" class="form-control form-control-solid" 
                                       placeholder="554796544996@s.whatsapp.net" />
                            </div>
                        </div>
                    </div>
                    
                    <div class="separator my-5"></div>
                    
                    <div class="mb-5">
                        <h4 class="fw-bold mb-4">Localização</h4>
                        <div class="row g-5">
                            <!-- Cidade -->
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">Cidade</label>
                                <input type="text" name="city" class="form-control form-control-solid" 
                                       placeholder="Digite o nome da cidade" />
                            </div>
                            <!-- País -->
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">País</label>
                                <select name="country" class="form-select form-select-solid">
                                    <option value="">Selecione o país</option>
                                    <option value="BR">Brasil</option>
                                    <option value="US">Estados Unidos</option>
                                    <option value="AR">Argentina</option>
                                    <option value="MX">México</option>
                                    <option value="PT">Portugal</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="separator my-5"></div>
                    
                    <div class="mb-5">
                        <h4 class="fw-bold mb-4">Informações Adicionais</h4>
                        <div class="row g-5">
                            <!-- Biografia -->
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">Biografia</label>
                                <textarea name="bio" class="form-control form-control-solid" rows="3" 
                                          placeholder="Digite uma biografia"></textarea>
                            </div>
                            <!-- Empresa -->
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">Empresa</label>
                                <input type="text" name="company" class="form-control form-control-solid" 
                                       placeholder="Digite o nome da empresa" />
                            </div>
                        </div>
                    </div>
                    
                    <div class="separator my-5"></div>
                    
                    <!-- Redes Sociais -->
                    <div class="mb-5">
                        <h4 class="fw-bold mb-4">Redes Sociais</h4>
                        <div class="row g-3">
                            <!-- LinkedIn -->
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text bg-light-primary">
                                        <i class="fab fa-linkedin fs-4 text-primary"></i>
                                    </span>
                                    <input type="url" name="social_media[linkedin]" class="form-control form-control-solid" 
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
                                           placeholder="https://github.com/usuario" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_new_contact_submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Novo Contato-->

<script>
// Função para executar busca
function performSearch() {
    const searchInput = document.getElementById('kt_contacts_search');
    const search = searchInput?.value.trim() || '';
    
    let url = '<?= \App\Helpers\Url::to('/contacts') ?>';
    const params = [];
    
    if (search) {
        params.push('search=' + encodeURIComponent(search));
        // Resetar para página 1 ao buscar
        params.push('page=1');
    } else {
        // Se não há busca, remover parâmetros de busca e voltar para página 1
        url = '<?= \App\Helpers\Url::to('/contacts') ?>';
    }
    
    if (params.length > 0) {
        url += '?' + params.join('&');
    }
    
    window.location.href = url;
}

// Busca de contatos - Enter no input
document.getElementById('kt_contacts_search')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        performSearch();
    }
});

// Busca de contatos - Botão de buscar
document.getElementById('kt_contacts_search_btn')?.addEventListener('click', function(e) {
    e.preventDefault();
    performSearch();
});

function previewNewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('new_avatar_preview');
            preview.innerHTML = `<img src="${e.target.result}" alt="Avatar" class="symbol-label" />`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Formulário de novo contato
document.getElementById('kt_modal_new_contact_form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const submitButton = document.getElementById('kt_modal_new_contact_submit');
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
    
    submitButton.setAttribute('data-kt-indicator', 'on');
    submitButton.disabled = true;
    
    fetch(form.action, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        submitButton.removeAttribute('data-kt-indicator');
        submitButton.disabled = false;
        
        if (data.success) {
            // Fechar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_new_contact'));
            if (modal) {
                modal.hide();
            }
            location.reload();
        } else {
            alert('Erro: ' + (data.message || 'Erro ao salvar contato'));
        }
    })
    .catch(error => {
        submitButton.removeAttribute('data-kt-indicator');
        submitButton.disabled = false;
        alert('Erro ao salvar contato');
    });
});

// Abrir contato para edição
function editContact(id) {
    if (!id) return;
    window.location.href = '<?= \App\Helpers\Url::to('/contacts/') ?>' + id;
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

