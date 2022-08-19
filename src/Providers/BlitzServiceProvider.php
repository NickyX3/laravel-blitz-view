<?php

namespace NickyX3\Blitz\Providers;

use NickyX3\Blitz\BlitzView;
use Illuminate\Support\ServiceProvider;
use NickyX3\Blitz\Console\Commands\BlitzClearCache;

class BlitzServiceProvider extends ServiceProvider
{
    public function register():void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/blitz.php', 'blitz'
        );
        $this->app->bind('blitz',function(){
            return new BlitzView();
        });
    }

    public function boot():void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BlitzClearCache::class,
            ]);
        }
        $this->publishes([
            __DIR__.'/../config/blitz.php' => config_path('blitz.php'),
        ]);
        $this->publishes([
            __DIR__.'/../resources/blitz_view/example' => resource_path('blitz_view/example'),
        ]);
        $this->publishes([
            __DIR__.'/../public/exception.css' => public_path('css/nickyx3/blitz/exception.css'),
        ]);
    }
}
