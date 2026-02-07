<!-- Modal Gerador de Mockup -->
<div class="modal fade" id="kt_modal_mockup_generator" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">🎨 Gerador de Mockup</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <!-- Progress Indicator -->
                <div class="d-flex justify-content-between mb-6">
                    <div class="d-flex align-items-center flex-fill">
                        <div class="rounded-circle" id="mockupStep1Indicator" style="width: 40px; height: 40px; line-height: 40px; text-align: center;">
                            <span class="fw-bold text-white">1</span>
                        </div>
                        <div class="flex-fill mx-2" style="height: 4px; background: #e4e6ef;"></div>
                    </div>
                    <div class="d-flex align-items-center flex-fill">
                        <div class="rounded-circle" id="mockupStep2Indicator" style="width: 40px; height: 40px; line-height: 40px; text-align: center;">
                            <span class="fw-bold text-white">2</span>
                        </div>
                        <div class="flex-fill mx-2" style="height: 4px; background: #e4e6ef;"></div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle" id="mockupStep3Indicator" style="width: 40px; height: 40px; line-height: 40px; text-align: center;">
                            <span class="fw-bold text-white">3</span>
                        </div>
                    </div>
                </div>

                <!-- ETAPA 1: Produto -->
                <div id="mockupStep1">
                    <h4 class="mb-4">🎁 Etapa 1/3: Selecione o Produto</h4>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label fw-bold mb-0">Imagens da Conversa</label>
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="uploadNewProduct()">
                                <i class="fas fa-upload me-1"></i> Upload Produto
                            </button>
                        </div>
                        <div class="row" id="mockupProductImages">
                            <div class="col-12 text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Selecione uma imagem da conversa ou faça upload de um novo produto
                    </div>
                </div>

                <!-- ETAPA 2: Logo -->
                <div id="mockupStep2" class="d-none">
                    <h4 class="mb-4">🏢 Etapa 2/3: Configure a Logo</h4>

                    <!-- Tabs: Logos vs Imagens -->
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-logos-saved" data-bs-toggle="tab" data-bs-target="#content-logos-saved" type="button" role="tab">
                                <i class="fas fa-bookmark me-1"></i> Logos Salvas
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-logos-images" data-bs-toggle="tab" data-bs-target="#content-logos-images" type="button" role="tab" onclick="loadImagesForLogo()">
                                <i class="fas fa-images me-1"></i> Imagens da Conversa
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content mb-4">
                        <!-- Logos Salvas -->
                        <div class="tab-pane fade show active" id="content-logos-saved" role="tabpanel">
                            <label class="form-label fw-bold">Logos do Cliente</label>
                            <div class="d-flex align-items-center gap-2 flex-wrap" id="mockupLogoImages">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            </div>
                            <button type="button" class="btn btn-light-primary btn-sm mt-2" onclick="uploadNewLogo()">
                                <i class="fas fa-upload me-1"></i> Upload Nova Logo
                            </button>
                        </div>

                        <!-- Imagens da Conversa -->
                        <div class="tab-pane fade" id="content-logos-images" role="tabpanel">
                            <label class="form-label fw-bold">Selecione imagem para usar como logo</label>
                            <div class="alert alert-info alert-sm py-2 px-3 mb-3">
                                <i class="fas fa-lightbulb me-1"></i>
                                <small>Você pode usar qualquer imagem da conversa como logo do mockup</small>
                            </div>
                            <div class="row" id="mockupLogoImagesFromConversation">
                                <div class="col-12 text-center py-3">
                                    <div class="text-muted">Carregue esta aba para ver as imagens</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Configurações da Logo -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-4">⚙️ Configurações de Aplicação</h5>

                            <!-- Presets -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Preset Rápido</label>
                                <div class="btn-group w-100" role="group">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="applyPreset('caneca')">🎁 Caneca</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="applyPreset('camiseta')">👕 Camiseta</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="applyPreset('caderno')">📓 Caderno</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="applyPreset('caneta')">🖊️ Caneta</button>
                                </div>
                            </div>

                            <!-- Posicionamento -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">📍 Posicionamento</label>
                                <div class="d-flex gap-2 justify-content-center">
                                    <div class="btn-group-vertical" role="group">
                                        <button type="button" class="btn btn-sm btn-light" onclick="updateLogoConfig('position', 'top-left')">↖️</button>
                                        <button type="button" class="btn btn-sm btn-light" onclick="updateLogoConfig('position', 'center-left')">⬅️</button>
                                        <button type="button" class="btn btn-sm btn-light" onclick="updateLogoConfig('position', 'bottom-left')">↙️</button>
                                    </div>
                                    <div class="btn-group-vertical" role="group">
                                        <button type="button" class="btn btn-sm btn-light" onclick="updateLogoConfig('position', 'top-center')">⬆️</button>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="updateLogoConfig('position', 'center')">⚫</button>
                                        <button type="button" class="btn btn-sm btn-light" onclick="updateLogoConfig('position', 'bottom-center')">⬇️</button>
                                    </div>
                                    <div class="btn-group-vertical" role="group">
                                        <button type="button" class="btn btn-sm btn-light" onclick="updateLogoConfig('position', 'top-right')">↗️</button>
                                        <button type="button" class="btn btn-sm btn-light" onclick="updateLogoConfig('position', 'center-right')">➡️</button>
                                        <button type="button" class="btn btn-sm btn-light" onclick="updateLogoConfig('position', 'bottom-right')">↘️</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Tamanho -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">📏 Tamanho: <span id="logoSizeValue">20%</span></label>
                                <input type="range" class="form-range" min="5" max="50" step="5" value="20" 
                                       oninput="updateLogoConfig('size', this.value); document.getElementById('logoSizeValue').textContent = this.value + '%'">
                                <div class="d-flex justify-content-between small text-muted">
                                    <span>Pequeno</span>
                                    <span>Médio</span>
                                    <span>Grande</span>
                                </div>
                            </div>

                            <!-- Estilo -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">🎨 Estilo da Logo</label>
                                <select class="form-select" onchange="updateLogoConfig('style', this.value)">
                                    <option value="original" selected>Manter cores originais</option>
                                    <option value="white">Converter para branco</option>
                                    <option value="black">Converter para preto</option>
                                    <option value="grayscale">Escala de cinza</option>
                                </select>
                            </div>

                            <!-- Opacidade -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">💫 Opacidade: <span id="logoOpacityValue">100%</span></label>
                                <input type="range" class="form-range" min="20" max="100" step="10" value="100" 
                                       oninput="updateLogoConfig('opacity', this.value); document.getElementById('logoOpacityValue').textContent = this.value + '%'">
                            </div>

                            <!-- Efeitos -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">✨ Efeitos Visuais</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="effectShadow" onchange="updateLogoConfig('effects.shadow', this.checked)">
                                    <label class="form-check-label" for="effectShadow">Adicionar sombra sutil</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="effectBorder" onchange="updateLogoConfig('effects.border', this.checked)">
                                    <label class="form-check-label" for="effectBorder">Adicionar borda branca</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="effectReflection" onchange="updateLogoConfig('effects.reflection', this.checked)">
                                    <label class="form-check-label" for="effectReflection">Efeito reflexo</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">👁️ Preview ao Vivo</h5>
                        </div>
                        <div class="card-body text-center" id="mockupLogoPreview">
                            <div class="text-muted">Configurando...</div>
                        </div>
                    </div>
                </div>

                <!-- ETAPA 3: Gerar -->
                <div id="mockupStep3" class="d-none">
                    <h4 class="mb-4">🚀 Etapa 3/3: Gerar Mockup</h4>

                    <!-- Modos de Geração -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Escolha o modo de geração</label>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="card generation-mode-card border-primary border-3" onclick="selectGenerationMode('ai')" style="cursor: pointer;">
                                    <div class="card-body text-center">
                                        <h5>🤖 IA Automática</h5>
                                        <p class="small text-muted mb-0">GPT-4o Vision + DALL-E 3 geram mockup profissional</p>
                                        <span class="badge badge-success mt-2">Recomendado</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card generation-mode-card" onclick="selectGenerationMode('manual')" style="cursor: pointer;">
                                    <div class="card-body text-center">
                                        <h5>✋ Editor Manual</h5>
                                        <p class="small text-muted mb-0">Monte seu mockup com controle total</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card generation-mode-card" onclick="selectGenerationMode('hybrid')" style="cursor: pointer;">
                                    <div class="card-body text-center">
                                        <h5>🔀 Híbrido</h5>
                                        <p class="small text-muted mb-0">IA gera base + ajustes manuais</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Prompt -->
                    <div id="mockupPromptContainer" class="mb-4">
                        <label class="form-label fw-bold">✍️ Prompt de Geração</label>
                        <textarea class="form-control" id="mockupPrompt" rows="8" placeholder="Prompt será gerado automaticamente..."></textarea>
                        <div class="form-text">O prompt foi otimizado automaticamente com base nas suas configurações. Você pode editá-lo se desejar.</div>
                    </div>

                    <!-- Opções -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-3">⚙️ Opções de Geração</h5>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Tamanho</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="mockupSize" value="1024x1024" id="size1" checked>
                                    <label class="form-check-label" for="size1">Padrão (1024x1024)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="mockupSize" value="1024x1792" id="size2">
                                    <label class="form-check-label" for="size2">Retrato (1024x1792)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="mockupSize" value="1792x1024" id="size3">
                                    <label class="form-check-label" for="size3">Paisagem (1792x1024)</label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Qualidade</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="mockupQuality" value="standard" id="quality1" checked>
                                    <label class="form-check-label" for="quality1">Padrão (mais rápido)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="mockupQuality" value="hd" id="quality2">
                                    <label class="form-check-label" for="quality2">HD (mais demorado)</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resumo -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">📋 Resumo</h5>
                        </div>
                        <div class="card-body" id="mockupSummary"></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-secondary" id="mockupBtnPrev" onclick="mockupWizardPrev()">
                    <i class="fas fa-arrow-left me-1"></i> Voltar
                </button>
                <button type="button" class="btn btn-primary" id="mockupBtnNext" onclick="mockupWizardNext()">
                    Próximo <i class="fas fa-arrow-right ms-1"></i>
                </button>
                <button type="button" class="btn btn-success d-none" id="mockupBtnGenerate" onclick="generateMockup()">
                    <i class="fas fa-magic me-1"></i> Gerar Mockup
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.mockup-image-card {
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
}

