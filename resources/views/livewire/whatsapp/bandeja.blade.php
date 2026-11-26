<div class="space-y-4" wire:poll.15s>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">WhatsApp</h1>
            <p class="text-sm text-slate-500">
                Conversaciones con alumnos. El asistente responde solo; las marcadas en ámbar esperan a una persona.
                @if (config('services.whatsapp.driver') !== 'cloud')
                    <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-800">MODO PRUEBA: no se envía nada a WhatsApp</span>
                @endif
            </p>
        </div>
    </div>

    <div class="grid h-[70vh] grid-cols-1 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200 md:grid-cols-3">
        {{-- Lista --}}
        <div class="flex flex-col border-r border-slate-200">
            <div class="space-y-2 border-b border-slate-200 p-3">
                <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Teléfono, matrícula o nombre…"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" wire:model.live="soloPendientes" class="rounded"> Solo las que esperan respuesta</label>
            </div>
            <ul class="flex-1 divide-y divide-slate-100 overflow-y-auto">
                @forelse ($this->conversaciones as $c)
                    <li wire:key="cv-{{ $c['telefono'] }}">
                        <button wire:click="abrir('{{ $c['telefono'] }}')"
                                @class(['block w-full px-4 py-3 text-left hover:bg-slate-50', 'bg-emerald-50' => $telefono === $c['telefono']])>
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold">{{ $c['ultimo']->alumno?->nombre_completo ?? $c['telefono'] }}</span>
                                <span class="shrink-0 text-xs text-slate-400">{{ $c['ultimo']->created_at->isToday() ? $c['ultimo']->created_at->format('H:i') : $c['ultimo']->created_at->format('d/m') }}</span>
                            </div>
                            <div class="mt-0.5 flex items-center justify-between gap-2">
                                <span class="truncate text-xs text-slate-500">{{ $c['ultimo']->direccion === 'saliente' ? '➡ ' : '' }}{{ $c['ultimo']->cuerpo }}</span>
                                @if ($c['pendientes'])
                                    <span class="shrink-0 rounded-full bg-amber-500 px-1.5 text-xs font-bold text-white">{{ $c['pendientes'] }}</span>
                                @endif
                            </div>
                        </button>
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-sm text-slate-500">Sin conversaciones.</li>
                @endforelse
            </ul>
        </div>

        {{-- Hilo --}}
        <div class="flex flex-col md:col-span-2">
            @if ($telefono)
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-3">
                    <div>
                        <p class="font-mono text-sm font-semibold">{{ $telefono }}</p>
                        <p class="text-xs text-slate-500">
                            @forelse ($this->alumnos as $a)
                                @hasanyrole('Administrador|Cajero')
                                    <a href="{{ route('caja.estado-cuenta', $a->matricula) }}" wire:navigate class="text-emerald-700 hover:underline">{{ $a->matricula }} {{ $a->nombre_completo }}</a>@if (! $loop->last), @endif
                                @endhasanyrole
                                @if ($a->whatsapp_baja_at) <span class="text-amber-700">(BAJA de avisos)</span> @endif
                            @empty
                                Número no registrado en el padrón.
                            @endforelse
                        </p>
                    </div>
                    <button wire:click="marcarAtendida" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs hover:bg-slate-50">Marcar atendida</button>
                </div>
                <div wire:key="hilo-{{ $telefono }}" class="flex-1 space-y-2 overflow-y-auto bg-slate-50 px-5 py-4" x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                    @foreach ($this->hilo as $m)
                        <div wire:key="m-{{ $m->id }}" @class(['flex', 'justify-end' => $m->direccion === 'saliente'])>
                            <div @class(['max-w-[80%] rounded-lg px-3 py-2 text-sm shadow-sm',
                                'bg-white text-slate-800' => $m->direccion === 'entrante',
                                'bg-amber-50 ring-1 ring-amber-300' => $m->direccion === 'entrante' && $m->requiere_atencion,
                                'bg-emerald-100 text-slate-800' => $m->direccion === 'saliente' && $m->tipo !== 'bot',
                                'bg-slate-200 text-slate-700' => $m->tipo === 'bot'])><p class="whitespace-pre-line">{{ $m->cuerpo }}</p>
                                <p class="mt-1 text-right text-[10px] text-slate-500">
                                    @if ($m->direccion === 'saliente')
                                        {{ $m->tipo === 'bot' ? (($m->variables['ia'] ?? false) ? 'Asistente (IA)' : 'Asistente') : ($m->tipo === 'plantilla' ? 'Plantilla · ' : '').($m->usuario?->name ?? 'Automático') }} ·
                                    @endif
                                    {{ $m->created_at->format('d/m H:i') }}
                                    @if ($m->direccion === 'saliente')
                                        · <span @class(['text-red-600 font-semibold' => $m->estado === 'fallido'])>{{ ['en_cola' => '⏳', 'enviado' => '✓', 'entregado' => '✓✓', 'leido' => '✓✓ leído', 'fallido' => 'falló'][$m->estado] ?? $m->estado }}</span>
                                    @endif
                                </p>
                                @if ($m->estado === 'fallido' && $m->error) <p class="text-[10px] text-red-600">{{ $m->error }}</p> @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <form wire:submit="responder" class="border-t border-slate-200 p-3">
                    @if ($this->ventanaAbierta)
                        <div class="flex gap-2">
                            <textarea wire:model="respuesta" rows="2" maxlength="4000" placeholder="Escribe tu respuesta…"
                                      class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" x-on:keydown.enter.prevent.exact="$wire.responder()"></textarea>
                            <button class="rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700">Enviar</button>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">Enter envía · Mayús+Enter nueva línea. Al responder, el asistente deja de contestar en esta conversación por 2 horas.</p>
                    @else
                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">Pasaron más de 24 h desde el último mensaje del alumno. WhatsApp solo permite enviarle una plantilla desde su estado de cuenta.</p>
                    @endif
                    @error('respuesta') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </form>
            @else
                <div class="flex flex-1 items-center justify-center text-sm text-slate-400">Elige una conversación.</div>
            @endif
        </div>
    </div>
</div>
