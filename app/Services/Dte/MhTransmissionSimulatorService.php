<?php

namespace App\Services\Dte;

use App\Models\DteContingencyBatch;
use App\Models\DteDocument;
use App\Models\DteInvalidationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MhTransmissionSimulatorService
{
    public function __construct(
        private readonly DteDocumentBuilderService $builder,
        private readonly DteSchemaValidatorService $validator,
        private readonly DteSignerSimulatorService $signer,
    ) {}

    /**
     * Emits, signs, and either transmits normally to the simulated MH API or
     * stores in contingency mode when simulateOutage=true.
     *
     * @param  array<string, mixed>  $input
     */
    public function emit(array $input, bool $simulateOutage = false): DteDocument
    {
        $payload = $this->builder->build([
            ...$input,
            'contingency_mode' => $simulateOutage,
        ]);

        $check = $this->validator->validate($payload);
        if (! $check['valid']) {
            throw new InvalidArgumentException(implode(' | ', $check['errors']));
        }

        $jws = $this->signer->sign($payload);
        $id = $payload['identificacion'];

        if ($simulateOutage) {
            return DteDocument::query()->create([
                'codigo_generacion' => $id['codigoGeneracion'],
                'numero_control' => $id['numeroControl'],
                'tipo_dte' => $id['tipoDte'],
                'schema_version' => $id['version'],
                'ambiente' => $id['ambiente'],
                'tipo_transmision' => 2,
                'status' => 'contingency_pending',
                'total_pagar' => (float) $payload['resumen']['totalPagar'],
                'payload_json' => $payload,
                'signed_jws' => $jws,
                'mh_response_json' => [
                    'estado' => 'PENDIENTE_CONTINGENCIA',
                    'observaciones' => ['Servicio MH no disponible (simulado); guardado para envio diferido.'],
                ],
            ]);
        }

        $sello = $this->generateSelloRecibido();

        return DteDocument::query()->create([
            'codigo_generacion' => $id['codigoGeneracion'],
            'numero_control' => $id['numeroControl'],
            'tipo_dte' => $id['tipoDte'],
            'schema_version' => $id['version'],
            'ambiente' => $id['ambiente'],
            'tipo_transmision' => 1,
            'status' => 'transmitted',
            'total_pagar' => (float) $payload['resumen']['totalPagar'],
            'sello_recibido' => $sello,
            'payload_json' => $payload,
            'signed_jws' => $jws,
            'mh_response_json' => [
                'version' => 2,
                'ambiente' => $id['ambiente'],
                'estado' => 'PROCESADO',
                'codigoGeneracion' => $id['codigoGeneracion'],
                'selloRecibido' => $sello,
                'fhProcesamiento' => date('d/m/Y H:i:s'),
                'descripcionMsg' => 'RECIBIDO',
            ],
        ]);
    }

    /**
     * Groups pending contingency DTEs into an official v4 contingency event and
     * transmits them when service connectivity is restored.
     */
    public function dispatchContingencyBatch(int $motivoCode = 1, string $motivoDesc = 'No disponibilidad del sistema MH'): DteContingencyBatch
    {
        return DB::transaction(function () use ($motivoCode, $motivoDesc) {
            $pending = DteDocument::query()
                ->where('status', 'contingency_pending')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($pending->isEmpty()) {
                throw new InvalidArgumentException('No pending contingency documents to transmit.');
            }

            $batchUuid = strtoupper((string) Str::uuid());
            $batchSello = $this->generateSelloRecibido();
            $today = date('Y-m-d');

            $eventPayload = [
                'identificacion' => [
                    'version' => 4,
                    'ambiente' => '00',
                    'codigoGeneracion' => $batchUuid,
                    'fTransmision' => $today,
                    'hTransmision' => date('H:i:s'),
                ],
                'emisor' => $pending->first()->payload_json['emisor'],
                'detalleDTE' => $pending->values()->map(fn (DteDocument $doc, int $idx) => [
                    'noItem' => $idx + 1,
                    'codigoGeneracion' => $doc->codigo_generacion,
                    'tipoDoc' => $doc->tipo_dte,
                ])->all(),
                'motivo' => [
                    'fInicio' => $today,
                    'fFin' => $today,
                    'hInicio' => '08:00:00',
                    'hFin' => date('H:i:s'),
                    'tipoContingencia' => $motivoCode,
                    'motivoContingencia' => $motivoDesc,
                ],
            ];

            $batch = DteContingencyBatch::query()->create([
                'codigo_generacion' => $batchUuid,
                'motivo_code' => $motivoCode,
                'motivo_desc' => $motivoDesc,
                'f_inicio' => $today,
                'f_fin' => $today,
                'status' => 'transmitted',
                'sello_recibido' => $batchSello,
                'event_payload_json' => $eventPayload,
            ]);

            foreach ($pending as $doc) {
                $docSello = $this->generateSelloRecibido();
                $doc->update([
                    'status' => 'transmitted',
                    'contingency_batch_id' => $batch->id,
                    'sello_recibido' => $docSello,
                    'mh_response_json' => [
                        'estado' => 'PROCESADO',
                        'codigoGeneracion' => $doc->codigo_generacion,
                        'selloRecibido' => $docSello,
                        'observaciones' => ["Transmitido via lote de contingencia {$batchUuid}"],
                    ],
                ]);
            }

            return $batch->load('documents');
        });
    }

    /**
     * Generates an invalidation event (v3) for a transmitted DTE.
     */
    public function invalidate(DteDocument $doc, int $tipoAnulacion = 2, string $motivo = 'Rescision de operacion'): DteInvalidationEvent
    {
        if ($doc->status !== 'transmitted') {
            throw new InvalidArgumentException('Only transmitted DTE documents can be invalidated.');
        }

        return DB::transaction(function () use ($doc, $tipoAnulacion, $motivo) {
            $eventUuid = strtoupper((string) Str::uuid());
            $sello = $this->generateSelloRecibido();

            $payload = [
                'identificacion' => [
                    'version' => 3,
                    'ambiente' => $doc->ambiente,
                    'codigoGeneracion' => $eventUuid,
                    'fecAnula' => date('Y-m-d'),
                    'horAnula' => date('H:i:s'),
                ],
                'emisor' => $doc->payload_json['emisor'],
                'documento' => [
                    'tipoDte' => $doc->tipo_dte,
                    'codigoGeneracion' => $doc->codigo_generacion,
                    'selloRecibido' => $doc->sello_recibido,
                    'numeroControl' => $doc->numero_control,
                    'fecEmi' => $doc->payload_json['identificacion']['fecEmi'],
                    'montoIva' => $doc->payload_json['resumen']['totalIva'] ?? ($doc->payload_json['resumen']['tributos'][0]['valor'] ?? 0),
                ],
                'motivo' => [
                    'tipoAnulacion' => $tipoAnulacion,
                    'motivoAnulacion' => $motivo,
                ],
            ];

            $event = DteInvalidationEvent::query()->create([
                'dte_document_id' => $doc->id,
                'codigo_generacion' => $eventUuid,
                'tipo_anulacion' => $tipoAnulacion,
                'motivo' => $motivo,
                'sello_recibido' => $sello,
                'event_payload_json' => $payload,
            ]);

            $doc->update(['status' => 'invalidated']);

            return $event;
        });
    }

    private function generateSelloRecibido(): string
    {
        return strtoupper(Str::random(40));
    }
}
