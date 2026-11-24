<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->decimal('monto_mensualidad', 10, 2);
            $table->decimal('monto_recargo', 10, 2)->default(0);
            $table->unsignedSmallInteger('dias_tolerancia')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes_pago');
    }
};
