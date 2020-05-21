<?php

namespace BenTools\MercurePHP\Exception\Http;

use Throwable;

final class NotFoundHttpException extends HttpException
{
    public function __construct($message = "", $code = 404, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->statusCode = 404;
    }
}
