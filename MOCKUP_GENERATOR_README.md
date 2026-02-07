# 🎨 Gerador de Mockup com IA

Sistema completo de geração de mockups profissionais usando **GPT-4o Vision + DALL-E 3**.

## ✨ Funcionalidades

### 🤖 Geração Inteligente com IA
- **GPT-4o Vision**: Analisa produto e logo, otimiza o prompt automaticamente
- **DALL-E 3**: Gera mockup fotorrealista de alta qualidade
- **Processamento**: ~30-60 segundos por mockup
- **Custo**: ~$0.04-0.05 por geração (desprezível)

### 🎯 Wizard em 3 Etapas
1. **Produto**: Selecione imagem do produto da conversa ou faça upload
2. **Logo**: Configure posicionamento, tamanho, estilo, efeitos
3. **Gerar**: Escolha modo (IA/Manual/Híbrido) e gere o mockup

### ⚙️ Configurações Avançadas
- **Posicionamento**: 9 posições (centro, cantos, laterais)
- **Tamanho**: 5% a 50% do produto
- **Estilo**: Original, branco, preto, escala de cinza
- **Opacidade**: 20% a 100%
- **Efeitos**: Sombra, borda, reflexo
- **Presets**: Caneca, Camiseta, Caderno, Caneta

### 📊 Modos de Geração
- **IA Automática** ⭐ (Recomendado): GPT-4o + DALL-E 3
- **Editor Manual**: Canvas Fabric.js com controle total
- **Híbrido**: IA gera base + edição manual

### 🖼️ Galeria de Mockups
- Histórico de todos mockups gerados
- Visualização, download, envio na conversa
- Indicadores de modo (IA/Manual/Híbrido)
- Filtros e busca

---

## 📦 Instalação

### 1. Executar Migrations

```bash
cd c:\laragon\www\chat
php database/run_migrations.php
```

Isso criará as tabelas:
- `mockup_products`
- `conversation_logos`
- `mockup_generations`
- `mockup_templates`

### 2. Verificar API Key da OpenAI

Certifique-se que a API Key da OpenAI está configurada em:
- **Configurações > OpenAI** (no sistema)
- Ou variável de ambiente `OPENAI_API_KEY`

### 3. Criar Diretórios

```bash
mkdir -p public/assets/media/mockups
mkdir -p public/assets/media/logos
mkdir -p public/assets/media/products
chmod -R 777 public/assets/media/mockups
chmod -R 777 public/assets/media/logos
chmod -R 777 public/assets/media/products
```

### 4. Verificar Dependências Frontend

O sistema já está configurado para carregar:
- **Fabric.js 5.3.0** (CDN) - para editor canvas
- **SweetAlert2** (já instalado)
- **Bootstrap 5** (já instalado)

---

## 🚀 Como Usar

### Para o Time Comercial

1. **Abrir Conversa** com o cliente
2. **Clicar no botão verde** 🎨 "Gerar Mockup" na toolbar
3. **Selecionar Produto**: Escolher imagem do produto enviada pelo cliente
4. **Configurar Logo**: Definir posição, tamanho e estilo da logo
5. **Gerar**: Clicar em "Gerar Mockup" e aguardar ~30-60 segundos
6. **Resultado**: Visualizar, baixar ou enviar direto na conversa

### Dicas de Uso

- **Presets**: Use os botões rápidos (Caneca, Camiseta, etc.) para configuração automática
- **Preview**: Sempre verifique o preview ao vivo antes de gerar
- **Prompt**: O prompt é otimizado automaticamente, mas pode ser editado
- **Qualidade**: Use "HD" apenas se precisar de altíssima qualidade (mais demorado)

---

## 🔧 Arquitetura Técnica

### Backend

```
app/
├── Controllers/
│   └── MockupController.php          # Rotas e endpoints
├── Services/
│   ├── DALLEService.php              # GPT-4o Vision + DALL-E 3
│   ├── MockupService.php             # Orquestração da geração
│   └── CanvasService.php             # Processar canvas → imagem
├── Models/
│   ├── MockupGeneration.php          # Histórico de gerações
│   ├── MockupProduct.php             # Produtos salvos
│   ├── MockupTemplate.php            # Templates canvas
│   └── ConversationLogo.php          # Logos por conversa
└── ...
```

### Frontend

```
public/assets/
├── js/
│   ├── mockup-wizard.js              # Wizard de 3 etapas
│   └── mockup-canvas-editor.js       # Editor Fabric.js
└── css/
    └── mockup-editor.css             # Estilos

views/conversations/
├── mockup-modal.php                  # Modal HTML
└── mockup-gallery.php                # Galeria no sidebar
```

### Rotas API

```
POST   /api/conversations/{id}/mockups/generate
POST   /api/conversations/{id}/mockups/save-canvas
GET    /api/conversations/{id}/mockups
GET    /api/mockups/{id}
POST   /api/mockups/{id}/send-message
DELETE /api/mockups/{id}
POST   /api/mockups/{id}/regenerate

# Produtos
GET    /api/mockup-products
POST   /api/mockup-products
DELETE /api/mockup-products/{id}

# Logos
POST   /api/conversations/{id}/logos/upload
GET    /api/conversations/{id}/logos
DELETE /api/logos/{id}

# Templates
GET    /api/mockup-templates
POST   /api/mockup-templates
DELETE /api/mockup-templates/{id}
```

