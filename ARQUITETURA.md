# ARQUITETURA DO SISTEMA - DOCUMENTAÇÃO TÉCNICA

## 📋 VISÃO GERAL

Sistema multiatendimento desenvolvido em PHP vanilla com arquitetura MVC, seguindo padrões de design e boas práticas.

---

## 🏗️ ARQUITETURA DE CAMADAS

### Camada de Apresentação (Views)
- **Localização**: `views/`
- **Responsabilidade**: Renderizar HTML, receber inputs do usuário
- **Tecnologia**: PHP templates, HTML5, CSS3, JavaScript

### Camada de Controle (Controllers)
- **Localização**: `app/Controllers/`
- **Responsabilidade**: Orquestrar requisições, validar inputs, chamar services
- **Padrão**: Thin controllers (lógica em Services)

### Camada de Serviço (Services)
- **Localização**: `app/Services/`
- **Responsabilidade**: Lógica de negócio, regras de validação
- **Padrão**: Business logic isolada

### Camada de Dados (Models)
- **Localização**: `app/Models/`
- **Responsabilidade**: Acesso a dados, queries, relacionamentos
- **Padrão**: Active Record ou Data Mapper

### Camada de Infraestrutura
- **Config**: `config/`
- **Database**: Migrations e seeds
- **Helpers**: Funções auxiliares
- **Middleware**: Interceptadores de requisições

---

## 🔄 FLUXO DE REQUISIÇÃO

```
1. Requisição HTTP
   ↓
2. public/index.php (Entry Point)
   ↓
3. Router (roteamento)
   ↓
4. Middleware (autenticação, CORS, etc)
   ↓
5. Controller
   ↓
6. Service (lógica de negócio)
   ↓
7. Model (acesso a dados)
   ↓
8. Database
   ↓
9. Response (JSON ou View)
```

---

## 📁 ESTRUTURA DETALHADA

### `/api/` - API REST
```
api/
├── v1/                    # Versão 1 da API
│   ├── auth.php           # Autenticação
│   ├── conversations.php  # Conversas
│   ├── messages.php      # Mensagens
│   └── ...
└── middleware/            # Middlewares da API
    ├── auth.php          # Autenticação JWT
    └── cors.php          # CORS
```

### `/app/` - Lógica da Aplicação

#### Controllers
```
app/Controllers/
├── AuthController.php
├── ConversationController.php
├── MessageController.php
├── ContactController.php
├── AgentController.php
├── FunnelController.php
├── AutomationController.php
└── ...
```

**Padrão de Controller**:
```php
class ConversationController {
    public function index() {
        // Listar conversas
    }
    
    public function show($id) {
        // Mostrar conversa específica
    }
    
    public function store() {
        // Criar nova conversa
    }
    
    public function update($id) {
        // Atualizar conversa
    }
    
    public function destroy($id) {
        // Deletar conversa
    }
}
```

#### Models
```
app/Models/
├── User.php
├── Agent.php
├── Conversation.php
├── Message.php
├── Contact.php
├── Funnel.php
├── Automation.php
└── ...
```

**Padrão de Model**:
```php
class Conversation extends Model {
    protected $table = 'conversations';
    
    public function contact() {
        return $this->belongsTo(Contact::class);
    }
    
    public function agent() {
        return $this->belongsTo(Agent::class);
    }
    
    public function messages() {
        return $this->hasMany(Message::class);
    }
}
```

#### Services
```
app/Services/
├── AuthService.php
├── ConversationService.php
├── MessageService.php
├── PermissionService.php
├── FunnelService.php
├── AutomationService.php
├── WhatsAppService.php
│   ├── QuepasaService.php
│   └── EvolutionService.php
└── ...
```

**Padrão de Service**:
```php
class ConversationService {
    public function createConversation($data) {
        // Validações
        // Regras de negócio
        // Criar conversa
        // Executar automações
        // Retornar resultado
    }
    
    public function assignConversation($conversationId, $agentId) {
        // Verificar permissões
        // Atribuir conversa
        // Notificar agente
        // Registrar atividade
    }
}
```

