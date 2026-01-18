# 🗺️ MAPA DE ARQUIVOS - SISTEMA DE CAMPANHAS

Localização rápida de todos os arquivos criados

---

## 📂 BACKEND

### **Migrations** (database/migrations/)
```
110_create_campaigns_table.php                    ← Tabela principal
111_create_contact_lists_table.php                ← Listas de contatos
112_create_contact_list_items_table.php           ← Itens das listas
113_create_campaign_messages_table.php            ← Mensagens individuais
114_create_campaign_blacklist_table.php           ← Blacklist
115_create_campaign_rotation_log_table.php        ← Log de rotação
```

### **Models** (app/Models/)
```
Campaign.php                 ← Model principal (+ helpers de stats)
ContactList.php              ← Gestão de listas
CampaignMessage.php          ← Tracking de mensagens
CampaignBlacklist.php        ← Gestão de blacklist
```

### **Services** (app/Services/)
```
CampaignService.php              ← CRUD + controle (start, pause, etc)
ContactListService.php           ← Listas + import CSV
CampaignSchedulerService.php ⭐  ← CORE: Envio + Rotação
```

### **Controllers** (app/Controllers/)
```
CampaignController.php           ← Endpoints de campanhas
ContactListController.php        ← Endpoints de listas
```

### **Rotas** (routes/)
```
web.php                          ← 26 rotas adicionadas
```

### **Seeds** (database/seeds/)
```
002_create_roles_and_permissions.php  ← 5 permissões adicionadas
```

---

## 🔧 SCRIPTS

### **Cron Job** (public/scripts/)
```
process-campaigns.php            ← Processa campanhas (rodar a cada 1 min)
```

### **Scripts de Teste** (raiz do projeto)
```
test-campaign-example.php        ← Teste completo (criar + enviar)
check-whatsapp-accounts.php      ← Ver contas WhatsApp disponíveis
check-contacts.php               ← Ver contatos cadastrados
check-stats.php                  ← Ver estatísticas de campanha
check-rotation.php               ← Ver rotação de contas
VALIDACAO_INSTALACAO_CAMPANHAS.php  ← Validação automática completa
```

---

## 📚 DOCUMENTAÇÃO

### **Início Rápido** (raiz do projeto)
```
README_CAMPANHAS.md              ← Visão geral (2 páginas)
INICIO_RAPIDO_CAMPANHAS.md       ← Começar em 5 min (2 páginas)
CAMPANHAS_INDEX.md               ← Índice central (navegação)
```

### **Guias de Uso**
```
GUIA_COMPLETO_CAMPANHAS.md       ← Manual detalhado (5 páginas)
TESTE_CAMPANHAS_PASSO_A_PASSO.md ← Testes práticos (6 páginas)
FAQ_CAMPANHAS.md                 ← 30+ perguntas (5 páginas)
CHECKLIST_VALIDACAO.md           ← Checklist passo a passo
```

### **Referências Técnicas**
```
ANALISE_SISTEMA_CAMPANHAS.md     ← Arquitetura detalhada (15 páginas)
ROTAS_CAMPANHAS.md               ← API REST (4 páginas)
DIAGRAMA_FLUXO_CAMPANHAS.md      ← Fluxos visuais (6 páginas)
```

### **Setup e Status**
```
SETUP_CAMPANHAS.md               ← Setup e troubleshooting
STATUS_DESENVOLVIMENTO_CAMPANHAS.md  ← Checklist de desenvolvimento
RESUMO_FINAL_CAMPANHAS.md       ← Resumo executivo
ENTREGA_FINAL_CAMPANHAS.md      ← Documento de entrega
SISTEMA_CAMPANHAS_ENTREGUE.txt  ← Sumário visual
MAPA_ARQUIVOS_CAMPANHAS.md      ← Este arquivo
```

---

## 📍 LOCALIZAÇÃO POR NECESSIDADE

### **"Preciso começar rápido!"**
👉 `INICIO_RAPIDO_CAMPANHAS.md` (5 minutos)

### **"Como faço X?"**
👉 `GUIA_COMPLETO_CAMPANHAS.md` (manual)

### **"Tenho uma dúvida..."**
👉 `FAQ_CAMPANHAS.md` (30+ perguntas)

### **"Algo não funciona!"**
👉 `TESTE_CAMPANHAS_PASSO_A_PASSO.md` (debug)

### **"Quero entender o código"**
👉 `ANALISE_SISTEMA_CAMPANHAS.md` (arquitetura)

### **"Preciso desenvolver..."**
👉 `ROTAS_CAMPANHAS.md` (API REST)

### **"Quero ver tudo que tem"**
👉 `CAMPANHAS_INDEX.md` (índice central)

---

## 🎯 ARQUIVOS MAIS IMPORTANTES

### **Top 5 Arquivos para Começar:**
1. **INICIO_RAPIDO_CAMPANHAS.md** - Comece aqui!
2. **test-campaign-example.php** - Execute para testar
3. **check-whatsapp-accounts.php** - Veja suas contas
4. **process-campaigns.php** - Processa os envios
5. **FAQ_CAMPANHAS.md** - Tire dúvidas

