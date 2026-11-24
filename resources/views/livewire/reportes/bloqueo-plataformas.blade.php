<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Bloqueo de plataformas</h1>
            <p class="text-sm text-slate-500">
                Alumnos activos que deben colegiatura o inglés con más de {{ $dias - 1 }} días de vencido
                (pago hasta el día 10 → bloqueo desde el día {{ 10 + $dias }}). Hoy: {{ today()->format('d/m/Y') }}.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <div class="rounded-xl bg-red-600 px-5 py-3 text-white shadow">
                <p class="text-xs uppercase tracking-wide text-red-100">{{ $filas->count() }} alumnos a bloquear</p>
                <p class="text-2xl font-bold tabular-nums">${{ number_format($total, 2) }}</p>
            </div>
            <button wire:click="exportar" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Exportar Excel</button>
        </div>
    </div>

    <div class="flex flex-wrap gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <select wire:model.live="sedeId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Todas las sedes</option>
            @foreach ($sedes as $s) <option value="{{ $s->id }}">{{ $s->clave }}</option> @endforeach
        </select>
        <select wire:model.live="carreraId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Todas las carreras</option>
            @foreach ($carreras as $c) <option value="{{ $c->id }}">{{ $c->nombre }}</option> @endforeach
        </select>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">Matrícula</th><th class="px-4 py-3">Alumno</th><th class="px-4 py-3">Sede</th><th class="px-4 py-3">Carrera / Grupo</th>
                    <th class="px-4 py-3">Bloqueo desde</th><th class="px-4 py-3">Conceptos vencidos</th><th class="px-4 py-3 text-right">Saldo</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($filas as $f)
                    <tr wire:key="bl-{{ $f['alumno']->matricula }}" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-2.5 font-mono font-semibold">{{ $f['alumno']->matricula }}</td>
                        <td class="px-4 py-2.5">{{ $f['alumno']->nombre_completo }}</td>
                        <td class="whitespace-nowrap px-4 py-2.5">{{ $f['alumno']->sede->clave }}</td>
                        <td class="px-4 py-2.5 text-xs">{{ $f['alumno']->carrera->nombre }}<span class="block font-mono text-slate-500">{{ $f['alumno']->grupo }}</span></td>
                        <td class="whitespace-nowrap px-4 py-2.5 font-semibold text-red-700">{{ $f['desde']->format('d/m/Y') }}</td>
                        <td class="px-4 py-2.5 text-xs">{{ $f['conceptos'] }}</td>
                        <td class="px-4 py-2.5 text-right font-bold tabular-nums">${{ number_format($f['saldo'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right">
                            @hasanyrole('Administrador|Cajero')
                                <a href="{{ route('caja.estado-cuenta', $f['alumno']->matricula) }}" wire:navigate class="text-sm font-medium text-emerald-700 hover:underline">Cobrar</a>
                            @endhasanyrole
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">Nadie para bloquear hoy.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
