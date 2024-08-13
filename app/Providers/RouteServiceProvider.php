<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
   /**
    * The path to the "home" route for your application.
    *
    * This is used by Laravel authentication to redirect users after login.
    *
    * @var string
    */
   public const HOME = '/home';

   protected $controllers = "App\Http\Controllers";
   /**
    * The controller namespace for the application.
    *
    * When present, controller route declarations will automatically be prefixed with this namespace.
    *
    * @var string|null
    */
   // protected $namespace = 'App\\Http\\Controllers';

   /**
    * Define your route model bindings, pattern filters, etc.
    *
    * @return void
    */
   public function boot()
   {
      $this->configureRateLimiting();

      $this->routes(function () {
         Route::prefix('administrador')
            ->middleware('administrador')
            ->namespace($this->controllers)
            ->group(base_path('routes/administrador.php'));
         Route::prefix('api')
            ->middleware('api')
            ->namespace($this->namespace)
            ->group(base_path('routes/api.php'));
         Route::middleware('web')
            ->namespace($this->controllers)
            ->group(base_path('routes/web.php'));
         Route::prefix('carteros')
            ->middleware('carteros')
            ->namespace($this->controllers)
            ->group(base_path('routes/carteros.php'));
         Route::prefix('sucursales')
            ->middleware('sucursales')
            ->namespace($this->controllers)
            ->group(base_path('routes/sucursales.php'));
         Route::prefix('encargados')
            ->middleware('encargados')
            ->namespace($this->controllers)
            ->group(base_path('routes/encargados.php'));
            Route::prefix('gestores')
            ->middleware('gestores')
            ->namespace($this->controllers)
            ->group(base_path('routes/gestores.php'));
            Route::prefix('contratos')
            ->middleware('contratos')
            ->namespace($this->controllers)
            ->group(base_path('routes/contratos.php'));
      });
   }

   /**
    * Configure the rate limiters for the application.
    *
    * @return void
    */
   protected function configureRateLimiting()
   {
      RateLimiter::for('api', function (Request $request) {
         return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
      });
   }
}
