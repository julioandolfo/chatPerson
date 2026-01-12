# 🚀 CRIAR ÍNDICES - Executar UM POR VEZ

Se o script SQL está dando erro, execute os comandos **um por vez** diretamente no MySQL.

---

## ⚡ COMANDOS (Copie e Cole Um de Cada Vez)

### 1️⃣ Conectar ao Banco

```bash
mysql -u root -p
```

Digite a senha, depois:

```sql
USE chat_person;
```

---

### 2️⃣ Criar Índice 1 (unread_count)

```sql
CREATE INDEX idx_messages_unread ON messages (conversation_id, sender_type, read_at);
```

**Se der erro "Duplicate key name"**: Índice já existe, pule para o próximo. ✅

---

### 3️⃣ Criar Índice 2 (last_message)

```sql
CREATE INDEX idx_messages_conversation_created ON messages (conversation_id, created_at DESC);
```

**Se der erro "Duplicate key name"**: Índice já existe, pule para o próximo. ✅

---

### 4️⃣ Criar Índice 3 (first_response)

```sql
CREATE INDEX idx_messages_response ON messages (conversation_id, sender_type, created_at);
```

**Se der erro "Duplicate key name"**: Índice já existe, pule para o próximo. ✅

---

### 5️⃣ Criar Índice 4 (composto)

```sql
CREATE INDEX idx_messages_conv_sender_date ON messages (conversation_id, sender_type, created_at);
```

**Se der erro "Duplicate key name"**: Índice já existe, pule para o próximo. ✅

---

### 6️⃣ Atualizar Estatísticas

```sql
ANALYZE TABLE messages;
```

---

### 7️⃣ Verificar Índices Criados

```sql
SHOW INDEX FROM messages WHERE Key_name LIKE 'idx_messages_%';
```

**Deve aparecer**:
- idx_messages_unread
- idx_messages_conversation_created
- idx_messages_response
- idx_messages_conv_sender_date

---

### 8️⃣ Medir QPS

```sql
SHOW GLOBAL STATUS LIKE 'Questions';
```

**Anote o valor**

**Aguarde 10 segundos**

```sql
SHOW GLOBAL STATUS LIKE 'Questions';
```

**Calcule**: `(valor2 - valor1) / 10` = QPS novo

---

## 📊 Resultado Esperado

- **QPS Antes**: 3.602
- **QPS Depois**: 0.3-1.0
- **Redução**: 70-90% ⚡

---

**Cole aqui os índices que apareceram e o novo QPS!** 📋
