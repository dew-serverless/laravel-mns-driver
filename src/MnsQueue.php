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
        $attributes = $this->mns->getQueueAttributes($this->getQueue($queue));

        return $attributes->activeMessages()
            + $attributes->inactiveMessages()
            + $attributes->delayMessages();
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
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue ?: $this->default, $data),
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
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return $this->mns->sendMessage($this->getQueue($queue), array_merge([
            'MessageBody' => $payload,
        ], $options))->messageId();
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
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue ?: $this->default, $data),
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
     * @param  array  $jobs
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return void
     */
    public function bulk($jobs, $data = '', $queue = null)
    {
        foreach ((array) $jobs as $job) {
            if (isset($job->delay)) {
                $this->later($job->delay, $job, $data, $queue);
            } else {
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

        if ($result->failed()) {
            return null;
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
        return $queue ?: $this->default;
    }
}
