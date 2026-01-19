# 🧙 WIZARD DE CONFIGURAÇÃO - FONTE EXTERNA

## ✨ Fluxo Progressivo em 5 Passos

Este é um **wizard progressivo**: os passos vão aparecendo conforme você avança!

---

## 📋 Passo a Passo Visual

```
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃  PASSO 1: Informações Básicas                               ┃
┃  ✅ SEMPRE VISÍVEL                                          ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
   │
   │  • Nome da Fonte: [Digite um nome]
   │  • Tipo de Banco: [MySQL ▼] [PostgreSQL]
   │
   ▼
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃  PASSO 2: Configuração de Conexão                           ┃
┃  ✅ SEMPRE VISÍVEL                                          ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
   │
   │  • Host:     [localhost]
   │  • Porta:    [3306]
   │  • Banco:    [meu_banco]
   │  • Usuário:  [root]
   │  • Senha:    [••••••]
   │
   │  [🔌 Testar Conexão] ← CLIQUE AQUI
   │
   ▼
   │
   ▼  ⚡ Testando conexão...
   │
   ▼  ✅ Conectado!
   │     💬 "Carregando tabelas do banco externo..."
   │
   ▼
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃  PASSO 3: Selecionar Tabela                                 ┃
┃  ✨ APARECE AUTOMATICAMENTE APÓS CONEXÃO!                   ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
   │
   │  💬 "15 tabela(s) encontrada(s)!"
   │
   │  Tabela: [Selecione uma tabela... ▼]
   │           - clientes
   │           - usuarios
   │           - pedidos
   │           - produtos
   │           - contatos ← SELECIONA ESTA
   │
   ▼
   │
   ▼  ⚡ Carregando colunas da tabela...
   │
   ▼  ✅ "22 coluna(s) encontrada(s)!"
   │
   ▼
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃  PASSO 4: Mapear Colunas                                    ┃
┃  ✨ APARECE AUTOMATICAMENTE APÓS SELECIONAR TABELA!         ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
   │
   │  ⚠️ Mapeie as colunas do banco externo para os campos do sistema
   │
   │  Nome do Contato:  [nome_completo ▼]
   │                     - id
   │                     - nome_completo ← SELECIONADO
   │                     - nome
   │                     - sobrenome
   │
   │  Telefone:         [celular ▼]
   │                     - telefone
   │                     - celular ← SELECIONADO
   │                     - whatsapp
   │
   │  Email (opcional): [email_contato ▼]
   │                     - email
   │                     - email_contato ← SELECIONADO
   │
   │  [👁️ Preview dos Dados] ← CLIQUE PARA VER 10 LINHAS
   │
   │  ┌────────────────────────────────────────────┐
   │  │  Preview dos Dados (primeiras 10 linhas)  │
   │  ├─────────────┬──────────┬─────────────────┤
   │  │ nome_comp.. │ celular  │ email_contato   │
   │  ├─────────────┼──────────┼─────────────────┤
   │  │ João Silva  │ 1199999  │ joao@email.com  │
   │  │ Maria Lima  │ 1188888  │ maria@email.com │
   │  └─────────────┴──────────┴─────────────────┘
   │
   ▼
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃  PASSO 5: Configurar Sincronização                          ┃
┃  ✨ APARECE JUNTO COM O PASSO 4!                            ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
   │
   │  Frequência: [Diariamente ▼]
   │               - Manual (sob demanda)
   │               - A cada hora
   │               - Diariamente ← SELECIONADO
   │               - Semanalmente
   │
   │  Filtro WHERE (opcional):
   │  [status = 'ativo' AND cidade = 'São Paulo']
   │
   │  Ordenação (opcional):
   │  [created_at DESC]
   │
   │  Limite de Registros (opcional):
   │  [1000]
   │
   │  [✅ Criar Fonte] ← SALVAR TUDO
   │
   ▼
   │
   ▼  💾 Salvando...
   │
   ▼  ✅ Fonte criada com sucesso!
   │
   │  ➡️ Redireciona para /external-sources
```

---

## 🎯 Comportamento do Wizard

### ✅ Inicialmente Visível
- ✅ Passo 1 (Informações Básicas)
- ✅ Passo 2 (Configuração de Conexão)

