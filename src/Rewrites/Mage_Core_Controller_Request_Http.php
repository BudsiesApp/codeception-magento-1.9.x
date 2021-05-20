<?php

namespace Optimus\Magento1\Codeception\Rewrites;

class Mage_Core_Controller_Request_Http extends \Mage_Core_Controller_Request_Http
{
    public function getRawBody()
    {
        return json_encode($_POST);
    }
}
