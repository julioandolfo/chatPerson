<?php
/**
 * Script de diagnóstico para verificar telefones de contatos (STANDALONE)
 * 
 * Uso via navegador: http://localhost/chat/public/debug-contact-phone.php
 * Uso via CLI: php public/debug-contact-phone.php
 */

// Garantir que estamos no diretório correto
$rootDir = dirname(__DIR__);
chdir($rootDir);

// Carregar bootstrap (que já tem o autoloader)
require_once $rootDir . '/config/bootstrap.php';

// Se for via navegador, definir header
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
}

echo "<h1>Diagnóstico de Telefones de Contatos</h1>";

// Buscar contatos com o telefone que termina em 91970289
$db = \App\Helpers\Database::getInstance();
$contacts = $db->query("SELECT id, name, phone, whatsapp_id, created_at FROM contacts WHERE phone LIKE '%91970289%' OR whatsapp_id LIKE '%91970289%' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Contatos encontrados com '91970289':</h2>";
echo "<pre>";
foreach ($contacts as $contact) {
    echo "ID: {$contact['id']}\n";
    echo "Nome: {$contact['name']}\n";
    echo "Phone: '{$contact['phone']}' (len: " . strlen($contact['phone']) . ")\n";
    echo "WhatsApp ID: '{$contact['whatsapp_id']}' (len: " . strlen($contact['whatsapp_id'] ?? '') . ")\n";
    echo "Criado em: {$contact['created_at']}\n";
    echo "---\n";
}
echo "</pre>";

// Testar a função de busca
echo "<h2>Testando findByPhoneNormalized:</h2>";

$testPhones = [
    '5535991970289',
    '553591970289',
    '35991970289',
    '3591970289',
    '991970289',
    '91970289'
];

foreach ($testPhones as $phone) {
    echo "<p>Buscando '{$phone}'... ";
    $result = \App\Models\Contact::findByPhoneNormalized($phone);
    if ($result) {
        echo "<strong style='color: green;'>ENCONTRADO: ID {$result['id']} - {$result['name']}</strong>";
    } else {
        echo "<strong style='color: red;'>NÃO ENCONTRADO</strong>";
    }
    echo "</p>";
}

echo "<h2>Testando fluxo completo de criação (simulação):</h2>";

// Simular exatamente o que o newConversation faz
$testPhone = '35991970289'; // Como seria digitado no formulário
$testName = 'Teste Novo Nome';

echo "<p><strong>Simulando criação com telefone '{$testPhone}' e nome '{$testName}':</strong></p>";

// 1. Normalização do frontend (JavaScript)
$normalizedPhone = preg_replace('/\D/', '', $testPhone);
if (strpos($normalizedPhone, '55') === 0 && strlen($normalizedPhone) > 11) {
    $normalizedPhone = substr($normalizedPhone, 2);
}
$fullPhoneFrontend = '55' . $normalizedPhone;
echo "<p>1. Frontend normaliza: '{$testPhone}' → '{$fullPhoneFrontend}'</p>";

// 2. Backend normalização (ConversationController)
$phone = $fullPhoneFrontend;
$phone = preg_replace('/^\+?55/', '', $phone); // Remove +55 do início
$phone = preg_replace('/\D/', '', $phone); // Remove caracteres não numéricos
$fullPhoneBackend = '55' . $phone;
echo "<p>2. Backend normaliza: '{$fullPhoneFrontend}' → '{$fullPhoneBackend}'</p>";

// 3. ContactService::createOrUpdate normaliza
$normalizedByContact = \App\Models\Contact::normalizePhoneNumber($fullPhoneBackend);
echo "<p>3. Contact::normalizePhoneNumber: '{$fullPhoneBackend}' → '{$normalizedByContact}'</p>";

// 4. Testar a busca que o ContactService faria
echo "<p>4. Contact::findByPhoneNormalized('{$normalizedByContact}')... ";
$found = \App\Models\Contact::findByPhoneNormalized($normalizedByContact);
if ($found) {
    echo "<strong style='color: green;'>ENCONTRADO: ID {$found['id']} - {$found['name']}</strong>";
    echo "<br>&nbsp;&nbsp;&nbsp;→ O sistema DEVERIA usar este contato existente";
} else {
    echo "<strong style='color: red;'>NÃO ENCONTRADO - Sistema criaria novo contato!</strong>";
}
echo "</p>";

// 5. Testar com o ContactService diretamente (sem salvar)
echo "<h3>Debug do ContactService:</h3>";
echo "<pre>";
$contactData = [
    'name' => $testName,
    'phone' => $fullPhoneBackend
];
echo "Dados que seriam passados para ContactService::createOrUpdate:\n";
print_r($contactData);
echo "\n";

// Verificar normalização
$normalizedPhone = \App\Models\Contact::normalizePhoneNumber($contactData['phone']);
echo "Telefone após normalização: '{$normalizedPhone}'\n";

// Verificar busca
$existing = \App\Models\Contact::findByPhoneNormalized($normalizedPhone);
if ($existing) {
    echo "✅ Contato existente encontrado:\n";
    echo "   ID: {$existing['id']}\n";
    echo "   Nome: {$existing['name']}\n";
    echo "   Phone: {$existing['phone']}\n";
} else {
    echo "❌ Nenhum contato existente - seria criado novo\n";
}
echo "</pre>";

echo "<h2>Verificar logs em:</h2>";

// Testar se o Logger está funcionando
$logDir = dirname(__DIR__) . '/logs';
echo "<p>Diretório de logs: <code>{$logDir}</code></p>";
echo "<p>Existe? " . (is_dir($logDir) ? '<span style="color:green">SIM</span>' : '<span style="color:red">NÃO</span>') . "</p>";
echo "<p>Gravável? " . (is_writable($logDir) ? '<span style="color:green">SIM</span>' : '<span style="color:red">NÃO</span>') . "</p>";

// Tentar gravar um log de teste
$testLogFile = $logDir . '/test-debug.log';
$testContent = "[" . date('Y-m-d H:i:s') . "] Teste de gravação de log\n";
$writeResult = @file_put_contents($testLogFile, $testContent, FILE_APPEND);
echo "<p>Teste de gravação: " . ($writeResult ? '<span style="color:green">SUCESSO (' . $writeResult . ' bytes)</span>' : '<span style="color:red">FALHA</span>') . "</p>";

// Listar arquivos de log existentes
echo "<p>Arquivos de log existentes:</p>";
$files = @scandir($logDir);
echo "<pre>";
if ($files) {
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..') {
            $size = @filesize($logDir . '/' . $f);
            echo "  {$f} ({$size} bytes)\n";
        }
    }
} else {
    echo "  (não foi possível listar)\n";
}
echo "</pre>";

