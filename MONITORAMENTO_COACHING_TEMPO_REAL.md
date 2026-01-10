# 🔍 Monitoramento de Coaching em Tempo Real

## ✅ CORREÇÃO APLICADA

### Problema Encontrado
O arquivo `views/settings/action-buttons/realtime-coaching-config.php` estava tentando acessar uma variável `$data['settings']` que não existia no escopo, fazendo com que as configurações nunca fossem carregadas corretamente.

### Solução
Corrigido para usar `$conversationSettings['realtime_coaching']` que é a variável correta passada pelo controller.

---

## 🎯 Como Verificar se o Coaching Está Ativo

### 1. Verificar Configurações Salvas

Acesse: `/settings?tab=conversations`

Na seção **"⚡ Coaching em Tempo Real (IA)"**:

1. ✅ Marque o checkbox **"Habilitar Coaching em Tempo Real"**
2. ✅ Configure as opções desejadas
3. ✅ Clique em **"Salvar Configurações"**
4. ✅ Recarregue a página e veja se as opções continuam marcadas

---

### 2. Visualizar Logs Detalhados

Acesse: **`http://seu-dominio/view-all-logs.php`**

No topo da página, clique no botão verde **"⚡ Coaching"**

---

## 📋 O Que os Logs Mostram

### Quando uma Mensagem é Recebida:

```
[2026-01-10 15:30:45] 📩 Nova mensagem recebida - ID: 123, Conversa: 45, Tipo: contact
[2026-01-10 15:30:45] 👤 Agente atribuído: ID 5
[2026-01-10 15:30:45] 🎯 queueMessageForAnalysis() - Msg #123, Conv #45, Agent #5
[2026-01-10 15:30:45] ✅ Coaching está HABILITADO - Prosseguindo com verificações...
```

### Verificação de Filtros:

```
[2026-01-10 15:30:45] 🔍 === INICIANDO VERIFICAÇÃO DE FILTROS ===
[2026-01-10 15:30:45] 📝 Mensagem: "Quanto custa esse produto?" (tamanho: 26 chars)
[2026-01-10 15:30:45] ✅ FILTRO 1: OK - É mensagem de cliente
[2026-01-10 15:30:45] ✅ FILTRO 2: OK - Tamanho adequado (26 >= 10)
[2026-01-10 15:30:45] ✅ FILTRO 3: OK - Rate limit global (2/10)
[2026-01-10 15:30:45] ✅ FILTRO 4: OK - Intervalo agente (15s >= 10s)
[2026-01-10 15:30:45] ✅ FILTRO 5: OK - Fila disponível (3/100)
[2026-01-10 15:30:45] ✅ FILTRO 6: OK - Dentro do limite (Hora: $0.15/$1.00, Dia: $0.45/$10.00)
[2026-01-10 15:30:45] ✅✅✅ TODOS OS FILTROS PASSARAM! Mensagem será analisada!
```

### Adição na Fila:

```
[2026-01-10 15:30:45] 📋 Modo FILA ativado - Adicionando mensagem na fila
[2026-01-10 15:30:45] ✅ Mensagem adicionada na fila com sucesso!
```

### Processamento da Fila:

```
[2026-01-10 15:30:48] ⚙️ === PROCESSANDO FILA DE COACHING ===
[2026-01-10 15:30:48] 📋 Encontrados 1 itens na fila (delay: 3s)
[2026-01-10 15:30:48] 🔄 Processando item #12 - Msg #123, Conv #45
[2026-01-10 15:30:48] 🤖 === ANÁLISE COM IA INICIADA ===
[2026-01-10 15:30:48] 📜 Buscando contexto da conversa (últimas 10 mensagens)...
[2026-01-10 15:30:48] 📜 Contexto carregado: 5 mensagens
[2026-01-10 15:30:48] 🧠 Chamando OpenAI (model: gpt-3.5-turbo, temp: 0.5)...
[2026-01-10 15:30:50] ⏱️ Resposta da IA recebida em 1.87s
[2026-01-10 15:30:50] ✅ HINT GERADO!
[2026-01-10 15:30:50]    Tipo: objection
[2026-01-10 15:30:50]    Texto: Cliente perguntou sobre preço - possível objeção
[2026-01-10 15:30:50]    Tokens: 245, Custo: $0.0004
[2026-01-10 15:30:50] 💾 Hint salvo no banco (ID: 78)
[2026-01-10 15:30:50] 📤 Enviando hint para agente #5...
[2026-01-10 15:30:50] ✅ Hint enviado via WebSocket para agente #5!
[2026-01-10 15:30:50] ✅ Item #12 processado com sucesso!
```

---

## ❌ Possíveis Problemas e Soluções

### Problema 1: "Coaching DESABILITADO"
```
[2026-01-10 15:30:45] ❌ Coaching DESABILITADO nas configurações - enabled=false
```

**Solução:** Vá em Configurações > Conversas e marque o checkbox "Habilitar Coaching em Tempo Real"

---

### Problema 2: Mensagem Bloqueada por Filtros

```
[2026-01-10 15:30:45] ❌ FILTRO 2: Mensagem muito curta (5 < 10 chars)
```

**Possíveis causas:**
- ❌ FILTRO 1: Mensagem não é de cliente (é do agente)
- ❌ FILTRO 2: Mensagem muito curta (ex: "ok", "sim")
- ❌ FILTRO 3: Rate limit excedido (muitas análises por minuto)
- ❌ FILTRO 4: Agente foi analisado há pouco tempo
- ❌ FILTRO 5: Fila cheia
- ❌ FILTRO 6: Limite de custo excedido

