/**
 * Kanban - Sistema de Funis e Estágios
 * Drag & Drop, Validações, Auto-atribuição e Métricas
 */

// Variáveis globais (definidas via PHP no HTML)
// window.KANBAN_CONFIG = { funnelId, moveConversationUrl, funnelBaseUrl, funnelsUrl, BASE_URL }

// Toast global (fallback para evitar "toast is not defined")
if (!window.toast && typeof Swal !== 'undefined') {
    window.toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
    });
}

let draggedElement = null;

// ============================================================================
// DRAG & DROP
// ============================================================================

document.addEventListener("DOMContentLoaded", function() {
    // Drag and Drop
    const kanbanItems = document.querySelectorAll(".kanban-item");
    const kanbanColumns = document.querySelectorAll(".kanban-column-body");
    
    kanbanItems.forEach(item => {
        item.addEventListener("dragstart", function(e) {
            draggedElement = this;
            this.classList.add("dragging");
            e.dataTransfer.effectAllowed = "move";
        });
        
        item.addEventListener("dragend", function() {
            this.classList.remove("dragging");
            draggedElement = null;
        });
    });
    
    kanbanColumns.forEach(column => {
        column.addEventListener("dragover", function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = "move";
            this.classList.add("kanban-drop-zone");
        });
        
        column.addEventListener("dragleave", function() {
            this.classList.remove("kanban-drop-zone");
        });
        
        column.addEventListener("drop", function(e) {
            e.preventDefault();
            this.classList.remove("kanban-drop-zone");
            
            if (draggedElement) {
                const columnElement = this.closest(".kanban-column");
                const newStageId = columnElement ? columnElement.dataset.stageId : null;
                const conversationId = draggedElement.dataset.conversationId;
                
                if (newStageId && conversationId) {
                    // Verificar se não está movendo para o mesmo estágio
                    const currentColumn = draggedElement.closest(".kanban-column");
                    const currentStageId = currentColumn ? currentColumn.dataset.stageId : null;
                    
                    if (currentStageId !== newStageId) {
                        // VALIDAÇÃO PRÉVIA: Verificar limite de conversas no estágio
                        const maxConversations = parseInt(columnElement.dataset.maxConversations) || 0;
                        const currentCount = this.querySelectorAll('.conversation-item').length;
                        
                        if (maxConversations > 0 && currentCount >= maxConversations) {
                            // Limite atingido - mostrar erro
                            draggedElement.style.opacity = "1";
                            
                            Swal.fire({
                                icon: "error",
                                title: "Limite Atingido",
                                html: "Este estágio já atingiu o limite máximo de <strong>" + maxConversations + "</strong> conversa(s).<br><br>Remova conversas deste estágio antes de adicionar novas.",
                                confirmButtonText: "OK",
                                customClass: {
                                    confirmButton: "btn btn-danger"
                                }
                            });
                            
                            draggedElement = null;
                            return;
                        }
                        
                        // Limite OK - prosseguir com movimentação
                        moveConversation(conversationId, newStageId);
                    } else {
                        // Restaurar elemento se for o mesmo estágio
                        draggedElement.style.opacity = "1";
                    }
                }
                
                draggedElement = null;
            }
        });
    });
});

// ============================================================================
// MOVIMENTAÇÃO DE CONVERSAS
// ============================================================================

