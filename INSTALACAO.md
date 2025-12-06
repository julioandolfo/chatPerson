# GUIA DE INSTALAÇÃO

## Pré-requisitos

- PHP 8.1 ou superior
- MySQL 8.0 ou superior
- Servidor web (Apache/Nginx) ou Laragon
- Extensões PHP: PDO, PDO_MySQL, mbstring, json

## Passo a Passo

### 1. Configurar Banco de Dados

Edite o arquivo `config/database.php` ou crie um arquivo `.env`:

```php
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=chat_multiatendimento
DB_USERNAME=root
DB_PASSWORD=
```

### 2. Executar Migrations

```bash
php scripts/migrate.php
```

Isso criará todas as tabelas necessárias no banco de dados.

### 3. Executar Seeds

```bash
php scripts/seed.php
```

Isso criará o usuário admin padrão:
- **Email**: admin@example.com
- **Senha**: admin123

### 4. Copiar Arquivos do Metronic

```bash
php scripts/copy-metronic.php
```

Isso copiará os arquivos CSS/JS necessários do Metronic para `public/assets/`.

### 5. Configurar Servidor Web

#### Laragon
- O projeto já está configurado para rodar no Laragon
- Acesse: http://localhost/chat

#### Apache
Configure o DocumentRoot para apontar para a pasta `public/`:

```apache
<VirtualHost *:80>
    ServerName chat.local
    DocumentRoot "C:/laragon/www/chat/public"
    <Directory "C:/laragon/www/chat/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx
```nginx
server {
    listen 80;
    server_name chat.local;
    root C:/laragon/www/chat/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 6. Acessar o Sistema

1. Acesse: http://localhost/chat
2. Faça login com:
   - Email: admin@example.com
   - Senha: admin123

## Estrutura Criada

Após a instalação, você terá:

- ✅ Estrutura de diretórios completa
- ✅ Sistema de rotas funcionando
- ✅ Autenticação básica
- ✅ Layout base Metronic
- ✅ Banco de dados configurado
- ✅ Usuário admin criado

## Próximos Passos

1. **Copiar arquivos do Metronic**: Execute `php scripts/copy-metronic.php`
2. **Personalizar layout**: Edite arquivos em `views/layouts/metronic/`
3. **Adicionar funcionalidades**: Siga a documentação em `CONTEXT_IA.md`
4. **Configurar WhatsApp**: Veja documentação de integração

## Troubleshooting

### Erro de conexão com banco
- Verifique se o MySQL está rodando
- Verifique as credenciais em `config/database.php`
- Certifique-se de que o banco existe

### Erro 404 nas rotas
- Verifique se o `.htaccess` está configurado (Apache)
- Verifique se o servidor está apontando para `public/`
- Verifique se o mod_rewrite está habilitado

### CSS/JS não carregam
- Execute `php scripts/copy-metronic.php`
- Verifique se os arquivos existem em `public/assets/`
- Verifique permissões de arquivos

## Suporte

Consulte a documentação:
- `CONTEXT_IA.md` - Contexto completo
- `ARQUITETURA.md` - Arquitetura técnica
- `README.md` - Visão geral

