<?php
$layout = 'layouts.metronic.app';
$title = 'Construtor de Automações';
$pageTitle = 'Automações';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    Construtor de Automações de Campanhas
                </h1>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-light" onclick="previewAutomation()">
                    <i class="ki-duotone ki-eye fs-3"></i>
                    Preview
                </button>
                <button class="btn btn-sm btn-primary" onclick="saveAutomation()">
                    <i class="ki-duotone ki-check fs-3"></i>
                    Salvar Automação
                </button>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <div class="row">
                <div class="col-xl-9">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Canvas de Automação</h3>
                        </div>
                        <div class="card-body" style="min-height: 600px; background: #f9f9f9;">
                            
                            <!-- Canvas interativo -->
                            <div id="automation_canvas" style="position: relative; min-height: 500px;">
                                
                                <!-- Início -->
                                <div class="automation-node start-node" style="position: absolute; top: 20px; left: 50%; transform: translateX(-50%);">
                                    <div class="card shadow-sm" style="width: 200px;">
                                        <div class="card-body text-center p-3" style="background: linear-gradient(135deg, #50CD89 0%, #28A745 100%);">
                                            <i class="ki-duotone ki-play fs-3x text-white mb-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            <div class="text-white fw-bold">Início</div>
                                            <div class="text-white fs-7">Contato entra na lista</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Placeholder para nodes -->
                                <div class="text-center text-muted" style="position: absolute; top: 200px; left: 50%; transform: translateX(-50%);">
                                    <i class="ki-duotone ki-plus-square fs-5x mb-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <div class="fw-bold fs-5">Arraste blocos da lateral para construir</div>
                                </div>
                                
                            </div>
                            
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3">
                    <!-- Paleta de Blocos -->
                    <div class="card" style="position: sticky; top: 100px;">
                        <div class="card-header">
                            <h3 class="card-title">Blocos</h3>
                        </div>
                        <div class="card-body">
                            
                            <div class="mb-5">
                                <h5 class="mb-3">Ações</h5>
                                
                                <div class="card mb-3 cursor-pointer hover-elevate-up" draggable="true" data-block-type="send_message">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center">
                                            <i class="ki-duotone ki-send fs-2x text-primary me-3"></i>
                                            <div>
                                                <div class="fw-bold">Enviar Mensagem</div>
                                                <div class="text-muted fs-7">Disparo de mensagem</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mb-3 cursor-pointer hover-elevate-up" draggable="true" data-block-type="wait">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center">
                                            <i class="ki-duotone ki-timer fs-2x text-warning me-3"></i>
                                            <div>
                                                <div class="fw-bold">Aguardar</div>
                                                <div class="text-muted fs-7">Delay configurável</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mb-3 cursor-pointer hover-elevate-up" draggable="true" data-block-type="add_tag">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center">
                                            <i class="ki-duotone ki-tag fs-2x text-success me-3"></i>
                                            <div>
                                                <div class="fw-bold">Adicionar Tag</div>
                                                <div class="text-muted fs-7">Marcar contato</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-5">
                                <h5 class="mb-3">Condições</h5>
                                
                                <div class="card mb-3 cursor-pointer hover-elevate-up" draggable="true" data-block-type="condition">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center">
                                            <i class="ki-duotone ki-abstract-39 fs-2x text-info me-3"></i>
                                            <div>
                                                <div class="fw-bold">Condição</div>
                                                <div class="text-muted fs-7">Se/Então</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
// Drag and Drop (simplificado - em produção usar biblioteca como jsPlumb)
let nodes = [];

document.addEventListener('DOMContentLoaded', () => {
    // Implementar drag and drop básico
    const blocks = document.querySelectorAll('[draggable="true"]');
    const canvas = document.getElementById('automation_canvas');
    
    blocks.forEach(block => {
        block.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('block-type', block.dataset.blockType);
        });
    });
    
    canvas.addEventListener('dragover', (e) => {
        e.preventDefault();
    });
    
    canvas.addEventListener('drop', (e) => {
        e.preventDefault();
        const blockType = e.dataTransfer.getData('block-type');
        addNodeToCanvas(blockType, e.offsetX, e.offsetY);
    });
});

