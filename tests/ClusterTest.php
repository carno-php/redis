<?php
/**
 * Cluster tests
 * User: moyo
 * Date: 2018/8/29
 * Time: 11:17 AM
 */

namespace Carno\Redis\Tests;

use Carno\Cluster\Classify\Scenes;
use Carno\Cluster\Classify\Selector;
use Carno\Cluster\Discovery\Adaptors\DNS;
use Carno\Cluster\Resources;
use function Carno\Coroutine\async;
use Carno\Net\Endpoint;
use Carno\Pool\Options;
use Carno\Redis\Cluster;
use PHPUnit\Framework\TestCase;
use Closure;
use Throwable;

class ClusterTest extends TestCase
{
    private $cluster = null;

    private $redis = null;

    private function cluster()
    {
        ($s = new Selector)->assigning(Scenes::RESOURCE, new DNS);
        return $this->cluster ?? $this->cluster = new Resources($s);
    }

    private function redis()
    {
        $cluster = $this->cluster();
        return $this->redis ?? $this->redis = new class($cluster) extends Cluster {
            protected $server = 'localhost';
            protected $timeout = 500;
            protected function options(Endpoint $endpoint) : Options
            {
                return new Options;
            }
        };
    }

    private function go(Closure $closure)
    {
        async($closure)->catch(static function (Throwable $e) {
            echo 'FAILURE ', get_class($e), ' :: ', $e->getMessage(), PHP_EOL;
            echo $e->getTraceAsString();
            exit(1);
        });
    }

    public function testCommands()
    {
        $this->go(function () {
            $redis = $this->redis();

            yield $this->cluster()->startup()->ready();

            $this->assertEquals('PONG', yield $redis->ping());

            yield $redis->set('hello', 'world', 1);

            $this->assertEquals('world', yield $redis->get('hello'));

            yield $redis->hMSet('hmkey', $set = [
                'a' => 1,
                'b' => 'b',
                'c' => null,
            ]);

            $this->assertEquals($set, yield $redis->hMGet('hmkey', array_keys($set)));

            yield $this->cluster()->release();
        });

        swoole_event_wait();
    }
}
