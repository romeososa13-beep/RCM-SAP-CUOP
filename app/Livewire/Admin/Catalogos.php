<?php

namespace App\Livewire\Admin;

use App\Models\Carrera;
use App\Models\PlanPago;
use App\Models\Sede;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Catálogos')]
class Catalogos extends Component
{
    private const MODELOS = ['sedes' => Sede::class, 'carreras' => Carrera::class, 'planes' => PlanPago::class];

    #[Url]
    public string $tab = 'sedes';

    public bool $modal = false;

    #[Locked]
    public ?int $editandoId = null;

    public array $form = [];

    public function updatedTab(): void
    {
        abort_unless(array_key_exists($this->tab, self::MODELOS), 404);
        $this->modal = false;
    }

    private function vacio(): array
    {
        return match ($this->tab) {
            'sedes' => ['clave' => '', 'nombre' => ''],
            'carreras' => ['nombre' => '', 'modalidad' => 'ESCOLARIZADO'],
            'planes' => ['nombre' => '', 'sede_id' => null, 'carrera_id' => null, 'monto_mensualidad' => '', 'monto_recargo' => '0', 'dias_tolerancia' => 10],
        };
    }

    public function nuevo(): void
    {
        $this->editandoId = null;
        $this->form = $this->vacio();
        $this->resetValidation();
        $this->modal = true;
    }

    public function editar(int $id): void
    {
        $m = (self::MODELOS[$this->tab])::findOrFail($id);
        $this->editandoId = $m->id;
        $this->form = $m->only(array_keys($this->vacio()));
        $this->resetValidation();
        $this->modal = true;
    }

    public function guardar(): void
    {
        foreach (['clave', 'nombre', 'modalidad'] as $c) {
            if (isset($this->form[$c])) {
                $this->form[$c] = mb_strtoupper(trim((string) $this->form[$c]));
            }
        }

        $id = $this->editandoId;
        $reglas = match ($this->tab) {
            'sedes' => [
                'form.clave' => ['required', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('sedes', 'clave')->ignore($id)],
                'form.nombre' => 'required|max:120',
            ],
            'carreras' => [
                'form.nombre' => ['required', 'max:150',
                    Rule::unique('carreras', 'nombre')->where('modalidad', $this->form['modalidad'] ?? '')->ignore($id)],
                'form.modalidad' => 'required|max:50',
            ],
            'planes' => [
                'form.nombre' => ['required', 'max:120', Rule::unique('planes_pago', 'nombre')
                    ->where(fn ($q) => $q
                        ->where(fn ($q) => filled($this->form['sede_id'] ?? null) ? $q->where('sede_id', $this->form['sede_id']) : $q->whereNull('sede_id'))
                        ->where(fn ($q) => filled($this->form['carrera_id'] ?? null) ? $q->where('carrera_id', $this->form['carrera_id']) : $q->whereNull('carrera_id')))
                    ->ignore($id)],
                'form.sede_id' => 'required|exists:sedes,id',
                'form.carrera_id' => 'nullable|exists:carreras,id',
                'form.monto_mensualidad' => 'required|numeric|min:0|max:999999.99',
                'form.monto_recargo' => 'required|numeric|min:0|max:999999.99',
                'form.dias_tolerancia' => 'required|integer|min:0|max:31',
            ],
        };

        $datos = $this->validate($reglas, [
            'form.clave.regex' => 'Solo letras, números y guiones (ej. CUOP-LOR).',
            'form.nombre.unique' => $this->tab === 'planes'
                ? 'Esa sede ya tiene un plan con ese nombre.'
                : 'Ya existe una carrera con ese nombre y modalidad.',
        ], [
            'form.clave' => 'clave', 'form.nombre' => 'nombre', 'form.modalidad' => 'modalidad',
            'form.sede_id' => 'sede', 'form.carrera_id' => 'carrera', 'form.monto_mensualidad' => 'mensualidad', 'form.monto_recargo' => 'recargo', 'form.dias_tolerancia' => 'días de tolerancia',
        ])['form'];

        if ($this->tab === 'planes') {
            $datos['carrera_id'] = filled($datos['carrera_id'] ?? null) ? $datos['carrera_id'] : null;

            // No permitir mover un plan a otra sede/carrera si deja alumnos con un plan ajeno.
            if ($id && ($plan = PlanPago::find($id))) {
                $incompatibles = $plan->alumnos()
                    ->where(fn ($q) => $q->where('sede_id', '!=', $datos['sede_id'])
                        ->when($datos['carrera_id'], fn ($q) => $q->orWhere('carrera_id', '!=', $datos['carrera_id'])))
                    ->count();
                if ($incompatibles > 0) {
                    $this->addError('form.sede_id', "{$incompatibles} alumno(s) con este plan quedarían fuera de su sede/carrera. Cámbieles el plan primero.");

                    return;
                }
            }
        }

        $clase = self::MODELOS[$this->tab];
        $id ? $clase::findOrFail($id)->update($datos) : $clase::create($datos);

        $this->modal = false;
        $this->dispatch('toast', tipo: 'success', mensaje: 'Catálogo actualizado.');
    }

    public function eliminar(int $id): void
    {
        $m = (self::MODELOS[$this->tab])::withCount('alumnos')->findOrFail($id);

        if ($m->alumnos_count > 0) {
            $this->dispatch('toast', tipo: 'error', mensaje: "No se puede eliminar: tiene {$m->alumnos_count} alumno(s) asignado(s).");

            return;
        }

        $columna = ['sedes' => 'sede_id', 'carreras' => 'carrera_id'][$this->tab] ?? null;
        if ($columna && ($planes = PlanPago::where($columna, $id)->count()) > 0) {
            $this->dispatch('toast', tipo: 'error', mensaje: "No se puede eliminar: tiene {$planes} plan(es) de pago ligados.");

            return;
        }

        $m->delete();
        $this->dispatch('toast', tipo: 'success', mensaje: 'Registro eliminado.');
    }

    public function render()
    {
        $clase = self::MODELOS[$this->tab] ?? abort(404);

        $query = $clase::withCount('alumnos');
        if ($this->tab === 'planes') {
            $query->with(['sede:id,clave,nombre', 'carrera:id,nombre'])
                ->orderByRaw('sede_id IS NULL')->orderBy('sede_id');
        }

        return view('livewire.admin.catalogos', [
            'registros' => $query->orderBy($this->tab === 'sedes' ? 'clave' : 'nombre')->get(),
            'sedes' => $this->tab === 'planes' ? Sede::orderBy('clave')->get(['id', 'clave', 'nombre']) : collect(),
            'carreras' => $this->tab === 'planes' ? Carrera::orderBy('nombre')->get(['id', 'nombre', 'modalidad']) : collect(),
            'modalidades' => ['ESCOLARIZADO', 'NO ESCOLARIZADO', 'MIXTO', 'EN LÍNEA', 'POR CONFIRMAR'],
        ]);
    }
}
