<?php

namespace phuongna\rabbitmq;

use Illuminate\Support\Facades\Auth;
use \Illuminate\Support\ServiceProvider as OriginServiceProvider;
use phuongna\rabbitmq\Services\SSOAuthGuard;

class ServiceProvider extends OriginServiceProvider
{
    const CONFIG_PATH = __DIR__ . '/../config/rabbitmq.php';

    public function boot()
    {
        $this->publishes([
            self::CONFIG_PATH => config_path('rabbitmq.php'),
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            self::CONFIG_PATH,
            'rabbitmq'
        );

        $this->app->bind('rabbitmq', function () {
            return new rabbitmq();
        });
    }


}
