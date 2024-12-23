<?php

namespace Dew\MnsDriver;

use Dew\Mns\Versions\V20150606\Models\Message;
use Dew\Mns\Versions\V20150606\Results\BatchReceiveMessageResult;
use Illuminate\Contracts\Queue\ClearableQueue;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;

class MnsQueue extends Queue implements ClearableQueue, QueueContract
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
        /** @var array<string, mixed> */
        $opts = ['MessageBody' => $payload, ...$options];

        $result = $this->mns->sendMessage($this->getQueue($queue), $opts);

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

            /** @var \Dew\Mns\Versions\V20150606\Models\Message[] */
            $messages = $result->messages();

            /** @var array<int, string> */
            $receipts = array_filter(
                array_map(fn (Message $message) => $message->receiptHandle(), $messages),
                fn (?string $handle) => is_string($handle)
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
        $result = $this->mns->batchReceiveMessage($queue, [
            'numOfMessages' => '16',  // max messages we could get at a time
            'waitseconds' => '30',    // max timeout
        ]);

        if ($result->failed() && $result->errorCode() === 'MessageNotExist') {
            return false;
        }

        if ($result->failed()) {
            throw new MnsQueueException(sprintf('Retrieve the messages with error [%s] %s',
                $result->errorCode(), $result->errorMessage()
            ));
        }

        return $result;
    }

    /**
     * Delete the messages from queue.
     *
     * @param  array<int, string>  $receipts
     */
    protected function deleteMessages(string $queue, array $receipts): int
    {
        $result = $this->mns->batchDeleteMessage($queue, $receipts);

        if ($result->failed()) {
            throw new MnsQueueException(sprintf('Delete the messages with error [%s] %s',
                $result->errorCode(), $result->errorMessage()
            ));
        }

        $errors = $result->errors();

        if (is_array($errors) && $errors !== []) {
            return count($receipts) - count($errors);
        }

        return count($receipts);
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
     * @return \Dew\Mns\Versions\V20150606\Queue
     */
    public function getMns()
    {
        return $this->mns;
    }
}
