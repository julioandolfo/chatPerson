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

echo "<h2>Logs serão gravados em storage/logs/</h2>";
