<?php
/**
 * View: Documentação da API
 */

use App\Helpers\Url;

$pageTitle = 'Documentação da API';
$baseUrl = Url::fullUrl('/api/v1');
// Remover /api/v1 do final para usar como base
$baseUrl = substr($baseUrl, 0, -7);
$standaloneUrl = Url::fullUrl('/api.php');
ob_start();
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-5">
        <div>
            <h1 class="fw-bold mb-1">Documentacao da API REST</h1>
            <p class="text-muted mb-0">Guia completo para integracao</p>
        </div>
        <a href="<?= Url::to('/settings/api-tokens') ?>" class="btn btn-light-primary">
            <i class="ki-duotone ki-arrow-left fs-2"></i>
            Voltar para Tokens
        </a>
    </div>
    
    <!-- Aviso Importante -->
    <div class="alert alert-success d-flex align-items-center mb-5">
        <i class="ki-duotone ki-shield-tick fs-2x text-success me-3"><span class="path1"></span><span class="path2"></span></i>
        <div>
            <strong>Nova API Standalone Disponivel!</strong><br>
            Agora a API possui um gateway standalone que funciona sem depender de configuracoes de servidor. 
            Use <code><?= $standaloneUrl ?></code> para maior compatibilidade.
        </div>
    </div>
    
    <!-- Menu Lateral + Conteudo -->
    <div class="row g-5">
        <!-- Sidebar de Navegacao -->
        <div class="col-lg-3">
            <div class="card card-flush sticky-top" style="top: 80px;">
                <div class="card-body p-5">
                    <h4 class="fw-bold mb-5">Navegacao</h4>
                    <ul class="nav nav-pills flex-column" id="docs-nav">
                        <li class="nav-item mb-2">
                            <a class="nav-link active" href="#inicio">Inicio Rapido</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#urls">URLs da API</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#autenticacao">Autenticacao</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#whatsapp-accounts">WhatsApp Accounts</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#envio-direto">Envio Direto (WhatsApp)</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#conversas">Conversas</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#mensagens">Mensagens</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#contatos">Contatos</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#auxiliares">Recursos Auxiliares</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#paginacao">Paginacao</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#erros">Erros</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#rate-limit">Rate Limiting</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Conteudo da Documentacao -->
        <div class="col-lg-9">
            
            <!-- Inicio Rapido -->
            <div class="card mb-5" id="inicio">
                <div class="card-header">
                    <h3 class="card-title">Inicio Rapido</h3>
                </div>
                <div class="card-body">
                    <h4 class="fw-bold mb-3">1. Gerar Token</h4>
                    <p>Acesse <a href="<?= Url::to('/settings/api-tokens') ?>">Configuracoes - API & Tokens</a> e gere um novo token.</p>
                    
                    <h4 class="fw-bold mb-3 mt-5">2. Fazer Primeira Requisicao</h4>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            curl -X GET "<?= $standaloneUrl ?>/whatsapp-accounts" \<br>
                            &nbsp;&nbsp;-H "Authorization: Bearer SEU_TOKEN_AQUI"
                        </code>
                    </div>
                    
                    <div class="alert alert-info d-flex align-items-center mt-5">
                        <i class="ki-duotone ki-information-5 fs-2x text-info me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        <div>
                            <strong>URL Recomendada (Standalone):</strong> <code><?= $standaloneUrl ?></code><br>
                            <strong>URL Alternativa:</strong> <code><?= $baseUrl ?>/api/v1</code><br>
                            <strong>Formato:</strong> JSON<br>
                            <strong>Rate Limit:</strong> 100 requisicoes/minuto (padrao)
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- URLs da API -->
            <div class="card mb-5" id="urls">
                <div class="card-header bg-primary">
                    <h3 class="card-title text-white">URLs da API</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="border border-success rounded p-4 h-100">
                                <h5 class="text-success fw-bold mb-3">
                                    <i class="ki-duotone ki-check-circle text-success fs-2 me-2"><span class="path1"></span><span class="path2"></span></i>
                                    API Standalone (RECOMENDADO)
                                </h5>
                                <code class="fs-6"><?= $standaloneUrl ?></code>
                                <ul class="mt-3 mb-0">
                                    <li>Funciona em qualquer servidor</li>
                                    <li>Nao depende de .htaccess</li>
                                    <li>Maior compatibilidade</li>
                                    <li>Ideal para integracoes externas</li>
                                </ul>
                                
                                <div class="mt-4">
                                    <strong>Exemplo:</strong><br>
                                    <code><?= $standaloneUrl ?>/whatsapp-accounts</code><br>
                                    <code><?= $standaloneUrl ?>/messages/send</code><br>
                                    <code><?= $standaloneUrl ?>/conversations</code>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border border-secondary rounded p-4 h-100">
                                <h5 class="text-secondary fw-bold mb-3">
                                    <i class="ki-duotone ki-information text-secondary fs-2 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    API Tradicional
                                </h5>
                                <code class="fs-6"><?= $baseUrl ?>/api/v1</code>
                                <ul class="mt-3 mb-0">
                                    <li>Requer .htaccess configurado</li>
                                    <li>Formato RESTful classico</li>
                                    <li>Pode ter problemas em alguns hosts</li>
                                </ul>
                                
                                <div class="mt-4">
                                    <strong>Exemplo:</strong><br>
                                    <code><?= $baseUrl ?>/api/v1/whatsapp-accounts</code><br>
                                    <code><?= $baseUrl ?>/api/v1/messages/send</code><br>
                                    <code><?= $baseUrl ?>/api/v1/conversations</code>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mt-5 mb-0">
                        <strong>Dica:</strong> Se voce esta tendo erros 404 com a API tradicional, use a API Standalone.
                    </div>
                </div>
            </div>
            
            <!-- Autenticacao -->
            <div class="card mb-5" id="autenticacao">
                <div class="card-header">
                    <h3 class="card-title">Autenticacao</h3>
                </div>
                <div class="card-body">
                    <h4 class="fw-bold mb-3">Opcao 1: API Token (Recomendado)</h4>
                    <p>Gere um token permanente no painel e use em todas as requisicoes:</p>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            Authorization: Bearer SEU_TOKEN_AQUI<br>
                            # ou<br>
                            X-API-Key: SEU_TOKEN_AQUI
                        </code>
                    </div>
                    
                    <h4 class="fw-bold mb-3 mt-5">Opcao 2: Login (Token Temporario)</h4>
                    <div class="mb-5">
                        <div class="badge badge-light-primary mb-2">POST</div>
                        <code class="ms-2"><?= $standaloneUrl ?>/auth/login</code>
                        <div class="bg-light rounded p-4 mt-3">
                            <strong>Request:</strong>
                            <pre class="mb-0"><code>{
  "email": "usuario@empresa.com",
  "password": "senha123"
}</code></pre>
                        </div>
                        <div class="bg-light rounded p-4 mt-3">
                            <strong>Response:</strong>
                            <pre class="mb-0"><code>{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Usuario",
      "email": "usuario@empresa.com"
    },
    "access_token": "abc123...",
    "token_type": "Bearer",
    "expires_in": 86400
  }
}</code></pre>
                        </div>
                    </div>
                    
                    <h4 class="fw-bold mb-3 mt-5">Outros Endpoints de Auth</h4>
                    <table class="table table-row-bordered">
                        <thead>
                            <tr>
                                <th>Metodo</th>
                                <th>Endpoint</th>
                                <th>Descricao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/auth/me</code></td>
                                <td>Dados do usuario autenticado</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- WhatsApp Accounts -->
            <div class="card mb-5" id="whatsapp-accounts">
                <div class="card-header bg-success">
                    <h3 class="card-title text-white">WhatsApp Accounts</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-success mb-5">
                        <strong>Importante para Integracoes!</strong><br>
                        Use este endpoint para listar as contas WhatsApp disponiveis antes de enviar mensagens.
                    </div>
                    
                    <table class="table table-row-bordered">
                        <thead>
                            <tr>
                                <th>Metodo</th>
                                <th>Endpoint</th>
                                <th>Descricao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/whatsapp-accounts</code></td>
                                <td>Listar todas as contas WhatsApp</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/whatsapp-accounts/:id</code></td>
                                <td>Obter detalhes de uma conta</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4 class="fw-bold mb-3 mt-5">Filtros Disponiveis</h4>
                    <table class="table table-row-bordered">
                        <thead>
                            <tr>
                                <th>Parametro</th>
                                <th>Tipo</th>
                                <th>Descricao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>status</code></td>
                                <td>string</td>
                                <td><code>active</code>, <code>inactive</code>, <code>disconnected</code></td>
                            </tr>
                            <tr>
                                <td><code>page</code></td>
                                <td>integer</td>
                                <td>Numero da pagina (padrao: 1)</td>
                            </tr>
                            <tr>
                                <td><code>per_page</code></td>
                                <td>integer</td>
                                <td>Itens por pagina (padrao: 20, max: 100)</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4 class="fw-bold mb-3 mt-5">Exemplo: Listar Contas Ativas</h4>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            curl -X GET "<?= $standaloneUrl ?>/whatsapp-accounts?status=active" \<br>
                            &nbsp;&nbsp;-H "Authorization: Bearer SEU_TOKEN"
                        </code>
                    </div>
                    
                    <h5 class="fw-bold mb-3 mt-4">Resposta</h5>
                    <div class="bg-light rounded p-4">
                        <pre class="mb-0"><code>{
  "success": true,
  "data": {
    "accounts": [
      {
        "id": 1,
        "name": "Atendimento Geral",
        "phone_number": "5535991970289",
        "provider": "quepasa",
        "status": "active",
        "default_funnel_id": 1,
        "default_stage_id": 3,
        "created_at": "2024-01-15 10:30:00"
      }
    ],
    "pagination": {
      "total": 1,
      "page": 1,
      "per_page": 20,
      "total_pages": 1
    }
  }
}</code></pre>
                    </div>
                </div>
            </div>
            
            <!-- Envio Direto de Mensagens -->
            <div class="card mb-5" id="envio-direto">
                <div class="card-header bg-success">
                    <h3 class="card-title text-white">Envio Direto de Mensagens (WhatsApp)</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-success d-flex align-items-center mb-5">
                        <i class="ki-duotone ki-check-circle fs-2x text-success me-3"><span class="path1"></span><span class="path2"></span></i>
                        <div>
                            <strong>Endpoint Simplificado!</strong><br>
                            Envie mensagens diretamente para qualquer numero, mesmo sem contato ou conversa pre-existente.
                            O sistema criara automaticamente o contato e a conversa se necessario.
                        </div>
                    </div>
                    
                    <table class="table table-row-bordered">
                        <thead>
                            <tr>
                                <th>Metodo</th>
                                <th>Endpoint</th>
                                <th>Descricao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/messages/send</code></td>
                                <td>Enviar mensagem via WhatsApp</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4 class="fw-bold mb-3 mt-5">Parametros do Envio</h4>
                    <table class="table table-row-bordered">
                        <thead>
                            <tr>
                                <th>Campo</th>
                                <th>Tipo</th>
                                <th>Obrigatorio</th>
                                <th>Descricao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>to</code></td>
                                <td>string</td>
                                <td><span class="badge badge-light-danger">Sim</span></td>
                                <td>Numero do destinatario (ex: <code>5511999998888</code>)</td>
                            </tr>
                            <tr>
                                <td><code>from</code></td>
                                <td>string</td>
                                <td><span class="badge badge-light-danger">Sim</span></td>
                                <td>Numero da integracao WhatsApp (ex: <code>5535991970289</code>)</td>
                            </tr>
                            <tr>
                                <td><code>message</code></td>
                                <td>string</td>
                                <td><span class="badge badge-light-danger">Sim</span></td>
                                <td>Texto da mensagem (max. 4096 caracteres)</td>
                            </tr>
                            <tr>
                                <td><code>contact_name</code></td>
                                <td>string</td>
                                <td><span class="badge badge-light-secondary">Nao</span></td>
                                <td>Nome do contato (usado se for um novo contato)</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4 class="fw-bold mb-3 mt-5">Exemplo: Enviar Mensagem Direta</h4>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            curl -X POST "<?= $standaloneUrl ?>/messages/send" \<br>
                            &nbsp;&nbsp;-H "Authorization: Bearer SEU_TOKEN" \<br>
                            &nbsp;&nbsp;-H "Content-Type: application/json" \<br>
                            &nbsp;&nbsp;-d '{<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"to": "5511999998888",<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"from": "5535991970289",<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"message": "Ola! Esta e uma mensagem de teste via API.",<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"contact_name": "Joao Silva"<br>
                            &nbsp;&nbsp;}'
                        </code>
                    </div>
                    
                    <h5 class="fw-bold mb-3 mt-5">Resposta de Sucesso</h5>
                    <div class="bg-light rounded p-4">
                        <pre class="mb-0"><code>{
  "success": true,
  "message": "Mensagem enviada",
  "data": {
    "message_id": "12345",
    "conversation_id": "789",
    "contact_id": "456",
    "status": "sent"
  }
}</code></pre>
                    </div>
                    
                    <div class="alert alert-warning d-flex align-items-center mt-5">
                        <i class="ki-duotone ki-information-5 fs-2x text-warning me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        <div>
                            <strong>Importante:</strong><br>
                            O numero <code>from</code> deve corresponder a uma integracao WhatsApp ativa no sistema.
                            Use o endpoint <code>/whatsapp-accounts</code> para listar os numeros disponiveis.
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Conversas -->
            <div class="card mb-5" id="conversas">
                <div class="card-header">
                    <h3 class="card-title">Conversas</h3>
                </div>
                <div class="card-body">
                    <h4 class="fw-bold mb-3">Endpoints Disponiveis</h4>
                    <table class="table table-row-bordered">
                        <thead>
                            <tr>
                                <th>Metodo</th>
                                <th>Endpoint</th>
                                <th>Descricao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/conversations</code></td>
                                <td>Listar conversas</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/conversations</code></td>
                                <td>Criar conversa</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/conversations/:id</code></td>
                                <td>Obter conversa</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-warning">PUT</span></td>
                                <td><code>/conversations/:id</code></td>
                                <td>Atualizar conversa</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-danger">DELETE</span></td>
                                <td><code>/conversations/:id</code></td>
                                <td>Deletar conversa</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/conversations/:id/assign</code></td>
                                <td>Atribuir a agente</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/conversations/:id/close</code></td>
                                <td>Encerrar conversa</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/conversations/:id/reopen</code></td>
                                <td>Reabrir conversa</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/conversations/:id/move-stage</code></td>
                                <td>Mover no funil</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4 class="fw-bold mb-3 mt-5">Filtros de Listagem</h4>
                    <table class="table table-row-bordered">
                        <thead>
                            <tr>
                                <th>Parametro</th>
                                <th>Tipo</th>
                                <th>Descricao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>status</code></td>
                                <td>string</td>
                                <td><code>open</code>, <code>pending</code>, <code>closed</code></td>
                            </tr>
                            <tr>
                                <td><code>agent_id</code></td>
                                <td>integer</td>
                                <td>Filtrar por agente</td>
                            </tr>
                            <tr>
                                <td><code>page</code></td>
                                <td>integer</td>
                                <td>Numero da pagina</td>
                            </tr>
                            <tr>
                                <td><code>per_page</code></td>
                                <td>integer</td>
                                <td>Itens por pagina (max: 100)</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4 class="fw-bold mb-3 mt-5">Exemplo: Listar Conversas Abertas</h4>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            curl -X GET "<?= $standaloneUrl ?>/conversations?status=open&page=1" \<br>
                            &nbsp;&nbsp;-H "Authorization: Bearer TOKEN"
                        </code>
                    </div>
                    
                    <h4 class="fw-bold mb-3 mt-5">Exemplo: Criar Conversa</h4>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            curl -X POST "<?= $standaloneUrl ?>/conversations" \<br>
                            &nbsp;&nbsp;-H "Authorization: Bearer TOKEN" \<br>
                            &nbsp;&nbsp;-H "Content-Type: application/json" \<br>
                            &nbsp;&nbsp;-d '{<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"contact_id": 123,<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"channel": "whatsapp",<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"agent_id": 5,<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"department_id": 2<br>
                            &nbsp;&nbsp;}'
                        </code>
                    </div>
                </div>
            </div>
            
            <!-- Mensagens -->
            <div class="card mb-5" id="mensagens">
                <div class="card-header">
                    <h3 class="card-title">Mensagens</h3>
                </div>
                <div class="card-body">
                    <table class="table table-row-bordered">
                        <thead>
                            <tr>
                                <th>Metodo</th>
                                <th>Endpoint</th>
                                <th>Descricao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/conversations/:id/messages</code></td>
                                <td>Listar mensagens de uma conversa</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4 class="fw-bold mb-3 mt-5">Exemplo: Listar Mensagens</h4>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            curl -X GET "<?= $standaloneUrl ?>/conversations/456/messages" \<br>
                            &nbsp;&nbsp;-H "Authorization: Bearer TOKEN"
                        </code>
                    </div>
                </div>
            </div>
            
            <!-- Contatos -->
            <div class="card mb-5" id="contatos">
                <div class="card-header">
                    <h3 class="card-title">Contatos</h3>
                </div>
                <div class="card-body">
                    <table class="table table-row-bordered">
                        <thead>
                            <tr>
                                <th>Metodo</th>
                                <th>Endpoint</th>
                                <th>Descricao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/contacts</code></td>
                                <td>Listar contatos</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/contacts</code></td>
                                <td>Criar contato</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/contacts/:id</code></td>
                                <td>Obter contato</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-warning">PUT</span></td>
                                <td><code>/contacts/:id</code></td>
                                <td>Atualizar contato</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-danger">DELETE</span></td>
                                <td><code>/contacts/:id</code></td>
                                <td>Deletar contato</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/contacts/:id/conversations</code></td>
                                <td>Conversas do contato</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4 class="fw-bold mb-3 mt-5">Busca de Contatos</h4>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            GET /contacts?search=joao
                        </code>
                    </div>
                    <p class="mt-2 text-muted">Busca por nome, telefone ou email.</p>
                    
                    <h4 class="fw-bold mb-3 mt-5">Exemplo: Criar Contato</h4>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            curl -X POST "<?= $standaloneUrl ?>/contacts" \<br>
                            &nbsp;&nbsp;-H "Authorization: Bearer TOKEN" \<br>
                            &nbsp;&nbsp;-H "Content-Type: application/json" \<br>
                            &nbsp;&nbsp;-d '{<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"name": "Joao Silva",<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"phone_number": "5511999998888",<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"email": "joao@email.com"<br>
                            &nbsp;&nbsp;}'
                        </code>
                    </div>
                </div>
            </div>
            
            <!-- Recursos Auxiliares -->
            <div class="card mb-5" id="auxiliares">
                <div class="card-header">
                    <h3 class="card-title">Recursos Auxiliares</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="fw-bold mb-3">Agentes</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2"><code>GET /agents</code> - Listar agentes</li>
                                <li class="mb-2"><code>GET /agents/:id</code> - Obter agente</li>
                                <li class="mb-2"><code>GET /agents/:id/stats</code> - Estatisticas</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5 class="fw-bold mb-3">Setores (Departments)</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2"><code>GET /departments</code> - Listar setores</li>
                                <li class="mb-2"><code>GET /departments/:id</code> - Obter setor</li>
                            </ul>
                        </div>
                        <div class="col-md-6 mt-5">
                            <h5 class="fw-bold mb-3">Funis (Funnels)</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2"><code>GET /funnels</code> - Listar funis</li>
                                <li class="mb-2"><code>GET /funnels/:id</code> - Obter funil</li>
                                <li class="mb-2"><code>GET /funnels/:id/stages</code> - Etapas</li>
                                <li class="mb-2"><code>GET /funnels/:id/conversations</code> - Conversas</li>
                            </ul>
                        </div>
                        <div class="col-md-6 mt-5">
                            <h5 class="fw-bold mb-3">Tags</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2"><code>GET /tags</code> - Listar tags</li>
                                <li class="mb-2"><code>POST /tags</code> - Criar tag</li>
                                <li class="mb-2"><code>GET /tags/:id</code> - Obter tag</li>
                                <li class="mb-2"><code>PUT /tags/:id</code> - Atualizar tag</li>
                                <li class="mb-2"><code>DELETE /tags/:id</code> - Deletar tag</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Paginacao -->
            <div class="card mb-5" id="paginacao">
                <div class="card-header">
                    <h3 class="card-title">Paginacao</h3>
                </div>
                <div class="card-body">
                    <p>Endpoints de listagem suportam paginacao via query string:</p>
                    <div class="bg-light rounded p-4 mb-3">
                        <code class="text-dark">
                            GET /conversations?page=2&per_page=50
                        </code>
                    </div>
                    
                    <h5 class="fw-bold mb-3">Formato de Resposta</h5>
                    <div class="bg-light rounded p-4">
                        <pre class="mb-0"><code>{
  "success": true,
  "data": {
    "items": [...],
    "pagination": {
      "total": 150,
      "page": 2,
      "per_page": 50,
      "total_pages": 3,
      "has_next": true,
      "has_prev": true
    }
  }
}</code></pre>
                    </div>
                </div>
            </div>
            
            <!-- Erros -->
            <div class="card mb-5" id="erros">
                <div class="card-header">
                    <h3 class="card-title">Tratamento de Erros</h3>
                </div>
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Codigos HTTP</h5>
                    <table class="table table-row-bordered">
                        <tbody>
                            <tr>
                                <td><span class="badge badge-light-success">200</span></td>
                                <td>OK - Sucesso</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">201</span></td>
                                <td>Created - Recurso criado</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-warning">400</span></td>
                                <td>Bad Request - Requisicao invalida</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-danger">401</span></td>
                                <td>Unauthorized - Nao autenticado</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-danger">403</span></td>
                                <td>Forbidden - Sem permissao</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-warning">404</span></td>
                                <td>Not Found - Recurso nao encontrado</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-warning">422</span></td>
                                <td>Validation Error - Erro de validacao</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-danger">429</span></td>
                                <td>Too Many Requests - Rate limit excedido</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-danger">500</span></td>
                                <td>Server Error - Erro interno</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h5 class="fw-bold mb-3 mt-5">Formato de Erro</h5>
                    <div class="bg-light rounded p-4">
                        <pre class="mb-0"><code>{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Dados invalidos",
    "details": {
      "contact_id": ["Campo obrigatorio"],
      "channel": ["Deve ser um dos: whatsapp, email, ..."]
    }
  }
}</code></pre>
                    </div>
                </div>
            </div>
            
            <!-- Rate Limiting -->
            <div class="card mb-5" id="rate-limit">
                <div class="card-header">
                    <h3 class="card-title">Rate Limiting</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="ki-duotone ki-information-5 fs-2x text-info me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        <div>
                            <strong>Limite Padrao:</strong> 100 requisicoes por minuto<br>
                            <strong>Configuravel:</strong> Por token no painel de gerenciamento
                        </div>
                    </div>
                    
                    <h5 class="fw-bold mb-3">Headers de Resposta</h5>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            X-RateLimit-Limit: 100<br>
                            X-RateLimit-Remaining: 95<br>
                            X-RateLimit-Reset: 1704465600
                        </code>
                    </div>
                    
                    <h5 class="fw-bold mb-3 mt-5">Quando Exceder</h5>
                    <div class="bg-light rounded p-4">
                        <pre class="mb-0"><code>HTTP/1.1 429 Too Many Requests
