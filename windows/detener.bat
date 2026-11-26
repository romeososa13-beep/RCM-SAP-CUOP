@echo off
chcp 65001 >nul
cd /d "%~dp0.."
docker info >nul 2>&1
if errorlevel 1 (
  echo.
  echo  Docker Desktop no esta abierto. Abrelo, espera a que diga "Engine running" y vuelve a intentar.
  echo.
  pause
  exit /b 1
)

docker compose stop
echo.
echo  ERP detenido. Tus datos quedan guardados. Para volver a usarlo: iniciar.bat
pause
