<?php
/**
 * Script de Teste - PostgreSQL + pgvector
 * 
 * Execute este script para verificar se PostgreSQL + pgvector está configurado corretamente
 * 
 * Acesso: http://seu-dominio/test-postgres-pgvector.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste PostgreSQL + pgvector</title>
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
        .test-section {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .info {
            color: #17a2b8;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Teste PostgreSQL + pgvector</h1>
        
        <?php
        $tests = [];
        $allPassed = true;

        // Teste 1: Verificar configurações do sistema
        echo '<div class="test-section">';
        echo '<h2>1. Verificando Configurações do Sistema</h2>';
        
        try {
            $postgresSettings = \App\Services\PostgreSQLSettingsService::getSettings();
            
            echo "<p><span class='info'>ℹ️</span> Usando configurações do sistema (tabela settings)</p>";
            
            $requiredSettings = ['postgres_host', 'postgres_port', 'postgres_database', 'postgres_username', 'postgres_password'];
            $settingsVars = [];
            
            foreach ($requiredSettings as $key) {
                $value = $postgresSettings[$key] ?? null;
                if ($value !== null && $value !== '') {
                    $displayValue = ($key === 'postgres_password') ? str_repeat('*', strlen($value)) : $value;
                    $displayKey = str_replace('postgres_', '', $key);
                    echo "<p><span class='success'>✅</span> <code>{$displayKey}</code> = {$displayValue}</p>";
                    $settingsVars[$key] = $value;
                } else {
                    $displayKey = str_replace('postgres_', '', $key);
                    echo "<p><span class='error'>❌</span> <code>{$displayKey}</code> não configurado</p>";
                    $allPassed = false;
                }
            }
            
            // Verificar se está habilitado
            if (!empty($postgresSettings['postgres_enabled'])) {
                echo "<p><span class='success'>✅</span> PostgreSQL está <strong>habilitado</strong></p>";
            } else {
                echo "<p><span class='error'>❌</span> PostgreSQL está <strong>desabilitado</strong> nas configurações</p>";
                $allPassed = false;
            }
            
            $tests['settings'] = !empty($settingsVars) && !empty($postgresSettings['postgres_enabled']);
        } catch (\Exception $e) {
            echo "<p><span class='error'>❌</span> Erro ao buscar configurações: " . htmlspecialchars($e->getMessage()) . "</p>";
            $tests['settings'] = false;
            $allPassed = false;
        }
        echo '</div>';

        // Teste 2: Verificar extensão PHP PostgreSQL
        echo '<div class="test-section">';
        echo '<h2>2. Verificando Extensão PHP PostgreSQL</h2>';
        
        if (extension_loaded('pgsql')) {
            echo "<p><span class='success'>✅</span> Extensão <code>pgsql</code> está carregada</p>";
            echo "<p class='info'>Versão: " . phpversion('pgsql') . "</p>";
            $tests['php_ext'] = true;
        } else {
            echo "<p><span class='error'>❌</span> Extensão <code>pgsql</code> NÃO está carregada</p>";
            echo "<div class='warning'>";
            echo "<strong>Como instalar:</strong><br>";
            echo "Ubuntu/Debian: <code>sudo apt-get install php-pgsql php-pdo-pgsql</code><br>";
            echo "Ou adicione ao Dockerfile: <code>RUN docker-php-ext-install pgsql pdo_pgsql</code>";
            echo "</div>";
            $tests['php_ext'] = false;
            $allPassed = false;
        }
        echo '</div>';

        // Teste 3: Conectar ao PostgreSQL
        echo '<div class="test-section">';
        echo '<h2>3. Testando Conexão PostgreSQL</h2>';
        
        if ($tests['settings'] && $tests['php_ext']) {
            try {
                $conn = \App\Helpers\PostgreSQL::getConnection();
                
                echo "<p><span class='success'>✅</span> Conexão estabelecida com sucesso!</p>";
                
                $postgresSettings = \App\Services\PostgreSQLSettingsService::getSettings();
                echo "<p class='info'>Host: {$postgresSettings['postgres_host']}:{$postgresSettings['postgres_port']}</p>";
                echo "<p class='info'>Database: {$postgresSettings['postgres_database']}</p>";
                echo "<p class='info'>User: {$postgresSettings['postgres_username']}</p>";
                
                $pdo = $conn; // Para usar nos testes seguintes
                $tests['connection'] = true;
            } catch (\Exception $e) {
                echo "<p><span class='error'>❌</span> Erro ao conectar: " . htmlspecialchars($e->getMessage()) . "</p>";
                $tests['connection'] = false;
                $allPassed = false;
            }
        } else {
            echo "<p><span class='error'>❌</span> Não é possível testar conexão (configurações ou extensão faltando)</p>";
            $tests['connection'] = false;
        }
        echo '</div>';

        // Teste 4: Verificar extensão pgvector
        echo '<div class="test-section">';
        echo '<h2>4. Verificando Extensão pgvector</h2>';
        
        if (isset($pdo) && $tests['connection']) {
            try {
                $stmt = $pdo->query("SELECT * FROM pg_extension WHERE extname = 'vector'");
                $result = $stmt->fetch();
                
                if ($result) {
                    echo "<p><span class='success'>✅</span> Extensão <code>pgvector</code> está instalada!</p>";
                    echo "<pre>";
                    print_r($result);
                    echo "</pre>";
                    
                    // Verificar versão
                    $versionStmt = $pdo->query("SELECT extversion FROM pg_extension WHERE extname = 'vector'");
                    $version = $versionStmt->fetchColumn();
                    echo "<p class='info'>Versão: {$version}</p>";
                    
                    $tests['pgvector'] = true;
                } else {
                    echo "<p><span class='error'>❌</span> Extensão <code>pgvector</code> NÃO está instalada</p>";
                    echo "<div class='warning'>";
                    echo "<strong>Como instalar:</strong><br>";
                    echo "Conecte ao banco como superuser e execute:<br>";
                    echo "<code>CREATE EXTENSION vector;</code>";
                    echo "</div>";
                    $tests['pgvector'] = false;
                    $allPassed = false;
                }
            } catch (PDOException $e) {
                echo "<p><span class='error'>❌</span> Erro ao verificar extensão: " . htmlspecialchars($e->getMessage()) . "</p>";
                $tests['pgvector'] = false;
                $allPassed = false;
            }
        } else {
            echo "<p><span class='error'>❌</span> Não é possível testar (sem conexão)</p>";
            $tests['pgvector'] = false;
        }
        echo '</div>';

        // Teste 5: Testar criação de tabela com vector
        echo '<div class="test-section">';
        echo '<h2>5. Testando Tabela com Vector</h2>';
        
        if (isset($pdo) && $tests['pgvector']) {
            try {
                // Criar tabela de teste
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS test_vectors_pgvector (
                        id SERIAL PRIMARY KEY,
                        text_content TEXT,
                        embedding vector(1536)
                    )
                ");
                
                echo "<p><span class='success'>✅</span> Tabela de teste criada com sucesso!</p>";
                
                // Inserir teste
                $testEmbedding = json_encode(array_fill(0, 1536, 0.1)); // Embedding de exemplo
                $stmt = $pdo->prepare("INSERT INTO test_vectors_pgvector (text_content, embedding) VALUES (?, ?::vector)");
                $stmt->execute(['Teste de embedding', $testEmbedding]);
                
                echo "<p><span class='success'>✅</span> Dados de teste inseridos!</p>";
                
                // Buscar
                $stmt = $pdo->query("SELECT id, text_content, embedding::text as embedding_text FROM test_vectors_pgvector LIMIT 1");
                $result = $stmt->fetch();
                
                if ($result) {
                    echo "<p><span class='success'>✅</span> Dados recuperados com sucesso!</p>";
                    echo "<pre>";
                    echo "ID: " . $result['id'] . "\n";
                    echo "Texto: " . $result['text_content'] . "\n";
                    echo "Embedding (primeiros 50 chars): " . substr($result['embedding_text'], 0, 50) . "...\n";
                    echo "</pre>";
                }
                
                // Testar busca por similaridade
                $stmt = $pdo->prepare("
                    SELECT id, text_content, 
                           1 - (embedding <=> ?::vector) as similarity
                    FROM test_vectors_pgvector
                    ORDER BY embedding <=> ?::vector
                    LIMIT 1
                ");
                $stmt->execute([$testEmbedding, $testEmbedding]);
                $similarityResult = $stmt->fetch();
                
                if ($similarityResult) {
                    echo "<p><span class='success'>✅</span> Busca por similaridade funcionando!</p>";
                    echo "<p class='info'>Similaridade: " . number_format($similarityResult['similarity'], 4) . "</p>";
                }
                
                // Limpar
                $pdo->exec("DROP TABLE test_vectors_pgvector");
                echo "<p><span class='success'>✅</span> Tabela de teste removida</p>";
                
                $tests['vector_table'] = true;
            } catch (PDOException $e) {
                echo "<p><span class='error'>❌</span> Erro ao testar tabela: " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
                $tests['vector_table'] = false;
                $allPassed = false;
            }
        } else {
            echo "<p><span class='error'>❌</span> Não é possível testar (pgvector não disponível)</p>";
            $tests['vector_table'] = false;
        }
        echo '</div>';

        // Teste 6: Verificar Helper PostgreSQL (se existir)
        echo '<div class="test-section">';
        echo '<h2>6. Verificando Helper PostgreSQL</h2>';
        
        if (class_exists('App\Helpers\PostgreSQL')) {
            try {
                $conn = \App\Helpers\PostgreSQL::getConnection();
                echo "<p><span class='success'>✅</span> Helper <code>PostgreSQL</code> está funcionando!</p>";
                
                // Testar query
                $result = \App\Helpers\PostgreSQL::query("SELECT version()");
                if ($result) {
                    echo "<p><span class='success'>✅</span> Método <code>query()</code> funcionando</p>";
                    echo "<p class='info'>Versão PostgreSQL: " . $result[0]['version'] . "</p>";
                }
                
                $tests['helper'] = true;
            } catch (\Exception $e) {
                echo "<p><span class='error'>❌</span> Erro no helper: " . htmlspecialchars($e->getMessage()) . "</p>";
                $tests['helper'] = false;
                $allPassed = false;
            }
        } else {
            echo "<p class='info'>⚠️ Helper <code>PostgreSQL</code> não encontrado (opcional)</p>";
            echo "<p class='info'>Crie o helper conforme documentado em <code>GUIA_INSTALACAO_POSTGRES_PGVECTOR_COOLIFY.md</code></p>";
            $tests['helper'] = null; // Não é obrigatório
        }
        echo '</div>';

        // Resumo final
        echo '<div class="test-section" style="border-left-color: ' . ($allPassed ? '#28a745' : '#dc3545') . ';">';
        echo '<h2>📊 Resumo dos Testes</h2>';
        
        $testNames = [
            'settings' => 'Configurações do Sistema',
            'php_ext' => 'Extensão PHP PostgreSQL',
            'connection' => 'Conexão PostgreSQL',
            'pgvector' => 'Extensão pgvector',
            'vector_table' => 'Tabela com Vector',
            'helper' => 'Helper PostgreSQL (opcional)',
        ];
        
        foreach ($testNames as $key => $name) {
            $status = $tests[$key] ?? null;
            if ($status === true) {
                echo "<p><span class='success'>✅</span> {$name}</p>";
            } elseif ($status === false) {
                echo "<p><span class='error'>❌</span> {$name}</p>";
            } else {
                echo "<p class='info'>⚠️ {$name} (não testado)</p>";
            }
        }
        
        if ($allPassed) {
            echo "<h3 style='color: #28a745;'>🎉 Todos os testes passaram! PostgreSQL + pgvector está configurado corretamente!</h3>";
        } else {
            echo "<h3 style='color: #dc3545;'>⚠️ Alguns testes falharam. Verifique os erros acima.</h3>";
        }
        echo '</div>';

        // Informações adicionais
        echo '<div class="test-section">';
        echo '<h2>ℹ️ Informações Adicionais</h2>';
        echo '<p><strong>PHP Version:</strong> ' . PHP_VERSION . '</p>';
        echo '<p><strong>PDO Drivers:</strong> ' . implode(', ', PDO::getAvailableDrivers()) . '</p>';
        echo '<p><strong>Server:</strong> ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Desconhecido') . '</p>';
        echo '</div>';
        ?>
    </div>
</body>
</html>

