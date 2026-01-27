<?php
$layout = 'layouts.metronic.app';
$title = $list['name'] ?? 'Lista';
$pageTitle = 'Detalhes da Lista';

ob_start();
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    <?php echo htmlspecialchars($list['name']); ?>
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="/dashboard" class="text-muted text-hover-primary">Home</a>
                    </li>
                    <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                    <li class="breadcrumb-item text-muted">
                        <a href="/contact-lists" class="text-muted text-hover-primary">Listas</a>
                    </li>
                    <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                    <li class="breadcrumb-item text-muted">#<?php echo $list['id']; ?></li>
                </ul>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modal_add_contact">
                    <i class="ki-duotone ki-plus fs-3"></i>
                    Adicionar Contato
                </button>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal_import_csv">
                    <i class="ki-duotone ki-file-up fs-3"></i>
                    Importar CSV
                </button>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <!-- Info Card -->
            <div class="card mb-5">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center mb-3">
                                <span class="fs-4 fw-bold me-3">Total de Contatos:</span>
                                <span class="badge badge-light-primary fs-3"><?php echo $total; ?></span>
                            </div>
                            <?php if ($list['description']): ?>
                            <div class="text-gray-700"><?php echo htmlspecialchars($list['description']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-light-danger btn-sm" onclick="clearList()">
                                <i class="ki-duotone ki-trash fs-3"></i>
                                Limpar Lista
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabela de Contatos -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Contatos</h3>
                    <div class="card-toolbar">
                        <div class="d-flex align-items-center position-relative my-1">
                            <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <input type="text" class="form-control form-control-sm w-250px ps-13" 
                                   placeholder="Buscar contato..." id="search_contact">
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-300 align-middle gs-0 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted bg-light">
                                    <th class="ps-4 min-w-200px">Nome</th>
                                    <th class="min-w-150px">Telefone</th>
                                    <th class="min-w-150px">Email</th>
                                    <th class="min-w-100px">Adicionado em</th>
                                    <th class="text-end pe-4 min-w-100px">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="contacts_tbody">
                                <?php if (empty($contacts)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-10">
                                        <div class="text-gray-600">
                                            <i class="ki-duotone ki-information fs-3x mb-3">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                            <div class="fw-bold fs-5">Nenhum contato nesta lista</div>
                                            <div class="fs-7 mt-2">Adicione contatos ou importe via CSV</div>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-circle symbol-40px me-3">
                                                <?php if ($contact['avatar']): ?>
                                                <img src="<?php echo $contact['avatar']; ?>" alt="">
                                                <?php else: ?>
                                                <div class="symbol-label bg-light-primary text-primary fs-6 fw-bold">
                                                    <?php echo strtoupper(substr($contact['name'], 0, 2)); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex flex-column">
                                                <span class="text-gray-900 fw-bold"><?php echo htmlspecialchars($contact['name']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($contact['phone'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($contact['email'] ?? '-'); ?></td>
                                    <td class="text-muted"><?php echo !empty($contact['added_at']) ? date('d/m/Y', strtotime($contact['added_at'])) : '-'; ?></td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-light-danger" 
                                                onclick="removeContact(<?php echo $contact['id']; ?>)">
                                            <i class="ki-duotone ki-trash fs-6"></i>
                                            Remover
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if ($total > $limit): ?>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-gray-600">
                            Mostrando <?php echo min($limit, $total); ?> de <?php echo $total; ?> contatos
                        </div>
                        <div>
                            <!-- Paginação aqui se necessário -->
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
</div>

<!-- Modal: Adicionar Contato -->
<div class="modal fade" id="modal_add_contact" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adicionar Contato</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form_add_contact">
                    <div class="mb-5">
                        <label class="form-label required">Buscar Contato</label>
                        <select class="form-select" id="contact_select" name="contact_id" required 
                                data-control="select2"
                                data-placeholder="Digite para buscar..."
                                data-allow-clear="true"
                                data-dropdown-parent="#modal_add_contact">
                        </select>
                        <div class="form-text">Digite o nome ou telefone do contato</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="addContact()">Adicionar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Importar CSV -->
<div class="modal fade" id="modal_import_csv" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importar Contatos via CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-5">
                    <strong>Formato do CSV:</strong> O arquivo deve ter as colunas: nome, telefone, email (opcionais: empresa, cidade, etc)
                    <br><a href="/assets/samples/contacts-template.csv" download>Baixar modelo de exemplo</a>
                </div>
                
                <form id="form_import_csv">
                    <div class="mb-5">
                        <label class="form-label required">Arquivo CSV</label>
                        <input type="file" class="form-control" name="csv_file" accept=".csv" required />
                    </div>
                    
                    <div class="mb-5">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="skip_duplicates" id="skip_duplicates" checked />
                            <label class="form-check-label" for="skip_duplicates">
                                Pular duplicados (não adicionar se o contato já existe)
                            </label>
                        </div>
                    </div>
                </form>
                
                <div id="import_progress" style="display:none;">
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 100%">
                            Importando...
                        </div>
                    </div>
                </div>
                
                <div id="import_result" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" id="btn_import" onclick="importCSV()">
                    <i class="ki-duotone ki-file-up fs-3"></i>
                    Importar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const listId = <?php echo $list['id']; ?>;

// Inicializar Select2 para busca de contatos quando o modal abrir
document.getElementById('modal_add_contact')?.addEventListener('shown.bs.modal', function() {
    initContactSelect2();
});

function initContactSelect2() {
    const $select = $('#contact_select');
    
    // Destruir instância anterior se existir
    if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy');
    }
    
    $select.select2({
        dropdownParent: $('#modal_add_contact'),
        placeholder: 'Digite para buscar contato...',
        allowClear: true,
        minimumInputLength: 2,
        language: {
            inputTooShort: function() {
                return 'Digite pelo menos 2 caracteres para buscar...';
            },
            noResults: function() {
                return 'Nenhum contato encontrado';
            },
            searching: function() {
                return 'Buscando...';
            }
        },
        ajax: {
            url: '/api/contacts/search',
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return {
                    q: params.term,
                    limit: 20
                };
            },
            processResults: function(data) {
                if (!data.success || !data.contacts) {
                    return { results: [] };
                }
                
                return {
                    results: data.contacts.map(function(contact) {
                        return {
                            id: contact.id,
                            text: contact.name + ' (' + (contact.phone || contact.email || 'Sem contato') + ')',
                            contact: contact
                        };
                    })
                };
            },
            cache: true
        },
        templateResult: function(contact) {
            if (contact.loading) {
                return contact.text;
            }
            
            const data = contact.contact || contact;
            const initials = (data.name || 'NC').substring(0, 2).toUpperCase();
            
            return $(`
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-circle symbol-35px me-3">
                        ${data.avatar 
                            ? '<img src="' + data.avatar + '" alt="">' 
                            : '<div class="symbol-label bg-light-primary text-primary fw-bold">' + initials + '</div>'
                        }
                    </div>
                    <div>
                        <div class="fw-bold">${data.name || 'Sem nome'}</div>
                        <div class="text-muted fs-7">${data.phone || ''} ${data.email ? '• ' + data.email : ''}</div>
                    </div>
                </div>
            `);
        },
        templateSelection: function(contact) {
            if (!contact.id) {
                return contact.text;
            }
            const data = contact.contact || contact;
            return data.name || contact.text;
        }
    });
}

function addContact() {
    const $select = $('#contact_select');
    const contactId = $select.val();
    
    if (!contactId) {
        toastr.error('Selecione um contato');
        return;
    }
    
    fetch(`/contact-lists/${listId}/contacts`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ contact_id: contactId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            toastr.success('Contato adicionado!');
            // Limpar seleção
            $select.val(null).trigger('change');
            setTimeout(() => location.reload(), 1000);
        } else {
            toastr.error(data.message || 'Erro ao adicionar contato');
        }
    })
    .catch(err => toastr.error('Erro de rede'));
}

function removeContact(contactId) {
    if (!confirm('Deseja remover este contato da lista?')) return;
    
    fetch(`/contact-lists/${listId}/contacts`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ contact_id: contactId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            toastr.success('Contato removido!');
            setTimeout(() => location.reload(), 1000);
        } else {
            toastr.error(data.message);
        }
    })
    .catch(err => toastr.error('Erro de rede'));
}

