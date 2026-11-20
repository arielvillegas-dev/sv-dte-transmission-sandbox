<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DteContingencyBatch;
use App\Models\DteDocument;
use App\Services\Dte\MhTransmissionSimulatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class DteDashboardController extends Controller
{
    public function index(): View
    {
        return view('dte_dashboard', [
            'documents' => DteDocument::query()->latest('id')->get(),
            'batches' => DteContingencyBatch::query()->latest('id')->get(),
        ]);
    }

    public function emit(Request $request, MhTransmissionSimulatorService $service): RedirectResponse
    {
        $validated = $request->validate([
            'tipo_dte' => ['required', 'in:01,03,14'],
            'receptor_nombre' => ['required', 'string', 'max:150'],
            'receptor_doc' => ['required', 'string', 'max:20'],
            'receptor_correo' => ['required', 'email'],
            'descripcion' => ['required', 'string', 'max:150'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'precio_unitario' => ['required', 'numeric', 'gte:0'],
            'simulate_outage' => ['nullable', 'boolean'],
        ]);

        $doc = $service->emit([
            'tipo_dte' => $validated['tipo_dte'],
            'receptor_nombre' => $validated['receptor_nombre'],
            'receptor_doc' => $validated['receptor_doc'],
            'receptor_correo' => $validated['receptor_correo'],
            'items' => [[
                'descripcion' => $validated['descripcion'],
                'cantidad' => (float) $validated['cantidad'],
                'precio_unitario' => (float) $validated['precio_unitario'],
            ]],
        ], (bool) ($validated['simulate_outage'] ?? false));

        return redirect()->route('dte.dashboard')->with('status', "DTE {$doc->numero_control} ({$doc->status})");
    }

    public function dispatchContingency(MhTransmissionSimulatorService $service): RedirectResponse
    {
        try {
            $batch = $service->dispatchContingencyBatch();

            return redirect()->route('dte.dashboard')->with('status', "Contingency batch {$batch->codigo_generacion} transmitted.");
        } catch (InvalidArgumentException $e) {
            return redirect()->route('dte.dashboard')->with('status', $e->getMessage());
        }
    }
}
