/**
 * Mockup Generator Wizard
 * Sistema de geração de mockups com GPT-4o Vision + DALL-E 3
 */

/**
 * Normalizar URL para evitar duplicação
 */
function normalizeImageUrl(url) {
    if (!url) return '';
    
    // Se já for URL completa (http/https), retornar como está
    if (url.startsWith('http://') || url.startsWith('https://')) {
        return url;
    }
    
    // Se já tem barra inicial, retornar como está
    if (url.startsWith('/')) {
        return url;
    }
    
    // Adicionar barra inicial
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
    generationMode: 'ai', // 'ai', 'manual', 'hybrid'
    canvasEditor: null
};

/**
 * Abrir modal do gerador de mockup
 */
function showMockupGeneratorModal() {
    const conversationId = currentConversationId || parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    
    if (!conversationId) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma conversa para gerar mockup'
        });
        return;
    }

    mockupWizard.conversationId = conversationId;
    mockupWizard.currentStep = 1;

    // Abrir modal
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_mockup_generator'));
    modal.show();

    // Carregar etapa 1
    loadMockupStep1();
}

/**
 * ETAPA 1: Selecionar Produto
 */
function loadMockupStep1() {
    updateWizardProgress(1);
    document.getElementById('mockupStep1').classList.remove('d-none');
    document.getElementById('mockupStep2').classList.add('d-none');
    document.getElementById('mockupStep3').classList.add('d-none');

    // Atualizar botões
    document.getElementById('mockupBtnPrev').classList.add('d-none');
    document.getElementById('mockupBtnNext').classList.remove('d-none');
    document.getElementById('mockupBtnGenerate').classList.add('d-none');

    // Carregar imagens da conversa
    loadConversationImages();
}

/**
 * Carregar imagens da conversa
 */
async function loadConversationImages() {
    try {
        const response = await fetch(`/api/conversations/${mockupWizard.conversationId}/images`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar imagens');
        }

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
                        <div class="mt-2 text-muted small">${img.sender_name}</div>
                        <div class="mockup-image-check d-none">
                            <i class="fas fa-check-circle text-success fs-2"></i>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(col);
        });

    } catch (error) {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message
        });
    }
}

/**
 * Selecionar imagem do produto
 */
function selectProductImage(path, element) {
    // Remover seleção anterior
    document.querySelectorAll('.mockup-image-card').forEach(card => {
        card.classList.remove('border-success', 'border-3');
        card.querySelector('.mockup-image-check').classList.add('d-none');
    });

    // Adicionar seleção
    element.classList.add('border-success', 'border-3');
    element.querySelector('.mockup-image-check').classList.remove('d-none');

    mockupWizard.selectedProduct = path;
}

/**
 * ETAPA 2: Configurar Logo
 */
function loadMockupStep2() {
    if (!mockupWizard.selectedProduct) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma imagem do produto primeiro'
        });
        return;
    }

    updateWizardProgress(2);
    document.getElementById('mockupStep1').classList.add('d-none');
    document.getElementById('mockupStep2').classList.remove('d-none');
    document.getElementById('mockupStep3').classList.add('d-none');

    // Atualizar botões
    document.getElementById('mockupBtnPrev').classList.remove('d-none');
    document.getElementById('mockupBtnNext').classList.remove('d-none');
    document.getElementById('mockupBtnGenerate').classList.add('d-none');

    // Carregar logos da conversa
    loadConversationLogos();

    // Atualizar preview
    updateLogoPreview();
}

/**
 * Carregar logos da conversa
 */
async function loadConversationLogos() {
    try {
        const response = await fetch(`/api/conversations/${mockupWizard.conversationId}/logos`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar logos');
        }

        const container = document.getElementById('mockupLogoImages');
        container.innerHTML = '';

        data.data.forEach(logo => {
            const col = document.createElement('div');
            col.className = 'col-auto mb-2';
            
            const logoUrl = normalizeImageUrl(logo.thumbnail_path || logo.logo_path);
            
            col.innerHTML = `
                <div class="mockup-logo-card ${logo.is_primary ? 'border-primary' : ''}" onclick="selectLogo('${logo.logo_path}', this)">
                    <img src="${logoUrl}" class="img-fluid rounded" style="max-width: 80px; max-height: 80px; object-fit: contain;">
                    ${logo.is_primary ? '<div class="badge badge-primary badge-sm">Principal</div>' : ''}
                </div>
            `;
            container.appendChild(col);

            // Selecionar logo primária automaticamente
            if (logo.is_primary && !mockupWizard.selectedLogo) {
                mockupWizard.selectedLogo = logo.logo_path;
                col.querySelector('.mockup-logo-card').classList.add('border-success', 'border-3');
            }
        });

    } catch (error) {
        console.error('Erro:', error);
    }
}

/**
 * Selecionar logo
 */
function selectLogo(path, element) {
    document.querySelectorAll('.mockup-logo-card').forEach(card => {
        card.classList.remove('border-success', 'border-3');
    });

    element.classList.add('border-success', 'border-3');
    mockupWizard.selectedLogo = path;

    updateLogoPreview();
}

