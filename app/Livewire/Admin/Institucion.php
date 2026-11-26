<?php

namespace App\Livewire\Admin;

use App\Models\Ajuste;
use App\Models\Bitacora;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Datos de la institución')]
class Institucion extends Component
{
    use WithFileUploads;

    public array $datos = [];

    public $logo = null;

    public function mount(): void
    {
        $this->datos = Ajuste::institucion();
    }

    public function guardar(): void
    {
        $this->datos['institucion_rfc'] = mb_strtoupper(trim((string) ($this->datos['institucion_rfc'] ?? '')));
        $this->validate([
            'datos.institucion_nombre' => 'required|string|max:120',
            'datos.institucion_razon_social' => 'nullable|string|max:150',
            // RFC de persona moral (12) o física (13)
            'datos.institucion_rfc' => ['nullable', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u'],
            'datos.institucion_direccion' => 'nullable|string|max:250',
            'datos.institucion_telefono' => 'nullable|string|max:40',
            'datos.recibo_leyenda' => 'nullable|string|max:300',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg|max:1024',
        ], ['datos.institucion_rfc.regex' => 'RFC inválido (ej. CUO010101AB1).'], ['datos.institucion_nombre' => 'nombre']);

        Ajuste::guardar($this->datos);

        if ($this->logo) {
            Storage::disk('local')->delete([Ajuste::LOGO.'.png', Ajuste::LOGO.'.jpg']);
            $ext = $this->logo->getClientOriginalExtension() === 'png' ? 'png' : 'jpg';
            $this->logo->storeAs('institucion', "logo.{$ext}", 'local');
            $this->logo = null;
        }

        Bitacora::registrar('actualizado', 'ajustes', 'institucion', 'Datos de la institución para recibos actualizados');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Datos guardados. Los próximos recibos ya los muestran.');
    }

    public function quitarLogo(): void
    {
        Storage::disk('local')->delete([Ajuste::LOGO.'.png', Ajuste::LOGO.'.jpg']);
        $this->dispatch('toast', tipo: 'success', mensaje: 'Logo eliminado.');
    }

    public function render()
    {
        return view('livewire.admin.institucion', ['logoActual' => Ajuste::logoDataUri()]);
    }
}
