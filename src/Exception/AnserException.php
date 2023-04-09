<?php

namespace SDPMlab\Anser\Exception;

use Throwable;
use SDPMlab\Anser\Exception\AnserExceptionInterface;

class AnserException extends \Exception implements AnserExceptionInterface
{
    public function __construct(string $message, int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}
