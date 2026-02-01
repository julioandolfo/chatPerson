# 🚨 CORREÇÕES URGENTES - Personizi

## ⚠️ 2 PROBLEMAS IDENTIFICADOS

---

## ❌ Problema #1: Endpoint de Listar Contas

### O que está errado:
```php
// ❌ ERRADO - Retorna 404
$result = $this->request( '/whatsapp/accounts', 'GET' );
```

### Correção:
```php
// ✅ CORRETO
$result = $this->request( '/whatsapp-accounts', 'GET' );
```

### Diferença:
- **Errado:** `/whatsapp/accounts` (com **barra** `/`)
- **Correto:** `/whatsapp-accounts` (com **hífen** `-`)

---

## ❌ Problema #2: Estrutura da Resposta

### O que está errado:
```php
// ❌ ERRADO - Acessa caminho incorreto
$accounts = isset( $result['data']['accounts'] )
    ? $result['data']['accounts']
    : array();
```

### Correção:
```php
// ✅ CORRETO - Acessa caminho correto
$accounts = isset( $result['data']['data']['accounts'] )
    ? $result['data']['data']['accounts']
    : array();
```

### Diferença:
- **Errado:** `$result['data']['accounts']`
- **Correto:** `$result['data']['data']['accounts']`

---

## 📝 CÓDIGO COMPLETO CORRIGIDO

### Arquivo: `includes/integrations/class-pcw-personizi.php`

```php
<?php
/**
 * Método: Listar Contas WhatsApp
 * VERSÃO CORRIGIDA - 01/02/2025
 */
public function get_whatsapp_accounts( $force_refresh = false ) {
    // ✅ CORREÇÃO #1: URL com hífen
    $result = $this->request( '/whatsapp-accounts', 'GET' );
    
    // Verificar se houve erro
    if ( is_wp_error( $result ) ) {
        return $result;
    }
    
    // ✅ CORREÇÃO #2: Estrutura da resposta correta
    $accounts = array();
    if ( isset( $result['data']['data']['accounts'] ) ) {
        $accounts = $result['data']['data']['accounts'];
    }
    
    return $accounts;
}

/**
 * Método: Enviar Mensagem WhatsApp
 * ✅ JÁ ESTÁ CORRETO - NÃO PRECISA ALTERAR
 */
public function send_whatsapp_message( $to, $message, $contact_name = '', $from = '' ) {
    // Remove caracteres não numéricos
    $to = preg_replace( '/[^0-9]/', '', $to );
    
    // Se não informou from, usa padrão
    if ( empty( $from ) ) {
        $from = $this->default_from; // 5511916127354
    }
    
    // Monta dados
    $data = array(
        'to'      => $to,
        'from'    => $from,
        'message' => $message,
    );
    
    // Adiciona contact_name se fornecido
    if ( ! empty( $contact_name ) ) {
        $data['contact_name'] = $contact_name;
    }
    
    // ✅ Endpoint correto: /messages/send
    return $this->request( '/messages/send', 'POST', $data );
}
```

---

## 🧪 TESTAR AS CORREÇÕES

### Teste 1: Listar Contas (Terminal/cURL)

```bash
# Teste no terminal
curl -X GET "https://chat.personizi.com.br/api/v1/whatsapp-accounts" \
  -H "Authorization: Bearer b481e4bb3d224638a498be99ae3e411c2f414e71a69f081039edd0c4dff99912"
```

**Resposta esperada:**
```json
{
  "success": true,
  "data": {
    "accounts": [
      {
        "id": 1,
        "name": "WhatsApp Principal",
        "phone_number": "5511916127354",
        "status": "active"
      }
    ],
    "pagination": {...}
  }
}
```

### Teste 2: Enviar Mensagem (Terminal/cURL)

```bash
# Teste no terminal
curl -X POST "https://chat.personizi.com.br/api/v1/messages/send" \
  -H "Authorization: Bearer b481e4bb3d224638a498be99ae3e411c2f414e71a69f081039edd0c4dff99912" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "5511999998888",
    "from": "5511916127354",
    "message": "Teste de envio"
  }'
```

**Resposta esperada:**
```json
{
  "success": true,
  "data": {
    "message_id": "123",
    "conversation_id": "456",
    "status": "sent"
  },
  "message": "Mensagem enviada com sucesso"
}
```

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

- [ ] **Passo 1:** Abrir arquivo `includes/integrations/class-pcw-personizi.php`
- [ ] **Passo 2:** Localizar método `get_whatsapp_accounts()`
- [ ] **Passo 3:** Alterar `/whatsapp/accounts` para `/whatsapp-accounts`
- [ ] **Passo 4:** Alterar `$result['data']['accounts']` para `$result['data']['data']['accounts']`
- [ ] **Passo 5:** Salvar arquivo
- [ ] **Passo 6:** Testar no painel do WordPress
- [ ] **Passo 7:** Verificar se contas WhatsApp aparecem corretamente
- [ ] **Passo 8:** Testar envio de mensagem

---

## 🎯 RESUMO RÁPIDO

| Item | Antes (Errado) | Depois (Correto) |
|------|----------------|------------------|
| **Endpoint** | `/whatsapp/accounts` | `/whatsapp-accounts` |
| **Resposta** | `$result['data']['accounts']` | `$result['data']['data']['accounts']` |
| **Status** | ❌ Erro 404 | ✅ Funciona |

---

## 📞 SUPORTE

Se após as correções ainda houver problemas:

1. **Verificar token:** Configurações > API & Tokens
2. **Ver logs:** Configurações > API & Tokens > Logs
3. **Diagnóstico:** https://chat.personizi.com.br/diagnostico-personizi.php
4. **Documentação:** `/DOCUMENTACAO_PERSONIZI_CORRIGIDA.md`

---

## ⏱️ TEMPO ESTIMADO

**Implementação:** 5 minutos  
**Teste:** 2 minutos  
**Total:** 7 minutos

---

**Data:** 01/02/2025  
**Status:** ✅ Correções implementadas na API  
**Ação necessária:** Atualizar código do Personizi
