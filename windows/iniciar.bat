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

if not exist ".env" copy "docker\.env.ejemplo" ".env" >nul
rem No arrancar hasta que el .env tenga datos propios (el Bloc de notas de Windows 11 no espera a que se cierre).
:revisar_env
findstr /c:"CAMBIAR_" /c:"tu_correo@" ".env" >nul
if not errorlevel 1 (
  echo.
  echo  Falta configurar el archivo .env (todavia tiene datos de ejemplo).
  echo  1. En el Bloc de notas cambia las contrasenas DB_PASSWORD, DB_ROOT_PASSWORD y ADMIN_PASSWORD,
  echo     y tu correo en ADMIN_EMAIL.
  echo  2. GUARDA con Ctrl+S ^(el punto junto al nombre de la pestana debe desaparecer^).
  echo  3. Regresa a esta ventana y presiona una tecla.
  echo.
  start "" notepad ".env"
  pause
  goto revisar_env
)
if not exist "respaldos" mkdir respaldos
if not exist "importar" mkdir importar
echo.
echo  Iniciando el ERP (la primera vez tarda varios minutos)...
docker compose up -d --build
if errorlevel 1 (
  echo.
  echo  Hubo un problema al iniciar. Revisa el mensaje de arriba.
  pause
  exit /b 1
)
echo  Esperando a que el sistema este listo...
for /l %%i in (1,1,60) do (
  docker compose logs app 2>nul | findstr /c:"ERP listo" >nul && goto listo
  timeout /t 3 >nul
)
:listo
start "" http://localhost:8080
echo.
echo  Listo. El ERP esta en http://localhost:8080
echo  Puedes cerrar esta ventana; el sistema sigue funcionando.
echo.
pause
