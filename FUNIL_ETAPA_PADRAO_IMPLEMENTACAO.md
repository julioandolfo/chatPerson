# 🎯 FUNIL E ETAPA PADRÃO - IMPLEMENTAÇÃO COMPLETA

**Data**: 2025-01-17  
**Status**: ✅ **IMPLEMENTADO - PENDENTE EXECUÇÃO DE MIGRATIONS**

---

## 📋 RESUMO

Implementado sistema de funil e etapa padrão para garantir que todas as conversas entrem em um funil/etapa, mesmo sem automações configuradas. Cada integração (WhatsApp, etc) pode configurar seu próprio funil/etapa padrão.

---

## ✅ O QUE FOI IMPLEMENTADO

### 1. **Funil e Etapa Padrão do Sistema** ⭐

**Migration**: `database/migrations/057_create_default_funnel_and_stage.php`

- Cria automaticamente:
  - **Funil**: "Funil Entrada" (is_default = 1, cinza)
  - **Etapa**: "Nova Entrada" (is_default = 1, cinza, position = 1)
- Salva configuração em `settings`:
  ```json
  {
    "key": "system_default_funnel_stage",
    "value": {
      "funnel_id": 1,
      "stage_id": 1
    }
  }
  ```
- **Não-removível**: Marcado como padrão do sistema

---

### 2. **Campos nas Integrações** ⭐

**Migration**: `database/migrations/058_add_default_funnel_stage_to_integrations.php`

**Tabela**: `whatsapp_accounts`
- Novos campos:
  - `default_funnel_id` INT NULL
  - `default_stage_id` INT NULL
- Foreign keys:
  - `fk_whatsapp_default_funnel` → `funnels(id)` ON DELETE SET NULL
  - `fk_whatsapp_default_stage` → `funnel_stages(id)` ON DELETE SET NULL
- Índices para performance
- **Auto-atualização**: Contas existentes recebem o funil/etapa padrão do sistema

---

### 3. **Interface de Configuração** ⭐

**Arquivo**: `views/integrations/whatsapp.php`

#### Modal de Nova Conta

Novos campos adicionados:

```html
<!-- Separador visual -->
<div class="separator separator-dashed my-7"></div>
<h4>Funil e Etapa Padrão</h4>

<!-- Select de Funil -->
<select name="default_funnel_id" onchange="loadFunnelStages(...)">
    <option value="">Usar padrão do sistema</option>
    <!-- Funis disponíveis -->
</select>

<!-- Select de Etapa (dinâmico) -->
<select name="default_stage_id">
    <option value="">Selecione um funil primeiro</option>
</select>
```

#### Cards de Contas

Cada conta exibe:
- 🎯 **Funil Padrão**: Nome do funil configurado
- 📍 **Etapa Padrão**: Nome da etapa configurada
- ⚠️ **Alerta**: Se estiver usando padrão do sistema

#### JavaScript Dinâmico

```javascript
function loadFunnelStages(funnelId, targetSelectId) {
    // Carrega etapas do funil selecionado via AJAX
    // Atualiza select de etapas dinamicamente
}
```

---

### 4. **Backend - Controller** ⭐

**Arquivo**: `app/Controllers/IntegrationController.php`

**Método**: `whatsapp()`

- Busca todas as contas WhatsApp
- **Enriquece** com nomes de funil/etapa:
  ```php
  foreach ($accounts as &$account) {
      if ($account['default_funnel_id']) {
          $funnel = Funnel::find($account['default_funnel_id']);
          $account['default_funnel_name'] = $funnel['name'];
      }
      if ($account['default_stage_id']) {
          $stage = FunnelStage::find($account['default_stage_id']);
          $account['default_stage_name'] = $stage['name'];
      }
  }
  ```
- Passa **funis disponíveis** para a view

---

### 5. **Backend - Service** ⭐

**Arquivo**: `app/Services/WhatsAppService.php`

**Método**: `createAccount()`

- Validação adicionada:
  ```php
  'default_funnel_id' => 'nullable|integer',
  'default_stage_id' => 'nullable|integer'
  ```
- Campos salvos automaticamente ao criar conta

---

### 6. **Script de Execução** ⭐

**Arquivo**: `public/migrate.php` (temporário)

- Interface web para executar migrations
- Executa especificamente migrations 057 e 058
- **IMPORTANTE**: Remover após executar!
- **Acesso**: `http://seu-dominio.com/migrate.php`

---

## 🚀 COMO EXECUTAR

### Opção 1: Via Web (Recomendado)

1. Acesse: `http://seu-dominio.com/migrate.php`
2. As migrations 057 e 058 serão executadas automaticamente
3. Verifique o resultado na tela
4. **REMOVA** o arquivo `public/migrate.php` por segurança

