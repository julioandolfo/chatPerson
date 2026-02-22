<script>
/**
 * AUTOMATION EDITOR - MODERN JAVASCRIPT
 * Sistema de automações com layout moderno
 */

// ============================================
// VARIÁVEIS GLOBAIS
// ============================================
let canvas, canvasContainer, canvasContent, connectionsSvg, nodesContainer;
let nodes = [];
let connections = [];
let nodeTypes = <?= json_encode($nodeTypes ?? []) ?>;
let automationId = <?= json_encode($automation['id'] ?? null) ?>;
let automationTriggerType = <?= json_encode($automation['trigger_type'] ?? 'new_conversation') ?>;

// Estado do Canvas
let canvasScale = 1;
let canvasTranslate = { x: 0, y: 0 };
const MIN_SCALE = 0.1;
const MAX_SCALE = 3;

// Estado de Interação
let isPanning = false;
let panStart = { x: 0, y: 0 };
let panInitialTranslate = { x: 0, y: 0 };
let isConnecting = false;
let connectingFrom = null;
let connectingLine = null;
let connectingStartPos = null;
let selectedNodes = new Set();
let draggedNode = null;

// Undo/Redo
let undoStack = [];
let redoStack = [];
const MAX_HISTORY = 50;

// ============================================
// INICIALIZAÇÃO
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    initElements();
    initCanvas();
    initNodes();
    initEventListeners();
    refreshLogs();
    
    // Ajustar à tela após carregar
    setTimeout(fitToScreen, 100);
    
    showToast('Editor carregado com sucesso!', 'success');
});

function initElements() {
    canvas = document.getElementById('automation_canvas');
    canvasContainer = document.getElementById('automation_canvas_container');
    canvasContent = canvas;
    connectionsSvg = document.getElementById('connections_svg');
    nodesContainer = document.getElementById('nodes_container');
    
    // Garantir que temos acesso aos dados dos nós do PHP
    const initialNodes = <?= json_encode($nodes ?? []) ?>;
    if (Array.isArray(initialNodes)) {
        nodes = initialNodes.map(n => ({
            ...n,
            node_data: typeof n.node_data === 'string' ? JSON.parse(n.node_data) : (n.node_data || {})
        }));
    }
}

// ============================================
// SISTEMA DE CANVAS E ZOOM
// ============================================
function initCanvas() {
    // Centralizar canvas inicialmente
    const containerRect = canvasContainer.getBoundingClientRect();
    canvasTranslate = {
        x: containerRect.width / 2 - 2500,
        y: containerRect.height / 2 - 2500
    };
    applyCanvasTransform();
}

function applyCanvasTransform() {
    if (!canvas) return;
    const matrix = `matrix(${canvasScale}, 0, 0, ${canvasScale}, ${canvasTranslate.x}, ${canvasTranslate.y})`;
    canvas.style.transform = matrix;
    
    // Atualizar label de zoom
    const zoomLabel = document.getElementById('zoom_value');
    if (zoomLabel) {
        zoomLabel.textContent = Math.round(canvasScale * 100) + '%';
    }
    
    // Atualizar mini-map se visível
    updateMinimap();
}

function setCanvasScale(newScale, focalX, focalY) {
    newScale = Math.max(MIN_SCALE, Math.min(MAX_SCALE, newScale));
    if (newScale === canvasScale) return;
    
    const prevScale = canvasScale;
    const viewportRect = canvasContainer.getBoundingClientRect();
    
    focalX = focalX ?? viewportRect.width / 2;
    focalY = focalY ?? viewportRect.height / 2;
    
    // Calcular novo translate para zoom em direção ao ponto focal
    canvasTranslate.x = focalX - ((focalX - canvasTranslate.x) * (newScale / prevScale));
    canvasTranslate.y = focalY - ((focalY - canvasTranslate.y) * (newScale / prevScale));
    
    canvasScale = newScale;
    applyCanvasTransform();
    renderConnections();
    
    saveState();
}

function zoomIn() {
    const newScale = Math.min(MAX_SCALE, canvasScale + 0.1);
    setCanvasScale(newScale);
}

function zoomOut() {
    const newScale = Math.max(MIN_SCALE, canvasScale - 0.1);
    setCanvasScale(newScale);
}

function centerCanvas() {
    const containerRect = canvasContainer.getBoundingClientRect();
    canvasTranslate = {
        x: containerRect.width / 2 - 2500,
        y: containerRect.height / 2 - 2500
    };
    applyCanvasTransform();
    renderConnections();
}

