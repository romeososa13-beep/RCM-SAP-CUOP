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

echo.
echo  RESPALDOS DISPONIBLES:
dir /b /o-d respaldos\*.sql.gz
echo.
echo  ATENCION: restaurar REEMPLAZA todos los datos actuales por los del respaldo.
set /p ARCHIVO=Escribe el nombre exacto del respaldo a restaurar (o deja vacio para cancelar): 
if "%ARCHIVO%"=="" exit /b 0
set /p SEGURO=Escribe SI para confirmar: 
if /i not "%SEGURO%"=="SI" exit /b 0
docker compose exec respaldo bash /respaldo.sh --ahora
docker compose exec respaldo bash /respaldo.sh --restaurar "%ARCHIVO%"
docker compose restart app
echo.
echo  Restauracion terminada (antes se guardo un respaldo de lo que habia).
pause
