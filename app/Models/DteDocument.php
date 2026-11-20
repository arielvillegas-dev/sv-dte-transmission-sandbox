<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DteDocument extends Model
{
    protected $fillable = [
        'codigo_generacion',
        'numero_control',
        'tipo_dte',
        'schema_version',
        'ambiente',
        'tipo_transmision',
        'status',
        'total_pagar',
        'sello_recibido',
        'contingency_batch_id',
        'payload_json',
        'signed_jws',
        'mh_response_json',
    ];

    protected $casts = [
        'total_pagar' => 'float',
        'payload_json' => 'array',
        'mh_response_json' => 'array',
    ];

    public function contingencyBatch(): BelongsTo
    {
        return $this->belongsTo(DteContingencyBatch::class);
    }
}
