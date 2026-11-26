<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Datos de la institución</h1>
        <p class="text-sm text-slate-500">Aparecen en los recibos de pago (impresos y por correo) y en el corte de caja.</p>
    </div>

    <form wire:submit="guardar" class="space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        @php($in = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm')
        @foreach ([
            'institucion_nombre' => ['Nombre que aparece en el recibo', 'CENTRO UNIVERSITARIO ...'],
            'institucion_razon_social' => ['Razón social', 'Ej. Centro Universitario de Occidente, A.C.'],
            'institucion_rfc' => ['RFC', 'CUO010101AB1'],
            'institucion_direccion' => ['Dirección fiscal', 'Calle, número, colonia, municipio, estado, C.P.'],
            'institucion_telefono' => ['Teléfono', '449 000 0000'],
        ] as $campo => [$etiqueta, $ejemplo])
            <div>
                <label class="mb-1 block text-sm font-medium">{{ $etiqueta }}</label>
                <input wire:model="datos.{{ $campo }}" placeholder="{{ $ejemplo }}" class="{{ $in }} {{ $campo === 'institucion_rfc' ? 'font-mono uppercase' : '' }}">
                @error("datos.$campo") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        @endforeach
        <div>
            <label class="mb-1 block text-sm font-medium">Leyenda al pie del recibo</label>
            <textarea wire:model="datos.recibo_leyenda" rows="2" maxlength="300" class="{{ $in }}"></textarea>
        </div>
        <div class="flex flex-wrap items-center gap-4">
            <div>
                <label class="mb-1 block text-sm font-medium">Logo (PNG o JPG, máx. 1 MB)</label>
                <input type="file" wire:model="logo" accept="image/png,image/jpeg" class="text-sm">
                @error('logo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            @if ($logo)
                <img src="{{ $logo->temporaryUrl() }}" alt="Logo nuevo" class="h-14 rounded ring-1 ring-slate-200">
            @elseif ($logoActual)
                <img src="{{ $logoActual }}" alt="Logo actual" class="h-14 rounded ring-1 ring-slate-200">
                <button type="button" wire:click="quitarLogo" class="text-xs text-red-600 hover:underline">Quitar logo</button>
            @endif
        </div>
        <div class="flex items-center justify-between border-t border-slate-100 pt-4">
            <a href="{{ route('admin.institucion.muestra') }}" target="_blank" class="text-sm font-medium text-emerald-700 hover:underline">Ver recibo de muestra</a>
            <button class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Guardar</button>
        </div>
    </form>
</div>
