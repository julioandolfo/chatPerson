# Proposta: Redesign da página /conversations — Painéis Flutuantes

**Data:** 03/07/2026
**Branch:** `claude/floating-menu-redesign-nwjhmi`
**Status:** 📋 Proposta aguardando aprovação (mockups enviados no chat)
**Contexto:** continuação da linguagem visual do menu flutuante (rail/dock)

---

## 1. Diagnóstico do visual atual

A tela de conversas funciona bem, mas visualmente é "colada e chapada":

| Elemento | Hoje | Problema visual |
|---|---|---|
| Layout 3 colunas (`.conversations-layout`) | Colunas encostadas, separadas por `border-right: 1px` | Parece uma tabela; nada respira |
| Colunas (`.conversations-list`, `.chat-area`, `.conversation-sidebar`) | Fundo chapado `var(--bs-body-bg)`, sem raio, sem sombra | Sem hierarquia entre superfície e página |
| Itens da lista (`.conversation-item`) | Linhas full-width com `border-bottom`, estado ativo = barra esquerda 3px | Visual de lista de e-mail antiga |
| Cabeçalho de métricas (`.conversations-metrics-header`) | Bloco chapado com `border-bottom` | Compete com o header do chat |
| Header do chat (`.chat-header`) e composer (`.chat-input`) | Blocos opacos com bordas retas | Cortes duros entre áreas |
| Bolhas (`.message-bubble`) | Raio 12px, sem sombra | OK, mas abaixo do novo padrão do menu/copiloto |
| Área de mensagens (`.chat-messages`) | `var(--bs-gray-100)` chapado | Plano e sem profundidade |

## 2. Conceito proposto: "Painéis Flutuantes" (mesma família do menu)

Os mockups (claro e escuro) foram gerados e enviados no chat. Resumo do conceito:

### 2.1 Estrutura
- As 3 colunas viram **3 painéis flutuantes independentes** com `gap: 14px`,
  `border-radius: 16px`, borda translúcida de 1px e sombra suave — os mesmos
  tokens do rail do menu e do widget do Copiloto.
- O **fundo da página** ganha um tom levemente distinto + um radial sutil na cor
  primária (2-7% de opacidade), para os painéis "flutuarem".

### 2.2 Transparência (glass)
`backdrop-filter: blur(14px)` + fundo semitransparente **apenas nas superfícies
pequenas e fixas** (por performance):
- Barra de métricas (vira um card glass separado, acima dos painéis)
- Header do chat (o conteúdo das mensagens rola por baixo dele)
- Composer (idem)
- Separadores de data ("Hoje") viram pills glass
- Fallback automático: browsers sem `backdrop-filter` recebem fundo sólido
  (`@supports not (backdrop-filter: blur(1px))`).

### 2.3 Lista de conversas
- Itens viram **cards arredondados (13px)** com margem interna de 8px,
  sem `border-bottom`.
- Estados atuais preservados com nova roupagem (borda 1px colorida + fundo tint,
  em vez da barra esquerda):
  - **Ativa**: fundo primário 10% + borda primária 25%
  - **Fixada (pinned)**: âmbar
  - **Alerta de inatividade**: vermelho + chip "Sem resposta Xmin"
  - **Aguardando cliente**: chip âmbar
- Avatares com raio 12px (quadrado arredondado, como o rail) e gradientes.

### 2.4 Chat
- Bolhas com raio 16px (canto da "cauda" 6px); enviadas com gradiente primário
  e sombra colorida sutil; recebidas com borda + sombra leve.
- Botão "Resolver" e ações do header como botões-pill arredondados.
- Textarea do composer com raio 14px e focus ring suave
  (`box-shadow: 0 0 0 3px rgba(primary, .12)`).
- Botão de enviar circular-arredondado com gradiente (par do widget Copiloto).

### 2.5 Painel de detalhes
- Blocos internos (Contato, Funil, Tags, Pedidos) viram **sub-cards** com raio
  13px sobre o painel, e chips/pills arredondados.

### 2.6 Scrollbars
- Finas (6px), thumb na cor primária com 18% de opacidade.

## 3. Estratégia de implementação (baixo risco)

**Um único arquivo novo de override**: `public/assets/css/custom/conversations-glass.css`,
carregado apenas na página de conversas (via `$styles` da view).

- **Zero alteração estrutural** no `views/conversations/index.php` (25k linhas):
  todos os seletores acima já existem (`.conversations-layout`, `.conversation-item`,
  `.chat-header`, `.chat-input`, `.message-bubble`, etc.) — o skin é 100% CSS.
- Tokens definidos em `:root`/`[data-bs-theme="dark"]` no próprio arquivo,
  espelhando os do `floating-menu.css`.
- **Rollback** = remover 1 `<link>`.

### Pontos de atenção mapeados
| Risco | Mitigação |
|---|---|
| `backdrop-filter` é caro em listas longas | Glass só em superfícies fixas pequenas (headers/composer/pills); painéis usam fundo com alpha alto (0.82-0.86) sem blur |
| Mobile: as views são full-screen com transição lateral | No mobile os painéis ficam **full-bleed** (sem margens/raio nas laterais); glass mantido no header/composer; nada muda na navegação em 3 views |
| Dropdowns dos itens (`overflow: visible`) | Cards mantêm `overflow: visible`; raio não corta dropdown |
| Tema escuro com muitos overrides existentes (`theme-dark-light-fix.css`) | Tokens próprios com prefixo (`--cvg-*`) para não colidir |
| Barra de progresso de autoclose (`.conv-autoclose-bar`) posicionada no bottom do item | Reposicionada para respeitar o raio (inset + raio herdado) |

## 4. Escopo e fases

| Fase | Conteúdo | Esforço |
|---|---|---|
| 1 | Tokens + painéis flutuantes + fundo da página | 0,5 dia |
| 2 | Lista (cards, estados, abas, busca) | 0,5 dia |
| 3 | Chat (header glass, bolhas, separadores, composer) | 0,5 dia |
| 4 | Painel de detalhes + scrollbars + dark + mobile + validação visual | 0,5-1 dia |
| **Total** | | **~2 dias** |

## 5. Fora de escopo (não muda)
- Nenhuma lógica JS, polling, websocket, envio de mensagens
- Nenhum markup/estrutura da página
- Kanban, dashboard e demais telas (podem herdar o skin depois, se aprovado)
