/**
 * Mockup Generator Wizard
 * Sistema de geração de mockups com GPT-4o Vision + DALL-E 3
 */

/**
 * Normalizar URL para evitar duplicação
 */
function normalizeImageUrl(url) {
    if (!url) return '';
    if (url.startsWith('http://') || url.startsWith('https://')) return url;
    if (url.startsWith('/')) return url;
    return '/' + url;
}

// Estado global do wizard
let mockupWizard = {
    currentStep: 1,
    totalSteps: 3,
    conversationId: null,
    selectedProduct: null,
    selectedLogo: null,
    logoConfig: {
        position: 'center',
        size: 20,
        style: 'original',
        orientation: 'auto',
        opacity: 100,
        effects: {
            shadow: false,
            border: false,
            reflection: false
        }
    },
    userPrompt: '',
    generationMode: 'ai',
    canvasEditor: null,
    logoDragData: null // posição X,Y do logo arrastado no canvas
};

/**
 * Abrir modal do gerador de mockup
 */
function showMockupGeneratorModal() {
    const conversationId = currentConversationId;
    
    if (!conversationId) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Selecione uma conversa para gerar mockup' });
        return;
    }

    mockupWizard.conversationId = conversationId;
    mockupWizard.currentStep = 1;
    mockupWizard.selectedProduct = null;
    mockupWizard.selectedLogo = null;
    mockupWizard.generationMode = 'ai';
    mockupWizard.logoDragData = null;

    const modal = new bootstrap.Modal(document.getElementById('kt_modal_mockup_generator'));
    modal.show();
    loadMockupStep1();
}

// ==================== ETAPA 1: Produto ====================

function loadMockupStep1() {
    updateWizardProgress(1);
    document.getElementById('mockupStep1').classList.remove('d-none');
    document.getElementById('mockupStep2').classList.add('d-none');
    document.getElementById('mockupStep3').classList.add('d-none');
    document.getElementById('mockupBtnPrev').classList.add('d-none');
    document.getElementById('mockupBtnNext').classList.remove('d-none');
    document.getElementById('mockupBtnGenerate').classList.add('d-none');
    loadConversationImages();
}

async function loadConversationImages() {
    try {
        const response = await fetch(`/api/conversations/${mockupWizard.conversationId}/images`);
        const text = await response.text();
        
        let data;
        try { data = JSON.parse(text); } catch(e) {
            throw new Error('Erro ao carregar imagens. Verifique o servidor.');
        }

        if (!data.success) throw new Error(data.error || 'Erro ao carregar imagens');

        const container = document.getElementById('mockupProductImages');
        container.innerHTML = '';

        if (data.data.length === 0) {
            container.innerHTML = '<div class="alert alert-info">Nenhuma imagem encontrada nesta conversa. Faça upload de uma imagem do produto.</div>';
            return;
        }

        data.data.forEach(img => {
            const col = document.createElement('div');
            col.className = 'col-md-3 col-sm-4 col-6 mb-3';
            const imgUrl = normalizeImageUrl(img.url || img.path);
            col.innerHTML = `
                <div class="card mockup-image-card" onclick="selectProductImage('${img.path}', this)">
                    <div class="card-body p-2 text-center">
                        <img src="${imgUrl}" class="img-fluid rounded" style="max-height: 120px; object-fit: cover;">
                        <div class="mt-2 text-muted small">${img.sender_name || ''}</div>
                        <div class="mockup-image-check d-none"><i class="fas fa-check-circle text-success fs-2"></i></div>
                    </div>
                </div>`;
            container.appendChild(col);
        });
    } catch (error) {
        console.error('Erro:', error);
        document.getElementById('mockupProductImages').innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
    }
}

function selectProductImage(path, element) {
    document.querySelectorAll('#mockupProductImages .mockup-image-card').forEach(card => {
        card.classList.remove('border-success', 'border-3');
        const check = card.querySelector('.mockup-image-check');
        if (check) check.classList.add('d-none');
    });
    element.classList.add('border-success', 'border-3');
    const check = element.querySelector('.mockup-image-check');
    if (check) check.classList.remove('d-none');
    mockupWizard.selectedProduct = path;
}

