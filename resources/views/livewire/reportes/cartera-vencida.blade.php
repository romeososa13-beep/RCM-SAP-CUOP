<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Cartera vencida</h1>
            <p class="text-sm text-slate-500">Adeudos no pagados con vencimiento anterior a hoy ({{ today()->format('d/m/Y') }})</p>
        </div>
        <div class="rounded-xl bg-red-600 px-5 py-3 text-white shadow">
            <p class="text-xs uppercase tracking-wide text-red-100">{{ (int) $resumen->alumnos }} alumnos</p>
            <p class="text-2xl font-bold tabular-nums">${{ number_format((float) $resumen->total, 2) }}</p>
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
                    <th class="px-4 py-3 text-center">Conceptos</th><th class="px-4 py-3">Más antiguo</th><th class="px-4 py-3 text-right">Saldo vencido</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($filas as $f)
                    <tr wire:key="cv-{{ $f->matricula }}" class="hover:bg-slate-50">
                        <td class="px-4 py-2.5 font-mono font-semibold">{{ $f->matricula }}</td>
                        <td class="px-4 py-2.5">{{ $f->nombre_completo }}
                            @if ($f->estatus !== 'ACTIVO') <span class="ml-1 rounded bg-amber-100 px-1.5 text-xs text-amber-800">{{ $f->estatus }}</span> @endif</td>
                        <td class="px-4 py-2.5">{{ $f->sede }}</td>
                        <td class="px-4 py-2.5 text-xs">{{ $f->carrera }}<span class="block font-mono text-slate-500">{{ $f->grupo }}</span></td>
                        <td class="px-4 py-2.5 text-center">{{ $f->conceptos }}</td>
                        <td class="px-4 py-2.5 text-red-700">{{ \Illuminate\Support\Carbon::parse($f->mas_antiguo)->format('d/m/Y') }}</td>
                        <td class="px-4 py-2.5 text-right font-bold tabular-nums text-red-700">${{ number_format((float) $f->saldo_vencido, 2) }}</td>
                        <td class="px-4 py-2.5 text-right"><a href="{{ route('caja.estado-cuenta', $f->matricula) }}" wire:navigate class="text-sm font-medium text-emerald-700 hover:underline">Cobrar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">Sin cartera vencida con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-slate-200 px-4 py-3">{{ $filas->links() }}</div>
    </div>
</div>
