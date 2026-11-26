# ERP CUOP · Control Escolar y Cobranza

Laravel 13 · Livewire 3 · Alpine.js · Tailwind CSS 3 · MySQL 8 (InnoDB)

> **Producción:** siga [`docs/INSTALACION.md`](docs/INSTALACION.md) (VPS o cPanel, HTTPS, cola,
> cron, respaldos, correo y WhatsApp).

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

**En producción** no use `--seed` (crea alumnos y pagos de prueba). Cargue solo los catálogos
reales (roles, 5 sedes, 15 carreras y 62 planes de pago por sede, tomados de INTELL):

```bash
php artisan migrate --force
php artisan db:seed --class=CatalogosSeeder --force
```

Luego entre como Administrador, cree los usuarios reales en **Usuarios** y cargue el padrón
en **Padrón de alumnos › Importar Excel / CSV** (se lee la primera hoja del libro).

### Migración de saldos del sistema anterior (una sola vez)

```bash
php artisan cobranza:migrar-saldos saldos.xlsx --simular   # valida y muestra el resumen
php artisan cobranza:migrar-saldos saldos.xlsx             # migra
```

Layout: `MATRICULA, CONCEPTO, TIPO, VENCIMIENTO, MONTO, RECARGO, PAGADO_PREVIO, EXENTO_RECARGO`.
Lo abonado antes queda en `pagado_previo` (no crea pagos ni folios ni entra al corte de caja).
Un cargo ya pagado en el sistema anterior se registra como liquidado, para que el generador
mensual no lo vuelva a cobrar. Es idempotente: volver a correrlo omite lo que ya existe.

Usuarios de prueba creados por `--seed` (contraseña `password`): `admin@cuop.test` (Administrador),
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
├── Livewire/Reportes/       Tablero (inicio del Administrador), CorteCaja, CarteraVencida, BloqueoPlataformas
├── Livewire/WhatsApp/       Bandeja (conversaciones; responde el personal)
├── Services/WhatsApp/       Cloud API de Meta, plantillas, chatbot, webhook
├── Livewire/Admin/          Usuarios (alta, rol, contraseña, desactivar), Catalogos (sedes, carreras, planes), BitacoraCambios
├── Livewire/Auth/           Login, MiCuenta (cambio de contraseña)
└── Models/                  Sede, Carrera, PlanPago, Alumno, Adeudo, Pago, FolioConsecutivo
database/migrations/         sedes, carreras, planes_pago, alumnos, adeudos, pagos (+ folios_consecutivos)
resources/views/livewire/    vistas Blade + Tailwind
resources/views/pdf/         recibo.blade.php, corte-caja.blade.php
tests/Feature/               85 pruebas (cobranza, administración, planes, reglas, migración, tablero, bitácora, becas, ingresos, correo, WhatsApp)
scripts/prueba-concurrencia.sh   cobros simultáneos contra MySQL (solo base de pruebas)
```

## Comandos

```bash
php artisan cobranza:aplicar-recargos [--fecha=2026-11-23] [--simular]
php artisan cobranza:generar-mensualidad [--mes=2026-12] [--simular]
php artisan test                                              # SQLite en memoria
DB_CONNECTION=mysql DB_DATABASE=erp_cuop_test php artisan test  # contra MySQL
```

## Pruebas automáticas (CI)

Cada push a `main` y cada pull request corre en GitHub Actions (`.github/workflows/pruebas.yml`):
formato con Pint, `composer audit` y la suite completa contra **MySQL 8** y **SQLite**.

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
- Cargos manuales (inscripción, constancias…) desde el estado de cuenta, con registro de quién
  los capturó; solo un Administrador puede eliminarlos y solo si nunca tuvieron pagos.
- **Planes de pago por sede**: cada plan pertenece a una sede (y opcionalmente a una carrera).
  Un alumno solo puede tener un plan de su sede/carrera; el formulario solo ofrece esos planes.
  En el Excel, `PLAN_PAGO` acepta el ID o el nombre: el nombre se busca solo dentro de la sede
  del alumno, así que dos sedes pueden tener un plan llamado igual con montos distintos.
- **Calendario de pago** (`config/cobranza.php`):
  del 1 al 10 colegiatura según plan; desde el 11 recargo ($180 fijo en colegiatura, 10% del
  costo en inglés; los cargos «Otro» no llevan recargo); desde el 16 **bloqueo de plataformas**
  para quien deba colegiatura o inglés (reporte «Bloqueos», exportable a Excel). El bloqueo
  se libera al liquidar esos conceptos. El sistema indica a quién bloquear; el bloqueo en
  cada plataforma lo aplica quien la administra.
- Inglés: costo mensual por alumno (`costo_ingles`, columna `INGLES` del Excel); se genera
  junto con la colegiatura.
- Teléfonos del alumno (celular, tutor, alterno): en el padrón, en caja y en el reporte de bloqueos.
- El plan de pago es obligatorio solo para alumnos ACTIVOS; bajas y egresados pueden no tenerlo.
- **Bitácora** (Administración › Bitácora): registra quién, cuándo, desde qué IP y qué cambió
  (antes → después) en alumnos, cargos, cancelaciones de pagos, catálogos, usuarios y roles,
  más un resumen de cada importación, migración, recargos y mensualidades automáticas.
  Es de solo escritura y nunca guarda contraseñas. Cada alumno muestra su historial en el padrón.
- **Becas:** porcentaje por alumno (solo Administrador) que se descuenta al generar la colegiatura
  y, si se indica, el inglés. **Condonar recargo:** solo Administrador, con motivo; queda en bitácora.
- **Reporte de ingresos:** cobrado en caja por concepto, forma de pago, sede y día; Excel.
- **Recibo por correo:** al cobrar o desde el historial, con el PDF adjunto (en segundo plano).
- **WhatsApp Business:** avisos con plantillas (recordatorio día 8, recargo día 11, bloqueo día 16,
  confirmación de pago), aviso masivo desde Bloqueos, bandeja de conversaciones y chatbot por
  reglas (saldo, fechas, cómo pagar, pase a una persona). Respeta BAJA/ALTA. Opcional: asistente con
  IA (Claude) para preguntas libres, solo con los datos del alumno de ese número. Ver docs/INSTALACION.md §9.
- **Cierre del corte de caja:** el cajero cierra su día; después no cobra ni se cancelan pagos de ese
  día hasta que un Administrador reabra el corte con motivo.
- **Datos de la institución** (Administración): nombre, RFC, dirección, teléfono, leyenda y logo en
  recibos, corte y correo.
- **Sedes y carreras activas:** el sistema arranca solo en Loreto; las demás sedes y carreras quedan
  registradas pero inactivas y se activan en Catálogos cuando se sumen.
- **Calendario confirmado:** pago del 1 al 10; recargo desde el 11; bloqueo de plataformas desde el 16.
- **Facturación:** fuera del alcance (los recibos son internos; el CFDI lo emite otro sistema).
- **Estatus PREINSCRITO:** no genera mensualidad ni bloqueo y no exige plan (no es una baja).
- Usuarios: no se borran, se desactivan (pierden la sesión al instante). Un administrador
  no puede quitarse su propio rol ni desactivarse.
- Importación: se re-analiza el archivo en el servidor al confirmar (no se confía en el preview).
