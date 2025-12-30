# Guia: Qual arquivo usar no Cron?

## Resumo

- **`check-availability.php`** → Use para cron no servidor (CLI)
- **`cron-availability.php`** → Use para cron HTTP externo (ex: cron-job.org)

---

## 1. `check-availability.php` - Script CLI (Recomendado)

### Quando usar:
- ✅ Cron direto no servidor (crontab)
- ✅ Windows Task Scheduler
- ✅ Execução via linha de comando
- ✅ Melhor para logs e debug

### Como configurar:

#### Linux (crontab):
```bash
# Editar crontab
crontab -e

# Adicionar linha (executa a cada 5 minutos)
*/5 * * * * php /caminho/completo/public/check-availability.php >> /var/log/availability-cron.log 2>&1
```

#### Windows (Task Scheduler):
1. Abra o Task Scheduler
2. Crie uma nova tarefa
3. Configure para executar:
   ```
   php C:\laragon\www\chat\public\check-availability.php
   ```
4. Configure para executar a cada 5 minutos

### Vantagens:
- ✅ Saída detalhada no console
- ✅ Fácil debug
- ✅ Logs completos
- ✅ Não depende de servidor web

---

## 2. `cron-availability.php` - Endpoint HTTP

### Quando usar:
- ✅ Serviços externos de cron (cron-job.org, EasyCron, etc)
- ✅ Quando não tem acesso ao crontab do servidor
- ✅ Quando precisa chamar via URL

### Como configurar:

#### No cron-job.org ou similar:
1. Acesse o serviço de cron HTTP
2. Configure a URL:
   ```
   https://seudominio.com/cron-availability.php
   ```
3. Configure para executar a cada 5 minutos
4. Método: GET

### Vantagens:
- ✅ Funciona sem acesso ao servidor
- ✅ Retorna JSON para monitoramento
- ✅ Pode ser chamado de qualquer lugar

### Desvantagens:
- ⚠️ Depende do servidor web estar funcionando
- ⚠️ Menos detalhes nos logs

---

## Recomendação

**Use `check-availability.php`** se você tem acesso ao servidor. É mais confiável e fornece melhor feedback.

Use `cron-availability.php` apenas se precisar de um serviço externo de cron HTTP.

---

## Testando

### Testar check-availability.php:
```bash
php public/check-availability.php
```

### Testar cron-availability.php:
```bash
curl https://seudominio.com/cron-availability.php
```

Ou acesse no navegador: `https://seudominio.com/cron-availability.php`

---

## Troubleshooting

### Erro 500 em cron-availability.php:

1. **Verifique os logs do PHP:**
   ```bash
   tail -f /var/log/php-errors.log
   ```

2. **Verifique se o autoload está funcionando:**
   - Confirme que `vendor/autoload.php` existe
   - Confirme que `app/Helpers/autoload.php` existe

3. **Verifique permissões:**
   ```bash
   chmod 644 public/cron-availability.php
   ```

4. **Teste diretamente:**
   ```bash
   php public/cron-availability.php
   ```

### Erro ao executar check-availability.php:

1. **Verifique o caminho do PHP:**
   ```bash
   which php
   # Use o caminho completo no cron
   ```

2. **Verifique permissões:**
   ```bash
   chmod +x public/check-availability.php
   ```

3. **Teste manualmente:**
   ```bash
   php public/check-availability.php
   ```

---

## Frequência Recomendada

Execute a cada **5 minutos** (`*/5 * * * *`).

Isso garante que os agentes sejam verificados regularmente sem sobrecarregar o servidor.

