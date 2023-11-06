<?php
namespace App\Controllers\V1;

use Datto\JsonRpc\Evaluator;
use Datto\JsonRpc\Exceptions\ArgumentException;
use Datto\JsonRpc\Exceptions\MethodException;
use Datto\JsonRpc\Examples\Library\Math;

class RpcApi implements Evaluator
{
    public function evaluate($method, $arguments)
    {
        if ($method === 'add') {
            return self::add($arguments);
        }
        if ($method === 'implementationError') {
            return self::implementationError($arguments);
        }
        if ($method === 'InternalError') {
            return self::InternalError();
        }
        

        throw new MethodException();
    }

    private static function add($arguments)
    {
        @list($a, $b) = $arguments;

        if (!is_int($a) || !is_int($b)) {
            throw new ArgumentException();
        }
        return $a+$b;
    }

    private static function implementationError($arguments)
    {
        throw new \Datto\JsonRpc\Exceptions\ImplementationException(-32099, @$arguments[0]);
    }

    private static function InternalError()
    {
        throw new \App\Controllers\V1\InternalErrorException();
    }
}

?>
<?php

namespace App\Controllers\V1;

use Datto\JsonRpc\Responses\ErrorResponse;

/**
 * If a method cannot be called (e.g. if the method doesn't exist, or is a
 * private method), then you should throw a "MethodException".
 *
 * If the method is callable, but the user-supplied arguments are incompatible
 * with the method's type signature, or an argument is invalid, then you should
 * throw an "ArgumentException".
 *
 * If the method is callable, and the user-supplied arguments are valid, but an
 * issue arose when the server-side application was evaluating the method, then
 * you should throw an "ApplicationException".
 *
 * If you've extended this JSON-RPC 2.0 library, and an issue arose in your
 * implementation of the JSON-RPC 2.0 specifications, then you should throw an
 * "ImplementationException".
 *
 * @link http://www.jsonrpc.org/specification#error_object
 */
class InternalErrorException extends \Datto\JsonRpc\Exceptions\Exception
{
    public function __construct()
    {
        parent::__construct('Internal error', -32603);
    }
}
