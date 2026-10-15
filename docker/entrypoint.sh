#!/bin/sh
# Entrypoint do container: atualiza o banco (migrations automáticas) e sobe o Apache.
# Falha nas migrations não impede o site de subir — o erro fica no log do deploy.

php -d variables_order=EGPCS /var/www/html/database/auto_migrate.php \
    || echo "[entrypoint] Atenção: migrations automáticas não concluídas (ver log acima)."

exec docker-php-entrypoint "$@"
