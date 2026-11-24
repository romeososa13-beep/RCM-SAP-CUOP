<?php

namespace App\Livewire\Admin;

use App\Models\Bitacora;
use App\Models\User;
use App\Support\BitacoraFormato;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Bitácora')]
class BitacoraCambios extends Component
{
    use WithPagination;

    #[Url]
    public string $buscar = '';

    #[Url]
    public string $entidad = '';

    #[Url]
    public ?string $usuarioId = null;

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function limpiar(): void
    {
        $this->reset('buscar', 'entidad', 'usuarioId', 'desde', 'hasta');
        $this->resetPage();
    }

    public function render()
    {
        $termino = trim($this->buscar);

        $registros = Bitacora::query()
            ->with('usuario:id,name')
            ->when($this->entidad, fn ($q) => $q->where('entidad', $this->entidad))
            ->when($this->usuarioId, fn ($q) => $q->where('user_id', $this->usuarioId))
            ->when($this->desde, fn ($q) => $q->where('created_at', '>=', $this->desde.' 00:00:00'))
            ->when($this->hasta, fn ($q) => $q->where('created_at', '<=', $this->hasta.' 23:59:59'))
            ->when($termino !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('entidad_id', $termino)
                ->orWhere('descripcion', 'like', '%'.$termino.'%')))
            ->orderByDesc('id')
            ->paginate(25);

        return view('livewire.admin.bitacora', [
            'registros' => $registros,
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
            'f' => new BitacoraFormato,
            'entidades' => BitacoraFormato::ENTIDADES,
            'acciones' => BitacoraFormato::ACCIONES,
        ]);
    }
}
