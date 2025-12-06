# 🚀 Guia de Início Rápido - Sistema Multiatendimento

## 📋 Visão Geral do Fluxo

Este documento explica como o sistema funciona após conectar o WhatsApp e o que precisa estar rodando.

## ✅ Passo a Passo Após Conectar WhatsApp

### 1. **Conectar WhatsApp (Já Feito ✅)**
- Você escaneou o QR Code
- O sistema detectou a conexão
- O webhook foi configurado automaticamente no Quepasa API

### 2. **Verificar se o Webhook Foi Configurado**
O sistema tenta configurar automaticamente, mas você pode verificar:

**No Quepasa API:**
- Acesse o painel do Quepasa
- Verifique se o webhook está configurado apontando para: `https://chat.personizi.com.br/whatsapp-webhook`

**Ou verifique nos logs:**
```bash
tail -f logs/quepasa.log | grep "configureWebhook"
```

### 3. **O Que Precisa Estar Rodando?**

#### ✅ **NÃO Precisa Rodar Nada Manualmente (Funciona Automaticamente)**

O sistema funciona de forma **reativa** através de webhooks:

1. **Webhook Público** (`/whatsapp-webhook`):
   - Recebe mensagens do Quepasa API automaticamente
   - Processa e cria conversas/mensagens no banco
   - **Funciona automaticamente** - não precisa rodar nada

2. **Tempo Real (WebSocket/Polling)** - **OPCIONAL**:
   - Para atualizações em tempo real no chat
   - Pode usar **Polling** (não precisa rodar nada) ou **WebSocket** (precisa rodar servidor)

### 4. **Como Funciona o Fluxo de Mensagens**

```
┌─────────────────┐
│  WhatsApp App   │
│  (Usuário)      │
└────────┬────────┘
         │ Envia mensagem
         ▼
┌─────────────────┐
│  Quepasa API    │
│  (Servidor)     │
└────────┬────────┘
         │ Webhook POST
         ▼
┌─────────────────┐
│ /whatsapp-webhook│
│  (Seu Sistema)  │
└────────┬────────┘
         │ Processa
         ▼
┌─────────────────┐
│  WhatsAppService │
│  processWebhook()│
└────────┬────────┘
         │
         ├─► Cria/Atualiza Contato
         ├─► Cria/Atualiza Conversa
         ├─► Salva Mensagem
         └─► Dispara Automações
```

### 5. **Testando se Está Funcionando**

#### **Opção 1: Enviar Mensagem de Teste**
1. Envie uma mensagem do WhatsApp para o número conectado
2. Verifique se aparece em `/conversations`
3. Verifique os logs: `logs/app.log` ou `logs/quepasa.log`

#### **Opção 2: Verificar Logs**
```bash
# Ver logs em tempo real
tail -f logs/app.log | grep "WhatsApp"
tail -f logs/quepasa.log
```

#### **Opção 3: Verificar Webhook no Quepasa**
- No painel do Quepasa, verifique se há eventos de webhook sendo enviados
- Verifique se há erros de conexão

### 6. **Tempo Real (Opcional - Para Atualizações Instantâneas)**

#### **Modo Polling (Recomendado - Não Precisa Rodar Nada)**
- Já está configurado por padrão
- O navegador verifica atualizações a cada 3 segundos
- Funciona automaticamente

#### **Modo WebSocket (Opcional - Precisa Rodar Servidor)**
Se quiser usar WebSocket para atualizações mais rápidas:

```bash
# Rodar servidor WebSocket
php public/websocket-server.php
```

**Ou em background:**
```bash
nohup php public/websocket-server.php > logs/websocket.log 2>&1 &
```

**Configurar no Sistema:**
- Acesse `/settings?tab=websocket`
- Escolha "Apenas WebSocket" ou "Automático"
- Configure porta (padrão: 8080)

### 7. **Verificando se Mensagens Estão Sendo Recebidas**

#### **Checklist:**

- [ ] WhatsApp está conectado (status "Conectado" na interface)
- [ ] Webhook configurado no Quepasa (verificar logs ou painel Quepasa)
- [ ] URL do webhook está acessível: `https://chat.personizi.com.br/whatsapp-webhook`
- [ ] Logs mostram webhooks sendo recebidos (`logs/app.log`)
- [ ] Mensagens aparecem em `/conversations`

### 8. **Troubleshooting**

#### **Mensagens Não Aparecem:**

1. **Verificar Webhook:**
   ```bash
   # Ver se webhook está sendo chamado
   tail -f logs/app.log | grep "WhatsApp Webhook"
   ```

2. **Verificar se Quepasa está enviando:**
   - Acesse painel do Quepasa
   - Verifique logs de webhook
   - Veja se há erros de conexão

