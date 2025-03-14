<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
class Codigo extends Model
{
    use HasFactory;
    protected $fillable = [
        'n_contrato',
        'codigo',
        'sucursale_id',
        'barcode' // 📌 Asegúrate de que esté aquí

    ];
    public function sucursale()
    {
        return $this->belongsTo(Sucursale::class, 'sucursale_id');
    }
}
