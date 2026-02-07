/**
 * Mockup Canvas Editor (Fabric.js)
 * Editor visual para mockups manuais
 */

let mockupCanvas = null;

/**
 * Inicializar editor canvas
 */
function initMockupCanvasEditor(containerId = 'mockupCanvasContainer') {
    // Verificar se Fabric.js está carregado
    if (typeof fabric === 'undefined') {
        console.error('Fabric.js não está carregado!');
        // Carregar Fabric.js dinamicamente
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js';
        script.onload = () => initMockupCanvasEditor(containerId);
        document.head.appendChild(script);
        return;
    }

    // Criar canvas
    const container = document.getElementById(containerId);
    if (!container) {
        console.error('Container não encontrado:', containerId);
        return;
    }

    container.innerHTML = '<canvas id="mockupCanvas" width="1024" height="1024"></canvas>';

    mockupCanvas = new fabric.Canvas('mockupCanvas', {
        backgroundColor: '#ffffff',
        selection: true,
        preserveObjectStacking: true
    });

    // Adicionar ferramentas
    setupCanvasTools();

    return mockupCanvas;
}

/**
 * Configurar ferramentas do canvas
 */
function setupCanvasTools() {
    // Adicionar produto
    if (mockupWizard.selectedProduct) {
        fabric.Image.fromURL('/' + mockupWizard.selectedProduct, (img) => {
            img.scaleToWidth(mockupCanvas.width * 0.8);
            img.center();
            mockupCanvas.add(img);
            mockupCanvas.sendToBack(img);
        });
    }

    // Adicionar logo
    if (mockupWizard.selectedLogo) {
        fabric.Image.fromURL('/' + mockupWizard.selectedLogo, (img) => {
            const config = mockupWizard.logoConfig;
            
            // Aplicar configurações
            img.scaleToWidth(mockupCanvas.width * (config.size / 100));
            img.set({
                opacity: config.opacity / 100
            });

            // Posicionar
            positionLogoOnCanvas(img, config.position);

            mockupCanvas.add(img);
            mockupCanvas.setActiveObject(img);
        });
    }
}

/**
 * Posicionar logo no canvas
 */
function positionLogoOnCanvas(obj, position) {
    const positions = {
        'center': () => obj.center(),
        'top-left': () => { obj.set({ left: 50, top: 50 }); },
        'top-center': () => { obj.set({ left: mockupCanvas.width / 2 - obj.width / 2, top: 50 }); },
        'top-right': () => { obj.set({ left: mockupCanvas.width - obj.width - 50, top: 50 }); },
        'center-left': () => { obj.set({ left: 50, top: mockupCanvas.height / 2 - obj.height / 2 }); },
        'center-right': () => { obj.set({ left: mockupCanvas.width - obj.width - 50, top: mockupCanvas.height / 2 - obj.height / 2 }); },
        'bottom-left': () => { obj.set({ left: 50, top: mockupCanvas.height - obj.height - 50 }); },
        'bottom-center': () => { obj.set({ left: mockupCanvas.width / 2 - obj.width / 2, top: mockupCanvas.height - obj.height - 50 }); },
        'bottom-right': () => { obj.set({ left: mockupCanvas.width - obj.width - 50, top: mockupCanvas.height - obj.height - 50 }); }
    };

    if (positions[position]) {
        positions[position]();
    } else {
        obj.center();
    }
}

/**
 * Adicionar texto ao canvas
 */
function addTextToCanvas() {
    const text = new fabric.IText('Seu texto aqui', {
        left: 100,
        top: 100,
        fontFamily: 'Arial',
        fontSize: 40,
        fill: '#000000'
    });

    mockupCanvas.add(text);
    mockupCanvas.setActiveObject(text);
}

/**
 * Adicionar forma ao canvas
 */
function addShapeToCanvas(shape) {
    let obj;

    switch (shape) {
        case 'rectangle':
            obj = new fabric.Rect({
                left: 100,
                top: 100,
                width: 200,
                height: 100,
                fill: '#cccccc',
                stroke: '#000000',
                strokeWidth: 2
            });
            break;
        case 'circle':
            obj = new fabric.Circle({
                left: 100,
                top: 100,
                radius: 50,
                fill: '#cccccc',
                stroke: '#000000',
                strokeWidth: 2
            });
            break;
        case 'line':
            obj = new fabric.Line([50, 100, 200, 100], {
                stroke: '#000000',
                strokeWidth: 3
            });
            break;
    }

    if (obj) {
        mockupCanvas.add(obj);
        mockupCanvas.setActiveObject(obj);
    }
}

/**
 * Deletar objeto selecionado
 */
function deleteSelectedObject() {
    const activeObject = mockupCanvas.getActiveObject();
    if (activeObject) {
        mockupCanvas.remove(activeObject);
    }
}

/**
 * Limpar canvas
 */
function clearCanvas() {
    if (confirm('Tem certeza que deseja limpar o canvas?')) {
        mockupCanvas.clear();
        mockupCanvas.backgroundColor = '#ffffff';
    }
}

/**
 * Salvar canvas como mockup
 */
async function saveCanvasMockup() {
    const canvasData = mockupCanvas.toJSON();

    try {
        const response = await fetch(`/api/conversations/${mockupWizard.conversationId}/mockups/save-canvas`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                canvas_data: canvasData,
                product_image_path: mockupWizard.selectedProduct,
                logo_image_path: mockupWizard.selectedLogo,
                logo_config: mockupWizard.logoConfig
            })
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Erro ao salvar mockup');
        }

        Swal.fire({
            icon: 'success',
            title: 'Mockup Salvo!',
            text: 'Mockup criado com sucesso',
            showConfirmButton: true,
            showCancelButton: true,
            confirmButtonText: 'Enviar na Conversa',
            cancelButtonText: 'Fechar'
        }).then((result) => {
            if (result.isConfirmed) {
                sendMockupAsMessage(data.generation_id);
            }
        });

        // Fechar modal
        bootstrap.Modal.getInstance(document.getElementById('kt_modal_mockup_generator')).hide();

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message
        });
    }
}

/**
 * Exportar canvas como imagem
 */
function exportCanvasAsImage() {
    const dataURL = mockupCanvas.toDataURL({
        format: 'png',
        quality: 1
    });

    // Download
    const link = document.createElement('a');
    link.download = 'mockup_' + Date.now() + '.png';
    link.href = dataURL;
    link.click();
}

/**
 * Desfazer (Undo)
 */
function undoCanvas() {
    // Implementação simples de undo/redo requer histórico
    // Por simplicidade, não implementado aqui
    Swal.fire({
        icon: 'info',
        title: 'Função não disponível',
        text: 'Undo/Redo será implementado em breve'
    });
}

/**
 * Redimensionar canvas
 */
function resizeCanvas(width, height) {
    mockupCanvas.setDimensions({
        width: width,
        height: height
    });
}