**Soluções:**
1. Ajuste as configurações conforme necessário
2. Para FILTRO 3/4: Aguarde alguns segundos
3. Para FILTRO 5: Aguarde a fila processar
4. Para FILTRO 6: Aumente os limites ou aguarde próxima hora/dia

---

### Problema 3: IA Não Gerou Hint

```
[2026-01-10 15:30:50] ⏭️ IA não identificou situação relevante (has_hint: false)
```

**Causa:** A IA analisou a mensagem mas não identificou nenhuma situação relevante que exija coaching.

**Isso é NORMAL** - Nem toda mensagem precisa de coaching. A IA só gera hints quando identifica:
- Objeções
- Oportunidades
- Perguntas importantes
- Sentimento negativo
- Sinais de compra
- Momentos de fechamento
- Necessidade de escalar

---

### Problema 4: Erro ao Chamar OpenAI

```
[2026-01-10 15:30:48] ❌ ERRO CRÍTICO ao analisar: API Key da OpenAI não configurada
```

**Solução:** Configure a API Key da OpenAI em Configurações > Geral

---

### Problema 5: WebSocket Não Disponível

```
[2026-01-10 15:30:50] ⚠️ WebSocket não disponível - Hint ficará disponível via polling
```

**Não é um erro crítico** - O hint será salvo no banco e o agente verá quando abrir a conversa. Mas para receber em tempo real, o WebSocket precisa estar rodando.

**Solução:** Inicie o servidor WebSocket (veja `INSTALACAO_WEBSOCKET.md`)

---

## 📊 Como Interpretar os Logs

### ✅ Tudo Funcionando Perfeitamente:

```
✅ Coaching está HABILITADO
✅✅✅ TODOS OS FILTROS PASSARAM!
✅ Mensagem adicionada na fila com sucesso!
✅ HINT GERADO!
✅ Hint enviado via WebSocket!
```

### ⚠️ Coaching Ativo mas Mensagem Não Qualifica:

```
✅ Coaching está HABILITADO
❌ FILTRO 2: Mensagem muito curta
```
ou
```
✅ TODOS OS FILTROS PASSARAM
⏭️ IA não identificou situação relevante
```

**Isso é NORMAL** - Nem toda mensagem precisa de coaching.

### ❌ Coaching Não Está Funcionando:

```
❌ Coaching DESABILITADO nas configurações
```
ou
```
❌ ERRO CRÍTICO ao analisar: [erro]
```

**Precisa de ação** - Veja soluções acima.

---

## 🧪 Como Testar o Sistema

### 1. Habilitar Coaching
- Vá em `/settings?tab=conversations`
- Marque "Habilitar Coaching em Tempo Real"
- Salve

### 2. Abra o Visualizador de Logs
- Acesse `/view-all-logs.php`
- Clique no botão verde "⚡ Coaching"
- Deixe aberto em outra aba

### 3. Simule uma Conversa
- Abra uma conversa existente
- Envie uma mensagem do WhatsApp (como se fosse o cliente)
- A mensagem pode ser algo como: "Quanto custa?" ou "Não tenho dinheiro"

### 4. Observe os Logs
- Recarregue a página de logs (F5)
- Você deve ver:
  - 📩 Nova mensagem recebida
  - 🔍 Verificação de filtros
  - ✅ ou ❌ para cada filtro
  - Se passou: 📋 Adição na fila
  - Após 3s: ⚙️ Processamento
  - Se relevante: ✅ HINT GERADO!

---

## 📈 Monitoramento Contínuo

Para ver o coaching em ação constantemente:

1. Mantenha `/view-all-logs.php` aberto
2. Configure auto-refresh (F5 a cada 5 segundos ou use extensão do navegador)
3. Observe as mensagens chegando em tempo real

---

## 🚀 Próximos Passos

1. ✅ Habilite o coaching nas configurações
2. ✅ Configure a API Key da OpenAI
3. ✅ Ajuste os filtros conforme necessário
4. ✅ Inicie o WebSocket para notificações em tempo real
5. ✅ Teste com conversas reais
6. 📊 Monitore os logs para ver o sistema em ação

---

## 📞 Suporte

Se após seguir este guia o coaching ainda não funcionar:

1. Copie as últimas 50 linhas do log de coaching
2. Verifique se há erros PHP em `/view-all-logs.php` > "Erros PHP"
3. Verifique se a tabela `coaching_queue` existe no banco
4. Verifique se a tabela `realtime_coaching_hints` existe no banco

---

## 🔧 Comandos Úteis

### Limpar Log de Coaching
```bash
# Linux
> /caminho/logs/coaching.log

# Windows (PowerShell)
Clear-Content C:\laragon\www\chat\logs\coaching.log
```

### Ver Últimas Linhas do Log (Linux)
```bash
tail -f logs/coaching.log
```

### Verificar Fila no Banco
```sql
SELECT * FROM coaching_queue WHERE status = 'pending' ORDER BY added_at DESC LIMIT 10;
```

### Verificar Hints Gerados
```sql
SELECT * FROM realtime_coaching_hints ORDER BY created_at DESC LIMIT 10;
```

---

## ✅ Resumo

Com os logs detalhados implementados, você agora pode:

✅ Ver se o coaching está habilitado
✅ Ver se mensagens estão chegando
✅ Ver quais filtros estão bloqueando mensagens
✅ Ver se a IA está gerando hints
✅ Ver se os hints estão sendo enviados
✅ Diagnosticar problemas rapidamente
✅ Monitorar custos em tempo real

**Acesse `/view-all-logs.php` e clique em "⚡ Coaching" para ver tudo em ação!**
