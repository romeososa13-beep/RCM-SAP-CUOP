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
</div>
