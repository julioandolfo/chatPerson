# 🚀 Guia de Implementação Rápida - Otimização de Queries

## ✅ O Que Foi Implementado

1. ✅ **Helper de Cache** (`app/Helpers/Cache.php`)
2. ✅ **Otimização Query #1** - ContactController com cache de 5 minutos
3. ✅ **Otimização Query #2** - AgentPerformanceService com cache de 2 minutos
4. ✅ **SQL de Índices** (`OTIMIZACAO_INDICES.sql`)
5. ✅ **Migration de Índices** (`database/migrations/021_create_performance_indexes.php`)

---

## 🎯 Como Aplicar (Passo a Passo)

### PASSO 1: Criar diretório de cache
```bash
mkdir -p storage/cache/queries
chmod 777 storage/cache/queries
```

### PASSO 2: Executar migration dos índices

**Opção A - Via CLI:**
```bash
php database/migrate.php
```

**Opção B - Via SQL direto (se preferir):**
```bash
# Backup primeiro!
mysqldump -u root -p chat > backup_antes_indices.sql

# Executar SQL
mysql -u root -p chat < OTIMIZACAO_INDICES.sql
```

### PASSO 3: Testar as otimizações

**Teste 1 - Cache funcionando:**
```bash
# No navegador, clique em uma conversa
# 1ª vez: deve demorar ~3 segundos (normal)
# 2ª vez (mesma conversa): deve ser instantâneo (< 0.1s)
```

**Teste 2 - Índices funcionando:**
```bash
# Verificar slow.log
tail -f /var/log/mysql/slow.log

# Deve ter MUITO menos queries agora
```

---

## 📊 SQL para Copiar e Executar

### Opção 1: Todos os Índices Críticos (Recomendado)

```sql
-- ═══════════════════════════════════════════════════════════════════
-- ÍNDICES CRÍTICOS - EXECUTAR PRIMEIRO
-- ═══════════════════════════════════════════════════════════════════

-- Query #1 - Histórico do Contato
CREATE INDEX idx_messages_conv_sender_date ON messages(conversation_id, sender_type, created_at);
CREATE INDEX idx_conversations_contact ON conversations(contact_id);
CREATE INDEX idx_messages_conversation_id ON messages(conversation_id, created_at);

-- Query #2 - Ranking de Agentes
CREATE INDEX idx_conversations_agent_date_status ON conversations(agent_id, created_at, status, resolved_at);
CREATE INDEX idx_messages_sender_type_date ON messages(sender_id, sender_type, created_at, ai_agent_id);
CREATE INDEX idx_users_role_status ON users(role, status);

-- Analisar tabelas
ANALYZE TABLE conversations;
ANALYZE TABLE messages;
ANALYZE TABLE users;
```

### Opção 2: Todos os Índices Completos (Para Máximo Desempenho)

```sql
-- ═══════════════════════════════════════════════════════════════════
-- ÍNDICES COMPLETOS - TODOS OS ÍNDICES DE OTIMIZAÇÃO
-- ═══════════════════════════════════════════════════════════════════

-- 1. Índices para Query #1 (Histórico do Contato)
CREATE INDEX idx_messages_conv_sender_date ON messages(conversation_id, sender_type, created_at);
CREATE INDEX idx_conversations_contact ON conversations(contact_id);
CREATE INDEX idx_messages_conversation_id ON messages(conversation_id, created_at);

-- 2. Índices para Query #2 (Ranking de Agentes)
CREATE INDEX idx_conversations_agent_date_status ON conversations(agent_id, created_at, status, resolved_at);
CREATE INDEX idx_messages_sender_type_date ON messages(sender_id, sender_type, created_at, ai_agent_id);
CREATE INDEX idx_users_role_status ON users(role, status);

-- 3. Índices Adicionais (Otimizações Gerais)
CREATE INDEX idx_conversations_status ON conversations(status);
CREATE INDEX idx_conversations_dept_status ON conversations(department_id, status);
CREATE INDEX idx_messages_ai_agent ON messages(ai_agent_id);
CREATE INDEX idx_contacts_email ON contacts(email);
CREATE INDEX idx_contacts_phone ON contacts(phone);
CREATE INDEX idx_messages_conv_count ON messages(conversation_id, id);
CREATE INDEX idx_conversations_created ON conversations(created_at);
CREATE INDEX idx_messages_created ON messages(created_at);

-- 4. Índices para Dashboard e Analytics
CREATE INDEX idx_conversations_funnel_date ON conversations(funnel_id, created_at, status);
CREATE INDEX idx_conversations_unassigned ON conversations(agent_id, status);
CREATE INDEX idx_messages_last ON messages(conversation_id, created_at DESC);

-- 5. Índices para Agent Departments (se a tabela existir)
-- CREATE INDEX idx_agent_departments_user ON agent_departments(user_id, department_id);
-- CREATE INDEX idx_agent_departments_dept ON agent_departments(department_id, user_id);

-- 6. Analisar tabelas
ANALYZE TABLE conversations;
ANALYZE TABLE messages;
ANALYZE TABLE users;
ANALYZE TABLE contacts;

-- 7. Verificar índices criados
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as COLUMNS
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE()
    AND INDEX_NAME LIKE 'idx_%'
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME, INDEX_NAME;
```

