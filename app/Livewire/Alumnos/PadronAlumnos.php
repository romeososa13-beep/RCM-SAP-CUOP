<?php

namespace App\Livewire\Alumnos;

use App\Enums\EstatusAlumno;
use App\Exports\PlantillaAlumnosExport;
use App\Models\Alumno;
use App\Models\Bitacora;
use App\Models\Carrera;
use App\Models\PlanPago;
use App\Models\Sede;
use App\Rules\PlanCompatible;
use App\Services\Padron\ImportarAlumnosService;
use App\Support\BitacoraFormato;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('components.layouts.app')]
#[Title('Padrón de alumnos')]
class PadronAlumnos extends Component
{
    use WithFileUploads, WithPagination;

    /* ---------- Filtros ---------- */
    #[Url]
    public string $buscar = '';

    #[Url]
    public ?string $sedeId = null;

    #[Url]
    public ?string $carreraId = null;

    #[Url]
    public string $grupo = '';

    #[Url]
    public string $estatus = '';

    /* ---------- Modal alta / edición ---------- */
    public bool $modalForm = false;

    #[Locked]
    public ?string $editando = null;

    public array $form = [];

    /* ---------- Importación ---------- */
    public bool $modalImportar = false;

    public $archivo = null;

    public ?array $preview = null;

