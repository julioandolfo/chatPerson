<?php
$layout = 'layouts.metronic.app';
$title = 'Templates de Campanhas';
$pageTitle = 'Templates';
?>

<?php ob_start(); ?>
<div class="app-toolbar py-3 py-lg-6">
    <div class="app-container container-fluid d-flex flex-stack">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                Templates de Campanhas
            </h1>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal_create_template">
                <i class="ki-duotone ki-plus fs-2"></i>
                Novo Template
            </button>
        </div>
    </div>
</div>
<div class="app-container container-fluid">
            
            <!-- Templates Grid -->
            <div class="row g-5">
                
                <!-- Template: Promoção -->
                <div class="col-xl-4">
                    <div class="card card-flush h-100">
                        <div class="card-header pt-7">
                            <div class="card-title">
                                <span class="badge badge-light-primary fs-5">Promoção</span>
                            </div>
                        </div>
                        <div class="card-body pt-5">
                            <h3 class="mb-5">Black Friday</h3>
                            <div class="p-4 bg-light rounded mb-5" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto;">
                                Olá {{nome}}! 🎉<br><br>
                                Nossa BLACK FRIDAY chegou!<br><br>
                                🔥 Até 70% OFF em todos os produtos<br>
                                ⏰ Promoção válida até domingo<br><br>
                                Use o cupom: <strong>BF2026</strong><br><br>
                                Aproveite!
                            </div>
                            <button class="btn btn-sm btn-light-primary w-100" onclick="useTemplate('promocao_bf')">
                                Usar Template
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Template: Follow-up -->
                <div class="col-xl-4">
                    <div class="card card-flush h-100">
                        <div class="card-header pt-7">
                            <div class="card-title">
                                <span class="badge badge-light-success fs-5">Follow-up</span>
                            </div>
                        </div>
                        <div class="card-body pt-5">
                            <h3 class="mb-5">Proposta Enviada</h3>
                            <div class="p-4 bg-light rounded mb-5" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto;">
                                Olá {{nome}},<br><br>
                                Enviei uma proposta personalizada para você sobre {{empresa}}.<br><br>
                                Conseguiu visualizar?<br><br>
                                Fico à disposição para esclarecer qualquer dúvida!<br><br>
                                Att,<br>
                                Equipe
                            </div>
                            <button class="btn btn-sm btn-light-success w-100" onclick="useTemplate('followup_proposta')">
                                Usar Template
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Template: Lançamento -->
                <div class="col-xl-4">
                    <div class="card card-flush h-100">
                        <div class="card-header pt-7">
                            <div class="card-title">
                                <span class="badge badge-light-warning fs-5">Lançamento</span>
                            </div>
                        </div>
                        <div class="card-body pt-5">
                            <h3 class="mb-5">Novo Produto</h3>
                            <div class="p-4 bg-light rounded mb-5" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto;">
                                Oi {{primeiro_nome}}! 👋<br><br>
                                Temos uma novidade INCRÍVEL para você!<br><br>
                                🚀 Acabamos de lançar [PRODUTO]<br><br>
                                E você, como cliente especial, tem acesso antecipado com 30% de desconto!<br><br>
                                Quer conhecer?
                            </div>
                            <button class="btn btn-sm btn-light-warning w-100" onclick="useTemplate('lancamento_produto')">
                                Usar Template
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Template: Reengajamento -->
                <div class="col-xl-4">
                    <div class="card card-flush h-100">
                        <div class="card-header pt-7">
                            <div class="card-title">
                                <span class="badge badge-light-info fs-5">Reengajamento</span>
                            </div>
                        </div>
                        <div class="card-body pt-5">
                            <h3 class="mb-5">Sentimos sua Falta</h3>
                            <div class="p-4 bg-light rounded mb-5" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto;">
                                Olá {{nome}},<br><br>
                                Percebemos que faz um tempo que não conversamos...<br><br>
                                Como agradecimento pela sua preferência, preparamos uma oferta especial só para você!<br><br>
                                Aproveite: [LINK]
                            </div>
                            <button class="btn btn-sm btn-light-info w-100" onclick="useTemplate('reengajamento')">
                                Usar Template
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Template: Pesquisa -->
                <div class="col-xl-4">
                    <div class="card card-flush h-100">
                        <div class="card-header pt-7">
                            <div class="card-title">
                                <span class="badge badge-light-danger fs-5">Pesquisa</span>
                            </div>
                        </div>
                        <div class="card-body pt-5">
                            <h3 class="mb-5">Feedback</h3>
                            <div class="p-4 bg-light rounded mb-5" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto;">
                                Oi {{nome}}!<br><br>
                                Sua opinião é muito importante para nós!<br><br>
                                Poderia nos dar um feedback rápido sobre nosso atendimento?<br><br>
                                São apenas 2 minutos: [LINK_PESQUISA]<br><br>
                                Muito obrigado! 😊
                            </div>
                            <button class="btn btn-sm btn-light-danger w-100" onclick="useTemplate('pesquisa_feedback')">
                                Usar Template
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Template: Evento -->
                <div class="col-xl-4">
                    <div class="card card-flush h-100">
                        <div class="card-header pt-7">
                            <div class="card-title">
                                <span class="badge badge-light-dark fs-5">Evento</span>
                            </div>
                        </div>
                        <div class="card-body pt-5">
                            <h3 class="mb-5">Convite Evento</h3>
                            <div class="p-4 bg-light rounded mb-5" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto;">
                                Olá {{nome}}! 🎊<br><br>
                                Você está convidado(a) para nosso evento exclusivo!<br><br>
                                📅 Data: [DATA]<br>
                                🕐 Horário: [HORA]<br>
                                📍 Local: [LOCAL]<br><br>
                                Confirme sua presença: [LINK]
                            </div>
                            <button class="btn btn-sm btn-light-dark w-100" onclick="useTemplate('convite_evento')">
                                Usar Template
                            </button>
                        </div>
                    </div>
                </div>
                
            </div>
            
    </div>
