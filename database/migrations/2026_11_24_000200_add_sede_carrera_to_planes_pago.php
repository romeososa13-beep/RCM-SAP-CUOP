<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada sede tiene sus propios planes de pago. sede_id / carrera_id NULL
     * significa "válido para todas". Un alumno solo puede tener un plan
     * compatible con su sede y su carrera.
     */
    public function up(): void
    {
        Schema::table('planes_pago', function (Blueprint $table) {
            $table->foreignId('sede_id')->nullable()->after('nombre')->constrained('sedes')->restrictOnDelete();
            $table->foreignId('carrera_id')->nullable()->after('sede_id')->constrained('carreras')->restrictOnDelete();
            $table->unique(['sede_id', 'carrera_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::table('planes_pago', function (Blueprint $table) {
            $table->dropUnique(['sede_id', 'carrera_id', 'nombre']);
            $table->dropConstrainedForeignId('carrera_id');
            $table->dropConstrainedForeignId('sede_id');
        });
    }
};
