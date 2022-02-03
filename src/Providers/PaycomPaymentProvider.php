<?php

namespace Asadbek\Paycom\Providers;

use Illuminate\Support\ServiceProvider;

class PaycomPaymentProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'../config/config.php', 'paycom');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
//        $this->loadViewsFrom(__DIR__.'/views', 'todolist');
        $this->publishes([
//            __DIR__.'/views' => base_path('resources/views/wisdmlabs/todolist'),
        ]);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->make('Asadbek\Paycom\Http\Controller\PaycomController');
        if ($this->app->runningInConsole()) {

            $this->publishes([
                __DIR__.'/config/config.php' => config_path('paycom.php'),
            ], 'paycom');

        }
    }
}
