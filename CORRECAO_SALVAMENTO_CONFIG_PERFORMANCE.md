# Correção: Salvamento de Configurações de Performance - 2026-01-10

## ❌ Problema Reportado

**Sintoma:** Ao habilitar "Análise de Performance de Vendedores (OpenAI)" e salvar, a configuração não persiste. Após dar refresh, a opção volta a estar desabilitada.

---

## 🔍 Causa Raiz

O método `SettingsController::saveConversations()` não estava processando o campo `agent_performance_analysis` do formulário. Ele processava:
- ✅ `sentiment_analysis`
- ✅ `audio_transcription`
- ✅ `text_to_speech`
- ❌ `agent_performance_analysis` ← **FALTANDO**

Resultado: Os dados eram enviados pelo formulário, mas ignorados pelo backend.

---

## ✅ Solução Aplicada

### 1. **Adicionado Processamento no Controller**

**Arquivo:** `app/Controllers/SettingsController.php`  
**Método:** `saveConversations()`  
**Linha:** Após o bloco `sentiment_analysis`

**Código adicionado:**

```php
'agent_performance_analysis' => [
    'enabled' => isset($data['agent_performance_analysis']['enabled']),
    'model' => $data['agent_performance_analysis']['model'] ?? 'gpt-4-turbo',
    'temperature' => isset($data['agent_performance_analysis']['temperature']) ? (float)$data['agent_performance_analysis']['temperature'] : 0.3,
    'check_interval_hours' => isset($data['agent_performance_analysis']['check_interval_hours']) ? (int)$data['agent_performance_analysis']['check_interval_hours'] : 6,
    'analyze_on_close' => isset($data['agent_performance_analysis']['analyze_on_close']),
    'min_agent_messages' => isset($data['agent_performance_analysis']['min_agent_messages']) ? (int)$data['agent_performance_analysis']['min_agent_messages'] : 5,
    'min_conversation_duration' => isset($data['agent_performance_analysis']['min_conversation_duration']) ? (int)$data['agent_performance_analysis']['min_conversation_duration'] : 5,
    'cost_limit_per_day' => isset($data['agent_performance_analysis']['cost_limit_per_day']) ? (float)$data['agent_performance_analysis']['cost_limit_per_day'] : 10.00,
    'dimension_weights' => [
        'proactivity' => isset($data['agent_performance_analysis']['weight_proactivity']) ? (float)$data['agent_performance_analysis']['weight_proactivity'] : 1.0,
        'objection_handling' => isset($data['agent_performance_analysis']['weight_objection_handling']) ? (float)$data['agent_performance_analysis']['weight_objection_handling'] : 1.0,
        'rapport' => isset($data['agent_performance_analysis']['weight_rapport']) ? (float)$data['agent_performance_analysis']['weight_rapport'] : 1.0,
        'closing_techniques' => isset($data['agent_performance_analysis']['weight_closing_techniques']) ? (float)$data['agent_performance_analysis']['weight_closing_techniques'] : 1.0,
        'qualification' => isset($data['agent_performance_analysis']['weight_qualification']) ? (float)$data['agent_performance_analysis']['weight_qualification'] : 1.0,
        'clarity' => isset($data['agent_performance_analysis']['weight_clarity']) ? (float)$data['agent_performance_analysis']['weight_clarity'] : 1.0,
        'value_proposition' => isset($data['agent_performance_analysis']['weight_value_proposition']) ? (float)$data['agent_performance_analysis']['weight_value_proposition'] : 1.0,
        'response_time' => isset($data['agent_performance_analysis']['weight_response_time']) ? (float)$data['agent_performance_analysis']['weight_response_time'] : 1.0,
        'follow_up' => isset($data['agent_performance_analysis']['weight_follow_up']) ? (float)$data['agent_performance_analysis']['weight_follow_up'] : 1.0,
        'professionalism' => isset($data['agent_performance_analysis']['weight_professionalism']) ? (float)$data['agent_performance_analysis']['weight_professionalism'] : 1.0,
    ],
    'gamification' => [
        'enabled' => isset($data['agent_performance_analysis']['gamification_enabled']),
        'auto_award_badges' => isset($data['agent_performance_analysis']['gamification_auto_award_badges']),
    ],
    'coaching' => [
        'enabled' => isset($data['agent_performance_analysis']['coaching_enabled']),
        'auto_create_goals' => isset($data['agent_performance_analysis']['coaching_auto_create_goals']),
        'goal_threshold' => isset($data['agent_performance_analysis']['coaching_goal_threshold']) ? (float)$data['agent_performance_analysis']['coaching_goal_threshold'] : 3.5,
    ],
    'best_practices' => [
        'enabled' => isset($data['agent_performance_analysis']['best_practices_enabled']),
        'auto_save' => isset($data['agent_performance_analysis']['best_practices_auto_save']),
        'min_score_threshold' => isset($data['agent_performance_analysis']['best_practices_min_score']) ? (float)$data['agent_performance_analysis']['best_practices_min_score'] : 4.5,
    ],
    'reports' => [
        'send_weekly_summary' => isset($data['agent_performance_analysis']['reports_send_weekly_summary']),
        'send_monthly_ranking' => isset($data['agent_performance_analysis']['reports_send_monthly_ranking']),
    ],
],
```

---

## 🧪 Como Testar