async function uploadNewProduct() {
    const { value: file } = await Swal.fire({
        title: 'Upload de Produto',
        html: '<input type="file" id="productFile" class="form-control mb-2" accept="image/*"><small class="text-muted">Selecione uma imagem do produto/brinde</small>',
        showCancelButton: true, confirmButtonText: 'Upload', cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const f = document.getElementById('productFile');
            if (!f.files || !f.files.length) { Swal.showValidationMessage('Selecione um arquivo'); return false; }
            return f.files[0];
        }
    });
    if (!file) return;

    const formData = new FormData();
    formData.append('product', file);
    try {
        Swal.fire({ title: 'Enviando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const response = await fetch(`/api/conversations/${mockupWizard.conversationId}/upload-temp-image`, { method: 'POST', body: formData });
        const text = await response.text();
        let data; try { data = JSON.parse(text); } catch(e) { throw new Error('Erro no upload'); }
        if (!data.success) throw new Error(data.error || 'Erro ao fazer upload');

        Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Produto enviado', timer: 2000, showConfirmButton: false });
        mockupWizard.selectedProduct = data.path;
        const container = document.getElementById('mockupProductImages');
        const col = document.createElement('div');
        col.className = 'col-md-3 col-sm-4 col-6 mb-3';
        col.innerHTML = `
            <div class="card mockup-image-card border-success border-3" onclick="selectProductImage('${data.path}', this)">
                <div class="card-body p-2 text-center">
                    <img src="${normalizeImageUrl(data.path)}" class="img-fluid rounded" style="max-height: 120px; object-fit: cover;">
                    <div class="mt-2 text-success small"><i class="fas fa-check-circle me-1"></i>Upload Recente</div>
                    <div class="mockup-image-check"><i class="fas fa-check-circle text-success fs-2"></i></div>
                </div>
            </div>`;
        container.insertBefore(col, container.firstChild);
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message });
    }
}

// ==================== ETAPA 2: Logo ====================

function loadMockupStep2() {
    if (!mockupWizard.selectedProduct) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Selecione uma imagem do produto primeiro' });
        return;
    }
    updateWizardProgress(2);
    document.getElementById('mockupStep1').classList.add('d-none');
    document.getElementById('mockupStep2').classList.remove('d-none');
    document.getElementById('mockupStep3').classList.add('d-none');
    document.getElementById('mockupBtnPrev').classList.remove('d-none');
    document.getElementById('mockupBtnNext').classList.remove('d-none');
    document.getElementById('mockupBtnGenerate').classList.add('d-none');
    loadConversationLogos();
    initPositionCanvas();
}

async function loadConversationLogos() {
    try {
        const response = await fetch(`/api/conversations/${mockupWizard.conversationId}/logos`);
        const text = await response.text();
        let data; try { data = JSON.parse(text); } catch(e) { throw new Error('Erro ao carregar logos'); }
        if (!data.success) throw new Error(data.error || 'Erro ao carregar logos');

        const container = document.getElementById('mockupLogoImages');
        container.innerHTML = '';

        if (data.data.length === 0) {
            container.innerHTML = '<div class="text-muted small">Nenhuma logo salva. Faça upload ou selecione das imagens da conversa.</div>';
            return;
        }

        data.data.forEach(logo => {
            const col = document.createElement('div');
            col.className = 'col-auto mb-2';
            const logoUrl = normalizeImageUrl(logo.thumbnail_path || logo.logo_path);
            col.innerHTML = `
                <div class="mockup-logo-card ${logo.is_primary ? 'border-primary' : ''}" onclick="selectLogo('${logo.logo_path}', this)">
                    <img src="${logoUrl}" class="img-fluid rounded" style="max-width: 80px; max-height: 80px; object-fit: contain;">
                    ${logo.is_primary ? '<div class="badge badge-primary badge-sm">Principal</div>' : ''}
                </div>`;
            container.appendChild(col);
            if (logo.is_primary && !mockupWizard.selectedLogo) {
                mockupWizard.selectedLogo = logo.logo_path;
                col.querySelector('.mockup-logo-card').classList.add('border-success', 'border-3');
                setTimeout(() => initPositionCanvas(), 100);
            }
        });
    } catch (error) { console.error('Erro:', error); }
}

