<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DteNormalAndContingencyFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_emits_and_transmits_fe_01_and_ccf_03_with_sequential_control_numbers(): void
    {
        $fe = $this->postJson('/api/v1/dte/emit', [
            'tipo_dte' => '01',
            'receptor_nombre' => 'Consumidor Demo',
            'receptor_doc' => '01234567-8',
            'receptor_correo' => 'cliente@example.test',
            'items' => [['descripcion' => 'Licencia Software', 'cantidad' => 1, 'precio_unitario' => 56.50]],
        ]);

        $fe->assertCreated()
            ->assertJsonPath('data.status', 'transmitted')
            ->assertJsonPath('data.schema_version', 2);

        $this->assertMatchesRegularExpression('/^DTE-01-M001P001-000000000000001$/', $fe->json('data.numero_control'));
        $this->assertNotEmpty($fe->json('data.sello_recibido'));
        $this->assertNotEmpty($fe->json('data.signed_jws'));

        $ccf = $this->postJson('/api/v1/dte/emit', [
            'tipo_dte' => '03',
            'receptor_nombre' => 'Empresa Demo',
            'receptor_doc' => '06140101901010',
            'receptor_correo' => 'empresa@example.test',
            'apply_iva_retention' => true,
            'items' => [['descripcion' => 'Consultoria', 'cantidad' => 1, 'precio_unitario' => 100.00]],
        ]);

        $ccf->assertCreated()
            ->assertJsonPath('data.status', 'transmitted')
            ->assertJsonPath('data.schema_version', 4)
            ->assertJsonPath('data.total_pagar', 112);
    }

    public function test_handles_outage_contingency_mode_and_dispatches_v4_batch_on_recovery(): void
    {
        $pending = $this->postJson('/api/v1/dte/emit', [
            'tipo_dte' => '14',
            'receptor_nombre' => 'Proveedor Demo',
            'receptor_doc' => '09876543-2',
            'receptor_correo' => 'proveedor@example.test',
            'simulate_outage' => true,
            'items' => [['descripcion' => 'Soporte Tecnico', 'cantidad' => 1, 'precio_unitario' => 100.00]],
        ]);

        $pending->assertCreated()
            ->assertJsonPath('data.status', 'contingency_pending')
            ->assertJsonPath('data.tipo_transmision', 2)
            ->assertJsonPath('data.total_pagar', 90);

        $dispatch = $this->postJson('/api/v1/dte/contingency/dispatch', [
            'motivo_code' => 1,
            'motivo_desc' => 'Enlace caido temporalmente',
        ]);

        $dispatch->assertOk()
            ->assertJsonPath('data.status', 'transmitted')
            ->assertJsonCount(1, 'data.documents');

        $uuid = $pending->json('data.codigo_generacion');
        $this->getJson("/api/v1/dte/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.status', 'transmitted');
    }
}
