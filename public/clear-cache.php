<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpar Cache do Navegador</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 600px;
            margin: 100px auto;
            padding: 20px;
            text-align: center;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        h1 {
            color: #009ef7;
            margin-top: 0;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #009ef7;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 10px;
            font-size: 16px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #0077b6;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,158,247,0.4);
        }
        .btn-success {
            background: #22c55e;
        }
        .btn-success:hover {
            background: #16a34a;
        }
        .instructions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        .instructions h3 {
            margin-top: 0;
            color: #495057;
        }
        .instructions ol {
            line-height: 1.8;
        }
        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            display: none;
        }
        code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            color: #e83e8c;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🧹 Limpar Cache do Navegador</h1>
        <p>Se você está vendo erros como <code>reorderStage is not defined</code>, isso significa que o navegador está usando uma versão antiga dos arquivos JavaScript.</p>
        
        <div class="instructions">
            <h3>📋 Instruções para Limpar o Cache:</h3>
            <ol>
                <li><strong>Windows/Linux:</strong> Pressione <code>Ctrl + Shift + R</code> ou <code>Ctrl + F5</code></li>
                <li><strong>Mac:</strong> Pressione <code>Cmd + Shift + R</code></li>
                <li>Ou abra as Ferramentas do Desenvolvedor (F12), clique com botão direito no botão de reload e escolha "Limpar cache e recarregar"</li>
            </ol>
        </div>

        <div class="success-message" id="successMessage">
            ✅ Cache limpo! Redirecionando para o Kanban...
        </div>

        <button class="btn" onclick="hardReload()">🔄 Limpar Cache Agora</button>
        <a href="/funnels/kanban" class="btn btn-success">📊 Ir para Kanban</a>

        <script>
            function hardReload() {
                // Mostrar mensagem de sucesso
                document.getElementById('successMessage').style.display = 'block';
                
                // Limpar todos os caches possíveis
                if ('caches' in window) {
                    caches.keys().then(function(names) {
                        for (let name of names) {
                            caches.delete(name);
                        }
                    });
                }
                
                // Adicionar timestamp aos assets para forçar reload
                const timestamp = new Date().getTime();
                localStorage.setItem('cache_clear_timestamp', timestamp);
                
                // Redirecionar após 1 segundo
                setTimeout(function() {
                    window.location.href = '/funnels/kanban?_=' + timestamp;
                }, 1000);
            }
        </script>
    </div>

    <div style="margin-top: 30px; color: #666; font-size: 14px;">
        <p><strong>Ainda com problemas?</strong></p>
        <p>Tente abrir o Kanban em uma aba anônima/privada do navegador:</p>
        <p><code>Ctrl + Shift + N</code> (Chrome) ou <code>Ctrl + Shift + P</code> (Firefox)</p>
    </div>
</body>
</html>