function selectLogo(path, element) {
    document.querySelectorAll('.mockup-logo-card').forEach(c => c.classList.remove('border-success', 'border-3'));
    // Também limpar seleção da aba de imagens
    document.querySelectorAll('#mockupLogoImagesFromConversation .mockup-image-card').forEach(c => {
        c.classList.remove('border-success', 'border-3');
        const check = c.querySelector('.mockup-image-check');
        if (check) check.classList.add('d-none');
    });
    element.classList.add('border-success', 'border-3');
    mockupWizard.selectedLogo = path;
    initPositionCanvas();
}

async function uploadNewLogo() {
    const { value: file } = await Swal.fire({
        title: 'Upload de Logo',
        html: '<input type="file" id="logoFile" class="form-control mb-2" accept="image/*"><small class="text-muted">Selecione a logo do cliente</small>',
        showCancelButton: true, confirmButtonText: 'Upload', cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const f = document.getElementById('logoFile');
            if (!f.files || !f.files.length) { Swal.showValidationMessage('Selecione um arquivo'); return false; }
            return f.files[0];
        }
    });
    if (!file) return;

    const formData = new FormData();
    formData.append('logo', file);
    try {
        Swal.fire({ title: 'Enviando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const response = await fetch(`/api/conversations/${mockupWizard.conversationId}/logos/upload`, { method: 'POST', body: formData });
        const text = await response.text();
        let data; try { data = JSON.parse(text); } catch(e) { throw new Error('Erro no upload'); }
        if (!data.success) throw new Error(data.error || 'Erro ao fazer upload');
        Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Logo enviada', timer: 2000, showConfirmButton: false });
        mockupWizard.selectedLogo = data.logo_path;
        loadConversationLogos();
        setTimeout(() => initPositionCanvas(), 300);
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message });
    }
}

async function loadImagesForLogo() {
    const container = document.getElementById('mockupLogoImagesFromConversation');
    container.innerHTML = '<div class="col-12 text-center py-3"><div class="spinner-border text-primary" role="status"></div></div>';
    try {
        const response = await fetch(`/api/conversations/${mockupWizard.conversationId}/images`);
        const text = await response.text();
        let data; try { data = JSON.parse(text); } catch(e) { throw new Error('Erro ao carregar imagens'); }
        if (!data.success) throw new Error(data.error || 'Erro ao carregar imagens');

        container.innerHTML = '';
        if (data.data.length === 0) {
            container.innerHTML = '<div class="col-12"><div class="alert alert-info">Nenhuma imagem encontrada.</div></div>';
            return;
        }
        data.data.forEach(img => {
            const col = document.createElement('div');
            col.className = 'col-md-3 col-sm-4 col-6 mb-3';
            const imgUrl = normalizeImageUrl(img.url || img.path);
            col.innerHTML = `
                <div class="card mockup-image-card" onclick="selectLogoFromImage('${img.path}', this)">
                    <div class="card-body p-2 text-center">
                        <img src="${imgUrl}" class="img-fluid rounded" style="max-height: 120px; object-fit: cover;">
                        <div class="mt-2 text-muted small">${img.sender_name || ''}</div>
                        <div class="mockup-image-check d-none"><i class="fas fa-check-circle text-success fs-2"></i></div>
                    </div>
                </div>`;
            container.appendChild(col);
        });
    } catch (error) {
        container.innerHTML = `<div class="col-12"><div class="alert alert-danger">${error.message}</div></div>`;
    }
}

function selectLogoFromImage(path, element) {
    // Limpar seleção de imagens da conversa
    document.querySelectorAll('#mockupLogoImagesFromConversation .mockup-image-card').forEach(c => {
        c.classList.remove('border-success', 'border-3');
        const check = c.querySelector('.mockup-image-check');
        if (check) check.classList.add('d-none');
    });
    // Limpar seleção de logos salvas
    document.querySelectorAll('.mockup-logo-card').forEach(c => c.classList.remove('border-success', 'border-3'));
    
    element.classList.add('border-success', 'border-3');
    const check = element.querySelector('.mockup-image-check');
    if (check) check.classList.remove('d-none');
    mockupWizard.selectedLogo = path;
    initPositionCanvas();
}