---

## ⚠️ Importante: Se Der Erro de Índice Duplicado

Se você já tiver alguns índices criados, pode dar erro. Use este SQL que ignora erros:

```sql
-- Criar índices ignorando erros se já existirem
DELIMITER $$

CREATE PROCEDURE create_index_if_not_exists()
BEGIN
    -- Índices críticos
    DECLARE CONTINUE HANDLER FOR 1061 BEGIN END; -- Duplicate key name
    DECLARE CONTINUE HANDLER FOR 1072 BEGIN END; -- Key column doesn't exist
    
    CREATE INDEX idx_messages_conv_sender_date ON messages(conversation_id, sender_type, created_at);
    CREATE INDEX idx_conversations_contact ON conversations(contact_id);
    CREATE INDEX idx_messages_conversation_id ON messages(conversation_id, created_at);
    CREATE INDEX idx_conversations_agent_date_status ON conversations(agent_id, created_at, status, resolved_at);
    CREATE INDEX idx_messages_sender_type_date ON messages(sender_id, sender_type, created_at, ai_agent_id);
    CREATE INDEX idx_users_role_status ON users(role, status);
    CREATE INDEX idx_conversations_status ON conversations(status);
    CREATE INDEX idx_conversations_dept_status ON conversations(department_id, status);
    CREATE INDEX idx_messages_ai_agent ON messages(ai_agent_id);
    CREATE INDEX idx_contacts_email ON contacts(email);
    CREATE INDEX idx_contacts_phone ON contacts(phone);
    CREATE INDEX idx_conversations_created ON conversations(created_at);
    CREATE INDEX idx_messages_created ON messages(created_at);
    CREATE INDEX idx_conversations_funnel_date ON conversations(funnel_id, created_at, status);
    CREATE INDEX idx_conversations_unassigned ON conversations(agent_id, status);
END$$

DELIMITER ;

-- Executar
CALL create_index_if_not_exists();

-- Limpar procedure
DROP PROCEDURE IF EXISTS create_index_if_not_exists;

-- Analisar
ANALYZE TABLE conversations;
ANALYZE TABLE messages;
ANALYZE TABLE users;
ANALYZE TABLE contacts;
```

---

## 📈 Como Verificar Se Está Funcionando

### 1. Verificar Cache
```bash
# Deve criar arquivos .cache
ls -lh storage/cache/queries/

# Ver informações do cache (criar script PHP):
# echo "<?php require 'app/Helpers/Cache.php'; print_r(\App\Helpers\Cache::info()); ?>" | php
```

### 2. Verificar Índices
```sql
-- Listar índices criados
SHOW INDEX FROM messages WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM conversations WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM users WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM contacts WHERE Key_name LIKE 'idx_%';
```