/**
 * Upload de nova logo
 */
async function uploadNewLogo() {
    const { value: file } = await Swal.fire({
        title: 'Upload de Logo',
        input: 'file',
        inputAttributes: {
            accept: 'image/*',
            'aria-label': 'Faça upload da logo'
        },
        showCancelButton: true,
        confirmButtonText: 'Upload',
        cancelButtonText: 'Cancelar'
    });

    if (file) {
        const formData = new FormData();
        formData.append('logo', file);

        try {
            const response = await fetch(`/api/conversations/${mockupWizard.conversationId}/logos/upload`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Erro ao fazer upload');
            }

            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: 'Logo enviada com sucesso',
                timer: 2000,
                showConfirmButton: false
            });

            // Recarregar logos
            loadConversationLogos();

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: error.message
            });
        }
    }
}

/**
 * Atualizar configurações da logo
 */
function updateLogoConfig(field, value) {
    if (field.includes('.')) {
        const [parent, child] = field.split('.');
        mockupWizard.logoConfig[parent][child] = value;
    } else {
        mockupWizard.logoConfig[field] = value;
    }

    updateLogoPreview();
}

/**
 * Atualizar preview da logo
 */
function updateLogoPreview() {
    const previewContainer = document.getElementById('mockupLogoPreview');
    
    if (!mockupWizard.selectedProduct || !mockupWizard.selectedLogo) {
        previewContainer.innerHTML = '<div class="text-muted">Selecione produto e logo para visualizar</div>';
        return;
    }

    const productUrl = normalizeImageUrl(mockupWizard.selectedProduct);
    const logoUrl = normalizeImageUrl(mockupWizard.selectedLogo);
    
    // Simular preview (em produção, usar canvas real)
    previewContainer.innerHTML = `
        <div class="position-relative" style="max-width: 400px; margin: 0 auto;">
            <img src="${productUrl}" class="img-fluid rounded">
            <img src="${logoUrl}" 
                 class="position-absolute" 
                 style="
                     width: ${mockupWizard.logoConfig.size}%;
                     opacity: ${mockupWizard.logoConfig.opacity / 100};
                     ${getLogoPositionStyle(mockupWizard.logoConfig.position)};
                     ${mockupWizard.logoConfig.effects.shadow ? 'filter: drop-shadow(2px 2px 4px rgba(0,0,0,0.3));' : ''}
                 ">
        </div>
    `;
}

/**
 * Obter estilo CSS de posicionamento da logo
 */
function getLogoPositionStyle(position) {
    const positions = {
        'center': 'top: 50%; left: 50%; transform: translate(-50%, -50%);',
        'top-left': 'top: 10%; left: 10%;',
        'top-center': 'top: 10%; left: 50%; transform: translateX(-50%);',
        'top-right': 'top: 10%; right: 10%;',
        'center-left': 'top: 50%; left: 10%; transform: translateY(-50%);',
        'center-right': 'top: 50%; right: 10%; transform: translateY(-50%);',
        'bottom-left': 'bottom: 10%; left: 10%;',
        'bottom-center': 'bottom: 10%; left: 50%; transform: translateX(-50%);',
        'bottom-right': 'bottom: 10%; right: 10%;'
    };
    return positions[position] || positions['center'];
}

/**
 * ETAPA 3: Gerar Mockup
 */
function loadMockupStep3() {
    if (!mockupWizard.selectedLogo) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma logo primeiro'
        });
        return;
    }

    updateWizardProgress(3);
    document.getElementById('mockupStep1').classList.add('d-none');
    document.getElementById('mockupStep2').classList.add('d-none');
    document.getElementById('mockupStep3').classList.remove('d-none');

    // Atualizar botões
    document.getElementById('mockupBtnPrev').classList.remove('d-none');
    document.getElementById('mockupBtnNext').classList.add('d-none');
    document.getElementById('mockupBtnGenerate').classList.remove('d-none');

    // Gerar prompt padrão
    generateDefaultPrompt();

    // Exibir resumo
    displayMockupSummary();
}

/**
 * Gerar prompt padrão
 */
function generateDefaultPrompt() {
    const positionText = {
        'center': 'centralizada',
        'top-center': 'no topo centralizada',
        'bottom-center': 'no rodapé centralizada',
        'top-left': 'no canto superior esquerdo',
        'top-right': 'no canto superior direito',
        'bottom-left': 'no canto inferior esquerdo',
        'bottom-right': 'no canto inferior direito'
    };

    const sizeText = {
        10: 'pequena (10%)',
        20: 'média (20%)',
        30: 'grande (30%)'
    };

    const prompt = `Crie um mockup fotorrealista profissional do produto com as seguintes especificações:

LOGO:
- Posicionamento: ${positionText[mockupWizard.logoConfig.position] || 'centralizada'}
- Tamanho: ${sizeText[mockupWizard.logoConfig.size] || mockupWizard.logoConfig.size + '%'}
- Estilo: ${mockupWizard.logoConfig.style === 'original' ? 'cores originais' : mockupWizard.logoConfig.style}
- Opacidade: ${mockupWizard.logoConfig.opacity}%

REQUISITOS:
- Fundo neutro e clean (branco ou cinza claro sólido)
- Iluminação suave e profissional com sombras sutis
- Ângulo de visão: 3/4 frontal levemente elevado
- Produto centralizado com espaço ao redor
- Qualidade: fotografia de produto profissional, alta resolução
- Estilo: clean, moderno, adequado para e-commerce`;

    document.getElementById('mockupPrompt').value = prompt;
    mockupWizard.userPrompt = prompt;
}

