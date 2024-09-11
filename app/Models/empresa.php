<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;



class empresa extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

   
    protected $table = 'empresas'; // Nombre de la tabla de maestros si es personalizada
 
    protected $fillable = [
       'email',
       'password',
       'codigo_confirmacion',
    ];
 
    protected $hidden = [
       'password',
       'confirmation_token'
    ];
 
    protected $casts = [];
 
 
 
    public function getJWTIdentifier()
    {
       return $this->getKey();
    }
 
    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
       return [];
    }
    public function sucursale()
    {
        return $this->belongsTo(Sucursale::class);
    }
}