// Verificar se o problema está na ordem do ID
echo "<h2>Verificar ordem dos contatos:</h2>";
$allWithPhone = $db->query("SELECT id, name, phone, whatsapp_id FROM contacts WHERE phone IS NOT NULL AND phone != '' ORDER BY id ASC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
foreach ($allWithPhone as $c) {
    $contains = (strpos($c['phone'], '91970289') !== false || strpos($c['whatsapp_id'] ?? '', '91970289') !== false) ? ' ← CONTÉM 91970289' : '';
    echo "ID {$c['id']}: {$c['name']} - phone: {$c['phone']}{$contains}\n";
}
echo "</pre>";

// Teste direto da query LIKE
echo "<h2>Teste direto da query LIKE:</h2>";
$testQueries = [
    "SELECT * FROM contacts WHERE phone LIKE '%553591970289%' ORDER BY id ASC LIMIT 1",
    "SELECT * FROM contacts WHERE phone LIKE '%91970289%' ORDER BY id ASC LIMIT 1",
    "SELECT * FROM contacts WHERE whatsapp_id LIKE '%553591970289%' ORDER BY id ASC LIMIT 1",
];
echo "<pre>";
foreach ($testQueries as $sql) {
    echo "Query: {$sql}\n";
    $result = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        echo "  → ENCONTRADO: ID {$result['id']} - {$result['name']}\n";
    } else {
        echo "  → NÃO ENCONTRADO\n";
    }
    echo "\n";
}
echo "</pre>";
