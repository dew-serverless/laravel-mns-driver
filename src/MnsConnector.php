<?php

namespace Dew\MnsDriver;

use Dew\Mns\MnsClient;
use Dew\Mns\Versions\V20150606\Queue;
use Illuminate\Queue\Connectors\ConnectorInterface;

class MnsConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        $mns = new MnsClient($config['endpoint'], $config['key'], $config['secret']);

        return new MnsQueue(new Queue($mns), $config['queue']);
    }
}