function fitToScreen() {
    if (nodes.length === 0) {
        centerCanvas();
        return;
    }
    
    // Calcular bounding box de todos os nós
    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    nodes.forEach(node => {
        const x = node.position_x || 0;
        const y = node.position_y || 0;
        minX = Math.min(minX, x);
        minY = Math.min(minY, y);
        maxX = Math.max(maxX, x + 240); // largura do nó
        maxY = Math.max(maxY, y + 100); // altura estimada
    });
    
    const containerRect = canvasContainer.getBoundingClientRect();
    const padding = 100;
    const contentWidth = maxX - minX + padding * 2;
    const contentHeight = maxY - minY + padding * 2;
    
    // Calcular zoom ideal
    const scaleX = containerRect.width / contentWidth;
    const scaleY = containerRect.height / contentHeight;
    const idealScale = Math.max(MIN_SCALE, Math.min(MAX_SCALE, Math.min(scaleX, scaleY) * 0.9));
    
    canvasScale = idealScale;
    canvasTranslate = {
        x: (containerRect.width / 2) - ((minX + maxX) / 2 + 2500) * idealScale,
        y: (containerRect.height / 2) - ((minY + maxY) / 2 + 2500) * idealScale
    };
    
    applyCanvasTransform();
    renderConnections();
    saveState();
    
    showToast('Ajustado à tela', 'info');
}

// ============================================
// EVENT LISTENERS
// ============================================
function initEventListeners() {
    // Zoom com mouse wheel
    canvasContainer.addEventListener('wheel', function(e) {
        if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.1 : 0.1;
            setCanvasScale(canvasScale + delta, e.offsetX, e.offsetY);
        }
    }, { passive: false });
    
    // Pan com middle mouse ou space+drag
    canvasContainer.addEventListener('mousedown', function(e) {
        // Middle mouse button ou space pressionado
        if (e.button === 1 || (e.button === 0 && e.code === 'Space')) {
            e.preventDefault();
            startPan(e.clientX, e.clientY);
        }
    });
    
    document.addEventListener('mousemove', function(e) {
        if (isPanning) {
            e.preventDefault();
            const deltaX = e.clientX - panStart.x;
            const deltaY = e.clientY - panStart.y;
            canvasTranslate.x = panInitialTranslate.x + deltaX;
            canvasTranslate.y = panInitialTranslate.y + deltaY;
            applyCanvasTransform();
            renderConnections();
        }
        
        if (isConnecting && connectingLine) {
            updateConnectingLine(e);
        }
    });
    
    document.addEventListener('mouseup', function() {
        if (isPanning) {
            isPanning = false;
            canvasContainer.classList.remove('is-panning');
            saveState();
        }
        
        if (isConnecting) {
            cancelConnection();
        }
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Espaço para pan
        if (e.code === 'Space' && !e.repeat) {
            canvasContainer.style.cursor = 'grab';
        }
        
        // Delete para remover nós selecionados
        if (e.key === 'Delete' || e.key === 'Backspace') {
            if (selectedNodes.size > 0 && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault();
                selectedNodes.forEach(nodeId => deleteNode(nodeId));
            }
        }
        
        // Ctrl+Z para undo
        if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
            e.preventDefault();
            undo();
        }
        
        // Ctrl+Y ou Ctrl+Shift+Z para redo
        if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) {
            e.preventDefault();
            redo();
        }
        
        // F para fit to screen
        if (e.key === 'f' && !e.ctrlKey && !e.metaKey) {
            if (document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault();
                fitToScreen();
            }
        }
        
        // C para centralizar
        if (e.key === 'c' && !e.ctrlKey && !e.metaKey) {
            if (document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault();
                centerCanvas();
            }
        }
        
        // +/- para zoom
        if (e.key === '+' || e.key === '=') {
            e.preventDefault();
            zoomIn();
        }
        if (e.key === '-') {
            e.preventDefault();
            zoomOut();
        }
    });
    
    document.addEventListener('keyup', function(e) {
        if (e.code === 'Space') {
            canvasContainer.style.cursor = '';
        }
    });
    
    // Resize observer para ajustar quando container muda de tamanho
    new ResizeObserver(() => {
        renderConnections();
    }).observe(canvasContainer);
}

function startPan(x, y) {
    isPanning = true;
    panStart = { x, y };
    panInitialTranslate = { ...canvasTranslate };
    canvasContainer.classList.add('is-panning');
}

// ============================================
// RENDERIZAÇÃO DE NÓS
// ============================================
function initNodes() {
    nodesContainer.innerHTML = '';
    nodes.forEach(node => renderNode(node));
    renderConnections();
}