function updateLogoConfig(field, value) {
    if (field.includes('.')) {
        const [parent, child] = field.split('.');
        mockupWizard.logoConfig[parent][child] = value;
    } else {
        mockupWizard.logoConfig[field] = value;
    }
}

// ==================== CANVAS POSICIONAL (Drag & Drop) ====================

function initPositionCanvas() {
    const container = document.getElementById('mockupPositionCanvas');
    if (!mockupWizard.selectedProduct || !mockupWizard.selectedLogo) {
        container.innerHTML = '<div class="text-muted py-5">Selecione produto e logo para posicionar</div>';
        return;
    }

    const productUrl = normalizeImageUrl(mockupWizard.selectedProduct);
    const logoUrl = normalizeImageUrl(mockupWizard.selectedLogo);

    container.innerHTML = `
        <img src="${productUrl}" id="positionProductImg" style="max-width: 100%; max-height: 350px; display: block; margin: 0 auto; user-select: none;" draggable="false">
        <img src="${logoUrl}" id="positionLogoImg" class="position-logo" draggable="false"
             style="position: absolute; width: 20%; cursor: grab; top: 50%; left: 50%; transform: translate(-50%, -50%); user-select: none; z-index: 10;">
    `;

    // Setup drag
    const logoEl = document.getElementById('positionLogoImg');
    if (logoEl) {
        let isDragging = false;
        let offsetX = 0, offsetY = 0;

        logoEl.addEventListener('mousedown', (e) => {
            isDragging = true;
            const rect = logoEl.getBoundingClientRect();
            offsetX = e.clientX - rect.left;
            offsetY = e.clientY - rect.top;
            logoEl.style.cursor = 'grabbing';
            e.preventDefault();
        });

        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            const containerRect = container.getBoundingClientRect();
            let x = e.clientX - containerRect.left - offsetX;
            let y = e.clientY - containerRect.top - offsetY;
            // Limites
            x = Math.max(0, Math.min(x, containerRect.width - logoEl.offsetWidth));
            y = Math.max(0, Math.min(y, containerRect.height - logoEl.offsetHeight));
            logoEl.style.left = x + 'px';
            logoEl.style.top = y + 'px';
            logoEl.style.transform = 'none';
            // Salvar posição relativa
            mockupWizard.logoDragData = {
                xPercent: ((x + logoEl.offsetWidth / 2) / containerRect.width * 100).toFixed(1),
                yPercent: ((y + logoEl.offsetHeight / 2) / containerRect.height * 100).toFixed(1)
            };
        });

        document.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                logoEl.style.cursor = 'grab';
            }
        });

        // Touch support
        logoEl.addEventListener('touchstart', (e) => {
            isDragging = true;
            const touch = e.touches[0];
            const rect = logoEl.getBoundingClientRect();
            offsetX = touch.clientX - rect.left;
            offsetY = touch.clientY - rect.top;
            e.preventDefault();
        }, { passive: false });

        document.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            const touch = e.touches[0];
            const containerRect = container.getBoundingClientRect();
            let x = touch.clientX - containerRect.left - offsetX;
            let y = touch.clientY - containerRect.top - offsetY;
            x = Math.max(0, Math.min(x, containerRect.width - logoEl.offsetWidth));
            y = Math.max(0, Math.min(y, containerRect.height - logoEl.offsetHeight));
            logoEl.style.left = x + 'px';
            logoEl.style.top = y + 'px';
            logoEl.style.transform = 'none';
            mockupWizard.logoDragData = {
                xPercent: ((x + logoEl.offsetWidth / 2) / containerRect.width * 100).toFixed(1),
                yPercent: ((y + logoEl.offsetHeight / 2) / containerRect.height * 100).toFixed(1)
            };
        }, { passive: false });

        document.addEventListener('touchend', () => { isDragging = false; });
    }
}

// ==================== ETAPA 3: Gerar ====================

