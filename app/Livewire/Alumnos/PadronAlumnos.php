<?php

namespace App\Livewire\Alumnos;

use App\Enums\EstatusAlumno;
use App\Exports\PlantillaAlumnosExport;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\PlanPago;
use App\Models\Sede;
use App\Services\Padron\ImportarAlumnosService;
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
    #[Url] public string $buscar = '';
    #[Url] public ?string $sedeId = null;
    #[Url] public ?string $carreraId = null;
    #[Url] public string $grupo = '';
    #[Url] public string $estatus = '';

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
            'planes' => PlanPago::orderBy('nombre')->get(['id', 'nombre', 'monto_mensualidad']),
        ];
    }

    #[Computed]
    public function grupos()
    {
        return Alumno::query()
            ->when($this->sedeId, fn ($q) => $q->where('sede_id', $this->sedeId))
            ->when($this->carreraId, fn ($q) => $q->where('carrera_id', $this->carreraId))
            ->distinct()->orderBy('grupo')->pluck('grupo');
    }

    /* =================== Alta / edición individual =================== */

    public function nuevo(): void
    {
        $this->editando = null;
        $this->form = [
            'matricula' => '', 'nombre_completo' => '', 'sede_id' => $this->sedeId, 'carrera_id' => $this->carreraId,
            'grupo' => $this->grupo, 'plan_pago_id' => null, 'estatus' => 'ACTIVO', 'comentarios' => '',
        ];
        $this->resetValidation();
        $this->modalForm = true;
    }

    public function editar(string $matricula): void
    {
        $a = Alumno::findOrFail($matricula);
        $this->editando = $a->matricula;
        $this->form = $a->only(['matricula', 'nombre_completo', 'sede_id', 'carrera_id', 'grupo', 'plan_pago_id', 'comentarios'])
            + ['estatus' => $a->estatus->value];
        $this->resetValidation();
        $this->modalForm = true;
    }

    public function guardar(): void
    {
        $this->form['matricula'] = mb_strtoupper(trim((string) ($this->form['matricula'] ?? '')));
        $this->form['nombre_completo'] = mb_strtoupper(trim((string) ($this->form['nombre_completo'] ?? '')));
        $this->form['grupo'] = mb_strtoupper(trim((string) ($this->form['grupo'] ?? '')));

        $datos = $this->validate([
            'form.matricula' => ['required', 'max:20', 'regex:/^[A-Z0-9\-]+$/',
                $this->editando ? Rule::in([$this->editando]) : Rule::unique('alumnos', 'matricula')],
            'form.nombre_completo' => 'required|max:200',
            'form.sede_id' => 'required|exists:sedes,id',
            'form.carrera_id' => 'required|exists:carreras,id',
            'form.grupo' => 'required|max:80',
            'form.plan_pago_id' => 'required|exists:planes_pago,id',
            'form.estatus' => ['required', Rule::in(EstatusAlumno::valores())],
            'form.comentarios' => 'nullable|max:2000',
        ], [
            'form.matricula.in' => 'La matrícula no se puede modificar.',
        ], [
            'form.matricula' => 'matrícula', 'form.nombre_completo' => 'nombre',
            'form.sede_id' => 'sede', 'form.carrera_id' => 'carrera', 'form.plan_pago_id' => 'plan de pago',
        ])['form'];

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
        return Excel::download(new PlantillaAlumnosExport(), 'plantilla-padron-alumnos.xlsx');
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
        ]);
    }
}
