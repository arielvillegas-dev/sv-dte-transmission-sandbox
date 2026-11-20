<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DteInvalidationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalidates_transmitted_dte_and_prevents_double_invalidation(): void
    {
        $emit = $this->postJson('/api/v1/dte/emit', [
            'tipo_dte' => '01',
            'receptor_nombre' => 'Cliente Anulacion',
            'receptor_doc' => '01234567-8',
            'receptor_correo' => 'anulacion@example.test',
            'items' => [['descripcion' => 'Producto Demo', 'cantidad' => 1, 'precio_unitario' => 25.00]],
        ]);
        $uuid = $emit->json('data.codigo_generacion');

        $inv = $this->postJson("/api/v1/dte/{$uuid}/invalidate", [
            'tipo_anulacion' => 2,
            'motivo' => 'Operacion rescindida por cliente',
        ]);

        $inv->assertOk()
            ->assertJsonPath('data.tipo_anulacion', 2);

        $this->getJson("/api/v1/dte/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.status', 'invalidated');

        // Second invalidation attempt must fail
        $this->postJson("/api/v1/dte/{$uuid}/invalidate", [
            'tipo_anulacion' => 2,
            'motivo' => 'Intento duplicado',
        ])->assertUnprocessable();
    }
}
