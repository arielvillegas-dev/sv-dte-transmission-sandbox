<?php

namespace Tests\Unit;

use App\Services\Dte\DteTaxCalculatorService;
use PHPUnit\Framework\TestCase;

class DteTaxCalculatorTest extends TestCase
{
    public function test_calculates_fe_01_with_iva_included(): void
    {
        $calc = new DteTaxCalculatorService();
        $res = $calc->calculate('01', [
            ['descripcion' => 'Producto A', 'cantidad' => 1, 'precio_unitario' => 113.00],
        ]);

        $this->assertSame(113.00, $res['summary']['totalPagar']);
        $this->assertSame(13.00, $res['summary']['totalIva']);
    }

    public function test_calculates_ccf_03_with_added_iva_and_retention(): void
    {
        $calc = new DteTaxCalculatorService();
        $res = $calc->calculate('03', [
            ['descripcion' => 'Servicio B', 'cantidad' => 2, 'precio_unitario' => 100.00],
        ], applyIvaRetention: true);

        $this->assertSame(200.00, $res['summary']['totalGravada']);
        $this->assertSame(26.00, $res['summary']['tributos'][0]['valor']);
        $this->assertSame(2.00, $res['summary']['ivaRete1']);
        $this->assertSame(224.00, $res['summary']['totalPagar']);
    }

    public function test_calculates_fse_14_with_10_percent_income_retention(): void
    {
        $calc = new DteTaxCalculatorService();
        $res = $calc->calculate('14', [
            ['descripcion' => 'Honorarios C', 'cantidad' => 1, 'precio_unitario' => 200.00],
        ]);

        $this->assertSame(200.00, $res['summary']['totalCompra']);
        $this->assertSame(20.00, $res['summary']['reteRenta']);
        $this->assertSame(180.00, $res['summary']['totalPagar']);
    }
}