function renderNode(node) {
    // Verificar se já existe
    const existing = document.getElementById('node-' + node.id);
    if (existing) existing.remove();
    
    const config = nodeTypes[node.node_type] || {};
    const nodeEl = document.createElement('div');
    nodeEl.id = 'node-' + node.id;
    nodeEl.className = 'automation-node' + (selectedNodes.has(String(node.id)) ? ' selected' : '');
    nodeEl.style.left = (node.position_x || 0) + 'px';
    nodeEl.style.top = (node.position_y || 0) + 'px';
    
    // Determinar categoria e ícone
    let category = 'action';
    if (node.node_type === 'trigger') category = 'trigger';
    else if (node.node_type.includes('condition')) category = 'condition';
    else if (['delay', 'end'].includes(node.node_type)) category = 'flow';
    
    // Construir HTML do nó
    let outputsHtml = '';
    const nodeData = node.node_data || {};
    
    // Verificar se tem outputs especiais (chatbot, AI agent, condição)
    if (node.node_type === 'action_chatbot' && nodeData.chatbot_type === 'menu' && nodeData.chatbot_options) {
        outputsHtml = '<div class="node-outputs-section">';
        nodeData.chatbot_options.forEach((opt, idx) => {
            const label = typeof opt === 'object' ? opt.text : opt;
            outputsHtml += `
                <div class="node-output-row">
                    <span class="node-output-label">${idx + 1}. ${label}</span>
                    <div class="node-handle output-side" data-handle="output" data-index="${idx}" data-type="option"></div>
                </div>`;
        });
        outputsHtml += '</div>';
    } else if (node.node_type === 'action_assign_ai_agent' && nodeData.ai_intents) {
        outputsHtml = '<div class="node-outputs-section">';
        nodeData.ai_intents.forEach((intent, idx) => {
            const label = intent.description || intent.intent || `Intent ${idx + 1}`;
            outputsHtml += `
                <div class="node-output-row">
                    <span class="node-output-label">🎯 ${label}</span>
                    <div class="node-handle output-side success" data-handle="output" data-index="${idx}" data-type="intent"></div>
                </div>`;
        });
        outputsHtml += '</div>';
    } else if (node.node_type === 'condition') {
        outputsHtml = `
            <div class="node-outputs-section">
                <div class="node-output-row">
                    <span class="node-output-label" style="color: var(--accent-success)">✓ Verdadeiro</span>
                    <div class="node-handle output-side success" data-handle="output" data-type="true"></div>
                </div>
                <div class="node-output-row">
                    <span class="node-output-label" style="color: var(--accent-danger)">✗ Falso</span>
                    <div class="node-handle output-side error" data-handle="output" data-type="false"></div>
                </div>
            </div>`;
    } else if (node.node_type === 'condition_business_hours') {
        outputsHtml = `
            <div class="node-outputs-section">
                <div class="node-output-row">
                    <span class="node-output-label" style="color: var(--accent-success)">☀️ Dentro</span>
                    <div class="node-handle output-side success" data-handle="output" data-type="within"></div>
                </div>
                <div class="node-output-row">
                    <span class="node-output-label" style="color: var(--accent-danger)">🌙 Fora</span>
                    <div class="node-handle output-side error" data-handle="output" data-type="outside"></div>
                </div>
            </div>`;
    }
    
    // Preview de configuração
    let configPreview = '';
    if (nodeData.label) {
        configPreview = `<div class="node-config-preview">${nodeData.label}</div>`;
    }
    
    nodeEl.innerHTML = `
        <div class="node-handle input" data-handle="input"></div>
        ${!['action_chatbot', 'action_assign_ai_agent', 'condition', 'condition_business_hours'].includes(node.node_type) ? 
            `<div class="node-handle output" data-handle="output"></div>` : ''}
        
        <div class="node-actions">
            <button class="node-action-btn edit" onclick="openNodeConfig('${node.id}')">
                <i class="ki-duotone ki-pencil fs-6"></i>
            </button>
            <button class="node-action-btn delete" onclick="deleteNode('${node.id}')">
                <i class="ki-duotone ki-trash fs-6"></i>
            </button>
        </div>
        
        <div class="node-header" onmousedown="startNodeDrag(event, '${node.id}')">
            <div class="node-icon" style="background: ${(config.color || '#3b82f6')}20; color: ${config.color || '#3b82f6'}">
                <i class="ki-duotone ${config.icon || 'ki-gear'}"></i>
            </div>
            <div class="node-title">
                <div class="title-text">${config.label || node.node_type}</div>
                <div class="subtitle-text">${nodeData.description || ''}</div>
            </div>
            <span class="node-id-badge">#${node.id}</span>
        </div>
        
        <div class="node-body">
            ${configPreview}
            ${outputsHtml}
        </div>
    `;
    
    // Adicionar eventos aos handles
    const handles = nodeEl.querySelectorAll('.node-handle.output, .node-handle.output-side');
    handles.forEach(handle => {
        handle.addEventListener('mousedown', (e) => {
            e.stopPropagation();
            startConnection(node.id, handle, e);
        });
    });
    
    const inputHandle = nodeEl.querySelector('.node-handle.input');
    if (inputHandle) {
        inputHandle.addEventListener('mouseup', (e) => {
            e.stopPropagation();
            if (isConnecting) {
                endConnection(node.id, e);
            }
        });
    }
    
    // Click para selecionar
    nodeEl.addEventListener('click', (e) => {
        if (!e.target.closest('.node-action-btn') && !e.target.classList.contains('node-handle')) {
            selectNode(node.id, e.ctrlKey || e.metaKey);
        }
    });
    
    nodesContainer.appendChild(nodeEl);
}

function renderNodes() {
    nodesContainer.innerHTML = '';
    nodes.forEach(node => renderNode(node));
}