#### Middleware
```
app/Middleware/
├── Authentication.php
├── Authorization.php
├── PermissionCheck.php
└── RateLimit.php
```

**Padrão de Middleware**:
```php
class Authentication {
    public function handle($request, $next) {
        if (!auth()->check()) {
            return redirect('/login');
        }
        return $next($request);
    }
}
```

### `/config/` - Configurações
```
config/
├── database.php           # Config do banco
├── app.php               # Config geral
├── permissions.php       # Config de permissões
├── whatsapp.php          # Config WhatsApp
└── automations.php       # Config automações
```

### `/database/` - Banco de Dados
```
database/
├── migrations/           # Migrações
│   ├── 001_create_users_table.php
│   ├── 002_create_roles_table.php
│   └── ...
└── seeds/                # Seeds
    ├── DefaultRolesSeeder.php
    └── ...
```

### `/public/` - Arquivos Públicos
```
public/
├── index.php             # Entry point
├── websocket.php        # Servidor WebSocket
├── whatsapp-webhook.php # Webhook WhatsApp
└── assets/              # Assets estáticos
    ├── css/
    ├── js/
    ├── plugins/
    └── media/
```

### `/views/` - Templates
```
views/
├── layouts/              # Layouts base
│   └── metronic/
│       ├── chatwoot-layout.php
│       ├── header.php
│       └── sidebar.php
├── conversations/        # Páginas de conversas
├── contacts/            # Páginas de contatos
└── components/          # Componentes reutilizáveis
```

---

## 🔐 SISTEMA DE AUTENTICAÇÃO

### Fluxo de Autenticação
1. Usuário faz login
2. Sistema valida credenciais
3. Gera token JWT (para API) ou sessão (para web)
4. Token/sessão armazenado
5. Middleware verifica em requisições subsequentes

### Implementação
- **Web**: Sessões PHP
- **API**: JWT (JSON Web Tokens)
- **Middleware**: `Authentication.php`

---

## 🔒 SISTEMA DE PERMISSÕES

### Arquitetura
```
User → Role → Permissions → Resources
```

### Componentes
1. **PermissionService**: Valida permissões
2. **PermissionCheck Middleware**: Verifica antes de ações
3. **Cache**: Redis para performance
4. **Hierarquia**: Herança de permissões

### Fluxo
1. Requisição chega
2. Middleware verifica autenticação
3. Middleware verifica permissões
4. PermissionService valida acesso
5. Cache consultado primeiro
6. Acesso concedido/negado

---

## 💬 SISTEMA DE MENSAGENS

### Fluxo de Mensagem
1. Mensagem recebida (WhatsApp webhook)
2. WebhookController recebe
3. MessageService processa
4. Cria/atualiza conversa
5. Verifica automações
6. Atribui conversa
7. Notifica agente (WebSocket)
8. Agente responde
9. MessageService envia via API WhatsApp

### Componentes
- **MessageService**: Lógica de mensagens
- **WhatsAppService**: Integração WhatsApp
- **WebSocket**: Tempo real
- **Queue**: Processamento assíncrono

---

## 📋 SISTEMA DE FUNIS E KANBAN

### Arquitetura
```
Funnel → Stages → Conversations
```

### Componentes
1. **FunnelService**: Gerenciar funis
2. **FunnelStage Model**: Estágios
3. **Frontend Kanban**: Drag & drop
4. **Validações**: Antes de mover

### Fluxo de Movimentação
1. Agente arrasta conversa
2. Frontend envia requisição
3. FunnelService valida
4. PermissionService verifica permissões
5. Conversa é movida
6. Automações do estágio executadas
7. Histórico registrado
8. WebSocket notifica outros usuários

---

## 🤖 SISTEMA DE AUTOMAÇÕES

### Arquitetura
```
Trigger → Conditions → Actions
```

