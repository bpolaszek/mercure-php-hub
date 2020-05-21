<?php

namespace BenTools\MercurePHP\Exception\Http;

use Throwable;

final class BadRequestHttpException extends HttpException
{
    public function __construct($message = "", $code = 400, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->statusCode = 400;
    }
}
