-- ============================================
-- Script para Resetar APENAS Conversas WhatsApp
-- ============================================
-- Este script mantém contatos mas limpa conversas e mensagens do WhatsApp
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Deletar mensagens de conversas WhatsApp
DELETE m FROM messages m
INNER JOIN conversations c ON m.conversation_id = c.id
WHERE c.channel = 'whatsapp';

-- 2. Deletar relacionamentos de tags com conversas WhatsApp
DELETE ctt FROM conversation_tags ctt
INNER JOIN conversations c ON ctt.conversation_id = c.id
WHERE c.channel = 'whatsapp';

-- 3. Deletar conversas WhatsApp
DELETE FROM conversations WHERE channel = 'whatsapp';

-- Resetar auto increment das conversas (opcional)
-- ALTER TABLE conversations AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- Verificar contagem após limpeza
SELECT 
    (SELECT COUNT(*) FROM messages m 
     INNER JOIN conversations c ON m.conversation_id = c.id 
     WHERE c.channel = 'whatsapp') as whatsapp_messages,
    (SELECT COUNT(*) FROM conversations WHERE channel = 'whatsapp') as whatsapp_conversations,
    (SELECT COUNT(*) FROM contacts) as total_contacts;

