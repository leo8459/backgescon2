<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Cliente extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nombre',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Get the authenticated client instance.
     *
     * @return \App\Models\Cliente
     */
    public function cliente()
    {
        return $this;
    }
    public function casillas()
{
    return $this->hasMany(Casilla::class);
}

}
