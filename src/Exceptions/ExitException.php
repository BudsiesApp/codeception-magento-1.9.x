<?php

namespace Optimus\Magento1\Codeception\Exceptions;


class ExitException extends Exception
{
    private $response;

    public function setResponse($response)
    {
        $this->response = $response;
    }

    public function getResponse()
    {
        return $this->response;
    }
}