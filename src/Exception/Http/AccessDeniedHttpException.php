<?php

namespace BenTools\MercurePHP\Exception\Http;

use Throwable;

final class AccessDeniedHttpException extends HttpException
{
    public function __construct($message = "", $code = 403, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->statusCode = 403;
    }
}
