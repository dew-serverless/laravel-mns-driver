<?php

use Dew\Mns\Contracts\XmlEncoder;
use Dew\Mns\MnsClient;
use Dew\Mns\Versions\V20150606\Queue;
use Dew\Mns\Versions\V20150606\Results\ChangeMessageVisibilityResult;
use Dew\Mns\Versions\V20150606\Results\ReceiveMessageResult;
use Dew\Mns\Versions\V20150606\Results\Result;
use Dew\MnsDriver\MnsJob;
use Illuminate\Container\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

beforeEach(function () {
    $this->client = new MnsClient('http://1234567891011.mns.us-west-1.aliyuncs.com', 'key', 'secret');
    $this->mns = Mockery::mock(new Queue($this->client));
    $this->mockedContainer = Mockery::mock(Container::class);
    $this->queueName = 'default';

    $this->mockedJob = 'greeting';
    $this->mockedData = ['message' => 'Hello world!'];
    $this->mockedPayload = json_encode(['job' => $this->mockedJob, 'data' => $this->mockedData]);
    $this->mockedMessageId = '5F290C926D472878-2-14D9529****-200000001';
    $this->mockedReceiptHandle = '1-ODU4OTkzNDU5My0xNDM1MTk3NjAwLTItNg==';

    $this->mockedStream = Mockery::mock(StreamInterface::class);
    $this->mockedStream->allows()->__toString()->andReturns('<response></response>');
    $this->mockedResponse = Mockery::mock(ResponseInterface::class);
    $this->mockedResponse->allows()->getHeaderLine('content-type')->andReturns('text/xml');
    $this->mockedResponse->allows()->getBody()->andReturns($this->mockedStream);
    $this->mockedXml = Mockery::mock(XmlEncoder::class);
    $this->mockedXml->allows()->decode('<response></response>')->andReturns([
        'MessageId' => $this->mockedMessageId,
        'ReceiptHandle' => $this->mockedReceiptHandle,
        'MessageBody' => $this->mockedPayload,
        'MessageBodyMD5' => md5($this->mockedPayload),
    ]);
    $this->mockedReceiveMessageResult = new ReceiveMessageResult($this->mockedResponse, $this->mockedXml);
});

test('release releases the job onto mns', function () {
    $mockedResponse = Mockery::mock(ResponseInterface::class);
    $mockedResponse->expects()->getHeaderLine('content-type')->andReturns('');
    $this->mns->expects()->changeMessageVisibility($this->queueName, $this->mockedReceiptHandle, 60)->andReturns(new ChangeMessageVisibilityResult($mockedResponse, Mockery::mock(XmlEncoder::class)));
    $job = new MnsJob($this->mockedContainer, $this->mns, $this->mockedReceiveMessageResult, 'mns', $this->queueName);
    $job->release(60);
});

test('delete removes the job from mns', function () {
    $mockedResponse = Mockery::mock(ResponseInterface::class);
    $mockedResponse->expects()->getHeaderLine('content-type')->andReturns('');
    $this->mns->expects()->deleteMessage($this->queueName, $this->mockedReceiptHandle)->andReturns(new Result($mockedResponse, Mockery::mock(XmlEncoder::class)));
    $job = new MnsJob($this->mockedContainer, $this->mns, $this->mockedReceiveMessageResult, 'mns', $this->queueName);
    $job->delete();
});

test('fire calls the job handler', function () {
    $job = new MnsJob($this->mockedContainer, $this->mns, $this->mockedReceiveMessageResult, 'mns', $this->queueName);
    $job->getContainer()->expects()->make($this->mockedJob)->andReturns($handler = Mockery::mock(stdClass::class));
    $handler->expects()->fire($job, $this->mockedData);
    $job->fire();
});