.mockup-image-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.mockup-image-check {
    position: absolute;
    top: 5px;
    right: 5px;
}

.mockup-logo-card {
    padding: 10px;
    border: 2px solid #e4e6ef;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.mockup-logo-card:hover {
    transform: scale(1.05);
}

.generation-mode-card {
    transition: all 0.3s;
    cursor: pointer;
}

.generation-mode-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>

<script>
// Presets de produtos
function applyPreset(product) {
    const presets = {
        'caneca': { position: 'center', size: 20, style: 'original' },
        'camiseta': { position: 'top-center', size: 15, style: 'original' },
        'caderno': { position: 'bottom-right', size: 12, style: 'original' },
        'caneta': { position: 'center', size: 8, style: 'original' }
    };

    const preset = presets[product];
    if (preset) {
        mockupWizard.logoConfig.position = preset.position;
        mockupWizard.logoConfig.size = preset.size;
        mockupWizard.logoConfig.style = preset.style;

        // Atualizar UI
        document.querySelector('input[type="range"][min="5"]').value = preset.size;
        document.getElementById('logoSizeValue').textContent = preset.size + '%';
        document.querySelector('select').value = preset.style;

        updateLogoPreview();

        Swal.fire({
            icon: 'success',
            title: 'Preset Aplicado!',
            text: `Configurações de ${product} aplicadas`,
            timer: 1500,
            showConfirmButton: false
        });
    }
}
</script>
