#!/bin/bash
# Script para sincronizar views para o container Docker
# Uso: ./scripts/sync-views-to-docker.sh [container_name]

CONTAINER_NAME="${1:-chat-app}"

echo "🔄 Sincronizando views para o container Docker..."
echo "Container: $CONTAINER_NAME"

# Verificar se o container existe
if ! docker ps -a --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo "❌ Container '$CONTAINER_NAME' não encontrado."
    echo "Containers disponíveis:"
    docker ps -a --format '{{.Names}}'
    exit 1
fi

# Verificar se o container está rodando
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo "⚠️  Container não está rodando. Iniciando..."
    docker start "$CONTAINER_NAME"
    sleep 2
fi

# Copiar diretório de views
echo "📁 Copiando views/logs/..."
docker cp views/logs/index.php "${CONTAINER_NAME}:/var/www/html/views/logs/index.php"

if [ $? -eq 0 ]; then
    echo "✅ Arquivo copiado com sucesso!"
else
    echo "❌ Erro ao copiar arquivo"
    exit 1
fi

echo "✨ Sincronização concluída!"

