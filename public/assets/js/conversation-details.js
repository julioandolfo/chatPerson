/**
 * Modal de Detalhes da Conversa
 * Sistema completo para exibir métricas e histórico de conversas
 */

async function showConversationDetails(conversationId) {
    try {
        // Abrir modal
        const modal = new bootstrap.Modal(document.getElementById('kt_modal_conversation_details'));
        modal.show();
        
        // Resetar conteúdo
        const contentDiv = document.getElementById('conversation_details_content');
        contentDiv.innerHTML = `
            <div class="text-center py-10">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
                <p class="text-muted mt-3">Carregando detalhes...</p>
            </div>
        `;
        
        // Buscar detalhes
        const response = await fetch(`${window.KANBAN_CONFIG.BASE_URL}/conversations/${conversationId}/details`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error('Resposta não é JSON: ' + text.substring(0, 200));
        }
        
        const data = await response.json();
        
        if (!data.success || !data.details) {
            throw new Error(data.message || 'Erro ao carregar detalhes');
        }
        
        const details = data.details;
        const conv = details.conversation;
        
        // Atualizar link do botão "Abrir Conversa"
        document.getElementById('btn_open_conversation').href = `${window.KANBAN_CONFIG.BASE_URL}/conversations?id=${conversationId}`;
        
        // Renderizar HTML
        let html = '';
        
        // ========== INFORMAÇÕES BÁSICAS ==========
        html += `
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ki-duotone ki-user fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Informações Básicas
                </h3>
            </div>
            <div class="card-body">
                <div class="row g-5">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-5">
                            <div class="symbol symbol-50px me-4">
                                ${conv.contact_avatar ? 
                                    `<img src="${conv.contact_avatar}" alt="avatar" class="rounded" />` :
                                    `<div class="symbol-label fs-3 fw-bold bg-light-primary text-primary">${conv.contact_name?.charAt(0) || 'C'}</div>`
                                }
                            </div>
                            <div>
                                <div class="fw-bold fs-5 text-gray-900">${conv.contact_name || 'Sem nome'}</div>
                                <div class="text-muted">${conv.contact_phone || ''}</div>
                                ${conv.contact_email ? `<div class="text-muted">${conv.contact_email}</div>` : ''}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <span class="text-muted">Status:</span>
                            <span class="badge badge-light-${conv.status === 'open' ? 'success' : conv.status === 'pending' ? 'warning' : 'secondary'} ms-2">
                                ${conv.status === 'open' ? 'Aberta' : conv.status === 'pending' ? 'Pendente' : conv.status === 'resolved' ? 'Resolvida' : 'Fechada'}
                            </span>
                        </div>
                        <div class="mb-3">
                            <span class="text-muted">Funil:</span>
                            <span class="fw-bold ms-2">${conv.funnel_name || '-'}</span>
                        </div>
                        <div class="mb-3">
                            <span class="text-muted">Etapa Atual:</span>
                            <span class="badge ms-2" style="background-color: ${conv.stage_color || '#009ef7'};">
                                ${conv.stage_name || '-'}
                            </span>
                        </div>
                        ${conv.department_name ? `
                        <div class="mb-3">
                            <span class="text-muted">Departamento:</span>
                            <span class="fw-bold ms-2">${conv.department_name}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                <div class="separator my-5"></div>
                
                <div class="row g-5">
                    <div class="col-md-3">
                        <div class="text-center">
                            <i class="ki-duotone ki-calendar fs-3x text-info mb-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="fw-bold fs-6 text-gray-800">${conv.lifetime_formatted}</div>
                            <div class="text-muted fs-8">Tempo de Vida</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <i class="ki-duotone ki-message-text fs-3x text-primary mb-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div class="fw-bold fs-6 text-gray-800">${details.response_metrics.total_messages}</div>
                            <div class="text-muted fs-8">Total de Mensagens</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <i class="ki-duotone ki-time fs-3x text-warning mb-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="fw-bold fs-6 text-gray-800">${details.response_metrics.avg_response_time_minutes}min</div>
                            <div class="text-muted fs-8">Tempo Médio de Resposta</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <i class="ki-duotone ki-abstract-35 fs-3x text-success mb-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="fw-bold fs-6 text-gray-800">${details.response_metrics.agents_participated.length}</div>
                            <div class="text-muted fs-8">Agentes Participantes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        `;
        
        // ========== AGENTE ATUAL ==========
        if (conv.agent_name) {
            html += `
            <div class="card mb-5">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="ki-duotone ki-user-tick fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Agente Responsável
                    </h3>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-50px me-4">
                            ${conv.agent_avatar ? 
                                `<img src="${conv.agent_avatar}" alt="avatar" class="rounded" />` :
                                `<div class="symbol-label fs-3 fw-bold bg-light-primary text-primary">${conv.agent_name?.charAt(0) || 'A'}</div>`
                            }
                        </div>
                        <div>
                            <div class="fw-bold fs-5 text-gray-900">${conv.agent_name}</div>
                            ${conv.agent_email ? `<div class="text-muted">${conv.agent_email}</div>` : ''}
                        </div>
                    </div>
                </div>
            </div>
            `;
        }
        
        // ========== MÉTRICAS DE RESPOSTA ==========
        html += `
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ki-duotone ki-chart-simple-2 fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Métricas de Resposta
                </h3>
            </div>
            <div class="card-body">
                <div class="row g-5">
                    <div class="col-md-4">
                        <div class="border border-gray-300 border-dashed rounded p-4 text-center">
                            <div class="text-muted mb-2">Mensagens do Cliente</div>
                            <div class="fw-bold fs-2 text-primary">${details.response_metrics.client_messages}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border border-gray-300 border-dashed rounded p-4 text-center">
                            <div class="text-muted mb-2">Mensagens dos Agentes</div>
                            <div class="fw-bold fs-2 text-success">${details.response_metrics.agent_messages}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border border-gray-300 border-dashed rounded p-4 text-center">
                            <div class="text-muted mb-2">Primeira Resposta</div>
                            <div class="fw-bold fs-2 text-warning">${details.response_metrics.first_response_time_minutes || 0}min</div>
                        </div>
                    </div>
                </div>
                
                ${details.response_metrics.agents_participated.length > 0 ? `
                <div class="separator my-5"></div>
                <div>
                    <div class="text-muted mb-3">Agentes que Participaram:</div>
                    <div class="d-flex flex-wrap gap-2">
                        ${details.response_metrics.agents_participated.map(agent => 
                            `<span class="badge badge-light-primary">${agent}</span>`
                        ).join('')}
                    </div>
                </div>
                ` : ''}
            </div>
        </div>
        `;
        
        // ========== TEMPO EM CADA ETAPA ==========
        if (details.time_in_stages && details.time_in_stages.length > 0) {
            html += `
            <div class="card mb-5">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="ki-duotone ki-time fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Tempo em Cada Etapa
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-row-bordered align-middle">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>Etapa</th>
                                    <th class="text-end">Tempo</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${details.time_in_stages.map(stage => `
                                <tr>
                                    <td>
                                        <span class="badge" style="background-color: ${stage.stage_color};">
                                            ${stage.stage_name}
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold">${stage.formatted}</td>
                                </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            `;
        }
        
        // ========== HISTÓRICO DE ETAPAS ==========
        if (details.stage_history && details.stage_history.length > 0) {
            html += `
            <div class="card mb-5">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="ki-duotone ki-arrow-right-left fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Histórico de Movimentações
                    </h3>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        ${details.stage_history.map((history, index) => `
                        <div class="timeline-item">
                            <div class="timeline-line w-40px"></div>
                            <div class="timeline-icon symbol symbol-circle symbol-40px">
                                <div class="symbol-label bg-light">
                                    <i class="ki-duotone ki-abstract-26 fs-2 text-primary">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                            <div class="timeline-content mb-7 mt-n1">
                                <div class="pe-3 mb-2">
                                    <div class="fs-6 fw-bold mb-2">
                                        ${history.from_stage_name ? 
                                            `De <span class="badge" style="background-color: ${history.from_stage_color};">${history.from_stage_name}</span>` : 
                                            'Início'
                                        }
                                        ➜
                                        <span class="badge" style="background-color: ${history.to_stage_color};">
                                            ${history.to_stage_name}
                                        </span>
                                    </div>
                                    <div class="text-muted fs-7">
                                        <i class="ki-duotone ki-user fs-7 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        ${history.changed_by_name || 'Sistema'}
                                        <span class="text-muted mx-2">•</span>
                                        ${new Date(history.changed_at).toLocaleString('pt-BR')}
                                    </div>
                                </div>
                            </div>
                        </div>
                        `).join('')}
                    </div>
                </div>
            </div>
            `;
        }
        
        // ========== HISTÓRICO DE ATRIBUIÇÕES ==========
        if (details.assignment_history && details.assignment_history.length > 0) {
            html += `
            <div class="card mb-5">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="ki-duotone ki-profile-user fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                        </i>
                        Histórico de Atribuições
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-row-bordered align-middle">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>De</th>
                                    <th>Para</th>
                                    <th>Atribuído Por</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${details.assignment_history.map(assignment => `
                                <tr>
                                    <td>${assignment.from_agent_name || 'Não atribuído'}</td>
                                    <td>${assignment.to_agent_name || 'Não atribuído'}</td>
                                    <td>${assignment.assigned_by_name || 'Sistema'}</td>
                                    <td class="text-muted fs-7">${new Date(assignment.assigned_at).toLocaleString('pt-BR')}</td>
                                </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            `;
        }
        
        // ========== TAGS ==========
        if (details.tags && details.tags.length > 0) {
            html += `
            <div class="card mb-5">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="ki-duotone ki-tag fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Tags
                    </h3>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        ${details.tags.map(tag => `
                        <span class="badge fs-6" style="background-color: ${tag.color}20; color: ${tag.color}; border: 1px solid ${tag.color};">
                            ${tag.name}
                        </span>
                        `).join('')}
                    </div>
                </div>
            </div>
            `;
        }
        
        // ========== AVALIAÇÃO ==========
        if (details.rating) {
            const stars = '★'.repeat(details.rating.rating) + '☆'.repeat(5 - details.rating.rating);
            html += `
            <div class="card mb-5">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="ki-duotone ki-star fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Avaliação
                    </h3>
                </div>
                <div class="card-body">
                    <div class="fs-2 text-warning mb-3">${stars}</div>
                    <div class="fw-bold fs-4 mb-2">${details.rating.rating}/5</div>
                    ${details.rating.comment ? `
                    <div class="text-muted mt-3">
                        <div class="fs-7 fw-bold mb-1">Comentário:</div>
                        <div class="bg-light p-4 rounded">${details.rating.comment}</div>
                    </div>
                    ` : ''}
                </div>
            </div>
            `;
        }
        
        // ========== NOTAS ==========
        if (details.notes && details.notes.length > 0) {
            html += `
            <div class="card mb-5">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="ki-duotone ki-notepad fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                            <span class="path5"></span>
                        </i>
                        Notas (${details.notes.length})
                    </h3>
                </div>
                <div class="card-body">
                    ${details.notes.map(note => `
                    <div class="border border-gray-300 border-dashed rounded p-4 mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="fw-bold">${note.author_name || 'Anônimo'}</div>
                            <div class="text-muted fs-7">${new Date(note.created_at).toLocaleString('pt-BR')}</div>
                        </div>
                        <div class="text-gray-700">${note.note}</div>
                    </div>
                    `).join('')}
                </div>
            </div>
            `;
        }
        
        // Atualizar conteúdo do modal
        contentDiv.innerHTML = html;
        
    } catch (error) {
        console.error('Erro ao carregar detalhes:', error);
        
        const contentDiv = document.getElementById('conversation_details_content');
        contentDiv.innerHTML = `
            <div class="alert alert-danger d-flex align-items-center p-5">
                <i class="ki-duotone ki-shield-cross fs-2hx text-danger me-4">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div class="d-flex flex-column">
                    <h4 class="mb-1 text-dark">Erro ao carregar detalhes</h4>
                    <span>${error.message || 'Não foi possível carregar os detalhes da conversa'}</span>
                </div>
            </div>
        `;
    }
}

// Exportar função global
window.showConversationDetails = showConversationDetails;
