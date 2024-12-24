<?php

namespace Dew\MnsDriver;

use Dew\Acs\MnsOpen\MnsOpenClient;
use Dew\Acs\MnsOpen\QueueClient;
use Illuminate\Queue\Connectors\ConnectorInterface;

/**
 * @phpstan-type TConfig array{
 *   key: string,
 *   secret: string,
 *   region: string,
 *   endpoint: string,
 *   queue: string,
 *   console_endpoint?: string
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
        /** @var TConfig $config */
        return new MnsQueue(
            $this->makeConsoleClient($config),
            $this->makeQueueClient($config),
            $config['queue']
        );
    }

    /**
     * Make a MNS console client.
     *
     * @param  TConfig  $config
     */
    private function makeConsoleClient(array $config): MnsOpenClient
    {
        return new MnsOpenClient(array_filter([
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
            'region' => $config['region'],
            'endpoint' => $config['console_endpoint'] ?? null,
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * Make a MNS queue client.
     *
     * @param  TConfig  $config
     */
    private function makeQueueClient(array $config): QueueClient
    {
        return new QueueClient([
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
            'endpoint' => $config['endpoint'],
        ]);
    }
}
