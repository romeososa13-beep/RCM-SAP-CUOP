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

if not exist ".env" (
  copy "docker\.env.ejemplo" ".env" >nul
  echo.
  echo  Se creo el archivo de configuracion .env
  echo  Cambia las contrasenas y tu correo, GUARDA y CIERRA el Bloc de notas para continuar.
  echo.
  notepad ".env"
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
