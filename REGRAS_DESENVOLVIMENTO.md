# 📋 REGRAS DE DESENVOLVIMENTO - GUIA RÁPIDO

**Última atualização**: 2025-01-27

Este documento serve como referência rápida para desenvolvimento no projeto. Para contexto completo, consulte `CONTEXT_IA.md` e `ARQUITETURA.md`.

---

## 🚀 INÍCIO RÁPIDO

### Estrutura Básica de um Módulo

```
1. Migration (database/migrations/XXX_create_table.php)
2. Model (app/Models/ModelName.php)
3. Service (app/Services/ModelNameService.php)
4. Controller (app/Controllers/ModelNameController.php)
5. Views (views/model-name/index.php, show.php)
6. Rotas (routes/web.php)
7. Permissões (database/seeds/002_create_roles_and_permissions.php)
8. Menu (views/layouts/metronic/sidebar.php)
```

---

## 📝 CONVENÇÕES DE CÓDIGO

### PHP
- **Padrão**: PSR-12
- **Namespaces**: `App\` para classes principais
- **Classes**: PascalCase (`UserController`, `ConversationService`)
- **Métodos**: camelCase (`getUser`, `createConversation`)
- **Variáveis**: camelCase (`$userId`, `$conversationData`)

### JavaScript
- **Padrão**: ES6+
- **Classes**: PascalCase para componentes (`ConversationList`, `MessageInput`)
- **Funções**: camelCase (`loadConversations`, `sendMessage`)
- **Variáveis**: camelCase (`const conversationId`, `let messages`)

### Banco de Dados
- **Tabelas**: snake_case (`conversations`, `message_templates`)
- **Colunas**: snake_case (`user_id`, `created_at`)
- **Timestamps**: `created_at`, `updated_at` (obrigatório)
- **Soft deletes**: `deleted_at` quando necessário

### Arquivos
- **Controllers**: `PascalCaseController.php`
- **Models**: `PascalCase.php`
- **Services**: `PascalCaseService.php`
- **Views**: `kebab-case.php`
- **Migrations**: `XXX_descriptive_name.php`

---

## 🏗️ PADRÕES DE ARQUITETURA

### MVC + Service Layer

```
Request → Controller → Service → Model → Database
                ↓
              View
```

**Regra**: Controllers são finos, Services contêm lógica de negócio, Models apenas acesso a dados.

### Exemplo Completo

#### 1. Migration
```php
// database/migrations/029_create_example_table.php
function up_example_table() {
    global $pdo;
    $sql = "CREATE TABLE IF NOT EXISTS examples (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'examples' criada!\n";
}
```

#### 2. Model
```php
// app/Models/Example.php
namespace App\Models;

class Example extends Model
{
    protected string $table = 'examples';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'description'];
    protected bool $timestamps = true;
}
```

#### 3. Service
```php
// app/Services/ExampleService.php
namespace App\Services;

use App\Models\Example;
use App\Helpers\Validator;

class ExampleService
{
    public static function create(array $data): int
    {
        // Validação
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }
        
        // Lógica de negócio aqui
        // ...
        
        // Criar no banco
        return Example::create($data);
    }
    
    public static function update(int $id, array $data): bool
    {
        $example = Example::find($id);
        if (!$example) {
            throw new \InvalidArgumentException('Exemplo não encontrado');
        }
        
        // Validação
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }
        
        // Lógica de negócio aqui
        // ...
        
        return Example::update($id, $data);
    }
}
```

#### 4. Controller
```php
// app/Controllers/ExampleController.php
namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\ExampleService;

class ExampleController
{
    public function index(): void
    {
        Permission::abortIfCannot('examples.view');
        
        try {
            $examples = \App\Models\Example::all();
            Response::view('examples/index', ['examples' => $examples]);
        } catch (\Exception $e) {
            Response::view('examples/index', ['examples' => [], 'error' => $e->getMessage()]);
        }
    }
    
    public function store(): void
    {
        Permission::abortIfCannot('examples.create');
        
        try {
            $data = Request::input();
            $id = ExampleService::create($data);
            Response::json(['success' => true, 'id' => $id], 201);
        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 400);
        }
    }
}
```

#### 5. Rotas
```php
// routes/web.php
Router::get('/examples', [ExampleController::class, 'index'], ['Authentication']);
Router::post('/examples', [ExampleController::class, 'store'], ['Authentication', 'Permission:examples.create']);
Router::get('/examples/{id}', [ExampleController::class, 'show'], ['Authentication']);
Router::put('/examples/{id}', [ExampleController::class, 'update'], ['Authentication', 'Permission:examples.edit']);
Router::delete('/examples/{id}', [ExampleController::class, 'destroy'], ['Authentication', 'Permission:examples.delete']);
```

---

## 🔐 SISTEMA DE PERMISSÕES

### Verificação Obrigatória

**SEMPRE** verificar permissões antes de ações:

```php
// No início dos métodos do Controller
Permission::abortIfCannot('resource.action');

