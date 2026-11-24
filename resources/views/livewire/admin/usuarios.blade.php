<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Usuarios y roles</h1>
            <p class="text-sm text-slate-500">Los usuarios con pagos registrados no se borran: se desactivan.</p>
        </div>
        <button wire:click="nuevo" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Nuevo usuario</button>
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Nombre o correo…"
               class="w-full max-w-md rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Correo</th><th class="px-4 py-3">Rol</th>
                    <th class="px-4 py-3 text-right">Pagos cobrados</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($usuarios as $u)
                    <tr wire:key="u-{{ $u->id }}" @class(['text-slate-400' => ! $u->activo])>
                        <td class="px-4 py-2.5 font-medium">{{ $u->name }} @if ($u->id === auth()->id()) <span class="text-xs text-slate-400">(usted)</span> @endif</td>
                        <td class="px-4 py-2.5">{{ $u->email }}</td>
                        <td class="px-4 py-2.5"><span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $u->roles->pluck('name')->implode(', ') ?: '—' }}</span></td>
                        <td class="px-4 py-2.5 text-right tabular-nums">{{ $u->pagos_count }}</td>
                        <td class="px-4 py-2.5">
                            @if ($u->activo)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Activo</span>
                            @else
                                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600">Desactivado</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-right">
                            <button wire:click="editar({{ $u->id }})" class="text-sm font-medium text-emerald-700 hover:underline">Editar</button>
                            @unless ($u->id === auth()->id())
                                <button wire:click="alternarActivo({{ $u->id }})" wire:confirm="¿{{ $u->activo ? 'Desactivar' : 'Reactivar' }} a {{ $u->name }}?"
                                        class="ml-3 text-sm font-medium {{ $u->activo ? 'text-red-600' : 'text-slate-600' }} hover:underline">
                                    {{ $u->activo ? 'Desactivar' : 'Reactivar' }}
                                </button>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="border-t border-slate-200 px-4 py-3">{{ $usuarios->links() }}</div>
    </div>

    <div x-data="{ open: @entangle('modal') }" x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60" x-on:click="open = false"></div>
        <form wire:submit="guardar" class="relative w-full max-w-md rounded-xl bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-4"><h3 class="text-lg font-bold">{{ $editandoId ? 'Editar usuario' : 'Nuevo usuario' }}</h3></div>
            <div class="space-y-4 px-6 py-5">
                @php($in = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm')
                <div><label class="mb-1 block text-sm font-medium">Nombre</label><input wire:model="name" class="{{ $in }}">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
                <div><label class="mb-1 block text-sm font-medium">Correo</label><input type="email" wire:model="email" class="{{ $in }}">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
                <div><label class="mb-1 block text-sm font-medium">Rol</label>
                    <select wire:model="rol" class="{{ $in }}">@foreach ($roles as $r) <option>{{ $r }}</option> @endforeach</select>
                    @error('rol') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
                <div><label class="mb-1 block text-sm font-medium">Contraseña @if ($editandoId) <span class="font-normal text-slate-400">(vacío = no cambiar)</span> @endif</label>
                    <input type="password" wire:model="password" autocomplete="new-password" class="{{ $in }}">
                    <p class="mt-1 text-xs text-slate-400">Mínimo 8 caracteres, con letras y números.</p>
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="activo" class="rounded"> Usuario activo</label>
            </div>
            <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button type="button" x-on:click="open = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm">Cancelar</button>
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Guardar</button>
            </div>
        </form>
    </div>
</div>
