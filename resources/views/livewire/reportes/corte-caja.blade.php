<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Corte de caja</h1>
            <p class="text-sm text-slate-500">Totales por forma de pago · pagos cancelados excluidos ({{ $corte['cancelados'] }} en el periodo)</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('reportes.corte-caja.exportar', ['formato' => 'xlsx'] + $params) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">Exportar Excel</a>
            <a href="{{ route('reportes.corte-caja.exportar', ['formato' => 'pdf'] + $params) }}" target="_blank" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Exportar PDF</a>
        </div>
    </div>

    {{-- Estado del corte del día --}}
    @if ($puedeCerrar)
        @php($ec = $this->estadoCorte)
        <div @class(['flex flex-wrap items-center justify-between gap-3 rounded-xl px-5 py-4 shadow-sm ring-1',
            'bg-slate-900 text-white ring-slate-900' => $ec?->esta_cerrado,
            'bg-white ring-slate-200' => ! $ec?->esta_cerrado])>
            <div class="text-sm">
                @if ($ec?->esta_cerrado)
                    <p class="font-semibold">🔒 Corte cerrado el {{ $ec->cerrado_at->format('d/m/Y H:i') }} por {{ $ec->cerradoPor->name }}</p>
                    <p class="text-slate-300">{{ $ec->num_pagos }} pagos por ${{ number_format($ec->total, 2) }}. Ese día ya no se puede cobrar ni cancelar pagos de este cajero.</p>
                @else
                    <p class="font-semibold">Corte abierto</p>
                    <p class="text-slate-500">
                        @if ($ec?->reabierto_at) Reabierto el {{ $ec->reabierto_at->format('d/m/Y H:i') }} por {{ $ec->reabiertoPor?->name }} · {{ $ec->motivo_reapertura }}.
                        @else Al terminar el día, ciérrelo: queda la foto de los totales y ya no se podrán cancelar estos pagos. @endif
                    </p>
                @endif
            </div>
            @if ($ec?->esta_cerrado)
                @if ($esAdmin)
                    <button wire:click="$set('modalReabrir', true)" class="rounded-lg border border-slate-500 px-4 py-2 text-sm hover:bg-slate-800">Reabrir corte</button>
                @endif
            @else
                <button wire:click="cerrarCorte" wire:confirm="¿Cerrar el corte del {{ \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') }}? Después ya no se podrá cobrar ni cancelar pagos de ese día."
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Cerrar corte del día</button>
            @endif
        </div>
    @elseif ($esAdmin && ! $cajeroId)
        <p class="text-xs text-slate-500">Para cerrar o reabrir un corte, elija un solo día y un cajero.</p>
    @endif

    <div class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div><label class="mb-1 block text-xs font-medium text-slate-500">Desde</label>
            <input type="date" wire:model.live="desde" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
        <div><label class="mb-1 block text-xs font-medium text-slate-500">Hasta</label>
            <input type="date" wire:model.live="hasta" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
        @if ($esAdmin)
            <div><label class="mb-1 block text-xs font-medium text-slate-500">Cajero</label>
                <select wire:model.live="cajeroId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($this->cajeros as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
                </select></div>
        @endif
        <button wire:click="hoy" class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Hoy</button>
        @error('hasta') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
        @foreach ($corte['totales'] as $t)
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $t['etiqueta'] }}</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">${{ number_format($t['monto'], 2) }}</p>
                <p class="text-xs text-slate-500">{{ $t['num'] }} operaciones</p>
            </div>
        @endforeach
        <div class="rounded-xl bg-slate-900 p-4 text-white shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-300">Total</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">${{ number_format($corte['total'], 2) }}</p>
            <p class="text-xs text-slate-300">{{ $corte['num'] }} operaciones</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">Folio</th><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Alumno</th><th class="px-4 py-3">Concepto</th>
                    <th class="px-4 py-3">Forma</th><th class="px-4 py-3">Cajero</th><th class="px-4 py-3 text-right">Importe</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($corte['pagos'] as $p)
                    <tr wire:key="cp-{{ $p->id }}">
                        <td class="px-4 py-2 font-mono font-semibold"><a href="{{ route('caja.recibo', $p) }}" target="_blank" class="hover:underline">{{ $p->folio_pago }}</a></td>
                        <td class="px-4 py-2">{{ $p->fecha_pago->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2"><span class="font-mono text-xs">{{ $p->adeudo->matricula }}</span> {{ $p->adeudo->alumno->nombre_completo }}</td>
                        <td class="px-4 py-2">{{ $p->adeudo->concepto }}</td>
                        <td class="px-4 py-2">{{ $p->forma_pago->etiqueta() }}</td>
                        <td class="px-4 py-2 text-xs">{{ $p->cajero->name }}</td>
                        <td class="px-4 py-2 text-right font-semibold tabular-nums">${{ number_format($p->monto_pagado, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Sin pagos en el periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($this->cortesRecientes->isNotEmpty())
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <h2 class="border-b border-slate-200 px-6 py-3 font-semibold">Cortes cerrados recientes</h2>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->cortesRecientes as $c)
                        <tr wire:key="cr-{{ $c->id }}">
                            <td class="px-4 py-2 font-medium">{{ $c->fecha->format('d/m/Y') }}</td>
                            <td class="px-4 py-2">{{ $c->cajero->name }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $c->num_pagos }} pagos · ${{ number_format($c->total, 2) }}</td>
                            <td class="px-4 py-2 text-right text-xs">
                                @if ($c->esta_cerrado) <span class="rounded bg-slate-900 px-2 py-0.5 font-semibold text-white">CERRADO</span>
                                @else <span class="rounded bg-amber-100 px-2 py-0.5 font-semibold text-amber-800">REABIERTO</span> @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($esAdmin)
        <div x-data="{ open: @entangle('modalReabrir') }" x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60" x-on:click="open = false"></div>
            <form wire:submit="reabrirCorte" class="relative w-full max-w-md rounded-xl bg-white shadow-2xl">
                <div class="border-b border-slate-200 px-6 py-4"><h3 class="text-lg font-bold">Reabrir corte</h3>
                    <p class="text-sm text-slate-500">Permite volver a cobrar y cancelar pagos de ese día. Queda en la bitácora.</p></div>
                <div class="px-6 py-5">
                    <label class="mb-1 block text-sm font-semibold">Motivo</label>
                    <input wire:model="motivoReapertura" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('motivoReapertura') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-6 py-4">
                    <button type="button" x-on:click="open = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm">Volver</button>
                    <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Reabrir</button>
                </div>
            </form>
        </div>
    @endif
</div>
