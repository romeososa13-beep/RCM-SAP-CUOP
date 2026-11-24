{{-- Lista de cambios de un registro de bitácora. Requiere $r (Bitacora) y $f (BitacoraFormato). --}}
@if ($r->cambios)
    <ul class="mt-1 space-y-0.5 text-xs">
        @foreach ($r->cambios as $campo => $c)
            <li>
                <span class="font-medium text-slate-600">{{ $f->campo($campo) }}:</span>
                @if ($r->accion === 'creado')
                    <span class="text-slate-800">{{ $f->valor($campo, $c['despues'] ?? null) }}</span>
                @elseif (in_array($r->accion, ['eliminado', 'cancelado']))
                    <span class="text-slate-500 line-through">{{ $f->valor($campo, $c['antes'] ?? null) }}</span>
                @else
                    <span class="text-red-700 line-through decoration-red-300">{{ $f->valor($campo, $c['antes'] ?? null) }}</span>
                    <span class="text-slate-400">→</span>
                    <span class="font-medium text-emerald-700">{{ $f->valor($campo, $c['despues'] ?? null) }}</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