3. **Testar Webhook Manualmente:**
   ```bash
   curl -X POST https://chat.personizi.com.br/whatsapp-webhook \
     -H "Content-Type: application/json" \
     -d '{
       "from": "5511999999999@s.whatsapp.net",
       "text": "Teste",
       "id": "test123",
       "trackid": "seu-trackid",
       "chatid": "553591970289:85@s.whatsapp.net"
     }'
   ```

4. **Verificar Permissões:**
   - Verifique se o arquivo `public/whatsapp-webhook.php` tem permissões de leitura
   - Verifique se o servidor web pode executar PHP

5. **Verificar Banco de Dados:**
   - Verifique se as mensagens estão sendo salvas:
   ```sql
   SELECT * FROM messages ORDER BY created_at DESC LIMIT 10;
   SELECT * FROM conversations ORDER BY created_at DESC LIMIT 10;
   ```

### 9. **Próximos Passos Após Conectar**

1. ✅ **WhatsApp Conectado** - Feito!
2. ✅ **Webhook Configurado** - Deve estar automático
3. ⏭️ **Enviar Mensagem de Teste** - Envie do WhatsApp para o número conectado
4. ⏭️ **Verificar Conversas** - Acesse `/conversations` e veja se aparece
5. ⏭️ **Configurar Automações** (Opcional) - Em `/automations`
6. ⏭️ **Configurar Agentes** (Opcional) - Em `/agents`

## 📝 Resumo

**O sistema funciona automaticamente após conectar o WhatsApp:**

- ✅ Webhook recebe mensagens automaticamente
- ✅ Conversas são criadas automaticamente
- ✅ Mensagens aparecem no chat automaticamente
- ✅ Não precisa rodar nenhum processo manualmente (exceto WebSocket se quiser)

**Para verificar se está funcionando:**
1. Envie uma mensagem do WhatsApp
2. Verifique `/conversations`
3. Veja os logs se necessário

**Tempo Real (Opcional):**
- Polling funciona automaticamente (sem processos)
- WebSocket precisa rodar `php public/websocket-server.php` (opcional)

---

## 🔍 O Que Acontece Quando Não Há Configurações?

### **Sem Funil Configurado:**
- ✅ Conversa é criada normalmente
- ✅ Status: `open`
- ⚠️ `funnel_id` = `NULL` (sem funil)
- ⚠️ `funnel_stage_id` = `NULL` (sem estágio)

### **Sem Agentes Atribuídos:**
- ✅ Conversa é criada normalmente
- ✅ Status: `open`
- ⚠️ `agent_id` = `NULL` (sem agente atribuído)
- ✅ Conversa aparece na listagem geral (`/conversations`)
- ✅ Qualquer agente pode visualizar e assumir manualmente

### **Sem Atribuição Automática Configurada:**
- ✅ Conversa é criada normalmente
- ✅ Sistema tenta atribuir automaticamente via `ConversationSettingsService`
- ⚠️ Se não houver regras configuradas, a conversa fica sem atribuição
- ✅ Conversa aparece na listagem para todos os agentes
- ✅ Agentes podem assumir manualmente clicando na conversa

### **Resumo do Comportamento Padrão:**

```
Mensagem Recebida
    ↓
Conversa Criada (status: 'open')
    ↓
Tenta Atribuição Automática
    ↓
┌─────────────────────────────┐
│ Se não há configurações:    │
│ - agent_id = NULL           │
│ - funnel_id = NULL          │
│ - funnel_stage_id = NULL    │
│ - status = 'open'           │
│                             │
│ Conversa aparece para      │
│ TODOS os agentes            │
│                             │
│ Qualquer agente pode       │
│ assumir manualmente         │
└─────────────────────────────┘
```

### **Como Assumir Conversas Manualmente:**

1. Acesse `/conversations`
2. Veja conversas sem agente atribuído (aparecem para todos)
3. Clique na conversa para abrir
4. A conversa será automaticamente atribuída ao agente que abriu

### **Próximos Passos Recomendados:**

1. **Configurar Funis** (Opcional):
   - Acesse `/funnels`
   - Crie funis e estágios para organizar conversas

2. **Configurar Atribuição Automática** (Opcional):
   - Acesse `/settings?tab=conversations`
   - Configure regras de atribuição automática

3. **Criar Agentes** (Recomendado):
   - Acesse `/agents` ou `/users`
   - Crie usuários com permissão de agente

4. **Testar Fluxo Completo:**
   - Envie mensagem do WhatsApp
   - Verifique se aparece em `/conversations`
   - Abra a conversa para assumir
   - Responda e veja se funciona

---

**Última atualização:** 2025-12-06

