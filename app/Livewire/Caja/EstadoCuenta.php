<?php

namespace App\Livewire\Caja;

use App\Actions\Cobranza\CancelarPagoAction;
use App\Actions\Cobranza\CondonarRecargoAction;
use App\Actions\Cobranza\CrearCargoAction;
use App\Actions\Cobranza\RegistrarPagoAction;
use App\Enums\EstatusAlumno;
use App\Enums\FormaPago;
use App\Enums\TipoAdeudo;
use App\Exceptions\CobranzaException;
use App\Mail\ReciboPagoMail;
use App\Models\Adeudo;
use App\Models\Alumno;
use App\Models\Bitacora;
use App\Models\Pago;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Estado de cuenta')]
class EstadoCuenta extends Component
{
    /* ---------------- Búsqueda / selección ---------------- */
    public string $search = '';

    #[Url(as: 'm', history: true)]
    public ?string $matricula = null;

    public bool $mostrarPagados = false;

    /* ---------------- Modal de cobro ---------------- */
    public bool $modalCobro = false;

    #[Locked]
    public ?int $adeudoCobroId = null;

    public string $monto_pagar = '';

    public string $forma_pago = 'EFECTIVO';

    public string $referencia = '';

    public bool $enviarRecibo = true;

    /* ---------------- Modal de comentarios ---------------- */
    public bool $modalComentarios = false;

    public string $comentarios = '';

    /* ---------------- Modal de cancelación (Administrador) ---------------- */
    public bool $modalCancelar = false;

    #[Locked]
    public ?int $pagoCancelarId = null;

    public string $motivoCancelacion = '';

    /* ---------------- Modal de cargo manual ---------------- */
    public bool $modalCargo = false;

    public string $cargoConcepto = '';

    public string $cargoMonto = '';

    public string $cargoVencimiento = '';

    public string $cargoTipo = 'OTRO';

    /* ---------------- Modal condonar recargo (Administrador) ---------------- */
    public bool $modalCondonar = false;

    #[Locked]
    public ?int $adeudoCondonarId = null;

    public string $motivoCondonacion = '';

    /** Último recibo emitido: respaldo si el navegador bloquea la pestaña emergente. */
    public ?string $ultimoReciboUrl = null;

    public ?string $ultimoFolio = null;

    public function mount(?string $matricula = null): void
    {
        $matricula ??= $this->matricula;

        if ($matricula && Alumno::query()->whereKey($matricula)->exists()) {
            $this->matricula = $matricula;
        } else {
            $this->matricula = null;
        }
    }

    /* =====================================================================
     |  Datos (propiedades computadas: se recalculan en cada render)
     | ===================================================================== */

    #[Computed]
    public function resultados(): Collection
    {
        if (mb_strlen(trim($this->search)) < 2) {
            return collect();
        }

        return Alumno::query()
            ->buscar($this->search)
            ->with('sede:id,clave')
            ->orderBy('nombre_completo')
            ->limit(10)
            ->get(['matricula', 'nombre_completo', 'sede_id', 'grupo', 'estatus']);
    }

    #[Computed]
    public function alumno(): ?Alumno
    {
        return $this->matricula
            ? Alumno::query()->with(['sede', 'carrera', 'planPago'])->find($this->matricula)
            : null;
    }

