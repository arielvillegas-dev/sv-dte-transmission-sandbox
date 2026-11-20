<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DteCorrelative extends Model
{
    protected $fillable = ['establishment_code', 'pos_code', 'tipo_dte', 'fiscal_year', 'last_sequence'];
}
