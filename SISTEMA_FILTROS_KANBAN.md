# SISTEMA DE FILTROS AVANÇADOS DO KANBAN

## 📋 Visão Geral

Sistema completo de filtros avançados para a visualização Kanban, permitindo busca e filtragem de conversas em tempo real com múltiplos critérios.

---

## 🎯 Funcionalidades Implementadas

### 1. **Filtros Disponíveis**

#### 1.1 Busca Textual
- **Campo**: Buscar por Nome/Telefone
- **Funcionalidade**: Busca em tempo real (300ms debounce)
- **Pesquisa em**: 
  - Nome do contato
  - Telefone do contato
  - Conteúdo da última mensagem
- **Atalho**: `Ctrl+F`

#### 1.2 Filtro por Agente
- **Opções**:
  - Todos os agentes
  - Não atribuídas
  - Agentes específicos
- **Seleção**: Dropdown com Select2

#### 1.3 Filtro por Status
- **Opções**:
  - Abertas
  - Pendentes
  - Resolvidas
  - Fechadas

#### 1.4 Filtro por Prioridade
- **Opções**:
  - Baixa
  - Normal
  - Alta
  - Urgente

#### 1.5 Filtro por Tags
- **Funcionalidade**: Multi-seleção de tags
- **Comportamento**: Mostra conversas que têm TODAS as tags selecionadas

#### 1.6 Filtro por SLA
- **Opções**:
  - Dentro do prazo
  - Próximo do vencimento
  - Vencido

#### 1.7 Filtro por Período de Criação
- **Opções**:
  - Hoje
  - Ontem
  - Últimos 7 dias
  - Últimos 30 dias
  - Últimos 90 dias

#### 1.8 Filtro por Mensagens Não Lidas
- **Opções**:
  - Todas
  - Com não lidas
  - Sem não lidas

---

## ⚡ Funcionalidades Avançadas

### 2. **Salvar e Carregar Filtros**

#### 2.1 Salvar Filtros
- **Funcionalidade**: Salva a configuração atual de filtros com um nome personalizado
- **Armazenamento**: LocalStorage do navegador
- **Organização**: Por funil (filtros são específicos para cada funil)
- **Atalho**: `Ctrl+S`

#### 2.2 Carregar Filtros Salvos
- **Funcionalidade**: Carrega rapidamente filtros previamente salvos
- **Interface**: Dropdown com lista de filtros salvos
- **Ações**: 
  - Carregar filtro específico
  - Deletar filtro individual
  - Deletar todos os filtros

#### 2.3 Gerenciar Filtros Salvos
- **Deletar Individual**: Botão ao lado de cada filtro salvo
- **Deletar Todos**: Opção no final da lista
- **Confirmação**: Dialog de confirmação antes de deletar

---

### 3. **Exportar Conversas Filtradas**

#### 3.1 Exportação para CSV
- **Funcionalidade**: Exporta apenas as conversas visíveis após aplicar filtros
- **Formato**: CSV (compatível com Excel)
- **Codificação**: UTF-8 com BOM (suporta acentuação)
- **Atalho**: `Ctrl+E`

#### 3.2 Dados Exportados
- ID da conversa
- Nome do contato
- Telefone
- Agente atribuído
- Status
- Prioridade
- Status SLA
- Mensagens não lidas
- Data de criação

#### 3.3 Nome do Arquivo
- **Formato**: `conversas_kanban_YYYY-MM-DD-HH-MM-SS.csv`
- **Exemplo**: `conversas_kanban_2025-01-18-14-30-45.csv`

---

## ⌨️ Atalhos de Teclado

| Atalho | Ação | Descrição |
|--------|------|-----------|
| `Ctrl+F` | Buscar | Abre o painel de filtros e foca no campo de busca |
| `Ctrl+Enter` | Aplicar | Aplica os filtros (quando em campos de filtro) |
| `Esc` | Limpar | Limpa os filtros (quando em campos de filtro) |
| `Ctrl+S` | Salvar | Salva a configuração atual de filtros |
| `Ctrl+E` | Exportar | Exporta conversas filtradas para CSV |

**Nota**: Em Mac, usar `Cmd` ao invés de `Ctrl`

---

## 🎨 Interface do Usuário

### 4. **Painel de Filtros**

#### 4.1 Localização
- **Posição**: Logo abaixo do cabeçalho do card Kanban
- **Visibilidade**: Recolhível (collapse)
- **Botão**: "Filtros" com badge mostrando quantidade de filtros ativos

