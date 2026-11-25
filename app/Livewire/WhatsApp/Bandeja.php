<?php

namespace App\Livewire\WhatsApp;

use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\Mensajeria;
use App\Services\WhatsApp\Telefono;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WhatsApp')]
class Bandeja extends Component
{
    #[Url]
    public ?string $telefono = null;

    #[Url]
    public bool $soloPendientes = false;

    public string $buscar = '';

    public string $respuesta = '';

    #[Computed]
    public function conversaciones()
    {
        $termino = trim($this->buscar);
        $ultimos = WhatsappMensaje::query()
            ->selectRaw('telefono, MAX(id) as ultimo_id, SUM(CASE WHEN requiere_atencion = 1 AND direccion = ? THEN 1 ELSE 0 END) as pendientes', ['entrante'])
            ->when($termino !== '', fn ($q) => $q->where(fn ($q) => $q->where('telefono', 'like', "%{$termino}%")
                ->orWhere('matricula', 'like', "%{$termino}%")
                ->orWhereHas('alumno', fn ($q) => $q->where('nombre_completo', 'like', "%{$termino}%"))))
            ->groupBy('telefono')
            ->when($this->soloPendientes, fn ($q) => $q->havingRaw('SUM(CASE WHEN requiere_atencion = 1 AND direccion = ? THEN 1 ELSE 0 END) > 0', ['entrante']))
            ->orderByDesc(DB::raw('MAX(id)'))
            ->limit(60)->get();

        $mensajes = WhatsappMensaje::with('alumno:matricula,nombre_completo')->whereIn('id', $ultimos->pluck('ultimo_id'))->get()->keyBy('id');

        return $ultimos->map(fn ($c) => ['telefono' => $c->telefono, 'pendientes' => (int) $c->pendientes, 'ultimo' => $mensajes[$c->ultimo_id]]);
    }

    #[Computed]
    public function hilo()
    {
        return $this->telefono
            ? WhatsappMensaje::with('usuario:id,name')->where('telefono', $this->telefono)->latest('id')->limit(100)->get()->reverse()->values()
            : collect();
    }

    #[Computed]
    public function alumnos()
    {
        return $this->telefono ? Mensajeria::alumnosDelTelefono($this->telefono) : collect();
    }

    #[Computed]
    public function ventanaAbierta(): bool
    {
        return $this->telefono && WhatsappMensaje::ventanaAbierta($this->telefono);
    }

    public function abrir(string $telefono): void
    {
        $this->telefono = Telefono::clave($telefono);
        $this->respuesta = '';
        $this->resetValidation();
        unset($this->hilo, $this->alumnos, $this->ventanaAbierta);
    }

    public function responder(Mensajeria $mensajeria): void
    {
        $this->validate(['respuesta' => 'required|string|max:4000'], [], ['respuesta' => 'respuesta']);

        $m = $mensajeria->texto($this->telefono, $this->respuesta, auth()->user(), 'texto',
            $this->alumnos->count() === 1 ? $this->alumnos->first()->matricula : null);

        if (! $m) {
            $this->addError('respuesta', 'Pasaron más de 24 h desde el último mensaje del alumno: WhatsApp solo permite enviarle una plantilla (desde su estado de cuenta).');

            return;
        }
        WhatsappMensaje::where('telefono', $this->telefono)->where('requiere_atencion', true)->update(['requiere_atencion' => false]);
        $this->respuesta = '';
        unset($this->hilo, $this->conversaciones);
    }

    public function marcarAtendida(): void
    {
        WhatsappMensaje::where('telefono', $this->telefono)->where('requiere_atencion', true)->update(['requiere_atencion' => false]);
        unset($this->conversaciones);
    }

    public function render()
    {
        return view('livewire.whatsapp.bandeja');
    }
}
