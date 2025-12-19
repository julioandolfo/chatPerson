# ✅ IMPLEMENTAÇÃO COMPLETA - Ramificação de IA Baseada em Intent

**Data**: 2025-12-19  
**Status**: ✅ **IMPLEMENTADO**

---

## 📋 Resumo

Sistema completo de ramificação baseada em intent para nós de Agente de IA nas automações. Permite que a IA analise suas próprias respostas e roteie a conversa para diferentes nós baseado no entendimento/intent detectado.

---

## ✅ O que foi Implementado

### 1. Backend (`app/Services/AutomationService.php`)

#### Novos Métodos:

**`handleAIBranchingResponse()`**
- Analisa mensagens enviadas pela IA
- Detecta intents baseado em palavras-chave
- Roteia para nós específicos quando intent é identificado
- Gerencia contador de interações
- Escala para humano quando atinge máximo

**`detectAIIntent()`**
- Analisa resposta da IA
- Compara com palavras-chave configuradas
- Retorna intent com maior score de match
- Suporta múltiplas palavras-chave por intent

**`escalateFromAI()`**
- Marca AIConversation como 'escalated'
- Tenta atribuir a agente humano automaticamente
- Envia mensagem de sistema informando escalação
- Executa nó de fallback se configurado
- Limpa metadata de ramificação

#### Modificações:

**`executeAssignAIAgent()`**
- Salva configuração de ramificação no metadata da conversa
- Armazena intents, fallback node, max interactions
- Inicializa contador de interações

**`executeForMessageReceived()`**
- Verifica se ramificação de IA está ativa
- Chama `handleAIBranchingResponse()` para mensagens da IA
- Prioridade: Ramificação IA → Chatbot → Automações normais

---

### 2. Frontend (`views/automations/show.php`)

#### Formulário de Configuração:

**Novos Campos:**
- ✅ Checkbox "Habilitar ramificação baseada em intent"
- ✅ Container expansível com configurações
- ✅ Lista dinâmica de intents
- ✅ Botão "Adicionar Intent"
- ✅ Máximo de interações (input number)
- ✅ Checkbox "Escalar automaticamente se ficar preso"
- ✅ Select de nó de fallback

**Cada Intent contém:**
- Nome do intent (identificador)
- Descrição (legível)
- Palavras-chave (separadas por vírgula)
- Nó de destino (select)
- Botão remover

#### Funções JavaScript:

**`toggleAIBranchingContainer()`**
- Mostra/oculta container de ramificação

**`addAIIntent()`**
- Adiciona novo card de intent
- Gera formulário com índice correto
- Popula select de nós disponíveis

**`removeAIIntent()`**
- Remove intent
- Renumera intents restantes

**`populateAIFallbackNodes()`**
- Preenche select de fallback com nós disponíveis
- Exclui trigger e o próprio nó de IA

**`populateAIIntentTargetNodes()`**
- Preenche select de target para cada intent
- Exclui trigger e o próprio nó de IA

**`populateAIIntents()`**
- Carrega intents existentes ao editar nó
- Preenche todos os campos corretamente
- Converte keywords de array para string

#### Salvamento de Dados:

- Coleta todos os intents do formulário
- Converte keywords de string para array
- Valida que intent tem nome e target_node_id
- Salva em `node_data.ai_intents`

#### Renderização Visual:

