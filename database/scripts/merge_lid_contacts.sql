-- Script para mesclar contatos duplicados (LID + Número Real)
-- Este script mescla o contato 134 (LID) com o contato 135 (número real)

-- 1. Atualizar todas as conversas do contato LID (134) para o contato real (135)
UPDATE conversations 
SET contact_id = 135 
WHERE contact_id = 134;

-- 2. Atualizar todas as mensagens do contato LID para o contato real
UPDATE messages 
SET contact_id = 135 
WHERE contact_id = 134;

-- 3. Atualizar quaisquer outras referências (contact_agents, etc)
UPDATE contact_agents 
SET contact_id = 135 
WHERE contact_id = 134;

-- 4. Deletar o contato LID duplicado
DELETE FROM contacts WHERE id = 134;

-- Resultado: Apenas o contato 135 (com número real 5511997587656) permanecerá
-- Todas as conversas e mensagens serão consolidadas no contato correto

-- Para verificar:
SELECT id, name, phone, whatsapp_id FROM contacts WHERE id IN (134, 135);