// ============================================
// DRAG DE NÓS
// ============================================
function startNodeDrag(e, nodeId) {
    if (e.button !== 0) return; // Só left click
    if (e.target.closest('.node-handle')) return; // Não iniciar se clicou em handle
    
    e.preventDefault();
    e.stopPropagation();
    
    draggedNode = nodeId;
    const node = nodes.find(n => String(n.id) === String(nodeId));
    if (!node) return;
    
    const startX = e.clientX;
    const startY = e.clientY;
    const initialX = node.position_x || 0;
    const initialY = node.position_y || 0;
    
    const nodeEl = document.getElementById('node-' + nodeId);
    nodeEl.style.zIndex = '1000';
    
    function onMouseMove(e) {
        const deltaX = (e.clientX - startX) / canvasScale;
        const deltaY = (e.clientY - startY) / canvasScale;
        
        node.position_x = initialX + deltaX;
        node.position_y = initialY + deltaY;
        
        nodeEl.style.left = node.position_x + 'px';
        nodeEl.style.top = node.position_y + 'px';
        
        renderConnections();
    }
    
    function onMouseUp() {
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
        nodeEl.style.zIndex = '';
        draggedNode = null;
        saveState();
    }
    
    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
}

function selectNode(nodeId, multi = false) {
    const id = String(nodeId);
    
    if (!multi) {
        selectedNodes.clear();
    }
    
    if (selectedNodes.has(id)) {
        selectedNodes.delete(id);
    } else {
        selectedNodes.add(id);
    }
    
    // Atualizar visual
    document.querySelectorAll('.automation-node').forEach(el => {
        el.classList.remove('selected');
    });
    selectedNodes.forEach(id => {
        const el = document.getElementById('node-' + id);
        if (el) el.classList.add('selected');
    });
}

// ============================================
// CONEXÕES
// ============================================
function startConnection(fromNodeId, handle, e) {
    isConnecting = true;
    connectingFrom = {
        nodeId: fromNodeId,
        handle: handle,
        type: handle.dataset.type || 'default',
        index: handle.dataset.index
    };
    
    canvasContainer.classList.add('is-connecting');
    
    // Criar linha temporária
    const startPos = getHandlePosition(handle);
    connectingStartPos = startPos;
    
    connectingLine = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    connectingLine.setAttribute('class', 'connecting-line');
    connectingLine.setAttribute('stroke', '#3b82f6');
    connectingLine.setAttribute('stroke-width', '2');
    connectingLine.setAttribute('fill', 'none');
    connectionsSvg.appendChild(connectingLine);
    
    updateConnectingLine(e);
}

function updateConnectingLine(e) {
    if (!connectingLine || !connectingStartPos) return;
    
    const rect = canvasContainer.getBoundingClientRect();
    const x = (e.clientX - rect.left - canvasTranslate.x) / canvasScale;
    const y = (e.clientY - rect.top - canvasTranslate.y) / canvasScale;
    
    // Curva Bézier
    const dx = x - connectingStartPos.x;
    const offsetX = Math.max(80, Math.min(Math.abs(dx) * 0.5, 150));
    
    const path = `M ${connectingStartPos.x} ${connectingStartPos.y} 
                  C ${connectingStartPos.x + offsetX} ${connectingStartPos.y},
                    ${x - offsetX} ${y},
                    ${x} ${y}`;
    
    connectingLine.setAttribute('d', path);
}

function endConnection(toNodeId, e) {
    if (!isConnecting || !connectingFrom) return;
    
    // Não permitir conectar no mesmo nó
    if (String(toNodeId) === String(connectingFrom.nodeId)) {
        cancelConnection();
        return;
    }
    
    // Adicionar conexão
    const fromNode = nodes.find(n => String(n.id) === String(connectingFrom.nodeId));
    if (!fromNode) {
        cancelConnection();
        return;
    }
    
    if (!fromNode.node_data) fromNode.node_data = {};
    if (!fromNode.node_data.connections) fromNode.node_data.connections = [];
    
    // Verificar se já existe conexão similar
    const exists = fromNode.node_data.connections.some(c => 
        String(c.target_node_id) === String(toNodeId) &&
        c.connection_type === (connectingFrom.type !== 'default' ? connectingFrom.type : null)
    );
    
    if (!exists) {
        fromNode.node_data.connections.push({
            target_node_id: toNodeId,
            connection_type: connectingFrom.type !== 'default' ? connectingFrom.type : null,
            option_index: connectingFrom.index !== undefined ? parseInt(connectingFrom.index) : null
        });
        
        showToast('Conexão criada', 'success');
        saveState();
        saveLayout();
    }
    
    cancelConnection();
    renderConnections();
}

function cancelConnection() {
    isConnecting = false;
    canvasContainer.classList.remove('is-connecting');
    
    if (connectingLine) {
        connectingLine.remove();
        connectingLine = null;
    }
    
    connectingFrom = null;
    connectingStartPos = null;
}