- Detecta se nó tem ramificação habilitada
- Renderiza handles múltiplos (um por intent)
- Cada handle tem ícone 🎯 e descrição do intent
- Cor específica para handles de IA (#6366f1)
- Similar ao chatbot menu

---

## 🎯 Como Usar

### 1. Criar Automação com IA

```
[Trigger: Conversa movida para "Ganho"]
    ↓
[Condição: Sem interação há 2h]
    ↓
[Atribuir Agente de IA: WooCommerce Assistant]
    - Processar imediatamente: ✓
    - Ramificação habilitada: ✓
    - Intents:
        1. "status_pedido" → [Consultar Pedido]
        2. "problema_entrega" → [Escalar Suporte]
        3. "duvida_produto" → [Enviar Catálogo]
    - Max interações: 5
    - Fallback: [Escalar para Humano]
```

### 2. Configurar Intents

**Intent 1: Status do Pedido**
- Nome: `status_pedido`
- Descrição: Cliente perguntando sobre status do pedido
- Keywords: `pedido, entrega, rastreamento, código, status`
- Target: Nó "Consultar Pedido no WooCommerce"

**Intent 2: Problema de Entrega**
- Nome: `problema_entrega`
- Descrição: Cliente com problema na entrega
- Keywords: `problema, não chegou, atrasado, errado, danificado`
- Target: Nó "Escalar para Suporte"

**Intent 3: Dúvida sobre Produto**
- Nome: `duvida_produto`
- Descrição: Cliente com dúvida sobre produto
- Keywords: `produto, especificação, tamanho, cor, modelo`
- Target: Nó "Enviar Catálogo"

### 3. Fluxo de Execução

1. **IA é atribuída à conversa**
   - Metadata salvo com configuração de ramificação
   - Contador de interações = 0

2. **IA responde ao cliente**
   - Sistema detecta que é mensagem da IA
   - Analisa conteúdo da resposta
   - Busca palavras-chave dos intents

3. **Intent detectado**
   - Sistema identifica intent com maior score
   - Executa nó de destino configurado
   - Limpa metadata de ramificação
   - Continua fluxo normal

4. **Intent não detectado**
   - Incrementa contador de interações
   - IA continua respondendo

5. **Máximo de interações atingido**
   - Escala para agente humano
   - Envia mensagem de sistema
   - Executa nó de fallback (se configurado)
   - Limpa metadata

---

## 📊 Estrutura de Dados

### Metadata da Conversa

```json
{
  "ai_branching_active": true,
  "ai_branching_node_id": "node_123",
  "ai_branching_automation_id": 456,
  "ai_intents": [
    {
      "intent": "status_pedido",
      "description": "Cliente perguntando sobre status",
      "keywords": ["pedido", "entrega", "rastreamento"],
      "target_node_id": "node_789"
    }
  ],
  "ai_fallback_node_id": "node_999",
  "ai_max_interactions": 5,
  "ai_interaction_count": 2
}
```

### Node Data

```json
{
  "node_type": "action_assign_ai_agent",
  "ai_agent_id": 123,
  "process_immediately": true,
  "assume_conversation": false,
  "only_if_unassigned": false,
  "ai_branching_enabled": true,
  "ai_intents": [
    {
      "intent": "status_pedido",
      "description": "Cliente perguntando sobre status",
      "keywords": ["pedido", "entrega", "rastreamento"],
      "target_node_id": "node_789"
    }
  ],
  "ai_fallback_node_id": "node_999",
  "ai_max_interactions": 5,
  "ai_escalate_on_stuck": true
}
```

---

## 🔍 Detecção de Intent

### Método Atual: Palavras-chave

- Busca por palavras-chave na resposta da IA
- Case-insensitive
- Score = número de keywords encontradas
- Retorna intent com maior score

### Exemplo:

**Resposta da IA:**
> "Claro! Deixa eu verificar o status do seu pedido #123. Vou consultar o rastreamento..."

**Keywords configuradas:**
- Intent 1: `["pedido", "entrega", "rastreamento"]` → **Score: 3** ✅
- Intent 2: `["problema", "danificado"]` → Score: 0
- Intent 3: `["produto", "especificação"]` → Score: 0

**Resultado:** Intent 1 detectado → Executa nó de "Consultar Pedido"

---

## 🚀 Melhorias Futuras

### 1. Análise Avançada de Intent

Usar OpenAI para análise semântica:

```php
private static function detectAIIntentAdvanced(string $aiResponse, array $intents): ?array
{
    $prompt = "Analise a seguinte resposta e identifique o intent:\n\n";
    $prompt .= "Resposta: \"{$aiResponse}\"\n\n";
    $prompt .= "Intents disponíveis:\n";
    
    foreach ($intents as $intent) {
        $prompt .= "- {$intent['intent']}: {$intent['description']}\n";
    }
    
    $response = \App\Services\OpenAIService::chat([
        ['role' => 'system', 'content' => 'Você é um analisador de intenções.'],
        ['role' => 'user', 'content' => $prompt]
    ]);
    
    // Processar resposta e retornar intent
}
```

### 2. Function Calling

Usar function calling da OpenAI para detecção precisa:

```php
$functions = [
    [
        'name' => 'identify_intent',
        'description' => 'Identifica o intent da resposta da IA',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'intent' => [
                    'type' => 'string',
                    'enum' => array_column($intents, 'intent')
                ],
                'confidence' => [
                    'type' => 'number',
                    'description' => 'Confiança de 0 a 1'
                ]
            ]
        ]
    ]
];
```

### 3. Dashboard de Analytics

- Taxa de detecção de cada intent
- Tempo médio até escalação
- Intents mais comuns
- Taxa de sucesso de resolução
- Análise de performance por agente de IA

### 4. A/B Testing

- Testar diferentes conjuntos de keywords
- Comparar performance de intents
- Otimizar automaticamente

---

## 📝 Logs e Debug

### Logs Automáticos

```
🤖 Ramificação de IA ATIVA detectada!
Mensagem da IA detectada, analisando intent...
Detectando intent. Total de intents configurados: 3
Intent 'status_pedido' matched 3 keyword(s): pedido, entrega, rastreamento
Melhor match: status_pedido com score 3
Intent detectado: status_pedido
Executando nó de destino: node_789
✅ Ramificação tratou a mensagem. Roteou para nó específico.
```

### Debug no Console

```javascript
console.log('AI Branching Config:', {
    enabled: node.node_data.ai_branching_enabled,
    intents: node.node_data.ai_intents,
    max_interactions: node.node_data.ai_max_interactions
});
```

---

## ✅ Checklist de Implementação

### Backend
- [x] Método `handleAIBranchingResponse()`
- [x] Método `detectAIIntent()`
- [x] Método `escalateFromAI()`
- [x] Modificação em `executeAssignAIAgent()`
- [x] Integração em `executeForMessageReceived()`

### Frontend
- [x] Formulário de configuração de ramificação
- [x] Lista dinâmica de intents
- [x] Funções JavaScript (add/remove/populate)
- [x] Salvamento de intents
- [x] Renderização visual com handles múltiplos
- [x] Carregamento de intents existentes

### Testes
- [ ] Teste unitário de detecção de intent
- [ ] Teste de escalação automática
- [ ] Teste de fluxo completo
- [ ] Teste de múltiplos intents
- [ ] Teste de fallback node

---

## 🎉 Conclusão

Sistema completo de ramificação baseada em intent implementado com sucesso! A IA agora pode analisar suas próprias respostas e rotear a conversa para diferentes nós baseado no entendimento, criando fluxos dinâmicos e inteligentes.

**Próximos passos sugeridos:**
1. Testar fluxo completo em ambiente de desenvolvimento
2. Criar automações de exemplo
3. Implementar análise avançada via OpenAI (opcional)
4. Adicionar dashboard de analytics (opcional)

