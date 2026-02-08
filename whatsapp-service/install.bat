@echo off
echo ==========================================
echo  WhatsApp Native Service - Instalacao
echo ==========================================
echo.

cd /d "%~dp0"

:: Check Node.js
where node >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Node.js nao encontrado!
    echo.
    echo Instale Node.js 18+ de: https://nodejs.org
    echo Apos instalar, execute este script novamente.
    pause
    exit /b 1
)

echo [OK] Node.js encontrado:
node -v
echo.

:: Install dependencies
echo Instalando dependencias do projeto...
npm install --production
echo.

:: Install PM2 globally
echo Instalando PM2 globalmente...
npm install -g pm2
echo.

:: Create required directories
if not exist "logs" mkdir logs
if not exist "src\store" mkdir src\store
if not exist "media" mkdir media

:: Create .env from example if not exists
if not exist ".env" (
    echo Criando arquivo .env a partir do exemplo...
    copy .env.example .env
    echo.
    echo [IMPORTANTE] Edite o arquivo .env com suas configuracoes!
    echo   - WEBHOOK_URL: URL do webhook do seu PHP app
    echo   - API_TOKEN: Token de seguranca para a API
    echo.
)

echo ==========================================
echo  Instalacao concluida!
echo ==========================================
echo.
echo Para iniciar o servico:
echo   pm2 start ecosystem.config.js
echo   -- ou --
echo   start.bat
echo.
echo Para verificar status:
echo   pm2 status
echo.
echo Para auto-iniciar no boot:
echo   pm2 startup
echo   pm2 save
echo.

pause
