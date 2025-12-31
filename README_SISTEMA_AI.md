# 🤖 Sistema de Conversas & AI Agents

> **Plataforma completa de atendimento multicanal com Inteligência Artificial**

[![Status](https://img.shields.io/badge/Status-Operacional-brightgreen)]()
[![Versão](https://img.shields.io/badge/Versão-1.0-blue)]()
[![Documentação](https://img.shields.io/badge/Documentação-Completa-success)]()

---

## 📖 O QUE É?

Sistema **multiatendimento multicanal** que combina **Agentes Humanos** e **Agentes de IA** (OpenAI GPT-4/3.5) para atender clientes em tempo real através de WhatsApp, Instagram, Telegram e outros canais.

### ✨ Principais Recursos

- 🤖 **AI Agents** - Bots inteligentes usando OpenAI
- 🛠️ **Function Calling** - IA pode usar ferramentas (buscar pedidos, APIs, etc)
- 🔄 **Distribuição Automática** - Round-robin, por carga, performance
- 💬 **Multicanal** - WhatsApp, Instagram, Telegram
- ⚡ **Tempo Real** - WebSocket + Polling
- 📊 **Analytics** - Tokens, custos, métricas completas
- 🎯 **AI Branching** - Roteamento inteligente por intents
- 💰 **Controle de Custos** - Rate limiting, limites configuráveis

---

## 🚀 INÍCIO RÁPIDO

### 1. Primeira Leitura (15 minutos)

👉 Comece por aqui: **[RESUMO_RAPIDO_SISTEMA_AI.md](RESUMO_RAPIDO_SISTEMA_AI.md)**

Você vai aprender:
- Conceitos básicos
- Fluxo de mensagem
- Como criar/atribuir AI Agents
- Comandos essenciais

### 2. Entender Visualmente (10 minutos)

👉 Veja os diagramas: **[DIAGRAMAS_VISUAIS_SISTEMA_AI.md](DIAGRAMAS_VISUAIS_SISTEMA_AI.md)**

14 diagramas explicando:
- Fluxo completo de mensagem
- Arquitetura do sistema
- Modelo de dados
- Processamento OpenAI
- E muito mais...

### 3. Ver na Prática (20 minutos)

👉 Analise logs reais: **[ANALISE_LOGS_SISTEMA.md](ANALISE_LOGS_SISTEMA.md)**

Entenda:
- Como uma conversa real funciona
- O que cada log significa
- Como debugar problemas
- Métricas e custos

---

## 📚 DOCUMENTAÇÃO COMPLETA

### 📑 Índice Geral

**[INDICE_DOCUMENTACAO_SISTEMA_AI.md](INDICE_DOCUMENTACAO_SISTEMA_AI.md)**

Seu mapa completo da documentação com:
- Navegação por objetivo
- Índice remissivo
- Roteiros de estudo
- Links rápidos

### 📖 Documentos Disponíveis

| Documento | Descrição | Quando Usar |
|-----------|-----------|-------------|
| **[SISTEMA_COMPLETO_CONVERSATIONS_AI_AGENTS.md](SISTEMA_COMPLETO_CONVERSATIONS_AI_AGENTS.md)** | Documentação técnica completa (1000+ linhas) | Desenvolvimento, manutenção, arquitetura |
| **[RESUMO_RAPIDO_SISTEMA_AI.md](RESUMO_RAPIDO_SISTEMA_AI.md)** | Guia de referência rápida | Consultas rápidas, comandos, troubleshooting |
| **[ANALISE_LOGS_SISTEMA.md](ANALISE_LOGS_SISTEMA.md)** | Análise de logs reais | Debug, aprender com exemplos |
| **[DIAGRAMAS_VISUAIS_SISTEMA_AI.md](DIAGRAMAS_VISUAIS_SISTEMA_AI.md)** | 14 diagramas visuais (Mermaid) | Visualizar fluxos, explicar sistema |
| **[FAQ_SISTEMA_AI_AGENTS.md](FAQ_SISTEMA_AI_AGENTS.md)** | 70+ perguntas e respostas | Dúvidas pontuais, troubleshooting |
| **[INDICE_DOCUMENTACAO_SISTEMA_AI.md](INDICE_DOCUMENTACAO_SISTEMA_AI.md)** | Índice organizador | Navegar pela documentação |

---

## 💡 CASOS DE USO

### Por Perfil

#### 👨‍💼 **Gerente/Product Owner**
1. Leia: **[RESUMO_RAPIDO](RESUMO_RAPIDO_SISTEMA_AI.md)** (Seção: Visão Geral)
2. Veja: **[DIAGRAMAS](DIAGRAMAS_VISUAIS_SISTEMA_AI.md)** (Diagramas 1, 2)
3. Entenda custos: **[FAQ](FAQ_SISTEMA_AI_AGENTS.md)** (Seção: Custos)

#### 👨‍💻 **Desenvolvedor**
1. Estude: **[SISTEMA_COMPLETO](SISTEMA_COMPLETO_CONVERSATIONS_AI_AGENTS.md)** (Completo)
2. Veja: **[DIAGRAMAS](DIAGRAMAS_VISUAIS_SISTEMA_AI.md)** (Todos)
3. Pratique: Exemplos em **[SISTEMA_COMPLETO](SISTEMA_COMPLETO_CONVERSATIONS_AI_AGENTS.md)** (Seção 11)

#### 🔧 **DevOps/SysAdmin**
1. Configure: **[RESUMO_RAPIDO](RESUMO_RAPIDO_SISTEMA_AI.md)** (Seção: Configurações)
2. Monitore: **[ANALISE_LOGS](ANALISE_LOGS_SISTEMA.md)** (Seção: Logs)
3. Otimize: **[FAQ](FAQ_SISTEMA_AI_AGENTS.md)** (Seção: Performance)

#### 🎯 **Suporte/QA**
1. Aprenda: **[RESUMO_RAPIDO](RESUMO_RAPIDO_SISTEMA_AI.md)** (Completo)
2. Resolva: **[FAQ](FAQ_SISTEMA_AI_AGENTS.md)** (Seção: Troubleshooting)
3. Debug: **[ANALISE_LOGS](ANALISE_LOGS_SISTEMA.md)** (Seção 2)

---

## 🎯 EXEMPLOS RÁPIDOS

### Criar AI Agent

```php
use App\Services\AIAgentService;

$agentId = AIAgentService::create([
    'name' => 'Atendente Virtual',
    'description' => 'Agente de suporte',
    'agent_type' => 'CS',
    'prompt' => 'Você é um assistente prestativo...',
    'model' => 'gpt-3.5-turbo',
    'temperature' => 0.7,
    'max_tokens' => 2000,
    'max_conversations' => 0,  // Ilimitado
    'enabled' => true
]);
```

### Atribuir IA à Conversa

```php
use App\Services\ConversationAIService;

ConversationAIService::addAIAgent(474, [
    'ai_agent_id' => 21,
    'process_immediately' => true,
    'assume_conversation' => true
]);
```

### Ver Status da IA

```php
$status = ConversationAIService::getAIStatus(474);

echo "IA Ativa: " . ($status['has_ai'] ? 'Sim' : 'Não');
echo "Tokens: " . $status['ai_conversation']['tokens_used'];
echo "Custo: $" . $status['ai_conversation']['cost'];
```

### Consultar Custos

```sql
SELECT 
    ai_agent_id,
    COUNT(*) as conversas,
    SUM(tokens_used) as tokens,
    SUM(cost) as custo_usd
FROM ai_conversations
WHERE created_at >= '2025-12-01'
GROUP BY ai_agent_id;
```

---

## ❓ PERGUNTAS FREQUENTES

### 🤔 Como sei se a IA está ativa em uma conversa?

Verifique se existe `ai_conversations` com `status = 'active'`:

```sql
SELECT * FROM ai_conversations 
WHERE conversation_id = 474 
  AND status = 'active';
```

### 💰 Quanto custa usar IA?

- **GPT-3.5-turbo:** ~$0.0005/conversa
- **GPT-4:** ~$0.01/conversa

Média de 10-50 conversas por $1.

### 🚀 Quantas conversas simultâneas suporta?

- **Agentes Humanos:** 5-10 por agente
- **Agentes de IA:** Ilimitado (limitado apenas por OpenAI rate limit)

### 🔧 Como debugar problemas?

1. Veja logs: `logs/ai-agents.log`
2. Consulte: **[FAQ_SISTEMA_AI_AGENTS.md](FAQ_SISTEMA_AI_AGENTS.md)** (Seção Troubleshooting)
3. Analise exemplo: **[ANALISE_LOGS_SISTEMA.md](ANALISE_LOGS_SISTEMA.md)**

**👉 Mais 70+ perguntas em:** [FAQ_SISTEMA_AI_AGENTS.md](FAQ_SISTEMA_AI_AGENTS.md)

---

## 🗺️ ROADMAP DE APRENDIZADO

### 🟢 Nível 1: Iniciante (2-3 horas)

**Objetivo:** Entender o básico

```
1. RESUMO_RAPIDO (30 min)
   ↓
2. DIAGRAMAS (Diagramas 1, 2, 3) (45 min)
   ↓
3. ANALISE_LOGS (Seção 1) (60 min)
   ↓
4. SISTEMA_COMPLETO (Seções 1, 4) (45 min)
```

**✅ Checkpoint:** Explicar fluxo básico de mensagem

---

### 🟡 Nível 2: Intermediário (4-5 horas)

**Objetivo:** Implementar funcionalidades

```
1. SISTEMA_COMPLETO (Seções 3, 5, 6, 11) (2h)
   ↓
2. DIAGRAMAS (4, 5, 6, 8) (1h)
   ↓
3. RESUMO_RAPIDO (Seções 7-10) (1h)
   ↓
4. ANALISE_LOGS (Seções 2, 3, 4) (1h)
```

**✅ Checkpoint:** Criar agente, tool, configurar distribuição

---

### 🔴 Nível 3: Avançado (6-8 horas)

**Objetivo:** Dominar completamente

```
1. SISTEMA_COMPLETO (Completo + Exemplos) (3h)
   ↓
2. DIAGRAMAS (Todos) (2h)
   ↓
3. ANALISE_LOGS (Completo + Implementar recomendações) (2h)
   ↓
4. Prática (Criar 3 agentes, 5 tools, testar) (1-2h)
```

**✅ Checkpoint:** Otimizar sistema, criar funcionalidades avançadas

---

## 📊 ESTATÍSTICAS DO SISTEMA

### Status Atual (31/12/2025)

- ✅ **Funcional:** 100%
- 📱 **Canais:** WhatsApp (Quepasa)
- 🤖 **AI Provider:** OpenAI (GPT-4, GPT-3.5-turbo)
- 🛠️ **Tools:** WooCommerce, N8N, Database, API, System
- ⚡ **Realtime:** Polling (WebSocket suportado)
- 💾 **Database:** MySQL 8.0+
- 🐘 **PHP:** 8.1+

### Performance

- ⚡ **Resposta IA:** 1-5s
- 💰 **Custo médio:** $0.001-0.01/conversa
- 🔄 **Processamento:** Assíncrono
- 📊 **Monitoramento:** Logs completos

---

## 🤝 CONTRIBUINDO

### Melhorar Documentação

Encontrou algo que pode ser melhorado?

1. Identifique o documento
2. Sugira alterações
3. Mantenha formatação consistente
4. Atualize índices se necessário

### Reportar Problemas

Encontrou um bug ou tem dúvida?

1. Verifique **[FAQ](FAQ_SISTEMA_AI_AGENTS.md)** primeiro
2. Consulte **[Troubleshooting](RESUMO_RAPIDO_SISTEMA_AI.md)** (Seção 13)
3. Veja **[Análise de Logs](ANALISE_LOGS_SISTEMA.md)**
4. Abra issue ou entre em contato

---

## 📞 SUPORTE

### Recursos de Ajuda

1. **📖 Documentação** - Leia este README e documentos linkados
2. **❓ FAQ** - [70+ perguntas respondidas](FAQ_SISTEMA_AI_AGENTS.md)
3. **📊 Logs** - Analise em `logs/` ou [guia de análise](ANALISE_LOGS_SISTEMA.md)
4. **🔍 Debug** - [Guia de troubleshooting](RESUMO_RAPIDO_SISTEMA_AI.md#-troubleshooting)

### Contato

Para suporte direto, entre em contato com o time de desenvolvimento.

---

## 📄 LICENÇA

Este sistema é proprietário e confidencial.

---

## 🎓 PRÓXIMOS PASSOS

### 👉 Para Começar AGORA

1. **Leia:** [RESUMO_RAPIDO_SISTEMA_AI.md](RESUMO_RAPIDO_SISTEMA_AI.md) (15 min)
2. **Veja:** [DIAGRAMAS_VISUAIS_SISTEMA_AI.md](DIAGRAMAS_VISUAIS_SISTEMA_AI.md) (10 min)
3. **Pratique:** Criar seu primeiro AI Agent (30 min)

### 📚 Para Aprofundar

1. **Estude:** [SISTEMA_COMPLETO_CONVERSATIONS_AI_AGENTS.md](SISTEMA_COMPLETO_CONVERSATIONS_AI_AGENTS.md)
2. **Consulte:** [FAQ_SISTEMA_AI_AGENTS.md](FAQ_SISTEMA_AI_AGENTS.md)
3. **Explore:** [ANALISE_LOGS_SISTEMA.md](ANALISE_LOGS_SISTEMA.md)

### 🗺️ Para Navegar

Use o **[ÍNDICE COMPLETO](INDICE_DOCUMENTACAO_SISTEMA_AI.md)** como seu mapa.

---

## ⭐ DESTAQUES

### 🏆 Documentação Completa

- ✅ 6 documentos especializados
- ✅ 1000+ linhas de conteúdo técnico
- ✅ 14 diagramas visuais (Mermaid)
- ✅ 70+ perguntas e respostas
- ✅ Exemplos práticos reais
- ✅ Análise de logs detalhada
- ✅ Guias de troubleshooting

### 🎯 Para Todos os Perfis

- 👨‍💼 **Gestores** - Visão estratégica
- 👨‍💻 **Desenvolvedores** - Implementação técnica
- 🔧 **DevOps** - Configuração e monitoramento
- 🎯 **Suporte** - Resolução de problemas

### 📈 Sempre Atualizado

**Última atualização:** 31/12/2025  
**Versão da documentação:** 1.0  
**Status:** Completo e operacional

---

**🚀 Comece agora:** [RESUMO_RAPIDO_SISTEMA_AI.md](RESUMO_RAPIDO_SISTEMA_AI.md)

---

<div align="center">

**Feito com ❤️ pela equipe de desenvolvimento**

[![Documentação](https://img.shields.io/badge/📖-Documentação-blue)](INDICE_DOCUMENTACAO_SISTEMA_AI.md)
[![FAQ](https://img.shields.io/badge/❓-FAQ-green)](FAQ_SISTEMA_AI_AGENTS.md)
[![Diagramas](https://img.shields.io/badge/📊-Diagramas-purple)](DIAGRAMAS_VISUAIS_SISTEMA_AI.md)

</div>
