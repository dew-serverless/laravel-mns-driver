<?php

namespace Dew\MnsDriver;

use Dew\Mns\Versions\V20150606\Queue;
use Dew\Mns\Versions\V20150606\Results\ReceiveMessageResult;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;

class MnsJob extends Job implements JobContract
{
    /**
     * The MNS queue instance.
     *
     * @var \Dew\Mns\Versions\V20150606\Queue
     */
    protected $mns;

    /**
     * The MNS job instance.
     */
    protected ReceiveMessageResult $job;

    /**
     * Create a new job instance.
     *
     * @param  \Dew\Mns\Versions\V20150606\Queue  $mns
     */
    public function __construct(
        Container $container,
        $mns,
        ReceiveMessageResult $job,
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

        $this->mns->changeMessageVisibility(
            $this->queue, $this->job->receiptHandle(), $delay
        );
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        $this->mns->deleteMessage($this->queue, $this->job->receiptHandle());
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return (int) $this->job->dequeueCount();
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->job->messageId();
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->job->messageBody();
    }

    /**
     * Get the underlying MNS queue instance.
     */
    public function getMns(): Queue
    {
        return $this->mns;
    }

    /**
     * Get the underlying raw MNS job.
     */
    public function getMnsJob(): ReceiveMessageResult
    {
        return $this->job;
    }
}