function loadMockupStep3() {
    if (!mockupWizard.selectedLogo) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Selecione uma logo primeiro' });
        return;
    }
    updateWizardProgress(3);
    document.getElementById('mockupStep1').classList.add('d-none');
    document.getElementById('mockupStep2').classList.add('d-none');
    document.getElementById('mockupStep3').classList.remove('d-none');
    document.getElementById('mockupBtnPrev').classList.remove('d-none');
    document.getElementById('mockupBtnNext').classList.add('d-none');
    document.getElementById('mockupBtnGenerate').classList.remove('d-none');

    // Marcar IA como selecionado por padrão
    mockupWizard.generationMode = 'ai';
    document.querySelectorAll('.generation-mode-card').forEach(c => c.classList.remove('active'));
    const aiCard = document.querySelector('.generation-mode-card[data-mode="ai"]');
    if (aiCard) aiCard.classList.add('active');

    generateDefaultPrompt();
    displayMockupSummary();
}

function generateDefaultPrompt() {
    const positionLabels = {
        'center': 'centralizada', 'top-center': 'no centro superior', 'bottom-center': 'no centro inferior',
        'center-left': 'no centro esquerda', 'center-right': 'no centro direita',
        'top-left': 'no superior esquerdo', 'top-right': 'no superior direito',
        'bottom-left': 'no inferior esquerdo', 'bottom-right': 'no inferior direito'
    };
    const sizeLabels = { 10: 'pequena', 20: 'média', 30: 'grande' };

    let positionInfo = positionLabels[mockupWizard.logoConfig.position] || 'centralizada';
    
    // Se o usuário arrastou no canvas, incluir posição personalizada
    if (mockupWizard.logoDragData) {
        positionInfo = `posição personalizada (${mockupWizard.logoDragData.xPercent}% horizontal, ${mockupWizard.logoDragData.yPercent}% vertical)`;
    }

    const prompt = `Crie um mockup fotorrealista profissional do produto com as seguintes especificações:

LOGO:
- Posicionamento: ${positionInfo}
- Tamanho: ${sizeLabels[mockupWizard.logoConfig.size] || mockupWizard.logoConfig.size + '%'}
- Estilo: ${mockupWizard.logoConfig.style === 'original' ? 'cores originais' : mockupWizard.logoConfig.style}

REQUISITOS:
- Fundo neutro e clean (branco ou cinza claro sólido)
- Iluminação suave e profissional com sombras sutis
- Ângulo de visão: 3/4 frontal levemente elevado
- Produto centralizado com espaço ao redor
- Logo perfeitamente aplicada no produto
- Qualidade: fotografia de produto profissional, alta resolução
- Estilo: clean, moderno, adequado para e-commerce e apresentação comercial`;

    document.getElementById('mockupPrompt').value = prompt;
    mockupWizard.userPrompt = prompt;
}

