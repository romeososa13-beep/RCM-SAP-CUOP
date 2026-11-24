<div class="mx-auto mt-24 max-w-sm">
    <div class="rounded-xl bg-white p-8 shadow">
        <h1 class="mb-1 text-2xl font-bold">ERP<span class="text-emerald-500">·</span>CUOP</h1>
        <p class="mb-6 text-sm text-slate-500">Control Escolar y Cobranza</p>
        <form wire:submit="login" class="space-y-4">
            <div>
                <label class="mb-1 block text-sm font-medium">Correo</label>
                <input type="email" wire:model="email" autofocus
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Contraseña</label>
                <input type="password" wire:model="password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200">
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="remember" class="rounded"> Recordarme</label>
            <button class="w-full rounded-lg bg-emerald-600 py-2 font-semibold text-white hover:bg-emerald-700" wire:loading.attr="disabled">
                Entrar
            </button>
        </form>
    </div>
</div>
