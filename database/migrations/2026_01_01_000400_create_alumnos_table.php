<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumnos', function (Blueprint $table) {
            $table->string('matricula', 20)->primary();
            $table->string('nombre_completo', 200)->index();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->foreignId('carrera_id')->constrained('carreras')->restrictOnDelete();
            $table->string('grupo', 80)->index();       // Ej. 27-1 LIC-EDUN-NESC-SAB-6A
            $table->foreignId('plan_pago_id')->constrained('planes_pago')->restrictOnDelete();
            $table->enum('estatus', ['ACTIVO', 'BAJA_TEMPORAL', 'BAJA_DEFINITIVA', 'EGRESADO'])
                ->default('ACTIVO')
                ->index();
            $table->text('comentarios')->nullable();
            $table->timestamps();

            $table->index(['sede_id', 'carrera_id', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumnos');
    }
};
