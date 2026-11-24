<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\PlanPago;
use App\Models\Sede;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Catálogos REALES de la institución (roles, sedes, carreras y planes por sede). Es seguro ejecutarlo en producción y
 * más de una vez (no duplica ni borra nada):
 *
 *   php artisan db:seed --class=CatalogosSeeder
 */
class CatalogosSeeder extends Seeder
{
    /** clave => nombre. Las claves se pueden cambiar después en Administración › Catálogos. */
    public const SEDES = [
        'CUOP-LOR' => 'LORETO',
        'CUOP-CIE' => 'CIENEGA',
        'CUOP-SJO' => 'SAN JOSE',
        'CUOP-CAL' => 'CALVILLO',
        'CUOP-AGS' => 'AGUASCALIENTES',
    ];

    public const ROLES = ['Administrador', 'Cajero', 'Control Escolar'];

    /** nombre => modalidad (fuente: tablero ejecutivo Loreto sep–dic 2026; grupos *-NESC-SAB). */
    public const CARRERAS = [
        'LICENCIATURA EN EDUCACIÓN' => 'NO ESCOLARIZADO',
        'LICENCIATURA EN DERECHO' => 'NO ESCOLARIZADO',
        'LICENCIATURA EN ADMINISTRACIÓN' => 'NO ESCOLARIZADO',
        'MAESTRÍA EN EDUCACIÓN E INNOVACIÓN' => 'NO ESCOLARIZADO',
        'MAESTRÍA EN MEDIACIÓN Y JUICIOS ORALES' => 'NO ESCOLARIZADO',
    ];

    /**
     * Planes por sede: clave de sede => [nombre => mensualidad]. Mismos nombres que en INTELL
     * para que la importación del padrón los encuentre.
     * PENDIENTE DE CONFIRMAR: recargo ($180 es el observado en el tablero) y días de tolerancia.
     */
    public const PLANES = [
        'CUOP-LOR' => [
            'PLAN 1050' => 1050, 'PLAN 1140' => 1140, 'PLAN 1163' => 1163, 'PLAN 1240' => 1240,
            'PLAN 1370' => 1370, 'PLAN 1386' => 1386, 'PLAN 1530' => 1530,
        ],
    ];

    public const RECARGO_POR_DEFECTO = 180;
    public const TOLERANCIA_POR_DEFECTO = 10;

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $rol) {
            Role::findOrCreate($rol, 'web');
        }

        foreach (self::SEDES as $clave => $nombre) {
            Sede::updateOrCreate(['clave' => $clave], ['nombre' => $nombre]);
        }

        foreach (self::CARRERAS as $nombre => $modalidad) {
            Carrera::firstOrCreate(['nombre' => $nombre, 'modalidad' => $modalidad]);
        }

        // firstOrCreate: si un plan ya existe, NO se sobreescriben los montos que el
        // administrador haya corregido en Catálogos.
        foreach (self::PLANES as $clave => $planes) {
            $sedeId = Sede::where('clave', $clave)->value('id');
            foreach ($planes as $nombre => $mensualidad) {
                PlanPago::firstOrCreate(
                    ['sede_id' => $sedeId, 'carrera_id' => null, 'nombre' => $nombre],
                    ['monto_mensualidad' => $mensualidad, 'monto_recargo' => self::RECARGO_POR_DEFECTO,
                        'dias_tolerancia' => self::TOLERANCIA_POR_DEFECTO],
                );
            }
        }
    }
}
