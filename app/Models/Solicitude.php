<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Solicitude extends Model
{
    use HasFactory;
  
    public function sucursale()
    {
        return $this->belongsTo(Sucursale::class, 'sucursale_id');
    }
    public function carteroRecogida()
    {
        return $this->belongsTo(Cartero::class, 'cartero_recogida_id');
    }

    public function carteroEntrega()
    {
        return $this->belongsTo(Cartero::class, 'cartero_entrega_id');
    }
    public function tarifa()
    {
        return $this->belongsTo(Tarifa::class);
    }
    public function direccion()
    {
        return $this->belongsTo(Direccione::class, 'direccion_id');
    }
    public function encargado()
    {
        return $this->belongsTo(Encargado::class, 'encargado_id');
    }
    public function encargadoregional()
    {
        return $this->belongsTo(Encargado::class, 'encargado_regional_id');
    }

    public function transporte()
    {
        return $this->belongsTo(Transporte::class, 'transporte_id');
    }
}
