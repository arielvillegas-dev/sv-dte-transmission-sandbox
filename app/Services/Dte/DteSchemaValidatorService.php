<?php

namespace App\Services\Dte;

class DteSchemaValidatorService
{
    /**
     * Validates structural compliance against SVFE DTE transmission rules.
     *
     * @param  array<string, mixed>  $dte
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validate(array $dte): array
    {
        $errors = [];

        $id = $dte['identificacion'] ?? null;
        if (! is_array($id)) {
            return ['valid' => false, 'errors' => ['Missing identificacion object.']];
        }

        $tipoDte = (string) ($id['tipoDte'] ?? '');
        $expectedVersions = DteDocumentBuilderService::SCHEMA_VERSIONS;
        if (! isset($expectedVersions[$tipoDte])) {
            $errors[] = "Unsupported tipoDte: {$tipoDte}";
        } elseif ((int) ($id['version'] ?? 0) !== $expectedVersions[$tipoDte]) {
            $errors[] = "Invalid schema version for tipoDte {$tipoDte}; expected {$expectedVersions[$tipoDte]}.";
        }

        if (! preg_match('/^[A-F0-9]{8}-[A-F0-9]{4}-4[A-F0-9]{3}-[89AB][A-F0-9]{3}-[A-F0-9]{12}$/', (string) ($id['codigoGeneracion'] ?? ''))) {
            $errors[] = 'codigoGeneracion must be an uppercase UUID v4.';
        }

        if (! preg_match('/^DTE-(01|03|14)-[A-Z0-9]{8}-\d{15}$/', (string) ($id['numeroControl'] ?? ''))) {
            $errors[] = 'numeroControl does not match DTE-XX-XXXXXXXX-XXXXXXXXXXXXXXX pattern.';
        }

        if (! in_array((string) ($id['ambiente'] ?? ''), ['00', '01'], true)) {
            $errors[] = 'ambiente must be 00 (test) or 01 (production).';
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($id['fecEmi'] ?? ''))) {
            $errors[] = 'fecEmi must follow YYYY-MM-DD format.';
        }

        if (! preg_match('/^\d{2}:\d{2}:\d{2}$/', (string) ($id['horEmi'] ?? ''))) {
            $errors[] = 'horEmi must follow HH:MM:SS format.';
        }

        $lines = $dte['cuerpoDocumento'] ?? null;
        if (! is_array($lines) || count($lines) === 0) {
            $errors[] = 'cuerpoDocumento must contain at least one item.';
        }

        $resumen = $dte['resumen'] ?? null;
        if (! is_array($resumen) || ! isset($resumen['totalPagar'])) {
            $errors[] = 'resumen.totalPagar is required.';
        }

        return ['valid' => count($errors) === 0, 'errors' => $errors];
    }
}
