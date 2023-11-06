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
     * Create a new job instance.
     *
     * @param  \Dew\Mns\Versions\V20150606\Queue  $mns
     */
    public function __construct(
        Container $container,
        protected $mns,
        protected ReceiveMessageResult $job,
        string $connectionName,
        string $queue
    ) {
        $this->container = $container;
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

        $result = $this->mns->changeMessageVisibility(
            $this->queue, (string) $this->job->receiptHandle(), $delay
        );

        if ($result->failed()) {
            throw new MnsQueueException(sprintf('Release the job with error [%s] %s',
                $result->errorCode(), $result->errorMessage()
            ));
        }
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        $result = $this->mns->deleteMessage($this->queue, (string) $this->job->receiptHandle());

        if ($result->failed()) {
            throw new MnsQueueException(sprintf('Delete the job with error [%s] %s',
                $result->errorCode(), $result->errorMessage()
            ));
        }
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
        return (string) $this->job->messageId();
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return (string) $this->job->messageBody();
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
