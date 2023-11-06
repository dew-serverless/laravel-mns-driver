<?php

namespace Dew\MnsDriver;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;

class MnsQueue extends Queue implements QueueContract
{
    /**
     * Create a new MNS queue instance.
     *
     * @param  \Dew\Mns\Versions\V20150606\Queue  $mns
     */
    public function __construct(
        protected $mns,
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
        $result = $this->mns->getQueueAttributes($this->getQueue($queue));

        if ($result->failed()) {
            throw new MnsQueueException(sprintf('Get the size of the queue with error [%s] %s',
                $result->errorCode(), $result->errorMessage()
            ));
        }

        return (int) $result->activeMessages()
            + (int) $result->inactiveMessages()
            + (int) $result->delayMessages();
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
            fn ($payload, $queue) => $this->pushRaw($payload, $queue)
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
        $result = $this->mns->sendMessage($this->getQueue($queue), array_merge([
            'MessageBody' => $payload,
        ], $options));

        if ($result->failed()) {
            throw new MnsQueueException(sprintf('Push job to queue with error [%s] %s',
                $result->errorCode(), $result->errorMessage()
            ));
        }

        return $result->messageId();
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
            fn ($payload, $queue, $delay) => $this->pushRaw($payload, $queue, [
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
            if (is_object($job) && isset($job->delay)) {
                $this->later($job->delay, $job, $data, $queue);
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
        $result = $this->mns->receiveMessage($queue = $this->getQueue($queue));

        if ($result->failed() && $result->errorCode() === 'MessageNotExist') {
            return null;
        }

        if ($result->failed()) {
            throw new MnsQueueException(sprintf('Pop job off of queue with error [%s] %s',
                $result->errorCode(), $result->errorMessage()
            ));
        }

        return new MnsJob(
            $this->container, $this->mns, $result,
            $this->connectionName, $queue
        );
    }

    /**
     * Get the queue or return the default.
     */
    public function getQueue(string $queue = null): string
    {
        if (is_string($queue)) {
            return $queue;
        }

        return $this->default;
    }
}