// Ou com verificação condicional
if (!Permission::can('resource.action')) {
    Response::json(['error' => 'Sem permissão'], 403);
}
```

### Níveis Hierárquicos

```
Nível 0: Super Admin (todas as permissões)
├── Nível 1: Admin
│   ├── Nível 2: Supervisor
│   │   ├── Nível 3: Agente Sênior
│   │   │   ├── Nível 4: Agente
│   │   │   └── Nível 5: Agente Júnior
│   │   └── Nível 6: Visualizador
│   └── Nível 7: API User
```

### Padrão de Nomes de Permissões

```
{recurso}.{ação}

Exemplos:
- conversations.view
- conversations.edit
- conversations.delete
- messages.send
- agents.create
- settings.edit
```

### Adicionar Nova Permissão

```php
// database/seeds/002_create_roles_and_permissions.php
Permission::create([
    'name' => 'Visualizar Exemplos',
    'slug' => 'examples.view',
    'module' => 'examples',
    'description' => 'Permite visualizar exemplos'
]);
```

---

## 🗄️ BANCO DE DADOS

### Migrations

**Regras**:
- Nunca alterar migrations existentes
- Criar nova migration para mudanças
- Usar `IF NOT EXISTS` para segurança
- Sempre incluir `created_at` e `updated_at`

### Timestamps

```sql
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

### Foreign Keys

```sql
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
-- ou
FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
```

### Campos JSON

```sql
settings JSON,
metadata JSON,
config JSON
```

### Índices

```sql
INDEX idx_user_id (user_id),
INDEX idx_status (status),
INDEX idx_created_at (created_at)
```

---

## 🎨 FRONTEND

### Layout

```
┌──────────┬──────────────────┬─────────────────┐
│ Sidebar  │ Lista Conversas │  Janela Chat    │
│ (70px)   │    (380px)       │    (flex)       │
└──────────┴──────────────────┴─────────────────┘
```

### Metronic 8

- **NÃO** referenciar arquivos de `/metronic/` diretamente
- Usar sempre `/public/assets/` para assets
- Usar classes e componentes do Metronic
- Documentação: Ver arquivos em `public/assets/`

### Componentes

- Componentes reutilizáveis em `views/components/`
- Incluir com `include __DIR__ . '/../components/component-name.php';`

### JavaScript

```javascript
// Modular, ES6+
class ComponentName {
    constructor(element) {
        this.element = element;
        this.init();
    }
    
    init() {
        // Inicialização
    }
}

// Inicializar quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    new ComponentName(document.querySelector('#element'));
});
```

---

## 🔌 INTEGRAÇÕES

### WebSocket

```php
// Notificar via WebSocket
\App\Helpers\WebSocket::notifyNewMessage($conversationId, $message);
\App\Helpers\WebSocket::notifyConversationUpdated($conversationId, $conversation);
```

### WhatsApp

```php
// Enviar mensagem
\App\Services\WhatsAppService::sendMessage($accountId, $phone, $message);
```

---

## ✅ CHECKLIST DE DESENVOLVIMENTO

### Ao Criar Nova Funcionalidade

- [ ] Migration criada e testada
- [ ] Model criado com fillable e timestamps
- [ ] Service criado com validações
- [ ] Controller criado com verificação de permissões
- [ ] Views criadas (index, show, create/edit se necessário)
- [ ] Rotas adicionadas
- [ ] Permissões criadas e atribuídas
- [ ] Link no menu adicionado
- [ ] Documentação atualizada
- [ ] Testado manualmente

### Ao Modificar Funcionalidade

- [ ] Verificado impacto em outras partes
- [ ] Migration criada se necessário
- [ ] Models/Services/Views atualizados
- [ ] Permissões atualizadas se necessário
- [ ] Documentação atualizada
- [ ] Testado manualmente

---

## 📚 DOCUMENTAÇÃO

### Arquivos Principais

- `CONTEXT_IA.md` - Contexto completo do sistema
- `ARQUITETURA.md` - Arquitetura técnica
- `FUNCIONALIDADES_PENDENTES.md` - Estado atual do projeto
- `SISTEMA_REGRAS_COMPLETO.md` - Regras detalhadas
- `PROGRESSO_*.md` - Progresso de cada módulo
- `NOVAS_FUNCIONALIDADES.md` - Novas funcionalidades planejadas

### Ao Documentar

- Atualizar `FUNCIONALIDADES_PENDENTES.md` quando concluir
- Criar/atualizar `PROGRESSO_*.md` para módulos específicos
- Atualizar `CONTEXT_IA.md` se adicionar novas tabelas/funcionalidades
- Comentar código complexo

---

## 🚨 ERROS COMUNS A EVITAR

1. ❌ Referenciar `/metronic/` diretamente
2. ❌ Esquecer verificação de permissões
3. ❌ Não validar inputs
4. ❌ SQL direto ao invés de Models
5. ❌ Lógica de negócio em Controllers
6. ❌ Esquecer timestamps nas migrations
7. ❌ Não atualizar documentação
8. ❌ Alterar migrations existentes

---

## 💡 DICAS

1. **Sempre** seguir padrões existentes no código
2. **Sempre** verificar código similar antes de criar novo
3. **Sempre** testar manualmente após implementar
4. **Sempre** atualizar documentação
5. **Sempre** verificar permissões
6. **Sempre** validar inputs
7. **Sempre** usar Services para lógica de negócio

---

**Última atualização**: 2025-01-27

