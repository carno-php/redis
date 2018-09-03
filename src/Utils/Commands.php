<?php
/**
 * Commands recognize
 * User: moyo
 * Date: 25/10/2017
 * Time: 3:12 PM
 */

namespace Carno\Redis\Utils;

class Commands
{
    /**
     * @var array
     */
    private const RO_CMDS = [
        'info',
        'smembers',
        'hlen',
        'hmget',
        'srandmember',
        'hvals',
        'randomkey',
        'strlen',
        'dbsize',
        'keys',
        'ttl',
        'lindex',
        'type',
        'llen',
        'dump',
        'scard',
        'echo',
        'lrange',
        'zcount',
        'exists',
        'sdiff',
        'zrange',
        'mget',
        'zrank',
        'get',
        'getbit',
        'getrange',
        'zrevrange',
        'zrevrangebyscore',
        'hexists',
        'object',
        'sinter',
        'zrevrank',
        'hget',
        'zscore',
        'hgetall',
        'sismember',
    ];

    /**
     * @param string $cmd
     * @return bool
     */
    public static function readonly(string $cmd) : bool
    {
        return in_array(strtolower($cmd), self::RO_CMDS);
    }
}
