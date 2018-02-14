<?php

namespace Bernard\Tests\Driver\PhpRedis;

use Bernard\Driver\PhpRedis\Driver;

class DriverTest extends \PHPUnit\Framework\TestCase
{
    public function setUp()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('"redis" extension is not loaded.');
        }

        $this->redis = $this->getMockBuilder('Redis')->setMethods([
            'lLen',
            'sMembers',
            'lRange',
            'blPop',
            'sRemove',
            'del',
            'sAdd',
            'sContains',
            'rPush',
            'sRem',
        ])->getMock();

        $this->connection = new Driver($this->redis);
    }

    public function testItImplementsDriverInterface()
    {
        $this->assertInstanceOf(\Bernard\Driver::class, $this->connection);
    }

    public function testItCountsNumberOfMessagesInQueue()
    {
        $this->redis->expects($this->once())->method('lLen')->with($this->equalTo('queue:send-newsletter'))
            ->will($this->returnValue(4));

        $this->assertEquals(4, $this->connection->countMessages('send-newsletter'));
    }

    public function testItGetsAllKeys()
    {
        $this->redis->expects($this->once())->method('sMembers')->with($this->equalTo('queues'))
            ->will($this->returnValue(['failed', 'queue1']));

        $this->assertEquals(['failed', 'queue1'], $this->connection->listQueues());
    }

    public function testItPeeksInAQueue()
    {
        $this->redis->expects($this->at(0))->method('lRange')
            ->with($this->equalTo('queue:my-queue'), $this->equalTo(10), $this->equalTo(19))
            ->will($this->returnValue(['message1']));

        $this->redis->expects($this->at(1))->method('lRange')
            ->with($this->equalTo('queue:send-newsletter'), $this->equalTo(0), $this->equalTo(19))
            ->will($this->returnValue(['message2']));

        $this->assertEquals(['message1'], $this->connection->peekQueue('my-queue', 10, 10));
        $this->assertEquals(['message2'], $this->connection->peekQueue('send-newsletter'));
    }

    public function testItRemovesAQueue()
    {
        $this->redis->expects($this->once())->method('del')->with($this->equalTo('queue:name'));
        $this->redis->expects($this->once())->method('srem')->with($this->equalTo('queues'), $this->equalTo('name'));

        $this->connection->removeQueue('name');
    }

    public function testItCreatesAQueue()
    {
        $this->redis->expects($this->once())->method('sAdd')->with($this->equalTo('queues'), $this->equalTo('send-newsletter'));

        $this->connection->createQueue('send-newsletter');
    }

    public function testItPushesMessages()
    {
        $this->redis->expects($this->once())->method('rPush')->with($this->equalTo('queue:send-newsletter'), $this->equalTo('This is a message'));

        $this->connection->pushMessage('send-newsletter', 'This is a message');
    }

    public function testItPopMessages()
    {
        $this->redis->expects($this->at(0))->method('blPop')->with($this->equalTo(['queue:send-newsletter']))
            ->will($this->returnValue(['my-queue', 'message1']));

        $this->redis->expects($this->at(1))->method('blPop')->with($this->equalTo(['queue:ask-forgiveness']), $this->equalTo(30))
            ->will($this->returnValue(['my-queue2', 'message2']));

        $this->assertEquals(['message1', null], $this->connection->popMessage('send-newsletter'));
        $this->assertEquals(['message2', null], $this->connection->popMessage('ask-forgiveness', 30));
    }
}
