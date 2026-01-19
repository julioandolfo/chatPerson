# ⚠️ URGENTE: Corrigir Ordem das Etapas

## 🎯 Problema

A **ordem das etapas do funil estava mudando sozinha**.

## 💡 Causa

Código no `FunnelService.php` **reordenava automaticamente TODAS as etapas** sempre que:
- Alguém tentava mover uma etapa ↑↓
- Existia pelo menos UMA etapa com `stage_order = NULL`

## ✅ Solução (3 Passos)

### 1️⃣ Execute o Script SQL ⭐ OBRIGATÓRIO

**Arquivo:** `CORRIGIR_ORDEM_ETAPAS_DEFINITIVO.sql`

```bash
# phpMyAdmin
1. Abra phpMyAdmin
2. Selecione o banco
3. Vá em "SQL"  
4. Cole o conteúdo do arquivo
5. Execute

# OU Terminal
mysql -u root -p nome_do_banco < CORRIGIR_ORDEM_ETAPAS_DEFINITIVO.sql
```

**O que faz:**
- Define `stage_order` para TODAS as etapas
- Corrige valores NULL, 0 ou duplicados
- Garante ordem: Entrada → Suas etapas → Fechadas → Perdidas

### 2️⃣ Código já foi corrigido ✅

O arquivo `app/Services/FunnelService.php` foi modificado:
- ❌ **Antes:** Reordenava tudo automaticamente
- ✅ **Depois:** Lança erro se encontrar NULL

### 3️⃣ Limpe o Cache

```bash
# Navegador
Ctrl + Shift + Del

# Redis (se usar)
redis-cli FLUSHALL

# Memcached (se usar)
echo 'flush_all' | nc localhost 11211
```

## 🧪 Teste

1. Acesse o Kanban
2. Clique em "Ordenar Etapas"
3. Mova uma etapa
4. Clique em "Salvar"
5. Recarregue a página
6. ✅ A ordem deve permanecer como você definiu

## ⏰ Tempo Estimado

- Executar SQL: 30 segundos
- Limpar cache: 10 segundos
- Testar: 1 minuto

**Total:** ~2 minutos

## 📋 Checklist

- [ ] Script SQL executado
- [ ] Código já está corrigido (verificar data do arquivo)
- [ ] Cache limpo
- [ ] Testado no Kanban
- [ ] Ordem permanece após recarregar

## ❓ Precisa de Ajuda?

**Se a ordem ainda mudar:**

1. Verifique se o SQL foi executado:
```sql
SELECT COUNT(*) FROM funnel_stages 
WHERE stage_order IS NULL OR stage_order = 0;
-- Deve retornar: 0
```

2. Veja os logs:
```bash
tail -f /var/log/php/error.log
```

3. Verifique o arquivo modificado:
```bash
ls -la app/Services/FunnelService.php
# Data deve ser 18/01/2026 ou posterior
```

## 📚 Documentação Completa

Para entender o problema em detalhes:
- `PROBLEMA_ORDEM_ETAPAS_MUDANDO_SOZINHA.md`

---

**Status:** ✅ Solução pronta  
**Urgência:** ⚠️ ALTA  
**Ação:** Execute o script SQL agora!  
**Data:** 18/01/2026
