<?php

namespace App\Services\Dte;

use Illuminate\Support\Str;

class DteDocumentBuilderService
{
    public const SCHEMA_VERSIONS = [
        '01' => 2,
        '03' => 4,
        '14' => 2,
    ];

    public function __construct(
        private readonly DteCorrelativeService $correlatives,
        private readonly DteTaxCalculatorService $calculator,
    ) {}

    /**
     * Builds a synthetic SVFE DTE JSON payload compliant with public MH structure.
     *
     * @param  array{
     *   tipo_dte: string,
     *   receptor_nombre: string,
     *   receptor_doc: string,
     *   receptor_correo: string,
     *   items: array<int, array{descripcion: string, cantidad: float|int, precio_unitario: float}>,
     *   contingency_mode?: bool,
     *   motivo_contingencia?: string|null,
     *   apply_iva_retention?: bool
     * }  $input
     * @return array<string, mixed>
     */
    public function build(array $input): array
    {
        $tipoDte = $input['tipo_dte'];
        $isContingency = (bool) ($input['contingency_mode'] ?? false);
        $version = self::SCHEMA_VERSIONS[$tipoDte];
        $codigoGeneracion = strtoupper((string) Str::uuid());
        $numeroControl = $this->correlatives->nextControlNumber($tipoDte);
        $calc = $this->calculator->calculate($tipoDte, $input['items'], (bool) ($input['apply_iva_retention'] ?? false));

        $identificacion = [
            'version' => $version,
            'ambiente' => '00',
            'tipoDte' => $tipoDte,
            'numeroControl' => $numeroControl,
            'codigoGeneracion' => $codigoGeneracion,
            'tipoModelo' => $isContingency ? 2 : 1,
            'tipoOperacion' => $isContingency ? 2 : 1,
            'tipoContingencia' => $isContingency ? 1 : null,
            'motivoContin' => $isContingency ? ($input['motivo_contingencia'] ?? 'No disponibilidad temporal del servicio MH (simulado)') : null,
            'fecEmi' => date('Y-m-d'),
            'horEmi' => date('H:i:s'),
            'tipoMoneda' => 'USD',
        ];

        // Synthetic demo issuer only (never real taxpayer data)
        $emisor = [
            'nit' => '06140101901010',
            'nrc' => '1234567',
            'nombre' => 'EMPRESA DEMO SANDBOX S.A. DE C.V.',
            'codActividad' => '62010',
            'descActividad' => 'Actividades de programacion informatica',
            'nombreComercial' => 'DEMO DTE SANDBOX',
            'tipoEstablecimiento' => '01',
            'direccion' => [
                'departamento' => '06',
                'municipio' => '14',
                'complemento' => 'Colonia Demo, Calle Ficticia #100, San Salvador',
            ],
            'telefono' => '22000000',
            'correo' => 'sandbox-emisor@example.test',
        ];

        $receptor = [
            'tipoDocumento' => $tipoDte === '03' ? null : '13',
            'numDocumento' => $tipoDte === '03' ? null : $input['receptor_doc'],
            'nit' => $tipoDte === '03' ? $input['receptor_doc'] : null,
            'nrc' => $tipoDte === '03' ? '7654321' : null,
            'nombre' => $input['receptor_nombre'],
            'codActividad' => $tipoDte === '03' ? '47110' : null,
            'descActividad' => $tipoDte === '03' ? 'Comercio al por menor' : null,
            'direccion' => [
                'departamento' => '06',
                'municipio' => '14',
                'complemento' => 'Direccion sintetica de prueba #200',
            ],
            'telefono' => '22001111',
            'correo' => $input['receptor_correo'],
        ];

        if ($tipoDte === '14') {
            return [
                'identificacion' => $identificacion,
                'emisor' => $emisor,
                'sujetoExcluido' => [
                    'tipoDocumento' => '13',
                    'numDocumento' => $input['receptor_doc'],
                    'nombre' => $input['receptor_nombre'],
                    'codActividad' => '62020',
                    'descActividad' => 'Consultoria informatica',
                    'direccion' => $receptor['direccion'],
                    'telefono' => '22001111',
                    'correo' => $input['receptor_correo'],
                ],
                'cuerpoDocumento' => $calc['lines'],
                'resumen' => $calc['summary'],
                'apendice' => null,
            ];
        }

        return [
            'identificacion' => $identificacion,
            'documentoRelacionado' => null,
            'emisor' => $emisor,
            'receptor' => $receptor,
            'otrosDocumentos' => null,
            'ventaTercero' => null,
            'cuerpoDocumento' => $calc['lines'],
            'resumen' => $calc['summary'],
            'extension' => null,
            'apendice' => null,
        ];
    }
}