    #[Computed]
    public function adeudos(): Collection
    {
        if (! $this->matricula) {
            return collect();
        }

        return Adeudo::query()
            ->where('matricula', $this->matricula)
            ->when(! $this->mostrarPagados, fn ($q) => $q->where('pagado', false))
            ->with('pagos:id,adeudo_id,monto_pagado')
            ->withCount(['pagos as pagos_historicos' => fn ($q) => $q->withTrashed()])
            ->orderBy('fecha_vencimiento')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function totales(): array
    {
        $pendientes = $this->adeudos->where('pagado', false);

        return [
            'saldo' => round($pendientes->sum('saldo_pendiente'), 2),
            'vencido' => round($pendientes->filter->esta_vencido->sum('saldo_pendiente'), 2),
            'conceptos' => $pendientes->count(),
        ];
    }

    /** Bloqueo de plataformas: colegiatura o inglés con más de N días de vencido. */
    #[Computed]
    public function bloqueo(): ?array
    {
        if (! $this->matricula || $this->alumno?->estatus !== EstatusAlumno::ACTIVO) {
            return null;
        }

        $causas = Adeudo::query()->where('matricula', $this->matricula)->causanBloqueo()
            ->with('pagos:id,adeudo_id,monto_pagado')->orderBy('fecha_vencimiento')->get();

        return $causas->isEmpty() ? null : [
            'desde' => $causas->first()->bloqueo_desde,
            'conceptos' => $causas->pluck('concepto')->all(),
            'monto' => round($causas->sum('saldo_pendiente'), 2),
        ];
    }

    #[Computed]
    public function pagos(): Collection
    {
        if (! $this->matricula) {
            return collect();
        }

        return Pago::withTrashed()
            ->whereHas('adeudo', fn ($q) => $q->where('matricula', $this->matricula))
            ->with(['adeudo:id,concepto', 'cajero:id,name'])
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function adeudoCobro(): ?Adeudo
    {
        return $this->adeudoCobroId
            ? Adeudo::query()->with('pagos')->find($this->adeudoCobroId)
            : null;
    }

    /* =====================================================================
     |  Búsqueda y selección de alumno
     | ===================================================================== */

    public function seleccionarAlumno(string $matricula): void
    {
        abort_unless(Alumno::query()->whereKey($matricula)->exists(), 404);

        $this->matricula = $matricula;
        $this->search = '';
        $this->mostrarPagados = false;
        $this->ultimoReciboUrl = null;
        $this->ultimoFolio = null;
        $this->refrescar();
    }

    /** Enter en el buscador: si hay un único resultado (o coincidencia exacta de matrícula) se selecciona. */
    public function seleccionarPrimero(): void
    {
        $exacto = $this->resultados->firstWhere('matricula', mb_strtoupper(trim($this->search)));
        $elegido = $exacto ?? ($this->resultados->count() === 1 ? $this->resultados->first() : null);

        if ($elegido) {
            $this->seleccionarAlumno($elegido->matricula);
        }
    }

    public function cambiarAlumno(): void
    {
        $this->reset('matricula', 'search', 'mostrarPagados', 'ultimoReciboUrl', 'ultimoFolio');
        $this->refrescar();
        $this->dispatch('enfocar-buscador');
    }

    /* =====================================================================
     |  Comentarios administrativos
     | ===================================================================== */

    public function abrirModalComentarios(): void
    {
        $this->comentarios = (string) $this->alumno?->comentarios;
        $this->resetValidation();
        $this->modalComentarios = true;
    }

    public function guardarComentarios(): void
    {
        $this->validate(['comentarios' => 'nullable|string|max:2000']);

        $alumno = Alumno::query()->findOrFail($this->matricula);
        $alumno->update(['comentarios' => trim($this->comentarios) ?: null]);

        $this->modalComentarios = false;
        unset($this->alumno);
        $this->dispatch('toast', tipo: 'success', mensaje: 'Comentarios actualizados.');
    }

    /* =====================================================================
     |  Cobro
     | ===================================================================== */

    public function abrirModalCobro(int $adeudoId): void
    {
        $adeudo = Adeudo::query()->with('pagos')->where('matricula', $this->matricula)->findOrFail($adeudoId);

        if ($adeudo->pagado || $adeudo->saldo_pendiente <= 0) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Ese adeudo ya está liquidado.');

            return;
        }

        $this->adeudoCobroId = $adeudo->id;
        $this->monto_pagar = number_format($adeudo->saldo_pendiente, 2, '.', '');
        $this->forma_pago = FormaPago::EFECTIVO->value;
        $this->referencia = '';
        $this->enviarRecibo = filled($this->alumno?->email);
        $this->resetValidation();
        unset($this->adeudoCobro);
        $this->modalCobro = true;
    }

    public function cerrarModalCobro(): void
    {
        $this->modalCobro = false;
        $this->adeudoCobroId = null;
        $this->resetValidation();
    }

    public function cobrar(RegistrarPagoAction $registrarPago): void
    {
        $saldo = $this->adeudoCobro?->saldo_pendiente ?? 0;

        $this->validate([
            'monto_pagar' => ['required', 'numeric', 'min:0.01', 'max:'.$saldo, 'regex:/^\d+(\.\d{1,2})?$/'],
            'forma_pago' => ['required', Rule::in(FormaPago::valores())],
            'referencia' => [
                Rule::requiredIf(fn () => FormaPago::from($this->forma_pago)->requiereReferencia()),
                'nullable', 'string', 'max:100',
            ],
        ], [
            'monto_pagar.max' => 'El monto no puede exceder el saldo pendiente ($'.number_format($saldo, 2).').',
            'monto_pagar.regex' => 'Capture el monto con máximo 2 decimales.',
            'referencia.required' => 'Capture la referencia bancaria.',
        ]);

        try {
            $pago = $registrarPago->execute(
                adeudoId: $this->adeudoCobroId,
                monto: $this->monto_pagar,
                formaPago: FormaPago::from($this->forma_pago),
                cajero: auth()->user(),
                referencia: $this->referencia,
            );
        } catch (CobranzaException $e) {
            $this->addError('monto_pagar', $e->getMessage());

            return;
        }

        $this->cerrarModalCobro();
        $this->refrescar();

        $this->ultimoFolio = $pago->folio_pago;
        $this->ultimoReciboUrl = route('caja.recibo', $pago);

        $enviado = $this->enviarRecibo && $this->mandarRecibo($pago);
        $this->dispatch('toast', tipo: 'success', mensaje: "Pago registrado. Folio {$pago->folio_pago}".($enviado ? ' · recibo enviado por correo' : ''));
        $this->dispatch('abrir-recibo', url: $this->ultimoReciboUrl);
    }

    /* =====================================================================
     |  Cargos manuales (inscripción, constancias, extraordinarios…)
     | ===================================================================== */

    public function abrirModalCargo(): void
    {
        abort_unless($this->matricula, 404);

        $this->cargoConcepto = '';
        $this->cargoMonto = '';
        $this->cargoVencimiento = today()->toDateString();
        $this->cargoTipo = TipoAdeudo::OTRO->value;
        $this->resetValidation();
        $this->modalCargo = true;
    }

    public function guardarCargo(CrearCargoAction $crearCargo): void
    {
        $this->validate([
            'cargoConcepto' => 'required|string|max:150',
            'cargoMonto' => ['required', 'numeric', 'min:0.01', 'max:999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'cargoVencimiento' => 'required|date',
            'cargoTipo' => ['required', Rule::in(TipoAdeudo::valores())],
        ], ['cargoMonto.regex' => 'Capture el monto con máximo 2 decimales.'],
            ['cargoConcepto' => 'concepto', 'cargoMonto' => 'monto', 'cargoVencimiento' => 'vencimiento']);

        try {
            $adeudo = $crearCargo->execute($this->matricula, $this->cargoConcepto, $this->cargoMonto,
                Carbon::parse($this->cargoVencimiento), auth()->user(), TipoAdeudo::from($this->cargoTipo));
        } catch (CobranzaException $e) {
            $this->addError('cargoConcepto', $e->getMessage());

            return;
        }

        $this->modalCargo = false;
        $this->refrescar();
        $this->dispatch('toast', tipo: 'success', mensaje: "Cargo #{$adeudo->id} agregado: {$adeudo->concepto}.");
    }

    public function eliminarCargo(int $adeudoId, CrearCargoAction $crearCargo): void
    {
        abort_unless(auth()->user()->hasRole('Administrador'), 403);
        abort_unless(Adeudo::whereKey($adeudoId)->where('matricula', $this->matricula)->exists(), 404);

        try {
            $crearCargo->eliminar($adeudoId, auth()->user());
        } catch (CobranzaException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        $this->refrescar();
        $this->dispatch('toast', tipo: 'success', mensaje: 'Cargo eliminado.');
    }

    /* =====================================================================
     |  Recibo por correo
     | ===================================================================== */

    public function reenviarRecibo(int $pagoId): void
    {
        $pago = Pago::query()->whereKey($pagoId)
            ->whereHas('adeudo', fn ($q) => $q->where('matricula', $this->matricula))->firstOrFail();

        $this->mandarRecibo($pago)
            ? $this->dispatch('toast', tipo: 'success', mensaje: "Recibo {$pago->folio_pago} enviado a {$this->alumno->email}.")
            : $this->dispatch('toast', tipo: 'error', mensaje: 'El alumno no tiene correo registrado (agréguelo en el padrón).');
    }

    private function mandarRecibo(Pago $pago): bool
    {
        $correo = $this->alumno?->email;
        if (blank($correo) || $pago->trashed()) {
            return false;
        }

        Mail::to($correo)->queue(new ReciboPagoMail($pago));
        Bitacora::registrar('correo', 'pago', $pago->id, "{$this->matricula} · Folio {$pago->folio_pago} · recibo enviado a {$correo}");

        return true;
    }

    /* =====================================================================
     |  Condonación de recargo (solo Administrador)
     | ===================================================================== */

    public function abrirModalCondonar(int $adeudoId): void
    {
        abort_unless(auth()->user()->hasRole('Administrador'), 403);
        abort_unless(Adeudo::whereKey($adeudoId)->where('matricula', $this->matricula)->exists(), 404);

        $this->adeudoCondonarId = $adeudoId;
        $this->motivoCondonacion = '';
        $this->resetValidation();
        $this->modalCondonar = true;
    }

    public function condonarRecargo(CondonarRecargoAction $condonar): void
    {
        abort_unless(auth()->user()->hasRole('Administrador'), 403);
        $this->validate(['motivoCondonacion' => 'required|string|min:5|max:150'], [], ['motivoCondonacion' => 'motivo']);

        try {
            $adeudo = $condonar->execute($this->adeudoCondonarId, auth()->user(), $this->motivoCondonacion);
        } catch (CobranzaException $e) {
            $this->addError('motivoCondonacion', $e->getMessage());

            return;
        }

        $this->modalCondonar = false;
        $this->refrescar();
        $this->dispatch('toast', tipo: 'success', mensaje: "Recargo condonado en {$adeudo->concepto}.");
    }

    /* =====================================================================
     |  Cancelación de pagos (solo Administrador)
     | ===================================================================== */

    public function abrirModalCancelar(int $pagoId): void
    {
        abort_unless(auth()->user()->hasRole('Administrador'), 403);

        $this->pagoCancelarId = $pagoId;
        $this->motivoCancelacion = '';
        $this->resetValidation();
        $this->modalCancelar = true;
    }

    public function cancelarPago(CancelarPagoAction $cancelarPago): void
    {
        abort_unless(auth()->user()->hasRole('Administrador'), 403);
        $this->validate(['motivoCancelacion' => 'required|string|min:5|max:255']);

        // El pago debe pertenecer al alumno abierto (evita manipular IDs desde el navegador).
        $pertenece = Pago::query()->whereKey($this->pagoCancelarId)
            ->whereHas('adeudo', fn ($q) => $q->where('matricula', $this->matricula))
            ->exists();
        abort_unless($pertenece, 404);

        try {
            $cancelarPago->execute($this->pagoCancelarId, auth()->user(), $this->motivoCancelacion);
        } catch (CobranzaException $e) {
            $this->addError('motivoCancelacion', $e->getMessage());

            return;
        }

        $this->modalCancelar = false;
        $this->pagoCancelarId = null;
        $this->refrescar();
        $this->dispatch('toast', tipo: 'success', mensaje: 'Pago cancelado. El adeudo fue reabierto.');
    }

    /* ===================================================================== */

    public function updatedMostrarPagados(): void
    {
        unset($this->adeudos, $this->totales);
    }

    private function refrescar(): void
    {
        unset($this->alumno, $this->adeudos, $this->totales, $this->pagos, $this->resultados, $this->bloqueo);
    }

    public function render()
    {
        return view('livewire.caja.estado-cuenta', [
            'formasPago' => FormaPago::cases(),
            'conceptosSugeridos' => CrearCargoAction::CONCEPTOS_SUGERIDOS,
            'tiposCargo' => TipoAdeudo::cases(),
        ]);
    }
}