#### 4.2 Layout
- **Grid**: 4 colunas responsivas
- **Linhas**: 2 linhas de filtros
- **Espaçamento**: Gap de 5 (spacing do Bootstrap)
- **Background**: Gradiente sutil (cinza claro para branco)

#### 4.3 Indicadores Visuais

**Badge de Filtros Ativos**
- **Localização**: Ao lado do botão "Filtros"
- **Cor**: Primary (azul)
- **Animação**: Pulse suave
- **Conteúdo**: Número de filtros ativos

**Contador de Resultados**
- **Localização**: Abaixo dos filtros, à esquerda
- **Formato**: "X conversas encontradas (Y ocultas pelos filtros)"
- **Atualização**: Em tempo real ao aplicar filtros

**Feedback Visual ao Filtrar**
- **Loading**: Overlay com mensagem "Aplicando filtros..."
- **Duração**: 100ms (suficiente para feedback)
- **Notificação**: Toast com número de conversas encontradas

---

## 📊 Contadores Dinâmicos

### 5. **Atualização de Contadores**

#### 5.1 Contadores por Etapa
- **Localização**: Badge no cabeçalho de cada etapa
- **Atualização**: Automática ao aplicar/limpar filtros
- **Formato**: Número de conversas visíveis na etapa

#### 5.2 Contador Global
- **Localização**: Texto abaixo do painel de filtros
- **Informações**:
  - Total de conversas visíveis
  - Total de conversas ocultas
  - Mensagem quando não há filtros ativos

---

## 🔧 Aspectos Técnicos

### 6. **Implementação**

#### 6.1 Armazenamento de Dados
```javascript
// Estrutura de dados das conversas
{
    id: string,
    name: string,
    phone: string,
    message: string,
    agentId: string,
    agentName: string,
    status: string,
    priority: string,
    sla: string,
    unread: number,
    tags: array,
    created_at: string,
    element: HTMLElement
}
```

#### 6.2 Data Attributes
Cada card de conversa possui atributos para facilitar filtragem:
- `data-conversation-id`
- `data-contact-name`
- `data-contact-phone`
- `data-agent-id`
- `data-agent-name`
- `data-status`
- `data-priority`
- `data-sla-status`
- `data-unread-count`
- `data-created-at`
- `data-tags` (JSON)

#### 6.3 Performance
- **Debounce**: 300ms no campo de busca
- **Cache**: Conversas armazenadas em memória ao carregar
- **Filtro**: JavaScript puro (sem requisições ao servidor)
- **Otimização**: Display CSS para ocultar/mostrar (rápido)

#### 6.4 Compatibilidade
- **Navegadores**: Chrome, Firefox, Safari, Edge (versões modernas)
- **Mobile**: Responsivo (layout ajusta para telas menores)
- **Select2**: Integrado para dropdowns avançados
- **Bootstrap 5**: Collapse, modals, tooltips

---

## 📱 Responsividade

### 7. **Adaptação Mobile**

#### 7.1 Layout Mobile
- **Colunas**: Stack vertical (1 coluna)
- **Filtros**: Mantém funcionalidade completa
- **Botões**: Ajustados para toque
- **Atalhos**: Funcionam em teclados físicos

#### 7.2 Touch Friendly
- **Botões**: Tamanho mínimo de 44x44px
- **Espaçamento**: Aumentado para facilitar toque
- **Dropdowns**: Native no mobile (melhor UX)

---

## 🎯 Casos de Uso

### 8. **Exemplos Práticos**

#### 8.1 Encontrar Conversas Urgentes Não Atribuídas
1. Abrir filtros (`Ctrl+F`)
2. Selecionar Agente: "Não atribuídas"
3. Selecionar Prioridade: "Urgente"
4. Aplicar (`Ctrl+Enter`)

#### 8.2 Exportar Conversas Vencidas do Último Mês
1. Selecionar SLA: "Vencido"
2. Selecionar Período: "Últimos 30 dias"
3. Aplicar filtros
4. Exportar (`Ctrl+E`)

#### 8.3 Criar Filtro Rápido para Vendas VIP
1. Configurar filtros:
   - Tags: "VIP", "Vendas"
   - Status: "Abertas"
2. Salvar filtro (`Ctrl+S`)
3. Nomear: "Vendas VIP Abertas"
4. Usar dropdown "Carregar" quando precisar