### 3. Testar Performance
```sql
-- Query #1 - Deve usar idx_messages_conv_sender_date
EXPLAIN SELECT 
    COUNT(DISTINCT c.id) AS total_conversations,
    AVG(response_times.response_time_minutes) AS avg_response_time_minutes
FROM conversations c
LEFT JOIN (
    SELECT 
        m1.conversation_id,
        AVG(TIMESTAMPDIFF(MINUTE, m1.created_at, m2.created_at)) as response_time_minutes
    FROM messages m1
    INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
        AND m2.sender_type = 'agent'
        AND m2.created_at > m1.created_at
    WHERE m1.sender_type = 'contact'
    GROUP BY m1.conversation_id
) response_times ON response_times.conversation_id = c.id
WHERE c.contact_id = 628;

-- Query #2 - Deve usar idx_conversations_agent_date_status
EXPLAIN SELECT 
    u.id,
    u.name,
    COUNT(DISTINCT c.id) as total_conversations
FROM users u
LEFT JOIN conversations c ON u.id = c.agent_id 
    AND c.created_at >= '2026-01-01' 
    AND c.created_at <= '2026-01-12 23:59:59'
WHERE u.role IN ('agent', 'admin', 'supervisor')
    AND u.status = 'active'
GROUP BY u.id
LIMIT 5;
```

### 4. Monitorar Slow Log
```bash
# Antes: ~100+ queries/hora
# Depois: ~10-20 queries/hora

tail -f /var/log/mysql/slow.log | grep "Query_time"
```

---

## 🎯 Checklist de Implementação

```
☐ 1. Criar diretório storage/cache/queries/
☐ 2. Fazer backup do banco de dados
☐ 3. Executar SQL dos índices críticos (Query #1 e #2)
☐ 4. Verificar se índices foram criados (SHOW INDEX)
☐ 5. Testar: clicar em conversa 2x (deve ser mais rápido)
☐ 6. Testar: refresh do dashboard 2x (deve ser mais rápido)
☐ 7. Monitorar slow.log (deve ter menos queries)
☐ 8. Monitorar CPU (deve estar mais baixa)
☐ 9. Executar ANALYZE TABLE (atualizar estatísticas)
☐ 10. Documentar resultado e ganho obtido
```

---

## 📊 Resultado Esperado

### Antes:
- ❌ Query #1: 3+ segundos
- ❌ Query #2: 1+ segundo
- ❌ CPU: 60-70%
- ❌ Slow log: 100+ queries/hora

### Depois (somente índices):
- ✅ Query #1: ~0.5 segundos
- ✅ Query #2: ~0.3 segundos
- ✅ CPU: 40-50%
- ✅ Slow log: 30-40 queries/hora

### Depois (índices + cache):
- ✅ Query #1: 0.01 segundos (na maioria das vezes)
- ✅ Query #2: 0.05 segundos (na maioria das vezes)
- ✅ CPU: 20-30%
- ✅ Slow log: 10-20 queries/hora

---

## 🆘 Troubleshooting

### Cache não está funcionando?
```bash
# Verificar permissões
chmod -R 777 storage/cache/queries/

# Verificar se diretório existe
ls -la storage/cache/

# Limpar cache manualmente se necessário
rm -rf storage/cache/queries/*.cache
```

### Índices não melhoraram performance?
```sql
-- Forçar atualização de estatísticas
ANALYZE TABLE conversations;
ANALYZE TABLE messages;
OPTIMIZE TABLE conversations;
OPTIMIZE TABLE messages;

-- Verificar se índices estão sendo usados
SHOW INDEX FROM messages;
EXPLAIN SELECT ... sua query aqui ...
```

### Erro ao criar índice?
- Se índice já existe, é normal dar erro - ignore
- Se coluna não existe, verifique nome da coluna
- Se tabela está travada, espere alguns minutos

---

## 📞 Suporte

- Ver logs: `tail -f storage/logs/error.log`
- Ver slow log: `tail -f /var/log/mysql/slow.log`
- Documentação completa: Ver arquivos `*_QUERIES_PESADAS.md`

---

**Data**: 2026-01-12  
**Status**: ✅ Pronto para Implementar  
**Tempo estimado**: 10-15 minutos

