<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Beca: porcentaje de descuento sobre la colegiatura (y opcionalmente el inglés).
        Schema::table('alumnos', function (Blueprint $table) {
            $table->decimal('beca_porcentaje', 5, 2)->default(0)->after('costo_ingles');
            $table->boolean('beca_ingles')->default(false)->after('beca_porcentaje');
            $table->string('beca_motivo', 150)->nullable()->after('beca_ingles');
        });

        // Descuento aplicado al generar el cargo: monto sin beca = monto_original + descuento.
        Schema::table('adeudos', function (Blueprint $table) {
            $table->decimal('descuento', 10, 2)->default(0)->after('monto_original');
        });
    }

    public function down(): void
    {
        Schema::table('adeudos', fn (Blueprint $t) => $t->dropColumn('descuento'));
        Schema::table('alumnos', fn (Blueprint $t) => $t->dropColumn(['beca_porcentaje', 'beca_ingles', 'beca_motivo']));
    }
};
