<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Catálogos</h1>
            <p class="text-sm text-slate-500">Sedes, carreras y planes de pago. Solo se elimina lo que no tiene alumnos asignados.</p>
        </div>
        <button wire:click="nuevo" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Agregar</button>
    </div>

    <div class="flex gap-1 border-b border-slate-200">
        @foreach (['sedes' => 'Sedes', 'carreras' => 'Carreras', 'planes' => 'Planes de pago'] as $k => $label)
            <button wire:click="$set('tab', '{{ $k }}')"
                    @class(['-mb-px border-b-2 px-4 py-2 text-sm font-medium',
                            'border-emerald-600 text-emerald-700' => $tab === $k,
                            'border-transparent text-slate-500 hover:text-slate-700' => $tab !== $k])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'planes')
        <p class="rounded-lg bg-amber-50 px-4 py-2 text-sm text-amber-900 ring-1 ring-amber-200">
            Cambiar montos de un plan afecta solo a las mensualidades y recargos que se generen después. Los cargos ya emitidos conservan su monto.
        </p>
    @endif

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    @if ($tab === 'sedes') <th class="px-4 py-3">Clave</th><th class="px-4 py-3">Nombre</th>
                    @elseif ($tab === 'carreras') <th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Modalidad</th>
                    @else <th class="px-4 py-3">Nombre</th><th class="px-4 py-3 text-right">Mensualidad</th><th class="px-4 py-3 text-right">Recargo</th><th class="px-4 py-3 text-right">Tolerancia</th>
                    @endif
                    <th class="px-4 py-3 text-right">Alumnos</th><th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($registros as $r)
                    <tr wire:key="{{ $tab }}-{{ $r->id }}" class="hover:bg-slate-50">
                        <td class="px-4 py-2.5 font-mono text-slate-500">{{ $r->id }}</td>
                        @if ($tab === 'sedes') <td class="px-4 py-2.5 font-mono font-semibold">{{ $r->clave }}</td><td class="px-4 py-2.5">{{ $r->nombre }}</td>
                        @elseif ($tab === 'carreras') <td class="px-4 py-2.5 font-medium">{{ $r->nombre }}</td><td class="px-4 py-2.5">{{ $r->modalidad }}</td>
                        @else <td class="px-4 py-2.5 font-medium">{{ $r->nombre }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">${{ number_format($r->monto_mensualidad, 2) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">${{ number_format($r->monto_recargo, 2) }}</td>
                            <td class="px-4 py-2.5 text-right">{{ $r->dias_tolerancia }} días</td>
                        @endif
                        <td class="px-4 py-2.5 text-right tabular-nums">{{ $r->alumnos_count }}</td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-right">
                            <button wire:click="editar({{ $r->id }})" class="text-sm font-medium text-emerald-700 hover:underline">Editar</button>
                            @if ($r->alumnos_count === 0)
                                <button wire:click="eliminar({{ $r->id }})" wire:confirm="¿Eliminar este registro?" class="ml-3 text-sm font-medium text-red-600 hover:underline">Eliminar</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Sin registros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div x-data="{ open: @entangle('modal') }" x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60" x-on:click="open = false"></div>
        <form wire:submit="guardar" class="relative w-full max-w-md rounded-xl bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-4"><h3 class="text-lg font-bold">{{ $editandoId ? 'Editar' : 'Agregar' }}</h3></div>
            <div class="space-y-4 px-6 py-5">
                @php($in = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm')
                @if ($tab === 'sedes')
                    <div><label class="mb-1 block text-sm font-medium">Clave</label><input wire:model="form.clave" placeholder="CUOP-LOR" class="{{ $in }} font-mono uppercase">
                        @error('form.clave') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
                @endif
                <div><label class="mb-1 block text-sm font-medium">Nombre</label><input wire:model="form.nombre" class="{{ $in }} {{ $tab !== 'planes' ? 'uppercase' : '' }}">
                    @error('form.nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
                @if ($tab === 'carreras')
                    <div><label class="mb-1 block text-sm font-medium">Modalidad</label>
                        <select wire:model="form.modalidad" class="{{ $in }}">@foreach ($modalidades as $m) <option>{{ $m }}</option> @endforeach</select></div>
                @endif
                @if ($tab === 'planes')
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="mb-1 block text-sm font-medium">Mensualidad $</label><input type="number" step="0.01" wire:model="form.monto_mensualidad" class="{{ $in }} text-right">
                            @error('form.monto_mensualidad') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
                        <div><label class="mb-1 block text-sm font-medium">Recargo $</label><input type="number" step="0.01" wire:model="form.monto_recargo" class="{{ $in }} text-right">
                            @error('form.monto_recargo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
                    </div>
                    <div><label class="mb-1 block text-sm font-medium">Días de tolerancia</label><input type="number" wire:model="form.dias_tolerancia" class="{{ $in }}">
                        <p class="mt-1 text-xs text-slate-400">La mensualidad vence el día 1 + estos días.</p>
                        @error('form.dias_tolerancia') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
                @endif
            </div>
            <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button type="button" x-on:click="open = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm">Cancelar</button>
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Guardar</button>
            </div>
        </form>
    </div>
</div>
