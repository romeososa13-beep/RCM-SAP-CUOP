<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'ERP Universitario' }} · CUOP</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="h-full font-sans text-slate-800 antialiased">
    @auth
    <nav class="bg-slate-900 text-slate-100 shadow">
        <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-3">
            <a href="{{ route('home') }}" class="text-lg font-bold tracking-tight">ERP<span class="text-emerald-400">·</span>CUOP</a>
            <div class="flex flex-1 flex-wrap items-center gap-0.5 text-sm">
                @role('Administrador')
                    <a href="{{ route('tablero') }}" wire:navigate
                       @class(['rounded px-3 py-1.5 hover:bg-slate-700', 'bg-slate-700' => request()->routeIs('tablero')])>Tablero</a>
                @endrole
                @hasanyrole('Administrador|Cajero')
                    <a href="{{ route('caja.estado-cuenta') }}" wire:navigate
                       @class(['rounded px-3 py-1.5 hover:bg-slate-700', 'bg-slate-700' => request()->routeIs('caja.*')])>Caja</a>
                    <a href="{{ route('reportes.corte-caja') }}" wire:navigate
                       @class(['rounded px-3 py-1.5 hover:bg-slate-700', 'bg-slate-700' => request()->routeIs('reportes.corte-caja*')])>Corte de caja</a>
                @endhasanyrole
                <a href="{{ route('reportes.bloqueo') }}" wire:navigate
                   @class(['rounded px-3 py-1.5 hover:bg-slate-700', 'bg-slate-700' => request()->routeIs('reportes.bloqueo')])>Bloqueos</a>
                @hasanyrole('Administrador|Control Escolar')
                    <a href="{{ route('alumnos.padron') }}" wire:navigate
                       @class(['rounded px-3 py-1.5 hover:bg-slate-700', 'bg-slate-700' => request()->routeIs('alumnos.*')])>Padrón de alumnos</a>
                @endhasanyrole
                @role('Administrador')
                    <a href="{{ route('reportes.cartera-vencida') }}" wire:navigate
                       @class(['rounded px-3 py-1.5 hover:bg-slate-700', 'bg-slate-700' => request()->routeIs('reportes.cartera-vencida')])>Cartera vencida</a>
                    <div class="relative" x-data="{ abierto: false }" x-on:click.outside="abierto = false" x-on:keydown.escape="abierto = false">
                        <button type="button" x-on:click="abierto = ! abierto" :aria-expanded="abierto"
                                @class(['flex items-center gap-1 rounded px-3 py-1.5 hover:bg-slate-700', 'bg-slate-700' => request()->routeIs('admin.*')])>
                            Administración
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="abierto" x-cloak x-transition.origin.top.left
                             class="absolute left-0 z-50 mt-1 w-44 overflow-hidden rounded-lg bg-white py-1 text-slate-700 shadow-lg ring-1 ring-slate-200">
                            <a href="{{ route('admin.usuarios') }}" wire:navigate class="block px-4 py-2 hover:bg-slate-100">Usuarios y roles</a>
                            <a href="{{ route('admin.catalogos') }}" wire:navigate class="block px-4 py-2 hover:bg-slate-100">Catálogos</a>
                            <a href="{{ route('admin.bitacora') }}" wire:navigate class="block px-4 py-2 hover:bg-slate-100">Bitácora</a>
                        </div>
                    </div>
                @endrole
            </div>
            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('mi-cuenta') }}" wire:navigate class="text-slate-300 hover:text-white" title="Mi cuenta">{{ auth()->user()->name }}
                    <span class="ml-1 rounded bg-slate-700 px-1.5 py-0.5 text-xs">{{ auth()->user()->getRoleNames()->first() }}</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="rounded px-2 py-1 text-slate-300 hover:bg-slate-700 hover:text-white">Salir</button>
                </form>
            </div>
        </div>
    </nav>
    @endauth

    <main class="mx-auto max-w-7xl px-4 py-6">
        {{ $slot }}
    </main>

    {{-- Toasts globales: $this->dispatch('toast', tipo: 'success', mensaje: '...') --}}
    <div x-data="{ toasts: [] }"
         x-on:toast.window="const t = { id: Date.now(), ...$event.detail }; toasts.push(t); setTimeout(() => toasts = toasts.filter(x => x.id !== t.id), 5000)"
         class="pointer-events-none fixed bottom-4 right-4 z-[60] flex w-96 flex-col gap-2">
        <template x-for="t in toasts" :key="t.id">
            <div x-transition class="pointer-events-auto rounded-lg px-4 py-3 text-sm font-medium shadow-lg"
                 :class="t.tipo === 'error' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'"
                 x-text="t.mensaje"></div>
        </template>
    </div>
</body>
</html>
