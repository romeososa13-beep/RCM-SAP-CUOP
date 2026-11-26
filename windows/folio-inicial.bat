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
set /p FOLIO=Ultimo folio usado en INTELL (solo numero, ej. 2500): 
if "%FOLIO%"=="" exit /b 0
docker compose exec app php artisan tinker --execute="App\Models\FolioConsecutivo::updateOrCreate(['serie'=>'PAGOS'],['ultimo_folio'=>(int)'%FOLIO%','longitud'=>5]); echo 'El siguiente recibo sera el folio '.str_pad((string)((int)'%FOLIO%'+1),5,'0',STR_PAD_LEFT);"
echo.
pause
