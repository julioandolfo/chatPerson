# Sistema Multiatendimento / Multiatendentes / Multicanal

Sistema completo de atendimento multicanal desenvolvido em PHP + MySQL, inspirado no Chatwoot, com funcionalidades avançadas de permissões, funis com Kanban e automações.

## 🚀 Tecnologias

- **Backend**: PHP 8.1+
- **Banco de Dados**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Tema**: Metronic 8 (Demo 3 - Compact Sidebar)
- **WebSocket**: Ratchet ou ReactPHP (tempo real)
- **APIs**: Quepasa API e Evolution API (WhatsApp)

## 📋 Funcionalidades Principais

### ✅ Sistema de Conversas
- Múltiplos canais (WhatsApp inicialmente)
- Lista de conversas com busca e filtros
- Interface de chat em tempo real
- Histórico completo de mensagens
- Anexos e mídia

### ✅ Sistema de Permissões Avançado
- Hierarquia de 7 níveis de acesso
- Permissões granulares por recurso
- Permissões por setor/departamento
- Permissões condicionais (temporais, por status, etc)
- Cache de permissões para performance

### ✅ Funis com Kanban
- Múltiplos funis por inbox
- Estágios customizáveis
- Drag & drop para movimentação
- Auto-atribuição inteligente
- Validações antes de mover
- Métricas por estágio

### ✅ Sistema de Automações
- Triggers diversos (conversa, mensagem, temporal, etc)
- Condições complexas (AND, OR, NOT, XOR)
- Ações múltiplas (mover, atribuir, enviar mensagem, etc)
- Variáveis e templates
- Logs de execução
- Modo de teste

### ✅ Integração WhatsApp
- Suporte a Quepasa API
- Suporte a Evolution API
- Múltiplas contas WhatsApp
- QR Code para conectar
- Envio/recebimento de mensagens
- Status de entrega/leitura

## 📁 Estrutura do Projeto

```
chat/
├── api/                  # API REST
├── app/                  # Lógica da aplicação (MVC)
│   ├── Controllers/      # Controladores
│   ├── Models/          # Modelos
│   ├── Services/        # Serviços de negócio
│   ├── Middleware/       # Middlewares
│   └── Helpers/         # Funções auxiliares
├── config/              # Configurações
├── database/            # Migrações e seeds
├── public/              # Arquivos públicos
│   ├── index.php        # Entry point
│   └── assets/          # CSS, JS, imagens
├── views/               # Templates/Páginas
├── metronic/            # ⚠️ Referência apenas (não usar diretamente)
└── docs/                # Documentação
```

## 🛠️ Instalação

### Pré-requisitos
- PHP 8.1+
- MySQL 8.0+
- Composer (opcional)
- Servidor web (Apache/Nginx) ou Laragon

### Passos

1. **Clone o repositório**:
```bash
git clone [url-do-repositorio]
cd chat
```

2. **Configure o banco de dados**:
- Crie um banco de dados MySQL
- Configure em `config/database.php`

3. **Execute as migrações**:
```bash
php scripts/migrate.php
```

4. **Execute os seeds**:
```bash
php scripts/seed.php
```

5. **Copie arquivos do Metronic**:
```bash
# Veja GUIA_COPIAR_METRONIC.md
php scripts/copy-metronic.php
```

6. **Configure variáveis de ambiente**:
- Copie `.env.example` para `.env`
- Configure as variáveis necessárias

7. **Configure o servidor web**:
- Apache: Configure DocumentRoot para `public/`
- Nginx: Configure root para `public/`
- Laragon: Aponte para a pasta do projeto

## 📚 Documentação

### Documentos Principais

1. **CONTEXT_IA.md** - Contexto completo do sistema para IA
2. **ARQUITETURA.md** - Arquitetura técnica detalhada
3. **SISTEMA_REGRAS_COMPLETO.md** - Regras de permissões, Kanban e automações
4. **LAYOUT_CHATWOOT_METRONIC.md** - Guia de implementação do layout
5. **EXEMPLO_IMPLEMENTACAO.md** - Exemplos práticos de código
6. **GUIA_COPIAR_METRONIC.md** - Como copiar arquivos do Metronic

### Para Desenvolvedores

- **Estrutura MVC**: Veja `ARQUITETURA.md`
- **Sistema de Permissões**: Veja `SISTEMA_REGRAS_COMPLETO.md` seção 1
- **Sistema Kanban**: Veja `SISTEMA_REGRAS_COMPLETO.md` seção 2
- **Sistema de Automações**: Veja `SISTEMA_REGRAS_COMPLETO.md` seção 3
- **Layout Frontend**: Veja `LAYOUT_CHATWOOT_METRONIC.md`

