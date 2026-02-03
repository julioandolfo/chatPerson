<?php
/**
 * Script para unificar contatos duplicados específicos (Julio Andolfo + Teste Webhook)
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDBConnection();
    
    echo "<h2>🔧 Unificação de Contatos Duplicados</h2>";
    echo "<hr>";
    
    // Buscar contatos duplicados pelos números
    $phones = ['553591970289', '5535991970289'];
    
    echo "<h3>📞 Buscando contatos com números relacionados...</h3>";
    
    $stmt = $db->prepare("SELECT id, name, phone, created_at FROM contacts WHERE phone IN (?, ?) ORDER BY created_at ASC");
    $stmt->execute($phones);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($contacts) < 2) {
        echo "<p>✅ Nenhuma duplicata encontrada para esses números!</p>";
        exit;
    }
    
    echo "<p><strong>Encontrados " . count($contacts) . " contatos:</strong></p>";
    echo "<ul>";
    foreach ($contacts as $c) {
        echo "<li>ID: {$c['id']}, Nome: <strong>{$c['name']}</strong>, Phone: {$c['phone']}, Criado: {$c['created_at']}</li>";
    }
    echo "</ul>";
    echo "<hr>";
    
    // Manter o mais antigo
    $keepContact = $contacts[0];
    $removeContacts = array_slice($contacts, 1);
    
    echo "<h3>✅ Mantendo contato:</h3>";
    echo "<p style='color: green; padding: 10px; background: #d4edda; border-radius: 5px;'>";
    echo "<strong>ID:</strong> {$keepContact['id']}<br>";
    echo "<strong>Nome:</strong> {$keepContact['name']}<br>";
    echo "<strong>Phone:</strong> {$keepContact['phone']}<br>";
    echo "<strong>Criado:</strong> {$keepContact['created_at']}";
    echo "</p>";
    
    // Atualizar telefone do contato mantido para formato normalizado
    $normalizedPhone = '5535991970289';
    $stmt = $db->prepare("UPDATE contacts SET phone = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$normalizedPhone, $keepContact['id']]);
    echo "<p>✅ Telefone atualizado para: <strong>{$normalizedPhone}</strong></p>";
    
    echo "<h3>❌ Removendo contatos duplicados:</h3>";
    
    $totalConversations = 0;
    
    foreach ($removeContacts as $removeContact) {
        echo "<div style='padding: 10px; background: #fff3cd; border-radius: 5px; margin: 10px 0;'>";
        echo "<p><strong>ID:</strong> {$removeContact['id']}, <strong>Nome:</strong> {$removeContact['name']}</p>";
        
        // Mover conversas
        $stmt = $db->prepare("SELECT id FROM conversations WHERE contact_id = ?");
        $stmt->execute([$removeContact['id']]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($conversations) > 0) {
            echo "<p>📨 Movendo " . count($conversations) . " conversa(s)...</p>";
            
            $stmt = $db->prepare("UPDATE conversations SET contact_id = ? WHERE contact_id = ?");
            $stmt->execute([$keepContact['id'], $removeContact['id']]);
            
            $totalConversations += count($conversations);
            echo "<p style='color: green;'>✅ Conversas movidas!</p>";
        } else {
            echo "<p>Nenhuma conversa para mover.</p>";
        }
        
        // Remover contato duplicado
        $stmt = $db->prepare("DELETE FROM contacts WHERE id = ?");
        $stmt->execute([$removeContact['id']]);
        
        echo "<p style='color: red;'>❌ Contato removido!</p>";
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<h3>✅ Unificação concluída!</h3>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
    echo "<p><strong>📊 Resumo:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Contato mantido:</strong> {$keepContact['name']} (ID: {$keepContact['id']})</li>";
    echo "<li><strong>Telefone normalizado:</strong> {$normalizedPhone}</li>";
    echo "<li><strong>Contatos removidos:</strong> " . count($removeContacts) . "</li>";
    echo "<li><strong>Conversas unificadas:</strong> {$totalConversations}</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<hr>";
    echo "<p><a href='/contacts' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>📋 Ver Contatos</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "<h3>❌ Erro</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
}
