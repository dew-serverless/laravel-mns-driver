<?php

use AliyunMNS\Client;
use AliyunMNS\Responses\ReceiveMessageResponse;
use Dew\Mns\MnsJob;
use Illuminate\Container\Container;

beforeEach(function () {
    $this->mns = Mockery::mock(Client::class);
    $this->mockedContainer = Mockery::mock(Container::class);
    $this->queueName = 'default';

    $this->mockedJob = 'greeting';
    $this->mockedData = ['message' => 'Hello world!'];
    $this->mockedPayload = json_encode(['job' => $this->mockedJob, 'data' => $this->mockedData]);
    $this->mockedMessageId = '5F290C926D472878-2-14D9529****-200000001';
    $this->mockedMessageMd5 = md5($this->mockedPayload);
    $this->mockedReceiptHandle = '1-ODU4OTkzNDU5My0xNDM1MTk3NjAwLTItNg==';

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

test('release releases the job onto mns', function () {
    $job = new MnsJob($this->mockedContainer, $this->mns, $this->mockedReceiveMessageResponse, 'mns', $this->queueName);
    $this->mns->expects('getQueueRef->changeMessageVisibility')->with($this->mockedReceiptHandle, 60)->once();
    $job->release(60);
});

test('delete removes the job from mns', function () {
    $job = new MnsJob($this->mockedContainer, $this->mns, $this->mockedReceiveMessageResponse, 'mns', $this->queueName);
    $this->mns->expects('getQueueRef->deleteMessage')->with($this->mockedReceiptHandle)->once();
    $job->delete();
});

test('fire calls the job handler', function () {
    $job = new MnsJob($this->mockedContainer, $this->mns, $this->mockedReceiveMessageResponse, 'mns', $this->queueName);
    $job->getContainer()->expects()->make($this->mockedJob)->once()->andReturn($handler = Mockery::mock(stdClass::class));
    $handler->expects()->fire($job, $this->mockedData)->once();
    $job->fire();
});
