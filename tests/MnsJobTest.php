<?php

use Dew\Acs\MnsOpen\Models\ReceiveMessage;
use Dew\Acs\MnsOpen\Results\ChangeMessageVisibilityResult;
use Dew\Acs\MnsOpen\Results\MnsResult;
use Dew\Acs\Result;
use Dew\MnsDriver\MnsJob;
use Illuminate\Container\Container;

beforeEach(function () {
    $this->mns = Mockery::mock(stdClass::class);
    $this->mockedContainer = Mockery::mock(Container::class);
    $this->queueName = 'default';

    $this->mockedJob = 'greeting';
    $this->mockedData = ['message' => 'Hello world!'];
    $this->mockedPayload = json_encode(['job' => $this->mockedJob, 'data' => $this->mockedData]);
    $this->mockedMessageId = '5F290C926D472878-2-14D9529****-200000001';
    $this->mockedReceiptHandle = '1-ODU4OTkzNDU5My0xNDM1MTk3NjAwLTItNg==';

    $this->mockedReceiveMessage = ReceiveMessage::make([
        'MessageId' => $this->mockedMessageId,
        'ReceiptHandle' => $this->mockedReceiptHandle,
        'MessageBody' => $this->mockedPayload,
        'MessageBodyMD5' => md5($this->mockedPayload),
        'EnqueueTime' => '1250700979248',
        'NextVisibleTime' => '1250700799348',
        'FirstDequeueTime' => '1250700779318',
        'DequeueCount' => '1',
        'Priority' => '8',
    ]);

    $this->mockedChangeMessageVisibilityResult = new ChangeMessageVisibilityResult(new Result([
        'ChangeVisibility' => [
            'ReceiptHandle' => $this->mockedReceiptHandle,
            'NextVisibleTime' => '1250700979298000',
        ],
    ]));

    $this->mockedDeleteMessageResult = new MnsResult(new Result);
});

test('release releases the job onto mns', function () {
    $this->mns->expects()->changeMessageVisibility(['QueueName' => $this->queueName, 'ReceiptHandle' => $this->mockedReceiptHandle, 'VisibilityTimeout' => 60])->andReturns($this->mockedChangeMessageVisibilityResult);
    $job = new MnsJob($this->mockedContainer, $this->mns, $this->mockedReceiveMessage, 'mns', $this->queueName);
    $job->release(60);
});

test('delete removes the job from mns', function () {
    $this->mns->expects()->deleteMessage(['QueueName' => $this->queueName, 'ReceiptHandle' => $this->mockedReceiptHandle])->andReturns($this->mockedDeleteMessageResult);
    $job = new MnsJob($this->mockedContainer, $this->mns, $this->mockedReceiveMessage, 'mns', $this->queueName);
    $job->delete();
});

test('fire calls the job handler', function () {
    $job = new MnsJob($this->mockedContainer, $this->mns, $this->mockedReceiveMessage, 'mns', $this->queueName);
    $job->getContainer()->expects()->make($this->mockedJob)->andReturns($handler = Mockery::mock(stdClass::class));
    $handler->expects()->fire($job, $this->mockedData);
    $job->fire();
});
