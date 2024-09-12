<?php

return [

   'defaults' => [
      'guard' => 'api_cartero',
      'passwords' => 'carteros',
   ],

   'guards' => [
      'cartero' => [
         'driver' => 'session',
         'provider' => 'carteros',
      ],
      'sucursal' => [
         'driver' => 'session',
         'provider' => 'sucursales',
      ],
      'api_cartero' => [
         'driver' => 'jwt',
         'provider' => 'carteros',
      ],
      'api_sucursal' => [
         'driver' => 'jwt',
         'provider' => 'sucursales',
      ],
      'admin' => [
         'driver' => 'session',
         'provider' => 'administrador',
      ],
      'api_admin' => [
         'driver' => 'jwt',
         'provider' => 'administrador',
      ],
      'encargado' => [
         'driver' => 'session',
         'provider' => 'encargados',
      ],
      'api_encargado' => [
         'driver' => 'jwt',
         'provider' => 'encargados',
      ],
      'gestore' => [
         'driver' => 'session',
         'provider' => 'gestores',
      ],
      'api_gestore' => [
         'driver' => 'jwt',
         'provider' => 'gestores',
      ],
      'contratos' => [
         'driver' => 'session',
         'provider' => 'contratos',
      ],
      'api_contratos' => [
         'driver' => 'jwt',
         'provider' => 'contratos',
      ],
      'empresas' => [
         'driver' => 'session',
         'provider' => 'empresas',
      ],
      'api_empresas' => [
         'driver' => 'jwt',
         'provider' => 'empresas',
      ],
   ],

   'providers' => [
      'carteros' => [
         'driver' => 'eloquent',
         'model' => App\Models\Cartero::class,
      ],
      'sucursales' => [
         'driver' => 'eloquent',
         'model' => App\Models\Sucursale::class,
      ],
      'administrador' => [
         'driver' => 'eloquent',
         'model' => App\Models\User::class,
      ],
      'encargados' => [
         'driver' => 'eloquent',
         'model' => App\Models\Encargado::class,
      ],
      'gestores' => [
         'driver' => 'eloquent',
         'model' => App\Models\Gestore::class,
      ],
      'contratos' => [
         'driver' => 'eloquent',
         'model' => App\Models\Contratos::class,
      ],
      'empresas' => [
         'driver' => 'eloquent',
         'model' => App\Models\Empresa::class,
      ],
   ],

   'passwords' => [
      'carteros' => [
         'provider' => 'carteros',
         'table' => 'password_resets',
         'expire' => 60,
         'throttle' => 60,
      ],
      'sucursales' => [
         'provider' => 'sucursales',
         'table' => 'password_resets',
         'expire' => 60,
         'throttle' => 60,
      ],
      'administrador' => [
         'provider' => 'administrador',
         'table' => 'password_resets',
         'expire' => 60,
         'throttle' => 60,
      ],
      'encargados' => [
         'provider' => 'encargados',
         'table' => 'password_resets',
         'expire' => 60,
         'throttle' => 60,
      ],
      'gestores' => [
         'provider' => 'gestores',
         'table' => 'password_resets',
         'expire' => 60,
         'throttle' => 60,
      ],
      'contratos' => [
         'provider' => 'contratos',
         'table' => 'password_resets',
         'expire' => 60,
         'throttle' => 60,
      ],
      'empresas' => [
         'provider' => 'empresas',
         'table' => 'password_resets',
         'expire' => 60,
         'throttle' => 60,
      ],
   ],

   'password_timeout' => 10800,

];
