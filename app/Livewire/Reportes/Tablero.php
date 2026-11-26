<?php

namespace App\Livewire\Reportes;

use App\Models\Sede;
use App\Services\Reportes\TableroService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Tablero ejecutivo')]
class Tablero extends Component
{
    /** Inicio del cuatrimestre en formato AAAA-MM (01, 05 o 09). */
    #[Url]
    public string $periodo = '';

    #[Url]
    public ?string $sedeId = null;

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-(01|05|09)$/', $this->periodo)) {
            $this->periodo = TableroService::periodoDe(today())[0]->format('Y-m');
        }
    }

    /** Los últimos 6 cuatrimestres y el siguiente. */
    public function periodos(): array
    {
        $actual = TableroService::periodoDe(today())[0];
        $meses = ['01' => 'Ene–abr', '05' => 'May–ago', '09' => 'Sep–dic'];

        return collect(range(-5, 1))->mapWithKeys(function ($i) use ($actual, $meses) {
            $d = $actual->copy()->addMonths(4 * $i);

            return [$d->format('Y-m') => $meses[$d->format('m')].' '.$d->year];
        })->all();
    }

    public function render(TableroService $service)
    {
        [$desde, $hasta] = TableroService::periodoDe(Carbon::createFromFormat('Y-m-d', $this->periodo.'-01'));

        return view('livewire.reportes.tablero', [
            't' => $service->generar($desde, $hasta, $this->sedeId ? (int) $this->sedeId : null),
            'sedes' => Sede::activas()->orderBy('clave')->get(['id', 'clave']),
            'periodos' => $this->periodos(),
        ]);
    }
}
