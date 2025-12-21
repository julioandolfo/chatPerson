#!/bin/bash
# Script para corrigir permissões de diretórios e arquivos

echo "🔧 Corrigindo permissões..."

# Diretório raiz do projeto
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "📁 Projeto: $PROJECT_ROOT"

# Criar diretórios se não existirem
echo "📂 Criando diretórios necessários..."
mkdir -p "$PROJECT_ROOT/logs"
mkdir -p "$PROJECT_ROOT/storage/cache"
mkdir -p "$PROJECT_ROOT/storage/uploads"
mkdir -p "$PROJECT_ROOT/public/uploads"

# Permissões para diretórios de escrita
echo "🔐 Configurando permissões..."

# Logs
chmod -R 777 "$PROJECT_ROOT/logs" 2>/dev/null || echo "⚠️  Não foi possível alterar permissões de logs/"

# Storage
if [ -d "$PROJECT_ROOT/storage" ]; then
    chmod -R 777 "$PROJECT_ROOT/storage" 2>/dev/null || echo "⚠️  Não foi possível alterar permissões de storage/"
fi

# Uploads
chmod -R 777 "$PROJECT_ROOT/public/uploads" 2>/dev/null || echo "⚠️  Não foi possível alterar permissões de public/uploads/"

# Scripts executáveis
echo "⚙️  Tornando scripts executáveis..."
chmod +x "$PROJECT_ROOT/public/scripts/"*.php 2>/dev/null || echo "⚠️  Não foi possível tornar scripts executáveis"
chmod +x "$PROJECT_ROOT/scripts/"*.sh 2>/dev/null || echo "⚠️  Não foi possível tornar scripts shell executáveis"

# Verificar proprietário (apenas se for root)
if [ "$EUID" -eq 0 ]; then
    echo "👤 Ajustando proprietário..."
    
    # Detectar usuário do servidor web
    WEB_USER="www-data"
    if id "nginx" &>/dev/null; then
        WEB_USER="nginx"
    elif id "apache" &>/dev/null; then
        WEB_USER="apache"
    fi
    
    echo "   Usuário web: $WEB_USER"
    
    chown -R "$WEB_USER:$WEB_USER" "$PROJECT_ROOT/logs" 2>/dev/null || echo "⚠️  Não foi possível alterar proprietário de logs/"
    
    if [ -d "$PROJECT_ROOT/storage" ]; then
        chown -R "$WEB_USER:$WEB_USER" "$PROJECT_ROOT/storage" 2>/dev/null || echo "⚠️  Não foi possível alterar proprietário de storage/"
    fi
    
    chown -R "$WEB_USER:$WEB_USER" "$PROJECT_ROOT/public/uploads" 2>/dev/null || echo "⚠️  Não foi possível alterar proprietário de public/uploads/"
else
    echo "ℹ️  Execute como root (sudo) para ajustar proprietário dos arquivos"
fi

echo ""
echo "✅ Permissões corrigidas!"
echo ""
echo "📋 Verificação:"
ls -la "$PROJECT_ROOT/logs" 2>/dev/null || echo "   logs/ não existe"
ls -la "$PROJECT_ROOT/storage" 2>/dev/null | head -5 || echo "   storage/ não existe"
ls -la "$PROJECT_ROOT/public/uploads" 2>/dev/null | head -5 || echo "   public/uploads/ não existe"

echo ""
echo "💡 Dica: Se ainda tiver problemas de permissão, execute:"
echo "   sudo chmod -R 777 $PROJECT_ROOT/logs"
echo "   sudo chown -R www-data:www-data $PROJECT_ROOT/logs"

