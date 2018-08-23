<?php
/**
 * Connection exception
 * User: moyo
 * Date: 2018/7/23
 * Time: 5:46 PM
 */

namespace Carno\Redis\Exception;

use Carno\Pool\Contracts\Broken;
use RuntimeException;

abstract class ConnectionException extends RuntimeException implements Broken
{

}
