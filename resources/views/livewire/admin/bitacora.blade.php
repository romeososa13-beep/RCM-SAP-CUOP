<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Bitácora</h1>
        <p class="text-sm text-slate-500">Quién cambió qué y cuándo. Los registros no se pueden editar ni borrar.</p>
    </div>

    <div class="grid grid-cols-1 gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 md:grid-cols-6">
        <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Matrícula, folio, nombre…"
               class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2">
        <select wire:model.live="entidad" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Todo</option>
            @foreach ($entidades as $k => $v) <option value="{{ $k }}">{{ $v }}</option> @endforeach
        </select>
        <select wire:model.live="usuarioId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Todos los usuarios</option>
            @foreach ($usuarios as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
        </select>
        <input type="date" wire:model.live="desde" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" title="Desde">
        <div class="flex gap-2">
            <input type="date" wire:model.live="hasta" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" title="Hasta">
            <button wire:click="limpiar" title="Limpiar filtros" class="rounded-lg border border-slate-300 px-3 text-slate-500 hover:bg-slate-50">✕</button>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Usuario</th><th class="px-4 py-3">Acción</th><th class="px-4 py-3">Registro</th><th class="px-4 py-3">Detalle</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 align-top">
                @forelse ($registros as $r)
                    <tr wire:key="b-{{ $r->id }}">
                        <td class="whitespace-nowrap px-4 py-3 text-xs">{{ $r->created_at->format('d/m/Y H:i:s') }}
                            <span class="block text-slate-400">{{ $r->origen === 'consola' ? 'proceso automático' : $r->ip }}</span></td>
                        <td class="px-4 py-3 text-xs">{{ $r->usuario?->name ?? 'Sistema' }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span @class(['rounded px-2 py-0.5 text-xs font-semibold',
                                'bg-emerald-100 text-emerald-800' => $r->accion === 'creado',
                                'bg-sky-100 text-sky-800' => in_array($r->accion, ['actualizado', 'rol']),
                                'bg-red-100 text-red-800' => in_array($r->accion, ['eliminado', 'cancelado']),
                                'bg-slate-100 text-slate-700' => $r->accion === 'proceso'])>{{ $acciones[$r->accion] ?? $r->accion }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <span class="font-semibold">{{ $entidades[$r->entidad] ?? $r->entidad }}</span>
                            <span class="font-mono text-slate-500">{{ $r->entidad_id }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs text-slate-700">{{ $r->descripcion }}</span>
                            @include('livewire.admin.partials.cambios')
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">Sin registros con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-slate-200 px-4 py-3">{{ $registros->links() }}</div>
    </div>
</div>
