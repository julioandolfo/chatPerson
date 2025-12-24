<?php
/**
 * Script para inserir configurações padrão de som
 */

require_once __DIR__ . '/../config/app.php';

$defaultSoundSettings = json_encode([
    'sounds_enabled' => true,
    'default_volume' => 70,
    'sounds' => [
        'new_conversation' => ['enabled' => true, 'sound' => 'new-conversation.mp3', 'label' => 'Nova Conversa'],
        'new_message' => ['enabled' => true, 'sound' => 'new-message.mp3', 'label' => 'Nova Mensagem'],
        'conversation_assigned' => ['enabled' => true, 'sound' => 'assigned.mp3', 'label' => 'Conversa Atribuída'],
        'invite_received' => ['enabled' => true, 'sound' => 'invite.mp3', 'label' => 'Convite para Conversa'],
        'sla_warning' => ['enabled' => true, 'sound' => 'sla-warning.mp3', 'label' => 'Aviso de SLA'],
        'sla_breached' => ['enabled' => true, 'sound' => 'sla-breached.mp3', 'label' => 'SLA Estourado'],
        'mention_received' => ['enabled' => true, 'sound' => 'mention.mp3', 'label' => 'Menção Recebida']
    ]
], JSON_UNESCAPED_UNICODE);

try {
    $pdo = \App\Helpers\Database::getInstance();
    $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`, `type`, `group`, `description`) 
                           VALUES (?, ?, 'json', 'notifications', 'Configurações padrão de sons do sistema') 
                           ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
    $stmt->execute(['system_sound_settings', $defaultSoundSettings]);
    echo "✅ Configurações de som inseridas com sucesso!\n";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

