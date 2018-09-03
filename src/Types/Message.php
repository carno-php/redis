<?php
/**
 * Type message
 * User: moyo
 * Date: 2018/8/28
 * Time: 5:29 PM
 */

namespace Carno\Redis\Types;

class Message
{
    /**
     * @var string
     */
    private $channel = null;

    /**
     * @var string
     */
    private $payload = null;

    /**
     * Message constructor.
     * @param string $channel
     * @param string $payload
     */
    public function __construct(string $channel, string $payload)
    {
        $this->channel = $channel;
        $this->payload = $payload;
    }

    /**
     * @return string
     */
    public function channel() : string
    {
        return $this->channel;
    }

    /**
     * @return string
     */
    public function payload() : string
    {
        return $this->payload;
    }
}
