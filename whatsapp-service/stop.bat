@echo off
echo ==========================================
echo  WhatsApp Native Service - Stop
echo ==========================================
echo.

where pm2 >nul 2>nul
if errorlevel 1 (
    echo [INFO] PM2 nao encontrado. Matando processo Node...
    taskkill /f /im node.exe /fi "WINDOWTITLE eq whatsapp-service" 2>nul
    echo Servico parado.
) else (
    pm2 stop whatsapp-service
    echo Servico parado via PM2.
)

pause
