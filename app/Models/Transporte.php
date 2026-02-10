<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transporte extends Model
{
    use HasFactory;

    protected $table = 'transporte';

    protected $fillable = [
        'transportadora',
        'provincia',
        'cartero_id',
        'n_recibo',
        'n_factura',
        'precio_total',
        'peso_total',
        'guias',
    ];

    protected $casts = [
        'guias' => 'array',
    ];

    public function cartero()
    {
        return $this->belongsTo(Cartero::class, 'cartero_id');
    }

    public function solicitudes()
    {
        return $this->hasMany(Solicitude::class, 'transporte_id');
    }
}
