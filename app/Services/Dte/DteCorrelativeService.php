<?php

namespace App\Services\Dte;

use App\Models\DteCorrelative;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DteCorrelativeService
{
    public const SUPPORTED_TYPES = ['01', '03', '14'];

    /**
     * Generates an atomic control number in official SVFE format:
     * DTE-{tipoDte}-{estab4}{pos4}-{15-digit-sequence}
     */
    public function nextControlNumber(
        string $tipoDte,
        string $establishmentCode = 'M001',
        string $posCode = 'P001',
        ?int $fiscalYear = null
    ): string {
        if (! in_array($tipoDte, self::SUPPORTED_TYPES, true)) {
            throw new InvalidArgumentException("Unsupported DTE type: {$tipoDte}");
        }

        $year = $fiscalYear ?? (int) date('Y');
        $estab = strtoupper(str_pad(substr($establishmentCode, 0, 4), 4, '0', STR_PAD_LEFT));
        $pos = strtoupper(str_pad(substr($posCode, 0, 4), 4, '0', STR_PAD_LEFT));

        $seq = DB::transaction(function () use ($tipoDte, $estab, $pos, $year) {
            $row = DteCorrelative::query()
                ->where('establishment_code', $estab)
                ->where('pos_code', $pos)
                ->where('tipo_dte', $tipoDte)
                ->where('fiscal_year', $year)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $row = DteCorrelative::query()->create([
                    'establishment_code' => $estab,
                    'pos_code' => $pos,
                    'tipo_dte' => $tipoDte,
                    'fiscal_year' => $year,
                    'last_sequence' => 0,
                ]);
            }

            $row->last_sequence += 1;
            $row->save();

            return $row->last_sequence;
        });

        return sprintf('DTE-%s-%s%s-%015d', $tipoDte, $estab, $pos, $seq);
    }
}
