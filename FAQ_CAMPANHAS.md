# ❓ FAQ - PERGUNTAS FREQUENTES SOBRE CAMPANHAS

**Respostas rápidas para dúvidas comuns**

---

## 🔄 ROTAÇÃO DE CONTAS

### **P: Quantas contas posso usar na rotação?**
**R:** Sem limite! Você pode usar 2, 3, 5, 10 ou mais contas. Quanto mais contas, melhor a distribuição.

### **P: E se uma conta cair durante o envio?**
**R:** O sistema detecta automaticamente e pula contas inativas. As mensagens continuam sendo enviadas pelas contas restantes.

### **P: Como sei qual conta enviou cada mensagem?**
**R:** Execute `php check-rotation.php 1` ou consulte a tabela `campaign_messages` (coluna `integration_account_id`).

### **P: Posso adicionar/remover contas durante a campanha?**
**R:** Não durante execução. Pause a campanha, edite e retome.

---

## ⏱️ CADÊNCIA E TIMING

### **P: Qual a melhor taxa de envio?**
**R:** Recomendado: 10-20 msgs/minuto para evitar bloqueios. Para contas novas: 5-10 msgs/min.

### **P: O que acontece se a campanha não terminar dentro da janela?**
**R:** Ela pausa automaticamente e retoma no próximo dia útil no horário configurado.

### **P: Posso enviar 24/7?**
**R:** Sim! Deixe `send_window_start` e `send_window_end` vazios ou NULL.

### **P: Como funciona o intervalo entre mensagens?**
**R:** Sistema aguarda X segundos após cada envio antes de processar a próxima. Usa `usleep()`.

---

## 📝 LISTAS DE CONTATOS

### **P: Posso usar o mesmo contato em múltiplas listas?**
**R:** Sim! Um contato pode estar em várias listas diferentes.

### **P: Como importar 10.000 contatos de uma vez?**
**R:** Use `ContactListService::importFromCsv()` ou adicione em lote via loop PHP.

### **P: Listas dinâmicas estão implementadas?**
**R:** Não na versão 1.0. Você pode criar listas baseadas em filtros manualmente via código.

---

## 💬 MENSAGENS E VARIÁVEIS

### **P: Quais variáveis posso usar?**
**R:** 
```
{{nome}}, {{primeiro_nome}}, {{sobrenome}},
{{telefone}}, {{email}}, {{cidade}}, 
{{pais}}, {{empresa}}

+ qualquer custom_attribute do contato
```

### **P: Posso enviar imagens/vídeos?**
**R:** Sim! Use o campo `attachments` (JSON array de URLs).

### **P: Como testar a mensagem antes de enviar?**
**R:** Crie uma lista com apenas seu número e teste.

---

## 🎯 CAMPANHAS

### **P: Posso pausar uma campanha no meio?**
**R:** Sim! Use `CampaignService::pause($id)`. Para retomar: `CampaignService::resume($id)`.

### **P: Posso editar campanha depois de iniciada?**
**R:** Não. Pause, cancele ou crie nova campanha.

### **P: Quantas campanhas posso rodar simultaneamente?**
**R:** Sem limite técnico. Mas cuidado com rate limit total (soma de todas).

### **P: Como duplicar uma campanha?**
**R:** Atualmente via código. Busque a campanha e crie nova com mesmos dados.

---

## ✅ VALIDAÇÕES

### **P: O que é blacklist?**
**R:** Lista de contatos que não devem receber campanhas (opt-out, inválidos, etc).

### **P: Como adicionar alguém à blacklist?**
**R:** 
```php
CampaignBlacklist::addContact($contactId, 'Pediu para parar', $userId);
// ou
CampaignBlacklist::addPhone('5511999991111', 'Número inválido', $userId);
```

### **P: O que significa "skip_recent_conversations"?**
**R:** Não envia se o contato tem conversa ativa nas últimas X horas (default: 24h). Evita spam.

### **P: Como desabilitar validações?**
**R:** Ao criar campanha:
```php
'skip_duplicates' => false,
'skip_recent_conversations' => false,
'respect_blacklist' => false
```

---

## 📊 ESTATÍSTICAS

### **P: Como ver estatísticas em tempo real?**
**R:** 
```php
$stats = CampaignService::getStats($campaignId);
// ou via API:
GET /api/campaigns/{id}/stats
```

### **P: Quando as estatísticas são atualizadas?**
**R:** 
- `total_sent`: Imediatamente após envio
- `total_delivered`: Via webhook (segundos/minutos após)
- `total_read`: Via webhook (quando cliente abre)
- `total_replied`: Detectado automaticamente quando cliente responde

### **P: Como exportar relatório?**
**R:** Atualmente via SQL direto ou script PHP customizado.

---

## 🐛 PROBLEMAS COMUNS

### **P: "Campanha não encontrada"**
**R:** Verifique se o ID existe: `Campaign::find($id)`

