<?php

namespace App\Providers;

use App\Services\WalletDoctrineService;
use App\Services\WalletServiceInterface;
use App\Services\WalletSoapService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar el servicio de billetera virtual según la configuración
        $this->app->singleton(WalletServiceInterface::class, function ($app) {
            // Podemos cambiar esta configuración en .env
            // USE_DOCTRINE=true para usar Doctrine ORM
            // USE_DOCTRINE=false para usar Eloquent ORM
            $useDoctrineORM = Config::get('services.wallet.use_doctrine', false);
            
            return $useDoctrineORM 
                ? new WalletDoctrineService()
                : new WalletSoapService();
        });
        
        // También registrar los servicios individualmente si se necesitan específicamente
        $this->app->singleton(WalletSoapService::class, function ($app) {
            return new WalletSoapService();
        });
        
        $this->app->singleton(WalletDoctrineService::class, function ($app) {
            return new WalletDoctrineService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
