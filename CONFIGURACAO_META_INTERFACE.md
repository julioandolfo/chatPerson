# 🎯 Configuração Meta Direto na Interface

## O que foi implementado

Agora você pode configurar as credenciais do App Meta **direto na interface**, sem precisar editar arquivos!

### ✅ Recursos Adicionados

1. **Formulário de Configuração** na página `/integrations/meta`
2. **Campos do formulário:**
   - App ID (obrigatório)
   - App Secret (obrigatório, com botão mostrar/ocultar)
   - Webhook Verify Token (com botão gerar aleatório)
   - Redirect URI (somente leitura, para copiar)
   - Webhook URL (somente leitura, para copiar)

3. **Validação automática:**
   - Campos obrigatórios
   - Botão "Conectar Conta" desabilitado se credenciais não configuradas
   - Alerta visual se configuração incompleta

4. **Armazenamento seguro:**
   - Salvo em `storage/config/meta.json` (fora do Git)
   - Permissões restritas (0600)
   - Não expõe credenciais no código

5. **Integração completa:**
   - OAuth usa as credenciais salvas
   - Fallback para `config/meta.php` se JSON não existir
   - Prioriza configurações da interface sobre arquivo

---

## 🚀 Como Usar

### Passo 1: Acessar a Interface

1. Faça login no sistema
2. Vá em **Menu > Integrações > Meta (Instagram + WhatsApp)**

### Passo 2: Configurar Credenciais

Na seção **"Configuração do App Meta"**:

1. **App ID:** Cole o ID do seu app Meta
2. **App Secret:** Cole o secret (use o botão 👁️ para visualizar)
3. **Webhook Verify Token:** Use o botão "Gerar" ou cole o seu
4. **Redirect URI:** Copie para configurar no Meta for Developers
5. **Webhook URL:** Copie para configurar webhooks

6. Clique em **"Salvar Configurações"**

### Passo 3: Configurar no Meta for Developers

1. Acesse: https://developers.facebook.com/apps/
2. Selecione seu app
3. Em **Facebook Login > Configurações:**
   - Cole o **Redirect URI** em "URIs de redirecionamento do OAuth"
4. Em **Webhooks:**
   - Cole o **Webhook URL** 
   - Cole o **Webhook Verify Token**

### Passo 4: Conectar Contas

Após salvar as configurações, o botão **"Conectar Conta Meta"** será habilitado.

1. Clique em "Conectar Conta Meta"
2. Escolha: Instagram, WhatsApp ou Ambos
3. Autorize no Facebook/Instagram
4. ✅ Pronto!

---

## 📂 Estrutura de Arquivos

### Arquivos Criados/Modificados:

```
views/integrations/meta/
└── index.php                          ✅ Adicionado formulário de config

app/Controllers/
├── MetaIntegrationController.php      ✅ Métodos saveConfig() e getMetaConfig()
└── MetaOAuthController.php            ✅ initConfig() atualizado

storage/config/
├── .gitignore                         ✅ Ignora *.json
├── README.md                          ✅ Documentação do diretório
└── meta.json                          ✅ Criado automaticamente ao salvar

routes/
└── web.php                            ✅ Rota /integrations/meta/config/save
```

### Onde as Credenciais são Salvas:

```json
// storage/config/meta.json
{
    "app_id": "123456789012345",
    "app_secret": "abc123def456...",
    "webhook_verify_token": "seu_token_seguro",
    "updated_at": "2024-12-26 10:30:00"
}
```

**⚠️ Importante:** Este arquivo está no `.gitignore` e não será versionado.

---

## 🔒 Segurança

### Proteções Implementadas:

1. **Arquivo não versionado:**
   - `storage/config/*.json` está no `.gitignore`
   - Credenciais não ficam expostas no repositório

2. **Permissões restritas:**
   - Arquivo criado com `chmod 0600`
   - Apenas owner pode ler/escrever

3. **Validação de acesso:**
   - Apenas usuários com permissão `integrations.manage`
   - CSRF protection nas requisições

4. **Fallback seguro:**
   - Se JSON não existir, usa `config/meta.php`
   - Não quebra sistema existente

---

## 🎨 Interface

