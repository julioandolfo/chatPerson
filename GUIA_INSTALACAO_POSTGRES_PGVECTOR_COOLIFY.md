# 🐘 GUIA DE INSTALAÇÃO - POSTGRESQL + PGVECTOR NO COOLIFY

**Data**: 2025-01-27  
**Plataforma**: Coolify  
**Objetivo**: Configurar PostgreSQL com extensão pgvector para sistema RAG

---

## 📋 PRÉ-REQUISITOS

- Coolify instalado e funcionando
- Acesso ao servidor Coolify (SSH)
- Conhecimento básico de Docker/PostgreSQL

---

## 🚀 MÉTODO 1: USANDO OPÇÃO PGVECTOR DO COOLIFY (MAIS FÁCIL - RECOMENDADO)

### Passo 1: Criar Novo Banco de Dados no Coolify

1. Acesse o painel do Coolify
2. Vá em **"Databases"** ou **"Services"**
3. Clique em **"New Database"** ou **"Add Service"**
4. Selecione **"PostgreSQL"**

### Passo 2: Selecionar Tipo PGVector

Na tela de seleção de tipo de PostgreSQL, você verá as seguintes opções:

- **PostgreSQL 17 (default)** - PostgreSQL padrão sem extensões
- **Supabase PostgreSQL (with extensions)** - PostgreSQL com muitas extensões
- **PostGIS (AMD only)** - Para dados geográficos
- **PGVector (17)** ⭐ **SELECIONE ESTA OPÇÃO!**

**Selecione "PGVector (17)"** - Esta opção já vem com a extensão pgvector pré-instalada!

### Passo 3: Configurar Banco de Dados

**Configurações do Banco:**

- **Database Name**: `chat_rag` (ou o nome que preferir)
- **Username**: `chat_user` (ou o nome que preferir)
- **Password**: *(defina uma senha forte)*
- **Port**: `5432` (padrão)

A extensão pgvector já estará disponível automaticamente! ✅

---

## 🐳 MÉTODO 2: USANDO IMAGEM DOCKER CUSTOMIZADA (ALTERNATIVA)

Se por algum motivo a opção "PGVector (17)" não estiver disponível no seu Coolify, você pode usar uma imagem Docker customizada:

### Passo 1: Criar Novo Banco de Dados no Coolify

1. Acesse o painel do Coolify
2. Vá em **"Databases"** ou **"Services"**
3. Clique em **"New Database"** ou **"Add Service"**
4. Selecione **"PostgreSQL"**

### Passo 2: Configurar PostgreSQL com pgvector

#### Opção A: Usar Imagem Oficial com pgvector

**Configurações do Banco:**

- **Image**: `pgvector/pgvector:pg16` (ou `pgvector/pgvector:pg15` para PostgreSQL 15)
- **Version**: `16` (ou `15`)
- **Database Name**: `chat_rag` (ou o nome que preferir)
- **Username**: `chat_user` (ou o nome que preferir)
- **Password**: *(defina uma senha forte)*
- **Port**: `5432` (padrão)

**Variáveis de Ambiente (Environment Variables):**

```env
POSTGRES_DB=chat_rag
POSTGRES_USER=chat_user
POSTGRES_PASSWORD=sua_senha_forte_aqui
POSTGRES_INITDB_ARGS=--encoding=UTF8 --locale=pt_BR.UTF-8
```

#### Opção B: Usar Imagem Ankane/pgvector (Alternativa)

**Image**: `ankane/pgvector:v0.5.1`

Esta imagem já vem com pgvector pré-instalado e é mantida pela comunidade.

### Passo 4: Verificar Instalação (Método 1 - PGVector do Coolify)

Se você usou a opção **"PGVector (17)"** do Coolify, a extensão já está instalada! Apenas verifique:

### Passo 3: Criar Script de Inicialização (Método 2 - Docker Customizado)

**⚠️ NOTA**: Se você usou a opção "PGVector (17)" do Coolify, **PULE ESTE PASSO** - a extensão já está instalada!