Retry-After: 45

{
  "success": false,
  "error": {
    "code": "TOO_MANY_REQUESTS",
    "message": "Limite de 100 requisicoes por minuto excedido"
  }
}</code></pre>
                    </div>
                </div>
            </div>
            
            <!-- Exemplo PHP -->
            <div class="card mb-5" id="exemplo-php">
                <div class="card-header bg-dark">
                    <h3 class="card-title text-white">Exemplo de Codigo PHP</h3>
                </div>
                <div class="card-body">
                    <p>Exemplo completo de integracao usando PHP:</p>
                    <div class="bg-light rounded p-4">
                        <pre class="mb-0"><code>&lt;?php
// Configuracao
$apiUrl = '<?= $standaloneUrl ?>';
$token = 'SEU_TOKEN_AQUI';

// Funcao para fazer requisicoes
function apiRequest($endpoint, $method = 'GET', $data = null) {
    global $apiUrl, $token;
    
    $ch = curl_init($apiUrl . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

// Exemplo 1: Listar contas WhatsApp
$accounts = apiRequest('/whatsapp-accounts');
print_r($accounts);

// Exemplo 2: Enviar mensagem
$result = apiRequest('/messages/send', 'POST', [
    'to' => '5511999998888',
    'from' => '5535991970289',
    'message' => 'Ola! Mensagem de teste.',
    'contact_name' => 'Cliente Teste'
]);
print_r($result);
?&gt;</code></pre>
                    </div>
                </div>
            </div>
            
            <!-- Botao Voltar -->
            <div class="text-center">
                <a href="<?= Url::to('/settings/api-tokens') ?>" class="btn btn-light-primary btn-lg">
                    <i class="ki-duotone ki-arrow-left fs-2"></i>
                    Voltar para Tokens
                </a>
            </div>
            
        </div>
    </div>
</div>

<style>
/* Scroll suave */
html {
    scroll-behavior: smooth;
}

/* Destaque do link ativo */
#docs-nav .nav-link.active {
    background-color: #3699FF !important;
    color: white !important;
}

/* Codigo mais legivel */
code {
    font-size: 0.9rem;
}

pre code {
    font-size: 0.85rem;
}
</style>

<script>
// Scroll spy - destacar secao atual
document.addEventListener('DOMContentLoaded', function() {
    const sections = document.querySelectorAll('.card[id]');
    const navLinks = document.querySelectorAll('#docs-nav .nav-link');
    
    window.addEventListener('scroll', () => {
        let current = '';
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            if (window.pageYOffset >= (sectionTop - 150)) {
                current = section.getAttribute('id');
            }
        });
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === '#' + current) {
                link.classList.add('active');
            }
        });
    });
});
</script>

<?php 
$content = ob_get_clean(); 
?>

<?php include __DIR__ . '/../../layouts/metronic/app.php'; ?>
