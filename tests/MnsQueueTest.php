<?php

use AliyunMNS\Client;
use AliyunMNS\Exception\MessageNotExistException;
use AliyunMNS\Model\QueueAttributes;
use AliyunMNS\Responses\ReceiveMessageResponse;
use AliyunMNS\Responses\SendMessageResponse;
use Dew\Mns\MnsJob;
use Dew\Mns\MnsQueue;
use Illuminate\Container\Container;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->mns = Mockery::mock(Client::class);
    $this->queueName = 'default';

    $this->mockedJob = 'greeting';
    $this->mockedData = ['message' => 'Hello world!'];
    $this->mockedPayload = json_encode(['job' => $this->mockedJob, 'data' => $this->mockedData]);
    $this->mockedDelay = 60;
    $this->mockedMessageId = '5F290C926D472878-2-14D9529****-200000001';
    $this->mockedMessageMd5 = md5($this->mockedPayload);
    $this->mockedReceiptHandle = '1-ODU4OTkzNDU5My0xNDM1MTk3NjAwLTItNg==';

    $this->mockedActiveMessages = 3;
    $this->mockedInactiveMessages = 4;
    $this->mockedDelayMessages = 5;
    $this->mockedQueueAttributes = new QueueAttributes(
        activeMessages: $this->mockedActiveMessages,
        inactiveMessages: $this->mockedInactiveMessages,
        delayMessages: $this->mockedDelayMessages
    );

    $this->mockedSendMessageResponse = tap(new SendMessageResponse)->parseResponse(201, <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<Message xmlns="http://mns.aliyuncs.com/doc/v1/">
    <MessageId>{$this->mockedMessageId}</MessageId>
    <MessageBodyMD5>{$this->mockedMessageMd5}</MessageBodyMD5>
    <ReceiptHandle>{$this->mockedReceiptHandle}</ReceiptHandle>
</Message>
EOF);

    $this->mockedReceiveMessageResponse = tap(new ReceiveMessageResponse(false))->parseResponse(200, <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<Message xmlns="http://mns.aliyuncs.com/doc/v1/">
    <MessageId>{$this->mockedMessageId}</MessageId>
    <ReceiptHandle>{$this->mockedReceiptHandle}</ReceiptHandle>
    <MessageBodyMD5>{$this->mockedMessageMd5}</MessageBodyMD5>
    <MessageBody>{$this->mockedPayload}</MessageBody>
</Message>
EOF);
});

test('queue size includes active, inactive and delayed messages', function () {
    $this->mns->expects('getQueueRef->getAttribute->getQueueAttributes')->once()->andReturn($this->mockedQueueAttributes);
    $queue = new MnsQueue($this->mns, $this->queueName);
    expect($queue->size())->toBeInt()->toBe($this->mockedActiveMessages + $this->mockedInactiveMessages + $this->mockedDelayMessages);
});

test('push job to mns', function () {
    $queue = $this->getMockBuilder(MnsQueue::class)->onlyMethods(['createPayload'])->setConstructorArgs([$this->mns, $this->queueName])->getMock();
    $queue->setContainer($container = Mockery::spy(Container::class));
    $queue->expects($this->once())->method('createPayload')->with($this->mockedJob, $this->queueName, $this->mockedData)->willReturn($this->mockedPayload);
    $this->mns->expects('getQueueRef->sendMessage')->once()->withArgs(fn ($request) => $request->getMessageBody() === $this->mockedPayload)->andReturn($this->mockedSendMessageResponse);
    $id = $queue->push($this->mockedJob, $this->mockedData, $this->queueName);
    expect($id)->toBe($this->mockedMessageId);
    $container->shouldHaveReceived('bound')->with('events')->once();
});

test('push delayed job to mns', function () {
    $queue = $this->getMockBuilder(MnsQueue::class)->onlyMethods(['createPayload'])->setConstructorArgs([$this->mns, $this->queueName])->getMock();
    $queue->setContainer($container = Mockery::spy(Container::class));
    $queue->expects($this->once())->method('createPayload')->with($this->mockedJob, $this->queueName, $this->mockedData)->willReturn($this->mockedPayload);
    $this->mns->expects('getQueueRef->sendMessage')->once()->withArgs(fn ($request) => $request->getDelaySeconds() === $this->mockedDelay && $request->getMessageBody() === $this->mockedPayload)->andReturn($this->mockedSendMessageResponse);
    $id = $queue->later($this->mockedDelay, $this->mockedJob, $this->mockedData, $this->queueName);
    expect($id)->toBe($this->mockedMessageId);
    $container->shouldHaveReceived('bound')->with('events')->once();
});

test('push delayed job with datetime to mns', function () {
    $now = Carbon::now();
    $queue = $this->getMockBuilder(MnsQueue::class)->onlyMethods(['createPayload'])->setConstructorArgs([$this->mns, $this->queueName])->getMock();
    $queue->setContainer($container = Mockery::spy(Container::class));
    $queue->expects($this->once())->method('createPayload')->with($this->mockedJob, $this->queueName, $this->mockedData)->willReturn($this->mockedPayload);
    $this->mns->expects('getQueueRef->sendMessage')->once()->withArgs(fn ($request) => $request->getDelaySeconds() === $this->mockedDelay && $request->getMessageBody() === $this->mockedPayload)->andReturn($this->mockedSendMessageResponse);
    $id = $queue->later($now->addSeconds($this->mockedDelay), $this->mockedJob, $this->mockedData, $this->queueName);
    expect($id)->toBe($this->mockedMessageId);
    $container->shouldHaveReceived('bound')->with('events')->once();
});

test('pop job off of mns', function () {
    $queue = new MnsQueue($this->mns, $this->queueName);
    $queue->setContainer(Mockery::mock(Container::class));
    $queue->setConnectionName('mns');
    $this->mns->expects('getQueueRef->receiveMessage')->once()->andReturn($this->mockedReceiveMessageResponse);
    $job = $queue->pop($this->queueName);
    expect($job)->toBeInstanceOf(MnsJob::class);
});

test('pop job off of empty mns', function () {
    $queue = new MnsQueue($this->mns, $this->queueName);
    $this->mns->expects('getQueueRef->receiveMessage')->once()->andThrow(MessageNotExistException::class, 404, '');
    $job = $queue->pop($this->queueName);
    expect($job)->toBe(null);
});
