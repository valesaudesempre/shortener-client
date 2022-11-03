<?php

namespace ValeSaude\ShortenerClient;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ShortenerClientServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ShortenerClient::class, static function (Application $app) {
            return new ShortenerClient(
                $app['config']->get('services.valesaude.shortener_client.base_uri'),
                $app['config']->get('services.valesaude.shortener_client.username'),
                $app['config']->get('services.valesaude.shortener_client.password'),
                $app['cache.store']
            );
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'valesaude');

        $this->publishes(
            [__DIR__.'/../resources/lang' => $this->app->resourcePath('lang/vendor/valesaude')],
            'valesaude-shortener-client-translation'
        );
    }
}