### **Top 3 Arquivos Técnicos:**
1. **CampaignSchedulerService.php** - Coração do sistema
2. **ANALISE_SISTEMA_CAMPANHAS.md** - Entenda tudo
3. **ROTAS_CAMPANHAS.md** - Use a API

---

## 🔍 BUSCA RÁPIDA

### **"Onde está a lógica de rotação?"**
📂 `app/Services/CampaignSchedulerService.php`  
📍 Métodos: `selectAccount()`, `selectAccountRoundRobin()`, etc

### **"Onde são as validações?"**
📂 `app/Services/CampaignSchedulerService.php`  
📍 Método: `shouldSkipContact()`

### **"Onde cria a campanha?"**
📂 `app/Services/CampaignService.php`  
📍 Método: `create()`

### **"Onde processa os envios?"**
📂 `app/Services/CampaignSchedulerService.php`  
📍 Método: `processPending()`

### **"Onde está o cron?"**
📂 `public/scripts/process-campaigns.php`

### **"Onde estão as rotas?"**
📂 `routes/web.php`  
📍 Linha: ~600 (no final do arquivo)

---

## 📊 ESTRUTURA DE PASTAS

```
c:\laragon\www\chat\
│
├── database\
│   ├── migrations\
│   │   ├── 110_create_campaigns_table.php
│   │   ├── 111_create_contact_lists_table.php
│   │   ├── 112_create_contact_list_items_table.php
│   │   ├── 113_create_campaign_messages_table.php
│   │   ├── 114_create_campaign_blacklist_table.php
│   │   └── 115_create_campaign_rotation_log_table.php
│   │
│   └── seeds\
│       └── 002_create_roles_and_permissions.php (modificado)
│
├── app\
│   ├── Models\
│   │   ├── Campaign.php
│   │   ├── ContactList.php
│   │   ├── CampaignMessage.php
│   │   └── CampaignBlacklist.php
│   │
│   ├── Services\
│   │   ├── CampaignService.php
│   │   ├── ContactListService.php
│   │   └── CampaignSchedulerService.php ⭐
│   │
│   └── Controllers\
│       ├── CampaignController.php
│       └── ContactListController.php
│
├── public\
│   └── scripts\
│       └── process-campaigns.php
│
├── routes\
│   └── web.php (modificado)
│
├── Scripts de Teste\
│   ├── test-campaign-example.php
│   ├── check-whatsapp-accounts.php
│   ├── check-contacts.php
│   ├── check-stats.php
│   ├── check-rotation.php
│   └── VALIDACAO_INSTALACAO_CAMPANHAS.php
│
└── Documentação\
    ├── README_CAMPANHAS.md
    ├── CAMPANHAS_INDEX.md
    ├── INICIO_RAPIDO_CAMPANHAS.md
    ├── GUIA_COMPLETO_CAMPANHAS.md
    ├── TESTE_CAMPANHAS_PASSO_A_PASSO.md
    ├── FAQ_CAMPANHAS.md
    ├── SETUP_CAMPANHAS.md
    ├── ROTAS_CAMPANHAS.md
    ├── DIAGRAMA_FLUXO_CAMPANHAS.md
    ├── ANALISE_SISTEMA_CAMPANHAS.md
    ├── STATUS_DESENVOLVIMENTO_CAMPANHAS.md
    ├── RESUMO_FINAL_CAMPANHAS.md
    ├── ENTREGA_FINAL_CAMPANHAS.md
    ├── CHECKLIST_VALIDACAO.md
    ├── SISTEMA_CAMPANHAS_ENTREGUE.txt
    └── MAPA_ARQUIVOS_CAMPANHAS.md (este arquivo)
```

---

## 🎯 COMANDOS ÚTEIS POR LOCALIZAÇÃO

### **Executar da raiz do projeto:**
```bash
# Migrations
php database\migrate.php

# Testes
php test-campaign-example.php
php check-whatsapp-accounts.php
php check-contacts.php
php check-stats.php 1
php check-rotation.php 1
php VALIDACAO_INSTALACAO_CAMPANHAS.php

# Processamento manual
php public\scripts\process-campaigns.php
```

### **Visualizar no navegador:**
```
http://localhost/campaigns              (lista de campanhas)
http://localhost/campaigns/create       (criar campanha)
http://localhost/campaigns/1            (detalhes)
http://localhost/api/campaigns/1/stats  (estatísticas JSON)
http://localhost/contact-lists          (listas)
```

---

## 🔗 RELAÇÃO DE DEPENDÊNCIAS

```
Campaign
  ├── usa → ContactList
  │   └── tem → ContactListItem
  │       └── referencia → Contact
  │
  ├── cria → CampaignMessage
  │   ├── referencia → Contact
  │   ├── referencia → Conversation
  │   ├── referencia → Message
  │   └── usa → IntegrationAccount (rotação)
  │
  └── valida → CampaignBlacklist
```

---

## 📝 NOTAS FINAIS

- ✅ Todos os arquivos estão documentados internamente
- ✅ Código segue padrões PSR-12
- ✅ Migrations são reversíveis (função `down_`)
- ✅ Services têm tratamento de erros
- ✅ Controllers têm validação de permissões
- ✅ Models têm helpers úteis

---

**Use este mapa para navegar rapidamente pelos arquivos!**

**Última atualização:** 18/01/2026
