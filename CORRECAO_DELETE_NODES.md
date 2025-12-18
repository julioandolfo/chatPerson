# 🐛 Correção: Deletar Nós não Funciona

## Problema
Ao deletar nós do diagrama e salvar o layout, os nós não são removidos do banco de dados.

## Diagnóstico Aplicado

### 1. Frontend - `deleteNode()`
Adicionados logs extensivos:
```javascript
console.log('deleteNode - Deletando nó:', nodeId, 'tipo:', typeof nodeId);
console.log('deleteNode - IDs no array:', nodes.map(n => n.id + ' (' + typeof n.id + ')'));
console.log('deleteNode - IDs restantes:', nodes.map(n => n.id));
```

**Possível Causa:** Comparação estrita de tipos (string vs number) falhando.

**Solução Aplicada:** Comparação flexível com múltiplos métodos:
```javascript
// Normalizar para comparação
const nodeIdStr = String(nodeId);
const nodeIdNum = isNaN(nodeId) ? nodeId : Number(nodeId);

// Filtrar com comparação fraca
nodes = nodes.filter(function(n) {
    return n.id != nodeId && 
           String(n.id) !== nodeIdStr && 
           (isNaN(n.id) || Number(n.id) !== nodeIdNum);
});
```

### 2. Backend - `saveLayout()`
Adicionados logs detalhados:
```php
Logger::automation('saveLayout - IDs antigos (banco): ' . json_encode($oldNodeIds));
Logger::automation('saveLayout - IDs recebidos (frontend): ' . json_encode($sentNodeIds));
Logger::automation('saveLayout - Diferença (a deletar): ' . json_encode($nodesToDelete));
Logger::automation('saveLayout - Quantidade a deletar: ' . count($nodesToDelete));

if (!empty($nodesToDelete)) {
    foreach ($nodesToDelete as $nodeIdToDelete) {
        Logger::automation('saveLayout - Deletando nó ID: ' . $nodeIdToDelete);
        $result = AutomationNode::delete($nodeIdToDelete);
        Logger::automation('saveLayout - Resultado da deleção: ' . ($result ? 'SUCESSO' : 'FALHOU'));
    }
}
```

## Como Testar

1. **Abrir console do navegador (F12)**
2. **Deletar um nó:**
   - Clicar no ícone de lixeira
   - Confirmar
   - **Observar logs:**
     ```
     deleteNode - Deletando nó: 5 tipo: number
     deleteNode - Array antes: 3
     deleteNode - IDs no array: [1 (number), 2 (number), 5 (number)]
     deleteNode - Array depois: 2
     deleteNode - IDs restantes: [1, 2]
     ```

3. **Salvar Layout:**
   - Clicar em "Salvar Layout"
   - **Observar console:**
     ```
     saveLayout - IDs dos nós que serão enviados: [1, 2]
     ```

4. **Verificar Backend:**
   - Acessar `/view-automation-logs.php`
   - Procurar por:
     ```
     saveLayout - IDs antigos (banco): [1,2,5]
     saveLayout - IDs recebidos (frontend): [1,2]
     saveLayout - Diferença (a deletar): [5]
     saveLayout - Quantidade a deletar: 1
     saveLayout - DELETANDO nós: [5]
     saveLayout - Deletando nó ID: 5
     saveLayout - Resultado da deleção: SUCESSO
     ```

5. **Recarregar página:**
   - O nó deletado não deve aparecer

## Possíveis Problemas Restantes

### Se o nó não sair do array no frontend:
- **Sintoma:** `deleteNode - Array depois: 3` (mesmo número)
- **Causa:** Tipo do ID não batendo
- **Debug:** Verificar `deleteNode - IDs no array:` e comparar tipos

### Se o array diminui mas não deleta do banco:
- **Sintoma:** Backend não loga "DELETANDO nós"
- **Causa:** `array_diff` retorna vazio
- **Debug:** Comparar `IDs antigos` vs `IDs recebidos` nos logs
- **Possível:** IDs como string no banco, number no frontend

### Se deleta mas retorna falha:
- **Sintoma:** `Resultado da deleção: FALHOU`
- **Causa:** `Database::execute` retornando 0
- **Debug:** Verificar se o nó realmente existe no banco

## Próximos Passos

1. **Teste:** Deletar um nó e observar TODOS os logs
2. **Copie:** Console (F12) + `/view-automation-logs.php`
3. **Envie:** Logs completos para análise detalhada

## Arquivos Modificados

1. ✏️ `views/automations/show.php` - Função `deleteNode()` com comparação flexível
2. ✏️ `app/Controllers/AutomationController.php` - Logs detalhados de deleção

---

**Data:** 18/12/2025 17:15  
**Status:** 🔍 **DIAGNÓSTICO APLICADO - AGUARDANDO TESTE**

