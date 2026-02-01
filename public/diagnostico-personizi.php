<?php
/**
 * Diagnóstico de Integração com Personizi
 * Acesse: https://chat.personizi.com.br/diagnostico-personizi.php
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

// Verificar autenticação
session_start();
if (!isset($_SESSION['user_id'])) {
    die('❌ Acesso negado. Faça login primeiro.');
}

$baseUrl = 'https://' . $_SERVER['HTTP_HOST'];
$apiUrl = $baseUrl . '/api/v1';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico - Integração Personizi</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .section {
            margin-bottom: 40px;
            padding-bottom: 40px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            font-size: 1.8em;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .icon {
            font-size: 1.2em;
        }
        
        .status-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .status-box.success {
            background: #d4edda;
            border-left-color: #28a745;
        }
        
        .status-box.error {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        
        .status-box.warning {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        
        .config-item {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .config-label {
            font-weight: bold;
            color: #495057;
            margin-bottom: 8px;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .config-value {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            font-size: 1em;
            color: #212529;
            word-break: break-all;
        }
        
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            cursor: pointer;
            font-size: 1em;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }
        
        .code-block {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            line-height: 1.6;
        }
        
        .step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .step-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 20px;
            flex-shrink: 0;
        }
        
        .step-content {
            flex: 1;
        }
        
        .step-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
            font-size: 1.1em;
        }
        
        .step-description {
            color: #666;
            line-height: 1.6;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 30px;
            text-align: center;
            color: #666;
        }
        
        .test-button {
            margin-top: 20px;
        }
        
        #testResult {
            margin-top: 20px;
            padding: 20px;
            border-radius: 8px;
            display: none;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔗 Diagnóstico Personizi</h1>
            <p>Configuração da Integração com API</p>
        </div>
        
        <div class="content">
            <!-- Status Atual -->
            <div class="section">
                <h2 class="section-title">
                    <span class="icon">✅</span>
                    Status da Correção
                </h2>
                
                <div class="status-box success">
                    <strong>✅ Endpoints criados com sucesso!</strong>
                    <p style="margin-top: 10px;">
                        Os novos endpoints para integração com o Personizi foram implementados e estão disponíveis.
                    </p>
                </div>
            </div>
            
            <!-- Configuração no Personizi -->
            <div class="section">
                <h2 class="section-title">
                    <span class="icon">⚙️</span>
                    Configuração no Personizi
                </h2>
                
                <div class="config-item">
                    <div class="config-label">URL Base da API</div>
                    <div class="config-value"><?= htmlspecialchars($apiUrl) ?></div>
                </div>
                
                <div class="config-item">
                    <div class="config-label">Endpoint de Contas WhatsApp</div>
                    <div class="config-value"><?= htmlspecialchars($apiUrl) ?>/whatsapp-accounts</div>
                </div>
                
                <div class="config-item">
                    <div class="config-label">Token de API</div>
                    <div class="config-value">
                        Configure em: <strong>Configurações > API & Tokens</strong>
                    </div>
                </div>
                
                <div style="margin-top: 30px;">
                    <a href="/settings/api-tokens" class="btn">
                        🔑 Gerenciar Tokens de API
                    </a>
                </div>
            </div>
            
            <!-- Passos de Configuração -->
            <div class="section">
                <h2 class="section-title">
                    <span class="icon">📋</span>
                    Como Configurar
                </h2>
                
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <div class="step-title">Gerar Token de API</div>
                        <div class="step-description">
                            Acesse <strong>Configurações > API & Tokens</strong> e gere um novo token.
                            Copie o token gerado (você só verá ele uma vez!).
                        </div>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <div class="step-title">Configurar URL no Personizi</div>
                        <div class="step-description">
                            No painel do Personizi, configure a URL base da API:<br>
                            <code style="background: #f8f9fa; padding: 5px 10px; border-radius: 4px; display: inline-block; margin-top: 5px;">
                                <?= htmlspecialchars($apiUrl) ?>
                            </code>
                        </div>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <div class="step-title">Colar Token no Personizi</div>
                        <div class="step-description">
                            Cole o token gerado no campo <strong>Token de API</strong> do Personizi.
                        </div>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <div class="step-title">Testar Conexão</div>
                        <div class="step-description">
                            Use o botão abaixo para testar se a conexão está funcionando corretamente.
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Teste de Conexão -->
            <div class="section">
                <h2 class="section-title">
                    <span class="icon">🧪</span>
                    Testar Endpoint
                </h2>
                
                <p style="margin-bottom: 20px; color: #666;">
                    Este teste verifica se o endpoint de contas WhatsApp está funcionando corretamente.
                </p>
                
                <button onclick="testEndpoint()" class="btn">
                    🚀 Testar Agora
                </button>
                
                <div id="testResult"></div>
            </div>
            
            <!-- Exemplo de Requisição -->
            <div class="section">
                <h2 class="section-title">
                    <span class="icon">💻</span>
                    Exemplo de Requisição
                </h2>
                
                <p style="margin-bottom: 20px; color: #666;">
                    Exemplo de como fazer uma requisição à API usando cURL:
                </p>
                
                <div class="code-block">
curl -X GET "<?= htmlspecialchars($apiUrl) ?>/whatsapp-accounts" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI" \
  -H "Accept: application/json"
                </div>
            </div>
            
            <!-- Documentação -->
            <div class="section">
                <h2 class="section-title">
                    <span class="icon">📚</span>
                    Documentação
                </h2>
                
                <p style="margin-bottom: 20px; color: #666;">
                    Para mais detalhes sobre a integração, consulte a documentação completa:
                </p>
                
                <a href="/INTEGRACAO_PERSONIZI.md" class="btn btn-secondary" download>
                    📥 Baixar Guia Completo
                </a>
                
                <a href="/api/README.md" class="btn btn-secondary" style="margin-left: 10px;" download>
                    📘 Documentação da API
                </a>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>Sistema de Chat Multiatendimento</strong></p>
            <p style="margin-top: 10px; font-size: 0.9em;">
                Última atualização: <?= date('d/m/Y H:i:s') ?>
            </p>
        </div>
    </div>
    
    <script>
        async function testEndpoint() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '🔄 Testando... <span class="loading"></span>';
            btn.disabled = true;
            
            const resultDiv = document.getElementById('testResult');
            resultDiv.style.display = 'block';
            resultDiv.className = 'status-box';
            resultDiv.innerHTML = '🔄 Testando conexão com a API...';
            
            try {
                const response = await fetch('<?= $apiUrl ?>/whatsapp-accounts', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    resultDiv.className = 'status-box success';
                    resultDiv.innerHTML = `
                        <strong>✅ Endpoint funcionando!</strong>
                        <p style="margin-top: 10px;">
                            Status: ${response.status} ${response.statusText}<br>
                            Contas encontradas: ${data.data?.accounts?.length || 0}
                        </p>
                        <details style="margin-top: 15px;">
                            <summary style="cursor: pointer; font-weight: bold;">Ver resposta completa</summary>
                            <pre style="margin-top: 10px; background: white; padding: 15px; border-radius: 6px; overflow-x: auto;">${JSON.stringify(data, null, 2)}</pre>
                        </details>
                    `;
                } else if (response.status === 401) {
                    resultDiv.className = 'status-box warning';
                    resultDiv.innerHTML = `
                        <strong>⚠️ Autenticação necessária</strong>
                        <p style="margin-top: 10px;">
                            O endpoint está funcionando, mas requer um token de API válido.<br>
                            <strong>Isso é esperado e correto!</strong>
                        </p>
                        <p style="margin-top: 10px;">
                            Configure o token no Personizi para completar a integração.
                        </p>
                    `;
                } else {
                    resultDiv.className = 'status-box error';
                    resultDiv.innerHTML = `
                        <strong>❌ Erro na requisição</strong>
                        <p style="margin-top: 10px;">
                            Status: ${response.status} ${response.statusText}<br>
                            Mensagem: ${data.message || data.error?.message || 'Erro desconhecido'}
                        </p>
                    `;
                }
            } catch (error) {
                resultDiv.className = 'status-box error';
                resultDiv.innerHTML = `
                    <strong>❌ Erro na conexão</strong>
                    <p style="margin-top: 10px;">
                        Não foi possível conectar à API.<br>
                        Erro: ${error.message}
                    </p>
                `;
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
