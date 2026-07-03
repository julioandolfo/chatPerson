# Plano: Redesign do Menu — Estilo Flutuante (Desktop + Mobile)

**Data:** 03/07/2026
**Branch:** `claude/floating-menu-redesign-nwjhmi`
**Status:** 📋 Plano aprovado para implementação

---

## 1. Diagnóstico do Menu Atual

### Arquivos envolvidos hoje

| Arquivo | Papel | Problema |
|---|---|---|
| `views/layouts/metronic/sidebar.php` | Aside fixo à esquerda (~770 linhas de HTML hardcoded) | Menu inteiro escrito à mão, sem fonte única de dados |
| `views/layouts/metronic/app.php` (linhas 27-123) | CSS inline com `--aside-width: 290px` + `body { padding-left }` | Layout inteiro depende de deslocar o `body`; cheio de `!important` |
| `public/assets/css/custom/sidebar-toggle.css` | Estado minimizado (70px) + ajustes de wrapper/header | **Inconsistência**: `app.php` define collapsed = `80px`, este CSS usa `70px` |
| `views/layouts/metronic/header.php` | Toggle mobile (`#kt_aside_toggle`) que abre o drawer Metronic | Drawer mobile é a mesma lista gigante com scroll |
| `app.php` (linhas 264-327) | JS de toggle com `localStorage('sidebar_collapsed')` | Lógica manual paralela ao Metronic |

### Problemas identificados

1. **Excesso de itens**: ~18 itens de primeiro nível, com accordions profundos (Campanhas tem **12 subitens**, Integrações tem 10, Performance tem 10+). O menu não cabe na tela → scroll interno (`hover-scroll-overlay-y`).
2. **Encostado à esquerda, ocupando 290px fixos**: o conteúdo é deslocado via `body { padding-left: 290px }`, desperdiçando espaço em telas comuns (1366px sobra ~1076px úteis).
3. **Modo minimizado frágil**: tooltips via `::after` no hover (não funciona em touch), submenus simplesmente somem (`display: none`), e há divergência 70px vs 80px entre CSS e variável.
4. **Mobile ruim**: o mesmo aside vira drawer lateral de 200-250px com a lista completa (18 itens + accordions) — navegação exige abrir drawer, rolar, expandir accordion, tocar. Nenhum acesso rápido às telas principais (Conversas, Dashboard).
5. **CSS de guerra**: `sidebar-toggle.css` tem ~490 linhas só de correções com `!important` para compensar interações aside/header/wrapper/sidebar-direito. Muito frágil.
6. **Sem hierarquia de frequência**: itens usados o dia inteiro (Conversas) têm o mesmo peso visual de itens raros (Logs do Sistema, API & Tokens).

---

## 2. Proposta: Menu Flutuante

### Conceito visual

**Desktop** — *Floating Rail* (barra vertical flutuante, destacada das bordas):

```
┌─────────────────────────────────────────────────┐
│                                        header    │
│  ╭────╮                                          │
│  │ 🏠 │   ← rail flutuante:                      │
│  │ 💬 │     - largura ~68px, só ícones           │
│  │ 👤 │     - border-radius 16px, sombra,        │
│  │ 📊 │       margem de 12px da borda            │
│  │ 🚀 │     - centralizada verticalmente         │
│  │ ⚙️  │     - SEM scroll (máx. 8 ícones)         │
│  │ ⋯  │     - grupos abrem FLYOUT ao lado        │
│  ╰────╯       (popover, não accordion)           │
│         conteúdo ocupa quase toda a largura      │
└─────────────────────────────────────────────────┘
```

- Grupos (Campanhas, Performance, Integrações...) abrem **painel flyout** ancorado à direita do ícone (hover no desktop, clique também funciona). O flyout lista os subitens em 1-2 colunas — **elimina accordion e scroll**.
- Item ativo com indicador (pílula/cor primária). Tooltip nativo do flyout mostra o nome do grupo.
- Botão `⋯` (Mais) agrupa itens administrativos raros: Permissões, Analytics, Configurações, Tags, Templates.
- O conteúdo passa a usar `padding-left: ~92px` (rail 68px + margens), ganhando ~200px de largura útil vs. hoje.

**Mobile** — *Bottom Dock flutuante + Bottom Sheet*:

```
┌──────────────────────┐
│      conteúdo        │
│                      │
│                      │
│ ╭──────────────────╮ │
│ │ 🏠  💬  👤  📊  ☰ │ │  ← dock flutuante inferior
│ ╰──────────────────╯ │    (4 destinos + "menu")
└──────────────────────┘
```

- Dock fixo flutuante no rodapé (border-radius, sombra, margem 12px, respeitando `env(safe-area-inset-bottom)` do iOS).
- 4 destinos principais de acesso direto: **Dashboard, Conversas, Contatos, Funis** (ordem configurável depois).
- Botão **☰ Menu** abre um **bottom sheet** com o menu completo em **grade de ícones agrupada por seção** (padrão de apps modernos) — sem drawer lateral, sem accordion, com busca no topo.
- Touch targets ≥ 44px.

