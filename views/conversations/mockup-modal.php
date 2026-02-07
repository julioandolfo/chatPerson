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

                            <!-- Posicionamento (dropdown) -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">📍 Posicionamento da Logo</label>
                                <select class="form-select" id="logoPositionSelect" onchange="updateLogoConfig('position', this.value)">
                                    <option value="center" selected>Centralizado</option>
                                    <option value="top-center">Centro Superior</option>
                                    <option value="bottom-center">Centro Inferior</option>
                                    <option value="center-left">Centro Esquerda</option>
                                    <option value="center-right">Centro Direita</option>
                                    <option value="top-left">Superior Esquerdo</option>
                                    <option value="top-right">Superior Direito</option>
                                    <option value="bottom-left">Inferior Esquerdo</option>
                                    <option value="bottom-right">Inferior Direito</option>
                                </select>
                            </div>

                            <!-- Tamanho (3 opções simples) -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">📏 Tamanho da Logo</label>
                                <div class="d-flex gap-2">
                                    <div class="form-check form-check-custom form-check-solid flex-fill">
                                        <input class="form-check-input" type="radio" name="logoSize" value="10" id="logoSizeSmall" onchange="updateLogoConfig('size', 10)">
                                        <label class="form-check-label w-100 text-center py-2 rounded border" for="logoSizeSmall" style="cursor: pointer;">Pequeno</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-solid flex-fill">
                                        <input class="form-check-input" type="radio" name="logoSize" value="20" id="logoSizeMedium" checked onchange="updateLogoConfig('size', 20)">
                                        <label class="form-check-label w-100 text-center py-2 rounded border" for="logoSizeMedium" style="cursor: pointer;">Médio</label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-solid flex-fill">
                                        <input class="form-check-input" type="radio" name="logoSize" value="30" id="logoSizeLarge" onchange="updateLogoConfig('size', 30)">
                                        <label class="form-check-label w-100 text-center py-2 rounded border" for="logoSizeLarge" style="cursor: pointer;">Grande</label>
                                    </div>
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
                            </div>
                        </div>
                    </div>

                    <!-- Canvas Posicional -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">📐 Posicionar Logo no Produto</h5>
                        </div>
                        <div class="card-body text-center p-0" id="mockupPositionCanvas" style="min-height: 350px; background: #f5f5f5; position: relative; overflow: hidden; border-radius: 0 0 8px 8px;">
                            <div class="text-muted py-5">Selecione produto e logo para posicionar</div>
                        </div>
                        <div class="card-footer text-muted small">
                            <i class="fas fa-hand-pointer me-1"></i> Arraste a logo para posicioná-la sobre o produto. Esta imagem será enviada como referência para a IA.
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
                                <div class="card generation-mode-card active" data-mode="ai" onclick="selectGenerationMode('ai', this)" style="cursor: pointer;">
                                    <div class="card-body text-center py-4">
                                        <div class="fs-1 mb-2">🤖</div>
                                        <h5 class="mb-1">IA Automática</h5>
                                        <p class="small text-muted mb-2">GPT-4o Vision + DALL-E 3 geram mockup profissional</p>
                                        <span class="badge bg-success">Recomendado</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card generation-mode-card" data-mode="manual" onclick="selectGenerationMode('manual', this)" style="cursor: pointer;">
                                    <div class="card-body text-center py-4">
                                        <div class="fs-1 mb-2">✋</div>
                                        <h5 class="mb-1">Editor Manual</h5>
                                        <p class="small text-muted mb-2">Monte seu mockup com controle total no editor</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card generation-mode-card" data-mode="hybrid" onclick="selectGenerationMode('hybrid', this)" style="cursor: pointer;">
                                    <div class="card-body text-center py-4">
                                        <div class="fs-1 mb-2">🔀</div>
                                        <h5 class="mb-1">Híbrido</h5>
                                        <p class="small text-muted mb-2">IA gera base + ajustes manuais no editor</p>
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
                                <label class="form-label fw-bold">Qualidade</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="mockupQuality" value="standard" id="quality1" checked>
                                    <label class="form-check-label" for="quality1">Padrão (mais rápido)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="mockupQuality" value="hd" id="quality2">
                                    <label class="form-check-label" for="quality2">HD (mais demorado, melhor qualidade)</label>
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

/* CARDS DE MODO DE GERAÇÃO - estado selecionado visível */
.generation-mode-card {
    transition: all 0.3s;
    cursor: pointer;
    border: 2px solid #e4e6ef;
}
.generation-mode-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.generation-mode-card.active {
    border: 3px solid #009ef7 !important;
    background: rgba(0, 158, 247, 0.06) !important;
    box-shadow: 0 0 0 4px rgba(0, 158, 247, 0.15);
}
.generation-mode-card.active h5 {
    color: #009ef7;
}

/* Canvas posicional */
#mockupPositionCanvas img.position-logo {
    cursor: grab;
    user-select: none;
    -webkit-user-drag: none;
}
#mockupPositionCanvas img.position-logo:active {
    cursor: grabbing;
}
</style>