function moveConversation(conversationId, stageId) {
    const formData = new FormData();
    formData.append("conversation_id", conversationId);
    formData.append("stage_id", stageId);
    
    // Encontrar item e colunas
    const item = document.querySelector("[data-conversation-id='" + conversationId + "']");
    const newColumn = document.querySelector(".kanban-column[data-stage-id='" + stageId + "'] .kanban-cards");
    const originalOpacity = item ? item.style.opacity : "1";
    const originalColumn = item ? item.closest('.kanban-cards') : null;
    
    // Mostrar loading no item
    if (item) {
        item.style.opacity = "0.5";
        item.style.pointerEvents = "none";
        item.classList.add('moving');
    }
    
    // Mostrar loading toast
    const toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
    
    toast.fire({
        icon: 'info',
        title: 'Movendo conversa...'
    });
    
    fetch(window.KANBAN_CONFIG.moveConversationUrl, {
        method: "POST",
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Sucesso - atualizar sem recarregar
            if (item && newColumn) {
                // Remover da coluna original
                item.remove();
                
                // Adicionar na nova coluna
                newColumn.appendChild(item);
                
                // Restaurar estilos
                item.style.opacity = "1";
                item.style.pointerEvents = "";
                item.classList.remove('moving');
                
                // Atualizar contadores
                updateStageCounters(originalColumn, newColumn);
                
                // Feedback de sucesso
                toast.fire({
                    icon: 'success',
                    title: data.message || 'Conversa movida com sucesso!'
                });
                
                // Scroll suave até o item
                item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                
                // Destacar item movido
                item.classList.add('just-moved');
                setTimeout(() => {
                    item.classList.remove('just-moved');
                }, 2000);
            } else {
                // Fallback: recarregar se não encontrou elementos
                location.reload();
            }
        } else {
            // Erro - restaurar item e mostrar mensagem
            if (item) {
                item.style.opacity = originalOpacity;
                item.style.pointerEvents = "";
                item.classList.remove('moving');
            }
            
            Swal.fire({
                icon: 'error',
                title: 'Erro ao mover conversa',
                html: data.message || 'Não foi possível mover a conversa. Verifique as permissões.',
                confirmButtonText: 'OK'
            });
        }
    })
    .catch(error => {
        console.error('Erro ao mover conversa:', error);
        
        // Erro de rede - restaurar item
        if (item) {
            item.style.opacity = originalOpacity;
            item.style.pointerEvents = "";
            item.classList.remove('moving');
        }
        
        Swal.fire({
            icon: 'error',
            title: 'Erro de Conexão',
            text: 'Erro ao mover conversa. Verifique sua conexão e tente novamente.',
            confirmButtonText: 'OK'
        });
    });
}

// Atualizar contadores de conversas nas colunas
function updateStageCounters(oldColumn, newColumn) {
    if (oldColumn) {
        const oldCard = oldColumn.closest('.card');
        const oldBadge = oldCard.querySelector('.badge');
        if (oldBadge) {
            const oldCount = parseInt(oldBadge.textContent) || 0;
            oldBadge.textContent = Math.max(0, oldCount - 1);
        }
    }
    
    if (newColumn) {
        const newCard = newColumn.closest('.card');
        const newBadge = newCard.querySelector('.badge');
        if (newBadge) {
            const newCount = parseInt(newBadge.textContent) || 0;
            newBadge.textContent = newCount + 1;
        }
    }
}

// ============================================================================
// NAVEGAÇÃO
// ============================================================================

function changeFunnel(funnelId) {
    window.location.href = window.KANBAN_CONFIG.funnelsUrl + "/" + funnelId + "/kanban";
}

// ============================================================================
// EDIÇÃO DE ESTÁGIOS
// ============================================================================

