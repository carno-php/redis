<?php

namespace TESTS;

require __DIR__ . '/../vendor/autoload.php';

use Carno\Cluster\Discover\Adaptors\DNS;
use Carno\Cluster\Resources;
use function Carno\Coroutine\go;
use Carno\Pool\Options;
use Carno\Redis\Cluster;

$cluster = new Resources(new DNS);

$redis = new class($cluster) extends Cluster {
    protected $server = 'localhost';
    protected $timeout = 500;
    protected function options(string $service) : Options
    {
        return new Options;
    }
};

go(static function () use ($cluster, $redis) {
    yield $cluster->startup()->ready();

    /**
     * @var \Redis $redis
     */

    // ping

    logger()->debug('INIT', ['ping' => yield $redis->ping()]);

    // set/get

    yield $redis->set('hello', 'world', 1);

    logger()->debug('GET', ['hello' => yield $redis->get('hello')]);

    // hmset

    yield $redis->hMSet('hmkey', $set = [
        'a' => 1,
        'b' => 'b',
        'c' => null,
    ]);

    logger()->debug('HMSET', ['got' => yield $redis->hMGet('hmkey', array_keys($set))]);

    // end

    yield $cluster->release();
});
