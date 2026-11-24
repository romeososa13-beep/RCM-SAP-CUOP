# ERP CUOP · Control Escolar y Cobranza

Laravel 13 · Livewire 3 · Alpine.js · Tailwind CSS 3 · MySQL 8 (InnoDB)

## Requisitos

PHP 8.3+ (extensiones `pdo_mysql`, `mbstring`, `gd`, `intl`, `zip`, `xml`), Composer 2,
Node 20+ y MySQL 8.0+ con `utf8mb4_unicode_ci`.

> El proyecto se inició en Laravel 11 y se actualizó a **Laravel 13** porque la rama 11.x
> ya no recibe parches de seguridad (5 avisos abiertos en 11.57.1).

## Instalación

```bash
git clone https://github.com/romeososa13-beep/RCM-SAP-CUOP.git erp-cuop && cd erp-cuop
composer install
cp .env.example .env && php artisan key:generate
```

Cree la base de datos y configure `.env`:

```sql
CREATE DATABASE erp_cuop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```


```dotenv
APP_TIMEZONE=America/Mexico_City
APP_LOCALE=es
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp_cuop
DB_USERNAME=...
DB_PASSWORD=...
```

```bash
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Usuarios de prueba (contraseña `password`): `admin@cuop.test` (Administrador),
`caja@cuop.test` (Cajero), `escolar@cuop.test` (Control Escolar).

Cron del servidor (ejecuta recargos diarios y mensualidades el día 1):

```cron
* * * * * cd /ruta/erp-cuop && php artisan schedule:run >> /dev/null 2>&1
```

## Estructura

```
app/
├── Actions/Cobranza/        RegistrarPagoAction, CancelarPagoAction   ← lógica financiera transaccional
├── Services/Cobranza/       FolioService (lockForUpdate), RecargoService, MensualidadService
├── Services/Padron/         ImportarAlumnosService (previsualizar → importar)
├── Services/Reportes/       CorteCajaService
├── Console/Commands/        cobranza:aplicar-recargos, cobranza:generar-mensualidad
├── Enums/                   EstatusAlumno, FormaPago
├── Imports/ Exports/        Maatwebsite\Excel
├── Http/Controllers/        ReciboPagoController (DomPDF 80 mm / media carta), CorteCajaExportController
├── Livewire/Caja/           EstadoCuenta
├── Livewire/Alumnos/        PadronAlumnos
├── Livewire/Reportes/       CorteCaja, CarteraVencida
└── Models/                  Sede, Carrera, PlanPago, Alumno, Adeudo, Pago, FolioConsecutivo
database/migrations/         sedes, carreras, planes_pago, alumnos, adeudos, pagos (+ folios_consecutivos)
resources/views/livewire/    vistas Blade + Tailwind
resources/views/pdf/         recibo.blade.php, corte-caja.blade.php
tests/Feature/CobranzaTest   9 pruebas de punta a punta
```

## Comandos

```bash
php artisan cobranza:aplicar-recargos [--fecha=2026-11-23] [--simular]
php artisan cobranza:generar-mensualidad [--mes=2026-12] [--simular]
php artisan test                                              # SQLite en memoria
DB_CONNECTION=mysql DB_DATABASE=erp_cuop_test php artisan test  # contra MySQL
```

## Reglas de negocio implementadas

- `monto_original` nunca se modifica; `saldo_pendiente` = original + recargo − pagos vigentes.
- Cobro en `DB::transaction` con bloqueo del adeudo y del consecutivo de folios
  (`folios_consecutivos` + `lockForUpdate()`): sin folios duplicados ni huecos, y sin
  dobles cobros si dos cajas atienden al mismo alumno. Reintenta ante *deadlocks*.
- Aritmética monetaria en centavos (enteros) para evitar errores de punto flotante.
- Transferencia y Depósito exigen referencia.
- Cancelación solo por Administrador: *soft delete* con motivo y usuario; el folio no se
  reutiliza, el recibo reimpreso sale marcado **CANCELADO** y el adeudo se reabre.
- Recargos: un solo recargo por adeudo, idempotente y seguro ante ejecuciones simultáneas.
- Mensualidades: solo alumnos ACTIVOS; vencimiento = día 1 + `dias_tolerancia`; no duplica.
- Importación: se re-analiza el archivo en el servidor al confirmar (no se confía en el preview).