function editStage(stageId, name, description, color) {
    // Carregar dados completos do estágio via AJAX
    fetch(window.KANBAN_CONFIG.funnelBaseUrl + "/stages/" + stageId + "/json")
        .then(response => response.json())
        .then(data => {
            if (data.success && data.stage) {
                const stage = data.stage;
                
                document.getElementById("kt_modal_stage_title").textContent = "Editar Estágio";
                document.getElementById("kt_stage_id").value = stageId;
                document.getElementById("kt_stage_name").value = stage.name || "";
                document.getElementById("kt_stage_description").value = stage.description || "";
                document.getElementById("kt_stage_color").value = stage.color || "#009ef7";
                document.getElementById("kt_stage_default").checked = stage.is_default == 1;
                document.getElementById("kt_stage_max_conversations").value = stage.max_conversations || "";
                document.getElementById("kt_stage_sla_hours").value = stage.sla_hours || "";
                document.getElementById("kt_stage_allow_move_back").checked = stage.allow_move_back !== false;
                document.getElementById("kt_stage_allow_skip_stages").checked = stage.allow_skip_stages == 1;
                document.getElementById("kt_stage_auto_assign").checked = stage.auto_assign == 1;
                document.getElementById("kt_stage_auto_assign_department").value = stage.auto_assign_department_id || "";
                document.getElementById("kt_stage_auto_assign_method").value = stage.auto_assign_method || "round-robin";
                
                // Preencher arrays (blocked_stages, required_stages, required_tags, blocked_tags)
                if (stage.blocked_stages) {
                    const blockedStages = typeof stage.blocked_stages === "string" ? JSON.parse(stage.blocked_stages) : stage.blocked_stages;
                    if (blockedStages && blockedStages.length > 0) {
                        $("#kt_stage_blocked_stages").val(blockedStages).trigger("change");
                    }
                }
                if (stage.required_stages) {
                    const requiredStages = typeof stage.required_stages === "string" ? JSON.parse(stage.required_stages) : stage.required_stages;
                    if (requiredStages && requiredStages.length > 0) {
                        $("#kt_stage_required_stages").val(requiredStages).trigger("change");
                    }
                }
                if (stage.required_tags) {
                    const requiredTags = typeof stage.required_tags === "string" ? JSON.parse(stage.required_tags) : stage.required_tags;
                    if (requiredTags && requiredTags.length > 0) {
                        $("#kt_stage_required_tags").val(requiredTags).trigger("change");
                    }
                }
                if (stage.blocked_tags) {
                    const blockedTags = typeof stage.blocked_tags === "string" ? JSON.parse(stage.blocked_tags) : stage.blocked_tags;
                    if (blockedTags && blockedTags.length > 0) {
                        $("#kt_stage_blocked_tags").val(blockedTags).trigger("change");
                    }
                }
                
                // Mostrar/ocultar campos de auto-atribuição
                toggleAutoAssignFields();
                
                const modal = new bootstrap.Modal(document.getElementById("kt_modal_stage"));
                modal.show();
            } else {
                // Fallback para dados básicos se não houver endpoint JSON
                document.getElementById("kt_modal_stage_title").textContent = "Editar Estágio";
                document.getElementById("kt_stage_id").value = stageId;
                document.getElementById("kt_stage_name").value = name;
                document.getElementById("kt_stage_description").value = description || "";
                document.getElementById("kt_stage_color").value = color || "#009ef7";
                document.getElementById("kt_stage_default").checked = false;
                
                const modal = new bootstrap.Modal(document.getElementById("kt_modal_stage"));
                modal.show();
            }
        })
        .catch(error => {
            console.error("Erro ao carregar dados do estágio:", error);
            // Fallback
            document.getElementById("kt_modal_stage_title").textContent = "Editar Estágio";
            document.getElementById("kt_stage_id").value = stageId;
            document.getElementById("kt_stage_name").value = name;
            document.getElementById("kt_stage_description").value = description || "";
            document.getElementById("kt_stage_color").value = color || "#009ef7";
            document.getElementById("kt_stage_default").checked = false;
            
            const modal = new bootstrap.Modal(document.getElementById("kt_modal_stage"));
            modal.show();
        });
}

function toggleAutoAssignFields() {
    const autoAssign = document.getElementById("kt_stage_auto_assign");
    const fields = document.getElementById("kt_auto_assign_fields");
    const methodField = document.getElementById("kt_auto_assign_method_field");
    
    if (autoAssign && fields && methodField) {
        if (autoAssign.checked) {
            fields.style.display = "block";
            methodField.style.display = "block";
        } else {
            fields.style.display = "none";
            methodField.style.display = "none";
        }
    }
}

function deleteStage(stageId, stageName) {
    // Primeiro tenta deletar para ver se há conversas
    fetch(window.KANBAN_CONFIG.funnelBaseUrl + "/stages/" + stageId, {
        method: "DELETE",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-Requested-With": "XMLHttpRequest"
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else if (data.requires_transfer) {
            // Tem conversas - perguntar para onde transferir
            showTransferConversationsModal(stageId, stageName, data.conversation_count);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: data.message || "Erro ao deletar estágio"
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao deletar estágio'
        });
    });
}

function showTransferConversationsModal(stageId, stageName, conversationCount) {
    // Buscar outras etapas do funil
    const columns = document.querySelectorAll('.kanban-column');
    let stageOptions = '';
    
    columns.forEach(column => {
        const colStageId = column.dataset.stageId;
        const colStageName = column.querySelector('.fw-bold')?.textContent || '';
        
        if (colStageId != stageId) {
            stageOptions += '<option value="' + colStageId + '">' + colStageName + '</option>';
        }
    });
    
    Swal.fire({
        title: 'Transferir Conversas',
        html: 
            '<p class="mb-5">Este estágio possui <strong>' + conversationCount + ' conversa(s)</strong>.</p>' +
            '<p class="mb-3">Para qual estágio deseja transferir antes de deletar?</p>' +
            '<select id="swal-target-stage" class="form-select form-select-solid">' +
                '<option value="">Selecione um estágio...</option>' +
                stageOptions +
            '</select>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Transferir e Deletar',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-secondary'
        },
        preConfirm: () => {
            const targetStageId = document.getElementById('swal-target-stage').value;
            if (!targetStageId) {
                Swal.showValidationMessage('Selecione um estágio de destino');
                return false;
            }
            return targetStageId;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const targetStageId = result.value;
            
            // Deletar com transferência
            fetch(window.KANBAN_CONFIG.funnelBaseUrl + "/stages/" + stageId, {
                method: "DELETE",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new URLSearchParams({
                    target_stage_id: targetStageId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: data.message,
                        timer: 2000
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: data.message || "Erro ao deletar estágio"
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Erro ao deletar estágio'
                });
            });
        }
    });
}

