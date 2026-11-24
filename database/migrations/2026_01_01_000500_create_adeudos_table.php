<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adeudos', function (Blueprint $table) {
            $table->id();
            $table->string('matricula', 20);
            $table->foreign('matricula')
                ->references('matricula')->on('alumnos')
                ->cascadeOnUpdate()     // si se corrige una matrícula, se propaga
                ->restrictOnDelete();   // nunca se borra un alumno con historial financiero
            $table->string('concepto', 150);            // Ej. MENSUALIDAD DIC 2026
            $table->date('fecha_vencimiento');
            $table->decimal('monto_original', 10, 2);
            $table->decimal('recargo_aplicado', 10, 2)->default(0);
            $table->boolean('pagado')->default(false);
            $table->timestamps();

            $table->index(['matricula', 'pagado']);
            $table->index(['pagado', 'fecha_vencimiento']);   // comando de recargos / cartera vencida
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adeudos');
    }
};
