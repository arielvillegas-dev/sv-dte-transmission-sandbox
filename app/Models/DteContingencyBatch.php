<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DteContingencyBatch extends Model
{
    protected $fillable = [
        'codigo_generacion',
        'motivo_code',
        'motivo_desc',
        'f_inicio',
        'f_fin',
        'status',
        'sello_recibido',
        'event_payload_json',
    ];

    protected $casts = [
        'event_payload_json' => 'array',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(DteDocument::class, 'contingency_batch_id');
    }
}
