<?php
$layout = 'layouts.metronic.app';
$title = 'Início Rápido - Campanhas';
$pageTitle = 'Início Rápido';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <div class="row">
                <div class="col-xl-8 offset-xl-2">
                    
                    <!-- Header -->
                    <div class="text-center mb-10">
                        <h1 class="mb-3">Bem-vindo às Campanhas WhatsApp!</h1>
                        <div class="text-gray-600 fs-5">
                            Siga este guia rápido de 3 passos para criar sua primeira campanha
                        </div>
                    </div>
                    
                    <!-- Passo 1 -->
                    <div class="card mb-5">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-5">
                                <div class="symbol symbol-circle symbol-60px me-5" style="background: linear-gradient(135deg, #009EF7 0%, #0077B5 100%);">
                                    <span class="text-white fw-bold fs-1">1</span>
                                </div>
                                <div class="flex-grow-1">
                                    <h2 class="mb-0">Crie uma Lista de Contatos</h2>
                                    <div class="text-muted">Organize seus contatos em listas para envio</div>
                                </div>
                            </div>
                            
                            <div class="p-5 bg-light rounded mb-5">
                                <h4 class="mb-3">Como fazer:</h4>
                                <ol class="mb-0">
                                    <li class="mb-2">Acesse <strong>Listas de Contatos</strong></li>
                                    <li class="mb-2">Clique em <strong>Nova Lista</strong></li>
                                    <li class="mb-2">Dê um nome (ex: "Clientes VIP")</li>
                                    <li>Adicione contatos ou <strong>importe via CSV</strong></li>
                                </ol>
                            </div>
                            
                            <a href="/contact-lists/create" class="btn btn-primary">
                                <i class="ki-duotone ki-plus fs-3"></i>
                                Criar Minha Primeira Lista
                            </a>
                        </div>
                    </div>
                    
                    <!-- Passo 2 -->
                    <div class="card mb-5">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-5">
                                <div class="symbol symbol-circle symbol-60px me-5" style="background: linear-gradient(135deg, #50CD89 0%, #28A745 100%);">
                                    <span class="text-white fw-bold fs-1">2</span>
                                </div>
                                <div class="flex-grow-1">
                                    <h2 class="mb-0">Configure suas Contas WhatsApp</h2>
                                    <div class="text-muted">Adicione múltiplas contas para rotação</div>
                                </div>
                            </div>
                            
                            <div class="p-5 bg-light rounded mb-5">
                                <h4 class="mb-3">Recomendação:</h4>
                                <ul class="mb-0">
                                    <li class="mb-2"><strong>Mínimo:</strong> 2 contas WhatsApp</li>
                                    <li class="mb-2"><strong>Ideal:</strong> 3-5 contas</li>
                                    <li>Quanto mais contas, melhor a distribuição e menor risco de bloqueio</li>
                                </ul>
                            </div>
                            
                            <a href="/integrations" class="btn btn-success">
                                <i class="ki-duotone ki-whatsapp fs-3"></i>
                                Configurar Contas WhatsApp
                            </a>
                        </div>
                    </div>
                    
                    <!-- Passo 3 -->
                    <div class="card mb-5">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-5">
                                <div class="symbol symbol-circle symbol-60px me-5" style="background: linear-gradient(135deg, #FFC700 0%, #F59E0B 100%);">
                                    <span class="text-white fw-bold fs-1">3</span>
                                </div>
                                <div class="flex-grow-1">
                                    <h2 class="mb-0">Crie sua Primeira Campanha</h2>
                                    <div class="text-muted">Configure e dispare suas mensagens</div>
                                </div>
                            </div>
                            
                            <div class="p-5 bg-light rounded mb-5">
                                <h4 class="mb-3">Escolha o tipo:</h4>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="card h-100 border border-primary">
                                            <div class="card-body text-center">
                                                <i class="ki-duotone ki-send fs-3x text-primary mb-3"></i>
                                                <h5>Campanha Normal</h5>
                                                <p class="text-muted fs-7 mb-3">Envio simples em massa</p>
                                                <a href="/campaigns/create" class="btn btn-sm btn-primary">Criar</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card h-100 border border-success">
                                            <div class="card-body text-center">
                                                <i class="ki-duotone ki-chart-line-up-2 fs-3x text-success mb-3"></i>
                                                <h5>Teste A/B</h5>
                                                <p class="text-muted fs-7 mb-3">Teste 2+ versões</p>
                                                <a href="/campaigns/ab-test" class="btn btn-sm btn-success">Criar</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card h-100 border border-warning">
                                            <div class="card-body text-center">
                                                <i class="ki-duotone ki-abstract-26 fs-3x text-warning mb-3"></i>
                                                <h5>Sequência Drip</h5>
                                                <p class="text-muted fs-7 mb-3">Múltiplas etapas</p>
                                                <a href="/drip-sequences/create" class="btn btn-sm btn-warning">Criar</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <a href="/campaigns/create" class="btn btn-warning">
                                <i class="ki-duotone ki-rocket fs-3"></i>
                                Criar Primeira Campanha
                            </a>
                        </div>
                    </div>
                    
                    <!-- Recursos Adicionais -->
                    <div class="card">
                        <div class="card-body">
                            <h3 class="mb-5">Recursos Disponíveis</h3>
                            
                            <div class="row g-5">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <i class="ki-duotone ki-chart-simple fs-2x text-primary me-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <div>
                                            <h5>Analytics Avançado</h5>
                                            <p class="text-muted mb-2">Gráficos, métricas e comparações</p>
                                            <a href="/campaigns/analytics" class="btn btn-sm btn-light-primary">Acessar</a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <i class="ki-duotone ki-pulse fs-2x text-success me-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <div>
                                            <h5>Monitoramento em Tempo Real</h5>
                                            <p class="text-muted mb-2">Veja envios acontecendo ao vivo</p>
                                            <a href="/campaigns/realtime" class="btn btn-sm btn-light-success">Acessar</a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <i class="ki-duotone ki-graph-up fs-2x text-warning me-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <div>
                                            <h5>Comparar Campanhas</h5>
                                            <p class="text-muted mb-2">Compare performance lado a lado</p>
                                            <a href="/campaigns/compare" class="btn btn-sm btn-light-warning">Acessar</a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <i class="ki-duotone ki-document fs-2x text-info me-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <div>
                                            <h5>Templates Prontos</h5>
                                            <p class="text-muted mb-2">Use templates pré-definidos</p>
                                            <a href="/campaigns/templates" class="btn btn-sm btn-light-info">Acessar</a>
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
