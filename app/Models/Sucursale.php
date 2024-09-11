<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;



class Sucursale extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;
    
    public function empresa()
    {
        return $this->belongsTo(empresa::class);
    }

    public function solicitudes()
    {
        return $this->hasMany(Solicitude::class);
    }
    public function tarifas()
    {
        return $this->hasMany(Tarifa::class);
    }



    protected $table = 'sucursales'; // Nombre de la tabla de maestros si es personalizada

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

  
    /**
     * Get the identifier that will be stored in the JWT subject claim.
     *
     * @return mixed
     */

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
}