---

## 💡 Fluxo Técnico (IA Automática)

```
1. Usuário configura produto + logo → Frontend
   ↓
2. POST /api/conversations/{id}/mockups/generate
   ↓
3. MockupController::generate()
   ↓
4. MockupService::generateWithAI()
   ↓
5. DALLEService::generateMockup()
   ├─→ GPT-4o Vision analisa produto + logo (base64)
   ├─→ GPT-4o gera prompt otimizado
   └─→ DALL-E 3 gera mockup a partir do prompt
   ↓
6. Download da imagem gerada
   ↓
7. Gera thumbnail
   ↓
8. Salva em mockup_generations
   ↓
9. Retorna para Frontend
   ↓
10. Exibe resultado + opção de enviar
```

---

## 📊 Banco de Dados

### `mockup_generations`
Histórico completo de todas gerações:
- IDs de produto e logo
- Configurações da logo (JSON)
- Prompts (original e otimizado)
- Análise do GPT-4o
- Caminho do resultado
- Status, tempo, custos
- Modo de geração

### `conversation_logos`
Logos enviadas em cada conversa:
- Path da logo
- Dimensões, tamanho, mime type
- Flag `is_primary` (logo principal)

### `mockup_products`
Produtos salvos para reutilização:
- Nome, categoria, descrição
- Imagem do produto
- Contador de uso

### `mockup_templates`
Templates do editor canvas salvos:
- Canvas data (JSON do Fabric.js)
- Thumbnail, categoria
- Flag `is_public`

---

## 🎯 Custos OpenAI

| Operação | Modelo | Custo |
|----------|--------|-------|
| Análise GPT-4o Vision | gpt-4o | ~$0.003-0.005 |
| Geração DALL-E 3 | dall-e-3 | $0.040 (1024x1024) |
| **Total por mockup** | - | **~$0.043-0.045** |

**Observações**:
- Custos extremamente baixos (~R$0.22 por mockup)
- Qualidade profissional justifica o investimento
- Economia de tempo: 30s vs 30min manual

---

## 🔒 Segurança

- ✅ Autenticação obrigatória em todas rotas
- ✅ Validação de tipos de arquivo (logos/produtos)
- ✅ Limites de tamanho (logos: 5MB, produtos: 16MB)
- ✅ Isolamento por conversa (cada conversa vê apenas seus mockups)
- ✅ Cleanup automático ao deletar

---

## 🐛 Troubleshooting

### Erro: "API Key não configurada"
**Solução**: Configure a API Key da OpenAI em Configurações > OpenAI

### Erro: "Falha ao salvar imagem"
**Solução**: Verifique permissões das pastas:
```bash
chmod -R 777 public/assets/media/mockups
chmod -R 777 public/assets/media/logos
```

### Mockup não aparece na galeria
**Solução**: Atualize a página ou clique no botão de atualizar na galeria

### Fabric.js não carrega
**Solução**: Verifique conexão com CDN. O sistema carrega automaticamente se necessário.

---

## 🚀 Melhorias Futuras (Roadmap)

- [ ] Suporte a múltiplas logos em um mockup
- [ ] Detecção automática de tipo de produto com IA
- [ ] Biblioteca de produtos pré-definidos
- [ ] Edição de mockups já gerados
- [ ] Batch generation (gerar múltiplos de uma vez)
- [ ] Integração com banco de imagens (Unsplash, Pexels)
- [ ] Exportar mockup em múltiplos formatos (PDF, SVG)
- [ ] Compartilhamento de templates entre usuários
- [ ] Análise de performance de mockups (quais convertem mais)

---

## 📞 Suporte

Em caso de dúvidas ou problemas:
1. Verificar este README
2. Consultar logs em `logs/` (se habilitado)
3. Verificar console do navegador (F12)

---

## ✅ Checklist de Implementação

- [x] Migrations criadas
- [x] Models implementados
- [x] Services (DALL-E, Mockup, Canvas)
- [x] Controller e rotas
- [x] Frontend (Wizard, Modal, JS)
- [x] Editor Canvas (Fabric.js)
- [x] Galeria no sidebar
- [x] Estilos CSS
- [x] Integração GPT-4o Vision
- [x] Integração DALL-E 3
- [x] Sistema de logos por conversa
- [x] Preview ao vivo
- [x] Presets de produtos
- [x] Histórico e custos
- [x] Envio como mensagem
- [x] Download de mockups

---

## 🎉 Conclusão

Sistema completo e funcional de geração de mockups com IA!

**Tecnologias**:
- GPT-4o Vision (análise inteligente)
- DALL-E 3 (geração fotorrealista)
- Fabric.js (editor canvas)
- Laravel/PHP (backend)
- Bootstrap 5 (UI)

**Resultado**: Mockups profissionais em ~30 segundos! 🚀
