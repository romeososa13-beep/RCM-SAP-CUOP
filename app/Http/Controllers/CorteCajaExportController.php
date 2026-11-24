<?php

namespace App\Http\Controllers;

use App\Exports\CorteCajaExport;
use App\Models\User;
use App\Services\Reportes\CorteCajaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class CorteCajaExportController extends Controller
{
    public function __invoke(Request $request, string $formato, CorteCajaService $service)
    {
        $data = $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
            'cajero' => 'nullable|integer|exists:users,id',
        ]);

        // Un cajero solo puede exportar su propio corte.
        $cajeroId = $request->user()->hasRole('Administrador') ? ($data['cajero'] ?? null) : $request->user()->id;

        $corte = $service->generar(Carbon::parse($data['desde']), Carbon::parse($data['hasta']), $cajeroId);
        $cajero = $cajeroId ? User::find($cajeroId)->name : 'TODOS LOS CAJEROS';
        $archivo = 'corte-caja-'.$corte['desde']->format('Ymd').'-'.$corte['hasta']->format('Ymd');

        if ($formato === 'xlsx') {
            return Excel::download(new CorteCajaExport($corte, $cajero), "{$archivo}.xlsx");
        }

        return Pdf::loadView('pdf.corte-caja', compact('corte', 'cajero'))
            ->setPaper('letter')
            ->stream("{$archivo}.pdf");
    }
}