### Por que essa arquitetura resolve os problemas

| Problema atual | Solução |
|---|---|
| Scroll no menu com muitas opções | Rail com máx. ~8 ícones; subitens em flyout; excedente no "Mais" |
| 290px fixos encostados à esquerda | Rail flutuante de 68px destacada das bordas |
| Accordions escondendo tudo | Flyouts de 1 nível — tudo visível em 1 hover/clique |
| Mobile ruim | Dock inferior (padrão de app) + bottom sheet em grade |
| HTML duplicado/hardcoded | Config PHP única renderiza rail, flyouts e bottom sheet |
| CSS frágil com `!important` | Novo CSS isolado; remoção do deslocamento de `body` |

---

## 3. Arquitetura Técnica

### 3.1 Fonte única do menu — `views/layouts/metronic/menu-config.php`

Array PHP declarativo (única fonte de verdade), preservando **todas** as permissões atuais:

```php
return [
    ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'ki-home', 'dock' => true,
     'children' => [
         ['label' => 'Geral', 'route' => '/dashboard'],
         ['label' => 'Inteligência Artificial', 'route' => '/dashboard/ai'],
     ]],
    ['id' => 'conversations', 'label' => 'Conversas', 'icon' => 'ki-chat',
     'route' => '/conversations', 'dock' => true],
    ['id' => 'contacts', 'label' => 'Contatos', 'icon' => 'ki-profile-user',
     'route' => '/contacts', 'dock' => true],
    ['id' => 'funnels', 'label' => 'Funis', 'icon' => 'ki-category', 'dock' => true,
     'children' => [ /* Todos os Funis, Kanban */ ]],
    ['id' => 'campaigns', 'label' => 'Campanhas', 'icon' => 'ki-send',
     'permission' => 'campaigns.view',
     'children' => [ /* 12 subitens atuais, com separadores por seção */ ]],
    ['id' => 'performance', 'label' => 'Performance', 'icon' => 'ki-chart-line-up',
     'children' => [ /* subitens com permissões individuais */ ]],
    ['id' => 'ai', 'label' => 'IA', 'icon' => 'ki-abstract-26',
     'children' => [ /* Copiloto, Agentes de IA, Agentes Kanban, Tools */ ]],
    ['id' => 'more', 'label' => 'Mais', 'icon' => 'ki-element-plus',
     'children' => [ /* Agentes, Tags, Templates, Integrações, Permissões,
                       Analytics, Configurações — com suas permissões */ ]],
];
```

Cada item suporta: `label`, `icon`, `route`, `permission`, `children`, `badge` (ex.: pulse do Coaching IA), `dock` (aparece no dock mobile), `separator`.

**Decisão de agrupamento** (18 itens → 8 ícones no rail):
1. **Dashboard** (grupo: Geral, IA)
2. **Conversas** (link direto — item mais usado)
3. **Contatos** (link direto)
4. **Funis** (grupo: Todos, Kanban)
5. **Campanhas** (grupo — 12 subitens com separadores)
6. **Performance** (grupo — inclui Metas, Coaching IA, Conversões)
7. **IA** (grupo: Copiloto, Agentes de IA, Agentes Kanban, Tools)
8. **⋯ Mais** (grupo: Agentes, Automações, Tags, Templates, Integrações, Permissões, Analytics, Configurações)

### 3.2 Novos arquivos

| Arquivo | Conteúdo |
|---|---|
| `views/layouts/metronic/menu-config.php` | Array do menu (acima) |
| `views/layouts/metronic/floating-menu.php` | Renderer: rail desktop + flyouts + dock mobile + bottom sheet (tudo a partir do config) |
| `public/assets/css/custom/floating-menu.css` | Todo o estilo novo (rail, flyout, dock, sheet, dark/light) |
| `public/assets/js/custom/floating-menu.js` | Abrir/fechar flyouts (hover intent + clique), bottom sheet, marcação de item ativo, teclado (Esc fecha) |

### 3.3 Arquivos alterados

| Arquivo | Mudança |
|---|---|
| `views/layouts/metronic/app.php` | Trocar include `sidebar.php` → `floating-menu.php`; **remover** o CSS inline de `--aside-width`/`padding-left` do body; substituir por `padding-left: 92px` (desktop) e `padding-bottom: 84px` (mobile, para o dock); remover JS `initSidebarToggle()` |
| `views/layouts/metronic/header.php` | Remover `#kt_aside_toggle` (drawer mobile deixa de existir — o dock assume) |
| `public/assets/css/custom/sidebar-toggle.css` | Manter **apenas** as regras do sidebar direito (`#kt_sidebar`, `right-sidebar-*`); remover tudo de `#kt_aside`/`aside-minimize` |
| `views/conversations/index.php` | Validar/ajustar: usa `conversations-layout` full-height próprio; conferir se o dock mobile não cobre o input de mensagem (na tela de conversa aberta o dock deve **ocultar-se** — ver 3.5) |

