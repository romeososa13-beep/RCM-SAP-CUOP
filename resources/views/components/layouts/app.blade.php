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
            <div class="flex flex-1 gap-1 text-sm">
                @hasanyrole('Administrador|Cajero')
                    <a href="{{ route('caja.estado-cuenta') }}" wire:navigate
                       @class(['rounded px-3 py-1.5 hover:bg-slate-700', 'bg-slate-700' => request()->routeIs('caja.*')])>Caja</a>
                    <a href="{{ route('reportes.corte-caja') }}" wire:navigate
                       @class(['rounded px-3 py-1.5 hover:bg-slate-700', 'bg-slate-700' => request()->routeIs('reportes.corte-caja*')])>Corte de caja</a>
                @endhasanyrole
                @hasanyrole('Administrador|Control Escolar')
                    <a href="{{ route('alumnos.padron') }}" wire:navigate
                       @class(['rounded px-3 py-1.5 hover:bg-slate-700', 'bg-slate-700' => request()->routeIs('alumnos.*')])>Padrón de alumnos</a>
                @endhasanyrole
                @role('Administrador')
                    <a href="{{ route('reportes.cartera-vencida') }}" wire:navigate
                       @class(['rounded px-3 py-1.5 hover:bg-slate-700', 'bg-slate-700' => request()->routeIs('reportes.cartera-vencida')])>Cartera vencida</a>
                @endrole
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-slate-300">{{ auth()->user()->name }}
                    <span class="ml-1 rounded bg-slate-700 px-1.5 py-0.5 text-xs">{{ auth()->user()->getRoleNames()->first() }}</span>
                </span>
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
