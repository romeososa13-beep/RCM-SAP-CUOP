@php($seg = \App\Services\Cobranza\CampanaService::SEGMENTOS)
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Campañas de cobranza</h1>
            <p class="text-sm text-slate-500">Elige a quién escribir, envía el aviso por WhatsApp y mide quién pagó después. Hoy: {{ today()->format('d/m/Y') }}.</p>
        </div>
        @if ($modoPrueba)
            <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-200">
                Modo prueba (WHATSAPP_DRIVER=log): los mensajes se registran pero <strong>no salen</strong> a los alumnos.
            </p>
        @endif
    </div>

    {{-- 1. Segmento y filtros --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($seg as $clave => [$titulo, , $desc])
            <button type="button" wire:click="$set('segmento', '{{ $clave }}')" wire:key="seg-{{ $clave }}"
                    @class(['rounded-xl p-4 text-left shadow-sm ring-1 transition',
                            'bg-slate-900 text-white ring-slate-900' => $segmento === $clave,
                            'bg-white ring-slate-200 hover:ring-slate-400' => $segmento !== $clave])>
                <p class="font-semibold">{{ $titulo }}</p>
                <p @class(['mt-1 text-xs', 'text-slate-300' => $segmento === $clave, 'text-slate-500' => $segmento !== $clave])>{{ $desc }}</p>
            </button>
        @endforeach
    </div>

    <div class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <label class="text-xs text-slate-500">Sede
            <select wire:model.live="sedeId" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todas</option>
                @foreach ($sedes as $s) <option value="{{ $s->id }}">{{ $s->clave }}</option> @endforeach
            </select>
        </label>
        <label class="text-xs text-slate-500">Carrera
            <select wire:model.live="carreraId" class="mt-1 block max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todas</option>
                @foreach ($carreras as $c) <option value="{{ $c->id }}">{{ $c->nombre }}</option> @endforeach
            </select>
        </label>
        <label class="text-xs text-slate-500">Grupo (contiene)
            <input wire:model.live.debounce.400ms="grupo" type="text" class="mt-1 block w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="SAB-6A">
        </label>
        @if ($segmento === 'atraso')
            <label class="text-xs text-slate-500">Días de atraso (mínimo)
                <input wire:model.live.debounce.400ms="diasMin" type="number" min="1" class="mt-1 block w-28 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
        @endif
        <label class="text-xs text-slate-500">Saldo mínimo $
            <input wire:model.live.debounce.400ms="saldoMin" type="number" min="0" step="50" class="mt-1 block w-28 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </label>
    </div>

    {{-- 2. Resumen, mensaje y envío --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="grid grid-cols-2 gap-3 lg:col-span-1">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs uppercase tracking-wide text-slate-500">Alumnos</p>
                <p class="text-2xl font-bold tabular-nums">{{ $filas->count() }}</p>
                <p class="text-xs text-slate-500 tabular-nums">${{ number_format($total, 2) }}</p>
            </div>
            <div class="rounded-xl bg-emerald-600 p-4 text-white shadow">
                <p class="text-xs uppercase tracking-wide text-emerald-100">Con WhatsApp</p>
                <p class="text-2xl font-bold tabular-nums">{{ $contactables }}</p>
                <p class="text-xs text-emerald-100 tabular-nums">${{ number_format($saldoContactable, 2) }}</p>
            </div>
            @foreach ($sinContacto as $motivo => $n)
                <div class="col-span-2 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-600">{{ $n }} sin enviar: {{ $motivo }}</div>
            @endforeach
        </div>

        <div class="space-y-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 lg:col-span-2">
            <div class="flex flex-wrap items-end gap-3">
                <label class="text-xs text-slate-500">Mensaje
                    <select wire:model.live="plantilla" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach (\App\Services\Cobranza\CampanaService::PLANTILLAS as $p)
                            <option value="{{ $p }}">{{ \App\Services\Cobranza\CampanaService::etiquetaPlantilla($p) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex-1 text-xs text-slate-500">Nombre de la campaña (opcional)
                    <input wire:model="nombre" type="text" maxlength="120" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                           placeholder="{{ $seg[$segmento][0] }} {{ now()->format('d/m/Y') }}">
                </label>
            </div>
            <div class="rounded-lg bg-emerald-50 p-3 text-sm text-slate-800 ring-1 ring-emerald-100">
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">Así le llega al alumno</p>
                {{ $vistaPrevia ?? 'Con este mensaje no aplica a ninguno de los primeros alumnos de la lista (p. ej. aviso de recargo a quien no tiene recargo).' }}
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-slate-500">Se omite a quien no tiene celular, pidió BAJA, ya recibió este aviso hoy o al que el mensaje no aplica.</p>
                <button wire:click="enviar" wire:loading.attr="disabled"
                        wire:confirm="¿Enviar «{{ \App\Services\Cobranza\CampanaService::etiquetaPlantilla($plantilla) }}» por WhatsApp a {{ $contactables }} alumnos?"
                        @disabled($contactables === 0)
                        class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">
                    Enviar a {{ $contactables }} alumnos
                </button>
            </div>
        </div>
    </div>

    {{-- 3. Lista de destinatarios --}}
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">Matrícula</th><th class="px-4 py-3">Alumno</th><th class="px-4 py-3">Grupo</th>
                    <th class="px-4 py-3">Pendiente</th><th class="px-4 py-3 text-right">Atraso</th><th class="px-4 py-3 text-right">Saldo</th><th class="px-4 py-3">WhatsApp</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($filas->take(300) as $f)
                    <tr wire:key="dest-{{ $f['alumno']->matricula }}" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-2 font-mono font-semibold">{{ $f['alumno']->matricula }}</td>
                        <td class="px-4 py-2">{{ $f['alumno']->nombre_completo }}</td>
                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $f['alumno']->grupo }}</td>
                        <td class="px-4 py-2 text-xs">{{ $f['conceptos'] }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums">{{ $f['dias_atraso'] ? $f['dias_atraso'].' d' : '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-right font-semibold tabular-nums">${{ number_format($f['saldo'], 2) }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-xs">
                            @if ($f['contacto']) <span class="text-slate-400">{{ $f['contacto'] }}</span>
                            @else <span class="text-emerald-700">✓</span> @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('caja.estado-cuenta', $f['alumno']->matricula) }}" wire:navigate class="text-sm font-medium text-emerald-700 hover:underline">Cobrar</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">Nadie en este segmento.</td></tr>
                @endforelse
                @if ($filas->count() > 300)
                    <tr><td colspan="8" class="px-4 py-3 text-center text-xs text-slate-500">Se muestran 300 de {{ $filas->count() }}; la campaña se envía a todos.</td></tr>
                @endif
            </tbody>
        </table>
    </div>

    {{-- 4. Historial y resultados --}}
    <div class="grid gap-4 lg:grid-cols-5">
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200 lg:col-span-2">
            <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">Campañas enviadas</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($campanas as $c)
                    <li wire:key="camp-{{ $c->id }}">
                        <button type="button" wire:click="ver({{ $c->id }})"
                                @class(['w-full px-4 py-2.5 text-left hover:bg-slate-50', 'bg-emerald-50' => $campanaId === $c->id])>
                            <span class="font-medium">{{ $c->nombre }}</span>
                            <span class="block text-xs text-slate-500">{{ $c->created_at->format('d/m/Y H:i') }} · {{ $c->en_cola }} enviados · {{ $c->usuario?->name }}</span>
                        </button>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-slate-500">Aún no hay campañas.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 lg:col-span-3">
            @if ($campana && $resultado)
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h2 class="font-semibold">{{ $campana->nombre }}</h2>
                        <p class="text-xs text-slate-500">{{ \App\Services\Cobranza\CampanaService::etiquetaPlantilla($campana->plantilla) }} · {{ $campana->created_at->format('d/m/Y H:i') }}
                            · debían ${{ number_format($campana->saldo_objetivo, 2) }}</p>
                    </div>
                    <button type="button" wire:click="ver(null)" class="text-xs text-slate-500 hover:underline">Cerrar</button>
                </div>
                @php($tasa = $resultado['contactados'] ? round($resultado['alumnos_pagaron'] * 100 / $resultado['contactados']) : 0)
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Contactados</p><p class="text-xl font-bold tabular-nums">{{ $resultado['contactados'] }}</p></div>
                    <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Pagaron después</p><p class="text-xl font-bold tabular-nums">{{ $resultado['alumnos_pagaron'] }} <span class="text-sm font-normal text-slate-500">({{ $tasa }}%)</span></p></div>
                    <div class="col-span-2 rounded-lg bg-emerald-600 p-3 text-white"><p class="text-xs text-emerald-100">Cobrado desde el envío</p><p class="text-xl font-bold tabular-nums">${{ number_format($resultado['recuperado'], 2) }}</p></div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    @foreach (['en_cola' => 'En cola', 'enviado' => 'Enviado', 'entregado' => 'Entregado', 'leido' => 'Leído', 'fallido' => 'Falló'] as $e => $txt)
                        <span @class(['rounded-full px-2.5 py-1', 'bg-red-100 text-red-700' => $e === 'fallido', 'bg-slate-100 text-slate-700' => $e !== 'fallido'])>{{ $txt }}: {{ $resultado['estados'][$e] ?? 0 }}</span>
                    @endforeach
                    @foreach ($campana->omitidos ?? [] as $motivo => $n)
                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-800">Omitidos · {{ $motivo }}: {{ $n }}</span>
                    @endforeach
                </div>
                <div class="mt-3 max-h-96 overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="py-2">Alumno</th><th>Estado</th><th class="text-right">Pagó</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($resultado['detalle'] as $d)
                                <tr wire:key="res-{{ $d['mensaje']->id }}">
                                    <td class="py-1.5"><span class="font-mono text-xs">{{ $d['mensaje']->matricula }}</span> {{ $d['mensaje']->alumno?->nombre_completo }}</td>
                                    <td class="text-xs" title="{{ $d['mensaje']->error }}">{{ $d['mensaje']->estado }}</td>
                                    <td class="text-right tabular-nums">{{ $d['pago'] !== null ? '$'.number_format($d['pago'], 2) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="py-10 text-center text-sm text-slate-500">Elige una campaña para ver cuántos mensajes se entregaron y cuánto se cobró después.</p>
            @endif
        </div>
    </div>
</div>
