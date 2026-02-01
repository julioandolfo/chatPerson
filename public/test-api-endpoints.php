<?php
/**
 * Teste de Endpoints da API
 * Acesse: https://chat.personizi.com.br/test-api-endpoints.php
 */

header('Content-Type: text/html; charset=utf-8');

// Configuração
$baseUrl = 'https://' . $_SERVER['HTTP_HOST'];
$apiUrl = $baseUrl . '/api/v1';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Endpoints - API Personizi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content { padding: 30px; }
        .endpoint {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .endpoint.success { border-left-color: #28a745; background: #d4edda; }
        .endpoint.error { border-left-color: #dc3545; background: #f8d7da; }
        .endpoint-title {
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 10px;
            color: #333;
        }
        .endpoint-url {
            font-family: 'Courier New', monospace;
            background: white;
            padding: 10px;
            border-radius: 6px;
            margin: 10px 0;
            word-break: break-all;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 10px;
        }
        .btn:hover { background: #5568d3; }
        .result {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            margin-top: 10px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            max-height: 300px;
            overflow: auto;
        }
        .loading { color: #667eea; }
        .success-text { color: #28a745; font-weight: bold; }
        .error-text { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Teste de Endpoints - API Personizi</h1>
            <p style="margin-top: 10px;">Verificação de disponibilidade dos endpoints</p>
        </div>
        
        <div class="content">
            <p style="margin-bottom: 20px; color: #666;">
                Esta página testa se os endpoints da API estão disponíveis no servidor de produção.
            </p>
            
            <!-- Endpoint 1: Listar Contas WhatsApp -->
            <div class="endpoint" id="endpoint-1">
                <div class="endpoint-title">1️⃣ GET /whatsapp-accounts</div>
                <div class="endpoint-url"><?= htmlspecialchars($apiUrl) ?>/whatsapp-accounts</div>
                <button class="btn" onclick="testEndpoint1()">🧪 Testar Agora</button>
                <div id="result-1" class="result" style="display:none;"></div>
            </div>
            
            <!-- Endpoint 2: Enviar Mensagem -->
            <div class="endpoint" id="endpoint-2">
                <div class="endpoint-title">2️⃣ POST /messages/send</div>
                <div class="endpoint-url"><?= htmlspecialchars($apiUrl) ?>/messages/send</div>
                <button class="btn" onclick="testEndpoint2()">🧪 Testar Agora</button>
                <div id="result-2" class="result" style="display:none;"></div>
            </div>
            
            <!-- Verificação de Arquivos -->
            <div class="endpoint" id="endpoint-3">
                <div class="endpoint-title">3️⃣ Verificação de Arquivos no Servidor</div>
                <button class="btn" onclick="testFiles()">📁 Verificar Arquivos</button>
                <div id="result-3" class="result" style="display:none;"></div>
            </div>
            
            <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
                <strong>⚠️ Importante:</strong> Se algum endpoint retornar erro 404, significa que o código não foi enviado para o servidor de produção ainda. Você precisa fazer o deploy/upload dos arquivos atualizados.
            </div>
        </div>
    </div>
    
    <script>
        async function testEndpoint1() {
            const resultDiv = document.getElementById('result-1');
            const endpoint = document.getElementById('endpoint-1');
            
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<span class="loading">⏳ Testando...</span>';
            endpoint.className = 'endpoint';
            
            try {
                const response = await fetch('<?= $apiUrl ?>/whatsapp-accounts', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (response.status === 401) {
                    endpoint.className = 'endpoint success';
                    resultDiv.innerHTML = `
                        <span class="success-text">✅ ENDPOINT EXISTE!</span><br><br>
                        Status: ${response.status} (Requer autenticação - isso é esperado)<br>
                        Resposta: ${JSON.stringify(data, null, 2)}
                    `;
                } else if (response.status === 404) {
                    endpoint.className = 'endpoint error';
                    resultDiv.innerHTML = `
                        <span class="error-text">❌ ENDPOINT NÃO ENCONTRADO</span><br><br>
                        Status: ${response.status}<br>
                        <strong>Problema:</strong> O código não foi enviado para produção ainda.<br>
                        <strong>Solução:</strong> Fazer deploy/upload dos arquivos atualizados.
                    `;
                } else if (response.ok) {
                    endpoint.className = 'endpoint success';
                    resultDiv.innerHTML = `
                        <span class="success-text">✅ FUNCIONANDO!</span><br><br>
                        Status: ${response.status}<br>
                        Resposta: ${JSON.stringify(data, null, 2)}
                    `;
                } else {
                    endpoint.className = 'endpoint error';
                    resultDiv.innerHTML = `
                        <span class="error-text">⚠️ ERRO</span><br><br>
                        Status: ${response.status}<br>
                        Resposta: ${JSON.stringify(data, null, 2)}
                    `;
                }
            } catch (error) {
                endpoint.className = 'endpoint error';
                resultDiv.innerHTML = `
                    <span class="error-text">❌ ERRO NA REQUISIÇÃO</span><br><br>
                    ${error.message}
                `;
            }
        }
        
        async function testEndpoint2() {
            const resultDiv = document.getElementById('result-2');
            const endpoint = document.getElementById('endpoint-2');
            
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<span class="loading">⏳ Testando...</span>';
            endpoint.className = 'endpoint';
            
            try {
                const response = await fetch('<?= $apiUrl ?>/messages/send', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        to: '5511999999999',
                        from: '5511916127354',
                        message: 'Teste'
                    })
                });
                
                const data = await response.json();
                
                if (response.status === 401) {
                    endpoint.className = 'endpoint success';
                    resultDiv.innerHTML = `
                        <span class="success-text">✅ ENDPOINT EXISTE!</span><br><br>
                        Status: ${response.status} (Requer autenticação - isso é esperado)<br>
                        Resposta: ${JSON.stringify(data, null, 2)}
                    `;
                } else if (response.status === 404) {
                    endpoint.className = 'endpoint error';
                    resultDiv.innerHTML = `
                        <span class="error-text">❌ ENDPOINT NÃO ENCONTRADO</span><br><br>
                        Status: ${response.status}<br>
                        <strong>Problema:</strong> O código não foi enviado para produção ainda.<br>
                        <strong>Solução:</strong> Fazer deploy/upload dos arquivos atualizados.
                    `;
                } else if (response.status === 422) {
                    endpoint.className = 'endpoint success';
                    resultDiv.innerHTML = `
                        <span class="success-text">✅ ENDPOINT EXISTE!</span><br><br>
                        Status: ${response.status} (Erro de validação - endpoint funciona)<br>
                        Resposta: ${JSON.stringify(data, null, 2)}
                    `;
                } else {
                    endpoint.className = 'endpoint';
                    resultDiv.innerHTML = `
                        Status: ${response.status}<br>
                        Resposta: ${JSON.stringify(data, null, 2)}
                    `;
                }
            } catch (error) {
                endpoint.className = 'endpoint error';
                resultDiv.innerHTML = `
                    <span class="error-text">❌ ERRO NA REQUISIÇÃO</span><br><br>
                    ${error.message}
                `;
            }
        }
        
        async function testFiles() {
            const resultDiv = document.getElementById('result-3');
            const endpoint = document.getElementById('endpoint-3');
            
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<span class="loading">⏳ Verificando arquivos...</span>';
            endpoint.className = 'endpoint';
            
            const files = [
                '/api/v1/routes.php',
                '/api/v1/Controllers/WhatsAppAccountsController.php',
                '/api/v1/Controllers/MessagesController.php'
            ];
            
            let html = '<strong>Verificação de Arquivos:</strong><br><br>';
            
            for (const file of files) {
                try {
                    const response = await fetch(file, { method: 'HEAD' });
                    const exists = response.status !== 404;
                    html += `${exists ? '✅' : '❌'} ${file} - ${exists ? 'Existe' : 'Não encontrado'}<br>`;
                } catch (error) {
                    html += `❌ ${file} - Erro ao verificar<br>`;
                }
            }
            
            resultDiv.innerHTML = html;
        }
    </script>
</body>
</html>
