<?php
$layout = 'layouts.metronic.app';
$title = 'Ajuda - Campanhas';
$pageTitle = 'Ajuda';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    Central de Ajuda - Campanhas
                </h1>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <div class="row">
                <div class="col-xl-3">
                    <!-- Menu Lateral -->
                    <div class="card card-flush mb-5" style="position: sticky; top: 100px;">
                        <div class="card-body">
                            <div class="menu menu-column menu-rounded menu-state-bg menu-state-title-primary">
                                <div class="menu-item">
                                    <a href="#rotacao" class="menu-link">
                                        <span class="menu-icon"><i class="ki-duotone ki-arrows-circle fs-2"></i></span>
                                        <span class="menu-title">Rotação de Contas</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a href="#cadencia" class="menu-link">
                                        <span class="menu-icon"><i class="ki-duotone ki-timer fs-2"></i></span>
                                        <span class="menu-title">Cadência</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a href="#variaveis" class="menu-link">
                                        <span class="menu-icon"><i class="ki-duotone ki-tag fs-2"></i></span>
                                        <span class="menu-title">Variáveis</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a href="#abtest" class="menu-link">
                                        <span class="menu-icon"><i class="ki-duotone ki-chart-line-up-2 fs-2"></i></span>
                                        <span class="menu-title">A/B Testing</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a href="#drip" class="menu-link">
                                        <span class="menu-icon"><i class="ki-duotone ki-abstract-26 fs-2"></i></span>
                                        <span class="menu-title">Drip Campaigns</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-9">
                    
                    <!-- Rotação -->
                    <div class="card mb-5" id="rotacao">
                        <div class="card-header">
                            <h3 class="card-title">Rotação de Contas WhatsApp</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-5">
                                <h4>O que é?</h4>
                                <p>Sistema que distribui automaticamente os envios entre múltiplas contas WhatsApp, alternando entre elas de forma inteligente.</p>
                            </div>
                            
                            <div class="mb-5">
                                <h4>Como funciona?</h4>
                                <div class="p-4 bg-light rounded">
                                    <strong>Exemplo com 3 contas:</strong><br><br>
                                    Mensagem 1 → Conta A (11 9999-1111)<br>
                                    Mensagem 2 → Conta B (11 9999-2222)<br>
                                    Mensagem 3 → Conta C (11 9999-3333)<br>
                                    Mensagem 4 → Conta A (reinicia) 🔄
                                </div>
                            </div>
                            
                            <div class="mb-5">
                                <h4>Estratégias disponíveis:</h4>
                                <ul>
                                    <li><strong>Round Robin:</strong> Revezamento justo (padrão)</li>
                                    <li><strong>Random:</strong> Seleção aleatória</li>
                                    <li><strong>By Load:</strong> Seleciona menos usada (últimas 24h)</li>
                                </ul>
                            </div>
                            
                            <div class="alert alert-success">
                                <strong>Benefícios:</strong>
                                <ul class="mb-0">
                                    <li>+40% deliverability</li>
                                    <li>-80% risco de bloqueio</li>
                                    <li>Escalabilidade ilimitada</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cadência -->
                    <div class="card mb-5" id="cadencia">
                        <div class="card-header">
                            <h3 class="card-title">Cadência e Rate Limiting</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-5">
                                <h4>Configurações:</h4>
                                <ul>
                                    <li><strong>Mensagens por minuto:</strong> Limite de envios (recomendado: 10-20)</li>
                                    <li><strong>Intervalo entre mensagens:</strong> Segundos de espera (recomendado: 3-6s)</li>
                                    <li><strong>Janela de envio:</strong> Horário permitido (ex: 09:00-18:00)</li>
                                    <li><strong>Dias da semana:</strong> Quais dias enviar (ex: Seg-Sex)</li>
                                </ul>
                            </div>
                            
                            <div class="alert alert-warning">
                                <strong>Dica:</strong> Contas novas devem usar taxas mais baixas (5-10 msgs/min). Aumente gradualmente conforme a reputação melhora.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Variáveis -->
                    <div class="card mb-5" id="variaveis">
                        <div class="card-header">
                            <h3 class="card-title">Variáveis Dinâmicas</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Variável</th>
                                            <th>Exemplo</th>
                                            <th>Descrição</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><code>{{nome}}</code></td>
                                            <td>João Silva</td>
                                            <td>Nome completo</td>
                                        </tr>
                                        <tr>
                                            <td><code>{{primeiro_nome}}</code></td>
                                            <td>João</td>
                                            <td>Primeiro nome</td>
                                        </tr>
                                        <tr>
                                            <td><code>{{telefone}}</code></td>
                                            <td>(11) 99999-1111</td>
                                            <td>Telefone formatado</td>
                                        </tr>
                                        <tr>
                                            <td><code>{{email}}</code></td>
                                            <td>joao@email.com</td>
                                            <td>Email do contato</td>
                                        </tr>
                                        <tr>
                                            <td><code>{{empresa}}</code></td>
                                            <td>Empresa ABC</td>
                                            <td>Nome da empresa</td>
                                        </tr>
                                        <tr>
                                            <td><code>{{cidade}}</code></td>
                                            <td>São Paulo</td>
                                            <td>Cidade</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- A/B Testing -->
                    <div class="card mb-5" id="abtest">
                        <div class="card-header">
                            <h3 class="card-title">A/B Testing</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-5">
                                <h4>Como usar:</h4>
                                <ol>
                                    <li>Crie 2 ou mais variantes da mensagem</li>
                                    <li>Defina a porcentagem para cada (ex: 50% / 50%)</li>
                                    <li>Sistema distribui automaticamente</li>
                                    <li>Compare resultados no dashboard</li>
                                    <li>Use a variante vencedora em futuras campanhas</li>
                                </ol>
                            </div>
                            
                            <div class="p-4 bg-light rounded">
                                <strong>Exemplo:</strong><br><br>
                                <strong>Variante A (50%):</strong> "Olá! Temos uma promoção..."<br>
                                <strong>Variante B (50%):</strong> "Oi {{nome}}! Oferta especial..."<br><br>
                                Sistema compara taxas de resposta e indica a vencedora.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Drip -->
                    <div class="card mb-5" id="drip">
                        <div class="card-header">
                            <h3 class="card-title">Drip Campaigns (Sequências)</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-5">
                                <h4>O que é?</h4>
                                <p>Campanhas em múltiplas etapas com delays automáticos. Ideal para nutrição de leads.</p>
                            </div>
                            
                            <div class="mb-5">
                                <h4>Exemplo de Sequência (3 etapas):</h4>
                                <div class="timeline">
                                    <div class="timeline-item">
                                        <div class="timeline-line w-40px"></div>
                                        <div class="timeline-icon symbol symbol-circle symbol-40px">
                                            <div class="symbol-label bg-light-primary"><span class="text-primary fw-bold">1</span></div>
                                        </div>
                                        <div class="timeline-content mb-5">
                                            <div class="fw-bold">Dia 0: Mensagem inicial</div>
                                            <div class="text-muted">"Olá! Temos uma oferta..."</div>
                                        </div>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-line w-40px"></div>
                                        <div class="timeline-icon symbol symbol-circle symbol-40px">
                                            <div class="symbol-label bg-light-success"><span class="text-success fw-bold">2</span></div>
                                        </div>
                                        <div class="timeline-content mb-5">
                                            <div class="fw-bold">Dia 2: Follow-up (se NÃO respondeu)</div>
                                            <div class="text-muted">"Não perca! Termina amanhã..."</div>
                                        </div>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-line w-40px"></div>
                                        <div class="timeline-icon symbol symbol-circle symbol-40px">
                                            <div class="symbol-label bg-light-warning"><span class="text-warning fw-bold">3</span></div>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="fw-bold">Dia 5: Última chance</div>
                                            <div class="text-muted">"Última chance!"</div>
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
