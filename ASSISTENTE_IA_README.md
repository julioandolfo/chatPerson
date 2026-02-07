# 🎯 Assistente IA - Copiloto do Agente

## 📋 Sumário
- [Visão Geral](#visão-geral)
- [O Que Foi Implementado](#o-que-foi-implementado)
- [Funcionalidades](#funcionalidades)
- [Agentes Especializados](#agentes-especializados)
- [Como Usar](#como-usar)
- [Dashboard e Relatórios](#dashboard-e-relatórios)
- [Instalação](#instalação)
- [Arquitetura](#arquitetura)

---

## 🎯 Visão Geral

O **Assistente IA** é uma ferramenta de copiloto que auxilia os agentes humanos durante o atendimento ao cliente. Ele oferece 8 funcionalidades inteligentes como gerar sugestões de resposta, resumir conversas, analisar sentimento, traduzir mensagens e muito mais.

### ✨ Principais Benefícios

- **Zero Configuração**: Funciona imediatamente após instalação
- **8 Funcionalidades Especializadas**: Cada uma com agente IA otimizado
- **Relatórios Completos**: Dashboard com uso, custos e performance
- **Criação Automática**: Agentes são criados automaticamente quando necessário

---

## 🚀 O Que Foi Implementado

### 1. **Seed de Agentes Especializados**
📁 `database/seeds/006_create_ai_assistant_specialized_agents.php`

- Cria 8 agentes de IA especializados automaticamente
- Cada agente otimizado para uma funcionalidade específica
- Prompts profissionais e configurações ajustadas
- Vinculação automática às funcionalidades

### 2. **Criação Automática de Agentes**
📁 `app/Controllers/AIAssistantController.php`

- Remove erro quando não há agentes configurados
- Detecta falta de agentes e cria automaticamente
- Executa seed de forma transparente
- Usuário não precisa fazer nada

### 3. **Dashboard com Estatísticas do Assistente IA**
📁 `app/Controllers/DashboardController.php` + `views/dashboard/ai-dashboard.php`

- Método `getAIAssistantStats()` para coletar métricas
- Seção completa no Dashboard de IA
- Relatórios de uso, custo, performance
- Estatísticas por funcionalidade e agente
- Top usuários e modelos

### 4. **Query SQL Completa**
📁 `database/manual_queries/create_ai_assistant_specialized_agents.sql`

- Query pronta para executar diretamente no MySQL
- Cria todos os 8 agentes de uma vez
- Vincula automaticamente às funcionalidades
- Não precisa rodar migrates ou seeds

---

## 🎨 Funcionalidades

O Assistente IA oferece 8 funcionalidades especializadas:

| # | Funcionalidade | Descrição | Agente |
|---|----------------|-----------|--------|
| 1 | **Gerar Resposta** | Sugestões inteligentes de resposta baseadas no contexto | GPT-4o (temp: 0.7) |
| 2 | **Resumir Conversa** | Resumo estruturado com pontos-chave e ações | GPT-4o (temp: 0.3) |
| 3 | **Sugerir Tags** | Categorização automática da conversa | GPT-4o (temp: 0.2) |
| 4 | **Análise de Sentimento** | Detecta emoções e estado emocional do cliente | GPT-4o (temp: 0.4) |
| 5 | **Traduzir Mensagens** | Tradução contextual mantendo tom e formatação | GPT-4o (temp: 0.3) |
| 6 | **Melhorar Gramática** | Correção e melhoria de textos profissionais | GPT-4o (temp: 0.2) |
| 7 | **Sugerir Próximos Passos** | Recomendações de ações e estratégias | GPT-4o (temp: 0.6) |
| 8 | **Extrair Informações** | Extração estruturada de dados (email, telefone, etc) | GPT-4o (temp: 0.1) |

---

## 🤖 Agentes Especializados

Cada funcionalidade tem um agente IA especializado com prompt otimizado:

### 1. Assistente de Respostas

**Especialidade**: Gerar sugestões de resposta profissionais

**Prompt**: Focado em criar respostas claras, empáticas e contextualizadas

**Configurações**:
- Modelo: GPT-4o
- Temperature: 0.7 (criativo mas focado)
- Max Tokens: 1000

**Formato de Saída**: 3 sugestões separadas por `---`

---

### 2. Assistente de Resumos

**Especialidade**: Criar resumos estruturados

**Prompt**: Extrai pontos-chave, ações realizadas e próximos passos

**Configurações**:
- Modelo: GPT-4o
- Temperature: 0.3 (preciso e objetivo)
- Max Tokens: 800

**Formato de Saída**:
```
📌 Assunto Principal: [tema]
🗣️ Solicitação do Cliente: [o que quer]
💬 Principais Pontos: [resumo]
✅ Ações Realizadas: [o que foi feito]
⏳ Próximos Passos: [pendências]
😊 Sentimento: [positivo/neutro/negativo]
```

---

### 3. Assistente de Tags

**Especialidade**: Categorização e organização

**Prompt**: Sugere tags precisas e relevantes

**Configurações**:
- Modelo: GPT-4o
- Temperature: 0.2 (muito preciso)
- Max Tokens: 200

**Formato de Saída**: Lista de tags (até 5) sem numeração

---

### 4. Assistente de Sentimentos

**Especialidade**: Análise emocional

**Prompt**: Detecta sentimentos, emoções e alertas críticos

**Configurações**:
- Modelo: GPT-4o
- Temperature: 0.4 (analítico)
- Max Tokens: 500

**Formato de Saída**: JSON com sentimento, intensidade, emoções, evolução e recomendação

---

### 5. Assistente de Tradução

**Especialidade**: Tradução contextual

**Prompt**: Mantém tom, formatação e intenção original

**Configurações**:
- Modelo: GPT-4o
- Temperature: 0.3 (preciso)
- Max Tokens: 2000

**Formato de Saída**: Texto traduzido com formatação preservada

---

### 6. Assistente de Gramática

**Especialidade**: Correção e melhoria de textos

**Prompt**: Corrige erros mantendo personalidade do autor

**Configurações**:
- Modelo: GPT-4o
- Temperature: 0.2 (muito preciso)
- Max Tokens: 1500

**Formato de Saída**: Texto corrigido sem marcações

---

### 7. Assistente de Planejamento

**Especialidade**: Sugerir ações e estratégias

**Prompt**: Identifica gaps e recomenda próximos passos

**Configurações**:
- Modelo: GPT-4o
- Temperature: 0.6 (criativo mas estruturado)
- Max Tokens: 800

**Formato de Saída**: Lista estruturada com emojis por categoria

---

### 8. Assistente de Extração

**Especialidade**: Extração de dados estruturados

**Prompt**: Identifica e organiza informações importantes

**Configurações**:
- Modelo: GPT-4o
- Temperature: 0.1 (extremamente preciso)
- Max Tokens: 600

**Formato de Saída**: JSON com contatos, datas, valores, keywords

---

## 📊 Dashboard e Relatórios

### Localização
`/dashboard/ai` → Seção "🎯 Assistente IA - Copiloto do Agente"

### Métricas Disponíveis

#### 1. **Cards Principais**
- Taxa de Sucesso (%)
- Custo Total ($)
- Tokens Utilizados
- Tempo Médio de Resposta (ms)

#### 2. **Tabela: Uso por Funcionalidade**
Mostra para cada funcionalidade:
- Total de usos
- Taxa de sucesso
- Tokens consumidos
- Custo gerado
- Tempo médio de execução

#### 3. **Cards: Agentes Especializados**
Para cada agente:
- Nome e modelo
- Número de usos
- Tokens e custo
- Tempo médio

#### 4. **Top Usuários**
Ranking dos usuários que mais utilizam o Assistente:
- Nome do usuário
- Total de usos
- Custo gerado

#### 5. **Filtros**
- Data inicial e final
- Atualização em tempo real

---

## 💻 Como Usar

### Para o Usuário Final

1. **Abrir Conversa**
   - Clique em uma conversa no chat

2. **Acessar Assistente IA**
   - Clique no botão "Assistente IA" (ícone de robô)
   - Modal abre automaticamente

3. **Escolher Funcionalidade**
   - Selecione uma das 8 funcionalidades disponíveis
   - Configure opções (tom, quantidade, etc)

4. **Gerar Resultado**
   - Clique em "Gerar" ou "Executar"
   - Aguarde processamento
   - Veja resultado e use conforme necessário

### Para Administradores

1. **Configurar API Key OpenAI**
   - Vá em `Configurações > Geral`
   - Adicione sua API Key da OpenAI
   - Salve

2. **Visualizar Relatórios**
   - Acesse `Dashboard > Dashboard de IA`
   - Role até a seção "Assistente IA"
   - Visualize métricas e custos

3. **Gerenciar Agentes** (Opcional)
   - Vá em `Agentes de IA`
   - Veja os agentes criados automaticamente
   - Edite prompts se necessário (avançado)

---

## 🔧 Instalação

### Opção 1: Via Seed (Recomendado)

```bash
# Executar seed
php database/run_seed.php 006_create_ai_assistant_specialized_agents
```

### Opção 2: Via Query SQL (Mais Rápido)

```bash
# Conectar ao MySQL
mysql -u root -p nome_do_banco

# Executar arquivo SQL
source database/manual_queries/create_ai_assistant_specialized_agents.sql

# Ou copiar e colar o conteúdo diretamente
```

### Opção 3: Automático (Já Implementado!)

- Não faça nada!
- Os agentes são criados automaticamente quando necessário
- Na primeira vez que alguém clicar em "Assistente IA"
- Se não houver agentes, o sistema cria automaticamente

---

## 🏗️ Arquitetura

### Fluxo de Funcionamento

```
1. Usuário clica em "Assistente IA"
   ↓
2. JavaScript chama checkAIAssistantAvailability()
   ↓
3. Backend verifica:
   ✓ API Key configurada?
   ✓ Funcionalidades ativas?
   ✓ Agentes disponíveis?
   ↓
4. Se não houver agentes:
   → Executa seed automaticamente
   → Cria 8 agentes especializados
   → Vincula às funcionalidades
   ↓
5. Modal abre com funcionalidades
   ↓
6. Usuário seleciona funcionalidade
   ↓
7. Sistema usa agente especializado correspondente
   ↓
8. OpenAI processa com prompt otimizado
   ↓
9. Resultado retorna formatado
   ↓
10. Log salvo na tabela ai_assistant_logs
```

### Tabelas Envolvidas

```
ai_agents
├── Armazena os 8 agentes especializados
└── Vinculados via default_ai_agent_id

ai_assistant_features
├── 8 funcionalidades do Assistente
└── Referencia ai_agents.id

ai_assistant_logs
├── Registra cada uso do Assistente
├── Armazena tokens, custo, tempo
└── Usado para relatórios no dashboard

ai_assistant_responses (opcional)
└── Cache de respostas geradas
```

### Arquivos Principais

```
app/
├── Controllers/
│   ├── AIAssistantController.php (checkAvailability com auto-create)
│   └── DashboardController.php (getAIAssistantStats)
├── Models/
│   ├── AIAgent.php
│   ├── AIAssistantFeature.php
│   └── AIAssistantLog.php
└── Services/
    ├── AIAssistantService.php
    └── AIAgentSelectorService.php

views/
└── dashboard/
    └── ai-dashboard.php (nova seção de estatísticas)

database/
├── seeds/
│   └── 006_create_ai_assistant_specialized_agents.php
└── manual_queries/
    └── create_ai_assistant_specialized_agents.sql
```

---

## 📈 Métricas e Custos

### Custo Estimado por Uso (GPT-4o)

| Funcionalidade | Tokens Médios | Custo Aprox. | Tempo Médio |
|----------------|---------------|--------------|-------------|
| Gerar Resposta | 800 | $0.004 | 2-3s |
| Resumir | 600 | $0.003 | 1-2s |
| Sugerir Tags | 150 | $0.001 | 1s |
| Sentimento | 400 | $0.002 | 1-2s |
| Traduzir | 1200 | $0.006 | 2-3s |
| Gramática | 900 | $0.0045 | 2s |
| Próximos Passos | 600 | $0.003 | 1-2s |
| Extrair Info | 500 | $0.0025 | 1-2s |

**Custo médio total por conversa assistida**: ~$0.025

---

## 🎓 Dicas e Boas Práticas

### Para Agentes

1. **Use Gerar Resposta** quando não souber como responder
2. **Use Resumir** antes de transferir conversa
3. **Use Sentimento** em conversas delicadas
4. **Use Próximos Passos** quando estiver perdido

### Para Administradores

1. **Monitore custos** no dashboard regularmente
2. **Analise funcionalidades mais usadas** para otimizar
3. **Veja top usuários** para identificar champions
4. **Configure alertas** se custo passar de $X por dia

### Para Desenvolvedores

1. **Não delete agentes do sistema** (tipo 'assistant')
2. **Prompts podem ser editados** mas teste antes
3. **Logs são salvos automaticamente** para análise
4. **Temperature controla criatividade** (baixo = preciso, alto = criativo)

---

## ❓ FAQ

### 1. O que acontece se eu deletar um agente especializado?

O sistema detectará a falta e criará novamente automaticamente na próxima vez que alguém usar o Assistente IA.

### 2. Posso editar os prompts dos agentes?

Sim, mas cuidado! Os prompts foram otimizados profissionalmente. Se editar, teste bem antes de usar em produção.

### 3. Como sei quanto estou gastando?

Acesse `Dashboard > Dashboard de IA` e veja a seção "Assistente IA - Copiloto do Agente". Lá tem custo total, por funcionalidade e por agente.

### 4. Posso usar outro modelo além do GPT-4o?

Sim, edite o agente em `Agentes de IA` e altere o campo "Modelo". Opções: gpt-4o, gpt-4-turbo, gpt-3.5-turbo.

### 5. As funcionalidades funcionam em português?

Sim! Todos os prompts foram escritos em português brasileiro e os agentes entendem perfeitamente o contexto brasileiro.

### 6. Quanto custa por mês?

Depende do uso. Com 100 usos por dia (~3000/mês), o custo é aproximadamente $75/mês. Monitor no dashboard!

---

## 🚀 Próximos Passos

### Possíveis Melhorias Futuras

- [ ] Adicionar mais modelos (Anthropic Claude, Google Gemini)
- [ ] Permitir usuários configurarem agentes personalizados
- [ ] Adicionar funcionalidade de "Verificar Gramática em Tempo Real"
- [ ] Criar atalhos de teclado para funcionalidades
- [ ] Integrar com histórico de conversas antigas
- [ ] Adicionar suporte a imagens nas funcionalidades
- [ ] Criar API pública para integração externa
- [ ] Implementar cache inteligente para respostas similares

---

## 📞 Suporte

Se tiver dúvidas ou problemas:

1. Verifique este README primeiro
2. Verifique logs em `storage/logs/`
3. Teste a API Key OpenAI diretamente
4. Verifique permissões do usuário

---

## 🎉 Conclusão

O **Assistente IA** está 100% funcional e pronto para uso! Ele funciona automaticamente, sem necessidade de configuração manual, e oferece 8 funcionalidades poderosas para auxiliar seus agentes no atendimento.

**Principais Benefícios**:
- ✅ Zero configuração necessária
- ✅ 8 agentes especializados otimizados
- ✅ Dashboard completo com métricas
- ✅ Criação automática quando necessário
- ✅ Custos transparentes e controláveis

Bom uso! 🚀
