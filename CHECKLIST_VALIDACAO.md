# ✅ CHECKLIST DE VALIDAÇÃO - SISTEMA DE CAMPANHAS

Use este checklist para garantir que tudo está funcionando

---

## 📋 INSTALAÇÃO

- [ ] **Migrations executadas**
  ```bash
  php database\migrate.php
  ```
  Deve criar 6 novas tabelas sem erros

- [ ] **Validação automática passou**
  ```bash
  php VALIDACAO_INSTALACAO_CAMPANHAS.php
  ```
  Deve retornar: "✅ PERFEITO! Sistema 100% configurado"

---

## 🔌 CONTAS WHATSAPP

- [ ] **Tenho pelo menos 2 contas WhatsApp configuradas**
  ```bash
  php check-whatsapp-accounts.php
  ```
  Deve mostrar 2+ contas com status "ATIVA"

- [ ] **Contas estão na tabela `integration_accounts`**
  ```sql
  SELECT * FROM integration_accounts WHERE channel = 'whatsapp';
  ```

- [ ] **Anotei os IDs das contas para usar na campanha**
  - Conta 1: ID = ___
  - Conta 2: ID = ___
  - Conta 3: ID = ___

---

## 👥 CONTATOS

- [ ] **Tenho pelo menos 2 contatos cadastrados**
  ```bash
  php check-contacts.php
  ```

- [ ] **Contatos têm telefone válido**
  ```sql
  SELECT id, name, phone FROM contacts WHERE phone IS NOT NULL LIMIT 5;
  ```

- [ ] **Anotei IDs dos contatos para teste**
  - Contato 1: ID = ___
  - Contato 2: ID = ___

---

## 🧪 TESTE BÁSICO

- [ ] **Script de teste executou sem erro**
  ```bash
  php test-campaign-example.php
  ```
  Deve criar lista, campanha e preparar mensagens

- [ ] **Campanha foi criada com sucesso**
  ```sql
  SELECT id, name, status FROM campaigns ORDER BY id DESC LIMIT 1;
  ```
  Deve mostrar campanha com status "running"

- [ ] **Mensagens foram preparadas**
  ```sql
  SELECT COUNT(*) as total FROM campaign_messages WHERE campaign_id = 1;
  ```
  Deve retornar quantidade igual ao número de contatos

---

## 📤 PROCESSAMENTO

- [ ] **Script de processamento executou sem erro**
  ```bash
  php public\scripts\process-campaigns.php
  ```
  Deve mostrar "X Enviadas"

- [ ] **Mensagens foram enviadas**
  ```sql
  SELECT status, COUNT(*) FROM campaign_messages WHERE campaign_id = 1 GROUP BY status;
  ```
  Deve mostrar mensagens com status "sent"

- [ ] **Conversa foi criada**
  ```sql
  SELECT * FROM conversations ORDER BY id DESC LIMIT 2;
  ```
  Deve mostrar 2 conversas novas

---

## 🔄 ROTAÇÃO

- [ ] **Rotação funcionou (cada mensagem usou conta diferente)**
  ```bash
  php check-rotation.php 1
  ```
  Deve mostrar distribuição entre contas

- [ ] **Log de rotação foi registrado**
  ```sql
  SELECT * FROM campaign_rotation_log WHERE campaign_id = 1;
  ```

- [ ] **Distribuição está balanceada**
  Diferença entre contas deve ser ≤ 1 mensagem

---

## 📊 ESTATÍSTICAS

- [ ] **Estatísticas estão corretas**
  ```bash
  php check-stats.php 1
  ```
  Números devem bater com o banco

- [ ] **Progresso está atualizado**
  ```sql
  SELECT 
    total_contacts,
    total_sent,
    total_delivered,
    (total_sent / total_contacts * 100) as progress
  FROM campaigns WHERE id = 1;
  ```

- [ ] **Contadores foram incrementados**
  `total_sent` deve ser > 0

---

## ⚙️ CRON JOB (Opcional mas Recomendado)

- [ ] **Cron job configurado**
  - Windows: Task Scheduler
  - Linux: crontab

- [ ] **Script executa automaticamente a cada 1 minuto**

- [ ] **Logs estão sendo gerados**
  Verificar: `logs/campaigns.log`

---

## 🎯 VALIDAÇÃO FINAL

Se você marcou **TODOS os checkboxes acima**, o sistema está:

✅ **100% Instalado**  
✅ **100% Configurado**  
✅ **100% Funcional**  
✅ **100% Testado**  

**PRONTO PARA PRODUÇÃO!** 🎉

---

## 🚨 SE ALGO NÃO PASSOU

### ❌ Migrations falharam
- Verifique conexão com banco
- Verifique permissões do usuário MySQL
- Execute uma por uma manualmente

### ❌ Contas não encontradas
- Configure contas em `/integrations`
- Ative as contas (status = 'active')
- Execute `check-whatsapp-accounts.php` novamente

### ❌ Mensagens não enviaram
- Verifique se contas estão ativas
- Verifique se está dentro da janela de horário
- Execute processamento manual: `php public\scripts\process-campaigns.php`
- Veja logs: `logs/campaigns.log`

### ❌ Rotação não funcionou
- Verifique se tem 2+ contas ativas
- Veja `campaign_messages.integration_account_id`
- Execute: `php check-rotation.php 1`

---

## 📞 PRÓXIMOS PASSOS

Após validação completa:

1. **Teste em produção** com volume pequeno
2. **Configure cron job** para automação
3. **Monitore resultados** primeiros dias
4. **Ajuste cadência** conforme performance
5. **Escale gradualmente**

---

**Boa sorte com suas campanhas!** 🚀

---

**Última atualização:** 18/01/2026
