<?php
/**
 * Script para executar migrations do sistema RAG (PostgreSQL)
 * 
 * ATENÇÃO: Este script cria tabelas no PostgreSQL, não no MySQL!
 * 
 * Acesse: http://seu-dominio.com/run-rag-migrations.php
 * Ou execute: php public/run-rag-migrations.php
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executar Migrations RAG</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .migration {
            margin: 15px 0;
            padding: 15px;
            border-left: 4px solid #007bff;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            margin: 10px 0;
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .alert-info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
        }
        .alert-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .alert-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Executar Migrations do Sistema RAG</h1>
        
        <?php
        // Verificar se PostgreSQL está habilitado
        try {
            if (!\App\Services\PostgreSQLSettingsService::isEnabled()) {
                echo '<div class="alert alert-warning">';
                echo '<strong>⚠️ PostgreSQL não está habilitado!</strong><br>';
                echo 'Acesse <a href="/settings?tab=postgres">Configurações → PostgreSQL</a> e habilite o PostgreSQL primeiro.';
                echo '</div>';
                exit;
            }
            
            // Testar conexão
            $conn = \App\Helpers\PostgreSQL::getConnection();
            echo '<div class="alert alert-success">';
            echo '<strong>✅ PostgreSQL conectado com sucesso!</strong><br>';
            echo 'Pronto para executar migrations do sistema RAG.';
            echo '</div>';
            
        } catch (\Exception $e) {
            echo '<div class="alert alert-warning">';
            echo '<strong>❌ Erro ao conectar PostgreSQL:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '<br><br>Acesse <a href="/settings?tab=postgres">Configurações → PostgreSQL</a> para configurar.';
            echo '</div>';
            exit;
        }
        
        // Migrations a executar
        $migrations = [
            '060_create_ai_knowledge_base_table.php',
            '061_create_ai_feedback_loop_table.php',
            '062_create_ai_url_scraping_table.php',
            '063_create_ai_agent_memory_table.php'
        ];
        
        echo '<h2>📋 Migrations a executar:</h2>';
        echo '<ul>';
        foreach ($migrations as $migration) {
            echo '<li>' . htmlspecialchars($migration) . '</li>';
        }
        echo '</ul>';
        
        echo '<div class="alert alert-info">';
        echo '<strong>ℹ️ Informação:</strong><br>';
        echo 'Estas migrations criam tabelas no <strong>PostgreSQL</strong>, não no MySQL.';
        echo 'Certifique-se de que o PostgreSQL está configurado corretamente antes de continuar.';
        echo '</div>';
        
        // Verificar se foi solicitado para executar
        $execute = $_GET['execute'] ?? false;
        
        if ($execute) {
            echo '<h2>🔄 Executando Migrations...</h2>';
            
            $allSuccess = true;
            
            foreach ($migrations as $migration) {
                $migrationPath = __DIR__ . '/../database/migrations/' . $migration;
                
                echo '<div class="migration">';
                echo '<strong>📄 ' . htmlspecialchars($migration) . '</strong><br>';
                
                if (!file_exists($migrationPath)) {
                    echo '<span class="error">❌ Arquivo não encontrado!</span>';
                    $allSuccess = false;
                    echo '</div>';
                    continue;
                }
                
                // Capturar output
                ob_start();
                
                try {
                    require $migrationPath;
                    
                    // Extrair nome da função
                    // Remove número inicial (ex: 060_)
                    $functionName = preg_replace('/^\d+_/', '', $migration);
                    // Remove extensão .php
                    $functionName = str_replace('.php', '', $functionName);
                    // Remove prefixo create_ se existir
                    $functionName = preg_replace('/^create_/', '', $functionName);
                    // Adiciona prefixo up_
                    $functionName = 'up_' . $functionName;
                    
                    if (function_exists($functionName)) {
                        $functionName();
                        $output = ob_get_clean();
                        
                        if (!empty($output)) {
                            echo '<pre>' . htmlspecialchars($output) . '</pre>';
                        }
                        
                        echo '<span class="success">✅ Migration executada com sucesso!</span>';
                    } else {
                        $output = ob_get_clean();
                        echo '<span class="error">❌ Função "' . htmlspecialchars($functionName) . '" não encontrada</span>';
                        
                        // Debug: mostrar funções disponíveis que começam com "up_"
                        $allFunctions = get_defined_functions()['user'];
                        $upFunctions = array_filter($allFunctions, function($fn) {
                            return strpos($fn, 'up_') === 0;
                        });
                        
                        if (!empty($upFunctions)) {
                            echo '<br><small class="info">💡 Funções disponíveis que começam com "up_": ' . implode(', ', array_slice($upFunctions, 0, 10)) . '</small>';
                        }
                        
                        $allSuccess = false;
                    }
                    
                } catch (\Exception $e) {
                    $output = ob_get_clean();
                    echo '<pre class="error">' . htmlspecialchars($e->getMessage()) . '</pre>';
                    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                    $allSuccess = false;
                }
                
                echo '</div>';
            }
            
            echo '<div class="alert ' . ($allSuccess ? 'alert-success' : 'alert-warning') . '">';
            if ($allSuccess) {
                echo '<strong>🎉 Todas as migrations foram executadas com sucesso!</strong><br>';
                echo 'As tabelas do sistema RAG foram criadas no PostgreSQL.';
            } else {
                echo '<strong>⚠️ Algumas migrations falharam.</strong><br>';
                echo 'Verifique os erros acima e tente novamente.';
            }
            echo '</div>';
            
        } else {
            echo '<div style="margin-top: 30px; text-align: center;">';
            echo '<a href="?execute=1" style="display: inline-block; padding: 15px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">';
            echo '▶️ Executar Migrations';
            echo '</a>';
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h3>📚 Próximos Passos:</h3>
            <ol>
                <li>✅ Executar migrations (você está aqui)</li>
                <li>Criar Models básicos (AIKnowledgeBase, AIFeedbackLoop, etc)</li>
                <li>Criar RAGService com busca semântica</li>
                <li>Criar EmbeddingService para gerar embeddings</li>
                <li>Integrar RAG no OpenAIService</li>
            </ol>
            <p><strong>Ver documentação completa:</strong> <code>PROXIMOS_PASSOS_RAG.md</code></p>
        </div>
    </div>
</body>
</html>

