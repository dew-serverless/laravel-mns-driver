<?php

use Dew\Mns\Contracts\XmlEncoder;
use Dew\Mns\MnsClient;
use Dew\Mns\Versions\V20150606\Queue;
use Dew\Mns\Versions\V20150606\Results\GetQueueAttributesResult;
use Dew\Mns\Versions\V20150606\Results\ReceiveMessageResult;
use Dew\Mns\Versions\V20150606\Results\SendMessageResult;
use Dew\MnsDriver\MnsJob;
use Dew\MnsDriver\MnsQueue;
use Illuminate\Container\Container;
use Illuminate\Support\Carbon;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

beforeEach(function () {
    $this->client = new MnsClient('http://1234567891011.mns.us-west-1.aliyuncs.com', 'key', 'secret');
    $this->mns = Mockery::mock(new Queue($this->client));
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

    $this->mockedStream = Mockery::mock(StreamInterface::class);
    $this->mockedStream->allows()->__toString()->andReturns('<response></response>');
    $this->mockedResponse = Mockery::mock(ResponseInterface::class);
    $this->mockedResponse->allows()->getheaderLine('content-type')->andReturns('text/xml');
    $this->mockedResponse->allows()->getBody()->andReturns($this->mockedStream);

    $this->mockedGetQueueAttributesResult = new GetQueueAttributesResult($this->mockedResponse, tap(Mockery::mock(XmlEncoder::class), function ($mock) {
        $mock->expects()->decode('<response></response>')->andReturns([
            'ActiveMessages' => $this->mockedActiveMessages,
            'InactiveMessages' => $this->mockedInactiveMessages,
            'DelayMessages' => $this->mockedDelayMessages,
        ]);
    }));

    $this->mockedSendMessageResult = new SendMessageResult($this->mockedResponse, tap(Mockery::mock(XmlEncoder::class), function ($mock) {
        $mock->expects()->decode('<response></response>')->andReturns([
            'MessageId' => $this->mockedMessageId,
            'MessageBodyMD5' => md5($this->mockedPayload),
            'ReceiptHandle' => $this->mockedReceiptHandle,
        ]);
    }));

    $this->mockedReceiveMessageResult = new ReceiveMessageResult($this->mockedResponse, tap(Mockery::mock(XmlEncoder::class), function ($mock) {
        $mock->expects()->decode('<response></response>')->andReturns([
            'MessageId' => $this->mockedMessageId,
            'MessageBody' => $this->mockedPayload,
            'MessageBodyMD5' => md5($this->mockedPayload),
            'ReceiptHandle' => $this->mockedReceiptHandle,
        ]);
    }));

    $this->mockedMessageNotExistsResult = new ReceiveMessageResult($this->mockedResponse, tap(Mockery::mock(XmlEncoder::class), function ($mock) {
        $mock->expects()->decode('<response></response>')->andReturns([
            'Code' => 'MessageNotExist',
            'Message' => 'Message not exist.',
        ]);
    }));
});

test('queue size includes active, inactive and delayed messages', function () {
    $this->mockedResponse->expects()->getStatusCode()->andReturns(200);
    $this->mns->expects()->getQueueAttributes($this->queueName)->andReturns($this->mockedGetQueueAttributesResult);
    $queue = new MnsQueue($this->mns, $this->queueName);
    expect($queue->size())->toBeInt()->toBe($this->mockedActiveMessages + $this->mockedInactiveMessages + $this->mockedDelayMessages);
});

test('push job to mns', function () {
    $queue = $this->getMockBuilder(MnsQueue::class)->onlyMethods(['createPayload'])->setConstructorArgs([$this->mns, $this->queueName])->getMock();
    $queue->setContainer($container = Mockery::spy(Container::class));
    $queue->expects($this->once())->method('createPayload')->with($this->mockedJob, $this->queueName, $this->mockedData)->willReturn($this->mockedPayload);
    $this->mockedResponse->expects()->getStatusCode()->andReturns(201);
    $this->mns->expects()->sendMessage($this->queueName, ['MessageBody' => $this->mockedPayload])->andReturns($this->mockedSendMessageResult);
    $id = $queue->push($this->mockedJob, $this->mockedData, $this->queueName);
    expect($id)->toBe($this->mockedMessageId);
    $container->shouldHaveReceived('bound')->with('events')->once();
});

test('push delayed job to mns', function () {
    $queue = $this->getMockBuilder(MnsQueue::class)->onlyMethods(['createPayload'])->setConstructorArgs([$this->mns, $this->queueName])->getMock();
    $queue->setContainer($container = Mockery::spy(Container::class));
    $queue->expects($this->once())->method('createPayload')->with($this->mockedJob, $this->queueName, $this->mockedData)->willReturn($this->mockedPayload);
    $this->mockedResponse->expects()->getStatusCode()->andReturns(201);
    $this->mns->expects()->sendMessage($this->queueName, ['MessageBody' => $this->mockedPayload, 'DelaySeconds' => $this->mockedDelay])->andReturns($this->mockedSendMessageResult);
    $id = $queue->later($this->mockedDelay, $this->mockedJob, $this->mockedData, $this->queueName);
    expect($id)->toBe($this->mockedMessageId);
    $container->shouldHaveReceived('bound')->with('events')->once();
});

test('push delayed job with datetime to mns', function () {
    $now = Carbon::now();
    $queue = $this->getMockBuilder(MnsQueue::class)->onlyMethods(['createPayload'])->setConstructorArgs([$this->mns, $this->queueName])->getMock();
    $queue->setContainer($container = Mockery::spy(Container::class));
    $queue->expects($this->once())->method('createPayload')->with($this->mockedJob, $this->queueName, $this->mockedData)->willReturn($this->mockedPayload);
    $this->mockedResponse->expects()->getStatusCode()->andReturns(201);
    $this->mns->expects()->sendMessage($this->queueName, ['MessageBody' => $this->mockedPayload, 'DelaySeconds' => $this->mockedDelay])->andReturns($this->mockedSendMessageResult);
    $id = $queue->later($now->addSeconds($this->mockedDelay), $this->mockedJob, $this->mockedData, $this->queueName);
    expect($id)->toBe($this->mockedMessageId);
    $container->shouldHaveReceived('bound')->with('events')->once();
});

test('pop job off of mns', function () {
    $queue = new MnsQueue($this->mns, $this->queueName);
    $queue->setContainer(Mockery::mock(Container::class));
    $queue->setConnectionName('mns');
    $this->mockedResponse->expects()->getStatusCode()->twice()->andReturns(200);
    $this->mns->expects()->receiveMessage($this->queueName)->andReturns($this->mockedReceiveMessageResult);
    $job = $queue->pop($this->queueName);
    expect($job)->toBeInstanceOf(MnsJob::class);
});

test('pop job off of empty mns', function () {
    $queue = new MnsQueue($this->mns, $this->queueName);
    $this->mockedResponse->expects()->getStatusCode()->andReturns(404);
    $this->mns->expects()->receiveMessage($this->queueName)->andReturns($this->mockedMessageNotExistsResult);
    $job = $queue->pop($this->queueName);
    expect($job)->toBe(null);
});
