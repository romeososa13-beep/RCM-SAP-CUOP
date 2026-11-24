@php
    $m = fn ($v) => '$'.number_format($v, 0);
    $pct = fn ($v) => $v === null ? '—' : number_format($v * 100, 1).'%';
    $mesNombre = fn ($ym) => ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun',
        '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'][substr($ym, 5, 2)].' '.substr($ym, 0, 4);
    $tipos = ['COLEGIATURA' => 'Colegiaturas', 'INGLES' => 'Inglés', 'OTRO' => 'Otros (inscripción, títulos, trámites…)'];
    $tot = $t['total'];
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Tablero ejecutivo</h1>
            <p class="text-sm text-slate-500">
                {{ $periodos[$periodo] ?? $periodo }} · vencimientos del {{ $t['desde']->format('d/m/Y') }} al {{ $t['hasta']->format('d/m/Y') }}
                · cifras al {{ $t['hoy']->format('d/m/Y') }}
            </p>
        </div>
        <div class="flex gap-2">
            <select wire:model.live="periodo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach ($periodos as $k => $label) <option value="{{ $k }}">{{ $label }}</option> @endforeach
            </select>
            <select wire:model.live="sedeId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todas las sedes</option>
                @foreach ($sedes as $s) <option value="{{ $s->id }}">{{ $s->clave }}</option> @endforeach
            </select>
        </div>
    </div>

    {{-- Tarjetas --}}
    <div class="relative grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div wire:loading.flex class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-slate-100/70 text-sm text-slate-500">Calculando…</div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Ingreso esperado</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $m($tot['esperado']) }}</p>
            <p class="text-xs text-slate-500">{{ number_format($tot['activos']) }} alumnos activos</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Recuperado</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-700">{{ $m($tot['recuperado']) }}</p>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-2 rounded-full bg-emerald-500" style="width: {{ min(100, ($tot['pct_recuperado'] ?? 0) * 100) }}%"></div></div>
            <p class="mt-1 text-xs text-slate-500">{{ $pct($tot['pct_recuperado']) }} del esperado</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Por recuperar</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $m($tot['por_recuperar']) }}</p>
            <dl class="mt-1 space-y-0.5 text-xs">
                <div class="flex justify-between"><dt class="text-slate-500">de meses anteriores</dt><dd class="font-semibold tabular-nums text-red-700">{{ $m($tot['anteriores']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">del mes en curso</dt><dd class="font-semibold tabular-nums text-amber-700">{{ $m($tot['mes']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">por vencer</dt><dd class="tabular-nums text-slate-600">{{ $m($tot['por_vencer']) }}</dd></div>
            </dl>
        </div>
        <div class="rounded-xl bg-slate-900 p-4 text-white shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-300">Avance de la cobranza</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $pct($tot['avance']) }}</p>
            <p class="text-xs text-slate-300">de lo que ya tocaba pagar</p>
            <div class="mt-2 flex gap-4 text-xs">
                <span><strong class="text-base">{{ $tot['deben'] }}</strong> deben</span>
                <a href="{{ route('reportes.bloqueo') }}" wire:navigate class="hover:underline"><strong class="text-base text-red-300">{{ $tot['bloqueos'] }}</strong> con bloqueo</a>
            </div>
        </div>
    </div>

    {{-- Por sede --}}
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <h2 class="border-b border-slate-200 px-6 py-3 font-semibold">Por sede</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3 text-left">Sede</th><th class="px-4 py-3">Activos</th><th class="px-4 py-3">Esperado</th><th class="px-4 py-3">Recuperado</th>
                        <th class="px-4 py-3 text-left">% recuperado</th><th class="px-4 py-3">Vencido + mes</th><th class="px-4 py-3">Por vencer</th>
                        <th class="px-4 py-3">Deben</th><th class="px-4 py-3">Bloqueos</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-right tabular-nums">
                    @foreach ($t['por_sede'] as $r)
                        <tr wire:key="ts-{{ $r['sede']->id }}" class="hover:bg-slate-50">
                            <td class="px-4 py-2.5 text-left"><span class="font-mono font-semibold">{{ $r['sede']->clave }}</span>
                                <span class="block text-xs text-slate-500">{{ $r['sede']->nombre }}</span></td>
                            <td class="px-4 py-2.5">{{ number_format($r['activos']) }}</td>
                            <td class="px-4 py-2.5">{{ $m($r['esperado']) }}</td>
                            <td class="px-4 py-2.5 text-emerald-700">{{ $m($r['recuperado']) }}</td>
                            <td class="px-4 py-2.5 text-left">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-28 overflow-hidden rounded-full bg-slate-100"><div class="h-2 rounded-full bg-emerald-500" style="width: {{ min(100, ($r['pct_recuperado'] ?? 0) * 100) }}%"></div></div>
                                    <span class="text-xs">{{ $pct($r['pct_recuperado']) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 font-semibold text-red-700">{{ $m($r['anteriores'] + $r['mes']) }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $m($r['por_vencer']) }}</td>
                            <td class="px-4 py-2.5">{{ $r['deben'] }}</td>
                            <td class="px-4 py-2.5">{{ $r['bloqueos'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                @if ($t['por_sede']->count() > 1)
                    <tfoot class="bg-slate-50 text-right font-semibold tabular-nums">
                        <tr><td class="px-4 py-3 text-left">Total</td><td class="px-4 py-3">{{ number_format($tot['activos']) }}</td>
                            <td class="px-4 py-3">{{ $m($tot['esperado']) }}</td><td class="px-4 py-3 text-emerald-700">{{ $m($tot['recuperado']) }}</td>
                            <td class="px-4 py-3 text-left text-xs">{{ $pct($tot['pct_recuperado']) }}</td>
                            <td class="px-4 py-3 text-red-700">{{ $m($tot['anteriores'] + $tot['mes']) }}</td><td class="px-4 py-3">{{ $m($tot['por_vencer']) }}</td>
                            <td class="px-4 py-3">{{ $tot['deben'] }}</td><td class="px-4 py-3">{{ $tot['bloqueos'] }}</td></tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Mes por mes --}}
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <h2 class="border-b border-slate-200 px-6 py-3 font-semibold">Mes por mes <span class="text-xs font-normal text-slate-500">(por mes de vencimiento)</span></h2>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-2 text-left">Mes</th><th class="px-4 py-2">Esperado</th><th class="px-4 py-2">Recuperado</th><th class="px-4 py-2">Falta</th><th class="px-4 py-2">%</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-right tabular-nums">
                    @forelse ($t['por_mes'] as $r)
                        <tr wire:key="tm-{{ $r['mes'] }}">
                            <td class="px-4 py-2 text-left font-medium">{{ $mesNombre($r['mes']) }}</td>
                            <td class="px-4 py-2">{{ $m($r['esperado']) }}</td><td class="px-4 py-2 text-emerald-700">{{ $m($r['recuperado']) }}</td>
                            <td class="px-4 py-2">{{ $m($r['por_recuperar']) }}</td><td class="px-4 py-2 text-xs">{{ $pct($r['pct_recuperado']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Sin cargos en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Por tipo --}}
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <h2 class="border-b border-slate-200 px-6 py-3 font-semibold">Por tipo de cargo</h2>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-2 text-left">Tipo</th><th class="px-4 py-2">Esperado</th><th class="px-4 py-2">Recuperado</th><th class="px-4 py-2">Falta</th><th class="px-4 py-2">%</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-right tabular-nums">
                    @forelse ($t['por_tipo'] as $r)
                        <tr wire:key="tt-{{ $r['tipo'] }}">
                            <td class="px-4 py-2 text-left font-medium">{{ $tipos[$r['tipo']] ?? $r['tipo'] }}</td>
                            <td class="px-4 py-2">{{ $m($r['esperado']) }}</td><td class="px-4 py-2 text-emerald-700">{{ $m($r['recuperado']) }}</td>
                            <td class="px-4 py-2">{{ $m($r['por_recuperar']) }}</td><td class="px-4 py-2 text-xs">{{ $pct($r['pct_recuperado']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Sin cargos en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="border-t border-slate-100 px-6 py-3 text-xs text-slate-500">
                Recuperado incluye lo cobrado en esta caja y lo abonado en el sistema anterior (saldos migrados).
                Lo pagado antes de la migración en meses ya cerrados queda en INTELL/EDUCAPP.
            </p>
        </div>
    </div>
</div>