function renderConnections() {
    // Limpar conexões existentes (exceto a linha de conexão ativa)
    const groups = connectionsSvg.querySelectorAll('.connection-group');
    groups.forEach(g => g.remove());
    
    nodes.forEach(fromNode => {
        if (!fromNode.node_data?.connections) return;
        
        fromNode.node_data.connections.forEach(conn => {
            const toNode = nodes.find(n => String(n.id) === String(conn.target_node_id));
            if (!toNode) return;
            
            // Determinar handles
            let fromHandle, toHandle;
            const fromEl = document.getElementById('node-' + fromNode.id);
            const toEl = document.getElementById('node-' + toNode.id);
            if (!fromEl || !toEl) return;
            
            // Encontrar handle de saída
            if (conn.connection_type && conn.connection_type !== 'default') {
                fromHandle = fromEl.querySelector(`[data-type="${conn.connection_type}"]`) ||
                           fromEl.querySelector(`[data-index="${conn.option_index}"]`);
            } else {
                fromHandle = fromEl.querySelector('.node-handle.output, .node-handle.output-side');
            }
            
            // Handle de entrada
            toHandle = toEl.querySelector('.node-handle.input');
            
            if (!fromHandle || !toHandle) return;
            
            const fromPos = getHandlePosition(fromHandle);
            const toPos = getHandlePosition(toHandle);
            
            // Criar grupo
            const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            group.setAttribute('class', 'connection-group');
            
            // Cor baseada no tipo
            let color = '#3b82f6';
            if (conn.connection_type === 'true' || conn.connection_type === 'within') color = '#10b981';
            else if (conn.connection_type === 'false' || conn.connection_type === 'outside') color = '#ef4444';
            
            // Curva Bézier
            const dx = toPos.x - fromPos.x;
            const offsetX = Math.max(80, Math.min(Math.abs(dx) * 0.5, 150));
            
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            const d = `M ${fromPos.x} ${fromPos.y} 
                       C ${fromPos.x + offsetX} ${fromPos.y},
                         ${toPos.x - offsetX} ${toPos.y},
                         ${toPos.x} ${toPos.y}`;
            path.setAttribute('d', d);
            path.setAttribute('class', 'connection-line');
            path.setAttribute('stroke', color);
            path.setAttribute('stroke-width', '2');
            path.setAttribute('fill', 'none');
            path.setAttribute('marker-end', `url(#arrow-${color === '#10b981' ? 'success' : color === '#ef4444' ? 'error' : 'default'})`);
            
            // Botão de delete
            const midX = (fromPos.x + toPos.x) / 2;
            const midY = (fromPos.y + toPos.y) / 2;
            
            const deleteBtn = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            deleteBtn.setAttribute('class', 'connection-delete-btn');
            deleteBtn.setAttribute('transform', `translate(${midX}, ${midY})`);
            deleteBtn.style.cursor = 'pointer';
            deleteBtn.innerHTML = `
                <circle r="8" fill="#ef4444" stroke="white" stroke-width="2"/>
                <path d="M-3,-3 L3,3 M-3,3 L3,-3" stroke="white" stroke-width="2" stroke-linecap="round"/>
            `;
            deleteBtn.addEventListener('click', () => {
                removeConnection(fromNode.id, toNode.id, conn.connection_type, conn.option_index);
            });
            
            group.appendChild(path);
            group.appendChild(deleteBtn);
            connectionsSvg.appendChild(group);
        });
    });
}

function getHandlePosition(handle) {
    const nodeEl = handle.closest('.automation-node');
    const nodeRect = nodeEl.getBoundingClientRect();
    const handleRect = handle.getBoundingClientRect();
    
    // Converter para coordenadas do canvas
    return {
        x: (handleRect.left - nodeRect.left + handleRect.width / 2 + parseFloat(nodeEl.style.left || 0)),
        y: (handleRect.top - nodeRect.top + handleRect.height / 2 + parseFloat(nodeEl.style.top || 0))
    };
}

function removeConnection(fromId, toId, type, optionIndex) {
    const fromNode = nodes.find(n => String(n.id) === String(fromId));
    if (!fromNode || !fromNode.node_data?.connections) return;
    
    fromNode.node_data.connections = fromNode.node_data.connections.filter(c => {
        if (String(c.target_node_id) !== String(toId)) return true;
        if (type && c.connection_type !== type) return true;
        if (optionIndex !== undefined && c.option_index !== optionIndex) return true;
        return false;
    });
    
    renderConnections();
    saveState();
    saveLayout();
    showToast('Conexão removida', 'info');
}

// ============================================
// ADICIONAR/EDITAR/DELETAR NÓS
// ============================================
function addNode(type, x, y) {
    const config = nodeTypes[type] || {};
    
    // Gerar ID temporário
    const tempId = 'temp-' + Date.now();
    
    // Posição padrão se não especificada
    if (x === undefined || y === undefined) {
        const containerRect = canvasContainer.getBoundingClientRect();
        x = (containerRect.width / 2 - canvasTranslate.x) / canvasScale - 2500;
        y = (containerRect.height / 2 - canvasTranslate.y) / canvasScale - 2500;
    }
    
    const newNode = {
        id: tempId,
        node_type: type,
        position_x: x,
        position_y: y,
        node_data: {
            label: config.label || type,
            description: '',
            connections: []
        }
    };
    
    nodes.push(newNode);
    renderNode(newNode);
    saveState();
    
    // Abrir configuração imediatamente
    openNodeConfig(tempId);
    
    showToast(`${config.label || type} adicionado`, 'success');
}

