<?php

namespace Dew\Mns;

use AliyunMNS\Client;
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
            new Client(
                $config['endpoint'],
                $config['key'],
                $config['secret'],
                $config['token'] ?? null,
                $config['client'] ?? null
            ),
            $config['queue']
        );
    }
}
