<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sedes y carreras activas. El sistema arranca solo en Loreto: las demás quedan
     * registradas pero inactivas (no aparecen en selectores, filtros ni importación)
     * y se activan desde Catálogos cuando se sumen.
     */
    public function up(): void
    {
        Schema::table('sedes', fn (Blueprint $t) => $t->boolean('activa')->default(true)->after('nombre'));
        Schema::table('carreras', fn (Blueprint $t) => $t->boolean('activa')->default(true)->after('modalidad'));
    }

    public function down(): void
    {
        Schema::table('carreras', fn (Blueprint $t) => $t->dropColumn('activa'));
        Schema::table('sedes', fn (Blueprint $t) => $t->dropColumn('activa'));
    }
};
