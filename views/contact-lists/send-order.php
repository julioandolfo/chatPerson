<?php
/**
 * Modal: Configurar Ordem de Envio da Lista
 * Incluir este modal nas views de lista
 */
?>

<!-- Modal Ordem de Envio -->
<div class="modal fade" id="modal_send_order" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Configurar Ordem de Envio</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="send_order_form">
                    <input type="hidden" id="list_id_order" name="list_id" />
                    
                    <div class="mb-10">
                        <label class="form-label required">Ordem de Envio</label>
                        <select class="form-select" name="send_order" id="send_order_type" onchange="toggleOrderConfig(this.value)">
                            <option value="default">Padrão (ordem de adição na lista)</option>
                            <option value="random">Aleatório</option>
                            <option value="asc">Crescente (ASC)</option>
                            <option value="desc">Decrescente (DESC)</option>
                            <option value="custom">Personalizado (SQL)</option>
                        </select>
                    </div>
                    
                    <!-- Config ASC/DESC -->
                    <div class="mb-10" id="order_field_config" style="display:none;">
                        <label class="form-label">Campo para Ordenação</label>
                        <input type="text" class="form-control" id="order_field" placeholder="Ex: created_at, id, name" />
                        <div class="form-text">Nome do campo da tabela contacts</div>
                    </div>
                    
                    <!-- Config Custom -->
                    <div class="mb-10" id="custom_order_config" style="display:none;">
                        <label class="form-label">SQL Personalizado</label>
                        <textarea class="form-control font-monospace" id="custom_order_sql" rows="4" 
                                  placeholder="Ex: CASE WHEN estado = 'SP' THEN 1 ELSE 2 END, name ASC"></textarea>
                        <div class="form-text">ORDER BY customizado (sem a palavra ORDER BY)</div>
                    </div>
                    
                    <!-- Condições WHERE -->
                    <div class="mb-10">
                        <label class="form-label">Filtro WHERE (opcional)</label>
                        <textarea class="form-control font-monospace" id="custom_where" rows="3" 
                                  placeholder="Ex: estado = 'SP' AND interesse = 'produto'"></textarea>
                        <div class="form-text">Filtrar contatos da lista (sem a palavra WHERE)</div>
                    </div>
                    
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="ki-duotone ki-information-5 fs-2 me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        <div>
                            <strong>Dica:</strong> A ordem configurada será aplicada ao enviar campanhas com esta lista.
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveSendOrder()">Salvar Ordem</button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleOrderConfig(orderType) {
    document.getElementById('order_field_config').style.display = 
        ['asc', 'desc'].includes(orderType) ? 'block' : 'none';
    document.getElementById('custom_order_config').style.display = 
        orderType === 'custom' ? 'block' : 'none';
}

function openSendOrderModal(listId) {
    document.getElementById('list_id_order').value = listId;
    
    // Carregar configuração atual via endpoint dedicado
    fetch(`/api/contact-lists`)
        .then(r => r.json())
        .then(result => {
            if (result.success && result.lists) {
                const list = result.lists.find(l => l.id == listId);
                if (list) {
                    document.getElementById('send_order_type').value = list.send_order || 'default';
                    toggleOrderConfig(list.send_order || 'default');
                    
                    const config = list.send_order_config ? JSON.parse(list.send_order_config) : {};
                    document.getElementById('order_field').value = config.field || '';
                    document.getElementById('custom_order_sql').value = config.order_by || '';
                    document.getElementById('custom_where').value = config.where || '';
                }
            }
        });
    
    const modal = new bootstrap.Modal(document.getElementById('modal_send_order'));
    modal.show();
}

function saveSendOrder() {
    const listId = document.getElementById('list_id_order').value;
    const orderType = document.getElementById('send_order_type').value;
    
    const config = {};
    
    if (['asc', 'desc'].includes(orderType)) {
        config.field = document.getElementById('order_field').value;
        config.direction = orderType;
    } else if (orderType === 'custom') {
        config.order_by = document.getElementById('custom_order_sql').value;
    }
    
    const whereValue = document.getElementById('custom_where').value;
    if (whereValue) {
        config.where = whereValue;
    }
    
    const data = {
        send_order: orderType,
        send_order_config: Object.keys(config).length > 0 ? config : null
    };
    
    fetch(`/contact-lists/${listId}/send-order`, {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            toastr.success('Ordem de envio configurada!');
            bootstrap.Modal.getInstance(document.getElementById('modal_send_order')).hide();
            location.reload();
        } else {
            toastr.error(result.message);
        }
    })
    .catch(err => toastr.error('Erro ao salvar'));
}
</script>
