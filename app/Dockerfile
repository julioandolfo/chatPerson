# Imagem base com Apache
FROM php:8.2-apache

# Instala dependências de sistema
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    curl \
 && docker-php-ext-install pdo pdo_mysql \
 && a2enmod rewrite

# Ajusta DocumentRoot para /public
RUN sed -i 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/apache2.conf

# Pasta de trabalho
WORKDIR /var/www/html

# (Opcional, mas bom) copia composer e instala deps
COPY composer.json composer.lock* ./

RUN php -r "copy('https://getcomposer.org/installer','composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && rm composer-setup.php \
 && composer install --no-dev --no-interaction --prefer-dist --no-progress || true

# Copia o resto do projeto
COPY . .

# Permissões (se for tipo Laravel, funciona; se não for, ignora com || true)
RUN chown -R www-data:www-data /var/www/html \
 && (find storage -type d -print0 2>/dev/null | xargs -0 chmod 775 || true) \
 && (find bootstrap/cache -type d -print0 2>/dev/null | xargs -0 chmod 775 || true)

EXPOSE 80