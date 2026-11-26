<?php

namespace App\Livewire\Cobranza;

use App\Models\CampanaCobranza;
use App\Models\Carrera;
use App\Models\Sede;
use App\Services\Cobranza\CampanaService;
use App\Services\WhatsApp\Plantillas;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Campañas de cobranza')]
class Campanas extends Component
{
    #[Url]
    public string $segmento = 'con_recargo';

    public string $plantilla = 'aviso_recargo';

    #[Url]
    public ?string $sedeId = null;

    #[Url]
    public ?string $carreraId = null;

    public string $grupo = '';

    public int $diasMin = 30;

    public ?string $saldoMin = null;

    public string $nombre = '';

    #[Url(as: 'campana')]
    public ?int $campanaId = null;

    public function mount(): void
    {
        if (! isset(CampanaService::SEGMENTOS[$this->segmento])) {
            $this->segmento = 'con_recargo';
        }
        $this->plantilla = CampanaService::plantillaPorDefecto($this->segmento);
    }

    public function updatedSegmento(): void
    {
        $this->plantilla = CampanaService::plantillaPorDefecto($this->segmento);
    }

    private function filtros(): array
    {
        return [
            'sede_id' => $this->sedeId ? (int) $this->sedeId : null,
            'carrera_id' => $this->carreraId ? (int) $this->carreraId : null,
            'grupo' => trim($this->grupo) ?: null,
            'dias_min' => $this->segmento === 'atraso' ? max(1, $this->diasMin) : null,
            'saldo_min' => is_numeric($this->saldoMin) ? (float) $this->saldoMin : null,
        ];
    }

    public function enviar(CampanaService $service): void
    {
        abort_unless(auth()->user()->hasAnyRole(['Administrador', 'Cajero']), 403);
        $this->validate([
            'segmento' => 'required|in:'.implode(',', array_keys(CampanaService::SEGMENTOS)),
            'plantilla' => 'required|in:'.implode(',', CampanaService::PLANTILLAS),
            'nombre' => 'nullable|string|max:120',
            'diasMin' => 'integer|min:1|max:999',
        ]);

        $campana = $service->lanzar($this->segmento, $this->plantilla, $this->filtros(), $this->nombre, auth()->user());
        $this->nombre = '';
        $this->campanaId = $campana->id;

        $omitidos = collect($campana->omitidos)->map(fn ($n, $m) => "{$n} {$m}")->implode(' · ');
        $this->dispatch('toast', tipo: $campana->en_cola ? 'success' : 'error',
            mensaje: "Campaña creada: {$campana->en_cola} mensajes en cola".($omitidos ? " · omitidos: {$omitidos}" : ''));
    }

    public function ver(?int $id): void
    {
        $this->campanaId = $id;
    }

    public function render(CampanaService $service)
    {
        $filas = $service->destinatarios($this->segmento, $this->filtros());
        $contactables = $filas->whereNull('contacto');
        $campana = $this->campanaId ? CampanaCobranza::with('usuario:id,name')->find($this->campanaId) : null;

        return view('livewire.cobranza.campanas', [
            'filas' => $filas,
            'contactables' => $contactables->count(),
            'saldoContactable' => round($contactables->sum('saldo'), 2),
            'sinContacto' => $filas->whereNotNull('contacto')->countBy('contacto'),
            'total' => round($filas->sum('saldo'), 2),
            'vistaPrevia' => $this->vistaPrevia($filas),
            'campanas' => CampanaCobranza::with('usuario:id,name')->latest('id')->limit(20)->get(),
            'campana' => $campana,
            'resultado' => $campana ? $service->resultados($campana) : null,
            'sedes' => Sede::activas()->orderBy('clave')->get(['id', 'clave']),
            'carreras' => Carrera::activas()->orderBy('nombre')->get(['id', 'nombre']),
            'modoPrueba' => config('services.whatsapp.driver') !== 'cloud',
        ]);
    }

    /** Cómo le llegaría el mensaje al primer alumno contactable de la lista. */
    private function vistaPrevia($filas): ?string
    {
        foreach ($filas->whereNull('contacto')->take(10) as $f) {
            $vars = Plantillas::variables($this->plantilla, $f['alumno']);
            if ($vars !== null) {
                return Plantillas::vistaPrevia($this->plantilla, $vars);
            }
        }

        return null;
    }
}