function addNodeToCanvas(type, x, y) {
    const nodeId = 'node_' + Date.now();
    
    const templates = {
        'send_message': {
            title: 'Enviar Mensagem',
            icon: 'send',
            color: 'primary',
            content: '<textarea class="form-control form-control-sm mt-2" placeholder="Mensagem..."></textarea>'
        },
        'wait': {
            title: 'Aguardar',
            icon: 'timer',
            color: 'warning',
            content: '<input type="number" class="form-control form-control-sm mt-2" placeholder="Dias..." min="1">'
        },
        'add_tag': {
            title: 'Adicionar Tag',
            icon: 'tag',
            color: 'success',
            content: '<input type="text" class="form-control form-control-sm mt-2" placeholder="Nome da tag...">'
        },
        'condition': {
            title: 'Condição',
            icon: 'abstract-39',
            color: 'info',
            content: '<select class="form-select form-select-sm mt-2"><option>Se respondeu</option><option>Se não respondeu</option></select>'
        }
    };
    
    const template = templates[type];
    if (!template) return;
    
    const nodeHtml = `
        <div class="card shadow" id="${nodeId}" style="position: absolute; top: ${y}px; left: ${x}px; width: 220px; cursor: move;" draggable="true">
            <div class="card-header p-2 bg-light-${template.color}">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="ki-duotone ki-${template.icon} fs-2 text-${template.color} me-2"></i>
                        <span class="fw-bold fs-7">${template.title}</span>
                    </div>
                    <button class="btn btn-sm btn-icon btn-light-danger" onclick="removeNode('${nodeId}')">
                        <i class="ki-duotone ki-cross fs-7"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-3">
                ${template.content}
            </div>
        </div>
    `;
    
    document.getElementById('automation_canvas').insertAdjacentHTML('beforeend', nodeHtml);
    
    // Tornar node arrastável
    makeNodeDraggable(nodeId);
    
    nodes.push({ id: nodeId, type: type, x: x, y: y });
}

function makeNodeDraggable(nodeId) {
    const node = document.getElementById(nodeId);
    let isDragging = false;
    let currentX, currentY, initialX, initialY;
    
    node.addEventListener('mousedown', (e) => {
        if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'SELECT') {
            isDragging = true;
            initialX = e.clientX - node.offsetLeft;
            initialY = e.clientY - node.offsetTop;
        }
    });
    
    document.addEventListener('mousemove', (e) => {
        if (isDragging) {
            e.preventDefault();
            currentX = e.clientX - initialX;
            currentY = e.clientY - initialY;
            node.style.left = currentX + 'px';
            node.style.top = currentY + 'px';
        }
    });
    
    document.addEventListener('mouseup', () => {
        isDragging = false;
    });
}

function removeNode(nodeId) {
    document.getElementById(nodeId).remove();
    nodes = nodes.filter(n => n.id !== nodeId);
}

function previewAutomation() {
    if (nodes.length === 0) {
        toastr.warning('Adicione pelo menos um bloco');
        return;
    }
    
    alert('Preview: ' + nodes.length + ' blocos configurados');
}

function saveAutomation() {
    if (nodes.length === 0) {
        toastr.error('Adicione pelo menos um bloco');
        return;
    }
    
    const automationData = {
        name: prompt('Nome da automação:'),
        nodes: nodes
    };
    
    if (!automationData.name) return;
    
    toastr.success('Automação salva! (Em desenvolvimento)');
}
</script>

<style>
.hover-elevate-up:hover {
    transform: translateY(-2px);
    transition: all 0.3s ease;
}
.cursor-pointer {
    cursor: pointer;
}
</style>
