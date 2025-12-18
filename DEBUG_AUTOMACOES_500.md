# Debug: Erro 500 ao Salvar Layout de Automação

## Sintomas
- Erro 500 ao salvar layout
- Response body vazio
- Console mostra: `Unexpected identifier 'id'`
- Log: `Error: HTTP error! status: 500, body: `

## Correções Aplicadas

### 1. Controller (`app/Controllers/AutomationController.php`)
- ✅ Shutdown handler para capturar erros fatais
- ✅ Limpeza agressiva de output buffers
- ✅ Supressão de warnings/notices
- ✅ Try-catch por nó individual com logs detalhados
- ✅ JSON enviado diretamente (sem usar Response::json())
- ✅ Headers explícitos em todos os pontos de saída

### 2. JavaScript (`views/automations/show.php`)
- ✅ Sintaxe compatível (sem arrow functions/optional chaining)
- ✅ Validação de `nodes` antes de usar
- ✅ Fallback para `validateAutomationConnections`

## Como Debugar

### 1. Ver Logs em Tempo Real
Acesse: `http://seu-dominio/view-automation-logs.php`

### 2. Verificar Logs Manualmente
```bash
tail -f storage/logs/automation.log
```

### 3. Verificar Erro PHP
```bash
tail -f storage/logs/php_errors.log
# ou
tail -f /var/log/apache2/error.log
```

## Possíveis Causas do Erro 500

### A. Erro de Validação no Service
Se o `AutomationService::updateNode()` ou `createNode()` estiver falhando:
- Verificar validação de `node_data` (pode não aceitar arrays vazios)
- Verificar se campos obrigatórios estão presentes

### B. Erro de Banco de Dados
- JSON mal formatado em `node_data`
- Campo muito grande para coluna
- Foreign key constraint

### C. Timeout ou Memory Limit
- Muitos nós sendo processados
- Recursão infinita em conexões
- Array muito grande

## Próximos Passos

1. **Teste novamente** salvando o layout
2. **Acesse os logs**: `/view-automation-logs.php`
3. **Procure por**:
   - `saveLayout - Erro:`
   - `ERRO:`
   - Stack traces
4. **Me envie**:
   - Últimas 50 linhas do log
   - Mensagem de erro específica
   - Qual nó estava sendo processado quando falhou

## Teste Simplificado

Se continuar falhando, tente:

1. **Criar automação nova e vazia**
2. **Adicionar apenas 1 nó (trigger)**
3. **Salvar**
4. **Se funcionar**: adicionar mais nós um por um até encontrar o problemático
5. **Se não funcionar**: o erro é na estrutura base (migrations/models)

## Verificar Estrutura do Banco

```sql
-- Verificar estrutura da tabela
DESCRIBE automation_nodes;

-- Ver node_data de nós existentes
SELECT id, node_type, node_data FROM automation_nodes LIMIT 5;

-- Verificar se há node_data corrompido
SELECT id, node_type, LENGTH(node_data) as data_size 
FROM automation_nodes 
WHERE node_data IS NOT NULL 
ORDER BY data_size DESC 
LIMIT 10;
```

## Workaround Temporário

Se precisar salvar urgentemente:

1. Abra console do navegador
2. Execute:
```javascript
console.log(JSON.stringify(nodes, null, 2));
```
3. Copie o JSON
4. Insira manualmente no banco via PHPMyAdmin/SQL