### 3.4 Z-index (mapa definitivo)

| Camada | z-index |
|---|---|
| Rail / Dock flutuante | 1050 |
| Flyout / Bottom sheet | 1060 |
| Header dropdowns (existente) | 1100-1200 (mantém acima) |
| Modais Bootstrap | 1055+ (padrão — sheet fecha ao abrir modal) |

### 3.5 Regras específicas do mobile

- Dock oculto com animação quando: (a) conversa aberta na tela de chat (o input de mensagem precisa do rodapé); (b) teclado virtual aberto (detecção via `visualViewport`). Botão flutuante "voltar" já existe no fluxo de conversas.
- `padding-bottom` do conteúdo aplicado via classe no `body` para não quebrar páginas full-height.
- Bottom sheet com `max-height: 80vh`, scroll interno próprio, fecha com swipe-down/tap no backdrop.

### 3.6 Estado ativo e badges

- Item ativo derivado da URI atual (reaproveitar lógica `isActive()` movida para o renderer).
- Grupo fica "ativo" se qualquer filho estiver ativo (ícone destacado no rail/dock).
- Badge de notificação (ex.: pulse verde do Coaching IA) suportado por config; futuro: contador de conversas não lidas no ícone Conversas (dado já disponível via realtime-client).

---

## 4. Fases de Implementação

### Fase 1 — Fundação (sem mudança visual)
1. Criar `menu-config.php` extraindo 100% dos itens e permissões do `sidebar.php` atual.
2. Criar renderer `floating-menu.php` gerando o novo HTML (rail + flyouts + dock + sheet).
3. **Validação**: diff manual item a item contra o menu atual (nenhum link/permissão perdido).

### Fase 2 — Desktop (rail flutuante)
4. `floating-menu.css`: rail, flyouts, dark/light mode (`[data-bs-theme]`), transições.
5. `floating-menu.js`: hover-intent (delay ~150ms para não abrir sem querer), clique para fixar, Esc/click-fora fecha.
6. `app.php`: trocar include, novo padding, remover CSS/JS legado do aside.
7. Limpar `sidebar-toggle.css` (manter só sidebar direito).

### Fase 3 — Mobile (dock + bottom sheet)
8. Dock inferior com os 4 itens `dock: true` + botão Menu.
9. Bottom sheet em grade agrupada, com todas as opções e permissões.
10. Regras de ocultação do dock (chat aberto, teclado virtual, safe-area iOS).
11. Remover toggle/drawer mobile do `header.php`.

### Fase 4 — Validação e ajustes
12. Testar páginas críticas: **Conversas** (layout próprio de 25k linhas — maior risco), Kanban (tela larga), Dashboard, Campanhas, Configurações.
13. Testar dark/light, colisões de z-index com dropdowns do header e modais.
14. Testar responsivo nos breakpoints: 375px, 768px, 991px (transição rail↔dock), 1366px, 1920px.
15. Remover `sidebar.php` antigo (ou manter 1 release como fallback — ver Rollback).

---

## 5. Riscos e Mitigações

| Risco | Mitigação |
|---|---|
| `views/conversations/index.php` (25.800 linhas) tem CSS próprio que assume o deslocamento atual do body | Fase 4 dedica teste específico; o novo padding é aplicado no mesmo ponto (body), minimizando impacto |
| Páginas com CSS que referencia `.aside-minimize` / `body.aside-minimize` | `grep` global antes da Fase 2; hoje só `sidebar-toggle.css` e `app.php` referenciam |
| Sidebar **direito** (conversas) compartilha o `sidebar-toggle.css` | Limpeza cirúrgica: remover apenas regras `#kt_aside`/`aside-minimize`, preservar `right-sidebar-*` |
| Usuários acostumados com menu lateral | Flyouts mantêm os mesmos nomes/agrupamentos; rail mantém ordem atual dos itens |
| Rollback | Manter `sidebar.php` no repositório por 1 release; voltar é trocar 1 include + restaurar bloco CSS no `app.php` |

---

## 6. Fora de Escopo (sugestões futuras)

- **Command palette (Ctrl+K)**: a busca global já existe em modal — promovê-la a atalho de teclado combinaria perfeitamente com o menu enxuto.
- Dock mobile configurável por usuário (escolher os 4 atalhos).
- Contador de não-lidas no ícone Conversas (rail + dock) via `realtime-client.js`.

---

## 7. Estimativa

| Fase | Esforço |
|---|---|
| Fase 1 (config + renderer) | 1 dia |
| Fase 2 (desktop) | 1-1,5 dia |
| Fase 3 (mobile) | 1 dia |
| Fase 4 (validação) | 0,5-1 dia |
| **Total** | **~4 dias úteis** |
