<!-- Mockup Gallery - Funções JS (conteúdo renderizado na aba Mockups do sidebar) -->
<script>
// Helper para normalizar URLs de imagem
if (typeof normalizeImageUrl === 'undefined') {
    function normalizeImageUrl(url) {
        if (!url) return '';
        if (url.startsWith('http://') || url.startsWith('https://')) return url;
        if (url.startsWith('/')) return url;
        return '/' + url;
    }
}

/**
 * Carregar galeria de mockups na aba do sidebar
 */
async function loadMockupGallery() {
    const container = document.getElementById('mockupGalleryContainer');
    if (!container) return;

    const conversationId = window.currentConversationId || null;

    if (!conversationId) {
        container.innerHTML = '<div class="text-muted text-center py-4 fs-7">Selecione uma conversa</div>';
        return;
    }

    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>';

    try {
        const response = await fetch(`/api/conversations/${conversationId}/mockups`);
        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch(e) { throw new Error('Erro ao carregar mockups'); }

        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar mockups');
        }

        if (data.data.length === 0) {
            container.innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-image fs-2x text-muted mb-3 d-block" style="opacity: 0.4;"></i>
                    <div class="fw-bold text-muted fs-7">Nenhum mockup gerado</div>
                    <div class="text-muted small mt-1">Clique no <i class="fas fa-plus text-success"></i> para criar</div>
                </div>
            `;
            return;
        }

        let html = '<div class="d-flex flex-column gap-3 px-2 py-1">';
        data.data.forEach(mockup => {
            const modeLabels = { 'ai': '🤖 IA', 'manual': '✋ Manual', 'hybrid': '🔀 Híbrido' };
            const modeLabel = modeLabels[mockup.generation_mode] || mockup.generation_mode;

            const date = new Date(mockup.created_at);
            const dateStr = date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) + ' ' + 
                           date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

            const thumbUrl = normalizeImageUrl(mockup.result_thumbnail_path || mockup.result_image_path);

            html += `
                <div class="mockup-sidebar-item" data-mockup-id="${mockup.id}">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="mockup-sidebar-thumb" onclick="viewMockup(${mockup.id})" style="cursor: pointer;">
                            <img src="${thumbUrl}" alt="Mockup" 
                                 style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid var(--bs-border-color);">
                        </div>
                        <div class="flex-fill" style="min-width: 0;">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="fs-8 text-muted">${modeLabel}</span>
                                ${mockup.sent_as_message ? '<span class="badge badge-light-success fs-9">Enviado</span>' : ''}
                            </div>
                            <div class="fs-8 text-muted mb-2">${dateStr}</div>
                            <div class="d-flex gap-1">
                                <button class="btn btn-icon btn-sm btn-light-primary" onclick="viewMockup(${mockup.id})" title="Visualizar" style="width: 28px; height: 28px;">
                                    <i class="fas fa-eye fs-8"></i>
                                </button>
                                ${!mockup.sent_as_message ? `
                                <button class="btn btn-icon btn-sm btn-light-success" onclick="sendMockupFromGallery(${mockup.id})" title="Enviar" style="width: 28px; height: 28px;">
                                    <i class="fas fa-paper-plane fs-8"></i>
                                </button>` : ''}
                                <button class="btn btn-icon btn-sm btn-light-danger" onclick="deleteMockup(${mockup.id})" title="Excluir" style="width: 28px; height: 28px;">
                                    <i class="fas fa-trash fs-8"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;

    } catch (error) {
        console.error('Erro ao carregar mockups:', error);
        container.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-exclamation-triangle text-danger fs-4 mb-2 d-block"></i>
                <div class="text-muted fs-7">Erro ao carregar mockups</div>
            </div>
        `;
    }
}

/**
 * Visualizar mockup em modal
 */
async function viewMockup(mockupId) {
    try {
        const response = await fetch(`/api/mockups/${mockupId}`);
        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch(e) { throw new Error('Erro ao carregar mockup'); }

        if (!data.success) throw new Error(data.error || 'Erro ao carregar mockup');

        const mockup = data.data;
        const modeLabels = { 'ai': '🤖 IA Automática', 'manual': '✋ Manual', 'hybrid': '🔀 Híbrido' };

        let infoHtml = `
            <div class="mb-3 text-start">
                <strong>Modo:</strong> ${modeLabels[mockup.generation_mode] || mockup.generation_mode}<br>
                <strong>Criado:</strong> ${new Date(mockup.created_at).toLocaleString('pt-BR')}<br>
                <strong>Tempo:</strong> ${(mockup.processing_time / 1000).toFixed(1)}s
        `;
        if (mockup.total_cost > 0) {
            infoHtml += `<br><strong>Custo:</strong> $${mockup.total_cost.toFixed(4)}`;
        }
        infoHtml += '</div>';

        const resultUrl = normalizeImageUrl(mockup.result_image_path);

        Swal.fire({
            title: 'Mockup Gerado',
            html: `${infoHtml}<img src="${resultUrl}" class="img-fluid rounded" style="max-width: 100%;">`,
            width: '800px',
            showConfirmButton: true,
            showCancelButton: true,
            showDenyButton: !mockup.sent_as_message,
            confirmButtonText: '<i class="fas fa-download me-1"></i> Download',
            denyButtonText: '<i class="fas fa-paper-plane me-1"></i> Enviar',
            cancelButtonText: 'Fechar',
            customClass: { container: 'mockup-view-modal' },
            preConfirm: () => {
                const link = document.createElement('a');
                link.href = resultUrl;
                link.download = 'mockup_' + mockupId + '.png';
                link.click();
                return false;
            },
            preDeny: () => {
                sendMockupFromGallery(mockupId);
                return false;
            }
        });

    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message });
    }
}

/**
 * Enviar mockup como mensagem (a partir da galeria)
 */
async function sendMockupFromGallery(generationId) {
    try {
        const response = await fetch(`/api/mockups/${generationId}/send-message`, { method: 'POST' });
        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch(e) { throw new Error('Erro ao enviar'); }
        if (!data.success) throw new Error(data.error || 'Erro ao enviar mensagem');

        Swal.fire({ icon: 'success', title: 'Enviado!', text: 'Mockup enviado na conversa', timer: 2000, showConfirmButton: false });
        if (typeof refreshMessages === 'function') refreshMessages();
        loadMockupGallery();
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message });
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
        const response = await fetch(`/api/mockups/${mockupId}`, { method: 'DELETE' });
        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch(e) { throw new Error('Erro ao excluir'); }
        if (!data.success) throw new Error(data.error || 'Erro ao excluir mockup');

        Swal.fire({ icon: 'success', title: 'Excluído!', timer: 2000, showConfirmButton: false });
        loadMockupGallery();
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message });
    }
}

// Recarregar ao trocar de conversa
if (typeof window.addEventListener !== 'undefined') {
    window.addEventListener('conversationChanged', () => {
        // Se a aba mockups estiver ativa, recarregar
        const mockupTab = document.querySelector('#kt_tab_mockups');
        if (mockupTab && mockupTab.classList.contains('active')) {
            setTimeout(() => loadMockupGallery(), 300);
        }
    });
}
</script>

<style>
.mockup-sidebar-item {
    padding: 10px;
    border-radius: 8px;
    background: var(--bs-gray-100);
    transition: all 0.2s;
}

.mockup-sidebar-item:hover {
    background: var(--bs-gray-200);
}

[data-bs-theme="dark"] .mockup-sidebar-item {
    background: var(--bs-gray-200);
}

[data-bs-theme="dark"] .mockup-sidebar-item:hover {
    background: var(--bs-gray-300);
}

/* Z-index alto para o modal de visualização de mockup */
.mockup-view-modal {
    z-index: 1300 !important;
}
</style>