    public function updating($prop): void
    {
        if (in_array($prop, ['buscar', 'sedeId', 'carreraId', 'grupo', 'estatus'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('buscar', 'sedeId', 'carreraId', 'grupo', 'estatus');
        $this->resetPage();
    }

    #[Computed]
    public function catalogos(): array
    {
        return [
            'sedes' => Sede::orderBy('clave')->get(['id', 'clave', 'nombre']),
            'carreras' => Carrera::orderBy('nombre')->get(['id', 'nombre', 'modalidad']),
        ];
    }

    /** Planes que se pueden asignar según la sede y carrera elegidas en el formulario. */
    #[Computed]
    public function planesFormulario()
    {
        $sede = filled($this->form['sede_id'] ?? null) ? (int) $this->form['sede_id'] : null;
        $carrera = filled($this->form['carrera_id'] ?? null) ? (int) $this->form['carrera_id'] : null;

        if (! $sede) {
            return collect();
        }

        return PlanPago::compatibleCon($sede, $carrera)
            ->with(['sede:id,clave', 'carrera:id,nombre'])
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'sede_id', 'carrera_id', 'monto_mensualidad']);
    }

    /** Si cambia la sede o carrera y el plan elegido ya no aplica, se limpia. */
    public function updatedForm($valor, string $campo): void
    {
        if (in_array($campo, ['sede_id', 'carrera_id'], true)) {
            unset($this->planesFormulario);
            if (! $this->planesFormulario->contains('id', (int) ($this->form['plan_pago_id'] ?? 0))) {
                $this->form['plan_pago_id'] = $this->planesFormulario->count() === 1 ? $this->planesFormulario->first()->id : null;
            }
        }
    }

    #[Computed]
    public function grupos()
    {
        return Alumno::query()
            ->when($this->sedeId, fn ($q) => $q->where('sede_id', $this->sedeId))
            ->when($this->carreraId, fn ($q) => $q->where('carrera_id', $this->carreraId))
            ->distinct()->orderBy('grupo')->pluck('grupo');
    }

    /** Últimos cambios del alumno y de sus cargos (solo al editar). */
    #[Computed]
    public function historial()
    {
        if (! $this->editando) {
            return collect();
        }

        return Bitacora::query()->with('usuario:id,name')
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('entidad', 'alumno')->where('entidad_id', $this->editando))
                ->orWhere(fn ($q) => $q->whereIn('entidad', ['adeudo', 'pago'])->where('descripcion', 'like', $this->editando.' ·%')))
            ->orderByDesc('id')->limit(20)->get();
    }

    /* =================== Alta / edición individual =================== */

    public function nuevo(): void
    {
        $this->editando = null;
        $this->form = [
            'matricula' => '', 'nombre_completo' => '', 'sede_id' => $this->sedeId, 'carrera_id' => $this->carreraId,
            'grupo' => $this->grupo, 'plan_pago_id' => null, 'costo_ingles' => null,
            'beca_porcentaje' => '0', 'beca_ingles' => false, 'beca_motivo' => '',
            'celular' => '', 'tel_tutor' => '', 'tel_alterno' => '', 'estatus' => 'ACTIVO', 'comentarios' => '',
        ];
        $this->resetValidation();
        $this->modalForm = true;
    }

    public function editar(string $matricula): void
    {
        $a = Alumno::findOrFail($matricula);
        $this->editando = $a->matricula;
        $this->form = $a->only(['matricula', 'nombre_completo', 'sede_id', 'carrera_id', 'grupo', 'plan_pago_id', 'costo_ingles', 'beca_porcentaje', 'beca_ingles', 'beca_motivo', 'celular', 'tel_tutor', 'tel_alterno', 'comentarios'])
            + ['estatus' => $a->estatus->value];
        $this->resetValidation();
        $this->modalForm = true;
    }

    public function guardar(): void
    {
        $this->form['matricula'] = mb_strtoupper(trim((string) ($this->form['matricula'] ?? '')));
        $this->form['nombre_completo'] = mb_strtoupper(trim((string) ($this->form['nombre_completo'] ?? '')));
        $this->form['grupo'] = mb_strtoupper(trim((string) ($this->form['grupo'] ?? '')));
        $this->form['plan_pago_id'] = filled($this->form['plan_pago_id'] ?? null) ? (int) $this->form['plan_pago_id'] : null;
        foreach (['celular', 'tel_tutor', 'tel_alterno'] as $tel) {
            $this->form[$tel] = Alumno::normalizarTelefono($this->form[$tel] ?? null);
        }
        $this->form['costo_ingles'] = filled($this->form['costo_ingles'] ?? null) && (float) $this->form['costo_ingles'] > 0
            ? $this->form['costo_ingles'] : null;

        $datos = $this->validate([
            'form.matricula' => ['required', 'max:20', 'regex:/^[A-Z0-9\-]+$/',
                $this->editando ? Rule::in([$this->editando]) : Rule::unique('alumnos', 'matricula')],
            'form.nombre_completo' => 'required|max:200',
            'form.sede_id' => 'required|exists:sedes,id',
            'form.carrera_id' => 'required|exists:carreras,id',
            'form.grupo' => 'required|max:80',
            'form.plan_pago_id' => ['nullable', Rule::requiredIf(($this->form['estatus'] ?? '') === EstatusAlumno::ACTIVO->value),
                'exists:planes_pago,id', new PlanCompatible(
                    filled($this->form['sede_id'] ?? null) ? (int) $this->form['sede_id'] : null,
                    filled($this->form['carrera_id'] ?? null) ? (int) $this->form['carrera_id'] : null,
                )],
            'form.estatus' => ['required', Rule::in(EstatusAlumno::valores())],
            'form.costo_ingles' => 'nullable|numeric|min:0|max:99999.99',
            'form.beca_porcentaje' => 'nullable|numeric|min:0|max:100',
            'form.beca_ingles' => 'boolean',
            'form.beca_motivo' => ['nullable', 'max:150', Rule::requiredIf(auth()->user()->hasRole('Administrador') && (float) ($this->form['beca_porcentaje'] ?? 0) > 0)],
            'form.celular' => 'nullable|max:20', 'form.tel_tutor' => 'nullable|max:20', 'form.tel_alterno' => 'nullable|max:20',
            'form.comentarios' => 'nullable|max:2000',
        ], [
            'form.matricula.in' => 'La matrícula no se puede modificar.',
            'form.plan_pago_id.required' => 'Un alumno ACTIVO necesita plan de pago.',
            'form.beca_motivo.required' => 'Indique el motivo o tipo de beca.',
        ], [
            'form.matricula' => 'matrícula', 'form.nombre_completo' => 'nombre',
            'form.sede_id' => 'sede', 'form.carrera_id' => 'carrera', 'form.plan_pago_id' => 'plan de pago', 'form.costo_ingles' => 'costo de inglés',
        ])['form'];

        // La beca es una decisión financiera: solo el Administrador la asigna o cambia.
        if (! auth()->user()->hasRole('Administrador')) {
            unset($datos['beca_porcentaje'], $datos['beca_ingles'], $datos['beca_motivo']);
        } else {
            $datos['beca_porcentaje'] = (float) ($datos['beca_porcentaje'] ?? 0);
            $datos['beca_ingles'] = $datos['beca_porcentaje'] > 0 && (bool) ($datos['beca_ingles'] ?? false);
            $datos['beca_motivo'] = $datos['beca_porcentaje'] > 0 ? (mb_strtoupper(trim((string) ($datos['beca_motivo'] ?? ''))) ?: null) : null;
        }

        Alumno::updateOrCreate(['matricula' => $datos['matricula']], $datos);

        $this->modalForm = false;
        unset($this->grupos);
        $this->dispatch('toast', tipo: 'success', mensaje: $this->editando ? 'Alumno actualizado.' : 'Alumno registrado.');
    }

    /* =================== Importación masiva =================== */

    public function abrirImportar(): void
    {
        $this->reset('archivo', 'preview');
        $this->resetValidation();
        $this->modalImportar = true;
    }

    public function updatedArchivo(ImportarAlumnosService $service): void
    {
        $this->preview = null;
        $this->validate(['archivo' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240']);

        try {
            $this->preview = $service->analizar($this->archivo->getRealPath());
        } catch (\Throwable $e) {
            report($e);
            $this->addError('archivo', 'No se pudo leer el archivo. Verifique que use la plantilla.');
        }
    }

    public function confirmarImportacion(ImportarAlumnosService $service): void
    {
        abort_unless($this->archivo, 422);

        // Se re-analiza el archivo en el servidor: nunca se confía en el preview enviado por el navegador.
        $analisis = $service->analizar($this->archivo->getRealPath());
        $r = $service->importar($analisis);

        $this->modalImportar = false;
        $this->reset('archivo', 'preview');
        unset($this->grupos);
        $this->dispatch('toast', tipo: 'success',
            mensaje: "Importación completa: {$r['creados']} creados, {$r['actualizados']} actualizados, ".count($analisis['errores']).' con error.');
    }

    public function descargarPlantilla()
    {
        return Excel::download(new PlantillaAlumnosExport, 'plantilla-padron-alumnos.xlsx');
    }

    public function render()
    {
        $alumnos = Alumno::query()
            ->with(['sede:id,clave', 'carrera:id,nombre', 'planPago:id,nombre'])
            ->buscar($this->buscar)
            ->when($this->sedeId, fn ($q) => $q->where('sede_id', $this->sedeId))
            ->when($this->carreraId, fn ($q) => $q->where('carrera_id', $this->carreraId))
            ->when($this->grupo, fn ($q) => $q->where('grupo', $this->grupo))
            ->when($this->estatus, fn ($q) => $q->where('estatus', $this->estatus))
            ->orderBy('nombre_completo')
            ->paginate(15);

        return view('livewire.alumnos.padron-alumnos', [
            'alumnos' => $alumnos,
            'estatuses' => EstatusAlumno::cases(),
            'fmt' => new BitacoraFormato,
        ]);
    }
}