</div>

<!-- Modal: Criar Template -->
<div class="modal fade" id="modal_create_template" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Criar Novo Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="template_form">
                    <div class="mb-5">
                        <label class="form-label required">Nome do Template</label>
                        <input type="text" class="form-control" name="name" required />
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Categoria</label>
                        <select class="form-select" name="category">
                            <option value="promocao">Promoção</option>
                            <option value="followup">Follow-up</option>
                            <option value="lancamento">Lançamento</option>
                            <option value="reengajamento">Reengajamento</option>
                            <option value="pesquisa">Pesquisa</option>
                            <option value="evento">Evento</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Mensagem</label>
                        <textarea class="form-control" name="message" rows="8" required></textarea>
                        <div class="form-text">Use variáveis: {{nome}}, {{telefone}}, {{email}}, etc</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveTemplate()">Salvar Template</button>
            </div>
        </div>
    </div>
</div>

<script>
function useTemplate(templateId) {
    const templates = {
        'promocao_bf': {
            name: 'Black Friday',
            message: 'Olá {{nome}}! 🎉\n\nNossa BLACK FRIDAY chegou!\n\n🔥 Até 70% OFF em todos os produtos\n⏰ Promoção válida até domingo\n\nUse o cupom: BF2026\n\nAproveite!'
        },
        'followup_proposta': {
            name: 'Follow-up Proposta',
            message: 'Olá {{nome}},\n\nEnviei uma proposta personalizada para você sobre {{empresa}}.\n\nConseguiu visualizar?\n\nFico à disposição para esclarecer qualquer dúvida!\n\nAtt,\nEquipe'
        },
        'lancamento_produto': {
            name: 'Lançamento',
            message: 'Oi {{primeiro_nome}}! 👋\n\nTemos uma novidade INCRÍVEL para você!\n\n🚀 Acabamos de lançar [PRODUTO]\n\nE você, como cliente especial, tem acesso antecipado com 30% de desconto!\n\nQuer conhecer?'
        },
        'reengajamento': {
            name: 'Reengajamento',
            message: 'Olá {{nome}},\n\nPercebemos que faz um tempo que não conversamos...\n\nComo agradecimento pela sua preferência, preparamos uma oferta especial só para você!\n\nAproveite: [LINK]'
        },
        'pesquisa_feedback': {
            name: 'Pesquisa',
            message: 'Oi {{nome}}!\n\nSua opinião é muito importante para nós!\n\nPoderia nos dar um feedback rápido sobre nosso atendimento?\n\nSão apenas 2 minutos: [LINK_PESQUISA]\n\nMuito obrigado! 😊'
        },
        'convite_evento': {
            name: 'Evento',
            message: 'Olá {{nome}}! 🎊\n\nVocê está convidado(a) para nosso evento exclusivo!\n\n📅 Data: [DATA]\n🕐 Horário: [HORA]\n📍 Local: [LOCAL]\n\nConfirme sua presença: [LINK]'
        }
    };
    
    const template = templates[templateId];
    if (template) {
        // Redirecionar para criação com template preenchido
        sessionStorage.setItem('campaign_template', JSON.stringify(template));
        window.location.href = '/campaigns/create';
    }
}

function saveTemplate() {
    const formData = new FormData(document.getElementById('template_form'));
    const data = Object.fromEntries(formData);
    
    // Salvar template customizado
    fetch('/api/campaign-templates', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            toastr.success('Template salvo!');
            bootstrap.Modal.getInstance(document.getElementById('modal_create_template')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            toastr.error(result.message);
        }
    })
    .catch(err => toastr.error('Erro de rede'));
}
</script>

<?php 
$content = ob_get_clean(); 
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
