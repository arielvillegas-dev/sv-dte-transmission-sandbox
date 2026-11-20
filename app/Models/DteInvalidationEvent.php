<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DteInvalidationEvent extends Model
{
    protected $fillable = [
        'dte_document_id',
        'codigo_generacion',
        'tipo_anulacion',
        'motivo',
        'sello_recibido',
        'event_payload_json',
    ];

    protected $casts = [
        'event_payload_json' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(DteDocument::class, 'dte_document_id');
    }
}
