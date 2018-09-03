<?php
/**
 * PHP Redis compatible
 * User: moyo
 * Date: 23/02/2018
 * Time: 2:30 PM
 */

namespace Carno\Redis\Chips;

trait Compatible
{
    /**
     * @param string $key
     * @param string $val
     * @param mixed $more
     * @return bool
     */
    public function set(string $key, string $val, $more = null)
    {
        $cmd = 'set';

        $args = [$key];

        if (is_null($more)) {
            // set(key, val)
            array_push($args, $val);
        } elseif (is_numeric($more)) {
            // set(key, val, timeout)
            $cmd = 'setex';
            array_push($args, $more, $val);
        } elseif (is_array($more) && $more) {
            // set(key, val, [nx|xx, ex|px => timeout])
            array_push($args, $val, $more[0] ?? 'nx');

            if (isset($more['ex'])) {
                array_push($args, 'ex', $more['ex']);
            } elseif (isset($more['px'])) {
                array_push($args, 'px', $more['px']);
            }
        }

        return (yield $this->__call($cmd, $args)) === 'OK';
    }

    /**
     * @param string $key
     * @param array $map
     * @return bool
     */
    public function hMSet(string $key, array $map)
    {
        $args = [$key];

        foreach ($map as $k => $v) {
            array_push($args, $k, $v);
        }

        return (yield $this->__call('hmset', $args)) === 'OK';
    }

    /**
     * @param string $key
     * @param array $fds
     * @return array
     */
    public function hMGet(string $key, array $fds)
    {
        $args = $fds;

        array_unshift($args, $key);

        $got = yield $this->__call('hmget', $args);

        $map = [];

        foreach ($got as $slot => $val) {
            $map[$fds[$slot]] = $val;
        }

        return $map;
    }

    /**
     * @param string $key
     * @return array
     */
    public function hGetAll(string $key)
    {
        $got = yield $this->__call('hgetall', [$key]);

        $map = [];

        for ($i = 0; $i < count($got); $i += 2) {
            $map[$got[$i]] = $got[$i + 1];
        }

        return $map;
    }

    /**
     * @param string $lua
     * @param array $args
     * @param int $keys
     * @return mixed
     */
    public function eval(string $lua, array $args = [], int $keys = 0)
    {
        array_unshift($args, $lua, $keys);

        return $this->__call('eval', $args);
    }
}
