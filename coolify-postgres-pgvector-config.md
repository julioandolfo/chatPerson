# ⚙️ CONFIGURAÇÃO COOLIFY - POSTGRESQL + PGVECTOR

**Guia rápido de configuração no Coolify**

---

## 📝 PASSOS NO COOLIFY

### 1. Criar Novo Banco de Dados

1. Acesse o painel do Coolify
2. Vá em **"Databases"** ou **"Services"**
3. Clique em **"New Database"** ou **"Add Service"**
4. Selecione **"PostgreSQL"**

### 2. Selecionar Tipo PGVector ⭐

Na tela de seleção, você verá várias opções:

- **PostgreSQL 17 (default)** - PostgreSQL padrão sem extensões
- **Supabase PostgreSQL (with extensions)** - Com muitas extensões
- **PostGIS (AMD only)** - Para dados geográficos
- **PGVector (17)** ⭐ **SELECIONE ESTA!**

**Selecione "PGVector (17)"** - Esta opção já vem com pgvector pré-instalado!

### 3. Configurações Recomendadas

#### Nome do Serviço
```
postgres-rag
```

#### Tipo Selecionado
```
PGVector (17)
```

**⚠️ IMPORTANTE**: Se a opção "PGVector (17)" não aparecer, use a alternativa abaixo:

#### Alternativa: Imagem Docker Customizada
```
pgvector/pgvector:pg16
```

**Outras alternativas:**
- `pgvector/pgvector:pg15` (PostgreSQL 15)
- `ankane/pgvector:v0.5.1` (Alternativa)

#### Variáveis de Ambiente

Adicione as seguintes variáveis:

```env
POSTGRES_DB=chat_rag
POSTGRES_USER=chat_user
POSTGRES_PASSWORD=SUA_SENHA_FORTE_AQUI
POSTGRES_INITDB_ARGS=--encoding=UTF8 --locale=pt_BR.UTF-8
```

**⚠️ IMPORTANTE**: Substitua `SUA_SENHA_FORTE_AQUI` por uma senha forte!

#### Porta
```
5432
```

#### Volumes (Opcional)

Se quiser persistir dados em um volume específico:

**Host Path**: `/var/lib/coolify/postgres-rag`
**Container Path**: `/var/lib/postgresql/data`

### 4. Script de Inicialização (Opcional - Apenas se não usar PGVector do Coolify)

**⚠️ NOTA**: Se você selecionou **"PGVector (17)"** do Coolify, **NÃO PRECISA** deste script - a extensão já está instalada!

Se você usou uma imagem Docker customizada, pode criar um arquivo `init-pgvector.sql`:

```sql
-- Habilitar extensão pgvector
CREATE EXTENSION IF NOT EXISTS vector;

-- Verificar instalação
SELECT extname, extversion FROM pg_extension WHERE extname = 'vector';
```

E adicione como volume:

**Host Path**: `/caminho/para/init-pgvector.sql`
**Container Path**: `/docker-entrypoint-initdb.d/init-pgvector.sql`

---

## 🔗 CONECTAR SEU PROJETO PHP

### Variáveis de Ambiente no Projeto PHP

No seu projeto PHP no Coolify, adicione:

```env
POSTGRES_HOST=postgres-rag
POSTGRES_PORT=5432
POSTGRES_DB=chat_rag
POSTGRES_USER=chat_user
POSTGRES_PASSWORD=SUA_SENHA_FORTE_AQUI
```

**Nota**: O `POSTGRES_HOST` deve ser o **nome do serviço** no Coolify (não `localhost`).

### Rede Docker

Certifique-se de que:
1. Seu projeto PHP e PostgreSQL estão na **mesma rede Docker**
2. No Coolify, ambos os serviços devem estar no mesmo **"Network"**

---

## ✅ VERIFICAÇÃO RÁPIDA

Após criar o banco com a opção **"PGVector (17)"**, a extensão já deve estar instalada!

### Verificação via Terminal

Execute no terminal do Coolify:

```bash
# Conectar ao container PostgreSQL
docker exec -it postgres-rag psql -U chat_user -d chat_rag

# Verificar extensão pgvector (já deve estar instalada)
SELECT * FROM pg_extension WHERE extname = 'vector';

# Se por algum motivo não estiver instalado, instalar:
CREATE EXTENSION vector;

# Sair
\q
```

### Verificação via Script Web

Ou use o script de teste (mais fácil):

```
http://seu-dominio/test-postgres-pgvector.php
```

Este script vai verificar automaticamente:
- ✅ Variáveis de ambiente
- ✅ Extensão PHP PostgreSQL
- ✅ Conexão ao banco
- ✅ Extensão pgvector instalada
- ✅ Criação de tabela com vector
- ✅ Busca por similaridade

---

## 🐳 DOCKER COMPOSE ALTERNATIVO

Se preferir usar Docker Compose diretamente no servidor:

```yaml
version: '3.8'

services:
  postgres-rag:
    image: pgvector/pgvector:pg16
    container_name: postgres-rag
    restart: unless-stopped
    environment:
      POSTGRES_DB: chat_rag
      POSTGRES_USER: chat_user
      POSTGRES_PASSWORD: sua_senha_forte
      POSTGRES_INITDB_ARGS: "--encoding=UTF8 --locale=pt_BR.UTF-8"
    ports:
      - "5432:5432"
    volumes:
      - postgres_rag_data:/var/lib/postgresql/data
      - ./init-pgvector.sql:/docker-entrypoint-initdb.d/init-pgvector.sql
    networks:
      - coolify-network
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U chat_user -d chat_rag"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  postgres_rag_data:
    driver: local

networks:
  coolify-network:
    external: true
```

Salve como `docker-compose.postgres-rag.yml` e execute:

```bash
docker-compose -f docker-compose.postgres-rag.yml up -d
```

---

## 📚 RECURSOS

- **Documentação Completa**: `GUIA_INSTALACAO_POSTGRES_PGVECTOR_COOLIFY.md`
- **Script de Teste**: `public/test-postgres-pgvector.php`
- **Plano RAG**: `PLANO_SISTEMA_RAG.md`

---

**Última atualização**: 2025-01-27

