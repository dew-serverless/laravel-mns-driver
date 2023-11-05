<?php

namespace Dew\MnsDriver;

use Illuminate\Support\ServiceProvider;

class MnsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerConnector();
    }

    /**
     * Register Alibaba Cloud MNS queue connctor.
     */
    protected function registerConnector(): void
    {
        $this->app->make('queue')->addConnector('mns', fn () => new MnsConnector);
    }
}
