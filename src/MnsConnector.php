<?php

namespace Dew\MnsDriver;

use Dew\Mns\MnsClient;
use Dew\Mns\Versions\V20150606\Queue;
use Illuminate\Queue\Connectors\ConnectorInterface;

/**
 * @phpstan-type Config array{
 *     endpoint: string,
 *     key: string,
 *     secret: string,
 *     queue: string,
 *     http?: array<string, mixed>
 * }
 */
class MnsConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param  array<mixed>  $config
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        /** @var Config $config */
        $mns = new MnsClient($config['endpoint'], $config['key'], $config['secret']);

        $mns->configure($this->withDefaultConfiguration($config['http'] ?? []));

        return new MnsQueue(new Queue($mns), $config['queue']);
    }

    /**
     * Build a configuration with default one.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function withDefaultConfiguration(array $config = []): array
    {
        // timeout: receiving messages could take up to 30 seconds.
        return array_merge([
            'timeout' => 60.0,
        ], $config);
    }
}
