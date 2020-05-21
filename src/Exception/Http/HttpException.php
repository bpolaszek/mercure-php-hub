<?php

namespace BenTools\MercurePHP\Exception\Http;

abstract class HttpException extends \RuntimeException
{
    protected int $statusCode;

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
