<?php
// Uso: php cobro_concurrente.php <ruta_proyecto> <adeudo_id> <monto> <t_inicio>
[$_, $root, $adeudoId, $monto, $inicio] = $argv;
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
while (microtime(true) < (float) $inicio) { usleep(500); } // todos arrancan a la vez
try {
    $p = app(App\Actions\Cobranza\RegistrarPagoAction::class)->execute(
        (int) $adeudoId, $monto, App\Enums\FormaPago::EFECTIVO, App\Models\User::first());
    echo "OK {$p->folio_pago}\n";
} catch (App\Exceptions\CobranzaException $e) {
    echo "RECHAZADO {$e->getMessage()}\n";
} catch (Throwable $e) {
    echo "ERROR ".get_class($e).": {$e->getMessage()}\n";
}
