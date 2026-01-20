# 🐛 DEBUG - FONTES EXTERNAS

## ✅ Logs Adicionados

Agora o sistema registra **TODOS** os passos do teste de conexão nos logs!

---

## 📍 Como Debugar

### 1️⃣ **Acesse os Logs**

```
http://seu-dominio/view-all-logs.php
```

Ou abra diretamente:
```
C:\laragon\www\chat\public\view-all-logs.php
```

---

### 2️⃣ **Teste a Conexão**

1. Acesse `/external-sources/create`
2. Preencha os dados de conexão
3. Clique em **"Testar Conexão"**
4. **Abra o Console do navegador** (F12)
5. **Abra `view-all-logs.php` em outra aba**

---

### 3️⃣ **O Que Procurar nos Logs**

#### 🟢 **Logs de Sucesso (ordem esperada):**

```
[INFO] === TESTE DE CONEXÃO EXTERNA INICIADO ===

[INFO] Dados recebidos para teste de conexão
{
  "type": "mysql",
  "connection_config": {
    "host": "localhost",
    "port": "3306",
    "database": "meu_banco",
    "username": "root",
    "password": "***DEFINIDA***"
  }
}

[INFO] ExternalDataSourceService::testConnection - Iniciando teste
{
  "type": "mysql",
  "host": "localhost",
  "port": "3306",
  "database": "meu_banco",
  "username": "root"
}

[INFO] ExternalDataSourceService::testConnection - Criando conexão PDO

[INFO] ExternalDataSourceService::createConnection - Preparando conexão
{
  "type": "mysql",
  "host": "localhost",
  "port": "3306",
  "database": "meu_banco",
  "username": "root",
  "has_password": true
}

[INFO] ExternalDataSourceService::createConnection - DSN construído
{
  "dsn": "mysql:host=localhost;port=3306;dbname=meu_banco;charset=utf8mb4"
}

[INFO] ExternalDataSourceService::createConnection - Tentando criar PDO

[INFO] ExternalDataSourceService::createConnection - PDO criado com sucesso

[INFO] ExternalDataSourceService::testConnection - Conexão PDO criada, executando SELECT 1

[INFO] ExternalDataSourceService::testConnection - Query executada
{
  "result": {"test": 1}
}

[INFO] ExternalDataSourceService::testConnection - Teste bem-sucedido

[INFO] Resultado do teste de conexão
{
  "success": true,
  "message": "Conexão estabelecida com sucesso!"
}
```

---

#### 🔴 **Logs de Erro (exemplos):**

##### **Erro 1: Banco não existe**

```
[ERROR] ExternalDataSourceService::createConnection - Erro ao criar PDO
{
  "code": "1049",
  "message": "SQLSTATE[42000] [1049] Unknown database 'banco_inexistente'",
  "dsn": "mysql:host=localhost;port=3306;dbname=banco_inexistente;charset=utf8mb4",
  "username": "root"
}

[ERROR] ExternalDataSourceService::testConnection - Erro PDO
{
  "code": "1049",
  "message": "SQLSTATE[42000] [1049] Unknown database 'banco_inexistente'"
}
```

**Solução:** Verifique se o banco de dados existe!

---

##### **Erro 2: Senha incorreta**

```
[ERROR] ExternalDataSourceService::createConnection - Erro ao criar PDO
{
  "code": "1045",
  "message": "SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: YES)",
  "dsn": "mysql:host=localhost;port=3306;dbname=meu_banco;charset=utf8mb4",
  "username": "root"
}
```

**Solução:** Senha está errada!

---

##### **Erro 3: Host inacessível**

```
[ERROR] ExternalDataSourceService::createConnection - Erro ao criar PDO
{
  "code": "2002",
  "message": "SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed: Name or service not known",
  "dsn": "mysql:host=192.168.1.999;port=3306;dbname=meu_banco;charset=utf8mb4"
}
```

**Solução:** Host/IP incorreto ou servidor inacessível!

---

##### **Erro 4: Porta bloqueada**

```
[ERROR] ExternalDataSourceService::createConnection - Erro ao criar PDO
{
  "code": "2002",
  "message": "SQLSTATE[HY000] [2002] Connection refused",
  "dsn": "mysql:host=192.168.1.100;port=3306"
}
```

**Solução:** Porta bloqueada por firewall ou MySQL não está rodando!

---

##### **Erro 5: Driver não instalado**

```
[ERROR] ExternalDataSourceService::createConnection - Erro ao criar PDO
{
  "message": "could not find driver"
}
```

**Solução:** PHP não tem extensão PDO_MYSQL ou PDO_PGSQL instalada!

---

### 4️⃣ **Logs no Console do Navegador**

Além dos logs do backend, o **console do navegador** (F12) também mostra:

```javascript
// Console ao testar
Testando conexão com: {
  type: "mysql",
  host: "localhost",
  port: 3306,
  database: "meu_banco",
  username: "root",
  has_password: true
}

Response status: 200
Response headers: Headers { }
Response raw: {"success":true,"message":"Conexão estabelecida com sucesso!"}

Resultado do teste: {
  success: true,
  message: "Conexão estabelecida com sucesso!"
}
```

Se der erro:
```javascript
Response status: 400
Response raw: {"success":false,"message":"Erro de conexão PDO: Unknown database 'teste'","error_detail":"Verifique os logs em view-all-logs.php"}

Erro ao testar conexão: {
  success: false,
  message: "Erro de conexão PDO: Unknown database 'teste'"
}
```

