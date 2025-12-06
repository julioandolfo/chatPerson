# Configuração para Laragon

## Problema: Listando diretório ao invés de executar aplicação

Se você está vendo o listing de diretórios ao acessar `localhost/chat/`, significa que o servidor não está apontando para a pasta `public/`.

## Solução 1: Acessar diretamente a pasta public

Acesse diretamente:
```
http://localhost/chat/public/
```

## Solução 2: Configurar Virtual Host no Laragon

### Passo a passo:

1. **Abra o Laragon**
2. **Menu → Tools → Quick add → Virtual Host**
3. **Configure:**
   - Domain: `chat.local` (ou outro nome)
   - Path: `C:\laragon\www\chat\public`
4. **Salve e reinicie o Laragon**

Depois acesse:
```
http://chat.local
```

## Solução 3: Usar arquivo .htaccess na raiz

Já foi criado um arquivo `.htaccess` na raiz que redireciona para `public/`.

Se ainda não funcionar, você pode:

1. **Editar o arquivo `.htaccess` na raiz** para ajustar o caminho
2. **Ou criar um Virtual Host** (recomendado)

## Solução 4: Configuração Manual do Apache

Se você quiser configurar manualmente, edite o arquivo de configuração do Apache no Laragon:

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

Depois reinicie o Apache no Laragon.

## Verificação

Após configurar, você deve ver a página de login ao invés do listing de diretórios.

Se ainda estiver vendo o listing, verifique:
- ✅ O arquivo `public/index.php` existe
- ✅ O `.htaccess` está na pasta `public/`
- ✅ O mod_rewrite está habilitado no Apache
- ✅ O Virtual Host está configurado corretamente

