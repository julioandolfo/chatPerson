<?php
/**
 * Script para normalizar números de telefone na tabela contacts
 * Remove sufixos como @lid, @s.whatsapp.net, etc.
 * Identifica e lista contatos duplicados
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Helpers\Database;
use App\Models\Contact;

echo "=== Normalização de Números de Telefone ===\n\n";

// 1. Listar contatos com números não normalizados
echo "1. Buscando contatos com números não normalizados...\n";
$sql = "SELECT id, name, phone FROM contacts WHERE phone LIKE '%@%' OR phone LIKE '%+%' OR phone LIKE '%:%'";
$contacts = Database::query($sql);

echo "   Encontrados " . count($contacts) . " contatos com números não normalizados.\n\n";

if (empty($contacts)) {
    echo "✅ Todos os números já estão normalizados!\n";
    exit(0);
}

// 2. Normalizar números
echo "2. Normalizando números...\n";
$normalized = [];
$duplicates = [];

foreach ($contacts as $contact) {
    $original = $contact['phone'];
    $normalizedPhone = Contact::normalizePhoneNumber($original);
    
    if ($normalizedPhone !== $original) {
        echo "   ID {$contact['id']}: '{$original}' → '{$normalizedPhone}'\n";
        
        // Verificar se já existe contato com este número normalizado
        $existing = Contact::findByPhone($normalizedPhone);
        
        if ($existing && $existing['id'] != $contact['id']) {
            // Duplicata encontrada!
            $duplicates[] = [
                'original_id' => $contact['id'],
                'original_name' => $contact['name'],
                'original_phone' => $original,
                'normalized_phone' => $normalizedPhone,
                'existing_id' => $existing['id'],
                'existing_name' => $existing['name'],
                'existing_phone' => $existing['phone']
            ];
            echo "      ⚠️  DUPLICATA: Já existe contato ID {$existing['id']} com número '{$normalizedPhone}'\n";
        } else {
            // Atualizar número normalizado
            Contact::update($contact['id'], ['phone' => $normalizedPhone]);
            $normalized[] = [
                'id' => $contact['id'],
                'original' => $original,
                'normalized' => $normalizedPhone
            ];
        }
    }
}

echo "\n3. Resumo:\n";
echo "   ✅ Normalizados: " . count($normalized) . " contatos\n";
echo "   ⚠️  Duplicatas encontradas: " . count($duplicates) . " contatos\n\n";

// 4. Listar duplicatas
if (!empty($duplicates)) {
    echo "4. Contatos duplicados (mesmo número normalizado):\n\n";
    foreach ($duplicates as $dup) {
        echo "   Número normalizado: {$dup['normalized_phone']}\n";
        echo "   - Contato ID {$dup['original_id']}: {$dup['original_name']} ({$dup['original_phone']})\n";
        echo "   - Contato ID {$dup['existing_id']}: {$dup['existing_name']} ({$dup['existing_phone']})\n";
        echo "   → Recomendação: Mesclar ou deletar um dos contatos\n\n";
    }
    
    echo "\n⚠️  ATENÇÃO: Contatos duplicados precisam ser mesclados manualmente!\n";
    echo "   Você pode usar o script merge_duplicate_contacts.php para mesclar.\n";
} else {
    echo "✅ Nenhuma duplicata encontrada!\n";
}

echo "\n=== Concluído ===\n";

