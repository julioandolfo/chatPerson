# 🔄 COMO FUNCIONAM AS FONTES EXTERNAS

## ✅ SIM! Os dados são IMPORTADOS para seu sistema

---

## 📊 FLUXO COMPLETO DE SINCRONIZAÇÃO

```
┌─────────────────────────────────────────────────────────────┐
│  BANCO EXTERNO (MySQL/PostgreSQL)                          │
│  - Tabela: clientes                                         │
│  - 1000 registros                                           │
└─────────────────────────────────────────────────────────────┘
                        │
                        │ 1. Cron Job (ou Manual)
                        │    Executa sync()
                        ▼
┌─────────────────────────────────────────────────────────────┐
│  SINCRONIZAÇÃO                                              │
│  ✅ Busca dados do banco externo                            │
│  ✅ Mapeia colunas (nome, telefone, email)                  │
│  ✅ Para cada registro:                                      │
│     1. Normaliza telefone (remove caracteres)              │
│     2. Verifica se JÁ EXISTE no sistema (por telefone)     │
│     3. Se existe: ATUALIZA dados                           │
│     4. Se não existe: CRIA novo contato                    │
│     5. Adiciona à lista local                              │
└─────────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│  SEU SISTEMA (Banco Local)                                  │
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │ Tabela: contacts                                  │    │
│  │ - id: 1                                           │    │
│  │ - name: João Silva                                │    │
│  │ - phone: 5511999999999 (normalizado!)            │    │
│  │ - email: joao@email.com                           │    │
│  │ - created_at: 2026-01-19                          │    │
│  └───────────────────────────────────────────────────┘    │
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │ Tabela: contact_list_items                        │    │
│  │ - list_id: 5                                      │    │
│  │ - contact_id: 1                                   │    │
│  │ - created_at: 2026-01-19                          │    │
│  └───────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                        │
                        │ 2. Quando você cria uma campanha
                        ▼
┌─────────────────────────────────────────────────────────────┐
│  CAMPANHA                                                   │
│  - Pega contatos da lista LOCAL (contact_list_items)       │
│  - Cria fila de envio (campaign_queue)                     │
│  - Envia mensagens                                          │
│  - Registra envios (campaign_messages)                     │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎯 CONTROLE DE DUPLICATAS

### ✅ **1. Por Telefone Normalizado**

```php
// Linha 382-400 do ExternalDataSourceService.php

// Verificar se contato já existe
$existingContact = Contact::findByPhoneNormalized($contactData['phone']);

if ($existingContact) {
    // ✅ Contato já existe: ATUALIZA dados
    Contact::update($existingContact['id'], $contactData);
    
    // Adiciona à lista se ainda não estiver
    ContactListService::addContact($contactListId, $existingContact['id']);
    
    $logData['records_updated']++;
} else {
    // ✅ Contato não existe: CRIA novo
    $contactId = Contact::create($contactData);
    
    // Adiciona à lista
    ContactListService::addContact($contactListId, $contactId);
    
    $logData['records_created']++;
}
```

### 📝 **Como Funciona:**

1. **Normaliza o telefone** (remove espaços, hífens, parênteses)
   - `(11) 99999-9999` → `5511999999999`
   - `11 9 9999-9999` → `5511999999999`
   - `+55 11 99999-9999` → `5511999999999`

2. **Busca no banco local** pelo telefone normalizado

3. **Se encontrar:** Atualiza nome, email e outros dados

4. **Se não encontrar:** Cria novo contato

---

## 🔍 CONTROLE DE ENVIOS (Quem já recebeu)

### ✅ **1. Por Campanha**

Quando você envia uma campanha, o sistema registra:

```sql
-- Tabela: campaign_messages
INSERT INTO campaign_messages (
    campaign_id,    -- ID da campanha
    contact_id,     -- ID do contato
    phone_number,   -- Telefone do contato
    status,         -- sent, failed, pending
    sent_at,        -- Data/hora do envio
    ...
);
```

### ✅ **2. Verificação Antes de Enviar**

Ao criar uma nova campanha, você pode:

**Opção 1: Enviar para TODOS da lista**
- Ignora envios anteriores
- Útil para campanhas recorrentes

**Opção 2: Enviar apenas para quem NÃO recebeu**
- Verifica `campaign_messages`
- Filtra contatos que já receberam esta campanha

**Opção 3: Enviar apenas para quem NÃO respondeu**
- Verifica `campaign_messages` + `messages`
- Filtra contatos que responderam

---

## 📊 EXEMPLO PRÁTICO

### **Cenário: Você tem 1000 clientes no CRM**

#### **1ª Sincronização (Hoje)**

```
Fonte Externa: 1000 clientes
↓
SEU SISTEMA:
✅ Criados: 1000 novos contatos
✅ Atualizados: 0
✅ Lista: 1000 contatos
```

#### **2ª Sincronização (Amanhã)**

```
Fonte Externa: 1050 clientes (50 novos, 1000 antigos com dados atualizados)
↓
SEU SISTEMA:
✅ Criados: 50 novos contatos
✅ Atualizados: 1000 (nome/email atualizados se mudaram)
✅ Lista: 1050 contatos (não duplica!)
```

#### **1ª Campanha**

```
Lista: 1050 contatos
↓
Envios: 1050 mensagens
↓
Registros criados em campaign_messages:
- contact_id: 1, status: sent, sent_at: 2026-01-19 10:00
- contact_id: 2, status: sent, sent_at: 2026-01-19 10:01
- contact_id: 3, status: sent, sent_at: 2026-01-19 10:02
...
```

#### **3ª Sincronização (Semana seguinte)**

```
Fonte Externa: 1100 clientes (50 novos)
↓
SEU SISTEMA:
✅ Criados: 50 novos contatos (ID 1051-1100)
✅ Atualizados: 1050 (se dados mudaram)
✅ Lista: 1100 contatos
```

#### **2ª Campanha (com filtro)**

```
Lista: 1100 contatos

