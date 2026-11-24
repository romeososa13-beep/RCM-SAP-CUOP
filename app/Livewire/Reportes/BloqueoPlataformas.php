<?php

namespace App\Livewire\Reportes;

use App\Exports\BloqueoExport;
use App\Models\Carrera;
use App\Models\Sede;
use App\Services\Reportes\BloqueoService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('components.layouts.app')]
#[Title('Bloqueo de plataformas')]
class BloqueoPlataformas extends Component
{
    #[Url] public ?string $sedeId = null;
    #[Url] public ?string $carreraId = null;

    private function filtros(): array
    {
        return [$this->sedeId ? (int) $this->sedeId : null, $this->carreraId ? (int) $this->carreraId : null];
    }

    public function exportar(BloqueoService $service)
    {
        return Excel::download(new BloqueoExport($service->alumnos(...$this->filtros())),
            'bloqueo-plataformas-'.today()->format('Ymd').'.xlsx');
    }

    public function render(BloqueoService $service)
    {
        $filas = $service->alumnos(...$this->filtros());

        return view('livewire.reportes.bloqueo-plataformas', [
            'filas' => $filas,
            'total' => round($filas->sum('saldo'), 2),
            'sedes' => Sede::orderBy('clave')->get(['id', 'clave']),
            'carreras' => Carrera::orderBy('nombre')->get(['id', 'nombre']),
            'dias' => config('cobranza.bloqueo_dias_despues_vencimiento'),
        ]);
    }
}
