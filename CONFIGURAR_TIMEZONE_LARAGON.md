# 🔧 GUIA: CONFIGURAR TIMEZONE NO LARAGON

**Objetivo**: Configurar timezone permanentemente no PHP do Laragon para evitar problemas futuros.

---

## 📋 PASSO A PASSO

### **1. Abrir php.ini do Laragon**

#### **Opção A - Pelo Menu do Laragon**:
1. Clique com botão direito no ícone do Laragon (bandeja do sistema)
2. Menu → **PHP** → **php.ini**
3. Abrirá o arquivo no editor padrão

#### **Opção B - Manualmente**:
1. Vá até: `C:\laragon\bin\php\php-8.x.x\` (veja sua versão)
2. Abra o arquivo `php.ini` com editor de texto

---

### **2. Localizar a seção [Date]**

Procure por `date.timezone` no arquivo (Ctrl+F):

```ini
[Date]
; Defines the default timezone used by the date functions
; http://php.net/date.timezone
;date.timezone =
```

---

### **3. Descomentar e Configurar**

Remova o `;` (ponto e vírgula) e defina:

```ini
[Date]
; Defines the default timezone used by the date functions
; http://php.net/date.timezone
date.timezone = America/Sao_Paulo
```

**IMPORTANTE**: Não deixe espaços no início da linha!

---

### **4. Salvar e Reiniciar**

1. **Salvar** o arquivo php.ini (Ctrl+S)
2. **Reiniciar** o Laragon:
   - Menu Laragon → **Parar Tudo**
   - Menu Laragon → **Iniciar Tudo**

---

### **5. Verificar se Funcionou**

Execute o script de verificação:

```bash
php check-timezone.php
```

**Resultado esperado**:
```
2. CONFIGURAÇÃO PHP.INI:
   date.timezone: America/Sao_Paulo ✅
```

---

## 🗄️ CONFIGURAR TIMEZONE DO MYSQL

### **1. Abrir my.ini do MariaDB/MySQL**

#### **Pelo Menu do Laragon**:
1. Menu Laragon → **MySQL/MariaDB** → **my.ini**

#### **Manualmente**:
- `C:\laragon\bin\mysql\mysql-x.x.x\my.ini`

---

### **2. Adicionar Configuração**

Procure pela seção `[mysqld]` e adicione:

```ini
[mysqld]
default-time-zone = 'America/Sao_Paulo'
```

**OU** (formato alternativo):

```ini
[mysqld]
default_time_zone = '+03:00'
```

---

### **3. Reiniciar MySQL**

```bash
# Parar MySQL
net stop mysql

# Iniciar MySQL
net start mysql
```

**OU** pelo Laragon:
- Menu → **Parar Tudo**
- Menu → **Iniciar Tudo**

---

### **4. Verificar MySQL Timezone**

Execute no banco de dados:

```sql
SELECT @@global.time_zone, @@session.time_zone;
SELECT NOW() as horario_atual;
```

**Resultado esperado**:
```
@@global.time_zone: America/Sao_Paulo
@@session.time_zone: America/Sao_Paulo
horario_atual: 2026-01-21 09:52:16  (horário de SP)
```

---

## ✅ VERIFICAÇÃO FINAL

### **Script PHP de Teste**:

```php
<?php
// Testar timezone
echo "Timezone: " . date_default_timezone_get() . "\n";
echo "Data/Hora: " . date('d/m/Y H:i:s') . "\n";

// Testar MySQL
$pdo = new PDO('mysql:host=localhost;dbname=chat', 'root', '');
$stmt = $pdo->query("SELECT NOW() as mysql_now");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "MySQL NOW(): " . $result['mysql_now'] . "\n";
?>
```

---

## 🎯 CHECKLIST

- [ ] php.ini configurado com `date.timezone = America/Sao_Paulo`
- [ ] PHP reiniciado (Laragon reiniciado)
- [ ] my.ini configurado com `default-time-zone`
- [ ] MySQL reiniciado
- [ ] Script `check-timezone.php` executado com sucesso
- [ ] Horários das conversas conferidos no sistema
- [ ] Métricas de SLA validadas

---

## 🚨 ATENÇÃO

### **Se Ainda Não Funcionar**:

1. **Verificar múltiplas instalações de PHP**:
   ```bash
   where php
   ```
   - Certifique-se de editar o php.ini correto!

2. **Limpar cache do PHP**:
   - Reiniciar servidor web (Apache/Nginx)
   - Reiniciar PHP-FPM (se usar)

3. **Verificar extensões**:
   ```bash
   php -i | grep timezone
   ```

4. **Forçar no código** (já fizemos isso!):
   - O código já força `America/Sao_Paulo` nos entry points
   - Mesmo com php.ini errado, funcionará

---

## 📞 SUPORTE

Se precisar de ajuda:
1. Verifique logs do PHP: `C:\laragon\logs\`
2. Execute: `php -i > phpinfo.txt` e analise
3. Veja seção `[Date]` no phpinfo

---

**Data**: 21 de Janeiro de 2026  
**Status**: Guia de Configuração