### Formulário de Configuração:

```
┌─────────────────────────────────────────────────────────┐
│ ⚙️ Configuração do App Meta      [Meta for Developers] │
├─────────────────────────────────────────────────────────┤
│                                                          │
│ ⚠️ Credenciais não configuradas                         │
│    Configure as credenciais abaixo para conectar contas │
│                                                          │
│ App ID *                    App Secret *                 │
│ [___________________]       [__________] [👁️]            │
│                                                          │
│ Webhook Verify Token *      Redirect URI                │
│ [___________________] [🔄]  [_____________] (readonly)   │
│                                                          │
│ Webhook URL                                              │
│ [_____________________________________________] (readonly)│
│                                                          │
│ ℹ️ Credenciais salvas no servidor      [Salvar Config]  │
└─────────────────────────────────────────────────────────┘
```

### Alertas:

- **⚠️ Amarelo:** Credenciais não configuradas
- **✅ Verde:** Configurações salvas com sucesso
- **❌ Vermelho:** Erro ao salvar

---

## 🧪 Testando

### 1. Verificar se salvou:

```bash
# Ver arquivo criado
cat storage/config/meta.json

# Deve mostrar:
{
    "app_id": "SEU_APP_ID",
    "app_secret": "SEU_APP_SECRET",
    "webhook_verify_token": "TOKEN_GERADO",
    "updated_at": "2024-12-26 10:30:00"
}
```

### 2. Verificar permissões:

```bash
ls -la storage/config/meta.json

# Deve mostrar: -rw------- (0600)
```

### 3. Testar OAuth:

1. Na interface, clique em "Conectar Conta Meta"
2. Será redirecionado para Facebook
3. Autorize
4. Deve voltar com sucesso

### 4. Verificar logs:

```bash
tail -f storage/logs/meta_*.log
```

---

## 🔄 Migração de Config Antiga

Se você já tem credenciais em `config/meta.php`:

### Opção A: Deixar como está (Fallback automático)
- Sistema detecta automaticamente
- Continua usando `config/meta.php`
- Nada quebra

### Opção B: Migrar para Interface
1. Abra `config/meta.php`
2. Copie `app_id`, `app_secret` e `webhook_verify_token`
3. Acesse `/integrations/meta`
4. Cole no formulário
5. Clique em "Salvar"
6. ✅ Pronto! Agora usa JSON

---

## 📝 Ordem de Prioridade

O sistema busca credenciais nesta ordem:

1. **storage/config/meta.json** ← Interface (prioridade)
2. **config/meta.php** ← Arquivo (fallback)
3. **Variáveis de ambiente** ← .env (se definidas em meta.php)

---

## 🎯 Vantagens

### Antes (Config Manual):
- ❌ Editar arquivos PHP
- ❌ Acessar servidor via FTP/SSH
- ❌ Conhecimento técnico necessário
- ❌ Risco de erro de sintaxe

### Agora (Config na Interface):
- ✅ Formulário visual simples
- ✅ Validação automática
- ✅ Gerar tokens aleatórios
- ✅ Copiar URLs facilmente
- ✅ Sem acesso ao servidor
- ✅ Sem conhecimento técnico
- ✅ Avisos visuais se incompleto

---

## 🆘 Problemas Comuns

### "Botão Conectar desabilitado"
**Causa:** Credenciais não configuradas
**Solução:** Preencha App ID, App Secret e Webhook Token

### "Erro ao salvar"
**Causa:** Permissões do diretório
**Solução:** 
```bash
mkdir -p storage/config
chmod 755 storage/config
```

### "ID do app inválido"
**Causa:** App ID incorreto
**Solução:** Verifique no Meta for Developers → Configurações → Básico

### "Redirect URI mismatch"
**Causa:** URI não configurada no Meta
**Solução:** Copie o Redirect URI da interface e cole no Facebook Login

---

## 🎉 Conclusão

Agora o processo de configuração é **muito mais simples**:

1. Criar app no Meta
2. Copiar credenciais
3. Colar na interface
4. Salvar
5. Conectar contas
6. ✅ Pronto!

**Sem editar arquivos, sem acessar servidor, sem complicação!** 🚀

