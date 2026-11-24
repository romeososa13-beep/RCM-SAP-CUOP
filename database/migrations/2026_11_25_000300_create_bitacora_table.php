<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Registro inmutable de cambios: quién, cuándo, desde dónde y qué (antes → después). */
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 30);                   // creado, actualizado, eliminado, cancelado, importacion…
            $table->string('entidad', 30);                  // alumno, adeudo, pago, plan_pago, sede, carrera, usuario, proceso
            $table->string('entidad_id', 40)->nullable();   // id o matrícula
            $table->string('descripcion', 255)->nullable();
            $table->json('cambios')->nullable();            // {"campo": {"antes": x, "despues": y}}
            $table->string('origen', 20)->default('web');   // web | consola
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['entidad', 'entidad_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
