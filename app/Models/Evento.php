<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    use HasFactory;

    protected $fillable = ['accion', 'descripcion', 'codigo', 'fecha_hora', 'sucursale_id', 'encargado_id', 'cartero_id'];
}
