<?php

use Dew\Acs\MnsOpen\QueueException;
use Dew\Acs\MnsOpen\Results\BatchReceiveMessageResult;
use Dew\Acs\MnsOpen\Results\MnsResult;
use Dew\Acs\MnsOpen\Results\ReceiveMessageResult;
use Dew\Acs\MnsOpen\Results\SendMessageResult;
use Dew\Acs\Result;
use Dew\MnsDriver\MnsJob;
use Dew\MnsDriver\MnsQueue;
use Illuminate\Container\Container;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->console = Mockery::mock(stdClass::class);
    $this->client = Mockery::mock(stdClass::class);
    $this->queueName = 'default';

    $this->mockedJob = 'greeting';
    $this->mockedData = ['message' => 'Hello world!'];
    $this->mockedPayload = json_encode(['job' => $this->mockedJob, 'data' => $this->mockedData]);
    $this->mockedDelay = 60;
    $this->mockedMessageId = '5F290C926D472878-2-14D9529****-200000001';
    $this->mockedReceiptHandle = '1-ODU4OTkzNDU5My0xNDM1MTk3NjAwLTItNg==';

    $this->mockedActiveMessages = 3;
    $this->mockedInactiveMessages = 4;
    $this->mockedDelayMessages = 5;

    $this->mockedGetQueueAttributesResult = new Result([
        'Data' => [
            'ActiveMessages' => $this->mockedActiveMessages,
            'InactiveMessages' => $this->mockedInactiveMessages,
            'DelayMessages' => $this->mockedDelayMessages,
        ],
    ]);

    $this->mockedSendMessageResult = new SendMessageResult(new Result([
        'Message' => [
            'MessageId' => $this->mockedMessageId,
            'MessageBodyMD5' => hash('md5', $this->mockedPayload),
        ],
    ]));

    $this->mockedReceiveMessage = [
        'MessageId' => $this->mockedMessageId,
        'MessageBody' => $this->mockedPayload,
        'MessageBodyMD5' => hash('md5', $this->mockedPayload),
        'ReceiptHandle' => $this->mockedReceiptHandle,
        'EnqueueTime' => '1250700979248',
        'NextVisibleTime' => '1250700799348',
        'FirstDequeueTime' => '1250700779318',
        'DequeueCount' => '1',
        'Priority' => '8',
    ];

    $this->mockedReceiveMessageResult = new ReceiveMessageResult(new Result([
        'Message' => $this->mockedReceiveMessage,
    ]));

    $this->mockedMessageNotExistsException = QueueException::makeFromResult(new Result([
        'Error' => [
            'Code' => 'MessageNotExist',
            'Message' => 'Message not exist.',
        ],
    ]));

    $this->mockedBatchReceiveMessageResult = new BatchReceiveMessageResult(new Result([
        'Messages' => ['Message' => [$this->mockedReceiveMessage]],
    ]));

    $this->mockedBatchMessageNotFoundException = QueueException::makeFromResult(new Result([
        'Error' => [
            'Code' => 'MessageNotExist',
            'Message' => 'Message not exist.',
        ],
    ]));

    $this->mockedBatchDeleteMessageResult = new MnsResult(new Result);
});

test('queue size includes active, inactive and delayed messages', function () {
    $this->console->shouldReceive('getQueueAttributes')->with(['QueueName' => $this->queueName])->once()->andReturn($this->mockedGetQueueAttributesResult);
    $queue = new MnsQueue($this->console, $this->client, $this->queueName);
    expect($queue->size())->toBe($this->mockedActiveMessages + $this->mockedInactiveMessages + $this->mockedDelayMessages);
});

test('push job to mns', function () {
    $queue = $this->getMockBuilder(MnsQueue::class)->onlyMethods(['createPayload'])->setConstructorArgs([$this->console, $this->client, $this->queueName])->getMock();
    $queue->setContainer($container = Mockery::spy(Container::class));
    $queue->expects($this->once())->method('createPayload')->with($this->mockedJob, $this->queueName, $this->mockedData)->willReturn($this->mockedPayload);
    $this->client->shouldReceive('sendMessage')->with(['QueueName' => $this->queueName, 'Message' => ['MessageBody' => $this->mockedPayload]])->andReturn($this->mockedSendMessageResult);
    $id = $queue->push($this->mockedJob, $this->mockedData, $this->queueName);
    expect($id)->toBe($this->mockedMessageId);
    $container->shouldHaveReceived('bound')->with('events');
});