/**
 * Editar apenas a cor de uma etapa do sistema
 */
function editStageColorOnly(stageId, name, currentColor) {
    Swal.fire({
        title: 'Editar Cor da Etapa',
        html: `
            <div class="text-start mb-5">
                <p class="text-muted"><strong>${name}</strong> é uma etapa obrigatória do sistema.</p>
                <p class="text-muted fs-7">Apenas a cor pode ser alterada. Nome e descrição são fixos.</p>
            </div>
            <div class="fv-row">
                <label class="fw-semibold fs-6 mb-2">Cor</label>
                <input type="color" id="swal-stage-color" class="form-control form-control-solid" value="${currentColor}" />
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Salvar Cor',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-secondary'
        },
        preConfirm: () => {
            const color = document.getElementById('swal-stage-color').value;
            if (!color) {
                Swal.showValidationMessage('Por favor, selecione uma cor');
                return false;
            }
            return { color: color };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('color', result.value.color);
            
            fetch(window.KANBAN_CONFIG.funnelBaseUrl + "/stages/" + stageId, {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: 'Cor atualizada com sucesso!',
                        timer: 2000
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: data.message || 'Erro ao atualizar cor'
                    });
                }
            })
            .catch(error => {
                console.error('Erro ao atualizar cor:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Erro ao atualizar cor da etapa'
                });
            });
        }
    });
}

// ============================================================================
// FORMULÁRIO DE ESTÁGIO
// ============================================================================

document.addEventListener("DOMContentLoaded", function() {
    const stageForm = document.getElementById("kt_modal_stage_form");
    if (stageForm) {
        stageForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const stageId = document.getElementById("kt_stage_id").value;
            const isEdit = stageId !== "";
            const url = isEdit 
                ? window.KANBAN_CONFIG.funnelBaseUrl + "/stages/" + stageId
                : window.KANBAN_CONFIG.funnelBaseUrl + "/stages";
            const method = "POST";
            
            const submitBtn = document.getElementById("kt_modal_stage_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(stageForm);
            if (!document.getElementById("kt_stage_default").checked) {
                formData.delete("is_default");
            }
            
            // Limpar campos numéricos vazios (para não causar erro de validação)
            const numericFields = ['max_conversations', 'auto_assign_department_id', 'sla_hours'];
            numericFields.forEach(field => {
                const value = formData.get(field);
                if (value === '' || value === null) {
                    formData.delete(field);
                }
            });
            
            // Processar arrays JSON
            const blockedStagesEl = document.getElementById("kt_stage_blocked_stages");
            const requiredStagesEl = document.getElementById("kt_stage_required_stages");
            const requiredTagsEl = document.getElementById("kt_stage_required_tags");
            const blockedTagsEl = document.getElementById("kt_stage_blocked_tags");
            
            if (blockedStagesEl) {
                const blockedStages = Array.from(blockedStagesEl.selectedOptions).map(opt => parseInt(opt.value));
                formData.set("blocked_stages", JSON.stringify(blockedStages));
            }
            if (requiredStagesEl) {
                const requiredStages = Array.from(requiredStagesEl.selectedOptions).map(opt => parseInt(opt.value));
                formData.set("required_stages", JSON.stringify(requiredStages));
            }
            if (requiredTagsEl) {
                const requiredTags = Array.from(requiredTagsEl.selectedOptions).map(opt => parseInt(opt.value));
                formData.set("required_tags", JSON.stringify(requiredTags));
            }
            if (blockedTagsEl) {
                const blockedTags = Array.from(blockedTagsEl.selectedOptions).map(opt => parseInt(opt.value));
                formData.set("blocked_tags", JSON.stringify(blockedTags));
            }
            
            // Processar checkboxes
            const allowMoveBack = document.getElementById("kt_stage_allow_move_back");
            if (allowMoveBack && !allowMoveBack.checked) {
                formData.set("allow_move_back", "0");
            }
            const allowSkipStages = document.getElementById("kt_stage_allow_skip_stages");
            if (allowSkipStages && !allowSkipStages.checked) {
                formData.delete("allow_skip_stages");
            }
            const autoAssign = document.getElementById("kt_stage_auto_assign");
            if (autoAssign && !autoAssign.checked) {
                formData.delete("auto_assign");
            }
            
            // DEBUG: Log dos dados sendo enviados
            console.log("=== SALVANDO ESTÁGIO ===");
            console.log("URL:", url);
            console.log("Método:", method);
            console.log("FormData entries:");
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            
            fetch(url, {
                method: method,
                body: formData
            })
            .then(async (response) => {
                console.log("Response status:", response.status);
                console.log("Response ok:", response.ok);
                
                const contentType = response.headers.get("content-type");
                console.log("Content-Type:", contentType);

                // Se não for JSON, tentar ler o corpo como texto e exibir para debug
                if (!contentType || !contentType.includes("application/json")) {
                    const html = await response.text();
                    console.error("❌ Resposta não JSON recebida (HTML/text):");
                    console.error(html);

                    const snippet = html.slice(0, 1200); // mostra no modal
                    throw new Error(
                        "Resposta não é JSON. Status: " + response.status +
                        " | Content-Type: " + contentType +
                        "\n\nPrévia da resposta:\n" + snippet
                    );
                }
                
                // OK, é JSON
                return response.json();
            })
            .then(data => {
                console.log("Response data:", data);
                
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    console.log("✅ Estágio salvo com sucesso!");
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_stage"));
                    modal.hide();
                    location.reload();
                } else {
                    console.error("❌ Erro ao salvar:", data.message);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro ao salvar',
                        text: data.message || "Erro ao salvar estágio",
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error("❌ Erro catch:", error);
                console.error("Erro stack:", error.stack);
                
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                Swal.fire({
                    icon: 'error',
                    title: 'Erro ao salvar estágio',
                    html: '<pre style="text-align:left; white-space:pre-wrap; max-height:300px; overflow:auto;">' + (error.message || '') + '</pre>',
                    confirmButtonText: 'OK'
                });
            });
        });
        
        // Listener para checkbox de auto-atribuição
        const autoAssignCheckbox = document.getElementById("kt_stage_auto_assign");
        if (autoAssignCheckbox) {
            autoAssignCheckbox.addEventListener("change", toggleAutoAssignFields);
        }
        
        // Resetar formulário ao fechar modal
        document.getElementById("kt_modal_stage").addEventListener("hidden.bs.modal", function() {
            stageForm.reset();
            document.getElementById("kt_modal_stage_title").textContent = "Novo Estágio";
            document.getElementById("kt_stage_id").value = "";
            document.getElementById("kt_stage_color").value = "#009ef7";
        });
    }
    
    // Abrir modal de novo estágio quando clicar no botão
    const newStageBtn = document.querySelector("[data-bs-target='#kt_modal_new_stage']");
    if (newStageBtn) {
        newStageBtn.addEventListener("click", function() {
            document.getElementById("kt_modal_stage_title").textContent = "Novo Estágio";
            document.getElementById("kt_stage_id").value = "";
            document.getElementById("kt_stage_name").value = "";
            document.getElementById("kt_stage_description").value = "";
            document.getElementById("kt_stage_color").value = "#009ef7";
            document.getElementById("kt_stage_default").checked = false;
        });
    }
});

// ============================================================================
// MÉTRICAS
// ============================================================================

// Métricas de estágio
function showStageMetrics(stageId, stageName) {
    const dateFrom = new Date();
    dateFrom.setDate(dateFrom.getDate() - 30);
    const dateTo = new Date();
    
    fetch(window.KANBAN_CONFIG.funnelBaseUrl + "/stages/metrics?stage_id=" + stageId + "&date_from=" + dateFrom.toISOString().split("T")[0] + "&date_to=" + dateTo.toISOString().split("T")[0])
        .then(response => response.json())
        .then(data => {
            if (data.success && data.metrics) {
                const m = data.metrics;
                let html = '<div class="mb-5"><h3 class="fw-bold mb-3">' + stageName + '</h3><div class="row g-4">';
                
                // Card 1: Conversas Atuais
                html += '<div class="col-md-6"><div class="card card-flush"><div class="card-body">';
                html += '<div class="d-flex align-items-center">';
                html += '<i class="ki-duotone ki-chat-text fs-2x text-primary me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>';
                html += '<div><div class="text-gray-500 fs-7">Conversas Atuais</div>';
                html += '<div class="fw-bold fs-3">' + m.current_count + '</div>';
                if (m.max_conversations) {
                    html += '<div class="text-muted fs-8">de ' + m.max_conversations + ' máximo</div>';
                }
                html += '</div></div>';
                
                // Progress bar (utilização)
                if (m.utilization_rate !== null) {
                    const progressColor = m.utilization_rate > 90 ? 'bg-danger' : (m.utilization_rate > 70 ? 'bg-warning' : 'bg-success');
                    html += '<div class="mt-3">';
                    html += '<div class="progress" style="height: 8px;">';
                    html += '<div class="progress-bar ' + progressColor + '" style="width: ' + m.utilization_rate + '%"></div>';
                    html += '</div>';
                    html += '<div class="text-muted fs-8 mt-1">' + m.utilization_rate.toFixed(1) + '% de utilização</div>';
                    html += '</div>';
                }
                html += '</div></div></div>';
                
                // Card 2: Taxa de Conversão
                html += '<div class="col-md-6"><div class="card card-flush"><div class="card-body">';
                html += '<div class="d-flex align-items-center">';
                html += '<i class="ki-duotone ki-chart-simple fs-2x text-info me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>';
                html += '<div><div class="text-gray-500 fs-7">Taxa de Conversão</div>';
                html += '<div class="fw-bold fs-3">' + m.conversion_rate + '%</div>';
                html += '<div class="text-muted fs-8">Últimos 30 dias</div>';
                html += '</div></div></div></div></div>';
                
                // Card 3: Tempo Médio
                html += '<div class="col-md-6"><div class="card card-flush"><div class="card-body">';
                html += '<div class="d-flex align-items-center">';
                html += '<i class="ki-duotone ki-clock fs-2x text-warning me-3"><span class="path1"></span><span class="path2"></span></i>';
                html += '<div><div class="text-gray-500 fs-7">Tempo Médio</div>';
                html += '<div class="fw-bold fs-3">' + m.avg_time_hours + 'h</div>';
                html += '<div class="text-muted fs-8">' + m.min_time_hours + 'h - ' + m.max_time_hours + 'h</div>';
                html += '</div></div></div></div></div>';
                
                // Card 4: Resolvidas
                html += '<div class="col-md-6"><div class="card card-flush"><div class="card-body">';
                html += '<div class="d-flex align-items-center">';
                html += '<i class="ki-duotone ki-check-circle fs-2x text-success me-3"><span class="path1"></span><span class="path2"></span></i>';
                html += '<div><div class="text-gray-500 fs-7">Resolvidas</div>';
                html += '<div class="fw-bold fs-3">' + m.resolved + '</div>';
                html += '<div class="text-muted fs-8">de ' + m.total_in_period + ' no período</div>';
                html += '</div></div></div></div></div>';
                
                // Card 5: Compliance SLA (se disponível)
                if (m.sla_compliance !== null) {
                    const slaClass = m.sla_compliance >= 90 ? 'text-success' : (m.sla_compliance >= 70 ? 'text-warning' : 'text-danger');
                    html += '<div class="col-md-6"><div class="card card-flush"><div class="card-body">';
                    html += '<div class="d-flex align-items-center">';
                    html += '<i class="ki-duotone ki-shield-check fs-2x text-success me-3"><span class="path1"></span><span class="path2"></span></i>';
                    html += '<div><div class="text-gray-500 fs-7">Compliance SLA</div>';
                    html += '<div class="fw-bold fs-3 ' + slaClass + '">' + m.sla_compliance + '%</div>';
                    html += '<div class="text-muted fs-8">SLA: ' + m.sla_hours + 'h</div>';
                    html += '</div></div></div></div></div>';
                }
                
                html += '</div></div>';
                
                Swal.fire({
                    html: html,
                    width: '1200px',
                    showConfirmButton: true,
                    confirmButtonText: 'Fechar',
                    customClass: {
                        popup: 'text-start'
                    },
                    heightAuto: false,
                    didOpen: (el) => {
                        // Ajustar altura e scroll interno
                        el.style.maxHeight = '85vh';
                        el.style.height = '85vh';
                        const htmlContainer = el.querySelector('.swal2-html-container');
                        if (htmlContainer) {
                            htmlContainer.style.maxHeight = '72vh';
                            htmlContainer.style.overflow = 'auto';
                        }
                    }
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Não foi possível carregar as métricas'
                });
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Erro ao carregar métricas'
            });
        });
}

// Métricas do funil completo
function showFunnelMetrics(funnelId) {
    const dateFrom = new Date();
    dateFrom.setDate(dateFrom.getDate() - 30);
    const dateTo = new Date();
    
    fetch(window.KANBAN_CONFIG.funnelsUrl + '/' + funnelId + '/metrics?date_from=' + dateFrom.toISOString().split('T')[0] + '&date_to=' + dateTo.toISOString().split('T')[0])
        .then(response => response.json())
        .then(data => {
            if (data.success && data.metrics) {
                const m = data.metrics;
                let html = '<div class="mb-5"><h3 class="fw-bold mb-3">' + m.funnel_name + '</h3>';
                html += '<div class="row g-4 mb-5">';
                
                // Totais
                html += '<div class="col-md-3"><div class="card card-flush"><div class="card-body text-center">';
                html += '<div class="text-gray-500 fs-7 mb-2">Total de Conversas</div>';
                html += '<div class="fw-bold fs-2x">' + m.totals.total_conversations + '</div>';
                html += '</div></div></div>';
                
                html += '<div class="col-md-3"><div class="card card-flush"><div class="card-body text-center">';
                html += '<div class="text-gray-500 fs-7 mb-2">Abertas</div>';
                html += '<div class="fw-bold fs-2x text-primary">' + m.totals.open_conversations + '</div>';
                html += '</div></div></div>';
                
                html += '<div class="col-md-3"><div class="card card-flush"><div class="card-body text-center">';
                html += '<div class="text-gray-500 fs-7 mb-2">Resolvidas</div>';
                html += '<div class="fw-bold fs-2x text-success">' + m.totals.resolved_conversations + '</div>';
                html += '</div></div></div>';
                
                html += '<div class="col-md-3"><div class="card card-flush"><div class="card-body text-center">';
                html += '<div class="text-gray-500 fs-7 mb-2">Taxa de Resolução</div>';
                html += '<div class="fw-bold fs-2x text-info">' + m.totals.resolution_rate + '%</div>';
                html += '</div></div></div>';
                
                html += '</div>';
                
                // Tabela de estágios
                html += '<h4 class="fw-bold mb-3">Métricas por Estágio</h4>';
                html += '<div class="table-responsive"><table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">';
                html += '<thead><tr class="fw-bold text-muted">';
                html += '<th>Estágio</th><th>Atual</th><th>Total (30d)</th><th>Tempo Médio</th><th>Taxa Conversão</th><th>Compliance SLA</th>';
                html += '</tr></thead><tbody>';
                
                m.stages.forEach(stage => {
                    html += '<tr>';
                    html += '<td><span class="fw-bold">' + stage.stage_name + '</span></td>';
                    html += '<td><span class="badge badge-light-primary">' + stage.current_count + '</span></td>';
                    html += '<td>' + stage.total_in_period + '</td>';
                    html += '<td>' + stage.avg_time_hours + 'h</td>';
                    html += '<td>' + stage.conversion_rate + '%</td>';
                    
                    if (stage.sla_compliance !== null) {
                        const badgeClass = stage.sla_compliance >= 90 ? 'badge-light-success' : (stage.sla_compliance >= 70 ? 'badge-light-warning' : 'badge-light-danger');
                        html += '<td><span class="badge ' + badgeClass + '">' + stage.sla_compliance + '%</span></td>';
                    } else {
                        html += '<td>-</td>';
                    }
                    html += '</tr>';
                });
                
                html += '</tbody></table></div></div>';
                
                Swal.fire({
                    html: html,
                    width: '1400px',
                    showConfirmButton: true,
                    confirmButtonText: 'Fechar',
                    customClass: {
                        popup: 'text-start'
                    },
                    heightAuto: false,
                    didOpen: (el) => {
                        // Ajustar altura e scroll interno
                        el.style.maxHeight = '85vh';
                        el.style.height = '85vh';
                        const htmlContainer = el.querySelector('.swal2-html-container');
                        if (htmlContainer) {
                            htmlContainer.style.maxHeight = '72vh';
                            htmlContainer.style.overflow = 'auto';
                        }
                    }
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Não foi possível carregar as métricas'
                });
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Erro ao carregar métricas'
            });
        });
}

// ============================================================================
// AÇÕES RÁPIDAS DOS CARDS
// ============================================================================

/**
 * Atribuir agente rapidamente
 */
function quickAssignAgent(conversationId) {
    // Carregar lista de agentes disponíveis
    fetch(window.KANBAN_CONFIG.BASE_URL + '/agents', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success || !data.agents) {
            throw new Error('Erro ao carregar agentes');
        }
        
        const agents = data.agents;
        const agentOptions = agents.map(a => 
            `<option value="${a.id}">${a.name}${a.email ? ' - ' + a.email : ''}</option>`
        ).join('');
        
        Swal.fire({
            title: 'Atribuir Agente',
            html: `
                <select id="swal-agent-select" class="form-select">
                    <option value="">Selecione um agente...</option>
                    ${agentOptions}
                </select>
            `,
            showCancelButton: true,
            confirmButtonText: 'Atribuir',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const agentId = document.getElementById('swal-agent-select').value;
                if (!agentId) {
                    Swal.showValidationMessage('Selecione um agente');
                    return false;
                }
                return agentId;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const agentId = result.value;
                
                const formData = new FormData();
                formData.append('agent_id', agentId);
                
                fetch(window.KANBAN_CONFIG.BASE_URL + '/conversations/' + conversationId + '/assign', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (window.toast) {
                            window.toast.fire({
                                icon: 'success',
                                title: 'Agente atribuído com sucesso!'
                            });
                        }
                        
                        // Recarregar página para atualizar card
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        throw new Error(data.message || 'Erro ao atribuir agente');
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: error.message
                    });
                });
            }
        });
    })
    .catch(error => {
        console.error('Erro ao carregar agentes:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao carregar agentes: ' + error.message
        });
    });
}

/**
 * Resolver conversa rapidamente
 */
function quickResolve(conversationId) {
    Swal.fire({
        title: 'Resolver Conversa',
        text: 'Deseja realmente marcar esta conversa como resolvida?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, resolver',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#50cd89'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(window.KANBAN_CONFIG.BASE_URL + '/conversations/' + conversationId + '/close', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (window.toast) {
                        window.toast.fire({
                            icon: 'success',
                            title: 'Conversa resolvida!'
                        });
                    }
                    
                    // Remover card do DOM com animação
                    const card = document.querySelector(`[data-conversation-id="${conversationId}"]`);
                    if (card) {
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.8)';
                        setTimeout(() => {
                            card.remove();
                            
                            // Atualizar contador da coluna
                            const column = card.closest('.kanban-column');
                            if (column) {
                                const stageId = column.dataset.stageId;
                                const badge = column.querySelector(`#stage_count_${stageId}`);
                                if (badge) {
                                    const currentCount = parseInt(badge.textContent) || 0;
                                    badge.textContent = Math.max(0, currentCount - 1);
                                }
                            }
                        }, 300);
                    }
                } else {
                    throw new Error(data.message || 'Erro ao resolver conversa');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: error.message
                });
            });
        }
    });
}

