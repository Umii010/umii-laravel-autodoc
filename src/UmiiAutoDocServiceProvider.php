<?php
namespace Umii\AutoDoc;

use Illuminate\Support\ServiceProvider;
use Umii\AutoDoc\Commands\GenerateDocs;

class UmiiAutoDocServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/umii_autodoc.php', 'umii_autodoc');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/umii_autodoc.php' => config_path('umii_autodoc.php'),
            ], 'config');

            $this->commands([
                GenerateDocs::class,
            ]);
        }

        // Views (internal) - not required but available
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'umii_autodoc');
    }
}