### **P: "Nenhuma conta ativa disponível"**
**R:** 
1. Execute: `php check-whatsapp-accounts.php`
2. Ative pelo menos 1 conta
3. Tente novamente

### **P: Mensagens não estão sendo enviadas**
**R:** 
1. Campanha está com status `running`?
2. Cron job está configurado?
3. Está dentro da janela de horário?
4. Execute manualmente: `php public\scripts\process-campaigns.php`

### **P: "Contato sem telefone"**
**R:** Contato precisa ter campo `phone` preenchido. Valide antes de adicionar à lista.

---

## ⚙️ CONFIGURAÇÃO

### **P: Onde configurar o cron job?**
**R:** 
- **Windows:** Task Scheduler (Agendador de Tarefas)
- **Linux:** crontab -e
- **Frequência:** A cada 1 minuto

### **P: Posso processar manualmente sem cron?**
**R:** Sim! Execute: `php public\scripts\process-campaigns.php` quando quiser.

### **P: Como verificar se cron está funcionando?**
**R:** Veja o arquivo `logs/campaigns.log` ou execute o script manualmente.

---

## 📈 PERFORMANCE

### **P: Quantas mensagens por minuto consigo enviar?**
**R:** 
- 1 conta: até 20 msgs/min (recomendado: 10)
- 5 contas: até 100 msgs/min (recomendado: 50)
- 10 contas: até 200 msgs/min (recomendado: 100)

### **P: Sistema aguenta 100.000 mensagens?**
**R:** Sim! Tabelas são indexadas e queries otimizadas. Processa em lotes de 50.

### **P: Quanto tempo para enviar 10.000 mensagens?**
**R:** 
- 5 contas × 20 msgs/min = 100 msgs/min
- 10.000 ÷ 100 = **100 minutos (~1h40min)**

---

## 🔐 SEGURANÇA E COMPLIANCE

### **P: Sistema respeita LGPD?**
**R:** Sim, através da blacklist. Adicione quem pedir opt-out.

### **P: Como implementar opt-out automático?**
**R:** Detecte palavras-chave ("SAIR", "PARAR") e adicione à blacklist:
```php
if (stripos($message, 'SAIR') !== false) {
    CampaignBlacklist::addContact($contactId, 'Opt-out automático', null, 'auto_optout');
}
```

### **P: Logs ficam salvos?**
**R:** Sim, em:
- `logs/campaigns.log`
- `logs/app.log`
- Tabela `campaign_rotation_log`

---

## 🛠️ CUSTOMIZAÇÕES

### **P: Posso adicionar novos campos à campanha?**
**R:** Sim! Adicione coluna na migration, no Model fillable e no Service.

### **P: Posso criar estratégia de rotação customizada?**
**R:** Sim! Adicione método em `CampaignSchedulerService::selectAccount()`.

### **P: Como adicionar validação customizada?**
**R:** Edite `CampaignSchedulerService::shouldSkipContact()`.

---

## 🚀 PRÓXIMAS FEATURES

### **P: Terá interface web?**
**R:** Opcional. Sistema funciona 100% via código/API. Interface pode ser desenvolvida conforme necessidade.

### **P: Terá A/B Testing?**
**R:** Planejado para versão 2.0.

### **P: Terá funis de campanha (drip)?**
**R:** Planejado para versão 2.0 (sequências automáticas).

### **P: Terá import de Excel?**
**R:** Planejado. Por enquanto use CSV ou código direto.

---

## 💡 DICAS PRO

### **1. Teste sempre com volume pequeno primeiro**
```
Teste 1: 2-3 contatos
Teste 2: 10-20 contatos
Teste 3: 100+ contatos
Produção: Escale conforme resultados
```

### **2. Use múltiplas contas desde o início**
```
Mínimo recomendado: 3 contas
Ideal: 5+ contas
Balanceamento perfeito!
```

### **3. Configure janelas de horário comercial**
```
09:00-12:00 e 14:00-18:00
Segunda a Sexta
Maior taxa de resposta!
```

### **4. Monitore as primeiras campanhas**
```
php check-stats.php 1
php check-rotation.php 1

Ajuste cadência conforme necessário
```

### **5. Use variáveis para personalizar**
```
Olá {{primeiro_nome}}!  ← Melhor que "Olá!"
Taxa de resposta: +30%
```

---

## 📞 PRECISA DE MAIS AJUDA?

Consulte a documentação completa:
- **[INICIO_RAPIDO_CAMPANHAS.md](INICIO_RAPIDO_CAMPANHAS.md)**
- **[GUIA_COMPLETO_CAMPANHAS.md](GUIA_COMPLETO_CAMPANHAS.md)**
- **[TESTE_CAMPANHAS_PASSO_A_PASSO.md](TESTE_CAMPANHAS_PASSO_A_PASSO.md)**

---

**Última atualização:** 18/01/2026  
**Versão:** 1.0
