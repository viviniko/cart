<?php

namespace Viviniko\Cart;

use Viviniko\Cart\Console\Commands\CartTableCommand;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class CartServiceProvider extends BaseServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish config files
        $this->publishes([
            __DIR__.'/../config/cart.php' => config_path('cart.php'),
        ]);

        // Register commands
        $this->commands('command.cart.table');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/cart.php', 'cart');

        $this->registerCartService();

        $this->registerCommands();
    }

    /**
     * Register the artisan commands.
     *
     * @return void
     */
    private function registerCommands()
    {
        $this->app->singleton('command.cart.table', function ($app) {
            return new CartTableCommand($app['files'], $app['composer']);
        });
    }

    /**
     * Register the cart service provider.
     *
     * @return void
     */
    protected function registerCartService()
    {
        $this->app->singleton(
            \Viviniko\Cart\Contracts\CartService::class,
            \Viviniko\Cart\Services\Cart\EloquentCart::class
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            \Viviniko\Cart\Contracts\CartService::class,
        ];
    }
}