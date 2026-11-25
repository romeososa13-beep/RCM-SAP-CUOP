@php($m = fn ($v) => '$'.number_format($v, 2))
@php($tipos = ['COLEGIATURA' => 'Colegiatura', 'INGLES' => 'Inglés', 'OTRO' => 'Otro'])
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Ingresos</h1>
            <p class="text-sm text-slate-500">Cobrado en caja del {{ $r['desde']->format('d/m/Y') }} al {{ $r['hasta']->format('d/m/Y') }} · pagos cancelados excluidos</p>
        </div>
        <button wire:click="exportar" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Exportar Excel</button>
    </div>

    <div class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div><label class="mb-1 block text-xs font-medium text-slate-500">Desde</label>
            <input type="date" wire:model.live="desde" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
        <div><label class="mb-1 block text-xs font-medium text-slate-500">Hasta</label>
            <input type="date" wire:model.live="hasta" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
        <select wire:model.live="sedeId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Todas las sedes</option>
            @foreach ($sedes as $s) <option value="{{ $s->id }}">{{ $s->clave }}</option> @endforeach
        </select>
        <div class="flex gap-1 text-sm">
            @foreach (['hoy' => 'Hoy', 'mes' => 'Este mes', 'mes_anterior' => 'Mes anterior', 'anio' => 'Este año'] as $k => $label)
                <button wire:click="rango('{{ $k }}')" class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-50">{{ $label }}</button>
            @endforeach
        </div>
        @error('hasta') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
        @foreach ($formas as $f)
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $f->etiqueta() }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums">{{ $m($r['por_forma'][$f->value]) }}</p>
            </div>
        @endforeach
        <div class="rounded-xl bg-emerald-700 p-4 text-white shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-emerald-100">Total cobrado</p>
            <p class="mt-1 text-xl font-bold tabular-nums">{{ $m($r['total']) }}</p>
            <p class="text-xs text-emerald-100">{{ number_format($r['num']) }} pagos</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <h2 class="border-b border-slate-200 px-6 py-3 font-semibold">Por concepto</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3 text-left">Concepto</th><th class="px-4 py-3">Pagos</th>
                        @foreach ($formas as $f) <th class="px-4 py-3">{{ $f->etiqueta() }}</th> @endforeach
                        <th class="px-4 py-3">Total</th><th class="px-4 py-3">%</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-right tabular-nums">
                    @forelse ($r['por_concepto'] as $g)
                        <tr wire:key="ic-{{ md5($g['clave']) }}">
                            <td class="px-4 py-2.5 text-left font-medium">{{ $g['clave'] }}
                                <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500">{{ $tipos[$g['tipo']] }}</span></td>
                            <td class="px-4 py-2.5">{{ $g['num'] }}</td>
                            @foreach ($formas as $f) <td class="px-4 py-2.5 text-slate-600">{{ $g['por_forma'][$f->value] > 0 ? $m($g['por_forma'][$f->value]) : '—' }}</td> @endforeach
                            <td class="px-4 py-2.5 font-semibold">{{ $m($g['total']) }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-500">{{ $r['total'] > 0 ? number_format($g['total'] / $r['total'] * 100, 1) : 0 }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-10 text-center text-slate-500">Sin pagos en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach (['por_sede' => 'Por sede', 'por_dia' => 'Por día'] as $clave => $titulo)
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                <h2 class="border-b border-slate-200 px-6 py-3 font-semibold">{{ $titulo }}</h2>
                <div class="max-h-96 overflow-y-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <tbody class="divide-y divide-slate-100 tabular-nums">
                            @forelse ($r[$clave] as $g)
                                <tr wire:key="{{ $clave }}-{{ $g['clave'] }}">
                                    <td class="px-4 py-2 font-medium">{{ $clave === 'por_dia' ? \Illuminate\Support\Carbon::parse($g['clave'])->format('d/m/Y') : $g['clave'] }}</td>
                                    <td class="px-4 py-2 text-right text-xs text-slate-500">{{ $g['num'] }} pagos</td>
                                    <td class="px-4 py-2 text-right font-semibold">{{ $m($g['total']) }}</td>
                                </tr>
                            @empty
                                <tr><td class="px-4 py-6 text-center text-slate-500">—</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</div>