Opção A - Enviar para TODOS:
✅ Envios: 1100 mensagens (incluindo quem já recebeu)

Opção B - Enviar apenas para NOVOS:
✅ Filtro: WHERE contact_id NOT IN (SELECT contact_id FROM campaign_messages WHERE campaign_id = 1)
✅ Envios: 50 mensagens (apenas os novos)

Opção C - Enviar para quem NÃO RESPONDEU:
✅ Filtro: WHERE contact_id IN (SELECT contact_id FROM campaign_messages WHERE campaign_id = 1 AND status = 'sent')
    AND contact_id NOT IN (SELECT contact_id FROM messages WHERE type = 'incoming' AND created_at > '2026-01-19')
✅ Envios: XXX mensagens (depende de quantos responderam)
```

---

## 🗄️ TABELAS ENVOLVIDAS

### **1. `contacts`** (Contatos locais)
```sql
CREATE TABLE contacts (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    phone VARCHAR(50),          -- Telefone normalizado
    email VARCHAR(255),
    custom_fields JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### **2. `contact_lists`** (Listas de contatos)
```sql
CREATE TABLE contact_lists (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    external_source_id INT,     -- ID da fonte externa
    sync_enabled BOOLEAN,       -- Sincronização automática?
    send_order VARCHAR(50),     -- default, random, asc, desc
    last_sync_at TIMESTAMP,     -- Última sincronização
    ...
);
```

### **3. `contact_list_items`** (Contatos em cada lista)
```sql
CREATE TABLE contact_list_items (
    id INT PRIMARY KEY,
    list_id INT,                -- ID da lista
    contact_id INT,             -- ID do contato local
    created_at TIMESTAMP,
    UNIQUE KEY (list_id, contact_id)  -- ✅ Impede duplicatas!
);
```

### **4. `external_data_sources`** (Fontes externas)
```sql
CREATE TABLE external_data_sources (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    type VARCHAR(50),           -- mysql, postgresql
    connection_config JSON,     -- host, port, database, user, pass
    table_name VARCHAR(255),    -- Tabela a ser consultada
    column_mapping JSON,        -- nome→nome_completo, phone→celular
    sync_frequency VARCHAR(50), -- manual, hourly, daily, weekly
    last_sync_at TIMESTAMP,     -- Última sincronização
    total_records INT,          -- Total de registros na última sync
    ...
);
```

### **5. `external_data_sync_logs`** (Histórico de sincronizações)
```sql
CREATE TABLE external_data_sync_logs (
    id INT PRIMARY KEY,
    source_id INT,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    status VARCHAR(50),         -- success, error
    records_fetched INT,        -- Quantos registros buscou
    records_created INT,        -- Quantos criou
    records_updated INT,        -- Quantos atualizou
    records_failed INT,         -- Quantos falharam
    execution_time_ms INT,      -- Tempo de execução
    ...
);
```

### **6. `campaigns`** (Campanhas)
```sql
CREATE TABLE campaigns (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    target_type VARCHAR(50),    -- list, segment, custom
    target_id INT,              -- ID da lista (se target_type=list)
    status VARCHAR(50),         -- draft, scheduled, active, completed
    ...
);
```

### **7. `campaign_queue`** (Fila de envio)
```sql
CREATE TABLE campaign_queue (
    id INT PRIMARY KEY,
    campaign_id INT,
    contact_id INT,
    phone_number VARCHAR(50),
    scheduled_at TIMESTAMP,
    status VARCHAR(50),         -- pending, sent, failed
    attempts INT,
    ...
);
```

### **8. `campaign_messages`** (Histórico de envios)
```sql
CREATE TABLE campaign_messages (
    id INT PRIMARY KEY,
    campaign_id INT,
    contact_id INT,
    phone_number VARCHAR(50),
    message_content TEXT,
    status VARCHAR(50),         -- sent, failed, delivered, read
    sent_at TIMESTAMP,
    delivered_at TIMESTAMP,
    read_at TIMESTAMP,
    error_message TEXT,
    ...
);
```

---

## 🔄 SINCRONIZAÇÃO AUTOMÁTICA

### **Como Funciona:**

1. **Cron Job** (configurado no sistema)
   ```
   0 * * * * php /caminho/process-external-sources.php
   ```

2. **Script processa:**
   - Busca fontes com `sync_frequency != 'manual'`
   - Verifica se já passou o tempo (hourly, daily, weekly)
   - Executa `ExternalDataSourceService::sync()`
   - Registra log em `external_data_sync_logs`

3. **Resultado:**
   - ✅ Contatos novos são criados
   - ✅ Contatos existentes são atualizados
   - ✅ Lista sempre sincronizada

---

## ✅ VANTAGENS DESTA ABORDAGEM

### ✅ **1. Performance**
- Envios são rápidos (consultam banco local)
- Não precisa acessar banco externo durante campanha

### ✅ **2. Controle Total**
- Histórico completo de envios
- Sabe quem já recebeu, respondeu, clicou, etc.
- Pode fazer filtros complexos

### ✅ **3. Sem Duplicatas**
- Telefone normalizado garante unicidade
- UNIQUE KEY em `contact_list_items` impede duplicatas na lista
- Mesma pessoa não é adicionada 2x

### ✅ **4. Dados Sempre Atualizados**
- Sincronização automática mantém dados frescos
- Se cliente mudou nome/email no CRM, atualiza automaticamente

### ✅ **5. Independência**
- Mesmo se banco externo ficar offline, campanhas continuam funcionando
- Usa dados locais já sincronizados

### ✅ **6. Auditoria Completa**
- `external_data_sync_logs`: histórico de todas as sincronizações
- `campaign_messages`: histórico de todos os envios
- Rastreabilidade total

---

## 🎯 RESUMO

| Pergunta | Resposta |
|----------|----------|
| **Os dados são importados?** | ✅ SIM, para a tabela `contacts` |
| **Há controle de duplicatas?** | ✅ SIM, por telefone normalizado |
| **Sabe quem já recebeu?** | ✅ SIM, via `campaign_messages` |
| **Sabe quem já respondeu?** | ✅ SIM, via `messages` |
| **Pode reenviar para mesma pessoa?** | ✅ SIM, configurável por campanha |
| **Dados ficam desatualizados?** | ❌ NÃO, sincronização automática |
| **Precisa acessar banco externo sempre?** | ❌ NÃO, só na sincronização |
| **E se banco externo cair?** | ✅ Campanhas funcionam normalmente |

---

## 📖 FLUXO COMPLETO PASSO A PASSO

```
1. Você configura fonte externa
   ↓
2. Sistema conecta e lista tabelas/colunas
   ↓
3. Você mapeia colunas (nome→nome_completo, phone→celular)
   ↓
4. Configura sincronização (manual/automática)
   ↓
5. Sincronização executa:
   ├─ Busca dados do banco externo
   ├─ Para cada registro:
   │  ├─ Normaliza telefone
   │  ├─ Verifica se já existe
   │  ├─ Cria ou atualiza contato
   │  └─ Adiciona à lista
   ↓
6. Você cria campanha:
   ├─ Escolhe lista
   ├─ Escreve mensagem
   ├─ Define filtros (opcional)
   ↓
7. Sistema cria fila de envio:
   ├─ Pega contatos da lista LOCAL
   ├─ Aplica filtros (ordem, condições)
   ├─ Cria registros em campaign_queue
   ↓
8. Cron job processa fila:
   ├─ Pega próximos da fila
   ├─ Envia mensagens
   ├─ Registra em campaign_messages
   ├─ Marca como enviado
   ↓
9. Análise de resultados:
   ├─ Quantos enviados
   ├─ Quantos entregues
   ├─ Quantos responderam
   ├─ Taxa de conversão
```

---

**🎉 Pronto! Agora você sabe EXATAMENTE como funciona!**

Os dados são importados, há controle de duplicatas, e você tem histórico completo de todos os envios! 📊
