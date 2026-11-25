<div class="space-y-6"
     x-data
     x-on:abrir-recibo.window="window.open($event.detail.url, '_blank')"
     x-on:enfocar-buscador.window="$nextTick(() => document.getElementById('buscador-alumno')?.focus())">

    {{-- =============================================================
         ENCABEZADO
       ============================================================= --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Estado de cuenta</h1>
            <p class="text-sm text-slate-500">Caja · consulta de adeudos, cobro y reimpresión de recibos</p>
        </div>

        @if ($ultimoReciboUrl)
            <a href="{{ $ultimoReciboUrl }}" target="_blank"
               class="inline-flex items-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Abrir recibo folio {{ $ultimoFolio }}
            </a>
        @endif
    </div>

    {{-- =============================================================
         1) BUSCADOR PREDICTIVO
       ============================================================= --}}
    @if (! $this->alumno)
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
             x-data="{ idx: -1, total: {{ $this->resultados->count() }} }"
             x-effect="total = {{ $this->resultados->count() }}; if (idx >= total) idx = total - 1">
            <label for="buscador-alumno" class="mb-2 block text-sm font-semibold text-slate-700">Buscar alumno</label>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                </div>
                <input id="buscador-alumno" type="text" autocomplete="off" autofocus
                       wire:model.live.debounce.300ms="search"
                       x-on:keydown.arrow-down.prevent="idx = Math.min(idx + 1, total - 1)"
                       x-on:keydown.arrow-up.prevent="idx = Math.max(idx - 1, 0)"
                       x-on:keydown.enter.prevent="idx >= 0 ? $refs['res' + idx]?.click() : $wire.seleccionarPrimero()"
                       x-on:keydown.escape="$wire.set('search', '')"
                       placeholder="Matrícula o nombre del alumno (mín. 2 caracteres)…"
                       class="w-full rounded-lg border border-slate-300 py-3 pl-10 pr-10 text-base focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                <div wire:loading wire:target="search" class="absolute inset-y-0 right-0 flex items-center pr-3">
                    <svg class="h-5 w-5 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                </div>

                @if (mb_strlen(trim($search)) >= 2)
                    <ul class="absolute z-30 mt-1 max-h-80 w-full overflow-auto rounded-lg bg-white py-1 shadow-lg ring-1 ring-slate-200">
                        @forelse ($this->resultados as $i => $r)
                            <li wire:key="res-{{ $r->matricula }}">
                                <button type="button" x-ref="res{{ $i }}"
                                        wire:click="seleccionarAlumno('{{ $r->matricula }}')"
                                        x-on:mouseenter="idx = {{ $i }}"
                                        :class="idx === {{ $i }} ? 'bg-emerald-50' : ''"
                                        class="flex w-full items-center justify-between gap-4 px-4 py-2.5 text-left hover:bg-emerald-50">
                                    <span>
                                        <span class="font-mono text-sm font-semibold text-slate-900">{{ $r->matricula }}</span>
                                        <span class="ml-2 text-sm text-slate-700">{{ $r->nombre_completo }}</span>
                                        <span class="block text-xs text-slate-500">{{ $r->sede?->clave }} · {{ $r->grupo }}</span>
                                    </span>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $r->estatus->color() }}">
                                        {{ $r->estatus->etiqueta() }}
                                    </span>
                                </button>
                            </li>
                        @empty
                            <li class="px-4 py-3 text-sm text-slate-500">Sin coincidencias para “{{ $search }}”.</li>
                        @endforelse
                    </ul>
                @endif
            </div>
            <p class="mt-2 text-xs text-slate-400">↑ ↓ para navegar · Enter para seleccionar · Esc para limpiar</p>
        </div>
    @else
        @php($alumno = $this->alumno)

        {{-- =============================================================
             2) FICHA RESUMEN DEL ALUMNO
           ============================================================= --}}
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-600 text-lg font-bold text-white">
                        {{ mb_substr($alumno->nombre_completo, 0, 1) }}
                    </div>
                    <div>
                        <h2 class="text-lg font-bold leading-tight text-slate-900">{{ $alumno->nombre_completo }}</h2>
                        <p class="font-mono text-sm text-slate-600">{{ $alumno->matricula }}
                            @if ($alumno->email) <span class="ml-3 text-xs text-slate-500">✉ {{ $alumno->email }}</span> @endif
                            @foreach ($alumno->telefonos as $tipo => $tel)
                                <a href="tel:{{ $tel }}" class="ml-3 text-xs text-slate-500 hover:text-emerald-700" title="{{ $tipo }}">☎ {{ $tipo }}: {{ $tel }}</a>
                            @endforeach
                        </p>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $alumno->estatus->color() }}">
                        {{ $alumno->estatus->etiqueta() }}
                    </span>
                </div>
                <div class="flex gap-2">
                    @if ($alumno->celular || $alumno->tel_tutor)
                        <button wire:click="abrirModalWhatsApp"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm5.3 14.2c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2.1 1-2.4c.3-.3.6-.3.8-.3h.6c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .5l-.4.6-.4.4c-.1.1-.3.3-.1.6.2.3.8 1.4 1.8 2.2 1.2 1.1 2.3 1.4 2.6 1.6.3.1.5.1.7-.1l.9-1.1c.2-.3.4-.2.7-.1l2 .9c.3.1.5.2.6.3.1.2.1.8-.1 1.4z"/></svg>
                            WhatsApp
                        </button>
                    @endif
                    <button wire:click="abrirModalComentarios"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4-.83L3 20l1.4-3.7A7.96 7.96 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        Comentarios
                    </button>
                    <button wire:click="cambiarAlumno"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        Cambiar alumno
                    </button>
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-x-6 gap-y-4 px-6 py-5 text-sm md:grid-cols-4">
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Sede</dt>
                    <dd class="mt-0.5 font-semibold">{{ $alumno->sede->clave }}</dd>
                    <dd class="text-xs text-slate-500">{{ $alumno->sede->nombre }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Carrera</dt>
                    <dd class="mt-0.5 font-semibold">{{ $alumno->carrera->nombre }}</dd>
                    <dd class="text-xs text-slate-500">{{ $alumno->carrera->modalidad }}</dd></div>
                <div class="col-span-2"><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Grupo</dt>
                    <dd class="mt-0.5 font-mono font-semibold">{{ $alumno->grupo }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Plan de pago ID</dt>
                    @if ($alumno->planPago)
                        <dd class="mt-0.5 font-semibold">#{{ $alumno->planPago->id }}</dd>
                        <dd class="text-xs text-slate-500">{{ $alumno->planPago->nombre }}</dd>
                    @else
                        <dd class="mt-0.5 font-semibold text-amber-700">Sin plan</dd>
                    @endif</div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Monto mensualidad</dt>
                    <dd class="mt-0.5 font-semibold">{{ $alumno->planPago ? '$'.number_format($alumno->planPago->monto_mensualidad, 2) : '—' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Recargo en mensualidad</dt>
                    <dd class="mt-0.5 font-semibold">{{ $alumno->planPago ? '$'.number_format($alumno->planPago->monto_recargo, 2) : '—' }}</dd>
                    @if ($alumno->planPago) <dd class="text-xs text-slate-500">Sin recargo hasta el día {{ $alumno->planPago->dias_tolerancia }}</dd> @endif</div>
                <div class="rounded-lg bg-slate-900 px-4 py-3 text-white">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-300">Saldo total</dt>
                    <dd class="text-xl font-bold">${{ number_format($this->totales['saldo'], 2) }}</dd>
                    @if ($this->totales['vencido'] > 0)
                        <dd class="text-xs font-semibold text-red-300">Vencido: ${{ number_format($this->totales['vencido'], 2) }}</dd>
                    @endif
                </div>
            </dl>

            @if ($this->bloqueo)
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-red-300 bg-red-600 px-6 py-3 text-sm text-white">
                    <span class="rounded bg-white px-2 py-0.5 text-xs font-bold tracking-wide text-red-700">BLOQUEO DE PLATAFORMAS</span>
                    <span>Desde el {{ $this->bloqueo['desde']->format('d/m/Y') }} por adeudo de
                        <strong>{{ implode(', ', $this->bloqueo['conceptos']) }}</strong>
                        (${{ number_format($this->bloqueo['monto'], 2) }}). Se libera al liquidar esos conceptos.</span>
                </div>
            @endif
            @if ((float) $alumno->beca_porcentaje > 0)
                <div class="border-t border-slate-200 bg-sky-50 px-6 py-2 text-sm text-sky-900">
                    Beca de <strong>{{ rtrim(rtrim(number_format($alumno->beca_porcentaje, 2), '0'), '.') }}%</strong>
                    en colegiatura{{ $alumno->beca_ingles ? ' e inglés' : '' }}@if ($alumno->beca_motivo) · {{ $alumno->beca_motivo }}@endif.
                    Se aplica al generar cada mensualidad.
                </div>
            @endif
            @if ($alumno->costo_ingles)
                <div class="border-t border-slate-200 bg-sky-50 px-6 py-2 text-sm text-sky-900">
                    Cursa inglés: <strong>${{ number_format($alumno->costo_ingles, 2) }}</strong> mensuales
                    (recargo {{ rtrim(rtrim(number_format(config('cobranza.recargo_ingles_porcentaje'), 2), '0'), '.') }}% después del día de tolerancia).
                </div>
            @endif
            @if ($alumno->comentarios)
                <div class="border-t border-amber-200 bg-amber-50 px-6 py-3 text-sm text-amber-900">
                    <span class="font-semibold">Nota administrativa:</span> {{ $alumno->comentarios }}
                </div>
            @endif
            @if ($alumno->estatus !== \App\Enums\EstatusAlumno::ACTIVO)
                <div class="border-t border-red-200 bg-red-50 px-6 py-2 text-sm font-medium text-red-800">
                    Alumno con estatus {{ $alumno->estatus->etiqueta() }}: no se generan nuevas mensualidades.
                </div>
            @endif
        </div>

        {{-- =============================================================
             3) TABLA DE ADEUDOS
           ============================================================= --}}
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-3">
                <h3 class="font-semibold text-slate-900">
                    Adeudos
                    <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $this->totales['conceptos'] }} pendientes</span>
                </h3>
                <div class="flex items-center gap-4">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model.live="mostrarPagados" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        Mostrar pagados
                    </label>
                    <button wire:click="abrirModalCargo"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        + Agregar cargo
                    </button>
                </div>
            </div>

            <div class="relative overflow-x-auto">
                <div wire:loading.flex wire:target="mostrarPagados,cobrar,cancelarPago"
                     class="absolute inset-0 z-10 items-center justify-center bg-white/60">
                    <span class="text-sm text-slate-500">Actualizando…</span>
                </div>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">AdeudoID</th>
                            <th class="px-4 py-3">Concepto</th>
                            <th class="px-4 py-3">Vencimiento</th>
                            <th class="px-4 py-3 text-right">Monto</th>
                            <th class="px-4 py-3 text-right">Pagos</th>
                            <th class="px-4 py-3 text-right">Adeudo</th>
                            <th class="px-4 py-3 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->adeudos as $adeudo)
                            <tr wire:key="adeudo-{{ $adeudo->id }}" @class(['bg-slate-50 text-slate-400' => $adeudo->pagado, 'hover:bg-slate-50' => ! $adeudo->pagado])>
                                <td class="px-4 py-3 font-mono">{{ $adeudo->id }}</td>
                                <td class="px-4 py-3 font-medium">{{ $adeudo->concepto }}
                                    <span @class(['ml-1 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                        'bg-slate-100 text-slate-600' => $adeudo->tipo === \App\Enums\TipoAdeudo::COLEGIATURA,
                                        'bg-sky-100 text-sky-700' => $adeudo->tipo === \App\Enums\TipoAdeudo::INGLES,
                                        'bg-violet-100 text-violet-700' => $adeudo->tipo === \App\Enums\TipoAdeudo::OTRO])>{{ $adeudo->tipo === \App\Enums\TipoAdeudo::OTRO ? 'Otro' : $adeudo->tipo->etiqueta() }}</span>
                                    @if ($adeudo->origen === 'MIGRACION') <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800" title="Saldo traído del sistema anterior">MIGRADO</span> @endif
                                    @if ($adeudo->causa_bloqueo) <span class="ml-1 rounded bg-red-600 px-1.5 py-0.5 text-[10px] font-bold text-white">BLOQUEO</span> @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span @class(['rounded px-1.5 py-0.5 font-semibold text-red-700 bg-red-50' => $adeudo->esta_vencido])>
                                        {{ $adeudo->fecha_vencimiento->format('d/m/Y') }}
                                    </span>
                                    @if ($adeudo->esta_vencido)
                                        <span class="ml-1 text-xs font-medium text-red-600">VENCIDO</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    ${{ number_format($adeudo->monto_original, 2) }}
                                    @if ((float) $adeudo->descuento > 0)
                                        <span class="block text-xs text-sky-700" title="Monto sin beca: ${{ number_format($adeudo->monto_original + $adeudo->descuento, 2) }}">beca − ${{ number_format($adeudo->descuento, 2) }}</span>
                                    @endif
                                    @if ((float) $adeudo->recargo_aplicado > 0)
                                        <span class="block text-xs text-red-600">+ ${{ number_format($adeudo->recargo_aplicado, 2) }} recargo
                                            @role('Administrador')
                                                @unless ($adeudo->pagado)
                                                    <button wire:click="abrirModalCondonar({{ $adeudo->id }})" class="ml-1 font-medium text-sky-700 underline hover:text-sky-900">condonar</button>
                                                @endunless
                                            @endrole
                                        </span>
                                    @elseif ($adeudo->exento_recargo && $adeudo->esta_vencido)
                                        <span class="block text-xs text-slate-400">exento de recargo</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-emerald-700">${{ number_format($adeudo->total_pagado, 2) }}
                                    @if ((float) $adeudo->pagado_previo > 0)
                                        <span class="block text-xs text-slate-500" title="Abonado en el sistema anterior (INTELL/EDUCAPP)">${{ number_format($adeudo->pagado_previo, 2) }} en sistema anterior</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums">${{ number_format($adeudo->saldo_pendiente, 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if ($adeudo->saldo_pendiente > 0)
                                        <button wire:click="abrirModalCobro({{ $adeudo->id }})"
                                                class="rounded-md bg-emerald-600 px-4 py-1.5 text-xs font-bold tracking-wide text-white shadow-sm hover:bg-emerald-700">
                                            COBRAR
                                        </button>
                                    @else
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">PAGADO</span>
                                    @endif
                                    @role('Administrador')
                                        @if ($adeudo->pagos_historicos === 0 && $adeudo->origen !== 'MIGRACION')
                                            <button wire:click="eliminarCargo({{ $adeudo->id }})"
                                                    wire:confirm="¿Eliminar el cargo «{{ $adeudo->concepto }}»? Solo procede si nunca tuvo pagos."
                                                    title="Eliminar cargo capturado por error"
                                                    class="ml-1 rounded-md px-1.5 py-1 text-xs text-slate-400 hover:bg-red-50 hover:text-red-600">✕</button>
                                        @endif
                                    @endrole
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">
                                El alumno no tiene adeudos pendientes. 🎉
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($this->adeudos->isNotEmpty())
                        <tfoot class="bg-slate-50 font-semibold">
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-right text-slate-600">Saldo pendiente total</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-900">${{ number_format($this->totales['saldo'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- =============================================================
             5) HISTORIAL DE PAGOS
           ============================================================= --}}
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="border-b border-slate-200 px-6 py-3">
                <h3 class="font-semibold text-slate-900">Historial de pagos</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">AdeudoID</th>
                            <th class="px-4 py-3">Folio pago</th>
                            <th class="px-4 py-3">Concepto</th>
                            <th class="px-4 py-3">Fecha pago</th>
                            <th class="px-4 py-3">Forma pago</th>
                            <th class="px-4 py-3 text-right">Monto</th>
                            <th class="px-4 py-3 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->pagos as $pago)
                            <tr wire:key="pago-{{ $pago->id }}" @class(['bg-red-50/50 text-slate-400 line-through decoration-red-400' => $pago->trashed()])>
                                <td class="px-4 py-3 font-mono">{{ $pago->adeudo_id }}</td>
                                <td class="px-4 py-3 font-mono font-semibold">{{ $pago->folio_pago }}</td>
                                <td class="px-4 py-3">{{ $pago->adeudo->concepto }}</td>
                                <td class="px-4 py-3">{{ $pago->fecha_pago->format('d/m/Y H:i') }}
                                    <span class="block text-xs text-slate-400 no-underline">{{ $pago->cajero->name }}</span></td>
                                <td class="px-4 py-3">
                                    {{ $pago->forma_pago->etiqueta() }}
                                    @if ($pago->referencia)<span class="block font-mono text-xs text-slate-400">{{ $pago->referencia }}</span>@endif
                                </td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums">${{ number_format($pago->monto_pagado, 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center no-underline">
                                    <a href="{{ route('caja.recibo', $pago) }}" target="_blank"
                                       class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 no-underline hover:bg-slate-50"
                                       title="Reimprimir recibo">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        Recibo
                                    </a>
                                    @if ($this->alumno->email && ! $pago->trashed())
                                        <button wire:click="reenviarRecibo({{ $pago->id }})" title="Enviar recibo por correo a {{ $this->alumno->email }}"
                                                class="ml-1 rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Correo</button>
                                    @endif
                                    @role('Administrador')
                                        @unless ($pago->trashed())
                                            <button wire:click="abrirModalCancelar({{ $pago->id }})"
                                                    class="ml-1 rounded-md border border-red-300 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-50">
                                                Cancelar
                                            </button>
                                        @endunless
                                    @endrole
                                    @if ($pago->trashed())
                                        <span class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs font-semibold text-red-700">CANCELADO</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Sin pagos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- =============================================================
         4) MODAL DE COBRO
       ============================================================= --}}
    <div x-data="{ open: @entangle('modalCobro') }" x-show="open" x-cloak
         x-on:keydown.escape.window="open && $wire.cerrarModalCobro()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-900/60" wire:click="cerrarModalCobro"></div>

        <div x-show="open" x-transition class="relative w-full max-w-lg rounded-xl bg-white shadow-2xl">
            @if ($this->adeudoCobro)
                @php($ac = $this->adeudoCobro)
                <form wire:submit="cobrar">
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                        <h3 class="text-lg font-bold text-slate-900">Registrar cobro</h3>
                        <button type="button" wire:click="cerrarModalCobro" class="text-2xl leading-none text-slate-400 hover:text-slate-600">&times;</button>
                    </div>

                    <div class="space-y-5 px-6 py-5">
                        {{-- Desglose --}}
                        <div class="rounded-lg bg-slate-50 p-4 text-sm ring-1 ring-slate-200">
                            <div class="mb-2 flex justify-between">
                                <span class="font-semibold">{{ $ac->concepto }}</span>
                                <span class="font-mono text-slate-500">#{{ $ac->id }}</span>
                            </div>
                            <dl class="space-y-1">
                                <div class="flex justify-between"><dt class="text-slate-500">Cargo original</dt><dd class="tabular-nums">${{ number_format($ac->monto_original, 2) }}</dd></div>
                                <div class="flex justify-between"><dt class="text-slate-500">Recargo</dt><dd class="tabular-nums text-red-600">+ ${{ number_format($ac->recargo_aplicado, 2) }}</dd></div>
                                <div class="flex justify-between"><dt class="text-slate-500">Abonado</dt><dd class="tabular-nums text-emerald-700">− ${{ number_format($ac->total_pagado, 2) }}</dd></div>
                                <div class="flex justify-between border-t border-slate-200 pt-1 font-bold"><dt>Saldo pendiente</dt><dd class="tabular-nums">${{ number_format($ac->saldo_pendiente, 2) }}</dd></div>
                            </dl>
                        </div>

                        {{-- Monto --}}
                        <div x-data="{ saldo: {{ $ac->saldo_pendiente }} }">
                            <label class="mb-1 block text-sm font-semibold text-slate-700">Monto a pagar</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">$</span>
                                <input type="number" step="0.01" min="0.01" max="{{ $ac->saldo_pendiente }}" wire:model="monto_pagar"
                                       class="w-full rounded-lg border border-slate-300 py-2.5 pl-7 pr-3 text-right text-xl font-bold tabular-nums focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                            </div>
                            <p class="mt-1 text-xs" :class="parseFloat($wire.monto_pagar) < saldo ? 'text-amber-600' : 'text-slate-400'"
                               x-text="parseFloat($wire.monto_pagar) < saldo
                                   ? 'Abono parcial: quedará un saldo de $' + (saldo - (parseFloat($wire.monto_pagar) || 0)).toFixed(2)
                                   : 'Liquida el adeudo completo.'"></p>
                            @error('monto_pagar') <p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Forma de pago --}}
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">Forma de pago</label>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                @foreach ($formasPago as $fp)
                                    <label wire:key="fp-{{ $fp->value }}"
                                           @class([
                                               'flex cursor-pointer items-center justify-center rounded-lg border px-2 py-2 text-sm font-medium transition',
                                               'border-emerald-600 bg-emerald-50 text-emerald-800 ring-1 ring-emerald-600' => $forma_pago === $fp->value,
                                               'border-slate-300 text-slate-600 hover:bg-slate-50' => $forma_pago !== $fp->value,
                                           ])>
                                        <input type="radio" wire:model.live="forma_pago" value="{{ $fp->value }}" class="sr-only">
                                        {{ $fp->etiqueta() }}
                                    </label>
                                @endforeach
                            </div>
                            @error('forma_pago') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Referencia --}}
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">
                                Referencia
                                @if (\App\Enums\FormaPago::from($forma_pago)->requiereReferencia())
                                    <span class="text-red-600">*</span>
                                @else
                                    <span class="font-normal text-slate-400">(opcional)</span>
                                @endif
                            </label>
                            <input type="text" wire:model="referencia" maxlength="100"
                                   placeholder="Núm. de autorización, rastreo SPEI o ficha de depósito"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                            @error('referencia') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="border-t border-slate-100 px-6 py-3">
                        @if (($this->alumno?->celular || $this->alumno?->tel_tutor) && ! $this->alumno?->whatsapp_baja_at)
                            <label class="mb-1 flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" wire:model="enviarWhatsApp" class="rounded border-slate-300 text-emerald-600">
                                Enviar confirmación por WhatsApp
                            </label>
                        @endif
                        @if ($this->alumno?->email)
                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" wire:model="enviarRecibo" class="rounded border-slate-300 text-emerald-600">
                                Enviar recibo por correo a <span class="font-medium">{{ $this->alumno->email }}</span>
                            </label>
                        @else
                            <p class="text-xs text-slate-400">El alumno no tiene correo registrado; el recibo solo se imprimirá.</p>
                        @endif
                    </div>
                    <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-6 py-4">
                        <button type="button" wire:click="cerrarModalCobro"
                                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="cobrar"
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2 text-sm font-bold text-white shadow hover:bg-emerald-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="cobrar">Confirmar cobro e imprimir</span>
                            <span wire:loading wire:target="cobrar">Procesando…</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- =============================================================
         MODAL DE CARGO MANUAL
       ============================================================= --}}
    <div x-data="{ open: @entangle('modalCargo') }" x-show="open" x-cloak
         x-on:keydown.escape.window="open = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-900/60" x-on:click="open = false"></div>
        <form x-show="open" x-transition wire:submit="guardarCargo" class="relative w-full max-w-md rounded-xl bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-bold">Agregar cargo</h3>
                <p class="text-sm text-slate-500">Para conceptos distintos a la mensualidad. Queda registrado quién lo capturó.</p>
            </div>
            <div class="space-y-4 px-6 py-5">
                <div>
                    <label class="mb-1 block text-sm font-medium">Concepto</label>
                    <input wire:model="cargoConcepto" list="conceptos-sugeridos" maxlength="150" placeholder="Ej. INSCRIPCIÓN 2027-1"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase">
                    <datalist id="conceptos-sugeridos">
                        @foreach ($conceptosSugeridos as $c) <option value="{{ $c }}"> @endforeach
                    </datalist>
                    @error('cargoConcepto') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Tipo</label>
                    <select wire:model="cargoTipo" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($tiposCargo as $t) <option value="{{ $t->value }}">{{ $t->etiqueta() }}</option> @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-400">Colegiatura e inglés llevan recargo y bloqueo si se vencen; «Otro» no.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Monto $</label>
                        <input type="number" step="0.01" min="0.01" wire:model="cargoMonto" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-right text-sm">
                        @error('cargoMonto') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Vencimiento</label>
                        <input type="date" wire:model="cargoVencimiento" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @error('cargoVencimiento') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button type="button" x-on:click="open = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm">Cancelar</button>
                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Agregar cargo</button>
            </div>
        </form>
    </div>

    {{-- =============================================================
         MODAL WHATSAPP
       ============================================================= --}}
    <div x-data="{ open: @entangle('modalWhatsApp') }" x-show="open" x-cloak x-on:keydown.escape.window="open = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-900/60" x-on:click="open = false"></div>
        <div x-show="open" x-transition class="relative w-full max-w-lg rounded-xl bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-bold">Enviar WhatsApp</h3>
                <p class="text-sm text-slate-500">Se envía como plantilla aprobada por Meta al
                    {{ $this->alumno?->celular ? 'celular '.$this->alumno->celular : 'tel. de tutor '.$this->alumno?->tel_tutor }}.</p>
            </div>
            <div class="space-y-4 px-6 py-5">
                @if ($this->alumno?->whatsapp_baja_at)
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">El alumno pidió no recibir mensajes (respondió BAJA el {{ $this->alumno->whatsapp_baja_at->format('d/m/Y') }}).</p>
                @endif
                <select wire:model.live="plantillaWa" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($plantillasWa as $k => $etiqueta) <option value="{{ $k }}">{{ $etiqueta }}</option> @endforeach
                </select>
                @if ($this->vistaPreviaWa)
                    <div class="rounded-lg bg-emerald-50 p-3 text-sm text-slate-800 ring-1 ring-emerald-100">{{ $this->vistaPreviaWa }}</div>
                @else
                    <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-500">Este aviso no aplica: el alumno no tiene adeudos para él.</p>
                @endif
                @if ($this->mensajesWa->isNotEmpty())
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Últimos mensajes</p>
                        <ul class="max-h-32 space-y-1 overflow-y-auto text-xs">
                            @foreach ($this->mensajesWa as $wm)
                                <li wire:key="wm-{{ $wm->id }}" class="flex justify-between gap-2">
                                    <span class="truncate">{{ $wm->direccion === 'entrante' ? '⬅' : '➡' }} {{ $wm->cuerpo }}</span>
                                    <span class="shrink-0 text-slate-400">{{ $wm->created_at->format('d/m H:i') }} · {{ $wm->estado }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
            <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button type="button" x-on:click="open = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm">Cerrar</button>
                <button wire:click="enviarWa" @disabled(! $this->vistaPreviaWa || $this->alumno?->whatsapp_baja_at)
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">Enviar</button>
            </div>
        </div>
    </div>

    {{-- =============================================================
         MODAL DE COMENTARIOS
       ============================================================= --}}
    <div x-data="{ open: @entangle('modalComentarios') }" x-show="open" x-cloak
         x-on:keydown.escape.window="open = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-900/60" x-on:click="open = false"></div>
        <form x-show="open" x-transition wire:submit="guardarComentarios" class="relative w-full max-w-lg rounded-xl bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-bold">Comentarios administrativos</h3>
                <p class="text-sm text-slate-500">Visibles para caja y control escolar.</p>
            </div>
            <div class="px-6 py-5">
                <textarea wire:model="comentarios" rows="5" maxlength="2000"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                          placeholder="Ej. Convenio de pago autorizado por dirección…"></textarea>
                @error('comentarios') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button type="button" x-on:click="open = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm">Cerrar</button>
                <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Guardar</button>
            </div>
        </form>
    </div>

    @role('Administrador')
        <div x-data="{ open: @entangle('modalCondonar') }" x-show="open" x-cloak x-on:keydown.escape.window="open = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-900/60" x-on:click="open = false"></div>
            <form x-show="open" x-transition wire:submit="condonarRecargo" class="relative w-full max-w-md rounded-xl bg-white shadow-2xl">
                <div class="rounded-t-xl border-b border-sky-200 bg-sky-50 px-6 py-4">
                    <h3 class="text-lg font-bold text-sky-900">Condonar recargo</h3>
                    <p class="text-sm text-sky-800">El recargo se quita y ya no se volverá a aplicar a este cargo. Queda en la bitácora.</p>
                </div>
                <div class="px-6 py-5">
                    <label class="mb-1 block text-sm font-semibold">Motivo</label>
                    <input type="text" wire:model="motivoCondonacion" maxlength="150" placeholder="Ej. convenio autorizado por dirección"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('motivoCondonacion') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-6 py-4">
                    <button type="button" x-on:click="open = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm">Volver</button>
                    <button type="submit" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800">Condonar</button>
                </div>
            </form>
        </div>
    @endrole

    {{-- =============================================================
         MODAL CANCELAR PAGO (Administrador)
       ============================================================= --}}
    @role('Administrador')
        <div x-data="{ open: @entangle('modalCancelar') }" x-show="open" x-cloak
             x-on:keydown.escape.window="open = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-900/60" x-on:click="open = false"></div>
            <form x-show="open" x-transition wire:submit="cancelarPago" class="relative w-full max-w-md rounded-xl bg-white shadow-2xl">
                <div class="border-b border-red-200 bg-red-50 px-6 py-4 rounded-t-xl">
                    <h3 class="text-lg font-bold text-red-800">Cancelar pago</h3>
                    <p class="text-sm text-red-700">El folio queda marcado como CANCELADO y el adeudo se reabre.</p>
                </div>
                <div class="px-6 py-5">
                    <label class="mb-1 block text-sm font-semibold">Motivo de cancelación</label>
                    <input type="text" wire:model="motivoCancelacion" maxlength="255"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200">
                    @error('motivoCancelacion') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-6 py-4">
                    <button type="button" x-on:click="open = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm">Volver</button>
                    <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Cancelar pago</button>
                </div>
            </form>
        </div>
    @endrole
</div>
