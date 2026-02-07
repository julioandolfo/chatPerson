<!-- Galeria de Mockups no Sidebar -->
<div class="card mockup-gallery-card mb-5">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-images text-success me-2"></i>
            Mockups Gerados
        </h3>
        <div class="card-toolbar">
            <button type="button" class="btn btn-sm btn-icon btn-light-success" title="Atualizar" onclick="loadMockupGallery()">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>
    <div class="card-body p-3" id="mockupGalleryContainer">
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Carregar galeria de mockups
 */
async function loadMockupGallery() {
    const container = document.getElementById('mockupGalleryContainer');
    const conversationId = currentConversationId || parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');

    if (!conversationId) {
        container.innerHTML = '<div class="text-muted text-center py-3">Selecione uma conversa</div>';
        return;
    }

    try {
        const response = await fetch(`/api/conversations/${conversationId}/mockups`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar mockups');
        }

        if (data.data.length === 0) {
            container.innerHTML = `
                <div class="mockup-empty-state">
                    <i class="fas fa-image"></i>
                    <div class="fw-bold">Nenhum mockup gerado</div>
                    <div class="text-muted small mt-2">Clique no botão verde<br>para criar seu primeiro mockup</div>
                </div>
            `;
            return;
        }

        let html = '<div class="mockup-gallery">';
        data.data.forEach(mockup => {
            const modeColors = {
                'ai': 'primary',
                'manual': 'danger',
                'hybrid': 'info'
            };
            const modeLabels = {
                'ai': '🤖 IA',
                'manual': '✋ Manual',
                'hybrid': '🔀 Híbrido'
            };
            const modeColor = modeColors[mockup.generation_mode] || 'secondary';
            const modeLabel = modeLabels[mockup.generation_mode] || mockup.generation_mode;

            const date = new Date(mockup.created_at);
            const dateStr = date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) + ' ' + 
                           date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

            html += `
                <div class="mockup-gallery-item" data-mockup-id="${mockup.id}">
                    <span class="mockup-badge badge-${mockup.generation_mode}">${modeLabel}</span>
                    <img src="/${mockup.result_thumbnail_path || mockup.result_image_path}" 
                         alt="Mockup" 
                         onclick="viewMockup(${mockup.id})"
                         class="mockup-gallery-thumbnail">
                    <div class="mockup-gallery-item-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">${dateStr}</span>
                            ${mockup.sent_as_message ? '<span class="badge badge-success badge-sm">Enviado</span>' : ''}
                        </div>
                    </div>
                    <div class="mockup-gallery-item-actions">
                        <button class="btn btn-sm btn-light-primary" onclick="viewMockup(${mockup.id})" title="Visualizar">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${!mockup.sent_as_message ? `
                        <button class="btn btn-sm btn-light-success" onclick="sendMockupAsMessage(${mockup.id})" title="Enviar">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                        ` : ''}
                        <button class="btn btn-sm btn-light-danger" onclick="deleteMockup(${mockup.id})" title="Excluir">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        html += '</div>';

        container.innerHTML = html;

    } catch (error) {
        console.error('Erro ao carregar mockups:', error);
        container.innerHTML = `
            <div class="alert alert-danger mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Erro ao carregar mockups
            </div>
        `;
    }
}

/**
 * Visualizar mockup
 */
async function viewMockup(mockupId) {
    try {
        const response = await fetch(`/api/mockups/${mockupId}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar mockup');
        }

        const mockup = data.data;
        const modeLabels = {
            'ai': '🤖 IA Automática',
            'manual': '✋ Manual',
            'hybrid': '🔀 Híbrido'
        };

        let infoHtml = `
            <div class="mb-3">
                <strong>Modo:</strong> ${modeLabels[mockup.generation_mode] || mockup.generation_mode}<br>
                <strong>Criado:</strong> ${new Date(mockup.created_at).toLocaleString('pt-BR')}<br>
                <strong>Tempo:</strong> ${(mockup.processing_time / 1000).toFixed(1)}s
        `;

        if (mockup.total_cost > 0) {
            infoHtml += `<br><strong>Custo:</strong> $${mockup.total_cost.toFixed(4)}`;
        }

        infoHtml += '</div>';

        Swal.fire({
            title: 'Mockup Gerado',
            html: `
                ${infoHtml}
                <img src="/${mockup.result_image_path}" class="img-fluid rounded" style="max-width: 100%;">
            `,
            width: '800px',
            showConfirmButton: true,
            showCancelButton: true,
            showDenyButton: !mockup.sent_as_message,
            confirmButtonText: '<i class="fas fa-download me-1"></i> Download',
            denyButtonText: '<i class="fas fa-paper-plane me-1"></i> Enviar',
            cancelButtonText: 'Fechar',
            preConfirm: () => {
                // Download
                const link = document.createElement('a');
                link.href = '/' + mockup.result_image_path;
                link.download = 'mockup_' + mockupId + '.png';
                link.click();
            },
            preDeny: () => {
                // Enviar
                sendMockupAsMessage(mockupId);
            }
        });

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message
        });
    }
}

/**
 * Deletar mockup
 */
async function deleteMockup(mockupId) {
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Confirmar Exclusão',
        text: 'Tem certeza que deseja excluir este mockup?',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    });

    if (!result.isConfirmed) return;

    try {
        const response = await fetch(`/api/mockups/${mockupId}`, {
            method: 'DELETE'
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Erro ao excluir mockup');
        }

        Swal.fire({
            icon: 'success',
            title: 'Excluído!',
            text: 'Mockup excluído com sucesso',
            timer: 2000,
            showConfirmButton: false
        });

        // Recarregar galeria
        loadMockupGallery();

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message
        });
    }
}

// Carregar galeria ao iniciar
document.addEventListener('DOMContentLoaded', () => {
    // Aguardar um pouco para garantir que a conversa foi selecionada
    setTimeout(() => {
        loadMockupGallery();
    }, 1000);
});

// Recarregar ao trocar de conversa
if (typeof window.addEventListener !== 'undefined') {
    window.addEventListener('conversationChanged', () => {
        setTimeout(() => {
            loadMockupGallery();
        }, 500);
    });
}
</script>

<style>
.mockup-gallery-thumbnail {
    width: 100%;
    border-radius: 4px;
    cursor: pointer;
    transition: transform 0.2s;
}

.mockup-gallery-thumbnail:hover {
    transform: scale(1.05);
}
</style>
