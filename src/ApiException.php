<?php
namespace MochiPay;

class ApiException extends \RuntimeException
{
    private $httpStatus;
    public function __construct($message, $httpStatus = 0)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }
    public function getHttpStatus() { return $this->httpStatus; }
}
