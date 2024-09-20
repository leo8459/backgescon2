<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    use HasFactory;

    protected $fillable = ['accion', 'descripcion', 'codigo', 'fecha_hora', 'sucursale_id', 'encargado_id', 'cartero_id'];
     // Relación con Sucursal
     public function sucursale()
     {
         return $this->belongsTo(Sucursale::class, 'sucursale_id');
     }
 
     // Relación con Encargado
     public function encargado()
     {
         return $this->belongsTo(Encargado::class, 'encargado_id');
     }
 
     // Relación con Cartero
     public function cartero()
     {
         return $this->belongsTo(Cartero::class, 'cartero_id');
     }
}
