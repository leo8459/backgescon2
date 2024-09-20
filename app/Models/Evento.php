<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    use HasFactory;

    protected $fillable = ['accion', 'descripcion', 'codigo', 'fecha_hora', 'user_type', 'user_id'];
}
