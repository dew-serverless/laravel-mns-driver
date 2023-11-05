<?php

namespace Dew\MnsDriver;

use Dew\Mns\MnsClient;
use Illuminate\Queue\Connectors\ConnectorInterface;

class MnsConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        return new MnsQueue(
            new MnsClient(
                $config['endpoint'],
                $config['key'],
                $config['secret']
            ),
            $config['queue']
        );
    }
}
