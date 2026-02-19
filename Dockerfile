# Imagem base com Apache
FROM php:8.2-apache

# PHP config: logs, limites de upload para mídia (vídeos até 200MB)
RUN printf "log_errors=On\n\
error_reporting=E_ALL\n\
display_errors=Off\n\
error_log=/proc/self/fd/2\n\
upload_max_filesize=256M\n\
post_max_size=256M\n\
memory_limit=512M\n\
max_execution_time=300\n\
max_input_time=300\n" \
> /usr/local/etc/php/conf.d/99-app.ini

# Instala dependências de sistema
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    curl \
    ffmpeg \
    libpq-dev \
 && docker-php-ext-configure gd --with-jpeg --with-freetype --with-webp \
 && docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql gd \
 && a2enmod rewrite

# Ajusta DocumentRoot para /public
RUN sed -i 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/apache2.conf

# Pasta de trabalho
WORKDIR /var/www/html

# Instalar Composer
RUN php -r "copy('https://getcomposer.org/installer','composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && rm composer-setup.php

# Copiar composer files primeiro (para cache do Docker)
COPY composer.json composer.lock ./

# Instalar dependências do Composer
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
 && composer dump-autoload --optimize --no-dev

# Copia o resto do projeto
COPY . .

# Regenerar autoloader após copiar tudo
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Criar diretórios obrigatórios e garantir permissões
RUN mkdir -p storage/cache storage/logs storage/uploads bootstrap/cache \
 && chown -R www-data:www-data /var/www/html \
 && chmod -R 775 storage bootstrap/cache

EXPOSE 80
