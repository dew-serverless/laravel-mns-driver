<?php

namespace Dew\MnsDriver;

use Dew\Acs\MnsOpen\Models\ReceiveMessage;
use Dew\Acs\MnsOpen\QueueClient;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;

class MnsJob extends Job implements JobContract
{
    /**
     * Create a new job instance.
     *
     * @param  \Dew\Acs\MnsOpen\QueueClient  $mns
     */
    public function __construct(
        Container $container,
        protected $mns,
        protected ReceiveMessage $job,
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

        $this->mns->changeMessageVisibility([
            'QueueName' => $this->queue,
            'ReceiptHandle' => $this->job->receiptHandle,
            'VisibilityTimeout' => $delay,
        ]);
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        $this->mns->deleteMessage([
            'QueueName' => $this->queue,
            'ReceiptHandle' => $this->job->receiptHandle,
        ]);
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return $this->job->dequeueCount;
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->job->messageId;
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->job->messageBody;
    }

    /**
     * Get the underlying MNS queue instance.
     */
    public function getMns(): QueueClient
    {
        return $this->mns;
    }

    /**
     * Get the underlying raw MNS job.
     */
    public function getMnsJob(): ReceiveMessage
    {
        return $this->job;
    }
}
