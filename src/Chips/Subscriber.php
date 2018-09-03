<?php
/**
 * Subscribes API
 * User: moyo
 * Date: 2018/8/28
 * Time: 5:08 PM
 */

namespace Carno\Redis\Chips;

use Carno\Channel\Chan;
use Carno\Channel\Channel;
use Carno\Promise\Promise;
use Carno\Promise\Promised;
use Carno\Redis\Types\Message;

trait Subscriber
{
    /**
     * @var Chan
     */
    private $consumer = null;

    /**
     * @var bool
     */
    private $subscribed = false;

    /**
     * @var Promised
     */
    private $acknowledge = null;

    /**
     * @param string ...$channels
     * @return Chan
     */
    public function subscribe(string ...$channels)
    {
        $this->command('subscribe', $channels);
        yield $this->acknowledge();
        return $this->consuming();
    }

    /**
     * @param string ...$patterns
     * @return Chan
     */
    public function pSubscribe(string ...$patterns)
    {
        $this->command('psubscribe', $patterns);
        yield $this->acknowledge();
        return $this->consuming();
    }

    /**
     * @return Chan
     */
    private function consuming() : Chan
    {
        if ($this->consumer) {
            return $this->consumer;
        }

        /**
         * @var Promised $closed
         */

        $closed = $this->closed();

        $closed->then(function () {
            $this->consumer && $this->consumer->close();
        });

        return $this->consumer = new Channel;
    }

    /**
     * @return bool
     */
    private function subscribed() : bool
    {
        return $this->subscribed;
    }

    /**
     * @return Promised
     */
    private function acknowledge() : Promised
    {
        ($this->acknowledge = Promise::deferred())->then(function () {
            unset($this->acknowledge);
        });
        return $this->acknowledge;
    }

    /**
     * @param array $recv
     */
    private function messaging(array $recv) : void
    {
        switch ($recv[0]) {
            case 'message':
                [1 => $channel, 2 => $payload] = $recv;
                break;
            case 'pmessage':
                [2 => $channel, 3 => $payload] = $recv;
                break;
            case 'subscribe':
            case 'psubscribe':
                $this->subscribed = true;
                $this->acknowledge->resolve();
                return;
            case 'unsubscribe':
            case 'punsubscribe':
                $this->subscribed = false;
                $this->acknowledge->resolve();
                unset($this->consumer);
                return;
        }

        if (isset($channel) && isset($payload)) {
            $this->consuming()->send(new Message($channel, $payload));
        }
    }
}