test('push delayed job to mns', function () {
    $queue = $this->getMockBuilder(MnsQueue::class)->onlyMethods(['createPayload'])->setConstructorArgs([$this->console, $this->client, $this->queueName])->getMock();
    $queue->setContainer($container = Mockery::spy(Container::class));
    $queue->expects($this->once())->method('createPayload')->with($this->mockedJob, $this->queueName, $this->mockedData)->willReturn($this->mockedPayload);
    $this->client->shouldReceive('sendMessage')->with(['QueueName' => $this->queueName, 'Message' => ['MessageBody' => $this->mockedPayload, 'DelaySeconds' => $this->mockedDelay]])->once()->andReturn($this->mockedSendMessageResult);
    $id = $queue->later($this->mockedDelay, $this->mockedJob, $this->mockedData, $this->queueName);
    expect($id)->toBe($this->mockedMessageId);
    $container->shouldHaveReceived('bound')->with('events');
});

test('push delayed job with datetime to mns', function () {
    $now = Carbon::now();
    $queue = $this->getMockBuilder(MnsQueue::class)->onlyMethods(['createPayload'])->setConstructorArgs([$this->console, $this->client, $this->queueName])->getMock();
    $queue->setContainer($container = Mockery::spy(Container::class));
    $queue->expects($this->once())->method('createPayload')->with($this->mockedJob, $this->queueName, $this->mockedData)->willReturn($this->mockedPayload);
    $this->client->shouldReceive('sendMessage')->with(['QueueName' => $this->queueName, 'Message' => ['MessageBody' => $this->mockedPayload, 'DelaySeconds' => $this->mockedDelay]])->once()->andReturn($this->mockedSendMessageResult);
    $id = $queue->later($now->addSeconds($this->mockedDelay), $this->mockedJob, $this->mockedData, $this->queueName);
    expect($id)->toBe($this->mockedMessageId);
    $container->shouldHaveReceived('bound')->with('events');
});

test('pop job off of mns', function () {
    $queue = new MnsQueue($this->console, $this->client, $this->queueName);
    $queue->setContainer(Mockery::mock(Container::class));
    $queue->setConnectionName('mns');
    $this->client->expects()->receiveMessage(['QueueName' => $this->queueName])->andReturns($this->mockedReceiveMessageResult);
    $job = $queue->pop($this->queueName);
    expect($job)->toBeInstanceOf(MnsJob::class);
});

test('pop job off of empty mns', function () {
    $queue = new MnsQueue($this->console, $this->client, $this->queueName);
    $this->client->expects()->receiveMessage(['QueueName' => $this->queueName])->andThrows($this->mockedMessageNotExistsException);
    $job = $queue->pop($this->queueName);
    expect($job)->toBeNull();
});

test('clear queue', function () {
    $queue = new MnsQueue($this->console, $this->client, $this->queueName);
    $this->client->allows()->batchReceiveMessage(['QueueName' => $this->queueName, 'numOfMessages' => '16', 'waitseconds' => '30'])->andReturnUsing(fn () => $this->mockedBatchReceiveMessageResult, fn () => throw $this->mockedBatchMessageNotFoundException);
    $this->client->expects()->batchDeleteMessage(['QueueName' => $this->queueName, 'ReceiptHandles' => ['ReceiptHandle' => [$this->mockedReceiptHandle]]])->andReturns($this->mockedBatchDeleteMessageResult);
    $deleted = $queue->clear($this->queueName);
    expect($deleted)->toBe(1);
});

test('clear empty queue', function () {
    $queue = new MnsQueue($this->console, $this->client, $this->queueName);
    $this->client->expects()->batchReceiveMessage(['QueueName' => $this->queueName, 'numOfMessages' => '16', 'waitseconds' => '30'])->andThrows($this->mockedBatchMessageNotFoundException);
    $deleted = $queue->clear($this->queueName);
    expect($deleted)->toBe(0);
});
