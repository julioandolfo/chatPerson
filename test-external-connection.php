<?php
/**
 * Script de Teste - Conexão Externa
 * Teste direto sem passar pelo sistema
 */

echo "<h1>🧪 Teste de Conexão Externa</h1>";
echo "<pre>";

// CONFIGURE AQUI OS DADOS DA SUA CONEXÃO
$config = [
    'type' => 'mysql',  // mysql ou postgresql
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'seu_banco_aqui',
    'username' => 'root',
    'password' => ''
];

echo "=== CONFIGURAÇÃO ===\n";
echo "Tipo: {$config['type']}\n";
echo "Host: {$config['host']}\n";
echo "Porta: {$config['port']}\n";
echo "Banco: {$config['database']}\n";
echo "Usuário: {$config['username']}\n";
echo "Senha: " . (empty($config['password']) ? 'VAZIA' : '***DEFINIDA***') . "\n\n";

echo "=== CHECKLIST ===\n";

// 1. Verificar extensão PDO
echo "1. PDO instalado? ";
if (extension_loaded('pdo')) {
    echo "✅ SIM\n";
} else {
    echo "❌ NÃO - INSTALE A EXTENSÃO PDO!\n";
    exit;
}

// 2. Verificar driver específico
$driver = $config['type'] === 'mysql' ? 'pdo_mysql' : 'pdo_pgsql';
echo "2. Driver {$driver}? ";
if (extension_loaded($driver)) {
    echo "✅ SIM\n";
} else {
    echo "❌ NÃO - INSTALE A EXTENSÃO {$driver}!\n";
    echo "\nNo php.ini, descomente:\n";
    echo "extension={$driver}\n";
    exit;
}

// 3. Testar conexão
echo "\n=== TENTANDO CONECTAR ===\n";

try {
    $driverMap = [
        'mysql' => 'mysql',
        'postgresql' => 'pgsql'
    ];
    
    $driver = $driverMap[$config['type']];
    $dsn = "{$driver}:host={$config['host']};port={$config['port']};dbname={$config['database']}";
    
    if ($config['type'] === 'mysql') {
        $dsn .= ';charset=utf8mb4';
    }
    
    echo "DSN: {$dsn}\n";
    echo "Criando PDO...\n";
    
    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5
        ]
    );
    
    echo "✅ PDO criado com sucesso!\n\n";
    
    echo "Executando SELECT 1...\n";
    $result = $pdo->query("SELECT 1 as test")->fetch();
    
    if ($result && $result['test'] == 1) {
        echo "✅ Query executada com sucesso!\n\n";
        
        echo "=== CONEXÃO FUNCIONAL ===\n";
        echo "🎉 A conexão está funcionando perfeitamente!\n\n";
        
        // Testar SHOW TABLES
        if ($config['type'] === 'mysql') {
            echo "=== LISTANDO TABELAS ===\n";
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_NUM);
            
            if (count($tables) > 0) {
                echo "Tabelas encontradas (" . count($tables) . "):\n";
                foreach ($tables as $table) {
                    echo "  - {$table[0]}\n";
                }
            } else {
                echo "⚠️ Nenhuma tabela encontrada no banco\n";
            }
        }
        
    } else {
        echo "❌ Query retornou resultado inesperado\n";
        var_dump($result);
    }
    
} catch (PDOException $e) {
    echo "\n❌ ERRO DE CONEXÃO PDO:\n";
    echo "Código: " . $e->getCode() . "\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n\n";
    
    echo "=== POSSÍVEIS CAUSAS ===\n";
    
    $errorCode = $e->getCode();
    $errorMsg = $e->getMessage();
    
    if (strpos($errorMsg, 'Unknown database') !== false) {
        echo "❌ Banco de dados não existe!\n";
        echo "Solução: CREATE DATABASE {$config['database']};\n";
    } elseif (strpos($errorMsg, 'Access denied') !== false) {
        echo "❌ Usuário/senha incorretos ou sem permissões!\n";
        echo "Solução:\n";
        echo "  CREATE USER '{$config['username']}'@'%' IDENTIFIED BY 'senha';\n";
        echo "  GRANT ALL ON {$config['database']}.* TO '{$config['username']}'@'%';\n";
        echo "  FLUSH PRIVILEGES;\n";
    } elseif (strpos($errorMsg, 'Connection refused') !== false) {
        echo "❌ Servidor recusou conexão!\n";
        echo "Possíveis causas:\n";
        echo "  - MySQL/PostgreSQL não está rodando\n";
        echo "  - Firewall bloqueando porta {$config['port']}\n";
        echo "  - Porta incorreta\n";
    } elseif (strpos($errorMsg, 'getaddrinfo failed') !== false || strpos($errorMsg, 'Name or service not known') !== false) {
        echo "❌ Host não encontrado!\n";
        echo "Possíveis causas:\n";
        echo "  - IP/hostname incorreto\n";
        echo "  - Servidor inacessível\n";
        echo "  - Problemas de DNS\n";
    } elseif (strpos($errorMsg, 'could not find driver') !== false) {
        echo "❌ Driver PDO não instalado!\n";
        echo "Solução:\n";
        echo "  1. Editar php.ini\n";
        echo "  2. Descomentar: extension={$driver}\n";
        echo "  3. Reiniciar servidor web\n";
    } else {
        echo "⚠️ Erro desconhecido - veja detalhes acima\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERRO GERAL:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== FIM DO TESTE ===\n";
echo "</pre>";

echo "<hr>";
echo "<h2>📋 Checklist Final</h2>";
echo "<ul>";
echo "<li>✅ Servidor MySQL/PostgreSQL está rodando?</li>";
echo "<li>✅ Banco de dados existe?</li>";
echo "<li>✅ Usuário tem permissões?</li>";
echo "<li>✅ Senha está correta?</li>";
echo "<li>✅ Host/IP está correto?</li>";
echo "<li>✅ Porta está correta?</li>";
echo "<li>✅ Firewall está liberado?</li>";
echo "<li>✅ Extensão PDO está instalada?</li>";
echo "</ul>";

echo "<h2>🔗 Links Úteis</h2>";
echo "<ul>";
echo "<li><a href='view-all-logs.php' target='_blank'>📄 Ver Logs do Sistema</a></li>";
echo "<li><a href='external-sources/create' target='_blank'>🔙 Voltar para Configuração</a></li>";
echo "</ul>";
?>
