<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adeudos', function (Blueprint $table) {
            // Lo que el alumno ya abonó a este cargo en el sistema anterior (INTELL/EDUCAPP).
            // No es un pago de esta caja: no tiene folio ni entra al corte.
            $table->decimal('pagado_previo', 10, 2)->default(0)->after('recargo_aplicado');
            // El comando de recargos no toca este cargo (migrado con su monto final, condonado, beca…).
            $table->boolean('exento_recargo')->default(false)->after('pagado_previo');
            // SISTEMA = generado aquí; MIGRACION = saldo traído del sistema anterior.
            $table->string('origen', 20)->default('SISTEMA')->after('creado_por');
        });

        Schema::table('alumnos', function (Blueprint $table) {
            $table->string('celular', 20)->nullable()->after('costo_ingles');
            $table->string('tel_tutor', 20)->nullable()->after('celular');
            $table->string('tel_alterno', 20)->nullable()->after('tel_tutor');
        });
    }

    public function down(): void
    {
        Schema::table('alumnos', fn (Blueprint $t) => $t->dropColumn(['celular', 'tel_tutor', 'tel_alterno']));
        Schema::table('adeudos', fn (Blueprint $t) => $t->dropColumn(['pagado_previo', 'exento_recargo', 'origen']));
    }
};
