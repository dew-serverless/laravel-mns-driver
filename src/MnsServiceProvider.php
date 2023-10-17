<?php

namespace Dew\Mns;

use Illuminate\Support\ServiceProvider;

class MnsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerConnector();
    }

    /**
     * Register Alibaba Cloud MNS queue connctor.
     */
    protected function registerConnector(): void
    {
        $this->app->make('queue')->addConnector('mns', function () {
            return new MnsConnector;
        });
    }
}
