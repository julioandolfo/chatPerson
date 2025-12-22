# 🔍 Validação de Tools de Agentes de IA

## 📋 Resumo

Sistema completo de validação para garantir a integridade das tools dos agentes de IA, verificando:
- ✅ Estrutura do `function_schema` (formato OpenAI)
- ✅ Correspondência entre `slug` e `function name`
- ✅ Integração com OpenAIService (montagem do payload)
- ✅ Execução das tools (métodos correspondentes)
- ✅ Validação de tipos e campos obrigatórios

## 🛠️ Componentes Criados

### 1. AIToolValidationService
**Arquivo**: `app/Services/AIToolValidationService.php`

Serviço completo de validação com os seguintes métodos:

- `validateAllTools()` - Valida todas as tools do sistema
- `validateTool(array $tool)` - Valida uma tool específica
- `validateOpenAIIntegration(int $agentId)` - Valida integração de um agente com OpenAI
- `validateToolCallExecution(string $functionName, int $agentId)` - Valida execução de uma tool call
- `generateReport()` - Gera relatório completo de validação

### 2. Correções no OpenAIService
**Arquivo**: `app/Services/OpenAIService.php`

Corrigido o tratamento do `function_schema` para suportar ambos os formatos:
- Formato completo: `{ "type": "function", "function": { ... } }`
- Formato direto: `{ "name": "...", "description": "...", "parameters": {...} }`

### 3. Endpoints de Validação
**Arquivo**: `app/Controllers/AIToolController.php`

Novos métodos adicionados:
- `validate()` - Valida todas as tools (GET `/ai-tools/validate/all`)
- `validateTool(int $id)` - Valida uma tool específica (GET `/ai-tools/{id}/validate`)

### 4. Script CLI
**Arquivo**: `public/validate-ai-tools.php`

Script de linha de comando para validação:

```bash
# Validar todas as tools
php public/validate-ai-tools.php

# Validar tool específica
php public/validate-ai-tools.php --tool-id=1

# Validar integração de um agente
php public/validate-ai-tools.php --agent-id=1

# Formato JSON
php public/validate-ai-tools.php --format=json

# Ajuda
php public/validate-ai-tools.php --help
```

## ✅ Validações Realizadas

### 1. Campos Obrigatórios
- `name` - Nome da tool
- `slug` - Slug único
- `tool_type` - Tipo da tool
- `function_schema` - Schema da função

### 2. Estrutura do function_schema
Valida se o schema está no formato correto da OpenAI:
- Deve conter `name` ou estrutura com `type='function'` e `function`
- Deve conter `description` (recomendado)
- Deve conter `parameters` com estrutura válida
- `parameters.type` deve ser `'object'`
- `parameters.properties` deve ser um objeto/array

### 3. Correspondência Slug ↔ Function Name
Valida se o `slug` da tool corresponde ao `name` no `function_schema`:
- Extrai o `name` do schema
- Compara com o `slug`
- Erro se não corresponderem

### 4. Tipos de Tools Válidos
Valida se o `tool_type` está na lista de tipos permitidos:
- `system`
- `woocommerce`
- `database`
- `n8n`
- `document`
- `api`
- `followup`

### 5. Execução de Tools
Verifica se existe método de execução correspondente no `OpenAIService`:
- Valida se o `function_name` está na lista de funções conhecidas para o tipo
- Gera aviso se não estiver

### 6. Integração com OpenAI
Valida o formato que será enviado para a API OpenAI:
- Verifica se todas as tools têm `function_schema` válido
- Normaliza formato (envolve se necessário)
- Valida estrutura final do payload

### 7. Execução de Tool Calls
Valida quando uma tool é chamada:
- Verifica se tool existe e está habilitada
- Verifica se tool está atribuída ao agente
- Valida método de execução

## 📊 Formato do Relatório

### Validação de Tool Individual
```json
{
  "valid": true,
  "errors": [],
  "warnings": []
}
```

### Relatório Completo
```json
{
  "timestamp": "2024-01-01 12:00:00",
  "tools_validation": {
    "total": 10,
    "valid": 8,
    "invalid": 2,
    "errors": [...],
    "warnings": [...],
    "tools": [...]
  },
  "agents_with_tools": [...]
}
```

## 🔧 Como Usar

### Via API (Web)
```bash
# Validar todas as tools
GET /ai-tools/validate/all

# Validar tool específica
GET /ai-tools/1/validate
```

### Via CLI
```bash
# Validar todas as tools
php public/validate-ai-tools.php

# Validar tool específica
php public/validate-ai-tools.php --tool-id=1

# Validar agente específico
php public/validate-ai-tools.php --agent-id=1

# Formato JSON
php public/validate-ai-tools.php --format=json
```

## 🐛 Problemas Corrigidos

### 1. Formato do function_schema
**Problema**: O `OpenAIService` estava envolvendo o `function_schema` novamente mesmo quando já estava no formato completo.

**Solução**: Adicionada verificação para detectar o formato e normalizar corretamente:
- Se já tem `type='function'`, usa diretamente
- Se tem apenas `name`, envolve com `type` e `function`

### 2. Correspondência Slug ↔ Function Name
**Problema**: Não havia validação se o `slug` corresponde ao `name` no schema.

**Solução**: Adicionada validação que extrai o `name` do schema e compara com o `slug`.

### 3. Validação de Execução
**Problema**: Não havia verificação se a tool pode ser executada.

**Solução**: Adicionada validação que verifica se existe método correspondente no `OpenAIService`.

## 📝 Exemplo de Saída

### Validação de Tool Individual
```
🔍 Validação da Tool: Buscar Conversas Anteriores (ID: 1)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Tool válida!
```

### Relatório Completo
```
🔍 Relatório de Validação de Tools de IA
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Data: 2024-01-01 12:00:00

📊 Estatísticas Gerais:
   Total de tools: 6
   ✅ Válidas: 6
   ❌ Inválidas: 0

📋 Detalhes por Tool:
   ✅ Buscar Conversas Anteriores (slug: buscar_conversas_anteriores)
   ✅ Adicionar Tag (slug: adicionar_tag)
   ✅ Mover para Estágio (slug: mover_para_estagio)
   ✅ Escalar para Humano (slug: escalar_para_humano)
   ✅ Verificar Status da Conversa (slug: verificar_status_conversa)
   ✅ Verificar Última Interação (slug: verificar_ultima_interacao)

🤖 Agentes com Tools:
   • Agente de Suporte (ID: 1) - 4 tools
```

## 🔄 Integração com CI/CD

O script CLI pode ser integrado em pipelines de CI/CD:

```yaml
# Exemplo GitHub Actions
- name: Validar Tools de IA
  run: php public/validate-ai-tools.php --format=json > validation-report.json
  continue-on-error: true

- name: Verificar Resultados
  run: |
    if grep -q '"invalid": [1-9]' validation-report.json; then
      echo "❌ Tools inválidas encontradas!"
      exit 1
    fi
```

## 📚 Próximos Passos

1. ✅ Validação básica implementada
2. ✅ Script CLI criado
3. ✅ Endpoints de API criados
4. ⏳ Adicionar validação automática antes de salvar tool
5. ⏳ Adicionar validação automática ao atribuir tool a agente
6. ⏳ Dashboard de validação na interface web

## 🎯 Conclusão

O sistema de validação garante que:
- ✅ Todas as tools têm estrutura válida
- ✅ Integração com OpenAI está correta
- ✅ Tools podem ser executadas corretamente
- ✅ Problemas são detectados antes de causar erros em produção

