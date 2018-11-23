<?php
/**
 * Redis cluster
 * User: moyo
 * Date: 24/10/2017
 * Time: 4:28 PM
 */

namespace Carno\Redis;

use Carno\Cluster\Classify\Scenes;
use Carno\Cluster\Contracts\Tags;
use Carno\Cluster\Managed;
use Carno\Cluster\Resources;
use Carno\DSN\DSN;
use Carno\Net\Endpoint;
use Carno\Pool\Options;
use Carno\Pool\Pool;
use Carno\Pool\Wrapper\SAR;
use Carno\Promise\Promised;
use Carno\Redis\Utils\Commands;

/**
 * @mixin Redis
 */
abstract class Cluster extends Managed
{
    use SAR;

    /**
     * @var array
     */
    protected $tags = [Tags::MASTER, Tags::SLAVE];

    /**
     * @var string
     */
    protected $type = 'redis';

    /**
     * @var int
     */
    protected $port = 6379;

    /**
     * @var int
     */
    protected $timeout = 3500;

    /**
     * Cluster constructor.
     * @param Resources $resources
     */
    public function __construct(Resources $resources)
    {
        $resources->initialize(Scenes::RESOURCE, $this->type, $this->server, $this);
    }

    /**
     * @param Endpoint $endpoint
     * @return Options
     */
    abstract protected function options(Endpoint $endpoint) : Options;

    /**
     * @param Endpoint $endpoint
     * @return Pool
     */
    protected function connecting(Endpoint $endpoint) : Pool
    {
        $node = $endpoint->address();

        $vid = "{$this->type}:{$this->server}";

        $dsn = new DSN(
            $node->port() <= 0
                ? $node->host()
                : sprintf('redis://%s:%d', $node->host(), $node->port())
        );

        $timeouts = new Timeouts(
            $dsn->option('connect', 1500),
            $dsn->option('execute', $this->timeout)
        );

        return new Pool($this->options($endpoint), static function () use ($timeouts, $dsn, $vid) {
            return new Redis(
                sprintf('%s:%d', $dsn->host(), $dsn->port()),
                $dsn->pass() ?: null,
                $dsn->option('db'),
                $timeouts,
                $vid
            );
        }, $vid);
    }

    /**
     * @param Pool $connected
     * @return Promised
     */
    protected function disconnecting($connected) : Promised
    {
        return $connected->shutdown();
    }

    /**
     * @param string $name
     * @param $arguments
     * @return mixed
     */
    public function __call(string $name, $arguments)
    {
        return yield $this->sarRun(
            $this->picking(
                $this->clustered() && Commands::readonly($name)
                    ? Tags::SLAVE
                    : Tags::MASTER
            ),
            $name,
            $arguments
        );
    }
}