---

## 🔄 Fluxo de Trabalho

### 9. **Workflow Típico**

```
1. Usuário clica em "Filtros" (ou Ctrl+F)
   ↓
2. Painel de filtros se expande
   ↓
3. Usuário configura critérios desejados
   ↓
4. Sistema aplica filtros em tempo real (busca) ou ao clicar "Aplicar"
   ↓
5. Conversas que não atendem critérios são ocultadas (display: none)
   ↓
6. Contadores são atualizados dinamicamente
   ↓
7. Usuário pode:
   - Salvar filtros (Ctrl+S)
   - Exportar resultados (Ctrl+E)
   - Limpar filtros (Esc ou botão)
   - Ajustar e reaplicar
```

---

## 🐛 Tratamento de Erros

### 10. **Validações e Feedback**

#### 10.1 Validações
- Verificar se há filtros ativos antes de salvar
- Verificar se há conversas visíveis antes de exportar
- Nome obrigatório ao salvar filtro
- Confirmação antes de deletar filtros

#### 10.2 Mensagens de Feedback
- **Sucesso**: Toast verde (2s)
- **Aviso**: SweetAlert amarelo
- **Erro**: SweetAlert vermelho
- **Info**: Toast azul (2s)

---

## 🚀 Melhorias Futuras Possíveis

### 11. **Funcionalidades Adicionais**

- [ ] Filtros por campos customizados
- [ ] Filtros por canal de origem
- [ ] Filtros por departamento
- [ ] Filtro por tempo médio de resposta
- [ ] Compartilhar filtros com equipe
- [ ] Filtros pré-configurados (templates)
- [ ] Filtros avançados com operadores complexos (AND/OR)
- [ ] Histórico de filtros aplicados
- [ ] Sugestões inteligentes de filtros
- [ ] Filtros baseados em IA

---

## 📚 Referências

### 12. **Arquivos Modificados**

- **View**: `views/funnels/kanban.php` (principal)
- **Controller**: Não foi necessário modificar (filtros no frontend)
- **Service**: Não foi necessário modificar

### 13. **Bibliotecas Utilizadas**

- **Bootstrap 5**: Layout e componentes
- **Select2**: Dropdowns avançados
- **SweetAlert2**: Dialogs e confirmações
- **Toastr**: Notificações toast (opcional)
- **jQuery**: Para integração com Select2

---

## 📝 Notas Importantes

### 14. **Considerações**

1. **LocalStorage**: Filtros salvos ficam no navegador do usuário
   - Não sincroniza entre dispositivos
   - Não é compartilhado com outros usuários
   - Persiste mesmo após fechar o navegador

2. **Performance**: 
   - Filtragem é client-side (rápida)
   - Não há requisições ao servidor ao filtrar
   - Ideal para até ~500 conversas simultâneas

3. **Dados**: 
   - Conversas são carregadas uma vez ao abrir o Kanban
   - Filtros são aplicados sobre os dados já carregados
   - Refresh da página recarrega todas as conversas

4. **Permissões**: 
   - Filtros respeitam permissões do usuário
   - Conversas sem permissão não aparecem no Kanban
   - Exportação inclui apenas conversas visíveis

---

## ✅ Checklist de Implementação

- [x] Painel de filtros colapsável
- [x] 8 tipos de filtros diferentes
- [x] Busca em tempo real com debounce
- [x] Aplicação de filtros com feedback visual
- [x] Contadores dinâmicos por etapa
- [x] Badge de filtros ativos com animação
- [x] Salvar filtros no LocalStorage
- [x] Carregar filtros salvos
- [x] Gerenciar (deletar) filtros salvos
- [x] Exportar para CSV
- [x] Atalhos de teclado (5 atalhos)
- [x] Guia visual de atalhos
- [x] Responsividade mobile
- [x] Feedback visual ao filtrar
- [x] Notificações de sucesso/erro
- [x] Confirmações antes de deletar
- [x] Data attributes para filtros
- [x] CSS customizado para filtros
- [x] Documentação completa

---

## 📞 Suporte

Para dúvidas ou sugestões sobre o sistema de filtros:
- Consulte este documento
- Verifique o código em `views/funnels/kanban.php`
- Teste os atalhos de teclado para produtividade

---

**Última atualização**: 2025-01-18
**Versão**: 1.0.0
**Status**: ✅ Implementado e Funcional
