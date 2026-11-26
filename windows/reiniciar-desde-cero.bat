@echo off
chcp 65001 >nul
cd /d "%~dp0.."
docker info >nul 2>&1
if errorlevel 1 (
  echo  Docker Desktop no esta abierto. Abrelo, espera a que diga "Engine running" y vuelve a intentar.
  pause
  exit /b 1
)
echo.
echo  ATENCION: esto BORRA la base de datos del ERP (alumnos, pagos, usuarios) y la crea de nuevo
echo  con los datos actuales del archivo .env. Los archivos de la carpeta respaldos NO se borran.
echo  Usalo solo en la instalacion inicial o si vas a restaurar un respaldo despues.
echo.
set /p OK=Escribe SI para continuar: 
if /i not "%OK%"=="SI" (
  echo  Cancelado.
  pause
  exit /b 0
)
docker compose down -v
echo.
echo  Listo. Ahora ejecuta iniciar.bat
pause
