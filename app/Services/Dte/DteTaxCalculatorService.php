<?php

namespace App\Services\Dte;

use InvalidArgumentException;

class DteTaxCalculatorService
{
    public const IVA_RATE = 0.13;
    public const IVA_RETENTION_RATE = 0.01;
    public const FSE_RENTA_RATE = 0.10;

    /**
     * Calculates line items and summary totals following SVFE DTE rules:
     * - 01 (Factura): unit prices are IVA-inclusive, extracts ivaItem.
     * - 03 (CCF): unit prices are net, adds 13% IVA and optional 1% retention.
     * - 14 (FSE): no IVA, applies 10% income tax retention (reteRenta).
     *
     * @param  array<int, array{descripcion: string, cantidad: float|int, precio_unitario: float}>  $items
     * @return array{lines: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function calculate(string $tipoDte, array $items, bool $applyIvaRetention = false): array
    {
        if (empty($items)) {
            throw new InvalidArgumentException('At least one line item is required.');
        }

        $lines = [];
        $totalGravada = 0.0;
        $totalIvaExtracted = 0.0;
        $totalCompraFse = 0.0;

        foreach (array_values($items) as $index => $item) {
            $qty = (float) $item['cantidad'];
            $unitPrice = (float) $item['precio_unitario'];
            if ($qty <= 0 || $unitPrice < 0) {
                throw new InvalidArgumentException('Quantity must be > 0 and unit price >= 0.');
            }

            $lineSubtotal = round($qty * $unitPrice, 2);

            if ($tipoDte === '01') {
                // IVA included in consumer price
                $ivaItem = round($lineSubtotal - ($lineSubtotal / (1 + self::IVA_RATE)), 2);
                $totalGravada += $lineSubtotal;
                $totalIvaExtracted += $ivaItem;

                $lines[] = [
                    'numItem' => $index + 1,
                    'tipoItem' => 1,
                    'cantidad' => $qty,
                    'codigo' => sprintf('ITEM-%03d', $index + 1),
                    'uniMedida' => 59,
                    'descripcion' => (string) $item['descripcion'],
                    'precioUni' => round($unitPrice, 2),
                    'montoDescu' => 0.0,
                    'ventaNoSuj' => 0.0,
                    'ventaExenta' => 0.0,
                    'ventaGravada' => $lineSubtotal,
                    'ivaItem' => $ivaItem,
                ];
            } elseif ($tipoDte === '03') {
                $totalGravada += $lineSubtotal;
                $lines[] = [
                    'numItem' => $index + 1,
                    'tipoItem' => 1,
                    'cantidad' => $qty,
                    'codigo' => sprintf('ITEM-%03d', $index + 1),
                    'uniMedida' => 59,
                    'descripcion' => (string) $item['descripcion'],
                    'precioUni' => round($unitPrice, 2),
                    'montoDescu' => 0.0,
                    'ventaNoSuj' => 0.0,
                    'ventaExenta' => 0.0,
                    'ventaGravada' => $lineSubtotal,
                    'tributos' => ['20'],
                ];
            } elseif ($tipoDte === '14') {
                $totalCompraFse += $lineSubtotal;
                $lines[] = [
                    'numItem' => $index + 1,
                    'tipoItem' => 2,
                    'cantidad' => $qty,
                    'codigo' => sprintf('SERV-%03d', $index + 1),
                    'uniMedida' => 59,
                    'descripcion' => (string) $item['descripcion'],
                    'precioUni' => round($unitPrice, 2),
                    'montoDescu' => 0.0,
                    'compra' => $lineSubtotal,
                ];
            } else {
                throw new InvalidArgumentException("Unsupported DTE type: {$tipoDte}");
            }
        }

        if ($tipoDte === '01') {
            $totalGravada = round($totalGravada, 2);
            return [
                'lines' => $lines,
                'summary' => [
                    'totalNoSuj' => 0.0,
                    'totalExenta' => 0.0,
                    'totalGravada' => $totalGravada,
                    'subTotalVentas' => $totalGravada,
                    'totalDescu' => 0.0,
                    'subTotal' => $totalGravada,
                    'ivaRete1' => 0.0,
                    'reteRenta' => 0.0,
                    'montoTotalOperacion' => $totalGravada,
                    'totalPagar' => $totalGravada,
                    'totalIva' => round($totalIvaExtracted, 2),
                    'condicionOperacion' => 1,
                ],
            ];
        }

        if ($tipoDte === '03') {
            $totalGravada = round($totalGravada, 2);
            $iva13 = round($totalGravada * self::IVA_RATE, 2);
            $ivaRete1 = $applyIvaRetention && $totalGravada >= 100.0 ? round($totalGravada * self::IVA_RETENTION_RATE, 2) : 0.0;
            $montoTotalOperacion = round($totalGravada + $iva13, 2);
            $totalPagar = round($montoTotalOperacion - $ivaRete1, 2);

            return [
                'lines' => $lines,
                'summary' => [
                    'totalNoSuj' => 0.0,
                    'totalExenta' => 0.0,
                    'totalGravada' => $totalGravada,
                    'subTotalVentas' => $totalGravada,
                    'totalDescu' => 0.0,
                    'tributos' => [
                        [
                            'codigo' => '20',
                            'descripcion' => 'Impuesto al Valor Agregado 13%',
                            'valor' => $iva13,
                        ],
                    ],
                    'subTotal' => $totalGravada,
                    'ivaPerci1' => 0.0,
                    'ivaRete1' => $ivaRete1,
                    'reteRenta' => 0.0,
                    'montoTotalOperacion' => $montoTotalOperacion,
                    'totalPagar' => $totalPagar,
                    'condicionOperacion' => 1,
                ],
            ];
        }

        // FSE 14
        $totalCompraFse = round($totalCompraFse, 2);
        $reteRenta = round($totalCompraFse * self::FSE_RENTA_RATE, 2);
        $totalPagar = round($totalCompraFse - $reteRenta, 2);

        return [
            'lines' => $lines,
            'summary' => [
                'totalCompra' => $totalCompraFse,
                'descu' => 0.0,
                'totalDescu' => 0.0,
                'subTotal' => $totalCompraFse,
                'ivaRete1' => 0.0,
                'reteRenta' => $reteRenta,
                'totalPagar' => $totalPagar,
                'condicionOperacion' => 1,
            ],
        ];
    }
}
