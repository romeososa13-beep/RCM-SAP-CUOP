<?php

namespace App\Livewire\Reportes;

use App\Models\Carrera;
use App\Models\Sede;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Cartera vencida')]
class CarteraVencida extends Component
{
    use WithPagination;

    #[Url]
    public ?string $sedeId = null;

    #[Url]
    public ?string $carreraId = null;

    public function updating(): void
    {
        $this->resetPage();
    }

    private function query()
    {
        // Suma de pagos vigentes por adeudo (excluye cancelados).
        $pagos = DB::table('pagos')
            ->whereNull('deleted_at')
            ->selectRaw('adeudo_id, SUM(monto_pagado) as abonado')
            ->groupBy('adeudo_id');

        return DB::table('adeudos as a')
            ->join('alumnos as al', 'al.matricula', '=', 'a.matricula')
            ->join('sedes as s', 's.id', '=', 'al.sede_id')
            ->join('carreras as c', 'c.id', '=', 'al.carrera_id')
            ->leftJoinSub($pagos, 'p', 'p.adeudo_id', '=', 'a.id')
            ->where('a.pagado', false)
            ->whereDate('a.fecha_vencimiento', '<', today()->toDateString())
            ->when($this->sedeId, fn ($q) => $q->where('al.sede_id', $this->sedeId))
            ->when($this->carreraId, fn ($q) => $q->where('al.carrera_id', $this->carreraId));
    }

    public function render()
    {
        $saldoSql = 'SUM(a.monto_original + a.recargo_aplicado - a.pagado_previo - COALESCE(p.abonado, 0))';

        $filas = $this->query()
            ->selectRaw("al.matricula, al.nombre_completo, al.grupo, al.estatus, s.clave as sede, c.nombre as carrera,
                         COUNT(a.id) as conceptos, MIN(a.fecha_vencimiento) as mas_antiguo, {$saldoSql} as saldo_vencido")
            ->groupBy('al.matricula', 'al.nombre_completo', 'al.grupo', 'al.estatus', 's.clave', 'c.nombre')
            ->orderByDesc('saldo_vencido')
            ->paginate(20);

        $resumen = $this->query()->selectRaw("COUNT(DISTINCT al.matricula) as alumnos, {$saldoSql} as total")->first();

        return view('livewire.reportes.cartera-vencida', [
            'filas' => $filas,
            'resumen' => $resumen,
            'sedes' => Sede::activas()->orderBy('clave')->get(),
            'carreras' => Carrera::activas()->orderBy('nombre')->get(),
        ]);
    }
}
