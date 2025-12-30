# Solução: Erro "vendor/autoload.php não encontrado"

## Problema

O script está dando erro porque o arquivo `vendor/autoload.php` não existe no servidor.

## Causa

O diretório `vendor/` não foi criado ou não foi enviado para o servidor. Isso acontece porque o `vendor/` geralmente não deve ser versionado no Git (está no `.gitignore`).

## Solução

### Opção 1: Executar composer install no servidor (RECOMENDADO)

```bash
# Acesse o servidor via SSH
ssh usuario@servidor

# Vá para o diretório do projeto
cd /var/www/html

# Execute composer install
composer install --no-dev --optimize-autoloader
```

**Nota:** Se não tiver acesso SSH, peça ao administrador do servidor para executar isso.

### Opção 2: Enviar vendor/ para o servidor (NÃO RECOMENDADO)

Se não puder executar composer no servidor, você pode enviar o diretório `vendor/` do seu ambiente local, mas isso não é recomendado porque:
- O `vendor/` pode ter dependências específicas do sistema operacional
- Pode causar problemas de compatibilidade

### Opção 3: Usar o script sem vendor (se não usar dependências externas)

Se o projeto não usa dependências do Composer, você pode modificar o script para não carregar o vendor. Mas isso geralmente não é o caso.

## Verificar se funcionou

Após executar `composer install`, teste novamente:

```bash
php /var/www/html/public/check-availability.php
```

Ou via HTTP:
```
https://seudominio.com/check-availability.php
```

## Dependências necessárias

Verifique se o servidor tem:
- PHP 8.1 ou superior
- Composer instalado
- Extensões PHP necessárias (PDO, MySQL, etc)

## Comandos úteis

```bash
# Verificar se composer está instalado
composer --version

# Verificar versão do PHP
php -v

# Verificar extensões PHP
php -m

# Instalar dependências
composer install

# Atualizar dependências
composer update

# Verificar autoload
composer dump-autoload
```

## Se ainda não funcionar

1. Verifique os logs do PHP: `/var/log/php-errors.log`
2. Verifique permissões: `chmod 755 /var/www/html`
3. Verifique se o diretório vendor foi criado: `ls -la /var/www/html/vendor`
4. Verifique se o composer.json existe: `cat /var/www/html/composer.json`

