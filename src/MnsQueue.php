<?php

namespace Dew\Mns;

use AliyunMNS\Client;
use AliyunMNS\Exception\MessageNotExistException;
use AliyunMNS\Requests\SendMessageRequest;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;

class MnsQueue extends Queue implements QueueContract
{
    /**
     * Create a new MNS queue instance.
     */
    public function __construct(
        protected Client $mns,
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
        $attributes = $this->mns->getQueueRef($this->getQueue($queue))
            ->getAttribute()
            ->getQueueAttributes();

        return $attributes->getActiveMessages()
            + $attributes->getInactiveMessages()
            + $attributes->getDelayMessages();
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
            function ($payload, $queue) {
                return $this->pushRaw($payload, $queue);
            }
        );
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  array  $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return $this->mns->getQueueRef($this->getQueue($queue))
            ->sendMessage(new SendMessageRequest($payload))
            ->getMessageId();
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
            function ($payload, $queue, $delay) {
                return $this->mns->getQueueRef($this->getQueue($queue))
                    ->sendMessage(new SendMessageRequest($payload, $this->secondsUntil($delay)))
                    ->getMessageId();
            }
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
        try {
            $response = $this->mns->getQueueRef($queue = $this->getQueue($queue))
                ->receiveMessage();
        } catch (MessageNotExistException $e) {
            return null;
        }

        return new MnsJob(
            $this->container, $this->mns, $response,
            $this->connectionName, $queue
        );
    }

    /**
     * Get the queue or return the default.
     */
    public function getQueue(?string $queue = null): string
    {
        return $queue ?: $this->default;
    }
}
