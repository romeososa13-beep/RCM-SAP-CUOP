<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Padrón de alumnos</h1>
            <p class="text-sm text-slate-500">Control Escolar · {{ $alumnos->total() }} registros</p>
        </div>
        <div class="flex gap-2">
            <button wire:click="abrirImportar" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                Importar Excel / CSV
            </button>
            <button wire:click="nuevo" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                + Nuevo alumno
            </button>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="grid grid-cols-1 gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 md:grid-cols-6">
        <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Matrícula o nombre…"
               class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2">
        <select wire:model.live="sedeId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Todas las sedes</option>
            @foreach ($this->catalogos['sedes'] as $s) <option value="{{ $s->id }}">{{ $s->clave }}</option> @endforeach
        </select>
        <select wire:model.live="carreraId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Todas las carreras</option>
            @foreach ($this->catalogos['carreras'] as $c) <option value="{{ $c->id }}">{{ $c->nombre }}</option> @endforeach
        </select>
        <select wire:model.live="grupo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Todos los grupos</option>
            @foreach ($this->grupos as $g) <option value="{{ $g }}">{{ $g }}</option> @endforeach
        </select>
        <div class="flex gap-2">
            <select wire:model.live="estatus" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todo estatus</option>
                @foreach ($estatuses as $e) <option value="{{ $e->value }}">{{ $e->etiqueta() }}</option> @endforeach
            </select>
            <button wire:click="limpiarFiltros" title="Limpiar filtros" class="rounded-lg border border-slate-300 px-3 text-slate-500 hover:bg-slate-50">✕</button>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="relative overflow-x-auto">
            <div wire:loading.flex class="absolute inset-0 z-10 items-center justify-center bg-white/60 text-sm text-slate-500">Cargando…</div>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Matrícula</th><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Sede</th>
                        <th class="px-4 py-3">Carrera</th><th class="px-4 py-3">Grupo</th><th class="px-4 py-3">Plan</th>
                        <th class="px-4 py-3">Estatus</th><th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($alumnos as $a)
                        <tr wire:key="al-{{ $a->matricula }}" class="hover:bg-slate-50">
                            <td class="px-4 py-2.5 font-mono font-semibold">{{ $a->matricula }}</td>
                            <td class="px-4 py-2.5">{{ $a->nombre_completo }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5">{{ $a->sede->clave }}</td>
                            <td class="px-4 py-2.5">{{ $a->carrera->nombre }}</td>
                            <td class="px-4 py-2.5 font-mono text-xs">{{ $a->grupo }}</td>
                            <td class="px-4 py-2.5 text-xs">@if ($a->planPago) #{{ $a->plan_pago_id }} {{ $a->planPago->nombre }} @else <span class="text-amber-700">Sin plan</span> @endif</td>
                            <td class="whitespace-nowrap px-4 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $a->estatus->color() }}">{{ $a->estatus->etiqueta() }}</span></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                <button wire:click="editar('{{ $a->matricula }}')" class="text-sm font-medium text-emerald-700 hover:underline">Editar</button>
                                @hasanyrole('Administrador|Cajero')
                                    <a href="{{ route('caja.estado-cuenta', $a->matricula) }}" wire:navigate class="ml-3 text-sm font-medium text-slate-600 hover:underline">Edo. cuenta</a>
                                @endhasanyrole
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">No hay alumnos con esos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $alumnos->links() }}</div>
    </div>

    {{-- Modal alta / edición --}}
    <div x-data="{ open: @entangle('modalForm') }" x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60" x-on:click="open = false"></div>
        <form wire:submit="guardar" class="relative w-full max-w-2xl rounded-xl bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-bold">{{ $editando ? 'Editar alumno '.$editando : 'Nuevo alumno' }}</h3>
            </div>
            <div class="grid grid-cols-1 gap-4 px-6 py-5 md:grid-cols-2">
                @php($in = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase disabled:bg-slate-100')
                <div>
                    <label class="mb-1 block text-sm font-medium">Matrícula</label>
                    <input wire:model="form.matricula" @disabled($editando) class="{{ $in }} font-mono">
                    @error('form.matricula') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Estatus</label>
                    <select wire:model="form.estatus" class="{{ $in }}">
                        @foreach ($estatuses as $e) <option value="{{ $e->value }}">{{ $e->etiqueta() }}</option> @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Nombre completo (apellidos primero)</label>
                    <input wire:model="form.nombre_completo" class="{{ $in }}">
                    @error('form.nombre_completo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Sede</label>
                    <select wire:model.live="form.sede_id" class="{{ $in }}">
                        <option value="">—</option>
                        @foreach ($this->catalogos['sedes'] as $s) <option value="{{ $s->id }}">{{ $s->clave }} · {{ $s->nombre }}</option> @endforeach
                    </select>
                    @error('form.sede_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Carrera</label>
                    <select wire:model.live="form.carrera_id" class="{{ $in }}">
                        <option value="">—</option>
                        @foreach ($this->catalogos['carreras'] as $c) <option value="{{ $c->id }}">{{ $c->nombre }} ({{ $c->modalidad }})</option> @endforeach
                    </select>
                    @error('form.carrera_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Grupo</label>
                    <input wire:model="form.grupo" placeholder="27-1 LIC-EDUN-NESC-SAB-6A" class="{{ $in }} font-mono">
                    @error('form.grupo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Plan de pago <span class="font-normal text-slate-400">(obligatorio si está ACTIVO)</span></label>
                    <select wire:model="form.plan_pago_id" @disabled($this->planesFormulario->isEmpty()) class="{{ $in }}">
                        <option value="">{{ filled($form['sede_id'] ?? null) ? ($this->planesFormulario->isEmpty() ? 'La sede no tiene planes' : '—') : 'Elija primero la sede' }}</option>
                        @foreach ($this->planesFormulario as $p)
                            <option value="{{ $p->id }}" wire:key="plan-{{ $p->id }}">#{{ $p->id }} {{ $p->nombre }}{{ $p->carrera ? ' · '.$p->carrera->nombre : '' }} (${{ number_format($p->monto_mensualidad, 2) }})</option>
                        @endforeach
                    </select>
                    @error('form.plan_pago_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Comentarios</label>
                    <textarea wire:model="form.comentarios" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button type="button" x-on:click="open = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm">Cancelar</button>
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Guardar</button>
            </div>
        </form>
    </div>

    {{-- Modal importación --}}
    <div x-data="{ open: @entangle('modalImportar') }" x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60" x-on:click="open = false"></div>
        <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-bold">Actualización masiva del padrón</h3>
                    <p class="text-sm text-slate-500">Matrículas nuevas se crean; existentes actualizan grupo, carrera, plan de pago y estatus.</p>
                </div>
                <button wire:click="descargarPlantilla" class="text-sm font-medium text-emerald-700 hover:underline">Descargar plantilla</button>
            </div>

            <div class="space-y-4 overflow-y-auto px-6 py-5">
                <label class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-300 px-6 py-8 text-center hover:border-emerald-400 hover:bg-emerald-50/40">
                    <input type="file" wire:model="archivo" accept=".xlsx,.xls,.csv" class="sr-only">
                    <span class="text-sm font-medium text-slate-700">Seleccione un archivo .xlsx o .csv</span>
                    <span class="text-xs text-slate-500">Columnas: MATRICULA, NOMBRE_COMPLETO, SEDE, CARRERA, GRUPO, PLAN_PAGO, ESTATUS</span>
                    @if ($archivo) <span class="mt-2 font-mono text-xs text-emerald-700">{{ $archivo->getClientOriginalName() }}</span> @endif
                </label>
                <div wire:loading wire:target="archivo" class="text-sm text-slate-500">Analizando archivo…</div>
                @error('archivo') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                @if ($preview)
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <div class="rounded-lg bg-emerald-50 p-3 ring-1 ring-emerald-200"><p class="text-xs font-medium text-emerald-700">A crear</p><p class="text-2xl font-bold text-emerald-800">{{ count($preview['crear']) }}</p></div>
                        <div class="rounded-lg bg-sky-50 p-3 ring-1 ring-sky-200"><p class="text-xs font-medium text-sky-700">A actualizar</p><p class="text-2xl font-bold text-sky-800">{{ count($preview['actualizar']) }}</p></div>
                        <div class="rounded-lg bg-slate-50 p-3 ring-1 ring-slate-200"><p class="text-xs font-medium text-slate-600">Sin cambios</p><p class="text-2xl font-bold text-slate-700">{{ $preview['sin_cambios'] }}</p></div>
                        <div class="rounded-lg bg-red-50 p-3 ring-1 ring-red-200"><p class="text-xs font-medium text-red-700">Con errores</p><p class="text-2xl font-bold text-red-800">{{ count($preview['errores']) }}</p></div>
                    </div>

                    @if ($preview['errores'])
                        <div class="rounded-lg ring-1 ring-red-200">
                            <p class="border-b border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-800">Filas con error (no se importarán)</p>
                            <ul class="max-h-40 divide-y divide-red-100 overflow-y-auto text-xs">
                                @foreach ($preview['errores'] as $e)
                                    <li class="px-3 py-1.5"><span class="font-mono font-semibold">Fila {{ $e['fila'] }} · {{ $e['matricula'] }}</span> — {{ implode(' ', $e['mensajes']) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($preview['actualizar'])
                        <div class="rounded-lg ring-1 ring-sky-200">
                            <p class="border-b border-sky-200 bg-sky-50 px-3 py-2 text-sm font-semibold text-sky-800">Cambios a matrículas existentes</p>
                            <ul class="max-h-40 divide-y divide-sky-100 overflow-y-auto text-xs">
                                @foreach (array_slice($preview['actualizar'], 0, 200) as $u)
                                    <li class="px-3 py-1.5"><span class="font-mono font-semibold">{{ $u['matricula'] }}</span> {{ $u['nombre_completo'] }}:
                                        @foreach ($u['cambios'] as $campo => $valor) <span class="ml-1 rounded bg-sky-100 px-1">{{ $campo }} → {{ $valor }}</span> @endforeach
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endif
            </div>

            <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button type="button" x-on:click="open = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm">Cancelar</button>
                <button wire:click="confirmarImportacion" wire:loading.attr="disabled"
                        @disabled(! $preview || (count($preview['crear']) + count($preview['actualizar'])) === 0)
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                    Confirmar importación
                </button>
            </div>
        </div>
    </div>
</div>