### Componentes
1. **AutomationService**: Executar automações
2. **AutomationRule Model**: Regras
3. **AutomationLog**: Logs de execução
4. **Queue**: Processamento assíncrono

### Fluxo de Execução
1. Evento ocorre (trigger)
2. AutomationService verifica automações ativas
3. Avalia condições
4. Se verdadeiro, executa ações
5. Registra logs
6. Notifica (se necessário)

---

## 📱 INTEGRAÇÃO WHATSAPP

### Arquitetura
```
WhatsApp API → Webhook → MessageService → Database
```

### Componentes
1. **WhatsAppService**: Interface comum
2. **QuepasaService**: Implementação Quepasa
3. **EvolutionService**: Implementação Evolution
4. **WebhookController**: Receber mensagens

### Fluxo
1. Mensagem recebida via webhook
2. WebhookController processa
3. Identifica provider (Quepasa/Evolution)
4. WhatsAppService processa
5. Cria/atualiza conversa
6. Executa automações
7. Notifica agente

---

## 🔄 PROCESSAMENTO ASSÍNCRONO

### Queue System
- **Jobs**: `app/Jobs/`
- **Queue**: Redis ou Database
- **Workers**: Processar jobs em background

### Jobs Principais
- `ProcessAutomationJob`: Processar automações
- `SendWhatsAppJob`: Enviar mensagens WhatsApp
- `SyncWhatsAppJob`: Sincronizar WhatsApp
- `SendNotificationJob`: Enviar notificações

---

## 🌐 WEBSOCKET (TEMPO REAL)

### Implementação
- **Servidor**: Ratchet ou ReactPHP ✅
- **Cliente**: JavaScript WebSocket API ✅
- **Eventos**: Novas mensagens, conversas, notificações ✅

### Eventos Principais
- `new_message`: Nova mensagem
- `conversation_updated`: Conversa atualizada
- `conversation_assigned`: Conversa atribuída
- `agent_status`: Status do agente mudou

---

## ⚙️ CONFIGURAÇÕES AVANÇADAS DE CONVERSAS

### Arquitetura
```
Settings (JSON) → ConversationService → Distribution Logic
```

### Componentes
1. **SettingService**: Gerenciar configurações
2. **ConversationDistributionService**: Lógica de distribuição
3. **SLA Service**: Cálculo e monitoramento de SLA
4. **ReassignmentService**: Lógica de reatribuição

### Configurações Principais
- Limites por agente/setor/funil
- SLA de resposta e resolução
- Métodos de distribuição (round-robin, por carga, etc)
- Distribuição percentual
- Regras de reatribuição
- Priorização e filas

### Fluxo de Distribuição
1. Nova conversa criada
2. Verifica configurações de distribuição
3. Seleciona método (IA ou humano)
4. Aplica regras de distribuição
5. Atribui conversa
6. Monitora SLA
7. Reatribui se necessário

---

## 🤖 SISTEMA DE AGENTES DE IA

### Arquitetura
```
Conversation → AIAgent → OpenAI API → Tools → Response
```

### Componentes
1. **AIAgentService**: Gerenciar agentes de IA
2. **AIToolService**: Gerenciar tools
3. **OpenAIService**: Integração com OpenAI
4. **ToolExecutor**: Executar tools chamadas pela IA

### Fluxo de Processamento
1. Conversa atribuída a Agente de IA
2. Busca contexto (mensagens, contato)
3. Monta prompt com instruções e tools
4. Chama OpenAI API com function calling
5. Executa tools chamadas
6. Reenvia para OpenAI com resultados
7. Envia resposta final
8. Registra logs (tokens, custo, tools)

### Tipos de Tools
- **WooCommerce**: Buscar pedidos, produtos, criar pedidos
- **Database**: Consultas SQL seguras
- **N8N**: Executar workflows via webhook
- **Documents**: Buscar e extrair texto de documentos
- **System**: Ações internas (tags, estágios, escalação)
- **API**: Chamadas genéricas a APIs externas

