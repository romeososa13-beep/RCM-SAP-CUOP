<?php

namespace App\Livewire\Reportes;

use App\Exports\BloqueoExport;
use App\Models\Bitacora;
use App\Models\Carrera;
use App\Models\Sede;
use App\Services\Reportes\BloqueoService;
use App\Services\WhatsApp\Mensajeria;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('components.layouts.app')]
#[Title('Bloqueo de plataformas')]
class BloqueoPlataformas extends Component
{
    #[Url]
    public ?string $sedeId = null;

    #[Url]
    public ?string $carreraId = null;

    private function filtros(): array
    {
        return [$this->sedeId ? (int) $this->sedeId : null, $this->carreraId ? (int) $this->carreraId : null];
    }

    public function exportar(BloqueoService $service)
    {
        return Excel::download(new BloqueoExport($service->alumnos(...$this->filtros())),
            'bloqueo-plataformas-'.today()->format('Ymd').'.xlsx');
    }

    public function avisarWhatsApp(BloqueoService $service, Mensajeria $mensajeria): void
    {
        abort_unless(auth()->user()->hasAnyRole(['Administrador', 'Cajero']), 403);

        $enviados = 0;
        $omitidos = [];
        foreach ($service->alumnos(...$this->filtros()) as $f) {
            [$m, $motivo] = $mensajeria->plantilla($f['alumno'], 'aviso_bloqueo', auth()->user());
            $m ? $enviados++ : ($omitidos[$motivo] = ($omitidos[$motivo] ?? 0) + 1);
        }
        if ($enviados) {
            Bitacora::registrar('proceso', 'proceso', 'whatsapp:aviso_bloqueo', "Aviso de bloqueo por WhatsApp: {$enviados} mensajes en cola");
        }

        $detalle = collect($omitidos)->map(fn ($n, $m) => "{$n} {$m}")->implode(' · ');
        $this->dispatch('toast', tipo: $enviados ? 'success' : 'error',
            mensaje: "WhatsApp: {$enviados} avisos en cola".($detalle ? " · omitidos: {$detalle}" : ''));
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
