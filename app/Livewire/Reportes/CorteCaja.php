<?php

namespace App\Livewire\Reportes;

use App\Actions\Cobranza\CorteCajaAction;
use App\Exceptions\CobranzaException;
use App\Models\CorteCaja as CorteCajaModel;
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

    public string $motivoReapertura = '';

    public bool $modalReabrir = false;

    /** Cajero y día del corte que se está viendo (solo cuando el rango es un solo día). */
    private function corteActual(): ?array
    {
        $cajero = $this->esAdmin() ? ($this->cajeroId ?: null) : auth()->id();

        return $cajero && $this->desde === $this->hasta ? [(int) $cajero, Carbon::parse($this->desde)] : null;
    }

    #[Computed]
    public function estadoCorte(): ?CorteCajaModel
    {
        [$cajero, $dia] = $this->corteActual() ?? [null, null];

        return $cajero ? CorteCajaModel::with(['cerradoPor:id,name', 'reabiertoPor:id,name'])
            ->where('user_id', $cajero)->whereDate('fecha', $dia->toDateString())->first() : null;
    }

    #[Computed]
    public function cortesRecientes()
    {
        $cajero = $this->esAdmin() ? ($this->cajeroId ?: null) : auth()->id();

        return CorteCajaModel::with('cajero:id,name')->when($cajero, fn ($q) => $q->where('user_id', $cajero))
            ->orderByDesc('fecha')->orderByDesc('id')->limit(10)->get();
    }

    public function cerrarCorte(CorteCajaAction $accion): void
    {
        [$cajero, $dia] = $this->corteActual() ?? abort(422);
        try {
            $c = $accion->cerrar(User::findOrFail($cajero), $dia, auth()->user());
        } catch (CobranzaException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }
        unset($this->estadoCorte, $this->cortesRecientes);
        $this->dispatch('toast', tipo: 'success', mensaje: "Corte cerrado: {$c->num_pagos} pagos por $".number_format($c->total, 2).'.');
    }

    public function reabrirCorte(CorteCajaAction $accion): void
    {
        abort_unless($this->esAdmin() && $this->estadoCorte, 403);
        $this->validate(['motivoReapertura' => 'required|string|min:5|max:255'], [], ['motivoReapertura' => 'motivo']);
        try {
            $accion->reabrir($this->estadoCorte->id, auth()->user(), $this->motivoReapertura);
        } catch (CobranzaException $e) {
            $this->addError('motivoReapertura', $e->getMessage());

            return;
        }
        $this->modalReabrir = false;
        $this->motivoReapertura = '';
        unset($this->estadoCorte, $this->cortesRecientes);
        $this->dispatch('toast', tipo: 'success', mensaje: 'Corte reabierto.');
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
            'puedeCerrar' => $this->corteActual() !== null,
            'params' => array_filter(['desde' => $this->desde, 'hasta' => $this->hasta, 'cajero' => $cajero]),
        ]);
    }
}
