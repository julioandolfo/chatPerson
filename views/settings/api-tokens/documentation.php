<?php
/**
 * View: Documentação da API
 */

use App\Helpers\Url;

$pageTitle = 'Documentação da API';
$baseUrl = Url::fullUrl('/api/v1');
// Remover /api/v1 do final para usar como base
$baseUrl = substr($baseUrl, 0, -7);
ob_start();
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-5">
        <div>
            <h1 class="fw-bold mb-1">Documentação da API REST</h1>
            <p class="text-muted mb-0">Guia completo para integração</p>
        </div>
        <a href="<?= Url::to('/settings/api-tokens') ?>" class="btn btn-light-primary">
            <i class="ki-duotone ki-arrow-left fs-2"></i>
            Voltar para Tokens
        </a>
    </div>
    
    <!-- Menu Lateral + Conteúdo -->
    <div class="row g-5">
        <!-- Sidebar de Navegação -->
        <div class="col-lg-3">
            <div class="card card-flush sticky-top" style="top: 80px;">
                <div class="card-body p-5">
                    <h4 class="fw-bold mb-5">Navegação</h4>
                    <ul class="nav nav-pills flex-column" id="docs-nav">
                        <li class="nav-item mb-2">
                            <a class="nav-link active" href="#inicio">Início Rápido</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#autenticacao">Autenticação</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#conversas">Conversas</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#mensagens">Mensagens</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#envio-direto">📤 Envio Direto (WhatsApp)</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#contatos">Contatos</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#participantes">Participantes</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#auxiliares">Recursos Auxiliares</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="#paginacao">Paginação</a>
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
        
        <!-- Conteúdo da Documentação -->
        <div class="col-lg-9">
            
            <!-- Início Rápido -->
            <div class="card mb-5" id="inicio">
                <div class="card-header">
                    <h3 class="card-title">🚀 Início Rápido</h3>
                </div>
                <div class="card-body">
                    <h4 class="fw-bold mb-3">1. Gerar Token</h4>
                    <p>Acesse <a href="<?= Url::to('/settings/api-tokens') ?>">Configurações → API & Tokens</a> e gere um novo token.</p>
                    
                    <h4 class="fw-bold mb-3 mt-5">2. Fazer Primeira Requisição</h4>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            curl -X GET "<?= $baseUrl ?>/api/v1/conversations" \<br>
                            &nbsp;&nbsp;-H "Authorization: Bearer SEU_TOKEN_AQUI"
                        </code>
                    </div>
                    
                    <div class="alert alert-info d-flex align-items-center mt-5">
                        <i class="ki-duotone ki-information-5 fs-2x text-info me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        <div>
                            <strong>Base URL:</strong> <code><?= $baseUrl ?>/api/v1</code><br>
                            <strong>Formato:</strong> JSON<br>
                            <strong>Rate Limit:</strong> 100 requisições/minuto (padrão)
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Autenticação -->
            <div class="card mb-5" id="autenticacao">
                <div class="card-header">
                    <h3 class="card-title">🔐 Autenticação</h3>
                </div>
                <div class="card-body">
                    <h4 class="fw-bold mb-3">Opção 1: JWT (Login Temporário)</h4>
                    <div class="mb-5">
                        <div class="badge badge-light-primary mb-2">POST</div>
                        <code class="ms-2">/api/v1/auth/login</code>
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
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}</code></pre>
                        </div>
                    </div>
                    
                    <h4 class="fw-bold mb-3">Opção 2: API Token (Permanente)</h4>
                    <p>Gere um token permanente no painel e use em todas as requisições:</p>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            Authorization: Bearer SEU_TOKEN_AQUI<br>
                            # ou<br>
                            X-API-Key: SEU_TOKEN_AQUI
                        </code>
                    </div>
                    
                    <h4 class="fw-bold mb-3 mt-5">Outros Endpoints de Auth</h4>
                    <table class="table table-row-bordered">
                        <thead>
                            <tr>
                                <th>Método</th>
                                <th>Endpoint</th>
                                <th>Descrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/api/v1/auth/refresh</code></td>
                                <td>Renovar JWT</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/api/v1/auth/logout</code></td>
                                <td>Logout</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/api/v1/auth/me</code></td>
                                <td>Dados do usuário autenticado</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Conversas -->
            <div class="card mb-5" id="conversas">
                <div class="card-header">
                    <h3 class="card-title">💬 Conversas</h3>
                </div>
                <div class="card-body">
                    <h4 class="fw-bold mb-3">Endpoints Disponíveis</h4>
                    <table class="table table-row-bordered">
                        <thead>
                            <tr>
                                <th>Método</th>
                                <th>Endpoint</th>
                                <th>Descrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/api/v1/conversations</code></td>
                                <td>Listar conversas</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/api/v1/conversations</code></td>
                                <td>Criar conversa</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/api/v1/conversations/:id</code></td>
                                <td>Obter conversa</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-warning">PUT</span></td>
                                <td><code>/api/v1/conversations/:id</code></td>
                                <td>Atualizar conversa</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-danger">DELETE</span></td>
                                <td><code>/api/v1/conversations/:id</code></td>
                                <td>Deletar conversa</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/api/v1/conversations/:id/assign</code></td>
                                <td>Atribuir conversa</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/api/v1/conversations/:id/close</code></td>
                                <td>Encerrar conversa</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/api/v1/conversations/:id/reopen</code></td>
                                <td>Reabrir conversa</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/api/v1/conversations/:id/move-stage</code></td>
                                <td>Mover no funil</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-warning">PUT</span></td>
                                <td><code>/api/v1/conversations/:id/department</code></td>
                                <td>Mudar setor</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/api/v1/conversations/:id/tags</code></td>
                                <td>Adicionar tag</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-danger">DELETE</span></td>
                                <td><code>/api/v1/conversations/:id/tags/:tagId</code></td>
                                <td>Remover tag</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4 class="fw-bold mb-3 mt-5">Exemplo: Criar Conversa</h4>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            curl -X POST "<?= $baseUrl ?>/api/v1/conversations" \<br>
                            &nbsp;&nbsp;-H "Authorization: Bearer TOKEN" \<br>
                            &nbsp;&nbsp;-H "Content-Type: application/json" \<br>
                            &nbsp;&nbsp;-d '{<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"contact_id": 123,<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"channel": "whatsapp",<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"agent_id": 5,<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"department_id": 2,<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"funnel_id": 1,<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"stage_id": 3<br>
                            &nbsp;&nbsp;}'
                        </code>
                    </div>
                    
                    <h4 class="fw-bold mb-3 mt-5">Exemplo: Listar com Filtros</h4>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            GET /api/v1/conversations?status=open&agent_id=5&page=1&per_page=20
                        </code>
                    </div>
                </div>
            </div>
            
            <!-- Mensagens -->
            <div class="card mb-5" id="mensagens">
                <div class="card-header">
                    <h3 class="card-title">✉️ Mensagens</h3>
                </div>
                <div class="card-body">
                    <table class="table table-row-bordered">
                        <thead>
                            <tr>
                                <th>Método</th>
                                <th>Endpoint</th>
                                <th>Descrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/api/v1/conversations/:id/messages</code></td>
                                <td>Listar mensagens</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/api/v1/conversations/:id/messages</code></td>
                                <td>Enviar mensagem</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/api/v1/messages/:id</code></td>
                                <td>Obter mensagem</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4 class="fw-bold mb-3 mt-5">Exemplo: Enviar Mensagem</h4>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            curl -X POST "<?= $baseUrl ?>/api/v1/conversations/456/messages" \<br>
                            &nbsp;&nbsp;-H "Authorization: Bearer TOKEN" \<br>
                            &nbsp;&nbsp;-H "Content-Type: application/json" \<br>
                            &nbsp;&nbsp;-d '{<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"body": "Olá! Como posso ajudar?",<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"type": "text"<br>
                            &nbsp;&nbsp;}'
                        </code>
                    </div>
                </div>
            </div>
            
            <!-- Envio Direto de Mensagens -->
            <div class="card mb-5" id="envio-direto">
                <div class="card-header">
                    <h3 class="card-title">📤 Envio Direto de Mensagens (WhatsApp)</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-success d-flex align-items-center mb-5">
                        <i class="ki-duotone ki-check-circle fs-2x text-success me-3"><span class="path1"></span><span class="path2"></span></i>
                        <div>
                            <strong>Endpoint Simplificado!</strong><br>
                            Envie mensagens diretamente para qualquer número, mesmo sem contato ou conversa pré-existente.
                            O sistema criará automaticamente o contato e a conversa se necessário.
                        </div>
                    </div>
                    
                    <table class="table table-row-bordered">
                        <thead>
                            <tr>
                                <th>Método</th>
                                <th>Endpoint</th>
                                <th>Descrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/api/v1/messages/send</code></td>
                                <td>Enviar mensagem via WhatsApp</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/api/v1/whatsapp/accounts</code></td>
                                <td>Listar integrações WhatsApp disponíveis</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4 class="fw-bold mb-3 mt-5">Parâmetros do Envio</h4>
                    <table class="table table-row-bordered">
                        <thead>
                            <tr>
                                <th>Campo</th>
                                <th>Tipo</th>
                                <th>Obrigatório</th>
                                <th>Descrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>to</code></td>
                                <td>string</td>
                                <td><span class="badge badge-light-danger">Sim</span></td>
                                <td>Número do destinatário (ex: <code>5535991970289</code>)</td>
                            </tr>
                            <tr>
                                <td><code>from</code></td>
                                <td>string</td>
                                <td><span class="badge badge-light-danger">Sim</span></td>
                                <td>Número da integração WhatsApp (ex: <code>5535991970289</code>)</td>
                            </tr>
                            <tr>
                                <td><code>message</code></td>
                                <td>string</td>
                                <td><span class="badge badge-light-danger">Sim</span></td>
                                <td>Texto da mensagem (máx. 4096 caracteres)</td>
                            </tr>
                            <tr>
                                <td><code>contact_name</code></td>
                                <td>string</td>
                                <td><span class="badge badge-light-secondary">Não</span></td>
                                <td>Nome do contato (usado se for um novo contato)</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4 class="fw-bold mb-3 mt-5">Exemplo: Enviar Mensagem Direta</h4>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            curl -X POST "<?= $baseUrl ?>/api/v1/messages/send" \<br>
                            &nbsp;&nbsp;-H "Authorization: Bearer SEU_TOKEN" \<br>
                            &nbsp;&nbsp;-H "Content-Type: application/json" \<br>
                            &nbsp;&nbsp;-d '{<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"to": "5511999998888",<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"from": "5535991970289",<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"message": "Olá! Esta é uma mensagem de teste via API.",<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"contact_name": "João Silva"<br>
                            &nbsp;&nbsp;}'
                        </code>
                    </div>
                    
                    <h5 class="fw-bold mb-3 mt-5">Resposta de Sucesso</h5>
                    <div class="bg-light rounded p-4">
                        <pre class="mb-0"><code>{
  "success": true,
  "data": {
    "message_id": 12345,
    "external_id": "3EB0123ABC456DEF",
    "status": "sent",
    "conversation_id": 789,
    "contact_id": 456,
    "is_new_contact": false,
    "is_new_conversation": false
  }
}</code></pre>
                    </div>
                    
                    <h4 class="fw-bold mb-3 mt-5">Exemplo: Listar Integrações WhatsApp</h4>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            curl -X GET "<?= $baseUrl ?>/api/v1/whatsapp/accounts" \<br>
                            &nbsp;&nbsp;-H "Authorization: Bearer SEU_TOKEN"
                        </code>
                    </div>
                    
                    <h5 class="fw-bold mb-3 mt-3">Resposta</h5>
                    <div class="bg-light rounded p-4">
                        <pre class="mb-0"><code>{
  "success": true,
  "data": {
    "accounts": [
      {
        "id": 1,
        "name": "Atendimento Geral",
        "phone_number": "5535991970289",
        "status": "active",
        "provider": "quepasa"
      }
    ],
    "total": 1
  }
}</code></pre>
                    </div>
                    
                    <div class="alert alert-warning d-flex align-items-center mt-5">
                        <i class="ki-duotone ki-information-5 fs-2x text-warning me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        <div>
                            <strong>Importante:</strong><br>
                            O número <code>from</code> deve corresponder a uma integração WhatsApp ativa no sistema.
                            Use o endpoint <code>/api/v1/whatsapp/accounts</code> para listar os números disponíveis.
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contatos -->
            <div class="card mb-5" id="contatos">
                <div class="card-header">
                    <h3 class="card-title">👥 Contatos</h3>
                </div>
                <div class="card-body">
                    <table class="table table-row-bordered">
                        <thead>
                            <tr>
                                <th>Método</th>
                                <th>Endpoint</th>
                                <th>Descrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/api/v1/contacts</code></td>
                                <td>Listar contatos</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/api/v1/contacts</code></td>
                                <td>Criar contato</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/api/v1/contacts/:id</code></td>
                                <td>Obter contato</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-warning">PUT</span></td>
                                <td><code>/api/v1/contacts/:id</code></td>
                                <td>Atualizar contato</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-danger">DELETE</span></td>
                                <td><code>/api/v1/contacts/:id</code></td>
                                <td>Deletar contato</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/api/v1/contacts/:id/conversations</code></td>
                                <td>Conversas do contato</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4 class="fw-bold mb-3 mt-5">Exemplo: Criar Contato</h4>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            curl -X POST "<?= $baseUrl ?>/api/v1/contacts" \<br>
                            &nbsp;&nbsp;-H "Authorization: Bearer TOKEN" \<br>
                            &nbsp;&nbsp;-H "Content-Type: application/json" \<br>
                            &nbsp;&nbsp;-d '{<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"name": "João Silva",<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"phone": "5511999998888",<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"email": "joao@email.com"<br>
                            &nbsp;&nbsp;}'
                        </code>
                    </div>
                </div>
            </div>
            
            <!-- Participantes -->
            <div class="card mb-5" id="participantes">
                <div class="card-header">
                    <h3 class="card-title">👨‍💼 Participantes</h3>
                </div>
                <div class="card-body">
                    <table class="table table-row-bordered">
                        <thead>
                            <tr>
                                <th>Método</th>
                                <th>Endpoint</th>
                                <th>Descrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-light-primary">GET</span></td>
                                <td><code>/api/v1/conversations/:id/participants</code></td>
                                <td>Listar participantes</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-success">POST</span></td>
                                <td><code>/api/v1/conversations/:id/participants</code></td>
                                <td>Adicionar participante</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-danger">DELETE</span></td>
                                <td><code>/api/v1/conversations/:id/participants/:userId</code></td>
                                <td>Remover participante</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4 class="fw-bold mb-3 mt-5">Exemplo: Adicionar Participante</h4>
                    <div class="bg-light rounded p-4">
                        <code class="text-dark">
                            curl -X POST "<?= $baseUrl ?>/api/v1/conversations/456/participants" \<br>
                            &nbsp;&nbsp;-H "Authorization: Bearer TOKEN" \<br>
                            &nbsp;&nbsp;-H "Content-Type: application/json" \<br>
                            &nbsp;&nbsp;-d '{<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"user_id": 10,<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"role": "observer"<br>
                            &nbsp;&nbsp;}'
                        </code>
                    </div>
                </div>
            </div>
            
            <!-- Recursos Auxiliares -->
            <div class="card mb-5" id="auxiliares">
                <div class="card-header">
                    <h3 class="card-title">🔧 Recursos Auxiliares</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="fw-bold mb-3">Agentes</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2"><code>GET /api/v1/agents</code></li>
                                <li class="mb-2"><code>GET /api/v1/agents/:id</code></li>
                                <li class="mb-2"><code>GET /api/v1/agents/:id/stats</code></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5 class="fw-bold mb-3">Setores</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2"><code>GET /api/v1/departments</code></li>
                                <li class="mb-2"><code>GET /api/v1/departments/:id</code></li>
                            </ul>
                        </div>
                        <div class="col-md-6 mt-5">
                            <h5 class="fw-bold mb-3">Funis</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2"><code>GET /api/v1/funnels</code></li>
                                <li class="mb-2"><code>GET /api/v1/funnels/:id</code></li>
                                <li class="mb-2"><code>GET /api/v1/funnels/:id/stages</code></li>
                                <li class="mb-2"><code>GET /api/v1/funnels/:id/conversations</code></li>
                            </ul>
                        </div>
                        <div class="col-md-6 mt-5">
                            <h5 class="fw-bold mb-3">Tags</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2"><code>GET /api/v1/tags</code></li>
                                <li class="mb-2"><code>POST /api/v1/tags</code></li>
                                <li class="mb-2"><code>GET /api/v1/tags/:id</code></li>
                                <li class="mb-2"><code>PUT /api/v1/tags/:id</code></li>
                                <li class="mb-2"><code>DELETE /api/v1/tags/:id</code></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Paginação -->
            <div class="card mb-5" id="paginacao">
                <div class="card-header">
                    <h3 class="card-title">📄 Paginação</h3>
                </div>
                <div class="card-body">
                    <p>Endpoints de listagem suportam paginação via query string:</p>
                    <div class="bg-light rounded p-4 mb-3">
                        <code class="text-dark">
                            GET /api/v1/conversations?page=2&per_page=50
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
                    <h3 class="card-title">❌ Tratamento de Erros</h3>
                </div>
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Códigos HTTP</h5>
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
                                <td>Bad Request - Requisição inválida</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-danger">401</span></td>
                                <td>Unauthorized - Não autenticado</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-danger">403</span></td>
                                <td>Forbidden - Sem permissão</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-warning">404</span></td>
                                <td>Not Found - Recurso não encontrado</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-light-warning">422</span></td>
                                <td>Validation Error - Erro de validação</td>
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
    "message": "Dados inválidos",
    "details": {
      "contact_id": ["Campo obrigatório"],
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
                    <h3 class="card-title">⏱️ Rate Limiting</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="ki-duotone ki-information-5 fs-2x text-info me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        <div>
                            <strong>Limite Padrão:</strong> 100 requisições por minuto<br>
                            <strong>Configurável:</strong> Por token no painel de gerenciamento
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
    "message": "Limite de 100 requisições por minuto excedido"
  }
}</code></pre>
                    </div>
                </div>
            </div>
            
            <!-- Botão Voltar -->
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

/* Código mais legível */
code {
    font-size: 0.9rem;
}

pre code {
    font-size: 0.85rem;
}
</style>

<script>
// Scroll spy - destacar seção atual
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