### 🔄 Após Clicar "Testar Conexão"
Se a conexão for bem-sucedida:
1. ✅ Badge verde "Conectado" aparece
2. 💾 Cria uma fonte temporária no banco (invisible para o usuário)
3. ⚡ Busca automaticamente as tabelas do banco externo
4. ✨ **Mostra o Passo 3** (Selecionar Tabela)
5. 📜 Scroll automático até o Passo 3
6. 💬 Mensagem: "X tabela(s) encontrada(s)!"

### 🔄 Após Selecionar uma Tabela
1. ⚡ Busca automaticamente as colunas da tabela
2. ✨ **Mostra os Passos 4 e 5** simultaneamente
3. 📜 Scroll automático até o Passo 4
4. 💬 Mensagem: "X coluna(s) encontrada(s)!"
5. 🔓 Botão "Criar Fonte" é habilitado

### 🔄 Após Clicar "Preview dos Dados"
1. ⚡ Busca as primeiras 10 linhas da tabela
2. 📊 Exibe uma tabela formatada com os dados
3. 💡 Ajuda a validar se o mapeamento está correto

### 🔄 Ao Clicar "Criar Fonte"
1. 🗑️ Deleta a fonte temporária (se existir)
2. 💾 Cria a fonte definitiva com todos os dados
3. ✅ Mensagem de sucesso
4. ➡️ Redireciona para a lista de fontes

---

## 🎨 Feedbacks Visuais

| Ação | Feedback |
|------|----------|
| Clica "Testar Conexão" | 🔵 Spinner no botão + "Conectando..." |
| Conexão OK | 🟢 Badge "Conectado" + Notificação verde |
| Conexão Erro | 🔴 Badge "Erro" + Notificação vermelha |
| Carregando tabelas | 🔵 Notificação: "Carregando tabelas..." |
| Tabelas carregadas | 🟢 Notificação: "15 tabela(s) encontrada(s)!" |
| Carregando colunas | 🔵 Notificação: "Carregando colunas..." |
| Colunas carregadas | 🟢 Notificação: "22 coluna(s) encontrada(s)!" + Scroll |
| Carregando preview | 🔵 Spinner no botão + "Buscando..." |
| Preview OK | 🟢 Tabela formatada aparece |
| Salvando fonte | 🔵 Spinner no botão + "Salvando..." |
| Fonte salva | 🟢 Notificação + Redirecionamento |

---

## 🎬 Exemplo Completo

```
VOCÊ ESTÁ EM: /external-sources/create

1. Digite: "CRM Principal"
2. Selecione: "MySQL"
3. Digite: host=192.168.1.100, porta=3306, banco=meu_crm
4. Clique: [Testar Conexão]
   → ✅ Conectado!
   → Passo 3 aparece magicamente! ✨

5. No dropdown, aparece:
   - clientes
   - usuarios
   - pedidos
   
6. Selecione: "clientes"
   → Passos 4 e 5 aparecem magicamente! ✨

7. Mapeie:
   - Nome: nome_completo
   - Telefone: celular
   - Email: email

8. Configure:
   - Frequência: Diária
   - Filtro: status = 'ativo'

9. Clique: [Criar Fonte]
   → ✅ Sucesso!
   → Vai para /external-sources
```

---

## 🐛 Correções Aplicadas

### Bug 1: Salvar não funcionava
**Problema:** Tentava usar PUT mas o controller só aceita POST  
**Solução:** Sempre usa POST, deletando a fonte temporária antes

### Bug 2: Sem feedback visual
**Problema:** Usuário não sabia se estava carregando  
**Solução:** Adicionados toasts e scrolls automáticos

### Bug 3: Preview sem formatação
**Problema:** Dados apareciam sem estilo  
**Solução:** Tabela formatada com tema Metronic

---

## ✅ Está Pronto!

Agora o wizard funciona perfeitamente:
- ✅ Passos aparecem progressivamente
- ✅ Feedback visual em todas as etapas
- ✅ Scroll automático para o próximo passo
- ✅ Preview formatado dos dados
- ✅ Salvamento correto da fonte

**Teste agora em:** `/external-sources/create`
