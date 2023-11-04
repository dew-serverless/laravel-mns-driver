<?php

namespace Dew\MnsDriver;

use AliyunMNS\Client;
use AliyunMNS\Queue;
use AliyunMNS\Responses\ReceiveMessageResponse;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;

class MnsJob extends Job implements JobContract
{
    /**
     * The MNS client instance.
     */
    protected Client $mns;

    /**
     * The MNS job instance.
     */
    protected ReceiveMessageResponse $job;

    /**
     * Create a new job instance.
     */
    public function __construct(
        Container $container,
        Client $mns,
        ReceiveMessageResponse $job,
        string $connectionName,
        string $queue
    ) {
        $this->container = $container;
        $this->mns = $mns;
        $this->job = $job;
        $this->connectionName = $connectionName;
        $this->queue = $queue;
    }

    /**
     * Release the job back into the queue after (n) seconds.
     *
     * @param  int  $delay
     * @return void
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        $this->mns->getQueueRef($this->queue)
            ->changeMessageVisibility($this->job->getReceiptHandle(), $delay);
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        $this->mns->getQueueRef($this->queue)
            ->deleteMessage($this->job->getReceiptHandle());
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return (int) $this->job->getDequeueCount();
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->job->getMessageId();
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->job->getMessageBody();
    }

    /**
     * Get the underlying MNS client instance.
     */
    public function getMns(): Client
    {
        return $this->mns;
    }

    /**
     * Get the underlying raw MNS job.
     */
    public function getMnsJob(): ReceiveMessageResponse
    {
        return $this->job;
    }
}
