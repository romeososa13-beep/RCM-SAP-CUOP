<?php

namespace App\Livewire\Reportes;

use App\Models\User;
use App\Services\Reportes\CorteCajaService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Corte de caja')]
class CorteCaja extends Component
{
    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    #[Url]
    public ?string $cajeroId = null;

    public function mount(): void
    {
        $this->desde = $this->desde ?: today()->toDateString();
        $this->hasta = $this->hasta ?: today()->toDateString();

        if (! $this->esAdmin()) {
            $this->cajeroId = (string) auth()->id(); // el cajero solo ve su propio corte
        }
    }

    private function esAdmin(): bool
    {
        return auth()->user()->hasRole('Administrador');
    }

    #[Computed]
    public function cajeros()
    {
        return User::role(['Cajero', 'Administrador'])->orderBy('name')->get(['id', 'name']);
    }

    public function hoy(): void
    {
        $this->desde = $this->hasta = today()->toDateString();
    }

    public function render(CorteCajaService $service)
    {
        $this->validate(['desde' => 'required|date', 'hasta' => 'required|date|after_or_equal:desde']);

        $cajero = $this->esAdmin() ? ($this->cajeroId ?: null) : auth()->id();

        return view('livewire.reportes.corte-caja', [
            'corte' => $service->generar(Carbon::parse($this->desde), Carbon::parse($this->hasta), $cajero ? (int) $cajero : null),
            'esAdmin' => $this->esAdmin(),
            'params' => array_filter(['desde' => $this->desde, 'hasta' => $this->hasta, 'cajero' => $cajero]),
        ]);
    }
}
