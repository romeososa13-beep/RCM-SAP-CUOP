<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Mi cuenta')]
class MiCuenta extends Component
{
    public string $actual = '';

    public string $nueva = '';

    public string $nueva_confirmation = '';

    public function cambiarPassword(): void
    {
        $this->validate([
            'actual' => 'required|current_password',
            'nueva' => ['required', 'confirmed', 'different:actual', Password::min(8)->letters()->numbers()],
        ], [
            'actual.current_password' => 'La contraseña actual no es correcta.',
            'nueva.different' => 'La nueva contraseña debe ser distinta a la actual.',
        ], ['actual' => 'contraseña actual', 'nueva' => 'nueva contraseña']);

        auth()->user()->update(['password' => Hash::make($this->nueva)]);
        $this->reset('actual', 'nueva', 'nueva_confirmation');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Contraseña actualizada.');
    }

    public function render()
    {
        return view('livewire.auth.mi-cuenta');
    }
}