function clearList() {
    if (!confirm('Deseja remover TODOS os contatos desta lista? Esta ação não pode ser desfeita.')) return;
    
    fetch(`/contact-lists/${listId}/clear`, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success('Lista limpa!');
                setTimeout(() => location.reload(), 1000);
            } else {
                toastr.error(data.message);
            }
        })
        .catch(err => toastr.error('Erro de rede'));
}

function importCSV() {
    const form = document.getElementById('form_import_csv');
    const formData = new FormData(form);
    
    document.getElementById('btn_import').disabled = true;
    document.getElementById('import_progress').style.display = 'block';
    document.getElementById('import_result').style.display = 'none';
    
    fetch(`/contact-lists/${listId}/import-csv`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('btn_import').disabled = false;
        document.getElementById('import_progress').style.display = 'none';
        document.getElementById('import_result').style.display = 'block';
        
        if (data.success) {
            const result = data.result;
            document.getElementById('import_result').innerHTML = `
                <div class="alert alert-success">
                    <strong>Import concluído!</strong><br>
                    ✅ ${result.imported} contatos importados<br>
                    ${result.skipped > 0 ? `⚠️ ${result.skipped} contatos pulados` : ''}
                    ${result.errors && result.errors.length > 0 ? `<br><br><strong>Erros:</strong><br>${result.errors.join('<br>')}` : ''}
                </div>
            `;
            
            setTimeout(() => location.reload(), 3000);
        } else {
            document.getElementById('import_result').innerHTML = `
                <div class="alert alert-danger">
                    <strong>Erro:</strong> ${data.message}
                </div>
            `;
        }
    })
    .catch(err => {
        document.getElementById('btn_import').disabled = false;
        document.getElementById('import_progress').style.display = 'none';
        document.getElementById('import_result').style.display = 'block';
        document.getElementById('import_result').innerHTML = `
            <div class="alert alert-danger">
                <strong>Erro de rede:</strong> ${err.message}
            </div>
        `;
    });
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/metronic/app.php';
?>
