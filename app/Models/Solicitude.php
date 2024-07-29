<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Solicitude extends Model
{
    use HasFactory;
  
    public function sucursale()
    {
        return $this->belongsTo(Sucursale::class);
    }
    public function carteroRecogida()
    {
        return $this->belongsTo(Cartero::class, 'cartero_recogida_id');
    }

    public function carteroEntrega()
    {
        return $this->belongsTo(Cartero::class, 'cartero_entrega_id');
    }
}
