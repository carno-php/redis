<?php
/**
 * Sub tests
 * User: moyo
 * Date: 2018/8/29
 * Time: 10:45 AM
 */

namespace Carno\Redis\Tests;

use Carno\Channel\Chan;
use Carno\Channel\Exception\ChannelClosingException;
use function Carno\Coroutine\async;
use Carno\Net\Address;
use Carno\Promise\Promise;
use Carno\Redis\Exception\CommandException;
use Carno\Redis\Redis;
use Carno\Redis\Types\Message;
use PHPUnit\Framework\TestCase;
use Closure;
use Throwable;

class SubscribeTest extends TestCase
{
    private function host() : Address
    {
        return new Address(6379);
    }

    private function go(Closure $closure)
    {
        async($closure)->catch(static function (Throwable $e) {
            echo 'FAILURE ', get_class($e), ' :: ', $e->getMessage(), PHP_EOL;
            echo $e->getTraceAsString();
            exit(1);
        });
    }

    public function testSub1()
    {
        $ws = Promise::all($w1 = Promise::deferred(), $w2 = Promise::deferred());

        $t2 = Promise::deferred();

        $this->go(function () use ($ws, $t2) {
            yield $ws;

            $redis = new Redis($this->host());
            yield $redis->connect();

            $p1 = yield $redis->publish('test1', 123);
            $this->assertEquals(2, $p1);

            yield $t2;

            $p2 = yield $redis->publish('test2', 456);
            $this->assertEquals(2, $p2);

            yield $redis->close();
        });

        $this->go(function () use ($w1, $t2) {
            /**
             * @var Chan $chan
             * @var Message $message
             */
            $redis = new Redis($this->host());
            yield $redis->connect();

            $chan = yield $redis->subscribe('test1');

            $w1->resolve();

            $message = yield $chan->recv();
            $this->assertEquals('test1', $message->channel());
            $this->assertEquals(123, $message->payload());

            $message = $chan->recv();
            $this->assertTrue($message->pended());

            yield $redis->subscribe('test2');

            $t2->resolve();

            $message = yield $message;
            $this->assertEquals('test2', $message->channel());
            $this->assertEquals(456, $message->payload());

            yield $redis->close();
        });

        $this->go(function () use ($w2) {
            /**
             * @var Chan $chan
             * @var Message $message
             */
            $redis = new Redis($this->host());
            yield $redis->connect();

            $chan = yield $redis->pSubscribe('test*');

            $w2->resolve();

            $message = yield $chan->recv();
            $this->assertEquals('test1', $message->channel());
            $this->assertEquals(123, $message->payload());

            $ee = null;
            try {
                yield $redis->publish('xx', 222);
            } catch (Throwable $e) {
                $ee = $e;
            }
            $this->assertInstanceOf(CommandException::class, $ee);

            $message = yield $chan->recv();
            $this->assertEquals('test2', $message->channel());
            $this->assertEquals(456, $message->payload());

            yield $redis->close();
        });

        swoole_event_wait();
    }

    public function testSub2()
    {
        $this->go(function () {
            $redis = new Redis($this->host());
            yield $redis->connect();

            /**
             * @var Chan $chan
             */

            $chan = yield $redis->subscribe('test1');

            $message = $chan->recv();

            yield $redis->close();

            $ee = null;
            $message->catch(static function (Throwable $e) use (&$ee) {
                $ee = $e;
            });
            $this->assertInstanceOf(ChannelClosingException::class, $ee);
        });

        swoole_event_wait();
    }
}
