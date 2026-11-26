<?php

namespace Database\Seeders;

use App\Actions\Cobranza\RegistrarPagoAction;
use App\Enums\FormaPago;
use App\Models\Adeudo;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\FolioConsecutivo;
use App\Models\Pago;
use App\Models\PlanPago;
use App\Models\Sede;
use App\Models\User;
use App\Services\Cobranza\MensualidadService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /* ---------------- Catálogos reales (roles y sedes) ---------------- */
        $this->call(CatalogosSeeder::class);

        // Datos de PRUEBA: se activan todas las sedes y carreras para ejercitar el modo multisede.
        // (En producción se usa solo CatalogosSeeder: Loreto activa y las demás inactivas.)
        Sede::query()->update(['activa' => true]);
        Carrera::query()->update(['activa' => true]);

        /* ---------------- Usuarios de prueba ---------------- */

        $admin = $this->usuario('Administrador General', 'admin@cuop.test', 'Administrador');
        $cajera = $this->usuario('María Fernanda López (Caja)', 'caja@cuop.test', 'Cajero');
        $this->usuario('Jorge Ramírez (Control Escolar)', 'escolar@cuop.test', 'Control Escolar');

        /* ---------------- Catálogos ---------------- */
        $lor = Sede::where('clave', 'CUOP-LOR')->firstOrFail();
        $ags = Sede::where('clave', 'CUOP-AGS')->firstOrFail();

        $eduNesc = Carrera::where('nombre', 'LICENCIATURA EN EDUCACIÓN')->firstOrFail();
        $derEsc = Carrera::where('nombre', 'LICENCIATURA EN DERECHO')->firstOrFail();

        // Cada sede tiene sus propios planes (datos de ejemplo; los reales se capturan en Catálogos).
        $planSabatino = PlanPago::updateOrCreate(['nombre' => 'SABATINO 2026', 'sede_id' => $lor->id, 'carrera_id' => null], [
            'monto_mensualidad' => 1850.00, 'monto_recargo' => 185.00, 'dias_tolerancia' => 10,
        ]);
        $planEscolar = PlanPago::updateOrCreate(['nombre' => 'ESCOLARIZADO 2026', 'sede_id' => $ags->id, 'carrera_id' => null], [
            'monto_mensualidad' => 2950.00, 'monto_recargo' => 300.00, 'dias_tolerancia' => 10,
        ]);

        // Los folios continúan la numeración del sistema anterior.
        FolioConsecutivo::updateOrCreate(['serie' => 'PAGOS'], ['ultimo_folio' => 2100, 'longitud' => 5]);

        /* ---------------- Alumnos ---------------- */
        $alumnos = [
            ['CUOP26001', 'GARCÍA HERNÁNDEZ ANA SOFÍA', $lor, $eduNesc, '27-1 LIC-EDUN-NESC-SAB-6A', $planSabatino, 'ACTIVO', null],
            ['CUOP26002', 'MARTÍNEZ LÓPEZ JOSÉ LUIS', $lor, $eduNesc, '27-1 LIC-EDUN-NESC-SAB-6A', $planSabatino, 'ACTIVO', 'CONVENIO: PAGA EN DOS EXHIBICIONES POR MES.'],
            ['CUOP26003', 'RODRÍGUEZ PÉREZ MARÍA GUADALUPE', $ags, $derEsc, '27-1 LIC-DER-ESC-MAT-3B', $planEscolar, 'ACTIVO', null],
            ['CUOP26004', 'SÁNCHEZ CRUZ LUIS ÁNGEL', $ags, $derEsc, '27-1 LIC-DER-ESC-MAT-3B', $planEscolar, 'ACTIVO', null],
            ['CUOP26005', 'TORRES RAMÍREZ DIANA LAURA', $lor, $eduNesc, '26-2 LIC-EDUN-NESC-SAB-4A', $planSabatino, 'BAJA_TEMPORAL', 'BAJA TEMPORAL POR MOTIVOS DE SALUD. REGRESA EN 2027-1.'],
        ];

        foreach ($alumnos as [$mat, $nombre, $sede, $carrera, $grupo, $plan, $estatus, $coment]) {
            Alumno::updateOrCreate(['matricula' => $mat], [
                'nombre_completo' => $nombre, 'sede_id' => $sede->id, 'carrera_id' => $carrera->id,
                'grupo' => $grupo, 'plan_pago_id' => $plan->id, 'estatus' => $estatus, 'comentarios' => $coment,
            ]);
        }

        /* ---------------- Adeudos SEP–DIC 2026 ---------------- */
        $meses = ['SEP' => '2026-09-01', 'OCT' => '2026-10-01', 'NOV' => '2026-11-01', 'DIC' => '2026-12-01'];
        $cargos = [];

        foreach (Alumno::with('planPago')->get() as $alumno) {
            foreach ($meses as $abr => $inicio) {
                // La baja temporal solo generó hasta octubre.
                if ($alumno->estatus->value === 'BAJA_TEMPORAL' && in_array($abr, ['NOV', 'DIC'], true)) {
                    continue;
                }

                $cargos[$alumno->matricula][$abr] = Adeudo::firstOrCreate(
                    ['matricula' => $alumno->matricula, 'concepto' => "MENSUALIDAD {$abr} 2026"],
                    [
                        'tipo' => 'COLEGIATURA',
                        'fecha_vencimiento' => MensualidadService::vencimiento(Carbon::parse($inicio), $alumno->planPago->dias_tolerancia),
                        'monto_original' => $alumno->planPago->monto_mensualidad,
                    ],
                );
            }
        }

        // Inglés: Ana Sofía lo cursa ($350 al mes; recargo del 10%).
        Alumno::whereKey('CUOP26001')->update(['costo_ingles' => 350]);
        foreach (['OCT' => '2026-10-01', 'NOV' => '2026-11-01', 'DIC' => '2026-12-01'] as $abr => $inicio) {
            $cargos['CUOP26001']["ING-{$abr}"] = Adeudo::firstOrCreate(
                ['matricula' => 'CUOP26001', 'concepto' => "INGLÉS {$abr} 2026"],
                ['tipo' => 'INGLES', 'fecha_vencimiento' => MensualidadService::vencimiento(Carbon::parse($inicio), 10), 'monto_original' => 350],
            );
        }

        // Inscripción para un alumno de nuevo ingreso
        $cargos['CUOP26004']['INSC'] = Adeudo::firstOrCreate(
            ['matricula' => 'CUOP26004', 'concepto' => 'INSCRIPCIÓN 2027-1'],
            ['fecha_vencimiento' => '2026-12-15', 'monto_original' => 1200.00],
        );

        /* ---------------- Recargos de meses vencidos ---------------- */
        // Ana Sofía pagó tarde septiembre (con recargo); José Luis y Diana tienen octubre vencido.
        foreach ([['CUOP26001', 'SEP'], ['CUOP26002', 'OCT'], ['CUOP26005', 'OCT'], ['CUOP26004', 'OCT']] as [$m, $abr]) {
            $cargos[$m][$abr]->update(['recargo_aplicado' => Alumno::find($m)->planPago->monto_recargo]);
        }

        /* ---------------- Pagos (totales y abonos parciales) ---------------- */
        if (Pago::query()->exists()) {
            return; // seeder re-ejecutado: no duplicar pagos
        }

        $pagar = function (string $mat, string $abr, float $monto, FormaPago $fp, string $fecha, ?string $ref = null) use ($cargos, $cajera) {
            app(RegistrarPagoAction::class)->execute(
                $cargos[$mat][$abr]->id, $monto, $fp, $cajera, $ref, Carbon::parse($fecha),
            );
        };

        // 1) Ana Sofía: SEP con recargo liquidado, OCT liquidado, NOV abono parcial.
        $pagar('CUOP26001', 'SEP', 1850.00 + 185.00, FormaPago::EFECTIVO, '2026-09-20 10:14');
        $pagar('CUOP26001', 'OCT', 1850.00, FormaPago::TRANSFERENCIA, '2026-10-08 12:30', 'SPEI 8842917730');
        $pagar('CUOP26001', 'NOV', 1000.00, FormaPago::TARJETA, '2026-11-09 09:05', 'AUT 552190');
        $pagar('CUOP26001', 'ING-OCT', 350.00, FormaPago::EFECTIVO, '2026-10-08 10:00');

        // 2) José Luis: SEP en dos exhibiciones; OCT vencido con recargo y un abono.
        $pagar('CUOP26002', 'SEP', 925.00, FormaPago::EFECTIVO, '2026-09-05 11:00');
        $pagar('CUOP26002', 'SEP', 925.00, FormaPago::EFECTIVO, '2026-09-10 11:20');
        $pagar('CUOP26002', 'OCT', 500.00, FormaPago::DEPOSITO, '2026-11-02 13:45', 'FICHA 004512');

        // 3) María Guadalupe: al corriente hasta noviembre.
        $pagar('CUOP26003', 'SEP', 2950.00, FormaPago::TRANSFERENCIA, '2026-09-04 08:50', 'SPEI 7712093381');
        $pagar('CUOP26003', 'OCT', 2950.00, FormaPago::TRANSFERENCIA, '2026-10-03 08:47', 'SPEI 7712555402');
        $pagar('CUOP26003', 'NOV', 2950.00, FormaPago::TARJETA, '2026-11-05 17:22', 'AUT 118804');

        // 4) Luis Ángel: SEP pagado, OCT con recargo pendiente, abono a inscripción.
        $pagar('CUOP26004', 'SEP', 2950.00, FormaPago::EFECTIVO, '2026-09-11 10:00');
        $pagar('CUOP26004', 'INSC', 600.00, FormaPago::EFECTIVO, '2026-11-23 09:40');

        // 5) Diana Laura (baja temporal): SEP pagado parcialmente, OCT vencido.
        $pagar('CUOP26005', 'SEP', 1200.00, FormaPago::EFECTIVO, '2026-09-15 16:10');

        $this->command?->info('Usuarios: admin@cuop.test / caja@cuop.test / escolar@cuop.test — contraseña: password');
    }

    private function usuario(string $nombre, string $email, string $rol): User
    {
        $u = User::updateOrCreate(['email' => $email], [
            'name' => $nombre,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $u->syncRoles([$rol]);

        return $u;
    }
}
