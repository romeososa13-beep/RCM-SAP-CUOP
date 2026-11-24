<?php

namespace Database\Seeders;

use App\Models\Sede;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Catálogos REALES de la institución. Es seguro ejecutarlo en producción y
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

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $rol) {
            Role::findOrCreate($rol, 'web');
        }

        foreach (self::SEDES as $clave => $nombre) {
            Sede::updateOrCreate(['clave' => $clave], ['nombre' => $nombre]);
        }
    }
}
