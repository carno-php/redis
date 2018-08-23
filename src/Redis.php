<?php
/**
 * Redis client
 * User: moyo
 * Date: 09/08/2017
 * Time: 3:02 PM
 */

namespace Carno\Redis;

use function Carno\Coroutine\await;
use function Carno\Coroutine\ctx;
use Carno\Pool\Managed;
use Carno\Pool\Poolable;
use Carno\Promise\Promise;
use Carno\Promise\Promised;
use Carno\Redis\Chips\PRCompatible;
use Carno\Redis\Exception\CommandException;
use Carno\Redis\Exception\ConnectingException;
use Carno\Redis\Exception\TimeoutException;
use Carno\Redis\Exception\UplinkException;
use Carno\Tracing\Contracts\Vars\EXT;
use Carno\Tracing\Contracts\Vars\TAG;
use Carno\Tracing\Standard\Endpoint;
use Carno\Tracing\Utils\SpansCreator;
use Swoole\Redis as SWRedis;

class Redis implements Poolable
{
    use Managed, SpansCreator, PRCompatible;

    /**
     * @var Timeouts
     */
    private $timeout = null;

    /**
     * @var string
     */
    private $named = null;

    /**
     * @var string
     */
    private $host = null;

    /**
     * @var int
     */
    private $port = null;

    /**
     * @var string
     */
    private $auth = null;

    /**
     * @var int
     */
    private $slot = null;

    /**
     * @var SWRedis
     */
    private $link = null;

    /**
     * Redis constructor.
     * @param string $target
     * @param string $auth
     * @param int $slot
     * @param Timeouts $timeout
     * @param string $named
     */
    public function __construct(
        string $target,
        string $auth = null,
        int $slot = null,
        Timeouts $timeout = null,
        string $named = 'redis'
    ) {
        if (substr($target, 0, 6) === 'unix:/') {
            $this->host = $target;
            $this->port = null;
        } else {
            list($this->host, $this->port) = explode(':', $target);
        }

        $this->auth = $auth;
        $this->slot = $slot;

        $this->named = $named;
        $this->timeout = $timeout ?? new Timeouts;

        $options = ['timeout' => round($this->timeout->connect() / 1000, 3)];

        is_null($this->auth) || $options['password'] = $this->auth;
        is_null($this->slot) || $options['database'] = $this->slot;

        $this->link = new SWRedis($options);
    }

    /**
     * @return Promised
     */
    public function connect() : Promised
    {
        $this->link->on('close', function () {
            unset($this->link);
            $this->closed()->resolve();
        });

        return new Promise(function (Promised $promise) {
            $executed = $this->link->connect(
                $this->host,
                $this->port,
                static function (SWRedis $c, bool $success) use ($promise) {
                    $success
                        ? $promise->resolve()
                        : $promise->throw(new ConnectingException($c->errMsg, $c->errCode))
                    ;
                }
            );
            if (false === $executed) {
                throw new ConnectingException('Unknown failure');
            }
        });
    }

    /**
     * @return Promised
     */
    public function heartbeat() : Promised
    {
        return new Promise(function (Promised $promised) {
            $this->link->__call('ping', [function (SWRedis $c, $result) use ($promised) {
                $result === false
                    ? $promised->reject()
                    : $promised->resolve()
                ;
            }]);
        });
    }

    /**
     * @return Promised
     */
    public function close() : Promised
    {
        $this->link->close();
        return $this->closed();
    }

    /**
     * @param $name
     * @param $arguments
     * @return Promised
     */
    public function __call($name, $arguments)
    {
        $this->traced() && $this->newSpan($ctx = clone yield ctx(), $name, [
            TAG::SPAN_KIND => TAG::SPAN_KIND_RPC_CLIENT,
            TAG::DATABASE_TYPE => 'redis',
            TAG::DATABASE_INSTANCE => sprintf('%s:%d', $this->host, $this->port),
            TAG::DATABASE_STATEMENT => sprintf('%s %s', $name, $arguments[0] ?? ''),
            EXT::REMOTE_ENDPOINT => new Endpoint($this->named),
        ]);

        $executor = function ($fn) use ($name, $arguments) {
            array_push($arguments, $fn);
            if (false === $this->link->__call($name, $arguments)) {
                throw new UplinkException('Unknown failure');
            }
        };

        $receiver = static function (SWRedis $c, $result) {
            if ($result === false && $c->errCode > 0) {
                throw new CommandException($c->errMsg, $c->errCode);
            } else {
                return $result;
            }
        };

        return $this->finishSpan(
            await(
                $executor,
                $receiver,
                $this->timeout->execute(),
                TimeoutException::class,
                sprintf('%s:%d [->] %s', $this->host, $this->port, $name)
            ),
            $ctx ?? null
        );
    }
}
