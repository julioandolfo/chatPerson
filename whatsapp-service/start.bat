@echo off
echo ==========================================
echo  WhatsApp Native Service - Start
echo ==========================================
echo.

cd /d "%~dp0"

:: Check if Node.js is installed
where node >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Node.js nao encontrado! Instale Node.js 18+
    echo Download: https://nodejs.org
    pause
    exit /b 1
)

:: Check Node.js version
for /f "tokens=1,2 delims=." %%a in ('node -v') do (
    set "NODE_VER=%%a"
)
echo Node.js: %NODE_VER%

:: Install dependencies if needed
if not exist "node_modules" (
    echo.
    echo Instalando dependencias...
    npm install --production
    echo.
)

:: Create logs directory
if not exist "logs" mkdir logs

:: Check if PM2 is available
where pm2 >nul 2>nul
if errorlevel 1 (
    echo [INFO] PM2 nao encontrado, iniciando diretamente...
    echo [INFO] Para gerenciamento avancado, instale PM2: npm install -g pm2
    echo.
    node src/index.js
) else (
    echo [INFO] Iniciando com PM2...
    pm2 start ecosystem.config.js
    pm2 logs whatsapp-service --lines 20
)

pause
