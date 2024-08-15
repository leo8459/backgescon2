<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Direccione extends Model
{
    use HasFactory;
    public function sucursale()
    {
        return $this->belongsTo(Sucursale::class, 'sucursale_id');
    }
}
