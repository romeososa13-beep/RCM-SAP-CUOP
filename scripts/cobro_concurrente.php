<?php

use App\Actions\Cobranza\RegistrarPagoAction;
use App\Enums\FormaPago;
use App\Exceptions\CobranzaException;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

// Uso: php cobro_concurrente.php <ruta_proyecto> <adeudo_id> <monto> <t_inicio>
[$_, $root, $adeudoId, $monto, $inicio] = $argv;
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
while (microtime(true) < (float) $inicio) {
    usleep(500);
} // todos arrancan a la vez
try {
    $p = app(RegistrarPagoAction::class)->execute(
        (int) $adeudoId, $monto, FormaPago::EFECTIVO, User::first());
    echo "OK {$p->folio_pago}\n";
} catch (CobranzaException $e) {
    echo "RECHAZADO {$e->getMessage()}\n";
} catch (Throwable $e) {
    echo 'ERROR '.get_class($e).": {$e->getMessage()}\n";
}
