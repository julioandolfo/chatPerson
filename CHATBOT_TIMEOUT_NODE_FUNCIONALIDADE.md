# Funcionalidade: Escolher Nó após Timeout no Chatbot

## Descrição

Foi implementada uma nova funcionalidade no sistema de automações que permite escolher um nó específico para continuar o fluxo quando ocorrer um timeout no chatbot.

## O que foi alterado

### 1. Interface (views/automations/show.php)

#### Nova Opção no Select de Timeout Action
- Adicionado a opção **"Seguir para Nó Específico"** (`go_to_node`) no select de ações ao timeout
- Criado um campo select adicional que aparece quando esta opção é selecionada
- O campo permite escolher qualquer nó disponível na automação (exceto o nó atual)

#### Localização no Modal de Configuração do Chatbot
```
Configurações do Chatbot
├── Tempo de Espera (segundos)
├── Ação ao Timeout
│   ├── Nada
│   ├── Atribuir a um Agente
│   ├── Enviar Mensagem
│   ├── Encerrar Conversa
│   └── ⭐ Seguir para Nó Específico (NOVO)
└── Nó de Destino (Timeout) - Aparece quando "Seguir para Nó Específico" é selecionado
```

### 2. Backend (app/Services/AutomationService.php)

- Adicionado o campo `chatbot_timeout_node_id` no metadata da conversa
- Este campo armazena o ID do nó que deve ser executado quando o timeout ocorrer

### 3. Processamento de Timeout (app/Jobs/ChatbotTimeoutJob.php)

#### Novo Job Criado
Foi criado um job agendado que verifica periodicamente conversas com chatbot ativo e timeout expirado.

**Funcionamento:**
1. A cada execução do cron, verifica todas as conversas abertas
2. Identifica conversas com `chatbot_active = true` e `chatbot_timeout_at < tempo_atual`
3. Processa a ação configurada:
   - **go_to_node**: Executa o nó específico escolhido
   - **assign_agent**: Limpa estado e prepara para atribuição
   - **send_message**: Envia mensagem padrão
   - **close**: Encerra a conversa
   - **nothing**: Apenas limpa o estado do chatbot

### 4. Job Agendado (public/run-scheduled-jobs.php)

O `ChatbotTimeoutJob` foi adicionado aos jobs críticos que executam a cada rodada do cron (aproximadamente a cada 1 minuto).

## Como Usar

### 1. Criar uma Automação com Chatbot

1. Acesse **Automações** > **Criar Nova Automação**
2. Adicione um nó de **Chatbot** (action_chatbot)
3. Configure a mensagem e opções do chatbot

### 2. Configurar Timeout com Nó Específico

1. No modal de configuração do chatbot, encontre a seção **"Ação ao Timeout"**
2. Selecione **"Seguir para Nó Específico"**
3. Um novo campo aparecerá: **"Nó de Destino (Timeout)"**
4. Escolha o nó para onde o fluxo deve seguir após o timeout
5. Configure o tempo de espera (em segundos)

### 3. Exemplos de Uso

#### Exemplo 1: Escalar para Atendimento Humano
```
[Chatbot] "Escolha uma opção:"
    ├─ Opção 1 → [Nó Resposta 1]
    ├─ Opção 2 → [Nó Resposta 2]
    └─ Timeout (300s) → [Nó Atribuir Agente]
```

#### Exemplo 2: Enviar Mensagem e Continuar Fluxo
```
[Chatbot] "Digite seu CPF:"
    ├─ Resposta válida → [Validar CPF]
    └─ Timeout (180s) → [Mensagem + Pedir novamente]
```

#### Exemplo 3: Oferecer Alternativa
```
[Chatbot] "Deseja agendar?"
    ├─ Sim → [Agendar]
    ├─ Não → [Agradecer]
    └─ Timeout (240s) → [Oferecer outras opções]
```

## Testando a Funcionalidade

### 1. Teste Manual

1. Crie uma automação com chatbot configurado com timeout e nó de destino
2. Inicie uma conversa que dispare a automação
3. Aguarde o chatbot enviar a mensagem
4. **NÃO responda** - aguarde o tempo de timeout expirar
5. Aguarde o cron executar (máximo 1 minuto)
6. Verifique se o nó de destino foi executado corretamente

### 2. Teste com Timeout Curto

Para testes rápidos, configure um timeout de 30 segundos:
- Configure o chatbot com timeout de 30s
- Escolha um nó de destino simples (ex: enviar mensagem)
- Inicie a conversa e aguarde 30 segundos
- Aguarde até 1 minuto para o cron processar
- Verifique os logs em `storage/logs/automation.log`

### 3. Verificar Logs

Os logs do ChatbotTimeoutJob aparecem em:
- Console do cron: `storage/logs/cron.log`
- Logs de automação: `storage/logs/automation.log`

Procure por:
```
⏰ Timeout de chatbot expirado para conversa {id}
Seguindo para nó {node_id}...
✅ Nó de timeout executado com sucesso
```

## Configuração do Cron

Certifique-se de que o cron está configurado para executar o arquivo `run-scheduled-jobs.php`:

**Windows (Agendador de Tarefas):**
```
php c:\laragon\www\chat\public\run-scheduled-jobs.php
```

**Linux (Crontab):**
```
* * * * * cd /caminho/do/projeto && php public/run-scheduled-jobs.php >> storage/logs/cron.log 2>&1
```

## Detalhes Técnicos

### Campos Salvos no Metadata

```json
{
  "chatbot_active": true,
  "chatbot_timeout_at": 1234567890,
  "chatbot_timeout_action": "go_to_node",
  "chatbot_timeout_node_id": "node_123",
  "chatbot_automation_id": 5,
  "chatbot_node_id": "node_456"
}
```

### Fluxo de Execução

```
1. Chatbot é ativado
   └─> Salva metadata com timeout_at e timeout_node_id

2. Usuário não responde
   └─> Timeout expira (timeout_at < time())

3. ChatbotTimeoutJob detecta
   └─> Verifica action = "go_to_node"
       └─> Busca automation e nó de destino
           └─> Limpa estado do chatbot
               └─> Executa nó de destino
```

## Troubleshooting

### Timeout não está sendo processado

**Verifique:**
1. Cron está rodando? `tail -f storage/logs/cron.log`
2. Job está sendo executado? Procure por "ChatbotTimeoutJob" nos logs
3. Conversa tem `chatbot_active = true`? Verifique na tabela `conversations`
4. Timeout expirou? Compare `chatbot_timeout_at` com `time()`

### Nó de destino não aparece no select

**Verifique:**
1. Automação tem outros nós além do chatbot?
2. JavaScript está carregando corretamente?
3. Console do navegador mostra erros?

### Nó de destino não é executado

**Verifique:**
1. `chatbot_timeout_node_id` está salvo no metadata?
2. ID do nó existe na automação?
3. Automação está ativa?
4. Logs de automação em `storage/logs/automation.log`

## Limitações

1. O timeout só é verificado quando o cron executa (aproximadamente a cada 1 minuto)
2. A precisão do timeout depende da frequência de execução do cron
3. Se o cron não estiver configurado, os timeouts nunca serão processados

## Melhorias Futuras

- [ ] Permitir configurar diferentes tempos de timeout por opção
- [ ] Adicionar retry automático antes do timeout
- [ ] Notificar supervisores quando timeouts ocorrem frequentemente
- [ ] Dashboard com estatísticas de timeout por automação

## Autor

Implementado em: 01/02/2026
