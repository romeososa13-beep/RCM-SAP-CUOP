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
echo  Coloca el archivo de saldos en la carpeta "importar". Archivos encontrados:
dir /b importar\*.xlsx
echo.
set /p ARCHIVO=Nombre del archivo de saldos: 
if "%ARCHIVO%"=="" exit /b 0
echo.
echo  === SIMULACION (no guarda nada) ===
docker compose exec app php artisan cobranza:migrar-saldos "/var/www/importar/%ARCHIVO%" --simular
echo.
set /p SEGURO=Si los totales son correctos escribe SI para migrar: 
if /i not "%SEGURO%"=="SI" exit /b 0
docker compose exec app php artisan cobranza:migrar-saldos "/var/www/importar/%ARCHIVO%" --no-interaction
pause
