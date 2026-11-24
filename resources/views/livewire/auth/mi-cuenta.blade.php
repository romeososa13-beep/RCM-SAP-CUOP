<div class="mx-auto max-w-md space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Mi cuenta</h1>
        <p class="text-sm text-slate-500">{{ auth()->user()->name }} · {{ auth()->user()->email }} · {{ auth()->user()->getRoleNames()->implode(', ') }}</p>
    </div>
    <form wire:submit="cambiarPassword" class="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="font-semibold">Cambiar contraseña</h2>
        @php($in = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm')
        <div><label class="mb-1 block text-sm font-medium">Contraseña actual</label><input type="password" wire:model="actual" autocomplete="current-password" class="{{ $in }}">
            @error('actual') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
        <div><label class="mb-1 block text-sm font-medium">Nueva contraseña</label><input type="password" wire:model="nueva" autocomplete="new-password" class="{{ $in }}">
            <p class="mt-1 text-xs text-slate-400">Mínimo 8 caracteres, con letras y números.</p>
            @error('nueva') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
        <div><label class="mb-1 block text-sm font-medium">Confirmar nueva contraseña</label><input type="password" wire:model="nueva_confirmation" autocomplete="new-password" class="{{ $in }}"></div>
        <button class="w-full rounded-lg bg-slate-800 py-2 text-sm font-semibold text-white hover:bg-slate-700">Guardar contraseña</button>
    </form>
</div>