function deleteNode(nodeId) {
    if (!confirm('Deseja remover este nó e todas as suas conexões?')) return;
    
    // Remover conexões que apontam para este nó
    nodes.forEach(node => {
        if (node.node_data?.connections) {
            node.node_data.connections = node.node_data.connections.filter(
                c => String(c.target_node_id) !== String(nodeId)
            );
        }
    });
    
    // Remover nó
    nodes = nodes.filter(n => String(n.id) !== String(nodeId));
    
    // Remover do DOM
    const el = document.getElementById('node-' + nodeId);
    if (el) el.remove();
    
    selectedNodes.delete(String(nodeId));
    
    renderConnections();
    saveState();
    saveLayout();
    showToast('Nó removido', 'info');
}

// ============================================
// CONFIGURAÇÃO DE NÓS (Modal)
// ============================================
function openNodeConfig(nodeId) {
    const node = nodes.find(n => String(n.id) === String(nodeId));
    if (!node) return;
    
    const config = nodeTypes[node.node_type] || {};
    const nodeData = node.node_data || {};
    
    // Preencher campos do modal
    document.getElementById('kt_node_id').value = nodeId;
    document.getElementById('kt_node_type').value = node.node_type;
    document.getElementById('kt_modal_node_config_title').textContent = 'Configurar: ' + (config.label || node.node_type);
    
    // Gerar conteúdo do formulário baseado no tipo
    let formContent = generateNodeForm(node.node_type, nodeData);
    document.getElementById('kt_node_config_content').innerHTML = formContent;
    
    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_node_config'));
    modal.show();
}