function displayMockupSummary() {
    const productUrl = normalizeImageUrl(mockupWizard.selectedProduct);
    const logoUrl = normalizeImageUrl(mockupWizard.selectedLogo);
    
    const positionLabels = {
        'center': 'Centralizado', 'top-center': 'Centro Superior', 'bottom-center': 'Centro Inferior',
        'center-left': 'Centro Esquerda', 'center-right': 'Centro Direita',
        'top-left': 'Superior Esquerdo', 'top-right': 'Superior Direito',
        'bottom-left': 'Inferior Esquerdo', 'bottom-right': 'Inferior Direito'
    };
    const sizeLabels = { 10: 'Pequeno', 20: 'Médio', 30: 'Grande' };

    let posText = positionLabels[mockupWizard.logoConfig.position] || 'Centralizado';
    if (mockupWizard.logoDragData) {
        posText = 'Posição personalizada (arrastada no canvas)';
    }

    document.getElementById('mockupSummary').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <strong>Produto:</strong><br>
                <img src="${productUrl}" class="img-fluid rounded mb-2" style="max-height: 100px;">
            </div>
            <div class="col-md-6">
                <strong>Logo:</strong><br>
                <img src="${logoUrl}" class="img-fluid rounded mb-2" style="max-height: 100px;">
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <strong>Configurações:</strong><br>
                Posição: ${posText} | 
                Tamanho: ${sizeLabels[mockupWizard.logoConfig.size] || mockupWizard.logoConfig.size + '%'} | 
                Estilo: ${mockupWizard.logoConfig.style}
            </div>
        </div>`;
}

/**
 * Selecionar modo de geração (com estado visual claro)
 */
function selectGenerationMode(mode, el) {
    mockupWizard.generationMode = mode;

    // Remover active de todos
    document.querySelectorAll('.generation-mode-card').forEach(card => {
        card.classList.remove('active');
    });

    // Adicionar active ao clicado
    if (el) {
        el.classList.add('active');
    }

    // Mostrar/ocultar prompt
    const promptContainer = document.getElementById('mockupPromptContainer');
    if (mode === 'ai' || mode === 'hybrid') {
        promptContainer.classList.remove('d-none');
    } else {
        promptContainer.classList.add('d-none');
    }
}

/**
 * Gerar mockup
 */
async function generateMockup() {
    const btn = document.getElementById('mockupBtnGenerate');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Gerando mockup...';
    btn.disabled = true;

    try {
        const payload = {
            product_image_path: mockupWizard.selectedProduct,
            logo_image_path: mockupWizard.selectedLogo,
            logo_config: mockupWizard.logoConfig,
            user_prompt: document.getElementById('mockupPrompt')?.value || '',
            size: '1024x1024',
            quality: document.querySelector('input[name="mockupQuality"]:checked')?.value || 'standard'
        };

        // Adicionar dados de posição do canvas se existirem
        if (mockupWizard.logoDragData) {
            payload.logo_config.customPosition = mockupWizard.logoDragData;
        }

        const response = await fetch(`/api/conversations/${mockupWizard.conversationId}/mockups/generate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch(e) {
            console.error('Resposta do servidor:', text);
            throw new Error('Erro interno do servidor. Verifique se a API Key da OpenAI está configurada corretamente.');
        }

        if (!data.success) {
            throw new Error(data.error || 'Erro ao gerar mockup');
        }

        // Fechar modal
        bootstrap.Modal.getInstance(document.getElementById('kt_modal_mockup_generator'))?.hide();

        const resultUrl = normalizeImageUrl(data.data.image_path);
        
        Swal.fire({
            icon: 'success',
            title: 'Mockup Gerado!',
            html: `
                <div class="text-center">
                    <img src="${resultUrl}" class="img-fluid rounded mb-3" style="max-width: 100%;">
                    <p class="text-muted">Tempo: ${(data.data.processing_time / 1000).toFixed(1)}s</p>
                </div>`,
            showConfirmButton: true,
            showCancelButton: true,
            confirmButtonText: 'Enviar na Conversa',
            cancelButtonText: 'Fechar',
            width: '800px'
        }).then((result) => {
            if (result.isConfirmed) sendMockupAsMessage(data.data.generation_id);
        });

        if (typeof loadMockupGallery === 'function') loadMockupGallery();

    } catch (error) {
        console.error('Erro:', error);
        Swal.fire({ icon: 'error', title: 'Erro ao Gerar', text: error.message });
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

async function sendMockupAsMessage(generationId) {
    try {
        const response = await fetch(`/api/mockups/${generationId}/send-message`, { method: 'POST' });
        const text = await response.text();
        let data; try { data = JSON.parse(text); } catch(e) { throw new Error('Erro ao enviar'); }
        if (!data.success) throw new Error(data.error || 'Erro ao enviar mensagem');

        Swal.fire({ icon: 'success', title: 'Enviado!', text: 'Mockup enviado na conversa', timer: 2000, showConfirmButton: false });
        if (typeof refreshMessages === 'function') refreshMessages();
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message });
    }
}

// ==================== NAVEGAÇÃO ====================

function updateWizardProgress(step) {
    mockupWizard.currentStep = step;
    for (let i = 1; i <= mockupWizard.totalSteps; i++) {
        const el = document.getElementById(`mockupStep${i}Indicator`);
        if (el) {
            if (i < step) el.className = 'rounded-circle bg-success';
            else if (i === step) el.className = 'rounded-circle bg-primary';
            else el.className = 'rounded-circle bg-light';
            el.style.cssText = 'width: 40px; height: 40px; line-height: 40px; text-align: center;';
        }
    }
}

function mockupWizardNext() {
    if (mockupWizard.currentStep === 1) loadMockupStep2();
    else if (mockupWizard.currentStep === 2) loadMockupStep3();
}

function mockupWizardPrev() {
    if (mockupWizard.currentStep === 2) loadMockupStep1();
    else if (mockupWizard.currentStep === 3) loadMockupStep2();
}