// ============================================================================
// REORDENAR ETAPA
// ============================================================================

async function reorderStage(stageId, direction) {
    try {
        const baseUrl = window.KANBAN_CONFIG?.BASE_URL || window.location.origin;
        const response = await fetch(`${baseUrl}/funnels/stages/${stageId}/reorder`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ direction })
        });

        const result = await response.json();

        if (result.success) {
            toast.fire({
                icon: 'success',
                title: 'Ordem atualizada!',
                text: 'A etapa foi movida com sucesso.'
            });
            
            // Recarregar página para atualizar ordem
            setTimeout(() => location.reload(), 500);
        } else {
            throw new Error(result.message || 'Erro ao reordenar etapa');
        }
    } catch (error) {
        console.error('Erro ao reordenar etapa:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message || 'Não foi possível reordenar a etapa'
        });
    }
}

// ============================================================================
// EXPORTAR FUNÇÕES GLOBAIS
// ============================================================================

window.moveConversation = moveConversation;
window.changeFunnel = changeFunnel;
window.editStage = editStage;
window.editStageColorOnly = editStageColorOnly;
window.deleteStage = deleteStage;
window.toggleAutoAssignFields = toggleAutoAssignFields;
window.showStageMetrics = showStageMetrics;
window.showFunnelMetrics = showFunnelMetrics;
window.quickAssignAgent = quickAssignAgent;
window.quickResolve = quickResolve;
window.reorderStage = reorderStage;

