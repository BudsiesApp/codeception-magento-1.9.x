<?php

namespace Optimus\Magento1\Codeception\Rewrites;

class Mage_Admin_Model_Session extends \Mage_Admin_Model_Session
{
    public function __construct(array $parameters = [])
    {
        if (!$parameters['response']) {
            $parameters['response'] = new Mage_Core_Controller_Response_Http();
        }

        parent::__construct($parameters);
    }
}