---

## 🔧 Erros Comuns e Soluções

### ❌ "Erro de Rede"

**Causa:** Requisição nem chegou no backend ou retornou algo que não é JSON

**Debug:**
1. Abra Console (F12)
2. Veja a aba **Network**
3. Procure pela requisição `/api/external-sources/test-connection`
4. Veja:
   - **Status Code:** Deve ser 200 ou 400
   - **Response:** O que o servidor retornou

**Possíveis causas:**
- ❌ Rota não configurada
- ❌ Middleware bloqueando
- ❌ Erro de PHP fatal (syntax error)
- ❌ Arquivo `view-all-logs.php` vai mostrar o erro!

---

### ❌ "Unknown database"

**Causa:** Banco de dados não existe

**Solução:**
```sql
CREATE DATABASE nome_do_banco;
```

---

### ❌ "Access denied"

**Causa:** Usuário/senha incorretos ou usuário sem permissões

**Solução:**
```sql
-- Criar usuário
CREATE USER 'usuario'@'%' IDENTIFIED BY 'senha';
GRANT ALL PRIVILEGES ON banco.* TO 'usuario'@'%';
FLUSH PRIVILEGES;
```

---

### ❌ "Connection refused" ou "Network unreachable"

**Causa:** Servidor inacessível

**Checklist:**
- [ ] Servidor MySQL está rodando?
  ```bash
  # Windows
  netstat -ano | findstr :3306
  
  # Linux
  sudo service mysql status
  ```
- [ ] Firewall bloqueando?
- [ ] IP/Host correto?
- [ ] Porta correta? (MySQL=3306, PostgreSQL=5432)

---

### ❌ "could not find driver"

**Causa:** Extensão PDO não instalada

**Solução:**

**Windows (Laragon):**
1. Abrir `C:\laragon\bin\php\php-8.x\php.ini`
2. Descomentar (remover `;`):
   ```ini
   extension=pdo_mysql
   extension=pdo_pgsql
   ```
3. Reiniciar Apache

**Linux:**
```bash
# Ubuntu/Debian
sudo apt install php-mysql php-pgsql

# CentOS/RHEL
sudo yum install php-mysql php-pgsql

# Reiniciar servidor
sudo service apache2 restart
```

---

## 🎯 Passo a Passo para Debugar

### 1. Reproduzir o erro

1. Acesse `/external-sources/create`
2. Preencha dados de conexão
3. Abra **Console do navegador** (F12)
4. Abra **`view-all-logs.php`** em outra aba
5. Clique em **"Testar Conexão"**

---

### 2. Coletar informações

✅ **No Console do navegador:**
- Status HTTP da requisição
- Response raw (texto retornado)
- Mensagens de erro JavaScript

✅ **No `view-all-logs.php`:**
- Última linha com `=== TESTE DE CONEXÃO EXTERNA INICIADO ===`
- Todas as linhas `[INFO]` e `[ERROR]` seguintes
- Código de erro PDO (ex: 1049, 1045, 2002)

---

### 3. Identificar o problema

Compare os logs com os **exemplos de erro** acima para identificar a causa.

---

### 4. Aplicar solução

Veja a seção **"Erros Comuns e Soluções"** acima.

---

## 📋 Checklist de Verificação

Antes de testar conexão, verifique:

### Servidor MySQL/PostgreSQL
- [ ] Está rodando?
- [ ] Porta acessível?
- [ ] Firewall liberado?

### Banco de Dados
- [ ] Banco existe?
- [ ] Usuário existe?
- [ ] Usuário tem permissões?
- [ ] Senha correta?

### PHP
- [ ] Extensão PDO_MYSQL instalada?
- [ ] Extensão PDO_PGSQL instalada? (se usar PostgreSQL)
- [ ] PHP.ini configurado?

### Rede
- [ ] Host/IP correto?
- [ ] Porta correta?
- [ ] Rede acessível?

---

## 🆘 Ainda com Problemas?

### Compartilhe estas informações:

1. **Logs do `view-all-logs.php`:**
   - Copie TODAS as linhas desde `=== TESTE DE CONEXÃO EXTERNA INICIADO ===`

2. **Console do navegador:**
   - Screenshot da aba **Network**
   - Screenshot da aba **Console**

3. **Dados de conexão** (SEM SENHA!):
   ```
   Tipo: MySQL
   Host: localhost
   Porta: 3306
   Banco: meu_banco
   Usuário: root
   ```

4. **Ambiente:**
   ```
   SO: Windows 10 / Linux Ubuntu 20.04
   PHP: 8.1
   MySQL: 8.0
   Laragon: Sim/Não
   ```

---

## ✅ Melhorias Aplicadas

### Backend (Logs detalhados)
✅ Controller registra dados recebidos  
✅ Service registra cada passo da conexão  
✅ Erros PDO são logados com código e mensagem  
✅ DSN completo é registrado (sem senha)  
✅ Stack trace completo em erros  

### Frontend (Console detalhado)
✅ Dados enviados são logados  
✅ Response HTTP completo é exibido  
✅ Erros de parse JSON são capturados  
✅ Mensagens de erro mais descritivas  

---

**🎯 Agora você tem visibilidade TOTAL do que está acontecendo!**

Qualquer erro será capturado nos logs! 🔍
