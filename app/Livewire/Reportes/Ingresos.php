<?php

namespace App\Livewire\Reportes;

use App\Enums\FormaPago;
use App\Exports\IngresosExport;
use App\Models\Sede;
use App\Services\Reportes\IngresosService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('components.layouts.app')]
#[Title('Ingresos')]
class Ingresos extends Component
{
    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    #[Url]
    public ?string $sedeId = null;

    public function mount(): void
    {
        $this->desde = $this->desde ?: today()->startOfMonth()->toDateString();
        $this->hasta = $this->hasta ?: today()->toDateString();
    }

    public function rango(string $cual): void
    {
        [$d, $h] = match ($cual) {
            'hoy' => [today(), today()],
            'mes' => [today()->startOfMonth(), today()],
            'mes_anterior' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            'anio' => [today()->startOfYear(), today()],
        };
        [$this->desde, $this->hasta] = [$d->toDateString(), $h->toDateString()];
    }

    private function datos(IngresosService $s): array
    {
        $this->validate(['desde' => 'required|date', 'hasta' => 'required|date|after_or_equal:desde']);

        return $s->generar(Carbon::parse($this->desde), Carbon::parse($this->hasta), $this->sedeId ? (int) $this->sedeId : null);
    }

    public function exportar(IngresosService $s)
    {
        return Excel::download(new IngresosExport($this->datos($s)), "ingresos-{$this->desde}-al-{$this->hasta}.xlsx");
    }

    public function render(IngresosService $s)
    {
        return view('livewire.reportes.ingresos', [
            'r' => $this->datos($s),
            'sedes' => Sede::activas()->orderBy('clave')->get(['id', 'clave']),
            'formas' => FormaPago::cases(),
        ]);
    }
}