Se você usou uma imagem Docker customizada, pode adicionar um **"Init Script"** ou criar um volume com script SQL:

**Criar arquivo `init-pgvector.sql`:**

```sql
-- Habilitar extensão pgvector
CREATE EXTENSION IF NOT EXISTS vector;

-- Verificar se foi instalado corretamente
SELECT * FROM pg_extension WHERE extname = 'vector';
```

**Como adicionar no Coolify:**

1. Vá em **"Volumes"** do seu banco PostgreSQL
2. Adicione um volume:
   - **Host Path**: `/path/to/init-pgvector.sql`
   - **Container Path**: `/docker-entrypoint-initdb.d/init-pgvector.sql`
3. O PostgreSQL executará automaticamente scripts em `/docker-entrypoint-initdb.d/` na primeira inicialização

### Passo 4: Verificar Instalação

Após o banco estar rodando, conecte via terminal ou ferramenta de banco:

```sql
-- Conectar ao banco
\c chat_rag

-- Verificar extensão
SELECT * FROM pg_extension WHERE extname = 'vector';

-- Testar criação de tabela com vector
CREATE TABLE test_vectors (
    id SERIAL PRIMARY KEY,
    embedding vector(1536)
);

-- Inserir teste
INSERT INTO test_vectors (embedding) 
VALUES ('[0.1,0.2,0.3]'::vector);

-- Verificar
SELECT * FROM test_vectors;

-- Limpar teste
DROP TABLE test_vectors;
```

---

## 🐳 MÉTODO 2: DOCKER COMPOSE MANUAL (SE NECESSÁRIO)

Se o Coolify não tiver suporte direto, você pode criar um Docker Compose:

**Arquivo `docker-compose.postgres.yml`:**

```yaml
version: '3.8'

services:
  postgres-pgvector:
    image: pgvector/pgvector:pg16
    container_name: chat_postgres_rag
    restart: unless-stopped
    environment:
      POSTGRES_DB: chat_rag
      POSTGRES_USER: chat_user
      POSTGRES_PASSWORD: sua_senha_forte_aqui
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

**Para usar:**

1. Salve o arquivo no servidor Coolify
2. Execute: `docker-compose -f docker-compose.postgres.yml up -d`
3. Verifique logs: `docker-compose -f docker-compose.postgres.yml logs -f`

---

## 🔧 CONFIGURAÇÃO NO SEU PROJETO PHP

### 1. Instalar Driver PostgreSQL para PHP

No seu projeto PHP, adicione ao `composer.json`:

```json
{
    "require": {
        "doctrine/dbal": "^3.6"
    }
}
```

Ou instale extensão PHP PostgreSQL:

```bash
# No servidor (se tiver acesso)
sudo apt-get install php-pgsql php-pdo-pgsql

# Ou via Dockerfile
RUN docker-php-ext-install pgsql pdo_pgsql
```

### 2. Configurar Conexão

**Arquivo `config/database.php` ou similar:**

```php
<?php

return [
    'mysql' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: 3306,
        'database' => getenv('DB_DATABASE'),
        'username' => getenv('DB_USERNAME'),
        'password' => getenv('DB_PASSWORD'),
    ],
    
    'postgres' => [
        'host' => getenv('POSTGRES_HOST') ?: 'localhost',
        'port' => getenv('POSTGRES_PORT') ?: 5432,
        'database' => getenv('POSTGRES_DB') ?: 'chat_rag',
        'username' => getenv('POSTGRES_USER') ?: 'chat_user',
        'password' => getenv('POSTGRES_PASSWORD'),
        'driver' => 'pgsql',
        'charset' => 'utf8',
    ],
];
```

### 3. Criar Helper para Conexão PostgreSQL

**Arquivo `app/Helpers/PostgreSQL.php`:**

```php
<?php

namespace App\Helpers;

use PDO;
use PDOException;

class PostgreSQL
{
    private static ?PDO $connection = null;

