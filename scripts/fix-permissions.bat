@echo off
REM Script para Windows - Criar diretórios necessários

echo Corrigindo estrutura de diretorios no Windows...

cd /d "%~dp0.."

echo Criando diretorios necessarios...
if not exist "logs" mkdir logs
if not exist "storage\cache" mkdir storage\cache
if not exist "storage\uploads" mkdir storage\uploads
if not exist "public\uploads" mkdir public\uploads

echo.
echo Diretorios criados com sucesso!
echo.
echo Estrutura:
dir /b logs 2>nul
dir /b storage 2>nul
dir /b public\uploads 2>nul

echo.
echo Concluido! No Windows, as permissoes sao gerenciadas automaticamente.
pause

