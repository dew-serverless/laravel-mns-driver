<?php

namespace Dew\MnsDriver;

use Illuminate\Queue\QueueManager;
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
        $manager = $this->app->make('queue');

        if ($manager instanceof QueueManager) {
            $manager->addConnector('mns', fn () => new MnsConnector);
        }
    }
}
