<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->string('folio_pago', 20)->unique();  // Ej. 02112
            $table->foreignId('adeudo_id')->constrained('adeudos')->restrictOnDelete();
            $table->dateTime('fecha_pago')->index();
            $table->enum('forma_pago', ['EFECTIVO', 'TRANSFERENCIA', 'TARJETA', 'DEPOSITO']);
            $table->decimal('monto_pagado', 10, 2);
            $table->string('referencia', 100)->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // cajero
            $table->timestamps();

            // Cancelación auditable: el pago nunca se borra físicamente.
            $table->softDeletes();
            $table->foreignId('cancelado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_cancelacion', 255)->nullable();

            $table->index(['user_id', 'fecha_pago']);   // corte de caja por cajero
        });

        // Tabla de control de consecutivos. Se bloquea con SELECT ... FOR UPDATE
        // para que dos cajas nunca emitan el mismo folio ni dejen huecos.
        Schema::create('folios_consecutivos', function (Blueprint $table) {
            $table->string('serie', 30)->primary();     // PAGOS
            $table->unsignedBigInteger('ultimo_folio')->default(0);
            $table->unsignedTinyInteger('longitud')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folios_consecutivos');
        Schema::dropIfExists('pagos');
    }
};