## 🔐 Sistema de Permissões

O sistema possui um sistema avançado de permissões com:

- **7 níveis hierárquicos** (Super Admin até API User)
- **Permissões granulares** por recurso e ação
- **Permissões por setor** com hierarquia
- **Permissões condicionais** (temporais, por status, etc)
- **Cache de permissões** para performance

Veja `SISTEMA_REGRAS_COMPLETO.md` para detalhes completos.

## 📋 Funis e Kanban

Sistema completo de funis com:

- **Múltiplos funis** por inbox
- **Estágios customizáveis** com cores e propriedades
- **Drag & drop** para movimentação
- **Auto-atribuição** inteligente
- **Validações** antes de mover
- **Métricas** por estágio e funil

Veja `SISTEMA_REGRAS_COMPLETO.md` seção 2 para detalhes.

## 🤖 Automações

Sistema avançado de automações com:

- **Múltiplos tipos de triggers** (conversa, mensagem, temporal, etc)
- **Condições complexas** com operadores lógicos
- **Ações diversas** (mover, atribuir, enviar mensagem, etc)
- **Variáveis e templates** para personalização
- **Logs de execução** para debugging

Veja `SISTEMA_REGRAS_COMPLETO.md` seção 3 para detalhes.

## 📱 Integração WhatsApp

Suporte a duas APIs de WhatsApp:

- **Quepasa API**: Integração completa
- **Evolution API**: Integração completa
- **Múltiplas contas**: Gerenciar várias contas
- **QR Code**: Conectar facilmente
- **Webhooks**: Receber mensagens em tempo real

## 🎨 Frontend

Layout inspirado no Chatwoot 4 usando Metronic:

- **3 colunas**: Sidebar + Lista + Chat
- **Responsivo**: Mobile-friendly
- **Tempo real**: WebSocket para atualizações
- **Componentes reutilizáveis**: Código modular

Veja `LAYOUT_CHATWOOT_METRONIC.md` para detalhes.

## 🔄 Fluxos Principais

### Fluxo de Conversa
1. Mensagem recebida via WhatsApp
2. Webhook processa
3. Conversa criada/atualizada
4. Automações verificadas
5. Conversa atribuída
6. Agente notificado
7. Agente responde
8. Mensagem enviada

### Fluxo de Permissões
1. Requisição chega
2. Middleware verifica autenticação
3. Middleware verifica permissões
4. PermissionService valida
5. Cache consultado
6. Acesso concedido/negado

Veja `ARQUITETURA.md` para mais detalhes.

## 🧪 Desenvolvimento

### Estrutura de Código
- **PSR-12** coding standard
- **MVC** pattern
- **Service Layer** para lógica de negócio
- **Middleware** para interceptação

### Convenções
- **PHP**: camelCase para métodos, PascalCase para classes
- **JavaScript**: ES6+, classes para componentes
- **Banco**: snake_case para tabelas e colunas
- **Arquivos**: kebab-case para views

## 📊 Banco de Dados

### Tabelas Principais
- `users`, `roles`, `permissions`
- `departments`, `agents`
- `inboxes`, `whatsapp_accounts`
- `contacts`, `conversations`, `messages`
- `funnels`, `funnel_stages`
- `automations`, `automation_rules`
- `tags`, `activities`

Veja `CONTEXT_IA.md` para estrutura completa.

## 🚀 Deploy

### Checklist
- [ ] Variáveis de ambiente configuradas
- [ ] Banco de dados migrado
- [ ] Assets compilados/minificados
- [ ] Permissões de arquivos corretas
- [ ] SSL/HTTPS configurado
- [ ] Backup automático configurado
- [ ] Monitoramento configurado

Veja `ARQUITETURA.md` seção Deployment para detalhes.

## 📝 Licença

[Especificar licença]

## 👥 Contribuindo

[Instruções de contribuição]

## 📞 Suporte

[Informações de suporte]

---

**Versão**: 2.2
**Última atualização**: 2025-12-05

### 🆕 Novidades Recentes (2025-12-05)
- ✅ Sistema de Reply/Quote de mensagens
- ✅ Encaminhamento de mensagens
- ✅ Gravação de áudio no chat
- ✅ Status detalhado de mensagens (enviado, entregue, lida, erro)
- ✅ Ordenação cronológica correta de mensagens

Para mais informações, consulte a documentação em `docs/` ou os arquivos `.md` na raiz do projeto.

