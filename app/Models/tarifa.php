<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tarifa extends Model
{
    use HasFactory;

    protected $table = 'tarifas'; // Aseguramos que está apuntando a la tabla correcta

    public function sucursale()
    {
        return $this->belongsTo(Sucursale::class);
    }
}