function generateNodeForm(type, data) {
    // Formulários específicos para cada tipo
    const commonFields = `
        <div class="fv-row mb-7">
            <label class="fw-semibold fs-6 mb-2">Nome/Label</label>
            <input type="text" name="label" class="form-control form-control-solid" value="${data.label || ''}" placeholder="Nome identificador do nó" />
        </div>
        <div class="fv-row mb-7">
            <label class="fw-semibold fs-6 mb-2">Descrição</label>
            <textarea name="description" class="form-control form-control-solid" rows="2" placeholder="Descrição opcional">${data.description || ''}</textarea>
        </div>
    `;
    
    switch(type) {
        case 'action_send_message':
            return commonFields + `
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Mensagem</label>
                    <textarea name="message" class="form-control form-control-solid" rows="4" required placeholder="Digite a mensagem...">${data.message || ''}</textarea>
                    <div class="form-text">Use {{variavel}} para variáveis dinâmicas</div>
                </div>
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Canal</label>
                    <select name="channel" class="form-select form-select-solid">
                        <option value="">Todos os canais</option>
                        <option value="whatsapp" ${data.channel === 'whatsapp' ? 'selected' : ''}>WhatsApp</option>
                        <option value="instagram" ${data.channel === 'instagram' ? 'selected' : ''}>Instagram</option>
                    </select>
                </div>
            `;
            
        case 'action_assign_agent':
            return commonFields + `
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Agente</label>
                    <select name="agent_id" class="form-select form-select-solid" required>
                        <option value="">Selecione um agente...</option>
                        <option value="auto" ${data.agent_id === 'auto' ? 'selected' : ''}>Auto-atribuição</option>
                    </select>
                </div>
            `;
            
        case 'delay':
            return commonFields + `
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Tempo de Espera</label>
                    <div class="row">
                        <div class="col-6">
                            <input type="number" name="delay_value" class="form-control form-control-solid" value="${data.delay_value || 5}" min="1" />
                        </div>
                        <div class="col-6">
                            <select name="delay_unit" class="form-select form-select-solid">
                                <option value="minutes" ${data.delay_unit === 'minutes' ? 'selected' : ''}>Minutos</option>
                                <option value="hours" ${data.delay_unit === 'hours' ? 'selected' : ''}>Horas</option>
                                <option value="days" ${data.delay_unit === 'days' ? 'selected' : ''}>Dias</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
            
        case 'condition':
            return commonFields + `
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Condição</label>
                    <select name="condition_type" class="form-select form-select-solid mb-3">
                        <option value="has_tag" ${data.condition_type === 'has_tag' ? 'selected' : ''}>Tem Tag</option>
                        <option value="in_stage" ${data.condition_type === 'in_stage' ? 'selected' : ''}>Está no Estágio</option>
                        <option value="message_contains" ${data.condition_type === 'message_contains' ? 'selected' : ''}>Mensagem Contém</option>
                    </select>
                    <input type="text" name="condition_value" class="form-control form-control-solid" value="${data.condition_value || ''}" placeholder="Valor a comparar" />
                </div>
            `;
            
        default:
            return commonFields + `
                <div class="alert alert-info">
                    <small>Configurações específicas para este tipo de nó.</small>
                </div>
            `;
    }
}

// Submeter configuração do nó
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('kt_modal_node_config_form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const nodeId = document.getElementById('kt_node_id').value;
            const nodeType = document.getElementById('kt_node_type').value;
            
            const node = nodes.find(n => String(n.id) === String(nodeId));
            if (!node) return;
            
            // Coletar dados do formulário
            const formData = new FormData(form);
            const newData = { ...node.node_data };
            
            formData.forEach((value, key) => {
                if (key !== 'node_id' && key !== 'node_type') {
                    newData[key] = value;
                }
            });
            
            node.node_data = newData;
            
            // Re-renderizar nó
            renderNode(node);
            renderConnections();
            
            // Fechar modal
            bootstrap.Modal.getInstance(document.getElementById('kt_modal_node_config')).hide();
            
            saveState();
            saveLayout();
            showToast('Configuração salva', 'success');
        });
    }
});

// ============================================
// UNDO/REDO
// ============================================
function saveState() {
    const state = JSON.stringify({
        nodes: nodes.map(n => ({...n})),
        scale: canvasScale,
        translate: { ...canvasTranslate }
    });
    
    undoStack.push(state);
    if (undoStack.length > MAX_HISTORY) {
        undoStack.shift();
    }
    
    redoStack = []; // Limpar redo ao fazer nova ação
}

function undo() {
    if (undoStack.length === 0) {
        showToast('Nada para desfazer', 'warning');
        return;
    }
    
    const currentState = JSON.stringify({
        nodes: nodes.map(n => ({...n})),
        scale: canvasScale,
        translate: { ...canvasTranslate }
    });
    redoStack.push(currentState);
    
    const state = JSON.parse(undoStack.pop());
    restoreState(state);
    showToast('Desfeito', 'info');
}

function redo() {
    if (redoStack.length === 0) {
        showToast('Nada para refazer', 'warning');
        return;
    }
    
    const currentState = JSON.stringify({
        nodes: nodes.map(n => ({...n})),
        scale: canvasScale,
        translate: { ...canvasTranslate }
    });
    undoStack.push(currentState);
    
    const state = JSON.parse(redoStack.pop());
    restoreState(state);
    showToast('Refeito', 'info');
}

function restoreState(state) {
    nodes = state.nodes;
    canvasScale = state.scale;
    canvasTranslate = state.translate;
    
    renderNodes();
    applyCanvasTransform();
    renderConnections();
}

// ============================================
// UI HELPERS
// ============================================
function toggleSidebar() {
    const sidebar = document.getElementById('automation_sidebar');
    const icon = document.getElementById('sidebar_toggle_icon');
    sidebar.classList.toggle('collapsed');
    icon.classList.toggle('ki-double-left');
    icon.classList.toggle('ki-double-right');
}

function toggleCategory(header) {
    const category = header.closest('.node-category');
    category.classList.toggle('collapsed');
    const icon = header.querySelector('.ki-arrow-down, .ki-arrow-right');
    if (icon) {
        icon.classList.toggle('ki-arrow-down');
        icon.classList.toggle('ki-arrow-right');
    }
}

function filterSidebar(query) {
    const items = document.querySelectorAll('.node-type-item');
    const categories = document.querySelectorAll('.node-category');
    
    query = query.toLowerCase();
    
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(query) ? 'flex' : 'none';
    });
    
    // Mostrar/esconder categorias baseado em itens visíveis
    categories.forEach(cat => {
        const visibleItems = cat.querySelectorAll('.node-type-item:not([style*="none"])');
        cat.style.display = visibleItems.length > 0 ? 'block' : 'none';
    });
}

function toggleMinimap() {
    const minimap = document.getElementById('automation_minimap');
    const isVisible = minimap.style.display !== 'none';
    minimap.style.display = isVisible ? 'none' : 'block';
    if (!isVisible) updateMinimap();
}

function updateMinimap() {
    const minimap = document.getElementById('automation_minimap');
    if (minimap.style.display === 'none') return;
    
    const container = minimap.querySelector('.minimap-content');
    const nodesContainer = document.getElementById('minimap_nodes');
    const viewport = document.getElementById('minimap_viewport');
    
    // Limpar
    nodesContainer.innerHTML = '';
    
    if (nodes.length === 0) return;
    
    // Calcular bounds
    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    nodes.forEach(node => {
        const x = node.position_x || 0;
        const y = node.position_y || 0;
        minX = Math.min(minX, x);
        minY = Math.min(minY, y);
        maxX = Math.max(maxX, x + 240);
        maxY = Math.max(maxY, y + 100);
    });
    
    const padding = 50;
    const contentWidth = maxX - minX + padding * 2;
    const contentHeight = maxY - minY + padding * 2;
    
    const scale = Math.min(200 / contentWidth, 140 / contentHeight) * 0.9;
    
    // Renderizar nós no minimap
    nodes.forEach(node => {
        const dot = document.createElement('div');
        dot.className = 'minimap-node';
        dot.style.left = ((node.position_x - minX + padding) * scale) + 'px';
        dot.style.top = ((node.position_y - minY + padding) * scale) + 'px';
        dot.style.width = (240 * scale) + 'px';
        dot.style.height = (20 * scale) + 'px';
        dot.style.background = '#3b82f6';
        nodesContainer.appendChild(dot);
    });
    
    // Viewport
    const containerRect = canvasContainer.getBoundingClientRect();
    const viewX = (-canvasTranslate.x / canvasScale - minX + padding) * scale;
    const viewY = (-canvasTranslate.y / canvasScale - minY + padding) * scale;
    const viewW = (containerRect.width / canvasScale) * scale;
    const viewH = (containerRect.height / canvasScale) * scale;
    
    viewport.style.left = Math.max(0, viewX) + 'px';
    viewport.style.top = Math.max(0, viewY) + 'px';
    viewport.style.width = Math.min(viewW, 200) + 'px';
    viewport.style.height = Math.min(viewH, 140) + 'px';
}

function showToast(message, type = 'info') {
    const container = document.getElementById('automation_toasts');
    const toast = document.createElement('div');
    toast.className = `automation-toast ${type}`;
    
    const icons = {
        success: 'ki-check-circle',
        error: 'ki-cross-circle',
        warning: 'ki-information',
        info: 'ki-information'
    };
    
    toast.innerHTML = `
        <i class="ki-duotone ${icons[type] || icons.info} fs-4"></i>
        <span>${message}</span>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function updateStatus(text) {
    const statusText = document.getElementById('status_text');
    if (statusText) statusText.textContent = text;
}

// ============================================
// SALVAMENTO E API
// ============================================
function saveLayout() {
    updateStatus('Salvando...');
    
    const payload = {
        nodes: nodes.map(n => ({
            id: n.id,
            node_type: n.node_type,
            position_x: n.position_x,
            position_y: n.position_y,
            node_data: n.node_data
        }))
    };
    
    fetch(`<?= \App\Helpers\Url::to('/automations/' . ($automation['id'] ?? 0) . '/layout') ?>`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            updateStatus('Salvo');
            showToast('Layout salvo!', 'success');
            setTimeout(() => updateStatus('Pronto'), 2000);
        } else {
            updateStatus('Erro ao salvar');
            showToast('Erro ao salvar: ' + (data.message || ''), 'error');
        }
    })
    .catch(err => {
        console.error('Erro:', err);
        updateStatus('Erro');
        showToast('Erro ao salvar', 'error');
    });
}

