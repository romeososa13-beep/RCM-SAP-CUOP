<?php

namespace App\Livewire\Admin;

use App\Models\Bitacora;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('components.layouts.app')]
#[Title('Usuarios')]
class Usuarios extends Component
{
    use WithPagination;

    public const ROLES = ['Administrador', 'Cajero', 'Control Escolar'];

    #[Url]
    public string $buscar = '';

    public bool $modal = false;

    #[Locked]
    public ?int $editandoId = null;

    public string $name = '';

    public string $email = '';

    public string $rol = 'Cajero';

    public string $password = '';

    public bool $activo = true;

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function nuevo(): void
    {
        $this->reset('editandoId', 'name', 'email', 'password');
        $this->rol = 'Cajero';
        $this->activo = true;
        $this->resetValidation();
        $this->modal = true;
    }

    public function editar(int $id): void
    {
        $u = User::findOrFail($id);
        $this->editandoId = $u->id;
        $this->name = $u->name;
        $this->email = $u->email;
        $this->rol = $u->getRoleNames()->first() ?? 'Cajero';
        $this->activo = $u->activo;
        $this->password = '';
        $this->resetValidation();
        $this->modal = true;
    }

    public function guardar(): void
    {
        $this->email = mb_strtolower(trim($this->email));
        $esYoMismo = $this->editandoId === auth()->id();

        $this->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->editandoId)],
            'rol' => ['required', Rule::in(self::ROLES)],
            'password' => [$this->editandoId ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
        ], [], ['name' => 'nombre', 'email' => 'correo', 'password' => 'contraseña']);

        // Un administrador no puede quitarse el rol ni desactivarse (evita quedarse sin acceso).
        if ($esYoMismo && ($this->rol !== 'Administrador' || ! $this->activo)) {
            $this->addError('rol', 'No puede quitarse el rol de Administrador ni desactivar su propio usuario.');

            return;
        }

        $datos = ['name' => $this->name, 'email' => $this->email, 'activo' => $this->activo];
        if ($this->password !== '') {
            $datos['password'] = Hash::make($this->password);
        }

        $user = $this->editandoId
            ? tap(User::findOrFail($this->editandoId))->update($datos)
            : User::create($datos + ['email_verified_at' => now()]);

        Role::findOrCreate($this->rol, 'web');
        $rolAnterior = $user->getRoleNames()->first();
        $user->syncRoles([$this->rol]);

        if ($rolAnterior !== $this->rol) {
            Bitacora::registrar('rol', 'usuario', $user->id, $user->name.' <'.$user->email.'>',
                ['rol' => ['antes' => $rolAnterior, 'despues' => $this->rol]]);
        }

        $this->modal = false;
        $this->dispatch('toast', tipo: 'success', mensaje: $this->editandoId ? 'Usuario actualizado.' : 'Usuario creado.');
    }

    public function alternarActivo(int $id): void
    {
        if ($id === auth()->id()) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'No puede desactivar su propio usuario.');

            return;
        }

        $u = User::findOrFail($id);
        $u->update(['activo' => ! $u->activo]);
        $this->dispatch('toast', tipo: 'success', mensaje: $u->activo ? "{$u->name} reactivado." : "{$u->name} desactivado.");
    }

    public function render()
    {
        return view('livewire.admin.usuarios', [
            'usuarios' => User::query()
                ->with('roles:id,name')
                ->withCount('pagos')
                ->when($this->buscar, fn ($q) => $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$this->buscar}%")->orWhere('email', 'like', "%{$this->buscar}%")))
                ->orderByDesc('activo')->orderBy('name')
                ->paginate(15),
            'roles' => self::ROLES,
        ]);
    }
}
