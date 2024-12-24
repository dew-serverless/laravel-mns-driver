<?php

namespace Dew\MnsDriver;

use Dew\Acs\MnsOpen\Models\ReceiveMessage;
use Dew\Acs\MnsOpen\QueueException;
use Dew\Acs\MnsOpen\Results\BatchReceiveMessageResult;
use Illuminate\Contracts\Queue\ClearableQueue;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;

class MnsQueue extends Queue implements ClearableQueue, QueueContract
{
    /**
     * Create a new MNS queue instance.
     *
     * @param  \Dew\Acs\MnsOpen\MnsOpenClient  $console
     * @param  \Dew\Acs\MnsOpen\QueueClient  $client
     */
    public function __construct(
        protected $console,
        protected $client,
        protected string $default
    ) {
        //
    }

    /**
     * Get the size of the queue.
     *
     * @param  string|null  $queue
     * @return int
     */
    public function size($queue = null)
    {
        $result = $this->console->getQueueAttributes([
            'QueueName' => $this->getQueue($queue),
        ]);

        $active = $result->get('Data.ActiveMessages');
        $active = is_int($active) ? $active : 0;

        $inactive = $result->get('Data.InactiveMessages');
        $inactive = is_int($inactive) ? $inactive : 0;

        $delayed = $result->get('Data.DelayMessages');
        $delayed = is_int($delayed) ? $delayed : 0;

        return $active + $inactive + $delayed;
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  string|object  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        $queue = $this->getQueue($queue);

        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue, $data),
            $queue,
            null,
            fn (string $payload, string $queue) => $this->pushRaw($payload, $queue)
        );
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  array<mixed>  $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $result = $this->client->sendMessage([
            'QueueName' => $this->getQueue($queue),
            'Message' => ['MessageBody' => $payload, ...$options],
        ]);

        return $result->message()->messageId;
    }

    /**
     * Push a new job onto the queue after (n) seconds.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  string|object  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        $queue = $this->getQueue($queue);

        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue, $data),
            $queue,
            $delay,
            fn (string $payload, string $queue, \DateTimeInterface|\DateInterval|int $delay) => $this->pushRaw($payload, $queue, [
                'DelaySeconds' => $this->secondsUntil($delay),
            ])
        );
    }

    /**
     * Push an array of jobs onto the queue.
     *
     * @param  array<mixed>  $jobs
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return void
     */
    public function bulk($jobs, $data = '', $queue = null)
    {
        foreach ((array) $jobs as $job) {
            if (is_object($job) && property_exists($job, 'delay')) {
                if ($job->delay instanceof \DateTimeInterface ||
                    $job->delay instanceof \DateInterval ||
                    is_int($job->delay)) {
                    $this->later($job->delay, $job, $data, $queue);
                }
            } else {
                /** @var object|string $job */
                $this->push($job, $data, $queue);
            }
        }
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string|null  $queue
     * @return \Illuminate\Contracts\Queue\Job|null
     */
    public function pop($queue = null)
    {
        try {
            $result = $this->client->receiveMessage([
                'QueueName' => $queue = $this->getQueue($queue),
            ]);
        } catch (QueueException $e) {
            if ($e->getCode() === 'MessageNotExist') {
                return null;
            }

            throw $e;
        }

        return new MnsJob(
            $this->container, $this->client, $result->message(),
            $this->connectionName, $queue
        );
    }

    /**
     * Delete all of the jobs from the queue.
     *
     * @param  string  $queue
     * @return int
     */
    public function clear($queue)
    {
        $deleted = 0;

        $queue = $this->getQueue($queue);

        while (true) {
            $result = $this->retrieveMessages($queue);

            if (! $result instanceof BatchReceiveMessageResult) {
                break;
            }

            $receipts = array_map(
                fn (ReceiveMessage $message) => $message->receiptHandle,
                $result->messages()
            );

            $deleted += $this->deleteMessages($queue, $receipts);
        }

        return $deleted;
    }

    /**
     * Retrieve the messages from queue.
     */
    protected function retrieveMessages(string $queue): BatchReceiveMessageResult|bool
    {
        try {
            return $this->client->batchReceiveMessage([
                'QueueName' => $queue,
                'numOfMessages' => 16,  // max messages we could get at a time
                'waitseconds' => 30,    // max timeout
            ]);
        } catch (QueueException $e) {
            if ($e->getCode() === 'MessageNotExist') {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Delete the messages from queue.
     *
     * @param  string[]  $handles
     */
    protected function deleteMessages(string $queue, array $handles): int
    {
        try {
            $this->client->batchDeleteMessage([
                'QueueName' => $queue,
                'ReceiptHandles' => [
                    'ReceiptHandle' => $handles,
                ],
            ]);

            return count($handles);
        } catch (QueueException $e) {
            $errors = $e->getResult()?->list('Errors.Error');

            if ($errors === null || $errors === []) {
                throw $e;
            }

            $failed = 0;

            foreach ($errors as $error) {
                if (! is_array($error)) {
                    throw $e;
                }

                $code = $error['ErrorCode'] ?? null;
                $message = $error['ErrorMessage'] ?? null;

                if (! is_string($code) || ! is_string($message)) {
                    throw $e;
                }

                if ($code !== 'MessageNotExist') {
                    throw new MnsQueueException($message, previous: $e);
                }

                $failed++;
            }

            return count($handles) - $failed;
        }
    }

    /**
     * Get the queue or return the default.
     */
    public function getQueue(?string $queue = null): string
    {
        if (is_string($queue)) {
            return $queue;
        }

        return $this->default;
    }

    /**
     * The underlying MNS queue.
     *
     * @return \Dew\Acs\MnsOpen\QueueClient
     */
    public function getMns()
    {
        return $this->client;
    }
}