    /**
     * Obter conexão PostgreSQL
     */
    public static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $host = getenv('POSTGRES_HOST') ?: 'localhost';
        $port = getenv('POSTGRES_PORT') ?: 5432;
        $database = getenv('POSTGRES_DB') ?: 'chat_rag';
        $username = getenv('POSTGRES_USER') ?: 'chat_user';
        $password = getenv('POSTGRES_PASSWORD');

        if (empty($password)) {
            throw new \Exception('POSTGRES_PASSWORD não configurado');
        }

        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";

        try {
            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Verificar se pgvector está instalado
            self::checkPgvectorExtension();

            return self::$connection;
        } catch (PDOException $e) {
            throw new \Exception("Erro ao conectar PostgreSQL: " . $e->getMessage());
        }
    }

    /**
     * Verificar se extensão pgvector está instalada
     */
    private static function checkPgvectorExtension(): void
    {
        $stmt = self::$connection->query("SELECT * FROM pg_extension WHERE extname = 'vector'");
        $result = $stmt->fetch();

        if (empty($result)) {
            throw new \Exception('Extensão pgvector não está instalada no PostgreSQL');
        }
    }

    /**
     * Executar query
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Executar query e retornar primeira linha
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Executar comando (INSERT, UPDATE, DELETE)
     */
    public static function execute(string $sql, array $params = []): bool
    {
        $stmt = self::getConnection()->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Inserir e retornar ID
     */
    public static function insert(string $sql, array $params = []): int
    {
        $stmt = self::getConnection()->prepare($sql . ' RETURNING id');
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['id'] ?? 0;
    }
}
```

### 4. Variáveis de Ambiente no Coolify

No seu projeto PHP no Coolify, adicione as variáveis de ambiente:

```env
# PostgreSQL RAG
POSTGRES_HOST=postgres-pgvector  # Nome do serviço no Coolify
POSTGRES_PORT=5432
POSTGRES_DB=chat_rag
POSTGRES_USER=chat_user
POSTGRES_PASSWORD=sua_senha_forte_aqui
```

**Como adicionar no Coolify:**

1. Vá no seu projeto PHP
2. Clique em **"Environment Variables"**
3. Adicione cada variável acima
4. Salve e reinicie o serviço

---

## 📊 CRIAR TABELAS DO SISTEMA RAG

Após configurar a conexão, execute as migrations do sistema RAG:

**Arquivo `database/migrations/060_create_ai_knowledge_base_table.php`:**

```php
<?php

function up_ai_knowledge_base()
{
    $pgsql = \App\Helpers\PostgreSQL::getConnection();
    
    $sql = "
    CREATE TABLE IF NOT EXISTS ai_knowledge_base (
        id SERIAL PRIMARY KEY,
        ai_agent_id INT NOT NULL,
        content_type VARCHAR(50) NOT NULL,
        title VARCHAR(500),
        content TEXT NOT NULL,
        source_url VARCHAR(1000),
        metadata JSONB,
        embedding vector(1536),
        chunk_index INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT NOW(),
        updated_at TIMESTAMP DEFAULT NOW()
    );
    
    CREATE INDEX IF NOT EXISTS idx_knowledge_agent ON ai_knowledge_base(ai_agent_id);
    CREATE INDEX IF NOT EXISTS idx_knowledge_type ON ai_knowledge_base(content_type);
    CREATE INDEX IF NOT EXISTS idx_knowledge_embedding ON ai_knowledge_base 
        USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);
    ";
    
    $pgsql->exec($sql);
    echo "✅ Tabela 'ai_knowledge_base' criada com sucesso!\n";
}
```

**Executar migration:**

```php
// Em um script de setup ou via CLI
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';

up_ai_knowledge_base();
```

---

## 🧪 TESTAR INSTALAÇÃO

### Teste 1: Verificar Conexão

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Helpers\PostgreSQL;

try {
    $conn = PostgreSQL::getConnection();
    echo "✅ Conexão PostgreSQL estabelecida!\n";
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
```

### Teste 2: Verificar pgvector

