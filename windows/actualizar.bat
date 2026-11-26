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

echo  Actualizando el ERP con la version descargada (tus datos se conservan)...
docker compose exec respaldo bash /respaldo.sh --ahora
docker compose up -d --build
echo.
echo  Actualizacion terminada.
pause