### 1. **Teste Via Interface (Recomendado)**

1. Acesse: https://chat.personizi.com.br/settings?tab=conversations
2. Role até "📊 Análise de Performance de Vendedores (OpenAI)"
3. Marque o checkbox "Habilitar análise de performance"
4. Configure outros campos (modelo, temperatura, etc)
5. Clique em **"Salvar Configurações"**
6. **Dê refresh na página (F5)**
7. ✅ Verifique se o checkbox continua marcado

### 2. **Teste Via Script (Para Debug)**

Execute o script de teste:

```bash
php public/scripts/test-performance-config.php
```

**Saída esperada:**
```
=== Teste de Configurações de Performance ===

Configurações carregadas:
========================

✅ Seção 'agent_performance_analysis' encontrada!

Enabled: SIM
Model: gpt-4-turbo
Temperature: 0.3
Check Interval: 6 horas
Min Agent Messages: 5
Cost Limit: $10/dia

Gamificação:
  Enabled: SIM
  Auto Award Badges: SIM

Coaching:
  Enabled: SIM
  Auto Create Goals: SIM

Melhores Práticas:
  Enabled: SIM
  Auto Save: SIM

=== Testando salvamento ===

Salvando configuração de teste...
✅ Salvo com sucesso!

Recarregando configurações...
✅ Configuração 'enabled' persistiu corretamente!

=== Fim do teste ===
```

---

## 📊 Estrutura de Dados Salva

```php
$conversationSettings['agent_performance_analysis'] = [
    'enabled' => true,
    'model' => 'gpt-4-turbo',
    'temperature' => 0.3,
    'check_interval_hours' => 6,
    'analyze_on_close' => true,
    'min_agent_messages' => 5,
    'min_conversation_duration' => 5,
    'cost_limit_per_day' => 10.00,
    'dimension_weights' => [
        'proactivity' => 1.0,
        'objection_handling' => 1.0,
        // ... todas as 10 dimensões
    ],
    'gamification' => [
        'enabled' => true,
        'auto_award_badges' => true,
    ],
    'coaching' => [
        'enabled' => true,
        'auto_create_goals' => true,
        'goal_threshold' => 3.5,
    ],
    'best_practices' => [
        'enabled' => true,
        'auto_save' => true,
        'min_score_threshold' => 4.5,
    ],
    'reports' => [
        'send_weekly_summary' => false,
        'send_monthly_ranking' => false,
    ],
];
```

---

## 🔄 Fluxo Completo

1. **Usuário marca checkbox** → `agent_performance_analysis[enabled]` = "1"
2. **Formulário envia** → POST `/settings/conversations`
3. **Controller recebe** → `$data['agent_performance_analysis']['enabled']`
4. **Controller processa** → `'enabled' => isset($data[...]['enabled'])`
5. **Service salva** → `ConversationSettingsService::saveSettings()`
6. **Banco atualiza** → Tabela `settings`, chave `conversation_settings`
7. **Reload carrega** → `$conversationSettings = ConversationSettingsService::getSettings()`
8. **View renderiza** → `<?= !empty($perfSettings['enabled']) ? 'checked' : '' ?>`

---

## ✅ Status Final

- ✅ Controller processa todos os campos de performance
- ✅ Configurações persistem após salvar
- ✅ Checkbox permanece marcado após refresh
- ✅ Todos os sub-campos salvos corretamente
- ✅ Script de teste criado

---

## 🐛 Se Ainda Não Funcionar

### Verificar se o arquivo foi sincronizado no Docker:

```bash
# Ver se o arquivo foi atualizado
docker exec <container> ls -la /var/www/html/app/Controllers/SettingsController.php

# Copiar manualmente se necessário
docker cp app/Controllers/SettingsController.php <container>:/var/www/html/app/Controllers/

# Reiniciar container
docker-compose restart
```

### Verificar logs:

```bash
# Logs do container
docker logs -f <container>

# Logs do Apache/PHP
tail -f /var/log/apache2/error.log
```

### Testar via curl:

```bash
curl -X POST https://chat.personizi.com.br/settings/conversations \
  -H "Cookie: PHPSESSID=seu_session_id" \
  -d "agent_performance_analysis[enabled]=1" \
  -d "agent_performance_analysis[model]=gpt-4-turbo"
```

---

## 📝 Campos Processados

| Campo no Formulário | Tipo | Valor Padrão |
|---------------------|------|--------------|
| `agent_performance_analysis[enabled]` | checkbox | false |
| `agent_performance_analysis[model]` | select | gpt-4-turbo |
| `agent_performance_analysis[temperature]` | float | 0.3 |
| `agent_performance_analysis[check_interval_hours]` | int | 6 |
| `agent_performance_analysis[analyze_on_close]` | checkbox | false |
| `agent_performance_analysis[min_agent_messages]` | int | 5 |
| `agent_performance_analysis[min_conversation_duration]` | int | 5 |
| `agent_performance_analysis[cost_limit_per_day]` | float | 10.00 |
| `agent_performance_analysis[weight_*]` | float | 1.0 |
| `agent_performance_analysis[gamification_enabled]` | checkbox | false |
| `agent_performance_analysis[coaching_enabled]` | checkbox | false |
| `agent_performance_analysis[best_practices_enabled]` | checkbox | false |

---

Agora o sistema deve salvar e carregar as configurações corretamente! 🎉
