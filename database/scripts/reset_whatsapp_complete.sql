-- ============================================
-- Script para Resetar COMPLETO WhatsApp (Conversas + Contatos WhatsApp)
-- ============================================
-- Este script limpa conversas WhatsApp e contatos criados via WhatsApp
-- Mantém outros canais e contatos criados manualmente
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

-- 4. Deletar contatos que foram criados via WhatsApp (têm whatsapp_id)
-- CUIDADO: Isso deleta contatos que têm whatsapp_id, mesmo que tenham sido criados manualmente
DELETE FROM contacts WHERE whatsapp_id IS NOT NULL AND whatsapp_id != '';

-- Resetar auto increments
ALTER TABLE conversations AUTO_INCREMENT = 1;
ALTER TABLE messages AUTO_INCREMENT = 1;
ALTER TABLE contacts AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- Verificar contagem após limpeza
SELECT 
    (SELECT COUNT(*) FROM messages m 
     INNER JOIN conversations c ON m.conversation_id = c.id 
     WHERE c.channel = 'whatsapp') as whatsapp_messages,
    (SELECT COUNT(*) FROM conversations WHERE channel = 'whatsapp') as whatsapp_conversations,
    (SELECT COUNT(*) FROM contacts WHERE whatsapp_id IS NOT NULL) as whatsapp_contacts,
    (SELECT COUNT(*) FROM contacts) as total_contacts;