---

## 🗄️ BANCO DE DADOS

### Estratégia
- **Migrations**: Versionamento do schema
- **Seeds**: Dados iniciais
- **ORM**: Active Record pattern
- **Queries**: Prepared statements

### Relacionamentos Principais
- User → Agent (1:1)
- Agent → Departments (N:N)
- Conversation → Contact (N:1)
- Conversation → Agent (N:1) ou AIAgent (N:1)
- Conversation → Funnel (N:1)
- Conversation → Messages (1:N)
- Conversation → Tags (N:N)
- Funnel → Stages (1:N)
- AIAgent → AITools (N:N)
- Conversation → AIConversations (1:N) - Logs de IA

---

## 🎨 FRONTEND

### Arquitetura
- **Layout Base**: Metronic (Chatwoot-like)
- **Componentes**: Reutilizáveis
- **JavaScript**: Modular, ES6+
- **CSS**: Metronic + Custom

### Estrutura
```
public/assets/
├── css/
│   ├── metronic/        # Metronic CSS
│   └── custom/          # CSS customizado
├── js/
│   ├── metronic/        # Metronic JS
│   └── custom/          # JS customizado
└── media/               # Imagens, ícones
```

---

## 🔧 PADRÕES DE DESIGN

### MVC (Model-View-Controller)
- **Model**: Dados e lógica de dados
- **View**: Apresentação
- **Controller**: Orquestração

### Service Layer
- Lógica de negócio isolada
- Controllers finos
- Services reutilizáveis

### Repository Pattern (Opcional)
- Abstração de acesso a dados
- Facilita testes
- Flexibilidade

### Factory Pattern
- Criar objetos complexos
- WhatsAppService factory

### Observer Pattern
- Eventos e listeners
- Automações como observers

---

## 📊 PERFORMANCE

### Otimizações
- **Cache**: Redis para dados frequentes
- **Índices**: Banco de dados otimizado
- **Paginação**: Listagens paginadas
- **Lazy Loading**: Carregar sob demanda
- **CDN**: Assets estáticos (produção)

### Monitoramento
- Logs estruturados
- Métricas de performance
- Alertas de erro

---

## 🔒 SEGURANÇA

### Medidas
- **Validação**: Inputs validados
- **Sanitização**: Outputs sanitizados
- **Prepared Statements**: SQL injection prevention
- **CSRF Protection**: Tokens CSRF
- **XSS Protection**: Escaping outputs
- **Rate Limiting**: Limitar requisições
- **HTTPS**: Em produção

---

## 🧪 TESTES

### Estratégia
- **Unit Tests**: Models, Services
- **Integration Tests**: Controllers, APIs
- **E2E Tests**: Fluxos completos

### Ferramentas
- PHPUnit (PHP)
- Jest (JavaScript - se necessário)

---

## 📚 DOCUMENTAÇÃO

### Arquivos
- `CONTEXT_IA.md`: Contexto para IA
- `ARQUITETURA.md`: Este arquivo
- `SISTEMA_REGRAS_COMPLETO.md`: Regras detalhadas
- `LAYOUT_CHATWOOT_METRONIC.md`: Layout frontend
- `EXEMPLO_IMPLEMENTACAO.md`: Exemplos de código

---

## 🚀 DEPLOYMENT

### Ambiente de Produção
- **Servidor**: Linux (recomendado)
- **PHP**: 8.1+
- **MySQL**: 8.0+
- **Web Server**: Nginx ou Apache
- **Process Manager**: Supervisor (para workers)
- **Cache**: Redis
- **Queue**: Redis ou Database

### Checklist
- [ ] Variáveis de ambiente configuradas
- [ ] Banco de dados migrado
- [ ] Assets compilados/minificados
- [ ] Permissões de arquivos corretas
- [ ] SSL/HTTPS configurado
- [ ] Backup automático configurado
- [ ] Monitoramento configurado

---

**Última atualização**: 2025-01-27
**Versão**: 2.0

