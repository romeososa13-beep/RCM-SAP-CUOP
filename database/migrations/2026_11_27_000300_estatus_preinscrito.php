<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Preinscrito: aún no inscrito; no genera mensualidad ni bloqueo (como las bajas), pero no es baja. */
    public function up(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->enum('estatus', ['ACTIVO', 'PREINSCRITO', 'BAJA_TEMPORAL', 'BAJA_DEFINITIVA', 'EGRESADO'])
                ->default('ACTIVO')->change();
        });
    }

    public function down(): void
    {
        DB::table('alumnos')->where('estatus', 'PREINSCRITO')->update(['estatus' => 'BAJA_TEMPORAL']);
        Schema::table('alumnos', function (Blueprint $table) {
            $table->enum('estatus', ['ACTIVO', 'BAJA_TEMPORAL', 'BAJA_DEFINITIVA', 'EGRESADO'])->default('ACTIVO')->change();
        });
    }
};
