#!/bin/bash
# Script para instalar FFmpeg no container Docker

echo "🔄 Atualizando repositórios..."
apt-get update

echo "📦 Instalando FFmpeg..."
apt-get install -y ffmpeg

echo "✅ Verificando instalação..."
if command -v ffmpeg &> /dev/null; then
    echo "✅ FFmpeg instalado com sucesso!"
    echo "📍 Localização: $(which ffmpeg)"
    echo "📊 Versão:"
    ffmpeg -version | head -n 1
else
    echo "❌ Erro ao instalar FFmpeg"
    exit 1
fi

echo ""
echo "✅ Instalação concluída!"