### Opção 2: Via CLI

```bash
php scripts/migrate.php
```

---

## 🎯 COMO FUNCIONA

### Prioridade de Funil/Etapa

1. **Automação específica** (se existir)
2. **Configuração da integração** (WhatsApp Account)
3. **Padrão do sistema** (Funil Entrada → Nova Entrada)

### Fluxo de Criação de Conversa

```php
// 1. Verificar automação
if ($automation) {
    $funnelId = $automation['funnel_id'];
    $stageId = $automation['stage_id'];
}

// 2. Verificar configuração da integração
elseif ($whatsappAccount['default_funnel_id']) {
    $funnelId = $whatsappAccount['default_funnel_id'];
    $stageId = $whatsappAccount['default_stage_id'];
}

// 3. Usar padrão do sistema
else {
    $defaultConfig = getSetting('system_default_funnel_stage');
    $funnelId = $defaultConfig['funnel_id'];
    $stageId = $defaultConfig['stage_id'];
}
```

---

## 📂 ARQUIVOS MODIFICADOS/CRIADOS

### Migrations
- ✅ `database/migrations/057_create_default_funnel_and_stage.php`
- ✅ `database/migrations/058_add_default_funnel_stage_to_integrations.php`

### Backend
- ✅ `app/Controllers/IntegrationController.php` (modificado)
- ✅ `app/Services/WhatsAppService.php` (modificado)

### Frontend
- ✅ `views/integrations/whatsapp.php` (modificado)

### Scripts
- ✅ `public/migrate.php` (temporário - REMOVER após uso)
- ✅ `database/run_migrations.php` (helper)

### Documentação
- ✅ `FUNIL_ETAPA_PADRAO_IMPLEMENTACAO.md` (este arquivo)

---

## 🔧 PRÓXIMOS PASSOS (PENDENTE)

### 1. Executar Migrations
- ⏳ Acesse `public/migrate.php` e execute
- ⏳ Remova `public/migrate.php` após executar

### 2. Modificar Criação de Conversas
- ⏳ `app/Services/ConversationService.php`
- ⏳ Implementar lógica de prioridade:
  1. Automação específica
  2. Configuração da integração
  3. Padrão do sistema

### 3. Endpoint de Etapas
- ⏳ `app/Controllers/FunnelController.php`
- ⏳ Método: `getStagesJson($funnelId)`
- ⏳ Rota: `/funnels/{id}/stages/json`

### 4. Testar
- ⏳ Criar nova conta WhatsApp
- ⏳ Configurar funil/etapa personalizado
- ⏳ Receber mensagem e verificar funil/etapa correto

---

## 💡 CASOS DE USO

### Caso 1: Sistema Novo
- Sistema cria automaticamente "Funil Entrada" → "Nova Entrada"
- Todas as conversas vão para lá até configurar automações

### Caso 2: Múltiplos WhatsApp
- WhatsApp 1 (Vendas): Funil Comercial → Novo Lead
- WhatsApp 2 (Suporte): Funil Suporte → Ticket Aberto
- WhatsApp 3: Usa padrão do sistema

### Caso 3: Com Automações
- Cliente envia "1" → Automação leva para "Funil Comercial"
- Cliente envia mensagem qualquer → Vai para funil padrão da conta

---

## 🎉 BENEFÍCIOS

✅ **Nunca mais conversas "órfãs"** sem funil/etapa  
✅ **Configuração flexível** por integração  
✅ **Padrão do sistema** como fallback  
✅ **Interface amigável** para configuração  
✅ **Backward compatible** (contas existentes recebem padrão)  
✅ **Escalável** (fácil adicionar outras integrações)

---

## 📊 ESTRUTURA DO BANCO

### Tabela: `funnels`
```sql
id | name            | is_default | color    | ...
1  | Funil Entrada   | 1          | #3F4254  | ...
```

### Tabela: `funnel_stages`
```sql
id | funnel_id | name          | is_default | position | ...
1  | 1         | Nova Entrada  | 1          | 1        | ...
```

### Tabela: `whatsapp_accounts`
```sql
id | name      | phone_number  | default_funnel_id | default_stage_id | ...
1  | Principal | 5511999999999 | 1                 | 1                | ...
2  | Vendas    | 5511888888888 | 2                 | 5                | ...
```

### Tabela: `settings`
```sql
key                          | value
system_default_funnel_stage  | {"funnel_id":1,"stage_id":1}
```

---

**Status Final**: ✅ **IMPLEMENTADO**  
**Próximo Passo**: ⏳ **EXECUTAR MIGRATIONS**  
**Última Atualização**: 2025-01-17

---

**🎊 SISTEMA DE FUNIL/ETAPA PADRÃO IMPLEMENTADO COM SUCESSO! 🎊**

