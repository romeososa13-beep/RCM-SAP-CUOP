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
        'CUOP-CIE' => 'CIÉNEGA',
        'CUOP-SJG' => 'SAN JOSÉ DE GRACIA',   // clave tomada de sus matrículas (CUOP-SJG…)
        'CUOP-CAL' => 'CALVILLO',
        'CUOP-AGS' => 'AGUASCALIENTES',
    ];

    public const ROLES = ['Administrador', 'Cajero', 'Control Escolar'];

    /** El sistema arranca solo en Loreto; las demás sedes se activan después en Catálogos. */
    public const SEDES_ACTIVAS = ['CUOP-LOR'];

    /** Carreras que hoy se imparten en Loreto (las demás quedan inactivas hasta sumar su sede). */
    public const CARRERAS_ACTIVAS = [
        'LICENCIATURA EN EDUCACIÓN', 'LICENCIATURA EN DERECHO', 'LICENCIATURA EN ADMINISTRACIÓN',
        'MAESTRÍA EN EDUCACIÓN E INNOVACIÓN', 'MAESTRÍA EN MEDIACIÓN Y JUICIOS ORALES',
    ];

    /**
     * nombre => modalidad. Fuente: tablero ejecutivo de las 5 sedes, sep–dic 2026.
     * Las 5 primeras son NO ESCOLARIZADO según los códigos de grupo de Loreto (*-NESC-SAB);
     * las demás quedan POR CONFIRMAR hasta que Control Escolar indique su modalidad.
     */
    public const CARRERAS = [
        'LICENCIATURA EN EDUCACIÓN' => 'NO ESCOLARIZADO',
        'LICENCIATURA EN DERECHO' => 'NO ESCOLARIZADO',
        'LICENCIATURA EN ADMINISTRACIÓN' => 'NO ESCOLARIZADO',
        'MAESTRÍA EN EDUCACIÓN E INNOVACIÓN' => 'NO ESCOLARIZADO',
        'MAESTRÍA EN MEDIACIÓN Y JUICIOS ORALES' => 'NO ESCOLARIZADO',
        'BACHILLERATO' => 'POR CONFIRMAR',
        'LICENCIATURA EN CRIMINOLOGÍA Y CRIMINALÍSTICA' => 'POR CONFIRMAR',
        'LICENCIATURA EN LENGUA INGLESA' => 'POR CONFIRMAR',
        'LICENCIATURA EN COMUNICACIÓN Y DISEÑO GRÁFICO' => 'POR CONFIRMAR',
        'LICENCIATURA EN NEGOCIOS DIGITALES' => 'POR CONFIRMAR',
        'INGENIERÍA INDUSTRIAL' => 'POR CONFIRMAR',
        'INGENIERÍA EN SOFTWARE' => 'POR CONFIRMAR',
        'INGENIERÍA EN INTELIGENCIA ARTIFICIAL Y CIENCIAS DE DATOS' => 'POR CONFIRMAR',
        'MAESTRÍA EN ADMINISTRACIÓN ESTRATÉGICA DE HOSPITALES' => 'POR CONFIRMAR',
        'MAESTRÍA EN CALIDAD Y PRODUCTIVIDAD' => 'POR CONFIRMAR',
    ];

    /**
     * Planes por sede: clave de sede => [nombre => mensualidad]. Mismos nombres que en INTELL
     * para que la importación del padrón los encuentre.
     * Fuente: «Plan en INTELL» de cada alumno. Confirmado por Administración: se paga del 1 al 10,
     * desde el 11 recargo de $180 (inglés: 10%) y desde el 16 bloqueo de plataformas.
     */
    public const PLANES = [
        'CUOP-LOR' => [
            'PLAN 1050' => 1050, 'PLAN 1140' => 1140, 'PLAN 1163' => 1163, 'PLAN 1240' => 1240, 'PLAN 1370' => 1370, 'PLAN 1386' => 1386,
            'PLAN 1530' => 1530,
        ],
        'CUOP-CIE' => [
            'PLAN 600' => 600, 'PLAN 800' => 800, 'PLAN 1000' => 1000, 'PLAN 1100' => 1100, 'PLAN 1245' => 1245, 'PLAN 1250' => 1250,
        ],
        'CUOP-SJG' => [
            'PLAN 1050' => 1050, 'PLAN 1115' => 1115, 'PLAN 1280' => 1280, 'PLAN 1750' => 1750,
        ],
        'CUOP-CAL' => [
            'PLAN 800' => 800, 'PLAN 855' => 855, 'PLAN 1000' => 1000, 'PLAN 1100' => 1100, 'PLAN 1116' => 1116, 'PLAN 1140' => 1140,
            'PLAN 1220' => 1220, 'PLAN 1240' => 1240, 'PLAN 1254' => 1254, 'PLAN 1370' => 1370,
        ],
        'CUOP-AGS' => [
            'PLAN 700' => 700, 'PLAN 750' => 750, 'PLAN 920' => 920, 'PLAN 945' => 945, 'PLAN 950' => 950, 'PLAN 1000' => 1000,
            'PLAN 1021' => 1021, 'PLAN 1035' => 1035, 'PLAN 1040' => 1040, 'PLAN 1050' => 1050, 'PLAN 1116' => 1116, 'PLAN 1135' => 1135,
            'PLAN 1138' => 1138, 'PLAN 1140' => 1140, 'PLAN 1150' => 1150, 'PLAN 1163' => 1163, 'PLAN 1220' => 1220, 'PLAN 1240' => 1240,
            'PLAN 1245' => 1245, 'PLAN 1250' => 1250, 'PLAN 1260' => 1260, 'PLAN 1265' => 1265, 'PLAN 1370' => 1370, 'PLAN 1380' => 1380,
            'PLAN 1385' => 1385, 'PLAN 1386' => 1386, 'PLAN 1390' => 1390, 'PLAN 1400' => 1400, 'PLAN 1530' => 1530, 'PLAN 1570' => 1570,
            'PLAN 1657' => 1657, 'PLAN 1744' => 1744, 'PLAN 1750' => 1750, 'PLAN 2180' => 2180, 'PLAN 2530' => 2530,
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
            // «activa» solo se fija al crear: no deshace lo que el Administrador active después.
            $sede = Sede::firstOrCreate(['clave' => $clave], ['nombre' => $nombre, 'activa' => in_array($clave, self::SEDES_ACTIVAS, true)]);
            $sede->update(['nombre' => $nombre]);
        }

        foreach (self::CARRERAS as $nombre => $modalidad) {
            Carrera::firstOrCreate(['nombre' => $nombre, 'modalidad' => $modalidad],
                ['activa' => in_array($nombre, self::CARRERAS_ACTIVAS, true)]);
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
