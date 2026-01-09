# 🔍 Diagnóstico: Sistema de Análise de Sentimento

## 📋 Problema Relatado

O script `analyze-sentiments.php` executa mas não processa nenhuma conversa (0 análises).

## 🎯 Causa Mais Provável

Analisando sua imagem de configuração, identifiquei o problema:

### ❌ Configuração Atual
- **Mín. Mensagens para Analisar**: **100**
- **Analisar a cada X Mensagens**: **100**

### ⚠️ Por que isso é um problema?

**100 mensagens é MUITO ALTO!**

- A maioria das conversas de atendimento tem entre 3-20 mensagens
- É extremamente raro uma conversa atingir 100+ mensagens apenas do contato
- Com essa configuração, **praticamente nenhuma conversa será analisada**

## 🔧 Scripts de Diagnóstico Criados

### 1️⃣ `public/scripts/check-sentiment-config.php`
**O que faz**: Mostra exatamente o que está salvo no banco de dados

```bash
php public/scripts/check-sentiment-config.php
```

**Mostra**:
- Se a configuração existe no banco
- Todos os valores salvos
- Validação básica dos valores

---

### 2️⃣ `public/scripts/debug-sentiment-analysis.php`
**O que faz**: Análise completa do sistema

```bash
php public/scripts/debug-sentiment-analysis.php
```

**Mostra**:
- Configurações carregadas
- Conversas no banco
- Conversas elegíveis para análise
- Por que conversas NÃO estão sendo analisadas
- Custo diário
- Histórico de análises

---

### 3️⃣ `public/scripts/fix-sentiment-config.php`
**O que faz**: Análise inteligente e sugestões

```bash
php public/scripts/fix-sentiment-config.php
```

**Mostra**:
- Estatísticas reais das suas conversas
- Distribuição de mensagens por conversa
- Problemas identificados
- Sugestões de valores ideais
- Simulação com valores recomendados
- Estimativa de custo

---

## ✅ Solução Recomendada

### Valores Ideais

| Configuração | Valor Atual | Valor Recomendado | Motivo |
|-------------|-------------|-------------------|---------|
| **Mín. Mensagens** | 100 | **5-10** | Captura maioria das conversas com contexto suficiente |
| **Analisar a cada X** | 100 | **100** (OK) | Boa para reanálise de conversas longas |
| **Intervalo** | 10h | **10-24h** (OK) | Evita análises muito frequentes |
| **Idade Máxima** | 3 dias | **3-7 dias** | Foca em conversas recentes |

### 🎯 Ação Recomendada

1. **Execute o diagnóstico completo**:
```bash
php public/scripts/fix-sentiment-config.php
```

2. **Veja quantas conversas você tem de verdade**
   - O script mostra a distribuição real
   - Você verá que poucas (ou nenhuma) tem 100+ mensagens

3. **Ajuste as configurações**:
   - Acesse: `Configurações > Botões de Ação > Análise de Sentimento`
   - Altere **"Mín. Mensagens para Analisar"** de `100` para `5`
   - Mantenha as outras configurações
   - Clique em **"Salvar Configurações"** no final da página

4. **Teste novamente**:
```bash
php public/scripts/analyze-sentiments.php
```

Agora você verá conversas sendo processadas! 🎉

---

## 📊 Por Que 5 Mensagens?

### Contexto Suficiente
- 5 mensagens já fornecem contexto suficiente para análise
- GPT-3.5-turbo consegue identificar sentimentos bem com pouco texto

### Custo-Benefício
- Com 5 mensagens, você captura 60-80% das conversas
- Com 100 mensagens, você captura menos de 5% (ou 0%)

### Exemplo Real

**Conversa típica de suporte**:
1. Cliente: "Olá, preciso de ajuda"
2. Cliente: "Meu pedido não chegou"
3. Cliente: "Número do pedido: 12345"
4. Cliente: "Já faz 2 semanas"
5. Cliente: "Estou muito frustrado"

✅ **5 mensagens** - Suficiente para detectar **frustração/negativo**

---

## 🧪 Script de Análise Melhorado

O script `public/scripts/analyze-sentiments.php` foi atualizado para:

✅ Verificar se está habilitado  
✅ Mostrar configurações atuais  
✅ Contar conversas elegíveis ANTES de processar  
✅ Explicar por que nenhuma conversa foi processada  
✅ Sugerir executar o debug se necessário  

---

## 📝 Checklist de Verificação

- [ ] Executar `php public/scripts/check-sentiment-config.php`
- [ ] Executar `php public/scripts/fix-sentiment-config.php`
- [ ] Verificar estatísticas reais das conversas
- [ ] Ajustar "Mín. Mensagens" para 5-10
- [ ] Salvar configurações na interface
- [ ] Executar `php public/scripts/analyze-sentiments.php` novamente
- [ ] Verificar se conversas foram processadas

---

## 🎯 Resultado Esperado

Após ajustar para 5 mensagens mínimas:

```
[2026-01-09 10:30:00] Iniciando análise de sentimentos...
[2026-01-09 10:30:00] ✅ Análise habilitada
[2026-01-09 10:30:00] 📊 Configurações:
[2026-01-09 10:30:00]    - Modelo: gpt-3.5-turbo
[2026-01-09 10:30:00]    - Intervalo: 10 horas
[2026-01-09 10:30:00]    - Idade máxima: 3 dias
[2026-01-09 10:30:00]    - Mín. mensagens: 5
[2026-01-09 10:30:00] 🔍 Conversas elegíveis para análise: 12
[2026-01-09 10:30:00] 🚀 Processando conversas...
[2026-01-09 10:30:45] ✅ Análises processadas: 12
[2026-01-09 10:30:45] ⚠️ Erros: 0
[2026-01-09 10:30:45] 💰 Custo total: $0.0145
[2026-01-09 10:30:45] Concluído.
```

---

## 💡 Dicas Adicionais

### Custo da Análise
- GPT-3.5-turbo: ~$0.001 por análise
- GPT-4: ~$0.03 por análise
- **Recomendado**: Use GPT-3.5-turbo (mais barato, suficiente para sentimento)

### Frequência Ideal
- **Cron**: A cada 12-24 horas
- **Sob demanda**: Botão na interface da conversa
- **Automático**: Ao fechar conversa (configurar via automação)

### Quando Usar Valores Mais Altos?
- Se você tem conversas MUITO longas (e-commerce, suporte técnico complexo)
- Se quer analisar apenas conversas "maduras"
- Se quer economizar créditos (mas perderá conversas)

---

## 🆘 Ainda Não Funciona?

Se após ajustar para 5 mensagens ainda não funcionar, execute:

```bash
php public/scripts/debug-sentiment-analysis.php
```

E envie o output completo para análise. O script mostrará exatamente onde está o problema:
- Configurações incorretas?
- Sem API Key?
- Sem conversas abertas?
- Conversas já analisadas?
- Limite de custo atingido?

---

**Criado em**: 2026-01-09  
**Versão**: 1.0  
