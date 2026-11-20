<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DteDocument;
use App\Services\Dte\MhTransmissionSimulatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DteSandboxController extends Controller
{
    public function emit(Request $request, MhTransmissionSimulatorService $service): JsonResponse
    {
        $validated = $request->validate([
            'tipo_dte' => ['required', 'in:01,03,14'],
            'receptor_nombre' => ['required', 'string', 'max:150'],
            'receptor_doc' => ['required', 'string', 'max:20'],
            'receptor_correo' => ['required', 'email'],
            'simulate_outage' => ['sometimes', 'boolean'],
            'apply_iva_retention' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['required', 'string', 'max:150'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.precio_unitario' => ['required', 'numeric', 'gte:0'],
        ]);

        $doc = $service->emit($validated, (bool) ($validated['simulate_outage'] ?? false));

        return response()->json(['data' => $doc], 201);
    }

    public function dispatchContingency(Request $request, MhTransmissionSimulatorService $service): JsonResponse
    {
        $validated = $request->validate([
            'motivo_code' => ['sometimes', 'integer', 'between:1,5'],
            'motivo_desc' => ['sometimes', 'string', 'max:180'],
        ]);

        try {
            $batch = $service->dispatchContingencyBatch(
                (int) ($validated['motivo_code'] ?? 1),
                (string) ($validated['motivo_desc'] ?? 'Falla temporal simulada de enlace')
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $batch]);
    }

    public function invalidate(string $codigoGeneracion, Request $request, MhTransmissionSimulatorService $service): JsonResponse
    {
        $doc = DteDocument::query()->where('codigo_generacion', strtoupper($codigoGeneracion))->firstOrFail();
        $validated = $request->validate([
            'tipo_anulacion' => ['sometimes', 'integer', 'between:1,3'],
            'motivo' => ['required', 'string', 'max:250'],
        ]);

        try {
            $event = $service->invalidate(
                $doc,
                (int) ($validated['tipo_anulacion'] ?? 2),
                $validated['motivo']
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $event]);
    }

    public function show(string $codigoGeneracion): JsonResponse
    {
        $doc = DteDocument::query()->where('codigo_generacion', strtoupper($codigoGeneracion))->firstOrFail();

        return response()->json(['data' => $doc]);
    }
}
