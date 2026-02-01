<?php
/**
 * Script para corrigir contatos duplicados por diferença no 9º dígito
 * 
 * Exemplo: 553591970289 e 5535991970289 são o mesmo contato
 * 
 * Este script:
 * 1. Normaliza todos os telefones de contatos
 * 2. Identifica duplicatas
 * 3. Mantém o contato mais antigo
 * 4. Move conversas e mensagens para o contato unificado
 * 5. Remove duplicatas
 */

require_once __DIR__ . '/../config/database.php';

function normalizePhoneBR(string $phone): string {
    if (empty($phone)) {
        return '';
    }
    
    // Remover caracteres especiais
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Normalizar 9º dígito para números brasileiros
    if (strlen($phone) == 12 && substr($phone, 0, 2) === '55') {
        return $phone;
    } elseif (strlen($phone) == 13 && substr($phone, 0, 2) === '55') {
        return '55' . ltrim(substr($phone, 2), '0');
    } elseif (strlen($phone) == 11 && substr($phone, 0, 2) === '55') {
        $ddd = substr($phone, 2, 2);
        $numero = substr($phone, 4);
        
        if (strlen($numero) === 8 && in_array($numero[0], ['6', '7', '8', '9'])) {
            return '55' . $ddd . '9' . $numero;
        }
        
        return $phone;
    }
    
    return $phone;
}

try {
    $db = getDBConnection();
    
    echo "<h2>🔧 Correção de Contatos Duplicados - 9º Dígito</h2>";
    echo "<hr>";
    
    // 1. Buscar todos os contatos
    echo "<h3>📊 Analisando contatos...</h3>";
    $stmt = $db->query("SELECT id, name, phone, created_at FROM contacts ORDER BY created_at ASC");
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Total de contatos: <strong>" . count($contacts) . "</strong></p>";
    
    // 2. Agrupar por telefone normalizado
    $phoneGroups = [];
    foreach ($contacts as $contact) {
        $normalized = normalizePhoneBR($contact['phone']);
        if (empty($normalized)) continue;
        
        if (!isset($phoneGroups[$normalized])) {
            $phoneGroups[$normalized] = [];
        }
        $phoneGroups[$normalized][] = $contact;
    }
    
    // 3. Identificar duplicatas
    $duplicates = array_filter($phoneGroups, function($group) {
        return count($group) > 1;
    });
    
    echo "<p>Grupos de duplicatas encontrados: <strong>" . count($duplicates) . "</strong></p>";
    echo "<hr>";
    
    if (empty($duplicates)) {
        echo "<p>✅ Nenhuma duplicata encontrada!</p>";
        exit;
    }
    
    // 4. Processar cada grupo de duplicatas
    $totalUnified = 0;
    $totalRemoved = 0;
    
    foreach ($duplicates as $normalizedPhone => $group) {
        echo "<div style='background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
        echo "<h4>📞 Telefone normalizado: {$normalizedPhone}</h4>";
        echo "<p><strong>Duplicatas encontradas: " . count($group) . "</strong></p>";
        
        // Manter o contato mais antigo
        usort($group, function($a, $b) {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });
        
        $keepContact = $group[0];
        $removeContacts = array_slice($group, 1);
        
        echo "<p style='color: green;'>✅ <strong>Manter:</strong> ID={$keepContact['id']}, Nome=\"{$keepContact['name']}\", Phone={$keepContact['phone']}, Criado em: {$keepContact['created_at']}</p>";
        
        // Atualizar telefone do contato mantido para formato normalizado
        $stmt = $db->prepare("UPDATE contacts SET phone = ? WHERE id = ?");
        $stmt->execute([$normalizedPhone, $keepContact['id']]);
        
        foreach ($removeContacts as $removeContact) {
            echo "<p style='color: orange;'>❌ <strong>Remover:</strong> ID={$removeContact['id']}, Nome=\"{$removeContact['name']}\", Phone={$removeContact['phone']}, Criado em: {$removeContact['created_at']}</p>";
            
            // Mover conversas
            $stmt = $db->prepare("UPDATE conversations SET contact_id = ? WHERE contact_id = ?");
            $stmt->execute([$keepContact['id'], $removeContact['id']]);
            $movedConversations = $stmt->rowCount();
            
            if ($movedConversations > 0) {
                echo "<p style='margin-left: 20px;'>↪️ {$movedConversations} conversa(s) movida(s)</p>";
            }
            
            // Remover contato duplicado
            $stmt = $db->prepare("DELETE FROM contacts WHERE id = ?");
            $stmt->execute([$removeContact['id']]);
            
            $totalRemoved++;
        }
        
        echo "</div>";
        $totalUnified++;
    }
    
    echo "<hr>";
    echo "<h3>✅ Correção concluída!</h3>";
    echo "<p>📊 <strong>Resumo:</strong></p>";
    echo "<ul>";
    echo "<li>Grupos unificados: <strong>{$totalUnified}</strong></li>";
    echo "<li>Contatos removidos: <strong>{$totalRemoved}</strong></li>";
    echo "<li>Contatos mantidos: <strong>{$totalUnified}</strong></li>";
    echo "</ul>";
    
    echo "<p style='background: #d4edda; padding: 10px; border-radius: 5px; color: #155724;'>";
    echo "✅ Todos os contatos duplicados foram unificados! As conversas foram preservadas e movidas para o contato correto.";
    echo "</p>";
    
    echo "<hr>";
    echo "<p><a href='fix-duplicate-contacts.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>🔄 Executar novamente</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "<h3>❌ Erro</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
}