function refreshLogs() {
    const container = document.getElementById('kt_automation_logs');
    container.innerHTML = `
        <div class="text-center py-10">
            <span class="spinner-border spinner-border-sm text-primary"></span>
            <span class="ms-2">Carregando logs...</span>
        </div>
    `;
    
    fetch(`<?= \App\Helpers\Url::to('/automations/' . ($automation['id'] ?? 0) . '/logs') ?>`)
        .then(r => r.text())
        .then(html => {
            container.innerHTML = html;
        })
        .catch(() => {
            container.innerHTML = `
                <div class="text-center py-10 text-muted">
                    <i class="ki-duotone ki-information fs-2x mb-3"></i>
                    <p>Não foi possível carregar os logs</p>
                </div>
            `;
        });
}

// ============================================
// FUNÇÕES DE TESTE
// ============================================
window.__realTestAutomation = function() {
    showToast('Iniciando teste rápido...', 'info');
    
    fetch(`<?= \App\Helpers\Url::to('/automations/' . ($automation['id'] ?? 0) . '/test') ?>`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Teste concluído com sucesso!', 'success');
        } else {
            showToast('Teste falhou: ' + (data.message || ''), 'error');
        }
        refreshLogs();
    })
    .catch(() => {
        showToast('Erro ao executar teste', 'error');
    });
};

window.__realAdvancedTestAutomation = function() {
    showToast('Teste avançado em desenvolvimento', 'warning');
};

// Trigger type change handler
document.addEventListener('DOMContentLoaded', function() {
    const triggerSelect = document.getElementById('kt_edit_trigger_type');
    if (triggerSelect) {
        triggerSelect.addEventListener('change', function() {
            const timeConfig = document.getElementById('kt_edit_time_config_container');
            const scheduleConfig = document.getElementById('kt_edit_schedule_config_container');
            
            if (timeConfig) {
                timeConfig.style.display = ['no_customer_response', 'no_agent_response'].includes(this.value) ? 'block' : 'none';
            }
            if (scheduleConfig) {
                scheduleConfig.style.display = this.value === 'time_based' ? 'block' : 'none';
            }
        });
    }
});

// Estado inicial para undo
saveState();
</script>

<?php
$content = ob_get_clean();
include $layout;
