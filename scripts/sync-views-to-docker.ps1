# Script PowerShell para sincronizar views para o container Docker
# Uso: .\scripts\sync-views-to-docker.ps1 [container_name]

param(
    [string]$ContainerName = "chat-app"
)

Write-Host "🔄 Sincronizando views para o container Docker..." -ForegroundColor Cyan
Write-Host "Container: $ContainerName" -ForegroundColor Yellow

# Verificar se o Docker está disponível
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Write-Host "❌ Docker não está disponível no PATH." -ForegroundColor Red
    Write-Host "Por favor, instale o Docker ou adicione-o ao PATH." -ForegroundColor Yellow
    exit 1
}

# Verificar se o container existe
$containerExists = docker ps -a --format '{{.Names}}' | Select-String -Pattern "^${ContainerName}$"
if (-not $containerExists) {
    Write-Host "❌ Container '$ContainerName' não encontrado." -ForegroundColor Red
    Write-Host "Containers disponíveis:" -ForegroundColor Yellow
    docker ps -a --format '{{.Names}}'
    exit 1
}

# Verificar se o container está rodando
$containerRunning = docker ps --format '{{.Names}}' | Select-String -Pattern "^${ContainerName}$"
if (-not $containerRunning) {
    Write-Host "⚠️  Container não está rodando. Iniciando..." -ForegroundColor Yellow
    docker start $ContainerName
    Start-Sleep -Seconds 2
}

# Verificar se o arquivo existe localmente
$localFile = "views\logs\index.php"
if (-not (Test-Path $localFile)) {
    Write-Host "❌ Arquivo local não encontrado: $localFile" -ForegroundColor Red
    exit 1
}

# Copiar arquivo para o container
Write-Host "📁 Copiando views/logs/index.php..." -ForegroundColor Cyan
docker cp "$localFile" "${ContainerName}:/var/www/html/views/logs/index.php"

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Arquivo copiado com sucesso!" -ForegroundColor Green
} else {
    Write-Host "❌ Erro ao copiar arquivo" -ForegroundColor Red
    exit 1
}

Write-Host "✨ Sincronização concluída!" -ForegroundColor Green