/**
 * Exibir resumo da configuração
 */
function displayMockupSummary() {
    const productUrl = normalizeImageUrl(mockupWizard.selectedProduct);
    const logoUrl = normalizeImageUrl(mockupWizard.selectedLogo);
    
    const summary = `
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
                Posição: ${mockupWizard.logoConfig.position} | 
                Tamanho: ${mockupWizard.logoConfig.size}% | 
                Estilo: ${mockupWizard.logoConfig.style}
            </div>
        </div>
    `;

    document.getElementById('mockupSummary').innerHTML = summary;
}

/**
 * Selecionar modo de geração
 */
function selectGenerationMode(mode) {
    mockupWizard.generationMode = mode;

    // Atualizar UI
    document.querySelectorAll('.generation-mode-card').forEach(card => {
        card.classList.remove('border-primary', 'border-3');
    });

    event.currentTarget.classList.add('border-primary', 'border-3');

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
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Gerando...';
    btn.disabled = true;

    try {
        const payload = {
            product_image_path: mockupWizard.selectedProduct,
            logo_image_path: mockupWizard.selectedLogo,
            logo_config: mockupWizard.logoConfig,
            user_prompt: document.getElementById('mockupPrompt').value,
            size: document.querySelector('input[name="mockupSize"]:checked')?.value || '1024x1024',
            quality: document.querySelector('input[name="mockupQuality"]:checked')?.value || 'standard'
        };

        const response = await fetch(`/api/conversations/${mockupWizard.conversationId}/mockups/generate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Erro ao gerar mockup');
        }

        // Fechar modal
        bootstrap.Modal.getInstance(document.getElementById('kt_modal_mockup_generator')).hide();

        const resultUrl = normalizeImageUrl(data.data.image_path);
        
        // Mostrar resultado
        Swal.fire({
            icon: 'success',
            title: 'Mockup Gerado!',
            html: `
                <div class="text-center">
                    <img src="${resultUrl}" class="img-fluid rounded mb-3" style="max-width: 100%;">
                    <p class="text-muted">Tempo: ${(data.data.processing_time / 1000).toFixed(1)}s | Custo: $${data.data.costs.total.toFixed(4)}</p>
                </div>
            `,
            showConfirmButton: true,
            showCancelButton: true,
            confirmButtonText: 'Enviar na Conversa',
            cancelButtonText: 'Fechar',
            width: '800px'
        }).then((result) => {
            if (result.isConfirmed) {
                sendMockupAsMessage(data.data.generation_id);
            }
        });

        // Recarregar galeria de mockups
        if (typeof loadMockupGallery === 'function') {
            loadMockupGallery();
        }

    } catch (error) {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message
        });
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

/**
 * Enviar mockup como mensagem
 */
async function sendMockupAsMessage(generationId) {
    try {
        const response = await fetch(`/api/mockups/${generationId}/send-message`, {
            method: 'POST'
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Erro ao enviar mensagem');
        }

        Swal.fire({
            icon: 'success',
            title: 'Enviado!',
            text: 'Mockup enviado na conversa',
            timer: 2000,
            showConfirmButton: false
        });

        // Recarregar mensagens
        if (typeof refreshMessages === 'function') {
            refreshMessages();
        }

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message
        });
    }
}

/**
 * Atualizar progresso do wizard
 */
function updateWizardProgress(step) {
    mockupWizard.currentStep = step;

    // Atualizar indicador de passos
    for (let i = 1; i <= mockupWizard.totalSteps; i++) {
        const indicator = document.getElementById(`mockupStep${i}Indicator`);
        if (indicator) {
            if (i < step) {
                indicator.className = 'bg-success';
            } else if (i === step) {
                indicator.className = 'bg-primary';
            } else {
                indicator.className = 'bg-light';
            }
        }
    }
}

/**
 * Navegação do wizard
 */
function mockupWizardNext() {
    if (mockupWizard.currentStep === 1) {
        loadMockupStep2();
    } else if (mockupWizard.currentStep === 2) {
        loadMockupStep3();
    }
}

function mockupWizardPrev() {
    if (mockupWizard.currentStep === 2) {
        loadMockupStep1();
    } else if (mockupWizard.currentStep === 3) {
        loadMockupStep2();
    }
}
