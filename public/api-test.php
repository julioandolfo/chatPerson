<?php
/**
 * Teste Simples da API Standalone
 * Acesse: https://chat.personizi.com.br/api-test.php
 */

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
         . '://' . $_SERVER['HTTP_HOST'];

// Header
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste da API</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #28a745; padding-bottom: 15px; }
        .status { padding: 15px; border-radius: 8px; margin: 10px 0; }
        .ok { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .info { background: #cce5ff; color: #004085; }
        code { background: #e9ecef; padding: 3px 8px; border-radius: 4px; font-size: 14px; }
        .url { font-family: monospace; background: #2d2d2d; color: #fff; padding: 15px; border-radius: 8px; margin: 10px 0; word-break: break-all; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .badge-get { background: #007bff; color: white; }
        .badge-post { background: #28a745; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Teste da API Standalone</h1>
        
        <?php
        // Verificar se o arquivo api.php existe
        $apiFile = __DIR__ . '/api.php';
        if (file_exists($apiFile)):
        ?>
        <div class="status ok">
            <strong>API Gateway:</strong> Arquivo <code>api.php</code> encontrado
        </div>
        
        <div class="status info">
            <strong>URL Base da API:</strong>
            <div class="url"><?= $baseUrl ?>/api.php</div>
        </div>
        
        <h2>Endpoints Disponiveis</h2>
        <table>
            <thead>
                <tr>
                    <th>Metodo</th>
                    <th>Endpoint</th>
                    <th>Descricao</th>
                    <th>URL Completa</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="badge badge-get">GET</span></td>
                    <td><code>/</code></td>
                    <td>Info da API</td>
                    <td><a href="<?= $baseUrl ?>/api.php" target="_blank"><?= $baseUrl ?>/api.php</a></td>
                </tr>
                <tr>
                    <td><span class="badge badge-post">POST</span></td>
                    <td><code>/auth/login</code></td>
                    <td>Autenticacao</td>
                    <td><?= $baseUrl ?>/api.php/auth/login</td>
                </tr>
                <tr>
                    <td><span class="badge badge-get">GET</span></td>
                    <td><code>/whatsapp-accounts</code></td>
                    <td>Listar contas WhatsApp</td>
                    <td><?= $baseUrl ?>/api.php/whatsapp-accounts</td>
                </tr>
                <tr>
                    <td><span class="badge badge-post">POST</span></td>
                    <td><code>/messages/send</code></td>
                    <td>Enviar mensagem WhatsApp</td>
                    <td><?= $baseUrl ?>/api.php/messages/send</td>
                </tr>
                <tr>
                    <td><span class="badge badge-get">GET</span></td>
                    <td><code>/conversations</code></td>
                    <td>Listar conversas</td>
                    <td><?= $baseUrl ?>/api.php/conversations</td>
                </tr>
                <tr>
                    <td><span class="badge badge-get">GET</span></td>
                    <td><code>/contacts</code></td>
                    <td>Listar contatos</td>
                    <td><?= $baseUrl ?>/api.php/contacts</td>
                </tr>
                <tr>
                    <td><span class="badge badge-get">GET</span></td>
                    <td><code>/agents</code></td>
                    <td>Listar agentes</td>
                    <td><?= $baseUrl ?>/api.php/agents</td>
                </tr>
                <tr>
                    <td><span class="badge badge-get">GET</span></td>
                    <td><code>/departments</code></td>
                    <td>Listar setores</td>
                    <td><?= $baseUrl ?>/api.php/departments</td>
                </tr>
                <tr>
                    <td><span class="badge badge-get">GET</span></td>
                    <td><code>/funnels</code></td>
                    <td>Listar funis</td>
                    <td><?= $baseUrl ?>/api.php/funnels</td>
                </tr>
                <tr>
                    <td><span class="badge badge-get">GET</span></td>
                    <td><code>/tags</code></td>
                    <td>Listar tags</td>
                    <td><?= $baseUrl ?>/api.php/tags</td>
                </tr>
            </tbody>
        </table>
        
        <h2>Exemplo de Uso (cURL)</h2>
        <pre style="background: #2d2d2d; color: #fff; padding: 15px; border-radius: 8px; overflow-x: auto;">
# Listar contas WhatsApp
curl -X GET "<?= $baseUrl ?>/api.php/whatsapp-accounts" \
  -H "Authorization: Bearer SEU_TOKEN"

# Enviar mensagem
curl -X POST "<?= $baseUrl ?>/api.php/messages/send" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "5511999998888",
    "from": "5535991970289",
    "message": "Ola! Mensagem de teste."
  }'
        </pre>
        
        <h2>Teste Rapido</h2>
        <p>Clique no botao abaixo para testar se a API esta respondendo:</p>
        <button onclick="testApi()" style="padding: 10px 20px; font-size: 16px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 5px;">
            Testar API
        </button>
        <div id="result" style="margin-top: 15px;"></div>
        
        <script>
        function testApi() {
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '<div class="status info">Testando...</div>';
            
            fetch('<?= $baseUrl ?>/api.php')
                .then(response => response.json())
                .then(data => {
                    resultDiv.innerHTML = '<div class="status ok"><strong>Sucesso!</strong><br><pre>' + JSON.stringify(data, null, 2) + '</pre></div>';
                })
                .catch(error => {
                    resultDiv.innerHTML = '<div class="status error"><strong>Erro:</strong> ' + error.message + '</div>';
                });
        }
        </script>
        
        <?php else: ?>
        <div class="status error">
            <strong>Erro:</strong> Arquivo <code>api.php</code> nao encontrado!
        </div>
        <?php endif; ?>
        
        <hr style="margin-top: 30px;">
        <p style="color: #666; font-size: 14px;">
            Documentacao completa: <a href="<?= $baseUrl ?>/settings/api-tokens/docs"><?= $baseUrl ?>/settings/api-tokens/docs</a>
        </p>
    </div>
</body>
</html>