```php
<?php
use App\Helpers\PostgreSQL;

$result = PostgreSQL::query("SELECT * FROM pg_extension WHERE extname = 'vector'");

if (!empty($result)) {
    echo "✅ pgvector está instalado!\n";
    print_r($result);
} else {
    echo "❌ pgvector NÃO está instalado!\n";
}
```

### Teste 3: Criar Tabela com Vector

```php
<?php
use App\Helpers\PostgreSQL;

// Criar tabela de teste
PostgreSQL::execute("
    CREATE TABLE IF NOT EXISTS test_vectors (
        id SERIAL PRIMARY KEY,
        text_content TEXT,
        embedding vector(1536)
    )
");

// Inserir teste
PostgreSQL::execute("
    INSERT INTO test_vectors (text_content, embedding) 
    VALUES (?, ?::vector)
", [
    'Teste de embedding',
    '[0.1,0.2,0.3]' // Exemplo de embedding (1536 dimensões)
]);

echo "✅ Tabela de teste criada e dados inseridos!\n";

// Buscar
$results = PostgreSQL::query("SELECT * FROM test_vectors");
print_r($results);

// Limpar
PostgreSQL::execute("DROP TABLE test_vectors");
```

---

## 🔍 TROUBLESHOOTING

### Problema 1: Extensão pgvector não encontrada

**Solução:**
```sql
-- Conectar ao banco como superuser
CREATE EXTENSION vector;

-- Verificar
\dx vector
```

### Problema 2: Erro ao criar índice IVFFlat

**Solução:**
```sql
-- Criar índice com menos lists (para bases pequenas)
CREATE INDEX idx_knowledge_embedding ON ai_knowledge_base 
USING ivfflat (embedding vector_cosine_ops) WITH (lists = 10);
```

### Problema 3: Conexão recusada

**Verificações:**
1. Banco está rodando? `docker ps | grep postgres`
2. Porta está correta? Verifique no Coolify
3. Variáveis de ambiente estão configuradas?
4. Rede Docker está correta? (se usar Docker Compose)

### Problema 4: Erro de autenticação

**Solução:**
1. Verifique usuário/senha no Coolify
2. Verifique se banco aceita conexões do seu IP
3. Verifique `pg_hba.conf` (se tiver acesso)

---

## 📚 RECURSOS ÚTEIS

### Documentação Oficial

- **pgvector**: https://github.com/pgvector/pgvector
- **Coolify**: https://coolify.io/docs
- **PostgreSQL**: https://www.postgresql.org/docs/

### Imagens Docker Recomendadas

- `pgvector/pgvector:pg16` - PostgreSQL 16 com pgvector
- `pgvector/pgvector:pg15` - PostgreSQL 15 com pgvector
- `ankane/pgvector:v0.5.1` - Alternativa mantida pela comunidade

### Versões Suportadas

- **PostgreSQL**: 11, 12, 13, 14, 15, 16
- **pgvector**: 0.5.0+
- **PHP**: 8.1+ com extensão pgsql

---

## ✅ CHECKLIST DE INSTALAÇÃO

- [ ] PostgreSQL criado no Coolify com imagem pgvector
- [ ] Variáveis de ambiente configuradas
- [ ] Script de inicialização criado (opcional)
- [ ] Extensão pgvector verificada no banco
- [ ] Conexão PostgreSQL testada no PHP
- [ ] Helper PostgreSQL criado
- [ ] Migrations do RAG executadas
- [ ] Testes de inserção/busca funcionando

---

## 🎯 PRÓXIMOS PASSOS

Após instalar PostgreSQL + pgvector:

1. ✅ Executar migrations do sistema RAG
2. ✅ Implementar `RAGService` com busca semântica
3. ✅ Integrar com `OpenAIService` para embeddings
4. ✅ Testar sistema completo

**Ver**: `PLANO_SISTEMA_RAG.md` para detalhes de implementação

---

**Última atualização**: 2025-01-27  
**Versão**: 1.0

