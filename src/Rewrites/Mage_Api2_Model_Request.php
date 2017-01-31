<?php

namespace Optimus\Magento1\Codeception\Rewrites;

class Mage_Api2_Model_Request extends \Mage_Api2_Model_Request
{
    public function getRawBody()
    {
        return json_encode($_POST);
    }
}